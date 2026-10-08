const CACHE_NAME = 'arisan-pwa-v1-{{ $subdomain }}';
const OFFLINE_URL = '/arisan-app/{{ $subdomain }}/offline';

const PRECACHE_ASSETS = [
    '/arisan-app/{{ $subdomain }}/offline',
    '/arisan-app/{{ $subdomain }}/member/login',
    '/arisan-app/{{ $subdomain }}/admin/login',
    'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap',
    'https://cdn.tailwindcss.com',
    '/favicon.png'
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => {
            return cache.addAll(PRECACHE_ASSETS);
        }).then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) => {
            return Promise.all(
                keys.map((key) => {
                    if (key !== CACHE_NAME && key.startsWith('arisan-pwa-')) {
                        return caches.delete(key);
                    }
                })
            );
        }).then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    if (event.request.method !== 'GET') {
        return;
    }

    // Handle HTML page navigation
    if (event.request.mode === 'navigate') {
        event.respondWith(
            fetch(event.request)
                .then((response) => {
                    if (response && response.status === 200) {
                        const responseClone = response.clone();
                        caches.open(CACHE_NAME).then((cache) => {
                            cache.put(event.request, responseClone);
                        });
                    }
                    return response;
                })
                .catch(() => {
                    return caches.match(event.request).then((cachedResponse) => {
                        return cachedResponse || caches.match(OFFLINE_URL);
                    });
                })
        );
        return;
    }

    // Handle static resources (fonts, scripts, images, css)
    event.respondWith(
        caches.match(event.request).then((cachedResponse) => {
            if (cachedResponse) {
                // Return cache immediately, update in background if online
                fetch(event.request).then((networkResponse) => {
                    if (networkResponse && networkResponse.status === 200) {
                        caches.open(CACHE_NAME).then((cache) => {
                            cache.put(event.request, networkResponse);
                        });
                    }
                }).catch(() => {});
                return cachedResponse;
            }

            return fetch(event.request).then((networkResponse) => {
                if (networkResponse && networkResponse.status === 200 && (event.request.url.startsWith('http'))) {
                    const responseClone = networkResponse.clone();
                    caches.open(CACHE_NAME).then((cache) => {
                        cache.put(event.request, responseClone);
                    });
                }
                return networkResponse;
            }).catch(() => {
                // If static request fails and not in cache, return nothing or error
            });
        })
    );
});
