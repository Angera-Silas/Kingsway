# Normalization mapping — Students & Guardians

Part of `10_NORMALIZATION_MAPPING/` (all 431 legacy tables mapped to `09_NORMALIZED_TARGET_ARCHITECTURE.md`). Covers 47 tables.
Base evidence: `08_PER_TABLE_BREAKDOWN/05_students_guardians.md`, `/tmp/opencode/domains/domain_05.txt`.

Tags: `[V]` verified in dump + live DB · `[I]` reasoned inference · `[U]` owner decision required.

### 1. `admission_applications`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** `academic_year(year4)` is not an FK [I]; `grade_applying_for` enum duplicates the `classes` master (preferred class should be a `class_id` FK) [I]; `target_term_id`/`enrolled_student_id` undeclared [I]
- **Target home(s):** `admission_applications (id, applicant details, academic_year_id, preferred_class_id, status, workflow)` (§4.12)
- **Composite / relation key:** `UNIQUE(application_no)`; intake context `(academic_year_id, preferred_class_id, target_term_id)`
- **Migration rule:** keep as pipeline fact; `academic_year(year4)` → `academic_year_id` (2026 = id 5) [I]; `grade_applying_for` → `preferred_class_id → classes.id` (undeterminable = NULL + flag); declare `target_term_id`/`enrolled_student_id` FKs; `workflow_data_json` → workflow-instance detail.

### 2. `admission_decisions`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** `decision_by` references users (actor) but is undeclared; multiple decisions per application are possible [V]
- **Target home(s):** `admission_decisions (application_id, decision, decided_by, decided_at)` (§4.12)
- **Composite / relation key:** `(application_id, decision_at)`
- **Migration rule:** keep; declare `application_id`/`decision_by` FKs; each decision is a new row, never overwrite.

### 3. `admission_documents`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** none structural — document verification event per application [V]
- **Target home(s):** `admission_documents` (§4.12)
- **Composite / relation key:** `(application_id, document_type)`
- **Migration rule:** keep; declare `application_id`/`verified_by` FKs.

### 4. `admission_enquiries`
- **Disposition:** MERGE (decided)
- **Normalization fault(s):** parallel public lead stream duplicates the admissions pipeline with no application identity [V]
- **Target home(s):** `admission_applications` (§4.12) with `application_source='enquiry'`
- **Composite / relation key:** `(created_at, parent contact)` lead moment
- **Migration rule:** each enquiry → an `admission_applications` row with `application_source='enquiry'`; `application_no` NULL + source flag where not promoted (no invented values); already-promoted enquiries keep their application reference.

### 5. `admission_enrollment_confirmations`
- **Disposition:** MERGE
- **Normalization fault(s):** application→student binding duplicates the enrollment hand-off that placement/`student_academic_enrollments` should own [V]
- **Target home(s):** `placement_offers` + `student_academic_enrollments` (§4.12/§4.3)
- **Composite / relation key:** `UNIQUE(application_id)`
- **Migration rule:** confirmation rows → the accepted `placement_offer` + a `student_academic_enrollments` enrollment row (student_id, academic_year_class_stream_id via placement); `confirmed_by`/`confirmed_at` → workflow history; undeterminable placement = NULL + flag.

### 6. `admission_interviews`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** `interviewer_id` and `conducted_by` both reference users (redundant actor columns) [V]; the `admission_applications.interview_id` back-ref duplicates the one-to-one interview row [V]
- **Target home(s):** `admission_interviews (application_id, staff_id, outcome)` (§4.12)
- **Composite / relation key:** `(application_id, scheduled_date)`
- **Migration rule:** keep; declare `application_id`/`conducted_by`/`interviewer_id` FKs; drop `admission_applications.interview_id` back-ref (resolved via the interview rows).

### 7. `admission_payments`
- **Disposition:** MERGE
- **Normalization fault(s):** application-fee payments duplicate the `payments` ledger; `UNIQUE(reference_no)`/`UNIQUE(receipt_no)` already match the payments shape; `posted_payment_id` ties a second ledger [V]
- **Target home(s):** `payments (receipt_no UNIQUE, student_id, amount, payment_date, method, reference, received_by, status)` (§4.6) with an application reference
- **Composite / relation key:** `UNIQUE(receipt_no)` / `UNIQUE(reference_no)`
- **Migration rule:** rows → `payments`; `application_id` retained as the application reference; `posted_payment_id`/`recorded_by` → `payment_allocations`/`received_by`; voided rows preserved as historical fact.

