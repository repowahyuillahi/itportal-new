CREATE TABLE IF NOT EXISTS audit_logs (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    actor_user_id   BIGINT UNSIGNED NULL DEFAULT NULL,
    action          VARCHAR(100) NOT NULL,
    resource_type   VARCHAR(100) NULL DEFAULT NULL,
    resource_id     VARCHAR(100) NULL DEFAULT NULL,
    before_json     JSON NULL DEFAULT NULL,
    after_json      JSON NULL DEFAULT NULL,
    ip_hash         VARCHAR(128) NULL DEFAULT NULL,
    user_agent_hash VARCHAR(128) NULL DEFAULT NULL,
    created_at      DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_audit_actor (actor_user_id),
    KEY idx_audit_action (action),
    KEY idx_audit_resource (resource_type, resource_id),
    KEY idx_audit_created (created_at),
    CONSTRAINT fk_audit_user FOREIGN KEY (actor_user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
