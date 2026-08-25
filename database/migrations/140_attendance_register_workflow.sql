-- Attendance register lifecycle: expected registers, configurable deadlines,
-- reminders and escalation.  No absence row is created by this workflow.

ALTER TABLE attendance_sessions
    ADD COLUMN teacher_reminder_minutes_before SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    ADD COLUMN grace_minutes_after SMALLINT UNSIGNED NOT NULL DEFAULT 15,
    ADD COLUMN escalation_minutes_after SMALLINT UNSIGNED NOT NULL DEFAULT 30;

CREATE TABLE IF NOT EXISTS attendance_registers (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    academic_year_id INT UNSIGNED NOT NULL,
    academic_year_term_id INT UNSIGNED NOT NULL,
    academic_year_calendar_day_id BIGINT UNSIGNED DEFAULT NULL,
    stream_id INT UNSIGNED NOT NULL,
    session_id INT UNSIGNED NOT NULL,
    register_type ENUM('class','boarding','activity') NOT NULL DEFAULT 'class',
    register_date DATE NOT NULL,
    assigned_staff_id INT UNSIGNED DEFAULT NULL,
    expected_count INT UNSIGNED NOT NULL DEFAULT 0,
    marked_count INT UNSIGNED NOT NULL DEFAULT 0,
    status ENUM('scheduled','open','overdue','completed','closed','not_required') NOT NULL DEFAULT 'scheduled',
    opens_at DATETIME NOT NULL,
    due_at DATETIME NOT NULL,
    overdue_at DATETIME NOT NULL,
    reminder_sent_at DATETIME DEFAULT NULL,
    escalation_sent_at DATETIME DEFAULT NULL,
    completed_at DATETIME DEFAULT NULL,
    last_reconciled_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_attendance_register (register_date, stream_id, session_id, register_type),
    KEY idx_attendance_register_status (register_date, status, due_at),
    KEY idx_attendance_register_staff (assigned_staff_id, register_date, status),
    KEY idx_attendance_register_term (academic_year_id, academic_year_term_id, register_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS attendance_register_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    register_id BIGINT UNSIGNED NOT NULL,
    event_type ENUM('opened','reminder','overdue','escalated','completed','closed') NOT NULL,
    actor_user_id INT UNSIGNED DEFAULT NULL,
    details TEXT DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_attendance_register_event (register_id, event_type),
    KEY idx_attendance_register_events_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
