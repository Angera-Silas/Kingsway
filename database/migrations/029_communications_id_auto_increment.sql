-- 029_communications_id_auto_increment.sql
-- The legacy communications table was imported without AUTO_INCREMENT on id,
-- so every createCommunication() INSERT failed under strict SQL mode with
-- "Field 'id' doesn't have a default value" (HTTP 500), breaking announcement
-- publishing, SMS, email and WhatsApp sends. Adding AUTO_INCREMENT is safe:
-- existing ids are preserved and the counter starts at MAX(id)+1; no
-- application insert specifies an explicit id.

ALTER TABLE `communications`
    MODIFY COLUMN `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT;
