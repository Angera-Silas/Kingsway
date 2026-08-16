-- 041_school_holidays_registry.sql
--
-- Introduces a UI-managed HOLIDAY REGISTRY so nothing about holidays is
-- hardcoded:
--
--   1. Creates `school_holidays` - the single registry of all holidays
--      (national, religious, inter-term, school). Admins add/edit/remove and
--      re-date holidays here (e.g. Idd-ul-Fitr / Idd-ul-Adha move with the
--      moon) and the calendar picks them up on regeneration - exactly the way
--      the generator already handles weekends, school days and half-terms.
--   2. Rewrites sp_generate_year_calendar so it:
--        * titles the first/last term day "Term N Opening Day" / "Term N
--          Closing Day" straight from academic_year_terms (single source -
--          no free-form events that could contradict the term dates);
--        * deletes stale auto holiday day rows (academic_year_calendar_id = 0,
--          is_manual = 0) for the year and re-applies them from
--          school_holidays. Dates inside a term override that day's type
--          (public_holiday for national/religious, school_holiday for
--          inter-term/school); dates between terms become calendar_id = 0
--          rows for the full span. Manual / event-created days (is_manual=1)
--          are preserved.
--   3. Removes the free-form opening/closing/holiday events seeded by
--      migration 040 - those are calendar-day titles now. The timed national
--      assessments (KPSEA/KILEA/KJSEA & KPLEA/KCSE) stay as events because
--      they carry start/end times.

