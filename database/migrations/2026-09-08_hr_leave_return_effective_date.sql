-- Persist an approved leave's effective return date without modifying the
-- original HR-approved leave interval. This keeps the leave record as the
-- historical approval while allowing attendance to resume from the return date.

CREATE TABLE IF NOT EXISTS hr_leave_returns (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    leave_id BIGINT UNSIGNED NOT NULL,
    employee_id BIGINT UNSIGNED NOT NULL,
    return_date DATE NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_hr_leave_returns_leave (leave_id),
    KEY idx_hr_leave_returns_employee_date (employee_id, return_date),
    CONSTRAINT fk_hr_leave_returns_leave
        FOREIGN KEY (leave_id) REFERENCES leaves(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_hr_leave_returns_employee
        FOREIGN KEY (employee_id) REFERENCES employees(id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
