(() => {
    const controller = DashboardBaseController.create({
        controllerName: 'DeputyDisciplineDashboardController',
        rootId: 'deputyDisciplineDashboard',
        refreshButtonId: 'deputyDisciplineDashboardRefresh',
        stateId: 'deputyDisciplineDashboardState',
        scopeId: 'deputyDisciplineDashboardScope',
        lastUpdatedId: 'deputyDisciplineDashboardLastUpdated',
        defaultPeriod: 'today',
        apiMethod: ({ period }) => window.API.dashboard.getDeputyDisciplineFull({ period }),
        cards: [
            {
                id: 'ddOpenCases',
                path: 'cards.discipline_cases.open',
                subtitleId: 'ddOpenCasesSub',
                subtitle: d => `${Number(d.cards?.discipline_cases?.in_progress || 0)} in progress`
            },
            {
                id: 'ddUrgent',
                path: 'cards.discipline_cases.urgent',
                subtitleId: 'ddUrgentSub',
                subtitle: d => `${Number(d.cards?.discipline_cases?.urgent || 0)} require attention`
            },
            {
                id: 'ddAbsentees',
                path: 'cards.attendance_today.absent_count',
                subtitleId: 'ddAbsenteesSub',
                subtitle: d => `${Number(d.cards?.attendance_today?.unexcused || 0)} unexcused`
            },
            {
                id: 'ddTruancy',
                path: 'cards.chronic_truancy.count',
                subtitleId: 'ddTruancySub',
                subtitle: d => `${Number(d.cards?.chronic_truancy?.new_this_week || 0)} new this week`
            },
            {
                id: 'ddCounseling',
                path: 'cards.counseling_referrals.pending',
                subtitleId: 'ddCounselingSub',
                subtitle: d => `${Number(d.cards?.counseling_referrals?.completed || 0)} completed`
            },
            {
                id: 'ddMeetings',
                path: 'cards.parent_meetings.scheduled',
                subtitleId: 'ddMeetingsSub',
                subtitle: d => `${Number(d.cards?.parent_meetings?.completed || 0)} completed`
            }
        ],
        chartDefinitions: [
            {
                id: 'ddTrendChart',
                path: 'charts.discipline_trend',
                label: 'Cases',
                type: 'line'
            },
            {
                id: 'ddCategoryChart',
                path: 'charts.cases_by_category',
                label: 'Cases',
                type: 'doughnut',
                showLegend: true
            }
        ],
        tableDefinitions: [
            {
                bodyId: 'ddCasesBody',
                path: 'tables.active_cases',
                emptyText: 'No active discipline cases.',
                columns: [
                    { key: 'student_name' },
                    { key: 'category' },
                    {
                        key: 'severity',
                        render: (v, r, c) => c.badge(v, {
                            low: 'secondary',
                            medium: 'info',
                            high: 'warning',
                            critical: 'danger'
                        })
                    },
                    { key: 'incident_date', format: 'date' },
                    {
                        key: 'status',
                        render: (v, r, c) => c.badge(v, {
                            open: 'danger',
                            in_progress: 'warning',
                            under_review: 'info',
                            resolved: 'success',
                            closed: 'secondary'
                        })
                    }
                ]
            },
            {
                bodyId: 'ddAtRiskBody',
                path: 'tables.at_risk_students',
                emptyText: 'No at-risk students identified.',
                columns: [
                    { key: 'student_name' },
                    { key: 'class_name' },
                    {
                        key: 'infraction_count',
                        render: (v) => {
                            const n = Number(v || 0);
                            const variant = n >= 5 ? 'danger' : n >= 3 ? 'warning' : 'info';
                            return `<span class="badge bg-${variant}">${n}</span>`;
                        }
                    },
                    { key: 'last_incident_date', format: 'date' }
                ]
            }
        ]
    });

    window.deputyDisciplineDashboard = controller;
    DashboardBaseController.boot(controller, 'DeputyDisciplineDashboardController');
})();
