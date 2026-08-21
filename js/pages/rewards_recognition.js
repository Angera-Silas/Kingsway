/**
 * rewards_recognition.js — Rewards & Recognition Controller (standalone page).
 * Student commendations and rewards from discipline records.
 */
const rewardsRecognitionController = {
  state: { records: [] },

  API: (method, endpoint, data, params, opts) => window.callAPI(endpoint, method, data, params, opts),

  esc(s) { return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); },

  fmtDate(v) {
    if (!v) return '\u2014';
    const dt = new Date(String(v).replace(' ', 'T'));
    if (isNaN(dt)) return this.esc(v);
    return dt.toLocaleDateString();
  },

  norm(r) { return Array.isArray(r) ? r : (r?.data || r?.cases || r?.list || r || []); },

  isReward(c) {
    const hay = `${c.type || ''} ${c.description || ''} ${c.category || ''} ${c.title || ''}`.toLowerCase();
    return /reward|commend|recognition|merit|honour|honor|outstanding|good conduct|achievement|star/.test(hay);
  },

  async load() {
    const body = document.getElementById('rrBody');
    body.innerHTML = '<tr><td colspan="7" class="text-center py-3"><div class="spinner-border spinner-border-sm"></div></td></tr>';
    try {
      const r = await this.API('GET', '/students/discipline-get', null, { limit: 500 });
      const list = this.norm(r);
      this.state.records = list.filter(c => this.isReward(c));
      this.render();
    } catch (e) { body.innerHTML = `<tr><td colspan="7" class="text-center py-3 text-danger">${this.esc(e.message || 'Load failed')}</td></tr>`; }
  },

  render() {
    const body = document.getElementById('rrBody');
    const records = this.state.records;
    if (!records.length) { body.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted">No reward or recognition records found. Rewards may be recorded as discipline incident types (e.g. reward, commendation, recognition) in the system.</td></tr>'; return; }
    body.innerHTML = records.map(c => `<tr>
      <td class="small fw-semibold">${this.esc(`${c.first_name || ''} ${c.last_name || ''}`.trim() || '\u2014')}</td>
      <td class="small">${this.esc(c.admission_no || '\u2014')}</td>
      <td class="small text-muted">${this.esc(c.class_name || '\u2014')}</td>
      <td class="small">${this.esc(c.type || c.category || '\u2014')}</td>
      <td class="small">${this.fmtDate(c.incident_date)}</td>
      <td class="small text-muted">${this.esc(c.description || c.title || '\u2014')}</td>
      <td><span class="badge bg-success">${this.esc(c.status || 'recorded')}</span></td>
    </tr>`).join('');
  },

  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    await this.load();
  },
};

window.rewardsRecognitionController = rewardsRecognitionController;

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => rewardsRecognitionController.init().catch(() => {}));
} else {
  rewardsRecognitionController.init().catch(() => {});
}
