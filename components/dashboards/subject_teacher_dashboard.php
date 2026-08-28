<?php
/**
 * Subject Teacher Dashboard — Teaching load, subject performance, strand mastery.
 * Role: Subject Teacher
 */
$rootId = 'subjectTeacherDashboard';
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

<div class="container-fluid py-4 role-dashboard dashboard-surface dashboard-learning-area" id="<?= $escape($rootId) ?>">
    <?php require __DIR__ . '/partials/period_selector.php'; ?>

    <!-- Meta Bar -->
    <div class="dash-meta-bar mb-4 d-flex align-items-center justify-content-end gap-3 flex-wrap">
        <span class="dash-badge bg-info" id="<?= $escape($rootId) ?>SubjectBadge">—</span>
        <span class="dash-badge bg-success" id="<?= $escape($rootId) ?>TermBadge">—</span>
        <span class="small text-muted">Updated: <span id="<?= $escape($rootId) ?>LastUpdated">—</span></span>
        <button class="btn btn-sm btn-outline-success" id="<?= $escape($rootId) ?>Refresh">
            <i class="bi bi-arrow-clockwise me-1"></i>Refresh
        </button>
    </div>

    <!-- ROW 1: MY TEACHING LOAD — 6 KPIs -->
    <div class="row g-3 mb-3">
        <div class="col-xl-4 col-md-6">
            <div class="dash-stat dsc-blue">
                <i class="bi bi-building dash-stat-icon"></i>
                <div class="dash-stat-value" id="stClasses">—</div>
                <div class="dash-stat-label">Assigned Classes</div>
                <div class="dash-stat-sub" id="stClassesSub">across all grades</div>
            </div>
        </div>
        <div class="col-xl-4 col-md-6">
            <div class="dash-stat dsc-indigo">
                <i class="bi bi-people dash-stat-icon"></i>
                <div class="dash-stat-value" id="stStudents">—</div>
                <div class="dash-stat-label">Total Students</div>
                <div class="dash-stat-sub" id="stStudentsSub">combined enrolment</div>
            </div>
        </div>
        <div class="col-xl-4 col-md-6">
            <div class="dash-stat dsc-orange">
                <i class="bi bi-clipboard2-data dash-stat-icon"></i>
                <div class="dash-stat-value" id="stAssessmentsDue">—</div>
                <div class="dash-stat-label">Assessments Due</div>
                <div class="dash-stat-sub" id="stAssessmentsDueSub">this week</div>
            </div>
        </div>
    </div>
    <div class="row g-3 mb-4">
        <div class="col-xl-4 col-md-6">
            <div class="dash-stat dsc-green">
                <i class="bi bi-check2-circle dash-stat-icon"></i>
                <div class="dash-stat-value" id="stGradingProgress">—</div>
                <div class="dash-stat-label">Grading Progress</div>
                <div class="dash-stat-sub" id="stGradingProgressSub">% of pending marks</div>
            </div>
        </div>
        <div class="col-xl-4 col-md-6">
            <div class="dash-stat dsc-cyan">
                <i class="bi bi-journal-text dash-stat-icon"></i>
                <div class="dash-stat-value" id="stLessonPlans">—</div>
                <div class="dash-stat-label">Lesson Plans Ready</div>
                <div class="dash-stat-sub" id="stLessonPlansSub">for this week</div>
            </div>
        </div>
        <div class="col-xl-4 col-md-6">
            <div class="dash-stat dsc-teal">
                <i class="bi bi-speedometer dash-stat-icon"></i>
                <div class="dash-stat-value" id="stWorkload">—</div>
                <div class="dash-stat-label">Workload Status</div>
                <div class="dash-stat-sub" id="stWorkloadSub">hrs / weekly target</div>
            </div>
        </div>
    </div>

    <!-- ROW 2: SUBJECT PERFORMANCE BY CLASS — 2 panels -->
    <div class="row g-3 mb-4">
        <div class="col-lg-7">
            <div class="card dash-card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="bi bi-bar-chart me-2 text-primary"></i>Performance by Class</h6>
                    <small class="text-muted">Average score per class this term</small>
                </div>
                <div class="card-body">
                    <div class="dash-chart-wrap-lg"><canvas id="stPerformanceChart"></canvas></div>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card dash-card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="bi bi-graph-up me-2 text-success"></i>Grading Progress</h6>
                    <small class="text-muted">By class</small>
                </div>
                <div class="card-body">
                    <div class="dash-chart-wrap-lg"><canvas id="stGradingChart"></canvas></div>
                </div>
            </div>
        </div>
    </div>

    <!-- ROW 3: STRAND MASTERY — full-width -->
    <div class="row g-3 mb-4">
        <div class="col-12">
            <div class="card dash-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="bi bi-diagram-3 me-2 text-indigo"></i>Strand Mastery Breakdown</h6>
                    <div class="d-flex gap-2 align-items-center">
                        <select class="form-select form-select-sm" id="stStrandLearningArea" style="width:auto">
                            <option value="">All Learning Areas</option>
                        </select>
                        <select class="form-select form-select-sm" id="stStrandClass" style="width:auto">
                            <option value="">All Classes</option>
                        </select>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col">Learning Area</th>
                                    <th scope="col">Strand</th>
                                    <th scope="col">Sub-strand</th>
                                    <th scope="col" class="text-center">Meeting+</th>
                                    <th scope="col" class="text-center">Approaching</th>
                                    <th scope="col" class="text-center">Below</th>
                                    <th scope="col" class="text-center">Mastery %</th>
                                </tr>
                            </thead>
                            <tbody id="stStrandBody">
                                <tr><td colspan="7" class="text-center text-muted py-4">
                                    <div class="spinner-border spinner-border-sm me-2" role="status"></div>Loading strand data...
                                </td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ROW 4: ASSESSMENT PIPELINE — 2 panels -->
    <div class="row g-3 mb-4">
        <div class="col-lg-6">
            <div class="card dash-card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="bi bi-clipboard2-check me-2 text-warning"></i>Pending Assessments</h6>
                    <button class="btn btn-sm btn-outline-warning" data-route="formative_assessments">View All</button>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col">Assessment</th>
                                    <th scope="col">Class</th>
                                    <th scope="col">Due</th>
                                    <th scope="col">Status</th>
                                </tr>
                            </thead>
                            <tbody id="stPendingBody">
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
                    <h6 class="mb-0"><i class="bi bi-calendar-event me-2 text-info"></i>Exam Schedule</h6>
                    <button class="btn btn-sm btn-outline-info" data-route="subject_exam_schedule">View All</button>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col">Date</th>
                                    <th scope="col">Class</th>
                                    <th scope="col">Type</th>
                                    <th scope="col">Status</th>
                                </tr>
                            </thead>
                            <tbody id="stExamBody">
                                <tr><td colspan="4" class="text-center text-muted py-4">Loading...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ROW 5: STUDENT GROWTH — 2 panels -->
    <div class="row g-3 mb-4">
        <div class="col-lg-6">
            <div class="card dash-card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="bi bi-graph-up-arrow me-2 text-success"></i>Students Improving</h6>
                    <span class="badge bg-success" id="stImprovingCount">0</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col">Student</th>
                                    <th scope="col">Class</th>
                                    <th scope="col">Previous</th>
                                    <th scope="col">Current</th>
                                    <th scope="col">Change</th>
                                </tr>
                            </thead>
                            <tbody id="stImprovingBody">
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
                    <h6 class="mb-0"><i class="bi bi-graph-down-arrow me-2 text-danger"></i>Students Declining</h6>
                    <span class="badge bg-danger" id="stDecliningCount">0</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col">Student</th>
                                    <th scope="col">Class</th>
                                    <th scope="col">Previous</th>
                                    <th scope="col">Current</th>
                                    <th scope="col">Change</th>
                                </tr>
                            </thead>
                            <tbody id="stDecliningBody">
                                <tr><td colspan="5" class="text-center text-muted py-4">Loading...</td></tr>
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
                        <div class="col-md-4 col-lg">
                            <a href="#" data-route="timetable" class="dash-quick-link">
                                <i class="bi bi-calendar3 ql-icon bg-primary text-white"></i>
                                <span>My Timetable</span><i class="bi bi-chevron-right ql-arrow"></i>
                            </a>
                        </div>
                        <div class="col-md-4 col-lg">
                            <a href="#" data-route="subject_grade_entry" class="dash-quick-link">
                                <i class="bi bi-pencil-square ql-icon bg-success text-white"></i>
                                <span>Enter Marks</span><i class="bi bi-chevron-right ql-arrow"></i>
                            </a>
                        </div>
                        <div class="col-md-4 col-lg">
                            <a href="#" data-route="manage_lesson_plans" class="dash-quick-link">
                                <i class="bi bi-journal-text ql-icon bg-info text-white"></i>
                                <span>Lesson Plans</span><i class="bi bi-chevron-right ql-arrow"></i>
                            </a>
                        </div>
                        <div class="col-md-4 col-lg">
                            <a href="#" data-route="formative_assessments" class="dash-quick-link">
                                <i class="bi bi-clipboard2-data ql-icon bg-warning text-white"></i>
                                <span>Assessments</span><i class="bi bi-chevron-right ql-arrow"></i>
                            </a>
                        </div>
                        <div class="col-md-4 col-lg">
                            <a href="#" data-route="subject_exam_schedule" class="dash-quick-link">
                                <i class="bi bi-calendar-event ql-icon bg-danger text-white"></i>
                                <span>Exam Schedule</span><i class="bi bi-chevron-right ql-arrow"></i>
                            </a>
                        </div>
                        <div class="col-md-4 col-lg">
                            <a href="#" data-route="academic_reports" class="dash-quick-link">
                                <i class="bi bi-file-earmark-bar-graph ql-icon bg-secondary text-white"></i>
                                <span>Reports</span><i class="bi bi-chevron-right ql-arrow"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Lesson Plan Readiness Summary -->
    <div class="row g-3 mb-4">
        <div class="col-lg-6">
            <div class="card dash-card h-100">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-journal-check me-2 text-cyan"></i>Lesson Plan Readiness</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col">Class</th>
                                    <th scope="col">Total Lessons</th>
                                    <th scope="col">Plans Ready</th>
                                    <th scope="col">Coverage</th>
                                </tr>
                            </thead>
                            <tbody id="stLessonPlanBody">
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
                    <h6 class="mb-0"><i class="bi bi-people me-2 text-purple"></i>Class Composition</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col">Class</th>
                                    <th scope="col">Students</th>
                                    <th scope="col">Boys</th>
                                    <th scope="col">Girls</th>
                                    <th scope="col">Avg Score</th>
                                </tr>
                            </thead>
                            <tbody id="stClassCompBody">
                                <tr><td colspan="5" class="text-center text-muted py-4">Loading...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<?php asset_script($appBase, 'js/dashboards/dashboard_base_controller.js'); ?>
<?php asset_script($appBase, 'js/dashboards/subject_teacher_dashboard.js'); ?>
