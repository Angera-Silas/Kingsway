/**
 * budget_actuals.js — Budget vs Actuals Controller (standalone page).
 * Accountant compares budgeted amounts with allocated, committed and actual spend.
 */
const budgetActualsController = {
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

  esc(s) { return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); },

  fmtKES(n) { return 'KES ' + Number(n || 0).toLocaleString(); },

  statusBadge(s) { return { approved: 'bg-success', active: 'bg-success', draft: 'bg-warning text-dark', pending: 'bg-info', rejected: 'bg-danger', archived: 'bg-secondary' }[s] || 'bg-secondary'; },

  async load() {
    const body = document.getElementById('baBody');
    body.innerHTML = '<tr><td colspan="9" class="text-center py-3"><div class="spinner-border spinner-border-sm"></div></td></tr>';
    try {
      const r = await this.API('GET', 'finance/budgets');
      const payload = r?.data ?? r ?? {};
      this.state.items = Array.isArray(payload) ? payload : (payload.budgets || []);
      if (!this.state.items.length) { body.innerHTML = '<tr><td colspan="9" class="text-center py-4 text-muted">No budgets found.</td></tr>'; return; }
      body.innerHTML = this.state.items.map(b => {
        const budget = Number(b.total_amount || b.budget_amount || 0);
        const allocated = Number(b.total_allocated || 0);
        const committed = Number(b.total_committed || 0);
        const spent = Number(b.total_spent || 0);
        const pct = Number(b.utilization_pct ?? (budget > 0 ? ((spent / budget) * 100) : 0));
        const pctW = Math.min(100, Math.max(0, pct));
        const barColor = pct > 90 ? 'bg-danger' : (pct > 70 ? 'bg-warning' : 'bg-success');
        return `<tr>
          <td class="small fw-semibold">${this.esc(b.name || b.budget_name || '\u2014')}</td>
          <td class="small">${this.esc(b.academic_year || '\u2014')}</td>
          <td class="small text-muted">${this.esc(b.term || '\u2014')}</td>
          <td class="small">${this.fmtKES(budget)}</td>
          <td class="small">${this.fmtKES(allocated)}</td>
          <td class="small">${this.fmtKES(committed)}</td>
          <td class="small">${this.fmtKES(spent)}</td>
          <td>
            <div class="d-flex align-items-center gap-2">
              <div class="progress flex-grow-1" style="height:8px"><div class="progress-bar ${barColor}" style="width:${pctW}%"></div></div>
              <span class="small fw-semibold">${pct.toFixed(1)}%</span>
            </div>
          </td>
          <td><span class="badge ${this.statusBadge(b.status)}">${this.esc(b.status || '\u2014')}</span></td>
        </tr>`;
      }).join('');
    } catch (e) { body.innerHTML = `<tr><td colspan="9" class="text-center py-3 text-danger">${this.esc(e.message || 'Load failed')}</td></tr>`; }
  },

  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    await this.load();
  },
};

window.budgetActualsController = budgetActualsController;

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => budgetActualsController.init().catch(() => {}));
} else {
  budgetActualsController.init().catch(() => {});
}
