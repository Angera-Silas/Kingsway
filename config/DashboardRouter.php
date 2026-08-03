<?php
/**
 * Dashboard component registry and safe fallback mapper.
 *
 * Runtime role assignments are read from `role_dashboards` + `dashboards` by
 * the authentication layer. This registry mirrors the approved production
 * mapping, validates component/controller availability, and provides a safe
 * access-denied fallback when database configuration is missing or invalid.
 */
namespace App\Config;

class DashboardRouter
{
    // role_id => dashboard file key (without .php)
    private const ROLE_DASHBOARDS = [
        2  => 'system_administrator_dashboard',
        3  => 'director_owner_dashboard',
        4  => 'school_administrative_officer_dashboard',
        5  => 'headteacher_dashboard',
        6  => 'deputy_head_academic_dashboard',
        7  => 'class_teacher_dashboard',
        8  => 'subject_teacher_dashboard',
        9  => 'intern_student_teacher_dashboard',
        10 => 'school_accountant_dashboard',
        14 => 'store_manager_dashboard',
        16 => 'catering_manager_cook_lead_dashboard',
        18 => 'matron_housemother_dashboard',
        21 => 'hod_talent_development_dashboard',
        23 => 'driver_dashboard',
        24 => 'school_counselor_chaplain_dashboard',
        32 => 'support_staff_dashboard',
        33 => 'support_staff_dashboard',
        34 => 'support_staff_dashboard',
        63 => 'deputy_head_discipline_dashboard',
        64 => 'support_staff_dashboard',
    ];

    private const DEFAULT_DASHBOARD = 'dashboard_access_denied';

    private const DASHBOARD_ALIASES = [
        'dashboard' => self::DEFAULT_DASHBOARD,
        'home' => self::DEFAULT_DASHBOARD,
        'director_dashboard' => 'director_owner_dashboard',
        'school_admin_dashboard' => 'school_administrative_officer_dashboard',
    ];

