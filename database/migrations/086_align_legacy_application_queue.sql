-- Legacy data correction only. The original test application was created
-- before document capture was moved into the application submission stage.
-- Keep it in the received queue rather than presenting an admin upload action.

UPDATE workflow_instances wi
JOIN admission_applications aa ON aa.id = wi.reference_id
SET wi.current_stage = 'application_received',
    wi.stage_code = 'application_received',
    wi.data_json = JSON_SET(COALESCE(wi.data_json, '{}'), '$.legacy_application_aligned', true)
WHERE wi.reference_type = 'admission_application'
  AND aa.application_no = 'ADM/2026/001'
  AND wi.current_stage = 'application_applied';

INSERT INTO workflow_stage_history
    (instance_id, stage_code, from_stage, to_stage, action_taken, processed_by, remarks, data_json)
SELECT wi.id, 'application_received', 'application_applied', 'application_received',
       'legacy_application_alignment', 1,
       'Legacy application retained in the received queue; administration does not upload application documents.',
       JSON_OBJECT('legacy_application_aligned', true)
FROM workflow_instances wi
JOIN admission_applications aa ON aa.id = wi.reference_id
WHERE wi.reference_type = 'admission_application'
  AND aa.application_no = 'ADM/2026/001'
  AND wi.current_stage = 'application_received'
  AND NOT EXISTS (
      SELECT 1 FROM workflow_stage_history h
      WHERE h.instance_id = wi.id AND h.action_taken = 'legacy_application_alignment'
  );
