/**
 * manage_job_applications.js — Job Applications Controller (standalone page).
 * Read-only review of applications received via the public Careers page.
 */
const jobAppsController = {
  state: { items: [] },

  API: (method, endpoint, data, params, opts) => window.callAPI(endpoint, method, data, params, opts),

  notify(msg, type = 'success') {
    if (window.showNotification) { showNotification(msg, type); return; }
    const el = document.createElement('div');
    el.className = `alert alert-${type} position-fixed top-0 end-0 m-3 shadow`;
    el.style.zIndex = 99999;
    el.textContent = msg;
    document.body.appendChild(el);
    setTimeout(() => el.remove(), 3000);
  },

  esc(s) { return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); },
  fmtDate(d) { return d ? new Date(d).toLocaleDateString('en-KE', { day: '2-digit', month: 'short', year: 'numeric' }) : '\u2014'; },
  badgeStatus(s) {
    const colors = { new: 'primary', shortlisted: 'info', interview_scheduled: 'warning', accepted: 'success', rejected: 'danger' };
    const c = colors[s] || 'secondary';
    const label = (s || '').replace(/_/g, ' ');
    return `<span class="badge bg-${c}">${label || '\u2014'}</span>`;
  },

  async loadData() {
    const body = document.getElementById('jobAppsTableBody');
    body.innerHTML = '<tr><td colspan="8" class="text-center py-3"><div class="spinner-border spinner-border-sm"></div></td></tr>';
    try {
      const r = await this.API('GET', 'website/job-applications');
      this.state.items = r?.data?.items || [];
      this.render();
    } catch (e) {
      body.innerHTML = `<tr><td colspan="8" class="text-center py-3 text-danger">${this.esc(e.message || 'Load failed')}</td></tr>`;
    }
  },

  render() {
    const body = document.getElementById('jobAppsTableBody');
    document.getElementById('statApps').textContent = this.state.items.length;
    const status = document.getElementById('appStatusFilter').value;
    const q = (document.getElementById('appSearch').value || '').toLowerCase();
    const filtered = this.state.items.filter(a => {
      const okStatus = !status || a.status === status;
      const hay = `${a.first_name} ${a.last_name} ${a.job_title} ${a.email} ${a.tsc_number || ''}`.toLowerCase();
      return okStatus && (!q || hay.includes(q));
    });
    if (!filtered.length) { body.innerHTML = '<tr><td colspan="8" class="text-center py-4 text-muted">No job applications found.</td></tr>'; return; }
    body.innerHTML = filtered.map(a => `
      <tr>
        <td class="fw-semibold small">${this.esc(a.first_name)} ${this.esc(a.last_name)}</td>
        <td class="small">${this.esc(a.job_title)}</td>
        <td class="small text-muted">${this.esc(a.email)}</td>
        <td class="small text-muted">${this.esc(a.phone)}</td>
        <td class="small text-muted">${this.esc(a.tsc_number || '\u2014')}</td>
        <td>${this.badgeStatus(a.status)}</td>
        <td class="small text-muted">${this.fmtDate(a.created_at)}</td>
        <td class="text-end">
          <button class="btn btn-sm btn-outline-secondary rounded-pill px-2" title="Copy email" onclick="jobAppsCopy('${this.esc(a.email)}')"><i class="bi bi-envelope"></i></button>
        </td>
      </tr>`).join('');
  },

  copy(email) {
    navigator.clipboard?.writeText(email).then(() => this.notify('Email copied')).catch(() => {});
  },

  setupEventListeners() {
    document.getElementById('appStatusFilter')?.addEventListener('change', () => this.render());
    const searchEl = document.getElementById('appSearch');
    if (searchEl) searchEl.addEventListener('keyup', () => this.render());
  },

  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    this.setupEventListeners();
    this.loadData();
  },
};

window.jobAppsController = jobAppsController;
window.jobAppsCopy       = (email) => jobAppsController.copy(email);

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => jobAppsController.init().catch(() => {}));
} else {
  jobAppsController.init().catch(() => {});
}
