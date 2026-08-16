/* =============================================================================
   Home — js/pages/public/home.js
   Renders the dynamic homepage sections from the public REST API through
   window.PublicSite (guest-scoped DataStore cache). index.php stays a thin
   HTML shell — this controller only fills the data containers:
   #home-ticker, #home-hero-stats, #home-stats, #home-programs, #home-news,
   #home-events, #home-gallery and the contact-strip value cells.
   ============================================================================= */
(function () {
  'use strict';

  const PS = window.PublicSite;
  if (!PS) return;

  const S = (v) => PS.escapeHtml(v);
  const base = String(window.APP_BASE || '').replace(/\/+$/, '');

  const home = {
    initialized: false,

    async init() {
      if (this.initialized) return;
      this.initialized = true;
      try {
        const [news, events, programs, gallery, stats, settings] = await Promise.all([
          PS.get('news', { limit: 3 }, { tier: 'dynamic' }),
          PS.get('events', { limit: 4 }, { tier: 'dynamic' }),
          PS.get('programs', {}, { tier: 'reference' }),
          PS.get('gallery', {}, { tier: 'reference' }),
          PS.get('stats', {}, { tier: 'dynamic' }),
          PS.get('settings', {}, { tier: 'reference' }),
        ]);
        const statMap = PS.keyToMap(settings, 'setting_key', 'setting_value');
        this.renderTicker(news);
        this.renderHeroStats(stats, statMap);
        this.renderStatsCounters(stats, statMap);
        this.renderPrograms(programs);
        this.renderNews(news);
        this.renderEvents(events);
        this.renderGallery(gallery);
        this.renderContactStrip(statMap);
      } catch (err) {
        if (window.KINGSWAY_DEBUG) console.warn('[home] render failed:', err);
      }
    },

    // Announcing ticker — items duplicated so the CSS marquee loops seamlessly.
    renderTicker(data) {
      const track = document.getElementById('home-ticker');
      if (!track) return;
      const list = PS.items(data).slice(0, 3);
      if (!list.length) return;
      const spans = list.map((n) =>
        '<span><a href="' + base + '/news-article.php?id=' + encodeURIComponent(n.id) + '">' + S(n.title) + '</a></span>'
      ).join('');
      track.innerHTML = spans + spans;
    },

    renderHeroStats(stats, m) {
      const el = document.getElementById('home-hero-stats');
      if (!el) return;
      const students = (stats && typeof stats.students === 'number') ? stats.students : 0;
      const rows = [
        [students + '+', m.hero_stat_1_label || 'Students Enrolled', 'bi-people-fill'],
        [m.hero_stat_2_value || '98%', m.hero_stat_2_label || 'KJSEA / KCPE Pass Rate', 'bi-mortarboard-fill'],
        [m.hero_stat_3_value || '30+', m.hero_stat_3_label || 'Regional Awards', 'bi-award-fill'],
        ['Est. ' + (m.school_founded_year || '2005'), m.hero_stat_4_label || 'Years of Excellence', 'bi-calendar2-check'],
      ];
      el.innerHTML = rows.map((r) =>
        '<div class="hero-stat">' +
        '<div class="hero-stat-icon"><i class="bi ' + S(r[2]) + '"></i></div>' +
        '<div class="hero-stat-text"><strong>' + S(r[0]) + '</strong><span>' + S(r[1]) + '</span></div>' +
        '</div>'
      ).join('');
    },

    // The six stat-card skeletons stay in the HTML; we only update the counter
    // targets/suffixes, then re-arm PublicUI's count-up observer.
    renderStatsCounters(stats, m) {
      const el = document.getElementById('home-stats');
      if (!el) return;
      const nums = el.querySelectorAll('.stat-number');
      const targets = [
        { target: (stats && stats.students) || 0, suffix: '+' },
        { target: (stats && stats.staff) || 0, suffix: '+' },
        { target: parseInt(m.stat_pass_rate, 10) || 98, suffix: '%' },
        { target: parseInt(m.stat_awards, 10) || 30, suffix: '+' },
        { target: parseInt(m.stat_years, 10) || 20, suffix: '' },
        { target: 100, suffix: '%' },
      ];
      nums.forEach((node, i) => {
        if (!targets[i]) return;
        node.dataset.target = targets[i].target;
        node.dataset.suffix = targets[i].suffix;
      });
      if (window.PublicUI && window.PublicUI.observeCounters) window.PublicUI.observeCounters(el);
    },

    renderPrograms(data) {
      const el = document.getElementById('home-programs');
      if (!el) return;
      const list = PS.items(data).slice(0, 6);
      if (!list.length) { el.innerHTML = PS.emptyHTML('Programs are being finalised. Check back soon.'); return; }
      el.innerHTML = list.map((p, i) => {
        const color = p.color || '#198754';
        const icon = p.icon || 'bi-journal-bookmark-fill';
        return '<div class="col-lg-4 col-md-6">' +
          '<a href="' + base + '/about.php#' + S(p.anchor || 'programs') + '" class="text-decoration-none">' +
          '<div class="program-card reveal delay-' + ((i % 3) + 1) + '" style="cursor:pointer">' +
          '<div class="program-icon" style="background:' + S(color) + '22">' +
          '<i class="bi ' + S(icon) + '" style="color:' + S(color) + '"></i></div>' +
          '<h5>' + S(p.name) + '</h5>' +
          '<p>' + S(p.description) + '</p>' +
          '<span style="color:' + S(color) + ';font-size:.82rem;font-weight:600">Learn More <i class="bi bi-arrow-right"></i></span>' +
          '</div></a></div>';
      }).join('');
      if (window.PublicUI && window.PublicUI.observeReveals) window.PublicUI.observeReveals(el);
    },

    renderNews(data) {
      const el = document.getElementById('home-news');
      if (!el) return;
      const list = PS.items(data).slice(0, 3);
      if (!list.length) { el.innerHTML = PS.emptyHTML('No articles published yet. Check back soon.'); return; }
      el.innerHTML = list.map((n, i) => {
        const style = PS.categoryStyle(n.category);
        const color = style[0];
        const icon = style[1];
        const img = n.image_url ? S(n.image_url) : PS.categoryImage(n.category, 600);
        const excerpt = String(n.excerpt || n.content || '').replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
        const trimmed = excerpt.length > 120 ? excerpt.slice(0, 120).trim() + '…' : excerpt;
        return '<div class="col-md-' + (i === 0 ? '12' : '6') + '">' +
          '<a href="' + base + '/news-article.php?id=' + encodeURIComponent(n.id) + '" class="text-decoration-none">' +
          '<div class="card-modern reveal delay-' + (i + 1) + '">' +
          '<div class="card-img-wrap">' +
          '<img src="' + img + '" alt="' + S(n.title) + '" loading="lazy" onerror="this.src=\'https://placehold.co/600x380/' + color.replace('#', '') + '/ffffff?text=News\'">' +
          '</div>' +
          '<div class="p-3">' +
          '<div class="d-flex align-items-center justify-content-between mb-2">' +
          '<span class="card-category" style="background:' + S(color) + '"><i class="bi ' + icon + '"></i>' + S(n.category || 'News') + '</span>' +
          '<span class="card-date"><i class="bi bi-calendar3"></i>' + PS.formatDate(n.created_at, 'full') + '</span>' +
          '</div>' +
          '<div class="card-title">' + S(n.title) + '</div>' +
          '<div class="card-excerpt">' + S(trimmed) + '</div>' +
          '<span class="read-more text-success">Read More <i class="bi bi-arrow-right"></i></span>' +
          '</div></div></a></div>';
      }).join('');
      if (window.PublicUI && window.PublicUI.observeReveals) window.PublicUI.observeReveals(el);
    },

    renderEvents(data) {
      const el = document.getElementById('home-events');
      if (!el) return;
      const list = PS.items(data).slice(0, 4);
      if (!list.length) { el.innerHTML = PS.emptyHTML('No upcoming events scheduled. Check back soon.'); return; }
      const typeColors = {
        Academic: ['#e3f2fd', '#1976d2'],
        Ceremony: ['#fff8e1', '#f9a825'],
        Sports: ['#e8f5e9', '#2e7d32'],
        Meeting: ['#fce4ec', '#c62828'],
      };
      el.innerHTML = list.map((ev) => {
        const tc = typeColors[ev.type] || ['#f3e5f5', '#7b1fa2'];
        return '<a href="' + base + '/event-detail.php?id=' + encodeURIComponent(ev.id) + '" class="text-decoration-none text-dark">' +
          '<div class="event-item" style="cursor:pointer">' +
          '<div class="event-date-box">' +
          '<div class="day">' + PS.formatDate(ev.start_at, 'd') + '</div>' +
          '<div class="month">' + PS.formatDate(ev.start_at, 'M') + '</div>' +
          '</div>' +
          '<div>' +
          '<div class="event-type" style="background:' + tc[0] + ';color:' + tc[1] + '">' + S(ev.type || 'Event') + '</div>' +
          '<div class="event-title">' + S(ev.title) + '</div>' +
          '<div class="event-meta">' +
          '<span><i class="bi bi-clock text-success"></i>' + PS.formatDate(ev.start_at, 'time') + '</span>' +
          (ev.location ? '<span><i class="bi bi-geo-alt text-success"></i>' + S(ev.location) + '</span>' : '') +
          '</div></div></div></a>';
      }).join('');
      if (window.PublicUI && window.PublicUI.observeReveals) window.PublicUI.observeReveals(el);
    },

    renderGallery(data) {
      const el = document.getElementById('home-gallery');
      if (!el) return;
      const list = PS.items(data).slice(0, 6);
      if (!list.length) { el.innerHTML = PS.emptyHTML('Gallery images are being added. Check back soon.'); return; }
      el.innerHTML = list.map((g) =>
        '<div class="gallery-item">' +
        '<img src="' + S(g.image_url) + '" alt="' + S(g.caption || 'Kingsway School') + '" loading="lazy" onerror="this.src=\'https://images.unsplash.com/photo-1503676260728-1c00da094a0b?w=400&q=70\'">' +
        '<div class="overlay"><i class="bi bi-zoom-in"></i></div>' +
        '</div>'
      ).join('');
    },

    renderContactStrip(m) {
      const set = (id, val) => { const node = document.getElementById(id); if (node) node.textContent = val; };
      set('contact-location', m.school_address_postal || 'P.O BOX 203-20203, Londiani, Kenya');
      set('contact-phone', (m.school_phone_main || '+254 720 113 030') + ' / ' + (m.school_phone_alt || '+254 720 113 031'));
      set('contact-email', m.school_email_main || 'info@kingswaypreparatoryschool.sc.ke');
      set('contact-hours', m.office_hours_weekday || 'Mon – Fri: 7:30 AM – 5:00 PM');
    },
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => home.init());
  } else {
    home.init();
  }
})();
