-- Move user-management schema preparation and reference-data seeding out of page requests.
-- Safe to reapply on MariaDB 10.4+ and aligned with database/ahl_el_kheir.sql.

CREATE TABLE IF NOT EXISTS departments (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name_ar VARCHAR(100) NOT NULL,
    name_en VARCHAR(100) NOT NULL,
    description TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS department_id INT UNSIGNED NULL,
    ADD COLUMN IF NOT EXISTS manager_id INT UNSIGNED NULL;

ALTER TABLE sponsors
    ADD COLUMN IF NOT EXISTS gender ENUM('male','female','organization','unknown') DEFAULT 'unknown';

ALTER TABLE families
    ADD COLUMN IF NOT EXISTS nanny_id INT UNSIGNED NULL;

ALTER TABLE supervisor_letters
    ADD COLUMN IF NOT EXISTS gender ENUM('male','female','both') DEFAULT 'both';

CREATE TABLE IF NOT EXISTS family_documents (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    family_id INT UNSIGNED NOT NULL,
    document_type VARCHAR(50) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    uploaded_by INT UNSIGNED NOT NULL,
    uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    file_name VARCHAR(255) DEFAULT NULL,
    file_size INT UNSIGNED DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    doc_type VARCHAR(50) NOT NULL DEFAULT 'other',
    PRIMARY KEY (id),
    KEY family_id (family_id),
    KEY uploaded_by (uploaded_by),
    CONSTRAINT family_documents_ibfk_1 FOREIGN KEY (family_id) REFERENCES families (id) ON DELETE CASCADE,
    CONSTRAINT family_documents_ibfk_2 FOREIGN KEY (uploaded_by) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Older databases may have the original runtime-created table, which lacked these later fields.
ALTER TABLE family_documents
    ADD COLUMN IF NOT EXISTS file_name VARCHAR(255) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS file_size INT UNSIGNED DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS notes TEXT DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS doc_type VARCHAR(50) NOT NULL DEFAULT 'other';

CREATE TABLE IF NOT EXISTS accountant_nanny_assignments (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    accountant_id INT UNSIGNED NOT NULL,
    nanny_id INT UNSIGNED NOT NULL,
    assigned_by INT UNSIGNED DEFAULT NULL,
    assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_acct_nanny (accountant_id, nanny_id),
    KEY nanny_id (nanny_id),
    KEY assigned_by (assigned_by),
    CONSTRAINT accountant_nanny_assignments_ibfk_1 FOREIGN KEY (accountant_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT accountant_nanny_assignments_ibfk_2 FOREIGN KEY (nanny_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT accountant_nanny_assignments_ibfk_3 FOREIGN KEY (assigned_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Preserve the legacy-label normalization that previously ran whenever this page loaded.
UPDATE departments
SET name_ar = 'استرجاع الكفلاء السابقين',
    name_en = 'Lost & Found'
WHERE name_en = 'Administration'
   OR name_ar = 'الإدارة التنفيذية'
   OR name_ar = 'المفقودات والاسترجاع';

UPDATE roles
SET name_ar = 'مندوب استرجاع الكفلاء',
    name_en = 'Recovery Officer'
WHERE code = 'administration';

UPDATE roles
SET name_ar = 'رئيس قسم المحاسبة',
    name_en = 'Accounting Dept Head'
WHERE code = 'accountant';

INSERT INTO departments (name_ar, name_en)
SELECT seed.name_ar, seed.name_en
FROM (
    SELECT 'الإدارة العامة' AS name_ar, 'General Management' AS name_en
    UNION ALL SELECT 'شؤون الأمهات', 'Mothers Affairs'
    UNION ALL SELECT 'استرجاع الكفلاء السابقين', 'Lost & Found'
    UNION ALL SELECT 'العلاقات العامة والإعلام', 'Social Media'
    UNION ALL SELECT 'شؤون الكفلاء', 'Sponsor Affairs'
    UNION ALL SELECT 'المالية والمحاسبة', 'Accounting & Finance'
) AS seed
WHERE NOT EXISTS (
    SELECT 1 FROM departments d WHERE d.name_en = seed.name_en
);

INSERT INTO roles (code, name_en, name_ar)
SELECT seed.code, seed.name_en, seed.name_ar
FROM (
    SELECT 'financial_manager' AS code, 'Financial Manager' AS name_en, 'المدير المالي' AS name_ar
    UNION ALL SELECT 'accountant_staff', 'Accountant', 'محاسب'
    UNION ALL SELECT 'nanny', 'Nanny / Case Worker', 'أخصائية شؤون الأمهات'
    UNION ALL SELECT 'administration', 'Recovery Officer', 'مندوب استرجاع الكفلاء'
    UNION ALL SELECT 'social_media', 'Social Media Staff', 'موظف العلاقات العامة والإعلام'
) AS seed
WHERE NOT EXISTS (
    SELECT 1 FROM roles r WHERE r.code = seed.code
);
