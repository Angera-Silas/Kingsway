# 14. Legacy Reference Audit & Module-by-Module Migration Plan

Status: **ACTIVE — fresh scan 2026-08-09 (multi-line scanner fixed); execution in progress**
Scope: every legacy table/view/column/procedure reference remaining in `api/`, `pages/`, `public/`, `scripts/`
Owner: Dev (any future coder/LLM must be able to execute this without the user present)
Related: `11_BUSINESS_LOGIC_QUERY_MAPPING.md`, `13_MODULE_MIGRATION_PLAN.md`, `progress.md`

> **2026-08-09 update.** The scanner at `/tmp/opencode/kingsway_scan/scan_legacy_sql.py` had a
> multi-line blind spot (see §2) that under-reported the remaining work (7 missing tables). After
> fixing it and refreshing the live-schema reference files the true remaining inventory is
> **33 missing tables, 0 missing procs, 169 wrong columns** — §4 below is the live source of truth.
> The old §5 module plans below are partially executed (see `progress.md` for the completion log);
> the per-module replacement logic that is still described there applies to the §4 targets.

---

## 1. Objective and Ground Rules

**Objective:** drive the remaining legacy-SQL references to zero against the live `KingsWayAcademy` DB (normalised schema). A reference is "legacy" when the object named in SQL does not exist in the live DB, or when a live table is queried with a column it does not have.

**Hard rules (do not violate):**
1. **No legacy object may remain in runtime code.** This includes the legacy table names listed in §4.1 and the retired columns in §4.4.
2. **No `users.*name/email` columns.** `users` has no `first_name/last_name/email`; names/email live on `persons` (via `users.person_id`). Roles live only in `user_roles`. Use `MIN(ur.role_id)` or `ORDER BY ur.role_id LIMIT 1` for a primary role.
3. **Do NOT recreate retired tables** (`routes`, `auth_sessions`, `rate_limit_logs`, `device_blacklist`, `device_logs`, `user_devices`, `system_logs`, `class_streams`, `academic_terms`, `payment_transactions`, …). Re-point business logic to the normalised objects in §4.1 and §5.
4. **Do not leak DB errors to clients.** All replacements must return generic messages; log details via `error_log()`.
5. **Names/phone of parents/students/staff always come from `persons`.** `parents` (live) has only `person_id, occupation, address, status, created_at, updated_at` — never `first_name/phone/email`.

**Executable verification every time:**
```bash
# 1. static legacy scan (zero missing tables/columns/procs must remain for a module)
python3 /tmp/opencode/kingsway_scan/scan_legacy_sql.py \
  /tmp/opencode/kingsway_scan/sql_dump.txt \
  /tmp/opencode/kingsway_scan . \
  /tmp/opencode/kingsway_scan/scan_report.md

# 2. targeted grep on touched module
rg -n 'academic_terms|payment_transactions|class_streams|auth_sessions|rate_limit_logs|budget_items|staff_payments' api/... 

# 3. PHP syntax on every touched file
find api -name '*.php' -newer <marker> -exec php -l {} \;

# 4. unit suite (176 green expected)
vendor/bin/phpunit

# 5. live smoke (Apache/LAMPP; pdo_mysql only in Apache)
curl -s http://localhost/api/<module>...
```

---

## 2. Scan Methodology (reproducible)

**Scanner** (in `/tmp/opencode/kingsway_scan/`):
- `extract_sql.php` — PHP-tokenizer (`T_CONSTANT_ENCAPSED_STRING`, `T_START_HEREDOC`, `T_ENCAPSED_AND_WHITESPACE`) extracts every string literal from every PHP file and emits `<relative_file>\0<sql>` per line. Interpolated `$vars` are normalised to `__VAR__`. Produces `sql_dump.txt` (3849 statements).
- `scan_legacy_sql.py` — tokenises each statement, resolves table aliases (global pass), and cross-checks tables/views/procs/functions/qualified columns against live-schema reference files (`relations.txt`, `routines.txt`, `columns.txt`, `all_columns.txt`, all dumped from `information_schema`).
- `looksLikeSql()` heuristic is not needed anymore: the tokenizer accepts any statement, but clause keyword starts (`FROM/JOIN/INTO/UPDATE/CALL`) avoid prose false positives ("Call Us", "Update failed").

**Reference files** (live `KingsWayAcademy`): 352 tables+views, 181 routines (159 procs + 22 funcs), 5412 `table.column`, 1940 column names.

**Current verified results (fresh run, 2026-08-09):**
- SQL statements: 3849 (extracted from `api/`, `pages/`, `public/`, `scripts/`, `components/`, `layouts/`)
- Missing tables/views: **33** (§4.1)
- Dynamic table names: 12 files (`__var__`/`{__var__`/`$__var__`, §4.1 + §2) — each must resolve to a live table
- Missing procedures: **0**
- Missing functions: **0 real** (6 candidates in §4.3 are dismissed false positives: `greatest` is a MySQL builtin; `idx_action/idx_created/idx_entity/idx_user/users` are `USE INDEX` hints in `AuditLogger.php`)
- Missing qualified columns: **169** (§4.4)

**Scanner fix (2026-08-09):** `extract_sql.php` emits one line per physical line of a multi-line
SQL string (`\0` only on the first). `scan_legacy_sql.py` previously did `if "\0" not in line: continue`,
which silently dropped every continuation line and missed all multi-line table/column references.
The main loop now accumulates continuation lines into full statements before analysis. Reference
files were re-dumped from `information_schema` (352 → 458 relations after migrations 016–022 + gap fixes).

---

## 3. Live-Schema Facts (verified 2026-08-08)

### 3.1 Names & contact data live on `persons`
- `persons`: `id, first_name, middle_name, last_name, dob, gender, national_id_no, photo_url, email, phone`
- `students`: `id, person_id, admission_no, student_type_id, assessment_number, assessment_status, nemis_number, nemis_status, status, application_id, admission_date, blood_group, created_at, updated_at`
- `staff`: `id, person_id, staff_no, staff_type_id, staff_category_id, position, contract_type, employment_date, status, supervisor_id, salary, bank_name, bank_account, created_at, updated_at`
- `parents`: `id, person_id, occupation, address, status, created_at, updated_at`

### 3.2 Academic context chain
- `academic_years` → `academic_year_terms` (via `academic_year_id`) → `terms` (`id, name, code` — **no `term_number`**)
- `classes` → `academic_year_classes` → `academic_year_class_streams` → `streams` (`id, name, code, capacity`)
- `student_academic_enrollments`: `id, student_id, academic_year_id, academic_year_class_stream_id, enrolled_on, enrollment_status enum('pending','active','completed','withdrawn','transferred','graduated')`
- `terms` has NO `term_number`. Use `academic_year_terms` ordering or a computed row number over `academic_year_terms` ordered by `opening_date`.

### 3.3 Finance
- `fee_catalog`: `id, code, name, fee_type_id, student_type_id, default_amount, status`
- `academic_year_fee_schedules`: per-year schedule of fee_catalog entries
- `student_fee_obligations`: per-student obligations
- `payments`: `id, student_id, receipt_no, amount, payment_date, method, reference, parent_id, received_by, status, notes`
- `budgets`: `id, name, academic_year, term, total_amount, description, status, created_by, submitted_by, submitted_at, reviewed_by, reviewed_at, approved_by, approved_at, activated_at, closed_at, review_notes, approval_notes, created_at, updated_at`
- `budget_line_items`: `id, budget_id, category_id, description, allocated_amount, spent_amount, committed_amount, notes, created_at, updated_at`
- `budget_amendments`: `id, budget_id, line_item_id, amendment_type, amount_change, reason, status, requested_by, approved_by, approved_at, rejection_reason, created_at`
- `departments` exists (live).
- `fee_credit_notes`: `id, credit_number, student_id, academic_year, term_id, source_transaction_id, credit_amount, credit_reason, status, applied_amount, remaining_amount, applied_to_year, applied_to_term_id, applied_at, expiry_date, notes, created_by, approved_by, created_at, updated_at`
- `expenses`: `id, expense_number, category_id, description, budget_line_item_id, department_id, financial_period_id, academic_year, term, payment_method, reference_number, vendor_id, receipt_number, notes, attachment_path, amount, expense_date, created_by, status, approved_by, approved_at, paid_by, paid_at, rejected_by, rejected_at, rejection_reason, created_at, updated_at, deleted_at`

