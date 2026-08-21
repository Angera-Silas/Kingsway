# resource_based_permissions.php

- **File**: `pages/resource_based_permissions.php`
- **Controller**: `resource_based_permissions.js`
- **Roles**: `System Administrator`
- **Sidebar item(s)**: `1015`, `100083`

## Init / auth

- `AuthContext.ready()` awaited: `Y`
- Permission/RBAC guard: `N`
- Raw `fetch`/`XMLHttpRequest`/`axios`: `0`

## API methods used

| API method | Payload keys (sent) | Flags | Endpoint | Handler | Status |
|---|---|---|---|---|---|
| `system.getResourcePermissions` | `page`, `limit` | — | GET /system/resource-permissions | System::getResourcePermissions | ok |
| `system.updatePermission` | — | — | PUT /system/permissions/ | System::updatePermission | ok |
| `system.createPermission` | — | — | POST /system/permissions | System::createPermission | ok |
| `system.deletePermission` | — | — | DELETE /system/permissions/ | System::deletePermission | ok |

## Backend params (expected)

| Handler | Input keys ($data/$_POST/$_GET) | Path id | Data sources (views/procs/tables) |
|---|---|---|---|
| System::getResourcePermissions | — | — | permissions |

## Response shape (data keys consumed)

_(no post-await `.prop` consumption detected)_

## UI / security

- `innerHTML`/`insertAdjacentHTML` assignments: `6` (with interpolation: `1`)
- `escapeHtml()` calls: `8` — XSS check: `PASS`
- Bootstrap modal usage: `1`
- Payload/backend param match: `OK`
- Fix flags: none
- Info flags: none
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
