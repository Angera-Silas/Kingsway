function escapeHtml(value) {
    const div = document.createElement('div');
    div.textContent = value === null || value === undefined ? '' : String(value);
    return div.innerHTML;
}

const RubricsController = {
    initialized: false,
    state: {
        rubrics: [],
        tools: [],
    },

    async init() {
        if (this.initialized) return;
        await window.AuthContext?.ready?.();
        if (!window.AuthContext?.isAuthenticated?.()) {
            window.location.href = (window.APP_BASE || '') + '/index.php';
            return;
        }
        if (!window.AuthContext?.hasPermission?.('academics_manage') &&
            !window.AuthContext?.hasPermission?.('assessments_rubric_manage') &&
            !window.AuthContext?.hasPermission?.('assessments_manage')) {
            return showNotification('Access denied.', 'error');
        }
        this.setupEventListeners();
        await this.loadToolReferences();
        await this.loadTools();
        await this.loadRubrics();
        this.initialized = true;
    },

    setupEventListeners() {
        document.getElementById('toolFilter')?.addEventListener('change', () => this.loadRubrics());
        document.getElementById('searchFilter')?.addEventListener('input', () => this.render());
        document.getElementById('saveRubricBtn')?.addEventListener('click', () => this.save());
        document.getElementById('saveToolBtn')?.addEventListener('click', () => this.saveTool());
    },

    async loadToolReferences() {
        try {
            const [types, areas] = await Promise.all([
                callAPI('/api/academic/assessment-classifications'),
                callAPI('/api/academic/learning-areas')
            ]);
            const typeRows = Array.isArray(types) ? types : (types?.data || []);
            const areaRows = Array.isArray(areas) ? areas : (areas?.data || []);
            document.getElementById('toolType').innerHTML = '<option value="">Select type</option>' +
                typeRows.map(t => `<option value="${t.id}">${escapeHtml(t.name)}</option>`).join('');
            document.getElementById('toolArea').innerHTML = '<option value="">Select learning area</option>' +
                areaRows.map(a => `<option value="${a.id}">${escapeHtml(a.name)}</option>`).join('');
        } catch (e) {
            console.error('Failed to load assessment tool references', e);
        }
    },

    openToolModal() {
        document.getElementById('assessmentToolForm')?.reset();
        new bootstrap.Modal(document.getElementById('assessmentToolModal')).show();
    },

    async saveTool() {
        const payload = {
            tool_name: document.getElementById('toolName').value.trim(),
            tool_code: document.getElementById('toolCode').value.trim(),
            assessment_type_id: parseInt(document.getElementById('toolType').value) || 0,
            learning_area_id: parseInt(document.getElementById('toolArea').value) || 0,
            grade_level: document.getElementById('toolGrade').value.trim(),
            description: document.getElementById('toolDescription').value.trim()
        };
        if (!payload.tool_name || !payload.assessment_type_id || !payload.learning_area_id) {
            return showNotification('Tool name, assessment type, and learning area are required.', 'warning');
        }
        try {
            await callAPI('/api/academic/assessment-tools', 'POST', payload);
            showNotification('Assessment tool created.');
            bootstrap.Modal.getInstance(document.getElementById('assessmentToolModal'))?.hide();
            await this.loadTools();
        } catch (e) {
            showNotification(e.message || 'Failed to create assessment tool.', 'error');
        }
    },

    async loadTools() {
        try {
            const res = await callAPI('/api/academic/assessment-tools');
            this.state.tools = Array.isArray(res) ? res : (res?.data || []);
            this.populateToolSelects();
        } catch (e) {
            console.error('Failed to load tools', e);
        }
    },

    populateToolSelects() {
        const toolFilter = document.getElementById('toolFilter');
        const rubricTool = document.getElementById('rubricTool');
        if (!toolFilter || !rubricTool) return;
        toolFilter.innerHTML = '<option value="">All Assessment Tools</option>';
        rubricTool.innerHTML = '<option value="">Select Tool</option>';
        this.state.tools.forEach(t => {
            toolFilter.innerHTML += `<option value="${t.id}">${escapeHtml(t.tool_name)}</option>`;
            rubricTool.innerHTML += `<option value="${t.id}">${escapeHtml(t.tool_name)}</option>`;
        });
    },

    async loadRubrics() {
        try {
            const toolId = document.getElementById('toolFilter')?.value || '';
            const url = toolId ? `/api/academic/assessment-rubrics?tool_id=${toolId}` : '/api/academic/assessment-rubrics';
            const res = await callAPI(url);
            this.state.rubrics = Array.isArray(res) ? res : (res?.data || []);
            this.render();
        } catch (e) {
            console.error('Failed to load rubrics', e);
        }
    },

    render() {
        const tbody = document.getElementById('rubricTableBody');
        if (!tbody) return;
        const search = (document.getElementById('searchFilter')?.value || '').toLowerCase();
        let rubrics = this.state.rubrics;
        if (search) {
            rubrics = rubrics.filter(r =>
                (r.criteria_name || '').toLowerCase().includes(search) ||
                (r.tool_name || '').toLowerCase().includes(search)
            );
        }
        if (!rubrics.length) {
            tbody.innerHTML = '<tr><td colspan="10" class="text-center text-muted py-4">No rubrics found.</td></tr>';
            return;
        }
        tbody.innerHTML = rubrics.map((r, i) => `
            <tr>
                <td>${i + 1}</td>
                <td>${escapeHtml(r.criteria_name)}</td>
                <td>${escapeHtml(r.tool_name || '—')}</td>
                <td class="text-muted small">${escapeHtml(r.level_1_descriptor || '—')}</td>
                <td class="text-muted small">${escapeHtml(r.level_2_descriptor || '—')}</td>
                <td class="text-muted small">${escapeHtml(r.level_3_descriptor || '—')}</td>
                <td class="text-muted small">${escapeHtml(r.level_4_descriptor || '—')}</td>
                <td>${r.points_per_level || 0}</td>
                <td>${r.sort_order || 1}</td>
                <td>
                    <button class="btn btn-sm btn-outline-primary me-1" onclick="RubricsController.openModal(${r.id})">
                        <i class="bi bi-pencil"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-danger" onclick="RubricsController.delete(${r.id})">
                        <i class="bi bi-trash"></i>
                    </button>
                </td>
            </tr>
        `).join('');
    },

    openModal(id) {
        const modalEl = document.getElementById('rubricModal');
        if (!modalEl) return;
        const modal = new bootstrap.Modal(modalEl);
        document.getElementById('rubricId').value = '';
        document.getElementById('rubricCriteria').value = '';
        document.getElementById('rubricL1').value = '';
        document.getElementById('rubricL2').value = '';
        document.getElementById('rubricL3').value = '';
        document.getElementById('rubricL4').value = '';
        document.getElementById('rubricPoints').value = '0';
        document.getElementById('rubricSort').value = '1';
        document.getElementById('rubricTool').value = '';
        document.getElementById('rubricModalLabel').textContent = 'Add Rubric Criterion';

        if (id) {
            const r = this.state.rubrics.find(x => x.id === id);
            if (r) {
                document.getElementById('rubricId').value = r.id;
                document.getElementById('rubricCriteria').value = r.criteria_name;
                document.getElementById('rubricL1').value = r.level_1_descriptor || '';
                document.getElementById('rubricL2').value = r.level_2_descriptor || '';
                document.getElementById('rubricL3').value = r.level_3_descriptor || '';
                document.getElementById('rubricL4').value = r.level_4_descriptor || '';
                document.getElementById('rubricPoints').value = r.points_per_level || 0;
                document.getElementById('rubricSort').value = r.sort_order || 1;
                document.getElementById('rubricTool').value = r.tool_id || '';
                document.getElementById('rubricModalLabel').textContent = 'Edit Rubric Criterion';
            }
        }
        modal.show();
    },

    async save() {
        const id = document.getElementById('rubricId').value;
        const toolId = document.getElementById('rubricTool').value;
        const criteriaName = document.getElementById('rubricCriteria').value.trim();
        if (!toolId || !criteriaName) return showNotification('Tool and criteria name are required.', 'warning');

        const payload = {
            tool_id: parseInt(toolId),
            criteria_name: criteriaName,
            level_1_descriptor: document.getElementById('rubricL1').value.trim(),
            level_2_descriptor: document.getElementById('rubricL2').value.trim(),
            level_3_descriptor: document.getElementById('rubricL3').value.trim(),
            level_4_descriptor: document.getElementById('rubricL4').value.trim(),
            points_per_level: parseInt(document.getElementById('rubricPoints').value) || 0,
            sort_order: parseInt(document.getElementById('rubricSort').value) || 1,
        };

        try {
            const method = id ? 'PUT' : 'POST';
            const url = id ? `/api/academic/assessment-rubrics/${id}` : '/api/academic/assessment-rubrics';
            await callAPI(url, method, payload);
            showNotification(id ? 'Rubric criterion updated.' : 'Rubric criterion saved.');
            bootstrap.Modal.getInstance(document.getElementById('rubricModal'))?.hide();
            await this.loadRubrics();
        } catch (e) {
            showNotification(e.message || 'Failed to save rubric.', 'error');
        }
    },

    async delete(id) {
        if (!confirm('Delete this rubric criterion? This action cannot be undone.')) return;
        try {
            await callAPI(`/api/academic/assessment-rubrics/${id}`, 'DELETE');
            showNotification('Rubric deleted.');
            await this.loadRubrics();
        } catch (e) {
            showNotification(e.message || 'Failed to delete.', 'error');
        }
    },
};

document.addEventListener('DOMContentLoaded', () => RubricsController.init());
