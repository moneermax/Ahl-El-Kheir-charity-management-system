-- Ahl El Kheir Charity Management System
-- HR Foundation - Employment State Model
--
-- Purpose:
--   Establish a proper employment-state layer without breaking the existing
--   employees.status column yet. Existing code can continue to use status
--   during the transition; the application refactor will move business logic
--   to employment_state_id/history in a later step.
--
-- Important:
--   This migration does NOT change attendance indexes and does NOT convert
--   approved leave into attendance records.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS hr_employment_states (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    code VARCHAR(50) NOT NULL,
    name_ar VARCHAR(100) NOT NULL,
    name_en VARCHAR(100) NOT NULL,
    category ENUM('working','temporary_unavailable','separation') NOT NULL DEFAULT 'working',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_hr_employment_states_code (code),
    KEY idx_hr_employment_states_active_sort (is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO hr_employment_states (code, name_ar, name_en, category, sort_order) VALUES
('active',     'على رأس العمل',   'Active',              'working',              10),
('probation',  'فترة تجربة',      'Probation',           'working',              20),
('suspended',  'موقوف مؤقتاً',    'Suspended',           'temporary_unavailable', 30),
('resigned',   'مستقيل',          'Resigned',             'separation',           40),
('terminated', 'منهي الخدمة',    'Terminated',           'separation',           50),
('retired',    'متقاعد',          'Retired',              'separation',           60);

-- Add the current canonical state reference to employees while preserving
-- the legacy status column for backward compatibility during migration.
SET @has_state_id := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'employees'
      AND COLUMN_NAME = 'employment_state_id'
);

SET @sql := IF(
    @has_state_id = 0,
    'ALTER TABLE employees ADD COLUMN employment_state_id INT UNSIGNED NULL AFTER status',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_state_changed_at := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'employees'
      AND COLUMN_NAME = 'employment_state_changed_at'
);

SET @sql := IF(
    @has_state_changed_at = 0,
    'ALTER TABLE employees ADD COLUMN employment_state_changed_at DATETIME NULL AFTER employment_state_id',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_state_idx := (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'employees'
      AND INDEX_NAME = 'idx_employees_employment_state'
);

SET @sql := IF(
    @has_state_idx = 0,
    'ALTER TABLE employees ADD INDEX idx_employees_employment_state (employment_state_id)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Persistent state history. No foreign key is introduced here intentionally:
-- the existing production database has evolved over time, and the first
-- foundation migration must remain safe across existing employee key types.
CREATE TABLE IF NOT EXISTS hr_employee_state_history (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    employee_id BIGINT UNSIGNED NOT NULL,
    employment_state_id INT UNSIGNED NOT NULL,
    effective_from DATETIME NOT NULL,
    effective_to DATETIME NULL,
    reason VARCHAR(255) NULL,
    changed_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_hr_state_history_employee_dates (employee_id, effective_from),
    KEY idx_hr_state_history_current (employee_id, effective_to),
    KEY idx_hr_state_history_state (employment_state_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Backfill the new state reference from the existing status values.
UPDATE employees e
JOIN hr_employment_states s ON s.code = CASE
    WHEN e.status IN ('active', 'suspended', 'terminated') THEN e.status
    ELSE 'active'
END
SET e.employment_state_id = s.id,
    e.employment_state_changed_at = COALESCE(e.employment_state_changed_at, NOW())
WHERE e.employment_state_id IS NULL;

-- Create an initial history row for employees that do not have one yet.
INSERT INTO hr_employee_state_history
    (employee_id, employment_state_id, effective_from, reason)
SELECT
    e.id,
    e.employment_state_id,
    COALESCE(e.employment_state_changed_at, NOW()),
    'Initial HR foundation migration'
FROM employees e
LEFT JOIN hr_employee_state_history h
    ON h.employee_id = e.id
WHERE e.employment_state_id IS NOT NULL
  AND h.id IS NULL;

-- Mark the history row as open/current for employees whose latest state is
-- still the current employment state.
UPDATE hr_employee_state_history h
JOIN employees e ON e.id = h.employee_id
SET h.effective_to = NULL
WHERE h.effective_to IS NULL
  AND h.employment_state_id = e.employment_state_id;

SELECT
    (SELECT COUNT(*) FROM hr_employment_states) AS employment_states,
    (SELECT COUNT(*) FROM employees WHERE employment_state_id IS NOT NULL) AS employees_migrated,
    (SELECT COUNT(*) FROM hr_employee_state_history) AS history_rows;
