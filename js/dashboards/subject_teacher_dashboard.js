(() => {
    const controller = DashboardBaseController.create({
        controllerName: 'SubjectTeacherDashboardController',
        rootId: 'subjectTeacherDashboard',
        refreshButtonId: 'subjectTeacherDashboardRefresh',
        stateId: 'subjectTeacherDashboardState',
        scopeId: 'subjectTeacherDashboardScope',
        lastUpdatedId: 'subjectTeacherDashboardLastUpdated',

        async apiMethod({ period } = {}) {
            try {
                const response = await window.API.dashboard.getSubjectTeacherFull({ period });
                const data = response?.data?.data || response?.data || response || {};
                return {
                    meta: data.meta || {},
                    cards: data.cards || {},
                    charts: data.charts || {},
                    tables: data.tables || {}
                };
            } catch (e) {
                return { meta: {}, cards: {}, charts: {}, tables: {} };
            }
        },

        cards: [
            { id: 'stClasses', path: 'cards.classes.total_classes', subtitleId: 'stClassesSub', subtitle: d => `${Number(d.cards?.classes?.total_students || 0)} students` },
            { id: 'stAssessments', path: 'cards.assessments_due.pending_assessments', subtitleId: 'stAssessmentsSub', subtitle: d => `${Number(d.cards?.assessments_due?.overdue || 0)} overdue` },
            { id: 'stGraded', path: 'cards.graded.graded_this_week', subtitleId: 'stGradedSub', subtitle: d => `${Number(d.cards?.graded?.average_score || 0).toFixed(1)} average` },
            { id: 'stExams', path: 'cards.upcoming_exams.count', subtitleId: 'stExamsSub', subtitle: d => `${Number(d.cards?.upcoming_exams?.next_exam_days || 0)} days to next` }
        ],
        chartDefinitions: [
            { id: 'subPerformanceChart', path: 'charts.subject_performance', label: 'Average score', type: 'bar' },
            { id: 'subGradingChart', path: 'charts.assessment_trends', label: 'Grading trend', type: 'line' }
        ],
        tableDefinitions: [
            {
                bodyId: 'subPendingBody', path: 'tables.pending_assessments', emptyText: 'No pending assessments.', columns: [
                    { key: 'title' }, { key: 'class' }, { key: 'due_date', format: 'date' },
                    { value: () => 'Pending', render: (v, r, c) => c.badge(v, { pending: 'warning' }) }
                ]
            },
            {
                bodyId: 'subExamBody', path: 'tables.exam_schedule', emptyText: 'No upcoming exams.', columns: [
                    { key: 'date', format: 'date' }, { key: 'class' }, { key: 'type' },
                    { key: 'status', render: (v, r, c) => c.badge(v || 'upcoming', { upcoming: 'info', ongoing: 'warning', completed: 'success' }) }
                ]
            }
        ]
    });

    window.SubjectTeacherDashboardController = controller;
    window.subjectTeacherDashboardController = controller;
    DashboardBaseController.boot(controller, 'SubjectTeacherDashboardController');
})();
