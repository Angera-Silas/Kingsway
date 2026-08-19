-- 044_professional_notifications.sql
-- Durable context for deep links and one-per-window event reminders.
ALTER TABLE notifications
    ADD COLUMN action_url VARCHAR(500) NULL AFTER message,
    ADD COLUMN reference_type VARCHAR(50) NULL AFTER action_url,
    ADD COLUMN reference_id INT UNSIGNED NULL AFTER reference_type,
    ADD COLUMN reminder_window VARCHAR(20) NULL AFTER reference_id,
    ADD KEY idx_notifications_reference (reference_type, reference_id),
    ADD UNIQUE KEY uq_notifications_reminder (user_id, reference_type, reference_id, reminder_window);
