CREATE TABLE IF NOT EXISTS migrations (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    filename    VARCHAR(255) NOT NULL,
    batch       INT NOT NULL,
    ran_at      DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_migrations_filename (filename)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
