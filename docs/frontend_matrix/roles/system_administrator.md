# System Administrator — Role Matrix

| Field | Value |
|---|---|
| role_id | 2 |
| scope | system |
| is_system | 1 |
| is_active | 1 |
| users | 1 |
| sidebar items | 102 |
| route-level auth (role_routes) | 239 |
| dashboards | 1 |
| distinct endpoints | 168 |
| unresolved endpoints | 0 |

## Dashboards

| Key | Display | Component | Endpoints | Unresolved |
|---|---|---|---|---|
| system_administrator_dashboard | System Administrator Dashboard | components/dashboards/system_administrator_dashboard.php | 6 | 0 |

## Sidebar items

| id | Label | Type | Parent | URL | Route | Page | Controllers | Status |
|---|---|---|---|---|---|---|---|---|
| 1001 | Dashboard | sidebar |  | system_administrator_dashboard | system_administrator_dashboard | components/dashboards/system_administrator_dashboard.php | system_administrator_dashboard.js | dashboard |
| 1011 | User Accounts | sidebar | Identity & Access | manage_users | manage_users | manage_users.php | manage_users.js | ok |
| 1016 | Route Registry | sidebar | Navigation & UI | route_registry | route_registry | route_registry.php | system_admin_console.js | ok |
| 1021 | Domain Isolation | sidebar | Policy & Governance | domain_isolation_rules | domain_isolation_rules | domain_isolation_rules.php | system_admin_console.js | ok |
| 1025 | Authentication Logs | sidebar | Security Center | authentication_logs | authentication_logs | authentication_logs.php | authentication_logs.js | ok |
| 1030 | System Health | sidebar | Monitoring | system_health | system_health | system_health.php | system_admin_console.js | ok |
| 1035 | System Settings | sidebar | Configuration | system_settings | system_settings | system_settings.php | system_admin_console.js | ok |
| 1039 | Schema Registry | sidebar | Data Governance | migrations | migrations | migrations.php | system_admin_console.js | ok |
| 1044 | API Explorer | sidebar | Developer Tools | api_explorer | api_explorer | api_explorer.php | system_admin_console.js | ok |
| 1048 | Activity Logs | sidebar | Audit & Forensics | activity_audit_logs | activity_audit_logs | activity_audit_logs.php | system_admin_console.js | ok |
| 1002 | Identity & Access | sidebar |  |  |  |  |  | group |
| 1012 | Account Status | sidebar | Identity & Access | account_status | account_status | account_status.php | account_status.js | ok |
| 1017 | Sidebar Menus | sidebar | Navigation & UI | sidebar_menus | sidebar_menus | sidebar_menus.php | system_admin_console.js | ok |
| 1022 | Time-Bound Access | sidebar | Policy & Governance | time_bound_access | time_bound_access | time_bound_access.php | system_admin_console.js | ok |
| 1026 | Failed Logins | sidebar | Security Center | failed_login_attempts | failed_login_attempts | failed_login_attempts.php | failed_login_attempts.js | ok |
| 1031 | Error Logs | sidebar | Monitoring | error_logs | error_logs | error_logs.php | system_admin_console.js | ok |
| 1036 | Feature Flags | sidebar | Configuration | feature_flags | feature_flags | feature_flags.php | system_admin_console.js | ok |
| 1040 | Migrations | sidebar | Data Governance | migrations | migrations | migrations.php | system_admin_console.js | ok |
| 1045 | Webhook Registry | sidebar | Developer Tools | webhook_registry | webhook_registry | webhook_registry.php | system_admin_console.js | ok |
| 1049 | Permission Changes | sidebar | Audit & Forensics | permission_changes | permission_changes | permission_changes.php | system_admin_console.js | ok |
| 100078 | Dashboard | sidebar | Users & Roles | home.php?route=system_administrator_dashboard | system_administrator_dashboard | components/dashboards/system_administrator_dashboard.php | system_administrator_dashboard.js | dashboard |
| 1013 | Role Definitions | sidebar | Identity & Access | manage_roles | manage_roles | manage_roles.php | manage_roles.js | ok |
| 1018 | Dashboard Registry | sidebar | Navigation & UI | dashboard_registry | dashboard_registry | dashboard_registry.php | system_admin_console.js | ok |
| 1023 | Route Access Rules | sidebar | Policy & Governance | route_access_rules | route_access_rules | route_access_rules.php | system_admin_console.js | ok |
| 1027 | Active Sessions | sidebar | Security Center | active_sessions | active_sessions | active_sessions.php | active_sessions.js | ok |
| 1032 | Background Jobs | sidebar | Monitoring | background_jobs | background_jobs | background_jobs.php | system_admin_console.js | ok |
| 1037 | Module Enablement | sidebar | Configuration | module_enablement | module_enablement | module_enablement.php | system_admin_console.js | ok |
| 1041 | Backups | sidebar | Data Governance | backups | backups | backups.php | system_admin_console.js | ok |
| 1046 | Job Inspector | sidebar | Developer Tools | job_inspector | job_inspector | job_inspector.php | system_admin_console.js | ok |
| 1050 | Policy Violations | sidebar | Audit & Forensics | policy_violations | policy_violations | policy_violations.php | system_admin_console.js | ok |
| 1014 | Role-Permission Matrix | sidebar | Identity & Access | role_permission_matrix | role_permission_matrix | role_permission_matrix.php | role_permission_matrix.js | ok |
| 1019 | Widget Registry | sidebar | Navigation & UI | widget_registry | widget_registry | widget_registry.php | system_admin_console.js | ok |
| 1024 | Permission Policies | sidebar | Policy & Governance | permission_policies | permission_policies | permission_policies.php | system_admin_console.js | ok |
| 1028 | Token Management | sidebar | Security Center | token_management | token_management | token_management.php | token_management.js | ok |
| 1033 | API Metrics | sidebar | Monitoring | api_metrics | api_metrics | api_metrics.php | system_admin_console.js | ok |
| 1038 | Maintenance Mode | sidebar | Configuration | maintenance_mode | maintenance_mode | maintenance_mode.php | system_admin_console.js | ok |
| 1042 | Data Retention | sidebar | Data Governance | data_retention | data_retention | data_retention.php | system_admin_console.js | ok |
| 1047 | System Diagnostics | sidebar | Developer Tools | system_diagnostics | system_diagnostics | system_diagnostics.php | system_admin_console.js | ok |
| 1051 | Security Incidents | sidebar | Audit & Forensics | security_incidents | security_incidents | security_incidents.php | system_admin_console.js | ok |
| 1015 | Resource Permissions | sidebar | Identity & Access | resource_based_permissions | resource_based_permissions | resource_based_permissions.php | resource_based_permissions.js | ok |
| 1020 | Role Navigation | sidebar | Navigation & UI | role_navigation_config | role_navigation_config | role_navigation_config.php | system_admin_console.js | ok |
| 1029 | IP Whitelist/Blacklist | sidebar | Security Center | ip_whitelist_blacklist | ip_whitelist_blacklist | ip_whitelist_blacklist.php | ip_whitelist_blacklist.js | ok |
| 1034 | Rate Limiting | sidebar | Monitoring | rate_limiting_status | rate_limiting_status | rate_limiting_status.php | system_admin_console.js | ok |
| 1043 | Data Purge Policies | sidebar | Data Governance | data_retention | data_retention | data_retention.php | system_admin_console.js | ok |
| 100069 | Users & Roles | sidebar |  |  |  |  |  | group |
| 100079 | User Accounts | sidebar | Users & Roles | home.php?route=manage_users | manage_users | manage_users.php | manage_users.js | ok |
| 100089 | Domain Isolation | sidebar | Policy & Governance | home.php?route=domain_isolation_rules | domain_isolation_rules | domain_isolation_rules.php | system_admin_console.js | ok |
| 100093 | System Settings | sidebar | Configuration | home.php?route=system_settings | system_settings | system_settings.php | system_admin_console.js | ok |
| 100097 | Route Registry | sidebar | Navigation & UI | home.php?route=route_registry | route_registry | route_registry.php | system_admin_console.js | ok |
| 100100 | System Health | sidebar | Monitoring | home.php?route=system_health | system_health | system_health.php | system_admin_console.js | ok |
| 100105 | Migrations | sidebar | Data Governance | home.php?route=migrations | migrations | migrations.php | system_admin_console.js | ok |
| 100108 | Activity Logs | sidebar | Audit & Forensics | home.php?route=activity_audit_logs | activity_audit_logs | activity_audit_logs.php | system_admin_console.js | ok |
| 100112 | API Explorer | sidebar | Developer Tools | home.php?route=api_explorer | api_explorer | api_explorer.php | system_admin_console.js | ok |
| 1005 | Security Center | sidebar |  |  |  |  |  | group |
| 100080 | Account Status | sidebar | Users & Roles | home.php?route=account_status | account_status | account_status.php | account_status.js | ok |
| 100090 | Time-Bound Access | sidebar | Policy & Governance | home.php?route=time_bound_access | time_bound_access | time_bound_access.php | system_admin_console.js | ok |
| 100094 | Feature Flags | sidebar | Configuration | home.php?route=feature_flags | feature_flags | feature_flags.php | system_admin_console.js | ok |
| 100098 | Sidebar Menus | sidebar | Navigation & UI | home.php?route=sidebar_menus | sidebar_menus | sidebar_menus.php | system_admin_console.js | ok |
| 100101 | Error Logs | sidebar | Monitoring | home.php?route=error_logs | error_logs | error_logs.php | system_admin_console.js | ok |
| 100106 | Backups | sidebar | Data Governance | home.php?route=backups | backups | backups.php | system_admin_console.js | ok |
| 100109 | Permission Changes | sidebar | Audit & Forensics | home.php?route=permission_changes | permission_changes | permission_changes.php | system_admin_console.js | ok |
| 100113 | Webhook Registry | sidebar | Developer Tools | home.php?route=webhook_registry | webhook_registry | webhook_registry.php | system_admin_console.js | ok |
| 1004 | Policy & Governance | sidebar |  |  |  |  |  | group |
| 100081 | Role Definitions | sidebar | Users & Roles | home.php?route=manage_roles | manage_roles | manage_roles.php | manage_roles.js | ok |
| 100091 | Permission Policies | sidebar | Policy & Governance | home.php?route=permission_policies | permission_policies | permission_policies.php | system_admin_console.js | ok |
| 100095 | Module Enablement | sidebar | Configuration | home.php?route=module_enablement | module_enablement | module_enablement.php | system_admin_console.js | ok |
| 100099 | Role Navigation Config | sidebar | Navigation & UI | home.php?route=role_navigation_config | role_navigation_config | role_navigation_config.php | system_admin_console.js | ok |
| 100102 | Background Jobs | sidebar | Monitoring | home.php?route=background_jobs | background_jobs | background_jobs.php | system_admin_console.js | ok |
| 100107 | Data Retention | sidebar | Data Governance | home.php?route=data_retention | data_retention | data_retention.php | system_admin_console.js | ok |
| 100110 | Policy Violations | sidebar | Audit & Forensics | home.php?route=policy_violations | policy_violations | policy_violations.php | system_admin_console.js | ok |
| 100114 | System Diagnostics | sidebar | Developer Tools | home.php?route=system_diagnostics | system_diagnostics | system_diagnostics.php | system_admin_console.js | ok |
| 1007 | Configuration | sidebar |  |  |  |  |  | group |
| 100082 | Role-Permission Matrix | sidebar | Users & Roles | home.php?route=role_permission_matrix | role_permission_matrix | role_permission_matrix.php | role_permission_matrix.js | ok |
| 100092 | Route Access Rules | sidebar | Policy & Governance | home.php?route=route_access_rules | route_access_rules | route_access_rules.php | system_admin_console.js | ok |
| 100096 | Maintenance Mode | sidebar | Configuration | home.php?route=maintenance_mode | maintenance_mode | maintenance_mode.php | system_admin_console.js | ok |
| 100103 | API Metrics | sidebar | Monitoring | home.php?route=api_metrics | api_metrics | api_metrics.php | system_admin_console.js | ok |
| 100111 | Security Incidents | sidebar | Audit & Forensics | home.php?route=security_incidents | security_incidents | security_incidents.php | system_admin_console.js | ok |
| 100115 | Job Inspector | sidebar | Developer Tools | home.php?route=job_inspector | job_inspector | job_inspector.php | system_admin_console.js | ok |
| 1003 | Navigation & UI | sidebar |  |  |  |  |  | group |
| 100083 | Resource Permissions | sidebar | Users & Roles | home.php?route=resource_based_permissions | resource_based_permissions | resource_based_permissions.php | resource_based_permissions.js | ok |
| 100104 | Rate Limiting | sidebar | Monitoring | home.php?route=rate_limiting_status | rate_limiting_status | rate_limiting_status.php | system_admin_console.js | ok |
| 1006 | Monitoring | sidebar |  |  |  |  |  | group |
| 1008 | Data Governance | sidebar |  |  |  |  |  | group |
| 1010 | Audit & Forensics | sidebar |  |  |  |  |  | group |
| 1009 | Developer Tools | sidebar |  |  |  |  |  | group |
| 100039 | Website | sidebar |  | manage_website | manage_website | manage_website.php | manage_website.js | ok (legacy callAPI) |
| 100116 | Assessment Overview | sidebar |  | assessment_overview | assessment_overview | assessment_overview.php | assessment_overview.js | ok |
| 100040 | News & Blog | sidebar | Website | manage_website | manage_website | manage_website.php | manage_website.js | ok (legacy callAPI) |
| 100117 | WhatsApp Management | sidebar |  | manage_whatsapp | manage_whatsapp | manage_whatsapp.php | manage_whatsapp.js | ok |
| 100041 | School Events | sidebar | Website | manage_website | manage_website | manage_website.php | manage_website.js | ok (legacy callAPI) |
| 100118 | Salary Advances | sidebar |  | salary_advances | salary_advances | salary_advances.php | salary_advances.js | ok |
| 100042 | Gallery | sidebar | Website | manage_website | manage_website | manage_website.php | manage_website.js | ok (legacy callAPI) |
| 100119 | Initialize School | sidebar |  | school_initialization | school_initialization | school_initialization.php | school_initialization.js | ok |
| 100043 | Downloads | sidebar | Website | manage_website | manage_website | manage_website.php | manage_website.js | ok (legacy callAPI) |
| 100120 | Staff Schedule | sidebar |  | staff_schedule | staff_schedule | staff_schedule.php | staff_schedule.js | ok |
| 100044 | Job Vacancies | sidebar | Website | manage_website | manage_website | manage_website.php | manage_website.js | ok (legacy callAPI) |
| 100121 | Student Timeline | sidebar |  | student_timeline | student_timeline | student_timeline.php | student_timeline.js | ok |
| 100045 | Applications | sidebar | Website | manage_website | manage_website | manage_website.php | manage_website.js | ok (legacy callAPI) |
| 100122 | Term Transition | sidebar |  | term_transition | term_transition | term_transition.php | term_transition.js | ok |
| 100046 | Site Settings | sidebar | Website | manage_website | manage_website | manage_website.php | manage_website.js | ok (legacy callAPI) |
| 100123 | Year Rollover | sidebar |  | year_rollover | year_rollover | year_rollover.php | year_rollover.js | ok |
| 100047 | Page Content | sidebar | Website | manage_website | manage_website | manage_website.php | manage_website.js | ok (legacy callAPI) |

