/**
 * YearMode: shows a completed academic year read-only for users who may not
 * edit it.
 *
 * Rule (2026-11-14): a completed year is readable by everyone. School admin,
 * system admin and director may edit it, and so may any user the system
 * administrator grants the edit-completed permission. The server decides and
 * enforces that (YearWriteScopeService); this helper only reflects the answer
 * of GET /api/academic/year-access so users are not offered actions that
 * would be refused.
 *
 * AcademicYearFilter calls YearMode.apply(yearId, anchorElement) whenever the
 * year changes, so every page with the shared filter gets this for free.
 * Pages mark write controls (buttons, inputs, forms) with `data-year-write`;
 * those are disabled while the selected year is read-only, including controls
 * a page renders later (table rows, modals): a MutationObserver applies the
 * read-only state to them as they appear. Page code can also
 * ask: `YearMode.editable` (true until known otherwise) or listen for the
 * `kingsway:year-mode` event on document ({detail: {year, editable}}).
 */
(function (window, document) {
  'use strict';

  const BANNER_ATTR = 'data-year-mode-banner';
  const WRITE_SELECTOR = '[data-year-write]';
  const cache = new Map();

  const state = { year: null, editable: true, scope: null };
  let observer = null;

  /** While read-only, disable write controls as soon as a page renders them. */
  function watch(scope) {
    if (typeof window.MutationObserver !== 'function') return;
    if (state.editable) {
      observer?.disconnect();
      observer = null;
      return;
    }
    if (observer && state.scope === scope) return;
    observer?.disconnect();
    observer = new window.MutationObserver((mutations) => {
      if (state.editable) return;
      const added = mutations.some((m) => Array.from(m.addedNodes || []).some((n) => n.nodeType === 1));
      if (added) setControls(scope, false);
    });
    observer.observe(scope, { childList: true, subtree: true });
  }

  async function access(yearId) {
    const key = String(yearId || 'current');
    if (!cache.has(key)) {
      const request = window.API.academic.getYearAccess(yearId || undefined)
        .then((response) => response?.data || response || null)
        .catch(() => { cache.delete(key); return null; });
      cache.set(key, request);
    }
    return cache.get(key);
  }

  function scopeOf(anchor) {
    return anchor?.closest('main, #mainContent, .page-content, .container-fluid, .container') || document.body;
  }

  function bannerText(year) {
    const label = year.label || 'This academic year';
    if (year.editable) {
      return `${label} is completed. You are allowed to edit its records; changes affect a closed year.`;
    }
    return `${label} is completed. You are viewing its records; only a school administrator, `
      + 'system administrator, director or a user given permission can change them.';
  }

  function renderBanner(anchor, year) {
    const scope = scopeOf(anchor);
    let banner = scope.querySelector(`[${BANNER_ATTR}]`);
    if (!year || year.state !== 'completed') {
      banner?.remove();
      return;
    }
    if (!banner) {
      banner = document.createElement('div');
      banner.setAttribute(BANNER_ATTR, '');
      banner.setAttribute('role', 'status');
      const block = anchor?.closest('.card, form, .row') || anchor?.parentElement;
      if (block && block.parentNode) block.parentNode.insertBefore(banner, block.nextSibling);
      else scope.prepend(banner);
    }
    banner.className = `alert ${year.editable ? 'alert-warning' : 'alert-secondary'} d-flex align-items-center gap-2 py-2 my-2 d-print-none`;
    banner.innerHTML = '';
    const icon = document.createElement('i');
    icon.className = `bi ${year.editable ? 'bi-pencil-square' : 'bi-lock'}`;
    icon.setAttribute('aria-hidden', 'true');
    const text = document.createElement('span');
    text.textContent = bannerText(year);
    banner.append(icon, text);
  }

  function setControls(scope, editable) {
    scope.querySelectorAll(WRITE_SELECTOR).forEach((element) => {
      const targets = element.matches('form, fieldset') ? element.querySelectorAll('button, input, select, textarea') : [element];
      targets.forEach((target) => {
        if (!editable) {
          if (!target.hasAttribute('data-year-mode-disabled')) {
            target.setAttribute('data-year-mode-disabled', target.disabled ? 'was' : 'yes');
          }
          target.disabled = true;
          target.setAttribute('title', 'This academic year is completed and read-only for you.');
        } else if (target.getAttribute('data-year-mode-disabled') === 'yes') {
          target.disabled = false;
          target.removeAttribute('title');
          target.removeAttribute('data-year-mode-disabled');
        } else if (target.hasAttribute('data-year-mode-disabled')) {
          target.removeAttribute('data-year-mode-disabled');
        }
      });
    });
  }

  async function apply(yearId, anchor = null) {
    const year = await access(yearId);
    state.year = year;
    state.editable = year ? year.editable !== false : true;
    renderBanner(anchor, year);
    state.scope = scopeOf(anchor);
    setControls(state.scope, state.editable);
    watch(state.scope);
    document.dispatchEvent(new CustomEvent('kingsway:year-mode', { detail: { year, editable: state.editable } }));
    return year;
  }

  /** Re-apply the current state, e.g. after a page re-renders its table. */
  function refresh(anchor = null) {
    setControls(scopeOf(anchor), state.editable);
  }

  window.YearMode = {
    apply,
    refresh,
    get editable() { return state.editable; },
    get year() { return state.year; },
  };
})(window, document);
