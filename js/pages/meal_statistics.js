/**
 * meal_statistics.js — Meal Statistics Controller (standalone page).
 * Meal allocations: planned vs served across a date range.
 */
const mealStatisticsController = {
  state: { rows: [] },

  API: (method, endpoint, data, params, opts) => window.callAPI(endpoint, method, data, params, opts),

  esc(s) { return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); },

  norm(r) { return Array.isArray(r) ? r : (r?.data || r?.rows || r || []); },

  fmtDate(v) {
    if (!v) return '\u2014';
    const dt = new Date(String(v).replace(' ', 'T'));
    if (isNaN(dt)) return this.esc(v);
    return dt.toLocaleDateString();
  },

  defaults() {
    const to = new Date();
    const from = new Date();
    from.setDate(to.getDate() - 30);
    return { from: from.toISOString().slice(0, 10), to: to.toISOString().slice(0, 10) };
  },

  getParams() {
    const params = {};
    const from = document.getElementById('msFrom')?.value;
    const to = document.getElementById('msTo')?.value;
    if (from) params.date_from = from;
    if (to) params.date_to = to;
    return params;
  },

  async load() {
    const body = document.getElementById('msBody');
    body.innerHTML = '<tr><td colspan="6" class="text-center py-3"><div class="spinner-border spinner-border-sm"></div></td></tr>';
    try {
      const r = await this.API('GET', '/reports/meal-allocations', null, this.getParams());
      this.state.rows = this.norm(r);
      this.render();
    } catch (e) { body.innerHTML = `<tr><td colspan="6" class="text-center py-3 text-danger">${this.esc(e.message || 'Load failed')}</td></tr>`; }
  },

  render() {
    const rows = this.state.rows;
    const stats = document.getElementById('msStats');
    const days = new Set(rows.map(r => r.meal_date)).size;
    const allocated = rows.reduce((s, r) => s + Number(r.allocated_count ?? 0), 0);
    const served = rows.reduce((s, r) => s + Number(r.served_count ?? 0), 0);
    stats.innerHTML = [
      `<div class="col-md-3"><div class="border rounded-3 p-3 bg-white"><div class="text-muted small">Days Covered</div><div class="fs-4 fw-bold">${this.esc(days)}</div></div></div>`,
      `<div class="col-md-3"><div class="border rounded-3 p-3 bg-white"><div class="text-muted small">Meal Slots</div><div class="fs-4 fw-bold">${this.esc(rows.length)}</div></div></div>`,
      `<div class="col-md-3"><div class="border rounded-3 p-3 bg-white"><div class="text-muted small">Allocated Servings</div><div class="fs-4 fw-bold">${this.esc(allocated.toLocaleString())}</div></div></div>`,
      `<div class="col-md-3"><div class="border rounded-3 p-3 bg-white"><div class="text-muted small">Served</div><div class="fs-4 fw-bold text-success">${this.esc(served.toLocaleString())}${allocated ? ` (${Math.round((served / allocated) * 100)}%)` : ''}</div></div></div>`,
    ].join('');

    const body = document.getElementById('msBody');
    if (!rows.length) { body.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-muted">No meal data for the selected range.</td></tr>'; return; }
    body.innerHTML = rows.map(r => {
      const alloc = Number(r.allocated_count ?? 0);
      const servedN = Number(r.served_count ?? 0);
      const rate = alloc ? Math.round((servedN / alloc) * 100) : 0;
      return `<tr>
        <td class="small">${this.fmtDate(r.meal_date)}</td>
        <td class="small fw-semibold text-capitalize">${this.esc(r.meal_type || '\u2014')}</td>
        <td class="small">${this.esc(r.planned_items ?? 0)}</td>
        <td class="small">${this.esc(alloc)}</td>
        <td class="small fw-semibold">${this.esc(servedN)}</td>
        <td><div class="d-flex align-items-center gap-2"><div class="progress flex-grow-1" style="height: 8px;"><div class="progress-bar ${rate >= 90 ? 'bg-success' : rate >= 60 ? 'bg-warning' : 'bg-danger'}" style="width:${this.esc(rate)}%"></div></div><small>${this.esc(rate)}%</small></div></td>
      </tr>`;
    }).join('');
  },

  setupEventListeners() {
    const d = this.defaults();
    document.getElementById('msFrom').value = d.from;
    document.getElementById('msTo').value = d.to;
    ['msFrom', 'msTo'].forEach(id => document.getElementById(id)?.addEventListener('change', () => this.load()));
  },

  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    this.setupEventListeners();
    await this.load();
  },
};

window.mealStatisticsController = mealStatisticsController;

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => mealStatisticsController.init().catch(() => {}));
} else {
  mealStatisticsController.init().catch(() => {});
}
