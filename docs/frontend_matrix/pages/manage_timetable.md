# manage_timetable.php

- **File**: `pages/manage_timetable.php`
- **Controller**: `manage_timetable.js`
- **Roles**: —
- **Sidebar item(s)**: —

## Init / auth

- `AuthContext.ready()` awaited: `N`
- Permission/RBAC guard: `Y`
- Raw `fetch`/`XMLHttpRequest`/`axios`: `0`

## API methods used

| API method | Payload keys (sent) | Flags | Endpoint | Handler | Status |
|---|---|---|---|---|---|
| `schedules.getTimeSlots` | — | — | GET /schedules/timetable-time-slots | Schedules::getTimeSlots | ok |
| `academic.listClasses` | — | — | GET /academic/classes-list | Academic::listClasses | ok |
| `academic.listCurriculumUnits` | — | — | GET /academic/curriculum-units-list | Academic::listCurriculumUnits | ok |
| `academic.listTerms` | — | — | GET /academic/terms-list | Academic::listTerms | ok |
| `schedules.getRooms` | — | — | GET /schedules/rooms-get/<br>GET /schedules/rooms-get | Schedules::getRooms<br>Schedules::getRooms | ok<br>ok |
| `schedules.getTimetable` | — | — | GET /schedules/timetable-get | Schedules::getTimetable | ok |
| `schedules.checkTimetableConflicts` | — | — | GET /schedules/timetable-check-conflicts | Schedules::checkTimetableConflicts | ok |
| `schedules.updateTimetable` | — | — | PUT /schedules/timetable-update/ | Schedules::updateTimetable | ok |
| `schedules.createTimetable` | — | — | POST /schedules/timetable-create | Schedules::createTimetable | ok |
| `schedules.deleteTimetableById` | — | — | DELETE /schedules/timetable-delete/ | Schedules::deleteTimetableById | ok |
| `schedules.deleteTimetable` | `day`, `start_time`, `class_id` | — | POST /schedules/timetable-delete | Schedules::deleteTimetable | ok |
| `schedules.reportTimetableConflict` | `description`, `time_slot`, `conflict_type` | — | POST /schedules/timetable-report-conflict | Schedules::reportTimetableConflict | ok |
| `schedules.startSchedulingWorkflow` | `reference_type`, `reference_id`, `initial_data` | — | POST /schedules/start-scheduling-workflow | Schedules::startSchedulingWorkflow | ok |

## Backend params (expected)

_(no backend handler resolved for param extraction)_

## Response shape (data keys consumed)

- `schedules.getTimeSlots -> length`
- `schedules.getTimeSlots -> map`
- `schedules.checkTimetableConflicts -> total`
- `schedules.checkTimetableConflicts -> conflicts`
- `schedules.getTimetable -> forEach`

## UI / security

- `innerHTML`/`insertAdjacentHTML` assignments: `10` (with interpolation: `0`)
- `escapeHtml()` calls: `0` — XSS check: `PASS`
- Bootstrap modal usage: `6`
- Payload/backend param match: `OK`
- Fix flags: none
- Info flags: `ESCAPED_LITERAL_HTML`
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
