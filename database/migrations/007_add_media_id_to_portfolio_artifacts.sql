-- Link portfolio artifacts to their canonical media_files record so uploaded
-- evidence is tracked per student (media_files.context='students/portfolios',
-- entity_id=student_id) and can be cleaned up alongside the artifact.
ALTER TABLE `portfolio_artifacts`
  ADD COLUMN `media_id` int(11) DEFAULT NULL AFTER `file_path`,
  ADD KEY `idx_artifact_media` (`media_id`),
  ADD KEY `idx_artifact_portfolio` (`portfolio_id`),
  ADD CONSTRAINT `fk_artifact_media` FOREIGN KEY (`media_id`) REFERENCES `media_files` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;