### 3.4 Payroll / disbursements
- `payroll_runs`: `id, financial_period_id, month, year, status, workflow, created_by, created_at` — this replaces `payrolls`
- `disbursement_transactions`: `id, disbursement_type, payroll_id, payslip_id, recipient_id, recipient_name, amount, phone_number, account_number, bank_code, bank_name, channel, conversation_id, originator_conversation_id, request_id, transaction_ref, transaction_id, status, result_description, callback_data, bank_charges, retry_count, created_at, completed_at, failed_at` — this replaces `staff_payments`/`supplier_payments` disbursement tracking
- `staff_payroll_profiles` exists; `staff_allowances`: `id, staff_id, name, description, allowance_type, amount, is_taxable, is_recurring, effective_date, start_date, end_date, status, created_at, updated_at`

### 3.5 Communications (replaces the retired `communication_*` family)
- `communications`: `id, type, subject, body, sender_id, status, priority, template_id, academic_year_id, academic_year_term_id, scheduled_at, reminder_at, sender_signature, created_at`
- `message_templates`: `id, name, subject, body, type, created_by, created_at, category, variables, last_used_at, use_count, status, category_id` (replaces `communication_templates`)
- `communication_attachments`, `communication_groups`, `communication_recipients` (live)
- `forum_threads`: `id, title, created_by, forum_type, status, created_at, updated_at`
- `forum_posts`: `id, thread_id, author_id, author_type, body, reply_to_id, created_at`
- `internal_conversations`: `id, title, conversation_type, created_by, is_locked, last_message_at, last_message_by, participant_count, created_at, updated_at`
- `internal_messages`: `id, conversation_id, sender_id, subject, message_body, message_type, priority, status, is_edited, last_edited_at, created_at, updated_at`
- `contact_inquiries`: `id, full_name, email, phone, subject, message, status, ip_address, created_at, updated_at` (already wired for contact form)

### 3.6 Transport
- `transport_routes`: `id, name, start_point, end_point, code, description, distance, estimated_time, fee, status, created_at, updated_at, morning_departure, afternoon_departure, estimated_duration, max_capacity, current_capacity, last_active_date`
- `transport_stops`: `id, route_id, name, sequence, arrival_time, departure_time, location, status, created_at, updated_at` — **no `latitude/longitude/stop_order`**; use `sequence` for order
- `transport_vehicles`, `transport_vehicle_routes` (live)
- `transport_monthly_bills`: `id, student_id, subscription_id, route_id, billing_month, amount_due, payment_status, due_date, generated_at, generated_by, notes, created_at, updated_at`
- `transport_bill_payments`: `id, bill_id, amount, payment_method, transaction_id, received_by, payment_date, notes, created_at`
- **No `drivers` table.** Drivers are staff with a transport role; the `staff` table (`position`, `staff_category_id`, `status`) + `staff_duty_types` is the canonical location. There is currently no `driver_id` on `transport_routes` — the driver linkage is `staff` + `staff_duty_types` (duty code). A `route_schedules` table also exists.

### 3.7 Inventory / uniform
- `inventory_items`, `inventory_locations`, `inventory_categories`, `inventory_transactions`, `inventory_allocations`, `inventory_departments`, `inventory_counts`, `inventory_count_items`, `requisitions` (`id, requisition_number, department_id, academic_year_id, academic_year_term_id, requisition_date, required_date, priority, status, requested_by, approved_by, approved_at, rejection_reason, fulfilled_at, notes, created_at, updated_at`), `requisition_items`, `suppliers`, `expense_categories`, `fixed_assets`
- `uniform_sales`: `id, student_id, item_id, size, quantity, unit_price, payment_status, sale_date, received_date, receipt_no, sold_by, notes, created_at, updated_at`
- `uniform_payment_records`: `id, sale_id, amount, payment_method, reference_no, payment_date, recorded_by, notes, created_at`
- `uniform_sizes`: `id, item_id, size, size_label, size_type, quantity_available, quantity_reserved, quantity_sold, unit_price, reorder_level, last_restocked, created_at, updated_at`
- Replaces retired `uniform_purchases`/`uniform_purchase_items` (there is no separate purchasing table; purchases land on `inventory_transactions` or `requisitions` + `suppliers`).

### 3.8 Attendance
- `attendance_sessions`: `id, code, name, description, type, start_time, end_time, applies_to, applicable_days, is_mandatory, display_order, status, created_at` — **no `session_type`** (use `type`)
- `student_attendance`, `staff_attendance`, `staff_attendance_profiles` (`id, staff_id, work_start_time, work_end_time, late_threshold_minutes, is_active, created_at, updated_at`), `department_attendance_rules`, `boarding_attendance`, `student_transport_attendance`
- `vw_staff_daily_register` exposes `work_start_time`, `late_threshold_minutes` (view computes expected check-in)

### 3.9 System/admin
- `audit_logs`: `id, action, entity, entity_id, user_id, ip_address, user_agent, details (text), status enum('success','failure'), created_at`
- `login_attempts`: `id, username, user_id, ip_address, user_agent, status enum('success','failed'), failure_reason, created_at`
- `user_sessions`: `id, user_id, ip_address, user_agent, login_time, last_activity, logout_time, session_status, created_at`
- `blocked_devices`: `id, user_agent_pattern, reason, created_at, created_by` (UA-pattern based; no user_id/device_fingerprint/is_active)
- `role_permissions`, `roles`, `permissions`, `role_sidebar_menus`, `sidebar_menu_items` (`id, name, label, icon, url, route_id, parent_id, menu_type, display_order, domain, is_active, created_at, updated_at`), `permission_delegations` (`delegated_from_user_id, delegated_to_user_id, form_permission_id, delegation_start_date, delegation_end_date, reason, approved_by, approval_date`), `role_delegations`
- `dashboards`, `role_dashboards`, `school_settings` (`setting_key, setting_value`), `school_configuration`
- Live counters for the SystemAdmin dashboard: `system_security_incidents`, `system_background_jobs`, `system_api_metrics`, `system_backups`, `system_error_logs` (all EXIST). **`system_permission_changes` does NOT exist** → route to `audit_logs`.

---

## 4. Complete Inventory of Legacy References (verified)

### 4.1 Missing tables/views (33, fresh 2026-08-09) — file occurrences

