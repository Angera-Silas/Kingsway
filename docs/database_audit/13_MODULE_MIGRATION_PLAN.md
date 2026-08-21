# 13. Module Migration Plan — Remaining Legacy SQL in API/Pages

Status: **PLAN (not started)**
Scope: remove every reference to retired tables/columns in `api/`, `js/`, `pages/`
Owner: Dev
Related: `progress.md`, `12_STAFF_MODULE_REFACTOR_PLAN.md`

## Objective

The five core module APIs (`StudentsAPI`, `StaffAPI`, `UsersAPI`, `AuthAPI`, `AcademicAPI`) were
migrated to the normalized live schema. However, the **controller layer** and the remaining module
services/analytics still contain large amounts of SQL written against the retired schema. Those
queries fail at runtime with "table doesn't exist / column doesn't exist" errors and return generic
500s.

This plan migrates the remaining code so that **zero** references to the retired objects remain in
runtime code paths.

## Legacy → Live Mapping (authoritative)

| Retired object | Live replacement | Notes |
|---|---|---|
| `students.first_name/last_name/gender/dob` | `persons.first_name/last_name/dob/gender` | join `persons p ON p.id = s.person_id` |
| `students.stream_id` | `student_academic_enrollments → academic_year_class_streams` | current enrollment = `enrollment_status = 'active'` |
| `students.class_id` | same chain + `academic_year_classes.class_id` | |
| `class_streams` (table) | `academic_year_class_streams` | + `academic_year_classes`, `streams` |
| `class_streams.stream_name` | `streams.name` | |
| `payment_transactions` (table) | `payments` | `amount_paid`→`amount`, `payment_method`→`method`, `reference_no`→`reference`, `status='confirmed'` |
| `student_promotions` | `student_transitions` | `transition_type` in `('promoted','retained')`; year via `academic_year_id` |
| `student_transfers` | `student_transitions` | `transition_type='transferred'` |
| `alumni` (table) | `student_transitions` `transition_type='graduation'` | StudentsAPI already migrated |
| `curriculum_units` | `strands` (+ `sub_strands`, `learning_areas`) | |
| `view_student_details` | `vw_current_enrollments` and friends | no code refs remain |
| `clearance_departments` | `student_clearances` | `clearance_type` enum |
| `student_fee_balances` (table) | `vw_student_fee_balances` (view) | has `balance`, `amount_due`, `amount_paid`, `payment_status`, `days_overdue` |
| `financial_transactions` | none — see Decision D1 | |
| `staff_payments`, `supplier_payments`, `disbursement_transactions` | none — see Decision D2 | PaymentsAPI disbursement flows |

## Live schema facts (verified against `KingsWayAcademy`)

- `students`: `id, person_id, admission_no, student_type_id, assessment_number, assessment_status, nemis_number, nemis_status, status, application_id, admission_date, blood_group, created_at, updated_at`
- `persons`: `id, first_name, middle_name, last_name, dob, gender, national_id_no, photo_url, email, phone`
- `student_academic_enrollments`: `id, student_id, academic_year_id, academic_year_class_stream_id, enrolled_on, enrollment_status` (`enum('pending','active','completed','withdrawn','transferred','graduated')`, default `active`)
- `academic_year_class_streams`: `id, academic_year_class_id, stream_id, room_id, class_teacher_id, status`
- `academic_year_classes`: `id, academic_year_id, class_id, status`
- `streams`: `id, name, code, capacity`
- `payments`: `id, student_id, receipt_no, amount, payment_date, method, reference, parent_id, received_by, status, notes`
- `student_transitions`: `id, student_id, from_student_academic_enrollment_id, to_student_academic_enrollment_id, academic_year_id, transition_type, reason, decided_by, decided_at, executed_at`
- `student_clearances`: `id, student_id, transfer_request_id, clearance_type, status, checked_by, checked_at, amount_outstanding, notes`
- `exam_schedules`: `id, academic_year_class_stream_id, academic_year_term_id, learning_area_id, exam_name, ...` (there is **no** `class_id`/`subject_id`/`term_id`/`academic_year_id`)
- `vw_student_fee_balances` columns: `student_academic_enrollment_id, student_id, academic_year_id, academic_year_term_id, term_id, term_code, academic_year, amount_due, amount_waived, amount_paid, balance, payment_status, latest_due_date, days_overdue`

