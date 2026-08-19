-- 069: Admission windows + application target-term backfill
--
-- Makes the admission intake window a first-class, school-admin-controlled
-- concept and repairs existing applications that were created without a
-- target term (the reason they sat stuck at the review stage).

-- ---------------------------------------------------------------------------
-- 1. Window table (idempotent; also safe if 068 already created it).
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `admission_windows` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `academic_year_id` int(10) UNSIGNED NOT NULL,
  `academic_year_term_id` int(10) UNSIGNED DEFAULT NULL,
  `label` varchar(100) NOT NULL,
  `status` enum('open','closed') NOT NULL DEFAULT 'open',
  `accepts_new_applications` tinyint(1) NOT NULL DEFAULT 1,
  `notes` text DEFAULT NULL,
  `opened_by` int(10) UNSIGNED DEFAULT NULL,
  `opened_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `closed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_aw_academic_year_id` (`academic_year_id`),
  KEY `idx_aw_term_id` (`academic_year_term_id`),
  KEY `idx_aw_status` (`status`),
  CONSTRAINT `fk_aw_academic_year` FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_aw_academic_year_term` FOREIGN KEY (`academic_year_term_id`) REFERENCES `academic_year_terms` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='School-admin-configured windows that control which years/terms accept admission applications';

-- ---------------------------------------------------------------------------
-- 2. Unique key so ON DUPLICATE KEY UPDATE below actually deduplicates.
--    Added conditionally because MySQL has no ADD UNIQUE KEY IF NOT EXISTS.
-- ---------------------------------------------------------------------------
SET @uk_exists := (
  SELECT COUNT(*) FROM information_schema.statistics
  WHERE table_schema = DATABASE()
    AND table_name = 'admission_windows'
    AND index_name = 'uk_aw_year_term'
);
SET @uk_ddl := IF(@uk_exists = 0,
  'ALTER TABLE `admission_windows` ADD UNIQUE KEY `uk_aw_year_term` (`academic_year_id`, `academic_year_term_id`)',
  'SELECT 1'
);
PREPARE uk_stmt FROM @uk_ddl;
EXECUTE uk_stmt;
DEALLOCATE PREPARE uk_stmt;

-- ---------------------------------------------------------------------------
-- 3. Seed open windows for the current and upcoming terms. This is the
--    default so an administrator who never touches this screen still has a
--    working intake for the next term.
-- ---------------------------------------------------------------------------
INSERT INTO `admission_windows`
  (`academic_year_id`, `academic_year_term_id`, `label`, `status`, `accepts_new_applications`, `notes`)
SELECT ayt.academic_year_id, ayt.id, CONCAT(t.name, ' ', ay.year_code), 'open', 1,
       'Auto-seeded open intake'
FROM academic_year_terms ayt
JOIN academic_years ay ON ay.id = ayt.academic_year_id
JOIN terms t ON t.id = ayt.term_id
WHERE ayt.status IN ('current', 'upcoming')
ON DUPLICATE KEY UPDATE
  `status` = 'open',
  `accepts_new_applications` = 1;

-- ---------------------------------------------------------------------------
-- 4. Backfill existing active applications that have no target term.
--    Preference order:
--      a. the current term of the matching academic year,
--      b. an upcoming term of the matching academic year,
--      c. the current global open term (final safety net).
-- ---------------------------------------------------------------------------

-- 4a. Current term of the matching academic year.
UPDATE admission_applications aa
JOIN academic_years ay ON ay.year_code LIKE CONCAT('%', aa.academic_year, '%')
JOIN academic_year_terms ayt ON ayt.academic_year_id = ay.id AND ayt.status = 'current'
JOIN admission_windows aw ON aw.academic_year_term_id = ayt.id AND aw.status = 'open'
SET aa.target_term_id = ayt.id
WHERE aa.target_term_id IS NULL
  AND aa.status NOT IN ('enrolled', 'cancelled');

-- 4b. Upcoming term of the matching academic year.
UPDATE admission_applications aa
JOIN academic_years ay ON ay.year_code LIKE CONCAT('%', aa.academic_year, '%')
JOIN academic_year_terms ayt ON ayt.academic_year_id = ay.id AND ayt.status = 'upcoming'
JOIN admission_windows aw ON aw.academic_year_term_id = ayt.id AND aw.status = 'open'
SET aa.target_term_id = ayt.id
WHERE aa.target_term_id IS NULL
  AND aa.status NOT IN ('enrolled', 'cancelled')
ORDER BY ayt.opening_date ASC
LIMIT 1;

-- 4c. Final fallback: any current open term.
UPDATE admission_applications aa
SET aa.target_term_id = (
    SELECT ayt.id
    FROM academic_year_terms ayt
    JOIN admission_windows aw ON aw.academic_year_term_id = ayt.id
       AND aw.status = 'open' AND aw.accepts_new_applications = 1
    WHERE ayt.status = 'current'
    ORDER BY ayt.opening_date ASC
    LIMIT 1
)
WHERE aa.target_term_id IS NULL
  AND aa.status NOT IN ('enrolled', 'cancelled');