### 8. `admission_placements`
- **Disposition:** REUSE-ALTER (renamed `placement_offers`)
- **Normalization fault(s):** recommended + final class/stream duplicate the same instance columns (redundancy) [V]; `placement_status`/`placement_type` mix decision fields; stream FKs undeclared [I]
- **Target home(s):** `placement_offers (application_id, academic_year_class_stream_id, offered, accepted)` (§4.12)
- **Composite / relation key:** `UNIQUE(application_id)`; target instance `academic_year_class_stream_id`
- **Migration rule:** resolve `final_class_id`+`final_stream_id`+`academic_year` → `academic_year_class_stream_id` (undeterminable = NULL + flag); `offered` = approved, `accepted` = assigned; declare `application_id`/`approved_by` FKs.

### 9. `admission_placement_tests`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** `subject_area` free string is not a `learning_area_id` FK [I]; stored `percentage` alongside `score`/`max_score` is derived (redundancy) [V]
- **Target home(s):** `admission_placement_tests (application_id, learning_area_id, score, date)` (§4.12)
- **Composite / relation key:** `(application_id, test_code)`
- **Migration rule:** keep; `subject_area` → `learning_area_id` where derivable, else NULL + flag; drop stored `percentage` → derived; declare `application_id`/`recorded_by` FKs.

### 10. `admission_process_steps`
- **Disposition:** MERGE
- **Normalization fault(s):** UI content (`icon/color/display_order`) fused with process-step definition; duplicates `workflow_stages` [V]
- **Target home(s):** `workflow_stages` (§4.15)
- **Composite / relation key:** `step_number` (admissions workflow ordering)
- **Migration rule:** rows seed `workflow_stages` (admissions workflow); `icon`/`color`/`description` retained as stage content attributes; no invented content.

### 11. `admission_workflow_history`
- **Disposition:** MERGE
- **Normalization fault(s):** duplicates the `workflow_history` mechanism; `from_stage`/`to_stage`/`action` free-form [V]
- **Target home(s):** `workflow_history` (§4.15)
- **Composite / relation key:** `(application_id, performed_at)`
- **Migration rule:** rows → `workflow_history` (entity=`admission_applications`, from/to stage, action, actor); append-only, never backfill.

### 12. `alumni`
- **Disposition:** RETIRE (become a VIEW — decided)
- **Normalization fault(s):** graduation-snapshot master duplicates `students` + `student_transitions(type=graduation)` data (redundancy) [V]; per mandate prefer a VIEW, no duplicate master
- **Target home(s):** VIEW over `student_transitions` (transition_type='graduation') + `students` (§4.3/§4.1)
- **Composite / relation key:** `student_id` (one snapshot per graduate)
- **Migration rule:** no physical rows — derive the alumni view from `student_transitions` where `transition_type='graduation'`; awards/next-school/contact snapshot fields, where a concrete fact must persist, are written once by the graduation process onto the transition row or a thin snapshot [U]; never re-ID `students` (34 FK children).

### 13. `external_institutions`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** `email_addresses`/`phone_numbers` stored as comma-joined lists — repeated group (4NF fault) [V]
- **Target home(s):** `external_institutions` (REF master for transfer in/out references, §4.12)
- **Composite / relation key:** `id`
- **Migration rule:** keep as REF master; [I] split list columns into a contact relation if multi-value querying is required, else retain with a documented convention; used by transfer in/out references.

### 14. `ieps`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** `academic_year(year4)` is not an FK [I]
- **Target home(s):** `ieps (student_id, plan)` keyed `(student_id, academic_year_id)` (student health/learning support)
- **Composite / relation key:** `(student_id, academic_year_id)`
- **Migration rule:** keep; `academic_year(year4)` → `academic_year_id` (2026 = id 5) [I]; declare `student_id`/`created_by`/`approved_by` FKs.

### 15. `parent_communication_preferences`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** `phone_number` + `is_primary` duplicate `parents.phone_1`/`phone_2` (redundancy) [V]; per-channel prefs are multi-valued per parent
- **Target home(s):** `parents` subtype (channel preferences, §4.1)
- **Composite / relation key:** `(parent_id, phone_number)`
- **Migration rule:** keep as per-channel preference relation; `phone_number`/`is_primary` reconciled with `parents.phone` (single source); declare `parent_id → parents.id` FK; undeterminable preference = NULL + flag.

