-- Admission payments are reported separately from tuition obligations. They
-- must not reduce tuition balances as well as the admission balance.
CREATE OR REPLACE VIEW vw_student_fee_balances AS
SELECT
    sae.id AS student_academic_enrollment_id,
    sae.student_id,
    ayt.id AS academic_year_term_id,
    ayt.term_id,
    t.code AS term_code,
    ay.year_code AS academic_year,
    COALESCE(ob.amount_due, 0) AS amount_due,
    COALESCE(dw.amount_waived, 0) AS amount_waived,
    COALESCE(pm.amount_paid, 0) AS amount_paid,
    GREATEST(COALESCE(ob.amount_due, 0) - COALESCE(dw.amount_waived, 0) - COALESCE(pm.amount_paid, 0), 0) AS balance,
    CASE
        WHEN COALESCE(ob.amount_due, 0) <= 0 THEN 'no_due'
        WHEN COALESCE(ob.amount_due, 0) - COALESCE(dw.amount_waived, 0) - COALESCE(pm.amount_paid, 0) <= 0 THEN 'paid'
        WHEN COALESCE(pm.amount_paid, 0) > 0 THEN 'partial'
        ELSE 'pending'
    END AS payment_status,
    ob.latest_due_date,
    GREATEST(TO_DAYS(CURDATE()) - TO_DAYS(COALESCE(ob.latest_due_date, CURDATE())), 0) AS days_overdue
FROM student_academic_enrollments sae
JOIN academic_years ay ON ay.id = sae.academic_year_id
JOIN academic_year_terms ayt ON ayt.academic_year_id = sae.academic_year_id
JOIN terms t ON t.id = ayt.term_id
LEFT JOIN (
    SELECT student_academic_enrollment_id, academic_year_term_id,
           SUM(amount_due) AS amount_due, MAX(due_date) AS latest_due_date
    FROM student_fee_obligations
    GROUP BY student_academic_enrollment_id, academic_year_term_id
) ob ON ob.student_academic_enrollment_id = sae.id
    AND ob.academic_year_term_id = ayt.id
LEFT JOIN (
    SELECT sfo.student_academic_enrollment_id, sfo.academic_year_term_id,
           SUM(COALESCE(fdw.discount_value, 0) + COALESCE(sfo.sponsored_waiver_amount, 0)) AS amount_waived
    FROM student_fee_obligations sfo
    LEFT JOIN fee_discounts_waivers fdw
      ON fdw.student_fee_obligation_id = sfo.id
     AND fdw.status = 'active'
    GROUP BY sfo.student_academic_enrollment_id, sfo.academic_year_term_id
) dw ON dw.student_academic_enrollment_id = sae.id
    AND dw.academic_year_term_id = ayt.id
LEFT JOIN (
    SELECT p.student_id, ayt2.id AS academic_year_term_id,
           SUM(p.amount) AS amount_paid
    FROM payments p
    JOIN academic_year_terms ayt2
      ON p.payment_date <= ayt2.closing_date
     AND NOT EXISTS (
         SELECT 1
         FROM academic_year_terms earlier
         JOIN terms earlier_term ON earlier_term.id = earlier.term_id
         WHERE earlier.academic_year_id = ayt2.academic_year_id
           AND CAST(SUBSTRING(earlier_term.code, 2) AS UNSIGNED)
               < CAST(SUBSTRING((SELECT code FROM terms WHERE id = ayt2.term_id), 2) AS UNSIGNED)
           AND earlier.closing_date >= p.payment_date
     )
    WHERE p.status IN ('confirmed', 'completed', 'success')
      AND NOT EXISTS (
          SELECT 1
          FROM admission_payments ap
          WHERE ap.student_id = p.student_id
            AND ap.reference_no = p.reference
            AND ap.status IN ('recorded', 'posted')
      )
    GROUP BY p.student_id, ayt2.id
) pm ON pm.student_id = sae.student_id
    AND pm.academic_year_term_id = ayt.id
WHERE sae.enrollment_status = 'active';
