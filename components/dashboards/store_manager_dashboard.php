<?php
$rootId = 'storeDashboard';
$periods = [
    ['key' => 'today', 'label' => 'Today'],
    ['key' => 'week', 'label' => 'This Week'],
    ['key' => 'term', 'label' => 'This Term'],
];
$default = 'today';
require __DIR__ . '/partials/period_selector.php';

$dashboardConfig = [
    'root_id' => $rootId,
    'title' => 'Store Manager Dashboard',
    'subtitle' => 'Stock health, requisitions, expiry and inventory value.',
    'icon' => 'bi-box-seam',
    'controller_file' => 'store_manager_dashboard.js',
    'cards' => [
        ['id' => 'storeItems', 'label' => 'Active Items', 'icon' => 'bi-boxes', 'colour' => 'dsc-blue', 'subtitle_id' => 'storeItemsSub'],
        ['id' => 'storeLowStock', 'label' => 'Low Stock', 'icon' => 'bi-exclamation-triangle', 'colour' => 'dsc-orange', 'subtitle_id' => 'storeLowStockSub'],
        ['id' => 'storeOutStock', 'label' => 'Out of Stock', 'icon' => 'bi-x-octagon', 'colour' => 'dsc-red', 'subtitle_id' => 'storeOutStockSub'],
        ['id' => 'storeValue', 'label' => 'Inventory Value', 'icon' => 'bi-currency-exchange', 'colour' => 'dsc-green', 'subtitle_id' => 'storeValueSub']
    ],
    'charts' => [
        ['id' => 'storeCategoryChart', 'title' => 'Stock by Category', 'icon' => 'bi-pie-chart', 'column' => 'col-lg-6'],
        ['id' => 'storeHealthChart', 'title' => 'Stock Health', 'icon' => 'bi-bar-chart', 'column' => 'col-lg-6']
    ],
    'tables' => [
        ['body_id' => 'storeLowBody', 'title' => 'Low-stock Items', 'columns' => ['Item', 'Current', 'Minimum', 'Supplier'], 'route' => 'manage_inventory', 'column' => 'col-xl-6'],
        ['body_id' => 'storeReqsBody', 'title' => 'Pending Requisitions', 'columns' => ['Requested By', 'Items', 'Date', 'Status'], 'route' => 'manage_requisitions', 'column' => 'col-xl-6']
    ],
    'quick_actions' => [
        ['label' => 'Inventory Items', 'route' => 'manage_inventory', 'icon' => 'bi-boxes'],
        ['label' => 'Stock Count', 'route' => 'manage_stock', 'icon' => 'bi-clipboard-check'],
        ['label' => 'Requisitions', 'route' => 'manage_requisitions', 'icon' => 'bi-card-checklist'],
        ['label' => 'Suppliers', 'route' => 'vendors', 'icon' => 'bi-truck']
    ],
];

require __DIR__ . '/partials/role_dashboard_shell.php';
