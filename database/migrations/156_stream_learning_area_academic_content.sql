-- Academic content must be owned by a stream-learning-area. Historical
-- class-level records remain nullable for audit/read compatibility and are not
-- accepted by new create/update workflows.
ALTER TABLE lesson_plans
    ADD COLUMN academic_year_class_stream_learning_area_id INT UNSIGNED NULL AFTER academic_year_class_learning_area_id,
    ADD KEY idx_lesson_plans_stream_learning_area (academic_year_class_stream_learning_area_id),
    ADD CONSTRAINT fk_lesson_plans_stream_learning_area
        FOREIGN KEY (academic_year_class_stream_learning_area_id)
        REFERENCES academic_year_class_stream_learning_areas (id) ON DELETE RESTRICT;

ALTER TABLE schemes_of_work
    ADD COLUMN academic_year_class_stream_learning_area_id INT UNSIGNED NULL AFTER academic_year_class_learning_area_id,
    ADD KEY idx_schemes_stream_learning_area (academic_year_class_stream_learning_area_id),
    ADD CONSTRAINT fk_schemes_stream_learning_area
        FOREIGN KEY (academic_year_class_stream_learning_area_id)
        REFERENCES academic_year_class_stream_learning_areas (id) ON DELETE RESTRICT;
