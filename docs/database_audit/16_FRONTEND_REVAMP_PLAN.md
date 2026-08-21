# 16. Frontend Revamp Plan — Backend-First Blueprint

> Guiding principle: **the backend is the blueprint.** Every frontend page must call a real,
> convention-resolved endpoint, submit parameters that match the controller signature, and
> render the response exactly as the backend returns it. Nothing is guessed.

This plan supersedes ad-hoc frontend fixes. All findings, decisions, and progress are recorded
in this directory (`docs/database_audit/15_FRONTEND_API_CONTRACT_AUDIT.md`, `progress.md`).

---

## 0. Context (from the completed audit)

| Measure | Value |
|---|---|
| API modules in `js/api.js` | 29 |
| `apiCall()` references | 1,217 |
| Unique (verb,path) pairs | 1,152 |
| …resolve to a real backend handler | 1,042 |
| …**do NOT resolve (404 at runtime)** | **110** |
| Backend handler-shaped methods | 1,642 |
| Backend handlers with NO `api.js` reference | 877 |
| Duplicate (verb,path) refs inside api.js | 53 |
| Pages with JS controllers | 291 (`js/pages/*.js`) |
| Backend controllers | 49 (`api/controllers/*Controller.php`) |
| `sidebar_menu_items` / `roles` / `routes_registry` (DB) | 657 / 20 / 373 |

The 110 dead paths split as: **61 naming mismatches** (handler exists under a different resource
name), **42 no-backend-handler** (feature was never built server-side), **6 wrong-verb**, **1 missing
controller** (`/assessments/*`). The 877 uncalled handlers must be triaged — many are legitimate
non-browser endpoints (webhooks, print/download, M-Pesa callbacks) and belong on an exclusion list.

Routing convention (authoritative, `api/router/ControllerRouter.php`):
`VERB /api/{controller}/{resource}/{id}` → method `verb+ResourceCamel`, e.g.
`GET /api/transport/transport-route/5` → `getTransportRoute($id)`. Resource names must be
`kebab-case` of the camel resource in the method name — this is the single largest source of
frontend 404s today.

---

## Phase 1 — Normalize `js/api.js` (the contract layer)

Goal: one method per backend capability, every method resolving, zero ambiguity.

1. **Repair the 61 naming mismatches.** Change the api.js paths to the canonical resource names,
   OR add convention-correct aliases in the backend for deliberate front-facing names. Prefer
   fixing **api.js** (backend names are the blueprint; do not rename backend methods).
   - e.g. `GET /transport/route` → `GET /transport/transport-route`; `/system/policies` → `/system/permission-policies`.
2. **Decide each of the 42 no-backend-handler paths**:
   - Feature genuinely needed → implement the backend controller method (backend-first).
   - Dead UI → remove the api.js method and the calling page section.
   Record each decision in `progress.md`.
3. **Deduplicate the 53 repeated (verb,path) refs** into single module methods.
4. **Add missing high-value backend handlers** to `window.API` (the 877 need triage; build the
   exclusion list for webhooks/download/print/callbacks, then expose the rest).
5. **Fix the response-contract bug** (from `docs/DATA_CONTRACT_AUDIT.md`): `handleApiResponse()`
   unwraps to raw `data`; page controllers must be trained to read the unwrapped payload, not `res.success`.
6. **Standardize module layout** in api.js: `auth`, `users`, `students`, `academic`, … one object
   per backend controller prefix, alphabetically arranged, CRUD verbs consistent.

**Exit criteria:** every `apiCall()` path resolves under the router convention (0 unresolved);
no duplicate (verb,path); response shapes documented per module.

---

## Phase 2 — Role → Sidebar → Route → Page → Controller → Endpoint Matrix

One matrix per role (20 roles), derived from the DB, cross-joined with page/controller code:

```
role ──(role_sidebar_menus)──> sidebar_menu_items ──(url)──> page (.php)
  │                                    │
  └──(role_routes / routes_registry)──┘
                                             page ──(js/pages/<page>.js controller)──> window.API.<module>.<method> ──> apiCall(path)
                                                                                                  │
                                                                                                  └──> backend controller method ──> SQL/views/procs
```

