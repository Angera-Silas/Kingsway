<?php
/**
 * Store Manager Dashboard — Stock health, requisitions, assets and supplier tracking.
 * Role: Store Manager / Inventory Manager
 */
$rootId = 'storeDashboard';
$periods = [
    ['key' => 'today', 'label' => 'Today'],
    ['key' => 'week', 'label' => 'This Week'],
    ['key' => 'term', 'label' => 'This Term'],
];
$default = 'today';

$escape = static function ($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
};
?>

<div class="container-fluid py-4 role-dashboard dashboard-surface dashboard-inventory" id="<?= $escape($rootId) ?>">
    <?php require __DIR__ . '/partials/period_selector.php'; ?>

    <!-- Meta Bar -->
    <div class="dash-meta-bar mb-4 d-flex align-items-center justify-content-end gap-3 flex-wrap">
        <span class="dash-badge bg-primary" id="<?= $escape($rootId) ?>RoleBadge">Inventory</span>
        <span class="small text-muted">Updated: <span id="<?= $escape($rootId) ?>LastUpdated">—</span></span>
        <button class="btn btn-sm btn-outline-success" id="<?= $escape($rootId) ?>Refresh">
            <i class="bi bi-arrow-clockwise me-1"></i>Refresh
        </button>
    </div>

    <!-- ROW 1: STOCK ALERT BANNER — full-width -->
    <div class="row g-3 mb-4">
        <div class="col-12">
            <div class="card dash-card">
                <div class="card-body py-3">
                    <div class="row text-center g-0">
                        <div class="col-md-4 border-end">
                            <div class="d-flex align-items-center justify-content-center gap-2">
                                <i class="bi bi-x-octagon fs-2 text-danger"></i>
                                <div>
                                    <div class="fs-3 fw-bold text-danger" id="storeOutOfStock">—</div>
                                    <div class="small text-muted">Out of Stock</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 border-end">
                            <div class="d-flex align-items-center justify-content-center gap-2">
                                <i class="bi bi-exclamation-triangle fs-2 text-warning"></i>
                                <div>
                                    <div class="fs-3 fw-bold text-warning" id="storeBelowMin">—</div>
                                    <div class="small text-muted">Below Minimum</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="d-flex align-items-center justify-content-center gap-2">
                                <i class="bi bi-check-circle fs-2 text-success"></i>
                                <div>
                                    <div class="fs-3 fw-bold text-success" id="storeHealthy">—</div>
                                    <div class="small text-muted">Healthy Items</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ROW 2: KPIs — 6 -->
    <div class="row g-3 mb-3">
        <div class="col-xl-4 col-md-6">
            <div class="dash-stat dsc-blue">
                <i class="bi bi-boxes dash-stat-icon"></i>
                <div class="dash-stat-value" id="storeActiveItems">—</div>
                <div class="dash-stat-label">Active Items</div>
                <div class="dash-stat-sub" id="storeActiveSub">in inventory</div>
            </div>
        </div>
        <div class="col-xl-4 col-md-6">
            <div class="dash-stat dsc-orange">
                <i class="bi bi-exclamation-triangle dash-stat-icon"></i>
                <div class="dash-stat-value" id="storeLowStockAlerts">—</div>
                <div class="dash-stat-label">Low Stock Alerts</div>
                <div class="dash-stat-sub" id="storeLowSub">below minimum</div>
            </div>
        </div>
        <div class="col-xl-4 col-md-6">
            <div class="dash-stat dsc-red">
                <i class="bi bi-x-octagon dash-stat-icon"></i>
                <div class="dash-stat-value" id="storeOutOfStockCount">—</div>
                <div class="dash-stat-label">Out of Stock</div>
                <div class="dash-stat-sub" id="storeOutSub">zero quantity</div>
            </div>
        </div>
    </div>
    <div class="row g-3 mb-4">
        <div class="col-xl-4 col-md-6">
            <div class="dash-stat dsc-green">
                <i class="bi bi-currency-exchange dash-stat-icon"></i>
                <div class="dash-stat-value" id="storeValue">—</div>
                <div class="dash-stat-label">Inventory Value</div>
                <div class="dash-stat-sub" id="storeValueSub">KES total</div>
            </div>
        </div>
        <div class="col-xl-4 col-md-6">
            <div class="dash-stat dsc-amber">
                <i class="bi bi-card-checklist dash-stat-icon"></i>
                <div class="dash-stat-value" id="storePendingReqs">—</div>
                <div class="dash-stat-label">Pending Requisitions</div>
                <div class="dash-stat-sub" id="storeReqsSub">awaiting approval</div>
            </div>
        </div>
        <div class="col-xl-4 col-md-6">
            <div class="dash-stat dsc-cyan">
                <i class="bi bi-arrow-repeat dash-stat-icon"></i>
                <div class="dash-stat-value" id="storeReorderPending">—</div>
                <div class="dash-stat-label">Reorder Pending</div>
                <div class="dash-stat-sub" id="storeReorderSub">items to reorder</div>
            </div>
        </div>
    </div>

    <!-- ROW 3: STOCK ANALYTICS — 2 panels -->
    <div class="row g-3 mb-4">
        <div class="col-lg-5">
            <div class="card dash-card h-100">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-pie-chart me-2 text-primary"></i>Stock by Category</h6>
                </div>
                <div class="card-body d-flex align-items-center justify-content-center">
                    <div class="dash-chart-wrap-lg"><canvas id="storeCategoryChart"></canvas></div>
                </div>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="card dash-card h-100">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-bar-chart me-2 text-success"></i>Stock Health by Category</h6>
                    <small class="text-muted">Healthy / Low / Out of Stock</small>
                </div>
                <div class="card-body">
                    <div class="dash-chart-wrap-lg"><canvas id="storeHealthChart"></canvas></div>
                </div>
            </div>
        </div>
    </div>

    <!-- ROW 4: LOW STOCK & REQUISITIONS — 2 panels -->
    <div class="row g-3 mb-4">
        <div class="col-lg-6">
            <div class="card dash-card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="bi bi-exclamation-diamond me-2 text-danger"></i>Low-stock Items</h6>
                    <button class="btn btn-sm btn-outline-danger" data-route="manage_inventory">View All</button>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col">Item</th>
                                    <th scope="col">Current</th>
                                    <th scope="col">Minimum</th>
                                    <th scope="col">Supplier</th>
                                </tr>
                            </thead>
                            <tbody id="storeLowBody">
                                <tr><td colspan="4" class="text-center text-muted py-4">Loading...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card dash-card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="bi bi-card-checklist me-2 text-warning"></i>Pending Requisitions</h6>
                    <button class="btn btn-sm btn-outline-warning" data-route="manage_requisitions">View All</button>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col">Requested By</th>
                                    <th scope="col">Items</th>
                                    <th scope="col">Date</th>
                                    <th scope="col">Status</th>
                                </tr>
                            </thead>
                            <tbody id="storeReqsBody">
                                <tr><td colspan="4" class="text-center text-muted py-4">Loading...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Stock Movement Summary -->
    <div class="row g-3 mb-4">
        <div class="col-lg-6">
            <div class="card dash-card h-100">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-arrow-left-right me-2 text-info"></i>Recent Stock Movements</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col">Date</th>
                                    <th scope="col">Item</th>
                                    <th scope="col">Type</th>
                                    <th scope="col">Qty</th>
                                    <th scope="col">By</th>
                                </tr>
                            </thead>
                            <tbody id="storeMovementsBody">
                                <tr><td colspan="5" class="text-center text-muted py-4">Loading...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card dash-card h-100">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-calendar-x me-2 text-danger"></i>Expiring Soon</h6>
                    <small class="text-muted">Items expiring within 90 days</small>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col">Item</th>
                                    <th scope="col">Qty</th>
                                    <th scope="col">Expiry</th>
                                    <th scope="col">Action</th>
                                </tr>
                            </thead>
                            <tbody id="storeExpiryBody">
                                <tr><td colspan="4" class="text-center text-muted py-4">Loading...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ROW 5: ASSET & SUPPLIER — 2 panels -->
    <div class="row g-3 mb-4">
        <div class="col-lg-6">
            <div class="card dash-card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="bi bi-box-seam me-2 text-indigo"></i>Asset Condition Summary</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col">Category</th>
                                    <th scope="col">Good</th>
                                    <th scope="col">Fair</th>
                                    <th scope="col">Poor</th>
                                    <th scope="col">Retired</th>
                                </tr>
                            </thead>
                            <tbody id="storeAssetBody">
                                <tr><td colspan="5" class="text-center text-muted py-4">Loading...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card dash-card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="bi bi-truck me-2 text-success"></i>Supplier Delivery Status</h6>
                    <button class="btn btn-sm btn-outline-success" data-route="vendors">All Suppliers</button>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col">Supplier</th>
                                    <th scope="col">Order</th>
                                    <th scope="col">Expected</th>
                                    <th scope="col">Status</th>
                                </tr>
                            </thead>
                            <tbody id="storeSupplierBody">
                                <tr><td colspan="4" class="text-center text-muted py-4">Loading...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ROW 6: QUICK ACTIONS -->
    <div class="row g-3 mb-3">
        <div class="col-12">
            <div class="card dash-card">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-lightning-charge me-2 text-warning"></i>Quick Actions</h6>
                </div>
                <div class="card-body">
                    <div class="row g-2">
                        <div class="col-md-3 col-lg">
                            <a href="#" data-route="manage_inventory" class="dash-quick-link">
                                <i class="bi bi-boxes ql-icon bg-primary text-white"></i>
                                <span>Inventory Items</span><i class="bi bi-chevron-right ql-arrow"></i>
                            </a>
                        </div>
                        <div class="col-md-3 col-lg">
                            <a href="#" data-route="manage_stock" class="dash-quick-link">
                                <i class="bi bi-clipboard-check ql-icon bg-success text-white"></i>
                                <span>Stock Count</span><i class="bi bi-chevron-right ql-arrow"></i>
                            </a>
                        </div>
                        <div class="col-md-3 col-lg">
                            <a href="#" data-route="manage_requisitions" class="dash-quick-link">
                                <i class="bi bi-card-checklist ql-icon bg-warning text-white"></i>
                                <span>Requisitions</span><i class="bi bi-chevron-right ql-arrow"></i>
                            </a>
                        </div>
                        <div class="col-md-3 col-lg">
                            <a href="#" data-route="vendors" class="dash-quick-link">
                                <i class="bi bi-truck ql-icon bg-info text-white"></i>
                                <span>Suppliers</span><i class="bi bi-chevron-right ql-arrow"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<?php asset_script($appBase, 'js/dashboards/dashboard_base_controller.js'); ?>
<?php asset_script($appBase, 'js/dashboards/store_manager_dashboard.js'); ?>
