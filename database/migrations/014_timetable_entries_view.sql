-- 014_timetable_entries_view.sql
-- Canonical read view for the live `timetable_entries` table.
-- The legacy Schedules module was written against a non-existent `class_schedules`
-- table (aliases cs.*, cs.class_id, cs.subject_id, day-name day_of_week, term_id).
-- This view exposes the same columns the API/JS payloads expect while resolving
-- against the real schema:
--   * class_id/class_name  -> via academic_year_class_streams -> academic_year_classes -> classes
--   * subject_id (learning_area_id) / subject_name
--   * teacher_id / teacher_name (staff -> persons)
--   * room_id / room_name   -> academic_year_class_streams.room_id -> rooms
--   * term_id (academic_year_terms.id) / term_name / term_number
--   * start_time/end_time/period_number/label -> time_slots
--   * day_of_week numeric 1=Mon..7=Sun + readable `day`
-- Idempotent: CREATE OR REPLACE.
CREATE OR REPLACE VIEW vw_timetable_entries AS
SELECT
    cs.id,
    cs.academic_year_class_stream_id,
    cs.academic_year_term_id,
    ayt.academic_year_id,
    c.id AS class_id,
    c.name AS class_name,
    aycs.stream_id,
    st.name AS stream_name,
    cs.day_of_week,
    CASE cs.day_of_week
        WHEN 1 THEN 'Monday'
        WHEN 2 THEN 'Tuesday'
        WHEN 3 THEN 'Wednesday'
        WHEN 4 THEN 'Thursday'
        WHEN 5 THEN 'Friday'
        WHEN 6 THEN 'Saturday'
        WHEN 7 THEN 'Sunday'
        ELSE NULL
    END AS day,
    cs.time_slot_id,
    ts.period_number,
    ts.start_time,
    ts.end_time,
    ts.slot_type,
    ts.label AS time_slot_label,
    cs.learning_area_id AS subject_id,
    la.name AS subject_name,
    la.name AS learning_area_name,
    cs.teacher_id,
    CONCAT_WS(' ', p.first_name, p.last_name) AS teacher_name,
    aycs.room_id,
    r.name AS room_name,
    r.code AS room_code,
    t.id AS term_id,
    t.name AS term_name,
    t.code AS term_number,
    cs.status,
    cs.created_at,
    cs.updated_at
FROM timetable_entries cs
JOIN academic_year_class_streams aycs ON aycs.id = cs.academic_year_class_stream_id
JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
JOIN classes c ON c.id = ayc.class_id
LEFT JOIN streams st ON st.id = aycs.stream_id
JOIN academic_year_terms ayt ON ayt.id = cs.academic_year_term_id
LEFT JOIN terms t ON t.id = ayt.term_id
LEFT JOIN time_slots ts ON ts.id = cs.time_slot_id
LEFT JOIN learning_areas la ON la.id = cs.learning_area_id
LEFT JOIN staff s ON s.id = cs.teacher_id
LEFT JOIN persons p ON p.id = s.person_id
LEFT JOIN rooms r ON r.id = aycs.room_id;
