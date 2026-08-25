ALTER TABLE scheme_workbooks
    ADD COLUMN parent_workbook_id INT UNSIGNED NULL AFTER id,
    ADD COLUMN revision_number SMALLINT UNSIGNED NOT NULL DEFAULT 1 AFTER parent_workbook_id,
    ADD COLUMN revision_reason VARCHAR(500) NULL AFTER revision_number,
    ADD COLUMN revision_requested_by INT UNSIGNED NULL AFTER revision_reason,
    ADD COLUMN revision_requested_at DATETIME NULL AFTER revision_requested_by,
    ADD KEY idx_scheme_workbook_parent (parent_workbook_id),
    ADD CONSTRAINT fk_scheme_workbook_parent FOREIGN KEY (parent_workbook_id) REFERENCES scheme_workbooks(id) ON DELETE RESTRICT;

CREATE TABLE IF NOT EXISTS scheme_workbook_revision_audit (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    workbook_id INT UNSIGNED NOT NULL,
    parent_workbook_id INT UNSIGNED NULL,
    action ENUM('revision_requested','revision_reopened','revision_created','submitted','approved') NOT NULL,
    actor_staff_id INT UNSIGNED NOT NULL,
    reason VARCHAR(500) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_swra_workbook (workbook_id),
    CONSTRAINT fk_swra_workbook FOREIGN KEY (workbook_id) REFERENCES scheme_workbooks(id) ON DELETE CASCADE,
    CONSTRAINT fk_swra_parent FOREIGN KEY (parent_workbook_id) REFERENCES scheme_workbooks(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
