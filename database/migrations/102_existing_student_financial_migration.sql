-- Preserve financial facts supplied during existing-learner migration.
-- academic_year_paid_amount is historical context; current_term_paid_amount
-- is the only amount applied to the current year's current-term bill.
CREATE TABLE IF NOT EXISTS student_fee_migration_snapshots (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    student_id INT UNSIGNED NOT NULL,
    academic_year_id INT UNSIGNED NOT NULL,
    academic_year_term_id INT UNSIGNED DEFAULT NULL,
    academic_year_paid_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    current_term_paid_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    arrears_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    advance_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    reference VARCHAR(100) DEFAULT NULL,
    payment_date DATE DEFAULT NULL,
    payment_method VARCHAR(30) DEFAULT NULL,
    receipt_no VARCHAR(50) DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    imported_by INT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_student_fee_migration_year (student_id, academic_year_id),
    KEY idx_student_fee_migration_term (academic_year_term_id),
    CONSTRAINT fk_student_fee_migration_student FOREIGN KEY (student_id) REFERENCES students(id),
    CONSTRAINT fk_student_fee_migration_year FOREIGN KEY (academic_year_id) REFERENCES academic_years(id),
    CONSTRAINT fk_student_fee_migration_term FOREIGN KEY (academic_year_term_id) REFERENCES academic_year_terms(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP VIEW IF EXISTS vw_student_fee_balances;
CREATE VIEW vw_student_fee_balances AS
SELECT
    sae.id AS student_academic_enrollment_id,
    sae.student_id,
    ayt.id AS academic_year_term_id,
    ayt.term_id,
    t.code AS term_code,
    ay.year_code AS academic_year,
    COALESCE(ob.amount_due, 0) + COALESCE(ms.arrears_amount, 0) AS amount_due,
    COALESCE(dw.amount_waived, 0) AS amount_waived,
    COALESCE(pm.amount_paid, 0) + COALESCE(ms.current_term_paid_amount, 0)
        + COALESCE(ms.advance_amount, 0) AS amount_paid,
    GREATEST(
        COALESCE(ob.amount_due, 0) + COALESCE(ms.arrears_amount, 0)
        - COALESCE(dw.amount_waived, 0)
        - COALESCE(pm.amount_paid, 0)
        - COALESCE(ms.current_term_paid_amount, 0)
        - COALESCE(ms.advance_amount, 0),
        0
    ) AS balance,
    CASE
        WHEN COALESCE(ob.amount_due, 0) + COALESCE(ms.arrears_amount, 0) <= 0 THEN 'no_due'
        WHEN COALESCE(ob.amount_due, 0) + COALESCE(ms.arrears_amount, 0)
             - COALESCE(dw.amount_waived, 0)
             - COALESCE(pm.amount_paid, 0)
             - COALESCE(ms.current_term_paid_amount, 0)
             - COALESCE(ms.advance_amount, 0) <= 0 THEN 'paid'
        WHEN COALESCE(pm.amount_paid, 0) + COALESCE(ms.current_term_paid_amount, 0)
             + COALESCE(ms.advance_amount, 0) > 0 THEN 'partial'
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
) ob ON ob.student_academic_enrollment_id = sae.id AND ob.academic_year_term_id = ayt.id
LEFT JOIN (
    SELECT sfo.student_academic_enrollment_id, sfo.academic_year_term_id,
           SUM(COALESCE(fdw.discount_value, 0) + COALESCE(sfo.sponsored_waiver_amount, 0)) AS amount_waived
    FROM student_fee_obligations sfo
    LEFT JOIN fee_discounts_waivers fdw
      ON fdw.student_fee_obligation_id = sfo.id AND fdw.status = 'approved'
    GROUP BY sfo.student_academic_enrollment_id, sfo.academic_year_term_id
) dw ON dw.student_academic_enrollment_id = sae.id AND dw.academic_year_term_id = ayt.id
LEFT JOIN (
    SELECT p.student_id, ayt2.id AS academic_year_term_id, SUM(p.amount) AS amount_paid
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
        SELECT 1 FROM admission_payments ap
        WHERE ap.student_id = p.student_id AND ap.reference_no = p.reference
          AND ap.status IN ('recorded', 'posted')
      )
    GROUP BY p.student_id, ayt2.id
) pm ON pm.student_id = sae.student_id AND pm.academic_year_term_id = ayt.id
LEFT JOIN student_fee_migration_snapshots ms
  ON ms.student_id = sae.student_id
 AND ms.academic_year_id = sae.academic_year_id
 AND (ms.academic_year_term_id = ayt.id OR ms.academic_year_term_id IS NULL)
WHERE sae.enrollment_status = 'active';
