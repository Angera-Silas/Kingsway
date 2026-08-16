# import_existing_staff.php

- **File**: `pages/import_existing_staff.php`
- **Controller**: `import_existing_staff.js`
- **Roles**: —
- **Sidebar item(s)**: —

## Init / auth

- `AuthContext.ready()` awaited: `Y`
- Permission/RBAC guard: `N`
- Raw `fetch`/`XMLHttpRequest`/`axios`: `0`

## API methods used

| API method | Payload keys (sent) | Flags | Endpoint | Handler | Status |
|---|---|---|---|---|---|
| `staffMigration.referenceData` | — | — | GET /staff-migration/reference-data | StaffMigration::referenceData | ok |
| `staffMigration.batches` | — | — | GET /staff-migration/batches | StaffMigration::batches | ok |
| `staffMigration.downloadTemplateXlsx` | — | — | GET /staff-migration/template-xlsx | StaffMigration::downloadTemplateXlsx | ok |
| `staffMigration.downloadTemplate` | — | — | GET /staff-migration/template | StaffMigration::downloadTemplate | ok |
| `staffMigration.stage` | — | — | POST /staff-migration/stage | StaffMigration::stage | ok |
| `staffMigration.commit` | — | — | POST /staff-migration/commit | StaffMigration::commit | ok |
| `staffMigration.rollback` | — | — | POST /staff-migration/rollback | StaffMigration::rollback | ok |
| `staffMigration.batch` | — | — | GET /staff-migration/batch/ | StaffMigration::batch | ok |

## Backend params (expected)

_(no backend handler resolved for param extraction)_

## Response shape (data keys consumed)

_(no post-await `.prop` consumption detected)_

## UI / security

- `innerHTML`/`insertAdjacentHTML` assignments: `5` (with interpolation: `0`)
- `escapeHtml()` calls: `16` — XSS check: `PASS`
- Bootstrap modal usage: `0`
- Payload/backend param match: `NA`
- Fix flags: none
- Info flags: `ESCAPED_LITERAL_HTML`
- Fix task: `—`

## End-to-end trace

> collect → store → analyse → display: page payload keys → API endpoint → controller method → service passthrough → SQL views/procs/tables (rows above). This is machine-derived; the interactive flow (form submit → render) needs human sign-off.

## Review checklist

| Check | Status | Notes |
|---|---|---|
| Init: `AuthContext.ready()` | ✅ | machine |
| Data load: `window.API.*`, no raw fetch | ✅ | machine |
| Params: sent ≈ backend `$data` | ⚠️ | heuristic |
| Response: unwrapped `data` handled | ⚠️ | heuristic |
| UI: Bootstrap + `escapeHtml` | ✅ | machine |
| Responsive @375/768/1200px | ⚠️ | **manual sign-off required** |
