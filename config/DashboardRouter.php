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

    // Auto-generated from role_sidebars.php — do not edit manually
    // Run: php scripts/generate_route_roles.php
    private const ROUTE_ROLES = [
        'academic_calendar' => [5, 6],  // 2 role(s)
        'academic_reports' => [3, 4, 5, 6],  // 4 role(s)
        'academic_students' => [6],  // 1 role(s)
        'academic_years' => [3, 4, 5, 6],  // 4 role(s)
        'account_status' => [2],  // 1 role(s)
        'active_sessions' => [2],  // 1 role(s)
        'activity_audit_logs' => [2],  // 1 role(s)
        'adjustments' => [10],  // 1 role(s)
        'admission_interviews' => [5],  // 1 role(s)
        'admissions_academic_applications' => [6],  // 1 role(s)
        'admissions_admission_decisions' => [5],  // 1 role(s)
        'admissions_class_placement' => [4, 6],  // 2 role(s)
        'admissions_headteacher_applications' => [5],  // 1 role(s)
        'admissions_pending_admission_approvals' => [5],  // 1 role(s)
        'all_lesson_plans' => [6],  // 1 role(s)
        'all_parents' => [3, 4, 5],  // 3 role(s)
        'all_students' => [21],  // 1 role(s)
        'all_teachers' => [3, 4, 5, 6, 63],  // 5 role(s)
        'alumni_management' => [3, 4, 5, 6],  // 4 role(s)
        'api_explorer' => [2],  // 1 role(s)
        'api_metrics' => [2],  // 1 role(s)
        'assemblies' => [4, 21],  // 2 role(s)
        'assessment_overview' => [2],  // 1 role(s)
        'assessment_rubrics' => [4, 5, 6],  // 3 role(s)
        'asset_purchases' => [10],  // 1 role(s)
        'assign_class_teachers' => [6],  // 1 role(s)
        'assign_subjects_to_teachers' => [6],  // 1 role(s)
        'at_risk_students' => [24],  // 1 role(s)
        'attendance_reports' => [3, 4, 5, 6, 18, 63],  // 6 role(s)
        'attendance_trends' => [3, 6, 63],  // 3 role(s)
        'audit_logs' => [10],  // 1 role(s)
        'authentication_logs' => [2],  // 1 role(s)
        'background_jobs' => [2],  // 1 role(s)
        'backups' => [2],  // 1 role(s)
        'bank_accounts' => [3, 10],  // 2 role(s)
        'bank_transactions' => [3, 10],  // 2 role(s)
        'boarding_roll_call' => [4, 5, 18],  // 3 role(s)
        'boarding_students' => [18],  // 1 role(s)
        'budget_overview' => [3, 5, 10],  // 3 role(s)
        'cash_reconciliation' => [10],  // 1 role(s)
        'catering_boarding_students' => [16, 32],  // 2 role(s)
        'catering_manager_cook_lead_dashboard' => [16],  // 1 role(s)
        'cbc_curriculum' => [4, 5, 6],  // 3 role(s)
        'chapel_services' => [24],  // 1 role(s)
        'class_attendance_history' => [7],  // 1 role(s)
        'class_capacity' => [4, 5],  // 2 role(s)
        'class_conduct_grades' => [7],  // 1 role(s)
        'class_parent_contacts' => [7],  // 1 role(s)
        'class_report_cards' => [7],  // 1 role(s)
        'class_results' => [7],  // 1 role(s)
        'class_streams' => [4, 5, 6],  // 3 role(s)
        'class_teacher_dashboard' => [7],  // 1 role(s)
        'clubs_societies' => [21],  // 1 role(s)
        'communications/messages_inbox' => [2, 3, 4, 5, 6, 7, 8, 9, 10, 14, 16, 18, 21, 23, 24, 32, 33, 34, 63, 64],  // 20 role(s)
        'comparative_reports' => [3, 5, 6],  // 3 role(s)
        'competencies_sheet' => [6, 7, 63],  // 3 role(s)
        'competency_checklist' => [9],  // 1 role(s)
        'competitions' => [21],  // 1 role(s)
        'conduct_reports' => [63],  // 1 role(s)
        'counseling_records' => [24],  // 1 role(s)
        'counseling_referrals' => [24],  // 1 role(s)
        'create_assessment' => [7],  // 1 role(s)
        'create_subject_cat' => [8],  // 1 role(s)
        'curriculum_cbc' => [4, 5, 6],  // 3 role(s)
        'data_retention' => [2],  // 1 role(s)
        'depreciation' => [10],  // 1 role(s)
        'deputy_head_academic_dashboard' => [6],  // 1 role(s)
        'deputy_head_discipline_dashboard' => [63],  // 1 role(s)
        'detailed_payslip' => [6, 32, 33, 34, 63, 64],  // 6 role(s)
        'development_progress' => [9],  // 1 role(s)
        'director_owner_dashboard' => [3],  // 1 role(s)
        'discipline_cases' => [3, 4, 5, 63],  // 4 role(s)
        'discipline_students' => [63],  // 1 role(s)
        'domain_isolation_rules' => [2],  // 1 role(s)
        'dormitory_management' => [3, 18],  // 2 role(s)
        'driver_dashboard' => [23],  // 1 role(s)
        'enrollment_reports' => [3, 4, 5, 10],  // 4 role(s)
        'enrollment_trends' => [6],  // 1 role(s)
        'enter_exam_results' => [8],  // 1 role(s)
        'enter_marks' => [7],  // 1 role(s)
        'error_logs' => [2],  // 1 role(s)
        'exam_moderation' => [4, 5],  // 2 role(s)
        'exam_schedule' => [3, 4, 5, 6, 7],  // 5 role(s)
        'exam_setup' => [5, 6],  // 2 role(s)
        'exception_reports' => [10],  // 1 role(s)
        'failed_login_attempts' => [2],  // 1 role(s)
        'feature_flags' => [2],  // 1 role(s)
        'fee_defaulters' => [4, 10],  // 2 role(s)
        'finance_approvals' => [3],  // 1 role(s)
        'finance_reports' => [3, 4, 5, 10],  // 4 role(s)
        'food_store' => [14, 16, 32],  // 3 role(s)
        'formative_assessments' => [6, 63],  // 2 role(s)
        'generate_class_report' => [7],  // 1 role(s)
        'generate_subject_report' => [8],  // 1 role(s)
        'grade_entry' => [7],  // 1 role(s)
        'grading_scales' => [4, 5, 6],  // 3 role(s)
        'grading_status' => [4, 5, 6],  // 3 role(s)
        'headteacher_dashboard' => [5],  // 1 role(s)
        'hod_talent_development_dashboard' => [21],  // 1 role(s)
        'import_existing_staff' => [3, 4],  // 2 role(s)
        'improvement_areas' => [9],  // 1 role(s)
        'intern_assigned_classes' => [9],  // 1 role(s)
        'intern_assigned_subjects' => [9],  // 1 role(s)
        'intern_schedule' => [9],  // 1 role(s)
        'intern_student_teacher_dashboard' => [9],  // 1 role(s)
        'intervention_plans' => [24],  // 1 role(s)
        'inventory_expenses' => [10],  // 1 role(s)
        'ip_whitelist_blacklist' => [2],  // 1 role(s)
        'job_inspector' => [2],  // 1 role(s)
        'learning_goals' => [9],  // 1 role(s)
        'lesson_plan_approval' => [5, 6],  // 2 role(s)
        'lesson_plans_by_class' => [6],  // 1 role(s)
        'lesson_plans_by_teacher' => [6],  // 1 role(s)
        'log_discipline_case' => [63],  // 1 role(s)
        'log_discipline_incident' => [7],  // 1 role(s)
        'maintenance_mode' => [2],  // 1 role(s)
        'manage_activities' => [3, 5, 21],  // 3 role(s)
        'manage_announcements' => [2, 3, 4, 5, 6, 7, 8, 9, 10, 14, 16, 18, 21, 23, 24, 32, 33, 34, 63, 64],  // 20 role(s)
        'manage_boarding' => [3, 4, 5, 18],  // 4 role(s)
        'manage_calendar_events' => [4, 21],  // 2 role(s)
        'manage_classes' => [3, 4, 5, 6],  // 4 role(s)
        'manage_communications' => [4, 18, 63],  // 3 role(s)
        'manage_email' => [2, 3, 4, 6, 10],  // 5 role(s)
        'manage_expenses' => [10],  // 1 role(s)
        'manage_family_groups' => [4],  // 1 role(s)
        'manage_fee_structure' => [3, 4, 5, 10],  // 4 role(s)
        'manage_inventory' => [3, 14],  // 2 role(s)
        'manage_lesson_plans' => [5, 6, 7, 8, 9, 63],  // 6 role(s)
        'manage_library' => [3, 4, 5, 6, 14, 63],  // 6 role(s)
        'manage_menus' => [16],  // 1 role(s)
        'manage_holidays' => [4],  // 1 role(s)
        'manage_staff_meetings' => [3, 4, 5, 6, 21, 63],  // 6 role(s)
        'manage_payments' => [3, 4, 10],  // 3 role(s)
        'manage_payrolls' => [3, 4, 10],  // 3 role(s)
        'manage_requisitions' => [14],  // 1 role(s)
        'manage_roles' => [2],  // 1 role(s)
        'manage_sms' => [2, 3, 4, 5, 10],  // 5 role(s)
        'manage_staff' => [3, 4, 5, 63],  // 4 role(s)
        'manage_stock' => [14],  // 1 role(s)
        'manage_students' => [4],  // 1 role(s)
        'manage_students_admissions' => [4],  // 1 role(s)
        'manage_subjects' => [5, 6],  // 2 role(s)
        'manage_terms' => [3, 4],  // 2 role(s)
        'manage_timetable' => [3, 4, 5, 6, 7],  // 5 role(s)
        'manage_transport' => [3, 4, 5],  // 3 role(s)
        'manage_uniform_sales' => [3, 4, 14],  // 3 role(s)
        'manage_users' => [2],  // 1 role(s)
        'manage_website' => [2, 3, 4, 5, 6, 21, 63],  // 7 role(s)
        'manage_whatsapp' => [2],  // 1 role(s)
        'mark_attendance' => [6, 7, 8, 23, 63],  // 5 role(s)
        'matron_housemother_dashboard' => [18],  // 1 role(s)
        'mentor_meetings' => [9],  // 1 role(s)
        'mentor_notes' => [9],  // 1 role(s)
        'menu_planning' => [16],  // 1 role(s)
        'migrations' => [2],  // 1 role(s)
        'module_enablement' => [2],  // 1 role(s)
        'mpesa_reconciliation' => [10],  // 1 role(s)
        'mpesa_settlements' => [3, 10],  // 2 role(s)
        'my_attendance' => [6, 32, 33, 34, 63, 64],  // 6 role(s)
        'my_cats' => [7],  // 1 role(s)
        'my_classes_taught' => [8],  // 1 role(s)
        'my_mentor' => [9],  // 1 role(s)
        'my_routes' => [3, 4, 23],  // 3 role(s)
        'my_schemes_of_work' => [7],  // 1 role(s)
        'my_students_list' => [6, 7, 63],  // 3 role(s)
        'my_students_performance' => [7],  // 1 role(s)
        'my_subject_cats' => [8],  // 1 role(s)
        'my_subject_syllabus' => [8],  // 1 role(s)
        'my_subjects_overview' => [8],  // 1 role(s)
        'my_vehicle' => [23],  // 1 role(s)
        'national_exams' => [6],  // 1 role(s)
        'new_applications' => [4],  // 1 role(s)
        'observation_feedback' => [9],  // 1 role(s)
        'observation_schedule' => [9],  // 1 role(s)
        'parent_meeting_records' => [7, 24],  // 2 role(s)
        'parent_meetings' => [4, 5, 63],  // 3 role(s)
        'past_papers' => [8],  // 1 role(s)
        'payroll' => [3, 4, 10],  // 3 role(s)
        'payslips' => [3, 10],  // 2 role(s)
        'performance_analysis' => [5, 6],  // 2 role(s)
        'performance_reports' => [3],  // 1 role(s)
        'permission_changes' => [2],  // 1 role(s)
        'permission_policies' => [2],  // 1 role(s)
        'permissions_exeats' => [3, 4, 18, 33],  // 4 role(s)
        'petty_cash' => [3, 10],  // 2 role(s)
        'placement_tests' => [4, 6],  // 2 role(s)
        'policy_violations' => [2, 63],  // 2 role(s)
        'pta_management' => [3, 4, 5],  // 3 role(s)
        'purchase_orders' => [3, 10, 14, 16],  // 4 role(s)
        'rate_limiting_status' => [2],  // 1 role(s)
        'reflection_journal' => [9],  // 1 role(s)
        'report_cards' => [3, 4, 5, 6],  // 4 role(s)
        'resource_based_permissions' => [2],  // 1 role(s)
        'results_analysis' => [5, 6],  // 2 role(s)
        'role_navigation_config' => [2],  // 1 role(s)
        'role_permission_matrix' => [2],  // 1 role(s)
        'route_access_rules' => [2],  // 1 role(s)
        'route_registry' => [2],  // 1 role(s)
        'salary_advances' => [2],  // 1 role(s)
        'schedule_parent_meetings' => [24],  // 1 role(s)
        'schemes_of_work' => [5, 6, 63],  // 3 role(s)
        'school_accountant_dashboard' => [10],  // 1 role(s)
        'school_administrative_officer_dashboard' => [4],  // 1 role(s)
        'school_counselor_chaplain_dashboard' => [24],  // 1 role(s)
        'school_events' => [3, 4, 5, 21],  // 4 role(s)
        'school_initialization' => [2],  // 1 role(s)
        'security_incidents' => [2],  // 1 role(s)
        'send_class_message' => [7],  // 1 role(s)
        'send_parent_notifications' => [24],  // 1 role(s)
        'sick_bay' => [4, 18],  // 2 role(s)
        'sidebar_menus' => [2],  // 1 role(s)
        'special_needs' => [3, 4, 5, 6, 18, 24, 63],  // 7 role(s)
        'special_needs_students' => [7],  // 1 role(s)
        'sports' => [3, 21],  // 2 role(s)
        'staff_appointments' => [3, 4, 5, 63],  // 4 role(s)
        'staff_attendance' => [3, 4, 5, 63],  // 4 role(s)
        'staff_id_cards' => [4],  // 1 role(s)
        'staff_leave' => [3, 4, 5, 32, 33, 34, 63, 64],  // 8 role(s)
        'staff_lifecycle' => [3, 4, 5, 63],  // 4 role(s)
        'staff_onboarding' => [3, 4, 5, 63],  // 4 role(s)
        'staff_performance' => [3, 4, 63],  // 3 role(s)
        'staff_role_assignments' => [4],  // 1 role(s)
        'staff_schedule' => [2],  // 1 role(s)
        'store_manager_dashboard' => [14],  // 1 role(s)
        'student_behavior_notes' => [7],  // 1 role(s)
        'student_counseling' => [5, 24, 63],  // 3 role(s)
        'student_fees' => [3, 4, 10],  // 3 role(s)
        'student_health' => [4, 5, 18],  // 3 role(s)
        'student_id_cards' => [4],  // 1 role(s)
        'student_performance' => [3, 5, 6],  // 3 role(s)
        'student_portfolio' => [4, 5, 6, 7, 8],  // 5 role(s)
        'student_profiles' => [4, 7, 18, 63],  // 4 role(s)
        'student_progress_reports' => [7],  // 1 role(s)
        'student_promotion' => [4, 6],  // 2 role(s)
        'student_subject_performance' => [8],  // 1 role(s)
        'student_timeline' => [2],  // 1 role(s)
        'student_welfare' => [24, 63],  // 2 role(s)
        'students_by_class' => [8],  // 1 role(s)
        'students_overview' => [3, 5],  // 2 role(s)
        'students_with_balance' => [4, 10],  // 2 role(s)
        'subject_class_comparison' => [8],  // 1 role(s)
        'subject_exam_schedule' => [8],  // 1 role(s)
        'subject_grade_entry' => [8],  // 1 role(s)
        'subject_grading_status' => [8],  // 1 role(s)
        'subject_results_summary' => [8],  // 1 role(s)
        'subject_schemes_of_work' => [8],  // 1 role(s)
        'subject_students_list' => [8],  // 1 role(s)
        'subject_teacher_dashboard' => [8],  // 1 role(s)
        'supervision_roster' => [4, 5, 6],  // 3 role(s)
        'support_staff_dashboard' => [32, 33, 34, 64],  // 4 role(s)
        'system_administrator_dashboard' => [2],  // 1 role(s)
        'system_diagnostics' => [2],  // 1 role(s)
        'system_health' => [2],  // 1 role(s)
        'system_settings' => [2],  // 1 role(s)
        'teacher_performance_reviews' => [5, 6, 63],  // 3 role(s)
        'teacher_workload' => [3, 5, 6],  // 3 role(s)
        'teaching_materials' => [8],  // 1 role(s)
        'term_reports' => [5, 6, 63],  // 3 role(s)
        'term_transition' => [2, 4],  // 2 role(s)
        'time_bound_access' => [2],  // 1 role(s)
        'timetable' => [6, 7, 8, 9, 63, 64],  // 6 role(s)
        'today_absentees' => [7],  // 1 role(s)
        'token_management' => [2],  // 1 role(s)
        'transaction_approvals' => [10],  // 1 role(s)
        'transport_passengers' => [23],  // 1 role(s)
        'unmatched_payments' => [3, 4, 10],  // 3 role(s)
        'upload_teaching_resource' => [8],  // 1 role(s)
        'vendor_invoices' => [14],  // 1 role(s)
        'vendors' => [3, 10, 14, 16],  // 4 role(s)
        'view_attendance' => [3, 4, 5, 6, 8, 63],  // 6 role(s)
        'view_calendar' => [21],  // 1 role(s)
        'view_class_lists' => [9],  // 1 role(s)
        'view_past_papers' => [9],  // 1 role(s)
        'view_results' => [3, 4, 5, 6],  // 4 role(s)
        'view_student_info' => [9],  // 1 role(s)
        'view_syllabus' => [9],  // 1 role(s)
        'view_teaching_materials' => [9],  // 1 role(s)
        'webhook_registry' => [2],  // 1 role(s)
        'welfare_follow_ups' => [24],  // 1 role(s)
        'year_calendar' => [3, 4],  // 2 role(s)
        'year_rollover' => [2, 4],  // 2 role(s)
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
