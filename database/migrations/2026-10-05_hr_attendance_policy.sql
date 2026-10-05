-- HR Attendance Policy foundation
-- Versioned organizational attendance rules. No runtime DDL, triggers, or views.

CREATE TABLE IF NOT EXISTS hr_attendance_policy_versions (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    version_no INT UNSIGNED NOT NULL,
    policy_name VARCHAR(150) NOT NULL,
    effective_from DATE NOT NULL,
    working_start_time TIME NOT NULL DEFAULT '07:00:00',
    working_end_time TIME NOT NULL DEFAULT '16:00:00',
    attendance_cutoff_time TIME NOT NULL DEFAULT '16:00:00',
    absence_finalization_time TIME NOT NULL DEFAULT '16:00:00',
    auto_login_attendance TINYINT(1) NOT NULL DEFAULT 1,
    auto_absence_enabled TINYINT(1) NOT NULL DEFAULT 1,
    default_work_mode ENUM('remote','onsite','hybrid') NOT NULL DEFAULT 'remote',
    notes VARCHAR(2000) NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_hr_attendance_policy_version (version_no),
    UNIQUE KEY uq_hr_attendance_policy_effective (effective_from),
    KEY idx_hr_attendance_policy_effective (effective_from),
    KEY idx_hr_attendance_policy_created_by (created_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
