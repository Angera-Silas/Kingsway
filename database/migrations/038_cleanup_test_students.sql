-- 038_cleanup_test_students.sql
--
-- Data cleanup: remove the two test students created during API verification
-- (ids 1 and 2, auto-gen + manual admission_no). Removes all linked rows:
-- enrollments, persons, parents (with their persons), student_parents links
-- and the incidental activity_participants row (enrollment 2).

SET @s1 = 1;
SET @s2 = 2;

-- Parent + parent-person rows created only for these test students (each
-- parent links to exactly one of the two students). Capture the parent-person
-- ids BEFORE removing the student_parents links.
CREATE TEMPORARY TABLE tmp_test_parent_persons AS
SELECT p.person_id
FROM parents p
JOIN student_parents sp ON sp.parent_id = p.id
WHERE sp.student_id IN (@s1, @s2);

CREATE TEMPORARY TABLE tmp_test_parents AS
SELECT id FROM parents WHERE person_id IN (SELECT person_id FROM tmp_test_parent_persons);

-- Activity participants pointing at the test enrollments, plus any that
-- reference enrollments which no longer exist (orphaned).
DELETE FROM activity_participants WHERE student_academic_enrollment_id IS NULL
   OR student_academic_enrollment_id NOT IN (SELECT id FROM student_academic_enrollments)
   OR student_academic_enrollment_id IN (
       SELECT sae.id FROM student_academic_enrollments sae WHERE sae.student_id IN (@s1, @s2)
   );

-- Parent/student links
DELETE FROM student_parents WHERE student_id IN (@s1, @s2);

-- Parents + their persons
DELETE FROM parents WHERE id IN (SELECT id FROM tmp_test_parents);
DELETE FROM persons WHERE id IN (SELECT person_id FROM tmp_test_parent_persons);

-- Student persons
DELETE FROM persons WHERE id IN (
    SELECT person_id FROM students WHERE id IN (@s1, @s2)
);

-- Students
DELETE FROM students WHERE id IN (@s1, @s2);

-- Enrollments
DELETE FROM student_academic_enrollments WHERE student_id IN (@s1, @s2);

DROP TEMPORARY TABLE IF EXISTS tmp_test_parent_persons;
DROP TEMPORARY TABLE IF EXISTS tmp_test_parents;
