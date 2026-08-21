# 11 — Business-Logic Query Mapping (Legacy → New 3NF/4NF Schema)

**Date:** 2026-08-02 · **Scope:** every literal table identifier referenced by PHP business logic in `config/`, `api/`, `scripts/` (279 files scanned) mapped against the live `KingsWayAcademy` 3NF/4NF schema (350 tables + 102 views).

Source artifacts: `/tmp/opencode/scan_queries.py`, `php_table_refs.json`, `tbl_files.json`, `normalization_mapping.json`; authoritative per-table normalization decisions in `10_NORMALIZATION_MAPPING/*.md`.

## 1. Inventory summary

- Distinct literal table identifiers referenced: **542** (540 non-dynamic)

- Present in new schema (reused or already-correct): **275**

- **MISSING from new schema (must be re-pointed or re-created): 265**

  - 78 have an authoritative normalization decision (Part A)

  - ~60 are verified/strong-evidence legacy tables needing re-pointing (Part B)

  - ~35 are integration/feature tables with no direct equivalent — need creation or a design decision (Part C)

  - ~90 are column/alias/interpolated noise to ignore (Part D)

- New-schema objects **never** referenced by PHP (wire-in opportunities): **177** (Part E)


## 2. PART A — Authoritative legacy→new mappings (78) with affected files

Source: `docs/database_audit/10_NORMALIZATION_MAPPING/*.md`. Disposition: SPLIT / MERGE / REUSE-ALTER / RETIRE / RETIRE (become VIEW).


