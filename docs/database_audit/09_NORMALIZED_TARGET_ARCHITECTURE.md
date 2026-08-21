# 09 — Normalized Target Architecture (3NF/4NF) — the "to-be" model

Supersedes the earlier "the context spine already exists" framing, which is **withdrawn**.
The legacy tables encode unnormalized logic and are **redesigned** — whether they existed or not is irrelevant.
Reuse-by-alter is allowed only where the physical table already matches a normalized concept; otherwise split, merge, or retire.
No data overriding, no archiving-over — this is a warehouse-grade, audit-ready design.

## 1. Mandated principles

1. **Fully normalized** — 3NF minimum, 4NF wherever multi-valued facts exist.
2. **Zero redundancy** — a fact exists in exactly one place. No duplicate/stored-derived columns (e.g. both `amount_paid` and `balance` when `balance = amount_due - amount_paid`), no "current X" column that duplicates a relation row.
3. **No data overriding, no archiving-over** — a state change is a **new fact row** or an **append-only audit entry**. Historical rows are never mutated, never "archived and closed". The past is only ever read.
4. **Warehouse / audit-ready** — every fact is attributable: who, what, when, and against which year/term/class/stream instance. Any year (2026, 2027, … 2040) is queried by its stable `id` — never by `curdate()`.
5. **Masters are pure** — they carry no year/term/class/stream context and are never re-ID'd.
6. **Context lives in relation tables** — every year-scoped binding is an explicit relation row with a composite key.
7. **Derived values are views, not tables** (balances, arrears, totals, positions) — unless a concrete fact must be snapshotted for audit, in which case it is written once by the process that owns it.

## 2. The canonical spine (guide chain `docs/DATABASE_ARCHITECTURE_GUIDE.md`, plus the owner's example)

```
classes                     (id, code UNIQUE, name UNIQUE)                        -- master
academic_years              (id, code UNIQUE, name, start_date, end_date, status) -- master
terms                       (id, name UNIQUE, code UNIQUE)                        -- master (Term 1, Term 2, Term 3)
streams                     (id, name UNIQUE, code UNIQUE, capacity)              -- master
learning_areas              (id, code UNIQUE, name, level_band)                   -- master (national REF, read-only)

academic_year_classes       (id, academic_year_id, class_id,
                             UNIQUE(academic_year_id, class_id))                  -- class-in-year context
academic_year_class_streams (id, academic_year_class_id, stream_id, room_id?, class_teacher_id?, capacity?,
                             UNIQUE(academic_year_class_id, stream_id))           -- stream-in-class-in-year context

student_academic_enrollments (id, student_id, academic_year_class_stream_id, enrolled_on,
                              enrollment_status, UNIQUE(student_id, academic_year_id),
                              UNIQUE(academic_year_class_stream_id, student_id))  -- enrollment context

academic_year_class_learning_areas        (id, academic_year_class_id, learning_area_id,
                              strand_id?, sub_strand_id?, status,
                              UNIQUE(academic_year_class_id, learning_area_id))    -- learning-area context
academic_year_class_learning_area_teachers(id, academic_year_class_learning_area_id, academic_year_term_id, staff_id, role,
                              UNIQUE(academic_year_class_learning_area_id, academic_year_term_id, staff_id, role)) -- role: subject_teacher | assistant | hod; one row per term — a mid-year change appends a row, never edits the old one
```

**History rule:** 2026 enrollment = rows whose `academic_year_classes.academic_year_id` = the 2026 id. 2027 adds new rows. Nothing is overwritten. This is the pattern every domain below follows.

## 3. The pattern, generalized

For every domain: **MASTERS** (stable entities: `classes`, `streams`, `terms`, `academic_years`, `learning_areas`, `staff`) → **CONTEXT** (year-scoped bindings: `academic_year_classes` / `academic_year_class_streams` / `academic_year_terms`) → **FACTS** (operational records keyed to a context + term + date) → **AUDIT** (`audit_logs`, append-only). The term chain is `terms` (Term 1/2/3 master) → `academic_year_terms` (term-in-year instance with opening/half-term/closing dates) → calendar (`academic_year_calendar` week grid) → dated facts. Students are linked to curriculum through `student_academic_enrollments` → `academic_year_class_streams` → `academic_year_classes` → `academic_year_class_learning_areas`. Master records never move between years; context records do; operational records attach to context; history is never overwritten. Planning *content* is itself a reusable master layer: scheme/lesson templates live once (bound to the national REF by strand/sub-strand), and each year-scoped delivery attaches that content to a context via a thin instance row — so 2026 Grade 5A content is reused by 2028 Grade 5C without copying, and a curriculum revision creates a new template while the old one stays frozen under its prior-year instances.

