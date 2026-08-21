-- Repair the payment-status reporting view after fee-balance normalization.
-- vw_student_fee_balances exposes academic_year (year_code) and term_id;
-- it does not expose the removed academic_year_id column.

DROP VIEW IF EXISTS vw_student_payment_status_enhanced;

CREATE VIEW vw_student_payment_status_enhanced AS
SELECT
    s.id AS id,
    s.admission_no AS admission_no,
    CONCAT(p.first_name, ' ', p.last_name) AS student_name,
    st.name AS student_type,
    CONCAT(c.name, ' - ', sn.name) AS class_name,
    sl.name AS level_name,
    f.academic_year AS academic_year,
    COALESCE(f.term_id, 1) AS term_number,
    COALESCE(SUM(f.amount_due), 0) AS total_due,
    COALESCE(SUM(f.amount_paid), 0) AS total_paid,
    COALESCE(SUM(f.amount_waived), 0) AS total_waived,
    COALESCE(SUM(f.balance), 0) AS current_balance,
    COALESCE(SUM(
        CASE WHEN f.academic_year = (
            SELECT y.year_code
            FROM academic_years y
            WHERE y.is_current = 1
            ORDER BY y.id DESC
            LIMIT 1
        ) THEN f.balance ELSE 0 END
    ), 0) AS year_balance,
    COALESCE(SUM(
        CASE WHEN f.academic_year_term_id = (
            SELECT MAX(x.id)
            FROM academic_year_terms x
            WHERE x.status = 'current'
        ) THEN f.balance ELSE 0 END
    ), 0) AS term_balance,
    0 AS previous_year_balance,
    0 AS previous_term_balance,
    f.payment_status AS payment_status,
    COALESCE(MAX(sfo.is_sponsored), 0) AS is_sponsored,
    COALESCE(MAX(sfo.sponsored_waiver_amount), 0) AS sponsor_waiver_percentage
FROM students s
JOIN persons p ON p.id = s.person_id
LEFT JOIN student_types st ON s.student_type_id = st.id
LEFT JOIN student_academic_enrollments sae ON sae.student_id = s.id
LEFT JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id
LEFT JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
LEFT JOIN classes c ON c.id = ayc.class_id
LEFT JOIN school_levels sl ON sl.id = c.level_id
LEFT JOIN streams sn ON sn.id = aycs.stream_id
LEFT JOIN vw_student_fee_balances f ON f.student_academic_enrollment_id = sae.id
LEFT JOIN student_fee_obligations sfo ON sfo.student_academic_enrollment_id = sae.id
GROUP BY s.id, f.academic_year, f.term_id, f.payment_status
ORDER BY s.admission_no ASC, f.academic_year DESC, f.term_id DESC;
