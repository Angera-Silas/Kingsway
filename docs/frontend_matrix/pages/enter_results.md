# enter_results.php

- **File**: `pages/enter_results.php`
- **Controller**: `enter_results.js`
- **Roles**: —
- **Sidebar item(s)**: —

## Init / auth

- `AuthContext.ready()` awaited: `N`
- Permission/RBAC guard: `N`
- Raw `fetch`/`XMLHttpRequest`/`axios`: `0`

## API methods used

| API method | Payload keys (sent) | Flags | Endpoint | Handler | Status |
|---|---|---|---|---|---|
| `academic.listClasses` | — | — | GET /academic/classes-list | Academic::listClasses | ok |
| `academic.getCustom` | `action` | — | GET /academic/custom | Academic::getCustom | ok |
| `academic.getAllAcademicYears` | — | — | GET /academic/years/list | Academic::getAllAcademicYears | ok |
| `students.get` | — | — | GET /students/student/<br>GET /students/student | Students::get<br>Students::get | ok<br>ok |
| `academic.postCustom` | `action` | — | POST /academic/custom | Academic::postCustom | ok |

## Backend params (expected)

| Handler | Input keys ($data/$_POST/$_GET) | Path id | Data sources (views/procs/tables) |
|---|---|---|---|
| Academic::getCustom | `id`, `action` | `id` | — |
| Academic::postCustom | `id`, `action`, `subject_id`, `day_of_week`, `start_time`, `end_time` | `id` | — |

## Response shape (data keys consumed)

_(no post-await `.prop` consumption detected)_

## UI / security

- `innerHTML`/`insertAdjacentHTML` assignments: `9` (with interpolation: `0`)
- `escapeHtml()` calls: `6` — XSS check: `PASS`
- Bootstrap modal usage: `1`
- Payload/backend param match: `WARN`
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
| Params: sent ≈ backend `$data` | ❌ | heuristic |
| Response: unwrapped `data` handled | ⚠️ | heuristic |
| UI: Bootstrap + `escapeHtml` | ✅ | machine |
| Responsive @375/768/1200px | ⚠️ | **manual sign-off required** |