## Canonical query shapes to use

```sql
-- Names
SELECT p.first_name, p.last_name FROM students s JOIN persons p ON p.id = s.person_id WHERE s.id = ?;

-- Current class/stream for a student
SELECT c.name AS class_name, st.name AS stream_name
FROM students s
JOIN student_academic_enrollments sae
  ON sae.student_id = s.id AND sae.enrollment_status = 'active'
LEFT JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id
LEFT JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
LEFT JOIN classes c ON c.id = ayc.class_id
LEFT JOIN streams st ON st.id = aycs.stream_id
WHERE s.id = ?;

-- All students in a stream (streamId) or class (classId)
SELECT s.id, p.first_name, p.last_name, s.admission_no
FROM students s
JOIN persons p ON p.id = s.person_id
JOIN student_academic_enrollments sae
  ON sae.student_id = s.id AND sae.enrollment_status = 'active'
JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id
[WHERE aycs.id = :streamId]  -- or: ayc.class_id = :classId

-- Promotion/transfer history
SELECT * FROM student_transitions
WHERE student_id = ? AND transition_type IN ('promoted','retained','transferred','graduation')
ORDER BY executed_at DESC;

-- Payments
SELECT COALESCE(SUM(amount),0) FROM payments WHERE status = 'confirmed' [AND ...];
```

## Affected-file inventory (by domain cluster)

Counts = total matches of the legacy patterns (`s.stream_id`, `s.first_name`, `s.last_name`, `s.gender`,
`class_streams`, `payment_transactions`, `student_promotions`, `student_transfers`,
`student_fee_balances` table, `financial_transactions`, `curriculum_units`) in each file.