| Legacy table | Files | Disposition | New home(s) |
|---|---|---|---|
| `class_streams` | 46 | SPLIT | `streams (id, name UNIQUE, code UNIQUE, capacity)` master + `academic_year_class_streams` context |
| `academic_terms` | 30 | SPLIT | `terms (id, name UNIQUE, code UNIQUE)` master — Term 1/2/3 stable + `academic_year_terms (id, academic_year_id, term_id, opening_date, half_term_start, half_term_end, closing_date, status, UNIQUE(academic_year_id, term_id))` |
| `payment_transactions` | 13 | REUSE-ALTER | `payments (id, receipt_no UNIQUE, student_id, amount, payment_date, method, reference, received_by, status)` |
| `class_enrollments` | 11 | REUSE-ALTER | `student_academic_enrollments (id, student_id, academic_year_id, academic_year_class_stream_id, enrollment_status, enrollment_date, UNIQUE(student_id, academic_year_id))` — the enrollment-history spine |
| `staff_class_assignments` | 10 | SPLIT | `academic_year_class_learning_area_teachers (academic_year_class_learning_area_id, academic_year_term_id, staff_id, role)` (§4.3) + `academic_year_class_learning_areas (learning_area_id)` with `periods_per_week` |
| `class_schedules` | 9 | SPLIT | `timetable_entries` (academic_year_class_stream_id, academic_year_term_id, day_of_week, time_slot_id, learning_area_id, teacher_id) + `timetable_templates` — §4.13 |
| `fee_structures_detailed` | 9 | SPLIT | `academic_year_fee_schedules` (+ `fee_catalog`, `fee_types`) |
| `curriculum_units` | 8 | RETIRE | none — the unit layer is not part of the target; children repoint to `learning_areas`/`strands` |
| `staff_payroll` | 8 | MERGE | `payroll_runs` (+ `payslips`) |
| `student_fee_balances` | 6 | RETIRE | derived balance view over obligations − allocations − credits |
| `school_calendar` | 5 | SPLIT | `calendar_day_types (id, code UNIQUE, name, affects_day_students, affects_boarders, requires_attendance)` REF + `academic_year_calendar_days (id, academic_year_calendar_id, date, calendar_day_type_id, title?, description?, UNIQUE(academic_year_calendar_id, date))` — §4.13; the parent `academic_year_calendar (id, academic_year_term_id, week_number, week_start, week_end, UNIQUE(academic_year_term_id, week_number))` holds the dated week grid |
| `staff_performance_reviews` | 5 | REUSE-ALTER | `performance_reviews (staff_id, period, rating, reviewed_by)` (§4.11) |
| `drivers` | 4 | REUSE-ALTER | `persons`/`staff` (person base + subtype) + `drivers` (staff/person + driver record) — §4.1 / §4.7 |
| `fee_structures` | 4 | RETIRE | `fee_catalog` (master: id, code UNIQUE, name, fee_type_id, student_type_id?, default_amount) |
| `staff_onboarding_progress` | 4 | RETIRE (parallel) | `workflow_instances` / `workflow_stage_history` (§4.15) |
| `student_discipline` | 4 | SPLIT | `discipline_incidents (student_academic_enrollment_id, academic_year_term_id, type, severity, date)` + `conduct_tracking` (§4.4) |
| `student_promotions` | 4 | MERGE | `student_transitions (id, student_id, from_student_academic_enrollment_id?, to_student_academic_enrollment_id?, academic_year_id, transition_type, reason, decided_by, decided_at, executed_at)` — the single promotion/retention/transfer/graduation writer |
| `alumni` | 3 | RETIRE (become a VIEW — decided) | VIEW over `student_transitions` (transition_type='graduation') + `students` (§4.3/§4.1) |
| `parent_portal_messages` | 3 | MERGE | `internal_messages` + `conversation_participants` (§4.14) |
| `staff_promotions` | 3 | MERGE | `staff_appointments` (unified appointment/promotion history, §4.11) |
| `allowance_templates` | 2 | REUSE-ALTER | `staff_allowances` allowance-type REF (`staff_allowances.type_id`) |
| `auth_sessions` | 2 | MERGE | `user_sessions` (§4.15 canonical session store) |
| `class_year_assignments` | 2 | REUSE-ALTER | `academic_year_classes (id, academic_year_id, class_id, status, UNIQUE(academic_year_id, class_id))` + `academic_year_class_streams (id, academic_year_class_id, stream_id, room_id?, class_teacher_id?, capacity?, status, UNIQUE(academic_year_class_id, stream_id))` |
| `communication_logs` | 2 | MERGE | `audit_logs` (§4.15 — THE history mechanism) |
| `delegation_audit` | 2 | MERGE | `audit_logs` (§4.15) — typed entry |
| `department_accounts` | 2 | MERGE | `budgets` / `department_fund_requests` (finance budget & accounting, §4.6) under the `departments` master (§4.11) |
| `fee_invoices` | 2 | RETIRE | derived per-term invoice report over `student_fee_obligations` + `payments` + `payment_allocations` |
| `fee_structure_change_log` | 2 | RETIRE | `audit_logs` |
| `financial_transactions` | 2 | MERGE | `school_transactions` (ledger spine) |
| `inventory_requisitions` | 2 | REUSE-ALTER | `requisitions` + `requisition_items` — §4.9 (renamed/reshaped from inventory_requisitions) |
| `parent_meetings` | 2 | REUSE-ALTER | `school_events`-aligned meeting fact (§4.13) / `parent_meetings` |
| `parent_portal_sessions` | 2 | MERGE | `user_sessions` / `auth_sessions` (§4.15) |
| `payment_allocations_detailed` | 2 | MERGE | `payment_allocations (payment_id, student_fee_obligation_id, amount_allocated, allocated_by, allocated_at)` |
| `staff_communication_profiles` | 2 | SPLIT | `persons` (primary_email/primary_phone, §4.1) + **[NEW]** `emergency_contacts (person_id, name, phone, relationship?, UNIQUE(person_id, name))` |
| `staff_onboarding` | 2 | RETIRE (parallel) | `workflow_instances` + `audit_logs` (§4.15); §4.11's `staff_onboarding` concept is satisfied by `workflow_instances` |
| `student_core_values` | 2 | REUSE-ALTER (renamed `learner_values_acquisition`) | `learner_values_acquisition (student_academic_enrollment_id, academic_year_term_id, core_value_id, rating)` (relation, §4.5 `core_values`) |
| `student_transport_payments` | 2 | MERGE | `transport_bill_payments` — §4.7 (finance file reconcile) |
| `transport_subscriptions` | 2 | RETIRE | none — covered by `student_transport_assignments` + monthly billing (§4.7) |
| `account_unlock_history` | 1 | MERGE | `audit_logs` (§4.15 — THE history mechanism) |
| `admission_enrollment_confirmations` | 1 | MERGE | `placement_offers` + `student_academic_enrollments` (§4.12/§4.3) |
| `admission_payments` | 1 | MERGE | `payments (receipt_no UNIQUE, student_id, amount, payment_date, method, reference, received_by, status)` (§4.6) with an application reference |
| `admission_placements` | 1 | REUSE-ALTER (renamed `placement_offers`) | `placement_offers (application_id, academic_year_class_stream_id, offered, accepted)` (§4.12) |
| `admission_process_steps` | 1 | MERGE | `workflow_stages` (§4.15) |
| `business_rule_violations_log` | 1 | MERGE | `audit_logs` (§4.15) — typed entry |
| `communication_templates` | 1 | MERGE | `message_templates` + `template_categories` (§4.14 canonical template store) |
| `config_sync_log` | 1 | MERGE | `audit_logs` (§4.15) — typed entry |
| `department_budget_proposals` | 1 | MERGE | `budgets` + `budget_amendments` |
| `department_contacts` | 1 | MERGE | `departments` (display attributes, §4.11) + `contact_directory` (§4.14) |
| `department_fund_requests` | 1 | MERGE | `budget_amendments` + `budgets` |
| `driver_attendance` | 1 | MERGE | `staff_attendance` (staff_id, date, check_in, check_out, status) — §4.11, with duty role |
| `failed_auth_attempts` | 1 | MERGE | `login_attempts` (§4.15 canonical auth-attempt fact) |
| `fee_structure_approvals` | 1 | RETIRE | `workflow_instances` + `audit_logs` (on `academic_year_fee_schedules`) |
| `finance_exceptions` | 1 | RETIRE | `workflow_instances` (exception-as-issue) + `audit_logs` |
| `financial_adjustments` | 1 | SPLIT | `fee_credit_notes` + `fee_discounts_waivers` + `payments` + `audit_logs` |
| `import_logs` | 1 | MERGE | `audit_logs` (§4.15) — typed entry |
| `parent_otp_sessions` | 1 | MERGE | `user_2fa_otp_sessions` (§4.15) |
| `parent_statement_downloads` | 1 | REUSE-ALTER | `page_downloads` / `audit_logs` (§4.14/§4.15) |
| `rate_limit_logs` | 1 | MERGE | `audit_logs` (§4.15) — typed entry |
| `schedules` | 1 | RETIRE | none — no target equivalent; candidate re-home only if a code path reads it |
| `sick_bay_visits` | 1 | MERGE (decided) | `student_health_visits` (single health-visit fact keyed student_id + date, §4.4) |
| `staff_appointment_approvals` | 1 | MERGE | `workflow_history` + `audit_logs` (§4.15) |
| `staff_child_fee_config` | 1 | REUSE-ALTER | `fee_discounts_waivers` config / `fee_catalog` policy (finance, §4.6) |
| `staff_child_fee_deductions` | 1 | REUSE-ALTER | `staff_deductions` / `payslip_items` (payroll-period deduction fact, §4.6) |
| `staff_domain_audit` | 1 | RETIRE (parallel) | `audit_logs (entity_type, entity_id, action, old_values, new_values, actor_id, acted_at)` (§4.15) |
| `staff_lifecycle_actions` | 1 | RETIRE (parallel) | `audit_logs` + `workflow_instances` (§4.15); lifecycle facts owned by `staff_appointments`/promotion history |
| `staff_payroll_adjustments` | 1 | SPLIT | `staff_allowances` (basic-salary effective line) + `audit_logs` |
| `student_fee_carryover` | 1 | RETIRE | `audit_logs` |
| `student_transfer_requests` | 1 | SPLIT | `student_transitions (id, student_id, from_student_academic_enrollment_id?, to_student_academic_enrollment_id?, academic_year_id, transition_type, reason, decided_by, decided_at, executed_at)` (§4.3) + `student_clearances` |
| `system_permission_changes` | 1 | MERGE | `audit_logs` (§4.15) — typed audit entry |
| `transport_assignments` | 1 | RETIRE | none — folded into `student_transport_assignments` (state choice; §4.7) |
| `transport_payments` | 1 | MERGE | `transport_bill_payments` |
| `uniform_purchase_items` | 1 | MERGE | `requisition_items` (under `purchase_orders`) |
| `uniform_purchases` | 1 | MERGE | `purchase_orders` (+ `suppliers`) |
| `uniform_sale_payments` | 1 | MERGE | `uniform_sale_payments (sale_id, amount, method, payment_date, receipt_no)` |
| `unit_topics` | 1 | RETIRE | none — topic content folds into strand/sub-strand content |
| `vw_active_students_per_class` | 1 | RETIRE | real VIEW `vw_active_students_per_class` over `student_academic_enrollments` + `academic_year_class_streams` + `classes` + `streams`, keyed by `academic_year_id` |
| `vw_all_school_payments` | 1 | RETIRE | derived view unioning `mpesa_transactions` + `bank_transactions` + `school_transactions` |
| `vw_outstanding_fees` | 1 | RETIRE | derived view over `student_fee_obligations` (year-scoped) + `students`/`parents` |

