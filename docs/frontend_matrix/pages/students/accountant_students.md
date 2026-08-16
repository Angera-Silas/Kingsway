# students/accountant_students.php

- **File**: `pages/students/accountant_students.php`
- **Controller**: `all_students_accountant.js`
- **Roles**: —
- **Sidebar item(s)**: —

## Init / auth

- `AuthContext.ready()` awaited: `N`
- Permission/RBAC guard: `N`
- Raw `fetch`/`XMLHttpRequest`/`axios`: `0`

## API methods used

| API method | Payload keys (sent) | Flags | Endpoint | Handler | Status |
|---|---|---|---|---|---|
| `academic.listClasses` | — | — | GET /academic/classes-list | Academic::listClasses | ok |
| `academic.listStreams` | — | — | GET /academic/streams-list | Academic::listStreams | ok |
| `finance.listStudentTypes` | — | — | GET /finance/student-types-list | Finance::listStudentTypes | ok |
| `students.list` | — | — | GET /students/student | Students::list | ok |
| `finance.getStudentsBalances` | — | — | None None | None::getStudentsBalances | no_api_method |
| `finance.getStudentPaymentHistory` | — | — | GET /finance/students-payment-history?student_id= | Finance::getStudentPaymentHistory | ok |
| `finance.getStudentBalance` | — | — | GET /finance/students/balance/ | Finance::getStudentBalance | ok |
| `students.getEnrollmentHistory` | — | — | GET /students/enrollment-history/ | Students::getEnrollmentHistory | ok |
| `finance.getStudentInvoiceTrack` | — | — | None None | None::getStudentInvoiceTrack | no_api_method |

## Backend params (expected)

| Handler | Input keys ($data/$_POST/$_GET) | Path id | Data sources (views/procs/tables) |
|---|---|---|---|
| Students::getEnrollmentHistory | `student_id` | `id` | academic_year_class_streams, academic_year_classes, academic_years, classes, streams, student_academic_enrollments |

## Response shape (data keys consumed)

_(no post-await `.prop` consumption detected)_

## UI / security

- `innerHTML`/`insertAdjacentHTML` assignments: `6` (with interpolation: `1`)
- `escapeHtml()` calls: `31` — XSS check: `PASS`
- Bootstrap modal usage: `1`
- Payload/backend param match: `OK`
- Fix flags: `UNRESOLVED_ENDPOINT`
- Info flags: `NO_AUTH_GUARD`
- Fix task: `FIX-3-0065`

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
