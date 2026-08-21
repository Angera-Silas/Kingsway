# library/viewer_library.php

- **File**: `pages/library/viewer_library.php`
- **Controller**: `manage_library.js`
- **Roles**: —
- **Sidebar item(s)**: —

## Init / auth

- `AuthContext.ready()` awaited: `N`
- Permission/RBAC guard: `Y`
- Raw `fetch`/`XMLHttpRequest`/`axios`: `0`

## API methods used

_(no `API.<module>.<method>` calls detected)_

## Direct `callAPI` endpoints

| Endpoint | Payload keys (sent) | Flags |
|---|---|---|
| `GET /library/summary` | — | — |
| `GET /library/categories` | — | — |
| `POST /library/categories` | `description` | — |
| `GET /library/books?` | — | — |
| `GET /library/books/` | — | — |
| `POST /library/books` | — | — |
| `GET /library/issues?status=issued` | — | — |
| `GET /library/overdue` | — | — |
| `GET /library/books?available_only=1` | — | — |
| `POST /library/issues` | — | — |
| `GET /library/issues/` | — | — |
| `GET /library/fines` | — | — |
| `GET /library/fines/` | — | — |

## Backend params (expected)

| Handler | Input keys ($data/$_POST/$_GET) | Path id | Data sources (views/procs/tables) |
|---|---|---|---|
| Library::getSummary | — | — | library_books, library_categories, library_fines, library_issues |
| Library::getCategories | — | — | library_books, library_categories |
| Library::postCategories | `name`, `description` | — | library_categories |
| Library::getBooks | `search`, `category_id`, `status`, `available_only` | `id` | library_books, library_categories |
| Library::postBooks | `title`, `author`, `total_copies`, `isbn`, `publisher`, `edition`, `publication_year`, `category_id`, `location_shelf`, `description` | — | library_books |
| Library::getIssues | `status`, `borrower_type`, `overdue_only` | — | library_books, library_issues, persons, staff, students |
| Library::getOverdue | — | — | library_books, library_issues, persons, staff, students |
| Library::postIssues | `issued_by`, `book_id`, `borrower_type`, `borrower_id`, `due_date` | — | library_books, library_issues |
| Library::getFines | `status` | — | library_books, library_fines, library_issues, persons, staff, students |

## Response shape (data keys consumed)

- `direct.callAPI -> total_books`
- `direct.callAPI -> available_copies`
- `direct.callAPI -> currently_issued`
- `direct.callAPI -> overdue_items`
- `direct.callAPI -> categories`
- `direct.callAPI -> pending_fines_kes`
- `direct.callAPI -> id`
- `direct.callAPI -> title`
- `direct.callAPI -> isbn`
- `direct.callAPI -> author`
- `direct.callAPI -> publisher`
- `direct.callAPI -> edition`
- `direct.callAPI -> publication_year`
- `direct.callAPI -> category_id`

## UI / security

- `innerHTML`/`insertAdjacentHTML` assignments: `26` (with interpolation: `7`)
- `escapeHtml()` calls: `27` — XSS check: `PASS`
- Bootstrap modal usage: `7`
- Payload/backend param match: `NA`
- Fix flags: none
- Info flags: `TEMPLATE_FRAGMENT`
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
