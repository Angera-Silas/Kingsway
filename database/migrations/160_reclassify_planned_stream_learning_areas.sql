-- `planned` is a valid configured stream-learning-area state. It means the
-- academic structure is prepared, but not yet marked operationally active.
-- Re-run legacy candidate classification using every non-skipped state.
UPDATE academic_content_reconciliation_queue q
JOIN schemes_of_work sw ON q.content_type='scheme_of_work' AND q.content_id=sw.id
JOIN (
    SELECT swx.id, MIN(aysla.id) AS canonical_id, COUNT(DISTINCT aysla.id) AS candidates
    FROM schemes_of_work swx
    JOIN academic_year_class_learning_areas aycla ON aycla.id=swx.academic_year_class_learning_area_id
    JOIN academic_year_class_stream_learning_areas aysla ON aysla.academic_year_class_learning_area_id=aycla.id AND aysla.status <> 'skipped'
    JOIN vw_teacher_effective_stream_learning_areas ts ON ts.academic_year_class_stream_learning_area_id=aysla.id AND ts.staff_id=swx.teacher_id
    WHERE swx.academic_year_class_stream_learning_area_id IS NULL
    GROUP BY swx.id
) candidates ON candidates.id=sw.id
SET q.candidate_count=candidates.candidates,
    q.status=CASE WHEN candidates.candidates=1 THEN 'auto_reconciled' WHEN candidates.candidates>1 THEN 'manual_required' ELSE 'legacy_unresolved' END,
    q.resolution_type=CASE WHEN candidates.candidates=1 THEN 'automatic' WHEN candidates.candidates>1 THEN 'manual' ELSE 'quarantine' END,
    q.resolved_at=CASE WHEN candidates.candidates=1 THEN NOW() ELSE q.resolved_at END;

UPDATE schemes_of_work sw
JOIN (
    SELECT swx.id, MIN(aysla.id) AS canonical_id
    FROM schemes_of_work swx
    JOIN academic_year_class_learning_areas aycla ON aycla.id=swx.academic_year_class_learning_area_id
    JOIN academic_year_class_stream_learning_areas aysla ON aysla.academic_year_class_learning_area_id=aycla.id AND aysla.status <> 'skipped'
    JOIN vw_teacher_effective_stream_learning_areas ts ON ts.academic_year_class_stream_learning_area_id=aysla.id AND ts.staff_id=swx.teacher_id
    WHERE swx.academic_year_class_stream_learning_area_id IS NULL
    GROUP BY swx.id HAVING COUNT(DISTINCT aysla.id)=1
) candidates ON candidates.id=sw.id
SET sw.academic_year_class_stream_learning_area_id=candidates.canonical_id
WHERE sw.academic_year_class_stream_learning_area_id IS NULL;
