/**
 * food_consumption.js — Food Consumption Controller (standalone page).
 * Food usage and waste recorded per item across a date range.
 */
const foodConsumptionController = {
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
    const from = document.getElementById('fcFrom')?.value;
    const to = document.getElementById('fcTo')?.value;
    if (from) params.date_from = from;
    if (to) params.date_to = to;
    return params;
  },

  async load() {
    const body = document.getElementById('fcBody');
    body.innerHTML = '<tr><td colspan="6" class="text-center py-3"><div class="spinner-border spinner-border-sm"></div></td></tr>';
    try {
      const r = await this.API('GET', '/reports/food-consumption-trends', null, this.getParams());
      this.state.rows = this.norm(r);
      this.render();
    } catch (e) { body.innerHTML = `<tr><td colspan="6" class="text-center py-3 text-danger">${this.esc(e.message || 'Load failed')}</td></tr>`; }
  },

  render() {
    const rows = this.state.rows;
    const stats = document.getElementById('fcStats');
    const consumed = rows.reduce((s, r) => s + Number(r.total_consumed ?? 0), 0);
    const waste = rows.reduce((s, r) => s + Number(r.total_waste ?? 0), 0);
    const cost = rows.reduce((s, r) => s + Number(r.total_cost ?? 0), 0);
    stats.innerHTML = [
      `<div class="col-md-3"><div class="border rounded-3 p-3 bg-white"><div class="text-muted small">Records</div><div class="fs-4 fw-bold">${this.esc(rows.length)}</div></div></div>`,
      `<div class="col-md-3"><div class="border rounded-3 p-3 bg-white"><div class="text-muted small">Total Consumed</div><div class="fs-4 fw-bold">${this.esc(consumed.toLocaleString())}</div></div></div>`,
      `<div class="col-md-3"><div class="border rounded-3 p-3 bg-white"><div class="text-muted small">Total Waste</div><div class="fs-4 fw-bold text-warning">${this.esc(waste.toLocaleString())}</div></div></div>`,
      `<div class="col-md-3"><div class="border rounded-3 p-3 bg-white"><div class="text-muted small">Total Cost</div><div class="fs-4 fw-bold">KES ${this.esc(Math.round(cost).toLocaleString())}</div></div></div>`,
    ].join('');

    const body = document.getElementById('fcBody');
    if (!rows.length) { body.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-muted">No consumption records for the selected range.</td></tr>'; return; }
    body.innerHTML = rows.map(r => `<tr>
      <td class="small">${this.fmtDate(r.consumption_date)}</td>
      <td class="small fw-semibold">${this.esc(r.item_name || `#${r.inventory_item_id}`)}</td>
      <td class="small text-muted">${this.esc(r.unit || '\u2014')}</td>
      <td class="small">${this.esc(Number(r.total_consumed ?? 0).toLocaleString())}</td>
      <td class="small ${Number(r.total_waste ?? 0) > 0 ? 'text-warning fw-semibold' : 'text-muted'}">${this.esc(Number(r.total_waste ?? 0).toLocaleString())}</td>
      <td class="small fw-semibold">KES ${this.esc(Math.round(Number(r.total_cost ?? 0)).toLocaleString())}</td>
    </tr>`).join('');
  },

  setupEventListeners() {
    const d = this.defaults();
    document.getElementById('fcFrom').value = d.from;
    document.getElementById('fcTo').value = d.to;
    ['fcFrom', 'fcTo'].forEach(id => document.getElementById(id)?.addEventListener('change', () => this.load()));
  },

  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    this.setupEventListeners();
    await this.load();
  },
};

window.foodConsumptionController = foodConsumptionController;

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => foodConsumptionController.init().catch(() => {}));
} else {
  foodConsumptionController.init().catch(() => {});
}
