# staff_role_assignments.php

- **File**: `pages/staff_role_assignments.php`
- **Controller**: `staff_role_assignments.js`
- **Roles**: —
- **Sidebar item(s)**: —

## Init / auth

- `AuthContext.ready()` awaited: `Y`
- Permission/RBAC guard: `N`
- Raw `fetch`/`XMLHttpRequest`/`axios`: `0`

## API methods used

| API method | Payload keys (sent) | Flags | Endpoint | Handler | Status |
|---|---|---|---|---|---|
| `staff.list` | `limit` | — | GET /staff | Staff::list | ok |
| `staff.getAvailableRoles` | — | — | GET /staff/available-roles | Staff::getAvailableRoles | ok |
| `staff.getRoleAssignments` | — | — | GET /staff/role-assignments | Staff::getRoleAssignments | ok |
| `staff.assignStaffRole` | `staff_id`, `role_id` | — | POST /staff/role-assignments | Staff::assignStaffRole | ok |
| `staff.revokeStaffRole` | — | — | DELETE /staff/role-assignments/ | Staff::revokeStaffRole | ok |

## Backend params (expected)

| Handler | Input keys ($data/$_POST/$_GET) | Path id | Data sources (views/procs/tables) |
|---|---|---|---|
| Staff::getAvailableRoles | — | — | roles |
| Staff::getRoleAssignments | `staff_id` | `id` | roles, staff, user_roles, users |

## Response shape (data keys consumed)

- `staff.list -> staff`

## UI / security

- `innerHTML`/`insertAdjacentHTML` assignments: `3` (with interpolation: `3`)
- `escapeHtml()` calls: `6` — XSS check: `PASS`
- Bootstrap modal usage: `0`
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
