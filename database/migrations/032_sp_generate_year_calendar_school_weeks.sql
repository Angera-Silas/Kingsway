-- 032_sp_generate_year_calendar_school_weeks.sql
--
-- Rewrites sp_generate_year_calendar so term calendars use REAL school weeks
-- instead of a rolling 7-day grid:
--
--   * A school week runs Monday-Friday. Week 1 starts on the opening day when
--     that is a Mon-Fri (e.g. reopening Wed 26 Aug 2026 => Week 1 = 26-28 Aug,
--     ending on the first Friday). A weekend opening starts week 1 the
--     following Monday.
--   * Week 2+ each run Monday-Friday with the weekend (Sat+Sun) in between.
--   * The final week is clamped to the closing date, so a midweek close yields
--     a short last week (e.g. close Wed 4 Nov 2026 => Week ends Wed 4 Nov).
--     If the term closes ON a weekend, that weekend is folded into the last
--     week (boarding learners stay in school through the close).
--   * Saturday/Sunday rows are typed 'weekend' (NOT school_holiday): boarding
--     attendance still counts on weekends and a weekend timetable applies.
--     Half-term break days are still typed 'school_holiday' and may begin
--     midweek.
--
-- Adds the 'weekend' row to calendar_day_types if missing
-- (affects_day_students=0, affects_boarders=1, requires_attendance=1): day
-- learners and weekly boarders are off; full boarders stay and their
-- attendance still counts on weekends.

DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_generate_year_calendar`$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_generate_year_calendar` (IN `p_academic_year_id` INT UNSIGNED, IN `p_weeks_t1` INT UNSIGNED, IN `p_weeks_t2` INT UNSIGNED, IN `p_weeks_t3` INT UNSIGNED)   BEGIN
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
    DECLARE v_d           DATE;
    DECLARE v_day_end     DATE;
    DECLARE v_trailing    DATE;
    DECLARE v_dow         INT;
    DECLARE v_school_day  INT UNSIGNED;
    DECLARE v_holiday     INT UNSIGNED;
    DECLARE v_weekend     INT UNSIGNED;

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

    -- Weekend: full boarders stay (attendance counts), day learners and
    -- weekly boarders are off; weekend timetable applies.
    SELECT id INTO v_weekend FROM calendar_day_types WHERE code = 'weekend' LIMIT 1;
    IF v_weekend IS NULL THEN
        SELECT COALESCE(MAX(id), 0) + 1 INTO v_weekend FROM calendar_day_types;
        INSERT INTO calendar_day_types (id, code, name, affects_day_students, affects_boarders, requires_attendance)
        VALUES (v_weekend, 'weekend', 'Weekend', 0, 1, 1);
    END IF;

    -- Day-type lookups above may raise NOT FOUND (e.g. the first run creating
    -- the 'weekend' type), which the cursor handler would otherwise absorb and
    -- cause an early exit. Reset the flag before processing terms.
    SET v_done = 0;

    OPEN term_cur;

    term_loop: LOOP
        FETCH term_cur INTO v_ayt_id, v_term_no, v_opening, v_closing, v_ht_start, v_ht_end;
        IF v_done THEN
            LEAVE term_loop;
        END IF;

        DELETE days
        FROM academic_year_calendar_days days
        JOIN academic_year_calendar c ON c.id = days.academic_year_calendar_id
        WHERE c.academic_year_term_id = v_ayt_id;

        DELETE FROM academic_year_calendar WHERE academic_year_term_id = v_ayt_id;

        IF v_opening IS NULL OR v_closing IS NULL OR v_closing < v_opening THEN
            ITERATE term_loop;
        END IF;

        IF v_term_no = 1 THEN
            SET v_force_weeks = COALESCE(p_weeks_t1, 0);
        ELSEIF v_term_no = 2 THEN
            SET v_force_weeks = COALESCE(p_weeks_t2, 0);
        ELSE
            SET v_force_weeks = COALESCE(p_weeks_t3, 0);
        END IF;

        -- First school week: starts on the opening day when Mon-Fri, otherwise
        -- the following Monday. DAYOFWEEK: 1=Sun .. 7=Sat.
        SET v_week_no = 0;
        SET v_week_start = v_opening;
        SET v_dow = DAYOFWEEK(v_week_start);
        IF v_dow = 1 THEN
            SET v_week_start = DATE_ADD(v_week_start, INTERVAL 1 DAY);
        ELSEIF v_dow = 7 THEN
            SET v_week_start = DATE_ADD(v_week_start, INTERVAL 2 DAY);
        END IF;

        week_loop: WHILE v_week_start <= v_closing AND (v_force_weeks = 0 OR v_week_no < v_force_weeks) DO
            SET v_week_no = v_week_no + 1;

            -- Week ends on the Friday of its week; clamped to the closing date.
            SET v_dow = DAYOFWEEK(v_week_start);
            SET v_week_end = DATE_ADD(v_week_start, INTERVAL (6 - v_dow) DAY);
            IF v_week_end > v_closing THEN
                SET v_week_end = v_closing;
            END IF;
            -- If the term closes ON the weekend that follows this teaching
            -- week, fold that weekend into the last week (boarding learners
            -- stay through the close).
            IF v_week_end < v_closing
               AND (DAYOFWEEK(v_closing) = 1 OR DAYOFWEEK(v_closing) = 7)
               AND v_closing <= DATE_ADD(v_week_end, INTERVAL 2 DAY) THEN
                SET v_week_end = v_closing;
            END IF;

            SELECT COALESCE(MAX(id), 0) + 1 INTO v_cal_id FROM academic_year_calendar;
            INSERT INTO academic_year_calendar (id, academic_year_term_id, week_number, week_start, week_end)
            VALUES (v_cal_id, v_ayt_id, v_week_no, v_week_start, v_week_end);

            -- The calendar row spans the teaching week (Mon-Fri). The weekend
            -- that follows it (Sat and/or Sun) is also within the term and is
            -- stored as days of the same row, typed 'weekend' (or
            -- 'school_holiday' when inside a half-term break), so boarding
            -- attendance and weekend timetables can reference those dates.
            SET v_day_end = v_week_end;
            SET v_trailing = DATE_ADD(v_week_end, INTERVAL 1 DAY);
            IF v_trailing <= v_closing AND DAYOFWEEK(v_trailing) = 7 THEN
                SET v_day_end = v_trailing;
                SET v_trailing = DATE_ADD(v_trailing, INTERVAL 1 DAY);
                IF v_trailing <= v_closing AND DAYOFWEEK(v_trailing) = 1 THEN
                    SET v_day_end = v_trailing;
                END IF;
            END IF;

            SET v_d = v_week_start;
            day_loop: WHILE v_d <= v_day_end DO
                SELECT COALESCE(MAX(id), 0) + 1 INTO v_day_id FROM academic_year_calendar_days;

                IF (v_ht_start IS NOT NULL AND v_ht_end IS NOT NULL
                    AND v_d BETWEEN v_ht_start AND v_ht_end) THEN
                    INSERT INTO academic_year_calendar_days
                        (id, academic_year_calendar_id, date, calendar_day_type_id, title)
                    VALUES (v_day_id, v_cal_id, v_d, v_holiday, 'Half Term Break');
                ELSEIF (DAYOFWEEK(v_d) = 1 OR DAYOFWEEK(v_d) = 7) THEN
                    -- Weekend: boarding attendance counts, weekend timetable applies.
                    INSERT INTO academic_year_calendar_days
                        (id, academic_year_calendar_id, date, calendar_day_type_id, title)
                    VALUES (v_day_id, v_cal_id, v_d, v_weekend, 'Weekend');
                ELSE
                    INSERT INTO academic_year_calendar_days
                        (id, academic_year_calendar_id, date, calendar_day_type_id, title)
                    VALUES (v_day_id, v_cal_id, v_d, v_school_day, NULL);
                END IF;

                SET v_d = DATE_ADD(v_d, INTERVAL 1 DAY);
            END WHILE day_loop;

            -- Advance to the Monday of the next school week.
            SET v_week_start = DATE_ADD(v_week_end, INTERVAL 3 DAY);
        END WHILE week_loop;
    END LOOP term_loop;

    CLOSE term_cur;

    INSERT INTO school_week_config (academic_year_id) VALUES (p_academic_year_id)
    ON DUPLICATE KEY UPDATE academic_year_id = academic_year_id;
END$$

DELIMITER ;
