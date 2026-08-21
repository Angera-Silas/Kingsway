# enter_marks.php

- **File**: `pages/enter_marks.php`
- **Controller**: `enter_marks.js`
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
| `GET academic/formative-assessments` | `year_id`, `term_id`, `class_id`, `teacher_only` | — |
| `GET academic/class-students` | `class_id`, `year_id`, `term_id` | — |
| `GET academic/grading-results` | `assessment_id` | — |
| `POST academic/formative-assessment-marks` | `assessment_id`, `marks` | — |

## Backend params (expected)

| Handler | Input keys ($data/$_POST/$_GET) | Path id | Data sources (views/procs/tables) |
|---|---|---|---|
| Academic::getYearsList | — | `id` | — |
| Academic::getTermsList | — | — | academic_year_classes, academic_year_terms, academic_years, terms |
| Academic::getClassesList | — | — | academic_year_class_streams, academic_year_classes, academic_years, classes, school_levels, streams, student_academic_enrollments |
| Academic::getFormativeAssessments | `class_id`, `subject_id`, `term_id`, `type_id`, `year_id`, `teacher_only`, `subject_teacher_only` | — | academic_year_class_learning_area_teachers, academic_year_class_streams, academic_year_classes, academic_year_terms, assessment_results, assessment_types, assessments, classes, learning_areas, persons, staff, terms |
| Academic::getClassStudents | `class_id` | `id` | persons, student_academic_enrollments, students |
| Academic::getGradingResults | — | — | academic_year_class_streams, academic_year_classes, academic_year_terms, assessment_results, assessments, classes, learning_areas, persons, streams, student_academic_enrollments, students, term_subject_scores |
| Academic::postFormativeAssessmentMarks | `assessment_id`, `marks`, `scores` | `id` | assessments, formative_scores, score |

## Response shape (data keys consumed)

_(no post-await `.prop` consumption detected)_

## UI / security

- `innerHTML`/`insertAdjacentHTML` assignments: `8` (with interpolation: `0`)
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
| Response: unwrapped `data` handled | ⚠️ | heuristic |
| UI: Bootstrap + `escapeHtml` | ✅ | machine |
| Responsive @375/768/1200px | ⚠️ | **manual sign-off required** |
