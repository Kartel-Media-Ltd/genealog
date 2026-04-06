-- Migration 002: Trees, tree_members, persons, relationships
-- Run: source .env.local && docker exec -i mariadb_docker mariadb \
--   -u $DATABASE_USER -p$DATABASE_PASSWORD $DATABASE_NAME < migrations/002_trees.sql

SET NAMES utf8mb4;
SET time_zone = '+00:00';

-- ─── Trees ────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `trees` (
    `id`          CHAR(36)     NOT NULL,
    `owner_id`    CHAR(36)     NOT NULL,
    `name`        VARCHAR(150) NOT NULL,
    `description` TEXT         NULL,
    `is_public`   TINYINT(1)   NOT NULL DEFAULT 0,
    `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    CONSTRAINT `fk_trees_owner` FOREIGN KEY (`owner_id`)
        REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    INDEX `idx_trees_owner` (`owner_id`),
    INDEX `idx_trees_updated` (`updated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Tree members ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `tree_members` (
    `tree_id`    CHAR(36)                            NOT NULL,
    `user_id`    CHAR(36)                            NOT NULL,
    `role`       ENUM('owner','editor','viewer')     NOT NULL DEFAULT 'viewer',
    `invited_at` TIMESTAMP                           NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`tree_id`, `user_id`),
    CONSTRAINT `fk_tree_members_tree` FOREIGN KEY (`tree_id`)
        REFERENCES `trees` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_tree_members_user` FOREIGN KEY (`user_id`)
        REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    INDEX `idx_tree_members_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Persons ──────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `persons` (
    `id`           CHAR(36)                                  NOT NULL,
    `tree_id`      CHAR(36)                                  NOT NULL,
    `first_name`   VARCHAR(100)                              NOT NULL,
    `last_name`    VARCHAR(100)                              NOT NULL,
    `maiden_name`  VARCHAR(100)                              NULL,
    `birth_date`   DATE                                      NULL,
    `birth_place`  VARCHAR(200)                              NULL,
    `death_date`   DATE                                      NULL,
    `death_place`  VARCHAR(200)                              NULL,
    `gender`       ENUM('male','female','unknown')           NOT NULL DEFAULT 'unknown',
    `is_living`    TINYINT(1)                                NOT NULL DEFAULT 1,
    `visibility`   ENUM('private','public','anonymous')      NOT NULL DEFAULT 'private',
    `notes`        TEXT                                      NULL,
    `photo_path`   VARCHAR(255)                              NULL,
    `gedcom_xref`  VARCHAR(20)                               NULL,
    `created_by`   CHAR(36)                                  NOT NULL,
    `created_at`   TIMESTAMP                                 NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   TIMESTAMP                                 NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    CONSTRAINT `fk_persons_tree`    FOREIGN KEY (`tree_id`)   REFERENCES `trees`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_persons_creator` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    INDEX `idx_persons_tree_last` (`tree_id`, `last_name`),
    INDEX `idx_persons_living`    (`tree_id`, `is_living`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Relationships ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `relationships` (
    `id`           CHAR(36)                                                NOT NULL,
    `tree_id`      CHAR(36)                                                NOT NULL,
    `person_a_id`  CHAR(36)                                                NOT NULL,
    `person_b_id`  CHAR(36)                                                NOT NULL,
    `type`         ENUM('parent','child','spouse','sibling','partner')      NOT NULL,
    `start_date`   DATE                                                     NULL,
    `end_date`     DATE                                                     NULL,
    `notes`        TEXT                                                     NULL,
    `created_at`   TIMESTAMP                                                NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    CONSTRAINT `fk_rel_tree`     FOREIGN KEY (`tree_id`)     REFERENCES `trees`(`id`)   ON DELETE RESTRICT,
    CONSTRAINT `fk_rel_person_a` FOREIGN KEY (`person_a_id`) REFERENCES `persons`(`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_rel_person_b` FOREIGN KEY (`person_b_id`) REFERENCES `persons`(`id`) ON DELETE RESTRICT,
    INDEX `idx_rel_person_a_type` (`person_a_id`, `type`),
    INDEX `idx_rel_person_b_type` (`person_b_id`, `type`),
    INDEX `idx_rel_tree`          (`tree_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Invitations ──────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `invitations` (
    `id`            CHAR(36)                            NOT NULL,
    `tree_id`       CHAR(36)                            NOT NULL,
    `invited_by`    CHAR(36)                            NOT NULL,
    `invited_email` VARCHAR(254)                        NOT NULL,
    `token`         CHAR(64)                            NOT NULL,
    `role`          ENUM('editor','viewer')             NOT NULL DEFAULT 'viewer',
    `expires_at`    TIMESTAMP                           NOT NULL,
    `used_at`       TIMESTAMP                           NULL,
    `created_at`    TIMESTAMP                           NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    UNIQUE KEY  `uq_invitations_token` (`token`),
    CONSTRAINT `fk_inv_tree` FOREIGN KEY (`tree_id`)    REFERENCES `trees`(`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_inv_user` FOREIGN KEY (`invited_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT,
    INDEX `idx_inv_email` (`invited_email`),
    INDEX `idx_inv_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
