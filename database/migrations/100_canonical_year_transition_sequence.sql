-- 100_canonical_year_transition_sequence.sql
-- Replace the legacy six-stage year rollover definition with the complete,
-- resumable 23-stage school transition sequence.

SET @wd_id = (SELECT id FROM workflow_definitions WHERE code = 'academic_year_transition' LIMIT 1);

UPDATE workflow_stages
SET is_active = 0
WHERE workflow_id = @wd_id;

-- The legacy stage rows use a unique (workflow_id, code) key. No transition
-- instance is allowed to be active while this migration runs, so remove the
-- inactive legacy rows and make this migration repeatable.
DELETE FROM workflow_stages
WHERE workflow_id = @wd_id;

UPDATE workflow_definitions
SET name = 'Canonical Academic Year Transition',
    description = 'Controlled, resumable transition from one Kenyan CBC academic year to the next.',
    config_json = JSON_SET(COALESCE(config_json, JSON_OBJECT()), '$.stages', JSON_ARRAY(
        'confirm_current_year', 'create_next_year', 'enter_year_term_dates', 'generate_calendar',
        'configure_classes_streams', 'configure_learning_areas', 'configure_teachers',
        'prepare_fee_structures', 'approve_fee_structures', 'configure_operational_context',
        'current_year_readiness', 'close_current_year_terms', 'review_promotion_candidates',
        'assign_promotion_decisions', 'assign_target_streams', 'create_new_year_enrollments',
        'carry_forward_finances', 'generate_obligations', 'reconcile_balances',
        'migrate_baselines', 'archive_previous_year', 'activate_new_year_term_one',
        'begin_new_year_operations'
    ))
WHERE id = @wd_id;

INSERT INTO workflow_stages
    (workflow_id, code, name, description, sequence, allowed_transitions, action_config, is_active)
VALUES
(@wd_id, 'confirm_current_year', 'Confirm current academic year', 'Verify the outgoing year and its current context.', 1, JSON_ARRAY('create_next_year'), JSON_OBJECT('kind','preflight'), 1),
(@wd_id, 'create_next_year', 'Create/find immediate next year', 'Use the canonical YYYY/YYYY+1 year code.', 2, JSON_ARRAY('enter_year_term_dates'), JSON_OBJECT('kind','setup'), 1),
(@wd_id, 'enter_year_term_dates', 'Enter year and term dates', 'Record year, term and optional half-term dates.', 3, JSON_ARRAY('generate_calendar'), JSON_OBJECT('kind','setup'), 1),
(@wd_id, 'generate_calendar', 'Generate calendar', 'Derive school weeks and calendar days from the approved dates.', 4, JSON_ARRAY('configure_classes_streams'), JSON_OBJECT('kind','automatic'), 1),
(@wd_id, 'configure_classes_streams', 'Configure classes and streams', 'Prepare target classes and administrator-selected streams.', 5, JSON_ARRAY('configure_learning_areas'), JSON_OBJECT('kind','setup'), 1),
(@wd_id, 'configure_learning_areas', 'Configure learning areas, strands, and substrands', 'Prepare CBC curriculum context for the target year.', 6, JSON_ARRAY('configure_teachers'), JSON_OBJECT('kind','setup'), 1),
(@wd_id, 'configure_teachers', 'Configure class and subject teachers', 'Assign target-year teacher context.', 7, JSON_ARRAY('prepare_fee_structures'), JSON_OBJECT('kind','setup'), 1),
(@wd_id, 'prepare_fee_structures', 'Prepare fee structures', 'Copy fee structures as drafts for the target year.', 8, JSON_ARRAY('approve_fee_structures'), JSON_OBJECT('kind','finance'), 1),
(@wd_id, 'approve_fee_structures', 'Review and approve fee structures', 'Approve the target-year fee matrix before billing.', 9, JSON_ARRAY('configure_operational_context'), JSON_OBJECT('kind','finance'), 1),
(@wd_id, 'configure_operational_context', 'Configure events, timetable, transport, boarding, and assessments', 'Complete target-year operational setup.', 10, JSON_ARRAY('current_year_readiness'), JSON_OBJECT('kind','setup'), 1),
(@wd_id, 'current_year_readiness', 'Complete current-year readiness checks', 'Check teaching, attendance, assessment and finance readiness.', 11, JSON_ARRAY('close_current_year_terms'), JSON_OBJECT('kind','preflight'), 1),
(@wd_id, 'close_current_year_terms', 'Close current-year terms', 'Close all outgoing-year terms before cutover.', 12, JSON_ARRAY('review_promotion_candidates'), JSON_OBJECT('kind','controlled'), 1),
(@wd_id, 'review_promotion_candidates', 'Review promotion candidates', 'Review each continuing learner and target class.', 13, JSON_ARRAY('assign_promotion_decisions'), JSON_OBJECT('kind','promotion'), 1),
(@wd_id, 'assign_promotion_decisions', 'Assign promotion decisions', 'Promote, retain, transfer, graduate or otherwise decide each learner.', 14, JSON_ARRAY('assign_target_streams'), JSON_OBJECT('kind','promotion'), 1),
(@wd_id, 'assign_target_streams', 'Assign each learner to a target class and stream', 'Assign in batches and resume later.', 15, JSON_ARRAY('create_new_year_enrollments'), JSON_OBJECT('kind','promotion'), 1),
(@wd_id, 'create_new_year_enrollments', 'Create new-year enrollments', 'Create target-year enrollments without overwriting history.', 16, JSON_ARRAY('carry_forward_finances'), JSON_OBJECT('kind','automatic'), 1),
(@wd_id, 'carry_forward_finances', 'Carry forward arrears, credits, and advance payments', 'Preserve and map old-year financial positions.', 17, JSON_ARRAY('generate_obligations'), JSON_OBJECT('kind','finance'), 1),
(@wd_id, 'generate_obligations', 'Generate new-year obligations', 'Generate current and future term obligations from approved fees.', 18, JSON_ARRAY('reconcile_balances'), JSON_OBJECT('kind','finance'), 1),
(@wd_id, 'reconcile_balances', 'Reconcile balances', 'Validate payments, arrears, credits and advances.', 19, JSON_ARRAY('migrate_baselines'), JSON_OBJECT('kind','finance'), 1),
(@wd_id, 'migrate_baselines', 'Migrate competency baselines', 'Carry CBC competency baselines into the new year.', 20, JSON_ARRAY('archive_previous_year'), JSON_OBJECT('kind','automatic'), 1),
(@wd_id, 'archive_previous_year', 'Archive the previous year', 'Finalize the outgoing-year history and audit record.', 21, JSON_ARRAY('activate_new_year_term_one'), JSON_OBJECT('kind','controlled'), 1),
(@wd_id, 'activate_new_year_term_one', 'Activate the new academic year and Term 1', 'Perform the final controlled cutover.', 22, JSON_ARRAY('begin_new_year_operations'), JSON_OBJECT('kind','controlled'), 1),
(@wd_id, 'begin_new_year_operations', 'Begin new-year operations', 'Open the new-year operating context.', 23, JSON_ARRAY(), JSON_OBJECT('kind','completion'), 1);