| Legacy object | Files | Live replacement |
|---|---|---|
| `` (empty; dynamic `INSERT INTO \`{__var__\``) | `api/services/SystemAdministrationService.php` | fix dynamic SQL guard (§6) |
| `__var__` / `{__var__` (dynamic) | `api/includes/helpers.php`, `api/modules/Import/DataImporter.php`, `api/modules/finance/FeeManager.php`, `api/modules/users/UsersAPI.php`, `api/modules/website/WebsiteManager.php`, `api/services/StaffMigrationService.php`, `api/services/StaffTeachingAssignmentService.php`, `api/modules/inventory/RequisitionsManager.php`, `api/modules/inventory/StockTransferWorkflow.php`, `api/modules/staff/StaffPayrollManager.php`, `api/modules/transport/DriverManager.php`, `api/services/SystemAdministrationService.php`, `public/layout/public_data.php` | dynamic table-name interpolation; verify each resolves to a live table + allowlist (§6) |
| `academic_terms` | `public/layout/public_data.php` | `academic_year_terms` + `terms` |
| `academic_year_fee_schedule_approvals` | `api/modules/finance/FeeManager.php` | table DOES NOT EXIST live (claimed created by gap-fix migration, but absent). Create it via migration OR re-point audit to `audit_logs` (§5.2) |
| `announcements` | `api/modules/reports/CommunicationReportManager.php` | live `announcements_bulletin` (or `communications`) |
| `asset_maintenance` | `api/modules/reports/InventoryReportManager.php` | `fixed_assets` + `audit_logs` (maintenance events) |
| `auth_sessions` | `scripts/test_auth_idle_timeout.php` | `user_sessions` |
| `class_streams` | `pages/student_portal.php` | `academic_year_class_streams` + `streams` |
| `competencies` | `api/services/InternTeacherAnalyticsService.php` | `core_competencies` / `strand_competencies` (CBC) |
| `conduct_cases` | `api/modules/reports/DisciplineReportManager.php` | `discipline_incidents` |
| `disposal_assets` | `api/modules/inventory/AssetDisposalWorkflow.php` | `asset_disposals` + `asset_disposal_items` (if live) else `inventory_transactions` |
| `failed_auth_attempts` | `api/services/SystemAdminAnalyticsService.php` | `login_attempts` (status='failed') |
| `fee_payments` | `api/modules/finance/ReportingManager.php` | `payments` (+ `payment_allocations`) |
| `fee_structures_detailed` | `scripts/test_c2b_callback.php` | `academic_year_fee_schedules` + `fee_catalog` |
| `intern_competencies` | `api/services/InternTeacherAnalyticsService.php` | `assessment_results` / intern competency table (verify live) |
| `locations` | `api/modules/inventory/StockMovementsManager.php` | `inventory_locations` |
| `migrations` | `api/services/MigrationService.php` | migration tooling checksum table — create in `000_init` (§5.11) |
| `payment_transactions` | `scripts/test_c2b_callback.php` | `payments` |
| `purchase_order_items` | `api/modules/inventory/PurchaseOrdersManager.php` | `purchase_orders` + `requisition_items` (verify live shape) |
| `rate_limit_logs` | `api/middleware/RateLimitMiddleware.php` | no live table; middleware fails open (§8 D1) |
| `staff_payroll` | `api/modules/reports/StaffReportManager.php` | `payslips` + `payroll_runs` |
| `student_addresses` | `api/modules/students/StudentsAPI.php` | `persons.address` or `student_profiles` (verify live) |
| `student_fees` | `api/modules/finance/ReportingManager.php`, `pages/student_portal.php` | `student_fee_obligations` / `vw_student_fee_ledger` |
| `student_guidance` | `pages/student_portal.php` | `counseling_cases` / `counseling_sessions` |
| `student_library` | `pages/student_portal.php` | `library_*` (verify live) |
| `student_medical` | `pages/student_portal.php` | `student_health_records` / `student_health_visits` |
| `student_performance` | `pages/student_portal.php` | `assessment_results` / `term_subject_scores` |
| `student_sports` | `pages/student_portal.php` | `activity_participants` + `activities` (sports type) |
| `student_transport` | `pages/student_portal.php` | `student_transport_assignments` + `transport_routes` |
| `teaching_resources` | `api/services/InternTeacherAnalyticsService.php` | `teaching_materials` (verify live columns) |
| `workflow_stage_logs` | `api/modules/reports/WorkflowReportManager.php` | `workflow_instances` / `workflow_history` |
| `workflow_transitions` | `api/modules/reports/WorkflowReportManager.php` | `workflow_history` |

### 4.2 Missing procedures: none (0). The legacy `CALL sp_run_maintenance` from `maintenance.php` is already removed/stubbed.

### 4.3 Missing functions: none (0). All remaining `fn(...)` refs are MySQL builtins.
> 2026-08-09: the 6 scanner candidates are dismissed — `greatest` is a MySQL builtin; `idx_action/idx_created/idx_entity/idx_user/users` are `USE INDEX` hints in `api/includes/AuditLogger.php`.

### 4.4 Missing qualified columns (169 total, fresh 2026-08-09) — real bugs to fix in code

Grouped by table; columns listed with the files that reference them. Identity columns
(`staff/users/parents/students.first_name|last_name|email|phone*`) all resolve to `persons`.

| Table | Columns | Files |
|---|---|---|
| `academic_year_class_learning_area_teachers` | `.academic_year_class_stream_id`, `.academic_year_id` | ClassTeacherAnalyticsService, InternTeacherAnalyticsService, SubjectTeacherAnalyticsService |
| `academic_year_class_learning_areas` | `.academic_year_class_stream_id` | InternTeacherAnalyticsService, SubjectTeacherAnalyticsService |
| `academic_year_classes` | `.grade_level` | academic/AcademicAPI |
| `academic_year_fee_schedule_approvals` | `.academic_year_fee_schedule_id`, `.action_at`, `.action_by`, `.approval_stage`, `.new_amount`, `.notes`, `.old_amount` | finance/FeeManager (table itself absent — see §4.1) |
| `academic_year_fee_schedules` | `.term_id` | admission/StudentAdmissionWorkflow |
| `academic_year_terms` | `.end_date`, `.start_date` | students/StudentsAPI |
| `activities` | `.created_by`, `.location` | activities/ActivitiesManager, workflows/ActivityPlanningWorkflow |
| `activity_participants` | `.registered_at`, `.registered_by`, `.student_id` | activities/ParticipantsManager, workflows/ActivityRegistrationWorkflow |
| `activity_resources` | `.notes` | workflows/ActivityPlanningWorkflow |
| `activity_schedule` | `.notes` | activities/SchedulesManager |
| `announcements` | `.id`, `.title` | reports/CommunicationReportManager (table absent — see §4.1) |
| `assessment_results` | `.grade_code`, `.grade_points`, `.marked_by`, `.percentage`, `.performance_level`, `.score`, `.score_obtained`, `.student_id` | academic/AcademicAPI, AcademicAssessmentWorkflow, ClassTeacherAnalyticsService, InternTeacherAnalyticsService, DirectorAnalyticsService, SubjectTeacherAnalyticsService |
| `assessments` | `.assessment_type`, `.class_id`, `.classification_code`, `.learning_outcome_id`, `.subject_id`, `.teacher_id`, `.term_id`, `.total_marks` | academic/AcademicAPI, AcademicAssessmentWorkflow, DirectorAnalyticsService, SubjectTeacherAnalyticsService, ClassTeacherAnalyticsService |
| `asset_disposals` | `.disposal_reason`, `.requested_by`, `.status`, `.suggested_method`, `.total_book_value` | inventory/AssetDisposalWorkflow |
| `budget_amendments` | `.amended_by`, `.amendment_reason`, `.new_amount`, `.old_amount` | finance/BudgetManager |
| `budget_line_items` | `.available_balance`, `.category`, `.department_id` | finance/BudgetManager, ExpenseManager, ExpenseApprovalWorkflow |
| `budgets` | `.end_date`, `.fiscal_year`, `.start_date` | finance/BudgetManager |
| `class_streams` | `.class_id`, `.id`, `.stream_name` | pages/student_portal.php (table absent — see §4.1) |
| `classes` | `.level`, `.status` | pages/student_portal.php, DirectorAnalyticsService, SchoolAdminAnalyticsService |
| `competencies` | `.category`, `.id`, `.name` | InternTeacherAnalyticsService (table absent — see §4.1) |
| `discipline_incidents` | `.student_id` | students/StudentsAPI |
| `disposal_assets` | `.asset_id`, `.disposal_id` | inventory/AssetDisposalWorkflow (table absent — see §4.1) |
| `exam_schedules` | `.class_id`, `.subject_id` | SubjectTeacherAnalyticsService |
| `expenses` | `.expense_category`, `.recorded_by`, `.vendor_name` | finance/ExpenseManager, FinanceCrudService |
| `fee_structures_detailed` | `.fee_type_id`, `.id` | scripts/test_c2b_callback.php (table absent) |
| `food_consumption_records` | `.total_cost` | reports/MealReportManager |
| `intern_competencies` | `.achieved_date`, `.competency_id`, `.intern_id`, `.notes`, `.status` | InternTeacherAnalyticsService (table absent) |
| `inventory_categories` | `.parent_category_id` | inventory/CategoriesManager |
| `inventory_items` | `.item_code`, `.unit_of_measure` | inventory/InventoryItemsManager, PurchaseOrdersManager, StockMovementsManager, TransactionsManager |
| `inventory_transactions` | `.created_by`, `.location_id`, `.quantity_change`, `.total_cost` | inventory/StockMovementsManager, TransactionsManager |
| `job_vacancies` | `.department` | staff/StaffAPI (live: `department_id`) |
| `lesson_observations` | `.academic_year_class_stream_id` | InternTeacherAnalyticsService |
| `locations` | `.id`, `.location_name` | inventory/StockMovementsManager (table absent → `inventory_locations`) |
| `migrations` | `.checksum`, `.duration_ms`, `.filename` | services/MigrationService (table absent — §5.11) |
| `notifications` | `.announcement_id`, `.status` | reports/CommunicationReportManager |
| `parents` | `.email`, `.first_name`, `.last_name`, `.phone_1`, `.phone_2` | HeadteacherAnalyticsService, services/payments/BankPaymentWebhook |
| `permissions` | `.name` | services/SystemConfigService |
| `persons` | `.created_at`, `.updated_at` | students/StudentsAPI, users/UsersAPI (drop from INSERT list) |
| `petty_cash_transactions` | `.vendor_name` | FinanceCrudService |
| `purchase_order_items` | `.id`, `.item_id`, `.po_id` | inventory/PurchaseOrdersManager (table absent) |
| `rate_limit_logs` | `.ip_address`, `.request_time` | middleware/RateLimitMiddleware (fail-open, §8 D1) |
| `schemes_of_work` | `.academic_year_id`, `.activities`, `.assessment_methods`, `.class_id`, `.description`, `.learning_area_id`, `.learning_outcomes`, `.resources`, `.strand`, `.strand_id`, `.sub_strand`, `.sub_strand_id`, `.subject_id`, `.subject_name`, `.term_id`, `.term_number`, `.title`, `.week_number` | academic/AcademicAPI (live cols: `scheme_template_id, academic_year_class_learning_area_id, academic_year_calendar_week_id, teacher_id, status, approved_by`) |
| `staff` | `.department_id`, `.first_name`, `.last_name`, `.staff_type` | PayrollWorkflow, StaffReportManager, DirectorAnalyticsService, AcademicAPI, AcademicManager, AttendanceManager, LibraryAPI, InternTeacherAnalyticsService |
| `student_addresses` | `.address_line1`, `.address_line2`, `.city`, `.county`, `.created_at`, `.postal_code`, `.student_id` | students/StudentsAPI (table absent) |
| `student_attendance` | `.student_id` | services/TeacherAnalyticsService (live: `student_academic_enrollment_id`) |
| `student_fee_obligations` | `.amount_paid`, `.balance`, `.fee_structure_detail_id`, `.student_id` | scripts/test_c2b_callback.php |
| `student_parents` | `.created_at`, `.financial_responsibility`, `.updated_at` | admission/StudentAdmissionWorkflow |
| `students` | `.class_id`, `.first_name`, `.last_name`, `.stream_id`, `.stream_name` | services/TeacherAnalyticsService, library/LibraryAPI, pages/student_portal.php, DirectorAnalyticsService |
| `suppliers` | `.city`, `.country`, `.payment_terms`, `.rating` | inventory/SuppliersManager |
| `teaching_materials` | `.class_id`, `.subject_id`, `.term_id` | academic/AcademicManager |
| `timetable_entries` | `.staff_id` | ClassTeacherAnalyticsService |
| `users` | `.email`, `.first_name`, `.last_name` | academic/AcademicAPI, AuthSessionService, SystemAdminAnalyticsService, finance/ExpenseManager, inventory/TransactionsManager, DirectorAnalyticsService |
| `vw_timetable_entries` | `.academic_year_class_id`, `.class_teacher_id` | staff/StaffAPI |
| `workflow_definitions` | `.workflow_type` | inventory/InventoryAPI |
| `workflow_history` | `.workflow_instance_id` | inventory/InventoryAPI |
| `workflow_instances` | `.created_at`, `.initiated_by`, `.workflow_data`, `.workflow_type` | finance/BudgetApprovalWorkflow, ExpenseApprovalWorkflow, FeeApprovalWorkflow, PayrollWorkflow, inventory/InventoryAPI (live cols: `started_at, started_by, data_json, reference_type`) |

