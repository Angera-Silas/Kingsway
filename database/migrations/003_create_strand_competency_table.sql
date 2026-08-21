CREATE TABLE IF NOT EXISTS `strand_competency` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `strand_id` int(10) UNSIGNED NOT NULL,
  `competency_id` int(10) UNSIGNED NOT NULL,
  `weight` decimal(5,2) NOT NULL DEFAULT 1.00,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_strand_competency` (`strand_id`, `competency_id`),
  KEY `idx_sc_strand` (`strand_id`),
  KEY `idx_sc_competency` (`competency_id`),
  CONSTRAINT `fk_sc_strand` FOREIGN KEY (`strand_id`) REFERENCES `strands` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_sc_competency` FOREIGN KEY (`competency_id`) REFERENCES `core_competencies` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
