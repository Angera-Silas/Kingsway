/* =============================================================================
   Product Details — js/pages/public/product_details.js
   Full product view with image gallery, size picker, add-to-cart, wishlist,
   and checkout. Auth via parent-portal token in sessionStorage.
   ============================================================================= */
(function () {
  'use strict';

  var PS = window.PublicSite;
  if (!PS) return;

  var S = PS.escapeHtml;
  var base = String(window.APP_BASE || '').replace(/\/+$/, '');
  var FALLBACK_IMG = base + '/images/official_school_logo.png';

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

  function parentToken() {
    try { return sessionStorage.getItem('pp_token'); } catch (_) { return null; }
  }

  function show(el, html) { if (el) el.innerHTML = html; }
  function showEl(id, show) { var e = document.getElementById(id); if (e) e.classList.toggle('d-none', !show); }

  function toast(msg, type) {
    var c = document.getElementById('appToastContainer');
    if (!c) { c = document.createElement('div'); c.id = 'appToastContainer';
      c.style.cssText = 'position:fixed;top:86px;right:24px;z-index:1085;display:flex;flex-direction:column;gap:10px;max-width:380px';
      document.body.appendChild(c); }
    var bg = type === 'error' ? '#fee2e2' : type === 'warning' ? '#fef3c7' : '#dcfce7';
    var border = type === 'error' ? '#ef4444' : type === 'warning' ? '#f59e0b' : '#22c55e';
    var el = document.createElement('div');
    el.style.cssText = 'background:' + bg + ';border-left:4px solid ' + border + ';padding:14px 18px;border-radius:8px;box-shadow:0 4px 16px rgba(0,0,0,.12);font-size:.9rem;animation:fadeIn .3s';
    el.textContent = msg;
    c.appendChild(el);
    setTimeout(function () { el.remove(); }, 4000);
  }

  /* ── API helpers ─────────────────────────────────────────────────────── */

  async function apiGet(path) {
    var resp = await fetch(base + path, {
      method: 'GET', credentials: 'same-origin',
      headers: { Accept: 'application/json' },
    });
    if (!resp.ok) throw new Error('HTTP ' + resp.status);
    return (await resp.json()).data || {};
  }

  async function apiPost(path, body) {
    var token = parentToken();
    var headers = { 'Content-Type': 'application/json', Accept: 'application/json' };
    if (token) headers['Authorization'] = 'Bearer ' + token;
    var resp = await fetch(base + path, {
      method: 'POST', credentials: 'same-origin', headers: headers,
      body: JSON.stringify(body),
    });
    var json = await resp.json();
    if (!resp.ok || json.success === false) throw new Error(json.message || 'Request failed');
    return json.data || json;
  }

  /* ── Page controller ─────────────────────────────────────────────────── */

  var page = {
    product: null,
    selectedSize: null,
    allImages: [],

    init: function () {
      var params = new URLSearchParams(window.location.search);
      var id = params.get('id');
      if (!id) { this.error('No product specified.'); return; }
      this.load(id);
    },

    error: function (msg) {
      showEl('pdLoading', false);
      var el = document.getElementById('pdError');
      if (el) { el.textContent = msg; el.classList.remove('d-none'); }
    },

    load: async function (id) {
      try {
        var data = await apiGet('/api/public/uniform-catalog/' + encodeURIComponent(id));
        this.product = data.product || data;
        if (!this.product || !this.product.id) { this.error('Product not found.'); return; }
        this.render();
        showEl('pdLoading', false);
        showEl('pdContent', true);
      } catch (err) {
        if (window.KINGSWAY_DEBUG) console.warn('[product_details] load failed:', err);
        this.error('Unable to load product details. Please try again.');
      }
    },

    render: function () {
      var p = this.product;
      document.title = S(p.title) + ' | Kingsway Uniform Store';

      // Breadcrumb
      var crumb = document.getElementById('pd-crumb');
      if (crumb) crumb.textContent = p.title;

      // Images
      var images = [];
      if (p.images && p.images.length) {
        p.images.forEach(function (img) { images.push(img.url); });
      }
      if (!images.length) images.push(productImage(p));
      this.allImages = images;
      this.renderGallery(images);

      // Title + description
      show(document.getElementById('pdTitle'), S(p.title));
      show(document.getElementById('pdDesc'), S(p.description || ''));

      // Sizes
      var sizes = p.sizes || [];
      if (!sizes.length) {
        showEl('pdSizesSection', false);
        return;
      }
      this.renderSizes(sizes);
    },

    renderGallery: function (images) {
      var mainEl = document.getElementById('pdMainImage');
      var thumbsEl = document.getElementById('pdThumbs');

      if (mainEl) {
        mainEl.innerHTML = '<img src="' + S(images[0]) + '" alt="' + S(this.product.title) +
          '" style="width:100%;height:100%;object-fit:cover" onerror="this.onerror=null;this.src=\'' + FALLBACK_IMG + '\'">';
      }

      if (thumbsEl && images.length > 1) {
        thumbsEl.innerHTML = images.map(function (url, i) {
          return '<img src="' + S(url) + '" class="pd-thumb' + (i === 0 ? ' active' : '') +
            '" data-idx="' + i + '" alt="Image ' + (i + 1) + '">';
        }).join('');
        thumbsEl.addEventListener('click', function (e) {
          var thumb = e.target.closest('.pd-thumb');
          if (!thumb) return;
          var idx = parseInt(thumb.dataset.idx, 10);
          if (mainEl) {
            mainEl.innerHTML = '<img src="' + S(images[idx]) + '" alt="' + S(page.product.title) +
              '" style="width:100%;height:100%;object-fit:cover" onerror="this.onerror=null;this.src=\'' + FALLBACK_IMG + '\'">';
          }
          thumbsEl.querySelectorAll('.pd-thumb').forEach(function (t) { t.classList.remove('active'); });
          thumb.classList.add('active');
        });
      }
    },

    renderSizes: function (sizes) {
      var container = document.getElementById('pdSizes');
      if (!container) return;

      container.innerHTML = sizes.map(function (s) {
        var label = s.size_label || s.size || '';
        var price = Number(s.unit_price) || 0;
        return '<button type="button" class="pd-size-btn" data-sid="' + s.size_id +
          '" data-price="' + price + '">' +
          '<span class="pd-size-label">' + S(label) + '</span>' +
          '<span class="pd-size-price">KES ' + price.toLocaleString() + '</span>' +
          '</button>';
      }).join('');

      var self = this;
      container.addEventListener('click', function (e) {
        var btn = e.target.closest('.pd-size-btn');
        if (!btn) return;
        container.querySelectorAll('.pd-size-btn').forEach(function (b) { b.classList.remove('selected'); });
        btn.classList.add('selected');
        self.selectedSize = { id: parseInt(btn.dataset.sid, 10), price: parseFloat(btn.dataset.price) };

        var priceEl = document.getElementById('pdPrice');
        var stockEl = document.getElementById('pdStock');
        if (priceEl) priceEl.textContent = 'KES ' + self.selectedSize.price.toLocaleString();
        if (stockEl) stockEl.textContent = 'In stock';
        showEl('pdPriceRow', true);

        var addBtn = document.getElementById('pdAddToCart');
        if (addBtn) addBtn.disabled = false;
      });
    },

    setupActions: function () {
      var addBtn = document.getElementById('pdAddToCart');
      var wishBtn = document.getElementById('pdWishlist');

      if (addBtn) {
        addBtn.addEventListener('click', async function () {
          if (!parentToken()) {
            window.location.href = base + '/parent_portal.php';
            return;
          }
          if (!page.selectedSize) {
            toast('Please select a size first.', 'warning');
            return;
          }
          addBtn.disabled = true;
          addBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Adding…';
          try {
            await apiPost('/api/parent-portal/uniform-cart', {
              product_id: page.product.id,
              size_id: page.selectedSize.id,
              quantity: 1,
            });
            toast('Added to your cart!', 'success');
            var cartInfo = document.getElementById('pdCartInfo');
            var cartBox = document.getElementById('pdCheckoutBox');
            if (cartInfo) cartInfo.textContent = page.product.title + ' added. Proceed to checkout.';
            showEl('pdCheckoutBox', true);
          } catch (err) {
            toast(err.message || 'Failed to add to cart.', 'error');
          }
          addBtn.disabled = false;
          addBtn.innerHTML = '<i class="bi bi-bag-plus me-2"></i>Add to Cart';
        });
      }

      if (wishBtn) {
        wishBtn.addEventListener('click', async function () {
          if (!parentToken()) {
            window.location.href = base + '/parent_portal.php';
            return;
          }
          wishBtn.disabled = true;
          try {
            await apiPost('/api/parent-portal/uniform-wishlist', { product_id: page.product.id });
            wishBtn.innerHTML = '<i class="bi bi-heart-fill text-danger"></i>';
            toast('Added to wishlist!', 'success');
          } catch (err) {
            toast(err.message || 'Failed.', 'error');
            wishBtn.disabled = false;
          }
        });
      }
    },
  };

  document.addEventListener('DOMContentLoaded', function () {
    page.init();
    page.setupActions();
  });
})();
