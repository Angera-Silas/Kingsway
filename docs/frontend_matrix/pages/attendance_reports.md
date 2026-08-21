# attendance_reports.php

- **File**: `pages/attendance_reports.php`
- **Controller**: `attendance_reports.js`
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
| `GET /attendance/academic-summary?` | — | — |
| `GET /academic/classes/list?status=active` | — | — |
| `GET /attendance/chronic-student-absentees?` | — | — |
| `GET /reports/attendance-rates` | `type`, `data` | — |

## Backend params (expected)

| Handler | Input keys ($data/$_POST/$_GET) | Path id | Data sources (views/procs/tables) |
|---|---|---|---|
| Attendance::getAcademicSummary | `date_from`, `date_to`, `session_id`, `stream_id`, `status` | — | vw_student_attendance_summary, student_academic_enrollments, student_attendance, vw_student_attendance_summary |
| Academic::getClassesList | — | — | academic_year_class_streams, academic_year_classes, academic_years, classes, school_levels, streams, student_academic_enrollments |
| Attendance::getChronicStudentAbsentees | `termId`, `term_id`, `yearId`, `year_id`, `threshold` | — | — |
| Reports::getAttendanceRates | — | — | — |

## Response shape (data keys consumed)

- `direct.callAPI -> total_enrolled`
- `direct.callAPI -> attendance_rate`
- `direct.callAPI -> absent_today`
- `direct.callAPI -> chronic_count`
- `direct.callAPI -> by_class`
- `direct.callAPI -> map`

## UI / security

- `innerHTML`/`insertAdjacentHTML` assignments: `7` (with interpolation: `0`)
- `escapeHtml()` calls: `4` — XSS check: `PASS`
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
