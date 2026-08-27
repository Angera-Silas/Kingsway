-- Resilient KCB Buni disbursement reconciliation.
-- Provider callbacks remain the preferred completion path; status inquiries
-- recover transfers whose callback is delayed or lost without permitting an
-- unsafe duplicate payment.

ALTER TABLE disbursement_transactions
    ADD COLUMN reconciliation_status ENUM(
        'awaiting_callback',
        'confirmed_success',
        'confirmed_failure',
        'exception',
        'manual_review'
    ) NOT NULL DEFAULT 'awaiting_callback' AFTER provider_status,
    ADD COLUMN last_status_inquiry_at DATETIME NULL AFTER reconciliation_status,
    ADD COLUMN next_status_inquiry_at DATETIME NULL AFTER last_status_inquiry_at,
    ADD COLUMN status_inquiry_count SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER next_status_inquiry_at,
    ADD COLUMN reconciliation_lock_until DATETIME NULL AFTER status_inquiry_count,
    ADD COLUMN retry_of_disbursement_id INT UNSIGNED NULL AFTER reconciliation_lock_until,
    ADD KEY idx_disbursement_kcb_reconciliation (
        channel, status, reconciliation_status, next_status_inquiry_at
    ),
    ADD KEY idx_disbursement_retry_parent (retry_of_disbursement_id),
    ADD CONSTRAINT fk_disbursement_retry_parent
        FOREIGN KEY (retry_of_disbursement_id) REFERENCES disbursement_transactions (id);

UPDATE disbursement_transactions
SET reconciliation_status = CASE
        WHEN status = 'completed' THEN 'confirmed_success'
        WHEN status = 'failed' THEN 'manual_review'
        ELSE 'awaiting_callback'
    END,
    next_status_inquiry_at = CASE
        WHEN channel = 'kcb_bank' AND status = 'pending' THEN DATE_ADD(created_at, INTERVAL 2 MINUTE)
        ELSE NULL
    END;

CREATE TABLE IF NOT EXISTS kcb_transfer_status_inquiries (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    disbursement_id INT UNSIGNED NOT NULL,
    actor_user_id INT UNSIGNED NULL,
    trigger_source ENUM('manual','worker','retry_guard') NOT NULL,
    provider_reference VARCHAR(150) NULL,
    provider_request_id VARCHAR(150) NULL,
    request_payload JSON NOT NULL,
    response_payload JSON NULL,
    normalized_status ENUM('successful','failed','pending','unknown','error') NOT NULL,
    provider_status VARCHAR(100) NULL,
    provider_message VARCHAR(500) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_kcb_inquiry_disbursement (disbursement_id, created_at),
    KEY idx_kcb_inquiry_status (normalized_status, created_at),
    CONSTRAINT fk_kcb_inquiry_disbursement
        FOREIGN KEY (disbursement_id) REFERENCES disbursement_transactions (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS kcb_disbursement_exceptions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    disbursement_id INT UNSIGNED NOT NULL,
    exception_code VARCHAR(80) NOT NULL,
    reason VARCHAR(500) NOT NULL,
    status ENUM('open','resolved') NOT NULL DEFAULT 'open',
    assigned_to INT UNSIGNED NULL,
    first_detected_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_detected_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    resolved_by INT UNSIGNED NULL,
    resolved_at DATETIME NULL,
    resolution_notes VARCHAR(500) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_kcb_exception_disbursement (disbursement_id),
    KEY idx_kcb_exception_queue (status, last_detected_at),
    CONSTRAINT fk_kcb_exception_disbursement
        FOREIGN KEY (disbursement_id) REFERENCES disbursement_transactions (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS kcb_disbursement_audit_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    disbursement_id INT UNSIGNED NOT NULL,
    actor_user_id INT UNSIGNED NULL,
    event_type VARCHAR(60) NOT NULL,
    previous_status VARCHAR(80) NULL,
    new_status VARCHAR(80) NULL,
    details JSON NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_kcb_audit_disbursement (disbursement_id, created_at),
    KEY idx_kcb_audit_event (event_type, created_at),
    CONSTRAINT fk_kcb_audit_disbursement
        FOREIGN KEY (disbursement_id) REFERENCES disbursement_transactions (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE OR REPLACE VIEW vw_kcb_disbursement_reconciliation AS
SELECT d.id AS disbursement_id,
       d.retry_of_disbursement_id,
       d.disbursement_type,
       d.payment_purpose,
       d.recipient_name,
       d.amount,
       d.currency,
       d.account_number,
       d.bank_name,
       d.request_id,
       d.transaction_ref,
       d.transaction_id,
       d.status,
       d.provider_status,
       d.reconciliation_status,
       d.status_inquiry_count,
       d.last_status_inquiry_at,
       d.next_status_inquiry_at,
       d.result_description,
       d.created_at,
       e.id AS exception_id,
       e.exception_code,
       e.reason AS exception_reason,
       e.status AS exception_status,
       e.last_detected_at AS exception_detected_at
FROM disbursement_transactions d
LEFT JOIN kcb_disbursement_exceptions e
       ON e.disbursement_id = d.id AND e.status = 'open'
WHERE d.channel = 'kcb_bank';
