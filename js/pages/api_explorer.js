const apiExplorerController = {
    state: {
        endpoints: [],
        filtered: [],
        selected: null,
        permissionMap: window.ENDPOINT_PERMISSIONS || {}
    },

    els: {},

    async init() {
        if (window.AuthContext?.ready) await window.AuthContext.ready();
        this.initElements();
        this.collectEndpoints();
        this.bindEvents();
    },

    initElements() {
        this.els.search = document.getElementById('apiSearch');
        this.els.namespaceFilter = document.getElementById('namespaceFilter');
        this.els.refreshBtn = document.getElementById('refreshEndpoints');
        this.els.tableBody = document.getElementById('apiEndpointsBody');
        this.els.selectedEndpointLabel = document.getElementById('selectedEndpointLabel');
        this.els.selectedNamespaceLabel = document.getElementById('selectedNamespaceLabel');
        this.els.permissionHint = document.getElementById('permissionHint');
        this.els.payload = document.getElementById('apiPayload');
        this.els.result = document.getElementById('apiResult');
        this.els.invokeBtn = document.getElementById('invokeEndpoint');
        this.els.loadSample = document.getElementById('loadSample');
    },

    collectEndpoints() {
        const api = window.API || {};
        const endpoints = [];
        Object.keys(api).forEach(ns => {
            const bucket = api[ns];
            if (typeof bucket === 'object' && bucket !== null) {
                Object.keys(bucket).forEach(method => {
                    if (typeof bucket[method] === 'function') {
                        const permCodes = this.state.permissionMap[method] || this.state.permissionMap[`${ns}.${method}`] || [];
                        endpoints.push({
                            namespace: ns,
                            method: method,
                            permissionCodes: Array.isArray(permCodes) ? permCodes : [permCodes].filter(Boolean),
                            sampleInput: null
                        });
                    }
                });
            }
        });
        this.state.endpoints = endpoints;
        this.state.filtered = endpoints;
    },

    filterEndpoints() {
        const q = (this.els.search?.value || '').toLowerCase();
        const ns = this.els.namespaceFilter?.value || '';
        this.state.filtered = this.state.endpoints.filter(ep => {
            if (ns && ep.namespace !== ns) return false;
            if (q && !ep.namespace.toLowerCase().includes(q) && !ep.method.toLowerCase().includes(q)) return false;
            return true;
        });
        this.render();
    },

    render() {
        const tbody = this.els.tableBody;
        if (!tbody) return;
        tbody.innerHTML = this.state.filtered.map(ep => `
            <tr class="api-endpoint-row ${this.state.selected === ep ? 'table-active' : ''}" data-ns="${ep.namespace}" data-method="${ep.method}">
                <td><span class="badge bg-secondary">${ep.namespace}</span></td>
                <td><code>${ep.method}</code></td>
                <td><small class="text-muted">${ep.permissionCodes.join(', ') || '—'}</small></td>
                <td><button class="btn btn-sm btn-outline-primary select-endpoint">Select</button></td>
            </tr>
        `).join('');
    },

    selectEndpoint(ep) {
        this.state.selected = ep;
        if (this.els.selectedEndpointLabel) this.els.selectedEndpointLabel.textContent = `${ep.namespace}.${ep.method}()`;
        if (this.els.selectedNamespaceLabel) this.els.selectedNamespaceLabel.textContent = ep.namespace;
        if (this.els.permissionHint) this.els.permissionHint.textContent = ep.permissionCodes.join(', ') || 'None required';
        if (this.els.payload) this.els.payload.value = ep.sampleInput || '';
        if (this.els.result) this.els.result.textContent = '';
        window.loadSampleEndpoint?.();
    },

    async invokeSelected() {
        const ep = this.state.selected;
        if (!ep) { alert('Select an endpoint first.'); return; }
        const ns = ep.namespace;
        const method = ep.method;
        const api = window.API || {};
        if (!api[ns] || typeof api[ns][method] !== 'function') {
            if (this.els.result) this.els.result.textContent = 'Error: Namespace or method not found on window.API';
            return;
        }
        let payload;
        try {
            payload = JSON.parse(this.els.payload?.value || '{}');
        } catch (e) {
            if (this.els.result) this.els.result.textContent = `JSON parse error: ${e.message}`;
            return;
        }
        try {
            const result = await api[ns][method](payload);
            if (this.els.result) this.els.result.textContent = JSON.stringify(result, null, 2);
        } catch (e) {
            if (this.els.result) this.els.result.textContent = `Error: ${e.message}`;
        }
    },

    loadSample() {
        if (!this.state.selected) return;
        if (this.els.payload) this.els.payload.value = JSON.stringify({ id: 1, limit: 10 }, null, 2);
    },

    bindEvents() {
        this.els.search?.addEventListener('input', () => this.filterEndpoints());
        this.els.namespaceFilter?.addEventListener('change', () => this.filterEndpoints());
        this.els.refreshBtn?.addEventListener('click', () => {
            this.collectEndpoints();
            this.filterEndpoints();
        });
        this.els.invokeBtn?.addEventListener('click', () => this.invokeSelected());
        this.els.loadSample?.addEventListener('click', () => this.loadSample());
        this.els.tableBody?.addEventListener('click', e => {
            const row = e.target.closest('.api-endpoint-row');
            if (row) {
                const idx = Array.from(row.parentNode.children).indexOf(row);
                this.selectEndpoint(this.state.filtered[idx]);
            }
        });
    }
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => apiExplorerController.init().catch(() => {}));
} else {
    apiExplorerController.init().catch(() => {});
}
