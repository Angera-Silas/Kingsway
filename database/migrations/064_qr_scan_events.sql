-- Durable QR scan audit trail.  The QR itself contains only an opaque card
-- credential; operational results are recorded here and in the domain table.
-- Kept separate from transport attendance so student/gate/exam scans remain
-- auditable without polluting the attendance fact table (4NF).
CREATE TABLE IF NOT EXISTS qr_scan_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    student_id INT UNSIGNED NULL,
    operator_user_id INT UNSIGNED NOT NULL,
    context VARCHAR(32) NOT NULL,
    action VARCHAR(32) NOT NULL DEFAULT 'verify',
    result VARCHAR(16) NOT NULL,
    record_id BIGINT UNSIGNED NULL,
    client_reference VARCHAR(100) NULL,
    reason VARCHAR(255) NULL,
    scanned_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_qr_scan_client_reference (client_reference),
    KEY ix_qr_scan_student_time (student_id, scanned_at),
    KEY ix_qr_scan_operator_time (operator_user_id, scanned_at),
    KEY ix_qr_scan_context_time (context, scanned_at),
    CONSTRAINT chk_qr_scan_result CHECK (result IN ('accepted', 'rejected', 'error'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
