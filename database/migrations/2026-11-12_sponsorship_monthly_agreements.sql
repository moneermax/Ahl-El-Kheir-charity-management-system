-- AHL EL KHEIR
-- Versioned monthly sponsorship agreements and per-submission component snapshots.
-- Apply through the project's migration process. No request-time DDL.
-- Legacy rows retain component_model='legacy' and NULL component snapshots.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS sponsorship_monthly_agreements (
    id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    sponsorship_id INT(10) UNSIGNED NOT NULL,
    covered_month DATE NOT NULL COMMENT 'First day of the covered calendar month',
    version_no INT(10) UNSIGNED NOT NULL,
    agreed_sponsorship_amount DECIMAL(12,2) NOT NULL,
    agreed_service_fee_amount DECIMAL(12,2) NOT NULL,
    currency_code CHAR(3) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    meeting_reference VARCHAR(255) DEFAULT NULL,
    amendment_reason TEXT DEFAULT NULL,
    created_by INT(10) UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_spma_sponsorship_month_version (sponsorship_id, covered_month, version_no),
    KEY idx_spma_month_latest (sponsorship_id, covered_month, id),
    CONSTRAINT fk_spma_sponsorship FOREIGN KEY (sponsorship_id)
        REFERENCES sponsorships (id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_spma_currency FOREIGN KEY (currency_code)
        REFERENCES currencies (code) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_spma_creator FOREIGN KEY (created_by)
        REFERENCES users (id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT chk_spma_sponsorship_amount CHECK (agreed_sponsorship_amount >= 0),
    CONSTRAINT chk_spma_service_fee_amount CHECK (agreed_service_fee_amount >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE transactions
    ADD COLUMN IF NOT EXISTS component_model ENUM('legacy','monthly_agreement') NOT NULL DEFAULT 'legacy',
    ADD COLUMN IF NOT EXISTS monthly_agreement_id INT(10) UNSIGNED DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS agreed_sponsorship_amount DECIMAL(12,2) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS agreed_service_fee_amount DECIMAL(12,2) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS actual_sponsorship_received DECIMAL(12,2) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS actual_service_fee_received DECIMAL(12,2) DEFAULT NULL;

ALTER TABLE sponsor_payments
    ADD COLUMN IF NOT EXISTS component_model ENUM('legacy','monthly_agreement') NOT NULL DEFAULT 'legacy',
    ADD COLUMN IF NOT EXISTS monthly_agreement_id INT(10) UNSIGNED DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS agreed_sponsorship_amount DECIMAL(12,2) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS agreed_service_fee_amount DECIMAL(12,2) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS actual_sponsorship_received DECIMAL(12,2) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS actual_service_fee_received DECIMAL(12,2) DEFAULT NULL;

-- MariaDB 10.4 has no ADD CONSTRAINT IF NOT EXISTS. Check the catalog so the
-- migration remains repeatable in the repository's disposable CI schema tests.
SET @spma_txn_fk_sql = IF(
    (SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS
     WHERE CONSTRAINT_SCHEMA = DATABASE()
       AND TABLE_NAME = 'transactions'
       AND CONSTRAINT_NAME = 'fk_transactions_spma') = 0,
    'ALTER TABLE transactions ADD CONSTRAINT fk_transactions_spma FOREIGN KEY (monthly_agreement_id) REFERENCES sponsorship_monthly_agreements (id) ON UPDATE CASCADE ON DELETE RESTRICT',
    'SELECT 1'
);
PREPARE spma_txn_fk_stmt FROM @spma_txn_fk_sql;
EXECUTE spma_txn_fk_stmt;
DEALLOCATE PREPARE spma_txn_fk_stmt;

SET @spma_payment_fk_sql = IF(
    (SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS
     WHERE CONSTRAINT_SCHEMA = DATABASE()
       AND TABLE_NAME = 'sponsor_payments'
       AND CONSTRAINT_NAME = 'fk_sponsor_payments_spma') = 0,
    'ALTER TABLE sponsor_payments ADD CONSTRAINT fk_sponsor_payments_spma FOREIGN KEY (monthly_agreement_id) REFERENCES sponsorship_monthly_agreements (id) ON UPDATE CASCADE ON DELETE RESTRICT',
    'SELECT 1'
);
PREPARE spma_payment_fk_stmt FROM @spma_payment_fk_sql;
EXECUTE spma_payment_fk_stmt;
DEALLOCATE PREPARE spma_payment_fk_stmt;
