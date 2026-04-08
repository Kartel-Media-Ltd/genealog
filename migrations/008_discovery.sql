-- Migration 008: Person Discovery — global index, suggestions, audit log
-- Plan: dev/active/person-discovery/person-discovery-plan.md
--
-- Uruchomić:
--   source .env.local && docker exec -i mariadb_docker mariadb \
--     -u $DATABASE_USER -p$DATABASE_PASSWORD $DATABASE_NAME < migrations/008_discovery.sql

SET NAMES utf8mb4;
SET foreign_key_checks = 0;

-- ─── persons: fingerprint + soundex ───────────────────────────────────────────
-- IF NOT EXISTS — migracja idempotentna, można uruchomić wielokrotnie
ALTER TABLE `persons`
    ADD COLUMN IF NOT EXISTS `fingerprint_hash` CHAR(64) NULL DEFAULT NULL AFTER `gedcom_xref`,
    ADD COLUMN IF NOT EXISTS `name_soundex`     CHAR(8)  NULL DEFAULT NULL AFTER `fingerprint_hash`,
    ADD INDEX IF NOT EXISTS `idx_persons_fingerprint` (`fingerprint_hash`),
    ADD INDEX IF NOT EXISTS `idx_persons_soundex`     (`name_soundex`);

-- ─── trees: opt-in globalny indeks ────────────────────────────────────────────
-- DEFAULT 0 — RODO Art. 25 (Privacy by Design). User musi jawnie włączyć.
ALTER TABLE `trees`
    ADD COLUMN IF NOT EXISTS `is_indexed_globally`  TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_public`,
    ADD COLUMN IF NOT EXISTS `discovery_consent_at` TIMESTAMP  NULL     DEFAULT NULL AFTER `is_indexed_globally`;

-- ─── users: opt-in na poziomie konta ──────────────────────────────────────────
ALTER TABLE `users`
    ADD COLUMN IF NOT EXISTS `discovery_opt_in` TINYINT(1) NOT NULL DEFAULT 0 AFTER `email_notifications`;

-- ─── global_person_index ──────────────────────────────────────────────────────
-- Anonimowy indeks osób historycznych (is_living=0 AND birth_year < NOW()-100 lat).
-- Służy do cross-tree matchingu bez ujawniania PII do innych użytkowników.
CREATE TABLE IF NOT EXISTS `global_person_index` (
    `id`                  CHAR(36)     NOT NULL,
    `fingerprint_hash`    CHAR(64)     NOT NULL,
    `name_soundex`        VARCHAR(8)   NULL, -- VARCHAR vs CHAR — krótkie wartości typu "K460" nie marnują miejsca
    `tree_id`             CHAR(36)     NOT NULL,
    `person_id`           CHAR(36)     NOT NULL,
    `owner_user_id`       CHAR(36)     NOT NULL,
    `region`              VARCHAR(100) NULL,
    `earliest_birth_year` SMALLINT     NULL,
    `latest_birth_year`   SMALLINT     NULL,
    `is_living`           TINYINT(1)   NOT NULL DEFAULT 0,
    `indexed_at`          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_gpi_person` (`person_id`),
    INDEX `idx_gpi_fingerprint` (`fingerprint_hash`),
    INDEX `idx_gpi_soundex`     (`name_soundex`),
    INDEX `idx_gpi_region`      (`region`),
    INDEX `idx_gpi_owner`       (`owner_user_id`),
    INDEX `idx_gpi_tree`        (`tree_id`),

    CONSTRAINT `fk_gpi_tree`   FOREIGN KEY (`tree_id`)       REFERENCES `trees`(`id`)   ON DELETE RESTRICT,
    CONSTRAINT `fk_gpi_person` FOREIGN KEY (`person_id`)     REFERENCES `persons`(`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_gpi_owner`  FOREIGN KEY (`owner_user_id`) REFERENCES `users`(`id`)   ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── person_match_suggestions ─────────────────────────────────────────────────
-- Persystentne propozycje dopasowań dla osoby, wygenerowane przez MatchingService.
-- UNIQUE żeby ta sama sugestia nie pojawiała się wielokrotnie.
CREATE TABLE IF NOT EXISTS `person_match_suggestions` (
    `id`                CHAR(36)                                              NOT NULL,
    `person_id`         CHAR(36)                                              NOT NULL,
    `source_type`       ENUM('local','cross_tree','external')                 NOT NULL,
    `source_id`         VARCHAR(255)                                          NOT NULL COMMENT 'persons.id | gpi.id | familysearch_id',
    `source_data`       JSON                                                  NULL,
    `confidence`        DECIMAL(3,2)                                          NOT NULL DEFAULT 0.00,
    `status`            ENUM('pending','accepted','rejected','imported')      NOT NULL DEFAULT 'pending',
    `created_for_user`  CHAR(36)                                              NOT NULL,
    `created_at`        TIMESTAMP                                             NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_suggestion` (`person_id`, `source_type`, `source_id`),
    INDEX `idx_suggestion_user`   (`created_for_user`, `status`),
    INDEX `idx_suggestion_person` (`person_id`, `status`),

    CONSTRAINT `fk_sug_person` FOREIGN KEY (`person_id`)        REFERENCES `persons`(`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_sug_user`   FOREIGN KEY (`created_for_user`) REFERENCES `users`(`id`)   ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── source_audit_log ─────────────────────────────────────────────────────────
-- RODO Art. 30 — rejestr czynności przetwarzania. Retencja minimum 3 lata.
-- GDPR erasure NIE usuwa wpisów audit (wymaga osobnego endpointu admin).
CREATE TABLE IF NOT EXISTS `source_audit_log` (
    `id`                CHAR(36)     NOT NULL,
    `user_id`           CHAR(36)     NOT NULL,
    `action`            VARCHAR(50)  NOT NULL COMMENT 'import|reject|external_search|cross_tree_query',
    `source_type`       VARCHAR(50)  NOT NULL,
    `source_id`         VARCHAR(255) NULL,
    `target_person_id`  CHAR(36)     NULL,
    `target_tree_id`    CHAR(36)     NULL,
    `ip`                VARCHAR(45)  NULL,
    `created_at`        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    INDEX `idx_audit_user`    (`user_id`),
    INDEX `idx_audit_created` (`created_at`),
    INDEX `idx_audit_action`  (`action`),

    CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET foreign_key_checks = 1;
