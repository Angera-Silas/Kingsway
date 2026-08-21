/* =============================================================================
   Downloads — js/pages/public/downloads.js
   Renders grouped document categories from GET /api/website/downloads and
   powers the #dlSearch filter. download_url is a signed public URL returned by
   the API, so each anchor points straight at the document.
   ============================================================================= */
(function () {
  'use strict';

  const PS = window.PublicSite;
  if (!PS) return;

  const S = PS.escapeHtml;

  const page = {
    initialized: false,
    items: [],

    async init() {
      if (this.initialized) return;
      this.initialized = true;
      try {
        const data = await PS.get('downloads', {}, { tier: 'dynamic' });
        this.items = PS.items(data);
        this.render();
        this.bindSearch();
      } catch (err) {
        if (window.KINGSWAY_DEBUG) console.warn('[downloads] render failed:', err);
        const el = document.getElementById('dl-categories');
        if (el) el.innerHTML = PS.errorHTML();
      }
    },

    render() {
      const el = document.getElementById('dl-categories');
      if (!el) return;
      if (!this.items.length) {
        el.innerHTML = '<div class="text-center py-5"><i class="bi bi-folder-x fs-1 text-muted d-block mb-3"></i>' +
          '<p class="text-muted">No documents available yet.</p></div>';
        return;
      }

      // Group by category preserving the API ordering (category, display_order).
      const order = [];
      const groups = {};
      this.items.forEach((doc) => {
        const cat = doc.category || 'General';
        if (!groups[cat]) { groups[cat] = []; order.push(cat); }
        groups[cat].push(doc);
      });

      el.innerHTML = order.map((cat) => {
        const docs = groups[cat];
        return '<div class="mb-5 reveal dl-category">' +
          '<div class="d-flex align-items-center gap-3 mb-3">' +
          '<h4 class="fw-bold mb-0">' + S(cat) + '</h4>' +
          '<span class="badge bg-success">' + docs.length + ' document' + (docs.length !== 1 ? 's' : '') + '</span>' +
          '</div>' +
          '<div class="row g-3">' +
          docs.map((doc) => this.item(doc)).join('') +
          '</div></div>';
      }).join('');

      if (window.PublicUI && window.PublicUI.observeReveals) window.PublicUI.observeReveals(el);
    },

    item(doc) {
      const color = doc.color || '#198754';
      const title = String(doc.title || 'Document');
      return '<div class="col-lg-6 dl-item" data-title="' + S(title.toLowerCase()) + '">' +
        '<div class="download-item">' +
        '<div class="download-icon" style="background:' + S(color) + '22;color:' + S(color) + '">' +
        '<i class="bi ' + S(doc.icon || 'bi-file-earmark-pdf-fill') + '"></i></div>' +
        '<div class="flex-grow-1">' +
        '<div class="download-name">' + S(title) + '</div>' +
        '<div class="download-meta">' +
        '<span class="tag me-1" style="background:' + S(color) + '22;color:' + S(color) + ';padding:2px 8px;font-size:.68rem">' + S(doc.file_type || 'PDF') + '</span>' +
        (doc.file_size ? '<span>' + S(doc.file_size) + '</span>' : '') +
        '</div></div>' +
        (doc.download_url
          ? '<a href="' + S(doc.download_url) + '" class="download-btn" title="Download ' + S(title) + '" target="_blank" rel="noopener"><i class="bi bi-download"></i></a>'
          : '') +
        '</div></div>';
    },

    bindSearch() {
      const input = document.getElementById('dlSearch');
      if (!input) return;
      input.addEventListener('input', () => {
        const q = input.value.toLowerCase().trim();
        document.querySelectorAll('.dl-item').forEach((item) => {
          const haystack = (item.dataset.title || item.textContent || '').toLowerCase();
          item.style.display = (!q || haystack.includes(q)) ? '' : 'none';
        });
      });
    },
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => page.init());
  } else {
    page.init();
  }
})();
