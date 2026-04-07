-- Migration 004: Global Admin Panel
-- Adds is_admin + is_blocked to users, creates admin_logs table

ALTER TABLE users
    ADD COLUMN is_admin   TINYINT(1) NOT NULL DEFAULT 0 AFTER email_notifications,
    ADD COLUMN is_blocked TINYINT(1) NOT NULL DEFAULT 0 AFTER is_admin;

CREATE TABLE admin_logs (
    id          CHAR(36)    NOT NULL PRIMARY KEY,
    admin_id    CHAR(36)    NOT NULL,
    action      VARCHAR(50) NOT NULL,
    target_type VARCHAR(30) NULL,
    target_id   CHAR(36)    NULL,
    meta        TEXT        NULL,
    created_at  TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_admin_logs_admin FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_admin_logs_admin_id (admin_id),
    INDEX idx_admin_logs_created  (created_at DESC),
    INDEX idx_admin_logs_action   (action)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
