-- Short-lived, single-use handoff tokens for opening existing website pages from the mobile app.
-- Apply explicitly during deployment; application requests never run DDL.
CREATE TABLE IF NOT EXISTS mobile_web_handoffs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    mobile_session_id BIGINT UNSIGNED NOT NULL,
    user_id INT(10) UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL,
    target_path VARCHAR(255) NOT NULL,
    target_query_json TEXT DEFAULT NULL,
    expires_at DATETIME NOT NULL,
    consumed_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent VARCHAR(255) DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_mobile_web_handoffs_token_hash (token_hash),
    KEY idx_mobile_web_handoffs_session (mobile_session_id),
    KEY idx_mobile_web_handoffs_user (user_id),
    KEY idx_mobile_web_handoffs_expiry (expires_at),
    CONSTRAINT fk_mobile_web_handoffs_mobile_session
        FOREIGN KEY (mobile_session_id) REFERENCES mobile_api_sessions (id) ON DELETE CASCADE,
    CONSTRAINT fk_mobile_web_handoffs_user
        FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
