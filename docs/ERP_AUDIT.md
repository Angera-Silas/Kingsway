# Kingsway Preparatory School — Complete ERP Audit

**Target:** Kenyan CBE/CBC primary school (PP1–PP2 → Grade 1–6 → JSS 7–9 → SSS 10–12)
**Date:** 2026-07-30 (Updated with 4-phase deep analysis)
**System surface:** 504 DB tables, 91 views, 150 stored procedures, 47 API controllers (~1,000+ endpoints), 350 PHP pages, ~200+ JS controllers, 28 services, 10 middleware

## 4-Phase Audit Methodology

This audit applies **four phases** to every module discovered in the system:

| Phase | What It Answers | How It Maps to This Document |
|-------|-----------------|------------------------------|
| **1. ERP Capability Inventory** | What must this module support for a real Kenyan CBC primary school? | → "What a Kenyan primary school needs" section |
| **2. End-to-End Workflow Tracing** | What is the exact user journey, and which files/controllers/DB tables participate at each step? | → "Workflow Trace" subsection (NEW) — traces a real user action from page → JS → API → controller → DB → back |
| **3. Evidence-Based Classification** | What is the real state: missing, scaffold-only, partial, not integrated, functional, or production-ready? | → "Assessment" with 6-tier icon classification |
| **4. Real-Life Acceptance Testing** | Can an actual school user complete the critical workflow successfully? Does data persist, permissions work, state behave correctly? | → "Acceptance Test" subsection (NEW) — verifies each step of the workflow actually works end-to-end |

## Classification Key

| Icon | Meaning |
|------|---------|
| 🟥 **Missing** | Feature does not exist at any layer |
| 🟧 **UI/Scaffold only** | Page exists but is HTML shell, no working JS/API/DB |
| 🟨 **Partially implemented** | Some layers exist but critical path broken |
| 🟦 **Implemented but not integrated** | Code exists but not wired end-to-end (e.g. no UI for existing API) |
| 🟩 **End-to-end functional** | Works from UI → API → DB → back to UI (static analysis) |
| 🟢 **Production ready** | Verified at runtime, error handling, permissions, audit trail |

---

## A. SCHOOL FOUNDATION

### A1. Academic Years & Terms

**What a Kenyan primary school needs:** Academic year setup (Jan–Dec or Jan–Nov), 3 terms with exact dates, term status (active/archived), current term tracking, year rollover.

**What exists:**
- Controller: `AcademicController` — 15 year/term methods (`getYearsList`, `getTermsList`, `putYearsSetCurrent`, etc.)
- API: `AcademicAPI` → `AcademicYearManager`, `AcademicYearService`, `AcademicYearTransitionWorkflow`
- DB: `academic_years` (id, name, start_date, end_date, status), `terms` (id, academic_year_id, name, start_date, end_date, status)
- JS: `academic_years.js`, `manage_terms.js`, `current_academic_year.js`
- Pages: `academic_years.php`, `manage_terms.php`, `term_dates.php`

**Assessment:** 🟩 **End-to-end functional**
- Full CRUD with status lifecycle
- Year rollover workflow (`AcademicYearTransitionWorkflow`) with competency baseline migration
- Multiple frontend controllers

**What's missing:** None identified for basic ops.

---

### A2. Classes & Streams

**What a Kenyan primary school needs:** Class definition per grade level (PP1, PP2, Grade 1–6, JSS 7–9), stream creation per class (e.g., Grade 4 East, Grade 4 West), class capacity tracking, class teacher assignment.

**What exists:**
- Controller: `AcademicController` — `getClassesList`, `postClassesCreate`, `putClassesUpdate`, `deleteClassesDelete`, `postClassesAssignTeacher`, `postStreamsCreate`, `getStreamsList`, etc.
- API: `AcademicAPI`
- DB: `classes`, `streams` tables
- JS: `manage_classes.js`, `class_streams.js`, `all_classes.js`
- Pages: `manage_classes.php`, `class_streams.php`, `class_capacity.php`, `all_classes.php`

**Assessment:** 🟩 **End-to-end functional**
- Full class/stream CRUD with teacher assignment
- Capacity tracking

---

### A3. Learning Areas (CBC Subjects)

**What a Kenyan primary school needs:**
- Grade 1–3: Literacy, Kiswahili, English, Math, Environmental, Hygiene & Nutrition, CRE, Creative Arts, PE
- Grade 4–6 adds: Science & Tech, Agriculture, Home Science, Social Studies, Creative Arts (strand-specific)
- JSS 7–9: expanded learning areas per CBC
- Strands → Sub-strands → Specific Learning Outcomes per learning area
- Core competencies (7) mapped to learning areas

**What exists:**
- DB: 49 learning areas (Playgroup–Grade 9), 149 strands, 36 sub-strands, 8 core competencies, `learning_outcomes` table
- Controller: `AcademicController` — `getLearningAreasList`, `postLearningAreasCreate`, `getCoreCompetenciesList`, `getStrands`
- API: `AcademicAPI` → `AcademicCurriculumService`, `CurriculumPlanningWorkflow`
- JS: `learning_areas.js`, `curriculum_cbc.js`, `view_syllabus.js`
- Pages: `learning_areas.php`, `curriculum_cbc.php`, `view_syllabus.php`

**Assessment:** 🟩 **End-to-end functional for learning areas** but...

**Workflow Trace (Phase 2) — Teacher views curriculum for a class:**
```
1. Teacher navigates → `curriculum_cbc.php` (page loads HTML shell)
2. JS: `curriculum_cbc.js` → `CurriculumCBCController.init()` 
3. API call: `API.academic.getLearningAreas()` → GET /academics/learning-areas
4. Controller: `AcademicController::getLearningAreasList()` → queries `learning_areas` table
5. API call: `API.academic.getStrands()` → GET /academics/strands/{learning_area_id}
6. Controller: `AcademicController::getStrands()` → queries `strands` table (149 rows)
7. Sub-strands: NO DIRECT API — only available via denormalized `/curriculum` endpoint
8. Learning outcomes: NO DIRECT API — `learning_outcomes` table accessed only through assessment FK
```
**Bottleneck:** Sub-strands (36 for 149 strands = 24% coverage) and outcomes have no independent management path. A teacher creating a scheme of work can pick a strand but cannot browse sub-strands and outcomes in isolation.

**Acceptance Test (Phase 4) — Grade 4 teacher checks available learning areas:**
| Step | Expected | Actual | Status |
|------|----------|--------|--------|
| View learning areas page | `learning_areas.php` renders | 274-line full page with JS controller | ✅ |
| Filter by grade/class | Dropdown filters exist in JS | `learning_areas.js` has filter methods | ✅ |
| See strands for a learning area | Strands listed below learning area | 149 strands loaded from DB via API | ✅ |
| See sub-strands for a strand | Sub-strands expandable | Only 36 exist; **no CRUD to add more** | ❌ |
| See learning outcomes for a sub-strand | Outcomes listed | `learning_outcomes` table exists but **no API endpoint** | ❌ |
| Edit sub-strand details | Inline edit or modal | **No sub-strand management UI** at all | ❌ |

**Critical gaps:**
- 🟥 **Sub-strand management** — only 36 exist for 149 strands; no dedicated sub-strand CRUD API endpoint or UI. 113 strands have zero sub-strands.
- 🟥 **Learning outcome management** — `learning_outcomes` table exists (FK from `assessments`) but no UI or API to manage them independently
- 🟥 **Strand → Competency crosswalk** — no bridge table linking strands to core competencies. The `core_competencies` table has `learning_outcomes` column but this is a denormalized text field, not a proper FK relationship
- 🟦 **Curriculum mapping** — `CurriculumPlanningWorkflow` exists but integrates with lesson planning, not standalone curriculum mapping UI
- 🟨 **Sub-strand data** — only available via denormalized `/curriculum` or `/syllabus` endpoints, not independently manageable

---

### A4. Grading & Rubrics (CBC Assessment Framework)

**What a Kenyan primary school needs:**
- EE/ME/AE/BE performance levels with configurable thresholds
- Per-learning-outcome rubrics (4-level descriptors: Exceeding, Meeting, Approaching, Below)
- Grade rules → grade points → final grade calculation
- 40% formative + 60% summative weighting
- CA (Classroom Assessment) / SBA (School-Based Assessment) / SA (Summative Assessment) classification

**What exists:**
- DB: `performance_levels_cbc` (EE≥75, ME=60–74, AE=40–59, BE<40), `grade_rules` (8 sub-levels), `grading_scales`, `grading_comments`, `assessment_rubrics` (4-level descriptors per tool), `assessment_type_classifications` (CA/SBA/SA)
- API: `ExaminationWorkflow` (plan → create → submit → logistics → conduct → assign marking → record marks → verify → moderate → compile → approve)
- Controller: `AcademicController` — `postExamsModerateMarks`, `postComputeTermScores`, `postCompetencyRatings`
- JS: `report_cards.js` has `deriveCBCGrade()` (EE≥80, ME≥50, AE≥25, BE<25 — *different thresholds!*)
- Pages: Various assessment/exam pages

**Assessment:** 🟨 **Partially implemented**

**Workflow Trace (Phase 2) — Teacher creates rubric → assesses → grade appears:**
```
1. Teacher wants to create a rubric → NO PAGE EXISTS
   - Permission `assessments_rubric_manage` is registered in menu system
   - `assessment_rubrics` table has 4-level descriptor columns ready
   - `assessment_tools` table links to rubrics
   - BUT: no page, no JS controller, no API endpoints for rubric CRUD

2. Teacher records student marks → `enter_marks.php`:
   → JS: `enterMarksCtrl` → `API.academic.postFormativeAssessmentMarks()`
   → Controller: `AcademicController::postFormativeAssessmentMarks()`
   → DB: INSERT into `formative_scores` (score, max_score, percentage generated, cbc_grade generated)
   → `cbc_grade` is a GENERATED COLUMN in DB — computes EE/ME/AE/BE at DB level using 75/60/40/40 thresholds

3. Teacher/reporter views grade → `report_cards.php`:
   → JS: `reportCardsCtrl` → calls `deriveCBCGrade()` function
   → **THIS FUNCTION USES DIFFERENT THRESHOLDS** (80/50/25) than DB (75/60/40)
   → Student with 78% gets `EE` from DB but `ME` from JS = **display inconsistency**
```

**Acceptance Test (Phase 4) — Teacher sets up a rubric and grades a student:**
| Step | Expected | Actual | Status |
|------|----------|--------|--------|
| Create a 4-level rubric | Form to create rubric with EE/ME/AE/BE descriptors | **No page exists** — `assessment_rubrics` table is empty unless populated via direct SQL | ❌ |
| Assign rubric to an assessment | Rubric selector in assessment creation | No rubric assignment UI | ❌ |
| Record student marks against rubric criteria | Criteria-level scoring with auto-calc total | Formative scores recorded per-assessment, not per-criterion | ❌ |
| View computed CBC grade (EE/ME/AE/BE) | Grade badge shows correct level | **Conflict:** DB says 78% = EE, JS says 78% = ME. Student sees wrong grade depending on render path | ❌ |
| View result moderation screen | Moderation UI for HoD to adjust/approve marks | **No moderation page** — `postExamsModerateMarks` controller method exists but no frontend | ❌ |

