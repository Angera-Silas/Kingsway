ALTER TABLE admission_payments
    MODIFY status ENUM('pending_verification','recorded','posted','voided') NOT NULL DEFAULT 'pending_verification',
    ADD COLUMN verification_source VARCHAR(40) NULL AFTER status,
    ADD COLUMN verified_by INT UNSIGNED NULL AFTER verification_source,
    ADD COLUMN verified_at DATETIME NULL AFTER verified_by,
    ADD COLUMN verification_notes TEXT NULL AFTER verified_at,
    ADD KEY idx_admission_payments_status (status);
