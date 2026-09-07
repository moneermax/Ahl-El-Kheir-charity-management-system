-- Ahl El Kheir Charity Management System
-- HR Payroll Policy Foundation - Stage 1
-- Versioned, effective-dated payroll policy records.
-- No payroll calculations are performed by this migration.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS hr_payroll_policy_versions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    version_no INT UNSIGNED NOT NULL,
    effective_from DATE NOT NULL,
    absence_enabled TINYINT(1) NOT NULL DEFAULT 1,
    absence_deduction_percent DECIMAL(7,4) NOT NULL DEFAULT 100.0000,
    unpaid_leave_enabled TINYINT(1) NOT NULL DEFAULT 1,
    unpaid_leave_deduction_percent DECIMAL(7,4) NOT NULL DEFAULT 100.0000,
    paid_leave_deduction_percent DECIMAL(7,4) NOT NULL DEFAULT 0.0000,
    late_enabled TINYINT(1) NOT NULL DEFAULT 0,
    early_departure_enabled TINYINT(1) NOT NULL DEFAULT 0,
    overtime_enabled TINYINT(1) NOT NULL DEFAULT 0,
    overtime_multiplier DECIMAL(7,4) NOT NULL DEFAULT 1.0000,
    daily_deduction_method ENUM('monthly_salary_div_30') NOT NULL DEFAULT 'monthly_salary_div_30',
    rounding_decimals TINYINT UNSIGNED NOT NULL DEFAULT 2,
    notes VARCHAR(1000) NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_hr_payroll_policy_version (version_no),
    UNIQUE KEY uq_hr_payroll_policy_effective_from (effective_from),
    KEY idx_hr_payroll_policy_effective (effective_from)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TRIGGER IF EXISTS trg_hr_payroll_policy_no_update_effective;
DELIMITER $$
CREATE TRIGGER trg_hr_payroll_policy_no_update_effective
BEFORE UPDATE ON hr_payroll_policy_versions
FOR EACH ROW
BEGIN
    IF OLD.effective_from <= CURDATE() THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Effective payroll policy versions are immutable; create a new version instead.';
    END IF;
END$$
DELIMITER ;

DROP TRIGGER IF EXISTS trg_hr_payroll_policy_no_delete_effective;
DELIMITER $$
CREATE TRIGGER trg_hr_payroll_policy_no_delete_effective
BEFORE DELETE ON hr_payroll_policy_versions
FOR EACH ROW
BEGIN
    IF OLD.effective_from <= CURDATE() THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Effective payroll policy versions cannot be deleted.';
    END IF;
END$$
DELIMITER ;

-- Version 1 is deliberately future-effective so the verified September 2026
-- payroll/accounting records remain untouched.
INSERT INTO hr_payroll_policy_versions
    (version_no, effective_from, absence_enabled, absence_deduction_percent,
     unpaid_leave_enabled, unpaid_leave_deduction_percent, paid_leave_deduction_percent,
     late_enabled, early_departure_enabled, overtime_enabled, overtime_multiplier,
     daily_deduction_method, rounding_decimals, notes, created_by)
SELECT
    1, '2026-10-01', 1, 100.0000,
    1, 100.0000, 0.0000,
    0, 0, 0, 1.0000,
    'monthly_salary_div_30', 2,
    'الإعدادات الافتراضية — يجب اعتمادها من إدارة الجمعية', NULL
WHERE NOT EXISTS (SELECT 1 FROM hr_payroll_policy_versions);

SELECT id, version_no, effective_from, absence_enabled, absence_deduction_percent,
       unpaid_leave_enabled, unpaid_leave_deduction_percent, paid_leave_deduction_percent,
       late_enabled, early_departure_enabled, overtime_enabled, overtime_multiplier,
       daily_deduction_method, rounding_decimals
FROM hr_payroll_policy_versions
ORDER BY effective_from, version_no;
