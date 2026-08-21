# Normalization mapping — Core Academics (39 tables)

Base evidence: `docs/database_audit/08_PER_TABLE_BREAKDOWN/01_core_academics.md`, `/tmp/opencode/domains/domain_01.txt`, `docs/database_audit/09_NORMALIZED_TARGET_ARCHITECTURE.md` §4.2–4.4.

Target of truth: file 09, following the guide chain `terms → academic_year_terms → academic_year_calendar`, `academic_years → academic_year_terms`, `classes → academic_year_classes → academic_year_class_streams`, `students → student_academic_enrollments`. Legacy tables are mapped ONTO it; nothing is re-designed here. No invented values: any legacy cell that cannot be re-homed is migrated as `NULL` with a flag. Year context follows the `academic_years` id-map (2026 = id 5, 2027 = id 6); `academic_year year(4)` on legacy tables becomes `academic_year_id` throughout. `student_id` facts re-anchor onto `student_academic_enrollments.student_academic_enrollment_id` via the enrollment id-map. Tags: `[V]` column/row evidence verified in slice + 08, `[I]` migration relies on inferred links (FK/id-map), `[U]` genuinely undeterminable — migrated `NULL` + flag.

### 1. `academic_capacity_config`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** `academic_year_id` is index-only (no FK) so capacity thresholds can silently attach to the wrong year; 1 row
- **Target home(s):** `academic_capacity_config (academic_year_id, available_pct_threshold, limited_pct_threshold)`
- **Composite / relation key:** `academic_year_id`
- **Migration rule:** keep as CFG; add FK `academic_year_id→academic_years.id`; seed explicit 2026/2027 rows via the year id-map; never derive the year from `curdate()`. [V]

### 2. `academic_capacity_reservations`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** `class_id`/`stream_id` bind to the class/stream masters instead of the year-scoped `academic_year_class_streams` instance
- **Target home(s):** `academic_capacity_reservations (academic_year_id, academic_year_term_id, academic_year_class_stream_id, application_id, reservation_status, reserved_at, expires_at, released_at, converted_to_enrollment_at, created_by)`
- **Composite / relation key:** `UNIQUE(application_id)`; year/term/stream instance inherited
- **Migration rule:** re-home `term_id`→`academic_year_term_id`, `class_id`+`stream_id`→`academic_year_class_stream_id` via the instance id-map; add `created_by→users.id` FK; keep reservation-status transitions append-only; `expires_at` computed against the reserved term's `end_date`. [V]

### 3. `academic_class_progression`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** `effective_from_academic_year_id` is a plain column (no FK); duplicate rules not guarded
- **Target home(s):** `academic_class_progression (source_class_id, target_class_id, progression_type, effective_from_academic_year_id, active)`
- **Composite / relation key:** `(effective_from_academic_year_id, source_class_id, target_class_id, progression_type)`
- **Migration rule:** add FK `effective_from_academic_year_id→academic_years.id`; dedupe rules per (effective year, source, target); remap `source_class_id`/`target_class_id` through the classes merge (Grade1 id14→id6). [V]

### 4. `academic_terms`
- **Disposition:** SPLIT
- **Normalization fault(s):** redundant `year year(4)` column alongside `academic_year_id`; 18 orphan rows with NULL `academic_year_id`; `name` duplicated per year rather than derived from `term_number`; `start_date`/`end_date`/`opening_date`/`closing_date` mix of a year-instance with master identity
- **Target home(s):** `terms (id, name UNIQUE, code UNIQUE)` master — Term 1/2/3 stable + `academic_year_terms (id, academic_year_id, term_id, opening_date, half_term_start, half_term_end, closing_date, status, UNIQUE(academic_year_id, term_id))`
- **Composite / relation key:** `UNIQUE(academic_year_id, term_id)` — the term-in-year instance; 2026 = ids 7/8/9 (Term 1/2/3)
- **Migration rule:** split into the `terms` master (3 stable rows: Term 1, Term 2, Term 3) + one `academic_year_terms` row per year per term; `start_date`→`opening_date`, `midterm_break_start/end`→`half_term_start/half_term_end`, `end_date`→`closing_date`; drop `name` (derive from master) and `year`; backfill the 18 orphans to the year implied by their dates or flag for owner decision; create the 2027 trio at rollover; never delete (quarantine instead). [V]

