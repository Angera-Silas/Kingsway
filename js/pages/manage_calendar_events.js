const ManageCalendarEventsController = (() => {
    let allData = [];
    async function init() {
        await window.AuthContext?.ready();
        if (typeof AuthContext !== 'undefined' && !AuthContext.isAuthenticated()) { window.location.href = (window.APP_BASE || '') + '/index.php'; return; }
        await loadData(); setupEventListeners();
    }
    function setupEventListeners() {
        document.getElementById('searchInput')?.addEventListener('input', filterData);
        document.getElementById('filterSelect')?.addEventListener('change', filterData);
        document.getElementById('dateFilter')?.addEventListener('change', filterData);
    }
    async function loadData() {
        try {
            await window.API.schedules.syncEvents().catch(() => null);
            const r = await window.API.schedules.getEvents().catch(() => null);
            allData = r?.data || r || [];
            populateTypeFilter();
            renderStats(allData);
            renderTable(Array.isArray(allData) ? allData : []);
        } catch (e) { console.error('Load failed:', e); renderTable([]); }
    }
    async function syncNow() {
        showNotification('Syncing calendar and events...', 'info');
        try {
            await window.API.schedules.syncEvents();
            showNotification('Calendar synced successfully', 'success');
            await loadData();
        } catch (e) {
            showNotification(e.message || 'Sync failed', 'danger');
        }
    }
    function populateTypeFilter() {
        const sel = document.getElementById('filterSelect');
        if (!sel || sel.dataset.populated) return;
        const seen = new Set();
        allData.forEach((d) => {
            const t = (d.type || d.event_type || d.category || 'general').toLowerCase();
            if (!seen.has(t)) { seen.add(t); const o = document.createElement('option'); o.value = t; o.textContent = t.replace(/_/g, ' '); sel.appendChild(o); }
        });
        sel.dataset.populated = '1';
    }
    function renderStats(data) {
        const items = Array.isArray(data) ? data : [];
        const el = (id, val) => {
          const e = document.getElementById(id);
          if (e) e.textContent = val;
        };
        const now = new Date();
        const currentMonth = now.getMonth();
        const currentYear = now.getFullYear();
        el("statTotal", items.length);
        el(
          "statUpcoming",
          items.filter(
            (d) => new Date(d.date || d.event_date || d.start_date) > now,
          ).length,
        );
        el(
          "statMonth",
          items.filter((d) => {
            const ed = new Date(d.date || d.event_date || d.start_date);
            return (
              ed.getMonth() === currentMonth && ed.getFullYear() === currentYear
            );
          }).length,
        );
        el(
          "statPast",
          items.filter(
            (d) => new Date(d.date || d.event_date || d.start_date) < now,
          ).length,
        );
    }
    function renderTable(items) {
        const tbody = document.querySelector('#dataTable tbody');
        if (!tbody) return;
        if (!items.length) {
          tbody.innerHTML =
            '<tr><td colspan="9" class="text-center text-muted py-4">No calendar events found</td></tr>';
          return;
        }
        const now = new Date();
        tbody.innerHTML = items
          .map((d, i) => {
            const eventDate = new Date(d.date || d.event_date || d.start_date);
            const isPast = eventDate < now;
            const isToday = eventDate.toDateString() === now.toDateString();
            const cat = (d.category || d.type || d.event_type || "general").toLowerCase();
            const catColors = {
              exam: "danger",
              holiday: "success",
              school_holiday: "success",
              public_holiday: "success",
              half_day: "warning",
              special_event: "info",
              opening: "primary",
              closing: "primary",
              meeting: "primary",
              sports: "warning",
              cultural: "info",
              general: "secondary",
            };
            const status = (d.status || 'upcoming').toLowerCase();
            const statusColors = { upcoming: "warning", ongoing: "success", past: "secondary", cancelled: "danger", completed: "success" };
            const termLabel = d.term_name ? `${d.term_name}${d.week_number ? ` · Wk ${d.week_number}` : ''}` : '—';
            const startDt = d.start_date || d.date || d.event_date || "--";
            const startT = d.start_time || (d.start_at ? String(d.start_at).substring(11, 16) : '') || '';
            const endDt = d.end_date || (d.end_at ? String(d.end_at).substring(0, 10) : '') || '';
            const endT = d.end_time || (d.end_at ? String(d.end_at).substring(11, 16) : '') || '';
            const startDisplay = startDt + (startT ? ' ' + startT : '');
            const endDisplay = (endDt && endDt !== (d.start_date || d.date || d.event_date))
              ? endDt + (endT ? ' ' + endT : '')
              : (endT && endT !== startT ? startDt + ' ' + endT : '');
            return `<tr class="${isToday ? "table-warning" : isPast ? "text-muted" : ""}">
                <td>${i + 1}</td>
                <td><strong>${escapeHtml(d.name || d.title || d.event_name || "--")}</strong></td>
                <td>${escapeHtml(startDisplay)}</td>
                <td>${escapeHtml(endDisplay || "--")}</td>
                <td><span class="badge bg-${catColors[cat] || "secondary"}">${escapeHtml(cat.replace(/_/g, ' '))}</span></td>
                <td>${escapeHtml(termLabel)}</td>
                <td><span class="badge bg-${statusColors[status] || "secondary"}">${escapeHtml(status)}</span></td>
                <td>${escapeHtml(d.location || d.venue || "--")}</td>
                <td>
                    <button class="btn btn-sm btn-outline-primary me-1" onclick="ManageCalendarEventsController.editRecord(${i})" title="Edit"><i class="fas fa-edit"></i></button>
                    <button class="btn btn-sm btn-outline-danger" onclick="ManageCalendarEventsController.deleteRecord('${d.id}')" title="Delete"><i class="fas fa-trash"></i></button>
                </td>
            </tr>`;
          })
          .join("");
    }
    function filterData() {
        const s = (document.getElementById('searchInput')?.value || '').toLowerCase();
        const f = document.getElementById("filterSelect")?.value;
        const dateF = document.getElementById("dateFilter")?.value;
        let filtered = allData;
        if (s)
          filtered = filtered.filter((item) =>
            JSON.stringify(item).toLowerCase().includes(s),
          );
        if (f)
          filtered = filtered.filter((item) => {
            const now = new Date();
            const ed = new Date(
              item.date || item.event_date || item.start_date,
            );
            if (f === "upcoming") return ed > now;
            if (f === "past") return ed < now;
            if (f === "today") return ed.toDateString() === now.toDateString();
            return (
              (item.category || item.type || item.event_type || "").toLowerCase() ===
              f.toLowerCase()
            );
          });
        if (dateF)
          filtered = filtered.filter((item) =>
            (item.date || item.event_date || item.start_date || "").includes(
              dateF,
            ),
          );
        renderTable(filtered);
    }
    function showAddModal() {
        document.getElementById("formModalTitle").innerHTML =
          '<i class="fas fa-calendar-plus me-2"></i>Add Event';
        document.getElementById('recordForm').reset(); document.getElementById('recordId').value = '';
        document.getElementById("recordType").value = 'special_event';
        new bootstrap.Modal(document.getElementById('formModal')).show();
    }
    function editRecord(index) {
        const item = allData[index]; if (!item) return;
        document.getElementById("formModalTitle").innerHTML =
          '<i class="fas fa-edit me-2"></i>Edit Event';
        document.getElementById('recordId').value = item.id || '';
        document.getElementById("recordName").value =
          item.name || item.title || item.event_name || "";
        document.getElementById('recordDescription').value = item.description || '';
        document.getElementById("recordStartDate").value =
          item.start_date || item.date || item.event_date || "";
        document.getElementById("recordStartTime").value =
          item.start_time || (item.start_at ? String(item.start_at).substring(11, 16) : "") || "";
        document.getElementById("recordEndDate").value = item.end_date || "";
        document.getElementById("recordEndTime").value =
          item.end_time || (item.end_at ? String(item.end_at).substring(11, 16) : "") || "";
        document.getElementById("recordType").value =
          (item.category || item.type || item.event_type || "general").toLowerCase();
        document.getElementById("recordLocation").value = item.location || item.venue || "";
        new bootstrap.Modal(document.getElementById('formModal')).show();
    }
    async function saveRecord() {
        const id = document.getElementById('recordId').value;
        const name = document.getElementById("recordName").value;
        const startDate = document.getElementById("recordStartDate").value;
        if (!name) { showNotification("Event name is required", "warning"); return; }
        if (!startDate) { showNotification("Event start date is required", "warning"); return; }
        const payload = {
            name,
            description: document.getElementById("recordDescription").value || null,
            start_date: startDate,
            start_time: document.getElementById("recordStartTime").value || null,
            end_date: document.getElementById("recordEndDate").value || null,
            end_time: document.getElementById("recordEndTime").value || null,
            type: document.getElementById("recordType").value,
            location: document.getElementById("recordLocation").value || null,
            status: "upcoming",
        };
        try {
            if (id) {
                await window.API.schedules.updateEvent(id, payload);
            } else {
                await window.API.schedules.createEvent(payload);
            }
            showNotification("Event saved successfully", "success");
            bootstrap.Modal.getInstance(document.getElementById('formModal'))?.hide(); await loadData();
        } catch (e) { showNotification(e.message || 'Failed to save', 'danger'); }
    }
    async function deleteRecord(id) {
        if (!(await window.confirmAction('Confirm Deletion', "Delete this calendar event? This will also clear the linked calendar day if applicable.", { confirmText: 'Delete', danger: true }))) return;
        try {
          await window.API.schedules.deleteEvent(id);
          showNotification("Event deleted", "success");
          await loadData();
        } catch (e) {
          showNotification(e.message || "Delete failed", "danger");
        }
    }
    function exportCSV() {
        if (!allData.length) return;
        const headers = [
          "#",
          "Event Name",
          "Start",
          "End",
          "Type",
          "Status",
          "Location",
          "Description",
        ];
        const rows = allData.map((d, i) => [
          i + 1,
          d.name || d.title || d.event_name,
          (d.start_date || d.date || d.event_date || "") + (d.start_time ? " " + d.start_time : ""),
          (d.end_date || "") + (d.end_time ? " " + d.end_time : ""),
          d.category || d.type,
          d.status,
          d.location || d.venue,
          d.description || "",
        ]);
        let csv =
          headers.join(",") +
          "\n" +
          rows
            .map((r) => r.map((v) => '"' + (v || "") + '"').join(","))
            .join("\n");
        KingswayFileLifecycle.exportText(csv, "calendar_events.csv", "text/csv");
    }
    function escapeHtml(s) {
      return String(s || "")
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;");
    }
    function showNotification(message, type) { window.showNotification(message, type); }
    return { init, refresh: loadData, syncNow, exportCSV, showAddModal, editRecord, saveRecord, deleteRecord };
})();
document.addEventListener('DOMContentLoaded', () => ManageCalendarEventsController.init());
