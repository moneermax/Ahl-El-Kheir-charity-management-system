-- FM payroll transfer workflow foundation.
-- Adds period-level default payment-account settings and per-payroll private proof metadata.
-- Does not alter payroll status, accounting entries, payment dates, or historical payroll rows.
-- File bytes are stored outside public web access; this table stores only metadata and an opaque storage key.
-- Apply only through the documented migration process after reviewing the SQL against the target schema.

CREATE TABLE IF NOT EXISTS hr_payroll_period_settings (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    month INT NOT NULL,
    year INT NOT NULL,
    default_payment_account_id INT UNSIGNED NOT NULL,
    created_by_user_id INT UNSIGNED NULL,
    updated_by_user_id INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_hr_payroll_period_settings_period (month, year),
    KEY idx_hr_payroll_period_settings_account (default_payment_account_id),
    KEY idx_hr_payroll_period_settings_created_by (created_by_user_id),
    KEY idx_hr_payroll_period_settings_updated_by (updated_by_user_id),
    CONSTRAINT fk_hr_payroll_period_settings_account
        FOREIGN KEY (default_payment_account_id) REFERENCES accounts (id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_hr_payroll_period_settings_created_by
        FOREIGN KEY (created_by_user_id) REFERENCES users (id)
        ON UPDATE RESTRICT ON DELETE SET NULL,
    CONSTRAINT fk_hr_payroll_period_settings_updated_by
        FOREIGN KEY (updated_by_user_id) REFERENCES users (id)
        ON UPDATE RESTRICT ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS hr_payroll_payment_proofs (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    payroll_id INT UNSIGNED NOT NULL,
    proof_state ENUM('prepared','confirmed','legacy_evidence') NOT NULL DEFAULT 'prepared',
    storage_key VARCHAR(190) NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    detected_mime_type VARCHAR(100) NOT NULL,
    file_size_bytes BIGINT UNSIGNED NOT NULL,
    uploaded_by_user_id INT UNSIGNED NULL,
    uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    confirmed_by_user_id INT UNSIGNED NULL,
    confirmed_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_hr_payroll_payment_proofs_payroll (payroll_id),
    UNIQUE KEY uq_hr_payroll_payment_proofs_storage_key (storage_key),
    KEY idx_hr_payroll_payment_proofs_state (proof_state),
    KEY idx_hr_payroll_payment_proofs_uploaded_by (uploaded_by_user_id),
    KEY idx_hr_payroll_payment_proofs_confirmed_by (confirmed_by_user_id),
    CONSTRAINT fk_hr_payroll_payment_proofs_payroll
        FOREIGN KEY (payroll_id) REFERENCES payroll (id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_hr_payroll_payment_proofs_uploaded_by
        FOREIGN KEY (uploaded_by_user_id) REFERENCES users (id)
        ON UPDATE RESTRICT ON DELETE SET NULL,
    CONSTRAINT fk_hr_payroll_payment_proofs_confirmed_by
        FOREIGN KEY (confirmed_by_user_id) REFERENCES users (id)
        ON UPDATE RESTRICT ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
