# role_permission_matrix.php

- **File**: `pages/role_permission_matrix.php`
- **Controller**: `role_permission_matrix.js`
- **Roles**: `System Administrator`
- **Sidebar item(s)**: `1014`, `100082`

## Init / auth

- `AuthContext.ready()` awaited: `Y`
- Permission/RBAC guard: `N`
- Raw `fetch`/`XMLHttpRequest`/`axios`: `0`

## API methods used

| API method | Payload keys (sent) | Flags | Endpoint | Handler | Status |
|---|---|---|---|---|---|
| `system.getRoles` | — | — | GET /system/roles | System::getRoles | ok |
| `system.getPermissions` | — | — | GET /system/permissions | System::getPermissions | ok |
| `system.getRolePermissions` | — | — | GET /system/role-permissions?role_id= | System::getRolePermissions | ok |
| `system.assignPermissionToRole` | — | — | POST /system/role-permissions | System::assignPermissionToRole | ok |
| `system.revokePermissionFromRole` | — | — | DELETE /system/role-permissions/ | System::revokePermissionFromRole | ok |

## Backend params (expected)

| Handler | Input keys ($data/$_POST/$_GET) | Path id | Data sources (views/procs/tables) |
|---|---|---|---|
| System::getRoles | `id` | `id` | — |
| System::getPermissions | — | — | permissions |
| System::getRolePermissions | `role_id` | `id` | permissions, role_permissions, roles |

## Response shape (data keys consumed)

_(no post-await `.prop` consumption detected)_

## UI / security

- `innerHTML`/`insertAdjacentHTML` assignments: `6` (with interpolation: `0`)
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
