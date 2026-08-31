-- Ahl El Kheir - Internal Messaging System
-- Run once against the application's active database.
-- This migration is intentionally independent from the existing notifications table.

CREATE TABLE IF NOT EXISTS messages (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    sender_id INT UNSIGNED NOT NULL,
    recipient_user_id INT UNSIGNED NULL,
    recipient_role VARCHAR(100) NULL,
    subject VARCHAR(255) NOT NULL,
    body TEXT NOT NULL,
    parent_id BIGINT UNSIGNED NULL,
    reference_type VARCHAR(100) NULL,
    reference_id BIGINT UNSIGNED NULL,
    is_urgent TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_messages_recipient_user (recipient_user_id, id),
    KEY idx_messages_recipient_role (recipient_role, id),
    KEY idx_messages_sender (sender_id, id),
    KEY idx_messages_parent (parent_id),
    CONSTRAINT fk_messages_sender FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_messages_recipient_user FOREIGN KEY (recipient_user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_messages_parent FOREIGN KEY (parent_id) REFERENCES messages(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS message_reads (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    message_id BIGINT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    read_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_message_read_user (message_id, user_id),
    KEY idx_message_reads_user (user_id, read_at),
    CONSTRAINT fk_message_reads_message FOREIGN KEY (message_id) REFERENCES messages(id) ON DELETE CASCADE,
    CONSTRAINT fk_message_reads_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Helpful indexes for the real-time unread query. These are safe to run only once.
-- If your database already has equivalent indexes, skip these two ALTER statements.
CREATE INDEX idx_users_role_active ON users(role_id, is_active, id);
