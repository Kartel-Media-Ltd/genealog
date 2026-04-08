-- Migration 013: RODO Art. 18 — Right to restriction of processing
-- Re-audit #3 backend-3 ZAD-3.2 (P11):
--   Użytkownik ma prawo żądać tymczasowego ograniczenia przetwarzania bez pełnej erasure.
--   Restricted account → blokada logowania, dane niezmienione, session_version zwiększone.
--   Odblokowanie: email-based confirmation.

ALTER TABLE users
    ADD COLUMN is_restricted TINYINT(1) NOT NULL DEFAULT 0 AFTER is_blocked,
    ADD COLUMN restricted_at TIMESTAMP NULL AFTER is_restricted;

CREATE INDEX idx_users_is_restricted ON users (is_restricted);
