SET NAMES utf8mb4;

-- Accounting core schema moved from request-time helper functions into migration.
-- Table definitions and indexes follow database/ahl_el_kheir.sql.

CREATE TABLE IF NOT EXISTS accounts (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    code VARCHAR(20) NOT NULL,
    name_ar VARCHAR(150) NOT NULL,
    name_en VARCHAR(150) DEFAULT NULL,
    account_type ENUM('asset','liability','equity','revenue','expense') NOT NULL,
    parent_id INT UNSIGNED DEFAULT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    description VARCHAR(255) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_accounts_code (code),
    KEY idx_accounts_type (account_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS journal_entries (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    entry_code VARCHAR(50) NOT NULL,
    entry_date DATE NOT NULL,
    description VARCHAR(255) DEFAULT NULL,
    reference_type VARCHAR(50) DEFAULT NULL,
    reference_id INT UNSIGNED DEFAULT NULL,
    status ENUM('posted','voided') NOT NULL DEFAULT 'posted',
    voided_at DATETIME DEFAULT NULL,
    voided_by INT UNSIGNED DEFAULT NULL,
    void_reason VARCHAR(255) DEFAULT NULL,
    created_by INT UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_je_code (entry_code),
    KEY idx_je_date (entry_date),
    KEY idx_je_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS journal_lines (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    entry_id INT UNSIGNED NOT NULL,
    account_id INT UNSIGNED NOT NULL,
    debit DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    credit DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    description VARCHAR(255) DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_jl_entry (entry_id),
    KEY idx_jl_account (account_id),
    CONSTRAINT fk_jl_entry FOREIGN KEY (entry_id) REFERENCES journal_entries (id) ON DELETE CASCADE,
    CONSTRAINT fk_jl_account FOREIGN KEY (account_id) REFERENCES accounts (id) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS vouchers (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    voucher_type ENUM('receipt','payment') NOT NULL,
    voucher_no VARCHAR(50) NOT NULL,
    voucher_date DATE NOT NULL,
    party_name VARCHAR(150) DEFAULT NULL,
    amount DECIMAL(14,2) NOT NULL,
    cash_account_id INT UNSIGNED NOT NULL,
    other_account_id INT UNSIGNED NOT NULL,
    description VARCHAR(255) DEFAULT NULL,
    reference_number VARCHAR(100) DEFAULT NULL,
    entry_id INT UNSIGNED DEFAULT NULL,
    status ENUM('posted','voided') NOT NULL DEFAULT 'posted',
    created_by INT UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_vouchers_no (voucher_no),
    KEY idx_vouchers_type (voucher_type),
    KEY idx_vouchers_date (voucher_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS project_beneficiaries (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    project_id INT UNSIGNED NOT NULL,
    beneficiary_name VARCHAR(150) NOT NULL,
    details VARCHAR(255) DEFAULT NULL,
    amount DECIMAL(12,2) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_pb_project (project_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS accounting_admin_fee_policies (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    policy_name VARCHAR(150) NOT NULL DEFAULT 'سياسة الرسوم الإدارية',
    method ENUM('none','fixed','percentage') NOT NULL DEFAULT 'none',
    value DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    currency_code CHAR(3) NOT NULL DEFAULT 'SDG',
    effective_from DATE NOT NULL,
    status ENUM('draft','active','superseded') NOT NULL DEFAULT 'draft',
    opening_balance_entry_id INT UNSIGNED DEFAULT NULL,
    published_at DATETIME DEFAULT NULL,
    created_by INT UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_afp_status_effective (status, effective_from),
    KEY idx_afp_opening_balance (opening_balance_entry_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE other_projects
    ADD COLUMN IF NOT EXISTS revenue_account_id INT UNSIGNED DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS expense_account_id INT UNSIGNED DEFAULT NULL;

ALTER TABLE transactions
    ADD COLUMN IF NOT EXISTS admin_fee_method VARCHAR(20) NOT NULL DEFAULT 'none',
    ADD COLUMN IF NOT EXISTS admin_fee_value DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    ADD COLUMN IF NOT EXISTS admin_fee_policy_id INT UNSIGNED DEFAULT NULL;

ALTER TABLE sponsor_payments
    ADD COLUMN IF NOT EXISTS payment_method VARCHAR(30) NOT NULL DEFAULT 'cash';
