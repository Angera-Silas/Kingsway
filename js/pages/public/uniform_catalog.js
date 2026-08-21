/* =============================================================================
   Uniform Catalogue — js/pages/public/uniform_catalog.js
   Listing page: image, name, short description, size count, price range.
   Every card links to product_details.php?id= for full details + cart.
   ============================================================================= */
(function () {
  'use strict';

  var PS = window.PublicSite;
  if (!PS) return;

  var S = PS.escapeHtml;
  var base = String(window.APP_BASE || '').replace(/\/+$/, '');
  var FALLBACK_IMG = base + '/images/official_school_logo.png';

  /* Keyword → fallback image for products with no uploaded image */
  var IMAGE_MAP = [
    [/shirt/i,           base + '/images/uniforms/shirt.jpg'],
    [/skirt/i,           base + '/images/uniforms/skirt.jpg'],
    [/sweater/i,         base + '/images/uniforms/sweater.jpg'],
    [/pullover/i,        base + '/images/uniforms/sweater.jpg'],
    [/jersey/i,          base + '/images/uniforms/sweater.jpg'],
    [/trouser/i,         base + '/images/uniforms/trousers.jpg'],
    [/pant/i,            base + '/images/uniforms/trousers.jpg'],
    [/track/i,           base + '/images/uniforms/tracksuit.jpg'],
    [/sock/i,            base + '/images/uniforms/socks.jpg'],
    [/shoe/i,            base + '/images/uniforms/shoes.jpg'],
    [/boot/i,            base + '/images/uniforms/shoes.jpg'],
    [/dress/i,           base + '/images/uniforms/dress.jpg'],
    [/cardigan/i,        base + '/images/uniforms/cardigan.jpg'],
    [/hat|cap|beret/i,   base + '/images/uniforms/hat.jpg'],
    [/tie/i,             base + '/images/uniforms/tie.jpg'],
    [/belt/i,            base + '/images/uniforms/belt.jpg'],
    [/bag|backpack/i,    base + '/images/uniforms/bag.jpg'],
    [/apron/i,           base + '/images/uniforms/apron.jpg'],
    [/lab.?coat/i,       base + '/images/uniforms/labcoat.jpg'],
  ];

  function productImage(p) {
    if (p.image_url && p.image_url !== FALLBACK_IMG) return p.image_url;
    var title = (p.title || '') + ' ' + (p.description || '');
    for (var i = 0; i < IMAGE_MAP.length; i++) {
      if (IMAGE_MAP[i][0].test(title)) return IMAGE_MAP[i][1];
    }
    return FALLBACK_IMG;
  }

  var page = {
    initialized: false,
    products: [],

    async init() {
      if (this.initialized) return;
      this.initialized = true;
      try {
        var resp = await fetch(base + '/api/public/uniform-catalog', {
          method: 'GET', credentials: 'same-origin',
          headers: { Accept: 'application/json' },
        });
        if (!resp.ok) throw new Error('HTTP ' + resp.status);
        var payload = await resp.json();
        this.products = (payload.data && payload.data.products) || payload.products || [];
        this.render();
        this.bindSearch();
      } catch (err) {
        if (window.KINGSWAY_DEBUG) console.warn('[uniform_catalog] load failed:', err);
        var el = document.getElementById('ucError');
        if (el) { el.textContent = 'Unable to load the catalogue. Please try again later.'; el.classList.remove('d-none'); }
        var grid = document.getElementById('ucGrid');
        if (grid) grid.innerHTML = '';
      }
    },

    render: function () {
      var el = document.getElementById('ucGrid');
      if (!el) return;
      if (!this.products.length) {
        el.innerHTML = '<div class="col-12 text-center py-5">' +
          '<i class="bi bi-bag-x fs-1 text-muted d-block mb-3"></i>' +
          '<p class="text-muted">No uniforms available yet. Check back soon!</p></div>';
        this.updateCount(0);
        return;
      }
      this.updateCount(this.products.length);
      el.innerHTML = this.products.map(function (p) { return page.card(p); }).join('');
      if (window.PublicUI && window.PublicUI.observeReveals) window.PublicUI.observeReveals(el);
    },

    card: function (p) {
      var img = productImage(p);
      var sizes = p.sizes || [];
      var count = sizes.length;
      var lo = Infinity, hi = 0;
      for (var i = 0; i < count; i++) {
        var price = Number(sizes[i].unit_price) || 0;
        if (price < lo) lo = price;
        if (price > hi) hi = price;
      }
      if (lo === Infinity) lo = 0;
      var priceStr = count
        ? (lo === hi
          ? 'KES ' + lo.toLocaleString()
          : 'KES ' + lo.toLocaleString() + ' – ' + hi.toLocaleString())
        : '';
      var href = base + '/product_details.php?id=' + encodeURIComponent(p.id);

      return '<div class="col-sm-6 col-lg-4 col-xl-3 reveal">' +
        '<a href="' + href + '" class="text-decoration-none">' +
        '<article class="card-modern h-100">' +
        '<div class="card-img-wrap">' +
        '<img src="' + S(img) + '" alt="' + S(p.title) + '" loading="lazy" onerror="this.onerror=null;this.src=\'' + FALLBACK_IMG + '\'">' +
        '</div>' +
        '<div class="p-3">' +
        '<h6 class="card-title mb-1">' + S(p.title) + '</h6>' +
        '<p class="card-excerpt mb-2">' + S((p.description || '').substring(0, 80)) + ((p.description || '').length > 80 ? '…' : '') + '</p>' +
        '<div class="d-flex align-items-center justify-content-between">' +
        '<div>' +
        (count
          ? '<span class="small text-muted"><i class="bi bi-rulers me-1"></i>' + count + ' size' + (count > 1 ? 's' : '') + '</span>'
          : '<span class="small text-muted">Coming soon</span>') +
        (priceStr ? '<div class="fw-bold mt-1" style="color:var(--green-dark);font-size:.88rem">' + priceStr + '</div>' : '') +
        '</div>' +
        '<span class="btn btn-success btn-sm rounded-pill px-3">' +
        (count ? 'Buy Now' : 'View') +
        '<i class="bi bi-arrow-right ms-1"></i></span>' +
        '</div></div></article></a></div>';
    },

    updateCount: function (n) {
      var el = document.getElementById('ucCount');
      if (el) el.textContent = n + ' item' + (n === 1 ? '' : 's');
    },

    bindSearch: function () {
      var input = document.getElementById('ucSearch');
      if (!input) return;
      input.addEventListener('input', function () {
        var q = input.value.toLowerCase().trim();
        var cards = document.querySelectorAll('#ucGrid .col-sm-6');
        var visible = 0;
        cards.forEach(function (col) {
          var text = (col.textContent || '').toLowerCase();
          var show = !q || text.indexOf(q) !== -1;
          col.style.display = show ? '' : 'none';
          if (show) visible++;
        });
        page.updateCount(visible);
      });
    },
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { page.init(); });
  } else {
    page.init();
  }
})();