---

## 5. Module-by-Module Replacement Plan

Execution order is dependency-safe: fix shared services first (`SystemAdministrationService`, `CommunicationsManager`, `FinanceCrudService`, `SystemConfigService`, `MenuBuilderService`, `DelegationService`), then module APIs, then reports, then importers/scripts.

Each module lists: **Objects** → **Replacement logic** → **Expected behavior** → **Acceptance**.

---

### 5.1 System Administration & Audit (highest priority — shared)

**Files:** `api/services/SystemAdministrationService.php`, `api/modules/system/SystemAdminManager.php`, `api/services/SystemConfigService.php`, `api/services/MenuBuilderService.php`, `api/services/DelegationService.php`, `api/middleware/RateLimitMiddleware.php`

**5.1.1 `accounts()` (line 172)** — `SELECT u.id,u.username,u.email,u.first_name,u.last_name,...` from `users` (broken: users has no such columns). Replace with `persons` join:
```sql
SELECT u.id, u.username, u.status, u.last_login, u.failed_login_attempts,
       u.account_locked_until, u.force_password_change,
       p.email, p.first_name, p.last_name,
       (SELECT r.name FROM user_roles ur
          JOIN roles r ON r.id = ur.role_id
         WHERE ur.user_id = u.id ORDER BY ur.role_id LIMIT 1) AS main_role
FROM users u LEFT JOIN persons p ON p.id = u.person_id
ORDER BY u.id DESC LIMIT 1000
```

**5.1.2 `authenticationLogs()` (line 207)** — `u.email` → `p.email` with `LEFT JOIN persons p ON p.id = u.person_id`; keep `login_attempts` as source.

**5.1.3 `sessions()` (line 217)** — `u.email` → `p.email`; same join; source `user_sessions`.

**5.1.4 `saveRolePermission()` (line 243)** — the `system_permission_changes` INSERT is already guarded by `tableExists()`; replace the guard body with an `audit_logs` INSERT:
```php
$this->writeAudit($actorId, 'permission_assigned', 'role', $roleId,
    ['permission_id' => $permissionId], 'success', $_SERVER['REMOTE_ADDR'] ?? null, $_SERVER['HTTP_USER_AGENT'] ?? null);
```
Delete the `system_permission_changes` branch entirely.

**5.1.5 Dynamic `INSERT INTO \`{__var__\``** — locate the interpolated table-name insert in `SystemAdministrationService` and confirm its runtime target. If it is the bulk/permission path it should become the explicit `audit_logs` write. If it is a generic dynamic table helper, assert the name is in an allowlist before executing.

**5.1.6 `account_unlock_history` (SystemAdminManager:477)** — guarded by `tableExists()`. Replace with:
```sql
INSERT INTO audit_logs(action,entity,entity_id,user_id,ip_address,user_agent,details,status,created_at)
VALUES ('account_unlock','user',?,?,?,?,JSON, 'success', NOW())
```

**5.1.7 `config_sync_log` (SystemConfigService:1158)** — replace `INSERT INTO config_sync_log(...)` with an `audit_logs` row (`action='config_sync'`, `entity='config'`, `entity_id=NULL`, `details` JSON with `config_type,file_path,checksum,records_count,sync_status,error_message`).

**5.1.8 `sidebar_menu_configs` / `user_sidebar_overrides` (MenuBuilderService)** — fold per-item behaviour into `sidebar_menu_items` (add columns only if needed; otherwise map to `role_sidebar_menus`):
- `show_badge/badge_source/badge_color/css_class/tooltip/open_in_new_tab/requires_confirmation/confirmation_message/visibility_rule` → add matching columns to `sidebar_menu_items` via migration, or drop (cosmetic). Prefer dropping the non-essential ones and keeping `role_sidebar_menus.custom_order`/`is_default`.
- `user_sidebar_overrides` → `role_sidebar_menus` with `role_id = user's primary role`; per-user overrides have no live home — log to `audit_logs` and drop the feature, or use `role_sidebar_menus` (record `user_id` in a nullable `user_id` column added by migration).

**5.1.9 `user_delegations_items` / `delegation_audit` (DelegationService)** — live objects are `permission_delegations` (user→user, `form_permission_id` = permission/menu) and `role_delegations` (role→role). Map:
- create → `INSERT INTO permission_delegations(delegated_from_user_id, delegated_to_user_id, form_permission_id, delegation_start_date, delegation_end_date, reason, approved_by, approval_date)`
- list/validate → read `permission_delegations` where `delegated_to_user_id = ?` and date window active
- `delegation_audit` → `audit_logs` (action='delegation', entity='delegation', details JSON of granted_permissions+note)
- the `menu_item_id`/`granted_permissions` semantics map to `form_permission_id` (permission tied to the menu) — check `permissions` has menu linkage; if not, store `menu_item_id` in `details`.

**5.1.10 `rate_limit_logs` (RateLimitMiddleware)** — store is absent and the check is already guarded (`rateLimitStoreAvailable` fails open). **Decision:** leave as-is (safe), OR provision a live store. Recommended: keep fail-open; nginx enforces limits. Document in `progress.md`.

