# 12 — Staff / HR Module Refactor Plan (Legacy → 3NF/4NF Schema)

**Date:** 2026-08-04 · **Status:** DRAFT (plan only — no code written yet)
**Follows:** the Academic Analytics Cluster migration (`progress.md`), same method and same verification bar.
**Grounded against:** `database/KingWayDatabase_3nf_4nf_implemented.sql` (the live implemented schema) and a full grep of the staff files. Every legacy→target claim below was confirmed table-by-table, not copied from the summary tables in file 11.

---

## 1. Why this module is different from the Academic cluster

The Academic cluster was a *rename* job — legacy tables mapped mostly 1:1 to renamed context tables. Staff is **three refactors at once**:

1. **Table re-pointing** — the legacy tables the code queries are **gone** from the live schema (verified absent: `staff_class_assignments`, `staff_onboarding`, `staff_onboarding_progress`, `staff_promotions`, `staff_performance_reviews`, `staff_lifecycle_actions`, `staff_communication_profiles`, `staff_departments`, `staff_child_fee_config`, `teacher_subjects`, `class_streams`, `subjects`, `staff_payroll`). The code is **currently broken** against the live DB, not merely stale.

2. **The persons/staff 4NF split** — new `staff` carries only `person_id` + employment columns. Identity (`first_name`, `middle_name`, `last_name`, `dob`, `gender`, `national_id_no`, `email`, `phone`, `photo_url`) lives in **`persons`**. Code that does `SELECT s.first_name FROM staff s` must JOIN `persons`. This touches nearly every read path.

3. **History/lifecycle consolidation** — parallel bespoke log/progress tables (`staff_onboarding_progress`, `staff_lifecycle_actions`, `staff_domain_audit`, `staff_appointment_approvals`) collapse into `workflow_instances` / `workflow_stage_history` / `audit_logs`. This is behavioural, not mechanical — it changes how state is written.

---

## 2. Confirmed legacy → target map (staff domain only)

Verified present/absent in `KingWayDatabase_3nf_4nf_implemented.sql` on 2026-08-04.

| Legacy table (in code) | Present in new? | Target home | Refactor type |
|---|---|---|---|
| `staff.first_name` etc. (identity) | `staff`✓ but columns moved | `persons` (JOIN on `staff.person_id`) | **JOIN split (4NF)** |
| `staff_class_assignments` (24 refs) | absent | `academic_year_class_learning_area_teachers` + `academic_year_class_learning_areas` (+ `academic_year_class_streams.class_teacher_id` for class-teacher) | SPLIT / re-point |
| `teacher_subjects` | absent | `academic_year_class_learning_area_teachers` | re-point |
| `staff_departments` | absent | `staff_department_assignments` (+ `departments`) | re-point |
| `staff_payroll` (per-staff row) | absent | **`payslips` + `payslip_items`** under a `payroll_runs` header | re-point (see §5) |
| `staff_performance_reviews` | absent | `performance_reviews` (+ `performance_review_kpis` / `kpi_*`) | re-point |
| `staff_promotions` | absent | `staff_appointments` (unified appointment/promotion history) | MERGE |
| `staff_appointment_approvals` | absent | `workflow_history` + `audit_logs` | MERGE → workflow |
| `staff_onboarding` / `staff_onboarding_progress` | absent | `workflow_instances` + `workflow_stage_history` (+ `audit_logs`) | RETIRE parallel mechanism |
| `staff_lifecycle_actions` | absent | `audit_logs` + `staff_appointments` | RETIRE → audit |
| `staff_domain_audit` | absent | `audit_logs` | MERGE → audit |
| `staff_communication_profiles` | absent | `persons` (email/phone) + **`emergency_contacts`** | SPLIT |
| `staff_child_fee_config` | absent | `fee_discounts_waivers` / `fee_catalog` policy | re-point (finance boundary) |
| `staff_child_fee_deductions` | absent | `staff_deductions` / `payslip_items` | re-point |
| `staff_payroll_adjustments` | absent | `staff_allowances` (effective line) + `audit_logs` | SPLIT |
| `allowance_templates` | absent | `staff_allowances.type_id` REF | re-point |
| `staff_requests` | absent | `workflow_instances` + `audit_logs` | re-point |
| `class_streams` (11 refs) | absent | `academic_year_class_streams` | re-point (context) |
| `subjects` | absent | `learning_areas` | re-point |
| `academic_terms` | absent | `academic_year_terms` (+ `terms` master) | SPLIT |

