# token_management.php

- **File**: `pages/token_management.php`
- **Controller**: `token_management.js`
- **Roles**: `System Administrator`
- **Sidebar item(s)**: `1028`

## Init / auth

- `AuthContext.ready()` awaited: `Y`
- Permission/RBAC guard: `N`
- Raw `fetch`/`XMLHttpRequest`/`axios`: `0`

## API methods used

| API method | Payload keys (sent) | Flags | Endpoint | Handler | Status |
|---|---|---|---|---|---|
| `system.getTokens` | `page`, `limit` | — | GET /system/tokens | System::getTokens | ok |
| `system.revokeToken` | — | — | POST /system/tokens-revoke | System::revokeToken | ok |

## Backend params (expected)

| Handler | Input keys ($data/$_POST/$_GET) | Path id | Data sources (views/procs/tables) |
|---|---|---|---|
| System::getTokens | — | — | api_tokens, audit_logs, persons, refresh_tokens, user_sessions, users |

## Response shape (data keys consumed)

_(no post-await `.prop` consumption detected)_

## UI / security

- `innerHTML`/`insertAdjacentHTML` assignments: `3` (with interpolation: `0`)
- `escapeHtml()` calls: `13` — XSS check: `PASS`
- Bootstrap modal usage: `0`
- Payload/backend param match: `OK`
- Fix flags: none
- Info flags: `ESCAPED_LITERAL_HTML`
- Fix task: `—`

## End-to-end trace

> collect → store → analyse → display: page payload keys → API endpoint → controller method → service passthrough → SQL views/procs/tables (rows above). This is machine-derived; the interactive flow (form submit → render) needs human sign-off.

## Review checklist

| Check | Status | Notes |
|---|---|---|
| Init: `AuthContext.ready()` | ✅ | machine |
| Data load: `window.API.*`, no raw fetch | ✅ | machine |
| Params: sent ≈ backend `$data` | ⚠️ | heuristic |
| Response: unwrapped `data` handled | ⚠️ | heuristic |
| UI: Bootstrap + `escapeHtml` | ✅ | machine |
| Responsive @375/768/1200px | ⚠️ | **manual sign-off required** |
