-- Admission windows are the single source of truth for application intake.
SET @has_eligible_grades := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'admission_windows' AND column_name = 'eligible_grades');
SET @sql := IF(@has_eligible_grades = 0, 'ALTER TABLE admission_windows ADD COLUMN eligible_grades TEXT NULL AFTER accepts_new_applications', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @has_default_category := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'admission_windows' AND column_name = 'default_admission_category');
SET @sql := IF(@has_default_category = 0, 'ALTER TABLE admission_windows ADD COLUMN default_admission_category VARCHAR(50) NULL AFTER eligible_grades', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
