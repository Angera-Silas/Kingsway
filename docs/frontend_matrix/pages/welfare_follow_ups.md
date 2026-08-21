# welfare_follow_ups.php

- **File**: `pages/welfare_follow_ups.php`
- **Controller**: `welfare_follow_ups.js`
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
| `GET /counseling/session?type=followup` | — | — |
| `GET /counseling/session/` | — | — |
| `POST /counseling/session` | — | — |
| `GET /students?status=active&limit=500` | — | — |
| `GET /staff?limit=200` | — | — |

## Backend params (expected)

| Handler | Input keys ($data/$_POST/$_GET) | Path id | Data sources (views/procs/tables) |
|---|---|---|---|
| Counseling::getSession | `search`, `status`, `category`, `date`, `page`, `limit` | `id` | academic_year_class_streams, academic_year_classes, classes, counseling_cases, counseling_sessions, persons, staff, student_academic_enrollments, students, users |
| Counseling::postSession | `counselee_type`, `counseleeType`, `student_id`, `staff_id`, `session_date`, `session_datetime`, `session_type`, `category`, `summary`, `session_notes`, `issue_summary`, `action_plan`, `follow_up_date`, `confidential_notes`, `case_id`, `title`, `case_type`, `priority`, `status`, `description`, `referral_source`, `assigned_to` | — | counseling_cases, counseling_sessions |
| Students::getStudent | `class_id`, `stream_id`, `status`, `gender`, `student_type_id`, `fee_status` | `id` | vw_student_fee_balances, academic_year_class_streams, academic_year_classes, classes, parents, persons, streams, student_academic_enrollments, student_parents, student_types, students, vw_student_fee_balances |
| Staff::get | — | `id` | staff_experience, staff_qualifications, departments, persons, roles, staff, staff_categories, staff_department_assignments, staff_payroll_profiles, staff_types, user_roles, users |

## Response shape (data keys consumed)

_(no post-await `.prop` consumption detected)_

## UI / security

- `innerHTML`/`insertAdjacentHTML` assignments: `6` (with interpolation: `1`)
- `escapeHtml()` calls: `7` — XSS check: `PASS`
- Bootstrap modal usage: `2`
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
| Response: unwrapped `data` handled | ⚠️ | heuristic |
| UI: Bootstrap + `escapeHtml` | ✅ | machine |
| Responsive @375/768/1200px | ⚠️ | **manual sign-off required** |
