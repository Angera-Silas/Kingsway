<?php
/**
 * Matron / Housemother Dashboard — Dormitory occupancy, roll calls, welfare and permissions.
 * Role: Matron / Housemother
 */
$rootId = 'matronDashboard';
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

<div class="container-fluid py-4 role-dashboard dashboard-surface dashboard-boarding" id="<?= $escape($rootId) ?>">
    <?php require __DIR__ . '/partials/period_selector.php'; ?>

    <!-- Meta Bar -->
    <div class="dash-meta-bar mb-4 d-flex align-items-center justify-content-end gap-3 flex-wrap">
        <span class="dash-badge bg-info" id="<?= $escape($rootId) ?>RoleBadge">Boarding</span>
        <span class="small text-muted">Updated: <span id="<?= $escape($rootId) ?>LastUpdated">—</span></span>
        <button class="btn btn-sm btn-outline-success" id="<?= $escape($rootId) ?>Refresh">
            <i class="bi bi-arrow-clockwise me-1"></i>Refresh
        </button>
    </div>

    <!-- ROW 1: DORMITORY GRID — full-width visual -->
    <div class="row g-3 mb-4">
        <div class="col-12">
            <div class="card dash-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="bi bi-house-door me-2 text-primary"></i>Dormitory Overview</h6>
                    <button class="btn btn-sm btn-outline-primary" data-route="dormitory_management">Manage</button>
                </div>
                <div class="card-body">
                    <div class="row g-3" id="brdDormGrid">
                        <?php for ($i = 1; $i <= 6; $i++): ?>
                        <div class="col-md-4 col-lg-2">
                            <div class="card border-0 shadow-sm h-100 dorm-card" style="border-left: 4px solid #198754;" id="brdDorm<?= $i ?>Card">
                                <div class="card-body text-center py-3">
                                    <h6 class="mb-1" id="brdDorm<?= $i ?>Name">—</h6>
                                    <div class="fs-4 fw-bold text-success" id="brdDorm<?= $i ?>Pct">—%</div>
                                    <small class="text-muted" id="brdDorm<?= $i ?>Detail">— / — beds</small>
                                    <div class="progress mt-2" style="height: 6px;">
                                        <div class="progress-bar bg-success" id="brdDorm<?= $i ?>Bar" style="width: 0%"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endfor; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ROW 2: KPIs — 6 -->
    <div class="row g-3 mb-3">
        <div class="col-xl-4 col-md-6">
            <div class="dash-stat dsc-blue">
                <i class="bi bi-people dash-stat-icon"></i>
                <div class="dash-stat-value" id="brdActiveBoarders">—</div>
                <div class="dash-stat-label">Active Boarders</div>
                <div class="dash-stat-sub" id="brdActiveSub">enrolled</div>
            </div>
        </div>
        <div class="col-xl-4 col-md-6">
            <div class="dash-stat dsc-cyan">
                <i class="bi bi-house-door dash-stat-icon"></i>
                <div class="dash-stat-value" id="brdOccupancy">—</div>
                <div class="dash-stat-label">Overall Occupancy</div>
                <div class="dash-stat-sub" id="brdOccupancySub">% of capacity</div>
            </div>
        </div>
        <div class="col-xl-4 col-md-6">
            <div class="dash-stat dsc-orange">
                <i class="bi bi-journal-text dash-stat-icon"></i>
                <div class="dash-stat-value" id="brdWelfareNotes">—</div>
                <div class="dash-stat-label">Welfare Notes</div>
                <div class="dash-stat-sub" id="brdWelfareSub">this week</div>
            </div>
        </div>
    </div>
    <div class="row g-3 mb-4">
        <div class="col-xl-4 col-md-6">
            <div class="dash-stat dsc-amber">
                <i class="bi bi-clock-history dash-stat-icon"></i>
                <div class="dash-stat-value" id="brdPermissionsPending">—</div>
                <div class="dash-stat-label">Permissions Pending</div>
                <div class="dash-stat-sub" id="brdPermissionsSub">awaiting approval</div>
            </div>
        </div>
        <div class="col-xl-4 col-md-6">
            <div class="dash-stat dsc-green">
                <i class="bi bi-check2-square dash-stat-icon"></i>
                <div class="dash-stat-value" id="brdRollCallCompliance">—</div>
                <div class="dash-stat-label">Roll Call Compliance</div>
                <div class="dash-stat-sub" id="brdRollCallSub">% sessions marked</div>
            </div>
        </div>
        <div class="col-xl-4 col-md-6">
            <div class="dash-stat dsc-red">
                <i class="bi bi-box-arrow-right dash-stat-icon"></i>
                <div class="dash-stat-value" id="brdExeatsWeek">—</div>
                <div class="dash-stat-label">Exeats This Week</div>
                <div class="dash-stat-sub" id="brdExeatsSub">approved departures</div>
            </div>
        </div>
    </div>

    <!-- ROW 3: ROLL CALL STATUS — 2 panels -->
    <div class="row g-3 mb-4">
        <div class="col-lg-6">
            <div class="card dash-card h-100">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-pie-chart me-2 text-success"></i>Roll Call Summary</h6>
                </div>
                <div class="card-body d-flex align-items-center justify-content-center">
                    <div class="dash-chart-wrap-lg"><canvas id="brdRollCallPie"></canvas></div>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card dash-card h-100">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-bar-chart me-2 text-info"></i>Boarder Movement</h6>
                </div>
                <div class="card-body">
                    <div class="dash-chart-wrap-lg"><canvas id="brdMovementChart"></canvas></div>
                </div>
            </div>
        </div>
    </div>

    <!-- ROW 4: WELFARE & PERMISSIONS — 2 panels -->
    <div class="row g-3 mb-4">
        <div class="col-lg-6">
            <div class="card dash-card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="bi bi-box-arrow-right me-2 text-warning"></i>Pending Permissions & Exeats</h6>
                    <button class="btn btn-sm btn-outline-warning" data-route="permissions_exeats">View All</button>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col">Student</th>
                                    <th scope="col">Type</th>
                                    <th scope="col">Dates</th>
                                    <th scope="col">Status</th>
                                </tr>
                            </thead>
                            <tbody id="brdPermissionsBody">
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
                    <h6 class="mb-0"><i class="bi bi-journal-medical me-2 text-danger"></i>Recent Welfare Notes</h6>
                    <button class="btn btn-sm btn-outline-danger" data-route="student_welfare">View All</button>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col">Student</th>
                                    <th scope="col">Category</th>
                                    <th scope="col">Note</th>
                                    <th scope="col">Date</th>
                                </tr>
                            </thead>
                            <tbody id="brdWelfareBody">
                                <tr><td colspan="4" class="text-center text-muted py-4">Loading...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Boarder Activity Summary -->
    <div class="row g-3 mb-4">
        <div class="col-lg-6">
            <div class="card dash-card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="bi bi-lightning me-2 text-purple"></i>Boarder Activity Log</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col">Student</th>
                                    <th scope="col">Activity</th>
                                    <th scope="col">Time</th>
                                    <th scope="col">Status</th>
                                </tr>
                            </thead>
                            <tbody id="brdActivityLogBody">
                                <tr><td colspan="4" class="text-center text-muted py-4">Loading...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card dash-card h-100">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-clipboard-data me-2 text-info"></i>Night Roll Call Status</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col">Dormitory</th>
                                    <th scope="col">Expected</th>
                                    <th scope="col">Present</th>
                                    <th scope="col">Missing</th>
                                    <th scope="col">Rate</th>
                                </tr>
                            </thead>
                            <tbody id="brdNightRollCallBody">
                                <tr><td colspan="5" class="text-center text-muted py-4">Loading...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Weekend Boarder Status -->
    <div class="row g-3 mb-4">
        <div class="col-lg-6">
            <div class="card dash-card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="bi bi-calendar-day me-2 text-info"></i>Weekend Boarders</h6>
                    <small class="text-muted">Full boarders present this weekend</small>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col">Dormitory</th>
                                    <th scope="col">Expected</th>
                                    <th scope="col">Present</th>
                                    <th scope="col">On Exeat</th>
                                    <th scope="col">Rate</th>
                                </tr>
                            </thead>
                            <tbody id="brdWeekendBody">
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
                    <h6 class="mb-0"><i class="bi bi-activity me-2 text-purple"></i>Roll Call Trend</h6>
                    <small class="text-muted">Last 7 days</small>
                </div>
                <div class="card-body">
                    <div class="dash-chart-wrap-lg"><canvas id="brdRollCallTrend"></canvas></div>
                </div>
            </div>
        </div>
    </div>

    <!-- ROW 5: BOARDER ALERTS — full-width -->
    <div class="row g-3 mb-4">
        <div class="col-12">
            <div class="card dash-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="bi bi-exclamation-triangle me-2 text-danger"></i>Boarder Alerts</h6>
                    <small class="text-muted">Attendance, health &amp; behavioural concerns</small>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col">Student</th>
                                    <th scope="col">Dormitory</th>
                                    <th scope="col">Alert Type</th>
                                    <th scope="col">Description</th>
                                    <th scope="col">Priority</th>
                                    <th scope="col">Date</th>
                                </tr>
                            </thead>
                            <tbody id="brdAlertsBody">
                                <tr><td colspan="6" class="text-center text-muted py-4">
                                    <div class="spinner-border spinner-border-sm me-2" role="status"></div>Loading alerts...
                                </td></tr>
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
                            <a href="#" data-route="boarding_roll_call" class="dash-quick-link">
                                <i class="bi bi-check2-square ql-icon bg-success text-white"></i>
                                <span>Roll Call</span><i class="bi bi-chevron-right ql-arrow"></i>
                            </a>
                        </div>
                        <div class="col-md-3 col-lg">
                            <a href="#" data-route="dormitory_management" class="dash-quick-link">
                                <i class="bi bi-house-door ql-icon bg-primary text-white"></i>
                                <span>Dormitories</span><i class="bi bi-chevron-right ql-arrow"></i>
                            </a>
                        </div>
                        <div class="col-md-3 col-lg">
                            <a href="#" data-route="permissions_exeats" class="dash-quick-link">
                                <i class="bi bi-box-arrow-right ql-icon bg-warning text-white"></i>
                                <span>Permissions</span><i class="bi bi-chevron-right ql-arrow"></i>
                            </a>
                        </div>
                        <div class="col-md-3 col-lg">
                            <a href="#" data-route="student_welfare" class="dash-quick-link">
                                <i class="bi bi-journal-medical ql-icon bg-danger text-white"></i>
                                <span>Welfare Notes</span><i class="bi bi-chevron-right ql-arrow"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<?php asset_script($appBase, 'js/dashboards/dashboard_base_controller.js'); ?>
<?php asset_script($appBase, 'js/dashboards/matron_housemother_dashboard.js'); ?>