### 16. `parent_meetings`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** `class_id` is a bare year-bound reference — meeting context is not attributable to year/term [V]; `parent_id`+`student_id` both present though the student implies the parent via `student_parents`
- **Target home(s):** `school_events`-aligned meeting fact (§4.13) / `parent_meetings`
- **Composite / relation key:** `(parent_id/student_id, meeting_date)`
- **Migration rule:** keep; resolve `class_id` to the `academic_year_class_streams` instance via `meeting_date` where derivable, else NULL + flag; declare `parent_id`/`student_id`/`created_by` FKs.

### 17. `parent_otp_sessions`
- **Disposition:** MERGE
- **Normalization fault(s):** duplicates the OTP/session runtime (`user_2fa_otp_sessions`); short-lived auth state [V]
- **Target home(s):** `user_2fa_otp_sessions` (§4.15)
- **Composite / relation key:** session id (expiry-driven)
- **Migration rule:** rows → `user_2fa_otp_sessions` (parent auth); no retention/backfill; declare `parent_id → parents.id` FK.

### 18. `parent_portal_messages`
- **Disposition:** MERGE
- **Normalization fault(s):** sender/recipient are polymorphic (`type` + `id`) columns; `parent_id`+`student_id` redundant (student implies parent via `student_parents`) [V]
- **Target home(s):** `internal_messages` + `conversation_participants` (§4.14)
- **Composite / relation key:** `(created_at, sender_id, recipient_id)`
- **Migration rule:** rows → `internal_messages` with `conversation_participants` rows for the parent+student participants; `reply_to_id` self-ref preserved; declare `parent_id`/`student_id` FKs where retained.

### 19. `parent_portal_sessions`
- **Disposition:** MERGE
- **Normalization fault(s):** duplicates `user_sessions`/`auth_sessions` runtime; `UNIQUE(session_token)` matches the target shape [V]
- **Target home(s):** `user_sessions` / `auth_sessions` (§4.15)
- **Composite / relation key:** `UNIQUE(session_token)`
- **Migration rule:** rows → `user_sessions` (parent portal sessions); no retention/backfill; declare `parent_id → parents.id` FK.

### 20. `parents`
- **Disposition:** SPLIT
- **Normalization fault(s):** mixed context — person identity + guardian subtype + portal auth columns in one master (`portal_password`/`portal_status`/`portal_last_login` are auth, not person data) [V]; `phone_1`/`phone_2` unnormalized
- **Target home(s):** `persons` (identity/contact, §4.1) + `parents` (subtype: occupation, status, §4.1) + `users` (portal credentials, hashed)
- **Composite / relation key:** `id` (never re-ID); `person_id UNIQUE` in target
- **Migration rule:** `persons` ← names, `id_number`, gender, date_of_birth, `phone_1`/`phone_2`/email; `parents` ← occupation, address, status; `portal_password` → `users.password_hash` (re-hash; [U] confirm posture); `student_parents` relation retained (student_id, parent_id, relationship, is_primary).

### 21. `parent_statement_downloads`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** none structural — immutable download log; `academic_year(year4)`/`term_id` present [V]
- **Target home(s):** `page_downloads` / `audit_logs` (§4.14/§4.15)
- **Composite / relation key:** `(parent_id, student_id, academic_year_id, term_id, downloaded_at)`
- **Migration rule:** keep append-only; `academic_year(year4)` → `academic_year_id` [I]; declare `parent_id`/`student_id` FKs.

### 22. `sick_bay_visits`
- **Disposition:** MERGE (decided)
- **Normalization fault(s):** duplicates `student_health_visits` as a second visit stream (wrong logic / redundancy) [V]
- **Target home(s):** `student_health_visits` (single health-visit fact keyed student_id + date, §4.4)
- **Composite / relation key:** `(student_id, visit_date, visit_time)`
- **Migration rule:** rows → `student_health_visits`; `temperature`/`weight`/`referral`/`parent_notified` columns retained on the merged visit fact; declare `student_id`/`attended_by` FKs; no duplicate visits.