## 3. PART B — Verified legacy re-points not covered by the docs (grep-confirmed)

Confirmed directly in code. These are real tables the business logic queries today; the target home exists in the new schema.


| Legacy table | Files | Target home(s) | Evidence |
|---|---|---|---|
| `subjects` | 10 | learning_areas; academic_year_class_learning_areas | JOIN subjects sub ON sca.subject_id = sub.id (AssignmentWorkflow:52); INSERT INTO subjects (DataImporter:694) |
| `student_transfers` | 5 | student_transitions; student_clearances | INSERT/UPDATE/SELECT student_transfers (TransferWorkflow:92-336) |
| `system_logs` | 5 | audit_logs (typed entry); system_error_logs | INSERT INTO system_logs (log_type,action,entity_type,entity_id,details,ip_address) (CommunicationsAPI:552) |
| `student_fees` | 4 | student_fee_obligations + vw_student_fee_balances (derived) | SUM(total_fees-paid_amount) FROM student_fees (FinanceController:1134) |
| `counseling_sessions` | 3 | student_counseling_sessions | file already uses BOTH tables (CounselingAPI:46 new, :147 legacy) |
| `user_delegations_items` | 3 | permission_delegations | INSERT INTO user_delegations_items (DelegationService:30) |
| `staff_payments` | 3 | payslips / payroll_runs (+ mpesa_transactions for disbursement status) | UPDATE staff_payments SET disbursement_status (PaymentsAPI:80,97,167) |
| `discipline_cases` | 1 | discipline_incidents (+ conduct_tracking) | FROM discipline_cases (AcademicController:3625); COUNT open/investigating (:4554) |
| `student_admissions` | 1 | admission_applications (application workflow) / student_academic_enrollments (enrolled) | COUNT FROM student_admissions status pending/reviewing (AcademicController:4411) |
| `fee_payments` | 1 | payments | SUM(amount) FROM fee_payments WHERE YEAR(payment_date) (FinanceController:1133) |
| `staff_assignments` | 1 | academic_year_class_learning_area_teachers; staff_duty_roster | FROM staff_assignments sa (AcademicController:4428) |
| `teacher_subjects` | 1 | academic_year_class_learning_area_teachers | FROM teacher_subjects ts (StaffAPI:1480) |
| `activity_enrollments` | 1 | activity_participants | COUNT(DISTINCT student_id) FROM activity_enrollments (ActivitiesController:1046) |
| `parent_portal_message_replies` | 1 | internal_messages / external_inbound_messages | INSERT/SELECT parent_portal_message_replies (ParentPortalMessageManager:125,143) |
| `transport_attendance` | 1 | design decision (staff_attendance for drivers; attendance_sessions+student_attendance for students) | INSERT INTO transport_attendance (driver_id, student_id, date, status) (TransportController:516) |
| `holidays` | 2 | academic_year_calendar_days + calendar_day_types | INSERT/UPDATE holidays (name,start_date,end_date,year,status) (TermHolidayManager:278,293) |
| `duty_schedules` | 1 | staff_duty_roster | FROM duty_schedules ds (SchedulesManager:136) |
| `internal_forum_topics / internal_forum_posts` | 0 | forum_threads / forum_posts | INSERT/SELECT internal_forum_topics (InternalCommManager:134,145) |
| `device_blacklist` | 2 | blocked_devices | SELECT id FROM device_blacklist WHERE user_id AND device_fingerprint (DeviceMiddleware:76) |
| `admissions_applications` | 1 | admission_applications | FROM admissions_applications a (AdmissionsReportManager:51) |
| `announcements` | 1 | announcements_bulletin | FROM announcements a (CommunicationReportManager:73) — note DirectorAnalyticsService:124 already uses announcements_bulletin |
| `clearance_departments` | 3 | student_clearances (+ departments) | ClearanceManager / TransferWorkflow / DocumentGenerator |
| `staff_onboarding_progress` | 4 | workflow_instances + audit_logs | AuthAPI/StaffAPI/StaffLifecycleService/StaffMigrationService |
| `formative_assessment_scores` | 1 | formative_scores (+ annual_scores for end-of-year) | DataImporter |
| `student_results` | 2 | assessment_results / term_subject_scores / national_exam_results | AcademicController, DataImporter |
| `student_term_enrollments` | 1 | student_academic_enrollments (enrollment-status spine) + academic_year_terms | StudentReportManager |
| `fee_structure_details / fee_structure` | 0 | academic_year_fee_schedules (items) + fee_catalog | AcademicController, DataImporter |
| `budget_items / budget_lines` | 0 | budget_line_items | BudgetManager, DataImporter |
| `inventory_transfers / inventory_transfer_items` | 0 | inventory_allocations (or inventory_movements — verify) | StockTransferWorkflow:88,92 |
| `stock_audits / audit_count_sheets` | 0 | inventory_counts / inventory_count_items | StockAuditWorkflow |
| `disposal_assets` | 1 | asset_disposals (+ fixed_assets) | AssetDisposalWorkflow |
| `asset_maintenance` | 1 | equipment_maintenance | InventoryReportManager |
| `locations` | 1 | storage_locations (+ inventory_allocations) | StockMovementsManager |
| `conduct_cases` | 1 | conduct_tracking (+ discipline_incidents) | DisciplineReportManager |
| `counseling_referrals` | 1 | student_counseling_cases / student_welfare_cases | CounselingController |
| `staff_departments` | 1 | departments + staff_department_assignments | StaffAPI |
| `staff_classes` | 1 | academic_year_classes / academic_year_class_learning_area_teachers | FinanceAPI |
| `parent_communications` | 1 | communications / sms_communications | ClassTeacherAnalyticsService |
| `timetable_sessions / timetable_slots` | 0 | time_slots + timetable_entries + timetable_changes | AcademicController, ClassTeacherAnalyticsService |
| `teacher_class_assignments / class_teacher_assignments` | 0 | academic_year_class_learning_area_teachers / academic_year_class_streams.class_teacher_id | ClassTeacherAnalyticsService, AcademicController |
| `intern_competencies / competencies / teaching_resources` | 0 | core_competencies / learner_competencies / sub_strand_competencies / teaching_materials / activity_resources | InternTeacherAnalyticsService |
| `sidebar_menu_configs / user_sidebar_overrides` | 0 | sidebar_menu_items + role_sidebar_menus | MenuBuilderService |
| `role_delegations_items` | 1 | permission_delegations | MenuBuilderService |
| `allowed_actions / role_form_permissions` | 0 | permissions + form_permissions (RBAC tables) | PermissionManager, RoleManager |
| `library` | 1 | library_books (already in use) — bare `library` capture is noise | LibraryAPI already queries library_books/library_issues/library_fines |
| `uniform_size` | 1 | uniform_sizes | UniformSalesManager |
| `uniform_sale_payments` | 1 | uniform_sale_payments (exists) — verify exact columns | InventoryController |
| `uniform_purchases / uniform_purchase_items` | 0 | purchase_orders + requisition_items (+ suppliers) | UniformSalesManager |
| `supplier_payments` | 1 | payments (+ suppliers) with purchase-order reference | PaymentsAPI |
| `disbursement_transactions` | 1 | mpesa_transactions / bank_transactions (outbound) | PaymentsAPI |
| `workflow_transitions / workflow_stage_logs` | 0 | workflow_history / workflow_stage_history / workflow_instances | WorkflowReportManager |
| `student_readmissions` | 1 | student_academic_enrollments (re-enrollment row) + student_transitions | ReAdmissionWorkflow |
| `term_reports` | 1 | term_consolidations / term_subject_scores (derived) | StudentsAPI |
| `student_addresses` | 1 | persons (address columns) — verify deliverable | StudentsAPI |
| `student_core_values` | 2 | learner_values_acquisition (Part A: REUSE-ALTER) | AcademicController, ParentPortalController |

