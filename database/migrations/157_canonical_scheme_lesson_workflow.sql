-- Canonical CBC planning workflow.
-- Existing legacy lesson plans remain readable and nullable for reconciliation;
-- all newly-created lesson plans must reference an approved scheme row.

SET @has_scheme_column := (
    SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'lesson_plans'
      AND column_name = 'scheme_of_work_id'
);
SET @sql := IF(@has_scheme_column = 0,
    'ALTER TABLE lesson_plans ADD COLUMN scheme_of_work_id INT UNSIGNED NULL AFTER lesson_template_id',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_scheme_index := (
    SELECT COUNT(*) FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'lesson_plans'
      AND index_name = 'idx_lesson_plans_scheme_of_work'
);
SET @sql := IF(@has_scheme_index = 0,
    'ALTER TABLE lesson_plans ADD KEY idx_lesson_plans_scheme_of_work (scheme_of_work_id)',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_scheme_fk := (
    SELECT COUNT(*) FROM information_schema.table_constraints
    WHERE constraint_schema = DATABASE()
      AND table_name = 'lesson_plans'
      AND constraint_name = 'fk_lesson_plans_scheme_of_work'
);
SET @sql := IF(@has_scheme_fk = 0,
    'ALTER TABLE lesson_plans ADD CONSTRAINT fk_lesson_plans_scheme_of_work FOREIGN KEY (scheme_of_work_id) REFERENCES schemes_of_work(id)',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- One teacher cannot create two plans for the same approved scheme row/day.
SET @has_unique := (
    SELECT COUNT(*) FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'lesson_plans'
      AND index_name = 'uk_lesson_scheme_day'
);
SET @sql := IF(@has_unique = 0,
    'ALTER TABLE lesson_plans ADD UNIQUE KEY uk_lesson_scheme_day (scheme_of_work_id, academic_year_calendar_day_id)',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
