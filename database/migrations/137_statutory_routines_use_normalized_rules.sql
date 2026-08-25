-- Replace legacy payroll routines that read payroll_configurations or embed
-- statutory rates with routines backed by the normalized rule tables.

DELIMITER $$

DROP PROCEDURE IF EXISTS sp_calculate_nssf_contribution$$
CREATE PROCEDURE sp_calculate_nssf_contribution(
    IN p_gross_salary DECIMAL(12,2),
    IN p_financial_year INT,
    OUT p_nssf_amount DECIMAL(12,2)
)
BEGIN
    DECLARE v_rate DECIMAL(12,6) DEFAULT NULL;
    DECLARE v_lower DECIMAL(12,2) DEFAULT NULL;
    DECLARE v_upper DECIMAL(12,2) DEFAULT NULL;
    DECLARE v_as_of DATE;

    SET v_as_of = STR_TO_DATE(CONCAT(p_financial_year, '-12-31'), '%Y-%m-%d');
    SELECT employee_rate, lower_earnings_limit, upper_earnings_limit
      INTO v_rate, v_lower, v_upper
      FROM statutory_rule_versions
     WHERE agency = 'NSSF'
       AND rule_code = 'employee_employer_contribution'
       AND active = 1
       AND effective_from <= v_as_of
       AND (effective_to IS NULL OR effective_to >= v_as_of)
     ORDER BY effective_from DESC, id DESC
     LIMIT 1;

    IF v_rate IS NULL OR v_upper IS NULL THEN
        SET p_nssf_amount = 0;
    ELSE
        SET v_lower = COALESCE(v_lower, 0);
        SET p_nssf_amount = ROUND(
            (LEAST(GREATEST(COALESCE(p_gross_salary, 0), 0), v_lower)
                + GREATEST(LEAST(GREATEST(COALESCE(p_gross_salary, 0), 0), v_upper) - v_lower, 0))
            * v_rate / 100, 2);
    END IF;
END$$

DROP PROCEDURE IF EXISTS sp_calculate_shif_contribution$$
CREATE PROCEDURE sp_calculate_shif_contribution(
    IN p_gross_salary DECIMAL(12,2),
    IN p_financial_year INT,
    OUT p_shif_amount DECIMAL(12,2)
)
BEGIN
    DECLARE v_rate DECIMAL(12,6) DEFAULT NULL;
    DECLARE v_as_of DATE;

    SET v_as_of = STR_TO_DATE(CONCAT(p_financial_year, '-12-31'), '%Y-%m-%d');
    SELECT employee_rate INTO v_rate
      FROM statutory_rule_versions
     WHERE agency = 'SHIF'
       AND rule_code = 'employee_contribution'
       AND active = 1
       AND effective_from <= v_as_of
       AND (effective_to IS NULL OR effective_to >= v_as_of)
     ORDER BY effective_from DESC, id DESC
     LIMIT 1;

    SET p_shif_amount = IF(v_rate IS NULL, 0,
        ROUND(GREATEST(COALESCE(p_gross_salary, 0), 0) * v_rate / 100, 2));
END$$

-- Compatibility name retained for old callers; it now calculates SHIF from
-- the SHIF rule table and has no NHIF rate or fallback value.
DROP PROCEDURE IF EXISTS sp_calculate_nhif_contribution$$
CREATE PROCEDURE sp_calculate_nhif_contribution(
    IN p_gross_salary DECIMAL(12,2),
    IN p_financial_year INT,
    OUT p_nhif_amount DECIMAL(12,2)
)
BEGIN
    CALL sp_calculate_shif_contribution(p_gross_salary, p_financial_year, p_nhif_amount);
END$$

