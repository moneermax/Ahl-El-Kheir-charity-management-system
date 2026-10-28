SET NAMES utf8mb4;

-- Move monthly disbursement, item and nanny-verification schema setup out of requests.
-- Canonical definitions follow database/ahl_el_kheir.sql.

CREATE TABLE IF NOT EXISTS monthly_disbursements (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    month VARCHAR(7) NOT NULL,
    nanny_id INT UNSIGNED NOT NULL,
    group_id INT UNSIGNED DEFAULT NULL,
    total_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    status ENUM('draft','pending_approval','approved','transferred','received','returned','cancelled','voided') NOT NULL DEFAULT 'draft',
    transaction_id INT DEFAULT NULL COMMENT 'Links to transactions.id upon FM approval',
    expense_account_code VARCHAR(20) NOT NULL DEFAULT '5100' COMMENT 'COA code for outflow journal (e.g., 5100 Programs & Aid)',
    reviewed_by_user_id INT DEFAULT NULL COMMENT 'The FM user_id who approved/returned',
    reviewed_at DATETIME DEFAULT NULL COMMENT 'Timestamp of FM review',
    return_note TEXT DEFAULT NULL COMMENT 'FM justification if disbursement is returned',
    returned_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Amount returned to organization safe',
    return_receipt_file_path VARCHAR(255) DEFAULT NULL COMMENT 'Receipt proving return of outstanding amount',
    returned_by_user_id INT UNSIGNED DEFAULT NULL COMMENT 'User who returned/closed the outstanding amount',
    returned_at DATETIME DEFAULT NULL COMMENT 'Return/closure timestamp',
    return_receipt_by_user_id INT UNSIGNED DEFAULT NULL COMMENT 'User who uploaded return receipt',
    return_receipt_at DATETIME DEFAULT NULL COMMENT 'Return receipt upload timestamp',
    created_by INT UNSIGNED DEFAULT NULL,
    submitted_by INT UNSIGNED DEFAULT NULL,
    submitted_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    transferred_at DATETIME DEFAULT NULL,
    received_at DATETIME DEFAULT NULL,
    receipt_file_path VARCHAR(255) DEFAULT NULL,
    voided_by_user_id INT UNSIGNED DEFAULT NULL,
    voided_at DATETIME DEFAULT NULL,
    void_reason TEXT DEFAULT NULL,
    reversal_journal_id INT UNSIGNED DEFAULT NULL COMMENT 'Links to the reversing journal entry',
    transfer_receipt_by_user_id INT UNSIGNED DEFAULT NULL,
    transfer_receipt_at DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    KEY nanny_id (nanny_id),
    KEY idx_md_status_created (status, created_at),
    KEY idx_group_id (group_id),
    CONSTRAINT fk_disbursements_group FOREIGN KEY (group_id) REFERENCES orphan_groups (id) ON DELETE SET NULL,
    CONSTRAINT monthly_disbursements_ibfk_1 FOREIGN KEY (nanny_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS disbursement_items (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    disbursement_id INT UNSIGNED NOT NULL,
    child_id INT UNSIGNED DEFAULT NULL,
    description VARCHAR(255) DEFAULT NULL,
    family_id INT UNSIGNED NOT NULL,
    amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    status ENUM('pending','paid','return_requested','returned') NOT NULL DEFAULT 'pending',
    confirmed_at DATETIME DEFAULT NULL,
    is_orphan_verified TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Nanny confirms orphan still eligible/alive',
    is_mother_contact_verified TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Nanny confirms mother phone is active',
    is_bank_verified TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Nanny confirms bank account/payment method is valid',
    verification_notes TEXT DEFAULT NULL,
    verified_by_user_id INT UNSIGNED DEFAULT NULL,
    verified_at DATETIME DEFAULT NULL,
    receipt_file_path VARCHAR(255) DEFAULT NULL,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    return_reason TEXT DEFAULT NULL,
    return_requested_at DATETIME DEFAULT NULL,
    return_requested_by_user_id INT UNSIGNED DEFAULT NULL,
    returned_at DATETIME DEFAULT NULL,
    returned_by_user_id INT UNSIGNED DEFAULT NULL,
    reversal_journal_id INT UNSIGNED DEFAULT NULL,
    PRIMARY KEY (id),
    KEY disbursement_id (disbursement_id),
    KEY family_id (family_id),
    CONSTRAINT disbursement_items_ibfk_1 FOREIGN KEY (disbursement_id) REFERENCES monthly_disbursements (id) ON DELETE CASCADE,
    CONSTRAINT disbursement_items_ibfk_2 FOREIGN KEY (family_id) REFERENCES families (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS nanny_family_verifications (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    family_id INT UNSIGNED NOT NULL,
    month VARCHAR(7) NOT NULL,
    nanny_id INT UNSIGNED NOT NULL,
    is_orphan_verified TINYINT(1) NOT NULL DEFAULT 0,
    is_mother_contact_verified TINYINT(1) NOT NULL DEFAULT 0,
    is_bank_verified TINYINT(1) NOT NULL DEFAULT 0,
    verification_notes TEXT DEFAULT NULL,
    verified_by_user_id INT UNSIGNED DEFAULT NULL,
    verified_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_nfv_family_month (family_id, month),
    KEY idx_nfv_nanny_month (nanny_id, month),
    CONSTRAINT fk_nfv_family FOREIGN KEY (family_id) REFERENCES families (id) ON DELETE CASCADE,
    CONSTRAINT fk_nfv_nanny FOREIGN KEY (nanny_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Older runtime-created monthly_disbursements tables lacked the group/return fields.
ALTER TABLE monthly_disbursements
    ADD COLUMN IF NOT EXISTS group_id INT UNSIGNED DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS transaction_id INT DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS expense_account_code VARCHAR(20) NOT NULL DEFAULT '5100',
    ADD COLUMN IF NOT EXISTS reviewed_by_user_id INT DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS reviewed_at DATETIME DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS return_note TEXT DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS returned_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    ADD COLUMN IF NOT EXISTS return_receipt_file_path VARCHAR(255) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS returned_by_user_id INT UNSIGNED DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS returned_at DATETIME DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS return_receipt_by_user_id INT UNSIGNED DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS return_receipt_at DATETIME DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS created_by INT UNSIGNED DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS submitted_by INT UNSIGNED DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS submitted_at DATETIME DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ADD COLUMN IF NOT EXISTS transferred_at DATETIME DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS received_at DATETIME DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS receipt_file_path VARCHAR(255) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS voided_by_user_id INT UNSIGNED DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS voided_at DATETIME DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS void_reason TEXT DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS reversal_journal_id INT UNSIGNED DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS transfer_receipt_by_user_id INT UNSIGNED DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS transfer_receipt_at DATETIME DEFAULT NULL;

ALTER TABLE disbursement_items
    ADD COLUMN IF NOT EXISTS updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- The old runtime enum allowed 'pending'; the application/API treat it as the same approval state
-- as 'pending_approval'. Normalize it before restoring the canonical schema enum.
UPDATE monthly_disbursements
SET status = 'pending_approval'
WHERE status = 'pending';

ALTER TABLE monthly_disbursements
    MODIFY COLUMN status ENUM('draft','pending_approval','approved','transferred','received','returned','cancelled','voided') NOT NULL DEFAULT 'draft';

ALTER TABLE disbursement_items
    MODIFY COLUMN status ENUM('pending','paid','return_requested','returned') NOT NULL DEFAULT 'pending';

-- Ensure the canonical status/group indexes and group foreign key on older runtime-created tables.
SET @ahl_has_md_status_index := (
    SELECT COUNT(DISTINCT index_name) FROM information_schema.statistics
    WHERE table_schema = DATABASE() AND table_name = 'monthly_disbursements' AND index_name = 'idx_md_status_created'
);
SET @ahl_md_status_index_sql := IF(
    @ahl_has_md_status_index = 0,
    'ALTER TABLE monthly_disbursements ADD KEY idx_md_status_created (status, created_at)',
    'SELECT 1'
);
PREPARE ahl_md_status_index_stmt FROM @ahl_md_status_index_sql;
EXECUTE ahl_md_status_index_stmt;
DEALLOCATE PREPARE ahl_md_status_index_stmt;

SET @ahl_has_md_group_index := (
    SELECT COUNT(DISTINCT index_name) FROM information_schema.statistics
    WHERE table_schema = DATABASE() AND table_name = 'monthly_disbursements' AND index_name = 'idx_group_id'
);
SET @ahl_md_group_index_sql := IF(
    @ahl_has_md_group_index = 0,
    'ALTER TABLE monthly_disbursements ADD KEY idx_group_id (group_id)',
    'SELECT 1'
);
PREPARE ahl_md_group_index_stmt FROM @ahl_md_group_index_sql;
EXECUTE ahl_md_group_index_stmt;
DEALLOCATE PREPARE ahl_md_group_index_stmt;

SET @ahl_has_md_group_fk := (
    SELECT COUNT(*)
    FROM information_schema.key_column_usage
    WHERE table_schema = DATABASE()
      AND table_name = 'monthly_disbursements'
      AND column_name = 'group_id'
      AND referenced_table_name = 'orphan_groups'
);
SET @ahl_md_group_fk_sql := IF(
    @ahl_has_md_group_fk = 0,
    'ALTER TABLE monthly_disbursements ADD CONSTRAINT fk_disbursements_group FOREIGN KEY (group_id) REFERENCES orphan_groups (id) ON DELETE SET NULL',
    'SELECT 1'
);
PREPARE ahl_md_group_fk_stmt FROM @ahl_md_group_fk_sql;
EXECUTE ahl_md_group_fk_stmt;
DEALLOCATE PREPARE ahl_md_group_fk_stmt;
