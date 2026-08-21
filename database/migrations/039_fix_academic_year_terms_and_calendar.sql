-- 039_fix_academic_year_terms_and_calendar.sql
--
-- This migration fixes multiple issues with academic year terms and calendar:
-- 1. Adds missing updated_at column to academic_year_terms
-- 2. Updates term dates to match Kenya CBC 2026/2027 calendar from Ministry of Education
-- 3. Updates school_events with correct dates
-- 4. Regenerates calendar using sp_generate_year_calendar with weekend handling
--
-- TERM DATES (2026/2027 Academic Year) from Kenya Ministry of Education:
-- Term I: Jan 6 - Apr 2, 2026 (13 weeks)
-- Half-term: Feb 25 - Mar 1, 2026 (5 days)
-- Term II: Apr 27 - Jul 31, 2026 (14 weeks)
-- Half-term: Jun 24 - Jun 28, 2026 (5 days)
-- Term III: Aug 24 - Oct 23, 2026 (9 weeks)
-- Half-term: Oct 9 - Oct 15, 2026 (5 days)
--
-- Assessorships:
-- KPSEA: Oct 26 - Oct 29, 2026 (4 days)
-- KILEA: Oct 26 - Oct 30, 2026 (5 days)
-- KJSEA & KPLEA: Oct 26 - Nov 5, 2026 (7 days)
-- KCSE: Nov 2 - Nov 20, 2026 (3 weeks)
-- December Holiday: Oct 26, 2026 - Jan 1, 2027 (10 weeks)

-- Step 1: Add updated_at column if missing
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
                  WHERE TABLE_SCHEMA = DATABASE() 
                  AND TABLE_NAME = 'academic_year_terms' 
                  AND COLUMN_NAME = 'updated_at');

SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE academic_year_terms ADD COLUMN updated_at TIMESTAMP NULL DEFAULT NULL', 
    'SELECT ""');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Set updated_at on existing rows
UPDATE academic_year_terms SET updated_at = CURRENT_TIMESTAMP WHERE updated_at IS NULL;

-- Step 2: Insert Term 1 with correct dates if missing
INSERT INTO academic_year_terms (id, academic_year_id, term_id, opening_date, half_term_start, half_term_end, closing_date, status, updated_at)
VALUES (1, 1, 1, '2026-01-06', '2026-02-25', '2026-03-01', '2026-04-02', 'completed', CURRENT_TIMESTAMP)
ON DUPLICATE KEY UPDATE
    opening_date = VALUES(opening_date),
    half_term_start = VALUES(half_term_start),
    half_term_end = VALUES(half_term_end),
    closing_date = VALUES(closing_date),
    status = CASE 
        WHEN VALUES(closing_date) < CURDATE() THEN 'completed'
        WHEN VALUES(opening_date) <= CURDATE() THEN 'current'
        ELSE 'upcoming'
    END,
    updated_at = VALUES(updated_at);

-- Step 3: Update Term 2 with correct dates
UPDATE academic_year_terms SET
    opening_date = '2026-04-27',
    half_term_start = '2026-06-24',
    half_term_end = '2026-06-28',
    closing_date = '2026-07-31',
    status = CASE WHEN CURDATE() BETWEEN '2026-04-27' AND '2026-07-31' THEN 'current' ELSE 'completed' END,
    updated_at = CURRENT_TIMESTAMP
WHERE term_id = 2 AND academic_year_id = 1;

-- Step 4: Update Term 3 with correct dates
UPDATE academic_year_terms SET
    opening_date = '2026-08-24',
    half_term_start = '2026-10-09',
    half_term_end = '2026-10-15',
    closing_date = '2026-10-23',
    status = CASE WHEN CURDATE() BETWEEN '2026-08-24' AND '2026-10-23' THEN 'current' ELSE 'upcoming' END,
    updated_at = CURRENT_TIMESTAMP
WHERE term_id = 3 AND academic_year_id = 1;

-- Step 5: Update school_events with correct dates from Kenya MoE
-- Clear existing events and re-seed with correct dates
TRUNCATE TABLE school_events;