Targets confirmed **present**: `persons`, `staff_department_assignments`, `payroll_runs`, `payslips`, `academic_year_class_learning_area_teachers`, `academic_year_class_streams`, `learning_areas`, `performance_reviews`, `staff_appointments`, `staff_deductions`, `staff_allowances`, `emergency_contacts`, `workflow_instances`, `audit_logs`.

---

## 3. Files in scope (by legacy-reference weight)

Grep-confirmed legacy-table hit counts (FROM/JOIN/INTO/UPDATE):

**Managers / workflows** (`api/modules/staff/`)
- `StaffAssignmentManager.php` (548) — 11 hits · `staff_class_assignments`, `class_streams`, `subjects`
- `StaffAPI.php` (3044) — 10 hits · identity split + `staff_class_assignments` + leave/attendance
- `AssignmentWorkflow.php` (422) — 7 hits · `subjects`, `staff_class_assignments`
- `StaffOnboardingManager.php` (730) — 7 hits · `staff_onboarding*`
- `OnboardingWorkflow.php` (634) — 5 hits · `staff_onboarding*`
- `StaffPayrollManager.php` (1469) — 5 hits · `staff_payroll` → payslips
- `StaffPerformanceManager.php` (525) — 3 hits · `staff_performance_reviews`
- `EvaluationWorkflow.php` (473) — 2 hits
- `StaffLeaveManager.php` (411), `StaffIDCardGenerator.php` (450), `StaffService.php` (dispatcher, no SQL)

**Services** (`api/services/`)
- `StaffTeachingAssignmentService.php` (93) — 13 hits · densest per line — `staff_class_assignments`, `teacher_subjects`
- `StaffLifecycleService.php` (194) — 8 hits · `staff_lifecycle_actions`, `staff_onboarding`
- `StaffMigrationService.php` (536) — 8 hits · `staff_onboarding_progress` (⚠ see §7)
- `StaffRecordsService.php` (771) — 7 hits · identity split
- `StaffAppointmentsService.php` (600) — 6 hits · `staff_promotions` → `staff_appointments`
- `StaffDomainAccessService.php` (225), `StaffLifecycleController` (21), `StaffAppointmentsController` (152), `StaffMigrationController` (216)

**Controllers / adjacent**
- `api/controllers/StaffController.php` (1989) — surface for the above
- `api/modules/attendance/StaffAttendanceManager.php`, `AttendanceStaffService.php`
- `api/modules/reports/StaffReportManager.php`
- `api/modules/communications/StaffRequestManager.php`, `StaffForumManager.php`

---

## 4. Cross-cutting rules (apply everywhere, decide once)

These are the reusable patterns; encode them before touching files so all 20+ files change the same way.

**R1 — Identity JOIN.** Any query selecting a staff name/contact:
```sql
FROM staff s
JOIN persons p ON p.id = s.person_id
-- select p.first_name, p.last_name, p.email, p.phone  (NOT s.*)
```
Consider a private helper `staffSelectWithPerson()` or a reusable `vw_staff_directory` view so the JOIN lives in one place, not 40.

**R2 — Context, not "current".** Teaching load = `academic_year_class_learning_area_teachers` → `academic_year_class_learning_areas` → `academic_year_classes` (+ `academic_year_terms` for the term instance). Never a bare `class_streams`/`subjects` row. A mid-year teacher change is a **new row**, never an UPDATE.

**R3 — History via audit, not bespoke tables.** Lifecycle/onboarding/appointment state transitions → write to `workflow_instances` + `workflow_stage_history`, and append to `audit_logs (entity_type='staff', entity_id, action, old_values, new_values, actor_id, acted_at)`. Do not re-create `staff_lifecycle_actions` / `staff_domain_audit`.

**R4 — Payroll shape.** One `payroll_runs` header per (month, year); one `payslips` row per staff per run; line items in `payslip_items`. Deductions/allowances resolve from `staff_deductions` / `staff_allowances`, not embedded legacy columns.

**R5 — `dbQuery()` everywhere.** Replace remaining `$this->db->query()` / raw `prepare()` in touched code with the `BaseAPI::dbQuery($sql, $bindings)` helper (per project memory), so the refactor also pays down the direct-PDO debt in the same pass.

---

## 5. Payroll sub-plan (highest risk — `StaffPayrollManager.php`, 1469 lines)

The audit summary says `staff_payroll → payroll_runs`. **That is incomplete** and would drop the per-employee row. Correct target:

