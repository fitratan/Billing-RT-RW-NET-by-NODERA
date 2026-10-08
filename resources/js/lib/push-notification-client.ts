/**
 * Web Push & FCM Helper for Frontend (Customer, Technician, Collector, Admin)
 * Safe execution: never throws errors, never blocks rendering.
 *
 * Cara kerja:
 * 1. Minta izin notifikasi dari browser
 * 2. Daftarkan service worker (/sw.js) untuk handle push
 * 3. Subscribe ke Push Manager dengan VAPID public key dari server
 * 4. Kirim subscription JSON ke backend untuk disimpan di database
 * 5. Backend bisa kirim push kapan saja, bahkan saat browser/app tertutup
 */
export async function registerPushNotification(saveUrl?: string): Promise<boolean> {
  try {
    if (typeof window === "undefined" || !window) {
      return false
    }

    // Hanya jalan di HTTPS atau localhost
    const isSecure =
      window.location.protocol === "https:" ||
      window.location.hostname === "localhost" ||
      window.location.hostname === "127.0.0.1"

    if (!isSecure) return false

    // Cek dukungan browser
    if (!("Notification" in window) || !("serviceWorker" in navigator)) {
      return false
    }

    // Minta izin notifikasi
    let permission: NotificationPermission = Notification.permission
    if (permission === "default") {
      permission = await Notification.requestPermission().catch(() => "denied" as NotificationPermission)
    }

    if (permission !== "granted") return false

    // Daftarkan service worker utama (sw.js sudah include push handler)
    const reg = await navigator.serviceWorker
      .register("/sw.js", { scope: "/" })
      .catch(() => null)

    if (!reg) return false

    // Tunggu SW aktif
    await navigator.serviceWorker.ready.catch(() => null)

    let token: string | null = null

    // Coba subscribe Web Push (VAPID) jika pushManager tersedia
    if (reg.pushManager) {
      try {
        // Cek apakah sudah punya subscription
        let sub = await reg.pushManager.getSubscription().catch(() => null)

        if (!sub) {
          // Ambil VAPID public key dari server (opsional, jika tidak ada skip saja)
          const vapidResp = await fetch("/api/push/public-key").catch(() => null)
          if (vapidResp && vapidResp.ok) {
            const vapidData = await vapidResp.json().catch(() => ({}))
            const vapidKey = vapidData.publicKey || null
            if (vapidKey) {
              sub = await reg.pushManager
                .subscribe({
                  userVisibleOnly: true,
                  applicationServerKey: urlBase64ToUint8Array(vapidKey) as unknown as BufferSource,
                })
                .catch(() => null)
            }
          }
        }

        if (sub) {
          token = JSON.stringify(sub)
        }
      } catch {
        // Push Manager error, fallback ke device token
      }
    }

    // Fallback: pakai device token yang disimpan di localStorage
    if (!token) {
      token = generateDeviceToken()
    }

    if (!token) return false

    // Kirim ke endpoint unified (backend deteksi role dari session)
    const endpoint = saveUrl || "/api/push/subscribe"
    await fetch(endpoint, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-CSRF-TOKEN": getCsrfToken(),
      },
      body: JSON.stringify({ token }),
    }).catch(() => null)

    return true
  } catch (err) {
    console.warn("Push notification registration skipped:", err)
    return false
  }
}

// ============================================================
// Helpers
// ============================================================

function getCsrfToken(): string {
  try {
    const meta = document.querySelector('meta[name="csrf-token"]')
    return meta ? meta.getAttribute("content") || "" : ""
  } catch {
    return ""
  }
}

function generateDeviceToken(): string {
  try {
    let token = localStorage.getItem("nodera_device_token")
    if (!token) {
      token = "web_" + Math.random().toString(36).substring(2) + Date.now().toString(36)
      localStorage.setItem("nodera_device_token", token)
    }
    return token
  } catch {
    return "web_" + Date.now().toString(36)
  }
}

function urlBase64ToUint8Array(base64String: string): Uint8Array {
  const padding = "=".repeat((4 - (base64String.length % 4)) % 4)
  const base64 = (base64String + padding).replace(/-/g, "+").replace(/_/g, "/")
  const rawData = window.atob(base64)
  const outputArray = new Uint8Array(rawData.length)
  for (let i = 0; i < rawData.length; ++i) {
    outputArray[i] = rawData.charCodeAt(i)
  }
  return outputArray
}