### 5. `academic_year_archives`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** stored counts (`promoted_count`, `retained_count`, …) duplicate the `student_transitions` roll-up and can drift; `academic_year year(4)` not FK-bound
- **Target home(s):** `academic_year_archives (academic_year_id, status, closure_date, closure_notes, closure_initiated_by)`
- **Composite / relation key:** `UNIQUE(academic_year_id)` — one archive state per year
- **Migration rule:** add FK `academic_year_id→academic_years.id`; make the counts a derived view over `student_transitions`/`student_academic_enrollments`; quarantine archive rows for junk years ids 7–10. [V]

### 6. `academic_year_rollover_log`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** `from_year_id`/`to_year_id`/`performed_by` are index-only (no FKs); append-only discipline not enforced
- **Target home(s):** `academic_year_rollover_log` (HIST — the year-rollover execution audit)
- **Composite / relation key:** `rollover_id` + `step` + `performed_at`
- **Migration rule:** add FKs `from_year_id→academic_years.id`, `to_year_id→academic_years.id`, `performed_by→users.id`; append-only (never UPDATE past rows); `error_message` stays operational (logged, never exposed). [V]

### 7. `academic_years`
- **Disposition:** REUSE
- **Normalization fault(s):** none as a master; `total_students`/`total_classes` stored counts duplicate enrollments/instances
- **Target home(s):** `academic_years (id, code UNIQUE, name, start_date, end_date, status, is_current)`
- **Composite / relation key:** `year_code` UNIQUE — the year id is the context root (2026 = id 5, 2027 = id 6; junk planning ids 7–10)
- **Migration rule:** keep MASTER; drop the stored `total_*` counts (derive from `student_academic_enrollments`/`academic_year_class_streams`); keep `is_current` as the single active-year flag and drive all year lookups from it (fixes the 23-view `curdate()` time bomb); never re-ID. [V]

### 8. `annual_scores`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** `academic_year year(4)` not FK-bound; `student_id` binds to the person, not the year's enrollment; stored aggregates can drift from `term_subject_scores`/`term_consolidations`
- **Target home(s):** `annual_scores (student_academic_enrollment_id, academic_year_id, term1..term3 weights/scores/grades, annual_score, annual_percentage, annual_grade, annual_points, annual_rank, grade_percentile, strengths, weaknesses, pathway_classification)`
- **Composite / relation key:** `UNIQUE(student_academic_enrollment_id, academic_year_id)` — one aggregate per student per year
- **Migration rule:** re-home `student_id`→`student_academic_enrollment_id` via the enrollment id-map; `academic_year`→`academic_year_id`; recompute all score/rank/percentile fields from term aggregates at backfill; keep narrative `strengths`/`weaknesses`. [I]

### 9. `assessment_benchmarks`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** `academic_year year(4)` not FK-bound; `subject_id→curriculum_units` is orphaned (curriculum_units has 0 rows)
- **Target home(s):** `assessment_benchmarks (academic_year_id, grade_level_id, learning_area_id, benchmark_type, target_percentage, acceptable_range_min/max, created_by)`
- **Composite / relation key:** `(academic_year_id, grade_level_id, learning_area_id, benchmark_type)` — one target band per year/grade/learning-area
- **Migration rule:** `academic_year`→`academic_year_id`; repoint `subject_id`→`learning_area_id` via the learning-area id-map; backfill the 3 orphaned references. [I]

### 10. `assessment_history`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** `assessment_result_id` is a plain column (no FK); `changed_by` not FK-bound
- **Target home(s):** `assessment_history` (HIST — append-only result-edit trail)
- **Composite / relation key:** `assessment_result_id` + `changed_by` + `created_at`
- **Migration rule:** add FK `assessment_result_id→assessment_results.id`, `changed_by→users.id`; append-only (a marks edit adds a row, never overwrites); year/term resolved through the parent `assessments` row. [V]

