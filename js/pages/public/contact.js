/* =============================================================================
   Contact — js/pages/public/contact.js
   Renders the contact-info card, social links, map links and departmental
   contact cards from /api/website/{settings,departments}. The inquiry form
   keeps its own inline handler posting to /api/public/inquiries.
   ============================================================================= */
(function () {
  'use strict';

  const PS = window.PublicSite;
  if (!PS) return;

  const S = PS.escapeHtml;

  const DEFAULTS = {
    school_address_physical: 'Londiani – Kericho Road, Londiani Town, Kenya',
    school_address_postal: 'P.O BOX 203-20203, Londiani, Kericho County',
    school_phone_main: '+254 720 113 030',
    school_phone_alt: '+254 720 113 031',
    school_email_main: 'info@kingswaypreparatoryschool.sc.ke',
    office_hours_weekday: 'Monday – Friday: 7:30 AM – 5:00 PM',
    office_hours_saturday: 'Saturday: 9:00 AM – 1:00 PM',
    social_facebook: 'https://www.facebook.com/kingswayprepschool',
    social_twitter: 'https://twitter.com/kingswayprepschool',
    social_instagram: 'https://www.instagram.com/kingswayprepschool',
    social_whatsapp: '254720113030',
    social_youtube: 'https://www.youtube.com/@kingswayprepschool',
    google_maps_url: 'https://www.google.com/maps/search/Kingsway+Preparatory+School+Londiani',
    school_name: 'Kingsway Preparatory School',
  };

  const page = {
    initialized: false,

    async init() {
      if (this.initialized) return;
      this.initialized = true;
      try {
        const [settings, departments] = await Promise.all([
          PS.get('settings', {}, { tier: 'reference' }),
          PS.get('departments', {}, { tier: 'reference' }),
        ]);
        const m = PS.keyToMap(settings, 'setting_key', 'setting_value');
        this.renderInfo(m);
        this.renderSocial(m);
        this.renderMap(m);
        this.renderDepartments(departments);
      } catch (err) {
        if (window.KINGSWAY_DEBUG) console.warn('[contact] render failed:', err);
      }
    },

    renderInfo(m) {
      const addressEl = document.getElementById('ci-address');
      if (addressEl) addressEl.textContent = m.school_address_physical || DEFAULTS.school_address_physical;

      const postalEl = document.getElementById('ci-postal');
      if (postalEl) postalEl.textContent = m.school_address_postal || DEFAULTS.school_address_postal;

      const phoneEl = document.getElementById('ci-phone');
      if (phoneEl) {
        const main = m.school_phone_main || DEFAULTS.school_phone_main;
        const alt = m.school_phone_alt || DEFAULTS.school_phone_alt;
        const tel = (v) => '<a href="tel:' + S(String(v).replace(/\s+/g, '')) + '" class="ci-value">' + S(v) + '</a>';
        phoneEl.innerHTML = tel(main) + (alt ? '<br>' + tel(alt) : '');
      }

      const emailEl = document.getElementById('ci-email');
      if (emailEl) {
        const email = m.school_email_main || DEFAULTS.school_email_main;
        emailEl.innerHTML = '<a href="mailto:' + S(email) + '" class="ci-value">' + S(email) + '</a>';
      }

      const hoursEl = document.getElementById('ci-hours');
      if (hoursEl) {
        const wkd = m.office_hours_weekday || DEFAULTS.office_hours_weekday;
        const sat = m.office_hours_saturday || DEFAULTS.office_hours_saturday;
        hoursEl.innerHTML = S(wkd) + (sat ? '<br>' + S(sat) : '');
      }
    },

    renderSocial(m) {
      const el = document.getElementById('ci-social');
      if (!el) return;
      const v = (k) => m[k] || DEFAULTS[k];
      let html = '';
      if (v('social_facebook')) html += '<a href="' + S(v('social_facebook')) + '" aria-label="Facebook" target="_blank"><i class="bi bi-facebook"></i></a>';
      if (v('social_twitter')) html += '<a href="' + S(v('social_twitter')) + '" aria-label="Twitter" target="_blank"><i class="bi bi-twitter-x"></i></a>';
      if (v('social_instagram')) html += '<a href="' + S(v('social_instagram')) + '" aria-label="Instagram" target="_blank"><i class="bi bi-instagram"></i></a>';
      if (v('social_whatsapp')) html += '<a href="https://wa.me/' + S(String(v('social_whatsapp')).replace(/[^\d]/g, '')) + '" aria-label="WhatsApp" target="_blank"><i class="bi bi-whatsapp"></i></a>';
      if (v('social_youtube')) html += '<a href="' + S(v('social_youtube')) + '" aria-label="YouTube" target="_blank"><i class="bi bi-youtube"></i></a>';
      el.innerHTML = html;
    },

    renderMap(m) {
      const url = m.google_maps_url || DEFAULTS.google_maps_url;
      const open = document.getElementById('ci-maps-open');
      const view = document.getElementById('ci-maps-view');
      if (open) open.href = url;
      if (view) view.href = url;
      const school = document.getElementById('ci-school-name');
      if (school) school.textContent = m.school_name || DEFAULTS.school_name;
      const addr = document.getElementById('ci-map-address');
      if (addr) addr.textContent = m.school_address_physical || DEFAULTS.school_address_physical;
    },

    renderDepartments(departments) {
      const el = document.getElementById('contact-departments');
      if (!el) return;
      const list = PS.items(departments);
      if (!list.length) { el.innerHTML = PS.emptyHTML(); return; }
      el.innerHTML = list.map((d) => {
        const color = d.color || '#198754';
        return '<div class="col-lg-3 col-md-6">' +
          '<div class="text-center card-modern p-4 h-100 reveal">' +
          '<div class="rounded-circle d-flex align-items-center justify-content-center mx-auto mb-3" style="width:64px;height:64px;background:' + S(color) + '22;">' +
          '<i class="bi ' + S(d.icon || 'bi-building') + ' fs-3" style="color:' + S(color) + '"></i></div>' +
          '<h6 class="fw-bold mb-1">' + S(d.name) + '</h6>' +
          '<p class="text-muted small mb-3">' + S(d.description) + '</p>' +
          (d.email ? '<a href="mailto:' + S(d.email) + '" class="d-block text-success small mb-1 text-truncate">' + S(d.email) + '</a>' : '') +
          (d.phone ? '<a href="tel:' + S(String(d.phone).replace(/\s/g, '')) + '" class="d-block text-muted small">' + S(d.phone) + '</a>' : '') +
          '</div></div>';
      }).join('');
      if (window.PublicUI && window.PublicUI.observeReveals) window.PublicUI.observeReveals(el);
    },
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => page.init());
  } else {
    page.init();
  }
})();
