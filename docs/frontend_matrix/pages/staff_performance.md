# staff_performance.php

- **File**: `pages/staff_performance.php`
- **Controller**: `staff_performance.js`
- **Roles**: —
- **Sidebar item(s)**: —

## Init / auth

- `AuthContext.ready()` awaited: `Y`
- Permission/RBAC guard: `N`
- Raw `fetch`/`XMLHttpRequest`/`axios`: `0`

## API methods used

| API method | Payload keys (sent) | Flags | Endpoint | Handler | Status |
|---|---|---|---|---|---|
| `staff.index` | — | — | GET /staff/index | Staff::index | ok |
| `academic.getCurrentAcademicYear` | — | — | GET /academic/years/current | Academic::getCurrentAcademicYear | ok |
| `staff.getPerformanceReviewHistory` | — | — | GET /staff/performance-review-history?staff_id= | Staff::getPerformanceReviewHistory | ok |
| `staff.getAcademicKPISummary` | — | — | GET /staff/performance-academic-kpi-summary?staff_id= | Staff::getAcademicKPISummary | ok |
| `staff.generatePerformanceReport` | — | — | GET /staff/performance-generate-report?review_id= | Staff::generatePerformanceReport | ok |

## Backend params (expected)

| Handler | Input keys ($data/$_POST/$_GET) | Path id | Data sources (views/procs/tables) |
|---|---|---|---|
| Staff::index | — | — | departments, persons, roles, staff, staff_categories, staff_department_assignments, staff_payroll_profiles, staff_types, user_roles, users |
| Staff::getPerformanceReviewHistory | `staff_id` | `id` | staff, users |
| Staff::getAcademicKPISummary | — | `id` | — |

## Response shape (data keys consumed)

- `staff.index -> staff`

## UI / security

- `innerHTML`/`insertAdjacentHTML` assignments: `5` (with interpolation: `0`)
- `escapeHtml()` calls: `0` — XSS check: `PASS`
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
| Response: unwrapped `data` handled | ✅ | heuristic |
| UI: Bootstrap + `escapeHtml` | ✅ | machine |
| Responsive @375/768/1200px | ⚠️ | **manual sign-off required** |
