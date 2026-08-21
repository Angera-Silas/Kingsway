/**
 * counseling_reports.js — Counseling Reports Controller (standalone page).
 * Counseling analytics and session trends.
 */
const counselingReportsController = {
  state: { summary: {} },

  API: (method, endpoint, data, params, opts) => window.callAPI(endpoint, method, data, params, opts),

  esc(s) { return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); },

  fmtDate(v) {
    if (!v) return '\u2014';
    const dt = new Date(String(v).replace(' ', 'T'));
    if (isNaN(dt)) return this.esc(v);
    return dt.toLocaleDateString();
  },

  async load() {
    const kb = document.getElementById('crKpis');
    const tb = document.getElementById('crByType');
    const trb = document.getElementById('crTrend');
    const fb = document.getElementById('crFollow');
    [kb, tb, trb, fb].forEach(b => b && (b.innerHTML = '<tr><td colspan="5" class="text-center py-3"><div class="spinner-border spinner-border-sm"></div></td></tr>'));
    try {
      const sumRes = await this.API('GET', '/counseling/summary').catch(() => ({}));
      this.state.summary = sumRes?.data || sumRes || {};
      this.render();
    } catch (e) { kb.innerHTML = `<p class="text-danger">${this.esc(e.message || 'Load failed')}</p>`; }
  },

  render() {
    const s = this.state.summary;
    const kpis = [
      { label: 'Total Cases', value: s.total ?? s.total_sessions ?? '—', icon: 'bi-folder2', color: 'primary' },
      { label: 'Open Cases', value: s.open_cases ?? s.active ?? '—', icon: 'bi-folder2-open', color: 'warning' },
      { label: 'Urgent', value: s.urgent_cases ?? '—', icon: 'bi-exclamation-circle', color: 'danger' },
      { label: 'Follow-ups Due', value: s.follow_ups_due ?? '—', icon: 'bi-calendar-check', color: 'info' },
      { label: 'Resolved / Closed', value: s.completed ?? '—', icon: 'bi-check2-circle', color: 'success' },
      { label: 'Active Counselees', value: s.active_students ?? '—', icon: 'bi-people', color: 'primary' },
      { label: 'Total Sessions', value: s.total_sessions ?? '—', icon: 'bi-clipboard', color: 'secondary' },
      { label: 'This Month Sessions', value: s.sessions_this_month ?? '—', icon: 'bi-calendar', color: 'secondary' },
    ];
    document.getElementById('crKpis').innerHTML = kpis.map(k => `
      <div class="col-xl-3 col-md-6"><div class="border rounded-3 p-3 bg-white">
        <div class="d-flex justify-content-between align-items-center">
          <div><div class="small text-muted">${this.esc(k.label)}</div><div class="fs-4 fw-bold">${this.esc(k.value)}</div></div>
          <i class="bi ${k.icon} fs-3 text-${k.color}"></i>
        </div>
      </div></div>`).join('');

    const byType = s.by_type || [];
    const tb = document.getElementById('crByType');
    if (!byType.length) { tb.innerHTML = '<tr><td colspan="2" class="text-center py-4 text-muted">No data.</td></tr>'; }
    else { tb.innerHTML = byType.map(t => `<tr><td class="small">${this.esc(t.case_type || '\u2014')}</td><td class="small fw-semibold">${this.esc(t.case_count ?? 0)}</td></tr>`).join(''); }

    const trend = s.session_trend || [];
    const trb = document.getElementById('crTrend');
    if (!trend.length) { trb.innerHTML = '<tr><td colspan="3" class="text-center py-4 text-muted">No data.</td></tr>'; }
    else {
      const max = Math.max(...trend.map(t => Number(t.session_count) || 0), 1);
      trb.innerHTML = trend.map(t => {
        const n = Number(t.session_count) || 0;
        const pct = (n / max) * 100;
        return `<tr><td class="small">${this.esc(t.month || '\u2014')}</td><td class="small fw-semibold">${this.esc(n)}</td><td style="width:200px;"><div class="progress" style="height:8px;"><div class="progress-bar bg-primary" style="width:${this.esc(pct)}%"></div></div></td></tr>`;
      }).join('');
    }

    const follow = s.follow_ups || [];
    const fb = document.getElementById('crFollow');
    const prioMap = { urgent: 'bg-danger', high: 'bg-warning text-dark', medium: 'bg-info text-dark', low: 'bg-secondary' };
    if (!follow.length) { fb.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-muted">No follow-ups due.</td></tr>'; }
    else {
      fb.innerHTML = follow.map(c => `<tr>
        <td class="small fw-semibold">${this.esc(c.case_code || `#${c.id}`)}</td>
        <td class="small">${this.esc(c.counselee_name || '\u2014')}</td>
        <td class="small text-muted">${this.esc(c.case_type || '\u2014')}</td>
        <td><span class="badge ${prioMap[c.priority] || 'bg-secondary'}">${this.esc(c.priority || '\u2014')}</span></td>
        <td class="small">${this.fmtDate(c.next_follow_up_at)}</td>
      </tr>`).join('');
    }
  },

  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    await this.load();
  },
};

window.counselingReportsController = counselingReportsController;

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => counselingReportsController.init().catch(() => {}));
} else {
  counselingReportsController.init().catch(() => {});
}
