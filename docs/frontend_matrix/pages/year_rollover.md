# year_rollover.php

- **File**: `pages/year_rollover.php`
- **Controller**: `year_rollover.js`
- **Roles**: `System Administrator`
- **Sidebar item(s)**: `100123`

## Init / auth

- `AuthContext.ready()` awaited: `N`
- Permission/RBAC guard: `Y`
- Raw `fetch`/`XMLHttpRequest`/`axios`: `0`

## API methods used

_(no `API.<module>.<method>` calls detected)_

## Direct `callAPI` endpoints

| Endpoint | Payload keys (sent) | Flags |
|---|---|---|
| `GET /academic/year-rollover-status` | — | — |
| `POST /academic/year-rollover` | — | — |

## Backend params (expected)

| Handler | Input keys ($data/$_POST/$_GET) | Path id | Data sources (views/procs/tables) |
|---|---|---|---|
| Academic::getYearRolloverStatus | — | — | vw_student_fee_balances, academic_year_rollover_log, academic_year_terms, academic_years, student_academic_enrollments, student_transitions, term_subject_scores, terms, vw_student_fee_balances |
| Academic::postYearRollover | `step` | — | vw_student_fee_balances, academic_year_archives, academic_year_class_learning_area_teachers, academic_year_class_learning_areas, academic_year_classes, academic_year_rollover_log, academic_year_terms, academic_years, audit_logs, fee_credit_notes, status, student_academic_enrollments, terms, vw_student_fee_balances |

## Response shape (data keys consumed)

- `direct.callAPI -> fee_balances_carried`
- `direct.callAPI -> credit_notes_created`
- `direct.callAPI -> new_year_code`
- `direct.callAPI -> activated_year`
- `direct.callAPI -> archived_year`
- `direct.callAPI -> note`

## UI / security

- `innerHTML`/`insertAdjacentHTML` assignments: `5` (with interpolation: `0`)
- `escapeHtml()` calls: `1` — XSS check: `PASS`
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
| Init: `AuthContext.ready()` | ❌ | machine |
| Data load: `window.API.*`, no raw fetch | ✅ | machine |
| Params: sent ≈ backend `$data` | ⚠️ | heuristic |
| Response: unwrapped `data` handled | ✅ | heuristic |
| UI: Bootstrap + `escapeHtml` | ✅ | machine |
| Responsive @375/768/1200px | ⚠️ | **manual sign-off required** |
