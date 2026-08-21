DROP PROCEDURE IF EXISTS sp_record_cash_payment_v2;

CREATE PROCEDURE sp_record_cash_payment_v2(
    IN p_student_id INT UNSIGNED,
    IN p_amount DECIMAL(12,2),
    IN p_payment_method VARCHAR(30),
    IN p_payment_date DATETIME,
    IN p_financial_account_id BIGINT UNSIGNED,
    IN p_received_by INT UNSIGNED,
    IN p_reference VARCHAR(100),
    IN p_purpose VARCHAR(40)
)
BEGIN
    INSERT INTO payments (
        student_id, financial_account_id, payment_purpose, receipt_no,
        amount, payment_date, method, reference, parent_id, received_by,
        status, notes, created_at, updated_at
    )
    SELECT
        p_student_id, p_financial_account_id, COALESCE(NULLIF(p_purpose,''),'fees'), NULL,
        p_amount, COALESCE(p_payment_date,NOW()), p_payment_method, p_reference,
        (SELECT sp.parent_id FROM student_parents sp WHERE sp.student_id=p_student_id ORDER BY sp.is_primary_contact DESC,sp.id LIMIT 1),
        p_received_by, 'confirmed', 'Recorded through controlled cash workflow', NOW(), NOW();

    SELECT LAST_INSERT_ID() AS payment_id;
END;
