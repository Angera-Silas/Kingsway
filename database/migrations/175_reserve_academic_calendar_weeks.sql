-- Academic calendar weeks can be reserved for examinations, closing,
-- assessment moderation, trips or other non-scheme activities.
ALTER TABLE academic_year_calendar
    ADD COLUMN week_purpose ENUM('instructional','reserved') NOT NULL DEFAULT 'instructional' AFTER week_end,
    ADD COLUMN reserved_reason VARCHAR(255) NULL AFTER week_purpose;

-- The final two calendar weeks are reserved by default. Administrators may
-- change either week back to instructional when the school timetable requires
-- teaching during that period.
UPDATE academic_year_calendar c
JOIN (
    SELECT academic_year_term_id, MAX(week_number) AS last_week
    FROM academic_year_calendar
    GROUP BY academic_year_term_id
) x ON x.academic_year_term_id = c.academic_year_term_id
SET c.week_purpose = 'reserved',
    c.reserved_reason = 'Examinations, assessment moderation and term closing activities'
WHERE c.week_number >= x.last_week - 1;
