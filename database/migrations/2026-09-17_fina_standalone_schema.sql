-- AHL EL KHEIR
-- Standalone Fina Al-Khair schema migration.
-- These tables must exist before normal Fina pages are rendered.
-- The application must not create or alter schema during a normal web request.

CREATE TABLE IF NOT EXISTS fina_sources (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    source_type ENUM('sponsor','person','organization','other') NOT NULL DEFAULT 'person',
    sponsor_id INT UNSIGNED NULL,
    source_name VARCHAR(255) NULL,
    source_phone VARCHAR(100) NULL,
    source_alt_phone VARCHAR(100) NULL,
    source_email VARCHAR(255) NULL,
    source_address TEXT NULL,
    source_id_number VARCHAR(150) NULL,
    source_reference VARCHAR(150) NULL,
    contact_person VARCHAR(255) NULL,
    source_details TEXT NULL,
    created_by INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_fina_sources_sponsor (sponsor_id),
    KEY idx_fina_sources_type (source_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fina_collections (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    fina_source_id INT UNSIGNED NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    currency_code VARCHAR(10) NOT NULL,
    payment_method VARCHAR(32) NOT NULL,
    collection_date DATE NOT NULL,
    receipt_path VARCHAR(500) NULL,
    purpose_note TEXT NULL,
    description TEXT NULL,
    status ENUM('pending','approved','returned') NOT NULL DEFAULT 'pending',
    created_by INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reviewed_by INT UNSIGNED NULL,
    reviewed_at DATETIME NULL,
    return_note TEXT NULL,
    accounting_journal_id BIGINT UNSIGNED NULL,
    PRIMARY KEY (id),
    KEY idx_fina_collections_source (fina_source_id),
    KEY idx_fina_collections_status (status),
    KEY idx_fina_collections_date (collection_date),
    KEY idx_fina_collections_journal (accounting_journal_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
