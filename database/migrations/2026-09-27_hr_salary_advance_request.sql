-- HR Salary Advance Stage 2: employee request foundation
-- Schema-only migration. No runtime DDL, triggers, or views.

CREATE TABLE IF NOT EXISTS hr_salary_advance_requests (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    request_no VARCHAR(40) NOT NULL,
    employee_id INT UNSIGNED NOT NULL,
    policy_version_id INT UNSIGNED NOT NULL,
    requested_amount DECIMAL(18,2) NOT NULL,
    requested_repayment_method ENUM('fixed_monthly','full_eligible_salary','full_settlement','direct_repayment') NOT NULL,
    requested_monthly_amount DECIMAL(18,2) NULL,
    requested_start_month DATE NULL,
    request_reason VARCHAR(2000) NULL,
    status ENUM('submitted','fm_review','approved','rejected','cancelled') NOT NULL DEFAULT 'submitted',
    submitted_by INT UNSIGNED NOT NULL,
    submitted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_hr_salary_advance_request_no (request_no),
    KEY idx_hr_salary_advance_request_employee (employee_id),
    KEY idx_hr_salary_advance_request_policy (policy_version_id),
    KEY idx_hr_salary_advance_request_status (status),
    KEY idx_hr_salary_advance_request_submitted_by (submitted_by),
    CONSTRAINT chk_hr_salary_advance_request_amount CHECK (requested_amount > 0),
    CONSTRAINT chk_hr_salary_advance_request_monthly CHECK (requested_monthly_amount IS NULL OR requested_monthly_amount > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
