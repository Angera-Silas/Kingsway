-- Migration 173: one canonical learning-area identity with band-specific
-- implementations retained for curriculum accuracy.

CREATE TABLE IF NOT EXISTS learning_area_families (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    code VARCHAR(30) NOT NULL,
    description VARCHAR(500) DEFAULT NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_learning_area_family_name (name),
    UNIQUE KEY uq_learning_area_family_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO learning_area_families (name, code, description)
SELECT MIN(la.name), CONCAT('LAF-', LPAD(ROW_NUMBER() OVER (ORDER BY MIN(la.name)), 3, '0')),
       'Canonical learning-area identity; band-specific curriculum remains in learning_areas.'
FROM learning_areas la
LEFT JOIN learning_area_families laf ON laf.name COLLATE utf8mb4_unicode_ci = la.name COLLATE utf8mb4_unicode_ci
WHERE la.status = 'active' AND laf.id IS NULL
GROUP BY LOWER(la.name) COLLATE utf8mb4_unicode_ci;

ALTER TABLE learning_areas
    ADD COLUMN learning_area_family_id INT UNSIGNED NULL AFTER id,
    ADD KEY idx_learning_area_family (learning_area_family_id),
    ADD CONSTRAINT fk_learning_area_family FOREIGN KEY (learning_area_family_id) REFERENCES learning_area_families(id) ON DELETE RESTRICT;

UPDATE learning_areas la
JOIN learning_area_families laf ON laf.name COLLATE utf8mb4_unicode_ci = la.name COLLATE utf8mb4_unicode_ci
SET la.learning_area_family_id = laf.id
WHERE la.learning_area_family_id IS NULL;
