/**
 * internal_products_catalog.js — Internal Products Catalog Controller.
 * Injecting page: pages/internal_products_catalog.php (route: internal_products_catalog).
 *
 * View-only for all authenticated staff; sell + management actions require an
 * inventory write permission (mirrors InventoryController::guardInventoryWrite:
 * inventory.manage / inventory.admin / system administrator).
 */
const internalProductsCatalogController = {
  initialized: false,
  state: {
    products: [],
    items: [],
    students: [],
    allStudents: [],
    filters: { q: '', status: 'all' },
    canManage: false,
    sellProduct: null,
  },

  esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  },

  notify(message, type = 'success') {
    if (typeof showNotification === 'function') {
      showNotification(message, type);
    } else {
      window.alert(message);
    }
  },

  canManageInventory() {
    return Boolean(
      window.API?.hasPermission?.('inventory_manage') ||
      window.API?.hasPermission?.('inventory.manage') ||
      window.API?.hasPermission?.('inventory.admin') ||
      window.API?.hasRole?.('system administrator'),
    );
  },

  normUnits(r) {
    const d = r?.data && r.data.data ? r.data : (r ?? {});
    const arr = d?.items ?? d?.students ?? d?.data ?? (Array.isArray(d) ? d : []);
    return Array.isArray(arr) ? arr : [];
  },

  async init() {
    if (this.initialized) return;
    this.initialized = true;
    if (window.AuthContext?.ready) await window.AuthContext.ready();

    this.state.canManage = this.canManageInventory();
    document.querySelectorAll('.ipc-manage-only').forEach((el) => {
      el.classList.toggle('d-none', !this.state.canManage);
    });

    this.setupEventListeners();

    if (this.state.canManage) {
      this.loadItems();
      this.loadStudents();
    }
    await this.loadData();
  },

  setupEventListeners() {
    const $ = (id) => document.getElementById(id);
    $('ipcRefreshBtn').addEventListener('click', () => this.loadData());
    $('ipcSearch').addEventListener('input', (e) => {
      this.state.filters.q = e.target.value.trim();
      this.render();
    });
    $('ipcStatusFilter').addEventListener('change', (e) => {
      this.state.filters.status = e.target.value;
      this.render();
    });
    $('ipcAddProductBtn').addEventListener('click', () => this.openEdit(null));
    $('ipcProductForm').addEventListener('submit', (e) => {
      e.preventDefault();
      this.submitProduct();
    });
    $('ipcSellForm').addEventListener('submit', (e) => {
      e.preventDefault();
      this.submitSell();
    });
    $('ipcSellStudentSearch').addEventListener('input', (e) => {
      this.filterStudents(e.target.value.trim().toLowerCase());
    });
    $('ipcImageForm').addEventListener('submit', (e) => {
      e.preventDefault();
      this.submitImage();
    });
  },

  async loadData() {
    const body = document.getElementById('ipcProductsBody');
    body.innerHTML = '<tr><td colspan="6" class="text-center py-5 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Loading…</td></tr>';
    try {
      const d = await window.API.inventory.getUniformCatalog();
      this.state.products = d?.products || [];
    } catch (err) {
      this.state.products = [];
      body.innerHTML = `<tr><td colspan="6" class="text-center py-5 text-danger">${this.esc(err?.message || 'Unable to load the catalogue.')}</td></tr>`;
      this.notify('Unable to load the catalogue.', 'danger');
      return;
    }
    this.render();
  },

  async loadItems() {
    try {
      const r = await window.API.inventory.list({ limit: 500 });
      this.state.items = this.normUnits(r);
    } catch (err) {
      this.state.items = [];
    }
  },

  async loadStudents() {
    try {
      const r = await window.API.students.get();
      const arr = Array.isArray(r) ? r : (r?.students ?? r?.data ?? []);
      this.state.allStudents = Array.isArray(arr)
        ? arr.filter((s) => typeof s === 'object' && s !== null)
        : [];
      this.filterStudents('');
    } catch (err) {
      this.state.allStudents = [];
      const sel = document.getElementById('ipcSellStudentLarge');
      sel.innerHTML = '<option value="">Unable to load learners</option>';
    }
  },

  filterStudents(q) {
    const filtered = this.state.allStudents.filter((s) => {
      if (!q) return true;
      const name = `${s.first_name || ''} ${s.last_name || ''} ${s.admission_number || ''} ${s.admission_no || ''}`;
      return name.toLowerCase().includes(q);
    });
    this.state.students = filtered;
    const sel = document.getElementById('ipcSellStudentLarge');
    sel.innerHTML = filtered.length
      ? filtered.map((s) => `<option value="${Number(s.id)}">${this.esc(s.first_name || '')} ${this.esc(s.last_name || '')} (${this.esc(s.admission_number || 'N/A')})</option>`).join('')
      : '<option value="">No matching learners</option>';
  },

  render() {
    const { q, status } = this.state.filters;
    const rows = this.state.products.filter((p) => {
      if (status !== 'all' && p.status !== status) return false;
      if (q) {
        const hay = `${p.title || ''} ${p.code || ''} ${p.description || ''}`.toLowerCase();
        if (!hay.includes(q)) return false;
      }
      return true;
    });

    const live = this.state.products.filter((p) => p.status === 'active' && Number(p.published) === 1).length;
    const draft = this.state.products.filter((p) => p.status !== 'archived' && Number(p.published) !== 1).length;
    const archived = this.state.products.filter((p) => p.status === 'archived').length;
    document.getElementById('ipcStatTotal').textContent = this.state.products.length;
    document.getElementById('ipcStatTotalSub').textContent = `${this.state.products.length - archived} catalogued`;
    document.getElementById('ipcStatLive').textContent = live;
    document.getElementById('ipcStatDraft').textContent = draft;
    document.getElementById('ipcStatArchived').textContent = archived;
    document.getElementById('ipcCount').textContent = `${rows.length} item${rows.length === 1 ? '' : 's'} shown`;

    const manage = this.state.canManage ? '' : 'd-none';
    const fallback = `${window.APP_BASE || ''}/images/official_school_logo.png`;
    document.getElementById('ipcProductsBody').innerHTML = rows.map((p) => {
      const image = p.image_url || fallback;
      const statusBadge = {
        active: 'bg-success', draft: 'bg-warning text-dark', archived: 'bg-secondary',
      }[p.status] || 'bg-secondary';
      const sizeCells = (p.sizes || []).length
        ? p.sizes.map((s) =>
            `<span class="badge text-bg-light border me-1 mb-1" title="${this.esc(s.size)}">` +
            `${this.esc(s.size_label || s.size)} · ${Number(s.available || 0)} · KES ${Number(s.unit_price || 0).toLocaleString()}` +
            `</span>`).join('')
        : '<span class="text-muted small">—</span>';
      return `<tr>
        <td>
          <div class="d-flex align-items-center gap-3">
            <img src="${this.esc(image)}" alt="${this.esc(p.title)}" onerror="this.src='${fallback}'" style="width:52px;height:52px;object-fit:cover;border-radius:.5rem;">
            <div>
              <div class="fw-semibold">${this.esc(p.title)}</div>
              <div class="text-muted small text-truncate" style="max-width:280px">${this.esc(p.description || '')}</div>
            </div>
          </div>
        </td>
        <td><code class="small">${this.esc(p.code || '')}</code></td>
        <td><span class="badge ${statusBadge}">${this.esc(p.status || '—')}</span></td>
        <td>${Number(p.published) === 1 ? '<span class="badge bg-success">Published</span>' : '<span class="badge bg-secondary">Hidden</span>'}</td>
        <td>${sizeCells}</td>
        <td class="text-end ${manage}">
          <div class="d-inline-flex gap-1 flex-wrap justify-content-end">
            <button class="btn btn-sm btn-success" data-sell="${p.id}"><i class="bi bi-cart-plus me-1"></i>Sell</button>
            <button class="btn btn-sm btn-outline-primary" data-edit="${p.id}"><i class="bi bi-pencil me-1"></i>Edit</button>
            <button class="btn btn-sm btn-outline-secondary" data-image="${p.id}" title="Upload product image"><i class="bi bi-image"></i></button>
            <button class="btn btn-sm ${Number(p.published) === 1 ? 'btn-outline-warning' : 'btn-outline-success'}" data-pub="${p.id}">
              ${Number(p.published) === 1 ? '<i class="bi bi-eye-slash me-1"></i>Unpublish' : '<i class="bi bi-eye me-1"></i>Publish'}
            </button>
          </div>
        </td>
      </tr>`;
    }).join('') || '<tr><td colspan="6" class="text-center py-5 text-muted"><i class="bi bi-search fs-2 d-block mb-2"></i>No matching products.</td></tr>';

    if (!this.state.canManage) return;
    document.querySelectorAll('[data-sell]').forEach((btn) => {
      const p = this.state.products.find((x) => Number(x.id) === Number(btn.dataset.sell));
      btn.addEventListener('click', () => p && this.openSell(p));
    });
    document.querySelectorAll('[data-edit]').forEach((btn) => {
      const p = this.state.products.find((x) => Number(x.id) === Number(btn.dataset.edit));
      btn.addEventListener('click', () => p && this.openEdit(p));
    });
    document.querySelectorAll('[data-image]').forEach((btn) => {
      const p = this.state.products.find((x) => Number(x.id) === Number(btn.dataset.image));
      btn.addEventListener('click', () => p && this.openImage(p));
    });
    document.querySelectorAll('[data-pub]').forEach((btn) => {
      const p = this.state.products.find((x) => Number(x.id) === Number(btn.dataset.pub));
      btn.addEventListener('click', () => p && this.togglePublish(p));
    });
  },

  openSell(product) {
    if (!this.state.canManage) return;
    this.state.sellProduct = product;
    document.getElementById('ipcSellProductInfo').innerHTML =
      `<strong>${this.esc(product.title)}</strong> <span class="text-muted">· SKU ${this.esc(product.code || '')}</span>`;
    document.getElementById('ipcSellProductId').value = product.id;
    document.getElementById('ipcSellQty').value = 1;
    const sizeSel = document.getElementById('ipcSellSize');
    const sizes = product.sizes || [];
    sizeSel.innerHTML = sizes.length
      ? '<option value="">Select size…</option>' + sizes.map((s) => {
          const available = Number(s.available || 0);
          return `<option value="${Number(s.size_id)}" ${available < 1 ? 'disabled' : ''}>` +
            `${this.esc(s.size_label || s.size)} · KES ${Number(s.unit_price || 0).toLocaleString()} (${available} available)` +
            `</option>`;
        }).join('')
      : '<option value="">No sizes in stock</option>';
    if (!this.state.students.length && this.state.allStudents.length) this.filterStudents('');
    bootstrap.Modal.getOrCreateInstance(document.getElementById('ipcSellModal')).show();
  },

  async submitSell() {
    const productId = Number(document.getElementById('ipcSellProductId').value);
    const studentId = Number(document.getElementById('ipcSellStudentLarge').value);
    const sizeId = Number(document.getElementById('ipcSellSize').value);
    const quantity = Number(document.getElementById('ipcSellQty').value);
    if (!studentId || !sizeId || quantity < 1) {
      this.notify('Select a learner, size and a positive quantity.', 'danger');
      return;
    }
    const btn = document.getElementById('ipcSellSaveBtn');
    btn.disabled = true;
    try {
      await window.API.inventory.createUniformCatalogPurchase({
        student_id: studentId,
        product_id: productId,
        size_id: sizeId,
        quantity,
      });
      bootstrap.Modal.getInstance(document.getElementById('ipcSellModal')).hide();
      this.notify('Purchase created. Collect payment in the Uniform Sales workflow.');
      this.loadData();
    } catch (err) {
      this.notify(err?.message || 'Unable to create the purchase.', 'danger');
    } finally {
      btn.disabled = false;
    }
  },

  openEdit(product) {
    if (!this.state.canManage) return;
    const usedItems = new Set(
      this.state.products.map((p) => Number(p.item_id)).filter(Boolean),
    );
    const currentItemId = product ? Number(product.item_id) : 0;
    if (currentItemId) usedItems.delete(currentItemId);
    const itemSel = document.getElementById('ipcProductItem');
    const available = this.state.items.filter((i) =>
      currentItemId === Number(i.id) || !usedItems.has(Number(i.id)));
    itemSel.innerHTML = available.length
      ? available.map((i) =>
          `<option value="${Number(i.id)}" ${currentItemId === Number(i.id) ? 'selected' : ''}>` +
          `${this.esc(i.item_name || i.name)} (${this.esc(i.code || '')})</option>`).join('')
      : '<option value="">No remaining inventory items</option>';

    document.getElementById('ipcProductExistingId').value = product ? product.id : 0;
    document.getElementById('ipcProductTitle').value = product ? (product.title || '') : '';
    document.getElementById('ipcProductDesc').value = product ? (product.description || '') : '';
    document.getElementById('ipcProductStatus').value = product ? (product.status || 'draft') : 'draft';
    document.getElementById('ipcProductPublished').checked = product ? Number(product.published) === 1 : false;
    document.getElementById('ipcProductModalTitle').textContent = product ? 'Edit Product' : 'Add Product';
    bootstrap.Modal.getOrCreateInstance(document.getElementById('ipcProductModal')).show();
  },

  async submitProduct() {
    const itemId = Number(document.getElementById('ipcProductItem').value);
    const title = document.getElementById('ipcProductTitle').value.trim();
    if (!itemId || !title) {
      this.notify('Select an inventory item and provide a title.', 'danger');
      return;
    }
    const btn = document.getElementById('ipcProductSaveBtn');
    btn.disabled = true;
    try {
      await window.API.inventory.saveUniformCatalogProduct({
        item_id: itemId,
        title,
        description: document.getElementById('ipcProductDesc').value.trim(),
        status: document.getElementById('ipcProductStatus').value,
        published: document.getElementById('ipcProductPublished').checked ? 1 : 0,
      });
      bootstrap.Modal.getInstance(document.getElementById('ipcProductModal')).hide();
      this.notify('Catalogue product saved.');
      this.loadData();
    } catch (err) {
      this.notify(err?.message || 'Unable to save the product.', 'danger');
    } finally {
      btn.disabled = false;
    }
  },

  async togglePublish(product) {
    const next = Number(product.published) === 1 ? 0 : 1;
    try {
      await window.API.inventory.saveUniformCatalogProduct({
        item_id: Number(product.item_id),
        slug: product.slug,
        title: product.title,
        description: product.description,
        status: product.status || 'active',
        published: next,
      });
      this.notify(next ? 'Product published to buyers.' : 'Product unpublished.');
      this.loadData();
    } catch (err) {
      this.notify(err?.message || 'Unable to update publication state.', 'danger');
    }
  },

  openImage(product) {
    if (!this.state.canManage) return;
    document.getElementById('ipcImageProductId').value = product.id;
    document.getElementById('ipcImageProductInfo').textContent = product.title;
    document.getElementById('ipcImageFile').value = '';
    document.getElementById('ipcImageAlt').value = '';
    document.getElementById('ipcImagePrimary').checked = true;
    bootstrap.Modal.getOrCreateInstance(document.getElementById('ipcImageModal')).show();
  },

  async submitImage() {
    const productId = Number(document.getElementById('ipcImageProductId').value);
    const file = document.getElementById('ipcImageFile').files[0];
    if (!productId || !file) {
      this.notify('Choose an image file to upload.', 'danger');
      return;
    }
    const btn = document.getElementById('ipcImageSaveBtn');
    btn.disabled = true;
    try {
      const fd = new FormData();
      fd.append('file', file);
      fd.append('product_id', String(productId));
      fd.append('alt_text', document.getElementById('ipcImageAlt').value.trim());
      fd.append('is_primary', document.getElementById('ipcImagePrimary').checked ? '1' : '0');
      await window.API.inventory.uploadUniformCatalogImage(fd);
      bootstrap.Modal.getInstance(document.getElementById('ipcImageModal')).hide();
      this.notify('Product image uploaded.');
      this.loadData();
    } catch (err) {
      this.notify(err?.message || 'Unable to upload the image.', 'danger');
    } finally {
      btn.disabled = false;
    }
  },
};

window.internalProductsCatalogController = internalProductsCatalogController;

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => internalProductsCatalogController.init().catch(() => {}));
} else {
  internalProductsCatalogController.init().catch(() => {});
}