    // route → allowed role IDs (auto-generated from role_sidebars.php)
    // Run: php scripts/generate_route_roles.php
    private const ROUTE_ROLES = [
        'academic_calendar' => [5, 6],
        'academic_reports' => [3, 4, 5, 6],
        'academic_students' => [6],
        'academic_years' => [3, 4, 5, 6],
        'account_status' => [2],
        'active_sessions' => [2],
        'activity_audit_logs' => [2],
        'adjustments' => [10],
        'admission_interviews' => [5],
        'admissions_academic_applications' => [6],
        'admissions_admission_decisions' => [5],
        'admissions_class_placement' => [4, 6],
        'admissions_headteacher_applications' => [5],
        'admissions_pending_admission_approvals' => [5],
        'all_lesson_plans' => [6],
        'all_parents' => [3, 4, 5],
        'all_students' => [21],
        'all_teachers' => [3, 4, 5, 6, 63],
        'alumni_management' => [3, 4, 5, 6],
        'api_explorer' => [2],
        'student_portfolio' => [5, 6, 7, 8, 9],
        'api_metrics' => [2],
        'assemblies' => [4, 21],
        'asset_purchases' => [10],
        'assign_class_teachers' => [6],
        'assign_subjects_to_teachers' => [6],
        'at_risk_students' => [24],
        'attendance_reports' => [3, 4, 5, 6, 18, 63],
        'attendance_trends' => [3, 6, 63],
        'audit_logs' => [10],
        'authentication_logs' => [2],
        'background_jobs' => [2],
        'backups' => [2],
        'bank_accounts' => [3, 10],
        'bank_transactions' => [3, 10],
        'boarding_roll_call' => [4, 5, 18],
        'boarding_students' => [18],
        'budget_overview' => [3, 5, 10],
        'cash_reconciliation' => [10],
        'catering_boarding_students' => [16, 32],
        'catering_manager_cook_lead_dashboard' => [16],
        'chapel_services' => [24],
        'class_attendance_history' => [7],
        'class_capacity' => [4],
        'class_conduct_grades' => [7],
        'class_parent_contacts' => [7],
        'class_report_cards' => [7],
        'class_results' => [7],
        'class_streams' => [4, 6],
        'class_teacher_dashboard' => [7],
        'clubs_societies' => [21],
        'comparative_reports' => [3, 5, 6],
        'competencies_sheet' => [6, 7, 63],
        'competency_checklist' => [9],
        'competitions' => [21],
        'complete_staff_profile' => [2,3,4,5,6,7,8,9,10,14,16,18,21,23,24,32,33,34,63,64,65,66,67,68,69,70],
        'conduct_reports' => [63],
        'counseling_records' => [24],
        'counseling_referrals' => [24],
        'create_assessment' => [7],
        'create_subject_cat' => [8],
        'curriculum_cbc' => [5, 6],
        'cbc_curriculum' => [5, 6],
        'assessment_rubrics' => [5, 6],
        'grading_scales' => [1, 5, 6],
        'data_retention' => [2],
        'depreciation' => [10],
        'deputy_head_academic_dashboard' => [6],
        'deputy_head_discipline_dashboard' => [63],
        'detailed_payslip' => [6, 32, 33, 34, 63, 64],
        'development_progress' => [9],
        'director_owner_dashboard' => [3],
        'discipline_cases' => [3, 4, 5, 63],
        'discipline_students' => [63],
        'domain_isolation_rules' => [2],
        'dormitory_management' => [3, 18],
        'driver_dashboard' => [23],
        'enrollment_reports' => [3, 4, 5, 10],
        'enrollment_trends' => [6],
        'enter_exam_results' => [8],
        'enter_marks' => [7],
        'error_logs' => [2],
        'exam_schedule' => [3, 4, 5, 6, 7],
        'exam_setup' => [5, 6],
        'exam_moderation' => [5, 6, 8],
        'exception_reports' => [10],
        'failed_login_attempts' => [2],
        'feature_flags' => [2],
        'fee_defaulters' => [4, 10],
        'finance_approvals' => [3],
        'finance_reports' => [3, 4, 5, 10],
        'food_store' => [14, 16, 32],
        'formative_assessments' => [6, 63],
        'generate_class_report' => [7],
        'generate_subject_report' => [8],
        'grade_entry' => [7],
        'grading_status' => [5, 6],
        'headteacher_dashboard' => [5],
        'hod_talent_development_dashboard' => [21],
        'import_existing_staff' => [3, 4],
        'improvement_areas' => [9],
        'intern_assigned_classes' => [9],
        'intern_assigned_subjects' => [9],
        'intern_schedule' => [9],
        'intern_student_teacher_dashboard' => [9],
        'intervention_plans' => [24],
        'inventory_expenses' => [10],
        'ip_whitelist_blacklist' => [2],
        'job_inspector' => [2],
        'learning_goals' => [9],
        'lesson_plan_approval' => [5, 6],
        'lesson_plans_by_class' => [6],
        'lesson_plans_by_teacher' => [6],
        'log_discipline_case' => [63],
        'log_discipline_incident' => [7],
        'maintenance_mode' => [2],
        'manage_activities' => [3, 5, 21],
        'manage_announcements' => [3, 4, 5, 6, 7, 8, 9, 14, 16, 18, 21, 23, 24, 32, 33, 34, 63, 64],
        'manage_boarding' => [3, 4, 5, 18],
        'manage_calendar_events' => [4, 21],
        'manage_classes' => [3, 4, 5, 6],
        'manage_communications' => [3, 4, 5, 6, 7, 8, 9, 14, 18, 21, 23, 24, 32, 33, 34, 63, 64],
        'manage_email' => [3, 4, 6],
        'manage_expenses' => [10],
        'manage_family_groups' => [4],
        'manage_fee_structure' => [3, 4, 5, 10],
        'manage_inventory' => [3, 14],
        'manage_lesson_plans' => [5, 6, 7, 8, 9, 63],
        'manage_library' => [3, 4, 5, 6, 14, 63],
        'manage_menus' => [16],
        'manage_payments' => [3, 4, 10],
        'manage_payrolls' => [3, 4, 10],
        'manage_requisitions' => [14],
        'manage_roles' => [2],
        'manage_sms' => [3, 4, 5],
        'manage_staff' => [3, 4, 5, 63],
        'manage_stock' => [14],
        'manage_students' => [4],
        'manage_students_admissions' => [4],
        'manage_subjects' => [5, 6],
        'manage_terms' => [3],
        'manage_timetable' => [3, 4, 5, 6, 7],
        'manage_transport' => [3, 4, 5],
        'manage_uniform_sales' => [3, 4, 14],
        'manage_users' => [2],
        'mark_attendance' => [6, 7, 8, 23, 63],
        'matron_housemother_dashboard' => [18],
        'mentor_meetings' => [9],
        'mentor_notes' => [9],
        'menu_planning' => [16],
        'migrations' => [2],
        'module_enablement' => [2],
        'mpesa_reconciliation' => [10],
        'mpesa_settlements' => [3, 10],
        'my_attendance' => [6, 32, 33, 34, 63, 64],
        'my_cats' => [7],
        'my_classes_taught' => [8],
        'my_mentor' => [9],
        'my_routes' => [3, 4, 23],
        'my_schemes_of_work' => [7],
        'my_students_list' => [6, 7, 63],
        'my_students_performance' => [7],
        'my_subject_cats' => [8],
        'my_subject_syllabus' => [8],
        'my_subjects_overview' => [8],
        'my_vehicle' => [23],
        'national_exams' => [6],
        'new_applications' => [4],
        'observation_feedback' => [9],
        'observation_schedule' => [9],
        'parent_meeting_records' => [7, 24],
        'parent_meetings' => [4, 5, 63],
        'past_papers' => [8],
        'payroll' => [3, 4, 10],
        'payslips' => [3, 10],
        'performance_analysis' => [5, 6],
        'performance_reports' => [3],
        'permission_changes' => [2],
        'permission_policies' => [2],
        'permissions_exeats' => [3, 4, 18, 33],
        'petty_cash' => [3, 10],
        'placement_tests' => [4, 6],
        'policy_violations' => [2, 63],
        'pta_management' => [3, 4, 5],
        'purchase_orders' => [3, 10, 14, 16],
        'rate_limiting_status' => [2],
        'reflection_journal' => [9],
        'report_cards' => [3, 4, 5, 6],
        'resource_based_permissions' => [2],
        'results_analysis' => [5, 6],
        'role_navigation_config' => [2],
        'role_permission_matrix' => [2],
        'route_access_rules' => [2],
        'route_registry' => [2],
        'schedule_parent_meetings' => [24],
        'schemes_of_work' => [5, 6, 63],
        'school_accountant_dashboard' => [10],
        'school_administrative_officer_dashboard' => [4],
        'school_counselor_chaplain_dashboard' => [24],
        'school_events' => [3, 4, 5, 21],
        'security_incidents' => [2],
        'send_class_message' => [7],
        'send_parent_notifications' => [24],
        'sick_bay' => [4, 18],
        'sidebar_menus' => [2],
        'special_needs' => [3, 4, 5, 6, 18, 24, 63],
        'special_needs_students' => [7],
        'sports' => [3, 21],
        'staff_appointments' => [3, 4, 5, 63],
        'staff_attendance' => [3, 4, 5, 63],
        'staff_id_cards' => [4],
        'staff_leave' => [3, 4, 5, 32, 33, 34, 63, 64],
        'staff_lifecycle' => [3, 4, 5, 63],
        'staff_onboarding' => [3, 4, 5, 63],
        'staff_performance' => [3, 4, 63],
        'staff_role_assignments' => [4],
        'store_manager_dashboard' => [14],
        'student_behavior_notes' => [7],
        'student_counseling' => [5, 24, 63],
        'student_fees' => [3, 4, 10],
        'student_health' => [4, 5, 18],
        'student_id_cards' => [4],
        'student_performance' => [3, 5, 6],
        'student_profiles' => [4, 7, 18, 63],
        'student_progress_reports' => [7],
        'student_promotion' => [4, 6],
        'student_subject_performance' => [8],
        'student_welfare' => [24, 63],
        'students_by_class' => [8],
        'students_overview' => [3, 5],
        'students_with_balance' => [4, 10],
        'subject_class_comparison' => [8],
        'subject_exam_schedule' => [8],
        'subject_grade_entry' => [8],
        'subject_grading_status' => [8],
        'subject_results_summary' => [8],
        'subject_schemes_of_work' => [8],
        'subject_students_list' => [8],
        'subject_teacher_dashboard' => [8],
        'supervision_roster' => [5, 6],
        'support_staff_dashboard' => [32, 33, 34, 64],
        'system_administrator_dashboard' => [2],
        'system_diagnostics' => [2],
        'system_health' => [2],
        'system_settings' => [2],
        'teacher_performance_reviews' => [5, 6, 63],
        'teacher_workload' => [3, 5, 6],
        'teaching_materials' => [8],
        'term_dates' => [3],
        'term_reports' => [5, 6, 63],
        'time_bound_access' => [2],
        'timetable' => [6, 7, 8, 9, 63, 64],
        'today_absentees' => [7],
        'token_management' => [2],
        'transaction_approvals' => [10],
        'transport_passengers' => [23],
        'unmatched_payments' => [3, 4, 10],
        'upload_teaching_resource' => [8],
        'vendor_invoices' => [14],
        'vendors' => [3, 10, 14, 16],
        'view_attendance' => [3, 4, 5, 6, 8, 63],
        'view_calendar' => [21],
        'view_class_lists' => [9],
        'view_past_papers' => [9],
        'view_results' => [3, 4, 5, 6],
        'view_student_info' => [9],
        'view_syllabus' => [9],
        'view_teaching_materials' => [9],
        'webhook_registry' => [2],
        'welfare_follow_ups' => [24],
        'year_calendar' => [3],
    ];

