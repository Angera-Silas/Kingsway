-- Session applicability is data, not a grade check in PHP.
CREATE TABLE IF NOT EXISTS attendance_session_class_rules (
    session_id INT UNSIGNED NOT NULL,
    class_id INT UNSIGNED NOT NULL,
    enabled TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (session_id, class_id),
    KEY idx_attendance_session_class_rules_class (class_id, enabled)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Morning/daytime attendance applies to every CBC class currently configured.
INSERT IGNORE INTO attendance_session_class_rules (session_id, class_id)
SELECT 1, id FROM classes WHERE code IN ('PLAYGROUP','PP1','PP2','GRADE1','GRADE2','GRADE3','GRADE4','GRADE5','GRADE6','GRADE7','GRADE8','GRADE9');

-- Afternoon attendance is only for Grade 4 through Grade 9.
INSERT IGNORE INTO attendance_session_class_rules (session_id, class_id)
SELECT 2, id FROM classes WHERE code IN ('GRADE4','GRADE5','GRADE6','GRADE7','GRADE8','GRADE9');

-- Saturday class applicability remains explicitly configurable; the current
-- school setup has a Saturday session for all CBC classes when enabled.
INSERT IGNORE INTO attendance_session_class_rules (session_id, class_id)
SELECT 3, id FROM classes WHERE code IN ('PLAYGROUP','PP1','PP2','GRADE1','GRADE2','GRADE3','GRADE4','GRADE5','GRADE6','GRADE7','GRADE8','GRADE9');
