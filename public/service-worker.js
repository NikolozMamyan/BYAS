const CACHE_VERSION = 'byas-v3';
const STATIC_CACHE = `${CACHE_VERSION}-static`;
const NAVIGATION_CACHE = `${CACHE_VERSION}-navigation`;
const STATIC_ASSETS = [
  '/',
  '/manifest.webmanifest',
  '/icons/icon-192.png',
  '/icons/icon-512.png',
  '/icons/maskable-512.png',
  '/screenshots/pwa-home-narrow.png',
  '/screenshots/pwa-passport-wide.png',
];
const KEY_SCREEN_PREFIXES = [
  '/app/passport',
  '/app/leaderboard',
  '/app/play-history',
];

function isKeyScreenPath(pathname) {
  return KEY_SCREEN_PREFIXES.some((prefix) => pathname.startsWith(prefix));
}

function shouldBypassCache(pathname) {
  return pathname === '/app/notifications/panel';
}

async function refreshNavigationCache(request) {
  const response = await fetch(request);

  if (response && response.ok) {
    const cache = await caches.open(NAVIGATION_CACHE);
    await cache.put(request, response.clone());
  }

  return response;
}

async function staleWhileRevalidateNavigation(event) {
  const cachedResponse = await caches.match(event.request, { cacheName: NAVIGATION_CACHE });
  const networkUpdate = refreshNavigationCache(event.request)
    .then((response) => {
      event.waitUntil(Promise.resolve(response));
      return response;
    })
    .catch(() => null);

  if (cachedResponse) {
    event.waitUntil(networkUpdate);
    return cachedResponse;
  }

  const freshResponse = await networkUpdate;
  return freshResponse || caches.match('/');
}

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(STATIC_CACHE).then((cache) => cache.addAll(STATIC_ASSETS))
  );

  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(
        keys
          .filter((key) => ![STATIC_CACHE, NAVIGATION_CACHE].includes(key))
          .map((key) => caches.delete(key))
      )
    )
  );

  self.clients.claim();
});

self.addEventListener('fetch', (event) => {
  const { request } = event;

  if (request.method !== 'GET') {
    return;
  }

  const url = new URL(request.url);

  if (url.origin !== self.location.origin) {
    return;
  }

  if (shouldBypassCache(url.pathname)) {
    event.respondWith(fetch(request));
    return;
  }

  if (request.mode === 'navigate') {
    if (isKeyScreenPath(url.pathname)) {
      event.respondWith(staleWhileRevalidateNavigation(event));
      return;
    }

    event.respondWith(
      fetch(request)
        .then(async (response) => {
          if (response && response.ok) {
            const cache = await caches.open(NAVIGATION_CACHE);
            await cache.put(request, response.clone());
          }

          return response;
        })
        .catch(() => caches.match(request, { cacheName: NAVIGATION_CACHE }).then((cached) => cached || caches.match('/')))
    );

    return;
  }

  if (
    url.pathname.startsWith('/assets/')
    || request.destination === 'script'
    || request.destination === 'style'
    || request.destination === 'worker'
    || request.destination === 'font'
    || request.destination === 'image'
  ) {
    event.respondWith(
      caches.match(request, { cacheName: STATIC_CACHE }).then((cachedResponse) => {
        const networkResponse = fetch(request)
          .then(async (response) => {
            if (response && response.ok) {
              const cache = await caches.open(STATIC_CACHE);
              await cache.put(request, response.clone());
            }

            return response;
          })
          .catch(() => cachedResponse);

        return cachedResponse || networkResponse;
      })
    );

    return;
  }

  event.respondWith(
    caches.match(request).then((cachedResponse) => {
      if (cachedResponse) {
        return cachedResponse;
      }

      return fetch(request).then(async (networkResponse) => {
        if (networkResponse && networkResponse.ok) {
          const cache = await caches.open(STATIC_CACHE);
          await cache.put(request, networkResponse.clone());
        }

        return networkResponse;
      });
    })
  );
});
