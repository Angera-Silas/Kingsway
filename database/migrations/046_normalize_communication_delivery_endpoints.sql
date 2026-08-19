-- 046_normalize_communication_delivery_endpoints.sql
-- 4NF correction: a logical communication recipient and its channel endpoint
-- are separate facts. Provider attempts/status belong to the endpoint row.

ALTER TABLE communication_recipients
    MODIFY recipient_id INT(10) UNSIGNED NULL,
    DROP COLUMN recipient_address,
    DROP COLUMN recipient_name,
    DROP COLUMN provider_message_id,
    DROP COLUMN provider_status,
    DROP COLUMN next_attempt_at,
    DROP COLUMN last_error;

CREATE TABLE communication_recipient_endpoints (
    id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    communication_recipient_id INT(10) UNSIGNED NOT NULL,
    channel ENUM('email','sms','whatsapp') NOT NULL,
    address VARCHAR(255) NOT NULL,
    status ENUM('pending','processing','sent','delivered','failed','retry') NOT NULL DEFAULT 'pending',
    provider_message_id VARCHAR(255) NULL,
    provider_status VARCHAR(100) NULL,
    attempt_count INT(10) UNSIGNED NOT NULL DEFAULT 0,
    last_attempt_at DATETIME NULL,
    next_attempt_at DATETIME NULL,
    delivered_at DATETIME NULL,
    last_error TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_comm_recipient_channel (communication_recipient_id, channel),
    KEY idx_endpoint_provider (provider_message_id),
    KEY idx_endpoint_queue (status, next_attempt_at),
    CONSTRAINT fk_endpoint_recipient
        FOREIGN KEY (communication_recipient_id)
        REFERENCES communication_recipients(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
