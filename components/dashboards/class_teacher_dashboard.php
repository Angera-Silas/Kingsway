<?php
/**
 * Class Teacher Dashboard
 * Operational view: class register, today's lessons, student welfare,
 * academic performance, parent communication, and quick actions.
 */
$rootId = 'classTeacherDashboard';
$periods = [
    ['key' => 'today', 'label' => 'Today'],
    ['key' => 'week',  'label' => 'This Week'],
    ['key' => 'term',  'label' => 'This Term'],
];
$default = 'today';
?>

<div class="container-fluid py-4 role-dashboard dashboard-surface dashboard-classroom" id="<?= $rootId ?>">

    <!-- Meta Bar — class name, period, refresh -->
    <div class="dash-meta-bar mb-4 d-flex align-items-center justify-content-between flex-wrap gap-2">
        <span class="fw-semibold text-dark" id="<?= $rootId ?>ClassName">
            <i class="bi bi-person-video3 me-1 text-primary"></i>Loading class…
        </span>
        <div class="d-flex align-items-center gap-3 flex-wrap">
            <?php require __DIR__ . '/partials/period_selector.php'; ?>
            <span class="small text-muted">
                Updated <span id="<?= $rootId ?>LastUpdated">—</span>
            </span>
            <button type="button" class="btn btn-sm btn-outline-success" id="<?= $rootId ?>Refresh">
                <i class="bi bi-arrow-clockwise me-1"></i>Refresh
            </button>
        </div>
    </div>

    <div class="dashboard-state alert alert-light border d-none" id="<?= $rootId ?>State" role="status"></div>

    <!-- ═══════════════════════════════════════════════════════════════════
         ROW 1 — TODAY'S CLASS STATUS (6 KPI cards)
         ═══════════════════════════════════════════════════════════════════ -->
    <div class="row g-3 mb-3">
        <div class="col-md-6 col-xl-4">
            <div class="dash-stat dsc-indigo h-100">
                <i class="bi bi-people dash-stat-icon"></i>
                <div class="dash-stat-value" id="ctClassSize">—</div>
                <div class="dash-stat-label">My Class Size</div>
                <div class="dash-stat-sub" id="ctClassSizeSub">Enrolled learners</div>
            </div>
        </div>
        <div class="col-md-6 col-xl-4">
            <div class="dash-stat dsc-green h-100">
                <i class="bi bi-person-check dash-stat-icon"></i>
                <div class="dash-stat-value" id="ctPresentToday">—</div>
                <div class="dash-stat-label">Present Today</div>
                <div class="dash-stat-sub" id="ctPresentSub">0% attendance rate</div>
            </div>
        </div>
        <div class="col-md-6 col-xl-4">
            <div class="dash-stat dsc-red h-100">
                <i class="bi bi-person-x dash-stat-icon"></i>
                <div class="dash-stat-value" id="ctAbsentToday">—</div>
                <div class="dash-stat-label">Absent Today</div>
                <div class="dash-stat-sub" id="ctAbsentSub">Without explanation</div>
            </div>
        </div>
    </div>
    <div class="row g-3 mb-4">
        <div class="col-md-6 col-xl-4">
            <div class="dash-stat dsc-amber h-100">
                <i class="bi bi-clock-history dash-stat-icon"></i>
                <div class="dash-stat-value" id="ctLateToday">—</div>
                <div class="dash-stat-label">Late Today</div>
                <div class="dash-stat-sub" id="ctLateSub">Arrived after roll call</div>
            </div>
        </div>
        <div class="col-md-6 col-xl-4">
            <div class="dash-stat dsc-orange h-100">
                <i class="bi bi-clipboard2-check dash-stat-icon"></i>
                <div class="dash-stat-value" id="ctPendingAssess">—</div>
                <div class="dash-stat-label">Pending Assessments</div>
                <div class="dash-stat-sub" id="ctPendingAssessSub">Not yet graded</div>
            </div>
        </div>
        <div class="col-md-6 col-xl-4">
            <div class="dash-stat dsc-purple h-100">
                <i class="bi bi-chat-dots dash-stat-icon"></i>
                <div class="dash-stat-value" id="ctParentMsgs">—</div>
                <div class="dash-stat-label">Parent Messages</div>
                <div class="dash-stat-sub" id="ctParentMsgsSub">Unread from parents</div>
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════════
         ROW 2 — TODAY'S SCHEDULE (full-width timeline)
         ═══════════════════════════════════════════════════════════════════ -->
    <div class="row g-3 mb-4">
        <div class="col-12">
            <div class="card dash-card">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <h6 class="dashboard-section-title mb-0">
                        <i class="bi bi-calendar-day me-1"></i>Today's Schedule
                    </h6>
                    <button type="button" class="btn btn-sm btn-outline-success" data-route="timetable">
                        Full timetable
                    </button>
                </div>
                <div class="card-body">
                    <div class="dash-timeline" id="ctScheduleTimeline">
                        <div class="dash-timeline-item" id="ctScheduleLoading">
                            <div class="d-flex align-items-center gap-2 text-muted small py-2">
                                <div class="spinner-border spinner-border-sm" role="status"></div>
                                Loading today's lessons…
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════════
         ROW 3 — STUDENTS NEEDING ATTENTION
         ═══════════════════════════════════════════════════════════════════ -->
    <div class="row g-3 mb-4">
        <div class="col-12">
            <div class="card dash-card">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <h6 class="dashboard-section-title mb-0">
                        <i class="bi bi-exclamation-triangle me-1"></i>Students Needing Attention
                    </h6>
                    <button type="button" class="btn btn-sm btn-outline-warning" data-route="my_students_list">
                        View all students
                    </button>
                </div>
                <div class="card-body p-0 dashboard-table-wrap">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th scope="col">Student</th>
                                    <th scope="col">Issue Type</th>
                                    <th scope="col">Metric</th>
                                    <th scope="col">Trend</th>
                                    <th scope="col">Last Contact</th>
                                    <th scope="col">Next Action</th>
                                </tr>
                            </thead>
                            <tbody id="ctAttentionBody">
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">
                                        <div class="spinner-border spinner-border-sm me-2" role="status"></div>Loading…
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════════
         ROW 4 — ACADEMIC PERFORMANCE (2 panels)
         ═══════════════════════════════════════════════════════════════════ -->
    <div class="row g-3 mb-4">
        <div class="col-lg-7">
            <div class="card dash-card h-100">
                <div class="card-header">
                    <h6 class="dashboard-section-title mb-0">
                        <i class="bi bi-bar-chart me-1"></i>Class Performance by Learning Area
                    </h6>
                </div>
                <div class="card-body">
                    <div class="dash-chart-wrap-lg">
                        <canvas id="ctPerformanceChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card dash-card h-100">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <h6 class="dashboard-section-title mb-0">
                        <i class="bi bi-clipboard2-data me-1"></i>Assessment Completion
                    </h6>
                    <button type="button" class="btn btn-sm btn-outline-primary" data-route="formative_assessments">
                        Enter marks
                    </button>
                </div>
                <div class="card-body p-0 dashboard-table-wrap">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th scope="col">Assessment</th>
                                    <th scope="col">Status</th>
                                    <th scope="col">Due</th>
                                </tr>
                            </thead>
                            <tbody id="ctAssessmentBody">
                                <tr>
                                    <td colspan="3" class="text-center text-muted py-4">
                                        <div class="spinner-border spinner-border-sm me-2" role="status"></div>Loading…
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════════
         ROW 5 — PARENT COMMUNICATION STATUS
         ═══════════════════════════════════════════════════════════════════ -->
    <div class="row g-3 mb-4">
        <div class="col-12">
            <div class="card dash-card">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <h6 class="dashboard-section-title mb-0">
                        <i class="bi bi-chat-left-text me-1"></i>Parent Communication Status
                    </h6>
                    <button type="button" class="btn btn-sm btn-outline-info" data-route="manage_communications">
                        Send message
                    </button>
                </div>
                <div class="card-body p-0 dashboard-table-wrap">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th scope="col">Student</th>
                                    <th scope="col">Last Contact</th>
                                    <th scope="col">Method</th>
                                    <th scope="col">Issue</th>
                                    <th scope="col">Next Action</th>
                                </tr>
                            </thead>
                            <tbody id="ctCommBody">
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">
                                        <div class="spinner-border spinner-border-sm me-2" role="status"></div>Loading…
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════════
         ROW 6 — QUICK ACTIONS
         ═══════════════════════════════════════════════════════════════════ -->
    <div class="row g-3">
        <div class="col-12">
            <div class="card dash-card">
                <div class="card-header">
                    <h6 class="dashboard-section-title mb-0">
                        <i class="bi bi-lightning-charge me-1"></i>Quick Actions
                    </h6>
                </div>
                <div class="card-body">
                    <div class="dashboard-action-grid">
                        <a href="#" class="dash-quick-link" data-route="mark_attendance">
                            <i class="bi bi-check2-square ql-icon bg-success text-white"></i>
                            <span>Mark Attendance</span>
                            <i class="bi bi-chevron-right ql-arrow"></i>
                        </a>
                        <a href="#" class="dash-quick-link" data-route="formative_assessments">
                            <i class="bi bi-pencil-square ql-icon bg-primary text-white"></i>
                            <span>Enter Marks</span>
                            <i class="bi bi-chevron-right ql-arrow"></i>
                        </a>
                        <a href="#" class="dash-quick-link" data-route="manage_lesson_plans">
                            <i class="bi bi-journal-text ql-icon bg-info text-white"></i>
                            <span>Lesson Plans</span>
                            <i class="bi bi-chevron-right ql-arrow"></i>
                        </a>
                        <a href="#" class="dash-quick-link" data-route="my_students_list">
                            <i class="bi bi-people ql-icon bg-indigo text-white"></i>
                            <span>Student List</span>
                            <i class="bi bi-chevron-right ql-arrow"></i>
                        </a>
                        <a href="#" class="dash-quick-link" data-route="manage_communications">
                            <i class="bi bi-chat-dots ql-icon bg-purple text-white"></i>
                            <span>Parent Messages</span>
                            <i class="bi bi-chevron-right ql-arrow"></i>
                        </a>
                        <a href="#" class="dash-quick-link" data-route="my_class_overview">
                            <i class="bi bi-person-video3 ql-icon bg-teal text-white"></i>
                            <span>My Class</span>
                            <i class="bi bi-chevron-right ql-arrow"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<script>
