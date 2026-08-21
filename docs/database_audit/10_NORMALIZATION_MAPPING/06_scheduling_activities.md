# Normalization mapping — Scheduling & Activities
Part of `10_NORMALIZATION_MAPPING/`. Covers 24 tables. Base evidence: `08_PER_TABLE_BREAKDOWN/06_scheduling_activities.md`, `/tmp/opencode/domains/domain_06.txt`.

### 1. `activities`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** mixed-context master — `start_date`/`end_date` carry the activity-instance window on the catalogue row; `title` vs target `name`; `started_by` FK undeclared; no `UNIQUE(name)` (intentional — clubs recur across years) [V]
- **Target home(s):** `activities` (id, code?, name, category_id, description) — §4.13 master
- **Composite / relation key:** `id`
- **Migration rule:** Keep MASTER, never re-ID; map `title`→`name`, add nullable `code`; keep `category_id`→activity_categories, `description`, `max_participants`, `target_audience`; retain `start_date`/`end_date` as the activity-instance window (year is derived from them); add `academic_year_id` as a non-key convenience column backfilled from `start_date` [I]; declare `started_by`→users FK; status lifecycle is a transition on the same row. [V]

### 2. `activity_categories`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** duplicate flag columns `is_active` + `status` both encode the same boolean; otherwise a clean REF [V]
- **Target home(s):** `activity_categories` — §4.13 master
- **Composite / relation key:** `id` + `UNIQUE(name)`
- **Migration rule:** Keep REF, never re-number; merge duplicate names by id (keep lowest id); drop `is_active` (keep `status`); keep `department_id`→departments FK as-is. [V]

