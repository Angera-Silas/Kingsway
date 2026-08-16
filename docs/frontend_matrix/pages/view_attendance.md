# view_attendance.php

- **File**: `pages/view_attendance.php`
- **Controller**: `view_attendance.js`
- **Roles**: —
- **Sidebar item(s)**: —

## Init / auth

- `AuthContext.ready()` awaited: `Y`
- Permission/RBAC guard: `N`
- Raw `fetch`/`XMLHttpRequest`/`axios`: `0`

## API methods used

| API method | Payload keys (sent) | Flags | Endpoint | Handler | Status |
|---|---|---|---|---|---|
| `attendance.getAcademicSummary` | — | — | GET /attendance/academic-summary | Attendance::getAcademicSummary | ok |
| `attendance.getDailyRegister` | — | — | GET /attendance/daily-register | Attendance::getDailyRegister | ok |
| `attendance.getBoardingSummary` | `date` | — | GET /attendance/boarding-summary | Attendance::getBoardingSummary | ok |
| `attendance.getPermissions` | — | — | GET /attendance/permissions | Attendance::getPermissions | ok |
| `attendance.getStudentSummary` | — | — | GET /attendance/student-summary?student_id= | Attendance::getStudentSummary | ok |
| `attendance.getStudentHistory` | — | — | GET /attendance/student-history?student_id= | Attendance::getStudentHistory | ok |

## Backend params (expected)

| Handler | Input keys ($data/$_POST/$_GET) | Path id | Data sources (views/procs/tables) |
|---|---|---|---|
| Attendance::getAcademicSummary | `date_from`, `date_to`, `session_id`, `stream_id`, `status` | — | vw_student_attendance_summary, student_academic_enrollments, student_attendance, vw_student_attendance_summary |
| Attendance::getDailyRegister | `date`, `session_id`, `stream_id` | — | academic_year_class_streams, academic_year_classes, attendance_sessions, classes, persons, streams, student_academic_enrollments, student_attendance, student_types, students |
| Attendance::getBoardingSummary | `date` | — | attendance_sessions, boarding_attendance, dormitories, dormitory_assignments, student_academic_enrollments |
| Attendance::getPermissions | `student_id`, `status`, `active`, `stream_id`, `search`, `date_from`, `date_to`, `permission_type_id` | — | persons, staff, student_permission_types, student_permissions, student_types, students, users |
| Attendance::getStudentSummary | `studentId` | — | — |
| Attendance::getStudentHistory | `studentId` | — | — |

## Response shape (data keys consumed)

- `attendance.getBoardingSummary -> summary`

## UI / security

- `innerHTML`/`insertAdjacentHTML` assignments: `17` (with interpolation: `0`)
- `escapeHtml()` calls: `23` — XSS check: `PASS`
- Bootstrap modal usage: `1`
- Payload/backend param match: `OK`
- Fix flags: none
- Info flags: `TEMPLATE_FRAGMENT`, `ESCAPED_LITERAL_HTML`
- Fix task: `—`

## End-to-end trace

> collect → store → analyse → display: page payload keys → API endpoint → controller method → service passthrough → SQL views/procs/tables (rows above). This is machine-derived; the interactive flow (form submit → render) needs human sign-off.

## Review checklist

| Check | Status | Notes |
|---|---|---|
| Init: `AuthContext.ready()` | ✅ | machine |
| Data load: `window.API.*`, no raw fetch | ✅ | machine |
| Params: sent ≈ backend `$data` | ⚠️ | heuristic |
| Response: unwrapped `data` handled | ✅ | heuristic |
| UI: Bootstrap + `escapeHtml` | ✅ | machine |
| Responsive @375/768/1200px | ⚠️ | **manual sign-off required** |
