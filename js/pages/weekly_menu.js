/**
 * weekly_menu.js — Weekly Menu Controller (standalone page).
 * Shows the meals planned for each day of the school week (Mon–Fri).
 */
const weeklyMenuController = {
  API: (method, endpoint, data, params, opts) => window.callAPI(endpoint, method, data, params, opts),

  esc(s) { return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); },

  norm(r) { return Array.isArray(r) ? r : (r?.data || r?.items || r || []); },

  today() { return new Date().toISOString().slice(0, 10); },

  toISO(dt) { return `${dt.getFullYear()}-${String(dt.getMonth() + 1).padStart(2, '0')}-${String(dt.getDate()).padStart(2, '0')}`; },

  weekDates() {
    const picker = document.getElementById('wmPicker');
    const ref = picker?.value ? new Date(picker.value + 'T00:00:00') : new Date();
    const day = (ref.getDay() + 6) % 7;
    const monday = new Date(ref);
    monday.setDate(ref.getDate() - day);
    const days = [];
    for (let i = 0; i < 5; i++) {
      const d = new Date(monday);
      d.setDate(monday.getDate() + i);
      days.push(this.toISO(d));
    }
    return days;
  },

  badgeClass(s) {
    return { served: 'bg-success', prepared: 'bg-info text-dark', planned: 'bg-warning text-dark', cancelled: 'bg-secondary' }[s] || 'bg-secondary';
  },

  mealIcon(m) { return { breakfast: 'bi-sunrise', snack: 'bi-cup-hot', lunch: 'bi-egg-fried', dinner: 'bi-moon-stars' }[m] || 'bi-egg-fried'; },

  async load() {
    const container = document.getElementById('wmDays');
    container.innerHTML = '<div class="text-center py-5 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Loading week…</div>';
    const days = this.weekDates();

    try {
      const results = await Promise.all(days.map(date => this.API('GET', '/catering/menu', null, { date }).catch(() => [])));
      const dayNames = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];

      const html = results.map((r, i) => {
        const meals = this.norm(r);
        const dateLabel = new Date(days[i] + 'T00:00:00').toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
        const order = ['breakfast', 'snack', 'lunch', 'dinner'];
        const grouped = {};
        meals.forEach(m => {
          const key = (m.meal_type || 'other').toLowerCase();
          if (!grouped[key]) grouped[key] = [];
          grouped[key].push(m);
        });
        const keys = Object.keys(grouped).sort((a, b) => (order.indexOf(a) === -1 ? 99 : order.indexOf(a)) - (order.indexOf(b) === -1 ? 99 : order.indexOf(b)));

        const mealHtml = meals.length
          ? keys.map(key => `<div class="mb-2">
              <div class="fw-semibold small text-capitalize"><i class="bi ${this.mealIcon(key)} me-1 text-success"></i>${this.esc(key)}</div>
              <ul class="list-unstyled small text-muted mb-2">
                ${grouped[key].map(m => `<li class="d-flex justify-content-between gap-2"><span>${this.esc(m.menu_item || '\u2014')}</span><span class="badge ${this.badgeClass(m.status)}">${this.esc(m.status || 'planned')}</span></li>`).join('')}
              </ul>
            </div>`).join('')
          : '<div class="text-muted small py-2">No meals planned.</div>';

        return `<div class="col-xl"><div class="bg-white border rounded-3 p-3 h-100">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <h6 class="fw-semibold mb-0">${dayNames[i]}</h6>
            <span class="text-muted small">${dateLabel}</span>
          </div>
          <div class="border-top pt-2">${mealHtml}</div>
        </div></div>`;
      }).join('');

      container.innerHTML = `<div class="row g-3">${html}</div>`;
    } catch (e) {
      container.innerHTML = `<div class="text-center py-5 text-danger">${this.esc(e.message || 'Load failed')}</div>`;
    }
  },

  setupEventListeners() {
    document.getElementById('wmPicker').value = this.today();
    document.getElementById('wmPicker').addEventListener('change', () => this.load());
  },

  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    this.setupEventListeners();
    await this.load();
  },
};

window.weeklyMenuController = weeklyMenuController;

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => weeklyMenuController.init().catch(() => {}));
} else {
  weeklyMenuController.init().catch(() => {});
}
