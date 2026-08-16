# Database Migration Progress

## Academic Analytics Cluster — COMPLETED ✓

All 9 analytics service files migrated from legacy schema to live 3NF/4NF schema:

- **ClassTeacherAnalyticsService.php** — `class_streams` → `academic_year_class_streams`, `students.stream_id` → `student_academic_enrollments`, `student_attendance.student_id` → `student_attendance.student_academic_enrollment_id`, `teacher_class_assignments` → `academic_year_class_learning_area_teachers`, `parent_communications` → `communications`
- **DirectorAnalyticsService.php** — `payment_transactions` → `payments` + `payment_allocations`, `staff_payroll` → `payroll_runs`, `academic_terms` → `academic_year_terms` + `terms`, `fee_structures_detailed` → `academic_year_fee_schedules`, `fee_structures` → `fee_catalog`, `class_streams` → `academic_year_class_streams`
- **HeadteacherAnalyticsService.php** — `discipline_cases` → `discipline_incidents`, `parent_communications` → `communications`, `school_calendar` → `academic_year_calendar_days`, `class_streams` → `academic_year_class_streams`, `student_discipline` → `discipline_incidents`
- **InternTeacherAnalyticsService.php** — `class_streams` → `academic_year_class_streams`, `students.stream_id` → `student_academic_enrollments`, `teacher_class_assignments` → `academic_year_class_learning_area_teachers`
- **SchoolAdminAnalyticsService.php** — `class_streams` → `academic_year_class_streams`, `students.stream_id` → `student_academic_enrollments`, `staff_class_assignments` → `academic_year_class_learning_area_teachers`, `system_logs` → `audit_logs`, `announcements_bulletin` → `announcements_bulletin` (already correct table name)
- **SubjectTeacherAnalyticsService.php** — `subjects` → `learning_areas`, `students.stream_id` → `student_academic_enrollments`
- **DeputyDisciplineAnalyticsService.php** — `discipline_cases` → `discipline_incidents`, `students.stream_id` → `student_academic_enrollments`
- **DeputyAcademicAnalyticsService.php** — `class_streams` → `academic_year_class_streams`, `students.stream_id` → `student_academic_enrollments`
- **AcademicContextService.php** — `academic_terms` → `academic_year_terms` + `terms` (with opening_date/closing_date column mapping)

### Verification
- All 9 files: `php -l` passes
- No remaining legacy table references in FROM/JOIN/UPDATE/INTO clauses
- All 176 PHPUnit tests pass (1 skipped)

## Staff / HR Cluster — IN PROGRESS

Plan: `docs/database_audit/12_STAFF_MODULE_REFACTOR_PLAN.md`. Architecture enforced everywhere:
master → context → operational → history; **term/year transitions APPEND rows, never overwrite**
(teacher responsibility is term-scoped: a mid-year change is a new `academic_year_class_learning_area_teachers`
row; workflow/approval state lives in `workflow_instances` + append-only history, not on the operational row).

### Phase B: Teaching assignments — COMPLETED ✓
- **StaffTeachingAssignmentService.php** — legacy `staff_class_assignments`/`class_streams`/`subjects` split:
  class_teacher → `academic_year_class_streams.class_teacher_id`; subject_teacher → `academic_year_class_learning_area_teachers`
  (bound to learning-area context + term). Reads via shipped view `vw_staff_assignments_detailed`; writes require
  pre-existing year context (academic setup owns context creation). `remove()` split into `removeClassTeacher()`/`removeSubjectAssignment()`.
- **StaffAssignmentManager.php** — now delegates all writes to StaffTeachingAssignmentService; reads via
  `vw_staff_assignments_detailed`/`vw_current_staff_assignments`/`vw_staff_workload`. Removed dead `sp_validate_staff_assignment`
  CALL (proc was repurposed for department appointments in the new schema — signature no longer matches).
- **AssignmentWorkflow.php** — operational row no longer carries a `status` column; approval state lives in
  `workflow_instances`. Assignment is materialised into normalized tables only on approval. Fixed pre-existing
  `startWorkflow()` arg-order bug; head-teacher role check routed through `user_roles`→`roles` (users has no role column).
- **StaffAPI.php** (teaching parts) — `listTeachers`, `getTeacherAssignmentRows`, `getTeachingSchedule` re-pointed to
  the normalized teacher tables; department filter via `staff_department_assignments`; dropped `tsc_no`/`periods_per_week`
  (→ `planned_weeks`)/`work_start_time` (→ `department_attendance_rules`).
- **AcademicController.php** — updated 2 call sites to split remove methods.
- Pre-existing bugs fixed in passing: `StaffAPI::getStaffAssignments/getCurrentAssignments` positional-vs-array args.

### Verification (Phase B)
- `php -l` passes on all touched files
- No teaching-cluster legacy identifiers (`staff_class_assignments`, `class_streams`, `subjects`, `teacher_subjects`) remain
- All 176 PHPUnit tests pass (1 skipped)

### Phase C: Records & directory (identity JOIN) — COMPLETED ✓
Identity (`first_name`/`last_name`/`email`/`phone`/`gender`/`photo_url`) moved to `persons`; `staff` keeps only
`person_id` + employment columns. Decision: **inline `JOIN persons`** (no directory view). `staff.user_id` → `users.person_id`;
`staff.department_id` → `staff_department_assignments`; `tsc_no` dropped; `profile_pic_url` → `persons.photo_url`.

### Phase D: Records service, onboarding/migration & UsersAPI — COMPLETED ✓
- **StaffRecordsService.php** — `performanceReviews()` rewritten → `performance_reviews` + `performance_review_kpis` subquery
  `AVG(score)` (`overall_score`, derived `performance_grade`, status map pending→draft/completed→submitted); forward-compatible
  key set kept. `createPerformanceReview()`/`updatePerformanceReview()`/`deletePerformanceReview()` → live manual-id
  `performance_reviews`. `promotions()`/`createPromotion()`/`decidePromotion()` now delegate to
  `new StaffAppointmentsService($this->db)` (`listInternal`/`submitInternal`/`reviewInternal` on `audit_logs` + JSON details);
  all legacy `staff_promotions` refs gone. `staff_offboarding` confirmed fully live-compatible (kept).
- **StaffMigrationService.php** — rewritten persons-first: manual ids via new `nextId()` helper for
  `persons`/`users`/`staff`/`staff_department_assignments`/`emergency_contacts`; `onboardingForUser()` rebuilt on
  `users→persons→staff` (+ dept/payroll/attendance-profile joins), all `staff_onboarding_progress`/`staff_communication_profiles`
  reads/writes removed; `completeProfile()` updates `persons` + delete-then-insert `emergency_contacts`;
  `users.password_hash` + `force_password_change=1`; `user_roles`, `user_invitations`, `outbound_messages` seeded.
  Full create-graph INSERT sequence verified in ROLLBACK transaction against live DB.
- **UsersAPI.php** — `addToStaffTable()` supplies manual id for `staff_department_assignments` INSERT.
- **StaffAPI.php** (cluster D parts) — `getTeacherPerformanceSnapshot()` → live `performance_reviews` + KPI-avg `overall_score`;
  `getDepartmentAssignments()` → `staff_department_assignments` + `departments.head_id`; `markAttendance()` → `check_in`/`check_out`
  columns + **manual id** on `staff_attendance` upsert; `updateStaffDepartment()` → manual id on
  `staff_department_assignments` INSERT (close-old-then-append).

### Schema facts learned during migration (id generation)
In the LIVE DB the following are **manual-id** (`SELECT COALESCE(MAX(id),0)+1`), not auto-increment:
`persons`, `users`, `staff`, `staff_department_assignments`, `staff_attendance`, `emergency_contacts`,
`performance_reviews`, `payroll_runs`, `academic_year_class_learning_area_teachers`,
`academic_year_class_learning_areas`, `academic_year_class_streams`, `fee_catalog`,
`academic_year_fee_schedules`, `student_fee_obligations`, `payments`, `academic_years`.
Auto-id: `payslips`, `payslip_items`, `staff_payroll_profiles`, `staff_employment_profiles`,
`staff_attendance_profiles`, `staff_import_batches`, `staff_import_rows`, `user_invitations`, `user_roles`,
`audit_logs`, `staff_offboarding`, `staff_appointments`, `staff_leaves`, `leave_types`,
`performance_review_kpis`, `staff_qualifications`, `staff_experience`, `staff_contracts`, `departments`,
`staff_shifts`, `outbound_messages`.

### Verification (Phases C–D)
- `php -l` passes on all touched files
- Zero legacy identifiers remain in the staff module/services SQL
  (`staff_performance_reviews`, `staff_promotions`, `staff_departments`, `staff_onboarding_progress`,
  `staff_communication_profiles`, `staff_payroll` bare, `class_streams`/`academic_terms` inside staff module)
- All 176 PHPUnit tests pass (1 skipped)
- Live-DB verified: create-graph INSERT sequence (ROLLBACK), onboarding SELECT shape, staff_attendance upsert
  with manual id, performance_reviews CRUD, batches/detail EXPLAIN plans
- **Known remaining (out of scope this phase — controllers + non-migrated modules still touch legacy tables):**
  `staff_class_assignments` in `ParentPortalController.php:699` and `AttendanceController.php:2386`;
  bare `staff_payroll` in `StaffReportManager.php:100` and `SystemController.php:569`;
  `class_streams`/`academic_terms` in attendance/admission/counseling/schedules/activities/reports modules +
  multiple controllers (separate migration phases)

## Finance Module — COMPLETED ✓

All 7 finance files migrated from legacy schema to live 3NF/4NF schema:

- **FeeManager.php** (~2990 lines) — `fee_catalog` (manual-id via `nextId()` helper), `academic_year_fee_schedules` (manual-id), `student_fee_obligations`, `payments`, `persons` (student names), `vw_student_fee_balances`. Bundle workflow (`submitFeeStructureBundle`/`reviewFeeStructureBundle`/`approveFeeStructureBundle`/`getFeeStructureBundles`) re-implemented on `academic_year_fee_schedules` rows with state-machine encoded via `status`/`approved_by`/`approved_at` (replacing absent `fee_structure_approvals`).
- **PaymentManager.php** — `payment_transactions` → `payments`, `payment_allocations_detailed` collapsed to `payments.notes`, `fee_structures_detailed` → `academic_year_fee_schedules`, `academic_terms` → `terms` via date-range ay/ayt, `sql_coalesce_existing_columns` helper removed. `payments.reference COLLATE utf8mb4_general_ci` for live collation match with `school_transactions.reference`.
- **PayrollWorkflow.php** — `staff_payroll` → `payslips` + `payroll_runs`. Child-fee deductions → `payslips.child_fees_deduction` + `child_fees_breakdown` JSON. Staff names via `persons` join.
- **FinancialPeriodAPI.php** — `student_fee_balances`/`fee_structures` → `student_fee_obligations` + `academic_year_fee_schedules` + `fee_catalog` with approved active discounts subtracted via pre-aggregated subquery.
- **ReportingManager.php** — `payment_transactions` → `payments`, `class_streams` → enrollment chain (`sae`→`aycs`→`ayc`→`classes`), `academic_terms` → ayt, `fee_structures_detailed` → `academic_year_fee_schedules`. Student names via `persons`. Parent contact via `parents` + `persons` (parents has `person_id`, name/phone/email come from `persons`).
- **FinanceAPI.php** — `staff_payroll` → `payslips` + `payroll_runs` (status mapping: pending→draft, approved→approved, paid→paid+payment_status='paid'), `staff_child_fee_deductions` → `payslips.child_fees_breakdown` JSON, `financial_transactions` → `school_transactions` (legacy fields encoded into `details` JSON: type/payment_method/notes/processed_by). `sp_process_student_payment` called with verified 9-param signature.
- **TransportBillingManager.php** — `transport_subscriptions` (table does not exist) → `student_transport_assignments` (status: active/withdrawn). `payment_transactions` → `transport_bill_payments`. Student names via `persons`. `amount_paid`/`balance` on bills derived via LEFT JOIN aggregate over `transport_bill_payments`.

