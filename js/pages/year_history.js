const YearHistoryController = (() => {
    let allData = [];
    async function init() {
        await window.AuthContext?.ready();
        if (typeof AuthContext !== 'undefined' && !AuthContext.isAuthenticated()) { window.location.href = (window.APP_BASE || '') + '/index.php'; return; }
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
            const r =
              (await window.API.academic.getYearHistory().catch(() => null)) ||
              (await window.API.academic
                ?.getAllAcademicYears?.()
                .catch(() => null));
            allData = r?.data || r || [];
            renderStats(allData);
            renderTable(Array.isArray(allData) ? allData : []);
        } catch (e) { console.error('Load failed:', e); renderTable([]); }
    }
    function renderStats(data) {
        const items = Array.isArray(data) ? data : [];
        const el = (id, val) => {
          const e = document.getElementById(id);
          if (e) e.textContent = val;
        };
        el("statTotal", items.length);
        const totalStudents = items.reduce(
          (s, d) =>
            s +
            (parseInt(d.total_students || d.student_count || d.enrollment) ||
              0),
          0,
        );
        el("statStudents", totalStudents > 0 ? totalStudents : "--");
        const perfs = items
          .map(
            (d) =>
              parseFloat(d.performance_avg || d.mean_score || d.average) || 0,
          )
          .filter((v) => v > 0);
        el(
          "statPerformance",
          perfs.length > 0
            ? (perfs.reduce((s, v) => s + v, 0) / perfs.length).toFixed(1) + "%"
            : "--",
        );
        // The year-history endpoint has no graduate-count field. A completed
        // year is not evidence that any learners graduated, so do not present
        // the number of completed years as a graduation rate/count.
        el("statGraduation", "—");
    }
    function renderTable(items) {
        const tbody = document.getElementById('yearHistoryTableBody');
        if (!tbody) return;
        const stateRow = document.getElementById('yearHistoryTableState');
        const stateCell = stateRow?.querySelector('td');
        tbody.querySelectorAll('[data-year-history-row]').forEach((row) => row.remove());
        if (!items.length) {
          if (stateCell) stateCell.textContent = 'No academic year history found';
          if (stateRow) stateRow.hidden = false;
          return;
        }
        if (stateRow) stateRow.hidden = true;
        const template = document.getElementById('yearHistoryRowTemplate');
        if (!template) return;
        const fragment = document.createDocumentFragment();
        items.forEach((d, i) => {
            const status = d.status || (d.is_current ? "Current" : "Completed");
            const statusColor =
              status.toLowerCase() === "current" ||
              status.toLowerCase() === "active"
                ? "success"
                : "secondary";
            const perf =
              parseFloat(d.performance_avg || d.mean_score || d.average) || 0;
            const perfColor =
              perf >= 70
                ? "success"
                : perf >= 50
                  ? "warning"
                  : perf > 0
                    ? "danger"
                    : "secondary";
            const row = template.content.firstElementChild.cloneNode(true);
            const set = (field, value) => {
                const element = row.querySelector(`[data-field="${field}"]`);
                if (element) element.textContent = value ?? "—";
            };
            set('number', String(i + 1));
            set('year', d.name || d.year || d.academic_year || "--");
            set('start', d.start_date || "--");
            set('end', d.end_date || "--");
            set('terms', d.terms || d.term_count || "--");
            set('students', d.total_students || d.student_count || d.enrollment || "--");
            const performance = row.querySelector('[data-field="performance"]');
            if (performance) {
                performance.textContent = perf > 0 ? `${perf.toFixed(1)}%` : "--";
                performance.classList.add(`bg-${perfColor}`);
            }
            const statusElement = row.querySelector('[data-field="status"]');
            if (statusElement) {
                statusElement.textContent = status;
                statusElement.classList.add(`bg-${statusColor}`);
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
          filtered = filtered.filter(
            (item) => (item.status || "").toLowerCase() === f.toLowerCase(),
          );
        renderTable(filtered);
    }
    function exportCSV() {
        if (!allData.length) return;
        const headers = ['#', 'Year', 'Start Date', 'End Date', 'Terms', 'Students', 'Performance Avg', 'Status'];
        const rows = allData.map((d, i) => [
          i + 1,
          d.name || d.year || d.academic_year,
          d.start_date,
          d.end_date,
          d.terms || d.term_count,
          d.total_students || d.student_count,
          (
            parseFloat(d.performance_avg || d.mean_score || d.average) || 0
          ).toFixed(1),
          d.status || "",
        ]);
        let csv =
          headers.join(",") +
          "\n" +
          rows
            .map((r) => r.map((v) => '"' + (v || "") + '"').join(","))
            .join("\n");
        KingswayFileLifecycle.exportText(csv, "year_history.csv", "text/csv");
    }
    function showNotification(message, type) { window.showNotification(message, type); }
    return { init, refresh: loadData, exportCSV };
})();
document.addEventListener('DOMContentLoaded', () => YearHistoryController.init());

window.YearHistoryController = YearHistoryController;
