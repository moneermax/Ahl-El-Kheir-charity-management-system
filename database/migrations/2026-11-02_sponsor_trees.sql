-- AHL EL KHEIR
-- Sponsor Trees: a Tree is an ordinary legal-entity sponsor plus independent
-- many-to-many memberships to existing individual sponsor records.
-- Apply through the migration process only. Never execute DDL at runtime.

CREATE TABLE IF NOT EXISTS sponsor_trees (
    id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    sponsor_id INT(10) UNSIGNED NOT NULL,
    tree_type ENUM('optional_members','required_members') NOT NULL,
    created_by INT(10) UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sponsor_trees_sponsor (sponsor_id),
    KEY idx_sponsor_trees_type (tree_type),
    CONSTRAINT fk_sponsor_trees_sponsor
        FOREIGN KEY (sponsor_id) REFERENCES sponsors(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_sponsor_trees_creator
        FOREIGN KEY (created_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sponsor_tree_members (
    id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    tree_id INT(10) UNSIGNED NOT NULL,
    member_sponsor_id INT(10) UNSIGNED NOT NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    joined_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ended_at DATETIME DEFAULT NULL,
    created_by INT(10) UNSIGNED DEFAULT NULL,
    updated_by INT(10) UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sponsor_tree_member (tree_id, member_sponsor_id),
    KEY idx_sponsor_tree_members_member (member_sponsor_id),
    KEY idx_sponsor_tree_members_status (tree_id, status),
    CONSTRAINT fk_sponsor_tree_members_tree
        FOREIGN KEY (tree_id) REFERENCES sponsor_trees(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_sponsor_tree_members_sponsor
        FOREIGN KEY (member_sponsor_id) REFERENCES sponsors(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_sponsor_tree_members_creator
        FOREIGN KEY (created_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_sponsor_tree_members_updater
        FOREIGN KEY (updated_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
