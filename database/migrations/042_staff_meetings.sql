-- 042_staff_meetings.sql
--
-- Internal staff meetings scheduled by heads (director, school admin, HODs to
-- department members or selected members, deputies, class teachers, etc.),
-- fully integrated with the academic calendar:
--
--   * staff_meetings             - the meeting record (date, start/end time,
--                                  venue OR online link, department/class
--                                  target, organizer, agenda, minutes).
--   * staff_meeting_attendees    - who is invited / RSVP status.
--   * Calendar integration: each meeting creates/syncs a linked school_events
--     row (type 'Meeting', location = venue, description carries the online
--     link) so it appears on the academic calendar, the Year Calendar and the
--     events pages - everyone knows about it, sees the venue or joins the
--     online link.
--   * Reminders: attendees are notified through the existing `notifications`
--     table when the meeting is created/updated and when "Send Reminder" is
--     pressed.

DROP TABLE IF EXISTS `staff_meeting_attendees`;
DROP TABLE IF EXISTS `staff_meetings`;

CREATE TABLE `staff_meetings` (
    `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `title`             VARCHAR(255) NOT NULL,
    `description`       TEXT NULL,
    `meeting_type`      ENUM('general','departmental','administrative','heads','class_teachers','assembly','other') NOT NULL DEFAULT 'general',
    `meeting_date`      DATE NOT NULL,
    `start_time`        TIME NOT NULL,
    `end_time`          TIME NULL,
    `venue`             VARCHAR(255) NULL,
    `meeting_link`      VARCHAR(500) NULL,
    `department_id`     INT UNSIGNED NULL,
    `class_id`          INT UNSIGNED NULL,
    `organizer_staff_id` INT UNSIGNED NOT NULL,
    `agenda`            TEXT NULL,
    `minutes`           TEXT NULL,
    `status`            ENUM('scheduled','ongoing','completed','cancelled') NOT NULL DEFAULT 'scheduled',
    `calendar_event_id` INT UNSIGNED NULL,
    `created_at`        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_meeting_date` (`meeting_date`),
    KEY `idx_meeting_organizer` (`organizer_staff_id`),
    KEY `idx_meeting_department` (`department_id`),
    KEY `idx_meeting_class` (`class_id`),
    KEY `idx_meeting_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Internal staff meetings (heads/HODs/deputies/class teachers) - calendar-integrated';

CREATE TABLE `staff_meeting_attendees` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `meeting_id`    INT UNSIGNED NOT NULL,
    `staff_id`      INT UNSIGNED NOT NULL,
    `status`        ENUM('invited','accepted','declined','maybe') NOT NULL DEFAULT 'invited',
    `responded_at`  TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_meeting_staff` (`meeting_id`, `staff_id`),
    KEY `idx_attendee_staff` (`staff_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Meeting invitees + RSVP';

SELECT 'Migration completed - staff meetings module created' AS status;