### 11. `assessment_results`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** `assessment_id`/`student_id` index-only (no FKs); `responder_type`/`responder_id` untyped
- **Target home(s):** `assessment_results (assessment_id, student_academic_enrollment_id, marks_obtained, grade, points, remarks, peer_feedback, is_submitted, is_approved, submitted_at, responder_type, responder_id)`
- **Composite / relation key:** `UNIQUE(assessment_id, student_academic_enrollment_id)` — one result per student per assessment
- **Migration rule:** add FKs `assessment_id→assessments.id`, `student_academic_enrollment_id→student_academic_enrollments.id`; re-home `student_id` via the enrollment id-map; keep the UNIQUE pair; re-scoring goes through `assessment_history`. [I]

### 12. `assessment_rubrics`
- **Disposition:** REUSE
- **Normalization fault(s):** none — REF content (criteria descriptors per tool)
- **Target home(s):** `assessment_rubrics (tool_id, criteria_name, level_1..4_descriptor, points_per_level, sort_order)`
- **Composite / relation key:** `UNIQUE(tool_id, criteria_name, sort_order)`
- **Migration rule:** keep REF; add the UNIQUE guard; rubric criteria versioned with a tool — archiving a tool keeps historical rubric content. [V]

### 13. `assessments`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** NO `academic_year_id` at all (year only recoverable through `term_id`, and 18 orphan NULL-year terms make that ambiguous); `class_id`/`subject_id`/`term_id`/`strand_id`/`sub_strand_id`/`coverage_id`/`assigned_by`/`approved_by` index-only, not FKs; `subject_id→curriculum_units` orphaned
- **Target home(s):** `assessments (id, academic_year_class_stream_id, academic_year_term_id, academic_year_calendar_day_id?, learning_area_id, strand_id?, sub_strand_id?, coverage_id→academic_year_class_learning_areas, assessment_type_id, title, max_marks, assessment_date, assigned_by, approved_by, status)` — §4.4, dates resolve via the calendar
- **Composite / relation key:** `(academic_year_term_id, academic_year_class_stream_id, learning_area_id, academic_year_calendar_day_id, title)`
- **Migration rule:** add `academic_year_id` FK derived from the term; re-anchor `class_id`+`stream_id`→`academic_year_class_stream_id`; map `assessment_date` → `academic_year_calendar_day_id` (undeterminable = NULL + flag); repoint `subject_id`→`learning_area_id`; promote the MUL context columns to real FKs; rows on orphan terms → `[U]` flag. [I]

### 14. `assessment_tools`
- **Disposition:** REUSE
- **Normalization fault(s):** `learning_area_id`/`created_by` index-only, not FKs
- **Target home(s):** `assessment_tools (tool_code UNIQUE, tool_name, description, assessment_type_id, learning_area_id, grade_level, competencies_assessed, file_url, status)`
- **Composite / relation key:** `tool_code` UNIQUE — curriculum-versioned content, stable across years
- **Migration rule:** keep REF; add FKs `learning_area_id→learning_areas.id`, `created_by→users.id`; `deprecated` status hides the tool from new use while keeping history. [V]

### 15. `assessment_type_classifications`
- **Disposition:** REUSE
- **Normalization fault(s):** none — pure REF (KNEC-portal code dictionary)
- **Target home(s):** `assessment_type_classifications`
- **Composite / relation key:** `code` UNIQUE
- **Migration rule:** keep REF; no year context; `is_national`/`is_knec_managed` preserved verbatim. [V]

### 16. `assessment_types`
- **Disposition:** REUSE
- **Normalization fault(s):** none — REF (formative/summative split)
- **Target home(s):** `assessment_types`
- **Composite / relation key:** `name` UNIQUE
- **Migration rule:** keep REF; align `is_formative`/`is_summative` with the CBC competency model; no year context. [V]

