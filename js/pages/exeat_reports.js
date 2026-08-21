/**
 * exeat_reports.js — Exeat Reports Controller (standalone page).
 * Leave request summary by status and dormitory.
 */
const exeatReportsController = {
  state: { requests: [] },

  API: (method, endpoint, data, params, opts) => window.callAPI(endpoint, method, data, params, opts),

  esc(s) { return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); },

  fmtDate(v) {
    if (!v) return '\u2014';
    const dt = new Date(String(v).replace(' ', 'T'));
    if (isNaN(dt)) return this.esc(v);
    return dt.toLocaleDateString();
  },

  norm(r) { return Array.isArray(r) ? r : (r?.data || r?.requests || r?.exeats || r || []); },

  badgeClass(s) {
    return { approved: 'bg-success', pending: 'bg-warning text-dark', rejected: 'bg-danger', checked_out: 'bg-primary', checked_in: 'bg-secondary' }[s] || 'bg-secondary';
  },

  async load() {
    const body = document.getElementById('xrBody');
    document.getElementById('xrKpis').innerHTML = '';
    document.getElementById('xrStatusBody').innerHTML = '<tr><td colspan="3" class="text-center py-3"><div class="spinner-border spinner-border-sm"></div></td></tr>';
    body.innerHTML = '<tr><td colspan="7" class="text-center py-3"><div class="spinner-border spinner-border-sm"></div></td></tr>';
    try {
      const r = await this.API('GET', '/boarding/exeats');
      this.state.requests = this.norm(r);
      this.render();
    } catch (e) { body.innerHTML = `<tr><td colspan="7" class="text-center py-3 text-danger">${this.esc(e.message || 'Load failed')}</td></tr>`; }
  },

  render() {
    const req = this.state.requests;
    const total = req.length;
    document.getElementById('xrKpis').innerHTML = [
      { label: 'Total Leave Requests', value: total, icon: 'bi-list', color: 'primary' },
      { label: 'Approved', value: req.filter(x => x.status === 'approved').length, icon: 'bi-check2-circle', color: 'success' },
      { label: 'Pending', value: req.filter(x => x.status === 'pending').length, icon: 'bi-hourglass-split', color: 'warning' },
      { label: 'Rejected', value: req.filter(x => x.status === 'rejected').length, icon: 'bi-x-circle', color: 'danger' },
    ].map(k => `
      <div class="col-xl-3 col-md-6"><div class="border rounded-3 p-3 bg-white">
        <div class="d-flex justify-content-between align-items-center">
          <div><div class="small text-muted">${this.esc(k.label)}</div><div class="fs-4 fw-bold">${this.esc(k.value)}</div></div>
          <i class="bi ${k.icon} fs-3 text-${k.color}"></i>
        </div>
      </div></div>`).join('');

    const groups = {};
    req.forEach(x => { groups[x.status || 'unknown'] = (groups[x.status || 'unknown'] || 0) + 1; });
    const statusBody = document.getElementById('xrStatusBody');
    const keys = Object.keys(groups);
    if (!keys.length) { statusBody.innerHTML = '<tr><td colspan="3" class="text-center py-4 text-muted">No leave requests found.</td></tr>'; }
    else {
      statusBody.innerHTML = keys.map(k => `<tr>
        <td><span class="badge ${this.badgeClass(k)}">${this.esc(k)}</span></td>
        <td class="small fw-semibold">${this.esc(groups[k])}</td>
        <td style="width: 200px;">
          <div class="progress" style="height: 8px;"><div class="progress-bar" style="width:${this.esc(total ? (groups[k] / total) * 100 : 0)}%"></div></div>
        </td>
      </tr>`).join('');
    }

    const body = document.getElementById('xrBody');
    if (!req.length) { body.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted">No leave requests found.</td></tr>'; return; }
    body.innerHTML = req.slice(0, 100).map(r => `<tr>
      <td class="small">${this.esc(r.admission_no || '\u2014')}</td>
      <td class="small fw-semibold">${this.esc(r.student_name || '\u2014')}</td>
      <td class="small">${this.esc(r.dormitory_name || '\u2014')}</td>
      <td class="small">${this.esc(r.permission_type_name || '\u2014')}</td>
      <td class="small">${this.fmtDate(r.start_date)}</td>
      <td class="small">${this.fmtDate(r.end_date)}</td>
      <td><span class="badge ${this.badgeClass(r.status)}">${this.esc(r.status || '\u2014')}</span></td>
    </tr>`).join('');
  },

  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    await this.load();
  },
};

window.exeatReportsController = exeatReportsController;

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => exeatReportsController.init().catch(() => {}));
} else {
  exeatReportsController.init().catch(() => {});
}
