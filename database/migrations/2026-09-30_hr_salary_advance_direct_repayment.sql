-- Ahl El Kheir Charity Management System
-- HR Salary Advance Stage 6: direct repayment transaction history
-- Schema-only migration. No runtime DDL, triggers, or views.

-- Direct cash/bank/wallet repayments are separate from payroll repayments.
-- One row represents one posted direct repayment transaction and its journal.
CREATE TABLE IF NOT EXISTS hr_salary_advance_direct_repayments (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    salary_advance_request_id INT UNSIGNED NOT NULL,
    repayment_amount DECIMAL(18,2) NOT NULL,
    repayment_account_id INT UNSIGNED NOT NULL,
    accounting_entry_id INT UNSIGNED NOT NULL,
    repayment_reference VARCHAR(100) NULL,
    repayment_date DATE NOT NULL,
    received_by INT UNSIGNED NOT NULL,
    notes VARCHAR(2000) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_hr_salary_advance_direct_repayment_journal (accounting_entry_id),
    KEY idx_hr_salary_advance_direct_repayment_request (salary_advance_request_id),
    KEY idx_hr_salary_advance_direct_repayment_account (repayment_account_id),
    KEY idx_hr_salary_advance_direct_repayment_receiver (received_by),
    KEY idx_hr_salary_advance_direct_repayment_date (repayment_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
