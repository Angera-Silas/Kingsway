-- Fix: exam_schedules.id was created without AUTO_INCREMENT, so every
-- INSERT (normal exam creation and calendar sync) fails with
-- "Field 'id' doesn't have a default value". Restore auto-increment.
ALTER TABLE exam_schedules MODIFY COLUMN id int(10) unsigned NOT NULL AUTO_INCREMENT;
