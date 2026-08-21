# conduct_reports.php

- **File**: `pages/conduct_reports.php`
- **Controller**: `conduct_reports.js`
- **Roles**: —
- **Sidebar item(s)**: —

## Init / auth

- `AuthContext.ready()` awaited: `N`
- Permission/RBAC guard: `N`
- Raw `fetch`/`XMLHttpRequest`/`axios`: `0`

## API methods used

| API method | Payload keys (sent) | Flags | Endpoint | Handler | Status |
|---|---|---|---|---|---|
| `academic.getAllAcademicYears` | — | — | GET /academic/years/list | Academic::getAllAcademicYears | ok |
| `academic.listClasses` | — | — | GET /academic/classes-list | Academic::listClasses | ok |
| `academic.listTerms` | — | — | GET /academic/terms-list | Academic::listTerms | ok |
| `students.get` | — | — | GET /students/student/<br>GET /students/student | Students::get<br>Students::get | ok<br>ok |

## Backend params (expected)

_(no backend handler resolved for param extraction)_

## Response shape (data keys consumed)

_(no post-await `.prop` consumption detected)_

## UI / security

- `innerHTML`/`insertAdjacentHTML` assignments: `11` (with interpolation: `1`)
- `escapeHtml()` calls: `9` — XSS check: `PASS`
- Bootstrap modal usage: `2`
- Payload/backend param match: `NA`
- Fix flags: none
- Info flags: `TEMPLATE_FRAGMENT`, `NO_AUTH_GUARD`
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
