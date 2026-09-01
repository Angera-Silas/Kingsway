<?php
/** Module-owned report routes. Roles here control both navigation and page access. */
return [
    ['module' => 'Enrollment', 'route' => 'report_current_enrollment_by_class', 'code' => 'ENR_CURRENT_BY_CLASS', 'title' => 'Current Enrollment by Class & Stream', 'roles' => [3, 4, 5, 6, 7]],
    ['module' => 'Enrollment', 'route' => 'report_enrollment_trend', 'code' => 'ENR_MONTHLY_TREND', 'title' => 'Enrollment Trend', 'roles' => [3, 4, 5]],
    ['module' => 'Enrollment', 'route' => 'report_enrolled_admissions_cohort', 'code' => 'ADM_ENROLLED_COHORT', 'title' => 'Enrolled Admissions by Cohort', 'roles' => [3, 4, 5]],
    ['module' => 'Enrollment', 'route' => 'report_admission_conversion', 'code' => 'ADM_CONVERSION', 'title' => 'Admission Conversion', 'roles' => [3, 4, 5]],
    ['module' => 'Academic & CBC', 'route' => 'report_cbc_class_assessment', 'code' => 'CBC_CLASS_ASSESSMENT_SUMMARY', 'title' => 'CBC Class Assessment Summary', 'roles' => [3, 4, 5, 6, 7]],
    ['module' => 'Academic', 'route' => 'report_cbc_rubric_distribution', 'code' => 'CBC_RUBRIC_DISTRIBUTION', 'title' => 'CBC Rubric Distribution', 'roles' => [3, 4, 5, 6, 7]],
    ['module' => 'Attendance', 'route' => 'report_class_attendance_by_term', 'code' => 'ATT_CLASS_TERM_RATE', 'title' => 'Class Attendance by Term', 'roles' => [3, 4, 5, 6, 7]],
    ['module' => 'Finance', 'route' => 'report_fee_summary_by_class', 'code' => 'FIN_FEE_SUMMARY_CLASS', 'title' => 'Fee Summary by Class', 'roles' => [3, 4, 5, 10]],
    ['module' => 'Finance', 'route' => 'report_fee_arrears_by_class', 'code' => 'FIN_ARREARS_CLASS', 'title' => 'Fee Arrears by Class', 'roles' => [3, 4, 5, 10]],
    ['module' => 'Staff ', 'route' => 'report_staff_attendance_rate', 'code' => 'HR_STAFF_ATTENDANCE_RATE', 'title' => 'Staff Attendance Rate', 'roles' => [3, 4, 5, 6]],
    ['module' => 'Inventory', 'route' => 'report_inventory_stock_levels', 'code' => 'INV_STOCK_LEVELS', 'title' => 'Inventory Stock Levels', 'roles' => [3, 4, 5, 14]],
    ['module' => 'Catering', 'route' => 'report_food_consumption_trend', 'code' => 'CAT_FOOD_CONSUMPTION', 'title' => 'Food Consumption Trend', 'roles' => [3, 4, 5, 16, 32, 71]],
    ['module' => 'Discipline ', 'route' => 'report_discipline_incident_trend', 'code' => 'DISC_INCIDENT_TREND', 'title' => 'Discipline Incident Trend', 'roles' => [3, 4, 5, 63]],
    ['module' => 'Communication', 'route' => 'report_communication_delivery', 'code' => 'COMMS_DELIVERY_SUMMARY', 'title' => 'Communication Delivery Summary', 'roles' => [3, 4, 5]],
    ['module' => 'System & Compliance', 'route' => 'report_system_audit_summary', 'code' => 'SYS_AUDIT_TRAIL_SUMMARY', 'title' => 'System Audit Trail Summary', 'roles' => [2, 3]],
];
