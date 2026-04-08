-- Migration 009: Person Discovery — immutable names in global_person_index (review I9)
--
-- Problem: CrossTreeMatchSource JOINował `persons` żeby pobrać `first_name`/`last_name`,
-- więc zmiana nazwiska w obcym drzewie (albo visibility → private bez jeszcze niezrobionego
-- unindex) ujawniałaby aktualne, mutowalne PII. Rozwiązanie: zamrozić name w global_person_index
-- przy indeksowaniu.
--
-- Uruchomić:
--   source .env.local && docker exec -i mariadb_docker mariadb \
--     -u $DATABASE_USER -p$DATABASE_PASSWORD $DATABASE_NAME < migrations/009_discovery_immutable_names.sql

SET NAMES utf8mb4;

-- IF NOT EXISTS — migracja idempotentna
ALTER TABLE `global_person_index`
    ADD COLUMN IF NOT EXISTS `first_name` VARCHAR(100) NULL AFTER `name_soundex`,
    ADD COLUMN IF NOT EXISTS `last_name`  VARCHAR(100) NULL AFTER `first_name`;

-- Backfill z aktualnych wartości w persons (jednorazowe, przy kolejnym indexPerson i tak zostaną
-- zaktualizowane przez INSERT...ON DUPLICATE KEY UPDATE).
UPDATE `global_person_index` gpi
JOIN `persons` p ON p.id = gpi.person_id
SET gpi.first_name = p.first_name,
    gpi.last_name  = p.last_name
WHERE gpi.first_name IS NULL;
