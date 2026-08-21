# intern_assigned_classes.php

- **File**: `pages/intern_assigned_classes.php`
- **Controller**: `intern_assigned_classes.js`
- **Roles**: —
- **Sidebar item(s)**: —

## Init / auth

- `AuthContext.ready()` awaited: `N`
- Permission/RBAC guard: `N`
- Raw `fetch`/`XMLHttpRequest`/`axios`: `0`

## API methods used

| API method | Payload keys (sent) | Flags | Endpoint | Handler | Status |
|---|---|---|---|---|---|
| `academic.listYears` | — | — | GET /academic/years/list | Academic::listYears | ok |
| `academic.getInternClasses` | — | — | GET /academic/intern-classes | Academic::getInternClasses | ok |

## Backend params (expected)

| Handler | Input keys ($data/$_POST/$_GET) | Path id | Data sources (views/procs/tables) |
|---|---|---|---|
| Academic::getInternClasses | — | — | academic_year_class_learning_area_teachers, academic_year_class_learning_areas, academic_year_class_streams, academic_year_classes, academic_years, classes, learning_areas, persons, staff, streams |

## Response shape (data keys consumed)

- `academic.listYears -> map`

## UI / security

- `innerHTML`/`insertAdjacentHTML` assignments: `4` (with interpolation: `1`)
- `escapeHtml()` calls: `5` — XSS check: `PASS`
- Bootstrap modal usage: `0`
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
