# clubs_societies.php

- **File**: `pages/clubs_societies.php`
- **Controller**: `clubs_societies.js`
- **Roles**: —
- **Sidebar item(s)**: —

## Init / auth

- `AuthContext.ready()` awaited: `N`
- Permission/RBAC guard: `N`
- Raw `fetch`/`XMLHttpRequest`/`axios`: `0`

## API methods used

| API method | Payload keys (sent) | Flags | Endpoint | Handler | Status |
|---|---|---|---|---|---|
| `activities.list` | `category` | — | GET /activities | Activities::list | ok |
| `activities.listCategories` | — | — | GET /activities/categories/list | Activities::listCategories | ok |
| `activities.getSummary` | — | — | GET /activities/statistics/get | Activities::getSummary | ok |
| `activities.get` | — | — | GET /activities/ | Activities::get | ok |
| `activities.listParticipants` | `activity_id` | — | GET /activities/participants/list | Activities::listParticipants | ok |
| `activities.update` | — | — | PUT /activities/ | Activities::update | ok |
| `activities.create` | — | — | POST /activities | Activities::create | ok |

## Backend params (expected)

| Handler | Input keys ($data/$_POST/$_GET) | Path id | Data sources (views/procs/tables) |
|---|---|---|---|
| Activities::get | — | `id` | — |

## Response shape (data keys consumed)

- `activities.get -> name`
- `activities.get -> category_name`
- `activities.get -> patron`
- `activities.get -> teacher_name`
- `activities.get -> member_count`
- `activities.get -> participants`
- `activities.get -> schedule`
- `activities.get -> meeting_day`
- `activities.get -> status`
- `activities.get -> description`
- `activities.listParticipants -> length`

## UI / security

- `innerHTML`/`insertAdjacentHTML` assignments: `5` (with interpolation: `0`)
- `escapeHtml()` calls: `14` — XSS check: `PASS`
- Bootstrap modal usage: `3`
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
