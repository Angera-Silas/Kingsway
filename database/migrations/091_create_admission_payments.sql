-- Pre-enrollment payments recorded against an admission application before
-- they are posted into the student's normal payment ledger.
CREATE TABLE IF NOT EXISTS admission_payments (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    application_id INT UNSIGNED NOT NULL,
    student_id INT UNSIGNED NULL,
    amount DECIMAL(12,2) NOT NULL,
    payment_method ENUM('cash','bank_transfer','mpesa','cheque','other') NOT NULL DEFAULT 'cash',
    reference_no VARCHAR(100) NULL,
    receipt_no VARCHAR(100) NULL,
    payment_date DATETIME NOT NULL,
    notes TEXT NULL,
    status ENUM('recorded','posted','voided') NOT NULL DEFAULT 'recorded',
    recorded_by INT UNSIGNED NULL,
    posted_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_admission_payments_application (application_id),
    KEY idx_admission_payments_student (student_id),
    KEY idx_admission_payments_reference (reference_no),
    CONSTRAINT fk_admission_payment_application FOREIGN KEY (application_id) REFERENCES admission_applications (id),
    CONSTRAINT fk_admission_payment_student FOREIGN KEY (student_id) REFERENCES students (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
