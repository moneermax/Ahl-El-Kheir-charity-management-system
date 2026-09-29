-- Ahl El Kheir Charity Management System
-- HR Salary Advance Stage 5: repayment schedule + payroll repayment traceability
-- Schema-only migration. No runtime DDL, triggers, or views.

SET NAMES utf8mb4;

-- The existing payroll.deductions field is an aggregate payroll field.
-- Keep salary-advance repayment separately so it cannot be overwritten by
-- ordinary payroll editing and so the accounting layer can identify the
-- 1410 receivable reduction explicitly.
ALTER TABLE payroll
    ADD COLUMN IF NOT EXISTS salary_advance_deduction DECIMAL(18,2) NOT NULL DEFAULT 0.00
        AFTER deductions,
    ADD KEY IF NOT EXISTS idx_payroll_salary_advance_deduction (salary_advance_deduction);

-- One immutable planned installment per salary-advance repayment period.
CREATE TABLE IF NOT EXISTS hr_salary_advance_repayment_schedule (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    salary_advance_request_id INT UNSIGNED NOT NULL,
    installment_no INT UNSIGNED NOT NULL,
    scheduled_month DATE NOT NULL,
    scheduled_amount DECIMAL(18,2) NOT NULL,
    applied_amount DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    status ENUM('pending','partial','paid','skipped') NOT NULL DEFAULT 'pending',
    applied_payroll_id INT UNSIGNED NULL,
    applied_at DATETIME NULL,
    skip_reason VARCHAR(1000) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_hr_salary_advance_schedule_installment (salary_advance_request_id, installment_no),
    UNIQUE KEY uq_hr_salary_advance_schedule_month (salary_advance_request_id, scheduled_month),
    KEY idx_hr_salary_advance_schedule_request_status (salary_advance_request_id, status),
    KEY idx_hr_salary_advance_schedule_payroll (applied_payroll_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One allocation record per salary-advance request and payroll period.
-- This is the authoritative trace for an actual/attempted payroll repayment;
-- payroll.salary_advance_deduction remains the period total for payroll display.
CREATE TABLE IF NOT EXISTS hr_salary_advance_payroll_repayments (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    salary_advance_request_id INT UNSIGNED NOT NULL,
    repayment_schedule_id INT UNSIGNED NULL,
    payroll_id INT UNSIGNED NOT NULL,
    employee_id INT UNSIGNED NOT NULL,
    eligible_salary DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    maximum_allowed_deduction DECIMAL(18,2) NULL,
    scheduled_amount DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    actual_amount DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    outcome ENUM('applied','partial','skipped') NOT NULL,
    outcome_reason VARCHAR(1000) NULL,
    accounting_entry_id INT UNSIGNED NULL,
    applied_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_hr_salary_advance_payroll_repayment (salary_advance_request_id, payroll_id),
    KEY idx_hr_salary_advance_payroll_repayment_schedule (repayment_schedule_id),
    KEY idx_hr_salary_advance_payroll_repayment_payroll (payroll_id),
    KEY idx_hr_salary_advance_payroll_repayment_employee (employee_id),
    KEY idx_hr_salary_advance_payroll_repayment_journal (accounting_entry_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Existing payroll records remain unchanged: salary_advance_deduction defaults to zero.