### 17. `class_curriculum_coverages`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** double FK `class_stream_id`/`stream_id` both →`class_streams` (redundant); binds to class/stream masters rather than the year instance
- **Target home(s):** `academic_year_class_learning_areas (id, academic_year_class_id, learning_area_id, strand_id?, sub_strand_id?, status, planned_weeks, notes)` — the coverage hub of §4.5; teacher responsibility lives in `academic_year_class_learning_area_teachers`
- **Composite / relation key:** `(academic_year_class_id, learning_area_id, strand_id?, sub_strand_id?)` — one coverage row per year-class-learning-area(-strand)
- **Migration rule:** re-home `class_id`+`stream_id`→`academic_year_class_id` (via `academic_year_classes`); drop the redundant `class_stream_id`; `teacher_id`→`academic_year_class_learning_area_teachers` (term-scoped, undeterminable term = NULL + flag); `term_id`→`academic_year_term_id` where term-scoped; backfill 2026 (year 5) rows. [V]

### 18. `class_enrollments`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** `class_id`/`stream_id` duplicate the placement already given by `class_assignment_id→class_year_assignments`; promotion-target fields duplicate `student_promotions`; stored averages/ranks/attendance drift
- **Target home(s):** `student_academic_enrollments (id, student_id, academic_year_id, academic_year_class_stream_id, enrollment_status, enrollment_date, UNIQUE(student_id, academic_year_id))` — the enrollment-history spine
- **Composite / relation key:** `UNIQUE(student_id, academic_year_id)` — one enrollment per student per year; a 2027 move appends a new row and never mutates the 2026 row
- **Migration rule:** keep as THE canonical enrollment table (61 rows in year 5); re-home `class_id`+`stream_id`→`academic_year_class_stream_id`; move promotion-target fields to `student_transitions` (single writer); drop stored averages/ranks/attendance (derive from `term_consolidations`/attendance); seed 2027 rows via the promotion process. [V]

### 19. `classes`
- **Disposition:** SPLIT
- **Normalization fault(s):** master + year coupling — `academic_year year(4)` + `status` + `capacity` + `teacher_id` belong to the year context, not the master; `uk_name_year`-style duplicates (Grade1 id14) exist
- **Target home(s):** `classes (id, code UNIQUE, name UNIQUE, level_id, grade_level)` master + year context → `academic_year_classes`
- **Composite / relation key:** `name` + `level_id` — 12 canonical classes (ids 5,12,13,6,7,2,8,9,1,10,11,4)
- **Migration rule:** strip `academic_year`/`status` into `academic_year_classes`; `capacity`/`teacher_id`/`room_number` → `academic_year_class_streams` (stream-level room + class teacher); merge id14→id6 and remap all 19 FK families; add `code UNIQUE`; never re-number. [V]

### 20. `class_promotion_queue`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** no `academic_year_id` (only via `batch_id`); stored per-class counts can drift from the batch totals
- **Target home(s):** `class_promotion_queue (batch_id, academic_year_class_stream_id, approval_status, assigned_to_user_id, notes, reviewed_at)`
- **Composite / relation key:** `(batch_id, academic_year_class_stream_id)` — one queue row per batch-class-stream approval step
- **Migration rule:** add `academic_year_id` FK inherited from the batch; re-home `class_id`+`stream_id`→`academic_year_class_stream_id`; make counts derived; approval-state transitions append-only. [I]

### 21. `class_streams`
- **Disposition:** SPLIT
- **Normalization fault(s):** stream-name + capacity + teacher without year wiring — a stream master carrying current-context columns; `current_students` denormalized count
- **Target home(s):** `streams (id, name UNIQUE, code UNIQUE, capacity)` master + `academic_year_class_streams` context
- **Composite / relation key:** `(class_id, stream_name)` — a stream is a stable sub-entity of a class; its year-instance lives in `academic_year_class_streams` (13 class-named streams + junk ids 15/17)
- **Migration rule:** collapse to the `streams` master; `UNIQUE(class_id, stream_name)` → promote `name`/`code` to global UNIQUE (A/B/C or single); year wiring moves to `academic_year_class_streams`; quarantine junk ids 15/17; drop `current_students` (derive). [V]

