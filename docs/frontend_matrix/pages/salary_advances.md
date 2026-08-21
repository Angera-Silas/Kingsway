# salary_advances.php

- **File**: `pages/salary_advances.php`
- **Controller**: `salary_advances.js`
- **Roles**: `System Administrator`
- **Sidebar item(s)**: `100118`

## Init / auth

- `AuthContext.ready()` awaited: `N`
- Permission/RBAC guard: `Y`
- Raw `fetch`/`XMLHttpRequest`/`axios`: `0`

## API methods used

_(no `API.<module>.<method>` calls detected)_

## Direct `callAPI` endpoints

| Endpoint | Payload keys (sent) | Flags |
|---|---|---|
| `GET /finance/salary-advances` | — | — |
| `GET /staff` | — | — |
| `POST /finance/salary-advances` | `staff_id`, `requested_amount` | — |
| `GET /finance/salary-advances/` | — | — |

## Backend params (expected)

| Handler | Input keys ($data/$_POST/$_GET) | Path id | Data sources (views/procs/tables) |
|---|---|---|---|
| Finance::getSalaryAdvances | `staff_id`, `status` | — | persons, staff, staff_salary_advances, users |
| Staff::get | — | `id` | staff_experience, staff_qualifications, departments, persons, roles, staff, staff_categories, staff_department_assignments, staff_payroll_profiles, staff_types, user_roles, users |
| Finance::postSalaryAdvances | `staff_id`, `requested_amount` | — | staff_salary_advances, staff |

## Response shape (data keys consumed)

- `direct.callAPI -> advances`
- `direct.callAPI -> map`

## UI / security

- `innerHTML`/`insertAdjacentHTML` assignments: `8` (with interpolation: `1`)
- `escapeHtml()` calls: `6` — XSS check: `PASS`
- Bootstrap modal usage: `2`
- Payload/backend param match: `NA`
- Fix flags: none
- Info flags: none
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
