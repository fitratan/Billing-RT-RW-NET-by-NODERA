/* Service Worker for Web Push & FCM Notifications */
self.addEventListener('push', function (event) {
  if (!event.data) return

  let data = {}
  try {
    data = event.data.json()
  } catch (e) {
    data = { title: 'Notifikasi Nodera', body: event.data.text() }
  }

  const title = data.title || data.notification?.title || 'Notifikasi Nodera'
  const options = {
    body: data.body || data.notification?.body || '',
    icon: '/images/logo.png',
    badge: '/images/logo.png',
    vibrate: [100, 50, 100],
    data: data.data || data,
  }

  event.waitUntil(self.registration.showNotification(title, options))
})

self.addEventListener('notificationclick', function (event) {
  event.notification.close()

  const targetUrl = (event.notification.data && event.notification.data.url) ? event.notification.data.url : '/'

  event.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (clientList) {
      for (let i = 0; i < clientList.length; i++) {
        const client = clientList[i]
        if (client.url && 'focus' in client) {
          return client.focus()
        }
      }
      if (clients.openWindow) {
        return clients.openWindow(targetUrl)
      }
    })
  )
})
