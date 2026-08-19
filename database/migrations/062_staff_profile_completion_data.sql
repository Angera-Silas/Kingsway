CREATE TABLE IF NOT EXISTS person_addresses (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    person_id INT UNSIGNED NOT NULL,
    address_type ENUM('residential','postal','work','other') NOT NULL DEFAULT 'residential',
    address_line VARCHAR(255) NOT NULL,
    is_primary TINYINT(1) NOT NULL DEFAULT 1,
    valid_from DATE NOT NULL,
    valid_to DATE NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_person_address_type_start (person_id, address_type, valid_from),
    KEY idx_person_address_current (person_id, address_type, valid_to),
    CONSTRAINT fk_person_address_person FOREIGN KEY (person_id) REFERENCES persons(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS person_marital_statuses (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    person_id INT UNSIGNED NOT NULL,
    marital_status ENUM('single','married','divorced','widowed','separated','unknown') NOT NULL,
    valid_from DATE NOT NULL,
    valid_to DATE NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_person_marital_start (person_id, valid_from),
    KEY idx_person_marital_current (person_id, valid_to),
    CONSTRAINT fk_person_marital_person FOREIGN KEY (person_id) REFERENCES persons(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS person_contact_points (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    person_id INT UNSIGNED NOT NULL,
    channel ENUM('email','phone') NOT NULL,
    purpose ENUM('communication','emergency','billing','other') NOT NULL DEFAULT 'communication',
    contact_value VARCHAR(150) NOT NULL,
    is_primary TINYINT(1) NOT NULL DEFAULT 0,
    verified_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_person_contact (person_id, channel, purpose),
    CONSTRAINT fk_person_contact_person FOREIGN KEY (person_id) REFERENCES persons(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
