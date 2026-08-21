# KingsWay Academy — Complete Table Inventory (Phase 1)

Source of truth: `database/KingsWayDatabase_2026_08_01_1409hrs.sql` (mirrors live `KingsWayAcademy`). Generated 2026-08-01 13:25.

**Total base tables: 431**

## `academic_capacity_config`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `academic_year_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 3 | `available_pct_threshold` | decimal(5,2) | NO | - | `20.00` |  |
| 4 | `limited_pct_threshold` | decimal(5,2) | NO | - | `1.00` |  |
| 5 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 6 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

---
## `academic_capacity_reservations`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `application_id` | int(10) unsigned | YES | UNI | `NULL` |  |
| 3 | `academic_year_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 4 | `term_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 5 | `class_id` | int(10) unsigned | NO | MUL | NULL |  |
| 6 | `stream_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 7 | `reservation_status` | enum('provisional','confirmed','converted','expired','released','cancelled') | NO | MUL | `'provisional'` |  |
| 8 | `reserved_at` | datetime | YES | - | `NULL` |  |
| 9 | `expires_at` | datetime | YES | - | `NULL` |  |
| 10 | `released_at` | datetime | YES | - | `NULL` |  |
| 11 | `converted_to_enrollment_at` | datetime | YES | - | `NULL` |  |
| 12 | `created_by` | int(10) unsigned | YES | - | `NULL` |  |
| 13 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 14 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

FKs: `stream_id` → `class_streams`.`id`; `class_id` → `classes`.`id`; `application_id` → `admission_applications`.`id`; `academic_year_id` → `academic_years`.`id`; `term_id` → `academic_terms`.`id`

UNIQUE/INDEX: `uk_application` (`application_id`)

---
## `academic_class_progression`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `source_class_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `target_class_id` | int(10) unsigned | NO | MUL | NULL |  |
| 4 | `progression_type` | varchar(40) | NO | - | `'standard'` |  |
| 5 | `effective_from_academic_year_id` | int(10) unsigned | YES | - | `NULL` |  |
| 6 | `active` | tinyint(1) | NO | MUL | `1` |  |
| 7 | `created_by` | int(10) unsigned | YES | - | `NULL` |  |
| 8 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 9 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

FKs: `target_class_id` → `classes`.`id`; `source_class_id` → `classes`.`id`

UNIQUE/INDEX: `uk_src_tgt` (`source_class_id,target_class_id`)

---
## `academic_terms`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `academic_year_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 3 | `name` | varchar(50) | NO | - | NULL |  |
| 4 | `start_date` | date | NO | - | NULL |  |
| 5 | `end_date` | date | NO | - | NULL |  |
| 6 | `midterm_break_start` | date | YES | - | `NULL` |  |
| 7 | `midterm_break_end` | date | YES | - | `NULL` |  |
| 8 | `opening_date` | date | YES | - | `NULL` |  |
| 9 | `closing_date` | date | YES | - | `NULL` |  |
| 10 | `year` | year(4) | NO | MUL | NULL |  |
| 11 | `term_number` | tinyint(4) | NO | - | NULL |  |
| 12 | `status` | enum('upcoming','current','completed') | NO | MUL | NULL |  |
| 13 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 14 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

FKs: `academic_year_id` → `academic_years`.`id`

UNIQUE/INDEX: `uk_year_term` (`year,term_number`)

---
## `academic_year_archives`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `academic_year` | year(4) | NO | UNI | NULL |  |
| 3 | `status` | enum('active','closing','archived','readonly') | NO | MUL | `'active'` |  |
| 4 | `total_students` | int(11) | YES | - | `0` |  |
| 5 | `promoted_count` | int(11) | YES | - | `0` |  |
| 6 | `retained_count` | int(11) | YES | - | `0` |  |
| 7 | `transferred_count` | int(11) | YES | - | `0` |  |
| 8 | `graduated_count` | int(11) | YES | - | `0` |  |
| 9 | `suspended_count` | int(11) | YES | - | `0` |  |
| 10 | `closure_initiated_by` | int(10) unsigned | YES | MUL | `NULL` |  |
| 11 | `closure_date` | datetime | YES | - | `NULL` |  |
| 12 | `closure_notes` | text | YES | - | `NULL` |  |
| 13 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 14 | `archived_at` | timestamp | YES | - | `NULL` |  |

FKs: `closure_initiated_by` → `users`.`id`

UNIQUE/INDEX: `uk_academic_year` (`academic_year`)

---
## `academic_year_rollover_log`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `rollover_id` | varchar(20) | NO | MUL | NULL |  |
| 3 | `from_year_id` | int(10) unsigned | NO | MUL | NULL |  |
| 4 | `to_year_id` | int(10) unsigned | YES | - | `NULL` |  |
| 5 | `step` | enum('preflight_check','compute_year_averages','generate_report_cards','process_promotions','fee_carryover','credit_note_generation','staff_reassignment','timetable_rollover','create_new_year','create_new_terms','generate_fee_obligations','archive_old_year','activate_new_year','complete') | NO | - | NULL |  |
| 6 | `status` | enum('pending','in_progress','completed','failed','skipped') | YES | - | `'pending'` |  |
| 7 | `students_processed` | int(11) | YES | - | `0` |  |
| 8 | `students_promoted` | int(11) | YES | - | `0` |  |
| 9 | `students_retained` | int(11) | YES | - | `0` |  |
| 10 | `students_transferred` | int(11) | YES | - | `0` |  |
| 11 | `fee_balances_carried` | int(11) | YES | - | `0` |  |
| 12 | `credit_notes_created` | int(11) | YES | - | `0` |  |
| 13 | `staff_reassigned` | int(11) | YES | - | `0` |  |
| 14 | `obligations_generated` | int(11) | YES | - | `0` |  |
| 15 | `details` | longtext | YES | - | `NULL` |  |
| 16 | `error_message` | text | YES | - | `NULL` |  |
| 17 | `performed_by` | int(10) unsigned | YES | - | `NULL` |  |
| 18 | `performed_at` | timestamp | NO | - | `current_timestamp()` |  |

---
## `academic_years`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `year_code` | varchar(20) | NO | UNI | NULL |  |
| 3 | `year_name` | varchar(100) | NO | - | NULL |  |
| 4 | `start_date` | date | NO | MUL | NULL |  |
| 5 | `end_date` | date | NO | - | NULL |  |
| 6 | `registration_start` | date | YES | - | `NULL` |  |
| 7 | `registration_end` | date | YES | - | `NULL` |  |
| 8 | `status` | enum('planning','registration','active','closing','archived') | NO | MUL | `'planning'` |  |
| 9 | `is_current` | tinyint(1) | YES | MUL | `0` |  |
| 10 | `total_students` | int(11) | YES | - | `0` |  |
| 11 | `total_classes` | int(11) | YES | - | `0` |  |
| 12 | `created_by` | int(10) unsigned | YES | MUL | `NULL` |  |
| 13 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 14 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

FKs: `created_by` → `users`.`id`

UNIQUE/INDEX: `year_code` (`year_code`)

---
## `account_unlock_history`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `user_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `locked_reason` | varchar(255) | YES | - | `NULL` |  |
| 4 | `locked_date` | datetime | NO | - | NULL |  |
| 5 | `unlocked_date` | datetime | NO | - | NULL |  |
| 6 | `unlocked_by` | int(10) unsigned | YES | - | `NULL` |  |
| 7 | `unlock_reason` | text | YES | - | `NULL` |  |
| 8 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |

FKs: `user_id` → `users`.`id`

---
## `activities`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `title` | varchar(255) | NO | - | NULL |  |
| 3 | `description` | text | YES | - | `NULL` |  |
| 4 | `category_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 5 | `start_date` | date | YES | - | `NULL` |  |
| 6 | `end_date` | date | YES | - | `NULL` |  |
| 7 | `status` | enum('planned','ongoing','completed','cancelled') | NO | - | `'planned'` |  |
| 8 | `max_participants` | int(11) | YES | - | `NULL` |  |
| 9 | `started_by` | int(11) | YES | - | `NULL` |  |
| 10 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 11 | `target_audience` | enum('students','staff','both') | NO | - | `'students'` |  |

FKs: `category_id` → `activity_categories`.`id`

---
## `activity_categories`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `name` | varchar(100) | NO | UNI | NULL |  |
| 3 | `description` | text | YES | - | `NULL` |  |
| 4 | `is_active` | tinyint(1) | NO | - | `1` |  |
| 5 | `status` | enum('active','inactive') | NO | - | `'active'` |  |
| 6 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 7 | `department_id` | int(10) unsigned | YES | MUL | `NULL` |  |

FKs: `department_id` → `departments`.`id`

UNIQUE/INDEX: `uk_name` (`name`)

---
## `activity_participants`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `activity_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `student_id` | int(10) unsigned | NO | MUL | NULL |  |
| 4 | `joined_at` | timestamp | NO | - | `current_timestamp()` |  |
| 5 | `status` | enum('active','withdrawn','completed','pending') | NO | - | `'active'` |  |
| 6 | `role` | varchar(50) | YES | - | `NULL` |  |
| 7 | `notes` | text | YES | - | `NULL` |  |

FKs: `student_id` → `students`.`id`; `activity_id` → `activities`.`id`

UNIQUE/INDEX: `uk_activity_student` (`activity_id,student_id`)

---
## `activity_resources`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `name` | varchar(255) | YES | MUL | `NULL` |  |
| 3 | `activity_id` | int(10) unsigned | NO | MUL | NULL |  |
| 4 | `resource_name` | varchar(255) | NO | - | NULL |  |
| 5 | `resource_type` | varchar(50) | YES | - | `NULL` |  |
| 6 | `resource_url` | varchar(255) | YES | - | `NULL` |  |
| 7 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 8 | `type` | varchar(100) | YES | - | `NULL` |  |
| 9 | `quantity` | int(10) unsigned | YES | - | `NULL` |  |
| 10 | `status` | varchar(50) | YES | - | `NULL` |  |
| 11 | `cost` | decimal(10,2) | YES | - | `NULL` |  |

FKs: `activity_id` → `activities`.`id`

---
## `activity_schedule`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `activity_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `day_of_week` | varchar(20) | YES | MUL | `NULL` |  |
| 4 | `schedule_date` | date | NO | - | NULL |  |
| 5 | `start_time` | time | NO | - | NULL |  |
| 6 | `end_time` | time | NO | - | NULL |  |
| 7 | `venue` | varchar(100) | YES | - | `NULL` |  |
| 8 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 9 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

FKs: `activity_id` → `activities`.`id`

---
## `activity_staff_participants`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `activity_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `staff_id` | int(10) unsigned | NO | MUL | NULL |  |
| 4 | `joined_at` | timestamp | NO | - | `current_timestamp()` |  |
| 5 | `status` | enum('active','inactive') | NO | - | `'active'` |  |

FKs: `activity_id` → `activities`.`id`

---
## `admission_applications`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `application_no` | varchar(20) | NO | UNI | NULL |  |
| 3 | `applicant_name` | varchar(100) | NO | - | NULL |  |
| 4 | `date_of_birth` | date | NO | - | NULL |  |
| 5 | `gender` | enum('male','female','other') | NO | - | NULL |  |
| 6 | `grade_applying_for` | enum('Playground','PP1','PP2','Grade1','Grade2','Grade3','Grade4','Grade5','Grade6','Grade7','Grade8','Grade9') | NO | - | NULL |  |
| 7 | `education_level` | enum('Pre-Primary','Lower-Primary','Upper-Primary','Junior-Secondary') | YES | MUL | `NULL` | STORED GENERATED |
| 8 | `academic_year` | year(4) | NO | - | NULL |  |
| 9 | `previous_school` | varchar(255) | YES | - | `NULL` |  |
| 10 | `parent_id` | int(10) unsigned | NO | MUL | NULL |  |
| 11 | `application_source` | enum('online','physical') | NO | - | `'physical'` |  |
| 12 | `web_application_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 13 | `admission_category` | enum('standard','nursery_term_1','nursery_term_3') | NO | - | `'standard'` |  |
| 14 | `target_term_id` | int(10) unsigned | YES | - | `NULL` |  |
| 15 | `requires_interview` | tinyint(1) | NO | - | `0` |  |
| 16 | `interview_policy_reason` | varchar(255) | YES | - | `NULL` |  |
| 17 | `has_special_needs` | tinyint(1) | YES | - | `0` |  |
| 18 | `special_needs_details` | text | YES | - | `NULL` |  |
| 19 | `status` | enum('submitted','documents_pending','documents_verified','placement_offered','fees_pending','enrolled','cancelled') | NO | MUL | `'submitted'` |  |
| 20 | `enrolled_student_id` | int(10) unsigned | YES | - | `NULL` |  |
| 21 | `interview_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 22 | `placement_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 23 | `enrolled_at` | datetime | YES | - | `NULL` |  |
| 24 | `director_confirmed_by` | int(10) unsigned | YES | - | `NULL` |  |
| 25 | `director_confirmed_at` | datetime | YES | - | `NULL` |  |
| 26 | `director_confirmation_notes` | text | YES | - | `NULL` |  |
| 27 | `workflow_data_json` | longtext | YES | - | `NULL` |  |
| 28 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 29 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

FKs: `placement_id` → `admission_placements`.`id`; `interview_id` → `admission_interviews`.`id`; `parent_id` → `parents`.`id`; `web_application_id` → `web_admission_applications`.`id`

UNIQUE/INDEX: `uk_application_no` (`application_no`)

---
## `admission_decisions`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `application_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `decision` | enum('approved','rejected','waitlisted','more_info_required','placement_test_required') | NO | MUL | NULL |  |
| 4 | `decision_by` | int(10) unsigned | NO | MUL | NULL |  |
| 5 | `decision_at` | datetime | NO | MUL | NULL |  |
| 6 | `remarks` | text | YES | - | `NULL` |  |
| 7 | `conditions` | text | YES | - | `NULL` |  |
| 8 | `interview_score` | int(11) | YES | - | `NULL` |  |
| 9 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 10 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

FKs: `decision_by` → `users`.`id`; `application_id` → `admission_applications`.`id`

---
## `admission_documents`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `application_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `document_type` | enum('birth_certificate','immunization_card','progress_report','previous_school_report','medical_records','passport_photo','parent_id','nemis_upi','leaving_certificate','transfer_letter','behavior_report','other') | NO | - | NULL |  |
| 4 | `document_path` | varchar(255) | NO | - | NULL |  |
| 5 | `is_mandatory` | tinyint(1) | NO | - | `1` |  |
| 6 | `verification_status` | enum('pending','verified','rejected') | NO | - | `'pending'` |  |
| 7 | `verified_by` | int(10) unsigned | YES | MUL | `NULL` |  |
| 8 | `verified_at` | datetime | YES | - | `NULL` |  |
| 9 | `notes` | text | YES | - | `NULL` |  |
| 10 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |

FKs: `verified_by` → `users`.`id`; `application_id` → `admission_applications`.`id`

---
## `admission_enquiries`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(11) | NO | PRI | NULL | auto_increment |
| 2 | `parent_name` | varchar(150) | NO | - | NULL |  |
| 3 | `phone` | varchar(30) | NO | - | NULL |  |
| 4 | `email` | varchar(200) | YES | - | `NULL` |  |
| 5 | `child_name` | varchar(150) | NO | - | NULL |  |
| 6 | `grade_applying` | varchar(20) | NO | - | NULL |  |
| 7 | `notes` | text | YES | - | `NULL` |  |
| 8 | `status` | enum('new','contacted','enrolled','declined') | NO | - | `'new'` |  |
| 9 | `ip_address` | varchar(45) | YES | - | `NULL` |  |
| 10 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 11 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

---
## `admission_enrollment_confirmations`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `application_id` | int(10) unsigned | NO | UNI | NULL |  |
| 3 | `student_id` | int(10) unsigned | NO | MUL | NULL |  |
| 4 | `confirmed_by` | int(10) unsigned | NO | MUL | NULL |  |
| 5 | `confirmed_at` | datetime | NO | - | NULL |  |
| 6 | `notes` | text | YES | - | `NULL` |  |
| 7 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |

UNIQUE/INDEX: `uq_admission_confirmation_application` (`application_id`)

---
## `admission_interviews`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `application_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `scheduled_date` | date | NO | MUL | NULL |  |
| 4 | `scheduled_time` | time | NO | - | NULL |  |
| 5 | `venue` | varchar(255) | YES | - | `'Main Office'` |  |
| 6 | `interviewer_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 7 | `status` | enum('scheduled','completed','cancelled','rescheduled') | NO | MUL | `'scheduled'` |  |
| 8 | `academic_readiness_score` | int(11) | YES | - | `NULL` |  |
| 9 | `behavior_score` | int(11) | YES | - | `NULL` |  |
| 10 | `communication_score` | int(11) | YES | - | `NULL` |  |
| 11 | `overall_score` | int(11) | YES | - | `NULL` |  |
| 12 | `recommendation` | enum('recommended','not_recommended','conditional') | YES | - | `NULL` |  |
| 13 | `remarks` | text | YES | - | `NULL` |  |
| 14 | `conducted_at` | datetime | YES | - | `NULL` |  |
| 15 | `conducted_by` | int(10) unsigned | YES | MUL | `NULL` |  |
| 16 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 17 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

FKs: `interviewer_id` → `users`.`id`; `conducted_by` → `users`.`id`; `application_id` → `admission_applications`.`id`

---
## `admission_payments`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `application_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `student_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 4 | `amount` | decimal(12,2) | NO | - | NULL |  |
| 5 | `payment_method` | enum('cash','bank_transfer','mpesa','cheque','other') | NO | - | `'cash'` |  |
| 6 | `reference_no` | varchar(100) | NO | UNI | NULL |  |
| 7 | `receipt_no` | varchar(100) | NO | UNI | NULL |  |
| 8 | `payment_date` | datetime | NO | - | NULL |  |
| 9 | `notes` | text | YES | - | `NULL` |  |
| 10 | `status` | enum('recorded','posted','voided') | NO | MUL | `'recorded'` |  |
| 11 | `posted_payment_id` | int(10) unsigned | YES | - | `NULL` |  |
| 12 | `recorded_by` | int(10) unsigned | NO | - | NULL |  |
| 13 | `posted_at` | datetime | YES | - | `NULL` |  |
| 14 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 15 | `updated_at` | timestamp | YES | - | `NULL` | on update current_timestamp() |

UNIQUE/INDEX: `uq_admission_payment_receipt` (`receipt_no`); `uq_admission_payment_reference` (`reference_no`)

---
## `admission_placements`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `application_id` | int(10) unsigned | NO | UNI | NULL |  |
| 3 | `recommended_class_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 4 | `recommended_stream_id` | int(10) unsigned | YES | - | `NULL` |  |
| 5 | `final_class_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 6 | `final_stream_id` | int(10) unsigned | YES | - | `NULL` |  |
| 7 | `placement_status` | enum('pending','recommended','approved','assigned') | NO | MUL | `'pending'` |  |
| 8 | `placement_type` | enum('automatic','test_based','interview_based') | YES | - | `'automatic'` |  |
| 9 | `recommendation_notes` | text | YES | - | `NULL` |  |
| 10 | `recommended_by` | int(10) unsigned | YES | MUL | `NULL` |  |
| 11 | `recommended_at` | datetime | YES | - | `NULL` |  |
| 12 | `approved_by` | int(10) unsigned | YES | MUL | `NULL` |  |
| 13 | `approved_at` | datetime | YES | - | `NULL` |  |
| 14 | `remarks` | text | YES | - | `NULL` |  |
| 15 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 16 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

FKs: `final_class_id` → `classes`.`id`; `approved_by` → `users`.`id`; `application_id` → `admission_applications`.`id`; `recommended_class_id` → `classes`.`id`; `recommended_by` → `users`.`id`

UNIQUE/INDEX: `uk_application_placement` (`application_id`)

---
## `admission_placement_tests`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `application_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `test_code` | varchar(50) | NO | MUL | NULL |  |
| 4 | `test_date` | date | NO | MUL | NULL |  |
| 5 | `subject_area` | varchar(100) | YES | - | `NULL` |  |
| 6 | `score` | decimal(5,2) | YES | - | `NULL` |  |
| 7 | `max_score` | decimal(5,2) | YES | - | `NULL` |  |
| 8 | `percentage` | int(11) | YES | - | `NULL` |  |
| 9 | `recommendation` | enum('promote','retain','conditional') | YES | - | `NULL` |  |
| 10 | `remarks` | text | YES | - | `NULL` |  |
| 11 | `recorded_by` | int(10) unsigned | YES | MUL | `NULL` |  |
| 12 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 13 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

FKs: `recorded_by` → `users`.`id`; `application_id` → `admission_applications`.`id`

---
## `admission_process_steps`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(11) | NO | PRI | NULL | auto_increment |
| 2 | `step_number` | int(11) | NO | - | NULL |  |
| 3 | `icon` | varchar(50) | NO | - | `'bi-circle-fill'` |  |
| 4 | `color` | varchar(20) | NO | - | `'#198754'` |  |
| 5 | `title` | varchar(150) | NO | - | NULL |  |
| 6 | `description` | text | YES | - | `NULL` |  |
| 7 | `display_order` | int(11) | NO | - | `0` |  |
| 8 | `is_active` | tinyint(1) | NO | - | `1` |  |

---
## `admission_workflow_history`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `application_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `from_stage` | varchar(50) | YES | - | `NULL` |  |
| 4 | `to_stage` | varchar(50) | NO | MUL | NULL |  |
| 5 | `action` | varchar(100) | NO | - | NULL |  |
| 6 | `remarks` | text | YES | - | `NULL` |  |
| 7 | `performed_by` | int(10) unsigned | YES | MUL | `NULL` |  |
| 8 | `performed_at` | timestamp | NO | MUL | `current_timestamp()` |  |
| 9 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |

FKs: `performed_by` → `users`.`id`; `application_id` → `admission_applications`.`id`

---
## `albums`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(11) | NO | PRI | NULL | auto_increment |
| 2 | `name` | varchar(255) | NO | - | NULL |  |
| 3 | `description` | text | YES | - | `NULL` |  |
| 4 | `cover_image` | varchar(255) | YES | - | `NULL` |  |
| 5 | `created_by` | int(11) | YES | - | `NULL` |  |
| 6 | `created_at` | datetime | YES | - | `current_timestamp()` |  |
| 7 | `updated_at` | datetime | YES | - | `current_timestamp()` | on update current_timestamp() |

---
## `allowance_templates`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `name` | varchar(100) | NO | - | NULL |  |
| 3 | `description` | text | YES | - | `NULL` |  |
| 4 | `allowance_type` | enum('housing','transport','medical','hardship','responsibility','overtime','bonus','other') | NO | - | `'other'` |  |
| 5 | `amount` | decimal(10,2) | NO | - | NULL |  |
| 6 | `is_taxable` | tinyint(1) | NO | - | `1` |  |
| 7 | `department_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 8 | `staff_type_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 9 | `role_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 10 | `contract_type` | enum('permanent','contract','temporary') | YES | - | `NULL` |  |
| 11 | `status` | enum('active','inactive') | NO | MUL | `'active'` |  |
| 12 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 13 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

---
## `alumni`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `student_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `graduation_year` | int(11) | NO | MUL | NULL |  |
| 4 | `graduated_class_id` | int(10) unsigned | NO | MUL | NULL |  |
| 5 | `graduated_stream_id` | int(10) unsigned | NO | MUL | NULL |  |
| 6 | `final_enrollment_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 7 | `final_average` | decimal(5,2) | YES | - | `NULL` |  |
| 8 | `final_grade` | varchar(4) | YES | - | `NULL` |  |
| 9 | `final_class_rank` | int(11) | YES | - | `NULL` |  |
| 10 | `final_stream_rank` | int(11) | YES | - | `NULL` |  |
| 11 | `overall_rank` | int(11) | YES | - | `NULL` |  |
| 12 | `awards` | text | YES | - | `NULL` |  |
| 13 | `achievements` | text | YES | - | `NULL` |  |
| 14 | `conduct_grade` | enum('Excellent','Very Good','Good','Fair','Poor') | YES | - | `NULL` |  |
| 15 | `next_school` | varchar(255) | YES | - | `NULL` |  |
| 16 | `career_interest` | varchar(255) | YES | - | `NULL` |  |
| 17 | `contact_email` | varchar(100) | YES | - | `NULL` |  |
| 18 | `contact_phone` | varchar(20) | YES | - | `NULL` |  |
| 19 | `is_active_alumni` | tinyint(1) | YES | - | `1` |  |
| 20 | `last_contact_date` | date | YES | - | `NULL` |  |
| 21 | `alumni_notes` | text | YES | - | `NULL` |  |
| 22 | `graduation_date` | date | NO | - | NULL |  |
| 23 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 24 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

FKs: `graduated_class_id` → `classes`.`id`; `student_id` → `students`.`id`; `final_enrollment_id` → `class_enrollments`.`id`; `graduated_stream_id` → `class_streams`.`id`

UNIQUE/INDEX: `unique_student_graduation` (`student_id,graduation_year`)

---
## `announcements_bulletin`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `title` | varchar(255) | NO | - | NULL |  |
| 3 | `content` | longtext | NO | - | NULL |  |
| 4 | `announcement_type` | enum('general','academic','administrative','event','emergency','maintenance') | NO | MUL | `'general'` |  |
| 5 | `priority` | enum('low','normal','high','critical') | NO | - | `'normal'` |  |
| 6 | `target_audience` | enum('all','staff','students','parents','specific') | NO | - | `'all'` |  |
| 7 | `audience_json` | longtext | YES | - | `NULL` |  |
| 8 | `published_by` | int(10) unsigned | NO | MUL | NULL |  |
| 9 | `status` | enum('draft','scheduled','published','archived','expired') | NO | MUL | `'draft'` |  |
| 10 | `scheduled_at` | datetime | YES | - | `NULL` |  |
| 11 | `published_at` | datetime | YES | MUL | `NULL` |  |
| 12 | `expires_at` | datetime | YES | - | `NULL` |  |
| 13 | `view_count` | int(11) | NO | - | `0` |  |
| 14 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 15 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

FKs: `published_by` → `staff`.`id`

---
## `announcement_views`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `announcement_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `viewer_id` | int(10) unsigned | NO | MUL | NULL |  |
| 4 | `viewed_at` | datetime | NO | - | `current_timestamp()` |  |
| 5 | `device_info` | varchar(255) | YES | - | `NULL` |  |

FKs: `viewer_id` → `users`.`id`; `announcement_id` → `announcements_bulletin`.`id`

UNIQUE/INDEX: `uk_announcement_viewer` (`announcement_id,viewer_id`)

---
## `annual_scores`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `student_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `academic_year` | year(4) | NO | MUL | NULL |  |
| 4 | `term1_weight` | decimal(3,2) | YES | - | `0.20` |  |
| 5 | `term1_score` | decimal(5,2) | YES | - | `0.00` |  |
| 6 | `term1_grade` | varchar(4) | YES | - | `NULL` |  |
| 7 | `term2_weight` | decimal(3,2) | YES | - | `0.30` |  |
| 8 | `term2_score` | decimal(5,2) | YES | - | `0.00` |  |
| 9 | `term2_grade` | varchar(4) | YES | - | `NULL` |  |
| 10 | `term3_weight` | decimal(3,2) | YES | - | `0.50` |  |
| 11 | `term3_score` | decimal(5,2) | YES | - | `0.00` |  |
| 12 | `term3_grade` | varchar(4) | YES | - | `NULL` |  |
| 13 | `annual_score` | decimal(5,2) | YES | - | `0.00` |  |
| 14 | `annual_percentage` | decimal(5,2) | YES | - | `0.00` |  |
| 15 | `annual_grade` | varchar(4) | YES | MUL | `NULL` |  |
| 16 | `annual_points` | decimal(5,1) | YES | - | `0.0` |  |
| 17 | `annual_rank` | int(11) | YES | - | `NULL` |  |
| 18 | `grade_total_students` | int(11) | YES | - | `NULL` |  |
| 19 | `grade_percentile` | decimal(5,2) | YES | - | `NULL` |  |
| 20 | `strengths` | longtext | YES | - | `NULL` |  |
| 21 | `weaknesses` | longtext | YES | - | `NULL` |  |
| 22 | `avg_formative_percentage` | decimal(5,2) | YES | - | `0.00` |  |
| 23 | `avg_summative_percentage` | decimal(5,2) | YES | - | `0.00` |  |
| 24 | `pathway_classification` | enum('excelling','on_track','support_needed') | YES | MUL | `'on_track'` |  |
| 25 | `insights_summary` | longtext | YES | - | `NULL` |  |
| 26 | `calculated_at` | timestamp | YES | - | `NULL` |  |
| 27 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 28 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

FKs: `student_id` → `students`.`id`

UNIQUE/INDEX: `uk_student_year` (`student_id,academic_year`)

---
## `api_tokens`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `user_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `token_hash` | varchar(255) | NO | UNI | NULL |  |
| 4 | `token_name` | varchar(100) | YES | - | `NULL` |  |
| 5 | `scope` | longtext | YES | - | `NULL` |  |
| 6 | `created_date` | datetime | NO | - | NULL |  |
| 7 | `last_used_date` | datetime | YES | - | `NULL` |  |
| 8 | `expiry_date` | datetime | YES | - | `NULL` |  |
| 9 | `is_active` | tinyint(1) | NO | - | `1` |  |
| 10 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |

FKs: `user_id` → `users`.`id`

UNIQUE/INDEX: `uk_token` (`token_hash`)

---
## `arrears_settlement_plans`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `student_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `arrears_id` | int(10) unsigned | NO | MUL | NULL |  |
| 4 | `total_amount` | decimal(10,2) | NO | - | NULL |  |
| 5 | `installments` | int(11) | NO | - | `3` |  |
| 6 | `installment_amount` | decimal(10,2) | NO | - | NULL |  |
| 7 | `first_payment_date` | date | NO | - | NULL |  |
| 8 | `final_payment_date` | date | NO | - | NULL |  |
| 9 | `amount_paid` | decimal(10,2) | NO | - | `0.00` |  |
| 10 | `installments_paid` | int(11) | NO | - | `0` |  |
| 11 | `status` | enum('active','completed','defaulted','cancelled') | NO | MUL | `'active'` |  |
| 12 | `created_by` | int(10) unsigned | NO | MUL | NULL |  |
| 13 | `approved_by` | int(10) unsigned | NO | MUL | NULL |  |
| 14 | `approved_date` | datetime | NO | - | NULL |  |
| 15 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 16 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

---
## `assessment_benchmarks`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `academic_year` | year(4) | NO | MUL | NULL |  |
| 3 | `grade_level_id` | int(10) unsigned | NO | MUL | NULL |  |
| 4 | `subject_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 5 | `benchmark_type` | enum('class','grade','national') | NO | - | `'grade'` |  |
| 6 | `target_percentage` | decimal(5,2) | NO | - | NULL |  |
| 7 | `acceptable_range_min` | decimal(5,2) | NO | - | NULL |  |
| 8 | `acceptable_range_max` | decimal(5,2) | NO | - | NULL |  |
| 9 | `description` | text | YES | - | `NULL` |  |
| 10 | `created_by` | int(10) unsigned | NO | MUL | NULL |  |
| 11 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 12 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

FKs: `subject_id` → `curriculum_units`.`id`; `grade_level_id` → `school_levels`.`id`; `created_by` → `users`.`id`

UNIQUE/INDEX: `uk_benchmark` (`academic_year,grade_level_id,subject_id,benchmark_type`)

---
## `assessment_history`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | bigint(20) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `assessment_result_id` | int(10) unsigned | NO | - | NULL |  |
| 3 | `student_id` | int(10) unsigned | NO | MUL | NULL |  |
| 4 | `assessment_id` | int(10) unsigned | NO | MUL | NULL |  |
| 5 | `old_marks` | decimal(6,2) | YES | - | `NULL` |  |
| 6 | `new_marks` | decimal(6,2) | NO | - | NULL |  |
| 7 | `old_grade` | varchar(4) | YES | - | `NULL` |  |
| 8 | `new_grade` | varchar(4) | YES | - | `NULL` |  |
| 9 | `change_reason` | varchar(255) | YES | - | `NULL` |  |
| 10 | `changed_by` | int(10) unsigned | NO | - | NULL |  |
| 11 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |

FKs: `student_id` → `students`.`id`; `assessment_id` → `assessments`.`id`

---
## `assessment_results`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `assessment_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `student_id` | int(10) unsigned | NO | MUL | NULL |  |
| 4 | `marks_obtained` | decimal(6,2) | NO | - | NULL |  |
| 5 | `grade` | varchar(4) | YES | - | `NULL` |  |
| 6 | `points` | decimal(3,1) | YES | - | `NULL` |  |
| 7 | `remarks` | varchar(255) | YES | - | `NULL` |  |
| 8 | `peer_feedback` | text | YES | - | `NULL` |  |
| 9 | `submitted_at` | timestamp | YES | - | `NULL` |  |
| 10 | `is_submitted` | tinyint(1) | NO | MUL | `0` |  |
| 11 | `is_approved` | tinyint(1) | NO | MUL | `0` |  |
| 12 | `responder_type` | enum('teacher','self','peer') | NO | MUL | `'teacher'` |  |
| 13 | `responder_id` | int(10) unsigned | YES | - | `NULL` |  |
| 14 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |

UNIQUE/INDEX: `uk_assessment_student` (`assessment_id,student_id`)

---
## `assessment_rubrics`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `tool_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `criteria_name` | varchar(255) | NO | - | NULL |  |
| 4 | `level_1_descriptor` | varchar(500) | YES | - | `NULL` |  |
| 5 | `level_2_descriptor` | varchar(500) | YES | - | `NULL` |  |
| 6 | `level_3_descriptor` | varchar(500) | YES | - | `NULL` |  |
| 7 | `level_4_descriptor` | varchar(500) | YES | - | `NULL` |  |
| 8 | `points_per_level` | int(11) | YES | - | `0` |  |
| 9 | `sort_order` | int(11) | NO | - | `1` |  |
| 10 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |

FKs: `tool_id` → `assessment_tools`.`id`

---
## `assessments`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `class_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `subject_id` | int(10) unsigned | NO | MUL | NULL |  |
| 4 | `term_id` | int(10) unsigned | NO | MUL | NULL |  |
| 5 | `title` | varchar(255) | NO | - | NULL |  |
| 6 | `max_marks` | decimal(6,2) | NO | - | NULL |  |
| 7 | `assessment_date` | date | NO | MUL | NULL |  |
| 8 | `assigned_by` | int(10) unsigned | NO | MUL | NULL |  |
| 9 | `status` | enum('pending_submission','submitted','pending_approval','approved') | NO | MUL | `'pending_submission'` |  |
| 10 | `approved_by` | int(10) unsigned | YES | MUL | `NULL` |  |
| 11 | `approved_at` | timestamp | YES | - | `NULL` |  |
| 12 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 13 | `assessment_type_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 14 | `learning_outcome_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 15 | `strand_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 16 | `sub_strand_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 17 | `coverage_id` | int(10) unsigned | YES | MUL | `NULL` |  |

FKs: `assessment_type_id` → `assessment_types`.`id`; `learning_outcome_id` → `learning_outcomes`.`id`

---
## `assessment_tools`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `tool_name` | varchar(255) | NO | - | NULL |  |
| 3 | `tool_code` | varchar(50) | YES | UNI | `NULL` |  |
| 4 | `description` | text | YES | - | `NULL` |  |
| 5 | `assessment_type_id` | int(10) unsigned | NO | MUL | NULL |  |
| 6 | `learning_area_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 7 | `grade_level` | varchar(20) | YES | MUL | `NULL` |  |
| 8 | `competencies_assessed` | text | YES | - | `NULL` |  |
| 9 | `file_url` | varchar(500) | YES | - | `NULL` |  |
| 10 | `created_by` | int(10) unsigned | NO | MUL | NULL |  |
| 11 | `status` | enum('active','archived','deprecated') | NO | - | `'active'` |  |
| 12 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |

FKs: `created_by` → `users`.`id`; `assessment_type_id` → `assessment_type_classifications`.`id`

UNIQUE/INDEX: `uk_tool_code` (`tool_code`)

---
## `assessment_type_classifications`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `code` | varchar(10) | NO | UNI | NULL |  |
| 3 | `name` | varchar(50) | NO | - | NULL |  |
| 4 | `description` | text | YES | - | `NULL` |  |
| 5 | `is_national` | tinyint(1) | NO | - | `0` |  |
| 6 | `is_knec_managed` | tinyint(1) | NO | - | `0` |  |
| 7 | `knec_portal_code` | varchar(50) | YES | - | `NULL` |  |
| 8 | `grade_applicable` | varchar(50) | YES | - | `NULL` |  |
| 9 | `status` | enum('active','inactive') | NO | - | `'active'` |  |
| 10 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |

UNIQUE/INDEX: `uk_code` (`code`)

---
## `assessment_types`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `name` | varchar(50) | NO | UNI | NULL |  |
| 3 | `description` | text | YES | - | `NULL` |  |
| 4 | `is_formative` | tinyint(1) | NO | - | `0` |  |
| 5 | `is_summative` | tinyint(1) | NO | - | `0` |  |
| 6 | `status` | enum('active','inactive') | NO | - | `'active'` |  |
| 7 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |

UNIQUE/INDEX: `uk_name` (`name`)

---
## `asset_categories`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `code` | varchar(20) | NO | UNI | NULL |  |
| 3 | `name` | varchar(100) | NO | - | NULL |  |
| 4 | `description` | text | YES | - | `NULL` |  |
| 5 | `depreciation_method` | enum('straight_line','reducing_balance','none') | NO | - | `'straight_line'` |  |
| 6 | `useful_life_years` | tinyint(4) | NO | - | `5` |  |
| 7 | `depreciation_rate` | decimal(5,2) | NO | - | `20.00` |  |
| 8 | `residual_value_pct` | decimal(5,2) | NO | - | `0.00` |  |
| 9 | `status` | enum('active','inactive') | NO | - | `'active'` |  |
| 10 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |

UNIQUE/INDEX: `uq_asset_category_code` (`code`)

---
## `asset_disposals`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `asset_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `disposal_date` | date | NO | - | NULL |  |
| 4 | `disposal_type` | enum('sale','scrap','donation','theft','loss','write_off') | NO | - | NULL |  |
| 5 | `book_value_at_disposal` | decimal(15,2) | NO | - | NULL |  |
| 6 | `proceeds` | decimal(15,2) | NO | - | `0.00` |  |
| 7 | `gain_loss` | decimal(15,2) | YES | - | `NULL` | STORED GENERATED |
| 8 | `buyer_name` | varchar(255) | YES | - | `NULL` |  |
| 9 | `reason` | text | NO | - | NULL |  |
| 10 | `authorised_by` | int(10) unsigned | NO | - | NULL |  |
| 11 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |

---
## `assignments`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `title` | varchar(255) | NO | - | NULL |  |
| 3 | `description` | text | YES | - | `NULL` |  |
| 4 | `subject_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 5 | `learning_area_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 6 | `strand_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 7 | `sub_strand_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 8 | `coverage_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 9 | `class_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 10 | `teacher_id` | int(10) unsigned | NO | MUL | NULL |  |
| 11 | `academic_year_id` | int(10) unsigned | YES | - | `NULL` |  |
| 12 | `term_id` | int(10) unsigned | YES | - | `NULL` |  |
| 13 | `due_date` | datetime | NO | MUL | NULL |  |
| 14 | `total_marks` | decimal(6,2) | NO | - | `100.00` |  |
| 15 | `attachment_url` | varchar(512) | YES | - | `NULL` |  |
| 16 | `status` | enum('draft','published','closed','archived') | NO | MUL | `'draft'` |  |
| 17 | `created_at` | datetime | NO | - | `current_timestamp()` |  |
| 18 | `updated_at` | datetime | NO | - | `current_timestamp()` | on update current_timestamp() |
| 19 | `deleted_at` | datetime | YES | - | `NULL` |  |

---
## `assignment_submissions`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `assignment_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `student_id` | int(10) unsigned | NO | MUL | NULL |  |
| 4 | `submitted_at` | datetime | YES | - | `NULL` |  |
| 5 | `submission_text` | text | YES | - | `NULL` |  |
| 6 | `attachment_url` | varchar(512) | YES | - | `NULL` |  |
| 7 | `marks_awarded` | decimal(6,2) | YES | - | `NULL` |  |
| 8 | `grade` | varchar(5) | YES | - | `NULL` |  |
| 9 | `feedback` | text | YES | - | `NULL` |  |
| 10 | `graded_by` | int(10) unsigned | YES | - | `NULL` |  |
| 11 | `graded_at` | datetime | YES | - | `NULL` |  |
| 12 | `status` | enum('pending','submitted','late','graded','excused') | NO | MUL | `'pending'` |  |
| 13 | `created_at` | datetime | NO | - | `current_timestamp()` |  |
| 14 | `updated_at` | datetime | NO | - | `current_timestamp()` | on update current_timestamp() |

UNIQUE/INDEX: `uq_submission` (`assignment_id,student_id`)

---
## `attendance_sessions`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `code` | varchar(20) | NO | UNI | NULL |  |
| 3 | `name` | varchar(100) | NO | - | NULL |  |
| 4 | `description` | text | YES | - | `NULL` |  |
| 5 | `session_type` | enum('academic','boarding','activity') | NO | - | `'academic'` |  |
| 6 | `start_time` | time | NO | - | NULL |  |
| 7 | `end_time` | time | NO | - | NULL |  |
| 8 | `applies_to` | enum('all','day_only','boarders_only') | NO | - | `'all'` |  |
| 9 | `applicable_days` | longtext | YES | - | `NULL` |  |
| 10 | `is_mandatory` | tinyint(1) | YES | - | `1` |  |
| 11 | `display_order` | int(11) | YES | - | `0` |  |
| 12 | `status` | enum('active','inactive') | YES | - | `'active'` |  |
| 13 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |

UNIQUE/INDEX: `code` (`code`)

---
## `audit_logs`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `action` | varchar(50) | NO | MUL | NULL |  |
| 3 | `entity` | varchar(50) | NO | MUL | NULL |  |
| 4 | `entity_id` | int(10) unsigned | YES | - | `NULL` |  |
| 5 | `user_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 6 | `ip_address` | varchar(45) | YES | - | `NULL` |  |
| 7 | `user_agent` | varchar(255) | YES | - | `NULL` |  |
| 8 | `details` | text | YES | - | `NULL` |  |
| 9 | `status` | enum('success','failure') | YES | MUL | `'success'` |  |
| 10 | `created_at` | timestamp | NO | MUL | `current_timestamp()` |  |

FKs: `user_id` → `users`.`id`

---
## `audit_trail`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | bigint(20) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `user_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 3 | `table_name` | varchar(100) | NO | MUL | NULL |  |
| 4 | `record_id` | int(10) unsigned | NO | - | NULL |  |
| 5 | `action` | enum('insert','update','delete') | NO | - | NULL |  |
| 6 | `old_values` | longtext | YES | - | `NULL` |  |
| 7 | `new_values` | longtext | YES | - | `NULL` |  |
| 8 | `ip_address` | varchar(45) | YES | - | `NULL` |  |
| 9 | `user_agent` | varchar(255) | YES | - | `NULL` |  |
| 10 | `created_at` | timestamp | NO | MUL | `current_timestamp()` |  |

---
## `auth_sessions`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `user_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `token` | varchar(255) | NO | UNI | NULL |  |
| 4 | `ip_address` | varchar(45) | YES | - | `NULL` |  |
| 5 | `user_agent` | varchar(255) | YES | - | `NULL` |  |
| 6 | `payload` | longtext | YES | - | `NULL` |  |
| 7 | `last_activity` | timestamp | NO | MUL | `current_timestamp()` | on update current_timestamp() |
| 8 | `expires_at` | timestamp | NO | - | `'0000-00-00 00:00:00'` |  |
| 9 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |

FKs: `user_id` → `users`.`id`

UNIQUE/INDEX: `uk_token` (`token`)

---
## `bank_accounts`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `name` | varchar(255) | NO | - | NULL |  |
| 3 | `account_no` | varchar(100) | NO | UNI | NULL |  |
| 4 | `bank_name` | varchar(255) | YES | - | `NULL` |  |
| 5 | `is_active` | tinyint(1) | YES | - | `1` |  |
| 6 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 7 | `updated_at` | timestamp | YES | - | `NULL` | on update current_timestamp() |

UNIQUE/INDEX: `ux_account_no` (`account_no`)

---
## `bank_transactions`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `transaction_ref` | varchar(100) | NO | UNI | NULL |  |
| 3 | `student_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 4 | `amount` | decimal(10,2) | NO | - | NULL |  |
| 5 | `transaction_date` | datetime | NO | - | NULL |  |
| 6 | `bank_name` | varchar(100) | YES | - | `NULL` |  |
| 7 | `account_number` | varchar(50) | YES | - | `NULL` |  |
| 8 | `narration` | varchar(255) | YES | - | `NULL` |  |
| 9 | `sender_name` | varchar(255) | YES | MUL | `NULL` |  |
| 10 | `sender_phone` | varchar(20) | YES | MUL | `NULL` |  |
| 11 | `sender_account` | varchar(50) | YES | - | `NULL` |  |
| 12 | `bank_reference` | varchar(100) | YES | - | `NULL` |  |
| 13 | `cheque_number` | varchar(50) | YES | - | `NULL` |  |
| 14 | `source_type` | enum('api_callback','statement_import','manual_entry') | YES | - | `'manual_entry'` |  |
| 15 | `matched_mpesa_code` | varchar(50) | YES | MUL | `NULL` |  |
| 16 | `webhook_data` | longtext | YES | - | `NULL` |  |
| 17 | `status` | enum('pending','processed','failed') | NO | - | `'pending'` |  |
| 18 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |

FKs: `student_id` → `students`.`id`

UNIQUE/INDEX: `uk_transaction_ref` (`transaction_ref`)

---
## `blocked_devices`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | bigint(20) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `user_agent_pattern` | varchar(255) | NO | MUL | NULL |  |
| 3 | `reason` | varchar(255) | NO | - | NULL |  |
| 4 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 5 | `created_by` | int(10) unsigned | YES | - | `NULL` |  |

---
## `blocked_ips`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | bigint(20) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `ip_address` | varchar(45) | NO | UNI | NULL |  |
| 3 | `reason` | varchar(255) | NO | - | NULL |  |
| 4 | `expires_at` | timestamp | YES | MUL | `NULL` |  |
| 5 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 6 | `created_by` | int(10) unsigned | YES | - | `NULL` |  |

UNIQUE/INDEX: `uk_blocked_ip` (`ip_address`)

---
## `boarding_attendance`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `student_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `dormitory_id` | int(10) unsigned | NO | MUL | NULL |  |
| 4 | `date` | date | NO | - | NULL |  |
| 5 | `session_id` | int(10) unsigned | NO | MUL | NULL |  |
| 6 | `status` | enum('present','absent','permission','sick_bay','unknown') | NO | MUL | `'present'` |  |
| 7 | `check_time` | time | YES | - | `NULL` |  |
| 8 | `location_verified` | tinyint(1) | YES | - | `0` |  |
| 9 | `absence_reason` | text | YES | - | `NULL` |  |
| 10 | `permission_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 11 | `marked_by` | int(10) unsigned | NO | MUL | NULL |  |
| 12 | `notes` | text | YES | - | `NULL` |  |
| 13 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 14 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

FKs: `student_id` → `students`.`id`; `marked_by` → `staff`.`id`; `permission_id` → `student_permissions`.`id`; `session_id` → `attendance_sessions`.`id`; `dormitory_id` → `dormitories`.`id`

UNIQUE/INDEX: `unique_boarding_attendance` (`student_id,date,session_id`)

---
## `budget_amendments`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `budget_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `line_item_id` | int(10) unsigned | YES | - | `NULL` |  |
| 4 | `amendment_type` | enum('reallocation','supplementary','reduction') | NO | - | NULL |  |
| 5 | `amount_change` | decimal(15,2) | NO | - | NULL |  |
| 6 | `reason` | text | NO | - | NULL |  |
| 7 | `status` | enum('pending','approved','rejected') | NO | - | `'pending'` |  |
| 8 | `requested_by` | int(10) unsigned | NO | - | NULL |  |
| 9 | `approved_by` | int(10) unsigned | YES | - | `NULL` |  |
| 10 | `approved_at` | datetime | YES | - | `NULL` |  |
| 11 | `rejection_reason` | text | YES | - | `NULL` |  |
| 12 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |

---
## `budget_line_items`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `budget_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `category_id` | int(10) unsigned | NO | MUL | NULL |  |
| 4 | `description` | varchar(255) | YES | - | `NULL` |  |
| 5 | `allocated_amount` | decimal(15,2) | NO | - | `0.00` |  |
| 6 | `spent_amount` | decimal(15,2) | NO | - | `0.00` |  |
| 7 | `committed_amount` | decimal(15,2) | NO | - | `0.00` |  |
| 8 | `notes` | text | YES | - | `NULL` |  |
| 9 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 10 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

---
## `budgets`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `name` | varchar(255) | NO | - | NULL |  |
| 3 | `academic_year` | year(4) | NO | MUL | NULL |  |
| 4 | `term` | tinyint(4) | YES | - | `NULL` |  |
| 5 | `total_amount` | decimal(15,2) | NO | - | `0.00` |  |
| 6 | `description` | text | YES | - | `NULL` |  |
| 7 | `status` | enum('draft','submitted','under_review','approved','active','closed','rejected') | NO | MUL | `'draft'` |  |
| 8 | `created_by` | int(10) unsigned | NO | - | NULL |  |
| 9 | `submitted_by` | int(10) unsigned | YES | - | `NULL` |  |
| 10 | `submitted_at` | datetime | YES | - | `NULL` |  |
| 11 | `reviewed_by` | int(10) unsigned | YES | - | `NULL` |  |
| 12 | `reviewed_at` | datetime | YES | - | `NULL` |  |
| 13 | `approved_by` | int(10) unsigned | YES | - | `NULL` |  |
| 14 | `approved_at` | datetime | YES | - | `NULL` |  |
| 15 | `activated_at` | datetime | YES | - | `NULL` |  |
| 16 | `closed_at` | datetime | YES | - | `NULL` |  |
| 17 | `review_notes` | text | YES | - | `NULL` |  |
| 18 | `approval_notes` | text | YES | - | `NULL` |  |
| 19 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 20 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

---
## `business_rule_violations_log`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `rule_code` | varchar(50) | NO | MUL | NULL |  |
| 3 | `rule_description` | varchar(255) | NO | - | NULL |  |
| 4 | `entity_type` | enum('student','staff','transaction','enrollment','payroll') | NO | MUL | NULL |  |
| 5 | `entity_id` | int(10) unsigned | NO | - | NULL |  |
| 6 | `triggered_by` | int(10) unsigned | YES | - | `NULL` |  |
| 7 | `action_attempted` | varchar(100) | YES | - | `NULL` |  |
| 8 | `violation_data` | longtext | YES | - | `NULL` |  |
| 9 | `resolved` | tinyint(1) | YES | MUL | `0` |  |
| 10 | `resolved_by` | int(10) unsigned | YES | - | `NULL` |  |
| 11 | `resolved_at` | datetime | YES | - | `NULL` |  |
| 12 | `override_reason` | text | YES | - | `NULL` |  |
| 13 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |

---
## `careers_benefits`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(11) | NO | PRI | NULL | auto_increment |
| 2 | `icon` | varchar(50) | NO | - | `'bi-check-circle'` |  |
| 3 | `title` | varchar(150) | NO | - | NULL |  |
| 4 | `description` | text | YES | - | `NULL` |  |
| 5 | `display_order` | int(11) | NO | - | `0` |  |
| 6 | `is_active` | tinyint(1) | NO | - | `1` |  |

---
## `cash_reconciliation_sessions`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `reconciliation_date` | date | NO | MUL | NULL |  |
| 3 | `system_cash_total` | decimal(15,2) | NO | - | NULL |  |
| 4 | `physical_cash_count` | decimal(15,2) | NO | - | NULL |  |
| 5 | `variance` | decimal(15,2) | YES | - | `NULL` | STORED GENERATED |
| 6 | `variance_reason` | text | YES | - | `NULL` |  |
| 7 | `status` | enum('draft','approved','escalated') | NO | - | `'draft'` |  |
| 8 | `cashier_id` | int(10) unsigned | NO | - | NULL |  |
| 9 | `approved_by` | int(10) unsigned | YES | - | `NULL` |  |
| 10 | `approved_at` | datetime | YES | - | `NULL` |  |
| 11 | `notes` | text | YES | - | `NULL` |  |
| 12 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |

UNIQUE/INDEX: `uq_cash_recon_date` (`reconciliation_date,cashier_id`)

---
## `catering_meal_statuses`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `student_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `meal_date` | date | NO | MUL | NULL |  |
| 4 | `meal_type` | enum('breakfast','lunch','supper','snack') | NO | MUL | NULL |  |
| 5 | `status` | enum('eating','not_eating','on_leave','sick_meal','fasting','special_diet') | NO | MUL | `'eating'` |  |
| 6 | `notes` | text | YES | - | `NULL` |  |
| 7 | `recorded_by` | int(10) unsigned | YES | - | `NULL` |  |
| 8 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 9 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

FKs: `student_id` → `students`.`id`

UNIQUE/INDEX: `uk_student_meal_date` (`student_id,meal_date,meal_type`)

---
## `class_curriculum_coverages`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `class_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `stream_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 4 | `class_stream_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 5 | `learning_area_id` | int(10) unsigned | NO | MUL | NULL |  |
| 6 | `strand_id` | int(10) unsigned | NO | MUL | NULL |  |
| 7 | `sub_strand_id` | int(10) unsigned | NO | MUL | NULL |  |
| 8 | `teacher_id` | int(10) unsigned | NO | MUL | NULL |  |
| 9 | `academic_year_id` | int(10) unsigned | NO | MUL | NULL |  |
| 10 | `term_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 11 | `status` | enum('planned','in_progress','covered','skipped') | NO | MUL | `'planned'` |  |
| 12 | `planned_weeks` | tinyint(3) unsigned | YES | - | `NULL` |  |
| 13 | `covered_at` | datetime | YES | - | `NULL` |  |
| 14 | `notes` | text | YES | - | `NULL` |  |
| 15 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 16 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

FKs: `class_stream_id` → `class_streams`.`id`; `term_id` → `academic_terms`.`id`; `class_id` → `classes`.`id`; `teacher_id` → `staff`.`id`; `learning_area_id` → `learning_areas`.`id`; `sub_strand_id` → `sub_strands`.`id`; `stream_id` → `class_streams`.`id`; `strand_id` → `strands`.`id`; `academic_year_id` → `academic_years`.`id`

UNIQUE/INDEX: `uq_coverage` (`class_id,stream_id,class_stream_id,sub_strand_id,academic_year_id,term_id`)

---
## `class_enrollments`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `student_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `academic_year_id` | int(10) unsigned | NO | MUL | NULL |  |
| 4 | `class_id` | int(10) unsigned | NO | MUL | NULL |  |
| 5 | `stream_id` | int(10) unsigned | NO | MUL | NULL |  |
| 6 | `class_assignment_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 7 | `enrollment_date` | date | NO | - | NULL |  |
| 8 | `enrollment_status` | enum('pending','active','completed','withdrawn','transferred','graduated') | YES | MUL | `'active'` |  |
| 9 | `term1_average` | decimal(5,2) | YES | - | `NULL` |  |
| 10 | `term2_average` | decimal(5,2) | YES | - | `NULL` |  |
| 11 | `term3_average` | decimal(5,2) | YES | - | `NULL` |  |
| 12 | `year_average` | decimal(5,2) | YES | - | `NULL` |  |
| 13 | `overall_grade` | varchar(4) | YES | - | `NULL` |  |
| 14 | `class_rank` | int(11) | YES | - | `NULL` |  |
| 15 | `stream_rank` | int(11) | YES | - | `NULL` |  |
| 16 | `days_present` | int(11) | YES | - | `0` |  |
| 17 | `days_absent` | int(11) | YES | - | `0` |  |
| 18 | `days_late` | int(11) | YES | - | `0` |  |
| 19 | `attendance_percentage` | decimal(5,2) | YES | - | `NULL` |  |
| 20 | `teacher_comments` | text | YES | - | `NULL` |  |
| 21 | `head_teacher_comments` | text | YES | - | `NULL` |  |
| 22 | `special_notes` | text | YES | - | `NULL` |  |
| 23 | `promoted_to_class_id` | int(10) unsigned | YES | - | `NULL` |  |
| 24 | `promoted_to_stream_id` | int(10) unsigned | YES | - | `NULL` |  |
| 25 | `promotion_status` | enum('pending','promoted','retained','transferred','graduated','withdrawn') | YES | MUL | `NULL` |  |
| 26 | `promotion_date` | date | YES | - | `NULL` |  |
| 27 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 28 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |
| 29 | `completed_at` | timestamp | YES | - | `NULL` |  |

FKs: `academic_year_id` → `academic_years`.`id`; `student_id` → `students`.`id`; `class_assignment_id` → `class_year_assignments`.`id`; `stream_id` → `class_streams`.`id`; `class_id` → `classes`.`id`

UNIQUE/INDEX: `unique_student_year` (`student_id,academic_year_id`)

---
## `classes`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `name` | varchar(50) | NO | MUL | NULL |  |
| 3 | `level_id` | int(10) unsigned | NO | MUL | NULL |  |
| 4 | `grade_level` | varchar(20) | YES | - | `NULL` |  |
| 5 | `teacher_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 6 | `capacity` | int(11) | NO | - | `40` |  |
| 7 | `room_number` | varchar(20) | YES | - | `NULL` |  |
| 8 | `academic_year` | year(4) | NO | - | NULL |  |
| 9 | `status` | enum('active','inactive','completed') | NO | MUL | `'active'` |  |
| 10 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 11 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

FKs: `teacher_id` → `staff`.`id`; `level_id` → `school_levels`.`id`

UNIQUE/INDEX: `uk_name_year` (`name,academic_year`)

---
## `class_promotion_queue`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `batch_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `class_id` | int(10) unsigned | NO | MUL | NULL |  |
| 4 | `stream_id` | int(10) unsigned | NO | MUL | NULL |  |
| 5 | `approval_status` | enum('pending','reviewing','approved','partially_approved','rejected','hold') | NO | MUL | `'pending'` |  |
| 6 | `total_in_class` | int(11) | YES | - | `0` |  |
| 7 | `approved_count` | int(11) | YES | - | `0` |  |
| 8 | `rejected_count` | int(11) | YES | - | `0` |  |
| 9 | `pending_count` | int(11) | YES | - | `0` |  |
| 10 | `assigned_to_user_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 11 | `notes` | text | YES | - | `NULL` |  |
| 12 | `reviewed_at` | datetime | YES | - | `NULL` |  |
| 13 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 14 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

FKs: `batch_id` → `promotion_batches`.`id`; `assigned_to_user_id` → `users`.`id`; `stream_id` → `class_streams`.`id`; `class_id` → `classes`.`id`

UNIQUE/INDEX: `uk_batch_class_stream` (`batch_id,class_id,stream_id`)

---
## `class_schedules`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `class_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `day_of_week` | enum('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday') | NO | MUL | NULL |  |
| 4 | `start_time` | time | NO | - | NULL |  |
| 5 | `end_time` | time | NO | - | NULL |  |
| 6 | `subject_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 7 | `teacher_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 8 | `room_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 9 | `academic_year_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 10 | `term_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 11 | `period_number` | tinyint(3) unsigned | YES | - | `NULL` |  |
| 12 | `status` | enum('active','inactive') | NO | - | `'active'` |  |
| 13 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 14 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

FKs: `room_id` → `rooms`.`id`; `teacher_id` → `staff`.`id`; `subject_id` → `curriculum_units`.`id`; `class_id` → `classes`.`id`

UNIQUE/INDEX: `idx_no_class_overlap` (`class_id,day_of_week,start_time,academic_year_id,term_id`)

---
## `class_streams`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `class_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `stream_name` | varchar(50) | NO | - | NULL |  |
| 4 | `capacity` | int(11) | NO | - | NULL |  |
| 5 | `teacher_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 6 | `status` | enum('active','inactive') | NO | MUL | `'active'` |  |
| 7 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 8 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |
| 9 | `current_students` | int(11) | NO | - | `0` |  |

FKs: `teacher_id` → `staff`.`id`; `class_id` → `classes`.`id`

UNIQUE/INDEX: `uk_class_stream` (`class_id,stream_name`)

---
## `class_year_assignments`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `academic_year_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `class_id` | int(10) unsigned | NO | MUL | NULL |  |
| 4 | `stream_id` | int(10) unsigned | NO | MUL | NULL |  |
| 5 | `teacher_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 6 | `room_number` | varchar(50) | YES | - | `NULL` |  |
| 7 | `capacity` | int(11) | YES | - | `40` |  |
| 8 | `current_enrollment` | int(11) | YES | - | `0` |  |
| 9 | `fee_structure_id` | int(10) unsigned | YES | - | `NULL` |  |
| 10 | `status` | enum('planning','active','completed') | YES | MUL | `'planning'` |  |
| 11 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 12 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

FKs: `academic_year_id` → `academic_years`.`id`; `teacher_id` → `staff`.`id`; `stream_id` → `class_streams`.`id`; `class_id` → `classes`.`id`

UNIQUE/INDEX: `unique_class_stream_year` (`academic_year_id,class_id,stream_id`)

---
## `communication_attachments`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `communication_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `file_name` | varchar(255) | NO | - | NULL |  |
| 4 | `file_path` | varchar(255) | NO | - | NULL |  |
| 5 | `uploaded_at` | timestamp | NO | - | `current_timestamp()` |  |

FKs: `communication_id` → `communications`.`id`

---
## `communication_groups`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `name` | varchar(100) | NO | - | NULL |  |
| 3 | `description` | text | YES | - | `NULL` |  |
| 4 | `type` | enum('staff','students','parents','custom') | NO | MUL | NULL |  |
| 5 | `created_by` | int(10) unsigned | NO | MUL | NULL |  |
| 6 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 7 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

FKs: `created_by` → `users`.`id`

---
## `communication_logs`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `communication_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `recipient_id` | int(10) unsigned | NO | MUL | NULL |  |
| 4 | `event_type` | enum('queued','sent','delivered','failed','opened','clicked') | NO | MUL | NULL |  |
| 5 | `details` | longtext | YES | - | `NULL` |  |
| 6 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |

FKs: `recipient_id` → `communication_recipients`.`id`; `communication_id` → `communications`.`id`

---
## `communication_recipients`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `communication_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `recipient_id` | int(10) unsigned | NO | MUL | NULL |  |
| 4 | `status` | enum('pending','delivered','failed') | NO | - | `'pending'` |  |
| 5 | `delivered_at` | datetime | YES | - | `NULL` |  |
| 6 | `delivery_attempts` | int(10) unsigned | NO | - | `0` |  |
| 7 | `last_attempt_at` | datetime | YES | - | `NULL` |  |
| 8 | `error_message` | text | YES | - | `NULL` |  |
| 9 | `opened_at` | datetime | YES | - | `NULL` |  |
| 10 | `clicked_at` | datetime | YES | - | `NULL` |  |
| 11 | `device_info` | varchar(255) | YES | - | `NULL` |  |

FKs: `recipient_id` → `users`.`id`; `communication_id` → `communications`.`id`

---
## `communications`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `sender_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 3 | `subject` | varchar(255) | NO | - | NULL |  |
| 4 | `content` | text | NO | - | NULL |  |
| 5 | `type` | enum('email','sms','notification','internal','whatsapp') | NO | - | `'email'` |  |
| 6 | `category` | varchar(50) | YES | - | `NULL` |  |
| 7 | `status` | enum('draft','sent','scheduled','failed') | NO | - | `'draft'` |  |
| 8 | `priority` | enum('low','medium','high') | NO | - | `'medium'` |  |
| 9 | `template_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 10 | `scheduled_at` | datetime | YES | - | `NULL` |  |
| 11 | `reminder_at` | datetime | YES | - | `NULL` |  |
| 12 | `sender_signature` | text | YES | - | `NULL` |  |
| 13 | `created_at` | timestamp | NO | MUL | `current_timestamp()` |  |

FKs: `template_id` → `message_templates`.`id`; `sender_id` → `users`.`id`

---
## `communication_templates`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `name` | varchar(255) | NO | - | NULL |  |
| 3 | `template_type` | enum('internal_message','sms','email','announcement') | NO | MUL | `'sms'` |  |
| 4 | `category` | varchar(100) | YES | MUL | `NULL` |  |
| 5 | `subject` | varchar(255) | YES | - | `NULL` |  |
| 6 | `template_body` | longtext | NO | - | NULL |  |
| 7 | `variables_json` | longtext | YES | - | `NULL` |  |
| 8 | `example_output` | text | YES | - | `NULL` |  |
| 9 | `created_by` | int(10) unsigned | NO | MUL | NULL |  |
| 10 | `status` | enum('active','inactive','archived') | NO | MUL | `'active'` |  |
| 11 | `usage_count` | int(11) | NO | - | `0` |  |
| 12 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 13 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

---
## `communication_workflow_instances`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `communication_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `workflow_code` | varchar(50) | NO | MUL | NULL |  |
| 4 | `current_stage` | varchar(50) | NO | - | NULL |  |
| 5 | `stage_code` | varchar(50) | YES | - | `NULL` |  |
| 6 | `status` | enum('active','completed','cancelled','escalated') | NO | - | `'active'` |  |
| 7 | `initiated_by` | int(10) unsigned | NO | - | NULL |  |
| 8 | `initiated_at` | timestamp | NO | - | `current_timestamp()` |  |
| 9 | `completed_at` | timestamp | YES | - | `NULL` |  |

---
## `conduct_tracking`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | bigint(20) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `student_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `academic_year` | year(4) | NO | - | NULL |  |
| 4 | `term_id` | int(10) unsigned | NO | MUL | NULL |  |
| 5 | `conduct_rating` | enum('excellent','good','satisfactory','needs_improvement','poor') | NO | MUL | `'good'` |  |
| 6 | `conduct_comments` | text | YES | - | `NULL` |  |
| 7 | `behavior_incidents` | text | YES | - | `NULL` |  |
| 8 | `teacher_notes` | text | YES | - | `NULL` |  |
| 9 | `recorded_by` | int(10) unsigned | NO | MUL | NULL |  |
| 10 | `recorded_date` | date | NO | - | NULL |  |
| 11 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 12 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

FKs: `recorded_by` → `users`.`id`; `term_id` → `academic_terms`.`id`; `student_id` → `students`.`id`

UNIQUE/INDEX: `uk_conduct` (`student_id,academic_year,term_id`)

---
## `config_sync_log`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `config_type` | enum('routes','menus','dashboards','policies','permissions','all') | NO | MUL | NULL |  |
| 3 | `file_path` | varchar(500) | NO | - | NULL |  |
| 4 | `checksum` | varchar(64) | NO | - | NULL |  |
| 5 | `records_count` | int(11) | NO | - | NULL |  |
| 6 | `sync_status` | enum('success','failed','partial') | NO | MUL | `'success'` |  |
| 7 | `error_message` | text | YES | - | `NULL` |  |
| 8 | `synced_by` | int(10) unsigned | YES | MUL | `NULL` |  |
| 9 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |

FKs: `synced_by` → `users`.`id`

---
## `contact_directory`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `contact_type` | enum('staff','student','parent','department','external') | NO | MUL | NULL |  |
| 3 | `linked_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 4 | `name` | varchar(255) | NO | - | NULL |  |
| 5 | `email` | varchar(255) | YES | - | `NULL` |  |
| 6 | `phone` | varchar(50) | YES | - | `NULL` |  |
| 7 | `department` | varchar(100) | YES | - | `NULL` |  |
| 8 | `role` | varchar(100) | YES | - | `NULL` |  |
| 9 | `notes` | text | YES | - | `NULL` |  |
| 10 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |

---
## `contact_inquiries`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(11) | NO | PRI | NULL | auto_increment |
| 2 | `full_name` | varchar(150) | NO | - | NULL |  |
| 3 | `email` | varchar(200) | NO | - | NULL |  |
| 4 | `phone` | varchar(30) | YES | - | `NULL` |  |
| 5 | `subject` | varchar(150) | YES | - | `NULL` |  |
| 6 | `message` | text | NO | - | NULL |  |
| 7 | `status` | enum('new','read','replied') | NO | - | `'new'` |  |
| 8 | `ip_address` | varchar(45) | YES | - | `NULL` |  |
| 9 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 10 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

---
## `conversation_participants`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `conversation_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `participant_id` | int(10) unsigned | NO | MUL | NULL |  |
| 4 | `unread_count` | int(11) | NO | - | `0` |  |
| 5 | `last_read_at` | datetime | YES | - | `NULL` |  |
| 6 | `is_muted` | tinyint(1) | NO | - | `0` |  |
| 7 | `joined_at` | timestamp | NO | - | `current_timestamp()` |  |
| 8 | `left_at` | datetime | YES | - | `NULL` |  |
| 9 | `role` | enum('participant','moderator','admin') | NO | - | `'participant'` |  |

FKs: `participant_id` → `staff`.`id`; `conversation_id` → `internal_conversations`.`id`

UNIQUE/INDEX: `uk_conversation_participant` (`conversation_id,participant_id`)

---
## `core_competencies`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `code` | varchar(20) | NO | UNI | NULL |  |
| 3 | `name` | varchar(150) | NO | - | NULL |  |
| 4 | `description` | text | YES | - | `NULL` |  |
| 5 | `grade_range` | varchar(50) | YES | - | `NULL` |  |
| 6 | `learning_outcomes` | text | YES | - | `NULL` |  |
| 7 | `assessment_criteria` | text | YES | - | `NULL` |  |
| 8 | `sort_order` | int(11) | NO | - | `1` |  |
| 9 | `status` | enum('active','inactive') | NO | - | `'active'` |  |
| 10 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |

UNIQUE/INDEX: `uk_code` (`code`)

---
## `core_values`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `code` | varchar(20) | NO | UNI | NULL |  |
| 3 | `name` | varchar(100) | NO | - | NULL |  |
| 4 | `description` | text | YES | - | `NULL` |  |
| 5 | `behavioral_indicators` | text | YES | - | `NULL` |  |
| 6 | `grade_range` | varchar(50) | YES | - | `NULL` |  |
| 7 | `sort_order` | int(11) | NO | - | `1` |  |
| 8 | `status` | enum('active','inactive') | NO | - | `'active'` |  |
| 9 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |

UNIQUE/INDEX: `uk_code` (`code`)

---
## `csl_activities`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `activity_name` | varchar(255) | NO | - | NULL |  |
| 3 | `description` | text | YES | - | `NULL` |  |
| 4 | `activity_date` | date | NO | MUL | NULL |  |
| 5 | `location` | varchar(255) | YES | - | `NULL` |  |
| 6 | `beneficiary` | varchar(255) | YES | - | `NULL` |  |
| 7 | `impact_area` | varchar(100) | YES | - | `NULL` |  |
| 8 | `total_hours` | int(11) | YES | - | `0` |  |
| 9 | `organized_by` | int(10) unsigned | NO | MUL | NULL |  |
| 10 | `status` | enum('planned','ongoing','completed','cancelled') | NO | MUL | `'planned'` |  |
| 11 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 12 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

FKs: `organized_by` → `users`.`id`

---
## `curriculum_units`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `learning_area_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `name` | varchar(255) | NO | - | NULL |  |
| 4 | `description` | text | YES | - | `NULL` |  |
| 5 | `learning_outcomes` | text | YES | - | `NULL` |  |
| 6 | `suggested_resources` | text | YES | - | `NULL` |  |
| 7 | `duration` | int(11) | NO | - | NULL |  |
| 8 | `order_sequence` | int(11) | NO | - | NULL |  |
| 9 | `status` | enum('active','inactive') | NO | MUL | `'active'` |  |
| 10 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 11 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

FKs: `learning_area_id` → `learning_areas`.`id`

---
## `daily_meal_allocations`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `allocation_date` | date | NO | MUL | NULL |  |
| 3 | `boarding_house_id` | int(10) unsigned | YES | - | `NULL` |  |
| 4 | `class_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 5 | `student_count` | int(11) | NO | - | NULL |  |
| 6 | `notes` | text | YES | - | `NULL` |  |
| 7 | `created_by` | int(10) unsigned | NO | MUL | NULL |  |
| 8 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |

FKs: `class_id` → `classes`.`id`; `created_by` → `staff`.`id`

UNIQUE/INDEX: `uk_meal_allocation` (`allocation_date,boarding_house_id,class_id`)

---
## `dashboards`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `name` | varchar(100) | NO | UNI | NULL |  |
| 3 | `display_name` | varchar(150) | NO | - | NULL |  |
| 4 | `description` | varchar(500) | YES | - | `NULL` |  |
| 5 | `domain` | enum('SYSTEM','SCHOOL') | NO | MUL | `'SCHOOL'` |  |
| 6 | `route_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 7 | `is_active` | tinyint(1) | NO | MUL | `1` |  |
| 8 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 9 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

FKs: `route_id` → `routes`.`id`

UNIQUE/INDEX: `name` (`name`)

---
## `deduction_types`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `code` | varchar(50) | NO | UNI | NULL |  |
| 3 | `name` | varchar(100) | NO | - | NULL |  |
| 4 | `description` | text | YES | - | `NULL` |  |
| 5 | `category` | enum('statutory','voluntary','loan','school_fees','advance','other') | NO | MUL | `'other'` |  |
| 6 | `is_percentage` | tinyint(1) | NO | - | `0` |  |
| 7 | `default_amount` | decimal(12,2) | YES | - | `NULL` |  |
| 8 | `default_percentage` | decimal(5,2) | YES | - | `NULL` |  |
| 9 | `is_taxable` | tinyint(1) | NO | - | `0` |  |
| 10 | `default_rate` | decimal(5,2) | YES | - | `NULL` |  |
| 11 | `is_mandatory` | tinyint(1) | NO | - | `0` |  |
| 12 | `is_active` | tinyint(1) | NO | - | `1` |  |
| 13 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 14 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

UNIQUE/INDEX: `uk_code` (`code`)

---
## `delegation_audit`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `delegator_user_id` | int(10) unsigned | NO | - | NULL |  |
| 3 | `delegate_user_id` | int(10) unsigned | NO | - | NULL |  |
| 4 | `menu_item_id` | int(10) unsigned | NO | - | NULL |  |
| 5 | `granted_permissions` | longtext | YES | - | `NULL` |  |
| 6 | `note` | varchar(255) | YES | - | `NULL` |  |
| 7 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |

---
## `department_accounts`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(11) | NO | PRI | NULL | auto_increment |
| 2 | `department_id` | int(11) | NO | - | NULL |  |
| 3 | `amount` | decimal(12,2) | NO | - | NULL |  |
| 4 | `allocated_by` | int(11) | NO | - | NULL |  |
| 5 | `allocated_at` | timestamp | NO | - | `current_timestamp()` |  |

---
## `department_attendance_rules`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `department_id` | int(10) unsigned | NO | UNI | NULL |  |
| 3 | `work_start_time` | time | YES | - | `'08:00:00'` |  |
| 4 | `work_end_time` | time | YES | - | `'17:00:00'` |  |
| 5 | `late_threshold_minutes` | int(11) | YES | - | `15` |  |
| 6 | `half_day_mark_time` | time | YES | - | `'13:00:00'` |  |
| 7 | `weekend_duty_required` | tinyint(1) | YES | - | `0` |  |
| 8 | `notes` | text | YES | - | `NULL` |  |
| 9 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

FKs: `department_id` → `departments`.`id`

UNIQUE/INDEX: `department_id` (`department_id`)

---
## `department_budget_proposals`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(11) | NO | PRI | NULL | auto_increment |
| 2 | `department_id` | int(11) | NO | - | NULL |  |
| 3 | `title` | varchar(255) | NO | - | NULL |  |
| 4 | `description` | text | YES | - | `NULL` |  |
| 5 | `amount_requested` | decimal(12,2) | NO | - | NULL |  |
| 6 | `status` | enum('pending','approved','rejected') | YES | - | `'pending'` |  |
| 7 | `created_by` | int(11) | NO | - | NULL |  |
| 8 | `reviewed_by` | int(11) | YES | - | `NULL` |  |
| 9 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 10 | `reviewed_at` | timestamp | YES | - | `NULL` |  |

---
## `department_contacts`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(11) | NO | PRI | NULL | auto_increment |
| 2 | `icon` | varchar(50) | NO | - | `'bi-building'` |  |
| 3 | `color` | varchar(20) | NO | - | `'#198754'` |  |
| 4 | `name` | varchar(150) | NO | - | NULL |  |
| 5 | `description` | text | YES | - | `NULL` |  |
| 6 | `email` | varchar(200) | YES | - | `NULL` |  |
| 7 | `phone` | varchar(30) | YES | - | `NULL` |  |
| 8 | `display_order` | int(11) | NO | - | `0` |  |
| 9 | `is_active` | tinyint(1) | NO | - | `1` |  |

---
## `department_fund_requests`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(11) | NO | PRI | NULL | auto_increment |
| 2 | `department_id` | int(11) | NO | - | NULL |  |
| 3 | `amount` | decimal(12,2) | NO | - | NULL |  |
| 4 | `reason` | text | YES | - | `NULL` |  |
| 5 | `status` | enum('pending','approved','rejected') | YES | - | `'pending'` |  |
| 6 | `requested_by` | int(11) | NO | - | NULL |  |
| 7 | `reviewed_by` | int(11) | YES | - | `NULL` |  |
| 8 | `requested_at` | timestamp | NO | - | `current_timestamp()` |  |
| 9 | `reviewed_at` | timestamp | YES | - | `NULL` |  |

---
## `departments`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `name` | varchar(100) | NO | - | NULL |  |
| 3 | `code` | varchar(20) | NO | UNI | NULL |  |
| 4 | `description` | text | YES | - | `NULL` |  |
| 5 | `head_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 6 | `status` | enum('active','inactive') | NO | MUL | `'active'` |  |
| 7 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 8 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

FKs: `head_id` → `staff`.`id`

UNIQUE/INDEX: `uk_code` (`code`)

---
## `depreciation_schedule`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `asset_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `financial_year` | year(4) | NO | MUL | NULL |  |
| 4 | `opening_value` | decimal(15,2) | NO | - | NULL |  |
| 5 | `depreciation_amount` | decimal(15,2) | NO | - | NULL |  |
| 6 | `closing_value` | decimal(15,2) | NO | - | NULL |  |
| 7 | `accumulated_total` | decimal(15,2) | NO | - | NULL |  |
| 8 | `depreciation_rate` | decimal(5,2) | NO | - | NULL |  |
| 9 | `computed_by` | int(10) unsigned | YES | - | `NULL` |  |
| 10 | `computed_at` | datetime | YES | - | `NULL` |  |
| 11 | `is_posted` | tinyint(1) | NO | - | `0` |  |
| 12 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |

UNIQUE/INDEX: `uq_depr_asset_year` (`asset_id,financial_year`)

---
## `dormitories`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `code` | varchar(20) | NO | UNI | NULL |  |
| 3 | `name` | varchar(100) | NO | - | NULL |  |
| 4 | `gender` | enum('male','female','mixed') | NO | - | NULL |  |
| 5 | `capacity` | int(11) | NO | - | `50` |  |
| 6 | `current_occupancy` | int(11) | YES | - | `0` |  |
| 7 | `floor_count` | int(11) | YES | - | `1` |  |
| 8 | `house_parent_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 9 | `assistant_house_parent_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 10 | `location` | varchar(255) | YES | - | `NULL` |  |
| 11 | `facilities` | longtext | YES | - | `NULL` |  |
| 12 | `status` | enum('active','inactive','maintenance') | YES | - | `'active'` |  |
| 13 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |

FKs: `assistant_house_parent_id` → `staff`.`id`; `house_parent_id` → `staff`.`id`

UNIQUE/INDEX: `code` (`code`)

---
## `dormitory_assignments`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `student_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `dormitory_id` | int(10) unsigned | NO | MUL | NULL |  |
| 4 | `bed_number` | varchar(20) | YES | - | `NULL` |  |
| 5 | `academic_year_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 6 | `assigned_date` | date | NO | - | NULL |  |
| 7 | `end_date` | date | YES | - | `NULL` |  |
| 8 | `status` | enum('active','transferred','exited') | YES | - | `'active'` |  |
| 9 | `notes` | text | YES | - | `NULL` |  |
| 10 | `assigned_by` | int(10) unsigned | YES | - | `NULL` |  |
| 11 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |

FKs: `dormitory_id` → `dormitories`.`id`; `student_id` → `students`.`id`; `academic_year_id` → `academic_years`.`id`

UNIQUE/INDEX: `unique_active_assignment` (`student_id,status`)

---
## `driver_attendance`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `driver_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `attendance_date` | date | NO | - | NULL |  |
| 4 | `status` | enum('present','absent','leave') | NO | - | `'present'` |  |
| 5 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |

---
## `drivers`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `first_name` | varchar(50) | NO | - | NULL |  |
| 3 | `last_name` | varchar(50) | NO | - | NULL |  |
| 4 | `phone` | varchar(20) | YES | - | `NULL` |  |
| 5 | `license_number` | varchar(50) | YES | UNI | `NULL` |  |
| 6 | `status` | enum('active','inactive') | NO | - | `'active'` |  |
| 7 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |

UNIQUE/INDEX: `uk_license_number` (`license_number`)

---
## `dropped__bak_permissions`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(11) | NO | PRI | NULL | auto_increment |
| 2 | `code` | varchar(255) | NO | UNI | NULL |  |
| 3 | `description` | varchar(500) | YES | - | `NULL` |  |
| 4 | `entity` | varchar(100) | YES | MUL | `NULL` |  |
| 5 | `action` | varchar(100) | YES | - | `NULL` |  |
| 6 | `module` | varchar(100) | YES | MUL | `NULL` |  |
| 7 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 8 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

UNIQUE/INDEX: `code` (`code`); `code_2` (`code`)

---
## `dropped__bak_role_permissions`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `role_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `permission_id` | int(11) | NO | MUL | NULL |  |
| 4 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |

UNIQUE/INDEX: `unique_role_permission` (`role_id,permission_id`)

---
## `dropped__bak_role_sidebar_menus`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `role_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `menu_item_id` | int(10) unsigned | NO | MUL | NULL |  |
| 4 | `is_default` | tinyint(1) | NO | - | `1` |  |
| 5 | `custom_order` | int(11) | YES | - | `NULL` |  |
| 6 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |

UNIQUE/INDEX: `uk_role_sidebar_menu` (`role_id,menu_item_id`); `uq_role_menu` (`role_id,menu_item_id`)

---
## `dropped__bak_routes`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `name` | varchar(100) | NO | UNI | NULL |  |
| 3 | `url` | varchar(255) | NO | - | NULL |  |
| 4 | `domain` | enum('SYSTEM','SCHOOL') | NO | MUL | `'SCHOOL'` |  |
| 5 | `module` | varchar(100) | YES | MUL | `NULL` |  |
| 6 | `description` | varchar(500) | YES | - | `NULL` |  |
| 7 | `controller` | varchar(255) | YES | - | `NULL` |  |
| 8 | `action` | varchar(100) | YES | - | `NULL` |  |
| 9 | `is_active` | tinyint(1) | NO | MUL | `1` |  |
| 10 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 11 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

UNIQUE/INDEX: `name` (`name`)

---
## `equipment_maintenance`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `equipment_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `maintenance_type_id` | int(10) unsigned | NO | MUL | NULL |  |
| 4 | `last_maintenance_date` | date | YES | - | `NULL` |  |
| 5 | `next_maintenance_date` | date | YES | MUL | `NULL` |  |
| 6 | `status` | enum('pending','scheduled','in_progress','completed','cancelled','overdue') | NO | - | `'pending'` |  |
| 7 | `notes` | text | YES | - | `NULL` |  |
| 8 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 9 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

FKs: `maintenance_type_id` → `equipment_maintenance_types`.`id`; `equipment_id` → `item_serials`.`id`

---
## `equipment_maintenance_types`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `name` | varchar(100) | NO | - | NULL |  |
| 3 | `code` | varchar(20) | NO | UNI | NULL |  |
| 4 | `description` | text | YES | - | `NULL` |  |
| 5 | `frequency_days` | int(11) | YES | - | `NULL` |  |
| 6 | `estimated_cost` | decimal(10,2) | YES | - | `NULL` |  |
| 7 | `status` | enum('active','inactive') | NO | MUL | `'active'` |  |
| 8 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |

UNIQUE/INDEX: `uk_code` (`code`)

---
## `exam_schedules`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `term_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 3 | `academic_year_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 4 | `class_id` | int(10) unsigned | NO | MUL | NULL |  |
| 5 | `subject_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 6 | `exam_name` | varchar(255) | YES | - | `NULL` |  |
| 7 | `exam_type` | varchar(50) | YES | - | `NULL` |  |
| 8 | `exam_date` | date | NO | MUL | NULL |  |
| 9 | `start_time` | time | NO | - | NULL |  |
| 10 | `end_time` | time | NO | - | NULL |  |
| 11 | `duration_minutes` | int(11) | YES | - | `NULL` |  |
| 12 | `room_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 13 | `venue` | varchar(100) | YES | - | `NULL` |  |
| 14 | `invigilator_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 15 | `supervisor_id` | int(10) unsigned | YES | - | `NULL` |  |
| 16 | `notes` | text | YES | - | `NULL` |  |
| 17 | `created_by` | int(10) unsigned | YES | - | `NULL` |  |
| 18 | `status` | enum('scheduled','upcoming','in_progress','completed','postponed','cancelled') | NO | - | `'scheduled'` |  |
| 19 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 20 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

FKs: `invigilator_id` → `staff`.`id`; `room_id` → `rooms`.`id`; `class_id` → `classes`.`id`

---
## `expense_categories`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `code` | varchar(30) | NO | UNI | NULL |  |
| 3 | `name` | varchar(100) | NO | - | NULL |  |
| 4 | `description` | text | YES | - | `NULL` |  |
| 5 | `parent_id` | int(10) unsigned | YES | - | `NULL` |  |
| 6 | `type` | enum('operational','capital','staff','academic','catering','transport','statutory','other') | NO | - | `'operational'` |  |
| 7 | `status` | enum('active','inactive') | NO | - | `'active'` |  |
| 8 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |

UNIQUE/INDEX: `uq_expense_category_code` (`code`)

---
## `expenses`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `expense_number` | varchar(30) | YES | - | `NULL` |  |
| 3 | `category_id` | int(10) unsigned | YES | - | `NULL` |  |
| 4 | `expense_category` | varchar(100) | NO | MUL | NULL |  |
| 5 | `description` | text | YES | - | `NULL` |  |
| 6 | `budget_line_item_id` | int(10) unsigned | YES | - | `NULL` |  |
| 7 | `department_id` | int(10) unsigned | YES | - | `NULL` |  |
| 8 | `financial_period_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 9 | `academic_year` | year(4) | YES | - | `NULL` |  |
| 10 | `term` | tinyint(4) | YES | - | `NULL` |  |
| 11 | `payment_method` | enum('cash','mpesa','bank_transfer','cheque','direct_debit') | YES | - | `'cash'` |  |
| 12 | `reference_number` | varchar(100) | YES | - | `NULL` |  |
| 13 | `vendor_id` | int(10) unsigned | YES | - | `NULL` |  |
| 14 | `vendor_name` | varchar(255) | YES | - | `NULL` |  |
| 15 | `receipt_number` | varchar(100) | YES | - | `NULL` |  |
| 16 | `notes` | text | YES | - | `NULL` |  |
| 17 | `attachment_path` | varchar(500) | YES | - | `NULL` |  |
| 18 | `amount` | decimal(12,2) | NO | - | NULL |  |
| 19 | `expense_date` | date | NO | MUL | NULL |  |
| 20 | `created_by` | int(10) unsigned | NO | - | `1` |  |
| 21 | `status` | enum('draft','pending_approval','approved','paid','rejected','cancelled') | NO | MUL | `'draft'` |  |
| 22 | `approved_by` | int(10) unsigned | YES | - | `NULL` |  |
| 23 | `approved_at` | datetime | YES | - | `NULL` |  |
| 24 | `paid_by` | int(10) unsigned | YES | - | `NULL` |  |
| 25 | `paid_at` | datetime | YES | - | `NULL` |  |
| 26 | `rejected_by` | int(10) unsigned | YES | - | `NULL` |  |
| 27 | `rejected_at` | datetime | YES | - | `NULL` |  |
| 28 | `rejection_reason` | text | YES | - | `NULL` |  |
| 29 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 30 | `updated_at` | timestamp | YES | - | `NULL` | on update current_timestamp() |
| 31 | `deleted_at` | timestamp | YES | - | `NULL` |  |

---
## `external_emails`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `institution_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 3 | `recipient_email` | varchar(255) | NO | MUL | NULL |  |
| 4 | `recipient_name` | varchar(255) | YES | - | `NULL` |  |
| 5 | `subject` | varchar(255) | NO | - | NULL |  |
| 6 | `email_body` | longtext | NO | - | NULL |  |
| 7 | `template_id` | int(10) unsigned | YES | - | `NULL` |  |
| 8 | `email_type` | enum('inquiry','report','application','information','request','other') | NO | MUL | `'information'` |  |
| 9 | `attachments` | longtext | YES | - | `NULL` |  |
| 10 | `status` | enum('draft','queued','sent','delivered','failed','bounced') | NO | MUL | `'draft'` |  |
| 11 | `sent_by` | int(10) unsigned | NO | MUL | NULL |  |
| 12 | `sent_at` | datetime | YES | - | `NULL` |  |
| 13 | `delivered_at` | datetime | YES | - | `NULL` |  |
| 14 | `bounced_at` | datetime | YES | - | `NULL` |  |
| 15 | `failure_reason` | text | YES | - | `NULL` |  |
| 16 | `external_reference_id` | varchar(255) | YES | - | `NULL` |  |
| 17 | `opened_at` | datetime | YES | - | `NULL` |  |
| 18 | `clicked_links` | int(11) | YES | - | `0` |  |
| 19 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 20 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

FKs: `sent_by` → `staff`.`id`; `institution_id` → `external_institutions`.`id`

---
## `external_inbound_messages`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `source_type` | enum('sms','email','web','other') | NO | - | NULL |  |
| 3 | `source_address` | varchar(255) | NO | - | NULL |  |
| 4 | `received_at` | datetime | NO | - | NULL |  |
| 5 | `linked_user_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 6 | `linked_parent_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 7 | `linked_student_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 8 | `subject` | varchar(255) | YES | - | `NULL` |  |
| 9 | `body` | text | NO | - | NULL |  |
| 10 | `status` | enum('pending','processed','archived','error') | NO | - | `'pending'` |  |
| 11 | `processing_notes` | text | YES | - | `NULL` |  |

---
## `external_institutions`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `name` | varchar(255) | NO | - | NULL |  |
| 3 | `institution_type` | enum('government','ngo','school','university','supplier','other') | NO | MUL | `'other'` |  |
| 4 | `email_addresses` | longtext | YES | - | `NULL` |  |
| 5 | `phone_numbers` | longtext | YES | - | `NULL` |  |
| 6 | `contact_person_name` | varchar(255) | YES | - | `NULL` |  |
| 7 | `contact_person_phone` | varchar(20) | YES | - | `NULL` |  |
| 8 | `contact_person_email` | varchar(100) | YES | - | `NULL` |  |
| 9 | `address` | text | YES | - | `NULL` |  |
| 10 | `website` | varchar(255) | YES | - | `NULL` |  |
| 11 | `status` | enum('active','inactive','archived') | NO | MUL | `'active'` |  |
| 12 | `notes` | text | YES | - | `NULL` |  |
| 13 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 14 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

---
## `failed_auth_attempts`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | bigint(20) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `ip_address` | varchar(45) | NO | MUL | NULL |  |
| 3 | `reason` | varchar(255) | NO | - | NULL |  |
| 4 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |

---
## `fee_credit_notes`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `credit_number` | varchar(30) | NO | UNI | NULL |  |
| 3 | `student_id` | int(10) unsigned | NO | MUL | NULL |  |
| 4 | `academic_year` | year(4) | NO | MUL | NULL |  |
| 5 | `term_id` | int(10) unsigned | YES | - | `NULL` |  |
| 6 | `source_transaction_id` | int(10) unsigned | YES | - | `NULL` |  |
| 7 | `credit_amount` | decimal(12,2) | NO | - | NULL |  |
| 8 | `credit_reason` | enum('overpayment','fee_reduction','sponsorship_adjustment','error_correction','waiver_excess','refund') | YES | - | `'overpayment'` |  |
| 9 | `status` | enum('available','partially_applied','fully_applied','refunded','expired','cancelled') | YES | MUL | `'available'` |  |
| 10 | `applied_amount` | decimal(12,2) | YES | - | `0.00` |  |
| 11 | `remaining_amount` | decimal(12,2) | YES | - | `NULL` | STORED GENERATED |
| 12 | `applied_to_year` | year(4) | YES | - | `NULL` |  |
| 13 | `applied_to_term_id` | int(10) unsigned | YES | - | `NULL` |  |
| 14 | `applied_at` | datetime | YES | - | `NULL` |  |
| 15 | `expiry_date` | date | YES | - | `NULL` |  |
| 16 | `notes` | text | YES | - | `NULL` |  |
| 17 | `created_by` | int(10) unsigned | YES | - | `NULL` |  |
| 18 | `approved_by` | int(10) unsigned | YES | - | `NULL` |  |
| 19 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 20 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

UNIQUE/INDEX: `credit_number` (`credit_number`)

---
## `fee_discounts_waivers`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `student_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `student_fee_obligation_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 4 | `discount_type` | enum('percentage','fixed_amount','full_waiver','merit','need_based','sibling','other') | NO | MUL | NULL |  |
| 5 | `discount_value` | decimal(10,2) | NO | - | NULL |  |
| 6 | `discount_percentage` | decimal(5,2) | YES | - | `NULL` |  |
| 7 | `reason` | text | NO | - | NULL |  |
| 8 | `academic_year` | year(4) | NO | - | NULL |  |
| 9 | `term_id` | int(10) unsigned | YES | - | `NULL` |  |
| 10 | `approved_by` | int(10) unsigned | NO | MUL | NULL |  |
| 11 | `approved_date` | datetime | NO | - | NULL |  |
| 12 | `status` | enum('active','expired','cancelled') | NO | MUL | `'active'` |  |
| 13 | `valid_until` | date | YES | - | `NULL` |  |
| 14 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |

---
## `fee_invoices`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `student_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `academic_year_id` | int(10) unsigned | NO | MUL | NULL |  |
| 4 | `term_id` | int(10) unsigned | NO | - | NULL |  |
| 5 | `total_amount` | decimal(10,2) | NO | - | `0.00` |  |
| 6 | `amount_paid` | decimal(10,2) | NO | - | `0.00` |  |
| 7 | `balance` | decimal(10,2) | YES | - | `NULL` | STORED GENERATED |
| 8 | `status` | enum('pending','partial','paid') | NO | MUL | `'pending'` |  |
| 9 | `due_date` | date | YES | - | `NULL` |  |
| 10 | `generated_by` | int(10) unsigned | YES | - | `NULL` |  |
| 11 | `generated_at` | timestamp | NO | - | `current_timestamp()` |  |
| 12 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

UNIQUE/INDEX: `uk_student_year_term` (`student_id,academic_year_id,term_id`)

---
## `fee_reminders`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `student_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `parent_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 4 | `academic_year` | year(4) | NO | - | NULL |  |
| 5 | `term_id` | int(10) unsigned | NO | - | NULL |  |
| 6 | `reminder_type` | enum('pre_due','due_date','overdue','arrears','settlement_plan') | NO | MUL | NULL |  |
| 7 | `outstanding_amount` | decimal(10,2) | NO | - | NULL |  |
| 8 | `sent_date` | datetime | NO | - | NULL |  |
| 9 | `delivery_method` | enum('sms','email','both','manual') | NO | - | NULL |  |
| 10 | `status` | enum('sent','bounced','acknowledged') | NO | MUL | `'sent'` |  |
| 11 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |

---
## `fee_structure_approvals`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `level_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `academic_year` | year(4) | NO | MUL | NULL |  |
| 4 | `term_id` | int(10) unsigned | NO | - | NULL |  |
| 5 | `student_type_id` | int(10) unsigned | NO | - | NULL |  |
| 6 | `status` | enum('draft','submitted','reviewed','approved','rejected','active') | NO | MUL | `'draft'` |  |
| 7 | `submitted_by` | int(10) unsigned | YES | - | `NULL` |  |
| 8 | `submitted_at` | datetime | YES | - | `NULL` |  |
| 9 | `reviewed_by` | int(10) unsigned | YES | - | `NULL` |  |
| 10 | `reviewed_at` | datetime | YES | - | `NULL` |  |
| 11 | `review_notes` | text | YES | - | `NULL` |  |
| 12 | `approved_by` | int(10) unsigned | YES | - | `NULL` |  |
| 13 | `approved_at` | datetime | YES | - | `NULL` |  |
| 14 | `approval_notes` | text | YES | - | `NULL` |  |
| 15 | `rejected_by` | int(10) unsigned | YES | - | `NULL` |  |
| 16 | `rejected_at` | datetime | YES | - | `NULL` |  |
| 17 | `rejection_reason` | text | YES | - | `NULL` |  |
| 18 | `obligations_generated` | tinyint(1) | YES | - | `0` |  |
| 19 | `obligations_count` | int(11) | YES | - | `0` |  |
| 20 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 21 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

UNIQUE/INDEX: `uk_bundle` (`level_id,academic_year,term_id,student_type_id`)

---
## `fee_structure_change_log`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `fee_structure_detail_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `changed_by` | int(10) unsigned | YES | MUL | `NULL` |  |
| 4 | `change_type` | enum('created','updated','reviewed','approved','activated','archived','rollover') | NO | MUL | NULL |  |
| 5 | `old_amount` | decimal(10,2) | YES | - | `NULL` |  |
| 6 | `new_amount` | decimal(10,2) | YES | - | `NULL` |  |
| 7 | `old_status` | varchar(50) | YES | - | `NULL` |  |
| 8 | `new_status` | varchar(50) | YES | - | `NULL` |  |
| 9 | `change_notes` | text | YES | - | `NULL` |  |
| 10 | `ip_address` | varchar(45) | YES | - | `NULL` |  |
| 11 | `created_at` | timestamp | NO | MUL | `current_timestamp()` |  |

---
## `fee_structure_rollover_log`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `source_academic_year` | year(4) | NO | MUL | NULL |  |
| 3 | `target_academic_year` | year(4) | NO | MUL | NULL |  |
| 4 | `rollover_date` | datetime | YES | - | `current_timestamp()` |  |
| 5 | `executed_by` | int(10) unsigned | YES | MUL | `NULL` |  |
| 6 | `structures_copied` | int(11) | YES | - | `0` |  |
| 7 | `notification_sent` | tinyint(1) | YES | - | `0` |  |
| 8 | `notification_sent_at` | datetime | YES | - | `NULL` |  |
| 9 | `notification_recipients` | text | YES | - | `NULL` |  |
| 10 | `rollover_status` | enum('pending','in_progress','completed','failed') | YES | MUL | `'pending'` |  |
| 11 | `error_message` | text | YES | - | `NULL` |  |

FKs: `executed_by` → `users`.`id`

---
## `fee_structure_rollover_schedule`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `academic_year_id` | int(10) unsigned | NO | UNI | NULL |  |
| 3 | `scheduled_date` | date | NO | MUL | NULL |  |
| 4 | `review_deadline` | date | YES | - | `NULL` |  |
| 5 | `executed` | tinyint(1) | YES | MUL | `0` |  |
| 6 | `executed_at` | datetime | YES | - | `NULL` |  |
| 7 | `notification_days_before` | int(11) | YES | - | `7` |  |
| 8 | `reminder_sent` | tinyint(1) | YES | - | `0` |  |
| 9 | `reminder_sent_at` | datetime | YES | - | `NULL` |  |

UNIQUE/INDEX: `unique_year_schedule` (`academic_year_id`)

---
## `fee_structures`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `name` | varchar(100) | NO | - | NULL |  |
| 3 | `amount` | decimal(10,2) | NO | - | NULL |  |
| 4 | `due_date` | date | YES | - | `NULL` |  |
| 5 | `description` | text | YES | - | `NULL` |  |

---
## `fee_structures_detailed`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `level_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `academic_year` | year(4) | NO | MUL | NULL |  |
| 4 | `term_id` | int(10) unsigned | NO | MUL | NULL |  |
| 5 | `student_type_id` | int(10) unsigned | NO | MUL | NULL |  |
| 6 | `fee_type_id` | int(10) unsigned | NO | MUL | NULL |  |
| 7 | `amount` | decimal(10,2) | NO | - | NULL |  |
| 8 | `status` | enum('draft','pending_review','reviewed','approved','active','archived') | YES | MUL | `'draft'` |  |
| 9 | `reviewed_by` | int(10) unsigned | YES | MUL | `NULL` |  |
| 10 | `reviewed_at` | datetime | YES | - | `NULL` |  |
| 11 | `approved_by` | int(10) unsigned | YES | MUL | `NULL` |  |
| 12 | `approved_at` | datetime | YES | - | `NULL` |  |
| 13 | `activated_at` | datetime | YES | - | `NULL` |  |
| 14 | `is_auto_rollover` | tinyint(1) | YES | - | `0` |  |
| 15 | `copied_from_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 16 | `rollover_notes` | text | YES | - | `NULL` |  |
| 17 | `due_date` | date | YES | - | `NULL` |  |
| 18 | `description` | text | YES | - | `NULL` |  |
| 19 | `created_by` | int(10) unsigned | YES | MUL | `NULL` |  |
| 20 | `updated_by` | int(10) unsigned | YES | - | `NULL` |  |
| 21 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 22 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

FKs: `reviewed_by` → `users`.`id`; `copied_from_id` → `fee_structures_detailed`.`id`; `approved_by` → `users`.`id`

UNIQUE/INDEX: `uk_fee_structure` (`level_id,academic_year,term_id,student_type_id,fee_type_id`)

---
## `fee_transition_history`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `student_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `from_academic_year` | int(11) | NO | MUL | NULL |  |
| 4 | `to_academic_year` | int(11) | NO | - | NULL |  |
| 5 | `from_term_id` | int(10) unsigned | YES | - | `NULL` |  |
| 6 | `to_term_id` | int(10) unsigned | YES | - | `NULL` |  |
| 7 | `balance_action` | enum('fresh_bill','add_to_current','deduct_from_current','manual_adjustment') | NO | - | NULL |  |
| 8 | `amount_transferred` | decimal(12,2) | YES | - | `0.00` |  |
| 9 | `previous_balance` | decimal(12,2) | YES | - | `0.00` |  |
| 10 | `new_balance` | decimal(12,2) | YES | - | `0.00` |  |
| 11 | `created_by` | int(10) unsigned | YES | - | `NULL` |  |
| 12 | `created_at` | timestamp | NO | MUL | `current_timestamp()` |  |
| 13 | `notes` | text | YES | - | `NULL` |  |

FKs: `student_id` → `students`.`id`

---
## `fee_types`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `code` | varchar(20) | NO | UNI | NULL |  |
| 3 | `name` | varchar(100) | NO | - | NULL |  |
| 4 | `description` | text | YES | - | `NULL` |  |
| 5 | `category` | enum('tuition','boarding','activity','infrastructure','other') | NO | MUL | NULL |  |
| 6 | `is_mandatory` | tinyint(1) | NO | - | `1` |  |
| 7 | `status` | enum('active','inactive') | NO | MUL | `'active'` |  |
| 8 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |

UNIQUE/INDEX: `code` (`code`)

---
## `finance_approval_log`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `module` | varchar(50) | NO | MUL | NULL |  |
| 3 | `record_id` | int(10) unsigned | NO | - | NULL |  |
| 4 | `action` | enum('submit','review','approve','reject','activate','cancel','pay') | NO | - | NULL |  |
| 5 | `from_status` | varchar(50) | YES | - | `NULL` |  |
| 6 | `to_status` | varchar(50) | YES | - | `NULL` |  |
| 7 | `actor_id` | int(10) unsigned | NO | MUL | NULL |  |
| 8 | `notes` | text | YES | - | `NULL` |  |
| 9 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |

---
## `finance_exceptions`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `type` | enum('budget_overrun','large_expense_unapproved','unreconciled_cash','missing_receipt','duplicate_payment','unmatched_mpesa','petty_cash_shortfall','payroll_variance','asset_untagged') | NO | MUL | NULL |  |
| 3 | `severity` | enum('low','medium','high','critical') | NO | - | `'medium'` |  |
| 4 | `description` | text | NO | - | NULL |  |
| 5 | `reference_id` | int(10) unsigned | YES | - | `NULL` |  |
| 6 | `reference_table` | varchar(100) | YES | - | `NULL` |  |
| 7 | `amount` | decimal(15,2) | YES | - | `NULL` |  |
| 8 | `status` | enum('open','under_review','resolved','dismissed') | NO | MUL | `'open'` |  |
| 9 | `flagged_by` | varchar(50) | NO | - | `'system'` |  |
| 10 | `resolved_by` | int(10) unsigned | YES | - | `NULL` |  |
| 11 | `resolved_at` | datetime | YES | - | `NULL` |  |
| 12 | `resolution_notes` | text | YES | - | `NULL` |  |
| 13 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |

---
## `financial_adjustments`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `adjustment_number` | varchar(30) | NO | UNI | NULL |  |
| 3 | `type` | enum('credit_note','fee_reversal','write_off','discount','penalty','arrears_write_off','overpayment_refund') | NO | - | NULL |  |
| 4 | `student_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 5 | `amount` | decimal(15,2) | NO | - | NULL |  |
| 6 | `reason` | text | NO | - | NULL |  |
| 7 | `reference_payment_id` | int(10) unsigned | YES | - | `NULL` |  |
| 8 | `status` | enum('pending','approved','applied','rejected') | NO | MUL | `'pending'` |  |
| 9 | `requested_by` | int(10) unsigned | NO | - | NULL |  |
| 10 | `approved_by` | int(10) unsigned | YES | - | `NULL` |  |
| 11 | `approved_at` | datetime | YES | - | `NULL` |  |
| 12 | `applied_at` | datetime | YES | - | `NULL` |  |
| 13 | `rejection_reason` | text | YES | - | `NULL` |  |
| 14 | `academic_year` | year(4) | YES | - | `NULL` |  |
| 15 | `term` | tinyint(4) | YES | - | `NULL` |  |
| 16 | `notes` | text | YES | - | `NULL` |  |
| 17 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 18 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

UNIQUE/INDEX: `uq_adjustment_number` (`adjustment_number`)

---
## `financial_periods`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `name` | varchar(100) | NO | - | NULL |  |
| 3 | `start_date` | date | NO | MUL | NULL |  |
| 4 | `end_date` | date | NO | - | NULL |  |
| 5 | `status` | enum('active','closed') | NO | MUL | `'active'` |  |
| 6 | `closed_by` | int(10) unsigned | YES | MUL | `NULL` |  |
| 7 | `closed_at` | timestamp | YES | - | `NULL` |  |
| 8 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |

FKs: `closed_by` → `users`.`id`

---
## `financial_transactions`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `type` | varchar(50) | NO | - | NULL |  |
| 3 | `amount` | decimal(10,2) | NO | - | NULL |  |
| 4 | `payment_method` | varchar(50) | YES | - | `NULL` |  |
| 5 | `reference_no` | varchar(100) | YES | - | `NULL` |  |
| 6 | `status` | varchar(50) | YES | - | `NULL` |  |
| 7 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 8 | `transaction_date` | datetime | NO | MUL | NULL |  |
| 9 | `processed_by` | int(10) unsigned | YES | MUL | `NULL` |  |
| 10 | `processed_at` | timestamp | YES | - | `NULL` |  |
| 11 | `reconciliation_status` | enum('pending','reconciled','disputed') | NO | MUL | `'pending'` |  |
| 12 | `reconciled_by` | int(10) unsigned | YES | MUL | `NULL` |  |
| 13 | `reconciled_at` | timestamp | YES | - | `NULL` |  |
| 14 | `notes` | text | YES | - | `NULL` |  |

FKs: `reconciled_by` → `users`.`id`; `processed_by` → `users`.`id`

---
## `fixed_assets`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `asset_code` | varchar(30) | NO | UNI | NULL |  |
| 3 | `name` | varchar(255) | NO | - | NULL |  |
| 4 | `category_id` | int(10) unsigned | NO | MUL | NULL |  |
| 5 | `description` | text | YES | - | `NULL` |  |
| 6 | `serial_number` | varchar(100) | YES | - | `NULL` |  |
| 7 | `model` | varchar(100) | YES | - | `NULL` |  |
| 8 | `brand` | varchar(100) | YES | - | `NULL` |  |
| 9 | `location` | varchar(200) | YES | - | `NULL` |  |
| 10 | `purchase_date` | date | NO | - | NULL |  |
| 11 | `purchase_price` | decimal(15,2) | NO | - | NULL |  |
| 12 | `supplier_id` | int(10) unsigned | YES | - | `NULL` |  |
| 13 | `invoice_number` | varchar(100) | YES | - | `NULL` |  |
| 14 | `warranty_expiry` | date | YES | - | `NULL` |  |
| 15 | `condition` | enum('excellent','good','fair','poor','written_off') | NO | - | `'good'` |  |
| 16 | `status` | enum('active','disposed','written_off','under_repair','stolen') | NO | MUL | `'active'` |  |
| 17 | `acquisition_type` | enum('purchase','donation','grant','transfer') | NO | - | `'purchase'` |  |
| 18 | `depreciation_method` | enum('straight_line','reducing_balance','none') | NO | - | `'straight_line'` |  |
| 19 | `useful_life_years` | tinyint(4) | NO | - | `5` |  |
| 20 | `residual_value` | decimal(15,2) | NO | - | `0.00` |  |
| 21 | `current_book_value` | decimal(15,2) | NO | - | NULL |  |
| 22 | `accumulated_depr` | decimal(15,2) | NO | - | `0.00` |  |
| 23 | `last_depreciation_date` | date | YES | - | `NULL` |  |
| 24 | `expense_id` | int(10) unsigned | YES | - | `NULL` |  |
| 25 | `added_by` | int(10) unsigned | NO | - | NULL |  |
| 26 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 27 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |
| 28 | `deleted_at` | timestamp | YES | - | `NULL` |  |

UNIQUE/INDEX: `uq_asset_code` (`asset_code`)

---
## `food_consumption_records`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `consumption_date` | date | NO | MUL | NULL |  |
| 3 | `meal_plan_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 4 | `inventory_item_id` | int(10) unsigned | NO | MUL | NULL |  |
| 5 | `quantity_planned` | decimal(10,2) | NO | - | NULL |  |
| 6 | `quantity_used` | decimal(10,2) | YES | - | `0.00` |  |
| 7 | `unit` | varchar(20) | NO | - | NULL |  |
| 8 | `waste_quantity` | decimal(10,2) | YES | - | `0.00` |  |
| 9 | `cost_per_unit` | decimal(10,2) | YES | - | `NULL` |  |
| 10 | `total_cost` | decimal(10,2) | YES | - | `NULL` |  |
| 11 | `recorded_by` | int(10) unsigned | NO | MUL | NULL |  |
| 12 | `recorded_at` | datetime | YES | - | `NULL` |  |
| 13 | `notes` | text | YES | - | `NULL` |  |
| 14 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |

FKs: `recorded_by` → `staff`.`id`; `inventory_item_id` → `inventory_items`.`id`; `meal_plan_id` → `meal_plans`.`id`

---
## `formative_scores`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `assessment_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `student_id` | int(10) unsigned | NO | MUL | NULL |  |
| 4 | `score` | decimal(6,2) | NO | - | `0.00` |  |
| 5 | `max_score` | decimal(6,2) | NO | - | `100.00` |  |
| 6 | `percentage` | decimal(5,2) | YES | - | `NULL` | STORED GENERATED |
| 7 | `cbc_grade` | enum('EE','ME','AE','BE') | YES | - | `NULL` | STORED GENERATED |
| 8 | `remarks` | varchar(255) | YES | - | `NULL` |  |
| 9 | `entered_by` | int(10) unsigned | YES | - | `NULL` |  |
| 10 | `created_at` | datetime | NO | - | `current_timestamp()` |  |
| 11 | `updated_at` | datetime | NO | - | `current_timestamp()` | on update current_timestamp() |

UNIQUE/INDEX: `uq_formative_score` (`assessment_id,student_id`)

---
## `form_permissions`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `form_code` | varchar(50) | NO | UNI | NULL |  |
| 3 | `form_name` | varchar(100) | NO | - | NULL |  |
| 4 | `form_description` | text | YES | - | `NULL` |  |
| 5 | `module_name` | varchar(50) | YES | - | `NULL` |  |
| 6 | `actions` | longtext | NO | - | NULL |  |
| 7 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |

UNIQUE/INDEX: `uk_code` (`form_code`)

---
## `forum_posts`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `thread_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `author_id` | int(10) unsigned | NO | MUL | NULL |  |
| 4 | `author_type` | enum('student','staff','parent','admin') | NO | - | NULL |  |
| 5 | `body` | text | NO | - | NULL |  |
| 6 | `reply_to_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 7 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |

---
## `forum_threads`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `title` | varchar(255) | NO | - | NULL |  |
| 3 | `created_by` | int(10) unsigned | NO | MUL | NULL |  |
| 4 | `forum_type` | enum('general','class','staff','parent','custom') | NO | MUL | `'general'` |  |
| 5 | `status` | enum('open','closed','archived') | NO | - | `'open'` |  |
| 6 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 7 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

---
## `gallery_items`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(11) | NO | PRI | NULL | auto_increment |
| 2 | `image_url` | varchar(500) | NO | - | NULL |  |
| 3 | `caption` | varchar(200) | YES | - | `NULL` |  |
| 4 | `category` | varchar(50) | NO | - | `'General'` |  |
| 5 | `display_order` | int(11) | NO | - | `0` |  |
| 6 | `is_active` | tinyint(1) | NO | MUL | `1` |  |
| 7 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |

---
## `grade_rules`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `scale_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `grade_code` | varchar(4) | NO | - | NULL |  |
| 4 | `grade_name` | varchar(50) | NO | - | NULL |  |
| 5 | `min_mark` | decimal(5,2) | NO | - | NULL |  |
| 6 | `max_mark` | decimal(5,2) | NO | - | NULL |  |
| 7 | `grade_points` | decimal(3,1) | NO | - | NULL |  |
| 8 | `performance_level` | varchar(50) | NO | - | NULL |  |
| 9 | `description` | text | YES | - | `NULL` |  |
| 10 | `sort_order` | int(11) | NO | - | `1` |  |
| 11 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |

FKs: `scale_id` → `grading_scales`.`id`

UNIQUE/INDEX: `uk_scale_grade` (`scale_id,grade_code`)

---
## `grading_comments`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `grade_code` | varchar(4) | NO | UNI | NULL |  |
| 3 | `comment` | varchar(255) | NO | - | NULL |  |
| 4 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |

UNIQUE/INDEX: `uk_grade_code` (`grade_code`)

---
## `grading_scales`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `name` | varchar(100) | NO | UNI | NULL |  |
| 3 | `description` | text | YES | - | `NULL` |  |
| 4 | `min_mark` | decimal(5,2) | NO | - | `0.00` |  |
| 5 | `max_mark` | decimal(5,2) | NO | - | `100.00` |  |
| 6 | `status` | enum('active','inactive') | NO | - | `'active'` |  |
| 7 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 8 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

UNIQUE/INDEX: `uk_name` (`name`)

---
## `group_members`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `group_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `user_id` | int(10) unsigned | NO | MUL | NULL |  |
| 4 | `added_by` | int(10) unsigned | NO | MUL | NULL |  |
| 5 | `added_at` | timestamp | NO | - | `current_timestamp()` |  |

FKs: `user_id` → `users`.`id`; `group_id` → `communication_groups`.`id`; `added_by` → `users`.`id`

UNIQUE/INDEX: `uk_group_user` (`group_id,user_id`)

---
## `ieps`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `student_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `academic_year` | year(4) | NO | - | NULL |  |
| 4 | `iep_type` | varchar(50) | YES | - | `NULL` |  |
| 5 | `special_needs_category` | varchar(100) | YES | - | `NULL` |  |
| 6 | `goals_summary` | text | NO | - | NULL |  |
| 7 | `strategies` | text | YES | - | `NULL` |  |
| 8 | `accommodations` | text | YES | - | `NULL` |  |
| 9 | `progress_monitoring_plan` | text | YES | - | `NULL` |  |
| 10 | `created_by` | int(10) unsigned | NO | MUL | NULL |  |
| 11 | `approved_by` | int(10) unsigned | YES | MUL | `NULL` |  |
| 12 | `approved_date` | date | YES | - | `NULL` |  |
| 13 | `status` | enum('draft','active','completed','archived') | NO | MUL | `'draft'` |  |
| 14 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 15 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

FKs: `student_id` → `students`.`id`; `created_by` → `users`.`id`; `approved_by` → `users`.`id`

---
## `import_logs`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(11) | NO | PRI | NULL | auto_increment |
| 2 | `import_type` | varchar(50) | NO | MUL | NULL |  |
| 3 | `import_category` | varchar(50) | YES | - | `NULL` |  |
| 4 | `original_filename` | varchar(255) | YES | - | `NULL` |  |
| 5 | `total_rows` | int(11) | NO | - | `0` |  |
| 6 | `success_rows` | int(11) | NO | - | `0` |  |
| 7 | `error_rows` | int(11) | NO | - | `0` |  |
| 8 | `skipped_rows` | int(11) | NO | - | `0` |  |
| 9 | `status` | enum('preview','completed','partial','failed') | NO | MUL | `'preview'` |  |
| 10 | `error_details` | longtext | YES | - | `NULL` |  |
| 11 | `imported_by` | int(10) unsigned | YES | MUL | `NULL` |  |
| 12 | `notes` | text | YES | - | `NULL` |  |
| 13 | `created_at` | timestamp | NO | MUL | `current_timestamp()` |  |
| 14 | `completed_at` | timestamp | YES | - | `NULL` |  |

FKs: `imported_by` → `users`.`id`

---
## `internal_conversations`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `title` | varchar(255) | NO | - | NULL |  |
| 3 | `conversation_type` | enum('one_on_one','group','department','broadcast') | NO | MUL | `'one_on_one'` |  |
| 4 | `created_by` | int(10) unsigned | NO | MUL | NULL |  |
| 5 | `is_locked` | tinyint(1) | NO | - | `0` |  |
| 6 | `last_message_at` | datetime | YES | MUL | `NULL` |  |
| 7 | `last_message_by` | int(10) unsigned | YES | MUL | `NULL` |  |
| 8 | `participant_count` | int(11) | NO | - | `0` |  |
| 9 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 10 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

FKs: `last_message_by` → `staff`.`id`

---
## `internal_messages`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `conversation_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 3 | `sender_id` | int(10) unsigned | NO | MUL | NULL |  |
| 4 | `subject` | varchar(255) | NO | - | NULL |  |
| 5 | `message_body` | longtext | NO | - | NULL |  |
| 6 | `message_type` | enum('personal','group','announcement') | NO | MUL | `'personal'` |  |
| 7 | `priority` | enum('low','normal','high','urgent') | NO | - | `'normal'` |  |
| 8 | `status` | enum('draft','sent','read','archived','deleted') | NO | MUL | `'sent'` |  |
| 9 | `is_edited` | tinyint(1) | NO | - | `0` |  |
| 10 | `last_edited_at` | datetime | YES | - | `NULL` |  |
| 11 | `created_at` | timestamp | NO | MUL | `current_timestamp()` |  |
| 12 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

---
## `inventory_adjustments`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `item_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `quantity_change` | int(11) | NO | - | NULL |  |
| 4 | `reason` | enum('count_adjustment','damage','loss','found','other') | NO | - | NULL |  |
| 5 | `reference_type` | varchar(50) | YES | - | `NULL` |  |
| 6 | `reference_id` | int(10) unsigned | YES | - | `NULL` |  |
| 7 | `notes` | text | YES | - | `NULL` |  |
| 8 | `adjusted_by` | int(10) unsigned | NO | MUL | NULL |  |
| 9 | `approved_by` | int(10) unsigned | YES | MUL | `NULL` |  |
| 10 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |

FKs: `approved_by` → `staff`.`id`; `adjusted_by` → `staff`.`id`; `item_id` → `inventory_items`.`id`

---
## `inventory_allocations`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `allocation_number` | varchar(50) | NO | UNI | NULL |  |
| 3 | `item_id` | int(10) unsigned | NO | MUL | NULL |  |
| 4 | `allocated_quantity` | int(11) | NO | - | NULL |  |
| 5 | `allocated_to_department_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 6 | `allocated_to_event` | varchar(100) | YES | - | `NULL` |  |
| 7 | `allocated_to_class_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 8 | `status` | enum('allocated','issued','partially_returned','fully_returned','expired','cancelled') | NO | - | `'allocated'` |  |
| 9 | `allocation_date` | date | NO | MUL | NULL |  |
| 10 | `expected_return_date` | date | YES | - | `NULL` |  |
| 11 | `allocated_by` | int(10) unsigned | NO | MUL | NULL |  |
| 12 | `issued_by` | int(10) unsigned | YES | MUL | `NULL` |  |
| 13 | `issued_at` | datetime | YES | - | `NULL` |  |
| 14 | `returned_quantity` | int(11) | YES | - | `0` |  |
| 15 | `returned_at` | datetime | YES | - | `NULL` |  |
| 16 | `notes` | text | YES | - | `NULL` |  |
| 17 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 18 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

FKs: `allocated_to_class_id` → `classes`.`id`; `allocated_to_department_id` → `inventory_departments`.`id`; `item_id` → `inventory_items`.`id`; `issued_by` → `staff`.`id`; `allocated_by` → `staff`.`id`

UNIQUE/INDEX: `uk_allocation_number` (`allocation_number`)

---
## `inventory_categories`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `name` | varchar(100) | NO | - | NULL |  |
| 3 | `code` | varchar(20) | NO | UNI | NULL |  |
| 4 | `description` | text | YES | - | `NULL` |  |
| 5 | `parent_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 6 | `status` | enum('active','inactive') | NO | MUL | `'active'` |  |
| 7 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 8 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |
| 9 | `category_name` | varchar(100) | YES | - | `NULL` | VIRTUAL GENERATED |

FKs: `parent_id` → `inventory_categories`.`id`

UNIQUE/INDEX: `uk_code` (`code`)

---
## `inventory_count_items`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `count_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `item_id` | int(10) unsigned | NO | MUL | NULL |  |
| 4 | `expected_quantity` | int(11) | NO | - | NULL |  |
| 5 | `actual_quantity` | int(11) | YES | - | `NULL` |  |
| 6 | `difference` | int(11) | YES | - | `NULL` | STORED GENERATED |
| 7 | `notes` | text | YES | - | `NULL` |  |
| 8 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |

FKs: `item_id` → `inventory_items`.`id`; `count_id` → `inventory_counts`.`id`

UNIQUE/INDEX: `uk_count_item` (`count_id,item_id`)

---
## `inventory_counts`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `count_date` | date | NO | - | NULL |  |
| 3 | `status` | enum('draft','in_progress','completed','cancelled') | NO | MUL | `'draft'` |  |
| 4 | `counted_by` | int(10) unsigned | NO | MUL | NULL |  |
| 5 | `verified_by` | int(10) unsigned | YES | MUL | `NULL` |  |
| 6 | `notes` | text | YES | - | `NULL` |  |
| 7 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 8 | `completed_at` | datetime | YES | - | `NULL` |  |

FKs: `verified_by` → `staff`.`id`; `counted_by` → `staff`.`id`

---
## `inventory_departments`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `name` | varchar(100) | NO | - | NULL |  |
| 3 | `code` | varchar(20) | NO | UNI | NULL |  |
| 4 | `description` | text | YES | - | `NULL` |  |
| 5 | `department_head_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 6 | `status` | enum('active','inactive') | NO | MUL | `'active'` |  |
| 7 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 8 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

FKs: `department_head_id` → `staff`.`id`

UNIQUE/INDEX: `uk_code` (`code`)

---
## `inventory_items`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `category_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `name` | varchar(255) | NO | MUL | NULL |  |
| 4 | `code` | varchar(50) | NO | UNI | NULL |  |
| 5 | `barcode` | varchar(50) | YES | UNI | `NULL` |  |
| 6 | `sku` | varchar(50) | YES | UNI | `NULL` |  |
| 7 | `description` | text | YES | - | `NULL` |  |
| 8 | `unit` | varchar(20) | NO | - | NULL |  |
| 9 | `minimum_quantity` | int(11) | NO | - | `0` |  |
| 10 | `current_quantity` | int(11) | NO | - | `0` |  |
| 11 | `unit_cost` | decimal(10,2) | NO | - | NULL |  |
| 12 | `location` | varchar(100) | YES | - | `NULL` |  |
| 13 | `location_id` | int(10) unsigned | YES | - | `NULL` |  |
| 14 | `supplier_id` | int(10) unsigned | YES | - | `NULL` |  |
| 15 | `status` | enum('active','inactive','out_of_stock') | NO | MUL | `'active'` |  |
| 16 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 17 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |
| 18 | `brand` | varchar(100) | YES | - | `NULL` |  |
| 19 | `model` | varchar(100) | YES | - | `NULL` |  |
| 20 | `reorder_level` | int(11) | NO | - | `0` |  |
| 21 | `expiry_date` | date | YES | - | `NULL` |  |
| 22 | `batch_tracking` | tinyint(1) | NO | - | `0` |  |
| 23 | `serial_tracking` | tinyint(1) | NO | - | `0` |  |
| 24 | `item_name` | varchar(255) | YES | - | `NULL` | VIRTUAL GENERATED |
| 25 | `quantity_on_hand` | int(11) | YES | - | `NULL` | VIRTUAL GENERATED |
| 26 | `last_purchase_date` | date | YES | - | `NULL` |  |
| 27 | `last_purchase_price` | decimal(10,2) | YES | - | `NULL` |  |
| 28 | `last_audit_date` | date | YES | - | `NULL` |  |

FKs: `category_id` → `inventory_categories`.`id`

UNIQUE/INDEX: `uk_barcode` (`barcode`); `uk_code` (`code`); `uk_sku` (`sku`)

---
## `inventory_locations`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `location_name` | varchar(100) | NO | - | NULL |  |
| 3 | `location_type` | enum('warehouse','store','classroom','lab','office','other') | NO | - | `'store'` |  |
| 4 | `description` | text | YES | - | `NULL` |  |
| 5 | `status` | enum('active','inactive') | NO | - | `'active'` |  |
| 6 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 7 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

---
## `inventory_requisitions`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `requisition_number` | varchar(50) | NO | UNI | NULL |  |
| 3 | `department_id` | int(10) unsigned | NO | MUL | NULL |  |
| 4 | `requisition_date` | date | NO | - | NULL |  |
| 5 | `required_date` | date | NO | MUL | NULL |  |
| 6 | `status` | enum('draft','submitted','pending_approval','approved','rejected','partially_fulfilled','fulfilled','cancelled') | NO | MUL | `'draft'` |  |
| 7 | `priority` | enum('low','normal','high','urgent') | NO | - | `'normal'` |  |
| 8 | `reason` | text | YES | - | `NULL` |  |
| 9 | `created_by` | int(10) unsigned | NO | MUL | NULL |  |
| 10 | `approved_by` | int(10) unsigned | YES | MUL | `NULL` |  |
| 11 | `rejection_reason` | text | YES | - | `NULL` |  |
| 12 | `approved_at` | datetime | YES | - | `NULL` |  |
| 13 | `fulfilled_at` | datetime | YES | - | `NULL` |  |
| 14 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 15 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

FKs: `approved_by` → `staff`.`id`; `created_by` → `staff`.`id`; `department_id` → `inventory_departments`.`id`

UNIQUE/INDEX: `uk_requisition_number` (`requisition_number`)

---
## `inventory_transactions`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `item_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `batch_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 4 | `serial_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 5 | `transaction_type` | enum('in','out') | NO | - | NULL |  |
| 6 | `quantity` | int(11) | NO | - | NULL |  |
| 7 | `transaction_date` | date | NO | - | NULL |  |
| 8 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 9 | `reference_type` | enum('purchase','sale','adjustment','transfer') | NO | - | NULL |  |
| 10 | `reference_id` | int(10) unsigned | YES | - | `NULL` |  |
| 11 | `unit_cost` | decimal(10,2) | YES | - | `NULL` |  |
| 12 | `total_cost` | decimal(10,2) | YES | - | `NULL` |  |
| 13 | `notes` | text | YES | - | `NULL` |  |

FKs: `serial_id` → `item_serials`.`id`; `batch_id` → `item_batches`.`id`; `item_id` → `inventory_items`.`id`

---
## `item_batches`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `item_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `batch_number` | varchar(50) | NO | UNI | NULL |  |
| 4 | `quantity` | int(11) | NO | - | NULL |  |
| 5 | `manufacturing_date` | date | YES | - | `NULL` |  |
| 6 | `expiry_date` | date | YES | - | `NULL` |  |
| 7 | `cost_price` | decimal(10,2) | NO | - | NULL |  |
| 8 | `supplier_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 9 | `status` | enum('active','expired','depleted') | NO | - | `'active'` |  |
| 10 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |

FKs: `supplier_id` → `suppliers`.`id`; `item_id` → `inventory_items`.`id`

UNIQUE/INDEX: `uk_batch_number` (`batch_number`)

---
## `item_serials`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `item_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `serial_number` | varchar(100) | NO | UNI | NULL |  |
| 4 | `batch_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 5 | `status` | enum('in_stock','sold','defective') | NO | - | `'in_stock'` |  |
| 6 | `notes` | text | YES | - | `NULL` |  |
| 7 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |

FKs: `batch_id` → `item_batches`.`id`; `item_id` → `inventory_items`.`id`

UNIQUE/INDEX: `uk_serial_number` (`serial_number`)

---
## `job_applications`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(11) | NO | PRI | NULL | auto_increment |
| 2 | `job_id` | int(11) | YES | MUL | `NULL` |  |
| 3 | `job_title` | varchar(200) | NO | - | NULL |  |
| 4 | `first_name` | varchar(100) | NO | - | NULL |  |
| 5 | `last_name` | varchar(100) | NO | - | NULL |  |
| 6 | `email` | varchar(200) | NO | - | NULL |  |
| 7 | `phone` | varchar(30) | NO | - | NULL |  |
| 8 | `tsc_number` | varchar(50) | YES | - | `NULL` |  |
| 9 | `cv_filename` | varchar(300) | YES | - | `NULL` |  |
| 10 | `cover_letter` | text | YES | - | `NULL` |  |
| 11 | `status` | enum('received','shortlisted','interviewed','hired','rejected') | NO | - | `'received'` |  |
| 12 | `applicant_type` | enum('external','internal') | NO | - | `'external'` |  |
| 13 | `staff_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 14 | `current_position` | varchar(100) | YES | - | `NULL` |  |
| 15 | `current_department_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 16 | `ip_address` | varchar(45) | YES | - | `NULL` |  |
| 17 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 18 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

FKs: `current_department_id` → `departments`.`id`; `staff_id` → `staff`.`id`

UNIQUE/INDEX: `uq_job_internal_staff` (`job_id,applicant_type,staff_id`)

---
## `job_vacancies`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(11) | NO | PRI | NULL | auto_increment |
| 2 | `title` | varchar(200) | NO | - | NULL |  |
| 3 | `department` | varchar(100) | NO | - | NULL |  |
| 4 | `job_type` | varchar(50) | NO | - | `'Full-Time'` |  |
| 5 | `location` | varchar(100) | NO | - | `'Londiani Campus'` |  |
| 6 | `description` | text | NO | - | NULL |  |
| 7 | `requirements` | text | YES | - | `NULL` |  |
| 8 | `responsibilities` | text | YES | - | `NULL` |  |
| 9 | `deadline` | date | NO | - | NULL |  |
| 10 | `color` | varchar(20) | YES | - | `'#198754'` |  |
| 11 | `status` | enum('open','closed','filled') | NO | - | `'open'` |  |
| 12 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 13 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

---
## `kpi_achievements`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `staff_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `kpi_definition_id` | int(10) unsigned | NO | MUL | NULL |  |
| 4 | `academic_year` | int(11) | NO | - | NULL |  |
| 5 | `achieved_value` | decimal(10,2) | NO | - | NULL |  |
| 6 | `achievement_date` | date | NO | - | NULL |  |
| 7 | `recorded_by` | int(10) unsigned | YES | - | `NULL` |  |
| 8 | `notes` | text | YES | - | `NULL` |  |
| 9 | `is_approved` | tinyint(1) | NO | - | `0` |  |
| 10 | `approved_by` | int(10) unsigned | YES | - | `NULL` |  |
| 11 | `approval_date` | datetime | YES | - | `NULL` |  |
| 12 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 13 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

FKs: `staff_id` → `staff`.`id`; `kpi_definition_id` → `kpi_definitions`.`id`

---
## `kpi_definitions`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `staff_category_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `kpi_code` | varchar(50) | NO | MUL | NULL |  |
| 4 | `kpi_name` | varchar(100) | NO | - | NULL |  |
| 5 | `kpi_description` | text | YES | - | `NULL` |  |
| 6 | `measurement_unit` | varchar(50) | YES | - | `NULL` |  |
| 7 | `target_type` | enum('individual','team','department') | NO | - | `'individual'` |  |
| 8 | `is_active` | tinyint(1) | NO | - | `1` |  |
| 9 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 10 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

FKs: `staff_category_id` → `staff_categories`.`id`

UNIQUE/INDEX: `uk_code_category` (`kpi_code,staff_category_id`)

---
## `kpi_targets`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `staff_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `kpi_definition_id` | int(10) unsigned | NO | MUL | NULL |  |
| 4 | `academic_year` | int(11) | NO | - | NULL |  |
| 5 | `target_value` | decimal(10,2) | NO | - | NULL |  |
| 6 | `weight_percentage` | decimal(5,2) | NO | - | `0.00` |  |
| 7 | `set_by` | int(10) unsigned | YES | - | `NULL` |  |
| 8 | `set_date` | datetime | NO | - | NULL |  |
| 9 | `is_active` | tinyint(1) | NO | - | `1` |  |
| 10 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 11 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

FKs: `staff_id` → `staff`.`id`; `kpi_definition_id` → `kpi_definitions`.`id`

---
## `leadership_team`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(11) | NO | PRI | NULL | auto_increment |
| 2 | `name` | varchar(150) | NO | - | NULL |  |
| 3 | `title` | varchar(200) | NO | - | NULL |  |
| 4 | `bio` | text | YES | - | `NULL` |  |
| 5 | `avatar_url` | varchar(500) | YES | - | `NULL` |  |
| 6 | `avatar_color` | varchar(20) | NO | - | `'#198754'` |  |
| 7 | `email` | varchar(200) | YES | - | `NULL` |  |
| 8 | `display_order` | int(11) | NO | MUL | `0` |  |
| 9 | `is_active` | tinyint(1) | NO | - | `1` |  |
| 10 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 11 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

---
## `learner_competencies`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | bigint(20) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `student_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `competency_id` | int(10) unsigned | NO | MUL | NULL |  |
| 4 | `academic_year` | year(4) | NO | - | NULL |  |
| 5 | `term_id` | int(10) unsigned | NO | MUL | NULL |  |
| 6 | `performance_level_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 7 | `evidence` | text | YES | - | `NULL` |  |
| 8 | `teacher_notes` | text | YES | - | `NULL` |  |
| 9 | `assessed_by` | int(10) unsigned | NO | MUL | NULL |  |
| 10 | `assessed_date` | date | NO | - | NULL |  |
| 11 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 12 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

FKs: `term_id` → `academic_terms`.`id`; `student_id` → `students`.`id`; `performance_level_id` → `performance_levels_cbc`.`id`; `competency_id` → `core_competencies`.`id`; `assessed_by` → `users`.`id`

UNIQUE/INDEX: `uk_learner_competency` (`student_id,competency_id,academic_year,term_id`)

---
## `learner_csl_participation`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | bigint(20) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `student_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `csl_activity_id` | int(10) unsigned | NO | MUL | NULL |  |
| 4 | `academic_year` | year(4) | NO | - | NULL |  |
| 5 | `hours_contributed` | int(11) | YES | - | `0` |  |
| 6 | `role` | varchar(100) | YES | - | `NULL` |  |
| 7 | `reflection` | text | YES | - | `NULL` |  |
| 8 | `teacher_feedback` | text | YES | - | `NULL` |  |
| 9 | `participation_status` | enum('participated','completed','pending','excused') | NO | - | `'participated'` |  |
| 10 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 11 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

FKs: `csl_activity_id` → `csl_activities`.`id`; `student_id` → `students`.`id`

UNIQUE/INDEX: `uk_student_activity` (`student_id,csl_activity_id,academic_year`)

---
## `learner_pci_awareness`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | bigint(20) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `student_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `pci_id` | int(10) unsigned | NO | MUL | NULL |  |
| 4 | `academic_year` | year(4) | NO | - | NULL |  |
| 5 | `term_id` | int(10) unsigned | NO | MUL | NULL |  |
| 6 | `awareness_level` | enum('unaware','aware','engaged','advocating') | NO | MUL | `'aware'` |  |
| 7 | `evidence` | text | YES | - | `NULL` |  |
| 8 | `learning_activity` | varchar(255) | YES | - | `NULL` |  |
| 9 | `assessed_by` | int(10) unsigned | NO | MUL | NULL |  |
| 10 | `assessed_date` | date | NO | - | NULL |  |
| 11 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |

FKs: `term_id` → `academic_terms`.`id`; `student_id` → `students`.`id`; `pci_id` → `pcis`.`id`; `assessed_by` → `users`.`id`

UNIQUE/INDEX: `uk_student_pci` (`student_id,pci_id,academic_year,term_id`)

---
## `learner_values_acquisition`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | bigint(20) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `student_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `value_id` | int(10) unsigned | NO | MUL | NULL |  |
| 4 | `academic_year` | year(4) | NO | - | NULL |  |
| 5 | `term_id` | int(10) unsigned | NO | MUL | NULL |  |
| 6 | `evidence` | text | NO | - | NULL |  |
| 7 | `incident_date` | date | NO | - | NULL |  |
| 8 | `recorded_by` | int(10) unsigned | NO | MUL | NULL |  |
| 9 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |

FKs: `value_id` → `core_values`.`id`; `term_id` → `academic_terms`.`id`; `student_id` → `students`.`id`; `recorded_by` → `users`.`id`

---
## `learning_areas`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `name` | varchar(100) | NO | - | NULL |  |
| 3 | `code` | varchar(20) | NO | MUL | NULL |  |
| 4 | `level_band` | varchar(20) | NO | - | `'lower_primary'` |  |
| 5 | `description` | text | YES | - | `NULL` |  |
| 6 | `source_documents` | longtext | YES | - | `NULL` |  |
| 7 | `status` | enum('active','inactive') | NO | MUL | `'active'` |  |
| 8 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 9 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |
| 10 | `levels` | varchar(255) | NO | - | `'NONE'` |  |
| 11 | `is_optional` | tinyint(1) | NO | - | `0` |  |

UNIQUE/INDEX: `uk_code_band` (`code,level_band`)

---
## `learning_outcomes`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `learning_area_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `strand_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 4 | `sub_strand_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 5 | `outcome` | text | NO | - | NULL |  |
| 6 | `grade_level` | varchar(20) | NO | - | NULL |  |
| 7 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |

---
## `leave_types`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `code` | varchar(20) | NO | UNI | NULL |  |
| 3 | `name` | varchar(100) | NO | - | NULL |  |
| 4 | `description` | text | YES | - | `NULL` |  |
| 5 | `days_allowed` | int(11) | YES | - | `NULL` |  |
| 6 | `requires_approval` | tinyint(1) | YES | - | `1` |  |
| 7 | `is_paid` | tinyint(1) | YES | - | `1` |  |
| 8 | `applicable_to` | enum('all','teaching','non_teaching','administration') | YES | - | `'all'` |  |
| 9 | `status` | enum('active','inactive') | YES | - | `'active'` |  |
| 10 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |

UNIQUE/INDEX: `uk_leave_code` (`code`)

---
## `lesson_observations`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `teacher_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `observer_id` | int(10) unsigned | NO | MUL | NULL |  |
| 4 | `intern_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 5 | `learning_area_id` | int(10) unsigned | YES | - | `NULL` |  |
| 6 | `subject_id` | int(10) unsigned | YES | - | `NULL` |  |
| 7 | `class_id` | int(10) unsigned | NO | MUL | NULL |  |
| 8 | `stream_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 9 | `observation_date` | date | NO | MUL | NULL |  |
| 10 | `strengths` | longtext | YES | - | `NULL` |  |
| 11 | `areas_for_improvement` | longtext | YES | - | `NULL` |  |
| 12 | `recommendations` | longtext | YES | - | `NULL` |  |
| 13 | `feedback` | text | YES | - | `NULL` |  |
| 14 | `rating` | decimal(3,2) | YES | - | `NULL` |  |
| 15 | `status` | varchar(30) | NO | - | `'scheduled'` |  |
| 16 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 17 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

FKs: `stream_id` → `class_streams`.`id`; `observer_id` → `staff`.`id`; `intern_id` → `staff`.`id`; `class_id` → `classes`.`id`; `teacher_id` → `staff`.`id`

---
## `lesson_plans`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `teacher_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `learning_area_id` | int(10) unsigned | NO | MUL | NULL |  |
| 4 | `class_id` | int(10) unsigned | NO | MUL | NULL |  |
| 5 | `term_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 6 | `academic_year_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 7 | `unit_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 8 | `strand_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 9 | `sub_strand_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 10 | `coverage_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 11 | `topic` | varchar(255) | NO | - | NULL |  |
| 12 | `subtopic` | varchar(255) | YES | - | `NULL` |  |
| 13 | `objectives` | text | NO | - | NULL |  |
| 14 | `resources` | text | YES | - | `NULL` |  |
| 15 | `activities` | text | NO | - | NULL |  |
| 16 | `assessment` | text | YES | - | `NULL` |  |
| 17 | `homework` | text | YES | - | `NULL` |  |
| 18 | `lesson_date` | date | NO | MUL | NULL |  |
| 19 | `duration` | int(11) | NO | - | NULL |  |
| 20 | `status` | enum('draft','submitted','approved','rejected','completed') | NO | - | `'draft'` |  |
| 21 | `remarks` | text | YES | - | `NULL` |  |
| 22 | `approved_by` | int(10) unsigned | YES | MUL | `NULL` |  |
| 23 | `approved_at` | datetime | YES | - | `NULL` |  |
| 24 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 25 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

FKs: `approved_by` → `staff`.`id`; `class_id` → `classes`.`id`; `learning_area_id` → `learning_areas`.`id`; `teacher_id` → `staff`.`id`

---
## `library_books`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `isbn` | varchar(20) | YES | MUL | `NULL` |  |
| 3 | `title` | varchar(255) | NO | - | NULL |  |
| 4 | `author` | varchar(255) | NO | - | NULL |  |
| 5 | `publisher` | varchar(255) | YES | - | `NULL` |  |
| 6 | `edition` | varchar(50) | YES | - | `NULL` |  |
| 7 | `publication_year` | smallint(5) unsigned | YES | - | `NULL` |  |
| 8 | `category_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 9 | `location_shelf` | varchar(50) | YES | - | `NULL` |  |
| 10 | `total_copies` | smallint(5) unsigned | NO | - | `1` |  |
| 11 | `available_copies` | smallint(5) unsigned | NO | - | `1` |  |
| 12 | `description` | text | YES | - | `NULL` |  |
| 13 | `cover_image_url` | varchar(512) | YES | - | `NULL` |  |
| 14 | `status` | enum('active','inactive','lost','damaged') | NO | MUL | `'active'` |  |
| 15 | `created_at` | datetime | NO | - | `current_timestamp()` |  |
| 16 | `updated_at` | datetime | NO | - | `current_timestamp()` | on update current_timestamp() |
| 17 | `deleted_at` | datetime | YES | - | `NULL` |  |

---
## `library_categories`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `name` | varchar(100) | NO | UNI | NULL |  |
| 3 | `description` | text | YES | - | `NULL` |  |
| 4 | `created_at` | datetime | NO | - | `current_timestamp()` |  |
| 5 | `updated_at` | datetime | NO | - | `current_timestamp()` | on update current_timestamp() |
| 6 | `deleted_at` | datetime | YES | - | `NULL` |  |

UNIQUE/INDEX: `uq_lib_cat_name` (`name`)

---
## `library_fines`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `issue_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `fine_amount` | decimal(10,2) | NO | - | `0.00` |  |
| 4 | `days_overdue` | smallint(5) unsigned | NO | - | `0` |  |
| 5 | `fine_status` | enum('pending','paid','waived') | NO | MUL | `'pending'` |  |
| 6 | `paid_date` | date | YES | - | `NULL` |  |
| 7 | `waived_by` | int(10) unsigned | YES | - | `NULL` |  |
| 8 | `waived_reason` | text | YES | - | `NULL` |  |
| 9 | `created_at` | datetime | NO | - | `current_timestamp()` |  |
| 10 | `updated_at` | datetime | NO | - | `current_timestamp()` | on update current_timestamp() |

---
## `library_issues`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `book_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `borrower_type` | enum('student','staff') | NO | MUL | `'student'` |  |
| 4 | `borrower_id` | int(10) unsigned | NO | - | NULL |  |
| 5 | `issued_by` | int(10) unsigned | NO | - | NULL |  |
| 6 | `issued_date` | date | NO | - | NULL |  |
| 7 | `due_date` | date | NO | MUL | NULL |  |
| 8 | `returned_date` | date | YES | - | `NULL` |  |
| 9 | `returned_by` | int(10) unsigned | YES | - | `NULL` |  |
| 10 | `status` | enum('issued','returned','overdue','lost') | NO | MUL | `'issued'` |  |
| 11 | `notes` | text | YES | - | `NULL` |  |
| 12 | `created_at` | datetime | NO | - | `current_timestamp()` |  |
| 13 | `updated_at` | datetime | NO | - | `current_timestamp()` | on update current_timestamp() |

---
## `login_attempts`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `username` | varchar(100) | NO | MUL | NULL |  |
| 3 | `user_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 4 | `ip_address` | varchar(45) | NO | MUL | NULL |  |
| 5 | `user_agent` | varchar(255) | YES | - | `NULL` |  |
| 6 | `status` | enum('success','failed') | NO | MUL | NULL |  |
| 7 | `failure_reason` | varchar(100) | YES | - | `NULL` |  |
| 8 | `created_at` | timestamp | NO | MUL | `current_timestamp()` |  |

FKs: `user_id` → `users`.`id`

---
## `maintenance_logs`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `maintenance_schedule_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `equipment_id` | int(10) unsigned | NO | MUL | NULL |  |
| 4 | `maintenance_date` | date | NO | - | NULL |  |
| 5 | `service_provider` | varchar(100) | YES | - | `NULL` |  |
| 6 | `cost` | decimal(10,2) | YES | - | `NULL` |  |
| 7 | `description` | text | NO | - | NULL |  |
| 8 | `findings` | text | YES | - | `NULL` |  |
| 9 | `actions_taken` | text | YES | - | `NULL` |  |
| 10 | `status` | enum('completed','in_progress','pending','cancelled') | NO | - | `'completed'` |  |
| 11 | `maintenance_staff_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 12 | `next_service_date` | date | YES | - | `NULL` |  |
| 13 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 14 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

FKs: `maintenance_staff_id` → `staff`.`id`; `equipment_id` → `item_serials`.`id`; `maintenance_schedule_id` → `equipment_maintenance`.`id`

---
## `meal_plans`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `plan_date` | date | NO | MUL | NULL |  |
| 3 | `meal_type` | enum('breakfast','lunch','dinner','snack') | NO | - | NULL |  |
| 4 | `menu_item_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 5 | `planned_servings` | int(11) | NO | - | NULL |  |
| 6 | `prepared_quantity` | int(11) | YES | - | `0` |  |
| 7 | `actual_servings` | int(11) | YES | - | `0` |  |
| 8 | `status` | enum('planned','prepared','served','cancelled') | NO | - | `'planned'` |  |
| 9 | `prepared_by` | int(10) unsigned | YES | MUL | `NULL` |  |
| 10 | `prepared_at` | datetime | YES | - | `NULL` |  |
| 11 | `notes` | text | YES | - | `NULL` |  |
| 12 | `created_by` | int(10) unsigned | NO | MUL | NULL |  |
| 13 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 14 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

FKs: `menu_item_id` → `menu_items`.`id`; `created_by` → `staff`.`id`; `prepared_by` → `staff`.`id`

UNIQUE/INDEX: `uk_meal_plan` (`plan_date,meal_type,menu_item_id`)

---
## `media_files`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(11) | NO | PRI | NULL | auto_increment |
| 2 | `filename` | varchar(255) | NO | - | NULL |  |
| 3 | `original_name` | varchar(255) | YES | - | `NULL` |  |
| 4 | `file_type` | varchar(50) | YES | - | `NULL` |  |
| 5 | `file_size` | int(11) | YES | - | `NULL` |  |
| 6 | `uploader_id` | int(11) | YES | MUL | `NULL` |  |
| 7 | `upload_date` | datetime | YES | - | `current_timestamp()` |  |
| 8 | `context` | varchar(50) | YES | MUL | `NULL` |  |
| 9 | `entity_id` | int(11) | YES | - | `NULL` |  |
| 10 | `album_id` | int(11) | YES | MUL | `NULL` |  |
| 11 | `description` | text | YES | - | `NULL` |  |
| 12 | `tags` | varchar(255) | YES | - | `NULL` |  |
| 13 | `is_active` | tinyint(1) | YES | - | `1` |  |
| 14 | `usage_context` | text | YES | - | `NULL` |  |

FKs: `album_id` → `albums`.`id`

---
## `menu_item_ingredients`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `menu_item_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `inventory_item_id` | int(10) unsigned | NO | MUL | NULL |  |
| 4 | `quantity_per_portion` | decimal(10,2) | NO | - | NULL |  |
| 5 | `unit` | varchar(20) | NO | - | NULL |  |
| 6 | `notes` | text | YES | - | `NULL` |  |
| 7 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |

FKs: `inventory_item_id` → `inventory_items`.`id`

UNIQUE/INDEX: `uk_menu_ingredient` (`menu_item_id,inventory_item_id`)

---
## `menu_items`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `name` | varchar(255) | NO | - | NULL |  |
| 3 | `description` | text | YES | - | `NULL` |  |
| 4 | `meal_type` | enum('breakfast','lunch','dinner','snack') | NO | MUL | NULL |  |
| 5 | `status` | enum('active','inactive') | NO | MUL | `'active'` |  |
| 6 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 7 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

---
## `message_read_status`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `message_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `recipient_id` | int(10) unsigned | NO | MUL | NULL |  |
| 4 | `read_at` | datetime | YES | - | `NULL` |  |
| 5 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |

FKs: `recipient_id` → `staff`.`id`; `message_id` → `internal_messages`.`id`

UNIQUE/INDEX: `uk_message_recipient` (`message_id,recipient_id`)

---
## `message_templates`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `name` | varchar(100) | NO | - | NULL |  |
| 3 | `subject` | varchar(255) | YES | - | `NULL` |  |
| 4 | `body` | text | NO | - | NULL |  |
| 5 | `type` | enum('email','sms','notification') | NO | - | NULL |  |
| 6 | `created_by` | int(10) unsigned | YES | MUL | `NULL` |  |
| 7 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 8 | `category` | varchar(50) | YES | MUL | `NULL` |  |
| 9 | `variables` | longtext | YES | - | `NULL` |  |
| 10 | `last_used_at` | datetime | YES | - | `NULL` |  |
| 11 | `use_count` | int(10) unsigned | NO | - | `0` |  |
| 12 | `status` | enum('active','inactive','archived') | NO | - | `'active'` |  |
| 13 | `category_id` | int(10) unsigned | YES | MUL | `NULL` |  |

FKs: `created_by` → `users`.`id`; `category_id` → `template_categories`.`id`

---
## `mpesa_transactions`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `mpesa_code` | varchar(50) | NO | UNI | NULL |  |
| 3 | `student_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 4 | `amount` | decimal(10,2) | NO | - | NULL |  |
| 5 | `transaction_date` | datetime | NO | - | NULL |  |
| 6 | `phone_number` | varchar(20) | YES | MUL | `NULL` |  |
| 7 | `first_name` | varchar(100) | YES | - | `NULL` |  |
| 8 | `middle_name` | varchar(100) | YES | - | `NULL` |  |
| 9 | `last_name` | varchar(100) | YES | - | `NULL` |  |
| 10 | `org_account_balance` | decimal(15,2) | YES | - | `NULL` |  |
| 11 | `third_party_trans_id` | varchar(100) | YES | - | `NULL` |  |
| 12 | `bill_ref_number` | varchar(100) | YES | MUL | `NULL` |  |
| 13 | `status` | enum('pending','processed','failed','reconciled') | NO | MUL | `'pending'` |  |
| 14 | `transaction_type` | enum('STK_PUSH','C2B','B2C') | YES | - | `'STK_PUSH'` |  |
| 15 | `reconciled_at` | datetime | YES | - | `NULL` |  |
| 16 | `raw_callback` | longtext | YES | - | `NULL` |  |
| 17 | `checkout_request_id` | varchar(100) | YES | MUL | `NULL` |  |
| 18 | `webhook_data` | longtext | YES | - | `NULL` |  |
| 19 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |

FKs: `student_id` → `students`.`id`

UNIQUE/INDEX: `uk_mpesa_code` (`mpesa_code`)

---
## `national_exam_results`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `student_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `exam_type` | enum('KNEC_G3','KPSEA_G6','KJSEA_G9') | NO | MUL | NULL |  |
| 4 | `academic_year_id` | int(10) unsigned | YES | - | `NULL` |  |
| 5 | `exam_year` | smallint(5) unsigned | NO | MUL | NULL |  |
| 6 | `learning_area_id` | int(10) unsigned | NO | - | NULL |  |
| 7 | `score` | decimal(6,2) | YES | - | `NULL` |  |
| 8 | `max_score` | decimal(6,2) | YES | - | `NULL` |  |
| 9 | `percentage` | decimal(5,2) | YES | - | `NULL` |  |
| 10 | `cbc_grade` | enum('EE','ME','AE','BE') | YES | - | `NULL` |  |
| 11 | `raw_grade` | varchar(10) | YES | - | `NULL` |  |
| 12 | `points` | decimal(4,1) | YES | - | `NULL` |  |
| 13 | `pathway` | enum('AST','STEM','Social_Sciences','Humanities') | YES | - | `NULL` |  |
| 14 | `remarks` | text | YES | - | `NULL` |  |
| 15 | `entered_by` | int(10) unsigned | YES | - | `NULL` |  |
| 16 | `created_at` | datetime | NO | - | `current_timestamp()` |  |
| 17 | `updated_at` | datetime | NO | - | `current_timestamp()` | on update current_timestamp() |

UNIQUE/INDEX: `uq_natl_exam` (`student_id,exam_type,exam_year,learning_area_id`)

---
## `news_articles`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(11) | NO | PRI | NULL | auto_increment |
| 2 | `title` | varchar(255) | NO | - | NULL |  |
| 3 | `slug` | varchar(255) | NO | UNI | NULL |  |
| 4 | `excerpt` | varchar(600) | YES | - | `NULL` |  |
| 5 | `content` | longtext | NO | - | NULL |  |
| 6 | `category` | varchar(50) | NO | - | `'Announcement'` |  |
| 7 | `image_url` | varchar(500) | YES | - | `NULL` |  |
| 8 | `author` | varchar(100) | NO | - | `'Kingsway Admin'` |  |
| 9 | `status` | enum('draft','published','archived') | NO | MUL | `'published'` |  |
| 10 | `views` | int(11) | NO | - | `0` |  |
| 11 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 12 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |
| 13 | `deleted_at` | timestamp | YES | - | `NULL` |  |

UNIQUE/INDEX: `slug` (`slug`)

---
## `news_categories`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(11) | NO | PRI | NULL | auto_increment |
| 2 | `name` | varchar(50) | NO | - | NULL |  |
| 3 | `slug` | varchar(50) | NO | UNI | NULL |  |
| 4 | `color` | varchar(20) | NO | - | `'#198754'` |  |
| 5 | `display_order` | int(11) | NO | - | `0` |  |
| 6 | `is_active` | tinyint(1) | NO | - | `1` |  |

UNIQUE/INDEX: `uk_slug` (`slug`)

---
## `newsletter_subscribers`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(11) | NO | PRI | NULL | auto_increment |
| 2 | `email` | varchar(200) | NO | UNI | NULL |  |
| 3 | `name` | varchar(150) | YES | - | `NULL` |  |
| 4 | `status` | enum('active','unsubscribed') | NO | - | `'active'` |  |
| 5 | `subscribed_at` | timestamp | NO | - | `current_timestamp()` |  |
| 6 | `unsubscribed_at` | timestamp | YES | - | `NULL` |  |

UNIQUE/INDEX: `email` (`email`)

---
## `notifications`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `user_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `type` | varchar(50) | NO | - | NULL |  |
| 4 | `title` | varchar(255) | NO | - | NULL |  |
| 5 | `message` | text | NO | - | NULL |  |
| 6 | `priority` | enum('low','medium','high') | NO | - | `'medium'` |  |
| 7 | `read_status` | enum('unread','read') | NO | - | `'unread'` |  |
| 8 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |

FKs: `user_id` → `users`.`id`

---
## `onboarding_documents`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `onboarding_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `staff_id` | int(10) unsigned | NO | MUL | NULL |  |
| 4 | `document_type` | varchar(100) | NO | - | NULL |  |
| 5 | `document_name` | varchar(255) | YES | - | `NULL` |  |
| 6 | `file_url` | varchar(500) | YES | - | `NULL` |  |
| 7 | `is_original_seen` | tinyint(1) | YES | - | `0` |  |
| 8 | `is_copy_filed` | tinyint(1) | YES | - | `0` |  |
| 9 | `verified_by` | int(10) unsigned | YES | - | `NULL` |  |
| 10 | `verified_at` | datetime | YES | - | `NULL` |  |
| 11 | `notes` | text | YES | - | `NULL` |  |
| 12 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |

FKs: `staff_id` → `staff`.`id`; `onboarding_id` → `staff_onboarding`.`id`

---
## `onboarding_tasks`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `onboarding_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `task_name` | varchar(255) | NO | - | NULL |  |
| 4 | `description` | text | YES | - | `NULL` |  |
| 5 | `category` | varchar(50) | YES | MUL | `NULL` |  |
| 6 | `department_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 7 | `assigned_to` | int(10) unsigned | YES | MUL | `NULL` |  |
| 8 | `due_date` | date | NO | MUL | NULL |  |
| 9 | `priority` | enum('low','medium','high') | NO | - | `'medium'` |  |
| 10 | `sequence` | int(11) | YES | - | `NULL` |  |
| 11 | `status` | enum('pending','in_progress','completed','blocked','skipped') | YES | - | `'pending'` |  |
| 12 | `completed_date` | datetime | YES | - | `NULL` |  |
| 13 | `completed_by` | int(10) unsigned | YES | - | `NULL` |  |
| 14 | `notes` | text | YES | - | `NULL` |  |
| 15 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 16 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

FKs: `onboarding_id` → `staff_onboarding`.`id`; `department_id` → `departments`.`id`; `assigned_to` → `users`.`id`

---
## `onboarding_task_templates`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `template_code` | varchar(50) | NO | UNI | NULL |  |
| 3 | `task_name` | varchar(255) | NO | - | NULL |  |
| 4 | `description` | text | YES | - | `NULL` |  |
| 5 | `category` | enum('documentation','hr_admin','it_setup','finance_setup','academic','welfare','probation') | NO | - | NULL |  |
| 6 | `applies_to_type_ids` | longtext | YES | - | `NULL` |  |
| 7 | `days_from_start` | int(11) | NO | - | `1` |  |
| 8 | `priority` | enum('low','medium','high') | YES | - | `'medium'` |  |
| 9 | `responsible_role` | varchar(50) | YES | - | `NULL` |  |
| 10 | `is_mandatory` | tinyint(1) | YES | - | `1` |  |
| 11 | `display_order` | int(11) | YES | - | `0` |  |
| 12 | `status` | enum('active','inactive') | YES | - | `'active'` |  |
| 13 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |

UNIQUE/INDEX: `template_code` (`template_code`)

---
## `outbound_messages`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | bigint(20) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `user_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 3 | `channel` | enum('email','sms','internal') | NO | - | NULL |  |
| 4 | `recipient` | varchar(255) | NO | - | NULL |  |
| 5 | `template_key` | varchar(100) | NO | - | NULL |  |
| 6 | `subject` | varchar(255) | YES | - | `NULL` |  |
| 7 | `payload_json` | longtext | NO | - | NULL |  |
| 8 | `status` | enum('queued','processing','sent','retry','failed','cancelled') | NO | MUL | `'queued'` |  |
| 9 | `attempts` | int(11) | NO | - | `0` |  |
| 10 | `next_attempt_at` | datetime | NO | - | NULL |  |
| 11 | `sent_at` | datetime | YES | - | `NULL` |  |
| 12 | `last_error` | text | YES | - | `NULL` |  |
| 13 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 14 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

---
## `page_downloads`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(11) | NO | PRI | NULL | auto_increment |
| 2 | `title` | varchar(200) | NO | - | NULL |  |
| 3 | `description` | varchar(400) | YES | - | `NULL` |  |
| 4 | `storage_filename` | varchar(255) | YES | - | `NULL` |  |
| 5 | `public_token` | char(64) | YES | UNI | `NULL` |  |
| 6 | `original_filename` | varchar(255) | YES | - | `NULL` |  |
| 7 | `mime_type` | varchar(150) | YES | - | `NULL` |  |
| 8 | `file_size_bytes` | bigint(20) unsigned | YES | - | `NULL` |  |
| 9 | `token_created_at` | datetime | YES | - | `NULL` |  |
| 10 | `token_revoked_at` | datetime | YES | - | `NULL` |  |
| 11 | `download_count` | bigint(20) unsigned | NO | - | `0` |  |
| 12 | `last_downloaded_at` | datetime | YES | - | `NULL` |  |
| 13 | `created_by` | bigint(20) unsigned | YES | - | `NULL` |  |
| 14 | `updated_by` | bigint(20) unsigned | YES | - | `NULL` |  |
| 15 | `file_url` | varchar(500) | NO | - | NULL |  |
| 16 | `file_type` | varchar(10) | NO | - | `'PDF'` |  |
| 17 | `file_size` | varchar(30) | YES | - | `NULL` |  |
| 18 | `category` | varchar(50) | NO | MUL | `'General'` |  |
| 19 | `icon` | varchar(50) | NO | - | `'bi-file-earmark-pdf-fill'` |  |
| 20 | `color` | varchar(20) | NO | - | `'#198754'` |  |
| 21 | `display_order` | int(11) | NO | - | `0` |  |
| 22 | `is_active` | tinyint(1) | NO | MUL | `1` |  |
| 23 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 24 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

UNIQUE/INDEX: `uq_page_downloads_public_token` (`public_token`)

---
## `parent_communication_preferences`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `parent_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `phone_number` | varchar(20) | NO | MUL | NULL |  |
| 4 | `is_primary` | tinyint(1) | NO | - | `1` |  |
| 5 | `sms_enabled` | tinyint(1) | NO | MUL | `1` |  |
| 6 | `email_enabled` | tinyint(1) | NO | - | `1` |  |
| 7 | `whatsapp_enabled` | tinyint(1) | NO | - | `0` |  |
| 8 | `sms_do_not_disturb_start` | time | YES | - | `NULL` |  |
| 9 | `sms_do_not_disturb_end` | time | YES | - | `NULL` |  |
| 10 | `preferred_language` | varchar(20) | YES | - | `'en'` |  |
| 11 | `verified` | tinyint(1) | NO | - | `0` |  |
| 12 | `verified_at` | datetime | YES | - | `NULL` |  |
| 13 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 14 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

FKs: `parent_id` → `parents`.`id`

---
## `parent_meetings`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(11) | NO | PRI | NULL | auto_increment |
| 2 | `title` | varchar(255) | NO | - | NULL |  |
| 3 | `parent_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 4 | `student_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 5 | `created_by` | int(10) unsigned | NO | MUL | NULL |  |
| 6 | `meeting_date` | date | NO | - | NULL |  |
| 7 | `start_time` | time | YES | - | `NULL` |  |
| 8 | `venue` | varchar(255) | YES | - | `NULL` |  |
| 9 | `purpose` | text | YES | - | `NULL` |  |
| 10 | `description` | text | YES | - | `NULL` |  |
| 11 | `minutes` | text | YES | - | `NULL` |  |
| 12 | `class_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 13 | `status` | enum('scheduled','confirmed','cancelled','postponed','completed','held') | YES | - | `'scheduled'` |  |
| 14 | `type` | enum('individual','class','general','pta') | YES | - | `'individual'` |  |
| 15 | `attendance_count` | int(11) | YES | - | `0` |  |
| 16 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 17 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

FKs: `class_id` → `classes`.`id`; `created_by` → `users`.`id`; `student_id` → `students`.`id`; `parent_id` → `parents`.`id`

---
## `parent_otp_sessions`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `parent_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `phone` | varchar(20) | NO | MUL | NULL |  |
| 4 | `otp_code` | varchar(8) | NO | - | NULL |  |
| 5 | `otp_expires_at` | datetime | NO | MUL | NULL |  |
| 6 | `verified` | tinyint(1) | NO | - | `0` |  |
| 7 | `attempts` | tinyint(3) unsigned | NO | - | `0` |  |
| 8 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |

---
## `parent_portal_messages`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `parent_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `student_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 4 | `sender_type` | enum('parent','school','staff','admin') | NO | - | NULL |  |
| 5 | `sender_id` | int(10) unsigned | NO | MUL | NULL |  |
| 6 | `recipient_type` | enum('parent','school','staff','admin') | NO | - | NULL |  |
| 7 | `recipient_id` | int(10) unsigned | NO | MUL | NULL |  |
| 8 | `subject` | varchar(255) | NO | - | NULL |  |
| 9 | `body` | text | NO | - | NULL |  |
| 10 | `status` | enum('sent','read','archived','deleted') | NO | - | `'sent'` |  |
| 11 | `is_reply` | tinyint(1) | NO | - | `0` |  |
| 12 | `reply_to_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 13 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |

---
## `parent_portal_sessions`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `parent_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `session_token` | varchar(64) | NO | UNI | NULL |  |
| 4 | `issued_at` | datetime | NO | - | NULL |  |
| 5 | `expires_at` | datetime | NO | MUL | NULL |  |
| 6 | `ip_address` | varchar(45) | YES | - | `NULL` |  |
| 7 | `user_agent` | varchar(255) | YES | - | `NULL` |  |
| 8 | `status` | enum('active','revoked') | NO | - | `'active'` |  |

UNIQUE/INDEX: `uk_session_token` (`session_token`)

---
## `parents`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `first_name` | varchar(50) | NO | MUL | NULL |  |
| 3 | `middle_name` | varchar(50) | YES | - | `NULL` |  |
| 4 | `last_name` | varchar(50) | NO | - | NULL |  |
| 5 | `id_number` | varchar(30) | YES | MUL | `NULL` |  |
| 6 | `gender` | enum('male','female','other') | NO | - | NULL |  |
| 7 | `date_of_birth` | date | YES | - | `NULL` |  |
| 8 | `phone_1` | varchar(20) | NO | MUL | NULL |  |
| 9 | `phone_2` | varchar(20) | YES | - | `NULL` |  |
| 10 | `email` | varchar(100) | YES | MUL | `NULL` |  |
| 11 | `occupation` | varchar(100) | YES | - | `NULL` |  |
| 12 | `address` | text | YES | - | `NULL` |  |
| 13 | `status` | enum('active','inactive') | NO | MUL | `'active'` |  |
| 14 | `portal_password` | varchar(255) | YES | - | `NULL` |  |
| 15 | `portal_status` | enum('active','inactive','suspended') | NO | - | `'active'` |  |
| 16 | `portal_last_login` | datetime | YES | - | `NULL` |  |
| 17 | `portal_password_reset_token` | varchar(64) | YES | - | `NULL` |  |
| 18 | `portal_password_reset_expires` | datetime | YES | - | `NULL` |  |
| 19 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 20 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

---
## `parent_statement_downloads`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `parent_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `student_id` | int(10) unsigned | NO | MUL | NULL |  |
| 4 | `academic_year` | year(4) | YES | - | `NULL` |  |
| 5 | `term_id` | int(10) unsigned | YES | - | `NULL` |  |
| 6 | `downloaded_at` | timestamp | NO | - | `current_timestamp()` |  |
| 7 | `ip_address` | varchar(45) | YES | - | `NULL` |  |

---
## `password_history`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `user_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `password_hash` | varchar(255) | NO | - | NULL |  |
| 4 | `created_at` | timestamp | NO | MUL | `current_timestamp()` |  |

FKs: `user_id` → `users`.`id`

---
## `password_resets`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `email` | varchar(100) | NO | MUL | NULL |  |
| 3 | `token` | varchar(255) | NO | - | NULL |  |
| 4 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 5 | `expires_at` | timestamp | NO | MUL | `'0000-00-00 00:00:00'` |  |
| 6 | `used` | tinyint(1) | NO | - | `0` |  |

---
## `past_papers`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `title` | varchar(255) | NO | - | NULL |  |
| 3 | `description` | text | YES | - | `NULL` |  |
| 4 | `subject_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 5 | `learning_area_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 6 | `exam_year` | year(4) | NO | MUL | NULL |  |
| 7 | `exam_type` | enum('kcpe','kcse','mock','internal','other') | YES | MUL | `'internal'` |  |
| 8 | `term_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 9 | `academic_year_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 10 | `file_path` | varchar(500) | NO | - | NULL |  |
| 11 | `file_name` | varchar(255) | NO | - | NULL |  |
| 12 | `file_type` | varchar(50) | NO | - | NULL |  |
| 13 | `file_size` | bigint(20) unsigned | YES | - | `NULL` |  |
| 14 | `uploaded_by` | int(10) unsigned | NO | MUL | NULL |  |
| 15 | `download_count` | int(10) unsigned | YES | - | `0` |  |
| 16 | `status` | enum('active','archived') | YES | MUL | `'active'` |  |
| 17 | `uploaded_at` | timestamp | NO | MUL | `current_timestamp()` |  |
| 18 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 19 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

FKs: `term_id` → `academic_terms`.`id`; `subject_id` → `curriculum_units`.`id`; `learning_area_id` → `learning_areas`.`id`; `academic_year_id` → `academic_years`.`id`; `uploaded_by` → `staff`.`id`

---
## `payment_allocations`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `payment_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `fee_structure_id` | int(10) unsigned | NO | MUL | NULL |  |
| 4 | `amount` | decimal(10,2) | NO | - | NULL |  |
| 5 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |

FKs: `fee_structure_id` → `fee_structures`.`id`; `payment_id` → `school_transactions`.`id`

---
## `payment_allocations_detailed`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `payment_transaction_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `student_fee_obligation_id` | int(10) unsigned | NO | MUL | NULL |  |
| 4 | `amount_allocated` | decimal(10,2) | NO | - | NULL |  |
| 5 | `allocation_date` | timestamp | NO | - | `current_timestamp()` |  |
| 6 | `allocated_by` | int(10) unsigned | YES | MUL | `NULL` |  |
| 7 | `notes` | text | YES | - | `NULL` |  |

---
## `payment_reconciliations`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `transaction_id` | int(10) unsigned | NO | UNI | NULL |  |
| 3 | `reconciled_by` | int(10) unsigned | NO | MUL | NULL |  |
| 4 | `reconciled_at` | timestamp | NO | MUL | `current_timestamp()` |  |
| 5 | `bank_statement_ref` | varchar(100) | NO | - | NULL |  |
| 6 | `notes` | text | YES | - | `NULL` |  |

FKs: `reconciled_by` → `users`.`id`; `transaction_id` → `school_transactions`.`id`

UNIQUE/INDEX: `uk_transaction` (`transaction_id`)

---
## `payment_security_audit`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(11) | NO | PRI | NULL | auto_increment |
| 2 | `event_type` | enum('signature_validation_failed','duplicate_payment_attempt','invalid_amount','balance_exceeded','race_condition_detected') | NO | MUL | NULL |  |
| 3 | `webhook_source` | varchar(50) | NO | - | NULL |  |
| 4 | `transaction_ref` | varchar(255) | YES | MUL | `NULL` |  |
| 5 | `student_id` | int(11) | YES | - | `NULL` |  |
| 6 | `amount` | decimal(10,2) | YES | - | `NULL` |  |
| 7 | `ip_address` | varchar(45) | YES | - | `NULL` |  |
| 8 | `headers` | longtext | YES | - | `NULL` |  |
| 9 | `details` | text | YES | - | `NULL` |  |
| 10 | `created_at` | timestamp | NO | MUL | `current_timestamp()` |  |

---
## `payment_transactions`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `student_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `academic_year` | year(4) | YES | MUL | `NULL` |  |
| 4 | `term_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 5 | `term_allocation` | decimal(10,2) | YES | - | `NULL` |  |
| 6 | `fee_structure_detail_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 7 | `transport_bill_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 8 | `parent_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 9 | `amount_paid` | decimal(10,2) | NO | - | NULL |  |
| 10 | `payment_date` | datetime | NO | - | NULL |  |
| 11 | `payment_method` | enum('cash','bank_transfer','mpesa','cheque','other') | NO | MUL | NULL |  |
| 12 | `reference_no` | varchar(100) | YES | MUL | `NULL` |  |
| 13 | `receipt_no` | varchar(50) | YES | MUL | `NULL` |  |
| 14 | `received_by` | int(10) unsigned | YES | MUL | `NULL` |  |
| 15 | `status` | enum('pending','confirmed','failed','reversed') | NO | MUL | `'pending'` |  |
| 16 | `notes` | text | YES | - | `NULL` |  |
| 17 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 18 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

FKs: `term_id` → `academic_terms`.`id`; `fee_structure_detail_id` → `fee_structures_detailed`.`id`

---
## `payment_webhooks_log`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `source` | enum('mpesa_stk','mpesa_c2b_validation','mpesa_c2b_confirmation','kcb_bank','generic_bank') | NO | MUL | NULL |  |
| 3 | `webhook_data` | longtext | NO | - | NULL |  |
| 4 | `status` | enum('received','validated','processed','failed') | YES | MUL | `'received'` |  |
| 5 | `error_message` | text | YES | - | `NULL` |  |
| 6 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 7 | `signature_verified` | tinyint(1) | YES | - | `0` |  |
| 8 | `validation_error` | varchar(255) | YES | - | `NULL` |  |
| 9 | `ip_address` | varchar(45) | YES | - | `NULL` |  |
| 10 | `request_method` | varchar(10) | YES | - | `NULL` |  |
| 11 | `requires_review` | tinyint(1) | YES | - | `0` |  |

---
## `payroll_configurations`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `config_key` | varchar(100) | NO | MUL | NULL |  |
| 3 | `config_value` | text | NO | - | NULL |  |
| 4 | `financial_year` | int(11) | NO | - | NULL |  |
| 5 | `description` | text | YES | - | `NULL` |  |
| 6 | `is_active` | tinyint(1) | NO | - | `1` |  |
| 7 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 8 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

UNIQUE/INDEX: `uk_key_year` (`config_key,financial_year`)

---
## `payslip_items`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `payslip_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `item_type` | enum('allowance','deduction','statutory','child_fees','loan','advance') | NO | MUL | NULL |  |
| 4 | `item_code` | varchar(50) | NO | MUL | NULL |  |
| 5 | `item_name` | varchar(150) | NO | - | NULL |  |
| 6 | `description` | text | YES | - | `NULL` |  |
| 7 | `amount` | decimal(12,2) | NO | - | NULL |  |
| 8 | `is_taxable` | tinyint(1) | NO | - | `0` |  |
| 9 | `reference_id` | int(10) unsigned | YES | - | `NULL` |  |
| 10 | `reference_type` | varchar(50) | YES | - | `NULL` |  |
| 11 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |

FKs: `payslip_id` → `payslips`.`id`

---
## `payslips`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `staff_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `payroll_month` | int(11) | NO | MUL | NULL |  |
| 4 | `payroll_year` | int(11) | NO | - | NULL |  |
| 5 | `basic_salary` | decimal(12,2) | NO | - | NULL |  |
| 6 | `allowances_total` | decimal(12,2) | NO | - | `0.00` |  |
| 7 | `gross_salary` | decimal(12,2) | NO | - | NULL |  |
| 8 | `paye_tax` | decimal(12,2) | NO | - | `0.00` |  |
| 9 | `nssf_contribution` | decimal(12,2) | NO | - | `0.00` |  |
| 10 | `nhif_contribution` | decimal(12,2) | NO | - | `0.00` |  |
| 11 | `loan_deduction` | decimal(12,2) | NO | - | `0.00` |  |
| 12 | `child_fees_deduction` | decimal(12,2) | NO | - | `0.00` |  |
| 13 | `sacco_deduction` | decimal(12,2) | NO | - | `0.00` |  |
| 14 | `housing_levy` | decimal(12,2) | NO | - | `0.00` |  |
| 15 | `salary_advance_deduction` | decimal(12,2) | NO | - | `0.00` |  |
| 16 | `other_deductions_total` | decimal(12,2) | NO | - | `0.00` |  |
| 17 | `net_salary` | decimal(12,2) | NO | - | NULL |  |
| 18 | `payment_method` | enum('bank','cash','check','mobile_money') | YES | - | `'bank'` |  |
| 19 | `payment_date` | date | YES | - | `NULL` |  |
| 20 | `payslip_status` | enum('draft','approved','paid','cancelled') | NO | MUL | `'draft'` |  |
| 21 | `payment_status` | enum('pending','processing','paid','failed') | NO | - | `'pending'` |  |
| 22 | `payment_reference` | varchar(100) | YES | - | `NULL` |  |
| 23 | `paid_at` | datetime | YES | - | `NULL` |  |
| 24 | `signed_by` | int(10) unsigned | YES | - | `NULL` |  |
| 25 | `notes` | text | YES | - | `NULL` |  |
| 26 | `allowances_breakdown` | longtext | YES | - | `NULL` |  |
| 27 | `deductions_breakdown` | longtext | YES | - | `NULL` |  |
| 28 | `child_fees_breakdown` | longtext | YES | - | `NULL` |  |
| 29 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 30 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

FKs: `staff_id` → `staff`.`id`

UNIQUE/INDEX: `uk_staff_month_year` (`staff_id,payroll_month,payroll_year`)

---
## `pcis`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `topic_code` | varchar(20) | NO | UNI | NULL |  |
| 3 | `topic_name` | varchar(255) | NO | - | NULL |  |
| 4 | `description` | text | YES | - | `NULL` |  |
| 5 | `category` | varchar(100) | YES | - | `NULL` |  |
| 6 | `learning_area_id` | int(10) unsigned | YES | - | `NULL` |  |
| 7 | `grade_applicable` | varchar(50) | YES | - | `NULL` |  |
| 8 | `learning_resources` | text | YES | - | `NULL` |  |
| 9 | `status` | enum('active','inactive') | NO | - | `'active'` |  |
| 10 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |

UNIQUE/INDEX: `uk_topic_code` (`topic_code`)

---
## `performance_levels_cbc`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `level` | int(11) | NO | UNI | NULL |  |
| 3 | `code` | varchar(10) | NO | - | NULL |  |
| 4 | `name` | varchar(50) | NO | - | NULL |  |
| 5 | `description` | text | YES | - | `NULL` |  |
| 6 | `mark_range_min` | decimal(5,2) | NO | - | NULL |  |
| 7 | `mark_range_max` | decimal(5,2) | NO | - | NULL |  |
| 8 | `feedback_template` | varchar(500) | YES | - | `NULL` |  |
| 9 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |

UNIQUE/INDEX: `uk_level` (`level`)

---
## `performance_ratings`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `staff_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `rating_period` | varchar(20) | NO | - | NULL |  |
| 4 | `overall_rating` | varchar(20) | NO | - | NULL |  |
| 5 | `kpi_achievement_score` | decimal(5,2) | YES | - | `NULL` |  |
| 6 | `supervisor_rating` | varchar(20) | YES | - | `NULL` |  |
| 7 | `supervisor_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 8 | `rated_date` | date | NO | - | NULL |  |
| 9 | `comments` | text | YES | - | `NULL` |  |
| 10 | `is_final` | tinyint(1) | NO | - | `0` |  |
| 11 | `signed_by` | int(10) unsigned | YES | - | `NULL` |  |
| 12 | `signed_date` | datetime | YES | - | `NULL` |  |
| 13 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 14 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

FKs: `staff_id` → `staff`.`id`

---
## `performance_review_kpis`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `review_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `kpi_template_id` | int(10) unsigned | NO | MUL | NULL |  |
| 4 | `weight` | decimal(5,2) | YES | - | `0.00` |  |
| 5 | `target_value` | decimal(10,2) | YES | - | `NULL` |  |
| 6 | `actual_value` | decimal(10,2) | YES | - | `NULL` |  |
| 7 | `score` | decimal(5,2) | YES | - | `NULL` |  |
| 8 | `comments` | text | YES | - | `NULL` |  |
| 9 | `status` | enum('pending','in_progress','completed','not_applicable') | YES | - | `'pending'` |  |
| 10 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 11 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

FKs: `kpi_template_id` → `staff_kpi_templates`.`id`; `review_id` → `staff_performance_reviews`.`id`

---
## `permission_delegations`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `delegated_from_user_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `delegated_to_user_id` | int(10) unsigned | NO | MUL | NULL |  |
| 4 | `form_permission_id` | int(10) unsigned | NO | - | NULL |  |
| 5 | `delegation_start_date` | date | NO | - | NULL |  |
| 6 | `delegation_end_date` | date | NO | - | NULL |  |
| 7 | `reason` | text | YES | - | `NULL` |  |
| 8 | `approved_by` | int(10) unsigned | YES | - | `NULL` |  |
| 9 | `approval_date` | datetime | YES | - | `NULL` |  |
| 10 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 11 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

FKs: `delegated_to_user_id` → `users`.`id`; `delegated_from_user_id` → `users`.`id`

---
## `permissions`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(11) | NO | PRI | NULL | auto_increment |
| 2 | `code` | varchar(255) | NO | UNI | NULL |  |
| 3 | `description` | varchar(500) | YES | - | `NULL` |  |
| 4 | `entity` | varchar(100) | YES | MUL | `NULL` |  |
| 5 | `action` | varchar(100) | YES | - | `NULL` |  |
| 6 | `module` | varchar(100) | YES | MUL | `NULL` |  |
| 7 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 8 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

UNIQUE/INDEX: `code` (`code`); `code_2` (`code`)

---
## `petty_cash_funds`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `fund_name` | varchar(100) | NO | - | `'Main Petty Cash'` |  |
| 3 | `custodian_id` | int(10) unsigned | NO | - | NULL |  |
| 4 | `opening_balance` | decimal(15,2) | NO | - | `0.00` |  |
| 5 | `current_balance` | decimal(15,2) | NO | - | `0.00` |  |
| 6 | `float_limit` | decimal(15,2) | NO | - | `10000.00` |  |
| 7 | `replenishment_amount` | decimal(15,2) | NO | - | `5000.00` |  |
| 8 | `last_reconciled_at` | datetime | YES | - | `NULL` |  |
| 9 | `last_reconciled_by` | int(10) unsigned | YES | - | `NULL` |  |
| 10 | `status` | enum('active','inactive','suspended') | NO | - | `'active'` |  |
| 11 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 12 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

---
## `petty_cash_reconciliations`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `fund_id` | int(10) unsigned | NO | MUL | `1` |  |
| 3 | `period_from` | date | NO | - | NULL |  |
| 4 | `period_to` | date | NO | - | NULL |  |
| 5 | `system_balance` | decimal(15,2) | NO | - | NULL |  |
| 6 | `physical_count` | decimal(15,2) | NO | - | NULL |  |
| 7 | `variance` | decimal(15,2) | YES | - | `NULL` | STORED GENERATED |
| 8 | `variance_reason` | text | YES | - | `NULL` |  |
| 9 | `status` | enum('draft','approved') | NO | - | `'draft'` |  |
| 10 | `reconciled_by` | int(10) unsigned | NO | - | NULL |  |
| 11 | `approved_by` | int(10) unsigned | YES | - | `NULL` |  |
| 12 | `approved_at` | datetime | YES | - | `NULL` |  |
| 13 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |

---
## `petty_cash_transactions`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `fund_id` | int(10) unsigned | NO | MUL | `1` |  |
| 3 | `type` | enum('expense','top_up','reconciliation_adjustment') | NO | MUL | NULL |  |
| 4 | `category_id` | int(10) unsigned | YES | - | `NULL` |  |
| 5 | `description` | varchar(500) | NO | - | NULL |  |
| 6 | `amount` | decimal(15,2) | NO | - | NULL |  |
| 7 | `balance_after` | decimal(15,2) | NO | - | NULL |  |
| 8 | `transaction_date` | date | NO | MUL | NULL |  |
| 9 | `receipt_number` | varchar(100) | YES | - | `NULL` |  |
| 10 | `vendor_name` | varchar(255) | YES | - | `NULL` |  |
| 11 | `notes` | text | YES | - | `NULL` |  |
| 12 | `recorded_by` | int(10) unsigned | NO | - | NULL |  |
| 13 | `approved_by` | int(10) unsigned | YES | - | `NULL` |  |
| 14 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |

---
## `portfolio_artifacts`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | bigint(20) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `portfolio_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `artifact_title` | varchar(255) | NO | - | NULL |  |
| 4 | `artifact_type` | enum('assignment','project','photo','video','document','reflection','other') | NO | - | NULL |  |
| 5 | `file_path` | varchar(500) | YES | - | `NULL` |  |
| 6 | `media_id` | int(11) | YES | MUL | `NULL` |  |
| 7 | `description` | text | YES | - | `NULL` |  |
| 8 | `upload_date` | date | NO | - | NULL |  |
| 9 | `learner_reflection` | text | YES | - | `NULL` |  |
| 10 | `teacher_feedback` | text | YES | - | `NULL` |  |
| 11 | `competency_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 12 | `value_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 13 | `rating` | decimal(3,1) | YES | - | `NULL` |  |
| 14 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |

FKs: `value_id` → `core_values`.`id`; `portfolio_id` → `portfolios`.`id`; `competency_id` → `core_competencies`.`id`; `media_id` → `media_files`.`id`

---
## `portfolios`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `student_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `academic_year` | year(4) | NO | - | NULL |  |
| 4 | `portfolio_type` | enum('physical','digital') | NO | - | `'digital'` |  |
| 5 | `title` | varchar(255) | YES | - | `NULL` |  |
| 6 | `theme` | varchar(255) | YES | - | `NULL` |  |
| 7 | `description` | text | YES | - | `NULL` |  |
| 8 | `created_date` | date | NO | - | NULL |  |
| 9 | `last_updated` | date | YES | - | `NULL` |  |
| 10 | `status` | enum('active','archived','submitted') | NO | MUL | `'active'` |  |
| 11 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 12 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

FKs: `student_id` → `students`.`id`

UNIQUE/INDEX: `uk_portfolio` (`student_id,academic_year`)

---
## `promotion_batches`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `from_academic_year` | year(4) | NO | MUL | NULL |  |
| 3 | `to_academic_year` | year(4) | NO | - | NULL |  |
| 4 | `batch_type` | enum('bulk_grade','bulk_class','single_class','manual') | NO | MUL | NULL |  |
| 5 | `batch_scope` | varchar(255) | YES | - | `NULL` |  |
| 6 | `status` | enum('pending','in_progress','completed','cancelled') | NO | MUL | `'pending'` |  |
| 7 | `total_students_processed` | int(11) | YES | - | `0` |  |
| 8 | `total_promoted` | int(11) | YES | - | `0` |  |
| 9 | `total_pending_approval` | int(11) | YES | - | `0` |  |
| 10 | `total_rejected` | int(11) | YES | - | `0` |  |
| 11 | `created_by` | int(10) unsigned | NO | MUL | NULL |  |
| 12 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 13 | `completed_at` | timestamp | YES | - | `NULL` |  |
| 14 | `notes` | text | YES | - | `NULL` |  |

FKs: `created_by` → `users`.`id`

---
## `promotion_rules`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `level_name` | varchar(50) | NO | - | NULL |  |
| 3 | `min_score_promote` | decimal(5,2) | YES | - | `40.00` |  |
| 4 | `min_score_review` | decimal(5,2) | YES | - | `30.00` |  |
| 5 | `attendance_min_pct` | decimal(5,2) | YES | - | `75.00` |  |
| 6 | `auto_promote` | tinyint(1) | YES | - | `1` |  |
| 7 | `require_approval` | tinyint(1) | YES | - | `0` |  |
| 8 | `notes` | text | YES | - | `NULL` |  |
| 9 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 10 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

---
## `purchase_orders`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `supplier_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `order_number` | varchar(50) | NO | UNI | NULL |  |
| 4 | `order_date` | date | NO | - | NULL |  |
| 5 | `expected_delivery_date` | date | YES | - | `NULL` |  |
| 6 | `total_amount` | decimal(10,2) | NO | - | NULL |  |
| 7 | `payment_terms` | text | YES | - | `NULL` |  |
| 8 | `status` | enum('draft','pending','approved','ordered','received','cancelled') | NO | MUL | `'draft'` |  |
| 9 | `remarks` | text | YES | - | `NULL` |  |
| 10 | `created_by` | int(10) unsigned | NO | MUL | NULL |  |
| 11 | `approved_by` | int(10) unsigned | YES | MUL | `NULL` |  |
| 12 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 13 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

FKs: `approved_by` → `staff`.`id`; `created_by` → `staff`.`id`; `supplier_id` → `suppliers`.`id`

UNIQUE/INDEX: `uk_order_number` (`order_number`)

---
## `rate_limit_logs`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | bigint(20) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `ip_address` | varchar(45) | NO | MUL | NULL |  |
| 3 | `request_time` | int(10) unsigned | NO | MUL | NULL |  |

---
## `record_permissions`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `user_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 3 | `role_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 4 | `table_name` | varchar(100) | NO | - | NULL |  |
| 5 | `record_id` | int(10) unsigned | NO | - | NULL |  |
| 6 | `permission_type` | varchar(50) | NO | - | NULL |  |
| 7 | `granted_date` | datetime | NO | - | NULL |  |
| 8 | `granted_by` | int(10) unsigned | YES | - | `NULL` |  |
| 9 | `expiry_date` | datetime | YES | - | `NULL` |  |
| 10 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |

FKs: `user_id` → `users`.`id`; `role_id` → `roles`.`id`

---
## `refresh_tokens`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `user_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `token` | varchar(500) | NO | UNI | NULL |  |
| 4 | `expires_at` | timestamp | NO | MUL | `current_timestamp()` | on update current_timestamp() |
| 5 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 6 | `revoked_at` | timestamp | YES | MUL | `NULL` |  |

UNIQUE/INDEX: `token` (`token`)

---
## `requisition_items`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `requisition_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `item_id` | int(10) unsigned | NO | MUL | NULL |  |
| 4 | `requested_quantity` | int(11) | NO | - | NULL |  |
| 5 | `unit` | varchar(20) | NO | - | NULL |  |
| 6 | `approved_quantity` | int(11) | YES | - | `NULL` |  |
| 7 | `fulfilled_quantity` | int(11) | YES | - | `0` |  |
| 8 | `unit_cost` | decimal(10,2) | YES | - | `NULL` |  |
| 9 | `notes` | text | YES | - | `NULL` |  |
| 10 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |

FKs: `item_id` → `inventory_items`.`id`; `requisition_id` → `inventory_requisitions`.`id`

UNIQUE/INDEX: `uk_requisition_item` (`requisition_id,item_id`)

---
## `role_dashboards`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `role_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `dashboard_id` | int(10) unsigned | NO | MUL | NULL |  |
| 4 | `is_primary` | tinyint(1) | NO | - | `0` |  |
| 5 | `display_order` | int(11) | NO | - | `0` |  |
| 6 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |

UNIQUE/INDEX: `unique_role_dashboard` (`role_id,dashboard_id`)

---
## `role_delegations`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `delegator_role_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `delegate_role_id` | int(10) unsigned | NO | - | NULL |  |
| 4 | `active` | tinyint(1) | NO | - | `1` |  |
| 5 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |

UNIQUE/INDEX: `ux_delegation_pair` (`delegator_role_id,delegate_role_id`)

---
## `role_permissions`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `role_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `permission_id` | int(11) | NO | MUL | NULL |  |
| 4 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |

FKs: `role_id` → `roles`.`id`; `permission_id` → `permissions`.`id`

UNIQUE/INDEX: `unique_role_permission` (`role_id,permission_id`)

---
## `role_routes`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `role_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `route_id` | int(10) unsigned | NO | MUL | NULL |  |
| 4 | `is_allowed` | tinyint(1) | NO | - | `1` |  |
| 5 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |

FKs: `route_id` → `routes`.`id`; `role_id` → `roles`.`id`

UNIQUE/INDEX: `uk_role_route` (`role_id,route_id`)

---
## `roles`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `name` | varchar(50) | NO | UNI | NULL |  |
| 3 | `description` | text | YES | - | `NULL` |  |
| 4 | `scope` | enum('system','school') | NO | - | `'school'` |  |
| 5 | `is_system` | tinyint(1) | NO | - | `0` |  |
| 6 | `is_active` | tinyint(1) | NO | - | `1` |  |
| 7 | `user_count` | int(11) | NO | - | `0` |  |
| 8 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 9 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

UNIQUE/INDEX: `name` (`name`); `name_2` (`name`); `uk_role_name` (`name`)

---
## `role_sidebar_menus`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `role_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `menu_item_id` | int(10) unsigned | NO | MUL | NULL |  |
| 4 | `is_default` | tinyint(1) | NO | - | `1` |  |
| 5 | `custom_order` | int(11) | YES | - | `NULL` |  |
| 6 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |

FKs: `role_id` → `roles`.`id`; `menu_item_id` → `sidebar_menu_items`.`id`

UNIQUE/INDEX: `uk_role_sidebar_menu` (`role_id,menu_item_id`); `uq_role_menu` (`role_id,menu_item_id`)

---
## `rooms`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `name` | varchar(50) | NO | - | NULL |  |
| 3 | `code` | varchar(20) | NO | UNI | NULL |  |
| 4 | `type` | enum('classroom','lab','office','other') | NO | MUL | NULL |  |
| 5 | `capacity` | int(11) | NO | - | NULL |  |
| 6 | `building` | varchar(50) | YES | - | `NULL` |  |
| 7 | `floor` | varchar(20) | YES | - | `NULL` |  |
| 8 | `status` | enum('active','inactive','maintenance') | NO | MUL | `'active'` |  |
| 9 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 10 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

UNIQUE/INDEX: `uk_code` (`code`)

---
## `route_permissions`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `route_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `permission_id` | int(11) | NO | MUL | NULL |  |
| 4 | `access_type` | enum('view','create','update','delete','approve','manage','all') | NO | - | `'view'` |  |
| 5 | `is_required` | tinyint(1) | NO | - | `1` |  |
| 6 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |

FKs: `permission_id` → `permissions`.`id`; `route_id` → `routes`.`id`

UNIQUE/INDEX: `uk_route_permission_access` (`route_id,permission_id,access_type`)

---
## `routes`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `name` | varchar(100) | NO | UNI | NULL |  |
| 3 | `url` | varchar(255) | NO | - | NULL |  |
| 4 | `domain` | enum('SYSTEM','SCHOOL') | NO | MUL | `'SCHOOL'` |  |
| 5 | `module` | varchar(100) | YES | MUL | `NULL` |  |
| 6 | `description` | varchar(500) | YES | - | `NULL` |  |
| 7 | `controller` | varchar(255) | YES | - | `NULL` |  |
| 8 | `action` | varchar(100) | YES | - | `NULL` |  |
| 9 | `is_active` | tinyint(1) | NO | MUL | `1` |  |
| 10 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 11 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

UNIQUE/INDEX: `name` (`name`)

---
## `route_schedules`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `route_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `day_of_week` | enum('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday') | NO | - | NULL |  |
| 4 | `direction` | enum('pickup','dropoff') | NO | - | NULL |  |
| 5 | `departure_time` | time | NO | - | NULL |  |
| 6 | `vehicle_id` | int(10) unsigned | YES | - | `NULL` |  |
| 7 | `driver_id` | int(10) unsigned | YES | - | `NULL` |  |
| 8 | `pickup_time` | time | YES | - | `NULL` |  |
| 9 | `dropoff_time` | time | YES | - | `NULL` |  |
| 10 | `notes` | text | YES | - | `NULL` |  |
| 11 | `status` | enum('active','inactive') | NO | - | `'active'` |  |
| 12 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |

FKs: `route_id` → `transport_routes`.`id`

---
## `route_stops`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `route_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `name` | varchar(100) | NO | - | NULL |  |
| 4 | `location` | varchar(255) | NO | - | NULL |  |
| 5 | `sequence` | int(11) | NO | - | NULL |  |
| 6 | `morning_time` | time | NO | - | NULL |  |
| 7 | `afternoon_time` | time | NO | - | NULL |  |
| 8 | `max_students` | int(11) | NO | - | `0` |  |
| 9 | `current_students` | int(11) | NO | - | `0` |  |
| 10 | `status` | enum('active','inactive') | NO | - | `'active'` |  |
| 11 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |

FKs: `route_id` → `transport_routes`.`id`

---
## `schedule_changes`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `schedule_type` | enum('class','exam') | NO | - | NULL |  |
| 3 | `schedule_id` | int(10) unsigned | NO | - | NULL |  |
| 4 | `change_type` | enum('reschedule','cancel','room_change','teacher_change') | NO | - | NULL |  |
| 5 | `old_value` | varchar(255) | YES | - | `NULL` |  |
| 6 | `new_value` | varchar(255) | YES | - | `NULL` |  |
| 7 | `changed_by` | int(10) unsigned | YES | MUL | `NULL` |  |
| 8 | `changed_at` | timestamp | NO | - | `current_timestamp()` |  |

FKs: `changed_by` → `users`.`id`

---
## `schedules`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `title` | varchar(255) | NO | - | NULL |  |
| 3 | `description` | text | YES | - | `NULL` |  |
| 4 | `start_time` | datetime | NO | - | NULL |  |
| 5 | `end_time` | datetime | NO | - | NULL |  |
| 6 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |

---
## `schema_discovery_cache`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `lookup_key` | varchar(64) | NO | PRI | NULL |  |
| 2 | `table_name` | varchar(255) | NO | MUL | NULL |  |
| 3 | `candidates` | text | NO | - | NULL |  |
| 4 | `columns_json` | text | NO | - | NULL |  |
| 5 | `updated_at` | datetime | NO | - | NULL |  |

---
## `schemes_of_work`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `teacher_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `class_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 4 | `subject_id` | int(10) unsigned | YES | - | `NULL` |  |
| 5 | `subject_name` | varchar(100) | YES | - | `NULL` |  |
| 6 | `learning_area_id` | int(10) unsigned | YES | - | `NULL` |  |
| 7 | `strand_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 8 | `sub_strand_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 9 | `coverage_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 10 | `academic_year_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 11 | `term_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 12 | `term_number` | tinyint(3) unsigned | YES | - | `NULL` |  |
| 13 | `title` | varchar(255) | NO | - | NULL |  |
| 14 | `description` | text | YES | - | `NULL` |  |
| 15 | `week_number` | tinyint(3) unsigned | YES | MUL | `NULL` |  |
| 16 | `strand` | varchar(255) | YES | - | `NULL` |  |
| 17 | `sub_strand` | varchar(255) | YES | - | `NULL` |  |
| 18 | `learning_outcomes` | text | YES | - | `NULL` |  |
| 19 | `key_vocabulary` | text | YES | - | `NULL` |  |
| 20 | `resources` | text | YES | - | `NULL` |  |
| 21 | `activities` | text | YES | - | `NULL` |  |
| 22 | `assessment_methods` | varchar(255) | YES | - | `NULL` |  |
| 23 | `status` | enum('draft','submitted','approved','rejected') | NO | MUL | `'draft'` |  |
| 24 | `approved_by` | int(10) unsigned | YES | - | `NULL` |  |
| 25 | `approved_at` | datetime | YES | - | `NULL` |  |
| 26 | `rejection_reason` | text | YES | - | `NULL` |  |
| 27 | `file_path` | varchar(500) | YES | - | `NULL` |  |
| 28 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 29 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

---
## `school_calendar`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `date` | date | NO | UNI | NULL |  |
| 3 | `day_type` | enum('school_day','weekend','public_holiday','school_holiday','half_day','exam_day','special_event') | NO | MUL | NULL |  |
| 4 | `title` | varchar(255) | NO | - | NULL |  |
| 5 | `description` | text | YES | - | `NULL` |  |
| 6 | `academic_year_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 7 | `term_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 8 | `affects_day_students` | tinyint(1) | YES | - | `1` |  |
| 9 | `affects_boarders` | tinyint(1) | YES | - | `1` |  |
| 10 | `requires_attendance` | tinyint(1) | YES | - | `1` |  |
| 11 | `created_by` | int(10) unsigned | YES | - | `NULL` |  |
| 12 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |

FKs: `academic_year_id` → `academic_years`.`id`

UNIQUE/INDEX: `unique_date` (`date`)

---
## `school_configuration`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `school_name` | varchar(255) | NO | - | NULL |  |
| 3 | `school_code` | varchar(50) | YES | - | `NULL` |  |
| 4 | `logo_url` | varchar(500) | YES | - | `NULL` |  |
| 5 | `favicon_url` | varchar(500) | YES | - | `NULL` |  |
| 6 | `motto` | varchar(500) | YES | - | `NULL` |  |
| 7 | `vision` | text | YES | - | `NULL` |  |
| 8 | `mission` | text | YES | - | `NULL` |  |
| 9 | `core_values` | text | YES | - | `NULL` |  |
| 10 | `about_us` | text | YES | - | `NULL` |  |
| 11 | `email` | varchar(255) | YES | - | `NULL` |  |
| 12 | `phone` | varchar(50) | YES | - | `NULL` |  |
| 13 | `alternative_phone` | varchar(50) | YES | - | `NULL` |  |
| 14 | `address` | text | YES | - | `NULL` |  |
| 15 | `city` | varchar(100) | YES | - | `NULL` |  |
| 16 | `state` | varchar(100) | YES | - | `NULL` |  |
| 17 | `country` | varchar(100) | YES | - | `NULL` |  |
| 18 | `postal_code` | varchar(20) | YES | - | `NULL` |  |
| 19 | `website` | varchar(255) | YES | - | `NULL` |  |
| 20 | `facebook_url` | varchar(255) | YES | - | `NULL` |  |
| 21 | `twitter_url` | varchar(255) | YES | - | `NULL` |  |
| 22 | `instagram_url` | varchar(255) | YES | - | `NULL` |  |
| 23 | `linkedin_url` | varchar(255) | YES | - | `NULL` |  |
| 24 | `youtube_url` | varchar(255) | YES | - | `NULL` |  |
| 25 | `established_year` | year(4) | YES | - | `NULL` |  |
| 26 | `principal_name` | varchar(255) | YES | - | `NULL` |  |
| 27 | `principal_message` | text | YES | - | `NULL` |  |
| 28 | `academic_calendar_url` | varchar(500) | YES | - | `NULL` |  |
| 29 | `prospectus_url` | varchar(500) | YES | - | `NULL` |  |
| 30 | `student_handbook_url` | varchar(500) | YES | - | `NULL` |  |
| 31 | `timezone` | varchar(50) | YES | - | `'Africa/Nairobi'` |  |
| 32 | `currency` | varchar(10) | YES | - | `'KES'` |  |
| 33 | `language` | varchar(10) | YES | - | `'en'` |  |
| 34 | `date_format` | varchar(20) | YES | - | `'Y-m-d'` |  |
| 35 | `time_format` | varchar(20) | YES | - | `'H:i:s'` |  |
| 36 | `is_active` | tinyint(1) | NO | MUL | `1` |  |
| 37 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 38 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |
| 39 | `created_by` | int(10) unsigned | YES | MUL | `NULL` |  |
| 40 | `updated_by` | int(10) unsigned | YES | MUL | `NULL` |  |

FKs: `updated_by` → `users`.`id`

---
## `school_content`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(11) | NO | PRI | NULL | auto_increment |
| 2 | `content_key` | varchar(100) | NO | UNI | NULL |  |
| 3 | `content_value` | longtext | YES | - | `NULL` |  |
| 4 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

UNIQUE/INDEX: `uk_key` (`content_key`)

---
## `school_events`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(11) | NO | PRI | NULL | auto_increment |
| 2 | `title` | varchar(255) | NO | - | NULL |  |
| 3 | `description` | text | YES | - | `NULL` |  |
| 4 | `event_date` | date | NO | MUL | NULL |  |
| 5 | `event_time` | time | YES | - | `NULL` |  |
| 6 | `end_date` | date | YES | - | `NULL` |  |
| 7 | `location` | varchar(200) | YES | - | `NULL` |  |
| 8 | `category` | varchar(50) | NO | - | `'Academic'` |  |
| 9 | `status` | enum('upcoming','ongoing','past','cancelled') | NO | - | `'upcoming'` |  |
| 10 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 11 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

---
## `school_facilities`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(11) | NO | PRI | NULL | auto_increment |
| 2 | `icon` | varchar(50) | NO | - | `'bi-building'` |  |
| 3 | `name` | varchar(150) | NO | - | NULL |  |
| 4 | `description` | text | YES | - | `NULL` |  |
| 5 | `display_order` | int(11) | NO | - | `0` |  |
| 6 | `is_active` | tinyint(1) | NO | - | `1` |  |

---
## `school_history`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(11) | NO | PRI | NULL | auto_increment |
| 2 | `year` | varchar(10) | NO | - | NULL |  |
| 3 | `event_title` | varchar(200) | NO | - | NULL |  |
| 4 | `description` | text | YES | - | `NULL` |  |
| 5 | `display_order` | int(11) | NO | MUL | `0` |  |

---
## `school_levels`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `name` | varchar(50) | NO | - | NULL |  |
| 3 | `code` | varchar(10) | NO | UNI | NULL |  |
| 4 | `description` | text | YES | - | `NULL` |  |
| 5 | `status` | enum('active','inactive') | NO | MUL | `'active'` |  |
| 6 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 7 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

UNIQUE/INDEX: `uk_code` (`code`)

---
## `school_programs`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(11) | NO | PRI | NULL | auto_increment |
| 2 | `name` | varchar(150) | NO | - | NULL |  |
| 3 | `level_range` | varchar(100) | YES | - | `NULL` |  |
| 4 | `icon` | varchar(50) | NO | - | `'bi-book'` |  |
| 5 | `color` | varchar(20) | NO | - | `'#198754'` |  |
| 6 | `description` | text | YES | - | `NULL` |  |
| 7 | `anchor` | varchar(50) | YES | - | `NULL` |  |
| 8 | `display_order` | int(11) | NO | - | `0` |  |
| 9 | `is_active` | tinyint(1) | NO | - | `1` |  |

---
## `school_settings`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(11) | NO | PRI | NULL | auto_increment |
| 2 | `setting_key` | varchar(100) | NO | UNI | NULL |  |
| 3 | `setting_value` | text | YES | - | `NULL` |  |
| 4 | `label` | varchar(200) | YES | - | `NULL` |  |
| 5 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

UNIQUE/INDEX: `uk_key` (`setting_key`)

---
## `school_transactions`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `student_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 3 | `financial_period_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 4 | `source` | enum('bank','mpesa','cash','other') | NO | - | NULL |  |
| 5 | `reference` | varchar(100) | YES | - | `NULL` |  |
| 6 | `amount` | decimal(10,2) | NO | - | NULL |  |
| 7 | `transaction_date` | datetime | NO | MUL | NULL |  |
| 8 | `status` | enum('pending','confirmed','failed') | NO | - | `'pending'` |  |
| 9 | `details` | longtext | YES | - | `NULL` |  |
| 10 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |

FKs: `financial_period_id` → `financial_periods`.`id`; `student_id` → `students`.`id`

---
## `school_values`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(11) | NO | PRI | NULL | auto_increment |
| 2 | `name` | varchar(80) | NO | - | NULL |  |
| 3 | `description` | text | YES | - | `NULL` |  |
| 4 | `icon` | varchar(50) | NO | - | `'bi-heart-fill'` |  |
| 5 | `color` | varchar(20) | NO | - | `'#198754'` |  |
| 6 | `display_order` | int(11) | NO | - | `0` |  |
| 7 | `is_active` | tinyint(1) | NO | - | `1` |  |

---
## `school_week_config`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `academic_year_id` | int(10) unsigned | NO | UNI | NULL |  |
| 3 | `saturday_classes` | tinyint(1) | YES | - | `0` |  |
| 4 | `sunday_boarding` | tinyint(1) | YES | - | `1` |  |
| 5 | `class_days` | longtext | YES | - | `'["Monday","Tuesday","Wednesday","Thursday","Friday"]'` |  |
| 6 | `boarding_days` | longtext | YES | - | `'["Monday","Tuesday","Wednesday","Thursday","Friday","Saturday","Sunday"]'` |  |
| 7 | `notes` | text | YES | - | `NULL` |  |
| 8 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |

UNIQUE/INDEX: `uq_year` (`academic_year_id`)

---
## `sick_bay_visits`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `student_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `visit_date` | date | NO | MUL | NULL |  |
| 4 | `visit_time` | time | NO | - | NULL |  |
| 5 | `complaint` | varchar(255) | NO | - | NULL |  |
| 6 | `symptoms` | text | YES | - | `NULL` |  |
| 7 | `diagnosis` | text | YES | - | `NULL` |  |
| 8 | `treatment_given` | text | YES | - | `NULL` |  |
| 9 | `temperature` | decimal(4,1) | YES | - | `NULL` |  |
| 10 | `weight_kg` | decimal(5,2) | YES | - | `NULL` |  |
| 11 | `medication_given` | text | YES | - | `NULL` |  |
| 12 | `referred_to_hospital` | tinyint(1) | NO | - | `0` |  |
| 13 | `referral_hospital` | varchar(150) | YES | - | `NULL` |  |
| 14 | `parent_notified` | tinyint(1) | NO | - | `0` |  |
| 15 | `parent_notified_at` | datetime | YES | - | `NULL` |  |
| 16 | `dismissed_at` | datetime | YES | - | `NULL` |  |
| 17 | `attended_by` | int(10) unsigned | YES | - | `NULL` |  |
| 18 | `status` | enum('active','dismissed','referred') | NO | MUL | `'active'` |  |
| 19 | `notes` | text | YES | - | `NULL` |  |
| 20 | `created_at` | datetime | NO | - | `current_timestamp()` |  |
| 21 | `updated_at` | datetime | NO | - | `current_timestamp()` | on update current_timestamp() |

---
## `sidebar_menu_items`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `name` | varchar(100) | NO | UNI | NULL |  |
| 3 | `label` | varchar(100) | NO | - | NULL |  |
| 4 | `icon` | varchar(100) | YES | - | `NULL` |  |
| 5 | `url` | varchar(255) | YES | - | `NULL` |  |
| 6 | `route_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 7 | `parent_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 8 | `menu_type` | enum('sidebar','topbar','dropdown') | NO | - | `'sidebar'` |  |
| 9 | `display_order` | int(11) | NO | - | `0` |  |
| 10 | `domain` | enum('SYSTEM','SCHOOL','SHARED') | NO | MUL | `'SCHOOL'` |  |
| 11 | `is_active` | tinyint(1) | NO | - | `1` |  |
| 12 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 13 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

FKs: `route_id` → `routes`.`id`; `parent_id` → `sidebar_menu_items`.`id`

UNIQUE/INDEX: `uk_sidebar_menu_name` (`name`)

---
## `sms_communications`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `parent_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 3 | `student_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 4 | `recipient_phone` | varchar(20) | NO | MUL | NULL |  |
| 5 | `message_body` | text | NO | - | NULL |  |
| 6 | `template_id` | int(10) unsigned | YES | - | `NULL` |  |
| 7 | `sms_type` | enum('academic','fees','attendance','event','emergency','general','report_card') | NO | MUL | `'general'` |  |
| 8 | `status` | enum('pending','queued','sent','delivered','failed') | NO | MUL | `'pending'` |  |
| 9 | `sent_by` | int(10) unsigned | NO | MUL | NULL |  |
| 10 | `sent_at` | datetime | YES | - | `NULL` |  |
| 11 | `delivered_at` | datetime | YES | - | `NULL` |  |
| 12 | `failure_reason` | text | YES | - | `NULL` |  |
| 13 | `external_reference_id` | varchar(255) | YES | - | `NULL` |  |
| 14 | `character_count` | int(11) | YES | - | `NULL` |  |
| 15 | `sms_parts` | int(11) | YES | - | `1` |  |
| 16 | `cost` | decimal(10,2) | YES | - | `NULL` |  |
| 17 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 18 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

FKs: `sent_by` → `staff`.`id`; `student_id` → `students`.`id`; `parent_id` → `parents`.`id`

---
## `staff`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `staff_type_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 3 | `staff_category_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 4 | `staff_no` | varchar(20) | NO | UNI | NULL |  |
| 5 | `first_name` | varchar(50) | NO | MUL | NULL |  |
| 6 | `last_name` | varchar(50) | NO | - | NULL |  |
| 7 | `phone` | varchar(30) | YES | - | `NULL` |  |
| 8 | `department_id` | int(10) unsigned | NO | MUL | NULL |  |
| 9 | `supervisor_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 10 | `user_id` | int(10) unsigned | NO | MUL | NULL |  |
| 11 | `position` | varchar(100) | NO | - | NULL |  |
| 12 | `employment_date` | date | NO | - | NULL |  |
| 13 | `contract_type` | enum('permanent','contract','temporary') | NO | - | `'permanent'` |  |
| 14 | `nssf_no` | varchar(30) | YES | - | `NULL` |  |
| 15 | `kra_pin` | varchar(30) | YES | - | `NULL` |  |
| 16 | `nhif_no` | varchar(30) | YES | - | `NULL` |  |
| 17 | `bank_name` | varchar(100) | YES | - | `NULL` |  |
| 18 | `bank_account` | varchar(50) | YES | - | `NULL` |  |
| 19 | `salary` | decimal(12,2) | YES | - | `NULL` |  |
| 20 | `gender` | enum('male','female','other') | YES | - | `NULL` |  |
| 21 | `marital_status` | enum('single','married','divorced','widowed') | YES | - | `NULL` |  |
| 22 | `tsc_no` | varchar(30) | YES | - | `NULL` |  |
| 23 | `address` | varchar(255) | YES | - | `NULL` |  |
| 24 | `profile_pic_url` | varchar(255) | YES | - | `NULL` |  |
| 25 | `documents_folder` | varchar(255) | YES | - | `NULL` |  |
| 26 | `status` | enum('active','inactive','on_leave') | NO | MUL | `'active'` |  |
| 27 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 28 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |
| 29 | `date_of_birth` | date | YES | - | `NULL` |  |
| 30 | `work_start_time` | time | YES | - | `'08:00:00'` |  |
| 31 | `work_end_time` | time | YES | - | `'17:00:00'` |  |
| 32 | `late_threshold_minutes` | int(11) | YES | - | `15` |  |

UNIQUE/INDEX: `uk_staff_no` (`staff_no`)

---
## `staff_allowances`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `staff_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `name` | varchar(100) | YES | - | `NULL` |  |
| 4 | `description` | text | YES | - | `NULL` |  |
| 5 | `allowance_type` | enum('housing','transport','medical','hardship','responsibility','overtime','bonus','other') | NO | MUL | `'other'` |  |
| 6 | `amount` | decimal(10,2) | NO | - | NULL |  |
| 7 | `is_taxable` | tinyint(1) | NO | - | `1` |  |
| 8 | `is_recurring` | tinyint(1) | NO | - | `1` |  |
| 9 | `effective_date` | date | NO | - | NULL |  |
| 10 | `start_date` | date | YES | - | `NULL` |  |
| 11 | `end_date` | date | YES | - | `NULL` |  |
| 12 | `status` | enum('active','paused','completed','cancelled') | NO | MUL | `'active'` |  |
| 13 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 14 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

---
## `staff_appointment_approvals`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `appointment_type` | enum('internal','new') | NO | MUL | NULL |  |
| 3 | `appointment_id` | int(10) unsigned | NO | - | NULL |  |
| 4 | `action` | enum('submitted','approved','rejected','onboarded','reverted') | NO | MUL | NULL |  |
| 5 | `actor_id` | int(10) unsigned | NO | MUL | NULL |  |
| 6 | `remarks` | text | YES | - | `NULL` |  |
| 7 | `previous_status` | varchar(30) | YES | - | `NULL` |  |
| 8 | `new_status` | varchar(30) | YES | - | `NULL` |  |
| 9 | `changes_json` | longtext | YES | - | `NULL` |  |
| 10 | `created_at` | timestamp | NO | MUL | `current_timestamp()` |  |

FKs: `actor_id` → `staff`.`id`

---
## `staff_appointments`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `recruitment_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 3 | `candidate_first_name` | varchar(50) | NO | - | NULL |  |
| 4 | `candidate_last_name` | varchar(50) | NO | - | NULL |  |
| 5 | `candidate_email` | varchar(100) | NO | MUL | NULL |  |
| 6 | `candidate_phone` | varchar(30) | YES | - | `NULL` |  |
| 7 | `candidate_id_number` | varchar(30) | YES | - | `NULL` |  |
| 8 | `candidate_qualifications` | text | YES | - | `NULL` |  |
| 9 | `candidate_experience` | text | YES | - | `NULL` |  |
| 10 | `candidate_notes` | text | YES | - | `NULL` |  |
| 11 | `department_id` | int(10) unsigned | NO | MUL | NULL |  |
| 12 | `position` | varchar(100) | NO | - | NULL |  |
| 13 | `employment_date` | date | NO | MUL | NULL |  |
| 14 | `contract_type` | enum('permanent','contract','temporary') | NO | - | `'permanent'` |  |
| 15 | `salary` | decimal(12,2) | YES | - | `NULL` |  |
| 16 | `supervisor_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 17 | `staff_type_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 18 | `staff_category_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 19 | `status` | enum('draft','submitted','approved','rejected','onboarded') | NO | MUL | `'draft'` |  |
| 20 | `submitted_by` | int(10) unsigned | YES | MUL | `NULL` |  |
| 21 | `submitted_at` | datetime | YES | - | `NULL` |  |
| 22 | `approved_by` | int(10) unsigned | YES | MUL | `NULL` |  |
| 23 | `approved_at` | datetime | YES | - | `NULL` |  |
| 24 | `rejection_reason` | text | YES | - | `NULL` |  |
| 25 | `onboarded_by` | int(10) unsigned | YES | MUL | `NULL` |  |
| 26 | `onboarded_at` | datetime | YES | - | `NULL` |  |
| 27 | `created_user_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 28 | `created_staff_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 29 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 30 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

FKs: `department_id` → `departments`.`id`; `supervisor_id` → `staff`.`id`; `created_user_id` → `users`.`id`; `submitted_by` → `staff`.`id`; `created_staff_id` → `staff`.`id`; `staff_type_id` → `staff_types`.`id`; `approved_by` → `staff`.`id`; `staff_category_id` → `staff_categories`.`id`; `onboarded_by` → `staff`.`id`

---
## `staff_attendance`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `staff_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `date` | date | NO | - | NULL |  |
| 4 | `academic_year_id` | int(10) unsigned | YES | - | `NULL` |  |
| 5 | `shift` | enum('morning','afternoon','evening','night','full_day') | NO | - | `'full_day'` |  |
| 6 | `status` | enum('present','absent','late') | NO | - | NULL |  |
| 7 | `check_in_time` | time | YES | - | `NULL` |  |
| 8 | `expected_check_in` | time | YES | - | `NULL` |  |
| 9 | `check_out_time` | time | YES | - | `NULL` |  |
| 10 | `duty_type_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 11 | `leave_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 12 | `absence_reason` | enum('leave','sick','off_day','unauthorized','other') | YES | - | `NULL` |  |
| 13 | `notes` | text | YES | - | `NULL` |  |
| 14 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 15 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |
| 16 | `marked_by` | int(10) unsigned | YES | MUL | `NULL` |  |

UNIQUE/INDEX: `uq_staff_date_shift` (`staff_id,date,shift`)

---
## `staff_attendance_profiles`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | bigint(20) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `staff_id` | int(10) unsigned | NO | UNI | NULL |  |
| 3 | `work_start_time` | time | NO | - | `'08:00:00'` |  |
| 4 | `work_end_time` | time | NO | - | `'17:00:00'` |  |
| 5 | `late_threshold_minutes` | int(11) | NO | - | `15` |  |
| 6 | `is_active` | tinyint(1) | NO | - | `1` |  |
| 7 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 8 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

UNIQUE/INDEX: `staff_id` (`staff_id`)

---
## `staff_categories`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `staff_type_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `category_name` | varchar(100) | NO | - | NULL |  |
| 4 | `description` | text | YES | - | `NULL` |  |
| 5 | `kpi_applicable` | tinyint(1) | NO | - | `1` |  |
| 6 | `is_active` | tinyint(1) | NO | - | `1` |  |
| 7 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 8 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

---
## `staff_child_fee_config`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `config_key` | varchar(100) | NO | UNI | NULL |  |
| 3 | `config_value` | text | NO | - | NULL |  |
| 4 | `description` | text | YES | - | `NULL` |  |
| 5 | `is_active` | tinyint(1) | NO | - | `1` |  |
| 6 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 7 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

UNIQUE/INDEX: `uk_config_key` (`config_key`)

---
## `staff_child_fee_deductions`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `staff_child_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `staff_id` | int(10) unsigned | NO | MUL | NULL |  |
| 4 | `student_id` | int(10) unsigned | NO | MUL | NULL |  |
| 5 | `payslip_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 6 | `payroll_month` | int(11) | NO | MUL | NULL |  |
| 7 | `payroll_year` | int(11) | NO | - | NULL |  |
| 8 | `term_id` | int(10) unsigned | YES | - | `NULL` |  |
| 9 | `fee_invoice_id` | int(10) unsigned | YES | - | `NULL` |  |
| 10 | `gross_fee_amount` | decimal(12,2) | NO | - | NULL |  |
| 11 | `staff_discount_percentage` | decimal(5,2) | NO | - | `0.00` |  |
| 12 | `staff_discount_amount` | decimal(12,2) | NO | - | `0.00` |  |
| 13 | `sponsor_waiver_amount` | decimal(12,2) | NO | - | `0.00` |  |
| 14 | `deductible_amount` | decimal(12,2) | NO | - | NULL |  |
| 15 | `deducted_amount` | decimal(12,2) | NO | - | `0.00` |  |
| 16 | `balance` | decimal(12,2) | NO | - | `0.00` |  |
| 17 | `status` | enum('pending','partial','deducted','waived','cancelled') | NO | MUL | `'pending'` |  |
| 18 | `notes` | text | YES | - | `NULL` |  |
| 19 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 20 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

UNIQUE/INDEX: `uk_child_period` (`staff_child_id,payroll_month,payroll_year`)

---
## `staff_children`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `staff_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `student_id` | int(10) unsigned | NO | MUL | NULL |  |
| 4 | `relationship` | enum('father','mother','guardian','spouse') | NO | - | `'father'` |  |
| 5 | `fee_deduction_enabled` | tinyint(1) | NO | MUL | `1` |  |
| 6 | `fee_deduction_percentage` | decimal(5,2) | NO | - | `100.00` |  |
| 7 | `notes` | text | YES | - | `NULL` |  |
| 8 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 9 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

UNIQUE/INDEX: `uk_staff_student` (`staff_id,student_id`)

---
## `staff_class_assignments`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `staff_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `class_stream_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 4 | `class_id` | int(10) unsigned | NO | MUL | NULL |  |
| 5 | `stream_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 6 | `academic_year_id` | int(10) unsigned | NO | MUL | NULL |  |
| 7 | `role` | enum('class_teacher','subject_teacher','assistant_teacher','head_of_department') | NO | MUL | `'subject_teacher'` |  |
| 8 | `subject_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 9 | `learning_area_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 10 | `start_date` | date | NO | - | NULL |  |
| 11 | `end_date` | date | YES | - | `NULL` |  |
| 12 | `status` | enum('active','completed','transferred','terminated') | NO | MUL | `'active'` |  |
| 13 | `notes` | text | YES | - | `NULL` |  |
| 14 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 15 | `created_by` | int(10) unsigned | YES | MUL | `NULL` |  |
| 16 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |
| 17 | `periods_per_week` | int(11) | NO | - | `0` |  |

UNIQUE/INDEX: `uk_staff_class_year` (`staff_id,class_id,stream_id,academic_year_id,role,subject_id`)

---
## `staff_communication_profiles`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | bigint(20) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `staff_id` | int(10) unsigned | NO | UNI | NULL |  |
| 3 | `primary_email` | varchar(100) | YES | - | `NULL` |  |
| 4 | `primary_phone` | varchar(30) | YES | - | `NULL` |  |
| 5 | `emergency_contact_name` | varchar(100) | YES | - | `NULL` |  |
| 6 | `emergency_contact_phone` | varchar(30) | YES | - | `NULL` |  |
| 7 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 8 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

UNIQUE/INDEX: `staff_id` (`staff_id`)

---
## `staff_contracts`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `staff_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `contract_type` | enum('permanent','temporary','contract','internship','probation') | NO | - | NULL |  |
| 4 | `start_date` | date | NO | MUL | NULL |  |
| 5 | `end_date` | date | YES | - | `NULL` |  |
| 6 | `salary` | decimal(12,2) | NO | - | NULL |  |
| 7 | `allowances` | decimal(12,2) | YES | - | `0.00` |  |
| 8 | `terms` | text | YES | - | `NULL` |  |
| 9 | `contract_document_url` | varchar(255) | YES | - | `NULL` |  |
| 10 | `status` | enum('active','completed','terminated','renewed') | YES | - | `'active'` |  |
| 11 | `termination_reason` | text | YES | - | `NULL` |  |
| 12 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 13 | `created_by` | int(10) unsigned | YES | MUL | `NULL` |  |

---
## `staff_deductions`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `staff_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `deduction_type_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 4 | `name` | varchar(100) | YES | - | `NULL` |  |
| 5 | `description` | text | YES | - | `NULL` |  |
| 6 | `amount` | decimal(10,2) | NO | - | NULL |  |
| 7 | `is_recurring` | tinyint(1) | NO | - | `1` |  |
| 8 | `effective_date` | date | NO | - | NULL |  |
| 9 | `start_date` | date | YES | - | `NULL` |  |
| 10 | `end_date` | date | YES | - | `NULL` |  |
| 11 | `status` | enum('active','paused','completed','cancelled') | NO | MUL | `'active'` |  |
| 12 | `related_student_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 13 | `reference_no` | varchar(100) | YES | - | `NULL` |  |
| 14 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 15 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

---
## `staff_domain_audit`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | bigint(20) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `user_id` | bigint(20) unsigned | YES | - | `NULL` |  |
| 3 | `staff_id` | bigint(20) unsigned | YES | MUL | `NULL` |  |
| 4 | `action` | varchar(120) | NO | MUL | NULL |  |
| 5 | `entity_type` | varchar(80) | NO | - | NULL |  |
| 6 | `entity_id` | varchar(80) | YES | - | `NULL` |  |
| 7 | `details` | longtext | YES | - | `NULL` |  |
| 8 | `ip_address` | varchar(45) | YES | - | `NULL` |  |
| 9 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |

---
## `staff_duty_roster`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `staff_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `date` | date | NO | MUL | NULL |  |
| 4 | `duty_type_id` | int(10) unsigned | NO | MUL | NULL |  |
| 5 | `shift` | enum('morning','afternoon','evening','night','full_day') | YES | - | `'full_day'` |  |
| 6 | `start_time` | time | YES | - | `NULL` |  |
| 7 | `end_time` | time | YES | - | `NULL` |  |
| 8 | `location` | varchar(255) | YES | - | `NULL` |  |
| 9 | `notes` | text | YES | - | `NULL` |  |
| 10 | `is_swap` | tinyint(1) | YES | - | `0` |  |
| 11 | `swapped_with_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 12 | `assigned_by` | int(10) unsigned | YES | MUL | `NULL` |  |
| 13 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |

---
## `staff_duty_types`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `code` | varchar(30) | NO | UNI | NULL |  |
| 3 | `name` | varchar(100) | NO | - | NULL |  |
| 4 | `description` | text | YES | - | `NULL` |  |
| 5 | `color` | varchar(20) | YES | - | `'#007bff'` |  |
| 6 | `status` | enum('active','inactive') | YES | - | `'active'` |  |
| 7 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |

UNIQUE/INDEX: `code` (`code`)

---
## `staff_employment_profiles`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | bigint(20) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `staff_id` | int(10) unsigned | NO | UNI | NULL |  |
| 3 | `department_id` | int(10) unsigned | NO | MUL | NULL |  |
| 4 | `position` | varchar(100) | NO | - | NULL |  |
| 5 | `employment_date` | date | NO | - | NULL |  |
| 6 | `contract_type` | enum('permanent','contract','temporary') | NO | - | NULL |  |
| 7 | `status` | enum('active','inactive','on_leave','terminated') | NO | - | `'active'` |  |
| 8 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 9 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

UNIQUE/INDEX: `staff_id` (`staff_id`)

---
## `staff_experience`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `staff_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `organization` | varchar(255) | NO | - | NULL |  |
| 4 | `position` | varchar(255) | NO | - | NULL |  |
| 5 | `start_date` | date | NO | MUL | NULL |  |
| 6 | `end_date` | date | YES | - | `NULL` |  |
| 7 | `responsibilities` | text | YES | - | `NULL` |  |
| 8 | `document_url` | varchar(255) | YES | - | `NULL` |  |
| 9 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 10 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

---
## `staff_id_cards`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | bigint(20) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `staff_id` | bigint(20) unsigned | NO | MUL | NULL |  |
| 3 | `card_number` | varchar(64) | NO | UNI | NULL |  |
| 4 | `generated_by` | bigint(20) unsigned | YES | - | `NULL` |  |
| 5 | `generated_at` | datetime | NO | - | `current_timestamp()` |  |
| 6 | `issued_by` | bigint(20) unsigned | YES | - | `NULL` |  |
| 7 | `issued_at` | datetime | YES | - | `NULL` |  |
| 8 | `expires_at` | date | YES | - | `NULL` |  |
| 9 | `status` | enum('generated','issued','revoked','expired') | NO | - | `'generated'` |  |
| 10 | `metadata` | longtext | YES | - | `NULL` |  |
| 11 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 12 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

UNIQUE/INDEX: `uq_staff_id_cards_number` (`card_number`)

---
## `staff_import_batches`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | bigint(20) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `source_filename` | varchar(255) | NO | - | NULL |  |
| 3 | `stored_path` | varchar(500) | NO | - | NULL |  |
| 4 | `total_rows` | int(11) | NO | - | `0` |  |
| 5 | `valid_rows` | int(11) | NO | - | `0` |  |
| 6 | `invalid_rows` | int(11) | NO | - | `0` |  |
| 7 | `imported_rows` | int(11) | NO | - | `0` |  |
| 8 | `status` | enum('validating','validated','validation_failed','processing','completed','failed','rolled_back','cancelled') | NO | MUL | `'validating'` |  |
| 9 | `validation_summary` | longtext | YES | - | `NULL` |  |
| 10 | `failure_message` | text | YES | - | `NULL` |  |
| 11 | `imported_by` | int(10) unsigned | NO | MUL | NULL |  |
| 12 | `started_at` | datetime | YES | - | `NULL` |  |
| 13 | `completed_at` | datetime | YES | - | `NULL` |  |
| 14 | `rolled_back_at` | datetime | YES | - | `NULL` |  |
| 15 | `rolled_back_by` | int(10) unsigned | YES | - | `NULL` |  |
| 16 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 17 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

---
## `staff_import_rows`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | bigint(20) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `batch_id` | bigint(20) unsigned | NO | MUL | NULL |  |
| 3 | `row_number` | int(11) | NO | - | NULL |  |
| 4 | `row_data` | longtext | NO | - | NULL |  |
| 5 | `validation_errors` | longtext | YES | - | `NULL` |  |
| 6 | `status` | enum('valid','invalid','created','failed','rolled_back') | NO | - | NULL |  |
| 7 | `staff_id` | int(10) unsigned | YES | - | `NULL` |  |
| 8 | `user_id` | int(10) unsigned | YES | - | `NULL` |  |
| 9 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 10 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

FKs: `batch_id` → `staff_import_batches`.`id`

UNIQUE/INDEX: `uk_staff_import_row` (`batch_id,row_number`)

---
## `staff_incident_reports`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | bigint(20) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `reference_no` | varchar(32) | NO | UNI | NULL |  |
| 3 | `staff_id` | int(10) unsigned | NO | MUL | NULL |  |
| 4 | `department_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 5 | `category` | enum('workplace_accident','property_damage','safety_hazard','security_concern','harassment','maintenance','student_welfare','transport','kitchen','other') | NO | - | NULL |  |
| 6 | `occurred_at` | datetime | NO | - | NULL |  |
| 7 | `location` | varchar(255) | NO | - | NULL |  |
| 8 | `description` | text | NO | - | NULL |  |
| 9 | `immediate_action` | text | YES | - | `NULL` |  |
| 10 | `severity` | enum('low','medium','high','critical') | NO | - | `'medium'` |  |
| 11 | `status` | enum('reported','under_review','assigned','resolved','closed','dismissed') | NO | MUL | `'reported'` |  |
| 12 | `assigned_to` | int(10) unsigned | YES | MUL | `NULL` |  |
| 13 | `resolution` | text | YES | - | `NULL` |  |
| 14 | `resolved_by` | int(10) unsigned | YES | MUL | `NULL` |  |
| 15 | `resolved_at` | datetime | YES | - | `NULL` |  |
| 16 | `created_by` | int(10) unsigned | NO | MUL | NULL |  |
| 17 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 18 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

FKs: `department_id` → `departments`.`id`; `created_by` → `users`.`id`; `assigned_to` → `staff`.`id`; `staff_id` → `staff`.`id`; `resolved_by` → `users`.`id`

UNIQUE/INDEX: `uk_staff_incident_reference` (`reference_no`)

---
## `staff_kpi_templates`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `staff_category_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `kpi_name` | varchar(255) | NO | - | NULL |  |
| 4 | `description` | text | YES | - | `NULL` |  |
| 5 | `measurement_criteria` | text | YES | - | `NULL` |  |
| 6 | `target_value` | decimal(10,2) | YES | - | `NULL` |  |
| 7 | `weight_percentage` | decimal(5,2) | YES | - | `NULL` |  |
| 8 | `evaluation_period` | enum('term','semester','annual') | YES | - | `'term'` |  |
| 9 | `is_active` | tinyint(1) | YES | - | `1` |  |
| 10 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |

---
## `staff_leaves`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `staff_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `leave_type_id` | int(10) unsigned | NO | MUL | NULL |  |
| 4 | `leave_type` | varchar(50) | YES | - | `NULL` |  |
| 5 | `start_date` | date | NO | MUL | NULL |  |
| 6 | `end_date` | date | NO | - | NULL |  |
| 7 | `days_requested` | int(11) | NO | - | NULL |  |
| 8 | `reason` | text | NO | - | NULL |  |
| 9 | `relief_staff_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 10 | `status` | enum('pending','approved','rejected','cancelled') | YES | MUL | `'pending'` |  |
| 11 | `approved_by` | int(10) unsigned | YES | MUL | `NULL` |  |
| 12 | `approved_at` | datetime | YES | - | `NULL` |  |
| 13 | `rejection_reason` | text | YES | - | `NULL` |  |
| 14 | `attachments_folder` | varchar(255) | YES | - | `NULL` |  |
| 15 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 16 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

---
## `staff_lifecycle_actions`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | bigint(20) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `staff_id` | bigint(20) unsigned | NO | MUL | NULL |  |
| 3 | `action_type` | enum('promotion','demotion','transfer','acting_appointment','confirmation','contract_renewal','salary_change','suspension','reinstatement','resignation','retirement','termination') | NO | - | NULL |  |
| 4 | `status` | enum('draft','pending','approved','rejected','effective','cancelled') | NO | MUL | `'pending'` |  |
| 5 | `effective_date` | date | NO | MUL | NULL |  |
| 6 | `reason` | varchar(500) | NO | - | NULL |  |
| 7 | `from_position` | varchar(150) | YES | - | `NULL` |  |
| 8 | `to_position` | varchar(150) | YES | - | `NULL` |  |
| 9 | `from_department_id` | bigint(20) unsigned | YES | - | `NULL` |  |
| 10 | `to_department_id` | bigint(20) unsigned | YES | - | `NULL` |  |
| 11 | `from_salary` | decimal(15,2) | YES | - | `NULL` |  |
| 12 | `to_salary` | decimal(15,2) | YES | - | `NULL` |  |
| 13 | `from_contract_type` | varchar(80) | YES | - | `NULL` |  |
| 14 | `to_contract_type` | varchar(80) | YES | - | `NULL` |  |
| 15 | `from_supervisor_id` | bigint(20) unsigned | YES | - | `NULL` |  |
| 16 | `to_supervisor_id` | bigint(20) unsigned | YES | - | `NULL` |  |
| 17 | `notes` | text | YES | - | `NULL` |  |
| 18 | `review_comment` | text | YES | - | `NULL` |  |
| 19 | `created_by` | bigint(20) unsigned | NO | - | NULL |  |
| 20 | `approved_by` | bigint(20) unsigned | YES | - | `NULL` |  |
| 21 | `approved_at` | datetime | YES | - | `NULL` |  |
| 22 | `applied_at` | datetime | YES | - | `NULL` |  |
| 23 | `created_at` | datetime | NO | - | `current_timestamp()` |  |
| 24 | `updated_at` | datetime | YES | - | `NULL` | on update current_timestamp() |

---
## `staff_loans`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `staff_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `loan_type` | varchar(50) | NO | - | NULL |  |
| 4 | `principal_amount` | decimal(12,2) | NO | - | NULL |  |
| 5 | `loan_date` | date | NO | - | NULL |  |
| 6 | `agreed_monthly_deduction` | decimal(10,2) | NO | - | NULL |  |
| 7 | `balance_remaining` | decimal(12,2) | NO | - | NULL |  |
| 8 | `status` | enum('active','paid_off','defaulted','suspended') | NO | MUL | `'active'` |  |
| 9 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 10 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

---
## `staff_offboarding`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `staff_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `offboarding_type` | enum('retirement','resignation','dismissal','contract_end','death','abscondment') | NO | MUL | NULL |  |
| 4 | `last_working_day` | date | NO | - | NULL |  |
| 5 | `exit_interview_date` | date | YES | - | `NULL` |  |
| 6 | `exit_interview_notes` | text | YES | - | `NULL` |  |
| 7 | `asset_return_complete` | tinyint(1) | NO | - | `0` |  |
| 8 | `clearance_form_complete` | tinyint(1) | NO | - | `0` |  |
| 9 | `handover_report_complete` | tinyint(1) | NO | - | `0` |  |
| 10 | `final_pay_calculated` | tinyint(1) | NO | - | `0` |  |
| 11 | `outstanding_leave_days` | decimal(5,1) | YES | - | `NULL` |  |
| 12 | `outstanding_salary` | decimal(12,2) | YES | - | `NULL` |  |
| 13 | `leave_pay_amount` | decimal(12,2) | YES | - | `NULL` |  |
| 14 | `final_settlement_amount` | decimal(12,2) | YES | - | `NULL` |  |
| 15 | `nssf_clearance` | tinyint(1) | NO | - | `0` |  |
| 16 | `paye_clearance` | tinyint(1) | NO | - | `0` |  |
| 17 | `documents_url` | varchar(255) | YES | - | `NULL` |  |
| 18 | `notify_hr` | tinyint(1) | NO | - | `1` |  |
| 19 | `notify_finance` | tinyint(1) | NO | - | `1` |  |
| 20 | `notify_it` | tinyint(1) | NO | - | `0` |  |
| 21 | `status` | enum('initiated','pending_clearance','pending_settlement','completed','cancelled') | NO | MUL | `'initiated'` |  |
| 22 | `processed_by` | int(10) unsigned | YES | MUL | `NULL` |  |
| 23 | `processed_at` | datetime | YES | - | `NULL` |  |
| 24 | `created_by` | int(10) unsigned | NO | - | NULL |  |
| 25 | `created_at` | datetime | NO | - | `current_timestamp()` |  |
| 26 | `updated_at` | datetime | NO | - | `current_timestamp()` | on update current_timestamp() |

FKs: `staff_id` → `staff`.`id`; `processed_by` → `staff`.`id`

---
## `staff_off_day_patterns`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `staff_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `day_of_week` | enum('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday') | NO | - | NULL |  |
| 4 | `is_off` | tinyint(1) | YES | - | `0` |  |
| 5 | `effective_from` | date | NO | - | NULL |  |
| 6 | `effective_to` | date | YES | - | `NULL` |  |
| 7 | `reason` | varchar(255) | YES | - | `NULL` |  |
| 8 | `created_by` | int(10) unsigned | YES | - | `NULL` |  |
| 9 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |

UNIQUE/INDEX: `unique_pattern` (`staff_id,day_of_week,effective_from`)

---
## `staff_onboarding`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `staff_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `mentor_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 4 | `contract_type` | enum('permanent','temporary','contract','internship','probation') | YES | - | `'probation'` |  |
| 5 | `probation_months` | int(11) | YES | - | `3` |  |
| 6 | `probation_outcome` | enum('pending','confirmed','extended','terminated') | YES | - | `'pending'` |  |
| 7 | `initiated_by` | int(10) unsigned | YES | - | `NULL` |  |
| 8 | `notes` | text | YES | - | `NULL` |  |
| 9 | `start_date` | date | NO | MUL | NULL |  |
| 10 | `target_completion` | date | NO | - | NULL |  |
| 11 | `expected_end_date` | date | YES | - | `NULL` |  |
| 12 | `actual_completion` | date | YES | - | `NULL` |  |
| 13 | `completion_date` | date | YES | - | `NULL` |  |
| 14 | `status` | enum('pending','in_progress','completed','extended','terminated') | YES | - | `'pending'` |  |
| 15 | `progress_percent` | int(11) | YES | - | `0` |  |
| 16 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 17 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

UNIQUE/INDEX: `uk_active_onboarding` (`staff_id,status`)

---
## `staff_onboarding_progress`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | bigint(20) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `staff_id` | int(10) unsigned | NO | UNI | NULL |  |
| 3 | `user_id` | int(10) unsigned | NO | UNI | NULL |  |
| 4 | `password_completed` | tinyint(1) | NO | - | `0` |  |
| 5 | `profile_completed` | tinyint(1) | NO | - | `0` |  |
| 6 | `communication_completed` | tinyint(1) | NO | - | `0` |  |
| 7 | `onboarding_status` | enum('invited','password_changed','profile_pending','completed','cancelled') | NO | - | `'invited'` |  |
| 8 | `completed_at` | datetime | YES | - | `NULL` |  |
| 9 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 10 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

UNIQUE/INDEX: `staff_id` (`staff_id`); `user_id` (`user_id`)

---
## `staff_payroll`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `staff_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `payroll_month` | int(11) | NO | - | NULL |  |
| 4 | `payroll_year` | int(11) | NO | - | NULL |  |
| 5 | `basic_salary` | decimal(10,2) | NO | - | NULL |  |
| 6 | `gross_salary` | decimal(10,2) | YES | - | `NULL` |  |
| 7 | `nssf_deduction` | decimal(10,2) | YES | - | `NULL` |  |
| 8 | `nhif_deduction` | decimal(10,2) | YES | - | `NULL` |  |
| 9 | `paye_tax` | decimal(10,2) | YES | - | `NULL` |  |
| 10 | `other_deductions` | decimal(10,2) | YES | - | `NULL` |  |
| 11 | `total_deductions` | decimal(10,2) | YES | - | `NULL` |  |
| 12 | `allowances` | decimal(10,2) | NO | - | NULL |  |
| 13 | `deductions` | decimal(10,2) | NO | - | NULL |  |
| 14 | `net_salary` | decimal(10,2) | NO | - | NULL |  |
| 15 | `status` | varchar(50) | NO | MUL | NULL |  |
| 16 | `approved_at` | datetime | YES | - | `NULL` |  |
| 17 | `approved_by` | int(11) | YES | - | `NULL` |  |
| 18 | `payment_date` | datetime | YES | - | `NULL` |  |
| 19 | `payment_mode` | enum('bank','cash','mpesa','airtel_money') | YES | - | `NULL` |  |
| 20 | `payment_reference` | varchar(100) | YES | - | `NULL` |  |
| 21 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 22 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |
| 23 | `payroll_period` | varchar(7) | NO | MUL | NULL |  |

---
## `staff_payroll_adjustments`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `staff_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `source_type` | enum('internal_appointment') | NO | - | `'internal_appointment'` |  |
| 4 | `source_id` | int(10) unsigned | NO | - | NULL |  |
| 5 | `previous_salary` | decimal(12,2) | YES | - | `NULL` |  |
| 6 | `new_salary` | decimal(12,2) | NO | - | NULL |  |
| 7 | `effective_date` | date | NO | MUL | NULL |  |
| 8 | `reason` | text | YES | - | `NULL` |  |
| 9 | `created_by` | int(10) unsigned | NO | MUL | NULL |  |
| 10 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |

FKs: `staff_id` → `staff`.`id`; `created_by` → `staff`.`id`

---
## `staff_payroll_profiles`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | bigint(20) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `staff_id` | int(10) unsigned | NO | UNI | NULL |  |
| 3 | `basic_salary` | decimal(12,2) | NO | - | `0.00` |  |
| 4 | `bank_name` | varchar(100) | YES | - | `NULL` |  |
| 5 | `bank_account` | varchar(50) | YES | - | `NULL` |  |
| 6 | `kra_pin` | varchar(30) | YES | - | `NULL` |  |
| 7 | `nssf_no` | varchar(30) | YES | - | `NULL` |  |
| 8 | `nhif_no` | varchar(30) | YES | - | `NULL` |  |
| 9 | `status` | enum('draft','active','inactive') | NO | - | `'draft'` |  |
| 10 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 11 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

UNIQUE/INDEX: `staff_id` (`staff_id`)

---
## `staff_performance_reviews`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `staff_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `academic_year_id` | int(10) unsigned | NO | MUL | NULL |  |
| 4 | `term_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 5 | `review_period` | varchar(50) | YES | - | `NULL` |  |
| 6 | `review_type` | enum('probation','annual','mid_year','special') | YES | - | `'annual'` |  |
| 7 | `reviewer_id` | int(10) unsigned | NO | MUL | NULL |  |
| 8 | `review_date` | date | NO | - | NULL |  |
| 9 | `overall_score` | decimal(5,2) | YES | - | `NULL` |  |
| 10 | `performance_grade` | char(1) | YES | - | `NULL` |  |
| 11 | `overall_rating` | enum('exceeding','meeting','approaching','below') | YES | - | `NULL` |  |
| 12 | `strengths` | text | YES | - | `NULL` |  |
| 13 | `areas_for_improvement` | text | YES | - | `NULL` |  |
| 14 | `recommendations` | text | YES | - | `NULL` |  |
| 15 | `action_plan` | text | YES | - | `NULL` |  |
| 16 | `follow_up_date` | date | YES | - | `NULL` |  |
| 17 | `status` | enum('draft','submitted','approved','completed') | YES | MUL | `'draft'` |  |
| 18 | `completion_date` | datetime | YES | - | `NULL` |  |
| 19 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 20 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

---
## `staff_probation_reviews`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `onboarding_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `staff_id` | int(10) unsigned | NO | MUL | NULL |  |
| 4 | `review_month` | tinyint(4) | NO | - | NULL |  |
| 5 | `review_date` | date | NO | - | NULL |  |
| 6 | `reviewer_id` | int(10) unsigned | YES | - | `NULL` |  |
| 7 | `overall_rating` | enum('unsatisfactory','needs_improvement','satisfactory','good','excellent') | YES | - | `NULL` |  |
| 8 | `attendance_score` | decimal(5,2) | YES | - | `NULL` |  |
| 9 | `performance_score` | decimal(5,2) | YES | - | `NULL` |  |
| 10 | `conduct_score` | decimal(5,2) | YES | - | `NULL` |  |
| 11 | `strengths` | text | YES | - | `NULL` |  |
| 12 | `areas_to_improve` | text | YES | - | `NULL` |  |
| 13 | `outcome` | enum('continue','extend_probation','confirm_permanent','terminate') | NO | - | NULL |  |
| 14 | `outcome_notes` | text | YES | - | `NULL` |  |
| 15 | `next_review_date` | date | YES | - | `NULL` |  |
| 16 | `staff_signature` | tinyint(1) | YES | - | `0` |  |
| 17 | `reviewer_signature` | tinyint(1) | YES | - | `0` |  |
| 18 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |

FKs: `staff_id` → `staff`.`id`; `onboarding_id` → `staff_onboarding`.`id`

---
## `staff_promotions`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `staff_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `promotion_type` | enum('acting','substantive','demotion','transfer','reclassification') | NO | - | `'substantive'` |  |
| 4 | `is_temporary` | tinyint(1) | NO | MUL | `0` |  |
| 5 | `reverts_to_promotion_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 6 | `payroll_adjustment_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 7 | `from_contract_type` | enum('permanent','contract','temporary') | YES | - | `NULL` |  |
| 8 | `to_contract_type` | enum('permanent','contract','temporary') | YES | - | `NULL` |  |
| 9 | `from_supervisor_id` | int(10) unsigned | YES | - | `NULL` |  |
| 10 | `to_supervisor_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 11 | `from_position` | varchar(100) | NO | - | NULL |  |
| 12 | `to_position` | varchar(100) | NO | - | NULL |  |
| 13 | `from_department_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 14 | `to_department_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 15 | `from_salary` | decimal(12,2) | YES | - | `NULL` |  |
| 16 | `to_salary` | decimal(12,2) | YES | - | `NULL` |  |
| 17 | `effective_date` | date | NO | MUL | NULL |  |
| 18 | `status` | enum('pending','approved','rejected','effective','cancelled') | NO | MUL | `'pending'` |  |
| 19 | `reason` | text | YES | - | `NULL` |  |
| 20 | `letter_url` | varchar(255) | YES | - | `NULL` |  |
| 21 | `approved_by` | int(10) unsigned | YES | MUL | `NULL` |  |
| 22 | `approved_at` | datetime | YES | - | `NULL` |  |
| 23 | `rejected_reason` | text | YES | - | `NULL` |  |
| 24 | `created_by` | int(10) unsigned | NO | - | NULL |  |
| 25 | `submitted_by` | int(10) unsigned | YES | - | `NULL` |  |
| 26 | `submitted_at` | datetime | YES | - | `NULL` |  |
| 27 | `created_at` | datetime | NO | - | `current_timestamp()` |  |
| 28 | `updated_at` | datetime | NO | - | `current_timestamp()` | on update current_timestamp() |

FKs: `staff_id` → `staff`.`id`; `reverts_to_promotion_id` → `staff_promotions`.`id`; `from_department_id` → `departments`.`id`; `approved_by` → `staff`.`id`; `to_department_id` → `departments`.`id`

---
## `staff_qualifications`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `staff_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `qualification_type` | enum('degree','diploma','certificate','other') | NO | - | NULL |  |
| 4 | `title` | varchar(255) | NO | - | NULL |  |
| 5 | `institution` | varchar(255) | NO | - | NULL |  |
| 6 | `year_obtained` | year(4) | NO | MUL | NULL |  |
| 7 | `description` | text | YES | - | `NULL` |  |
| 8 | `document_url` | varchar(255) | YES | - | `NULL` |  |
| 9 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 10 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

---
## `staff_salary_advances`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `advance_number` | varchar(30) | NO | UNI | NULL |  |
| 3 | `staff_id` | int(10) unsigned | NO | MUL | NULL |  |
| 4 | `requested_amount` | decimal(12,2) | NO | - | NULL |  |
| 5 | `approved_amount` | decimal(12,2) | YES | - | `NULL` |  |
| 6 | `request_date` | date | NO | - | NULL |  |
| 7 | `reason` | text | YES | - | `NULL` |  |
| 8 | `deduction_schedule` | enum('single_month','two_months','three_months') | YES | - | `'single_month'` |  |
| 9 | `deduction_start_month` | date | YES | - | `NULL` |  |
| 10 | `amount_per_deduction` | decimal(12,2) | YES | - | `NULL` |  |
| 11 | `amount_deducted` | decimal(12,2) | YES | - | `0.00` |  |
| 12 | `balance_remaining` | decimal(12,2) | YES | - | `0.00` |  |
| 13 | `status` | enum('pending','approved','rejected','active','fully_deducted','cancelled') | YES | MUL | `'pending'` |  |
| 14 | `approved_by` | int(10) unsigned | YES | - | `NULL` |  |
| 15 | `approval_date` | datetime | YES | - | `NULL` |  |
| 16 | `rejection_reason` | text | YES | - | `NULL` |  |
| 17 | `notes` | text | YES | - | `NULL` |  |
| 18 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 19 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

UNIQUE/INDEX: `advance_number` (`advance_number`)

---
## `staff_shift_assignments`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `staff_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `shift` | enum('morning','afternoon','evening','night','full_day') | NO | - | `'full_day'` |  |
| 4 | `day_of_week` | enum('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday') | NO | - | NULL |  |
| 5 | `start_time` | time | NO | - | NULL |  |
| 6 | `end_time` | time | NO | - | NULL |  |
| 7 | `effective_from` | date | NO | - | NULL |  |
| 8 | `effective_to` | date | YES | - | `NULL` |  |
| 9 | `academic_year_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 10 | `assigned_by` | int(10) unsigned | YES | - | `NULL` |  |
| 11 | `notes` | text | YES | - | `NULL` |  |
| 12 | `status` | enum('active','inactive') | YES | - | `'active'` |  |
| 13 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |

---
## `staff_types`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `name` | varchar(50) | NO | UNI | NULL |  |
| 3 | `description` | text | YES | - | `NULL` |  |
| 4 | `is_active` | tinyint(1) | NO | - | `1` |  |
| 5 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 6 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

UNIQUE/INDEX: `uk_name` (`name`)

---
## `storage_locations`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `name` | varchar(100) | NO | - | NULL |  |
| 3 | `code` | varchar(20) | NO | UNI | NULL |  |
| 4 | `description` | text | YES | - | `NULL` |  |
| 5 | `capacity` | int(11) | YES | - | `NULL` |  |
| 6 | `parent_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 7 | `status` | enum('active','inactive') | NO | MUL | `'active'` |  |
| 8 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 9 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

UNIQUE/INDEX: `uk_code` (`code`)

---
## `strand_competency`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `strand_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `competency_id` | int(10) unsigned | NO | MUL | NULL |  |
| 4 | `weight` | decimal(5,2) | NO | - | `1.00` |  |
| 5 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |

UNIQUE/INDEX: `uk_strand_comp` (`strand_id,competency_id`)

---
## `strands`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `learning_area_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `grade_level` | varchar(20) | NO | MUL | `''` |  |
| 4 | `code` | varchar(20) | NO | - | NULL |  |
| 5 | `name` | varchar(150) | NO | - | NULL |  |
| 6 | `variant` | varchar(20) | YES | - | `NULL` |  |
| 7 | `source_subject` | varchar(100) | YES | - | `NULL` |  |
| 8 | `source_document` | varchar(255) | YES | - | `NULL` |  |
| 9 | `source_page` | varchar(20) | YES | - | `NULL` |  |
| 10 | `is_optional` | tinyint(1) | NO | - | `0` |  |
| 11 | `description` | text | YES | - | `NULL` |  |
| 12 | `level_range` | varchar(50) | YES | - | `NULL` |  |
| 13 | `sort_order` | smallint(5) unsigned | NO | - | `1` |  |
| 14 | `status` | enum('active','inactive') | NO | - | `'active'` |  |
| 15 | `created_at` | datetime | NO | - | `current_timestamp()` |  |

UNIQUE/INDEX: `uq_strand_grade` (`learning_area_id,grade_level,name,variant`)

---
## `student_activities`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `student_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `activity_id` | int(10) unsigned | NO | MUL | NULL |  |
| 4 | `joined_at` | timestamp | NO | - | `current_timestamp()` |  |

UNIQUE/INDEX: `uk_student_activity` (`student_id,activity_id`)

---
## `student_arrears`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `student_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `academic_year` | year(4) | NO | - | NULL |  |
| 4 | `term_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 5 | `total_arrears` | decimal(10,2) | NO | - | NULL |  |
| 6 | `arrears_date` | date | NO | - | NULL |  |
| 7 | `last_payment_date` | date | YES | - | `NULL` |  |
| 8 | `arrears_status` | enum('current','overdue','settled','written_off') | NO | MUL | `'current'` |  |
| 9 | `days_overdue` | int(11) | YES | MUL | `0` |  |
| 10 | `settlement_plan_id` | int(10) unsigned | YES | - | `NULL` |  |
| 11 | `notes` | text | YES | - | `NULL` |  |
| 12 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 13 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

---
## `student_attendance`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `student_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `date` | date | NO | MUL | NULL |  |
| 4 | `status` | enum('present','absent','late') | NO | - | NULL |  |
| 5 | `check_in_time` | time | YES | - | `NULL` |  |
| 6 | `check_out_time` | time | YES | - | `NULL` |  |
| 7 | `absence_reason` | enum('unexcused','sick','permission','suspension','other') | YES | - | `'unexcused'` |  |
| 8 | `permission_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 9 | `notes` | text | YES | - | `NULL` |  |
| 10 | `class_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 11 | `term_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 12 | `academic_year_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 13 | `register_type` | enum('class','boarding','activity') | NO | MUL | `'class'` |  |
| 14 | `session_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 15 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 16 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |
| 17 | `marked_by` | int(10) unsigned | YES | MUL | `NULL` |  |

UNIQUE/INDEX: `uq_attendance_mark` (`student_id,date,session_id,register_type`)

---
## `student_boarding_notes`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `student_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `note_type` | enum('dormitory','welfare','discipline','health','safety','general') | NO | MUL | `'general'` |  |
| 4 | `note` | text | NO | - | NULL |  |
| 5 | `visibility` | enum('private','staff','boarding','all') | NO | MUL | `'boarding'` |  |
| 6 | `priority` | enum('low','medium','high','urgent') | NO | MUL | `'medium'` |  |
| 7 | `resolved` | tinyint(1) | YES | MUL | `0` |  |
| 8 | `resolved_at` | datetime | YES | - | `NULL` |  |
| 9 | `resolved_by` | int(10) unsigned | YES | - | `NULL` |  |
| 10 | `created_by` | int(10) unsigned | NO | - | NULL |  |
| 11 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 12 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

FKs: `student_id` → `students`.`id`

---
## `student_clearances`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `student_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `transfer_request_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 4 | `clearance_type` | enum('finance','library','uniform','property','academic') | NO | - | NULL |  |
| 5 | `status` | enum('pending','cleared','blocked') | YES | - | `'pending'` |  |
| 6 | `checked_by` | int(10) unsigned | YES | - | `NULL` |  |
| 7 | `checked_at` | datetime | YES | - | `NULL` |  |
| 8 | `amount_outstanding` | decimal(12,2) | YES | - | `0.00` |  |
| 9 | `notes` | text | YES | - | `NULL` |  |
| 10 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 11 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

UNIQUE/INDEX: `uq_request_type` (`transfer_request_id,clearance_type`)

---
## `student_core_values`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `student_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `term_id` | int(10) unsigned | NO | MUL | NULL |  |
| 4 | `value_id` | int(10) unsigned | NO | - | NULL |  |
| 5 | `rating` | enum('consistently','sometimes','rarely') | NO | - | `'sometimes'` |  |
| 6 | `evidence` | text | YES | - | `NULL` |  |
| 7 | `assessed_by` | int(10) unsigned | YES | - | `NULL` |  |
| 8 | `created_at` | datetime | NO | - | `current_timestamp()` |  |
| 9 | `updated_at` | datetime | NO | - | `current_timestamp()` | on update current_timestamp() |

UNIQUE/INDEX: `uq_student_value` (`student_id,term_id,value_id`)

---
## `student_counseling_cases`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `student_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `case_code` | varchar(20) | NO | UNI | NULL |  |
| 4 | `title` | varchar(255) | NO | - | NULL |  |
| 5 | `case_type` | enum('academic','behavioral','personal','family','career','disciplinary','other') | NO | - | NULL |  |
| 6 | `referral_source` | varchar(100) | YES | - | `NULL` |  |
| 7 | `priority` | enum('low','medium','high','urgent') | NO | MUL | `'medium'` |  |
| 8 | `status` | enum('open','in_progress','resolved','closed','cancelled') | NO | MUL | `'open'` |  |
| 9 | `description` | text | YES | - | `NULL` |  |
| 10 | `assigned_to` | int(10) unsigned | YES | MUL | `NULL` |  |
| 11 | `opened_by` | int(10) unsigned | YES | - | `NULL` |  |
| 12 | `opened_at` | timestamp | YES | - | `NULL` |  |
| 13 | `next_follow_up_at` | timestamp | YES | - | `NULL` |  |
| 14 | `closed_at` | timestamp | YES | - | `NULL` |  |
| 15 | `closed_by` | int(10) unsigned | YES | - | `NULL` |  |
| 16 | `closure_notes` | text | YES | - | `NULL` |  |
| 17 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 18 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

FKs: `student_id` → `students`.`id`

UNIQUE/INDEX: `uk_case_code` (`case_code`)

---
## `student_counseling_sessions`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `case_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `session_date` | timestamp | YES | MUL | `NULL` |  |
| 4 | `session_type` | varchar(50) | YES | - | `NULL` |  |
| 5 | `summary` | text | YES | - | `NULL` |  |
| 6 | `confidential_notes` | text | YES | - | `NULL` |  |
| 7 | `action_plan` | text | YES | - | `NULL` |  |
| 8 | `follow_up_date` | timestamp | YES | - | `NULL` |  |
| 9 | `recorded_by` | int(10) unsigned | YES | - | `NULL` |  |
| 10 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 11 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

FKs: `case_id` → `student_counseling_cases`.`id`

---
## `student_discipline`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `student_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `incident_date` | date | NO | - | NULL |  |
| 4 | `description` | text | YES | - | `NULL` |  |
| 5 | `severity` | enum('low','medium','high') | NO | - | NULL |  |
| 6 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 7 | `action_taken` | text | YES | - | `NULL` |  |
| 8 | `resolved_by` | int(10) unsigned | YES | MUL | `NULL` |  |
| 9 | `resolution_date` | date | YES | - | `NULL` |  |
| 10 | `status` | enum('pending','resolved','escalated') | NO | - | `'pending'` |  |

---
## `student_fee_balances`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `student_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `fee_structure_id` | int(10) unsigned | NO | MUL | NULL |  |
| 4 | `academic_term_id` | int(10) unsigned | NO | MUL | NULL |  |
| 5 | `balance` | decimal(10,2) | NO | - | `0.00` |  |
| 6 | `last_updated` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

---
## `student_fee_carryover`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `student_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `academic_year` | int(11) | NO | MUL | NULL |  |
| 4 | `term_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 5 | `previous_balance` | decimal(12,2) | YES | - | `0.00` |  |
| 6 | `surplus_amount` | decimal(12,2) | YES | - | `0.00` |  |
| 7 | `action_taken` | enum('fresh_bill','add_to_current','deduct_from_current','manual_adjustment') | YES | - | `'fresh_bill'` |  |
| 8 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 9 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |
| 10 | `notes` | text | YES | - | `NULL` |  |

UNIQUE/INDEX: `uk_student_year_term` (`student_id,academic_year,term_id`)

---
## `student_fee_obligations`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `student_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `academic_year` | year(4) | NO | - | NULL |  |
| 4 | `term_id` | int(10) unsigned | NO | - | NULL |  |
| 5 | `fee_structure_detail_id` | int(10) unsigned | NO | - | NULL |  |
| 6 | `amount_due` | decimal(10,2) | NO | - | NULL |  |
| 7 | `amount_paid` | decimal(10,2) | NO | - | `0.00` |  |
| 8 | `amount_waived` | decimal(10,2) | NO | - | `0.00` |  |
| 9 | `balance` | decimal(10,2) | YES | MUL | `NULL` | STORED GENERATED |
| 10 | `status` | enum('pending','partial','paid','arrears') | NO | MUL | `'pending'` |  |
| 11 | `due_date` | date | YES | MUL | `NULL` |  |
| 12 | `year_balance` | decimal(12,2) | YES | MUL | `0.00` |  |
| 13 | `term_balance` | decimal(12,2) | YES | MUL | `0.00` |  |
| 14 | `previous_year_balance` | decimal(12,2) | YES | - | `0.00` |  |
| 15 | `previous_term_balance` | decimal(12,2) | YES | - | `0.00` |  |
| 16 | `is_sponsored` | tinyint(1) | YES | MUL | `0` |  |
| 17 | `sponsored_waiver_amount` | decimal(12,2) | YES | - | `0.00` |  |
| 18 | `payment_status` | enum('pending','partial','paid','arrears','waived') | NO | MUL | `'pending'` |  |
| 19 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 20 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

UNIQUE/INDEX: `uk_student_obligation` (`student_id,academic_year,term_id,fee_structure_detail_id`)

---
## `student_fee_obligations_backup_20260112`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | - | `0` |  |
| 2 | `student_id` | int(10) unsigned | NO | - | NULL |  |
| 3 | `academic_year` | year(4) | NO | - | NULL |  |
| 4 | `term_id` | int(10) unsigned | NO | - | NULL |  |
| 5 | `fee_structure_detail_id` | int(10) unsigned | NO | - | NULL |  |
| 6 | `amount_due` | decimal(10,2) | NO | - | NULL |  |
| 7 | `amount_paid` | decimal(10,2) | NO | - | `0.00` |  |
| 8 | `amount_waived` | decimal(10,2) | NO | - | `0.00` |  |
| 9 | `balance` | decimal(10,2) | YES | - | `NULL` |  |
| 10 | `status` | enum('pending','partial','paid','arrears') | NO | - | `'pending'` |  |
| 11 | `due_date` | date | YES | - | `NULL` |  |
| 12 | `year_balance` | decimal(12,2) | YES | - | `0.00` |  |
| 13 | `term_balance` | decimal(12,2) | YES | - | `0.00` |  |
| 14 | `previous_year_balance` | decimal(12,2) | YES | - | `0.00` |  |
| 15 | `previous_term_balance` | decimal(12,2) | YES | - | `0.00` |  |
| 16 | `is_sponsored` | tinyint(1) | YES | - | `0` |  |
| 17 | `sponsored_waiver_amount` | decimal(12,2) | YES | - | `0.00` |  |
| 18 | `payment_status` | enum('pending','partial','paid','arrears','waived') | NO | - | `'pending'` |  |
| 19 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 20 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

---
## `student_health_records`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `student_id` | int(10) unsigned | NO | UNI | NULL |  |
| 3 | `record_code` | varchar(20) | YES | - | `NULL` |  |
| 4 | `blood_group` | enum('A+','A-','B+','B-','AB+','AB-','O+','O-','Unknown') | YES | - | `'Unknown'` |  |
| 5 | `health_category` | enum('general','allergy','condition','medication','injury','incident','other') | YES | - | `NULL` |  |
| 6 | `alert_type` | varchar(100) | YES | - | `NULL` |  |
| 7 | `condition_name` | varchar(255) | YES | - | `NULL` |  |
| 8 | `allergy_name` | varchar(255) | YES | - | `NULL` |  |
| 9 | `medication_name` | varchar(255) | YES | - | `NULL` |  |
| 10 | `severity` | enum('low','medium','high','critical') | YES | - | `'medium'` |  |
| 11 | `status` | enum('active','inactive','resolved','monitoring') | YES | MUL | `'active'` |  |
| 12 | `description` | text | YES | - | `NULL` |  |
| 13 | `emergency_flag` | tinyint(1) | YES | MUL | `0` |  |
| 14 | `action_instructions` | text | YES | - | `NULL` |  |
| 15 | `sensitive_notes` | text | YES | - | `NULL` |  |
| 16 | `next_review_date` | date | YES | - | `NULL` |  |
| 17 | `allergies` | text | YES | - | `NULL` |  |
| 18 | `chronic_conditions` | text | YES | - | `NULL` |  |
| 19 | `disability_notes` | text | YES | - | `NULL` |  |
| 20 | `special_diet` | text | YES | - | `NULL` |  |
| 21 | `emergency_contact_name` | varchar(150) | YES | - | `NULL` |  |
| 22 | `emergency_contact_phone` | varchar(30) | YES | - | `NULL` |  |
| 23 | `medical_aid_provider` | varchar(100) | YES | - | `NULL` |  |
| 24 | `medical_aid_number` | varchar(50) | YES | - | `NULL` |  |
| 25 | `doctor_name` | varchar(100) | YES | - | `NULL` |  |
| 26 | `doctor_phone` | varchar(30) | YES | - | `NULL` |  |
| 27 | `notes` | text | YES | - | `NULL` |  |
| 28 | `created_by` | int(10) unsigned | YES | - | `NULL` |  |
| 29 | `updated_by` | int(10) unsigned | YES | - | `NULL` |  |
| 30 | `created_at` | datetime | NO | - | `current_timestamp()` |  |
| 31 | `updated_at` | datetime | NO | - | `current_timestamp()` | on update current_timestamp() |

UNIQUE/INDEX: `uq_student_health` (`student_id`)

---
## `student_health_reviews`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `health_record_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `review_date` | timestamp | YES | MUL | `NULL` |  |
| 4 | `review_notes` | text | YES | - | `NULL` |  |
| 5 | `next_review_date` | timestamp | YES | - | `NULL` |  |
| 6 | `reviewed_by` | int(10) unsigned | YES | - | `NULL` |  |
| 7 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 8 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

FKs: `health_record_id` → `student_health_records`.`id`

---
## `student_health_visits`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `health_record_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 3 | `student_id` | int(10) unsigned | NO | MUL | NULL |  |
| 4 | `visit_date` | timestamp | YES | MUL | `NULL` |  |
| 5 | `complaint` | text | YES | - | `NULL` |  |
| 6 | `observation` | text | YES | - | `NULL` |  |
| 7 | `action_taken` | text | YES | - | `NULL` |  |
| 8 | `medication_given` | text | YES | - | `NULL` |  |
| 9 | `referred_to_hospital` | tinyint(1) | YES | - | `0` |  |
| 10 | `recorded_by` | int(10) unsigned | YES | - | `NULL` |  |
| 11 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 12 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

FKs: `student_id` → `students`.`id`

---
## `student_id_card_history`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `card_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `student_id` | int(10) unsigned | NO | MUL | NULL |  |
| 4 | `action` | enum('generated','qr_generated','printed','issued','renewed','replaced','marked_lost','revoked') | NO | MUL | NULL |  |
| 5 | `from_status` | enum('not_generated','generated','printed','issued','lost','damaged','expired','replaced','revoked') | YES | - | `NULL` |  |
| 6 | `to_status` | enum('not_generated','generated','printed','issued','lost','damaged','expired','replaced','revoked') | YES | - | `NULL` |  |
| 7 | `remarks` | text | YES | - | `NULL` |  |
| 8 | `performed_by` | int(10) unsigned | YES | - | `NULL` |  |
| 9 | `performed_at` | timestamp | NO | - | `current_timestamp()` |  |
| 10 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |

FKs: `student_id` → `students`.`id`; `card_id` → `student_id_cards`.`id`

---
## `student_id_cards`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `student_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `card_number` | varchar(20) | NO | UNI | NULL |  |
| 4 | `qr_token` | varchar(100) | YES | UNI | `NULL` |  |
| 5 | `qr_payload` | text | YES | - | `NULL` |  |
| 6 | `qr_code_path` | varchar(255) | YES | - | `NULL` |  |
| 7 | `academic_year_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 8 | `expiry_year` | int(4) | YES | - | `NULL` |  |
| 9 | `status` | enum('not_generated','generated','printed','issued','lost','replaced') | NO | MUL | `'not_generated'` |  |
| 10 | `issue_date` | date | YES | - | `NULL` |  |
| 11 | `generated_at` | timestamp | YES | - | `NULL` |  |
| 12 | `printed_at` | timestamp | YES | - | `NULL` |  |
| 13 | `issued_at` | timestamp | YES | - | `NULL` |  |
| 14 | `lost_at` | timestamp | YES | - | `NULL` |  |
| 15 | `replaced_at` | timestamp | YES | - | `NULL` |  |
| 16 | `revoked_at` | timestamp | YES | - | `NULL` |  |
| 17 | `revoked_by` | int(10) unsigned | YES | - | `NULL` |  |
| 18 | `revoked_reason` | text | YES | - | `NULL` |  |
| 19 | `replaced_from_card_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 20 | `replacement_reason` | enum('lost','damaged','expired','correction','other') | YES | - | `NULL` |  |
| 21 | `generated_by` | int(10) unsigned | YES | - | `NULL` |  |
| 22 | `issued_by` | int(10) unsigned | YES | - | `NULL` |  |
| 23 | `notes` | text | YES | - | `NULL` |  |
| 24 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 25 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

FKs: `student_id` → `students`.`id`; `replaced_from_card_id` → `student_id_cards`.`id`

UNIQUE/INDEX: `qr_token` (`qr_token`); `uk_card_number` (`card_number`)

---
## `student_meal_profiles`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `student_id` | int(10) unsigned | NO | UNI | NULL |  |
| 3 | `diet_type` | enum('normal','vegetarian','diabetic','allergy','medical','religious','other') | NO | MUL | `'normal'` |  |
| 4 | `food_restrictions` | text | YES | - | `NULL` |  |
| 5 | `allergy_notes` | text | YES | - | `NULL` |  |
| 6 | `medical_food_notes` | text | YES | - | `NULL` |  |
| 7 | `religious_food_notes` | text | YES | - | `NULL` |  |
| 8 | `active` | tinyint(1) | YES | MUL | `1` |  |
| 9 | `created_by` | int(10) unsigned | YES | - | `NULL` |  |
| 10 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 11 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

FKs: `student_id` → `students`.`id`

UNIQUE/INDEX: `uk_student_meal_profile` (`student_id`)

---
## `student_parents`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `student_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `parent_id` | int(10) unsigned | NO | MUL | NULL |  |
| 4 | `relationship` | enum('father','mother','guardian','step_father','step_mother','grandparent','uncle','aunt','sibling','other') | NO | - | `'guardian'` |  |
| 5 | `is_primary_contact` | tinyint(1) | NO | - | `0` |  |
| 6 | `is_emergency_contact` | tinyint(1) | NO | - | `0` |  |
| 7 | `financial_responsibility` | decimal(5,2) | YES | - | `100.00` |  |
| 8 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 9 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

UNIQUE/INDEX: `uk_student_parent` (`student_id,parent_id`)

---
## `student_payment_history_summary`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `student_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `academic_year` | year(4) | NO | MUL | NULL |  |
| 4 | `term_id` | int(10) unsigned | NO | - | NULL |  |
| 5 | `total_fees_due` | decimal(10,2) | YES | - | `0.00` |  |
| 6 | `total_paid` | decimal(10,2) | YES | - | `0.00` |  |
| 7 | `payment_count` | int(11) | YES | - | `0` |  |
| 8 | `balance` | decimal(10,2) | YES | MUL | `0.00` |  |
| 9 | `cash_payments` | decimal(10,2) | YES | - | `0.00` |  |
| 10 | `mpesa_payments` | decimal(10,2) | YES | - | `0.00` |  |
| 11 | `bank_transfers` | decimal(10,2) | YES | - | `0.00` |  |
| 12 | `last_payment_date` | datetime | YES | - | `NULL` |  |
| 13 | `last_updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

UNIQUE/INDEX: `unique_student_year_term` (`student_id,academic_year,term_id`)

---
## `student_permissions`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `student_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `permission_type_id` | int(10) unsigned | NO | MUL | NULL |  |
| 4 | `start_date` | date | NO | MUL | NULL |  |
| 5 | `start_time` | time | YES | - | `NULL` |  |
| 6 | `end_date` | date | NO | - | NULL |  |
| 7 | `end_time` | time | YES | - | `NULL` |  |
| 8 | `reason` | text | NO | - | NULL |  |
| 9 | `supporting_document` | varchar(255) | YES | - | `NULL` |  |
| 10 | `requested_by_parent` | tinyint(1) | YES | - | `0` |  |
| 11 | `parent_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 12 | `requested_at` | timestamp | NO | - | `current_timestamp()` |  |
| 13 | `status` | enum('pending','approved','rejected','cancelled','completed') | YES | MUL | `'pending'` |  |
| 14 | `approved_by` | int(10) unsigned | YES | MUL | `NULL` |  |
| 15 | `approved_at` | datetime | YES | - | `NULL` |  |
| 16 | `rejection_reason` | text | YES | - | `NULL` |  |
| 17 | `checked_out_at` | datetime | YES | - | `NULL` |  |
| 18 | `checked_out_by` | int(10) unsigned | YES | - | `NULL` |  |
| 19 | `expected_return` | datetime | YES | - | `NULL` |  |
| 20 | `checked_in_at` | datetime | YES | - | `NULL` |  |
| 21 | `checked_in_by` | int(10) unsigned | YES | - | `NULL` |  |
| 22 | `notes` | text | YES | - | `NULL` |  |
| 23 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 24 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

---
## `student_permission_types`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `code` | varchar(30) | NO | UNI | NULL |  |
| 3 | `name` | varchar(100) | NO | - | NULL |  |
| 4 | `description` | text | YES | - | `NULL` |  |
| 5 | `max_days` | int(11) | YES | - | `NULL` |  |
| 6 | `requires_approval` | tinyint(1) | YES | - | `1` |  |
| 7 | `requires_parent_request` | tinyint(1) | YES | - | `1` |  |
| 8 | `applies_to` | enum('all','boarders_only','day_only') | YES | - | `'all'` |  |
| 9 | `status` | enum('active','inactive') | YES | - | `'active'` |  |
| 10 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |

UNIQUE/INDEX: `code` (`code`)

---
## `student_promotions`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `batch_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `from_enrollment_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 4 | `to_enrollment_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 5 | `from_academic_year_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 6 | `to_academic_year_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 7 | `student_id` | int(10) unsigned | NO | MUL | NULL |  |
| 8 | `current_class_id` | int(10) unsigned | NO | MUL | NULL |  |
| 9 | `current_stream_id` | int(10) unsigned | NO | MUL | NULL |  |
| 10 | `promoted_to_class_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 11 | `promoted_to_stream_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 12 | `from_academic_year` | year(4) | NO | MUL | NULL |  |
| 13 | `to_academic_year` | year(4) | NO | - | NULL |  |
| 14 | `from_term_id` | int(10) unsigned | NO | MUL | NULL |  |
| 15 | `promotion_status` | enum('pending_approval','approved','rejected','transferred','retained','graduated','suspended','on_hold') | NO | MUL | `'pending_approval'` |  |
| 16 | `overall_score` | decimal(5,2) | YES | - | `NULL` |  |
| 17 | `final_grade` | varchar(4) | YES | - | `NULL` |  |
| 18 | `promotion_reason` | varchar(255) | YES | - | `NULL` |  |
| 19 | `rejection_reason` | text | YES | - | `NULL` |  |
| 20 | `transfer_to_school` | varchar(255) | YES | - | `NULL` |  |
| 21 | `approved_by` | int(10) unsigned | YES | MUL | `NULL` |  |
| 22 | `approval_date` | datetime | YES | MUL | `NULL` |  |
| 23 | `approval_notes` | text | YES | - | `NULL` |  |
| 24 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 25 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

UNIQUE/INDEX: `uk_promotion_cycle` (`student_id,from_academic_year,to_academic_year`)

---
## `student_registrations`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `student_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `class_id` | int(10) unsigned | NO | MUL | NULL |  |
| 4 | `term_id` | int(10) unsigned | NO | MUL | NULL |  |
| 5 | `registration_date` | date | NO | - | NULL |  |

UNIQUE/INDEX: `uk_student_class_term` (`student_id,class_id,term_id`)

---
## `students`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `admission_no` | varchar(20) | NO | UNI | NULL |  |
| 3 | `first_name` | varchar(50) | NO | MUL | NULL |  |
| 4 | `middle_name` | varchar(50) | YES | - | `NULL` |  |
| 5 | `last_name` | varchar(50) | NO | - | NULL |  |
| 6 | `date_of_birth` | date | NO | - | NULL |  |
| 7 | `gender` | enum('male','female','other') | NO | - | NULL |  |
| 8 | `stream_id` | int(10) unsigned | NO | MUL | NULL |  |
| 9 | `student_type_id` | int(10) unsigned | YES | MUL | `1` |  |
| 10 | `admission_date` | date | NO | - | NULL |  |
| 11 | `assessment_number` | varchar(50) | YES | UNI | `NULL` |  |
| 12 | `assessment_status` | enum('not_assigned','pending','assigned','verified','transferred') | NO | MUL | `'not_assigned'` |  |
| 13 | `nemis_number` | varchar(20) | YES | UNI | `NULL` |  |
| 14 | `nemis_status` | enum('not_assigned','pending','assigned','verified') | NO | MUL | `'not_assigned'` |  |
| 15 | `status` | enum('active','inactive','graduated','transferred','suspended') | NO | MUL | `'active'` |  |
| 16 | `application_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 17 | `photo_url` | varchar(255) | YES | - | `NULL` |  |
| 18 | `qr_code_path` | varchar(255) | YES | MUL | `NULL` |  |
| 19 | `is_sponsored` | tinyint(1) | YES | MUL | `0` |  |
| 20 | `sponsor_name` | varchar(100) | YES | - | `NULL` |  |
| 21 | `sponsor_type` | enum('partial','full','conditional') | YES | MUL | `NULL` |  |
| 22 | `sponsor_waiver_percentage` | decimal(5,2) | YES | - | `0.00` |  |
| 23 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 24 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |
| 25 | `blood_group` | varchar(10) | YES | - | `NULL` |  |

FKs: `application_id` → `admission_applications`.`id`

UNIQUE/INDEX: `idx_assessment_number` (`assessment_number`); `nemis_number` (`nemis_number`); `uk_admission_no` (`admission_no`)

---
## `student_suspensions`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `student_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `academic_year` | year(4) | NO | - | NULL |  |
| 4 | `suspension_type` | enum('disciplinary','medical','financial','other') | NO | - | NULL |  |
| 5 | `reason` | text | NO | - | NULL |  |
| 6 | `suspension_date` | date | NO | MUL | NULL |  |
| 7 | `expected_return_date` | date | YES | - | `NULL` |  |
| 8 | `actual_return_date` | date | YES | - | `NULL` |  |
| 9 | `suspended_by` | int(10) unsigned | NO | MUL | NULL |  |
| 10 | `status` | enum('active','resolved','pending','appealed') | NO | MUL | `'active'` |  |
| 11 | `appeal_notes` | text | YES | - | `NULL` |  |
| 12 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 13 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

---
## `student_transfer_requests`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `request_number` | varchar(30) | NO | UNI | NULL |  |
| 3 | `student_id` | int(10) unsigned | NO | MUL | NULL |  |
| 4 | `academic_year_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 5 | `request_date` | date | NO | - | NULL |  |
| 6 | `requested_by` | int(10) unsigned | NO | - | NULL |  |
| 7 | `transfer_type` | enum('inter_school','withdrawal','intra_school','upgrade') | YES | - | `'inter_school'` |  |
| 8 | `destination_school` | varchar(255) | YES | - | `NULL` |  |
| 9 | `reason` | text | YES | - | `NULL` |  |
| 10 | `fee_balance_at_request` | decimal(12,2) | YES | - | `0.00` |  |
| 11 | `clearance_status` | enum('pending','finance_cleared','library_cleared','fully_cleared','blocked') | YES | - | `'pending'` |  |
| 12 | `status` | enum('draft','pending_clearance','clearance_passed','approved','rejected','completed','cancelled') | YES | MUL | `'draft'` |  |
| 13 | `approved_by` | int(10) unsigned | YES | - | `NULL` |  |
| 14 | `approval_date` | datetime | YES | - | `NULL` |  |
| 15 | `rejection_reason` | text | YES | - | `NULL` |  |
| 16 | `completed_at` | datetime | YES | - | `NULL` |  |
| 17 | `notes` | text | YES | - | `NULL` |  |
| 18 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 19 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

UNIQUE/INDEX: `request_number` (`request_number`)

---
## `student_transport_assignments`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `student_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `route_id` | int(10) unsigned | NO | MUL | NULL |  |
| 4 | `stop_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 5 | `pickup_stop_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 6 | `dropoff_stop_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 7 | `pickup_time` | time | YES | - | `NULL` |  |
| 8 | `dropoff_time` | time | YES | - | `NULL` |  |
| 9 | `assignment_date` | date | YES | - | `NULL` |  |
| 10 | `assigned_by` | int(10) unsigned | YES | MUL | `NULL` |  |
| 11 | `notes` | text | YES | - | `NULL` |  |
| 12 | `month` | tinyint(3) unsigned | NO | - | NULL |  |
| 13 | `year` | year(4) | NO | - | NULL |  |
| 14 | `expected_amount` | decimal(10,2) | NO | - | `0.00` |  |
| 15 | `status` | enum('active','withdrawn','suspended','not_riding','transferred') | YES | - | `'active'` |  |
| 16 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |

FKs: `pickup_stop_id` → `transport_stops`.`id`; `stop_id` → `transport_stops`.`id`; `route_id` → `transport_routes`.`id`; `student_id` → `students`.`id`; `assigned_by` → `users`.`id`; `dropoff_stop_id` → `transport_stops`.`id`

---
## `student_transport_attendance`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `student_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `route_id` | int(10) unsigned | NO | MUL | NULL |  |
| 4 | `vehicle_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 5 | `attendance_date` | date | NO | MUL | NULL |  |
| 6 | `trip_session` | enum('morning_pickup','evening_dropoff','midday_trip','special_trip') | NO | - | `'morning_pickup'` |  |
| 7 | `status` | enum('pending','picked_up','dropped_off','absent','excused','not_riding') | NO | MUL | `'pending'` |  |
| 8 | `marked_time` | time | YES | - | `NULL` |  |
| 9 | `notes` | text | YES | - | `NULL` |  |
| 10 | `marked_by` | int(10) unsigned | YES | MUL | `NULL` |  |
| 11 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 12 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

FKs: `student_id` → `students`.`id`; `marked_by` → `users`.`id`; `vehicle_id` → `transport_vehicles`.`id`; `route_id` → `transport_routes`.`id`

UNIQUE/INDEX: `unique_attendance` (`student_id,attendance_date,trip_session`)

---
## `student_transport_incidents`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `student_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 3 | `route_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 4 | `vehicle_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 5 | `incident_datetime` | datetime | NO | MUL | NULL |  |
| 6 | `incident_type` | enum('accident','late_pickup','late_dropoff','wrong_stop','behavior','medical','vehicle_breakdown','other') | NO | - | NULL |  |
| 7 | `description` | text | NO | - | NULL |  |
| 8 | `action_taken` | text | YES | - | `NULL` |  |
| 9 | `escalated` | tinyint(1) | NO | MUL | `0` |  |
| 10 | `escalated_to` | int(10) unsigned | YES | MUL | `NULL` |  |
| 11 | `escalated_at` | timestamp | YES | - | `NULL` |  |
| 12 | `reported_by` | int(10) unsigned | NO | MUL | NULL |  |
| 13 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 14 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

FKs: `reported_by` → `users`.`id`; `vehicle_id` → `transport_vehicles`.`id`; `route_id` → `transport_routes`.`id`; `student_id` → `students`.`id`; `escalated_to` → `users`.`id`

---
## `student_transport_notes`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `student_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `note_type` | enum('pickup_instruction','dropoff_instruction','safety_alert','emergency_contact','behavior_note','other') | NO | MUL | `'other'` |  |
| 4 | `note` | text | NO | - | NULL |  |
| 5 | `visibility` | enum('public','driver_only','admin_only') | NO | MUL | `'public'` |  |
| 6 | `priority` | enum('low','medium','high','urgent') | NO | MUL | `'medium'` |  |
| 7 | `resolved` | tinyint(1) | NO | MUL | `0` |  |
| 8 | `resolved_at` | timestamp | YES | - | `NULL` |  |
| 9 | `resolved_by` | int(10) unsigned | YES | MUL | `NULL` |  |
| 10 | `created_by` | int(10) unsigned | NO | MUL | NULL |  |
| 11 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 12 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

FKs: `resolved_by` → `users`.`id`; `created_by` → `users`.`id`; `student_id` → `students`.`id`

---
## `student_transport_payments`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `student_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `route_id` | int(10) unsigned | YES | - | `NULL` |  |
| 4 | `month` | tinyint(3) unsigned | NO | - | NULL |  |
| 5 | `year` | year(4) | NO | - | NULL |  |
| 6 | `amount` | decimal(10,2) | NO | - | `0.00` |  |
| 7 | `payment_date` | date | YES | - | `NULL` |  |
| 8 | `payment_method` | varchar(50) | NO | - | `'cash'` |  |
| 9 | `transaction_id` | varchar(100) | YES | - | `NULL` |  |
| 10 | `status` | enum('pending','confirmed','reversed') | NO | MUL | `'pending'` |  |
| 11 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |

FKs: `student_id` → `students`.`id`

---
## `student_types`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `code` | varchar(20) | NO | UNI | NULL |  |
| 3 | `name` | varchar(100) | NO | - | NULL |  |
| 4 | `description` | text | YES | - | `NULL` |  |
| 5 | `status` | enum('active','inactive') | NO | MUL | `'active'` |  |
| 6 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |

UNIQUE/INDEX: `code` (`code`)

---
## `student_uniforms`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `student_id` | int(10) unsigned | NO | UNI | NULL |  |
| 3 | `uniform_size` | varchar(20) | YES | - | `NULL` |  |
| 4 | `shirt_size` | varchar(20) | YES | - | `NULL` |  |
| 5 | `trousers_size` | varchar(20) | YES | - | `NULL` |  |
| 6 | `skirt_size` | varchar(20) | YES | - | `NULL` |  |
| 7 | `sweater_size` | varchar(20) | YES | - | `NULL` |  |
| 8 | `shoes_size` | varchar(10) | YES | - | `NULL` |  |
| 9 | `notes` | text | YES | - | `NULL` |  |
| 10 | `last_updated` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |
| 11 | `updated_by` | int(10) unsigned | YES | - | `NULL` |  |

UNIQUE/INDEX: `uk_student` (`student_id`)

---
## `student_vaccinations`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `student_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `vaccine_name` | varchar(150) | NO | - | NULL |  |
| 4 | `dose_number` | tinyint(3) unsigned | NO | - | `1` |  |
| 5 | `date_given` | date | NO | MUL | NULL |  |
| 6 | `next_due_date` | date | YES | - | `NULL` |  |
| 7 | `given_by` | varchar(100) | YES | - | `NULL` |  |
| 8 | `batch_number` | varchar(50) | YES | - | `NULL` |  |
| 9 | `notes` | text | YES | - | `NULL` |  |
| 10 | `created_by` | int(10) unsigned | YES | - | `NULL` |  |
| 11 | `created_at` | datetime | NO | - | `current_timestamp()` |  |

---
## `student_welfare_cases`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `student_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `case_code` | varchar(20) | NO | UNI | NULL |  |
| 4 | `title` | varchar(255) | NO | - | NULL |  |
| 5 | `welfare_category` | enum('emotional','social','behavioral','family','chapel','pastoral','referral','other') | NO | - | `'other'` |  |
| 6 | `referral_source` | varchar(100) | YES | - | `NULL` |  |
| 7 | `priority` | enum('low','medium','high','urgent') | NO | MUL | `'medium'` |  |
| 8 | `status` | enum('open','in_progress','resolved','closed','cancelled') | NO | MUL | `'open'` |  |
| 9 | `description` | text | YES | - | `NULL` |  |
| 10 | `assigned_to` | int(10) unsigned | YES | MUL | `NULL` |  |
| 11 | `opened_by` | int(10) unsigned | NO | MUL | NULL |  |
| 12 | `opened_at` | timestamp | NO | - | `current_timestamp()` |  |
| 13 | `next_follow_up_at` | timestamp | YES | MUL | `NULL` |  |
| 14 | `resolved_at` | timestamp | YES | - | `NULL` |  |
| 15 | `resolved_by` | int(10) unsigned | YES | MUL | `NULL` |  |
| 16 | `resolution_notes` | text | YES | - | `NULL` |  |
| 17 | `related_discipline_case_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 18 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 19 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

FKs: `opened_by` → `users`.`id`; `assigned_to` → `users`.`id`; `student_id` → `students`.`id`; `related_discipline_case_id` → `student_discipline`.`id`; `resolved_by` → `users`.`id`

UNIQUE/INDEX: `case_code` (`case_code`)

---
## `student_welfare_notes`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `welfare_case_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `note_type` | enum('assessment','intervention','observation','guardian_contact','follow_up','referral','other') | NO | MUL | `'other'` |  |
| 4 | `note` | text | NO | - | NULL |  |
| 5 | `visibility` | enum('public','counselor_only','admin_only') | NO | MUL | `'public'` |  |
| 6 | `follow_up_date` | timestamp | YES | MUL | `NULL` |  |
| 7 | `recorded_by` | int(10) unsigned | NO | MUL | NULL |  |
| 8 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 9 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

FKs: `recorded_by` → `users`.`id`; `welfare_case_id` → `student_welfare_cases`.`id`

---
## `subject_time_allocations`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `class_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `subject_id` | int(10) unsigned | YES | - | `NULL` |  |
| 4 | `subject_name` | varchar(100) | YES | - | `NULL` |  |
| 5 | `academic_year_id` | int(10) unsigned | YES | - | `NULL` |  |
| 6 | `term_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 7 | `periods_per_week` | tinyint(3) unsigned | NO | - | `5` |  |
| 8 | `teacher_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 9 | `is_active` | tinyint(1) | NO | - | `1` |  |
| 10 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 11 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

UNIQUE/INDEX: `uq_class_subject_term` (`class_id,subject_name,term_id`)

---
## `sub_strand_competencies`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `sub_strand_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `competency_id` | int(10) unsigned | NO | MUL | NULL |  |
| 4 | `weight` | decimal(5,2) | NO | - | `1.00` |  |
| 5 | `source_document` | varchar(255) | YES | - | `NULL` |  |
| 6 | `source_page` | varchar(20) | YES | - | `NULL` |  |
| 7 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |

FKs: `sub_strand_id` → `sub_strands`.`id`; `competency_id` → `core_competencies`.`id`

UNIQUE/INDEX: `uq_ss_comp` (`sub_strand_id,competency_id`)

---
## `sub_strand_key_inquiry_questions`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `sub_strand_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `question` | text | NO | - | NULL |  |
| 4 | `sort_order` | smallint(5) unsigned | NO | - | `1` |  |
| 5 | `source_document` | varchar(255) | YES | - | `NULL` |  |
| 6 | `source_page` | varchar(20) | YES | - | `NULL` |  |
| 7 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |

FKs: `sub_strand_id` → `sub_strands`.`id`

---
## `sub_strand_pci_issues`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `sub_strand_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `pci_id` | int(10) unsigned | NO | MUL | NULL |  |
| 4 | `note` | text | YES | - | `NULL` |  |
| 5 | `sort_order` | smallint(5) unsigned | NO | - | `1` |  |
| 6 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |

FKs: `sub_strand_id` → `sub_strands`.`id`; `pci_id` → `pcis`.`id`

UNIQUE/INDEX: `uq_ss_pci` (`sub_strand_id,pci_id`)

---
## `sub_strand_rubrics`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `sub_strand_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `level_number` | tinyint(3) unsigned | NO | - | NULL |  |
| 4 | `level_label` | varchar(50) | YES | - | `NULL` |  |
| 5 | `descriptor` | text | NO | - | NULL |  |
| 6 | `sort_order` | smallint(5) unsigned | NO | - | `1` |  |
| 7 | `source_document` | varchar(255) | YES | - | `NULL` |  |
| 8 | `source_page` | varchar(20) | YES | - | `NULL` |  |
| 9 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |

FKs: `sub_strand_id` → `sub_strands`.`id`

---
## `sub_strands`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `strand_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `grade_level` | varchar(20) | NO | MUL | `''` |  |
| 4 | `code` | varchar(20) | NO | - | NULL |  |
| 5 | `name` | varchar(200) | NO | - | NULL |  |
| 6 | `variant` | varchar(20) | YES | - | `NULL` |  |
| 7 | `source_subject` | varchar(100) | YES | - | `NULL` |  |
| 8 | `description` | text | YES | - | `NULL` |  |
| 9 | `sort_order` | smallint(5) unsigned | NO | - | `1` |  |
| 10 | `status` | enum('active','inactive') | NO | - | `'active'` |  |
| 11 | `created_at` | datetime | NO | - | `current_timestamp()` |  |

UNIQUE/INDEX: `uq_sub_strand_grade` (`strand_id,grade_level,name,variant`)

---
## `sub_strand_suggested_experiences`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `sub_strand_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `experience` | text | NO | - | NULL |  |
| 4 | `sort_order` | smallint(5) unsigned | NO | - | `1` |  |
| 5 | `source_document` | varchar(255) | YES | - | `NULL` |  |
| 6 | `source_page` | varchar(20) | YES | - | `NULL` |  |
| 7 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |

FKs: `sub_strand_id` → `sub_strands`.`id`

---
## `sub_strand_values`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `sub_strand_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `value_name` | varchar(150) | NO | - | NULL |  |
| 4 | `description` | text | YES | - | `NULL` |  |
| 5 | `sort_order` | smallint(5) unsigned | NO | - | `1` |  |
| 6 | `source_document` | varchar(255) | YES | - | `NULL` |  |
| 7 | `source_page` | varchar(20) | YES | - | `NULL` |  |
| 8 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |

FKs: `sub_strand_id` → `sub_strands`.`id`

---
## `suppliers`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `name` | varchar(255) | NO | - | NULL |  |
| 3 | `contact_person` | varchar(100) | YES | - | `NULL` |  |
| 4 | `phone` | varchar(20) | NO | - | NULL |  |
| 5 | `email` | varchar(100) | YES | - | `NULL` |  |
| 6 | `address` | text | YES | - | `NULL` |  |
| 7 | `status` | enum('active','inactive') | NO | MUL | `'active'` |  |
| 8 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 9 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |
| 10 | `supplier_name` | varchar(255) | YES | - | `NULL` | VIRTUAL GENERATED |

---
## `system_access_policies`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `policy_key` | varchar(150) | NO | UNI | NULL |  |
| 3 | `name` | varchar(190) | NO | - | NULL |  |
| 4 | `description` | text | YES | - | `NULL` |  |
| 5 | `domain` | enum('SYSTEM','SCHOOL') | YES | - | `NULL` |  |
| 6 | `effect` | enum('allow','deny') | NO | - | `'deny'` |  |
| 7 | `enabled` | tinyint(1) | NO | MUL | `1` |  |
| 8 | `rules_json` | longtext | YES | - | `NULL` |  |
| 9 | `created_by` | int(10) unsigned | YES | MUL | `NULL` |  |
| 10 | `updated_by` | int(10) unsigned | YES | MUL | `NULL` |  |
| 11 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 12 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

FKs: `updated_by` → `users`.`id`; `created_by` → `users`.`id`

UNIQUE/INDEX: `uq_system_access_policy_key` (`policy_key`)

---
## `system_alerts`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `title` | varchar(255) | NO | - | NULL |  |
| 3 | `message` | text | YES | - | `NULL` |  |
| 4 | `severity` | enum('info','warning','critical') | YES | - | `'info'` |  |
| 5 | `resolved` | tinyint(1) | YES | - | `0` |  |
| 6 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 7 | `resolved_at` | timestamp | YES | - | `NULL` |  |
| 8 | `resolved_by` | int(10) unsigned | YES | - | `NULL` |  |

---
## `system_api_metrics`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | bigint(20) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `endpoint` | varchar(500) | NO | MUL | NULL |  |
| 3 | `http_method` | varchar(12) | NO | - | NULL |  |
| 4 | `status_code` | smallint(5) unsigned | NO | - | NULL |  |
| 5 | `duration_ms` | decimal(12,3) | NO | - | `0.000` |  |
| 6 | `user_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 7 | `ip_address` | varchar(45) | YES | - | `NULL` |  |
| 8 | `created_at` | timestamp | NO | MUL | `current_timestamp()` |  |

FKs: `user_id` → `users`.`id`

---
## `system_background_jobs`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `job_type` | varchar(150) | NO | - | NULL |  |
| 3 | `payload_json` | longtext | YES | - | `NULL` |  |
| 4 | `status` | enum('queued','processing','completed','failed','retrying','cancelled') | NO | MUL | `'queued'` |  |
| 5 | `attempts` | int(10) unsigned | NO | - | `0` |  |
| 6 | `max_attempts` | int(10) unsigned | NO | - | `3` |  |
| 7 | `next_attempt_at` | datetime | YES | - | `NULL` |  |
| 8 | `started_at` | datetime | YES | - | `NULL` |  |
| 9 | `completed_at` | datetime | YES | - | `NULL` |  |
| 10 | `last_error` | text | YES | - | `NULL` |  |
| 11 | `created_by` | int(10) unsigned | YES | MUL | `NULL` |  |
| 12 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 13 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

FKs: `created_by` → `users`.`id`

---
## `system_backups`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `filename` | varchar(500) | NO | - | NULL |  |
| 3 | `storage_path` | varchar(1000) | YES | - | `NULL` |  |
| 4 | `status` | enum('queued','processing','completed','failed','deleted') | NO | MUL | `'queued'` |  |
| 5 | `file_size_bytes` | bigint(20) unsigned | YES | - | `NULL` |  |
| 6 | `checksum` | varchar(128) | YES | - | `NULL` |  |
| 7 | `created_by` | int(10) unsigned | YES | MUL | `NULL` |  |
| 8 | `completed_at` | datetime | YES | - | `NULL` |  |
| 9 | `failure_message` | text | YES | - | `NULL` |  |
| 10 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 11 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

FKs: `created_by` → `users`.`id`

---
## `system_domain_isolation_rules`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `domain_key` | varchar(150) | NO | MUL | NULL |  |
| 3 | `resource_pattern` | varchar(255) | NO | - | NULL |  |
| 4 | `isolation_mode` | enum('strict','shared-read','disabled') | NO | - | `'strict'` |  |
| 5 | `description` | text | YES | - | `NULL` |  |
| 6 | `enabled` | tinyint(1) | NO | MUL | `1` |  |
| 7 | `created_by` | int(10) unsigned | YES | MUL | `NULL` |  |
| 8 | `updated_by` | int(10) unsigned | YES | MUL | `NULL` |  |
| 9 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 10 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

FKs: `updated_by` → `users`.`id`; `created_by` → `users`.`id`

UNIQUE/INDEX: `uq_system_domain_rule` (`domain_key,resource_pattern`)

---
## `system_error_logs`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | bigint(20) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `error_type` | varchar(150) | YES | - | `NULL` |  |
| 3 | `message` | text | NO | - | NULL |  |
| 4 | `file_path` | varchar(1000) | YES | - | `NULL` |  |
| 5 | `line_number` | int(10) unsigned | YES | - | `NULL` |  |
| 6 | `context_json` | longtext | YES | - | `NULL` |  |
| 7 | `user_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 8 | `ip_address` | varchar(45) | YES | - | `NULL` |  |
| 9 | `created_at` | timestamp | NO | MUL | `current_timestamp()` |  |

FKs: `user_id` → `users`.`id`

---
## `system_events`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `event_type` | varchar(100) | NO | MUL | NULL |  |
| 3 | `event_data` | longtext | NO | - | NULL |  |
| 4 | `created_at` | timestamp | NO | MUL | `current_timestamp()` |  |

---
## `system_feature_flags`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `key_name` | varchar(150) | NO | UNI | NULL |  |
| 3 | `name` | varchar(190) | NO | - | NULL |  |
| 4 | `description` | text | YES | - | `NULL` |  |
| 5 | `enabled` | tinyint(1) | NO | MUL | `0` |  |
| 6 | `environment` | enum('all','development','staging','production') | NO | - | `'all'` |  |
| 7 | `rollout_percentage` | tinyint(3) unsigned | NO | - | `100` |  |
| 8 | `created_by` | int(10) unsigned | YES | MUL | `NULL` |  |
| 9 | `updated_by` | int(10) unsigned | YES | MUL | `NULL` |  |
| 10 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 11 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

FKs: `updated_by` → `users`.`id`; `created_by` → `users`.`id`

UNIQUE/INDEX: `uq_system_feature_flags_key` (`key_name`)

---
## `system_ip_rules`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `rule_type` | enum('allow','deny') | NO | MUL | NULL |  |
| 3 | `cidr` | varchar(100) | NO | - | NULL |  |
| 4 | `description` | text | YES | - | `NULL` |  |
| 5 | `enabled` | tinyint(1) | NO | MUL | `1` |  |
| 6 | `starts_at` | datetime | YES | - | `NULL` |  |
| 7 | `expires_at` | datetime | YES | - | `NULL` |  |
| 8 | `created_by` | int(10) unsigned | YES | MUL | `NULL` |  |
| 9 | `updated_by` | int(10) unsigned | YES | MUL | `NULL` |  |
| 10 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 11 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

FKs: `updated_by` → `users`.`id`; `created_by` → `users`.`id`

UNIQUE/INDEX: `uq_system_ip_rule` (`rule_type,cidr`)

---
## `system_maintenance_windows`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `name` | varchar(190) | NO | - | NULL |  |
| 3 | `message` | text | YES | - | `NULL` |  |
| 4 | `starts_at` | datetime | YES | - | `NULL` |  |
| 5 | `ends_at` | datetime | YES | - | `NULL` |  |
| 6 | `enabled` | tinyint(1) | NO | MUL | `0` |  |
| 7 | `bypass_roles_json` | longtext | YES | - | `NULL` |  |
| 8 | `created_by` | int(10) unsigned | YES | MUL | `NULL` |  |
| 9 | `updated_by` | int(10) unsigned | YES | MUL | `NULL` |  |
| 10 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 11 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

FKs: `updated_by` → `users`.`id`; `created_by` → `users`.`id`

---
## `system_migration_history`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `migration_name` | varchar(500) | NO | UNI | NULL |  |
| 3 | `checksum` | varchar(128) | YES | - | `NULL` |  |
| 4 | `status` | enum('pending','applied','failed','rolled_back') | NO | - | `'pending'` |  |
| 5 | `notes` | text | YES | - | `NULL` |  |
| 6 | `executed_by` | int(10) unsigned | YES | MUL | `NULL` |  |
| 7 | `executed_at` | datetime | YES | - | `NULL` |  |
| 8 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 9 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

FKs: `executed_by` → `users`.`id`

UNIQUE/INDEX: `uq_system_migration_name` (`migration_name`)

---
## `system_modules`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `module_key` | varchar(150) | NO | UNI | NULL |  |
| 3 | `name` | varchar(190) | NO | - | NULL |  |
| 4 | `description` | text | YES | - | `NULL` |  |
| 5 | `enabled` | tinyint(1) | NO | MUL | `1` |  |
| 6 | `dependencies_json` | longtext | YES | - | `NULL` |  |
| 7 | `created_by` | int(10) unsigned | YES | MUL | `NULL` |  |
| 8 | `updated_by` | int(10) unsigned | YES | MUL | `NULL` |  |
| 9 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 10 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

FKs: `updated_by` → `users`.`id`; `created_by` → `users`.`id`

UNIQUE/INDEX: `uq_system_modules_key` (`module_key`)

---
## `system_permission_changes`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | bigint(20) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `actor_user_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 3 | `target_type` | enum('user','role') | NO | MUL | NULL |  |
| 4 | `target_id` | int(10) unsigned | NO | - | NULL |  |
| 5 | `permission_id` | int(11) | NO | MUL | NULL |  |
| 6 | `change_type` | enum('assigned','revoked','updated') | NO | - | NULL |  |
| 7 | `details_json` | longtext | YES | - | `NULL` |  |
| 8 | `created_at` | timestamp | NO | MUL | `current_timestamp()` |  |

FKs: `permission_id` → `permissions`.`id`; `actor_user_id` → `users`.`id`

---
## `system_policies`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `name` | varchar(100) | NO | UNI | NULL |  |
| 3 | `display_name` | varchar(150) | NO | - | NULL |  |
| 4 | `rule_type` | enum('deny','allow','restrict','require','audit') | NO | MUL | `'deny'` |  |
| 5 | `priority` | int(11) | NO | MUL | `0` |  |
| 6 | `rule_expression` | longtext | NO | - | NULL |  |
| 7 | `description` | varchar(500) | YES | - | `NULL` |  |
| 8 | `applies_to` | enum('role','user','route','domain','global') | NO | - | `'global'` |  |
| 9 | `target_ids` | longtext | YES | - | `NULL` |  |
| 10 | `effective_from` | timestamp | YES | MUL | `NULL` |  |
| 11 | `effective_until` | timestamp | YES | - | `NULL` |  |
| 12 | `is_active` | tinyint(1) | NO | MUL | `1` |  |
| 13 | `created_by` | int(10) unsigned | YES | MUL | `NULL` |  |
| 14 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 15 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

UNIQUE/INDEX: `name` (`name`)

---
## `system_policy_violations`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `policy_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 3 | `severity` | enum('low','medium','high','critical') | NO | - | `'medium'` |  |
| 4 | `status` | enum('open','investigating','resolved','dismissed') | NO | MUL | `'open'` |  |
| 5 | `user_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 6 | `resource_type` | varchar(100) | YES | - | `NULL` |  |
| 7 | `resource_id` | varchar(100) | YES | - | `NULL` |  |
| 8 | `description` | text | YES | - | `NULL` |  |
| 9 | `resolved_by` | int(10) unsigned | YES | MUL | `NULL` |  |
| 10 | `resolved_at` | datetime | YES | - | `NULL` |  |
| 11 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 12 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

FKs: `user_id` → `users`.`id`; `resolved_by` → `users`.`id`; `policy_id` → `system_access_policies`.`id`

---
## `system_rate_limit_rules`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `rule_key` | varchar(150) | NO | UNI | NULL |  |
| 3 | `route_pattern` | varchar(255) | NO | - | NULL |  |
| 4 | `http_method` | enum('GET','POST','PUT','PATCH','DELETE','*') | NO | - | `'*'` |  |
| 5 | `requests_limit` | int(10) unsigned | NO | - | `60` |  |
| 6 | `window_seconds` | int(10) unsigned | NO | - | `60` |  |
| 7 | `enabled` | tinyint(1) | NO | MUL | `1` |  |
| 8 | `created_by` | int(10) unsigned | YES | MUL | `NULL` |  |
| 9 | `updated_by` | int(10) unsigned | YES | MUL | `NULL` |  |
| 10 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 11 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

FKs: `updated_by` → `users`.`id`; `created_by` → `users`.`id`

UNIQUE/INDEX: `uq_system_rate_limit_key` (`rule_key`)

---
## `system_retention_policies`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `resource_key` | varchar(150) | NO | UNI | NULL |  |
| 3 | `retention_days` | int(10) unsigned | NO | - | NULL |  |
| 4 | `action` | enum('archive','anonymize','delete') | NO | - | `'archive'` |  |
| 5 | `enabled` | tinyint(1) | NO | MUL | `1` |  |
| 6 | `description` | text | YES | - | `NULL` |  |
| 7 | `created_by` | int(10) unsigned | YES | MUL | `NULL` |  |
| 8 | `updated_by` | int(10) unsigned | YES | MUL | `NULL` |  |
| 9 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 10 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

FKs: `updated_by` → `users`.`id`; `created_by` → `users`.`id`

UNIQUE/INDEX: `uq_system_retention_resource` (`resource_key`)

---
## `system_route_access_rules`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `route_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `http_method` | enum('GET','POST','PUT','PATCH','DELETE','*') | NO | - | `'*'` |  |
| 4 | `permission_id` | int(11) | YES | MUL | `NULL` |  |
| 5 | `effect` | enum('allow','deny') | NO | - | `'allow'` |  |
| 6 | `enabled` | tinyint(1) | NO | MUL | `1` |  |
| 7 | `created_by` | int(10) unsigned | YES | MUL | `NULL` |  |
| 8 | `updated_by` | int(10) unsigned | YES | MUL | `NULL` |  |
| 9 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 10 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

FKs: `updated_by` → `users`.`id`; `route_id` → `routes`.`id`; `permission_id` → `permissions`.`id`; `created_by` → `users`.`id`

UNIQUE/INDEX: `uq_system_route_access_rule` (`route_id,http_method,permission_id,effect`)

---
## `system_security_incidents`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `title` | varchar(255) | NO | - | NULL |  |
| 3 | `severity` | enum('low','medium','high','critical') | NO | - | `'medium'` |  |
| 4 | `status` | enum('open','investigating','contained','resolved','closed') | NO | MUL | `'open'` |  |
| 5 | `description` | text | YES | - | `NULL` |  |
| 6 | `assigned_to` | int(10) unsigned | YES | MUL | `NULL` |  |
| 7 | `resolved_at` | datetime | YES | - | `NULL` |  |
| 8 | `created_by` | int(10) unsigned | YES | MUL | `NULL` |  |
| 9 | `updated_by` | int(10) unsigned | YES | MUL | `NULL` |  |
| 10 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 11 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

FKs: `updated_by` → `users`.`id`; `created_by` → `users`.`id`; `assigned_to` → `users`.`id`

---
## `system_time_bound_access`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `user_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `role_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 4 | `permission_id` | int(11) | YES | MUL | `NULL` |  |
| 5 | `starts_at` | datetime | NO | - | NULL |  |
| 6 | `expires_at` | datetime | NO | - | NULL |  |
| 7 | `reason` | text | YES | - | `NULL` |  |
| 8 | `enabled` | tinyint(1) | NO | - | `1` |  |
| 9 | `granted_by` | int(10) unsigned | YES | MUL | `NULL` |  |
| 10 | `revoked_by` | int(10) unsigned | YES | MUL | `NULL` |  |
| 11 | `revoked_at` | datetime | YES | - | `NULL` |  |
| 12 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 13 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

FKs: `role_id` → `roles`.`id`; `revoked_by` → `users`.`id`; `permission_id` → `permissions`.`id`; `granted_by` → `users`.`id`; `user_id` → `users`.`id`

---
## `system_webhooks`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `name` | varchar(190) | NO | - | NULL |  |
| 3 | `target_url` | varchar(1000) | NO | - | NULL |  |
| 4 | `events_json` | longtext | YES | - | `NULL` |  |
| 5 | `enabled` | tinyint(1) | NO | MUL | `1` |  |
| 6 | `secret_hash` | char(64) | YES | - | `NULL` |  |
| 7 | `last_delivery_at` | datetime | YES | - | `NULL` |  |
| 8 | `created_by` | int(10) unsigned | YES | MUL | `NULL` |  |
| 9 | `updated_by` | int(10) unsigned | YES | MUL | `NULL` |  |
| 10 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 11 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

FKs: `updated_by` → `users`.`id`; `created_by` → `users`.`id`

---
## `tax_brackets`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `financial_year` | int(11) | NO | MUL | NULL |  |
| 3 | `min_income` | decimal(12,2) | NO | - | NULL |  |
| 4 | `max_income` | decimal(12,2) | NO | - | NULL |  |
| 5 | `tax_rate` | decimal(5,2) | NO | - | NULL |  |
| 6 | `relief_amount` | decimal(12,2) | YES | - | `0.00` |  |
| 7 | `is_active` | tinyint(1) | NO | - | `1` |  |
| 8 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |

UNIQUE/INDEX: `uk_year_bracket` (`financial_year,min_income,max_income`)

---
## `tax_withholding_history`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `staff_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `financial_year` | int(11) | NO | - | NULL |  |
| 4 | `payroll_month` | int(11) | NO | - | NULL |  |
| 5 | `gross_income` | decimal(12,2) | NO | - | NULL |  |
| 6 | `tax_calculated` | decimal(12,2) | NO | - | NULL |  |
| 7 | `tax_withheld` | decimal(12,2) | NO | - | NULL |  |
| 8 | `cumulative_tax` | decimal(12,2) | NO | - | NULL |  |
| 9 | `kra_pin` | varchar(50) | YES | MUL | `NULL` |  |
| 10 | `filed_with_kra` | tinyint(1) | NO | - | `0` |  |
| 11 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |

---
## `teaching_materials`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `title` | varchar(255) | NO | - | NULL |  |
| 3 | `description` | text | YES | - | `NULL` |  |
| 4 | `subject_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 5 | `learning_area_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 6 | `teacher_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 7 | `class_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 8 | `academic_year_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 9 | `term_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 10 | `file_path` | varchar(500) | NO | - | NULL |  |
| 11 | `file_name` | varchar(255) | NO | - | NULL |  |
| 12 | `file_type` | varchar(50) | NO | - | NULL |  |
| 13 | `file_size` | bigint(20) unsigned | YES | - | `NULL` |  |
| 14 | `resource_type` | enum('document','presentation','video','audio','image','other') | YES | MUL | `'document'` |  |
| 15 | `access_scope` | enum('private','subject','school','public') | YES | MUL | `'subject'` |  |
| 16 | `status` | enum('pending','approved','rejected','archived') | YES | MUL | `'pending'` |  |
| 17 | `download_count` | int(10) unsigned | YES | - | `0` |  |
| 18 | `uploaded_at` | timestamp | NO | MUL | `current_timestamp()` |  |
| 19 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 20 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

FKs: `subject_id` → `curriculum_units`.`id`; `learning_area_id` → `learning_areas`.`id`; `class_id` → `classes`.`id`; `academic_year_id` → `academic_years`.`id`; `term_id` → `academic_terms`.`id`; `teacher_id` → `staff`.`id`

---
## `template_categories`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `name` | varchar(50) | NO | UNI | NULL |  |
| 3 | `description` | text | YES | - | `NULL` |  |
| 4 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |

UNIQUE/INDEX: `uk_name` (`name`)

---
## `term_consolidations`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `student_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `term_id` | int(10) unsigned | NO | MUL | NULL |  |
| 4 | `academic_year` | year(4) | NO | MUL | NULL |  |
| 5 | `total_subjects` | int(11) | YES | - | `0` |  |
| 6 | `total_assessed_subjects` | int(11) | YES | - | `0` |  |
| 7 | `avg_overall_percentage` | decimal(5,2) | YES | - | `0.00` |  |
| 8 | `avg_overall_grade` | varchar(4) | YES | MUL | `NULL` |  |
| 9 | `performance_summary` | longtext | YES | - | `NULL` |  |
| 10 | `class_position` | int(11) | YES | MUL | `NULL` |  |
| 11 | `class_total` | int(11) | YES | - | `NULL` |  |
| 12 | `percentile` | decimal(5,2) | YES | - | `NULL` |  |
| 13 | `points_total` | decimal(5,1) | YES | - | `0.0` |  |
| 14 | `best_subject_id` | int(10) unsigned | YES | - | `NULL` |  |
| 15 | `best_subject_grade` | varchar(4) | YES | - | `NULL` |  |
| 16 | `worst_subject_id` | int(10) unsigned | YES | - | `NULL` |  |
| 17 | `worst_subject_grade` | varchar(4) | YES | - | `NULL` |  |
| 18 | `consolidated_at` | timestamp | YES | - | `NULL` |  |
| 19 | `consolidated_by` | int(10) unsigned | YES | MUL | `NULL` |  |
| 20 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 21 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

UNIQUE/INDEX: `uk_student_term` (`student_id,term_id`)

---
## `term_subject_scores`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `student_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `term_id` | int(10) unsigned | NO | MUL | NULL |  |
| 4 | `subject_id` | int(10) unsigned | NO | MUL | NULL |  |
| 5 | `formative_total` | decimal(8,2) | YES | - | `0.00` |  |
| 6 | `formative_max` | decimal(8,2) | YES | - | `0.00` |  |
| 7 | `formative_percentage` | decimal(5,2) | YES | - | `0.00` |  |
| 8 | `formative_grade` | varchar(4) | YES | - | `NULL` |  |
| 9 | `formative_count` | int(11) | YES | - | `0` |  |
| 10 | `summative_total` | decimal(8,2) | YES | - | `0.00` |  |
| 11 | `summative_max` | decimal(8,2) | YES | - | `0.00` |  |
| 12 | `summative_percentage` | decimal(5,2) | YES | - | `0.00` |  |
| 13 | `summative_grade` | varchar(4) | YES | - | `NULL` |  |
| 14 | `summative_count` | int(11) | YES | - | `0` |  |
| 15 | `overall_score` | decimal(8,2) | YES | - | `0.00` |  |
| 16 | `overall_percentage` | decimal(5,2) | YES | - | `0.00` |  |
| 17 | `overall_grade` | varchar(4) | YES | MUL | `NULL` |  |
| 18 | `overall_points` | decimal(3,1) | YES | - | `0.0` |  |
| 19 | `assessment_count` | int(11) | YES | - | `0` |  |
| 20 | `calculated_at` | timestamp | YES | - | `NULL` |  |
| 21 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 22 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

UNIQUE/INDEX: `uk_student_term_subject` (`student_id,term_id,subject_id`)

---
## `term_transition_log`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `from_term_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 3 | `to_term_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 4 | `academic_year_id` | int(10) unsigned | YES | - | `NULL` |  |
| 5 | `action` | enum('close_term','activate_term','rollover_timetable','rollover_schemes','generate_exam_schedule','notify_staff','full_transition') | NO | MUL | NULL |  |
| 6 | `status` | enum('pending','in_progress','completed','failed') | NO | MUL | `'pending'` |  |
| 7 | `details` | longtext | YES | - | `NULL` |  |
| 8 | `error_message` | text | YES | - | `NULL` |  |
| 9 | `performed_by` | int(10) unsigned | YES | - | `NULL` |  |
| 10 | `performed_at` | timestamp | NO | - | `current_timestamp()` |  |

---
## `time_slots`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `period_number` | tinyint(3) unsigned | NO | MUL | NULL |  |
| 3 | `start_time` | time | NO | - | NULL |  |
| 4 | `end_time` | time | NO | - | NULL |  |
| 5 | `slot_type` | enum('lesson','break','lunch','assembly','games') | NO | - | `'lesson'` |  |
| 6 | `label` | varchar(50) | YES | - | `NULL` |  |
| 7 | `is_active` | tinyint(1) | NO | - | `1` |  |
| 8 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |

UNIQUE/INDEX: `idx_period_time` (`period_number,start_time`)

---
## `timetable_conflicts`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `reported_by` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `conflict_type` | enum('teacher_overlap','room_overlap','class_overlap','other') | NO | - | `'other'` |  |
| 4 | `description` | text | NO | - | NULL |  |
| 5 | `day_of_week` | enum('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday') | YES | - | `NULL` |  |
| 6 | `time_slot` | varchar(50) | YES | - | `NULL` |  |
| 7 | `schedule_id_1` | int(10) unsigned | YES | - | `NULL` |  |
| 8 | `schedule_id_2` | int(10) unsigned | YES | - | `NULL` |  |
| 9 | `status` | enum('reported','acknowledged','resolved','dismissed') | NO | MUL | `'reported'` |  |
| 10 | `resolved_by` | int(10) unsigned | YES | - | `NULL` |  |
| 11 | `resolution_notes` | text | YES | - | `NULL` |  |
| 12 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 13 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

---
## `timetable_templates`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `name` | varchar(100) | NO | - | NULL |  |
| 3 | `description` | text | YES | - | `NULL` |  |
| 4 | `template_data` | longtext | NO | - | NULL |  |
| 5 | `applies_to` | enum('school','class','level') | YES | MUL | `'school'` |  |
| 6 | `class_id` | int(10) unsigned | YES | - | `NULL` |  |
| 7 | `created_by` | int(10) unsigned | YES | - | `NULL` |  |
| 8 | `is_active` | tinyint(1) | NO | - | `1` |  |
| 9 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 10 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

---
## `tmp_backup_role_dashboards`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | - | `0` |  |
| 2 | `role_id` | int(10) unsigned | NO | - | NULL |  |
| 3 | `dashboard_id` | int(10) unsigned | NO | - | NULL |  |
| 4 | `is_primary` | tinyint(1) | NO | - | `0` |  |
| 5 | `display_order` | int(11) | NO | - | `0` |  |
| 6 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |

---
## `transport_assignments`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `route_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `student_id` | int(10) unsigned | NO | MUL | NULL |  |
| 4 | `status` | enum('active','inactive') | NO | - | `'active'` |  |
| 5 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |

FKs: `student_id` → `students`.`id`; `route_id` → `transport_routes`.`id`

---
## `transport_bill_payments`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `bill_id` | int(10) unsigned | NO | - | NULL |  |
| 3 | `amount` | decimal(10,2) | NO | - | NULL |  |
| 4 | `payment_method` | varchar(50) | NO | - | `'cash'` |  |
| 5 | `transaction_id` | varchar(100) | YES | - | `NULL` |  |
| 6 | `received_by` | int(10) unsigned | YES | - | `NULL` |  |
| 7 | `payment_date` | date | NO | - | NULL |  |
| 8 | `notes` | text | YES | - | `NULL` |  |
| 9 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |

---
## `transport_bills`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `subscription_id` | int(10) unsigned | NO | - | NULL |  |
| 3 | `student_id` | int(10) unsigned | NO | MUL | NULL |  |
| 4 | `route_id` | int(10) unsigned | NO | - | NULL |  |
| 5 | `billing_month` | date | NO | MUL | NULL |  |
| 6 | `amount` | decimal(10,2) | NO | - | `0.00` |  |
| 7 | `payment_status` | enum('unpaid','partial','paid','waived') | NO | MUL | `'unpaid'` |  |
| 8 | `amount_paid` | decimal(10,2) | NO | - | `0.00` |  |
| 9 | `generated_by` | int(10) unsigned | YES | - | `NULL` |  |
| 10 | `paid_at` | timestamp | YES | - | `NULL` |  |
| 11 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 12 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

UNIQUE/INDEX: `uq_bill` (`student_id,route_id,billing_month`)

---
## `transport_monthly_bills`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `student_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `subscription_id` | int(10) unsigned | NO | MUL | NULL |  |
| 4 | `route_id` | int(10) unsigned | NO | - | NULL |  |
| 5 | `billing_month` | date | NO | MUL | NULL |  |
| 6 | `amount_due` | decimal(10,2) | NO | - | NULL |  |
| 7 | `amount_paid` | decimal(10,2) | NO | - | `0.00` |  |
| 8 | `balance` | decimal(10,2) | YES | - | `NULL` | STORED GENERATED |
| 9 | `payment_status` | enum('pending','partial','paid','waived','cancelled') | NO | MUL | `'pending'` |  |
| 10 | `due_date` | date | NO | - | NULL |  |
| 11 | `generated_at` | datetime | NO | - | `current_timestamp()` |  |
| 12 | `generated_by` | int(10) unsigned | YES | - | `NULL` |  |
| 13 | `notes` | text | YES | - | `NULL` |  |
| 14 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 15 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

UNIQUE/INDEX: `uk_student_month` (`student_id,billing_month`)

---
## `transport_payments`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(11) | NO | PRI | NULL | auto_increment |
| 2 | `student_id` | int(11) | NO | - | NULL |  |
| 3 | `amount` | decimal(12,2) | NO | - | NULL |  |
| 4 | `payment_method` | varchar(50) | YES | - | `NULL` |  |
| 5 | `paybill_reference` | varchar(100) | YES | - | `NULL` |  |
| 6 | `status` | enum('pending','confirmed','failed') | YES | - | `'pending'` |  |
| 7 | `paid_at` | timestamp | NO | - | `current_timestamp()` |  |

---
## `transport_routes`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `name` | varchar(100) | NO | - | NULL |  |
| 3 | `start_point` | varchar(255) | NO | - | NULL |  |
| 4 | `end_point` | varchar(255) | NO | - | NULL |  |
| 5 | `code` | varchar(20) | NO | UNI | NULL |  |
| 6 | `description` | text | YES | - | `NULL` |  |
| 7 | `distance` | decimal(10,2) | YES | - | `NULL` |  |
| 8 | `estimated_time` | time | YES | - | `NULL` |  |
| 9 | `fee` | decimal(10,2) | NO | - | NULL |  |
| 10 | `status` | enum('active','inactive') | NO | MUL | `'active'` |  |
| 11 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 12 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |
| 13 | `morning_departure` | time | NO | - | NULL |  |
| 14 | `afternoon_departure` | time | NO | - | NULL |  |
| 15 | `estimated_duration` | int(11) | NO | - | NULL |  |
| 16 | `max_capacity` | int(11) | NO | - | `0` |  |
| 17 | `current_capacity` | int(11) | NO | - | `0` |  |
| 18 | `last_active_date` | date | YES | - | `NULL` |  |

UNIQUE/INDEX: `uk_code` (`code`)

---
## `transport_schedules`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `vehicle_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 3 | `route_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 4 | `driver_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 5 | `date` | date | YES | - | `NULL` |  |
| 6 | `pickup_time` | time | YES | - | `NULL` |  |
| 7 | `term_id` | int(10) unsigned | YES | - | `NULL` |  |
| 8 | `status` | enum('active','cancelled') | NO | - | `'active'` |  |
| 9 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |

---
## `transport_stops`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `route_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `name` | varchar(100) | NO | - | NULL |  |
| 4 | `sequence` | int(11) | NO | MUL | NULL |  |
| 5 | `arrival_time` | time | YES | - | `NULL` |  |
| 6 | `departure_time` | time | YES | - | `NULL` |  |
| 7 | `location` | varchar(255) | YES | - | `NULL` |  |
| 8 | `status` | enum('active','inactive') | NO | MUL | `'active'` |  |
| 9 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 10 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

---
## `transport_subscriptions`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `student_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `route_id` | int(10) unsigned | NO | MUL | NULL |  |
| 4 | `start_month` | date | NO | - | NULL |  |
| 5 | `end_month` | date | YES | - | `NULL` |  |
| 6 | `status` | enum('active','cancelled','suspended') | NO | MUL | `'active'` |  |
| 7 | `subscribed_by` | int(10) unsigned | YES | - | `NULL` |  |
| 8 | `cancelled_by` | int(10) unsigned | YES | - | `NULL` |  |
| 9 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 10 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

---
## `transport_vehicle_routes`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `vehicle_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `route_id` | int(10) unsigned | NO | MUL | NULL |  |
| 4 | `direction` | enum('pickup','dropoff') | NO | - | NULL |  |
| 5 | `status` | enum('active','inactive') | NO | MUL | `'active'` |  |
| 6 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 7 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

UNIQUE/INDEX: `uk_vehicle_route_direction` (`vehicle_id,route_id,direction`)

---
## `transport_vehicles`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `registration_number` | varchar(20) | NO | UNI | NULL |  |
| 3 | `type` | varchar(50) | NO | - | NULL |  |
| 4 | `model` | varchar(50) | YES | - | `NULL` |  |
| 5 | `make` | varchar(50) | YES | - | `NULL` |  |
| 6 | `year` | year(4) | YES | - | `NULL` |  |
| 7 | `capacity` | int(11) | NO | - | NULL |  |
| 8 | `driver_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 9 | `insurance_expiry` | date | YES | - | `NULL` |  |
| 10 | `service_due_date` | date | YES | - | `NULL` |  |
| 11 | `status` | enum('active','maintenance','inactive') | NO | MUL | `'active'` |  |
| 12 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 13 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

UNIQUE/INDEX: `uk_registration` (`registration_number`)

---
## `uniform_payment_records`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `sale_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `amount` | decimal(10,2) | NO | - | NULL |  |
| 4 | `payment_method` | enum('cash','mpesa','bank','cheque') | YES | - | `'cash'` |  |
| 5 | `reference_no` | varchar(100) | YES | - | `NULL` |  |
| 6 | `payment_date` | date | NO | - | NULL |  |
| 7 | `recorded_by` | int(10) unsigned | YES | - | `NULL` |  |
| 8 | `notes` | text | YES | - | `NULL` |  |
| 9 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |

---
## `uniform_purchase_items`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `purchase_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `item_id` | int(10) unsigned | NO | - | NULL |  |
| 4 | `size` | varchar(20) | NO | - | NULL |  |
| 5 | `quantity` | int(11) | NO | - | NULL |  |
| 6 | `unit_cost` | decimal(10,2) | NO | - | NULL |  |
| 7 | `total_cost` | decimal(12,2) | YES | - | `NULL` | STORED GENERATED |
| 8 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |

FKs: `purchase_id` → `uniform_purchases`.`id`

---
## `uniform_purchases`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `purchase_date` | date | NO | MUL | NULL |  |
| 3 | `supplier_id` | int(10) unsigned | YES | - | `NULL` |  |
| 4 | `supplier_name` | varchar(255) | YES | - | `NULL` |  |
| 5 | `invoice_number` | varchar(100) | YES | - | `NULL` |  |
| 6 | `delivery_note` | varchar(100) | YES | - | `NULL` |  |
| 7 | `total_cost` | decimal(12,2) | YES | - | `0.00` |  |
| 8 | `payment_status` | enum('unpaid','partial','paid') | YES | - | `'unpaid'` |  |
| 9 | `amount_paid` | decimal(12,2) | YES | - | `0.00` |  |
| 10 | `received_by` | int(10) unsigned | YES | - | `NULL` |  |
| 11 | `notes` | text | YES | - | `NULL` |  |
| 12 | `status` | enum('draft','received','cancelled') | YES | - | `'received'` |  |
| 13 | `created_by` | int(10) unsigned | YES | - | `NULL` |  |
| 14 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 15 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

---
## `uniform_sale_payments`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `sale_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `amount_paid` | decimal(10,2) | NO | - | NULL |  |
| 4 | `payment_date` | datetime | NO | MUL | NULL |  |
| 5 | `payment_method` | enum('cash','bank_transfer','mpesa','other') | NO | - | `'cash'` |  |
| 6 | `reference_no` | varchar(100) | YES | - | `NULL` |  |
| 7 | `receipt_no` | varchar(50) | YES | - | `NULL` |  |
| 8 | `received_by` | int(10) unsigned | YES | - | `NULL` |  |
| 9 | `notes` | text | YES | - | `NULL` |  |
| 10 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |

---
## `uniform_sales`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `student_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `item_id` | int(10) unsigned | NO | MUL | NULL |  |
| 4 | `size` | varchar(20) | NO | - | NULL |  |
| 5 | `quantity` | int(11) | NO | - | `1` |  |
| 6 | `unit_price` | decimal(10,2) | NO | - | NULL |  |
| 7 | `total_amount` | decimal(10,2) | NO | - | NULL |  |
| 8 | `amount_paid` | decimal(10,2) | NO | - | `0.00` |  |
| 9 | `balance_due` | decimal(12,2) | YES | - | `NULL` | STORED GENERATED |
| 10 | `balance` | decimal(10,2) | YES | - | `NULL` | STORED GENERATED |
| 11 | `payment_status` | enum('paid','pending','partial') | NO | MUL | `'pending'` |  |
| 12 | `sale_date` | date | NO | MUL | NULL |  |
| 13 | `received_date` | date | YES | - | `NULL` |  |
| 14 | `receipt_no` | varchar(50) | YES | - | `NULL` |  |
| 15 | `sold_by` | int(10) unsigned | YES | - | `NULL` |  |
| 16 | `notes` | text | YES | - | `NULL` |  |
| 17 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 18 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

---
## `uniform_sales_summary`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `month_year` | date | NO | MUL | NULL |  |
| 3 | `item_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 4 | `total_sales_count` | int(11) | NO | - | `0` |  |
| 5 | `total_sales_amount` | decimal(12,2) | NO | - | `0.00` |  |
| 6 | `total_paid` | decimal(12,2) | NO | - | `0.00` |  |
| 7 | `total_pending` | decimal(12,2) | NO | - | `0.00` |  |
| 8 | `total_partial` | decimal(12,2) | NO | - | `0.00` |  |
| 9 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 10 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

UNIQUE/INDEX: `uk_month_item` (`month_year,item_id`)

---
## `uniform_sizes`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `item_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `size` | varchar(20) | NO | - | NULL |  |
| 4 | `size_label` | varchar(50) | YES | - | `NULL` |  |
| 5 | `size_type` | enum('clothing','waist','shoe','one_size') | YES | - | `'clothing'` |  |
| 6 | `quantity_available` | int(11) | NO | - | `0` |  |
| 7 | `quantity_reserved` | int(11) | NO | - | `0` |  |
| 8 | `quantity_sold` | int(11) | NO | - | `0` |  |
| 9 | `unit_price` | decimal(10,2) | NO | - | NULL |  |
| 10 | `reorder_level` | int(11) | NO | - | `5` |  |
| 11 | `last_restocked` | datetime | YES | - | `NULL` |  |
| 12 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 13 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

UNIQUE/INDEX: `uk_item_size` (`item_id,size`)

---
## `unit_topics`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `unit_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `name` | varchar(255) | NO | - | NULL |  |
| 4 | `description` | text | YES | - | `NULL` |  |
| 5 | `learning_outcomes` | text | YES | - | `NULL` |  |
| 6 | `suggested_activities` | text | YES | - | `NULL` |  |
| 7 | `duration` | int(11) | NO | - | NULL |  |
| 8 | `order_sequence` | int(11) | NO | - | NULL |  |
| 9 | `status` | enum('active','inactive') | NO | MUL | `'active'` |  |
| 10 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 11 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

---
## `user_2fa_backup_codes`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `user_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `code_hash` | varchar(255) | NO | - | NULL |  |
| 4 | `used_at` | datetime | YES | MUL | `NULL` |  |
| 5 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |

---
## `user_2fa_otp_sessions`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `user_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `otp_code` | varchar(8) | NO | - | NULL |  |
| 4 | `otp_type` | enum('login','setup','disable','email','sms') | NO | - | `'login'` |  |
| 5 | `method` | enum('email','sms') | NO | - | NULL |  |
| 6 | `otp_expires_at` | datetime | NO | MUL | NULL |  |
| 7 | `verified` | tinyint(1) | NO | MUL | `0` |  |
| 8 | `attempts` | tinyint(3) unsigned | NO | - | `0` |  |
| 9 | `ip_address` | varchar(45) | YES | - | `NULL` |  |
| 10 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |

---
## `user_invitations`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | bigint(20) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `user_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `staff_id` | int(10) unsigned | YES | - | `NULL` |  |
| 4 | `email` | varchar(100) | NO | MUL | NULL |  |
| 5 | `token_hash` | char(64) | NO | UNI | NULL |  |
| 6 | `status` | enum('pending','accepted','expired','revoked') | NO | - | `'pending'` |  |
| 7 | `expires_at` | datetime | NO | - | NULL |  |
| 8 | `accepted_at` | datetime | YES | - | `NULL` |  |
| 9 | `revoked_at` | datetime | YES | - | `NULL` |  |
| 10 | `created_by` | int(10) unsigned | NO | - | NULL |  |
| 11 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 12 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

UNIQUE/INDEX: `token_hash` (`token_hash`)

---
## `user_login_attempts`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `username` | varchar(50) | NO | MUL | NULL |  |
| 3 | `ip_address` | varchar(45) | NO | - | NULL |  |
| 4 | `attempt_time` | datetime | NO | - | NULL |  |
| 5 | `attempt_status` | enum('success','failed','locked') | NO | MUL | NULL |  |
| 6 | `failure_reason` | varchar(255) | YES | - | `NULL` |  |
| 7 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |

---
## `user_permissions`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `user_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `permission_id` | int(11) | NO | MUL | NULL |  |
| 4 | `permission_type` | enum('grant','deny','override') | NO | MUL | `'grant'` |  |
| 5 | `reason` | varchar(255) | YES | - | `NULL` |  |
| 6 | `granted_by` | int(10) unsigned | YES | MUL | `NULL` |  |
| 7 | `expires_at` | timestamp | YES | MUL | `NULL` |  |
| 8 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 9 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

UNIQUE/INDEX: `unique_user_perm` (`user_id,permission_id`)

---
## `user_roles`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `user_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `role_id` | int(10) unsigned | NO | MUL | NULL |  |
| 4 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |

UNIQUE/INDEX: `uk_user_role` (`user_id,role_id`)

---
## `user_routes`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `user_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `route_id` | int(10) unsigned | NO | MUL | NULL |  |
| 4 | `is_allowed` | tinyint(1) | NO | - | `1` |  |
| 5 | `granted_by` | int(10) unsigned | YES | MUL | `NULL` |  |
| 6 | `reason` | varchar(255) | YES | - | `NULL` |  |
| 7 | `expires_at` | timestamp | YES | - | `NULL` |  |
| 8 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |

UNIQUE/INDEX: `uk_user_route` (`user_id,route_id`)

---
## `users`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `username` | varchar(50) | NO | UNI | NULL |  |
| 3 | `email` | varchar(100) | NO | UNI | NULL |  |
| 4 | `first_name` | varchar(50) | NO | - | NULL |  |
| 5 | `last_name` | varchar(50) | NO | - | NULL |  |
| 6 | `password` | varchar(255) | NO | - | NULL |  |
| 7 | `role_id` | int(10) unsigned | NO | MUL | NULL |  |
| 8 | `status` | enum('active','inactive','suspended','pending') | NO | MUL | `'pending'` |  |
| 9 | `last_login` | datetime | YES | MUL | `NULL` |  |
| 10 | `password_changed_at` | datetime | YES | - | `NULL` |  |
| 11 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 12 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |
| 13 | `failed_login_attempts` | int(11) | YES | - | `0` |  |
| 14 | `account_locked_until` | timestamp | YES | - | `NULL` |  |
| 15 | `password_expires_at` | timestamp | YES | - | `NULL` |  |
| 16 | `force_password_change` | tinyint(1) | YES | - | `0` |  |
| 17 | `is_test_user` | tinyint(1) | NO | - | `0` |  |
| 18 | `two_factor_secret` | varchar(255) | YES | - | `NULL` |  |
| 19 | `two_factor_enabled` | tinyint(1) | NO | - | `0` |  |
| 20 | `two_factor_method` | enum('totp','email','sms') | YES | - | `NULL` |  |
| 21 | `two_factor_verified_at` | datetime | YES | - | `NULL` |  |
| 22 | `backup_codes_generated_at` | datetime | YES | - | `NULL` |  |

UNIQUE/INDEX: `uk_email` (`email`); `uk_username` (`username`)

---
## `user_sessions`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `user_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `session_token` | varchar(255) | NO | UNI | NULL |  |
| 4 | `ip_address` | varchar(45) | YES | - | `NULL` |  |
| 5 | `user_agent` | varchar(255) | YES | - | `NULL` |  |
| 6 | `login_time` | datetime | NO | - | NULL |  |
| 7 | `last_activity` | datetime | NO | - | NULL |  |
| 8 | `logout_time` | datetime | YES | - | `NULL` |  |
| 9 | `session_status` | enum('active','expired','logged_out') | NO | - | `'active'` |  |
| 10 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 11 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

UNIQUE/INDEX: `uk_token` (`session_token`)

---
## `vehicle_fuel_logs`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `vehicle_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `fill_date` | date | NO | - | NULL |  |
| 4 | `liters` | decimal(10,2) | NO | - | NULL |  |
| 5 | `cost_per_liter` | decimal(10,2) | NO | - | NULL |  |
| 6 | `total_cost` | decimal(10,2) | NO | - | NULL |  |
| 7 | `odometer_reading` | int(10) unsigned | NO | - | NULL |  |
| 8 | `filled_by` | int(10) unsigned | NO | MUL | NULL |  |
| 9 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |

---
## `vehicle_maintenance`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `vehicle_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `maintenance_date` | date | NO | - | NULL |  |
| 4 | `description` | text | YES | - | `NULL` |  |
| 5 | `cost` | decimal(10,2) | YES | - | `NULL` |  |
| 6 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 7 | `maintenance_type` | enum('routine','repair','inspection','emergency') | NO | - | NULL |  |
| 8 | `odometer_reading` | int(10) unsigned | YES | - | `NULL` |  |
| 9 | `next_maintenance_date` | date | YES | - | `NULL` |  |
| 10 | `next_maintenance_reading` | int(10) unsigned | YES | - | `NULL` |  |
| 11 | `parts_replaced` | text | YES | - | `NULL` |  |
| 12 | `mechanic_details` | text | YES | - | `NULL` |  |
| 13 | `documents_folder` | varchar(255) | YES | - | `NULL` |  |

---
## `vw_active_students_per_class`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `class_id` | int(10) unsigned | YES | - | `NULL` |  |
| 2 | `class_name` | varchar(50) | YES | - | `NULL` |  |
| 3 | `stream_id` | int(10) unsigned | YES | - | `NULL` |  |
| 4 | `stream_name` | varchar(50) | YES | - | `NULL` |  |
| 5 | `active_students` | bigint(21) | YES | - | `NULL` |  |

---
## `vw_all_school_payments`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `source` | varchar(5) | YES | - | `NULL` |  |
| 2 | `reference` | varchar(100) | YES | - | `NULL` |  |
| 3 | `student_id` | int(10) unsigned | YES | - | `NULL` |  |
| 4 | `amount` | decimal(10,2) | YES | - | `NULL` |  |
| 5 | `transaction_date` | datetime | YES | - | `NULL` |  |
| 6 | `status` | varchar(9) | YES | - | `NULL` |  |
| 7 | `details` | longtext | YES | - | `NULL` |  |

---
## `vw_financial_period_summary`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `period_id` | int(10) unsigned | YES | - | `NULL` |  |
| 2 | `period_name` | varchar(100) | YES | - | `NULL` |  |
| 3 | `start_date` | date | YES | - | `NULL` |  |
| 4 | `end_date` | date | YES | - | `NULL` |  |
| 5 | `status` | enum('active','closed') | YES | - | `NULL` |  |
| 6 | `total_transactions` | bigint(21) | YES | - | `NULL` |  |
| 7 | `reconciled_transactions` | bigint(21) | YES | - | `NULL` |  |
| 8 | `total_amount` | decimal(32,2) | YES | - | `NULL` |  |
| 9 | `reconciled_amount` | decimal(32,2) | YES | - | `NULL` |  |

---
## `vw_inventory_low_stock`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | YES | - | `NULL` |  |
| 2 | `name` | varchar(255) | YES | - | `NULL` |  |
| 3 | `current_quantity` | int(11) | YES | - | `NULL` |  |
| 4 | `minimum_quantity` | int(11) | YES | - | `NULL` |  |
| 5 | `category` | varchar(100) | YES | - | `NULL` |  |

---
## `vw_lesson_plan_summary`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | YES | - | `NULL` |  |
| 2 | `teacher_id` | int(10) unsigned | YES | - | `NULL` |  |
| 3 | `learning_area_id` | int(10) unsigned | YES | - | `NULL` |  |
| 4 | `class_id` | int(10) unsigned | YES | - | `NULL` |  |
| 5 | `unit_id` | int(10) unsigned | YES | - | `NULL` |  |
| 6 | `topic` | varchar(255) | YES | - | `NULL` |  |
| 7 | `subtopic` | varchar(255) | YES | - | `NULL` |  |
| 8 | `objectives` | text | YES | - | `NULL` |  |
| 9 | `resources` | text | YES | - | `NULL` |  |
| 10 | `activities` | text | YES | - | `NULL` |  |
| 11 | `assessment` | text | YES | - | `NULL` |  |
| 12 | `homework` | text | YES | - | `NULL` |  |
| 13 | `lesson_date` | date | YES | - | `NULL` |  |
| 14 | `duration` | int(11) | YES | - | `NULL` |  |
| 15 | `status` | enum('draft','submitted','approved','completed') | YES | - | `NULL` |  |
| 16 | `remarks` | text | YES | - | `NULL` |  |
| 17 | `approved_by` | int(10) unsigned | YES | - | `NULL` |  |
| 18 | `approved_at` | datetime | YES | - | `NULL` |  |
| 19 | `created_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |
| 20 | `updated_at` | timestamp | NO | - | `'0000-00-00 00:00:00'` |  |
| 21 | `teacher_name` | varchar(101) | YES | - | `NULL` |  |
| 22 | `learning_area_name` | varchar(100) | YES | - | `NULL` |  |
| 23 | `class_name` | varchar(50) | YES | - | `NULL` |  |
| 24 | `unit_name` | varchar(255) | YES | - | `NULL` |  |
| 25 | `topic_name` | varchar(255) | YES | - | `NULL` |  |

---
## `vw_outstanding_fees`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `student_id` | int(10) unsigned | YES | - | `NULL` |  |
| 2 | `first_name` | varchar(50) | YES | - | `NULL` |  |
| 3 | `last_name` | varchar(50) | YES | - | `NULL` |  |
| 4 | `parent_id` | int(10) unsigned | YES | - | `NULL` |  |
| 5 | `parent_first` | varchar(50) | YES | - | `NULL` |  |
| 6 | `parent_last` | varchar(50) | YES | - | `NULL` |  |
| 7 | `outstanding_balance` | decimal(32,2) | YES | - | `NULL` |  |

---
## `vw_upcoming_activities`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | YES | - | `NULL` |  |
| 2 | `title` | varchar(255) | YES | - | `NULL` |  |
| 3 | `start_date` | date | YES | - | `NULL` |  |
| 4 | `end_date` | date | YES | - | `NULL` |  |
| 5 | `category` | varchar(100) | YES | - | `NULL` |  |

---
## `vw_user_recent_communications`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `recipient_id` | int(10) unsigned | YES | - | `NULL` |  |
| 2 | `subject` | varchar(255) | YES | - | `NULL` |  |
| 3 | `content` | text | YES | - | `NULL` |  |
| 4 | `type` | enum('email','sms','notification','internal') | YES | - | `NULL` |  |
| 5 | `status` | enum('draft','sent','scheduled','failed') | YES | - | `NULL` |  |
| 6 | `created_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

---
## `web_admission_applications`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `child_full_name` | varchar(100) | NO | - | NULL |  |
| 3 | `child_dob` | date | YES | - | `NULL` |  |
| 4 | `child_gender` | enum('male','female','other') | YES | - | `NULL` |  |
| 5 | `child_nationality` | varchar(50) | YES | - | `'Kenyan'` |  |
| 6 | `child_prev_school` | varchar(255) | YES | - | `NULL` |  |
| 7 | `child_prev_grade` | varchar(50) | YES | - | `NULL` |  |
| 8 | `parent_name` | varchar(100) | NO | - | NULL |  |
| 9 | `parent_relationship` | varchar(50) | YES | - | `NULL` |  |
| 10 | `parent_id_number` | varchar(50) | YES | - | `NULL` |  |
| 11 | `parent_phone` | varchar(30) | NO | - | NULL |  |
| 12 | `parent_alt_phone` | varchar(30) | YES | - | `NULL` |  |
| 13 | `parent_email` | varchar(200) | YES | - | `NULL` |  |
| 14 | `parent_address` | varchar(255) | YES | - | `NULL` |  |
| 15 | `grade_applying` | varchar(20) | NO | MUL | NULL |  |
| 16 | `boarding_preference` | enum('day','full_boarding','weekly_boarding') | YES | - | `'day'` |  |
| 17 | `preferred_start` | varchar(50) | YES | - | `NULL` |  |
| 18 | `referral_source` | varchar(100) | YES | - | `NULL` |  |
| 19 | `special_needs` | text | YES | - | `NULL` |  |
| 20 | `application_ref` | varchar(20) | NO | UNI | NULL |  |
| 21 | `ip_address` | varchar(45) | YES | - | `NULL` |  |
| 22 | `status` | enum('new','reviewed','approved','rejected') | YES | MUL | `'new'` |  |
| 23 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 24 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

UNIQUE/INDEX: `uk_application_ref` (`application_ref`)

---
## `workflow_definitions`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `code` | varchar(50) | NO | UNI | NULL |  |
| 3 | `name` | varchar(100) | NO | - | NULL |  |
| 4 | `description` | text | YES | - | `NULL` |  |
| 5 | `category` | enum('academic','administrative','financial','student_affairs','staff_affairs','general') | NO | - | NULL |  |
| 6 | `handler_class` | varchar(255) | NO | - | NULL |  |
| 7 | `config_json` | longtext | YES | - | `NULL` |  |
| 8 | `is_active` | tinyint(1) | NO | - | `1` |  |
| 9 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 10 | `updated_at` | timestamp | NO | - | `current_timestamp()` | on update current_timestamp() |

UNIQUE/INDEX: `uk_workflow_code` (`code`)

---
## `workflow_history`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `workflow_id` | int(10) unsigned | NO | - | NULL |  |
| 3 | `stage` | varchar(100) | NO | - | NULL |  |
| 4 | `action` | varchar(100) | NO | - | NULL |  |
| 5 | `performed_by` | int(10) unsigned | NO | - | NULL |  |
| 6 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |

---
## `workflow_instances`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `workflow_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `reference_type` | varchar(50) | NO | - | NULL |  |
| 4 | `reference_id` | int(10) unsigned | NO | - | NULL |  |
| 5 | `current_stage` | varchar(50) | NO | - | NULL |  |
| 6 | `stage_code` | varchar(50) | YES | - | `NULL` |  |
| 7 | `status` | enum('pending','in_progress','completed','cancelled','error') | NO | - | `'pending'` |  |
| 8 | `started_by` | int(10) unsigned | NO | MUL | NULL |  |
| 9 | `started_at` | timestamp | NO | - | `current_timestamp()` |  |
| 10 | `completed_at` | timestamp | YES | - | `NULL` |  |
| 11 | `data_json` | longtext | YES | - | `NULL` |  |

---
## `workflow_notifications`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `instance_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `notification_type` | enum('stage_entry','stage_complete','stage_timeout','action_required','error') | NO | - | NULL |  |
| 4 | `user_id` | int(10) unsigned | NO | MUL | NULL |  |
| 5 | `title` | varchar(200) | NO | - | NULL |  |
| 6 | `message` | text | NO | - | NULL |  |
| 7 | `is_read` | tinyint(1) | NO | - | `0` |  |
| 8 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |
| 9 | `read_at` | timestamp | YES | - | `NULL` |  |

---
## `workflow_stage_history`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `instance_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `stage_code` | varchar(50) | YES | - | `NULL` |  |
| 4 | `from_stage` | varchar(50) | NO | - | NULL |  |
| 5 | `to_stage` | varchar(50) | NO | - | NULL |  |
| 6 | `action_taken` | varchar(100) | NO | - | NULL |  |
| 7 | `processed_by` | int(10) unsigned | NO | MUL | NULL |  |
| 8 | `processed_at` | timestamp | NO | - | `current_timestamp()` |  |
| 9 | `remarks` | text | YES | - | `NULL` |  |
| 10 | `data_json` | longtext | YES | - | `NULL` |  |

---
## `workflow_stage_permissions`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `workflow_stage_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `permission_id` | int(11) | NO | MUL | NULL |  |
| 4 | `role_id` | int(10) unsigned | YES | MUL | `NULL` |  |
| 5 | `can_view` | tinyint(1) | NO | - | `1` |  |
| 6 | `can_process` | tinyint(1) | NO | - | `0` |  |
| 7 | `can_approve` | tinyint(1) | NO | - | `0` |  |
| 8 | `is_responsible` | tinyint(4) | YES | - | `0` |  |
| 9 | `required_count` | int(11) | YES | - | `1` |  |
| 10 | `created_at` | timestamp | NO | - | `current_timestamp()` |  |

FKs: `workflow_stage_id` → `workflow_stages`.`id`; `role_id` → `roles`.`id`; `permission_id` → `permissions`.`id`

UNIQUE/INDEX: `uq_stage_perm_role` (`workflow_stage_id,permission_id,role_id`)

---
## `workflow_stages`

| # | Column | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|---|
| 1 | `id` | int(10) unsigned | NO | PRI | NULL | auto_increment |
| 2 | `workflow_id` | int(10) unsigned | NO | MUL | NULL |  |
| 3 | `code` | varchar(50) | NO | - | NULL |  |
| 4 | `name` | varchar(100) | NO | - | NULL |  |
| 5 | `required_permission` | varchar(255) | YES | - | `NULL` |  |
| 6 | `responsible_role_ids` | longtext | YES | - | `NULL` |  |
| 7 | `description` | text | YES | - | `NULL` |  |
| 8 | `sequence` | int(11) | NO | - | NULL |  |
| 9 | `required_role` | varchar(50) | YES | - | `NULL` |  |
| 10 | `allowed_transitions` | longtext | NO | - | NULL |  |
| 11 | `action_config` | longtext | YES | - | `NULL` |  |
| 12 | `timeout_hours` | int(11) | YES | - | `NULL` |  |
| 13 | `is_active` | tinyint(1) | NO | - | `1` |  |

UNIQUE/INDEX: `uk_workflow_stage` (`workflow_id,code`)

---
