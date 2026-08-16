# manage_lesson_plans.php

- **File**: `pages/manage_lesson_plans.php`
- **Controller**: `manage_lesson_plans.js`
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
| `academic.listLearningAreas` | — | — | GET /academic/learning-areas/list | Academic::listLearningAreas | ok |
| `academic.getTeachers` | — | — | GET /academic/teachers-list | Academic::getTeachers | ok |
| `academic.getCurriculumUnits` | `learning_area_id` | — | GET /academic/curriculum-units-list | Academic::getCurriculumUnits | ok |
| `academic.listLessonPlans` | — | — | GET /academic/lesson-plans-list | Academic::listLessonPlans | ok |
| `academic.updateLessonPlan` | — | — | PUT /academic/lesson-plans-update | Academic::updateLessonPlan | ok |
| `academic.createLessonPlan` | — | — | POST /academic/lesson-plans-create | Academic::createLessonPlan | ok |
| `academic.submitLessonPlan` | `plan_id` | — | POST /academic/lesson-plans-submit | Academic::submitLessonPlan | ok |
| `academic.approveLessonPlan` | `plan_id` | — | POST /academic/lesson-plans-approve | Academic::approveLessonPlan | ok |
| `academic.rejectLessonPlan` | `plan_id`, `remarks` | — | POST /academic/lesson-plans-reject | Academic::rejectLessonPlan | ok |
| `academic.deleteLessonPlan` | — | — | DELETE /academic/lesson-plans-delete | Academic::deleteLessonPlan | ok |

## Backend params (expected)

| Handler | Input keys ($data/$_POST/$_GET) | Path id | Data sources (views/procs/tables) |
|---|---|---|---|
| Academic::getCurriculumUnits | — | `id` | — |

## Response shape (data keys consumed)

_(no post-await `.prop` consumption detected)_

## UI / security

- `innerHTML`/`insertAdjacentHTML` assignments: `7` (with interpolation: `0`)
- `escapeHtml()` calls: `0` — XSS check: `PASS`
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
| Response: unwrapped `data` handled | ⚠️ | heuristic |
| UI: Bootstrap + `escapeHtml` | ✅ | machine |
| Responsive @375/768/1200px | ⚠️ | **manual sign-off required** |
