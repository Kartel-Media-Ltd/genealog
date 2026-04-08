# Backup & Recovery Plan — Genealog

**Właściciel dokumentu:** [DPO / Head of Ops]
**Ostatnia aktualizacja:** 2026-04-08
**Review cadence:** kwartalnie (test DR), rocznie (rewizja strategii)
**Podstawa prawna:** NIS2 Dyrektywa (UE) 2022/2555 Art. 21.2(c) — business continuity

---

## Cele

- **RTO (Recovery Time Objective):** 4 godziny — maksymalny dopuszczalny czas niedostępności usługi po awarii
- **RPO (Recovery Point Objective):** 24 godziny — maksymalna ilość danych która może zostać utracona (okno między backupami)

---

## Strategia 3-2-1

Każdy zestaw danych posiada:

- **3 kopie:** primary DB + local backup + offsite backup
- **2 media:** dysk SSD (primary/local) + obiektowe storage S3-compatible (offsite)
- **1 offsite:** minimum jedna kopia w innej lokalizacji geograficznej (inna strefa dostępności, najlepiej inne państwo w EU)

---

## Co podlega backupowi

### 1. MariaDB (primary)
Zawiera: users, trees, persons, relationships, invitations, notifications, media metadata,
source_audit_log, global_person_index, password_resets, rate_limits.

**Strategy:**
- **Full backup:** codziennie o 2:00 UTC (poza godzinami szczytu)
- **Method:** `mariadb-dump --single-transaction --routines --triggers --events`
- **Binary logs:** włączone, rotacja 7 dni — umożliwia point-in-time recovery
- **Retention:**
  - Daily fulls: **30 dni**
  - Monthly fulls (pierwszy dzień miesiąca): **12 miesięcy**
  - Binary logs: **7 dni**

**Skrypt:** `bin/backup-db.sh` (TODO — utworzyć)

### 2. Filesystem — `storage/media/`
Zdjęcia i dokumenty dodane przez użytkowników (po konwersji do WebP).

**Strategy:**
- **Method:** `rsync -az --delete` codziennie o 3:00 UTC → offsite S3
- **Retention:** 30 dni (z wersjonowaniem obiektowym S3)

### 3. Configuration
- `config/config.php` — w git repo (backup przez GitHub)
- `.env.local` — NIE w git, ręczny backup do sejfu password managera + offsite vault
- `migrations/` — w git repo

### 4. Audit logs (systemowe)
- `/var/log/genealog/*.log` — cron jobs, error_log
- **Retention:** 90 dni (zgodnie z NIS2 Art. 32)

---

## Procedury recovery

### Scenariusz A — Awaria bazy danych (corruption, data loss)

1. **Zatrzymaj aplikację:**
   ```bash
   docker stop genealog-app
   ```
2. **Zidentyfikuj ostatni udany backup:**
   ```bash
   ls -lat /backups/mariadb/ | head -5
   ```
3. **Przywróć full backup:**
   ```bash
   docker exec -i mariadb_docker mariadb -u root -p$MYSQL_ROOT_PW \
     < /backups/mariadb/genealog-YYYY-MM-DD.sql
   ```
4. **Odtwórz binary logs do punktu przed awarią:**
   ```bash
   docker exec -i mariadb_docker mariadb-binlog \
     --start-datetime="2026-04-08 00:00:00" \
     --stop-datetime="2026-04-08 09:30:00" \
     /var/log/mysql/binlog.00000X \
     | docker exec -i mariadb_docker mariadb -u root -p$MYSQL_ROOT_PW genealog
   ```
5. **Zweryfikuj integralność:**
   ```bash
   docker exec mariadb_docker mariadb -u genealog -p$PW genealog -e "
     SELECT COUNT(*) FROM users;
     SELECT COUNT(*) FROM trees;
     SELECT COUNT(*) FROM persons;
     SELECT MAX(created_at) FROM source_audit_log;
   "
   ```
6. **Test smoke z aplikacji:** login → lista drzew → odczyt osoby.
7. **Uruchom aplikację:**
   ```bash
   docker start genealog-app
   ```

