const YearCalendarController = (() => {
    let allData = [];
    let filtered = [];
    let currentDayDate = null;
    // The academic year being shown (null = current year until the filter loads).
    let yearId = null;
    let yearInfo = null;
    async function init() {
        await window.AuthContext?.ready();
        if (typeof AuthContext !== 'undefined' && !AuthContext.isAuthenticated()) { window.location.href = (window.APP_BASE || '') + '/index.php'; return; }
        setupEventListeners();
        const select = document.getElementById('academicYearFilter');
        if (window.AcademicYearFilter && select) {
            // Calls back once with the default (current) year, then on every change.
            await window.AcademicYearFilter.mount(select, {
                onChange: (id, year) => { yearId = id; yearInfo = year; loadData(); },
            });
        } else {
            await loadData();
        }
    }
    function setupEventListeners() {
        document.getElementById('searchInput')?.addEventListener('input', filterData);
        document.getElementById('filterSelect')?.addEventListener('change', filterData);
        document.getElementById('termFilter')?.addEventListener('change', filterData);
        document.getElementById('dateFilter')?.addEventListener('change', filterData);
        document.getElementById('yearCalendarBody')?.addEventListener('click', (event) => { const button = event.target.closest('[data-action=edit]'); if (button) openEditDay(button.dataset.calendarDayId || null); });
    }
    async function loadData() {
        try {
            const r = await window.API.academic.getYearCalendar(yearId).catch(() => null);
            allData = r?.data || r || [];
            if (!Array.isArray(allData)) allData = [];
            populateTermFilter(allData);
            populateTypeFilter(allData);
            renderStats(allData);
            filterData();
        } catch (e) { console.error('Load failed:', e); renderTable([]); }
    }
    const DAY_TYPES = [
        { code: 'school_day', label: 'School Day', color: 'success' },
        { code: 'half_day', label: 'Half Day', color: 'info' },
        { code: 'exam_day', label: 'Exam Day', color: 'danger' },
        { code: 'special_event', label: 'Special Event', color: 'primary' },
        { code: 'holiday', label: 'Holiday', color: 'warning' },
        { code: 'public_holiday', label: 'Public Holiday', color: 'warning' },
        { code: 'school_holiday', label: 'School Holiday', color: 'secondary' },
        { code: 'weekend', label: 'Weekend', color: 'secondary' },
    ];
    const typeCode = (d) => (d.type_code || d.type || d.event_type || 'school_day').toLowerCase();
    const typeLabel = (d) => {
        const c = typeCode(d);
        const t = DAY_TYPES.find((x) => x.code === c);
        return t ? t.label : (d.type_name || d.type || c);
    };
    const typeColor = (d) => {
        const t = DAY_TYPES.find((x) => x.code === typeCode(d));
        return t ? t.color : 'secondary';
    };
    function populateTermFilter(data) {
        const sel = document.getElementById('termFilter');
        if (!sel) return;
        const current = sel.value;
        // Terms and holiday breaks in calendar order (the rows arrive sorted).
        const terms = [];
        for (const d of data) {
            const t = termOf(d);
            if (t && !terms.includes(t)) terms.push(t);
        }
        sel.innerHTML = '<option value="">All terms and holidays</option>' +
            terms.map(t => `<option value="${escapeHtml(t)}">${escapeHtml(t)}</option>`).join('');
        sel.value = terms.includes(current) ? current : '';
    }
    function populateTypeFilter(data) {
        const sel = document.getElementById('filterSelect');
        if (!sel) return;
        const current = sel.value;
        const present = [];
        for (const d of data) {
            const c = typeCode(d);
            if (!present.includes(c)) present.push(c);
        }
        sel.innerHTML = '<option value="">All Types</option>' +
            DAY_TYPES.filter((t) => present.includes(t.code)).map(t =>
                `<option value="${t.code}">${t.label}</option>`).join('');
        sel.value = current;
    }
    function termOf(d) {
        return d.term_name || d.term || '';
    }
    // Week of the term or holiday break, numbered by the server (never by
    // month): week 1 starts on the period's first day.
    function weekOf(d) {
        const w = Number(d.week_number);
        return Number.isFinite(w) && w > 0 ? w : 0;
    }
    function dateCell(d) {
        const start = d.date || d.event_date || "--";
        const end = d.end_date && d.end_date !== start ? " → " + d.end_date : "";
        const startTime = d.start_time || (d.start_at ? String(d.start_at).substring(11, 16) : "");
        const endTime = d.end_time || (d.end_at ? String(d.end_at).substring(11, 16) : "");
        const times = [startTime, endTime].filter(Boolean).join(" – ");
        return start + end + (times ? " (" + times + ")" : "");
    }

    function renderStats(data) {
        // Count calendar days, once each. Free-standing events (rows with an
        // event_id and no calendar day) are listed in the table but are not days.
        const days = (Array.isArray(data) ? data : []).filter((d) => d.calendar_day_id || !d.event_id);
        const uniqueDates = (rows) => new Set(rows.map((d) => d.date)).size;
        const el = (id, val) => { const e = document.getElementById(id); if (e) e.textContent = val; };
        el("statEvents", uniqueDates(days));
        el("statTermDays", uniqueDates(days.filter((d) => ["school_day", "half_day", "exam_day"].includes(typeCode(d)))));
        el("statHolidays", uniqueDates(days.filter((d) => ["holiday", "public_holiday", "school_holiday"].includes(typeCode(d)))));
        const exams = (Array.isArray(data) ? data : []).filter((d) => typeCode(d) === "exam_day");
        el("statExamDays", uniqueDates(exams));
    }
    function renderTable(items) {
        filtered = items;
        const tbody = document.getElementById("yearCalendarBody");
        const stateRow = document.getElementById("yearCalendarState");
        const template = document.getElementById("yearCalendarRowTemplate");
        if (!tbody || !stateRow || !template) return;
        tbody.querySelectorAll("[data-year-calendar-row]").forEach((row) => row.remove());
        if (!items.length) {
            stateRow.querySelector("td").textContent = "No calendar entries found";
            stateRow.hidden = false;
            return;
        }
        stateRow.hidden = true;
        const now = new Date();
        const fragment = document.createDocumentFragment();
        items.forEach((day, index) => {
            const date = new Date(day.date || day.event_date);
            const row = template.content.cloneNode(true);
            const tr = row.querySelector("tr");
            tr.classList.toggle("table-warning", date.toDateString() === now.toDateString());
            row.querySelector('[data-fill="number"]').textContent = String(index + 1);
            row.querySelector('[data-fill="term"]').textContent = termOf(day) || "--";
            const week = weekOf(day);
            row.querySelector('[data-fill="week"]').textContent = week > 0 ? "Week " + week : "--";
            row.querySelector('[data-fill="date"]').textContent = dateCell(day);
            row.querySelector('[data-fill="day"]').textContent = Number.isNaN(date.getTime()) ? "--" : date.toLocaleDateString("en-US", { weekday: "short" });
            row.querySelector('[data-fill="event-name"]').textContent = day.name || day.event || day.title || "--";
            row.querySelector('[data-fill="manual"]').hidden = !day.is_manual;
            const events = row.querySelector('[data-fill="events"]');
            (day.events || []).forEach((event) => {
                const badge = document.getElementById("yearCalendarEventBadgeTemplate").content.cloneNode(true);
                const node = badge.querySelector("span");
                node.textContent = event.title || "Event";
                node.title = [event.type, event.location, event.status].filter(Boolean).join(" • ");
                events.appendChild(badge);
            });
            const type = row.querySelector('[data-fill="type"]');
            type.textContent = typeLabel(day);
            type.classList.add("bg-" + typeColor(day));
            row.querySelector('[data-action="edit"]').dataset.calendarDayId = day.calendar_day_id ?? "";
            fragment.appendChild(row);
        });
        tbody.insertBefore(fragment, stateRow);
    }

    function filterData() {
        const s = (document.getElementById('searchInput')?.value || '').toLowerCase();
        const f = document.getElementById("filterSelect")?.value;
        const t = document.getElementById("termFilter")?.value;
        const dateFilter = document.getElementById("dateFilter")?.value;
        let items = allData;
        if (s) items = items.filter((item) => JSON.stringify(item).toLowerCase().includes(s));
        if (f) items = items.filter((item) => typeCode(item) === f);
        if (t) items = items.filter((item) => termOf(item) === t);
        if (dateFilter) items = items.filter((item) => (item.date || item.event_date) === dateFilter);
        renderTable(items);
    }
    function openEditDay(dayId) {
        if (!dayId) { showNotification("This entry cannot be edited", "warning"); return; }
        const day = allData.find((d) => String(d.calendar_day_id) === String(dayId));
        if (!day) { showNotification("Calendar day not found", "warning"); return; }
        document.getElementById('dayId').value = dayId;
        currentDayDate = day.date || null;
        const ctx = document.getElementById('dayContext');
        if (ctx) ctx.textContent = (day.date || '') + (termOf(day) ? ' \u2022 ' + termOf(day) + (weekOf(day) > 0 ? ' (Week ' + weekOf(day) + ')' : '') : '');
        document.getElementById('dayType').value = typeCode(day);
        document.getElementById('dayTitle').value = day.title || day.name || '';
        document.getElementById('dayDescription').value = day.description || '';
        renderDayEvents(day);
        bootstrap.Modal.getOrCreateInstance(document.getElementById('dayModal')).show();
    }
    function renderDayEvents(day) {
        const list = document.getElementById('dayEventsList');
        if (!list) return;
        const evts = day.events || [];
        list.innerHTML = evts.length
            ? evts.map((e) => `<div class="d-flex justify-content-between align-items-center border rounded px-2 py-1 mb-1">
                <div><strong>${escapeHtml(e.title)}</strong> <small class="text-muted">${escapeHtml(e.type || '')}${e.location ? ' \u2022 ' + escapeHtml(e.location) : ''}${rangeSuffix(e)}</small></div>
            </div>`).join('')
            : '<p class="text-muted small mb-0">No events on this day yet.</p>';
    }
    function rangeSuffix(e) {
        const start = String(e.start_at || '');
        const end = String(e.end_at || '');
        const sT = e.start_time || (start ? start.substring(11, 16) : '');
        const eT = e.end_time || (end ? end.substring(11, 16) : '');
        const sD = (e.start_date || (start ? start.substring(0, 10) : ''));
        const eD = (e.end_date || (end ? end.substring(0, 10) : ''));
        const parts = [];
        if (sT) parts.push('\u2022 ' + sT);
        if (eD && eD !== sD) { parts.push('\u2192 ' + eD + (eT ? ' ' + eT : '')); }
        else if (eT && eT !== sT) { parts.push('\u2192 ' + eT); }
        return parts.length ? ' ' + parts.join(' ') : '';
    }
    async function addEvent() {
        const title = document.getElementById('eventTitle').value.trim();
        if (!title) { showNotification('Event title is required', 'warning'); return; }
        if (!currentDayDate) { showNotification('Select a day first', 'warning'); return; }
        const startTime = document.getElementById('eventStartTime').value || '';
        const endTime = document.getElementById('eventEndTime').value || '';
        const endDate = document.getElementById('eventEndDate').value || '';
        if (endDate && endDate < currentDayDate) { showNotification('End date cannot be before the start date', 'warning'); return; }
        const payload = {
            name: title,
            start_date: currentDayDate,
            type: document.getElementById('eventType').value || 'general',
            location: document.getElementById('eventLocation').value.trim() || null,
            status: 'upcoming',
        };
        if (startTime) payload.start_time = startTime;
        if (endDate) { payload.end_date = endDate; payload.end_time = endTime || '17:00'; }
        else if (endTime) { payload.end_time = endTime; payload.end_date = currentDayDate; }
        try {
            await window.API.schedules.createEvent(payload);
            document.getElementById('eventTitle').value = '';
            document.getElementById('eventLocation').value = '';
            document.getElementById('eventStartTime').value = '';
            document.getElementById('eventEndTime').value = '';
            document.getElementById('eventEndDate').value = '';
            showNotification('Event added', 'success');
            if (window.DataStore) window.DataStore.invalidateMany(['academic', 'schedules']);
            await loadData();
            const day = allData.find((d) => d.date === currentDayDate);
            if (day) renderDayEvents(day);
        } catch (e) { showNotification(e.message || 'Failed to add event', 'danger'); }
    }
    async function deleteEvent(eventId) {
        if (!await window.confirmAction('Confirm', 'Delete this event?')) return;
        try {
            await window.API.schedules.deleteEvent(eventId);
            showNotification('Event deleted', 'success');
            if (window.DataStore) window.DataStore.invalidateMany(['academic', 'schedules']);
            await loadData();
            const day = allData.find((d) => d.date === currentDayDate);
            if (day) renderDayEvents(day);
        } catch (e) { showNotification(e.message || 'Failed to delete event', 'danger'); }
    }
    async function saveDay() {
        const id = document.getElementById('dayId').value;
        if (!id) { showNotification("No calendar day selected", "warning"); return; }
        const payload = {
            day_type: document.getElementById('dayType').value,
            title: document.getElementById('dayTitle').value,
            description: document.getElementById('dayDescription').value,
        };
        try {
            await window.API.academic.updateCalendarDay(id, payload);
            showNotification("Calendar day updated", "success");
            bootstrap.Modal.getInstance(document.getElementById('dayModal'))?.hide();
            if (window.DataStore) window.DataStore.invalidateMany(['academic']);
            await loadData();
        } catch (e) { showNotification(e.message || 'Failed to update day', 'danger'); }
    }
    function exportCSV() {
        const rows = filtered.length ? filtered : allData;
        if (!rows.length) return;
        const headers = ['#', 'Term / holiday', 'Week', 'Date', 'Day', 'Event', 'Type'];
        const csvRows = rows.map((d, i) => {
            return [
                i + 1,
                termOf(d),
                weekOf(d) || '',
                d.date || d.event_date,
                d.day_name || new Date(d.date || d.event_date).toLocaleDateString("en-US", { weekday: "short" }),
                d.name || d.event || d.title,
                typeLabel(d),
            ];
        });
        const cell = (v) => '"' + String(v ?? '').replace(/"/g, '""') + '"';
        const csv = [headers, ...csvRows].map((r) => r.map(cell).join(",")).join("\r\n");
        const yearLabel = String(yearInfo?.year_code || 'current').replace(/[^0-9A-Za-z]+/g, '-');
        KingswayFileLifecycle.exportText('\ufeff' + csv, `year_calendar_${yearLabel}.csv`, "text/csv;charset=utf-8");
    }
    /** The official term-by-term calendar PDF for the selected year. */
    async function downloadPdf() {
        if (window.AuthContext?.canPrint && !window.AuthContext.canPrint('academic')) {
            window.showNotification?.('You do not have permission to print', 'warning');
            return;
        }
        if (!yearId || !window.PrintManager?.printAcademicCalendar) return;
        const label = String(yearInfo?.year_code || yearInfo?.year_name || yearId).replace(/[^0-9A-Za-z-]+/g, '-');
        await window.PrintManager.printAcademicCalendar({ academicYear: yearId, filename: `academic_calendar_${label}` }).catch(() => {});
    }

    function print() {
        window.print();
    }
    async function markExamWeek() {
        if (!currentDayDate) { showNotification('Select a day first', 'warning'); return; }
        if (!(await window.confirmAction('Confirm Exam Week', 'Mark the whole Mon-Fri week of ' + currentDayDate + ' as EXAM WEEK?\nExam days will be created for every active class. This cannot be undone per day (you can clear individual days afterwards).'))) return;
        try {
            const r = await window.API.schedules.markExamWeek({ date: currentDayDate });
            const msg = r?.data?.message || r?.message || 'Exam week marked';
            showNotification(msg, 'success');
            bootstrap.Modal.getInstance(document.getElementById('dayModal'))?.hide();
            if (window.DataStore) window.DataStore.invalidateMany(['academic', 'schedules']);
            await loadData();
        } catch (e) { showNotification(e.message || 'Failed to mark exam week', 'danger'); }
    }
    function escapeHtml(s) {
        return String(s || "")
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;");
    }
    function showNotification(message, type) { window.showNotification(message, type); }
    return { init, refresh: loadData, exportCSV, print, downloadPdf, openEditDay, saveDay, addEvent, deleteEvent, markExamWeek };
})();
document.addEventListener('DOMContentLoaded', () => YearCalendarController.init());

window.YearCalendarController = YearCalendarController;
