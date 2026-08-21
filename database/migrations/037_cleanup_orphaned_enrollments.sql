-- 037_cleanup_orphaned_enrollments.sql
--
-- Data cleanup: remove student_academic_enrollments rows that reference
-- non-existent students (orphaned data). The `students` master table is empty,
-- so all 61 enrollments are orphans with no dependent records
-- (fee obligations, attendance, assessment results all empty).

DELETE FROM `student_academic_enrollments`
WHERE `student_id` NOT IN (SELECT `id` FROM `students`);