| Cluster | File | Matches | Notes |
|---|---|---|---|
| Students | `api/controllers/StudentsController.php` | 89 | 7× `SELECT id,class_id,stream_name FROM class_streams` + ~18 `LEFT JOIN class_streams` blocks |
| Attendance | `api/controllers/AttendanceController.php` | 39 | incl. `class_streams` in perms/exeats/roster queries |
| Academic | `api/modules/academic/AcademicAPI.php` | 32 | **verify only** — mostly migrated; confirm hits are comments/legit aliases |
| Students | `api/modules/students/StudentsAPI.php` | 26 | **verify only** — migrated; confirm no stragglers |
| Health | `api/controllers/HealthController.php` | 22 | sick-bay joins via `class_streams` |
| Students | `api/modules/students/FamilyGroupsManager.php` | 21 | verify — likely real `s.first_name`/`class_streams` |
| Payments | `api/controllers/PaymentsController.php` | 21 | `payment_transactions` ×7 + `class_streams` ×3 |
| Finance | `api/modules/finance/FeeManager.php` | 17 | verify/migrate `s.stream_id` |
| Counseling | `api/modules/counseling/CounselingAPI.php` | 16 | `class_streams` ×2 + `s.first_name` |
| Activities | `api/modules/activities/ParticipantsManager.php` | 16 | `class_streams` ×4 + names |
| Academic | `api/controllers/AcademicController.php` | 16 | |
| Parent portal | `api/controllers/ParentPortalController.php` | 15 | `payment_transactions` ×3 + `class_streams` ×2 |
| Students | `api/modules/students/StudentInsightsService.php` | 14 | |
| Boarding | `api/controllers/BoardingController.php` | 14 | |
| Finance | `api/services/FinanceCrudService.php` | 12 | |
| Reports | `api/modules/reports/StudentReportManager.php` | 12 | `student_promotions`/`student_transfers`/`class_streams` |
| Inventory | `api/modules/inventory/UniformSalesManager.php` | 12 | |
| Students | `api/modules/students/PromotionManager.php` | 10 | verify only (comment mentions `student_promotions`) |
| Attendance | `api/modules/attendance/StudentAttendanceManager.php` | 10 | |
| Finance | `api/modules/finance/ReportingManager.php` | 9 | mostly comments; code already on `payments` |
| Finance | `api/modules/finance/PaymentManager.php` | 9 | |
| Transport | `api/modules/transport/*` (3 files) | 18 | |
| Reports | `api/modules/reports/FinanceReportManager.php` | 8 | `payment_transactions`, `class_streams`, `student_fee_balances`, `financial_transactions` |
| Attendance | `api/modules/attendance/StaffAttendanceManager.php` | 8 | |
| Students | `api/modules/students/StudentService.php` | 7 | |
| Analytics | `api/services/*` (7 files) | 30 | mostly **mapping comments** (already migrated) — verify only |
| Students | `api/modules/students/StudentRepository.php` | 6 | verify only |
| Schedules | `api/modules/schedules/SchedulesAPI.php` | 6 | `curriculum_units` + missing `es.subject_id/class_id/term_id/academic_year_id` columns |
| Reports | `api/modules/reports/AdmissionsReportManager.php` | 6 | `class_streams`; alumni already via status |
| Finance | `api/modules/finance/FinanceAPI.php` | 6 | verify only |
| Attendance | `api/modules/attendance/AttendancePermissionService.php` | 6 | `class_streams` + `s.first_name` |
| Payments | `api/services/payments/MpesaPaymentService.php` | 5 | `student_fee_balances` table ref |
| Payments | `api/services/payments/BankPaymentWebhook.php` | 4 | |
| Payments | `api/modules/payments/PaymentsAPI.php` | 3 | line 473 `s.first_name` + `student_fee_balances`; see Decision D2 |
| System | `api/controllers/SystemController.php` | 4 | allowlist entry `class_streams` (line ~3533) + join at ~504 |
| Print | `api/controllers/PrintController.php` | 4 | `class_streams` joins |
| Misc | `api/controllers/AdmissionController.php` | 1 | line ~1391 `class_streams` join (classes dropdown) |
| Misc | `pages/student_portal.php` | 1 | inline `class_streams` join |
| Misc | `api/includes/helpers.php` | 1 | docblock only (cosmetic) |
| Frontend | `js/pages/alumni_management.js` | — | reads columns API no longer returns |
| Frontend | `js/pages/manage_subjects.js` | 1 | `payload.curriculum_units` response key (cosmetic) |

## Execution phases

### Phase 0 — Baseline & tooling
1. Add a strict grep script `scripts/check_legacy_sql.sh` with an allowlist of file:line pairs that are
   **proven** comments/legit (comments in migrated modules, docblocks). Grep patterns must be
   word-boundary (`\b`) to avoid matching live views (`vw_student_fee_balances`).
2. Baseline: run the script, record the failing list in this doc's inventory.
3. Confirm DB access works (`mysql -u root -pYOUR_DB_PASSWORD KingsWayAcademy`).

Acceptance: script exits 0 against current allowlist baseline.

### Phase 1 — Students domain (largest, highest risk)
Files: `StudentsController` (89), `StudentService` (7), `StudentInsightsService` (14),
`FamilyGroupsManager` (21), `DocumentGenerator`, `StudentIDCardGenerator`, `PromotionManager`,
`TransferWorkflow`, `StudentRepository`, `StudentScopeService` — **verify-only** on the claimed-migrated ones.
1. Rewrite the 7 `SELECT id, class_id, stream_name FROM class_streams` dropdown queries →
   `academic_year_class_streams` (+ `streams.name`, `academic_year_classes.class_id`), current-year scoped.
2. Rewrite all `LEFT JOIN class_streams cs ON cs.id = s.stream_id` + `cs.stream_name` →
   current-enrollment chain (shape above). Watch for `s.gender`, `s.dob` → `persons`.
