<?php
$rootId = 'internDashboard';
$periods = [
    ['key' => 'today', 'label' => 'Today'],
    ['key' => 'week', 'label' => 'This Week'],
    ['key' => 'term', 'label' => 'This Term'],
];
$default = 'today';
require __DIR__ . '/partials/period_selector.php';

$dashboardConfig = [
    'root_id' => $rootId,
    'title' => 'Intern / Student Teacher Dashboard',
    'subtitle' => 'Teaching assignments, observations and professional development.',
    'icon' => 'bi-person-workspace',
    'controller_file' => 'intern_student_teacher_dashboard.js',
    'cards' => [
        ['id' => 'internClasses', 'label' => 'Assigned Classes', 'icon' => 'bi-building', 'colour' => 'dsc-blue', 'subtitle_id' => 'internClassesSub'],
        ['id' => 'internObservations', 'label' => 'Observations', 'icon' => 'bi-eye', 'colour' => 'dsc-purple', 'subtitle_id' => 'internObservationsSub'],
        ['id' => 'internMentor', 'label' => 'Mentor Meetings', 'icon' => 'bi-person-check', 'colour' => 'dsc-indigo', 'subtitle_id' => 'internMentorSub'],
        ['id' => 'internProgress', 'label' => 'Development Progress', 'icon' => 'bi-graph-up-arrow', 'colour' => 'dsc-green', 'subtitle_id' => 'internProgressSub']
    ],
    'charts' => [
        ['id' => 'internCompetencyChart', 'title' => 'Competency Progress', 'icon' => 'bi-radar', 'column' => 'col-lg-6'],
        ['id' => 'internObsChart', 'title' => 'Observation Outcomes', 'icon' => 'bi-bar-chart', 'column' => 'col-lg-6']
    ],
    'tables' => [
        ['body_id' => 'internClassesBody', 'title' => 'My Classes', 'columns' => ['Class', 'Subject', 'Students'], 'route' => 'timetable', 'column' => 'col-xl-6'],
        ['body_id' => 'internFeedbackBody', 'title' => 'Recent Feedback', 'columns' => ['Date', 'Observer', 'Type', 'Notes'], 'route' => 'teacher_performance_reviews', 'column' => 'col-xl-6']
    ],
    'quick_actions' => [
        ['label' => 'My Timetable', 'route' => 'timetable', 'icon' => 'bi-calendar3'],
        ['label' => 'Lesson Plans', 'route' => 'manage_lesson_plans', 'icon' => 'bi-journal-text'],
        ['label' => 'Teaching Resources', 'route' => 'view_teaching_materials', 'icon' => 'bi-folder2-open'],
        ['label' => 'My Profile', 'route' => 'intern_student_teacher_dashboard', 'icon' => 'bi-person']
    ],
];

require __DIR__ . '/partials/role_dashboard_shell.php';
