/**
 * Manage Holidays Controller
 * Page: manage_holidays.php
 * UI-managed HOLIDAY REGISTRY (school_holidays) - the single source of truth
 * for national, religious (moon-based), inter-term (April/August/December)
 * and school holidays. The academic calendar generator reads from this
 * registry, so editing a holiday and clicking "Apply to Calendar" updates the
 * whole year calendar + school events.
 */
const ManageHolidaysController = (() => {
    let allData = [];
    let filtered = [];

    const TYPE_LABELS = {
        national: 'National',
        religious: 'Religious',
        inter_term: 'Inter-Term',
        school: 'School',
    };

    async function init() {
        await window.AuthContext?.ready();
        if (typeof AuthContext !== 'undefined' && !AuthContext.isAuthenticated()) { window.location.href = (window.APP_BASE || '') + '/index.php'; return; }
        await loadData(); setupEventListeners();
    }

    function setupEventListeners() {
        document.getElementById('searchInput')?.addEventListener('input', filterData);
        document.getElementById('typeFilter')?.addEventListener('change', filterData);
        document.getElementById('dateFilter')?.addEventListener('change', filterData);
    }

    async function loadData() {
        try {
            const r = await window.API.schedules.listHolidays().catch(() => null);
            allData = r?.data || r || [];
            if (!Array.isArray(allData)) allData = [];
            renderStats(allData);
            filterData();
        } catch (e) { console.error('Load failed:', e); renderTable([]); }
    }

    function renderStats(data) {
        const items = Array.isArray(data) ? data : [];
        const el = (id, val) => { const e = document.getElementById(id); if (e) e.textContent = val; };
        el("statTotal", items.length);
        el("statNational", items.filter((h) => h.holiday_type === 'national').length);
        el("statReligious", items.filter((h) => h.holiday_type === 'religious').length);
        el("statOther", items.filter((h) => h.holiday_type !== 'national' && h.holiday_type !== 'religious').length + items.filter((h) => String(h.is_active) !== '1').length);
    }

    function daysBetween(start, end) {
        const a = new Date(start), b = new Date(end);
        if (isNaN(a) || isNaN(b)) return 1;
        return Math.round((b - a) / 86400000) + 1;
    }

    function renderTable(items) {
        filtered = items;
        const tbody = document.querySelector('#dataTable tbody');
        if (!tbody) return;
        if (!items.length) {
            tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">No holidays found</td></tr>';
            return;
        }
        tbody.innerHTML = items
            .map((h, i) => {
                const type = h.holiday_type || 'school';
                const typeColors = { national: 'warning', religious: 'info', inter_term: 'success', school: 'secondary' };
                const days = daysBetween(h.start_date, h.end_date);
                const active = String(h.is_active) === '1';
                return `<tr class="${active ? '' : 'text-muted'}">
                    <td>${i + 1}</td>
                    <td><strong>${escapeHtml(h.name)}</strong>${h.description ? `<div class="small text-muted">${escapeHtml(h.description)}</div>` : ''}</td>
                    <td><span class="badge bg-${typeColors[type] || 'secondary'}">${escapeHtml(TYPE_LABELS[type] || type)}</span></td>
                    <td>${escapeHtml(h.start_date)}</td>
                    <td>${escapeHtml(h.end_date || h.start_date)}</td>
                    <td>${days}</td>
                    <td><span class="badge bg-${active ? 'success' : 'secondary'}">${active ? 'Active' : 'Inactive'}</span></td>
                    <td>
                        <button class="btn btn-sm btn-outline-primary me-1" onclick="ManageHolidaysController.editRecord(${i})" title="Edit / re-date"><i class="fas fa-edit"></i></button>
                        <button class="btn btn-sm btn-outline-danger" onclick="ManageHolidaysController.deleteRecord(${h.id}, '${escapeAttr(h.name)}')" title="Delete"><i class="fas fa-trash"></i></button>
                    </td>
                </tr>`;
            })
            .join('');
    }

    function filterData() {
        const s = (document.getElementById('searchInput')?.value || '').toLowerCase();
        const f = document.getElementById("typeFilter")?.value;
        const dateF = document.getElementById("dateFilter")?.value;
        let items = allData;
        if (s) items = items.filter((h) => JSON.stringify(h).toLowerCase().includes(s));
        if (f) items = items.filter((h) => (h.holiday_type || 'school') === f);
        if (dateF) items = items.filter((h) => h.start_date === dateF || (h.end_date && h.end_date === dateF));
        renderTable(items);
    }

    function showAddModal() {
        document.getElementById("formModalTitle").innerHTML = '<i class="fas fa-calendar-plus me-2"></i>Add Holiday';
        document.getElementById('recordForm').reset();
        document.getElementById('recordId').value = '';
        document.getElementById("recordType").value = 'national';
        document.getElementById("recordActive").value = '1';
        new bootstrap.Modal(document.getElementById('formModal')).show();
    }

    function editRecord(index) {
        const h = allData[index]; if (!h) return;
        document.getElementById("formModalTitle").innerHTML = '<i class="fas fa-edit me-2"></i>Edit Holiday';
        document.getElementById('recordId').value = h.id;
        document.getElementById("recordName").value = h.name || '';
        document.getElementById("recordType").value = h.holiday_type || 'school';
        document.getElementById("recordStartDate").value = h.start_date || '';
        document.getElementById("recordEndDate").value = h.end_date || h.start_date || '';
        document.getElementById("recordDescription").value = h.description || '';
        document.getElementById("recordActive").value = String(h.is_active) === '0' ? '0' : '1';
        new bootstrap.Modal(document.getElementById('formModal')).show();
    }

    function collectPayload() {
        const name = document.getElementById("recordName").value.trim();
        const startDate = document.getElementById("recordStartDate").value;
        if (!name) { showNotification("Holiday name is required", "warning"); return null; }
        if (!startDate) { showNotification("Start date is required", "warning"); return null; }
        const endDate = document.getElementById("recordEndDate").value || startDate;
        if (endDate < startDate) { showNotification("End date cannot be before start date", "warning"); return null; }
        return {
            name,
            holiday_type: document.getElementById("recordType").value,
            start_date: startDate,
            end_date: endDate,
            description: document.getElementById("recordDescription").value.trim() || null,
            is_active: document.getElementById("recordActive").value === '1' ? 1 : 0,
        };
    }

    async function saveRecord(andApply = false) {
        const id = document.getElementById('recordId').value;
        const payload = collectPayload();
        if (!payload) return;
        try {
            if (id) {
                await window.API.schedules.updateHoliday(id, payload);
            } else {
                await window.API.schedules.createHoliday(payload);
            }
            showNotification("Holiday saved", "success");
            bootstrap.Modal.getInstance(document.getElementById('formModal'))?.hide();
            if (andApply) {
                await applyToCalendar();
            } else {
                await loadData();
            }
        } catch (e) { showNotification(e.message || 'Failed to save holiday', 'danger'); }
    }

    async function saveAndApply() {
        await saveRecord(true);
    }

    async function deleteRecord(id, name) {
        if (!(await window.confirmAction('Confirm Deletion', `Delete holiday "${name}"? It will be removed from the academic calendar on the next Apply to Calendar.`, { confirmText: 'Delete', danger: true }))) return;
        try {
            await window.API.schedules.deleteHoliday(id);
            showNotification("Holiday deleted", "success");
            await loadData();
        } catch (e) { showNotification(e.message || 'Delete failed', 'danger'); }
    }

    async function applyToCalendar() {
        showNotification('Applying holidays to the academic calendar...', 'info');
        try {
            const r = await window.API.schedules.applyHolidays();
            showNotification(r?.data?.message || 'Holidays applied to the calendar', 'success');
            if (window.DataStore) window.DataStore.invalidateMany(['academic', 'schedules']);
            await loadData();
        } catch (e) { showNotification(e.message || 'Failed to apply holidays', 'danger'); }
    }

    function exportCSV() {
        const rows = filtered.length ? filtered : allData;
        if (!rows.length) return;
        const headers = ['#', 'Holiday', 'Type', 'Start Date', 'End Date', 'Days', 'Status', 'Description'];
        const csvRows = rows.map((h, i) => [
            i + 1,
            h.name,
            TYPE_LABELS[h.holiday_type] || h.holiday_type,
            h.start_date,
            h.end_date || h.start_date,
            daysBetween(h.start_date, h.end_date),
            String(h.is_active) === '1' ? 'Active' : 'Inactive',
            h.description || '',
        ]);
        let csv = headers.join(",") + "\n" + csvRows.map((r) => r.map((v) => '"' + (v || "") + '"').join(",")).join("\n");
        KingswayFileLifecycle.exportText(csv, "holidays.csv", "text/csv");
    }

    function escapeHtml(s) {
        return String(s || "")
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;");
    }
    function escapeAttr(s) {
        return escapeHtml(s).replace(/'/g, "&#39;");
    }
    function showNotification(message, type) { window.showNotification(message, type); }
    return { init, refresh: loadData, exportCSV, showAddModal, editRecord, saveRecord, saveAndApply, deleteRecord, applyToCalendar };
})();
document.addEventListener('DOMContentLoaded', () => ManageHolidaysController.init());
