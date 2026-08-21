# manage_classes.php

- **File**: `pages/manage_classes.php`
- **Controller**: `academics.js`
- **Roles**: —
- **Sidebar item(s)**: —

## Init / auth

- `AuthContext.ready()` awaited: `Y`
- Permission/RBAC guard: `N`
- Raw `fetch`/`XMLHttpRequest`/`axios`: `0`

## API methods used

| API method | Payload keys (sent) | Flags | Endpoint | Handler | Status |
|---|---|---|---|---|---|
| `academic.listClasses` | — | — | GET /academic/classes-list | Academic::listClasses | ok |
| `academic.listLevels` | — | — | GET /academic/levels-list | Academic::listLevels | ok |
| `academic.listStreams` | — | — | GET /academic/streams-list | Academic::listStreams | ok |
| `academic.listTeachers` | `limit` | — | GET /academic/teachers-list | Academic::listTeachers | ok |
| `academic.updateClass` | — | — | PUT /academic/classes/update/ | Academic::updateClass | ok |
| `academic.createClass` | — | — | POST /academic/classes/create | Academic::createClass | ok |
| `academic.deleteClass` | — | — | DELETE /academic/classes/delete/ | Academic::deleteClass | ok |
| `academic.updateStream` | — | — | PUT /academic/streams/update/ | Academic::updateStream | ok |
| `academic.createStream` | — | — | POST /academic/streams/create | Academic::createStream | ok |
| `academic.deleteStream` | — | — | DELETE /academic/streams/delete/ | Academic::deleteStream | ok |
| `academic.listSchedules` | — | — | GET /academic/schedules-list | Academic::listSchedules | ok |

## Direct `callAPI` endpoints

| Endpoint | Payload keys (sent) | Flags |
|---|---|---|
| `GET /academic/learning-areas/list` | — | — |
| `GET /academic/learning-areas/update/` | — | — |
| `POST /academic/learning-areas/create` | — | — |
| `GET /academic/learning-areas/delete/` | — | — |
| `GET /academic/curriculum-units/${id}` | — | — |
| `GET /academic/curriculum-units/` | — | — |
| `POST /academic/curriculum-units` | — | — |
| `GET /staff?type=teaching&status=active&limit=200` | — | — |

## Backend params (expected)

| Handler | Input keys ($data/$_POST/$_GET) | Path id | Data sources (views/procs/tables) |
|---|---|---|---|
| Academic::getLearningAreasList | — | — | learning_areas |
| Academic::getLearningAreas | — | `id` | — |
| Academic::postLearningAreasCreate | `name`, `code`, `description`, `status` | — | learning_areas |
| Academic::getCurriculumUnits | — | `id` | — |
| Academic::postCurriculumUnits | — | `id` | — |
| Staff::get | — | `id` | staff_experience, staff_qualifications, departments, persons, roles, staff, staff_categories, staff_department_assignments, staff_payroll_profiles, staff_types, user_roles, users |

## Response shape (data keys consumed)

- `direct.callAPI -> id`
- `direct.callAPI -> name`
- `direct.callAPI -> code`
- `direct.callAPI -> sort_order`
- `direct.callAPI -> order_sequence`
- `direct.callAPI -> learning_area_id`
- `direct.callAPI -> term_number`
- `direct.callAPI -> duration`
- `direct.callAPI -> description`
- `direct.callAPI -> objectives`
- `direct.callAPI -> topics`
- `direct.callAPI -> suggested_resources`
- `direct.callAPI -> resources_needed`
- `direct.callAPI -> status`

## UI / security

- `innerHTML`/`insertAdjacentHTML` assignments: `36` (with interpolation: `2`)
- `escapeHtml()` calls: `30` — XSS check: `PASS`
- Bootstrap modal usage: `10`
- Payload/backend param match: `OK`
- Fix flags: none
- Info flags: none
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
