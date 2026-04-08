-- Migration 010: RODO Art. 17 — Account Deletion support
--
-- Pozwala na anonimizację konta użytkownika bez naruszenia integralności audit log.
-- `source_audit_log.user_id` zmienia się z ON DELETE RESTRICT na ON DELETE SET NULL,
-- żeby usunięcie konta nie blokowało się na FK constraint.
--
-- Dodatkowo `users.email` musi być NULLABLE żeby anonimizacja przez SET NULL działała,
-- ALE zachowujemy UNIQUE — anonimizowane konto zachowuje pseudo-email
-- `deleted-{uuid}@deleted.local` aby UNIQUE nie kolidował z innymi.
--
-- Uruchomić:
--   source .env.local && docker exec -i mariadb_docker mariadb \
--     -u $DATABASE_USER -p$DATABASE_PASSWORD $DATABASE_NAME < migrations/010_account_deletion.sql

SET NAMES utf8mb4;
SET foreign_key_checks = 0;

-- Drop existing FK
ALTER TABLE `source_audit_log` DROP FOREIGN KEY `fk_audit_user`;

-- Make user_id nullable (potrzebne dla SET NULL)
ALTER TABLE `source_audit_log` MODIFY COLUMN `user_id` CHAR(36) NULL;

-- Add new FK with ON DELETE SET NULL
ALTER TABLE `source_audit_log`
    ADD CONSTRAINT `fk_audit_user`
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
    ON DELETE SET NULL;

-- Dodaj kolumnę `deleted_at` do users dla soft-delete tracking + retention period
ALTER TABLE `users`
    ADD COLUMN `deleted_at` TIMESTAMP NULL DEFAULT NULL AFTER `is_active`;

CREATE INDEX `idx_users_deleted_at` ON `users` (`deleted_at`);

SET foreign_key_checks = 1;
