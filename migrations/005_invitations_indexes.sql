-- Migration 005: Indeksy i kolumna invited_by dla tree sharing
ALTER TABLE invitations
    ADD INDEX idx_inv_tree_active (tree_id, used_at, expires_at);

ALTER TABLE tree_members
    ADD COLUMN IF NOT EXISTS invited_by CHAR(36) NULL AFTER role,
    ADD CONSTRAINT fk_tree_members_invited_by
        FOREIGN KEY (invited_by) REFERENCES users(id) ON DELETE SET NULL;
