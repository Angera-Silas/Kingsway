const CompetitionsController = (() => {
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
        document.getElementById('competitionsTableBody')?.addEventListener('click', (event) => {
            const button = event.target.closest('[data-action]');
            if (!button) return;
            if (button.dataset.action === 'edit') editRecord(Number(button.dataset.index));
            if (button.dataset.action === 'delete') deleteRecord(button.dataset.id);
        });
    }
    async function loadData() {
        try {
            const r = await window.API.activities
                ?.list?.({ category: "competition" })
                .catch(() => null);
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
        const now = new Date();
        el("statTotal", items.length);
        el(
          "statWins",
          items.filter((d) => {
            const r = (d.result || d.position || "").toLowerCase();
            return (
              r.includes("1st") ||
              r.includes("first") ||
              r.includes("won") ||
              r.includes("winner") ||
              r === "1"
            );
          }).length,
        );
        el(
          "statParticipants",
          items.reduce(
            (s, d) =>
              s +
              (parseInt(d.participants || d.participant_count || d.team_size) ||
                0),
            0,
          ),
        );
        el(
          "statUpcoming",
          items.filter((d) => new Date(d.date || d.competition_date) > now)
            .length,
        );
    }
    function renderTable(items) {
        const tbody = document.getElementById('competitionsTableBody');
        if (!tbody) return;
        const stateRow = document.getElementById('competitionsTableState');
        const stateCell = stateRow?.querySelector('td');
        tbody.querySelectorAll('[data-competition-row]').forEach((row) => row.remove());
        if (!items.length) {
          if (stateCell) stateCell.textContent = 'No competitions found';
          if (stateRow) stateRow.hidden = false;
          return;
        }
        if (stateRow) stateRow.hidden = true;
        const template = document.getElementById('competitionRowTemplate');
        if (!template) return;
        const fragment = document.createDocumentFragment();
        items.forEach((d, i) => {
            const result = d.result || d.position || d.award || "--";
            const resultLower = result.toLowerCase();
            const resultColor =
              resultLower.includes("1st") ||
              resultLower.includes("won") ||
              resultLower.includes("winner")
                ? "success"
                : resultLower.includes("2nd") || resultLower.includes("runner")
                  ? "primary"
                  : resultLower.includes("3rd")
                    ? "info"
                    : "secondary";
            const cat = d.category || d.competition_type || d.type || "--";
            const row = template.content.firstElementChild.cloneNode(true);
            const set = (field, value) => {
                const element = row.querySelector(`[data-field="${field}"]`);
                if (element) element.textContent = value ?? "—";
            };
            set('number', String(i + 1));
            set('name', d.name || d.competition_name || d.title || "--");
            set('category', cat);
            set('date', d.date || d.competition_date || "--");
            set('venue', d.venue || d.location || "--");
            set('participants', d.participants || d.participant_count || d.team_size || "--");
            set('result', result);
            set('award', d.award || d.prize || "--");
            const resultBadge = row.querySelector('[data-field="result"]');
            if (resultBadge) resultBadge.classList.add(`bg-${resultColor}`);
            row.querySelectorAll('[data-action]').forEach((button) => {
                button.dataset.index = String(allData.indexOf(d));
                if (button.dataset.action === 'delete') button.dataset.id = String(d.id ?? '');
            });
            fragment.appendChild(row);
        });
        tbody.insertBefore(fragment, stateRow || null);
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
          filtered = filtered.filter(
            (item) =>
              (
                item.category ||
                item.competition_type ||
                item.type ||
                ""
              ).toLowerCase() === f.toLowerCase(),
          );
        if (dateF)
          filtered = filtered.filter((item) =>
            (item.date || item.competition_date || "").includes(dateF),
          );
        renderTable(filtered);
    }
    function showAddModal() {
        document.querySelector("#formModalTitle [data-modal-title]").textContent = "Add Competition";
        document.getElementById('recordForm').reset(); document.getElementById('recordId').value = '';
        const nameEl = document.getElementById("recordName");
        const descEl = document.getElementById("recordDescription");
        const dateEl = document.getElementById("recordDate");
        const statusEl = document.getElementById("recordStatus");
        if (nameEl?.previousElementSibling)
          nameEl.previousElementSibling.textContent = "Competition Name";
        if (nameEl) nameEl.placeholder = "e.g., Inter-School Science Quiz";
        if (descEl?.previousElementSibling)
          descEl.previousElementSibling.textContent = "Venue";
        if (descEl) descEl.placeholder = "Competition venue";
        if (dateEl?.previousElementSibling)
          dateEl.previousElementSibling.textContent = "Date";
        if (statusEl?.previousElementSibling)
          statusEl.previousElementSibling.textContent = "Category";
        new bootstrap.Modal(document.getElementById('formModal')).show();
    }
    function editRecord(index) {
        const item = allData[index]; if (!item) return;
        document.querySelector("#formModalTitle [data-modal-title]").textContent = "Edit Competition";
        document.getElementById('recordId').value = item.id || '';
        document.getElementById("recordName").value =
          item.name || item.competition_name || item.title || "";
        document.getElementById("recordDescription").value =
          item.venue || item.location || "";
        document.getElementById("recordDate").value =
          item.date || item.competition_date || "";
        document.getElementById("recordStatus").value =
          item.category || item.competition_type || item.type || "general";
        new bootstrap.Modal(document.getElementById('formModal')).show();
    }
    async function saveRecord() {
        const id = document.getElementById('recordId').value;
        const data = {
          name: document.getElementById("recordName").value,
          venue: document.getElementById("recordDescription").value,
          date: document.getElementById("recordDate").value,
          category: document.getElementById("recordStatus").value,
        };
        if (!data.name) {
          showNotification("Competition name is required", "warning");
          return;
        }
        try {
            if (id) {
                await window.API.activities.update(id, data);
            } else {
                await window.API.activities.create(data);
            }
            showNotification("Competition saved successfully", "success");
            bootstrap.Modal.getInstance(document.getElementById('formModal'))?.hide(); await loadData();
        } catch (e) { showNotification(e.message || 'Failed to save', 'danger'); }
    }
    async function deleteRecord(id) {
        if (!(await window.confirmAction('Confirm Deletion', "Delete this competition record?", { confirmText: 'Delete', danger: true }))) return;
        try { await window.API.activities.delete(id); showNotification('Deleted', 'success'); await loadData(); }
        catch (e) { showNotification(e.message || 'Delete failed', 'danger'); }
    }
    function exportCSV() {
        if (!allData.length) return;
        const headers = [
          "#",
          "Competition",
          "Category",
          "Date",
          "Venue",
          "Participants",
          "Result",
          "Award",
        ];
        const rows = allData.map((d, i) => [
          i + 1,
          d.name || d.competition_name || d.title,
          d.category || d.competition_type || d.type,
          d.date || d.competition_date,
          d.venue || d.location,
          d.participants || d.participant_count,
          d.result || d.position,
          d.award || d.prize,
        ]);
        let csv =
          headers.join(",") +
          "\n" +
          rows
            .map((r) => r.map((v) => '"' + (v || "") + '"').join(","))
            .join("\n");
        KingswayFileLifecycle.exportText(csv, "competitions.csv", "text/csv");
    }
    function escapeHtml(s) {
      return String(s || "")
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;");
    }
    function showNotification(message, type) { window.showNotification(message, type); }
    return { init, refresh: loadData, exportCSV, showAddModal, editRecord, saveRecord, deleteRecord };
})();
document.addEventListener('DOMContentLoaded', () => CompetitionsController.init());

window.CompetitionsController = CompetitionsController;
