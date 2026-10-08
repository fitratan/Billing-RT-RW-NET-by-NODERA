// Service worker NODERA — Network-First Strategy with Cache Fallback & Web Push
const CACHE = "nodera-obsidian-v142"

self.addEventListener("install", (event) => {
  self.skipWaiting()
})

self.addEventListener("activate", (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(
        keys.map((k) => {
          if (k !== CACHE) {
            return caches.delete(k)
          }
        })
      )
    )
  )
  self.clients.claim()
})

// ============================================================
// PUSH NOTIFICATION HANDLER
// ============================================================
self.addEventListener("push", function (event) {
  if (!event.data) return

  let data = {}
  try {
    data = event.data.json()
  } catch (e) {
    data = { title: "Notifikasi NODERA", body: event.data.text() }
  }

  const title = data.title || (data.notification && data.notification.title) || "Notifikasi NODERA"
  const body = data.body || (data.notification && data.notification.body) || ""
  const icon = data.icon || "/images/logo.png"
  const url = (data.data && data.data.url) || data.url || "/"

  const options = {
    body,
    icon,
    badge: "/images/logo.png",
    vibrate: [100, 50, 100],
    tag: data.tag || "nodera-notif",
    renotify: true,
    requireInteraction: false,
    data: { url },
  }

  event.waitUntil(self.registration.showNotification(title, options))
})

self.addEventListener("notificationclick", function (event) {
  event.notification.close()

  const targetUrl =
    event.notification.data && event.notification.data.url
      ? event.notification.data.url
      : "/"

  event.waitUntil(
    clients.matchAll({ type: "window", includeUncontrolled: true }).then(function (clientList) {
      for (let i = 0; i < clientList.length; i++) {
        const client = clientList[i]
        if (client.url && "focus" in client) {
          client.navigate(targetUrl)
          return client.focus()
        }
      }
      if (clients.openWindow) {
        return clients.openWindow(targetUrl)
      }
    })
  )
})

// ============================================================
// NETWORK-FIRST CACHE STRATEGY (Always fetch fresh build assets)
// ============================================================
self.addEventListener("fetch", (event) => {
  const req = event.request
  if (req.method !== "GET") return

  const url = new URL(req.url)
  if (url.origin !== self.location.origin) return

  // HTML page navigation & Inertia requests: ALWAYS Network-Only (Prevent stale data in PWA)
  if (
    req.mode === "navigate" ||
    req.headers.get("x-inertia") === "true" ||
    req.headers.get("X-Inertia") === "true" ||
    (req.headers.get("accept") && req.headers.get("accept").includes("text/html"))
  ) {
    event.respondWith(
      fetch(req).catch(() => {
        return caches.match(req).then((cached) => cached || caches.match("/"))
      })
    )
    return
  }

  // For /build/ assets: Network-First to guarantee latest deployment chunks
  if (url.pathname.startsWith('/build/')) {
    event.respondWith(
      fetch(req)
        .then((res) => {
          if (res && res.status === 200) {
            const copy = res.clone()
            caches.open(CACHE).then((c) => c.put(req, copy))
          }
          return res
        })
        .catch(() => {
          return caches.match(req).then((fallback) => {
            if (fallback) return fallback
            // Jangan kembalikan response kosong sintetis (bisa merusak dynamic import JS)
            return Promise.reject(new Error('Network error loading chunk ' + url.pathname))
          })
        })
    )
    return
  }

  // Assets (JS, CSS, images): Network first, then cache, fallback to cache
  const isAsset =
    url.pathname.startsWith("/css/") ||
    url.pathname.startsWith("/images/") ||
    url.pathname.endsWith(".png") ||
    url.pathname.endsWith(".ico") ||
    url.pathname.endsWith(".svg") ||
    url.pathname.endsWith(".woff2") ||
    url.pathname.endsWith(".css") ||
    url.pathname.endsWith(".js")

  if (!isAsset) return

  event.respondWith(
    fetch(req)
      .then((res) => {
        if (res && res.status === 200) {
          const copy = res.clone()
          caches.open(CACHE).then((c) => c.put(req, copy))
        }
        return res
      })
      .catch(() => {
        return caches.match(req)
      })
  )
})