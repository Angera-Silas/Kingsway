-- All active canonical teacher/timetable rows are now linked. Enforce the
-- relationship so future code cannot create class-stream records without a
-- stream-learning-area context.
ALTER TABLE academic_year_class_stream_learning_area_teachers
    MODIFY COLUMN academic_year_class_stream_learning_area_id INT UNSIGNED NOT NULL;

ALTER TABLE timetable_entries
    MODIFY COLUMN academic_year_class_stream_learning_area_id INT UNSIGNED NOT NULL;

ALTER TABLE timetable_draft_entries
    MODIFY COLUMN academic_year_class_stream_learning_area_id INT UNSIGNED NOT NULL;
