# ip_whitelist_blacklist.php

- **File**: `pages/ip_whitelist_blacklist.php`
- **Controller**: `ip_whitelist_blacklist.js`
- **Roles**: `System Administrator`
- **Sidebar item(s)**: `1029`

## Init / auth

- `AuthContext.ready()` awaited: `Y`
- Permission/RBAC guard: `N`
- Raw `fetch`/`XMLHttpRequest`/`axios`: `0`

## API methods used

| API method | Payload keys (sent) | Flags | Endpoint | Handler | Status |
|---|---|---|---|---|---|
| `system.getIpLists` | `page`, `limit` | — | GET /system/ip-lists | System::getIpLists | ok |
| `system.updateIpRule` | — | — | PUT /system/ip-lists/ | System::updateIpRule | ok |
| `system.createIpRule` | — | — | POST /system/ip-lists | System::createIpRule | ok |
| `system.deleteIpRule` | — | — | DELETE /system/ip-lists/ | System::deleteIpRule | ok |

## Backend params (expected)

| Handler | Input keys ($data/$_POST/$_GET) | Path id | Data sources (views/procs/tables) |
|---|---|---|---|
| System::getIpLists | — | — | system_ip_rules, users |

## Response shape (data keys consumed)

_(no post-await `.prop` consumption detected)_

## UI / security

- `innerHTML`/`insertAdjacentHTML` assignments: `5` (with interpolation: `0`)
- `escapeHtml()` calls: `9` — XSS check: `PASS`
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
