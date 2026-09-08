-- Persist an approved leave's effective return date without modifying the
-- original HR-approved leave interval. This keeps the leave record as the
-- historical approval while allowing attendance to resume from the return date.
--
-- No foreign keys are used intentionally, matching the HR foundation's
-- compatibility approach for the existing production schema.

CREATE TABLE IF NOT EXISTS hr_leave_returns (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    leave_id BIGINT UNSIGNED NOT NULL,
    employee_id BIGINT UNSIGNED NOT NULL,
    return_date DATE NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_hr_leave_returns_leave (leave_id),
    KEY idx_hr_leave_returns_employee_date (employee_id, return_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
