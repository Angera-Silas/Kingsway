# learning_areas.php

- **File**: `pages/learning_areas.php`
- **Controller**: `learning_areas.js`
- **Roles**: —
- **Sidebar item(s)**: —

## Init / auth

- `AuthContext.ready()` awaited: `N`
- Permission/RBAC guard: `N`
- Raw `fetch`/`XMLHttpRequest`/`axios`: `0`

## API methods used

| API method | Payload keys (sent) | Flags | Endpoint | Handler | Status |
|---|---|---|---|---|---|
| `academic.listLearningAreas` | — | — | GET /academic/learning-areas/list | Academic::listLearningAreas | ok |
| `academic.listClasses` | — | — | GET /academic/classes-list | Academic::listClasses | ok |
| `academic.getCustom` | `action` | — | GET /academic/custom | Academic::getCustom | ok |
| `academic.listCurriculumUnits` | — | — | GET /academic/curriculum-units-list | Academic::listCurriculumUnits | ok |
| `academic.getSchemeOfWork` | — | — | GET /academic/scheme-of-work-get/<br>GET /academic/scheme-of-work-get | Academic::getSchemeOfWork<br>Academic::getSchemeOfWork | ok<br>ok |
| `academic.updateLearningArea` | — | — | PUT /academic/learning-areas/update/ | Academic::updateLearningArea | ok |
| `academic.createLearningArea` | — | — | POST /academic/learning-areas/create | Academic::createLearningArea | ok |
| `academic.getLearningArea` | — | — | GET /academic/learning-areas/get/ | Academic::getLearningArea | ok |
| `academic.deleteLearningArea` | — | — | DELETE /academic/learning-areas/delete/ | Academic::deleteLearningArea | ok |

## Backend params (expected)

| Handler | Input keys ($data/$_POST/$_GET) | Path id | Data sources (views/procs/tables) |
|---|---|---|---|
| Academic::getCustom | `id`, `action` | `id` | — |

## Response shape (data keys consumed)

- `academic.listCurriculumUnits -> length`
- `academic.listCurriculumUnits -> forEach`
- `academic.getLearningArea -> code`
- `academic.getLearningArea -> name`
- `academic.getLearningArea -> category`
- `academic.getLearningArea -> status`
- `academic.getLearningArea -> teacher_count`
- `academic.getLearningArea -> class_count`
- `academic.getLearningArea -> description`

## UI / security

- `innerHTML`/`insertAdjacentHTML` assignments: `16` (with interpolation: `2`)
- `escapeHtml()` calls: `20` — XSS check: `PASS`
- Bootstrap modal usage: `3`
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
