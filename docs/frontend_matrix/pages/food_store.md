# food_store.php

- **File**: `pages/food_store.php`
- **Controller**: `food_store.js`
- **Roles**: —
- **Sidebar item(s)**: —

## Init / auth

- `AuthContext.ready()` awaited: `N`
- Permission/RBAC guard: `N`
- Raw `fetch`/`XMLHttpRequest`/`axios`: `0`

## API methods used

| API method | Payload keys (sent) | Flags | Endpoint | Handler | Status |
|---|---|---|---|---|---|
| `inventory.listCategories` | — | — | GET /inventory/categories-list | Inventory::listCategories | ok |
| `inventory.update` | `item_name`, `category_id`, `unit_of_measure`, `unit_cost`, `reorder_level`, `location_id`, `supplier_id` | — | PUT /inventory/inventory/ | Inventory::update | ok |
| `inventory.recordMovement` | `transaction_type`, `item_id`, `quantity`, `unit_cost`, `notes`, `transaction_date`, `update_quantity` | — | POST /inventory/movements-record | Inventory::recordMovement | ok |
| `inventory.create` | `item_name`, `item_code`, `category_id`, `unit_of_measure`, `quantity_on_hand`, `unit_cost`, `reorder_level`, `location_id`, `supplier_id` | — | POST /inventory/inventory | Inventory::create | ok |
| `inventory.delete` | — | — | DELETE /inventory/inventory/ | Inventory::delete | ok |

## Backend params (expected)

_(no backend handler resolved for param extraction)_

## Response shape (data keys consumed)

_(no post-await `.prop` consumption detected)_

## UI / security

- `innerHTML`/`insertAdjacentHTML` assignments: `8` (with interpolation: `1`)
- `escapeHtml()` calls: `10` — XSS check: `PASS`
- Bootstrap modal usage: `4`
- Payload/backend param match: `OK`
- Fix flags: none
- Info flags: `NO_AUTH_GUARD`
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