    // role name → role_id for string-based lookups
    private const ROLE_NAME_MAP = [
        'system administrator'    => 2,
        'director'                => 3,
        'school administrator'    => 4,
        'headteacher'             => 5,
        'deputy head - academic'  => 6,
        'deputy head academic'    => 6,
        'class teacher'           => 7,
        'subject teacher'         => 8,
        'intern/student teacher'  => 9,
        'intern student teacher'  => 9,
        'accountant'              => 10,
        'school accountant'       => 10,
        'inventory manager'       => 14,
        'store manager'           => 14,
        'cateress'                => 16,
        'catering manager'        => 16,
        'boarding master'         => 18,
        'matron'                  => 18,
        'housemother'             => 18,
        'talent development'      => 21,
        'hod talent development'  => 21,
        'driver'                  => 23,
        'chaplain'                => 24,
        'counselor'               => 24,
        'school counselor'        => 24,
        'kitchen staff'           => 32,
        'security staff'          => 33,
        'janitor'                 => 34,
        'deputy head - discipline'=> 63,
        'deputy head discipline'  => 63,
        'staff'                   => 64,
    ];

    /**
     * Get dashboard route for a role (by ID, name, or array of roles).
     */
    public static function getDashboardForRole($role): string
    {
        if (is_array($role)) {
            if (empty($role)) return self::DEFAULT_DASHBOARD;
            $role = $role[0]['id'] ?? $role[0]['name'] ?? $role[0];
        }

        if (is_numeric($role)) {
            return self::ROLE_DASHBOARDS[(int)$role] ?? self::DEFAULT_DASHBOARD;
        }

        // String name lookup — normalise and match
        $key = strtolower(trim((string)$role));
        if (isset(self::ROLE_NAME_MAP[$key])) {
            $id = self::ROLE_NAME_MAP[$key];
            return self::ROLE_DASHBOARDS[$id] ?? self::DEFAULT_DASHBOARD;
        }

        // Unknown or newly-created roles must be explicitly assigned in the
        // database and mirrored in this fallback registry. Never infer a
        // privileged dashboard from an arbitrary role name.
        return self::DEFAULT_DASHBOARD;
    }

