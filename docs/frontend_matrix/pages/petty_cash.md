# petty_cash.php

- **File**: `pages/petty_cash.php`
- **Controller**: `petty_cash.js`
- **Roles**: —
- **Sidebar item(s)**: —

## Init / auth

- `AuthContext.ready()` awaited: `N`
- Permission/RBAC guard: `Y`
- Raw `fetch`/`XMLHttpRequest`/`axios`: `0`

## API methods used

_(no `API.<module>.<method>` calls detected)_

## Direct `callAPI` endpoints

| Endpoint | Payload keys (sent) | Flags |
|---|---|---|
| `GET /finance/petty-cash` | — | — |
| `GET /finance/expense-categories` | — | — |
| `POST /finance/petty-cash` | `category_id`, `amount`, `transaction_date`, `description`, `vendor_name` | — |
| `GET /finance/petty-cash/` | — | — |

## Backend params (expected)

| Handler | Input keys ($data/$_POST/$_GET) | Path id | Data sources (views/procs/tables) |
|---|---|---|---|
| Finance::getPettyCash | `fund_id` | — | petty_cash_funds, petty_cash_transactions, expense_categories, persons, users |
| Finance::getExpenseCategories | — | — | expense_categories |
| Finance::postPettyCash | `type`, `amount`, `description`, `fund_id` | — | petty_cash_funds, petty_cash_transactions |

## Response shape (data keys consumed)

- `direct.callAPI -> transactions`

## UI / security

- `innerHTML`/`insertAdjacentHTML` assignments: `8` (with interpolation: `0`)
- `escapeHtml()` calls: `4` — XSS check: `PASS`
- Bootstrap modal usage: `1`
- Payload/backend param match: `NA`
- Fix flags: none
- Info flags: `ESCAPED_LITERAL_HTML`
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