## 4. PART C — Legacy integration/feature tables with NO direct new-schema equivalent

These are referenced by live code but no equivalent exists in the deliverable. Each needs a small DDL add or a deliberate re-point. Verify columns against the consuming code before writing.


| Legacy table | Files | Proposed action |
|---|---|---|
| `c2b_confirmation_log / c2b_validation_log / c2b_url_registrations` | MpesaPaymentService | Fold into payment_webhooks_log (source=mpesa, signature_verified, payload_hash) + mpesa_transactions; or keep as a dedicated mpesa_webhook_log DDL add |
| `mpesa_stk_requests` | MpesaPaymentService | Re-point to mpesa_transactions (add stk fields if absent) or small DDL add |
| `user_devices` | DeviceSessionController, DeviceMiddleware | Re-point to user_sessions (device_fingerprint columns) or add user_devices DDL |
| `purchase_order_items` | PurchaseOrdersManager | Re-point to requisition_items under purchase_orders; verify line-item columns |
| `activity_awards` | ActivitiesController | Add activity_awards DDL under activities domain (no equivalent in deliverable) |
| `scheduled_reports` | SchedulesAPI | No equivalent — evaluate against system_events / cron-equivalent; or add scheduled_reports DDL |
| `staff_requests` | StaffRequestManager | Re-point to workflow_instances (request-as-workflow) + audit_logs |
| `uniform_size` | UniformSalesManager | Re-point to uniform_sizes |

