# Phase 4 — Bad Current-State Design (verified evidence)

Source of truth: `database/KingsWayDatabase_2026_08_01_1409hrs.sql` + live `KingsWayAcademy`. Generated 2026-08-01.
Every row below is a **verified fact** with a live-DB measurement; the TARGET CONTEXT is the recommendation.

## 4.1 The six structural defects

| # | CURRENT TABLE | COLUMN / Feature | CURRENT PURPOSE | PROBLEM | TARGET CONTEXT |
|---|---|---|---|---|---|
| 1 | `classes` | `academic_year`, `capacity`, `status`, `teacher_id`; unique `uk_name_year(name, academic_year)` | represent a class in a given year | year-bound data pollutes the class master; `uk_name_year` manufactures a **new class row every year** (verified: `Grade 1` exists for 2026 **and** 2027, ids 6 and 14); `AcademicYearTransitionWorkflow::setupNewYear` inserts classes without `name` (verified code) | `classes` = pure master (name, level_id, grade_level, unique normalized name); year/teacher/capacity/status move to `academic_year_classes` (already exists as `class_year_assignments`) |
| 2 | `students` | `stream_id` | hold "current stream" | only ONE stream can ever be stored; a student who moved Grade 4A→Grade 5B in 2027 loses 2026 context; 61/61 active students have `stream_id` set (verified) | strip `stream_id`; stream membership lives in `class_enrollments` (already has `stream_id`) |
| 3 | `class_streams` | `class_id` | stream under a class | stream is bound to `classes`, which is itself year-polluted; `trg_auto_create_default_stream` auto-creates a stream row named after the class (verified trigger) | stream becomes `academic_year_class_streams` keyed by `academic_year_class_id` |
| 4 | `assessments` | `subject_id` NOT NULL, no `academic_year_id` | reference legacy subject | `subject_id` is **100% orphaned** — 3/3 rows reference `curriculum_units` rows that no longer exist (table has 0 rows, verified); and the assessment cannot be attributed to a year | drop `subject_id`; keep `learning_area_id`; add `academic_year_id` (context via class/term already present) |
| 5 | `student_discipline` | (none) | record incidents | has **no** year/term/class/stream — an incident recorded in 2027 cannot be told apart from 2026 | add `academic_year_id`, `term_id`, `class_id`/`stream_id` (or `enrollment_id`) |
| 6 | `vw_active_students_per_class`, `vw_all_school_payments`, `vw_financial_period_summary`, `vw_inventory_low_stock`, `vw_lesson_plan_summary`, `vw_outstanding_fees`, `vw_upcoming_activities`, `vw_user_recent_communications` | real tables, prefix `vw_` | reporting snapshots | misnamed as views; misleading for developers; `class_assignments` is conversely a **real VIEW** while code may treat it as a table | rename with a summary prefix or convert to genuine `CREATE VIEW` |

## 4.2 Time-bomb patterns that will silently destroy/obscure history

| # | Pattern | Evidence | Consequence |
|---|---|---|---|
| 1 | **`year(curdate())` in views** | 23 of 84 views embed `curdate()` / `year(curdate())` (e.g. `vw_arrears_summary`, `vw_outstanding_fees`) | When the server clock crosses into 2027, these views silently report 2027 data only — 2026 records "disappear" from reports; no explicit year parameter |
| 2 | **Single current-value columns** | `students.stream_id`; `classes.academic_year`; `class_streams.current_students`; `staff_employment_profiles.status` (single active) | only one value storable; overwrite destroys history (see 4.1) |
| 3 | **Unattributable transactions** | `student_discipline` (no year); `assessment_results` only inherits context via `assessment` (ok) but `assessments` itself lacks year | data recorded now cannot be replayed for a past/future academic year |
| 4 | **Parallel promotion mechanisms** | `student_promotions` (0 rows), `class_promotion_queue` (0 rows), `promotion_batches` (0 rows) coexist with `class_enrollments.promoted_to_class_id`/`promotion_status` | two code paths → divergent history; only one has data |
| 5 | **Duplicate/backup tables live in schema** | `dropped__bak_permissions`, `dropped__bak_role_permissions`, `dropped__bak_role_sidebar_menus`, `dropped__bak_routes`, `tmp_backup_role_dashboards`, `student_fee_obligations_backup_20260112` (6 verified) | schema confusion; risk that app queries the backup instead of canonical |
| 6 | **Legacy curriculum tables kept but empty** | `curriculum_units` (0 rows), `unit_topics` (0 rows) | FK/`NOT NULL` columns still reference them (`assessments.subject_id`) producing orphans |
| 7 | **Generic finance tables overlap** | `financial_transactions`, `school_transactions`, `bank_transactions`, `mpesa_transactions`, `payment_transactions`, `fee_invoices`, `student_fee_obligations` | several partial ledgers; a single payment can appear in multiple tables with no cross-link except `reference_no`/`receipt_no` strings |

## 4.3 Verdict on the CLASSES defect (the original trigger)

Current: 13 `classes` rows; `Grade 1` duplicated (id 6 = 2026, id 14 = 2027); `class_streams` for id 14 = stream 17; `class_year_assignments` holds the real year-class context (12 rows); `academic_years` has 6 rows (2026 active/current, 2027 active, plus junk planning rows 2031/2032/2033 and a `2026/2027` string row).

Target model (verified supported by existing tables):

```
classes  (MASTER: id, name, level_id, grade_level, unique normalized_name)
   ▲
   │  class_year_assignments.id  (academic_year_id, class_id, stream_id, teacher_id,
   │                              room_number, capacity, current_enrollment, fee_structure_id, status)   [12 rows today]
   │
academic_year_class_streams  (academic_year_class_id, stream_name, capacity, teacher_id, status, current_students)
   ▲
   │  class_enrollments.student_id + class_id + stream_id + academic_year_id   [61 rows today]
```

`academic_year_classes` does **not** need to be invented — `class_year_assignments` IS it. Per rule 10 of the guide (no duplicate canonical tables) it should be kept/renamed, not recreated.
