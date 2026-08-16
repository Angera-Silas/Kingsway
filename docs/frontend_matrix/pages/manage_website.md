# manage_website.php

- **File**: `pages/manage_website.php`
- **Controller**: `manage_website.js`
- **Roles**: `System Administrator`, `Director`, `School Administrator`, `Headteacher`, `Deputy Head - Academic`, `Deputy Head - Discipline`, `Talent Development`
- **Sidebar item(s)**: `100039`, `100040`, `100041`, `100042`, `100043`, `100044`, `100045`, `100046`, `100047`, `100048`, `100049`, `100050`, `100051`, `100052`, `100053`, `100054`, `100055`, `100056`, `100057`, `100058`, `100059`, `100060`, `100061`, `100062`, `100063`, `100064`, `100065`

## Init / auth

- `AuthContext.ready()` awaited: `Y`
- Permission/RBAC guard: `Y`
- Raw `fetch`/`XMLHttpRequest`/`axios`: `0`

## API methods used

_(no `API.<module>.<method>` calls detected)_

## Backend params (expected)

_(no backend handler resolved for param extraction)_

## Response shape (data keys consumed)

_(no post-await `.prop` consumption detected)_

## UI / security

- `innerHTML`/`insertAdjacentHTML` assignments: `47` (with interpolation: `12`)
- `escapeHtml()` calls: `68` — XSS check: `PASS`
- Bootstrap modal usage: `11`
- Payload/backend param match: `NA`
- Fix flags: none
- Info flags: none
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
