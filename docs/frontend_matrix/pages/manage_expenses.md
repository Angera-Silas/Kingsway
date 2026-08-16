# manage_expenses.php

- **File**: `pages/manage_expenses.php`
- **Controller**: `expenses.js`
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
| `GET /finance/expenses` | — | — |
| `GET /finance/expense-categories` | — | — |
| `GET /finance/budgets` | — | — |
| `GET /finance/expenses/` | `status` | — |
| `POST /finance/expenses` | — | — |

## Backend params (expected)

| Handler | Input keys ($data/$_POST/$_GET) | Path id | Data sources (views/procs/tables) |
|---|---|---|---|
| Finance::getExpenses | — | `id` | — |
| Finance::getExpenseCategories | — | — | expense_categories |
| Finance::getBudgets | — | `id` | vw_budget_utilization, budget_line_items, budgets, expense_categories, persons, users, vw_budget_utilization |
| Finance::postExpenses | `description`, `amount`, `expense_date` | — | expenses |

## Response shape (data keys consumed)

- `direct.callAPI -> expenses`

## UI / security

- `innerHTML`/`insertAdjacentHTML` assignments: `10` (with interpolation: `0`)
- `escapeHtml()` calls: `6` — XSS check: `PASS`
- Bootstrap modal usage: `2`
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
