# Inventory Manager — Role Matrix

| Field | Value |
|---|---|
| role_id | 14 |
| scope | school |
| is_system | 0 |
| is_active | 1 |
| users | 1 |
| sidebar items | 0 |
| route-level auth (role_routes) | 106 |
| dashboards | 1 |
| distinct endpoints | 1 |
| unresolved endpoints | 0 |

## Dashboards

| Key | Display | Component | Endpoints | Unresolved |
|---|---|---|---|---|
| store_manager_dashboard | Inventory Manager Dashboard | components/dashboards/store_manager_dashboard.php | 1 | 0 |

## Sidebar items

| id | Label | Type | Parent | URL | Route | Page | Controllers | Status |
|---|---|---|---|---|---|---|---|---|

## Endpoint usage (dedup, all sources)

| Endpoint | Module | API method | Handler | Status | Source |
|---|---|---|---|---|---|
| `GET /inventory/dashboard` | inventory | getDashboard | `Inventory::getDashboard` | OK | dashboard:store_manager_dashboard |

## Authorized routes (role_routes)

| route_id | Name | URL | Controller | Action |
|---|---|---|---|---|
| 86 | home | home.php |  |  |
| 78 | manage_communications | home.php?route=manage_communications |  |  |
| 70 | manage_inventory | home.php?route=manage_inventory |  |  |
| 72 | manage_requisitions | home.php?route=manage_requisitions |  |  |
| 71 | manage_stock | home.php?route=manage_stock |  |  |
| 87 | me | home.php?route=me |  |  |
| 30 | store_manager_dashboard | home.php?route=store_manager_dashboard |  |  |
| 22 | director_owner_dashboard | home.php?route=director_owner_dashboard |  |  |
| 23 | school_administrative_officer_dashboard | home.php?route=school_administrative_officer_dashboard |  |  |
| 24 | headteacher_dashboard | home.php?route=headteacher_dashboard |  |  |
| 28 | intern_student_teacher_dashboard | home.php?route=intern_student_teacher_dashboard |  |  |
| 29 | school_accountant_dashboard | home.php?route=school_accountant_dashboard |  |  |
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
| 68 | mark_attendance | home.php?route=mark_attendance |  |  |
| 45 | manage_timetable | home.php?route=manage_timetable |  |  |
| 51 | view_results | home.php?route=view_results |  |  |
| 64 | manage_staff | home.php?route=manage_staff |  |  |
| 66 | staff_attendance | home.php?route=staff_attendance |  |  |
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
| 47 | manage_lesson_plans | home.php?route=manage_lesson_plans |  |  |
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
| 178 | exam_schedule | home.php?route=exam_schedule |  |  |
| 179 | exam_setup | home.php?route=exam_setup |  |  |
| 180 | supervision_roster | home.php?route=supervision_roster |  |  |
| 181 | grading_status | home.php?route=grading_status |  |  |
| 182 | results_analysis | home.php?route=results_analysis |  |  |
| 183 | report_cards | home.php?route=report_cards |  |  |
| 40 | student_counseling | home.php?route=student_counseling |  |  |
| 229 | special_needs | home.php?route=special_needs |  |  |
| 230 | counseling_records | home.php?route=counseling_records |  |  |
| 50 | submit_results | home.php?route=submit_results |  |  |
| 48 | myclasses | home.php?route=myclasses |  |  |
| 100014 | manage_library | home.php?route=manage_library | library | getBooks |
| 100029 | student_id_cards | home.php?route=student_id_cards |  |  |
| 100023 | manage_family_groups | home.php?route=manage_family_groups |  |  |
| 44 | manage_subjects | home.php?route=manage_subjects |  |  |
| 100007 | manage_teachers | pages/manage_teachers.php |  |  |
| 81 | manage_sms | home.php?route=manage_sms |  |  |
| 80 | manage_email | home.php?route=manage_email |  |  |
| 69 | view_attendance | home.php?route=view_attendance |  |  |
| 67 | staff_performance | home.php?route=staff_performance |  |  |
| 210 | parent_meetings | home.php?route=parent_meetings |  |  |
| 100011 | formative_assessments | home.php?route=formative_assessments | academic | getFormativeAssessments |
| 100016 | announcements | home.php?route=announcements |  |  |
| 100017 | circulars | home.php?route=circulars |  |  |
| 79 | manage_announcements | home.php?route=manage_announcements |  |  |
| 56 | manage_payments | home.php?route=manage_payments |  |  |
| 157 | manage_uniform_sales | manage_uniform_sales |  |  |
| 165 | learning_areas | home.php?route=learning_areas |  |  |
| 204 | all_teachers | home.php?route=all_teachers |  |  |
| 100012 | student_health | home.php?route=student_health | health | getStudentHealth |
| 100009 | national_exams | home.php?route=national_exams | academic | getNationalExams |
| 100010 | competencies_sheet | home.php?route=competencies_sheet | academic | getCompetencyRatings |
| 100062 | auth_refresh_token | /api/auth/refresh-token | AuthController | postRefreshToken |
| 100063 | auth_logout_refresh | /api/auth/logout-refresh | AuthController | postLogoutRefresh |
| 100471 | Complete Staff Profile | complete_staff_profile |  |  |
