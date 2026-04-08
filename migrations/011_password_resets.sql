-- Migration 011: Password reset flow
--
-- Tabela do tokenów resetowania hasła. Token jednorazowy, TTL 1h, on-use staje się invalid.
-- Komentarz audit: dotychczas /forgot-password był stubem (P2 z OWASP audit + R-P1 z RODO).
--
-- Uruchomić:
--   source .env.local && docker exec -i mariadb_docker mariadb \
--     -u $DATABASE_USER -p$DATABASE_PASSWORD $DATABASE_NAME < migrations/011_password_resets.sql

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `password_resets` (
    `id`         CHAR(36)     NOT NULL,
    `user_id`    CHAR(36)     NOT NULL,
    `token`      CHAR(64)     NOT NULL,
    `expires_at` TIMESTAMP    NOT NULL,
    `used_at`    TIMESTAMP    NULL DEFAULT NULL,
    `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `ip`         VARCHAR(45)  NULL,

    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_password_resets_token` (`token`),
    INDEX `idx_password_resets_user` (`user_id`),
    INDEX `idx_password_resets_expires` (`expires_at`),

    CONSTRAINT `fk_password_resets_user`
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
