# student_rewards.php

- **File**: `pages/student_rewards.php`
- **Controller**: `student_rewards.js`
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
| `GET /activities/achievements` | — | — |
| `GET /students/discipline-get?type=reward` | — | — |
| `GET /activities/achievements/` | — | — |
| `POST /activities/achievements` | — | — |
| `GET /students/list` | — | — |
| `GET /students` | — | — |

## Backend params (expected)

| Handler | Input keys ($data/$_POST/$_GET) | Path id | Data sources (views/procs/tables) |
|---|---|---|---|
| Students::getDisciplineGet | `student_id`, `search`, `status`, `severity`, `class_id` | `id` | academic_year_class_streams, academic_year_classes, academic_year_terms, classes, discipline_incidents, persons, streams, student_academic_enrollments, students |
| Students::getStudent | `class_id`, `stream_id`, `status`, `gender`, `student_type_id`, `fee_status` | `id` | vw_student_fee_balances, academic_year_class_streams, academic_year_classes, classes, parents, persons, streams, student_academic_enrollments, student_parents, student_types, students, vw_student_fee_balances |

## Response shape (data keys consumed)

_(no post-await `.prop` consumption detected)_

## UI / security

- `innerHTML`/`insertAdjacentHTML` assignments: `5` (with interpolation: `1`)
- `escapeHtml()` calls: `8` — XSS check: `PASS`
- Bootstrap modal usage: `3`
- Payload/backend param match: `NA`
- Fix flags: `UNRESOLVED_ENDPOINT`
- Info flags: `NO_AUTH_GUARD`
- Fix task: `FIX-3-0063`

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