## 5. PART D — Column / alias / interpolated noise (do NOT map as tables)

Captures that are columns, SELECT … INTO variables, joined aliases, or interpolated identifiers. Confirm per occurrence during the per-file revamp; no DDL or re-point required.


`access_type`, `active`, `activity`, `amount`, `amount_due`, `approved`, `attendance`, `audit`, `basic_salary`, `batch_id`, `blood_group`, `budgeted_amount`, `capacity`, `category`, `class_teacher_id`, `columns_json`, `communupdate`, `completed`, `content_value`, `current`, `current_class_id`, `custom_order`, `dashboard`, `date`, `day_type`, `does`, `entry`, `error`, `events`, `expires_at`, `fields`, `file`, `fine_amount`, `first_name`, `flag`, `follow`, `food_stock`, `formative_total`, `gallery`, `ip`, `is_allowed`, `is_default`, `is_primary`, `learning_area`, `legacy`, `managed`, `management`, `marks_obtained`, `methods`, `migrations`, `name`, `normal`, `notes`, `operation`, `participant`, `participants`, `payment`, `permission`, `permission_type`, `performance_level_id`, `phone`, `previous_balance`, `primary_email`, `profile_completed`, `qr_token`, `quantity`, `quantity_available`, `records`, `relationship`, `resource`, `retrieved`, `schedule`, `score`, `score_obtained`, `setting_value`, `show_badge`, `sick`, `sms`, `source`, `stage`, `start_date`, `stats`, `stream`, `term_id`, `total_amount`, `updated_at`, `user_id`, `weight`, `your`, plus interpolated refs `{$table}`, `{$this->table}`, `{$customerName}`, `{$assignment['class_name']}`, `{$participant['activity_title']}`, `{$fromStage}`, `{$extra['document_table']}`, `{$activity['status']}`, `{$firstName}`, `{$t}` and dynamic `$table`, `$now`.


