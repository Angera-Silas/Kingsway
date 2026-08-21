# budget_overview.php

- **File**: `pages/budget_overview.php`
- **Controller**: `budget_overview.js`
- **Roles**: —
- **Sidebar item(s)**: —

## Init / auth

- `AuthContext.ready()` awaited: `N`
- Permission/RBAC guard: `N`
- Raw `fetch`/`XMLHttpRequest`/`axios`: `0`

## API methods used

| API method | Payload keys (sent) | Flags | Endpoint | Handler | Status |
|---|---|---|---|---|---|
| `finance.getDepartmentBudgetsSummary` | — | — | GET /finance/department-budgets-summary | Finance::getDepartmentBudgetsSummary | ok |
| `finance.getDepartmentBudgetsProposals` | — | — | GET /finance/department-budgets-proposals | Finance::getDepartmentBudgetsProposals | ok |
| `finance.proposeDepartmentBudget` | — | — | POST /finance/department-budgets-propose | Finance::proposeDepartmentBudget | ok |
| `finance.approveDepartmentBudget` | — | — | POST /finance/department-budgets-approve | Finance::approveDepartmentBudget | ok |

## Backend params (expected)

| Handler | Input keys ($data/$_POST/$_GET) | Path id | Data sources (views/procs/tables) |
|---|---|---|---|
| Finance::getDepartmentBudgetsSummary | `department_id` | `id` | budgets, departments, expenses |
| Finance::getDepartmentBudgetsProposals | — | — | — |

## Response shape (data keys consumed)

_(no post-await `.prop` consumption detected)_

## UI / security

- `innerHTML`/`insertAdjacentHTML` assignments: `5` (with interpolation: `0`)
- `escapeHtml()` calls: `11` — XSS check: `PASS`
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
