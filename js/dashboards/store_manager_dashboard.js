(() => {
    const unwrap = (response) => {
        let value = response;
        for (let depth = 0; depth < 4; depth += 1) {
            if (value && typeof value === 'object' && !Array.isArray(value)
                && Object.prototype.hasOwnProperty.call(value, 'data')) {
                value = value.data;
                continue;
            }
            break;
        }
        return value;
    };

    const controller = DashboardBaseController.create({
        controllerName: 'StoreManagerDashboardController',
        rootId: 'storeDashboard',
        refreshButtonId: 'storeDashboardRefresh',
        stateId: 'storeDashboardState',
        scopeId: 'storeDashboardScope',
        lastUpdatedId: 'storeDashboardLastUpdated',

        async apiMethod({ period } = {}) {
            try {
                const source = unwrap(await window.API.inventory.getDashboard({ period })) || {};
                const summary = source.summary || {};
                const categories = Array.isArray(source.by_category) ? source.by_category : [];
                const health = Array.isArray(source.stock_health) ? source.stock_health : [];

                return {
                    meta: { scope_label: 'Inventory', period },
                    cards: {
                        active_items: Number(summary.active_items || 0),
                        low_stock: Number(summary.low_stock || 0),
                        out_of_stock: Number(summary.out_of_stock || 0),
                        inventory_value: Number(summary.inventory_value || 0)
                    },
                    charts: {
                        by_category: {
                            labels: categories.map(row => row.category || 'Uncategorised'),
                            data: categories.map(row => Number(row.item_count || 0))
                        },
                        health: {
                            labels: health.map(row => row.stock_status || 'Unknown'),
                            data: health.map(row => Number(row.item_count || 0))
                        }
                    },
                    tables: {
                        low_stock: Array.isArray(source.low_stock_items) ? source.low_stock_items : [],
                        requisitions: Array.isArray(source.pending_requisitions) ? source.pending_requisitions : []
                    }
                };
            } catch (e) {
                return {
                    meta: { scope_label: 'Inventory' },
                    cards: { active_items: 0, low_stock: 0, out_of_stock: 0, inventory_value: 0 },
                    charts: { by_category: { labels: [], data: [] }, health: { labels: [], data: [] } },
                    tables: { low_stock: [], requisitions: [] }
                };
            }
        },

        cards: [
            { id: 'storeItems', path: 'cards.active_items', subtitleId: 'storeItemsSub', subtitle: 'Items available for issue' },
            { id: 'storeLowStock', path: 'cards.low_stock', subtitleId: 'storeLowStockSub', subtitle: 'At or below reorder level' },
            { id: 'storeOutStock', path: 'cards.out_of_stock', subtitleId: 'storeOutStockSub', subtitle: 'Require replenishment' },
            { id: 'storeValue', path: 'cards.inventory_value', format: 'currency', subtitleId: 'storeValueSub', subtitle: 'Current stock valuation' }
        ],
        chartDefinitions: [
            { id: 'storeCategoryChart', path: 'charts.by_category', label: 'Items', type: 'doughnut', showLegend: true },
            { id: 'storeHealthChart', path: 'charts.health', label: 'Items', type: 'bar' }
        ],
        tableDefinitions: [
            {
                bodyId: 'storeLowBody', path: 'tables.low_stock', emptyText: 'No low-stock items.', columns: [
                    { key: 'name' }, { key: 'current_quantity', format: 'number' },
                    { key: 'minimum_quantity', format: 'number' }, { key: 'supplier_name' }
                ]
            },
            {
                bodyId: 'storeReqsBody', path: 'tables.requisitions', emptyText: 'No pending requisitions.', columns: [
                    { value: (row) => row.requested_by_name || row.requested_by || '—' },
                    { value: (row) => row.item_count || row.items || '—' },
                    { key: 'created_at', format: 'date' },
                    { key: 'status', render: (value, row, instance) => instance.badge(value, { pending: 'warning', approved: 'success', rejected: 'danger', fulfilled: 'info' }) }
                ]
            }
        ]
    });

    window.StoreManagerDashboardController = controller;
    window.storeDashboardController = controller;
    DashboardBaseController.boot(controller, 'StoreManagerDashboardController');
})();
