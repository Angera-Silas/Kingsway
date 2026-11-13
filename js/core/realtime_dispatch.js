/**
 * Single normalization point for realtime changes.
 *
 * Why this file exists
 * --------------------
 * The Node SSE stream (js/core/realtime_sse.js) is the only realtime
 * transport. It parses frames and calls `dispatch()`; this module alone
 * decides what a change does: invalidate cached keys, schedule one refresh,
 * patch a row, or route presence, conflict and job events. Keeping that here
 * stops a second listener (for example on `kingsway:data-mutated`) from
 * re-scheduling work that has already been scheduled.
 *
 * The contract here
 * -----------------
 * Transports call `RealtimeDispatch.dispatch(...)` and NOTHING else. This module
 * is the only place that decides whether a change refreshes the page.
 *
 * `APIRealtime` (js/api.js) owns the refresh scheduler and the patch registry
 * only. It never listens to events, so there is no cycle between them.
 */
(function (window) {
  'use strict';

  /** Types that are pure notifications and must never refresh the page. */
  const LIVENESS_TYPES = new Set(['HEARTBEAT']);
  const PRESENCE_TYPES = new Set(['PRESENCE']);
  const CONFLICT_TYPES = new Set(['CONFLICT_HINT']);
  const JOB_TYPES = new Set(['JOB_PROGRESS', 'JOB_COMPLETE', 'JOB_FAILED']);
  const ROW_PATCH_TYPES = new Set(['ROW_UPDATED', 'ROW_DELETED']);

  function emit(name, detail) {
    window.dispatchEvent(new CustomEvent(name, { detail }));
  }

  function targetsOf(payload) {
    return Array.isArray(payload?.targets) ? payload.targets.filter((t) => typeof t === 'string' && t) : [];
  }

  /**
   * Apply the one correct reaction to a change.
   *
   * @param {object} change
   * @param {string} change.type      e.g. ROW_UPDATED, CACHE_INVALIDATE
   * @param {string} [change.scope]   audience scope
   * @param {object} [change.payload] descriptor body
   * @param {string} [change.source]   which transport delivered it
   * @returns {{refreshed: boolean, patched: boolean, targets: string[]}}
   */
  function dispatch(change = {}) {
    const payload = change.payload || {};
    const type = String(change.type || '').toUpperCase();
    const scope = change.scope || 'all';
    const source = change.source || 'unknown';
    const at = Number.isFinite(change.at) ? change.at : Date.now();
    const targets = targetsOf(payload);
    const detail = { scope, payload, type, at, source };

    // Everyone may observe the raw event; it carries no obligation to act.
    emit('kingsway:realtime', detail);

    if (LIVENESS_TYPES.has(type)) {
      return { refreshed: false, patched: false, targets };
    }

    if (CONFLICT_TYPES.has(type)) {
      // Never auto-refresh: the user is mid-edit on this row and a refresh
      // would discard what they typed. Surface it and let them reconcile.
      emit('kingsway:row-conflict', { ...detail });
      return { refreshed: false, patched: false, targets };
    }

    if (PRESENCE_TYPES.has(type)) {
      emit('kingsway:presence', { ...detail });
      return { refreshed: false, patched: false, targets };
    }

    if (JOB_TYPES.has(type)) {
      emit('kingsway:job', { ...detail });
      // Job widgets subscribe per job; emit the generic event plus a per-job
      // event (any state) and a per-job-per-status event. `kingsway:job:<id>`
      // lets a widget learn about a long-lived job whatever it becomes next;
      // `kingsway:job:<id>:progress|done|failed` lets it react to one state.
      const jobId = payload?.job_id;
      if (jobId !== undefined && jobId !== null && jobId !== '') {
        emit(`kingsway:job:${jobId}`, { ...detail });
        const status = type === 'JOB_PROGRESS' ? 'progress' : (type === 'JOB_FAILED' ? 'failed' : 'done');
        emit(`kingsway:job:${jobId}:${status}`, { ...detail });
      }
      // Job events do not by themselves change cached school data, so they do
      // not refresh anything.
      return { refreshed: false, patched: false, targets };
    }

    if (ROW_PATCH_TYPES.has(type) && window.APIRealtime?.applyPatch) {
      if (window.APIRealtime.applyPatch({ ...payload, type, scope, at, source })) {
        emit('kingsway:row-patched', { ...detail });
        return { refreshed: false, patched: true, targets };
      }
      // No handler claimed the row. Fall through to a full refresh rather than
      // leaving stale data on screen.
    }

    if (targets.length && window.DataStore?.invalidateMany) {
      window.DataStore.invalidateMany(targets).catch((error) => {
        console.warn('[RealtimeDispatch] Cache invalidation failed:', error);
      });
    }
    // One refresh, here. Nothing downstream re-schedules it.
    window.APIRealtime?.schedule?.(targets);

    emit('kingsway:data-mutated', {
      source, scope, targets, events: [{ type, scope, payload }], patched: false,
    });

    return { refreshed: true, patched: false, targets };
  }

  window.RealtimeDispatch = Object.freeze({ dispatch });
})(window);