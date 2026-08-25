-- Parent meeting visibility targets.
-- A meeting is visible to a class teacher when its class, learner, or parent
-- audience resolves to one of that teacher's active class-teacher streams.
CREATE TABLE IF NOT EXISTS parent_meeting_targets (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    meeting_id INT UNSIGNED NOT NULL,
    target_type ENUM('class', 'student', 'parent', 'staff') NOT NULL,
    target_id INT UNSIGNED NOT NULL,
    created_by INT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_parent_meeting_target (meeting_id, target_type, target_id),
    KEY idx_parent_meeting_target_lookup (target_type, target_id),
    CONSTRAINT fk_parent_meeting_target_event FOREIGN KEY (meeting_id) REFERENCES school_events(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
