-- Single database projection consumed by academic modules. It represents the
-- union of:
--   1) full visibility of a teacher's assigned class streams; and
--   2) exact stream-learning-area teaching assignments.
-- Leadership scope remains permission-driven and is intentionally not encoded
-- here as a blanket teacher grant.
CREATE OR REPLACE VIEW vw_teacher_effective_stream_learning_areas AS
SELECT DISTINCT
    aycs.class_teacher_id AS staff_id,
    ayc.academic_year_id,
    ayt.id AS academic_year_term_id,
    aycs.id AS academic_year_class_stream_id,
    sla.id AS academic_year_class_stream_learning_area_id,
    cla.learning_area_id,
    'class_teacher' AS scope_type
FROM academic_year_class_streams aycs
JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
JOIN academic_year_class_stream_learning_areas sla
  ON sla.academic_year_class_stream_id = aycs.id
JOIN academic_year_class_learning_areas cla
  ON cla.id = sla.academic_year_class_learning_area_id
JOIN academic_year_terms ayt
  ON ayt.academic_year_id = ayc.academic_year_id
WHERE aycs.class_teacher_id IS NOT NULL
  AND aycs.status IN ('planning','active')
  AND sla.status IN ('planned','active','in_progress','covered')
UNION ALL
SELECT DISTINCT
    x.staff_id,
    ayc.academic_year_id,
    x.academic_year_term_id,
    x.academic_year_class_stream_id,
    x.academic_year_class_stream_learning_area_id,
    cla.learning_area_id,
    x.role AS scope_type
FROM academic_year_class_stream_learning_area_teachers x
JOIN academic_year_class_stream_learning_areas sla
  ON sla.id = x.academic_year_class_stream_learning_area_id
JOIN academic_year_class_learning_areas cla
  ON cla.id = sla.academic_year_class_learning_area_id
JOIN academic_year_class_streams aycs
  ON aycs.id = x.academic_year_class_stream_id
JOIN academic_year_classes ayc
  ON ayc.id = aycs.academic_year_class_id
WHERE x.status = 'active'
  AND aycs.status IN ('planning','active')
  AND sla.status IN ('planned','active','in_progress','covered');
