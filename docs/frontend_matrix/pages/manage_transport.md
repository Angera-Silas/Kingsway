# manage_transport.php

- **File**: `pages/manage_transport.php`
- **Controller**: `transport.js`
- **Roles**: —
- **Sidebar item(s)**: —

## Init / auth

- `AuthContext.ready()` awaited: `Y`
- Permission/RBAC guard: `N`
- Raw `fetch`/`XMLHttpRequest`/`axios`: `0`

## API methods used

| API method | Payload keys (sent) | Flags | Endpoint | Handler | Status |
|---|---|---|---|---|---|
| `transport.getAllRoutes` | — | — | GET /transport/all-routes | Transport::getAllRoutes | ok |
| `transport.createRoute` | — | — | POST /transport/transport-route | Transport::createRoute | ok |
| `transport.getRoute` | — | — | GET /transport/transport-route/<br>GET /transport/transport-route | Transport::getRoute<br>Transport::getRoute | ok<br>ok |
| `transport.getStudentsByRoute` | — | — | GET /transport/students-by-route?route_id= | Transport::getStudentsByRoute | ok |
| `transport.deleteRoute` | — | — | DELETE /transport/transport-route/ | Transport::deleteRoute | ok |
| `transport.getAllStops` | — | — | GET /transport/all-stops | Transport::getAllStops | ok |
| `transport.createStop` | — | — | POST /transport/transport-stop | Transport::createStop | ok |
| `transport.deleteStop` | — | — | DELETE /transport/transport-stop/ | Transport::deleteStop | ok |
| `transport.getAllDrivers` | — | — | GET /transport/all-drivers | Transport::getAllDrivers | ok |
| `transport.createDriver` | — | — | POST /transport/transport-driver | Transport::createDriver | ok |
| `transport.assignDriver` | `route_id`, `driver_id` | — | POST /transport/driver-assign | Transport::assignDriver | ok |
| `transport.deleteDriver` | — | — | DELETE /transport/transport-driver/ | Transport::deleteDriver | ok |
| `transport.assignStudent` | — | — | POST /transport/assign-student | Transport::assignStudent | ok |
| `transport.withdrawAssignment` | `student_id` | — | POST /transport/withdraw-assignment | Transport::withdrawAssignment | ok |
| `transport.verifyStudent` | `student_id` | — | POST /transport/verify-student | Transport::verifyStudent | ok |
| `transport.recordPayment` | — | — | POST /transport/record-payment | Transport::recordPayment | ok |
| `transport.getPaymentSummary` | — | — | GET /transport/payment-summary?student_id= | Transport::getPaymentSummary | ok |

## Backend params (expected)

| Handler | Input keys ($data/$_POST/$_GET) | Path id | Data sources (views/procs/tables) |
|---|---|---|---|
| Transport::getAllRoutes | — | — | — |
| Transport::getStudentsByRoute | `route_id`, `month`, `year` | — | — |
| Transport::getAllStops | — | — | — |
| Transport::getAllDrivers | — | — | — |
| Transport::getPaymentSummary | `student_id` | — | — |

## Response shape (data keys consumed)

- `transport.getStudentsByRoute -> length`
- `transport.getStudentsByRoute -> forEach`
- `transport.verifyStudent -> verified`
- `transport.verifyStudent -> student_name`
- `transport.getPaymentSummary -> total_paid`
- `transport.getPaymentSummary -> outstanding`
- `transport.getPaymentSummary -> last_payment_date`

## UI / security

- `innerHTML`/`insertAdjacentHTML` assignments: `4` (with interpolation: `0`)
- `escapeHtml()` calls: `0` — XSS check: `PASS`
- Bootstrap modal usage: `0`
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
