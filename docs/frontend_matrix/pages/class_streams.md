# class_streams.php

- **File**: `pages/class_streams.php`
- **Controller**: `class_streams.js`
- **Roles**: —
- **Sidebar item(s)**: —

## Init / auth

- `AuthContext.ready()` awaited: `N`
- Permission/RBAC guard: `Y`
- Raw `fetch`/`XMLHttpRequest`/`axios`: `0`

## API methods used

| API method | Payload keys (sent) | Flags | Endpoint | Handler | Status |
|---|---|---|---|---|---|
| `academic.listClasses` | — | — | GET /academic/classes-list | Academic::listClasses | ok |
| `academic.listTeachers` | — | — | GET /academic/teachers-list | Academic::listTeachers | ok |
| `academic.listStreams` | — | — | GET /academic/streams-list | Academic::listStreams | ok |
| `academic.updateStream` | — | — | PUT /academic/streams/update/ | Academic::updateStream | ok |
| `academic.createStream` | — | — | POST /academic/streams/create | Academic::createStream | ok |
| `academic.deleteStream` | — | — | DELETE /academic/streams/delete/ | Academic::deleteStream | ok |

## Backend params (expected)

_(no backend handler resolved for param extraction)_

## Response shape (data keys consumed)

_(no post-await `.prop` consumption detected)_

## UI / security

- `innerHTML`/`insertAdjacentHTML` assignments: `5` (with interpolation: `0`)
- `escapeHtml()` calls: `8` — XSS check: `PASS`
- Bootstrap modal usage: `3`
- Payload/backend param match: `NA`
- Fix flags: none
- Info flags: `ESCAPED_LITERAL_HTML`
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
