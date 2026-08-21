# Phase 3 — Complete Table Classification Matrix (431 tables)

Source of truth: `database/KingsWayDatabase_2026_08_01_1409hrs.sql` (mirrors live `KingsWayAcademy`).
Generated 2026-08-01. Classification legend:

| Code | Meaning |
|---|---|
| MASTER | Stable entity. Does NOT change meaning across years/terms. Kept as-is (or merged/renamed). |
| REF | Reference / lookup data (code lists, definitions, curriculum content). |
| ACTX | Academic context — records bound to a year/term/class/stream/learning-area assignment. |
| SCTX | Student context — records bound to a student *within* an academic year/term/class/stream. |
| TXN | Transactional / event data (payments, attendance, submissions, cases, logs of activity). |
| HIST | History / audit trail. |
| SYS | System / auth / RBAC / operational infrastructure. |
| JXN | Junction / relationship (pure composite key, no own attributes). |
| CFG | Configuration / content / settings. |
| DEP | Deprecated / duplicate / backup (candidate for removal after archiving). |
| RESTR | Requires restructuring (currently carries year/term/class columns on the wrong entity). |

> **"Context required?"** means: does this table already carry (or need) `academic_year_id` / `term_id` / `class_id` /
> `stream_id` / `academic_year_class_id` so historical records stay attributable?

## Classification methodology (per-column evidence)

Each of the 431 rows below was classified by reading the table's **actual DDL** from the dump
(columns, types, nullability, PK/unique/keys, FKs, defaults, comments) — never by name alone:

1. **PK + unique keys** — a stable single-entity row (`id` + `uk_name`) ⇒ MASTER/REF; a fact-about-a-moment
   (unique on `student_id + term_id` etc.) ⇒ TXN/ACTX/SCTX.
2. **FK payload** — references to `academic_years`/`academic_terms`/`class_year_assignments`/`class_enrollments`
   ⇒ context-bearing (ACTX/SCTX/TXN) and must survive transitions. FK load verified in
   `information_schema` (users 98 · staff 60 · students 34 · classes 19 · class_streams 8 · academic_years 9 ·
   academic_terms 9).
3. **Actor columns** (`created_by`, `approved_by`, `received_by`, `assessed_by`, `sold_by`) + timestamps ⇒ operational (TXN).
4. **Enum/list/definition columns** (`*_type`, `*_category`, `*_status` enums, code lists) ⇒ REF.
5. **Only `id` + one FK** ⇒ JXN.
6. **`*_config` / `*_settings` / `*_version` / `is_*` flag-only** ⇒ CFG.
7. **Name contains `log|history|archive|audit|queue|staging|_old|_legacy`** — HIST/SYS/DEP *after* confirming from
   columns (presence of `created_at`, immutable rows, or deprecated comment).

`RESTR` rows are the six structural defects (see `04_phase4_bad_current_state.md`): year columns on the wrong entity,
missing context, or orphaned FKs. Every table appears exactly once; counts sum to **431**.

