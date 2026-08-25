-- The normalized statutory tables are the only source for tax rules. Remove
-- the obsolete duplicate table so no payroll path can read stale bands.
DROP TABLE IF EXISTS tax_brackets;

DELIMITER $$

DROP PROCEDURE IF EXISTS sp_generate_p9_form$$
CREATE PROCEDURE sp_generate_p9_form(IN p_staff_id INT, IN p_year INT)
BEGIN
    SELECT
        s.id AS staff_id,
        CONCAT_WS(' ', p.first_name, p.middle_name, p.last_name) AS staff_name,
        spp.kra_pin,
        SUM(ps.basic_salary) AS total_basic,
        SUM(COALESCE(ps.allowances_total, 0)) AS total_allowances,
        SUM(COALESCE(ps.gross_salary, 0)) AS total_gross,
        SUM(COALESCE(ps.paye_tax, 0)) AS total_paye,
        SUM(COALESCE(ps.nssf_contribution, 0)) AS total_nssf,
        SUM(COALESCE(ps.shif_contribution, 0)) AS total_shif,
        SUM(COALESCE(ps.housing_levy, 0)) AS total_housing_levy,
        p_year AS tax_year
    FROM staff s
    JOIN persons p ON p.id = s.person_id
    LEFT JOIN staff_payroll_profiles spp ON spp.staff_id = s.id
    JOIN payslips ps ON ps.staff_id = s.id
    WHERE s.id = p_staff_id
      AND ps.payroll_year = p_year
      AND ps.payslip_status IN ('approved', 'paid')
    GROUP BY s.id, p.first_name, p.middle_name, p.last_name, spp.kra_pin;
END$$

DELIMITER ;
