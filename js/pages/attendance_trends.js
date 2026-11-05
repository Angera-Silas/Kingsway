const AttendanceTrendsController = (() => {
    let allData = [];
    let charts = {};
    async function init() {
        await window.AuthContext?.ready();
        if (typeof AuthContext !== 'undefined' && !AuthContext.isAuthenticated()) { window.location.href = (window.APP_BASE || '') + '/index.php'; return; }
        await loadData(); setupEventListeners();
    }
    function setupEventListeners() {
        document.getElementById('searchInput')?.addEventListener('input', filterData);
        document.getElementById('filterSelect')?.addEventListener('change', filterData);
        document
          .getElementById("dateFilter")
          ?.addEventListener("change", loadData);
    }
    async function loadData() {
        try {
            const dateFilter = document.getElementById("dateFilter")?.value;
            const r =
              (await window.API.apiCall(
                "/attendance/trends",
                "GET",
                null,
                dateFilter ? { date: dateFilter } : {},
              ).catch(() => null)) ||
              (await window.API.attendance
                ?.getClassAttendance?.()
                .catch(() => null));
            allData = r || [];
            renderStats(allData); renderTable(Array.isArray(allData) ? allData : []);
            renderCharts(allData);
        } catch (e) { console.error('Load failed:', e); renderTable([]); }
    }
    function renderStats(data) {
        const items = Array.isArray(data) ? data : [];
        const el = (id, val) => {
          const e = document.getElementById(id);
          if (e) e.textContent = val;
        };
        const totalPresent = items.reduce(
          (s, d) => s + (parseInt(d.present) || 0),
          0,
        );
        const totalAbsent = items.reduce(
          (s, d) => s + (parseInt(d.absent) || 0),
          0,
        );
        const totalStudents = totalPresent + totalAbsent;
        const todayRate =
          totalStudents > 0
            ? Math.round((totalPresent / totalStudents) * 100)
            : 0;

        el("statToday", todayRate + "%");
        el(
          "statWeekly",
          items.length > 0
            ? Math.round(
                items
                  .slice(-5)
                  .reduce(
                    (s, d) =>
                      s + (parseFloat(d.attendance_rate || d.percentage) || 0),
                    0,
                  ) / Math.min(items.length, 5),
              ) + "%"
            : "0%",
        );
        el(
          "statMonthly",
          items.length > 0
            ? Math.round(
                items.reduce(
                  (s, d) =>
                    s + (parseFloat(d.attendance_rate || d.percentage) || 0),
                  0,
                ) / items.length,
              ) + "%"
            : "0%",
        );
        el(
          "statAbsentees",
          totalAbsent ||
            items.filter(
              (d) => (parseFloat(d.attendance_rate || d.percentage) || 0) < 75,
            ).length,
        );
    }
    function renderTable(items) {
        const tbody = document.querySelector('#dataTable tbody');
        if (!tbody) return;
        const stateRow = document.getElementById('attendanceTrendsState');
        const template = document.getElementById('attendanceTrendRowTemplate');
        tbody.querySelectorAll('[data-attendance-trend-row]').forEach((row) => row.remove());
        if (!items.length) {
          const cell = stateRow?.querySelector('td');
          if (cell) cell.textContent = 'No attendance trend data found';
          if (stateRow) stateRow.hidden = false;
          return;
        }
        if (stateRow) stateRow.hidden = true;
        const fragment = document.createDocumentFragment();
        items.forEach((d, i) => {
            const present = parseInt(d.present) || 0;
            const absent = parseInt(d.absent) || 0;
            const late = parseInt(d.late) || 0;
            const total = present + absent;
            const rate =
              total > 0
                ? ((present / total) * 100).toFixed(1)
                : d.attendance_rate || d.percentage || 0;
            const rateColor =
              rate >= 90 ? "success" : rate >= 75 ? "warning" : "danger";
            const row = template.content.cloneNode(true);
            row.querySelector('[data-fill="number"]').textContent = String(i + 1);
            row.querySelector('[data-fill="date"]').textContent = d.date || d.week || d.period || "--";
            row.querySelector('[data-fill="class"]').textContent = d.class_name || d.name || "All";
            row.querySelector('[data-fill="present"]').textContent = String(present);
            row.querySelector('[data-fill="absent"]').textContent = String(absent);
            row.querySelector('[data-fill="late"]').textContent = String(late);
            const badge = row.querySelector('[data-fill="rate"]');
            badge.textContent = rate + "%";
            badge.classList.add("bg-" + rateColor);
            row.querySelector("tr").dataset.attendanceTrendRow = "1";
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
            const rate =
              parseFloat(item.attendance_rate || item.percentage) || 0;
            if (f === "excellent") return rate >= 90;
            if (f === "good") return rate >= 75 && rate < 90;
            if (f === "poor") return rate < 75;
            return true;
          });
        renderTable(filtered);
    }
    function renderCharts(data) {
        const items = Array.isArray(data) ? data : [];
        if (typeof Chart === "undefined") return;

        const ctx1 = document.getElementById('trendChart')?.getContext('2d');
        if (ctx1) {
          if (charts.trend) charts.trend.destroy();
          charts.trend = new Chart(ctx1, {
            type: "line",
            data: {
              labels: items
                .slice(-15)
                .map((d) => d.date || d.week || d.period || ""),
              datasets: [
                {
                  label: "Attendance Rate (%)",
                  data: items.slice(-15).map((d) => {
                    const p = parseInt(d.present) || 0;
                    const a = parseInt(d.absent) || 0;
                    return p + a > 0
                      ? ((p / (p + a)) * 100).toFixed(1)
                      : d.attendance_rate || d.percentage || 0;
                  }),
                  borderColor: "#0d6efd",
                  tension: 0.3,
                  fill: true,
                  backgroundColor: "rgba(13,110,253,0.1)",
                },
              ],
            },
            options: {
              responsive: true,
              scales: { y: { beginAtZero: false, min: 50, max: 100 } },
              plugins: { legend: { display: true } },
            },
          });
        }

        const ctx2 = document.getElementById('comparisonChart')?.getContext('2d');
        if (ctx2) {
          if (charts.comparison) charts.comparison.destroy();
          charts.comparison = new Chart(ctx2, {
            type: "bar",
            data: {
              labels: items
                .slice(0, 10)
                .map((d) => d.class_name || d.name || d.date || ""),
              datasets: [
                {
                  label: "Present",
                  data: items.slice(0, 10).map((d) => parseInt(d.present) || 0),
                  backgroundColor: "rgba(25,135,84,0.7)",
                },
                {
                  label: "Absent",
                  data: items.slice(0, 10).map((d) => parseInt(d.absent) || 0),
                  backgroundColor: "rgba(220,53,69,0.7)",
                },
              ],
            },
            options: {
              responsive: true,
              scales: {
                x: { stacked: true },
                y: { stacked: true, beginAtZero: true },
              },
            },
          });
        }
    }
    function exportCSV() {
        if (!allData.length) return;
        const headers = ['#', 'Date', 'Class', 'Present', 'Absent', 'Late', 'Attendance %'];
        const rows = allData.map((d, i) => {
          const p = parseInt(d.present) || 0;
          const a = parseInt(d.absent) || 0;
          return [
            i + 1,
            d.date || d.period,
            d.class_name || d.name,
            p,
            a,
            d.late || 0,
            p + a > 0
              ? ((p / (p + a)) * 100).toFixed(1)
              : d.attendance_rate || 0,
          ];
        });
        let csv =
          headers.join(",") +
          "\n" +
          rows
            .map((r) => r.map((v) => '"' + (v || "") + '"').join(","))
            .join("\n");
        KingswayFileLifecycle.exportText(csv, "attendance_trends.csv", "text/csv");
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
document.addEventListener('DOMContentLoaded', () => AttendanceTrendsController.init());

window.AttendanceTrendsController = AttendanceTrendsController;
