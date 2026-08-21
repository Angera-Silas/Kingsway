# Normalization mapping — System, Auth & Security
Part of `10_NORMALIZATION_MAPPING/`. Covers 78 tables. Base evidence: `08_PER_TABLE_BREAKDOWN/10_system_auth_security.md`, `/tmp/opencode/domains/domain_10.txt`.
Target authority: `09_NORMALIZED_TARGET_ARCHITECTURE.md` §4.15 (system/auth/RBAC/workflow/audit) + §4.1 (people/users). Tags: `[V]` verified · `[I]` inference · `[U]` owner decision.

### 1. `account_unlock_history`
- **Disposition:** MERGE
- **Normalization fault(s):** lock/unlock log duplicating the single history mechanism (audit_logs); `unlocked_by` not FK-constrained `[V]`
- **Target home(s):** `audit_logs` (§4.15 — THE history mechanism)
- **Composite / relation key:** `audit_logs(entity_type='user', entity_id=users.id, action='lock'|'unlock', acted_at=locked_date|unlocked_date)` — one unlock event per lock
- **Migration rule:** re-home each lock/unlock transition as a typed append-only `audit_logs` entry; `unlocked_by` → `actor_id` (NULL + flag where absent); keep reason text in `details`; rows preserved read-only in the migration snapshot

### 2. `api_tokens`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** none structural — token infra keyed on `token_hash` UNIQUE (hash-only storage already correct) `[V]`
- **Target home(s):** `api_tokens` (§4.15)
- **Composite / relation key:** `api_tokens.id`; `token_hash` identifies the credential (never plaintext)
- **Migration rule:** re-home rows as-is; keep `user_id → users.id`, expiry and `last_used_date` maintenance; no year context

### 3. `audit_logs`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** none — already append-only immutable audit event log; needs reshape to the target column names and must absorb the merged logs in this file `[V]`
- **Target home(s):** `audit_logs` (§4.15 — THE only history mechanism)
- **Composite / relation key:** `audit_logs(id, entity_type, entity_id, action, old_values, new_values, actor_id, acted_at)` — auditable event per object
- **Migration rule:** re-home rows; reshape legacy columns (`entity → entity_type`, `user_id → actor_id`, `created_at → acted_at`, `details → old/new_values as appropriate`); gains `entity_type`-typed entries from `audit_trail`, `communication_logs`, `business_rule_violations_log`, `config_sync_log`, `import_logs`, `rate_limit_logs`, `account_unlock_history`, `delegation_audit`, `system_permission_changes`; never mutated — append-only; retention via `system_retention_policies`

### 4. `audit_trail`
- **Disposition:** MERGE
- **Normalization fault(s):** generic row-change log (`table_name`/`record_id` + old/new values) duplicating the `audit_logs` mechanism; `user_id` not FK-constrained `[V]`
- **Target home(s):** `audit_logs` (§4.15)
- **Composite / relation key:** `audit_logs(entity_type=table_name, entity_id=record_id, action, old_values, new_values, actor_id=user_id, acted_at=created_at)`
- **Migration rule:** re-home each row as an `audit_logs` entry carrying its before/after diff; `user_id` → `actor_id` (NULL + flag where absent); preserve `ip_address`/`user_agent`; rows preserved read-only in the migration snapshot

### 5. `auth_sessions`
- **Disposition:** MERGE
- **Normalization fault(s):** second parallel session store alongside `user_sessions` — two stores, same fact (redundancy) `[V]`
- **Target home(s):** `user_sessions` (§4.15 canonical session store)
- **Composite / relation key:** `user_sessions.session_token` (UNIQUE); `(user_id, login_time)` timeline
- **Migration rule:** re-home active `auth_sessions` rows into `user_sessions` (`token → session_token`, `last_activity`/`expires_at` mapped, `payload` kept); conflict on `session_token` = dedupe, never overwrite; active sessions kept live; drop `auth_sessions`

### 6. `blocked_devices`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** `created_by` not FK-constrained `[V]`
- **Target home(s):** `blocked_devices` (§4.15 security infra)
- **Composite / relation key:** `blocked_devices.id`; `user_agent_pattern`-based device deny-list
- **Migration rule:** re-home rows as-is; declare `created_by → users.id`; no year context

### 7. `blocked_ips`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** overlaps `system_ip_rules` (two IP rule stores) `[V]`
- **Target home(s):** `blocked_ips` (§4.15) — kept as the explicit single-IP deny-list, coordinated with `system_ip_rules` (one evaluation order defined)
- **Composite / relation key:** `blocked_ips.ip_address` (UNIQUE); `expires_at` bounds the block
- **Migration rule:** re-home rows as-is; declare `created_by → users.id`; define precedence vs `system_ip_rules` `[I]`; no year context

