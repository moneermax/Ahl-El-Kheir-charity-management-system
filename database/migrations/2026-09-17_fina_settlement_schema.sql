-- AHL EL KHEIR
-- Fina Al-Khair settlement and allocation schema.
-- Stage 2: dedicated settlement records + collection-level allocation.
-- This migration does not modify existing fina_collections rows or their journals.
-- Settlement accounting and runtime workflow are implemented separately.

CREATE TABLE IF NOT EXISTS fina_settlements (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    settlement_code VARCHAR(50) NOT NULL,
    settlement_date DATE NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    currency_code VARCHAR(10) NOT NULL,
    payment_method VARCHAR(32) NOT NULL,
    remitting_account_id INT UNSIGNED NOT NULL,
    transfer_reference VARCHAR(150) NULL,
    evidence_path VARCHAR(500) NULL,
    status ENUM('draft','approved','transferred','reconciled','closed','cancelled') NOT NULL DEFAULT 'draft',
    settlement_journal_id INT UNSIGNED NULL,
    reconciliation_note TEXT NULL,
    created_by INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    approved_by INT UNSIGNED NULL,
    approved_at DATETIME NULL,
    transferred_by INT UNSIGNED NULL,
    transferred_at DATETIME NULL,
    reconciled_by INT UNSIGNED NULL,
    reconciled_at DATETIME NULL,
    cancelled_by INT UNSIGNED NULL,
    cancelled_at DATETIME NULL,
    cancellation_note TEXT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_fina_settlements_code (settlement_code),
    KEY idx_fina_settlements_date (settlement_date),
    KEY idx_fina_settlements_status (status),
    KEY idx_fina_settlements_currency (currency_code),
    KEY idx_fina_settlements_account (remitting_account_id),
    KEY idx_fina_settlements_journal (settlement_journal_id),
    CONSTRAINT fk_fina_settlements_account FOREIGN KEY (remitting_account_id) REFERENCES accounts(id) ON UPDATE CASCADE,
    CONSTRAINT fk_fina_settlements_journal FOREIGN KEY (settlement_journal_id) REFERENCES journal_entries(id) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fina_settlement_allocations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    settlement_id BIGINT UNSIGNED NOT NULL,
    fina_collection_id BIGINT UNSIGNED NOT NULL,
    allocated_amount DECIMAL(15,2) NOT NULL,
    created_by INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_fina_settlement_collection (settlement_id, fina_collection_id),
    KEY idx_fina_allocations_collection (fina_collection_id),
    KEY idx_fina_allocations_settlement (settlement_id),
    CONSTRAINT fk_fina_allocations_settlement FOREIGN KEY (settlement_id) REFERENCES fina_settlements(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_fina_allocations_collection FOREIGN KEY (fina_collection_id) REFERENCES fina_collections(id) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
