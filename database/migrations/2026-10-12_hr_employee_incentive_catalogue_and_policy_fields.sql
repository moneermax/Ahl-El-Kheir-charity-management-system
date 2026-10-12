-- Employee incentive category catalogue and version-scoped custom policy fields.
-- Existing incentive_type ENUM values are preserved as their existing string codes.
-- User-created categories are manual-review only; this migration does not implement award issuance.
-- No historical source events are backfilled and no payroll/accounting data is changed.

CREATE TABLE IF NOT EXISTS hr_incentive_types (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    type_code VARCHAR(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    name_ar VARCHAR(190) NOT NULL,
    name_en VARCHAR(190) NOT NULL,
    qualification_mode ENUM('source_event','manual_review') NOT NULL DEFAULT 'manual_review',
    is_system TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 100,
    created_by_user_id INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_hr_incentive_type_code (type_code),
    KEY idx_hr_incentive_type_active_sort (is_active, sort_order, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO hr_incentive_types
    (type_code, name_ar, name_en, qualification_mode, is_system, is_active, sort_order)
VALUES
    ('collection', 'حوافز تحصيل الكفالات والتبرعات', 'Sponsorship and Donation Collection', 'source_event', 1, 1, 10),
    ('new_sponsor', 'حافز استقطاب كفيل جديد', 'New Sponsor Acquisition', 'source_event', 1, 1, 20),
    ('reactivation', 'حافز استعادة كفيل', 'Sponsor Reactivation', 'source_event', 1, 1, 30),
    ('project_completion', 'حافز إتمام المشروع', 'Project Completion', 'source_event', 1, 1, 40)
ON DUPLICATE KEY UPDATE
    name_ar = VALUES(name_ar),
    name_en = VALUES(name_en),
    qualification_mode = VALUES(qualification_mode),
    is_system = 1;

-- Convert the ENUM columns to catalog-backed codes without changing existing values.
-- Convert only while the column is still ENUM so a rerun after a partial/full
-- application does not attempt to modify a column already referenced by an FK.
SET @ak_rule_type_is_enum = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'hr_incentive_policy_rules'
      AND COLUMN_NAME = 'incentive_type'
      AND COLUMN_TYPE LIKE 'enum(%'
);
SET @ak_sql = IF(
    @ak_rule_type_is_enum > 0,
    'ALTER TABLE hr_incentive_policy_rules MODIFY incentive_type VARCHAR(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL',
    'SELECT 1'
);
PREPARE ak_stmt FROM @ak_sql;
EXECUTE ak_stmt;
DEALLOCATE PREPARE ak_stmt;

SET @ak_event_type_is_enum = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'hr_incentive_events'
      AND COLUMN_NAME = 'incentive_type'
      AND COLUMN_TYPE LIKE 'enum(%'
);
SET @ak_sql = IF(
    @ak_event_type_is_enum > 0,
    'ALTER TABLE hr_incentive_events MODIFY incentive_type VARCHAR(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL',
    'SELECT 1'
);
PREPARE ak_stmt FROM @ak_sql;
EXECUTE ak_stmt;
DEALLOCATE PREPARE ak_stmt;

-- Ensure a leading index exists for the policy-rule category foreign key.
SET @ak_idx_exists = (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'hr_incentive_policy_rules'
      AND INDEX_NAME = 'idx_hr_incentive_rule_type_catalog'
);
SET @ak_sql = IF(
    @ak_idx_exists = 0,
    'ALTER TABLE hr_incentive_policy_rules ADD KEY idx_hr_incentive_rule_type_catalog (incentive_type)',
    'SELECT 1'
);
PREPARE ak_stmt FROM @ak_sql;
EXECUTE ak_stmt;
DEALLOCATE PREPARE ak_stmt;

-- Add category foreign keys only if they are not already present.
SET @ak_fk_exists = (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = 'hr_incentive_policy_rules'
      AND CONSTRAINT_NAME = 'fk_hr_incentive_rule_type'
      AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);
SET @ak_sql = IF(
    @ak_fk_exists = 0,
    'ALTER TABLE hr_incentive_policy_rules ADD CONSTRAINT fk_hr_incentive_rule_type FOREIGN KEY (incentive_type) REFERENCES hr_incentive_types (type_code) ON UPDATE RESTRICT ON DELETE RESTRICT',
    'SELECT 1'
);
PREPARE ak_stmt FROM @ak_sql;
EXECUTE ak_stmt;
DEALLOCATE PREPARE ak_stmt;

SET @ak_fk_exists = (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = 'hr_incentive_events'
      AND CONSTRAINT_NAME = 'fk_hr_incentive_event_type'
      AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);
SET @ak_sql = IF(
    @ak_fk_exists = 0,
    'ALTER TABLE hr_incentive_events ADD CONSTRAINT fk_hr_incentive_event_type FOREIGN KEY (incentive_type) REFERENCES hr_incentive_types (type_code) ON UPDATE RESTRICT ON DELETE RESTRICT',
    'SELECT 1'
);
PREPARE ak_stmt FROM @ak_sql;
EXECUTE ak_stmt;
DEALLOCATE PREPARE ak_stmt;

-- A custom field is defined and valued within one policy version. Active/retired
-- policy fields are historical snapshots; application code only writes draft fields.
CREATE TABLE IF NOT EXISTS hr_incentive_policy_custom_fields (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    policy_version_id INT UNSIGNED NOT NULL,
    field_key VARCHAR(80) NOT NULL,
    label_ar VARCHAR(190) NOT NULL,
    label_en VARCHAR(190) NULL,
    field_type ENUM('short_text','long_text','number','date','boolean','select') NOT NULL,
    is_required TINYINT(1) NOT NULL DEFAULT 0,
    options_json TEXT NULL,
    field_value TEXT NULL,
    sort_order INT NOT NULL DEFAULT 100,
    created_by_user_id INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_hr_incentive_policy_custom_field_key (policy_version_id, field_key),
    KEY idx_hr_incentive_policy_custom_field_order (policy_version_id, sort_order, id),
    CONSTRAINT fk_hr_incentive_custom_field_policy
        FOREIGN KEY (policy_version_id) REFERENCES hr_incentive_policy_versions (id)
        ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
