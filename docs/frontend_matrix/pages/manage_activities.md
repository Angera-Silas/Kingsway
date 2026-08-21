# manage_activities.php

- **File**: `pages/manage_activities.php`
- **Controller**: `manage_activities.js`
- **Roles**: —
- **Sidebar item(s)**: —

## Init / auth

- `AuthContext.ready()` awaited: `N`
- Permission/RBAC guard: `N`
- Raw `fetch`/`XMLHttpRequest`/`axios`: `0`

## API methods used

| API method | Payload keys (sent) | Flags | Endpoint | Handler | Status |
|---|---|---|---|---|---|
| `activities.getSummary` | — | — | GET /activities/statistics/get | Activities::getSummary | ok |
| `activities.list` | — | — | GET /activities | Activities::list | ok |
| `activities.get` | — | — | GET /activities/ | Activities::get | ok |
| `activities.update` | — | — | PUT /activities/ | Activities::update | ok |
| `activities.create` | — | — | POST /activities | Activities::create | ok |
| `activities.delete` | — | — | DELETE /activities/ | Activities::delete | ok |
| `activities.listCategories` | — | — | GET /activities/categories/list | Activities::listCategories | ok |
| `activities.getCategory` | — | — | GET /activities/categories/get/ | Activities::getCategory | ok |
| `activities.updateCategory` | — | — | PUT /activities/categories/update/ | Activities::updateCategory | ok |
| `activities.createCategory` | — | — | POST /activities/categories/create | Activities::createCategory | ok |
| `activities.deleteCategory` | — | — | DELETE /activities/categories/delete/ | Activities::deleteCategory | ok |
| `activities.listParticipants` | — | — | GET /activities/participants/list | Activities::listParticipants | ok |
| `activities.registerParticipant` | `activity_id`, `student_id`, `role` | — | POST /activities/participants/register | Activities::registerParticipant | ok |
| `activities.withdrawParticipant` | — | — | POST /activities/participants/withdraw/ | Activities::withdrawParticipant | ok |
| `activities.listSchedules` | — | — | GET /activities/schedules/list | Activities::listSchedules | ok |
| `activities.updateSchedule` | — | — | PUT /activities/schedules/update/ | Activities::updateSchedule | ok |
| `activities.createSchedule` | — | — | POST /activities/schedules/create | Activities::createSchedule | ok |
| `activities.deleteSchedule` | — | — | DELETE /activities/schedules/delete/ | Activities::deleteSchedule | ok |
| `activities.listResources` | — | — | GET /activities/resources/list | Activities::listResources | ok |
| `activities.updateResource` | — | — | PUT /activities/resources/update/ | Activities::updateResource | ok |
| `activities.addResource` | — | — | POST /activities/resources/add | Activities::addResource | ok |
| `activities.deleteResource` | — | — | DELETE /activities/resources/delete/ | Activities::deleteResource | ok |

## Backend params (expected)

| Handler | Input keys ($data/$_POST/$_GET) | Path id | Data sources (views/procs/tables) |
|---|---|---|---|
| Activities::get | — | `id` | — |
| Activities::delete | — | `id` | — |

## Response shape (data keys consumed)

- `activities.getSummary -> total_activities`
- `activities.getSummary -> active_activities`
- `activities.getSummary -> upcoming_activities`
- `activities.getSummary -> total_participants`
- `activities.get -> id`
- `activities.get -> activity_id`
- `activities.get -> name`
- `activities.get -> activity_name`
- `activities.get -> category_id`
- `activities.get -> start_date`
- `activities.get -> end_date`
- `activities.get -> venue`
- `activities.get -> status`
- `activities.get -> max_participants`
- `activities.get -> teacher_in_charge`
- `activities.get -> coordinator`
- `activities.get -> description`
- `activities.getCategory -> id`
- `activities.getCategory -> category_id`
- `activities.getCategory -> name`
- `activities.getCategory -> category_name`
- `activities.getCategory -> description`

## UI / security

- `innerHTML`/`insertAdjacentHTML` assignments: `24` (with interpolation: `6`)
- `escapeHtml()` calls: `23` — XSS check: `PASS`
- Bootstrap modal usage: `14`
- Payload/backend param match: `OK`
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
