# Normalization mapping — Curriculum & Learning Areas (28 tables)

Base evidence: `docs/database_audit/08_PER_TABLE_BREAKDOWN/02_curriculum_learning_areas.md`, `/tmp/opencode/domains/domain_02.txt`, `docs/database_audit/09_NORMALIZED_TARGET_ARCHITECTURE.md` §4.5.

Target of truth: file 09, following the guide chain `learning_areas → academic_year_class_learning_areas → academic_year_class_learning_area_teachers`. Curriculum content (`strands`, `sub_strands`, `core_competencies`, `core_values`, `pcis`, …) is NATIONAL REFERENCE — a read-only master the school never edits; school implementation is the per-year selection in `academic_year_class_learning_areas` (see §4.5). Legacy tables are mapped ONTO the target; nothing is re-designed here. No invented values: any legacy cell that cannot be re-homed is migrated as `NULL` with a flag. Year context follows the `academic_years` id-map (2026 = id 5); `academic_year year(4)` on legacy tables becomes `academic_year_id` throughout. Tags: `[V]` column/row evidence verified in slice + 08, `[I]` migration relies on inferred links (FK/id-map), `[U]` genuinely undeterminable — migrated `NULL` + flag.

### 1. `assignments`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** `academic_year_id`/`term_id` plain columns, `class_id`/`teacher_id`/`learning_area_id`/`strand_id`/`sub_strand_id`/`coverage_id` index-only — no FKs; `subject_id→curriculum_units` orphaned (empty table)
- **Target home(s):** `assignments (id, academic_year_class_learning_area_id, academic_year_calendar_day_id?, strand_id?, sub_strand_id?, teacher_id, title, total_marks, due_date, status)` — §4.4
- **Composite / relation key:** `(academic_year_class_learning_area_id, academic_year_calendar_day_id?, title)` — a published assignment within a learning-area context
- **Migration rule:** re-anchor `class_id`+`stream_id`+`academic_year_id`+`term_id`+`learning_area_id`→`academic_year_class_learning_area_id`; repoint `subject_id`→`learning_area_id`; `due_date` → `academic_year_calendar_day_id` (undeterminable = NULL + flag); promote all MUL context columns to real FKs; `coverage_id`→`academic_year_class_learning_areas.id` as the year+class+strand anchor. [I]

### 2. `assignment_submissions`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** `assignment_id`/`student_id`/`graded_by` index-only (no FKs); re-submission policy not encoded
- **Target home(s):** `assignment_submissions (assignment_id, student_academic_enrollment_id, submission_text, submitted_at, marks_awarded, grade, feedback, graded_by, status)`
- **Composite / relation key:** `(assignment_id, student_academic_enrollment_id, submitted_at)` — re-submissions append
- **Migration rule:** add FKs `assignment_id→assignments.id`, `graded_by→staff.id`; re-home `student_id`→`student_academic_enrollment_id` via the enrollment id-map; keep attempts append-only (UNIQUE only if the policy is single-submission). [I]

### 3. `core_competencies`
- **Disposition:** REUSE
- **Normalization fault(s):** `code` not declared UNIQUE
- **Target home(s):** `core_competencies` (national REF — stable CBC competency definition)
- **Composite / relation key:** `code` UNIQUE
- **Migration rule:** keep REF; promote `code` to declared UNIQUE; version via `grade_range`; no year context. [V]

### 4. `core_values`
- **Disposition:** REUSE
- **Normalization fault(s):** `code` not declared UNIQUE; overlaps `sub_strand_values.value_name` value dictionary
- **Target home(s):** `core_values` (national REF)
- **Composite / relation key:** `code` UNIQUE
- **Migration rule:** keep REF; promote `code` to declared UNIQUE; optionally FK `sub_strand_values.value_name→core_values.code` to dedupe the value dictionary; no year context. [V]

### 5. `curriculum_units`
- **Disposition:** RETIRE
- **Normalization fault(s):** legacy unit layer replaced by strands/sub-strands; 0 rows; still referenced by 4 orphaned FK families (`assessment_benchmarks.subject_id`, `class_schedules.subject_id`, `past_papers.subject_id`, `teaching_materials.subject_id`)
- **Target home(s):** none — the unit layer is not part of the target; children repoint to `learning_areas`/`strands`
- **Composite / relation key:** n/a (empty table)
- **Migration rule:** quarantine the empty table to the migration snapshot; repoint all 4 `subject_id` children → `learning_area_id`; never DELETE while children reference it — retire in the same migration step that repoints the children. [V]

