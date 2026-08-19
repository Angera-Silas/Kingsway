/**
 * morning_roll_call.js — Morning Roll Call Controller (standalone page).
 * Morning roll call attendance per dormitory.
 */
const morningRollCallController = {
  SESSION_CODE: 'MORNING_ROLL_CALL',
  state: { rows: [] },

  API: (method, endpoint, data, params, opts) => window.callAPI(endpoint, method, data, params, opts),

  esc(s) { return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); },

  norm(r) { return Array.isArray(r) ? r : (r?.data || r?.roll_call || r?.rows || r || []); },

  async load() {
    const body = document.getElementById('mrcBody');
    body.innerHTML = '<tr><td colspan="9" class="text-center py-3"><div class="spinner-border spinner-border-sm"></div></td></tr>';
    try {
      const date = document.getElementById('mrcDate')?.value || new Date().toISOString().slice(0, 10);
      const r = await this.API('GET', '/boarding/roll-call', null, { date });
      this.state.rows = this.norm(r).filter(x => x.session_code === this.SESSION_CODE || x.session_name?.includes('Morning'));
      this.render();
    } catch (e) { body.innerHTML = `<tr><td colspan="9" class="text-center py-3 text-danger">${this.esc(e.message || 'Load failed')}</td></tr>`; }
  },

  render() {
    const body = document.getElementById('mrcBody');
    if (!this.state.rows.length) { body.innerHTML = '<tr><td colspan="9" class="text-center py-4 text-muted">No morning roll call data for the selected date.</td></tr>'; return; }
    body.innerHTML = this.state.rows.map(r => {
      const pct = Number(r.attendance_percentage ?? (r.total_students ? (r.present_count / r.total_students) * 100 : 0)) || 0;
      return `<tr>
        <td class="small fw-semibold">${this.esc(r.dormitory_name || '\u2014')}</td>
        <td class="small text-muted">${this.esc(r.house_parent || '\u2014')}</td>
        <td class="small">${this.esc(r.session_name || '\u2014')}</td>
        <td class="small text-center">${this.esc(r.total_students ?? 0)}</td>
        <td class="small text-center">${this.esc(r.present_count ?? 0)}</td>
        <td class="small text-center">${this.esc(r.absent_count ?? 0)}</td>
        <td class="small text-center">${this.esc(r.permission_count ?? 0)}</td>
        <td class="small text-center">${this.esc(r.sick_bay_count ?? 0)}</td>
        <td>
          <div class="d-flex align-items-center gap-2">
            <div class="progress flex-grow-1" style="height: 8px;"><div class="progress-bar ${pct >= 90 ? 'bg-success' : pct >= 70 ? 'bg-warning' : 'bg-danger'}" style="width:${this.esc(pct)}%"></div></div>
            <span class="small">${this.esc(pct.toFixed(1))}%</span>
          </div>
        </td>
      </tr>`;
    }).join('');
  },

  setupEventListeners() {
    document.getElementById('mrcDate')?.addEventListener('change', () => this.load());
  },

  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    this.setupEventListeners();
    await this.load();
  },
};

window.morningRollCallController = morningRollCallController;

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => morningRollCallController.init().catch(() => {}));
} else {
  morningRollCallController.init().catch(() => {});
}
