-- HR Salary Advance Stage 4: accounting verification and actual disbursement lifecycle
-- Schema-only migration. No runtime DDL, triggers, or views.

-- Dedicated employee salary-advance receivable/control account.
-- This is intentionally separate from generic 1400 receivables so salary-advance
-- balances can be reconciled directly against HR requests.
INSERT INTO accounts (code, name_ar, name_en, account_type, is_active, description)
SELECT '1410', 'ذمم سلف الموظفين', 'Employee Salary Advances Receivable', 'asset', 1,
       'Control account for employee salary advances; disbursement is not an expense.'
WHERE NOT EXISTS (
    SELECT 1 FROM accounts WHERE code = '1410'
);

ALTER TABLE hr_salary_advance_requests
    MODIFY COLUMN status ENUM(
        'submitted',
        'fm_review',
        'approved',
        'disbursed',
        'settled',
        'rejected',
        'cancelled'
    ) NOT NULL DEFAULT 'submitted',
    ADD COLUMN accounting_status ENUM('pending','verified','rejected') NOT NULL DEFAULT 'pending'
        AFTER fm_customization_reason,
    ADD COLUMN accounting_verified_by INT UNSIGNED NULL
        AFTER accounting_status,
    ADD COLUMN accounting_verified_at DATETIME NULL
        AFTER accounting_verified_by,
    ADD COLUMN accounting_rejection_reason VARCHAR(2000) NULL
        AFTER accounting_verified_at,
    ADD COLUMN disbursed_by INT UNSIGNED NULL
        AFTER accounting_rejection_reason,
    ADD COLUMN disbursed_at DATETIME NULL
        AFTER disbursed_by,
    ADD COLUMN disbursement_account_id INT UNSIGNED NULL
        AFTER disbursed_at,
    ADD COLUMN disbursement_journal_entry_id INT UNSIGNED NULL
        AFTER disbursement_account_id,
    ADD COLUMN disbursement_reference VARCHAR(100) NULL
        AFTER disbursement_journal_entry_id,
    ADD COLUMN outstanding_balance DECIMAL(18,2) NULL
        AFTER disbursement_reference,
    ADD COLUMN settled_by INT UNSIGNED NULL
        AFTER outstanding_balance,
    ADD COLUMN settled_at DATETIME NULL
        AFTER settled_by,
    ADD KEY idx_hr_salary_advance_accounting_status (accounting_status),
    ADD KEY idx_hr_salary_advance_disbursement_account (disbursement_account_id),
    ADD KEY idx_hr_salary_advance_disbursement_journal (disbursement_journal_entry_id),
    ADD KEY idx_hr_salary_advance_status_balance (status, outstanding_balance);
