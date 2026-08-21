# boarding_roll_call.php

- **File**: `pages/boarding_roll_call.php`
- **Controller**: `boarding_roll_call.js`
- **Roles**: —
- **Sidebar item(s)**: —

## Init / auth

- `AuthContext.ready()` awaited: `N`
- Permission/RBAC guard: `N`
- Raw `fetch`/`XMLHttpRequest`/`axios`: `0`

## API methods used

| API method | Payload keys (sent) | Flags | Endpoint | Handler | Status |
|---|---|---|---|---|---|
| `attendance.getDormitories` | — | — | GET /attendance/dormitories | Attendance::getDormitories | ok |
| `attendance.getSessions` | `type`, `day` | — | GET /attendance/sessions | Attendance::getSessions | ok |
| `attendance.getDormitoryStudents` | `dormitory_id`, `session_id`, `date` | — | GET /attendance/dormitory-students | Attendance::getDormitoryStudents | ok |
| `attendance.isSchoolDay` | `date` | — | GET /attendance/is-school-day | Attendance::isSchoolDay | ok |
| `attendance.markBoarding` | `dormitory_id`, `session_id`, `date` | — | POST /attendance/mark-boarding | Attendance::markBoarding | ok |
| `attendance.getBoardingSummary` | `date` | — | GET /attendance/boarding-summary | Attendance::getBoardingSummary | ok |

## Backend params (expected)

| Handler | Input keys ($data/$_POST/$_GET) | Path id | Data sources (views/procs/tables) |
|---|---|---|---|
| Attendance::getDormitories | — | — | dormitories, dormitory_assignments, persons, staff |
| Attendance::getSessions | `type`, `day` | — | attendance_sessions |
| Attendance::getDormitoryStudents | `dormitory_id`, `date`, `session_id` | `id` | academic_year_class_streams, academic_year_classes, boarding_attendance, classes, dormitories, dormitory_assignments, persons, staff, student_academic_enrollments, student_permission_types, student_permissions, students |
| Attendance::getBoardingSummary | `date` | — | attendance_sessions, boarding_attendance, dormitories, dormitory_assignments, student_academic_enrollments |

## Response shape (data keys consumed)

- `attendance.getSessions -> some`
- `attendance.getSessions -> forEach`
- `attendance.getSessions -> length`
- `attendance.getSessions -> find`
- `attendance.getDormitoryStudents -> students`
- `attendance.isSchoolDay -> reason`
- `attendance.isSchoolDay -> calendar_event`
- `attendance.getBoardingSummary -> summary`

## UI / security

- `innerHTML`/`insertAdjacentHTML` assignments: `9` (with interpolation: `0`)
- `escapeHtml()` calls: `13` — XSS check: `PASS`
- Bootstrap modal usage: `0`
- Payload/backend param match: `OK`
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
