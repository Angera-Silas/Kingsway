const AssembliesController = (() => {
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
        document.getElementById('assemblyTableBody')?.addEventListener('click', (event) => {
            const button = event.target.closest('[data-action]');
            if (!button) return;
            const index = Number(button.dataset.index);
            if (button.dataset.action === 'edit') editRecord(index);
            if (button.dataset.action === 'delete') deleteRecord(button.dataset.id);
        });
    }
    async function loadData() {
        try {
            const r = await window.API.activities?.list?.({ category: "assembly" }).catch(
                () => null,
            );
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
          "statTerm",
          items.filter((d) => {
            const dt = new Date(d.date || d.assembly_date);
            const termStart = new Date(
              now.getFullYear(),
              now.getMonth() - 2,
              1,
            );
            return dt >= termStart && dt <= now;
          }).length,
        );
        el(
          "statUpcoming",
          items.filter((d) => new Date(d.date || d.assembly_date) > now).length,
        );
        el(
          "statSpeakers",
          new Set(
            items.map((d) => d.speaker || d.guest_speaker).filter(Boolean),
          ).size,
        );
    }
    function renderTable(items) {
        const tbody = document.getElementById('assemblyTableBody');
        if (!tbody) return;
        const stateRow = document.getElementById('assemblyTableState');
        const stateCell = stateRow?.querySelector('td');
        tbody.querySelectorAll('[data-assembly-row]').forEach((row) => row.remove());
        if (!items.length) {
          if (stateCell) stateCell.textContent = 'No assemblies found';
          if (stateRow) stateRow.hidden = false;
          return;
        }
        if (stateRow) stateRow.hidden = true;
        const template = document.getElementById('assemblyRowTemplate');
        if (!template) return;
        const now = new Date();
        const fragment = document.createDocumentFragment();
        items.forEach((d, i) => {
            const dt = new Date(d.date || d.assembly_date);
            const dayName = dt.toLocaleDateString("en-US", { weekday: "long" });
            const typeColors = {
              general: "primary",
              special: "warning",
              class: "info",
              devotional: "success",
              awards: "danger",
            };
            const type = (d.type || d.assembly_type || "general").toLowerCase();
            const row = template.content.firstElementChild.cloneNode(true);
            const set = (field, value) => {
                const target = row.querySelector(`[data-field="${field}"]`);
                if (target) target.textContent = value ?? "—";
            };
            row.classList.toggle("table-warning", dt.toDateString() === now.toDateString());
            set("number", String(i + 1));
            set("date", d.date || d.assembly_date || "--");
            set("day", dayName);
            set("time", d.time || d.start_time || "--");
            set("theme", d.theme || d.topic || d.name || "--");
            set("speaker", d.speaker || d.guest_speaker || "--");
            set("class", d.class_responsible || d.responsible_class || "--");
            const typeBadge = row.querySelector('[data-field="type"]');
            if (typeBadge) {
                typeBadge.textContent = type.charAt(0).toUpperCase() + type.slice(1);
                typeBadge.classList.add(`bg-${typeColors[type] || "secondary"}`);
            }
            const recordIndex = allData.indexOf(d);
            row.querySelectorAll('[data-action]').forEach((button) => {
                button.dataset.index = String(recordIndex);
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
              (item.type || item.assembly_type || "").toLowerCase() ===
              f.toLowerCase(),
          );
        if (dateF)
          filtered = filtered.filter((item) =>
            (item.date || item.assembly_date || "").includes(dateF),
          );
        renderTable(filtered);
    }
    function showAddModal() {
        document.querySelector("#formModalTitle [data-modal-title]").textContent = "Schedule Assembly";
        document.getElementById('recordForm').reset(); document.getElementById('recordId').value = '';
        const nameEl = document.getElementById("recordName");
        const descEl = document.getElementById("recordDescription");
        const dateEl = document.getElementById("recordDate");
        const statusEl = document.getElementById("recordStatus");
        if (nameEl?.previousElementSibling)
          nameEl.previousElementSibling.textContent = "Theme / Topic";
        if (nameEl) nameEl.placeholder = "Assembly theme or topic";
        if (descEl?.previousElementSibling)
          descEl.previousElementSibling.textContent = "Speaker";
        if (descEl) descEl.placeholder = "Speaker or guest name";
        if (dateEl?.previousElementSibling)
          dateEl.previousElementSibling.textContent = "Date";
        if (statusEl?.previousElementSibling)
          statusEl.previousElementSibling.textContent = "Type";
        bootstrap.Modal.getOrCreateInstance(document.getElementById('formModal')).show();
    }
    function editRecord(index) {
        const item = allData[index]; if (!item) return;
        document.querySelector("#formModalTitle [data-modal-title]").textContent = "Edit Assembly";
        document.getElementById('recordId').value = item.id || '';
        document.getElementById("recordName").value =
          item.theme || item.topic || item.name || "";
        document.getElementById("recordDescription").value =
          item.speaker || item.guest_speaker || "";
        document.getElementById("recordDate").value =
          item.date || item.assembly_date || "";
        document.getElementById("recordStatus").value =
          item.type || item.assembly_type || "general";
        bootstrap.Modal.getOrCreateInstance(document.getElementById('formModal')).show();
    }
    async function saveRecord() {
        const id = document.getElementById('recordId').value;
        const data = {
          theme: document.getElementById("recordName").value,
          speaker: document.getElementById("recordDescription").value,
          date: document.getElementById("recordDate").value,
          type: document.getElementById("recordStatus").value,
        };
        if (!data.theme) {
          showNotification("Theme/Topic is required", "warning");
          return;
        }
        try {
            if (id) {
                await window.API.activities.update(id, data);
            } else {
                await window.API.activities.create(data);
            }
            showNotification("Assembly saved successfully", "success");
            bootstrap.Modal.getInstance(document.getElementById('formModal'))?.hide(); await loadData();
        } catch (e) { showNotification(e.message || 'Failed to save', 'danger'); }
    }
    async function deleteRecord(id) {
        if (!(await window.confirmAction('Confirm Deletion', "Delete this assembly?", { confirmText: 'Delete', danger: true }))) return;
        try {
          await window.API.activities.delete(id);
          showNotification("Assembly deleted", "success");
          await loadData();
        } catch (e) {
          showNotification(e.message || "Delete failed", "danger");
        }
    }
    function exportCSV() {
        if (!allData.length) return;
        const headers = [
          "#",
          "Date",
          "Day",
          "Time",
          "Theme/Topic",
          "Speaker",
          "Class Responsible",
          "Type",
        ];
        const rows = allData.map((d, i) => {
          const dt = new Date(d.date || d.assembly_date);
          return [
            i + 1,
            d.date || d.assembly_date,
            dt.toLocaleDateString("en-US", { weekday: "long" }),
            d.time || d.start_time,
            d.theme || d.topic || d.name,
            d.speaker || d.guest_speaker,
            d.class_responsible || d.responsible_class,
            d.type || d.assembly_type,
          ];
        });
        let csv =
          headers.join(",") +
          "\n" +
          rows
            .map((r) => r.map((v) => '"' + (v || "") + '"').join(","))
            .join("\n");
        KingswayFileLifecycle.exportText(csv, "assemblies.csv", "text/csv");
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
document.addEventListener('DOMContentLoaded', () => AssembliesController.init());

window.AssembliesController = AssembliesController;