### Verification (Finance)
- All 7 files: `php -l` passes
- Zero legacy table references in FROM/JOIN/UPDATE/INTO clauses (`staff_payroll`, `staff_child_fee_deductions`, `payment_transactions`, `financial_transactions`, `fee_structures_detailed`, `fee_invoices`, `class_streams`, `academic_terms`, `fee_structures`, `payment_allocations_detailed`, `transport_subscriptions`)
- All 176 PHPUnit tests pass (1 skipped)
- Bundle workflow end-to-end tested via mysql CLI (submit→list→review→approve with ROLLBACK cleanup)

## Auth/Users Module — COMPLETED ✓

- **UsersAPI.php** — identity JOIN pattern (`users→persons`), `staff_department_assignments` manual id on
  staff-create, persons-based email uniqueness. Remaining `users.first_name`-style reads already migrated in cluster D pass.

## Upcoming Modules
- Students module (17 files) — `api/modules/students/*.php` (delegated to another engineer; **needs verification**
  against the corrected manual-id list: `students`, `student_academic_enrollments`, `academic_year_class_streams`
  inserts must supply explicit ids)
- Controller sweep — `api/controllers/*` still reference legacy tables
  (`staff_class_assignments`, bare `staff_payroll`, `class_streams`, `academic_terms`); flagged in Phase D verification
- Non-staff legacy modules — activities, schedules, admission, counseling, reports, Import (separate phases)

## Attendance Module — COMPLETED ✓

7 files, 1,351 lines migrated from legacy schema to live 3NF/4NF schema:

- **AttendanceStudentService.php** — `student_attendance.student_id` → `student_academic_enrollment_id` (live key);
  `students.first_name/last_name` → `JOIN persons p ON p.id = s.person_id`;
  `students.stream_id` → `student_academic_enrollments sae JOIN academic_year_class_streams aycs` (enrollment chain);
  `student_attendance.class_id/term_id/academic_year_id` columns removed (not present on live table); INSERT rewritten to
  `INSERT INTO student_attendance (student_academic_enrollment_id, date, register_type, session_id, status, absence_reason, marked_by)`.
  `getStudentByClass` enrollment-chain alias `aycs.id` for stream filter. `getStudentHistoryByYear` repointed:
  `academic_terms`→`academic_year_terms`+`terms`, `sa.class_id`→`ayc.class_id` via `c` master.
- **AttendancePermissionService.php** — `class_streams cs ON cs.id = s.stream_id`→`student_academic_enrollments sae JOIN academic_year_class_streams aycs`→`streams stm, academic_year_classes ayc, classes c`.
  `students.first_name/last_name`→`persons p ON p.id = s.person_id`.
  `staff.first_name/last_name`/`users.first_name/last_name`→`persons approver_p` via chain `approver_user→staff→persons`.
- **StudentAttendanceManager.php** — full rewrite: all 7 methods repointed to `student_academic_enrollment_id` table key.
  `create()` now does `INSERT..SELECT sae.id` resolving enrollment_id from student_id+stream. All reads (`getHistory`,
  `getSummary`, `getClassAttendance`, `getPercentage`, `getChronicAbsentees`, `read`) JOIN through
  `student_academic_enrollments sae`. Student names via `persons p ON p.id=s.person_id`. Deprecated
  `sp_bulk_mark_student_attendance` call removed. `getClassAttendance/getChronicAbsentees` use `sae.academic_year_class_stream_id` context.
- **StaffAttendanceManager.php** — identity split complete: `staff.first_name/last_name`→`persons` JOIN in `getStaffHistory`,
  `getDepartmentAttendance`, `getChronicAbsentees`. `s.department_id`→`staff_department_assignments` in department
  and chronic-absentee queries.
- **AttendanceStaffService.php** — `staff.work_start_time/late_threshold_minutes`→`staff_employment_profiles`.
  `staff_attendance.shift/check_in_time/check_out_time/expected_check_in`→`check_in/check_out` (live columns).
  `staff_attendance.shift` filter removed (column not in live schema). `s.department_id`→`staff_department_assignments` in `getStaffReport`.
  `school_calendar`→`academic_year_calendar_days` + `calendar_day_types`.
  `vw_staff_daily_register` queries preserved unchanged (shipped view, column names match).
- **AttendanceAPI.php** — `create(student)` repointed: `INSERT INTO student_attendance (student_academic_enrollment_id, date, status, marked_by)` with `student_academic_enrollment_id` resolved via `student_academic_enrollments sae`→`sae.id` subquery.
  `update()/delete()` unchanged (use live `id` column).
- **AttendanceWorkflow.php** — deprecated `sp_bulk_mark_student_attendance` call replaced with direct INSERT loop
  (proc body was a dead `SELECT 'Use direct INSERT'` stub).

### Verification
- All 7 files: `php -l` passes
- Zero legacy identifiers in SQL clauses (`class_streams`, `staff_class_assignments`, `academic_terms` bare, `student_attendance.student_id`, `students.first_name`, `students.last_name`, `staff.first_name`, `staff.last_name`, `staff.department_id`, `school_calendar`). Remaining `vw_staff_daily_register` column references are shipped view columns (valid).
- All 176 PHPUnit tests pass (1 skipped)

## NOTE
The single source of truth for all migration/refactor progress is the repo-root **`progress.md`** (master tracker: architecture rules, completed / pending / next tasks, decisions). Update that file, not this one.

## Activities Module — COMPLETED ✓

8 files (3,127 lines), 2 workflow files migrated:

- **ParticipantsManager.php** (584 lines) — `class_streams cs ON s.stream_id = cs.id` → enrollment chain (`student_academic_enrollments` → `academic_year_class_streams` → `streams`/`academic_year_classes`/`classes`). `students.first_name/last_name` → `persons p ON p.id = s.person_id`. 7 SQL blocks repointed.
- **ActivityRegistrationWorkflow.php** — `students.first_name/last_name` → `persons` JOIN (student name lookup for workflow data_json).
- **ActivitiesManager.php**, **CategoriesManager.php**, **SchedulesManager.php**, **ResourcesManager.php**, **ActivitiesAPI.php** — all use shipped tables only. Clean.
- **Workflows** (PlanningWorkflow, CompetitionWorkflow, PerformanceEvaluationWorkflow) — all use shipped tables. Clean.

### Verification
- All 16 files (activities + workflows): `php -l` passes
- Zero legacy table identifiers (`class_streams`, `students.first_name`, etc.)
- 176/176 PHPUnit tests pass

## Admission Module — COMPLETED ✅

4 files, 2,086 lines migrated.

### StudentAdmissionWorkflow.php — 3NF/4NF refactored

- **`createProvisionalStudent`** — legacy raw `INSERT INTO students (first_name, last_name, stream_id, ...)` replaced with `CALL sp_register_applicant_as_student` (live proc: creates `persons` + `students` with `person_id`, links `student_parents`, marks application `enrolled`). No more `class_streams`, `students.first_name/last_name`, or `stream_id` in code.
- **`completeEnrollment`** — legacy `UPDATE students SET stream_id = ...` + `CALL sp_complete_student_enrollment(class_id, stream_id)` replaced with `CALL sp_place_application_into_class` (resolves `academic_year_class_stream_id` → creates enrollment → calls `sp_onboard_student_enrollment` which seeds learning areas + generates fee obligations + auto-assigns dormitory for boarders). Redundant `linkParentToStudent` and `UPDATE admission_applications` removed (proc handles both). `getCurrentAcademicYearId` preserved for `academic_year_class_streams` resolution.
- **`calculatePlacementFees`** — `fee_structures_detailed` → `academic_year_fee_schedules` via `academic_year_classes.class_id` + `classes.level_id` chain. `resolveToinsuranceContext` → `academic_year_terms` + `terms` + `academic_years.year_code`.
- **`resolveAcademicTermId`** — `academic_terms` → `academic_year_terms ayt JOIN academic_years ay ON ay.id = ayt.academic_year_id JOIN terms t ON t.id = ayt.term_id`.
- **`confirmEnrollment`** — `INSERT INTO admission_enrollment_confirmations` (table Eben) → `INSERT INTO system_events`.
- **New helpers** — `findExistingParentByNationalId()` (JOIN persons+prents via `national_id_no`), `getParentChildren()` (JOIN through `student_parents` → enrollment chain).
- **DDL** — `admission_payments` table created (was missing in live schema — used by `AdmissionPaymentService`).

### AdmissionPolicy.php
- `getRequiredDocuments()` — added `$isExistingParent` param. When true, skips `parent_id` document requirement. Previous-school documents (progress_report, leaving_certificate) now marked `mandatory: false` for all grades (currently appear for documents_verified stage).

### Other admission files
- **AdmissionPaymentService.php** — uses `admission_payments` table (now exists). `postApplicationPaymentsToStudent` calls `sp_process_student_payment` → confirms to `payments` table. Clean.
- **AdmissionPolicy.php** — pure logic. Clean.
- **AdmissionStageAuthorization.php** — workflow stages only. Clean.

### Verification
- All admission + activities + workflows files: `php -l` passes
- Zero legacy table identifiers (`class_streams`, `fee_structures_detailed`, `sp_complete_student_enrollment`, `admission_enrollment_confirmations`, `students.stream_id`)
- 176/176 PHPUnit tests pass
- `admission_payments` table created in live DB

## Finance/Billing Gap Fixes — COMPLETED ✅

10 critical billing system gaps addressed. Changes split between MySQL procs + PHP module fixes.

### New Stored Procedures (gap fixes)

- **`sp_propagate_fee_schedule_changes(new_schedule_id, operator_id, OUT updated, OUT credits)`** — gap #2
  When admin changes a fee schedule amount (`createAnnualFeeStructure` archives old → inserts new), this proc propagates: updates all pending/partial `student_fee_obligations.amount_due`, creates `fee_credit_notes` for reductions. Logs to `system_events`.
- **`sp_apply_available_credits(student_id, year_id, OUT applied_count, OUT total)`** — gap #4
  Applies all `available` `fee_credit_notes` against pending/partial obligations. Updates `applied_amount`, marks `fully_applied`/`partially_applied`.
- **`sp_transition_to_new_term(from_ayt_id, to_ayt_id, operator, OUT carried, OUT new_obl)`** — gap #7 (was absent from live DB)
  Carries over unpaid balances from old term to `fee_credit_notes`, regenerates obligations with latest active schedule prices. Locks old term records. Logs to `system_events` as `term_transition_completed`.

