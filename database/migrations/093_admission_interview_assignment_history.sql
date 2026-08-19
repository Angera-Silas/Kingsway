CREATE TABLE IF NOT EXISTS admission_interview_assignment_history (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    admission_interview_id INT UNSIGNED NOT NULL,
    from_session_id INT UNSIGNED NULL,
    to_session_id INT UNSIGNED NOT NULL,
    action ENUM('assigned','switched','rescheduled') NOT NULL,
    reason VARCHAR(500) NULL,
    changed_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_interview_assignment_history_interview (admission_interview_id),
    CONSTRAINT fk_interview_assignment_history_interview FOREIGN KEY (admission_interview_id) REFERENCES admission_interviews (id),
    CONSTRAINT fk_interview_assignment_history_from_session FOREIGN KEY (from_session_id) REFERENCES admission_interview_sessions (id) ON DELETE SET NULL,
    CONSTRAINT fk_interview_assignment_history_to_session FOREIGN KEY (to_session_id) REFERENCES admission_interview_sessions (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
