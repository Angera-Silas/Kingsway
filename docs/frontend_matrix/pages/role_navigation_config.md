# role_navigation_config.php

- **File**: `pages/role_navigation_config.php`
- **Controller**: `system/system_admin_console.js`
- **Roles**: `System Administrator`
- **Sidebar item(s)**: `1020`, `100099`

## Init / auth

- `AuthContext.ready()` awaited: `N`
- Permission/RBAC guard: `N`
- Raw `fetch`/`XMLHttpRequest`/`axios`: `0`

## API methods used

| API method | Payload keys (sent) | Flags | Endpoint | Handler | Status |
|---|---|---|---|---|---|
| `system.getSchoolConfig` | — | — | GET /system/school-config | System::getSchoolConfig | ok |
| `system.updateSchoolConfig` | — | — | POST /system/school-config | System::updateSchoolConfig | ok |
| `system.getBackups` | — | — | GET /system/backups | System::getBackups | ok |
| `system.createBackup` | — | — | POST /system/backups | System::createBackup | ok |
| `system.deleteBackup` | — | — | DELETE /system/backups | System::deleteBackup | ok |
| `system.getFeatureFlags` | — | — | GET /system/feature-flags | System::getFeatureFlags | ok |
| `system.updateFeatureFlags` | — | — | PUT /system/feature-flags | System::updateFeatureFlags | ok |
| `system.getModuleEnablement` | — | — | GET /system/module-enablement | System::getModuleEnablement | ok |
| `system.updateModuleEnablement` | — | — | PUT /system/module-enablement | System::updateModuleEnablement | ok |
| `system.getDataRetention` | — | — | GET /system/data-retention | System::getDataRetention | ok |
| `system.updateDataRetention` | — | — | PUT /system/data-retention | System::updateDataRetention | ok |
| `system.getDomainIsolation` | — | — | GET /system/domain-isolation | System::getDomainIsolation | ok |
| `system.updateDomainIsolation` | — | — | PUT /system/domain-isolation | System::updateDomainIsolation | ok |
| `system.getTimeBoundAccess` | — | — | GET /system/time-bound-access | System::getTimeBoundAccess | ok |
| `system.updateTimeBoundAccess` | — | — | PUT /system/time-bound-access | System::updateTimeBoundAccess | ok |
| `system.getRoutes` | — | — | GET /system/routes | System::getRoutes | ok |
| `system.updateRoute` | — | — | PUT /system/routes | System::updateRoute | ok |
| `system.createRoute` | — | — | POST /system/routes | System::createRoute | ok |
| `system.deleteRoute` | — | — | DELETE /system/routes | System::deleteRoute | ok |
| `system.getRouteAccessRules` | — | — | GET /system/route-access-rules | System::getRouteAccessRules | ok |
| `system.updateRouteAccessRule` | — | — | PUT /system/route-access-rules | System::updateRouteAccessRule | ok |
| `system.createRouteAccessRule` | — | — | POST /system/route-access-rules | System::createRouteAccessRule | ok |
| `system.deleteRouteAccessRule` | — | — | DELETE /system/route-access-rules | System::deleteRouteAccessRule | ok |
| `system.getRoleNavigation` | — | — | GET /system/role-navigation | System::getRoleNavigation | ok |
| `system.getPermissionPolicies` | — | — | GET /system/permission-policies | System::getPermissionPolicies | ok |
| `system.updatePermissionPolicy` | — | — | PUT /system/permission-policies | System::updatePermissionPolicy | ok |
| `system.createPermissionPolicy` | — | — | POST /system/permission-policies | System::createPermissionPolicy | ok |
| `system.deletePermissionPolicy` | — | — | DELETE /system/permission-policies | System::deletePermissionPolicy | ok |
| `system.getSidebarMenus` | — | — | GET /system/sidebar-menus | System::getSidebarMenus | ok |
| `system.updateSidebarMenu` | — | — | PUT /system/sidebar-menus | System::updateSidebarMenu | ok |
| `system.createSidebarMenu` | — | — | POST /system/sidebar-menus | System::createSidebarMenu | ok |
| `system.deleteSidebarMenu` | — | — | DELETE /system/sidebar-menus | System::deleteSidebarMenu | ok |
| `system.getDashboards` | — | — | GET /system/dashboards | System::getDashboards | ok |
| `system.updateDashboard` | — | — | PUT /system/dashboards | System::updateDashboard | ok |
| `system.createDashboard` | — | — | POST /system/dashboards | System::createDashboard | ok |
| `system.deleteDashboard` | — | — | DELETE /system/dashboards | System::deleteDashboard | ok |
| `system.getWidgets` | — | — | GET /system/widgets | System::getWidgets | ok |
| `system.updateWidget` | — | — | PUT /system/widgets | System::updateWidget | ok |
| `system.createWidget` | — | — | POST /system/widgets | System::createWidget | ok |
| `system.deleteWidget` | — | — | DELETE /system/widgets | System::deleteWidget | ok |
| `system.getWebhookRegistry` | — | — | GET /system/webhook-registry | System::getWebhookRegistry | ok |
| `system.updateWebhook` | — | — | PUT /system/webhook-registry | System::updateWebhook | ok |
| `system.createWebhook` | — | — | POST /system/webhook-registry | System::createWebhook | ok |
| `system.deleteWebhook` | — | — | DELETE /system/webhook-registry | System::deleteWebhook | ok |
| `system.getSecurityIncidents` | — | — | GET /system/security-incidents | System::getSecurityIncidents | ok |
| `system.getPolicyViolations` | — | — | GET /system/policy-violations | System::getPolicyViolations | ok |
| `system.getMigrations` | — | — | GET /system/migrations | System::getMigrations | ok |
| `system.runMigration` | — | — | POST /system/migrations | System::runMigration | ok |
| `system.getHealth` | — | — | GET /system/health | System::getHealth | ok |
| `system.getDiagnostics` | — | — | GET /system/diagnostics | System::getDiagnostics | ok |
| `system.getActivityAuditLogs` | — | — | GET /system/activity-audit-logs | System::getActivityAuditLogs | ok |
| `system.getErrorLogs` | — | — | GET /system/error-logs | System::getErrorLogs | ok |
| `system.getApiMetrics` | — | — | GET /system/api-metrics | System::getApiMetrics | ok |
| `system.getRateLimiting` | — | — | GET /system/rate-limiting | System::getRateLimiting | ok |
| `system.getPermissionChanges` | — | — | GET /system/permission-changes | System::getPermissionChanges | ok |
| `system.getBackgroundJobs` | — | — | GET /system/background-jobs | System::getBackgroundJobs | ok |
| `system.getJobInspector` | — | — | GET /system/job-inspector | System::getJobInspector | ok |