### 23. `student_activities`
- **Disposition:** REUSE-ALTER (renamed `activity_participants`)
- **Normalization fault(s):** membership per student without year/schedule context; the activity may be year-bound while the join carries only `joined_at` [V]
- **Target home(s):** `activity_participants (activity_schedule_id, student_academic_enrollment_id, role)` (§4.13)
- **Composite / relation key:** `(student_id, activity_id)` → `(activity_schedule_id, student_academic_enrollment_id)`
- **Migration rule:** keep as participation relation; `student_id` resolved to `student_academic_enrollment_id` for the join year (undeterminable = NULL + flag); declare `student_id`/`activity_id` FKs.

### 24. `student_boarding_notes`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** `note_type` enum is a multi-domain categorization (`dormitory/welfare/discipline/health/safety/general` — mixed contexts in one flag) [V]; `resolved_by`/`created_by` undeclared
- **Target home(s):** boarding-note fact keyed `student_academic_enrollment_id` + date (§4.8)
- **Composite / relation key:** `(student_id, created_at, note_type)`
- **Migration rule:** keep; resolve `student_id` → `student_academic_enrollment_id` for the note year where derivable, else `student_id` + NULL flag; declare `student_id`/`created_by`/`resolved_by` FKs.

### 25. `student_clearances`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** `transfer_request_id` undeclared; `amount_outstanding` duplicates fee state (redundancy) [V]
- **Target home(s):** `student_clearances` (transfer-clearance items keyed to the transfer request, §4.4)
- **Composite / relation key:** `(student_id, transfer_request_id, clearance_type)`
- **Migration rule:** keep; declare `student_id`/`transfer_request_id` FKs; drop `amount_outstanding` → derived from fee obligations at clearance time; feeds the transfer clearance status.

### 26. `student_core_values`
- **Disposition:** REUSE-ALTER (renamed `learner_values_acquisition`)
- **Normalization fault(s):** `student_id` + `term_id` should be a stream-student context (mixed context) [V]; `value_id` undeclared
- **Target home(s):** `learner_values_acquisition (student_academic_enrollment_id, academic_year_term_id, core_value_id, rating)` (relation, §4.5 `core_values`)
- **Composite / relation key:** `(student_academic_enrollment_id, academic_year_term_id, core_value_id)`
- **Migration rule:** resolve `student_id` → `student_academic_enrollment_id` via the term's enrollment (undeterminable = NULL + flag); declare `term_id → academic_year_terms` and `value_id → core_values` FKs.

### 27. `student_counseling_cases`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** `assigned_to`/`opened_by`/`closed_by` actor FKs undeclared [I]
- **Target home(s):** `student_counseling_cases` (case fact keyed student_id, §4.4)
- **Composite / relation key:** `UNIQUE(case_code)`
- **Migration rule:** keep; declare `student_id` + actor FKs; case lifecycle states are new fact states, never overwrite closed history.

### 28. `student_counseling_sessions`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** `recorded_by` actor FK undeclared [I]
- **Target home(s):** `student_counseling_sessions` (session event, §4.4)
- **Composite / relation key:** `(case_id, session_date)`
- **Migration rule:** keep; declare `case_id`/`recorded_by` FKs.

### 29. `student_discipline`
- **Disposition:** SPLIT
- **Normalization fault(s):** NO academic_year/term/class/stream context — incidents are unattributable (the flagged missing-context RESTR) [V]; `description`/`action_taken`/`resolution` mixed; no incident-type field
- **Target home(s):** `discipline_incidents (student_academic_enrollment_id, academic_year_term_id, type, severity, date)` + `conduct_tracking` (§4.4)
- **Composite / relation key:** `(student_academic_enrollment_id, academic_year_term_id, incident_date)`
- **Migration rule:** derive `student_academic_enrollment_id` from the enrollment active on `incident_date` (undeterminable = NULL + flag); `type` mapped from severity/description where derivable, else NULL + flag; conduct outcome → `conduct_tracking`; `student_welfare_cases.related_discipline_case_id` repointed to `discipline_incidents.id`; never overwrite resolved incidents.

### 30. `student_health_records`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** `UNIQUE(student_id)` single-profile row with multi-valued text lists (`allergies`/`chronic_conditions`) — repeated groups (4NF fault) [V]; `record_code` duplicates student identity
- **Target home(s):** `student_health_records` (persistent per-student health master, §4.4)
- **Composite / relation key:** `UNIQUE(student_id)`
- **Migration rule:** keep as profile master; [I] split multi-valued lists into condition rows if queryable detail is required, else retain with a documented convention; declare `student_id → students.id` FK.

