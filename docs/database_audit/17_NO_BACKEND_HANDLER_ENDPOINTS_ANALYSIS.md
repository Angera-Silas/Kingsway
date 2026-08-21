# 17. The 42 No-Backend-Handler Endpoints — Usage Analysis & Decision Input

> Purpose: answer, for every endpoint that has **no backend handler** under the active router
> convention — what it's for, which page uses it, which roles can reach it, and whether an
> equivalent endpoint already exists — so we can decide **implement vs remove** per feature.
>
> Companion: `docs/database_audit/15_FRONTEND_API_CONTRACT_AUDIT.md` (full coverage diff).

---

## Headline

Of the **42 endpoints** with no backend handler:

| Verdict | Endpoints | Feature / page | Roles today |
|---|---|---|---|
| **A. LIVE — implement backend** | 20 | Sports (teams/fixtures/standings) — `sports.php` | no sidebar role (via talent/activity dashboards) |
| **B. LIVE — implement backend** | 8 | Dashboard builder (dashboards + widgets) — `dashboard_registry.js` / `widget_registry.js` | **System Administrator** only |
| **C. LIVE — REDUNDANT, reuse existing** | 5 | Food store — `food_store.php` | no sidebar role (cateress dashboard) |
| **D. LIVE — implement small backend** | 3 | Chapel services — `chapel_services.php` | no sidebar role (chaplain dashboard) |
| **E. DEAD — remove from api.js** | 6 | Beds, menus, food-store consume, chapel update, my-lessons, student imports/stats | — |

Total = 20 + 8 + 5 + 3 + 6 = 42 (endpoint paths; some methods above share paths).

> **Role note:** none of the three boarding/sports pages (`sports`, `food_store`, `chapel_services`)
> is assigned to any role in `role_sidebar_menus` (only 130 sidebar-role assignments exist, mostly
> website management + System Administrator). Users reach them via dashboard links. That wiring
> must be confirmed during the matrix phase (Phase 2 of `16_FRONTEND_REVAMP_PLAN.md`).

---

## A. Sports — `activities/sports/*` (20 endpoint-paths) — LIVE, no equivalent

**Pages:** `pages/sports.php` → `js/pages/sports.js`
**api.js methods used:** `listTeams`, `getTeam`, `createTeam`, `listFixtures`, `createFixture`,
`getFixture`, `recordResult`, `getStandings`, `listTeamMembers`
**What the page renders:** teams grid; fixtures table (schedule); results table; standings table
(play/won/drawn/lost/points); team-members.
**Purpose:** inter-house/inter-school sports management (teams, fixtures, match results, league
standings).

**Unused methods on the same paths (dead api.js, still implement or trim):** `updateTeam`,
`deleteTeam`, `updateFixture`, `deleteFixture`, `standings` POST, plus standalone `head-to-head`,
`player-stats`, `top-scorers`, `season-summary`, `recent-results`, `dashboard-stats`,
`upcoming-fixtures`.

**Equivalent?** ❌ No. Backend `activities` controller has competition workflows
(`postCompetitionPrepareTeam`, `postCompetitionRecordParticipation`, `postCompetitionReportResults`,
`getUpcomingList`) but **no sports teams/fixtures/standings CRUD**.
**Verdict:** implement a sports backend module (new tables or reuse `activities` +
`activity_participants`/`activity_schedule` with a `sport` category); only the 9 used methods are
required — trim the rest.

---

## B. System dashboard builder — `system/dashboards` + `system/widgets` (8 endpoint-paths) — LIVE, no equivalent

**Pages:** `dashboard_registry.js`, `widget_registry.js` (injected by `DashboardController` /
`DashboardAPI` — dashboard-builder UI; not a static `.php` page)
**api.js methods used:** `getDashboards`, `createDashboard`, `updateDashboard`, `deleteDashboard`,
`getWidgets`, `createWidget`, `updateWidget`, `deleteWidget` (all 8).
**Roles:** sidebar items `sys_dashboard_registry` (1018) and `sys_widget_registry` (1019) are
assigned to **System Administrator** only.
**Purpose:** per-role dashboard composition (which widgets appear on which dashboard).
**Equivalent?** ❌ No. `system` has sidebar-menu CRUD (`get/post/putSidebarMenus`,
`role_sidebar_assignments`) and `dashboard::getDashboardRegistry()` *reads* a registry, but there is
no `dashboards`/`widgets` write endpoint anywhere.
**Verdict:** implement `dashboards` + `widgets` tables + controller methods (System Administrator
feature), or remove the builder UI. Check `DashboardRouter::getDashboardRegistry()` for the
expected data shape before building.

