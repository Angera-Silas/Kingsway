# staff/manage_staff_base.php

- **File**: `pages/staff/manage_staff_base.php`
- **Controller**: `staff.js`
- **Roles**: —
- **Sidebar item(s)**: —

## Init / auth

- `AuthContext.ready()` awaited: `N`
- Permission/RBAC guard: `N`
- Raw `fetch`/`XMLHttpRequest`/`axios`: `0`

## API methods used

| API method | Payload keys (sent) | Flags | Endpoint | Handler | Status |
|---|---|---|---|---|---|
| `staff.index` | — | — | GET /staff/index | Staff::index | ok |
| `staff.getDepartments` | — | — | GET /staff/departments-get/<br>GET /staff/departments-get | Staff::getDepartments<br>Staff::getDepartments | ok<br>ok |
| `users.getRoles` | — | — | GET /users/roles-get/<br>GET /users/roles-get | Users::getRoles<br>Users::getRoles | ok<br>ok |
| `staff.update` | — | — | PUT /staff/ | Staff::update | ok |
| `staff.create` | — | — | POST /staff | Staff::create | ok |
| `staff.get` | — | — | GET /staff/<br>GET /staff | Staff::get<br>Staff::get | ok<br>ok |
| `staff.delete` | — | — | DELETE /staff/ | Staff::delete | ok |
| `staff.listLeaves` | — | — | GET /staff/leaves-list | Staff::listLeaves | ok |
| `staff.updateLeaveStatus` | — | — | PUT /staff/leaves-update-status | Staff::updateLeaveStatus | ok |
| `staff.getAttendance` | — | — | GET /staff/attendance-get/<br>GET /staff/attendance-get | Staff::getAttendance<br>Staff::getAttendance | ok<br>ok |
| `staff.getPayrollSummary` | — | — | GET /staff/payroll-summary | Staff::getPayrollSummary | ok |
| `staff.listPayroll` | — | — | GET /staff/payroll-list | Staff::listPayroll | ok |
| `staff.listContracts` | — | — | GET /staff/contracts-list | Staff::listContracts | ok |
| `staff.updateContract` | — | — | PUT /staff/contracts-update/ | Staff::updateContract | ok |
| `staff.createContract` | — | — | POST /staff/contracts-create | Staff::createContract | ok |
| `staff.getAssignments` | — | — | GET /staff/assignments-get<br>GET /staff/assignments-get/<br>GET /staff/assignments-get | Staff::getAssignments<br>Staff::getAssignments<br>Staff::getAssignments | ok<br>ok<br>ok |
| `staff.getCurrentAssignments` | — | — | GET /staff/assignments-current?staff_id= | Staff::getCurrentAssignments | ok |
| `academic.listClasses` | — | — | GET /academic/classes-list | Academic::listClasses | ok |
| `academic.listStreams` | `class_id` | — | GET /academic/streams-list | Academic::listStreams | ok |
| `staff.assignClass` | — | — | POST /staff/assign-class | Staff::assignClass | ok |
| `academic.listLearningAreas` | — | — | GET /academic/learning-areas/list | Academic::listLearningAreas | ok |
| `staff.assignSubject` | — | — | POST /staff/assign-subject | Staff::assignSubject | ok |
| `staff.getPerformanceReviewHistory` | — | — | GET /staff/performance-review-history?staff_id= | Staff::getPerformanceReviewHistory | ok |
| `staff.getSchedule` | — | — | GET /staff/schedule-get/<br>GET /staff/schedule-get | Staff::getSchedule<br>Staff::getSchedule | ok<br>ok |
| `staff.getPayrollHistory` | — | — | GET /staff/payroll-history?staff_id= | Staff::getPayrollHistory | ok |
| `staff.getPayslip` | — | — | GET /staff/payroll-payslip?staff_id= | Staff::getPayslip | ok |
| `staff.generateDetailedPayslip` | — | — | GET /staff/payroll-detailed-payslip?staff_id=&month=&year= | Staff::generateDetailedPayslip | ok |

## Backend params (expected)

| Handler | Input keys ($data/$_POST/$_GET) | Path id | Data sources (views/procs/tables) |
|---|---|---|---|
| Staff::index | — | — | departments, persons, roles, staff, staff_categories, staff_department_assignments, staff_payroll_profiles, staff_types, user_roles, users |
| Staff::getDepartments | — | `id` | — |
| Staff::get | — | `id` | staff_experience, staff_qualifications, departments, persons, roles, staff, staff_categories, staff_department_assignments, staff_payroll_profiles, staff_types, user_roles, users |
| Staff::delete | — | `id` | staff |
| Staff::getPayrollSummary | — | — | vw_payslip_detailed, vw_payslip_detailed |
| Staff::getPerformanceReviewHistory | `staff_id` | `id` | staff, users |
| Staff::getPayrollHistory | — | `id` | — |

## Response shape (data keys consumed)

- `staff.listLeaves -> leaves`
- `staff.getAttendance -> attendance`
- `staff.getPayrollSummary -> gross_payroll`
- `staff.getPayrollSummary -> total_deductions`
- `staff.getPayrollSummary -> net_payroll`
- `staff.getPayrollSummary -> pending_approval`
- `staff.getAssignments -> assignments`
- `staff.getCurrentAssignments -> assignments`
- `staff.getPerformanceReviewHistory -> reviews`
- `staff.getSchedule -> schedule`
- `staff.getSchedule -> slots`
- `staff.getSchedule -> length`
- `staff.getSchedule -> map`
- `staff.get -> records`
- `staff.get -> history`
- `staff.get -> reviews`
- `staff.get -> assignments`
- `staff.getPayslip -> first_name`
- `staff.getPayslip -> staff_name`
- `staff.getPayslip -> last_name`
- `staff.getPayslip -> staff_no`
- `staff.getPayslip -> department_name`

## UI / security

- `innerHTML`/`insertAdjacentHTML` assignments: `37` (with interpolation: `0`)
- `escapeHtml()` calls: `9` — XSS check: `PASS`
- Bootstrap modal usage: `15`
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
| Response: unwrapped `data` handled | ✅ | heuristic |
| UI: Bootstrap + `escapeHtml` | ✅ | machine |
| Responsive @375/768/1200px | ⚠️ | **manual sign-off required** |
