<?php
/**
 * Deputy Head – Discipline Dashboard
 * Role ID: 63
 *
 * Row 1: Incident Alerts — 6 severity KPI cards
 * Row 2: Critical Cases — urgent/repeat-offender table
 * Row 3: Pattern Analysis — category, time/location, repeat offenders, intervention effectiveness
 * Row 4: Attendance–Behaviour Correlation — dual-risk students
 * Row 5: Safeguarding & Compliance — flags and compliance rates
 * Row 6: Quick Actions
 */
?>

<div class="container-fluid py-4 role-dashboard dashboard-surface dashboard-safeguarding" id="deputyDisciplineDashboard" data-dashboard-layout="executive-wave-mosaic">
    
    <div class="dash-meta-bar mb-3 d-flex align-items-center justify-content-end gap-3 flex-wrap">
        <span class="dash-badge bg-success" id="deputyDisciplineDashboardScope"></span>
        <span class="small text-muted">
            Updated <span id="deputyDisciplineDashboardLastUpdated">—</span>
        </span>
        <button type="button" class="btn btn-sm btn-outline-success" id="deputyDisciplineDashboardRefresh">
            <i class="bi bi-arrow-clockwise me-1"></i>Refresh
        </button>
    </div>

    <div class="dashboard-state alert alert-light border" id="deputyDisciplineDashboardState" role="status">
        Loading dashboard data...
    </div>

    <?php $rootId = 'deputyDisciplineDashboard'; $periods = [
        ['key' => 'today', 'label' => 'Today'],
        ['key' => 'week', 'label' => 'This Week'],
        ['key' => 'term', 'label' => 'This Term'],
    ]; require __DIR__ . '/partials/period_selector.php'; ?>

    <!-- ROW 1 — INCIDENT ALERTS -->
    <div class="row g-3 mb-3 executive-wave-kpis">
        <div class="col-xl-4 col-md-6">
            <div class="dash-stat dsc-red h-100">
                <i class="bi bi-exclamation-circle dash-stat-icon"></i>
                <div class="dash-stat-value" id="ddOpenCases">0</div>
                <div class="dash-stat-label">Open Cases</div>
                <div class="dash-stat-sub" id="ddOpenCasesSub">Requiring attention</div>
            </div>
        </div>
        <div class="col-xl-4 col-md-6">
            <div class="dash-stat" style="background:linear-gradient(135deg,#c0392b,#e74c3c);color:#fff;">
                <i class="bi bi-exclamation-triangle dash-stat-icon"></i>
                <div class="dash-stat-value" id="ddUrgent">0</div>
                <div class="dash-stat-label">Urgent Cases</div>
                <div class="dash-stat-sub" id="ddUrgentCasesSub">Immediate action needed</div>
            </div>
        </div>
        <div class="col-xl-4 col-md-6">
            <div class="dash-stat dsc-orange h-100">
                <i class="bi bi-person-x dash-stat-icon"></i>
                <div class="dash-stat-value" id="ddAbsentees">0</div>
                <div class="dash-stat-label">Absent Today</div>
                <div class="dash-stat-sub" id="ddChronicAbsentSub">3+ unexcused absences</div>
            </div>
        </div>
    </div>
    <div class="row g-3 mb-4 executive-wave-kpis executive-wave-kpis-secondary">
        <div class="col-xl-4 col-md-6">
            <div class="dash-stat dsc-green h-100">
                <i class="bi bi-check-circle dash-stat-icon"></i>
                <div class="dash-stat-value" id="ddTruancy">0</div>
                <div class="dash-stat-label">Chronic Truancy Alerts</div>
                <div class="dash-stat-sub" id="ddResolvedSub">Cases closed</div>
            </div>
        </div>
        <div class="col-xl-4 col-md-6">
            <div class="dash-stat dsc-teal h-100">
                <i class="bi bi-clock-history dash-stat-icon"></i>
                <div class="dash-stat-value" id="ddCounseling">—</div>
                <div class="dash-stat-label">Counselling Referrals</div>
                <div class="dash-stat-sub" id="ddAvgResolutionSub">Days to close</div>
            </div>
        </div>
        <div class="col-xl-4 col-md-6">
            <div class="dash-stat dsc-blue h-100">
                <i class="bi bi-telephone-outbound dash-stat-icon"></i>
                <div class="dash-stat-value" id="ddMeetings">0</div>
                <div class="dash-stat-label">Parent Meetings</div>
                <div class="dash-stat-sub" id="ddParentContactsSub">This period</div>
            </div>
        </div>
    </div>

    <!-- ROW 2 — CRITICAL CASES -->
    <div class="row g-3 mb-4 executive-wave-critical">
        <div class="col-12">
            <div class="card dash-card h-100 border-danger">
                <div class="card-header bg-danger bg-opacity-10 d-flex align-items-center justify-content-between">
                    <h6 class="dashboard-section-title mb-0 text-danger">
                        <i class="bi bi-shield-exclamation me-1"></i>Critical Cases — Immediate Attention
                    </h6>
                    <span class="badge bg-danger" id="ddCriticalCount">0</span>
                </div>
                <div class="card-body dashboard-table-wrap">
                    <div class="dash-chart-wrap-lg mb-3"><canvas id="ddTrendChart"></canvas></div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-dark">
                                <tr>
                                    <th scope="col">Learner</th>
                                    <th scope="col">Category</th>
                                    <th scope="col">Severity</th>
                                    <th scope="col">Incident Date</th>
                                    <th scope="col">Status</th>
                                </tr>
                            </thead>
                            <tbody id="ddCasesBody">
                                <tr><td colspan="5" class="text-center text-muted py-4">Loading critical cases...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ROW 3 — PATTERN ANALYSIS -->
    <div class="row g-3 mb-4 executive-wave-mosaic">
        <div class="col-lg-3">
            <div class="card dash-card h-100">
                <div class="card-header">
                    <h6 class="dashboard-section-title">
                        <i class="bi bi-tags"></i>Cases by Category
                    </h6>
                </div>
                <div class="card-body p-0">
                    <div class="dash-chart-wrap p-3"><canvas id="ddCategoryChart"></canvas></div>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr>
                                    <th scope="col">Category</th>
                                    <th scope="col" class="text-end">Count</th>
                                    <th scope="col" class="text-end">%</th>
                                </tr>
                            </thead>
                            <tbody id="ddCategoryBody">
                                <tr><td colspan="3" class="text-center text-muted py-3">Loading...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-3">
            <div class="card dash-card h-100">
                <div class="card-header">
                    <h6 class="dashboard-section-title">
                        <i class="bi bi-geo-alt"></i>Cases by Time / Location
                    </h6>
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush" id="ddTimeLocationList">
                        <li class="list-group-item text-center text-muted py-3">Loading...</li>
                    </ul>
                </div>
            </div>
        </div>
        <div class="col-lg-3">
            <div class="card dash-card h-100">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <h6 class="dashboard-section-title mb-0">
                        <i class="bi bi-arrow-repeat"></i>Repeat Offenders
                    </h6>
                    <span class="badge bg-warning text-dark" id="ddRepeatCount">0</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr>
                                    <th scope="col">Student</th>
                                    <th scope="col" class="text-end">Cases</th>
                                    <th scope="col">Trend</th>
                                </tr>
                            </thead>
                            <tbody id="ddRepeatBody">
                                <tr><td colspan="3" class="text-center text-muted py-3">Loading...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-3">
            <div class="card dash-card h-100">
                <div class="card-header">
                    <h6 class="dashboard-section-title">
                        <i class="bi bi-bar-chart-line"></i>Intervention Effectiveness
                    </h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr>
                                    <th scope="col">Method</th>
                                    <th scope="col" class="text-end">Success %</th>
                                </tr>
                            </thead>
                            <tbody id="ddInterventionBody">
                                <tr><td colspan="2" class="text-center text-muted py-3">Loading...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ROW 4 — ATTENDANCE–BEHAVIOUR CORRELATION -->
    <div class="row g-3 mb-4">
        <div class="col-12">
            <div class="card dash-card h-100">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <h6 class="dashboard-section-title mb-0">
                        <i class="bi bi-link-45deg"></i>Attendance–Behaviour Correlation
                    </h6>
                    <button type="button" class="btn btn-sm btn-outline-success" data-route="attendance_reports">View all reports</button>
                </div>
                <div class="card-body p-0 dashboard-table-wrap">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th scope="col">Student</th>
                                    <th scope="col">Class</th>
                                    <th scope="col" class="text-end">Absences</th>
                                    <th scope="col" class="text-end">Incidents</th>
                                    <th scope="col" class="text-end">Risk Score</th>
                                    <th scope="col">Last Intervention</th>
                                    <th scope="col">Next Action</th>
                                </tr>
                            </thead>
                            <tbody id="ddCorrelationBody">
                                <tr><td colspan="7" class="text-center text-muted py-4">Loading correlation data...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ROW 5 — SAFEGUARDING & COMPLIANCE -->
    <div class="row g-3 mb-4">
        <div class="col-lg-6">
            <div class="card dash-card h-100">
                <div class="card-header">
                    <h6 class="dashboard-section-title">
                        <i class="bi bi-shield-lock"></i>Safeguarding Flags
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row text-center g-3">
                        <div class="col-4">
                            <div class="p-3 rounded bg-danger bg-opacity-10">
                                <div class="fs-3 fw-bold text-danger" id="ddSafeguardConfidential">0</div>
                                <div class="small text-muted">Confidential Cases</div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="p-3 rounded bg-warning bg-opacity-10">
                                <div class="fs-3 fw-bold text-warning" id="ddSafeguardUrgentRef">0</div>
                                <div class="small text-muted">Urgent Referrals</div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="p-3 rounded bg-info bg-opacity-10">
                                <div class="fs-3 fw-bold text-info" id="ddSafeguardPending">0</div>
                                <div class="small text-muted">Pending Review</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card dash-card h-100">
                <div class="card-header">
                    <h6 class="dashboard-section-title">
                        <i class="bi bi-clipboard-check"></i>Compliance Status
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row text-center g-3">
                        <div class="col-4">
                            <div class="p-3 rounded bg-success bg-opacity-10">
                                <div class="fs-3 fw-bold text-success" id="ddComplianceReports">0%</div>
                                <div class="small text-muted">Reports Submitted</div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="p-3 rounded bg-primary bg-opacity-10">
                                <div class="fs-3 fw-bold text-primary" id="ddComplianceNotify">0%</div>
                                <div class="small text-muted">Parent Notified 24h</div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="p-3 rounded bg-teal bg-opacity-10">
                                <div class="fs-3 fw-bold text-teal" id="ddComplianceResponse">—</div>
                                <div class="small text-muted">Avg Response Time</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ROW 6 — QUICK ACTIONS -->
    <div class="row g-3">
        <div class="col-12">
            <div class="card dash-card">
                <div class="card-body">
                    <div class="dashboard-action-grid">
                        <a href="#" class="dash-quick-link" data-route="log_discipline_case">
                            <i class="bi bi-plus-circle ql-icon bg-danger text-white"></i>
                            <span>Log Case</span>
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
                            <span>Refer to Counselor</span>
                            <i class="bi bi-chevron-right ql-arrow"></i>
                        </a>
                        <a href="#" class="dash-quick-link" data-route="parent_communication">
                            <i class="bi bi-chat-dots ql-icon bg-info text-white"></i>
                            <span>Parent Communication</span>
                            <i class="bi bi-chevron-right ql-arrow"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php asset_script($appBase, 'js/dashboards/dashboard_base_controller.js'); ?>
<?php asset_script($appBase, 'js/dashboards/deputy_head_discipline_dashboard.js'); ?>
