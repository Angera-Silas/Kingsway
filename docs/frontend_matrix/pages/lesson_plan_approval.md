# lesson_plan_approval.php

- **File**: `pages/lesson_plan_approval.php`
- **Controller**: `lesson_plan_approval.js`
- **Roles**: —
- **Sidebar item(s)**: —

## Init / auth

- `AuthContext.ready()` awaited: `N`
- Permission/RBAC guard: `N`
- Raw `fetch`/`XMLHttpRequest`/`axios`: `0`

## API methods used

| API method | Payload keys (sent) | Flags | Endpoint | Handler | Status |
|---|---|---|---|---|---|
| `academic.listLessonPlansApproval` | — | — | GET /academic/lesson-plans-approval | Academic::listLessonPlansApproval | ok |
| `academic.getLessonPlan` | — | — | GET /academic/lesson-plans-get/<br>GET /academic/lesson-plans-get | Academic::getLessonPlan<br>Academic::getLessonPlan | ok<br>ok |
| `academic.reviewLessonPlan` | — | — | PUT /academic/lesson-plans-review/ | Academic::reviewLessonPlan | ok |
| `academic.bulkApproveLessonPlans` | — | — | PUT /academic/lesson-plans-bulk-approve | Academic::bulkApproveLessonPlans | ok |

## Backend params (expected)

_(no backend handler resolved for param extraction)_

## Response shape (data keys consumed)

- `academic.listLessonPlansApproval -> lesson_plans`
- `academic.listLessonPlansApproval -> pagination`
- `academic.listLessonPlansApproval -> total`
- `academic.listLessonPlansApproval -> filter`
- `academic.listLessonPlansApproval -> length`
- `academic.getLessonPlan -> title`
- `academic.getLessonPlan -> status`
- `academic.getLessonPlan -> teacher_name`
- `academic.getLessonPlan -> subject_name`

## UI / security

- `innerHTML`/`insertAdjacentHTML` assignments: `4` (with interpolation: `0`)
- `escapeHtml()` calls: `0` — XSS check: `PASS`
- Bootstrap modal usage: `2`
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
