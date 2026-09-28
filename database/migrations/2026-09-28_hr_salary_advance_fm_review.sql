-- HR Salary Advance Stage 3: FM review and request-level decision terms
-- Schema-only migration. No runtime DDL, triggers, or views.

ALTER TABLE hr_salary_advance_requests
    ADD COLUMN fm_reviewed_by INT UNSIGNED NULL AFTER submitted_at,
    ADD COLUMN fm_reviewed_at DATETIME NULL AFTER fm_reviewed_by,
    ADD COLUMN fm_decision VARCHAR(20) NULL AFTER fm_reviewed_at,
    ADD COLUMN fm_rejection_reason VARCHAR(2000) NULL AFTER fm_decision,
    ADD COLUMN approved_amount DECIMAL(18,2) NULL AFTER fm_rejection_reason,
    ADD COLUMN approved_repayment_method ENUM('fixed_monthly','full_eligible_salary','full_settlement','direct_repayment') NULL AFTER approved_amount,
    ADD COLUMN approved_monthly_amount DECIMAL(18,2) NULL AFTER approved_repayment_method,
    ADD COLUMN approved_start_month DATE NULL AFTER approved_monthly_amount,
    ADD COLUMN fm_customized TINYINT(1) NOT NULL DEFAULT 0 AFTER approved_start_month,
    ADD COLUMN fm_customization_reason VARCHAR(2000) NULL AFTER fm_customized,
    ADD KEY idx_hr_salary_advance_request_fm_reviewed_by (fm_reviewed_by),
    ADD KEY idx_hr_salary_advance_request_fm_decision (fm_decision);
