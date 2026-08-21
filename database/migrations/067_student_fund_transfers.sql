-- Controlled movement of confirmed, unallocated student credits between the
-- independent fee and transport accounts. This is not a payment provider call.
CREATE TABLE IF NOT EXISTS student_fund_transfers (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    source_student_id INT UNSIGNED NOT NULL,
    source_account_type ENUM('fees','transport') NOT NULL,
    source_credit_note_id INT UNSIGNED DEFAULT NULL,
    source_entitlement_id BIGINT UNSIGNED DEFAULT NULL,
    destination_student_id INT UNSIGNED NOT NULL,
    destination_account_type ENUM('fees','transport') NOT NULL,
    destination_entitlement_id BIGINT UNSIGNED DEFAULT NULL,
    amount DECIMAL(12,2) NOT NULL,
    parent_id INT UNSIGNED DEFAULT NULL,
    parent_request_reference VARCHAR(120) DEFAULT NULL,
    reason VARCHAR(255) NOT NULL,
    status ENUM('pending_approval','approved','rejected','posted','reversed') NOT NULL DEFAULT 'pending_approval',
    created_by INT UNSIGNED NOT NULL,
    approved_by INT UNSIGNED DEFAULT NULL,
    posted_by INT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    approved_at DATETIME DEFAULT NULL,
    posted_at DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_student_fund_transfer_status (status, created_at),
    KEY idx_student_fund_transfer_source (source_student_id, source_account_type),
    KEY idx_student_fund_transfer_destination (destination_student_id, destination_account_type),
    CONSTRAINT fk_fund_transfer_source_student FOREIGN KEY (source_student_id) REFERENCES students(id),
    CONSTRAINT fk_fund_transfer_destination_student FOREIGN KEY (destination_student_id) REFERENCES students(id),
    CONSTRAINT fk_fund_transfer_parent FOREIGN KEY (parent_id) REFERENCES parents(id),
    CONSTRAINT fk_fund_transfer_source_credit FOREIGN KEY (source_credit_note_id) REFERENCES fee_credit_notes(id),
    CONSTRAINT fk_fund_transfer_source_entitlement FOREIGN KEY (source_entitlement_id) REFERENCES student_transport_entitlements(id),
    CONSTRAINT fk_fund_transfer_destination_entitlement FOREIGN KEY (destination_entitlement_id) REFERENCES student_transport_entitlements(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS student_fund_transfer_postings (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    transfer_id BIGINT UNSIGNED NOT NULL,
    posting_type ENUM('fee_credit_debit','fee_credit_credit','transport_debit','transport_credit') NOT NULL,
    source_record_id BIGINT UNSIGNED DEFAULT NULL,
    destination_record_id BIGINT UNSIGNED DEFAULT NULL,
    amount DECIMAL(12,2) NOT NULL,
    posted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_fund_transfer_posting (transfer_id, posting_type),
    CONSTRAINT fk_fund_transfer_posting_transfer FOREIGN KEY (transfer_id) REFERENCES student_fund_transfers(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
