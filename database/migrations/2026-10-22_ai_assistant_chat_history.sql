-- Persistent, user-owned conversation history for the shared AI assistant.
-- Apply explicitly to each target database; application requests never create/alter tables.
-- users.id was verified locally as INT UNSIGNED.

CREATE TABLE IF NOT EXISTS ai_assistant_conversations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT(10) UNSIGNED NOT NULL,
    title VARCHAR(180) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_message_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_ai_assistant_conversations_user_recent (user_id, last_message_at, id),
    CONSTRAINT fk_ai_assistant_conversations_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ai_assistant_messages (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    conversation_id BIGINT UNSIGNED NOT NULL,
    speaker ENUM('user', 'assistant') NOT NULL,
    content MEDIUMTEXT NOT NULL,
    answer_mode VARCHAR(40) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_ai_assistant_messages_conversation (conversation_id, id),
    CONSTRAINT fk_ai_assistant_messages_conversation
        FOREIGN KEY (conversation_id) REFERENCES ai_assistant_conversations (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