- `staff_payroll` (one row per staff per month) → **`payslips`** (columns already match: `basic_salary`, `gross_salary`, `paye_tax`, `nssf/nhif/housing_levy`, `net_salary`, `payment_status`, breakdown JSON columns).
- Batch/run metadata (month, year, status, approver) → **`payroll_runs`** header; add `payslips.payroll_run_id` linkage.
- Adjustments (`staff_payroll_adjustments`) → `staff_allowances` effective lines + `audit_logs`.
- Approval flow → `PayrollApprovalWorkflow` already exists (`api/services/workflows/`, already modified in working tree) → drive via `workflow_instances`.

Do this file **last within the module** and verify against the finance module's payment tables, since payroll and finance share `payslips` / `payment_status`.

---

## 6. Execution order (phased, each phase independently verifiable)

Ordered so shared helpers land first and the riskiest file is last.

1. **Phase A — Foundations & helpers.** Add R1 identity helper/view + R4 payroll helpers. No behaviour change. Verify `php -l` + tests still green.
2. **Phase B — Teaching assignments cluster.** `StaffTeachingAssignmentService.php`, `StaffAssignmentManager.php`, `AssignmentWorkflow.php`, teaching bits of `StaffAPI.php`. Re-point `staff_class_assignments`/`teacher_subjects`/`subjects`/`class_streams` → context tables (R2).
3. **Phase C — Records & directory.** `StaffRecordsService.php`, `StaffAPI.php` reads, `StaffIDCardGenerator.php`, `StaffReportManager.php` — apply R1 identity JOIN + `staff_communication_profiles` → `persons`/`emergency_contacts`.
4. **Phase D — Lifecycle / onboarding / appointments.** `StaffOnboardingManager.php`, `OnboardingWorkflow.php`, `StaffLifecycleService.php`, `StaffAppointmentsService.php` — R3 (workflow + audit), `staff_promotions` → `staff_appointments`.
5. **Phase E — Performance.** `StaffPerformanceManager.php`, `EvaluationWorkflow.php` → `performance_reviews` (+ `kpi_*`).
6. **Phase F — Departments / requests / attendance.** `staff_departments` → `staff_department_assignments`; `StaffRequestManager.php` → `workflow_instances`; attendance managers.
7. **Phase G — Payroll (§5).** `StaffPayrollManager.php`, `PayrollApprovalWorkflow.php`, `DisbursementManager.php`.
8. **Phase H — Migration services.** `StaffMigrationService.php` / `StaffMigrationController.php` — **decide first** (see §7).

---

## 7. Open decisions (need your call before Phase D/H)

1. **`StaffMigrationService.php` / `staff_management_migration.sql`** — these may be one-time legacy→new *data* migration code that legitimately reads the OLD table names. Refactoring them to new names could break the very migration that populates the new schema. **Decide: is this file live app logic, or a run-once migration to leave frozen?**
2. **`staff_child_fee_config` / `staff_child_fee_deductions`** — these straddle the finance boundary (`fee_discounts_waivers`, `payslip_items`). Refactor here, or defer to the Finance module pass (17 files, already in-flight per git status)?
3. **Identity helper form** — private method vs. a `vw_staff_directory` DB view. A view is reusable by the finance/reports modules too; a method keeps it in PHP. Recommend the **view** (one definition, already the pattern with the 84 existing `vw_*` objects).

---

## 8. Verification bar (same as the Academic cluster)

Per file and per phase:
- `php -l` passes on every touched file.
- **Zero** legacy identifiers remain in FROM/JOIN/INTO/UPDATE clauses:
  `grep -riE "(FROM|JOIN|INTO|UPDATE)\s+\`?(staff_class_assignments|staff_onboarding|staff_promotions|staff_performance_reviews|staff_payroll|staff_communication_profiles|staff_lifecycle_actions|class_streams|subjects|teacher_subjects)\`?" <files>` → empty.
- All **176 PHPUnit tests** pass (1 skipped) — add staff-specific coverage where a query shape changes materially (payroll, teaching load).
- Spot-check live queries against `KingsWayAcademy` for the identity JOIN and payroll shape before marking a phase done.
- Update `docs/database_audit/progress.md` with the per-file mapping (same format as the Academic cluster entry).

---

## 9. Effort estimate

~20 files, ~14k lines, but concentrated: 5 files hold >60% of the SQL. Phases B–F are mechanical once R1–R5 are fixed. Phase G (payroll) and Phase H (migration decision) carry the real risk. Rough sequence: A→B→C in one sitting, D→E→F next, G+H last with review.
