# manage_roles.php

- **File**: `pages/manage_roles.php`
- **Controller**: `manage_roles.js`
- **Roles**: `System Administrator`
- **Sidebar item(s)**: `1013`, `100081`

## Init / auth

- `AuthContext.ready()` awaited: `Y`
- Permission/RBAC guard: `N`
- Raw `fetch`/`XMLHttpRequest`/`axios`: `0`

## API methods used

| API method | Payload keys (sent) | Flags | Endpoint | Handler | Status |
|---|---|---|---|---|---|
| `system.getRoles` | — | — | GET /system/roles | System::getRoles | ok |
| `system.getRole` | — | — | GET /system/roles/ | System::getRole | ok |
| `system.updateRole` | — | — | PUT /system/roles | System::updateRole | ok |
| `system.createRole` | — | — | POST /system/roles | System::createRole | ok |
| `system.deleteRole` | — | — | DELETE /system/roles/ | System::deleteRole | ok |
| `system.toggleRoleStatus` | — | — | POST /system/roles-toggle | System::toggleRoleStatus | ok |

## Backend params (expected)

| Handler | Input keys ($data/$_POST/$_GET) | Path id | Data sources (views/procs/tables) |
|---|---|---|---|
| System::getRoles | `id` | `id` | — |

## Response shape (data keys consumed)

_(no post-await `.prop` consumption detected)_

## UI / security

- `innerHTML`/`insertAdjacentHTML` assignments: `5` (with interpolation: `0`)
- `escapeHtml()` calls: `5` — XSS check: `PASS`
- Bootstrap modal usage: `1`
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
