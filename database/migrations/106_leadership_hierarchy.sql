-- Leadership hierarchy: replace flat `leadership_team` with normalized 4NF
-- structure that models the school's actual organisational levels.
--
-- Levels:  BOM → Administration → Department Heads → Teaching Staff → Student Leadership
-- Every entry is tied to an academic_year — in 2050 you can query 2026's leaders.
-- External BOM members get a persons record but no users record (no system login).

-- ═══════════════════════════════════════════════════════════════════════════
-- 1. LEADERSHIP LEVELS
-- ═══════════════════════════════════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS leadership_levels (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name            VARCHAR(100) NOT NULL,
  description     TEXT         DEFAULT NULL,
  display_order   INT          NOT NULL DEFAULT 0,
  is_active       TINYINT(1)   NOT NULL DEFAULT 1,
  created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_level_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ═══════════════════════════════════════════════════════════════════════════
-- 2. LEADERSHIP POSITIONS (roles within each level)
-- ═══════════════════════════════════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS leadership_positions (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  level_id        INT UNSIGNED NOT NULL,
  name            VARCHAR(150) NOT NULL,
  display_order   INT          NOT NULL DEFAULT 0,
  is_active       TINYINT(1)   NOT NULL DEFAULT 1,
  created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_level (level_id),
  CONSTRAINT fk_pos_level FOREIGN KEY (level_id) REFERENCES leadership_levels(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ═══════════════════════════════════════════════════════════════════════════
-- 3. SCHOOL LEADERSHIP (who holds which position — per academic year)
-- ═══════════════════════════════════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS school_leadership (
  id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  academic_year_id  INT UNSIGNED NOT NULL COMMENT 'FK to academic_years — each year has its own leaders',
  position_id       INT UNSIGNED NOT NULL,
  person_id         INT UNSIGNED DEFAULT NULL COMMENT 'FK to persons (always set)',
  staff_id          INT UNSIGNED DEFAULT NULL COMMENT 'FK to staff (null if not staff)',
  student_id        INT UNSIGNED DEFAULT NULL COMMENT 'FK to students (null if not student)',
  public_photo_url  VARCHAR(500) DEFAULT NULL COMMENT 'Admin-uploaded photo for THIS specific role/section',
  public_bio        TEXT         DEFAULT NULL COMMENT 'Role-specific bio',
  display_order     INT          NOT NULL DEFAULT 0,
  is_active         TINYINT(1)   NOT NULL DEFAULT 1,
  created_at        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_academic_year (academic_year_id),
  KEY idx_position (position_id),
  KEY idx_person (person_id),
  KEY idx_staff (staff_id),
  KEY idx_student (student_id),
  CONSTRAINT fk_sl_year     FOREIGN KEY (academic_year_id) REFERENCES academic_years(id) ON DELETE CASCADE,
  CONSTRAINT fk_sl_position FOREIGN KEY (position_id)      REFERENCES leadership_positions(id) ON DELETE CASCADE,
  CONSTRAINT fk_sl_person   FOREIGN KEY (person_id)        REFERENCES persons(id) ON DELETE SET NULL,
  CONSTRAINT fk_sl_staff    FOREIGN KEY (staff_id)         REFERENCES staff(id)   ON DELETE SET NULL,
  CONSTRAINT fk_sl_student  FOREIGN KEY (student_id)       REFERENCES students(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ═══════════════════════════════════════════════════════════════════════════
-- 4. SEED DATA
-- ═══════════════════════════════════════════════════════════════════════════

-- Levels
INSERT INTO leadership_levels (id, name, description, display_order) VALUES
(1, 'Board of Management', 'Strategic governance — BOM Chairperson, Secretary, Treasurer, Board Members, PTA reps.', 10),
(2, 'School Administration', 'Operational leadership — Directors, Headteacher, Deputies, Admin, Accountant.', 20),
(3, 'Department Heads', 'Heads of department managing specific functional areas.', 30),
(4, 'Teaching Staff', 'Subject teachers, class teachers — the academic backbone.', 40),
(5, 'Student Leadership', 'Student leaders — School President, Deputy, Cabinet.', 50);

-- BOM Positions
INSERT INTO leadership_positions (level_id, name, display_order) VALUES
(1, 'BOM Chairperson', 10), (1, 'BOM Vice Chairperson', 20), (1, 'BOM Secretary', 30),
(1, 'BOM Treasurer', 40), (1, 'BOM Member', 50), (1, 'PTA Representative', 60);

-- Administration Positions
INSERT INTO leadership_positions (level_id, name, display_order) VALUES
(2, 'Director', 10), (2, 'Headteacher', 20), (2, 'Deputy Head - Academic', 30),
(2, 'Deputy Head - Discipline', 40), (2, 'School Administrator', 50), (2, 'Accountant / Bursar', 60);

-- Unified positions
INSERT INTO leadership_positions (level_id, name, display_order) VALUES
(4, 'Teaching Staff', 10),
(5, 'School President', 10), (5, 'Deputy School President', 20), (5, 'Cabinet Member', 30);

-- Per-department HOD positions
INSERT INTO leadership_positions (level_id, name, display_order) VALUES
(3, 'HOD Academics', 10), (3, 'HOD Transport', 20), (3, 'HOD Food & Nutrition', 30),
(3, 'HOD Administration', 40), (3, 'HOD Games & Sports', 50), (3, 'HOD Student Welfare', 60),
(3, 'HOD Talent Development', 70), (3, 'HOD Boarding', 80), (3, 'HOD Admissions', 90), (3, 'HOD Finance', 100);
