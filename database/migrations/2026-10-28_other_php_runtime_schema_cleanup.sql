SET NAMES utf8mb4;

-- Move profile, sponsor import, transaction receipt and payroll-reversal schema setup
-- out of page/endpoint requests. Definitions follow database/ahl_el_kheir.sql.

CREATE TABLE IF NOT EXISTS sponsor_import_exceptions (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    batch_id VARCHAR(50) DEFAULT NULL,
    sheet_row INT DEFAULT NULL,
    mother_name VARCHAR(255) DEFAULT NULL,
    sponsor_entry VARCHAR(500) DEFAULT NULL,
    reason VARCHAR(255) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS hr_payroll_reversals (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    payroll_id INT UNSIGNED NOT NULL,
    original_entry_id INT UNSIGNED NOT NULL,
    reversal_entry_id INT UNSIGNED NOT NULL,
    reason VARCHAR(255) NOT NULL,
    reversed_by INT UNSIGNED DEFAULT NULL,
    reversed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_hr_payroll_reversal_payroll (payroll_id),
    KEY idx_hr_payroll_reversal_entry (reversal_entry_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS avatar_path VARCHAR(255) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS address VARCHAR(255) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS birth_date DATE DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS gender VARCHAR(20) DEFAULT NULL;

ALTER TABLE sponsors
    ADD COLUMN IF NOT EXISTS is_manual_override TINYINT(1) NOT NULL DEFAULT 0;

ALTER TABLE transactions
    ADD COLUMN IF NOT EXISTS receipt_path VARCHAR(255) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS unified_receipt_path VARCHAR(255) DEFAULT NULL;
