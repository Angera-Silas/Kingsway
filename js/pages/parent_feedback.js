const ParentFeedbackController = (() => {
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
        document.getElementById('parentFeedbackTableBody')?.addEventListener('click', (event) => {
            const button = event.target.closest('[data-action="view"]');
            if (button) viewFeedback(Number(button.dataset.index));
        });
    }
    async function loadData() {
        try {
            const r = await window.API.apiCall(
              "/communications/parent-feedback",
              "GET",
            ).catch(() => null);
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
        const ratings = items
          .map((d) => parseFloat(d.rating || d.score) || 0)
          .filter((v) => v > 0);
        const avgRating =
          ratings.length > 0
            ? (ratings.reduce((s, v) => s + v, 0) / ratings.length).toFixed(1)
            : "0.0";
        el("statAvg", avgRating + "/5");
        el(
          "statPositive",
          items.filter((d) => {
            const r = parseFloat(d.rating || d.score) || 0;
            const s = (d.sentiment || "").toLowerCase();
            return (
              r >= 4 || s === "positive" || s === "good" || s === "excellent"
            );
          }).length,
        );
        el(
          "statNegative",
          items.filter((d) => {
            const r = parseFloat(d.rating || d.score) || 0;
            const s = (d.sentiment || "").toLowerCase();
            return r <= 2 || s === "negative" || s === "poor" || s === "bad";
          }).length,
        );
    }
    function renderTable(items) {
      const tbody = document.getElementById("parentFeedbackTableBody");
      if (!tbody) return;
      const stateRow = document.getElementById("parentFeedbackTableState");
      const stateCell = stateRow?.querySelector("td");
      tbody.querySelectorAll("[data-feedback-row]").forEach((row) => row.remove());
      if (!items.length) {
        if (stateCell) stateCell.textContent = "No parent feedback found";
        if (stateRow) stateRow.hidden = false;
        return;
      }
      if (stateRow) stateRow.hidden = true;
      const template = document.getElementById("parentFeedbackRowTemplate");
      if (!template) return;
      const fragment = document.createDocumentFragment();
      items.forEach((d, i) => {
          const rating = parseFloat(d.rating || d.score) || 0;
          const stars =
            "★".repeat(Math.round(rating)) + "☆".repeat(5 - Math.round(rating));
          const ratingColor =
            rating >= 4 ? "success" : rating >= 3 ? "warning" : "danger";
          const cat = d.category || d.feedback_type || d.type || "General";
          const status = d.status || "received";
          const statusColor =
            status.toLowerCase() === "responded"
              ? "success"
              : status.toLowerCase() === "pending"
                ? "warning"
                : "secondary";
          const row = template.content.firstElementChild.cloneNode(true);
          const set = (field, value) => {
            const element = row.querySelector(`[data-field="${field}"]`);
            if (element) element.textContent = value ?? "—";
          };
          set("number", String(i + 1));
          set("parent", d.parent_name || d.parent || "--");
          set("student", d.student_name || d.student || "--");
          set("category", cat);
          set("rating", stars);
          set("rating-number", `(${rating})`);
          const message = d.message || d.feedback || d.comment || "";
          set("message", `${message.substring(0, 60)}${message.length > 60 ? "..." : ""}`);
          set("date", d.date || d.created_at || d.submitted_at || "--");
          set("status", status);
          const ratingElement = row.querySelector('[data-field="rating"]');
          if (ratingElement) ratingElement.classList.add(`text-${ratingColor}`);
          const statusElement = row.querySelector('[data-field="status"]');
          if (statusElement) statusElement.classList.add(`bg-${statusColor}`);
          const viewButton = row.querySelector('[data-action="view"]');
          if (viewButton) viewButton.dataset.index = String(allData.indexOf(d));
          fragment.appendChild(row);
      });
      tbody.insertBefore(fragment, stateRow || null);
    }
    function viewFeedback(index) {
      const d = allData[index];
      if (!d) return;
      const rating = parseFloat(d.rating || d.score) || 0;
      const modal = document.getElementById("parentFeedbackViewModal");
      if (!modal) return;
      const values = {
        parent: d.parent_name || d.parent || "—",
        student: d.student_name || d.student || "—",
        category: d.category || d.feedback_type || "General",
        rating: `${"★".repeat(Math.round(rating))}${"☆".repeat(5 - Math.round(rating))} (${rating}/5)`,
        date: d.date || d.created_at || "—",
        message: d.message || d.feedback || d.comment || "No message",
        response: d.response || "",
      };
      modal.querySelectorAll("[data-view]").forEach((element) => {
        element.textContent = values[element.dataset.view] ?? "";
      });
      const responseContainer = modal.querySelector("[data-view-response-container]");
      if (responseContainer) responseContainer.hidden = !d.response;
      bootstrap.Modal.getOrCreateInstance(modal).show();
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
            const r = parseFloat(item.rating || item.score) || 0;
            if (f === "positive") return r >= 4;
            if (f === "neutral") return r >= 3 && r < 4;
            if (f === "negative") return r < 3;
            return (
              (item.category || item.feedback_type || "").toLowerCase() ===
              f.toLowerCase()
            );
          });
        renderTable(filtered);
    }
    function exportCSV() {
        if (!allData.length) return;
        const headers = [
          "#",
          "Parent",
          "Student",
          "Category",
          "Rating",
          "Message",
          "Date",
          "Status",
        ];
        const rows = allData.map((d, i) => [
          i + 1,
          d.parent_name || d.parent,
          d.student_name || d.student,
          d.category || d.feedback_type,
          d.rating || d.score,
          d.message || d.feedback || d.comment,
          d.date || d.created_at,
          d.status || "received",
        ]);
        let csv =
          headers.join(",") +
          "\n" +
          rows
            .map((r) => r.map((v) => '"' + (v || "") + '"').join(","))
            .join("\n");
        KingswayFileLifecycle.exportText(csv, "parent_feedback.csv", "text/csv");
    }
    function showNotification(message, type) { window.showNotification(message, type); }
    return { init, refresh: loadData, exportCSV, viewFeedback };
})();
document.addEventListener('DOMContentLoaded', () => ParentFeedbackController.init());

window.ParentFeedbackController = ParentFeedbackController;
