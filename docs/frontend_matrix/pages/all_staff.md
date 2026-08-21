# all_staff.php

- **File**: `pages/all_staff.php`
- **Controller**: `all_staff.js`
- **Roles**: —
- **Sidebar item(s)**: —

## Init / auth

- `AuthContext.ready()` awaited: `N`
- Permission/RBAC guard: `N`
- Raw `fetch`/`XMLHttpRequest`/`axios`: `0`

## API methods used

| API method | Payload keys (sent) | Flags | Endpoint | Handler | Status |
|---|---|---|---|---|---|
| `staff.index` | — | — | GET /staff/index | Staff::index | ok |
| `staff.getDepartments` | — | — | GET /staff/departments-get/<br>GET /staff/departments-get | Staff::getDepartments<br>Staff::getDepartments | ok<br>ok |
| `staff.get` | — | — | GET /staff/<br>GET /staff | Staff::get<br>Staff::get | ok<br>ok |
| `staff.delete` | — | — | DELETE /staff/ | Staff::delete | ok |

## Backend params (expected)

| Handler | Input keys ($data/$_POST/$_GET) | Path id | Data sources (views/procs/tables) |
|---|---|---|---|
| Staff::index | — | — | departments, persons, roles, staff, staff_categories, staff_department_assignments, staff_payroll_profiles, staff_types, user_roles, users |
| Staff::getDepartments | — | `id` | — |
| Staff::get | — | `id` | staff_experience, staff_qualifications, departments, persons, roles, staff, staff_categories, staff_department_assignments, staff_payroll_profiles, staff_types, user_roles, users |
| Staff::delete | — | `id` | staff |

## Response shape (data keys consumed)

- `staff.get -> name`
- `staff.get -> first_name`
- `staff.get -> last_name`
- `staff.get -> photo`
- `staff.get -> status`

## UI / security

- `innerHTML`/`insertAdjacentHTML` assignments: `6` (with interpolation: `1`)
- `escapeHtml()` calls: `31` — XSS check: `PASS`
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
