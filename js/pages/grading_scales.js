/**
 * Grading Scales Controller
 * Manages the DB-driven grade boundaries (grading_scales + grade_rules).
 * After any mutation the shared GradingScale cache is invalidated so every
 * page picks up the new ranges/grades immediately.
 */
function escapeHtml(value) {
    const div = document.createElement('div');
    div.textContent = value === null || value === undefined ? '' : String(value);
    return div.innerHTML;
}

const GradingScalesCtrl = {
    initialized: false,
    state: {
        scales: [],           // [{ scale: {...}, rules: [...] }]
        activeScaleId: null,
    },

    async init() {
        if (this.initialized) return;
        await window.AuthContext?.ready?.();
        if (!window.AuthContext?.isAuthenticated?.()) {
            window.location.href = (window.APP_BASE || '') + '/index.php';
            return;
        }
        this.setupEventListeners();
        await this.loadScales();
        this.initialized = true;
    },

    setupEventListeners() {
        document.getElementById('saveScaleBtn')?.addEventListener('click', () => this.saveScale());
        document.getElementById('saveRuleBtn')?.addEventListener('click', () => this.saveRule());
    },

    async loadScales() {
        try {
            const res = await callAPI('/api/academic/grading-scale?all=1');
            const list = Array.isArray(res) ? res : (res?.data || []);
            this.state.scales = list;
            const active = list.find(item => item.scale?.status === 'active') || list[0];
            this.state.activeScaleId = active ? active.scale.id : null;
            this.renderScaleFilter();
            if (this.state.activeScaleId) {
                this.selectScale();
            } else {
                document.getElementById('ruleTableBody').innerHTML =
                    '<tr><td colspan="8" class="text-center text-muted py-4">No grading scales defined. Create one to start.</td></tr>';
            }
        } catch (e) {
            console.error('Failed to load grading scales', e);
            showNotification('Failed to load grading scales.', 'error');
        }
    },

    renderScaleFilter() {
        const sel = document.getElementById('scaleFilter');
        sel.innerHTML = this.state.scales.map(item => {
            const s = item.scale;
            const label = s.status === 'active' ? s.name + ' (active)' : s.name;
            return `<option value="${s.id}" ${Number(s.id) === Number(this.state.activeScaleId) ? 'selected' : ''}>${escapeHtml(label)}</option>`;
        }).join('');
    },

    selectScale() {
        const sel = document.getElementById('scaleFilter');
        this.state.activeScaleId = parseInt(sel.value) || null;
        if (!this.state.activeScaleId) return;
        this.renderScaleDetails();
        this.renderRules();
    },

    getActiveItem() {
        return this.state.scales.find(item => Number(item.scale.id) === Number(this.state.activeScaleId)) || null;
    },

    renderScaleDetails() {
        const item = this.getActiveItem();
        const s = item ? item.scale : null;
        const el = document.getElementById('scaleDetails');
        document.getElementById('scaleNameBadge').textContent = s ? s.name : '—';
        if (!s) {
            el.innerHTML = 'No scale selected.';
            return;
        }
        el.innerHTML = `
            <div class="mb-1"><strong>Name:</strong> ${escapeHtml(s.name)}</div>
            <div class="mb-1"><strong>Range:</strong> ${s.min_mark} – ${s.max_mark}</div>
            <div class="mb-1"><strong>Status:</strong> <span class="badge ${s.status === 'active' ? 'bg-success' : 'bg-secondary'}">${escapeHtml(s.status)}</span></div>
            <div class="mb-1"><strong>Grades:</strong> ${item.rules.length}</div>
            ${s.description ? `<div class="mb-1"><strong>Notes:</strong> ${escapeHtml(s.description)}</div>` : ''}
            <button class="btn btn-outline-secondary btn-sm mt-2" onclick="GradingScalesCtrl.openScaleModal()">
                <i class="bi bi-pencil me-1"></i> Edit Scale
            </button>`;
    },

    renderRules() {
        const item = this.getActiveItem();
        const tbody = document.getElementById('ruleTableBody');
        if (!item || !item.rules.length) {
            tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">No grades defined for this scale. Add one.</td></tr>';
            return;
        }
        tbody.innerHTML = item.rules.map((r, i) => {
            const band = GradingScale.band(r.grade_code) || 'BE';
            const color = { EE: 'success', ME: 'primary', AE: 'warning', BE: 'danger' }[band] || 'secondary';
            return `<tr>
                <td>${i + 1}</td>
                <td><span class="badge bg-${color}">${escapeHtml(r.grade_code)}</span></td>
                <td>${escapeHtml(r.grade_name)}</td>
                <td>${r.min_mark}% – ${r.max_mark}%</td>
                <td>${r.grade_points}</td>
                <td>${escapeHtml(r.performance_level || '—')}</td>
                <td>${r.sort_order}</td>
                <td>
                    <button class="btn btn-sm btn-outline-primary me-1" title="Edit" onclick="GradingScalesCtrl.openRuleModal(${r.id})">
                        <i class="bi bi-pencil"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-danger" title="Delete" onclick="GradingScalesCtrl.deleteRule(${r.id})">
                        <i class="bi bi-trash"></i>
                    </button>
                </td>
            </tr>`;
        }).join('');
    },

    openScaleModal(id) {
        const item = id ? this.state.scales.find(x => Number(x.scale.id) === Number(id)) : null;
        const s = item ? item.scale : null;
        document.getElementById('scaleForm')?.reset();
        document.getElementById('scaleId').value = s ? s.id : '';
        document.getElementById('scaleModalLabel').textContent = s ? 'Edit Grading Scale' : 'New Grading Scale';
        if (s) {
            document.getElementById('scaleName').value = s.name || '';
            document.getElementById('scaleMin').value = s.min_mark;
            document.getElementById('scaleMax').value = s.max_mark;
            document.getElementById('scaleStatus').value = s.status || 'active';
            document.getElementById('scaleDescription').value = s.description || '';
        } else {
            document.getElementById('scaleStatus').value = 'active';
        }
        new bootstrap.Modal(document.getElementById('scaleModal')).show();
    },

    async saveScale() {
        const id = document.getElementById('scaleId').value;
        const payload = {
            name: document.getElementById('scaleName').value.trim(),
            min_mark: parseFloat(document.getElementById('scaleMin').value) || 0,
            max_mark: parseFloat(document.getElementById('scaleMax').value) || 100,
            status: document.getElementById('scaleStatus').value,
            description: document.getElementById('scaleDescription').value.trim() || null,
        };
        if (!payload.name) return showNotification('Scale name is required.', 'error');
        try {
            if (id) {
                await callAPI('/api/academic/grading-scale/' + id, 'PUT', payload);
                showNotification('Grading scale updated.', 'success');
            } else {
                const res = await callAPI('/api/academic/grading-scale', 'POST', payload);
                this.state.activeScaleId = res?.data?.id || res?.id;
                showNotification('Grading scale created.', 'success');
            }
            bootstrap.Modal.getInstance(document.getElementById('scaleModal'))?.hide();
            GradingScale.invalidate();
            await this.loadScales();
        } catch (e) {
            console.error(e);
            showNotification('Failed to save grading scale.', 'error');
        }
    },

    openRuleModal(id) {
        const item = this.getActiveItem();
        if (!item && !id) return showNotification('Select a grading scale first.', 'error');
        let rule = null;
        if (id) {
            rule = item.rules.find(r => Number(r.id) === Number(id)) || null;
        }
        document.getElementById('ruleForm')?.reset();
        document.getElementById('ruleId').value = rule ? rule.id : '';
        document.getElementById('ruleModalLabel').textContent = rule ? 'Edit Grade' : 'Add Grade';
        if (rule) {
            document.getElementById('ruleCode').value = rule.grade_code || '';
            document.getElementById('ruleName').value = rule.grade_name || '';
            document.getElementById('ruleMin').value = rule.min_mark;
            document.getElementById('ruleMax').value = rule.max_mark;
            document.getElementById('rulePoints').value = rule.grade_points;
            document.getElementById('ruleSort').value = rule.sort_order;
            document.getElementById('ruleLevel').value = rule.performance_level || 'Meeting Expectation';
            document.getElementById('ruleDesc').value = rule.description || '';
        } else {
            document.getElementById('ruleSort').value = item.rules.length + 1;
        }
        new bootstrap.Modal(document.getElementById('ruleModal')).show();
    },

    async saveRule() {
        const id = document.getElementById('ruleId').value;
        const scaleId = this.getActiveItem().scale.id;
        const payload = {
            scale_id: scaleId,
            grade_code: document.getElementById('ruleCode').value.trim(),
            grade_name: document.getElementById('ruleName').value.trim(),
            min_mark: parseFloat(document.getElementById('ruleMin').value),
            max_mark: parseFloat(document.getElementById('ruleMax').value),
            grade_points: parseFloat(document.getElementById('rulePoints').value) || 0,
            sort_order: parseInt(document.getElementById('ruleSort').value) || 1,
            performance_level: document.getElementById('ruleLevel').value,
            description: document.getElementById('ruleDesc').value.trim() || null,
        };
        if (!payload.grade_code || !payload.grade_name) return showNotification('Grade code and name are required.', 'error');
        if (isNaN(payload.min_mark) || isNaN(payload.max_mark)) return showNotification('Enter valid min and max marks.', 'error');
        try {
            if (id) {
                await callAPI('/api/academic/grade-rules/' + id, 'PUT', payload);
                showNotification('Grade updated.', 'success');
            } else {
                await callAPI('/api/academic/grade-rules', 'POST', payload);
                showNotification('Grade added.', 'success');
            }
            bootstrap.Modal.getInstance(document.getElementById('ruleModal'))?.hide();
            GradingScale.invalidate();
            await this.loadScales();
        } catch (e) {
            console.error(e);
            showNotification('Failed to save grade.', 'error');
        }
    },

    async deleteRule(id) {
        const confirmed = await window.confirmAction('Confirm Deletion', 'Delete this grade range? This affects every report that uses it.', { confirmText: 'Delete', danger: true });
        if (!confirmed) return;
        try {
            await callAPI('/api/academic/grade-rules/' + id, 'DELETE');
            showNotification('Grade deleted.', 'success');
            GradingScale.invalidate();
            await this.loadScales();
        } catch (e) {
            console.error(e);
            showNotification('Failed to delete grade.', 'error');
        }
    },
};