### 3. `activity_participants`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** `student_id` not stream-scoped (no academic year); relation keyed to activity master instead of a scheduled instance; join/withdrawal lifecycle overwritten by status transitions [V]
- **Target home(s):** `activity_participants` (activity_schedule_id, student_academic_enrollment_id?, staff_id?, role) — §4.13 relation
- **Composite / relation key:** `UNIQUE(activity_schedule_id, student_academic_enrollment_id, role)`
- **Migration rule:** Re-home rows: `activity_id`→`activity_schedule_id` (the schedule row for the activity's term window); `student_id`→`student_academic_enrollment_id` (resolved via `student_academic_enrollments` on the year derived from `activity.start_date`); keep `role`, `joined_at`; preserve history by **appending** rows on status transition (withdrawn→completed), never rewriting a past row; undeterminable schedule/stream mapping = NULL + flag. [V]

### 4. `activity_resources`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** duplicate column pairs `name`/`resource_name` and `type`/`resource_type`; `status` varchar [V]
- **Target home(s):** `activity_resources` — §4.13 relation
- **Composite / relation key:** `activity_id` (resource committed to one activity instance)
- **Migration rule:** Keep TXN; de-duplicate to single `name` + `type` columns; keep `resource_url`, `quantity`, `cost`; align `status` to the same enum used by sibling activity tables [I]; no year context — the activity carries it. [V]

### 5. `activity_schedule`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** `day_of_week` is `varchar(20)` while `class_schedules.day_of_week` is an enum (cross-domain week queries diverge); recurrence stored as loose rows; `venue` free text not FK'd [V]
- **Target home(s):** `activity_schedule` (activity_id, academic_year_term_id, day/time, location) — §4.13
- **Composite / relation key:** `(activity_id, academic_year_term_id, schedule_date, start_time)`
- **Migration rule:** Keep TXN; add `academic_year_term_id` backfilled from `schedule_date`; align `day_of_week` to the shared enum; represent recurrence as explicit rows, never `curdate()`; optionally bind `venue`→rooms where resolvable, else keep free text. [V]

### 6. `activity_staff_participants`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** `staff_id` FK never declared; keyed to activity master, mirroring the participant fault; same status-rewrite risk [V]
- **Target home(s):** `activity_staff_participants` (activity_schedule_id, staff_id?, role) — §4.13 relation
- **Composite / relation key:** `UNIQUE(activity_schedule_id, staff_id, role)`
- **Migration rule:** Re-home rows: `activity_id`→`activity_schedule_id`; keep `staff_id` (never re-number the 60-table staff FK family) and declare `staff_id`→staff FK; add `role`; append-not-rewrite on status transitions; undeterminable schedule mapping = NULL + flag. [V]

### 7. `attendance_sessions`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** clean REF; `session_id` links in `student_attendance`/`boarding_attendance` are undeclared FKs [V]
- **Target home(s):** `attendance_sessions` (id, code, name, type: morning/afternoon/boarding/activity) — master kept
- **Composite / relation key:** `id` + `UNIQUE(code)`
- **Migration rule:** Keep master, never re-ID; map `session_type`→`type`; keep `start_time`/`end_time`, `applies_to`, `applicable_days`, `is_mandatory`, `display_order`, `status`; declare the child FKs; roll-call expectation views must select the year via `academic_year_id`, never `curdate()`. [V]

### 8. `class_schedules`
- **Disposition:** SPLIT
- **Normalization fault(s):** year-term-class-period row binds five contexts at once; `subject_id`→`curriculum_units` is EMPTY (orphan risk for every row); `period_number`/`start_time`/`end_time` duplicate the `time_slots` master; `academic_year_id`/`term_id` FKs undeclared [V]
- **Target home(s):** `timetable_entries` (academic_year_class_stream_id, academic_year_term_id, day_of_week, time_slot_id, learning_area_id, teacher_id) + `timetable_templates` — §4.13
- **Composite / relation key:** `UNIQUE(academic_year_class_stream_id, academic_year_term_id, day_of_week, time_slot_id, learning_area_id, teacher_id)`
- **Migration rule:** `class_id`+`academic_year_id`+`term_id` → `academic_year_class_streams` (instance) + `academic_year_term_id`; `subject_id`→`learning_area_id` resolved through the curriculum spine (`academic_year_class_learning_areas`), unresolvable = NULL + flag; `period_number`+times → `time_slot_id`→time_slots; `room_id`→rooms and `teacher_id`→staff kept; template rows stay in `timetable_templates` as the generation source — never re-number class/room/teacher ids. [V]

### 9. `csl_activities`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** no `UNIQUE(name/code)` (event occurrence, not a stable master); event date embedded in identity; `organized_by`→users rather than staff [V]
- **Target home(s):** `csl_activities` (community-service learning, keyed term) — §4.13
- **Composite / relation key:** `(academic_year_term_id, activity_date, activity_name, organized_by)`
- **Migration rule:** Keep TXN; add `academic_year_id` + `academic_year_term_id` backfilled from `activity_date`; keep `beneficiary`/`impact_area`/`total_hours`/`location`; status lifecycle is a transition, never a rewrite; any future student/staff attribution reuses the `activity_participants`/`activity_staff_participants` pattern — no new table. [V]

### 10. `driver_attendance`
- **Disposition:** MERGE
- **Normalization fault(s):** duplicates `staff_attendance` for a staff subtype (drivers); `driver_id` FK undeclared; `leave` status semantics diverge from `staff_attendance` [V]
- **Target home(s):** `staff_attendance` (staff_id, date, check_in, check_out, status) — §4.11, with duty role
- **Composite / relation key:** `UNIQUE(staff_id, date)` on `staff_attendance`
- **Migration rule:** Re-home each row as `staff_attendance(staff_id, date, status)` with `duty_role='driver'`; resolve `driver_id`→staff via the person/staff subtype (drivers keep their licence record); align `leave` semantics with `staff_attendance`; declare the FK. [V]

### 11. `exam_schedules`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** `subject_id`→`curriculum_units` empty-orphan risk; `academic_year_id`/`term_id`/`supervisor_id`/`created_by` FKs undeclared; exam bound to class+year+term instead of a class-stream instance [V]
- **Target home(s):** `exam_schedules` / `supervision_rosters` (tied to academic_year_class_streams + terms) — §4.4
- **Composite / relation key:** `UNIQUE(academic_year_class_stream_id, academic_year_term_id, learning_area_id)` per sitting (exam_date, start_time)
- **Migration rule:** `class_id`+`academic_year_id`+`term_id` → `academic_year_class_stream_id` + `academic_year_term_id`; `subject_id`→`learning_area_id` via the curriculum spine, unresolvable = NULL + flag; keep `room_id`, `invigilator_id`→staff, `supervisor_id`→staff, `duration_minutes`, `exam_name`/`exam_type`, `status`; declare the spine FKs; never re-number class/room/staff ids. [V]

### 12. `rooms`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** clean MASTER — no year/term context; `name`/`code` both present [V]
- **Target home(s):** `rooms` — master kept (referenced by `academic_year_class_streams.room_id` and the timetable)
- **Composite / relation key:** `id` + `UNIQUE(code)`
- **Migration rule:** Keep MASTER, never re-ID; merge duplicate room codes if found; room usage per year lives in the timetable/instance tables, not here. [V]

### 13. `route_permissions`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** clean SYS — RBAC matrix; the `routes` parent is the **route registry**, not transport [V]
- **Target home(s):** `role_routes` (system domain, §4.15) — RBAC route↔permission matrix under `routes_registry`
- **Composite / relation key:** `UNIQUE(route_id, permission_id, access_type)`
- **Migration rule:** Keep SYS; note `routes` here is the RBAC route registry (controller/action), DISTINCT from `transport_routes` — do not conflate; keep the unique triple; declare `route_id`→routes_registry and `permission_id`→permissions FKs; no year context. [V]

### 14. `schedule_changes`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** HIST by design — `schedule_id` is a logical pointer to timetable rows that are regenerated each year; no year/term attribution [V]
- **Target home(s):** `timetable_changes` — append-only audit fact (no ongoing archive mechanism)
- **Composite / relation key:** `(schedule_type, schedule_id, changed_at)`
- **Migration rule:** Rename to `timetable_changes`; keep append-only — never backfill or rewrite; keep `schedule_id` logical since timetable rows are rebuilt per year; add `academic_year_id` only if conflict history must be replayable per year [I]; `changed_by`→users FK stays. [V]

### 15. `schedules`
- **Disposition:** RETIRE
- **Normalization fault(s):** generic time-ranged registry with no year/term/class FK, no enum payload, and no FK children; superseded by the domain-specific schedule tables [U]
- **Target home(s):** none — no target equivalent; candidate re-home only if a code path reads it
- **Composite / relation key:** `id` (no year attribution)
- **Migration rule:** No target home; the table is empty today — if any rows exist, review each for re-homing to `timetable_templates`/`school_events`, undeterminable rows = NULL + flag; otherwise drop and keep rows read-only in the one-time migration snapshot; confirm no code path reads it before retiring. [U]

### 16. `school_calendar`
- **Disposition:** SPLIT
- **Normalization fault(s):** `term_id` FK undeclared; the `affects_day_students`/`affects_boarders`/`requires_attendance` flags are properties of `day_type`, not of each date — repeating them per row is a transitive dependency; `day_type` enum denormalized as an inline value; no week-level structure — the school operates on a dated week grid (week 1 T1 … week 10 T3) [V]
- **Target home(s):** `calendar_day_types (id, code UNIQUE, name, affects_day_students, affects_boarders, requires_attendance)` REF + `academic_year_calendar_days (id, academic_year_calendar_id, date, calendar_day_type_id, title?, description?, UNIQUE(academic_year_calendar_id, date))` — §4.13; the parent `academic_year_calendar (id, academic_year_term_id, week_number, week_start, week_end, UNIQUE(academic_year_term_id, week_number))` holds the dated week grid
- **Composite / relation key:** `UNIQUE(academic_year_calendar_id, date)`; `week_number` derived from term's opening/closure
- **Migration rule:** split `day_type`+flags into the `calendar_day_types` REF (one row per distinct type; where the legacy rows disagree on a flag for the same type, take the owner's decision or the majority and log the exception); declare `term_id`→academic_year_terms FK; build `academic_year_calendar` weeks per term from each term's `opening_date`/`half_term`/`closing_date` boundaries, then load each date row into `academic_year_calendar_days` with `calendar_day_type_id`, `title`/`description`; a 2027 calendar is new rows, never a rewrite of 2026; select years via `academic_year_id`, never `curdate()`. [V]

### 17. `school_events`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** no year/term FK; event identity `(title, event_date)` carries year implicitly in the date [V]
- **Target home(s):** `school_events` (id, academic_year_calendar_day_id?, academic_year_term_id?, title, start_at, end_at, type, location) — §4.13, keyed to a calendar day (which implies year+term+week)
- **Composite / relation key:** `(academic_year_calendar_day_id, title, start_at)`
- **Migration rule:** Keep MASTER; add `academic_year_calendar_day_id` backfilled from `event_date` (via the calendar), `academic_year_term_id` backfilled from `event_date` (via term boundaries), undeterminable = NULL + flag; map `event_date`/`event_time`→`start_at`, `end_date`→`end_at`, `category`→`type`; keep `location`, `status`; no re-ID. [V]

### 18. `school_facilities`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** clean CFG — presentation rows only, no year context, no FK [V]
- **Target home(s):** `school_facilities` — keep
- **Composite / relation key:** `id` + `display_order`
- **Migration rule:** Keep CFG; no structural change; content rows with no year context. [V]

### 19. `school_week_config`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** `academic_year_id` UNIQUE but FK undeclared; per-year row correctly scoped [V]
- **Target home(s):** `school_week_config` — keep master (one row per academic year)
- **Composite / relation key:** `UNIQUE(academic_year_id)`
- **Migration rule:** Keep; declare `academic_year_id`→academic_years FK; keep `saturday_classes`, `sunday_boarding`, `class_days`/`boarding_days` JSON; the 2027 config is an appended row, never a mutation of the 2026 row; drives `attendance_sessions.applicable_days`. [V]

### 20. `student_attendance`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** `class_id`/`term_id`/`academic_year_id` duplicate the instance context already derivable from the student's `student_academic_enrollments` row; `session_id`/`register_type` mix must stay coherent [V]
- **Target home(s):** `student_attendance` (student_academic_enrollment_id, date, session_id, status, marked_by, UNIQUE(student_academic_enrollment_id, date, session_id)) — §4.4
- **Composite / relation key:** `UNIQUE(student_academic_enrollment_id, date, session_id)`
- **Migration rule:** `student_id`→`student_academic_enrollment_id` (resolved via `student_academic_enrollments` on the year already present); **REMOVE** `class_id`/`term_id`/`academic_year_id` — derived from `student_academic_enrollments.academic_year_class_stream_id`; `session_id`→attendance_sessions; keep `status`, `marked_by`, `check_in_time`/`check_out_time`, `absence_reason`, `permission_id`, `register_type` as fact columns [I]; undeterminable stream mapping = NULL + flag; never rewrite a past mark. [V]

### 21. `time_slots`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** clean REF — static day shape; referenced by `period_number` in legacy class_schedules [V]
- **Target home(s):** `time_slots` (id, code, start_time, end_time) — master kept
- **Composite / relation key:** `id` (or `(period_number, start_time)`)
- **Migration rule:** Keep master; referenced by `timetable_entries.time_slot_id`; add `academic_year_id` only if the day shape changes per year — today it is static [U]; otherwise keep as-is. [V]

### 22. `timetable_conflicts`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** `schedule_id_1`/`schedule_id_2` logical pointers into a regenerated timetable; no year/term attribution [V]
- **Target home(s):** `timetable_conflicts` — keep (advisory fact over timetable_entries)
- **Composite / relation key:** `(schedule_id_1, schedule_id_2, day_of_week, time_slot)`
- **Migration rule:** Keep TXN; add `academic_year_id`/`academic_year_term_id` so conflict history attributes per year; keep schedule_id_* logical to survive timetable regeneration; keep `conflict_type`, `status` lifecycle, `reported_by`/`resolved_by`→users (declare FK). [V]

### 23. `timetable_templates`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** `template_data` longtext (schedule generation source); `class_id`/`created_by` FKs undeclared; borderline CFG [V]
- **Target home(s):** `timetable_templates` — keep master (template library feeding `timetable_entries`)
- **Composite / relation key:** `id` + `name` under `applies_to` scope
- **Migration rule:** Keep REF; declare `class_id`→classes and `created_by`→users FKs; add `academic_year_id` only if templates become year-specific [U]; a template change affects only future schedule generation, never past `timetable_entries` rows. [V]

### 24. `vw_upcoming_activities`
- **Disposition:** RETIRE
- **Normalization fault(s):** real table misnamed `vw_` (no PK, all columns nullable); `curdate()`-bound snapshot window — one of the 23 time-bomb views [V]
- **Target home(s):** none — re-created as a genuine VIEW over `activities` (no table home)
- **Composite / relation key:** none (derived projection)
- **Migration rule:** RETIRE-as-view: drop the materialized table and re-create a real `CREATE VIEW` over `activities` (join `activity_categories` for the category) filtered by `academic_year_id` or an explicit date range — never `curdate()`; rename with a `v_`/`summary_` prefix; no data migration (all values re-derivable from `activities`). [U]
