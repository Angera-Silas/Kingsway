# supervision_roster.php

- **File**: `pages/supervision_roster.php`
- **Controller**: `supervision_roster.js`
- **Roles**: —
- **Sidebar item(s)**: —

## Init / auth

- `AuthContext.ready()` awaited: `N`
- Permission/RBAC guard: `N`
- Raw `fetch`/`XMLHttpRequest`/`axios`: `0`

## API methods used

| API method | Payload keys (sent) | Flags | Endpoint | Handler | Status |
|---|---|---|---|---|---|
| `academic.listSupervisionRoster` | — | — | GET /academic/supervision-roster | Academic::listSupervisionRoster | ok |
| `academic.listExamSchedules` | — | — | GET /academic/exam-schedule | Academic::listExamSchedules | ok |
| `academic.updateSupervisionRoster` | — | — | PUT /academic/supervision-roster/ | Academic::updateSupervisionRoster | ok |
| `academic.createSupervisionRoster` | — | — | POST /academic/supervision-roster | Academic::createSupervisionRoster | ok |
| `academic.getSupervisionRoster` | — | — | GET /academic/supervision-roster/ | Academic::getSupervisionRoster | ok |
| `academic.deleteSupervisionRoster` | — | — | DELETE /academic/supervision-roster/ | Academic::deleteSupervisionRoster | ok |
| `academic.autoGenerateSupervisionRoster` | — | — | POST /academic/supervision-roster-auto-generate | Academic::autoGenerateSupervisionRoster | ok |

## Backend params (expected)

| Handler | Input keys ($data/$_POST/$_GET) | Path id | Data sources (views/procs/tables) |
|---|---|---|---|
| Academic::getSupervisionRoster | — | `id` | exam_schedules, persons, staff, supervision_rosters, time_slots |
| Academic::deleteSupervisionRoster | — | `id` | supervision_rosters |

## Response shape (data keys consumed)

- `academic.listSupervisionRoster -> roster`
- `academic.listSupervisionRoster -> pagination`
- `academic.listExamSchedules -> exams`
- `academic.listExamSchedules -> filter`
- `academic.listExamSchedules -> map`

## UI / security

- `innerHTML`/`insertAdjacentHTML` assignments: `5` (with interpolation: `0`)
- `escapeHtml()` calls: `7` — XSS check: `PASS`
- Bootstrap modal usage: `2`
- Payload/backend param match: `NA`
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