## Backend params (expected)

| Handler | Input keys ($data/$_POST/$_GET) | Path id | Data sources (views/procs/tables) |
|---|---|---|---|
| System::getSchoolConfig | — | `id` | school_configuration |
| System::getBackups | — | — | — |
| System::getFeatureFlags | — | — | — |
| System::getModuleEnablement | — | — | — |
| System::getDataRetention | — | — | — |
| System::getDomainIsolation | — | — | — |
| System::getTimeBoundAccess | — | — | — |
| System::getRoutes | — | `id` | — |
| System::getRouteAccessRules | — | `id` | — |
| System::getRoleNavigation | — | — | — |
| System::getPermissionPolicies | — | — | — |
| System::getSidebarMenus | — | — | sidebar_menu_items |
| System::getDashboards | — | — | dashboards, role_dashboards, roles, routes_registry |
| System::getWidgets | — | — | widgets |
| System::getWebhookRegistry | — | — | — |
| System::getSecurityIncidents | — | — | audit_logs |
| System::getPolicyViolations | — | — | audit_logs |
| System::getMigrations | — | — | — |
| System::getHealth | — | — | — |
| System::getDiagnostics | — | — | — |
| System::getActivityAuditLogs | — | — | audit_logs, users |
| System::getErrorLogs | — | `id` | — |
| System::getApiMetrics | — | `id` | — |
| System::getRateLimiting | — | — | — |
| System::getPermissionChanges | — | — | audit_logs |
| System::getBackgroundJobs | — | — | — |
| System::getJobInspector | — | `id` | — |

## Response shape (data keys consumed)

_(no post-await `.prop` consumption detected)_

## UI / security

- `innerHTML`/`insertAdjacentHTML` assignments: `5` (with interpolation: `0`)
- `escapeHtml()` calls: `15` — XSS check: `PASS`
- Bootstrap modal usage: `1`
- Payload/backend param match: `NA`
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
| Response: unwrapped `data` handled | ⚠️ | heuristic |
| UI: Bootstrap + `escapeHtml` | ✅ | machine |
| Responsive @375/768/1200px | ⚠️ | **manual sign-off required** |