### 8. `business_rule_violations_log`
- **Disposition:** MERGE
- **Normalization fault(s):** immutable violation log duplicating the single history mechanism; entity refs are type+id with no FKs `[V]`
- **Target home(s):** `audit_logs` (§4.15) — typed entry
- **Composite / relation key:** `audit_logs(entity_type='business_rule_violation', entity_id=entity_id, action=rule_code, actor_id=triggered_by, acted_at=created_at)`
- **Migration rule:** re-home each violation as a typed append-only `audit_logs` entry; `rule_code`/`action_attempted` → `action`/`details`; `violation_data` → `new_values`; resolve/override trail kept in `details`; rows preserved read-only in the migration snapshot

### 9. `config_sync_log`
- **Disposition:** MERGE
- **Normalization fault(s):** config-deploy log duplicating the single history mechanism (also pairs with `system_migration_history`) `[V]`
- **Target home(s):** `audit_logs` (§4.15) — typed entry
- **Composite / relation key:** `audit_logs(entity_type='config_sync', entity_id=checksum-ref, action=config_type, actor_id=synced_by, acted_at=created_at)`
- **Migration rule:** re-home each deploy as a typed append-only `audit_logs` entry; `file_path`/`records_count`/`sync_status`/`error_message` → `details`; rows preserved read-only in the migration snapshot

### 10. `dashboards`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** none structural — RBAC/menu master keyed on `name` UNIQUE `[V]`
- **Target home(s):** `dashboards` (§4.15)
- **Composite / relation key:** `dashboards.name` (UNIQUE)
- **Migration rule:** re-home rows as-is; declare FK from `role_dashboards.dashboard_id`; `route_id → routes.id` kept; no year context

### 11. `delegation_audit`
- **Disposition:** MERGE
- **Normalization fault(s):** immutable delegation log duplicating the single history mechanism; actor refs not FK-constrained `[V]`
- **Target home(s):** `audit_logs` (§4.15) — typed entry
- **Composite / relation key:** `audit_logs(entity_type='permission_delegation', entity_id=menu_item_id, action='delegate', actor_id=delegator_user_id, acted_at=created_at)`
- **Migration rule:** re-home each delegation event as a typed append-only `audit_logs` entry; `delegate_user_id`/`granted_permissions`/`note` → `new_values`/`details`; rows preserved read-only in the migration snapshot

### 12. `failed_auth_attempts`
- **Disposition:** MERGE
- **Normalization fault(s):** third parallel auth-attempt log alongside `login_attempts`/`user_login_attempts` — same fact stored three times (redundancy) `[V]`
- **Target home(s):** `login_attempts` (§4.15 canonical auth-attempt fact)
- **Composite / relation key:** `login_attempts(username=NULL, user_id=NULL, ip_address, status='failed', failure_reason=reason, created_at)` — one normalized fact: user?, ip, outcome, at
- **Migration rule:** re-home each row into `login_attempts` as a failed attempt; `user_id` unresolvable from the source → NULL + flag; `ip_address` + `created_at` preserved; rows preserved read-only in the migration snapshot

### 13. `form_permissions`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** none structural — RBAC master keyed on `form_code` UNIQUE `[V]`
- **Target home(s):** `form_permissions` (§4.15 UI/RBAC config)
- **Composite / relation key:** `form_permissions.form_code` (UNIQUE)
- **Migration rule:** re-home rows as-is; declare FK from `permission_delegations.form_permission_id`; no year context

### 14. `import_logs`
- **Disposition:** MERGE
- **Normalization fault(s):** import-run log duplicating the single history mechanism `[V]`
- **Target home(s):** `audit_logs` (§4.15) — typed entry
- **Composite / relation key:** `audit_logs(entity_type='import', entity_id=original_filename-ref, action='import', actor_id=imported_by, acted_at=created_at)`
- **Migration rule:** re-home each run as a typed append-only `audit_logs` entry; row-counts/`status`/`error_details`/`notes` → `details`; rows preserved read-only in the migration snapshot

### 15. `login_attempts`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** canonical auth-attempt fact but fragmented across three stores (`login_attempts`/`user_login_attempts`/`failed_auth_attempts`); `user_id` nullable is fine but must accept the merged failure rows `[V]`
- **Target home(s):** `login_attempts` (§4.15 canonical) — one normalized fact: `user?`, `ip`, `outcome`, `at`
- **Composite / relation key:** `(username, ip_address, created_at)` with nullable `user_id`; `status` covers success/failed (and `locked` from merged `user_login_attempts`)
- **Migration rule:** re-home rows; add `user_id` nullable + FK to `users.id` (NULL + flag for pre-registration rows); absorb `user_login_attempts` (seq 66) and `failed_auth_attempts` (seq 12) rows; extend `status` enum to cover `locked`; retention via `system_retention_policies`