## 6. PART E — New-schema objects never referenced by PHP (177) — wire-in opportunities

These tables/views exist in the new DB but no business-logic file touches them yet. They are the *destination* of the revamp (and several must replace legacy logic). Notable groups:

- **Core spine:** `persons`, `users`, `academic_year_classes`, `academic_year_class_streams`, `student_academic_enrollments`, `academic_year_class_learning_areas`, `academic_year_class_learning_area_teachers`, `academic_year_terms`, `academic_year_calendar`, `academic_year_calendar_days`, `calendar_day_types`, `academic_year_fee_schedules`, `fee_catalog`

- **Academic:** `annual_scores`, `assessment_benchmarks`, `assessment_history`, `assignments`, `assignment_submissions`, `term_consolidations`, `term_subject_scores`, `discipline_incidents`, `conduct_tracking`, `student_transitions`, `student_suspensions`

- **Staff/HR:** `staff_department_assignments`, `performance_reviews`, `performance_ratings`, `payroll_runs`, `payroll_configurations`, `staff_duty_roster`, `supervision_rosters`, `payslips`(view `vw_payslip_detailed`, `vw_staff_payroll_eligibility`)

- **Finance:** `payment_webhooks_log`, `mpesa_transactions`, `bank_transactions`, `requisitions`, `requisition_items`, `petty_cash_reconciliations`, `budget_line_items`, `fee_credit_notes`, `fee_discounts_waivers`, `placement_offers`

