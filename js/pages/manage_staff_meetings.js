/**
 * Manage Staff Meetings Controller
 * Page: manage_staff_meetings.php
 * Internal staff meetings scheduled by heads (director, school admin, HODs to
 * department members or selected members, deputies, class teachers).
 * Every meeting syncs a linked calendar event (school_events, type 'Meeting')
 * carrying the venue + online link, so it shows on the academic calendar, the
 * Year Calendar and the events pages. Attendees RSVP and get notifications.
 */
const StaffMeetingsController = (() => {
    let allData = [];
    let filtered = [];
    let staffList = [];
    let departments = [];

    const TYPE_LABELS = {
        general: 'General', departmental: 'Departmental', administrative: 'Administrative',
        heads: 'Heads', class_teachers: 'Class Teachers', assembly: 'Assembly', other: 'Other',
    };
    const TYPE_COLORS = {
        general: 'secondary', departmental: 'primary', administrative: 'warning',
        heads: 'info', class_teachers: 'success', assembly: 'danger', other: 'dark',
    };

    async function init() {
        await window.AuthContext?.ready();
        if (typeof AuthContext !== 'undefined' && !AuthContext.isAuthenticated()) { window.location.href = (window.APP_BASE || '') + '/index.php'; return; }
        await loadPickers();
        await loadData();
        setupEventListeners();
    }

    async function loadPickers() {
        try {
            const r = await window.API.meetings.staffList().catch(() => null);
            const payload = r?.data || r || {};
            staffList = Array.isArray(payload.staff) ? payload.staff : (Array.isArray(payload) ? payload : []);
            departments = Array.isArray(payload.departments) ? payload.departments : [];
            populatePickers();
        } catch (e) { console.error('Failed to load pickers:', e); }
    }

    function populatePickers() {
        const typeSel = document.getElementById('typeFilter');
        if (typeSel) {
            typeSel.innerHTML = '<option value="">All Types</option>' +
                Object.entries(TYPE_LABELS).map(([k, v]) => `<option value="${k}">${v}</option>`).join('');
        }
        const deptFilter = document.getElementById('departmentFilter');
        if (deptFilter) {
            deptFilter.innerHTML = '<option value="">All Departments</option>' +
                departments.map((d) => `<option value="${d.id}">${escapeHtml(d.name)}</option>`).join('');
        }
        const deptSel = document.getElementById('recordDepartment');
        if (deptSel) {
            deptSel.innerHTML = '<option value="">-- No department --</option>' +
                departments.map((d) => `<option value="${d.id}">${escapeHtml(d.name)}</option>`).join('');
        }
        const attSel = document.getElementById('recordAttendees');
        if (attSel) {
            attSel.innerHTML = staffList.map((s) =>
                `<option value="${s.id}">${escapeHtml(s.name)}${s.position ? ' — ' + escapeHtml(s.position) : ''}${s.department_name ? ' (' + escapeHtml(s.department_name) + ')' : ''}</option>`
            ).join('');
        }
    }

    function setupEventListeners() {
        document.getElementById('searchInput')?.addEventListener('input', filterData);
        document.getElementById('statusFilter')?.addEventListener('change', filterData);
        document.getElementById('typeFilter')?.addEventListener('change', filterData);
        document.getElementById('departmentFilter')?.addEventListener('change', filterData);
    }

    async function loadData() {
        try {
            const r = await window.API.meetings.list().catch(() => null);
            allData = r?.data || r || [];
            if (!Array.isArray(allData)) allData = [];
            renderStats(allData);
            filterData();
        } catch (e) { console.error('Load failed:', e); renderTable([]); }
    }

    function renderStats(data) {
        const now = new Date();
        const weekEnd = new Date(now.getTime() + 7 * 86400000);
        const items = Array.isArray(data) ? data : [];
        const el = (id, val) => { const e = document.getElementById(id); if (e) e.textContent = val; };
        el("statTotal", items.length);
        el("statUpcoming", items.filter((m) => new Date(m.meeting_date) >= now && m.status !== 'cancelled').length);
        el("statWeek", items.filter((m) => { const d = new Date(m.meeting_date); return d >= now && d <= weekEnd; }).length);
        el("statPast", items.filter((m) => new Date(m.meeting_date) < now || m.status === 'completed').length);
    }

    function meetingDate(m) {
        return m.meeting_date || '';
    }

    function renderTable(items) {
        filtered = items;
        const tbody = document.querySelector('#dataTable tbody');
        if (!tbody) return;
        if (!items.length) {
            tbody.innerHTML = '<tr><td colspan="9" class="text-center text-muted py-4">No meetings found</td></tr>';
            return;
        }
        tbody.innerHTML = items.map((m, i) => {
            const type = m.meeting_type || 'general';
            const status = m.status || 'scheduled';
            const statusColors = { scheduled: 'warning', ongoing: 'primary', completed: 'secondary', cancelled: 'danger' };
            const when = fmtWhen(m);
            const venue = m.venue || (m.meeting_link ? 'Online' : '—');
            const venueMarkup = m.venue
                ? `<span class="badge bg-secondary-subtle text-secondary border"><i class="fas fa-map-marker-alt me-1"></i>${escapeHtml(m.venue)}</span>`
                : (m.meeting_link
                    ? `<a href="${escapeHtml(m.meeting_link)}" target="_blank" rel="noopener" class="badge bg-info-subtle text-info border text-decoration-none"><i class="fas fa-video me-1"></i>Join online</a>`
                    : '—');
            const att = m.attendee_count || 0;
            const myStatus = m.my_status || 'invited';
            const myBadge = myStatus === 'invited'
                ? '<span class="badge bg-secondary-subtle text-secondary">Invited</span>'
                : `<span class="badge bg-${myStatus === 'accepted' ? 'success' : myStatus === 'declined' ? 'danger' : 'warning'}">${escapeHtml(myStatus)}</span>`;
            return `<tr class="${status === 'cancelled' ? 'text-muted' : ''}">
                <td>${i + 1}</td>
                <td><strong>${escapeHtml(m.title)}</strong><br><span class="badge bg-${TYPE_COLORS[type] || 'secondary'}">${escapeHtml(TYPE_LABELS[type] || type)}</span></td>
                <td>${escapeHtml(when)}</td>
                <td>${venueMarkup}</td>
                <td>${escapeHtml(m.organizer_name || '—')}</td>
                <td>${att} <span class="text-muted small">(${m.accepted_count || 0} ✓)</span></td>
                <td>${myBadge}</td>
                <td><span class="badge bg-${statusColors[status] || 'secondary'}">${escapeHtml(status)}</span></td>
                <td>
                    <button class="btn btn-sm btn-outline-secondary me-1" onclick="StaffMeetingsController.viewMeeting(${i})" title="Details"><i class="fas fa-eye"></i></button>
                    ${myStatus === 'invited' ? `<button class="btn btn-sm btn-outline-success me-1" onclick="StaffMeetingsController.respond(${m.id},'accepted')" title="Accept"><i class="fas fa-check"></i></button>
                    <button class="btn btn-sm btn-outline-danger me-1" onclick="StaffMeetingsController.respond(${m.id},'declined')" title="Decline"><i class="fas fa-times"></i></button>` : ''}
                    <button class="btn btn-sm btn-outline-primary me-1" onclick="StaffMeetingsController.editMeeting(${i})" title="Edit"><i class="fas fa-edit"></i></button>
                    <button class="btn btn-sm btn-outline-info me-1" onclick="StaffMeetingsController.remind(${m.id})" title="Send reminder to attendees"><i class="fas fa-bell"></i></button>
                    <button class="btn btn-sm btn-outline-danger" onclick="StaffMeetingsController.deleteMeeting(${m.id}, '${escapeAttr(m.title)}')" title="Delete"><i class="fas fa-trash"></i></button>
                </td>
            </tr>`;
        }).join('');
    }

    function fmtWhen(m) {
        const d = new Date(m.meeting_date + 'T00:00:00');
        const day = isNaN(d) ? m.meeting_date : d.toLocaleDateString('en-KE', { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' });
        const s = (m.start_time || '').substring(0, 5);
        const e = (m.end_time || '').substring(0, 5);
        return day + (s ? ' · ' + s + (e && e !== s ? ' – ' + e : '') : '');
    }

    function filterData() {
        const s = (document.getElementById('searchInput')?.value || '').toLowerCase();
        const statusF = document.getElementById("statusFilter")?.value;
        const typeF = document.getElementById("typeFilter")?.value;
        const deptF = document.getElementById("departmentFilter")?.value;
        const now = new Date();
        let items = allData;
        if (s) items = items.filter((m) => JSON.stringify(m).toLowerCase().includes(s));
        if (typeF) items = items.filter((m) => (m.meeting_type || 'general') === typeF);
        if (deptF) items = items.filter((m) => String(m.department_id || '') === deptF);
        if (statusF) {
            items = items.filter((m) => {
                const st = m.status || 'scheduled';
                if (statusF === 'upcoming') return st === 'scheduled' && new Date(m.meeting_date) >= now;
                if (statusF === 'ongoing') return st === 'ongoing';
                if (statusF === 'completed') return st === 'completed' || (st === 'scheduled' && new Date(m.meeting_date) < now);
                if (statusF === 'cancelled') return st === 'cancelled';
                return true;
            });
        }
        renderTable(items);
    }

    function showAddModal() {
        document.getElementById("formModalTitle").innerHTML = '<i class="fas fa-calendar-plus me-2"></i>Schedule Meeting';
        document.getElementById('recordForm').reset();
        document.getElementById('recordId').value = '';
        document.getElementById("recordType").value = 'general';
        document.getElementById("recordDepartment").value = '';
        clearAttendeeSelection();
        new bootstrap.Modal(document.getElementById('formModal')).show();
    }

    function clearAttendeeSelection() {
        const sel = document.getElementById('recordAttendees');
        if (sel) { for (const o of sel.options) o.selected = false; }
    }

    function editMeeting(index) {
        const m = allData[index]; if (!m) return;
        document.getElementById("formModalTitle").innerHTML = '<i class="fas fa-edit me-2"></i>Edit Meeting';
        document.getElementById('recordId').value = m.id;
        document.getElementById("recordTitle").value = m.title || '';
        document.getElementById("recordType").value = m.meeting_type || 'general';
        document.getElementById("recordDate").value = m.meeting_date || '';
        document.getElementById("recordStartTime").value = (m.start_time || '').substring(0, 5);
        document.getElementById("recordEndTime").value = (m.end_time || '').substring(0, 5);
        document.getElementById("recordVenue").value = m.venue || '';
        document.getElementById("recordLink").value = m.meeting_link || '';
        document.getElementById("recordDepartment").value = m.department_id || '';
        document.getElementById("recordAgenda").value = m.agenda || '';
        document.getElementById("recordDescription").value = m.description || '';
        clearAttendeeSelection();
        // Pre-select existing attendees if we have them (from a detail fetch).
        StaffMeetingsController.getMeetingDetails(m.id).then((detail) => {
            const sel = document.getElementById('recordAttendees');
            if (!sel || !detail || !Array.isArray(detail.attendees)) return;
            const ids = new Set(detail.attendees.map((a) => String(a.staff_id)));
            for (const o of sel.options) { if (ids.has(o.value)) o.selected = true; }
        }).catch(() => {});
        new bootstrap.Modal(document.getElementById('formModal')).show();
    }

    async function getMeetingDetails(id) {
        try {
            const r = await window.API.meetings.get(id);
            return r?.data || r || null;
        } catch (e) { return null; }
    }

    async function saveRecord() {
        const id = document.getElementById('recordId').value;
        const title = document.getElementById("recordTitle").value.trim();
        const date = document.getElementById("recordDate").value;
        const startTime = document.getElementById("recordStartTime").value;
        if (!title) { showNotification("Meeting title is required", "warning"); return; }
        if (!date) { showNotification("Meeting date is required", "warning"); return; }
        if (!startTime) { showNotification("Start time is required", "warning"); return; }
        const endTime = document.getElementById("recordEndTime").value || '';
        if (endTime && endTime <= startTime) { showNotification("End time must be after start time", "warning"); return; }
        const attSel = document.getElementById('recordAttendees');
        const staffIds = attSel ? Array.from(attSel.selectedOptions).map((o) => Number(o.value)) : [];
        const payload = {
            title,
            meeting_type: document.getElementById("recordType").value,
            meeting_date: date,
            start_time: startTime,
            end_time: endTime || null,
            venue: document.getElementById("recordVenue").value.trim() || null,
            meeting_link: document.getElementById("recordLink").value.trim() || null,
            department_id: document.getElementById("recordDepartment").value || null,
            staff_ids: staffIds,
            agenda: document.getElementById("recordAgenda").value.trim() || null,
            description: document.getElementById("recordDescription").value.trim() || null,
        };
        try {
            if (id) {
                await window.API.meetings.update(id, payload);
            } else {
                await window.API.meetings.create(payload);
            }
            showNotification("Meeting saved - attendees notified and calendar updated", "success");
            bootstrap.Modal.getInstance(document.getElementById('formModal'))?.hide();
            await loadData();
        } catch (e) { showNotification(e.message || 'Failed to save meeting', 'danger'); }
    }

    async function respond(meetingId, status) {
        try {
            await window.API.meetings.respond(meetingId, status);
            showNotification(status === 'accepted' ? 'Meeting accepted' : 'Meeting declined', 'success');
            await loadData();
        } catch (e) { showNotification(e.message || 'Failed to respond', 'danger'); }
    }

    async function remind(meetingId) {
        if (!(await window.confirmAction('Send Reminder', 'Send a meeting reminder to all attendees?'))) return;
        try {
            await window.API.meetings.remind(meetingId);
            showNotification('Reminder sent to attendees', 'success');
        } catch (e) { showNotification(e.message || 'Failed to send reminder', 'danger'); }
    }

    async function deleteMeeting(id, title) {
        if (!(await window.confirmAction('Confirm Deletion', `Delete meeting "${title}"? Its calendar event is removed too.`, { confirmText: 'Delete', danger: true }))) return;
        try {
            await window.API.meetings.remove(id);
            showNotification('Meeting deleted', 'success');
            await loadData();
        } catch (e) { showNotification(e.message || 'Delete failed', 'danger'); }
    }

    async function viewMeeting(index) {
        const m = allData[index]; if (!m) return;
        const detail = await getMeetingDetails(m.id);
        const body = document.getElementById('detailModalBody');
        const title = document.getElementById('detailModalTitle');
        if (title) title.textContent = 'Meeting: ' + (m.title || '');
        const d = detail || m;
        const attendees = (d.attendees || []).map((a) =>
            `<li class="list-group-item d-flex justify-content-between align-items-center">
                <span>${escapeHtml(a.name)} <small class="text-muted">${escapeHtml(a.position || '')}${a.department_name ? ' · ' + escapeHtml(a.department_name) : ''}</small></span>
                <span class="badge bg-${a.status === 'accepted' ? 'success' : a.status === 'declined' ? 'danger' : a.status === 'maybe' ? 'warning' : 'secondary'}">${escapeHtml(a.status)}</span>
            </li>`).join('') || '<li class="list-group-item text-muted">No attendees</li>';
        body.innerHTML = `
            <p class="mb-1"><strong>When:</strong> ${escapeHtml(fmtWhen(m))}</p>
            <p class="mb-1"><strong>Venue:</strong> ${escapeHtml(m.venue || '—')} ${m.meeting_link ? `<span class="ms-2"><a href="${escapeHtml(m.meeting_link)}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-info"><i class="fas fa-video me-1"></i>Join online</a></span>` : ''}</p>
            <p class="mb-1"><strong>Organizer:</strong> ${escapeHtml(m.organizer_name || '—')}</p>
            ${m.department_name ? `<p class="mb-1"><strong>Department:</strong> ${escapeHtml(m.department_name)}</p>` : ''}
            ${m.agenda ? `<p class="mb-1"><strong>Agenda:</strong></p><pre class="bg-light p-2 rounded">${escapeHtml(m.agenda)}</pre>` : ''}
            ${m.description ? `<p class="mb-1"><strong>Notes:</strong> ${escapeHtml(m.description)}</p>` : ''}
            <h6 class="mt-3 fw-bold">Attendees (${attendees.length ? d.attendees.length : 0})</h6>
            <ul class="list-group mb-2">${attendees}</ul>
            <p class="small text-muted mb-0"><i class="bi bi-calendar-check me-1"></i>This meeting appears on the academic calendar and events pages.</p>`;
        new bootstrap.Modal(document.getElementById('detailModal')).show();
    }

    function exportCSV() {
        const rows = filtered.length ? filtered : allData;
        if (!rows.length) return;
        const headers = ['#', 'Meeting', 'Type', 'Date', 'Start', 'End', 'Venue', 'Link', 'Organizer', 'Attendees', 'Status'];
        const csvRows = rows.map((m, i) => [
            i + 1, m.title, TYPE_LABELS[m.meeting_type] || m.meeting_type, m.meeting_date,
            (m.start_time || '').substring(0, 5), (m.end_time || '').substring(0, 5),
            m.venue || '', m.meeting_link || '', m.organizer_name || '',
            m.attendee_count || 0, m.status || '',
        ]);
        let csv = headers.join(",") + "\n" + csvRows.map((r) => r.map((v) => '"' + (v || "") + '"').join(",")).join("\n");
        KingswayFileLifecycle.exportText(csv, "staff_meetings.csv", "text/csv");
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
    return { init, refresh: loadData, exportCSV, showAddModal, editMeeting, saveRecord, respond, remind, deleteMeeting, viewMeeting, getMeetingDetails };
})();
document.addEventListener('DOMContentLoaded', () => StaffMeetingsController.init());

window.StaffMeetingsController = StaffMeetingsController;