### Modified Procs
- **`sp_process_student_sponsorship`** — gap #5 — discount_type now maps to `government_bursary`/`ngo`/`corporate` based on `p_sponsor_type` param (ENUM values added to `fee_discounts_waivers`).

### Trigger Fixes
- **`trg_update_obligation_on_payment`** — gap #3 — was using `payment_date BETWEEN term_opening AND term_closing` across all 3 terms (causing triple-application for a single Term 1 payment). Now resolves to ONE current term via `NEW.payment_date BETWEEN ayt.opening/close` with fallback to `ayt.status='current'`. Payments now allocate to the correct term only.

### New Table
- **`academic_year_fee_schedule_approvals`** — gap #10 — captures full audit trail: `(schedule_id, approval_stage ENUM, action_by, action_at, notes, old_amount, new_amount)`. Every draft→review→approve→activate creates a new row. Prior `approved_by` column on `academic_year_fee_schedules` is no longer overwritten (legacy columns left in place for backward compatibility).

### PHP: `FeeManager.php` Key Changes
- **createAnnualFeeStructure()** — gap #1, #9. Now resolves `academic_year_class_id` for ALL classes in a level (or supplied `class_ids`). Creates one schedule row per **class × term × fee_type × student_type**. Rather than overwriting old schedule rows, archives old ones (status='archived') and inserts new ones. First INSERT logs to `academic_year_fee_schedule_approvals` with `drafted` stage.
- **reviewFeeStructureBundle()** — gap #10. Replaces `UPDATE...SET approved_by=?, approved_at=NOW()` overwrite with `INSERT INTO academic_year_fee_schedule_approvals (..., 'reviewed', ...)`. Schedule status updated but approver audit preserved separately.
- **approveFeeStructureBundle()** — gap #10. Same pattern: INSERT audit row with `approved` stage + `notes`, no overwrite of `approved_by` column.

### Fees Architecture Now vs Before

| Aspect | Before | After |
|---|---|---|
| Schedule→class binding | `academic_year_class_id = NULL` → orphaned schedules | Resolved for every class in level |
| Amount change propagation | Nothing changed — student caused STALE obligations | Archived old schedule → insert new → `sp_propagate_fee_schedule_changes` |
| Approval history | One `approved_by` column overwritten each round | Separate `academic_year_fee_schedule_approvals` append-only table |
| Payment allocation | Paid to all 3 terms simultaneously | Paid to single current term |
| Credit notes application | Free-floating credits never applied | `sp_apply_available_credits` auto-allocates |
| Sponsorship | Always bulk `need_based` | Per-type `government_bursary`/`ngo`/`corporate`/`merit` |
| Term transition | absent | Full proc + credit notes + re-generation of obligations |

### Verification
- 176/176 PHPUnit tests pass (1 skipped — unchanged)
- All PHP files: `php -l` passes
- Zero legacy identifiers across finance, attendance, admission, activities modules
- 3 new procs, 1 new audit table, 1 trigger rewritten, 1 master view

## Fee Structure Bundle Design (Grade-Range Approach) — COMPLETED ✅

### How many structures per year?
The accountant creates **one bundle per grade range per year**. A typical year may have 2-3 bundles:
- Early Years: Playgroup → PP2 (ECD)
- Primary: Grade 1 → Grade 9 (Lower + Upper Primary + JSS)
- (Optionally) a separate bundle for JSS if charges differ

Each bundle covers ALL fee items × ALL terms × ALL student types for that grade range.

### Input JSON Format (from Frontend UI)
```json
{
  "academic_year": 2026,
  "grade_range": {"from_id": 4, "to_id": 12},
  "student_type_ids": [1, 2, 3],
  "items": {
    "TUITION": {
      "term1": {"1": 15000, "2": 18000, "3": 18000},
      "term2": {"1": 15000, "2": 18000, "3": 18000},
      "term3": {"1": 15000, "2": 18000, "3": 18000}
    },
    "BOARDING": {
      "term1": {"2": 8000, "3": 8000},
      "term2": {"2": 8000, "3": 8000},
      "term3": {"2": 8000, "3": 8000}
    }
  },
  "created_by": 5,
  "notes": "Fee structure for 2026/2027"
}
```
- Transport is EXCLUDED (handled separately by Director)
- BOOTING only has entries for boarder student types (2, 3) — UI auto- filters

### Backend (FeeManager::createFeeStructureBundle)
1. Resolves `academic_year_id` and term map (term1/term2/term3 → `academic_year_term_id`)
2. Resolves grade range from_id→to_id → all `academic_year_classes` rows for those classes
3. For each fee_type_code × term × student_type × class: archives previous active rows, inserts new row, logs `academic_year_fee_schedule_approvals` with `drafted` stage
4. Returns: `{total_rows_created, total_rows_archived, class_count, grade_range}`

### Master View (vw_fee_structure_grid)
- Wide pivot: fee_item | term1_CODE1 | term2_CODE1 | term3_CODE1 | term1_CODE2 | etc.
- Columns auto-expand per student_type_code
- Filterable by academic_year + grade range
- One view gives the accountant the full grid for review/edit

### Workflow
1. Accountant (or admin with override) opens fee structure manage → selects grade range, student types, enters items via the UI
2. JSON submitted to `createFeeStructureBundle` → rows created, previous rows archived
3. Accountant reviews → clicks "Submit for Review" → `reviewFeeStructureBundle` (approve → move to `reviewed` + audit log)
4. Director/Admin approves → `approveFeeStructureBundle` → `approved` + audit log → generates student a set of obligations
5. Any amount changes after initial creation:
   - Accountant calls `createFeeStructureBundle` again (new rows insert, old archived)
   - `sp_propagate_fee_schedule_changes` is called to propagate new amounts to active obligations


## Architecture Rule (enforced)

- **Controllers expose endpoints only.** No `$this->db->query()`, `$db->prepare()`, `->fetch*()`, raw SQL, or business decisions inside `api/controllers/*`. Controllers validate auth/RBAC, read request input, delegate to a module/service/manager, and return the response.
- Business logic lives in `api/modules/*` (APIs, managers, workflows) and `api/services/*`.
- All frontend calls go through `window.API.*` / `callAPI()` — never raw `fetch()`.
- No legacy (retired) table/column references in any runtime code path.

## Status Overview

| Area | Status |
| --- | --- |
| 5 core module APIs (students, staff, users, auth, academic) | ✅ Migrated |
| Admission placement tests + curriculum-units frontend | ✅ Done |
| 9 analytics services | ✅ Migrated |
| Controller de-layering (move SQL out of controllers) | ✅ Done (24/27 batches; stragglers: `StudentsController`, `BoardingController`, `ActivitiesController`, `TransportController`) |
| Remaining module clusters (finance, reports, attendance, health, boarding, schedules, counseling, activities, transport, library, inventory, parent portal) | ⚠️ IN PROGRESS — counseling ✅ (016 generalization + `CounselingAPI` rewrite); schedules ✅ (cluster D: `TermHolidayManager`/`TermHolidayWorkflow` live) |
| Legacy→live mapping doc | ✅ `docs/database_audit/13_MODULE_MIGRATION_PLAN.md` |

## Architecture Counts (2026-08-07 audit)

- **49 controllers** — 43 clean (zero direct DB), **5 with business logic** (SQL in controller), 1 = BaseController. (2026-08-07 re-audit: `ActivitiesController` + `TransportController` were wrongly listed as clean — both have direct SQL.)
- **Modules:** 23 dirs in `api/modules/` (2 empty: `assignments/`, `health/`), **153 module PHP files** across 21 active modules.
- **Services:** 47 files (`api/services/` + `api/services/payments/`).
- **Middleware:** 9 files. Live DB: `KingsWayAcademy` (root creds in `config/.env`).

### Controllers WITH business logic (must be de-layered)

Ranked by direct DB-call count:

| # | Controller | Lines | Direct DB ops | Notes |
| --- | --- | --- | --- | --- |
| 1 | `AcademicController` | 6532 | 128 | heaviest; also legacy refs |
| 2 | `StudentsController` | 5364 | 90 | `class_streams` dropdowns + joins |
| 3 | `AttendanceController` | 2979→559 | 52→0 | ✅ Batch 17: `AttendanceManager` (perms/exeats/roster/boarding/staff-register via `persons` joins) |
| 4 | `ParentPortalController` | 1034→225 | 38→0 | ✅ Batch 16: `ParentPortalManager` (auth via `users`/`user_sessions`/`user_2fa_otp_sessions`, messaging via `internal_messages`/`conversation_participants`, statement downloads → `audit_logs`, person joins) |
| 5 | `SystemController` | 3788→1414 | 29→0 | ✅ Batch 14: `SystemAdminManager` (`routes_registry`, `audit_logs`, `system_background_jobs`, persons joins) |
| 6 | `AdmissionController` | 2544→571 | 29→0 | ✅ Batch 15: `AdmissionAdminManager` (`routes_registry`, `admission_*`, parents→persons joins) |
| 7 | `WebsiteController` | 480 | 27 | ✅ rewritten (Batch 13) |
| 8 | `PaymentsController` | 337 | 19 | ✅ rewritten (Batch 12) |
| 9 | `InventoryController` | 1842 | 11 | ✅ rewritten (Batch 11) |
| 10 | `SettingsController` | 231 | 9 | ✅ rewritten (Batch 7) |
| 11 | `BoardingController` | 427 | 9 | |
| 12 | `PrintController` | 541 | 6 | ✅ rewritten (Batch 8) |
| 13 | `ActivitiesController` | 1089 | 5 | ⚠️ wrongly listed clean; has SQL (stats/analytics) |
| 14 | `FinanceController` | 1874 | 5 | ✅ rewritten (Batch 6) |
| 15 | `DelegationsController` | 219 | 5 | ✅ rewritten (Batch 5) |
| 16 | `TransportController` | 637 | 4 | ⚠️ wrongly listed clean; has SQL (summary counts + mark attendance) |
| 17 | `HealthController` | 357 | 4 | ✅ rewritten (Batch 4) |
| 18 | `VendorsController` | 190 | 3 | ✅ rewritten (Batch 2) |
| 19 | `TwoFactorController` | 418 | 3 | ✅ rewritten (Batch 3) |
| 20 | `DeviceSessionController` | 145 | 3 | ✅ rewritten (Batch 3) |
| 21 | `AccountsController` | 168 | 3 | ✅ rewritten (Batch 2) |
| 22 | `ChapelController` | 72 | 2 | ✅ rewritten (Batch 1) |
| 23 | `SchedulesController` | 653 | 1 | ✅ rewritten (Batch 9) |
| 24 | `EventsController` | 62 | 1 | ✅ rewritten (Batch 1) |
| 25 | `CounselingController` | 224 | 1 | ✅ rewritten (Batch 10) |
| 26 | `AuditController` | 76 | 1 | ✅ rewritten (Batch 1) |
| 27 | `AlertsController` | 36 | 1 | ✅ rewritten (Batch 1) |

### Controllers already clean (reference pattern)

`AuthController`, `BaseController`, `CateringController`, `CommunicationsController`, `DashboardController`, `DownloadController`, `ImportController`, `LibraryController`, `MaintenanceController`, `PushController`, `ReportsController`, `SchoolConfigController`, `SessionController`, `StaffAppointmentsController`, `StaffController`, `StaffLifecycleController`, `StaffMigrationController`, `SystemAdministrationController`, `SystemConfigController`, `TelemetryController`, `UploadsController`, `UsersController`.

