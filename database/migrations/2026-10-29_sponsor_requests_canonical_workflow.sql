-- AHL EL KHEIR
-- Reconcile public sponsor applications with the canonical sponsor-request queue.
-- Safe to re-run on MariaDB 10.4; run as a migration, never from a web request.

CREATE TABLE IF NOT EXISTS sponsor_requests (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    sponsor_name VARCHAR(255) NOT NULL,
    phone VARCHAR(30) DEFAULT NULL,
    email VARCHAR(190) DEFAULT NULL,
    address VARCHAR(255) DEFAULT NULL,
    sponsor_type ENUM('individual','company','organization') NOT NULL DEFAULT 'individual',
    preferred_payment_method ENUM('cash','bank_transfer','credit_card','mobile','other') NOT NULL DEFAULT 'cash',
    proposed_amount DECIMAL(12,2) DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    first_letter_raw VARCHAR(10) DEFAULT NULL,
    first_letter_id TINYINT UNSIGNED DEFAULT NULL,
    assigned_supervisor_id INT UNSIGNED DEFAULT NULL,
    status ENUM('new','contacted','converted','lost') NOT NULL DEFAULT 'new',
    contact_notes TEXT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    confirmed_at DATETIME DEFAULT NULL,
    confirmed_by INT UNSIGNED DEFAULT NULL,
    created_sponsor_id INT UNSIGNED DEFAULT NULL,
    phone_purpose VARCHAR(20) NOT NULL DEFAULT 'both',
    alt_phone VARCHAR(30) DEFAULT NULL,
    alt_phone_purpose VARCHAR(20) NOT NULL DEFAULT 'both',
    desired_orphans SMALLINT UNSIGNED DEFAULT NULL,
    gender VARCHAR(20) DEFAULT NULL,
    source VARCHAR(100) DEFAULT NULL,
    contacted_at DATETIME DEFAULT NULL,
    brought_by VARCHAR(190) DEFAULT NULL,
    brought_by_name VARCHAR(255) DEFAULT NULL,
    INDEX idx_sr_status (status),
    INDEX idx_sr_supervisor (assigned_supervisor_id),
    INDEX idx_sr_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- The first public-form implementation used full_name. Reconcile it without
-- creating a stored procedure/function, even temporarily.
SET @ak_has_old_name := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'sponsor_requests'
      AND COLUMN_NAME = 'full_name'
);
SET @ak_has_new_name := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'sponsor_requests'
      AND COLUMN_NAME = 'sponsor_name'
);
SET @ak_reconcile_name_sql := CASE
    WHEN @ak_has_old_name = 1 AND @ak_has_new_name = 0
        THEN 'ALTER TABLE sponsor_requests CHANGE COLUMN full_name sponsor_name VARCHAR(255) NOT NULL'
    WHEN @ak_has_old_name = 1 AND @ak_has_new_name = 1
        THEN 'UPDATE sponsor_requests SET sponsor_name = full_name WHERE (sponsor_name IS NULL OR LENGTH(sponsor_name) = 0) AND full_name IS NOT NULL AND LENGTH(full_name) > 0'
    WHEN @ak_has_new_name = 0
        THEN CONCAT('ALTER TABLE sponsor_requests ADD COLUMN sponsor_name VARCHAR(255) NOT NULL DEFAULT ', CHAR(39), CHAR(39))
    ELSE 'SELECT 1'
END;
PREPARE ak_reconcile_name_stmt FROM @ak_reconcile_name_sql;
EXECUTE ak_reconcile_name_stmt;
DEALLOCATE PREPARE ak_reconcile_name_stmt;

-- Restore fields used by the shared staff/API queue and public form on older
-- tables created by the previous runtime DDL.
ALTER TABLE sponsor_requests ADD COLUMN IF NOT EXISTS email VARCHAR(190) DEFAULT NULL;
ALTER TABLE sponsor_requests ADD COLUMN IF NOT EXISTS address VARCHAR(255) DEFAULT NULL;
ALTER TABLE sponsor_requests ADD COLUMN IF NOT EXISTS sponsor_type ENUM('individual','company','organization') NOT NULL DEFAULT 'individual';
ALTER TABLE sponsor_requests ADD COLUMN IF NOT EXISTS preferred_payment_method ENUM('cash','bank_transfer','credit_card','mobile','other') NOT NULL DEFAULT 'cash';
ALTER TABLE sponsor_requests ADD COLUMN IF NOT EXISTS proposed_amount DECIMAL(12,2) DEFAULT NULL;
ALTER TABLE sponsor_requests ADD COLUMN IF NOT EXISTS first_letter_raw VARCHAR(10) DEFAULT NULL;
ALTER TABLE sponsor_requests ADD COLUMN IF NOT EXISTS first_letter_id TINYINT UNSIGNED DEFAULT NULL;
ALTER TABLE sponsor_requests ADD COLUMN IF NOT EXISTS assigned_supervisor_id INT UNSIGNED DEFAULT NULL;
ALTER TABLE sponsor_requests ADD COLUMN IF NOT EXISTS contact_notes TEXT DEFAULT NULL;
ALTER TABLE sponsor_requests ADD COLUMN IF NOT EXISTS confirmed_at DATETIME DEFAULT NULL;
ALTER TABLE sponsor_requests ADD COLUMN IF NOT EXISTS confirmed_by INT UNSIGNED DEFAULT NULL;
ALTER TABLE sponsor_requests ADD COLUMN IF NOT EXISTS created_sponsor_id INT UNSIGNED DEFAULT NULL;
ALTER TABLE sponsor_requests ADD COLUMN IF NOT EXISTS phone_purpose VARCHAR(20) NOT NULL DEFAULT 'both';
ALTER TABLE sponsor_requests ADD COLUMN IF NOT EXISTS alt_phone VARCHAR(30) DEFAULT NULL;
ALTER TABLE sponsor_requests ADD COLUMN IF NOT EXISTS alt_phone_purpose VARCHAR(20) NOT NULL DEFAULT 'both';
ALTER TABLE sponsor_requests ADD COLUMN IF NOT EXISTS desired_orphans SMALLINT UNSIGNED DEFAULT NULL;
ALTER TABLE sponsor_requests ADD COLUMN IF NOT EXISTS gender VARCHAR(20) DEFAULT NULL;
ALTER TABLE sponsor_requests MODIFY COLUMN gender VARCHAR(20) DEFAULT NULL;
ALTER TABLE sponsor_requests ADD COLUMN IF NOT EXISTS source VARCHAR(100) DEFAULT NULL;
ALTER TABLE sponsor_requests ADD COLUMN IF NOT EXISTS contacted_at DATETIME DEFAULT NULL;
ALTER TABLE sponsor_requests ADD COLUMN IF NOT EXISTS brought_by VARCHAR(190) DEFAULT NULL;
ALTER TABLE sponsor_requests ADD COLUMN IF NOT EXISTS brought_by_name VARCHAR(255) DEFAULT NULL;

-- Widen before mapping legacy ENUM values, then restore the canonical queue states.
ALTER TABLE sponsor_requests
    MODIFY COLUMN status ENUM('pending','new','contacted','confirmed','converted','rejected','lost') NOT NULL DEFAULT 'new';
UPDATE sponsor_requests
   SET status = CASE status
       WHEN 'pending' THEN 'new'
       WHEN 'confirmed' THEN 'converted'
       WHEN 'rejected' THEN 'lost'
       ELSE status
   END
 WHERE status IN ('pending','confirmed','rejected');
ALTER TABLE sponsor_requests
    MODIFY COLUMN status ENUM('new','contacted','converted','lost') NOT NULL DEFAULT 'new';
\n