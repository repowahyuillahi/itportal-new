CREATE TABLE IF NOT EXISTS export_jobs (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    type            ENUM('monthly_excel','monthly_pdf') NOT NULL,
    filters_json    JSON NULL DEFAULT NULL,
    file_path       VARCHAR(500) NOT NULL,
    created_by      BIGINT UNSIGNED NOT NULL,
    created_at      DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_export_type (type),
    KEY idx_export_user (created_by),
    KEY idx_export_created (created_at),
    CONSTRAINT fk_export_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
