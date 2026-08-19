/* Planned admissions intake periods and their school-calendar event. */
SET @has_open_at := (
    SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = 'admission_windows' AND column_name = 'application_open_at'
);
SET @ddl_open_at := IF(@has_open_at = 0,
    'ALTER TABLE admission_windows ADD COLUMN application_open_at DATETIME NULL AFTER accepts_new_applications',
    'SELECT 1');
PREPARE stmt_open_at FROM @ddl_open_at; EXECUTE stmt_open_at; DEALLOCATE PREPARE stmt_open_at;

SET @has_close_at := (
    SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = 'admission_windows' AND column_name = 'application_close_at'
);
SET @ddl_close_at := IF(@has_close_at = 0,
    'ALTER TABLE admission_windows ADD COLUMN application_close_at DATETIME NULL AFTER application_open_at',
    'SELECT 1');
PREPARE stmt_close_at FROM @ddl_close_at; EXECUTE stmt_close_at; DEALLOCATE PREPARE stmt_close_at;

SET @has_event_id := (
    SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = 'admission_windows' AND column_name = 'calendar_event_id'
);
SET @ddl_event_id := IF(@has_event_id = 0,
    'ALTER TABLE admission_windows ADD COLUMN calendar_event_id INT UNSIGNED NULL AFTER application_close_at',
    'SELECT 1');
PREPARE stmt_event_id FROM @ddl_event_id; EXECUTE stmt_event_id; DEALLOCATE PREPARE stmt_event_id;

SET @has_window_open_idx := (
    SELECT COUNT(*) FROM information_schema.statistics
    WHERE table_schema = DATABASE() AND table_name = 'admission_windows' AND index_name = 'idx_aw_schedule'
);
SET @ddl_window_open_idx := IF(@has_window_open_idx = 0,
    'ALTER TABLE admission_windows ADD KEY idx_aw_schedule (status, accepts_new_applications, application_open_at, application_close_at)',
    'SELECT 1');
PREPARE stmt_window_open_idx FROM @ddl_window_open_idx; EXECUTE stmt_window_open_idx; DEALLOCATE PREPARE stmt_window_open_idx;

SET @has_year_term_unique := (
    SELECT COUNT(*) FROM information_schema.statistics
    WHERE table_schema = DATABASE() AND table_name = 'admission_windows' AND index_name = 'uk_aw_year_term'
);
SET @ddl_year_term_unique := IF(@has_year_term_unique = 0,
    'ALTER TABLE admission_windows ADD UNIQUE KEY uk_aw_year_term (academic_year_id, academic_year_term_id)',
    'SELECT 1');
PREPARE stmt_year_term_unique FROM @ddl_year_term_unique; EXECUTE stmt_year_term_unique; DEALLOCATE PREPARE stmt_year_term_unique;

SET @has_event_fk := (
    SELECT COUNT(*) FROM information_schema.table_constraints
    WHERE table_schema = DATABASE() AND table_name = 'admission_windows' AND constraint_name = 'fk_aw_calendar_event'
);
SET @ddl_event_fk := IF(@has_event_fk = 0,
    'ALTER TABLE admission_windows ADD CONSTRAINT fk_aw_calendar_event FOREIGN KEY (calendar_event_id) REFERENCES school_events(id) ON DELETE SET NULL',
    'SELECT 1');
PREPARE stmt_event_fk FROM @ddl_event_fk; EXECUTE stmt_event_fk; DEALLOCATE PREPARE stmt_event_fk;