3. Replace `s.first_name`/`s.last_name` with `persons` aliases in every remaining query.
4. Confirm `FamilyGroupsManager`/`StudentInsightsService` hits are real; fix if so.

Acceptance: `php -l` clean; spot-run the rewritten statements via `mysql`/EXPLAIN; PHPUnit green.

### Phase 2 — Attendance / Health / Boarding
Files: `AttendanceController` (39), `AttendancePermissionService` (6), `StudentAttendanceManager` (10),
`StudentAttendanceService`, `StaffAttendanceManager`, `StaffAttendanceService`, `HealthController` (22),
`BoardingController` (14).
1. `student_permissions` join chain (permission/exeat queries) → `persons` + `aycs` chain.
2. Stream→class lookups (`SELECT class_id FROM class_streams WHERE id=?`) → `academic_year_class_streams`.
3. Sick-bay + boarding roster joins → current-enrollment chain.

Acceptance: same as Phase 1.

### Phase 3 — Academic / Curriculum / Scheduling
Files: `SchedulesAPI` (6), `AcademicController` (16), and **verify-only** on `AcademicAPI` (32),
`AcademicAssessmentWorkflow`, `AcademicCohortProjectionService`, `ReportGenerationWorkflow`,
`StudentPromotionWorkflow`, `AcademicYearTransitionWorkflow`.
1. `SchedulesAPI` exam-schedule queries: full rewrite against live `exam_schedules` — join
   `academic_year_class_streams` (→ class name, stream name), `academic_year_terms` (→ term_number,
   academic_year), `learning_areas` (→ subject_name). Replaces both the `curriculum_units` join AND
   the already-broken `es.class_id/es.subject_id/es.term_id/es.academic_year_id` columns.
2. Confirm `AcademicAPI`'s 32 hits are comments only; fix any real stragglers (should be on
   `strands`/`sub_strands` already).
3. Any remaining `curriculum_units` → `strands`.

Acceptance: exam-schedule list/detail SQL executes against live schema.

### Phase 4 — Finance / Fees / Payments
Files: `PaymentsController` (21), `ParentPortalController` (15), `FinanceReportManager` (8),
`FinanceCrudService` (12), `FeeManager` (17), `PaymentManager` (9), `MpesaPaymentService` (5),
`BankPaymentWebhook` (4), `PaymentsAPI` (3), and verify-only `FinanceAPI` (6), `ReportingManager` (9),
`PaymentReconciliationAPI`, `PayrollWorkflow`, `AllowanceTemplateAPI`.
1. `payment_transactions` → `payments`: `amount_paid`→`amount`, `payment_method`→`method`,
   `reference_no`→`reference`, keep `status='confirmed'`. Where old column names are required by
   consumers, use `vw_payment_transactions_with_amount` (aliases legacy names over `payments`).
2. `student_fee_balances` (table) → `vw_student_fee_balances`; note per-student row is now
   enrollment-scoped — adjust aggregations accordingly (`balance`, `payment_status`,
   `days_overdue`, `latest_due_date`).
3. `class_streams` joins in fee/collection reports → `vw_student_fee_balances` +
   `academic_year_class_streams`/`academic_year_classes` chain.
4. **Decision D1** — `financial_transactions` (FinanceReportManager 2 queries): no replacement
   exists. Options: (a) drop the methods + callers, (b) map to `payments` grouped by `method`, or
   (c) create a `financial_transactions` table in the schema. Default: (b).
5. **Decision D2** — `disbursement_transactions`/`staff_payments`/`supplier_payments` in
   `PaymentsAPI` disbursement flows: none of the three exist in live or in the 3NF deliverable.
   Live payroll uses `payroll_runs`/`staff_payroll_profiles`/`staff_salary_advances`; suppliers use
   `suppliers`. Options: (a) map disbursement flows to `payments` + `payroll_runs`/`suppliers`,
   (b) add the three tables to the schema (requires design sign-off). Default: (a) for staff
   salary, (b) for supplier payments if no mapping exists.

