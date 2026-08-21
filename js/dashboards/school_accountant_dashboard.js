(() => {
    const unwrap = (response) => {
        let value = response;
        for (let depth = 0; depth < 4; depth += 1) {
            if (
                value
                && typeof value === 'object'
                && !Array.isArray(value)
                && Object.prototype.hasOwnProperty.call(value, 'data')
            ) {
                value = value.data;
                continue;
            }
            break;
        }
        return value;
    };

    const controller = DashboardBaseController.create({
        controllerName: 'SchoolAccountantDashboardController',
        rootId: 'accountantDashboard',
        refreshButtonId: 'accountantDashboardRefresh',
        stateId: 'accountantDashboardState',
        scopeId: 'accountantDashboardScope',
        lastUpdatedId: 'accountantDashboardLastUpdated',

        async apiMethod({ period }) {
            const [financialResponse, paymentsResponse] = await Promise.all([
                window.API.dashboard.getAccountantFinancial({ period }),
                window.API.dashboard.getAccountantPayments({ period, limit: 15 })
            ]);

            const financial = unwrap(financialResponse) || {};
            const paymentFeed = unwrap(paymentsResponse) || {};
            const fees = financial.fees || {};
            const collections = financial.collections || {};
            const payments = financial.payments || {};
            const budget = financial.budget || {};
            const expenses = financial.expenses || {};
            const payroll = financial.payroll || {};
            const approvals = financial.approvals || {};
            const trends = Array.isArray(paymentFeed.trends)
                ? paymentFeed.trends
                : [];
            const methods = Array.isArray(payments.by_method)
                ? payments.by_method
                : [];
            const byLevel = Array.isArray(fees.by_level)
                ? fees.by_level
                : [];

            const periodKey = period || 'month';
            const collectedByPeriod = {
                today: Number(collections.today_total || 0),
                week: Number(collections.week_total || 0),
                month: Number(collections.month_total || 0),
                term: Number(fees.term_collected || 0),
                year: Number(fees.total_collected || 0)
            };
            const countByPeriod = {
                today: Number(collections.today_count || 0),
                week: Number(collections.week_count || 0),
                month: Number(collections.month_count || 0),
                term: Number(fees.term_collected_count || collections.month_count || 0),
                year: Number(fees.total_collected_count || collections.month_count || 0)
            };

            return {
                meta: {
                    scope_label: fees.current_term_name
                        || (financial.academic_year
                            ? `Academic year ${financial.academic_year}`
                            : 'Finance')
                },
                cards: {
                    current_term_collected: collectedByPeriod[periodKey] || collectedByPeriod.month,
                    total_outstanding: Number(fees.total_outstanding || 0),
                    mpesa_total: Number(payments.mpesa_total || 0),
                    bank_total: Number(payments.bank_total || 0),
                    cash_total: Number(payments.cash_total || 0),
                    unreconciled_count: Number(payments.unreconciled_count || 0),
                    month_count: countByPeriod[periodKey] || countByPeriod.month,
                    defaulters_count: Number(fees.defaulters_count || 0),
                    mpesa_count: Number(payments.mpesa_count || 0),
                    bank_count: Number(payments.bank_count || 0),
                    cash_count: Number(payments.cash_count || 0),
                    defaulters_amount: Number(fees.defaulters_amount || 0)
                },
                charts: {
                    collection_trend: {
                        labels: trends.map((r) => r.month || 'Unknown'),
                        data: trends.map((r) => Number(r.total_collected || 0))
                    },
                    payment_methods: {
                        labels: methods.map((r) => r.payment_method || 'Other'),
                        data: methods.map((r) => Number(r.total_amount || 0))
                    },
                    by_level: {
                        labels: byLevel.map((r) => r.level || r.class_level || 'Unknown'),
                        data: byLevel.map((r) => Number(r.collected || r.total_collected || 0))
                    }
                },
                tables: {
                    recent_transactions: Array.isArray(paymentFeed.recent_transactions)
                        ? paymentFeed.recent_transactions
                        : [],
                    unreconciled: Array.isArray(paymentFeed.unreconciled)
                        ? paymentFeed.unreconciled
                        : [],
                    defaulters: Array.isArray(fees.defaulters)
                        ? fees.defaulters
                        : []
                },
                widgets: {
                    budget: {
                        allocated: Number(budget.total_allocated || 0),
                        spent: Number(budget.total_spent || 0),
                        rate: Number(budget.utilization_rate || 0)
                    },
                    payroll: {
                        staff_count: Number(payroll.staff_count || 0),
                        processed: Number(payroll.processed_count || 0),
                        pending: Number(payroll.pending_count || 0),
                        total_amount: Number(payroll.total_amount || 0)
                    },
                    approvals: {
                        pending_count: Number(approvals.pending_count || 0),
                        urgent_count: Number(approvals.urgent_count || 0),
                        items: Array.isArray(approvals.items) ? approvals.items : []
                    }
                }
            };
        },

        cards: [
            {
                id: 'accCollected',
                path: 'cards.current_term_collected',
                format: 'currency',
                subtitleId: 'accCollectedSub',
                subtitle: (d) => `${Number(d.cards?.month_count || 0)} transactions this period`
            },
            {
                id: 'accOutstanding',
                path: 'cards.total_outstanding',
                format: 'currency',
                subtitleId: 'accOutstandingSub',
                subtitle: (d) => `${Number(d.cards?.defaulters_count || 0)} students with arrears`
            },
            {
                id: 'accMpesa',
                path: 'cards.mpesa_total',
                format: 'currency',
                subtitleId: 'accMpesaSub',
                subtitle: (d) => `${Number(d.cards?.mpesa_count || 0)} M-Pesa payments`
            },
            {
                id: 'accBank',
                path: 'cards.bank_total',
                format: 'currency',
                subtitleId: 'accBankSub',
                subtitle: (d) => `${Number(d.cards?.bank_count || 0)} bank payments`
            },
            {
                id: 'accCash',
                path: 'cards.cash_total',
                format: 'currency',
                subtitleId: 'accCashSub',
                subtitle: (d) => `${Number(d.cards?.cash_count || 0)} cash payments`
            },
            {
                id: 'accUnreconciled',
                path: 'cards.unreconciled_count',
                format: 'number',
                subtitleId: 'accUnreconciledSub',
                subtitle: (d) => {
                    const count = Number(d.cards?.unreconciled_count || 0);
                    return count > 0 ? `${count} require attention` : 'All reconciled';
                },
                value: (d, instance) => {
                    const count = Number(d.cards?.unreconciled_count || 0);
                    if (count > 0) {
                        return `${instance.formatValue(count, 'number')} <span class="badge bg-danger ms-1" style="font-size:0.55em">!</span>`;
                    }
                    return instance.formatValue(count, 'number');
                }
            }
        ],

        chartDefinitions: [
            {
                id: 'accCollectionChart',
                path: 'charts.collection_trend',
                label: 'Collections',
                type: 'line',
                fill: true
            },
            {
                id: 'accMethodChart',
                path: 'charts.payment_methods',
                label: 'Payments',
                type: 'doughnut',
                showLegend: true
            },
            {
                id: 'accLevelChart',
                path: 'charts.by_level',
                label: 'Collected',
                type: 'bar'
            }
        ],

        tableDefinitions: [
            {
                bodyId: 'accTransactionsBody',
                path: 'tables.recent_transactions',
                emptyText: 'No recent payments.',
                columns: [
                    { key: 'reference' },
                    { key: 'student_name' },
                    { key: 'class_name' },
                    { key: 'payment_method' },
                    { key: 'amount', format: 'currency' },
                    { key: 'payment_date', format: 'datetime' }
                ]
            },
            {
                bodyId: 'accUnreconciledBody',
                path: 'tables.unreconciled',
                emptyText: 'No unreconciled M-Pesa payments.',
                columns: [
                    { key: 'phone_number' },
                    { key: 'amount', format: 'currency' },
                    { key: 'transaction_date', format: 'datetime' },
                    {
                        key: 'status',
                        render: (value, row, instance) => instance.badge(value || 'pending', {
                            pending: 'warning',
                            matched: 'success',
                            failed: 'danger'
                        })
                    }
                ]
            },
            {
                bodyId: 'accDefaultersBody',
                path: 'tables.defaulters',
                emptyText: 'No fee defaulters.',
                columns: [
                    { key: 'student_name' },
                    { key: 'class_name' },
                    { key: 'amount_owed', format: 'currency' },
                    { key: 'days_overdue', format: 'number' }
                ]
            }
        ],

        afterRender(data) {
            const widgets = data.widgets || {};
            const budget = widgets.budget || {};
            const payroll = widgets.payroll || {};
            const approvals = widgets.approvals || {};

            const budgetUtil = document.getElementById('accBudgetUtil');
            if (budgetUtil) {
                const rate = Number(budget.rate || 0);
                const spent = Number(budget.spent || 0);
                const allocated = Number(budget.allocated || 0);
                const barClass = rate > 100 ? 'bg-danger' : rate > 80 ? 'bg-warning' : 'bg-success';
                budgetUtil.innerHTML = `
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="fw-bold fs-5">${this.formatValue(rate, 'percent')}</span>
                        <span class="text-muted small">${this.formatValue(spent, 'currency')} of ${this.formatValue(allocated, 'currency')}</span>
                    </div>
                    <div class="progress" style="height:12px;">
                        <div class="progress-bar ${barClass}" role="progressbar" style="width:${Math.min(rate, 100)}%"></div>
                    </div>`;
            }

            const payrollEl = document.getElementById('accPayroll');
            if (payrollEl) {
                const pending = Number(payroll.pending || 0);
                const processed = Number(payroll.processed || 0);
                const total = Number(payroll.staff_count || 0);
                const totalAmt = Number(payroll.total_amount || 0);
                payrollEl.innerHTML = `
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="fw-bold fs-5">${processed} / ${total}</span>
                        <span class="text-muted small">staff paid</span>
                    </div>
                    <div class="progress mb-2" style="height:8px;">
                        <div class="progress-bar bg-success" role="progressbar" style="width:${total ? ((processed / total) * 100) : 0}%"></div>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="small text-muted">Total: ${this.formatValue(totalAmt, 'currency')}</span>
                        <span class="small ${pending > 0 ? 'text-warning' : 'text-success'}">${pending > 0 ? `${pending} pending` : 'All processed'}</span>
                    </div>`;
            }

            const approvalsEl = document.getElementById('accApprovals');
            if (approvalsEl) {
                const pending = Number(approvals.pending_count || 0);
                const urgent = Number(approvals.urgent_count || 0);
                const items = approvals.items || [];
                let html = `
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="fw-bold fs-5">${pending}</span>
                        <span class="text-muted small">pending</span>
                    </div>`;
                if (urgent > 0) {
                    html += `<div class="mb-2"><span class="badge bg-danger">${urgent} urgent</span></div>`;
                }
                if (items.length > 0) {
                    html += '<ul class="list-unstyled mb-0">';
                    items.slice(0, 4).forEach((item) => {
                        html += `<li class="small mb-1"><i class="bi bi-circle-fill text-warning me-1" style="font-size:0.4em;vertical-align:middle;"></i>${this.escapeHtml(item.description || item.label || 'Item')}</li>`;
                    });
                    html += '</ul>';
                } else if (pending === 0) {
                    html += '<p class="text-success small mb-0"><i class="bi bi-check-circle me-1"></i>Nothing pending</p>';
                }
                approvalsEl.innerHTML = html;
            }
        }
    });

    window.SchoolAccountantDashboardController = controller;
    window.schoolAccountantDashboardController = controller;
    DashboardBaseController.boot(controller, 'SchoolAccountantDashboardController');
})();
