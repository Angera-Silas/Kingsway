# cash_reconciliation.php

- **File**: `pages/cash_reconciliation.php`
- **Controller**: `cash_reconciliation.js`
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
| `GET /payments/collections?method=cash&date=` | — | — |
| `GET /finance/cash-reconciliation?date=` | — | — |
| `POST /finance/cash-reconciliation` | `reconciliation_date`, `system_cash_total`, `physical_cash_count` | — |
| `GET /finance/cash-reconciliation` | — | — |
| `GET /finance/cash-reconciliation/` | — | — |

## Backend params (expected)

| Handler | Input keys ($data/$_POST/$_GET) | Path id | Data sources (views/procs/tables) |
|---|---|---|---|
| Payments::getCollections | — | — | payments, persons, students, transport_bill_payments, transport_monthly_bills |
| Finance::getCashReconciliation | `date` | `id` | cash_reconciliation_sessions, persons, users |
| Finance::postCashReconciliation | `reconciliation_date`, `system_cash_total`, `physical_cash_count` | — | cash_reconciliation_sessions |

## Response shape (data keys consumed)

- `direct.callAPI -> sessions`
- `direct.callAPI -> reconciliation_date`
- `direct.callAPI -> system_cash_total`
- `direct.callAPI -> physical_cash_count`
- `direct.callAPI -> variance`
- `direct.callAPI -> cashier_name`
- `direct.callAPI -> s`

## UI / security

- `innerHTML`/`insertAdjacentHTML` assignments: `11` (with interpolation: `2`)
- `escapeHtml()` calls: `12` — XSS check: `PASS`
- Bootstrap modal usage: `1`
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
