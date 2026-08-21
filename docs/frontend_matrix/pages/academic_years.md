# academic_years.php

- **File**: `pages/academic_years.php`
- **Controller**: `academic_years.js`
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
| `academic.updateYear` | — | — | PUT /academic/years/update/ | Academic::updateYear | ok |
| `academic.createYear` | — | — | POST /academic/years/create | Academic::createYear | ok |
| `academic.getYear` | — | — | GET /academic/years/get/ | Academic::getYear | ok |
| `academic.setCurrentYear` | — | — | PUT /academic/years/set-current/ | Academic::setCurrentYear | ok |
| `academic.deleteYear` | — | — | DELETE /academic/years/delete/ | Academic::deleteYear | ok |
| `academic.listTerms` | `academic_year_id` | — | GET /academic/terms-list | Academic::listTerms | ok |
| `academic.createTerm` | — | — | POST /academic/terms/create | Academic::createTerm | ok |

## Backend params (expected)

_(no backend handler resolved for param extraction)_

## Response shape (data keys consumed)

- `academic.listTerms -> length`
- `academic.listTerms -> forEach`

## UI / security

- `innerHTML`/`insertAdjacentHTML` assignments: `8` (with interpolation: `1`)
- `escapeHtml()` calls: `6` — XSS check: `PASS`
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
