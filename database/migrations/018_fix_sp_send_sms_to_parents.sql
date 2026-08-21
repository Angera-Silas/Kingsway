-- 018_fix_sp_send_sms_to_parents.sql
-- Rewrites sp_send_sms_to_parents against the live sms_communications schema.
-- The old procedure inserted into columns (sender_id, recipient_ids, message,
-- message_type) that do not exist on the live table, so every call failed
-- (admission pipeline + sp_implement_discipline_action).
--
-- New behaviour: p_parent_ids may be a single id, a comma-separated list, or a
-- JSON array. Each id is treated as a parent id OR a student id; parents are
-- resolved to their phone via persons, and students fan out to their linked
-- parents. sms_type is clamped to the live enum.
--
-- Applied to live DB: 2026-08-08

DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_send_sms_to_parents`$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_send_sms_to_parents` (IN `p_parent_ids` TEXT, IN `p_message` TEXT, IN `p_template_id` INT, IN `p_message_type` VARCHAR(50), IN `p_sent_by` INT)
BEGIN
    DECLARE v_id_list TEXT;
    DECLARE v_sms_type VARCHAR(50);

    SET v_id_list = REPLACE(REPLACE(REPLACE(COALESCE(p_parent_ids, ''), '[', ''), ']', ''), ' ', '');
    SET v_sms_type = CASE
        WHEN p_message_type IN ('academic','fees','attendance','event','emergency','general','report_card')
            THEN p_message_type
        ELSE 'general'
    END;

    INSERT INTO sms_communications (
        parent_id, student_id, recipient_phone, message_body, template_id,
        sms_type, status, sent_by, sent_at
    )
    SELECT DISTINCT p.id, NULL, COALESCE(per.phone, ''), p_message, p_template_id,
           v_sms_type, 'pending', p_sent_by, NOW()
    FROM parents p
    JOIN persons per ON per.id = p.person_id
    WHERE FIND_IN_SET(p.id, v_id_list) > 0;

    INSERT INTO sms_communications (
        parent_id, student_id, recipient_phone, message_body, template_id,
        sms_type, status, sent_by, sent_at
    )
    SELECT DISTINCT sp.parent_id, sp.student_id, COALESCE(per.phone, ''), p_message, p_template_id,
           v_sms_type, 'pending', p_sent_by, NOW()
    FROM student_parents sp
    JOIN parents p ON p.id = sp.parent_id
    JOIN persons per ON per.id = p.person_id
    WHERE FIND_IN_SET(sp.student_id, v_id_list) > 0;
END$$

DELIMITER ;
