-- AHL EL KHEIR
-- Fina Al-Khair third-party funds accounting foundation.
--
-- The sponsor's incoming payment remains the historical gross amount.
-- Fina's share is tracked separately as a liability and never as Ahl El Kheir revenue.
-- Monthly sponsor obligations are separate from actual payment records so later payments
-- can be linked without overwriting historical payments.

INSERT INTO accounts (code, name_ar, name_en, account_type, is_active, description)
SELECT '2300', 'مستحقات لصالح Fina Al-Khair', 'Funds Payable to Fina Al-Khair', 'liability', 1,
       'أموال طرف ثالث مخصصة لصالح Fina Al-Khair ولا تمثل إيراداً لأهل الخير.'
WHERE NOT EXISTS (SELECT 1 FROM accounts WHERE code = '2300');

CREATE TABLE IF NOT EXISTS fina_payment_intakes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sponsor_payment_id INT UNSIGNED NOT NULL,
    allocation_mode ENUM('ahl_only','fina_only','shared') NOT NULL,
    fina_share_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    integration_reference VARCHAR(100) NOT NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_fina_intake_payment (sponsor_payment_id),
    UNIQUE KEY uq_fina_intake_reference (integration_reference),
    KEY idx_fina_intake_mode (allocation_mode),
    CONSTRAINT fk_fina_intake_payment FOREIGN KEY (sponsor_payment_id) REFERENCES sponsor_payments(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_fina_intake_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fina_payment_allocations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    transaction_id INT UNSIGNED NOT NULL,
    sponsor_payment_id INT UNSIGNED NULL,
    allocation_mode ENUM('ahl_only','fina_only','shared') NOT NULL,
    gross_amount DECIMAL(12,2) NOT NULL,
    ahl_share_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    fina_share_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    ahl_admin_fee_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    ahl_net_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    settled_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    currency_code CHAR(3) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    status ENUM('protected','partially_settled','settled','voided') NOT NULL DEFAULT 'protected',
    integration_reference VARCHAR(100) NOT NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_fina_allocation_transaction (transaction_id),
    UNIQUE KEY uq_fina_integration_reference (integration_reference),
    KEY idx_fina_alloc_status (status),
    KEY idx_fina_alloc_sponsor_payment (sponsor_payment_id),
    KEY idx_fina_alloc_currency (currency_code),
    CONSTRAINT fk_fina_alloc_transaction FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_fina_alloc_sponsor_payment FOREIGN KEY (sponsor_payment_id) REFERENCES sponsor_payments(id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_fina_alloc_currency FOREIGN KEY (currency_code) REFERENCES currencies(code) ON UPDATE CASCADE,
    CONSTRAINT fk_fina_alloc_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sponsor_payment_obligations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sponsorship_id INT UNSIGNED NOT NULL,
    sponsor_id INT UNSIGNED NOT NULL,
    payment_period VARCHAR(20) NOT NULL,
    due_amount DECIMAL(12,2) NOT NULL,
    paid_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    currency_code CHAR(3) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    status ENUM('open','partially_paid','paid','cancelled') NOT NULL DEFAULT 'open',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_sponsor_obligation_period (sponsorship_id, payment_period),
    KEY idx_sponsor_obligation_sponsor (sponsor_id),
    KEY idx_sponsor_obligation_status (status),
    CONSTRAINT fk_sponsor_obligation_sponsorship FOREIGN KEY (sponsorship_id) REFERENCES sponsorships(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_sponsor_obligation_sponsor FOREIGN KEY (sponsor_id) REFERENCES sponsors(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_sponsor_obligation_currency FOREIGN KEY (currency_code) REFERENCES currencies(code) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fina_settlements (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    settlement_code VARCHAR(50) NOT NULL,
    settlement_date DATE NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    currency_code CHAR(3) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    transfer_method ENUM('bank_transfer','cash','other') NOT NULL DEFAULT 'bank_transfer',
    destination_name VARCHAR(150) NOT NULL DEFAULT 'Fina Al-Khair',
    destination_reference VARCHAR(150) DEFAULT NULL,
    external_reference VARCHAR(100) DEFAULT NULL,
    evidence_path VARCHAR(255) DEFAULT NULL,
    status ENUM('draft','pending_approval','approved','transferred','returned','cancelled','voided') NOT NULL DEFAULT 'draft',
    created_by INT UNSIGNED NULL,
    reviewed_by INT UNSIGNED NULL,
    reviewed_at DATETIME NULL,
    review_note VARCHAR(255) DEFAULT NULL,
    journal_entry_id INT UNSIGNED NULL,
    integration_reference VARCHAR(100) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_fina_settlement_code (settlement_code),
    UNIQUE KEY uq_fina_settlement_integration (integration_reference),
    KEY idx_fina_settlement_status (status),
    KEY idx_fina_settlement_date (settlement_date),
    CONSTRAINT fk_fina_settlement_currency FOREIGN KEY (currency_code) REFERENCES currencies(code) ON UPDATE CASCADE,
    CONSTRAINT fk_fina_settlement_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_fina_settlement_reviewed_by FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fina_settlement_allocations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    settlement_id INT UNSIGNED NOT NULL,
    allocation_id INT UNSIGNED NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_fina_settlement_allocation (settlement_id, allocation_id),
    KEY idx_fina_settlement_alloc_allocation (allocation_id),
    CONSTRAINT fk_fina_settlement_alloc_settlement FOREIGN KEY (settlement_id) REFERENCES fina_settlements(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_fina_settlement_alloc_allocation FOREIGN KEY (allocation_id) REFERENCES fina_payment_allocations(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