---

### 5.2 Finance (fee adjustments, budget, allowances)

**Files:** `api/services/FinanceCrudService.php`, `api/modules/finance/BudgetManager.php`, `api/modules/finance/DepartmentBudgetManager.php`, `api/modules/finance/AllowanceTemplateAPI.php`, `api/modules/finance/FeeManager.php`, `api/modules/finance/FinancePaymentsAPI.php`, `api/modules/reports/FinanceReportManager.php`

**5.2.1 `financial_adjustments` / `finance_exceptions` (FinanceCrudService)** — live home is `fee_credit_notes` (+ `fee_discounts_waivers` for exceptions that are waivers). Map:
- create adjustment → `INSERT INTO fee_credit_notes(credit_number, student_id, academic_year, term_id, credit_amount, credit_reason, status, created_by, expiry_date)`
- approve/reject → `UPDATE fee_credit_notes SET status=?, applied_amount=?, applied_at=NOW(), approved_by=?, notes=? WHERE id=?`
- list → `SELECT * FROM fee_credit_notes` (map `adjustment_number`→`credit_number`, `type`→`credit_reason`, `amount`→`credit_amount`)
- `finance_exceptions` list/resolve → use `fee_credit_notes` status flow (`pending`/`approved`/`applied`) or `fee_reminders` for reminder-type exceptions. Rejected rows get `status='rejected'`, `resolution_notes`.

**5.2.2 `budget_items` (BudgetManager:430)** — `DELETE FROM budget_items` → `DELETE FROM budget_line_items WHERE budget_id = ?`.

**5.2.3 `department_accounts` / `department_budget_proposals` / `department_fund_requests` (DepartmentBudgetManager)** — map to the live budget model:
- proposals → `INSERT INTO budgets(name, academic_year, term, total_amount, description, status='pending', created_by, submitted_by, submitted_at)`. Attach department via a `department_id` column on `budgets` (add by migration) or via `budget_line_items.category_id` → `expense_categories` → `departments`.
- fund requests → `INSERT INTO budget_amendments(budget_id, line_item_id, amendment_type='fund_request', amount_change, reason, status='pending', requested_by)`; approve/reject via `approved_by/approved_at/rejection_reason`.
- accounts (allocation of money to a dept) → `UPDATE budgets SET total_amount = ?, status='approved', approved_by=?, approved_at=NOW() WHERE id=?`.

**5.2.4 `allowance_templates` (AllowanceTemplateAPI)** — `staff_allowances` is the live home. Map `template_name`→`name`, `template_description`→`description`, `allowance_type`→`allowance_type`, `default_amount`→`amount`, `is_taxable`→`is_taxable`, `is_recurring`→`is_recurring`, `effective_date`→`effective_date/start_date/end_date`, `status`. CRUD directly against `staff_allowances` (drop the "template" abstraction or treat `staff_allowances` with `staff_id=NULL` as templates).

**5.2.5 `academic_terms` (FinanceReportManager:13)** — `SELECT id FROM academic_terms WHERE status='current'` → `SELECT ayt.id FROM academic_year_terms ayt JOIN academic_years ay ON ay.id = ayt.academic_year_id JOIN terms t ON t.id = ayt.term_id WHERE ayt.status='current' AND ay.year_code = YEAR(CURDATE()) ORDER BY ayt.opening_date LIMIT 1`.

**5.2.6 `fee_structure_change_log` (FinanceReportManager:118, LogsReportManager)** — use `audit_logs` (`action='fee_structure_change'`, entity `fee_structure`, details JSON).

**5.2.7 `vw_all_school_payments` (FinancePaymentsAPI:128)** — `SELECT * FROM vw_all_school_payments WHERE student_id = ? ORDER BY transaction_date DESC` → use `vw_student_payment_history_multi_year` (columns: student_id, year, term, paid, due, balance, payment_date, …) or `vw_payment_transactions_with_amount`. Map the fields the endpoint returns.

**5.2.8 `FeeManager` dynamic `__var__`** — the `nextId('fee_catalog')` path inserts into `fee_catalog` (live). Verify no other dynamic target; fee_catalog/fee_structure IDs are fine (auto-increment). Leave as-is once confirmed.

---

### 5.3 Attendance

**Files:** `api/modules/attendance/AttendanceStudentService.php`, `api/modules/attendance/AttendanceStaffService.php`

**5.3.1 `AttendanceStudentService:134`** — replace `ass.session_type` with `ass.type`, and `t.term_number` with a computed term ordinal:
```sql
SELECT sae.academic_year_id, ay.year_code, ay.year_name,
       ayt.id AS term_instance_id,
       ROW_NUMBER() OVER (PARTITION BY ayt.academic_year_id ORDER BY ayt.opening_date) AS term_number,
       t.name AS term_name,
       ayc.class_id, c.name AS class_name,
       sa.register_type, sa.date, sa.status, sa.absence_reason, sa.session_id,
       ass.name AS session_name, ass.type AS session_type
FROM student_attendance sa
JOIN student_academic_enrollments sae ON sa.student_academic_enrollment_id = sae.id
LEFT JOIN academic_years ay ON ay.id = sae.academic_year_id
LEFT JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id
LEFT JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
LEFT JOIN classes c ON c.id = ayc.class_id
LEFT JOIN attendance_sessions ass ON ass.id = sa.session_id
LEFT JOIN academic_year_terms ayt ON ayt.academic_year_id = sae.academic_year_id
LEFT JOIN terms t ON t.id = ayt.term_id
WHERE sae.student_id = ? ORDER BY sa.date ASC, sa.session_id ASC
```
(MySQL 8 window function is fine on the LAMPP MariaDB/MySQL 8 stack; if the runtime is MySQL 5.7, fall back to a correlated subquery `(SELECT COUNT(*) FROM academic_year_terms x WHERE x.academic_year_id=ayt.academic_year_id AND x.opening_date <= ayt.opening_date)`.)

**5.3.2 `AttendanceStaffService:120`** — source `work_start_time`/`late_threshold_minutes` from `staff_attendance_profiles`:
```sql
SELECT sap.work_start_time, sap.late_threshold_minutes
FROM staff_attendance_profiles sap WHERE sap.staff_id = ? AND sap.is_active = 1
```
Keep default `lateThresh = 15`, `expectedCheckIn = null` fallback. Line 172 uses `vw_staff_daily_register` which already exposes both columns — no change.

---

### 5.4 Payroll & Disbursements

**Files:** `api/services/workflows/PayrollApprovalWorkflow.php`, `api/modules/payments/PaymentsAPI.php`, `api/modules/staff/StaffPayrollManager.php`

**5.4.1 `payrolls` (PayrollApprovalWorkflow:603-749)** — live home is `payroll_runs`. Map `payrolls.id`→`payroll_runs.id`, `status`→`payroll_runs.status`, `month/year`→`month/year`, `financial_period_id`→`financial_period_id`. Status flow stays: draft→pending_approval→approved→processing→completed/partial/cancelled/rejected. Update all `UPDATE payrolls SET ...` and `$this->db->update('payrolls', ...)` to `payroll_runs`.

**5.4.2 `staff_payments` (PayrollApprovalWorkflow:664)** — `SELECT COUNT(*) FROM staff_payments WHERE payroll_id = ? AND payment_status='failed'` → `SELECT COUNT(*) FROM disbursement_transactions WHERE payroll_id = ? AND status='failed'`. The per-staff payment rows that `staff_payments` used to hold live in `disbursement_transactions` (`payroll_id`, `payslip_id`, `recipient_id`, `recipient_name`, `amount`, `status`).

**5.4.3 `PaymentsAPI` `staff_payments`/`supplier_payments`** — disbursement flows already write `disbursement_transactions` (verified lines 60–176, 490–510, 714–727). Remove any remaining `staff_payments`/`supplier_payments` reads/writes; confirm the API only surfaces `disbursement_transactions`.

**5.4.4 `staff_child_fee_config` (StaffPayrollManager:843)** — `SELECT config_key, config_value, description FROM staff_child_fee_config WHERE is_active=1` → `SELECT setting_key AS config_key, setting_value AS config_value, NULL AS description FROM school_settings WHERE setting_key LIKE 'staff_child_fee_%'`. (Or `vw_staff_children_fees` for computed fee data.)

---

### 5.5 Communications

