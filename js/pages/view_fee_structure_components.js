/**
 * view_fee_structure_components.js — Fee Components Controller (standalone page).
 * Full CRUD for fee types/components used in fee structures.
 */
const viewFeeStructureComponentsController = {
  state: { items: [], editingId: null },

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

  async load() {
    const body = document.getElementById('fscBody');
    body.innerHTML = '<tr><td colspan="6" class="text-center py-3"><div class="spinner-border spinner-border-sm"></div></td></tr>';
    try {
      const r = await this.API('GET', 'finance/fee-types-list');
      this.state.items = Array.isArray(r) ? r : (r?.items || r?.data || []);
      if (!this.state.items.length) { body.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-muted">No fee components found.</td></tr>'; return; }
      body.innerHTML = this.state.items.map(i => `
        <tr>
          <td class="small"><span class="badge bg-light text-dark border">${this.esc(i.code || '\u2014')}</span></td>
          <td class="small fw-semibold">${this.esc(i.name || '\u2014')}</td>
          <td class="small text-muted">${this.esc(i.category || '\u2014')}</td>
          <td class="small">${i.is_mandatory ? '<span class="badge bg-primary">Mandatory</span>' : '<span class="badge bg-secondary">Optional</span>'}</td>
          <td class="small text-muted">${this.esc(i.description || '\u2014')}</td>
          <td>
            <div class="btn-group btn-group-sm">
              <button class="btn btn-outline-primary" onclick="viewFeeStructureComponentsController.openEditModal(${i.id})" title="Edit">
                <i class="bi bi-pencil"></i>
              </button>
              <button class="btn btn-outline-${i.status === 'active' ? 'danger' : 'success'}" onclick="viewFeeStructureComponentsController.toggleStatus(${i.id})" title="${i.status === 'active' ? 'Deactivate' : 'Activate'}">
                <i class="bi bi-${i.status === 'active' ? 'archive' : 'check-circle'}"></i>
              </button>
            </div>
          </td>
        </tr>`).join('');
    } catch (e) { body.innerHTML = `<tr><td colspan="6" class="text-center py-3 text-danger">${this.esc(e.message || 'Load failed')}</td></tr>`; }
  },

  openCreateModal() {
    this.state.editingId = null;
    document.getElementById('fscModalTitle').textContent = 'Add Fee Component';
    document.getElementById('fscEditId').value = '';
    document.getElementById('fscCode').value = '';
    document.getElementById('fscCode').disabled = false;
    document.getElementById('fscName').value = '';
    document.getElementById('fscCategory').value = 'tuition';
    document.getElementById('fscDescription').value = '';
    document.getElementById('fscMandatory').value = '1';
    new bootstrap.Modal(document.getElementById('fscModal')).show();
  },

  openEditModal(id) {
    const item = this.state.items.find(i => i.id === id);
    if (!item) return this.notify('Fee component not found', 'danger');
    this.state.editingId = id;
    document.getElementById('fscModalTitle').textContent = 'Edit Fee Component';
    document.getElementById('fscEditId').value = id;
    document.getElementById('fscCode').value = item.code || '';
    document.getElementById('fscCode').disabled = true;
    document.getElementById('fscName').value = item.name || '';
    document.getElementById('fscCategory').value = item.category || 'tuition';
    document.getElementById('fscDescription').value = item.description || '';
    document.getElementById('fscMandatory').value = item.is_mandatory ? '1' : '0';
    new bootstrap.Modal(document.getElementById('fscModal')).show();
  },

  async save() {
    const code = document.getElementById('fscCode').value.trim();
    const name = document.getElementById('fscName').value.trim();
    const category = document.getElementById('fscCategory').value;
    const description = document.getElementById('fscDescription').value.trim();
    const is_mandatory = parseInt(document.getElementById('fscMandatory').value, 10);
    if (!code) return this.notify('Code is required', 'warning');
    if (!name) return this.notify('Name is required', 'warning');
    const payload = { code, name, category, description: description || null, is_mandatory };
    try {
      if (this.state.editingId) {
        await this.API('PUT', `finance/fee-types/${this.state.editingId}`, payload);
        this.notify('Fee component updated');
      } else {
        await this.API('POST', 'finance/fee-types', payload);
        this.notify('Fee component created');
      }
      bootstrap.Modal.getInstance(document.getElementById('fscModal'))?.hide();
      await this.load();
    } catch (e) { this.notify(e.message || 'Save failed', 'danger'); }
  },

  async toggleStatus(id) {
    const item = this.state.items.find(i => i.id === id);
    if (!item) return;
    const action = item.status === 'active' ? 'deactivate' : 'activate';
    if (!confirm(`${action === 'activate' ? 'Activate' : 'Deactivate'} "${item.name}"?`)) return;
    try {
      await this.API('PATCH', `finance/fee-types/${id}`);
      this.notify(`Fee component ${action}d`);
      await this.load();
    } catch (e) { this.notify(e.message || 'Status change failed', 'danger'); }
  },

  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    await this.load();
  },
};

window.viewFeeStructureComponentsController = viewFeeStructureComponentsController;

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => viewFeeStructureComponentsController.init().catch(() => {}));
} else {
  viewFeeStructureComponentsController.init().catch(() => {});
}
