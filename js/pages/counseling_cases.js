/**
 * counseling_cases.js — Counseling Cases Controller (standalone page).
 * Counseling sessions and case management.
 */
const counselingCasesController = {
  state: { sessions: [], byType: [] },

  API: (method, endpoint, data, params, opts) => window.callAPI(endpoint, method, data, params, opts),

  esc(s) { return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); },

  fmtDate(v) {
    if (!v) return '\u2014';
    const dt = new Date(String(v).replace(' ', 'T'));
    if (isNaN(dt)) return this.esc(v);
    return dt.toLocaleDateString();
  },

  norm(r) { return Array.isArray(r) ? r : (r?.data || r?.sessions || r?.list || r || []); },

  badgeClass(s, map) {
    return (map || {})[s] || 'bg-secondary';
  },

  async load() {
    const body = document.getElementById('ccBody');
    body.innerHTML = '<tr><td colspan="7" class="text-center py-3"><div class="spinner-border spinner-border-sm"></div></td></tr>';
    try {
      const params = {};
      const search = document.getElementById('ccSearch')?.value.trim();
      const status = document.getElementById('ccStatus')?.value;
      const category = document.getElementById('ccCategory')?.value;
      if (search) params.search = search;
      if (status) params.status = status;
      if (category) params.category = category;
      params.limit = 100;

      const [sessionRes, sumRes] = await Promise.all([
        this.API('GET', '/counseling/session', null, params).catch(() => []),
        this.API('GET', '/counseling/summary').catch(() => ({})),
      ]);
      const sum = sumRes?.data || sumRes || {};
      this.state.byType = sum.by_type || [];
      this.state.sessions = this.norm(sessionRes);
      this.renderFilters();
      this.render();
    } catch (e) { body.innerHTML = `<tr><td colspan="7" class="text-center py-3 text-danger">${this.esc(e.message || 'Load failed')}</td></tr>`; }
  },

  renderFilters() {
    const sel = document.getElementById('ccCategory');
    const types = [...new Set(this.state.byType.map(t => t.case_type).filter(Boolean))];
    sel.innerHTML = '<option value="">All categories</option>' + types.map(t => `<option value="${this.esc(t)}">${this.esc(t)}</option>`).join('');
  },

  render() {
    const body = document.getElementById('ccBody');
    if (!this.state.sessions.length) { body.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted">No counseling sessions found.</td></tr>'; return; }
    const statusMap = { open: 'bg-primary', in_progress: 'bg-info text-dark', resolved: 'bg-success', closed: 'bg-secondary' };
    const prioMap = { urgent: 'bg-danger', high: 'bg-warning text-dark', medium: 'bg-info text-dark', low: 'bg-secondary' };
    body.innerHTML = this.state.sessions.map(s => `<tr>
      <td class="small fw-semibold">${this.esc(s.case_code || `#${s.case_id}`)}</td>
      <td class="small">${this.esc(s.counselee_name || s.student_name || s.staff_name || '\u2014')}</td>
      <td class="small text-muted">${this.esc(s.case_type || s.session_type || '\u2014')}</td>
      <td><span class="badge ${this.badgeClass(s.priority, prioMap)}">${this.esc(s.priority || '\u2014')}</span></td>
      <td class="small">${this.fmtDate(s.session_date)}</td>
      <td class="small">${this.esc(s.counselor_name || '\u2014')}</td>
      <td><span class="badge ${this.badgeClass(s.status, statusMap)}">${this.esc(s.status || '\u2014')}</span></td>
    </tr>`).join('');
  },

  setupEventListeners() {
    ['ccSearch', 'ccStatus', 'ccCategory'].forEach(id => {
      document.getElementById(id)?.addEventListener('change', () => this.load());
    });
  },

  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    this.setupEventListeners();
    await this.load();
  },
};

window.counselingCasesController = counselingCasesController;

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => counselingCasesController.init().catch(() => {}));
} else {
  counselingCasesController.init().catch(() => {});
}
