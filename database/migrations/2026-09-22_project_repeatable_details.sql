-- Repeatable project planning/detail fields
-- No triggers or views.

CREATE TABLE IF NOT EXISTS project_government_requirements (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    project_id INT UNSIGNED NOT NULL,
    requirement_text VARCHAR(500) NOT NULL,
    fee_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    created_by INT UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_pgr_project_id (project_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS project_partners (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    project_id INT UNSIGNED NOT NULL,
    partner_name VARCHAR(255) NOT NULL,
    role_description VARCHAR(255) DEFAULT NULL,
    created_by INT UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_project_partners_project_id (project_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS project_procurement_methods (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    project_id INT UNSIGNED NOT NULL,
    method_name VARCHAR(255) NOT NULL,
    notes VARCHAR(1000) DEFAULT NULL,
    created_by INT UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_project_procurement_project_id (project_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS project_contacts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    project_id INT UNSIGNED NOT NULL,
    contact_name VARCHAR(150) NOT NULL,
    role_description VARCHAR(150) DEFAULT NULL,
    phone VARCHAR(50) DEFAULT NULL,
    email VARCHAR(190) DEFAULT NULL,
    notes VARCHAR(1000) DEFAULT NULL,
    created_by INT UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_project_contacts_project_id (project_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Backfill existing newline-separated values from project_details.
INSERT INTO project_partners (project_id, partner_name, role_description, created_by)
SELECT pd.project_id, TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(pd.implementing_partner, CHAR(10), seq.n), CHAR(10), -1)), NULL, pd.updated_by
FROM project_details pd
JOIN (SELECT 1 n UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9 UNION ALL SELECT 10) seq
  ON seq.n <= 1 + LENGTH(pd.implementing_partner) - LENGTH(REPLACE(pd.implementing_partner, CHAR(10), ''))
WHERE pd.implementing_partner IS NOT NULL AND TRIM(pd.implementing_partner) <> ''
  AND NOT EXISTS (SELECT 1 FROM project_partners x WHERE x.project_id = pd.project_id);

INSERT INTO project_procurement_methods (project_id, method_name, notes, created_by)
SELECT pd.project_id, TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(pd.procurement_method, CHAR(10), seq.n), CHAR(10), -1)), NULL, pd.updated_by
FROM project_details pd
JOIN (SELECT 1 n UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9 UNION ALL SELECT 10) seq
  ON seq.n <= 1 + LENGTH(pd.procurement_method) - LENGTH(REPLACE(pd.procurement_method, CHAR(10), ''))
WHERE pd.procurement_method IS NOT NULL AND TRIM(pd.procurement_method) <> ''
  AND NOT EXISTS (SELECT 1 FROM project_procurement_methods x WHERE x.project_id = pd.project_id);

INSERT INTO project_contacts (project_id, contact_name, role_description, phone, email, notes, created_by)
SELECT pd.project_id, TRIM(pd.contact_person), NULL, NULLIF(TRIM(pd.contact_phone), ''), NULLIF(TRIM(pd.contact_email), ''), NULL, pd.updated_by
FROM project_details pd
WHERE pd.contact_person IS NOT NULL AND TRIM(pd.contact_person) <> ''
  AND NOT EXISTS (SELECT 1 FROM project_contacts x WHERE x.project_id = pd.project_id);

INSERT INTO project_government_requirements (project_id, requirement_text, fee_amount, created_by)
SELECT pd.project_id, TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(pd.government_requirements, CHAR(10), seq.n), CHAR(10), -1)), 0.00, pd.updated_by
FROM project_details pd
JOIN (SELECT 1 n UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9 UNION ALL SELECT 10) seq
  ON seq.n <= 1 + LENGTH(pd.government_requirements) - LENGTH(REPLACE(pd.government_requirements, CHAR(10), ''))
WHERE pd.government_requirements IS NOT NULL AND TRIM(pd.government_requirements) <> ''
  AND NOT EXISTS (SELECT 1 FROM project_government_requirements x WHERE x.project_id = pd.project_id);
