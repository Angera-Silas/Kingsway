# sanctions.php

- **File**: `pages/sanctions.php`
- **Controller**: `sanctions.js`
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
| `GET /students/discipline-get?status=active` | — | — |
| `GET /students` | — | — |
| `POST /students/discipline-get` | `student_id`, `sanction_type`, `start_date`, `end_date`, `parent_notified` | — |
| `GET /students/discipline-get/` | `status`, `lift_reason` | — |

## Backend params (expected)

| Handler | Input keys ($data/$_POST/$_GET) | Path id | Data sources (views/procs/tables) |
|---|---|---|---|
| Students::getDisciplineGet | `student_id`, `search`, `status`, `severity`, `class_id` | `id` | academic_year_class_streams, academic_year_classes, academic_year_terms, classes, discipline_incidents, persons, streams, student_academic_enrollments, students |
| Students::getStudent | `class_id`, `stream_id`, `status`, `gender`, `student_type_id`, `fee_status` | `id` | vw_student_fee_balances, academic_year_class_streams, academic_year_classes, classes, parents, persons, streams, student_academic_enrollments, student_parents, student_types, students, vw_student_fee_balances |
| Students::postStudent | `parent_info`, `gender`, `status`, `is_sponsored`, `initial_payment_amount`, `skip_payment_check`, `first_name`, `middle_name`, `last_name`, `date_of_birth`, `photo_url`, `admission_no`, `student_type_id`, `admission_date`, `assessment_number`, `assessment_status`, `nemis_number`, `nemis_status`, `application_id`, `blood_group`, `stream_id`, `sponsor_waiver_percentage`, `payment_method`, `payment_reference`, `receipt_no` | `id` | persons, students |

## Response shape (data keys consumed)

_(no post-await `.prop` consumption detected)_

## UI / security

- `innerHTML`/`insertAdjacentHTML` assignments: `5` (with interpolation: `0`)
- `escapeHtml()` calls: `10` — XSS check: `PASS`
- Bootstrap modal usage: `2`
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
| Response: unwrapped `data` handled | ⚠️ | heuristic |
| UI: Bootstrap + `escapeHtml` | ✅ | machine |
| Responsive @375/768/1200px | ⚠️ | **manual sign-off required** |
