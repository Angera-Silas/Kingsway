<?php
$rootId = 'cateringDashboard';
$periods = [
    ['key' => 'today', 'label' => 'Today'],
    ['key' => 'week', 'label' => 'This Week'],
    ['key' => 'term', 'label' => 'This Term'],
];
$default = 'today';
require __DIR__ . '/partials/period_selector.php';

$dashboardConfig = [
    'root_id' => $rootId,
    'title' => 'Catering Manager Dashboard',
    'subtitle' => 'Meals planned, serving readiness, food stock and consumption.',
    'icon' => 'bi-cup-hot',
    'controller_file' => 'catering_manager_cook_lead_dashboard.js',
    'cards' => [
        ['id' => 'catMeals', 'label' => 'Meals Planned', 'icon' => 'bi-list-check', 'colour' => 'dsc-green', 'subtitle_id' => 'catMealsSub'],
        ['id' => 'catStudents', 'label' => 'Students to Feed', 'icon' => 'bi-people', 'colour' => 'dsc-blue', 'subtitle_id' => 'catStudentsSub'],
        ['id' => 'catLowStock', 'label' => 'Food Stock Low', 'icon' => 'bi-exclamation-triangle', 'colour' => 'dsc-orange', 'subtitle_id' => 'catLowStockSub'],
        ['id' => 'catServed', 'label' => 'Meals Served', 'icon' => 'bi-check2-circle', 'colour' => 'dsc-teal', 'subtitle_id' => 'catServedSub']
    ],
    'charts' => [
        ['id' => 'catReadinessChart', 'title' => 'Meal Readiness', 'icon' => 'bi-bar-chart', 'column' => 'col-lg-6'],
        ['id' => 'catConsumptionChart', 'title' => 'Consumption Trend', 'icon' => 'bi-graph-up', 'column' => 'col-lg-6']
    ],
    'tables' => [
        ['body_id' => 'catMenuBody', 'title' => 'Today\'s Menu', 'columns' => ['Meal', 'Items', 'Servings', 'Status'], 'route' => 'todays_menu', 'column' => 'col-xl-6'],
        ['body_id' => 'catStockBody', 'title' => 'Low Stock', 'columns' => ['Item', 'Current', 'Minimum', 'Supplier'], 'route' => 'food_store', 'column' => 'col-xl-6']
    ],
    'quick_actions' => [
        ['label' => 'Today\'s Menu', 'route' => 'todays_menu', 'icon' => 'bi-card-list'],
        ['label' => 'Food Store', 'route' => 'food_store', 'icon' => 'bi-warehouse'],
        ['label' => 'Meal Allocations', 'route' => 'meal_statistics', 'icon' => 'bi-people'],
        ['label' => 'Consumption Records', 'route' => 'food_consumption', 'icon' => 'bi-clipboard-data']
    ],
];

require __DIR__ . '/partials/role_dashboard_shell.php';
