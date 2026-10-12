-- Employee incentives ledger foundation.
-- This migration creates durable policy/event/award records only.
-- It does not backfill historical events or mutate payroll/accounting data.
-- All monetary amounts are stored with their currency; no FX conversion is implied.

CREATE TABLE IF NOT EXISTS hr_incentive_policy_versions (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    version_no INT UNSIGNED NOT NULL,
    policy_name VARCHAR(150) NOT NULL,
    status ENUM('draft','active','retired') NOT NULL DEFAULT 'draft',
    effective_from DATE NOT NULL,
    effective_to DATE NULL,
    agreement_recorded_at DATETIME NULL,
    agreement_notes TEXT NULL,
    created_by_user_id INT UNSIGNED NOT NULL,
    activated_by_user_id INT UNSIGNED NULL,
    activated_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_hr_incentive_policy_version (version_no),
    KEY idx_hr_incentive_policy_effective (status, effective_from, effective_to),
    KEY idx_hr_incentive_policy_created_by (created_by_user_id),
    KEY idx_hr_incentive_policy_activated_by (activated_by_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS hr_incentive_policy_rules (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    policy_version_id INT UNSIGNED NOT NULL,
    incentive_type ENUM('collection','new_sponsor','reactivation','project_completion') NOT NULL,
    reward_amount DECIMAL(12,2) NOT NULL,
    currency_code CHAR(3) NOT NULL,
    is_enabled TINYINT(1) NOT NULL DEFAULT 1,
    rule_notes VARCHAR(1000) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_hr_incentive_rule_type (policy_version_id, incentive_type),
    KEY idx_hr_incentive_rule_currency (currency_code),
    CONSTRAINT fk_hr_incentive_rule_policy
        FOREIGN KEY (policy_version_id) REFERENCES hr_incentive_policy_versions (id)
        ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS hr_incentive_events (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    idempotency_key VARCHAR(190) NOT NULL,
    source_table VARCHAR(80) NOT NULL,
    source_record_id BIGINT UNSIGNED NOT NULL,
    incentive_type ENUM('collection','new_sponsor','reactivation','project_completion') NOT NULL,
    policy_version_id INT UNSIGNED NOT NULL,
    occurred_at DATETIME NOT NULL,
    source_amount DECIMAL(12,2) NULL,
    source_currency_code CHAR(3) NULL,
    status ENUM('pending_verification','verified','rejected','held') NOT NULL DEFAULT 'pending_verification',
    verification_note VARCHAR(2000) NULL,
    verified_by_user_id INT UNSIGNED NULL,
    verified_at DATETIME NULL,
    created_by_user_id INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_hr_incentive_event_idempotency (idempotency_key),
    KEY idx_hr_incentive_event_source (source_table, source_record_id),
    KEY idx_hr_incentive_event_type_status (incentive_type, status, occurred_at),
    KEY idx_hr_incentive_event_policy (policy_version_id),
    CONSTRAINT fk_hr_incentive_event_policy
        FOREIGN KEY (policy_version_id) REFERENCES hr_incentive_policy_versions (id)
        ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS hr_incentive_project_allocations (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    event_id INT UNSIGNED NOT NULL,
    employee_id INT UNSIGNED NOT NULL,
    recipient_user_id INT UNSIGNED NOT NULL,
    allocation_percent DECIMAL(5,2) NOT NULL,
    reward_amount DECIMAL(12,2) NOT NULL,
    currency_code CHAR(3) NOT NULL,
    approved_by_user_id INT UNSIGNED NOT NULL,
    approved_at DATETIME NOT NULL,
    allocation_note VARCHAR(1000) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_hr_incentive_project_recipient (event_id, employee_id),
    KEY idx_hr_incentive_project_user (recipient_user_id),
    KEY idx_hr_incentive_project_approver (approved_by_user_id),
    CONSTRAINT fk_hr_incentive_project_event
        FOREIGN KEY (event_id) REFERENCES hr_incentive_events (id)
        ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS hr_incentive_awards (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    event_id INT UNSIGNED NOT NULL,
    employee_id INT UNSIGNED NOT NULL,
    recipient_user_id INT UNSIGNED NOT NULL,
    project_allocation_id INT UNSIGNED NULL,
    reward_amount DECIMAL(12,2) NOT NULL,
    reward_currency_code CHAR(3) NOT NULL,
    salary_currency_code CHAR(3) NULL,
    status ENUM('earned','held_currency','scheduled','paid','rejected','adjustment_pending','adjusted') NOT NULL DEFAULT 'earned',
    hold_reason VARCHAR(1000) NULL,
    payroll_id INT UNSIGNED NULL,
    journal_entry_id INT UNSIGNED NULL,
    earned_at DATETIME NOT NULL,
    scheduled_at DATETIME NULL,
    paid_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_hr_incentive_award_event_employee (event_id, employee_id),
    UNIQUE KEY uq_hr_incentive_award_project_allocation (project_allocation_id),
    KEY idx_hr_incentive_award_employee_status (employee_id, status, earned_at),
    KEY idx_hr_incentive_award_user_status (recipient_user_id, status, earned_at),
    KEY idx_hr_incentive_award_payroll (payroll_id),
    KEY idx_hr_incentive_award_journal (journal_entry_id),
    CONSTRAINT fk_hr_incentive_award_event
        FOREIGN KEY (event_id) REFERENCES hr_incentive_events (id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_hr_incentive_award_project_allocation
        FOREIGN KEY (project_allocation_id) REFERENCES hr_incentive_project_allocations (id)
        ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS hr_incentive_adjustments (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    award_id INT UNSIGNED NOT NULL,
    adjustment_type ENUM('correction','reversal') NOT NULL,
    amount_delta DECIMAL(12,2) NOT NULL,
    currency_code CHAR(3) NOT NULL,
    reason VARCHAR(2000) NOT NULL,
    requested_by_user_id INT UNSIGNED NOT NULL,
    reviewed_by_user_id INT UNSIGNED NULL,
    reviewed_at DATETIME NULL,
    journal_entry_id INT UNSIGNED NULL,
    status ENUM('pending_review','approved','rejected','posted') NOT NULL DEFAULT 'pending_review',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_hr_incentive_adjustment_award (award_id, status),
    KEY idx_hr_incentive_adjustment_reviewer (reviewed_by_user_id),
    KEY idx_hr_incentive_adjustment_journal (journal_entry_id),
    CONSTRAINT fk_hr_incentive_adjustment_award
        FOREIGN KEY (award_id) REFERENCES hr_incentive_awards (id)
        ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