### 22. `class_year_assignments`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** `fee_structure_id`/`room_number`/`capacity`/`current_enrollment`/`teacher_id` mix instance-level and derived values; `current_enrollment` stored
- **Target home(s):** `academic_year_classes (id, academic_year_id, class_id, status, UNIQUE(academic_year_id, class_id))` + `academic_year_class_streams (id, academic_year_class_id, stream_id, room_id?, class_teacher_id?, capacity?, status, UNIQUE(academic_year_class_id, stream_id))`
- **Composite / relation key:** `UNIQUE(academic_year_id, class_id, stream_id)` — the guide's `classes → academic_year_classes → academic_year_class_streams` split of this table
- **Migration rule:** split rows into the two context layers; `teacher_id`→`class_teacher_id` and `room_number`→`room_id` on `academic_year_class_streams` (each stream has its own room + class teacher); `capacity`→`academic_year_class_streams`; `fee_structure_id`→ the year-class fee schedule; `current_enrollment` derived from `student_academic_enrollments`; seed 2027 rows during rollover (never mutate 2026). [V]

### 23. `conduct_tracking`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** denormalized `academic_year year(4)` duplicates `term_id`; `student_id` not enrollment-bound
- **Target home(s):** `conduct_tracking (student_academic_enrollment_id, academic_year_term_id, conduct_rating, conduct_comments, behavior_incidents, teacher_notes, recorded_by, recorded_date)`
- **Composite / relation key:** `UNIQUE(student_academic_enrollment_id, academic_year_term_id)` — one conduct record per student per term
- **Migration rule:** re-home `student_id`→`student_academic_enrollment_id`; drop the `academic_year` column (derive via `term_id`); keep `behavior_incidents` append-only detail. [I]

### 24. `formative_scores`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** `assessment_id`/`student_id` index-only; `entered_by` not FK-bound
- **Target home(s):** `formative_scores (assessment_id, student_academic_enrollment_id, score, max_score, percentage, cbc_grade, remarks, entered_by, created_at)`
- **Composite / relation key:** `(assessment_id, student_academic_enrollment_id, created_at)` — formative attempts are additive (re-assessment appends)
- **Migration rule:** add FKs `assessment_id→assessments.id`, `entered_by→users.id`; re-home `student_id` via enrollment id-map; keep append-only (no UNIQUE on the pair) so re-assessment history survives. [I]

### 25. `grade_rules`
- **Disposition:** REUSE
- **Normalization fault(s):** `UNIQUE(scale_id, grade_code)` not declared; bands could overlap
- **Target home(s):** `grade_rules (scale_id, grade_code, grade_name, min_mark, max_mark, grade_points, performance_level, sort_order)`
- **Composite / relation key:** `UNIQUE(scale_id, grade_code)`
- **Migration rule:** keep REF; add the UNIQUE guard; bands must tile `min_mark..max_mark` without gaps; align `performance_level` with `performance_levels_cbc`. [V]

### 26. `grading_comments`
- **Disposition:** REUSE
- **Normalization fault(s):** none — REF lookup
- **Target home(s):** `grading_comments`
- **Composite / relation key:** `grade_code` UNIQUE
- **Migration rule:** keep REF; extend to per-subject/per-year comments only if the product needs them; no context columns. [V]

### 27. `grading_scales`
- **Disposition:** REUSE
- **Normalization fault(s):** none as REF; in-place edits of a used scale would rewrite history
- **Target home(s):** `grading_scales`
- **Composite / relation key:** `name` UNIQUE
- **Migration rule:** keep REF; version a new scale row per curriculum change instead of editing the old one; no year context. [V]

