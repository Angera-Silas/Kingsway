# student_profiles.php

- **File**: `pages/student_profiles.php`
- **Controller**: `student_profile_context.js`
- **Roles**: —
- **Sidebar item(s)**: —

## Init / auth

- `AuthContext.ready()` awaited: `N`
- Permission/RBAC guard: `N`
- Raw `fetch`/`XMLHttpRequest`/`axios`: `0`

## API methods used

| API method | Payload keys (sent) | Flags | Endpoint | Handler | Status |
|---|---|---|---|---|---|
| `students.contextList` | — | — | GET /students/context-list | Students::contextList | ok |
| `students.contextProfile` | — | — | GET /students/context-profile/ | Students::contextProfile | ok |

## Backend params (expected)

_(no backend handler resolved for param extraction)_

## Response shape (data keys consumed)

- `students.contextList -> context`
- `students.contextList -> students`
- `students.contextProfile -> student`
- `students.contextProfile -> tabs`
- `students.contextProfile -> context`

## UI / security

- `innerHTML`/`insertAdjacentHTML` assignments: `6` (with interpolation: `3`)
- `escapeHtml()` calls: `9` — XSS check: `PASS`
- Bootstrap modal usage: `0`
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
| Response: unwrapped `data` handled | ✅ | heuristic |
| UI: Bootstrap + `escapeHtml` | ✅ | machine |
| Responsive @375/768/1200px | ⚠️ | **manual sign-off required** |
