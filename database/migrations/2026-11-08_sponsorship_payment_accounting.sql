-- AHL EL KHEIR
-- Sponsorship payment allocation, append-only accounting events, and pause history.
-- Apply through the project's migration process. No request-time DDL.

CREATE TABLE IF NOT EXISTS sponsorship_payment_allocations (
    id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    transaction_id INT(10) UNSIGNED NOT NULL,
    sponsorship_id INT(10) UNSIGNED DEFAULT NULL,
    collector_user_id INT(10) UNSIGNED DEFAULT NULL,
    payment_period VARCHAR(20) DEFAULT NULL,
    covered_month DATE DEFAULT NULL COMMENT 'First day of the covered calendar month',
    commitment_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    net_received DECIMAL(12,2) NOT NULL,
    allocated_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    unallocated_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    allocation_status ENUM('allocated','partial','unallocated') NOT NULL,
    source_type ENUM('live','historical_backfill') NOT NULL DEFAULT 'live',
    created_by INT(10) UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_spa_transaction (transaction_id),
    KEY idx_spa_sponsorship_month (sponsorship_id, covered_month),
    KEY idx_spa_month_status (covered_month, allocation_status),
    CONSTRAINT fk_spa_transaction FOREIGN KEY (transaction_id)
        REFERENCES transactions (id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_spa_sponsorship FOREIGN KEY (sponsorship_id)
        REFERENCES sponsorships (id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_spa_collector FOREIGN KEY (collector_user_id)
        REFERENCES users (id) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_spa_creator FOREIGN KEY (created_by)
        REFERENCES users (id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sponsorship_accounting_events (
    id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    event_key VARCHAR(120) NOT NULL,
    event_type ENUM('historical_reclassification','month_end_recognition','transaction_void_reversal') NOT NULL,
    transaction_id INT(10) UNSIGNED NOT NULL,
    allocation_id INT(10) UNSIGNED DEFAULT NULL,
    journal_entry_id INT(10) UNSIGNED NOT NULL,
    event_date DATE NOT NULL,
    amount DECIMAL(14,2) NOT NULL,
    created_by INT(10) UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sae_event_key (event_key),
    UNIQUE KEY uq_sae_journal (journal_entry_id),
    KEY idx_sae_transaction_type (transaction_id, event_type),
    KEY idx_sae_allocation_type (allocation_id, event_type),
    CONSTRAINT fk_sae_transaction FOREIGN KEY (transaction_id)
        REFERENCES transactions (id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_sae_allocation FOREIGN KEY (allocation_id)
        REFERENCES sponsorship_payment_allocations (id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_sae_journal FOREIGN KEY (journal_entry_id)
        REFERENCES journal_entries (id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_sae_creator FOREIGN KEY (created_by)
        REFERENCES users (id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sponsorship_pause_events (
    id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    sponsorship_id INT(10) UNSIGNED NOT NULL,
    event_type ENUM('pause','resume') NOT NULL,
    effective_date DATE NOT NULL,
    reason TEXT DEFAULT NULL,
    actor_user_id INT(10) UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_spe_sponsorship_date (sponsorship_id, effective_date, id),
    CONSTRAINT fk_spe_sponsorship FOREIGN KEY (sponsorship_id)
        REFERENCES sponsorships (id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_spe_actor FOREIGN KEY (actor_user_id)
        REFERENCES users (id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
