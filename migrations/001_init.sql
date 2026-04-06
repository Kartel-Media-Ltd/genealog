-- migrations/001_init.sql
-- Inicjalizacja schematu: tabela users + rate_limits

SET NAMES utf8mb4;
SET time_zone = '+00:00';
SET foreign_key_checks = 0;

CREATE TABLE IF NOT EXISTS `users` (
    `id`                  CHAR(36)     NOT NULL,
    `email`               VARCHAR(255) NOT NULL,
    `password_hash`       VARCHAR(255) NOT NULL,
    `name`                VARCHAR(100) NOT NULL,
    `locale`              VARCHAR(10)  NOT NULL DEFAULT 'pl',
    `is_active`           TINYINT(1)   NOT NULL DEFAULT 1,
    `email_notifications` TINYINT(1)   NOT NULL DEFAULT 1,
    `created_at`          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_users_email` (`email`),
    KEY `idx_users_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `rate_limits` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `ip`           VARCHAR(45)  NOT NULL,
    `endpoint`     VARCHAR(100) NOT NULL,
    `attempts`     TINYINT      NOT NULL DEFAULT 1,
    `window_start` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_rate_ip_endpoint` (`ip`, `endpoint`, `window_start`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET foreign_key_checks = 1;