### P0 — Controller de-layering progress (batches)

- **Batch 1 (system)**: `AlertsController`, `AuditController`, `EventsController`, `ChapelController` → `SystemAPI::getActiveAlerts/getAuditLogs/approveTransaction/listSchoolEvents/listChapelServices`. Also `AuditController` uses `getUserId()`.
- **Batch 2 (vendors+accounts)**: `VendorsController` → `SuppliersManager` (+`deleteSupplier`,`getOutstandingLiabilities`) & `PurchaseOrdersManager` (+`createPurchaseOrder`); `AccountsController` → new `AccountsManager` + `FinanceAPI` delegates.
- **Batch 3 (auth)**: `DeviceSessionController` → new `DeviceSessionManager` (fixes duplicate-`$db` bug); `TwoFactorController` → `TwoFactorService` (+verifyUserPassword/rotateBackupCodes/getUserContact/store+getPendingSecret) & `OTPDeliveryService`; removed incompatible `getUserId(): int` override.
- **Batch 4 (health)**: `HealthController` → new `api/modules/health/HealthAPI.php` (live `student_health_visits`/`student_health_records`/`student_vaccinations` + `vw_student_health_summary` join shape); `/sick-bay/{id}/dismiss` route via `$segments[0]`.
- **Batch 5 (delegations)**: `DelegationsController` → `DelegationService` (+`getDelegation`/`listDelegations`/`findDelegation`/`updateDelegation`/`deleteDelegation`/`revokeDelegationPermissionsById`; fixed LSP via `UserPermissionManager` import).
- **Batch 6 (finance)**: `FinanceController` → `ExpenseManager::getExpenseDetailed/listExpensesWithStats`, `ReportingManager::getFinancialSummaryReport/getCurrentAcademicYearCode`, `FinanceCrudService::getStaffBasicSalary` for salary-cap check; repaired 5 pre-existing `$e`-in-`error_log` fatal blocks (postFeeCredits/putFeeCredits/postSalaryAdvances/putSalaryAdvances). **All 25-offender batches so far pass `php -l` + `rg` DB-op-clean check.**
- **Batch 7 (settings)**: `SettingsController` → `RoleManager::listRolesForSettings/getRoleForSettings/createRoleForSettings/updateRoleForSettings/deleteRoleForSettings/listPermissionsForSettings` + `SystemAPI::createDatabaseBackup` (incl. `SELECT DATABASE()`/`SELECT USER()`, mysqldump detection). Controller pure; pre-existing `getRequestData` LSP noise left untouched.
- **Batch 8 (print)**: `PrintController` → new `api/modules/students/PortfolioManager.php::getStudentPortfolioData()`; **fixed legacy `class_streams`/`students.first_name` join** to canonical `persons→students→student_academic_enrollments→academic_year_class_streams→academic_year_classes→classes/streams` (verified via EXPLAIN against live DB; `class_streams` does not exist in live schema).
- **Batch 9 (schedules)**: `SchedulesController::getWeekly` → `SchedulesAPI::getWeeklyLessonStats()` (7-day `vw_timetable_entries` count loop).
- **Batch 10 (counseling)**: `CounselingController::getStats/getSessions` → `CounselingAPI::getStats()/getRecentSessions()`; **fixed legacy `counseling_sessions`/`counseling_referrals`/`students.first_name` refs** → live `student_counseling_sessions`/`student_counseling_cases` + `persons` (EXPLAIN-verified).
- **Batch 11 (inventory)**: `InventoryController` uniform+asset sections → `InventoryAPI::recordUniformSalePayment/getUniformSalesStudentInvoice/getUniformSalesSummary/getAssets/createAsset/updateAsset/getAssetCategories/getDepreciationSchedule`; **fixed legacy `uniform_sales.total_amount/amount_paid`, `uniform_sale_payments`, `uniform_items`, `users.full_name` refs** → live `uniform_payment_records` sums + `inventory_items` + `users→persons` join (EXPLAIN-verified); upgraded `InventoryController::handleResponse` to support `formatResponse` shape.
- **Batch 12 (payments)**: `PaymentsController` (19 DB ops) → `PaymentsAPI::getRevenueSources/getFeeStats/getCollectionTrends/getUnmatchedMpesa/importMpesa/reconcileMpesa/getMpesaReconcileHistory/lookupByPhone/linkStudent`; **fixed legacy `payment_transactions` (`payment_method/amount_paid/reference_no`) refs** → live `payments` (`method/amount/reference`); stats moved off `student_fee_obligations.balance` (column removed) onto `vw_student_fee_ledger`/`payments`; reconciliation path kept `sp_process_student_payment` CALL + `school_transactions`/`payment_reconciliations`; lookup joins now use canonical `persons→parents→student_parents→students→enrollments→academic_year_class_streams→classes/streams`; **fixed collation mismatch** on `mpesa_transactions.mpesa_code` (utf8mb4_general_ci) vs `payments.reference` (utf8mb4_unicode_ci) with `COLLATE`; controller `handleResponse` now maps API error codes (404/401/403/500) to `notFound/unauthorized/forbidden/serverError` (all EXPLAIN-verified against live DB).
- **Batch 13 (website)**: `WebsiteController` (27 DB ops) → new `api/modules/website/WebsiteManager.php` (full CRUD: stats, news, events, gallery, downloads incl. managed upload + `DownloadService` normalization, jobs, settings, content, applications, job-applications, inquiries, categories, generic showcase-table engine). **Fixed legacy refs** → live: `school_events` `event_date/event_time/end_date/category` → `start_at/end_at/type`; `job_vacancies.department` → `department_id`; `admission_applications` website-form columns (`child_full_name/grade_applying/application_ref/parent_name/...`) → live pipeline columns (`applicant_name/grade_applying_for/application_no`); **dropped `admission_process_steps`** (table absent from live — removed from content sections + generic allowlist). Controller keeps permission helpers (`hasPerm`/`requirePerm`/`forbidden` JSON override) and `handleResponse` with 201→`created` mapping (all EXPLAIN-verified against live DB).
- **Batch 14 (system)**: `SystemController` (29 DB ops, 3788L) → new `api/modules/system/SystemAdminManager.php` (37 methods: schema helpers, activity audit logs, background jobs, pending approvals, account status, routes CRUD/toggle via `routes_registry`, roles CRUD/toggle + conditional-count `fetchRoleDefinitions`, permissions CRUD + usage-definition engine, role-permissions assign/remove, role-permission matrix, sidebar menus CRUD + role assignments, modules + module-enablement toggles, resource permissions, generic `fetchRows` allowlist, JSON-file system state, backups/migrations). **Fixed legacy refs** → live: `activity_logs`→`audit_logs` (+ `users→persons` join for user names), `jobs`→`system_background_jobs`, `routes`→`routes_registry`, users `email/first_name/last_name`→`persons`, `users.role_id` count dropped (column absent → `user_roles` only), `allowance_templates` count guarded (table absent), pending-approvals dropped `fee_structures_detailed`/`academic_terms`/`school_levels`/`staff_payroll` branches + fixed `class_streams`→`streams` and broken `staff.user_id`→`persons` joins. Controller keeps all `ensure*` guards + `isSystemAdmin`/`isSchoolAdmin` + `formatUptime`, delegates everything else via `handleApiResponse` (all EXPLAIN-verified against live DB; `php -l` + `rg` DB-op-clean = 0).
- **Next**: `StudentsController` (90), `AcademicController` (128).

## Modules inventory

| Module | Files | Legacy refs? |
| --- | --- | --- |
| academic | 17 | verify-only (migrated) |
| activities | 8 | ✅ work needed |
| admission | 4 | ✅ work needed |
| attendance | 7 | ✅ work needed |
| auth | 1 | ✅ migrated |
| communications | 11 | verify |
| counseling | 1 | ✅ migrated (016 generalization: student+staff) |
| dashboard | 1 | verify |
| finance | 17 | ✅ work needed |
| Import | 1 | verify |
| inventory | 14 | ✅ work needed |
| library | 1 | ✅ work needed |
| maintenance | 3 | verify |
| payments | 1 | ✅ work needed (D2) |
| reports | 12 | ✅ cluster A done (StudentReportManager/AdmissionsReportManager rewired to views + live schema; FinanceReportManager legacy refs remain for cluster C) |
| schedules | 5 | ✅ cluster D done (`TermHolidayManager`/`TermHolidayWorkflow`; orphaned `SchedulesAPI` exam/events/activity/reports/route methods rewired to live schema, dead base CRUD + scheduled-reports removed) |
| staff | 12 | ✅ migrated |
| students | 16 | ✅ migrated (verify stragglers) |
| system | 3 | verify |
| transport | 8 | ✅ work needed |
| users | 5 | ✅ migrated |

## COMPLETED

1. **Five core module APIs migrated** (legacy → live schema): `StudentsAPI` (6559L), `StaffAPI` (3296L), `UsersAPI` (1570L), `AuthAPI` (1695L), `AcademicAPI` (7513L).
   Key rewrites: `promote()/promoteSingleStudent()/promoteMultipleStudents()/bulkPromoteStudents()` → `PromotionManager`; `startTransferWorkflow()/approveTransfer()` → `TransferWorkflow`; `verifyTransferEligibility()` → `vw_student_fee_balances`; transfer/promotion history → `student_transitions`; `getStudentsByClass()/getStudentsByStream()` → `per.dob`; bulk create/update → per-row `persons`/`students`; parent ops → `persons`+`parents`+`student_parents`; `getOrCreateStreamId()` → live `streams`/`academic_year_class_streams`; `getMedicalRecords()` → `students.application_id` join; `schemes_of_work` teacher name → `staff→persons`.
2. **Admission placement tests** (`api/controllers/AdmissionController.php`): fixed 11× legacy `parents` joins in `getQueues`; added `placement_pending` queue bucket (stage `placement_offer`); added `getPlacementTests`/`postPlacementTest`/`putPlacementTest` on live `admission_placement_tests`; frontend `js/pages/placement_tests.js` + `pages/placement_tests.php` rewired (applicant dropdown, create/results payloads).
3. **Curriculum-units frontend wiring**: `js/api.js` update/delete URLs fixed (id in path); `js/pages/manage_subjects.js` `sort_order`; `js/pages/academics.js` payload → `learning_area_id`/`sort_order`/`description`/`topics[]`.
4. **9 analytics services migrated**: `ClassTeacherAnalyticsService`, `DirectorAnalyticsService`, `HeadteacherAnalyticsService`, `InternTeacherAnalyticsService`, `SchoolAdminAnalyticsService`, `SubjectTeacherAnalyticsService`, `DeputyDisciplineAnalyticsService`, `DeputyAcademicAnalyticsService`, `AcademicContextService` (`class_streams`→`academic_year_class_streams`, `students.stream_id`→`student_academic_enrollments`, `payment_transactions`→`payments`, `discipline_cases`→`discipline_incidents`, `parent_communications`→`communications`, `school_calendar`→`academic_year_calendar_days`, `staff_payroll`→`payroll_runs`, etc.).
5. **Legacy→live migration plan written**: `docs/database_audit/13_MODULE_MIGRATION_PLAN.md` (mapping table, inventory, 10 phases).

