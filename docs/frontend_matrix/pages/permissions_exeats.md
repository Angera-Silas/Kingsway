# permissions_exeats.php

- **File**: `pages/permissions_exeats.php`
- **Controller**: `permissions_exeats.js`
- **Roles**: —
- **Sidebar item(s)**: —

## Init / auth

- `AuthContext.ready()` awaited: `Y`
- Permission/RBAC guard: `N`
- Raw `fetch`/`XMLHttpRequest`/`axios`: `0`

## API methods used

| API method | Payload keys (sent) | Flags | Endpoint | Handler | Status |
|---|---|---|---|---|---|
| `attendance.getPermissionTypes` | — | — | GET /attendance/permission-types | Attendance::getPermissionTypes | ok |
| `students.getAll` | `limit`, `status` | — | GET /students/student | Students::getAll | ok |
| `attendance.getPermissions` | `status`, `permission_type_id`, `search`, `date_from`, `date_to` | — | GET /attendance/permissions | Attendance::getPermissions | ok |
| `attendance.updatePermission` | — | — | PUT /attendance/permissions/ | Attendance::updatePermission | ok |
| `attendance.createPermission` | — | — | POST /attendance/permissions | Attendance::createPermission | ok |

## Backend params (expected)

| Handler | Input keys ($data/$_POST/$_GET) | Path id | Data sources (views/procs/tables) |
|---|---|---|---|
| Attendance::getPermissionTypes | — | `id` | student_permission_types |
| Attendance::getPermissions | `student_id`, `status`, `active`, `stream_id`, `search`, `date_from`, `date_to`, `permission_type_id` | — | persons, staff, student_permission_types, student_permissions, student_types, students, users |

## Response shape (data keys consumed)

_(no post-await `.prop` consumption detected)_

## UI / security

- `innerHTML`/`insertAdjacentHTML` assignments: `6` (with interpolation: `0`)
- `escapeHtml()` calls: `9` — XSS check: `PASS`
- Bootstrap modal usage: `0`
- Payload/backend param match: `OK`
- Fix flags: none
- Info flags: `ESCAPED_LITERAL_HTML`
- Fix task: `—`

## End-to-end trace

> collect → store → analyse → display: page payload keys → API endpoint → controller method → service passthrough → SQL views/procs/tables (rows above). This is machine-derived; the interactive flow (form submit → render) needs human sign-off.

## Review checklist

| Check | Status | Notes |
|---|---|---|
| Init: `AuthContext.ready()` | ✅ | machine |
| Data load: `window.API.*`, no raw fetch | ✅ | machine |
| Params: sent ≈ backend `$data` | ⚠️ | heuristic |
| Response: unwrapped `data` handled | ⚠️ | heuristic |
| UI: Bootstrap + `escapeHtml` | ✅ | machine |
| Responsive @375/768/1200px | ⚠️ | **manual sign-off required** |
