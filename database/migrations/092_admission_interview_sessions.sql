-- Group interview sessions belong to an admission intake window. Applicants
-- retain individual result rows in admission_interviews and reference a session.
CREATE TABLE IF NOT EXISTS admission_interview_sessions (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    admission_window_id INT UNSIGNED NOT NULL,
    session_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    venue VARCHAR(255) NOT NULL DEFAULT 'Main Office',
    interviewer_id INT UNSIGNED NULL,
    capacity INT UNSIGNED NOT NULL DEFAULT 20,
    status ENUM('scheduled','full','completed','cancelled') NOT NULL DEFAULT 'scheduled',
    calendar_event_id INT UNSIGNED NULL,
    notes TEXT NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_interview_sessions_window (admission_window_id),
    KEY idx_interview_sessions_date (session_date),
    KEY idx_interview_sessions_status (status),
    CONSTRAINT fk_interview_session_window FOREIGN KEY (admission_window_id) REFERENCES admission_windows (id),
    CONSTRAINT fk_interview_session_event FOREIGN KEY (calendar_event_id) REFERENCES school_events (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @has_session_id := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'admission_interviews' AND column_name = 'session_id');
SET @sql := IF(@has_session_id = 0, 'ALTER TABLE admission_interviews ADD COLUMN session_id INT UNSIGNED NULL AFTER application_id, ADD KEY idx_admission_interviews_session (session_id)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @has_session_fk := (SELECT COUNT(*) FROM information_schema.table_constraints WHERE constraint_schema = DATABASE() AND table_name = 'admission_interviews' AND constraint_name = 'fk_admission_interview_session');
SET @sql := IF(@has_session_fk = 0, 'ALTER TABLE admission_interviews ADD CONSTRAINT fk_admission_interview_session FOREIGN KEY (session_id) REFERENCES admission_interview_sessions (id)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
