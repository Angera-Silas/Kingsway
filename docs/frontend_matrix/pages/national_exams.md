# national_exams.php

- **File**: `pages/national_exams.php`
- **Controller**: `national_exams.js`
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
| `GET /academic/learning-areas-list` | — | — |
| `GET /academic/national-exams` | — | — |
| `GET /academic/national-exams?exam_type=KJSEA_G9` | — | — |
| `GET /students/by-class-get/` | — | — |
| `GET /academic/national-exams?exam_type=${examType}&exam_year=${examYear}&learning_area_id=${subjectId}` | — | — |
| `POST /academic/national-exams` | `exam_type`, `exam_year` | — |

## Backend params (expected)

| Handler | Input keys ($data/$_POST/$_GET) | Path id | Data sources (views/procs/tables) |
|---|---|---|---|
| Academic::getLearningAreasList | — | — | learning_areas |
| Academic::getNationalExams | `student_id`, `class_id` | — | learning_areas, national_exam_results, persons, student_academic_enrollments, students |
| Students::getByClassGet | `class_id` | `id` | vw_student_attendance_summary, academic_year_class_streams, academic_year_classes, academic_years, classes, persons, streams, student_academic_enrollments, students, term_consolidations, vw_student_attendance_summary |
| Academic::postNationalExams | `results`, `exam_type`, `exam_year`, `academic_year_id` | — | national_exam_results, score |

## Response shape (data keys consumed)

_(no post-await `.prop` consumption detected)_

## UI / security

- `innerHTML`/`insertAdjacentHTML` assignments: `17` (with interpolation: `4`)
- `escapeHtml()` calls: `14` — XSS check: `PASS`
- Bootstrap modal usage: `2`
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
