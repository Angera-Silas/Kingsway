-- The fee editor displays Term 3, Term 2, Term 1, while older frontend code
-- saved by column position. Swap the affected Term 1/Term 3 schedule amounts
-- once so the database matches the labelled columns.
CREATE TEMPORARY TABLE kwa_fee_term_swap AS
SELECT
    t1.id AS term1_id,
    t3.id AS term3_id,
    t1.amount AS term1_amount,
    t3.amount AS term3_amount
FROM academic_year_fee_schedules t1
JOIN academic_year_terms ayt1 ON ayt1.id = t1.academic_year_term_id
JOIN terms tm1 ON tm1.id = ayt1.term_id AND tm1.code = 'T1'
JOIN academic_year_fee_schedules t3
  ON t3.academic_year_id = t1.academic_year_id
 AND t3.academic_year_class_id = t1.academic_year_class_id
 AND t3.student_type_id = t1.student_type_id
 AND t3.fee_catalog_id = t1.fee_catalog_id
JOIN academic_year_terms ayt3 ON ayt3.id = t3.academic_year_term_id
JOIN terms tm3 ON tm3.id = ayt3.term_id AND tm3.code = 'T3'
WHERE t1.status = 'active'
  AND t3.status = 'active';

UPDATE academic_year_fee_schedules s
JOIN kwa_fee_term_swap x ON x.term1_id = s.id
SET s.amount = x.term3_amount, s.updated_at = NOW();

UPDATE academic_year_fee_schedules s
JOIN kwa_fee_term_swap x ON x.term3_id = s.id
SET s.amount = x.term1_amount, s.updated_at = NOW();

DROP TEMPORARY TABLE kwa_fee_term_swap;

-- Treat a payment made before a term opens as an advance payment for the
-- earliest remaining term, rather than excluding it from every term balance.
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
     AND fdw.status = 'approved'
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
    GROUP BY p.student_id, ayt2.id
) pm ON pm.student_id = sae.student_id
    AND pm.academic_year_term_id = ayt.id
WHERE sae.enrollment_status = 'active';
