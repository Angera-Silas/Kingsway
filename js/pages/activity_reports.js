/**
 * activity_reports.js — Activity Reports Controller (standalone page).
 * Co-curricular activity analytics.
 */
const activityReportsController = {
  state: { stats: {}, upcoming: [], list: [] },

  API: (method, endpoint, data, params, opts) => window.callAPI(endpoint, method, data, params, opts),

  esc(s) { return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); },

  norm(r) { return Array.isArray(r) ? r : (r?.data || r?.upcoming || r?.activities || r?.list || r || []); },

  badgeClass(s) {
    return { planned: 'bg-secondary', ongoing: 'bg-primary', completed: 'bg-success', cancelled: 'bg-danger' }[s] || 'bg-secondary';
  },

  async load() {
    const ub = document.getElementById('arUpcoming');
    const lb = document.getElementById('arList');
    document.getElementById('arKpis').innerHTML = '';
    ub.innerHTML = '<tr><td colspan="5" class="text-center py-3"><div class="spinner-border spinner-border-sm"></div></td></tr>';
    lb.innerHTML = '<tr><td colspan="5" class="text-center py-3"><div class="spinner-border spinner-border-sm"></div></td></tr>';
    try {
      const [statsRes, upRes, listRes] = await Promise.all([
        this.API('GET', '/activities/statistics/get').catch(() => ({})),
        this.API('GET', '/activities/upcoming/list', null, { limit: 10 }).catch(() => []),
        this.API('GET', '/activities', null, { limit: 100 }).catch(() => []),
      ]);
      this.state.stats = statsRes?.data || statsRes || {};
      this.state.upcoming = this.norm(upRes);
      this.state.list = this.norm(listRes);
      this.render();
    } catch (e) { ub.innerHTML = `<tr><td colspan="5" class="text-center py-3 text-danger">${this.esc(e.message || 'Load failed')}</td></tr>`; }
  },

  render() {
    const s = this.state.stats;
    const kpis = [
      { label: 'Total Activities', value: s.total_activities ?? '—', icon: 'bi-calendar-event', color: 'primary' },
      { label: 'Planned', value: s.planned ?? '—', icon: 'bi-hourglass-split', color: 'secondary' },
      { label: 'Ongoing', value: s.ongoing ?? '—', icon: 'bi-play-circle', color: 'info' },
      { label: 'Completed', value: s.completed ?? '—', icon: 'bi-check2-circle', color: 'success' },
      { label: 'Cancelled', value: s.cancelled ?? '—', icon: 'bi-x-circle', color: 'danger' },
    ];
    document.getElementById('arKpis').innerHTML = kpis.map(k => `
      <div class="col-xl-3 col-md-6"><div class="border rounded-3 p-3 bg-white">
        <div class="d-flex justify-content-between align-items-center">
          <div><div class="small text-muted">${this.esc(k.label)}</div><div class="fs-4 fw-bold">${this.esc(k.value)}</div></div>
          <i class="bi ${k.icon} fs-3 text-${k.color}"></i>
        </div>
      </div></div>`).join('');

    const ub = document.getElementById('arUpcoming');
    if (!this.state.upcoming.length) { ub.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-muted">No upcoming activities.</td></tr>'; }
    else {
      ub.innerHTML = this.state.upcoming.map(a => `<tr>
        <td class="small fw-semibold">${this.esc(a.title || '\u2014')}</td>
        <td class="small">${this.esc(a.category_name || '\u2014')}</td>
        <td class="small">${this.esc(a.start_date || '\u2014')}</td>
        <td class="small">${a.days_until_start != null ? this.esc(a.days_until_start) + 'd' : '\u2014'}</td>
        <td class="small text-center">${this.esc(a.participant_count ?? 0)}</td>
      </tr>`).join('');
    }

    const lb = document.getElementById('arList');
    if (!this.state.list.length) { lb.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-muted">No activities found.</td></tr>'; }
    else {
      lb.innerHTML = this.state.list.map(a => `<tr>
        <td class="small fw-semibold">${this.esc(a.title || '\u2014')}</td>
        <td class="small">${this.esc(a.category_name || '\u2014')}</td>
        <td><span class="badge ${this.badgeClass(a.status)}">${this.esc(a.status || '\u2014')}</span></td>
        <td class="small text-center">${this.esc(a.active_participants ?? a.participant_count ?? 0)}</td>
        <td class="small">${this.esc(a.start_date || '\u2014')}</td>
      </tr>`).join('');
    }
  },

  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    await this.load();
  },
};

window.activityReportsController = activityReportsController;

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => activityReportsController.init().catch(() => {}));
} else {
  activityReportsController.init().catch(() => {});
}
