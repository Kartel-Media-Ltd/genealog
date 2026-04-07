-- Migration 007: Admin extras — session invalidation + view actions audit
-- Uruchomić: source .env.local && docker exec -i mariadb_docker mariadb \
--   -u $DATABASE_USER -p$DATABASE_PASSWORD $DATABASE_NAME < migrations/007_admin_extras.sql

-- Dodajemy session_version do users — inkrementacja przy block/demote
-- powoduje że istniejące sesje stają się nieważne (AdminMiddleware sprawdza)
ALTER TABLE users
    ADD COLUMN session_version INT NOT NULL DEFAULT 0 AFTER is_blocked;

-- Indeks dla filtrowania logów po typie akcji + dacie
ALTER TABLE admin_logs
    ADD INDEX idx_admin_logs_target (target_type, target_id),
    ADD INDEX idx_admin_logs_action_date (action, created_at DESC);