### 6. `learner_csl_participation`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** `academic_year year(4)` not FK-bound; `student_id` not enrollment-bound; `UNIQUE(student_id, csl_activity_id, academic_year)` not declared
- **Target home(s):** `learner_csl_participation (student_academic_enrollment_id, csl_activity_id, academic_year_id, hours_contributed, role, reflection, teacher_feedback, participation_status)`
- **Composite / relation key:** `UNIQUE(student_academic_enrollment_id, csl_activity_id, academic_year_id)` — one participation per student per CSL activity per year
- **Migration rule:** re-home `student_id` via enrollment id-map; `academic_year`→`academic_year_id`; add the UNIQUE guard; activity deletion keeps participation history (CASCADE reviewed). [I]

### 7. `learner_pci_awareness`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** denormalized `academic_year year(4)`; `student_id` not enrollment-bound; no UNIQUE guard
- **Target home(s):** `learner_pci_awareness (student_academic_enrollment_id, pci_id, academic_year_term_id, awareness_level, evidence, assessed_by, assessed_date)`
- **Composite / relation key:** `UNIQUE(student_academic_enrollment_id, pci_id, academic_year_term_id)` — one PCI-awareness assessment per student per term
- **Migration rule:** re-home `student_id` via enrollment id-map; drop `academic_year` (derive via term); add the UNIQUE guard; orphan-term rows → `[U]` flag. [I]

### 8. `learner_values_acquisition`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** denormalized `academic_year year(4)`; `student_id` not enrollment-bound
- **Target home(s):** `learner_values_acquisition (student_academic_enrollment_id, value_id, academic_year_term_id, evidence, incident_date, recorded_by)`
- **Composite / relation key:** `(student_academic_enrollment_id, value_id, academic_year_term_id, incident_date)` — evidence rows are append-only
- **Migration rule:** re-home `student_id` via enrollment id-map; drop `academic_year` (derive via term); keep append-only — do NOT force UNIQUE(student, value, term) since multiple incidents per term are valid. [I]

### 9. `learning_areas`
- **Disposition:** REUSE
- **Normalization fault(s):** `code` is [MUL] indexed but not declared UNIQUE; `strands.learning_area_id` is not an FK
- **Target home(s):** `learning_areas (id, code UNIQUE, name, level_band, description, source_documents, levels, is_optional, status)` — the subject-of-record for CBC (33 rows)
- **Composite / relation key:** `code` UNIQUE — stable curriculum master; school implementation = per-year selection, never edits this
- **Migration rule:** keep MASTER; promote `code` to declared UNIQUE; make `strands.learning_area_id` a real FK; route the `curriculum_units` orphans onto it. [V]

### 10. `learning_outcomes`
- **Disposition:** REUSE
- **Normalization fault(s):** `learning_area_id`/`strand_id`/`sub_strand_id` index-only (no FKs)
- **Target home(s):** `learning_outcomes` (national REF content)
- **Composite / relation key:** `(learning_area_id, strand_id, sub_strand_id, outcome)`
- **Migration rule:** keep REF; add FKs to `learning_areas`/`strands`/`sub_strands`; no year context; re-parenting an outcome breaks assessment links — never re-number. [V]

### 11. `lesson_observations`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** NO year/term columns at all — observation cannot be attributed to a term without guessing; `class_id`/`stream_id` bind to masters
- **Target home(s):** `lesson_observations (teacher_id, intern_id, observer_id, academic_year_class_stream_id, academic_year_term_id, observation_date, rating, strengths, areas_for_improvement, recommendations)`
- **Composite / relation key:** `UNIQUE(teacher_id, observer_id, observation_date, academic_year_class_stream_id)`
- **Migration rule:** add `academic_year_id`/`academic_year_term_id` FKs derived from `observation_date`→term; re-home `class_id`+`stream_id`→`academic_year_class_stream_id`; rows whose date maps to no term → `[U]` flag. [I]

