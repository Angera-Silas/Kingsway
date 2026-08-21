-- Replace the rejected configurable fee-component catalogue with the school's
-- approved matrix: grade/class × student type × term × amount.
--
-- The schedule table remains the source of truth.  fee_catalog is retained as
-- one non-configurable technical identity (School Fees) for compatibility with
-- existing obligation and payment history; fee_types is removed entirely.

SET FOREIGN_KEY_CHECKS = 0;

-- The old component catalogue is not part of the approved school model.
DROP VIEW IF EXISTS vw_fee_schedule_by_class;
DROP VIEW IF EXISTS vw_fee_structure_annual_summary;
DROP VIEW IF EXISTS vw_fee_type_collection;

-- Normalize the one technical schedule identity and remove rejected catalogue
-- rows.  All schedules in this installation use fee_catalog id 1.
INSERT INTO fee_catalog (id, code, name, fee_type_id, student_type_id, default_amount, status, description)
VALUES (1, 'SCHOOL_FEES', 'School Fees', NULL, NULL, 0, 'active', 'Approved school fee matrix')
ON DUPLICATE KEY UPDATE
    code = VALUES(code),
    name = VALUES(name),
    fee_type_id = NULL,
    student_type_id = NULL,
    default_amount = 0,
    status = 'active',
    description = VALUES(description);

UPDATE academic_year_fee_schedules SET fee_catalog_id = 1;
DELETE FROM fee_catalog WHERE id <> 1;

-- These rows were generated without a grade/class and are not referenced by
-- any student_fee_obligations.  They cannot represent the approved matrix.
DELETE ayfs
FROM academic_year_fee_schedules ayfs
LEFT JOIN student_fee_obligations sfo
    ON sfo.academic_year_fee_schedule_id = ayfs.id
WHERE ayfs.academic_year_class_id IS NULL
  AND sfo.id IS NULL;

DROP PROCEDURE IF EXISTS sp_calculate_student_fees;
DROP PROCEDURE IF EXISTS sp_get_class_fee_schedule;
DROP PROCEDURE IF EXISTS sp_get_fee_breakdown_for_review;

DROP TABLE IF EXISTS fee_types;

DELIMITER $$

CREATE PROCEDURE sp_calculate_student_fees(IN p_student_id INT, IN p_year INT, IN p_term_id INT)
BEGIN
    SELECT ayfs.id AS fee_structure_id,
           'School Fees' AS fee_type,
           ayfs.amount,
           ayfs.due_date
    FROM academic_year_fee_schedules ayfs
    JOIN student_academic_enrollments sae
      ON sae.student_id = p_student_id
     AND sae.academic_year_id = (SELECT id FROM academic_years WHERE year_code = p_year LIMIT 1)
     AND sae.enrollment_status IN ('active', 'pending')
    JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id
    WHERE ayfs.academic_year_class_id = aycs.academic_year_class_id
      AND ayfs.student_type_id = (SELECT student_type_id FROM students WHERE id = p_student_id)
      AND ayfs.academic_year_id = (SELECT id FROM academic_years WHERE year_code = p_year LIMIT 1)
      AND (p_term_id IS NULL OR ayfs.academic_year_term_id IN (
          SELECT ayt.id FROM academic_year_terms ayt WHERE ayt.term_id = p_term_id
      ))
      AND ayfs.status = 'active';
END$$

CREATE PROCEDURE sp_get_class_fee_schedule(IN p_class_id INT, IN p_year INT)
BEGIN
    SELECT 'School Fees' AS fee_type,
           ayfs.amount,
           ayfs.due_date,
           st.name AS student_type
    FROM academic_year_fee_schedules ayfs
    JOIN student_types st ON st.id = ayfs.student_type_id
    JOIN academic_year_classes ayc
      ON ayc.id = ayfs.academic_year_class_id
     AND ayc.class_id = p_class_id
    JOIN academic_years ay
      ON ay.id = ayfs.academic_year_id
     AND ay.year_code = p_year
    WHERE ayfs.status = 'active'
    ORDER BY st.name;
END$$

CREATE PROCEDURE sp_get_fee_breakdown_for_review(IN p_year INT, IN p_term INT)
BEGIN
    SELECT l.name AS level_name,
           'School Fees' AS fee_type,
           st.name AS student_type,
           ayfs.amount,
           ayfs.status,
           ayfs.due_date
    FROM academic_year_fee_schedules ayfs
    JOIN student_types st ON st.id = ayfs.student_type_id
    JOIN academic_year_classes ayc ON ayc.id = ayfs.academic_year_class_id
    JOIN classes c ON c.id = ayc.class_id
    JOIN school_levels l ON l.id = c.level_id
    WHERE ayfs.academic_year_id = (SELECT id FROM academic_years WHERE year_code = p_year LIMIT 1)
      AND ayfs.academic_year_term_id IN (
          SELECT ayt.id FROM academic_year_terms ayt WHERE ayt.term_id = p_term
      )
      AND ayfs.status = 'active'
    ORDER BY l.name, st.name;
