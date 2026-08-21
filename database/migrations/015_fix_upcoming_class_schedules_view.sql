-- 015_fix_upcoming_class_schedules_view.sql
-- `vw_upcoming_class_schedules` was built against `timetable_entries` but filtered on
-- `status = 'active'`. The live enum is scheduled|cancelled|rescheduled, so the view
-- always returned 0 rows. Re-create it with the correct status filter.
CREATE OR REPLACE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_upcoming_class_schedules` AS
select `te`.`id` AS `id`,
`ayc`.`class_id` AS `class_id`,
`c`.`name` AS `class_name`,
`te`.`day_of_week` AS `day_of_week`,
`ts`.`start_time` AS `start_time`,
`ts`.`end_time` AS `end_time`,
`ts`.`period_number` AS `period_number`,
`te`.`learning_area_id` AS `subject_id`,
coalesce(`la`.`name`,'') AS `subject_name`,
`te`.`teacher_id` AS `teacher_id`,
concat(`p`.`first_name`,' ',`p`.`last_name`) AS `teacher_name`,
NULL AS `room_id`,
NULL AS `room_name`,
`ayt`.`academic_year_id` AS `academic_year_id`,
`ayt`.`id` AS `term_id`,
`te`.`status` AS `status`
from ((((((((`timetable_entries` `te`
join `academic_year_class_streams` `aycs` on(`aycs`.`id` = `te`.`academic_year_class_stream_id`))
join `academic_year_classes` `ayc` on(`ayc`.`id` = `aycs`.`academic_year_class_id`))
join `classes` `c` on(`c`.`id` = `ayc`.`class_id`))
left join `academic_year_terms` `ayt` on(`ayt`.`id` = `te`.`academic_year_term_id`))
left join `time_slots` `ts` on(`ts`.`id` = `te`.`time_slot_id`))
left join `learning_areas` `la` on(`la`.`id` = `te`.`learning_area_id`))
left join `staff` `s` on(`te`.`teacher_id` = `s`.`id`))
left join `persons` `p` on(`p`.`id` = `s`.`person_id`))
where `te`.`status` = 'scheduled';
