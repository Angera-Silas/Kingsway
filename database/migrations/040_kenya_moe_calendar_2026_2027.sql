-- 040_kenya_moe_calendar_2026_2027.sql
--
-- Applies the official Kenya Ministry of Education 2026/2027 term calendar and
-- cleans legacy calendar/event data:
--
--   1. Fixes academic_years metadata (2026/2027, 6 Jan 2026 - 1 Jan 2027).
--   2. Removes the incorrect Term 3 half-term (official 2026 Term 3 has NO
--      half-term; half-terms are optional and may be NULL).
--   3. Drops ALL legacy school_events (school-based events, wrong "Term 3
--      Half-Term Break", "School Campus" locations, duplicated opening days,
--      stale calendar mirrors) and re-seeds ONLY the official MoE calendar:
--      term boundaries, April/August/December holidays, national assessments.
--   4. Adds the missing gazetted religious public holidays
--      (Idd-ul-Fitr, Idd-ul-Adha - moon-sighting dependent approximations).
--   5. Regenerates the year calendar via sp_generate_year_calendar so the
--      bogus Term 3 half-term days (9-15 Oct 2026) become school days while
--      Term 1/2 half-term days and the 13/14/9-week grid are preserved.
--   6. Removes legacy 'campus'/'semester' terminology.
--
-- Official dates (Kenya MoE, pre-primary / primary / junior / senior / secondary):
--   Term 1:         6 Jan - 2 Apr 2026   (13 weeks)   Half-term 25 Feb - 1 Mar 2026
--   Term 2:        27 Apr - 31 Jul 2026  (14 weeks)   Half-term 24 Jun - 28 Jun 2026
--   Term 3:        24 Aug - 23 Oct 2026  ( 9 weeks)   NO half-term
--   April Holiday:     7 - 24 Apr 2026
--   August Holiday:    3 - 21 Aug 2026
--   December Holiday: 26 Oct 2026 - 1 Jan 2027
--   KPSEA            26 - 29 Oct 2026
--   KILEA            26 - 30 Oct 2026
--   KJSEA & KPLEA    26 Oct - 5 Nov 2026
--   KCSE              2 - 20 Nov 2026

-- ---------------------------------------------------------------------------
-- 1. Fix academic_years metadata for the 2026/2027 academic year
-- ---------------------------------------------------------------------------
UPDATE academic_years
SET year_code   = '2026/2027',
    year_name   = '2026/2027',
    start_date  = '2026-01-06',
    end_date    = '2027-01-01',
    updated_at  = CURRENT_TIMESTAMP
WHERE id = 1;

-- ---------------------------------------------------------------------------
-- 2. Term 3 (2026) has NO official half-term - clear the incorrect dates.
--    Term 1 (25 Feb - 1 Mar) and Term 2 (24 - 28 Jun) are already correct.
-- ---------------------------------------------------------------------------
UPDATE academic_year_terms
SET half_term_start = NULL,
    half_term_end   = NULL,
    updated_at      = CURRENT_TIMESTAMP
WHERE id = 3;

-- ---------------------------------------------------------------------------
-- 3. Drop ALL legacy school_events (outdated / invalid / school-based) and
--    re-seed the official Ministry of Education calendar.
-- ---------------------------------------------------------------------------
TRUNCATE TABLE school_events;

