/**
 * create_activity.js — Create Activity Controller (standalone page).
 * Create a new co-curricular activity.
 */
const createActivityController = {
  state: { categories: [], recent: [] },

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

  norm(r) { return Array.isArray(r) ? r : (r?.data || r?.categories || r?.activities || r?.list || r || []); },

  badgeClass(s) {
    return { planned: 'bg-secondary', ongoing: 'bg-primary', completed: 'bg-success', cancelled: 'bg-danger' }[s] || 'bg-secondary';
  },

  async load() {
    try {
      const [catRes, actRes] = await Promise.all([
        this.API('GET', '/activities/categories/list').catch(() => []),
        this.API('GET', '/activities', null, { limit: 10 }).catch(() => []),
      ]);
      this.state.categories = this.norm(catRes);
      this.state.recent = this.norm(actRes);
      this.renderCategories();
      this.renderRecent();
    } catch (e) { /* handled by form render paths */ }
  },

  renderCategories() {
    const sel = document.getElementById('caCategory');
    const cats = this.state.categories;
    if (!cats.length) { sel.innerHTML = '<option value="">No categories available</option>'; return; }
    sel.innerHTML = cats.map(c => `<option value="${this.esc(c.id)}">${this.esc(c.name)}</option>`).join('');
  },

  renderRecent() {
    const body = document.getElementById('caRecent');
    if (!this.state.recent.length) { body.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-muted">No activities yet.</td></tr>'; return; }
    body.innerHTML = this.state.recent.map(a => `<tr>
      <td class="small fw-semibold">${this.esc(a.title || '\u2014')}</td>
      <td class="small">${this.esc(a.category_name || '\u2014')}</td>
      <td class="small">${this.esc(a.start_date || '\u2014')}</td>
      <td class="small">${this.esc(a.end_date || '\u2014')}</td>
      <td class="small text-center">${this.esc(a.active_participants ?? a.participant_count ?? 0)}</td>
      <td><span class="badge ${this.badgeClass(a.status)}">${this.esc(a.status || '\u2014')}</span></td>
    </tr>`).join('');
  },

  async save(event) {
    event.preventDefault();
    const btn = document.getElementById('caBtn');
    const payload = {
      title: document.getElementById('caTitle').value.trim(),
      category_id: parseInt(document.getElementById('caCategory').value, 10),
      start_date: document.getElementById('caStart').value,
      end_date: document.getElementById('caEnd').value,
      max_participants: parseInt(document.getElementById('caMax').value, 10) || undefined,
      status: document.getElementById('caStatus').value,
      description: document.getElementById('caDesc').value.trim() || undefined,
    };
    if (!payload.title) { this.notify('Title is required.', 'danger'); return; }
    if (!payload.category_id) { this.notify('Category is required.', 'danger'); return; }
    if (!payload.start_date || !payload.end_date) { this.notify('Start and end dates are required.', 'danger'); return; }
    if (new Date(payload.end_date) < new Date(payload.start_date)) { this.notify('End date must be after start date.', 'danger'); return; }

    btn.disabled = true;
    try {
      const r = await this.API('POST', '/activities', payload);
      this.notify(r?.message || 'Activity created successfully.', 'success');
      event.target.reset();
      await this.load();
    } catch (e) { this.notify(e.message || 'Failed to create activity.', 'danger'); }
    finally { btn.disabled = false; }
  },

  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    await this.load();
  },
};

window.createActivityController = createActivityController;

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => createActivityController.init().catch(() => {}));
} else {
  createActivityController.init().catch(() => {});
}
