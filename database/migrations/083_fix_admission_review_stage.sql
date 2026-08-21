-- Allow the initial admissions review stage to be advanced explicitly.
-- The previous procedure omitted application_review from its CASE statement,
-- causing a MariaDB 1339 "Case not found" error after the workflow row had
-- already been updated.

DROP PROCEDURE IF EXISTS sp_advance_admission_workflow_stage;

DELIMITER $$

CREATE DEFINER=`root`@`localhost` PROCEDURE sp_advance_admission_workflow_stage(
    IN p_application_id INT,
    IN p_to_stage VARCHAR(50),
    IN p_action VARCHAR(100),
    IN p_user_id INT,
    IN p_remarks TEXT,
    IN p_workflow_updates LONGTEXT
)
BEGIN
    DECLARE v_workflow_instance_id INT;
    DECLARE v_current_stage VARCHAR(50);
    DECLARE v_from_stage VARCHAR(50);

    SELECT id, current_stage
      INTO v_workflow_instance_id, v_current_stage
      FROM workflow_instances
     WHERE reference_type = 'admission_application'
       AND reference_id = p_application_id
     LIMIT 1;

    IF v_workflow_instance_id IS NULL THEN
        INSERT INTO workflow_instances (
            workflow_id, reference_type, reference_id, current_stage,
            stage_code, status, started_by, started_at
        ) VALUES (
            102, 'admission_application', p_application_id, p_to_stage,
            p_to_stage, 'in_progress', COALESCE(p_user_id, 1), NOW()
        );
        SET v_workflow_instance_id = LAST_INSERT_ID();
        SET v_from_stage = NULL;
    ELSE
        SET v_from_stage = v_current_stage;
    END IF;

    INSERT INTO workflow_stage_history (
        instance_id, stage_code, from_stage, to_stage, action_taken,
        processed_by, remarks, data_json
    ) VALUES (
        v_workflow_instance_id, p_to_stage, v_from_stage, p_to_stage,
        p_action, COALESCE(p_user_id, 1), p_remarks, p_workflow_updates
    );

    UPDATE workflow_instances
       SET current_stage = p_to_stage,
           stage_code = p_to_stage,
           data_json = COALESCE(p_workflow_updates, data_json)
     WHERE id = v_workflow_instance_id;

    IF p_workflow_updates IS NOT NULL THEN
        UPDATE admission_applications
           SET workflow_data_json = JSON_MERGE_PRESERVE(
               COALESCE(workflow_data_json, '{}'), p_workflow_updates
           )
         WHERE id = p_application_id;
    END IF;

    CASE p_to_stage
        WHEN 'application_review' THEN
            UPDATE admission_applications
               SET status = 'submitted'
             WHERE id = p_application_id;
        WHEN 'documents_upload' THEN
            UPDATE admission_applications SET status = 'documents_pending' WHERE id = p_application_id;
        WHEN 'documents_verification' THEN
            UPDATE admission_applications SET status = 'documents_pending' WHERE id = p_application_id;
        WHEN 'class_space_check' THEN
            UPDATE admission_applications SET status = 'documents_verified' WHERE id = p_application_id;
        WHEN 'interview_scheduling' THEN
            UPDATE admission_applications SET status = 'documents_verified' WHERE id = p_application_id;
        WHEN 'interview_results' THEN
            UPDATE admission_applications SET status = 'documents_verified' WHERE id = p_application_id;
        WHEN 'admission_decision' THEN
            UPDATE admission_applications SET status = 'documents_verified' WHERE id = p_application_id;
        WHEN 'provisional_student_creation' THEN
            UPDATE admission_applications SET status = 'placement_offered' WHERE id = p_application_id;
        WHEN 'fees_payment' THEN
            UPDATE admission_applications SET status = 'fees_pending' WHERE id = p_application_id;
        WHEN 'student_id_generation' THEN
            UPDATE admission_applications SET status = 'fees_pending' WHERE id = p_application_id;
        WHEN 'final_approval' THEN
            UPDATE admission_applications SET status = 'fees_pending' WHERE id = p_application_id;
        WHEN 'enrollment' THEN
            UPDATE admission_applications SET status = 'fees_pending' WHERE id = p_application_id;
        WHEN 'enrolled' THEN
            UPDATE admission_applications
               SET status = 'enrolled', enrolled_at = NOW()
             WHERE id = p_application_id;
        WHEN 'rejected' THEN
            UPDATE admission_applications SET status = 'cancelled' WHERE id = p_application_id;
    END CASE;

    SELECT v_workflow_instance_id AS workflow_instance_id,
           v_from_stage AS from_stage,
           p_to_stage AS to_stage;
END$$

DELIMITER ;