## Endpoint usage (dedup, all sources)

| Endpoint | Module | API method | Handler | Status | Source |
|---|---|---|---|---|---|
| `GET /dashboard/system-admin/auth-events` | dashboard | getAuthEvents | `Dashboard::getAuthEvents` | OK | dashboard:system_administrator_dashboard |
| `GET /dashboard/system-admin/active-sessions` | dashboard | getActiveSessions | `Dashboard::getActiveSessions` | OK | dashboard:system_administrator_dashboard |
| `GET /dashboard/system-admin/uptime` | dashboard | getSystemUptime | `Dashboard::getSystemUptime` | OK | dashboard:system_administrator_dashboard |
| `GET /dashboard/system-admin/health-errors` | dashboard | getSystemHealthErrors | `Dashboard::getSystemHealthErrors` | OK | dashboard:system_administrator_dashboard |
| `GET /dashboard/system-admin/health-warnings` | dashboard | getSystemHealthWarnings | `Dashboard::getSystemHealthWarnings` | OK | dashboard:system_administrator_dashboard |
| `GET /dashboard/system-admin/api-load` | dashboard | getAPIRequestLoad | `Dashboard::getAPIRequestLoad` | OK | dashboard:system_administrator_dashboard |
| `GET /users/index` | users | index | `Users::index` | OK | item:1011 |
| `GET /system/roles` | system | getRoles | `System::getRoles` | OK | item:1011 |
| `PUT /users/user/` | users | update | `Users::update` | OK | item:1011 |
| `POST /users/user` | users | create | `Users::create` | OK | item:1011 |
| `DELETE /users/user/` | users | delete | `Users::delete` | OK | item:1011 |
| `GET /system/school-config` | system | getSchoolConfig | `System::getSchoolConfig` | OK | item:1016 |
| `POST /system/school-config` | system | updateSchoolConfig | `System::updateSchoolConfig` | OK | item:1016 |
| `GET /system/backups` | system | getBackups | `System::getBackups` | OK | item:1016 |
| `POST /system/backups` | system | createBackup | `System::createBackup` | OK | item:1016 |
| `DELETE /system/backups` | system | deleteBackup | `System::deleteBackup` | OK | item:1016 |
| `GET /system/feature-flags` | system | getFeatureFlags | `System::getFeatureFlags` | OK | item:1016 |
| `PUT /system/feature-flags` | system | updateFeatureFlags | `System::updateFeatureFlags` | OK | item:1016 |
| `GET /system/module-enablement` | system | getModuleEnablement | `System::getModuleEnablement` | OK | item:1016 |
| `PUT /system/module-enablement` | system | updateModuleEnablement | `System::updateModuleEnablement` | OK | item:1016 |
| `GET /system/data-retention` | system | getDataRetention | `System::getDataRetention` | OK | item:1016 |
| `PUT /system/data-retention` | system | updateDataRetention | `System::updateDataRetention` | OK | item:1016 |
| `GET /system/domain-isolation` | system | getDomainIsolation | `System::getDomainIsolation` | OK | item:1016 |
| `PUT /system/domain-isolation` | system | updateDomainIsolation | `System::updateDomainIsolation` | OK | item:1016 |
| `GET /system/time-bound-access` | system | getTimeBoundAccess | `System::getTimeBoundAccess` | OK | item:1016 |
| `PUT /system/time-bound-access` | system | updateTimeBoundAccess | `System::updateTimeBoundAccess` | OK | item:1016 |
| `GET /system/routes` | system | getRoutes | `System::getRoutes` | OK | item:1016 |
| `PUT /system/routes` | system | updateRoute | `System::updateRoute` | OK | item:1016 |
| `POST /system/routes` | system | createRoute | `System::createRoute` | OK | item:1016 |
| `DELETE /system/routes` | system | deleteRoute | `System::deleteRoute` | OK | item:1016 |
| `GET /system/route-access-rules` | system | getRouteAccessRules | `System::getRouteAccessRules` | OK | item:1016 |
| `PUT /system/route-access-rules` | system | updateRouteAccessRule | `System::updateRouteAccessRule` | OK | item:1016 |
| `POST /system/route-access-rules` | system | createRouteAccessRule | `System::createRouteAccessRule` | OK | item:1016 |
| `DELETE /system/route-access-rules` | system | deleteRouteAccessRule | `System::deleteRouteAccessRule` | OK | item:1016 |
| `GET /system/role-navigation` | system | getRoleNavigation | `System::getRoleNavigation` | OK | item:1016 |
| `GET /system/permission-policies` | system | getPermissionPolicies | `System::getPermissionPolicies` | OK | item:1016 |
| `PUT /system/permission-policies` | system | updatePermissionPolicy | `System::updatePermissionPolicy` | OK | item:1016 |
| `POST /system/permission-policies` | system | createPermissionPolicy | `System::createPermissionPolicy` | OK | item:1016 |
| `DELETE /system/permission-policies` | system | deletePermissionPolicy | `System::deletePermissionPolicy` | OK | item:1016 |
| `GET /system/sidebar-menus` | system | getSidebarMenus | `System::getSidebarMenus` | OK | item:1016 |
| `PUT /system/sidebar-menus` | system | updateSidebarMenu | `System::updateSidebarMenu` | OK | item:1016 |
| `POST /system/sidebar-menus` | system | createSidebarMenu | `System::createSidebarMenu` | OK | item:1016 |
| `DELETE /system/sidebar-menus` | system | deleteSidebarMenu | `System::deleteSidebarMenu` | OK | item:1016 |
| `GET /system/dashboards` | system | getDashboards | `System::getDashboards` | OK | item:1016 |
| `PUT /system/dashboards` | system | updateDashboard | `System::updateDashboard` | OK | item:1016 |
| `POST /system/dashboards` | system | createDashboard | `System::createDashboard` | OK | item:1016 |
| `DELETE /system/dashboards` | system | deleteDashboard | `System::deleteDashboard` | OK | item:1016 |
| `GET /system/widgets` | system | getWidgets | `System::getWidgets` | OK | item:1016 |
| `PUT /system/widgets` | system | updateWidget | `System::updateWidget` | OK | item:1016 |
| `POST /system/widgets` | system | createWidget | `System::createWidget` | OK | item:1016 |
| `DELETE /system/widgets` | system | deleteWidget | `System::deleteWidget` | OK | item:1016 |
| `GET /system/webhook-registry` | system | getWebhookRegistry | `System::getWebhookRegistry` | OK | item:1016 |
| `PUT /system/webhook-registry` | system | updateWebhook | `System::updateWebhook` | OK | item:1016 |
| `POST /system/webhook-registry` | system | createWebhook | `System::createWebhook` | OK | item:1016 |
| `DELETE /system/webhook-registry` | system | deleteWebhook | `System::deleteWebhook` | OK | item:1016 |
| `GET /system/security-incidents` | system | getSecurityIncidents | `System::getSecurityIncidents` | OK | item:1016 |
| `GET /system/policy-violations` | system | getPolicyViolations | `System::getPolicyViolations` | OK | item:1016 |
| `GET /system/migrations` | system | getMigrations | `System::getMigrations` | OK | item:1016 |
| `POST /system/migrations` | system | runMigration | `System::runMigration` | OK | item:1016 |
| `GET /system/health` | system | getHealth | `System::getHealth` | OK | item:1016 |
| `GET /system/diagnostics` | system | getDiagnostics | `System::getDiagnostics` | OK | item:1016 |
| `GET /system/activity-audit-logs` | system | getActivityAuditLogs | `System::getActivityAuditLogs` | OK | item:1016 |
| `GET /system/error-logs` | system | getErrorLogs | `System::getErrorLogs` | OK | item:1016 |
| `GET /system/api-metrics` | system | getApiMetrics | `System::getApiMetrics` | OK | item:1016 |
| `GET /system/rate-limiting` | system | getRateLimiting | `System::getRateLimiting` | OK | item:1016 |
| `GET /system/permission-changes` | system | getPermissionChanges | `System::getPermissionChanges` | OK | item:1016 |
| `GET /system/background-jobs` | system | getBackgroundJobs | `System::getBackgroundJobs` | OK | item:1016 |
| `GET /system/job-inspector` | system | getJobInspector | `System::getJobInspector` | OK | item:1016 |
| `GET /system/authentication-logs` | system | getAuthenticationLogs | `System::getAuthenticationLogs` | OK | item:1025 |
| `GET /system/account-status` | system | getAccountStatuses | `System::getAccountStatuses` | OK | item:1012 |
| `PUT /system/account-status` | system | updateAccountStatus | `System::updateAccountStatus` | OK | item:1012 |
| `GET /system/failed-login-attempts` | system | getFailedLogins | `System::getFailedLogins` | OK | item:1026 |
| `GET /system/roles/` | system | getRole | `System::getRole` | OK | item:1013 |
| `PUT /system/roles` | system | updateRole | `System::updateRole` | OK | item:1013 |
| `POST /system/roles` | system | createRole | `System::createRole` | OK | item:1013 |
| `DELETE /system/roles/` | system | deleteRole | `System::deleteRole` | OK | item:1013 |
| `POST /system/roles-toggle` | system | toggleRoleStatus | `System::toggleRoleStatus` | OK | item:1013 |
| `GET /system/active-sessions` | system | getActiveSessions | `System::getActiveSessions` | OK | item:1027 |
| `POST /system/active-sessions-revoke` | system | revokeSession | `System::revokeSession` | OK | item:1027 |
| `GET /system/permissions` | system | getPermissions | `System::getPermissions` | OK | item:1014 |
| `GET /system/role-permissions?role_id=` | system | getRolePermissions | `System::getRolePermissions` | OK | item:1014 |
| `POST /system/role-permissions` | system | assignPermissionToRole | `System::assignPermissionToRole` | OK | item:1014 |
| `DELETE /system/role-permissions/` | system | revokePermissionFromRole | `System::revokePermissionFromRole` | OK | item:1014 |
| `GET /system/tokens` | system | getTokens | `System::getTokens` | OK | item:1028 |
| `POST /system/tokens-revoke` | system | revokeToken | `System::revokeToken` | OK | item:1028 |
| `GET /system/resource-permissions` | system | getResourcePermissions | `System::getResourcePermissions` | OK | item:1015 |
| `PUT /system/permissions/` | system | updatePermission | `System::updatePermission` | OK | item:1015 |
| `POST /system/permissions` | system | createPermission | `System::createPermission` | OK | item:1015 |
| `DELETE /system/permissions/` | system | deletePermission | `System::deletePermission` | OK | item:1015 |
| `GET /system/ip-lists` | system | getIpLists | `System::getIpLists` | OK | item:1029 |
| `POST /system/ip-lists` | system | createIpRule | `System::createIpRule` | OK | item:1029 |
| `PUT /system/ip-lists/` | system | updateIpRule | `System::updateIpRule` | OK | item:1029 |
| `DELETE /system/ip-lists/` | system | deleteIpRule | `System::deleteIpRule` | OK | item:1029 |
| `GET /website/stats` | legacy | this.API | `Website::this.API` | OK | item:100039 |
| `GET /website/categories` | legacy | this.API | `Website::this.API` | OK | item:100039 |
| `GET /website/news` | legacy | this.API | `Website::this.API` | OK | item:100039 |
| `GET /website/news/` | legacy | this.API | `Website::this.API` | OK | item:100039 |
| `PUT /website/news/` | legacy | this.API | `Website::this.API` | OK | item:100039 |
| `POST /website/news` | legacy | this.API | `Website::this.API` | OK | item:100039 |
| `DELETE /website/news/` | legacy | this.API | `Website::this.API` | OK | item:100039 |
| `GET /website/events` | legacy | this.API | `Website::this.API` | OK | item:100039 |
| `GET /website/events/` | legacy | this.API | `Website::this.API` | OK | item:100039 |
| `PUT /website/events/` | legacy | this.API | `Website::this.API` | OK | item:100039 |
| `POST /website/events` | legacy | this.API | `Website::this.API` | OK | item:100039 |
| `DELETE /website/events/` | legacy | this.API | `Website::this.API` | OK | item:100039 |
| `GET /website/gallery` | legacy | this.API | `Website::this.API` | OK | item:100039 |
| `POST /website/gallery` | legacy | this.API | `Website::this.API` | OK | item:100039 |
| `DELETE /website/gallery/` | legacy | this.API | `Website::this.API` | OK | item:100039 |
| `GET /website/downloads` | legacy | this.API | `Website::this.API` | OK | item:100039 |
| `PUT /website/downloads/` | legacy | this.API | `Website::this.API` | OK | item:100039 |
| `POST /website/downloads` | legacy | this.API | `Website::this.API` | OK | item:100039 |
| `DELETE /website/downloads/` | legacy | this.API | `Website::this.API` | OK | item:100039 |
| `GET /website/jobs` | legacy | this.API | `Website::this.API` | OK | item:100039 |
| `GET /website/jobs/` | legacy | this.API | `Website::this.API` | OK | item:100039 |
| `PUT /website/jobs/` | legacy | this.API | `Website::this.API` | OK | item:100039 |
| `POST /website/jobs` | legacy | this.API | `Website::this.API` | OK | item:100039 |
| `DELETE /website/jobs/` | legacy | this.API | `Website::this.API` | OK | item:100039 |
| `GET /website/applications` | legacy | this.API | `Website::this.API` | OK | item:100039 |
| `GET /website/job-applications` | legacy | this.API | `Website::this.API` | OK | item:100039 |
| `PUT /website/applications/` | legacy | this.API | `Website::this.API` | OK | item:100039 |
| `GET /website/inquiries` | legacy | this.API | `Website::this.API` | OK | item:100039 |
| `PUT /website/inquiries/` | legacy | this.API | `Website::this.API` | OK | item:100039 |
| `GET /website/content` | legacy | this.API | `Website::this.API` | OK | item:100039 |
| `PUT /website/content` | legacy | this.API | `Website::this.API` | OK | item:100039 |
| `POST /website/categories` | legacy | this.API | `Website::this.API` | OK | item:100039 |
| `DELETE /website/categories/` | legacy | this.API | `Website::this.API` | OK | item:100039 |
| `GET /website/settings` | legacy | this.API | `Website::this.API` | OK | item:100039 |
| `PUT /website/settings` | legacy | this.API | `Website::this.API` | OK | item:100039 |
| `GET /website/` | legacy | this.API | — | dynamic | item:100039 |
| `DELETE /website/` | legacy | this.API | — | dynamic | item:100039 |
| `GET /academic/terms-list` | direct | apiCall | `Academic::apiCall` | OK | item:100116 |
| `GET /academic/assessment-types` | direct | apiCall | `Academic::apiCall` | OK | item:100116 |
| `GET /academic/learning-areas-list` | direct | apiCall | `Academic::apiCall` | OK | item:100116 |
| `GET /academic/strands?learning_area_id=` | direct | apiCall | `Academic::apiCall` | OK | item:100116 |
| `GET /academic/assessments-list?` | direct | apiCall | `Academic::apiCall` | OK | item:100116 |
| `GET /academic/learning-outcomes?learning_area_id=` | direct | apiCall | `Academic::apiCall` | OK | item:100116 |
| `POST /academic/formative-assessments` | direct | apiCall | `Academic::apiCall` | OK | item:100116 |
| `GET /academic/class-students?class_id=` | direct | apiCall | `Academic::apiCall` | OK | item:100116 |
| `GET /academic/formative-assessment-marks?assessment_id=` | direct | apiCall | `Academic::apiCall` | OK | item:100116 |
| `POST /academic/formative-assessment-marks` | direct | apiCall | `Academic::apiCall` | OK | item:100116 |
| `POST /academic/compute-term-scores` | direct | apiCall | `Academic::apiCall` | OK | item:100116 |
| `GET /communications/communication` | communications | getMessages | `Communications::getMessages` | OK | item:100117 |
| `GET /communications/contact/` | communications | getContact | `Communications::getContact` | OK | item:100117 |
| `GET /communications/contact` | communications | getContact | `Communications::getContact` | OK | item:100117 |
| `GET /communications/group/` | communications | getGroup | `Communications::getGroup` | OK | item:100117 |
| `GET /communications/group` | communications | getGroup | `Communications::getGroup` | OK | item:100117 |
| `GET /communications/template` | communications | getTemplates | `Communications::getTemplates` | OK | item:100117 |
| `POST /communications/send-whatsapp` | communications | sendWhatsapp | `Communications::sendWhatsapp` | OK | item:100117 |
| `POST /communications/communication` | communications | createCommunication | `Communications::createCommunication` | OK | item:100117 |
| `POST /communications/resend` | communications | resendCommunication | `Communications::resendCommunication` | OK | item:100117 |
| `GET /finance/salary-advances` | direct | apiCall | `Finance::apiCall` | OK | item:100118 |
| `GET /staff` | direct | apiCall | `Staff::apiCall` | OK | item:100118 |
| `POST /finance/salary-advances` | direct | apiCall | `Finance::apiCall` | OK | item:100118 |
| `GET /finance/salary-advances/` | direct | apiCall | `Finance::apiCall` | OK | item:100118 |
| `GET /schedules/timetable-time-slots` | direct | apiCall | `Schedules::apiCall` | OK | item:100120 |
| `GET /schedules/timetable-get?` | direct | apiCall | `Schedules::apiCall` | OK | item:100120 |
| `GET /schedules/staff-duty-schedule?` | direct | apiCall | `Schedules::apiCall` | OK | item:100120 |
| `GET /students/all` | direct | apiCall | `Students::apiCall` | OK | item:100121 |
| `GET /academic/student-timeline/` | direct | apiCall | `Academic::apiCall` | OK | item:100121 |
| `POST /academic/transfer-requests` | direct | apiCall | `Academic::apiCall` | OK | item:100121 |
| `GET /academic/term-transition/context` | direct | apiCall | `Academic::apiCall` | OK | item:100122 |
| `GET /academic/timetable-stats` | direct | apiCall | `Academic::apiCall` | OK | item:100122 |
| `GET /academic/lesson-plans-list?term_id=` | direct | apiCall | `Academic::apiCall` | OK | item:100122 |
| `GET /attendance/academic-summary?term_id=` | direct | apiCall | `Attendance::apiCall` | OK | item:100122 |
| `GET /academic/grading-results?term_id=` | direct | apiCall | `Academic::apiCall` | OK | item:100122 |
| `POST /academic/term-transition/execute` | direct | apiCall | `Academic::apiCall` | OK | item:100122 |
| `GET /academic/year-rollover-status` | direct | apiCall | `Academic::apiCall` | OK | item:100123 |
| `POST /academic/year-rollover` | direct | apiCall | `Academic::apiCall` | OK | item:100123 |

