# grading_scales.php

- **File**: `pages/grading_scales.php`
- **Controller**: `grading_scales.js`
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
| `GET /api/academic/grading-scale?all=1` | — | — |
| `GET /api/academic/grading-scale/` | — | — |
| `POST /api/academic/grading-scale` | — | — |
| `GET /api/academic/grade-rules/` | — | — |
| `POST /api/academic/grade-rules` | — | — |

## Backend params (expected)

| Handler | Input keys ($data/$_POST/$_GET) | Path id | Data sources (views/procs/tables) |
|---|---|---|---|
| Academic::getGradingScale | — | `id` | grade_rules, grading_scales |
| Academic::postGradingScale | `name`, `description`, `min_mark`, `max_mark`, `status` | — | grading_scales |
| Academic::postGradeRules | `scale_id`, `grade_code`, `grade_name`, `min_mark`, `max_mark`, `grade_points`, `performance_level`, `description`, `sort_order` | — | grade_rules, grading_scales |

## Response shape (data keys consumed)

_(no post-await `.prop` consumption detected)_

## UI / security

- `innerHTML`/`insertAdjacentHTML` assignments: `6` (with interpolation: `0`)
- `escapeHtml()` calls: `8` — XSS check: `PASS`
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