(() => {
    const ROOT = '<?= $rootId ?>';
    const $ = id => document.getElementById(id);

    /* Helpers */
    const esc = s => { const d = document.createElement('div'); d.textContent = s ?? ''; return d.innerHTML; };
    const sevClass = t => ({ Academic: '', Attendance: 'table-warning', Behavioural: 'table-danger', Welfare: 'table-warning' })[t] ?? '';
    const trendIco = t => t === 'improving' ? '<i class="bi bi-arrow-up text-success"></i>' : t === 'declining' ? '<i class="bi bi-arrow-down text-danger"></i>' : '<i class="bi bi-dash text-muted"></i>';
    const dotClr = s => ({ delivered: '#198754', current: '#0d6efd', upcoming: '#6c757d', missed: '#dc3545' })[s] || '#6c757d';
    const badgeHTML = s => '<span class="badge bg-' + ({ done: 'success', pending: 'warning', overdue: 'danger' })[s] + '">' + esc(s) + '</span>';

    const periodBar = $(ROOT + 'PeriodBar');
    if (periodBar) {
        periodBar.addEventListener('click', e => {
            const btn = e.target.closest('.dash-period-btn');
            if (!btn) return;
            loadDashboard(btn.dataset.period || 'today');
        });
    }

    function renderTimeline(lessons) {
        const el = $(ROOT + 'ScheduleTimeline');
        if (!lessons?.length) { el.innerHTML = '<div class="text-center text-muted py-4 small">No lessons scheduled today.</div>'; return; }
        el.innerHTML = lessons.map(l => {
            const sc = l.status === 'missed' ? 'text-danger fw-semibold' : l.status === 'delivered' ? 'text-success' : l.status === 'current' ? 'text-primary fw-semibold' : 'text-muted';
            return '<div class="dash-timeline-item" style="--timeline-dot-color:' + dotClr(l.status) + '">'
                + '<div class="d-flex justify-content-between align-items-start flex-wrap gap-1"><div>'
                + '<span class="fw-semibold">' + esc(l.start_time || '—') + '</span> <span class="ms-1">' + esc(l.subject_name || '—') + '</span>'
                + '<div class="small text-muted">Room ' + esc(l.room || '—') + '</div></div>'
                + '<span class="' + sc + ' small text-capitalize">' + esc(l.status || '—') + '</span></div></div>';
        }).join('');
    }

    /* Timeline colour override via CSS custom property */
    const ts = document.createElement('style');
    ts.textContent = '.role-dashboard .dash-timeline-item::before{background:var(--timeline-dot-color,var(--bs-success));box-shadow:0 0 0 1px var(--timeline-dot-color,var(--bs-success));}';
    document.head.appendChild(ts);

    function renderAttention(students) {
        const tbody = $('ctAttentionBody');
        if (!students?.length) { tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">No students flagged.</td></tr>'; return; }
        tbody.innerHTML = students.map(s => {
            const bc = s.issue_type === 'Behavioural' ? 'danger' : s.issue_type === 'Attendance' ? 'warning' : s.issue_type === 'Welfare' ? 'info' : 'primary';
            return '<tr class="' + sevClass(s.issue_type) + '">'
                + '<td class="fw-semibold">' + esc(s.student_name || '—') + '</td>'
                + '<td><span class="badge bg-' + bc + ' text-capitalize">' + esc(s.issue_type || '—') + '</span></td>'
                + '<td class="small">' + esc(s.metric || '—') + '</td>'
                + '<td>' + trendIco(s.trend) + '</td>'
                + '<td class="small text-muted">' + esc(s.last_contact || '—') + '</td>'
                + '<td class="small">' + esc(s.next_action || '—') + '</td></tr>';
        }).join('');
    }

    function renderAssessments(items) {
        const tbody = $('ctAssessmentBody');
        if (!items?.length) { tbody.innerHTML = '<tr><td colspan="3" class="text-center text-muted py-4">No assessments in this period.</td></tr>'; return; }
        tbody.innerHTML = items.map(a => '<tr><td class="small">' + esc(a.name || '—') + '</td><td>' + badgeHTML(a.status) + '</td><td class="small text-muted">' + esc(a.due_date || '—') + '</td></tr>').join('');
    }

    function renderComm(rows) {
        const tbody = $('ctCommBody');
        if (!rows?.length) { tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">No recent parent communications.</td></tr>'; return; }
        tbody.innerHTML = rows.map(r => '<tr class="' + (r.overdue ? 'table-warning' : '') + '">'
            + '<td class="fw-semibold">' + esc(r.student_name || '—') + '</td>'
            + '<td class="small">' + esc(r.last_contact_date || '—') + '</td>'
            + '<td class="small">' + esc(r.method || '—') + '</td>'
            + '<td class="small">' + esc(r.issue || '—') + '</td>'
            + '<td class="small">' + esc(r.next_action || '—') + '</td></tr>').join('');
    }

    /* KPI + meta update */
    function updateKPIs(cards) {
        if (!cards) return;
        const c = cards.class_size || {}, a = cards.today_attendance || {}, p = cards.pending_assessments || {}, m = cards.parent_messages || {};
        if (c.total != null) $('ctClassSize').textContent = c.total;
        if (c.class_name) $('ctClassSizeSub').textContent = c.class_name;
        if (a.present != null) $('ctPresentToday').textContent = a.present;
        if (a.percentage != null) $('ctPresentSub').textContent = a.percentage + '% attendance rate';
        if (a.absent != null) $('ctAbsentToday').textContent = a.absent;
        if (a.absent_explained != null) $('ctAbsentSub').textContent = a.absent_explained + ' with explanation';
        if (a.late != null) $('ctLateToday').textContent = a.late;
        if (a.late_note) $('ctLateSub').textContent = a.late_note;
        if (p.pending != null) $('ctPendingAssess').textContent = p.pending;
        if (p.note) $('ctPendingAssessSub').textContent = p.note;
        if (m.total != null) $('ctParentMsgs').textContent = m.total;
        if (m.note) $('ctParentMsgsSub').textContent = m.note;
    }

    function updateMeta(meta) {
        if (meta.class_name) $(ROOT + 'ClassName').innerHTML = '<i class="bi bi-person-video3 me-1 text-primary"></i>' + esc(meta.class_name);
        if (meta.updated_at) $(ROOT + 'LastUpdated').textContent = meta.updated_at;
    }

    /* Main loader */
    async function loadDashboard(period) {
        const stateEl = $(ROOT + 'State');
        try {
            stateEl.classList.remove('d-none');
            stateEl.textContent = 'Loading dashboard data…';
            const resp = await window.API.dashboard.getClassTeacherFull({ period: period || 'today' });
            const d = resp?.data?.data || resp?.data || resp || {};
            updateMeta(d.meta || {});
            updateKPIs(d.cards || {});
            renderTimeline(d.tables?.today_schedule || []);
            renderAttention(d.tables?.attention_students || []);
            renderAssessments(d.tables?.assessment_completion || []);
            renderComm(d.tables?.parent_communications || []);
            stateEl.classList.add('d-none');
            if (typeof Chart !== 'undefined' && d.charts) renderPerformanceChart(d.charts.performance_by_area || []);
        } catch (e) {
            stateEl.textContent = 'Failed to load dashboard. Please try again.';
            stateEl.classList.remove('alert-light', 'd-none');
            stateEl.classList.add('alert-danger');
        }
    }

    /* Performance chart */
    let perfChart = null;
    function renderPerformanceChart(areas) {
        const canvas = $('ctPerformanceChart');
        if (!canvas || !areas.length) return;
        if (perfChart) perfChart.destroy();
        perfChart = new Chart(canvas.getContext('2d'), {
            type: 'bar',
            data: {
                labels: areas.map(a => a.learning_area || '—'),
                datasets: [
                    { label: 'Meeting / Exceeding', data: areas.map(a => a.meeting_exceeding || 0), backgroundColor: '#198754' },
                    { label: 'Approaching', data: areas.map(a => a.approaching || 0), backgroundColor: '#ffc107' },
                    { label: 'Below Expectations', data: areas.map(a => a.below || 0), backgroundColor: '#dc3545' }
                ]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom', labels: { boxWidth: 14, font: { size: 11 } } } },
                scales: {
                    x: { stacked: true, ticks: { font: { size: 10 }, maxRotation: 45 } },
                    y: { stacked: true, beginAtZero: true, max: 100, ticks: { callback: v => v + '%', font: { size: 10 } } }
                }
            }
        });
    }

    /* Init + refresh */
    $(ROOT + 'Refresh')?.addEventListener('click', () => {
        const activeBtn = periodBar?.querySelector('.btn-success');
        loadDashboard(activeBtn?.dataset.period || 'today');
    });
    loadDashboard('today');
    window.classTeacherDashboard = { reload: loadDashboard };
})();
</script>

<?php asset_script($appBase, 'js/dashboards/dashboard_base_controller.js'); ?>
<?php asset_script($appBase, 'js/dashboards/class_teacher_dashboard.js'); ?>