**Files:** `api/modules/communications/CommunicationsManager.php`, `InternalCommManager.php`, `ParentPortalMessageManager.php`, `StaffRequestManager.php`

**5.5.1 `communications` / `communication_logs` / `communication_templates` (CommunicationsManager)** — map to live `communications`/`message_templates`. The `communupdate` literal at line 515 is a corrupted `communication_logs` reference (`commun...update...ication_logs`); fix the string and then re-point to `audit_logs` (action='communication_log') or `communications` (sent record). For template CRUD: `message_templates` columns `name, subject, body, type, category, variables, use_count, last_used_at, status, created_by, created_at` replace `communication_templates` (`example_output`→drop/store in `variables`; `template_type`→`type`; `usage_count`→`use_count`; `variables_json`→`variables`).

**5.5.2 `internal_forum_topics` / `internal_forum_posts` (InternalCommManager)** — use `forum_threads` / `forum_posts`:
```sql
-- create topic
INSERT INTO forum_threads(title, created_by, forum_type, status, created_at, updated_at)
VALUES (:title, :created_by, 'internal', 'open', NOW(), NOW());
-- post
INSERT INTO forum_posts(thread_id, author_id, author_type, body, reply_to_id, created_at)
VALUES (:topic_id, :created_by, 'user', :content, NULL, NOW());
-- list topics
SELECT * FROM forum_threads WHERE forum_type='internal' AND status='open' ORDER BY created_at DESC;
-- list posts
SELECT * FROM forum_posts WHERE thread_id = ? ORDER BY created_at ASC;
```
(Distinguish `internal` vs public forum via `forum_type`; `author_type='user'`.)

**5.5.3 `parent_portal_messages` / `parent_portal_message_replies` (ParentPortalMessageManager)** — use `communications` for parent-portal messages (type='parent', sender/recipient stored in `sender_id` + a new `recipient_id` column if needed, or via `communication_recipients`):
- create → `INSERT INTO communications(type, subject, body, sender_id, status, priority) VALUES ('parent', :subject, :body, :sender_id, :status, 'normal')` + `INSERT INTO communication_recipients(...)` for each recipient.
- replies → `communications` row with `type='parent_reply'`, `reply_to` stored in `details`/`sender_signature`; simpler: add a nullable `parent_message_id` column.
- list → `SELECT * FROM communications WHERE type='parent' ...`.
If `communications` lacks the recipient role fields (`recipient_type`, `recipient_id`, `sender_type`), add them via migration (they are safe additive columns).

**5.5.4 `staff_requests` (StaffRequestManager:138)** — replace with `communications` type='staff_request':
```sql
SELECT s.email, s.phone
FROM communications c
JOIN staff s ON s.id = c.sender_id
LEFT JOIN persons p ON p.id = s.person_id
WHERE c.id = ?
```
The email/phone come from `persons` (`p.email`, `p.phone`). Fix `staff.email`/`staff.phone` → `p.email`/`p.phone`.

---

### 5.6 Transport

**Files:** `api/modules/transport/DriverManager.php`, `StopManager.php`, `RouteManager.php`, `StudentTransportAssignmentManager.php`, `StudentTransportPaymentManager.php`, `api/modules/students/StudentScopeService.php`

**5.6.1 Drivers (DriverManager, StudentTransportAssignmentManager, StudentScopeService)** — no `drivers` table. Represent drivers as `staff`:
- create driver → create/update `staff` row (with `position='Driver'`) + `persons` for name/phone; license in `staff_employment_profiles` or `staff_qualifications` (add `license_number` column if needed, or use `staff_qualifications`).
- list → `SELECT s.id, p.first_name, p.last_name, p.phone, s.status FROM staff s JOIN persons p ON p.id = s.person_id WHERE s.position = 'Driver'`.
- `StudentScopeService:268` (`SELECT id FROM drivers WHERE staff_id = ? AND status='active'`) → `SELECT id FROM staff WHERE id = ? AND position='Driver' AND status='active' LIMIT 1`.
- `driver_attendance` → `staff_attendance` (`staff_id`, `date`, `status`) — driver = staff id.

**5.6.2 Stops (StopManager, RouteManager)** — `transport_stops` uses `sequence` (not `stop_order`), no lat/lng:
- INSERT → `INSERT INTO transport_stops(name, route_id, sequence, location, status) VALUES (?,?,?,?,?)` (drop lat/lng or serialize into `location`).
- UPDATE → `SET name=?, route_id=?, sequence=?, location=?, status=? WHERE id=?`.
- list by route → `SELECT * FROM transport_stops WHERE route_id=? ORDER BY sequence ASC`.

**5.6.3 Transport payments (StudentTransportPaymentManager)** — `transport_payments`/`student_transport_payments` → `transport_monthly_bills` (bill per student per month) + `transport_bill_payments`:
- create payment → `INSERT INTO transport_bill_payments(bill_id, amount, payment_method, transaction_id, received_by, payment_date, notes)` where `bill_id` is the matching `transport_monthly_bills.id` for `(student_id, billing_month)`.
- update status → `UPDATE transport_bill_payments SET ...`.
- list for student → join bills: `SELECT b.id, b.billing_month, b.amount_due, p.amount AS amount_paid, p.status, p.payment_method FROM transport_monthly_bills b LEFT JOIN transport_bill_payments p ON p.bill_id = b.id WHERE b.student_id=? ORDER BY b.billing_month DESC`.
- totals → aggregate over `transport_monthly_bills.amount_due` minus `transport_bill_payments.amount`.
- `students.first_name/last_name` (line 103/112) → `persons` join:
```sql
SELECT a.student_id, p.first_name, p.last_name, s.admission_no,
       SUM(p.amount) AS total_paid, a.expected_amount,
       (SUM(p.amount) - a.expected_amount) AS balance
FROM student_transport_assignments a
JOIN students s ON a.student_id = s.id
JOIN persons p ON p.id = s.person_id
LEFT JOIN transport_bill_payments p_ ON ... -- bill-payment mapping
```

**5.6.4 `RouteManager`** — `transport_stops` order: use `sequence` (replace `stop_order`).

---

### 5.7 Inventory, Stock, Uniform

**Files:** `api/modules/inventory/RequisitionsManager.php`, `StockTransferWorkflow.php`, `StockAuditWorkflow.php`, `UniformSalesManager.php`, `api/modules/reports/InventoryReportManager.php`, `api/modules/reports/LogsReportManager.php`

**5.7.1 `inventory_requisitions` (RequisitionsManager)** → `requisitions` + `requisition_items`. Map `inventory_requisitions.id`→`requisitions.id`, `requested_by`→`requested_by`, `status`→`status`, `cancelled_at`→`updated_at`. The join lists at lines 69/86/132 (department/user/status joins) keep working with `requisitions`.

**5.7.2 `inventory_transfers` (StockTransferWorkflow)** → `inventory_transactions` with a transfer type: `INSERT INTO inventory_transactions(item_id, location_id, quantity, transaction_type='transfer', from_location_id, to_location_id, notes, created_by, created_at)`. Replace the numbered `UPDATE inventory_transfers` lines (88–456) with the equivalent `inventory_transactions` status workflow (or `inventory_allocations` for the receiving side).

**5.7.3 `stock_audits` (StockAuditWorkflow)** → `inventory_counts` + `inventory_count_items`. Map audit header → `inventory_counts` (`count_date`, `status`, `counted_by`, `verified_by`, `completed_at`), audit line items → `inventory_count_items(item_id, expected_quantity, actual_quantity, difference)`.

**5.7.4 `uniform_purchases` / `uniform_purchase_items` (UniformSalesManager)** → requisitions/inventory model: purchases → `requisitions` (`status='approved'`) with items → `requisition_items`; receiving updates `inventory_transactions` and `uniform_sizes.quantity_available`. Sales/payments already use `uniform_sales`/`uniform_payment_records`.

**5.7.5 `inventory_adjustment_logs` (InventoryReportManager) / `inventory_logs` (LogsReportManager)** → `inventory_transactions` (adjustment type) with `created_at` ordering; report queries join `inventory_items`.

---

### 5.8 Importers & DataImporter

**File:** `api/modules/Import/DataImporter.php`