### 16. `onboarding_documents`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** none structural — onboarding document fact; `file_url` storage path needs the configured uploads base `[V]`
- **Target home(s):** `onboarding_documents` (§4.11 staff onboarding staging; keyed to `staff_onboarding` per file 04)
- **Composite / relation key:** `(staff_id, onboarding_id, document_type)`
- **Migration rule:** re-home rows; keep `onboarding_id → staff_onboarding.id`, `staff_id → staff.id`; resolve `file_url` under the configured uploads path; no year context

### 17. `onboarding_tasks`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** none structural — onboarding task fact; status machine documented `[V]`
- **Target home(s):** `onboarding_tasks` (§4.11 staff onboarding staging)
- **Composite / relation key:** `(onboarding_id, sequence)` — task ordering per onboarding run
- **Migration rule:** re-home rows; keep `assigned_to → users.id`, `department_id → departments.id`, `onboarding_id → staff_onboarding.id`; keep status machine (pending/in_progress/completed/blocked/skipped); no year context

### 18. `onboarding_task_templates`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** none — reusable task definition keyed on `template_code` UNIQUE `[V]`
- **Target home(s):** `onboarding_task_templates` (§4.11)
- **Composite / relation key:** `onboarding_task_templates.template_code` (UNIQUE)
- **Migration rule:** re-home rows as-is; drives task generation for onboarding runs; no year context

### 19. `password_history`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** none structural — immutable password-rotation log, hash-only `[V]`
- **Target home(s):** `password_history` (§4.15)
- **Composite / relation key:** `(user_id, created_at)`
- **Migration rule:** re-home rows; store hashes only; retention window per password policy; keep `user_id → users.id`; no year context

### 20. `password_resets`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** reset token stored as plaintext `token` column (security); `email`-based, no FK `[V]`
- **Target home(s):** `password_resets` (§4.15)
- **Composite / relation key:** `(email, created_at)`; token expiry + `used` flag gate the reset flow
- **Migration rule:** re-home rows; store hash of the reset token, not plaintext; purge expired rows; resolve `email` through `persons.email` after the users re-home (seq 70); no year context

### 21. `permission_delegations`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** `form_permission_id` not FK-constrained; end-date must be honoured in authorization `[V]`
- **Target home(s):** `permission_delegations` (§4.15 delegation facts)
- **Composite / relation key:** `(delegated_from_user_id, delegated_to_user_id, form_permission_id, delegation_start_date)`
- **Migration rule:** re-home rows; declare `form_permission_id → form_permissions.id`; keep `delegated_from/to_user_id → users.id`; honour `delegation_end_date` expiry in authorization checks; trail via `audit_logs` (from `delegation_audit`, seq 11)

### 22. `permissions`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** none — RBAC master keyed on `code` UNIQUE, already normalized `[V]`
- **Target home(s):** `permissions` (§4.15 RBAC)
- **Composite / relation key:** `permissions.code` (UNIQUE) — the permission identity
- **Migration rule:** re-home rows, never re-ID (five FK children); never hard-delete — deprecate instead; keep the roles→role_permissions→permissions chain intact

### 23. `rate_limit_logs`
- **Disposition:** MERGE
- **Normalization fault(s):** high-volume throttling counter duplicating the single history mechanism `[V]`
- **Target home(s):** `audit_logs` (§4.15) — typed entry
- **Composite / relation key:** `audit_logs(entity_type='rate_limit', entity_id=NULL, action='rate_limited', actor_id=NULL, acted_at=request_time)` — keyed by `(ip_address, request_time)` in `details`
- **Migration rule:** re-home each throttle event as a typed append-only `audit_logs` entry (`ip_address` → `details`); enforce `system_retention_policies` (high volume) `[I]`; rows preserved read-only in the migration snapshot

### 24. `record_permissions`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** `granted_by` not FK-constrained; `expiry_date` must be honoured in evaluation `[V]`
- **Target home(s):** `record_permissions` (§4.15 UI/RBAC config — row-level authorization)
- **Composite / relation key:** `(table_name, record_id, user_id, permission_type, granted_date)`
- **Migration rule:** re-home rows; declare `granted_by → users.id`; keep `user_id → users.id`, `role_id → roles.id`; enforce `expiry_date` in permission evaluation; no year context

### 25. `refresh_tokens`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** `user_id` not FK-constrained `[V]`
- **Target home(s):** `refresh_tokens` (§4.15)
- **Composite / relation key:** `refresh_tokens.token` (UNIQUE); revocation + expiry
- **Migration rule:** re-home rows; declare `user_id → users.id`; rotate on refresh; revoke on user deactivation; no year context

