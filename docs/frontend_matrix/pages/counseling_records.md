# counseling_records.php

- **File**: `pages/counseling_records.php`
- **Controller**: `counseling_records.js`
- **Roles**: —
- **Sidebar item(s)**: —

## Init / auth

- `AuthContext.ready()` awaited: `N`
- Permission/RBAC guard: `N`
- Raw `fetch`/`XMLHttpRequest`/`axios`: `0`

## API methods used

| API method | Payload keys (sent) | Flags | Endpoint | Handler | Status |
|---|---|---|---|---|---|
| `counseling.list` | — | — | GET /counseling/session | Counseling::list | ok |
| `counseling.getSummary` | — | — | GET /counseling/summary | Counseling::getSummary | ok |
| `counseling.get` | — | — | GET /counseling/session/ | Counseling::get | ok |

## Backend params (expected)

| Handler | Input keys ($data/$_POST/$_GET) | Path id | Data sources (views/procs/tables) |
|---|---|---|---|
| Counseling::getSummary | — | — | counseling_cases, counseling_sessions, persons, staff, students |

## Response shape (data keys consumed)

- `counseling.get -> counselee_name`
- `counseling.get -> student_name`
- `counseling.get -> staff_name`
- `counseling.get -> date`
- `counseling.get -> session_date`
- `counseling.get -> case_type`
- `counseling.get -> type`
- `counseling.get -> session_type`
- `counseling.get -> counselor`
- `counseling.get -> counselor_name`

## UI / security

- `innerHTML`/`insertAdjacentHTML` assignments: `6` (with interpolation: `1`)
- `escapeHtml()` calls: `10` — XSS check: `PASS`
- Bootstrap modal usage: `1`
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