    public static function normalizeDashboardKey(string $key): string
    {
        $key = trim($key);
        return self::DASHBOARD_ALIASES[$key] ?? $key;
    }

    public static function getDefaultDashboard(): string
    {
        return self::DEFAULT_DASHBOARD;
    }

    public static function getRoleDashboards(): array
    {
        return self::ROLE_DASHBOARDS;
    }

    public static function getRoleNameMap(): array
    {
        return self::ROLE_NAME_MAP;
    }

    public static function dashboardExists(string $key): bool
    {
        $key = self::normalizeDashboardKey($key);
        return file_exists(__DIR__ . '/../components/dashboards/' . $key . '.php');
    }

    public static function getDashboardPath(string $key): ?string
    {
        $key = self::normalizeDashboardKey($key);
        $path = __DIR__ . '/../components/dashboards/' . $key . '.php';
        return file_exists($path) ? $path : null;
    }

    public static function getDashboardJsPath(string $key): ?string
    {
        $key = self::normalizeDashboardKey($key);
        $path = __DIR__ . '/../js/dashboards/' . $key . '.js';
        return file_exists($path) ? $path : null;
    }

    public static function getDashboardRegistry(): array
    {
        $registry = [];
        foreach (array_unique(self::ROLE_DASHBOARDS) as $key) {
            $registry[$key] = [
                'key' => $key,
                'php' => self::dashboardExists($key),
                'js' => self::getDashboardJsPath($key) !== null,
            ];
        }

        if (!isset($registry[self::DEFAULT_DASHBOARD])) {
            $registry[self::DEFAULT_DASHBOARD] = [
                'key' => self::DEFAULT_DASHBOARD,
                'php' => self::dashboardExists(self::DEFAULT_DASHBOARD),
                'js' => false,
            ];
        }

        foreach (self::DASHBOARD_ALIASES as $alias => $target) {
            $registry[$alias] = [
                'key' => $alias,
                'alias_for' => $target,
                'php' => self::dashboardExists($target),
                'js' => self::getDashboardJsPath($target) !== null,
            ];
        }

        ksort($registry);
        return array_values($registry);
    }