## PENDING (not started)

Ordered by priority:

1. **P0 — Controller de-layering** (architecture rule): ~~strip all SQL/business logic from the 25 offending controllers~~ ✅ DONE (24/27; stragglers `StudentsController`, `BoardingController`, `ActivitiesController`, `TransportController` remain).
2. **P1 — Students domain**: `StudentsController` (90 DB ops, 7× `stream_name FROM class_streams` dropdowns, ~18 `class_streams` joins), `StudentService`, `StudentInsightsService`, `FamilyGroupsManager` (verify), `DocumentGenerator`, `StudentIDCardGenerator`.
3. **P2 — Attendance / Health / Boarding**: `BoardingController` (9) + `AttendancePermissionService`, `StudentAttendanceManager`, `HealthController`.
4. **P3 — Academic / Schedules**: ~~`SchedulesAPI` exam_schedules full rewrite~~ ✅ DONE this session (orphaned `SchedulesAPI` exam/events/activity/reports/route methods rewired to live schema). Cluster D (terms/holidays) ✅ done. Remaining: verify `AcademicAPI` stragglers.
5. **P4 — Finance / Fees / Payments**: `PaymentsController` ✅ (Batch 12); `ParentPortalController` ✅; **D1 RESOLVED**: live DB has no `financial_transactions` — map to `payments`; **D2 RESOLVED**: no `staff_payments`/`supplier_payments`/`disbursement_transactions` — map to `payroll_runs`/`suppliers`+`payments` (write mapping doc, then rewrite `FinanceReportManager`, `PaymentsAPI` L473, `FinanceAPI`, `PayrollApprovalWorkflow`; verify `FinanceCrudService`/`FeeManager`/`PaymentManager`/`MpesaPaymentService`/`BankPaymentWebhook`).
6. **P5 — Reports & Analytics**: ~~`StudentReportManager`/`AdmissionsReportManager`~~ ✅ DONE (new `vw_assessment_results_detail` migration 019 + reuse of `vw_current_enrollments`/`vw_student_term_attendance_summary`; transitions/batches for promotion/dropout/alumni). Remaining: `FinanceReportManager` (deferred to cluster C).
7. **P6 — Counseling / Activities / Transport / Library / Inventory**: ~~`CounselingAPI`~~ ✅ (016 generalization + full live rewrite); `ParticipantsManager`, transport managers, `LibraryAPI`, `UniformSalesManager`, `ActivitiesController`/`TransportController` de-layering.
8. **P7 — System / Print / Misc**: `PrintController` (verify), `pages/student_portal.php` inline SQL, `helpers.php` docblock.
9. **P8 — Frontend field mapping**: `js/pages/alumni_management.js` (reads retired `contact_email`/`contact_phone`/`is_active_alumni`/`alumni_notes`), `js/pages/manage_subjects.js` cosmetic key.
10. **P9 — Verification & sign-off**: strict legacy-SQL grep script, PHPUnit, `php -l`, `node --check`, EXPLAIN spot-checks, `npm run test:ui`.

## NEXT TASKS (this session / next session)

- [ ] Verify cluster B end-to-end: `php -l` on `CounselingAPI`/`CounselingController`/`StudentProfileManager`/`AcademicManager`; recreate `sp_implement_discipline_action` in live DB (renamed tables + `status='open'`); smoke-test staff + student case/session creation (CHECK constraint); EXPLAIN `list`/`get`.
- [ ] Cluster A (reports): ~~`StudentReportManager`/`AdmissionsReportManager`~~ ✅ DONE this session; verify `FinanceReportManager` in cluster C.
- [ ] Cluster C (finance, last/riskiest): use D1/D2 resolution mapping, rewrite `FinanceReportManager`, `PaymentsAPI` L473, `FinanceAPI`, `PayrollApprovalWorkflow`; verify `FinanceCrudService`/`FeeManager`/`PaymentManager`/`MpesaPaymentService`/`BankPaymentWebhook`.
- [ ] Cluster E (communications): `ParentPortalMessageManager.php` + `CommunicationReportManager.php:27` → `internal_messages`.
- [ ] Cluster F (cleanup): `pages/student_portal.php` inline SQL, `helpers.php` docblock, `js/pages/alumni_management.js` + `manage_subjects.js` field maps.
- [ ] (low priority) `scripts/check_legacy_sql.sh` — strict word-boundary grep over `api/`+`js/`+`pages/` for retired objects, with allowlist for proven comments.
- [ ] Update this tracker at the end of the session.

## DECISIONS

| ID | Question | Default | Status |
| --- | --- | --- | --- |
| D1 | `financial_transactions` (FinanceReportManager) has no live replacement | Map to `payments` grouped by `method`, or drop method+callers | RESOLVED (verified via `SHOW TABLES`): table absent live → map to `payments` |
| D2 | `staff_payments` / `supplier_payments` / `disbursement_transactions` (PaymentsAPI disbursement flows) absent from live + 3NF deliverable | Map to `payroll_runs`/`suppliers`+`payments`; else add tables (design sign-off) | RESOLVED (verified via `SHOW TABLES`): all three absent live → map to `payroll_runs`/`suppliers`+`payments` |

## VERIFICATION (standard for every session)

```bash
php -l $(git diff --name-only -- '*.php')                 # lint touched PHP
node --check <touched>.js                                  # lint touched JS
vendor/bin/phpunit                                         # PHPUnit (176 tests, 1 pre-existing skip)
mysql -u root -padmin123 KingsWayAcademy -e "EXPLAIN <rewritten query>"   # spot-check SQL
scripts/check_legacy_sql.sh                                # strict grep (once created)
```

## Change log
- **2026-08-12** — **Frontend contract fixes (P1/P3 — inventory + asset purchases)**: closed payload/endpoint
  mismatches on the live inventory pages found by the Phase 3 machine baseline. `js/pages/asset_purchases.js`:
  asset list unwraps `data.assets` (was `r.data` → always empty), category dropdown loads from
  `GET /inventory/asset-categories` (was hardcoded name values), vendor dropdown from
  `GET /inventory/suppliers-list` (was `/inventory/vendors` — no such endpoint, always empty), save posts
  `category_id`/`supplier_id`/`invoice_number`/`description` (was `category` name + `vendor_id`/`invoice_no`
  which `createAsset` ignores; also `postAssets` guard 400s without `category_id`), edit + dispose payloads
  matched to `updateAsset` contract (`dispose`/`disposal_date`/`disposal_type`/`proceeds`/`reason` were
  previously all dropped). `api/modules/inventory/InventoryAPI.php`: `updateAsset` allowed-field set extended
  with `category_id/supplier_id/purchase_date/purchase_price/invoice_number` (edits silently discarded before);
  asset list now LEFT JOINs `suppliers` for `supplier_name`. `js/pages/manage_inventory.js`:
  `_submitAdjust` maps `movement_type`→`adjustment_type` (in/out→increase/decrease), `unit_price`→`unit_cost`,
  `notes`→`reason` (restock/issue was failing with "Missing required fields"). Deleted orphaned
  `js/pages/inventory.js` — nothing loads it (page uses `manage_inventory.js`; the Phase 3 matrix mapped
  `manage_inventory → inventory.js` incorrectly). Verified: `node --check` on both JS, `php -l` on the PHP,
  no references to the deleted file remain.

- **2026-08-10** — **Frontend↔Backend contract audit + revamp plan (Phase 0)**: built the api.js vs active-router audit tooling and produced `docs/database_audit/15_FRONTEND_API_CONTRACT_AUDIT.md` + `16_FRONTEND_REVAMP_PLAN.md`. Findings: 29 API modules / 1,217 apiCall() refs / 1,152 unique (verb,path); **1,042 resolve** under the ControllerRouter convention (`VERB /ctrl/resource(/id)` → `verb+ResourceCamel`), **110 do NOT resolve (404)** — broken down as 61 naming-mismatch (e.g. `/transport/route` vs backend `getTransportRoute`; `/system/policies` vs `getPermissionPolicies`), 42 no-backend-handler (activities sports teams/fixtures/standings, boarding beds/chapel/food/menus, schedules/my-lessons, students import-existing/import-template/stats, system dashboards/widgets), 6 wrong-verb, 1 missing controller (`/assessments/*`). Reverse direction: 877 of 1,642 handler-shaped backend methods have no resolving api.js reference (needs triage — many are legit webhooks/print/download/callbacks). 53 duplicate (verb,path) refs inside api.js (redundancy). Also recorded known response-contract bug (`DATA_CONTRACT_AUDIT.md`): handleApiResponse unwraps to raw data while ~40 page controllers test `res?.success`. Fixes this round: `BoardingManager` missing `use PDOStatement;` (latent fatal in `allRows`/`firstRow`); removed `dormitories.updated_at` writes (col absent live+deliverable); `TransportBillingManager` dropped `amount_paid` store (derived) + `student_transport_assignments.updated_at`; `StudentIDCardGenerator` photo write → `persons.photo_url`; `ExternalInboundManager` 4 workflow methods rewritten to `status`+`processing_notes` (dead code, no callers). Plan: Phase 1 api.js normalization (fix 110, dedupe 53, triage 877, response-contract fix), Phase 2 role→sidebar→route→page→controller→endpoint matrix (data: 20 roles / 657 sidebar_menu_items / 373 routes_registry / 291 pages / 49 controllers), Phase 3 per-page controller review (291), Phase 4 end-to-end traces, Phase 5 responsive pass. Verified: `php -l` clean on all touched; PHPUnit 176/321/1-skip green.

- **2026-08-09** — **Full-codebase legacy-SQL close-out (final gate)**: scanned all 646 runtime PHP files (`api/`+`public/`+`pages/`, 3816 SQL statements) and fixed the last 9 real legacy refs: `StaffAPI::listInternalOpportunities` `j.department`→`d.name AS department` (join `departments`); `AttendanceManager::getDormitories`/`getDormitoryStudents` `hp.first_name/last_name`→`hp_person.*` (staff→persons); `LibraryAPI` borrower names in issues+fines queries via `persons` joins (`sp`/`stp`); `AuthSessionService` 4 queries (`u.email/first_name/last_name`→`p.*` via persons joins) across session-fetch, refresh-token registry, and token-exchange locks; `SystemConfigService` `permissions.name`→`p.code as permission_name`; `BankPaymentWebhook` parent contact `p.phone_1/p.email`→`pp.phone/pp.email` via `parents.person_id`; `UsersAPI` two `persons` INSERTs dropped non-existent `created_at/updated_at`. Also fixed a pre-existing fatal discovered during smoke: `LibraryAPI::errorResponse()` was `private` overriding `BaseAPI`'s `protected` — PHP fatal broke the entire library module; removed the override (inherits parent). Verified: `php -l` clean on all 7 touched files; PHPUnit 176/321/1-skip green; rescan → 0 real issues (residual flags all documented: `rate_limit_logs` D1 fail-open, `greatest` builtin, AuditLogger `idx_*` index defs, `vw_timetable_entries` alias-shadowing false positives, allowlist/regex-protected `{$table}` interpolation). **Live smoke via Apache** (`https://localhost/Kingsway/api`, JWT for `test_sysadmin`/`test_director`): `attendance/dormitories` 200, library issues/fines/books/summary 200, `POST /api/users` 200 (person+user rows verified, then cleaned up), `staff/internal-opportunities` 200, `auth/refresh-token` 200 (JWT email now from `persons`). Dev note: seeded test users' shared bcrypt hash was replaced with a known dev password (`KingswayTest@2026`) on `test_sysadmin` + `test_director` to enable the smoke; `smoke_test_user_9x` created and removed.

