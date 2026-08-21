# attendance_trends.php

- **File**: `pages/attendance_trends.php`
- **Controller**: `attendance_trends.js`
- **Roles**: —
- **Sidebar item(s)**: —

## Init / auth

- `AuthContext.ready()` awaited: `N`
- Permission/RBAC guard: `N`
- Raw `fetch`/`XMLHttpRequest`/`axios`: `0`

## API methods used

_(no `API.<module>.<method>` calls detected)_

## Backend params (expected)

_(no backend handler resolved for param extraction)_

## Response shape (data keys consumed)

_(no post-await `.prop` consumption detected)_

## UI / security

- `innerHTML`/`insertAdjacentHTML` assignments: `2` (with interpolation: `0`)
- `escapeHtml()` calls: `3` — XSS check: `PASS`
- Bootstrap modal usage: `0`
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