---

## C. Food store — `boarding/food-store` (5 endpoint-paths) — LIVE but REDUNDANT with inventory

**Pages:** `pages/food_store.php` → `js/pages/food_store.js`
**api.js methods used:** `getFoodStore`, `addFoodItem`, `updateFoodItem`
**What the page renders:** food-items inventory table — `name, category, quantity, unit,
reorder_level, unit_price, value, stock status` + add/edit/delete modal.
**Equivalent?** ✅ **Yes — the inventory module.** The rendered columns match `vw_inventory_health`
(`name, code, category, current_quantity, minimum_quantity, reorder_level, stock_status,
expiry_status, expiry_date, location, unit_cost, inventory_value`) — and inventory already exposes
`GET /inventory/items-with-stock`, item CRUD, low-stock alerts. Catering's `getFoodStock` also
reads `vw_inventory_health`.
**Verdict:** **re-point `food_store.js` to the inventory API** (`window.API.inventory.*`) and delete
the boarding food-store entries — do NOT build a duplicate backend. (Unused: `recordConsumption` → remove.)
**Confirm with user:** whether "food store" was meant to be a distinct kitchen pantry (then build on
`menu_items`/new table) or simply the food subset of inventory (recommended, matches current UI).

---

## D. Chapel services — `boarding/chapel-services` (3 endpoint-paths) — LIVE, no equivalent

**Pages:** `pages/chapel_services.php` → `js/pages/chapel_services.js`
**api.js methods used:** `getChapelServices`, `createChapelService` (`updateChapelService` unused)
**What the page renders:** services table (id/name/etc.) + create form.
**Equivalent?** ❌ Mostly no. `chapel::getServices` (`SystemAPI::listChapelServices`) returns
**chapel-typed events** from `school_events`/`calendar_events`/`events` — a different concept
(upcoming services list, not a CRUD). There is **no `chapel_services` table** live.
**Verdict:** two options — (1) point the page at `chapel` events for the list and drop the create
form, or (2) build a small `chapel_services` table + CRUD. **Recommend (2)** only if the school
maintains a chapel-service roster distinct from events; otherwise (1).

---

## E. Dead — no page uses them (remove from api.js, and any UI stubs)

Verified **0 references** across `js/pages/*` for every method below.

| Endpoint | api.js methods | Evidence / note |
|---|---|---|
| `/boarding/beds` (+ `?dormitory_id`, `/assign`, `/unassign/:id`) | `getBeds`, `assignBed`, `unassignBed` | no UI anywhere |
| `/boarding/menus` (+ `/:id` PUT/DELETE) | `getMenus`, `createMenu`, `updateMenu`, `deleteMenu` | no UI; **but `menu_items` table exists** (empty) and `meal_plans` join it — a boarding menus feature is a candidate future build, not needed now |
| `/boarding/food-store/consume` | `recordConsumption` | no UI |
| `/boarding/chapel-services/:id` PUT | `updateChapelService` | page only creates/lists |
| `/schedules/my-lessons` | `dashboard.getMyLessonPlan` | no UI; teacher lessons handled by `academic` |
| `/students/import-existing` | `students.importExisting` | no UI; import handled elsewhere? (verify) |
| `/students/import-template` | `students.getImportTemplate` | no UI |
| `/students/stats` | `dashboard.getStudentStats` | no UI; students stats via `/students/statistics-get` |
| `/activities/sports/head-to-head`, `player-stats`, `top-scorers`, `season-summary`, `recent-results`, `dashboard-stats`, `upcoming-fixtures` | (nested `sports.*`) | not used by `sports.js` — remove unless sports module plan adds them |

> **Caveat on `students` imports:** the student-import UI may live behind a *different* controller
> (`import` / `DataImporter`). Verify `/students/import-*` vs any `import` module endpoints before
> deleting — if an import page exists, re-point it instead.

---

## Recommended next action

1. **C** and **E** are safe to close now: re-point `food_store.js` → inventory; remove the dead
   `api.js` entries.
2. **A/B** are live features → Phase 1 of the revamp plan should *implement backend* (sports module,
   dashboards/widgets tables + methods), then wire api.js + pages.
3. **D** needs a product decision (events vs dedicated table).
4. Record each decision in `progress.md` as it's taken.
