-- Canonical admissions workflow. Legacy admission stage rows remain only as
-- historical records and are not active workflow stages.

UPDATE workflow_definitions
SET description = 'Canonical CBC admissions: Application Applied -> Application Received -> Reviewed and Approved -> Grade 4-9 Interview -> Student Admission Number -> Class/Stream Placement -> Fees/Transport/Uniform Payments -> ID Generation -> Final Enrollment'
WHERE id = 102;

UPDATE workflow_stages SET is_active = 0 WHERE workflow_id = 102;

UPDATE workflow_stages SET name = 'Application Applied', required_role = 'school_administrator', sequence = 1,
    allowed_transitions = '["application_received", "rejected"]', is_active = 1
WHERE workflow_id = 102 AND code = 'application_applied';
UPDATE workflow_stages SET name = 'Application Received', required_role = 'school_administrator', sequence = 2,
    allowed_transitions = '["application_review", "rejected"]', is_active = 1
WHERE workflow_id = 102 AND code = 'application_received';
UPDATE workflow_stages SET name = 'Reviewed and Approved', required_role = 'school_administrator', sequence = 3,
    allowed_transitions = '["interview_scheduling", "student_admission_number", "rejected"]', is_active = 1
WHERE workflow_id = 102 AND code = 'application_review';
UPDATE workflow_stages SET name = 'Interview Scheduling (Grade 4-9)', sequence = 4,
    allowed_transitions = '["interview_results", "rejected"]', is_active = 1
WHERE workflow_id = 102 AND code = 'interview_scheduling';
UPDATE workflow_stages SET name = 'Interview Results (Grade 4-9)', sequence = 5,
    allowed_transitions = '["student_admission_number", "rejected"]', is_active = 1
WHERE workflow_id = 102 AND code = 'interview_results';

INSERT INTO workflow_stages (workflow_id, code, name, required_role, description, sequence, allowed_transitions, action_config, is_active)
SELECT 102, 'student_admission_number', 'Student Admission Number', 'school_administrator',
       'Create the student record and authoritative admission number after approval.', 6,
       '["class_placement", "rejected"]', '{}', 1
WHERE NOT EXISTS (SELECT 1 FROM workflow_stages WHERE workflow_id = 102 AND code = 'student_admission_number');

UPDATE workflow_stages SET name = 'Class / Stream Placement', sequence = 7,
    allowed_transitions = '["fees_payment", "rejected"]', is_active = 1
WHERE workflow_id = 102 AND code = 'class_placement';
UPDATE workflow_stages SET name = 'Fees / Transport / Uniform Payments', sequence = 8,
    allowed_transitions = '["student_id_generation", "cancelled"]', is_active = 1
WHERE workflow_id = 102 AND code = 'fees_payment';
UPDATE workflow_stages SET name = 'ID Generation', sequence = 9,
    allowed_transitions = '["final_enrollment", "rejected"]', is_active = 1
WHERE workflow_id = 102 AND code = 'student_id_generation';

INSERT INTO workflow_stages (workflow_id, code, name, required_role, description, sequence, allowed_transitions, action_config, is_active)
SELECT 102, 'final_enrollment', 'Final Enrollment', 'school_administrator',
       'Complete the final enrollment after payment and ID generation; boarding is assigned where applicable during onboarding.', 10,
       '["enrolled"]', '{}', 1
WHERE NOT EXISTS (SELECT 1 FROM workflow_stages WHERE workflow_id = 102 AND code = 'final_enrollment');

UPDATE workflow_stages SET sequence = 11, is_active = 1 WHERE workflow_id = 102 AND code = 'enrolled';
UPDATE workflow_stages SET sequence = 99, is_active = 1 WHERE workflow_id = 102 AND code = 'rejected';

DROP PROCEDURE IF EXISTS sp_advance_admission_workflow_stage;
DELIMITER $$
CREATE DEFINER=`root`@`localhost` PROCEDURE sp_advance_admission_workflow_stage(
    IN p_application_id INT, IN p_to_stage VARCHAR(50), IN p_action VARCHAR(100),
    IN p_user_id INT, IN p_remarks TEXT, IN p_workflow_updates LONGTEXT
)
BEGIN
    DECLARE v_workflow_instance_id INT;
    DECLARE v_current_stage VARCHAR(50);
    DECLARE v_from_stage VARCHAR(50);
    SELECT id, current_stage INTO v_workflow_instance_id, v_current_stage
      FROM workflow_instances WHERE reference_type = 'admission_application' AND reference_id = p_application_id
      ORDER BY id DESC LIMIT 1;
    IF v_workflow_instance_id IS NULL THEN
        INSERT INTO workflow_instances (workflow_id, reference_type, reference_id, current_stage, stage_code, status, started_by, started_at)
        VALUES (102, 'admission_application', p_application_id, p_to_stage, p_to_stage, 'in_progress', COALESCE(p_user_id, 1), NOW());
        SET v_workflow_instance_id = LAST_INSERT_ID(); SET v_from_stage = NULL;
    ELSE SET v_from_stage = v_current_stage;
    END IF;
    INSERT INTO workflow_stage_history (instance_id, stage_code, from_stage, to_stage, action_taken, processed_by, remarks, data_json)
    VALUES (v_workflow_instance_id, p_to_stage, v_from_stage, p_to_stage, p_action, COALESCE(p_user_id, 1), p_remarks, p_workflow_updates);
    UPDATE workflow_instances SET current_stage = p_to_stage, stage_code = p_to_stage,
        data_json = COALESCE(p_workflow_updates, data_json) WHERE id = v_workflow_instance_id;
    IF p_workflow_updates IS NOT NULL THEN
        UPDATE admission_applications SET workflow_data_json = JSON_MERGE_PRESERVE(COALESCE(workflow_data_json, '{}'), p_workflow_updates)
        WHERE id = p_application_id;
    END IF;
    CASE p_to_stage
        WHEN 'application_applied' THEN UPDATE admission_applications SET status = 'submitted' WHERE id = p_application_id;
        WHEN 'application_received' THEN UPDATE admission_applications SET status = 'submitted' WHERE id = p_application_id;
        WHEN 'application_review' THEN UPDATE admission_applications SET status = 'submitted' WHERE id = p_application_id;
        WHEN 'interview_scheduling' THEN UPDATE admission_applications SET status = 'documents_verified' WHERE id = p_application_id;
        WHEN 'interview_results' THEN UPDATE admission_applications SET status = 'documents_verified' WHERE id = p_application_id;
        WHEN 'student_admission_number' THEN UPDATE admission_applications SET status = 'placement_offered' WHERE id = p_application_id;
        WHEN 'class_placement' THEN UPDATE admission_applications SET status = 'placement_offered' WHERE id = p_application_id;
        WHEN 'fees_payment' THEN UPDATE admission_applications SET status = 'fees_pending' WHERE id = p_application_id;
        WHEN 'student_id_generation' THEN UPDATE admission_applications SET status = 'fees_pending' WHERE id = p_application_id;
        WHEN 'final_enrollment' THEN UPDATE admission_applications SET status = 'fees_pending' WHERE id = p_application_id;
        WHEN 'enrolled' THEN UPDATE admission_applications SET status = 'enrolled', enrolled_at = NOW() WHERE id = p_application_id;
        WHEN 'rejected' THEN UPDATE admission_applications SET status = 'cancelled' WHERE id = p_application_id;
    END CASE;
    SELECT v_workflow_instance_id AS workflow_instance_id, v_from_stage AS from_stage, p_to_stage AS to_stage;
END$$
DELIMITER ;
