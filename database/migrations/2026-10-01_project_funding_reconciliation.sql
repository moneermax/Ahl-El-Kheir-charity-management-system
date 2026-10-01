-- Projects Phase 5: controlled-fund return reconciliation
-- FM financial approval releases the approved funding from organization accounts.
-- Saved/unused controlled funds are later returned through this auditable table.
-- No triggers, views, stored procedures, or runtime DDL.

CREATE TABLE IF NOT EXISTS project_funding_returns (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    project_id INT UNSIGNED NOT NULL,
    funding_allocation_id INT UNSIGNED NOT NULL,
    source_account_id INT UNSIGNED NOT NULL,
    journal_entry_id INT UNSIGNED NOT NULL,
    amount DECIMAL(14,2) NOT NULL,
    currency_code VARCHAR(10) NOT NULL DEFAULT 'SDG',
    return_date DATE NOT NULL,
    reference_number VARCHAR(100) DEFAULT NULL,
    description VARCHAR(500) DEFAULT NULL,
    returned_by INT UNSIGNED NOT NULL,
    returned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_project_funding_return_allocation (funding_allocation_id),
    KEY idx_project_funding_return_project (project_id),
    KEY idx_project_funding_return_source (source_account_id),
    KEY idx_project_funding_return_journal (journal_entry_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
