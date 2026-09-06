-- Ahl El Kheir Charity Management System
-- HR Contract / Salary Foundation
--
-- Purpose:
--   Establish historical employment contracts and salary records before payroll.
--   Existing employees.basic_salary remains untouched as a compatibility field.
--
-- Rules:
--   - Contract lifecycle is separate from employment lifecycle.
--   - Salary is effective-dated and historical; payroll must use the applicable
--     salary record for the payroll period.
--   - Leave and attendance are not modeled here.
--   - No existing Attendance indexes are changed.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS hr_employee_contracts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    employee_id BIGINT UNSIGNED NOT NULL,
    contract_number VARCHAR(100) NULL,
    contract_type ENUM('permanent','fixed_term','part_time','temporary','internship','other') NOT NULL DEFAULT 'permanent',
    start_date DATE NOT NULL,
    end_date DATE NULL,
    status ENUM('draft','active','expired','terminated','cancelled') NOT NULL DEFAULT 'draft',
    basic_salary DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    salary_currency CHAR(3) NOT NULL DEFAULT 'EGP',
    pay_frequency ENUM('monthly','weekly','daily','hourly') NOT NULL DEFAULT 'monthly',
    working_hours_per_week DECIMAL(5,2) NULL,
    probation_end_date DATE NULL,
    contract_file_path VARCHAR(500) NULL,
    notes TEXT NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_hr_contract_number (contract_number),
    KEY idx_hr_contract_employee_dates (employee_id, start_date, end_date),
    KEY idx_hr_contract_employee_status (employee_id, status),
    KEY idx_hr_contract_active_dates (status, start_date, end_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS hr_employee_salary_history (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    employee_id BIGINT UNSIGNED NOT NULL,
    contract_id BIGINT UNSIGNED NULL,
    effective_from DATE NOT NULL,
    effective_to DATE NULL,
    basic_salary DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    salary_currency CHAR(3) NOT NULL DEFAULT 'EGP',
    pay_frequency ENUM('monthly','weekly','daily','hourly') NOT NULL DEFAULT 'monthly',
    reason ENUM('initial','annual_increase','promotion','adjustment','contract_change','correction','other') NOT NULL DEFAULT 'initial',
    notes VARCHAR(500) NULL,
    changed_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_hr_salary_employee_dates (employee_id, effective_from, effective_to),
    KEY idx_hr_salary_contract (contract_id),
    KEY idx_hr_salary_current (employee_id, effective_to)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Backfill one initial salary record from the existing employee salary field.
-- This does not change employees.basic_salary.
INSERT INTO hr_employee_salary_history
    (employee_id, contract_id, effective_from, effective_to, basic_salary, salary_currency, pay_frequency, reason, notes)
SELECT
    e.id,
    NULL,
    COALESCE(e.hire_date, CURDATE()),
    NULL,
    COALESCE(e.basic_salary, 0.00),
    'EGP',
    'monthly',
    'initial',
    'Initial HR contract/salary foundation migration'
FROM employees e
LEFT JOIN hr_employee_salary_history h
    ON h.employee_id = e.id
WHERE h.id IS NULL;

-- Seed a contract only when the employee has no contract yet. Existing salary
-- becomes the initial contract salary; the contract is active when its start
-- date is not in the future, otherwise draft.
INSERT INTO hr_employee_contracts
    (employee_id, contract_number, contract_type, start_date, end_date, status,
     basic_salary, salary_currency, pay_frequency, probation_end_date, notes)
SELECT
    e.id,
    CONCAT('LEGACY-', e.employee_code),
    CASE
        WHEN e.employment_type = 'part_time' THEN 'part_time'
        ELSE 'permanent'
    END,
    COALESCE(e.hire_date, CURDATE()),
    NULL,
    CASE WHEN COALESCE(e.hire_date, CURDATE()) <= CURDATE() THEN 'active' ELSE 'draft' END,
    COALESCE(e.basic_salary, 0.00),
    'EGP',
    'monthly',
    NULL,
    'Initial contract created from existing employee record'
FROM employees e
LEFT JOIN hr_employee_contracts c
    ON c.employee_id = e.id
WHERE c.id IS NULL;

-- Link initial salary records to the seeded contract.
UPDATE hr_employee_salary_history h
JOIN hr_employee_contracts c
  ON c.employee_id = h.employee_id
 AND c.contract_number = CONCAT('LEGACY-', (SELECT e2.employee_code FROM employees e2 WHERE e2.id = h.employee_id))
SET h.contract_id = c.id
WHERE h.contract_id IS NULL
  AND h.notes = 'Initial HR contract/salary foundation migration';

-- Keep the compatibility salary field aligned with the latest migrated salary.
UPDATE employees e
JOIN hr_employee_salary_history h
  ON h.employee_id = e.id
 AND h.effective_to IS NULL
SET e.basic_salary = h.basic_salary;

SELECT
    (SELECT COUNT(*) FROM hr_employee_contracts) AS contracts_created,
    (SELECT COUNT(*) FROM hr_employee_salary_history) AS salary_history_rows,
    (SELECT COUNT(*) FROM employees WHERE basic_salary IS NOT NULL) AS employees_with_salary_field;
