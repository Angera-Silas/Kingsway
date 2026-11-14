const ClassCapacityController = (() => {
    let allData = [];
    async function init() {
        await window.AuthContext?.ready();
        if (typeof AuthContext !== 'undefined' && !AuthContext.isAuthenticated()) { window.location.href = (window.APP_BASE || '') + '/index.php'; return; }

        // Initialize Academic Context if available
        if (window.AcademicContext) {
          // Subscribe to context changes
          window.AcademicContext.subscribe((context, event, data) => {
            if (event === 'yearChanged' || event === 'initialized' || event === 'refreshed') {
              loadData();
            }
          });

          // Ensure context is loaded
          if (!window.AcademicContext.isLoaded()) {
            await window.AcademicContext.init();
          }
        }

        await loadData(); setupEventListeners();
    }
    function setupEventListeners() {
        document.getElementById('searchInput')?.addEventListener('input', filterData);
        document
          .getElementById("filterSelect")
          ?.addEventListener("change", filterData);
    }
    async function loadData() {
        try {
            const r = window.API.academic?.getClassCapacity
              ? await window.API.academic.getClassCapacity()
              : await window.API.apiCall("/academic/class-capacity", "GET");
            allData = r?.data || r || [];
            renderStats(allData);
            renderTable(Array.isArray(allData) ? allData : []);
        } catch (e) { console.error('Load failed:', e); renderTable([]); }
    }
    function renderStats(data) {
        const items = Array.isArray(data) ? data : [];
        const totalCapacity = items.reduce(
          (s, c) => s + (parseInt(c.capacity) || 40),
          0,
        );
        const totalEnrolled = items.reduce(
          (s, c) =>
            s + (parseInt(c.enrolled || c.student_count || c.current_students || c.students) || 0),
          0,
        );
        const available = totalCapacity - totalEnrolled;
        const utilization =
          totalCapacity > 0
            ? Math.round((totalEnrolled / totalCapacity) * 100)
            : 0;
        const el = (id, val) => {
          const e = document.getElementById(id);
          if (e) e.textContent = val;
        };
        el("statCapacity", totalCapacity);
        el("statEnrolled", totalEnrolled);
        el("statAvailable", Math.max(0, available));
        el("statUtil", utilization + "%");
    }
    function renderTable(items) {
        const tbody = document.getElementById('classCapacityTableBody');
        if (!tbody) return;
        const stateRow = document.getElementById('classCapacityTableState');
        const stateCell = stateRow?.querySelector('td');
        tbody.querySelectorAll('[data-capacity-row]').forEach((row) => row.remove());
        if (!items.length) {
          if (stateCell) stateCell.textContent = 'No class data found';
          if (stateRow) stateRow.hidden = false;
          return;
        }
        if (stateRow) stateRow.hidden = true;
        const template = document.getElementById('classCapacityRowTemplate');
        if (!template) return;
        const fragment = document.createDocumentFragment();
        items.forEach((c, i) => {
            const capacity = parseInt(c.capacity) || 40;
            const enrolled =
              parseInt(c.enrolled || c.student_count || c.current_students || c.students) || 0;
            const available = Math.max(0, capacity - enrolled);
            const util =
              capacity > 0 ? Math.round((enrolled / capacity) * 100) : 0;
            const barColor =
              util >= 90 ? "danger" : util >= 70 ? "warning" : "success";
            const row = template.content.firstElementChild.cloneNode(true);
            const set = (field, value) => {
                const element = row.querySelector(`[data-field="${field}"]`);
                if (element) element.textContent = value ?? "—";
            };
            set('number', String(i + 1));
            set('class', c.name || c.class_name || "");
            set('stream', c.stream || c.stream_name || "--");
            set('capacity', String(capacity));
            set('enrolled', String(enrolled));
            set('available', String(available));
            set('utilization', `${util}%`);
            const bar = row.querySelector('[data-field="bar"]');
            if (bar) {
                bar.classList.add(`bg-${barColor}`);
                bar.style.width = `${util}%`;
            }
            const status = row.querySelector('[data-field="status"]');
            if (status) {
                status.classList.add(`bg-${barColor}`);
                status.textContent = util >= 90 ? "Full" : util >= 70 ? "Near Full" : "Available";
            }
            fragment.appendChild(row);
        });
        tbody.insertBefore(fragment, stateRow || null);
    }
    function filterData() {
        const s = (document.getElementById('searchInput')?.value || '').toLowerCase();
        const f = document.getElementById("filterSelect")?.value;
        let filtered = allData;
        if (s)
          filtered = filtered.filter((item) =>
            JSON.stringify(item).toLowerCase().includes(s),
          );
        if (f)
          filtered = filtered.filter((item) => {
            const util =
              parseInt(item.capacity) > 0
                ? Math.round(
                    (parseInt(item.enrolled || item.student_count || item.current_students || 0) /
                      parseInt(item.capacity)) *
                      100,
                  )
                : 0;
            if (f === "full") return util >= 90;
            if (f === "near_full") return util >= 70 && util < 90;
            if (f === "available") return util < 70;
            return true;
          });
        renderTable(filtered);
    }
    function exportCSV() {
        if (!allData.length) return;
        if (!window.PrintManager) {
          showNotification("PrintManager not available", "error");
          return;
        }
        const headers = ['#', 'Class', 'Stream', 'Capacity', 'Enrolled', 'Available', 'Utilization', 'Status'];
        const rows = allData.map((c, i) => {
          const cap = parseInt(c.capacity) || 40;
          const enr = parseInt(c.enrolled || c.student_count || c.current_students || 0);
          const util = cap > 0 ? Math.round((enr / cap) * 100) : 0;
          return {
            index: i + 1,
            class_name: c.name || c.class_name,
            stream_name: c.stream || c.stream_name || "",
            capacity: cap,
            enrolled: enr,
            available: Math.max(0, cap - enr),
            utilization: util + "%",
            status: util >= 90 ? "Full" : util >= 70 ? "Near Full" : "Available",
          };
        });
        window.PrintManager.exportToCSV({
          filename: `class_capacity_${new Date().toISOString().slice(0, 10)}.csv`,
          columns: [
            { key: "index", label: headers[0] },
            { key: "class_name", label: headers[1] },
            { key: "stream_name", label: headers[2] },
            { key: "capacity", label: headers[3] },
            { key: "enrolled", label: headers[4] },
            { key: "available", label: headers[5] },
            { key: "utilization", label: headers[6] },
            { key: "status", label: headers[7] },
          ],
          rows,
        });
    }
    function escapeHtml(s) {
      return String(s || "")
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;");
    }
    function showNotification(message, type) { window.showNotification(message, type); }
    return { init, refresh: loadData, exportCSV };
})();
document.addEventListener('DOMContentLoaded', () => ClassCapacityController.init());

window.ClassCapacityController = ClassCapacityController;
window.APIRealtime?.register?.("class_capacity", ClassCapacityController, ["academic"]);