- **2026-08-09** — **MigrationService migrations table (Step 10)**: created `database/migrations/000_init.sql` (first migration, per §5.11 decision (a)) with the `migrations` table DDL matching `MigrationService::ensureMigrationsTable()` exactly (`id` AUTO_INCREMENT PK, `filename` UNIQUE, `checksum` varchar(64), `applied_at` DATETIME CURRENT_TIMESTAMP, `duration_ms` int). Applied to live `KingsWayAcademy` — `SHOW CREATE TABLE` matches service expectations; all service SQL statements (`SELECT filename, checksum`, INSERT with `filename/checksum/duration_ms`, status SELECT) execute against live DB (round-trip tested then rolled back). Synced into `KingWayDatabase_3nf_4nf_implemented.sql` — extracted DDL round-trips on scratch `kwa4nf_verify` with identical table + `uq_migrations_filename` index + AUTO_INCREMENT. **Final gate PASSED**: PHPUnit 176/321/1-skip green; `php -l` clean on `MigrationService.php`; aggregate re-scan of all Step 6–10 touched files → 305 SQL statements, 0 missing tables, 0 missing columns (only `greatest` builtin candidate remains, §4.3); `migrations` now exists live so §8 D2 exclusion no longer needed.

- **2026-08-09** — **Test scripts scan-clean (Step 9)**: plan §7 item 9 (`scripts/test_c2b_callback.php`, `scripts/test_auth_idle_timeout.php`) now reference only live schema. `test_c2b_callback`: student lookup `JOIN persons` for `first_name/last_name` (students has none); payment verification `payment_transactions`→`payments` (`amount_paid→amount`, `payment_method→method`, `reference_no→reference`); fee-obligation verification rewritten from `fee_structures_detailed`→`academic_year_fee_schedules`+`fee_catalog` and `student_fee_obligations.student_id/fee_structure_detail_id/amount_paid/balance`→ join via `student_academic_enrollments` + `academic_year_fee_schedule_id`→`fee_catalog.name` (`amount_paid`/`balance` are view-derived, not columns; output now shows Due+Status). `test_auth_idle_timeout`: `auth_sessions`→`user_sessions` (age + row-count checks, idempotent). Verified: `php -l` clean on both; 11 SQL statements rescanned → **0 missing tables, 0 missing columns**; EXPLAIN of rewritten SFO/payment queries vs live DB; target-script grep (`auth_sessions|fee_structures_detailed|payment_transactions`) 0 hits (remaining hits are only in migration tooling `db_reauth_*`/`validate_schema.php`/`db_build_objects_*`, excluded per plan); PHPUnit 176/321/1-skip green.

- **2026-08-09** — **Activities module scan-clean (Step 8)**: all 5 files in the plan §7 item 8 (`ActivitiesManager`, `ParticipantsManager`, `SchedulesManager`, `workflows/ActivityPlanningWorkflow`, `workflows/ActivityRegistrationWorkflow`) now reference only live schema. `activities`: `created_by`→`started_by`, dropped absent `location` (venue lives on `activity_schedule.venue`; `manage_activities.js` already sends `venue`). `activity_participants` (no AUTO_INCREMENT on `id`, composite semantics via `student_academic_enrollment_id` + `staff_id`): rewired all `student_id` refs→`student_academic_enrollment_id` via a new `resolveEnrollmentId` helper (active-then-latest fallback) in both `ParticipantsManager` and `ActivityRegistrationWorkflow` — participant INSERT, duplicate-check and approve/reject/activate/complete status UPDATEs now `JOIN student_academic_enrollments sae ON sae.id = ap.student_academic_enrollment_id ... sae.student_id = ?`; `registered_at`→`joined_at`, dropped absent `registered_by`; both INSERTs supply manual `id` via new `nextId()` helper (MAX+1) because the live table has no AUTO_INCREMENT. `activity_resources`: dropped absent `notes`, and the workflow INSERT now supplies NOT NULL `resource_name` (workflow `name` field) alongside live `name`/`type`/`quantity`/`cost`. `activity_schedule`: dropped absent `notes`, added required NOT NULL `schedule_date` to INSERT + update allowed-fields (frontend already sends it). Verified: `php -l` clean on all 5; 78 SQL statements rescanned → **0 missing tables, 0 missing procs/funcs, 0 missing columns**; Step-8 gate grep 0 SQL hits; EXPLAIN participant join + INSERT dry-runs (activities/activity_schedule/activity_resources/activity_participants, all rolled back) vs live DB; PHPUnit 176/321/1-skip green.

- **2026-08-09** — **Students/admission module scan-clean (Step 7)**: all files in the plan §7 item 7 (`api/modules/students/StudentsAPI.php`, `api/modules/admission/StudentAdmissionWorkflow.php`) now reference only live schema. `StudentsAPI`: both `INSERT INTO persons` dropped non-existent `created_at`; `listDisciplineCases` (count + select) and `getDisciplineRecords` re-joined `discipline_incidents` through `student_academic_enrollment_id` → `student_academic_enrollments` → `students` (live has no `student_id` on incidents); discipline term-count uses `academic_year_terms.opening_date/closing_date` (was `start_date/end_date`); `resolveDisciplineCase` dropped absent `resolved_by`/`resolution_date` (status+action only, `updated_at` auto-tracks); removed dead `addStudentAddress` (no `student_addresses` table live or in deliverable — address lives on `parents.address`) and orphaned `address_line1/address_line2/city/county/postal_code` import-template columns. `StudentAdmissionWorkflow`: fee-calculation joins use `academic_year_fee_schedules.academic_year_term_id` (was `term_id`, value is `academic_year_terms.id`); `linkParentToStudent` INSERT/`ON DUPLICATE KEY UPDATE` and the primary-contact reset dropped absent `financial_responsibility`/`created_at`/`updated_at`; `resolveParentRelationship` dropped `ORDER BY ... id` (composite PK — no `id` column). Verified: `php -l` clean on both; combined 212 SQL statements rescanned → **0 missing tables, 0 missing columns**; Step-7 gate grep (`student_addresses` + all flagged `table.column` refs) 0 SQL hits repo-wide; EXPLAIN smoke-tested discipline join + fee-schedule sum vs live DB; PHPUnit 176/321/1-skip green. Pre-existing (unrelated, not fixed): `StudentAdmissionWorkflow.php:872` wrong namespace `App\Modules\Students\StudentIDCardGenerator` and `getCurrentAcademicYearId()` not defined on the class (LSP only; runtime paths already fall back to `date('Y')`).

- **2026-08-09** — **Academic module scan-clean (Step 6)**: all 3 files in the plan §7 item 6 (`api/modules/academic/AcademicAPI.php`, `AcademicManager.php`, `AcademicAssessmentWorkflow.php`) now reference only live schema. `AcademicAPI`: `getCurrentStaffId` + 2 staff-by-user lookups now `users JOIN staff` via `person_id` (no `user_id`/`email`/`first_name`/`last_name` on staff/users), `ayc.grade_level`→`classes.grade_level`, `u.email`→`persons.email`, lesson-observation staff names via `persons`; `createAssessmentRecord` INSERT rewritten to live columns (`academic_year_class_stream_id`/`learning_area_id`/`academic_year_term_id`, no `subject_id`/`class_id`/`term_id`); `saveAssessmentResults` maps `student_id`→`student_academic_enrollment_id` + `marks_obtained`/`grade`/`points`/`responder_id`; schemes-of-work rewritten to the live `scheme_templates`+`schemes_of_work` split (`get/create/generate/update/approve/reject`, new helpers `ensureSchemeTemplate`/`resolveAcademicYearClassId`/`resolveClassLearningAreaId`/`resolveCalendarWeekId`/`resolveAcademicYearClassStreamId`/`resolveStudentEnrollmentId`/`normalizeSchemeStatus`; approve sets `status='approved'`+`approved_by`, reject maps `rejected`→`draft`). `AcademicManager`: `getResources`/`postResources` on live `teaching_materials` (`learning_area_id` not `subject_id`, `academic_year_class_stream_id` not `class_id`, `academic_year_term_id` not `term_id`, +`uploaded_by`/`academic_year_id`, staff→persons names). `AcademicAssessmentWorkflow`: `planAssessment` (assessment_type from classification CA→formative / SBA·SA→summative, `status='pending_submission'`), `administerAssessment` (`administered`→`submitted`), `markAndGrade` (`marked`→`submitted`, marks via `student_academic_enrollment_id`, responder_id), `analyzeResults` (percentage from `max_marks`, grade band via `derivePerformanceLevel`). Verified: `php -l` clean on all 3; 454 SQL statements rescanned → **0 missing tables, 0 missing columns** (was 39); Step-6 legacy-column repo grep 0 SQL hits (2 doc comments only); PHPUnit 176/321/1-skip green. Gate nuance: `schemes_of_work` is now a **live** reshaped table (same name), so the literal `-w 'schemes_of_work'` final-gate grep can't be satisfied — recorded in plan §7; meaningful gate = §4.4 legacy column list (0 hits).

- **2026-08-09** — **Inventory module scan-clean (Step 5)**: all 7 files in the plan §7 item 5 (`AssetDisposalWorkflow`, `StockMovementsManager`, `PurchaseOrdersManager`, `CategoriesManager`, `InventoryItemsManager`, `TransactionsManager`, `SuppliersManager`) now reference only live schema. CategoriesManager: `category_name`→`name`/`code` (generates `code` from name; `category_name` is VIRTUAL GENERATED), `parent_category_id`→`parent_id`, added missing `deleteCategory`. SuppliersManager: `supplier_name`→`name` (virtual), dropped non-existent `city/country/payment_terms/rating`, required = `supplier_name`+`phone` (phone NOT NULL). InventoryItemsManager: `item_code`→`code`, `item_name`→`name`, `unit_of_measure`→`unit`, `quantity_on_hand`→`current_quantity` (virtual alias) for INSERT/UPDATE, removed dead `sp_add_item_to_inventory` branch. TransactionsManager: dropped `location_id/quantity_change/created_by/total_cost` — `quantity_change`→`quantity` split into `transaction_type='in'/'out'` with abs quantity, `reference_type`→valid `'adjustment'` enum, location filter/join via `inventory_items.location_id`, users join dropped. StockMovementsManager: `locations`→`inventory_locations` (join via item), `item_code`→`code`/`unit_of_measure`→`unit`, `total_cost`→`SUM(quantity*unit_cost)`, movement type mapped to `'in'/'out'` + reference enum, `quantity_on_hand`→`current_quantity`. PurchaseOrdersManager: `po_number`→`order_number`, `purchase_order_items` (absent live; no PO→requisition link) mapped to `inventory_transactions` (`reference_type='purchase'`, `reference_id=po.id`) per plan line 130, PO statuses aligned to live enum (`draft/pending/approved/ordered/received/cancelled`). AssetDisposalWorkflow: disposal lifecycle moved onto `fixed_assets` (id/name/status/current_book_value) + one `asset_disposals` row per asset (`disposal_type` from `suggested_method`, `book_value_at_disposal`, `reason`, `authorised_by`); stage data (`condition_rating/estimated_value/disposal_method/approval`) kept in workflow `data_json` only (live `asset_disposals` has no stage columns); `users.role`→`user_roles`+`roles.name`; executeDisposal persists `proceeds`/`buyer_name` and flips `fixed_assets.status`→`disposed`/`written_off`. Verified: `php -l` clean on all 7; 64 SQL statements rescanned → **0 missing tables, 0 missing procs/funcs, 0 missing columns**; Step-5 gate grep 0 SQL hits; PHPUnit 176/321/1-skip green.

