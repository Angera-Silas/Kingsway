/**
 * Staff Management Controller
 * Handles manage_staff.php
 * Uses existing api.js JWT authentication
 */
const StaffProductionUI = {
    initialized: false,
    initializationPromise: null,
    eventsBound: false,
    staffLoadPromise: null,
    staffRequestSequence: 0,
    departmentsLoadPromise: null,
    rolesLoadPromise: null,
    searchTimer: null,
    managingStaffIds: [],
    selectedRoleIds: [],
    staffWizardSteps: [
        'staff-tab-employment',
        'staff-tab-personal',
        'staff-tab-payroll',
        'staff-tab-details',
        'staff-tab-experience',
        'staff-tab-final',
        'staff-tab-review'
    ],
    staffWizardStep: 0,
    staffDraftTimer: null,
    staffCameraStream: null,
    staffAutoFilledEmail: '',
    staffAutoFilledPhone: '',
    currentEditingStaff: null,

    state: {
        staff: [],
        filteredStaff: [],
        departments: [],
        roles: [],
        staffTypes: [],
        staffCategories: [],
        learningAreas: [],
        positions: [],
        pagination: null,
        currentPage: 1,
        pageSize: 25,
        selectedStaffIds: new Set(),
        currentFilters: {
            search: '',
            department: null,
            staff_type_id: null,
            status: null
        }
    },

    async init() {
        if (this.initializationPromise) {
            return this.initializationPromise;
        }

        this.initializationPromise = this._initialize();

        try {
            await this.initializationPromise;
            return this;
        } catch (error) {
            this.initializationPromise = null;
            throw error;
        }
    },

    async _initialize() {
        if (this.initialized) {
            return this;
        }


        if (window.AuthContext?.ready) {
            await window.AuthContext.ready();
        } else if (window.AuthContext?.initialize) {
            await window.AuthContext.initialize();
        }

        if (window.StaffAccess?.init) {
            await window.StaffAccess.init();
        }

        if (!window.API?.staff) {
            throw new Error('Staff API is unavailable.');
        }

        if (!window.AuthContext?.isAuthenticated?.()) {
            this.showToast('Please log in to access this page', 'error', 'Authentication Required');
            window.setTimeout(() => {
                window.location.replace(`${window.APP_BASE || ''}/index.php`);
            }, 800);
            return this;
        }

        if (!this.canViewDirectory()) {
            this.showToast('You do not have permission to view staff', 'error', 'Access denied');
            this.renderAccessDenied();
            return this;
        }

        this.setupEventListeners();
        this.applyAccessUi();
        await this.loadInitialData();
        this.applyAccessUi();
        this.applyPageContext();

        this.initialized = true;

        return this;
    },

    canViewDirectory() {
        return (window.StaffAccess && StaffAccess.can('staff.directory.view')) || window.AuthContext?.canView?.('staff');
    },

    canManageDirectory() {
        return (window.StaffAccess && StaffAccess.can('staff.directory.manage')) || window.AuthContext?.canCreate?.('staff') || window.AuthContext?.canEdit?.('staff');
    },

    canManageStaffRoles() {
        return this.canManageDirectory() && Boolean(window.StaffAccess?.can?.('staff.roles.manage'));
    },

    canDeleteDirectory() {
        // Deletion is a directory-management action. Backend authorization is
        // authoritative; the UI only hides it when explicitly denied.
        return this.canManageDirectory() && window.AuthContext?.canDelete?.('staff') !== false;
    },

    canExportDirectory() {
        return window.AuthContext?.canExport?.('staff');
    },

    showToast(message, type = 'info', title = 'Notification') {
        if (window.showNotification) {
            window.showNotification(message, type);
            return;
        }

        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type === 'success' ? 'success' : type === 'error' ? 'danger' : type} alert-dismissible fade show`;
        alertDiv.innerHTML = `
            <strong>${this.escapeHtml(title)}</strong> ${this.escapeHtml(message)}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        document.body.insertBefore(alertDiv, document.body.firstChild);
        window.setTimeout(() => alertDiv.remove(), 4000);
    },

    renderAccessDenied() {
        const host = document.querySelector('[data-staff-directory-page]');
        if (!host) return;
        host.innerHTML = `
            <div class="alert alert-danger m-4">
                <h5 class="alert-heading">Access denied</h5>
                <p class="mb-0">You do not have permission to view staff.</p>
            </div>
        `;
    },

    applyAccessUi() {
        const addBtn = document.getElementById('addStaffBtn');
        if (addBtn) addBtn.hidden = !this.canManageDirectory();

        const exportBtn = document.getElementById('exportStaffBtn');
        if (exportBtn) exportBtn.hidden = !this.canExportDirectory();

        const printBtn = document.getElementById('printStaffBtn');
        if (printBtn) printBtn.hidden = !(window.AuthContext?.canPrint?.('staff') ?? true);

        const importBtn = document.getElementById('importStaffBtn');
        if (importBtn) importBtn.hidden = !this.canManageDirectory();

        this.applyRoleLayout();

        if (window.StaffAccess) StaffAccess.apply(document);
    },

    getRoles() {
        const contextRoles = window.StaffAccess?.getContext?.().roles || [];
        const user = window.AuthContext?.getUser?.() || {};
        const userRoles = user.roles || user.role_names || (user.role ? [user.role] : []);
        return [...contextRoles, ...userRoles]
            .map(role => String(role.name || role.role_name || role).toLowerCase().replace(/_/g, ' '))
            .filter(Boolean);
    },

    hasRole(fragment) {
        return this.getRoles().some(role => role.includes(fragment));
    },

    getRoleMode() {
        if (this.canManageDirectory() || this.hasRole('school administrator') || this.hasRole('system administrator')) {
            return 'operations';
        }

        if (this.hasRole('director')) return 'director';
        if (this.hasRole('headteacher')) return 'headteacher';
        if (this.hasRole('deputy head')) return 'deputy';
        if (this.hasRole('accountant') || this.hasRole('bursar')) return 'finance';

        return 'directory';
    },

    getLayoutConfig() {
        const configs = {
            operations: {
                title: 'Staff Operations',
                description: 'Full HR directory with onboarding, employment, and record actions',
                cards: ['total', 'active', 'teaching', 'non_teaching'],
                columns: ['staff_no', 'name', 'department', 'roles', 'contact', 'actions'],
                actions: ['view', 'edit']
            },
            director: {
                title: 'Staff Oversight',
                description: 'Leadership view of staffing levels, appointments, and workforce status',
                cards: ['total', 'active', 'teaching', 'on_leave'],
                columns: ['name', 'department', 'type', 'position', 'contact', 'status', 'actions'],
                actions: ['view', 'performance', 'workload']
            },
            headteacher: {
                title: 'Teaching Staff Oversight',
                description: 'Academic leadership view focused on teachers, workload, attendance, and performance',
                cards: ['teaching', 'active', 'on_leave', 'departments'],
                columns: ['name', 'department', 'type', 'position', 'status', 'actions'],
                actions: ['view', 'performance', 'workload']
            },
            deputy: {
                title: 'Staff Oversight',
                description: 'Oversight view for teaching staff, performance, attendance, and discipline workflows',
                cards: ['teaching', 'active', 'on_leave'],
                columns: ['name', 'department', 'type', 'position', 'status', 'actions'],
                actions: ['view', 'performance']
            },
            finance: {
                title: 'Staff Payroll Directory',
                description: 'Payroll-focused staff view with employment and payment readiness context',
                cards: ['total', 'active', 'payroll_ready', 'missing_payroll'],
                columns: ['staff_no', 'name', 'department', 'position', 'payroll', 'status', 'actions'],
                actions: ['view']
            },
            directory: {
                title: 'Staff Directory',
                description: 'Read-only contact and department directory',
                cards: ['total', 'active'],
                columns: ['name', 'department', 'position', 'status'],
                actions: []
            }
        };

        return configs[this.getRoleMode()] || configs.directory;
    },

    applyPageChrome() {
        const config = this.getLayoutConfig();
        const header = document.querySelector('[data-staff-directory-page] .page-header h4');
        const description = document.querySelector('[data-staff-directory-page] .page-header p');
        if (header) {
            header.innerHTML = `<i class="fas fa-chalkboard-teacher me-2"></i>${this.escapeHtml(config.title)}`;
        }
        if (description) {
            description.textContent = config.description;
        }
    },

    applyRoleLayout() {
        const container = document.querySelector('[data-staff-directory-page]');
        if (container) {
            container.dataset.roleMode = this.getRoleMode();
        }

        this.applyPageChrome();
        this.renderTableHeader();
    },

    async loadInitialData() {
        await Promise.all([
            this.loadStaff(),
            this.loadDepartments(),
            this.loadRoles(),
            this.loadStaffClassifications(),
            this.loadStaffFormLookups()
        ]);
        if (this.canManageDirectory()) await this.loadSetupCatalogs();
    },

    async loadStaffFormLookups() {
        if (!this.canManageDirectory()) return [];
        try {
            const response = await window.API.staffMigration.referenceData();
            const payload = this.extractPayload(response) || {};
            this.state.learningAreas = Array.isArray(payload.learning_areas) ? payload.learning_areas : [];
            if (Array.isArray(payload.positions)) {
                this.state.positions = payload.positions;
                this.populatePositionOptions();
            }
            this.populateLearningAreaDropdowns([], '');
            return this.state.learningAreas;
        } catch (error) {
            console.error('[StaffProductionUI] Failed to load staff form lookups:', error);
            return [];
        }
    },

    populatePositionOptions() {
        const input = document.getElementById('staffPositionOptions');
        if (!input) return;
        input.innerHTML = this.state.positions.map(position =>
            `<option value="${this.escapeHtml(position.name || '')}"></option>`
        ).join('');
    },

    populateLearningAreaDropdowns(selectedIds = [], primaryId = '') {
        const multi = document.getElementById('learningAreas');
        const primary = document.getElementById('primaryLearningArea');
        const selected = new Set((selectedIds || []).map(id => Number(id)));
        const options = this.state.learningAreas.map(area =>
            `<option value="${Number(area.id)}"${selected.has(Number(area.id)) ? ' selected' : ''}>${this.escapeHtml(area.name || '')}</option>`
        ).join('');
        if (multi) multi.innerHTML = options;
        if (primary) {
            primary.innerHTML = '<option value="">None</option>' +
                this.state.learningAreas.map(area =>
                    `<option value="${Number(area.id)}"${Number(primaryId) === Number(area.id) ? ' selected' : ''}>${this.escapeHtml(area.name || '')}</option>`
                ).join('');
        }
    },

    async loadStaff(options = {}) {
        const force = options.force === true;
        if (Number.isInteger(options.page) && options.page > 0) this.state.currentPage = options.page;

        if (this.staffLoadPromise && !force) {
            return this.staffLoadPromise;
        }

        const request = this._loadStaff();
        this.staffLoadPromise = request;

        try {
            return await request;
        } finally {
            if (this.staffLoadPromise === request) {
                this.staffLoadPromise = null;
            }
        }
    },

    async _loadStaff() {
        const requestSequence = ++this.staffRequestSequence;
        try {
            const response = await window.API.staff.index({
                page: this.state.currentPage,
                limit: this.state.pageSize,
                search: this.state.currentFilters.search,
                department_id: this.state.currentFilters.department,
                staff_type_id: this.state.currentFilters.staff_type_id,
                status: this.state.currentFilters.status
            });

            if (requestSequence !== this.staffRequestSequence) return this.state.staff;

            this.state.staff = this.extractStaffList(response);
            const payload = response?.data?.data || response?.data || response || {};
            this.state.pagination = payload?.pagination || response?.pagination || null;
            if (this.state.pagination?.page) this.state.currentPage = Number(this.state.pagination.page);
            this.state.filteredStaff = [...this.state.staff];
            this.render();
            return this.state.staff;
        } catch (error) {
            if (requestSequence !== this.staffRequestSequence) return this.state.staff;
            if (error.code === 'PERMISSION_DENIED') {
                this.showToast('You do not have permission to view staff', 'error');
            } else {
                console.error('[StaffProductionUI] Failed to load staff:', error);
                this.showToast(error?.message || 'Failed to load staff', 'error');
            }
            this.renderLoadError(error);
            return [];
        }
    },

    extractStaffList(data) {
        if (!data) return [];
        let rows = [];
        if (Array.isArray(data)) rows = data;
        else if (Array.isArray(data.staff)) rows = data.staff;
        else if (Array.isArray(data.data?.staff)) rows = data.data.staff;
        else if (Array.isArray(data.data)) rows = data.data;

        // GET /api/staff (list) keys a row as `staff_id`, while
        // GET /api/staff/{id} (detail) keys it as `id`. Every row renderer and
        // selection helper reads `staff.id`, so without this alias
        // Number(staff.id) is NaN, data-staff-id="NaN" is emitted, and the
        // click handler's `if (!staffId) return` guard silently swallows every
        // row action - no View/Edit/Roles/Reset modal ever opens. Normalise the
        // list once here so both response shapes behave identically.
        return rows.map((row) => {
            if (row && typeof row === 'object'
                && (row.id === undefined || row.id === null)
                && row.staff_id !== undefined && row.staff_id !== null) {
                return { ...row, id: row.staff_id };
            }
            return row;
        });
    },

    renderLoadError(error) {
        this.applyRoleLayout();
        const row = document.getElementById('staffStatsRow');
        if (row) row.innerHTML = '';

        const tbody = document.getElementById('staffTableBody');
        if (!tbody) return;

        const columns = this.getLayoutConfig().columns.length || 1;
        const message = error?.message || 'Unable to load staff records.';
        tbody.innerHTML = `
            <tr>
                <td colspan="${columns}" class="text-center text-danger py-4">
                    <div class="fw-semibold mb-1">Unable to load staff records</div>
                    <div class="small">${this.escapeHtml(message)}</div>
                </td>
            </tr>
        `;
    },

    async loadDepartments(options = {}) {
        const force = options.force === true;

        if (this.departmentsLoadPromise && !force) {
            return this.departmentsLoadPromise;
        }

        const request = this._loadDepartments();
        this.departmentsLoadPromise = request;

        try {
            return await request;
        } finally {
            if (this.departmentsLoadPromise === request) {
                this.departmentsLoadPromise = null;
            }
        }
    },

    async _loadDepartments() {
        try {
            const response = await window.API.staff.getDepartments();
            this.state.departments = this.extractList(response, 'departments');
            this.populateDepartmentDropdown();
            return this.state.departments;
        } catch (error) {
            console.error('[StaffProductionUI] Failed to load departments:', error);
            return [];
        }
    },

    async loadRoles(options = {}) {
        if (!this.canManageDirectory() || !window.StaffAccess?.can?.('staff.roles.manage')) {
            return [];
        }

        const force = options.force === true;

        if (this.rolesLoadPromise && !force) {
            return this.rolesLoadPromise;
        }

        const request = this._loadRoles();
        this.rolesLoadPromise = request;

        try {
            return await request;
        } finally {
            if (this.rolesLoadPromise === request) {
                this.rolesLoadPromise = null;
            }
        }
    },

    async _loadRoles() {
        try {
            const response = await window.API.staff.getAvailableRoles();
            this.state.roles = this.extractList(response, 'roles');
            this.populateRoleDropdown();
            return this.state.roles;
        } catch (error) {
            console.error('[StaffProductionUI] Failed to load roles:', error);
            return [];
        }
    },

    async loadStaffClassifications() {
        if (!this.canManageDirectory()) return [];
        try {
            const response = await window.API.staff.getClassificationOptions();
            const payload = response?.data || response || {};
            this.state.staffTypes = Array.isArray(payload.staff_types) ? payload.staff_types : [];
            this.state.staffCategories = Array.isArray(payload.staff_categories) ? payload.staff_categories : [];
            this.populateStaffClassificationDropdowns();
            return this.state.staffCategories;
        } catch (error) {
            console.error('[StaffProductionUI] Failed to load staff classifications:', error);
            return [];
        }
    },

    async loadSetupCatalogs() {
        try {
            const [departments, positions] = await Promise.all([
                window.API.staff.getDepartmentCatalog(), window.API.staff.getPositions()
            ]);
            this.setupDepartments = this.extractList(departments, 'departments');
            this.setupPositions = this.extractList(positions, 'positions');
            this.fillSetupSelect('staffPositionType', this.state.staffTypes, 'Any type');
            this.fillSetupSelect('staffPositionCategory', this.state.staffCategories, 'Any category');
            this.fillSetupSelect('staffPositionRoles', this.state.roles, 'Any role');
            this.fillSetupSelect('staffPositionDefaultRoles', this.state.roles, 'Select roles');
            this.filterPositionDefaultRoles();
            this.filterSetupCategories();
            this.renderSetupCatalogs();
            this.bindSetupCatalogEvents();
        } catch (error) {
            console.error('[StaffProductionUI] Failed to load staff setup catalogues:', error);
        }
    },

    fillSetupSelect(id, rows, placeholder) {
        const select = document.getElementById(id);
        if (!select) return;
        const value = select.value;
        select.innerHTML = (select.multiple ? '' : `<option value="">${placeholder}</option>`) + rows.map(row =>
            `<option value="${Number(row.id)}">${this.escapeHtml(row.name || row.category_name || '')}</option>`
        ).join('');
        select.value = value;
    },

    renderSetupCatalogs() {
        const departments = document.getElementById('staffDepartmentsList');
        if (departments) departments.innerHTML = this.setupDepartments.map(row => `<tr><td>${this.escapeHtml(row.name)}</td><td>${this.escapeHtml(row.code)}</td><td>${row.status === 'active' ? 'Active' : 'Inactive'}</td><td class="text-end"><button class="btn btn-sm btn-outline-primary" data-edit-department="${Number(row.id)}">Edit</button></td></tr>`).join('') || '<tr><td colspan="4" class="text-muted">No departments found.</td></tr>';
        const positions = document.getElementById('staffPositionsList');
        if (positions) positions.innerHTML = this.setupPositions.map(row => {
            const defaults = String(row.default_role_ids || '').split(',').map(Number).filter(Boolean).map(id => this.state.roles.find(role => Number(role.id) === id)?.name).filter(Boolean);
            const scope = [row.staff_type, row.staff_category, row.system_roles ? `Roles: ${row.system_roles}` : ''].filter(Boolean).join(' · ');
            return `<tr><td>${this.escapeHtml(row.name)}</td><td>${this.escapeHtml(scope || 'Any staff classification')}<small class="d-block text-muted">${defaults.length ? `Default for: ${this.escapeHtml(defaults.join(', '))}` : 'No default role assigned'}</small></td><td>${Number(row.is_active) ? 'Active' : 'Inactive'}</td><td class="text-end"><button class="btn btn-sm btn-outline-primary" data-edit-position="${Number(row.id)}">Edit</button></td></tr>`;
        }).join('') || '<tr><td colspan="4" class="text-muted">No positions configured.</td></tr>';
    },

    bindSetupCatalogEvents() {
        if (this.setupEventsBound) return;
        this.setupEventsBound = true;
        document.getElementById('exportStaffSetupBtn')?.addEventListener('click', () => {
            if (window.AuthContext?.canExport && !window.AuthContext.canExport('staff')) return this.showToast('You do not have permission to export staff setup data.', 'error');
            const rows = [['kind','name','code_or_scope','status']];
            this.setupDepartments.forEach(row => rows.push(['department', row.name, row.code, row.status]));
            this.setupPositions.forEach(row => rows.push(['position', row.name, [row.staff_type, row.staff_category, row.system_roles].filter(Boolean).join(' | '), Number(row.is_active) ? 'active' : 'inactive']));
            const csv = rows.map(row => row.map(value => `"${String(value ?? '').replaceAll('"','""')}"`).join(',')).join('\r\n');
            window.KingswayFileLifecycle?.exportText(csv, 'staff_setup.csv', 'text/csv');
        });
        document.getElementById('printStaffSetupBtn')?.addEventListener('click', () => {
            if (window.AuthContext?.canPrint && !window.AuthContext.canPrint('staff')) return this.showToast('You do not have permission to print staff setup data.', 'error');
            window.PrintManager?.printPage({ title: 'Staff Setup', orientation: 'portrait' }) ?? window.print();
        });
        document.getElementById('staffDepartmentForm')?.addEventListener('submit', async event => {
            event.preventDefault();
            const id = Number(this.getValue('staffDepartmentId') || 0);
            const payload = { name: this.getValue('staffDepartmentName'), code: this.getValue('staffDepartmentCode'), is_active: document.getElementById('staffDepartmentActive')?.checked ?? true };
            try { if (id) await window.API.staff.updateDepartment(id, payload); else await window.API.staff.createDepartment(payload); this.resetDepartmentEditor(); await this.loadDepartments(); await this.loadSetupCatalogs(); this.showToast('Department saved.', 'success'); }
            catch (error) { this.showToast(error.message || 'Could not save department.', 'error'); }
        });
        document.getElementById('staffDepartmentsList')?.addEventListener('click', event => {
            const row = this.setupDepartments.find(item => Number(item.id) === Number(event.target.closest('[data-edit-department]')?.dataset.editDepartment));
            if (!row) return;
            this.setValue('staffDepartmentId', row.id); this.setValue('staffDepartmentName', row.name); this.setValue('staffDepartmentCode', row.code);
            document.getElementById('staffDepartmentActive').checked = row.status === 'active';
        });
        document.getElementById('resetStaffDepartmentForm')?.addEventListener('click', () => this.resetDepartmentEditor());
        document.getElementById('staffPositionForm')?.addEventListener('submit', async event => {
            event.preventDefault();
            const id = Number(this.getValue('staffPositionId') || 0);
            const payload = { name: this.getValue('staffPositionName'), staff_type_id: this.getValue('staffPositionType') || null, staff_category_id: this.getValue('staffPositionCategory') || null, role_ids: [...(document.getElementById('staffPositionRoles')?.selectedOptions || [])].map(option => Number(option.value)), default_role_ids: [...(document.getElementById('staffPositionDefaultRoles')?.selectedOptions || [])].map(option => Number(option.value)), is_active: document.getElementById('staffPositionActive')?.checked ? 1 : 0 };
            try { if (id) await window.API.staff.updatePosition(id, payload); else await window.API.staff.createPosition(payload); this.resetPositionEditor(); await this.loadStaffClassifications(); await this.loadSetupCatalogs(); this.showToast('Position saved.', 'success'); }
            catch (error) { this.showToast(error.message || 'Could not save position.', 'error'); }
        });
        document.getElementById('staffPositionType')?.addEventListener('change', () => this.filterSetupCategories());
        document.getElementById('staffPositionRoles')?.addEventListener('change', () => this.filterPositionDefaultRoles());
        document.getElementById('staffPositionsList')?.addEventListener('click', event => {
            const row = this.setupPositions.find(item => Number(item.id) === Number(event.target.closest('[data-edit-position]')?.dataset.editPosition));
            if (!row) return;
            this.setValue('staffPositionId', row.id); this.setValue('staffPositionName', row.name); this.setValue('staffPositionType', row.staff_type_id || ''); this.setValue('staffPositionCategory', row.staff_category_id || ''); this.filterSetupCategories();
            const linkedRoles = String(row.role_ids || '').split(',').map(value => value.trim());
            [...document.getElementById('staffPositionRoles').options].forEach(option => { option.selected = linkedRoles.includes(option.value); });
            this.filterPositionDefaultRoles();
            const defaultRoles = String(row.default_role_ids || '').split(',').map(value => value.trim());
            [...document.getElementById('staffPositionDefaultRoles').options].forEach(option => { option.selected = defaultRoles.includes(option.value); });
            document.getElementById('staffPositionActive').checked = Number(row.is_active) === 1;
        });
        document.getElementById('resetStaffPositionForm')?.addEventListener('click', () => this.resetPositionEditor());
    },

    resetDepartmentEditor() { document.getElementById('staffDepartmentForm')?.reset(); this.setValue('staffDepartmentId', ''); const active=document.getElementById('staffDepartmentActive'); if(active) active.checked=true; },
    resetPositionEditor() { document.getElementById('staffPositionForm')?.reset(); this.setValue('staffPositionId', ''); document.getElementById('staffPositionActive').checked=true; },
    filterSetupCategories() {
        const select = document.getElementById('staffPositionCategory');
        if (!select) return;
        const typeId = Number(this.getValue('staffPositionType') || 0);
        const current = select.value;
        const categories = this.state.staffCategories.filter(item => !typeId || Number(item.staff_type_id) === typeId);
        select.innerHTML = '<option value="">Any category</option>' + categories.map(item => `<option value="${Number(item.id)}">${this.escapeHtml(item.name)}</option>`).join('');
        if (categories.some(item => String(item.id) === current)) select.value = current;
    },

    filterPositionDefaultRoles() {
        const related = document.getElementById('staffPositionRoles');
        const defaults = document.getElementById('staffPositionDefaultRoles');
        if (!related || !defaults) return;
        const allowed = new Set([...related.selectedOptions].map(option => option.value));
        [...defaults.options].forEach(option => {
            const keep = allowed.has(option.value);
            option.hidden = !keep;
            option.disabled = !keep;
            if (!keep) option.selected = false;
        });
    },

    populateStaffClassificationDropdowns(selectedType = null, selectedCategory = null) {
        const typeSelect = document.getElementById('staff_type_id');
        const categorySelect = document.getElementById('staff_category_id');
        if (!typeSelect || !categorySelect) return;
        const typeValue = selectedType ?? typeSelect.value;
        const categoryValue = selectedCategory ?? categorySelect.value;
        typeSelect.innerHTML = '<option value="">Select Type</option>' + this.state.staffTypes.map(type =>
            `<option value="${Number(type.id)}">${this.escapeHtml(type.name)}</option>`
        ).join('');
        typeSelect.value = String(typeValue || '');
        const matching = this.state.staffCategories.filter(category => Number(category.staff_type_id) === Number(typeSelect.value));
        categorySelect.innerHTML = '<option value="">Select category</option>' + matching.map(category =>
            `<option value="${Number(category.id)}">${this.escapeHtml(category.name)}</option>`
        ).join('');
        categorySelect.value = matching.some(category => String(category.id) === String(categoryValue)) ? String(categoryValue) : '';
    },

    extractList(data, key) {
        if (!data) return [];
        if (Array.isArray(data)) return data;
        if (Array.isArray(data[key])) return data[key];
        if (Array.isArray(data.data?.[key])) return data.data[key];
        if (Array.isArray(data.data)) return data.data;
        return [];
    },

    populateDepartmentDropdown() {
        const filterSelect = document.getElementById('filterDepartment');
        const formSelect = document.getElementById('department');
        
        if (filterSelect) {
            filterSelect.innerHTML = '<option value="">All Departments</option>' +
                this.state.departments.map(dept => 
                    `<option value="${dept.id}">${dept.name}</option>`
                ).join('');
        }
        
        if (formSelect) {
            formSelect.innerHTML = '<option value="">Select Department</option>' +
                this.state.departments.map(dept => 
                    `<option value="${dept.id}">${dept.name}</option>`
                ).join('');
        }
    },

    populateRoleDropdown() {
        const roleSelect = document.getElementById('roleId');
        if (!roleSelect) return;

        roleSelect.innerHTML = '<option value="">Select Role</option>' +
            this.state.roles.map(role =>
                `<option value="${Number(role.id)}">${this.escapeHtml(role.name || role.role_name || '')}</option>`
            ).join('');
    },

    setupEventListeners() {
        if (this.eventsBound) {
            return;
        }

        this.eventsBound = true;

        document.getElementById('searchStaff')?.addEventListener('input', (e) => {
            this.state.currentFilters.search = e.target.value;
            window.clearTimeout(this.searchTimer);
            this.searchTimer = window.setTimeout(() => this.applyFilters(), 250);
        });

        document.getElementById('filterDepartment')?.addEventListener('change', (e) => {
            this.state.currentFilters.department = e.target.value || null;
            this.applyFilters();
        });

        document.getElementById('filterStaffType')?.addEventListener('change', (e) => {
            this.state.currentFilters.staff_type_id = e.target.value || null;
            this.applyFilters();
        });
        document.getElementById('staff_type_id')?.addEventListener('change', () => {
            this.populateStaffClassificationDropdowns(document.getElementById('staff_type_id').value, '');
            this.syncTeacherOnlyFields();
        });
        document.getElementById('roleId')?.addEventListener('change', () => this.syncTeacherOnlyFields());
        document.getElementById('email')?.addEventListener('input', () => this.syncCommunicationDefaults('email'));
        document.getElementById('phone')?.addEventListener('input', () => this.syncCommunicationDefaults('phone'));

        document.getElementById('filterStatus')?.addEventListener('change', (e) => {
            this.state.currentFilters.status = e.target.value || null;
            this.applyFilters();
        });

        document.getElementById('resetFilters')?.addEventListener('click', () => {
            this.resetFilters();
        });
        document.getElementById('staffTablePagination')?.addEventListener('click', (event) => {
            const button = event.target.closest('[data-staff-page]');
            if (!button || button.disabled) return;
            this.state.selectedStaffIds.clear();
            void this.loadStaff({ force: true, page: Number(button.dataset.staffPage) });
        });
        document.getElementById('staffTablePagination')?.addEventListener('change', (event) => {
            if (event.target.id !== 'staffPageSize') return;
            const size = Number(event.target.value);
            if (![10, 25, 50, 100].includes(size)) return;
            this.state.pageSize = size;
            this.state.selectedStaffIds.clear();
            void this.loadStaff({ force: true, page: 1 });
        });
        document.getElementById('staffTableHead')?.addEventListener('change', (event) => {
            if (event.target.id !== 'selectAllStaffOnPage') return;
            for (const staff of this.state.staff) {
                if (event.target.checked && staff.user_id) this.state.selectedStaffIds.add(Number(staff.id));
                else this.state.selectedStaffIds.delete(Number(staff.id));
            }
            this.renderTable();
        });
        document.getElementById('staffTableBody')?.addEventListener('change', (event) => {
            const checkbox = event.target.closest('[data-select-staff]');
            if (!checkbox) return;
            const staffId = Number(checkbox.value);
            if (checkbox.checked) this.state.selectedStaffIds.add(staffId);
            else this.state.selectedStaffIds.delete(staffId);
            this.renderBulkActions();
            this.syncSelectAllCheckbox();
        });
        document.getElementById('bulkActivateStaffBtn')?.addEventListener('click', () => void this.setSelectedAccountStatus('active'));
        document.getElementById('bulkDeactivateStaffBtn')?.addEventListener('click', () => void this.setSelectedAccountStatus('inactive'));
        document.getElementById('bulkManageStaffRolesBtn')?.addEventListener('click', () => void this.openRoleManager([...this.state.selectedStaffIds]));
        document.getElementById('bulkResetStaffPasswordsBtn')?.addEventListener('click', () => void this.resetSelectedPasswords([...this.state.selectedStaffIds]));
        document.getElementById('clearStaffSelectionBtn')?.addEventListener('click', () => {
            this.state.selectedStaffIds.clear();
            this.renderTable();
        });
        document.getElementById('saveStaffRolesBtn')?.addEventListener('click', () => void this.saveManagedRoles());
        document.getElementById('addStaffBtn')?.addEventListener('click', () => {
            if (this.canManageDirectory()) {
                this.showAddModal();
            } else {
                this.showToast('You do not have permission to add staff', 'error');
            }
        });

        document.getElementById('saveStaffBtn')?.addEventListener('click', () => {
            this.saveStaff();
        });

        document.getElementById('addStaffQualificationBtn')?.addEventListener('click', () => {
            this.addQualificationRow();
        });

        document.getElementById('staffQualificationRows')?.addEventListener('click', (event) => {
            const removeButton = event.target.closest('[data-remove-qualification]');
            if (!removeButton) return;
            removeButton.closest('.qualification-row')?.remove();
            this.scheduleStaffDraftSave();
        });

        document.getElementById('addStaffExperienceBtn')?.addEventListener('click', () => {
            this.addExperienceRow();
        });

        document.getElementById('staffExperienceRows')?.addEventListener('click', (event) => {
            const removeButton = event.target.closest('[data-remove-experience]');
            if (!removeButton) return;
            removeButton.closest('.experience-row')?.remove();
            this.scheduleStaffDraftSave();
        });

        // Guided wizard navigation and resilient on-device draft saving.
        const staffForm = document.getElementById('staffForm');
        staffForm?.addEventListener('input', () => this.scheduleStaffDraftSave());
        staffForm?.addEventListener('change', () => this.scheduleStaffDraftSave());
        document.getElementById('staffFormTabs')?.addEventListener('click', (event) => {
            const tab = event.target.closest('[data-bs-toggle="tab"]');
            if (!tab) return;
            window.setTimeout(() => {
                const index = this.staffWizardSteps.indexOf(tab.id);
                if (index >= 0) this.setStaffWizardStep(index);
            }, 0);
        });
        document.getElementById('staffModalPrevBtn')?.addEventListener('click', () => {
            this.setStaffWizardStep(this.staffWizardStep - 1);
        });
        document.getElementById('staffModalNextBtn')?.addEventListener('click', () => {
            this.setStaffWizardStep(this.staffWizardStep + 1);
        });
        document.getElementById('staffReviewContent')?.addEventListener('click', (event) => {
            const button = event.target.closest('[data-staff-review-edit]');
            if (!button) return;
            const index = this.staffWizardSteps.indexOf(button.dataset.staffReviewEdit);
            if (index >= 0) this.setStaffWizardStep(index);
        });
        document.getElementById('clearStaffDraftBtn')?.addEventListener('click', () => {
            this.clearStaffDraft(true);
        });
        document.getElementById('staffInvitationStatusCard')?.addEventListener('click', async (event) => {
            const button = event.target.closest('[data-invitation-action]');
            if (!button || button.disabled) return;
            const staff = this.currentEditingStaff || {};
            if (button.dataset.invitationAction === 'reset-password') {
                await this.sendPasswordReset(Number(staff.id || 0));
                return;
            }
            const userId = Number(button.dataset.userId || staff.user_id || 0);
            const channel = ['sms', 'both'].includes(button.dataset.channel) ? button.dataset.channel : 'email';
            if (!userId) {
                this.showToast('This staff record has no account to invite.', 'warning');
                return;
            }
            const originalText = button.innerHTML;
            button.disabled = true;
            button.innerHTML = '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>Sending';
            try {
                await window.API.staffMigration.resendInvitation(userId, channel);
                this.showToast(
                    channel === 'both'
                        ? 'A replacement invitation was sent by email and SMS.'
                        : channel === 'sms'
                            ? 'A replacement invitation was sent by SMS.'
                            : 'A replacement invitation was sent by email.',
                    'success'
                );
                const refreshed = await this.getStaffRecord(Number(staff.id || 0));
                this.currentEditingStaff = { ...staff, ...refreshed };
                this.renderStaffInvitationStatus(this.currentEditingStaff);
            } catch (error) {
                this.showToast(error?.message || 'Could not resend the invitation.', 'error');
            } finally {
                button.disabled = false;
                button.innerHTML = originalText;
            }
        });
        document.getElementById('staffPhotoInput')?.addEventListener('change', (event) => {
            const file = event.target.files?.[0];
            if (file) void this.acceptStaffPhotoFile(file);
        });
        document.getElementById('startStaffCameraBtn')?.addEventListener('click', () => void this.startStaffCamera());
        document.getElementById('captureStaffPhotoBtn')?.addEventListener('click', () => this.captureStaffPhoto());
        document.getElementById('stopStaffCameraBtn')?.addEventListener('click', () => this.stopStaffCamera());
        document.getElementById('staffModal')?.addEventListener('hidden.bs.modal', () => {
            this.stopStaffCamera();
        });

        document.getElementById('exportStaffBtn')?.addEventListener('click', () => {
            if (this.canExportDirectory()) {
                this.exportStaff();
            } else {
                this.showToast('You do not have permission to export staff', 'error');
            }
        });

        document.getElementById('printStaffBtn')?.addEventListener('click', () => {
            if (window.AuthContext?.canPrint?.('staff') === false) {
                this.showToast('You do not have permission to print staff records.', 'error');
                return;
            }
            window.PrintManager?.printPage({ title: 'Staff Directory', orientation: 'landscape' }) ?? window.print();
        });

        document.getElementById('staffTableBody')?.addEventListener('click', async (event) => {
            const button = event.target.closest('[data-staff-action]');
            if (!button) return;

            const action = button.dataset.staffAction;
            if (action === 'resend-invitation') {
                const userId = Number(button.dataset.userId || 0);
                const channel = ['sms', 'both'].includes(button.dataset.channel) ? button.dataset.channel : 'email';
                if (!userId) return;
                button.disabled = true;
                void window.API.staffMigration.resendInvitation(userId, channel).then(() => {
                    showNotification(
                        channel === 'sms'
                            ? 'A replacement staff invitation was sent by SMS'
                            : channel === 'both'
                                ? 'A replacement staff invitation was sent by email and SMS'
                                : 'A replacement staff invitation was sent by email',
                        NOTIFICATION_TYPES.SUCCESS
                    );
                    void this.loadStaff();
                }).catch(error => {
                    showNotification(error.message || 'Could not resend invitation', NOTIFICATION_TYPES.ERROR);
                    button.disabled = false;
                });
                return;
            }
            if (action === 'resend-setup-otp') {
                const userId = Number(button.dataset.userId || 0);
                if (!userId) return;
                button.disabled = true;
                void window.API.staffMigration.resendSetupOtp(userId).then(() => {
                    showNotification('A new setup verification code was sent', NOTIFICATION_TYPES.SUCCESS);
                    void this.loadStaff();
                }).catch(error => {
                    showNotification(error.message || 'Could not resend the verification code', NOTIFICATION_TYPES.ERROR);
                    button.disabled = false;
                });
                return;
            }
            if (action === 'cancel-invitation') {
                const userId = Number(button.dataset.userId || 0);
                if (!userId) return;
                const confirmed = await window.confirmAction(
                    'Cancel staff invitation',
                    'The pending setup link will stop working and queued invitation emails will be cancelled.',
                    { confirmText: 'Cancel invitation', danger: true },
                );
                if (!confirmed) return;
                button.disabled = true;
                void window.API.staffMigration.cancelInvitation(userId).then(() => {
                    showNotification('Staff invitation cancelled', NOTIFICATION_TYPES.SUCCESS);
                    void this.loadStaff();
                }).catch(error => {
                    showNotification(error.message || 'Could not cancel invitation', NOTIFICATION_TYPES.ERROR);
                    button.disabled = false;
                });
                return;
            }
            const staffId = Number(button.dataset.staffId || 0);
            if (!staffId) return;

            if (action === 'view') {
                void this.viewStaff(staffId);
            } else if (action === 'edit') {
                void this.editStaff(staffId);
            } else if (action === 'manage-roles') {
                void this.openRoleManager([staffId]);
            } else if (action === 'activate-account') {
                void this.setAccountStatus([staffId], 'active');
            } else if (action === 'deactivate-account') {
                void this.setAccountStatus([staffId], 'inactive');
            } else if (action === 'reset-password') {
                void this.sendPasswordReset(staffId);
            } else if (action === 'delete') {
                void this.deleteStaff(staffId);
            } else if (action === 'performance') {
                this.openRoute('staff_performance', staffId);
            } else if (action === 'workload') {
                this.openRoute('teacher_workload', staffId);
            }
        });

        document.getElementById('editFromViewBtn')?.addEventListener('click', () => {
            const staffId = Number(document.getElementById('editFromViewBtn')?.dataset.staffId || 0);
            if (!staffId) return;
            bootstrap.Modal.getInstance(document.getElementById('staffViewModal'))?.hide();
            void this.editStaff(staffId);
        });
    },

    bindEvents() {
        this.setupEventListeners();
    },

    applyPageContext() {
        const context = window.STAFF_PAGE_CONTEXT || {};

        if (context.mode === 'create' && this.canManageDirectory()) {
            window.setTimeout(() => this.showAddModal(), 100);
        }
    },

    applyFilters() {
        this.state.currentPage = 1;
        this.state.selectedStaffIds.clear();
        void this.loadStaff({ force: true, page: 1 });
    },

    resetFilters() {
        this.state.currentFilters = {
            search: '',
            department: null,
            staff_type_id: null,
            status: null
        };

        document.getElementById('searchStaff').value = '';
        document.getElementById('filterDepartment').value = '';
        document.getElementById('filterStaffType').value = '';
        document.getElementById('filterStatus').value = '';
        this.applyFilters();
    },

    render() {
        this.applyRoleLayout();
        this.renderStats();
        this.renderTable();
    },

    renderStats() {
        const total = Number(this.state.pagination?.total || this.state.staff.length);
        const active = this.state.staff.filter(s => s.status === 'active').length;
        const teaching = this.state.staff.filter(s => s.staff_type_id == 1).length;
        const nonTeaching = this.state.staff.filter(s => s.staff_type_id == 2).length;
        const onLeave = this.state.staff.filter(s => s.status === 'on_leave').length;
        const departments = new Set(this.state.staff.map(s => s.department_id || s.department_name).filter(Boolean)).size;
        const payrollReady = this.state.staff.filter(s => s.status === 'active' && (s.salary || s.bank_account || s.bank_name)).length;
        const missingPayroll = this.state.staff.filter(s => s.status === 'active' && !(s.salary || s.bank_account || s.bank_name)).length;

        const metrics = {
            total: { value: total, label: 'Total Staff', icon: 'bi-people-fill', tone: 'primary' },
            active: { value: active, label: 'Active Staff', icon: 'bi-person-check-fill', tone: 'success' },
            teaching: { value: teaching, label: 'Teaching Staff', icon: 'bi-mortarboard-fill', tone: 'info' },
            non_teaching: { value: nonTeaching, label: 'Non-Teaching', icon: 'bi-tools', tone: 'warning', textClass: 'text-dark' },
            on_leave: { value: onLeave, label: 'On Leave', icon: 'bi-calendar-x-fill', tone: 'secondary' },
            departments: { value: departments, label: 'Departments', icon: 'bi-diagram-3-fill', tone: 'dark' },
            payroll_ready: { value: payrollReady, label: 'Payroll Ready', icon: 'bi-cash-coin', tone: 'success' },
            missing_payroll: { value: missingPayroll, label: 'Payroll Gaps', icon: 'bi-exclamation-triangle-fill', tone: 'danger' }
        };

        const cards = this.getLayoutConfig().cards;
        const row = document.getElementById('staffStatsRow');
        if (!row) return;

        row.innerHTML = cards.map(key => {
            const metric = metrics[key] || metrics.total;
            const textClass = metric.textClass || 'text-white';
            return `
                <div class="col-md-${cards.length > 3 ? '3' : '4'} mb-3" data-staff-card="${this.escapeHtml(key)}">
                    <div class="card bg-${this.escapeHtml(metric.tone)} ${textClass}">
                        <div class="card-body d-flex align-items-center gap-3">
                            <i class="bi ${this.escapeHtml(metric.icon)} fs-2"></i>
                            <div>
                                <h3 class="mb-0">${Number(metric.value || 0)}</h3>
                                <p class="mb-0">${this.escapeHtml(metric.label)}</p>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }).join('');
    },

    renderTableHeader() {
        const header = document.getElementById('staffTableHead');
        if (!header) return;
        const selectable = this.canManageStaffRoles() && this.getRoleMode() === 'operations';
        const labels = {
            staff_no: 'Staff No',
            name: 'Name',
            department: 'Department',
            roles: 'Roles',
            type: 'Type',
            position: 'Employment Position',
            contact: 'Contacts',
            account: 'Account & invitation',
            source: 'Staff source',
            payroll: 'Payroll',
            status: 'Status',
            actions: 'Actions'
        };

        header.innerHTML = `${selectable ? '<th scope="col" class="text-center"><input class="form-check-input" type="checkbox" id="selectAllStaffOnPage" aria-label="Select all staff on this page"></th>' : ''}${this.getLayoutConfig().columns
            .map(column => `<th class="${column === 'actions' ? 'text-end staff-actions-column' : ''}">${labels[column] || column}</th>`)
            .join('')}`;
    },

    renderTable() {
        const tbody = document.getElementById('staffTableBody');
        if (!tbody) return;
        const columns = this.getLayoutConfig().columns;
        const selectable = this.canManageStaffRoles() && this.getRoleMode() === 'operations';

        if (this.state.filteredStaff.length === 0) {
            tbody.innerHTML = `<tr><td colspan="${columns.length + (selectable ? 1 : 0)}" class="text-center text-muted py-4">No staff found</td></tr>`;
            this.renderBulkActions();
            this.renderPagination();
            return;
        }

        tbody.innerHTML = this.state.filteredStaff.map((staff) => {
            return `
                <tr>
                    ${selectable ? `<td class="text-center"><input class="form-check-input" type="checkbox" data-select-staff value="${Number(staff.id)}" aria-label="Select ${this.escapeHtml(staff.full_name || `${staff.first_name || ''} ${staff.last_name || ''}`)}" ${staff.user_id ? '' : 'disabled title="Staff account has not been created"'} ${this.state.selectedStaffIds.has(Number(staff.id)) ? 'checked' : ''}></td>` : ''}
                    ${columns.map(column => this.renderStaffCell(staff, column)).join('')}
                </tr>
            `;
        }).join('');
        this.renderBulkActions();
        this.renderPagination();
        this.syncSelectAllCheckbox();
    },

    renderStaffCell(staff, column) {
        const fullName = staff.full_name || `${staff.first_name || ''} ${staff.last_name || ''}`.trim() || 'Unnamed staff';
        const position = staff.display_position || staff.position || '-';
        const cells = {
            staff_no: `<td>${this.escapeHtml(staff.staff_no || '-')}</td>`,
            name: `<td><strong>${this.escapeHtml(fullName)}</strong>${this.getRoleMode() === 'operations' ? `<br>${this.renderAccountStatusBadge(staff)}` : ''}</td>`,
            source: `<td>${this.renderEmploymentSource(staff)}</td>`,
            department: `<td>${this.escapeHtml(staff.department_name || '-')}</td>`,
            roles: `<td>${this.renderRoleNames(staff)}</td>`,
            type: `<td>${this.renderStaffType(staff)}</td>`,
            position: `<td>${this.escapeHtml(position)}</td>`,
            contact: `<td>${this.renderContact(staff)}</td>`,
            account: `<td>${this.renderInvitationState(staff)}</td>`,
            payroll: `<td>${this.renderPayrollState(staff)}</td>`,
            status: `<td>${this.renderStatusBadge(staff)}</td>`,
            actions: `<td class="text-end staff-actions-column">${this.renderActionButtons(staff)}</td>`,
        };
        return cells[column] || '';
    },

    renderContact(staff) {
        const parts = [];
        if (staff.phone) parts.push(`<div>${this.escapeHtml(staff.phone)}</div>`);
        if (staff.email) parts.push(`<small class="text-muted">${this.escapeHtml(staff.email)}</small>`);
        return parts.length ? parts.join('') : '-';
    },

    renderRoleNames(staff) {
        const raw = staff.role_names || staff.role_name || '';
        const roles = Array.isArray(raw) ? raw : String(raw).split(',');
        const names = [...new Set(roles.map(role => String(role?.name || role?.role_name || role || '').trim()).filter(Boolean))];
        return names.length
            ? names.map(name => `<span class="badge bg-light text-dark border me-1 mb-1">${this.escapeHtml(name)}</span>`).join('')
            : '<span class="text-muted">-</span>';
    },

    renderInvitationState(staff) {
        if (!staff.user_id) return '<span class="badge bg-secondary">No account</span>';
        const profileComplete = Number(staff.profile_completed) === 1;
        const profile = profileComplete ? 'Profile complete' : 'Profile pending';
        const delivery = staff.invitation_delivery_status || 'not_queued';
        const status = staff.invitation_status || 'not_sent';
        const canResendOtp = status === 'accepted' && !profileComplete;
        const invitationActions = Number(staff.setup_required) === 1
            ? `<div class="dropdown d-inline-block ms-1"><button type="button" class="btn btn-sm btn-link p-0" data-bs-toggle="dropdown" aria-label="Staff invitation actions"><i class="bi bi-three-dots-vertical"></i></button><ul class="dropdown-menu dropdown-menu-end"><li><button class="dropdown-item" type="button" data-staff-action="resend-invitation" data-user-id="${Number(staff.user_id)}">Resend setup link</button></li>${status === 'pending' ? `<li><button class="dropdown-item text-danger" type="button" data-staff-action="cancel-invitation" data-user-id="${Number(staff.user_id)}">Cancel invitation</button></li>` : ''}</ul></div>`
            : canResendOtp
                ? `<div class="dropdown d-inline-block ms-1"><button type="button" class="btn btn-sm btn-link p-0" data-bs-toggle="dropdown" aria-label="Staff setup actions"><i class="bi bi-three-dots-vertical"></i></button><ul class="dropdown-menu dropdown-menu-end"><li><button class="dropdown-item" type="button" data-staff-action="resend-setup-otp" data-user-id="${Number(staff.user_id)}">Resend verification code</button></li></ul></div>`
                : '';
        return `<span class="small">Invitation: ${this.escapeHtml(status)}<br>Email: ${this.escapeHtml(delivery)} · ${profile}</span>${invitationActions}`;
    },

    renderEmploymentSource(staff) {
        return this.escapeHtml(this.sourceLabel(staff.employment_source));
    },

    renderPayrollState(staff) {
        const ready = Boolean(staff.salary || staff.bank_account || staff.bank_name);
        return ready
            ? '<span class="badge bg-success">Ready</span>'
            : '<span class="badge bg-warning text-dark">Incomplete</span>';
    },

    renderStaffType(staff) {
        const typeMap = { 1: 'Teaching', 2: 'Non-Teaching', 3: 'Admin' };
        const typeName = typeMap[staff.staff_type_id] || 'Unknown';
        const colorMap = { 1: 'primary', 2: 'info', 3: 'warning' };
        const color = colorMap[staff.staff_type_id] || 'secondary';
        return `<span class="badge bg-${color}">${this.escapeHtml(typeName)}</span>`;
    },

    renderStatusBadge(staff) {
        const statusMap = {
            'active': 'success',
            'inactive': 'secondary',
            'on_leave': 'warning'
        };
        const color = statusMap[staff.status] || 'secondary';
        return `<span class="badge bg-${color}">${this.escapeHtml(staff.status || 'Unknown')}</span>`;
    },

    renderActionButtons(staff) {
        const staffId = Number(staff.id);
        const actions = new Set(this.getLayoutConfig().actions);
        const menu = [];
        if (actions.has('view')) menu.push(`<li><button class="dropdown-item" type="button" data-staff-action="view" data-staff-id="${staffId}"><i class="bi bi-eye me-2"></i>View profile</button></li>`);
        if (actions.has('edit') && this.canManageDirectory()) menu.push(`<li><button class="dropdown-item" type="button" data-staff-action="edit" data-staff-id="${staffId}"><i class="bi bi-pencil me-2"></i>Edit staff details</button></li>`);
        if (this.canManageStaffRoles() && staff.user_id) {
            menu.push(`<li><button class="dropdown-item" type="button" data-staff-action="manage-roles" data-staff-id="${staffId}"><i class="bi bi-person-lock me-2"></i>Manage system roles</button></li>`);
            const active = staff.user_status === 'active';
            const accountAction = active ? 'deactivate-account' : 'activate-account';
            const accountLabel = active ? 'Deactivate account' : (staff.user_status === 'pending' ? 'Account setup pending' : 'Activate account');
            menu.push(`<li><button class="dropdown-item ${active ? 'text-danger' : 'text-success'}" type="button" data-staff-action="${accountAction}" data-staff-id="${staffId}" ${staff.user_status === 'pending' && !active ? 'disabled' : ''}><i class="bi ${active ? 'bi-person-x' : 'bi-person-check'} me-2"></i>${accountLabel}</button></li>`);
        }

        // Invitation resend is available on every staff row with an account so
        // the school administrator can choose email or SMS at the moment of use.
        if (staff.user_id && this.canManageDirectory()) {
            menu.push('<li><hr class="dropdown-divider"></li>');
            const userId = Number(staff.user_id);
            menu.push(`<li><button class="dropdown-item" type="button" data-staff-action="resend-invitation" data-channel="email" data-user-id="${userId}"><i class="bi bi-envelope-arrow-up me-2"></i>Resend invitation (Email)</button></li>`);
            menu.push(`<li><button class="dropdown-item" type="button" data-staff-action="resend-invitation" data-channel="sms" data-user-id="${userId}"><i class="bi bi-chat-square-text me-2"></i>Resend invitation (SMS)</button></li>`);
            menu.push(`<li><button class="dropdown-item" type="button" data-staff-action="resend-invitation" data-channel="both" data-user-id="${userId}"><i class="bi bi-send me-2"></i>Resend invitation (Both)</button></li>`);
            if (staff.invitation_status === 'pending') {
                menu.push(`<li><button class="dropdown-item text-danger" type="button" data-staff-action="cancel-invitation" data-user-id="${userId}"><i class="bi bi-x-circle me-2"></i>Cancel setup invitation</button></li>`);
            }
            if (staff.invitation_status === 'accepted' && Number(staff.profile_completed) !== 1) {
                menu.push(`<li><button class="dropdown-item" type="button" data-staff-action="resend-setup-otp" data-user-id="${userId}"><i class="bi bi-shield-lock me-2"></i>Resend setup verification code</button></li>`);
            }
            if (!Number(staff.setup_required) && staff.email) {
                menu.push(`<li><button class="dropdown-item" type="button" data-staff-action="reset-password" data-staff-id="${staffId}"><i class="bi bi-key me-2"></i>Send password reset link</button></li>`);
            }
        }
        if (actions.has('performance')) menu.push(`<li><button class="dropdown-item" type="button" data-staff-action="performance" data-staff-id="${staffId}"><i class="bi bi-graph-up me-2"></i>Performance</button></li>`);
        if (actions.has('workload')) menu.push(`<li><button class="dropdown-item" type="button" data-staff-action="workload" data-staff-id="${staffId}"><i class="bi bi-calendar-week me-2"></i>Workload</button></li>`);
        if (this.canDeleteDirectory() && this.canManageDirectory()) menu.push(`<li><hr class="dropdown-divider"></li><li><button class="dropdown-item text-danger" type="button" data-staff-action="delete" data-staff-id="${staffId}"><i class="bi bi-trash me-2"></i>Delete staff record</button></li>`);
        if (!menu.length) return '<span class="text-muted">—</span>';
        return `<div class="dropdown"><button class="btn btn-sm btn-light border" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Actions for ${this.escapeHtml(staff.full_name || 'staff member')}"><i class="bi bi-three-dots-vertical"></i></button><ul class="dropdown-menu dropdown-menu-end">${menu.join('')}</ul></div>`;
    },

    renderAccountStatusBadge(staff) {
        if (!staff.user_id) return '<span class="badge text-bg-light border">No account</span>';
        const status = String(staff.user_status || 'pending');
        const tone = status === 'active' ? 'success' : status === 'inactive' ? 'secondary' : status === 'suspended' ? 'danger' : 'warning text-dark';
        return `<span class="badge bg-${tone}">Account ${this.escapeHtml(status)}</span>`;
    },

    renderBulkActions() {
        const host = document.getElementById('staffBulkActions');
        if (!host) return;
        const selected = this.state.selectedStaffIds.size;
        const visible = this.canManageStaffRoles() && this.getRoleMode() === 'operations' && selected > 0;
        host.classList.toggle('d-none', !visible);
        host.classList.toggle('d-flex', visible);
        const count = document.getElementById('staffSelectionCount');
        if (count) count.textContent = `${selected} selected`;
        const activate = document.getElementById('bulkActivateStaffBtn');
        if (activate) activate.disabled = visible && this.state.staff.some(row => this.state.selectedStaffIds.has(Number(row.id)) && row.user_status === 'pending');
    },

    syncSelectAllCheckbox() {
        const checkbox = document.getElementById('selectAllStaffOnPage');
        if (!checkbox) return;
        const selectableRows = this.state.staff.filter(row => Boolean(row.user_id));
        const selectedOnPage = selectableRows.filter(row => this.state.selectedStaffIds.has(Number(row.id))).length;
        checkbox.checked = selectableRows.length > 0 && selectedOnPage === selectableRows.length;
        checkbox.indeterminate = selectedOnPage > 0 && selectedOnPage < selectableRows.length;
    },

    renderPagination() {
        const host = document.getElementById('staffTablePagination');
        if (!host) return;
        const page = Number(this.state.pagination?.page || this.state.currentPage || 1);
        const limit = Number(this.state.pagination?.limit || this.state.pageSize);
        const total = Number(this.state.pagination?.total || 0);
        const totalPages = Math.max(1, Number(this.state.pagination?.total_pages || Math.ceil(total / limit) || 1));
        const from = total ? ((page - 1) * limit) + 1 : 0;
        const to = Math.min(page * limit, total);
        host.innerHTML = `<div class="small text-muted">Showing ${from}–${to} of ${total}</div><div class="d-flex align-items-center gap-2"><label class="small text-muted" for="staffPageSize">Rows</label><select id="staffPageSize" class="form-select form-select-sm" style="width:auto"><option value="10" ${limit === 10 ? 'selected' : ''}>10</option><option value="25" ${limit === 25 ? 'selected' : ''}>25</option><option value="50" ${limit === 50 ? 'selected' : ''}>50</option><option value="100" ${limit === 100 ? 'selected' : ''}>100</option></select><div class="btn-group btn-group-sm" role="group" aria-label="Staff table pages"><button class="btn btn-outline-secondary" type="button" data-staff-page="${Math.max(1, page - 1)}" ${page <= 1 ? 'disabled' : ''}>Previous</button><span class="btn btn-outline-secondary disabled">${page} / ${totalPages}</span><button class="btn btn-outline-secondary" type="button" data-staff-page="${Math.min(totalPages, page + 1)}" ${page >= totalPages ? 'disabled' : ''}>Next</button></div></div>`;
    },

    async setAccountStatus(staffIds, status) {
        if (!this.canManageStaffRoles()) return this.showToast('You do not have permission to manage staff accounts.', 'error');
        if (!staffIds.length) return;
        if (status === 'active' && this.state.staff.some(row => staffIds.includes(Number(row.id)) && row.user_status === 'pending')) {
            return this.showToast('Pending invitations must complete setup before their accounts can be activated.', 'warning');
        }
        const actionText = status === 'active' ? 'activate' : 'deactivate';
        const confirmed = await window.confirmAction(
            `${status === 'active' ? 'Activate' : 'Deactivate'} staff account${staffIds.length === 1 ? '' : 's'}`,
            `This will ${actionText} ${staffIds.length} selected staff account${staffIds.length === 1 ? '' : 's'}.`,
            { confirmText: status === 'active' ? 'Activate' : 'Deactivate', danger: status !== 'active' },
        );
        if (!confirmed) return;
        try {
            const response = await window.API.staff.manageStaffAccounts({ staff_ids: staffIds, action: 'set_status', status });
            this.showToast(response?.message || `Selected accounts ${status}.`, 'success');
            this.state.selectedStaffIds.clear();
            await this.loadStaff({ force: true });
        } catch (error) {
            this.showToast(error?.message || `Could not ${actionText} selected accounts.`, 'error');
        }
    },

    setSelectedAccountStatus(status) {
        return this.setAccountStatus([...this.state.selectedStaffIds], status);
    },

    async resetSelectedPasswords(staffIds) {
        if (!this.canManageStaffRoles()) return this.showToast('You do not have permission to manage staff accounts.', 'error');
        const ids = [...new Set(staffIds.map(Number).filter(id => id > 0))];
        if (!ids.length) return;
        const confirmed = await window.confirmAction(
            'Send password reset links',
            `Send secure password reset instructions to ${ids.length} selected staff account${ids.length === 1 ? '' : 's'} by email?`,
            { confirmText: 'Send reset links' },
        );
        if (!confirmed) return;
        let sent = 0;
        const failed = [];
        for (const id of ids) {
            try {
                await window.API.staff.requestStaffPasswordReset(id);
                sent++;
            } catch (error) {
                failed.push(id);
                console.warn(`Password reset request failed for staff record ${id}`, error);
            }
        }
        this.state.selectedStaffIds.clear();
        this.renderTable();
        if (failed.length) {
            this.showToast(`${sent} reset link${sent === 1 ? '' : 's'} sent; ${failed.length} could not be sent.`, 'warning');
        } else {
            this.showToast(`Password reset instructions sent to ${sent} staff account${sent === 1 ? '' : 's'}.`, 'success');
        }
    },

    async openRoleManager(staffIds) {
        if (!this.canManageStaffRoles()) return this.showToast('You do not have permission to manage staff roles.', 'error');
        const ids = [...new Set(staffIds.map(Number).filter(id => id > 0))];
        if (!ids.length) return;
        if (!this.state.roles.length) await this.loadRoles({ force: true });
        if (!this.state.roles.length) return this.showToast('No assignable school roles are available.', 'error');
        this.managingStaffIds = ids;
        let selected = [];
        let primaryRoleId = 0;
        if (ids.length === 1) {
            const response = await window.API.staff.getRoleAssignments(ids[0]);
            const rows = this.extractList(response, 'roles');
            const payload = response?.data?.assignments || response?.assignments;
            const assignments = Array.isArray(payload) ? payload : rows;
            selected = assignments.map(role => Number(role.role_id || role.id)).filter(Boolean);
            primaryRoleId = Number(assignments.find(role => Number(role.is_primary) === 1)?.role_id || assignments.find(role => Number(role.is_primary) === 1)?.id || 0);
        }
        this.selectedRoleIds = selected;
        this.selectedPrimaryRoleId = primaryRoleId;
        const title = document.getElementById('staffRoleManagerTitle');
        const description = document.getElementById('staffRoleManagerDescription');
        if (title) title.textContent = ids.length === 1 ? 'Manage staff roles' : `Set roles for ${ids.length} staff members`;
        if (description) description.textContent = ids.length === 1
            ? 'Choose the dashboard role. Other selected roles remain available as secondary roles.'
            : 'Choose the primary dashboard role for all selected staff. Other selected roles remain secondary.';
        const choices = document.getElementById('staffRoleManagerChoices');
        if (choices) choices.innerHTML = this.state.roles.map(role => {
            const roleId = Number(role.id);
            const checked = selected.includes(roleId);
            return `<div class="list-group-item d-flex gap-2 align-items-start"><input class="form-check-input mt-1" type="checkbox" aria-label="Assign ${this.escapeHtml(role.name || role.role_name || '')}" data-managed-role="${roleId}" ${checked ? 'checked' : ''}><input class="form-check-input mt-1" type="radio" name="managedPrimaryRole" aria-label="Make ${this.escapeHtml(role.name || role.role_name || '')} primary" data-managed-primary="${roleId}" ${primaryRoleId === roleId ? 'checked' : ''} ${checked ? '' : 'disabled'}><span><span class="fw-semibold">${this.escapeHtml(role.name || role.role_name || '')}</span>${role.description ? `<small class="d-block text-muted">${this.escapeHtml(role.description)}</small>` : ''}</span></div>`;
        }).join('');
        choices?.querySelectorAll('[data-managed-role]').forEach(input => input.addEventListener('change', () => {
            const radio = choices.querySelector(`[data-managed-primary="${input.dataset.managedRole}"]`);
            if (radio) {
                radio.disabled = !input.checked;
                if (input.checked && !choices.querySelector('[data-managed-primary]:checked')) radio.checked = true;
                if (!input.checked && radio.checked) radio.checked = false;
            }
        }));
        bootstrap.Modal.getOrCreateInstance(document.getElementById('staffRoleManagerModal')).show();
    },

    async saveManagedRoles() {
        const roleIds = [...document.querySelectorAll('[data-managed-role]:checked')].map(input => Number(input.dataset.managedRole)).filter(id => id > 0);
        if (!roleIds.length) return this.showToast('Select at least one role.', 'warning');
        const primaryRoleId = Number(document.querySelector('[data-managed-primary]:checked')?.dataset.managedPrimary || 0);
        if (!roleIds.includes(primaryRoleId)) return this.showToast('Choose which selected role determines the staff dashboard.', 'warning');
        const staffIds = this.managingStaffIds || [];
        if (!staffIds.length) return;
        try {
            await window.API.staff.manageStaffAccounts({ staff_ids: staffIds, action: 'set_roles', role_ids: roleIds, primary_role_id: primaryRoleId });
            bootstrap.Modal.getInstance(document.getElementById('staffRoleManagerModal'))?.hide();
            this.state.selectedStaffIds.clear();
            this.showToast('Staff roles updated.', 'success');
            await this.loadStaff({ force: true });
        } catch (error) {
            this.showToast(error?.message || 'Could not update staff roles.', 'error');
        }
    },

    async sendPasswordReset(staffId) {
        if (!this.canManageStaffRoles()) return this.showToast('You do not have permission to manage staff accounts.', 'error');
        const staff = this.state.staff.find(row => Number(row.id) === Number(staffId));
        if (!staff?.email || !window.API?.staff?.requestStaffPasswordReset) return this.showToast('A staff account email is unavailable.', 'error');
        const confirmed = await window.confirmAction('Send password reset link', `Send a secure password reset link to ${staff.email}?`, { confirmText: 'Send reset link' });
        if (!confirmed) return;
        try {
            await window.API.staff.requestStaffPasswordReset(staffId);
            this.showToast('Password reset link requested for the staff member.', 'success');
        } catch (error) {
            this.showToast(error?.message || 'Could not send the password reset link.', 'error');
        }
    },

    openRoute(route, staffId) {
        window.location.href = `${window.APP_BASE || ''}/home.php?route=${encodeURIComponent(route)}&staff_id=${encodeURIComponent(staffId)}`;
    },

    showAddModal() {
        if (!this.canManageDirectory()) {
            this.showToast('You do not have permission to add staff', 'error');
            return;
        }
        document.getElementById('staffModalTitle').textContent = 'Add Staff';
        const subtitle = document.getElementById('staffModalSubtitle');
        if (subtitle) subtitle.textContent = 'Add a person already employed by the school, or a new staff member.';
        document.getElementById('staffId').value = '';
        document.getElementById('staffForm').reset();
        this.currentEditingStaff = null;
        const invitationCard = document.getElementById('staffInvitationStatusCard');
        const channelGroup = document.getElementById('invitationChannelGroup');
        if (invitationCard) invitationCard.hidden = true;
        if (channelGroup) channelGroup.hidden = false;
        this.populateStaffClassificationDropdowns('', '');
        this.populateLearningAreaDropdowns([], '');
        this.renderQualificationRows([]);
        this.renderExperienceRows([]);
        this.resetStaffPhoto();
        this.setValue('contractType', 'permanent');
        this.setValue('idNumberType', 'national_id');
        this.staffAutoFilledEmail = '';
        this.staffAutoFilledPhone = '';
        this.syncTeacherOnlyFields();
        const emailInvite = document.getElementById('inviteEmail');
        if (emailInvite) emailInvite.checked = true;
        this.setStaffWizardStep(0);

        const draft = this.readStaffDraft('');
        if (draft) {
            this.applyStaffDraft(draft);
            this.setStaffDraftStatus('Saved draft restored on this device', true);
        } else {
            this.setStaffDraftStatus('Draft saves automatically');
        }
        this.syncTeacherOnlyFields();
        this.primeStaffCommunicationDefaults();

        const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('staffModal'));
        modal.show();
    },

    activateStaffTab(tabId) {
        const tab = document.getElementById(tabId);
        if (!tab || !window.bootstrap?.Tab) return;
        bootstrap.Tab.getOrCreateInstance(tab).show();
    },

    isTeacherRole() {
        const roleId = Number(this.getValue('roleId') || 0);
        const role = (this.state.roles || []).find(item => Number(item.id) === roleId) || {};
        const staffTypeId = Number(this.getValue('staff_type_id') || 0);
        const staffType = (this.state.staffTypes || []).find(item => Number(item.id) === staffTypeId) || {};
        const selectedRoleLabel = document.querySelector('#roleId option:checked')?.textContent || '';
        const selectedStaffTypeLabel = document.querySelector('#staff_type_id option:checked')?.textContent || '';
        const roleName = String(role.name || role.role_name || selectedRoleLabel);
        const staffTypeName = String(staffType.name || staffType.staff_type_name || selectedStaffTypeLabel);

        // A non-teaching staff type always wins, even if a role label contains "teacher".
        if (/non[\s_-]?(teaching|teacher)/i.test(staffTypeName)) return false;
        return /(teacher|teaching|tutor|faculty|subject\s+teacher|class\s+teacher|academic\s+staff)/i
            .test(`${roleName} ${staffTypeName}`);
    },

    syncTeacherOnlyFields() {
        const isTeacher = this.isTeacherRole();
        ['tscNumberField', 'learningAreasField', 'primaryLearningAreaField'].forEach(id => {
            const wrapper = document.getElementById(id);
            if (wrapper) wrapper.hidden = !isTeacher;
        });
        if (!isTeacher) {
            // Hidden facts must never be submitted, even if an earlier role selection filled them.
            this.setValue('tscNo', '');
            const learningAreas = document.getElementById('learningAreas');
            if (learningAreas) [...learningAreas.options].forEach(option => { option.selected = false; });
            this.setValue('primaryLearningArea', '');
        }
    },

    syncCommunicationDefaults(source) {
        const email = this.getValue('email');
        const phone = this.getValue('phone');
        if (source === 'email') {
            const communicationEmail = this.getValue('communicationEmail');
            if (!communicationEmail || communicationEmail === this.staffAutoFilledEmail) {
                this.setValue('communicationEmail', email);
            }
            this.staffAutoFilledEmail = email;
        } else {
            const communicationPhone = this.getValue('communicationPhone');
            if (!communicationPhone || communicationPhone === this.staffAutoFilledPhone) {
                this.setValue('communicationPhone', phone);
            }
            this.staffAutoFilledPhone = phone;
        }
    },

    primeStaffCommunicationDefaults() {
        this.staffAutoFilledEmail = this.getValue('email');
        this.staffAutoFilledPhone = this.getValue('phone');
        this.syncCommunicationDefaults('email');
        this.syncCommunicationDefaults('phone');
        if (this.staffWizardSteps[this.staffWizardStep] === 'staff-tab-review') this.renderStaffReview();
    },

    staffOptionLabel(list, id, idKey = 'id') {
        const numericId = Number(id);
        const match = (list || []).find(item => Number.isNaN(numericId)
            ? String(item[idKey]) === String(id)
            : Number(item[idKey]) === numericId);
        return String(match?.name || match?.role_name || match?.staff_type_name || id || '-');
    },

    staffReviewSection(title, rows, tabId) {
        return `
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center py-2">
                    <h6 class="mb-0">${this.escapeHtml(title)}</h6>
                    <button type="button" class="btn btn-sm btn-link" data-staff-review-edit="${this.escapeHtml(tabId)}"><i class="bi bi-pencil me-1"></i>Edit</button>
                </div>
                <div class="card-body py-2">${rows}</div>
            </div>
        `;
    },

    renderStaffInvitationStatus(staff) {
        const card = document.getElementById('staffInvitationStatusCard');
        if (!card) return;
        const status = String(staff.invitation_status || 'not_sent');
        const labels = {
            not_sent: 'Not sent',
            pending: 'Pending',
            accepted: 'Accepted',
            expired: 'Expired',
            revoked: 'Revoked',
            cancelled: 'Cancelled'
        };
        const colors = {
            not_sent: 'secondary', pending: 'warning', accepted: 'success',
            expired: 'danger', revoked: 'danger', cancelled: 'dark'
        };
        const summaries = {
            not_sent: 'No account setup invitation has been issued for this staff member.',
            pending: 'A secure setup link is still waiting to be used.',
            accepted: 'The account setup link was sent and accepted.',
            expired: 'The setup link expired before it was used.',
            revoked: 'This setup link was cancelled by an administrator.',
            cancelled: 'This setup link was cancelled.'
        };
        const badge = document.getElementById('staffInvitationBadge');
        const summary = document.getElementById('staffInvitationSummary');
        const details = document.getElementById('staffInvitationDetails');
        const help = document.getElementById('staffInvitationHelp');

        if (badge) {
            badge.className = `badge text-bg-${colors[status] || 'secondary'}`;
            badge.textContent = labels[status] || status;
        }
        if (summary) summary.textContent = summaries[status] || 'Invitation status is unavailable.';
        if (details) {
            const sentAt = staff.invitation_sent_at
                ? new Date(staff.invitation_sent_at).toLocaleString()
                : 'Not recorded';
            const channel = staff.invitation_channel
                ? String(staff.invitation_channel).replace(/\b\w/g, char => char.toUpperCase())
                : 'Not recorded';
            const delivery = staff.invitation_delivery_status || 'not_queued';
            details.innerHTML = [
                this.detailRow('Delivery status', delivery.replaceAll('_', ' ')),
                this.detailRow('Last sent', sentAt),
                this.detailRow('Channel', channel),
                this.detailRow('Account status', staff.user_status || 'Not linked')
            ].join('');
        }

        const canResend = Boolean(staff.invitation_can_resend);
        card.querySelectorAll('[data-invitation-action="resend"]').forEach(button => {
            button.dataset.userId = String(staff.user_id || '');
            button.disabled = !canResend;
            button.title = canResend
                ? 'Create and deliver a new single-use setup link'
                : 'This account cannot receive a replacement setup link';
        });
        const resetButton = card.querySelector('[data-invitation-action="reset-password"]');
        if (resetButton) {
            resetButton.dataset.staffId = String(staff.id || '');
            resetButton.hidden = !staff.password_reset_available;
        }
        if (help) {
            help.textContent = canResend
                ? 'Resending creates a new single-use link and cancels the previous pending link.'
                : staff.invitation_setup_completed
                    ? 'Password setup is complete. Use password recovery or the reset action below instead of resending a setup link.'
                    : staff.user_status && staff.user_status !== 'active'
                        ? 'Restore the staff account before resending an invitation.'
                        : 'This invitation cannot be resent from the directory.';
        }
    },

    renderStaffReview() {
        const host = document.getElementById('staffReviewContent');
        if (!host) return;
        const selectedAreas = [...(document.getElementById('learningAreas')?.selectedOptions || [])]
            .map(option => option.textContent || '').filter(Boolean);
        const primaryAreaLabel = document.querySelector('#primaryLearningArea option:checked')?.textContent || '';
        const qualifications = this.collectQualifications();
        const experience = this.collectExperience();
        const qualificationLabels = {
            certificate: 'Certificate', diploma: 'Diploma', degree: 'Degree',
            postgraduate_diploma: 'Postgraduate diploma', masters: 'Masters',
            phd: 'PhD', professional: 'Professional', other: 'Other'
        };
        const qualificationRows = qualifications.length
            ? qualifications.map(item => this.detailRow(
                `${item.title} · ${qualificationLabels[item.qualification_level] || 'Other'}`,
                [item.institution, item.year_obtained, item.description].filter(Boolean).join(' · ')
            )).join('')
            : this.detailRow('Qualifications', 'None recorded');
        const experienceRows = experience.length
            ? experience.map(item => {
                const period = [item.start_date, item.end_date].filter(Boolean).join(' – ');
                return this.detailRow(
                    `${item.position || 'Role'} · ${item.organization || 'Organisation'}`,
                    [period, item.responsibilities].filter(Boolean).join(' · ') || 'Not provided'
                );
            }).join('')
            : this.detailRow('Previous employment', 'None recorded');
        const channelLabels = { email: 'Email', sms: 'SMS', both: 'Email and SMS' };
        const channel = document.querySelector('input[name="invitationChannel"]:checked')?.value || 'email';
        const photoAttached = Boolean(this.getValue('staffPhotoDataUrl'));
        const isTeacher = this.isTeacherRole();
        const nameOf = (list, id) => this.staffOptionLabel(list, id);

        const employment = [
            this.detailRow('First name', this.getValue('firstName')),
            this.detailRow('Middle name', this.getValue('middleName') || 'Not provided'),
            this.detailRow('Last name', this.getValue('lastName')),
            this.detailRow('Email', this.getValue('email')),
            this.detailRow('Phone', this.getValue('phone')),
            this.detailRow('Department', nameOf(this.state.departments, this.getValue('department'))),
            this.detailRow('Staff type', nameOf(this.state.staffTypes, this.getValue('staff_type_id'))),
            this.detailRow('Staff category', nameOf(this.state.staffCategories, this.getValue('staff_category_id'))),
            this.detailRow('Primary system role', nameOf(this.state.roles, this.getValue('roleId'))),
            this.detailRow('Contract type', this.getValue('contractType')),
            this.detailRow('Employment date', this.getValue('employmentDate') || 'Not provided'),
            this.detailRow('Position', this.getValue('position') || 'Derived from role'),
        ].join('');

        const personal = [
            this.detailRow('Identity document type', this.staffOptionLabel([
                { id: 'national_id', name: 'National ID' },
                { id: 'passport', name: 'Passport' },
                { id: 'birth_certificate', name: 'Birth Certificate' },
                { id: 'alien_id', name: 'Alien ID' },
                { id: 'other', name: 'Other' },
            ], this.getValue('idNumberType'), 'id')),
            this.detailRow('Identity document number', this.getValue('nationalIdNo')),
            this.detailRow('Date of birth', this.getValue('dateOfBirth') || 'Left for staff member'),
            this.detailRow('Gender', this.getValue('gender') || 'Left for staff member'),
            this.detailRow('Marital status', this.getValue('maritalStatus') || 'Left for staff member'),
            this.detailRow('Residential address', this.getValue('address') || 'Left for staff member'),
            this.detailRow('Communication email', this.getValue('communicationEmail') || 'Left for staff member'),
            this.detailRow('Communication phone', this.getValue('communicationPhone') || 'Left for staff member'),
        ].join('');

        const payroll = [
            this.detailRow('KRA PIN', this.getValue('kraPin') || 'N/A'),
            this.detailRow('NSSF number', this.getValue('nssfNo') || 'N/A'),
            this.detailRow('SHIF / NHIF number', this.getValue('nhifNo') || 'N/A'),
            this.detailRow('Bank name', this.getValue('bankName') || 'N/A'),
            this.detailRow('Bank account', this.getValue('bankAccount') || 'N/A'),
            this.detailRow('M-Pesa phone', this.getValue('mpesaPhone') || 'N/A'),
            ...(isTeacher ? [this.detailRow('TSC number', this.getValue('tscNo') || 'N/A')] : []),
            this.detailRow('Individual salary', this.getValue('salary') || 'Use role rate'),
        ].join('');

        const family = [
            this.detailRow('Emergency contact', this.getValue('emergencyContactName') || 'Not provided'),
            this.detailRow('Emergency phone', this.getValue('emergencyContactPhone') || 'Not provided'),
            this.detailRow('Relationship', this.getValue('emergencyContactRelationship') || 'Not provided'),
            ...(isTeacher ? [
                this.detailRow('Teaching / learning areas', selectedAreas.join(', ') || 'Not provided'),
                this.detailRow('Primary learning area', primaryAreaLabel || 'Not set'),
            ] : []),
        ].join('') + qualificationRows;

        const editing = this.currentEditingStaff;
        const invitationRows = editing ? [
            this.detailRow('Staff record status', editing.status || 'Not recorded'),
            this.detailRow('Account status', editing.user_status || 'Not linked'),
            this.detailRow('Invitation status', String(editing.invitation_status || 'not_sent').replaceAll('_', ' ')),
            this.detailRow('Invitation last sent', editing.invitation_sent_at
                ? new Date(editing.invitation_sent_at).toLocaleString()
                : 'Not recorded'),
        ] : [
            this.detailRow('Send invitation via', channelLabels[channel] || 'Email'),
        ];
        const invitation = invitationRows.concat(
            this.detailRow('Staff photo', photoAttached
                ? 'New photo attached and ready to upload'
                : editing?.photo_url
                    ? 'Existing photo retained'
                    : 'No photo selected')
        ).join('');

        host.innerHTML = [
            this.staffReviewSection('Employment details', employment, 'staff-tab-employment'),
            this.staffReviewSection('Identity & contact details', personal, 'staff-tab-personal'),
            this.staffReviewSection('Payroll & compliance', payroll, 'staff-tab-payroll'),
            this.staffReviewSection('Family & qualifications', family, 'staff-tab-details'),
            this.staffReviewSection('Experience', experienceRows, 'staff-tab-experience'),
            this.staffReviewSection('Invitation & photo', invitation, 'staff-tab-final'),
        ].join('');
    },

    staffDraftKey(staffId = '') {
        return `kingsway.staff.directory.draft.${staffId || 'new'}`;
    },

    readStaffDraft(staffId = '') {
        try {
            const raw = window.localStorage?.getItem(this.staffDraftKey(staffId));
            if (!raw) return null;
            const draft = JSON.parse(raw);
            return draft && typeof draft === 'object' ? draft : null;
        } catch (error) {
            console.warn('[StaffProductionUI] Could not read saved staff draft:', error);
            return null;
        }
    },

    scheduleStaffDraftSave() {
        if (this.staffDraftTimer) window.clearTimeout(this.staffDraftTimer);
        this.staffDraftTimer = window.setTimeout(() => {
            this.staffDraftTimer = null;
            this.saveStaffDraft();
        }, 250);
    },

    saveStaffDraft() {
        const form = document.getElementById('staffForm');
        if (!form) return;
        const staffId = this.getValue('staffId') || '';
        const draft = { staff_id: staffId, fields: {}, saved_at: new Date().toISOString() };
        form.querySelectorAll('input, select, textarea').forEach((element) => {
            if (!element.id || element.type === 'file') return;
            if (element.type === 'radio') {
                if (element.checked) draft.fields[element.name] = element.value;
                return;
            }
            draft.fields[element.id] = element.value;
        });
        draft.qualifications = this.collectQualifications();
        draft.experience = this.collectExperience();
        draft.photo_data_url = this.getValue('staffPhotoDataUrl') || '';
        draft.step = this.staffWizardStep || 0;
        try {
            window.localStorage?.setItem(this.staffDraftKey(staffId), JSON.stringify(draft));
            this.setStaffDraftStatus(`Draft saved ${new Date().toLocaleTimeString()}`, true);
        } catch (error) {
            // Large camera images or a full local store must never block the form.
            console.warn('[StaffProductionUI] Could not save staff draft:', error);
            this.setStaffDraftStatus('Draft could not be saved on this device');
        }
    },

    applyStaffDraft(draft) {
        const fields = draft.fields || {};
        Object.entries(fields).forEach(([key, value]) => {
            const element = document.getElementById(key);
            if (!element) return;
            if (element.type === 'radio') element.checked = element.value === value;
            else element.value = value ?? '';
        });
        if (fields.invitationChannel) {
            document.querySelectorAll('input[name="invitationChannel"]').forEach(radio => {
                radio.checked = radio.value === fields.invitationChannel;
            });
        }
        this.renderQualificationRows(draft.qualifications || []);
        this.renderExperienceRows(draft.experience || []);
        if (draft.photo_data_url) this.showStaffPhotoPreview(draft.photo_data_url);
        this.setStaffWizardStep(Number(draft.step || 0));
    },

    clearStaffDraft(showToast = false) {
        const staffId = this.getValue('staffId') || '';
        try { window.localStorage?.removeItem(this.staffDraftKey(staffId)); } catch (error) { console.warn(error); }
        this.setStaffDraftStatus('Draft cleared');
        if (showToast) this.showToast('Saved draft cleared. The fields you filled remain in the form.', 'info');
    },

    setStaffDraftStatus(message, restored = false) {
        const status = document.getElementById('staffDraftStatus');
        if (!status) return;
        status.innerHTML = `<i class="bi ${restored ? 'bi-arrow-counterclockwise' : 'bi-cloud-check'} me-1"></i>${this.escapeHtml(message)}`;
    },

    setStaffWizardStep(index) {
        const bounded = Math.max(0, Math.min(index, this.staffWizardSteps.length - 1));
        this.staffWizardStep = bounded;
        this.activateStaffTab(this.staffWizardSteps[bounded]);
        const isLast = bounded === this.staffWizardSteps.length - 1;
        const labels = ['Employment', 'Personal & Contact', 'Payroll & Compliance', 'Family & Qualifications', 'Experience', 'Invitation & Photo', 'Review'];
        const label = document.getElementById('staffWizardStepLabel');
        if (label) label.textContent = `Step ${bounded + 1} of ${this.staffWizardSteps.length} · ${labels[bounded] || ''}`;
        const previous = document.getElementById('staffModalPrevBtn');
        const next = document.getElementById('staffModalNextBtn');
        const save = document.getElementById('saveStaffBtn');
        if (previous) previous.disabled = bounded === 0;
        if (next) next.hidden = isLast;
        if (save) save.hidden = !isLast;
        const photoStep = this.staffWizardSteps.indexOf('staff-tab-final');
        if (bounded !== photoStep) this.stopStaffCamera();
        if (this.staffWizardSteps[bounded] === 'staff-tab-review') this.renderStaffReview();
        this.scheduleStaffDraftSave();
    },

    resetStaffPhoto() {
        this.setValue('staffPhotoDataUrl', '');
        const preview = document.getElementById('staffPhotoPreview');
        const placeholder = document.getElementById('staffPhotoPlaceholder');
        const input = document.getElementById('staffPhotoInput');
        if (preview) { preview.src = ''; preview.style.display = 'none'; }
        if (placeholder) placeholder.style.display = '';
        if (input) input.value = '';
        this.stopStaffCamera();
    },

    showStaffPhotoPreview(dataUrl) {
        const preview = document.getElementById('staffPhotoPreview');
        const placeholder = document.getElementById('staffPhotoPlaceholder');
        this.setValue('staffPhotoDataUrl', dataUrl || '');
        if (preview) { preview.src = dataUrl || ''; preview.style.display = dataUrl ? '' : 'none'; }
        if (placeholder) placeholder.style.display = dataUrl ? 'none' : '';
    },

    async startStaffCamera() {
        const video = document.getElementById('staffCameraVideo');
        const capture = document.getElementById('captureStaffPhotoBtn');
        const stop = document.getElementById('stopStaffCameraBtn');
        if (!video || !navigator.mediaDevices?.getUserMedia) {
            this.showToast('Live camera capture is not supported by this browser. Use upload from device instead.', 'warning');
            return;
        }
        try {
            this.stopStaffCamera();
            this.staffCameraStream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: 'environment', width: { ideal: 1280 }, height: { ideal: 960 } },
                audio: false
            });
            video.srcObject = this.staffCameraStream;
            video.style.display = '';
            if (capture) capture.disabled = false;
            if (stop) stop.hidden = false;
            await video.play();
        } catch (error) {
            console.error('[StaffProductionUI] Camera unavailable:', error);
            this.showToast('Camera access was not granted. You can still upload a photo from your device.', 'warning');
        }
    },

    stopStaffCamera() {
        if (this.staffCameraStream) {
            this.staffCameraStream.getTracks().forEach(track => track.stop());
            this.staffCameraStream = null;
        }
        const video = document.getElementById('staffCameraVideo');
        const capture = document.getElementById('captureStaffPhotoBtn');
        const stop = document.getElementById('stopStaffCameraBtn');
        if (video) { video.pause(); video.srcObject = null; video.style.display = 'none'; }
        if (capture) capture.disabled = true;
        if (stop) stop.hidden = true;
    },

    captureStaffPhoto() {
        const video = document.getElementById('staffCameraVideo');
        const canvas = document.getElementById('staffCameraCanvas');
        if (!video?.videoWidth || !canvas) {
            this.showToast('The camera is not ready yet.', 'warning');
            return;
        }
        const maxSide = 900;
        const scale = Math.min(1, maxSide / Math.max(video.videoWidth, video.videoHeight));
        canvas.width = Math.round(video.videoWidth * scale);
        canvas.height = Math.round(video.videoHeight * scale);
        canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
        this.showStaffPhotoPreview(canvas.toDataURL('image/jpeg', 0.82));
        this.stopStaffCamera();
        this.saveStaffDraft();
        this.showToast('Photo captured. It will be saved with the staff record.', 'success');
    },

    async acceptStaffPhotoFile(file) {
        if (!file?.type?.startsWith('image/')) {
            this.showToast('Choose a JPEG, PNG or WebP image.', 'warning');
            return;
        }
        try {
            const dataUrl = await this.compressStaffImage(file, 900, 0.82);
            this.showStaffPhotoPreview(dataUrl);
            this.saveStaffDraft();
        } catch (error) {
            console.error('[StaffProductionUI] Photo processing failed:', error);
            this.showToast('That image could not be processed. Try another file.', 'error');
        }
    },

    compressStaffImage(file, maxSide = 900, quality = 0.82) {
        return new Promise((resolve, reject) => {
            const reader = new FileReader();
            reader.onerror = () => reject(new Error('Could not read the selected image.'));
            reader.onload = () => {
                const image = new Image();
                image.onerror = () => reject(new Error('Could not decode the selected image.'));
                image.onload = () => {
                    const scale = Math.min(1, maxSide / Math.max(image.width, image.height));
                    const canvas = document.createElement('canvas');
                    canvas.width = Math.max(1, Math.round(image.width * scale));
                    canvas.height = Math.max(1, Math.round(image.height * scale));
                    canvas.getContext('2d').drawImage(image, 0, 0, canvas.width, canvas.height);
                    resolve(canvas.toDataURL('image/jpeg', quality));
                };
                image.src = String(reader.result);
            };
            reader.readAsDataURL(file);
        });
    },

    async dataUrlToFile(dataUrl, filename) {
        const response = await fetch(dataUrl);
        const blob = await response.blob();
        return new File([blob], filename, { type: blob.type || 'image/jpeg' });
    },

    async uploadStaffPhotoIfSelected(staffId) {
        const dataUrl = this.getValue('staffPhotoDataUrl');
        if (!dataUrl || !window.API?.staff?.uploadPhoto) return true;
        try {
            const file = await this.dataUrlToFile(dataUrl, `staff-${staffId}.jpg`);
            await window.API.staff.uploadPhoto(staffId, file, {
                description: 'Staff directory photograph',
                tags: 'staff_directory'
            });
            return true;
        } catch (error) {
            console.error('[StaffProductionUI] Staff photo upload failed:', error);
            this.showToast(error?.message || 'Staff saved, but the photo could not be uploaded. You can retry from the directory.', 'warning');
            return false;
        }
    },

    async saveStaff() {
        if (!this.canManageDirectory()) {
            this.showToast('You do not have permission to save staff', 'error');
            return;
        }
        const staffId = document.getElementById('staffId').value;
        const data = {
            first_name: this.getValue('firstName'),
            middle_name: this.getValue('middleName'),
            last_name: this.getValue('lastName'),
            email: this.getValue('email'),
            phone: this.getValue('phone'),
            id_number_type: this.getValue('idNumberType') || 'national_id',
            national_id_no: this.getValue('nationalIdNo'),
            department_id: this.getValue('department') || null,
            staff_type_id: this.getValue('staff_type_id') || null,
            staff_category_id: this.getValue('staff_category_id') || null,
            role_id: this.getValue('roleId') || null,
            contract_type: this.getValue('contractType') || 'permanent',
            employment_date: this.getValue('employmentDate') || null,
            position: this.getValue('position'),
            invitation_channel: document.querySelector('input[name="invitationChannel"]:checked')?.value || 'email',

            // Optional profile facts. Blank values stay pending for the staff member.
            date_of_birth: this.getValue('dateOfBirth') || null,
            gender: this.getValue('gender') || null,
            national_id_no: this.getValue('nationalIdNo') || null,
            marital_status: this.getValue('maritalStatus') || null,
            address: this.getValue('address') || null,
            communication_email: this.getValue('communicationEmail') || null,
            communication_phone: this.getValue('communicationPhone') || null,
            kra_pin: this.getValue('kraPin') || null,
            nssf_no: this.getValue('nssfNo') || null,
            nhif_no: this.getValue('nhifNo') || null,
            bank_name: this.getValue('bankName') || null,
            bank_account: this.getValue('bankAccount') || null,
            mpesa_phone: this.getValue('mpesaPhone') || null,
            tsc_no: this.getValue('tscNo') || null,
            emergency_contact_name: this.getValue('emergencyContactName') || null,
            emergency_contact_phone: this.getValue('emergencyContactPhone') || null,
            emergency_contact_relationship: this.getValue('emergencyContactRelationship') || null,
            learning_area_ids: [...(document.getElementById('learningAreas')?.selectedOptions || [])]
                .map(option => Number(option.value)).filter(Boolean),
            primary_learning_area_id: Number(this.getValue('primaryLearningArea')) || null,
        };

        if (this.getValue('salary') !== '') data.salary = this.getValue('salary');
        if (!data.position) delete data.position;
        if (!data.primary_learning_area_id) delete data.primary_learning_area_id;

        const qualifications = this.collectQualifications();
        if (staffId) data.qualification_details = qualifications;
        else data.qualifications = qualifications;
        const experience = this.collectExperience();
        if (staffId) data.experience_details = experience;
        else data.experience = experience;

        const requiredForCreate = {
            first_name: 'First name',
            last_name: 'Last name',
            email: 'Email',
            phone: 'Phone number',
            id_number_type: 'Identity document type',
            national_id_no: 'Identity document number',
            department_id: 'Department',
            staff_type_id: 'Staff type',
            staff_category_id: 'Staff category',
            role_id: 'System role',
            contract_type: 'Contract type'
        };

        if (!staffId) {
            const missing = Object.entries(requiredForCreate)
                .filter(([field]) => !data[field])
                .map(([, label]) => label);

            if (missing.length) {
                this.showToast(`Required fields missing: ${missing.join(', ')}`, 'warning');
                return;
            }
        } else if (!data.national_id_no || !data.id_number_type) {
            this.showToast('Identity document type and number are required.', 'warning');
            return;
        }

        try {
            this.stopStaffCamera();
            let savedStaffId = staffId;
            if (staffId) {
                const response = await window.API.staff.update(staffId, data);
                this.showToast(response?.message || 'Staff updated successfully', 'success');
            } else {
                const response = await window.API.staff.create(data);
                const payload = this.extractPayload(response) || {};
                savedStaffId = payload.id || payload.staff_id || null;
                this.showToast(payload.message || response?.message || 'Staff created successfully', 'success');
            }

            // The record is now committed. Clear the on-device draft even if a later
            // photo upload fails, so reopening the form cannot create a duplicate.
            try { window.localStorage?.removeItem(this.staffDraftKey(staffId)); } catch (error) { console.warn(error); }
            if (savedStaffId) await this.uploadStaffPhotoIfSelected(savedStaffId);

            const modal = bootstrap.Modal.getInstance(document.getElementById('staffModal'));
            modal.hide();

            await this.loadStaff();
        } catch (error) {
            console.error('Error saving staff:', error);
            this.showToast(error?.message || 'Failed to save staff', 'error');
        }
    },

    collectQualifications() {
        return [...document.querySelectorAll('#staffQualificationRows .qualification-row')]
            .map(row => ({
                id: Number(row.dataset.qualificationId || 0) || undefined,
                qualification_level: row.querySelector('[data-qual-field="qualification_level"]')?.value || 'other',
                title: row.querySelector('[data-qual-field="title"]')?.value?.trim() || '',
                institution: row.querySelector('[data-qual-field="institution"]')?.value?.trim() || '',
                year_obtained: row.querySelector('[data-qual-field="year_obtained"]')?.value || null,
                description: row.querySelector('[data-qual-field="description"]')?.value?.trim() || null,
            }))
            .filter(qualification => qualification.title && qualification.institution);
    },

    renderQualificationRows(qualifications = []) {
        const host = document.getElementById('staffQualificationRows');
        if (!host) return;
        host.innerHTML = '';
        const rows = Array.isArray(qualifications) && qualifications.length ? qualifications : [null];
        rows.forEach(qualification => this.addQualificationRow(qualification));
    },

    addQualificationRow(qualification = null) {
        const host = document.getElementById('staffQualificationRows');
        if (!host) return;
        const emptyRow = host.querySelector('.qualification-row');
        if (emptyRow && !emptyRow.dataset.qualificationId
            && !emptyRow.querySelector('[data-qual-field="title"]')?.value
            && !emptyRow.querySelector('[data-qual-field="institution"]')?.value) {
            emptyRow.remove();
        }
        const row = document.createElement('div');
        row.className = 'qualification-row';
        row.dataset.qualificationId = qualification?.id || '';
        const levels = ['certificate','diploma','degree','postgraduate_diploma','masters','phd','professional','other'];
        const level = qualification?.qualification_level || qualification?.level || 'other';
        row.innerHTML = `
            <div class="row g-2 align-items-end">
                <div class="col-md-3"><label class="form-label small mb-1">Level</label><select class="form-select form-select-sm" data-qual-field="qualification_level">${levels.map(item => `<option value="${item}"${item === level ? ' selected' : ''}>${this.escapeHtml(item.replaceAll('_', ' '))}</option>`).join('')}</select></div>
                <div class="col-md-3"><label class="form-label small mb-1">Title *</label><input class="form-control form-control-sm" data-qual-field="title"></div>
                <div class="col-md-3"><label class="form-label small mb-1">Institution *</label><input class="form-control form-control-sm" data-qual-field="institution"></div>
                <div class="col-md-2"><label class="form-label small mb-1">Year</label><input type="number" class="form-control form-control-sm" data-qual-field="year_obtained" min="1900" max="${new Date().getFullYear() + 1}"></div>
                <div class="col-md-1"><button type="button" class="btn btn-sm btn-outline-danger w-100" data-remove-qualification aria-label="Remove qualification"><i class="bi bi-x-lg"></i></button></div>
                <div class="col-12"><label class="form-label small mb-1">Notes</label><textarea class="form-control form-control-sm" rows="2" data-qual-field="description"></textarea></div>
            </div>
        `;
        row.querySelector('[data-qual-field="title"]').value = qualification?.title || qualification?.degree || '';
        row.querySelector('[data-qual-field="institution"]').value = qualification?.institution || '';
        row.querySelector('[data-qual-field="year_obtained"]').value = qualification?.year_obtained || qualification?.year || '';
        row.querySelector('[data-qual-field="description"]').value = qualification?.description || qualification?.details || '';
        host.appendChild(row);
    },

    collectExperience() {
        return [...document.querySelectorAll('#staffExperienceRows .experience-row')]
            .map(row => ({
                organization: row.querySelector('[data-exp-field="organization"]')?.value?.trim() || '',
                position: row.querySelector('[data-exp-field="position"]')?.value?.trim() || '',
                start_date: row.querySelector('[data-exp-field="start_date"]')?.value || null,
                end_date: row.querySelector('[data-exp-field="end_date"]')?.value || null,
                responsibilities: row.querySelector('[data-exp-field="responsibilities"]')?.value?.trim() || null,
            }))
            .filter(entry => entry.organization || entry.position);
    },

    renderExperienceRows(entries = []) {
        const host = document.getElementById('staffExperienceRows');
        if (!host) return;
        host.innerHTML = '';
        const rows = Array.isArray(entries) && entries.length ? entries : [null];
        rows.forEach(entry => this.addExperienceRow(entry));
    },

    addExperienceRow(entry = null) {
        const host = document.getElementById('staffExperienceRows');
        if (!host) return;
        const emptyRow = host.querySelector('.experience-row');
        if (emptyRow
            && !emptyRow.querySelector('[data-exp-field="organization"]')?.value
            && !emptyRow.querySelector('[data-exp-field="position"]')?.value) {
            emptyRow.remove();
        }
        const row = document.createElement('div');
        row.className = 'experience-row';
        row.innerHTML = `
            <div class="row g-2 align-items-end">
                <div class="col-md-3"><label class="form-label small mb-1">Organisation</label><input class="form-control form-control-sm" data-exp-field="organization"></div>
                <div class="col-md-3"><label class="form-label small mb-1">Position</label><input class="form-control form-control-sm" data-exp-field="position"></div>
                <div class="col-md-2"><label class="form-label small mb-1">Start date</label><input type="date" class="form-control form-control-sm" data-exp-field="start_date"></div>
                <div class="col-md-2"><label class="form-label small mb-1">End date</label><input type="date" class="form-control form-control-sm" data-exp-field="end_date"></div>
                <div class="col-md-1"><button type="button" class="btn btn-sm btn-outline-danger w-100" data-remove-experience aria-label="Remove experience"><i class="bi bi-x-lg"></i></button></div>
                <div class="col-12"><label class="form-label small mb-1">Responsibilities</label><textarea class="form-control form-control-sm" rows="2" data-exp-field="responsibilities"></textarea></div>
            </div>
        `;
        row.querySelector('[data-exp-field="organization"]').value = entry?.organization || '';
        row.querySelector('[data-exp-field="position"]').value = entry?.position || '';
        row.querySelector('[data-exp-field="start_date"]').value = entry?.start_date || '';
        row.querySelector('[data-exp-field="end_date"]').value = entry?.end_date || '';
        row.querySelector('[data-exp-field="responsibilities"]').value = entry?.responsibilities || '';
        host.appendChild(row);
    },

    async viewStaff(staffId) {
        try {
            const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('staffViewModal'));
            const title = document.getElementById('staffViewModalTitle');
            const body = document.getElementById('staffViewModalBody');
            if (title) title.textContent = 'Staff Profile';
            if (body) {
                body.innerHTML = `
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                `;
            }
            modal.show();

            const staff = await this.getStaffRecord(staffId);
            this.renderStaffProfile(staff);
        } catch (error) {
            console.error('Error loading staff details:', error);
            this.showToast(error?.message || 'Failed to load staff details', 'error');
        }
    },

    async editStaff(staffId) {
        if (!this.canManageDirectory()) {
            this.showToast('You do not have permission to edit staff', 'error');
            return;
        }
        try {
            const staff = await this.getStaffRecord(staffId);
            this.currentEditingStaff = staff;
            const invitationCard = document.getElementById('staffInvitationStatusCard');
            const channelGroup = document.getElementById('invitationChannelGroup');
            if (invitationCard) invitationCard.hidden = false;
            if (channelGroup) channelGroup.hidden = true;
            this.resetStaffPhoto();
            this.populateEditForm(staff);
            document.getElementById('staffModalTitle').textContent = `Edit ${staff.full_name || 'Staff Record'}`;
            const subtitle = document.getElementById('staffModalSubtitle');
            if (subtitle) subtitle.textContent = 'Update any staff detail. Your progress is saved on this device as you move between steps.';
            this.setStaffWizardStep(0);

            const draft = this.readStaffDraft(staffId);
            if (draft) {
                this.applyStaffDraft(draft);
                this.setStaffDraftStatus('Saved draft restored on this device', true);
            } else {
                this.setStaffDraftStatus('Draft saves automatically');
            }
            this.syncTeacherOnlyFields();
            this.primeStaffCommunicationDefaults();
            this.renderStaffInvitationStatus(staff);

            const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('staffModal'));
            modal.show();
        } catch (error) {
            console.error('Error loading staff for edit:', error);
            this.showToast(error?.message || 'Failed to load staff details', 'error');
        }
    },

    async getStaffRecord(staffId) {
        const response = await window.API.staff.get(staffId);
        const data = this.extractPayload(response);
        const staff = Array.isArray(data) ? data[0] : data;
        if (!staff || !staff.id) {
            throw new Error('Staff record was not returned by the API.');
        }
        return this.normalizeStaffRecord(staff);
    },

    extractPayload(response) {
        if (!response) return null;
        if (response.data?.data) return response.data.data;
        if (response.data) return response.data;
        return response;
    },

    normalizeStaffRecord(staff) {
        const firstName = staff.first_name || staff.staff_first_name || '';
        const lastName = staff.last_name || staff.staff_last_name || '';
        return {
            ...staff,
            first_name: firstName,
            last_name: lastName,
            full_name: staff.full_name || `${firstName} ${lastName}`.trim(),
            display_position: staff.display_position || staff.position || '',
            department_id: staff.department_id || staff.department_assignment_id || '',
            photo_url: staff.photo_url || staff.profile_pic_url || ''
        };
    },

    populateEditForm(staff) {
        const normalized = this.normalizeStaffRecord(staff);
        const payroll = normalized.payroll_profile || {};
        this.setValue('staffId', normalized.id);
        this.setValue('firstName', normalized.first_name || '');
        this.setValue('middleName', normalized.middle_name || '');
        this.setValue('lastName', normalized.last_name || '');
        this.setValue('email', normalized.email || '');
        this.setValue('phone', normalized.phone || '');
        this.setValue('idNumberType', normalized.id_number_type || 'national_id');
        this.populateStaffClassificationDropdowns(normalized.staff_type_id || '', normalized.staff_category_id || '');

        // Existing values must remain visible even when a lookup catalog is
        // unavailable to the current administrator. Inject the stored option
        // before selecting it rather than silently showing an empty field.
        this.ensureSelectOption('department', normalized.department_id, normalized.department_name || normalized.department);
        this.ensureSelectOption('staff_type_id', normalized.staff_type_id, normalized.staff_type_name);
        this.ensureSelectOption('staff_category_id', normalized.staff_category_id, normalized.staff_category_name);
        this.ensureSelectOption('roleId', normalized.role_id, normalized.role_name || normalized.role_names);

        this.setValue('department', normalized.department_id || '');
        this.setValue('staff_type_id', normalized.staff_type_id || '');
        this.setValue('staff_category_id', normalized.staff_category_id || '');
        this.setValue('roleId', normalized.role_id || '');
        this.setValue('contractType', normalized.contract_type || 'permanent');
        this.setValue('employmentDate', normalized.employment_date || '');
        this.setValue('position', normalized.position || '');
        this.populatePositionOptions();

        this.setValue('dateOfBirth', normalized.date_of_birth || '');
        this.setValue('gender', normalized.gender || '');
        this.setValue('nationalIdNo', normalized.national_id_no || '');
        this.setValue('maritalStatus', normalized.marital_status || '');
        this.setValue('address', normalized.address || '');
        this.setValue('communicationEmail', normalized.communication_email || '');
        this.setValue('communicationPhone', normalized.communication_phone || '');

        this.setValue('kraPin', normalized.kra_pin ?? payroll.kra_pin ?? '');
        this.setValue('nssfNo', normalized.nssf_no ?? payroll.nssf_no ?? '');
        this.setValue('nhifNo', normalized.nhif_no ?? payroll.nhif_no ?? '');
        this.setValue('bankName', normalized.bank_name ?? payroll.bank_name ?? '');
        this.setValue('bankAccount', normalized.bank_account ?? payroll.bank_account ?? '');
        this.setValue('mpesaPhone', normalized.mpesa_phone ?? payroll.mpesa_phone ?? '');
        this.setValue('tscNo', normalized.tsc_no || '');
        this.setValue('salary', payroll.basic_salary ?? normalized.salary ?? '');

        this.setValue('emergencyContactName', normalized.emergency_contact_name || '');
        this.setValue('emergencyContactPhone', normalized.emergency_contact_phone || '');
        this.setValue('emergencyContactRelationship', normalized.emergency_contact_relationship || '');
        this.populateLearningAreaDropdowns(normalized.learning_area_ids || [], normalized.primary_learning_area_id || '');
        this.renderQualificationRows(normalized.qualifications || []);
        this.renderExperienceRows(normalized.experience || []);
        if (normalized.photo_url || normalized.profile_pic_url) {
            const preview = document.getElementById('staffPhotoPreview');
            const placeholder = document.getElementById('staffPhotoPlaceholder');
            if (preview) { preview.src = normalized.photo_url || normalized.profile_pic_url; preview.style.display = ''; }
            if (placeholder) placeholder.style.display = 'none';
        }
        this.activateStaffTab('staff-tab-employment');
    },

    renderStaffProfile(staff) {
        const normalized = this.normalizeStaffRecord(staff);
        const title = document.getElementById('staffViewModalTitle');
        const body = document.getElementById('staffViewModalBody');
        const editButton = document.getElementById('editFromViewBtn');
        if (title) title.textContent = normalized.full_name || 'Staff Profile';
        if (editButton) {
            editButton.dataset.staffId = normalized.id;
            editButton.hidden = !this.canManageDirectory();
        }
        if (!body) return;

        body.innerHTML = `
            <div class="row g-4">
                <div class="col-lg-4">
                    <div class="border rounded p-3 h-100">
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <div class="rounded-circle bg-success text-white d-flex align-items-center justify-content-center" style="width:56px;height:56px;font-weight:700;">
                                ${this.escapeHtml(this.initials(normalized.full_name))}
                            </div>
                            <div>
                                <div class="fw-semibold fs-5">${this.escapeHtml(normalized.full_name || 'Unnamed staff')}</div>
                                <div class="text-muted">${this.escapeHtml(normalized.staff_no || '-')}</div>
                            </div>
                        </div>
                        ${this.detailRow('Employment Position', normalized.display_position || 'Not set')}
                        ${this.detailRow('Department', normalized.department_name)}
                        ${this.detailRow('Staff Type', normalized.staff_type_name || this.getStaffTypeName(normalized.staff_type_id))}
                        ${this.detailRow('Status', normalized.status)}
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="border rounded p-3 h-100">
                        <h6 class="mb-3">Contact & Employment</h6>
                        ${this.detailRow('Email', normalized.email)}
                        ${this.detailRow('Phone', normalized.phone)}
                        ${this.detailRow('Employment Date', normalized.employment_date)}
                        ${this.detailRow('Contract Type', normalized.contract_type)}
                        ${this.detailRow('Address', normalized.address)}
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="border rounded p-3 h-100">
                        <h6 class="mb-3">Compliance & Payroll</h6>
                        ${this.detailRow('KRA PIN', normalized.kra_pin)}
                        ${this.detailRow('NSSF No', normalized.nssf_no)}
                        ${this.detailRow('NHIF/SHIF No', normalized.nhif_no)}
                        ${this.detailRow('Bank', normalized.bank_name)}
                        ${this.detailRow('Bank Account', normalized.bank_account)}
                        ${this.detailRow('Salary', 'Managed from the primary role in Payroll')}
                    </div>
                </div>
            </div>
        `;
    },

    detailRow(label, value) {
        return `
            <div class="d-flex justify-content-between gap-3 border-bottom py-2">
                <span class="text-muted">${this.escapeHtml(label)}</span>
                <span class="text-end fw-medium">${this.escapeHtml(value || '-')}</span>
            </div>
        `;
    },

    getValue(id) {
        return document.getElementById(id)?.value?.trim() || '';
    },

    setValue(id, value) {
        const element = document.getElementById(id);
        if (element) element.value = value ?? '';
    },

    ensureSelectOption(id, value, label) {
        if (!value) return;
        const select = document.getElementById(id);
        if (!select) return;
        const needle = String(value);
        if ([...select.options].some(option => option.value === needle)) return;
        const option = document.createElement('option');
        option.value = needle;
        option.textContent = String(label || needle);
        select.insertBefore(option, select.firstChild);
    },

    initials(name) {
        return String(name || 'S')
            .split(/\s+/)
            .filter(Boolean)
            .slice(0, 2)
            .map(part => part.charAt(0).toUpperCase())
            .join('') || 'S';
    },

    formatCurrency(value) {
        const amount = Number(value || 0);
        if (!amount) return '-';
        return new Intl.NumberFormat('en-KE', {
            style: 'currency',
            currency: 'KES',
            maximumFractionDigits: 0
        }).format(amount);
    },

    async deleteStaff(staffId) {
        if (!this.canDeleteDirectory()) {
            this.showToast('You do not have permission to delete staff', 'error');
            return;
        }
        if (!(await window.confirmAction(
            'Confirm Deletion',
            'Delete this staff member? Their record and history are retained for audit, but their account will be disabled and removed from the active directory.',
            { confirmText: 'Delete', danger: true }
        ))) return;

        try {
            const response = await window.API.staff.delete(staffId);
            this.showToast(response?.message || 'Staff deleted successfully', 'success');
            await this.loadStaff();
        } catch (error) {
            console.error('Error deleting staff:', error);
            this.showToast(error?.message || 'Failed to delete staff', 'error');
        }
    },

    exportStaff() {
        if (!this.state.filteredStaff.length) {
            this.showToast('No data to export', 'warning');
            return;
        }

        const headers = ['Staff No', 'Name', 'Department', 'Roles', 'Contacts'];
        const rows = this.state.filteredStaff.map(staff => [
            staff.staff_no || '',
            `${staff.first_name} ${staff.last_name}`,
            staff.department_name || '',
            (Array.isArray(staff.role_names) ? staff.role_names.map(role => role.name || role).join(', ') : staff.role_names || staff.role_name || ''),
            [staff.phone, staff.email].filter(Boolean).join(' / ')
        ]);

        const csvCell = value => `"${String(value ?? '').replace(/"/g, '""')}"`;
        const csv = [headers, ...rows].map(row => row.map(csvCell).join(',')).join('\r\n');
        if (window.KingswayFileLifecycle?.exportText) {
            window.KingswayFileLifecycle.exportText(csv, 'staff_export.csv', 'text/csv');
        }
    },

    sourceLabel(source) {
        const labels = {
            existing_import: 'Existing · spreadsheet import',
            existing_manual: 'Existing · manual record',
            new_online_hire: 'New hire · online applicant',
            new_walk_in_hire: 'New hire · walk-in applicant',
            new_school_entered_hire: 'New hire · school-entered',
        };
        return labels[source] || 'Existing · manual record';
    },

    getStaffTypeName(typeId) {
        const typeMap = { 1: 'Teaching', 2: 'Non-Teaching', 3: 'Admin' };
        return typeMap[typeId] || 'Unknown';
    },

    escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }
};

window.StaffProductionUI = StaffProductionUI;
window.staffProductionController = StaffProductionUI;

function initializeStaffProductionUI() {
    void StaffProductionUI.init().catch((error) => {
        console.error('[StaffProductionUI] Page initialization failed:', error);
    });
}

if (window.__APP_BOOTED__) {
    initializeStaffProductionUI();
} else {
    window.addEventListener(
        'kingsway:ready',
        initializeStaffProductionUI,
        { once: true }
    );
}
