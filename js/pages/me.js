const ProfileController = (() => {
    let userData = null;
    let staffProfile = null;

    async function init() {
        await window.AuthContext?.ready();
        if (typeof AuthContext === 'undefined' || !AuthContext.isAuthenticated()) {
            window.location.href = (window.APP_BASE || '') + '/index.php';
            return;
        }
        userData = AuthContext.getUser();
        await loadStaffProfile();
        renderProfile();
        renderRoles();
        loadLoginHistory();
        loadCurrentAcademicYear();
        loadKpiSummary();
    }

    async function loadStaffProfile() {
        try {
            const resp = await window.API.staff.getProfile();
            staffProfile = resp?.data || null;
        } catch (e) {
            staffProfile = null;
        }
    }

    function formatTenure(employmentDate) {
        if (!employmentDate) return '—';
        const start = new Date(employmentDate);
        const now = new Date();
        let years = now.getFullYear() - start.getFullYear();
        let months = now.getMonth() - start.getMonth();
        let days = now.getDate() - start.getDate();
        if (days < 0) { months--; days += new Date(now.getFullYear(), now.getMonth(), 0).getDate(); }
        if (months < 0) { years--; months += 12; }
        if (years < 0) return '—';
        const parts = [];
        if (years > 0) parts.push(`${years} year${years > 1 ? 's' : ''}`);
        if (months > 0) parts.push(`${months} month${months > 1 ? 's' : ''}`);
        if (days >= 0) parts.push(`${days} day${days > 1 ? 's' : ''}`);
        return parts.join(', ') || '—';
    }

    function renderProfile() {
        const s = staffProfile || {};
        const u = userData || {};

        const name = `${s.first_name || u.first_name || ''} ${s.last_name || u.last_name || ''}`.trim() || u.username || 'User';
        const initials = name.split(' ').map(n => n.charAt(0)).join('').toUpperCase().substring(0, 2);

        document.getElementById('profileAvatar').innerHTML = initials;
        document.getElementById('profileName').textContent = name;
        document.getElementById('profileEmail').textContent = s.email || u.email || '—';

        const roles = u.roles || [];
        const mainRole = roles.length > 0 ? (typeof roles[0] === 'string' ? roles[0] : roles[0].name) : 'User';
        document.getElementById('profileRole').textContent = mainRole;

        // Staff number (NOT user ID)
        document.getElementById('profileEmployeeId').textContent = s.staff_no || '—';
        document.getElementById('profilePhone').textContent = s.phone || u.phone || '—';
        document.getElementById('profileDepartment').textContent = s.department_name || '—';
        document.getElementById('profileLastLogin').textContent = u.last_login ? new Date(u.last_login).toLocaleDateString() : '—';

        // Greeting
        const greetEl = document.getElementById('profileGreeting');
        if (greetEl) {
            const hour = new Date().getHours();
            let greeting = 'Good evening';
            if (hour < 12) greeting = 'Good morning';
            else if (hour < 17) greeting = 'Good afternoon';
            greetEl.textContent = `${greeting}, ${name.split(' ')[0]}`;
        }

        // Tenure
        document.getElementById('profileTenure').textContent = formatTenure(s.employment_date);

        // Populate employee info form
        document.getElementById('firstName').value = s.first_name || u.first_name || '';
        document.getElementById('lastName').value = s.last_name || u.last_name || '';
        document.getElementById('email').value = s.email || u.email || '';
        document.getElementById('phone').value = s.phone || u.phone || '';

        // Employee-specific readonly fields
        document.getElementById('position').value = s.position || '—';
        document.getElementById('department').value = s.department_name || '—';
        document.getElementById('supervisor').value = s.supervisor_name || '—';
        document.getElementById('contractType').value = s.contract_type || '—';
        document.getElementById('employmentDate').value = s.employment_date || '—';
        document.getElementById('gender').value = s.gender || u.gender || '';
        document.getElementById('dob').value = s.date_of_birth || u.date_of_birth || '—';
        document.getElementById('address').value = s.address || u.address || '—';
        document.getElementById('maritalStatus').value = s.marital_status || '—';

        // Financial / confidential fields
        document.getElementById('bankName').value = s.bank_name || '—';
        document.getElementById('bankAccount').value = s.bank_account ? '••••' + s.bank_account.slice(-4) : '—';
        document.getElementById('kraPin').value = s.kra_pin || '—';
        document.getElementById('nssfNo').value = s.nssf_no || '—';
        document.getElementById('nhifNo').value = s.nhif_no || '—';
        document.getElementById('tscNo').value = s.tsc_no || '—';
    }

    function renderRoles() {
        const roles = userData?.roles || [];
        const rolesHtml = roles.map(r => {
            const name = typeof r === 'string' ? r : r.name;
            return `<span class="badge bg-primary me-2 mb-2 fs-6">${name}</span>`;
        }).join('') || '<span class="text-muted">No roles assigned</span>';
        document.getElementById('rolesList').innerHTML = rolesHtml;
    }

    async function loadCurrentAcademicYear() {
        const el = document.getElementById('currentAcademicYear');
        if (!el) return;
        try {
            const resp = await window.API.academic.getCurrentAcademicYear?.();
            const year = resp?.data?.year_code || resp?.data?.name || '';
            el.textContent = year || '—';
        } catch (e) {
            el.textContent = '—';
        }
    }

    async function loadKpiSummary() {
        const container = document.getElementById('kpiSummary');
        if (!container) return;
        const staffId = staffProfile?.id;
        if (!staffId) { container.innerHTML = ''; return; }
        try {
            const resp = await window.API.staff.getAcademicKPISummary(staffId);
            const kpis = resp?.data || {};
            const cards = Object.entries(kpis).filter(([, v]) => v !== null && v !== undefined).slice(0, 6);
            if (!cards.length) { container.innerHTML = '<p class="text-muted small mb-0">No KPI data available</p>'; return; }
            container.innerHTML = '<div class="row g-2">' + cards.map(([key, value]) => `
                <div class="col-md-4 col-6">
                    <div class="border rounded p-2 text-center">
                        <strong class="d-block fs-5">${value}</strong>
                        <small class="text-muted">${key.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())}</small>
                    </div>
                </div>
            `).join('') + '</div>';
        } catch (e) {
            container.innerHTML = '';
        }
    }

    async function loadLoginHistory() {
        try {
            const response = await window.API.apiCall(`/users/${userData.id}/login-history`, 'GET');
            const history = response?.data || response || [];
            renderLoginHistory(Array.isArray(history) ? history : []);
        } catch (e) {
            document.querySelector('#loginHistoryTable tbody').innerHTML =
                '<tr><td colspan="5" class="text-center text-muted">Unable to load login history</td></tr>';
        }
    }

    function renderLoginHistory(history) {
        const tbody = document.querySelector('#loginHistoryTable tbody');
        if (!history.length) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">No login history available</td></tr>';
            return;
        }
        tbody.innerHTML = history.slice(0, 20).map(h => `
            <tr>
                <td>${h.created_at ? new Date(h.created_at).toLocaleString() : '--'}</td>
                <td>${h.ip_address || '--'}</td>
                <td>${h.device || '--'}</td>
                <td>${h.browser || '--'}</td>
                <td><span class="badge bg-${h.status === 'success' ? 'success' : 'danger'}">${h.status || 'unknown'}</span></td>
            </tr>
        `).join('');
    }

    function showNotification(message, type) { window.showNotification(message, type); }

    return { init, refresh: init };
})();

document.addEventListener('DOMContentLoaded', () => ProfileController.init());
