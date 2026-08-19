/**
 * view_fee_structure_progress.js — Fee Structure Progress Controller (standalone page).
 * Accountant tracks bundle status and submits draft bundles for director review.
 */
const viewFeeStructureProgressController = {
  state: { bundles: [], years: [] },

  API: (method, endpoint, data, params, opts) => window.callAPI(endpoint, method, data, params, opts),

  notify(msg, type = 'success') {
    if (window.showNotification) { showNotification(msg, type); return; }
    const el = document.createElement('div');
    el.className = `alert alert-${type} position-fixed top-0 end-0 m-3 shadow`;
    el.style.zIndex = 99999;
    el.textContent = msg;
    document.body.appendChild(el);
    setTimeout(() => el.remove(), 3000);
  },

  esc(s) { return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); },

  async loadReference() {
    try {
      const r = await this.API('GET', 'academic/years/list');
      const years = Array.isArray(r) ? r : (r?.data?.items || r?.data || []);
      this.state.years = years;
      document.getElementById('fspYear').innerHTML = '<option value="">All years</option>' + years.map(y => `<option value="${this.esc(y.year_code || y.year)}">${this.esc(y.year_code || y.year)}</option>`).join('');
    } catch (_) {}
  },

  async load() {
    const body = document.getElementById('fspBody');
    body.innerHTML = '<tr><td colspan="9" class="text-center py-3"><div class="spinner-border spinner-border-sm"></div></td></tr>';
    try {
      const params = { limit: 100 };
      const s = document.getElementById('fspStatus').value;
      if (s) params.status = s;
      const y = document.getElementById('fspYear').value;
      if (y) params.academic_year = y;
      const r = await this.API('GET', 'finance/fees-bundle-list', null, params);
      this.state.bundles = r?.bundles || [];
      this.render();
    } catch (e) { body.innerHTML = `<tr><td colspan="9" class="text-center py-3 text-danger">${this.esc(e.message || 'Load failed')}</td></tr>`; }
  },

  badgeClass(s) {
    return { approved: 'bg-success', active: 'bg-success', reviewed: 'bg-info', submitted: 'bg-primary', draft: 'bg-warning text-dark', rejected: 'bg-danger' }[s] || 'bg-secondary';
  },

  render() {
    const body = document.getElementById('fspBody');
    if (!this.state.bundles.length) { body.innerHTML = '<tr><td colspan="9" class="text-center py-4 text-muted">No fee structure bundles found.</td></tr>'; return; }
    body.innerHTML = this.state.bundles.map(b => `
      <tr>
        <td class="small fw-semibold">${this.esc(b.level_name || '\u2014')}</td>
        <td class="small">${this.esc(b.academic_year || '\u2014')}</td>
        <td class="small">${this.esc(b.term_name || '\u2014')}</td>
        <td class="small">${this.esc(b.student_type_name || '\u2014')}</td>
        <td class="small">KES ${Number(b.total_amount || 0).toLocaleString()}</td>
        <td class="small">${b.line_item_count ?? '\u2014'}</td>
        <td class="small text-muted">${this.esc(b.submitted_by_name || '\u2014')}</td>
        <td><span class="badge ${this.badgeClass(b.status)}">${this.esc(b.status || 'draft')}</span></td>
        <td class="text-end">
          ${['draft', 'rejected'].includes(b.status)
            ? `<button class="btn btn-sm btn-outline-primary rounded-pill px-2" onclick="viewFeeStructureProgressController.submit(${b.id})" title="Submit for review"><i class="bi bi-send"></i></button>`
            : '<span class="text-muted small">\u2014</span>'}
        </td>
      </tr>`).join('');
  },

  async submit(id) {
    const b = this.state.bundles.find(x => Number(x.id) === Number(id));
    if (!b) return;
    if (!confirm(`Submit ${b.level_name} ${b.academic_year} ${b.term_name} (${b.student_type_name}) for director review?`)) return;
    try {
      await this.API('POST', 'finance/fees-bundle-submit', {
        level_id: b.level_id, academic_year: b.academic_year, term_id: b.term_id, student_type_id: b.student_type_id, notes: 'Submitted for director review',
      });
      this.notify('Bundle submitted for review');
      this.load();
    } catch (e) { this.notify(e.message || 'Submit failed', 'danger'); }
  },

  setupEventListeners() {
    ['fspStatus', 'fspYear'].forEach(id => document.getElementById(id)?.addEventListener('change', () => this.load()));
  },

  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    this.setupEventListeners();
    await this.loadReference();
    await this.load();
  },
};

window.viewFeeStructureProgressController = viewFeeStructureProgressController;

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => viewFeeStructureProgressController.init().catch(() => {}));
} else {
  viewFeeStructureProgressController.init().catch(() => {});
}
