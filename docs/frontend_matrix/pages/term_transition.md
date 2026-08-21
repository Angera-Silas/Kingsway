# term_transition.php

- **File**: `pages/term_transition.php`
- **Controller**: `term_transition.js`
- **Roles**: `System Administrator`
- **Sidebar item(s)**: `100122`

## Init / auth

- `AuthContext.ready()` awaited: `N`
- Permission/RBAC guard: `N`
- Raw `fetch`/`XMLHttpRequest`/`axios`: `0`

## API methods used

_(no `API.<module>.<method>` calls detected)_

## Direct `callAPI` endpoints

| Endpoint | Payload keys (sent) | Flags |
|---|---|---|
| `GET /academic/term-transition/context` | — | — |
| `GET /academic/timetable-stats` | — | — |
| `GET /academic/lesson-plans-list?term_id=` | — | — |
| `GET /attendance/academic-summary?term_id=` | — | — |
| `GET /academic/grading-results?term_id=` | — | — |
| `POST /academic/term-transition/execute` | `from_term_id`, `to_term_id`, `academic_year_id`, `rollover_timetable`, `keep_teachers`, `keep_rooms`, `dates` | — |

## Backend params (expected)

| Handler | Input keys ($data/$_POST/$_GET) | Path id | Data sources (views/procs/tables) |
|---|---|---|---|
| Academic::getTermTransitionContext | — | — | — |
| Academic::getTimetableStats | `term_id` | — | academic_year_class_streams, academic_year_classes, classes, timetable_entries |
| Academic::getLessonPlansList | — | — | academic_year_calendar, academic_year_calendar_days, academic_year_class_learning_areas, academic_year_class_streams, academic_year_classes, academic_year_terms, classes, learning_areas, lesson_plans, lesson_templates, persons, staff |
| Attendance::getAcademicSummary | `date_from`, `date_to`, `session_id`, `stream_id`, `status` | — | vw_student_attendance_summary, student_academic_enrollments, student_attendance, vw_student_attendance_summary |
| Academic::getGradingResults | — | — | academic_year_class_streams, academic_year_classes, academic_year_terms, assessment_results, assessments, classes, learning_areas, persons, streams, student_academic_enrollments, students, term_subject_scores |
| Academic::postTermTransitionExecute | — | — | — |

## Response shape (data keys consumed)

- `direct.callAPI -> terms`
- `direct.callAPI -> current_term`
- `direct.callAPI -> next_term`
- `direct.callAPI -> slots`
- `direct.callAPI -> class_count`
- `direct.callAPI -> teacher_count`

## UI / security

- `innerHTML`/`insertAdjacentHTML` assignments: `10` (with interpolation: `1`)
- `escapeHtml()` calls: `6` — XSS check: `PASS`
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
