/**
 * exeat_history.js — Exeat History Controller (standalone page).
 * All leave requests with a status filter.
 */
const exeatHistoryController = {
  state: { requests: [] },

  API: (method, endpoint, data, params, opts) => window.callAPI(endpoint, method, data, params, opts),

  esc(s) { return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); },

  fmtDate(v) {
    if (!v) return '\u2014';
    const dt = new Date(String(v).replace(' ', 'T'));
    if (isNaN(dt)) return this.esc(v);
    return dt.toLocaleDateString();
  },

  norm(r) { return Array.isArray(r) ? r : (r?.data || r?.requests || r?.exeats || r || []); },

  badgeClass(s) {
    return { approved: 'bg-success', pending: 'bg-warning text-dark', rejected: 'bg-danger', checked_out: 'bg-primary', checked_in: 'bg-secondary' }[s] || 'bg-secondary';
  },

  async load() {
    const body = document.getElementById('ehBody');
    body.innerHTML = '<tr><td colspan="7" class="text-center py-3"><div class="spinner-border spinner-border-sm"></div></td></tr>';
    try {
      const status = document.getElementById('ehStatus')?.value || '';
      const params = status ? { status } : {};
      const r = await this.API('GET', '/boarding/exeats', null, params);
      this.state.requests = this.norm(r);
      this.render();
    } catch (e) { body.innerHTML = `<tr><td colspan="7" class="text-center py-3 text-danger">${this.esc(e.message || 'Load failed')}</td></tr>`; }
  },

  render() {
    const body = document.getElementById('ehBody');
    if (!this.state.requests.length) { body.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted">No leave requests found.</td></tr>'; return; }
    body.innerHTML = this.state.requests.map(r => `<tr>
      <td class="small">${this.esc(r.admission_no || '\u2014')}</td>
      <td class="small fw-semibold">${this.esc(r.student_name || '\u2014')}</td>
      <td class="small">${this.esc(r.permission_type_name || '\u2014')}</td>
      <td class="small">${this.fmtDate(r.start_date)}</td>
      <td class="small">${this.fmtDate(r.end_date)}</td>
      <td class="small text-muted">${this.esc(r.reason || '\u2014')}</td>
      <td><span class="badge ${this.badgeClass(r.status)}">${this.esc(r.status || '\u2014')}</span></td>
    </tr>`).join('');
  },

  setupEventListeners() {
    document.getElementById('ehStatus')?.addEventListener('change', () => this.load());
  },

  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    this.setupEventListeners();
    await this.load();
  },
};

window.exeatHistoryController = exeatHistoryController;

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => exeatHistoryController.init().catch(() => {}));
} else {
  exeatHistoryController.init().catch(() => {});
}
