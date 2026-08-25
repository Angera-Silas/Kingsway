-- Preserve legacy planning records while making their missing canonical
-- context explicit for controlled staff reconciliation.
CREATE TABLE IF NOT EXISTS academic_content_reconciliation_queue (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    content_type ENUM('scheme_of_work','lesson_plan') NOT NULL,
    content_id INT UNSIGNED NOT NULL,
    reason VARCHAR(255) NOT NULL,
    status ENUM('open','resolved','ignored') NOT NULL DEFAULT 'open',
    resolved_by INT UNSIGNED NULL,
    resolved_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_academic_content_reconciliation (content_type, content_id),
    KEY idx_academic_content_reconciliation_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO academic_content_reconciliation_queue (content_type, content_id, reason)
SELECT 'scheme_of_work', id, 'Missing canonical academic-year stream-learning-area context'
FROM schemes_of_work
WHERE academic_year_class_stream_learning_area_id IS NULL;

INSERT IGNORE INTO academic_content_reconciliation_queue (content_type, content_id, reason)
SELECT 'lesson_plan', id, 'Missing scheme-of-work and/or canonical stream-learning-area context'
FROM lesson_plans
WHERE scheme_of_work_id IS NULL
   OR academic_year_class_stream_learning_area_id IS NULL;