### Scenariusz B — Corruption filesystem (`storage/media/`)

1. **Zatrzymaj aplikację.**
2. **Przywróć pliki z S3:**
   ```bash
   aws s3 sync s3://genealog-backup/media/ /var/genealog/storage/media/ --delete
   ```
3. **Permissions:** `chown -R www-data:www-data storage/`
4. **Test:** załaduj dowolną osobę z zdjęciem w aplikacji.

### Scenariusz C — Total loss (rebuild from scratch)

1. **Provision nowy serwer** (TODO: infrastructure/terraform/ — brakuje IaC)
2. **Install stack:**
   ```bash
   apt install php8.2 php8.2-pdo php8.2-mysql php8.2-mbstring php8.2-gd php8.2-zip
   docker run -d --name mariadb_docker mariadb:11
   ```
3. **Clone repo:**
   ```bash
   git clone https://github.com/[org]/genealog /var/www/genealog
   cd /var/www/genealog && composer install --no-dev
   ```
4. **Przywróć DB:** wg Scenariusza A (kroki 2-4).
5. **Przywróć filesystem:** wg Scenariusza B (krok 2).
6. **Uruchom migracje weryfikująco:**
   ```bash
   for f in migrations/*.sql; do
     docker exec -i mariadb_docker mariadb -u genealog -p$PW genealog < "$f" 2>&1 || true
   done
   ```
   (IF NOT EXISTS chroni przed duplikacją)
7. **Start aplikacji** + test smoke.

---

## Testowanie DR

### Kwartalnie
- **Scenariusz A** na staging env (restore test z ostatniego daily backup)
- Weryfikacja że backup jest prawidłowo pobierany i restore działa
- Pomiar faktycznego RTO (powinno być <4h)

### Rocznie (minimum)
- **Scenariusz C** — pełny DR drill na nowym serwerze
- Test wszystkich integracji (SMTP, file storage, cron jobs)
- Rewizja strategii (nowe tabele? większe wolumen?)
- Aktualizacja dokumentu

### Log testów
`docs/operations/dr-tests.log` — chronologia testów z wynikami.

---

## Monitoring

### Alerty krytyczne (Slack + Email):
- Brak udanego backupu DB przez >36h
- Brak udanego rsync media przez >36h
- Binary log gap >1h (missing events)
- Restore test fail

### Alerty ostrzegawcze:
- Backup rozmiar <90% średniej z ostatnich 7 dni (podejrzane: truncation?)
- Free space na partition backupu <20%

### Dashboard
- **Grafana URL:** [TBD — do utworzenia]
- Metryki: last backup timestamp, backup size, restore test age, DB size growth

---

## Role i odpowiedzialności

| Rola | Odpowiedzialność |
|------|------------------|
| **Ops lead** | Codzienna weryfikacja backupów, incident response |
| **DPO** | Zatwierdzenie strategii, audyt compliance RODO/NIS2 |
| **Development team** | Testowanie procedur recovery, utrzymanie skryptów |
| **On-call** | First response przy alertach, eskalacja do Ops lead |

---

## Kontakty w razie awarii

- **Ops lead:** [EMAIL / tel]
- **DPO:** [EMAIL]
- **MariaDB support:** [tel komercyjny, jeśli dotyczy]
- **Cloud provider:** [AWS/Hetzner/etc. — kontakt serwisowy]
- **Zespół developmentu:** [Slack #genealog-ops]

---

## Historia zmian

- **2026-04-08** — Pierwsza wersja (ZAD-3.3 backend-3)

## TODO

- [ ] Utworzyć `bin/backup-db.sh`
- [ ] Utworzyć `bin/restore-db.sh` (template dla Scenariusza A)
- [ ] Konfiguracja Grafana dashboard
- [ ] IaC w `infrastructure/terraform/` dla Scenariusza C
- [ ] Pierwszy DR drill — deadline [TBD]
- [ ] Szkolenie zespołu z procedur recovery
