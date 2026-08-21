# all_lesson_plans.php

- **File**: `pages/all_lesson_plans.php`
- **Controller**: `all_lesson_plans.js`
- **Roles**: —
- **Sidebar item(s)**: —

## Init / auth

- `AuthContext.ready()` awaited: `N`
- Permission/RBAC guard: `N`
- Raw `fetch`/`XMLHttpRequest`/`axios`: `0`

## API methods used

| API method | Payload keys (sent) | Flags | Endpoint | Handler | Status |
|---|---|---|---|---|---|
| `academic.listLessonPlans` | — | — | GET /academic/lesson-plans-list | Academic::listLessonPlans | ok |
| `academic.updateLessonPlan` | — | — | PUT /academic/lesson-plans-update | Academic::updateLessonPlan | ok |
| `academic.createLessonPlan` | — | — | POST /academic/lesson-plans-create | Academic::createLessonPlan | ok |
| `academic.getLessonPlan` | — | — | GET /academic/lesson-plans-get/<br>GET /academic/lesson-plans-get | Academic::getLessonPlan<br>Academic::getLessonPlan | ok<br>ok |
| `academic.deleteLessonPlan` | — | — | DELETE /academic/lesson-plans-delete | Academic::deleteLessonPlan | ok |

## Backend params (expected)

_(no backend handler resolved for param extraction)_

## Response shape (data keys consumed)

- `academic.listLessonPlans -> lesson_plans`
- `academic.listLessonPlans -> pagination`
- `academic.listLessonPlans -> total`
- `academic.listLessonPlans -> length`
- `academic.listLessonPlans -> filter`
- `academic.getLessonPlan -> title`
- `academic.getLessonPlan -> status`
- `academic.getLessonPlan -> teacher_name`
- `academic.getLessonPlan -> subject_name`
- `academic.getLessonPlan -> date`
- `academic.getLessonPlan -> lesson_date`
- `academic.getLessonPlan -> objectives`
- `academic.getLessonPlan -> learning_objectives`
- `academic.getLessonPlan -> content`
- `academic.getLessonPlan -> activities`
- `academic.getLessonPlan -> resources`
- `academic.getLessonPlan -> materials`
- `academic.getLessonPlan -> assessment`
- `academic.getLessonPlan -> feedback`

## UI / security

- `innerHTML`/`insertAdjacentHTML` assignments: `5` (with interpolation: `0`)
- `escapeHtml()` calls: `0` — XSS check: `PASS`
- Bootstrap modal usage: `3`
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
