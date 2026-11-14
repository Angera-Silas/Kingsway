/** Kingsway service worker: safe static caching only. */
// Bump on every SW behaviour change so existing installations flush the old
// worker and its caches.
const CACHE_VERSION = 'v11.0-sse-only';
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

// One failed connection is not "offline". Mobile signal drops, a reset
// connection or a server restart usually clear within a second, and showing
// the offline page for those sent users away from a page that would have
// loaded on the next attempt. Retry briefly first; go offline at once only
// when the device itself reports no network.
const NAVIGATION_RETRY_DELAYS_MS = [700, 1600];

function wait(ms) {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

async function navigate(request) {
  let lastError = null;
  for (let attempt = 0; attempt <= NAVIGATION_RETRY_DELAYS_MS.length; attempt += 1) {
    try {
      // Any HTTP response, including 4xx/5xx, is the server answering: hand it
      // to the browser unchanged. Only a failed connection is retried.
      return await fetch(request, { cache: 'no-store' });
    } catch (error) {
      lastError = error;
      if (self.navigator && self.navigator.onLine === false) break;
      if (attempt < NAVIGATION_RETRY_DELAYS_MS.length) {
        await wait(NAVIGATION_RETRY_DELAYS_MS[attempt]);
      }
    }
  }
  const offline = await caches.match(OFFLINE_URL);
  if (offline) return offline;
  throw lastError || new TypeError('Network unavailable');
}

self.addEventListener('fetch', (event) => {
  const request = event.request;
  const url = new URL(request.url);

  // Never intercept API/auth/session requests or mutations.
  if (request.method !== 'GET' || url.pathname.includes('/api/')) return;
  // Never intercept upload or asset paths — must load fresh from server.
  // Uploads may live under any configured application base path; inspect the
  // URL path instead of assuming a deployment directory.
  if (url.pathname.includes('/uploads/') || url.pathname.includes('/assets/')) return;
  if (url.origin !== self.location.origin) return;

  // Never cache PHP/application navigations. Network only; the offline page is
  // a last resort for a device that genuinely cannot connect. It is served at
  // the requested URL and reloads that same URL once the server answers.
  if (request.mode === 'navigate') {
    event.respondWith(navigate(request));
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
      : caches.keys().then((names) => Promise.all(names.map((name) => caches.delete(name)))));
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
});

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
