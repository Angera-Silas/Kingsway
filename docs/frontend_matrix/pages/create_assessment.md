# create_assessment.php

- **File**: `pages/create_assessment.php`
- **Controller**: `create_assessment.js`
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
| `GET academic/formative-assessments` | `year_id`, `term_id`, `limit` | — |
| `POST academic/formative-assessments` | — | — |

## Backend params (expected)

| Handler | Input keys ($data/$_POST/$_GET) | Path id | Data sources (views/procs/tables) |
|---|---|---|---|
| Academic::getYearsList | — | `id` | — |
| Academic::getTermsList | — | — | academic_year_classes, academic_year_terms, academic_years, terms |
| Academic::getClassesList | — | — | academic_year_class_streams, academic_year_classes, academic_years, classes, school_levels, streams, student_academic_enrollments |
| Academic::getSubjectsList | — | `id` | — |
| Academic::getFormativeAssessments | `class_id`, `subject_id`, `term_id`, `type_id`, `year_id`, `teacher_only`, `subject_teacher_only` | — | academic_year_class_learning_area_teachers, academic_year_class_streams, academic_year_classes, academic_year_terms, assessment_results, assessment_types, assessments, classes, learning_areas, persons, staff, terms |
| Academic::postFormativeAssessments | `title`, `name`, `assessment_type_id`, `type`, `term_id`, `class_id`, `status`, `subject_id`, `max_marks`, `assessment_date`, `cat_date` | — | academic_year_terms, academic_years, assessments, staff, users |

## Response shape (data keys consumed)

_(no post-await `.prop` consumption detected)_

## UI / security

- `innerHTML`/`insertAdjacentHTML` assignments: `7` (with interpolation: `0`)
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
