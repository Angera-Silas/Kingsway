<?php
/**
 * Deputy Head – Academic Dashboard
 * Role ID: 6
 */
?>

<div class="container-fluid py-4 role-dashboard" id="deputyAcademicDashboard">
    <div class="dash-greeting-bar">
        <div>
            <h5>
                <i class="bi bi-mortarboard me-2"></i>
                Deputy Head – Academic Dashboard
            </h5>
            <p>Lesson quality, assessment coverage and teacher management.</p>
        </div>
        <div class="dash-meta">
            <span class="dash-badge" id="deputyAcademicDashboardScope"></span>
            <span class="small opacity-75">
                Updated <span id="deputyAcademicDashboardLastUpdated">—</span>
            </span>
            <button type="button" class="dash-refresh-btn" id="deputyAcademicDashboardRefresh">
                <i class="bi bi-arrow-clockwise me-1"></i>Refresh
            </button>
        </div>
    </div>

    <div class="dashboard-state alert alert-light border" id="deputyAcademicDashboardState" role="status">
        Loading dashboard data...
    </div>

    <?php $rootId = 'deputyAcademicDashboard'; $periods = [
        ['key' => 'today', 'label' => 'Today'],
        ['key' => 'week', 'label' => 'This Week'],
        ['key' => 'term', 'label' => 'This Term'],
    ]; require __DIR__ . '/partials/period_selector.php'; ?>

    <div class="row g-3 mb-4">
        <div class="col-md-2">
            <div class="dash-stat dsc-orange h-100">
                <i class="bi bi-journal-text dash-stat-icon"></i>
                <div class="dash-stat-value" id="daLessonPlans">0</div>
                <div class="dash-stat-label">Lesson Plans Pending</div>
                <div class="dash-stat-sub" id="daLessonPlansSub">Awaiting approval</div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="dash-stat dsc-blue h-100">
                <i class="bi bi-clipboard2-check dash-stat-icon"></i>
                <div class="dash-stat-value" id="daGrading">0</div>
                <div class="dash-stat-label">Grading Status</div>
                <div class="dash-stat-sub" id="daGradingSub">Pending grades</div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="dash-stat dsc-green h-100">
                <i class="bi bi-calendar-check dash-stat-icon"></i>
                <div class="dash-stat-value" id="daTimetable">0%</div>
                <div class="dash-stat-label">Timetable Coverage</div>
                <div class="dash-stat-sub" id="daTimetableSub">Of scheduled periods</div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="dash-stat dsc-amber h-100">
                <i class="bi bi-person-plus dash-stat-icon"></i>
                <div class="dash-stat-value" id="daAdmissions">0</div>
                <div class="dash-stat-label">Pending Admissions</div>
                <div class="dash-stat-sub" id="daAdmissionsSub">Awaiting placement</div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="dash-stat dsc-teal h-100">
                <i class="bi bi-person-check dash-stat-icon"></i>
                <div class="dash-stat-value" id="daAttendance">0%</div>
                <div class="dash-stat-label">Attendance Today</div>
                <div class="dash-stat-sub" id="daAttendanceSub">Present rate</div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="dash-stat dsc-red h-100">
                <i class="bi bi-exclamation-triangle dash-stat-icon"></i>
                <div class="dash-stat-value" id="daWorkload">0</div>
                <div class="dash-stat-label">Workload Alerts</div>
                <div class="dash-stat-sub" id="daWorkloadSub">Teachers over limit</div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-lg-6">
            <div class="card dash-card h-100">
                <div class="card-header">
                    <h6 class="dashboard-section-title">
                        <i class="bi bi-graph-up"></i>Lesson Plan Trend
                    </h6>
                </div>
                <div class="card-body">
                    <div class="dash-chart-wrap"><canvas id="daLessonPlanChart"></canvas></div>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card dash-card h-100">
                <div class="card-header">
                    <h6 class="dashboard-section-title">
                        <i class="bi bi-bar-chart"></i>Academic Performance
                    </h6>
                </div>
                <div class="card-body">
                    <div class="dash-chart-wrap"><canvas id="daPerformanceChart"></canvas></div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-xl-6">
            <div class="card dash-card h-100">
                <div class="card-header d-flex align-items-center justify-content-between gap-2">
                    <h6 class="dashboard-section-title">
                        <i class="bi bi-table"></i>Pending Placements
                    </h6>
                    <button type="button" class="btn btn-sm btn-outline-success" data-route="admissions_academic_applications">View all</button>
                </div>
                <div class="card-body p-0 dashboard-table-wrap">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th scope="col">Student</th>
                                    <th scope="col">Applied Class</th>
                                    <th scope="col">Test Score</th>
                                    <th scope="col">Status</th>
                                </tr>
                            </thead>
                            <tbody id="daPlacementsBody">
                                <tr><td colspan="4" class="text-center text-muted py-4">Loading...</td></tr>
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
                        <i class="bi bi-table"></i>Incomplete Grades
                    </h6>
                    <button type="button" class="btn btn-sm btn-outline-success" data-route="assessments_exams">View all</button>
                </div>
                <div class="card-body p-0 dashboard-table-wrap">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th scope="col">Teacher</th>
                                    <th scope="col">Class</th>
                                    <th scope="col">% Pending</th>
                                    <th scope="col">Status</th>
                                </tr>
                            </thead>
                            <tbody id="daGradesBody">
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
                        <a href="#" class="dash-quick-link" data-route="timetable">
                            <i class="bi bi-calendar3 ql-icon bg-success text-white"></i>
                            <span>My Timetable</span>
                            <i class="bi bi-chevron-right ql-arrow"></i>
                        </a>
                        <a href="#" class="dash-quick-link" data-route="lesson_plan_approval">
                            <i class="bi bi-journal-check ql-icon bg-primary text-white"></i>
                            <span>Review Lesson Plans</span>
                            <i class="bi bi-chevron-right ql-arrow"></i>
                        </a>
                        <a href="#" class="dash-quick-link" data-route="manage_timetable">
                            <i class="bi bi-calendar2-week ql-icon bg-info text-white"></i>
                            <span>Manage Timetable</span>
                            <i class="bi bi-chevron-right ql-arrow"></i>
                        </a>
                        <a href="#" class="dash-quick-link" data-route="exam_setup">
                            <i class="bi bi-file-earmark-text ql-icon bg-warning text-white"></i>
                            <span>Exam Setup</span>
                            <i class="bi bi-chevron-right ql-arrow"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="<?= $appBase ?>/js/dashboards/dashboard_base_controller.js?v=<?= filemtime(__DIR__ . '/../../js/dashboards/dashboard_base_controller.js') ?>"></script>
<script src="<?= $appBase ?>/js/dashboards/deputy_head_academic_dashboard.js?v=<?= filemtime(__DIR__ . '/../../js/dashboards/deputy_head_academic_dashboard.js') ?>"></script>
