// MIKHMON PWA Service Worker — v10
const CACHE_NAME = 'mikhmon-pwa-v10';
const STATIC_ASSETS = [
  './css/font-awesome/css/font-awesome.min.css',
  './js/jquery.min.js',
  './js/pace.min.js',
  './img/favicon.png',
  './img/icon-192.png',
  './img/icon-512.png',
  './manifest.json?v=4',
  './manifest-buy.json?v=4',
  './manifest-warung.json?v=4'
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => {
      return Promise.allSettled(
        STATIC_ASSETS.map((asset) =>
          fetch(asset).then((response) => {
            if (response.ok) {
              return cache.put(asset, response);
            }
          }).catch((err) => {
            console.warn('Could not cache asset:', asset, err);
          })
        )
      );
    })
  );
  self.skipWaiting();
});

self.addEventListener('message', (event) => {
  if (event.data && (event.data === 'skipWaiting' || event.data.action === 'skipWaiting')) {
    self.skipWaiting();
  }
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) => {
      return Promise.all(
        keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key))
      );
    })
  );
  return self.clients.claim();
});

self.addEventListener('fetch', (event) => {
  const url = new URL(event.request.url);

  // Network-First for ALL HTML documents, navigation requests, PHP endpoints, APIs, and query strings
  const isDocOrDynamic =
    event.request.method !== 'GET' ||
    event.request.mode === 'navigate' ||
    event.request.destination === 'document' ||
    url.pathname.endsWith('.php') ||
    url.pathname.endsWith('/') ||
    url.search.length > 0 ||
    url.pathname.includes('/api/') ||
    url.pathname.includes('/status/') ||
    url.pathname.includes('/settings/') ||
    url.pathname.includes('/report/') ||
    url.pathname.includes('/hotspot/') ||
    url.pathname.includes('/voucher/');

  if (isDocOrDynamic) {
    event.respondWith(
      fetch(event.request, { cache: 'no-cache' }).catch(() => {
        return caches.match(event.request);
      })
    );
    return;
  }

  // Network-First with Cache fallback for static assets to ensure latest updates
  event.respondWith(
    fetch(event.request).then((response) => {
      if (response && response.status === 200 && response.type === 'basic') {
        const responseToCache = response.clone();
        caches.open(CACHE_NAME).then((cache) => {
          cache.put(event.request, responseToCache);
        });
      }
      return response;
    }).catch(() => {
      return caches.match(event.request);
    })
  );
});