## 4. Target model by domain

### 4.1 People & identity (4NF: shared person base + subtypes)
```
persons            (id, first_name, middle_name, last_name, dob, gender, national_id_no, photo_url, email, phone)
users              (id, person_id UNIQUE, username UNIQUE, password_hash, status, ...)          -- account (not a person copy)
students           (id, person_id UNIQUE, admission_no UNIQUE, admission_date, status)           -- learner subtype
staff              (id, person_id UNIQUE, staff_no UNIQUE, employment_date, status)              -- employee subtype
parents            (id, person_id UNIQUE, occupation, ...)                                       -- guardian subtype
student_parents    (student_id, parent_id, relationship, is_primary, PRIMARY KEY(student_id, parent_id))
```

### 4.2 Academic masters
```
classes            (id, code UNIQUE, name UNIQUE)                         -- 12 rows, Playgroup → Grade 9
streams            (id, name UNIQUE, code UNIQUE, capacity)               -- 'A', 'B', 'C' or single
terms              (id, name UNIQUE, code UNIQUE)                         -- Term 1, Term 2, Term 3 (stable; never re-ID'd)
academic_years     (id, code UNIQUE, name, start_date, end_date, status)  -- no is_current column (current = one row with status 'active' / config)
```

### 4.3 Context instances (the bridge the guide specified)
```
academic_year_terms            (id, academic_year_id, term_id, opening_date, half_term_start, half_term_end,
                                closing_date, status, UNIQUE(academic_year_id, term_id))  -- term-in-year instance (dates vary per year; name/code come from the terms master)
academic_year_classes          (id, academic_year_id, class_id, status,
                                UNIQUE(academic_year_id, class_id))
academic_year_class_streams    (id, academic_year_class_id, stream_id, room_id?, class_teacher_id?, capacity?, status,
                                UNIQUE(academic_year_class_id, stream_id))
student_academic_enrollments   (id, student_id, academic_year_class_stream_id, enrolled_on, enrollment_status,
                                UNIQUE(student_id, academic_year_id), UNIQUE(academic_year_class_stream_id, student_id))  -- surrogate id for FK-friendliness
academic_year_class_learning_areas       (id, academic_year_class_id, learning_area_id, strand_id?, sub_strand_id?,
                                status, UNIQUE(academic_year_class_id, learning_area_id, strand_id?, sub_strand_id?))
academic_year_class_learning_area_teachers (id, academic_year_class_learning_area_id, academic_year_term_id, staff_id, role,
                                UNIQUE(academic_year_class_learning_area_id, academic_year_term_id, staff_id, role))  -- role: subject_teacher | assistant | hod; term-scoped so teacher X (T1) → teacher Y (T2) is two rows, never a rewrite
student_transitions         (id, student_id, from_student_academic_enrollment_id?, to_student_academic_enrollment_id?, academic_year_id,
                             transition_type, reason, decided_by, decided_at, executed_at)   -- promotion/retention/transfer/graduation history
```