INSERT INTO school_events (id, title, description, start_at, end_at, type, location, status, source) VALUES
(1,  'Term 1 Opening Day',    'First day of Term 1 (Kenya MoE 2026/2027).',                     '2026-01-06 07:30:00',  '2026-01-06 17:00:00', 'opening',       NULL, 'past',     'manual'),
(2,  'Term 1 Closing Day',    'Last day of Term 1.',                                            '2026-04-02 12:00:00',  '2026-04-02 17:00:00', 'closing',       NULL, 'past',     'manual'),
(3,  'April Holiday',         'April holiday break (Kenya MoE 2026/2027).',                     '2026-04-07 00:00:00',  '2026-04-24 23:59:59', 'school_holiday', NULL, 'past',     'manual'),
(4,  'Term 2 Opening Day',    'First day of Term 2 (Kenya MoE 2026/2027).',                     '2026-04-27 07:30:00',  '2026-04-27 17:00:00', 'opening',       NULL, 'past',     'manual'),
(5,  'Term 2 Closing Day',    'Last day of Term 2.',                                            '2026-07-31 12:00:00',  '2026-07-31 17:00:00', 'closing',       NULL, 'past',     'manual'),
(6,  'August Holiday',        'August holiday break (Kenya MoE 2026/2027).',                    '2026-08-03 00:00:00',  '2026-08-21 23:59:59', 'school_holiday', NULL, 'ongoing',  'manual'),
(7,  'Term 3 Opening Day',    'First day of Term 3 (Kenya MoE 2026/2027).',                     '2026-08-24 07:30:00',  '2026-08-24 17:00:00', 'opening',       NULL, 'upcoming', 'manual'),
(8,  'Term 3 Closing Day',    'Last day of Term 3.',                                            '2026-10-23 12:00:00',  '2026-10-23 17:00:00', 'closing',       NULL, 'upcoming', 'manual'),
(9,  'December Holiday',      'December holiday break (Kenya MoE 2026/2027).',                  '2026-10-26 00:00:00',  '2027-01-01 23:59:59', 'school_holiday', NULL, 'upcoming', 'manual'),
(10, 'KPSEA',                'Kenya Primary Education Assessment (Kenya MoE 2026/2027).',      '2026-10-26 08:00:00',  '2026-10-29 17:00:00', 'exam',          NULL, 'upcoming', 'manual'),
(11, 'KILEA',                'Kenya Intermediate Level Education Assessment (Kenya MoE 2026/2027).', '2026-10-26 08:00:00', '2026-10-30 17:00:00', 'exam',          NULL, 'upcoming', 'manual'),
(12, 'KJSEA & KPLEA',        'Kenya Junior School Education Assessment & Kenya Pre-Vocational Level Education Assessment (Kenya MoE 2026/2027).', '2026-10-26 08:00:00', '2026-11-05 17:00:00', 'exam', NULL, 'upcoming', 'manual'),
(13, 'KCSE',                 'Kenya Certificate of Secondary Education (Kenya MoE 2026/2027).', '2026-11-02 08:00:00',  '2026-11-20 17:00:00', 'exam',          NULL, 'upcoming', 'manual');

-- ---------------------------------------------------------------------------
-- 4. Add missing gazetted religious public holidays (moon-sighting dependent
--    approximate dates for 2026). Existing gazetted holidays are preserved.
-- ---------------------------------------------------------------------------
INSERT INTO academic_year_calendar_days (id, academic_year_calendar_id, date, calendar_day_type_id, title, description) VALUES
(23, 0, '2026-03-20', 6, 'Idd-ul-Fitr', 'End of the Holy month of Ramadan (approximate - subject to moon sighting)'),
(24, 0, '2026-05-27', 6, 'Idd-ul-Adha', 'Feast of Sacrifice (approximate - subject to moon sighting)')
ON DUPLICATE KEY UPDATE
    calendar_day_type_id = VALUES(calendar_day_type_id),
    title                = VALUES(title),
    description          = VALUES(description);

-- ---------------------------------------------------------------------------
-- 5. Regenerate the year calendar (13/14/9 week grid). Term 3's bogus
--    half-term days (9-15 Oct 2026) become school days automatically.
-- ---------------------------------------------------------------------------
CALL sp_generate_year_calendar(1, 13, 14, 9);

-- ---------------------------------------------------------------------------
-- 6. Remove legacy 'campus' / 'semester' terminology (this is a CBC primary +
--    junior school for learners under 15 - not a campus, no semesters).
-- ---------------------------------------------------------------------------
UPDATE job_vacancies
SET location = 'Londiani'
WHERE location LIKE '%campus%' OR location LIKE '%Campus%';

UPDATE careers_benefits
SET description = 'Staff accommodation available for full-time teaching and boarding staff.'
WHERE id = 3 AND description LIKE '%campus%';

ALTER TABLE staff_kpi_templates
    MODIFY COLUMN `evaluation_period` ENUM('term','annual') DEFAULT 'term';

SELECT 'Migration completed - Kenya MoE 2026/2027 calendar applied' AS status;