Data sources:
- `roles` (20), `role_sidebar_menus`, `sidebar_menu_items` (657), `routes_registry` (373), `role_routes`.
- `pages/*.php` → the `<script src="js/pages/<name>.js">` include maps a page to its controller.
- `js/pages/*.js` → grep `window.API.` / `API.` calls; each call maps to an `apiCall(path)` in api.js.
- api.js → `apiCall(path)` → backend controller method (router convention).

Deliverable: `docs/frontend_matrix/<role>.md` per role, plus a summary CSV.
Automation: a generator script under `scripts/` (repo-owned, deterministic).

**Exit criteria:** every sidebar item resolves to a page; every page has a controller; every
controller API call resolves to a backend handler; every role can trace every visible item.

### Status: DONE (2026-08-10)

Deliverables (regenerate with `python3 scripts/frontend_matrix/generate_role_matrix.py`):
- `docs/frontend_matrix/index.md` — methodology, headline counts, role summary, shared endpoints,
  unresolved endpoints, data findings
- `docs/frontend_matrix/summary.csv` — 138 rows (role → sidebar item → page/controller/route)
- `docs/frontend_matrix/endpoints.csv` — 493 rows (role → endpoint → resolved handler)
- `docs/frontend_matrix/roles/<slug>.md` — 20 per-role matrices (dashboard, sidebar tree with
  status flags, endpoint→handler chain, authorized routes)

Exit-criteria verification: 0 sidebar items without a page; 0 pages without a controller;
198/202 distinct endpoints resolve to a backend handler. Remaining unresolved are deliberate
exceptions, tracked in index.md "Unresolved endpoints":
- `GET|DELETE /website/` — `js/pages/manage_website.js` legacy `this.API('VERB','path')` arg-order
  is incompatible with `window.callAPI = apiCall(endpoint, method)`; concatenated paths → dynamic
- `GET /` — legacy root dispatch, `js/dashboards/school_administrative_officer_dashboard.js` L461
- `GET /dashboard/system-admin/api-load` — **genuine naming bug**: api.js should call
  `/dashboard/system-admin-api-load` to reach `DashboardController::getSystemAdminAPILoad`

---

## Phase 3 — Per-Page Controller Review (291 pages)

