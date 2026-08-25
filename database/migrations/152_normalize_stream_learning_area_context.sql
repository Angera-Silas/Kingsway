-- Canonical academic unit:
-- academic_year -> academic_year_class_streams ->
-- academic_year_class_stream_learning_areas.
--
-- The older academic_year_class_learning_areas table remains the curriculum
-- template for a class. This table materialises that curriculum per stream,
-- which is the level at which learners, teachers, timetables and assessments
-- operate.
CREATE TABLE IF NOT EXISTS academic_year_class_stream_learning_areas (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    academic_year_class_stream_id INT UNSIGNED NOT NULL,
    academic_year_class_learning_area_id INT UNSIGNED NOT NULL,
    status ENUM('planned','active','in_progress','covered','skipped') NOT NULL DEFAULT 'planned',
    notes TEXT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_stream_curriculum_area (
        academic_year_class_stream_id, academic_year_class_learning_area_id
    ),
    KEY idx_stream_learning_area_stream (academic_year_class_stream_id),
    KEY idx_stream_learning_area_curriculum (academic_year_class_learning_area_id),
    CONSTRAINT fk_stream_learning_area_stream
        FOREIGN KEY (academic_year_class_stream_id)
        REFERENCES academic_year_class_streams (id) ON DELETE CASCADE,
    CONSTRAINT fk_stream_learning_area_curriculum
        FOREIGN KEY (academic_year_class_learning_area_id)
        REFERENCES academic_year_class_learning_areas (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Materialise every configured class learning area for every stream in that
-- academic year. INSERT IGNORE makes this safe for a partially seeded DB.
INSERT IGNORE INTO academic_year_class_stream_learning_areas
    (academic_year_class_stream_id, academic_year_class_learning_area_id, status, notes)
SELECT aycs.id, aycla.id,
       CASE WHEN aycla.status = 'planned' THEN 'planned' ELSE 'active' END,
       aycla.notes
FROM academic_year_class_streams aycs
JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
JOIN academic_year_class_learning_areas aycla
  ON aycla.academic_year_class_id = ayc.id
WHERE aycs.status IN ('planning','active');

-- New assignment rows point to the canonical stream-learning-area context.
ALTER TABLE academic_year_class_stream_learning_area_teachers
    ADD COLUMN academic_year_class_stream_learning_area_id INT UNSIGNED NULL AFTER id,
    ADD KEY idx_stream_learning_area_teacher_context (academic_year_class_stream_learning_area_id),
    ADD CONSTRAINT fk_stream_learning_area_teacher_context
        FOREIGN KEY (academic_year_class_stream_learning_area_id)
        REFERENCES academic_year_class_stream_learning_areas (id) ON DELETE CASCADE;

UPDATE academic_year_class_stream_learning_area_teachers x
JOIN academic_year_class_stream_learning_areas sla
  ON sla.academic_year_class_stream_id = x.academic_year_class_stream_id
JOIN academic_year_class_learning_areas cla
  ON cla.id = sla.academic_year_class_learning_area_id
 AND cla.learning_area_id = x.learning_area_id
SET x.academic_year_class_stream_learning_area_id = sla.id
WHERE x.academic_year_class_stream_learning_area_id IS NULL;

-- Timetable facts and drafts also retain their old learning_area_id for
-- compatibility, but the canonical context is now available and populated.
ALTER TABLE timetable_entries
    ADD COLUMN academic_year_class_stream_learning_area_id INT UNSIGNED NULL AFTER academic_year_class_stream_id,
    ADD KEY idx_timetable_stream_learning_area (academic_year_class_stream_learning_area_id),
    ADD CONSTRAINT fk_timetable_stream_learning_area
        FOREIGN KEY (academic_year_class_stream_learning_area_id)
        REFERENCES academic_year_class_stream_learning_areas (id) ON DELETE RESTRICT;

UPDATE timetable_entries t
JOIN academic_year_class_stream_learning_areas sla
  ON sla.academic_year_class_stream_id = t.academic_year_class_stream_id
JOIN academic_year_class_learning_areas cla
  ON cla.id = sla.academic_year_class_learning_area_id
 AND cla.learning_area_id = t.learning_area_id
SET t.academic_year_class_stream_learning_area_id = sla.id
WHERE t.academic_year_class_stream_learning_area_id IS NULL;

-- Convert existing class-level teacher assignments into explicit stream-level
-- assignments. This preserves current access while removing implicit class
-- scope from the canonical teacher path.
INSERT IGNORE INTO academic_year_class_stream_learning_area_teachers
    (academic_year_class_stream_id, academic_year_class_stream_learning_area_id,
     academic_year_term_id, learning_area_id, staff_id, role, status)
SELECT sla.academic_year_class_stream_id, sla.id, old_t.academic_year_term_id,
       cla.learning_area_id, old_t.staff_id, old_t.role, 'active'
FROM academic_year_class_learning_area_teachers old_t
JOIN academic_year_class_learning_areas cla
  ON cla.id = old_t.academic_year_class_learning_area_id
JOIN academic_year_classes ayc
  ON ayc.id = cla.academic_year_class_id
JOIN academic_year_class_streams aycs
  ON aycs.academic_year_class_id = ayc.id
JOIN academic_year_class_stream_learning_areas sla
  ON sla.academic_year_class_stream_id = aycs.id
 AND sla.academic_year_class_learning_area_id = cla.id
WHERE aycs.status IN ('planning','active');

ALTER TABLE timetable_draft_entries
    ADD COLUMN academic_year_class_stream_learning_area_id INT UNSIGNED NULL AFTER academic_year_class_stream_id,
    ADD KEY idx_timetable_draft_stream_learning_area (academic_year_class_stream_learning_area_id),
    ADD CONSTRAINT fk_timetable_draft_stream_learning_area
        FOREIGN KEY (academic_year_class_stream_learning_area_id)
        REFERENCES academic_year_class_stream_learning_areas (id) ON DELETE RESTRICT;

UPDATE timetable_draft_entries t
JOIN academic_year_class_stream_learning_areas sla
  ON sla.academic_year_class_stream_id = t.academic_year_class_stream_id
JOIN academic_year_class_learning_areas cla
  ON cla.id = sla.academic_year_class_learning_area_id
 AND cla.learning_area_id = t.learning_area_id
SET t.academic_year_class_stream_learning_area_id = sla.id
WHERE t.academic_year_class_stream_learning_area_id IS NULL;