### 4.4 Academic operations (facts, keyed to contexts)
```
student_attendance          (student_academic_enrollment_id, date, session_id, status, marked_by, UNIQUE(student_academic_enrollment_id, date, session_id))
assessments                 (id, academic_year_class_learning_area_id, academic_year_calendar_day_id?, strand_id?, sub_strand_id?, type, title, max_score, ...)
assessment_results          (assessment_id, student_academic_enrollment_id, score, grade?, UNIQUE(assessment_id, student_academic_enrollment_id))
formative_scores            (student_academic_enrollment_id, academic_year_term_id, learning_area_id, score, assessed_by, assessed_date)
term_subject_scores         (student_academic_enrollment_id, academic_year_term_id, learning_area_id, totals, UNIQUE(student_academic_enrollment_id, academic_year_term_id, learning_area_id))
term_consolidations         (student_academic_enrollment_id, academic_year_term_id, aggregates, UNIQUE(student_academic_enrollment_id, academic_year_term_id))
annual_scores               (student_academic_enrollment_id, academic_year_id, aggregates, UNIQUE(student_academic_enrollment_id, academic_year_id))
national_exam_results       (student_academic_enrollment_id, exam_type, exam_year, learning_area_id, score, grade, UNIQUE(...))
portfolios                  (id, student_academic_enrollment_id, academic_year_id, portfolio_type, title, theme, description, status, UNIQUE(student_academic_enrollment_id, academic_year_id, portfolio_type))
portfolio_artifacts         (id, portfolio_id, lesson_plan_id?, artifact_title, artifact_type, file_path, media_id?, description, upload_date, learner_reflection, teacher_feedback, competency_id?, value_id?, rating, UNIQUE(portfolio_id, lesson_plan_id?, artifact_title))
                            -- lesson_plan_id binds the artifact to the exact lesson (→ date → week → term → learning area → class → year), so "the artifact from the Place Value lesson in week 5" is one join; NULL lesson_plan_id = portfolio-level artifact
learner_competencies        (student_academic_enrollment_id, competency_id, academic_year_term_id, performance_level_id, evidence, assessed_by, UNIQUE(student_academic_enrollment_id, competency_id, academic_year_term_id))
scheme_templates  (id, learning_area_id, strand_id, sub_strand_id, title, activities, resources, assessment_methods,
                   created_by, is_shared, status,
                   UNIQUE(learning_area_id, strand_id, sub_strand_id, title))   -- [NEW] reusable content master — NO year/term/class; a curriculum revision creates a new template, the old one stays frozen under prior-year instances
lesson_templates  (id, learning_area_id, strand_id, sub_strand_id, title, duration, activities, resources, assessment, homework,
                   created_by, is_shared, status,
                   UNIQUE(learning_area_id, strand_id, sub_strand_id, title))   -- [NEW] reusable content master
scheme_template_learning_outcomes (scheme_template_id, learning_outcome_id, PK(scheme_template_id, learning_outcome_id))  -- [NEW] objectives selected for the template (national REF, stable per grade)
lesson_template_learning_outcomes (lesson_template_id, learning_outcome_id, PK(lesson_template_id, learning_outcome_id))  -- [NEW] objectives for the lesson
schemes_of_work (id, scheme_template_id, academic_year_class_learning_area_id, academic_year_calendar_week_id, teacher_id, status, approved_by,
                 UNIQUE(academic_year_class_learning_area_id, academic_year_calendar_week_id, scheme_template_id))
                 -- year-instance: strand/sub-strand/outcomes derive from the template; week dates come from academic_year_calendar, never a bare week_number
lesson_plans    (id, lesson_template_id, academic_year_class_learning_area_id, academic_year_calendar_day_id, teacher_id, status, approved_by,
                 UNIQUE(academic_year_class_learning_area_id, academic_year_calendar_day_id, lesson_template_id))
                 -- year-instance: date→week→term resolves via the calendar, so no redundant term_id
lesson_deliveries (id, lesson_plan_id, delivered_on, delivered_by, taught_duration, outcomes_met, outcomes_total, follow_up_notes,
                 UNIQUE(lesson_plan_id, delivered_on))
                 -- [NEW] actual-vs-plan fact: "met the plan" = outcomes_met/outcomes_total and taught_duration vs lesson_templates.duration; a rescheduled lesson appends a second delivery row, never rewrites the first
assignments     (id, academic_year_class_learning_area_id, academic_year_calendar_day_id?, strand_id?, sub_strand_id?, teacher_id, title, total_marks, due_date, status)
assignment_submissions      (assignment_id, student_academic_enrollment_id, submitted_on, score, ...)
discipline_incidents        (student_academic_enrollment_id, academic_year_term_id, incident_type, severity, incident_date, description, action) + conduct_tracking
timetable_entries           (academic_year_class_stream_id, academic_year_term_id, day_of_week, time_slot_id, learning_area_id, teacher_id, UNIQUE(...))
exam_schedules / supervision_rosters (tied to academic_year_class_streams + terms)
```

