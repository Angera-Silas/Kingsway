# assessment_rubrics.php

- **File**: `pages/assessment_rubrics.php`
- **Controller**: `assessment_rubrics.js`
- **Roles**: —
- **Sidebar item(s)**: —

## Init / auth

- `AuthContext.ready()` awaited: `N`
- Permission/RBAC guard: `N`
- Raw `fetch`/`XMLHttpRequest`/`axios`: `0`

## API methods used

_(no `API.<module>.<method>` calls detected)_

## Direct `callAPI` endpoints

| Endpoint | Payload keys (sent) | Flags |
|---|---|---|
| `GET /api/academic/assessment-classifications` | — | — |
| `GET /api/academic/learning-areas` | — | — |
| `POST /api/academic/assessment-tools` | — | — |
| `GET /api/academic/assessment-tools` | — | — |
| `DELETE /api/academic/assessment-rubrics/${id}` | — | — |

## Backend params (expected)

| Handler | Input keys ($data/$_POST/$_GET) | Path id | Data sources (views/procs/tables) |
|---|---|---|---|
| Academic::getAssessmentClassifications | — | — | assessment_type_classifications |
| Academic::getLearningAreas | — | `id` | — |
| Academic::postAssessmentTools | `tool_name`, `assessment_type_id`, `learning_area_id`, `tool_code`, `description`, `grade_level`, `competencies_assessed` | — | assessment_tools, assessment_type_classifications, learning_areas |
| Academic::getAssessmentTools | — | — | assessment_tools, assessment_type_classifications, learning_areas |
| Academic::deleteAssessmentRubrics | — | `id` | assessment_rubrics |

## Response shape (data keys consumed)

_(no post-await `.prop` consumption detected)_

## UI / security

- `innerHTML`/`insertAdjacentHTML` assignments: `6` (with interpolation: `0`)
- `escapeHtml()` calls: `11` — XSS check: `PASS`
- Bootstrap modal usage: `4`
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
| Response: unwrapped `data` handled | ⚠️ | heuristic |
| UI: Bootstrap + `escapeHtml` | ✅ | machine |
| Responsive @375/768/1200px | ⚠️ | **manual sign-off required** |
