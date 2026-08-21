-- 099_unify_academic_year_transition_workflow.sql
-- The visible year-transition page is the authoritative rollover path.
-- Preparation happens before promotion; archive/cutover happens last.

ALTER TABLE academic_year_fee_schedules
    MODIFY status ENUM('active','inactive','cancelled','draft','pending_review') NOT NULL DEFAULT 'active';

SET @wd_id = (SELECT id FROM workflow_definitions WHERE code = 'academic_year_transition' LIMIT 1);

UPDATE workflow_definitions
SET config_json = JSON_SET(
    COALESCE(config_json, JSON_OBJECT()), '$.stages',
    JSON_ARRAY('prepare_calendar','setup_new_year','execute_promotions',
               'migrate_baselines','archive_data','validate_readiness')
)
WHERE id = @wd_id;

UPDATE workflow_stages SET sequence = 1, allowed_transitions = JSON_ARRAY('setup_new_year')
WHERE workflow_id = @wd_id AND code = 'prepare_calendar';
UPDATE workflow_stages SET sequence = 2, allowed_transitions = JSON_ARRAY('execute_promotions')
WHERE workflow_id = @wd_id AND code = 'setup_new_year';
UPDATE workflow_stages SET sequence = 3, allowed_transitions = JSON_ARRAY('migrate_baselines')
WHERE workflow_id = @wd_id AND code = 'execute_promotions';
UPDATE workflow_stages SET sequence = 4, allowed_transitions = JSON_ARRAY('archive_data')
WHERE workflow_id = @wd_id AND code = 'migrate_baselines';
UPDATE workflow_stages SET sequence = 5, allowed_transitions = JSON_ARRAY('validate_readiness')
WHERE workflow_id = @wd_id AND code = 'archive_data';
UPDATE workflow_stages SET sequence = 6, allowed_transitions = JSON_ARRAY()
WHERE workflow_id = @wd_id AND code = 'validate_readiness';
