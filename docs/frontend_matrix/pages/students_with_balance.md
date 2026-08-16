# students_with_balance.php

- **File**: `pages/students_with_balance.php`
- **Controller**: `students_with_balance.js`
- **Roles**: —
- **Sidebar item(s)**: —

## Init / auth

- `AuthContext.ready()` awaited: `Y`
- Permission/RBAC guard: `N`
- Raw `fetch`/`XMLHttpRequest`/`axios`: `0`

## API methods used

| API method | Payload keys (sent) | Flags | Endpoint | Handler | Status |
|---|---|---|---|---|---|
| `academic.listClasses` | — | — | GET /academic/classes-list | Academic::listClasses | ok |
| `finance.getStudentPaymentStatusList` | — | — | GET /finance/students/payment-status | Finance::getStudentPaymentStatusList | ok |
| `finance.getOutstandingFees` | — | — | GET /finance/fees-annual-summary | Finance::getOutstandingFees | ok |
| `finance.getStudentFeeStatement` | — | — | GET /finance/students/fee-statement/ | Finance::getStudentFeeStatement | ok |
| `finance.recordPayment` | `student_id`, `amount`, `payment_method`, `reference`, `notes` | — | POST /finance | Finance::recordPayment | ok |

## Backend params (expected)

_(no backend handler resolved for param extraction)_

## Response shape (data keys consumed)

_(no post-await `.prop` consumption detected)_

## UI / security

- `innerHTML`/`insertAdjacentHTML` assignments: `8` (with interpolation: `0`)
- `escapeHtml()` calls: `5` — XSS check: `PASS`
- Bootstrap modal usage: `3`
- Payload/backend param match: `OK`
- Fix flags: none
- Info flags: `ESCAPED_LITERAL_HTML`
- Fix task: `—`

## End-to-end trace

> collect → store → analyse → display: page payload keys → API endpoint → controller method → service passthrough → SQL views/procs/tables (rows above). This is machine-derived; the interactive flow (form submit → render) needs human sign-off.

## Review checklist

| Check | Status | Notes |
|---|---|---|
| Init: `AuthContext.ready()` | ✅ | machine |
| Data load: `window.API.*`, no raw fetch | ✅ | machine |
| Params: sent ≈ backend `$data` | ⚠️ | heuristic |
| Response: unwrapped `data` handled | ⚠️ | heuristic |
| UI: Bootstrap + `escapeHtml` | ✅ | machine |
| Responsive @375/768/1200px | ⚠️ | **manual sign-off required** |