    public static function getDashboardUrl($role): string
    {
        return '?route=' . self::getDashboardForRole($role);
    }

    /** Returns the approved fallback role-to-dashboard pairs. */
    public static function getAllDashboards(): array
    {
        $result = [];
        foreach (self::ROLE_DASHBOARDS as $roleId => $key) {
            if (isset($result[$key])) continue; // dedupe support_staff_dashboard etc.
            $result[$key] = [
                'name'      => $key,
                'title'     => ucwords(str_replace('_', ' ', str_replace('_dashboard', '', $key))),
                'is_active' => 1,
            ];
        }
        return array_values($result);
    }

    /** Returns fallback dashboard information for one role. */
    public static function getDashboardsForRole(int $roleId): array
    {
        $key = self::ROLE_DASHBOARDS[$roleId] ?? null;
        if (!$key) return [];
        return [[
            'name'       => $key,
            'title'      => ucwords(str_replace('_', ' ', str_replace('_dashboard', '', $key))),
            'is_primary' => 1,
            'is_active'  => 1,
        ]];
    }

    /**
     * Check if a route is allowed for a given role ID.
     * Returns true if the route has no explicit restriction (open route)
     * or if the role is in the allowed list.
     */
    public static function isRouteAllowedForRole(string $route, int $roleId): bool
    {
        // Dashboard routes bypass the allowlist (each role has its own dashboard)
        $dashboardKeys = array_values(self::ROLE_DASHBOARDS);
        if (in_array($route, $dashboardKeys, true)) {
            return ($roleId === 2) // System Admin can reach all dashboards
                || (self::ROLE_DASHBOARDS[$roleId] ?? null) === $route;
        }

        // If no restriction defined, the route is public within the app shell
        $allowed = self::ROUTE_ROLES[$route] ?? null;
        if ($allowed === null) {
            return true;
        }

        return in_array($roleId, $allowed, true);
    }

    /** @deprecated Use getDashboardForRole() */
    public static function getUserDefaultDashboard(): string
    {
        return self::DEFAULT_DASHBOARD;
    }

    public static function redirectToDefaultDashboard(bool $exit = true): void
    {
        header('Location: ?route=' . self::DEFAULT_DASHBOARD);
        if ($exit) exit;
    }

    // No-op: this fallback registry has no runtime cache.
    public static function clearCache(): void {}
}
