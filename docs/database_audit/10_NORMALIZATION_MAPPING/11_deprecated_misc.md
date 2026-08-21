# Normalization mapping — Deprecated / Backup / Misc
Part of `10_NORMALIZATION_MAPPING/`. Covers 6 tables. Base evidence: `08_PER_TABLE_BREAKDOWN/11_deprecated_misc.md`, `/tmp/opencode/domains/domain_11.txt`.
Target authority: `09_NORMALIZED_TARGET_ARCHITECTURE.md` §4.15 (RBAC/audit) + §4.6 (finance). These are migration snapshots only — no ongoing archive mechanism, no live source. Tags: `[V]` verified · `[I]` inference · `[U]` owner decision.

### 1. `dropped__bak_permissions`
- **Disposition:** RETIRE
- **Normalization fault(s):** backup copy of the old RBAC permission store; mirrors live `permissions` (`code` UNIQUE, entity/action/module) — a redundant snapshot, not a live fact `[V]`
- **Target home(s):** none — live `permissions` (§4.15) supersedes it
- **Composite / relation key:** `dropped__bak_permissions.id` + `code` — snapshot of a prior permissions set
- **Migration rule:** archive dump to the one-time migration snapshot; diff on `code` against live `permissions` to confirm supersession; quarantine out of the schema; never restore over live RBAC; no data rows needed in the target

### 2. `dropped__bak_role_permissions`
- **Disposition:** RETIRE
- **Normalization fault(s):** backup copy of the old role→permission junction; mirrors live `role_permissions` (`role_id`, `permission_id`, `created_at`) — redundant snapshot `[V]`
- **Target home(s):** none — live `role_permissions` (§4.15) supersedes it
- **Composite / relation key:** `(role_id, permission_id)` — legacy grant snapshot
- **Migration rule:** archive dump; diff against live `role_permissions`; quarantine out of the schema; never merge blindly into live RBAC; no data rows needed in the target

### 3. `dropped__bak_role_sidebar_menus`
- **Disposition:** RETIRE
- **Normalization fault(s):** backup copy of the old role→menu junction; mirrors live `role_sidebar_menus` (`role_id`, `menu_item_id`, `is_default`, `custom_order`, `created_at`) — redundant snapshot `[V]`
- **Target home(s):** none — live `role_sidebar_menus` (§4.15) supersedes it
- **Composite / relation key:** `(role_id, menu_item_id)` — legacy menu-assignment snapshot
- **Migration rule:** archive dump; diff against live `role_sidebar_menus`; quarantine out of the schema; no data rows needed in the target

### 4. `dropped__bak_routes`
- **Disposition:** RETIRE
- **Normalization fault(s):** backup copy of the old RBAC route registry; mirrors live `routes` (`name` UNIQUE, url, domain SYSTEM/SCHOOL, module, controller, action, is_active) — redundant snapshot `[V]`
- **Target home(s):** none — live `routes`/`routes_registry` (§4.15) supersedes it (RBAC registry — not `transport_routes`)
- **Composite / relation key:** `dropped__bak_routes.name` + `url` — legacy route snapshot
- **Migration rule:** archive dump; diff against live `routes`; quarantine out of the schema; no data rows needed in the target

### 5. `student_fee_obligations_backup_20260112`
- **Disposition:** RETIRE
- **Normalization fault(s):** dated backup snapshot of `student_fee_obligations` (2026-01-12); carries the same finance payload (`student_id`, `academic_year`, `term_id`, `fee_structure_detail_id`, amount due/paid/waived/balance, status, due_date, carry-over balances, sponsorship) with **no PK declared** — a copy, not a live source; also embeds stored balances (`balance`/`year_balance`/`term_balance`) that are derived values in the target `[V]`
- **Target home(s):** none directly — live finance target per §4.6 (`student_fee_obligations` + `academic_year_fee_schedules`); this snapshot is a forensic copy only
- **Composite / relation key:** `(student_id, academic_year, term_id)` — matches the live obligation composite
- **Migration rule:** archive dump (preserves the 2026-01-12 obligation state for reconciliation); quarantine out of the schema; must never be re-fed into payment-allocation logic; no data rows needed in the target (the live finance migration, per file 03 mapping, is authoritative)

### 6. `tmp_backup_role_dashboards`
- **Disposition:** RETIRE
- **Normalization fault(s):** temporary backup copy of `role_dashboards` (`role_id`, `dashboard_id`, `is_primary`, `display_order`, `created_at`); **no PK declared** — redundant snapshot `[V]`
- **Target home(s):** none — live `role_dashboards` (§4.15) supersedes it
- **Composite / relation key:** `(role_id, dashboard_id)` — matches live `role_dashboards` UNIQUE pair
- **Migration rule:** archive dump; diff against live `role_dashboards`; quarantine out of the schema; live table keeps the RBAC menu linkage; no data rows needed in the target

## Retired in this file
- `dropped__bak_permissions`, `dropped__bak_role_permissions`, `dropped__bak_role_sidebar_menus`, `dropped__bak_routes`, `student_fee_obligations_backup_20260112`, `tmp_backup_role_dashboards`
