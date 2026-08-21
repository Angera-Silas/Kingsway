# at_risk_students.php

- **File**: `pages/at_risk_students.php`
- **Controller**: `at_risk_students.js`
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
| `GET /attendance/chronic-student-absentees?threshold=80` | `id`, `name` | — |
| `GET /students/discipline-get?status=open` | `id`, `name` | — |
| `GET /counseling/summary` | `id`, `name` | — |

## Backend params (expected)

| Handler | Input keys ($data/$_POST/$_GET) | Path id | Data sources (views/procs/tables) |
|---|---|---|---|
| Attendance::getChronicStudentAbsentees | `termId`, `term_id`, `yearId`, `year_id`, `threshold` | — | — |
| Students::getDisciplineGet | `student_id`, `search`, `status`, `severity`, `class_id` | `id` | academic_year_class_streams, academic_year_classes, academic_year_terms, classes, discipline_incidents, persons, streams, student_academic_enrollments, students |
| Counseling::getSummary | — | — | counseling_cases, counseling_sessions, persons, staff, students |

## Response shape (data keys consumed)

_(no post-await `.prop` consumption detected)_

## UI / security

- `innerHTML`/`insertAdjacentHTML` assignments: `4` (with interpolation: `1`)
- `escapeHtml()` calls: `11` — XSS check: `PASS`
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
