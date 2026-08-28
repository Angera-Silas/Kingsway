<?php
/**
 * Intern / Student Teacher Dashboard — Development, competencies, mentor feedback.
 * Role: Intern / Student Teacher
 */
$rootId = 'internDashboard';
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

<div class="container-fluid py-4 role-dashboard dashboard-surface dashboard-practicum" id="<?= $escape($rootId) ?>">
    <?php require __DIR__ . '/partials/period_selector.php'; ?>

    <!-- Meta Bar -->
    <div class="dash-meta-bar mb-4 d-flex align-items-center justify-content-end gap-3 flex-wrap">
        <span class="dash-badge bg-purple" id="<?= $escape($rootId) ?>RoleBadge">Intern</span>
        <span class="dash-badge bg-info" id="<?= $escape($rootId) ?>TermBadge">—</span>
        <span class="small text-muted">Updated: <span id="<?= $escape($rootId) ?>LastUpdated">—</span></span>
        <button class="btn btn-sm btn-outline-success" id="<?= $escape($rootId) ?>Refresh">
            <i class="bi bi-arrow-clockwise me-1"></i>Refresh
        </button>
    </div>

    <!-- ROW 1: MY DEVELOPMENT — 6 KPIs -->
    <div class="row g-3 mb-3">
        <div class="col-xl-4 col-md-6">
            <div class="dash-stat dsc-blue">
                <i class="bi bi-building dash-stat-icon"></i>
                <div class="dash-stat-value" id="internClasses">—</div>
                <div class="dash-stat-label">Assigned Classes</div>
                <div class="dash-stat-sub" id="internClassesSub">teaching load</div>
            </div>
        </div>
        <div class="col-xl-4 col-md-6">
            <div class="dash-stat dsc-indigo">
                <i class="bi bi-people dash-stat-icon"></i>
                <div class="dash-stat-value" id="internStudents">—</div>
                <div class="dash-stat-label">Total Students</div>
                <div class="dash-stat-sub" id="internStudentsSub">combined class size</div>
            </div>
        </div>
        <div class="col-xl-4 col-md-6">
            <div class="dash-stat dsc-green">
                <i class="bi bi-trophy dash-stat-icon"></i>
                <div class="dash-stat-value" id="internCompetency">—</div>
                <div class="dash-stat-label">Competency Score</div>
                <div class="dash-stat-sub" id="internCompetencySub">% overall rating</div>
            </div>
        </div>
    </div>
    <div class="row g-3 mb-4">
        <div class="col-xl-4 col-md-6">
            <div class="dash-stat dsc-cyan">
                <i class="bi bi-easel dash-stat-icon"></i>
                <div class="dash-stat-value" id="internLessons">—</div>
                <div class="dash-stat-label">Lessons Delivered</div>
                <div class="dash-stat-sub" id="internLessonsSub">this term</div>
            </div>
        </div>
        <div class="col-xl-4 col-md-6">
            <div class="dash-stat dsc-purple">
                <i class="bi bi-eye dash-stat-icon"></i>
                <div class="dash-stat-value" id="internObservations">—</div>
                <div class="dash-stat-label">Observations Done</div>
                <div class="dash-stat-sub" id="internObservationsSub">of expected</div>
            </div>
        </div>
        <div class="col-xl-4 col-md-6">
            <div class="dash-stat dsc-amber">
                <i class="bi bi-person-check dash-stat-icon"></i>
                <div class="dash-stat-value" id="internMentorMeetings">—</div>
                <div class="dash-stat-label">Mentor Meetings</div>
                <div class="dash-stat-sub" id="internMentorMeetingsSub">completed</div>
            </div>
        </div>
    </div>

    <!-- ROW 2: COMPETENCY PROGRESS — 2 panels -->
    <div class="row g-3 mb-4">
        <div class="col-lg-6">
            <div class="card dash-card h-100">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-radar me-2 text-purple"></i>Competency Radar</h6>
                    <small class="text-muted">Pedagogy, Classroom, Assessment, Communication</small>
                </div>
                <div class="card-body d-flex align-items-center justify-content-center">
                    <div class="dash-chart-wrap-lg"><canvas id="internRadarChart"></canvas></div>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card dash-card h-100">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-bar-chart me-2 text-success"></i>Progress Milestones</h6>
                    <small class="text-muted">By competency area</small>
                </div>
                <div class="card-body">
                    <div class="dash-chart-wrap-lg"><canvas id="internMilestoneChart"></canvas></div>
                </div>
            </div>
        </div>
    </div>

    <!-- ROW 3: TODAY'S SCHEDULE — full-width timeline -->
    <div class="row g-3 mb-4">
        <div class="col-12">
            <div class="card dash-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="bi bi-calendar-day me-2 text-info"></i>Today's Teaching Schedule</h6>
                    <button class="btn btn-sm btn-outline-info" data-route="timetable">Full Timetable</button>
                </div>
                <div class="card-body">
                    <div class="dash-timeline" id="internTodayTimeline">
                        <div class="text-center text-muted py-3">
                            <div class="spinner-border spinner-border-sm me-2" role="status"></div>Loading today's schedule...
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ROW 4: FEEDBACK & OBSERVATIONS — 2 panels -->
    <div class="row g-3 mb-4">
        <div class="col-lg-6">
            <div class="card dash-card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="bi bi-chat-heart me-2 text-primary"></i>Recent Mentor Feedback</h6>
                    <button class="btn btn-sm btn-outline-primary" data-route="teacher_performance_reviews">All Feedback</button>
                </div>
                <div class="card-body">
                    <div class="dash-timeline" id="internFeedbackTimeline">
                        <div class="text-center text-muted py-3">Loading feedback...</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card dash-card h-100">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-calendar-check me-2 text-warning"></i>Upcoming Observations</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col">Date</th>
                                    <th scope="col">Observer</th>
                                    <th scope="col">Class</th>
                                    <th scope="col">Type</th>
                                </tr>
                            </thead>
                            <tbody id="internObservationsTable">
                                <tr><td colspan="4" class="text-center text-muted py-4">Loading...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ROW 5: MY CLASSES — 2 panels -->
    <div class="row g-3 mb-4">
        <div class="col-lg-6">
            <div class="card dash-card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="bi bi-building me-2 text-cyan"></i>My Classes</h6>
                    <button class="btn btn-sm btn-outline-secondary" data-route="timetable">Details</button>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col">Class</th>
                                    <th scope="col">Students</th>
                                    <th scope="col">Sessions/Week</th>
                                    <th scope="col">Mentor</th>
                                </tr>
                            </thead>
                            <tbody id="internClassListBody">
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
                    <h6 class="mb-0"><i class="bi bi-calendar2-week me-2 text-indigo"></i>Mentor Meeting Schedule</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col">Date</th>
                                    <th scope="col">Mentor</th>
                                    <th scope="col">Focus Area</th>
                                    <th scope="col">Status</th>
                                </tr>
                            </thead>
                            <tbody id="internMentorScheduleBody">
                                <tr><td colspan="4" class="text-center text-muted py-4">Loading...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Development Competency Breakdown -->
    <div class="row g-3 mb-4">
        <div class="col-12">
            <div class="card dash-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="bi bi-clipboard-data me-2 text-purple"></i>Competency Scores Detail</h6>
                    <small class="text-muted">Last observation rating</small>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col">Competency Area</th>
                                    <th scope="col" class="text-center">Score</th>
                                    <th scope="col" class="text-center">Target</th>
                                    <th scope="col" class="text-center">Gap</th>
                                    <th scope="col">Status</th>
                                    <th scope="col">Last Observed</th>
                                </tr>
                            </thead>
                            <tbody id="internCompetencyDetailBody">
                                <tr><td colspan="6" class="text-center text-muted py-4">Loading competency details...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Development Plan Summary -->
    <div class="row g-3 mb-4">
        <div class="col-lg-6">
            <div class="card dash-card h-100">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-list-check me-2 text-green"></i>Development Plan Tasks</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col">Task</th>
                                    <th scope="col">Area</th>
                                    <th scope="col">Due</th>
                                    <th scope="col">Status</th>
                                </tr>
                            </thead>
                            <tbody id="internDevPlanBody">
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
                    <h6 class="mb-0"><i class="bi bi-journal-richtext me-2 text-info"></i>Lesson Plan Submissions</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col">Class</th>
                                    <th scope="col">Subject</th>
                                    <th scope="col">Submitted</th>
                                    <th scope="col">Feedback</th>
                                </tr>
                            </thead>
                            <tbody id="internLessonPlanSubsBody">
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
                            <a href="#" data-route="timetable" class="dash-quick-link">
                                <i class="bi bi-calendar3 ql-icon bg-primary text-white"></i>
                                <span>My Timetable</span><i class="bi bi-chevron-right ql-arrow"></i>
                            </a>
                        </div>
                        <div class="col-md-3 col-lg">
                            <a href="#" data-route="manage_lesson_plans" class="dash-quick-link">
                                <i class="bi bi-journal-text ql-icon bg-success text-white"></i>
                                <span>Lesson Plans</span><i class="bi bi-chevron-right ql-arrow"></i>
                            </a>
                        </div>
                        <div class="col-md-3 col-lg">
                            <a href="#" data-route="view_teaching_materials" class="dash-quick-link">
                                <i class="bi bi-folder2-open ql-icon bg-info text-white"></i>
                                <span>Resources</span><i class="bi bi-chevron-right ql-arrow"></i>
                            </a>
                        </div>
                        <div class="col-md-3 col-lg">
                            <a href="#" data-route="intern_student_teacher_dashboard" class="dash-quick-link">
                                <i class="bi bi-person ql-icon bg-secondary text-white"></i>
                                <span>My Profile</span><i class="bi bi-chevron-right ql-arrow"></i>
                            </a>
                        </div>
                        <div class="col-md-3 col-lg">
                            <a href="#" data-route="teacher_performance_reviews" class="dash-quick-link">
                                <i class="bi bi-clipboard-data ql-icon bg-danger text-white"></i>
                                <span>My Reviews</span><i class="bi bi-chevron-right ql-arrow"></i>
                            </a>
                        </div>
                        <div class="col-md-3 col-lg">
                            <a href="#" data-route="formative_assessments" class="dash-quick-link">
                                <i class="bi bi-pencil-square ql-icon bg-purple text-white"></i>
                                <span>Enter Marks</span><i class="bi bi-chevron-right ql-arrow"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<?php asset_script($appBase, 'js/dashboards/dashboard_base_controller.js'); ?>
<?php asset_script($appBase, 'js/dashboards/intern_student_teacher_dashboard.js'); ?>
