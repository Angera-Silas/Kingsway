/**
 * manage_school_gallery.js — School Gallery Controller (standalone page).
 * Manage images displayed in the public website gallery.
 */
const manageSchoolGalleryController = {
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

  async loadData() {
    const grid = document.getElementById('galleryGrid');
    grid.innerHTML = '<div class="text-muted small p-3"><div class="spinner-border spinner-border-sm me-2"></div>Loading\u2026</div>';
    try {
      const r = await this.API('GET', 'website/gallery');
      this.state.items = r?.data?.items || [];
      this.render();
    } catch (e) {
      grid.innerHTML = `<div class="text-danger small p-3">${this.esc(e.message || 'Load failed')}</div>`;
    }
  },

  render() {
    const grid = document.getElementById('galleryGrid');
    document.getElementById('statImages').textContent = this.state.items.length;
    if (!this.state.items.length) { grid.innerHTML = '<div class="text-muted small p-3">No images in gallery yet. Add one above.</div>'; return; }
    grid.innerHTML = this.state.items.map(g => `
      <div class="ws-gallery-item">
        <img src="${this.esc(g.image_url)}" alt="${this.esc(g.caption || '')}"
             onerror="this.src='https://placehold.co/300x200/198754/fff?text=Image'"
             onclick="galleryViewImg('${this.esc(g.image_url)}')" style="cursor:pointer">
        <div class="ws-overlay">
          <button class="btn btn-sm btn-danger rounded-circle" style="width:32px;height:32px;padding:0" onclick="galleryDelete(${g.id})" title="Remove"><i class="bi bi-trash-fill"></i></button>
        </div>
        <div class="caption">${this.esc(g.caption || '\u2014')} <span class="text-muted">(${this.esc(g.category || '')})</span></div>
      </div>`).join('');
  },

  viewImg(url) {
    document.getElementById('wsImgViewSrc').src = url;
    new bootstrap.Modal(document.getElementById('wsImgViewModal')).show();
  },

  openModal() {
    document.getElementById('galleryUrl').value      = '';
    document.getElementById('galleryCaption').value  = '';
    document.getElementById('galleryCategory').value = 'General';
    document.getElementById('galleryImgPreviewWrap').style.display = 'none';
    new bootstrap.Modal(document.getElementById('wsGalleryModal')).show();
  },

  previewImg(url) {
    const wrap = document.getElementById('galleryImgPreviewWrap');
    const img  = document.getElementById('galleryImgPreview');
    if (url) { img.src = url; wrap.style.display = ''; }
    else { wrap.style.display = 'none'; }
  },

  async save() {
    const url = document.getElementById('galleryUrl').value.trim();
    if (!url) return this.notify('Image URL is required.', 'warning');
    try {
      const r = await this.API('POST', 'website/gallery', {
        image_url: url,
        caption:   document.getElementById('galleryCaption').value.trim(),
        category:  document.getElementById('galleryCategory').value,
      });
      if (r.status === 'success') {
        this.notify('Image added to gallery');
        bootstrap.Modal.getInstance(document.getElementById('wsGalleryModal')).hide();
        this.loadData();
      } else this.notify(r.message, 'danger');
    } catch (e) { this.notify(e.message, 'danger'); }
  },

  async delete(id) {
    if (!(await window.confirmAction('Confirm Deletion', 'Remove this image from the gallery?', { confirmText: 'Delete', danger: true }))) return;
    try { await this.API('DELETE', `website/gallery/${id}`); this.notify('Image removed'); this.loadData(); }
    catch (e) { this.notify(e.message, 'danger'); }
  },

  setupEventListeners() {
    const urlEl = document.getElementById('galleryUrl');
    if (urlEl) urlEl.addEventListener('input', () => this.previewImg(urlEl.value));
  },

  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    this.setupEventListeners();
    this.loadData();
  },
};

window.galleryOpenModal = () => manageSchoolGalleryController.openModal();
window.gallerySave      = () => manageSchoolGalleryController.save();
window.galleryDelete    = (id) => manageSchoolGalleryController.delete(id);
window.galleryViewImg   = (url) => manageSchoolGalleryController.viewImg(url);

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => manageSchoolGalleryController.init().catch(() => {}));
} else {
  manageSchoolGalleryController.init().catch(() => {});
}

window.manageSchoolGalleryController = manageSchoolGalleryController;
