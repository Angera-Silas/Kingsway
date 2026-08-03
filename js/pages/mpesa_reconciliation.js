const MpesaReconciliationController = {
    state: {
        transactions: [],
        filtered: [],
    },

    async init() {
        await window.AuthContext?.ready?.();
        if (!window.AuthContext?.isAuthenticated()) return;
        this.bindEvents();
        await this.loadData();
    },

    bindEvents() {
        const search = document.getElementById('searchTxn');
        if (search) search.addEventListener('input', () => this.applyFilters());

        const dtFrom = document.getElementById('filterDateFrom');
        if (dtFrom) dtFrom.addEventListener('change', () => this.applyFilters());

        const dtTo = document.getElementById('filterDateTo');
        if (dtTo) dtTo.addEventListener('change', () => this.applyFilters());
    },

    async loadData() {
        try {
            this.showLoading();
            const res = await window.API.apiCall('/payments/unmatched-mpesa', 'GET');
            const txns = res?.transactions ?? res?.data ?? [];
            this.state.transactions = Array.isArray(txns) ? txns : [];
            this.state.filtered = [...this.state.transactions];
            this.updateStats();
            this.renderTable();
        } catch (err) {
            console.error('Error loading unmatched transactions:', err);
            this.showNotification('Failed to load transactions', 'error');
        }
    },

    updateStats() {
        const all = this.state.transactions;
        const total = all.length;
        const totalAmount = all.reduce((s, t) => s + parseFloat(t.amount || 0), 0);
        const el = (id, v) => { const e = document.getElementById(id); if (e) e.textContent = v; };
        el('kpiUnmatched', total);
        el('kpiTotalAmount', `KES ${totalAmount.toLocaleString(undefined, { minimumFractionDigits: 2 })}`);
        el('kpiReconciledToday', '--');
    },

    applyFilters() {
        const search = (document.getElementById('searchTxn')?.value || '').toLowerCase();
        const from = document.getElementById('filterDateFrom')?.value || '';
        const to = document.getElementById('filterDateTo')?.value || '';

        let f = [...this.state.transactions];
        if (search) {
            f = f.filter(t =>
                (t.mpesa_code || '').toLowerCase().includes(search) ||
                (t.phone_number || '').includes(search) ||
                (t.first_name || '').toLowerCase().includes(search) ||
                (t.last_name || '').toLowerCase().includes(search) ||
                (t.bill_ref_number || '').toLowerCase().includes(search)
            );
        }
        if (from) f = f.filter(t => (t.transaction_date || '').slice(0, 10) >= from);
        if (to) f = f.filter(t => (t.transaction_date || '').slice(0, 10) <= to);

        this.state.filtered = f;
        this.renderTable();
    },

    renderTable() {
        const tbody = document.querySelector('#reconTable tbody');
        if (!tbody) return;
        if (this.state.filtered.length === 0) {
            tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">No unmatched transactions found</td></tr>';
            return;
        }
        tbody.innerHTML = this.state.filtered.map((t, i) => `
            <tr>
                <td>${i + 1}</td>
                <td><code>${this.esc(t.mpesa_code || '--')}</code></td>
                <td><small>${this.esc((t.transaction_date || '').slice(0, 16).replace('T', ' '))}</small></td>
                <td>${this.esc([t.first_name, t.middle_name, t.last_name].filter(Boolean).join(' ') || '--')}</td>
                <td>${this.esc(t.phone_number || '--')}</td>
                <td class="text-end fw-bold">${parseFloat(t.amount || 0).toLocaleString(undefined, { minimumFractionDigits: 2 })}</td>
                <td><small>${this.esc(t.bill_ref_number || '--')}</small></td>
                <td>
                    <button class="btn btn-sm btn-success" onclick="MpesaReconciliationController.openReconcile(${i})">
                        <i class="bi bi-check-circle"></i> Reconcile
                    </button>
                </td>
            </tr>
        `).join('');
    },

    async openReconcile(index) {
        const t = this.state.filtered[index];
        if (!t) return;

        const payerName = [t.first_name, t.middle_name, t.last_name].filter(Boolean).join(' ') || 'Unknown';
        const phone = t.phone_number || '';

        this.showModal('Reconcile M-Pesa Transaction', `
            <div class="mb-3 p-3 bg-light rounded">
                <div class="row">
                    <div class="col-md-6">
                        <small class="text-muted">M-Pesa Code</small>
                        <p class="fw-bold mb-1">${this.esc(t.mpesa_code)}</p>
                        <small class="text-muted">Amount</small>
                        <p class="fw-bold mb-1 text-success">KES ${parseFloat(t.amount || 0).toLocaleString(undefined, { minimumFractionDigits: 2 })}</p>
                    </div>
                    <div class="col-md-6">
                        <small class="text-muted">Payer</small>
                        <p class="mb-1">${this.esc(payerName)}</p>
                        <small class="text-muted">Phone</small>
                        <p class="mb-1">${this.esc(phone || 'N/A')}</p>
                    </div>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold">Student Lookup by Phone</label>
                <div class="input-group">
                    <input type="tel" class="form-control" id="lookupPhone" value="${this.esc(phone)}" placeholder="Enter phone number">
                    <button class="btn btn-outline-primary" onclick="MpesaReconciliationController.lookupStudent()">
                        <i class="bi bi-search"></i> Find
                    </button>
                </div>
                <div id="lookupResults" class="mt-2"></div>
            </div>

            <form id="reconcileForm">
                <input type="hidden" name="mpesa_id" value="${t.id}">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Select Student</label>
                    <select class="form-select" id="studentSelect" name="student_id" required>
                        <option value="">-- Select student --</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Notes (optional)</label>
                    <textarea class="form-control" name="notes" rows="2" placeholder="Reconciliation notes..."></textarea>
                </div>
                <button type="submit" class="btn btn-success w-100">
                    <i class="bi bi-check-circle me-1"></i> Reconcile & Allocate to Fees
                </button>
            </form>
        `, () => {
            document.getElementById('reconcileForm')?.addEventListener('submit', async (e) => {
                e.preventDefault();
                await this.doReconcile(e.target);
            });
            if (phone) setTimeout(() => this.lookupStudent(), 300);
        });
    },

    async lookupStudent() {
        const phone = document.getElementById('lookupPhone')?.value;
        if (!phone) return;

        const resultsDiv = document.getElementById('lookupResults');
        const select = document.getElementById('studentSelect');
        if (!resultsDiv || !select) return;

        resultsDiv.innerHTML = '<div class="text-muted"><div class="spinner-border spinner-border-sm me-1"></div>Searching...</div>';

        try {
            const res = await window.API.apiCall(`/payments/lookup-by-phone?phone=${encodeURIComponent(phone)}`, 'GET');
            const students = res?.students ?? [];
            select.innerHTML = '<option value="">-- Select student --</option>';

            if (students.length === 0) {
                resultsDiv.innerHTML = '<div class="alert alert-warning py-2 mb-0">No students found for this phone number</div>';
                return;
            }

            select.innerHTML += students.map(s =>
                `<option value="${s.student_id}">${this.esc(s.first_name || '')} ${this.esc(s.last_name || '')} (${this.esc(s.admission_no || 'N/A')}) - ${this.esc(s.class_name || '')}</option>`
            ).join('');

            resultsDiv.innerHTML = `<div class="alert alert-success py-1 mb-0"><small>Found ${students.length} student(s)</small></div>`;
        } catch (err) {
            resultsDiv.innerHTML = '<div class="alert alert-danger py-2 mb-0">Lookup failed</div>';
        }
    },

    async doReconcile(form) {
        const data = {};
        new FormData(form).forEach((v, k) => { data[k] = v; });

        if (!data.student_id) {
            this.showNotification('Please select a student', 'warning');
            return;
        }

        try {
            await window.API.apiCall('/payments/reconcile-mpesa', 'POST', data);
            this.showNotification('Transaction reconciled and allocated to fees', 'success');
            bootstrap.Modal.getInstance(document.getElementById('dynamicModal'))?.hide();
            await this.loadData();
        } catch (err) {
            this.showNotification(err.message || 'Error during reconciliation', 'error');
        }
    },

    clearFilters() {
        ['searchTxn', 'filterDateFrom', 'filterDateTo'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.value = '';
        });
        this.state.filtered = [...this.state.transactions];
        this.renderTable();
    },

    refresh() {
        this.loadData();
    },

    showLoading() {
        const tbody = document.querySelector('#reconTable tbody');
        if (tbody) tbody.innerHTML = '<tr><td colspan="8" class="text-center py-4"><div class="spinner-border spinner-border-sm text-success me-2"></div>Loading...</td></tr>';
    },

    esc(str) {
        if (!str && str !== 0) return '';
        const d = document.createElement('div');
        d.textContent = str;
        return d.innerHTML;
    },

    showNotification(msg, type = 'info') {
        const alert = document.createElement('div');
        alert.className = `alert alert-${type === 'error' ? 'danger' : type} alert-dismissible fade show position-fixed top-0 end-0 m-3`;
        alert.style.zIndex = '9999';
        alert.innerHTML = `${msg}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
        document.body.appendChild(alert);
        setTimeout(() => alert.remove(), 4000);
    },

    showModal(title, bodyHtml, onShow) {
        let modal = document.getElementById('dynamicModal');
        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'dynamicModal';
            modal.className = 'modal fade';
            modal.tabIndex = -1;
            modal.innerHTML = '<div class="modal-dialog modal-lg"><div class="modal-content"><div class="modal-header"><h5 class="modal-title"></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"></div></div></div>';
            document.body.appendChild(modal);
        }
        modal.querySelector('.modal-title').textContent = title;
        modal.querySelector('.modal-body').innerHTML = bodyHtml;
        new bootstrap.Modal(modal).show();
        if (onShow) setTimeout(onShow, 300);
    },
};

document.addEventListener('DOMContentLoaded', () => MpesaReconciliationController.init());