Acceptance: payments/revenue/fee-trend endpoints return live data; no 500s.

### Phase 5 — Reports & Analytics
Files: `StudentReportManager` (12), `FinanceReportManager` (in Phase 4), `AdmissionsReportManager` (6),
and verify-only on `DirectorAnalyticsService`, `HeadteacherAnalyticsService`,
`ClassTeacherAnalyticsService`, `InternTeacherAnalyticsService`, `SubjectTeacherAnalyticsService`,
`SchoolAdminAnalyticsService` (most hits are mapping comments).
1. `StudentReportManager`: `student_promotions`/`student_transfers` →
   `student_transitions` grouped by `transition_type` + `academic_year_id` (no `to_academic_year`/
   `promotion_status` columns). `class_streams` → aycs chain.
2. `AdmissionsReportManager`: `class_streams` → aycs chain; keep `status='alumni'` value usage.

Acceptance: promotion/dropout/progression report endpoints return live data.

### Phase 6 — Counseling / Activities / Transport / Library / Inventory
Files: `CounselingAPI` (16), `CounselingController` (2), `ParticipantsManager` (16),
`StudentTransportAssignmentManager`, `StudentTransportStatusManager`,
`StudentTransportPaymentManager`, `DriverManager`, `LibraryAPI` (4), `UniformSalesManager` (12).
1. Mechanical replacement: names via `persons`, class/stream via aycs chain, no `class_streams`.
2. Verify each module's queries with a targeted `mysql` run before/after.

Acceptance: module endpoints return live data.

### Phase 7 — System / Print / Misc / pages
Files: `SystemController` (4), `PrintController` (4), `AdmissionController` (1), `pages/student_portal.php` (1),
`api/includes/helpers.php` (1), `config/role_sidebars.php` + `config/DashboardRouter.php` + `streams.php`
(route-name only — no change).
1. `SystemController`: replace `class_streams` in the `fetchRows` allowlist with
   `academic_year_class_streams` (or drop); fix the join at ~504.
2. `PrintController` joins → aycs chain; `AdmissionController` ~1391 classes dropdown → aycs chain.
3. `student_portal.php` inline join → aycs chain (or delegate to a view).
4. Update `helpers.php` docblock example (cosmetic).

Acceptance: system/print/portal pages render without SQL errors.

### Phase 8 — Frontend field mapping
1. `js/pages/alumni_management.js`: `getAlumni()` returns `transition_id, first_name, last_name,
   class_name, stream_name, graduation_year` — the page reads `contact_email`, `contact_phone`,
   `is_active_alumni`, `alumni_notes` (no longer returned). Either derive display values from the
   new fields or drop the columns from the table/export.
2. `js/pages/manage_subjects.js` `payload.curriculum_units` key: harmless response-key fallback —
   optionally remove.

Acceptance: alumni page renders real data; no console errors.

### Phase 9 — Verification & sign-off
1. `node --check` + `php -l` on all touched files.
2. `vendor/bin/phpunit` (176 tests, 1 pre-existing skip).
3. Strict grep: `scripts/check_legacy_sql.sh` exits 0 (allowlist only contains proven comments).
4. `mysql -e "EXPLAIN ..."` spot-checks for each rewritten query shape.
5. `npm run test:ui` smoke on affected pages (students, attendance, finance, reports, schedules).
6. Update `progress.md` and this document's Status → DONE.

## Open decisions

- **D1** `financial_transactions` replacement (default: map to `payments`).
- **D2** `staff_payments`/`supplier_payments`/`disbursement_transactions` (default: map to
  `payroll_runs`/`suppliers`+`payments`; else add tables with design sign-off).

## Verification commands

```bash
php -l $(git diff --name-only -- '*.php')
node --check <each touched js>
vendor/bin/phpunit
mysql -u root -pYOUR_DB_PASSWORD KingsWayAcademy -e "EXPLAIN <rewritten query>"
scripts/check_legacy_sql.sh   # strict grep, allowlist-only
```
