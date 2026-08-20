/**
 * Extra Charges — JS controller
 * Manages database-driven charges that appear on the fee structure printout.
 */
(function () {
    'use strict';

    const API_BASE = (window.APP_BASE || '') + '/api/finance';
    let charges = [];
    let studentTypes = [];
    let classes = [];
    let currentYearId = null;

    async function apiCall(method, path, body) {
        const opts = {
            method,
            headers: { 'Content-Type': 'application/json' },
        };
        if (body) opts.body = JSON.stringify(body);
        const res = await fetch(API_BASE + path, opts);
        const json = await res.json();
        if (!json.success) throw new Error(json.error || 'Request failed');
        return json.data || json;
    }

    async function loadFilters() {
        try {
            const res = await apiCall('GET', '/fee-structures-list?limit=100');
            const years = res.years || res.data?.years || [];
            const sel = document.getElementById('filterYear');
            sel.innerHTML = '<option value="">Current Year</option>';
            years.forEach(y => {
                const opt = document.createElement('option');
                opt.value = y.id;
                opt.textContent = y.year_code || y.year_name || y.id;
                sel.appendChild(opt);
            });
            sel.addEventListener('change', loadCharges);
        } catch (e) { /* silent */ }

        try {
            const stRes = await apiCall('GET', '/fee-types-list');
            studentTypes = stRes.student_types || stRes.data?.student_types || [];
        } catch (e) { studentTypes = []; }

        try {
            const clsRes = await apiCall('GET', '/fee-structures-list');
            classes = clsRes.classes || clsRes.data?.classes || [];
        } catch (e) { classes = []; }
    }

    async function loadCharges() {
        const yearId = document.getElementById('filterYear').value;
        const status = document.getElementById('filterStatus').value;
        const scope = document.getElementById('filterScope').value;
        currentYearId = yearId || null;

        let url = '/extra-charges?';
        if (yearId) url += 'academic_year=' + yearId + '&';
        if (status) url += 'status=' + status + '&';
        if (scope) url += 'scope=' + scope;

        try {
            const res = await apiCall('GET', url);
            charges = res.charges || [];
            renderTable();
        } catch (e) {
            document.getElementById('chargesBody').innerHTML =
                '<tr><td colspan="8" class="text-center text-danger py-3">Failed to load charges</td></tr>';
        }
    }

    function renderTable() {
        const tbody = document.getElementById('chargesBody');
        if (!charges.length) {
            tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">No extra charges found</td></tr>';
            return;
        }

        const freqLabels = { one_time: 'One Time', per_term: 'Per Term', per_year: 'Per Year' };
        const scopeLabels = { all: 'All Students', student_type: 'Student Type', class: 'Class', specific_students: 'Specific' };
        const statusBadges = {
            draft: '<span class="badge bg-secondary">Draft</span>',
            active: '<span class="badge bg-success">Active</span>',
            inactive: '<span class="badge bg-dark">Inactive</span>',
        };

        tbody.innerHTML = charges.map((c, i) => {
            const appliesTo = c.student_type_name || c.class_name || '—';
            const canEdit = c.status === 'draft';
            const canSubmit = c.status === 'draft';
            const canApprove = c.status !== 'active' && c.status !== 'inactive';
            const canDelete = c.status === 'draft';

            let actions = '';
            if (canEdit) actions += `<button class="btn btn-outline-primary btn-sm me-1" onclick="window.extraCharges.edit(${c.id})" title="Edit"><i class="bi bi-pencil"></i></button>`;
            if (canSubmit) actions += `<button class="btn btn-outline-info btn-sm me-1" onclick="window.extraCharges.submit(${c.id})" title="Submit for Review"><i class="bi bi-send"></i></button>`;
            if (canApprove) actions += `<button class="btn btn-outline-success btn-sm me-1" onclick="window.extraCharges.approve(${c.id})" title="Approve"><i class="bi bi-check-lg"></i></button>`;
            if (canApprove) actions += `<button class="btn btn-outline-warning btn-sm me-1" onclick="window.extraCharges.reject(${c.id})" title="Reject"><i class="bi bi-x-lg"></i></button>`;
            if (canDelete) actions += `<button class="btn btn-outline-danger btn-sm" onclick="window.extraCharges.remove(${c.id})" title="Delete"><i class="bi bi-trash"></i></button>`;

            return `<tr>
                <td>${i + 1}</td>
                <td><strong>${escHtml(c.name)}</strong>${c.description ? '<br><small class="text-muted">' + escHtml(c.description) + '</small>' : ''}</td>
                <td class="text-end">${Number(c.amount).toLocaleString()}</td>
                <td>${freqLabels[c.charge_frequency] || c.charge_frequency}</td>
                <td>${scopeLabels[c.scope] || c.scope}</td>
                <td>${escHtml(appliesTo)}</td>
                <td>${statusBadges[c.status] || c.status}</td>
                <td>${actions}</td>
            </tr>`;
        }).join('');
    }

    function escHtml(str) {
        const div = document.createElement('div');
        div.textContent = str || '';
        return div.innerHTML;
    }

    async function populateScopeDetail() {
        const scope = document.getElementById('chargeScope').value;
        const group = document.getElementById('scopeDetailGroup');
        const label = document.getElementById('scopeDetailLabel');
        const select = document.getElementById('scopeDetailId');

        if (scope === 'all' || scope === 'specific_students') {
            group.style.display = 'none';
            return;
        }

        group.style.display = '';
        select.innerHTML = '';

        if (scope === 'student_type') {
            label.textContent = 'Student Type';
            select.innerHTML = '<option value="">Select Type</option>';
            try {
                const res = await apiCall('GET', '/fee-types-list');
                const types = res.student_types || res.data?.student_types || [];
                types.forEach(t => {
                    select.innerHTML += `<option value="${t.id}">${escHtml(t.name)}</option>`;
                });
            } catch (e) { /* silent */ }
        } else if (scope === 'class') {
            label.textContent = 'Class';
            select.innerHTML = '<option value="">Select Class</option>';
            try {
                const res = await apiCall('GET', '/fee-structures-list');
                const cls = res.classes || res.data?.classes || [];
                cls.forEach(c => {
                    select.innerHTML += `<option value="${c.id}">${escHtml(c.name)}</option>`;
                });
            } catch (e) { /* silent */ }
        }
    }

    function openCreateModal() {
        document.getElementById('chargeModalTitle').textContent = 'New Extra Charge';
        document.getElementById('chargeForm').reset();
        document.getElementById('chargeId').value = '';
        document.getElementById('scopeDetailGroup').style.display = 'none';
        if (currentYearId) {
            // year is pre-set from filter
        }
        new bootstrap.Modal(document.getElementById('chargeModal')).show();
    }

    async function saveCharge() {
        const id = document.getElementById('chargeId').value;
        const data = {
            name: document.getElementById('chargeName').value.trim(),
            amount: parseFloat(document.getElementById('chargeAmount').value) || 0,
            charge_frequency: document.getElementById('chargeFrequency').value,
            scope: document.getElementById('chargeScope').value,
            description: document.getElementById('chargeDescription').value.trim(),
            display_order: parseInt(document.getElementById('chargeDisplayOrder').value) || 0,
        };
        if (!data.name) return showNotification('Name is required', 'error');
        if (data.amount <= 0) return showNotification('Amount must be greater than zero', 'error');

        const scopeDetail = document.getElementById('scopeDetailId').value;
        if (data.scope === 'student_type' && scopeDetail) data.student_type_id = parseInt(scopeDetail);
        if (data.scope === 'class' && scopeDetail) data.class_id = parseInt(scopeDetail);

        const yearId = document.getElementById('filterYear').value;
        if (yearId) data.academic_year_id = parseInt(yearId);

        try {
            if (id) {
                await apiCall('PUT', '/extra-charges/' + id, data);
                showNotification('Charge updated', 'success');
            } else {
                await apiCall('POST', '/extra-charges', data);
                showNotification('Charge created', 'success');
            }
            bootstrap.Modal.getInstance(document.getElementById('chargeModal')).hide();
            loadCharges();
        } catch (e) {
            showNotification(e.message || 'Save failed', 'error');
        }
    }

    async function editCharge(id) {
        try {
            const res = await apiCall('GET', '/extra-charges/' + id);
            const c = res.charge || {};
            document.getElementById('chargeModalTitle').textContent = 'Edit Extra Charge';
            document.getElementById('chargeId').value = c.id;
            document.getElementById('chargeName').value = c.name || '';
            document.getElementById('chargeAmount').value = c.amount || '';
            document.getElementById('chargeFrequency').value = c.charge_frequency || 'one_time';
            document.getElementById('chargeScope').value = c.scope || 'all';
            document.getElementById('chargeDescription').value = c.description || '';
            document.getElementById('chargeDisplayOrder').value = c.display_order || 0;
            await populateScopeDetail();
            if (c.student_type_id) document.getElementById('scopeDetailId').value = c.student_type_id;
            if (c.class_id) document.getElementById('scopeDetailId').value = c.class_id;
            new bootstrap.Modal(document.getElementById('chargeModal')).show();
        } catch (e) {
            showNotification('Failed to load charge', 'error');
        }
    }

    async function submitCharge(id) {
        try {
            await apiCall('POST', '/extra-charges/' + id + '/submit');
            showNotification('Submitted for review', 'success');
            loadCharges();
        } catch (e) {
            showNotification(e.message || 'Submit failed', 'error');
        }
    }

    async function approveCharge(id) {
        try {
            await apiCall('POST', '/extra-charges/' + id + '/approve');
            showNotification('Charge approved', 'success');
            loadCharges();
        } catch (e) {
            showNotification(e.message || 'Approve failed', 'error');
        }
    }

    function rejectCharge(id) {
        document.getElementById('rejectChargeId').value = id;
        document.getElementById('rejectNotes').value = '';
        new bootstrap.Modal(document.getElementById('rejectModal')).show();
    }

    async function confirmReject() {
        const id = document.getElementById('rejectChargeId').value;
        const notes = document.getElementById('rejectNotes').value.trim();
        try {
            await apiCall('POST', '/extra-charges/' + id + '/reject', { notes });
            showNotification('Charge rejected', 'success');
            bootstrap.Modal.getInstance(document.getElementById('rejectModal')).hide();
            loadCharges();
        } catch (e) {
            showNotification(e.message || 'Reject failed', 'error');
        }
    }

    async function deleteCharge(id) {
        if (!confirm('Remove this extra charge?')) return;
        try {
            await apiCall('DELETE', '/extra-charges/' + id);
            showNotification('Charge removed', 'success');
            loadCharges();
        } catch (e) {
            showNotification(e.message || 'Delete failed', 'error');
        }
    }

    function showNotification(msg, type) {
        if (typeof window.showNotification === 'function') {
            window.showNotification(msg, type);
        } else {
            alert(msg);
        }
    }

    function init() {
        loadFilters().then(loadCharges);

        document.getElementById('btnCreateCharge').addEventListener('click', openCreateModal);
        document.getElementById('btnSaveCharge').addEventListener('click', saveCharge);
        document.getElementById('btnClearFilters').addEventListener('click', () => {
            document.getElementById('filterYear').value = '';
            document.getElementById('filterStatus').value = '';
            document.getElementById('filterScope').value = '';
            loadCharges();
        });
        document.getElementById('filterStatus').addEventListener('change', loadCharges);
        document.getElementById('filterScope').addEventListener('change', loadCharges);
        document.getElementById('chargeScope').addEventListener('change', populateScopeDetail);
        document.getElementById('btnConfirmReject').addEventListener('click', confirmReject);
    }

    window.extraCharges = { edit: editCharge, submit: submitCharge, approve: approveCharge, reject: rejectCharge, remove: deleteCharge };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
