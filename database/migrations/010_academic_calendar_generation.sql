-- 010_academic_calendar_generation.sql
--
-- Adds sp_generate_year_calendar(), the single source of truth for building
-- a year's term calendar from the government-issued term dates recorded on
-- academic_year_terms (opening_date, half_term_start, half_term_end,
-- closing_date).
--
-- For every term of the given academic year it (re)generates:
--   * academic_year_calendar       - consecutive 7-day weeks from opening to
--                                    closing date (last week clamped). Week
--                                    counts are derived from the dates unless
--                                    an explicit count is supplied.
--   * academic_year_calendar_days  - one row per school day; days inside the
--                                    half-term break are typed 'school_holiday'
--                                    (title 'Half Term Break').
--   * school_week_config           - a default row for the year (Mon-Fri
--                                    class days, boarders all week).
--
-- The procedure is idempotent: existing weeks/days for each term are removed
-- first, so it can be re-run whenever the term dates change.
--
-- Params:
--   p_academic_year_id  target academic year
--   p_weeks_t1..3       optional explicit week counts per term (0/NULL = derive
--                       from opening/closing dates). Standard Kenyan calendar
--                       is Term 1 = 14, Term 2 = 14, Term 3 = 10 weeks.

DROP PROCEDURE IF EXISTS sp_generate_year_calendar;

DELIMITER $$

CREATE PROCEDURE sp_generate_year_calendar(
    IN p_academic_year_id INT UNSIGNED,
    IN p_weeks_t1 INT UNSIGNED,
    IN p_weeks_t2 INT UNSIGNED,
    IN p_weeks_t3 INT UNSIGNED
)
BEGIN
    DECLARE v_ayt_id      INT UNSIGNED;
    DECLARE v_term_no     INT;
    DECLARE v_opening     DATE;
    DECLARE v_closing     DATE;
    DECLARE v_ht_start    DATE;
    DECLARE v_ht_end      DATE;
    DECLARE v_done        INT DEFAULT 0;
    DECLARE v_week_no     INT UNSIGNED;
    DECLARE v_week_start  DATE;
    DECLARE v_week_end    DATE;
    DECLARE v_force_weeks INT UNSIGNED;
    DECLARE v_cal_id      INT UNSIGNED;
    DECLARE v_day_id      INT UNSIGNED;
    DECLARE v_next_id     INT UNSIGNED;
    DECLARE v_d           DATE;
    DECLARE v_school_day  INT UNSIGNED;
    DECLARE v_holiday     INT UNSIGNED;

    DECLARE term_cur CURSOR FOR
        SELECT id, term_id, opening_date, closing_date, half_term_start, half_term_end
        FROM academic_year_terms
        WHERE academic_year_id = p_academic_year_id
        ORDER BY term_id;

    DECLARE CONTINUE HANDLER FOR NOT FOUND SET v_done = 1;
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        RESIGNAL;
    END;

    SELECT id INTO v_school_day FROM calendar_day_types WHERE code = 'school_day' LIMIT 1;
    IF v_school_day IS NULL THEN
        SELECT COALESCE(MAX(id), 0) + 1 INTO v_school_day FROM calendar_day_types;
        INSERT INTO calendar_day_types (id, code, name)
        VALUES (v_school_day, 'school_day', 'School Day');
    END IF;

    SELECT id INTO v_holiday FROM calendar_day_types WHERE code = 'school_holiday' LIMIT 1;
    IF v_holiday IS NULL THEN
        SELECT COALESCE(MAX(id), 0) + 1 INTO v_holiday FROM calendar_day_types;
        INSERT INTO calendar_day_types (id, code, name)
        VALUES (v_holiday, 'school_holiday', 'School Holiday');
    END IF;

    OPEN term_cur;

    term_loop: LOOP
        FETCH term_cur INTO v_ayt_id, v_term_no, v_opening, v_closing, v_ht_start, v_ht_end;
        IF v_done THEN
            LEAVE term_loop;
        END IF;

        -- Idempotent: clear existing calendar weeks/days for this term.
        DELETE days
        FROM academic_year_calendar_days days
        JOIN academic_year_calendar c ON c.id = days.academic_year_calendar_id
        WHERE c.academic_year_term_id = v_ayt_id;

        DELETE FROM academic_year_calendar WHERE academic_year_term_id = v_ayt_id;

        IF v_opening IS NULL OR v_closing IS NULL OR v_closing < v_opening THEN
            ITERATE term_loop;
        END IF;

        -- Resolve forced week count for this term (0 = derive from dates).
        IF v_term_no = 1 THEN
            SET v_force_weeks = COALESCE(p_weeks_t1, 0);
        ELSEIF v_term_no = 2 THEN
            SET v_force_weeks = COALESCE(p_weeks_t2, 0);
        ELSE
            SET v_force_weeks = COALESCE(p_weeks_t3, 0);
        END IF;

        SET v_week_no = 0;
        SET v_week_start = v_opening;

        week_loop: WHILE v_week_start <= v_closing AND (v_force_weeks = 0 OR v_week_no < v_force_weeks) DO
            SET v_week_no = v_week_no + 1;
            SET v_week_end = DATE_ADD(v_week_start, INTERVAL 6 DAY);
            IF v_week_end > v_closing THEN
                SET v_week_end = v_closing;
            END IF;

            SELECT COALESCE(MAX(id), 0) + 1 INTO v_cal_id FROM academic_year_calendar;
            INSERT INTO academic_year_calendar (id, academic_year_term_id, week_number, week_start, week_end)
            VALUES (v_cal_id, v_ayt_id, v_week_no, v_week_start, v_week_end);

            SET v_d = v_week_start;
            day_loop: WHILE v_d <= v_week_end DO
                SELECT COALESCE(MAX(id), 0) + 1 INTO v_day_id FROM academic_year_calendar_days;

                IF (v_ht_start IS NOT NULL AND v_ht_end IS NOT NULL
                    AND v_d BETWEEN v_ht_start AND v_ht_end) THEN
                    INSERT INTO academic_year_calendar_days
                        (id, academic_year_calendar_id, date, calendar_day_type_id, title)
                    VALUES (v_day_id, v_cal_id, v_d, v_holiday, 'Half Term Break');
                ELSE
                    INSERT INTO academic_year_calendar_days
                        (id, academic_year_calendar_id, date, calendar_day_type_id, title)
                    VALUES (v_day_id, v_cal_id, v_d, v_school_day, NULL);
                END IF;

                SET v_d = DATE_ADD(v_d, INTERVAL 1 DAY);
            END WHILE day_loop;

            SET v_week_start = DATE_ADD(v_week_start, INTERVAL 7 DAY);
        END WHILE week_loop;
    END LOOP term_loop;

    CLOSE term_cur;

    INSERT INTO school_week_config (academic_year_id) VALUES (p_academic_year_id)
    ON DUPLICATE KEY UPDATE academic_year_id = academic_year_id;
END$$

DELIMITER ;