## Authorized routes (role_routes)

| route_id | Name | URL | Controller | Action |
|---|---|---|---|---|
| 5 | activity_audit_logs | home.php?route=activity_audit_logs | SystemAdministrationController |  |
| 9 | api_explorer | home.php?route=api_explorer | SystemAdministrationController |  |
| 4 | authentication_logs | home.php?route=authentication_logs | SystemController |  |
| 11 | cache_monitor | home.php?route=cache_monitor |  |  |
| 12 | db_health_monitor | home.php?route=db_health_monitor |  |  |
| 16 | delegated_permissions | home.php?route=delegated_permissions |  |  |
| 3 | error_logs | home.php?route=error_logs | SystemAdministrationController |  |
| 86 | home | home.php |  |  |
| 10 | job_queue_monitor | home.php?route=job_queue_monitor |  |  |
| 8 | maintenance_mode | home.php?route=maintenance_mode | SystemAdministrationController |  |
| 15 | manage_permissions | home.php?route=manage_permissions |  |  |
| 14 | manage_roles | home.php?route=manage_roles | SystemController |  |
| 13 | manage_users | home.php?route=manage_users | SystemAdministrationController |  |
| 87 | me | home.php?route=me |  |  |
| 7 | module_management | home.php?route=module_management |  |  |
| 1 | system_administrator_dashboard | home.php?route=system_administrator_dashboard | DashboardController |  |
| 2 | system_health | home.php?route=system_health | SystemAdministrationController |  |
| 6 | system_settings | home.php?route=system_settings | SystemAdministrationController |  |
| 17 | manage_routes | home.php?route=manage_routes |  |  |
| 18 | manage_menus | home.php?route=manage_menus |  |  |
| 19 | manage_dashboards | home.php?route=manage_dashboards |  |  |
| 20 | manage_policies | home.php?route=manage_policies |  |  |
| 21 | config_sync | home.php?route=config_sync |  |  |
| 88 | system_uptime | home.php?route=system_uptime |  |  |
| 89 | active_users | home.php?route=active_users |  |  |
| 90 | error_rate | home.php?route=error_rate |  |  |
| 91 | queue_health | home.php?route=queue_health |  |  |
| 92 | db_health | home.php?route=db_health |  |  |
| 94 | account_status | home.php?route=account_status | SystemAdministrationController |  |
| 95 | role_definitions | home.php?route=role_definitions |  |  |
| 96 | role_scope | home.php?route=role_scope |  |  |
| 97 | permission_registry | home.php?route=permission_registry |  |  |
| 98 | temporary_roles | home.php?route=temporary_roles |  |  |
| 99 | expiry_based_access | home.php?route=expiry_based_access |  |  |
| 101 | route_registry | home.php?route=route_registry | SystemAdministrationController |  |
| 102 | route_domains | home.php?route=route_domains |  |  |
| 103 | sidebar_menus | home.php?route=sidebar_menus | SystemAdministrationController |  |
| 104 | submenus_management | home.php?route=submenus_management |  |  |
| 105 | icons_ordering | home.php?route=icons_ordering |  |  |
| 106 | dashboard_registry | home.php?route=dashboard_registry |  |  |
| 107 | role_dashboard_mapping | home.php?route=role_dashboard_mapping |  |  |
| 108 | domain_isolation_rules | home.php?route=domain_isolation_rules | SystemAdministrationController |  |
| 109 | readonly_enforcement | home.php?route=readonly_enforcement |  |  |
| 110 | time_bound_access | home.php?route=time_bound_access | SystemAdministrationController |  |
| 111 | location_device_rules | home.php?route=location_device_rules |  |  |
| 112 | audit_requirements | home.php?route=audit_requirements |  |  |
| 113 | retention_policies | home.php?route=retention_policies |  |  |
| 114 | authorization_logs | home.php?route=authorization_logs |  |  |
| 115 | failed_login_attempts | home.php?route=failed_login_attempts | SystemController |  |
| 116 | active_sessions | home.php?route=active_sessions | SystemController |  |
| 117 | force_logout | home.php?route=force_logout |  |  |
| 118 | revoke_tokens | home.php?route=revoke_tokens |  |  |
| 120 | background_jobs | home.php?route=background_jobs | SystemAdministrationController |  |
| 121 | queue_monitor | home.php?route=queue_monitor |  |  |
| 122 | api_metrics | home.php?route=api_metrics | SystemAdministrationController |  |
| 127 | feature_flags | home.php?route=feature_flags | SystemAdministrationController |  |
| 128 | module_enablement | home.php?route=module_enablement | SystemAdministrationController |  |
| 131 | schema_registry | home.php?route=schema_registry |  |  |
| 132 | migrations | home.php?route=migrations | SystemAdministrationController |  |
| 133 | backups | home.php?route=backups | SystemAdministrationController |  |
| 134 | data_retention_rules | home.php?route=data_retention_rules |  |  |
| 135 | anonymization_rules | home.php?route=anonymization_rules |  |  |
| 136 | webhook_registry | home.php?route=webhook_registry | SystemAdministrationController |  |
| 137 | job_inspector | home.php?route=job_inspector | SystemAdministrationController |  |
| 138 | system_diagnostics | home.php?route=system_diagnostics | SystemAdministrationController |  |
| 140 | permission_changes | home.php?route=permission_changes | SystemAdministrationController |  |
| 141 | policy_violations | home.php?route=policy_violations | SystemAdministrationController |  |
| 142 | security_incidents | home.php?route=security_incidents | SystemAdministrationController |  |
| 144 | role_permission_matrix | home.php?route=role_permission_matrix | SystemAdministrationController |  |
| 145 | resource_based_permissions | home.php?route=resource_based_permissions | SystemController |  |
| 146 | widget_registry | home.php?route=widget_registry |  |  |
| 147 | role_navigation_config | home.php?route=role_navigation_config | SystemAdministrationController |  |
| 148 | route_access_rules | home.php?route=route_access_rules | SystemAdministrationController |  |
| 149 | permission_policies | home.php?route=permission_policies | SystemAdministrationController |  |
| 150 | token_management | home.php?route=token_management | SystemController |  |
| 151 | ip_whitelist_blacklist | home.php?route=ip_whitelist_blacklist | SystemController |  |
| 152 | rate_limiting_status | home.php?route=rate_limiting_status | SystemAdministrationController |  |
| 153 | data_retention | home.php?route=data_retention | SystemAdministrationController |  |
| 154 | data_purge_policies | home.php?route=data_purge_policies |  |  |
| 88887 | admin_delegation_test_item | /admin_delegation_test_item |  |  |
| 22 | director_owner_dashboard | home.php?route=director_owner_dashboard |  |  |
| 23 | school_administrative_officer_dashboard | home.php?route=school_administrative_officer_dashboard |  |  |
| 24 | headteacher_dashboard | home.php?route=headteacher_dashboard |  |  |
| 28 | intern_student_teacher_dashboard | home.php?route=intern_student_teacher_dashboard |  |  |
| 29 | school_accountant_dashboard | home.php?route=school_accountant_dashboard |  |  |
| 30 | store_manager_dashboard | home.php?route=store_manager_dashboard |  |  |
| 31 | catering_manager_cook_lead_dashboard | home.php?route=catering_manager_cook_lead_dashboard |  |  |
| 32 | matron_housemother_dashboard | home.php?route=matron_housemother_dashboard |  |  |
| 33 | hod_talent_development_dashboard | home.php?route=hod_talent_development_dashboard |  |  |
| 34 | driver_dashboard | home.php?route=driver_dashboard |  |  |
| 35 | school_counselor_chaplain_dashboard | home.php?route=school_counselor_chaplain_dashboard |  |  |
| 78 | manage_communications | home.php?route=manage_communications |  |  |
| 53 | manage_finance | home.php?route=manage_finance |  |  |
| 60 | finance_reports | home.php?route=finance_reports |  |  |
| 62 | budget_overview | home.php?route=budget_overview |  |  |
| 63 | finance_approvals | home.php?route=finance_approvals |  |  |
| 61 | financial_reports | home.php?route=financial_reports |  |  |
| 82 | enrollment_reports | home.php?route=enrollment_reports |  |  |
| 83 | performance_reports | home.php?route=performance_reports |  |  |
| 36 | manage_students | home.php?route=manage_students |  |  |
| 37 | manage_students_admissions | home.php?route=manage_students_admissions |  |  |
| 68 | mark_attendance | home.php?route=mark_attendance |  |  |
| 45 | manage_timetable | home.php?route=manage_timetable |  |  |
| 51 | view_results | home.php?route=view_results |  |  |
| 64 | manage_staff | home.php?route=manage_staff |  |  |
| 66 | staff_attendance | home.php?route=staff_attendance |  |  |
| 42 | manage_academics | home.php?route=manage_academics |  |  |
| 201 | all_students | home.php?route=all_students |  |  |
| 38 | student_performance | home.php?route=student_performance |  |  |
| 202 | all_staff | home.php?route=all_staff |  |  |
| 203 | staff_performance_overview | home.php?route=staff_performance_overview |  |  |
| 39 | student_discipline | home.php?route=student_discipline |  |  |
| 219 | discipline_cases | home.php?route=discipline_cases |  |  |
| 220 | conduct_reports | home.php?route=conduct_reports |  |  |
| 221 | academic_reports | home.php?route=academic_reports |  |  |
| 222 | performance_analysis | home.php?route=performance_analysis |  |  |
| 223 | term_reports | home.php?route=term_reports |  |  |
| 160 | academic_years | home.php?route=academic_years |  |  |
| 161 | current_academic_year | home.php?route=current_academic_year |  |  |
| 162 | manage_terms | home.php?route=manage_terms |  |  |
| 163 | year_calendar | home.php?route=year_calendar |  |  |
| 164 | year_history | home.php?route=year_history |  |  |
| 47 | manage_lesson_plans | home.php?route=manage_lesson_plans |  |  |
| 170 | all_lesson_plans | home.php?route=all_lesson_plans |  |  |
| 171 | lesson_plans_by_class | home.php?route=lesson_plans_by_class |  |  |
| 172 | lesson_plans_by_teacher | home.php?route=lesson_plans_by_teacher |  |  |
| 173 | lesson_plan_approval | home.php?route=lesson_plan_approval |  |  |
| 46 | manage_assessments | home.php?route=manage_assessments |  |  |
| 49 | add_results | home.php?route=add_results |  |  |
| 174 | academic_calendar | home.php?route=academic_calendar |  |  |
| 175 | view_calendar | home.php?route=view_calendar |  |  |
| 176 | manage_calendar_events | home.php?route=manage_calendar_events |  |  |
| 217 | new_applications | home.php?route=new_applications | new_applications |  |
| 218 | admission_status | home.php?route=admission_status |  |  |
| 54 | manage_fees | home.php?route=manage_fees |  |  |
| 237 | students_with_balance | home.php?route=students_with_balance |  |  |
| 238 | balances_by_class | home.php?route=balances_by_class |  |  |
| 239 | fee_defaulters | home.php?route=fee_defaulters |  |  |
| 76 | manage_activities | home.php?route=manage_activities |  |  |
| 224 | clubs_societies | home.php?route=clubs_societies |  |  |
| 225 | sports | home.php?route=sports |  |  |
| 226 | competitions | home.php?route=competitions |  |  |
| 227 | school_events | home.php?route=school_events |  |  |
| 228 | assemblies | home.php?route=assemblies |  |  |
| 177 | assessments_exams | home.php?route=assessments_exams |  |  |
| 178 | exam_schedule | home.php?route=exam_schedule |  |  |
| 179 | exam_setup | home.php?route=exam_setup |  |  |
| 180 | supervision_roster | home.php?route=supervision_roster |  |  |
| 181 | grading_status | home.php?route=grading_status |  |  |
| 182 | results_analysis | home.php?route=results_analysis |  |  |
| 183 | report_cards | home.php?route=report_cards |  |  |
| 40 | student_counseling | home.php?route=student_counseling |  |  |
| 229 | special_needs | home.php?route=special_needs |  |  |
| 230 | counseling_records | home.php?route=counseling_records |  |  |
| 50 | submit_results | home.php?route=submit_results |  |  |
| 48 | myclasses | home.php?route=myclasses |  |  |
| 55 | student_fees | home.php?route=student_fees |  |  |
| 56 | manage_payments | home.php?route=manage_payments |  |  |
| 57 | manage_payrolls | home.php?route=manage_payrolls |  |  |
| 58 | payroll | home.php?route=payroll |  |  |
| 70 | manage_inventory | home.php?route=manage_inventory |  |  |
| 71 | manage_stock | home.php?route=manage_stock |  |  |
| 72 | manage_requisitions | home.php?route=manage_requisitions |  |  |
| 74 | menu_planning | home.php?route=menu_planning |  |  |
| 73 | food_store | home.php?route=food_store |  |  |
| 75 | manage_boarding | home.php?route=manage_boarding |  |  |
| 155 | manage_transport | home.php?route=manage_transport |  |  |
| 84 | my_routes | home.php?route=my_routes |  |  |
| 85 | my_vehicle | home.php?route=my_vehicle |  |  |
| 77 | chapel_services | home.php?route=chapel_services |  |  |
| 69 | view_attendance | home.php?route=view_attendance |  |  |
| 79 | manage_announcements | home.php?route=manage_announcements |  |  |
| 81 | manage_sms | home.php?route=manage_sms |  |  |
| 80 | manage_email | home.php?route=manage_email |  |  |
| 157 | manage_uniform_sales | manage_uniform_sales |  |  |
| 165 | learning_areas | home.php?route=learning_areas |  |  |
| 166 | all_subjects | home.php?route=all_subjects |  |  |
| 167 | assign_subjects_to_teachers | home.php?route=assign_subjects_to_teachers |  |  |
| 168 | curriculum_cbc | home.php?route=curriculum_cbc |  |  |
| 169 | schemes_of_work | home.php?route=schemes_of_work |  |  |
| 204 | all_teachers | home.php?route=all_teachers |  |  |
| 205 | class_teachers | home.php?route=class_teachers |  |  |
| 206 | subject_teachers | home.php?route=subject_teachers |  |  |
| 207 | teacher_workload | home.php?route=teacher_workload |  |  |
| 208 | teacher_performance_reviews | home.php?route=teacher_performance_reviews |  |  |
| 209 | all_parents | home.php?route=all_parents |  |  |
| 210 | parent_meetings | home.php?route=parent_meetings |  |  |
| 211 | parent_feedback | home.php?route=parent_feedback |  |  |
| 212 | pta_management | home.php?route=pta_management |  |  |
| 213 | all_classes | home.php?route=all_classes |  |  |
| 233 | enrollment_trends | home.php?route=enrollment_trends |  |  |
| 234 | performance_trends | home.php?route=performance_trends |  |  |
| 235 | attendance_trends | home.php?route=attendance_trends |  |  |
| 236 | comparative_reports | home.php?route=comparative_reports |  |  |
| 158 | boarding_roll_call | home.php?route=boarding_roll_call |  |  |
| 231 | permissions_exeats | home.php?route=permissions_exeats |  |  |
| 232 | dormitory_management | home.php?route=dormitory_management |  |  |
| 240 | deputy_head_academic_dashboard | home.php?route=deputy_head_academic_dashboard |  |  |
| 241 | deputy_head_discipline_dashboard | home.php?route=deputy_head_discipline_dashboard |  |  |
| 99998 | headteacher_test_item | /headteacher_test_item |  |  |
| 156 | manage_fee_structure | home.php?route=manage_fee_structure |  |  |
| 100000 | unmatched_payments | home.php?route=unmatched_payments |  |  |
| 100001 | vendors | home.php?route=vendors |  |  |
| 100002 | purchase_orders | home.php?route=purchase_orders |  |  |
| 100003 | petty_cash | home.php?route=petty_cash |  |  |
| 100004 | bank_accounts | home.php?route=bank_accounts |  |  |
| 100005 | bank_transactions | home.php?route=bank_transactions |  |  |
| 100006 | mpesa_settlements | home.php?route=mpesa_settlements |  |  |
| 100008 | support_staff_dashboard | home.php?route=support_staff_dashboard |  |  |
| 100010 | competencies_sheet | home.php?route=competencies_sheet | academic | getCompetencyRatings |
| 100011 | formative_assessments | home.php?route=formative_assessments | academic | getFormativeAssessments |
| 100009 | national_exams | home.php?route=national_exams | academic | getNationalExams |
| 100013 | sick_bay | home.php?route=sick_bay | health | getSickBay |
| 100012 | student_health | home.php?route=student_health | health | getStudentHealth |
| 100014 | manage_library | home.php?route=manage_library | library | getBooks |
| 100029 | student_id_cards | home.php?route=student_id_cards |  |  |
| 100023 | manage_family_groups | home.php?route=manage_family_groups |  |  |
| 44 | manage_subjects | home.php?route=manage_subjects |  |  |
| 100007 | manage_teachers | pages/manage_teachers.php |  |  |
| 67 | staff_performance | home.php?route=staff_performance |  |  |
| 100016 | announcements | home.php?route=announcements |  |  |
| 100017 | circulars | home.php?route=circulars |  |  |
| 100031 | timetable | home.php?route=timetable |  |  |
| 100045 | academic_students | home.php?route=academic_students |  |  |
| 100047 | boarding_students | home.php?route=boarding_students |  |  |
| 100048 | catering_boarding_students | home.php?route=catering_boarding_students |  |  |
| 100046 | discipline_students | home.php?route=discipline_students |  |  |
| 100051 | my_students_list | home.php?route=my_students_list |  |  |
| 100055 | student_profiles | home.php?route=student_profiles |  |  |
| 100050 | student_welfare | home.php?route=student_welfare |  |  |
| 100053 | students_by_class | home.php?route=students_by_class |  |  |
| 100044 | students_oversview | home.php?route=students_overview |  |  |
| 100043 | students_overview | home.php?route=students_overview |  |  |
| 100052 | subject_students_list | home.php?route=subject_students_list |  |  |
| 100049 | transport_passengers | home.php?route=transport_passengers |  |  |
| 100054 | view_class_lists | home.php?route=view_class_lists |  |  |
| 100062 | auth_refresh_token | /api/auth/refresh-token | AuthController | postRefreshToken |
| 100063 | auth_logout_refresh | /api/auth/logout-refresh | AuthController | postLogoutRefresh |
| 100471 | Complete Staff Profile | complete_staff_profile |  |  |
