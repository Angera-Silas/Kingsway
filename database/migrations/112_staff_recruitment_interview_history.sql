-- Recruitment workflow facts. job_applications remains the current-state
-- application record; these tables hold the repeating interview events and
-- immutable status transitions (4NF, no interview columns on the application).

CREATE TABLE IF NOT EXISTS job_application_status_history (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    application_id INT NOT NULL,
    from_status VARCHAR(40) NULL,
    to_status VARCHAR(40) NOT NULL,
    changed_by INT UNSIGNED NULL,
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_jash_application (application_id, created_at),
    CONSTRAINT fk_jash_application FOREIGN KEY (application_id) REFERENCES job_applications(id),
    CONSTRAINT fk_jash_changed_by FOREIGN KEY (changed_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS job_application_interviews (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    application_id INT NOT NULL,
    scheduled_at DATETIME NOT NULL,
    mode ENUM('in_person','phone','video') NOT NULL DEFAULT 'in_person',
    location VARCHAR(255) NULL,
    interviewer_user_id INT UNSIGNED NULL,
    status ENUM('scheduled','completed','cancelled','no_show') NOT NULL DEFAULT 'scheduled',
    score DECIMAL(5,2) NULL,
    notes TEXT NULL,
    completed_by INT UNSIGNED NULL,
    completed_at DATETIME NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_jai_application (application_id, scheduled_at),
    CONSTRAINT fk_jai_application FOREIGN KEY (application_id) REFERENCES job_applications(id),
    CONSTRAINT fk_jai_interviewer FOREIGN KEY (interviewer_user_id) REFERENCES users(id),
    CONSTRAINT fk_jai_completed_by FOREIGN KEY (completed_by) REFERENCES users(id),
    CONSTRAINT fk_jai_created_by FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET @has_interview_status := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='job_applications' AND COLUMN_NAME='status'
);
SET @sql := IF(@has_interview_status > 0,
    "ALTER TABLE job_applications MODIFY status ENUM('received','shortlisted','interview_scheduled','interviewed','hired','rejected') NOT NULL DEFAULT 'received'",
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