### 4.5 Curriculum (pure content masters — school never edits these; it selects coverage)
```
learning_areas (id, code UNIQUE, name, level_band)
strands        (id, learning_area_id, grade_level, code, name, variant, source_document, UNIQUE(learning_area_id, grade_level, name, variant))
sub_strands    (id, strand_id, grade_level, code, name, variant, UNIQUE(strand_id, grade_level, name, variant))
learning_outcomes (id, learning_area_id, strand_id?, sub_strand_id, outcome, grade_level)   -- national REF; the school does NOT write objectives, it SELECTS which of these each scheme week / lesson covers
sub_strand_competencies / strand_competency / sub_strand_values / sub_strand_pci_issues /
sub_strand_suggested_experiences / sub_strand_key_inquiry_questions / sub_strand_rubrics / core_competencies /
core_values / pcis — all content masters keyed to (sub_)strand; school implementation = academic_year_class_learning_areas only
```
**Objectives chain:** `scheme_templates` / `lesson_templates` select learning outcomes from this REF via the junction tables in §4.4; `schemes_of_work` / `lesson_plans` derive strand, sub-strand, and outcomes through their template — so "teacher X teaches sub-strand Y (strand Z, learning area L) on date D of week W of term T, addressing outcomes O1..On" is one query from a scheme/lesson row. No free-text objectives.

### 4.6 Finance (masters → year/term schedule → per-student obligation → payment fact → allocation)
```
fee_catalog              (id, code UNIQUE, name, fee_type_id, student_type_id?, default_amount)        -- master
academic_year_fee_schedules (id, academic_year_id, academic_year_term_id?, academic_year_class_id?, student_type_id?,
                             fee_catalog_id, amount, due_date, UNIQUE(year, term?, class?, stream?, type, fee_catalog))
student_fee_obligations  (id, student_academic_enrollment_id, academic_year_fee_schedule_id, amount_due, UNIQUE(student_academic_enrollment_id, academic_year_fee_schedule_id))
payments                 (id, receipt_no UNIQUE, student_id, amount, payment_date, method, reference, received_by, status)   -- cash fact, not pre-bound to a term
payment_allocations      (payment_id, student_fee_obligation_id, amount_allocated, allocated_by, allocated_at, UNIQUE(payment_id, student_fee_obligation_id))
fee_credit_notes / fee_discounts_waivers  (student_academic_enrollment_id, academic_year_term_id, amount, reason, approved_by)
balances / arrears       → VIEWS derived from obligations + allocations + credits (never stored, no curdate())
budgets                  (id, academic_year_id, term_id?, department_id?, name, amount, status, workflow)
budget_line_items / budget_amendments / department_budget_proposals / department_fund_requests
expenses                 (id, budget_line_item_id?, department_id?, vendor_id?, amount, expense_date, method, status, workflow)
financial_periods        (id, code UNIQUE, name, start_date, end_date)                                  -- school-wide fiscal periods
school_transactions / bank_transactions / mpesa_transactions / petty_cash_*   (dated money-movement facts referencing payments/expenses)
payroll_runs             (id, financial_period_id?, month, year, status, workflow)
payslips                 (id, payroll_run_id, staff_id, gross, deductions, net, UNIQUE(payroll_run_id, staff_id))
payslip_items            (payslip_id, item_type, item_name, amount)
staff_allowances / staff_deductions (staff_id, type_id, amount, effective_from, effective_to)
tax_brackets / tax_withholding_history
uniform_items            (id, code UNIQUE, name, size, price)                                          -- catalog
uniform_sales            (id, student_academic_enrollment_id, uniform_item_id, quantity, unit_price, sale_date, sold_by)
uniform_sale_payments    (sale_id, amount, method, payment_date, receipt_no)
```

### 4.7 Transport
```
transport_routes (id, code UNIQUE, name) · transport_stops (id, route_id, name, order_no) · transport_vehicles (id, reg_no UNIQUE, capacity)
transport_vehicle_routes (vehicle_id, route_id, academic_year_id?, effective_from, effective_to)
transport_schedules / route_schedules (route_id, stop_id, time, direction)
student_transport_assignments (student_academic_enrollment_id, route_id, stop_id, month, year, amount, status, UNIQUE(student_academic_enrollment_id, month, year))
transport_fee_catalog + transport_bills / transport_monthly_bills / transport_bill_payments
vehicle_fuel_logs / vehicle_maintenance
```

### 4.8 Boarding
```
dormitories (id, code UNIQUE, name, capacity) · beds (id, dormitory_id, label)
dormitory_assignments (id, student_academic_enrollment_id, dormitory_id, bed_id?, academic_year_id, start_date, end_date?, UNIQUE(student_academic_enrollment_id, academic_year_id))
boarding_attendance (dormitory_assignment_id, date, session_id, status, UNIQUE(dormitory_assignment_id, date, session_id))
```

