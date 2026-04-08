-- Migration 014: Dodaj brakującą kolumnę tree_members.invited_by + FK
-- Re-audit #3 ZAD-4.5 (D5) + Re-audit #4 ZAD-3.11 (D11):
--   InvitationRepository::insertMember() wstawia wartość do `invited_by`, ale kolumna
--   nie była zdefiniowana w migracji 002. FK teraz dodane automatycznie (wcześniej
--   wymagane manualnie).

-- Dodaj kolumnę — idempotent
ALTER TABLE tree_members
    ADD COLUMN IF NOT EXISTS invited_by CHAR(36) NULL AFTER invited_at;

-- Dodaj FK — drop-if-exists pattern dla idempotency (MariaDB/MySQL nie wspiera
-- "IF NOT EXISTS" dla FK constraints).
SET @fk_exists := (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = 'tree_members'
      AND CONSTRAINT_NAME = 'fk_tree_members_invited_by'
);

SET @sql := IF(@fk_exists > 0,
    'ALTER TABLE tree_members DROP FOREIGN KEY fk_tree_members_invited_by',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Dodaj FK. ON DELETE SET NULL — usunięcie zaproszającego nie kasuje członka drzewa.
ALTER TABLE tree_members
    ADD CONSTRAINT fk_tree_members_invited_by
    FOREIGN KEY (invited_by) REFERENCES users(id) ON DELETE SET NULL;
