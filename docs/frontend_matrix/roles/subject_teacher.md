# Subject Teacher — Role Matrix

| Field | Value |
|---|---|
| role_id | 8 |
| scope | school |
| is_system | 0 |
| is_active | 1 |
| users | 13 |
| sidebar items | 0 |
| route-level auth (role_routes) | 190 |
| dashboards | 1 |
| distinct endpoints | 1 |
| unresolved endpoints | 0 |

## Dashboards

| Key | Display | Component | Endpoints | Unresolved |
|---|---|---|---|---|
| subject_teacher_dashboard | Subject Teacher Dashboard | components/dashboards/subject_teacher_dashboard.php | 1 | 0 |

## Sidebar items

| id | Label | Type | Parent | URL | Route | Page | Controllers | Status |
|---|---|---|---|---|---|---|---|---|

## Endpoint usage (dedup, all sources)

| Endpoint | Module | API method | Handler | Status | Source |
|---|---|---|---|---|---|
| `GET /dashboard/subject-teacher/full` | dashboard | getSubjectTeacherFull | `Dashboard::getSubjectTeacherFull` | OK | dashboard:subject_teacher_dashboard |

## Authorized routes (role_routes)

| route_id | Name | URL | Controller | Action |
|---|---|---|---|---|
| 86 | home | home.php |  |  |
| 78 | manage_communications | home.php?route=manage_communications |  |  |
| 47 | manage_lesson_plans | home.php?route=manage_lesson_plans |  |  |
| 87 | me | home.php?route=me |  |  |
| 48 | myclasses | home.php?route=myclasses |  |  |
| 27 | subject_teacher_dashboard | home.php?route=subject_teacher_dashboard |  |  |
| 50 | submit_results | home.php?route=submit_results |  |  |
| 51 | view_results | home.php?route=view_results |  |  |
| 66 | staff_attendance | home.php?route=staff_attendance |  |  |
| 68 | mark_attendance | home.php?route=mark_attendance |  |  |
| 69 | view_attendance | home.php?route=view_attendance |  |  |
| 158 | boarding_roll_call | home.php?route=boarding_roll_call |  |  |
| 169 | schemes_of_work | home.php?route=schemes_of_work |  |  |
| 178 | exam_schedule | home.php?route=exam_schedule |  |  |
| 181 | grading_status | home.php?route=grading_status |  |  |
| 52 | enter_results | home.php?route=enter_results |  |  |
| 22 | director_owner_dashboard | home.php?route=director_owner_dashboard |  |  |
| 23 | school_administrative_officer_dashboard | home.php?route=school_administrative_officer_dashboard |  |  |
| 24 | headteacher_dashboard | home.php?route=headteacher_dashboard |  |  |
| 28 | intern_student_teacher_dashboard | home.php?route=intern_student_teacher_dashboard |  |  |
| 29 | school_accountant_dashboard | home.php?route=school_accountant_dashboard |  |  |
| 30 | store_manager_dashboard | home.php?route=store_manager_dashboard |  |  |
| 31 | catering_manager_cook_lead_dashboard | home.php?route=catering_manager_cook_lead_dashboard |  |  |
| 32 | matron_housemother_dashboard | home.php?route=matron_housemother_dashboard |  |  |
| 33 | hod_talent_development_dashboard | home.php?route=hod_talent_development_dashboard |  |  |
| 34 | driver_dashboard | home.php?route=driver_dashboard |  |  |
| 35 | school_counselor_chaplain_dashboard | home.php?route=school_counselor_chaplain_dashboard |  |  |
| 53 | manage_finance | home.php?route=manage_finance |  |  |
| 60 | finance_reports | home.php?route=finance_reports |  |  |
| 62 | budget_overview | home.php?route=budget_overview |  |  |
| 63 | finance_approvals | home.php?route=finance_approvals |  |  |
| 61 | financial_reports | home.php?route=financial_reports |  |  |
| 82 | enrollment_reports | home.php?route=enrollment_reports |  |  |
| 83 | performance_reports | home.php?route=performance_reports |  |  |
| 36 | manage_students | home.php?route=manage_students |  |  |
| 37 | manage_students_admissions | home.php?route=manage_students_admissions |  |  |
| 45 | manage_timetable | home.php?route=manage_timetable |  |  |
| 64 | manage_staff | home.php?route=manage_staff |  |  |
| 13 | manage_users | home.php?route=manage_users | SystemAdministrationController |  |
| 42 | manage_academics | home.php?route=manage_academics |  |  |
| 201 | all_students | home.php?route=all_students |  |  |
| 38 | student_performance | home.php?route=student_performance |  |  |
| 202 | all_staff | home.php?route=all_staff |  |  |
| 203 | staff_performance_overview | home.php?route=staff_performance_overview |  |  |
| 39 | student_discipline | home.php?route=student_discipline |  |  |
| 219 | discipline_cases | home.php?route=discipline_cases |  |  |
| 220 | conduct_reports | home.php?route=conduct_reports |  |  |
| 221 | academic_reports | home.php?route=academic_reports |  |  |
| 222 | performance_analysis | home.php?route=performance_analysis |  |  |
| 223 | term_reports | home.php?route=term_reports |  |  |
| 160 | academic_years | home.php?route=academic_years |  |  |
| 161 | current_academic_year | home.php?route=current_academic_year |  |  |
| 162 | manage_terms | home.php?route=manage_terms |  |  |
| 163 | year_calendar | home.php?route=year_calendar |  |  |
| 164 | year_history | home.php?route=year_history |  |  |
| 170 | all_lesson_plans | home.php?route=all_lesson_plans |  |  |
| 171 | lesson_plans_by_class | home.php?route=lesson_plans_by_class |  |  |
| 172 | lesson_plans_by_teacher | home.php?route=lesson_plans_by_teacher |  |  |
| 173 | lesson_plan_approval | home.php?route=lesson_plan_approval |  |  |
| 46 | manage_assessments | home.php?route=manage_assessments |  |  |
| 49 | add_results | home.php?route=add_results |  |  |
| 174 | academic_calendar | home.php?route=academic_calendar |  |  |
| 175 | view_calendar | home.php?route=view_calendar |  |  |
| 176 | manage_calendar_events | home.php?route=manage_calendar_events |  |  |
| 217 | new_applications | home.php?route=new_applications | new_applications |  |
| 218 | admission_status | home.php?route=admission_status |  |  |
| 54 | manage_fees | home.php?route=manage_fees |  |  |
| 237 | students_with_balance | home.php?route=students_with_balance |  |  |
| 238 | balances_by_class | home.php?route=balances_by_class |  |  |
| 239 | fee_defaulters | home.php?route=fee_defaulters |  |  |
| 76 | manage_activities | home.php?route=manage_activities |  |  |
| 224 | clubs_societies | home.php?route=clubs_societies |  |  |
| 225 | sports | home.php?route=sports |  |  |
| 226 | competitions | home.php?route=competitions |  |  |
| 227 | school_events | home.php?route=school_events |  |  |
| 228 | assemblies | home.php?route=assemblies |  |  |
| 177 | assessments_exams | home.php?route=assessments_exams |  |  |
| 179 | exam_setup | home.php?route=exam_setup |  |  |
| 180 | supervision_roster | home.php?route=supervision_roster |  |  |
| 182 | results_analysis | home.php?route=results_analysis |  |  |
| 183 | report_cards | home.php?route=report_cards |  |  |
| 40 | student_counseling | home.php?route=student_counseling |  |  |
| 229 | special_needs | home.php?route=special_needs |  |  |
| 230 | counseling_records | home.php?route=counseling_records |  |  |
| 55 | student_fees | home.php?route=student_fees |  |  |
| 56 | manage_payments | home.php?route=manage_payments |  |  |
| 57 | manage_payrolls | home.php?route=manage_payrolls |  |  |
| 58 | payroll | home.php?route=payroll |  |  |
| 70 | manage_inventory | home.php?route=manage_inventory |  |  |
| 71 | manage_stock | home.php?route=manage_stock |  |  |
| 72 | manage_requisitions | home.php?route=manage_requisitions |  |  |
| 74 | menu_planning | home.php?route=menu_planning |  |  |
| 73 | food_store | home.php?route=food_store |  |  |
| 75 | manage_boarding | home.php?route=manage_boarding |  |  |
| 155 | manage_transport | home.php?route=manage_transport |  |  |
| 84 | my_routes | home.php?route=my_routes |  |  |
| 85 | my_vehicle | home.php?route=my_vehicle |  |  |
| 77 | chapel_services | home.php?route=chapel_services |  |  |
| 1 | system_administrator_dashboard | home.php?route=system_administrator_dashboard | DashboardController |  |
| 94 | account_status | home.php?route=account_status | SystemAdministrationController |  |
| 95 | role_definitions | home.php?route=role_definitions |  |  |
| 144 | role_permission_matrix | home.php?route=role_permission_matrix | SystemAdministrationController |  |
| 101 | route_registry | home.php?route=route_registry | SystemAdministrationController |  |
| 103 | sidebar_menus | home.php?route=sidebar_menus | SystemAdministrationController |  |
| 106 | dashboard_registry | home.php?route=dashboard_registry |  |  |
| 146 | widget_registry | home.php?route=widget_registry |  |  |
| 147 | role_navigation_config | home.php?route=role_navigation_config | SystemAdministrationController |  |
| 108 | domain_isolation_rules | home.php?route=domain_isolation_rules | SystemAdministrationController |  |
| 110 | time_bound_access | home.php?route=time_bound_access | SystemAdministrationController |  |
| 148 | route_access_rules | home.php?route=route_access_rules | SystemAdministrationController |  |
| 149 | permission_policies | home.php?route=permission_policies | SystemAdministrationController |  |
| 2 | system_health | home.php?route=system_health | SystemAdministrationController |  |
| 3 | error_logs | home.php?route=error_logs | SystemAdministrationController |  |
| 120 | background_jobs | home.php?route=background_jobs | SystemAdministrationController |  |
| 122 | api_metrics | home.php?route=api_metrics | SystemAdministrationController |  |
| 152 | rate_limiting_status | home.php?route=rate_limiting_status | SystemAdministrationController |  |
| 6 | system_settings | home.php?route=system_settings | SystemAdministrationController |  |
| 127 | feature_flags | home.php?route=feature_flags | SystemAdministrationController |  |
| 128 | module_enablement | home.php?route=module_enablement | SystemAdministrationController |  |
| 8 | maintenance_mode | home.php?route=maintenance_mode | SystemAdministrationController |  |
| 131 | schema_registry | home.php?route=schema_registry |  |  |
| 132 | migrations | home.php?route=migrations | SystemAdministrationController |  |
| 133 | backups | home.php?route=backups | SystemAdministrationController |  |
| 153 | data_retention | home.php?route=data_retention | SystemAdministrationController |  |
| 154 | data_purge_policies | home.php?route=data_purge_policies |  |  |
| 9 | api_explorer | home.php?route=api_explorer | SystemAdministrationController |  |
| 136 | webhook_registry | home.php?route=webhook_registry | SystemAdministrationController |  |
| 137 | job_inspector | home.php?route=job_inspector | SystemAdministrationController |  |
| 138 | system_diagnostics | home.php?route=system_diagnostics | SystemAdministrationController |  |
| 5 | activity_audit_logs | home.php?route=activity_audit_logs | SystemAdministrationController |  |
| 140 | permission_changes | home.php?route=permission_changes | SystemAdministrationController |  |
| 141 | policy_violations | home.php?route=policy_violations | SystemAdministrationController |  |
| 142 | security_incidents | home.php?route=security_incidents | SystemAdministrationController |  |
| 79 | manage_announcements | home.php?route=manage_announcements |  |  |
| 81 | manage_sms | home.php?route=manage_sms |  |  |
| 80 | manage_email | home.php?route=manage_email |  |  |
| 157 | manage_uniform_sales | manage_uniform_sales |  |  |
| 165 | learning_areas | home.php?route=learning_areas |  |  |
| 166 | all_subjects | home.php?route=all_subjects |  |  |
| 167 | assign_subjects_to_teachers | home.php?route=assign_subjects_to_teachers |  |  |
| 168 | curriculum_cbc | home.php?route=curriculum_cbc |  |  |
| 204 | all_teachers | home.php?route=all_teachers |  |  |
| 205 | class_teachers | home.php?route=class_teachers |  |  |
| 206 | subject_teachers | home.php?route=subject_teachers |  |  |
| 207 | teacher_workload | home.php?route=teacher_workload |  |  |
| 208 | teacher_performance_reviews | home.php?route=teacher_performance_reviews |  |  |
| 209 | all_parents | home.php?route=all_parents |  |  |
| 210 | parent_meetings | home.php?route=parent_meetings |  |  |
| 211 | parent_feedback | home.php?route=parent_feedback |  |  |
| 212 | pta_management | home.php?route=pta_management |  |  |
| 213 | all_classes | home.php?route=all_classes |  |  |
| 233 | enrollment_trends | home.php?route=enrollment_trends |  |  |
| 234 | performance_trends | home.php?route=performance_trends |  |  |
| 235 | attendance_trends | home.php?route=attendance_trends |  |  |
| 236 | comparative_reports | home.php?route=comparative_reports |  |  |
| 231 | permissions_exeats | home.php?route=permissions_exeats |  |  |
| 232 | dormitory_management | home.php?route=dormitory_management |  |  |
| 240 | deputy_head_academic_dashboard | home.php?route=deputy_head_academic_dashboard |  |  |
| 241 | deputy_head_discipline_dashboard | home.php?route=deputy_head_discipline_dashboard |  |  |
| 99998 | headteacher_test_item | /headteacher_test_item |  |  |
| 156 | manage_fee_structure | home.php?route=manage_fee_structure |  |  |
| 100000 | unmatched_payments | home.php?route=unmatched_payments |  |  |
| 100001 | vendors | home.php?route=vendors |  |  |
| 100002 | purchase_orders | home.php?route=purchase_orders |  |  |
| 100003 | petty_cash | home.php?route=petty_cash |  |  |
| 100004 | bank_accounts | home.php?route=bank_accounts |  |  |
| 100005 | bank_transactions | home.php?route=bank_transactions |  |  |
| 100006 | mpesa_settlements | home.php?route=mpesa_settlements |  |  |
| 100008 | support_staff_dashboard | home.php?route=support_staff_dashboard |  |  |
| 100010 | competencies_sheet | home.php?route=competencies_sheet | academic | getCompetencyRatings |
| 100011 | formative_assessments | home.php?route=formative_assessments | academic | getFormativeAssessments |
| 100009 | national_exams | home.php?route=national_exams | academic | getNationalExams |
| 100013 | sick_bay | home.php?route=sick_bay | health | getSickBay |
| 100012 | student_health | home.php?route=student_health | health | getStudentHealth |
| 100014 | manage_library | home.php?route=manage_library | library | getBooks |
| 100029 | student_id_cards | home.php?route=student_id_cards |  |  |
| 100023 | manage_family_groups | home.php?route=manage_family_groups |  |  |
| 44 | manage_subjects | home.php?route=manage_subjects |  |  |
| 100007 | manage_teachers | pages/manage_teachers.php |  |  |
| 67 | staff_performance | home.php?route=staff_performance |  |  |
| 100016 | announcements | home.php?route=announcements |  |  |
| 100017 | circulars | home.php?route=circulars |  |  |
| 100031 | timetable | home.php?route=timetable |  |  |
| 100055 | student_profiles | home.php?route=student_profiles |  |  |
| 100053 | students_by_class | home.php?route=students_by_class |  |  |
| 100052 | subject_students_list | home.php?route=subject_students_list |  |  |
| 100054 | view_class_lists | home.php?route=view_class_lists |  |  |
| 100062 | auth_refresh_token | /api/auth/refresh-token | AuthController | postRefreshToken |
| 100063 | auth_logout_refresh | /api/auth/logout-refresh | AuthController | postLogoutRefresh |
| 100471 | Complete Staff Profile | complete_staff_profile |  |  |
