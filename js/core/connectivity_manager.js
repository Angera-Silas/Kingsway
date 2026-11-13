/**
 * ConnectivityManager: server reachability only. It never owns or refreshes
 * authentication, and it never navigates away from the current page.
 *
 * Other modules only *hint* that the server may be unreachable (api.js
 * dispatches `kingsway:server-unreachable` when a request or token refresh
 * cannot connect). The manager confirms with its own cheap probe before
 * telling the user anything, so a single dropped request never raises a
 * banner. While the server is unreachable it shows one quiet banner, keeps
 * probing, and announces `kingsway:server-reachable` the moment it answers,
 * which lets waiting work (the auth boot, a pending page load) resume in place.
 *
 * Listening starts when this file loads, not on initialize(): the auth boot can
 * be waiting for the server before app_bootstrap ever calls initialize().
 */
const ConnectivityManager = (() => {
  'use strict';

  const PROBE_TIMEOUT_MS = 6000;
  const RETRY_DELAYS_MS = [3000, 5000, 10000, 15000];
  const RESTORED_NOTICE_MS = 2500;

  const subscribers = new Set();
  // 'ok' | 'unreachable' (no connection) | 'server_error' (answering, failing)
  let state = 'ok';
  let probePromise = null;
  let retryTimer = null;
  let retryIndex = 0;
  let banner = null;
  let restoredTimer = null;

  function appBase() {
    return (window.APP_BASE || '').replace(/\/+$/, '');
  }

  // A static file through the normal web server: no PHP work, and HEAD
  // requests are never intercepted by the service worker.
  function probeUrl() {
    return `${appBase()}/images/favicon/favicon.svg?_reachability=${Date.now()}`;
  }

  async function probe() {
    if (probePromise) return probePromise;
    probePromise = (async () => {
      if (navigator.onLine === false) return false;
      const controller = new AbortController();
      const timeout = setTimeout(() => controller.abort(), PROBE_TIMEOUT_MS);
      try {
        const response = await fetch(probeUrl(), {
          method: 'HEAD',
          cache: 'no-store',
          credentials: 'same-origin',
          signal: controller.signal,
        });
        // Any answer below 500 proves the server is there. 502/503/504 come
        // from the load balancer when the application servers are down.
        return response.status < 500;
      } catch (_) {
        return false;
      } finally {
        clearTimeout(timeout);
      }
    })();
    try {
      return await probePromise;
    } finally {
      probePromise = null;
    }
  }

  async function checkConnectivity() {
    const ok = await probe();
    setReachable(ok);
    return ok;
  }

  function scheduleRetry() {
    clearTimeout(retryTimer);
    const delay = RETRY_DELAYS_MS[Math.min(retryIndex, RETRY_DELAYS_MS.length - 1)];
    retryIndex += 1;
    retryTimer = setTimeout(async () => {
      if (state !== 'unreachable') return;
      if (document.visibilityState === 'hidden') {
        scheduleRetry();
        return;
      }
      await checkConnectivity();
    }, delay);
  }

  // A probe can prove the network path is back, but not that a failing
  // server has recovered: leave a server-error state alone until a real
  // request succeeds (api.js then announces kingsway:server-reachable).
  function setReachable(value) {
    if (value && state === 'server_error') return;
    setState(value ? 'ok' : 'unreachable');
  }

  function setState(next) {
    if (next === state) {
      if (next === 'unreachable') scheduleRetry();
      return;
    }
    const wasOk = state === 'ok';
    state = next;
    if (next === 'ok') {
      clearTimeout(retryTimer);
      retryTimer = null;
      retryIndex = 0;
      window.__KINGSWAY_SERVER_UNREACHABLE__ = false;
      showRestored();
      emit('ONLINE', { online: true });
      window.dispatchEvent(new CustomEvent('kingsway:server-reachable'));
      window.SyncQueue?.processQueue?.().catch(console.warn);
      return;
    }
    showProblem();
    if (wasOk) emit('OFFLINE', { online: false });
    if (next === 'unreachable') scheduleRetry();
    else clearTimeout(retryTimer);
  }

  // ---- Banner ----------------------------------------------------------

  function ensureBanner() {
    if (banner) return banner;
    if (!document.body) return null;

    if (!document.getElementById('kw-connection-banner-style')) {
      const style = document.createElement('style');
      style.id = 'kw-connection-banner-style';
      style.textContent = `
        .kw-connection-banner{position:fixed;left:50%;top:12px;transform:translate(-50%,-160%);
          z-index:2000;display:flex;align-items:center;gap:12px;max-width:calc(100vw - 32px);
          padding:10px 14px 10px 16px;border-radius:10px;font:500 14px/1.4 system-ui,-apple-system,"Segoe UI",sans-serif;
          background:#3b2f0b;color:#fff8df;box-shadow:0 8px 24px rgba(0,0,0,.18);
          transition:transform .25s ease;}
        .kw-connection-banner.is-visible{transform:translate(-50%,0);}
        .kw-connection-banner.is-restored{background:#11513d;color:#edf6f1;}
        .kw-connection-banner button{flex:none;border:1px solid currentColor;background:transparent;color:inherit;
          border-radius:6px;padding:3px 10px;font:inherit;font-size:13px;cursor:pointer;}
        .kw-connection-banner button[hidden]{display:none;}
        @media print{.kw-connection-banner{display:none!important;}}
        @media (prefers-reduced-motion:reduce){.kw-connection-banner{transition:none;}}
      `;
      document.head.appendChild(style);
    }

    banner = document.createElement('div');
    banner.className = 'kw-connection-banner';
    banner.setAttribute('role', 'status');
    banner.setAttribute('aria-live', 'polite');
    banner.innerHTML = '<span class="kw-connection-text"></span><button type="button">Try again</button>';
    banner.querySelector('button').addEventListener('click', () => {
      retryIndex = 0;
      // Wakes any waiting work (the auth boot retries its refresh at once).
      window.dispatchEvent(new CustomEvent('kingsway:retry-connection'));
      if (state === 'unreachable') void checkConnectivity();
    });
    document.body.appendChild(banner);
    return banner;
  }

  function renderBanner(text, { restored = false } = {}) {
    const el = ensureBanner();
    if (!el) {
      document.addEventListener('DOMContentLoaded', () => renderBanner(text, { restored }), { once: true });
      return;
    }
    el.querySelector('.kw-connection-text').textContent = text;
    el.querySelector('button').hidden = restored;
    el.classList.toggle('is-restored', restored);
    el.classList.add('is-visible');
  }

  function hideBanner() {
    banner?.classList.remove('is-visible');
  }

  function showProblem() {
    clearTimeout(restoredTimer);
    if (state === 'server_error') {
      renderBanner('The school server is having a problem. This page will continue when it recovers.');
    } else if (navigator.onLine === false) {
      renderBanner('You are offline. This page will continue when your connection is back.');
    } else {
      renderBanner('Cannot reach the school server. This page will continue when it is back.');
    }
  }

  function showRestored() {
    if (!banner?.classList.contains('is-visible')) return;
    renderBanner('Connection restored.', { restored: true });
    clearTimeout(restoredTimer);
    restoredTimer = setTimeout(hideBanner, RESTORED_NOTICE_MS);
  }

  // ---- Wiring (at load) --------------------------------------------------

  window.addEventListener('offline', () => setState('unreachable'));
  window.addEventListener('online', () => {
    if (state === 'unreachable') void checkConnectivity();
  });
  window.addEventListener('kingsway:server-unreachable', (event) => {
    if (event.detail?.answered) setState('server_error');
    else if (state === 'ok') void checkConnectivity();
  });
  window.addEventListener('kingsway:server-reachable', () => setState('ok'));
  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible' && state === 'unreachable') void checkConnectivity();
  });

  // api.js may have hinted before this file loaded (the auth boot starts first).
  if (window.__KINGSWAY_SERVER_UNREACHABLE__ === 'server_error') {
    setState('server_error');
  } else if (window.__KINGSWAY_SERVER_UNREACHABLE__ || navigator.onLine === false) {
    void checkConnectivity();
  }

  // ---- Public API ------------------------------------------------------

  function initialize() {
    return true; // listeners are attached at load; kept for app_bootstrap
  }

  function subscribe(event, callback) {
    const entry = { event, callback };
    subscribers.add(entry);
    return () => subscribers.delete(entry);
  }

  function emit(event, data) {
    subscribers.forEach((entry) => {
      if (entry.event === event || entry.event === '*') {
        try { entry.callback(data); } catch (error) { console.error(error); }
      }
    });
  }

  function stop() {
    clearTimeout(retryTimer);
    retryTimer = null;
  }

  return {
    initialize,
    checkConnectivity,
    updateStatus: checkConnectivity,
    getStatus: () => state === 'ok',
    isOnline: () => state === 'ok',
    subscribe,
    stop,
  };
})();
window.ConnectivityManager = ConnectivityManager;
