# parent_meetings.php

- **File**: `pages/parent_meetings.php`
- **Controller**: `parent_meetings.js`
- **Roles**: —
- **Sidebar item(s)**: —

## Init / auth

- `AuthContext.ready()` awaited: `N`
- Permission/RBAC guard: `Y`
- Raw `fetch`/`XMLHttpRequest`/`axios`: `0`

## API methods used

| API method | Payload keys (sent) | Flags | Endpoint | Handler | Status |
|---|---|---|---|---|---|
| `academic.listClasses` | — | — | GET /academic/classes-list | Academic::listClasses | ok |
| `academic.postCustom` | `action`, `meeting_id` | — | POST /academic/custom | Academic::postCustom | ok |
| `communications.updateCommunication` | `status` | — | PUT /communications/communication/ | Communications::updateCommunication | ok |
| `communications.createCommunication` | — | — | POST /communications/communication | Communications::createCommunication | ok |

## Backend params (expected)

| Handler | Input keys ($data/$_POST/$_GET) | Path id | Data sources (views/procs/tables) |
|---|---|---|---|
| Academic::postCustom | `id`, `action`, `subject_id`, `day_of_week`, `start_time`, `end_time` | `id` | — |

## Response shape (data keys consumed)

_(no post-await `.prop` consumption detected)_

## UI / security

- `innerHTML`/`insertAdjacentHTML` assignments: `10` (with interpolation: `1`)
- `escapeHtml()` calls: `16` — XSS check: `PASS`
- Bootstrap modal usage: `2`
- Payload/backend param match: `WARN`
- Fix flags: none
- Info flags: `TEMPLATE_FRAGMENT`
- Fix task: `—`

## End-to-end trace

> collect → store → analyse → display: page payload keys → API endpoint → controller method → service passthrough → SQL views/procs/tables (rows above). This is machine-derived; the interactive flow (form submit → render) needs human sign-off.

## Review checklist

| Check | Status | Notes |
|---|---|---|
| Init: `AuthContext.ready()` | ❌ | machine |
| Data load: `window.API.*`, no raw fetch | ✅ | machine |
| Params: sent ≈ backend `$data` | ❌ | heuristic |
| Response: unwrapped `data` handled | ⚠️ | heuristic |
| UI: Bootstrap + `escapeHtml` | ✅ | machine |
| Responsive @375/768/1200px | ⚠️ | **manual sign-off required** |
