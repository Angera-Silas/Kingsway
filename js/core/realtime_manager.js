/**
 * RealtimeManager - static-first role-scoped real-time event delivery.
 *
 * The real-time engine writes role-scoped event buffers to static JSON files
 * with unguessable HMAC slugs (api/services/EventBroadcaster) that Apache
 * serves directly with zero PHP. This manager:
 *
 *   1. Authenticates once per page load to know the current user's roles and
 *      receive the signed buffer URL(s) it is authorized to poll.
 *      GET /api/realtime/my-buffer  (role-scoped handshake; returns paths only)
 *   2. Handles the buffer URL(s) to the Service Worker, which then re-polls
 *      them on a jittered ~12–18s cadence with fetch({cache:'no-store'}) —
 *      again zero PHP, without synchronizing every browser on one instant.
 *   3. Relays buffer changes it receives from the Service Worker as
 *      window "kingsway:realtime" CustomEvents and mirrors them over a
 *      BroadcastChannel so every tab in the same origin reacts without each
 *      tab hammering PHP.
 *
 * This keeps the polling loop entirely off PHP (static file reads), avoiding
 * the thundering-herd that ~1000 concurrent users polling a PHP endpoint would
 * create on HostAfrica's limited PHP process pool.
 */
const RealtimeManager = (() => {
  'use strict';

  const CHANNEL_NAME = 'kingsway-realtime';
  const POLL_ENABLED = true;

  let initialized = false;
  let registeredScopeUrls = [];
  let channel = null;
  const lastSeenByScope = new Map();

  try {
    if (typeof BroadcastChannel !== 'undefined') channel = new BroadcastChannel(CHANNEL_NAME);
  } catch (ignored) {
    channel = null;
  }

  /**
   * Emit a real-time payload to this tab as a CustomEvent and re-broadcast it
   * over the shared channel so sibling tabs receive it locally even if they
   * are not the tab that owns the Service Worker's polling.
   */
  function emit(scope, payload) {
    const detail = { scope, payload, at: Date.now() };
    window.dispatchEvent(new CustomEvent('kingsway:realtime', { detail }));
    applyInvalidations(scope, payload);
  }

  function applyInvalidations(scope, payload) {
    const events = Array.isArray(payload?.events) ? payload.events : [];
    const previousId = Number(lastSeenByScope.get(scope) || 0);
    const freshEvents = events.filter((event) => Number(event?.id || 0) > previousId);
    const latestId = Number(payload?.latest_id || 0);
    if (latestId > previousId) lastSeenByScope.set(scope, latestId);
    if (!freshEvents.length) return;

    const targets = [...new Set(freshEvents.flatMap((event) =>
      Array.isArray(event?.payload?.targets) ? event.payload.targets : [event?.domain]
    ).filter((target) => typeof target === 'string' && target))];

    if (targets.length && window.DataStore?.invalidateMany) {
      window.DataStore.invalidateMany(targets).catch((error) => {
        console.warn('[RealtimeManager] Cache invalidation failed:', error);
      });
    }
    window.APIRealtime?.schedule?.(targets);
    window.dispatchEvent(new CustomEvent('kingsway:data-mutated', {
      detail: { source: 'realtime', scope, targets, events: freshEvents },
    }));
  }

  /**
   * Start the manager. Safe to call repeatedly.
   */
  async function init() {
    if (initialized) return;
    initialized = true;
    if (!POLL_ENABLED) return;

    // The ServiceWorkerManager registers the worker; real-time polling needs it.
    try {
      await window.ServiceWorkerManager?.initialize?.();
    } catch (ignored) {
      // Continue even if SW registration fails — the manager simply won't poll
      // this tab (other tabs or a later registration may still deliver events).
    }

    // If a worker already controls the page, register buffers now. Otherwise
    // wait until a worker takes control on first install.
    if (navigator.serviceWorker?.controller) {
      registerBuffers();
    } else {
      navigator.serviceWorker?.addEventListener('controllerchange', registerBuffers, { once: true });
    }

    // Relay buffer polls produced by the Service Worker in this tab.
    navigator.serviceWorker?.addEventListener('message', onServiceWorkerMessage);

    // Relay events pushed over the channel from sibling tabs.
    if (channel) channel.onmessage = (event) => {
      const data = event.data || {};
      if (data?.type === 'EVENT') {
        window.dispatchEvent(new CustomEvent('kingsway:realtime', {
          detail: { scope: data.scope, payload: data.payload, at: data.at || Date.now() },
        }));
        applyInvalidations(data.scope || 'all', data.payload);
      }
    };

    // Whenever a hidden tab becomes visible, prompt the worker to poll
    // immediately instead of waiting for the next 4s tick.
    document.addEventListener('visibilitychange', () => {
      if (document.visibilityState === 'visible') nudgeWorker();
    });

    // Buffer URLs rotate daily. Refresh the authenticated handshake well
    // before an epoch boundary can leave a long-lived dashboard on an old URL.
    window.setInterval(registerBuffers, 6 * 60 * 60 * 1000);
  }

  /**
   * Authenticated handshake: learn the current user's role-scoped buffer URLs.
   * Returns only paths (never payloads); scope authorization happens server-side.
   */
  async function registerBuffers() {
    let response;
    try {
      response = await window.API?.apiCall('/realtime/my-buffer', 'GET');
    } catch (ignored) {
      return; // Unauthenticated or session error: nothing to poll.
    }
    const buffers = Array.isArray(response?.data?.buffers) ? response.data.buffers : [];
    registeredScopeUrls = buffers.map((b) => b.url).filter((u) => typeof u === 'string' && u);
    const worker = navigator.serviceWorker?.controller;
    if (worker && registeredScopeUrls.length) {
      worker.postMessage({ type: 'REGISTER_BUFFERS', urls: registeredScopeUrls });
    }
  }

  /**
   * Ask the controlling worker to poll buffers right now (used on tab focus).
   */
  function nudgeWorker() {
    const worker = navigator.serviceWorker?.controller;
    if (!worker) return;
    if (registeredScopeUrls.length) {
      worker.postMessage({ type: 'REGISTER_BUFFERS', urls: registeredScopeUrls });
    } else {
      registerBuffers();
    }
  }

  /**
   * Handle messages relayed by the Service Worker. The worker only forwards
   * actual changes (its own diffing prevents duplicate re-delivery) as a
   * BUFFER_POLL result list.
   */
  function onServiceWorkerMessage(event) {
    const data = event.data || {};
    if (data?.type !== 'BUFFER_POLL' || !Array.isArray(data.data)) return;
    for (const item of data.data) {
      if (item?.type === 'UPDATE' && item?.payload) {
        emit(scopeFromUrl(item.url), item.payload);
      }
    }
  }

  /**
   * Derive the scope name from a buffer URL like
   * /Kingsway/buffers/finance_<slug>.json → "finance".
   */
  function scopeFromUrl(url) {
    try {
      const path = new URL(url, window.location.href).pathname;
      const match = path.match(/buffers\/([^_/]+)_/);
      if (match) return match[1];
    } catch (ignored) {
      /* fall through */
    }
    return 'all';
  }

  /**
   * Convenience for feature code: subscribe to real-time events.
   * Returns an unsubscribe function.
   */
  function onEvent(callback) {
    if (typeof callback !== 'function') return () => {};
    const handler = (event) => callback(event.detail);
    window.addEventListener('kingsway:realtime', handler);
    return () => window.removeEventListener('kingsway:realtime', handler);
  }

  function isEnabled() {
    return POLL_ENABLED;
  }

  return {
    initialize: init,
    init,
    onEvent,
    isEnabled,
    getRegisteredUrls: () => registeredScopeUrls.slice(),
  };
})();

window.RealtimeManager = RealtimeManager;
