-- Migration 014: Dodaj brakującą kolumnę tree_members.invited_by
-- Re-audit #3 backend-3 ZAD-4.5 (D5):
--   InvitationRepository::insertMember() wstawia wartość do `invited_by`, ale kolumna
--   nie była zdefiniowana w migracji 002. Prawdopodobnie została dodana manualnie
--   w środowisku dev — nowy deployment by się wyłożył.
--
-- Bezpieczne dla środowisk gdzie kolumna już istnieje (IF NOT EXISTS).

ALTER TABLE tree_members
    ADD COLUMN IF NOT EXISTS invited_by CHAR(36) NULL AFTER invited_at;

-- FK do users — ON DELETE SET NULL żeby usunięcie konta zaproszającego nie kasowało członków
-- Zawijamy w try bo FK może już istnieć w środowiskach manualnie utworzonych:
-- ALTER TABLE w MySQL/MariaDB nie wspiera IF NOT EXISTS dla CONSTRAINT, więc zostawiamy
-- dodanie FK jako opcjonalny krok manualny (informujemy w komentarzu).

-- Jeśli FK jeszcze nie istnieje, uruchom ręcznie:
-- ALTER TABLE tree_members ADD CONSTRAINT fk_tree_members_invited_by
--   FOREIGN KEY (invited_by) REFERENCES users(id) ON DELETE SET NULL;