- **Analytic views (already wired):** `vw_student_fee_ledger`, `vw_fee_collection_monthly_trend`, `vw_staff_salary_history`, `vw_staff_leave_history`, `vw_student_learning_progress`, `vw_class_learning_area_performance`, `vw_student_term_performance`, `vw_parent_children`, `vw_student_health_summary`, `vw_student_transport_summary`, `vw_dormitory_occupancy`, `vw_student_attendance_analytics` — plus ~80 more views listed below.


<details><summary>Full 177-object list</summary>

```
academic_year_calendar
academic_year_calendar_days
academic_year_class_learning_area_teachers
academic_year_class_learning_areas
academic_year_class_streams
academic_year_classes
academic_year_fee_schedules
academic_year_terms
admission_decisions
admission_interviews
admission_placement_tests
announcement_views
annual_scores
arrears_settlement_plans
assessment_benchmarks
assessment_history
assignment_submissions
assignments
blocked_devices
blocked_ips
calendar_day_types
conduct_tracking
conversation_participants
csl_activities
daily_meal_allocations
department_attendance_rules
depreciation_schedule
discipline_incidents
emergency_contacts
equipment_maintenance
external_emails
external_institutions
fee_catalog
fee_reminders
grading_comments
group_members
inventory_allocations
inventory_count_items
inventory_counts
inventory_departments
item_batches
item_serials
kpi_achievements
kpi_definitions
kpi_targets
learner_csl_participation
learner_pci_awareness
lesson_deliveries
lesson_template_learning_outcomes
lesson_templates
maintenance_logs
menu_item_ingredients
message_read_status
message_templates
newsletter_subscribers
password_history
payroll_configurations
payroll_runs
pcis
performance_ratings
performance_reviews
permission_delegations
persons
petty_cash_reconciliations
placement_offers
promotion_rules
requisitions
routes_registry
scheme_template_learning_outcomes
scheme_templates
sms_communications
staff_department_assignments
storage_locations
student_academic_enrollments
student_suspensions
student_transitions
sub_strand_competencies
sub_strand_key_inquiry_questions
sub_strand_pci_issues
sub_strand_rubrics
sub_strand_suggested_experiences
sub_strand_values
subject_time_allocations
supervision_rosters
system_access_policies
system_domain_isolation_rules
system_feature_flags
system_maintenance_windows
system_migration_history
system_modules
system_policy_violations
system_rate_limit_rules
system_retention_policies
system_route_access_rules
system_webhooks
tax_brackets
tax_withholding_history
template_categories
term_consolidations
term_transition_log
timetable_changes
timetable_entries
timetable_templates
transport_bill_payments
users
v_active_users
v_payment_security_alerts
v_role_permission_summary
v_user_permissions_effective
v_user_security
vehicle_fuel_logs
vw_active_allocations
vw_active_salary_advances
vw_arrears_summary
vw_asset_depreciation_summary
vw_attendance_by_context
vw_available_fee_credits
vw_boarding_roll_call_today
vw_class_learning_area_performance
vw_class_positions
vw_class_rosters
vw_class_timetable_coverage
vw_currently_blocked_ips
vw_dormitory_occupancy
vw_expected_attendance_today
vw_expense_summary_by_category
vw_failed_attempts_by_ip
vw_fee_collection_monthly_trend
vw_fee_totals_by_class
vw_fee_totals_by_year
vw_food_consumption_summary
vw_internal_conversations
vw_maintenance_schedule
vw_outstanding_by_class
vw_parent_children
vw_parent_summary
vw_payment_tracking
vw_payment_transactions_with_amount
vw_payslip_detailed
vw_pending_sms
vw_petty_cash_summary
vw_requisition_fulfillment
vw_scheme_completion
vw_school_day_context
vw_sent_emails
vw_sponsored_students_status
vw_staff_attendance_anomalies
vw_staff_attendance_status
vw_staff_leave_balance
vw_staff_leave_balances
vw_staff_leave_history
vw_staff_off_day_schedule
vw_staff_payroll_eligibility
vw_staff_salary_history
vw_staff_service_history
vw_student_academic_history
vw_student_arrears
vw_student_attendance_analytics
vw_student_fee_balances
vw_student_fee_clearance
vw_student_fee_ledger
vw_student_finance_history
vw_student_health_summary
vw_student_learning_progress
vw_student_payment_status
vw_student_term_attendance_summary
vw_student_term_performance
vw_student_transport_summary
vw_student_uniform_balance
vw_teacher_weekly_load
vw_term_expected_days
vw_uniform_sales_analytics
vw_uniform_sales_summary
vw_uniform_stock_matrix
vw_unread_announcements
vw_upcoming_class_schedules
vw_upcoming_exam_schedules
```
</details>