### 12. `lesson_plans`
- **Disposition:** SPLIT
- **Normalization fault(s):** `unit_id`/`strand_id`/`sub_strand_id`/`coverage_id`/`term_id`/`academic_year_id` index-only (no FKs); `unit_id→curriculum_units` orphaned; `teacher_id`/`approved_by` bind to staff master; `objectives` free-text instead of a selection from the national REF; `lesson_date` not resolvable to a term week without the calendar; content (activities/resources/objectives) duplicated per year-instance — no reusable content layer, cross-year reuse requires manual copy
- **Target home(s):** `lesson_templates (id, learning_area_id, strand_id, sub_strand_id, title, duration, activities, resources, assessment, homework, created_by, is_shared, status, UNIQUE(learning_area_id, strand_id, sub_strand_id, title))` [NEW] + `lesson_template_learning_outcomes (lesson_template_id, learning_outcome_id, PK(...))` [NEW] + `lesson_plans (id, lesson_template_id, academic_year_class_learning_area_id, academic_year_calendar_day_id, teacher_id, status, approved_by, UNIQUE(academic_year_class_learning_area_id, academic_year_calendar_day_id, lesson_template_id))` + `lesson_deliveries (id, lesson_plan_id, delivered_on, delivered_by, taught_duration, outcomes_met, outcomes_total, follow_up_notes, UNIQUE(lesson_plan_id, delivered_on))` [NEW] — §4.4
- **Composite / relation key:** `UNIQUE(academic_year_class_learning_area_id, academic_year_calendar_day_id, lesson_template_id)` — one plan per taught day per template; the calendar day fixes date→week→term
- **Migration rule:** split each legacy row 1:1 into a `lesson_templates` content row (activities/resources; free-text `objectives` → `lesson_template_learning_outcomes` rows referencing `learning_outcomes.id`, unmatched text → NULL + flag) and a year-instance `lesson_plans` row re-keyed to the template; re-anchor `class_id`+`term_id`+`academic_year_id`+`learning_area_id` → `academic_year_class_learning_area_id`; map `lesson_date` → `academic_year_calendar_day_id` (undeterminable = NULL + flag); repoint `unit_id`→`strands`; no delivery history exists → `lesson_deliveries` starts empty, backfilled only where a `completed` status gives a reliable taught outcome count; `vw_lesson_plan_summary` becomes its projection view; keep the approval workflow append-only. [I]

### 13. `past_papers`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** `subject_id→curriculum_units` orphaned (empty table); `UNIQUE(exam_year, exam_type, learning_area_id, title)` not declared
- **Target home(s):** `past_papers (academic_year_id, academic_year_term_id?, learning_area_id, exam_type, exam_year, title, file_url, uploaded_by, uploaded_at)`
- **Composite / relation key:** `UNIQUE(exam_year, exam_type, learning_area_id, title)` — one paper per exam per learning-area
- **Migration rule:** repoint `subject_id`→`learning_area_id`; add the UNIQUE guard; keep file metadata; quarantine files for deleted years. [I]

### 14. `pcis`
- **Disposition:** REUSE
- **Normalization fault(s):** `topic_code` not declared UNIQUE; `learning_area_id` not FK-bound
- **Target home(s):** `pcis` (national REF — Pertinent & Contemporary Issues definitions)
- **Composite / relation key:** `topic_code` UNIQUE
- **Migration rule:** keep REF; promote `topic_code` to declared UNIQUE; add FK `learning_area_id→learning_areas.id`; no year context. [V]

