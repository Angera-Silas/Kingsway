# manage_email.php

- **File**: `pages/manage_email.php`
- **Controller**: `manage_email.js`
- **Roles**: —
- **Sidebar item(s)**: —

## Init / auth

- `AuthContext.ready()` awaited: `N`
- Permission/RBAC guard: `N`
- Raw `fetch`/`XMLHttpRequest`/`axios`: `0`

## API methods used

| API method | Payload keys (sent) | Flags | Endpoint | Handler | Status |
|---|---|---|---|---|---|
| `communications.getMessages` | `type` | — | GET /communications/communication | Communications::getMessages | ok |
| `communications.getContact` | — | — | GET /communications/contact/<br>GET /communications/contact | Communications::getContact<br>Communications::getContact | ok<br>ok |
| `communications.getGroup` | — | — | GET /communications/group/<br>GET /communications/group | Communications::getGroup<br>Communications::getGroup | ok<br>ok |
| `communications.getTemplates` | — | — | GET /communications/template | Communications::getTemplates | ok |
| `communications.createCommunication` | — | — | POST /communications/communication | Communications::createCommunication | ok |
| `communications.createAttachment` | — | — | POST /communications/attachment | Communications::createAttachment | ok |
| `communications.resendCommunication` | — | — | POST /communications/resend | Communications::resendCommunication | ok |

## Backend params (expected)

| Handler | Input keys ($data/$_POST/$_GET) | Path id | Data sources (views/procs/tables) |
|---|---|---|---|
| Communications::getContact | — | `id` | — |
| Communications::getGroup | — | `id` | — |

## Response shape (data keys consumed)

- `communications.resendCommunication -> message`

## UI / security

- `innerHTML`/`insertAdjacentHTML` assignments: `9` (with interpolation: `0`)
- `escapeHtml()` calls: `5` — XSS check: `PASS`
- Bootstrap modal usage: `1`
- Payload/backend param match: `OK`
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
