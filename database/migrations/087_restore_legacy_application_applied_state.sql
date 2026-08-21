-- Keep the incomplete legacy test application at the initial application
-- stage. Documents are submitted with the application; the admin queue must
-- never present document upload as its next action.

UPDATE workflow_instances wi
JOIN admission_applications aa ON aa.id = wi.reference_id
SET wi.current_stage = 'application_applied',
    wi.stage_code = 'application_applied',
    wi.data_json = JSON_SET(COALESCE(wi.data_json, '{}'), '$.legacy_application_aligned', true)
WHERE wi.reference_type = 'admission_application'
  AND aa.application_no = 'ADM/2026/001'
  AND wi.current_stage = 'application_received';

INSERT INTO workflow_stage_history
    (instance_id, stage_code, from_stage, to_stage, action_taken, processed_by, remarks, data_json)
SELECT wi.id, 'application_applied', 'application_received', 'application_applied',
       'legacy_application_incomplete_alignment', 1,
       'Legacy application has no submitted documents; retained at application applied without an administration upload action.',
       JSON_OBJECT('legacy_application_aligned', true)
FROM workflow_instances wi
JOIN admission_applications aa ON aa.id = wi.reference_id
WHERE wi.reference_type = 'admission_application'
  AND aa.application_no = 'ADM/2026/001'
  AND wi.current_stage = 'application_applied'
  AND NOT EXISTS (
      SELECT 1 FROM workflow_stage_history h
      WHERE h.instance_id = wi.id AND h.action_taken = 'legacy_application_incomplete_alignment'
  );
