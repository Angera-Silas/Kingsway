-- Keep the legacy calculation entry point executable against the current
-- schema. It returns the calculated values through OUT parameters; FinanceAPI
-- remains the writer for payslips and accounting entries.

DELIMITER $$

DROP PROCEDURE IF EXISTS sp_calculate_staff_full_payroll$$
CREATE PROCEDURE sp_calculate_staff_full_payroll(
    IN p_staff_id INT,
    IN p_month INT,
    IN p_year INT,
    OUT p_payroll_id INT,
    OUT p_net_salary DECIMAL(10,2)
)
BEGIN
    DECLARE v_basic_salary DECIMAL(10,2) DEFAULT 0;
    DECLARE v_gross_salary DECIMAL(10,2) DEFAULT 0;
    DECLARE v_allowances DECIMAL(10,2) DEFAULT 0;
    DECLARE v_nssf DECIMAL(10,2) DEFAULT 0;
    DECLARE v_shif DECIMAL(10,2) DEFAULT 0;
    DECLARE v_paye DECIMAL(10,2) DEFAULT 0;
    DECLARE v_total_deductions DECIMAL(10,2) DEFAULT 0;
    DECLARE v_other_deductions DECIMAL(10,2) DEFAULT 0;
    DECLARE v_period_end DATE;

    SELECT COALESCE(spp.basic_salary, s.salary, 0) INTO v_basic_salary
      FROM staff s
      LEFT JOIN staff_payroll_profiles spp ON spp.staff_id = s.id
     WHERE s.id = p_staff_id;
    SELECT COALESCE(SUM(amount), 0) INTO v_allowances
      FROM staff_allowances
     WHERE staff_id = p_staff_id AND status = 'active';
    SET v_gross_salary = v_basic_salary + v_allowances;
    SET v_period_end = LAST_DAY(STR_TO_DATE(CONCAT(p_year, '-', p_month, '-01'), '%Y-%m-%d'));

    CALL sp_calculate_nssf_contribution(v_gross_salary, p_year, v_nssf);
    CALL sp_calculate_shif_contribution(v_gross_salary, p_year, v_shif);
    CALL sp_calculate_paye_tax(v_gross_salary - v_nssf - v_shif, p_year, v_paye);

    SELECT COALESCE(SUM(amount), 0) INTO v_other_deductions
      FROM staff_deductions
     WHERE staff_id = p_staff_id AND status = 'active'
       AND (end_date IS NULL OR end_date >= v_period_end);

    SET v_total_deductions = v_nssf + v_shif + v_paye + v_other_deductions;
    SET p_net_salary = v_gross_salary - v_total_deductions;
    SET p_payroll_id = NULL;
END$$

DELIMITER ;
