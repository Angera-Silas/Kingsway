# timetable.php

- **File**: `pages/timetable.php`
- **Controller**: `timetable.js`
- **Roles**: —
- **Sidebar item(s)**: —

## Init / auth

- `AuthContext.ready()` awaited: `N`
- Permission/RBAC guard: `N`
- Raw `fetch`/`XMLHttpRequest`/`axios`: `0`

## API methods used

| API method | Payload keys (sent) | Flags | Endpoint | Handler | Status |
|---|---|---|---|---|---|
| `schedules.getTimeSlots` | — | — | GET /schedules/timetable-time-slots | Schedules::getTimeSlots | ok |
| `academic.listClasses` | — | — | GET /academic/classes-list | Academic::listClasses | ok |
| `academic.getTeachers` | — | — | GET /academic/teachers-list | Academic::getTeachers | ok |
| `schedules.getRooms` | — | — | GET /schedules/rooms-get/<br>GET /schedules/rooms-get | Schedules::getRooms<br>Schedules::getRooms | ok<br>ok |
| `schedules.getTimetable` | — | — | GET /schedules/timetable-get | Schedules::getTimetable | ok |

## Backend params (expected)

_(no backend handler resolved for param extraction)_

## Response shape (data keys consumed)

- `schedules.getTimeSlots -> length`
- `schedules.getTimeSlots -> map`

## UI / security

- `innerHTML`/`insertAdjacentHTML` assignments: `6` (with interpolation: `1`)
- `escapeHtml()` calls: `4` — XSS check: `PASS`
- Bootstrap modal usage: `0`
- Payload/backend param match: `NA`
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
