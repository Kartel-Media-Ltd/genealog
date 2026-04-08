#!/usr/bin/env bash
# =============================================================================
# restore-db.sh — Przywracanie bazy danych MariaDB (Genealog)
# =============================================================================
#
# Szablon procedury recovery — Scenariusz A z docs/operations/backup.md
#
# OSTRZEŻENIE: Ten skrypt NADPISUJE istniejącą bazę danych!
#              Wykonaj to wyłącznie po potwierdzeniu z Ops lead.
#
# Użycie:
#   bash scripts/restore-db.sh [PLIK_BACKUP.sql.gz]
#   bash scripts/restore-db.sh                     # wybór interaktywny
# =============================================================================
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "$0")/.." && pwd)"
ENV_FILE="${ROOT_DIR}/.env.local"
BACKUP_DIR="${BACKUP_DIR:-/backups/mariadb}"
CONTAINER="${CONTAINER:-mariadb_docker}"

if [[ ! -f "$ENV_FILE" ]]; then
    echo "ERROR: brak pliku .env.local w $ROOT_DIR" >&2
    exit 1
fi

# shellcheck disable=SC1090
source "$ENV_FILE"

: "${DATABASE_NAME:?Brak DATABASE_NAME w .env.local}"
: "${DATABASE_USER:?Brak DATABASE_USER w .env.local}"
: "${DATABASE_PASSWORD:?Brak DATABASE_PASSWORD w .env.local}"

log() { echo "[$(date -u +%Y-%m-%dT%H:%M:%SZ)] $*"; }

# ---------------------------------------------------------------------------
# Wybór pliku backupu
# ---------------------------------------------------------------------------
if [[ $# -ge 1 ]]; then
    BACKUP_FILE="$1"
else
    echo ""
    echo "Dostępne backupy (od najnowszego):"
    echo "-----------------------------------"
    if ! ls -t "$BACKUP_DIR"/genealog-*.sql.gz 2>/dev/null; then
        echo "Brak plików w $BACKUP_DIR" >&2
        exit 1
    fi
    echo ""
    read -r -p "Podaj pełną ścieżkę do pliku backupu: " BACKUP_FILE
fi

if [[ ! -f "$BACKUP_FILE" ]]; then
    echo "ERROR: plik nie istnieje: $BACKUP_FILE" >&2
    exit 1
fi

# ---------------------------------------------------------------------------
# Potwierdzenie — OSTRZEŻENIE
# ---------------------------------------------------------------------------
BASENAME=$(basename "$BACKUP_FILE")
echo ""
echo "================================================================"
echo "  OSTRZEŻENIE: OPERACJA NIEODWRACALNA"
echo "================================================================"
echo "  Plik backupu : $BASENAME"
echo "  Baza danych  : $DATABASE_NAME"
echo "  Kontener     : $CONTAINER"
echo "  Serwer       : $(hostname)"
echo "================================================================"
echo ""
read -r -p "Wpisz 'RESTORE' aby potwierdzić: " CONFIRM

if [[ "$CONFIRM" != "RESTORE" ]]; then
    log "Restore anulowany przez użytkownika."
    exit 0
fi

# ---------------------------------------------------------------------------
# Sprawdź dostępność kontenera
# ---------------------------------------------------------------------------
if ! docker ps --format '{{.Names}}' | grep -q "^${CONTAINER}$"; then
    log "ERROR: kontener Docker '${CONTAINER}' nie jest uruchomiony" >&2
    exit 1
fi

# ---------------------------------------------------------------------------
# Restore
# ---------------------------------------------------------------------------
log "Rozpoczynam restore z: $BASENAME"

gunzip -c "$BACKUP_FILE" \
    | docker exec -i "$CONTAINER" mariadb \
        -u "$DATABASE_USER" \
        -p"${DATABASE_PASSWORD}" \
        2>/dev/null

log "Restore zakończony."

# ---------------------------------------------------------------------------
# Weryfikacja integralności (podstawowa)
# ---------------------------------------------------------------------------
log "Weryfikacja integralności..."
docker exec "$CONTAINER" mariadb \
    -u "$DATABASE_USER" \
    -p"${DATABASE_PASSWORD}" \
    "$DATABASE_NAME" \
    --execute "
        SELECT 'users'    AS tabela, COUNT(*) AS wiersze FROM users
        UNION ALL
        SELECT 'trees',   COUNT(*) FROM trees
        UNION ALL
        SELECT 'persons', COUNT(*) FROM persons
        UNION ALL
        SELECT 'last_audit', COALESCE(MAX(created_at), 'brak') FROM source_audit_log;
    " \
    2>/dev/null

log "Restore i weryfikacja zakończone. Uruchom smoke test aplikacji."
log "Następny krok: docker start genealog-app (jeśli był zatrzymany)"
exit 0
