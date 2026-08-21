# manage_communications.php

- **File**: `pages/manage_communications.php`
- **Controller**: `communications.js`
- **Roles**: —
- **Sidebar item(s)**: —

## Init / auth

- `AuthContext.ready()` awaited: `Y`
- Permission/RBAC guard: `N`
- Raw `fetch`/`XMLHttpRequest`/`axios`: `0`

## API methods used

| API method | Payload keys (sent) | Flags | Endpoint | Handler | Status |
|---|---|---|---|---|---|
| `communications.getCommunication` | — | — | GET /communications/communication/<br>GET /communications/communication | Communications::getCommunication<br>Communications::getCommunication | ok<br>ok |
| `communications.deleteCommunication` | — | — | DELETE /communications/communication/ | Communications::deleteCommunication | ok |
| `communications.getAnnouncement` | — | — | GET /communications/announcement/<br>GET /communications/announcement | Communications::getAnnouncement<br>Communications::getAnnouncement | ok<br>ok |
| `communications.createAnnouncement` | — | — | POST /communications/announcement | Communications::createAnnouncement | ok |
| `communications.deleteAnnouncement` | — | — | DELETE /communications/announcement/ | Communications::deleteAnnouncement | ok |
| `communications.getContact` | — | — | GET /communications/contact/<br>GET /communications/contact | Communications::getContact<br>Communications::getContact | ok<br>ok |
| `communications.createContact` | — | — | POST /communications/contact | Communications::createContact | ok |
| `communications.createGroup` | — | — | POST /communications/group | Communications::createGroup | ok |
| `communications.createParentMessage` | — | — | POST /communications/parent-message | Communications::createParentMessage | ok |
| `communications.createStaffForumTopic` | — | — | POST /communications/staff-forum-topic | Communications::createStaffForumTopic | ok |
| `communications.createStaffRequest` | — | — | POST /communications/staff-request | Communications::createStaffRequest | ok |
| `communications.createCommunication` | `type` | — | POST /communications/communication | Communications::createCommunication | ok |
| `communications.getLog` | — | — | GET /communications/log/<br>GET /communications/log | Communications::getLog<br>Communications::getLog | ok<br>ok |

## Backend params (expected)

| Handler | Input keys ($data/$_POST/$_GET) | Path id | Data sources (views/procs/tables) |
|---|---|---|---|
| Communications::getCommunication | — | `id` | — |
| Communications::deleteCommunication | — | `id` | — |
| Communications::getAnnouncement | — | `id` | — |
| Communications::deleteAnnouncement | — | `id` | — |
| Communications::getContact | — | `id` | — |
| Communications::getLog | — | `id` | — |

## Response shape (data keys consumed)

- `communications.getCommunication -> from_name`
- `communications.getCommunication -> to_name`
- `communications.getCommunication -> subject`
- `communications.getCommunication -> message`
- `communications.getCommunication -> created_at`
- `communications.getCommunication -> status`
- `communications.getAnnouncement -> title`
- `communications.getAnnouncement -> content`
- `communications.getAnnouncement -> target_audience`
- `communications.getAnnouncement -> created_at`

## UI / security

- `innerHTML`/`insertAdjacentHTML` assignments: `3` (with interpolation: `0`)
- `escapeHtml()` calls: `0` — XSS check: `PASS`
- Bootstrap modal usage: `1`
- Payload/backend param match: `OK`
- Fix flags: none
- Info flags: `TEMPLATE_FRAGMENT`, `ESCAPED_LITERAL_HTML`
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
