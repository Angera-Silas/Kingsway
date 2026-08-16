-- 019_create_report_views.sql
-- Adds vw_assessment_results_detail, the single normalized join used by the
-- reports module for both the score-distribution report and the per-class/term
-- exam report (assessment_results -> assessments -> academic_year_terms ->
-- terms/academic_years and to the class stream via the assessment's class).
--
-- The other report queries reuse existing views already wired into the live
-- DB (vw_current_enrollments, vw_student_term_attendance_summary), so no extra
-- views are created for those.
--
-- Applied to live DB: 2026-08-08

CREATE OR REPLACE VIEW `vw_assessment_results_detail` AS
SELECT
    `ar`.`id` AS `result_id`,
    `ar`.`assessment_id` AS `assessment_id`,
    `ar`.`student_academic_enrollment_id` AS `student_academic_enrollment_id`,
    `sae`.`student_id` AS `student_id`,
    `a`.`academic_year_class_stream_id` AS `academic_year_class_stream_id`,
    `a`.`academic_year_term_id` AS `academic_year_term_id`,
    `ayt`.`academic_year_id` AS `academic_year_id`,
    `ayt`.`term_id` AS `term_id`,
    `t`.`name` AS `term_name`,
    `t`.`code` AS `term_number`,
    `ay`.`year_code` AS `year_code`,
    `ay`.`year_name` AS `year_name`,
    `ayc`.`class_id` AS `class_id`,
    `c`.`name` AS `class_name`,
    `aycs`.`stream_id` AS `stream_id`,
    `sn`.`name` AS `stream_name`,
    `a`.`max_marks` AS `max_marks`,
    `ar`.`marks_obtained` AS `marks_obtained`,
    ROUND(`ar`.`marks_obtained` / NULLIF(`a`.`max_marks`, 0) * 100, 2) AS `percentage`,
    CASE
        WHEN `ar`.`marks_obtained` / NULLIF(`a`.`max_marks`, 0) * 100 >= 80 THEN 'EE'
        WHEN `ar`.`marks_obtained` / NULLIF(`a`.`max_marks`, 0) * 100 >= 50 THEN 'ME'
        WHEN `ar`.`marks_obtained` / NULLIF(`a`.`max_marks`, 0) * 100 >= 25 THEN 'AE'
        ELSE 'BE'
    END AS `grade_band`
FROM `assessment_results` `ar`
JOIN `assessments` `a` ON `a`.`id` = `ar`.`assessment_id`
JOIN `academic_year_terms` `ayt` ON `ayt`.`id` = `a`.`academic_year_term_id`
JOIN `terms` `t` ON `t`.`id` = `ayt`.`term_id`
JOIN `academic_years` `ay` ON `ay`.`id` = `ayt`.`academic_year_id`
JOIN `student_academic_enrollments` `sae` ON `sae`.`id` = `ar`.`student_academic_enrollment_id`
LEFT JOIN `academic_year_class_streams` `aycs` ON `aycs`.`id` = `a`.`academic_year_class_stream_id`
LEFT JOIN `academic_year_classes` `ayc` ON `ayc`.`id` = `aycs`.`academic_year_class_id`
LEFT JOIN `classes` `c` ON `c`.`id` = `ayc`.`class_id`
LEFT JOIN `streams` `sn` ON `sn`.`id` = `aycs`.`stream_id`
WHERE `a`.`max_marks` > 0 AND `ar`.`marks_obtained` IS NOT NULL;
