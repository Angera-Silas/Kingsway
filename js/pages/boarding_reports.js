/**
 * boarding_reports.js — Boarding Reports Controller (standalone page).
 * Boarding KPIs and dormitory occupancy summary.
 */
const boardingReportsController = {
  state: { stats: {}, occupancy: [] },

  API: (method, endpoint, data, params, opts) => window.callAPI(endpoint, method, data, params, opts),

  esc(s) { return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); },

  norm(r) { return Array.isArray(r) ? r : (r?.data || r?.occupancy || r?.dormitories || r || []); },

  async load() {
    const body = document.getElementById('brBody');
    document.getElementById('brKpis').innerHTML = '';
    body.innerHTML = '<tr><td colspan="8" class="text-center py-3"><div class="spinner-border spinner-border-sm"></div></td></tr>';
    try {
      const [statsRes, occRes] = await Promise.all([
        this.API('GET', '/boarding/stats').catch(() => ({})),
        this.API('GET', '/boarding/occupancy').catch(() => []),
      ]);
      this.state.stats = statsRes?.data || statsRes || {};
      this.state.occupancy = this.norm(occRes);
      this.renderKpis();
      this.renderOccupancy();
    } catch (e) { body.innerHTML = `<tr><td colspan="8" class="text-center py-3 text-danger">${this.esc(e.message || 'Load failed')}</td></tr>`; }
  },

  renderKpis() {
    const s = this.state.stats;
    const kpis = [
      { label: 'Total Boarders', value: s.assigned_beds ?? '—', icon: 'bi-people', color: 'primary' },
      { label: 'Occupancy Rate', value: s.occupancy_rate != null ? `${s.occupancy_rate}%` : '—', icon: 'bi-house-check', color: 'success' },
      { label: 'Available Beds', value: s.available_beds ?? '—', icon: 'bi-house-door', color: 'info' },
      { label: 'Present Tonight', value: s.present_tonight ?? '—', icon: 'bi-check2-circle', color: 'success' },
      { label: 'On Leave', value: s.on_leave ?? '—', icon: 'bi-sign-turn-right', color: 'warning' },
      { label: 'Pending Leaves', value: s.pending_leaves ?? '—', icon: 'bi-hourglass-split', color: 'secondary' },
      { label: 'Absent / Unknown', value: s.absent_or_unknown ?? '—', icon: 'bi-question-circle', color: 'danger' },
      { label: 'Urgent Notes', value: s.urgent_notes ?? '—', icon: 'bi-exclamation-triangle', color: 'danger' },
    ];
    document.getElementById('brKpis').innerHTML = kpis.map(k => `
      <div class="col-xl-3 col-md-6"><div class="border rounded-3 p-3 bg-white">
        <div class="d-flex justify-content-between align-items-center">
          <div><div class="small text-muted">${this.esc(k.label)}</div><div class="fs-4 fw-bold">${this.esc(k.value)}</div></div>
          <i class="bi ${k.icon} fs-3 text-${k.color}"></i>
        </div>
      </div></div>`).join('');
  },

  renderOccupancy() {
    const body = document.getElementById('brBody');
    if (!this.state.occupancy.length) { body.innerHTML = '<tr><td colspan="8" class="text-center py-4 text-muted">No dormitory data found.</td></tr>'; return; }
    body.innerHTML = this.state.occupancy.map(o => {
      const cap = Number(o.capacity ?? 0);
      const occ = Number(o.occupied ?? 0);
      const pct = cap ? Math.round((occ / cap) * 100) : 0;
      return `<tr>
        <td class="small fw-semibold">${this.esc(o.dormitory_name || '\u2014')}</td>
        <td class="small text-muted">${this.esc(o.code || '\u2014')}</td>
        <td class="small">${this.esc(o.gender || '\u2014')}</td>
        <td class="small">${this.esc(o.house_parent || '\u2014')}</td>
        <td class="small text-center">${this.esc(cap)}</td>
        <td class="small text-center">${this.esc(occ)}</td>
        <td class="small text-center">${this.esc(o.available ?? 0)}</td>
        <td style="width: 220px;">
          <div class="d-flex align-items-center gap-2">
            <div class="progress flex-grow-1" style="height: 8px;"><div class="progress-bar ${pct >= 90 ? 'bg-danger' : pct >= 70 ? 'bg-warning' : 'bg-success'}" style="width:${this.esc(pct)}%"></div></div>
            <span class="small">${this.esc(pct)}%</span>
          </div>
        </td>
      </tr>`;
    }).join('');
  },

  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    await this.load();
  },
};

window.boardingReportsController = boardingReportsController;

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => boardingReportsController.init().catch(() => {}));
} else {
  boardingReportsController.init().catch(() => {});
}
