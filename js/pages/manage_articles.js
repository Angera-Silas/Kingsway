/**
 * manage_articles.js — News Articles Controller (standalone page).
 * CRUD for public website news articles.
 */
const manageArticlesController = {
  state: { items: [], newsCats: [] },

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
  fmtDate(d) { return d ? new Date(d).toLocaleDateString('en-KE', { day: '2-digit', month: 'short', year: 'numeric' }) : '\u2014'; },
  badgeStatus(s) {
    const colors = { published: 'success', draft: 'secondary', archived: 'dark' };
    const c = colors[s] || 'secondary';
    return `<span class="badge bg-${c}">${s || '\u2014'}</span>`;
  },
  catColor(cat) {
    const m = { Sports: '#198754', Academic: '#1976d2', Infrastructure: '#e91e63', Announcement: '#f9a825', Arts: '#9c27b0', Community: '#00695c' };
    return m[cat] || '#198754';
  },

  async loadStats() {
    try {
      const r = await this.API('GET', 'website/stats');
      if (r.status === 'success' && r.data) {
        document.getElementById('statTotal').textContent = r.data.news ?? '\u2014';
      }
      const items = this.state.items;
      const views = items.reduce((s, n) => s + (n.views || 0), 0);
      document.getElementById('statViews').textContent = views;
    } catch (_) {}
  },

  async loadData() {
    const body = document.getElementById('newsTableBody');
    body.innerHTML = '<tr><td colspan="7" class="text-center py-3"><div class="spinner-border spinner-border-sm"></div></td></tr>';
    try {
      if (this.state.newsCats.length === 0) {
        const cr = await this.API('GET', 'website/categories');
        this.state.newsCats = cr?.data?.items || [];
        const sel = document.getElementById('newsCategory');
        const filterSel = document.getElementById('newsCatFilter');
        this.state.newsCats.forEach(c => {
          if (!sel.querySelector(`option[value="${c.name}"]`)) sel.innerHTML += `<option value="${this.esc(c.name)}">${this.esc(c.name)}</option>`;
          filterSel.innerHTML += `<option value="${this.esc(c.name)}">${this.esc(c.name)}</option>`;
        });
      }
      const cat    = document.getElementById('newsCatFilter').value;
      const status = document.getElementById('newsStatusFilter').value;
      const search = document.getElementById('newsSearch').value;
      const r = await this.API('GET', 'website/news', { category: cat, status, search, limit: 100 });
      this.state.items = r?.data?.items || [];
      this.render();
      this.loadStats();
    } catch (e) {
      body.innerHTML = `<tr><td colspan="7" class="text-center py-3 text-danger">${this.esc(e.message || 'Load failed')}</td></tr>`;
    }
  },

  render() {
    const body = document.getElementById('newsTableBody');
    if (!this.state.items.length) { body.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted">No articles found.</td></tr>'; return; }
    body.innerHTML = this.state.items.map(n => `
      <tr>
        <td><img src="${this.esc(n.image_url || '')}" class="ws-img-thumb" onerror="this.src='https://placehold.co/80x50/198754/fff?text=News'"></td>
        <td><div class="fw-semibold small" style="max-width:260px">${this.esc(n.title)}</div><div class="text-muted" style="font-size:.72rem">${this.esc(n.author || '')}</div></td>
        <td><span class="ws-tag-chip" style="background:${this.catColor(n.category)}22;color:${this.catColor(n.category)};border-color:${this.catColor(n.category)}44">${this.esc(n.category)}</span></td>
        <td>${this.badgeStatus(n.status)}</td>
        <td class="text-muted small">${n.views || 0}</td>
        <td class="text-muted small">${this.fmtDate(n.created_at)}</td>
        <td class="text-end">
          <button class="btn btn-sm btn-outline-secondary rounded-pill px-2 me-1" title="Edit" onclick="articlesOpenModal(${n.id})"><i class="bi bi-pencil"></i></button>
          <button class="btn btn-sm btn-outline-danger rounded-pill px-2" title="Delete" onclick="articlesDelete(${n.id},'${this.esc(n.title).replace(/'/g, '')}')"><i class="bi bi-trash"></i></button>
        </td>
      </tr>`).join('');
  },

  previewNewsImg(url) {
    const wrap = document.getElementById('newsImgPreviewWrap');
    const img  = document.getElementById('newsImgPreview');
    if (url) { img.src = url; wrap.style.display = ''; }
    else { wrap.style.display = 'none'; }
  },

  async openModal(id = null) {
    document.getElementById('newsEditId').value = id || '';
    document.getElementById('wsNewsModalTitle').textContent = id ? 'Edit Article' : 'New Article';
    ['newsTitle', 'newsExcerpt', 'newsContent', 'newsAuthor', 'newsImageUrl'].forEach(f => {
      const el = document.getElementById(f); if (el) el.value = '';
    });
    document.getElementById('newsImgPreviewWrap').style.display = 'none';
    document.getElementById('newsStatus').value   = 'published';
    document.getElementById('newsCategory').value = 'Announcement';
    if (id) {
      const r = await this.API('GET', `website/news/${id}`);
      const a = r?.data;
      if (a) {
        document.getElementById('newsTitle').value     = a.title || '';
        document.getElementById('newsExcerpt').value   = a.excerpt || '';
        document.getElementById('newsContent').value   = a.content || '';
        document.getElementById('newsAuthor').value    = a.author || '';
        document.getElementById('newsImageUrl').value  = a.image_url || '';
        document.getElementById('newsStatus').value    = a.status || 'published';
        document.getElementById('newsCategory').value  = a.category || 'Announcement';
        this.previewNewsImg(a.image_url);
      }
    }
    new bootstrap.Modal(document.getElementById('wsNewsModal')).show();
  },

  async save() {
    const id = document.getElementById('newsEditId').value;
    const payload = {
      title:     document.getElementById('newsTitle').value.trim(),
      excerpt:   document.getElementById('newsExcerpt').value.trim(),
      content:   document.getElementById('newsContent').value.trim(),
      author:    document.getElementById('newsAuthor').value.trim(),
      image_url: document.getElementById('newsImageUrl').value.trim(),
      category:  document.getElementById('newsCategory').value,
      status:    document.getElementById('newsStatus').value,
    };
    if (!payload.title || !payload.content) return this.notify('Title and content are required.', 'warning');
    const btn = document.getElementById('newsSubmitBtn');
    btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving\u2026';
    try {
      const r = id
        ? await this.API('PUT',  `website/news/${id}`, payload)
        : await this.API('POST', 'website/news',        payload);
      if (r.status === 'success') {
        this.notify(id ? 'Article updated' : 'Article published');
        bootstrap.Modal.getInstance(document.getElementById('wsNewsModal')).hide();
        this.loadData();
      } else { this.notify(r.message || 'Save failed', 'danger'); }
    } catch (e) { this.notify(e.message || 'Error', 'danger'); }
    finally { btn.disabled = false; btn.innerHTML = '<i class="bi bi-send-fill me-1"></i>Publish Article'; }
  },

  async delete(id, title) {
    if (!(await window.confirmAction('Confirm Deletion', `Delete "${title}"? This cannot be undone.`, { confirmText: 'Delete', danger: true }))) return;
    try {
      await this.API('DELETE', `website/news/${id}`);
      this.notify('Article deleted'); this.loadData();
    } catch (e) { this.notify(e.message, 'danger'); }
  },

  setupEventListeners() {
    ['newsSearch', 'newsCatFilter', 'newsStatusFilter'].forEach(id => {
      const el = document.getElementById(id);
      if (el) el.addEventListener('change', () => this.loadData());
    });
    const searchEl = document.getElementById('newsSearch');
    if (searchEl) searchEl.addEventListener('keyup', () => this.loadData());
    const urlEl = document.getElementById('newsImageUrl');
    if (urlEl) urlEl.addEventListener('input', () => this.previewNewsImg(urlEl.value));
  },

  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    this.setupEventListeners();
    this.loadData();
  },
};

window.articlesOpenModal = (id) => manageArticlesController.openModal(id);
window.articlesSave      = () => manageArticlesController.save();
window.articlesDelete    = (id, title) => manageArticlesController.delete(id, title);

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => manageArticlesController.init().catch(() => {}));
} else {
  manageArticlesController.init().catch(() => {});
}

window.manageArticlesController = manageArticlesController;
