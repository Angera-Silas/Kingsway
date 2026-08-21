# finance_approvals.php

- **File**: `pages/finance_approvals.php`
- **Controller**: `finance_approvals.js`
- **Roles**: —
- **Sidebar item(s)**: —

## Init / auth

- `AuthContext.ready()` awaited: `N`
- Permission/RBAC guard: `N`
- Raw `fetch`/`XMLHttpRequest`/`axios`: `0`

## API methods used

| API method | Payload keys (sent) | Flags | Endpoint | Handler | Status |
|---|---|---|---|---|---|
| `finance.getTransactions` | — | — | GET /finance | Finance::getTransactions | ok |
| `finance.getDepartmentBudgetsProposals` | — | — | GET /finance/department-budgets-proposals | Finance::getDepartmentBudgetsProposals | ok |
| `finance.listPayrolls` | `page`, `limit` | — | GET /finance/payrolls-list | Finance::listPayrolls | ok |
| `finance.approveExpense` | — | — | POST /finance/expenses-approve | Finance::approveExpense | ok |
| `finance.rejectExpense` | — | — | POST /finance/expenses-reject | Finance::rejectExpense | ok |
| `finance.approveDepartmentBudget` | `proposal_id`, `status`, `reviewed_by` | — | POST /finance/department-budgets-approve | Finance::approveDepartmentBudget | ok |
| `finance.approvePayroll` | `payroll_id`, `user_id` | — | POST /finance/approve-payroll | Finance::approvePayroll | ok |
| `finance.rejectPayroll` | `payroll_id`, `user_id`, `reason` | — | POST /finance/payrolls-reject | Finance::rejectPayroll | ok |

## Backend params (expected)

| Handler | Input keys ($data/$_POST/$_GET) | Path id | Data sources (views/procs/tables) |
|---|---|---|---|
| Finance::getDepartmentBudgetsProposals | — | — | — |

## Response shape (data keys consumed)

- `finance.getTransactions -> expenses`
- `finance.getDepartmentBudgetsProposals -> proposals`
- `finance.listPayrolls -> payrolls`

## UI / security

- `innerHTML`/`insertAdjacentHTML` assignments: `8` (with interpolation: `1`)
- `escapeHtml()` calls: `7` — XSS check: `PASS`
- Bootstrap modal usage: `2`
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
| Response: unwrapped `data` handled | ✅ | heuristic |
| UI: Bootstrap + `escapeHtml` | ✅ | machine |
| Responsive @375/768/1200px | ⚠️ | **manual sign-off required** |
