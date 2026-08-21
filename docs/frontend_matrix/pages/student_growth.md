# student_growth.php

- **File**: `pages/student_growth.php`
- **Controller**: `student_growth.js`
- **Roles**: —
- **Sidebar item(s)**: —

## Init / auth

- `AuthContext.ready()` awaited: `N`
- Permission/RBAC guard: `N`
- Raw `fetch`/`XMLHttpRequest`/`axios`: `0`

## API methods used

_(no `API.<module>.<method>` calls detected)_

## Direct `callAPI` endpoints

| Endpoint | Payload keys (sent) | Flags |
|---|---|---|
| `GET /academic/terms-list` | — | — |
| `GET /students?search=` | — | — |
| `GET /academic/class-students?class_id=` | — | — |
| `GET /students/profile-get/` | — | — |
| `GET /academic/student-results?` | — | — |
| `GET /academic/student-results?student_id=` | — | — |
| `GET /academic/competency-ratings?student_id=` | — | — |
| `GET /academic/student-assessment-history?student_id=` | — | — |
| `GET /academic/student-growth-trend?student_id=${this._studentId}&learning_area_id=${laId}` | — | — |

## Backend params (expected)

| Handler | Input keys ($data/$_POST/$_GET) | Path id | Data sources (views/procs/tables) |
|---|---|---|---|
| Academic::getTermsList | — | — | academic_year_classes, academic_year_terms, academic_years, terms |
| Students::getStudent | `class_id`, `stream_id`, `status`, `gender`, `student_type_id`, `fee_status` | `id` | vw_student_fee_balances, academic_year_class_streams, academic_year_classes, classes, parents, persons, streams, student_academic_enrollments, student_parents, student_types, students, vw_student_fee_balances |
| Academic::getClassStudents | `class_id` | `id` | persons, student_academic_enrollments, students |
| Students::getProfileGet | `student_id` | `id` | — |
| Academic::getStudentResults | `student_id` | `id` | vw_student_attendance_analytics, academic_year_class_streams, academic_year_classes, academic_year_terms, academic_years, annual_scores, assessment_results, assessments, classes, learning_areas, persons, streams, student_academic_enrollments, students, term_subject_scores, terms, vw_student_attendance_analytics |
| Academic::getCompetencyRatings | `term_id`, `class_id`, `student_id` | — | core_competencies, learner_competencies, performance_levels_cbc, persons, student_academic_enrollments, students |
| Academic::getStudentAssessmentHistory | `student_id`, `term_id`, `subject_id` | — | academic_year_terms, academic_years, assessment_types, assessments, formative_scores, learning_areas, terms |
| Academic::getStudentGrowthTrend | `student_id`, `learning_area_id` | — | academic_year_terms, academic_years, learning_areas, student_academic_enrollments, term_subject_scores, terms |

## Response shape (data keys consumed)

- `direct.callAPI -> first_name`
- `direct.callAPI -> last_name`
- `direct.callAPI -> class_name`
- `direct.callAPI -> grade`
- `direct.callAPI -> admission_no`
- `direct.callAPI -> learning_area_id`
- `direct.callAPI -> subject_id`
- `direct.callAPI -> learning_area_name`
- `direct.callAPI -> subject_name`
- `direct.callAPI -> competency_id`
- `direct.callAPI -> code`

## UI / security

- `innerHTML`/`insertAdjacentHTML` assignments: `31` (with interpolation: `3`)
- `escapeHtml()` calls: `21` — XSS check: `PASS`
- Bootstrap modal usage: `0`
- Payload/backend param match: `NA`
- Fix flags: none
- Info flags: `NO_AUTH_GUARD`
- Fix task: `—`

## End-to-end trace

> collect → store → analyse → display: page payload keys → API endpoint → controller method → service passthrough → SQL views/procs/tables (rows above). This is machine-derived; the interactive flow (form submit → render) needs human sign-off.

## Review checklist

| Check | Status | Notes |
|---|---|---|
| Init: `AuthContext.ready()` | ❌ | machine |
| Data load: `window.API.*`, no raw fetch | ✅ | machine |
| Params: sent ≈ backend `$data` | ⚠️ | heuristic |
| Response: unwrapped `data` handled | ✅ | heuristic |
| UI: Bootstrap + `escapeHtml` | ✅ | machine |
| Responsive @375/768/1200px | ⚠️ | **manual sign-off required** |
