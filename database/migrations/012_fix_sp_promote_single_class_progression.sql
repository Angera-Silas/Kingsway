-- 012_fix_sp_promote_single_class_progression.sql
-- Fixes sp_promote_single_class: the next-class lookup used
--   JOIN school_levels sl ON c.level_id = sl.id AND sl.id + 1
-- which breaks for every band (classes.level_id does not match school_levels.id
-- for ECD/JSS, and lower/upper primary all share a single school_level row, so
-- e.g. Grade 1 resolved to Grade 4). Now resolves via academic_class_progression
-- and handles Grade 9 graduation instead of failing the batch.

DROP PROCEDURE IF EXISTS `sp_promote_single_class`;

DELIMITER $$

CREATE PROCEDURE `sp_promote_single_class`(
    IN `p_batch_id` INT UNSIGNED,
    IN `p_from_year` YEAR,
    IN `p_to_year` YEAR,
    IN `p_current_class_id` INT UNSIGNED,
    IN `p_current_stream_id` INT UNSIGNED
)
BEGIN
    DECLARE v_batch_status VARCHAR(50);
    DECLARE v_error_msg VARCHAR(255);
    DECLARE v_next_class_id INT UNSIGNED;
    DECLARE v_next_stream_id INT UNSIGNED;
    DECLARE v_from_year_id INT UNSIGNED;
    DECLARE v_to_year_id INT UNSIGNED;
    DECLARE v_transition_count INT UNSIGNED DEFAULT 0;
    DECLARE v_is_graduation TINYINT DEFAULT 0;

    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        GET DIAGNOSTICS CONDITION 1 v_error_msg = MESSAGE_TEXT;
        UPDATE promotion_batches
        SET status = 'cancelled'
        WHERE id = p_batch_id;
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Single class promotion failed';
    END;

    SELECT status INTO v_batch_status
    FROM promotion_batches
    WHERE id = p_batch_id;
    IF v_batch_status IS NULL THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Promotion batch not found';
    END IF;

    SELECT id INTO v_from_year_id FROM academic_years WHERE year_code = p_from_year LIMIT 1;
    SELECT id INTO v_to_year_id FROM academic_years WHERE year_code = p_to_year LIMIT 1;

    -- Resolve the next class through the normalized progression ladder.
    -- An absent target (e.g. Grade 9) means the stream's students graduate.
    SELECT target_class_id INTO v_next_class_id
    FROM academic_class_progression
    WHERE source_class_id = p_current_class_id AND active = 1
    LIMIT 1;

    IF v_next_class_id IS NULL THEN
        SET v_is_graduation = 1;
    ELSE
        -- Stream continuity by name (5A promotes to 6A, 5B to 6B, ...).
        SELECT aycs_to.stream_id INTO v_next_stream_id
        FROM academic_year_classes ayc_to
        JOIN academic_year_class_streams aycs_to ON aycs_to.academic_year_class_id = ayc_to.id
        JOIN streams s_next ON s_next.id = aycs_to.stream_id
        JOIN streams s_cur ON s_cur.id = p_current_stream_id AND s_cur.name = s_next.name
        WHERE ayc_to.class_id = v_next_class_id
          AND ayc_to.academic_year_id = v_to_year_id
        LIMIT 1;

        IF v_next_stream_id IS NULL THEN
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Next class or stream not found for promotion';
        END IF;
    END IF;

    IF v_batch_status = 'pending' THEN
        UPDATE promotion_batches
        SET status = 'in_progress'
        WHERE id = p_batch_id;
    END IF;

    INSERT INTO student_transitions (
        id,
        student_id,
        from_student_academic_enrollment_id,
        to_student_academic_enrollment_id,
        academic_year_id,
        transition_type,
        reason,
        decided_by,
        decided_at
    )
    SELECT (SELECT COALESCE(MAX(id), 0) FROM student_transitions) + ROW_NUMBER() OVER (ORDER BY s.id),
        s.id,
        sae.id,
        NULL,
        v_to_year_id,
        IF(v_is_graduation = 1, 'graduation', 'promotion'),
        IF(v_is_graduation = 1, 'Graduation', 'Pending approval'),
        NULL,
        NOW()
    FROM students s
    JOIN student_academic_enrollments sae ON sae.student_id = s.id
        AND sae.academic_year_id = v_from_year_id
        AND sae.enrollment_status IN ('active', 'pending')
    JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id
    JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
    WHERE aycs.stream_id = p_current_stream_id
        AND ayc.class_id = p_current_class_id
        AND s.status = 'active'
        AND NOT EXISTS (
            SELECT 1
            FROM student_transitions st
            WHERE st.student_id = s.id
                AND st.academic_year_id = v_to_year_id
                AND st.transition_type = IF(v_is_graduation = 1, 'graduation', 'promotion')
        );

    SELECT ROW_COUNT() INTO v_transition_count;

    IF v_is_graduation = 0 THEN
        INSERT INTO class_promotion_queue (
            batch_id,
            class_id,
            stream_id,
            total_in_class,
            approval_status
        )
        VALUES (
            p_batch_id,
            v_next_class_id,
            v_next_stream_id,
            v_transition_count,
            'pending'
        )
        ON DUPLICATE KEY UPDATE
            total_in_class = total_in_class + v_transition_count;

        UPDATE promotion_batches
        SET total_students_processed = (
                SELECT COALESCE(SUM(total_in_class), 0)
                FROM class_promotion_queue
                WHERE batch_id = p_batch_id
            ),
            total_pending_approval = (
                SELECT COALESCE(SUM(pending_count), 0)
                FROM class_promotion_queue
                WHERE batch_id = p_batch_id
            )
        WHERE id = p_batch_id;
    ELSE
        -- Graduates are counted directly (no queue entry for them).
        UPDATE promotion_batches
        SET total_students_processed = COALESCE(total_students_processed, 0) + v_transition_count
        WHERE id = p_batch_id;
    END IF;
END$$

DELIMITER ;
