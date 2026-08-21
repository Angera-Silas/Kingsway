# manage_staff_children.php

- **File**: `pages/manage_staff_children.php`
- **Controller**: `staff_children.js`
- **Roles**: —
- **Sidebar item(s)**: —

## Init / auth

- `AuthContext.ready()` awaited: `N`
- Permission/RBAC guard: `N`
- Raw `fetch`/`XMLHttpRequest`/`axios`: `0`

## API methods used

| API method | Payload keys (sent) | Flags | Endpoint | Handler | Status |
|---|---|---|---|---|---|
| `staff.getChildFeeConfig` | — | — | GET /staff/children-fee-config | Staff::getChildFeeConfig | ok |
| `staff.list` | — | — | GET /staff | Staff::list | ok |
| `students.list` | `status`, `limit` | — | GET /students/student | Students::list | ok |
| `staff.getStaffChildren` | — | — | GET /staff/children-list?staff_id= | Staff::getStaffChildren | ok |
| `staff.updateStaffChild` | — | — | PUT /staff/children-update/ | Staff::updateStaffChild | ok |
| `staff.addStaffChild` | — | — | POST /staff/children-add | Staff::addStaffChild | ok |
| `staff.removeStaffChild` | — | — | DELETE /staff/children-remove/ | Staff::removeStaffChild | ok |
| `staff.calculateChildFeeDeductions` | — | — | GET /staff/children-calculate-deductions?staff_id=&month=&year= | Staff::calculateChildFeeDeductions | ok |

## Backend params (expected)

_(no backend handler resolved for param extraction)_

## Response shape (data keys consumed)

_(no post-await `.prop` consumption detected)_

## UI / security

- `innerHTML`/`insertAdjacentHTML` assignments: `12` (with interpolation: `1`)
- `escapeHtml()` calls: `17` — XSS check: `PASS`
- Bootstrap modal usage: `4`
- Payload/backend param match: `OK`
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