**Critical gaps:**
- 🟥 **No rubric builder UI** — `assessment_rubrics` table exists (4-level descriptors), but no page/API/JS to create or edit rubrics. Permission `assessments_rubric_manage` exists in menu data.
- 🟥 **Threshold inconsistency** — JS `deriveCBCGrade()` uses EE≥80/ME≥50/AE≥25/BE<25 but DB and PHP use EE≥75/ME≥60/AE≥40/BE<40. **A student scoring 78 would get EE from backend but ME from frontend.**
- 🟥 **No result moderation UI** — `postExamsModerateMarks` exists in controller, but no frontend page to moderate results before approval
- 🟦 **Rubric ↔ tool linking** — `assessment_rubrics` table has FKs but no UI to manage the relationship

---

## B. STUDENT LIFECYCLE

### B1. Enquiry → Admission → Registration

**What a Kenyan primary school needs:** Prospect/enquiry capture, application submission, document upload, interview scheduling, placement test, admission decision, fee payment, enrollment, student ID generation, class placement.

**What exists:**
- Controller: `AdmissionController` — 50+ methods covering full workflow (pending applications, submit, verify documents, schedule interview, record results, placement offer, fee payment, enrollment)
- API: `AdmissionAPI`, `StudentAdmissionWorkflow`, `AdmissionStageAuthorization`, `AdmissionPolicy`, `AdmissionPaymentService`
- DB: Multiple admission tables
- JS: `admissions.js`, `admissions_academic_applications.js`, `admissions_admission_decisions.js`, `admissions_headteacher_applications.js`, `admissions_class_placement.js`, `admissions_pending_admission_approvals.js`, `admissions_workspace.js`, `admission_interviews.js`, `new_applications.js`
- Pages: 10+ admission pages

**Assessment:** 🟩 **End-to-end functional**

**Critical gaps:**
- 🟨 **Placement tests** — `placement_tests.js` has documented API TODO comments (`:409`, `:482`)
- 🟦 **Parent portal admission tracking** — `ParentPortalController` exists but admission status visibility for parents unclear

### B2. Student Profile

**What a Kenyan primary school needs:** Student biodata, documents, siblings, medical info, special needs, previous school, transport assignment, guardian information.

**What exists:**
- Controller: `StudentsController` — 80+ methods including `getContextProfile`, `getProfileGet`, `getMedicalGet`, `getSpecialNeeds`, `postPhotoUpload`
- API: `StudentsAPI`, `StudentService`, `StudentParentService`, `StudentPermissionService`, `StudentRepository`, `StudentScopeService`
- DB: Students tables with medical, special needs, family groups
- JS: `student_profile.js`, `student_profile_context.js`, `student_context.js`, `student_health.js`, `special_needs.js`
- Pages: `student_profiles.php`, `student_health.php`, `special_needs.php`

**Assessment:** 🟩 **End-to-end functional**

### B3. Parents & Guardians

**What a Kenyan primary school needs:** Parent registration, link to students, multiple parents per student, parent portal access, fee payment tracking, communication.

**What exists:**
- Controller: `StudentsController` — `getParentsGet`, `getParentsList`, `postParentsAdd`, `postParentsCreate`, `getMyChildren`
- `ParentPortalController` — `postLogin`, `getDashboard`, `getStudentFees`, `getStudentPaymentHistory`, `getStudentStatement`
- API: `StudentParentService`
- DB: Parent/guardian tables
- JS: `all_parents.js`, `parent_feedback.js`
- Pages: `all_parents.php`, `parent_meetings.php`, `parent_feedback.php`

**Assessment:** 🟩 **End-to-end functional for basic ops**

**Critical gaps:**
- 🟨 **Parent portal** — login exists, dashboard exists, but full parent experience (report card viewing, communication, fee payment tracking) not verified end-to-end

### B4. Student Transfers

**What a Kenyan primary school needs:** Transfer between classes, streams, or schools; eligibility checks, approval workflow, document trail, history.

**What exists:**
- Controller: `StudentsController` — `postTransferStartWorkflow`, `postTransferVerifyEligibility`, `postTransferApprove`, `postTransferExecute`, `getTransferWorkflowStatus`, `getTransferHistory`
- API: `TransferWorkflow`
- DB: Transfer tables

**Assessment:** 🟩 **End-to-end functional**

### B5. Student Promotions

**What a Kenyan primary school needs:** Promotion to next grade at year-end, multiple promotion modes (single, batch, entire class), CBE competency baseline migration, graduation at Grade 9, alumni tracking.

**What exists:**
- Controller: `StudentsController` — `postPromotionSingle`, `postPromotionMultiple`, `postPromotionEntireClass`, `postPromotionMultipleClasses`, `postPromotionGraduateGrade9`, `getPromotionBatches`, `getPromotionHistory`, `getAlumniGet`
- API: `PromotionManager`, `StudentPromotionWorkflow`, `StudentPromotionService`, `AcademicYearTransitionWorkflow`
- DB: Promotion tables, alumni

**Assessment:** 🟩 **End-to-end functional**

### B6. Alumni

**What exists:**
- Controller: `StudentsController` — `getAlumniGet`
- DB: Alumni data

**Assessment:** 🟧 **UI/Scaffold only** — no dedicated page or JS controller for alumni management

---

## C. STAFF LIFECYCLE

### C1. Staff Profile & Onboarding

**What a Kenyan primary school needs:** Staff record creation, profile with qualifications, employment details, department assignment, staff ID, onboarding workflow, document upload, probation reviews.

**What exists:**
- Controller: `StaffController` — 60+ methods (`postStaff`, `getProfileGet`, `postUploadPhoto`, `postUploadDocument`, `getLifecycle`, `postOnboarding`, `putOnboarding`, `postOnboardingDocument`)
- `StaffLifecycleController` — full lifecycle workflow (hire → active → leave → terminate)
- `StaffMigrationController` — batch import, onboarding, invitations
- API: `StaffAPI`, `StaffService`, `StaffOnboardingManager`, `StaffOnboardingWorkflow`, `StaffMigrationService`
- JS: `staff.js`, `staff_lifecycle.js`, `staff_production_ui.js`, `complete_staff_profile.js`, `staff_appointments.js`
- Pages: `manage_staff.php`, `staff_lifecycle.php`, `staff_onboarding.php`, `staff_id_cards.php`

**Assessment:** 🟩 **End-to-end functional**

### C2. Staff Appointments & Assignments

**What a Kenyan primary school needs:** Appointment type (permanent, contract, intern), teaching vs non-teaching, department assignment, learning area/subject assignment, class assignment, timetable integration.

**What exists:**
- Controller: `StaffController` — `postAssignClass`, `postAssignSubject`, `getAssignmentsGet`, `getAssignmentsCurrent`
- `StaffAppointmentsController` — internal hiring, new appointments, approval/rejection
- API: `StaffAssignmentManager`, `AssignmentWorkflow`, `StaffTeachingAssignmentService`
- DB: Staff appointments, assignments tables

**Assessment:** 🟩 **End-to-end functional**

### C3. Leave Management

**What a Kenyan primary school needs:** Leave types (annual, sick, study, maternity/paternity), application workflow, approval chain, balance tracking, substitute assignment.

**What exists:**
- Controller: `StaffController` — `getLeavesList`, `postLeavesApply`, `putLeavesUpdateStatus`, `postLeaveInitiateRequest`, `getLeaveBalance`, `getLeaveRequests`, `postLeaveRequests`, `putLeaveRequestsStatus`
- API: `StaffLeaveManager`, `LeaveWorkflow`
- DB: Leave tables

**Assessment:** 🟩 **End-to-end functional**

### C4. Staff Attendance

**What a Kenyan primary school needs:** Daily staff check-in/out, staff register, department attendance, leave integration, late arrival tracking.

**What exists:**
- Controller: `StaffController` — `getAttendanceGet`, `postAttendanceMark`
- `AttendanceController` — `getStaffToday`, `getStaffHistory`, `getStaffSummary`, `getDepartmentAttendance`, `getStaffPercentage`, `postMarkStaff`, `getStaffRegisterContext`
- API: `AttendanceStaffService`, `StaffAttendanceManager`
- DB: Staff attendance tables, `vw_staff_daily_register` view
- JS: `staff_attendance.js`, `my_attendance.js`
- Pages: `staff_attendance.php`, `my_attendance.php`, `attendance_trends.php`

**Assessment:** 🟩 **End-to-end functional**

### C5. Payroll

**What a Kenyan primary school needs:** Salary structure, allowances, deductions (NHIF, NSSF, PAYE, HELB), payslip generation, bank file export, P9 form, salary advances, loan management.

**What exists:**
- Controller: `FinanceController` — 30+ payroll methods: `getPayrollsList`, `postPayrollsCreateDraft`, `postPayrollsCalculate`, `postPayrollsVerify`, `postPayrollsApprove`, `postPayrollsProcess`, `postPayrollsDisburse`, `getDetailedPayslip`, `getAllowanceTemplates`, `postPayrollRequestAdvance`, `postPayrollApplyLoan`, `getPayrollDownloadP9`, `getPayrollDownloadPayslip`
- API: `PayrollWorkflow`, `AllowanceTemplateAPI`
- DB: Payroll, allowances, deductions tables
- JS: `payroll.js`, `payroll_manager.js`, `detailed_payslip.js`
- Pages: `manage_payrolls.php`, `detailed_payslip.php`, `salary_advances.php`

**Assessment:** 🟩 **End-to-end functional for basic payroll**

**Critical gaps:**
- 🟨 **NHIF/NSSF/PAYE auto-calculation** — controller methods exist for calculation but accuracy against 2026 KRA rates unknown
- 🟨 **Bank file export** — not verified if bank-compatible format (e.g., JSON/CSV for EFT)
- 🟦 **P9 generation** — `getPayrollDownloadP9` exists but generation process unclear

### C6. Staff Performance

**What a Kenyan primary school needs:** Performance reviews, lesson observation, student performance metrics, goal tracking, promotion criteria.

**What exists:**
- Controller: `StaffController` — `getPerformanceReviewHistory`, `getPerformanceGenerateReport`, `getPerformanceAcademicKpiSummary`, `postPerformanceReviews`, `putPerformanceReviews`
- API: `StaffPerformanceManager`, `EvaluationWorkflow`
- DB: Performance review tables
- JS: `staff_performance.js`, `staff_performance_overview.js`, `teacher_performance_reviews.js`
- Pages: `teacher_performance_reviews.php`, `staff_performance.php`

**Assessment:** 🟩 **End-to-end functional**

### C7. Staff Separation

**What a Kenyan primary school needs:** Resignation, termination, retirement, offboarding checklist, final dues calculation, exit interview.

**What exists:**
- Controller: `StaffController` — `getOffboarding`, `postOffboarding`, `putOffboarding`, `getUpcomingRetirements`
- `StaffLifecycleController` — complete lifecycle from hire to separation
- DB: Offboarding tables
- JS: `staff_lifecycle.js`

**Assessment:** 🟩 **End-to-end functional**

---

## D. ACADEMIC OPERATIONS (CBE/CBC)

### D1. Teacher Assignment to Classes & Learning Areas

**What a Kenyan primary school needs:** Class teacher (homeroom) assignment, subject teacher assignment per learning area per class, timetable integration, workload balancing.

