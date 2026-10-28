SET NAMES utf8mb4;

-- Move family/orphan schema preparation out of web requests.
-- Definitions follow database/ahl_el_kheir.sql and are repeatable on MariaDB 10.4+.

CREATE TABLE IF NOT EXISTS family_bank_accounts (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    family_id INT UNSIGNED NOT NULL,
    bank_name VARCHAR(100) NOT NULL,
    bank_branch VARCHAR(100) DEFAULT NULL,
    account_number VARCHAR(50) NOT NULL,
    account_holder VARCHAR(150) DEFAULT NULL,
    is_primary TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY family_id (family_id),
    CONSTRAINT family_bank_accounts_ibfk_1
        FOREIGN KEY (family_id) REFERENCES families (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE families
    ADD COLUMN IF NOT EXISTS father_name VARCHAR(150) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS father_death_date DATE DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS father_death_cause VARCHAR(150) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS mother_marital_status VARCHAR(30) DEFAULT 'أرملة';

ALTER TABLE family_children
    ADD COLUMN IF NOT EXISTS national_id VARCHAR(20) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS nationality VARCHAR(50) NOT NULL DEFAULT 'سودانية',
    ADD COLUMN IF NOT EXISTS health_status VARCHAR(150) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS health_status_other VARCHAR(150) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS psychological_state VARCHAR(150) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS psychological_state_other VARCHAR(150) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS guardian_name VARCHAR(150) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS guardian_relationship VARCHAR(60) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS photo_path VARCHAR(255) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS form_serial VARCHAR(20) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS monthly_sponsorship_value DECIMAL(12,2) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS extra_allowance DECIMAL(12,2) DEFAULT NULL;

CREATE TABLE IF NOT EXISTS orphan_documents (
    id INT NOT NULL AUTO_INCREMENT,
    child_id INT NOT NULL,
    family_id INT NOT NULL,
    doc_type VARCHAR(50) NOT NULL DEFAULT 'other',
    file_path VARCHAR(255) NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_size INT NOT NULL,
    uploaded_by INT DEFAULT NULL,
    uploaded_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_od_child (child_id),
    KEY idx_od_family (family_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Preserve the unique form-serial invariant from the checked-in schema.
SET @ahl_has_family_children_form_serial_index := (
    SELECT COUNT(DISTINCT index_name)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'family_children'
      AND index_name = 'uq_family_children_form_serial'
);
SET @ahl_family_children_form_serial_index_sql := IF(
    @ahl_has_family_children_form_serial_index = 0,
    'ALTER TABLE family_children ADD UNIQUE KEY uq_family_children_form_serial (form_serial)',
    'SELECT 1'
);
PREPARE ahl_family_children_form_serial_index_stmt FROM @ahl_family_children_form_serial_index_sql;
EXECUTE ahl_family_children_form_serial_index_stmt;
DEALLOCATE PREPARE ahl_family_children_form_serial_index_stmt;
