CREATE TABLE IF NOT EXISTS fee_structure_adjustments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    student_fee_obligation_id INT UNSIGNED NOT NULL,
    old_schedule_id INT UNSIGNED NOT NULL,
    new_schedule_id INT UNSIGNED NOT NULL,
    old_amount DECIMAL(12,2) NOT NULL,
    new_amount DECIMAL(12,2) NOT NULL,
    amount_delta DECIMAL(12,2) NOT NULL,
    adjustment_type ENUM('credit','debit','reprice','unchanged') NOT NULL,
    payment_protected TINYINT(1) NOT NULL DEFAULT 1,
    created_by INT UNSIGNED NULL,
    notes TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_fee_adjustment_obligation (student_fee_obligation_id),
    KEY idx_fee_adjustment_schedule (old_schedule_id, new_schedule_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