**What exists:**
- Controller: `AcademicController` — `postClassesAssignTeacher`, `getTeachersClasses`, `getTeachersSubjects`, `postClassTeachers`, `putClassTeachers`, `postSubjectAssignments`, `putSubjectAssignments`, `getSubjectTeachers`, `guardTeachingAssignments`
- API: `AcademicAPI`, `StaffTeachingAssignmentService`
- DB: Class teacher, subject assignment tables
- JS: `assign_class_teachers.js`, `assign_subjects_to_teachers.js`, `subject_teachers.js`, `class_teachers.js`
- Pages: `assign_class_teachers.php`, `assign_subjects_to_teachers.php`, `subject_teachers.php`, `class_teachers.php`

**Assessment:** 🟩 **End-to-end functional**

### D2. Timetable

**What a Kenyan primary school needs:** Period-based timetable per class, teacher timetable view, conflict detection, room assignment, substitute scheduling, timetable export, break/lunch periods.

**What exists:**
- Controller: `SchedulesController` — `getTimetableGet`, `postTimetableCreate`, `putTimetableUpdate`, `deleteTimetableDelete`, `getTimetableCheckConflicts`, `postTimetableReportConflict`, `getTimetableTimeSlots`, `getMasterSchedule`, `getAdminTermOverview`, `postDefineTermDates`, `postDetectScheduleConflicts`, `postStartSchedulingWorkflow`, `postAdvanceSchedulingWorkflow`
- API: `SchedulesAPI`, `SchedulesManager`, `SchedulesWorkflow`, `TermHolidayManager`
- JS: `manage_timetable.js`, `timetable.js`, `student_schedule_extension.js`
- Pages: `manage_timetable.php`, `timetable.php`, `exam_schedule.php`

**Assessment:** 🟩 **End-to-end functional**

**Critical gaps:**
- 🟨 **Scheduling workflow** — 12-stage workflow exists in `SchedulesWorkflow` but UI integration unclear
- 🟦 **Student timetable view** — `getStudentSchedules` exists, but student-facing timetable not verified

### D3. Lesson Plans

**What a Kenyan primary school needs:** Lesson plan creation per learning area/strand/class, CBC-aligned format (learning outcomes, competencies, resources, assessment), approval workflow, draft/submit/approve/reject lifecycle, version history.

**What exists:**
- Controller: `AcademicController` — `postLessonPlansCreate`, `getLessonPlansList`, `putLessonPlansUpdate`, `postLessonPlansApprove`, `postLessonPlansReject`, `postLessonPlansSubmit`, `putLessonPlansReview`, `putLessonPlansBulkApprove`
- API: `CurriculumPlanningWorkflow`
- DB: `lesson_plans` table with approval workflow
- JS: `manage_lesson_plans.js`, `lesson_plan_approval.js`, `lesson_plans_by_class.js`, `lesson_plans_by_teacher.js`, `all_lesson_plans.js`
- Pages: `manage_lesson_plans.php`, `lesson_plan_approval.php`, `lesson_plans_by_class.php`, `lesson_plans_by_teacher.php`, `all_lesson_plans.php`

**Assessment:** 🟩 **End-to-end functional**

### D4. Schemes of Work

**What a Kenyan primary school needs:** Termly SOW per learning area/class/teacher, strand/sub-strand mapping, learning outcomes, assessment methods, resources, approval workflow.

**What exists:**
- Controller: `AcademicController` — `postSchemeOfWorkCreate`, `getSchemesOfWork`, `putSchemesOfWork`, `postSchemesOfWorkApprove`, `postSchemesOfWorkReject`, `getSchemeOfWorkGet`
- API: `CurriculumPlanningWorkflow`
- DB: `schemes_of_work` table
- JS: `schemes_of_work.js`, `my_schemes_of_work.js`, `subject_schemes_of_work.js`
- Pages: `schemes_of_work.php`, `view_syllabus.php`

**Assessment:** 🟩 **End-to-end functional**

### D5. Formative Assessment (Classroom Continuous Assessment)

**What a Kenyan primary school needs:** Per-strand/sub-strand assessment creation, homework, quizzes, projects, practical, observation, oral assessments, rubric alignment, learning outcome linkage, 40% contribution to final score.

**What exists:**
- Controller: `AcademicController` — `getFormativeAssessments`, `postFormativeAssessments`, `getFormativeAssessmentMarks`, `postFormativeAssessmentMarks`, `getFormativeSummary`
- API: `AcademicAssessmentWorkflow`
- DB: `formative_scores` table (has `cbc_grade` generated column), `assessment_types` (16 types), `assessments` table with `learning_outcome_id` FK
- JS: `formative_assessments.js`
- Pages: `formative_assessments.php`

**Assessment:** 🟩 **End-to-end functional for basic formative assessment**

**Workflow Trace (Phase 2) — Teacher creates a formative assessment and records scores:**
```
1. Teacher → `formative_assessments.php` 
2. JS: `fAssCtrl.init()` → loads classes, subjects, students via API
3. Teacher clicks "Create Assessment":
   → API: `API.academic.postFormativeAssessments()` → POST /academics/formative-assessments
   → Controller: `AcademicController::postFormativeAssessments()`
   → DB: INSERT into `assessments` (class_id, subject_id, term_id, title, max_marks, learning_outcome_id, type=formative)
   
4. Teacher enters scores for each student:
   → API: `API.academic.postFormativeAssessmentMarks()` → POST /academics/formative-assessment-marks
   → Controller: `AcademicController::postFormativeAssessmentMarks()`
   → DB: INSERT into `formative_scores` (assessment_id, student_id, score, max_score)
   → DB auto-computes: `percentage` (generated), `cbc_grade` (generated column using 75/60/40 threshold)

5. Teacher views summary:
   → API: `API.academic.getFormativeSummary()` → GET /academics/formative-summary
   → Controller: `AcademicController::getFormativeSummary()`
   → Aggregates from `formative_scores` table
   → **BUT: Summary is per-learning-area, NOT per-strand as CBC requires**
```

**Acceptance Test (Phase 4) — Grade 4 teacher records daily formative scores:**
| Step | Expected | Actual | Status |
|------|----------|--------|--------|
| View formative assessments page | Page loads with class/student selectors | `formative_assessments.php` (191 lines) renders with `fAssCtrl` | ✅ |
| Select learning area and strand | Strand selector available | Learning area selector exists; **strand selector not confirmed** | ❓ |
| Create assessment linked to learning outcome | Outcome selector in create form | `assessments.learning_outcome_id` FK exists but **outcome picker UI unknown** | ❓ |
| Enter scores per student | Score input per student, auto-calc percentage | `postFormativeAssessmentMarks` works — scores persist to DB | ✅ |
| See computed CBC grade for each student | EE/ME/AE/BE badge renders correctly | DB generates correct grade; **JS may override with wrong thresholds** | ❌ |
| View summary by strand | Breakdown of performance per strand | Summary is per-learning-area only | ❌ |

**Critical gaps:**
- 🟥 **No rubric UI** — formative assessment should use 4-level rubrics; `assessment_rubrics` table exists but has no management UI
- 🟥 **Threshold inconsistency** — JS displays EE/ME/AE/BE using different thresholds (80/50/25) than backend (75/60/40)
- 🟨 **Per-strand assessment tracking** — formative scores are per-learning-area, not per-strand/sub-strand as CBC requires

### D6. Summative Assessment (End-of-Term/Year Exams)

**What a Kenyan primary school needs:** Exam scheduling, question paper management, logistics (rooms, invigilators), marking assignment, marks recording, verification, moderation, compilation, approval. 60% contribution to final score.

**What exists:**
- Controller: `AcademicController` — 11-stage examination workflow (`postExamsStartWorkflow`, `postExamsCreateSchedule`, `postExamsSubmitQuestions`, `postExamsPrepareLogistics`, `postExamsConduct`, `postExamsAssignMarking`, `postExamsRecordMarks`, `postExamsVerifyMarks`, `postExamsModerateMarks`, `postExamsCompileResults`, `postExamsApproveResults`)
- API: `ExaminationWorkflow` (690 lines)
- DB: Multiple exam tables
- JS: `assessments_exams.js`, `exam_setup.js`, `exam_schedule.js`, `enter_marks.js`, `enter_exam_results.js`
- Pages: `assessments_exams.php`, `exam_setup.php`, `exam_schedule.php`

**Assessment:** 🟩 **End-to-end functional**

**Critical gaps:**
- 🟥 **No moderation UI** — `postExamsModerateMarks` exists in controller but no frontend page for moderators
- 🟦 **Question paper management** — upload exists but no item bank for reusable questions

### D7. Competency Assessment

**What a Kenyan primary school needs:** Assessment of 7 core competencies per learner, competency rating recording (EE/ME/AE/BE), competency progress tracking over time, evidence/artifact linkage, competency dashboard.

**What exists:**
- DB: `learner_competencies`, `core_competencies` (8 competencies), `competency_checklist` tables, stored procedures (`sp_assess_learner_competency`, `sp_get_competency_report`, `sp_record_core_value`, `sp_record_csl_participation`, `sp_record_pci_awareness`)
- Controller: `AcademicController` — `postCompetencyRatings`, `getCompetencyRatings`, `postCompetencyRecordEvidence`, `postCompetencyRecordCoreValueEvidence`, `getCompetencyDashboard`
- API: `AcademicAPI`
- JS: `competencies_sheet.js`
- Pages: `competencies_sheet.php`, `competency_checklist.php`

**Assessment:** 🟨 **Partially implemented**

**Workflow Trace (Phase 2) — Teacher rates a learner's core competency:**
```
1. Teacher → `competencies_sheet.php`
2. JS: `compCtrl` → loads students, competencies list
3. API: `API.academic.getCoreCompetenciesList()` → GET /academics/core-competencies
   → Controller: `AcademicController::getCoreCompetenciesList()`
   → DB: `core_competencies` (8 rows: Communication, Critical Thinking, Creativity, Citizenship, Digital Literacy, Learning to Learn, Self-Efficacy)
   
4. Teacher selects student + competency → rates EE/ME/AE/BE:
   → API: `API.academic.postCompetencyRatings()` → POST /academics/competency-ratings
   → Controller: `AcademicController::postCompetencyRatings()`
   → DB: INSERT into `learner_competencies` (student_id, competency_id, academic_year, term_id, performance_level_id, evidence, teacher_notes, assessed_by)
   → DB trigger: `trg_log_competency_assessment` fires

5. Teacher records evidence (artifact):
   → API: `API.academic.postCompetencyRecordEvidence()` → POST /academics/competency-record-evidence
   → Controller: `AcademicController::postCompetencyRecordEvidence()`
   → SUPPOSED TO: Insert into `portfolio_artifacts` with competency mapping
   → **BUT: No UI page exists for portfolio artifact upload. Endpoint may be orphaned.**

6. Teacher views competency dashboard:
   → API: `API.academic.getCompetencyDashboard()` → GET /academics/competency-dashboard
   → Controller: `AcademicController::getCompetencyDashboard()`
   → Aggregates from `learner_competencies`
   → **Dashboard UI existence unknown — page link not confirmed**
```

