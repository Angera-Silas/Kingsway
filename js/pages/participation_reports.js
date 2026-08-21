/**
 * participation_reports.js — Participation Reports Controller (standalone page).
 * Student participation across co-curricular activities.
 */
const participationReportsController = {
  state: { participants: [] },

  API: (method, endpoint, data, params, opts) => window.callAPI(endpoint, method, data, params, opts),

  esc(s) { return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); },

  async load() {
    const ab = document.getElementById('prByActivity');
    const cb = document.getElementById('prByClass');
    ab.innerHTML = '<tr><td colspan="5" class="text-center py-3"><div class="spinner-border spinner-border-sm"></div></td></tr>';
    cb.innerHTML = '<tr><td colspan="3" class="text-center py-3"><div class="spinner-border spinner-border-sm"></div></td></tr>';
    try {
      const r = await this.API('GET', '/activities/participants/list', null, { limit: 500 });
      const resp = r?.data || r || [];
      this.state.participants = Array.isArray(resp) ? resp : (resp.participants || []);
      this.render();
    } catch (e) { ab.innerHTML = `<tr><td colspan="5" class="text-center py-3 text-danger">${this.esc(e.message || 'Load failed')}</td></tr>`; }
  },

  render() {
    const list = this.state.participants;
    const ab = document.getElementById('prByActivity');
    if (!list.length) { ab.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-muted">No participants found.</td></tr>'; }
    else {
      const byAct = {};
      list.forEach(p => {
        const key = p.activity_title || 'Unknown';
        byAct[key] = byAct[key] || { total: 0, active: 0, category: p.category_name };
        byAct[key].total += 1;
        if (p.status === 'active') byAct[key].active += 1;
      });
      ab.innerHTML = Object.entries(byAct).map(([title, v]) => {
        const pct = list.length ? (v.total / list.length) * 100 : 0;
        return `<tr>
          <td class="small fw-semibold">${this.esc(title)}</td>
          <td class="small">${this.esc(v.category || '\u2014')}</td>
          <td class="small text-center">${this.esc(v.total)}</td>
          <td class="small text-center text-success">${this.esc(v.active)}</td>
          <td style="width: 200px;"><div class="progress" style="height: 8px;"><div class="progress-bar bg-success" style="width:${this.esc(pct)}%"></div></div></td>
        </tr>`;
      }).join('');
    }

    const cb = document.getElementById('prByClass');
    const byClass = {};
    list.forEach(p => {
      const key = p.class_name || 'Unknown';
      byClass[key] = (byClass[key] || 0) + 1;
    });
    const keys = Object.keys(byClass);
    if (!keys.length) { cb.innerHTML = '<tr><td colspan="3" class="text-center py-4 text-muted">No participants found.</td></tr>'; }
    else {
      cb.innerHTML = keys.map(k => {
        const pct = list.length ? (byClass[k] / list.length) * 100 : 0;
        return `<tr>
          <td class="small fw-semibold">${this.esc(k)}</td>
          <td class="small text-center">${this.esc(byClass[k])}</td>
          <td style="width: 200px;"><div class="progress" style="height: 8px;"><div class="progress-bar bg-primary" style="width:${this.esc(pct)}%"></div></div></td>
        </tr>`;
      }).join('');
    }
  },

  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    await this.load();
  },
};

window.participationReportsController = participationReportsController;

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => participationReportsController.init().catch(() => {}));
} else {
  participationReportsController.init().catch(() => {});
}
