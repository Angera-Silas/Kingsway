<?php
$rootId = 'subjectTeacherDashboard';
$periods = [
    ['key' => 'today', 'label' => 'Today'],
    ['key' => 'week', 'label' => 'This Week'],
    ['key' => 'term', 'label' => 'This Term'],
];
$default = 'today';
require __DIR__ . '/partials/period_selector.php';

$dashboardConfig = [
    'root_id' => $rootId,
    'title' => 'Subject Teacher Dashboard',
    'subtitle' => 'Your teaching load, assessment queue and subject performance.',
    'icon' => 'bi-book',
    'controller_file' => 'subject_teacher_dashboard.js',
    'cards' => [
        ['id' => 'stClasses', 'label' => 'Assigned Classes', 'icon' => 'bi-building', 'colour' => 'dsc-blue', 'subtitle_id' => 'stClassesSub'],
        ['id' => 'stAssessments', 'label' => 'Assessments Due', 'icon' => 'bi-clipboard2-data', 'colour' => 'dsc-orange', 'subtitle_id' => 'stAssessmentsSub'],
        ['id' => 'stGraded', 'label' => 'Graded This Week', 'icon' => 'bi-check2-circle', 'colour' => 'dsc-green', 'subtitle_id' => 'stGradedSub'],
        ['id' => 'stExams', 'label' => 'Upcoming Exams', 'icon' => 'bi-calendar-event', 'colour' => 'dsc-amber', 'subtitle_id' => 'stExamsSub']
    ],
    'charts' => [
        ['id' => 'subPerformanceChart', 'title' => 'Subject Performance', 'icon' => 'bi-bar-chart', 'column' => 'col-lg-6'],
        ['id' => 'subGradingChart', 'title' => 'Grading Progress', 'icon' => 'bi-graph-up', 'column' => 'col-lg-6']
    ],
    'tables' => [
        ['body_id' => 'subPendingBody', 'title' => 'Pending Assessments', 'columns' => ['Assessment', 'Class', 'Due Date', 'Status'], 'route' => 'formative_assessments', 'column' => 'col-xl-6'],
        ['body_id' => 'subExamBody', 'title' => 'Exam Schedule', 'columns' => ['Date', 'Class', 'Type', 'Status'], 'route' => 'subject_exam_schedule', 'column' => 'col-xl-6']
    ],
    'quick_actions' => [
        ['label' => 'My Timetable', 'route' => 'timetable', 'icon' => 'bi-calendar3'],
        ['label' => 'Enter Marks', 'route' => 'subject_grade_entry', 'icon' => 'bi-pencil-square'],
        ['label' => 'Lesson Plans', 'route' => 'manage_lesson_plans', 'icon' => 'bi-journal-text'],
        ['label' => 'Exam Schedule', 'route' => 'subject_exam_schedule', 'icon' => 'bi-calendar-event']
    ],
];

require __DIR__ . '/partials/role_dashboard_shell.php';
