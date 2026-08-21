-- 011_seed_academic_year_transition_workflow.sql
-- Seeds the academic_year_transition workflow definition + stages so the
-- year-to-year automation (prepareCalendar -> archiveData -> setupNewYear
-- -> executePromotions -> migrateBaselines -> validateReadiness) can actually run.
-- Structure (classes/streams/learning areas) must exist BEFORE promotions so
-- promoted students resolve to real target-year enrollments.
-- Idempotent: skips inserts when the definition/stage codes already exist.

-- Workflow definition
INSERT INTO workflow_definitions (code, name, description, category, handler_class, config_json, is_active)
SELECT 'academic_year_transition',
       'Academic Year Transition',
       'Automates the roll-over from one academic year to the next: calendar generation, data archival, promotions, new-year class/stream setup, competency migration and readiness validation.',
       'academic',
       'App\\API\\Modules\\academic\\AcademicYearTransitionWorkflow',
       JSON_OBJECT('stages', JSON_ARRAY('prepare_calendar','archive_data','setup_new_year','execute_promotions','migrate_baselines','validate_readiness')),
       1
WHERE NOT EXISTS (
    SELECT 1 FROM workflow_definitions WHERE code = 'academic_year_transition'
);

SET @wd_id = (SELECT id FROM workflow_definitions WHERE code = 'academic_year_transition' LIMIT 1);

-- Stages (idempotent per (workflow_id, code))
INSERT INTO workflow_stages (workflow_id, code, name, description, sequence, allowed_transitions, action_config, is_active)
SELECT @wd_id, 'prepare_calendar', 'Prepare Calendar',
       'Create the new academic year, its three terms and the auto-generated term calendar.',
       1, JSON_ARRAY('archive_data'), NULL, 1
WHERE NOT EXISTS (SELECT 1 FROM workflow_stages WHERE workflow_id = @wd_id AND code = 'prepare_calendar');

INSERT INTO workflow_stages (workflow_id, code, name, description, sequence, allowed_transitions, action_config, is_active)
SELECT @wd_id, 'archive_data', 'Archive Previous Year Data',
       'Archive assessment results, reports and competencies for the outgoing year.',
       2, JSON_ARRAY('setup_new_year'), NULL, 1
WHERE NOT EXISTS (SELECT 1 FROM workflow_stages WHERE workflow_id = @wd_id AND code = 'archive_data');

INSERT INTO workflow_stages (workflow_id, code, name, description, sequence, allowed_transitions, action_config, is_active)
SELECT @wd_id, 'setup_new_year', 'Setup New Year',
       'Create the new year class/stream structure (auto-clone one grade ahead, with stream rebalancing support).',
       3, JSON_ARRAY('execute_promotions'), NULL, 1
WHERE NOT EXISTS (SELECT 1 FROM workflow_stages WHERE workflow_id = @wd_id AND code = 'setup_new_year');

INSERT INTO workflow_stages (workflow_id, code, name, description, sequence, allowed_transitions, action_config, is_active)
SELECT @wd_id, 'execute_promotions', 'Execute Promotions',
       'Promote students to the next class/stream via the academic class progression ladder.',
       4, JSON_ARRAY('migrate_baselines'), NULL, 1
WHERE NOT EXISTS (SELECT 1 FROM workflow_stages WHERE workflow_id = @wd_id AND code = 'execute_promotions');

INSERT INTO workflow_stages (workflow_id, code, name, description, sequence, allowed_transitions, action_config, is_active)
SELECT @wd_id, 'migrate_baselines', 'Migrate Competency Baselines',
       'Carry forward learner competency baselines for continued CBC tracking.',
       5, JSON_ARRAY('validate_readiness'), NULL, 1
WHERE NOT EXISTS (SELECT 1 FROM workflow_stages WHERE workflow_id = @wd_id AND code = 'migrate_baselines');

INSERT INTO workflow_stages (workflow_id, code, name, description, sequence, allowed_transitions, action_config, is_active)
SELECT @wd_id, 'validate_readiness', 'Validate Readiness',
       'Final checks before the new year goes live.',
       6, JSON_ARRAY(), NULL, 1
WHERE NOT EXISTS (SELECT 1 FROM workflow_stages WHERE workflow_id = @wd_id AND code = 'validate_readiness');
