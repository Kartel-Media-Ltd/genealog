-- Migration 017: Cross-tree person links (Opcja C)
-- Łączenie osób między drzewami różnych użytkowników po obustronnej akceptacji

CREATE TABLE IF NOT EXISTS cross_tree_links (
    id                  CHAR(36)     NOT NULL,
    requester_person_id CHAR(36)     NOT NULL,
    target_person_id    CHAR(36)     NOT NULL,
    requester_user_id   CHAR(36)     NOT NULL,
    target_user_id      CHAR(36)     NOT NULL,
    status              ENUM('pending','accepted','rejected','cancelled','broken')
                        NOT NULL DEFAULT 'pending',
    visibility_level    ENUM('shared_basic','shared_full')
                        NOT NULL DEFAULT 'shared_basic',
    note                TEXT,
    -- Kanoniczny klucz pary: min(personId_A, personId_B)|max(personId_A, personId_B)
    -- Ustawiany przez aplikację w CrossTreeLinkRepository::create().
    -- Eliminuje duplikaty A→B i B→A.
    pair_key            CHAR(73)     NOT NULL,
    created_at          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),

    -- Eliminuje duplikaty A→B i B→A: para_key normalizuje kolejność
    UNIQUE KEY uq_ctl_pair (pair_key),

    KEY idx_ctl_requester_user (requester_user_id),
    KEY idx_ctl_target_user    (target_user_id),
    KEY idx_ctl_status         (status),
    KEY idx_ctl_req_person     (requester_person_id),
    KEY idx_ctl_tgt_person     (target_person_id),

    -- ON DELETE RESTRICT: nigdy nie każ kaskadowo usuwać powiązań genealogicznych
    CONSTRAINT fk_ctl_requester_person FOREIGN KEY (requester_person_id)
        REFERENCES persons(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_ctl_target_person    FOREIGN KEY (target_person_id)
        REFERENCES persons(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_ctl_requester_user   FOREIGN KEY (requester_user_id)
        REFERENCES users(id)   ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_ctl_target_user      FOREIGN KEY (target_user_id)
        REFERENCES users(id)   ON DELETE RESTRICT ON UPDATE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Widok symetryczny: zaakceptowane połączenia widoczne z obu stron
-- (UNION ALL zapewnia dostęp zarówno dla requester jak i target)
CREATE OR REPLACE VIEW v_active_cross_tree_links AS
    SELECT id,
           requester_person_id AS person_a_id,
           target_person_id    AS person_b_id,
           requester_user_id   AS user_a_id,
           target_user_id      AS user_b_id,
           visibility_level,
           created_at
    FROM cross_tree_links
    WHERE status = 'accepted'
    UNION ALL
    SELECT id,
           target_person_id    AS person_a_id,
           requester_person_id AS person_b_id,
           target_user_id      AS user_a_id,
           requester_user_id   AS user_b_id,
           visibility_level,
           created_at
    FROM cross_tree_links
    WHERE status = 'accepted';
