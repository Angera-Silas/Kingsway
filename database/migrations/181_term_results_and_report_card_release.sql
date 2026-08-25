-- Year-safe term score snapshots, configurable CBC aggregation/ranking, and
-- an auditable report-card approval/release ledger.

CREATE TABLE IF NOT EXISTS academic_result_policies (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    academic_year_id INT UNSIGNED NOT NULL,
    formative_weight DECIMAL(5,4) NOT NULL DEFAULT 0.4000,
    summative_weight DECIMAL(5,4) NOT NULL DEFAULT 0.6000,
    ranking_method ENUM('dense_rank','competition_rank','none') NOT NULL DEFAULT 'dense_rank',
    publish_rank_to_guardians TINYINT(1) NOT NULL DEFAULT 1,
    require_formative_for_release TINYINT(1) NOT NULL DEFAULT 1,
    require_summative_for_release TINYINT(1) NOT NULL DEFAULT 1,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_result_policy_year (academic_year_id),
    CONSTRAINT chk_result_policy_weights CHECK (ABS((formative_weight + summative_weight) - 1.0000) < 0.0001)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO academic_result_policies (academic_year_id)
SELECT id FROM academic_years
ON DUPLICATE KEY UPDATE academic_year_id = VALUES(academic_year_id);

ALTER TABLE term_subject_scores
    DROP INDEX uk_student_term_subject,
    ADD COLUMN academic_year_term_id INT UNSIGNED NULL AFTER term_id,
    ADD COLUMN academic_year_class_stream_id INT UNSIGNED NULL AFTER academic_year_term_id,
    ADD UNIQUE KEY uk_student_year_term_subject (student_id, academic_year_term_id, subject_id),
    ADD KEY idx_tss_year_term_stream (academic_year_term_id, academic_year_class_stream_id),
    ADD CONSTRAINT fk_tss_year_term FOREIGN KEY (academic_year_term_id) REFERENCES academic_year_terms(id),
    ADD CONSTRAINT fk_tss_class_stream FOREIGN KEY (academic_year_class_stream_id) REFERENCES academic_year_class_streams(id);

CREATE TABLE IF NOT EXISTS student_term_rankings (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    student_id INT UNSIGNED NOT NULL,
    academic_year_term_id INT UNSIGNED NOT NULL,
    academic_year_class_stream_id INT UNSIGNED NOT NULL,
    subject_count INT UNSIGNED NOT NULL DEFAULT 0,
    overall_percentage DECIMAL(5,2) NULL,
    overall_points DECIMAL(6,2) NULL,
    class_position INT UNSIGNED NULL,
    class_population INT UNSIGNED NOT NULL DEFAULT 0,
    cohort_position INT UNSIGNED NULL,
    cohort_population INT UNSIGNED NOT NULL DEFAULT 0,
    ranking_method ENUM('dense_rank','competition_rank','none') NOT NULL,
    calculated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_student_term_ranking (student_id, academic_year_term_id),
    KEY idx_term_stream_position (academic_year_term_id, academic_year_class_stream_id, class_position)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS report_card_releases (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    student_id INT UNSIGNED NOT NULL,
    student_academic_enrollment_id INT UNSIGNED NOT NULL,
    academic_year_term_id INT UNSIGNED NOT NULL,
    version_no INT UNSIGNED NOT NULL DEFAULT 1,
    status ENUM('draft','pending_approval','approved','released','superseded') NOT NULL DEFAULT 'draft',
    report_data_json LONGTEXT NOT NULL CHECK (JSON_VALID(report_data_json)),
    pdf_path VARCHAR(500) NULL,
    pdf_sha256 CHAR(64) NULL,
    overall_percentage DECIMAL(5,2) NULL,
    class_position INT UNSIGNED NULL,
    class_population INT UNSIGNED NULL,
    generated_by INT UNSIGNED NOT NULL,
    generated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    approved_by INT UNSIGNED NULL,
    approved_at DATETIME NULL,
    released_by INT UNSIGNED NULL,
    released_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_report_release_version (student_id, academic_year_term_id, version_no),
    KEY idx_report_release_term_status (academic_year_term_id, status),
    KEY idx_report_release_enrollment (student_academic_enrollment_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS report_card_deliveries (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    report_card_release_id BIGINT UNSIGNED NOT NULL,
    parent_id INT UNSIGNED NOT NULL,
    channel ENUM('sms','email','whatsapp','portal') NOT NULL,
    communication_id INT UNSIGNED NULL,
    status ENUM('queued','sent','delivered','failed','skipped') NOT NULL DEFAULT 'queued',
    failure_reason VARCHAR(500) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_report_delivery_parent_channel (report_card_release_id, parent_id, channel),
    KEY idx_report_delivery_communication (communication_id),
    CONSTRAINT fk_report_delivery_release FOREIGN KEY (report_card_release_id) REFERENCES report_card_releases(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
