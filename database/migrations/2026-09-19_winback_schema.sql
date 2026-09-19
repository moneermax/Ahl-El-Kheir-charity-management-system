-- AHL EL KHEIR
-- Winback workflow schema. Apply once before using modules/administration/winback.php.
-- Normal web requests must never create/drop/alter this schema.

CREATE TABLE IF NOT EXISTS winback_campaigns (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sponsor_id INT UNSIGNED NOT NULL,
    handled_by INT UNSIGNED NULL,
    status ENUM('open','contacted','persuaded','declined') NOT NULL DEFAULT 'open',
    opened_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_contact_at DATETIME NULL,
    closed_at DATETIME NULL,
    notes TEXT,
    FOREIGN KEY (sponsor_id) REFERENCES sponsors(id) ON DELETE CASCADE,
    FOREIGN KEY (handled_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS winback_contacts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    campaign_id INT UNSIGNED NOT NULL,
    contacted_by INT UNSIGNED NULL,
    contact_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    method VARCHAR(30) NOT NULL DEFAULT 'phone',
    outcome VARCHAR(50) NOT NULL DEFAULT 'no_answer',
    notes TEXT,
    FOREIGN KEY (campaign_id) REFERENCES winback_campaigns(id) ON DELETE CASCADE,
    FOREIGN KEY (contacted_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
