/**
 * suspensions_expulsions.js — Suspensions & Expulsions Controller (standalone page).
 * Serious disciplinary cases: suspensions and expulsions.
 */
const suspensionsExpulsionsController = {
  state: { cases: [] },

  API: (method, endpoint, data, params, opts) => window.callAPI(endpoint, method, data, params, opts),

  esc(s) { return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); },

  fmtDate(v) {
    if (!v) return '\u2014';
    const dt = new Date(String(v).replace(' ', 'T'));
    if (isNaN(dt)) return this.esc(v);
    return dt.toLocaleDateString();
  },

  norm(r) { return Array.isArray(r) ? r : (r?.data || r?.cases || r?.list || r || []); },

  badgeClass(s) {
    return { pending: 'bg-warning text-dark', approved: 'bg-success', resolved: 'bg-info text-dark', rejected: 'bg-danger' }[s] || 'bg-secondary';
  },

  isSuspExp(c) {
    const hay = `${c.type || ''} ${c.description || ''} ${c.category || ''}`.toLowerCase();
    return /suspend|expel|expulsion|suspension/.test(hay);
  },

  async load() {
    const body = document.getElementById('seBody');
    body.innerHTML = '<tr><td colspan="8" class="text-center py-3"><div class="spinner-border spinner-border-sm"></div></td></tr>';
    try {
      const params = {};
      const search = document.getElementById('seSearch')?.value.trim();
      const status = document.getElementById('seStatus')?.value;
      if (search) params.search = search;
      if (status) params.status = status;
      params.limit = 500;
      const r = await this.API('GET', '/students/discipline-get', null, params);
      const list = this.norm(r);
      this.state.cases = list.filter(c => this.isSuspExp(c));
      this.render();
    } catch (e) { body.innerHTML = `<tr><td colspan="8" class="text-center py-3 text-danger">${this.esc(e.message || 'Load failed')}</td></tr>`; }
  },

  render() {
    const body = document.getElementById('seBody');
    const cases = this.state.cases;
    const sevColors = { high: 'bg-danger', medium: 'bg-warning text-dark', low: 'bg-secondary', critical: 'bg-dark' };
    if (!cases.length) { body.innerHTML = '<tr><td colspan="8" class="text-center py-4 text-muted">No suspension or expulsion records found.</td></tr>'; return; }
    body.innerHTML = cases.map(c => `<tr>
      <td class="small fw-semibold">${this.esc(`${c.first_name || ''} ${c.last_name || ''}`.trim() || '\u2014')}</td>
      <td class="small">${this.esc(c.admission_no || '\u2014')}</td>
      <td class="small text-muted">${this.esc(c.class_name || '\u2014')}</td>
      <td class="small">${this.esc(c.type || '\u2014')}</td>
      <td><span class="badge ${sevColors[c.severity] || 'bg-secondary'}">${this.esc(c.severity || '\u2014')}</span></td>
      <td class="small">${this.fmtDate(c.incident_date)}</td>
      <td class="small text-muted">${this.esc(c.description || '\u2014')}</td>
      <td><span class="badge ${this.badgeClass(c.status)}">${this.esc(c.status || '\u2014')}</span></td>
    </tr>`).join('');
  },

  setupEventListeners() {
    ['seSearch', 'seStatus'].forEach(id => {
      document.getElementById(id)?.addEventListener('change', () => this.load());
    });
  },

  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    this.setupEventListeners();
    await this.load();
  },
};

window.suspensionsExpulsionsController = suspensionsExpulsionsController;

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => suspensionsExpulsionsController.init().catch(() => {}));
} else {
  suspensionsExpulsionsController.init().catch(() => {});
}