**Acceptance Test (Phase 4) — Teacher assesses all 7 competencies for a Grade 4 learner:**
| Step | Expected | Actual | Status |
|------|----------|--------|--------|
| View competencies sheet | Page with student list and 7 competencies | `competencies_sheet.php` (81 lines) loads with `compCtrl` | ✅ |
| Rate Communication competency | Dropdown: EE/ME/AE/BE per student | `postCompetencyRatings` persists to `learner_competencies` | ✅ |
| Rate Critical Thinking | Same UI flow | Same endpoint, different `competency_id` | ✅ |
| Attach evidence artifact (student work) | File upload + competency mapping link | `postCompetencyRecordEvidence` exists but **no upload UI** | ❌ |
| View student's competency progression (across terms) | Graph showing EE/ME/AE/BE over time | `getCompetencyDashboard` exists but **UI integration unclear** | ❓ |
| Generate competency report card section | Competency summary on report card | Report card renders academic scores; **competency section not confirmed** | ❓ |
| Student/parent sees competency progress | Parent portal shows competency growth | **No parent portal competency view** | ❌ |

**Critical gaps:**
- 🟥 **No portfolio artifact UI** — `portfolio_artifacts` table stores evidence (competency mappings, ratings, reflections, teacher feedback, files) but has **no UI** to upload, view, or manage artifacts
- 🟥 **No portfolio review UI** — `portfolios` table with auto-create trigger on promotion, but no way for teacher/parent to view learner portfolio
- 🟦 **Competency dashboard** — `getCompetencyDashboard` endpoint exists but its UI integration unclear
- 🟥 **No learner-facing competency view** — student cannot see their own competency progression

### D8. Results Compilation & Reporting

**What a Kenyan primary school needs:** 40% formative + 60% summative → EE/ME/AE/BE per learning area, competency ratings, core values, attendance, co-curricular, teacher comments, headteacher comments, parent section.

**What exists:**
- Controller: `AcademicController` — `postComputeTermScores`, `getReportCardData`, `getGradingResults`, `getResultsAnalysis`, `getPerformanceOverview`, `getStudentResults`, `getReportCardsDownload`
- API: `ReportGenerationWorkflow` (559 lines)
- DB: `term_subject_scores` table with 40/60 breakdown
- JS: `report_cards.js`, `add_results.js`, `enter_results.js`, `performance_analysis.js`, `results_analysis.js`
- Pages: `report_cards.php`, `add_results.php`, `enter_results.php`, `performance_analysis.php`

**Assessment:** 🟨 **Partially implemented**

**Critical gaps:**
- 🟥 **Threshold inconsistency** — JS uses EE≥80/ME≥50/AE≥25/BE<25 but DB/PHP uses EE≥75/ME≥60/AE≥40/BE<40. **Same student gets different grades depending on the layer.**
- 🟥 **No moderation → approval → publish workflow UI** — backend chain exists (moderate → compile → approve) but no frontend pages to execute these steps
- 🟥 **No printable CBC report card** — report template generation exists but CBE-format report card (competencies + values + learning areas + comments) not verified
- 🟨 **Parent report card access** — results available but parent portal view not confirmed end-to-end

### D9. Student Portfolio (CBE)

**What a Kenyan primary school needs:** Digital portfolio per learner, artifact upload (assessments, projects, observations), competency mapping, learner reflection, teacher feedback, parent access, progress over time.

**What exists:**
- DB: **Fully modeled** — `portfolios` (student_id, academic_year, portfolio_type, title, theme, status), `portfolio_artifacts` (portfolio_id, artifact_title, artifact_type (assignment/project/photo/video/document/reflection/other), file_path, learner_reflection, teacher_feedback, competency_id, value_id, rating), auto-create trigger `trg_create_portfolio_on_promotion` on promotion
- **No controller methods** for portfolio management
- **No API endpoints** for portfolio CRUD
- **No UI pages** for portfolio viewing
- **No JS controllers** for portfolio
- DB event: `ev_yearly_portfolio_archive_reminder` — yearly reminder to archive portfolios (exists but cannot fire since no data flows in)

**Assessment:** 🟥 **Missing** (DB exists, nothing else)

**Workflow Trace (Phase 2) — What SHOULD happen vs what CAN happen:**
```
SHOULD HAPPEN:
1. Student/teacher uploads artifact → POST /portfolios/{id}/artifacts
2. Artifact stored in `portfolio_artifacts` (file_path, competency mapping, rating)
3. Learner adds reflection → stored in `learner_reflection` column
4. Teacher adds feedback → stored in `teacher_feedback` column
5. Parent views portfolio → GET /parent/portfolio/{student_id}
6. Student sees progression → GET /portfolio/{id}/timeline

CAN ACTUALLY HAPPEN:
1. Nothing — zero front-end or API layer exists
2. The `trg_create_portfolio_on_promotion` trigger fires on promotion → creates empty portfolio row
3. The `ev_yearly_portfolio_archive_reminder` fires yearly → but reminds about empty portfolios
4. `postCompetencyRecordEvidence` controller method may attempt portfolio artifact writes but has no UI trigger
```

**Acceptance Test (Phase 4) — Teacher uploads a student's project artifact:**
| Step | Expected | Actual | Status |
|------|----------|--------|--------|
| Navigate to portfolio page | Link in sidebar or student profile | **No portfolio link exists** in menus | ❌ |
| Upload artifact (PDF/photo) | File picker → upload → stored | **No upload UI, no API endpoint** | ❌ |
| Map artifact to competency | Competency selector in upload form | **No UI** — `portfolio_artifacts.competency_id` FK exists but no way to set it | ❌ |
| Student writes reflection | Text area for learner reflection | **No UI** — `learner_reflection` column unused | ❌ |
| Teacher provides feedback | Text area for teacher feedback | **No UI** — `teacher_feedback` column unused | ❌ |
| Parent views portfolio | Parent portal shows artifacts | **No parent portal portfolio section** | ❌ |
| Portfolio appears on report card | Report card includes portfolio summary | **No report card portfolio integration** | ❌ |

**Critical gaps:** All layers missing except DB. Portfolio is the single largest gap in the CBC implementation.

---

## E. FINANCE

### E1. Fee Structure

**What a Kenyan primary school needs:** Fee items per class/grade (tuition, transport, boarding, meals, uniform, etc.), annual fee structure, term breakdown, approval workflow, activation, rollover.

**What exists:**
- Controller: `FinanceController` — `postFeesCreateAnnualStructure`, `postFeesReviewStructure`, `postFeesApproveStructure`, `postFeesActivateStructure`, `postFeesRolloverStructure`, `getFeeTypesList`, `getStudentTypesList`, `getFeeStructuresList`, `getFeesAnnualSummary`, `getFeesPendingReviews`, `getFeesTermBreakdown`
- API: `FeeManager`, `FeeApprovalWorkflow`
- DB: Fee structure tables
- JS: `fee_structure_admin.js`, `fee_structure_accountant.js`, `fee_structure_viewer.js`, `manage_fees.js`
- Pages: `fee_structure.php` (role-based template loader), `manage_fee_structure.php`

**Assessment:** 🟩 **End-to-end functional**

**Critical gaps:**
- 🟨 **Fee structure per class per term** — exists but per-class fee item configuration not verified

### E2. Student Billing & Invoicing

**What a Kenyan primary school needs:** Invoice generation per student per term, fee obligation assignment (tuition, transport, boarding, etc.), opening balance, discounts, waivers, billing history.

**What exists:**
- Controller: `FinanceController` — `postFeeInvoicesGenerate`, `postFeeInvoicesGenerateBatch`, `getFeeInvoicesGet`, `postFeesActivateGenerateObligations`, `getStudentsBillingHistory`, `getClassBillingReport`
- API: `FinanceAPI`
- DB: Invoices, fee obligations tables
- JS: `student_fees.js`, `fee_defaulters.js`
- Pages: `student_fees.php`, `fee_defaulters.php`, `students_with_balance.php`

**Assessment:** 🟩 **End-to-end functional**

### E3. Payment Collection (M-Pesa/Bank/Cash)

**What a Kenyan primary school needs:** M-Pesa C2B integration (STK Push, callback), bank transfer/webhook, cash payment recording, payment verification, receipt generation, student account credit.

**What exists:**
- Controller: `PaymentsController` — `postMpesaB2cCallback`, `postMpesaB2cTimeout`, `postMpesaC2bConfirmation`, `postKcbValidation`, `postKcbTransferCallback`, `postKcbNotification`, `postBankWebhook`, `getUnmatchedMpesa`, `postImportMpesa`, `postReconcileMpesa`, `getMpesaReconcileHistory`, `getLookupByPhone`, `postLinkStudent`
- `FinanceController` — `postPaymentsGenerateReceipt`, `postPaymentsSendNotification`
- API: `PaymentsAPI`, `PaymentManager`, `PaymentReconciliationAPI`, `PaymentGateway` hooks
- Service: `MpesaPaymentService` (848 lines — full STK Push, B2C, C2B validation/confirmation, account balance, transaction status)
- DB: `mpesa_transactions`, `payment_transactions`, `payment_allocations_detailed`, `payment_webhooks_log`, `payment_security_audit`, `student_fee_obligations`, `student_fee_balances`
- JS: `manage_payments.js`, `mpesa_settlements.js`, `unmatched_payments.js`
- Pages: `manage_payments.php`, `mpesa_settlements.php`, `unmatched_payments.php`

**Assessment:** 🟨 **Partially implemented**

**Workflow Trace (Phase 2) — Parent pays fees via M-Pesa (what SHOULD happen vs what CAN happen):**
```
SHOULD HAPPEN (full flow):

1. Parent logs into portal → sees fee balance
   → `student_portal.php` → `parentPortalController.loadBalanceSummary()`
   → API.parent.getFeeBalance() → ParentPortalController.getFeeBalance()
   
2. Parent clicks "Pay Now" → STK Push sent to their phone
   → POST /finance/mpesa/stk-push → MpesaPaymentService.initiateSTKPush()
   → Safaricom API: /mpesa/stkpush/v1/processrequest
   → Customer receives M-Pesa PIN prompt on phone
   
3. Customer enters PIN → Safaricom sends C2B callback
   → POST /payments/mpesa-c2b-confirmation → PaymentsController.postMpesaC2bConfirmation()
   → Validates signature, checks for duplicates via payment_security_audit
   → Inserts into mpesa_transactions
   → Trigger: trg_mpesa_payment_processed fires
   → Allocates to student fee obligation via sp_allocate_payment
   
4. Accountant reconciles daily
   → /mpesa_settlements.php → unmatched_payments.js
   → POST /payments/reconcile-mpesa → PaymentsController.postReconcileMpesa()
   
CAN ACTUALLY HAPPEN:
1. Parent logs in → sees fee balance ✅
2. Parent clicks "Pay Now" → **BUTTON DOES NOT EXIST** ❌
   ParentPortalController has NO method to initiate STK Push
   MpesaPaymentService::initiateSTKPush() exists but has NO API endpoint and NO UI trigger
3. If a payment arrives (e.g. via Till Number sent manually):
   → postMpesaC2bConfirmation() processes it ✅
   → Auto-allocation stored procedure runs ✅
4. Accountant can reconcile ✅
```

