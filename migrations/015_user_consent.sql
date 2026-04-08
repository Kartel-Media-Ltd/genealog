-- Migration 015: RODO Art. 7(1) — persystencja zgody na regulamin
-- Re-audit #4 backend-4 ZAD-1.1 (K1):
--   Bez tych kolumn administrator nie może udowodnić że user zaakceptował regulamin.
--   RODO Art. 7(1) wymaga by ciężar dowodu spoczywał na administratorze.
--
-- Idempotent: użycie IF NOT EXISTS dla ADD + MODIFY dla korekcji rozmiaru
-- (poprzednia wersja używała VARCHAR(20) co było za małe dla 'legacy-pre-2026-04-08' = 21 znaków).

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS terms_accepted_at TIMESTAMP NULL AFTER created_at,
    ADD COLUMN IF NOT EXISTS terms_version VARCHAR(40) NULL AFTER terms_accepted_at;

-- Korekcja rozmiaru jeśli kolumna istnieje z poprzednim VARCHAR(20).
-- MODIFY jest idempotentny — wielokrotne uruchomienie nie zmienia struktury.
ALTER TABLE users MODIFY COLUMN terms_version VARCHAR(40) NULL;

-- Istniejących userów oznacz jako "grandfathered" — zgoda implicit z faktu
-- aktywnego korzystania z usługi przed wprowadzeniem consent flow.
-- Wersja `legacy-pre-2026-04-08` sygnalizuje że to zgoda domniemana.
UPDATE users
SET terms_accepted_at = created_at,
    terms_version = 'legacy-pre-2026-04-08'
WHERE terms_accepted_at IS NULL;
