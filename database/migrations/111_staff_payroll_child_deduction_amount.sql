-- Staff payroll policy: support either a percentage or a fixed monthly child-fee
-- deduction. The relationship remains in staff_children (4NF); the monthly
-- payroll fact is materialised in payslip_items when payroll is processed.

SET @has_fee_amount := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'staff_children'
      AND COLUMN_NAME = 'fee_deduction_amount'
);
SET @sql := IF(
    @has_fee_amount = 0,
    'ALTER TABLE staff_children ADD COLUMN fee_deduction_amount DECIMAL(12,2) NULL AFTER fee_deduction_percentage',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

CREATE OR REPLACE VIEW vw_staff_children_fees AS
SELECT
    sc.id AS staff_child_id,
    sc.staff_id,
    s.staff_no,
    CONCAT(sp.first_name, ' ', sp.last_name) AS staff_name,
    spp.basic_salary AS staff_salary,
    sc.student_id,
    st.admission_no,
    CONCAT(tp.first_name, ' ', tp.last_name) AS student_name,
    tp.dob AS student_dob,
    c.name AS class_name,
    sn.name AS stream_name,
    sc.relationship,
    sc.fee_deduction_enabled,
    sc.fee_deduction_percentage,
    sc.fee_deduction_amount,
    0 AS is_sponsored,
    0 AS sponsor_waiver_percentage,
    (SELECT COUNT(*) FROM staff_children x WHERE x.staff_id = sc.staff_id) AS total_children,
    sc.created_at
FROM staff_children sc
JOIN staff s ON sc.staff_id = s.id
JOIN persons sp ON sp.id = s.person_id
LEFT JOIN staff_payroll_profiles spp ON spp.staff_id = s.id
JOIN students st ON sc.student_id = st.id
JOIN persons tp ON tp.id = st.person_id
LEFT JOIN student_academic_enrollments sae ON sae.student_id = st.id
LEFT JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id
LEFT JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
LEFT JOIN classes c ON c.id = ayc.class_id
LEFT JOIN streams sn ON sn.id = aycs.stream_id
WHERE s.status = 'active' AND st.status = 'active';