| # | Table | Classification | Context required? | Notes |
|---|---|---|---|---|
| 1 | academic_capacity_config | CFG | yes (year) | year-scoped capacity thresholds |
| 2 | academic_capacity_reservations | ACTX | yes | reservation per year/term/class/stream |
| 3 | academic_class_progression | ACTX | yes (year) | class progression rule map, effective_from_academic_year |
| 4 | academic_terms | ACTX | yes (year) | terms belong to a year |
| 5 | academic_year_archives | ACTX | yes | year archive lifecycle |
| 6 | academic_year_rollover_log | HIST | — | rollover execution log |
| 7 | academic_years | MASTER | — | year master; duplicate 2026 rows + junk 2031-2033 planning rows → clean |
| 8 | account_unlock_history | HIST | — | auth history |
| 9 | activities | MASTER | no | co-curricular activity catalogue |
| 10 | activity_categories | REF | no | |
| 11 | activity_participants | TXN | no | student joins activity (activity itself may be year-bound) |
| 12 | activity_resources | TXN | no | |
| 13 | activity_schedule | TXN | no | |
| 14 | activity_staff_participants | TXN | no | |
| 15 | admission_applications | TXN | yes (year/term/grade) | applicant → enrollment pipeline; carries grade/academic_year/target_term |
| 16 | admission_decisions | TXN | no | child of application |
| 17 | admission_documents | TXN | no | |
| 18 | admission_enquiries | TXN | no | public web lead |
| 19 | admission_enrollment_confirmations | TXN | no | application→student link |
| 20 | admission_interviews | TXN | no | |
| 21 | admission_payments | TXN | no | application fee |
| 22 | admission_placements | ACTX | yes (class/stream) | recommended/final class+stream placement → should resolve to academic_year_class_stream |
| 23 | admission_placement_tests | TXN | no | |
| 24 | admission_process_steps | CFG | no | UI config |
| 25 | admission_workflow_history | HIST | — | |
| 26 | albums | MASTER | no | media albums |
| 27 | allowance_templates | REF | no | staff allowance definitions |
| 28 | alumni | MASTER | yes (year) | graduation snapshot; holds graduated_class/stream/final_enrollment — keep year-scoped fields |
| 29 | announcements_bulletin | TXN | no | communication |
| 30 | announcement_views | HIST | — | |
| 31 | annual_scores | ACTX | yes (year) | per student per year aggregate — keep, add academic_year_class_id link |
| 32 | api_tokens | SYS | — | |
| 33 | arrears_settlement_plans | TXN | yes (year/term) | finance |
| 34 | assessment_benchmarks | ACTX | yes (year/grade) | benchmark per year/grade/subject |
| 35 | assessment_history | HIST | — | |
| 36 | assessment_results | TXN | yes | result of assessment (assessment carries context) |
| 37 | assessment_rubrics | REF | no | rubric criteria |
| 38 | assessments | ACTX | yes | has class_id/subject_id/term_id but NO academic_year_id → **RESTR: add year context** |
| 39 | assessment_tools | REF | no | |
| 40 | assessment_type_classifications | REF | no | |
| 41 | assessment_types | REF | no | |
| 42 | asset_categories | REF | no | |
| 43 | asset_disposals | TXN | no | fixed asset |
| 44 | assignments | ACTX | yes | already has academic_year_id + term_id + class_id — good |
| 45 | assignment_submissions | TXN | yes | child of assignment |
| 46 | attendance_sessions | REF | no | session definition |
| 47 | audit_logs | HIST | — | |
| 48 | audit_trail | HIST | — | generic row-level audit |
| 49 | auth_sessions | SYS | — | |
| 50 | bank_accounts | REF | no | |
| 51 | bank_transactions | TXN | no | finance in-flow (matches to student) |
| 52 | blocked_devices | SYS | — | |
| 53 | blocked_ips | SYS | — | |
| 54 | boarding_attendance | TXN | yes | per student/dormitory/date/session; year derivable from date — record year context |
| 55 | budget_amendments | TXN | no | finance |
| 56 | budget_line_items | TXN | no | finance |
| 57 | budgets | TXN | yes (year/term) | finance; has academic_year + term |
| 58 | business_rule_violations_log | HIST | — | |
| 59 | careers_benefits | CFG | no | public content |
| 60 | cash_reconciliation_sessions | TXN | no | finance |
| 61 | catering_meal_statuses | TXN | yes | per student/date/meal; year derivable |
| 62 | class_curriculum_coverages | ACTX | yes | year/term/class/stream/learning-area/strand coverage — good context table |
| 63 | class_enrollments | SCTX | yes | **the key enrollment context** — student in year/class/stream; promotion fields here too |
| 64 | classes | MASTER | **RESTR** | carries academic_year/capacity/status/teacher_id; uk_name_year causes dup Grade 1 → strip year-bound columns; base class master |
| 65 | class_promotion_queue | TXN | yes | promotion workflow queue |
| 66 | class_schedules | ACTX | yes | year/term/class/subject/teacher/room — good |
| 67 | class_streams | ACTX | **RESTR** | tied to classes directly (not to class_year) — needs academic_year_class_streams context |
| 68 | class_year_assignments | ACTX | yes | **already the `academic_year_classes` table** — year/class/stream/teacher/capacity/fee_structure |
| 69 | communication_attachments | TXN | no | |
| 70 | communication_groups | MASTER | no | |
| 71 | communication_logs | HIST | — | |
| 72 | communication_recipients | TXN | no | |
| 73 | communications | TXN | no | |
| 74 | communication_templates | REF | no | |
| 75 | communication_workflow_instances | TXN | no | |
| 76 | conduct_tracking | SCTX | yes (year/term) | per student/year/term conduct |
| 77 | config_sync_log | HIST | — | system |
| 78 | contact_directory | MASTER | no | |
| 79 | contact_inquiries | TXN | no | public web lead |
| 80 | conversation_participants | TXN | no | messaging |
| 81 | core_competencies | REF | no | curriculum |
| 82 | core_values | REF | no | curriculum |
| 83 | csl_activities | MASTER | no | community service learning catalogue |
| 84 | curriculum_units | DEP | — | legacy unit; replaced by strands/sub_strands; keep for FK integrity, mark deprecated |
| 85 | daily_meal_allocations | TXN | yes (class) | catering; class-bound → needs year context |
| 86 | dashboards | SYS | — | RBAC/menu |
| 87 | deduction_types | REF | no | payroll |
| 88 | delegation_audit | HIST | — | |
| 89 | department_accounts | TXN | no | dept finance |
| 90 | department_attendance_rules | REF | no | |
| 91 | department_budget_proposals | TXN | no | |
| 92 | department_contacts | CFG | no | content |
| 93 | department_fund_requests | TXN | no | |
| 94 | departments | MASTER | no | |
| 95 | depreciation_schedule | TXN | no | fixed asset |
| 96 | dormitories | MASTER | no | boarding house |
| 97 | dormitory_assignments | SCTX | yes (year) | has academic_year_id — good |
| 98 | driver_attendance | TXN | no | transport |
| 99 | drivers | MASTER | no | |
| 100 | dropped__bak_permissions | DEP | — | backup of old RBAC |
| 101 | dropped__bak_role_permissions | DEP | — | |
| 102 | dropped__bak_role_sidebar_menus | DEP | — | |
| 103 | dropped__bak_routes | DEP | — | |
| 104 | equipment_maintenance | TXN | no | fixed asset |
| 105 | equipment_maintenance_types | REF | no | |
| 106 | exam_schedules | ACTX | yes | year/term/class/subject/room — good |
| 107 | expense_categories | REF | no | |
| 108 | expenses | TXN | yes (year/term/period) | finance; has financial_period + academic_year + term |
| 109 | external_emails | TXN | no | |
| 110 | external_inbound_messages | TXN | no | |
| 111 | external_institutions | MASTER | no | |
| 112 | failed_auth_attempts | HIST | — | system |
| 113 | fee_credit_notes | TXN | yes (year/term) | finance |
| 114 | fee_discounts_waivers | TXN | yes (year/term) | finance |
| 115 | fee_invoices | TXN | yes (year/term) | finance; has academic_year_id + term_id |
| 116 | fee_reminders | TXN | yes (year/term) | |
| 117 | fee_structure_approvals | TXN | yes (year/term) | finance workflow |
| 118 | fee_structure_change_log | HIST | — | |
| 119 | fee_structure_rollover_log | HIST | — | |
| 120 | fee_structure_rollover_schedule | CFG | yes (year) | |
| 121 | fee_structures_detailed | ACTX | yes (year/term) | fee per level/year/term/student_type — good context |
| 122 | fee_structures | DEP | — | legacy simple fee table; still referenced by payment_allocations → migrate |
| 123 | fee_transition_history | HIST | — | finance |
| 124 | fee_types | REF | no | |
| 125 | finance_approval_log | HIST | — | |
| 126 | finance_exceptions | TXN | no | alerts |
| 127 | financial_adjustments | TXN | yes (year/term) | finance |
| 128 | financial_periods | REF | no | finance periods |
| 129 | financial_transactions | TXN | no | generic finance ledger rows |
| 130 | fixed_assets | MASTER | no | asset register |
| 131 | food_consumption_records | TXN | no | catering |
| 132 | formative_scores | TXN | yes | child of assessment |
| 133 | form_permissions | SYS | — | RBAC |
| 134 | forum_posts | TXN | no | |
| 135 | forum_threads | TXN | no | |
| 136 | gallery_items | CFG | no | content |
| 137 | grade_rules | REF | no | |
| 138 | grading_comments | REF | no | |
| 139 | grading_scales | REF | no | |
| 140 | group_members | JXN | no | group↔user |
| 141 | ieps | SCTX | yes (year) | per student/year IEP |
| 142 | import_logs | HIST | — | |
| 143 | internal_conversations | TXN | no | messaging |
| 144 | internal_messages | TXN | no | |
| 145 | inventory_adjustments | TXN | no | |
| 146 | inventory_allocations | TXN | yes (class) | allocated_to_class → year context needed |
| 147 | inventory_categories | REF | no | |
| 148 | inventory_count_items | TXN | no | |
| 149 | inventory_counts | TXN | no | |
| 150 | inventory_departments | REF | no | |
| 151 | inventory_items | MASTER | no | item master |
| 152 | inventory_locations | REF | no | |
| 153 | inventory_requisitions | TXN | no | |
| 154 | inventory_transactions | TXN | no | |
| 155 | item_batches | TXN | no | |
| 156 | item_serials | TXN | no | |
| 157 | job_applications | TXN | no | HR |
| 158 | job_vacancies | MASTER | no | HR |
| 159 | kpi_achievements | TXN | yes (year) | staff |
| 160 | kpi_definitions | REF | no | |
| 161 | kpi_targets | TXN | yes (year) | staff |
| 162 | leadership_team | CFG | no | content |
| 163 | learner_competencies | SCTX | yes (year/term) | per student/year/term |
| 164 | learner_csl_participation | SCTX | yes (year) | |
| 165 | learner_pci_awareness | SCTX | yes (year/term) | |
| 166 | learner_values_acquisition | SCTX | yes (year) | |
| 167 | learning_areas | MASTER | no | curriculum master |
| 168 | learning_outcomes | REF | no | curriculum content (child of strand/sub_strand) |
| 169 | leave_types | REF | no | |
| 170 | lesson_observations | TXN | yes (class/stream) | class/stream-bound → year context |
| 171 | lesson_plans | ACTX | yes | year/term/class/learning-area — good |
| 172 | library_books | MASTER | no | |
| 173 | library_categories | REF | no | |
| 174 | library_fines | TXN | no | |
| 175 | library_issues | TXN | no | |
| 176 | login_attempts | HIST | — | system |
| 177 | maintenance_logs | TXN | no | |
| 178 | meal_plans | TXN | no | catering |
| 179 | media_files | MASTER | no | |
| 180 | menu_item_ingredients | JXN | no | menu↔inventory item |
| 181 | menu_items | MASTER | no | catering |
| 182 | message_read_status | TXN | no | |
| 183 | message_templates | REF | no | |
| 184 | mpesa_transactions | TXN | no | finance in-flow |
| 185 | national_exam_results | SCTX | yes (year) | per student/year/exam |
| 186 | news_articles | CFG | no | content |
| 187 | news_categories | CFG | no | content |
| 188 | newsletter_subscribers | TXN | no | |
| 189 | notifications | TXN | no | system notifications |
| 190 | onboarding_documents | TXN | no | staff |
| 191 | onboarding_tasks | TXN | no | staff |
| 192 | onboarding_task_templates | REF | no | staff |
| 193 | outbound_messages | TXN | no | |
| 194 | page_downloads | CFG | no | public downloads |
| 195 | parent_communication_preferences | CFG | no | parent prefs |
| 196 | parent_meetings | TXN | yes (class) | class-bound → year context |
| 197 | parent_otp_sessions | SYS | — | auth |
| 198 | parent_portal_messages | TXN | no | |
| 199 | parent_portal_sessions | SYS | — | auth |
| 200 | parents | MASTER | no | |
| 201 | parent_statement_downloads | HIST | — | |
| 202 | password_history | HIST | — | system |
| 203 | password_resets | SYS | — | |
| 204 | past_papers | ACTX | yes | year/term/academic_year/subject/learning_area — good |
| 205 | payment_allocations_detailed | TXN | yes | finance |
| 206 | payment_allocations | TXN | no | legacy; references fee_structures → migrate to fee_structures_detailed |
| 207 | payment_reconciliations | TXN | no | |
| 208 | payment_security_audit | HIST | — | finance |
| 209 | payment_transactions | TXN | yes (year/term) | finance; has academic_year + term_id |
| 210 | payment_webhooks_log | HIST | — | finance |
| 211 | payroll_configurations | CFG | yes (financial year) | |
| 212 | payslip_items | TXN | no | payroll |
| 213 | payslips | TXN | no | payroll |
| 214 | pcis | REF | no | curriculum (Pertinent & Contemporary Issues) |
| 215 | performance_levels_cbc | REF | no | CBC |
| 216 | performance_ratings | TXN | no | staff |
| 217 | performance_review_kpis | TXN | no | staff |
| 218 | permission_delegations | SYS | — | |
| 219 | permissions | SYS | — | RBAC |
| 220 | petty_cash_funds | MASTER | no | |
| 221 | petty_cash_reconciliations | TXN | no | |
| 222 | petty_cash_transactions | TXN | no | |
| 223 | portfolio_artifacts | SCTX | yes | child of portfolio (student/year) |
| 224 | portfolios | SCTX | yes (year) | per student/year |
| 225 | promotion_batches | TXN | yes (year) | from/to academic year |
| 226 | promotion_rules | REF | no | |
| 227 | purchase_orders | TXN | no | procurement |
| 228 | rate_limit_logs | HIST | — | system |
| 229 | record_permissions | SYS | — | |
| 230 | refresh_tokens | SYS | — | |
| 231 | requisition_items | TXN | no | |
| 232 | role_dashboards | JXN | no | RBAC |
| 233 | role_delegations | SYS | — | |
| 234 | role_permissions | JXN | no | RBAC |
| 235 | role_routes | JXN | no | RBAC |
| 236 | role_sidebar_menus | JXN | no | RBAC |
| 237 | roles | SYS | — | RBAC |
| 238 | rooms | MASTER | no | |
| 239 | route_permissions | SYS | — | RBAC (note: `routes` = RBAC route registry, NOT transport) |
| 240 | route_schedules | TXN | no | transport |
| 241 | routes | SYS | — | **RBAC route registry** — not transport routes |
| 242 | route_stops | TXN | no | transport |
| 243 | schedule_changes | HIST | — | timetable |
| 244 | schedules | REF | no | generic schedule registry |
| 245 | schema_discovery_cache | SYS | — | internal cache |
| 246 | schemes_of_work | ACTX | yes | year/term/class/strand — good |
| 247 | school_calendar | ACTX | yes (year/term) | per-date calendar |
| 248 | school_configuration | CFG | no | school profile |
| 249 | school_content | CFG | no | |
| 250 | school_events | MASTER | no | |
| 251 | school_facilities | CFG | no | content |
| 252 | school_history | CFG | no | content |
| 253 | school_levels | REF | no | level master (Playgroup/PP1/PP2/G1..G9) |
| 254 | school_programs | CFG | no | content |
| 255 | school_settings | CFG | no | |
| 256 | school_transactions | TXN | yes (period) | finance; has financial_period_id |
| 257 | school_values | CFG | no | content |
| 258 | school_week_config | ACTX | yes (year) | per-year week config |
| 259 | sick_bay_visits | TXN | yes | per student/date; year derivable |
| 260 | sidebar_menu_items | SYS | — | RBAC |
| 261 | sms_communications | TXN | no | |
| 262 | staff_allowances | TXN | no | payroll |
| 263 | staff_appointment_approvals | HIST | — | |
| 264 | staff_appointments | TXN | no | hiring workflow |
| 265 | staff_attendance | TXN | yes (year) | has academic_year_id |
| 266 | staff_attendance_profiles | REF | no | |
| 267 | staff_categories | REF | no | |
| 268 | staff_child_fee_config | CFG | no | |
| 269 | staff_child_fee_deductions | TXN | yes (year/term) | payroll |
| 270 | staff_children | JXN | no | staff↔student |
| 271 | staff_class_assignments | ACTX | yes | teacher per year/class/stream/learning-area — **key context table** |
| 272 | staff_communication_profiles | REF | no | |
| 273 | staff_contracts | MASTER | no | employment history kept here — good |
| 274 | staff_deductions | TXN | no | payroll |
| 275 | staff_domain_audit | HIST | — | |
| 276 | staff_duty_roster | TXN | no | |
| 277 | staff_duty_types | REF | no | |
| 278 | staff_employment_profiles | REF | no | current employment |
| 279 | staff_experience | MASTER | no | employment history |
| 280 | staff_id_cards | MASTER | no | |
| 281 | staff_import_batches | TXN | no | system |
| 282 | staff_import_rows | TXN | no | system |
| 283 | staff_incident_reports | TXN | no | |
| 284 | staff_kpi_templates | REF | no | |
| 285 | staff_leaves | TXN | no | |
| 286 | staff_lifecycle_actions | HIST | — | promotion/transfer/salary history |
| 287 | staff_loans | TXN | no | |
| 288 | staff_offboarding | TXN | no | |
| 289 | staff_off_day_patterns | TXN | no | |
| 290 | staff_onboarding | TXN | no | |
| 291 | staff_onboarding_progress | TXN | no | |
| 292 | staff_payroll_adjustments | HIST | — | |
| 293 | staff_payroll | TXN | no | payroll |
| 294 | staff_payroll_profiles | REF | no | |
| 295 | staff_performance_reviews | TXN | yes (year/term) | has academic_year_id + term_id |
| 296 | staff | MASTER | no | staff master |
| 297 | staff_probation_reviews | TXN | no | |
| 298 | staff_promotions | TXN | no | from/to dept/position history |
| 299 | staff_qualifications | MASTER | no | |
| 300 | staff_salary_advances | TXN | no | |
| 301 | staff_shift_assignments | ACTX | yes (year) | has academic_year_id |
| 302 | staff_types | REF | no | |
| 303 | storage_locations | REF | no | inventory |
| 304 | strand_competency | JXN | no | strand↔competency |
| 305 | strands | REF | no | curriculum |
| 306 | student_activities | JXN | no | student↔activity |
| 307 | student_arrears | TXN | yes (year/term) | finance |
| 308 | student_attendance | TXN | yes | has class_id/term_id/academic_year_id — good |
| 309 | student_boarding_notes | TXN | no | welfare |
| 310 | student_clearances | TXN | no | transfer clearance |
| 311 | student_core_values | SCTX | yes (term) | per student/term |
| 312 | student_counseling_cases | TXN | no | |
| 313 | student_counseling_sessions | TXN | no | |
| 314 | student_discipline | TXN | **RESTR** | NO academic_year/term/class — incidents unattributable to context |
| 315 | student_fee_balances | TXN | yes (year/term) | finance |
| 316 | student_fee_carryover | HIST | — | finance |
| 317 | student_fee_obligations_backup_20260112 | DEP | — | backup copy → drop after verify |
| 318 | student_fee_obligations | TXN | yes (year/term) | finance |
| 319 | student_health_records | MASTER | no | per student persistent health |
| 320 | student_health_reviews | TXN | no | |
| 321 | student_health_visits | TXN | no | |
| 322 | student_id_card_history | HIST | — | |
| 323 | student_id_cards | SCTX | yes (year) | year-issued cards |
| 324 | student_meal_profiles | MASTER | no | per student diet profile |
| 325 | student_parents | JXN | no | student↔parent |
| 326 | student_payment_history_summary | TXN | yes (year/term) | denormalized summary |
| 327 | student_permissions | TXN | no | leave permissions |
| 328 | student_permission_types | REF | no | |
| 329 | student_promotions | TXN | yes | promotion records (from/to year/class/stream) |
| 330 | student_registrations | SCTX | yes | legacy student/class/term registration → fold into class_enrollments |
| 331 | students | MASTER | **RESTR** | carries stream_id (year-bound!) → strip; class/stream belongs in enrollment context |
| 332 | student_suspensions | TXN | yes (year) | has academic_year |
| 333 | student_transfer_requests | TXN | yes (year) | has academic_year_id |
| 334 | student_transport_assignments | TXN | yes (month/year) | has month/year |
| 335 | student_transport_attendance | TXN | no | |
| 336 | student_transport_incidents | TXN | no | |
| 337 | student_transport_notes | TXN | no | |
| 338 | student_transport_payments | TXN | yes (month/year) | |
| 339 | student_types | REF | no | |
| 340 | student_uniforms | MASTER | no | per student sizes |
| 341 | student_vaccinations | MASTER | no | per student health |
| 342 | student_welfare_cases | TXN | no | |
| 343 | student_welfare_notes | TXN | no | |
| 344 | subject_time_allocations | ACTX | yes | year/term/class/subject/teacher — good |
| 345 | sub_strand_competencies | JXN | no | sub_strand↔competency |
| 346 | sub_strand_key_inquiry_questions | REF | no | curriculum |
| 347 | sub_strand_pci_issues | JXN | no | sub_strand↔pci |
| 348 | sub_strand_rubrics | REF | no | curriculum |
| 349 | sub_strands | REF | no | curriculum |
| 350 | sub_strand_suggested_experiences | REF | no | curriculum |
| 351 | sub_strand_values | REF | no | curriculum |
| 352 | suppliers | MASTER | no | |
| 353 | system_access_policies | SYS | — | |
| 354 | system_alerts | SYS | — | |
| 355 | system_api_metrics | HIST | — | |
| 356 | system_background_jobs | SYS | — | |
| 357 | system_backups | SYS | — | |
| 358 | system_domain_isolation_rules | SYS | — | |
| 359 | system_error_logs | HIST | — | |
| 360 | system_events | SYS | — | |
| 361 | system_feature_flags | SYS | — | |
| 362 | system_ip_rules | SYS | — | |
| 363 | system_maintenance_windows | SYS | — | |
| 364 | system_migration_history | HIST | — | |
| 365 | system_modules | SYS | — | |
| 366 | system_permission_changes | HIST | — | |
| 367 | system_policies | SYS | — | |
| 368 | system_policy_violations | HIST | — | |
| 369 | system_rate_limit_rules | SYS | — | |
| 370 | system_retention_policies | SYS | — | |
| 371 | system_route_access_rules | SYS | — | |
| 372 | system_security_incidents | SYS | — | |
| 373 | system_time_bound_access | SYS | — | |
| 374 | system_webhooks | SYS | — | |
| 375 | tax_brackets | REF | no | payroll |
| 376 | tax_withholding_history | HIST | — | payroll |
| 377 | teaching_materials | ACTX | yes | year/term/class/learning-area — good |
| 378 | template_categories | REF | no | |
| 379 | term_consolidations | ACTX | yes (year/term) | per student/term aggregates |
| 380 | term_subject_scores | ACTX | yes (term) | per student/term/subject |
| 381 | term_transition_log | HIST | — | |
| 382 | time_slots | REF | no | timetable |
| 383 | timetable_conflicts | TXN | no | |
| 384 | timetable_templates | REF | no | |
| 385 | tmp_backup_role_dashboards | DEP | — | tmp backup → drop |
| 386 | transport_assignments | TXN | no | |
| 387 | transport_bill_payments | TXN | no | |
| 388 | transport_bills | TXN | yes (month) | |
| 389 | transport_monthly_bills | TXN | yes (month) | |
| 390 | transport_payments | TXN | no | |
| 391 | transport_routes | MASTER | no | **transport** routes |
| 392 | transport_schedules | TXN | no | |
| 393 | transport_stops | REF | no | |
| 394 | transport_subscriptions | TXN | yes (month range) | |
| 395 | transport_vehicle_routes | JXN | no | vehicle↔route |
| 396 | transport_vehicles | MASTER | no | |
| 397 | uniform_payment_records | TXN | no | |
| 398 | uniform_purchase_items | TXN | no | |
| 399 | uniform_purchases | TXN | no | |
| 400 | uniform_sale_payments | TXN | no | |
| 401 | uniform_sales | TXN | no | |
| 402 | uniform_sales_summary | TXN | no | denormalized |
| 403 | uniform_sizes | REF | no | |
| 404 | unit_topics | DEP | — | legacy, child of curriculum_units |
| 405 | user_2fa_backup_codes | SYS | — | |
| 406 | user_2fa_otp_sessions | SYS | — | |
| 407 | user_invitations | SYS | — | |
| 408 | user_login_attempts | HIST | — | |
| 409 | user_permissions | JXN | no | RBAC |
| 410 | user_roles | JXN | no | RBAC |
| 411 | user_routes | JXN | no | RBAC |
| 412 | user_sessions | SYS | — | |
| 413 | users | SYS | — | identity/auth master |
| 414 | vehicle_fuel_logs | TXN | no | |
| 415 | vehicle_maintenance | TXN | no | |
| 416 | vw_active_students_per_class | DEP | — | **misnamed summary table** (prefix `vw_` but is a real table) → rename or convert to view |
| 417 | vw_all_school_payments | DEP | — | misnamed summary table |
| 418 | vw_financial_period_summary | DEP | — | misnamed summary table |
| 419 | vw_inventory_low_stock | DEP | — | misnamed summary table |
| 420 | vw_lesson_plan_summary | DEP | — | misnamed summary table |
| 421 | vw_outstanding_fees | DEP | — | misnamed summary table |
| 422 | vw_upcoming_activities | DEP | — | misnamed summary table |
| 423 | vw_user_recent_communications | DEP | — | misnamed summary table |
| 424 | web_admission_applications | TXN | no | public web application |
| 425 | workflow_definitions | REF | no | workflow engine config |
| 426 | workflow_history | HIST | — | |
| 427 | workflow_instances | TXN | no | |
| 428 | workflow_notifications | TXN | no | |
| 429 | workflow_stage_history | HIST | — | |
| 430 | workflow_stage_permissions | JXN | no | workflow↔permission/role |
| 431 | workflow_stages | REF | no | workflow config |

