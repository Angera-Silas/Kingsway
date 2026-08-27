<?php
/**
 * Headteacher Dashboard — Strategic command centre
 * Role ID: 5
 * 6 rows: Executive KPIs, Academic Intelligence, Financial Health,
 *         Operational Alerts, Priority Actions, Events & Quick Actions
 */
?>

<div class="container-fluid py-4 role-dashboard dashboard-surface dashboard-leadership" id="headteacherDashboard" data-dashboard-layout="edu-center-command">
    
    <!-- Period Selector -->
    <?php $rootId = 'headteacherDashboard'; require __DIR__ . '/partials/period_selector.php'; ?>

    <!-- Loading / Error states -->
    <div id="dashboardError" class="alert alert-danger d-none mb-3" role="alert">
        <i class="bi bi-exclamation-triangle me-1"></i>
        <span id="dashboardErrorMessage">Failed to load dashboard data.</span>
    </div>

    <!-- ======================================================================
         ROW 1 — EXECUTIVE SUMMARY KPI CARDS
         ====================================================================== -->
    <div class="row g-3 mb-3 edu-center-kpis" id="htKpiRow">
        <!-- Total Students -->
        <div class="col-xl-4 col-md-6">
            <div class="dash-stat dsc-indigo">
                <i class="bi bi-people dash-stat-icon"></i>
                <div class="dash-stat-value" id="totalStudents">—</div>
                <div class="dash-stat-label">Total Students</div>
                <div class="d-flex align-items-center gap-2 mt-1">
                    <span class="dash-compare-up" id="studentGrowth">—</span>
                    <span class="dash-stat-sub">vs last term</span>
                </div>
                <div class="dash-stat-sub" id="studentTarget">Target: 100% enrolled</div>
            </div>
        </div>
        <!-- Today's Attendance -->
        <div class="col-xl-4 col-md-6">
            <div class="dash-stat dsc-green">
                <i class="bi bi-calendar-check dash-stat-icon"></i>
                <div class="dash-stat-value" id="attendanceToday">—%</div>
                <div class="dash-stat-label">Attendance Today</div>
                <div class="d-flex align-items-center gap-2 mt-1">
                    <span class="dash-compare-up" id="attendanceDetails">—</span>
                    <span class="dash-stat-sub">vs last term</span>
                </div>
                <div class="dash-stat-sub" id="attendanceTarget">Target: 95%</div>
            </div>
        </div>
        <!-- Fee Collection Rate -->
        <div class="col-xl-4 col-md-6">
            <div class="dash-stat dsc-cyan">
                <i class="bi bi-cash-stack dash-stat-icon"></i>
                <div class="dash-stat-value" id="classSchedules">—</div>
                <div class="dash-stat-label">Class Schedules</div>
                <div class="d-flex align-items-center gap-2 mt-1">
                    <span class="dash-compare-up" id="feeTrend"><i class="bi bi-arrow-up"></i> —</span>
                    <span class="dash-stat-sub">vs last term</span>
                </div>
                <div class="dash-stat-sub">Active teaching sessions this week</div>
            </div>
        </div>
    </div>
    <div class="row g-3 mb-4 edu-center-kpis edu-center-kpis-secondary">
        <!-- Staff Present -->
        <div class="col-xl-4 col-md-6">
            <div class="dash-stat dsc-purple">
                <i class="bi bi-person-badge dash-stat-icon"></i>
                <div class="dash-stat-value" id="disciplineCases">—</div>
                <div class="dash-stat-label">Open Discipline Cases</div>
                <div class="d-flex align-items-center gap-2 mt-1">
                    <span class="dash-compare-up" id="staffTrend"><i class="bi bi-arrow-up"></i> —</span>
                    <span class="dash-stat-sub">of <span id="staffTotal">—</span></span>
                </div>
                <div class="dash-stat-sub" id="disciplineDetails">Requiring leadership attention</div>
            </div>
        </div>
        <!-- Boarding Occupancy -->
        <div class="col-xl-4 col-md-6">
            <div class="dash-stat dsc-teal">
                <i class="bi bi-house-door dash-stat-icon"></i>
                <div class="dash-stat-value" id="parentComms">—</div>
                <div class="dash-stat-label">Parent Communications</div>
                <div class="d-flex align-items-center gap-2 mt-1">
                    <span class="dash-compare-up" id="boardingTrend"><i class="bi bi-arrow-up"></i> —</span>
                    <span class="dash-stat-sub">vs last term</span>
                </div>
                <div class="dash-stat-sub">Sent this week</div>
            </div>
        </div>
        <!-- Pending Admissions -->
        <div class="col-xl-4 col-md-6">
            <div class="dash-stat dsc-amber">
                <i class="bi bi-person-plus dash-stat-icon"></i>
                <div class="dash-stat-value" id="pendingAdmissions">—</div>
                <div class="dash-stat-label">Pending Admissions</div>
                <div class="d-flex align-items-center gap-2 mt-1">
                    <span class="dash-compare-down" id="admissionDetails">—</span>
                    <span class="dash-stat-sub">vs last term</span>
                </div>
                <div class="dash-stat-sub" id="admissionTarget">Applications awaiting</div>
            </div>
        </div>
    </div>

    <!-- ======================================================================
         ROW 2 — ACADEMIC INTELLIGENCE (4 panels)
         ====================================================================== -->
    <div class="row g-3 mb-4 edu-center-academics">
        <!-- 2a: Performance by Grade -->
        <div class="col-lg-3 col-md-6">
            <div class="card dash-card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="bi bi-bar-chart me-2 text-primary"></i>Performance by Grade</h6>
                    <a href="home.php?route=academic_reports" class="btn btn-sm btn-outline-primary" data-route="academic_reports">Details</a>
                </div>
                <div class="card-body">
                    <div class="dash-chart-wrap-lg"><canvas id="performanceChart"></canvas></div>
                </div>
            </div>
        </div>

        <!-- 2b: Teacher Compliance -->
        <div class="col-lg-3 col-md-6">
            <div class="card dash-card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="bi bi-journal-check me-2 text-success"></i>Teacher Compliance</h6>
                    <a href="home.php?route=manage_staff" class="btn btn-sm btn-outline-success" data-route="manage_staff">Staff</a>
                </div>
                <div class="card-body d-flex flex-column gap-2">
                    <div class="dash-chart-wrap"><canvas id="attendanceChart"></canvas></div>
                    <div class="dash-kpi-widget">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="dash-kpi-title">Lesson Plans</span>
                            <span class="dash-kpi-value" id="htLessonPlans">—%</span>
                        </div>
                        <div class="progress mt-1">
                            <div class="progress-bar bg-success" id="htLessonPlansBar" role="progressbar" style="width:0%"></div>
                        </div>
                    </div>
                    <div class="dash-kpi-widget">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="dash-kpi-title">Timetable Coverage</span>
                            <span class="dash-kpi-value" id="htTimetableCoverage">—%</span>
                        </div>
                        <div class="progress mt-1">
                            <div class="progress-bar bg-primary" id="htTimetableBar" role="progressbar" style="width:0%"></div>
                        </div>
                    </div>
                    <div class="dash-kpi-widget">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="dash-kpi-title">Grading Progress</span>
                            <span class="dash-kpi-value" id="htGradingProgress">—%</span>
                        </div>
                        <div class="progress mt-1">
                            <div class="progress-bar bg-info" id="htGradingBar" role="progressbar" style="width:0%"></div>
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-2 mt-1">
                        <i class="bi bi-exclamation-circle text-warning"></i>
                        <span class="small text-muted" id="htWorkloadAlerts">— workload alerts</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- 2c: Assessment Pipeline -->
        <div class="col-lg-3 col-md-6">
            <div class="card dash-card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="bi bi-clipboard-data me-2 text-info"></i>Assessment Pipeline</h6>
                    <a href="home.php?route=assessments_exams" class="btn btn-sm btn-outline-info" data-route="assessments_exams">View</a>
                </div>
                <div class="card-body d-flex flex-column gap-2">
                    <div class="dash-kpi-widget">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="dash-kpi-title">Completed</span>
                            <span class="dash-kpi-value text-success" id="htAssessCompleted">—%</span>
                        </div>
                        <div class="progress mt-1">
                            <div class="progress-bar bg-success" id="htAssessCompletedBar" role="progressbar" style="width:0%"></div>
                        </div>
                    </div>
                    <div class="d-flex gap-2">
                        <div class="dash-kpi-widget flex-fill text-center">
                            <div class="dash-kpi-title">Pending</div>
                            <div class="dash-kpi-value text-warning" id="htAssessPending">—%</div>
                        </div>
                        <div class="dash-kpi-widget flex-fill text-center">
                            <div class="dash-kpi-title">Overdue</div>
                            <div class="dash-kpi-value text-danger" id="htAssessOverdue">—%</div>
                        </div>
                    </div>
                    <div class="dash-kpi-widget">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="dash-kpi-title">Unmoderated</span>
                            <span class="dash-kpi-value text-secondary" id="htAssessUnmoderated">—</span>
                        </div>
                        <div class="progress mt-1">
                            <div class="progress-bar bg-secondary" id="htAssessUnmodBar" role="progressbar" style="width:0%"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 2d: Early Warning Learners -->
        <div class="col-lg-3 col-md-6">
            <div class="card dash-card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="bi bi-exclamation-triangle me-2 text-danger"></i>Early Warning Learners</h6>
                    <a href="home.php?route=students_overview" class="btn btn-sm btn-outline-danger" data-route="students_overview">At-Risk</a>
                </div>
                <div class="card-body d-flex flex-column gap-2">
                    <div class="text-center mb-2">
                        <div class="display-6 fw-bold text-danger" id="htAtRiskCount">—</div>
                        <div class="small text-muted">learners flagged at risk</div>
                    </div>
                    <div class="dash-kpi-widget d-flex justify-content-between align-items-center">
                        <span class="dash-kpi-title"><i class="bi bi-calendar-x text-warning me-1"></i>Chronic Absence</span>
                        <span class="dash-kpi-value" id="htRiskAbsence">—</span>
                    </div>
                    <div class="dash-kpi-widget d-flex justify-content-between align-items-center">
                        <span class="dash-kpi-title"><i class="bi bi-book text-danger me-1"></i>Failing Grades</span>
                        <span class="dash-kpi-value" id="htRiskFailing">—</span>
                    </div>
                    <div class="dash-kpi-widget d-flex justify-content-between align-items-center">
                        <span class="dash-kpi-title"><i class="bi bi-person-x text-info me-1"></i>Behavioural</span>
                        <span class="dash-kpi-value" id="htRiskBehavioural">—</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ======================================================================
         ROW 3 — FINANCIAL HEALTH (4 panels)
         ====================================================================== -->
    <div class="row g-3 mb-4">
        <!-- 3a: Fee Collection -->
        <div class="col-lg-3 col-md-6">
            <div class="card dash-card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="bi bi-cash-coin me-2 text-success"></i>Fee Collection</h6>
                    <a href="home.php?route=fees_overview" class="btn btn-sm btn-outline-success" data-route="fees_overview">Fees</a>
                </div>
                <div class="card-body">
                    <div class="dash-kpi-widget mb-2">
                        <div class="d-flex justify-content-between align-items-baseline">
                            <span class="dash-kpi-title">Collected</span>
                            <span class="dash-kpi-value" id="htFeesCollected">KES —</span>
                        </div>
                        <div class="small text-muted mt-1">of <span id="htFeesTarget">KES —</span> target</div>
                        <div class="progress mt-2">
                            <div class="progress-bar bg-success" id="htFeesProgress" role="progressbar" style="width:0%"></div>
                        </div>
                        <div class="d-flex align-items-center gap-2 mt-2">
                            <span class="small text-muted">Rate:</span>
                            <span class="fw-bold" id="htFeeRateValue">—%</span>
                            <span class="dash-compare-up" id="htFeeRateTrend"><i class="bi bi-arrow-up"></i> —</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 3b: Outstanding by Age -->
        <div class="col-lg-3 col-md-6">
            <div class="card dash-card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="bi bi-hourglass-split me-2 text-warning"></i>Outstanding by Age</h6>
                </div>
                <div class="card-body">
                    <div class="dash-kpi-widget mb-2">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="small fw-semibold">0 – 30 days</span>
                            <span class="fw-bold" id="htAge030">KES —</span>
                        </div>
                        <div class="progress" style="height:6px">
                            <div class="progress-bar bg-success" id="htAge030Bar" role="progressbar" style="width:0%"></div>
                        </div>
                    </div>
                    <div class="dash-kpi-widget mb-2">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="small fw-semibold">31 – 60 days</span>
                            <span class="fw-bold" id="htAge3160">KES —</span>
                        </div>
                        <div class="progress" style="height:6px">
                            <div class="progress-bar bg-warning" id="htAge3160Bar" role="progressbar" style="width:0%"></div>
                        </div>
                    </div>
                    <div class="dash-kpi-widget mb-2">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="small fw-semibold">61 – 90 days</span>
                            <span class="fw-bold" id="htAge6190">KES —</span>
                        </div>
                        <div class="progress" style="height:6px">
                            <div class="progress-bar bg-orange" id="htAge6190Bar" role="progressbar" style="width:0%"></div>
                        </div>
                    </div>
                    <div class="dash-kpi-widget">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="small fw-semibold">90+ days</span>
                            <span class="fw-bold text-danger" id="htAge90">KES —</span>
                        </div>
                        <div class="progress" style="height:6px">
                            <div class="progress-bar bg-danger" id="htAge90Bar" role="progressbar" style="width:0%"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 3c: Revenue vs Budget -->
        <div class="col-lg-3 col-md-6">
            <div class="card dash-card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="bi bi-pie-chart me-2 text-indigo"></i>Revenue vs Budget</h6>
                    <a href="home.php?route=finance_reports" class="btn btn-sm btn-outline-primary" data-route="finance_reports">Reports</a>
                </div>
                <div class="card-body">
                    <div class="dash-kpi-widget mb-2">
                        <div class="d-flex justify-content-between align-items-baseline">
                            <span class="dash-kpi-title">Total Revenue</span>
                            <span class="dash-kpi-value" id="htTotalRevenue">KES —</span>
                        </div>
                        <div class="small text-muted mt-1">of KES <span id="htApprovedBudget">—</span> approved budget</div>
                        <div class="progress mt-2">
                            <div class="progress-bar bg-primary" id="htBudgetBar" role="progressbar" style="width:0%"></div>
                        </div>
                        <div class="d-flex justify-content-between mt-2">
                            <span class="small text-muted">Utilisation</span>
                            <span class="fw-bold" id="htBudgetUtil">—%</span>
                        </div>
                    </div>
                    <div class="d-flex gap-2 mt-1">
                        <div class="dash-kpi-widget flex-fill text-center">
                            <div class="dash-kpi-title">Capital</div>
                            <div class="fw-bold small" id="htCapitalExpend">KES —</div>
                        </div>
                        <div class="dash-kpi-widget flex-fill text-center">
                            <div class="dash-kpi-title">Recurrent</div>
                            <div class="fw-bold small" id="htRecurrentExpend">KES —</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 3d: Payroll Status -->
        <div class="col-lg-3 col-md-6">
            <div class="card dash-card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="bi bi-wallet2 me-2 text-purple"></i>Payroll Status</h6>
                </div>
                <div class="card-body d-flex flex-column gap-2">
                    <div class="dash-kpi-widget d-flex justify-content-between align-items-center">
                        <span class="dash-kpi-title">Gross Payroll</span>
                        <span class="dash-kpi-value" id="htGrossPayroll">KES —</span>
                    </div>
                    <div class="dash-kpi-widget d-flex justify-content-between align-items-center">
                        <span class="dash-kpi-title">Net Payroll</span>
                        <span class="dash-kpi-value" id="htNetPayroll">KES —</span>
                    </div>
                    <div class="dash-kpi-widget d-flex justify-content-between align-items-center">
                        <span class="dash-kpi-title">Pending Payslips</span>
                        <span class="fw-bold" id="htPendingPayslips">—</span>
                    </div>
                    <div class="dash-kpi-widget d-flex justify-content-between align-items-center">
                        <span class="dash-kpi-title">Staff on Payroll</span>
                        <span class="fw-bold" id="htPayrollStaffCount">—</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ======================================================================
         ROW 4 — OPERATIONAL ALERTS (4 panels)
         ====================================================================== -->
    <div class="row g-3 mb-4">
        <!-- 4a: Discipline -->
        <div class="col-lg-3 col-md-6">
            <div class="card dash-card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="bi bi-exclamation-octagon me-2 text-danger"></i>Discipline</h6>
                    <a href="home.php?route=discipline_cases" class="btn btn-sm btn-outline-danger" data-route="discipline_cases">Cases</a>
                </div>
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div>
                            <div class="display-6 fw-bold text-danger" id="htDiscOpen">—</div>
                            <div class="small text-muted">open cases</div>
                        </div>
                        <div class="text-end">
                            <span class="badge bg-danger" id="htDiscUrgent">— urgent</span>
                            <div class="mt-1">
                                <span class="dash-compare-down" id="htDiscTrend"><i class="bi bi-arrow-down"></i> —</span>
                                <span class="small text-muted ms-1">vs last month</span>
                            </div>
                        </div>
                    </div>
                    <div class="dashboard-table-wrap" style="max-height:120px">
                        <table class="table table-sm table-hover mb-0">
                            <thead class="table-light">
                                <tr><th>Student</th><th>Issue</th><th>Days</th></tr>
                            </thead>
                            <tbody id="htDisciplineTable">
                                <tr><td colspan="3" class="text-center text-muted py-2">—</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- 4b: Transport -->
        <div class="col-lg-3 col-md-6">
            <div class="card dash-card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="bi bi-bus-front me-2 text-primary"></i>Transport</h6>
                    <a href="home.php?route=transport_overview" class="btn btn-sm btn-outline-primary" data-route="transport_overview">Routes</a>
                </div>
                <div class="card-body d-flex flex-column gap-2">
                    <div class="dash-kpi-widget d-flex justify-content-between align-items-center">
                        <span class="dash-kpi-title">On-time Rate</span>
                        <span class="fw-bold" id="htTransportOnTime">—%</span>
                    </div>
                    <div class="dash-kpi-widget d-flex justify-content-between align-items-center">
                        <span class="dash-kpi-title">Incidents (30d)</span>
                        <span class="fw-bold" id="htTransportIncidents">—</span>
                    </div>
                    <div class="dash-kpi-widget d-flex justify-content-between align-items-center">
                        <span class="dash-kpi-title">Vehicles Active</span>
                        <span class="fw-bold" id="htTransportActive">— / —</span>
                    </div>
                    <div class="dash-kpi-widget d-flex justify-content-between align-items-center">
                        <span class="dash-kpi-title">Fuel Budget Used</span>
                        <span class="fw-bold" id="htTransportFuel">—%</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- 4c: Catering -->
        <div class="col-lg-3 col-md-6">
            <div class="card dash-card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="bi bi-cup-hot me-2 text-warning"></i>Catering</h6>
                    <a href="home.php?route=catering_overview" class="btn btn-sm btn-outline-warning" data-route="catering_overview">Kitchen</a>
                </div>
                <div class="card-body d-flex flex-column gap-2">
                    <div class="dash-kpi-widget d-flex justify-content-between align-items-center">
                        <span class="dash-kpi-title">Meals Served Today</span>
                        <span class="fw-bold" id="htCaterMeals">—</span>
                    </div>
                    <div class="dash-kpi-widget d-flex justify-content-between align-items-center">
                        <span class="dash-kpi-title">Low Stock Alerts</span>
                        <span class="fw-bold text-warning" id="htCaterLowStock">—</span>
                    </div>
                    <div class="dash-kpi-widget d-flex justify-content-between align-items-center">
                        <span class="dash-kpi-title">Waste Rate</span>
                        <span class="fw-bold" id="htCaterWaste">—%</span>
                    </div>
                    <div class="dash-kpi-widget d-flex justify-content-between align-items-center">
                        <span class="dash-kpi-title">Cost per Meal</span>
                        <span class="fw-bold" id="htCaterCostMeal">KES —</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- 4d: Health / Welfare -->
        <div class="col-lg-3 col-md-6">
            <div class="card dash-card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="bi bi-heart-pulse me-2 text-pink"></i>Health &amp; Welfare</h6>
                    <a href="home.php?route=health_records" class="btn btn-sm btn-outline-pink" data-route="health_records">Records</a>
                </div>
                <div class="card-body d-flex flex-column gap-2">
                    <div class="dash-kpi-widget d-flex justify-content-between align-items-center">
                        <span class="dash-kpi-title">Incidents (30d)</span>
                        <span class="fw-bold" id="htHealthIncidents">—</span>
                    </div>
                    <div class="dash-kpi-widget d-flex justify-content-between align-items-center">
                        <span class="dash-kpi-title">Referrals Pending</span>
                        <span class="fw-bold text-warning" id="htHealthReferrals">—</span>
                    </div>
                    <div class="dash-kpi-widget d-flex justify-content-between align-items-center">
                        <span class="dash-kpi-title">Medication Due</span>
                        <span class="fw-bold text-danger" id="htHealthMedication">—</span>
                    </div>
                    <div class="dash-kpi-widget d-flex justify-content-between align-items-center">
                        <span class="dash-kpi-title">Nurse Visits (wk)</span>
                        <span class="fw-bold" id="htHealthVisits">—</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4 edu-center-live-queues">
        <div class="col-lg-7">
            <div class="card dash-card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="bi bi-person-plus me-2 text-primary"></i>Admissions Queue</h6>
                    <a class="btn btn-sm btn-outline-primary" href="home.php?route=admissions_academic_applications" data-route="admissions_academic_applications">Open workflow</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead><tr><th>Applicant</th><th>Applied Grade</th><th>Submitted</th><th>Status</th></tr></thead>
                        <tbody id="admissionsTableBody"><tr><td colspan="4" class="text-center text-muted py-3">Loading admissions…</td></tr></tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card dash-card h-100">
                <div class="card-header"><h6 class="mb-0"><i class="bi bi-shield-exclamation me-2 text-danger"></i>Discipline Attention</h6></div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead><tr><th>Learner</th><th>Class</th><th>Issue</th><th>Severity</th></tr></thead>
                        <tbody id="disciplineTableBody"><tr><td colspan="4" class="text-center text-muted py-3">Loading cases…</td></tr></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- ======================================================================
         ROW 5 — PRIORITY ACTIONS (actionable ranked list)
         ====================================================================== -->
    <div class="row g-3 mb-4">
        <div class="col-12">
            <div class="card dash-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="bi bi-lightning me-2 text-warning"></i>Priority Actions</h6>
                    <span class="badge bg-secondary" id="htActionCount">— items</span>
                </div>
                <div class="card-body p-0">
                    <div class="dashboard-table-wrap" style="max-height:300px">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width:36px">#</th>
                                    <th>Action Required</th>
                                    <th style="width:100px">Severity</th>
                                    <th style="width:120px">Domain</th>
                                    <th style="width:110px">Due / Age</th>
                                    <th style="width:90px">Action</th>
                                </tr>
                            </thead>
                            <tbody id="htPriorityActions">
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-3">
                                        <div class="spinner-border spinner-border-sm me-2" role="status"></div>Loading actions...
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ======================================================================
         ROW 6 — UPCOMING EVENTS & QUICK ACTIONS
         ====================================================================== -->
    <div class="row g-3 mb-4">
        <!-- 6a: This Week's Events -->
        <div class="col-lg-7">
            <div class="card dash-card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="bi bi-calendar-event me-2 text-info"></i>This Week's Events</h6>
                    <a href="home.php?route=academic_calendar" class="btn btn-sm btn-outline-info" data-route="academic_calendar">Calendar</a>
                </div>
                <div class="card-body">
                    <div class="dash-timeline" id="htWeekEvents">
                        <div class="dash-timeline-item">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="fw-semibold small">Loading events...</div>
                                    <div class="text-muted" style="font-size:0.75rem">—</div>
                                </div>
                                <span class="badge bg-secondary" style="font-size:0.65rem">—</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 6b: Quick Actions -->
        <div class="col-lg-5">
            <div class="card dash-card h-100">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-grid me-2 text-success"></i>Quick Actions</h6>
                </div>
                <div class="card-body">
                    <div class="dashboard-action-grid">
                        <a href="home.php?route=students_overview" class="dash-quick-link" data-route="students_overview">
                            <i class="bi bi-people ql-icon bg-indigo text-white"></i>
                            <span>All Students</span><i class="bi bi-chevron-right ql-arrow"></i>
                        </a>
                        <a href="home.php?route=manage_staff" class="dash-quick-link" data-route="manage_staff">
                            <i class="bi bi-person-badge ql-icon bg-success text-white"></i>
                            <span>All Staff</span><i class="bi bi-chevron-right ql-arrow"></i>
                        </a>
                        <a href="home.php?route=manage_timetable" class="dash-quick-link" data-route="manage_timetable">
                            <i class="bi bi-calendar-week ql-icon bg-primary text-white"></i>
                            <span>Timetable</span><i class="bi bi-chevron-right ql-arrow"></i>
                        </a>
                        <a href="home.php?route=assessments_exams" class="dash-quick-link" data-route="assessments_exams">
                            <i class="bi bi-clipboard-check ql-icon bg-warning text-white"></i>
                            <span>Assessments</span><i class="bi bi-chevron-right ql-arrow"></i>
                        </a>
                        <a href="home.php?route=fees_overview" class="dash-quick-link" data-route="fees_overview">
                            <i class="bi bi-cash-stack ql-icon bg-cyan text-white"></i>
                            <span>Fees &amp; Finance</span><i class="bi bi-chevron-right ql-arrow"></i>
                        </a>
                        <a href="home.php?route=manage_students_admissions" class="dash-quick-link" data-route="manage_students_admissions">
                            <i class="bi bi-person-plus ql-icon bg-amber text-white"></i>
                            <span>Admissions</span><i class="bi bi-chevron-right ql-arrow"></i>
                        </a>
                        <a href="home.php?route=academic_reports" class="dash-quick-link" data-route="academic_reports">
                            <i class="bi bi-file-earmark-bar-graph ql-icon bg-danger text-white"></i>
                            <span>Academic Reports</span><i class="bi bi-chevron-right ql-arrow"></i>
                        </a>
                        <a href="home.php?route=manage_communications" class="dash-quick-link" data-route="manage_communications">
                            <i class="bi bi-chat-dots ql-icon bg-purple text-white"></i>
                            <span>Communications</span><i class="bi bi-chevron-right ql-arrow"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<?php asset_script($appBase, 'js/components/academic_events.js'); ?>
<?php asset_script($appBase, 'js/dashboards/headteacher_dashboard.js'); ?>
