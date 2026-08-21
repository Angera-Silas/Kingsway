# fee_structure/admin_fee_structure.php

- **File**: `pages/fee_structure/admin_fee_structure.php`
- **Controller**: `fee_structure_admin.js`
- **Roles**: —
- **Sidebar item(s)**: —

## Init / auth

- `AuthContext.ready()` awaited: `Y`
- Permission/RBAC guard: `N`
- Raw `fetch`/`XMLHttpRequest`/`axios`: `0`

## API methods used

| API method | Payload keys (sent) | Flags | Endpoint | Handler | Status |
|---|---|---|---|---|---|
| `academic.getAllAcademicYears` | — | — | GET /academic/years/list | Academic::getAllAcademicYears | ok |
| `academic.listLevels` | — | — | GET /academic/levels-list | Academic::listLevels | ok |
| `finance.listStudentTypes` | — | — | GET /finance/student-types-list | Finance::listStudentTypes | ok |
| `finance.listFeeTypes` | — | — | GET /finance/fee-types-list | Finance::listFeeTypes | ok |
| `academic.listTerms` | — | — | GET /academic/terms-list | Academic::listTerms | ok |
| `academic.listClasses` | `limit` | — | GET /academic/classes-list | Academic::listClasses | ok |
| `finance.createFeeStructureBundle` | — | — | POST /finance/fees-create-bundle | Finance::createFeeStructureBundle | ok |
| `finance.reviewStructure` | `academic_year`, `level_id`, `student_type_id`, `reviewed_by`, `notes` | — | POST /finance/fees-review-structure | Finance::reviewStructure | ok |
| `finance.approveStructure` | `academic_year`, `level_id`, `student_type_id`, `approved_by`, `notes` | — | POST /finance/fees-approve-structure | Finance::approveStructure | ok |
| `finance.activateStructure` | `academic_year`, `level_id`, `student_type_id` | — | POST /finance/fees-activate-structure | Finance::activateStructure | ok |
| `finance.deleteAnnualStructure` | `academic_year`, `level_id`, `student_type_id`, `term_id` | — | POST /finance/fees-delete-annual-structure | Finance::deleteAnnualStructure | ok |
| `finance.rolloverStructure` | `source_year`, `target_year`, `executed_by` | — | POST /finance/fees-rollover-structure | Finance::rolloverStructure | ok |

## Direct `callAPI` endpoints

| Endpoint | Payload keys (sent) | Flags |
|---|---|---|
| `GET /finance/fee-structures/list` | — | — |

## Backend params (expected)

| Handler | Input keys ($data/$_POST/$_GET) | Path id | Data sources (views/procs/tables) |
|---|---|---|---|
| Finance::getFeeStructuresList | — | — | — |

## Response shape (data keys consumed)

- `finance.createFeeStructureBundle -> message`
- `finance.createFeeStructureBundle -> total_rows_created`
- `finance.createFeeStructureBundle -> total_rows_archived`
- `finance.createFeeStructureBundle -> class_count`
- `direct.callAPI -> Please`
- `direct.callAPI -> forEach`
- `direct.callAPI -> length`
- `direct.callAPI -> filter`
- `direct.callAPI -> reduce`
- `direct.callAPI -> page`
- `direct.callAPI -> pages`
- `direct.callAPI -> total`
- `direct.callAPI -> limit`

## UI / security

- `innerHTML`/`insertAdjacentHTML` assignments: `21` (with interpolation: `1`)
- `escapeHtml()` calls: `10` — XSS check: `PASS`
- Bootstrap modal usage: `2`
- Payload/backend param match: `OK`
- Fix flags: none
- Info flags: `TEMPLATE_FRAGMENT`
- Fix task: `—`

## End-to-end trace

> collect → store → analyse → display: page payload keys → API endpoint → controller method → service passthrough → SQL views/procs/tables (rows above). This is machine-derived; the interactive flow (form submit → render) needs human sign-off.

## Review checklist

| Check | Status | Notes |
|---|---|---|
| Init: `AuthContext.ready()` | ✅ | machine |
| Data load: `window.API.*`, no raw fetch | ✅ | machine |
| Params: sent ≈ backend `$data` | ⚠️ | heuristic |
| Response: unwrapped `data` handled | ✅ | heuristic |
| UI: Bootstrap + `escapeHtml` | ✅ | machine |
| Responsive @375/768/1200px | ⚠️ | **manual sign-off required** |
