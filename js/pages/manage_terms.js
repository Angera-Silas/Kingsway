/**
 * Manage Terms Controller
 * Page: manage_terms.php
 * Single terms & dates page: view terms (optionally per academic year), edit
 * start/end dates + status, add a missing term, search/filter, export.
 * This page absorbed the former term_dates page.
 */
const ManageTermsController = (() => {
    let allData = [];
    let allYears = [];
    let currentData = [];

    async function init() {
        await window.AuthContext?.ready();
        if (typeof AuthContext !== 'undefined' && !AuthContext.isAuthenticated()) { window.location.href = (window.APP_BASE || '') + '/index.php'; return; }

        // Initialize Academic Context if available
        if (window.AcademicContext) {
            window.AcademicContext.subscribe((context, event, data) => {
                if (event === 'termChanged' || event === 'initialized' || event === 'refreshed') {
                    loadData();
                }
            });
            if (!window.AcademicContext.isLoaded()) {
                await window.AcademicContext.init();
            }
        }

        await loadData(); setupEventListeners();
    }
    function setupEventListeners() {
        document.getElementById('searchInput')?.addEventListener('input', filterData);
        document.getElementById('filterSelect')?.addEventListener('change', filterData);
        document.getElementById('yearFilter')?.addEventListener('change', filterData);
        document.getElementById('dateFilter')?.addEventListener('change', filterData);
    }
    async function loadData() {
        try {
            const [yearsR, termsR] = await Promise.all([
                window.API.apiCall('/academic/years/list', 'GET').catch(() => null),
                window.API.apiCall('/academic/terms', 'GET').catch(() => null),
            ]);
            const years = yearsR?.data || yearsR || [];
            allYears = Array.isArray(years) ? years : [];
            const terms = termsR?.data || termsR || [];
            allData = Array.isArray(terms) ? terms : [];
            populateYearFilter();
            populateStatusFilter();
            populateYearSelect();
            filterData();
        } catch (e) { console.error('Load failed:', e); renderTable([]); }
    }
    function populateYearFilter() {
        const sel = document.getElementById('yearFilter');
        if (!sel) return;
        const current = sel.value;
        sel.innerHTML = '<option value="">All Academic Years</option>' +
            allYears.map(y => `<option value="${y.id}">${escapeHtml(y.name || y.year_name || y.year_code)}</option>`).join('');
        sel.value = current;
    }
    function populateStatusFilter() {
        const sel = document.getElementById('filterSelect');
        if (!sel) return;
        const current = sel.value;
        sel.innerHTML = '<option value="">All Statuses</option>' +
            ['Active', 'Completed', 'Upcoming'].map(s =>
                `<option value="${s.toLowerCase()}">${s}</option>`).join('');
        sel.value = current;
    }
    function populateYearSelect() {
        const sel = document.getElementById('recordYear');
        if (!sel) return;
        const currentYearId = allYears.find(y => y.is_current)?.id;
        sel.innerHTML = allYears.map(y =>
            `<option value="${y.id}" ${y.id == currentYearId ? 'selected' : ''}>${escapeHtml(y.name || y.year_name || y.year_code)}</option>`).join('');
    }
    function renderStats(data) {
        const items = Array.isArray(data) ? data : [];
        const el = (id, val) => { const e = document.getElementById(id); if (e) e.textContent = val; };
        const now = new Date();
        el("statTotal", items.length);
        el("statActive", items.filter((d) => {
            const s = new Date(d.start_date), e = new Date(d.end_date);
            return (d.status || "").toLowerCase() === "active" || (d.status || "").toLowerCase() === "current" || (now >= s && now <= e);
        }).length);
        el("statCompleted", items.filter((d) => {
            return (d.status || "").toLowerCase() === "completed" || new Date(d.end_date) < now;
        }).length);
        el("statUpcoming", items.filter((d) => {
            return (d.status || "").toLowerCase() === "upcoming" || new Date(d.start_date) > now;
        }).length);
    }
    function renderTable(items) {
        currentData = items;
        const tbody = document.querySelector('#dataTable tbody');
        if (!tbody) return;
        if (!items.length) {
            tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">No terms found</td></tr>';
            return;
        }
        const now = new Date();
        tbody.innerHTML = items
            .map((d, i) => {
                const start = new Date(d.start_date);
                const end = new Date(d.end_date);
                const isCurrent = now >= start && now <= end;
                const isPast = now > end;
                const status = d.status || (isCurrent ? "Active" : isPast ? "Completed" : "Upcoming");
                const statusColor = isCurrent || ["active", "current"].includes(String(status).toLowerCase())
                    ? "success" : isPast || String(status).toLowerCase() === "completed"
                        ? "secondary" : "primary";
                const weeks = d.weeks ? Number(d.weeks) : Math.ceil((end - start) / (7 * 24 * 60 * 60 * 1000));
                return `<tr class="${isCurrent ? "table-success" : ""}">
                    <td>${i + 1}</td>
                    <td><strong>${escapeHtml(d.name || d.term_name || "Term " + (d.term_number || i + 1))}</strong></td>
                    <td>${escapeHtml(d.year_name || d.academic_year || "--")}</td>
                    <td>${d.start_date || "--"}</td>
                    <td>${d.end_date || "--"}</td>
                    <td>${weeks > 0 ? weeks + " weeks" : "--"}</td>
                    <td><span class="badge bg-${statusColor}">${status}</span></td>
                    <td>
                        <button class="btn btn-sm btn-outline-primary me-1" onclick="ManageTermsController.editRecord(${i})" title="Edit"><i class="fas fa-edit"></i></button>
                        <button class="btn btn-sm btn-outline-danger" onclick="ManageTermsController.deleteRecord('${d.id}')" title="Delete"><i class="fas fa-trash"></i></button>
                    </td>
                </tr>`;
            })
            .join("");
    }
    function filterData() {
        const s = (document.getElementById('searchInput')?.value || '').toLowerCase();
        const f = document.getElementById("filterSelect")?.value;
        const y = document.getElementById("yearFilter")?.value;
        const dateFilter = document.getElementById("dateFilter")?.value;
        let filtered = allData;
        if (s) filtered = filtered.filter((item) => JSON.stringify(item).toLowerCase().includes(s));
        if (y) filtered = filtered.filter((item) => String(item.year) === String(y));
        if (dateFilter) filtered = filtered.filter((item) => item.start_date === dateFilter || item.end_date === dateFilter);
        if (f) {
            const now = new Date();
            filtered = filtered.filter((item) => {
                const start = new Date(item.start_date);
                const end = new Date(item.end_date);
                if (f === "active") return now >= start && now <= end;
                if (f === "completed") return now > end;
                if (f === "upcoming") return now < start;
                return true;
            });
        }
        renderStats(filtered);
        renderTable(filtered);
    }
    function showAddModal() {
        document.getElementById("formModalTitle").innerHTML = '<i class="fas fa-list-ol me-2"></i>Add Term';
        document.getElementById('recordForm').reset(); document.getElementById('recordId').value = '';
        const yearSel = document.getElementById("recordYear");
        const yearFilter = document.getElementById("yearFilter");
        if (yearSel && yearFilter?.value) yearSel.value = yearFilter.value;
        if (document.getElementById('recordStatus')) document.getElementById('recordStatus').value = 'upcoming';
        new bootstrap.Modal(document.getElementById('formModal')).show();
    }
    function editRecord(index) {
        const item = currentData[index]; if (!item) return;
        document.getElementById("formModalTitle").innerHTML = '<i class="fas fa-edit me-2"></i>Edit Term';
        document.getElementById('recordId').value = item.id || '';
        document.getElementById("recordName").value = item.name || item.term_name || "";
        const yearSel = document.getElementById("recordYear");
        if (yearSel && item.year) yearSel.value = String(item.year);
        document.getElementById("recordStartDate").value = item.start_date || "";
        document.getElementById("recordEndDate").value = item.end_date || "";
        document.getElementById("recordHalfTermStart").value = item.half_term_start || "";
        document.getElementById("recordHalfTermEnd").value = item.half_term_end || "";
        document.getElementById('recordStatus').value = item.status || 'upcoming';
        new bootstrap.Modal(document.getElementById('formModal')).show();
    }
    async function saveRecord() {
        const id = document.getElementById('recordId').value;
        const halfStart = document.getElementById("recordHalfTermStart").value;
        const halfEnd = document.getElementById("recordHalfTermEnd").value;
        const data = {
            name: document.getElementById("recordName").value,
            academic_year_id: document.getElementById("recordYear")?.value,
            start_date: document.getElementById("recordStartDate").value,
            end_date: document.getElementById("recordEndDate").value,
            status: document.getElementById("recordStatus").value,
        };
        if (!data.name) { showNotification("Term name is required", "warning"); return; }
        if (!data.start_date || !data.end_date) { showNotification("Start and end dates are required", "warning"); return; }
        if (new Date(data.end_date) <= new Date(data.start_date)) { showNotification("End date must be after start date", "warning"); return; }
        if (halfStart && halfEnd && new Date(halfEnd) <= new Date(halfStart)) { showNotification("Half-term end must be after half-term start", "warning"); return; }
        try {
            // Blank half-term dates = no half-term for this term (send null to clear).
            const payload = {
                name: data.name, start_date: data.start_date, end_date: data.end_date, status: data.status,
                half_term_start: halfStart || null,
                half_term_end: halfEnd || null,
            };
            if (id) {
                await window.API.apiCall('/academic/terms/' + id, 'PUT', payload);
            } else {
                await window.API.apiCall('/academic/terms', 'POST', payload);
            }
            showNotification("Term saved successfully", "success");
            bootstrap.Modal.getInstance(document.getElementById('formModal'))?.hide();

            if (data.status === 'current' || data.status === 'active') {
                if (window.AcademicContext) await window.AcademicContext.refresh();
                if (window.DataStore) window.DataStore.invalidateMany(['academic']);
            }
            await loadData();
        } catch (e) { showNotification(e.message || 'Failed to save', 'danger'); }
    }
    async function deleteRecord(id) {
        if (!(await window.confirmAction('Confirm Deletion', "Delete this term? This may affect linked academic records.", { confirmText: 'Delete', danger: true }))) return;
        try {
            await window.API.apiCall("/academic/terms/" + id, "DELETE");
            showNotification("Term deleted", "success");
            await loadData();
        } catch (e) { showNotification(e.message || "Delete failed", "danger"); }
    }
    function exportCSV() {
        const rows = currentData.length ? currentData : allData;
        if (!rows.length) return;
        const headers = ["#", "Term Name", "Academic Year", "Start Date", "End Date", "Weeks", "Status"];
        const csvRows = rows.map((d, i) => {
            const weeks = d.weeks ? Number(d.weeks) : Math.ceil((new Date(d.end_date) - new Date(d.start_date)) / (7 * 24 * 60 * 60 * 1000));
            return [i + 1, d.name || d.term_name, d.year_name || d.academic_year, d.start_date, d.end_date, weeks, d.status || ""];
        });
        let csv = headers.join(",") + "\n" + csvRows.map((r) => r.map((v) => '"' + (v || "") + '"').join(",")).join("\n");
        KingswayFileLifecycle.exportText(csv, "manage_terms.csv", "text/csv");
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
document.addEventListener('DOMContentLoaded', () => ManageTermsController.init());
