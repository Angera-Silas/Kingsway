/**
 * health_reports.js — Health Reports Controller (standalone page).
 * School health overview and sick bay activity.
 */
const healthReportsController = {
  state: { summary: {}, visits: [] },

  API: (method, endpoint, data, params, opts) => window.callAPI(endpoint, method, data, params, opts),

  esc(s) { return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); },

  fmtDate(v) {
    if (!v) return '\u2014';
    const dt = new Date(String(v).replace(' ', 'T'));
    if (isNaN(dt)) return this.esc(v);
    return dt.toLocaleString();
  },

  norm(r) { return Array.isArray(r) ? r : (r?.data || r?.visits || r || []); },

  async load() {
    const body = document.getElementById('hrBody');
    document.getElementById('hrKpis').innerHTML = '';
    body.innerHTML = '<tr><td colspan="7" class="text-center py-3"><div class="spinner-border spinner-border-sm"></div></td></tr>';
    try {
      const [sumRes, visRes] = await Promise.all([
        this.API('GET', '/health/summary').catch(() => ({})),
        this.API('GET', '/health/sick-bay').catch(() => []),
      ]);
      this.state.summary = sumRes?.data || sumRes || {};
      this.state.visits = this.norm(visRes);
      this.renderKpis();
      this.renderVisits();
    } catch (e) { body.innerHTML = `<tr><td colspan="7" class="text-center py-3 text-danger">${this.esc(e.message || 'Load failed')}</td></tr>`; }
  },

  renderKpis() {
    const s = this.state.summary;
    const kpis = [
      { label: 'Active Sick Bay', value: s.active_sick_bay ?? '—', icon: 'bi-hospital', color: 'danger' },
      { label: 'Visits Today', value: s.visits_today ?? '—', icon: 'bi-calendar-check', color: 'primary' },
      { label: 'Hospital Referrals', value: s.referred ?? '—', icon: 'bi-ambulance', color: 'warning' },
      { label: 'Students With Records', value: s.students_with_records ?? '—', icon: 'bi-folder2-open', color: 'info' },
      { label: 'Vaccinations Due (30d)', value: s.vaccinations_due ?? '—', icon: 'bi-eyedropper', color: 'success' },
    ];
    document.getElementById('hrKpis').innerHTML = kpis.map(k => `
      <div class="col-xl-3 col-md-6"><div class="border rounded-3 p-3 bg-white">
        <div class="d-flex justify-content-between align-items-center">
          <div><div class="small text-muted">${this.esc(k.label)}</div><div class="fs-4 fw-bold">${this.esc(k.value)}</div></div>
          <i class="bi ${k.icon} fs-3 text-${k.color}"></i>
        </div>
      </div></div>`).join('');
  },

  renderVisits() {
    const body = document.getElementById('hrBody');
    if (!this.state.visits.length) { body.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted">No sick bay visits found.</td></tr>'; return; }
    body.innerHTML = this.state.visits.map(v => `<tr>
      <td class="small">${this.esc(v.admission_no || '\u2014')}</td>
      <td class="small fw-semibold">${this.esc(`${v.first_name || ''} ${v.last_name || ''}`.trim() || '\u2014')}</td>
      <td class="small">${this.esc(v.class_name || '\u2014')}</td>
      <td class="small">${this.esc(v.complaint || '\u2014')}</td>
      <td class="small">${this.fmtDate(v.visit_time || v.visit_date)}</td>
      <td>${v.referred_to_hospital ? '<span class="badge bg-danger">Yes</span>' : '<span class="badge bg-secondary">No</span>'}</td>
      <td class="small text-muted">${this.esc(v.action_taken || '\u2014')}</td>
    </tr>`).join('');
  },

  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    await this.load();
  },
};

window.healthReportsController = healthReportsController;

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => healthReportsController.init().catch(() => {}));
} else {
  healthReportsController.init().catch(() => {});
}
