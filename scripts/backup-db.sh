#!/usr/bin/env bash
# =============================================================================
# backup-db.sh — Backup bazy danych MariaDB (Genealog)
# =============================================================================
#
# Strategia: full dump przez mariadb-dump, gzip, retencja 30 dni (daily) + 12 miesięcy (monthly).
# Referencja: docs/operations/backup.md
# NIS2 Art. 21.2(c) — business continuity
#
# Uruchomienie ręczne:
#   bash scripts/backup-db.sh
#
# Cron (dodaj do infrastructure/cron.example):
#   0 2 * * * www-data /bin/bash /var/www/genealog/scripts/backup-db.sh >> /var/log/genealog/backup-db.log 2>&1
#
# Zmienne środowiskowe (opcjonalne nadpisanie):
#   BACKUP_DIR     — katalog docelowy (domyślnie: /backups/mariadb)
#   CONTAINER      — nazwa kontenera Docker (domyślnie: mariadb_docker)
#   DB_MIN_SIZE    — minimalny rozmiar dumpa w bajtach (domyślnie: 1024)
# =============================================================================
set -euo pipefail

# ---------------------------------------------------------------------------
# Konfiguracja
# ---------------------------------------------------------------------------
ROOT_DIR="$(cd "$(dirname "$0")/.." && pwd)"
ENV_FILE="${ROOT_DIR}/.env.local"

if [[ ! -f "$ENV_FILE" ]]; then
    echo "ERROR: brak pliku .env.local w $ROOT_DIR" >&2
    exit 1
fi

# Załaduj zmienne środowiskowe (bez eksportowania do środowiska bieżącego procesu)
# shellcheck disable=SC1090
source "$ENV_FILE"

BACKUP_DIR="${BACKUP_DIR:-/backups/mariadb}"
CONTAINER="${CONTAINER:-mariadb_docker}"
DB_MIN_SIZE="${DB_MIN_SIZE:-1024}"
RETENTION_DAILY=30    # dni — retencja codziennych backupów
RETENTION_MONTHLY=365 # dni — retencja backupów z 1. dnia miesiąca

# Wymagane zmienne z .env.local
: "${DATABASE_NAME:?Brak DATABASE_NAME w .env.local}"
: "${DATABASE_USER:?Brak DATABASE_USER w .env.local}"
: "${DATABASE_PASSWORD:?Brak DATABASE_PASSWORD w .env.local}"

TIMESTAMP=$(date +%Y%m%d_%H%M%S)
DAY_OF_MONTH=$(date +%d)
FILENAME="genealog-${TIMESTAMP}.sql.gz"
BACKUP_PATH="${BACKUP_DIR}/${FILENAME}"

log() {
    echo "[$(date -u +%Y-%m-%dT%H:%M:%SZ)] $*"
}

# ---------------------------------------------------------------------------
# Sprawdź dostępność kontenera Docker
# ---------------------------------------------------------------------------
if ! docker ps --format '{{.Names}}' | grep -q "^${CONTAINER}$"; then
    log "ERROR: kontener Docker '${CONTAINER}' nie jest uruchomiony" >&2
    exit 1
fi

# ---------------------------------------------------------------------------
# Utwórz katalog backupu
# ---------------------------------------------------------------------------
mkdir -p "$BACKUP_DIR"
log "Katalog backupu: $BACKUP_DIR"

# ---------------------------------------------------------------------------
# Wykonaj dump bazy danych
# ---------------------------------------------------------------------------
log "Rozpoczynam dump: $DATABASE_NAME → $FILENAME"

docker exec "$CONTAINER" mariadb-dump \
    -u "$DATABASE_USER" \
    -p"${DATABASE_PASSWORD}" \
    --single-transaction \
    --routines \
    --triggers \
    --events \
    --set-gtid-purged=OFF \
    --add-drop-database \
    --databases "$DATABASE_NAME" \
    2>/dev/null \
    | gzip -9 > "$BACKUP_PATH"

# ---------------------------------------------------------------------------
# Weryfikacja rozmiaru dumpa
# ---------------------------------------------------------------------------
# Obsługa różnic między macOS (stat -f%z) i Linux (stat -c%s)
if [[ "$(uname)" == "Darwin" ]]; then
    SIZE=$(stat -f%z "$BACKUP_PATH")
else
    SIZE=$(stat -c%s "$BACKUP_PATH")
fi

if [[ "$SIZE" -lt "$DB_MIN_SIZE" ]]; then
    log "ERROR: plik backupu zbyt mały (${SIZE} bajtów < ${DB_MIN_SIZE}), usuwam i kończę z błędem" >&2
    rm -f "$BACKUP_PATH"
    exit 1
fi

log "Backup OK: $FILENAME (${SIZE} bajtów)"

# ---------------------------------------------------------------------------
# Rotacja plików — retencja dzienna (30 dni, z wyjątkiem monthly)
# Monthly = pliki z 1. dnia miesiąca (pattern: genealog-YYYYMM01_*.sql.gz)
# ---------------------------------------------------------------------------
DELETED_DAILY=0
while IFS= read -r -d '' OLD_FILE; do
    # Pomiń pliki monthly (1. dzień miesiąca)
    BASENAME=$(basename "$OLD_FILE")
    if [[ "$BASENAME" =~ genealog-[0-9]{4}[0-9]{2}01_.*\.sql\.gz ]]; then
        continue
    fi
    rm -f "$OLD_FILE"
    DELETED_DAILY=$((DELETED_DAILY + 1))
done < <(find "$BACKUP_DIR" -name "genealog-*.sql.gz" -mtime +${RETENTION_DAILY} -print0)

[[ "$DELETED_DAILY" -gt 0 ]] && log "Rotacja dzienna: usunięto $DELETED_DAILY starych plików"

# ---------------------------------------------------------------------------
# Rotacja plików — retencja monthly (12 miesięcy)
# ---------------------------------------------------------------------------
DELETED_MONTHLY=0
while IFS= read -r -d '' OLD_FILE; do
    rm -f "$OLD_FILE"
    DELETED_MONTHLY=$((DELETED_MONTHLY + 1))
done < <(find "$BACKUP_DIR" -name "genealog-*.sql.gz" -mtime +${RETENTION_MONTHLY} -print0)

[[ "$DELETED_MONTHLY" -gt 0 ]] && log "Rotacja monthly: usunięto $DELETED_MONTHLY starych plików"

# ---------------------------------------------------------------------------
# Podsumowanie
# ---------------------------------------------------------------------------
TOTAL=$(find "$BACKUP_DIR" -name "genealog-*.sql.gz" | wc -l | tr -d ' ')
log "Backup zakończony pomyślnie. Pliki w $BACKUP_DIR: $TOTAL"
exit 0
