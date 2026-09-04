-- Supervisor organizational lifecycle foundation
-- Supports reversible leave/suspension and permanent departure without deleting identity/history.

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS supervisor_status ENUM('active','on_leave','suspended','returning','departed','archived') NULL AFTER is_active;

UPDATE users
SET supervisor_status = CASE
    WHEN legacy_status = 'deleted' THEN 'archived'
    WHEN is_active = 1 THEN 'active'
    ELSE 'suspended'
END
WHERE role_id = (SELECT id FROM roles WHERE code = 'supervisor')
  AND supervisor_status IS NULL;

ALTER TABLE users
    MODIFY COLUMN supervisor_status ENUM('active','on_leave','suspended','returning','departed','archived') NOT NULL DEFAULT 'active';

CREATE TABLE IF NOT EXISTS supervisor_leaves (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    supervisor_id INT UNSIGNED NOT NULL,
    leave_type ENUM('vacation','personal','medical','maternity','study','other') NOT NULL DEFAULT 'other',
    start_date DATE NOT NULL,
    expected_return_date DATE NULL,
    actual_return_date DATE NULL,
    reason VARCHAR(255) NULL,
    notes TEXT NULL,
    status ENUM('planned','active','completed','cancelled') NOT NULL DEFAULT 'planned',
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_by INT UNSIGNED NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_supervisor_leave_supervisor (supervisor_id),
    INDEX idx_supervisor_leave_status (status),
    INDEX idx_supervisor_leave_dates (start_date, expected_return_date),
    CONSTRAINT fk_supervisor_leave_supervisor FOREIGN KEY (supervisor_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_supervisor_leave_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_supervisor_leave_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE sponsor_supervisor_assignments
    ADD COLUMN IF NOT EXISTS assignment_type ENUM('permanent','temporary') NOT NULL DEFAULT 'permanent' AFTER assigned_by,
    ADD COLUMN IF NOT EXISTS assignment_reason VARCHAR(100) NULL AFTER end_reason;

-- Historical supervisor-letter assignments.
-- supervisor_letter_id is nullable because the live supervisor_letters row is intentionally
-- deleted/released when a supervisor departs. The historical row must survive that deletion.
CREATE TABLE IF NOT EXISTS supervisor_letter_assignment_history (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    supervisor_letter_id INT UNSIGNED NULL,
    supervisor_id INT UNSIGNED NOT NULL,
    letter_id TINYINT UNSIGNED NOT NULL,
    gender ENUM('male','female','both') NOT NULL DEFAULT 'both',
    assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    assigned_by INT UNSIGNED NULL,
    assignment_type ENUM('permanent','temporary') NOT NULL DEFAULT 'permanent',
    assignment_reason VARCHAR(100) NULL,
    ended_at DATETIME NULL,
    ended_by INT UNSIGNED NULL,
    end_reason VARCHAR(100) NULL,
    INDEX idx_slah_supervisor (supervisor_id),
    INDEX idx_slah_letter (letter_id),
    INDEX idx_slah_supervisor_letter (supervisor_letter_id),
    INDEX idx_slah_active (letter_id, gender, ended_at),
    CONSTRAINT fk_slah_supervisor FOREIGN KEY (supervisor_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_slah_letter FOREIGN KEY (letter_id) REFERENCES letters(id) ON DELETE RESTRICT,
    CONSTRAINT fk_slah_assigned_by FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_slah_ended_by FOREIGN KEY (ended_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_slah_supervisor_letter FOREIGN KEY (supervisor_letter_id) REFERENCES supervisor_letters(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
