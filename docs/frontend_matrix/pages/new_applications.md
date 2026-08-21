# new_applications.php

- **File**: `pages/new_applications.php`
- **Controller**: `new_applications.js`
- **Roles**: —
- **Sidebar item(s)**: —

## Init / auth

- `AuthContext.ready()` awaited: `Y`
- Permission/RBAC guard: `N`
- Raw `fetch`/`XMLHttpRequest`/`axios`: `0`

## API methods used

| API method | Payload keys (sent) | Flags | Endpoint | Handler | Status |
|---|---|---|---|---|---|
| `admission.getPlacementClasses` | — | — | GET /admission/placement-classes | Admission::getPlacementClasses | ok |
| `academic.listClasses` | `limit` | — | GET /academic/classes-list | Academic::listClasses | ok |

## Backend params (expected)

| Handler | Input keys ($data/$_POST/$_GET) | Path id | Data sources (views/procs/tables) |
|---|---|---|---|
| Admission::getPlacementClasses | — | — | academic_year_class_streams, academic_year_classes, classes, streams, student_academic_enrollments |

## Response shape (data keys consumed)

- `admission.getPlacementClasses -> classes`
- `academic.listClasses -> classes`

## UI / security

- `innerHTML`/`insertAdjacentHTML` assignments: `10` (with interpolation: `0`)
- `escapeHtml()` calls: `25` — XSS check: `PASS`
- Bootstrap modal usage: `5`
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
| Response: unwrapped `data` handled | ✅ | heuristic |
| UI: Bootstrap + `escapeHtml` | ✅ | machine |
| Responsive @375/768/1200px | ⚠️ | **manual sign-off required** |
