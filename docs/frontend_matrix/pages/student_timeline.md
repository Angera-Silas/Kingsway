# student_timeline.php

- **File**: `pages/student_timeline.php`
- **Controller**: `student_timeline.js`
- **Roles**: `System Administrator`
- **Sidebar item(s)**: `100121`

## Init / auth

- `AuthContext.ready()` awaited: `N`
- Permission/RBAC guard: `Y`
- Raw `fetch`/`XMLHttpRequest`/`axios`: `0`

## API methods used

_(no `API.<module>.<method>` calls detected)_

## Direct `callAPI` endpoints

| Endpoint | Payload keys (sent) | Flags |
|---|---|---|
| `GET /students/all` | — | — |
| `GET /academic/student-timeline/` | — | — |
| `POST /academic/transfer-requests` | `student_id`, `transfer_type` | — |

## Backend params (expected)

| Handler | Input keys ($data/$_POST/$_GET) | Path id | Data sources (views/procs/tables) |
|---|---|---|---|
| Students::getStudent | `class_id`, `stream_id`, `status`, `gender`, `student_type_id`, `fee_status` | `id` | vw_student_fee_balances, academic_year_class_streams, academic_year_classes, classes, parents, persons, streams, student_academic_enrollments, student_parents, student_types, students, vw_student_fee_balances |
| Academic::getStudentTimeline | — | `id` | vw_student_fee_balances, academic_year_class_streams, academic_year_classes, academic_year_fee_schedules, academic_year_terms, academic_years, classes, discipline_incidents, fee_catalog, fee_credit_notes, learning_areas, payments, persons, streams, student_academic_enrollments, student_attendance, student_clearances, student_fee_obligations, student_transitions, student_types, students, term_subject_scores, terms, vw_student_fee_balances |
| Academic::postTransferRequests | `student_id`, `transfer_type`, `reason` | — | vw_student_fee_balances, academic_years, audit_logs, student_academic_enrollments, student_clearances, student_transitions, vw_student_fee_balances |

## Response shape (data keys consumed)

_(no post-await `.prop` consumption detected)_

## UI / security

- `innerHTML`/`insertAdjacentHTML` assignments: `16` (with interpolation: `2`)
- `escapeHtml()` calls: `15` — XSS check: `PASS`
- Bootstrap modal usage: `1`
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
| Response: unwrapped `data` handled | ⚠️ | heuristic |
| UI: Bootstrap + `escapeHtml` | ✅ | machine |
| Responsive @375/768/1200px | ⚠️ | **manual sign-off required** |
