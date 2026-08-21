# enter_exam_results.php

- **File**: `pages/enter_exam_results.php`
- **Controller**: `enter_exam_results.js`
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
| `GET academic/years-list` | — | — |
| `GET academic/terms-list` | — | — |
| `GET academic/classes-list` | — | — |
| `GET academic/subjects-list` | — | — |
| `GET academic/exam-schedule` | `year_id`, `class_id`, `subject_id` | — |
| `GET academic/class-students` | `class_id`, `subject_id`, `year_id`, `term_id` | — |
| `GET academic/assessments-list` | `class_id`, `subject_id`, `term_id` | — |
| `GET academic/grading-results` | `assessment_id` | — |
| `POST academic/assessments-mark-and-grade` | `assessment_id`, `grading_data` | — |

## Backend params (expected)

| Handler | Input keys ($data/$_POST/$_GET) | Path id | Data sources (views/procs/tables) |
|---|---|---|---|
| Academic::getYearsList | — | `id` | — |
| Academic::getTermsList | — | — | academic_year_classes, academic_year_terms, academic_years, terms |
| Academic::getClassesList | — | — | academic_year_class_streams, academic_year_classes, academic_years, classes, school_levels, streams, student_academic_enrollments |
| Academic::getSubjectsList | — | `id` | — |
| Academic::getExamSchedule | — | `id` | academic_year_class_streams, academic_year_classes, academic_year_terms, classes, exam_schedules, learning_areas, persons, rooms, staff |
| Academic::getClassStudents | `class_id` | `id` | persons, student_academic_enrollments, students |
| Academic::getAssessmentsList | `class_id`, `term_id`, `subject_id`, `status`, `assessment_type_id` | — | academic_year_class_streams, academic_year_classes, academic_year_terms, assessment_types, assessments, classes, formative_scores, learning_areas, streams, student_academic_enrollments, terms |
| Academic::getGradingResults | — | — | academic_year_class_streams, academic_year_classes, academic_year_terms, assessment_results, assessments, classes, learning_areas, persons, streams, student_academic_enrollments, students, term_subject_scores |
| Academic::postAssessmentsMarkAndGrade | `instance_id`, `assessment_id`, `grading_data`, `marks_data`, `marks`, `is_final`, `marked_by` | — | assessment_results, assessments, marks_obtained |

## Response shape (data keys consumed)

- `direct.callAPI -> length`

## UI / security

- `innerHTML`/`insertAdjacentHTML` assignments: `9` (with interpolation: `0`)
- `escapeHtml()` calls: `0` — XSS check: `PASS`
- Bootstrap modal usage: `0`
- Payload/backend param match: `NA`
- Fix flags: none
- Info flags: `NO_AUTH_GUARD`, `ESCAPED_LITERAL_HTML`
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
