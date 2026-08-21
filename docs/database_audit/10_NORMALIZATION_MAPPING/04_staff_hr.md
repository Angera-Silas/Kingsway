# Normalization mapping — Staff / HR

Part of `10_NORMALIZATION_MAPPING/` (all 431 legacy tables mapped to `09_NORMALIZED_TARGET_ARCHITECTURE.md`). Covers 51 tables.
Base evidence: `08_PER_TABLE_BREAKDOWN/04_staff_hr.md`, `/tmp/opencode/domains/domain_04.txt`.

Tags: `[V]` verified in dump + live DB · `[I]` reasoned inference · `[U]` owner decision required.

### 1. `careers_benefits`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** none structural — static public careers content (`icon/title/description/display_order/is_active`); no actor or year columns, nothing to attribute across years [V]
- **Target home(s):** `careers_benefits` (public careers content master, §4.11)
- **Composite / relation key:** `id`
- **Migration rule:** keep rows as-is; seed/CRUD content only, no restructuring, no FKs to declare.

### 2. `department_accounts`
- **Disposition:** MERGE
- **Normalization fault(s):** per-department money allocation is a budget/accounting fact, not a standalone entity; `department_id → departments.id` and `allocated_by → staff.id` are undeclared [I]; row is an allocation moment with no purpose/line-item context
- **Target home(s):** `budgets` / `department_fund_requests` (finance budget & accounting, §4.6) under the `departments` master (§4.11)
- **Composite / relation key:** `(department_id, allocated_at)`
- **Migration rule:** re-home each row as a department budget/allocation fact; declare `department_id` and `allocated_by` FKs; year/term derivable from `allocated_at` [I] — undeterminable leaves term NULL + flag; no invented amounts.

### 3. `department_attendance_rules`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** `UNIQUE(department_id)` single rule set is not versioned — a policy change overwrites history; no effective window [I]
- **Target home(s):** `department_attendance_rules` (§4.11, staff-attendance domain)
- **Composite / relation key:** `department_id` (+ `effective_from`/`effective_to` for versioning)
- **Migration rule:** keep rows; declare `department_id → departments.id` FK; add effective-window columns; undeterminable start = NULL + flag [U].

### 4. `department_contacts`
- **Disposition:** MERGE
- **Normalization fault(s):** mixed context — department display content (`icon/color/name/description`) fused with contact channels (`email/phone`) that belong to the contact directory [V]
- **Target home(s):** `departments` (display attributes, §4.11) + `contact_directory` (§4.14)
- **Composite / relation key:** department identity (department `code`)
- **Migration rule:** display columns (icon/color/description) → `departments`; email/phone → `contact_directory` linked to the department; undeterminable department mapping = NULL + flag.

### 5. `departments`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** `head_id` is a stored "current head" that duplicates the head role carried by `staff_department_assignments` (redundancy); no parent-hierarchy column [I]
- **Target home(s):** `departments (id, code UNIQUE, name, parent_department_id?)` (§4.11)
- **Composite / relation key:** `id` (never re-ID)
- **Migration rule:** keep `code`/`name`/`description`; add `parent_department_id`; drop `head_id` — current head resolved from `staff_department_assignments` (role=head) [I]; declare the implicit `department_accounts.department_id` FK [I].

### 6. `job_applications`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** `job_id → job_vacancies.id` is an undeclared reference [I]; applicant identity (name/email/phone) overlaps `persons` once an internal applicant maps to staff [I]
- **Target home(s):** `job_applications` (§4.11)
- **Composite / relation key:** `(job_id, applicant identity, created_at)`
- **Migration rule:** keep rows; declare `job_id → job_vacancies.id`, `current_department_id`, `staff_id` FKs; `created_at` is the immutable application moment; applicant contact duplication tolerated as a careers fact [I].

### 7. `job_vacancies`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** none structural — vacancy catalogue (`title/department/job_type/location/deadline/status`), no year or actor columns [V]
- **Target home(s):** `job_vacancies` (§4.11)
- **Composite / relation key:** `id`
- **Migration rule:** keep as catalogue; declare the implicit `job_applications.job_id` FK [I]; `status` drives the lifecycle.

### 8. `kpi_achievements`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** `academic_year` is a plain int, so the year is unattributable to `academic_years.id` [I]
- **Target home(s):** `kpi_achievements` (staff performance fact, §4.11)
- **Composite / relation key:** `(staff_id, kpi_definition_id, academic_year_id)`
- **Migration rule:** keep rows; promote `academic_year` → `academic_year_id` (2026 = id 5) [I]; undeterminable year = NULL + flag; declare `staff_id`/`kpi_definition_id` FKs.

