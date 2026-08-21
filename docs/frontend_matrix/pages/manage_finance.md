# manage_finance.php

- **File**: `pages/manage_finance.php`
- **Controller**: `finance.js`
- **Roles**: —
- **Sidebar item(s)**: —

## Init / auth

- `AuthContext.ready()` awaited: `Y`
- Permission/RBAC guard: `N`
- Raw `fetch`/`XMLHttpRequest`/`axios`: `0`

## API methods used

| API method | Payload keys (sent) | Flags | Endpoint | Handler | Status |
|---|---|---|---|---|---|
| `finance.index` | — | — | GET /finance?type=payments | Finance::index | ok |
| `finance.recordPayment` | — | — | POST /finance | Finance::recordPayment | ok |
| `finance.get` | — | — | GET /finance/<br>GET /finance | Finance::get<br>Finance::get | ok<br>ok |
| `finance.generateReceipt` | — | — | POST /finance/payments-generate-receipt | Finance::generateReceipt | ok |
| `finance.sendNotification` | `payment_id`, `notification_type` | — | POST /finance/payments-send-notification | Finance::sendNotification | ok |
| `finance.listPayrolls` | — | — | GET /finance/payrolls-list | Finance::listPayrolls | ok |
| `finance.createDraftPayroll` | — | — | POST /finance/payrolls-create-draft | Finance::createDraftPayroll | ok |
| `finance.calculatePayroll` | `payroll_id` | — | POST /finance/payrolls-calculate | Finance::calculatePayroll | ok |
| `finance.verifyPayroll` | `payroll_id`, `verified_by` | — | POST /finance/payrolls-verify | Finance::verifyPayroll | ok |
| `finance.approvePayroll` | `payroll_id`, `approved_by` | — | POST /finance/approve-payroll | Finance::approvePayroll | ok |
| `finance.processPayroll` | `payroll_id` | — | POST /finance/payrolls-process | Finance::processPayroll | ok |
| `finance.getPayrollSummary` | — | — | GET /finance/payrolls-summary?payroll_id= | Finance::getPayrollSummary | ok |
| `finance.generatePayrollReport` | — | — | POST /finance/reports-generate-payroll | Finance::generatePayrollReport | ok |
| `finance.getAnnualSummary` | — | — | GET /finance/fees-annual-summary | Finance::getAnnualSummary | ok |
| `finance.createAnnualStructure` | — | — | POST /finance/fees-create-annual-structure | Finance::createAnnualStructure | ok |
| `finance.approveStructure` | `structure_id`, `approved_by` | — | POST /finance/fees-approve-structure | Finance::approveStructure | ok |
| `finance.activateStructure` | `structure_id` | — | POST /finance/fees-activate-structure | Finance::activateStructure | ok |
| `finance.proposeBudget` | — | — | POST /finance/department-budgets-propose | Finance::proposeBudget | ok |
| `finance.approveBudget` | `budget_id`, `approved_by` | — | POST /finance/department-budgets-approve | Finance::approveBudget | ok |
| `finance.requestFunds` | — | — | POST /finance/department-budgets-request-funds | Finance::requestFunds | ok |
| `finance.getOutstandingFees` | — | — | GET /finance/fees-annual-summary | Finance::getOutstandingFees | ok |
| `finance.compareYearlyCollections` | — | — | GET /finance/reports-compare-yearly-collections | Finance::compareYearlyCollections | ok |
| `finance.getTransactions` | `type`, `limit` | — | GET /finance | Finance::getTransactions | ok |
| `finance.approveExpense` | — | — | POST /finance/expenses-approve | Finance::approveExpense | ok |
| `finance.rejectExpense` | — | — | POST /finance/expenses-reject | Finance::rejectExpense | ok |
| `finance.create` | `type`, `expense_category`, `expense_date`, `recorded_by`, `status` | — | POST /finance | Finance::create | ok |
| `staff.getDepartments` | — | — | GET /staff/departments-get/<br>GET /staff/departments-get | Staff::getDepartments<br>Staff::getDepartments | ok<br>ok |
| `finance.getBudgetSummary` | — | — | GET /finance/department-budgets-summary | Finance::getBudgetSummary | ok |
| `finance.getStudentBalance` | — | — | GET /finance/students/balance/ | Finance::getStudentBalance | ok |
| `finance.getStudentPaymentHistory` | — | — | GET /finance/students-payment-history?student_id= | Finance::getStudentPaymentHistory | ok |
| `students.list` | `search`, `limit` | — | GET /students/student | Students::list | ok |
| `finance.getStudentFeeStatement` | — | — | GET /finance/students/fee-statement/ | Finance::getStudentFeeStatement | ok |

## Backend params (expected)

| Handler | Input keys ($data/$_POST/$_GET) | Path id | Data sources (views/procs/tables) |
|---|---|---|---|
| Finance::index | — | — | — |
| Finance::get | `type`, `payroll_id` | `id` | — |
| Staff::getDepartments | — | `id` | — |

## Response shape (data keys consumed)

- `finance.get -> receipt_no`
- `finance.get -> student_name`
- `finance.get -> amount`
- `finance.get -> payment_method`
- `finance.get -> payment_date`
- `finance.get -> status`
- `finance.generateReceipt -> url`
- `finance.generateReceipt -> file_path`
- `finance.getPayrollSummary -> total_staff`
- `finance.getPayrollSummary -> gross_amount`
- `finance.getPayrollSummary -> total_deductions`
- `finance.getPayrollSummary -> net_amount`
- `finance.getPayrollSummary -> status`
- `finance.generatePayrollReport -> url`
- `finance.generatePayrollReport -> file_path`
- `finance.getOutstandingFees -> total_outstanding`
- `finance.getOutstandingFees -> student_count`

## UI / security

- `innerHTML`/`insertAdjacentHTML` assignments: `18` (with interpolation: `1`)
- `escapeHtml()` calls: `11` — XSS check: `PASS`
- Bootstrap modal usage: `0`
- Payload/backend param match: `OK`
- Fix flags: none
- Info flags: none
- Fix task: `—`

## End-to-end trace

> collect → store → analyse → display: page payload keys → API endpoint → controller method → service passthrough → SQL views/procs/tables (rows above). This is machine-derived; the interactive flow (form submit → render) needs human sign-off.

## Review checklist

| Check | Status | Notes |
|---|---|---|
| Init: `AuthContext.ready()` | ✅ | machine |
| Data load: `window.API.*`, no raw fetch | ✅ | machine |
| Params: sent ≈ backend `$data` | ⚠️ | heuristic |
| Response: unwrapped `data` handled | ✅ | heuristic |
| UI: Bootstrap + `escapeHtml` | ✅ | machine |
| Responsive @375/768/1200px | ⚠️ | **manual sign-off required** |
