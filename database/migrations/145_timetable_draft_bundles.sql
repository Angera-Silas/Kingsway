-- Resumable timetable drafting. Draft rows are not live until published.
CREATE TABLE IF NOT EXISTS timetable_drafts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    academic_year_id INT UNSIGNED NOT NULL,
    academic_year_term_id INT UNSIGNED NOT NULL,
    scope ENUM('lower_primary','upper_primary','whole_school') NOT NULL,
    title VARCHAR(160) NOT NULL,
    status ENUM('draft','submitted','changes_requested','approved','published','cancelled') NOT NULL DEFAULT 'draft',
    created_by INT UNSIGNED NOT NULL,
    submitted_at DATETIME NULL,
    approved_at DATETIME NULL,
    published_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_timetable_drafts_term (academic_year_term_id, scope, status),
    KEY idx_timetable_drafts_creator (created_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS timetable_draft_entries (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    draft_id BIGINT UNSIGNED NOT NULL,
    academic_year_class_stream_id INT UNSIGNED NOT NULL,
    day_of_week TINYINT UNSIGNED NOT NULL,
    time_slot_id INT UNSIGNED NOT NULL,
    learning_area_id INT UNSIGNED NULL,
    teacher_id INT UNSIGNED NULL,
    room_id INT UNSIGNED NULL,
    notes VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_timetable_draft_slot (draft_id, academic_year_class_stream_id, day_of_week, time_slot_id),
    KEY idx_timetable_draft_entries_draft (draft_id),
    CONSTRAINT fk_timetable_draft_entries_draft FOREIGN KEY (draft_id) REFERENCES timetable_drafts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS timetable_draft_reviews (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    draft_id BIGINT UNSIGNED NOT NULL,
    reviewer_id INT UNSIGNED NOT NULL,
    action ENUM('submitted','reviewed','changes_requested','approved','published') NOT NULL,
    comments TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_timetable_draft_reviews_draft (draft_id),
    CONSTRAINT fk_timetable_draft_reviews_draft FOREIGN KEY (draft_id) REFERENCES timetable_drafts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
