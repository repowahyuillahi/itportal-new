CREATE TABLE IF NOT EXISTS items (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name        VARCHAR(150) NOT NULL,
    slug        VARCHAR(150) NOT NULL,
    description TEXT NULL DEFAULT NULL,
    status      ENUM('active','inactive') NOT NULL DEFAULT 'active',
    sort_order  INT NOT NULL DEFAULT 0,
    created_at  DATETIME NOT NULL,
    updated_at  DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_items_slug (slug),
    KEY idx_items_status (status),
    KEY idx_items_sort (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