---

## Phase 3 — Classification summary (verified counts)

| Classification | Count |
|---|---|
| TXN (Transactional) | 160 |
| REF (Reference) | 60 |
| SYS (System/RBAC/auth) | 41 |
| HIST (History/Audit) | 40 |
| MASTER | 36 |
| ACTX (Academic context) | 26 |
| CFG (Configuration/content) | 20 |
| JXN (Junction) | 17 |
| DEP (Deprecated/duplicate/backup) | 17 |
| SCTX (Student context) | 14 |
| **Total** | **431** |

**Tables flagged REQUIRES RESTRUCTURING (carry year/class/stream on the wrong entity):**
1. `classes` — carries `academic_year`, `capacity`, `status`, `teacher_id`; unique `uk_name_year(name, academic_year)` manufactures duplicate classes across years. Target: pure class master + `academic_year_classes`.
2. `class_streams` — tied directly to `classes`, not to the year context → must become `academic_year_class_streams` (context keyed by year + class).
3. `students` — carries `stream_id` (year-bound current stream) → belongs in enrollment context (`class_enrollments`).
4. `assessments` — has `class_id`/`subject_id`/`term_id` but **no `academic_year_id`** → historical assessments cannot be attributed to a year.
5. `student_discipline` — no year/term/class context at all → incidents unattributable to a context.
6. `vw_active_students_per_class`, `vw_all_school_payments`, `vw_financial_period_summary`, `vw_inventory_low_stock`, `vw_lesson_plan_summary`, `vw_outstanding_fees`, `vw_upcoming_activities`, `vw_user_recent_communications` (8) — real tables misnamed as `vw_` views → rename or convert to genuine views (also `class_assignments` is a real VIEW, not a table — code must not treat it as one).

**Verification note:** the audit distinguishes two `routes`-like entities: `routes` (RBAC route registry, SYS) vs `transport_routes` (MASTER). Do not conflate them in app code.