### 4.9 Inventory / procurement
```
inventory_categories · inventory_items (id, code UNIQUE, name, category_id, unit_of_measure) · inventory_locations / storage_locations
item_batches (item_id, batch_no, expiry) · item_serials (item_id, serial_no)
inventory_transactions (item_id, location_id, transaction_type, quantity, unit_price, transaction_date, reference_entity_type, reference_entity_id)
inventory_adjustments · inventory_counts + inventory_count_items · inventory_allocations
purchase_orders · requisitions + requisition_items · suppliers
```

### 4.10 Catering
```
meal_plans (id, name, academic_year_term_id?) · menu_items (id, name, unit) · menu_item_ingredients (menu_item_id, inventory_item_id, quantity)
daily_meal_allocations (menu_item_id?, date, quantity) · food_consumption_records · catering_meal_statuses
```

### 4.11 Staff / HR
```
departments (id, code UNIQUE, name, parent_department_id?)
staff_department_assignments (staff_id, department_id, role, effective_from, effective_to)
staff_contracts / staff_appointments / staff_employment_profiles
staff_attendance (staff_id, date, check_in, check_out, status, UNIQUE(staff_id, date)) + staff_attendance_profiles + department_attendance_rules
staff_leaves (staff_id, leave_type_id, start_date, end_date, status, approved_by) + leave_types
staff_loans / staff_salary_advances
staff_duty_roster (staff_id, academic_year_term_id?, date, duty_type_id, shift, ...) / staff_shift_assignments / staff_off_day_patterns  -- roster dates follow academic_year_terms
performance_reviews (staff_id, period, rating, reviewed_by) · kpi_definitions / kpi_targets / kpi_achievements / performance_review_kpis
staff_qualifications / staff_experience / staff_children / staff_incident_reports / staff_offboarding / staff_onboarding
job_vacancies / job_applications / careers_benefits
```

### 4.12 Admissions
```
admission_applications (id, applicant details, academic_year_id, preferred_class_id, status, workflow)
admission_placement_tests (application_id, learning_area_id, score, date)
admission_interviews (application_id, staff_id, outcome)
admission_decisions (application_id, decision, decided_by, decided_at)
admission_enrollment_confirmations (application_id, ...)
admission_placements / placement_offers (application_id, academic_year_class_stream_id, offered, accepted)
admission_payments → payments (reference application)
web_admission_applications → merged into admission_applications (source='web')
```

### 4.13 Scheduling / events / activities
```
activities (id, code?, name, category_id, description) + activity_categories
activity_schedule (activity_id, academic_year_calendar_day_id?, academic_year_term_id?, day/time, location)
activity_participants (activity_schedule_id, student_academic_enrollment_id?, staff_id?, role) + activity_staff_participants + activity_resources
csl_activities (same pattern, community-service learning)
time_slots (id, code, start_time, end_time) · school_week_config
academic_year_calendar (id, academic_year_term_id, week_number, week_start, week_end,
                        UNIQUE(academic_year_term_id, week_number))    -- dated week grid (week 1 T1 … week 10 T3), generated from term opening/half-term/closing dates
calendar_day_types (id, code UNIQUE, name, affects_day_students, affects_boarders, requires_attendance)   -- [NEW] REF: school_day/half_day/exam_day/special_event/holiday; flags are properties of the TYPE, not the date
academic_year_calendar_days (id, academic_year_calendar_id, date, calendar_day_type_id, title?, description?,
                        UNIQUE(academic_year_calendar_id, date))  -- per-date rows (from legacy school_calendar); flags resolve via calendar_day_types
school_events (id, academic_year_calendar_day_id?, academic_year_term_id?, title, start_at, end_at, type, location)  -- activities bind to a date/week
timetable_templates / timetable_entries / timetable_conflicts
```

### 4.14 Communications
```
communications (id, type, subject, body, sender_id, sent_at, status)
communication_recipients (communication_id, recipient_type, recipient_id, channel, status, delivered_at)  -- (recipient_type, recipient_id) is the normalized target reference
communication_attachments (communication_id, media_file_id)
communication_groups / communication_templates / message_templates / template_categories
sms_communications / email_logs / external_emails / external_inbound_messages / outbound_messages
internal_conversations / internal_messages / conversation_participants / message_read_status
notifications / announcements_bulletin + announcement_views / news_articles + news_categories / newsletter_subscribers
forum_threads / forum_posts / group_members / contact_directory / contact_inquiries
media_files / albums / gallery_items / page_downloads
```

