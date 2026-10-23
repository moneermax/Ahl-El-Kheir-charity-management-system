-- Document archive catalogue foundation.
-- Schema-only migration: this file is not executed at runtime.
-- Initial rollout registers existing paths in place; it does not move or delete files.
-- Source records remain authoritative and domain endpoints remain responsible for access checks.
-- No source records are backfilled by this migration.
--
-- Rollback note: before dropping these tables, confirm they contain no production data
-- and remove dependent rows in reverse FK order. Do not run destructive rollback on a
-- populated archive; preserve audit/version records.

CREATE TABLE IF NOT EXISTS document_archive_blobs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    storage_key VARCHAR(500) NOT NULL,
    sha256 CHAR(64) DEFAULT NULL,
    byte_size BIGINT UNSIGNED DEFAULT NULL,
    detected_mime_type VARCHAR(127) DEFAULT NULL,
    storage_status ENUM('present','missing','quarantined') NOT NULL DEFAULT 'present',
    first_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_verified_at DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_document_archive_blob_storage_key (storage_key),
    KEY idx_document_archive_blob_sha256 (sha256),
    KEY idx_document_archive_blob_status (storage_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS document_archive_documents (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    document_kind VARCHAR(80) NOT NULL,
    display_title VARCHAR(255) DEFAULT NULL,
    created_by_user_id INT UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_document_archive_documents_kind (document_kind),
    KEY idx_document_archive_documents_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS document_archive_versions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    document_id BIGINT UNSIGNED NOT NULL,
    blob_id BIGINT UNSIGNED NOT NULL,
    version_no INT UNSIGNED NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    mime_type_snapshot VARCHAR(127) DEFAULT NULL,
    byte_size_snapshot BIGINT UNSIGNED DEFAULT NULL,
    sha256_snapshot CHAR(64) DEFAULT NULL,
    uploaded_by_user_id INT UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_document_archive_version_number (document_id, version_no),
    UNIQUE KEY uq_document_archive_version_document_id (document_id, id),
    KEY idx_document_archive_version_blob (blob_id),
    KEY idx_document_archive_version_created_at (created_at),
    CONSTRAINT fk_document_archive_version_document
        FOREIGN KEY (document_id) REFERENCES document_archive_documents (id)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_document_archive_version_blob
        FOREIGN KEY (blob_id) REFERENCES document_archive_blobs (id)
        ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS document_archive_source_links (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    document_id BIGINT UNSIGNED NOT NULL,
    current_version_id BIGINT UNSIGNED NOT NULL,
    source_table VARCHAR(80) NOT NULL,
    source_record_id BIGINT UNSIGNED NOT NULL,
    source_path_column VARCHAR(80) NOT NULL,
    source_path_snapshot VARCHAR(500) NOT NULL,
    link_status ENUM('active','unlinked') NOT NULL DEFAULT 'active',
    linked_by_user_id INT UNSIGNED DEFAULT NULL,
    linked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_document_archive_source_locator (source_table, source_record_id, source_path_column),
    KEY idx_document_archive_source_document (document_id),
    KEY idx_document_archive_source_version (current_version_id),
    KEY idx_document_archive_source_status (link_status),
    CONSTRAINT fk_document_archive_source_document
        FOREIGN KEY (document_id) REFERENCES document_archive_documents (id)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_document_archive_source_version
        FOREIGN KEY (document_id, current_version_id)
        REFERENCES document_archive_versions (document_id, id)
        ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS document_archive_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    event_type VARCHAR(64) NOT NULL,
    document_id BIGINT UNSIGNED DEFAULT NULL,
    version_id BIGINT UNSIGNED DEFAULT NULL,
    blob_id BIGINT UNSIGNED DEFAULT NULL,
    source_table VARCHAR(80) DEFAULT NULL,
    source_record_id BIGINT UNSIGNED DEFAULT NULL,
    source_path_column VARCHAR(80) DEFAULT NULL,
    actor_user_id INT UNSIGNED DEFAULT NULL,
    event_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    metadata_json JSON DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_document_archive_event_document (document_id, event_at),
    KEY idx_document_archive_event_version (version_id, event_at),
    KEY idx_document_archive_event_blob (blob_id, event_at),
    KEY idx_document_archive_event_source (source_table, source_record_id, event_at),
    KEY idx_document_archive_event_actor (actor_user_id, event_at),
    CONSTRAINT fk_document_archive_event_document
        FOREIGN KEY (document_id) REFERENCES document_archive_documents (id)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_document_archive_event_version
        FOREIGN KEY (version_id) REFERENCES document_archive_versions (id)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_document_archive_event_blob
        FOREIGN KEY (blob_id) REFERENCES document_archive_blobs (id)
        ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
