# manage_uniform_sales.php

- **File**: `pages/manage_uniform_sales.php`
- **Controller**: `uniform_sales.js`
- **Roles**: —
- **Sidebar item(s)**: —

## Init / auth

- `AuthContext.ready()` awaited: `N`
- Permission/RBAC guard: `N`
- Raw `fetch`/`XMLHttpRequest`/`axios`: `0`

## API methods used

| API method | Payload keys (sent) | Flags | Endpoint | Handler | Status |
|---|---|---|---|---|---|
| `inventory.getUniformDashboard` | — | — | GET /inventory/uniform-dashboard | Inventory::getUniformDashboard | ok |
| `inventory.getLowStockUniforms` | — | — | GET /inventory/uniform-low-stock | Inventory::getLowStockUniforms | ok |
| `inventory.getUniformItems` | — | — | GET /inventory/uniform-items | Inventory::getUniformItems | ok |
| `inventory.getUniformSizes` | — | — | GET /inventory/uniform-sizes/ | Inventory::getUniformSizes | ok |
| `students.get` | — | — | GET /students/student/<br>GET /students/student | Students::get<br>Students::get | ok<br>ok |
| `inventory.registerUniformSale` | — | — | POST /inventory/uniform-sales | Inventory::registerUniformSale | ok |
| `inventory.restockUniformSize` | — | — | POST /inventory/uniform-restock | Inventory::restockUniformSize | ok |
| `inventory.listUniformSales` | — | — | GET /inventory/uniform-sales-list | Inventory::listUniformSales | ok |
| `inventory.updateUniformPayment` | — | — | PUT /inventory/uniform-sales-payment/ | Inventory::updateUniformPayment | ok |
| `inventory.deleteUniformSale` | — | — | DELETE /inventory/uniform-sales/ | Inventory::deleteUniformSale | ok |
| `inventory.getUniformSalesReport` | — | — | GET /inventory/uniform-sales-report | Inventory::getUniformSalesReport | ok |

## Direct `callAPI` endpoints

| Endpoint | Payload keys (sent) | Flags |
|---|---|---|
| `GET /inventory/uniform-sales/` | `amount_paid`, `payment_method` | — |
| `DELETE /inventory/uniform-sales/${saleId}` | — | — |

## Backend params (expected)

| Handler | Input keys ($data/$_POST/$_GET) | Path id | Data sources (views/procs/tables) |
|---|---|---|---|
| Inventory::getUniformDashboard | — | — | — |
| Inventory::getUniformItems | — | — | — |
| Inventory::getUniformSizes | — | `id` | — |
| Inventory::getUniformSalesReport | — | — | — |
| Inventory::getInventory | — | `id` | — |
| Inventory::deleteUniformSales | — | `id` | — |

## Response shape (data keys consumed)

- `inventory.getUniformItems -> items`
- `inventory.getUniformSizes -> item`
- `inventory.getUniformSizes -> sizes`
- `inventory.getUniformSizes -> name`
- `inventory.getUniformSizes -> map`
- `students.get -> map`
- `inventory.getUniformSizes -> length`
- `inventory.listUniformSales -> sales`
- `inventory.listUniformSales -> pagination`
- `inventory.listUniformSales -> length`
- `inventory.listUniformSales -> map`
- `inventory.listUniformSales -> total_pages`
- `inventory.listUniformSales -> page`
- `inventory.getLowStockUniforms -> items`
- `inventory.getLowStockUniforms -> length`
- `inventory.getLowStockUniforms -> map`

## UI / security

- `innerHTML`/`insertAdjacentHTML` assignments: `41` (with interpolation: `0`)
- `escapeHtml()` calls: `0` — XSS check: `PASS`
- Bootstrap modal usage: `5`
- Payload/backend param match: `NA`
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
| Response: unwrapped `data` handled | ✅ | heuristic |
| UI: Bootstrap + `escapeHtml` | ✅ | machine |
| Responsive @375/768/1200px | ⚠️ | **manual sign-off required** |
