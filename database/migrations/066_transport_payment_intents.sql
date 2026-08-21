-- Transport payments are independent from school-fee payments.
-- This table is the business intent that is linked to a provider attempt;
-- entitlement allocation happens only after a verified confirmation.
CREATE TABLE IF NOT EXISTS transport_payment_intents (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    entitlement_id BIGINT UNSIGNED NOT NULL,
    student_id INT UNSIGNED NOT NULL,
    channel ENUM('daraja_mpesa','buni_mpesa','bank_transfer','cash','cheque') NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    phone_number VARCHAR(20) DEFAULT NULL,
    idempotency_reference VARCHAR(150) NOT NULL,
    provider_request_id VARCHAR(150) DEFAULT NULL,
    provider_transaction_id VARCHAR(150) DEFAULT NULL,
    status ENUM('pending','accepted','manual_review','confirmed','failed','reversed','cancelled') NOT NULL DEFAULT 'pending',
    request_payload JSON DEFAULT NULL,
    response_payload JSON DEFAULT NULL,
    failure_reason VARCHAR(500) DEFAULT NULL,
    created_by INT UNSIGNED DEFAULT NULL,
    confirmed_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_transport_payment_intent_reference (idempotency_reference),
    UNIQUE KEY uq_transport_payment_intent_provider_tx (channel, provider_transaction_id),
    KEY idx_transport_payment_intent_status (status, created_at),
    KEY idx_transport_payment_intent_provider_request (channel, provider_request_id),
    CONSTRAINT fk_transport_intent_entitlement FOREIGN KEY (entitlement_id) REFERENCES student_transport_entitlements(id),
    CONSTRAINT fk_transport_intent_student FOREIGN KEY (student_id) REFERENCES students(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
