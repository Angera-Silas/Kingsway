# staff_attendance.php

- **File**: `pages/staff_attendance.php`
- **Controller**: `staff_attendance.js`
- **Roles**: —
- **Sidebar item(s)**: —

## Init / auth

- `AuthContext.ready()` awaited: `Y`
- Permission/RBAC guard: `N`
- Raw `fetch`/`XMLHttpRequest`/`axios`: `0`

## API methods used

| API method | Payload keys (sent) | Flags | Endpoint | Handler | Status |
|---|---|---|---|---|---|
| `attendance.getDutyTypes` | — | — | GET /attendance/duty-types | Attendance::getDutyTypes | ok |
| `attendance.getStaffToday` | `date`, `department_id` | — | GET /attendance/staff-today | Attendance::getStaffToday | ok |
| `attendance.getStaffReport` | — | — | GET /attendance/staff-report | Attendance::getStaffReport | ok |
| `attendance.markStaff` | `date`, `shift`, `attendance` | — | POST /attendance/mark-staff | Attendance::markStaff | ok |
| `attendance.getStaffSummary` | — | — | GET /attendance/staff-summary?staff_id= | Attendance::getStaffSummary | ok |
| `attendance.getStaffHistory` | — | — | GET /attendance/staff-history?staff_id= | Attendance::getStaffHistory | ok |

## Backend params (expected)

| Handler | Input keys ($data/$_POST/$_GET) | Path id | Data sources (views/procs/tables) |
|---|---|---|---|
| Attendance::getDutyTypes | — | — | staff_duty_types |
| Attendance::getStaffToday | `date`, `department_id` | — | vw_staff_daily_register, vw_staff_daily_register |
| Attendance::getStaffReport | `date_from`, `date_to`, `department_id`, `duty_type_id`, `status` | — | vw_staff_daily_register, departments, persons, staff, staff_duty_roster, staff_employment_profiles, vw_staff_daily_register |
| Attendance::getStaffSummary | `staff_id`, `staffId` | — | staff, users |
| Attendance::getStaffHistory | `staff_id` | — | — |

## Response shape (data keys consumed)

- `attendance.getStaffToday -> staff`

## UI / security

- `innerHTML`/`insertAdjacentHTML` assignments: `16` (with interpolation: `1`)
- `escapeHtml()` calls: `18` — XSS check: `PASS`
- Bootstrap modal usage: `1`
- Payload/backend param match: `OK`
- Fix flags: none
- Info flags: none
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
