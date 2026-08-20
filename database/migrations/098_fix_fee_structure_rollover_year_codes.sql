-- Fee rollover must use the stored academic year code (for example
-- 2026/2027), not an integer cast of that value. The old procedure silently
-- failed to resolve slash-formatted year codes and could also duplicate rows.
DROP PROCEDURE IF EXISTS sp_auto_rollover_fee_structures;
DELIMITER $$
CREATE PROCEDURE sp_auto_rollover_fee_structures(
    IN p_from_year VARCHAR(20),
    IN p_to_year VARCHAR(20),
    IN p_apply_increase TINYINT,
    OUT p_copied INT,
    OUT p_log_id INT
)
BEGIN
    DECLARE v_copied INT DEFAULT 0;
    DECLARE v_from_year_id INT UNSIGNED;
    DECLARE v_to_year_id INT UNSIGNED;
    DECLARE v_next_id BIGINT UNSIGNED;

    SELECT id INTO v_from_year_id
    FROM academic_years
    WHERE year_code = p_from_year OR CAST(id AS CHAR) = p_from_year
    LIMIT 1;

    SELECT id INTO v_to_year_id
    FROM academic_years
    WHERE year_code = p_to_year OR CAST(id AS CHAR) = p_to_year
    LIMIT 1;

    IF v_from_year_id IS NULL OR v_to_year_id IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Source or target academic year not found';
    END IF;

    SELECT COALESCE(MAX(id), 0) INTO v_next_id FROM academic_year_fee_schedules;

    INSERT INTO academic_year_fee_schedules (
        id, academic_year_id, academic_year_term_id, academic_year_class_id,
        student_type_id, fee_catalog_id, amount, due_date, status,
        created_by, created_at, updated_at
    )
    SELECT
        v_next_id + ROW_NUMBER() OVER (ORDER BY src.id),
        v_to_year_id,
        dst_ayt.id,
        dst_ayc.id,
        src.student_type_id,
        src.fee_catalog_id,
        CASE WHEN p_apply_increase = 1 THEN ROUND(src.amount * 1.1, 2) ELSE src.amount END,
        src.due_date,
        'draft',
        NULL,
        NOW(),
        NOW()
    FROM academic_year_fee_schedules src
    JOIN academic_year_classes src_ayc ON src_ayc.id = src.academic_year_class_id
    JOIN academic_year_classes dst_ayc
      ON dst_ayc.academic_year_id = v_to_year_id
     AND dst_ayc.class_id = src_ayc.class_id
    LEFT JOIN academic_year_terms src_ayt ON src_ayt.id = src.academic_year_term_id
    LEFT JOIN academic_year_terms dst_ayt
      ON dst_ayt.academic_year_id = v_to_year_id
     AND dst_ayt.term_id = src_ayt.term_id
    WHERE src.academic_year_id = v_from_year_id
      AND src.status = 'active'
      AND (src.academic_year_term_id IS NULL OR dst_ayt.id IS NOT NULL)
      AND NOT EXISTS (
          SELECT 1
          FROM academic_year_fee_schedules existing
          WHERE existing.academic_year_id = v_to_year_id
            AND existing.academic_year_term_id <=> dst_ayt.id
            AND existing.academic_year_class_id = dst_ayc.id
            AND existing.student_type_id = src.student_type_id
            AND existing.fee_catalog_id = src.fee_catalog_id
            AND existing.status IN ('active', 'draft')
      );

    SET v_copied = ROW_COUNT();
    SET p_copied = v_copied;
    SET p_log_id = 0;
END$$
DELIMITER ;