END$$

DELIMITER ;

CREATE OR REPLACE VIEW vw_fee_schedule_by_class AS
SELECT sl.name AS level_name,
       sl.code AS level_code,
       t.name AS academic_term,
       st.name AS student_type,
       st.code AS student_type_code,
       'School Fees' AS fee_name,
       'school_fees' AS fee_category,
       ayfs.amount AS amount_due,
       ayfs.due_date,
       COUNT(DISTINCT s.id) AS number_of_students
FROM academic_year_fee_schedules ayfs
JOIN academic_year_classes ayc ON ayc.id = ayfs.academic_year_class_id
JOIN classes c ON c.id = ayc.class_id
JOIN school_levels sl ON sl.id = c.level_id
LEFT JOIN academic_year_terms ayt ON ayt.id = ayfs.academic_year_term_id
LEFT JOIN terms t ON t.id = ayt.term_id
JOIN student_types st ON st.id = ayfs.student_type_id
LEFT JOIN student_academic_enrollments sae
  ON sae.academic_year_class_stream_id IN (
      SELECT x.id FROM academic_year_class_streams x
      WHERE x.academic_year_class_id = ayc.id
  )
LEFT JOIN students s ON s.id = sae.student_id
WHERE ayfs.status = 'active'
GROUP BY sl.id, t.id, st.id, ayfs.id
ORDER BY sl.name, t.id, st.name;

CREATE OR REPLACE VIEW vw_fee_structure_annual_summary AS
SELECT ay.year_code AS academic_year,
       sl.name AS level_name,
       sl.id AS level_id,
       'School Fees' AS fee_type,
       NULL AS fee_type_id,
       'school_fees' AS fee_category,
       SUM(CASE WHEN ayt.term_id = 1 THEN ayfs.amount ELSE 0 END) AS term1_amount,
       SUM(CASE WHEN ayt.term_id = 2 THEN ayfs.amount ELSE 0 END) AS term2_amount,
       SUM(CASE WHEN ayt.term_id = 3 THEN ayfs.amount ELSE 0 END) AS term3_amount,
       SUM(ayfs.amount) AS annual_total,
       ayfs.status,
       0 AS is_auto_rollover,
       NULL AS reviewed_by,
       NULL AS reviewer_name,
       MAX(ayfs.approved_by) AS approved_by,
       NULL AS approver_name,
       MAX(ayfs.approved_at) AS approved_at,
       NULL AS activated_at,
       COUNT(DISTINCT ayfs.id) AS structure_count
FROM academic_year_fee_schedules ayfs
JOIN academic_years ay ON ay.id = ayfs.academic_year_id
JOIN academic_year_terms ayt ON ayt.id = ayfs.academic_year_term_id
JOIN academic_year_classes ayc ON ayc.id = ayfs.academic_year_class_id
JOIN classes c ON c.id = ayc.class_id
JOIN school_levels sl ON sl.id = c.level_id
WHERE ayfs.status = 'active'
GROUP BY ay.id, sl.id, ayfs.student_type_id, ayfs.status;

CREATE OR REPLACE VIEW vw_fee_type_collection AS
SELECT 'School Fees' AS fee_type,
       'SCHOOL_FEES' AS fee_code,
       'school_fees' AS fee_category,
       1 AS is_mandatory,
       SUM(f.amount_due) AS total_due,
       SUM(f.amount_paid) AS total_collected,
       SUM(f.balance) AS total_outstanding,
       COUNT(DISTINCT f.student_id) AS students_affected,
       ROUND(SUM(f.amount_paid) / NULLIF(SUM(f.amount_due), 0) * 100, 2) AS collection_rate_percent,
       COUNT(DISTINCT CASE WHEN f.payment_status = 'paid' THEN f.student_id END) AS students_paid,
       COUNT(DISTINCT CASE WHEN f.payment_status = 'partial' THEN f.student_id END) AS students_partial,
       COUNT(DISTINCT CASE WHEN f.payment_status = 'pending' THEN f.student_id END) AS students_pending
FROM vw_student_fee_balances f
WHERE f.academic_year = YEAR(CURDATE());

SET FOREIGN_KEY_CHECKS = 1;
