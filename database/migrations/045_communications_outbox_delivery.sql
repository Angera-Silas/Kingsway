-- 045_communications_outbox_delivery.sql
-- Turn existing communications tables into a durable, per-recipient outbox.

ALTER TABLE communications
    MODIFY status ENUM('draft','queued','processing','scheduled','sent','delivered','failed') NOT NULL DEFAULT 'draft',
    ADD COLUMN attempt_count INT UNSIGNED NOT NULL DEFAULT 0 AFTER reminder_at,
    ADD COLUMN next_attempt_at DATETIME NULL AFTER attempt_count,
    ADD COLUMN locked_at DATETIME NULL AFTER next_attempt_at,
    ADD COLUMN locked_by VARCHAR(100) NULL AFTER locked_at,
    ADD COLUMN processed_at DATETIME NULL AFTER locked_by,
    ADD COLUMN last_error TEXT NULL AFTER processed_at,
    ADD KEY idx_communications_outbox (status, scheduled_at, next_attempt_at);

ALTER TABLE communication_recipients
    MODIFY status ENUM('pending','queued','processing','sent','delivered','failed','retry') NOT NULL DEFAULT 'pending',
    ADD COLUMN recipient_address VARCHAR(255) NULL AFTER recipient_id,
    ADD COLUMN recipient_name VARCHAR(255) NULL AFTER recipient_address,
    ADD COLUMN provider_message_id VARCHAR(255) NULL AFTER recipient_name,
    ADD COLUMN provider_status VARCHAR(100) NULL AFTER provider_message_id,
    ADD COLUMN next_attempt_at DATETIME NULL AFTER last_attempt_at,
    ADD COLUMN last_error TEXT NULL AFTER error_message,
    ADD KEY idx_comm_recipient_provider (provider_message_id),
    ADD KEY idx_comm_recipient_delivery (status, next_attempt_at);