| Legacy INSERT | Live replacement |
|---|---|
| `fee_structure` (446) | `academic_year_fee_schedules` (+ `fee_catalog` lookup by name) |
| `budget_lines` (513) | `budget_line_items` |
| `student_results` (552) | `assessment_results` |
| `formative_assessment_scores` (592) | `assessment_results` |
| `attendance` (631) | `student_attendance` (or `staff_attendance` based on row type) |
| `subjects` (694) | `learning_areas` |
| `food_stock` (760) | `daily_meal_allocations` / `food_consumption_records` |
| `uniform_items` (794) | `inventory_items` (+ `uniform_sizes`) |
| `academic_terms` (827) | `academic_year_terms` + `terms` |
| `import_logs` (937) | `staff_import_batches` / `staff_import_rows` |

Each requires a column mapping in the importer; write the mapping table in the module and validate one sample row end-to-end per sheet.

---

### 5.9 Public pages / website

**Files:** `public/layout/public_data.php`, `api/modules/website/WebsiteManager.php`

**5.9.1 `admission_enquiries` (line 181)** — replace with `contact_inquiries`:
```php
"INSERT INTO contact_inquiries (full_name,email,phone,subject,message,ip_address)
 VALUES (?,?,?,?,?,?)"
// map parent_name→full_name, child_name+grade→subject, ''→message
```
Update the caller (`kw_save_admission_enquiry`) argument order.

**5.9.2 `parents` INSERT (line 348)** — `parents` has no name/phone columns. Create/find the parent via `persons`, then link:
```php
-- find or create person
INSERT INTO persons (first_name, middle_name, last_name, email, phone, national_id_no) VALUES (...);
$personId = $db->lastInsertId();
-- parent row links the person
INSERT INTO parents (person_id, address, status) VALUES (?, ?, 'active');
```
Replace `SELECT id FROM parents WHERE phone_1 = ? OR id_number = ?` with the persons-based lookup: `SELECT pr.id FROM persons pe JOIN parents pr ON pr.person_id = pe.id WHERE pe.phone = ? OR pe.national_id_no = ?`.

**5.9.3 `web_admission_applications` (line 310)** — `admission_applications` already has `web_application_id`; the raw audit copy is redundant. Either (a) drop the separate INSERT and store raw form data in `admission_applications.notes`/`application_source='online'`, or (b) keep a raw copy in `admission_applications` only. Recommended: remove the `web_admission_applications` insert and the `webAppId` linkage (set `web_application_id = NULL` or store `$webRef` in `application_no`).

**5.9.4 `WebsiteManager` dynamic `__var__`** — the interpolated table in the generic CRUD; verify target is live (`news_articles`, `school_events`, `gallery_items`, `page_downloads` all exist). Add an allowlist guard for the dynamic table name.

---

### 5.10 Scripts (non-runtime, but keep green)

- `scripts/test_auth_idle_timeout.php` — `auth_sessions` → `user_sessions` (column `login_time`, `last_activity`, `logout_time`, `session_status`). Update the aging UPDATE and the COUNT query.
- `scripts/test_c2b_callback.php:187` — `payment_transactions` → `payments` (`receipt_no`, `amount`, `method`, `reference`, `status`, `payment_date`). The stored procedure it comments about (creating `payment_transactions`) no longer exists — verify the M-Pesa callback writes `payments` via `MpesaService`/`PaymentsAPI`.
- `scripts/check_db_config.php` — dynamic table ref via `SHOW COLUMNS FROM $table`; guard with allowlist or leave (read-only utility).
- `streams.php:12` — `class_streams` → `academic_year_class_streams` + `streams`:
```sql
SELECT st.id, st.name AS stream_name
FROM academic_year_class_streams aycs
JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
JOIN streams st ON st.id = aycs.stream_id
WHERE ayc.class_id = :cid AND ayc.status = 'active' AND aycs.status = 'active'
ORDER BY st.name ASC
```
- `maintenance.php` — `CALL sp_run_maintenance` is legacy. Either drop the call (the maintenance page otherwise works) or implement the maintenance body in PHP against live tables. **Recommended:** remove the CALL and stub the routine to no-op with a status message.

---

### 5.11 MigrationService (`migrations` table)

`api/services/MigrationService.php` checksums migration files against a `migrations` table that is absent. The migrations tooling is development infrastructure. **Decision:** either (a) keep the tooling and create the `migrations` table via the very first migration, or (b) disable checksum tracking when the table is absent (fail-open like RateLimitMiddleware). Recommended (a): create `migrations(filename PK, checksum, applied_at, duration_ms)` in migration `000_init`.

---

## 6. Cross-cutting Implementation Checklist

1. **Persons join for all name/email/phone** — audit `api/` for `JOIN users u` selecting `u.email|u.first_name|u.last_name`, `JOIN students s` selecting `s.first_name|s.last_name`, `JOIN staff s` selecting `s.email|s.phone`, `parents.*name/phone`, `drivers.first_name/last_name/phone`. Every one → `persons`.
2. **`users` primary role** — any `WHERE role_id =` on users must go through `user_roles`.
3. **Column allowlists** — every dynamic table-name insert must be allowlisted.
4. **`audit_logs` as the universal event sink** — all history (`account_unlock`, `fee_structure_change`, `delegation`, `config_sync`, `permission_*`, `communication_log`, `system_permission_changes`) uses `audit_logs`.
5. **No new legacy objects** — do not introduce new references; scanner must stay at 0 for touched modules.

## 7. Order of Execution & Acceptance

Execution order (2026-08-09, from the fresh §4 inventory; earlier §5.x passes are recorded in `progress.md`):

