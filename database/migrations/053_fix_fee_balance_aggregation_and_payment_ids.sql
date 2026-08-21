-- Prevent fee balances from multiplying payments when a student has more than
-- one obligation and more than one payment in the same term. Each independent
-- fact is aggregated before it is joined to the enrollment/term grain.

ALTER TABLE payments
    MODIFY id INT UNSIGNED NOT NULL AUTO_INCREMENT;

DROP VIEW IF EXISTS vw_student_fee_balances;
CREATE VIEW vw_student_fee_balances AS
SELECT
    sae.id AS student_academic_enrollment_id,
    sae.student_id,
    sae.academic_year_id,
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
    SELECT student_academic_enrollment_id,
           SUM(amount_due) AS amount_due,
           MAX(due_date) AS latest_due_date
    FROM student_fee_obligations
    GROUP BY student_academic_enrollment_id
) ob ON ob.student_academic_enrollment_id = sae.id
LEFT JOIN (
    SELECT sfo.student_academic_enrollment_id,
           SUM(COALESCE(fdw.discount_value, 0) + COALESCE(sfo.sponsored_waiver_amount, 0)) AS amount_waived
    FROM student_fee_obligations sfo
    LEFT JOIN fee_discounts_waivers fdw
      ON fdw.student_fee_obligation_id = sfo.id
     AND fdw.status = 'approved'
    GROUP BY sfo.student_academic_enrollment_id
) dw ON dw.student_academic_enrollment_id = sae.id
LEFT JOIN (
    SELECT p.student_id, ayt2.id AS academic_year_term_id, SUM(p.amount) AS amount_paid
    FROM payments p
    JOIN academic_year_terms ayt2
      ON p.payment_date >= ayt2.opening_date
     AND p.payment_date <= ayt2.closing_date
    WHERE p.status IN ('confirmed', 'completed', 'success')
    GROUP BY p.student_id, ayt2.id
) pm ON pm.student_id = sae.student_id
     AND pm.academic_year_term_id = ayt.id
WHERE sae.enrollment_status = 'active';

DROP PROCEDURE IF EXISTS sp_process_student_payment;
DELIMITER $$
CREATE PROCEDURE sp_process_student_payment(
    IN p_student_id INT,
    IN p_parent_id INT,
    IN p_amount DECIMAL(10,2),
    IN p_payment_method VARCHAR(50),
    IN p_reference_no VARCHAR(100),
    IN p_receipt_no VARCHAR(50),
    IN p_received_by INT,
    IN p_payment_date DATETIME,
    IN p_notes TEXT
)
BEGIN
    DECLARE v_transaction_id INT;

    INSERT INTO payments (
        student_id, receipt_no, amount, payment_date, method,
        reference, parent_id, received_by, status, notes, created_at, updated_at
    ) VALUES (
        p_student_id, p_receipt_no, p_amount, COALESCE(p_payment_date, NOW()),
        p_payment_method, p_reference_no, p_parent_id, p_received_by,
        'confirmed', p_notes, NOW(), NOW()
    );

    SET v_transaction_id = LAST_INSERT_ID();

    SELECT v_transaction_id AS transaction_id, p_amount AS amount_applied,
           'confirmed' AS status;
END$$
DELIMITER ;
