-- Migration 164: keep key inquiry questions separate from teacher notes.
CREATE TABLE IF NOT EXISTS scheme_workbook_item_questions (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    workbook_item_id INT UNSIGNED NOT NULL,
    question_text TEXT NOT NULL,
    is_custom TINYINT(1) NOT NULL DEFAULT 0,
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    KEY idx_swiq_item (workbook_item_id),
    CONSTRAINT fk_swiq_item FOREIGN KEY (workbook_item_id) REFERENCES scheme_workbook_items(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