-- ---------------------------------------------------------------------------
-- 1. Holiday registry table
-- ---------------------------------------------------------------------------
DROP TABLE IF EXISTS `school_holidays`;
CREATE TABLE `school_holidays` (
    `id`            INT UNSIGNED NOT NULL,
    `name`          VARCHAR(150) NOT NULL,
    `holiday_type`  ENUM('national','religious','inter_term','school') NOT NULL DEFAULT 'school',
    `start_date`    DATE NOT NULL,
    `end_date`      DATE NOT NULL,
    `description`   TEXT NULL,
    `is_active`     TINYINT(1) NOT NULL DEFAULT 1,
    `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_holiday_dates` (`start_date`, `end_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='UI-managed holiday registry';

-- Official Kenya 2026/2027 holidays (Kenya MoE circular). Islamic holidays are
-- approximations - subject to moon sighting - and are edited via the UI.
INSERT INTO `school_holidays` (`id`, `name`, `holiday_type`, `start_date`, `end_date`, `description`, `is_active`) VALUES
(1,  'New Year\'s Day',    'national',   '2026-01-01', '2026-01-01', 'Public holiday', 1),
(2,  'Good Friday',        'national',   '2026-04-03', '2026-04-03', 'Public holiday', 1),
(3,  'Easter Monday',      'national',   '2026-04-06', '2026-04-06', 'Public holiday', 1),
(4,  'Labour Day',         'national',   '2026-05-01', '2026-05-01', 'Public holiday', 1),
(5,  'Madaraka Day',       'national',   '2026-06-01', '2026-06-01', 'Public holiday', 1),
(6,  'Huduma Day',         'national',   '2026-10-10', '2026-10-10', 'Public holiday', 1),
(7,  'Mashujaa Day',       'national',   '2026-10-20', '2026-10-20', 'Public holiday', 1),
(8,  'Jamhuri Day',        'national',   '2026-12-12', '2026-12-12', 'Public holiday', 1),
(9,  'Christmas Day',      'national',   '2026-12-25', '2026-12-25', 'Public holiday', 1),
(10, 'Boxing Day',         'national',   '2026-12-26', '2026-12-26', 'Public holiday', 1),
(11, 'Idd-ul-Fitr',        'religious',  '2026-03-20', '2026-03-20', 'End of Ramadan - approximate, subject to moon sighting', 1),
(12, 'Idd-ul-Adha',        'religious',  '2026-05-27', '2026-05-27', 'Feast of Sacrifice - approximate, subject to moon sighting', 1),
(13, 'April Holiday',      'inter_term', '2026-04-07', '2026-04-24', 'April holiday between Term 1 and Term 2', 1),
(14, 'August Holiday',     'inter_term', '2026-08-03', '2026-08-21', 'August holiday between Term 2 and Term 3', 1),
(15, 'December Holiday',   'inter_term', '2026-10-26', '2027-01-01', 'December holiday between Term 3 and the next academic year', 1);

-- ---------------------------------------------------------------------------
-- 2. Rewrite sp_generate_year_calendar - holidays come from the registry
-- ---------------------------------------------------------------------------
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
    DECLARE v_public_hol  INT UNSIGNED;

    -- Holiday registry application
    DECLARE v_h_name      VARCHAR(150);
    DECLARE v_h_type      ENUM('national','religious','inter_term','school');
    DECLARE v_h_start     DATE;
    DECLARE v_h_end       DATE;
    DECLARE v_h_day_type  INT UNSIGNED;
    DECLARE v_year_start  DATE;
    DECLARE v_year_end    DATE;
    DECLARE v_hol_done    INT DEFAULT 0;

    DECLARE term_cur CURSOR FOR
        SELECT id, term_id, opening_date, closing_date, half_term_start, half_term_end
        FROM academic_year_terms
        WHERE academic_year_id = p_academic_year_id
        ORDER BY term_id;

    DECLARE holiday_cur CURSOR FOR
        SELECT name, holiday_type, start_date, end_date
        FROM school_holidays
        WHERE is_active = 1
          AND end_date >= v_year_start
          AND start_date <= v_year_end
        ORDER BY start_date;

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

    SELECT id INTO v_public_hol FROM calendar_day_types WHERE code = 'public_holiday' LIMIT 1;
    IF v_public_hol IS NULL THEN
        SELECT COALESCE(MAX(id), 0) + 1 INTO v_public_hol FROM calendar_day_types;
        INSERT INTO calendar_day_types (id, code, name)
        VALUES (v_public_hol, 'public_holiday', 'Public Holiday');
    END IF;

    -- Day-type lookups above may raise NOT FOUND, which the cursor handler
    -- would otherwise absorb and cause an early exit. Reset before processing.
    SET v_done = 0;

    -- Year span used to scope the holiday application.
    SELECT start_date, end_date INTO v_year_start, v_year_end
    FROM academic_years WHERE id = p_academic_year_id;

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
            -- 'school_holiday' when inside a half-term break).
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

        -- Single source of truth for opening/closing days: title the actual
        -- term-boundary day rows from academic_year_terms. Free-form
        -- opening/closing events are no longer created anywhere.
        UPDATE academic_year_calendar_days d
        JOIN academic_year_calendar c ON c.id = d.academic_year_calendar_id
        SET d.title = CONCAT('Term ', v_term_no, ' Opening Day')
        WHERE c.academic_year_term_id = v_ayt_id
          AND d.date = v_opening AND (d.title IS NULL OR d.title = '');

        UPDATE academic_year_calendar_days d
        JOIN academic_year_calendar c ON c.id = d.academic_year_calendar_id
        SET d.title = CONCAT('Term ', v_term_no, ' Closing Day')
        WHERE c.academic_year_term_id = v_ayt_id
          AND d.date = v_closing AND (d.title IS NULL OR d.title = '');
    END LOOP term_loop;

    CLOSE term_cur;

    -- -----------------------------------------------------------------------
    -- Apply the UI-managed holiday registry (weekends/school days are handled
    -- above; holidays are handled here - all from the database, nothing
    -- hardcoded).
    -- -----------------------------------------------------------------------
    SET v_done = 0;

    -- Stale auto holiday rows for this year are removed and re-applied from
    -- school_holidays. Manually-marked / event-created days survive.
    DELETE FROM academic_year_calendar_days
    WHERE academic_year_calendar_id = 0
      AND is_manual = 0
      AND date BETWEEN v_year_start AND v_year_end;

    OPEN holiday_cur;

    holiday_loop: LOOP
        FETCH holiday_cur INTO v_h_name, v_h_type, v_h_start, v_h_end;
        IF v_done THEN
            LEAVE holiday_loop;
        END IF;

        SET v_h_day_type = IF(v_h_type IN ('national','religious'), v_public_hol, v_holiday);

        SET v_d = v_h_start;
        holiday_day_loop: WHILE v_d <= v_h_end DO
            IF v_d BETWEEN v_year_start AND v_year_end THEN
                -- 1) A term day exists for this date? Override it (unless it is
                --    a half-term break - the break wins inside a term).
                IF EXISTS (
                    SELECT 1
                    FROM academic_year_calendar_days d
                    JOIN academic_year_calendar c ON c.id = d.academic_year_calendar_id
                    JOIN academic_year_terms ayt ON ayt.id = c.academic_year_term_id
                    WHERE ayt.academic_year_id = p_academic_year_id
                      AND d.date = v_d
                      AND (d.title IS NULL OR d.title = '' OR d.title = 'Weekend')
                ) THEN
                    UPDATE academic_year_calendar_days d
                    JOIN academic_year_calendar c ON c.id = d.academic_year_calendar_id
                    JOIN academic_year_terms ayt ON ayt.id = c.academic_year_term_id
                    SET d.calendar_day_type_id = v_h_day_type, d.title = v_h_name, d.is_manual = 0
                    WHERE ayt.academic_year_id = p_academic_year_id
                      AND d.date = v_d
                      AND (d.title IS NULL OR d.title = '' OR d.title = 'Weekend');
                ELSEIF EXISTS (
                    SELECT 1 FROM academic_year_calendar_days
                    WHERE academic_year_calendar_id = 0 AND date = v_d AND is_manual = 0
                ) THEN
                    -- 2) Auto year-wide row exists: refresh from the registry.
                    UPDATE academic_year_calendar_days
                    SET calendar_day_type_id = v_h_day_type, title = v_h_name
                    WHERE academic_year_calendar_id = 0 AND date = v_d AND is_manual = 0;
                ELSEIF NOT EXISTS (
                    SELECT 1 FROM academic_year_calendar_days
                    WHERE academic_year_calendar_id = 0 AND date = v_d
                ) THEN
                    -- 3) No row at all: create one. Manually-marked rows
                    --    (is_manual = 1) are deliberately left untouched.
                    SELECT COALESCE(MAX(id), 0) + 1 INTO v_day_id FROM academic_year_calendar_days;
                    INSERT INTO academic_year_calendar_days
                        (id, academic_year_calendar_id, date, calendar_day_type_id, title)
                    VALUES (v_day_id, 0, v_d, v_h_day_type, v_h_name);
                END IF;
            END IF;
            SET v_d = DATE_ADD(v_d, INTERVAL 1 DAY);
        END WHILE holiday_day_loop;
    END LOOP holiday_loop;

    CLOSE holiday_cur;

    INSERT INTO school_week_config (academic_year_id) VALUES (p_academic_year_id)
    ON DUPLICATE KEY UPDATE academic_year_id = academic_year_id;
END$$

DELIMITER ;

-- ---------------------------------------------------------------------------
-- 3. Free-form events that are now calendar-day records (single source of
--    truth). The timed national assessments stay as events.
-- ---------------------------------------------------------------------------
DELETE FROM school_events WHERE source = 'manual' AND type IN ('opening', 'closing');
DELETE FROM school_events WHERE source = 'manual' AND title IN ('April Holiday', 'August Holiday', 'December Holiday');

-- Regenerate the 2026/2027 calendar so the registry holidays, opening/closing
-- titles and the removed Term 3 half-term are all reflected.
CALL sp_generate_year_calendar(1, 13, 14, 9);

SELECT 'Migration completed - holiday registry created and calendar regenerated' AS status;
