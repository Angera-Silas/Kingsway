-- Classify and automatically reconcile legacy planning records only when the
-- database proves there is exactly one possible canonical context.
ALTER TABLE academic_content_reconciliation_queue
    MODIFY status ENUM('open','manual_required','legacy_unresolved','auto_reconciled','resolved','ignored') NOT NULL DEFAULT 'open',
    ADD COLUMN IF NOT EXISTS resolution_type ENUM('automatic','manual','quarantine') NULL AFTER reason,
    ADD COLUMN IF NOT EXISTS candidate_count INT UNSIGNED NOT NULL DEFAULT 0 AFTER resolution_type;

-- Candidate stream-learning-area contexts for legacy schemes. A candidate must
-- retain the original class-learning-area identity and teacher assignment.
UPDATE schemes_of_work sw
JOIN (
    SELECT swx.id AS scheme_id, MIN(aysla.id) AS canonical_id, COUNT(DISTINCT aysla.id) AS candidates
    FROM schemes_of_work swx
    JOIN academic_year_class_learning_areas aycla ON aycla.id = swx.academic_year_class_learning_area_id
    JOIN academic_year_classes ayc ON ayc.id = aycla.academic_year_class_id
    JOIN academic_year_class_stream_learning_areas aysla
      ON aysla.academic_year_class_learning_area_id = aycla.id AND aysla.status = 'active'
    JOIN academic_year_class_streams aycs ON aycs.id = aysla.academic_year_class_stream_id AND aycs.status = 'active'
    JOIN vw_teacher_effective_stream_learning_areas ts
      ON ts.academic_year_class_stream_learning_area_id = aysla.id AND ts.staff_id = swx.teacher_id
    JOIN academic_year_calendar ac ON ac.id = swx.academic_year_calendar_week_id
    WHERE swx.academic_year_class_stream_learning_area_id IS NULL
    GROUP BY swx.id
    HAVING candidates = 1
) candidate ON candidate.scheme_id = sw.id
SET sw.academic_year_class_stream_learning_area_id = candidate.canonical_id
WHERE sw.academic_year_class_stream_learning_area_id IS NULL;

UPDATE academic_content_reconciliation_queue q
JOIN schemes_of_work sw ON q.content_type='scheme_of_work' AND q.content_id=sw.id
SET q.status='auto_reconciled', q.resolution_type='automatic', q.candidate_count=1, q.resolved_at=NOW()
WHERE sw.academic_year_class_stream_learning_area_id IS NOT NULL;

-- Records with a valid class-level identity but multiple possible streams need
-- an administrator decision. Everything else remains quarantined.
UPDATE academic_content_reconciliation_queue q
JOIN schemes_of_work sw ON q.content_type='scheme_of_work' AND q.content_id=sw.id
LEFT JOIN academic_year_class_learning_areas aycla ON aycla.id=sw.academic_year_class_learning_area_id
LEFT JOIN (
    SELECT swx.id, COUNT(DISTINCT aysla.id) AS candidates
    FROM schemes_of_work swx
    JOIN academic_year_class_learning_areas x ON x.id=swx.academic_year_class_learning_area_id
    JOIN academic_year_class_stream_learning_areas aysla ON aysla.academic_year_class_learning_area_id=x.id AND aysla.status='active'
    JOIN vw_teacher_effective_stream_learning_areas ts ON ts.academic_year_class_stream_learning_area_id=aysla.id AND ts.staff_id=swx.teacher_id
    WHERE swx.academic_year_class_stream_learning_area_id IS NULL GROUP BY swx.id
) candidates ON candidates.id=sw.id
SET q.status=CASE WHEN COALESCE(candidates.candidates,0)>1 THEN 'manual_required' ELSE 'legacy_unresolved' END,
    q.resolution_type=CASE WHEN COALESCE(candidates.candidates,0)>1 THEN 'manual' ELSE 'quarantine' END,
    q.candidate_count=COALESCE(candidates.candidates,0)
WHERE q.status='open';

UPDATE academic_content_reconciliation_queue
SET status='legacy_unresolved', resolution_type='quarantine'
WHERE content_type='lesson_plan' AND status='open';
