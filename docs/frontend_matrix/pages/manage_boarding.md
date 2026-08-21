# manage_boarding.php

- **File**: `pages/manage_boarding.php`
- **Controller**: `boarding.js`
- **Roles**: —
- **Sidebar item(s)**: —

## Init / auth

- `AuthContext.ready()` awaited: `N`
- Permission/RBAC guard: `N`
- Raw `fetch`/`XMLHttpRequest`/`axios`: `0`

## API methods used

_(no `API.<module>.<method>` calls detected)_

## Direct `callAPI` endpoints

| Endpoint | Payload keys (sent) | Flags |
|---|---|---|
| `GET /boarding/stats` | — | — |
| `GET /boarding/dormitories` | — | — |
| `GET /boarding/activity` | — | — |
| `GET /boarding/dormitories/` | — | — |
| `POST /boarding/dormitories` | — | — |
| `GET /boarding/exeats?status=pending` | — | — |
| `GET /boarding/exeats/` | — | — |
| `GET /staff?status=active&limit=200` | — | — |
| `GET /boarding/students` | — | — |
| `POST /students/boarding-assign-dorm` | — | — |
| `POST /health/sick-bay` | — | — |
| `GET /parent-portal/dashboard` | — | — |
| `GET /boarding/exeats` | — | — |
| `POST /boarding/exeats` | — | — |

## Backend params (expected)

| Handler | Input keys ($data/$_POST/$_GET) | Path id | Data sources (views/procs/tables) |
|---|---|---|---|
| Boarding::getStats | — | — | boarding_attendance, dormitories, student_boarding_notes, student_permissions |
| Boarding::getDormitories | — | — | dormitories, dormitory_assignments, persons, staff |
| Boarding::getActivity | — | — | boarding_attendance, persons, student_permissions, students |
| Boarding::postDormitories | `name`, `patron_id`, `gender`, `capacity`, `location`, `description`, `facilities` | — | dormitories |
| Boarding::getExeats | `status` | — | dormitories, dormitory_assignments, persons, student_academic_enrollments, student_permission_types, student_permissions, students |
| Staff::get | — | `id` | staff_experience, staff_qualifications, departments, persons, roles, staff, staff_categories, staff_department_assignments, staff_payroll_profiles, staff_types, user_roles, users |
| Boarding::getStudents | `dormitory_id`, `search` | — | academic_year_class_streams, academic_year_classes, boarding_attendance, classes, dormitories, dormitory_assignments, persons, student_academic_enrollments, students |
| Students::postBoardingAssignDorm | `student_id`, `dormitory_id`, `bed_number`, `allocation_date`, `notes` | — | dormitory_assignments, student_academic_enrollments |
| Health::postSickBay | `student_id`, `complaint`, `visit_date`, `visit_time`, `symptoms`, `treatment_given`, `medication_given`, `referred_to_hospital` | — | student_health_visits |
| ParentPortal::getDashboard | — | — | vw_payment_transactions_with_amount, vw_student_fee_balances, academic_year_class_streams, academic_year_classes, classes, persons, school_levels, student_academic_enrollments, student_parents, students, vw_payment_transactions_with_amount, vw_student_fee_balances |
| Boarding::postExeats | `student_id`, `permission_type_id`, `departure_date`, `start_date`, `return_date`, `end_date`, `reason` | — | student_permissions |

## Response shape (data keys consumed)

- `direct.callAPI -> total_capacity`
- `direct.callAPI -> assigned_beds`
- `direct.callAPI -> available_beds`
- `direct.callAPI -> occupancy_rate`
- `direct.callAPI -> urgent_notes`
- `direct.callAPI -> on_leave`
- `direct.callAPI -> disciplinary_cases`
- `direct.callAPI -> roll_call_pct`
- `direct.callAPI -> total_boarders`
- `direct.callAPI -> present_tonight`
- `direct.callAPI -> pending_leaves`
- `direct.callAPI -> children`

## UI / security

- `innerHTML`/`insertAdjacentHTML` assignments: `22` (with interpolation: `0`)
- `escapeHtml()` calls: `28` — XSS check: `PASS`
- Bootstrap modal usage: `9`
- Payload/backend param match: `NA`
- Fix flags: none
- Info flags: `NO_AUTH_GUARD`, `ESCAPED_LITERAL_HTML`
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
