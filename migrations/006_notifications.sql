-- Migration 006: Notifications system
-- Tabela powiadomień dla użytkowników (RODO + global match + admin actions)
-- Uruchomić: source .env.local && docker exec -i mariadb_docker mariadb \
--   -u $DATABASE_USER -p$DATABASE_PASSWORD $DATABASE_NAME < migrations/006_notifications.sql

CREATE TABLE IF NOT EXISTS notifications (
    id          CHAR(36)      NOT NULL PRIMARY KEY,
    user_id     CHAR(36)      NOT NULL,
    type        VARCHAR(50)   NOT NULL,           -- person_match | invitation | edit | impersonation | gedcom_import
    title       VARCHAR(255)  NOT NULL,
    body        TEXT          NULL,
    link        VARCHAR(500)  NULL,               -- URL do kliknięcia
    is_read     TINYINT(1)    NOT NULL DEFAULT 0,
    created_at  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    read_at     TIMESTAMP     NULL,

    CONSTRAINT fk_notifications_user FOREIGN KEY (user_id)
        REFERENCES users(id) ON DELETE RESTRICT,

    INDEX idx_notifications_user_id    (user_id),
    INDEX idx_notifications_unread     (user_id, is_read, created_at DESC),
    INDEX idx_notifications_type       (type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
