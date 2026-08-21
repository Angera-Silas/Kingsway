# competencies_sheet.php

- **File**: `pages/competencies_sheet.php`
- **Controller**: `competencies_sheet.js`
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
| `GET /academic/core-competencies-list` | — | — |
| `GET /students/by-class-get/` | — | — |
| `GET /academic/competency-ratings?term_id=${termId}&class_id=${classId}` | — | — |
| `POST /academic/competency-ratings` | `term_id` | — |

## Backend params (expected)

| Handler | Input keys ($data/$_POST/$_GET) | Path id | Data sources (views/procs/tables) |
|---|---|---|---|
| Academic::getTermsList | — | — | academic_year_classes, academic_year_terms, academic_years, terms |
| Academic::getCoreCompetenciesList | — | — | core_competencies |
| Students::getByClassGet | `class_id` | `id` | vw_student_attendance_summary, academic_year_class_streams, academic_year_classes, academic_years, classes, persons, streams, student_academic_enrollments, students, term_consolidations, vw_student_attendance_summary |
| Academic::getCompetencyRatings | `term_id`, `class_id`, `student_id` | — | core_competencies, learner_competencies, performance_levels_cbc, persons, student_academic_enrollments, students |
| Academic::postCompetencyRatings | `ratings`, `term_id`, `academic_year` | — | learner_competencies, performance_level_id, performance_levels_cbc |

## Response shape (data keys consumed)

_(no post-await `.prop` consumption detected)_

## UI / security

- `innerHTML`/`insertAdjacentHTML` assignments: `8` (with interpolation: `1`)
- `escapeHtml()` calls: `7` — XSS check: `PASS`
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
| Response: unwrapped `data` handled | ⚠️ | heuristic |
| UI: Bootstrap + `escapeHtml` | ✅ | machine |
| Responsive @375/768/1200px | ⚠️ | **manual sign-off required** |
