# school_settings.php

- **File**: `pages/school_settings.php`
- **Controller**: `settings.js`
- **Roles**: —
- **Sidebar item(s)**: —

## Init / auth

- `AuthContext.ready()` awaited: `N`
- Permission/RBAC guard: `N`
- Raw `fetch`/`XMLHttpRequest`/`axios`: `0`

## API methods used

| API method | Payload keys (sent) | Flags | Endpoint | Handler | Status |
|---|---|---|---|---|---|
| `users.index` | — | — | GET /users/index | Users::index | ok |
| `system.getRoles` | — | — | GET /system/roles | System::getRoles | ok |
| `system.getPermissions` | — | — | GET /system/permissions | System::getPermissions | ok |
| `system.createBackup` | — | — | POST /system/backups | System::createBackup | ok |

## Backend params (expected)

| Handler | Input keys ($data/$_POST/$_GET) | Path id | Data sources (views/procs/tables) |
|---|---|---|---|
| Users::index | `status` | — | persons, roles, user_roles, users |
| System::getRoles | `id` | `id` | — |
| System::getPermissions | — | — | permissions |

## Response shape (data keys consumed)

_(no post-await `.prop` consumption detected)_

## UI / security

- `innerHTML`/`insertAdjacentHTML` assignments: `0` (with interpolation: `0`)
- `escapeHtml()` calls: `0` — XSS check: `PASS`
- Bootstrap modal usage: `2`
- Payload/backend param match: `WARN`
- Fix flags: none
- Info flags: `NO_AUTH_GUARD`, `RENDER_ONLY`
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
