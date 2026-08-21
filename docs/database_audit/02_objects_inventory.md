# KingsWay Academy — Database Object Inventory (Phase 2)

Source of truth: `database/KingsWayDatabase_2026_08_01_1409hrs.sql`. Generated 2026-08-01 13:25.

## Views (84)

| View | Definition |
|---|---|
| `class_assignments` | `select `sca`.`class_id` AS `class_id`,`sca`.`staff_id` AS `user_id`,`sca`.`role` AS `role`,`sca`.`stream_id` AS `stream`,coalesce(`cs`.`stream_name`,'') AS `section`,coalesce(`c`.`name`,'') AS `form`,`sca`.`subject_id` A…` |
| `vw_active_allocations` | `select `a`.`id` AS `id`,`a`.`allocation_number` AS `allocation_number`,`i`.`name` AS `item_name`,`i`.`code` AS `item_code`,`ic`.`name` AS `category`,`a`.`allocated_quantity` AS `allocated_quantity`,`a`.`returned_quantity…` |
| `vw_active_salary_advances` | `select `sa`.`id` AS `id`,`sa`.`staff_id` AS `staff_id`,`sa`.`advance_number` AS `advance_number`,`sa`.`approved_amount` AS `approved_amount`,`sa`.`balance_remaining` AS `balance_remaining`,`sa`.`amount_per_deduction` AS …` |
| `vw_arrears_summary` | `select `sl`.`name` AS `level`,`sl`.`code` AS `level_code`,count(distinct `sa`.`student_id`) AS `students_in_arrears`,sum(`sa`.`total_arrears`) AS `total_arrears_amount`,avg(`sa`.`total_arrears`) AS `average_arrears`,coun…` |
| `vw_asset_depreciation_summary` | `select `ac`.`name` AS `category`,count(`fa`.`id`) AS `asset_count`,sum(`fa`.`purchase_price`) AS `total_original_cost`,sum(`fa`.`accumulated_depr`) AS `total_accumulated_depr`,sum(`fa`.`current_book_value`) AS `total_boo…` |
| `vw_attendance_by_context` | `select `sa`.`id` AS `id`,`sa`.`student_id` AS `student_id`,`sa`.`date` AS `date`,`sa`.`status` AS `status`,`sa`.`register_type` AS `register_type`,`sa`.`absence_reason` AS `absence_reason`,`sa`.`check_in_time` AS `check_…` |
| `vw_available_fee_credits` | `select `fcn`.`id` AS `id`,`fcn`.`credit_number` AS `credit_number`,`fcn`.`student_id` AS `student_id`,concat(`st`.`first_name`,' ',`st`.`last_name`) AS `student_name`,`st`.`admission_no` AS `admission_no`,`fcn`.`academic…` |
| `vw_boarding_roll_call` | `select `d`.`id` AS `dormitory_id`,`d`.`name` AS `dormitory_name`,`d`.`code` AS `dormitory_code`,concat(`hp`.`first_name`,' ',`hp`.`last_name`) AS `house_parent`,`ba`.`date` AS `date`,`ass`.`name` AS `session_name`,`ass`.…` |
| `vw_boarding_roll_call_today` | `select `s`.`id` AS `student_id`,`s`.`admission_no` AS `admission_no`,concat(`s`.`first_name`,' ',`s`.`last_name`) AS `student_name`,`cs`.`stream_name` AS `stream_name`,`c`.`name` AS `class_name`,`d`.`name` AS `dormitory_…` |
| `vw_budget_utilization` | `select `b`.`id` AS `budget_id`,`b`.`name` AS `budget_name`,`b`.`academic_year` AS `academic_year`,`b`.`term` AS `term`,`b`.`total_amount` AS `total_amount`,`b`.`status` AS `budget_status`,sum(`bli`.`allocated_amount`) AS…` |
| `vw_class_rosters` | `select `cya`.`id` AS `assignment_id`,`ay`.`year_code` AS `year_code`,`c`.`name` AS `class_name`,`cs`.`stream_name` AS `stream_name`,concat(`c`.`name`,' - ',`cs`.`stream_name`) AS `class_stream`,`cya`.`teacher_id` AS `tea…` |
| `vw_class_timetable_coverage` | `select `c`.`id` AS `class_id`,`c`.`name` AS `class_name`,NULL AS `stream_name`,`cs`.`term_id` AS `term_id`,`cs`.`academic_year_id` AS `academic_year_id`,count(`cs`.`id`) AS `slots_filled`,(select count(0) * 5 from `Kings…` |
| `vw_collection_rate_by_class` | `select `sl`.`name` AS `level_name`,`sl`.`code` AS `level_code`,`at`.`name` AS `academic_term`,count(distinct `sfo`.`student_id`) AS `total_students`,sum(`sfo`.`amount_due`) AS `total_fees_due`,sum(`sfo`.`amount_paid`) AS…` |
| `vw_currently_blocked_ips` | `select `KingsWayAcademy`.`blocked_ips`.`ip_address` AS `ip_address`,`KingsWayAcademy`.`blocked_ips`.`reason` AS `reason`,`KingsWayAcademy`.`blocked_ips`.`created_at` AS `blocked_at`,`KingsWayAcademy`.`blocked_ips`.`expir…` |
| `vw_current_enrollments` | `select `e`.`id` AS `enrollment_id`,`e`.`student_id` AS `student_id`,`s`.`admission_no` AS `admission_no`,concat(`s`.`first_name`,' ',ifnull(`s`.`middle_name`,''),' ',`s`.`last_name`) AS `student_name`,`s`.`gender` AS `ge…` |
| `vw_current_staff_assignments` | `select `sca`.`id` AS `id`,`sca`.`staff_id` AS `staff_id`,concat(`s`.`first_name`,' ',`s`.`last_name`) AS `staff_name`,`s`.`staff_no` AS `staff_no`,`sc`.`category_name` AS `staff_category`,`sca`.`class_id` AS `class_id`,`…` |
| `vw_expected_attendance_today` | `select `s`.`id` AS `student_id`,concat(`s`.`first_name`,' ',`s`.`last_name`) AS `student_name`,`s`.`admission_no` AS `admission_no`,`st`.`code` AS `student_type_code`,`st`.`name` AS `student_type`,`ass`.`id` AS `session_…` |
| `vw_expense_summary_by_category` | `select `ec`.`id` AS `category_id`,`ec`.`name` AS `category_name`,`ec`.`type` AS `category_type`,`e`.`academic_year` AS `academic_year`,`e`.`term` AS `term`,count(`e`.`id`) AS `expense_count`,sum(`e`.`amount`) AS `total_a…` |
| `vw_failed_attempts_by_ip` | `select `KingsWayAcademy`.`failed_auth_attempts`.`ip_address` AS `ip_address`,count(0) AS `attempt_count`,max(`KingsWayAcademy`.`failed_auth_attempts`.`created_at`) AS `last_attempt`,group_concat(distinct `KingsWayAcademy…` |
| `vw_family_groups` | `select `p`.`id` AS `parent_id`,concat(`p`.`first_name`,coalesce(concat(' ',`p`.`middle_name`),''),' ',`p`.`last_name`) AS `parent_full_name`,`p`.`id_number` AS `parent_id_number`,`p`.`phone_1` AS `parent_phone_primary`,`…` |
| `vw_fee_carryover_summary` | `select `sfc`.`student_id` AS `student_id`,`s`.`admission_no` AS `admission_no`,concat(`s`.`first_name`,' ',`s`.`last_name`) AS `student_name`,`cs`.`stream_name` AS `class_name`,`sfc`.`academic_year` AS `academic_year`,`s…` |
| `vw_fee_collection_by_year` | `select `sfo`.`academic_year` AS `academic_year`,count(distinct `sfo`.`student_id`) AS `total_students`,sum(`sfo`.`amount_due`) AS `total_fees_due`,sum(`sfo`.`amount_paid`) AS `total_collected`,sum(`sfo`.`balance`) AS `to…` |
| `vw_fee_schedule_by_class` | `select `sl`.`name` AS `level_name`,`sl`.`code` AS `level_code`,`at`.`name` AS `academic_term`,`st`.`code` AS `student_type`,`st`.`name` AS `student_type_name`,`ft`.`name` AS `fee_name`,`ft`.`category` AS `fee_category`,`…` |
| `vw_fee_structure_annual_summary` | `select `fsd`.`academic_year` AS `academic_year`,`sl`.`name` AS `level_name`,`sl`.`id` AS `level_id`,`ft`.`name` AS `fee_type`,`ft`.`id` AS `fee_type_id`,`ft`.`category` AS `fee_category`,sum(case when `at`.`term_number` …` |
| `vw_fee_transition_audit` | `select `fth`.`student_id` AS `student_id`,`s`.`admission_no` AS `admission_no`,concat(`s`.`first_name`,' ',`s`.`last_name`) AS `student_name`,`fth`.`from_academic_year` AS `from_academic_year`,`fth`.`to_academic_year` AS…` |
| `vw_fee_type_collection` | `select `ft`.`name` AS `fee_type`,`ft`.`code` AS `fee_code`,`ft`.`category` AS `fee_category`,`ft`.`is_mandatory` AS `is_mandatory`,sum(`sfo`.`amount_due`) AS `total_due`,sum(`sfo`.`amount_paid`) AS `total_collected`,sum(…` |
| `vw_food_consumption_summary` | `select `fc`.`consumption_date` AS `consumption_date`,`i`.`name` AS `food_item`,`i`.`code` AS `code`,`ic`.`name` AS `category`,`fc`.`unit` AS `unit`,sum(`fc`.`quantity_planned`) AS `total_quantity_planned`,sum(`fc`.`quant…` |
| `vw_internal_conversations` | `select `c`.`id` AS `conversation_id`,`c`.`title` AS `title`,`c`.`conversation_type` AS `conversation_type`,`c`.`created_by` AS `created_by`,`u`.`first_name` AS `first_name`,`u`.`last_name` AS `last_name`,count(distinct `…` |
| `vw_inventory_health` | `select `i`.`id` AS `id`,`i`.`name` AS `name`,`i`.`code` AS `code`,`ic`.`name` AS `category`,`i`.`current_quantity` AS `current_quantity`,`i`.`minimum_quantity` AS `minimum_quantity`,`i`.`reorder_level` AS `reorder_level`…` |
| `vw_maintenance_schedule` | `select `em`.`id` AS `id`,`i`.`serial_number` AS `serial_number`,`ii`.`name` AS `equipment_name`,`ii`.`brand` AS `brand`,`ii`.`model` AS `model`,`emt`.`name` AS `maintenance_type`,`em`.`status` AS `status`,`em`.`last_main…` |
| `vw_onboarding_dashboard` | `select `so`.`id` AS `onboarding_id`,`so`.`staff_id` AS `staff_id`,`so`.`contract_type` AS `contract_type`,`so`.`probation_months` AS `probation_months`,`so`.`probation_outcome` AS `probation_outcome`,`so`.`start_date` AS…` |
| `vw_onboarding_pending_by_role` | `select `ot`.`onboarding_id` AS `onboarding_id`,`ot`.`id` AS `task_id`,`ot`.`task_name` AS `task_name`,`ot`.`category` AS `category`,`ot`.`priority` AS `priority`,`ot`.`due_date` AS `due_date`,`ot`.`status` AS `status`,ca…` |
| `vw_outstanding_by_class` | `select `sl`.`name` AS `level_name`,`sl`.`code` AS `level_code`,`at`.`name` AS `academic_term`,count(distinct `sfo`.`student_id`) AS `students_with_arrears`,sum(`sfo`.`balance`) AS `total_arrears`,avg(`sfo`.`balance`) AS …` |
| `vw_parent_payment_activity` | `select `p`.`id` AS `parent_id`,concat(`p`.`first_name`,' ',`p`.`last_name`) AS `parent_name`,`p`.`phone_1` AS `contact_number`,count(distinct `pt`.`id`) AS `total_payments`,sum(`pt`.`amount_paid`) AS `total_amount_paid`,…` |
| `vw_parent_summary` | `select `p`.`id` AS `parent_id`,concat(`p`.`first_name`,coalesce(concat(' ',`p`.`middle_name`),''),' ',`p`.`last_name`) AS `parent_full_name`,`p`.`id_number` AS `id_number`,`p`.`phone_1` AS `phone_1`,`p`.`phone_2` AS `pho…` |
| `vw_payment_tracking` | `select 'mpesa' AS `payment_source`,`mt`.`id` AS `source_id`,`mt`.`mpesa_code` AS `reference_code`,`mt`.`student_id` AS `student_id`,`s`.`admission_no` AS `admission_number`,concat(`s`.`first_name`,' ',`s`.`last_name`) AS…` |
| `vw_payment_transactions_with_amount` | `select `p`.`id` AS `id`,`p`.`student_id` AS `student_id`,`p`.`academic_year` AS `academic_year`,`p`.`term_id` AS `term_id`,`p`.`term_allocation` AS `term_allocation`,`p`.`fee_structure_detail_id` AS `fee_structure_detail…` |
| `vw_payslip_detailed` | `select `p`.`id` AS `payslip_id`,`p`.`staff_id` AS `staff_id`,`s`.`staff_no` AS `staff_no`,concat(`s`.`first_name`,' ',`s`.`last_name`) AS `staff_name`,`s`.`position` AS `position`,`d`.`name` AS `department_name`,`s`.`ban…` |
| `vw_pending_fee_structure_reviews` | `select `fsd`.`academic_year` AS `academic_year`,`sl`.`name` AS `level_name`,count(distinct `fsd`.`id`) AS `pending_structures`,min(`fsd`.`created_at`) AS `oldest_pending_date`,to_days(curdate()) - to_days(min(`fsd`.`crea…` |
| `vw_pending_requisitions` | `select `r`.`id` AS `id`,`r`.`requisition_number` AS `requisition_number`,`d`.`name` AS `department`,`r`.`status` AS `status`,`r`.`priority` AS `priority`,`r`.`requisition_date` AS `requisition_date`,`r`.`required_date` A…` |
| `vw_pending_sms` | `select `s`.`id` AS `sms_id`,`s`.`parent_id` AS `parent_id`,concat(`p`.`first_name`,' ',`p`.`last_name`) AS `parent_name`,`s`.`recipient_phone` AS `recipient_phone`,`s`.`message_body` AS `message_body`,`s`.`sms_type` AS `…` |
| `vw_petty_cash_summary` | `select `pcf`.`id` AS `fund_id`,`pcf`.`fund_name` AS `fund_name`,`pcf`.`current_balance` AS `current_balance`,`pcf`.`float_limit` AS `float_limit`,`pcf`.`last_reconciled_at` AS `last_reconciled_at`,sum(case when `pct`.`ty…` |
| `vw_requisition_fulfillment` | `select `r`.`id` AS `id`,`r`.`requisition_number` AS `requisition_number`,`d`.`name` AS `department`,`ri`.`id` AS `item_id`,`i`.`name` AS `item_name`,`ri`.`requested_quantity` AS `requested_quantity`,`ri`.`unit` AS `unit`…` |
| `vw_scheme_completion` | `select `s`.`id` AS `teacher_id`,concat(`s`.`first_name`,' ',`s`.`last_name`) AS `teacher_name`,`sw`.`term_id` AS `term_id`,`sw`.`subject_name` AS `subject_name`,count(`sw`.`id`) AS `total_schemes`,sum(case when `sw`.`sta…` |
| `vw_school_day_context` | `select `sc`.`date` AS `date`,`sc`.`day_type` AS `day_type`,`sc`.`title` AS `event_name`,`sc`.`affects_day_students` AS `affects_day_students`,`sc`.`affects_boarders` AS `affects_boarders`,`sc`.`requires_attendance` AS `r…` |
| `vw_sent_emails` | `select `e`.`id` AS `email_id`,`e`.`institution_id` AS `institution_id`,`ei`.`name` AS `institution_name`,`ei`.`contact_person_name` AS `contact_person_name`,`e`.`recipient_email` AS `recipient_email`,`e`.`subject` AS `su…` |
| `vw_sponsored_students_status` | `select `s`.`id` AS `id`,`s`.`admission_no` AS `admission_no`,concat(`s`.`first_name`,' ',`s`.`last_name`) AS `student_name`,`st`.`name` AS `student_type`,`cs`.`stream_name` AS `class_name`,`s`.`is_sponsored` AS `is_spons…` |
| `vw_staff_assignments_detailed` | `select `sca`.`id` AS `id`,`sca`.`staff_id` AS `staff_id`,`sca`.`class_stream_id` AS `class_stream_id`,`sca`.`class_id` AS `class_id`,`sca`.`stream_id` AS `stream_id`,`sca`.`academic_year_id` AS `academic_year_id`,`sca`.`…` |
| `vw_staff_attendance_anomalies` | `select `sa`.`staff_id` AS `staff_id`,concat(`s`.`first_name`,' ',`s`.`last_name`) AS `staff_name`,`s`.`staff_no` AS `staff_no`,`d`.`name` AS `department`,count(case when `sa`.`status` = 'absent' and `sa`.`absence_reason`…` |
| `vw_staff_attendance_status` | `select `st`.`id` AS `staff_id`,concat(`st`.`first_name`,' ',`st`.`last_name`) AS `staff_name`,`st`.`staff_no` AS `staff_no`,`d`.`name` AS `department`,`st`.`position` AS `position`,`st`.`contract_type` AS `contract_type`…` |
| `vw_staff_children_fees` | `select `sc`.`id` AS `staff_child_id`,`sc`.`staff_id` AS `staff_id`,`s`.`staff_no` AS `staff_no`,concat(`s`.`first_name`,' ',`s`.`last_name`) AS `staff_name`,`s`.`salary` AS `staff_salary`,`sc`.`student_id` AS `student_id…` |
| `vw_staff_daily_register` | `select `s`.`id` AS `staff_id`,`s`.`staff_no` AS `staff_no`,concat(`s`.`first_name`,' ',`s`.`last_name`) AS `staff_name`,`s`.`first_name` AS `first_name`,`s`.`last_name` AS `last_name`,`s`.`position` AS `position`,`s`.`wo…` |
| `vw_staff_leave_balance` | `select `s`.`id` AS `staff_id`,`s`.`staff_no` AS `staff_no`,concat(`s`.`first_name`,' ',`s`.`last_name`) AS `staff_name`,`lt`.`code` AS `leave_type`,`lt`.`name` AS `leave_name`,`lt`.`days_allowed` AS `annual_entitlement`,…` |
| `vw_staff_leave_balances` | `select `s`.`id` AS `staff_id`,`s`.`staff_no` AS `staff_no`,concat(`s`.`first_name`,' ',`s`.`last_name`) AS `staff_name`,`lt`.`id` AS `leave_type_id`,`lt`.`name` AS `leave_type_name`,`lt`.`days_allowed` AS `entitled_days`…` |
| `vw_staff_loan_details` | `select `sl`.`id` AS `loan_id`,`sl`.`staff_id` AS `staff_id`,concat(`s`.`first_name`,' ',`s`.`last_name`) AS `staff_name`,`s`.`staff_no` AS `staff_number`,`sl`.`loan_type` AS `loan_type`,`sl`.`principal_amount` AS `princi…` |
| `vw_staff_monthly_summary` | `select `sa`.`staff_id` AS `staff_id`,`sa`.`academic_year_id` AS `academic_year_id`,`ay`.`year_code` AS `year_code`,year(`sa`.`date`) AS `attendance_year`,month(`sa`.`date`) AS `attendance_month`,monthname(`sa`.`date`) AS…` |
| `vw_staff_off_day_schedule` | `select `s`.`id` AS `staff_id`,concat(`s`.`first_name`,' ',`s`.`last_name`) AS `staff_name`,`s`.`department_id` AS `department_id`,'pattern' AS `source`,`sop`.`day_of_week` AS `day_of_week`,`sop`.`effective_from` AS `effe…` |
| `vw_staff_onboarding_progress` | `select `so`.`id` AS `onboarding_id`,`s`.`id` AS `staff_id`,`s`.`staff_no` AS `staff_no`,concat(`s`.`first_name`,' ',`s`.`last_name`) AS `staff_name`,`s`.`position` AS `position`,`d`.`name` AS `department`,`so`.`status` A…` |
| `vw_staff_payroll_eligibility` | `select `ep`.`staff_id` AS `staff_id`,case when lcase(coalesce(`ep`.`status`,'')) = 'active' and coalesce(`pp`.`basic_salary`,0) > 0 and `ep`.`employment_date` is not null and nullif(trim(coalesce(`pp`.`bank_account`,''))…` |
| `vw_staff_payroll_summary` | `select `ps`.`id` AS `payslip_id`,`ps`.`staff_id` AS `staff_id`,concat(`s`.`first_name`,' ',`s`.`last_name`) AS `staff_name`,`s`.`staff_no` AS `staff_number`,`ps`.`payroll_month` AS `payroll_month`,`ps`.`payroll_year` AS …` |
| `vw_staff_performance_summary` | `select `spr`.`id` AS `review_id`,`s`.`id` AS `staff_id`,`s`.`staff_no` AS `staff_no`,concat(`s`.`first_name`,' ',`s`.`last_name`) AS `staff_name`,`s`.`position` AS `position`,`d`.`name` AS `department`,`ay`.`year_name` A…` |
| `vw_staff_service_history` | `select `sca`.`staff_id` AS `staff_id`,`ay`.`year_code` AS `academic_year`,`c`.`name` AS `class_name`,`cs`.`stream_name` AS `stream_name`,`sca`.`role` AS `role`,`la`.`name` AS `subject_name`,`sca`.`status` AS `status`,`sc…` |
| `vw_staff_workload` | `select `s`.`id` AS `staff_id`,`s`.`staff_no` AS `staff_no`,concat(`s`.`first_name`,' ',`s`.`last_name`) AS `staff_name`,`sc`.`category_name` AS `category_name`,`ay`.`year_name` AS `academic_year`,count(distinct `sca`.`cl…` |
| `vw_student_academic_history` | `select `ce`.`student_id` AS `student_id`,`ay`.`year_code` AS `academic_year`,`ay`.`year_name` AS `year_name`,`c`.`name` AS `class_name`,`cs`.`stream_name` AS `stream_name`,`ce`.`term1_average` AS `term1_average`,`ce`.`te…` |
| `vw_student_attendance_summary` | `select `s`.`id` AS `student_id`,concat(`s`.`first_name`,' ',`s`.`last_name`) AS `student_name`,`s`.`admission_no` AS `admission_no`,`c`.`name` AS `class_name`,`st`.`name` AS `student_type`,`d`.`name` AS `dormitory`,`ass`…` |
| `vw_student_fee_clearance` | `select `s`.`id` AS `student_id`,concat(`s`.`first_name`,' ',`s`.`last_name`) AS `student_name`,`s`.`admission_no` AS `admission_no`,coalesce(sum(`o`.`balance`),0) AS `total_outstanding`,coalesce(sum(`o`.`amount_paid`),0)…` |
| `vw_student_finance_history` | `select `pt`.`student_id` AS `student_id`,`pt`.`academic_year` AS `academic_year`,`at2`.`name` AS `term_name`,`at2`.`term_number` AS `term_number`,sum(`pt`.`amount_paid`) AS `total_paid`,count(`pt`.`id`) AS `payment_count…` |
| `vw_student_payment_history_multi_year` | `select `s`.`id` AS `student_id`,`s`.`first_name` AS `first_name`,`s`.`last_name` AS `last_name`,`s`.`admission_no` AS `admission_no`,`pt`.`academic_year` AS `academic_year`,`at`.`name` AS `term_name`,`at`.`term_number` A…` |
| `vw_student_payment_status` | `select `s`.`admission_no` AS `admission_no`,concat(`s`.`first_name`,' ',`s`.`last_name`) AS `student_name`,`sl`.`name` AS `level`,`st`.`name` AS `student_type`,`at`.`name` AS `academic_term`,sum(`sfo`.`amount_due`) AS `t…` |
| `vw_student_payment_status_enhanced` | `select `s`.`id` AS `id`,`s`.`admission_no` AS `admission_no`,concat(`s`.`first_name`,' ',`s`.`last_name`) AS `student_name`,`st`.`name` AS `student_type`,`cs`.`stream_name` AS `class_name`,`cl`.`name` AS `level_name`,yea…` |
| `vw_student_term_attendance_summary` | `select `sa`.`student_id` AS `student_id`,`sa`.`academic_year_id` AS `academic_year_id`,`sa`.`term_id` AS `term_id`,`sa`.`class_id` AS `class_id`,`sa`.`register_type` AS `register_type`,`ay`.`year_code` AS `year_code`,`at…` |
| `vw_student_uniform_balance` | `select `s`.`id` AS `student_id`,concat(`s`.`first_name`,' ',`s`.`last_name`) AS `student_name`,`s`.`admission_no` AS `admission_no`,count(`us`.`id`) AS `total_sales`,sum(`us`.`total_amount`) AS `total_billed`,sum(`us`.`a…` |
| `vw_teacher_weekly_load` | `select `s`.`id` AS `teacher_id`,concat(`s`.`first_name`,' ',`s`.`last_name`) AS `teacher_name`,`s`.`position` AS `designation`,`cs`.`term_id` AS `term_id`,`cs`.`academic_year_id` AS `academic_year_id`,count(0) AS `total_…` |
| `vw_term_expected_days` | `select `sc`.`term_id` AS `term_id`,`sc`.`academic_year_id` AS `academic_year_id`,count(0) AS `expected_class_days` from `KingsWayAcademy`.`school_calendar` `sc` where `sc`.`day_type` in ('school_day','half_day','exam_day…` |
| `vw_uniform_sales_analytics` | `select `us`.`id` AS `sale_id`,`s`.`id` AS `student_id`,concat(`s`.`first_name`,' ',`s`.`last_name`) AS `student_name`,`s`.`admission_no` AS `admission_number`,`ii`.`name` AS `uniform_item`,`ii`.`code` AS `item_code`,`us`…` |
| `vw_uniform_stock_matrix` | `select `ii`.`id` AS `item_id`,`ii`.`name` AS `item_name`,`ii`.`code` AS `item_code`,`us`.`size` AS `size`,`us`.`size_label` AS `size_label`,`us`.`size_type` AS `size_type`,`us`.`quantity_available` AS `quantity_available…` |
| `vw_unread_announcements` | `select `a`.`id` AS `announcement_id`,`a`.`title` AS `title`,`a`.`content` AS `content`,`a`.`priority` AS `priority`,`a`.`target_audience` AS `target_audience`,`u`.`first_name` AS `published_by_first`,`u`.`last_name` AS `…` |
| `vw_upcoming_class_schedules` | `select `cs`.`id` AS `id`,`cs`.`class_id` AS `class_id`,`c`.`name` AS `class_name`,`cs`.`day_of_week` AS `day_of_week`,`cs`.`start_time` AS `start_time`,`cs`.`end_time` AS `end_time`,`cs`.`period_number` AS `period_number…` |
| `vw_upcoming_exam_schedules` | `select `es`.`id` AS `id`,`es`.`term_id` AS `term_id`,`es`.`academic_year_id` AS `academic_year_id`,`es`.`class_id` AS `class_id`,`c`.`name` AS `class_name`,`es`.`subject_id` AS `subject_id`,coalesce(`cu`.`name`,'') AS `s…` |
| `v_active_users` | `select `u`.`id` AS `id`,`u`.`username` AS `username`,`u`.`email` AS `email`,`u`.`first_name` AS `first_name`,`u`.`last_name` AS `last_name`,`u`.`status` AS `status`,`u`.`role_id` AS `role_id`,`r`.`name` AS `role_name`,`u…` |
| `v_payment_security_alerts` | `select `KingsWayAcademy`.`payment_security_audit`.`id` AS `id`,`KingsWayAcademy`.`payment_security_audit`.`event_type` AS `event_type`,`KingsWayAcademy`.`payment_security_audit`.`webhook_source` AS `webhook_source`,`King…` |
| `v_role_permission_summary` | `select `r`.`id` AS `id`,`r`.`name` AS `name`,count(distinct `rp`.`permission_id`) AS `total_perms`,sum(case when `p`.`entity` like 'communication%' then 1 else 0 end) AS `comms`,sum(case when `p`.`entity` like 'academic%…` |
| `v_user_permissions_effective` | `select distinct `ur`.`user_id` AS `user_id`,`rp`.`permission_id` AS `permission_id`,`p`.`code` AS `permission_code`,`p`.`entity` AS `entity`,`p`.`action` AS `action`,'role' AS `source` from ((`KingsWayAcademy`.`user_role…` |
| `v_user_security` | `select `u`.`id` AS `id`,`u`.`username` AS `username`,`u`.`email` AS `email`,`u`.`status` AS `status`,`u`.`failed_login_attempts` AS `failed_login_attempts`,`u`.`account_locked_until` AS `account_locked_until`,`u`.`last_l…` |

## Procedures (150)

| Procedure |
|---|
| `add_students_to_class` |
| `sp_add_custom_stream` |
| `sp_add_item_to_inventory` |
| `sp_advance_admission_workflow_stage` |
| `sp_advance_workflow_stage` |
| `sp_allocate_payment` |
| `sp_apply_fee_discount` |
| `sp_apply_staff_loan` |
| `sp_approve_class_promotion` |
| `sp_approve_student_promotion` |
| `sp_assess_learner_competency` |
| `sp_assign_financial_period` |
| `sp_assign_role` |
| `sp_assign_staff_type_and_category` |
| `sp_assign_student_transport` |
| `sp_auto_generate_onboarding_tasks` |
| `sp_auto_rollover_fee_structures` |
| `sp_broadcast_notification` |
| `sp_bulk_inventory_reconciliation` |
| `sp_bulk_mark_staff_attendance` |
| `sp_bulk_mark_student_attendance` |
| `sp_bulk_payroll_calculation` |
| `sp_bulk_transport_assignment` |
| `sp_bulk_upsert_json` |
| `sp_calculate_annual_scores` |
| `sp_calculate_kpi_achievement_score` |
| `sp_calculate_nhif_contribution` |
| `sp_calculate_nssf_contribution` |
| `sp_calculate_paye_tax` |
| `sp_calculate_payroll_for_staff` |
| `sp_calculate_staff_child_fees` |
| `sp_calculate_staff_full_payroll` |
| `sp_calculate_staff_leave_balance` |
| `sp_calculate_student_fees` |
| `sp_calculate_term_subject_score` |
| `sp_carryover_fee_balance` |
| `sp_check_class_space_availability` |
| `sp_check_finance_clearance` |
| `sp_check_form_permission` |
| `sp_check_record_permission` |
| `sp_check_student_transport_status` |
| `sp_cleanup_expired_blocks` |
| `sp_cleanup_failed_attempts` |
| `sp_compare_to_benchmark` |
| `sp_compile_exam_results` |
| `sp_complete_maintenance` |
| `sp_complete_promotion_batch` |
| `sp_complete_student_enrollment` |
| `sp_consolidate_term_scores` |
| `sp_create_arrears_record` |
| `sp_create_arrears_settlement_plan` |
| `sp_create_exam_schedule` |
| `sp_create_parent` |
| `sp_create_promotion_batch` |
| `sp_create_user_session` |
| `sp_deny_permission` |
| `sp_detect_schedule_conflicts` |
| `sp_end_user_session` |
| `sp_enroll_student_in_class` |
| `sp_ensure_class_streams` |
| `sp_generate_api_token` |
| `sp_generate_comment` |
| `sp_generate_p9_form` |
| `sp_generate_performance_rating` |
| `sp_generate_school_year_report` |
| `sp_generate_student_fee_obligations` |
| `sp_generate_student_report` |
| `sp_get_assessment_trends` |
| `sp_get_class_fee_schedule` |
| `sp_get_competency_report` |
| `sp_get_dormitory_students` |
| `sp_get_family_group_details` |
| `sp_get_fee_breakdown_for_review` |
| `sp_get_fee_collection_rate` |
| `sp_get_outstanding_fees_report` |
| `sp_get_parent_children` |
| `sp_get_payments_by_admission` |
| `sp_get_staff_kpi_summary` |
| `sp_get_user_permissions` |
| `sp_get_values_summary` |
| `sp_grant_permission` |
| `sp_implement_discipline_action` |
| `sp_initialize_transfer_clearances` |
| `sp_issue_allocation` |
| `sp_link_parent_to_student` |
| `sp_mark_boarding_attendance` |
| `sp_mark_uniform_sale_paid` |
| `sp_migrate_admission_applications_to_new_workflow` |
| `sp_moderate_marks` |
| `sp_process_monthly_payroll` |
| `sp_process_requisition` |
| `sp_process_staff_payroll` |
| `sp_process_staff_performance_review` |
| `sp_process_student_payment` |
| `sp_process_student_sponsorship` |
| `sp_promote_bulk_students` |
| `sp_promote_by_grade_bulk` |
| `sp_promote_single_class` |
| `sp_reallocate_all_payments` |
| `sp_record_assessment_change` |
| `sp_record_cash_payment` |
| `sp_record_conduct` |
| `sp_record_core_value` |
| `sp_record_csl_participation` |
| `sp_record_discipline_case` |
| `sp_record_food_consumption` |
| `sp_record_login_attempt` |
| `sp_record_payment_security_event` |
| `sp_record_pci_awareness` |
| `sp_record_transport_payment` |
| `sp_record_uniform_payment` |
| `sp_refresh_student_payment_summary` |
| `sp_register_uniform_sale` |
| `sp_reject_class_promotion` |
| `sp_reject_student_promotion` |
| `sp_remove_custom_stream` |
| `sp_request_staff_advance` |
| `sp_return_allocation` |
| `sp_revoke_permission` |
| `sp_revoke_role` |
| `sp_schedule_discipline_hearing` |
| `sp_schedule_maintenance` |
| `sp_search_family_groups` |
| `sp_send_announcement` |
| `sp_send_external_email` |
| `sp_send_fee_reminder` |
| `sp_send_fee_reminders` |
| `sp_send_internal_message` |
| `sp_send_sms_to_parents` |
| `sp_suspend_student_promotion` |
| `sp_track_student_behavior` |
| `sp_transfer_student_promotion` |
| `sp_transition_to_new_academic_year` |
| `sp_transition_to_new_term` |
| `sp_unlink_parent_from_student` |
| `sp_unlock_user_account` |
| `sp_update_kpi_achievement` |
| `sp_update_parent` |
| `sp_users_with_multiple_roles` |
| `sp_users_with_permission` |
| `sp_users_with_role` |
| `sp_users_with_temporary_permissions` |
| `sp_user_get_denied_permissions` |
| `sp_user_get_effective_permissions` |
| `sp_user_get_permissions_by_entity` |
| `sp_user_get_permission_summary` |
| `sp_user_get_roles_detailed` |
| `sp_validate_payment_request` |
| `sp_validate_staff_assignment` |
| `sp_validate_term_holiday_conflicts` |

## Functions (21)

| Function |
|---|
| `calculate_class_average` |
| `calculate_grade` |
| `calculate_points` |
| `calculate_student_age` |
| `calculate_term_fees` |
| `fn_get_admission_workflow_communication` |
| `fn_get_batch_approval_percentage` |
| `fn_get_child_discount_rate` |
| `fn_get_parent_children_count` |
| `fn_get_parent_total_fee_balance` |
| `fn_get_student_promotion_status` |
| `fn_has_permission` |
| `fn_is_school_day` |
| `fn_is_student_suspended` |
| `fn_notify_low_stock` |
| `fn_staff_expected_today` |
| `fn_student_fee_due` |
| `fn_student_has_permission` |
| `fn_student_outstanding_balance` |
| `fn_user_has_permission` |
| `generate_student_number` |

## Triggers (58)

| Trigger | Table | Timing/Event |
|---|---|---|
| `trg_validate_academic_term` | `academic_terms` | BEFORE INSERT |
| `trg_complete_staff_assignments_on_year_end` | `academic_years` | AFTER UPDATE |
| `trg_auto_create_notification` | `announcements_bulletin` | AFTER INSERT |
| `trg_log_settlement_plan` | `arrears_settlement_plans` | AFTER INSERT |
| `trg_bank_payment_processed` | `bank_transactions` | AFTER INSERT |
| `trg_auto_create_default_stream` | `classes` | AFTER INSERT |
| `after_enrollment_delete` | `class_enrollments` | AFTER DELETE |
| `after_enrollment_insert` | `class_enrollments` | AFTER INSERT |
| `after_enrollment_update` | `class_enrollments` | AFTER UPDATE |
| `trg_validate_class_capacity` | `class_streams` | BEFORE INSERT |
| `trg_auto_start_comm_workflow` | `communications` | AFTER INSERT |
| `trg_log_conduct_recording` | `conduct_tracking` | AFTER INSERT |
| `trg_check_maintenance_overdue` | `equipment_maintenance` | BEFORE UPDATE |
| `trg_expense_approved_update_budget` | `expenses` | AFTER UPDATE |
| `trg_log_email_delivery` | `external_emails` | AFTER UPDATE |
| `trg_log_discount_waiver` | `fee_discounts_waivers` | AFTER INSERT |
| `trg_emit_payment_event` | `financial_transactions` | AFTER INSERT |
| `trg_check_meal_plan_completion` | `food_consumption_records` | AFTER INSERT |
| `trg_log_food_consumption` | `food_consumption_records` | AFTER INSERT |
| `trg_audit_allocation_insert` | `inventory_allocations` | AFTER INSERT |
| `trg_check_allocation_expiry` | `inventory_allocations` | BEFORE UPDATE |
| `trg_log_allocation_status` | `inventory_allocations` | AFTER UPDATE |
| `trg_audit_inventory_insert` | `inventory_items` | AFTER INSERT |
| `trg_emit_low_stock_event` | `inventory_items` | AFTER UPDATE |
| `trg_log_requisition_status` | `inventory_requisitions` | AFTER UPDATE |
| `trg_log_competency_assessment` | `learner_competencies` | AFTER INSERT |
| `trg_log_csl_participation` | `learner_csl_participation` | AFTER INSERT |
| `trg_log_pci_awareness` | `learner_pci_awareness` | AFTER INSERT |
| `trg_log_value_demonstration` | `learner_values_acquisition` | AFTER INSERT |
| `trg_mpesa_payment_processed` | `mpesa_transactions` | AFTER INSERT |
| `trg_update_onboarding_progress` | `onboarding_tasks` | AFTER UPDATE |
| `trg_update_obligation_on_payment` | `payment_allocations_detailed` | AFTER INSERT |
| `trg_log_payment_transaction` | `payment_transactions` | AFTER INSERT |
| `trg_petty_cash_balance_update` | `petty_cash_transactions` | AFTER INSERT |
| `trg_create_portfolio_on_promotion` | `portfolios` | AFTER INSERT |
| `trg_role_delegations_prevent_insert` | `role_delegations` | BEFORE INSERT |
| `trg_role_delegations_prevent_update` | `role_delegations` | BEFORE UPDATE |
| `trg_log_scheme_changes` | `schemes_of_work` | AFTER UPDATE |
| `trg_assign_financial_period` | `school_transactions` | AFTER INSERT |
| `trg_log_sms_delivery` | `sms_communications` | AFTER UPDATE |
| `trg_check_leave_overlap` | `staff_leaves` | BEFORE INSERT |
| `trg_update_leave_balance` | `staff_leaves` | AFTER UPDATE |
| `trg_validate_payroll_payment` | `staff_payroll` | BEFORE UPDATE |
| `trg_notify_performance_review_complete` | `staff_performance_reviews` | AFTER UPDATE |
| `trg_create_student_obligations` | `students` | AFTER INSERT |
| `trg_emit_student_status_event` | `students` | AFTER UPDATE |
| `trg_update_class_capacity` | `students` | AFTER INSERT |
| `trg_update_student_status` | `students` | AFTER UPDATE |
| `trg_log_arrears_creation` | `student_arrears` | AFTER INSERT |
| `trg_emit_attendance_event` | `student_attendance` | AFTER INSERT |
| `trg_check_and_create_arrears` | `student_fee_obligations` | AFTER UPDATE |
| `trg_log_material_approval` | `teaching_materials` | AFTER UPDATE |
| `trg_uniform_sale_insert` | `uniform_sales` | AFTER INSERT |
| `trg_audit_delete` | `users` | BEFORE DELETE |
| `trg_audit_insert` | `users` | AFTER INSERT |
| `trg_audit_update` | `users` | AFTER UPDATE |
| `trg_prevent_user_delete` | `users` | BEFORE DELETE |
| `trg_validate_email` | `users` | BEFORE INSERT |

## Events (10)

| Event |
|---|
| `evt_process_academic_summary` |
| `evt_process_attendance_summary` |
| `evt_staff_appraisal_reminders` |
| `evt_vehicle_maintenance_alerts` |
| `ev_daily_attendance_report` |
| `ev_monthly_competency_report_reminder` |
| `ev_quarterly_csl_audit` |
| `ev_term_end_values_summary` |
| `ev_weekly_maintenance_notify` |
| `ev_yearly_portfolio_archive_reminder` |