### 31. `student_health_reviews`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** `health_record_id` FK declared; `review_date` timestamp [V]
- **Target home(s):** `student_health_reviews` (periodic review event, §4.4)
- **Composite / relation key:** `(health_record_id, review_date)`
- **Migration rule:** keep; declare `health_record_id`/`reviewed_by` FKs.

### 32. `student_health_visits`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** `health_record_id` undeclared; duplicates the `sick_bay_visits` stream (now merged) [V]
- **Target home(s):** `student_health_visits` (visit fact keyed student_id + date, §4.4)
- **Composite / relation key:** `(student_id, visit_date)`
- **Migration rule:** keep as the single visit stream (`sick_bay_visits` merged in); declare `health_record_id`/`student_id`/`recorded_by` FKs; no duplicate visits.

### 33. `student_id_card_history`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** none structural — immutable card lifecycle log [V]
- **Target home(s):** student-id-card history (append-only log, §4.15 audit pattern)
- **Composite / relation key:** `(card_id, performed_at)`
- **Migration rule:** keep append-only; declare `card_id`/`student_id` FKs; never backfill.

### 34. `student_id_cards`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** `academic_year_id` undeclared [I]; `replaced_from_card_id` self-ref is correct [V]
- **Target home(s):** `student_id_cards` (year-issued card, §4.15/student identity)
- **Composite / relation key:** `(student_id, academic_year_id)`
- **Migration rule:** keep; declare `academic_year_id → academic_years.id` FK (2026 = id 5); each year's card is a new row, never mutated.

### 35. `student_meal_profiles`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** `food_restrictions`/`allergy`/`medical`/`religious` notes are parallel multi-valued text lists (4NF fault) [V]
- **Target home(s):** `student_meal_profiles` → catering (§4.10 meal profiles)
- **Composite / relation key:** `UNIQUE(student_id)`
- **Migration rule:** keep as per-student diet profile; [I] split restriction notes into a restriction relation if queryable detail is required; declare `student_id → students.id` FK.

### 36. `student_parents`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** none structural — canonical student↔parent junction with relation attributes [V]; FKs undeclared
- **Target home(s):** `student_parents (student_id, parent_id, relationship, is_primary, PRIMARY KEY(student_id, parent_id))` (§4.1)
- **Composite / relation key:** `(student_id, parent_id)`
- **Migration rule:** keep; declare `student_id → students.id` and `parent_id → parents.id` FKs; `is_primary_contact`/`is_emergency_contact` retained as relation attributes; no year context.

### 37. `student_permission_types`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** none structural — permission-type code list `UNIQUE(code)` [V]
- **Target home(s):** `student_permission_types` (REF master, §4.4)
- **Composite / relation key:** `id`
- **Migration rule:** keep; no FKs to declare.

### 38. `student_registrations`
- **Disposition:** RETIRE (decided — 0 rows today [V])
- **Normalization fault(s):** legacy per-student registration duplicates the enrollment spine; `(student_id, class_id, term_id)` without stream — mixed context [V]
- **Target home(s):** `students` + `student_academic_enrollments` (§4.1/§4.3)
- **Composite / relation key:** `(student_id, academic_year_class_stream_id)` resolved from `class_id`+`term_id`
- **Migration rule:** 0 rows today — no data to move [V]; any future rows would become `student_academic_enrollments` enrollment facts; do not build new FK structure on it.

### 39. `students`
- **Disposition:** SPLIT
- **Normalization fault(s):** identity + learner subtype mixed; `stream_id` is a stored redundant current-stream (duplicates `student_academic_enrollments`) [V]; `assessment_status`/`nemis_status`/sponsor columns are mixed context; `application_id`/`student_type_id` undeclared [I]
- **Target home(s):** `persons` (identity, §4.1) + `students` (admission_no UNIQUE, admission_date, status, assessment_number, nemis_number) + `student_academic_enrollments` (enrollment, §4.3)
- **Composite / relation key:** `admission_no` (master key); `person_id UNIQUE` in target
- **Migration rule:** `persons` ← names, date_of_birth, gender, `photo_url`; `students` ← `admission_no`, `assessment_number`, `nemis_number`, `admission_date`, `status`, `application_id` (admissions ref), `student_type_id`; `stream_id` column REMOVED — enrollment lives only in `student_academic_enrollments` (via `academic_year_class_streams`); sponsor columns → sponsorship fact where required [U]; never re-ID (34 FK children).