### 15. `schemes_of_work`
- **Disposition:** SPLIT
- **Normalization fault(s):** `subject_id`/`subject_name` denormalization duplicates the learning-area mapping; context columns index-only (no FKs); `week_number` is a bare ordinal with no term dates — it cannot answer "what dates is week 3 of Term 1?"; free-text `strand`/`sub_strand` duplicate the REF; `learning_outcomes` is free-text instead of a selection from the national REF; content duplicated per year-instance — no reusable content layer
- **Target home(s):** `scheme_templates (id, learning_area_id, strand_id, sub_strand_id, title, activities, resources, assessment_methods, created_by, is_shared, status, UNIQUE(learning_area_id, strand_id, sub_strand_id, title))` [NEW] + `scheme_template_learning_outcomes (scheme_template_id, learning_outcome_id, PK(...))` [NEW] + `schemes_of_work (id, scheme_template_id, academic_year_class_learning_area_id, academic_year_calendar_week_id, teacher_id, status, approved_by, UNIQUE(academic_year_class_learning_area_id, academic_year_calendar_week_id, scheme_template_id))` — §4.4
- **Composite / relation key:** `UNIQUE(academic_year_class_learning_area_id, academic_year_calendar_week_id, scheme_template_id)` — one scheme row per week per template; the calendar week fixes the exact dates
- **Migration rule:** split each legacy row 1:1 into a `scheme_templates` content row (activities/resources; free-text `learning_outcomes` → `scheme_template_learning_outcomes` rows referencing `learning_outcomes.id`, unmatched text → NULL + flag) and a year-instance `schemes_of_work` row re-keyed to the template; re-anchor `class_id`+`stream_id`+`academic_year_id`+`term_id`+`learning_area_id` → `academic_year_class_learning_area_id`; resolve `week_number` + term → `academic_year_calendar_week_id` (undeterminable = NULL + flag); drop `subject_name`, `strand`, `sub_strand` text (derive from REF); repoint `subject_id`→`learning_area_id`; promote all context columns to real FKs. [I]

### 16. `strand_competency`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** `strand_id`/`competency_id` index-only (no FKs); `UNIQUE(strand_id, competency_id)` not declared
- **Target home(s):** `strand_competency (strand_id, competency_id, weight)` (national REF junction, weight-bearing)
- **Composite / relation key:** `UNIQUE(strand_id, competency_id)`
- **Migration rule:** keep JXN; add FKs `strand_id→strands.id`, `competency_id→core_competencies.id`; add the UNIQUE guard; `weight` feeds assessment/benchmark computations. [V]

### 17. `strands`
- **Disposition:** REUSE
- **Normalization fault(s):** `learning_area_id` index-only (no FK); `UNIQUE(learning_area_id, grade_level, code)` not declared
- **Target home(s):** `strands (learning_area_id, grade_level, code, name, variant, source_subject, source_document, source_page, is_optional, sort_order, status)` (national REF — 482 rows)
- **Composite / relation key:** `UNIQUE(learning_area_id, grade_level, code)`
- **Migration rule:** keep REF; add FK `learning_area_id→learning_areas.id`; add the UNIQUE guard; never re-number. [V]

### 18. `subject_time_allocations`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** `subject_id`/`subject_name` denormalization; context columns index-only (no FKs); UNIQUE not declared
- **Target home(s):** `subject_time_allocations (academic_year_class_stream_id, academic_year_term_id, learning_area_id, periods_per_week, teacher_id, is_active)`
- **Composite / relation key:** `UNIQUE(academic_year_term_id, academic_year_class_stream_id, learning_area_id)` — one allocation per learning-area per class per term
- **Migration rule:** repoint `subject_id`→`learning_area_id`, drop `subject_name` (derive); re-anchor `class_id`+`stream_id`→`academic_year_class_stream_id`; add the UNIQUE guard. [I]

### 19. `sub_strand_competencies`
- **Disposition:** REUSE
- **Normalization fault(s):** `UNIQUE(sub_strand_id, competency_id)` not declared
- **Target home(s):** `sub_strand_competencies (sub_strand_id, competency_id, weight, source_document, source_page)` (national REF junction)
- **Composite / relation key:** `UNIQUE(sub_strand_id, competency_id)`
- **Migration rule:** keep JXN; add the UNIQUE guard; assessment weighting cascades on either side. [V]

### 20. `sub_strand_key_inquiry_questions`
- **Disposition:** REUSE
- **Normalization fault(s):** `UNIQUE(sub_strand_id, sort_order)` not declared
- **Target home(s):** `sub_strand_key_inquiry_questions` (national REF content)
- **Composite / relation key:** `UNIQUE(sub_strand_id, sort_order)`
- **Migration rule:** keep REF; add the UNIQUE guard; cascades on sub-strand re-ID (never re-number). [V]

### 21. `sub_strand_pci_issues`
- **Disposition:** REUSE
- **Normalization fault(s):** `UNIQUE(sub_strand_id, pci_id)` not declared
- **Target home(s):** `sub_strand_pci_issues (sub_strand_id, pci_id, note, sort_order)` (national REF junction)
- **Composite / relation key:** `UNIQUE(sub_strand_id, pci_id)`
- **Migration rule:** keep JXN; add the UNIQUE guard; `note`/`sort_order` are junction attributes. [V]

