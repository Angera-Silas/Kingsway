/**
 * welfare_records.js — Welfare Records Controller (standalone page).
 * Student welfare cases with filters.
 */
const welfareRecordsController = {
  state: { cases: [], meta: {} },

  API: (method, endpoint, data, params, opts) => window.callAPI(endpoint, method, data, params, opts),

  esc(s) { return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); },

  fmtDate(v) {
    if (!v) return '\u2014';
    const dt = new Date(String(v).replace(' ', 'T'));
    if (isNaN(dt)) return this.esc(v);
    return dt.toLocaleDateString();
  },

  norm(r) { return Array.isArray(r) ? r : (r?.data || r?.cases || r?.list || r || []); },

  badgeClass(s, map) { return (map || {})[s] || 'bg-secondary'; },

  async load() {
    const body = document.getElementById('wrBody');
    body.innerHTML = '<tr><td colspan="7" class="text-center py-3"><div class="spinner-border spinner-border-sm"></div></td></tr>';
    try {
      const [casesRes, metaRes] = await Promise.all([
        this.API('GET', '/students/welfare-cases', null, {
          search: document.getElementById('wrSearch')?.value.trim() || '',
          welfare_category: document.getElementById('wrCategory')?.value || '',
          priority: document.getElementById('wrPriority')?.value || '',
          status: document.getElementById('wrStatus')?.value || '',
        }).catch(() => []),
        this.API('GET', '/students/welfare-meta').catch(() => ({})),
      ]);
      this.state.cases = this.norm(casesRes);
      this.state.meta = metaRes?.data || metaRes || {};
      this.renderFilters();
      this.render();
    } catch (e) { body.innerHTML = `<tr><td colspan="7" class="text-center py-3 text-danger">${this.esc(e.message || 'Load failed')}</td></tr>`; }
  },

  renderFilters() {
    const meta = this.state.meta;
    document.getElementById('wrCategory').innerHTML = '<option value="">All categories</option>' +
      (meta.welfare_categories || ['emotional','social','behavioral','family','chapel','pastoral','referral','other']).map(c => `<option value="${this.esc(c)}">${this.esc(c)}</option>`).join('');
    document.getElementById('wrPriority').innerHTML = '<option value="">All priorities</option>' +
      (meta.priorities || ['low','medium','high','urgent']).map(p => `<option value="${this.esc(p)}">${this.esc(p)}</option>`).join('');
  },

  render() {
    const body = document.getElementById('wrBody');
    const cases = this.state.cases;
    const statusMap = { open: 'bg-primary', in_progress: 'bg-info text-dark', resolved: 'bg-success', closed: 'bg-secondary', cancelled: 'bg-danger' };
    const prioMap = { urgent: 'bg-danger', high: 'bg-warning text-dark', medium: 'bg-info text-dark', low: 'bg-secondary' };
    if (!cases.length) { body.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted">No welfare cases found.</td></tr>'; return; }
    body.innerHTML = cases.map(c => `<tr>
      <td class="small fw-semibold">${this.esc(c.case_code || `#${c.id}`)}</td>
      <td class="small">${this.esc(c.full_name || '\u2014')}<br><span class="text-muted">${this.esc(c.admission_no || '')}</span></td>
      <td class="small">${this.esc(c.welfare_category || '\u2014')}</td>
      <td><span class="badge ${this.badgeClass(c.priority, prioMap)}">${this.esc(c.priority || '\u2014')}</span></td>
      <td class="small text-muted">${this.esc(c.assigned_to_name || '\u2014')}</td>
      <td class="small">${this.fmtDate(c.next_follow_up_at)}</td>
      <td><span class="badge ${this.badgeClass(c.status, statusMap)}">${this.esc(c.status || '\u2014')}</span></td>
    </tr>`).join('');
  },

  setupEventListeners() {
    ['wrSearch', 'wrCategory', 'wrPriority', 'wrStatus'].forEach(id => {
      document.getElementById(id)?.addEventListener('change', () => this.load());
    });
  },

  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    this.setupEventListeners();
    await this.load();
  },
};

window.welfareRecordsController = welfareRecordsController;

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => welfareRecordsController.init().catch(() => {}));
} else {
  welfareRecordsController.init().catch(() => {});
}
