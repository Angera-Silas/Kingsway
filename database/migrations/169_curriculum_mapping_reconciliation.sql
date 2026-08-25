CREATE TABLE IF NOT EXISTS assessment_tool_scope_reconciliation_queue (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    assessment_tool_id INT UNSIGNED NOT NULL,
    reason VARCHAR(255) NOT NULL,
    status ENUM('open','resolved','ignored') NOT NULL DEFAULT 'open',
    resolved_by INT UNSIGNED DEFAULT NULL,
    resolved_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_tool_scope_queue (assessment_tool_id, status),
    CONSTRAINT fk_atsq_tool FOREIGN KEY (assessment_tool_id) REFERENCES assessment_tools(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS curriculum_competency_reconciliation_queue (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    source_document VARCHAR(255) NOT NULL,
    source_area_code VARCHAR(50) NOT NULL,
    strand_name VARCHAR(255) NOT NULL,
    sub_strand_name VARCHAR(255) NOT NULL,
    competency_codes VARCHAR(255) NOT NULL,
    status ENUM('open','resolved','ignored') NOT NULL DEFAULT 'open',
    resolved_by INT UNSIGNED DEFAULT NULL,
    resolved_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_curriculum_comp_queue (source_document,source_area_code,strand_name,sub_strand_name),
    KEY idx_ccrq_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