**Acceptance Test (Phase 4) — A parent pays KES 15,000 school fees via M-Pesa:**
| Step | Expected | Actual | Status |
|------|----------|--------|--------|
| Parent views fee balance | Dashboard shows amount due | `parentPortalController.loadBalanceSummary()` works | ✅ |
| Parent clicks "Pay Now" | M-Pesa STK Push sent to phone | **No "Pay Now" button exists in parent portal** | ❌ |
| Parent sends via Till Number manually | SMS sent to Safaricom | This works if Till Number is registered | ✅ |
| System receives C2B callback | Payment recorded automatically | `postMpesaC2bConfirmation()` handles callback | ✅ |
| Payment auto-allocates to fee obligation | Student balance updates | `sp_allocate_payment` runs via trigger | ✅ |
| Receipt generated | Receipt available in portal | `postPaymentsGenerateReceipt` exists | ✅ |
| Parent sees updated balance | Dashboard shows new lower balance | API call returns updated `getFeeBalance()` | ✅ |
| Cashier reconciles M-Pesa statement | Page to match M-Pesa transactions vs bank | `mpesa_reconciliation.php` is **3-line stub** | ❌ |

**Critical gaps:**
- 🟨 **M-Pesa STK Push implemented but sandbox-only** — 848-line `MpesaPaymentService.php` has full `initiateSTKPush()` method wired via `FinanceAPI`. But `MPESA_ENVIRONMENT=sandbox` in config. Needs live credentials to function.
- 🟨 **Payment → student auto-mapping** — `postLinkStudent` exists but phone-number-based matching reliability unknown
- 🟥 **No payment gateway button in parent portal** — parent portal shows fees but has no "Pay Now" button to trigger STK Push. Service exists but isn't exposed from the portal UI.
- 🟦 **KCB/bank integration** — `postKcbValidation`, `postKcbTransferCallback` exist but integration status unknown
- 🟧 **M-Pesa reconciliation page** — `mpesa_reconciliation.php` is 3-line delegation stub

### E4. Student Account & Ledger

**What a Kenyan primary school needs:** Per-student account with opening balance, charges, payments, credits, debits, outstanding balance, payment history, fee statement.

**What exists:**
- Controller: `FinanceController` — `getStudentsPaymentHistory`, `getStudentsPaymentStatus`, `getStudentsFeeStatement`, `getStudentsBalance`, `getCurrentAcademicYear`
- `StudentsController` — `getFeesGet`
- API: `FinanceAPI`, `PaymentManager`
- JS: `student_fees.js`, `balances_by_class.js`
- Pages: `student_fees.php`, `balances_by_class.php`

**Assessment:** 🟩 **End-to-end functional**

### E5. Daily Reconciliation & Audit

**What a Kenyan primary school needs:** Daily M-Pesa/bank/cash reconciliation, unmatched payment resolution, audit trail, cash-up report, receipt verification.

**What exists:**
- Controller: `FinanceController` — `getReconciliationUnreconciled`, `getCashReconciliation`, `postCashReconciliation`, `getExceptionReports`, `putExceptionReports`
- `PaymentsController` — `getUnmatchedMpesa`, `postReconcileMpesa`, `getMpesaReconcileHistory`
- DB: Reconciliation tables
- JS: `cash_reconciliation.js` (likely), `bank_reconciliation.js`
- Pages: `cash_reconciliation.php`, `mpesa_reconciliation.php` (3-line stub)

**Assessment:** 🟨 **Partially implemented**

**Critical gaps:**
- 🟧 **M-Pesa reconciliation page** — `mpesa_reconciliation.php` is a 3-line delegation stub → `mpesa_settlements.php`
- 🟨 **Auto-reconciliation** — manual matching available but auto-reconciliation of known payers not confirmed

### E6. Financial Reporting

**What a Kenyan primary school needs:** Revenue reports (fees, uniform, transport, boarding, meals), expenditure reports, budget vs actual, collection rates, arrears reports, daily/weekly/termly summaries, audit trail.

**What exists:**
- Controller: `ReportsController` — `getFeeSummary`, `getFeePaymentTrends`, `getDiscountStats`, `getArrearsStats`, `getFinancialTransactionsSummary`, `getBankTransactionsSummary`
- `FinanceController` — `getReportsGeneratePayroll`, `getReportsCompareYearlyCollections`
- API: `ReportingManager`, `FinanceReportManager`
- DB: Views (`vw_collection_rate_by_class`, `vw_budget_utilization`)
- JS: `finance_reports.js`, `budget_overview.js`, `comparative_reports.js`
- Pages: `finance_reports.php`, `budget_overview.php`, `comparative_reports.php`

**Assessment:** 🟩 **End-to-end functional for basic reports**

**Critical gaps:**
- 🟨 **Revenue source breakdown** — separate revenue streams (fees vs uniform vs transport vs boarding) not confirmed in reporting

### E7. Expenditure & Budget

**What a Kenyan primary school needs:** Expense categories, purchase orders, approval workflow, budget allocation per department, budget vs actual tracking, supplier payments, utilities, maintenance, operations.

**What exists:**
- Controller: `FinanceController` — `postDepartmentBudgetsPropose`, `getDepartmentBudgetsProposals`, `postDepartmentBudgetsApprove`, `postDepartmentBudgetsAllocate`, `postDepartmentBudgetsRequestFunds`, `getDepartmentBudgetsSummary`, `postExpensesApprove`, `postExpensesReject`, `getExpenses`, `postExpenses`, `putExpenses`, `getExpenseCategories`, `getBudgets`, `postBudgets`, `putBudgets`
- API: `BudgetManager`, `BudgetApprovalWorkflow`, `ExpenseManager`, `ExpenseApprovalWorkflow`
- DB: Budget, expense tables
- JS: `manage_expenses.js`
- Pages: `manage_expenses.php`, `budget_overview.php`

**Assessment:** 🟩 **End-to-end functional**

### E8. Supplier & Vendor Management

**What a Kenyan primary school needs:** Vendor registration, purchase orders, goods received note, payment tracking, outstanding liabilities.

**What exists:**
- Controller: `VendorsController` — `getVendors`, `postVendors`, `getPurchaseOrders`, `postPurchaseOrders`, `putVendors`, `deleteVendors`, `getOutstandingLiabilities`
- `InventoryController` — `getSuppliersList`, `getSuppliersGet`, `postSuppliersCreate`, `putSuppliersUpdate`
- JS: `vendors.js`, `purchase_orders.js`
- Pages: `vendors.php`, `purchase_orders.php`

**Assessment:** 🟩 **End-to-end functional**

---

## F. OPERATIONS

### F1. Attendance (Student)

**What a Kenyan primary school needs:** Daily class attendance marking, absentee tracking, late arrival recording, attendance statistics per student/class/term, parent notification, medical notes, permissions/exeats.

**What exists:**
- Controller: `AttendanceController` — 40+ methods: `getToday`, `getClassAttendance`, `getStudentHistory`, `getStudentSummary`, `getStudentPercentage`, `getTrends`, `getChronicStudentAbsentees`, `postMarkBulk`, `getSessions`, `postMarkSession`, `getAcademicSummary`, `getDailyRegister`
- API: `AttendanceAPI`, `AttendanceStudentService`, `StudentAttendanceManager`, `AttendancePermissionService`
- DB: Attendance tables, `vw_student_attendance_summary` view
- JS: `mark_attendance.js`, `view_attendance.js`, `attendance_trends.js`, `today_absentees.js`, `truancy_cases.js`
- Pages: 15+ attendance pages

**Assessment:** 🟩 **End-to-end functional**

### F2. Transport

**What a Kenyan primary school needs:** Route definition, stops, vehicles, drivers, student assignment to routes, fee collection per route, attendance on bus, incident tracking, route optimization.

**What exists:**
- Controller: `TransportController` — 30+ methods: `getTransportRoute`, `getAllRoutes`, `postTransportRoute`, `getTransportStop`, `getTransportVehicle`, `getTransportDriver`, `postDriverAssign`, `postAssignStudent`, `postWithdrawAssignment`, `getAssignments`, `postRecordPayment`, `getPayments`, `postAttendance`, `postSubscriptions`, `postBillsGenerate`
- API: `TransportAPI`, `RouteManager`, `StopManager`, `VehicleManager`, `DriverManager`, `StudentTransportAssignmentManager`, `StudentTransportPaymentManager`, `StudentTransportStatusManager`, `TransportBillingManager`
- DB: Transport tables
- JS: `transport.js`, `my_routes.js`, `my_vehicle.js`, `transport_passengers.js`
- Pages: `manage_transport.php`, `transport.php`, `transport_passengers.php`

**Assessment:** 🟩 **End-to-end functional**

### F3. Inventory & Procurement

**What a Kenyan primary school needs:** Item catalog, stock levels, low stock alerts, stock movements (in/out/adjustment), suppliers, purchase orders, requisitions, approvals, stock audit, uniform sales.

**What exists:**
- Controller: `InventoryController` — 60+ methods: `getItemsList`, `getItemsWithStock`, `getItemsLowStock`, `getItemsStockValuation`, `getRequisitionsList`, `postRequisitionsCreate`, `postMovementsAdjustStock`, `postMovementsRecord`, `getSuppliersList`, `postSuppliersCreate`, `getPurchaseOrdersList`, `postPurchaseOrdersCreate`, post full procurement workflow (initiate → verify budget → request quotes → evaluate → approve → create PO), full disposal workflow, full transfer workflow, full audit workflow, uniform sales management
- API: `InventoryAPI`, `RequisitionsManager`, `StockMovementsManager`, `StockProcurementWorkflow`, `StockAuditWorkflow`, `StockTransferWorkflow`, `AssetDisposalWorkflow`, `UniformSalesManager`, `CategoriesManager`, `LocationsManager`, `SuppliersManager`, `PurchaseOrdersManager`, `TransactionsManager`
- DB: Inventory tables
- JS: `manage_inventory.js`, `manage_requisitions.js`, `manage_stock.js`, `uniform_sales.js`, `purchase_orders.js`
- Pages: `manage_inventory.php`, `manage_requisitions.php`, `manage_stock.php`, `purchase_orders.php`

**Assessment:** 🟩 **End-to-end functional**

### F4. Library

**What a Kenyan primary school needs:** Book catalog, book issues/returns, overdue tracking, fines, student borrowing history, library card.

**What exists:**
- Controller: `LibraryController` — `getSummary`, `getCategories`, `getBooks`, `postBooks`, `putBooks`, `deleteBooks`, `getIssues`, `postIssues`, `putIssues`, `getOverdue`, `getFines`, `putFines`
- API: `LibraryAPI`, `LibraryManagementWorkflow`
- DB: Library tables
- JS: `library.js`
- Pages: `manage_library.php`

**Assessment:** 🟩 **End-to-end functional**

### F5. Boarding

**What a Kenyan primary school needs:** Dormitory management, bed allocation, roll call, exeat management, boarding discipline, visitor log, inventory (bedding, etc.).

**What exists:**
- Controller: `BoardingController` — `getStats`, `getOccupancy`, `getDormitories`, `postDormitories`, `putDormitories`, `deleteDormitories`, `getStudents`, `getRollCall`, `postRollCall`, `getExeats`, `postExeats`, `putExeats`
- `StudentsController` — `getBoardingMeta`, `getBoardingStudents`, `getBoardingSummary`, `getBoardingStudent`, `postBoardingAssignDorm`, `postBoardingNote`
- DB: Boarding tables
- JS: `boarding_students.js`, `boarding_roll_call.js`
- Pages: `boarding_students.php` (584 lines, robust), `manage_boarding.php`