### 40. `student_suspensions`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** `academic_year(year4)` is not an FK [I]; `suspended_by` undeclared
- **Target home(s):** `student_suspensions` (suspension fact keyed student_id + date, §4.4)
- **Composite / relation key:** `(student_id, suspension_date)`
- **Migration rule:** keep; `academic_year(year4)` → `academic_year_id` (2026 = id 5) [I]; resolve `student_id` → `student_academic_enrollment_id` for the year where derivable, else NULL + flag; declare `suspended_by` FK.

### 41. `student_transfer_requests`
- **Disposition:** SPLIT
- **Normalization fault(s):** `inter_school`/`withdrawal`/`intra_school`/`upgrade` are four different lifecycle-event types fused in one table; `clearance_status`/clearance fields duplicate `student_clearances` detail [V]
- **Target home(s):** `student_transitions (id, student_id, from_student_academic_enrollment_id?, to_student_academic_enrollment_id?, academic_year_id, transition_type, reason, decided_by, decided_at, executed_at)` (§4.3) + `student_clearances`
- **Composite / relation key:** `UNIQUE(request_number)`; transition key `(student_id, academic_year_id, transition_type)`
- **Migration rule:** `inter_school`/`withdrawal`/`intra_school` rows → `student_transitions` (transition_type as-is); `upgrade` → promotion/graduation transition; clearance detail stays in `student_clearances` (already keyed to the request); `fee_balance_at_request` is a snapshot fact written once at request time [I].

### 42. `student_types`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** none structural — student-type code list `UNIQUE(code)` [V]
- **Target home(s):** `student_types` (REF master used by fee schedules, §4.6)
- **Composite / relation key:** `id`
- **Migration rule:** keep; declare the implicit `students.student_type_id` FK [I].

### 43. `student_uniforms`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** one row per student with many size columns — vertical repeated group (4NF fault) [V]; `updated_by` undeclared
- **Target home(s):** student uniform sizes (REF or attribute, §4.6 uniform catalog)
- **Composite / relation key:** `UNIQUE(student_id)`
- **Migration rule:** keep as per-student size profile; [I] normalize to per-garment size rows if queryable per-item history is required; declare `student_id → students.id` FK.

### 44. `student_vaccinations`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** none structural — `(student_id, vaccine_name, dose_number)` vaccination fact [V]
- **Target home(s):** `student_vaccinations` (health fact keyed student_id + vaccine + dose, §4.4)
- **Composite / relation key:** `(student_id, vaccine_name, dose_number)`
- **Migration rule:** keep; declare `student_id → students.id` FK; `given_by` retained as free-text provider.

### 45. `student_welfare_cases`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** `related_discipline_case_id → student_discipline.id` must repoint to `discipline_incidents`; actor FKs declared [V]
- **Target home(s):** `student_welfare_cases` (case fact keyed student_id, §4.4)
- **Composite / relation key:** `UNIQUE(case_code)`
- **Migration rule:** keep; repoint `related_discipline_case_id` → `discipline_incidents.id`; declare `student_id`/`assigned_to`/`opened_by`/`resolved_by` FKs; closed cases never overwritten.

### 46. `student_welfare_notes`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** none structural — case-note event [V]
- **Target home(s):** `student_welfare_notes` (§4.4)
- **Composite / relation key:** `(welfare_case_id, created_at)`
- **Migration rule:** keep; declare `welfare_case_id`/`recorded_by` FKs.

### 47. `web_admission_applications`
- **Disposition:** MERGE
- **Normalization fault(s):** parallel public application stream duplicates `admission_applications` with only a source difference [V]
- **Target home(s):** `admission_applications` (application_source='web', §4.12)
- **Composite / relation key:** `UNIQUE(application_ref)`
- **Migration rule:** rows → `admission_applications` with `application_source='web'`; child/parent data maps to applicant + parent fields; `application_no` NULL + source flag where not promoted; preserve the `web_application_id` back-ref on the promoted row; `grade_applying` → `preferred_class_id` where derivable, else NULL + flag.

