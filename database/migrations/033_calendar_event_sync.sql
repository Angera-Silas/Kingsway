-- 033_calendar_event_sync.sql
--
-- Makes academic_year_calendar_days and school_events two-way synced:
--   * school_events.calendar_day_id links an event to its calendar day (UNIQUE),
--     so an update on either side can be propagated to the other.
--   * school_events.source distinguishes calendar-derived mirror events from
--     manually created ones.
--   * exam_schedules.source marks exam rows auto-generated from calendar exam
--     days/weeks so they can be refreshed (and cleaned up) without touching
--     manually created exam rows.

ALTER TABLE `school_events`
    ADD COLUMN `calendar_day_id` INT(10) UNSIGNED NULL AFTER `type`,
    ADD COLUMN `source` ENUM('calendar','manual') NOT NULL DEFAULT 'manual' AFTER `calendar_day_id`,
    ADD UNIQUE KEY `uq_school_events_calendar_day` (`calendar_day_id`);

ALTER TABLE `exam_schedules`
    ADD COLUMN `source` ENUM('calendar','manual') NOT NULL DEFAULT 'manual' AFTER `status`;
