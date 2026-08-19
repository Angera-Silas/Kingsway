CREATE TABLE IF NOT EXISTS parent_pta_memberships (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    parent_id INT UNSIGNED NOT NULL,
    role VARCHAR(100) NOT NULL,
    membership_status ENUM('active','inactive','pending') NOT NULL DEFAULT 'active',
    appointed_at DATE NULL,
    ended_at DATE NULL,
    notes TEXT NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_parent_pta_active_role (parent_id,role,membership_status),
    KEY idx_parent_pta_status (parent_id,membership_status),
    CONSTRAINT fk_parent_pta_parent FOREIGN KEY (parent_id) REFERENCES parents(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
