# manage_subjects.php

- **File**: `pages/manage_subjects.php`
- **Controller**: `manage_subjects.js`
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
| `academic.listCurriculumUnits` | — | — | GET /academic/curriculum-units-list | Academic::listCurriculumUnits | ok |
| `academic.updateLearningArea` | — | — | PUT /academic/learning-areas/update/ | Academic::updateLearningArea | ok |
| `academic.createLearningArea` | — | — | POST /academic/learning-areas/create | Academic::createLearningArea | ok |
| `academic.updateCurriculumUnit` | — | — | PUT /academic/curriculum-units-update/ | Academic::updateCurriculumUnit | ok |
| `academic.createCurriculumUnit` | — | — | POST /academic/curriculum-units-create | Academic::createCurriculumUnit | ok |
| `academic.getLearningArea` | — | — | GET /academic/learning-areas/get/ | Academic::getLearningArea | ok |
| `academic.getCurriculumUnit` | — | — | GET /academic/curriculum-units-get/<br>GET /academic/curriculum-units-get | Academic::getCurriculumUnit<br>Academic::getCurriculumUnit | ok<br>ok |
| `academic.deleteLearningArea` | — | — | DELETE /academic/learning-areas/delete/ | Academic::deleteLearningArea | ok |
| `academic.deleteCurriculumUnit` | — | — | DELETE /academic/curriculum-units-delete/ | Academic::deleteCurriculumUnit | ok |

## Backend params (expected)

_(no backend handler resolved for param extraction)_

## Response shape (data keys consumed)

- `academic.listCurriculumUnits -> units`
- `academic.listCurriculumUnits -> curriculum_units`
- `academic.listLearningAreas -> length`
- `academic.listLearningAreas -> map`
- `academic.getLearningArea -> name`
- `academic.getLearningArea -> code`
- `academic.getLearningArea -> description`
- `academic.getCurriculumUnit -> name`
- `academic.getCurriculumUnit -> learning_area_id`
- `academic.getCurriculumUnit -> duration`
- `academic.getCurriculumUnit -> sort_order`
- `academic.getCurriculumUnit -> learning_outcomes`
- `academic.getCurriculumUnit -> description`
- `academic.getCurriculumUnit -> sugg`

## UI / security

- `innerHTML`/`insertAdjacentHTML` assignments: `8` (with interpolation: `1`)
- `escapeHtml()` calls: `6` — XSS check: `PASS`
- Bootstrap modal usage: `6`
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
