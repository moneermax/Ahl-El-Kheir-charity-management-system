-- HR Salary Advance Stage 7: policy-authorized, versioned repayment plans.
-- Schema-only migration. Existing repayment rows and payroll allocations are preserved.

ALTER TABLE hr_salary_advance_policy_versions
    ADD COLUMN IF NOT EXISTS allow_fm_repayment_rescheduling TINYINT(1) NOT NULL DEFAULT 0
        AFTER maximum_repayment_months;

CREATE TABLE IF NOT EXISTS hr_salary_advance_repayment_plans (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    salary_advance_request_id INT UNSIGNED NOT NULL,
    plan_no INT UNSIGNED NOT NULL,
    status ENUM('active','superseded') NOT NULL DEFAULT 'active',
    reason VARCHAR(1000) NULL,
    outstanding_balance_at_creation DECIMAL(18,2) NOT NULL,
    start_month DATE NOT NULL,
    maximum_repayment_months INT UNSIGNED NULL,
    maximum_monthly_deduction DECIMAL(18,2) NOT NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    activated_at DATETIME NULL,
    superseded_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_hr_salary_advance_plan_no (salary_advance_request_id, plan_no),
    KEY idx_hr_salary_advance_plan_active (salary_advance_request_id, status),
    KEY idx_hr_salary_advance_plan_created_by (created_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE hr_salary_advance_repayment_schedule
    ADD COLUMN IF NOT EXISTS repayment_plan_id INT UNSIGNED NULL AFTER salary_advance_request_id;

-- Preserve every legacy schedule row by assigning its request's first plan.
INSERT INTO hr_salary_advance_repayment_plans
    (salary_advance_request_id, plan_no, status, reason,
     outstanding_balance_at_creation, start_month, maximum_repayment_months,
     maximum_monthly_deduction, activated_at)
SELECT s.salary_advance_request_id, 1, 'active',
       'Initial schedule migrated from the pre-versioned repayment plan.',
       COALESCE(r.outstanding_balance, 0),
       MIN(s.scheduled_month),
       MAX(p.maximum_repayment_months),
       COALESCE(MAX(p.maximum_monthly_deduction), 0.01),
       CURRENT_TIMESTAMP
FROM hr_salary_advance_repayment_schedule s
JOIN hr_salary_advance_requests r ON r.id = s.salary_advance_request_id
JOIN hr_salary_advance_policy_versions p ON p.id = r.policy_version_id
WHERE NOT EXISTS (
    SELECT 1
    FROM hr_salary_advance_repayment_plans existing
    WHERE existing.salary_advance_request_id = s.salary_advance_request_id
      AND existing.plan_no = 1
)
GROUP BY s.salary_advance_request_id, r.outstanding_balance;

UPDATE hr_salary_advance_repayment_schedule s
JOIN hr_salary_advance_repayment_plans p
  ON p.salary_advance_request_id = s.salary_advance_request_id
 AND p.plan_no = 1
SET s.repayment_plan_id = p.id
WHERE s.repayment_plan_id IS NULL;

-- Uniqueness now applies within a plan, allowing a new plan to retain its
-- own installment numbering/months without deleting the old plan's history.
ALTER TABLE hr_salary_advance_repayment_schedule
    DROP INDEX IF EXISTS uq_hr_salary_advance_schedule_installment,
    DROP INDEX IF EXISTS uq_hr_salary_advance_schedule_month,
    ADD UNIQUE KEY IF NOT EXISTS uq_hr_salary_advance_schedule_plan_installment
        (salary_advance_request_id, repayment_plan_id, installment_no),
    ADD UNIQUE KEY IF NOT EXISTS uq_hr_salary_advance_schedule_plan_month
        (salary_advance_request_id, repayment_plan_id, scheduled_month),
    ADD KEY IF NOT EXISTS idx_hr_salary_advance_schedule_plan (repayment_plan_id);
