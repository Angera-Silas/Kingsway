# Kingsway School Management System — Comprehensive Code Review

> **Date:** 2026-07-29  
> **Reviewer:** Senior Software Engineer  
> **Scope:** Architecture, Security, Frontend, Backend, Database, State Management, Module Completeness, Production Readiness

---

## Table of Contents

1. [Overall Architecture](#1-overall-architecture)
2. [Module Completeness Analysis](#2-module-completeness-analysis)
3. [Backend Review](#3-backend-review)
4. [Frontend Review](#4-frontend-review)
5. [State Management & Session Handling Review](#5-state-management--session-handling-review)
6. [Database Layer Review](#6-database-layer-review)
7. [Security Review](#7-security-review)
8. [Production Readiness](#8-production-readiness)
9. [Summary of Issues by Priority](#9-summary-of-issues-by-priority)
10. [Remediation Roadmap](#10-remediation-roadmap)

---

## 1. Overall Architecture

### 1.1 Strengths

The system follows a well-layered, RESTful API architecture:

```
index.php (Front Controller)
  └── Router.php (Middleware Pipeline — 8 stages)
        └── ControllerRouter.php (Convention-based URI routing)
              └── *Controller.php (REST handlers, RBAC enforcement)
                    └── *API.php / *Manager.php / *Service.php (Business logic)
```

- **PSR-4 autoloading** via Composer with 13 namespace-to-directory mappings
- **49 controllers**, **10 middleware**, **45+ services**, **24 module directories**, ~250+ PHP files in `api/`
- **Single front controller** (`api/index.php`) with global error/exception handlers
- **Middleware pipeline** with 8 sequential stages: CORS → IP ACL → Rate Limit → Auth → CSRF → RBAC → Route Auth → Device
- **Convention-over-configuration** routing: `/{controller}/{resource}/{id}` → `{Controller::{httpMethod}{CamelResource}($id, $data, $segments)}`
- **Two base classes**: `FileLifecycleBase` → `BaseController` (controllers) and `BaseAPI` (module APIs)

### 1.2 Architectural Issues

| # | Issue | Severity | Location |
|---|-------|----------|----------|
| A1 | **Two competing response format systems** — `BaseController` returns arrays with `status/data/message/code/timestamp/request_id` while Module API helpers use `status/message/type/code/data`. Reconciled by `ApiResponse::normalize()` but creates ambiguity. | Medium | `api/controllers/BaseController.php` vs `api/includes/helpers.php` |
| A2 | **`system_config_router.php` bypasses the main Router pipeline** — standalone procedural script with its own auth checking and no middleware. Creates a parallel, less secure request-handling path. | High | `api/router/system_config_router.php` |
| A3 | **Unused `EnhancedRBACMiddleware`** — not in the middleware pipeline. Must be called manually by controllers. Workflow-stage permission features are likely not being used consistently. | Low | `api/middleware/EnhancedRBACMiddleware.php` |
| A4 | **Duplicate module directories** — Both `api/modules/finance/` and `api/modules/Finance/` exist (different casing). On case-sensitive Linux filesystems, `TransportBillingManager.php` could conflict or fail to autoload. | Medium | `api/modules/finance/` vs `api/modules/Finance/` |
| A5 | **God controllers** — `StudentsController.php` (~5,369 lines), `AcademicController.php` (~4,413 lines), `AttendanceController.php` (~2,947 lines), `FinanceController.php` (~1,867 lines). These should be split into focused handlers. | Medium | `api/controllers/StudentsController.php`, `AcademicController.php`, etc. |
| A6 | **PSR-4 case-sensitivity risk** — Module subdirectories use lowercase (`finance/`, `students/`) while Composer maps `App\API\Modules\` to `api/modules/`. On case-sensitive filesystems, `use App\API\Modules\Finance\FinanceAPI` would fail to find `api/modules/Finance/FinanceAPI.php` (needs `api/modules/finance/FinanceAPI.php`). | Medium | `api/modules/*/` |
| A7 | **`Router.php` catch block leaks exceptions** — Line 77 returns `$e->getMessage()` to the client. While `api/index.php` has a generic handler, the Router's try-catch fires first. | Critical | `api/router/Router.php:67-81` |
| A8 | **Dead code** — `ControllerRouter.php::ensureLogDir()` and `debugLog()` are conditionally compiled behind `defined('DEBUG')` and never used. | Low | `api/router/ControllerRouter.php:278-296` |
| A9 | **`auth_check.php` is legacy** — uses `session_start()` and `$_SESSION['user']` which is incompatible with the JWT-based stateless API architecture. | Low | `api/includes/auth_check.php` |
| A10 | **Some `includes/` files bypass autoloading** — `DashboardManager.php`, `RoutePermissionsStore.php` use `require_once` instead of Composer autoloading. | Low | `api/includes/DashboardManager.php`, `RoutePermissionsStore.php` |

---

## 2. Module Completeness Analysis

### 2.1 Modules Present (Covered)

| # | Module | Pages | Controller | DB Tables | Assessment |
|---|--------|-------|------------|-----------|------------|
| 1 | Students Management | 25+ | StudentsController (5369L) | 30+ tables | ✅ Fully covered |
| 2 | Academics (Classes, Subjects, Curriculum) | 30+ | AcademicController (4413L) | 40+ tables | ✅ Fully covered |
| 3 | Assessments & Examinations | 15+ | AcademicController | 10+ tables | ✅ Fully covered |
| 4 | Timetable/Scheduling | 5+ | SchedulesController | 8+ tables | ✅ Fully covered |
| 5 | Attendance | 15+ | AttendanceController (2947L) | 8+ tables | ✅ Fully covered |
| 6 | Finance (Fees, Payments, Budgets, Payroll) | 25+ | FinanceController (1867L) | 40+ tables | ✅ Fully covered |
| 7 | Admissions | 15+ | AdmissionController (2322L) | 8+ tables | ✅ Fully covered |
| 8 | HR/Staff Management | 20+ | StaffController (1964L) | 50+ tables | ✅ Fully covered |
| 9 | Transport | 5+ | TransportController | 16 tables | ✅ Fully covered |
| 10 | Inventory | 8+ | InventoryController (1839L) | 15+ tables | ✅ Fully covered |
| 11 | Library | 3+ | LibraryController | 5 tables | ✅ Fully covered |
| 12 | Health | 4+ | HealthController (349L) | 4 tables | ✅ Good |
| 13 | Sports/Co-curricular | 4+ | `ActivitiesController` (shared) | 10+ tables | ⚠️ Partial — uses generic Activities module via `api/modules/activities/`. Has frontend-only `js/pages/sports.js` adapter but **no dedicated backend manager/service** (`SportsManager`, `SportsService`) and **no sports-specific DB tables** (teams, fixtures, player stats, results). All sports data stored as generic activities + JSON blobs in workflows. |
| 14 | Discipline | 10+ | StudentsController + CounselingController | 8+ tables | ✅ Fully covered |
| 15 | Counseling | 5+ | CounselingController (224L) | 5+ tables | ✅ Fully covered |
| 16 | Boarding | 5+ | BoardingController (414L) | 5+ tables | ✅ Fully covered |
| 17 | Catering | 5+ | CateringController (80L) | 8+ tables | ⚠️ Partial — thin controller |
| 18 | Communications | 10+ | CommunicationsController (718L) | 15+ tables | ✅ Fully covered |
| 19 | Parent Portal | parent_portal.php | ParentPortalController (491L) | 6+ tables | ✅ Fully covered |
| 20 | Reports & Analytics | 20+ | ReportsController + 9 analytics services | 84 views | ✅ Fully covered |
| 21 | RBAC / Permissions | 10+ | UsersController, SystemController | 15+ tables | ✅ Fully covered |
| 22 | System Administration | 15+ | SystemAdministrationController | 30+ tables | ✅ Fully covered |
| 23 | Workflows | 5+ | Multiple controllers | 5+ tables | ✅ Fully covered |
| 24 | Website CMS | 10 pages | WebsiteController (1032L) | 8+ tables | ✅ Fully covered |
| 25 | M-Pesa & Payments | 5+ | PaymentsController (848L) | 8+ tables | ✅ Fully covered |
| 26 | Chapel/Spiritual | 1 page | ChapelController (72L) | 1 table | ⚠️ Minimal |
| 27 | Staff Lifecycle | 10+ | StaffLifecycleController, StaffAppointmentsController | 10+ tables | ✅ Fully covered |
| 28 | Two-Factor Auth | Auth flow | TwoFactorController | 4+ tables | ✅ Fully covered |

> **Note on Activities module architecture:** The `ActivitiesController` (shared across all co-curricular categories) delegates to `api/modules/activities/` which holds category-agnostic managers (`ActivitiesManager`, `ParticipantsManager`, `SchedulesManager`, `CategoriesManager`, `ResourcesManager`) and workflows (`CompetitionWorkflow`, `ActivityRegistrationWorkflow`, `ActivityPlanningWorkflow`, `PerformanceEvaluationWorkflow`). The intended architecture is that each category (Sports, Clubs, Arts, etc.) can have its **own manager/service** living under `api/modules/activities/` and exported through the shared `ActivitiesController`. Currently only generic managers exist — Sports is missing its dedicated `SportsManager` and `SportsService`.

### 2.2 What's Missing for a Complete School ERP

| # | Missing Feature | Impact | Notes |
|---|----------------|--------|-------|
| M1 | **Sports Manager & Service under Activities module** | Low | Sports uses generic Activities module with no dedicated backend manager/service. Architecture supports per-category managers — should add `SportsManager` + `SportsService` under `api/modules/activities/`, plus dedicated DB tables (`teams`, `fixtures`, `player_stats`, `sport_results`) while routing through the existing `ActivitiesController`. Player statistics backend is missing entirely. |
| M2 | **Alumni Management Module** | Low | `alumni` table exists in DB but no dedicated pages or controller for alumni relations, donations, or engagement tracking. |
| M3 | **Procurement Module** | Medium | Inventory has purchase orders and suppliers, but no full procurement lifecycle (RFQ, PO approval, GRN, vendor evaluation). |
| M4 | **Formal API Documentation** | Low | No OpenAPI/Swagger docs. An `api_explorer.php` page exists but is not comprehensive. |
| M5 | **Database Migration Framework** | Medium | `database/migrations/` is empty. No versioned schema migrations despite having a `system_migration_history` table. Schema changes require manual SQL. |
| M6 | **Automated Seeders** | Low | No database seeding system for test environments. |
| M7 | **Test Coverage** | **High** | Only **6 unit test files** across 4 directories for a ~250+ file API. No integration or E2E tests for API endpoints. The 49 controllers have zero test coverage. |
| M8 | **Hostel/Dormitory Management** | Low | Basic boarding management exists but lacks room capacity planning, bed allocation, maintenance tracking. |

### 2.3 Summary Statistics

| Metric | Count |
|--------|-------|
| Database tables | 504 |
| Database views | 84 |
| Stored procedures | 150 |
| Triggers | 58 |
| Functions | 21 |
| Events | 10 |
| API Controllers | 49 |
| Service classes | 41+ |
| Module directories | 24 |
| PHP pages | 350 |
| JS page controllers | 284 |
| Middleware classes | 10 |
| Total PHP in `api/` | ~59,334 lines |
| SQL dump size | 121,780 lines / ~7 MB |

---

## 3. Backend Review

### 3.1 Routing & Middleware

- Router is convention-based and works well for RESTful patterns
- Middleware pipeline order is logical: CORS → IP ACL → Rate Limit → Auth → CSRF → RBAC → Route Auth → Device
- `RouteAuthorization` **bypasses all API routes** — line 289-296 short-circuits with 200 for any `/api/` path, meaning **all API endpoint authorization relies 100% on individual controller checks**. If a controller forgets to check permissions, the endpoint is unprotected.
- `RateLimitMiddleware` uses DB-backed sliding window (DELETE + SELECT + INSERT per request) — high overhead under load.
- `DeviceMiddleware` generates fingerprint from only 3 attributes (IP, User-Agent, Accept-Language) — weak and easily spoofed.

### 3.2 Controller Patterns

- All controllers extend `BaseController` which extends `FileLifecycleBase`
- Method signature: `methodName($id = null, $data = [], $segments = [])` — consistent
- Response helpers: `success()`, `created()`, `badRequest()`, `unauthorized()`, `forbidden()`, `serverError()` — consistent pattern
- Many controllers exceed 1,000 lines; the biggest (`StudentsController` at 5,369 lines) is a **God object**
- `StaffLifecycleController` has a `guard()` helper on line 20 that wraps ALL methods in try-catch returning `$e->getMessage()` — this single line is responsible for leaking exceptions from the entire controller

### 3.3 Services Layer

- Well-structured with dedicated services for: Analytics (9 services), Finance (FinanceCrudService), Staff operations, Auth/Security, Infrastructure, and external integrations (M-Pesa, KCB, SMS, WhatsApp)
- Analytics services provide role-specific dashboards (Director, Headteacher, Class Teacher, Subject Teacher, Deputy Academic, Deputy Discipline, Intern Teacher, School Admin, System Admin)
- `PolicyEngine.php` provides rule-based policy evaluation
- `SharedCache.php` provides in-memory caching for services
- `AuthSessionService.php` validates tokens against `auth_sessions` table with SHA-256 hashing — strong design

### 3.4 Code Quality Issues

| # | Issue | Location |
|---|-------|----------|
| B1 | **God controllers** — StudentsController (5,369L), AcademicController (4,413L), AttendanceController (2,947L) | Multiple files |
| B2 | **Duplicate permission expansion logic** — identical code in RBACMiddleware and EnhancedRBACMiddleware | `RBACMiddleware.php:107-124`, `EnhancedRBACMiddleware.php:228-251` |
| B3 | **Bug in `mapMessageToCode()`** — `!strpos($lowerMessage, 'table') !== false` — `!` operator has higher precedence than `!==`, making the condition always true, causing false 404 responses. | `api/includes/helpers.php:74` |
| B4 | **`WorkflowHandler` uses wrong namespace** — `use App\Config\Database;` instead of proper namespace. Would cause fatal error in production. | `api/includes/WorkflowHandler.php` |
| B5 | **`StaffLifecycleController` single-line `guard()`** — wraps ALL methods in try-catch returning `$e->getMessage()`. Every exception from this controller leaks to client. | `api/controllers/StaffLifecycleController.php:20` |
| B6 | **Mixed query patterns** — some use `$this->db->query()`, some use prepared statements, some use stored procedures via `callProcedure()`. | Across many controllers |
| B7 | **No ORM** — All 504 tables accessed via raw SQL strings embedded in PHP. Error-prone and hard to refactor. | All files |

---

## 4. Frontend Review

### 4.1 Architecture

- **No framework** — Vanilla JS + Bootstrap 5. No React/Vue/Angular. This is both a strength (no build step) and weakness (manual DOM manipulation).
- **284 JS page controllers** in `js/pages/` following a consistent pattern
- **26 role-specific dashboard controllers** in `js/dashboards/`
- **Centralized API layer** (`js/api.js`, 6,068 lines) — all API calls go through `window.API.*` or `apiCall()`. No raw `fetch()`.
- **Three-tier caching** in `js/core/data_store.js` — memory LRU (100 items, 60s TTL) → IndexedDB → Network
- **Service worker** for offline support and background sync
- **Cross-tab synchronization** via BroadcastChannel + localStorage events

### 4.2 JS Controller Pattern

Consistent pattern across all 284+ controllers:

```javascript
const controllerName = {
    initialized: false,
    state: { /* reactive state */ },
    async init() { /* auth check, load, bind */ },
    setupEventListeners() { /* DOM events */ },
    async loadData() { /* window.API.* */ },
    render() { /* DOM updates */ },
    showModal(id) { /* Bootstrap modal */ },
    async save(event) { /* validate → API → refresh */ },
    async delete(id) { /* confirm → API → refresh */ },
};
```

**No virtual DOM** — all rendering is manual DOM manipulation. This works but is tedious, error-prone, and makes complex UIs hard to maintain.

### 4.3 UI Completion Assessment

- **350 PHP pages** vs **284 JS controllers** = **81% coverage**
- ~30 files are **delegation stubs** (3-12 line PHP include redirects) — these are intentional
- ~36 PHP pages have **NO JS controller** — legacy pages using traditional form POSTs or no JS
- **26/26 dashboards** have dedicated JS controllers — **100% coverage**
- **10 JS files** without exported controller names — cosmetic issue

**Pages missing JS controllers** are mostly legacy/informational pages:
- Static pages like `about.php`, `contact.php`, `careers.php`
- Legacy pre-API pages (some under `pages/legacy/`)
- Primarily server-rendered pages without interactive JS

### 4.4 CSS Organization

| Layer | File | Purpose |
|-------|------|---------|
| Foundation | `css/app-common.css` (79 lines) | Brand tokens, stat-card gradients, toast styles, accessibility |
| Theme | `css/school-theme.css` | School visual branding |
| Dashboard | `css/dashboards.css` | Role dashboard layouts |
| Role theme | `css/roles/*-theme.css` (4 files) | Per-role CSS overrides |

CSS is minimal and clean. Bootstrap 5 provides the bulk of styling. No CSS preprocessor (SASS/LESS) — uses CSS custom properties.

### 4.5 Frontend Issues

| # | Issue | Severity | Location |
|---|-------|----------|----------|
| F1 | **62 XSS via unescaped `e.message` in innerHTML** — 62 instances across 30+ JS files. Some use `_esc()`/`esc()` helper, but 12+ files use raw `${e.message}` without escaping. | **Critical** | Multiple files (see [Security Review](#7-security-review)) |
| F2 | **3 unresolved TODOs** in JS for missing API endpoints — `academics.js:1187` (curriculum units endpoint), `placement_tests.js:409,482` (API endpoints). These pages will show broken functionality. | Medium | `js/pages/academics.js`, `js/pages/placement_tests.js` |
| F3 | **No JS testing** — Zero unit tests for any of the 284+ JS controllers or the 6,068-line `api.js`. | High | All JS files |
| F4 | **Manual DOM manipulation** — No client-side templating engine or virtual DOM. Complex renders are hard to maintain and prone to XSS. | Medium | All `render()` methods |
| F5 | **CSS lacks SASS/LESS variables** — While CSS custom properties are used, the project does not use a CSS preprocessor for advanced features. | Low | All CSS |
| F6 | **~36 pages with no JS controller** — These pages may lack dynamic data loading, client-side validation, and the modern UX pattern. | Low | Various pages/ |

---

## 5. State Management & Session Handling Review

### 5.1 State Management Architecture

```
┌─────────────────────────────────────────────┐
│            Page Controller                    │
│  ┌────────────────────────────────────┐      │
│  │  state: { data, currentPage, ... } │      │
│  └────────────────────────────────────┘      │
└──────────┬──────────┬───────────────┬────────┘
           │          │               │
    ┌──────▼──┐ ┌─────▼──────┐ ┌──────▼──────────┐
    │DataStore│ │AuthContext  │ │ SessionManager   │
    │(3-tier) │ │(global)    │ │ (monitor/coordinate)│
    └─────────┘ └────────────┘ └──────────────────┘
```

**State is decentralized** — each controller owns its own `state` object. There is no Redux/Vuex-style centralized store. Data flows through:
1. `DataStore.get()` → Memory cache → IndexedDB → Network (apiCall)
2. `AuthContext` — global auth state (user, roles, permissions, tokens)
3. `SessionManager` — session lifecycle monitoring & cross-tab coordination

### 5.2 Data Caching (DataStore)

**Three-tier cache:**
- **Tier 1 — Memory LRU**: 100 entries, 60s TTL
- **Tier 2 — IndexedDB**: Persistent, per-user namespaced
- **Tier 3 — Network**: Authoritative source via `apiCall()`

**Fetch strategies:** `stale-while-revalidate` (default), `cache-first`, `network-first`, `cache-only`

**Cross-tab invalidation:** `BroadcastChannel 'kingsway-cache'` + localStorage fallback. `apiCall()` auto-invalidates on POST/PUT/PATCH/DELETE.

### 5.3 Session Management

**Session lifecycle:**
1. User logs in → JWT access token (1hr) + HttpOnly refresh cookie
2. `SessionManager` monitors every 30s via setInterval
3. Checks: idle expiry (30min), token expiry, refresh window
4. Pre-emptive token refresh before expiry (600s window)
5. On session expiry: clear user → notification → redirect → broadcast `SESSION_EXPIRED`

**Cross-tab coordination:**
- `BroadcastChannel 'kingsway-session-{uid}'` for live events
- `localStorage` events as fallback
- Tab-level mutex for token refresh to prevent stampede

### 5.4 Issues

| # | Issue | Severity | Location |
|---|-------|----------|----------|
| S1 | **No centralized state store** — Each controller has its own `state` object. Controllers cannot easily share state. If two controllers need the same data, both fetch independently. | Medium | All JS page controllers |
| S2 | **Memory cache TTL is fixed at 60s** — DataStore doesn't support per-key TTL configuration. Some data (reference data, school config) could be cached longer. | Low | `js/core/data_store.js` |
| S3 | **No WebSocket/SSE for real-time updates** — Pages must poll or wait for user action to get fresh data. | Low | Architecture |
| S4 | **`localStorage` event pattern vulnerable to XSS** — Fake `SESSION_EXPIRED`/`LOGGED_OUT` events can be injected via `localStorage.setItem()` if any XSS exists on any tab. | Medium | `js/core/session_manager.js:233-238` |
| S5 | **Offline queue mutations stored in IndexedDB** — If a user clears browser storage, queued mutations are lost. No server-side idempotency key to prevent duplicate processing. | Medium | `js/sync/sync_queue.js` |

---

## 6. Database Layer Review

### 6.1 Database Design

- **504 tables**, **84 views**, **150 stored procedures**, **58 triggers**, **21 functions**, **10 events**
- Schema stored as a **single 121,780-line SQL dump** (~7 MB)
- Connection via singleton PDO with prepared statements (emulated prepares OFF — good)
- **No ORM** — all queries are raw SQL

### 6.2 Database Issues

| # | Issue | Severity | Location |
|---|-------|----------|----------|
| D1 | **No migration framework** — `database/migrations/` is empty. Schema changes require manually editing the giant SQL dump. Despite the `system_migration_history` table, no migration tooling exists. | **High** | `database/migrations/` |
| D2 | **Monolithic SQL dump** — Single 121K-line file. Poor version control. Applying incremental schema changes in a team is impossible without migrations. | Medium | `database/KingsWayDatabase_2026_07_27_0153hrs.sql` |
| D3 | **Duplicate PDO connections** — `config_development.php` creates its own raw PDO connection at lines 79-84 to fetch current year/term at definition-time. This is before the singleton `Database` class loads — creates a duplicate connection during bootstrap. | Low | `config/config_development.php:79-84` |
| D4 | **Singleton defensive coding** — `Database::__wakeup()` throws exceptions, `__clone()` is restricted. Overly defensive but not harmful. | Low | `database/Database.php` |
| D5 | **Raw SQL everywhere** — No query builder or ORM. 504 tables worth of SQL embedded in PHP strings. Hard to maintain, hard to refactor, prone to injection if parameterization is missed. | Medium | All controllers & modules |

---

## 7. Security Review

### 7.1 Executive Summary

**Overall Severity: CRITICAL**

The application implements several strong security controls (prepared statements, CSRF tokens, RBAC, rate limiting, session hashing, security headers) but is undermined by **production secrets in git**, **massive error information leakage** (100+ instances), **authentication bypass backdoor**, and **XSS vulnerabilities** (62+ instances).

### 7.2 Critical Vulnerabilities

#### C-01: Production Financial Credentials in Git — [IMMEDIATE ACTION REQUIRED]

**File:** `config/.env`
**Risk Level: CRITICAL** — These are LIVE production financial API credentials:

| Credential | Value (truncated) | Impact if Compromised |
|-----------|-------------------|-----------------------|
| **M-Pesa Consumer Key** | `KOuNiCwAxbOoHXgwLhiO19uukdg2AwfDClPeuImtm2dSSN5h` | Full M-Pesa API access — initiate STK Push, B2C payments, query transactions |
| **M-Pesa Consumer Secret** | `1xGaj8DxjK0iAK0gOESHUfy2hnfVmQQPDr2AAiMDggwHHNPw16odelfjGGfAO1IV` | Combined with consumer key: OAuth token generation, full API access |
| **M-Pesa Security Credential** | (encrypted B2C credential) | B2C (Business-to-Consumer) payments — ability to send money to any phone |
| **KCB Consumer Key** | `VuDpL9GmLg5GgC_Y3yeNXqRsshQa` | KCB Buni API access |
| **KCB Consumer Secret** | `voOG2c_HYHdV_mVmFW8nErkgTFUa` | Combined with key: full KCB banking API access |
| **KCB API Key** | `eyJ4NXQiOi...` (long JWT) | Direct KCB API access without authentication |
| **Africa's Talking SMS Key** | `atsk_c5500c783227e742d2db31baf235dccfbce1ca1923ae3316026cdf8354c1e531e98ebf2c` | Send SMS at the school's expense, read SMS logs |
| **SMTP Password** | `@Kingsway123` | Send email as `info@kingswaypreparatoryschool.sc.ke` |
| **Database Password** | `admin123` | Direct database access |
| **JWT Secret** | `51c47afc73a6f2cf1a052309d1f8a8bb4839d7bc7aaddb32cd8f26b2898aed23` | Forge ANY JWT token — impersonate any user |

**Remediation:**
1. **IMMEDIATELY** rotate ALL credentials (M-Pesa CK/CS, KCB keys, AT SMS key, SMTP password, JWT secret)
2. Remove `.env` from git history with `git filter-branch` or `BFG Repo-Cleaner`
3. Add `.env` to `.gitignore` (it is currently missing — only `.env.example` should be tracked)
4. Store production secrets in environment variables or a secrets manager (Azure Key Vault, AWS Secrets Manager)
5. Set up CI/CD to inject secrets at deploy time

#### C-02: Developer Authentication Bypass

**File:** `js/core/app_bootstrap.js:29`
```javascript
if (localStorage.getItem('dev_bypass_auth') !== 'true') {
    location.replace(`${window.APP_BASE || ''}/index.php`);
}
```

**Risk:** Setting `localStorage.dev_bypass_auth = 'true'` in browser devtools completely bypasses authentication. Any user discovering this (via devtools, bookmarklet, compromised browser extension) gains access to the full application.

**Remediation:** Remove the `dev_bypass_auth` check entirely. If developer bypass is needed, use a server-side IP whitelist or feature flag. The line should simply redirect unauthenticated users unconditionally.

#### C-03: Massive Error Information Leakage (100+ Instances)

`$e->getMessage()` is returned directly to clients in API responses across the codebase. This leaks:
- SQL queries and database schema details (table names, column names)
- Internal file paths and filesystem layout
- PDO exception messages revealing database structure
- Internal application logic details

**Affected files (representative sample):**

| File | Instances |
|------|-----------|
| `api/controllers/WebsiteController.php` | 33+ |
| `api/controllers/StaffController.php` | 25+ |
| `api/controllers/HealthController.php` | 8 |
| `api/controllers/AttendanceController.php` | 13+ |
| `api/controllers/FinanceController.php` | 5 |
| `api/modules/inventory/UniformSalesManager.php` | 20 |
| `api/modules/students/StudentService.php` | 14 |
| `api/modules/finance/BudgetManager.php` | 7 |
| `api/modules/finance/FeeManager.php` | 17 |
| `api/modules/finance/BudgetApprovalWorkflow.php` | 11 |
| `api/modules/inventory/StockAuditWorkflow.php` | 2 |
| `api/modules/inventory/StockTransferWorkflow.php` | 2 |
| `api/modules/inventory/AssetDisposalWorkflow.php` | 2 |
| `api/modules/counseling/CounselingAPI.php` | 6 |
| `api/modules/activities/ActivitiesManager.php` | 3 |
| `api/modules/activities/ParticipantsManager.php` | 1 |
| `api/modules/activities/SchedulesManager.php` | 1 |
| `api/modules/staff/OnboardingWorkflow.php` | 2 |
| `api/modules/staff/StaffIDCardGenerator.php` | 8 |
| `api/modules/communications/CommunicationsAPI.php` | 11 |
| `api/router/Router.php` | 1 (global catch-all) |
| `api/controllers/StaffLifecycleController.php` | 1 (wraps ALL methods) |
| **Total** | **~175+ instances** |

**Remediation:**
```php
// INSTEAD OF:
return $this->serverError($e->getMessage());
// OR:
return $this->error('Failed: ' . $e->getMessage());

// USE:
error_log('Operation failed: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return $this->serverError('An internal error occurred. Please contact the administrator.');
```

Or better, create a centralized error handler:
```php
protected function handleError(\Throwable $e, string $context = 'Operation') {
    error_log("[{$context}] {$e->getMessage()} in {$e->getFile()}:{$e->getLine()}");
    return $this->serverError("{$context} failed. Please contact the administrator.");
}
```

#### C-04: XSS — Unescaped `e.message` in innerHTML (62 Instances)

**62 instances across 30+ JS files** where error messages are injected into `innerHTML` without escaping. Some files use `_esc()`/`esc()` helpers (parent_portal.js, vendors.js, etc.), but many do **not**:

**Unprotected files (raw `${e.message}` without escaping):**
| File | Lines |
|------|-------|
| `js/pages/manage_website.js` | 153, 273, 350, 418, 496, 588, 640, 678, 715, 829 |
| `js/pages/manage_activities.js` | 122, 258, 371, 461, 572 |
| `js/pages/fee_structure_admin.js` | 1543 |
| `js/pages/report_cards.js` | 231 |
| `js/pages/salary_advances.js` | 45 |
| `js/pages/staff_onboarding.js` | 64 |

**Protected files (properly using `_esc()` or `esc()` or `this._esc()`):**
| File | Lines |
|------|-------|
| `js/pages/parent_portal.js` | 219, 291 |
| `js/pages/vendors.js` | 207 |
| `js/pages/cash_reconciliation.js` | 114, 231 |
| `js/pages/manage_library.js` | 205, 303, 315, 457, 522 |
| `js/pages/student_profiles.js` | 132, 228, 277, 362, 444, 490 |
| `js/pages/admission_interviews.js` | 55, 337 |
| `js/pages/formative_assessments.js` | 202, 388, 542 |
| `js/pages/national_exams.js` | 129, 219, 319 |
| ... and ~30 more | |

**Risk:** If an API error message contains user-controlled data (e.g., a malformed student name, a validation error reflecting user input), the attacker's payload executes as DOM. This enables stored/reflected XSS.

**Remediation:**
- Add `_esc()` calls to the ~12 unprotected files (manage_website.js, manage_activities.js, fee_structure_admin.js, report_cards.js, salary_advances.js, staff_onboarding.js)
- Audit all other `${e.message}` usages
- Ideally, create a helper function like `showError(container, message)` that safely uses `textContent` instead of `innerHTML`

### 7.3 High Vulnerabilities

#### H-01: Router Exception Leaks Internal Details

**File:** `api/router/Router.php:67-81`

The global try-catch in the router returns `$e->getMessage()` directly to the client. While `api/index.php` has a generic `set_exception_handler`, the Router's catch fires FIRST (it's inside the normal request flow) and leaks the message. Any unhandled exception from any controller leaks internal details.

**Remediation:** Replace `$e->getMessage()` with a generic message. Log the actual exception.

#### H-02: SQL Injection Risk — Dynamic SQL Interpolation

**Files:**
- `api/services/AuthSessionService.php` — `$idleTimeoutSeconds` interpolated directly into SQL strings (lines 183, 235, 540) inside `INTERVAL {$idleTimeoutSeconds} SECOND`
- `api/includes/helpers.php` — `SHOW COLUMNS FROM \`$table\`` uses direct table name interpolation with backtick quoting (no parameter binding)

**Remediation:** Parameterize all dynamic values. For `INTERVAL` clauses, cast to integer and validate. For table names, validate against an allowlist.

#### H-03: `RouteAuthorization` Bypasses All API Routes

**File:** `api/middleware/RouteAuthorization.php:289-296`

```php
if (preg_match('#/api(/|$)#', $path)) {
    return ['success' => true, 'http_code' => 200];
}
```

All `/api/*` routes automatically pass the route whitelist check. This means authorization for API endpoints relies entirely on controller-level checks. If a controller method forgets to call `userHasPermission()`, the endpoint is unprotected.

**Remediation:** Remove this bypass. API routes should also be validated against the route whitelist, or at minimum have a mandatory permission assertion in the base controller.

#### H-04: Rate Limiting for Login is Too Generous

- Anonymous users: 120 req/min (2 req/sec) — allows 120 password guesses per minute
- No account lockout mechanism after failed login attempts
- `AuthMiddleware` has no separate, more restrictive rate limit for login specifically

**Remediation:** Add a separate rate limit for `/auth/login` (e.g., 5 attempts per IP per minute, 10 per user per hour). Implement account lockout after 5 consecutive failed attempts.

#### H-05: `.htaccess` Missing API File Access Protection

**Active `.htaccess`:**
```apache
RewriteCond %{REQUEST_FILENAME} !-f
RewriteRule ^api/(.*)$ api/index.php [L,QSA]
```

**Missing rule** (present in `htaccess.txt` but NOT in active `.htaccess`):
```apache
RewriteRule ^api/[^/]+\.php$ - [F]
```

Without this rule, an attacker could directly call `api/controllers/SomeController.php` and bypass the entire middleware pipeline.

**Remediation:** Add the file-blocking rule from `htaccess.txt` into the active `.htaccess`.

#### H-06: Debug Mode Enabled Alongside Production Credentials

**File:** `config/.env`
```
APP_ENV=development
DEBUG=true
MPESA_ENVIRONMENT=production
KCB_ENVIRONMENT=production
```

Despite `APP_ENV=development`, the M-Pesa and KCB environments are set to **production**. Having `DEBUG=true` with production credentials is extremely dangerous — if the app misroutes or leaks debug information during a production financial transaction, sensitive data is exposed.

**Remediation:** Separate development and production `.env` files. Never put production credentials in a dev `.env`.

### 7.4 Medium Vulnerabilities

| # | Issue | Location |
|---|-------|----------|
| M-01 | **CORS missing `Vary: Origin` header** — Without it, CDNs may cache responses for one origin and serve them to another. | `api/middleware/CORSMiddleware.php` |
| M-02 | **CSRF token lifetime is 1 hour** — Generous window for token theft. | `api/middleware/CsrfMiddleware.php:121` |
| M-03 | **No CSP on HTML pages** — CSP header only set on JSON API responses. PHP-rendered pages have no CSP. | `api/index.php` vs. all HTML pages |
| M-04 | **Weak default DB credentials** — `DB_PASS` defaults to `admin123` if `.env` not loaded. | `config/config_development.php:70` |
| M-05 | **Weak default JWT secret** — Defaults to `dev_secret_key_change_this_use_64_chars_minimum` if `.env` not loaded. | `config/config_development.php:103` |
| M-06 | **No account lockout** — No mechanism to lock accounts after failed login attempts. | Auth flow |
| M-07 | **File upload accepts `application/octet-stream`** — Generic MIME type accepted for many categories; could mask executable uploads. | `api/services/UploadService.php` |
| M-08 | **Potential path traversal in `UploadService`** — Sanitization relies on prefix-based checks that could be bypassed. | `api/services/UploadService.php:319-347` |
| M-09 | **Weak device fingerprint** — Only IP, User-Agent, Accept-Language. Easily spoofed. | `api/middleware/DeviceMiddleware.php:46-52` |
| M-10 | **Parent portal tokens stored in `sessionStorage` in cleartext** — No encryption wrapping. | `js/pages/parent_portal.js:12-13` |
| M-11 | **CSRF token is user-visible base64-encoded JSON** — While HMAC-signed, the structure reveals `user_id`, `timestamp`, `random` to any observer. | `api/middleware/CsrfMiddleware.php:106` |
| M-12 | **No JWT `iss`/`aud` claim verification** — Tokens not scoped to specific issuer/audience. | `api/middleware/AuthMiddleware.php:191-194` |
| M-13 | **Weak default password for staff creation** — `Kingsway@` + 8 random alphanumeric chars. Predictable prefix reduces entropy. | `js/pages/staff_controllers.js:288` |

### 7.5 Security Strengths (What's Done Right)

| Feature | Details |
|---------|---------|
| **Prepared statements** | Extensive use of parameterized queries via PDO — good SQL injection protection in primary queries |
| **CSRF protection** | HMAC-signed tokens on all mutating requests (POST/PUT/DELETE/PATCH) |
| **RBAC** | 3-layer authorization: middleware RBAC → route auth → controller-level checks |
| **Rate limiting** | Per-IP (120 req/min) and per-user (600 req/min) rate limiting |
| **Session hashing** | Tokens SHA-256 hashed before storage in `auth_sessions` table |
| **Security headers** | CSP, X-Frame-Options, X-Content-Type-Options, Referrer-Policy, Permissions-Policy, Cache-Control (on API responses) |
| **Error handlers** | `set_exception_handler`, `set_error_handler`, `register_shutdown_function` in `index.php` all return generic messages (though Router's catch bypasses these) |
| **JWT with HS256** | Uses strong algorithm, not the weaker `HS256` vs `none` confusion |
| **HttpOnly refresh cookies** | Refresh tokens are HttpOnly — JS never touches them |
| **Multi-tab isolation** | localStorage keys namespaced by user ID; BroadcastChannel for events |
| **Content-Type validation** | UploadService validates both extension AND MIME type via `finfo` |

---

## 8. Production Readiness

### 8.1 Blockers (Must Fix Before Production)

| # | Issue | Priority |
|---|-------|----------|
| 1 | **Production credentials in `.env` committed to git** — Rotate ALL credentials immediately. Remove `.env` from repo history. | **CRITICAL** |
| 2 | **Developer auth bypass** — Remove `localStorage.dev_bypass_auth` check from `app_bootstrap.js`. | **CRITICAL** |
| 3 | **Error information leakage** — Fix all ~175+ instances of `$e->getMessage()` being returned to clients. | **CRITICAL** |
| 4 | **XSS via unescaped `e.message` in innerHTML** — Fix all 62 instances. | **Critical** |
| 5 | **Router exception leak** — Fix `Router.php:77` to return generic message. | **Critical** |
| 6 | **`.htaccess` missing API file protection** — Add `RewriteRule` blocking direct PHP access in `api/`. | **High** |
| 7 | **API route authorization bypass** — Remove `/api/` short-circuit from `RouteAuthorization`. | **High** |
| 8 | **Weak login rate limiting** — Add stricter rate limiting and account lockout. | **High** |
| 9 | **CSP missing on HTML pages** — Add Content-Security-Policy header to PHP-rendered pages. | **Medium** |
| 10 | **`APP_ENV=development` with `MPESA_ENVIRONMENT=production`** — Separate dev/prod environments. | **Critical** |

### 8.2 Strong Recommendations for Production

| # | Recommendation | Priority |
|---|---------------|----------|
| R1 | **Set up CI/CD pipeline** — No CI/CD detected. Add automated testing, linting, and deployment pipeline. | High |
| R2 | **Add migration framework** — Implement `phinx`, `doctrine-migrations`, or custom migration system. Without it, schema changes cannot be reliably deployed. | High |
| R3 | **Add integration tests** — Zero API integration tests. Every controller endpoint should have basic tests. | High |
| R4 | **Separate dev/prod `.env` files** — Use environment variables or a secrets manager for production. | High |
| R5 | **Split god controllers** — `StudentsController` (5,369L), `AcademicController` (4,413L), `AttendanceController` (2,947L) should be split into domain-specific handlers. | Medium |
| R6 | **Add JS unit tests** — At minimum, test `apiCall()`, `DataStore`, `SessionManager`, and critical page controllers. | Medium |
| R7 | **Fix API endpoint TODOs** — Complete the 3 unresolved JS API endpoints (curriculum units, placement tests). | Medium |
| R8 | **Add `Vary: Origin` header** to CORS middleware. | Low |
| R9 | **Add monitoring/alerting** — No centralized logging, APM, or error tracking configured. | Medium |
| R10 | **Add backup automation** — `system_backups` table exists but no automated backup strategy was detected. | Medium |
| R11 | **Reduce CSRF token lifetime** from 1 hour to 15-30 minutes. | Medium |
| R12 | **Add explicit JWT claim verification** (`iss`, `aud`, `nbf`). | Low |
| R13 | **Implement a centralized error handling method** in `BaseController` to avoid repeating the `catch { log; return generic_error }` pattern. | Medium |
| R14 | **Consider using an ORM or query builder** for new development to reduce SQL injection risk and improve maintainability. | Low |
| R15 | **Add proper logging framework** — Replace `error_log()` calls with PSR-3 compatible logger (Monolog is already installable via Composer). | Medium |

### 8.3 What's Production-Ready

| Aspect | Status |
|--------|--------|
| **Security headers** (API) | ✅ Excellent |
| **CSRF protection** | ✅ Good (need lifetime reduction) |
| **RBAC / Authorization** | ✅ Good (need route authorization fix) |
| **SQL injection prevention** (primary queries) | ✅ Good |
| **XSS prevention** (most pages) | ✅ Adequate (need to fix 12 files) |
| **Cache management** | ✅ Excellent (3-tier, cross-tab) |
| **Session management** | ✅ Excellent |
| **Offline support** | ✅ Excellent (service worker, sync queue) |
| **File upload security** | ✅ Good (extension + MIME validation) |
| **Rate limiting** | ⚠️ Good for general use, weak for login |
| **CORS configuration** | ⚠️ Missing Vary header |
| **Content Security Policy** | ⚠️ API only, not on HTML pages |
| **Error handling** | ❌ Critical — leaking details everywhere |
| **Secret management** | ❌ Critical — credentials in git |
| **Testing** | ❌ Critical — 6 unit tests for 250+ files |
| **CI/CD** | ❌ Not detected |
| **Database migrations** | ❌ Not implemented |
| **Logging** | ⚠️ `error_log()` calls scattered, no PSR-3 logger |
| **Monitoring** | ❌ Not configured |

---

## 9. Summary of Issues by Priority

### Critical
| ID | Description | Category |
|----|------------|----------|
| C-01 | Production financial credentials (M-Pesa, KCB, AT SMS, SMTP, DB, JWT) committed to git | Security |
| C-02 | Developer auth bypass via `localStorage.dev_bypass_auth` | Security |
| C-03 | ~175+ instances of `$e->getMessage()` leaking internal details | Security |
| C-04 | 62 XSS instances via unescaped `e.message` in innerHTML across 30+ JS files | Security |
| A7 | Router global exception handler leaks `$e->getMessage()` | Security/Backend |

### High
| ID | Description | Category |
|----|------------|----------|
| H-01 | Route `/api/*` bypass in `RouteAuthorization` — auth relies 100% on controller checks | Security/Backend |
| H-02 | SQL injection risk — dynamic SQL in `AuthSessionService` and `helpers.php` | Security |
| H-03 | `.htaccess` missing API file-direct-access protection rule | Security |
| H-04 | Weak login rate limiting (120 req/min) with no account lockout | Security |
| H-05 | `APP_ENV=development` with `MPESA_ENVIRONMENT=production` | Security |
| A2 | `system_config_router.php` bypasses middleware pipeline | Backend |
| D1 | No database migration framework | Database |
| D5 | No ORM/query builder — raw SQL everywhere | Backend |
| F3 | Zero JS tests for 284+ controllers | Frontend |
| M7 | Zero API integration tests — only 6 unit tests for entire backend | Testing |

### Medium
| ID | Description | Category |
|----|------------|----------|
| A1 | Two competing response format systems | Backend |
| A4 | Duplicate module directories (`finance` vs `Finance`) | Backend |
| A5 | God controllers (5,369L, 4,413L, 2,947L) | Backend |
| A6 | PSR-4 case-sensitivity risk on Linux | Backend |
| B3 | Bug in `mapMessageToCode()` — incorrect operator precedence | Backend |
| S4 | `localStorage` event pattern vulnerable to XSS injection | Frontend |
| F2 | 3 unresolved JS API endpoint TODOs | Frontend |
| M-01-M-13 | Multiple medium security issues (see Security Review) | Security |

### Low
| ID | Description | Category |
|----|------------|----------|
| A3 | Unused `EnhancedRBACMiddleware` | Backend |
| A8-A10 | Dead code, legacy files, manual requires | Backend |
| D3-D4 | Duplicate PDO connections, singleton defense | Database |
| F5-F6 | CSS preprocessor missing, no-JS pages | Frontend |
| S2-S3 | Cache TTL rigidity, no real-time updates | Frontend |
| M1 | Sports backend (manager/service/tables) missing | Module |

---

## 10. Remediation Roadmap

### Phase 1: Immediate (0-2 days) — Security Crisis
1. Rotate ALL credentials in `config/.env` (M-Pesa, KCB, AT SMS, SMTP, JWT secret, DB password)
2. Remove `config/.env` from git history using `git filter-branch` or BFG
3. Add `.env` to `.gitignore`
4. Remove `dev_bypass_auth` check from `js/core/app_bootstrap.js`
5. Fix `Router.php:77` to return generic error message
6. Fix the ~62 XSS instances in JS files (add `_esc()` where missing)
7. Add `.htaccess` API file protection rule from `htaccess.txt`

### Phase 2: Short-term (1-2 weeks) — Security Hardening
1. Fix all ~175+ `$e->getMessage()` instances across controllers, modules, and services
2. Add strict rate limiting for login endpoint + account lockout
3. Add CSP header to all HTML pages
4. Fix `RouteAuthorization` API route bypass
5. Separate production and development `.env` configurations
6. Add `Vary: Origin` header to CORS middleware
7. Fix SQL injection risks in `AuthSessionService` and `helpers.php`

### Phase 3: Medium-term (2-4 weeks) — Architecture & Testing
1. Implement database migration framework
2. Add API integration tests for critical endpoints
3. Add JS unit tests for core infrastructure (`apiCall`, `DataStore`, `SessionManager`)
4. Split god controllers (`StudentsController`, `AcademicController`, `AttendanceController`)
5. Fix PSR-4 case-sensitivity in module directories
6. Standardize response format (eliminate dual system)
7. Integrate `system_config_router.php` into main Router pipeline
8. Set up CI/CD pipeline with automated testing

### Phase 4: Long-term (1-3 months) — Production Hardening
1. Implement centralized logging with PSR-3 compatible logger (Monolog)
2. Add monitoring, alerting, and APM
3. Add automated backup strategy
4. Implement remaining module gaps (Sports manager/service/tables under Activities module, Procurement, Alumni)
5. Consider implementing an ORM for new development
6. Add OpenAPI documentation generation
7. Performance optimization of rate limiter (move from DB-backed to Redis-backed)
8. Add E2E UI smoke tests (expand beyond the single Puppeteer test)

---

## 11. Proposed Implementation Plan for Closing Gaps

### 11.1 Sports Manager/Service/Tables (M1)

**Architecture** (following existing Activities module patterns):

```
ActivitiesController (unchanged — add new delegation methods)
  └── ActivitiesAPI (orchestrator — wire in new managers)
        ├── SportsManager        (new — low-level CRUD for sports tables)
        ├── SportsService        (new — business logic: standings, rankings, stats)
        └── ... existing managers/workflows unchanged
```

**Phase 1 — Database Tables** (`database/migrations/001_create_sports_tables.sql`):

```sql
-- Sports teams (replaces generic "activity as team" pattern)
CREATE TABLE sports_teams (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(150) NOT NULL,
    sport_type      VARCHAR(50) NOT NULL,        -- football, basketball, athletics, etc.
    category_id     INT NOT NULL,                -- FK to activity_categories (e.g. "Sports")
    coach_id        INT,                         -- FK to staff
    captain_id      INT,                         -- FK to students
    description     TEXT,
    season          VARCHAR(50),                 -- e.g. "2026 Term 1"
    wins            INT DEFAULT 0,
    losses          INT DEFAULT 0,
    draws           INT DEFAULT 0,
    status          ENUM('active','inactive','disbanded') DEFAULT 'active',
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES activity_categories(id),
    FOREIGN KEY (coach_id) REFERENCES staff(id),
    FOREIGN KEY (captain_id) REFERENCES students(id)
);

-- Team rosters (student membership in teams)
CREATE TABLE sports_team_members (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    team_id         INT NOT NULL,
    student_id      INT NOT NULL,
    position        VARCHAR(100),                -- e.g. "Forward", "Goalkeeper"
    jersey_number   INT,
    joined_date     DATE,
    status          ENUM('active','inactive','graduated') DEFAULT 'active',
    FOREIGN KEY (team_id) REFERENCES sports_teams(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(id)
);

-- Fixtures (matches)
CREATE TABLE sports_fixtures (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    team_id         INT NOT NULL,
    opponent        VARCHAR(200) NOT NULL,
    fixture_type    ENUM('friendly','league','cup','tournament') DEFAULT 'friendly',
    venue           VARCHAR(255),
    fixture_date    DATETIME NOT NULL,
    home_away       ENUM('home','away') DEFAULT 'home',
    status          ENUM('scheduled','in_progress','completed','cancelled','postponed') DEFAULT 'scheduled',
    -- Results (only populated when status = 'completed')
    our_score       INT DEFAULT NULL,
    opponent_score  INT DEFAULT NULL,
    result          ENUM('win','loss','draw','') DEFAULT NULL,
    match_report    TEXT,
    referee         VARCHAR(150),
    season          VARCHAR(50),
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (team_id) REFERENCES sports_teams(id) ON DELETE CASCADE
);

-- Player match statistics
CREATE TABLE sports_player_stats (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    fixture_id      INT NOT NULL,
    player_id       INT NOT NULL,                -- FK to students
    team_id         INT NOT NULL,
    goals_scored    INT DEFAULT 0,
    assists         INT DEFAULT 0,
    minutes_played  INT DEFAULT 0,
    yellow_cards    INT DEFAULT 0,
    red_cards       INT DEFAULT 0,
    subs_in         TINYINT DEFAULT 0,
    subs_out        TINYINT DEFAULT 0,
    rating          DECIMAL(3,1) DEFAULT NULL,   -- 1.0-10.0 performance rating
    notes           TEXT,
    FOREIGN KEY (fixture_id) REFERENCES sports_fixtures(id) ON DELETE CASCADE,
    FOREIGN KEY (player_id) REFERENCES students(id),
    FOREIGN KEY (team_id) REFERENCES sports_teams(id) ON DELETE CASCADE
);

-- League/competition standings
CREATE TABLE sports_standings (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    competition_id  INT,                         -- FK to workflow_instances or new competitions table
    team_id         INT NOT NULL,
    played          INT DEFAULT 0,
    won             INT DEFAULT 0,
    lost            INT DEFAULT 0,
    drawn           INT DEFAULT 0,
    goals_for       INT DEFAULT 0,
    goals_against   INT DEFAULT 0,
    points          INT DEFAULT 0,
    position        INT,
    season          VARCHAR(50),
    FOREIGN KEY (team_id) REFERENCES sports_teams(id) ON DELETE CASCADE
);

-- Indexes for performance
CREATE INDEX idx_fixtures_team ON sports_fixtures(team_id);
CREATE INDEX idx_fixtures_date ON sports_fixtures(fixture_date);
CREATE INDEX idx_fixtures_season ON sports_fixtures(season);
CREATE INDEX idx_player_stats_fixture ON sports_player_stats(fixture_id);
CREATE INDEX idx_player_stats_player ON sports_player_stats(player_id);
CREATE INDEX idx_standings_season ON sports_standings(season);
```

**Phase 2 — `SportsManager.php`** (`api/modules/activities/SportsManager.php`):

```
namespace App\API\Modules\activities;

class SportsManager extends BaseAPI
{
    // Constructor: parent::__construct('sports_teams')
    
    // TEAMS
    listTeams($params)          → SELECT from sports_teams + LEFT JOIN sport_type, coach, captain
    getTeam($id)                → SELECT team + member_count + recent_fixtures
    createTeam($data, $userId)  → INSERT into sports_teams
    updateTeam($id, $data)      → UPDATE sports_teams
    deleteTeam($id)             → DELETE sports_teams (cascades to members, fixtures, stats)
    getTeamRoster($teamId)      → SELECT from sports_team_members JOIN students
    addTeamMember($data)        → INSERT sports_team_members
    removeTeamMember($id)       → DELETE sports_team_members
    
    // FIXTURES
    listFixtures($params)       → SELECT sports_fixtures (filter by team, season, status, date range)
    getFixture($id)             → SELECT fixture + player stats
    createFixture($data)        → INSERT sports_fixtures
    updateFixture($id, $data)   → UPDATE sports_fixtures
    deleteFixture($id)          → DELETE sports_fixtures
    recordResult($id, $data)    → UPDATE sports_fixtures SET our_score, opponent_score, result, status='completed'
                                  → UPDATE sports_teams SET wins/losses/draws (aggregate)
    
    // PLAYER STATS
    getPlayerStats($fixtureId)  → SELECT sports_player_stats WHERE fixture_id
    savePlayerStats($data[])    → INSERT/UPDATE sports_player_stats (bulk per fixture)
    getPlayerCareerStats($id)   → SELECT SUM(goals, assists, etc.) GROUP BY player_id
    
    // STANDINGS
    getStandings($season)       → SELECT sports_standings ORDER BY points DESC
    calculateStandings()        → Recalculate standings from fixtures
}
```

**Phase 3 — `SportsService.php`** (`api/modules/activities/SportsService.php`):

```
namespace App\API\Modules\activities;

class SportsService extends BaseAPI
{
    // Higher-level business logic, coordinates SportsManager
    getDashboardStats($season)      → teams count, fixtures count, top scorers, recent results
    getLeagueTable($season)         → standings + form guide (last 5 results)
    getHeadToHead($teamA, $teamB)   → historical results between two teams
    getTopScorers($season, $limit)  → players with most goals
    getSeasonSummary($season)       → complete season stats (wins, losses, top performers)
    getUpcomingMatches($days)       → next N days of fixtures across all teams
}
```

**Phase 4 — Wire into `ActivitiesAPI`**:

```php
class ActivitiesAPI extends BaseAPI
{
    private SportsManager $sportsManager;
    private SportsService $sportsService;

    public function __construct() {
        // ... existing initializations ...
        $this->sportsManager = new SportsManager();
        $this->sportsService = new SportsService();
    }

    // Delegation methods:
    public function listTeams($params)     { return $this->sportsManager->listTeams($params); }
    public function createTeam($data, $uid) { return $this->sportsManager->createTeam($data, $uid); }
    public function listFixtures($params)   { return $this->sportsManager->listFixtures($params); }
    public function recordResult($id, $data) { return $this->sportsManager->recordResult($id, $data); }
    public function getStandings($params)   { return $this->sportsManager->getStandings($params); }
    public function getDashboardStats($params) { return $this->sportsService->getDashboardStats($params); }
    // ... etc
}
```

**Phase 5 — Controller Methods** (`ActivitiesController.php`):

```php
// Pattern: getSports{Resource}{Action}()
getSportsTeams()           → $this->api->listTeams($data)
postSportsTeams()          → $this->api->createTeam($data, userId)
getSportsFixtures()        → $this->api->listFixtures($data)
postSportsFixtures()       → $this->api->createFixture($data)
postSportsRecordResult()   → $this->api->recordResult($id, $data)
getSportsStandings()       → $this->api->getStandings($data)
getSportsDashboardStats()  → $this->api->getDashboardStats($data)
```

**Phase 6 — JS API Surface** (`js/api.js`):

```javascript
// Under window.API.activities — add sports-specific endpoints:
window.API.activities = {
    // ... 60+ existing methods ...

    // Sports
    listTeams: (params)             => apiCall('/activities/sports/teams', 'GET', null, params),
    createTeam: (data)              => apiCall('/activities/sports/teams', 'POST', data),
    listFixtures: (params)          => apiCall('/activities/sports/fixtures', 'GET', null, params),
    recordResult: (data)            => apiCall('/activities/sports/record-result', 'POST', data),
    getStandings: (params)          => apiCall('/activities/sports/standings', 'GET', null, params),
    getDashboardStats: (params)     => apiCall('/activities/sports/dashboard-stats', 'GET', null, params),
};
```

**Phase 7 — Frontend** (`js/pages/sports.js`):

- Replace `window.API.activities.list({ category: "sports" })` → `window.API.activities.listTeams()`
- Replace `window.API.activities.listSchedules({ type: "sports" })` → `window.API.activities.listFixtures()`
- Add create team modal form
- Add fixture modal (opponent, date, venue, home/away)
- Add result recording form (scores, player stats)
- Add standings table view

**Phase 8 — Add missing modals** (`pages/sports.php`):

- Add `#addTeamModal` with fields: name, sport type, coach, captain, description
- Add `#addFixtureModal` with fields: team, opponent, date, venue, home/away
- Add `#recordResultModal` with fields: our score, opponent score, player stats grid
- Wire up via the existing Bootstrap 5 modal pattern

---

### 11.2 Database Migration Framework (M5)

**Recommendation: Use a lightweight custom migration system** (no external dependency needed).

**Directory structure:**
```
database/
├── Database.php                      (existing — PDO singleton)
├── Migrator.php                      (new — run/rollback migrations)
├── migrations/
│   ├── 001_create_sports_tables.sql
│   ├── 002_add_alumni_module.sql
│   ├── 003_create_procurement_tables.sql
│   └── ...
└── seeders/
    ├── Seeder.php                    (base class)
    ├── AcademicYearSeeder.php
    └── SystemConfigSeeder.php
```

**`database/Migrator.php` skeleton:**

```
namespace App\Database;

class Migrator
{
    private \PDO $db;
    private string $migrationDir;
    private string $historyTable = 'system_migration_history';

    public function __construct() { /* init DB */ }

    public function migrate(): array
    {
        // 1. Ensure system_migration_history table exists
        // 2. Get all completed migrations from DB
        // 3. Scan migration files sorted by prefix (001_, 002_, etc.)
        // 4. For each unapplied migration:
        //    a. Read SQL file
        //    b. Execute in transaction
        //    c. Record in system_migration_history (migration_name, hash, applied_at, duration_ms)
        // 5. Return list of applied migrations
    }

    public function rollback(int $steps = 1): array
    {
        // 1. Get last N applied migrations
        // 2. Look for corresponding down.sql files (001_down.sql)
        // 3. Execute rollback SQL
        // 4. Remove from history
    }

    public function seed(string $seederClass): void
    {
        // Execute seeder
    }

    public function status(): array
    {
        // Return list of migrations with applied/pending status
    }
}
```

**Integration points:**
- Add a CLI entry point: `php scripts/migrate.php migrate|rollback|status|seed`
- Add a web UI page: `pages/migrations.php` (already exists as stub)
- Hook into `Config::init()` for auto-migrate in development only

---

### 11.3 Alumni Management Module (M2)

**Leverages existing `alumni` table** (already in schema).

**Files to create:**
```
api/
├── controllers/AlumniController.php         (new — REST endpoints)
├── modules/alumni/
│   ├── AlumniAPI.php                        (orchestrator)
│   ├── AlumniManager.php                    (CRUD operations)
│   └── AlumniService.php                    (engagement, donations, events)
pages/
├── alumni/
│   ├── admin_alumni.php
│   └── alumni_directory.php
├── alumni_dashboard.php
js/
├── pages/alumni.js                          (controller)
├── pages/alumni_directory.js
```

**Endpoints:**
- `GET /api/alumni` — list/search alumni (by graduation year, class, status)
- `POST /api/alumni` — register new alumni (migrate from students)
- `PUT /api/alumni/{id}` — update contact info, employment
- `GET /api/alumni/directory` — public directory (opt-in)
- `POST /api/alumni/donations` — record donations
- `GET /api/alumni/engagement` — engagement stats (events attended, donations)

**Pages:**
- Alumni directory (searchable, filterable)
- Alumni profile (education history, employment, donations)
- Alumni dashboard (stats: total alumni, by year, by region, donations)

---

### 11.4 Procurement Module Enhancement (M3)

**Enhances existing Inventory module** — not a separate module.

**New tables:**
```sql
CREATE TABLE procurement_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    requester_id INT NOT NULL,          -- FK to staff
    department_id INT,
    requested_date DATE NOT NULL,
    required_date DATE,
    priority ENUM('low','medium','high','urgent') DEFAULT 'medium',
    status ENUM('draft','submitted','approved','rejected','ordered','received','closed'),
    notes TEXT,
    approved_by INT,                    -- FK to staff
    approved_at DATETIME,
    total_estimated DECIMAL(12,2),
    FOREIGN KEY (requester_id) REFERENCES staff(id)
);

CREATE TABLE procurement_request_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    request_id INT NOT NULL,
    item_description VARCHAR(500) NOT NULL,
    quantity INT NOT NULL,
    estimated_unit_cost DECIMAL(12,2),
    actual_unit_cost DECIMAL(12,2),
    category_id INT,
    inventory_item_id INT,              -- linked when item is received into inventory
    FOREIGN KEY (request_id) REFERENCES procurement_requests(id) ON DELETE CASCADE
);

CREATE TABLE purchase_orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    request_id INT,
    supplier_id INT NOT NULL,
    order_date DATE NOT NULL,
    expected_delivery DATE,
    status ENUM('pending','sent','acknowledged','partially_received','received','cancelled'),
    total_amount DECIMAL(12,2),
    delivery_note_ref VARCHAR(100),
    invoice_ref VARCHAR(100),
    received_by INT,
    received_at DATETIME,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id)
);

CREATE TABLE goods_received_notes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    purchase_order_id INT NOT NULL,
    received_date DATE NOT NULL,
    received_by INT NOT NULL,
    notes TEXT,
    status ENUM('pending','verified','discrepancy','approved'),
    FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders(id)
);
```

**Integration:** Add procurement workflow to existing `api/modules/inventory/` with new `ProcurementWorkflow.php`.

---

### 11.5 Testing Infrastructure (M7)

**Short-term (add alongside Phase 1—2):**

```
tests/
├── Unit/
│   ├── Includes/
│   │   └── ApiResponseTest.php              (existing)
│   ├── Middleware/
│   │   └── AuthMiddlewareTest.php           (existing)
│   ├── Router/
│   │   └── ControllerRouterTest.php         (existing)
│   └── Services/
│       └── PolicyEngineTest.php             (existing)
├── Integration/
│   ├── activities/
│   │   ├── SportsManagerTest.php
│   │   └── SportsServiceTest.php
│   └── finance/
│       └── FinanceCrudServiceTest.php
├── API/
│   ├── ActivitiesControllerTest.php
│   ├── AuthControllerTest.php
│   └── StudentsControllerTest.php
└── bootstrap.php                             (set up test DB, seed data)
```

**Key test patterns:**
- **Unit tests:** Test PHP classes in isolation with mocked PDO
- **Integration tests:** Test against a test database (use SQLite or a dedicated MySQL test DB)
- **API tests:** Use PHPUnit's `setUp()` to create test DB records, call controller methods, assert response structure

**For JS (longer-term):**
- Add Jest or Vitest for JS testing
- Test `apiCall()`, `DataStore`, `SessionManager`
- Use Puppeteer (already installed) for E2E tests beyond the single smoke test

---

### 11.6 Implementation Order & Estimated Effort

| Phase | Item | Dependencies | Effort |
|-------|------|-------------|--------|
| **P1** | Sports DB tables (migration SQL) | None | 1 day |
| **P2** | SportsManager (CRUD) | P1 | 2-3 days |
| **P3** | SportsService (business logic) | P2 | 1-2 days |
| **P4** | Wire SportsManager/Service into ActivitiesAPI | P2, P3 | 0.5 day |
| **P5** | Controller methods in ActivitiesController | P4 | 0.5 day |
| **P6** | JS API surface + update sports.js | P5 | 1 day |
| **P7** | Add missing modals to sports.php | None | 0.5 day |
| **P8** | Migrator class + CLI | None | 2 days |
| **P9** | Convert existing schema to initial migration | P8 | 1 day |
| **P10** | Alumni module (controller + manager + pages) | None | 3-4 days |
| **P11** | Procurement tables + workflows | Existing Inventory | 3-5 days |
| **P12** | Test infrastructure + sample tests | P1-P11 | Ongoing |

**Total estimated effort:** ~15-20 days for all major gaps.

---

*End of Review*
