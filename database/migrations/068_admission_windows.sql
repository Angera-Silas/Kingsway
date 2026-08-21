-- 068: Admission Windows
-- Lets the school admin control which academic years and terms accept new applications.
-- An "open" window means applications are accepted for that year/term combination.

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

-- Seed: open the current term (Term 3, 2026/2027) by default
INSERT INTO `admission_windows` (`academic_year_id`, `academic_year_term_id`, `label`, `status`, `accepts_new_applications`, `notes`)
SELECT ay.id, ayt.id, CONCAT(t.name, ' ', ay.year_code), 'open', 1, 'Auto-seeded: current term'
FROM academic_year_terms ayt
JOIN academic_years ay ON ay.id = ayt.academic_year_id
JOIN terms t ON t.id = ayt.term_id
WHERE ayt.status = 'current'
ON DUPLICATE KEY UPDATE `admission_windows`.`status` = `admission_windows`.`status`;
