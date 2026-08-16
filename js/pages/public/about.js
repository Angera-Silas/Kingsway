/* =============================================================================
   About — js/pages/public/about.js
   Renders the About page dynamic sections (mission/vision/motto, core values,
   history timeline, leadership, programs, facilities) from /api/website/content
   and /api/website/settings through window.PublicSite. about.php stays a thin
   HTML shell; this controller fills #about-* containers.
   ============================================================================= */
(function () {
  'use strict';

  const PS = window.PublicSite;
  if (!PS) return;

  const S = (v) => PS.escapeHtml(v);

  const about = {
    initialized: false,

    async init() {
      if (this.initialized) return;
      this.initialized = true;
      try {
        const [content, settings] = await Promise.all([
          PS.get('content', {}, { tier: 'reference' }),
          PS.get('settings', {}, { tier: 'reference' }),
        ]);
        const blockMap = PS.keyToMap(content && content.blocks, 'content_key', 'content_value');
        const statMap = PS.keyToMap(settings, 'setting_key', 'setting_value');
        const sections = (content && content.sections) || {};
        this.renderIntro(blockMap, statMap);
        this.renderValues(sections.values);
        this.renderHistory(sections.history);
        this.renderLeadership(sections.leadership);
        this.renderPrograms(sections.programs);
        this.renderFacilities(sections.facilities);
      } catch (err) {
        if (window.KINGSWAY_DEBUG) console.warn('[about] render failed:', err);
      }
    },

    renderIntro(blocks, m) {
      const set = (id, val) => { const node = document.getElementById(id); if (node) node.textContent = val; };
      set('about-mission', blocks.mission || 'To provide a nurturing, inclusive, and academically rigorous environment that develops confident, virtuous, and globally-competitive learners through the Kenya Competency-Based Curriculum.');
      set('about-vision', blocks.vision || 'To be the most preferred school of excellence in the East African region, producing well-rounded, morally upright, and intellectually superior graduates.');
      set('about-motto', m.school_motto || 'In God We Soar');
      set('about-founded', m.school_founded_year || '2005');
    },

    renderValues(data) {
      const el = document.getElementById('about-values');
      if (!el) return;
      const list = PS.items(data);
      if (!list.length) { el.innerHTML = PS.emptyHTML(); return; }
      el.innerHTML = list.map((v) =>
        '<div class="col-6">' +
        '<div class="d-flex align-items-start gap-3 p-3 bg-white border rounded-3">' +
        '<i class="bi ' + S(v.icon || 'bi-star-fill') + ' fs-4 flex-shrink-0" style="color:' + S(v.color || '#198754') + '"></i>' +
        '<div>' +
        '<div class="fw-semibold small">' + S(v.name) + '</div>' +
        '<div class="text-muted" style="font-size:.75rem">' + S(v.description) + '</div>' +
        '</div></div></div>'
      ).join('');
      if (window.PublicUI && window.PublicUI.observeReveals) window.PublicUI.observeReveals(el);
    },

    renderHistory(data) {
      const el = document.getElementById('about-history');
      if (!el) return;
      const list = PS.items(data);
      if (!list.length) { el.innerHTML = PS.emptyHTML(); return; }
      el.innerHTML = list.map((h) =>
        '<div class="mb-5 position-relative reveal">' +
        '<div class="position-absolute" style="width:14px;height:14px;background:var(--green);border-radius:50%;left:-40px;top:5px;border:3px solid #fff;box-shadow:0 0 0 3px var(--green)"></div>' +
        '<span class="badge bg-success mb-2">' + S(h.year) + '</span>' +
        '<h5 class="fw-bold text-dark mb-1">' + S(h.event_title) + '</h5>' +
        '<p class="text-muted mb-0">' + S(h.description) + '</p>' +
        '</div>'
      ).join('');
      if (window.PublicUI && window.PublicUI.observeReveals) window.PublicUI.observeReveals(el);
    },

    renderLeadership(data) {
      const el = document.getElementById('about-leadership');
      if (!el) return;
      const list = PS.items(data);
      if (!list.length) { el.innerHTML = PS.emptyHTML(); return; }
      el.innerHTML = list.map((l) => {
        const nameParts = String(l.name || '').split(' ').filter(Boolean);
        const initials = (nameParts.length ? nameParts[nameParts.length - 1] : 'A').charAt(0).toUpperCase();
        const avatarBg = l.avatar_color || '#198754';
        return '<div class="col-lg-2 col-md-4 col-6">' +
          '<div class="text-center card-modern p-3 h-100 reveal">' +
          (l.avatar_url
            ? '<img src="' + S(l.avatar_url) + '" alt="' + S(l.name) + '" class="rounded-circle mx-auto d-block mb-3 object-fit-cover" style="width:72px;height:72px;">'
            : '<div class="rounded-circle d-flex align-items-center justify-content-center mx-auto mb-3 text-white fs-3 fw-bold" style="width:72px;height:72px;background:' + S(avatarBg) + '">' + S(initials) + '</div>') +
          '<div class="fw-bold small">' + S(l.name) + '</div>' +
          '<div class="text-success" style="font-size:.75rem;font-weight:600">' + S(l.title) + '</div>' +
          (l.bio ? '<p class="text-muted mt-2 mb-0" style="font-size:.75rem">' + S(l.bio) + '</p>' : '') +
          '</div></div>';
      }).join('');
      if (window.PublicUI && window.PublicUI.observeReveals) window.PublicUI.observeReveals(el);
    },

    renderPrograms(data) {
      const el = document.getElementById('about-programs');
      if (!el) return;
      const list = PS.items(data);
      if (!list.length) { el.innerHTML = PS.emptyHTML(); return; }
      el.innerHTML = list.map((p) => {
        const color = p.color || '#198754';
        return '<div class="col-lg-4 col-md-6 reveal">' +
          '<div class="program-card h-100">' +
          '<div class="program-icon" style="background:' + S(color) + '22">' +
          '<i class="bi ' + S(p.icon || 'bi-journal-bookmark-fill') + ' fs-2" style="color:' + S(color) + '"></i></div>' +
          '<h5>' + S(p.name) + '</h5>' +
          (p.level_range ? '<div class="tag mb-2" style="background:' + S(color) + '22;color:' + S(color) + '">' + S(p.level_range) + '</div>' : '') +
          '<p>' + S(p.description) + '</p>' +
          '</div></div>';
      }).join('');
      if (window.PublicUI && window.PublicUI.observeReveals) window.PublicUI.observeReveals(el);
    },

    renderFacilities(data) {
      const el = document.getElementById('about-facilities');
      if (!el) return;
      const list = PS.items(data);
      if (!list.length) { el.innerHTML = PS.emptyHTML(); return; }
      el.innerHTML = list.map((f) =>
        '<div class="col-lg-4 col-md-6 reveal">' +
        '<div class="d-flex align-items-start gap-3 p-4 bg-white border rounded-3 h-100">' +
        '<div class="bg-success bg-opacity-10 rounded-2 p-2 flex-shrink-0">' +
        '<i class="bi ' + S(f.icon || 'bi-building') + ' text-success fs-4"></i></div>' +
        '<div>' +
        '<div class="fw-bold small mb-1">' + S(f.name) + '</div>' +
        '<div class="text-muted" style="font-size:.82rem">' + S(f.description) + '</div>' +
        '</div></div></div>'
      ).join('');
      if (window.PublicUI && window.PublicUI.observeReveals) window.PublicUI.observeReveals(el);
    },
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => about.init());
  } else {
    about.init();
  }
})();
