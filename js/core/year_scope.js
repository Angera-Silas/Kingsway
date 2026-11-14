/**
 * KingswayYearScope: year switching for pages that have no year select of
 * their own.
 *
 * A route listed in ROUTES shows one academic year's data. For such a route
 * the shell:
 *   1. adds the shared year (and optionally term) bar at the top of the page
 *      (AcademicYearFilter.mountBar); the current year is the default and a
 *      chosen year is kept in the URL (?academic_year_id=), so it survives a
 *      reload, a shared link and navigation to the next page;
 *   2. lets js/api.js add that academic_year_id (and academic_year_term_id) to
 *      the page's GET requests that do not name a year themselves, so the
 *      page's existing loaders read the chosen year without changes;
 *   3. reloads the page when the user picks another year or term.
 *
 * A completed year is shown read-only for users who may not edit it (YearMode,
 * applied by the filter). Pages that mount their own AcademicYearFilter are
 * NOT listed here. Pages that must show every year at once (a learner's
 * lifetime record, a learner's growth) must never be listed.
 *
 * Add a route: { term: true } when the page shows one term at a time,
 * { term: false } for whole-year pages. The route's endpoints must honour
 * academic_year_id (BaseAPI::normalizeYearFilters / YearScope::fromRequest).
 */
(function (window, document) {
  'use strict';

  const ROUTES = Object.freeze({
    // Phase 1 batch 5: teacher workspace (lesson plans)
    all_lesson_plans: { term: true },
    approved_lesson_plans: { term: true },
    lesson_plan_approval: { term: true },
    lesson_plans_by_class: { term: false },
    manage_lesson_plans: { term: true },
    // its own term filter is the term number within the selected year
    schemes_of_work: { term: false },
    // Phase 2: learners
    manage_students: { term: false },
  });

  const YEAR = 'academic_year_id';
  const TERM = 'academic_year_term_id';

  function currentRoute() {
    try {
      return window.REQUESTED_ROUTE || new URLSearchParams(window.location.search).get('route') || '';
    } catch (_) {
      return '';
    }
  }

  function config() {
    return ROUTES[currentRoute()] || null;
  }

  function urlValue(name) {
    try {
      const value = new URLSearchParams(window.location.search).get(name);
      return value && /^\d+$/.test(value) ? value : '';
    } catch (_) {
      return '';
    }
  }

  /**
   * Query parameters api.js adds to a GET on a year-scoped route. Empty for
   * the current year (the server defaults to it) and on every other route.
   */
  function requestParams() {
    const cfg = config();
    if (!cfg) return {};
    const params = {};
    const year = urlValue(YEAR);
    if (year) params[YEAR] = year;
    if (cfg.term) {
      const term = urlValue(TERM);
      if (term) params[TERM] = term;
    }
    return params;
  }

  let attachedFor = '';
  async function attach() {
    const route = currentRoute();
    const cfg = ROUTES[route];
    if (!cfg || attachedFor === route || !window.AcademicYearFilter?.mountBar) return;
    attachedFor = route;
    let first = true;
    await window.AcademicYearFilter.mountBar({
      term: Boolean(cfg.term),
      allTermsLabel: cfg.term ? 'All terms' : '',
      allTermsDefault: false,
      onChange: () => {
        // The first call reports the year already shown; later calls are the
        // user's choice: reload the route, whose URL the filter has updated.
        if (first) { first = false; return; }
        const params = new URLSearchParams(window.location.search);
        params.delete('route');
        const target = `home.php?route=${encodeURIComponent(route)}${params.toString() ? `&${params}` : ''}`;
        if (window.AppRouter?.go) window.AppRouter.go(target);
        else window.location.reload();
      },
    });
  }

  window.addEventListener('kingsway:page-will-load', () => { attachedFor = ''; });
  window.addEventListener('kingsway:page-loaded', () => { attach(); });
  document.addEventListener('DOMContentLoaded', () => { attach(); });

  window.KingswayYearScope = Object.freeze({
    routes: ROUTES,
    isYearScoped: (route) => Boolean(ROUTES[route || currentRoute()]),
    requestParams,
    attach,
  });
})(window, document);
