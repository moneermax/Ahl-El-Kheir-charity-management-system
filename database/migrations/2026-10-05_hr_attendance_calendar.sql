-- HR Attendance Calendar
-- Date-specific non-working holidays. Weekly schedule remains in attendance policy.
-- No runtime DDL, triggers, views, procedures, functions, or events.

CREATE TABLE IF NOT EXISTS hr_holiday_definitions (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    code VARCHAR(80) NOT NULL,
    name_ar VARCHAR(200) NOT NULL,
    name_en VARCHAR(200) NULL,
    holiday_type ENUM('religious','official','organizational') NOT NULL DEFAULT 'official',
    calendar_basis ENUM('gregorian','hijri','manual') NOT NULL DEFAULT 'manual',
    default_duration_days SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    notes VARCHAR(2000) NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_hr_holiday_definition_code (code),
    KEY idx_hr_holiday_definition_active (is_active),
    KEY idx_hr_holiday_definition_type (holiday_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS hr_holiday_calendar (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    holiday_definition_id INT UNSIGNED NULL,
    name_ar VARCHAR(200) NOT NULL,
    name_en VARCHAR(200) NULL,
    holiday_type ENUM('religious','official','organizational') NOT NULL DEFAULT 'official',
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    status ENUM('provisional','confirmed','cancelled') NOT NULL DEFAULT 'confirmed',
    source_label VARCHAR(200) NULL,
    source_reference VARCHAR(1000) NULL,
    notes VARCHAR(2000) NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_hr_holiday_calendar_dates (start_date, end_date),
    KEY idx_hr_holiday_calendar_status (status),
    KEY idx_hr_holiday_calendar_definition (holiday_definition_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
