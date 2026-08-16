-- ============================================================
-- Migration 016: Generalize counseling schema for staff counselees
-- Date: 2026-08-08
--
-- Renames student_counseling_cases/sessions -> counseling_cases/sessions and
-- adds counselee_type + staff_id so that both students AND staff can receive
-- counseling. Sessions inherit the counselee via their case (case_id).
-- ============================================================

RENAME TABLE `student_counseling_cases` TO `counseling_cases`;
RENAME TABLE `student_counseling_sessions` TO `counseling_sessions`;

ALTER TABLE `counseling_cases`
    ADD COLUMN `counselee_type` ENUM('student','staff') NOT NULL DEFAULT 'student' AFTER `id`,
    ADD COLUMN `staff_id` INT(10) UNSIGNED DEFAULT NULL AFTER `student_id`,
    MODIFY COLUMN `student_id` INT(10) UNSIGNED DEFAULT NULL,
    ADD KEY `idx_staff_id` (`staff_id`),
    ADD CONSTRAINT `chk_counseling_cases_counselee` CHECK (
        (counselee_type = 'student' AND student_id IS NOT NULL AND staff_id IS NULL)
        OR (counselee_type = 'staff' AND staff_id IS NOT NULL AND student_id IS NULL)
    );
