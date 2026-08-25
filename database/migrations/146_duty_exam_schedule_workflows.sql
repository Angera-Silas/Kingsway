CREATE TABLE IF NOT EXISTS duty_roster_drafts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    academic_year_id INT UNSIGNED NOT NULL,
    academic_year_term_id INT UNSIGNED NOT NULL,
    title VARCHAR(160) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    status ENUM('draft','submitted','changes_requested','approved','published','cancelled') NOT NULL DEFAULT 'draft',
    created_by INT UNSIGNED NOT NULL,
    submitted_at DATETIME NULL, approved_at DATETIME NULL, published_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY(id), KEY idx_duty_draft_term (academic_year_term_id,status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS duty_roster_draft_entries (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, draft_id BIGINT UNSIGNED NOT NULL,
    staff_id INT UNSIGNED NOT NULL, date DATE NOT NULL, duty_type_id INT UNSIGNED NOT NULL,
    shift ENUM('morning','afternoon','evening','night','full_day') NOT NULL DEFAULT 'full_day',
    start_time TIME NULL, end_time TIME NULL, location VARCHAR(255) NULL, notes TEXT NULL,
    swapped_with_id INT UNSIGNED NULL, PRIMARY KEY(id), KEY idx_duty_draft_entries(draft_id,date),
    CONSTRAINT fk_duty_draft_entries_draft FOREIGN KEY(draft_id) REFERENCES duty_roster_drafts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS exam_timetable_drafts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, academic_year_id INT UNSIGNED NOT NULL,
    academic_year_term_id INT UNSIGNED NOT NULL, title VARCHAR(160) NOT NULL,
    status ENUM('draft','submitted','changes_requested','approved','published','cancelled') NOT NULL DEFAULT 'draft',
    created_by INT UNSIGNED NOT NULL, submitted_at DATETIME NULL, approved_at DATETIME NULL, published_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY(id), KEY idx_exam_draft_term(academic_year_term_id,status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS exam_timetable_draft_entries (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, draft_id BIGINT UNSIGNED NOT NULL,
    academic_year_class_stream_id INT UNSIGNED NOT NULL, learning_area_id INT UNSIGNED NULL,
    exam_name VARCHAR(255) NULL, exam_type VARCHAR(50) NULL, exam_date DATE NOT NULL,
    start_time TIME NOT NULL, end_time TIME NOT NULL, duration_minutes INT UNSIGNED NULL,
    room_id INT UNSIGNED NULL, venue VARCHAR(100) NULL, invigilator_id INT UNSIGNED NULL,
    supervisor_id INT UNSIGNED NULL, notes TEXT NULL, PRIMARY KEY(id),
    KEY idx_exam_draft_entries(draft_id,exam_date),
    CONSTRAINT fk_exam_draft_entries_draft FOREIGN KEY(draft_id) REFERENCES exam_timetable_drafts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS schedule_draft_reviews (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, document_type ENUM('duty','exam','timetable') NOT NULL,
    draft_id BIGINT UNSIGNED NOT NULL, reviewer_id INT UNSIGNED NOT NULL,
    action ENUM('submitted','reviewed','changes_requested','approved','published') NOT NULL,
    comments TEXT NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY(id), KEY idx_schedule_draft_reviews(document_type,draft_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
