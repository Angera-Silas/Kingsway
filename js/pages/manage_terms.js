/**
 * Manage Terms Controller
 * Manages academic terms.
 */
const manageTermsController = {
  state: { referenceCache: null, terms: [], years: [] },

  API: (method, endpoint, data, params, opts) => window.callAPI(endpoint, method, data, params, opts),

  esc(s) { return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;'); },

  listFrom(response) {
    let value = response;
    for (let depth = 0; depth < 6; depth += 1) {
      if (Array.isArray(value)) return value;
      if (!value || typeof value !== 'object') return null;
      value = value.terms ?? value.academic_terms ?? value.years ?? value.academic_years
        ?? value.data ?? value.items ?? value.rows ?? value.records;
    }
    return Array.isArray(value) ? value : null;
  },

  statusOf(term) {
    const status = String(term?.status || term?.term_status || term?.term_period_status || '').trim().toLowerCase();
    if (term?.is_current === true || Number(term?.is_current) === 1 || status === 'current') return 'current';
    if (status === 'active') return 'active';
    if (status === 'completed' || status === 'closed') return 'completed';
    if (status === 'upcoming' || status === 'planned') return 'upcoming';
    return status;
  },

  async loadReferencesComposed() {
    try {
      const response = await window.API.nodeViews.read("academic", "academic-reports-references");
      this.state.referenceCache = response?.data?.sources || response?.sources || {};
    } catch (error) {
      console.warn("Manage Terms Node view failed; using direct reads:", error);
      this.state.referenceCache = null;
    }
  },

  async load() {
    try {
      const cache = this.state.referenceCache;
      const [terms, years] = await Promise.all([
        cache?.terms !== undefined ? cache.terms : this.API('GET', '/api/academic/terms'),
        cache?.years !== undefined ? cache.years : this.API('GET', '/api/academic/years/list'),
      ]);
      const normalizedTerms = this.listFrom(terms);
      const normalizedYears = this.listFrom(years);
      if (!normalizedTerms || !normalizedYears) throw new Error('The academic API returned an unexpected terms or years data structure.');
      this.state.terms = normalizedTerms;
      this.state.years = normalizedYears;
      this.renderTerms();
      this.renderFormOptions();
      this.renderStats();
      this.bindFilters();
    } catch (e) {
      console.error('Failed to load terms:', e);
      const body = document.getElementById('mtTermsBody');
      this.showTermsState('Could not load terms. Check your connection and refresh.', 'text-danger');
    }
  },

  getCache(key) { return this.state.referenceCache?.[key]; },

  renderTerms() {
    const terms = this.filteredTerms();
    const body = document.getElementById('mtTermsBody');
    const stateRow = document.getElementById('mtTermsState');
    const template = document.getElementById('mtTermRowTemplate');
    if (!body || !stateRow || !template) return;
    this.renderStats();
    body.querySelectorAll('[data-term-row]').forEach((row) => row.remove());
    if (!terms.length) { this.showTermsState('No terms defined.'); return; }
    stateRow.hidden = true;
    const fragment = document.createDocumentFragment();
    terms.forEach((term, index) => {
      const row = template.content.cloneNode(true);
      row.querySelector('[data-fill="number"]').textContent = String(index + 1);
      row.querySelector('[data-fill="name"]').textContent = term.name || term.term_name || '—';
      row.querySelector('[data-fill="year"]').textContent = term.year_name || term.academic_year_name || term.year || '—';
      row.querySelector('[data-fill="start"]').textContent = this.fmtDate(term.start_date);
      row.querySelector('[data-fill="end"]').textContent = this.fmtDate(term.end_date);
      row.querySelector('[data-fill="weeks"]').textContent = String(Number(term.weeks ?? term.week_count ?? 0) || '—');
      const badge = row.querySelector('[data-fill="status"]');
      const status = term.status || (term.is_current ? 'Current' : term.is_active ? 'Active' : 'Inactive');
      badge.textContent = status;
      const normalized = this.statusOf(term);
      badge.classList.add(normalized === 'current' ? 'bg-success' : normalized === 'active' ? 'bg-primary' : 'bg-secondary');
      row.querySelector('[data-action="edit"]').dataset.termId = String(Number(term.id));
      fragment.appendChild(row);
    });
    body.insertBefore(fragment, stateRow);
  },

  showTermsState(message, tone = 'text-center text-muted py-4') {
    const body = document.getElementById('mtTermsBody');
    const stateRow = document.getElementById('mtTermsState');
    body?.querySelectorAll('[data-term-row]').forEach((row) => row.remove());
    const cell = stateRow?.querySelector('td');
    if (cell) { cell.className = tone; cell.textContent = message; }
    if (stateRow) stateRow.hidden = false;
  },

  filteredTerms() {
    const yearId = document.getElementById('yearFilter')?.value || '';
    const status = document.getElementById('filterSelect')?.value || '';
    const search = (document.getElementById('searchInput')?.value || '').trim().toLowerCase();
    const date = document.getElementById('dateFilter')?.value || '';
    return this.state.terms.filter((term) => {
      const matchesYear = !yearId || String(term.academic_year_id ?? term.year) === yearId;
      const normalizedStatus = this.statusOf(term);
      const matchesStatus = !status || normalizedStatus === status;
      const matchesSearch = !search || `${term.name || ''} ${term.year_name || ''} ${term.year_code || ''}`.toLowerCase().includes(search);
      const matchesDate = !date || (String(term.start_date || '') <= date && String(term.end_date || '') >= date);
      return matchesYear && matchesStatus && matchesSearch && matchesDate;
    });
  },

  renderStats() {
    const statuses = this.state.terms.map((term) => this.statusOf(term));
    const set = (id, value) => { const el = document.getElementById(id); if (el) el.textContent = String(value); };
    set('mtStatTotal', this.state.terms.length);
    set('mtStatActive', statuses.filter((status) => status === 'current' || status === 'active').length);
    set('mtStatCompleted', statuses.filter((status) => status === 'completed').length);
    set('mtStatUpcoming', statuses.filter((status) => status === 'upcoming').length);
  },

  bindFilters() {
    if (this.filtersBound) return;
    this.filtersBound = true;
    const yearFilter = document.getElementById('yearFilter');
    if (yearFilter) yearFilter.innerHTML = '<option value="">All Academic Years</option>' + this.state.years.map((year) => `<option value="${Number(year.id)}">${this.esc(year.year_name || year.name || year.year_code)}</option>`).join('');
    const statusFilter = document.getElementById('filterSelect');
    if (statusFilter) statusFilter.innerHTML = '<option value="">All Statuses</option><option value="current">Current</option><option value="completed">Completed</option><option value="upcoming">Upcoming</option>';
    ['yearFilter', 'filterSelect', 'searchInput', 'dateFilter'].forEach((id) => document.getElementById(id)?.addEventListener(id === 'searchInput' ? 'input' : 'change', () => this.renderTerms()));
    document.getElementById('mtTermsBody')?.addEventListener('click', (event) => { const button = event.target.closest('[data-action=edit]'); if (button) this.editTerm(button.dataset.termId); });
  },

  renderFormOptions() {
    const years = this.state.years;
    const yearSelect = document.getElementById('mtYearSelect');
    const target = yearSelect || document.getElementById('recordYear');
    if (target) target.innerHTML = '<option value="">Select Year</option>' + years.map(y => `<option value="${y.id}">${this.esc(y.name || y.year_name || y.year_code)}</option>`).join('');
  },

  fmtDate(v) { if (!v) return '—'; const dt = new Date(String(v).replace(' ', 'T')); return isNaN(dt) ? this.esc(v) : dt.toLocaleDateString(); },

  refresh() { return this.load(); },

  showAddModal() {
    document.getElementById('recordForm')?.reset();
    document.getElementById('recordId').value = '';
    document.getElementById('formModalTitle').innerHTML = '<i class="bi bi-list-ul-ol me-2"></i>Add Term';
    bootstrap.Modal.getOrCreateInstance(document.getElementById('formModal')).show();
  },

  editTerm(id) {
    const term = this.state.terms.find((item) => Number(item.id) === Number(id));
    if (!term) return;
    document.getElementById('recordId').value = term.id;
    document.getElementById('recordName').value = term.name || '';
    document.getElementById('recordYear').value = term.academic_year_id ?? term.year ?? '';
    document.getElementById('recordStartDate').value = term.start_date || '';
    document.getElementById('recordEndDate').value = term.end_date || '';
    document.getElementById('recordHalfTermStart').value = term.half_term_start || '';
    document.getElementById('recordHalfTermEnd').value = term.half_term_end || '';
    document.getElementById('recordStatus').value = term.status || 'upcoming';
    document.getElementById('formModalTitle').innerHTML = '<i class="bi bi-pencil me-2"></i>Edit Term';
    bootstrap.Modal.getOrCreateInstance(document.getElementById('formModal')).show();
  },

  async saveRecord() {
    const id = document.getElementById('recordId')?.value;
    const name = document.getElementById('recordName')?.value.trim();
    const payload = {
      name,
      academic_year_id: Number(document.getElementById('recordYear')?.value || 0),
      start_date: document.getElementById('recordStartDate')?.value,
      end_date: document.getElementById('recordEndDate')?.value,
      half_term_start: document.getElementById('recordHalfTermStart')?.value || null,
      half_term_end: document.getElementById('recordHalfTermEnd')?.value || null,
      status: document.getElementById('recordStatus')?.value,
    };
    if (!name || !payload.academic_year_id || !payload.start_date || !payload.end_date) {
      this.notify('Term name, academic year, opening date and closing date are required.', 'warning');
      return;
    }
    const termNumber = Number(name.match(/term\s*(\d+)/i)?.[1] || 1);
    try {
      if (id) {
        await this.API('PUT', `/api/academic/terms/${Number(id)}`, payload);
      } else {
        await this.API('POST', '/api/academic/terms', { ...payload, term_number: termNumber });
      }
      bootstrap.Modal.getInstance(document.getElementById('formModal'))?.hide();
      this.notify(id ? 'Term updated.' : 'Term created.', 'success');
      await this.load();
    } catch (error) {
      this.notify(error.message || 'Could not save term.', 'danger');
    }
  },

  exportCSV() {
    const rows = this.filteredTerms();
    if (!rows.length) { this.notify('There are no terms to export.', 'warning'); return; }
    const cell = (value) => `"${String(value ?? '').replaceAll('"', '""')}"`;
    const csv = [['Term', 'Academic Year', 'Start Date', 'End Date', 'Weeks', 'Status'], ...rows.map((term) => [term.name, term.year_name || term.academic_year_name || term.year, term.start_date, term.end_date, term.weeks, term.status])]
      .map((row) => row.map(cell).join(',')).join('\r\n');
    window.KingswayFileLifecycle?.exportText?.(csv, 'academic_terms.csv', 'text/csv;charset=utf-8;');
  },

  notify(msg, type = 'info') { if (window.showNotification) window.showNotification(msg, type); else if (window.showToast) window.showToast(msg, type); else alert(msg); },

  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    await this.loadReferencesComposed();
    await this.load();
    window.APIRealtime?.register?.("manage_terms", {
      refresh: async () => { this.state.referenceCache = null; await this.loadReferencesComposed(); await this.load(); }
    }, ["academic"]);
  },
};

window.manageTermsController = manageTermsController;
window.ManageTermsController = manageTermsController;
document.addEventListener('DOMContentLoaded', () => manageTermsController.init().catch((error) => {
  console.error('Manage terms page initialization failed:', error);
  const body = document.getElementById('mtTermsBody');
  window.ManageTermsController?.showTermsState?.('Page setup failed. Refresh to try again.', 'text-center text-danger py-4');
}));
