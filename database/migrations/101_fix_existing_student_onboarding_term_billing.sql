-- 101_fix_existing_student_onboarding_term_billing.sql
-- Ensure the enrollment trigger bills only the current term and later terms.

DROP PROCEDURE IF EXISTS sp_generate_student_fee_obligations;

DELIMITER $$
CREATE PROCEDURE sp_generate_student_fee_obligations(
    IN p_student_id INT UNSIGNED,
    IN p_academic_year_id INT UNSIGNED,
    IN p_term_id INT UNSIGNED,
    OUT p_obligations_created INT
)
BEGIN
    DECLARE v_academic_year_id INT UNSIGNED;
    DECLARE v_academic_year_class_id INT UNSIGNED;
    DECLARE v_student_type_id INT UNSIGNED;
    DECLARE v_enrollment_id INT UNSIGNED;
    DECLARE v_start_term_number INT DEFAULT NULL;
    DECLARE v_requested_term_id INT UNSIGNED DEFAULT NULL;

    SET p_obligations_created = 0;

    IF p_academic_year_id IS NULL THEN
        SELECT id INTO v_academic_year_id
        FROM academic_years
        WHERE is_current = 1
        ORDER BY id DESC
        LIMIT 1;
    ELSE
        SET v_academic_year_id = p_academic_year_id;
    END IF;

    IF v_academic_year_id IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'No active academic year found';
    END IF;

    SELECT s.student_type_id INTO v_student_type_id
    FROM students s WHERE s.id = p_student_id LIMIT 1;

    SELECT sae.id, aycs.academic_year_class_id
      INTO v_enrollment_id, v_academic_year_class_id
    FROM student_academic_enrollments sae
    JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id
    WHERE sae.student_id = p_student_id
      AND sae.academic_year_id = v_academic_year_id
      AND sae.enrollment_status IN ('active', 'pending')
    ORDER BY sae.id DESC LIMIT 1;

    IF v_enrollment_id IS NOT NULL AND v_student_type_id IS NOT NULL THEN
        -- An explicitly supplied term is authoritative (used by controlled
        -- rollover/onboarding calls). Otherwise resolve the active term from
        -- the year context, then from dates, then the next upcoming term.
        IF p_term_id IS NOT NULL THEN
            SELECT CAST(SUBSTRING(t.code, 2) AS UNSIGNED), ayt.id
              INTO v_start_term_number, v_requested_term_id
            FROM academic_year_terms ayt
            JOIN terms t ON t.id = ayt.term_id
            WHERE ayt.academic_year_id = v_academic_year_id
              AND (ayt.id = p_term_id OR ayt.term_id = p_term_id)
            ORDER BY CAST(SUBSTRING(t.code, 2) AS UNSIGNED) DESC LIMIT 1;
        END IF;

        IF v_start_term_number IS NULL THEN
            -- Use one aggregate query so the no-row behaviour of SELECT ...
            -- INTO cannot silently leave the default at Term 1. Explicitly
            -- current terms win, then date-matching terms, then the next
            -- upcoming term.
            SELECT COALESCE(
                MAX(CASE WHEN ayt.status = 'current' THEN CAST(SUBSTRING(t.code, 2) AS UNSIGNED) END),
                MAX(CASE WHEN CURDATE() BETWEEN ayt.opening_date AND ayt.closing_date THEN CAST(SUBSTRING(t.code, 2) AS UNSIGNED) END),
                MIN(CASE WHEN ayt.opening_date >= CURDATE() THEN CAST(SUBSTRING(t.code, 2) AS UNSIGNED) END),
                1
            ) INTO v_start_term_number
            FROM academic_year_terms ayt
            JOIN terms t ON t.id = ayt.term_id
            WHERE ayt.academic_year_id = v_academic_year_id;
        END IF;

        INSERT INTO student_fee_obligations (
            id, student_academic_enrollment_id, academic_year_id,
            academic_year_term_id, academic_year_fee_schedule_id, amount_due,
            status, due_date, is_sponsored, sponsored_waiver_amount,
            created_at, updated_at
        )
        SELECT
            COALESCE((SELECT MAX(id) FROM student_fee_obligations), 0)
                + ROW_NUMBER() OVER (ORDER BY CAST(SUBSTRING(t.code, 2) AS UNSIGNED), ayfs.id),
            v_enrollment_id, v_academic_year_id, ayfs.academic_year_term_id,
            ayfs.id, ayfs.amount, 'pending',
            COALESCE(ayfs.due_date, ayt.closing_date, DATE_ADD(CURDATE(), INTERVAL 30 DAY)),
            0, 0, NOW(), NOW()
        FROM academic_year_fee_schedules ayfs
        JOIN academic_year_terms ayt ON ayt.id = ayfs.academic_year_term_id
        JOIN terms t ON t.id = ayt.term_id
        WHERE ayfs.academic_year_id = v_academic_year_id
          AND ayfs.academic_year_class_id = v_academic_year_class_id
          AND ayfs.student_type_id = v_student_type_id
          AND ayfs.status = 'active'
          AND CAST(SUBSTRING(t.code, 2) AS UNSIGNED) >= v_start_term_number
          AND NOT EXISTS (
              SELECT 1 FROM student_fee_obligations sfo
              WHERE sfo.student_academic_enrollment_id = v_enrollment_id
                AND sfo.academic_year_fee_schedule_id = ayfs.id
          );

        SET p_obligations_created = ROW_COUNT();
    END IF;
END$$
DELIMITER ;
