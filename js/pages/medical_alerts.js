/**
 * medical_alerts.js — Medical Alerts Controller (standalone page).
 * Active health alerts and current sick bay visits.
 */
const medicalAlertsController = {
  state: { records: [], visits: [], summary: {} },

  API: (method, endpoint, data, params, opts) => window.callAPI(endpoint, method, data, params, opts),

  esc(s) { return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); },

  fmtDate(v) {
    if (!v) return '\u2014';
    const dt = new Date(String(v).replace(' ', 'T'));
    if (isNaN(dt)) return this.esc(v);
    return dt.toLocaleString();
  },

  norm(r) { return Array.isArray(r) ? r : (r?.data || r?.records || r?.visits || r || []); },

  async load() {
    const alertsBody = document.getElementById('maAlertsBody');
    const sickBody = document.getElementById('maSickBody');
    alertsBody.innerHTML = '<tr><td colspan="6" class="text-center py-3"><div class="spinner-border spinner-border-sm"></div></td></tr>';
    sickBody.innerHTML = alertsBody.innerHTML;
    try {
      const [recRes, sickRes, sumRes] = await Promise.all([
        this.API('GET', '/health/records').catch(() => []),
        this.API('GET', '/health/sick-bay', null, { status: 'active' }).catch(() => []),
        this.API('GET', '/health/summary').catch(() => ({})),
      ]);
      this.state.records = this.norm(recRes);
      this.state.visits = this.norm(sickRes);
      this.state.summary = sumRes?.data || sumRes || {};
      this.render();
    } catch (e) {
      alertsBody.innerHTML = `<tr><td colspan="6" class="text-center py-3 text-danger">${this.esc(e.message || 'Load failed')}</td></tr>`;
    }
  },

  render() {
    const s = this.state.summary;
    document.getElementById('maSickBay').textContent = s.active_sick_bay ?? this.state.visits.length ?? '—';
    document.getElementById('maVisits').textContent = s.visits_today ?? '—';
    document.getElementById('maReferred').textContent = s.referred ?? '—';
    document.getElementById('maVaxDue').textContent = s.vaccinations_due ?? '—';

    const alerts = this.state.records.filter(r =>
      (r.alert_type && r.status === 'active') || r.emergency_flag == 1 || ['high', 'critical'].includes(r.severity)
    );
    const alertsBody = document.getElementById('maAlertsBody');
    if (!alerts.length) { alertsBody.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-muted">No active medical alerts.</td></tr>'; }
    else {
      alertsBody.innerHTML = alerts.map(r => `<tr>
        <td class="small">${this.esc(r.admission_no || '\u2014')}</td>
        <td class="small fw-semibold">${this.esc(`${r.first_name || ''} ${r.last_name || ''}`.trim() || '\u2014')}</td>
        <td class="small">${this.esc(r.health_category || '\u2014')}</td>
        <td class="small">${this.esc(r.alert_type || r.condition_name || r.allergy_name || r.medication_name || '\u2014')}</td>
        <td><span class="badge ${r.severity === 'critical' ? 'bg-danger' : r.severity === 'high' ? 'bg-warning text-dark' : 'bg-secondary'}">${this.esc(r.severity || 'low')}</span></td>
        <td class="small text-muted">${this.esc(r.action_instructions || '\u2014')}</td>
      </tr>`).join('');
    }

    const sickBody = document.getElementById('maSickBody');
    if (!this.state.visits.length) { sickBody.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-muted">No active sick bay visits.</td></tr>'; }
    else {
      sickBody.innerHTML = this.state.visits.map(v => `<tr>
        <td class="small">${this.esc(v.admission_no || '\u2014')}</td>
        <td class="small fw-semibold">${this.esc(`${v.first_name || ''} ${v.last_name || ''}`.trim() || '\u2014')}</td>
        <td class="small">${this.esc(v.class_name || '\u2014')}</td>
        <td class="small">${this.esc(v.complaint || '\u2014')}</td>
        <td class="small">${this.fmtDate(v.visit_time || v.visit_date)}</td>
        <td class="small text-muted">${this.esc(v.observation || '\u2014')}</td>
      </tr>`).join('');
    }
  },

  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    await this.load();
  },
};

window.medicalAlertsController = medicalAlertsController;

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => medicalAlertsController.init().catch(() => {}));
} else {
  medicalAlertsController.init().catch(() => {});
}
