-- 031_academic_calendar_manual_days.sql
--
-- Adds an is_manual flag to academic_year_calendar_days so manually-marked
-- days (emergency/national holidays, closures, special events) can be
-- preserved when sp_generate_year_calendar regenerates a term's calendar.
-- Rows regenerated from term dates default to is_manual = 0.

ALTER TABLE `academic_year_calendar_days`
    ADD COLUMN `is_manual` TINYINT(1) NOT NULL DEFAULT 0 AFTER `description`;
