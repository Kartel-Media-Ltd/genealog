-- Migration 016: Dodaj gender do global_person_index
-- Umożliwia filtrowanie/scoring cross-tree matchingu po płci.
-- Gender jest bezpieczny do przechowywania dla osób historycznych
-- (binarny enum, nie ujawnia wrażliwych danych).
--
-- Uruchom: docker exec -i mariadb_docker mariadb -u genealog -pPASS genealog < migrations/016_global_index_gender.sql

ALTER TABLE global_person_index
    ADD COLUMN gender ENUM('male','female','unknown') NOT NULL DEFAULT 'unknown'
        AFTER is_living;
