# sick_bay.php

- **File**: `pages/sick_bay.php`
- **Controller**: `sick_bay.js`
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
| `GET /health/summary` | — | — |
| `GET /health/sick-bay?status=dismissed&date=` | — | — |
| `GET /health/sick-bay` | — | — |
| `GET /health/sick-bay/` | — | — |
| `POST /health/sick-bay` | — | — |
| `GET /students/list` | — | — |
| `GET /students` | — | — |

## Backend params (expected)

| Handler | Input keys ($data/$_POST/$_GET) | Path id | Data sources (views/procs/tables) |
|---|---|---|---|
| Health::getSummary | — | — | student_health_records, student_health_visits, student_vaccinations |
| Health::getSickBay | `status`, `date` | — | student_health_visits, students |
| Health::postSickBay | `student_id`, `complaint`, `visit_date`, `visit_time`, `symptoms`, `treatment_given`, `medication_given`, `referred_to_hospital` | — | student_health_visits |
| Students::getStudent | `class_id`, `stream_id`, `status`, `gender`, `student_type_id`, `fee_status` | `id` | vw_student_fee_balances, academic_year_class_streams, academic_year_classes, classes, parents, persons, streams, student_academic_enrollments, student_parents, student_types, students, vw_student_fee_balances |

## Response shape (data keys consumed)

- `direct.callAPI -> active_sick_bay_visits`
- `direct.callAPI -> visits_today`
- `direct.callAPI -> referred_to_hospital`

## UI / security

- `innerHTML`/`insertAdjacentHTML` assignments: `5` (with interpolation: `1`)
- `escapeHtml()` calls: `6` — XSS check: `PASS`
- Bootstrap modal usage: `3`
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
| Response: unwrapped `data` handled | ✅ | heuristic |
| UI: Bootstrap + `escapeHtml` | ✅ | machine |
| Responsive @375/768/1200px | ⚠️ | **manual sign-off required** |