**Assessment:** 🟩 **End-to-end functional**

### F6. Catering

**What a Kenyan primary school needs:** Menu planning, meal tracking per student, dietary requirements, food inventory, stock usage, cost tracking, special diets.

**What exists:**
- Controller: `CateringController` — `getStats`, `getMenu`, `getFoodStock`
- `StudentsController` — `getCateringBoardingMeta`, `getCateringBoardingStudents`, `getCateringBoardingSummary`, `getCateringBoardingStudent`, `postCateringMenuPlan`, `getCateringFoodRequisition`
- DB: Catering tables
- JS: `catering_boarding_students.js`, `menu_planning.js`, `food_store.js`
- Pages: `catering_boarding_students.php`, `menu_planning.php`, `manage_menus.php` (17 lines), `food_store.php`

**Assessment:** 🟨 **Partially implemented**

**Critical gaps:**
- 🟧 **Menu management page** — `manage_menus.php` is 17 lines
- 🟨 **Dietary requirements tracking** — unclear if special diets are supported
- 🟦 **Meal cost tracking** — not confirmed

### F7. Health / Sick Bay

**What a Kenyan primary school needs:** Sick bay admission/discharge, symptoms recording, medication given, referral tracking, parent notification, vaccination records, medical history.

**What exists:**
- Controller: `HealthController` — `getSummary`, `getRecords`, `postRecords`, `putRecords`, `getSickBay`, `postSickBay`, `putSickBay`, `getVaccinations`, `postVaccinations`
- `StudentsController` — `getHealthMeta`, `getHealthRecords`, `getHealthRecord`
- DB: Health tables
- JS: `sick_bay.js`, `student_health.js`
- Pages: `sick_bay.php` (174 lines, partially marked), `student_health.php`

**Assessment:** 🟩 **End-to-end functional**

### F8. Discipline

**What a Kenyan primary school needs:** Incident recording, case tracking, sanctions, parent notification, conduct grades, counseling referral, resolution workflow.

**What exists:**
- Controller: `StudentsController` — `getDisciplineMeta`, `getDisciplineCases`, `getDisciplineCase`, `putDisciplineCase`, `postDisciplineRecord`, `putDisciplineUpdate`, `postDisciplineResolve`
- DB: Discipline tables
- JS: `discipline_cases.js`, `student_discipline.js`
- Pages: `discipline_cases.php`, `conduct_reports.php`, `behavior_logs.php` (3-line stub), `all_discipline_cases.php` (3-line stub)

**Assessment:** 🟩 **End-to-end functional**

**Critical gaps:**
- 🟧 **Behavior logs page** — 3-line delegation stub
- 🟧 **All discipline cases page** — 3-line delegation stub

### F9. Counseling

**What a Kenyan primary school needs:** Counseling case management, session notes, referrals, follow-up tracking, welfare checks, intervention plans.

**What exists:**
- Controller: `CounselingController` — `getIndex`, `getSummary`, `getSession`, `postSession`, `putSession`, `deleteSession`, `getStats`, `getSessions`
- `StudentsController` — `getCounselingMeta`, `getCounselingCases`, `getCounselingCase`, `postWelfareCase`, `postWelfareCaseNote`, `postWelfareCaseFollowUp`, `postWelfareCaseResolve`, `postWelfareCaseEscalate`, `postCounselingCaseSessionNote`, `postCounselingCaseClose`
- API: `CounselingAPI`
- DB: Counseling tables
- JS: `counseling_records.js`, `student_counseling.js`, `student_welfare.js`
- Pages: `counseling_records.php`, `student_counseling.php`, `student_welfare.php`, `intervention_plans.php`

**Assessment:** 🟩 **End-to-end functional**

### F10. Sports & Co-curricular

**What a Kenyan primary school needs:** Sports teams, fixtures, results, player registration, competitions, clubs/societies, member management, activity scheduling.

**What exists:**
- Controller: `ActivitiesController` — 40+ methods covering full activities/sports lifecycle: categories, participants, resources, schedules, registration, competitions, evaluations
- API: `ActivitiesAPI`, `SportsManager`, `SportsService`, `CategoriesManager`, `ParticipantsManager`, `ResourcesManager`, `SchedulesManager`
- DB: 5 sports tables (migrations/001_create_sports_tables.sql)
- JS: `sports.js`, `manage_activities.js`, `assemblies.js`, `clubs_societies.js`, `competitions.js`
- Pages: `sports.php`, `manage_activities.php`, `assemblies.php`, `clubs_societies.php`, `competitions.php`

**Assessment:** 🟩 **End-to-end functional**

### F11. Chapel / Religious Activities

**What a Kenyan primary school needs:** Chapel service scheduling, attendance, religious education events, assembly management.

**What exists:**
- Controller: `ChapelController` — `index`, `getServices`
- `ActivitiesController` — general activity scheduling
- JS: `chapel_services.js`
- Pages: `chapel_services.php`

**Assessment:** 🟩 **End-to-end functional (basic)**

---

## G. COMMUNICATION

### G1. Internal Messaging

**What a Kenyan primary school needs:** Staff-to-staff messaging, forums, announcements, department communication, request/approval workflows.

**What exists:**
- Controller: `CommunicationsController` — full CRUD for: contacts, inbox/outbound, threads, announcements, internal requests, staff forum topics, staff requests, groups, templates, logs
- API: `CommunicationsAPI`, `InternalCommManager`, `InternalAnnouncementManager`, `ForumManager`, `StaffForumManager`, `StaffRequestManager`, `ContactDirectoryManager`
- DB: Communication tables
- JS: `communications.js`, `manage_communications.js`, `messaging.js`
- Pages: `manage_communications.php`, `messaging.php`, `manage_announcements.php`

**Assessment:** 🟩 **End-to-end functional**

### G2. External Communication (SMS/Email/WhatsApp)

**What a Kenyan primary school needs:** Send SMS to parents/guardians, send email, send WhatsApp messages, template-based messaging, delivery status tracking, retry logic, audit log, provider API integration.

**What exists:**
- Controller: `CommunicationsController` — `postSendSms`, `postSendEmail`, `postSendWhatsapp`, `postSendSmsTemplate`, `postSmsDeliveryReport`, `postSmsOptOutCallback`, `postSmsSubscriptionCallback`, `postFeeReminder`, `postFeeReminderBulk`
- API: `CommunicationWorkflowHandler`, `ExternalInboundManager`
- Services: `MessageService`
- DB: Message logs
- JS: `manage_sms.js`, `manage_email.js`
- Pages: `manage_sms.php`, `manage_email.php`

**Assessment:** 🟨 **Partially implemented**

**Critical gaps:**
- 🟥 **No WhatsApp Business API integration confirmed** — `postSendWhatsapp` exists but provider connection not verified
- 🟥 **No SMS provider key verification** — Africa's Talking / Twilio / other provider keys in config not confirmed active
- 🟥 **No email SMTP configuration verified** — `manage_email.php` exists but deliverability unknown
- 🟥 **No delivery status tracking UI** — backend handles callbacks (`postSmsDeliveryReport`) but frontend status display not confirmed
- 🟥 **No retry mechanism UI** — retry exists at API level but user cannot see/trigger retries
- 🟧 **Send parent notification page** — `send_parent_notifications.php` is 3-line delegation stub
- 🟧 **Send class message page** — `send_class_message.php` is 3-line delegation stub

### G3. Parent Communication

**What a Kenyan primary school needs:** Parent-teacher messaging, fee reminders, attendance alerts, performance report delivery, meeting scheduling, announcements to parents.

**What exists:**
- Controller: `CommunicationsController` — `getParentMessage`, `postParentMessage`, `putParentMessage`, `deleteParentMessage`
- `ParentPortalController` — parent dashboard
- API: `ParentPortalMessageManager`
- JS: `parent_meetings.js`, `parent_feedback.js`
- Pages: `parent_meetings.php`, `parent_feedback.php`, `schedule_parent_meetings.php` (3-line stub)

**Assessment:** 🟨 **Partially implemented**

**Critical gaps:**
- 🟧 **Parent meeting scheduling page** — `schedule_parent_meetings.php` is 3-line delegation stub
- 🟨 **Parent feedback/reply** — unclear if two-way parent-teacher messaging works end-to-end

---

## H. REPORTING

### H1. Academic Reports

**What a Kenyan primary school needs:** Per-class, per-learning-area performance analysis, grade distribution (EE/ME/AE/BE), student ranking, subject analysis, term comparison, trend analysis.

**What exists:**
- Controller: `ReportsController` — `getAcademicPerformance`, `getScoreDistributions`, `getExamReports`, `getAcademicYearReports`, `getStudentProgressionRates`
- `AcademicController` — `getReportCardData`, `getStudentGrowthTrend`, `getPerformanceOverview`, `getResultsAnalysis`
- API: `StudentReportManager`, `ReportGenerationWorkflow`
- JS: `academic_reports.js`, `performance_analysis.js`, `performance_reports.js`, `performance_trends.js`, `comparative_reports.js`
- Pages: 10+ reporting pages

**Assessment:** 🟩 **End-to-end functional**

**Critical gaps:**
- 🟥 **CBE-format report card** — not confirmed if printable report matches KICD-approved format with competencies, values, EA/ME/AE/BE, and parent section

### H2. Student Reports

**What a Kenyan primary school needs:** Student report card (CBE format), progress report, term summary, competency progression, portfolio access, growth trends.

**What exists:**
- Controller: `AcademicController` — `getStudentAssessmentHistory`, `getStudentGrowthTrend`, `getReportCardsDownload`, `getStudentTimeline`
- `StudentsController` — `getPerformanceGet`, `getPerformanceMeta`, `getPerformanceOverview`, `getPerformanceFull`
- API: `ReportGenerationWorkflow`, `StudentInsightsService`
- JS: `student_performance.js`, `student_growth.js`, `student_timeline.js`
- Pages: `student_performance.php`, `student_growth.php`, `student_timeline.php`

**Assessment:** 🟩 **End-to-end functional**

### H3. Staff Reports

**What a Kenyan primary school needs:** Staff list by department, workload summary, attendance report, leave balance, payroll summary, performance reviews.

**What exists:**
- Controller: `ReportsController` — `getTotalStaff`, `getStaffAttendanceRates`, `getActiveStaffCount`, `getStaffLoanStats`, `getPayrollSummary`
- `StaffController` — `getPayrollSummary`, `getWorkloadGet`
- API: `StaffReportManager`
- JS: Various
- Pages: Various

**Assessment:** 🟩 **End-to-end functional**

### H4. Finance Reports

**What a Kenyan primary school needs:** Fee collection summary, revenue by source, expenditure by category, budget vs actual, arrears report, daily reconciliation report, term fee report.