### 26. `role_dashboards`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** pure junction with `UNIQUE(role_id, dashboard_id)` already declared; FKs not declared `[V]`
- **Target home(s):** `role_dashboards` (§4.15 UI/RBAC config)
- **Composite / relation key:** `(role_id, dashboard_id)` — the UNIQUE pair
- **Migration rule:** re-home rows; declare FKs to `roles.id` and `dashboards.id`; twins `tmp_backup_role_dashboards` retired (file 11); no year context

### 27. `role_delegations`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** role refs are columns with no FK constraints `[V]`
- **Target home(s):** `role_delegations` (§4.15 delegation facts)
- **Composite / relation key:** `(delegator_role_id, delegate_role_id)` while `active`
- **Migration rule:** re-home rows; declare FKs to `roles.id`; effective role-resolution for delegates; no year context

### 28. `role_permissions`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** pure junction lacking `UNIQUE(role_id, permission_id)` `[V]`
- **Target home(s):** `role_permissions` (§4.15 RBAC) — `(role_id, permission_id)` PK
- **Composite / relation key:** `(role_id, permission_id)`
- **Migration rule:** re-home rows; add `UNIQUE(role_id, permission_id)`; keep FKs to `roles.id` and `permissions.id`; never re-ID the linked masters

### 29. `role_routes`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** junction lacking `UNIQUE(role_id, route_id)` `[V]`
- **Target home(s):** `role_routes` (§4.15 route access facts)
- **Composite / relation key:** `(role_id, route_id)`
- **Migration rule:** re-home rows; add `UNIQUE(role_id, route_id)`; keep `role_id → roles.id`, `route_id → routes.id`; no year context

### 30. `role_sidebar_menus`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** junction lacking `UNIQUE(role_id, menu_item_id)` `[V]`
- **Target home(s):** `role_sidebar_menus` (§4.15 UI/RBAC config)
- **Composite / relation key:** `(role_id, menu_item_id)`
- **Migration rule:** re-home rows; add `UNIQUE(role_id, menu_item_id)`; keep `role_id → roles.id`, `menu_item_id → sidebar_menu_items.id`; twins `dropped__bak_role_sidebar_menus` retired (file 11)

### 31. `roles`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** `user_count` is a stored derived value (countable from `user_roles`, §1.7); target adds `code UNIQUE` alongside legacy `name` UNIQUE `[V]`
- **Target home(s):** `roles` (§4.15 RBAC) — `(id, code UNIQUE, name)`
- **Composite / relation key:** `roles.name` (UNIQUE) / target `roles.code` (UNIQUE)
- **Migration rule:** re-home rows, never re-ID (six+ FK children); drop `user_count` (derive as a view over `user_roles`); populate `code` from `name` where absent (deterministic, non-overwriting); keep `scope`/`is_system`/`is_active`

