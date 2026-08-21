/**
 * todays_menu.js — Today's Menu Controller (standalone page).
 * Shows the meals planned for the selected date.
 */
const todaysMenuController = {
  API: (method, endpoint, data, params, opts) => window.callAPI(endpoint, method, data, params, opts),

  esc(s) { return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); },

  norm(r) { return Array.isArray(r) ? r : (r?.data || r?.items || r?.meals || r || []); },

  today() { return new Date().toISOString().slice(0, 10); },

  badgeClass(s) {
    return { served: 'bg-success', prepared: 'bg-info text-dark', planned: 'bg-warning text-dark', cancelled: 'bg-secondary' }[s] || 'bg-secondary';
  },

  mealIcon(m) { return { breakfast: 'bi-sunrise', snack: 'bi-cup-hot', lunch: 'bi-egg-fried', dinner: 'bi-moon-stars' }[m] || 'bi-egg-fried'; },

  async load() {
    const picker = document.getElementById('tmPicker');
    const date = picker?.value || this.today();
    document.getElementById('tmDate').textContent = `Meals planned for ${new Date(date + 'T00:00:00').toLocaleDateString(undefined, { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })}.`;

    document.getElementById('tmStats').innerHTML = '';
    document.getElementById('tmMeals').innerHTML = '<div class="text-center py-5 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Loading…</div>';

    try {
      const [menuRes, statsRes] = await Promise.all([
        this.API('GET', '/catering/menu', null, { date }).catch(() => []),
        this.API('GET', '/catering/stats', null, { date }).catch(() => null),
      ]);

      const meals = this.norm(menuRes);
      const stats = statsRes?.data ?? statsRes ?? {};

      document.getElementById('tmStats').innerHTML = [
        `<div class="col-md-3"><div class="border rounded-3 p-3 bg-white"><div class="text-muted small">Meals Planned</div><div class="fs-4 fw-bold">${this.esc(stats.meals_planned ?? meals.length)}</div></div></div>`,
        `<div class="col-md-3"><div class="border rounded-3 p-3 bg-white"><div class="text-muted small">Planned Servings</div><div class="fs-4 fw-bold">${this.esc(stats.planned_servings ?? 0)}</div></div></div>`,
        `<div class="col-md-3"><div class="border rounded-3 p-3 bg-white"><div class="text-muted small">Actual Servings</div><div class="fs-4 fw-bold">${this.esc(stats.actual_servings ?? 0)}</div></div></div>`,
        `<div class="col-md-3"><div class="border rounded-3 p-3 bg-white"><div class="text-muted small">Daily Cost</div><div class="fs-4 fw-bold">KES ${this.esc(Math.round(Number(stats.daily_cost ?? 0)).toLocaleString())}</div></div></div>`,
      ].join('');

      if (!meals.length) { document.getElementById('tmMeals').innerHTML = '<div class="text-center py-5 text-muted">No meals planned for this date.</div>'; return; }

      const order = ['breakfast', 'snack', 'lunch', 'dinner'];
      const grouped = {};
      meals.forEach(m => {
        const key = (m.meal_type || 'other').toLowerCase();
        if (!grouped[key]) grouped[key] = [];
        grouped[key].push(m);
      });

      const keys = Object.keys(grouped).sort((a, b) => (order.indexOf(a) === -1 ? 99 : order.indexOf(a)) - (order.indexOf(b) === -1 ? 99 : order.indexOf(b)));
      document.getElementById('tmMeals').innerHTML = keys.map(key => `
        <div class="bg-white border rounded-3 overflow-hidden mb-3">
          <div class="p-3 border-bottom d-flex justify-content-between align-items-center">
            <h6 class="fw-semibold mb-0 text-capitalize"><i class="bi ${this.mealIcon(key)} me-2"></i>${this.esc(key)}</h6>
            <span class="badge bg-light text-dark">${this.esc(grouped[key].length)} item(s)</span>
          </div>
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead class="table-light"><tr><th>Item</th><th>Description</th><th>Planned</th><th>Prepared</th><th>Served</th><th>Status</th></tr></thead>
              <tbody>${grouped[key].map(m => `<tr>
                <td class="small fw-semibold">${this.esc(m.menu_item || '\u2014')}</td>
                <td class="small text-muted">${this.esc(m.description || '\u2014')}</td>
                <td class="small">${this.esc(m.planned_servings ?? 0)}</td>
                <td class="small">${this.esc(m.prepared_quantity ?? 0)}</td>
                <td class="small">${this.esc(m.actual_servings ?? 0)}</td>
                <td><span class="badge ${this.badgeClass(m.status)}">${this.esc(m.status || 'planned')}</span></td>
              </tr>`).join('')}</tbody>
            </table>
          </div>
        </div>`).join('');
    } catch (e) {
      document.getElementById('tmMeals').innerHTML = `<div class="text-center py-5 text-danger">${this.esc(e.message || 'Load failed')}</div>`;
    }
  },

  setupEventListeners() {
    document.getElementById('tmPicker').value = this.today();
    document.getElementById('tmPicker').addEventListener('change', () => this.load());
  },

  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    this.setupEventListeners();
    await this.load();
  },
};

window.todaysMenuController = todaysMenuController;

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => todaysMenuController.init().catch(() => {}));
} else {
  todaysMenuController.init().catch(() => {});
}
