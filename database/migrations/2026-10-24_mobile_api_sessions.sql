-- Mobile API bearer-session storage.
-- Apply explicitly during deployment; never run DDL from application requests.
-- Existing browser user_sessions are intentionally unchanged.
CREATE TABLE IF NOT EXISTS mobile_api_sessions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT(10) UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    revoked_at DATETIME DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent VARCHAR(255) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_used_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_mobile_api_sessions_token_hash (token_hash),
    KEY idx_mobile_api_sessions_user_id (user_id),
    KEY idx_mobile_api_sessions_expiry (expires_at),
    KEY idx_mobile_api_sessions_user_revoked (user_id, revoked_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
