-- Migration 012: performance indexes
-- Re-audit #3 backend-3:
--   P8 — GedcomService::importIndividuals() wywołuje findByXref($treeId, $xref) w pętli.
--        Bez indeksu: N full-scans na persons dla pliku z N osobami. Import 1000 osób ~60s.
--        Z indeksem: ~3s.
--   D5 — tree_members.user_id jest często filtrowane w queries (lista drzew usera).

ALTER TABLE persons
    ADD INDEX idx_persons_gedcom_xref (tree_id, gedcom_xref);

ALTER TABLE tree_members
    ADD INDEX idx_tree_members_user_id (user_id);