- **2026-08-09** — **Workflow + finance module scan-clean (Step 4)**: all 9 files in `api/modules/finance/` + `api/services/FinanceCrudService.php` + `api/modules/inventory/InventoryAPI.php` now reference only live schema. FeeManager: 5 `academic_year_fee_schedule_approvals` INSERTs → `audit_logs` (actions `fee_structure_drafted/reviewed/rejected/approved`, details JSON; table absent live, write-only in code). FinanceCrudService: dropped `vendor_name` from `expenses`/`petty_cash_transactions` INSERTs + `updateExpense` allowed set; `getPettyCashFund` derives `current_balance` from `opening_balance + SUM(top_up) − SUM(expense)` subqueries (verified live fund 1 = 5000.00). ExpenseManager: `recordExpense` resolves `expense_category`→`expense_categories.id` + `vendor_name`→`suppliers.id`, `recorded_by`→`created_by`, status map via `mapExpenseStatus()` (pending→draft, pending_validation→pending_approval, approved_for_payment→approved); `rejectExpense`→`rejected_by/at/rejection_reason`; reads join `expense_categories`/`suppliers`/persons. BudgetManager: `createBudget`→`academic_year`/`term` (keeps `fiscal_year` API key), line items→`category_id`, amendments→live `budget_amendments` (type from delta), variance/reads via `expense_categories`, `approveBudget` accepts `('submitted','under_review')`. BudgetApprovalWorkflow: `workflow_type`→`workflow_id` + `data_json`, `created_at`→`started_at`, `workflow_stage_history.created_at`→`processed_at`, `budgets.fiscal_year/department`→`academic_year`, `SUM(amount)`→`SUM(allocated_amount)`, statuses `pending_*`→`submitted`/`under_review`. ExpenseApprovalWorkflow: same workflow cols + `budget_line_items.category`→`expense_categories` join, `available_balance` computed `allocated−spent−committed`, payment → `reference_number`/`paid_at`, expense statuses `pending_validation`/`approved_for_payment`→enum-valid. FeeApprovalWorkflow: workflow cols only (fee_catalog verified: `default_amount`, status `active/inactive`). PayrollWorkflow: constructor code `'payroll_processing'`→`'PAYROLL'`, staff query `first_name/last_name`→`persons` + `department_id`→`staff_department_assignments`, and aligned dead-code base-class interface (`startWorkflow` returns int, `getWorkflowInstance` returns array|null with `data`=decoded `data_json`, `completeWorkflow` returns bool; no live callers). InventoryAPI: `workflow_definitions.workflow_type`→`code`, `wi.initiated_by`→`wi.started_by`, `workflow_history`→`workflow_stage_history` (`instance_id`, `processed_by`, `processed_at`). Verified: `php -l` clean on all 9; 264 SQL statements scanned via `/tmp/opencode/kingsway_scan/scan_legacy_sql.py` → **0 missing tables/procs/funcs/columns** (only known `$table` dynamic in FeeManager `nextId()`, literal callers `fee_catalog`/`academic_year_fee_schedules`/`student_fee_obligations` all exist live); EXPLAIN spot-checks on expense join, workflow instance, payroll staff query.
- **2026-08-09** — **Analytics services scan-clean (Step 3 done)**: fixed `SubjectTeacherAnalyticsService` (class/section stats, assessments due/graded, exam stats, pending assessments, exam schedule, performance chart + trends all via canonical `ayclat→aycla→ayc→aycs` join scoped by `staff_id`, status IN ('submitted','pending_approval')), `InternTeacherAnalyticsService` (assigned classes, lesson observations, teaching resources→`teaching_materials`, development progress + competencies→`core_competencies`/`learner_competencies`, keys aligned to `intern_student_teacher_dashboard.js`), `TeacherAnalyticsService` (getMyClass/getMyAttendanceToday via `academic_year_class_streams.class_teacher_id`), `DirectorAnalyticsService` (gender via `persons`, payroll→`vw_staff_payroll_summary`, dept via `staff_employment_profiles`, age via `persons.dob`, classes count→`academic_year_classes.status`, performance matrix + fallback via stream routing + `assessment_results.student_academic_enrollment_id`, absent-students query, audit user name via `persons`), `SchoolAdminAnalyticsService` (`classes.status`→`academic_year_classes.status` ×3), `HeadteacherAnalyticsService` (`discipline_incidents.resolved_at`→`updated_at`; pending-admissions contact + parent name via `parents`→`persons`), `SystemAdminAnalyticsService` (login-attempt user name/email via `users`→`persons`, search filter cols). Verified: `php -l` clean on all; 141 SQL statements in all `*AnalyticsService.php` scanned via `/tmp/opencode/kingsway_scan/scan_legacy_sql.py` → **0 missing tables, 0 missing procs/funcs, 0 missing columns**. Remaining service-level flags deferred to later steps: `MigrationService` (`migrations` table absent live; `filename/checksum/duration_ms`), `SystemConfigService` (`permissions.name`→`code`/`description`), `AuthSessionService` (`users.email`→`persons`).
- **2026-08-08** — **Cluster A (reports)**: rewired `api/modules/reports/StudentReportManager.php` + `AdmissionsReportManager.php` to the live normalized schema and created DB view `vw_assessment_results_detail` (`database/migrations/019_create_report_views.sql`, applied to live, synced to deliverable stand-in + actual view). **Routine usage audit**: (1) `vw_assessment_results_detail` — new view joining `assessment_results`→`assessments`→`academic_year_terms`→`terms`/`academic_years`→class stream with `percentage` (`marks_obtained/max_marks*100`) and CBC `grade_band`; called by `getExamReports` and `getScoreDistributions` (both need the same normalized join + percentage normalization — one view, two consumers). (2) `vw_current_enrollments` — reused for `getTotalStudents` (gender via `persons`, class/stream via enrollments; the old `students.stream_id`/`class_streams` are gone). (3) `vw_student_term_attendance_summary` — reused for `getAttendanceRates` (old `student_attendance.term_id` no longer exists; view already rolls up present/absent per student-term-class). (4) **No view/proc for the transition/batch reports** — justified: `getPromotionRates` (`promotion_batches`), `getDropoutRates`/`getStudentProgressionRates`/`getAlumniStats` (`student_transitions`), `getEnrollmentTrends` (`students`) are all single-table (or simple 2-table) aggregates with no shared join; a view would add nothing. (5) `getAcademicYearReports` kept inline (`academic_years` LEFT JOIN `student_academic_enrollments`, no `academic_terms`/`student_term_enrollments` live). Fixed legacy refs: `class_streams`/`s.stream_id`, `student_promotions`, `student_transfers`, `academic_terms`, `student_term_enrollments`, `admissions_applications`→`admission_applications`, `s.gender`/`s.graduation_date`→`persons.gender`/graduation transitions, `academic_years.name`→`year_code`/`year_name`. Verified: `php -l` clean, all 12 report queries smoke-tested against live DB (roll-up + filtered variants), PHPUnit 176/321/1-skip green.
- **2026-08-08** — **Cluster D loose end (orphaned SchedulesAPI rewrite)**: rewired the orphaned `api/modules/schedules/SchedulesAPI.php` methods to live schema. Exams: `getExamSchedule`/`getExamScheduleById` now read `vw_upcoming_exam_schedules` (filters `term_id`/`academic_year_id`/`class_id`/`status`/`exam_type`); `createExamSchedule` inserts live `exam_schedules` columns via new resolver helpers `resolveClassStreamId`/`resolveAcademicYearTermId`/`resolveLearningAreaId` + `getCurrentAcademicYearId`; `updateExamSchedule` dynamic column-map (`class_id`→`academic_year_class_stream_id`, `subject_id`→`learning_area_id`, `term_id`→`academic_year_term_id`); added `bulkGenerateExamSchedule` delegating to `sp_create_exam_schedule` (signature verified). Events: `getEvents`/`createEvent`/new `updateEvent`/`deleteEvent` on live `school_events` (manual ids via `MAX(id)+1`, no AUTO_INCREMENT). Activity: `getActivitySchedule`/`createActivitySchedule` on live `activity_schedule` (no `status` column; `activities.title` not `name`). Routes: `getRouteSchedule`/`createRouteSchedule` on `route_schedules` + `transport_routes`/`transport_vehicles`/`student_transport_assignments` (driver name via `staff→persons`, `direction`/`departure_time` columns). Removed dead `getScheduledReports`/`createScheduledReport` (no `scheduled_reports` table live or in deliverable) and dead base `list/get/create/update/delete` (`schedules` table absent from both DBs — controller base handlers now 404 with guidance). Controller: added `putEventsUpdate`/`deleteEventsDelete`/`postExamBulkGenerate`; removed `getReportsGet`/`postReportsCreate`. Frontend `js/api.js`: dropped dead reports functions, rewired `updateEvent`/`deleteEvent` to `/schedules/events-update|events-delete`, added `bulkGenerateExams`, added 9 ENDPOINT_PERMISSIONS entries. Verified: `php -l` clean, `node --check js/api.js` clean, no legacy table refs, all referenced tables exist live, event create/update/soft-delete + route/exam-view SQL smoke-tested against live DB (rolled back), PHPUnit 176/321/1-skip green.
- **2026-08-08** — **Cluster D (schedules)**: rewrote `api/modules/schedules/TermHolidayManager.php` (kept only the 3 live-called read methods `getStudentSchedules`/`getStaffSchedules`/`getAdminTermOverview`, already live-schema; deleted dead legacy CRUD + `getTermClassesEvents`/`getTermDetails` — no callers) and `api/modules/schedules/TermHolidayWorkflow.php` (`defineTermDates` now resolves `terms.id` by name and upserts `academic_year_terms` with manual id [no AUTO_INCREMENT]; holidays → `academic_year_calendar_days` via week buckets in `academic_year_calendar` keyed on `(academic_year_term_id, week_number)` with `MAX(id)+1` ids and sequential weeks from the term opening date; `activateTermDates` → `academic_year_terms.status='current'`; fixed broken indentation). Discovered: `academic_year_terms`/`academic_year_calendar`/`academic_year_calendar_days` all use **manually-assigned ids** (deliverable generator omitted AUTO_INCREMENT) and have no `updated_at` column. Verified all SQL branches (INSERT/UPDATE/reuse) via rolled-back dry-runs against live DB. Confirmed `WorkflowHandler` resolves `workflow_id` int from `workflow_definitions.code` and `started_by` from `BaseAPI::user_id`. Noted orphaned legacy SQL: `SchedulesAPI::getExamSchedule`/`createExamSchedule` join dead `curriculum_units`/`es.subject_id`/`es.term_id` — no frontend routes hit `/schedules/exam-schedule` (UI uses `/academic/exam-schedule`), defer to cleanup.
- **2026-08-08** — **Counseling schema generalization (migration 016) + API rewrite**: user requirement that staff also receive counseling (unmodeled anywhere). `database/migrations/016_generalize_counseling_schema.sql` applied to live DB: renamed `student_counseling_cases`→`counseling_cases`, `student_counseling_sessions`→`counseling_sessions`; added `counselee_type ENUM('student','staff') NOT NULL DEFAULT 'student'`, nullable `staff_id`, `idx_staff_id`, CHECK `chk_counseling_cases_counselee` (verified enforced, ERROR 4025). Synced deliverable `database/KingWayDatabase_3nf_4nf_implemented.sql` (both table DDLs, comments, TRUNCATE/relationship sections). Fixed latent proc bug in deliverable: `sp_implement_discipline_action` inserted `status='active'` (not in enum) → `'open'`; **live DB proc still needs recreation** (references renamed table). Rewrote `api/modules/counseling/CounselingAPI.php` fully live-schema (transactional case+session create, `case_code` `CC-<year>-<5-digit>`, staff+student joins, `getSummary`/`getStats`/`getRecentSessions`); extended `CounselingController::mapRequestData`; refactored renamed-table refs in `StudentProfileManager`/`AcademicManager`; updated `js/pages/counseling_records.js` (staff-aware) + `pages/counseling_records.php` labels.
- **2026-08-08** — P0 controller de-layering: **AcademicController de-layered** (6532→3255 lines, 242→0 DB ops) into `api/modules/academic/AcademicManager.php` (78 public methods, extends `BaseAPI`). Migrated to manager (via `handleResponse`): resources (`getResources`/`postResources`/`getResourceDownloadMeta`), calendar/timetable/assessments (`resolveCurrentAcademicYearId`/`getTimetableStats`/`getAssessmentsList`), formative cluster (`get/postFormativeAssessments`, `get/postFormativeAssessmentMarks`, `getFormativeSummary`), CBC tooling (`get/post/put/deleteAssessmentTools`, `getAssessmentTypes`, `getAssessmentClassifications`, `getCoreCompetenciesList`, `getCoreValuesList`, `get/postCompetencyRatings`, `get/postNationalExams`), strands (`get/post/put/deleteStrands`, `getClassStudents`), compute/report-card/growth (`postComputeTermScores` 40% formative+60% summative, `getReportCardData`, `getStudentAssessmentHistory`, `getStudentGrowthTrend`), timelines (`getStudentTimeline`, `getStaffTimeline`), transfers/rollover (`get/post/putTransferRequests`, `getYearRolloverStatus`, `postYearRollover`), deputy/teaching dashboards (`getMyTeachingToday`, `getDeputyAcademicSummary`, `getDeputyDisciplineSummary`), curriculum clusters (`get/post/put/deleteSubStrands`, `get/post/put/deleteLearningOutcomes`, `get/post/put/deleteAssessmentRubrics`, `getGradingScale`, `post/putGradingScale`, `post/put/deleteGradeRules`, `get/post/put/deleteStrandCompetencies`, `getCurriculumTree`, `getCurriculum`, `getPendingModeration`, `approveAssessmentResults`, `rejectAssessmentResult`), and portfolios (`getPortfolioAll/List/Get`, `postPortfolioCreate`, `postPortfolioArtifactAdd`, `putPortfolioArtifactUpdate`, `postPortfolioArtifactFileReplace`, `deletePortfolioArtifactDelete`). Portfolio MediaManager uploads moved into the manager (`new MediaManager($this->db)`, raw PDO ctor). Improved `handleResponse` to preserve manager error codes (401/403/404/500 → unauthorized/forbidden/notFound/serverError). Guards kept in controller (`requireAcademicWorkflowAccess(['academic_manage','curriculum_manage',...])`), `postSubStrandsBulk` unchanged (disabled-by-design). Verified `php -l`, `rg` DB-op-clean across `api/controllers/` (0), method parity 1:1 vs `/tmp/AcademicController.php.bak`, PHPUnit 176/321/1-skip.
- **2026-08-07** — P0 controller de-layering: **Batch 17 — `AttendanceController` de-layered** (2979→559 lines, 52→0 DB ops) into new `api/modules/attendance/AttendanceManager.php` (2873 lines, extends `BaseAPI`, normalised schema only). Moved to manager (via `handleApiResponse`): `getToday`/`getTodayAttendance`, `getStudentPercentage` (was inline SQL), `getClasses`/`getStudentsByClass`, `postMarkBulk`, `getSessions`/`getSessionAttendance`/`postMarkSession`, `getAcademicSummary`, `getDailyRegister`, `getDormitories`/`getDormitoryStudents`/`postMarkBoarding`/`getBoardingSummary`, `getPermissions`/`postPermissions`/`putPermissions` (approve vs edit split), `getStaffToday`/`postMarkStaff`/`getStaffRegisterContext`/`getDutyTypes`/`getStaffReport` (all `guardStaffAttendance`-gated), `getCalendar`/`getIsSchoolDay`/`getRegisterContext`/`getStudentHistoryByYear`. Fixed legacy refs → live: names via `persons`, current class via `student_academic_enrollments`→`academic_year_class_streams`→`classes`/`streams`, `attendance_sessions.type` (old `session_type` GONE), `staff_attendance.check_in/check_out` (old `check_in_time` GONE, UNIQUE `uk_staff_date`), `student_attendance` UNIQUE `uq_attendance_mark`, staff dept via `staff_employment_profiles`/`staff_department_assignments` (0 rows → NULL departments), calendar via `academic_year_calendar_days`/`calendar_day_types`/`school_week_config`, views `vw_staff_daily_register`/`vw_student_attendance_summary`. Kept existing delegations (`AttendanceAPI`, `DirectorAnalyticsService`, `AttendancePermissionService`, student/staff attendance services) + public helper proxies for services (`guardStaffAttendance`, `handleResponse`, `getAccessibleStaffScope`, `isStaffInScope`, `buildStreamScopeClause`, `_resolveTermForDate`). Verified `php -l`, `rg` DB-op-clean (0), method parity 54/54 vs backup, EXPLAIN vs live DB, PHPUnit 176/321/1-skip.
- **2026-08-07** — P0 controller de-layering: **Batch 14 — `SystemController` de-layered** (29→0 DB ops) into new `SystemAdminManager` (37 methods). Fixed legacy refs: `activity_logs`→`audit_logs`, `jobs`→`system_background_jobs`, `routes`→`routes_registry`, users names→`persons`, dropped absent-table branches in pending-approvals + role counts; controller now delegates via `handleApiResponse` keeping all `ensure*` guards. Verified `php -l`, `rg` DB-op-clean (0), EXPLAIN vs live DB, PHPUnit 176/321/1-skip.
- **2026-08-07** — P0 controller de-layering: **Batch 16 — `ParentPortalController` de-layered** (1034→225 lines, 38→0 DB ops) into new `ParentPortalManager` (18 public methods, normalised schema only). Auth: `parents`→`persons` name/email/phone + `users.password_hash`/`status`; login creates `user_sessions` (opaque 64-hex token, 7-day `login_time` window, `logout_time` on logout); OTP via `user_2fa_otp_sessions` (bcrypt-hashed code, 5-attempt cap, `otp_type='login'`) delivered by `OTPDeliveryService`, anti-enumeration success. Access guard via `student_parents` composite PK (`SELECT student_id`). Messaging: lazy `internal_conversations` titled `ParentPortal|<parent_id>|<student_id>` (+ `idx_conversation_title` added live), participants `conversation_participants` (users), read-state `message_read_status`, unread counter on participant; school recipient = class-teacher user → admin → parent self. Statement downloads → append-only `audit_logs(action='parent_statement_download', entity='student', ...)`; report card resolves class teacher via `aycs.class_teacher_id → staff → persons` (teacher-comment columns absent → nulls). Fees via `vw_student_fee_balances`/`vw_payment_transactions_with_amount`; attendance via `vw_student_term_attendance_summary`; performance via `term_subject_scores`/`learner_competencies`/`learner_values_acquisition`; M-Pesa via `MpesaPaymentService` (STK push + status query). `ParentAuthMiddleware` rewritten against `user_sessions` JOIN `users`/`persons`/`parents`. Controller keeps permission/stage guards + all 18 endpoint signatures 1:1. Verified `php -l`, `rg` DB-op-clean (0), method parity, EXPLAIN vs live DB, PHPUnit 176/321/1-skip.
- **2026-08-07** — P0 controller de-layering: **Batch 15 — `AdmissionController` de-layered** (2544→571 lines, 29→0 DB ops) into new `AdmissionAdminManager` (37 public methods: role-scoped workflow queues + `allowed_tabs` + summary, per-application detail with media-file document join, placement classes from `academic_year_class_streams`/`student_academic_enrollments`, stats, placement tests CRUD, notifications, `CALL sp_check_class_space_availability` + `CALL sp_advance_admission_workflow_stage`, stage matrix, payments). Fixed legacy refs: `routes`→`routes_registry` (user_routes/role_routes checks with expiry), `class_streams`→`academic_year_classes`/`streams`/`academic_year_class_streams`, `parents.email/phone`→`persons` joins, stage-code normalizations (`application`→`application_review`, `fee_payment`→`fees_payment`, `interview_assessment`→`interview_results`, `document_verification`→`documents_verification`, `placement_offer`→`admission_decision`), role-alias normalization incl. parent scope via `persons.email`. Controller keeps permission/stage guards + all 29 endpoint signatures 1:1. Verified `php -l`, `rg` DB-op-clean (0), EXPLAIN vs live DB, PHPUnit 176/321/1-skip.
- **2026-08-07** — P0 controller de-layering: Batches 1–13 complete (17 controllers now pure: `Alerts`, `Audit`, `Events`, `Chapel`, `Vendors`, `Accounts`, `DeviceSession`, `TwoFactor`, `Health`, `Delegations`, `Finance`, `Settings`, `Print`, `Schedules`, `Counseling`, `Inventory`, `Payments`, `Website`); logic moved to `SystemAPI`, `SuppliersManager`/`PurchaseOrdersManager`, `AccountsManager`/`FinanceAPI`, `DeviceSessionManager`, `TwoFactorService`, `HealthAPI`, `DelegationService`, `ExpenseManager`/`ReportingManager`/`FinanceCrudService`, `RoleManager`, `PortfolioManager`, `SchedulesAPI`, `CounselingAPI`, `InventoryAPI`, `PaymentsAPI`, `WebsiteManager`. Fixed 5 latent fatal `$e`-in-`error_log` blocks in `FinanceController`; fixed legacy joins/columns in portfolio, counseling, inventory/uniform, payment, and website-content flows (incl. collation mismatch on `mpesa_transactions.mpesa_code` vs `payments.reference`). Every batch verified `php -l` + `rg` DB-op-clean + EXPLAIN spot-check.
- **2026-08-07** — placement tests + curriculum-units frontend wiring; architecture audit (controller business logic, module counts); plan `13_MODULE_MIGRATION_PLAN.md` written; this tracker created.
- **Earlier** — five core module APIs migrated; analytics services migrated (see `docs/database_audit/progress.md` for the cluster detail).
