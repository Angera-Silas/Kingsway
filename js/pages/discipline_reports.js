/**
 * discipline_reports.js — Discipline Reports Controller (standalone page).
 * Disciplinary case statistics and monthly trends.
 */
const disciplineReportsController = {
  state: { byType: [], trend: [], cases: [] },

  API: (method, endpoint, data, params, opts) => window.callAPI(endpoint, method, data, params, opts),

  esc(s) { return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); },

  fmtDate(v) {
    if (!v) return '\u2014';
    const dt = new Date(String(v).replace(' ', 'T'));
    if (isNaN(dt)) return this.esc(v);
    return dt.toLocaleDateString();
  },

  norm(r) { return Array.isArray(r) ? r : (r?.data || r?.cases || r?.list || r || []); },

  badgeClass(s) {
    return { pending: 'bg-warning text-dark', approved: 'bg-success', resolved: 'bg-info text-dark', rejected: 'bg-danger' }[s] || 'bg-secondary';
  },

  async load() {
    const kb = document.getElementById('drKpis');
    const tb = document.getElementById('drByType');
    const trb = document.getElementById('drTrend');
    const body = document.getElementById('drBody');
    [kb, tb, trb].forEach(b => b && (b.innerHTML = '<tr><td colspan="3" class="text-center py-3"><div class="spinner-border spinner-border-sm"></div></td></tr>'));
    body.innerHTML = '<tr><td colspan="7" class="text-center py-3"><div class="spinner-border spinner-border-sm"></div></td></tr>';
    try {
      const [statsRes, trendRes, casesRes] = await Promise.all([
        this.API('GET', '/reports/conduct-cases-stats').catch(() => []),
        this.API('GET', '/reports/disciplinary-trends').catch(() => []),
        this.API('GET', '/students/discipline-get', null, { limit: 50 }).catch(() => []),
      ]);
      this.state.byType = Array.isArray(statsRes) ? statsRes : (statsRes?.data || []);
      this.state.trend = Array.isArray(trendRes) ? trendRes : (trendRes?.data || []);
      this.state.cases = this.norm(casesRes);
      this.render();
    } catch (e) { kb.innerHTML = `<p class="text-danger">${this.esc(e.message || 'Load failed')}</p>`; }
  },

  render() {
    const total = this.state.byType.reduce((s, r) => s + (Number(r.total) || 0), 0);
    const open = this.state.cases.filter(c => ['pending','open'].includes(c.status)).length;
    const resolved = this.state.cases.filter(c => ['resolved','approved'].includes(c.status)).length;
    const highSev = this.state.cases.filter(c => ['high','critical'].includes(c.severity)).length;

    document.getElementById('drKpis').innerHTML = [
      { label: 'Total Cases', value: total, icon: 'bi-shield-exclamation', color: 'danger' },
      { label: 'Pending / Open', value: open, icon: 'bi-hourglass-split', color: 'warning' },
      { label: 'Resolved', value: resolved, icon: 'bi-check2-circle', color: 'success' },
      { label: 'High / Critical Severity', value: highSev, icon: 'bi-exclamation-circle', color: 'danger' },
    ].map(k => `
      <div class="col-xl-3 col-md-6"><div class="border rounded-3 p-3 bg-white">
        <div class="d-flex justify-content-between align-items-center">
          <div><div class="small text-muted">${this.esc(k.label)}</div><div class="fs-4 fw-bold">${this.esc(k.value)}</div></div>
          <i class="bi ${k.icon} fs-3 text-${k.color}"></i>
        </div>
      </div></div>`).join('');

    const tb = document.getElementById('drByType');
    if (!this.state.byType.length) { tb.innerHTML = '<tr><td colspan="3" class="text-center py-4 text-muted">No data.</td></tr>'; }
    else {
      tb.innerHTML = this.state.byType.map(r => `<tr>
        <td class="small">${this.esc(r.case_type || '\u2014')}</td>
        <td class="small"><span class="badge ${r.status === 'resolved' ? 'bg-success' : 'bg-secondary'}">${this.esc(r.status || '\u2014')}</span></td>
        <td class="small fw-semibold">${this.esc(r.total ?? 0)}</td>
      </tr>`).join('');
    }

    const trb = document.getElementById('drTrend');
    if (!this.state.trend.length) { trb.innerHTML = '<tr><td colspan="3" class="text-center py-4 text-muted">No trend data.</td></tr>'; }
    else {
      const max = Math.max(...this.state.trend.map(t => Number(t.total) || 0), 1);
      trb.innerHTML = this.state.trend.map(t => {
        const n = Number(t.total) || 0;
        const pct = (n / max) * 100;
        const label = t.month ? `${this.esc(t.year)}-${String(this.esc(t.month)).padStart(2,'0')}` : '\u2014';
        return `<tr><td class="small">${label}</td><td class="small fw-semibold">${this.esc(n)}</td><td style="width:200px;"><div class="progress" style="height:8px;"><div class="progress-bar bg-danger" style="width:${this.esc(pct)}%"></div></div></td></tr>`;
      }).join('');
    }

    const body = document.getElementById('drBody');
    const sevColors = { high: 'bg-danger', medium: 'bg-warning text-dark', low: 'bg-secondary', critical: 'bg-dark' };
    if (!this.state.cases.length) { body.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted">No cases found.</td></tr>'; }
    else {
      body.innerHTML = this.state.cases.map(c => `<tr>
        <td class="small fw-semibold">${this.esc(`${c.first_name || ''} ${c.last_name || ''}`.trim() || '\u2014')}</td>
        <td class="small">${this.esc(c.admission_no || '\u2014')}</td>
        <td class="small text-muted">${this.esc(c.class_name || '\u2014')}</td>
        <td class="small">${this.esc(c.type || '\u2014')}</td>
        <td><span class="badge ${sevColors[c.severity] || 'bg-secondary'}">${this.esc(c.severity || '\u2014')}</span></td>
        <td class="small">${this.fmtDate(c.incident_date)}</td>
        <td><span class="badge ${this.badgeClass(c.status)}">${this.esc(c.status || '\u2014')}</span></td>
      </tr>`).join('');
    }
  },

  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    await this.load();
  },
};

window.disciplineReportsController = disciplineReportsController;

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => disciplineReportsController.init().catch(() => {}));
} else {
  disciplineReportsController.init().catch(() => {});
}