1. `pages/student_portal.php` + `public/layout/public_data.php` (public-facing inline SQL) → `php -l` → scanner grep on the two files.
2. Reports module (`api/modules/reports/*` + `api/modules/finance/ReportingManager.php`) → same gate.
3. Analytics services (`*AnalyticsService.php` + `services/TeacherAnalyticsService.php`) → same gate. ✅ DONE (2026-08-09): all 10 services scan-clean — 141 SQL statements, 0 missing tables, 0 missing columns; `php -l` clean on all.
4. Workflows + finance (`BudgetApprovalWorkflow`, `ExpenseApprovalWorkflow`, `FeeApprovalWorkflow`, `PayrollWorkflow`, `FeeManager`, `BudgetManager`, `ExpenseManager`, `FinanceCrudService`, `InventoryAPI`) → same gate. ✅ DONE (2026-08-09): all 9 files scan-clean — 264 SQL statements, 0 missing tables/columns; `php -l` clean on all.
5. Inventory module (`AssetDisposalWorkflow`, `StockMovementsManager`, `PurchaseOrdersManager`, `CategoriesManager`, `InventoryItemsManager`, `TransactionsManager`, `SuppliersManager`) → same gate. ✅ DONE (2026-08-09): all 7 files scan-clean — 64 SQL statements, 0 missing tables, 0 missing procs/funcs, 0 missing columns; `php -l` clean on all; Step-5 gate grep (`disposal_assets|locations|purchase_order_items`) 0 SQL hits.
6. Academic module (`AcademicAPI`, `AcademicManager`, `AcademicAssessmentWorkflow`) → same gate. ✅ DONE (2026-08-09): all 3 files scan-clean — 454 SQL statements, 0 missing tables, 0 missing columns (was 39); `php -l` clean on all; the 1 dynamic table (`AcademicManager` `$var` → `assessment_type_classifications`/`learning_areas`) resolves to live tables; `greatest` is a MySQL builtin (see §4.3). Step-6 gate grep on the 39 legacy `table.column` refs → 0 SQL hits (only doc comments remain). Note: `schemes_of_work` now has the SAME live table name (reshaped to `scheme_template_id, academic_year_class_learning_area_id, academic_year_calendar_week_id, teacher_id, status, approved_by`), so the §7 final-gate `-w 'schemes_of_work'` grep can no longer be satisfied literally — the meaningful gate is the §4.4 column list, which is at 0.
7. Students/admission (`StudentsAPI`, `StudentAdmissionWorkflow`) → same gate. ✅ DONE (2026-08-09): both files scan-clean — `StudentsAPI` 160 SQL statements, `StudentAdmissionWorkflow` 52, 0 missing tables, 0 missing columns each; `php -l` clean on both; `greatest` is a MySQL builtin (see §4.3). Fixed: `persons` INSERTs dropped non-existent `created_at`; `listDisciplineCases`/`getDisciplineRecords` join `discipline_incidents` via `student_academic_enrollment_id` → `student_academic_enrollments` → `students` (no `student_id` column); `academic_year_terms.start_date/end_date` → `opening_date/closing_date`; `resolveDisciplineCase` dropped absent `resolved_by`/`resolution_date`; removed dead `student_addresses` INSERT (table absent live + deliverable; address lives on `parents.address`) + orphaned CSV template address columns; `academic_year_fee_schedules.term_id` → `academic_year_term_id`; `student_parents` INSERT/UPDATE dropped absent `financial_responsibility`/`created_at`/`updated_at` and `ORDER BY id` (composite PK, no `id` column). Step-7 gate grep (`student_addresses` + all flagged `table.column` refs) → 0 SQL hits; EXPLAIN smoke-tested the discipline join + fee-schedule sum against live DB; PHPUnit 176/321/1-skip green.
8. Activities module (`ActivitiesManager`, `ActivityPlanningWorkflow`, `ActivityRegistrationWorkflow`, `ParticipantsManager`, `SchedulesManager`) → same gate. ✅ DONE (2026-08-09): all 5 files scan-clean — 78 SQL statements, 0 missing tables, 0 missing procs/funcs, 0 missing columns; `php -l` clean on all; Step-8 gate grep 0 SQL hits; EXPLAIN + INSERT dry-runs (rolled back) vs live DB; PHPUnit 176/321/1-skip green. Fixed: `activities.created_by`→`started_by`, dropped absent `activities.location` (venue lives on `activity_schedule.venue`); `activity_participants` rewired `student_id`→`student_academic_enrollment_id` (new `resolveEnrollmentId` helper in both managers; participant INSERT/dup-check/status UPDATEs join via `student_academic_enrollments`), `registered_at`→`joined_at`, dropped absent `registered_by`, manual id via new `nextId()` (table has no AUTO_INCREMENT); `activity_resources.notes` dropped + NOT NULL `resource_name` supplied in workflow INSERT; `activity_schedule.notes` dropped + required `schedule_date` (NOT NULL) added to INSERT/allowed-fields (frontend already sends it).
9. Scripts (`test_c2b_callback.php`, `test_auth_idle_timeout.php`) → same gate. ✅ DONE (2026-08-09): both scan-clean — 11 SQL statements, 0 missing tables, 0 missing columns; `php -l` clean on both; EXPLAIN verified vs live DB; PHPUnit 176/321/1-skip green. Fixed per the Step-9 mapping: `auth_sessions`→`user_sessions` (idempotent `last_activity` age + row-count checks); `payment_transactions`→`payments` (verification SELECT maps `amount_paid→amount`, `payment_method→method`, `reference_no→reference`); `fee_structures_detailed`→`academic_year_fee_schedules`+`fee_catalog` and `student_fee_obligations.student_id/fee_structure_detail_id/amount_paid/balance`→`student_academic_enrollment_id` join + `academic_year_fee_schedule_id`→`fee_catalog.name` (`amount_paid`/`balance` are view-derived, not columns); student lookup now joins `persons` for `first_name/last_name` (students has none).
10. MigrationService (`migrations` table) → final gate. ✅ DONE (2026-08-09): created `database/migrations/000_init.sql` (the very first migration, per §5.11 decision (a)) with the `migrations` table DDL in lockstep with `MigrationService::ensureMigrationsTable()` (`id` AUTO_INCREMENT PK, `filename` UNIQUE, `checksum`, `applied_at`, `duration_ms`); applied to live `KingsWayAcademy` (`SHOW CREATE TABLE` matches); synced into `KingWayDatabase_3nf_4nf_implemented.sql` (verified: extracted DDL round-trips on scratch DB `kwa4nf_verify` with identical CREATE TABLE + indexes + AUTO_INCREMENT); every SQL statement the service runs (`SELECT filename, checksum`, INSERT with `filename/checksum/duration_ms`, status SELECT) executes against live DB. `php -l` clean on `MigrationService.php`; PHPUnit 176/321/1-skip green.

**Final gate (all modules):**
- `vendor/bin/phpunit` → 176 tests / 321 assertions / 1 skipped, all green. ✅
- `php -l` on every touched file → clean. ✅
- Scanner re-run → missing tables = 0, missing columns = 0 (for touched paths; `rate_limit_logs` and `migrations` excluded per §8 decisions). ✅ — aggregate re-scan of all Step 6–10 touched files: 305 SQL statements, 0 missing tables, 0 missing columns; only remaining candidate is `greatest` (MySQL builtin, §4.3). `migrations` now exists live so it no longer needs the exclusion.
- `rg -n -w 'academic_terms|payment_transactions|class_streams|auth_sessions|rate_limit_logs|staff_payroll|fee_payments|student_fees|schemes_of_work|student_addresses|intern_competencies|competencies|teaching_resources|failed_auth_attempts|disposal_assets|locations|purchase_order_items|asset_maintenance|conduct_cases|workflow_stage_logs|workflow_transitions|announcements|workflow_instances|workflow_data|workflow_type|initiated_by' api/ public/ scripts/ pages/` → 0 hits. **Important:** use `-w` word-boundary matching — `class_streams` is a substring of the live `academic_year_class_streams` and `payment_transactions` appears inside the live `vw_payment_transactions_with_amount`, so a plain substring grep produces false positives. Doc comments and migration scripts (`database/`, `scripts/*.sql`) are excluded from the runtime gate.
- Live smoke of each touched module's read + write endpoints via Apache (curl + token). ✅ (2026-08-09) — full-codebase scan (646 files, 3816 SQL statements) closed the last 9 real legacy refs (below); smoke-verified via `https://localhost/Kingsway/api` with a JWT: `attendance/dormitories` 200, `library/issues|fines|books|summary` 200 (after fixing a pre-existing `LibraryAPI::errorResponse()` visibility fatal that broke the whole module), `POST /api/users` 200 (person INSERT + row verified + cleaned up), `staff/internal-opportunities` 200, `auth/refresh-token` 200 (JWT now carries email from `persons`). Residual scan flags are all documented false-positives/by-design: `rate_limit_logs` (D1 fail-open), `greatest` (builtin, §4.3), `idx_*`/`users` (CREATE TABLE index definitions in AuditLogger), `vw_timetable_entries.academic_year_class_id/class_teacher_id` (alias-shadowing — columns exist on `academic_year_class_streams`), and allowlist/regex-protected `{$table}` interpolations. Legacy-names grep residual = only migration tooling (`.py`/`.sql`/`validate_schema.php`) + doc comments, excluded per plan.

## 8. Open Decisions (documented, not blocking)

| # | Decision | Status |
|---|---|---|
| D1 | `rate_limit_logs`: keep fail-open (nginx enforces limits) | Recommended — record in progress.md |
| D2 | `migrations`: create `migrations` table in `000_init` | ✅ Done (2026-08-09) — `database/migrations/000_init.sql`, applied live + synced to deliverable (§7 item 10) |
| D3 | `sp_run_maintenance`: remove CALL from maintenance.php | ✅ Removed (scan shows 0 missing procs) |
| D4 | `sidebar_menu_configs` cosmetic columns: drop vs add to `sidebar_menu_items` | ✅ Done (2026-08-09) — table already retired: `MenuBuilderService::getMenuItemConfig()/saveMenuItemConfig()` are explicit no-ops with doc comments; zero SQL references remain (only comments) |
| D5 | `web_admission_applications` audit copy: remove (store in `admission_applications.notes`) | ✅ Done (2026-08-09) — 0 references repo-wide (table absent live + deliverable); nothing to remove |
| D6 | `staff_child_fee_config` → `school_settings` keys | ✅ Done (2026-08-09) — config already lives on `staff_children.fee_deduction_enabled` + `fee_deduction_percentage` (used by FinanceAPI/StaffRecordsService); only a doc-comment mapping remains in `scripts/db_reauth_procs.py` |
| D7 | `academic_year_fee_schedule_approvals` (FeeManager) — table absent live despite gap-fix claim | ✅ Done (2026-08-09) — resolved by re-pointing to `audit_logs` (the plan's alternative): FeeManager approval flow is now entirely in-memory bundle (`MIN(id)` of schedule rows) + status columns (`approved_by`/`approved_at`/`updated_at`) on `academic_year_fee_schedules` + `audit_logs` event trail (`fee_structure_drafted`); 0 references to any absent approval table |
