/**
 * vaccination_records.js — Vaccination Records Controller (standalone page).
 * Immunisation history and doses due within 30 days.
 */
const vaccinationRecordsController = {
  state: { due: [], all: [] },

  API: (method, endpoint, data, params, opts) => window.callAPI(endpoint, method, data, params, opts),

  esc(s) { return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); },

  fmtDate(v) {
    if (!v) return '\u2014';
    const dt = new Date(String(v).replace(' ', 'T'));
    if (isNaN(dt)) return this.esc(v);
    return dt.toLocaleDateString();
  },

  norm(r) { return Array.isArray(r) ? r : (r?.data || r?.records || r?.vaccinations || r || []); },

  name(r) { return `${r.first_name || ''} ${r.last_name || ''}`.trim() || '\u2014'; },

  async load() {
    const dueBody = document.getElementById('vcDueBody');
    const allBody = document.getElementById('vcAllBody');
    dueBody.innerHTML = '<tr><td colspan="7" class="text-center py-3"><div class="spinner-border spinner-border-sm"></div></td></tr>';
    allBody.innerHTML = '<tr><td colspan="8" class="text-center py-3"><div class="spinner-border spinner-border-sm"></div></td></tr>';
    try {
      const [dueRes, allRes] = await Promise.all([
        this.API('GET', '/health/vaccinations', null, { due_only: 1 }).catch(() => []),
        this.API('GET', '/health/vaccinations').catch(() => []),
      ]);
      this.state.due = this.norm(dueRes);
      this.state.all = this.norm(allRes);
      this.render();
    } catch (e) { dueBody.innerHTML = `<tr><td colspan="7" class="text-center py-3 text-danger">${this.esc(e.message || 'Load failed')}</td></tr>`; }
  },

  render() {
    const dueBody = document.getElementById('vcDueBody');
    if (!this.state.due.length) { dueBody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted">No vaccinations due within the next 30 days.</td></tr>'; }
    else {
      dueBody.innerHTML = this.state.due.map(v => `<tr>
        <td class="small">${this.esc(v.admission_no || '\u2014')}</td>
        <td class="small fw-semibold">${this.esc(this.name(v))}</td>
        <td class="small">${this.esc(v.class_name || '\u2014')}</td>
        <td class="small">${this.esc(v.vaccine_name || '\u2014')}</td>
        <td class="small">${this.esc(v.dose_number || 1)}</td>
        <td class="small">${this.fmtDate(v.date_given)}</td>
        <td class="small"><span class="badge bg-warning text-dark">${this.fmtDate(v.next_due_date)}</span></td>
      </tr>`).join('');
    }

    const allBody = document.getElementById('vcAllBody');
    if (!this.state.all.length) { allBody.innerHTML = '<tr><td colspan="8" class="text-center py-4 text-muted">No vaccination records found.</td></tr>'; }
    else {
      allBody.innerHTML = this.state.all.map(v => `<tr>
        <td class="small">${this.esc(v.admission_no || '\u2014')}</td>
        <td class="small fw-semibold">${this.esc(this.name(v))}</td>
        <td class="small">${this.esc(v.class_name || '\u2014')}</td>
        <td class="small">${this.esc(v.vaccine_name || '\u2014')}</td>
        <td class="small">${this.esc(v.dose_number || 1)}</td>
        <td class="small">${this.fmtDate(v.date_given)}</td>
        <td class="small">${this.fmtDate(v.next_due_date)}</td>
        <td class="small text-muted">${this.esc(v.given_by || '\u2014')}</td>
      </tr>`).join('');
    }
  },

  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    await this.load();
  },
};

window.vaccinationRecordsController = vaccinationRecordsController;

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => vaccinationRecordsController.init().catch(() => {}));
} else {
  vaccinationRecordsController.init().catch(() => {});
}
