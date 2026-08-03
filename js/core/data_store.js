/**
 * DataStore
 *
 * Coordinates memory cache, IndexedDB and authoritative backend fetches.
 * Cache keys are identifiers only; they are never converted into API paths.
 *
 * ── Per-key TTL Override ──────────────────────────────────────────────
 * Controllers can pass a per-call TTL override via the options object:
 *   DataStore.get('students', { ttl: 300000 })              // 5 minutes
 *   DataStore.get('attendance', { ttl: 15000, strategy: 'network-first' })
 *
 * The default TTL for each key is defined in the `policies` object below
 * (60 s for volatile data, 24 h for reference data, 7 d for long-lived).
 * If no policy matches, MEMORY_TTL (60 s) is used.
 *
 * ── Real‑time / Cross‑tab Updates ─────────────────────────────────────
 *  • BroadcastChannel ('kingsway-cache') — when the same origin fires
 *    CACHE_INVALIDATED, all non‑sending tabs invalidate the listed keys.
 *  • localStorage fallback (kingsway_cache_invalidation) — the same
 *    mechanism via the 'storage' event for browsers without BroadcastChannel.
 *  • Subscriber pattern — controllers can subscribe to keys and react to
 *    'local' and 'network' source events (see `subscribe()`).
 *
 * There is no WebSocket / SSE integration yet. If real‑time push is added,
 * use the subscriber API to deliver live updates:
 *   DataStore.subscribe('attendance', ({ key, data, source }) => { … });
 */
