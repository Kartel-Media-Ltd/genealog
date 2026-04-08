-- Migration 007: Admin extras — session invalidation + view actions audit
-- Uruchomić: source .env.local && docker exec -i mariadb_docker mariadb \
--   -u $DATABASE_USER -p$DATABASE_PASSWORD $DATABASE_NAME < migrations/007_admin_extras.sql
--
-- Idempotentna — bezpieczna do ponownego uruchomienia (IF NOT EXISTS na kolumnie i indeksach).

-- Dodajemy session_version do users — inkrementacja przy block/demote
-- powoduje że istniejące sesje stają się nieważne (AdminMiddleware sprawdza)
ALTER TABLE users
    ADD COLUMN IF NOT EXISTS session_version INT NOT NULL DEFAULT 0 AFTER is_blocked;

-- Indeksy dla filtrowania logów po typie akcji + dacie
ALTER TABLE admin_logs
    ADD INDEX IF NOT EXISTS idx_admin_logs_target (target_type, target_id),
    ADD INDEX IF NOT EXISTS idx_admin_logs_action_date (action, created_at DESC);
