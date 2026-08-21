# vendors.php

- **File**: `pages/vendors.php`
- **Controller**: `vendors.js`
- **Roles**: —
- **Sidebar item(s)**: —

## Init / auth

- `AuthContext.ready()` awaited: `N`
- Permission/RBAC guard: `N`
- Raw `fetch`/`XMLHttpRequest`/`axios`: `0`

## API methods used

_(no `API.<module>.<method>` calls detected)_

## Direct `callAPI` endpoints

| Endpoint | Payload keys (sent) | Flags |
|---|---|---|
| `GET /vendors` | — | — |
| `GET /vendors/` | — | — |
| `POST /vendors` | — | — |
| `GET /vendors/purchase-orders` | — | — |

## Backend params (expected)

| Handler | Input keys ($data/$_POST/$_GET) | Path id | Data sources (views/procs/tables) |
|---|---|---|---|
| Vendors::getVendors | — | — | — |
| Vendors::postVendors | `supplier_name`, `name` | — | — |
| Vendors::getPurchaseOrders | — | — | — |

## Response shape (data keys consumed)

- `direct.callAPI -> length`
- `direct.callAPI -> map`

## UI / security

- `innerHTML`/`insertAdjacentHTML` assignments: `8` (with interpolation: `1`)
- `escapeHtml()` calls: `13` — XSS check: `PASS`
- Bootstrap modal usage: `3`
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
