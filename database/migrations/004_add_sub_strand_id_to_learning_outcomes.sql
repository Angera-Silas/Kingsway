ALTER TABLE `learning_outcomes`
  ADD COLUMN `sub_strand_id` int(10) UNSIGNED DEFAULT NULL AFTER `learning_area_id`,
  ADD KEY `idx_lo_sub_strand` (`sub_strand_id`),
  ADD CONSTRAINT `fk_lo_sub_strand` FOREIGN KEY (`sub_strand_id`) REFERENCES `sub_strands` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;
