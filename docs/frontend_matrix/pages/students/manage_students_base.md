# students/manage_students_base.php

- **File**: `pages/students/manage_students_base.php`
- **Controller**: `manage_students.js`
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
| `finance.listStudentTypes` | — | — | GET /finance/student-types-list | Finance::listStudentTypes | ok |
| `academic.listStreams` | — | — | GET /academic/streams-list | Academic::listStreams | ok |
| `students.getParentsList` | — | — | GET /students/parents/list | Students::getParentsList | ok |
| `students.list` | — | — | GET /students/student | Students::list | ok |
| `students.update` | — | — | PUT /students/student/ | Students::update | ok |
| `students.create` | — | — | POST /students/student | Students::create | ok |
| `students.uploadPhoto` | — | — | POST /students/photo-upload | Students::uploadPhoto | ok |
| `students.get` | — | — | GET /students/student/<br>GET /students/student | Students::get<br>Students::get | ok<br>ok |
| `students.getParents` | — | — | GET /students/parents-get/<br>GET /students/parents-get | Students::getParents<br>Students::getParents | ok<br>ok |
| `students.getFees` | — | — | GET /students/fees-get/<br>GET /students/fees-get | Students::getFees<br>Students::getFees | ok<br>ok |
| `students.getAttendance` | — | — | GET /students/attendance-get/<br>GET /students/attendance-get | Students::getAttendance<br>Students::getAttendance | ok<br>ok |
| `students.getPerformance` | — | — | GET /students/performance-get/<br>GET /students/performance-get | Students::getPerformance<br>Students::getPerformance | ok<br>ok |
| `students.getDiscipline` | — | — | GET /students/discipline-get/<br>GET /students/discipline-get | Students::getDiscipline<br>Students::getDiscipline | ok<br>ok |
| `students.delete` | — | — | DELETE /students/student/ | Students::delete | ok |
| `students.startTransferWorkflow` | `student_id`, `target_class_id`, `target_stream_id`, `reason` | — | POST /students/transfer-start-workflow | Students::startTransferWorkflow | ok |

## Backend params (expected)

| Handler | Input keys ($data/$_POST/$_GET) | Path id | Data sources (views/procs/tables) |
|---|---|---|---|
| Students::getParentsList | — | — | vw_student_fee_balances, parents, persons, student_parents, vw_student_fee_balances |

## Response shape (data keys consumed)

_(no post-await `.prop` consumption detected)_

## UI / security

- `innerHTML`/`insertAdjacentHTML` assignments: `18` (with interpolation: `0`)
- `escapeHtml()` calls: `4` — XSS check: `PASS`
- Bootstrap modal usage: `8`
- Payload/backend param match: `OK`
- Fix flags: none
- Info flags: `TEMPLATE_FRAGMENT`, `NO_AUTH_GUARD`, `ESCAPED_LITERAL_HTML`
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
