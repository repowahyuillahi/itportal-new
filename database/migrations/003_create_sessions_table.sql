CREATE TABLE IF NOT EXISTS sessions (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id         BIGINT UNSIGNED NOT NULL,
    session_hash    VARCHAR(128) NOT NULL,
    expires_at      DATETIME NOT NULL,
    revoked_at      DATETIME NULL DEFAULT NULL,
    created_at      DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_sessions_hash (session_hash),
    KEY idx_sessions_user (user_id),
    KEY idx_sessions_expires (expires_at),
    CONSTRAINT fk_sessions_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