### 28. `learner_competencies`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** denormalized `academic_year year(4)`; `student_id` not enrollment-bound; `UNIQUE(student_id, competency_id, term_id)` not declared
- **Target home(s):** `learner_competencies (student_academic_enrollment_id, competency_id, academic_year_term_id, performance_level_id, evidence, teacher_notes, assessed_by, assessed_date)`
- **Composite / relation key:** `UNIQUE(student_academic_enrollment_id, competency_id, academic_year_term_id)`
- **Migration rule:** re-home `student_id` via enrollment id-map; drop `academic_year` (derive via term); add the UNIQUE guard; keep `evidence` append-only. [I]

### 29. `national_exam_results`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** `student_id`/`academic_year_id`/`exam_year` index-only (no FKs); `learning_area_id` not FK-bound
- **Target home(s):** `national_exam_results (student_academic_enrollment_id, exam_type, academic_year_id, exam_year, learning_area_id, score, max_score, percentage, cbc_grade, raw_grade, points, pathway, entered_by)`
- **Composite / relation key:** `UNIQUE(student_academic_enrollment_id, exam_type, exam_year, learning_area_id)`
- **Migration rule:** add FKs `academic_year_id→academic_years.id`, `learning_area_id→learning_areas.id`, `entered_by→users.id`; re-home `student_id` via enrollment id-map; `pathway` feeds senior-school placement. [I]

### 30. `performance_levels_cbc`
- **Disposition:** REUSE
- **Normalization fault(s):** none — REF (EE/ME/AE/BE band set)
- **Target home(s):** `performance_levels_cbc`
- **Composite / relation key:** `level` UNIQUE
- **Migration rule:** keep REF; align `mark_range` with `grade_rules` bands; no year context. [V]

### 31. `portfolio_artifacts`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** `competency_id`/`value_id`/`media_id` FKs are fine; `media_id` ON DELETE SET NULL orphans the link on media delete; **no lesson binding** — an artifact cannot be traced to the lesson/date it was produced in
- **Target home(s):** `portfolio_artifacts (id, portfolio_id, lesson_plan_id?, artifact_title, artifact_type, file_path, media_id?, description, upload_date, learner_reflection, teacher_feedback, competency_id, value_id, rating, UNIQUE(portfolio_id, lesson_plan_id?, artifact_title))`
- **Composite / relation key:** `(portfolio_id, lesson_plan_id?, artifact_title)` — lesson_plan_id binds the artifact to date→week→term→learning area (NULL = portfolio-level artifact)
- **Migration rule:** keep SCTX child; student/year context resolved through `portfolios` (do not add a year here); backfill `lesson_plan_id` from `upload_date` where the artifact maps to a taught lesson (undeterminable = NULL + flag); keep `file_path` immutable; keep `media_id` nullable. [V]

### 32. `portfolios`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** `academic_year year(4)` not FK-bound; `student_id` not enrollment-bound
- **Target home(s):** `portfolios (student_academic_enrollment_id, academic_year_id, portfolio_type, title, theme, description, status)`
- **Composite / relation key:** `UNIQUE(student_academic_enrollment_id, academic_year_id, portfolio_type)` — one portfolio per student per year
- **Migration rule:** re-home `student_id` via enrollment id-map; `academic_year`→`academic_year_id`; add the UNIQUE guard; artifacts cascade on delete. [I]

### 33. `promotion_batches`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** `from_academic_year`/`to_academic_year` as year(4) duplicates, not FKs; stored processed/promoted/rejected counts drift
- **Target home(s):** `promotion_batches (from_academic_year_id, to_academic_year_id, batch_type, batch_scope, status, created_by, created_at, completed_at, notes)`
- **Composite / relation key:** `(from_academic_year_id, to_academic_year_id, created_at)` — one run of the promotion engine
- **Migration rule:** convert from/to year(4)→`academic_year_id` FKs via the id-map; counts derived; tie the batch to `academic_year_rollover_log` for the year. [I]

### 34. `promotion_rules`
- **Disposition:** REUSE
- **Normalization fault(s):** none as REF; a mid-year edit re-scopes every cohort
- **Target home(s):** `promotion_rules`
- **Composite / relation key:** `level_name`
- **Migration rule:** keep REF; add `effective_from_academic_year_id` FK only if rules genuinely diverge per year, otherwise keep as level definitions; no versionless in-place edits of used rules. [V]

