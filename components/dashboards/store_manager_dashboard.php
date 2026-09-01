<?php
/** Uniform Store Manager analytics dashboard. */
$rootId = 'storeDashboard';
?>

<div class="container-fluid py-3 role-dashboard dashboard-inventory inventory-command" id="<?= $rootId ?>">
    <div class="inventory-command__layout">
        <main class="inventory-command__main">
            <section class="inventory-kpis" aria-label="Inventory key metrics">
                <article class="inventory-kpi"><span>Active items</span><strong id="storeItems">—</strong><small>Tracked catalogue items</small></article>
                <article class="inventory-kpi inventory-kpi--value"><span>Monthly sales</span><strong id="storeValue">—</strong><small>Uniform revenue this month</small></article>
                <article class="inventory-kpi inventory-kpi--warning"><span>Low stock</span><strong id="storeLowStock">—</strong><small>At or below reorder level</small></article>
                <article class="inventory-kpi inventory-kpi--danger"><span>Out of stock</span><strong id="storeOutStock">—</strong><small>Immediate replenishment</small></article>
                <article class="inventory-kpi inventory-kpi--success"><span>Healthy items</span><strong id="storeHealthy">—</strong><small>Stock level adequate</small></article>
            </section>

            <section class="inventory-analytics-grid">
                <article class="inventory-panel inventory-panel--gauge">
                    <div class="inventory-panel__heading"><div><span>Stock health</span><h2>Availability rate</h2></div></div>
                    <div class="inventory-gauge" id="storeHealthGauge" style="--health:0">
                        <div><strong id="storeHealthRate">0%</strong><span>healthy</span></div>
                    </div>
                </article>

                <article class="inventory-panel">
                    <div class="inventory-panel__heading"><div><span>Composition</span><h2>Items by category</h2></div></div>
                    <div class="inventory-chart inventory-chart--donut"><canvas id="storeCategoryChart"></canvas></div>
                </article>

                <article class="inventory-panel inventory-panel--wide">
                    <div class="inventory-panel__heading"><div><span>Comparison</span><h2>Stock condition</h2></div></div>
                    <div class="inventory-chart"><canvas id="storeHealthChart"></canvas></div>
                </article>

                <article class="inventory-panel inventory-panel--wide">
                    <div class="inventory-panel__heading">
                        <div><span>Action required</span><h2>Replenishment priorities</h2></div>
                        <a href="#" data-route="manage_inventory">Open inventory <i class="bi bi-arrow-right"></i></a>
                    </div>
                    <div class="table-responsive">
                        <table class="table inventory-table mb-0">
                            <thead><tr><th>Item</th><th>Category</th><th>On hand</th><th>Minimum</th><th>Status</th></tr></thead>
                            <tbody id="storeLowBody"><tr><td colspan="5" class="text-center py-4 text-muted">Loading stock…</td></tr></tbody>
                        </table>
                    </div>
                </article>

                <article class="inventory-panel inventory-panel--wide">
                    <div class="inventory-panel__heading">
                        <div><span>Sales</span><h2>Top-selling uniforms</h2></div>
                        <a href="#" data-route="uniform_sales_records">View sales <i class="bi bi-arrow-right"></i></a>
                    </div>
                    <div class="table-responsive">
                        <table class="table inventory-table mb-0">
                            <thead><tr><th>Uniform</th><th>Store</th><th>Units sold</th><th>Period</th><th>Status</th></tr></thead>
                            <tbody id="storeReqsBody"><tr><td colspan="5" class="text-center py-4 text-muted">Loading uniform sales…</td></tr></tbody>
                        </table>
                    </div>
                </article>
            </section>
        </main>

        <aside class="inventory-filters" aria-label="Dashboard filters">
            <div class="inventory-filters__title"><i class="bi bi-sliders"></i><div><span>View controls</span><strong>Filters</strong></div></div>
            <label for="storeCategoryFilter">Category</label>
            <select id="storeCategoryFilter" class="form-select"><option value="">All categories</option></select>
            <label for="storeStatusFilter">Stock status</label>
            <select id="storeStatusFilter" class="form-select">
                <option value="">All statuses</option><option value="OUT OF STOCK">Out of stock</option>
                <option value="REORDER">Reorder</option><option value="LOW STOCK">Low stock</option><option value="ADEQUATE">Adequate</option>
            </select>
            <label for="storePriorityFilter">Sales status</label>
            <select id="storePriorityFilter" class="form-select">
                <option value="">All priorities</option><option value="urgent">Urgent</option><option value="high">High</option>
                <option value="normal">Normal</option><option value="low">Low</option>
            </select>
            <button type="button" class="btn inventory-refresh" id="storeDashboardRefresh"><i class="bi bi-arrow-clockwise"></i> Refresh data</button>
            <nav class="inventory-quick-actions" aria-label="Inventory shortcuts">
                <a href="#" data-route="manage_uniform_sales"><i class="bi bi-clipboard-check"></i>Uniform stock</a>
                <a href="#" data-route="vendors"><i class="bi bi-truck"></i>Suppliers</a>
                <a href="#" data-route="uniform_sales_records"><i class="bi bi-graph-up"></i>Sales records</a>
            </nav>
        </aside>
    </div>
</div>

<?php asset_script($appBase, 'js/dashboards/store_manager_dashboard.js'); ?>