### 22. `sub_strand_rubrics`
- **Disposition:** REUSE
- **Normalization fault(s):** `UNIQUE(sub_strand_id, level_number)` not declared
- **Target home(s):** `sub_strand_rubrics` (national REF content, distinct from the tool-level `assessment_rubrics` family)
- **Composite / relation key:** `UNIQUE(sub_strand_id, level_number)`
- **Migration rule:** keep REF; add the UNIQUE guard; align `level_label`/`descriptor` with `performance_levels_cbc`. [V]

### 23. `sub_strands`
- **Disposition:** REUSE
- **Normalization fault(s):** `strand_id` index-only (no FK); `UNIQUE(strand_id, grade_level, code)` not declared
- **Target home(s):** `sub_strands (strand_id, grade_level, code, name, variant, source_subject, description, sort_order, status)` (national REF — 2109 rows)
- **Composite / relation key:** `UNIQUE(strand_id, grade_level, code)`
- **Migration rule:** keep REF; add FK `strand_id→strands.id`; add the UNIQUE guard; never re-number (7 FK families depend on it). [V]

### 24. `sub_strand_suggested_experiences`
- **Disposition:** REUSE
- **Normalization fault(s):** `UNIQUE(sub_strand_id, sort_order)` not declared
- **Target home(s):** `sub_strand_suggested_experiences` (national REF content)
- **Composite / relation key:** `UNIQUE(sub_strand_id, sort_order)`
- **Migration rule:** keep REF; add the UNIQUE guard; cascades on sub-strand re-ID (never re-number). [V]

### 25. `sub_strand_values`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** `value_name` duplicates the `core_values` dictionary; `UNIQUE(sub_strand_id, sort_order)` not declared
- **Target home(s):** `sub_strand_values (sub_strand_id, value_name→core_values.code?, description, sort_order, source_document, source_page)` (national REF content)
- **Composite / relation key:** `UNIQUE(sub_strand_id, sort_order)`
- **Migration rule:** keep REF; optionally FK `value_name→core_values.code` to dedupe the value dictionary; add the UNIQUE guard. [V]

### 26. `teaching_materials`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** `subject_id→curriculum_units` orphaned (empty table); `uploaded_by` not FK-bound; file metadata not FK-linked to media
- **Target home(s):** `teaching_materials (academic_year_id, academic_year_term_id, academic_year_class_stream_id, learning_area_id, teacher_id, resource_type, access_scope, status, file_url, file_size, uploaded_by, uploaded_at)`
- **Composite / relation key:** `(academic_year_term_id, academic_year_class_stream_id, learning_area_id, title, uploaded_at)` — a material scoped to a class/term
- **Migration rule:** repoint `subject_id`→`learning_area_id`; re-anchor `class_id`+`stream_id`→`academic_year_class_stream_id`; add FK `uploaded_by→staff.id`; keep `access_scope` private/subject/school/public; quarantine files for deleted years. [I]

### 27. `unit_topics`
- **Disposition:** RETIRE
- **Normalization fault(s):** legacy child of the legacy unit layer; `unit_id` index-only (no FK); orphaned with its parent (`curriculum_units` empty)
- **Target home(s):** none — topic content folds into strand/sub-strand content
- **Composite / relation key:** `(unit_id, name, order_sequence)` — orphaned with `curriculum_units`
- **Migration rule:** quarantine with `curriculum_units`; review content and map to strands/sub-strands or discard; do NOT backfill. [V]

### 28. `vw_lesson_plan_summary`
- **Disposition:** RETIRE
- **Normalization fault(s):** misnamed summary base table — `CREATE TABLE` with a `vw_` prefix; denormalized projection of `lesson_plans`; `unit_name`/`topic_name` become obsolete when `curriculum_units` is dropped
- **Target home(s):** real VIEW `vw_lesson_plan_summary` over `lesson_plans` joined to `staff`/`learning_areas`/`classes`/`streams`
- **Composite / relation key:** `id` mirrors `lesson_plans.id` — derived, not a source of truth
- **Migration rule:** no data backfill; drop the base table and create the VIEW; resolve `unit_name` to strand/sub-strand names; bind the year via `academic_year_id`, never `curdate()` (23-view time-bomb rule). [V]
