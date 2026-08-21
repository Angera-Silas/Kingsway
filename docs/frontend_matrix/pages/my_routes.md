# my_routes.php

- **File**: `pages/my_routes.php`
- **Controller**: `my_routes.js`
- **Roles**: —
- **Sidebar item(s)**: —

## Init / auth

- `AuthContext.ready()` awaited: `N`
- Permission/RBAC guard: `N`
- Raw `fetch`/`XMLHttpRequest`/`axios`: `0`

## API methods used

| API method | Payload keys (sent) | Flags | Endpoint | Handler | Status |
|---|---|---|---|---|---|
| `transport.getRoutes` | `driver_id` | — | GET /transport/routes-get/<br>GET /transport/routes-get | Transport::getRoutes<br>Transport::getRoutes | ok<br>ok |
| `transport.getVehicles` | — | — | GET /transport/vehicles-get/<br>GET /transport/vehicles-get | Transport::getVehicles<br>Transport::getVehicles | ok<br>ok |
| `transport.index` | `action`, `route_id`, `driver_id`, `start_time`, `end_time` | — | GET /transport/index | Transport::index | ok |
| `transport.getRoute` | — | — | GET /transport/transport-route/<br>GET /transport/transport-route | Transport::getRoute<br>Transport::getRoute | ok<br>ok |

## Backend params (expected)

| Handler | Input keys ($data/$_POST/$_GET) | Path id | Data sources (views/procs/tables) |
|---|---|---|---|
| Transport::index | — | — | — |

## Response shape (data keys consumed)

_(no post-await `.prop` consumption detected)_

## UI / security

- `innerHTML`/`insertAdjacentHTML` assignments: `7` (with interpolation: `0`)
- `escapeHtml()` calls: `20` — XSS check: `PASS`
- Bootstrap modal usage: `2`
- Payload/backend param match: `OK`
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