### 9. `kpi_definitions`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** none structural — KPI code list; `staff_category_id → staff_categories.id` FK present [V]
- **Target home(s):** `kpi_definitions` (§4.11)
- **Composite / relation key:** `id` (stable definition)
- **Migration rule:** keep as definition master; `staff_category_id` retained (staff_categories kept — see #21).

### 10. `kpi_targets`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** `academic_year` plain int not an FK [I]
- **Target home(s):** `kpi_targets` (staff performance fact, §4.11)
- **Composite / relation key:** `(staff_id, kpi_definition_id, academic_year_id)`
- **Migration rule:** keep rows; promote `academic_year` → `academic_year_id` [I]; undeterminable year = NULL + flag; declare `staff_id`/`kpi_definition_id` FKs.

### 11. `leadership_team`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** public content that duplicates staff master identity (`name/title/email`) [I]; only presentational columns (bio/avatar/display_order) [V]
- **Target home(s):** content master (public site leadership profiles)
- **Composite / relation key:** `id` (or staff identity if derived)
- **Migration rule:** keep rows as public content; [U] owner decides whether to derive from `persons`/`staff` instead of duplicating — if derived, drop the physical rows and render from staff.

### 12. `leave_types`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** none structural — leave code list `UNIQUE(code)`, `days_allowed/is_paid/applicable_to` [V]; `staff_leaves.leave_type_id` is an implicit reference [I]
- **Target home(s):** `leave_types` (§4.11)
- **Composite / relation key:** `id`
- **Migration rule:** keep; declare the implicit `staff_leaves.leave_type_id` FK [I].

### 13. `performance_ratings`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** `rating_period` is a free string, so ratings are not attributable to academic years/terms [I]; `supervisor_id` FK undeclared [I]
- **Target home(s):** `performance_ratings` (performance fact keyed to period, §4.11)
- **Composite / relation key:** `(staff_id, academic_year_id, term_id)`
- **Migration rule:** keep rows; map `rating_period` → `academic_year_id`/`term_id` where derivable (2026 = ids 5/7/8/9) [I]; undeterminable = NULL + flag; declare `supervisor_id → staff.id` FK.

### 14. `performance_review_kpis`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** none structural — KPI line of a review; year/term context inherited from the parent review [V]
- **Target home(s):** `performance_review_kpis` (§4.11)
- **Composite / relation key:** `(review_id, kpi_template_id)`
- **Migration rule:** keep; declare `review_id`/`kpi_template_id` FKs; no separate year context (inherited).

### 15. `staff`
- **Disposition:** SPLIT
- **Normalization fault(s):** mixed context — person identity + employment subtype + payroll columns in one master [V]; `work_start_time/work_end_time/late_threshold_minutes` duplicate `staff_attendance_profiles` (redundancy) [I]; `staff_type_id/staff_category_id/department_id/supervisor_id/user_id` FKs undeclared [I]
- **Target home(s):** `persons` (identity, §4.1) + `staff` (employee subtype, §4.1) + `staff_attendance_profiles` (schedule dedupe, §4.11)
- **Composite / relation key:** `staff_no` (master key); `person_id UNIQUE` in target
- **Migration rule:** `persons` ← names, date_of_birth, gender, phone, address, profile_pic_url, identity numbers (nssf/kra/nhif/tsc); `staff` ← staff_no, employment_date, contract_type, status; `work_*` columns re-homed to `staff_attendance_profiles`; department binding via `staff_department_assignments`; salary/bank retained on `staff` as payroll snapshot [I]; never re-ID — ~60 FK children follow the legacy `staff.id`.

### 16. `staff_allowances`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** `allowance_type` is a free enum (not a REF) [I]; `staff_id` FK undeclared [I]
- **Target home(s):** `staff_allowances (staff_id, type_id, amount, effective_from, effective_to)` (§4.6)
- **Composite / relation key:** `(staff_id, allowance_type, effective_date)`
- **Migration rule:** keep rows; declare `staff_id → staff.id` FK; map `allowance_type` to a type REF (undeterminable mapping = NULL + flag).

### 17. `staff_appointment_approvals`
- **Disposition:** MERGE
- **Normalization fault(s):** parallel workflow trail; `appointment_id` is polymorphic (`appointment_type` internal/new) and not an FK; duplicates `audit_logs`/`workflow_history` [V]
- **Target home(s):** `workflow_history` + `audit_logs` (§4.15)
- **Composite / relation key:** `(appointment_type, appointment_id, created_at)`
- **Migration rule:** each row → one `workflow_history` entry (entity=`staff_appointments`, action, from/to status) + one `audit_logs` entry (`actor_id`, `changes_json`); append-only, never backfill.

### 18. `staff_appointments`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** candidate payload (name/phone/id/qualifications) duplicates `persons` once onboarded [I]; `recruitment_id` is an external/polymorphic ref [I]
- **Target home(s):** `staff_appointments` (§4.11)
- **Composite / relation key:** `(candidate_email, employment_date)`
- **Migration rule:** keep as hiring fact; on status `onboarded` bind to the created `staff.id` (staff_no master key) so approvals trace; candidate identity merges into `persons` on onboarding [I]; declare the 9 declared FKs.

### 19. `staff_attendance`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** PK `UNIQUE(staff_id, date, shift)` splits one staff-day across rows (redundancy); `academic_year_id` nullable; `leave_id`/`duty_type_id` mixed context; check-in columns duplicate `staff_attendance_profiles` defaults [V]
- **Target home(s):** `staff_attendance (staff_id, date, check_in, check_out, status, UNIQUE(staff_id, date))` (§4.11)
- **Composite / relation key:** `(staff_id, date)`
- **Migration rule:** collapse per staff-day; `shift` is a schedule attribute on `staff_shift_assignments`/`staff_duty_roster`; declare `academic_year_id → academic_years.id` + staff/duty_type/leave FKs; year stays nullable for backfill [I]; no overwrite.

### 20. `staff_attendance_profiles`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** none structural — `UNIQUE(staff_id)` per-staff schedule; it is the dedupe target for `staff.work_start_time/work_end_time/late_threshold_minutes` [V]
- **Target home(s):** `staff_attendance_profiles` (§4.11)
- **Composite / relation key:** `UNIQUE(staff_id)`
- **Migration rule:** keep; absorb the `staff.work_*` columns' rows; declare `staff_id → staff.id` FK.

### 21. `staff_categories`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** `staff_type_id → staff_types.id` FK undeclared [I]
- **Target home(s):** `staff_categories` (REF master, §4.11)
- **Composite / relation key:** `id`
- **Migration rule:** keep. **Decision (with #51):** `staff_types` and `staff_categories` are NOT duplicative — a type groups many categories (one-to-many); both stay as REF masters. Declare `staff_type_id → staff_types.id` FK.

### 22. `staff_child_fee_config`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** key/value config rows encode discount policy (per-child %, max deduction, priority) that finance must interpret at runtime — policy belongs to fee-discount config, not a generic KV store [I]
- **Target home(s):** `fee_discounts_waivers` config / `fee_catalog` policy (finance, §4.6)
- **Composite / relation key:** `UNIQUE(config_key)`
- **Migration rule:** keep as seeded policy config; [U] owner maps keys onto discount-policy fields; no invented values.

### 23. `staff_child_fee_deductions`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** stored `balance` duplicates derived fee state (redundancy) [V]; `staff_id`/`student_id` duplicate the `staff_children` relation (student implied by `staff_child_id`) [V]; FKs undeclared [V]
- **Target home(s):** `staff_deductions` / `payslip_items` (payroll-period deduction fact, §4.6)
- **Composite / relation key:** `(staff_child_id, payroll_month, payroll_year)`
- **Migration rule:** keep as per-period deduction fact; drop stored `balance` → derived view; declare `staff_child_id/staff_id/student_id/payslip_id/term_id` FKs; `term_id` carries the academic term.

### 24. `staff_children`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** none structural — staff↔student junction with relationship attributes (`fee_deduction_enabled/percentage`), consistent with `student_parents` [V]
- **Target home(s):** `staff_children` (junction, §4.11)
- **Composite / relation key:** `UNIQUE(staff_id, student_id)`
- **Migration rule:** keep; declare `staff_id → staff.id` and `student_id → students.id` FKs; no year context.

### 25. `staff_class_assignments`
- **Disposition:** SPLIT
- **Normalization fault(s):** `class_id`+`stream_id` duplicate the `academic_year_class_streams` instance they belong to (redundancy) [V]; `subject_id` (legacy) and `learning_area_id` duplicate each other (duplicate columns) [V]; role `head_of_department` is a department role, not a stream role [I]
- **Target home(s):** `academic_year_class_learning_area_teachers (academic_year_class_learning_area_id, academic_year_term_id, staff_id, role)` (§4.3) + `academic_year_class_learning_areas (learning_area_id)` with `periods_per_week`
- **Composite / relation key:** `(academic_year_class_learning_area_id, academic_year_term_id, staff_id, role)`
- **Migration rule:** resolve `class_id`+`stream_id`+`academic_year_id`+`learning_area_id` → `academic_year_class_learning_area_id`; add `academic_year_term_id` from the assignment's term (undeterminable = NULL + flag); role rows → `academic_year_class_learning_area_teachers`; `periods_per_week` → `academic_year_class_learning_areas`; `subject_id` dropped (map to `learning_area_id` where derivable, else NULL + flag); a 2027 assignment is a NEW row — never overwrite the 2026 rows.

### 26. `staff_communication_profiles`
- **Disposition:** SPLIT
- **Normalization fault(s):** contact profile duplicates `persons.email/phone` (redundancy) [V]; emergency-contact fields have no home on `persons` [V]
- **Target home(s):** `persons` (primary_email/primary_phone, §4.1) + **[NEW]** `emergency_contacts (person_id, name, phone, relationship?, UNIQUE(person_id, name))`
- **Composite / relation key:** staff identity → `person_id`
- **Migration rule:** `primary_email`/`primary_phone` → `persons`; `emergency_contact_name`/`emergency_contact_phone` → `emergency_contacts`; undeterminable = NULL + flag.

### 27. `staff_contracts`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** none structural — per-staff contract lifecycle (`contract_type/start/end/status`), window dates carry context; `staff_id` FK undeclared [V]
- **Target home(s):** `staff_contracts` (§4.11)
- **Composite / relation key:** `(staff_id, start_date)`
- **Migration rule:** keep; declare `staff_id → staff.id` FK; no year FK needed (window dates).

### 28. `staff_deductions`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** `deduction_type_id` undeclared [V]; `related_student_id` mixes staff-child fee deductions into general payroll deductions [I]
- **Target home(s):** `staff_deductions (staff_id, type_id, amount, effective_from, effective_to)` (§4.6)
- **Composite / relation key:** `(staff_id, name/deduction_type_id, effective_date)`
- **Migration rule:** keep; declare `staff_id`/`deduction_type_id`/`related_student_id` FKs; staff-child-linked rows retain the student link.

### 29. `staff_domain_audit`
- **Disposition:** RETIRE (parallel)
- **Normalization fault(s):** duplicates the `audit_logs` mechanism; `entity_type`/`entity_id` free-form; `user_id`/`staff_id` FKs undeclared [V]
- **Target home(s):** `audit_logs (entity_type, entity_id, action, old_values, new_values, actor_id, acted_at)` (§4.15)
- **Composite / relation key:** `(staff_id, created_at)`
- **Migration rule:** rows → `audit_logs` (entity = staff-domain object, details → old/new values); append-only; old rows preserved read-only in the one-time migration snapshot.

### 30. `staff_duty_roster`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** `staff_id`/`duty_type_id`/`swapped_with_id`/`assigned_by` FKs undeclared; `is_swap` logic embedded in the fact row; no term binding — a duty date only implies its term [V]
- **Target home(s):** `staff_duty_roster` (§4.11 scheduling facts), keyed to `academic_year_terms` via `academic_year_term_id` so rosters follow term dates (opening/half-term/closing)
- **Composite / relation key:** `(staff_id, date, duty_type_id, shift)`
- **Migration rule:** keep; declare FKs to `staff` and `staff_duty_types`; add `academic_year_term_id` backfilled from `date` via the term boundaries (undeterminable = NULL + flag); year derivable from `date` [I].

### 31. `staff_duty_types`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** none structural — `UNIQUE(code)` duty-type code list [V]
- **Target home(s):** `staff_duty_types` (§4.11)
- **Composite / relation key:** `id`
- **Migration rule:** keep; declare the implicit `staff_duty_roster.duty_type_id` FK [I].

### 32. `staff_employment_profiles`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** `UNIQUE(staff_id)` current-state snapshot duplicates appointment/assignment history; `department_id → departments.id` FK undeclared [V]
- **Target home(s):** `staff_employment_profiles` + `staff_department_assignments (staff_id, department_id, role, effective_from, effective_to)` (§4.11)
- **Composite / relation key:** `staff_id` (snapshot); binding `(staff_id, department_id, effective_from)`
- **Migration rule:** keep the snapshot; seed `staff_department_assignments` from `department_id`/`position`/`employment_date` where present; historical changes remain in appointments/lifecycle history.

### 33. `staff_experience`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** none structural — per-staff employment history entries; `staff_id` FK undeclared [V]
- **Target home(s):** `staff_experience` (§4.11)
- **Composite / relation key:** `(staff_id, start_date, organization)`
- **Migration rule:** keep; declare `staff_id → staff.id` FK.

### 34. `staff_id_cards`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** `generated_by`/`issued_by` FKs undeclared; `metadata` free-form [V]
- **Target home(s):** `staff_id_cards (staff_id, card_no)` (§4.11)
- **Composite / relation key:** `UNIQUE(card_number)`
- **Migration rule:** keep; declare `staff_id`/`generated_by`/`issued_by → staff` FKs; card lifecycle, no year context.

### 35. `staff_import_batches`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** none structural — import job control with status lifecycle [V]
- **Target home(s):** import staging (SYS, §4.15 runtime/config)
- **Composite / relation key:** `id` (one batch run)
- **Migration rule:** keep as staging; no re-home of imported staff rows (those are created by the import itself).

### 36. `staff_import_rows`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** none structural — per-row import outcome with resulting `staff_id`/`user_id` [V]
- **Target home(s):** import staging (SYS)
- **Composite / relation key:** `(batch_id, row_number)`
- **Migration rule:** keep; declare `staff_id`/`user_id` FKs once rows materialize [I].

### 37. `staff_incident_reports`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** none structural — incident fact with `UNIQUE(reference_no)` and full FK payload [V]
- **Target home(s):** `staff_incident_reports` (§4.11)
- **Composite / relation key:** `UNIQUE(reference_no)`
- **Migration rule:** keep; declare the 5 declared FKs (`staff_id`/`assigned_to`→staff, `department_id`→departments, `created_by`/`resolved_by`→users).

### 38. `staff_kpi_templates`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** `staff_category_id` FK undeclared [I]
- **Target home(s):** `staff_kpi_templates` (§4.11)
- **Composite / relation key:** `id`
- **Migration rule:** keep; declare `staff_category_id → staff_categories.id` FK.

### 39. `staff_leaves`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** `leave_type` varchar duplicates `leave_type_id` (duplicate columns) [V]; `staff_id`/`leave_type_id`/`relief_staff_id`/`approved_by` FKs undeclared [V]
- **Target home(s):** `staff_leaves (staff_id, leave_type_id, start_date, end_date, status, approved_by)` + `leave_types` (§4.11)
- **Composite / relation key:** `(staff_id, start_date, leave_type_id)`
- **Migration rule:** keep; drop the `leave_type` varchar duplicate; declare the four FKs; year derivable from `start_date` [I].

### 40. `staff_lifecycle_actions`
- **Disposition:** RETIRE (parallel)
- **Normalization fault(s):** duplicates `staff_promotions` and the workflow-history mechanism; parallel HR event log with from/to snapshots [V]
- **Target home(s):** `audit_logs` + `workflow_instances` (§4.15); lifecycle facts owned by `staff_appointments`/promotion history
- **Composite / relation key:** `(staff_id, effective_date, action_type)`
- **Migration rule:** rows → `workflow_instances`/`audit_logs` (entity = staff lifecycle action); old rows preserved read-only in the migration snapshot; [U] reconcile ownership with `staff_promotions` (#48) — one canonical history.

### 41. `staff_loans`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** `balance_remaining` is a stored derived value (redundancy) [V]; `staff_id` FK undeclared [V]
- **Target home(s):** `staff_loans` / `staff_salary_advances` (loan fact, §4.11)
- **Composite / relation key:** `(staff_id, loan_date, loan_type)`
- **Migration rule:** keep as loan fact; drop `balance_remaining` → derived from repayments; declare `staff_id → staff.id` FK.

### 42. `staff_offboarding`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** none structural — offboarding case with lifecycle and `processed_by` FK [V]
- **Target home(s):** `staff_offboarding` (staff lifecycle end fact, §4.11)
- **Composite / relation key:** `(staff_id, last_working_day)`
- **Migration rule:** keep; declare `staff_id`/`processed_by → staff` FKs; final-settlement fields are snapshot facts written once at completion.

### 43. `staff_off_day_patterns`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** `staff_id` FK undeclared; recurring `day_of_week` rule is a schedule pattern, not a dated fact [V]
- **Target home(s):** `staff_off_day_patterns` (§4.11)
- **Composite / relation key:** `(staff_id, day_of_week, effective_from)`
- **Migration rule:** keep; declare `staff_id → staff.id` FK.

### 44. `staff_onboarding`
- **Disposition:** RETIRE (parallel)
- **Normalization fault(s):** onboarding case duplicated by `workflow_instances`; `probation_months`/`progress_percent` mixed into the case [V]
- **Target home(s):** `workflow_instances` + `audit_logs` (§4.15); §4.11's `staff_onboarding` concept is satisfied by `workflow_instances`
- **Composite / relation key:** `(staff_id, start_date)`
- **Migration rule:** rows → `workflow_instances` (entity=onboarding, status, progress); `staff_probation_reviews.onboarding_id` repointed to the `workflow_instance.id`; old rows preserved read-only in the migration snapshot.

### 45. `staff_onboarding_progress`
- **Disposition:** RETIRE (parallel)
- **Normalization fault(s):** per-user completion flags duplicate `workflow_instances` stage state; `UNIQUE(staff_id)`/`UNIQUE(user_id)` snapshots [V]
- **Target home(s):** `workflow_instances` / `workflow_stage_history` (§4.15)
- **Composite / relation key:** `staff_id` / `user_id` (one row each)
- **Migration rule:** rows → workflow-instance stage completions; append-only where history is required; old rows preserved read-only in the migration snapshot.

### 46. `staff_performance_reviews`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** `reviewer_id` undeclared; overlaps `performance_ratings` (two review tracks for the same period) [V]
- **Target home(s):** `performance_reviews (staff_id, period, rating, reviewed_by)` (§4.11)
- **Composite / relation key:** `(staff_id, academic_year_id, term_id, review_type)`
- **Migration rule:** keep; declare `staff_id`/`academic_year_id`/`term_id`/`reviewer_id → staff` FKs; [U] reconcile overlap with `performance_ratings` (#13) — a single review fact per period.

### 47. `staff_probation_reviews`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** `onboarding_id` depends on `staff_onboarding` which is retired; `staff_id`/`reviewer_id` FKs undeclared [V]
- **Target home(s):** `staff_probation_reviews` (probation checkpoint, §4.11)
- **Composite / relation key:** `(onboarding_id → workflow_instance.id, review_month)`
- **Migration rule:** keep; repoint `onboarding_id` to the `workflow_instance.id`; declare `staff_id`/`reviewer_id → staff` FKs.

### 48. `staff_promotions`
- **Disposition:** MERGE
- **Normalization fault(s):** duplicates `staff_lifecycle_actions` (#40) and the appointment history; from/to snapshot columns mirror `staff_employment_profiles` [V]
- **Target home(s):** `staff_appointments` (unified appointment/promotion history, §4.11)
- **Composite / relation key:** `(staff_id, effective_date, promotion_type)`
- **Migration rule:** promotion rows re-home as appointment-history rows with `promotion_type`/from-to payload; self-ref `reverts_to_promotion_id` preserved in the unified fact; [U] reconcile with the retired lifecycle actions — one canonical history.

### 49. `staff_qualifications`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** `staff_id` FK undeclared [V]
- **Target home(s):** `staff_qualifications` (§4.11)
- **Composite / relation key:** `(staff_id, qualification_type, year_obtained, title)`
- **Migration rule:** keep; declare `staff_id → staff.id` FK.

### 50. `staff_shift_assignments`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** `staff_id`/`academic_year_id` FKs undeclared [V]
- **Target home(s):** `staff_shift_assignments` (§4.11 scheduling facts)
- **Composite / relation key:** `(staff_id, academic_year_id, day_of_week, shift)`
- **Migration rule:** keep; declare `staff_id → staff.id` and `academic_year_id → academic_years.id` FKs; 2026 = id 5; a 2027 schedule is NEW rows.

### 51. `staff_types`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** none structural — `UNIQUE(name)` staff-type code list [V]
- **Target home(s):** `staff_types` (§4.11)
- **Composite / relation key:** `id`
- **Migration rule:** keep. **Decision (with #21):** `staff_types` (employment kind, e.g. teaching/non-teaching) and `staff_categories` (classification band under a type) are NOT duplicative — a type groups many categories; both kept as REF masters. Declare the implicit `staff.staff_type_id` and `staff_categories.staff_type_id` FKs [I].