For **each** page, review and record:
1. **Initialization** — does `init()` await `AuthContext.ready()` and guard by permission?
2. **Data load** — `loadData()` calls `window.API.*` (never raw `fetch`), loading states shown.
3. **Parameters** — submitted payload keys match the backend method's expected fields
   (verified against the controller's `$data` usage — not guessed).
4. **Response extraction** — correct handling of the unwrapped `data` payload; arrays vs objects;
   `null`/empty states handled; no `res.success` checks on unwrapped data.
5. **UI structure** — Bootstrap 5 grid, modals via `bootstrap.Modal.getInstance(...).hide()`,
   `escapeHtml()` on all interpolations, tables have loading/empty/error states.
6. **Responsiveness** — tables/cards usable at 375/768/1200 px; no horizontal overflow of controls.

Record in `docs/frontend_matrix/pages/<page>.md` using the template below.

### Page review template
```md
# <Page>.php
- Controller: js/pages/<page>.js
- Sidebar item(s): <ids/titles>
- Roles: <role list>
- API methods used: <module.method → path>
- Endpoint params (sent): <payload keys>
- Backend handler: <controller::method>
- Backend params (expected): <$data keys / $id>
- Response shape: <data keys consumed>
- Init: AuthContext.ready() ✅/❌
- Data extraction: correct ✅/❌ (notes)
- Bootstrap UI: ✅/❌ (notes)
- Responsive: ✅/❌ (notes)
- End-to-end trace: <collect → store → analyse → display>
```

**Exit criteria:** 291/291 pages reviewed; status flags in a tracked sheet; each `❌` linked to a fix task.

### Status: DONE (machine baseline, 2026-08-10)

Automation: `python3 scripts/frontend_matrix/generate_page_review.py` (imports the Phase 2
parser; reuses its `ControllerRouter` resolver and `$data`/service-passthrough extraction).

Deliverables:
- `docs/frontend_matrix/pages/<route>.md` — 359 per-page reviews (plan template, machine-derived;
  "Responsive" and interactive-flow fields left for manual sign-off)
- `docs/frontend_matrix/page_review_status.csv` — 359 rows, one per page, with per-check columns
- `docs/frontend_matrix/page_review_index.md` — headline counts, flag distribution, fix-task list

Machine checks per page: roles/sidebar items, API calls (`API.<mod>.<meth>` AND bare
`callAPI(path, verb)`), endpoint→handler resolution, payload keys sent vs backend `$data`/
`$_POST`/`$_GET` keys (following `$this->x->svc()` passthroughs into service classes),
response keys consumed (post-await `.prop`, unwrapped `data`), `AuthContext.ready()`,
permission guard, raw `fetch`, interpolated `innerHTML` vs `escapeHtml()` (XSS), Bootstrap modals.

Findings (196 fix tasks, `FIX-3-*`):
- **86** pages with no `js/pages` controller (legacy/inline scripts/stubs) — incl. a real bug:
  `pages/fees/manager_fees.php` includes `js/pages/studentFees.js` which **does not exist**
- **63** pages with interpolated `innerHTML` and zero `escapeHtml()` (XSS backlog)
- **55** pages with ≥1 endpoint that does not resolve under the router convention — incl. new
  genuine mismatches in `js/pages/term_transition.js`: `GET /attendance/summary` (no
  `AttendanceController::getSummary`; has `getAcademicSummary`) and `GET /academic/results-list`
  (no `AcademicController::getResultsList`)
- **23** pages where sent payload keys ≠ backend-expected keys (e.g. `adjustments.js` posts
  `adjustment_type/status` but `Finance::postAdjustments` reads `type/reason`)
- Informational (not fix-triggering): 289 pages without an in-file auth guard (guarded by
  middleware/shell), 89 render-only pages

---

## Phase 4 — End-to-End Traces

For every data element shown on the UI, the chain must be documented:
**Collection** (form/upload/import/C2B) → **Storage** (table + columns, normalized) →
**Analysis** (view/proc/aggregation that powers it) → **Presentation** (controller → api.js → JS render).

Deliverable: `docs/end_to_end/` — one trace per major module (students, fees, payroll, transport,
boarding, attendance, academics, reports, portal). Traces reference the 3NF table names, the views
(vw_*) and procs that serve them, and the exact api.js method + page section that displays them.

**Exit criteria:** every dashboard KPI and every primary table on every role's dashboard has a trace.

---

## Phase 5 — UI/UX Responsive Pass (post-contract-fix)

Only after Phases 1–4 (contract correctness first). Replace Bootstrap-flavored ad-hoc markup with
the shared `css/app-common.css` tokens, standard table/card/modal components, and a responsive
breakpoint check per page. Update `js/api.js` notification/toast standards.

---

## Sequencing & Ownership

| Phase | Depends on | Est. effort |
|---|---|---|
| P1 api.js normalization | — | 1 session |
| P2 matrix generator + role matrices | P1 | 2–3 sessions |
| P3 per-page review (291) | P2 (matrix tells us what each page calls) | long-running, batch by module |
| P4 end-to-end traces | P3 | long-running, batch by module |
| P5 responsive pass | P3 | long-running |

Batch P3/P4 **by module** (students, finance, transport, boarding, attendance, academics,
communications, system) so each module is end-to-end complete before moving on.

## Rules (locked)
1. Backend = source of truth; frontend adapts to backend names/shapes.
2. No raw `fetch()` in pages; everything through `window.API` / `callAPI()`.
3. No new backend endpoint without a matching `api.js` method and a matrix trace.
4. Every frontend change is recorded in `progress.md` with its trace link.
5. `escapeHtml()` on all `innerHTML`; no secrets in frontend code; RBAC via `hasPermission()`.
