# staff_lifecycle.php

- **File**: `pages/staff_lifecycle.php`
- **Controller**: `staff_lifecycle.js`
- **Roles**: —
- **Sidebar item(s)**: —

## Init / auth

- `AuthContext.ready()` awaited: `Y`
- Permission/RBAC guard: `N`
- Raw `fetch`/`XMLHttpRequest`/`axios`: `0`

## API methods used

| API method | Payload keys (sent) | Flags | Endpoint | Handler | Status |
|---|---|---|---|---|---|
| `staffLifecycle.createAction` | — | — | POST /stafflifecycle/action | StaffLifecycle::createAction | ok |
| `staffLifecycle.list` | `status` | — | GET /stafflifecycle | StaffLifecycle::list | ok |
| `staffLifecycle.referenceData` | — | — | GET /stafflifecycle/reference-data | StaffLifecycle::referenceData | ok |
| `staffLifecycle.get` | — | — | GET /stafflifecycle/ | StaffLifecycle::get | ok |

## Backend params (expected)

| Handler | Input keys ($data/$_POST/$_GET) | Path id | Data sources (views/procs/tables) |
|---|---|---|---|
| StaffLifecycle::get | — | `id` | vw_staff_onboarding_progress, audit_logs, departments, persons, staff, staff_department_assignments, the, users, vw_staff_onboarding_progress |

## Response shape (data keys consumed)

- `staffLifecycle.get -> staff`
- `staffLifecycle.get -> timeline`

## UI / security

- `innerHTML`/`insertAdjacentHTML` assignments: `8` (with interpolation: `5`)
- `escapeHtml()` calls: `24` — XSS check: `PASS`
- Bootstrap modal usage: `3`
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
