# manage_payrolls.php

- **File**: `pages/manage_payrolls.php`
- **Controller**: `payroll_manager.js`
- **Roles**: —
- **Sidebar item(s)**: —

## Init / auth

- `AuthContext.ready()` awaited: `Y`
- Permission/RBAC guard: `N`
- Raw `fetch`/`XMLHttpRequest`/`axios`: `0`

## API methods used

| API method | Payload keys (sent) | Flags | Endpoint | Handler | Status |
|---|---|---|---|---|---|
| `finance.getPayrollList` | — | — | GET /finance/payroll-list | Finance::getPayrollList | ok |
| `finance.getPayrollStats` | — | — | GET /finance/payroll-stats?month=&year= | Finance::getPayrollStats | ok |
| `finance.getStaffForPayroll` | — | — | GET /finance/staff-for-payroll | Finance::getStaffForPayroll | ok |
| `finance.getBulkPayrollPreview` | — | — | GET /finance/bulk-payroll-preview?month=&year= | Finance::getBulkPayrollPreview | ok |
| `finance.processBulkPayroll` | `staff_ids`, `payroll_month`, `payroll_year` | — | POST /finance/process-bulk-payroll | Finance::processBulkPayroll | ok |
| `finance.getStaffPayrollDetails` | — | — | GET /finance/staff-payroll-details?staff_id= | Finance::getStaffPayrollDetails | ok |
| `finance.processPayrollWithDeductions` | — | — | POST /finance/process-payroll-with-deductions | Finance::processPayrollWithDeductions | ok |
| `finance.getDetailedPayslip` | — | — | GET /finance/detailed-payslip?payroll_id= | Finance::getDetailedPayslip | ok |
| `finance.approvePayroll` | — | — | POST /finance/approve-payroll | Finance::approvePayroll | ok |
| `finance.markPayrollPaid` | — | — | POST /finance/mark-payroll-paid | Finance::markPayrollPaid | ok |

## Backend params (expected)

| Handler | Input keys ($data/$_POST/$_GET) | Path id | Data sources (views/procs/tables) |
|---|---|---|---|
| Finance::getPayrollList | — | — | departments, payslips, persons, staff, staff_employment_profiles |
| Finance::getPayrollStats | `month`, `year` | — | payslips, staff, staff_children |
| Finance::getStaffForPayroll | — | — | departments, persons, staff, staff_children, staff_employment_profiles, staff_payroll_profiles, user_roles, users |
| Finance::getBulkPayrollPreview | `month`, `year` | — | — |
| Finance::getStaffPayrollDetails | `staff_id` | `id` | vw_student_fee_balances, academic_year_class_streams, academic_year_classes, academic_year_terms, academic_years, classes, departments, persons, staff, staff_children, staff_employment_profiles, staff_payroll_profiles, streams, student_academic_enrollments, students, vw_student_fee_balances |
| Finance::getDetailedPayslip | `payroll_id` | `id` | academic_year_class_streams, academic_year_classes, classes, departments, payslips, persons, staff, staff_employment_profiles, staff_payroll_profiles, student_academic_enrollments, students, the |

## Response shape (data keys consumed)

- `finance.getPayrollStats -> total_staff`
- `finance.getPayrollStats -> staff_with_children`
- `finance.getPayrollStats -> this_month_net`
- `finance.getPayrollStats -> children_fees_deducted`
- `finance.getBulkPayrollPreview -> map`
- `finance.processBulkPayroll -> processed_count`
- `finance.processBulkPayroll -> failed_count`
- `finance.processBulkPayroll -> failed`
- `finance.getStaffPayrollDetails -> id`
- `finance.getStaffPayrollDetails -> children`
- `finance.processPayrollWithDeductions -> id`
- `finance.processPayrollWithDeductions -> payroll_id`
- `finance.processPayrollWithDeductions -> payslip_id`
- `finance.processPayrollWithDeductions -> net_salary`
- `finance.processPayrollWithDeductions -> staff_id`
- `finance.processPayrollWithDeductions -> message`
- `finance.getDetailedPayslip -> id`
- `finance.getDetailedPayslip -> message`
- `finance.approvePayroll -> status`
- `finance.approvePayroll -> payroll_id`
- `finance.approvePayroll -> message`
- `finance.markPayrollPaid -> payroll_id`
- `finance.markPayrollPaid -> status`
- `finance.markPayrollPaid -> id`
- `finance.markPayrollPaid -> message`

## UI / security

- `innerHTML`/`insertAdjacentHTML` assignments: `12` (with interpolation: `0`)
- `escapeHtml()` calls: `16` — XSS check: `PASS`
- Bootstrap modal usage: `7`
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
| Response: unwrapped `data` handled | ✅ | heuristic |
| UI: Bootstrap + `escapeHtml` | ✅ | machine |
| Responsive @375/768/1200px | ⚠️ | **manual sign-off required** |
