-- Project payment evidence after final GM approval.
-- This table documents the actual project funding release without creating a second
-- accounting event. The GM approval journal remains the accounting release event;
-- this table stores the cash-voucher/receipt evidence attached to that release.
CREATE TABLE IF NOT EXISTS project_payment_evidence (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    project_id INT UNSIGNED NOT NULL,
    funding_allocation_id INT UNSIGNED NOT NULL,
    source_account_id INT UNSIGNED NOT NULL,
    journal_entry_id INT UNSIGNED NULL,
    payment_method ENUM('cash','bank_transfer','e_wallet') NOT NULL,
    amount DECIMAL(14,2) NOT NULL,
    currency_code VARCHAR(10) NOT NULL DEFAULT 'SDG',
    payment_date DATE NOT NULL,
    status ENUM('pending','documented') NOT NULL DEFAULT 'pending',
    reference_number VARCHAR(100) DEFAULT NULL,
    receipt_file_path VARCHAR(255) DEFAULT NULL,
    receipt_original_name VARCHAR(255) DEFAULT NULL,
    receipt_mime_type VARCHAR(100) DEFAULT NULL,
    voucher_confirmed_at DATETIME NULL,
    documented_by INT UNSIGNED NULL,
    documented_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_project_payment_funding (funding_allocation_id),
    KEY idx_project_payment_project (project_id),
    KEY idx_project_payment_status (status),
    KEY idx_project_payment_method (payment_method)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