INSERT INTO school_events (id, title, description, start_at, end_at, `type`, location, status, created_at, updated_at) VALUES
-- Term 1 Events
(1, 'Term 1 Opening Day', 'First day of Term 1 classes', '2026-01-06 08:00:00', '2026-01-06 17:00:00', 'Academic', 'All Classrooms', 'past', NOW(), NOW()),
(2, 'Half Term Break', 'Mid-term break for Term 1', '2026-02-25 00:00:00', '2026-03-01 23:59:59', 'school_holiday', 'School Campus', 'past', NOW(), NOW()),
(3, 'Term 1 Half-Term Ends', 'Return from mid-term break', '2026-03-02 08:00:00', '2026-03-02 17:00:00', 'Academic', 'All Classrooms', 'past', NOW(), NOW()),
-- Ministry holidays and events for Term 1
(4, 'Good Friday', 'Public holiday', '2026-04-03 00:00:00', '2026-04-03 23:59:59', 'public_holiday', 'School Campus', 'past', NOW(), NOW()),
(5, 'Easter Monday', 'Public holiday', '2026-04-06 00:00:00', '2026-04-06 23:59:59', 'public_holiday', 'School Campus', 'past', NOW(), NOW()),
(6, 'Madaraka Day', 'Public holiday celebrating Constitution day', '2026-06-01 00:00:00', '2026-06-01 23:59:59', 'public_holiday', 'School Campus', 'past', NOW(), NOW()),

-- Term 2 Events  
(7, 'Term 2 Opening Day', 'Second term begins', '2026-04-27 08:00:00', '2026-04-27 17:00:00', 'Academic', 'All Classrooms', 'past', NOW(), NOW()),
(8, 'Labour Day', 'Public holiday', '2026-05-01 00:00:00', '2026-05-01 23:59:59', 'public_holiday', 'School Campus', 'past', NOW(), NOW()),
(9, 'End of Term 2 Examinations Begin', 'End of term examinations', '2026-05-11 07:30:00', NULL, 'Academic', 'All Classrooms', 'past', NOW(), NOW()),
(10, 'Term 2 Parent-Teacher Feedback Day', 'Parent-teacher meeting', '2026-05-18 08:00:00', NULL, 'Meeting', 'All Classrooms', 'past', NOW(), NOW()),
(11, 'Annual Prize-Giving & Awards Ceremony', 'School awards ceremony', '2026-05-25 10:00:00', NULL, 'Ceremony', 'School Assembly Ground', 'past', NOW(), NOW()),
(12, 'Term 2 Closing Day', 'Last day of Term 2', '2026-07-31 12:00:00', NULL, 'Academic', 'School Campus', 'past', NOW(), NOW()),

-- Term 2 Half-Term Break
(13, 'Half Term Break', 'Mid-term break for Term 2', '2026-06-24 00:00:00', '2026-06-28 23:59:59', 'school_holiday', 'School Campus', 'past', NOW(), NOW()),

-- Term 3 Events
(14, 'Term 3 Opening Day', 'Third term begins', '2026-08-24 08:00:00', '2026-08-24 17:00:00', 'Academic', 'All Classrooms', 'upcoming', NOW(), NOW()),
(15, 'Term 3 Half-Term Break', 'Mid-term break for Term 3', '2026-10-09 00:00:00', '2026-10-15 23:59:59', 'school_holiday', 'School Campus', 'upcoming', NOW(), NOW()),
(16, 'Term 3 Closing Day', 'Last day of Term 3', '2026-10-23 12:00:00', NULL, 'Academic', 'School Campus', 'upcoming', NOW(), NOW()),

-- Assessorships
(17, 'KPSEA', 'Kenya Primary Education Assessment', '2026-10-26 08:00:00', '2026-10-29 17:00:00', 'Exam', 'Exam Centers', 'upcoming', NOW(), NOW()),
(18, 'KILEA', 'Kenya Intermediate Level Education Assessment', '2026-10-26 08:00:00', '2026-10-30 17:00:00', 'Exam', 'Exam Centers', 'upcoming', NOW(), NOW()),
(19, 'KJSEA & KPLEA', 'Kenya Junior School Education Assessment', '2026-10-26 08:00:00', '2026-11-05 17:00:00', 'Exam', 'Exam Centers', 'upcoming', NOW(), NOW()),
(20, 'KCSE', 'Kenya Certificate of Secondary Education', '2026-11-02 08:00:00', '2026-11-20 17:00:00', 'Exam', 'Exam Centers', 'upcoming', NOW(), NOW());

-- Step 6: Regenerate calendar using the existing procedure
-- This will properly mark weekends as 'weekend' type and school days as 'school_day'
CALL sp_generate_year_calendar(1, 13, 14, 9);

SELECT 'Migration completed successfully - Terms, Events, and Calendar updated with Kenya MoE 2026/2027 dates' as status;