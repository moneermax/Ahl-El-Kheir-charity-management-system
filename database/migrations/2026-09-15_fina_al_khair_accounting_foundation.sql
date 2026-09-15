-- Ahl El Kheir — Fina Al-Khair accounting foundation
-- Phase 1: verified-schema foundation only.
-- Fina-designated funds are third-party funds payable to Fina, not Ahl El Kheir revenue.
-- No historical financial data is rewritten by this migration.

INSERT INTO accounts (code, name_ar, name_en, account_type, parent_id, is_active, description)
SELECT
    '2300',
    'أموال مستحقة لفينا الخير',
    'Funds Payable to Fina Al-Khair',
    'liability',
    NULL,
    1,
    'Protected third-party funds collected by Ahl El Kheir on behalf of Fina Al-Khair'
WHERE NOT EXISTS (
    SELECT 1 FROM accounts WHERE code = '2300'
);

CREATE TABLE IF NOT EXISTS fina_payment_allocations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    allocation_code VARCHAR(50) NOT NULL,
    transaction_id INT UNSIGNED NULL,
    sponsorship_id INT UNSIGNED NULL,
    payment_period VARCHAR(30) NULL,
    destination_type ENUM('ahl_el_kheir','fina_al_khair') NOT NULL,
    amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    currency_code VARCHAR(10) NOT NULL DEFAULT 'SDG',
    status ENUM('pending','posted','partially_settled','settled','cancelled') NOT NULL DEFAULT 'pending',
    external_reference VARCHAR(100) NULL,
    integration_reference VARCHAR(100) NULL,
    notes VARCHAR(500) NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    posted_at DATETIME NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_fina_payment_allocations_code (allocation_code),
    KEY idx_fina_alloc_txn (transaction_id),
    KEY idx_fina_alloc_sponsorship (sponsorship_id),
    KEY idx_fina_alloc_destination (destination_type),
    KEY idx_fina_alloc_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fina_settlements (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    settlement_code VARCHAR(50) NOT NULL,
    partner_code VARCHAR(50) NOT NULL DEFAULT 'FINA_ALKHAIR',
    settlement_date DATE NOT NULL,
    amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    currency_code VARCHAR(10) NOT NULL DEFAULT 'SDG',
    payment_method ENUM('cash','bank_transfer','mobile','other') NOT NULL DEFAULT 'bank_transfer',
    source_account_id INT UNSIGNED NULL,
    destination_reference VARCHAR(150) NULL,
    external_reference VARCHAR(100) NULL,
    integration_reference VARCHAR(100) NULL,
    status ENUM('draft','pending_approval','approved','transferred','settled','returned','cancelled','voided') NOT NULL DEFAULT 'draft',
    created_by INT UNSIGNED NULL,
    approved_by INT UNSIGNED NULL,
    transferred_by INT UNSIGNED NULL,
    settled_by INT UNSIGNED NULL,
    approved_at DATETIME NULL,
    transferred_at DATETIME NULL,
    settled_at DATETIME NULL,
    notes VARCHAR(500) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_fina_settlements_code (settlement_code),
    KEY idx_fina_settlements_status (status),
    KEY idx_fina_settlements_date (settlement_date),
    KEY idx_fina_settlements_partner (partner_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fina_settlement_allocations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    settlement_id INT UNSIGNED NOT NULL,
    allocation_id INT UNSIGNED NOT NULL,
    amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_fina_settlement_allocation (settlement_id, allocation_id),
    KEY idx_fina_sa_settlement (settlement_id),
    KEY idx_fina_sa_allocation (allocation_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
