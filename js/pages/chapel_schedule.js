/**
 * chapel_schedule.js — Chapel Schedule Controller (standalone page).
 * Upcoming chapel services and events.
 */
const chapelScheduleController = {
  state: { upcoming: [], all: [] },

  API: (method, endpoint, data, params, opts) => window.callAPI(endpoint, method, data, params, opts),

  esc(s) { return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); },

  fmtDate(v) {
    if (!v) return '\u2014';
    const dt = new Date(String(v).replace(' ', 'T'));
    if (isNaN(dt)) return this.esc(v);
    return dt.toLocaleDateString();
  },

  norm(r) { return Array.isArray(r) ? r : (r?.data || r?.services || r?.events || r || []); },

  rows(list) {
    if (!list.length) return '<tr><td colspan="4" class="text-center py-4 text-muted">No chapel events found.</td></tr>';
    return list.map(e => `<tr>
      <td class="small fw-semibold">${this.esc(e.title || '\u2014')}</td>
      <td class="small">${this.fmtDate(e.date || e.service_date)}</td>
      <td class="small text-muted">${this.esc(e.type || '\u2014')}</td>
      <td class="small text-muted">${this.esc(e.description || '\u2014')}</td>
    </tr>`).join('');
  },

  async load() {
    const ub = document.getElementById('csUpcoming');
    const ab = document.getElementById('csAll');
    ub.innerHTML = '<tr><td colspan="4" class="text-center py-3"><div class="spinner-border spinner-border-sm"></div></td></tr>';
    ab.innerHTML = '<tr><td colspan="4" class="text-center py-3"><div class="spinner-border spinner-border-sm"></div></td></tr>';
    try {
      const [upRes, allRes] = await Promise.all([
        this.API('GET', '/chapel/services', null, { upcoming: 1, limit: 50 }).catch(() => []),
        this.API('GET', '/chapel/services', null, { limit: 50 }).catch(() => []),
      ]);
      this.state.upcoming = this.norm(upRes);
      this.state.all = this.norm(allRes);
      ub.innerHTML = this.rows(this.state.upcoming);
      ab.innerHTML = this.rows(this.state.all);
    } catch (e) { ub.innerHTML = `<tr><td colspan="4" class="text-center py-3 text-danger">${this.esc(e.message || 'Load failed')}</td></tr>`; }
  },

  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    await this.load();
  },
};

window.chapelScheduleController = chapelScheduleController;

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => chapelScheduleController.init().catch(() => {}));
} else {
  chapelScheduleController.init().catch(() => {});
}
