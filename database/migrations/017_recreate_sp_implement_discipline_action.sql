-- 017_recreate_sp_implement_discipline_action.sql
-- Recreates sp_implement_discipline_action against the generalized counseling schema.
-- After migration 016 renamed student_counseling_cases -> counseling_cases, the old
-- live procedure still referenced the retired table and inserted an invalid
-- status ('active'). This recreates it from the 3NF deliverable definition.
--
-- Applied to live DB: 2026-08-08

DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_implement_discipline_action`$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_implement_discipline_action` (IN `p_case_id` INT UNSIGNED, IN `p_action_type` ENUM('warning','detention','suspension','expulsion','counseling'), IN `p_action_details` JSON, IN `p_implemented_by` INT UNSIGNED)
BEGIN
    DECLARE v_student_id INT UNSIGNED;
    DECLARE v_enrollment_id INT UNSIGNED;

    START TRANSACTION;

    SELECT sae.student_id, di.student_academic_enrollment_id
    INTO v_student_id, v_enrollment_id
    FROM discipline_incidents di
    JOIN student_academic_enrollments sae ON sae.id = di.student_academic_enrollment_id
    WHERE di.id = p_case_id;

    CASE p_action_type
        WHEN 'suspension' THEN
            INSERT INTO student_suspensions (
                student_id, academic_year, suspension_type, reason,
                suspension_date, expected_return_date, suspended_by, status
            ) VALUES (
                v_student_id,
                YEAR(CURDATE()),
                'disciplinary',
                JSON_UNQUOTE(JSON_EXTRACT(p_action_details, '$.reason')),
                NOW(),
                JSON_UNQUOTE(JSON_EXTRACT(p_action_details, '$.end_date')),
                p_implemented_by,
                'active'
            );

        WHEN 'expulsion' THEN
            UPDATE students
            SET status = 'transferred'
            WHERE id = v_student_id;
            UPDATE student_academic_enrollments
            SET enrollment_status = 'withdrawn'
            WHERE id = v_enrollment_id;

        WHEN 'counseling' THEN
            INSERT INTO counseling_cases (
                student_id, case_code, title, case_type, referral_source,
                priority, status, description, assigned_to, opened_by, opened_at
            ) VALUES (
                v_student_id,
                CONCAT('CC-', p_case_id, '-', UNIX_TIMESTAMP()),
                'Disciplinary counseling',
                'disciplinary',
                'discipline',
                'medium',
                'open',
                JSON_UNQUOTE(JSON_EXTRACT(p_action_details, '$.notes')),
                JSON_UNQUOTE(JSON_EXTRACT(p_action_details, '$.counselor_id')),
                p_implemented_by,
                NOW()
            );
    END CASE;

    UPDATE discipline_incidents
    SET action_taken = p_action_type,
        description = COALESCE(JSON_UNQUOTE(JSON_EXTRACT(p_action_details, '$.notes')), description),
        status = CASE
            WHEN p_action_type = 'expulsion' THEN 'resolved'
            WHEN p_action_type = 'suspension' THEN 'escalated'
            ELSE 'resolved'
        END
    WHERE id = p_case_id;

    CALL sp_send_sms_to_parents(
        JSON_ARRAY(v_student_id),
        CONCAT('Discipline action: ', p_action_type, '. Details sent via email.'),
        NULL,
        'general',
        p_implemented_by
    );

    COMMIT;
END$$

DELIMITER ;
