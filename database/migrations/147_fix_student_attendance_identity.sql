/*
 * Attendance rows are created by the attendance API without supplying an id.
 * The legacy table had a plain primary key, so valid attendance submissions
 * failed with "Field 'id' doesn't have a default value".  Keep existing ids
 * and let MySQL allocate ids for new attendance marks.
 */
ALTER TABLE student_attendance
    MODIFY COLUMN id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT;