**What exists:**
- Controller: `ReportsController` — `getFeeSummary`, `getFeePaymentTrends`, `getDiscountStats`, `getArrearsStats`, `getFinancialTransactionsSummary`, `getBankTransactionsSummary`
- `FinanceController` — `getReportsCompareYearlyCollections`
- API: `FinanceReportManager`, `ReportingManager`
- JS: `finance_reports.js`, `comparative_reports.js`
- Pages: `finance_reports.php`, `comparative_reports.php`

**Assessment:** 🟩 **End-to-end functional**

### H5. Dashboard (Role-Based)

**What a Kenyan primary school needs:** Role-specific dashboard for each user type (director, headteacher, deputy academic, deputy discipline, class teacher, subject teacher, accountant, school admin, intern teacher, parent).

**What exists:**
- Controller: `DashboardController` — 40+ dashboard methods for every role: `getDirectorFull`, `getHeadteacherFull`, `getDeputyAcademicFull`, `getDeputyDisciplineFull`, `getSubjectTeacherFull`, `getClassTeacherFull`, `getSchoolAdminFull`, `getInternTeacherFull`, `getAccountantFinancial`, etc.
- API: `DashboardAPI` + 13 analytics services (`DirectorAnalyticsService`, `HeadteacherAnalyticsService`, `SubjectTeacherAnalyticsService`, `ClassTeacherAnalyticsService`, etc.)
- JS: Role-specific dashboards loaded via `PageShell.loadRoleTemplate()`

**Assessment:** 🟩 **End-to-end functional**

---

## I. ADDITIONAL SYSTEM MODULES

### I1. User & Role Management

**What exists:**
- Controller: `UsersController` — 50+ methods for user/role/permission CRUD + bulk operations
- `SettingsController` — role/permission management
- API: `UsersAPI`, `RoleManager`, `PermissionManager`, `UserRoleManager`, `UserPermissionManager`
- DB: Users, roles, permissions tables
- JS: `manage_users.js`, `manage_roles.js`, `role_definitions.js`, `role_navigation_config.js`, `role_permission_matrix.js`, `permission_registry.js`, `resource_based_permissions.js`
- Pages: 10+ user/role management pages

**Assessment:** 🟩 **End-to-end functional**

### I2. System Configuration

**What exists:**
- Controller: `SystemConfigController` — 80+ methods: routes, menus, dashboards, policies, roles, permissions, module enablement, feature flags
- `SchoolConfigController` — school settings, health
- `SystemController` — logs, backups, migrations, feature flags, maintenance mode, domain isolation, webhooks, audit logs, API metrics, diagnostics
- API: `SystemConfigAPI`, `SystemAPI`
- DB: Config tables
- JS: `system_settings.js`, `school_settings.js`, `feature_flags.js`, `module_management.js`, `dashboard_registry.js`, `route_registry.js`, `sidebar_menus.js`
- Pages: 20+ system admin pages

**Assessment:** 🟩 **End-to-end functional**

### I3. Parent Portal

**What a Kenyan primary school needs:** Secure parent login, view multiple children's profiles, view fee balances and history, pay fees (M-Pesa), view/download report cards, view attendance, communicate with teachers, view competency progression and portfolio, receive notifications.

**What exists:**
- Controller: `ParentPortalController` — `postLogin`, `postLoginOtpRequest`, `postLoginOtpVerify`, `postLogout`, `getDashboard`, `getStudentFees`, `getStudentPaymentHistory`, `getStudentStatement`, `getFeeBalance`, `createSession`, `verifyAccess`
- DB: `parents` (portal_password, portal_status, portal_last_login), `parent_portal_sessions`, `parent_otp_sessions`, `parent_communication_preferences`, `parent_portal_messages`, `parent_statement_downloads`
- JS: `student_portal.js`
- Pages: `student_portal.php` (527 lines)

**Assessment:** 🟨 **Partially implemented**

**Workflow Trace (Phase 2) — Parent authenticates and uses portal:**
```
AUTHENTICATION:
1. Parent visits → `student_portal.php` → `parentPortalController`
2. Parent submits email/phone → postLogin() → OTP sent via OTPDeliveryService
3. Parent enters OTP → postLoginOtpVerify() → session created in parent_portal_sessions
4. Parent redirected to dashboard

DASHBOARD (what exists):
→ getDashboard() → list of linked children (`student_parents` table)
→ Shows each child: name, class, fee balance summary
→ API.parent.getChildren() → ParentPortalController.getDashboard()

FEE VIEWING (what exists):
→ Click child → loadStudentTab() → tabs: Fee History, Payment History, Statement
→ API.parent.getFeeBalance() → ParentPortalController.getFeeBalance()
→ API.parent.getPaymentHistory() → ParentPortalController.getStudentPaymentHistory()
→ Statement rendered via buildStatementHTML()

REPORT CARDS (MISSING):
→ No "Report Card" tab exists
→ ParentPortalController has NO getStudentReportCard() method
→ `API.parent.getReportCard()` does NOT exist in api.js
→ report_cards.js code exists but has no parent-facing view

PAYMENT (MISSING):
→ No "Pay Now" button anywhere in portal UI
→ ParentPortalController has NO initiateMpesaStkPush() method
→ MpesaPaymentService.initiateSTKPush() exists but no endpoint exposes it to parent portal

ATTENDANCE (MISSING):
→ No attendance tab
→ API.parent.getAttendance() IS defined in api.js (GET /parent/attendance)
→ But ParentPortalController has NO getAttendance() method
→ API endpoint leads to 404

MESSAGING (MISSING):
→ No messaging UI in portal
→ `parent_portal_messages` table exists (parent_id, student_id, sender_type, subject, body)
→ `CommunicationsController` has parent message CRUD
→ But parent portal JS has no message compose/view UI

COMPETENCY/PORTFOLIO (MISSING):
→ No competency view in portal
→ API.parent.getPerformance() IS defined in api.js (GET /parent/performance)
→ But ParentPortalController has NO getPerformance() method
```

**Acceptance Test (Phase 4) — A parent of a Grade 4 student uses the portal:**
| Step | Expected | Actual | Status |
|------|----------|--------|--------|
| Login with email + OTP | OTP sent to phone/email | `postLogin` + `postLoginOtpRequest` + `postLoginOtpVerify` — full OTP flow works | ✅ |
| View dashboard with child | Child name, class, photo shown | `getDashboard()` → `parentPortalController.renderChildren()` | ✅ |
| View fee balance | Shows amount due, payment deadline | `loadBalanceSummary()` via `API.parent.getFeeBalance()` | ✅ |
| View payment history | List of past payments | `API.parent.getPaymentHistory()` → `renderPaymentHistory()` | ✅ |
| Download fee statement | PDF statement generated | `buildStatementHTML()` renders in-page statement | ✅ |
| **View report card** | CBE-format report card with scores, grades, competencies | **No report card endpoint exists** | ❌ |
| **Pay fees (M-Pesa)** | Click "Pay Now" → STK Push to phone | **No Pay Now button anywhere** | ❌ |
| **Message teacher** | Compose message → teacher receives | **No messaging UI in portal** | ❌ |
| **View attendance** | See child's attendance record | API.parent.getAttendance() defined but **no controller method** | ❌ |
| **View competency progress** | See EE/ME/AE/BE per competency | **No endpoint, no UI** | ❌ |
| **View portfolio artifacts** | See child's uploaded work | **No portfolio section at all** | ❌ |

**Critical gaps:**
- 🟥 **No report card viewing** — parent cannot view/download child's CBE report card
- 🟥 **No M-Pesa payment button** — M-Pesa STK Push service exists (`MpesaPaymentService`) but parent portal has no "Pay Now" button to trigger it
- 🟥 **No parent-teacher messaging** — parent cannot message teacher from portal
- 🟥 **No attendance view** — API.parent.getAttendance() defined but backend controller method missing
- 🟥 **No competency/portfolio view** — parent cannot see child's competency progression

---

## COMPLETE CLASSIFICATION SUMMARY

