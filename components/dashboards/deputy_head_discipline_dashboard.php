<?php
/**
 * Deputy Head – Discipline Dashboard
 * Role ID: 63
 */
?>

<div class="container-fluid py-4 role-dashboard" id="deputyDisciplineDashboard">
    <div class="dash-greeting-bar">
        <div>
            <h5>
                <i class="bi bi-shield-exclamation me-2"></i>
                Deputy Head – Discipline Dashboard
            </h5>
            <p>Student behavior, attendance patterns and welfare.</p>
        </div>
        <div class="dash-meta">
            <span class="dash-badge" id="deputyDisciplineDashboardScope"></span>
            <span class="small opacity-75">
                Updated <span id="deputyDisciplineDashboardLastUpdated">—</span>
            </span>
            <button type="button" class="dash-refresh-btn" id="deputyDisciplineDashboardRefresh">
                <i class="bi bi-arrow-clockwise me-1"></i>Refresh
            </button>
        </div>
    </div>

    <div class="dashboard-state alert alert-light border" id="deputyDisciplineDashboardState" role="status">
        Loading dashboard data...
    </div>

    <?php $rootId = 'deputyDisciplineDashboard'; $periods = [
        ['key' => 'today', 'label' => 'Today'],
        ['key' => 'week', 'label' => 'This Week'],
        ['key' => 'term', 'label' => 'This Term'],
    ]; require __DIR__ . '/partials/period_selector.php'; ?>

    <div class="row g-3 mb-4">
        <div class="col-md-2">
            <div class="dash-stat dsc-red h-100">
                <i class="bi bi-exclamation-circle dash-stat-icon"></i>
                <div class="dash-stat-value" id="ddOpenCases">0</div>
                <div class="dash-stat-label">Open Cases</div>
                <div class="dash-stat-sub" id="ddOpenCasesSub">Requiring attention</div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="dash-stat dsc-orange h-100">
                <i class="bi bi-exclamation-triangle dash-stat-icon"></i>
                <div class="dash-stat-value" id="ddUrgent">0</div>
                <div class="dash-stat-label">Urgent Cases</div>
                <div class="dash-stat-sub" id="ddUrgentSub">Immediate action</div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="dash-stat dsc-amber h-100">
                <i class="bi bi-person-x dash-stat-icon"></i>
                <div class="dash-stat-value" id="ddAbsentees">0</div>
                <div class="dash-stat-label">Absentees Today</div>
                <div class="dash-stat-sub" id="ddAbsenteesSub">Students absent</div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="dash-stat dsc-pink h-100">
                <i class="bi bi-clock-history dash-stat-icon"></i>
                <div class="dash-stat-value" id="ddTruancy">0</div>
                <div class="dash-stat-label">Chronic Truancy</div>
                <div class="dash-stat-sub" id="ddTruancySub">Repeat offenders</div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="dash-stat dsc-purple h-100">
                <i class="bi bi-heart dash-stat-icon"></i>
                <div class="dash-stat-value" id="ddCounseling">0</div>
                <div class="dash-stat-label">Counseling Referrals</div>
                <div class="dash-stat-sub" id="ddCounselingSub">Pending sessions</div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="dash-stat dsc-cyan h-100">
                <i class="bi bi-people dash-stat-icon"></i>
                <div class="dash-stat-value" id="ddMeetings">0</div>
                <div class="dash-stat-label">Parent Meetings</div>
                <div class="dash-stat-sub" id="ddMeetingsSub">Scheduled this week</div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-lg-6">
            <div class="card dash-card h-100">
                <div class="card-header">
                    <h6 class="dashboard-section-title">
                        <i class="bi bi-graph-down-arrow"></i>Discipline Trend
                    </h6>
                </div>
                <div class="card-body">
                    <div class="dash-chart-wrap"><canvas id="ddTrendChart"></canvas></div>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card dash-card h-100">
                <div class="card-header">
                    <h6 class="dashboard-section-title">
                        <i class="bi bi-pie-chart"></i>Cases by Category
                    </h6>
                </div>
                <div class="card-body">
                    <div class="dash-chart-wrap"><canvas id="ddCategoryChart"></canvas></div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-xl-6">
            <div class="card dash-card h-100">
                <div class="card-header d-flex align-items-center justify-content-between gap-2">
                    <h6 class="dashboard-section-title">
                        <i class="bi bi-table"></i>Active Cases
                    </h6>
                    <button type="button" class="btn btn-sm btn-outline-success" data-route="discipline_cases">View all</button>
                </div>
                <div class="card-body p-0 dashboard-table-wrap">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th scope="col">Student</th>
                                    <th scope="col">Category</th>
                                    <th scope="col">Severity</th>
                                    <th scope="col">Date</th>
                                    <th scope="col">Status</th>
                                </tr>
                            </thead>
                            <tbody id="ddCasesBody">
                                <tr><td colspan="5" class="text-center text-muted py-4">Loading...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-6">
            <div class="card dash-card h-100">
                <div class="card-header d-flex align-items-center justify-content-between gap-2">
                    <h6 class="dashboard-section-title">
                        <i class="bi bi-table"></i>At-Risk Students
                    </h6>
                    <button type="button" class="btn btn-sm btn-outline-success" data-route="student_counseling">View all</button>
                </div>
                <div class="card-body p-0 dashboard-table-wrap">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th scope="col">Student</th>
                                    <th scope="col">Class</th>
                                    <th scope="col">Infractions</th>
                                    <th scope="col">Last Incident</th>
                                </tr>
                            </thead>
                            <tbody id="ddAtRiskBody">
                                <tr><td colspan="4" class="text-center text-muted py-4">Loading...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-xl-8">
            <div class="card dash-card">
                <div class="card-header">
                    <h6 class="dashboard-section-title">
                        <i class="bi bi-lightning-charge"></i>Quick Actions
                    </h6>
                </div>
                <div class="card-body">
                    <div class="dashboard-action-grid">
                        <a href="#" class="dash-quick-link" data-route="log_discipline_case">
                            <i class="bi bi-plus-circle ql-icon bg-danger text-white"></i>
                            <span>Log Discipline Case</span>
                            <i class="bi bi-chevron-right ql-arrow"></i>
                        </a>
                        <a href="#" class="dash-quick-link" data-route="discipline_cases">
                            <i class="bi bi-folder2-open ql-icon bg-primary text-white"></i>
                            <span>All Cases</span>
                            <i class="bi bi-chevron-right ql-arrow"></i>
                        </a>
                        <a href="#" class="dash-quick-link" data-route="attendance_reports">
                            <i class="bi bi-clipboard-data ql-icon bg-success text-white"></i>
                            <span>Attendance Reports</span>
                            <i class="bi bi-chevron-right ql-arrow"></i>
                        </a>
                        <a href="#" class="dash-quick-link" data-route="student_counseling">
                            <i class="bi bi-heart-pulse ql-icon bg-purple text-white"></i>
                            <span>Refer to Counseling</span>
                            <i class="bi bi-chevron-right ql-arrow"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="<?= $appBase ?>/js/dashboards/dashboard_base_controller.js?v=<?= filemtime(__DIR__ . '/../../js/dashboards/dashboard_base_controller.js') ?>"></script>
<script src="<?= $appBase ?>/js/dashboards/deputy_head_discipline_dashboard.js?v=<?= filemtime(__DIR__ . '/../../js/dashboards/deputy_head_discipline_dashboard.js') ?>"></script>
