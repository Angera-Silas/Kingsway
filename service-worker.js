/** Kingsway service worker: safe static caching only. */
const CACHE_VERSION = 'v10.4-catalog-image-recovery';
const STATIC_CACHE = `kingsway-static-${CACHE_VERSION}`;
const OFFLINE_URL = './offline.html';
const PRECACHE = [
  './offline.html',
  './css/school-theme.css',
  './css/dashboards.css',
  './king.css',
  './public/vendor/bootstrap/css/bootstrap.min.css',
  './images/favicon/favicon-96x96.png',
  './images/favicon/favicon.svg',
  './images/favicon/favicon.ico'
];

self.addEventListener('install', (event) => {
  event.waitUntil((async () => {
    const cache = await caches.open(STATIC_CACHE);
    await Promise.allSettled(PRECACHE.map((url) => cache.add(url)));
    await self.skipWaiting();
  })());
});

self.addEventListener('activate', (event) => {
  event.waitUntil((async () => {
    const names = await caches.keys();
    await Promise.all(names.filter((name) => name.startsWith('kingsway-') && name !== STATIC_CACHE)
      .map((name) => caches.delete(name)));
    await self.clients.claim();
  })());
});

self.addEventListener('fetch', (event) => {
  const request = event.request;
  const url = new URL(request.url);

  // Never intercept API/auth/session requests or mutations.
  if (request.method !== 'GET' || url.pathname.includes('/api/')) return;
  // Never intercept upload or asset paths — must load fresh from server.
  // The app lives under a subdirectory (e.g. /Kingsway/), so url.pathname
  // is /Kingsway/uploads/..., not /uploads/... — use includes() not startsWith().
  if (url.pathname.includes('/uploads/') || url.pathname.includes('/uploads_backup/') || url.pathname.includes('/assets/')) return;
  if (url.origin !== self.location.origin) return;

  // Never cache PHP/application navigations. Use network and offline fallback only.
  if (request.mode === 'navigate') {
    event.respondWith(fetch(request, { cache: 'no-store' }).catch(async () => {
      return (await caches.match(OFFLINE_URL)) || new Response('Offline', { status: 503 });
    }));
    return;
  }

  // JS and CSS are network-first so deployments cannot execute stale controllers.
  if (/\.(?:js|css)$/i.test(url.pathname)) {
    event.respondWith((async () => {
      try {
        const response = await fetch(request, { cache: 'no-store' });
        return response;
      } catch (_) {
        return (await caches.match(request)) || new Response('Offline', { status: 503 });
      }
    })());
    return;
  }

  // Real-time event buffers are strictly network-first and never cached. The
  // buffers are already governed by no-store headers; this guards the fetch
  // path on the client too so a poll can never be answered from a stale cache.
  if (/\/buffers\/.+\.json$/i.test(url.pathname)) {
    event.respondWith(fetch(request, { cache: 'no-store' }).catch(
      () => new Response('{}', { status: 503, headers: { 'Content-Type': 'application/json' } })
    ));
    return;
  }

  // Cache-first only for immutable visual/font assets.
  if (/\.(?:png|jpe?g|gif|svg|ico|webp|woff2?|ttf|eot)$/i.test(url.pathname)) {
    event.respondWith((async () => {
      try {
        const cached = await caches.match(request);
        if (cached) return cached;
        const response = await fetch(request);
        if (response.ok) {
          const cache = await caches.open(STATIC_CACHE);
          await cache.put(request, response.clone());
        }
        return response;
      } catch (_) {
        return new Response('', { status: 503 });
      }
    })());
  }
});

self.addEventListener('message', (event) => {
  const type = event.data?.type;
  if (type === 'SKIP_WAITING') self.skipWaiting();
  if (type === 'CLEAR_CACHE') {
    event.waitUntil(event.data?.data?.cacheName
      ? caches.delete(event.data.data.cacheName)
      : Promise.all(caches.keys().then((names) => names.map((name) => caches.delete(name)))));
  }
  if (type === 'GET_CACHE_STATS' && event.ports?.[0]) {
    event.waitUntil((async () => {
      const stats = {};
      for (const name of await caches.keys()) {
        stats[name] = { entries: (await (await caches.open(name)).keys()).length };
      }
      event.ports[0].postMessage({ type: 'CACHE_STATS', data: stats });
    })());
  }

  // Role-scoped real-time buffers to start polling (from realtime_manager).
  // Payloads never arrive here — only signed static-buffer URLs. The service
  // worker re-polls them on a jittered 12–18s cycle WITHOUT touching PHP and forwards only
  // actual changes to controlled clients.
  if (type === 'REGISTER_BUFFERS' && Array.isArray(event.data?.urls)) {
    self.__kingswayBuffers = event.data.urls
      .filter((u) => typeof u === 'string' && u.startsWith('http'))
      .map((u) => new URL(u, self.location.origin).href);
    self.__kingswayBufferState = {};
    if (self.__kingswayBuffers.length && !self.__kingswayPollTimer) {
      const schedulePoll = async () => {
        await pollRealTimeBuffers();
        // Jitter spreads clients across the interval instead of producing a
        // synchronized static-file spike after login or page refresh. Fifteen
        // seconds is responsive enough for dashboards without turning 1,000
        // browsers into a shared-hosting denial of service.
        const delay = 12000 + Math.floor(Math.random() * 6001);
        self.__kingswayPollTimer = setTimeout(schedulePoll, delay);
      };
      schedulePoll();
    }
  }
});

// Poll role-scoped static buffers and forward only diffs to clients. Uses
// fetch(no-store) plus the no-store server headers, so each poll returns the
// freshest buffer the web server has written (zero PHP). Clients receive a
// change-detected event with the current scope's payloads.
async function pollRealTimeBuffers() {
  const buffers = self.__kingswayBuffers || [];
  if (!buffers.length) return;
  const results = [];
  await Promise.all(buffers.map(async (href) => {
    try {
      const res = await fetch(href, { cache: 'no-store' });
      if (!res.ok) return;
      const body = await res.text();
      let payload;
      try { payload = JSON.parse(body); } catch { return; }
      const key = href;
      const previous = self.__kingswayBufferState?.[key];
      const signature = body; // raw text diff, not a security boundary
      if (previous !== signature) {
        self.__kingswayBufferState = self.__kingswayBufferState || {};
        self.__kingswayBufferState[key] = signature;
        results.push({ url: href, type: 'UPDATE', payload });
      } else {
        results.push({ url: href, type: 'NO_CHANGE' });
      }
    } catch (ignored) {
      /* transient network error: skip this tick */
    }
  }));

  if (results.length) {
    const clients = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
    for (const client of clients) {
      client.postMessage({ type: 'BUFFER_POLL', data: results });
    }
  }
}

self.addEventListener('push', (event) => {
  const data = (() => { try { return event.data?.json() || {}; } catch { return { body: event.data?.text() }; } })();
  event.waitUntil(self.registration.showNotification(data.title || 'Kingsway Preparatory School', {
    body: data.body || 'New notification',
    icon: './images/favicon/favicon-96x96.png',
    badge: './images/favicon/favicon-96x96.png',
    data: { url: data.url || './home.php' }
  }));
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  event.waitUntil(self.clients.openWindow(event.notification.data?.url || './home.php'));
});