| Module | Rating | Key Issue | PB | ST | WF | AT |
|--------|--------|-----------|----|----|----|----|
| **A1** Academic Years | 🟩 | Full CRUD + rollover | ✅ | ✅ | ✅ | ✅ |
| **A2** Classes & Streams | 🟩 | Full CRUD + teacher assignment | ✅ | ✅ | ✅ | ✅ |
| **A3** Learning Areas | 🟩/🟨 | Sub-strand mgmt missing (113 strands orphaned) | ✅ | ✅ | ⚠️ | ❌ |
| **A4** Grading & Rubrics | 🟨 | **THRESHOLD BUG** (JS≠DB); no rubric UI | ✅ | ✅ | ❌ | ❌ |
| **B1** Admission | 🟩 | Full workflow; placement test TODO | ✅ | ✅ | ✅ | ⚠️ |
| **B2** Student Profile | 🟩 | Full profile with medical, special needs | ✅ | ✅ | ✅ | ✅ |
| **B3** Parents | 🟩 | Full parent linking; portal incomplete | ✅ | ✅ | ✅ | ⚠️ |
| **B4** Transfers | 🟩 | Full workflow | ✅ | ✅ | ✅ | ✅ |
| **B5** Promotions | 🟩 | Multiple modes + CBE migration | ✅ | ✅ | ✅ | ✅ |
| **B6** Alumni | 🟧 | No dedicated UI | ✅ | ✅ | ❌ | ❌ |
| **C1** Staff Onboarding | 🟩 | Full lifecycle | ✅ | ✅ | ✅ | ✅ |
| **C2** Staff Assignments | 🟩 | Subjects, classes, departments | ✅ | ✅ | ✅ | ✅ |
| **C3** Leave | 🟩 | Application + approval | ✅ | ✅ | ✅ | ✅ |
| **C4** Staff Attendance | 🟩 | Register + reports | ✅ | ✅ | ✅ | ✅ |
| **C5** Payroll | 🟩/🟨 | Basic function; NHIF/NSSF/PAYE accuracy unknown | ✅ | ✅ | ✅ | ⚠️ |
| **C6** Performance | 🟩 | Reviews + observation | ✅ | ✅ | ✅ | ✅ |
| **C7** Separation | 🟩 | Offboarding + retirement | ✅ | ✅ | ✅ | ✅ |
| **D1** Teacher Assignments | 🟩 | Class + subject + workload | ✅ | ✅ | ✅ | ✅ |
| **D2** Timetable | 🟩 | Period-based + conflict detection | ✅ | ✅ | ✅ | ✅ |
| **D3** Lesson Plans | 🟩 | Full approval workflow | ✅ | ✅ | ✅ | ✅ |
| **D4** Schemes of Work | 🟩 | Strand-based + approval | ✅ | ✅ | ✅ | ✅ |
| **D5** Formative Assessment | 🟩/🟨 | **THRESHOLD BUG**; no rubric; not per-strand | ✅ | ✅ | ⚠️ | ❌ |
| **D6** Summative Assessment | 🟩 | 11-stage workflow; no moderation UI | ✅ | ✅ | ✅ | ⚠️ |
| **D7** Competency Assessment | 🟨 | No portfolio UI; evidence recording incomplete | ✅ | ✅ | ⚠️ | ❌ |
| **D8** Results Compilation | 🟨 | **THRESHOLD BUG**; no moderation; no CBE report | ✅ | ✅ | ⚠️ | ❌ |
| **D9** Student Portfolio | 🟥 | DB modeled; zero UI exists | ✅ | ❌ | ❌ | ❌ |
| **E1** Fee Structure | 🟩 | Full workflow + approval | ✅ | ✅ | ✅ | ✅ |
| **E2** Billing/Invoicing | 🟩 | Generate per student | ✅ | ✅ | ✅ | ✅ |
| **E3** Payments (M-Pesa) | 🟨 | **No Pay Now button**; sandbox-only; stub page | ✅ | ✅ | ⚠️ | ❌ |
| **E4** Student Account | 🟩 | Ledger + statements | ✅ | ✅ | ✅ | ✅ |
| **E5** Reconciliation | 🟨 | Cash OK; M-Pesa page is stub | ✅ | ✅ | ⚠️ | ❌ |
| **E6** Financial Reports | 🟩 | Revenue, arrears, trends | ✅ | ✅ | ✅ | ✅ |
| **E7** Expenditure/Budget | 🟩 | Budget + expense approval | ✅ | ✅ | ✅ | ✅ |
| **E8** Vendors/Suppliers | 🟩 | PO + vendor management | ✅ | ✅ | ✅ | ✅ |
| **F1** Student Attendance | 🟩 | Full marking + reports | ✅ | ✅ | ✅ | ✅ |
| **F2** Transport | 🟩 | Routes, vehicles, drivers, billing | ✅ | ✅ | ✅ | ✅ |
| **F3** Inventory | 🟩 | Full procurement/audit/disposal workflows | ✅ | ✅ | ✅ | ✅ |
| **F4** Library | 🟩 | Books, issues, fines | ✅ | ✅ | ✅ | ✅ |
| **F5** Boarding | 🟩 | Dormitories, roll call, exeats | ✅ | ✅ | ✅ | ✅ |
| **F6** Catering | 🟨 | Menu page is 17-line shell; dietary tracking unclear | ✅ | ✅ | ⚠️ | ❌ |
| **F7** Health/Sick Bay | 🟩 | Admissions, vaccinations | ✅ | ✅ | ✅ | ✅ |
| **F8** Discipline | 🟩 | Cases + sanctions; 2 delegation stubs | ✅ | ✅ | ✅ | ✅ |
| **F9** Counseling | 🟩 | Cases + welfare | ✅ | ✅ | ✅ | ✅ |
| **F10** Sports | 🟩 | Teams, fixtures, competitions | ✅ | ✅ | ✅ | ✅ |
| **F11** Chapel | 🟩 | Services scheduling | ✅ | ✅ | ✅ | ✅ |
| **G1** Internal Communication | 🟩 | Forums, announcements, requests | ✅ | ✅ | ✅ | ✅ |
| **G2** External Communication | 🟥/🟨 | SMS/Email/WhatsApp code exists; no provider verification | ✅ | ✅ | ⚠️ | ❌ |
| **G3** Parent Communication | 🟨 | Meeting scheduling is stub; 2-way unclear | ✅ | ✅ | ⚠️ | ❌ |
| **H1** Academic Reports | 🟩 | Analysis + trends | ✅ | ✅ | ✅ | ✅ |
| **H2** Student Reports | 🟩 | Growth + timeline | ✅ | ✅ | ✅ | ✅ |
| **H3** Staff Reports | 🟩 | Various | ✅ | ✅ | ✅ | ✅ |
| **H4** Finance Reports | 🟩 | Collection + expenditure | ✅ | ✅ | ✅ | ✅ |
| **H5** Dashboards | 🟩 | 9+ role-specific | ✅ | ✅ | ✅ | ✅ |
| **I1** User/RBAC | 🟩 | Full user/role/permission management | ✅ | ✅ | ✅ | ✅ |
| **I2** System Config | 🟩 | Routes, menus, modules, logs | ✅ | ✅ | ✅ | ✅ |
| **I3** Parent Portal | 🟨 | Login + fee view; **5 critical features missing** | ✅ | ✅ | ⚠️ | ❌ |

**Legend:** PB = Page/UI exists, ST = Static code analysis passes, WF = Workflow complete end-to-end, AT = Acceptance test passes

---

## CRITICAL BLOCKERS (Fixes Required Before Any Module Can Run)

These are bugs in existing code that would cause incorrect behavior at runtime:

1. **🔴 THRESHOLD MISMATCH** — **FIXED 2026-07-30** — `js/pages/report_cards.js:611`, `assessments_exams.js:51`, `academic_reports.js:358`, `exam_setup.js:48` all used wrong thresholds (EE≥80/ME≥50/AE≥25). Unified to DB standard: EE≥75/ME≥60/AE≥40/BE<40. `formative_assessments.js:536` was already correct.

2. **🔴 MPESA_ENVIRONMENT=sandbox** — `config/.env` has `MPESA_ENVIRONMENT=sandbox`. No live Safaricom API keys configured. STK Push service (`MpesaPaymentService`, 848 lines) is fully coded but cannot function in production without live keys.

3. **🔴 46 delegation stubs** — 46 files are 3–13 line `include` stubs (26 pure + 20 variable-setting). Pages like `behavior_logs.php`, `daily_attendance.php`, `mpesa_reconciliation.php`, `send_parent_notifications.php`, `schedule_parent_meetings.php`. Most point to legit pages but:
   - `manage_menus.php` (17 lines) has minimal HTML — needs full catering menu management UI
   - `mpesa_reconciliation.php` delegates to `mpesa_settlements.php` — works as alias but actual reconciliation features not on that page
   - `send_parent_notifications.php` → `manage_communications.php` — no parent-specific notification workflow

4. **🔴 Communication provider API keys unknown** — Africa's Talking (SMS) configured in `.env` but API key validity unconfirmed. WhatsApp Business API and email SMTP status unknown.

5. **🔴 Parent portal lacks 5 critical features** — no report card viewing, no M-Pesa payment button (service exists but not wired), no messaging, no attendance, no portfolio.

6. **🔴 Formative scores not per-strand** — DB `formative_scores` links to `assessments` which has `learning_outcome_id` FK but formative summary aggregates per-learning-area, not per-strand. CBC requires per-strand tracking for competency-based reporting.

---

## WHAT TO KEEP, WHAT TO ADD, WHAT TO MODIFY

### Keep (High Quality, Minimal Changes Needed)
- Student lifecycle (admission → profile → parents → transfers → promotions → alumni)
- Staff lifecycle (onboarding → assignments → leave → attendance → payroll → performance → separation)
- Inventory & procurement (full procurement/audit/disposal workflows — exceptional depth)
- Transport (routes, vehicles, drivers, billing)
- Boarding (dormitories, roll call, exeats)
- Sports & activities
- User/RBAC system
- System configuration (routes, menus, modules, feature flags)
- Role-specific dashboards (9+ roles)

### Add (New Development Required)
1. **🟥 Portfolio UI** — pages + JS + API for artifact upload, teacher feedback, learner reflection, parent view. DB fully modeled (`portfolios`, `portfolio_artifacts`) but zero front-end exists. **Highest priority CBC gap.**
2. **🟥 Rubric Builder UI** — page to create/edit 4-level CBC rubrics (EE/ME/AE/BE descriptors), assign to assessment tools, link to learning outcomes. `assessment_rubrics` table exists. Permission `assessments_rubric_manage` is registered.
3. **🟥 Result Moderation UI** — page for HoD/subject lead to moderate marks before approval. `postExamsModerateMarks` controller method exists but no frontend.
4. **🟥 Parent Portal: Report Cards** — parent can view/download CBE-format report cards. Need: `ParentPortalController::getStudentReportCard()`, `API.parent.getReportCard()`, and UI tab in `student_portal.js`.
5. **🟥 Parent Portal: Pay Now button** — STK Push service (`MpesaPaymentService::initiateSTKPush()`) exists but needs: API endpoint (`POST /parent/pay`), `ParentPortalController::initiateMpesaPayment()`, and "Pay Now" button in portal UI.
6. **🟥 Parent Portal: Communication** — parent-to-teacher messaging. `parent_portal_messages` table exists, `CommunicationsController` has CRUD, but portal JS lacks compose/view UI.
7. **🟥 Parent Portal: Attendance View** — `API.parent.getAttendance()` defined in api.js but `ParentPortalController` has no matching method. Need: controller method + portal UI tab.
8. **🟥 Parent Portal: Competency/Portfolio View** — `API.parent.getPerformance()` defined but no backend. Need: controller method + portal competency progression view.
9. **🟥 Communication Provider Setup** — configure Africa's Talking / Twilio / SMTP / WhatsApp Business API with verified keys.
10. **🟥 Sub-strand Management UI** — CRUD for sub-strands linked to strands. 113 strands have zero sub-strands (only 36 exist for 149 strands).
11. **🟥 Learning Outcome Management UI** — CRUD for specific learning outcomes. `learning_outcomes` table exists but no API endpoint or UI to manage outcomes independently.
12. **🟦 Strand→Competency crosswalk table** — no bridge between `strands` and `core_competencies`. Currently denormalized in `core_competencies.learning_outcomes` text field. Need a junction table for proper CBC competency mapping.

### Modify (Fix Existing Code)
1. **🔧 Unify EE/ME/AE/BE thresholds** — **DONE 2026-07-30** — Fixed in `report_cards.js:611`, `assessments_exams.js:51`, `academic_reports.js:358`, `exam_setup.js:48`. All now use DB standard: EE≥75/ME≥60/AE≥40/BE<40.
2. **🔧 Fix M-Pesa sandbox → live** — configure live Safaricom API credentials. Add `ParentPortalController::initiateMpesaPayment()` to expose STK Push to parent.
3. **🔧 Upgrade 46 delegation stubs** — verify each alias works, add JS controllers where needed. Prioritize `mpesa_reconciliation.php`, `manage_menus.php`, `send_parent_notifications.php`, `schedule_parent_meetings.php`.
4. **🔧 Fix `manage_menus.php`** — 17 lines → full catering menu management UI with meal types, dietary flags, cost tracking
5. **🔧 Fix `mpesa_reconciliation.php`** — 3-line stub → full reconciliation page with auto-match, manual match, report download
6. **🔧 Fix `schedule_parent_meetings.php`** — 3-line stub → meeting scheduling UI with teacher availability, parent time selection, confirmation
7. **🔧 Fix `send_parent_notifications.php`** — 3-line stub → notification page with template selection, bulk send, delivery tracking
8. **🔧 Fix `send_class_message.php`** — 3-line stub → class messaging page with group compose, attachment support
9. **🔧 Wire `assessment_rubrics` permission** — menu link exists (`dha_rubrics`) but no page; create rubric builder UI or remove link
10. **🔧 Add per-strand formative summary** — modify `AcademicController::getFormativeSummary()` to support strand-level aggregation, not just learning-area-level

### Remove (Dead Code / Deprecated)
- `api/router/system_config_router.php` — deprecated, bypasses middleware
- `api/includes/DashboardManager.php` — deprecated
- `api/includes/auth_check.php` — deprecated
- `api/middleware/EnhancedRBACMiddleware.php` — deprecated
- `api/middleware/DashboardManager.php` — deprecated
- `api/includes/RoutePermissionsStore.php` — deprecated

---


This document answers "what exists, what's missing, what's broken."

**End-to-end acceptance testing** — test each workflow per module table above, starting from School Foundation → Student Lifecycle → Academic/CBC → Finance → Operations → Communication
