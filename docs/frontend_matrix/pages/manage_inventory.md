# manage_inventory.php

- **File**: `pages/manage_inventory.php`
- **Controller**: `manage_inventory.js`
- **Roles**: —
- **Sidebar item(s)**: —

## Init / auth

- `AuthContext.ready()` awaited: `N`
- Permission/RBAC guard: `N`
- Raw `fetch`/`XMLHttpRequest`/`axios`: `0`

## API methods used

| API method | Payload keys (sent) | Flags | Endpoint | Handler | Status |
|---|---|---|---|---|---|
| `inventory.getLowStockItems` | — | — | GET /inventory/items-low-stock | Inventory::getLowStockItems | ok |
| `inventory.getStockValuation` | — | — | GET /inventory/items-stock-valuation | Inventory::getStockValuation | ok |
| `inventory.getItemsWithStock` | — | — | GET /inventory/items-with-stock | Inventory::getItemsWithStock | ok |
| `inventory.listCategories` | — | — | GET /inventory/categories-list | Inventory::listCategories | ok |
| `inventory.listLocations` | — | — | GET /inventory/locations-list | Inventory::listLocations | ok |
| `inventory.update` | — | — | PUT /inventory/inventory/ | Inventory::update | ok |
| `inventory.create` | — | — | POST /inventory/inventory | Inventory::create | ok |
| `inventory.delete` | — | — | DELETE /inventory/inventory/ | Inventory::delete | ok |
| `inventory.adjustStock` | — | — | POST /inventory/movements-adjust-stock | Inventory::adjustStock | ok |

## Backend params (expected)

| Handler | Input keys ($data/$_POST/$_GET) | Path id | Data sources (views/procs/tables) |
|---|---|---|---|
| Inventory::getItemsWithStock | `id` | `id` | — |

## Response shape (data keys consumed)

_(no post-await `.prop` consumption detected)_

## UI / security

- `innerHTML`/`insertAdjacentHTML` assignments: `7` (with interpolation: `0`)
- `escapeHtml()` calls: `12` — XSS check: `PASS`
- Bootstrap modal usage: `5`
- Payload/backend param match: `WARN`
- Fix flags: none
- Info flags: `TEMPLATE_FRAGMENT`, `NO_AUTH_GUARD`, `ESCAPED_LITERAL_HTML`
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
