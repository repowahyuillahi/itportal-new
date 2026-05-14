CREATE TABLE IF NOT EXISTS dealers (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    code        VARCHAR(50) NULL DEFAULT NULL,
    name        VARCHAR(190) NOT NULL,
    area        VARCHAR(120) NULL DEFAULT NULL,
    address     TEXT NULL DEFAULT NULL,
    pic_name    VARCHAR(150) NULL DEFAULT NULL,
    pic_phone   VARCHAR(50) NULL DEFAULT NULL,
    status      ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at  DATETIME NOT NULL,
    updated_at  DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_dealers_code (code),
    KEY idx_dealers_name (name),
    KEY idx_dealers_status (status),
    KEY idx_dealers_area (area)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
