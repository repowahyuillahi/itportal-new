CREATE TABLE IF NOT EXISTS ticket_attachments (
    id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    ticket_id           BIGINT UNSIGNED NOT NULL,
    filename_original   VARCHAR(255) NOT NULL,
    storage_path        VARCHAR(500) NOT NULL,
    mime_type           VARCHAR(150) NOT NULL,
    size_bytes          BIGINT UNSIGNED NOT NULL,
    uploaded_by         BIGINT UNSIGNED NOT NULL,
    created_at          DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_attach_ticket (ticket_id),
    KEY idx_attach_user (uploaded_by),
    CONSTRAINT fk_attach_ticket FOREIGN KEY (ticket_id)   REFERENCES tickets (id) ON DELETE CASCADE,
    CONSTRAINT fk_attach_user   FOREIGN KEY (uploaded_by) REFERENCES users   (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
