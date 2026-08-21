# add_results.php

- **File**: `pages/add_results.php`
- **Controller**: `add_results.js`
- **Roles**: —
- **Sidebar item(s)**: —

## Init / auth

- `AuthContext.ready()` awaited: `N`
- Permission/RBAC guard: `N`
- Raw `fetch`/`XMLHttpRequest`/`axios`: `0`

## API methods used

| API method | Payload keys (sent) | Flags | Endpoint | Handler | Status |
|---|---|---|---|---|---|
| `academic.getAllAcademicYears` | — | — | GET /academic/years/list | Academic::getAllAcademicYears | ok |
| `academic.listClasses` | — | — | GET /academic/classes-list | Academic::listClasses | ok |
| `academic.listLearningAreas` | — | — | GET /academic/learning-areas/list | Academic::listLearningAreas | ok |
| `academic.getTerms` | — | — | GET /academic/terms | Academic::getTerms | ok |
| `academic.getAssessmentTypes` | — | — | GET /academic/assessment-types | Academic::getAssessmentTypes | ok |
| `students.get` | — | — | GET /students/student/<br>GET /students/student | Students::get<br>Students::get | ok<br>ok |
| `academic.recordMarks` | — | — | POST /academic/exams-record-marks | Academic::recordMarks | ok |

## Backend params (expected)

| Handler | Input keys ($data/$_POST/$_GET) | Path id | Data sources (views/procs/tables) |
|---|---|---|---|
| Academic::getTerms | — | `id` | — |
| Academic::getAssessmentTypes | `filter` | — | assessment_types |

## Response shape (data keys consumed)

- `academic.getAllAcademicYears -> forEach`
- `academic.listClasses -> forEach`
- `academic.listLearningAreas -> forEach`
- `academic.getTerms -> terms`
- `academic.getAssessmentTypes -> assessment_types`
- `students.get -> forEach`

## UI / security

- `innerHTML`/`insertAdjacentHTML` assignments: `6` (with interpolation: `1`)
- `escapeHtml()` calls: `5` — XSS check: `PASS`
- Bootstrap modal usage: `0`
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
