
<div class="container-fluid py-4" id="accountantDashboard">
    <div class="dash-greeting-bar mb-4">
        <div>
            <h5 id="accountantGreeting">Good morning!</h5>
            <p>School Accountant &mdash; <span id="accountantDashboardScope">&mdash;</span></p>
        </div>
        <div class="dash-meta">
            <span class="text-white-50 small">Updated: <span id="accountantDashboardLastUpdated">&mdash;</span></span>
            <button class="dash-refresh-btn" id="accountantDashboardRefresh"><i class="bi bi-arrow-clockwise me-1"></i>Refresh</button>
        </div>
    </div>
    <script>
        (function () {
            var user = (typeof AuthContext !== 'undefined') ? AuthContext.getUser() : null;
            if (user) {
                var hr = new Date().getHours();
                var greet = hr < 12 ? 'Good morning' : hr < 17 ? 'Good afternoon' : 'Good evening';
                var name = user.first_name || user.name || '';
                var el = document.getElementById('accountantGreeting');
                if (el) el.textContent = greet + (name ? ', ' + name : '') + '!';
            }
        })();
    </script>

    <?php $rootId = 'accountantDashboard'; ?>
    <?php require __DIR__ . '/partials/period_selector.php'; ?>

    <div id="accountantDashboardState" hidden></div>

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="dash-stat dsc-green">
                <i class="bi bi-cash-coin dash-stat-icon"></i>
                <div class="dash-stat-value" id="accCollected">&mdash;</div>
                <div class="dash-stat-label">Fees Collected</div>
                <div class="dash-stat-sub" id="accCollectedSub">&mdash;</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="dash-stat dsc-red">
                <i class="bi bi-exclamation-circle dash-stat-icon"></i>
                <div class="dash-stat-value" id="accOutstanding">&mdash;</div>
                <div class="dash-stat-label">Outstanding Balances</div>
                <div class="dash-stat-sub" id="accOutstandingSub">&mdash;</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="dash-stat dsc-blue">
                <i class="bi bi-phone dash-stat-icon"></i>
                <div class="dash-stat-value" id="accMpesa">&mdash;</div>
                <div class="dash-stat-label">M-Pesa Collections</div>
                <div class="dash-stat-sub" id="accMpesaSub">&mdash;</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="dash-stat dsc-indigo">
                <i class="bi bi-bank dash-stat-icon"></i>
                <div class="dash-stat-value" id="accBank">&mdash;</div>
                <div class="dash-stat-label">Bank Collections</div>
                <div class="dash-stat-sub" id="accBankSub">&mdash;</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="dash-stat dsc-teal">
                <i class="bi bi-cash dash-stat-icon"></i>
                <div class="dash-stat-value" id="accCash">&mdash;</div>
                <div class="dash-stat-label">Cash Collections</div>
                <div class="dash-stat-sub" id="accCashSub">&mdash;</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="dash-stat dsc-orange">
                <i class="bi bi-exclamation-triangle dash-stat-icon"></i>
                <div class="dash-stat-value" id="accUnreconciled">&mdash;</div>
                <div class="dash-stat-label">Unreconciled</div>
                <div class="dash-stat-sub" id="accUnreconciledSub">&mdash;</div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100 dash-card">
                <div class="card-header bg-transparent border-0 pt-3 pb-0">
                    <h6 class="mb-0"><i class="bi bi-graph-up me-2 text-primary"></i>Collection Trend</h6>
                </div>
                <div class="card-body" style="position:relative;height:280px;">
                    <canvas id="accCollectionChart"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100 dash-card">
                <div class="card-header bg-transparent border-0 pt-3 pb-0">
                    <h6 class="mb-0"><i class="bi bi-pie-chart me-2 text-success"></i>Payment Methods</h6>
                </div>
                <div class="card-body" style="position:relative;height:280px;">
                    <canvas id="accMethodChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    <div class="row g-3 mb-4">
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100 dash-card">
                <div class="card-header bg-transparent border-0 pt-3 pb-0">
                    <h6 class="mb-0"><i class="bi bi-bar-chart me-2 text-info"></i>Collection by Level</h6>
                </div>
                <div class="card-body" style="position:relative;height:280px;">
                    <canvas id="accLevelChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100 dash-card">
                <div class="card-header bg-transparent border-0 pt-3 pb-0 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="bi bi-list-check me-2 text-primary"></i>Recent Payments</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3">Reference</th>
                                    <th>Student</th>
                                    <th>Class</th>
                                    <th>Method</th>
                                    <th class="text-end">Amount</th>
                                    <th class="pe-3">Date</th>
                                </tr>
                            </thead>
                            <tbody id="accTransactionsBody">
                                <tr><td colspan="6" class="text-center text-muted py-4"><i class="bi bi-inbox me-2"></i>Loading&hellip;</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100 dash-card">
                <div class="card-header bg-transparent border-0 pt-3 pb-0 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="bi bi-phone me-2 text-warning"></i>Unreconciled M-Pesa</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3">Phone</th>
                                    <th class="text-end">Amount</th>
                                    <th>Date</th>
                                    <th class="pe-3">Status</th>
                                </tr>
                            </thead>
                            <tbody id="accUnreconciledBody">
                                <tr><td colspan="4" class="text-center text-muted py-4"><i class="bi bi-inbox me-2"></i>Loading&hellip;</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="row g-3 mb-4">
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100 dash-card">
                <div class="card-header bg-transparent border-0 pt-3 pb-0 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="bi bi-person-x me-2 text-danger"></i>Top Defaulters</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3">Student</th>
                                    <th>Class</th>
                                    <th class="text-end">Amount</th>
                                    <th class="pe-3">Days</th>
                                </tr>
                            </thead>
                            <tbody id="accDefaultersBody">
                                <tr><td colspan="4" class="text-center text-muted py-4"><i class="bi bi-inbox me-2"></i>Loading&hellip;</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100 dash-card">
                <div class="card-header bg-transparent border-0 pt-3 pb-0">
                    <h6 class="mb-0"><i class="bi bi-speedometer me-2 text-info"></i>Budget Utilization</h6>
                </div>
                <div class="card-body">
                    <div id="accBudgetUtil">
                        <p class="text-muted small mb-0">No budget data available.</p>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100 dash-card">
                <div class="card-header bg-transparent border-0 pt-3 pb-0">
                    <h6 class="mb-0"><i class="bi bi-people me-2 text-success"></i>Payroll Status</h6>
                </div>
                <div class="card-body">
                    <div id="accPayroll">
                        <p class="text-muted small mb-0">No payroll data available.</p>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100 dash-card">
                <div class="card-header bg-transparent border-0 pt-3 pb-0">
                    <h6 class="mb-0"><i class="bi bi-hourglass-split me-2 text-warning"></i>Pending Approvals</h6>
                </div>
                <div class="card-body">
                    <div id="accApprovals">
                        <p class="text-muted small mb-0">No pending approvals.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php asset_script($appBase, 'js/dashboards/dashboard_base_controller.js'); ?>
<?php asset_script($appBase, 'js/dashboards/school_accountant_dashboard.js'); ?>
