-- Ahl El Kheir - Internal Messaging Attachments
-- Run once against the active application database.
-- IMPORTANT: message_id MUST match messages.id exactly (INT UNSIGNED).

CREATE TABLE IF NOT EXISTS message_attachments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    message_id INT UNSIGNED NOT NULL,
    uploader_user_id INT UNSIGNED NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    stored_name VARCHAR(255) NOT NULL,
    mime_type VARCHAR(120) NOT NULL,
    size_bytes BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_message_attachments_message (message_id, id),
    KEY idx_message_attachments_uploader (uploader_user_id, id),
    CONSTRAINT fk_message_attachments_message FOREIGN KEY (message_id) REFERENCES messages(id) ON DELETE CASCADE,
    CONSTRAINT fk_message_attachments_uploader FOREIGN KEY (uploader_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