### 32. `schema_discovery_cache`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** none — internal schema-introspection cache, disposable/rebuildable `[V]`
- **Target home(s):** `schema_discovery_cache` (§4.15 SYS cache)
- **Composite / relation key:** `schema_discovery_cache.lookup_key` — cache key, not a business key
- **Migration rule:** re-home as rebuildable cache; invalidate on schema changes (including this redesign's renames); no year context

### 33. `school_configuration`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** none structural — single-source school profile; must not be duplicated in `school_settings` `[V]`
- **Target home(s):** `school_configuration` (§4.15 school config master)
- **Composite / relation key:** `school_configuration.id` (single active row implied by `is_active`)
- **Migration rule:** re-home rows as-is; keep `updated_by → users.id`; partition concerns vs `school_settings` keys; no year context

### 34. `school_history`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** `year` is a display string, not a FK to `academic_years` — must not be treated as the year context spine `[V]`
- **Target home(s):** `school_history` (§4.15 school config/content master) — **audit, not archive**: these are public milestones, preserved as content and audited, not a dropped-data archive
- **Composite / relation key:** `school_history.id`; `display_order` sequences milestones
- **Migration rule:** re-home rows as-is; keep `year` as display-only text; changes to milestones go through `audit_logs`; no year spine linkage invented

### 35. `school_levels`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** none — level lookup keyed on `code` UNIQUE `[V]`
- **Target home(s):** `school_levels` (§4.15 school config master)
- **Composite / relation key:** `school_levels.code` (UNIQUE) — Playgroup/PP1/PP2/G1..G9
- **Migration rule:** re-home rows, never re-ID (referenced across finance/curriculum domains); no year context

### 36. `school_programs`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** none structural — public content (`level_range` is descriptive text) `[V]`
- **Target home(s):** `school_programs` (§4.15 school config master)
- **Composite / relation key:** `school_programs.id`; `display_order` + `is_active`
- **Migration rule:** re-home rows as-is; no year context

### 37. `school_settings`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** key→value store that must not overlap `school_configuration` (profile) `[V]`
- **Target home(s):** `school_settings` (§4.15)
- **Composite / relation key:** `school_settings.setting_key` (UNIQUE)
- **Migration rule:** re-home rows as-is; partition keys by concern vs `school_configuration`; maintain `updated_at`; no year context

### 38. `school_values`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** none structural — public content `[V]`
- **Target home(s):** `school_values` (§4.15 school config master)
- **Composite / relation key:** `school_values.id`; `display_order` + `is_active`
- **Migration rule:** re-home rows as-is; no year context

### 39. `sidebar_menu_items`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** none — RBAC/menu master keyed on `name` UNIQUE, `parent_id` self-FK builds the tree `[V]`
- **Target home(s):** `sidebar_menu_items` (§4.15 UI/RBAC config)
- **Composite / relation key:** `sidebar_menu_items.name` (UNIQUE); `parent_id` self-reference
- **Migration rule:** re-home rows, never re-ID (menu assignment depends on it); keep `route_id → routes.id`; twins `dropped__bak_role_sidebar_menus` retired (file 11)

### 40. `student_permissions`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** `student_id`/`permission_type_id`/`parent_id`/`approved_by` are columns with no FK constraints `[V]`
- **Target home(s):** `student_permissions` (boarding/welfare — §4.8-adjacent fact; kept in the students domain per file 05)
- **Composite / relation key:** `(student_id, start_date, start_time)` — one outing window
- **Migration rule:** re-home rows; declare FKs to `students`, `student_permission_types`, `parents`, `users`; term/year derivable from dates — record `academic_year_id` if term reporting is needed (NULL + flag) `[I]`

### 41. `system_access_policies`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** overlaps `system_policies` (two policy stores — one evaluation order must be defined) `[V]`
- **Target home(s):** `system_access_policies` (§4.15 system_* config)
- **Composite / relation key:** `system_access_policies.policy_key` (UNIQUE)
- **Migration rule:** re-home rows as-is; keep `policy_id` linkage from `system_policy_violations`; define evaluation precedence vs `system_policies` `[I]`; no year context

### 42. `system_alerts`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** `resolved_by` not FK-constrained `[V]`
- **Target home(s):** `system_alerts` (§4.15 system_* runtime)
- **Composite / relation key:** `system_alerts.id` + `created_at`/`resolved_at` lifecycle
- **Migration rule:** re-home rows as-is; declare `resolved_by → users.id`; no year context

### 43. `system_api_metrics`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** none structural — immutable API telemetry `[V]`
- **Target home(s):** `system_api_metrics` (§4.15 system_* runtime)
- **Composite / relation key:** `(endpoint, http_method, created_at)` — request row
- **Migration rule:** re-home rows; keep `user_id → users.id`; high-volume — aggregate/prune per `system_retention_policies`; no year context

### 44. `system_background_jobs`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** none structural — job infra with retry/status machine `[V]`
- **Target home(s):** `system_background_jobs` (§4.15)
- **Composite / relation key:** `system_background_jobs.id` + `job_type`
- **Migration rule:** re-home rows as-is; idempotency + retry policy; queue worker drives `outbound_messages`/`sms_communications` delivery; keep `created_by → users.id`; no year context

### 45. `system_backups`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** none structural — backup infra with checksum `[V]`
- **Target home(s):** `system_backups` (§4.15)
- **Composite / relation key:** `system_backups.id` + `filename`/`checksum`
- **Migration rule:** re-home rows as-is; never store in web root; verify restore tests; keep `created_by → users.id`; no year context

### 46. `system_domain_isolation_rules`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** none structural — policy infra `[V]`
- **Target home(s):** `system_domain_isolation_rules` (§4.15)
- **Composite / relation key:** `system_domain_isolation_rules.id` + `domain_key`
- **Migration rule:** re-home rows as-is; no year context

### 47. `system_error_logs`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** none structural — immutable error log (generic-to-client rule already applied) `[V]`
- **Target home(s):** `system_error_logs` (§4.15)
- **Composite / relation key:** `(error_type, created_at)`
- **Migration rule:** re-home rows; retention via `system_retention_policies`; keep `user_id → users.id`; no year context

### 48. `system_events`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** none structural — event bus `[V]`
- **Target home(s):** `system_events` (§4.15)
- **Composite / relation key:** `(event_type, created_at)`
- **Migration rule:** re-home rows as-is; coordinate webhook dispatch with `system_webhooks`; no year context

### 49. `system_feature_flags`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** none — feature infra keyed on `key_name` UNIQUE `[V]`
- **Target home(s):** `system_feature_flags` (§4.15)
- **Composite / relation key:** `system_feature_flags.key_name` (UNIQUE)
- **Migration rule:** re-home rows as-is; no year context

### 50. `system_ip_rules`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** overlaps `blocked_ips` (two IP rule stores) `[V]`
- **Target home(s):** `system_ip_rules` (§4.15) — kept as the canonical allow/deny CIDR store, coordinated with `blocked_ips`
- **Composite / relation key:** `system_ip_rules.id` + `cidr`
- **Migration rule:** re-home rows as-is; define evaluation precedence vs `blocked_ips` `[I]`; no year context

### 51. `system_maintenance_windows`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** none structural — ops infra `[V]`
- **Target home(s):** `system_maintenance_windows` (§4.15)
- **Composite / relation key:** `system_maintenance_windows.id` + `starts_at`/`ends_at`
- **Migration rule:** re-home rows as-is; maintenance-mode gating; no year context

### 52. `system_migration_history`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** none — migration ledger keyed on `migration_name` UNIQUE `[V]`
- **Target home(s):** `system_migration_history` (§4.15)
- **Composite / relation key:** `system_migration_history.migration_name` (UNIQUE) + `checksum`
- **Migration rule:** keep; drive all redesign migrations (including these three mapping files) through this ledger `[I]`; keep `executed_by → users.id`

### 53. `system_modules`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** none — module infra keyed on `module_key` UNIQUE `[V]`
- **Target home(s):** `system_modules` (§4.15)
- **Composite / relation key:** `system_modules.module_key` (UNIQUE)
- **Migration rule:** re-home rows as-is; no year context

### 54. `system_permission_changes`
- **Disposition:** MERGE
- **Normalization fault(s):** immutable RBAC change log duplicating the single history mechanism `[V]`
- **Target home(s):** `audit_logs` (§4.15) — typed audit entry
- **Composite / relation key:** `audit_logs(entity_type=target_type, entity_id=target_id, action=change_type, actor_id=actor_user_id, acted_at=created_at)`
- **Migration rule:** re-home each RBAC change as a typed append-only `audit_logs` entry; `permission_id` and `details_json` → `new_values`/`details`; rows preserved read-only in the migration snapshot

### 55. `system_policies`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** `created_by` not FK-constrained; overlaps `system_access_policies` (evaluation order) `[V]`
- **Target home(s):** `system_policies` (§4.15)
- **Composite / relation key:** `system_policies.name` (UNIQUE); `priority` + effective window
- **Migration rule:** re-home rows; declare `created_by → users.id`; define evaluation precedence vs `system_access_policies` `[I]`; no year context

### 56. `system_policy_violations`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** none structural — immutable violation log keyed to `system_access_policies` `[V]`
- **Target home(s):** `system_policy_violations` (§4.15)
- **Composite / relation key:** `(policy_id, user_id, created_at)`
- **Migration rule:** re-home rows; keep `policy_id → system_access_policies.id`, `user_id`/`resolved_by → users.id`; retention via `system_retention_policies`; no year context

### 57. `system_rate_limit_rules`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** none — throttling infra keyed on `rule_key` UNIQUE `[V]`
- **Target home(s):** `system_rate_limit_rules` (§4.15)
- **Composite / relation key:** `system_rate_limit_rules.rule_key` (UNIQUE)
- **Migration rule:** re-home rows as-is; enforces `rate_limit_logs`/`audit_logs` throttling entries; no year context

### 58. `system_retention_policies`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** none — lifecycle infra keyed on `resource_key` UNIQUE `[V]`
- **Target home(s):** `system_retention_policies` (§4.15)
- **Composite / relation key:** `system_retention_policies.resource_key` (UNIQUE)
- **Migration rule:** re-home rows as-is; wire the HIST/SYS tables in this file (incl. merged `audit_logs` entries) to these policies; never delete before archive

### 59. `system_route_access_rules`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** none structural — route policy infra `[V]`
- **Target home(s):** `system_route_access_rules` (§4.15)
- **Composite / relation key:** `(route_id, http_method, permission_id)`
- **Migration rule:** re-home rows; keep `route_id → routes.id`, `permission_id → permissions.id`, `created_by`/`updated_by → users.id`; layers with `role_routes`/`user_routes`; no year context

### 60. `system_security_incidents`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** none structural — incident infra `[V]`
- **Target home(s): `system_security_incidents` (§4.15)
- **Composite / relation key:** `system_security_incidents.id` + status lifecycle
- **Migration rule:** re-home rows; keep `assigned_to`/`created_by`/`updated_by → users.id`; no year context

### 61. `system_time_bound_access`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** none structural — temporary-grant infra `[V]`
- **Target home(s):** `system_time_bound_access` (§4.15)
- **Composite / relation key:** `(user_id, permission_id/role_id, starts_at, expires_at)` — temporary grant window
- **Migration rule:** re-home rows; keep all five user/role/permission FKs; enforce `expires_at` in authorization; revocation trail via `audit_logs`; no year context

### 62. `system_webhooks`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** `secret_hash` already hash-only (correct); delivery retry should ride `system_background_jobs` `[V]`
- **Target home(s):** `system_webhooks` (§4.15)
- **Composite / relation key:** `system_webhooks.id` + `target_url`
- **Migration rule:** re-home rows; never store plaintext secrets — hash only; delivery retry via `system_background_jobs`; keep `created_by`/`updated_by → users.id`; no year context

### 63. `user_2fa_backup_codes`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** `user_id` not FK-constrained `[V]`
- **Target home(s):** `user_2fa_backup_codes` (§4.15)
- **Composite / relation key:** `(user_id, code_hash)`; `used_at` marks one-time consumption
- **Migration rule:** re-home rows; hash codes only; purge used/expired; declare `user_id → users.id`; no year context

### 64. `user_2fa_otp_sessions`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** `user_id` not FK-constrained `[V]`
- **Target home(s):** `user_2fa_otp_sessions` (§4.15)
- **Composite / relation key:** `(user_id, otp_type, created_at)` — one OTP issuance
- **Migration rule:** re-home rows; short TTL + attempt limits; declare `user_id → users.id`; no year context

### 65. `user_invitations`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** `user_id`/`staff_id`/`created_by` are columns with no FK constraints `[V]`
- **Target home(s):** `user_invitations` (§4.15 provisioning)
- **Composite / relation key:** `user_invitations.token_hash` (UNIQUE); status lifecycle
- **Migration rule:** re-home rows; declare FKs to `users.id`/`staff.id`; hash tokens; no year context

### 66. `user_login_attempts`
- **Disposition:** MERGE
- **Normalization fault(s):** third parallel auth-attempt log (redundancy with `login_attempts`/`failed_auth_attempts`) `[V]`
- **Target home(s):** `login_attempts` (§4.15 canonical)
- **Composite / relation key:** `login_attempts(username, user_id=NULL, ip_address, status, failure_reason, created_at)` — `attempt_status` success/failed/locked → `status`
- **Migration rule:** re-home each row into `login_attempts`; `attempt_time` → `created_at`; `attempt_status` mapped to the extended `status` enum (add `locked`); `user_id` unresolvable → NULL + flag; rows preserved read-only in the migration snapshot

### 67. `user_permissions`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** `user_id`/`permission_id`/`granted_by` are columns with no FK constraints; `expires_at` must be honoured `[V]`
- **Target home(s):** `user_permissions` (§4.15 grant/deny/override facts)
- **Composite / relation key:** `(user_id, permission_id, permission_type, granted_at)` — one override grant
- **Migration rule:** re-home rows; declare FKs to `users.id`/`permissions.id`; honour `expires_at` in evaluation; keeps `permission_type` (grant/deny/override) + reason + grantor; no year context

### 68. `user_roles`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** pure junction lacking `UNIQUE(user_id, role_id)`; FKs not declared `[V]`
- **Target home(s):** `user_roles` (§4.15 RBAC) — `(user_id, role_id)` PK
- **Composite / relation key:** `(user_id, role_id)`
- **Migration rule:** re-home rows; add `UNIQUE(user_id, role_id)`; declare FKs to `users.id`/`roles.id`; absorbs the legacy `users.role_id` single-role column (each non-NULL `users.role_id` becomes one `user_roles` row); no year context

### 69. `user_routes`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** `user_id`/`route_id`/`granted_by` are columns with no FK constraints; `expires_at` must be honoured `[V]`
- **Target home(s):** `user_routes` (§4.15 route access facts)
- **Composite / relation key:** `(user_id, route_id, granted_at)` — one route override
- **Migration rule:** re-home rows; declare FKs to `users.id`/`routes.id`; honour `expires_at`; layers with `role_routes` and `system_route_access_rules`; no year context

### 70. `users`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** person attributes (`first_name`/`last_name`/`email`) embedded on the account — duplicated by the shared `persons` base; `role_id` single-role column duplicates the `user_roles` junction; ~98 FK children bind to this PK `[V]`
- **Target home(s):** `users` (§4.1 account) + `persons` (shared identity base)
- **Composite / relation key:** `users.id`; `person_id` UNIQUE FK → `persons.id`; `username` UNIQUE kept
- **Migration rule:** re-home the account; add `person_id → persons.id` (one `persons` row created per distinct person from `first_name`/`last_name`/`email`/phone; unresolved = NULL + flag); move `first_name`/`last_name`/`email` to `persons`; keep `username`, `password → password_hash`, `status`, 2FA columns, lockout/expiry columns; resolve legacy `role_id` → `user_roles` rows; never re-ID (≈98 FK children); `password_resets`/`login_attempts` resolve identity through `persons.email` `[I]`

### 71. `user_sessions`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** canonical session store but duplicated by `auth_sessions`; `user_id` not FK-constrained `[V]`
- **Target home(s): `user_sessions` (§4.15 canonical session store)
- **Composite / relation key:** `user_sessions.session_token` (UNIQUE); login/logout timeline
- **Migration rule:** re-home rows; declare `user_id → users.id`; absorbs `auth_sessions` rows (seq 5); session invalidation on user lock/suspend; no year context

### 72. `workflow_definitions`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** none — workflow engine config keyed on `code` UNIQUE `[V]`
- **Target home(s):** `workflow_definitions` (§4.15 workflow engine)
- **Composite / relation key:** `workflow_definitions.code` (UNIQUE)
- **Migration rule:** re-home rows; declare FK from `workflow_stages.workflow_id`; absorbs communication workflows (file 09 seq 10 re-homed into `workflow_instances` referencing this engine) `[I]`

### 73. `workflow_history`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** `workflow_id`/`performed_by` are columns with no FK constraints; kept as the definition-level log distinct from `workflow_stage_history` (per-instance) `[V]`
- **Target home(s):** `workflow_history` (§4.15 workflow engine)
- **Composite / relation key:** `(workflow_id, stage, created_at)`
- **Migration rule:** re-home rows; declare FKs to `workflow_definitions.id`/`users.id`; retention via `system_retention_policies`; no year context

### 74. `workflow_instances`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** `workflow_id`/`started_by` are columns with no FK constraints; becomes the run fact for communication workflows too `[V]`
- **Target home(s): `workflow_instances` (§4.15 workflow engine)
- **Composite / relation key:** `(workflow_id, reference_type, reference_id)` — one run per target object
- **Migration rule:** re-home rows; declare FKs to `workflow_definitions.id`/`users.id`; absorb `communication_workflow_instances` rows as `reference_type='communication'` (file 09 seq 10); notifications + stage history logically cascade; no year context

### 75. `workflow_notifications`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** `instance_id`/`user_id` are columns with no FK constraints `[V]`
- **Target home(s): `workflow_notifications` (§4.15 workflow engine)
- **Composite / relation key:** `(instance_id, notification_type, created_at)`
- **Migration rule:** re-home rows; declare FKs to `workflow_instances.id`/`users.id`; no year context

### 76. `workflow_stage_history`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** `instance_id`/`processed_by` are columns with no FK constraints `[V]`
- **Target home(s): `workflow_stage_history` (§4.15 workflow engine)
- **Composite / relation key:** `(instance_id, stage_code, processed_at)` — transition record
- **Migration rule:** re-home rows; declare FK to `workflow_instances.id`; retention via `system_retention_policies`; receives stage transitions from communication workflows (file 09 seq 10)

### 77. `workflow_stage_permissions`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** junction lacking `UNIQUE(workflow_stage_id, role_id, permission_id)` `[V]`
- **Target home(s): `workflow_stage_permissions` (§4.15 workflow engine)
- **Composite / relation key:** `(workflow_stage_id, role_id, permission_id)`
- **Migration rule:** re-home rows; add `UNIQUE(workflow_stage_id, role_id, permission_id)`; keep FKs to `workflow_stages.id`/`roles.id`/`permissions.id`; no year context

### 78. `workflow_stages`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** `workflow_id` is a column with no FK constraint `[V]`
- **Target home(s): `workflow_stages` (§4.15 workflow engine)
- **Composite / relation key:** `(workflow_id, code)` — stage identity within a workflow
- **Migration rule:** re-home rows; declare FK to `workflow_definitions.id`; `workflow_stage_permissions` cascades; instance stage tracking via `workflow_instances`/`workflow_stage_history`; no year context

## Merged in this file
- Into `audit_logs`: `audit_trail`, `business_rule_violations_log`, `config_sync_log`, `delegation_audit`, `import_logs`, `rate_limit_logs`, `account_unlock_history`, `system_permission_changes`
- Into `user_sessions`: `auth_sessions`
- Into `login_attempts`: `user_login_attempts`, `failed_auth_attempts`

## Retired in this file
- (none)
