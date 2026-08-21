-- 013_seed_student_promotion_workflow.sql
-- Seeds the student_promotion workflow definition + stages so the CBC-compliant
-- promotion flow (defineCriteria -> identifyCandidates -> validateEligibility
-- -> executePromotion -> generateReports) can run through the workflow engine.
-- The handler (StudentPromotionWorkflow) requires this definition to exist before
-- startWorkflow() can resolve a starting stage.
-- Idempotent: skips inserts when the definition/stage codes already exist.

-- Workflow definition
INSERT INTO workflow_definitions (code, name, description, category, handler_class, config_json, is_active)
SELECT 'student_promotion',
       'Student Promotion',
       'CBC-compliant end-of-year promotion: define criteria, identify candidates, validate eligibility (scores/attendance/competency), execute promotions via stored procedures, and generate reports.',
       'academic',
       'App\\API\\Modules\\academic\\StudentPromotionWorkflow',
       JSON_OBJECT('stages', JSON_ARRAY('define_criteria','identify_candidates','validate_eligibility','execute_promotion','generate_reports')),
       1
WHERE NOT EXISTS (
    SELECT 1 FROM workflow_definitions WHERE code = 'student_promotion'
);

SET @wd_id = (SELECT id FROM workflow_definitions WHERE code = 'student_promotion' LIMIT 1);

-- Stages (idempotent per (workflow_id, code))
INSERT INTO workflow_stages (workflow_id, code, name, description, sequence, allowed_transitions, action_config, is_active)
SELECT @wd_id, 'define_criteria', 'Define Criteria',
       'Set promotion rules, thresholds and the source/target grades; create the promotion batch.',
       1, JSON_ARRAY('identify_candidates'), NULL, 1
WHERE NOT EXISTS (SELECT 1 FROM workflow_stages WHERE workflow_id = @wd_id AND code = 'define_criteria');

INSERT INTO workflow_stages (workflow_id, code, name, description, sequence, allowed_transitions, action_config, is_active)
SELECT @wd_id, 'identify_candidates', 'Identify Candidates',
       'Query eligible students for the grade/class/stream scope of the batch.',
       2, JSON_ARRAY('validate_eligibility'), NULL, 1
WHERE NOT EXISTS (SELECT 1 FROM workflow_stages WHERE workflow_id = @wd_id AND code = 'identify_candidates');

INSERT INTO workflow_stages (workflow_id, code, name, description, sequence, allowed_transitions, action_config, is_active)
SELECT @wd_id, 'validate_eligibility', 'Validate Eligibility',
       'Check academic performance, attendance and CBC competencies; flag retentions.',
       3, JSON_ARRAY('execute_promotion'), NULL, 1
WHERE NOT EXISTS (SELECT 1 FROM workflow_stages WHERE workflow_id = @wd_id AND code = 'validate_eligibility');

INSERT INTO workflow_stages (workflow_id, code, name, description, sequence, allowed_transitions, action_config, is_active)
SELECT @wd_id, 'execute_promotion', 'Execute Promotion',
       'Apply approved promotions, retentions and graduations; create new-year enrollments.',
       4, JSON_ARRAY('generate_reports'), NULL, 1
WHERE NOT EXISTS (SELECT 1 FROM workflow_stages WHERE workflow_id = @wd_id AND code = 'execute_promotion');

INSERT INTO workflow_stages (workflow_id, code, name, description, sequence, allowed_transitions, action_config, is_active)
SELECT @wd_id, 'generate_reports', 'Generate Reports',
       'Compile promotion statistics, generate reports and notify stakeholders.',
       5, JSON_ARRAY(), NULL, 1
WHERE NOT EXISTS (SELECT 1 FROM workflow_stages WHERE workflow_id = @wd_id AND code = 'generate_reports');
