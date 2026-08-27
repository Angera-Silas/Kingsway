<?php
/**
 * Deputy Head – Academic Dashboard
 * Role ID: 6
 * Rows: Curriculum Health KPIs, Progress Bars, Class Matrix, Teacher Compliance, Intervention, Quick Actions
 */
?>

<div class="container-fluid py-4 role-dashboard dashboard-surface dashboard-academic-command" id="deputyAcademicDashboard" data-dashboard-layout="registrar-rail">


    <!-- META BAR -->
    <div class="dash-meta-bar mb-4 d-flex align-items-center justify-content-end gap-3 flex-wrap">
        <span class="dash-badge bg-success" id="deputyAcademicDashboardScope"></span>
        <span class="small text-muted">Updated <span id="deputyAcademicDashboardLastUpdated">—</span></span>
        <button type="button" class="btn btn-sm btn-outline-success" id="deputyAcademicDashboardRefresh">
            <i class="bi bi-arrow-clockwise me-1"></i>Refresh
        </button>
    </div>

    <!-- STATE / ERROR BANNER -->
    <div class="dashboard-state alert alert-light border d-none" id="deputyAcademicDashboardState" role="status"></div>

    <!-- CRITICAL ALERTS (hidden until populated by JS) -->
    <div class="alert alert-danger d-none mb-4" id="daCriticalAlerts" role="alert">
        <h6 class="alert-heading"><i class="bi bi-exclamation-octagon me-2"></i>Critical Academic Alerts</h6>
        <ul class="mb-0" id="daCriticalAlertsList"></ul>
    </div>

    <!-- PERIOD SELECTOR -->
    <?php $rootId = 'deputyAcademicDashboard'; $periods = [
        ['key' => 'today', 'label' => 'Today'],
        ['key' => 'week', 'label' => 'This Week'],
        ['key' => 'term', 'label' => 'This Term'],
    ]; require __DIR__ . '/partials/period_selector.php'; ?>

    <!-- ════════════════════════════════════════════════════════
         ROW 1 — CURRICULUM HEALTH KPIs (6 cards)
         ════════════════════════════════════════════════════════ -->
    <div class="registrar-kpi-stack">
    <div class="row g-3 mb-3 registrar-kpi-rail" id="daKpiRow">
        <div class="col-xl-4 col-md-6">
            <div class="dash-stat dsc-blue h-100" aria-label="Curriculum Coverage">
                <i class="bi bi-journal-check dash-stat-icon"></i>
                <div class="dash-stat-value" id="daAdmissions">—</div>
                <div class="dash-stat-label">Pending Placements</div>
                <div class="dash-stat-sub">
                    <span id="daCoverageTrend" class="dash-compare-up">—</span> vs target 85%
                </div>
            </div>
        </div>
        <div class="col-xl-4 col-md-6">
            <div class="dash-stat dsc-green h-100" aria-label="Grading Progress">
                <i class="bi bi-clipboard2-check dash-stat-icon"></i>
                <div class="dash-stat-value" id="daGrading">—</div>
                <div class="dash-stat-label">Pending Grade Entries</div>
                <div class="dash-stat-sub">
                    <span id="daGradingTrend" class="dash-compare-up">—</span> of entries complete
                </div>
            </div>
        </div>
        <div class="col-xl-4 col-md-6">
            <div class="dash-stat dsc-teal h-100" aria-label="Timetable Coverage">
                <i class="bi bi-calendar3 dash-stat-icon"></i>
                <div class="dash-stat-value" id="daTimetable">—%</div>
                <div class="dash-stat-label">Timetable Coverage</div>
                <div class="dash-stat-sub">
                    <span id="daTimetableTrend" class="dash-compare-up">—</span> of periods filled
                </div>
            </div>
        </div>
    </div>
    <div class="row g-3 mb-4 registrar-kpi-rail registrar-kpi-rail-lower">
        <div class="col-xl-4 col-md-6">
            <div class="dash-stat dsc-cyan h-100" aria-label="Lesson Plans Submitted">
                <i class="bi bi-journal-text dash-stat-icon"></i>
                <div class="dash-stat-value" id="daLessonPlans">—%</div>
                <div class="dash-stat-label">Lesson Plans Submitted</div>
                <div class="dash-stat-sub">
                    <span id="daLessonPlanTrend" class="dash-compare-up">—</span> this week
                </div>
            </div>
        </div>
        <div class="col-xl-4 col-md-6">
            <div class="dash-stat dsc-indigo h-100" aria-label="Assessment Completion">
                <i class="bi bi-clipboard-data dash-stat-icon"></i>
                <div class="dash-stat-value" id="daAttendance">—%</div>
                <div class="dash-stat-label">Learner Attendance</div>
                <div class="dash-stat-sub">
                    <span id="daAssessTrend" class="dash-compare-up">—</span> results entered
                </div>
            </div>
        </div>
        <div class="col-xl-4 col-md-6">
            <div class="dash-stat dsc-red h-100" aria-label="Workload Alerts">
                <i class="bi bi-exclamation-triangle dash-stat-icon"></i>
                <div class="dash-stat-value" id="daWorkload">0</div>
                <div class="dash-stat-label">Workload Alerts</div>
                <div class="dash-stat-sub">
                    <span id="daWorkloadTrend" class="dash-compare-down">—</span> teachers over limit
                </div>
            </div>
        </div>
    </div>
    </div>

    <!-- ════════════════════════════════════════════════════════
         ROW 2 — CURRICULUM PROGRESS BARS (2 panels)
         ════════════════════════════════════════════════════════ -->
    <div class="row g-3 mb-4 registrar-analytics-panel">
        <!-- Coverage by Grade -->
        <div class="col-lg-6">
            <div class="card dash-card h-100">
                <div class="card-header d-flex justify-content-between align-items-start">
                    <div>
                        <h6 class="mb-0"><i class="bi bi-bar-chart-line me-2 text-primary"></i>Coverage by Grade</h6>
                        <small class="text-muted">Curriculum delivery progress per grade level</small>
                    </div>
                    <span class="badge bg-primary-subtle text-primary" id="daCoverageOverall">—</span>
                </div>
                <div class="card-body" id="daCoverageByGrade">
                    <div class="dash-chart-wrap mb-3"><canvas id="daPerformanceChart"></canvas></div>
                    <div class="d-flex align-items-center justify-content-center py-4 text-muted">
                        <div class="spinner-border spinner-border-sm me-2" role="status"></div>Loading coverage data…
                    </div>
                </div>
                <div class="card-footer bg-transparent border-top-0 pt-0">
                    <small class="text-muted">
                        <i class="bi bi-info-circle me-1"></i>
                        Target: 85% per grade by end of current half
                    </small>
                </div>
            </div>
        </div>

        <!-- Grading Status -->
        <div class="col-lg-6">
            <div class="card dash-card h-100">
                <div class="card-header d-flex justify-content-between align-items-start">
                    <div>
                        <h6 class="mb-0"><i class="bi bi-clipboard-check me-2 text-success"></i>Grading Status</h6>
                        <small class="text-muted">Completed / Pending / Overdue by subject area</small>
                    </div>
                    <div class="d-flex gap-2">
                        <span class="badge bg-success-subtle text-success" id="daGradingDone">0 done</span>
                        <span class="badge bg-warning-subtle text-warning" id="daGradingPending">0 pending</span>
                        <span class="badge bg-danger-subtle text-danger" id="daGradingOverdue">0 overdue</span>
                    </div>
                </div>
                <div class="card-body" id="daGradingStatus">
                    <div class="dash-chart-wrap mb-3"><canvas id="daLessonPlanChart"></canvas></div>
                    <div class="d-flex align-items-center justify-content-center py-4 text-muted">
                        <div class="spinner-border spinner-border-sm me-2" role="status"></div>Loading grading status…
                    </div>
                </div>
                <div class="card-footer bg-transparent border-top-0 pt-0">
                    <small class="text-muted">
                        <i class="bi bi-info-circle me-1"></i>
                        Overdue entries highlighted — contact subject teachers
                    </small>
                </div>
            </div>
        </div>
    </div>

    <!-- ════════════════════════════════════════════════════════
         ROW 3 — CLASS PERFORMANCE MATRIX (full-width table)
         ════════════════════════════════════════════════════════ -->
    <div class="row g-3 mb-4 registrar-enrollment-grid">
        <div class="col-12">
            <div class="card dash-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="mb-0"><i class="bi bi-grid me-2 text-info"></i>Class Performance Matrix</h6>
                        <small class="text-muted">All classes at a glance — attendance, coverage, grading, performance</small>
                    </div>
                    <div class="d-flex gap-2 align-items-center">
                        <span class="badge bg-info-subtle text-info" id="daClassCount">0 classes</span>
                        <button type="button" class="btn btn-sm btn-outline-info" data-route="academic_reports">
                            Full Report <i class="bi bi-arrow-right ms-1"></i>
                        </button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive" style="max-height:420px;">
                        <table class="table table-hover align-middle mb-0" aria-label="Class performance overview">
                            <thead class="table-light" style="position:sticky;top:0;z-index:2;">
                                <tr>
                                    <th scope="col" style="min-width:120px;">Class</th>
                                    <th scope="col" class="text-center" style="min-width:80px;">Students</th>
                                    <th scope="col" class="text-center" style="min-width:100px;">Attendance</th>
                                    <th scope="col" class="text-center" style="min-width:100px;">Coverage</th>
                                    <th scope="col" class="text-center" style="min-width:100px;">Grading</th>
                                    <th scope="col" class="text-center" style="min-width:90px;">Avg Score</th>
                                    <th scope="col" class="text-center" style="min-width:80px;">Trend</th>
                                    <th scope="col" class="text-center" style="min-width:90px;">Action</th>
                                </tr>
                            </thead>
                            <tbody id="daClassMatrixBody">
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-4">
                                        <div class="spinner-border spinner-border-sm me-2" role="status"></div>
                                        Loading class data…
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ════════════════════════════════════════════════════════
         ROW 4 — TEACHER COMPLIANCE + ASSESSMENT PIPELINE
         ════════════════════════════════════════════════════════ -->
    <div class="row g-3 mb-4">
        <!-- Teacher Compliance Matrix -->
        <div class="col-lg-7">
            <div class="card dash-card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="mb-0"><i class="bi bi-person-check me-2 text-primary"></i>Teacher Compliance Matrix</h6>
                        <small class="text-muted">Lesson plans · timetable adherence · grading progress · workload</small>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-primary" data-route="manage_staff">
                        All Staff <i class="bi bi-arrow-right ms-1"></i>
                    </button>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive" style="max-height:380px;">
                        <table class="table table-hover align-middle mb-0" aria-label="Teacher compliance status">
                            <thead class="table-light" style="position:sticky;top:0;z-index:2;">
                                <tr>
                                    <th scope="col" style="min-width:160px;">Teacher</th>
                                    <th scope="col" class="text-center" style="min-width:100px;">Lesson Plans</th>
                                    <th scope="col" class="text-center" style="min-width:100px;">Timetable</th>
                                    <th scope="col" class="text-center" style="min-width:100px;">Grading</th>
                                    <th scope="col" class="text-center" style="min-width:100px;">Workload</th>
                                </tr>
                            </thead>
                            <tbody id="daComplianceBody">
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">
                                        <div class="spinner-border spinner-border-sm me-2" role="status"></div>
                                        Loading compliance data…
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Assessment Pipeline -->
        <div class="col-lg-5">
            <div class="card dash-card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="mb-0"><i class="bi bi-hourglass-split me-2 text-warning"></i>Assessment Pipeline</h6>
                        <small class="text-muted">Upcoming and overdue assessments</small>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-warning" data-route="assessments_exams">
                        View All <i class="bi bi-arrow-right ms-1"></i>
                    </button>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive" style="max-height:380px;">
                        <table class="table table-hover align-middle mb-0" aria-label="Assessment pipeline">
                            <thead class="table-light" style="position:sticky;top:0;z-index:2;">
                                <tr>
                                    <th scope="col" style="min-width:140px;">Assessment</th>
                                    <th scope="col" style="min-width:100px;">Class</th>
                                    <th scope="col" style="min-width:100px;">Due Date</th>
                                    <th scope="col" style="min-width:90px;">Status</th>
                                </tr>
                            </thead>
                            <tbody id="daAssessmentPipelineBody">
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-4">
                                        <div class="spinner-border spinner-border-sm me-2" role="status"></div>
                                        Loading pipeline…
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ════════════════════════════════════════════════════════
         ROW 5 — INTERVENTION NEEDS (2 panels)
         ════════════════════════════════════════════════════════ -->
    <div class="row g-3 mb-4">
        <!-- Classes Needing Intervention -->
        <div class="col-lg-6">
            <div class="card dash-card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="mb-0"><i class="bi bi-person-check me-2 text-danger"></i>Pending Class Placements</h6>
                        <small class="text-muted">Approved applicants awaiting academic placement</small>
                    </div>
                    <span class="badge bg-danger-subtle text-danger" id="daInterventionCount">0 issues</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive" style="max-height:340px;">
                        <table class="table table-hover align-middle mb-0" aria-label="Classes needing intervention">
                            <thead class="table-light" style="position:sticky;top:0;z-index:2;">
                                <tr>
                                    <th scope="col">Applicant</th>
                                    <th scope="col">Applied Grade</th>
                                    <th scope="col" class="text-center">Test Score</th>
                                    <th scope="col">Status</th>
                                </tr>
                            </thead>
                            <tbody id="daPlacementsBody">
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-4">
                                        <div class="spinner-border spinner-border-sm me-2" role="status"></div>
                                        Loading intervention data…
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Early Warning Learners -->
        <div class="col-lg-6">
            <div class="card dash-card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="mb-0"><i class="bi bi-clipboard-x me-2 text-purple"></i>Incomplete Grade Entries</h6>
                        <small class="text-muted">Teacher assessment records requiring completion</small>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-route="student_reports">
                        All Reports <i class="bi bi-arrow-right ms-1"></i>
                    </button>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive" style="max-height:340px;">
                        <table class="table table-hover align-middle mb-0" aria-label="Early warning learners">
                            <thead class="table-light" style="position:sticky;top:0;z-index:2;">
                                <tr>
                                    <th scope="col" style="min-width:140px;">Teacher</th>
                                    <th scope="col" style="min-width:80px;">Class</th>
                                    <th scope="col" class="text-center" style="min-width:100px;">Pending</th>
                                    <th scope="col" style="min-width:130px;">Status</th>
                                </tr>
                            </thead>
                            <tbody id="daGradesBody">
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-4">
                                        <div class="spinner-border spinner-border-sm me-2" role="status"></div>
                                        Loading early warning data…
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ════════════════════════════════════════════════════════
         ROW 6 — QUICK ACTIONS
         ════════════════════════════════════════════════════════ -->
    <div class="row g-3 mb-4">
        <div class="col-12">
            <div class="card dash-card">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-lightning me-2 text-warning"></i>Quick Actions</h6>
                </div>
                <div class="card-body">
                    <div class="row g-2">
                        <div class="col-md col-6">
                            <a href="#" class="dash-quick-link" data-route="lesson_plan_approval">
                                <i class="bi bi-journal-check ql-icon bg-primary text-white"></i>
                                <span>Review Lesson Plans</span>
                                <i class="bi bi-chevron-right ql-arrow"></i>
                            </a>
                        </div>
                        <div class="col-md col-6">
                            <a href="#" class="dash-quick-link" data-route="manage_timetable">
                                <i class="bi bi-calendar2-week ql-icon bg-info text-white"></i>
                                <span>Manage Timetable</span>
                                <i class="bi bi-chevron-right ql-arrow"></i>
                            </a>
                        </div>
                        <div class="col-md col-6">
                            <a href="#" class="dash-quick-link" data-route="exam_setup">
                                <i class="bi bi-file-earmark-text ql-icon bg-warning text-white"></i>
                                <span>Exam Setup</span>
                                <i class="bi bi-chevron-right ql-arrow"></i>
                            </a>
                        </div>
                        <div class="col-md col-6">
                            <a href="#" class="dash-quick-link" data-route="grade_management">
                                <i class="bi bi-clipboard-check ql-icon bg-success text-white"></i>
                                <span>Grade Management</span>
                                <i class="bi bi-chevron-right ql-arrow"></i>
                            </a>
                        </div>
                        <div class="col-md col-6">
                            <a href="#" class="dash-quick-link" data-route="academic_reports">
                                <i class="bi bi-file-earmark-bar-graph ql-icon bg-danger text-white"></i>
                                <span>Reports</span>
                                <i class="bi bi-chevron-right ql-arrow"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<?php asset_script($appBase, 'js/dashboards/dashboard_base_controller.js'); ?>
<?php asset_script($appBase, 'js/dashboards/deputy_head_academic_dashboard.js'); ?>
