# asset_purchases.php

- **File**: `pages/asset_purchases.php`
- **Controller**: `asset_purchases.js`
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
| `GET /inventory/assets` | — | — |
| `GET /inventory/suppliers-list` | — | — |
| `GET /inventory/asset-categories` | — | — |
| `GET /inventory/assets/` | — | — |
| `POST /inventory/assets` | — | — |

## Backend params (expected)

| Handler | Input keys ($data/$_POST/$_GET) | Path id | Data sources (views/procs/tables) |
|---|---|---|---|
| Inventory::getAssets | — | `id` | asset_categories, fixed_assets, persons, suppliers, users |
| Inventory::getSuppliersList | — | — | — |
| Inventory::getAssetCategories | — | — | asset_categories |
| Inventory::postAssets | `name`, `category_id`, `purchase_date`, `purchase_price`, `depreciation_method`, `useful_life_years`, `residual_value`, `description`, `serial_number`, `model`, `brand`, `location`, `supplier_id`, `invoice_number`, `warranty_expiry`, `condition`, `status`, `acquisition_type` | — | asset_categories, fixed_assets |

## Response shape (data keys consumed)

- `direct.callAPI -> assets`
- `direct.callAPI -> suppliers`

## UI / security

- `innerHTML`/`insertAdjacentHTML` assignments: `9` (with interpolation: `1`)
- `escapeHtml()` calls: `14` — XSS check: `PASS`
- Bootstrap modal usage: `2`
- Payload/backend param match: `NA`
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
| Response: unwrapped `data` handled | ✅ | heuristic |
| UI: Bootstrap + `escapeHtml` | ✅ | machine |
| Responsive @375/768/1200px | ⚠️ | **manual sign-off required** |
