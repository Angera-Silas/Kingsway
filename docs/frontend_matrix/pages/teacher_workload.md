# teacher_workload.php

- **File**: `pages/teacher_workload.php`
- **Controller**: `teacher_workload.js`
- **Roles**: —
- **Sidebar item(s)**: —

## Init / auth

- `AuthContext.ready()` awaited: `Y`
- Permission/RBAC guard: `N`
- Raw `fetch`/`XMLHttpRequest`/`axios`: `0`

## API methods used

| API method | Payload keys (sent) | Flags | Endpoint | Handler | Status |
|---|---|---|---|---|---|
| `staff.getTeachers` | — | — | GET /staff/teachers | Staff::getTeachers | ok |
| `staff.getDepartments` | — | — | GET /staff/departments-get/<br>GET /staff/departments-get | Staff::getDepartments<br>Staff::getDepartments | ok<br>ok |
| `staff.getWorkload` | — | — | GET /staff/workload-get/<br>GET /staff/workload-get | Staff::getWorkload<br>Staff::getWorkload | ok<br>ok |

## Backend params (expected)

| Handler | Input keys ($data/$_POST/$_GET) | Path id | Data sources (views/procs/tables) |
|---|---|---|---|
| Staff::getTeachers | — | — | academic_year_class_learning_area_teachers, academic_year_class_learning_areas, academic_year_classes, academic_years, classes, department_attendance_rules, departments, learning_areas, persons, roles, school_levels, staff, staff_categories, staff_department_assignments, staff_types, user_roles, users |
| Staff::getDepartments | — | `id` | — |

## Response shape (data keys consumed)

_(no post-await `.prop` consumption detected)_

## UI / security

- `innerHTML`/`insertAdjacentHTML` assignments: `3` (with interpolation: `0`)
- `escapeHtml()` calls: `2` — XSS check: `PASS`
- Bootstrap modal usage: `0`
- Payload/backend param match: `NA`
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