### 4.15 System / auth / RBAC / workflow / audit
```
users / roles (id, code UNIQUE, name) / permissions (id, code UNIQUE, name)
role_permissions (role_id, permission_id, PK(role_id, permission_id)) · user_roles (user_id, role_id, PK(user_id, role_id))
user_sessions / auth_sessions / refresh_tokens / api_tokens / user_2fa_backup_codes / user_2fa_otp_sessions
password_resets / password_history / login_attempts / user_login_attempts / failed_auth_attempts / blocked_ips / blocked_devices
routes_registry / role_routes / sidebar_menu_items / role_sidebar_menus / role_dashboards / dashboards / form_permissions / record_permissions
permission_delegations / role_delegations / delegation_audit
audit_logs (id, entity_type, entity_id, action, old_values, new_values, actor_id, acted_at)  -- THE only history mechanism, append-only
workflow_definitions / workflow_stages / workflow_stage_permissions / workflow_instances / workflow_history / workflow_stage_history / workflow_notifications
system_* config/policy/runtime tables (settings, feature_flags, modules, policies, access_rules, retention, backups, migrations, error_logs, api_metrics, background_jobs, webhooks, security_incidents, maintenance_windows, rate_limit_rules, ip_rules, domain_isolation)
```

## 5. Resolution of the six legacy defects in the target

| Legacy defect | Target resolution |
|---|---|
| `classes` carries `academic_year/teacher_id/capacity/status` + `uk_name_year` | **SPLIT** → `classes(id,code,name)` master; year context → `academic_year_classes`; room + class teacher → `academic_year_class_streams`; capacity → instance |
| `students.stream_id` single "current stream" | **REMOVE column** → enrollment lives only in `student_academic_enrollments` (one row per student-year) |
| `class_streams` bound to class + auto-create trigger | **SPLIT** → `streams` master (name/code/capacity); binding via `academic_year_class_streams`; trigger retired |
| `assessments` no year + orphaned `subject_id` | **ALTER** → add `academic_year_class_learning_area_id` + `academic_year_term_id`; `subject_id` FK removed, replaced by `learning_area_id`/`strand_id` |
| `student_discipline` no year/term/class | **ALTER** → `discipline_incidents(student_academic_enrollment_id, academic_year_term_id, ...)` |
| 8 real `vw_*` tables + `class_assignments` view | **RENAME** the 8 tables; **REPOINT/RETIRE** `class_assignments` to `academic_year_class_streams` |

## 6. Normalization faults the redesign eliminates (examples)
- Stored balances: `student_fee_obligations.balance/year_balance/term_balance` and `uniform_sales.balance/balance_due` → derived views only.
- `classes.academic_year` duplicating `class_year_assignments.academic_year_id` (redundancy) → removed.
- `classes.teacher_id` duplicating `staff_class_assignments` (redundancy) → removed.
- `students.stream_id` duplicating `class_enrollments` (redundancy) → removed.
- `academic_terms.year` column duplicating `academic_terms.academic_year_id` (partial key dependency) → keyed by `academic_year_id` only; `name` derived from the `terms` master instead of stored per year.
- `fee_structures_detailed.level_id/academic_year/term_id/student_type_id/fee_type_id` mixed-context uniqueness → normalized to `academic_year_fee_schedules` with explicit composite key.
- `class_enrollments.class_id/stream_id` duplicating the instance they belong to → kept only on `academic_year_class_streams`.
- `staff_class_assignments` carrying both `subject_id` (legacy) and `learning_area_id` → single `learning_area_id` in `academic_year_class_learning_areas`, teacher responsibility in `academic_year_class_learning_area_teachers`.
- `schemes_of_work`/`lesson_plans` content (activities, resources, objectives) duplicated across every year-instance, forcing manual copy for reuse → content lives once in `scheme_templates`/`lesson_templates`; year-instances attach it to a context and `lesson_deliveries` records the taught-vs-planned fact.

## 7. Mapping convention for the 431 legacy tables (delivered in file 10)
Every legacy table receives one disposition against this target model:
- **REUSE-ALTER** — physical table stays but is renamed/reshaped to a target concept and its logic corrected.
- **SPLIT** — columns/rows distributed to ≥2 target tables.
- **MERGE** — folded into an existing target concept.
- **RETIRE** — no home; data migrated into target(s), old rows preserved read-only in a one-time migration snapshot (not an ongoing archive mechanism).
- **NEW** — target table has no legacy home.
Each mapping row states the normalization fault, the target table(s), the composite/relation key, and the data-migration rule. No invented values; undeterminable cells stay NULL with a flag.