const DataStore = (() => {
  'use strict';

  const memoryCache = new Map();
  const subscribers = new Map();
  const inFlight = new Map();
  const MEMORY_LIMIT = 100;
  const MEMORY_TTL = 60000;

  const DEFAULT_TTL = Object.freeze({
    REFERENCE: 86400000,
    LONG: 604800000,
    DIRECTORY: 300000,
  });

  const policies = Object.freeze({
    classes: { ttl: DEFAULT_TTL.REFERENCE, strategy: 'stale-while-revalidate' },
    streams: { ttl: DEFAULT_TTL.REFERENCE, strategy: 'stale-while-revalidate' },
    subjects: { ttl: DEFAULT_TTL.REFERENCE, strategy: 'stale-while-revalidate' },
    terms: { ttl: DEFAULT_TTL.REFERENCE, strategy: 'stale-while-revalidate' },
    academic_years: { ttl: DEFAULT_TTL.LONG, strategy: 'stale-while-revalidate' },
    departments: { ttl: DEFAULT_TTL.LONG, strategy: 'stale-while-revalidate' },
    students: { ttl: DEFAULT_TTL.DIRECTORY, strategy: 'network-first' },
    staff: { ttl: 1800000, strategy: 'network-first' },
    attendance: { ttl: 60000, strategy: 'network-first' },
    admissions: { ttl: 60000, strategy: 'network-first' },
    school_profile: { ttl: 3600000, strategy: 'stale-while-revalidate' },
  });

  function normalizeOptions(key, options) {
    if (typeof options === 'string') {
      console.warn('[DataStore] A string storeName is deprecated; pass an options object.');
      options = { storeName: options };
    }
    options = options || {};
    return {
      strategy: options.strategy || policies[key]?.strategy || 'stale-while-revalidate',
      ttl: Number(options.ttl || policies[key]?.ttl || MEMORY_TTL),
      forceRefresh: options.forceRefresh === true,
      storeName: options.storeName || getStoreNameForKey(key),
      endpoint: options.endpoint || null,
      fetcher: typeof options.fetcher === 'function' ? options.fetcher : null,
      params: options.params && typeof options.params === 'object' ? options.params : {},
      useMemory: options.useMemory !== false,
      useIndexedDB: options.useIndexedDB !== false,
      bypassCache: options.bypassCache === true,
      allowStaleOnError: options.allowStaleOnError !== false,
    };
  }

  function generateCacheKey(key, params = {}) {
    return Object.keys(params).length ? `${key}:${JSON.stringify(params)}` : key;
  }

  function getStoreNameForKey(key) {
    const map = {
      classes: 'reference_classes', streams: 'reference_streams',
      subjects: 'reference_subjects', terms: 'reference_terms',
      academic_years: 'reference_academic_years', departments: 'reference_departments',
      students: 'student_directory_cache', staff: 'staff_directory_cache',
      attendance: 'attendance_roster_cache', attendance_roster: 'attendance_roster_cache',
      admissions: 'admission_queue_cache', dashboard_school_admin: 'dashboard_cache',
    };
    return map[key] || 'dashboard_cache';
  }

  function currentUserId() {
    const auth = window.AuthContext;
    return auth?.isAuthenticated?.() ? auth.getUser?.()?.id ?? null : null;
  }

  function currentRoleId() {
    const auth = window.AuthContext;
    const roles = auth?.getRoles?.() || [];
    const first = roles[0];
    return typeof first === 'object' ? first?.id ?? null : first ?? null;
  }

  function isExpired(entry) {
    return Boolean(entry?.expires_at && Date.now() > entry.expires_at);
  }

  function unwrap(record) {
    if (record && typeof record === 'object' && record.data !== undefined) return record.data;
    return record;
  }

  function setMemory(cacheKey, payload, ttl) {
    if (memoryCache.size >= MEMORY_LIMIT && !memoryCache.has(cacheKey)) {
      memoryCache.delete(memoryCache.keys().next().value);
    }
    memoryCache.set(cacheKey, {
      data: payload,
      cached_at: Date.now(),
      expires_at: Date.now() + ttl,
    });
  }

  async function readIndexed(storeName, cacheKey) {
    if (!window.KingswayDB || !storeName) return null;
    try {
      if (typeof KingswayDB.getCached === 'function') {
        return await KingswayDB.getCached(storeName, cacheKey, currentUserId());
      }
      if (typeof KingswayDB.get === 'function') return await KingswayDB.get(storeName, cacheKey);
    } catch (error) {
      console.warn('[DataStore] IndexedDB read failed:', storeName, cacheKey, error);
    }
    return null;
  }

  async function persist(storeName, cacheKey, payload, ttl) {
    if (!window.KingswayDB || !storeName) return;
    try {
      if (typeof KingswayDB.setCached === 'function') {
        await KingswayDB.setCached(
          storeName,
          { id: cacheKey, data: payload },
          ttl,
          currentUserId(),
          currentRoleId(),
        );
      }
    } catch (error) {
      console.warn('[DataStore] IndexedDB write failed:', storeName, cacheKey, error);
    }
  }

  async function peek(key, options = {}) {
    const config = normalizeOptions(key, options);
    const cacheKey = generateCacheKey(key, config.params);

    if (config.useMemory) {
      const entry = memoryCache.get(cacheKey);
      if (entry) return entry.data;
    }

    if (config.useIndexedDB) {
      const record = await readIndexed(config.storeName, cacheKey);
      const payload = unwrap(record);
      if (payload !== null && payload !== undefined) {
        if (config.useMemory) setMemory(cacheKey, payload, config.ttl);
        return payload;
      }
    }
    return null;
  }

  async function performFetch(key, config) {
    if (config.fetcher) return config.fetcher();
    if (!config.endpoint) {
      const error = new Error(`DataStore cache miss for "${key}" and no endpoint/fetcher was supplied.`);
      error.code = 'DATASTORE_NO_SOURCE';
      throw error;
    }
    if (!window.API?.apiCall) throw new Error('Centralized API is unavailable.');
    return window.API.apiCall(config.endpoint, 'GET', null, config.params);
  }

  async function fetchAndCache(key, config) {
    const cacheKey = generateCacheKey(key, config.params);
    if (inFlight.has(cacheKey)) return inFlight.get(cacheKey);

    const task = (async () => {
      const response = await performFetch(key, config);
      const payload = response?.success !== undefined && response?.data !== undefined
        ? response.data
        : response;
      if (payload === null || payload === undefined) {
        throw new Error(`The backend returned no data for ${key}.`);
      }
      if (config.useMemory) setMemory(cacheKey, payload, config.ttl);
      if (config.useIndexedDB) await persist(config.storeName, cacheKey, payload, config.ttl);
      emit(key, { key: cacheKey, data: payload, source: 'network' });
      return payload;
    })();

    inFlight.set(cacheKey, task);
    try { return await task; }
    finally { inFlight.delete(cacheKey); }
  }

  async function revalidate(key, config) {
    if (!config.endpoint && !config.fetcher) return null;
    try { return await fetchAndCache(key, config); }
    catch (error) {
      console.warn('[DataStore] Background revalidation failed:', key, error.message || error);
      return null;
    }
  }

  /**
   * Retrieve a cached value (or fetch from network).
   *
   * @param {string} key                    Cache key (must match a policy entry).
   * @param {object} [options]              Options bag.
   * @param {number} [options.ttl]          Override TTL in ms (default: policy TTL or 60000).
   * @param {string} [options.strategy]     Cache strategy (stale-while-revalidate, cache-first,
   *                                        cache-only, network-first).
   * @param {boolean} [options.forceRefresh] Skip cache and fetch from network.
   * @param {boolean} [options.bypassCache]  Skip memory + IndexedDB entirely.
   * @param {string} [options.endpoint]     API path to fetch (if not using fetcher).
   * @param {function} [options.fetcher]    Async function that returns data.
   * @param {object} [options.params]       Query params appended to endpoint.
   * @param {boolean} [options.useMemory]   Default true.
   * @param {boolean} [options.useIndexedDB] Default true.
   * @param {boolean} [options.allowStaleOnError] Default true — fall back to stale data
   *                                              when the network request fails.
   * @returns {Promise<*|null>}
   */
  async function get(key, options = {}) {
    const config = normalizeOptions(key, options);
    const cacheKey = generateCacheKey(key, config.params);

    if (config.bypassCache || config.forceRefresh) {
      return fetchAndCache(key, config);
    }

    const memoryEntry = config.useMemory ? memoryCache.get(cacheKey) : null;
    const memoryValue = memoryEntry?.data;
    const memoryFresh = memoryEntry && !isExpired(memoryEntry);

    let indexedRecord = null;
    let indexedValue = null;
    let indexedFresh = false;
    if (!memoryEntry && config.useIndexedDB) {
      indexedRecord = await readIndexed(config.storeName, cacheKey);
      indexedValue = unwrap(indexedRecord);
      indexedFresh = indexedRecord && !isExpired(indexedRecord);
      if (indexedValue !== null && indexedValue !== undefined && config.useMemory) {
        setMemory(cacheKey, indexedValue, config.ttl);
      }
    }

    const cachedValue = memoryValue ?? indexedValue;
    const cacheFresh = Boolean(memoryFresh || indexedFresh);

    if (config.strategy === 'cache-only') return cachedValue ?? null;

    if (config.strategy === 'stale-while-revalidate' && cachedValue !== null && cachedValue !== undefined) {
      revalidate(key, config);
      return cachedValue;
    }

    if (config.strategy === 'cache-first' && cacheFresh) return cachedValue;

    try {
      return await fetchAndCache(key, config);
    } catch (error) {
      if (config.allowStaleOnError && cachedValue !== null && cachedValue !== undefined) {
        console.warn('[DataStore] Network failed; returning cached data:', key, error.message || error);
        return cachedValue;
      }
      throw error;
    }
  }

  async function getOrFetch(key, options = {}) {
    const config = normalizeOptions(key, options);
    if (!config.endpoint && !config.fetcher) {
      throw new TypeError(`DataStore.getOrFetch("${key}") requires endpoint or fetcher.`);
    }
    const cached = await peek(key, config);
    if (cached !== null && cached !== undefined && config.strategy === 'stale-while-revalidate' && !config.forceRefresh) {
      revalidate(key, config);
      return { data: cached, source: 'cache', stale: true };
    }
    try {
      const data = await fetchAndCache(key, config);
      return { data, source: 'network', stale: false };
    } catch (networkError) {
      if (cached !== null && cached !== undefined && config.allowStaleOnError) {
        return { data: cached, source: 'cache', stale: true, networkError };
      }
      throw networkError;
    }
  }

  async function fetchPage(key, options = {}) {
    const result = await getOrFetch(key, options);
    return result.data;
  }

  async function set(key, data, options = {}) {
    const config = normalizeOptions(key, options);
    const cacheKey = generateCacheKey(key, config.params);
    if (config.useMemory) setMemory(cacheKey, data, config.ttl);
    if (config.useIndexedDB) await persist(config.storeName, cacheKey, data, config.ttl);
    emit(key, { key: cacheKey, data, source: 'local' });
    return data;
  }

  async function invalidate(key, options = {}) {
    const config = normalizeOptions(key, options);
    const cacheKey = generateCacheKey(key, config.params);
    memoryCache.delete(cacheKey);
    if (window.KingswayDB && config.storeName) {
      try { await KingswayDB.remove?.(config.storeName, cacheKey); }
      catch (error) { console.warn('[DataStore] IndexedDB invalidation failed:', error); }
    }
    emit('INVALIDATED', { key, cacheKey });
  }

  async function invalidateMany(keys = []) {
    for (const key of keys.filter(Boolean)) await invalidate(key);
  }

  async function invalidateRelated(key) {
    const rules = {
      students: ['students', 'student_directory_cache', 'class_list_cache'],
      classes: ['classes', 'student_directory_cache', 'class_list_cache'],
      attendance: ['attendance', 'attendance_roster'],
      admissions: ['admissions', 'admission_queue_cache'],
      staff: ['staff', 'staff_directory_cache'],
    };
    await invalidateMany(rules[key] || []);
  }

  function subscribe(key, callback) {
    if (!subscribers.has(key)) subscribers.set(key, new Set());
    subscribers.get(key).add(callback);
    return () => subscribers.get(key)?.delete(callback);
  }

  function emit(key, data) {
    subscribers.get(key)?.forEach((callback) => {
      try { callback(data); }
      catch (error) { console.error('[DataStore] Subscriber failed:', error); }
    });
  }

  async function clearAll() {
    memoryCache.clear();
    const stores = [
      'student_directory_cache', 'staff_directory_cache', 'class_list_cache',
      'admission_queue_cache', 'attendance_roster_cache', 'dashboard_cache',
    ];
    for (const store of stores) {
      try { await window.KingswayDB?.clear?.(store); }
      catch (error) { console.warn('[DataStore] Failed to clear store:', store, error); }
    }
    emit('CLEARED', {});
  }

  const CACHE_INVALIDATION_KEY = 'kingsway_cache_invalidation';

  function initCrossTabInvalidation() {
    if (typeof BroadcastChannel !== 'undefined') {
      try {
        const channel = new BroadcastChannel('kingsway-cache');
        channel.onmessage = (event) => {
          if (event.data?.type === 'CACHE_INVALIDATED' && Array.isArray(event.data?.keys)) {
            event.data.keys.forEach((key) => invalidate(key));
          }
        };
      } catch (_) {}
    }

    window.addEventListener('storage', (event) => {
      if (event.key === CACHE_INVALIDATION_KEY && event.newValue) {
        try {
          const message = JSON.parse(event.newValue);
          if (message?.type === 'CACHE_INVALIDATED' && Array.isArray(message?.keys)) {
            message.keys.filter((k) => typeof k === 'string').forEach((key) => invalidate(key));
          }
        } catch (_) {}
      }
    });
  }

  initCrossTabInvalidation();

  return {
    get, getOrFetch, fetchPage, peek, set, invalidate, invalidateMany,
    invalidateRelated, subscribe,
    unsubscribeAll: () => subscribers.clear(),
    clearAll,
    getStats: () => ({ memory: { size: memoryCache.size, limit: MEMORY_LIMIT }, inFlight: inFlight.size }),
    DEFAULT_TTL,
  };
})();
window.DataStore = DataStore;