DROP PROCEDURE IF EXISTS sp_calculate_paye_tax$$
CREATE PROCEDURE sp_calculate_paye_tax(
    IN p_gross_salary DECIMAL(12,2),
    IN p_financial_year INT,
    OUT p_tax_amount DECIMAL(12,2)
)
BEGIN
    DECLARE v_rule_id INT DEFAULT NULL;
    DECLARE v_relief DECIMAL(12,2) DEFAULT 0;
    DECLARE v_lower DECIMAL(12,2);
    DECLARE v_upper DECIMAL(12,2);
    DECLARE v_rate DECIMAL(12,6);
    DECLARE v_tax DECIMAL(18,6) DEFAULT 0;
    DECLARE v_as_of DATE;
    DECLARE v_done TINYINT DEFAULT 0;
    DECLARE band_cursor CURSOR FOR
        SELECT lower_bound, upper_bound, tax_rate
          FROM statutory_tax_bands
         WHERE rule_version_id = v_rule_id
         ORDER BY band_order;
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET v_done = 1;

    SET v_as_of = STR_TO_DATE(CONCAT(p_financial_year, '-12-31'), '%Y-%m-%d');
    SELECT id, COALESCE(personal_relief, 0)
      INTO v_rule_id, v_relief
      FROM statutory_rule_versions
     WHERE agency = 'KRA'
       AND rule_code = 'paye_bands'
       AND active = 1
       AND effective_from <= v_as_of
       AND (effective_to IS NULL OR effective_to >= v_as_of)
     ORDER BY effective_from DESC, id DESC
     LIMIT 1;

    IF v_rule_id IS NULL THEN
        SET p_tax_amount = 0;
    ELSE
        OPEN band_cursor;
        band_loop: LOOP
            FETCH band_cursor INTO v_lower, v_upper, v_rate;
            IF v_done = 1 THEN LEAVE band_loop; END IF;
            SET v_tax = v_tax + GREATEST(
                LEAST(GREATEST(COALESCE(p_gross_salary, 0), 0), COALESCE(v_upper, GREATEST(COALESCE(p_gross_salary, 0), 0)))
                - v_lower, 0) * v_rate / 100;
        END LOOP;
        CLOSE band_cursor;
        SET p_tax_amount = ROUND(GREATEST(v_tax - v_relief, 0), 2);
    END IF;
END$$

DROP PROCEDURE IF EXISTS sp_calculate_staff_full_payroll$$
CREATE PROCEDURE sp_calculate_staff_full_payroll(
    IN p_staff_id INT,
    IN p_month INT,
    IN p_year INT,
    OUT p_payroll_id INT,
    OUT p_net_salary DECIMAL(10,2)
)
BEGIN
    DECLARE v_basic_salary DECIMAL(10,2);
    DECLARE v_gross_salary DECIMAL(10,2);
    DECLARE v_allowances DECIMAL(10,2);
    DECLARE v_nssf DECIMAL(10,2);
    DECLARE v_shif DECIMAL(10,2);
    DECLARE v_paye DECIMAL(10,2);
    DECLARE v_total_deductions DECIMAL(10,2);
    DECLARE v_other_deductions DECIMAL(10,2);

    SELECT basic_salary INTO v_basic_salary FROM staff WHERE id = p_staff_id;
    SELECT COALESCE(SUM(amount), 0) INTO v_allowances
      FROM staff_allowances WHERE staff_id = p_staff_id AND is_active = 1;
    SET v_gross_salary = COALESCE(v_basic_salary, 0) + COALESCE(v_allowances, 0);

    CALL sp_calculate_nssf_contribution(v_gross_salary, p_year, v_nssf);
    CALL sp_calculate_shif_contribution(v_gross_salary, p_year, v_shif);
    CALL sp_calculate_paye_tax(v_gross_salary - v_nssf, p_year, v_paye);

    SELECT COALESCE(SUM(amount), 0) INTO v_other_deductions
      FROM staff_deductions
     WHERE staff_id = p_staff_id AND is_active = 1
       AND (end_date IS NULL OR end_date >= LAST_DAY(STR_TO_DATE(CONCAT(p_year, '-', p_month, '-01'), '%Y-%m-%d')));

    SET v_total_deductions = v_nssf + v_shif + v_paye + v_other_deductions;
    SET p_net_salary = v_gross_salary - v_total_deductions;

    SET p_payroll_id = NULL;
END$$

DELIMITER ;
