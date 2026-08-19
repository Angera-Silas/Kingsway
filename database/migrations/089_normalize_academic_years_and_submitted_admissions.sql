-- Standardize academic-year labels and finalize submitted admission records.
-- year_code and year_name always use YYYY/YYYY+1 derived from start_date.

UPDATE academic_years
SET year_code = CONCAT(YEAR(start_date), '/', YEAR(start_date) + 1),
    year_name = CONCAT(YEAR(start_date), '/', YEAR(start_date) + 1)
WHERE start_date IS NOT NULL;

INSERT INTO workflow_stage_history
    (instance_id, stage_code, from_stage, to_stage, action_taken, processed_by, remarks, data_json)
SELECT wi.id, 'application_received', 'application_applied', 'application_received',
       'legacy_submitted_application_received', 1,
       'Legacy submitted application normalized to Application Received; source channel does not change workflow stage.',
       '{"legacy_normalization":true}'
FROM workflow_instances wi
JOIN admission_applications aa
  ON aa.id = wi.reference_id
WHERE wi.reference_type = 'admission_application'
  AND wi.current_stage = 'application_applied'
  AND aa.status = 'submitted';

UPDATE workflow_instances wi
JOIN admission_applications aa
  ON aa.id = wi.reference_id
SET wi.current_stage = 'application_received',
    wi.stage_code = 'application_received'
WHERE wi.reference_type = 'admission_application'
  AND wi.current_stage = 'application_applied'
  AND aa.status = 'submitted';
