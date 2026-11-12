-- AHL EL KHEIR
-- Effective-dated, auditable mapping for sponsorship service-fee revenue.
-- No default account is assigned. Positive service-fee posting remains blocked
-- until an authorized accounting owner records an approved mapping.
-- Apply through the project's migration process. No request-time DDL.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS sponsorship_service_fee_account_mappings (
    id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    effective_from DATE NOT NULL,
    version_no INT(10) UNSIGNED NOT NULL,
    account_id INT(10) UNSIGNED NOT NULL,
    authorized_by_name VARCHAR(150) NOT NULL,
    authorization_reference VARCHAR(255) NOT NULL,
    authorization_note TEXT DEFAULT NULL,
    created_by INT(10) UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_ssfam_effective_version (effective_from, version_no),
    KEY idx_ssfam_account (account_id),
    KEY idx_ssfam_effective_latest (effective_from, version_no, id),
    CONSTRAINT fk_ssfam_account FOREIGN KEY (account_id)
        REFERENCES accounts (id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_ssfam_creator FOREIGN KEY (created_by)
        REFERENCES users (id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE sponsorship_service_fee_account_mappings
    ADD COLUMN IF NOT EXISTS authorized_by_name VARCHAR(150) NOT NULL DEFAULT '';

ALTER TABLE transactions
    ADD COLUMN IF NOT EXISTS service_fee_account_mapping_id INT(10) UNSIGNED DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS service_fee_account_id INT(10) UNSIGNED DEFAULT NULL;

SET @ssfam_txn_idx_sql = IF(
    (SELECT COUNT(*) FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'transactions'
       AND INDEX_NAME = 'idx_transactions_service_fee_mapping') = 0,
    'ALTER TABLE transactions ADD KEY idx_transactions_service_fee_mapping (service_fee_account_mapping_id)',
    'SELECT 1'
);
PREPARE ssfam_txn_idx_stmt FROM @ssfam_txn_idx_sql;
EXECUTE ssfam_txn_idx_stmt;
DEALLOCATE PREPARE ssfam_txn_idx_stmt;

SET @ssfam_mapping_fk_sql = IF(
    (SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS
     WHERE CONSTRAINT_SCHEMA = DATABASE()
       AND TABLE_NAME = 'transactions'
       AND CONSTRAINT_NAME = 'fk_transactions_ssfam_mapping') = 0,
    'ALTER TABLE transactions ADD CONSTRAINT fk_transactions_ssfam_mapping FOREIGN KEY (service_fee_account_mapping_id) REFERENCES sponsorship_service_fee_account_mappings (id) ON UPDATE CASCADE ON DELETE RESTRICT',
    'SELECT 1'
);
PREPARE ssfam_mapping_fk_stmt FROM @ssfam_mapping_fk_sql;
EXECUTE ssfam_mapping_fk_stmt;
DEALLOCATE PREPARE ssfam_mapping_fk_stmt;

SET @ssfam_account_fk_sql = IF(
    (SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS
     WHERE CONSTRAINT_SCHEMA = DATABASE()
       AND TABLE_NAME = 'transactions'
       AND CONSTRAINT_NAME = 'fk_transactions_ssfam_account') = 0,
    'ALTER TABLE transactions ADD CONSTRAINT fk_transactions_ssfam_account FOREIGN KEY (service_fee_account_id) REFERENCES accounts (id) ON UPDATE CASCADE ON DELETE RESTRICT',
    'SELECT 1'
);
PREPARE ssfam_account_fk_stmt FROM @ssfam_account_fk_sql;
EXECUTE ssfam_account_fk_stmt;
DEALLOCATE PREPARE ssfam_account_fk_stmt;
