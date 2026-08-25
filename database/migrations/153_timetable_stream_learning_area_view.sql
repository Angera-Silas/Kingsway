CREATE OR REPLACE VIEW vw_timetable_entries AS
SELECT
    te.id,
    te.academic_year_class_stream_id,
    te.academic_year_class_stream_learning_area_id,
    te.academic_year_term_id,
    ayt.academic_year_id,
    c.id AS class_id,
    c.name AS class_name,
    aycs.stream_id,
    st.name AS stream_name,
    te.day_of_week,
    CASE te.day_of_week
        WHEN 1 THEN 'Monday' WHEN 2 THEN 'Tuesday' WHEN 3 THEN 'Wednesday'
        WHEN 4 THEN 'Thursday' WHEN 5 THEN 'Friday' WHEN 6 THEN 'Saturday'
        WHEN 7 THEN 'Sunday' ELSE NULL
    END AS day,
    te.time_slot_id,
    ts.period_number,
    ts.start_time,
    ts.end_time,
    ts.slot_type,
    ts.label AS time_slot_label,
    COALESCE(cla.learning_area_id, te.learning_area_id) AS subject_id,
    la.name AS subject_name,
    la.name AS learning_area_name,
    te.teacher_id,
    CONCAT_WS(' ', p.first_name, p.last_name) AS teacher_name,
    aycs.room_id,
    r.name AS room_name,
    r.code AS room_code,
    t.id AS term_id,
    t.name AS term_name,
    t.code AS term_number,
    te.status,
    te.created_at,
    te.updated_at
FROM timetable_entries te
JOIN academic_year_class_streams aycs ON aycs.id = te.academic_year_class_stream_id
JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
JOIN classes c ON c.id = ayc.class_id
LEFT JOIN streams st ON st.id = aycs.stream_id
JOIN academic_year_terms ayt ON ayt.id = te.academic_year_term_id
LEFT JOIN terms t ON t.id = ayt.term_id
LEFT JOIN time_slots ts ON ts.id = te.time_slot_id
LEFT JOIN academic_year_class_stream_learning_areas sla
       ON sla.id = te.academic_year_class_stream_learning_area_id
LEFT JOIN academic_year_class_learning_areas cla
       ON cla.id = sla.academic_year_class_learning_area_id
LEFT JOIN learning_areas la ON la.id = COALESCE(cla.learning_area_id, te.learning_area_id)
LEFT JOIN staff s ON s.id = te.teacher_id
LEFT JOIN persons p ON p.id = s.person_id
LEFT JOIN rooms r ON r.id = aycs.room_id;