### 35. `student_promotions`
- **Disposition:** MERGE
- **Normalization fault(s):** duplicates promotion logic in `class_enrollments.promotion_status`/`promoted_to_class_id`; many context columns index-only (no FKs); year(4) duplicates alongside `academic_year_id`
- **Target home(s):** `student_transitions (id, student_id, from_student_academic_enrollment_id?, to_student_academic_enrollment_id?, academic_year_id, transition_type, reason, decided_by, decided_at, executed_at)` — the single promotion/retention/transfer/graduation writer
- **Composite / relation key:** `(student_id, from_academic_year_id, to_academic_year_id)` — one transition event per student per move; the enrollment-id chain gives full history
- **Migration rule:** merge promotion-target fields here and strip them from `student_academic_enrollments`; `transition_type` = promoted/retained/transferred/graduated/withdrawn/suspended; approval workflow fields (`approved_by`/`approval_date`/`rejection_reason`) stay append-only; add real FKs. [I]

### 36. `term_consolidations`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** `academic_year year(4)` duplicates `term_id`; `student_id` not enrollment-bound; stored rank/percentile can drift
- **Target home(s):** `term_consolidations (student_academic_enrollment_id, academic_year_term_id, avg_overall_percentage, avg_overall_grade, class_position, percentile, points_total, best/worst learning_area, consolidated_by)`
- **Composite / relation key:** `UNIQUE(student_academic_enrollment_id, academic_year_term_id)` — one consolidation per student per term
- **Migration rule:** re-home `student_id` via enrollment id-map; drop `academic_year` (derive via term); recompute from `term_subject_scores` at backfill; add the UNIQUE guard. [I]

### 37. `term_subject_scores`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** `subject_id`→`curriculum_units` orphaned; `student_id`/`term_id`/`subject_id` index-only; no UNIQUE guard
- **Target home(s):** `term_subject_scores (student_academic_enrollment_id, academic_year_term_id, learning_area_id, formative_*, summative_*, overall_score, overall_percentage, overall_grade, overall_points, calculated_at)`
- **Composite / relation key:** `UNIQUE(student_academic_enrollment_id, academic_year_term_id, learning_area_id)` — one aggregate per subject per term
- **Migration rule:** re-home `student_id` via enrollment id-map; repoint `subject_id`→`learning_area_id` via the learning-area id-map; recompute deterministically from `assessment_results` + `formative_scores`. [I]

### 38. `term_transition_log`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** `from_term_id`/`to_term_id`/`academic_year_id`/`performed_by` index-only (no FKs); orphan terms make `from/to_term_id` ambiguous
- **Target home(s):** `term_transition_log` (HIST — append-only term-transition audit)
- **Composite / relation key:** `performed_at` + `action`
- **Migration rule:** add FKs `from_term_id→academic_year_terms.id`, `to_term_id→academic_year_terms.id`, `academic_year_id→academic_years.id`, `performed_by→users.id`; append-only; rows touching orphan terms → `[U]` flag. [I]

### 39. `vw_active_students_per_class`
- **Disposition:** RETIRE
- **Normalization fault(s):** misnamed summary base table (`CREATE TABLE` with a `vw_` prefix); denormalized `class_id`/`stream_id`/`active_students` with NO year column; point-in-time snapshot that cannot reconcile with `class_enrollments` (61 rows in year 5)
- **Target home(s):** real VIEW `vw_active_students_per_class` over `student_academic_enrollments` + `academic_year_class_streams` + `classes` + `streams`, keyed by `academic_year_id`
- **Composite / relation key:** `(academic_year_id, class_id, stream_id)` view key
- **Migration rule:** no data backfill; drop the base table and create the VIEW; bind the year via `academic_year_id` from the enrollment instance, never `curdate()` (23-view time-bomb rule). [V]
