-- Salary Advance GM Waiver / Exemption
-- Additive, isolated schema only. No existing salary-advance columns/statuses are modified.
-- Rollback: remove this feature by dropping only the two tables created here.
-- IMPORTANT: posted accounting history must never be deleted as part of rollback.
CREATE TABLE IF NOT EXISTS hr_salary_advance_waiver_decisions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    decision_no VARCHAR(50) NOT NULL,
    decision_type ENUM('individual','blanket') NOT NULL,
    effective_month DATE NOT NULL,
    reason VARCHAR(2000) NOT NULL,
    status ENUM('pending_fm','rejected','executed') NOT NULL DEFAULT 'pending_fm',
    created_by INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fm_reviewed_by INT UNSIGNED NULL,
    fm_reviewed_at DATETIME NULL,
    fm_rejection_reason VARCHAR(2000) NULL,
    executed_at DATETIME NULL,
    UNIQUE KEY uq_hr_salary_advance_waiver_decision_no (decision_no),
    KEY idx_hr_salary_advance_waiver_decision_status (status),
    KEY idx_hr_salary_advance_waiver_decision_effective_month (effective_month),
    KEY idx_hr_salary_advance_waiver_decision_type (decision_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS hr_salary_advance_waiver_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    decision_id INT UNSIGNED NOT NULL,
    salary_advance_request_id INT UNSIGNED NOT NULL,
    employee_id INT UNSIGNED NOT NULL,
    balance_before DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    current_period_repayment DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    refund_amount DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    balance_before_waiver DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    waived_amount DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    balance_after DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    previous_request_status VARCHAR(50) NOT NULL,
    resulting_request_status VARCHAR(50) NOT NULL,
    refund_account_id INT UNSIGNED NULL,
    refund_journal_entry_id INT UNSIGNED NULL,
    waiver_expense_account_id INT UNSIGNED NULL,
    waiver_journal_entry_id INT UNSIGNED NULL,
    executed_at DATETIME NULL,
    KEY idx_hr_salary_advance_waiver_item_decision (decision_id),
    KEY idx_hr_salary_advance_waiver_item_request (salary_advance_request_id),
    KEY idx_hr_salary_advance_waiver_item_employee (employee_id),
    KEY idx_hr_salary_advance_waiver_item_refund_journal (refund_journal_entry_id),
    KEY idx_hr_salary_advance_waiver_item_waiver_journal (waiver_journal_entry_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
