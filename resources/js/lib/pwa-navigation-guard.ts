/**
 * PWA Navigation Guard v2 — menyamakan efek navigasi antara tombol back native
 * mobile / gesture swipe dengan tombol back custom Inertia.
 *
 * Root cause (v1 bug):
 *  Inertia mendaftarkan popstate listener saat module-nya di-import, sebelum
 *  installPwaNavigationGuard() dipanggil. Karena keduanya di bubble phase,
 *  Inertia jalan duluan → page sudah re-render → overlay baru muncul → sia-sia.
 *
 * Fix v2:
 *  1. Daftarkan popstate di CAPTURE phase ({ capture: true }) — dijamin jalan
 *     SEBELUM Inertia's bubble-phase handler.
 *  2. Tampilkan overlay SYNCHRONOUSLY (tanpa rAF) — browser paint overlay
 *     sebelum Inertia me-render ulang konten halaman.
 *  3. Hapus backdrop-filter: blur dari overlay — terlalu berat di mobile GPU.
 *  4. rAF hanya dipakai saat HIDE agar konten baru sempat render dulu.
 *  5. Export `isPopStateNavigation` agar AppLayout bisa skip entry animation
 *     saat back — menghilangkan gadmin/flicker saat native back button ditekan.
 */

let overlay: HTMLDivElement | null = null

/**
 * Flag yang bernilai true selama rentang waktu antara popstate dan
 * inertia:navigate selesai. Dibaca oleh AppLayout untuk skip entry animation
 * pada motion.main saat navigasi native back/forward.
 */
export let isPopStateNavigation = false

function createOverlay(): HTMLDivElement {
  const el = document.createElement("div")
  el.id = "pwa-nav-overlay"
  el.setAttribute("aria-hidden", "true")
  // Tidak ada backdrop-filter: blur — terlalu berat di GPU mobile
  el.style.cssText = [
    "position:fixed",
    "inset:0",
    "z-index:999998",
    "background:rgba(11,14,20,0.72)",
    "opacity:0",
    "pointer-events:none",
  ].join(";")
  document.body.appendChild(el)
  return el
}

function showOverlaySync() {
  if (!overlay) return
  overlay.style.transition = "none"
  overlay.style.opacity = "1"
  overlay.style.pointerEvents = "auto"
}

function hideOverlay() {
  if (!overlay) return
  const el = overlay
  el.style.transition = "opacity 220ms ease"
  el.style.opacity = "0"
  el.style.pointerEvents = "none"
}

/**
 * Inisialisasi guard — panggil sekali di app.tsx (bisa sebelum atau sesudah
 * createInertiaApp karena kita pakai capture phase).
 */
export function installPwaNavigationGuard() {
  if (typeof window === "undefined") return

  const isStandalone =
    window.matchMedia("(display-mode: standalone)").matches ||
    window.matchMedia("(display-mode: fullscreen)").matches ||
    (window.navigator as any).standalone === true

  const isMobileUA = /Mobi|Android|iPhone|iPad/i.test(navigator.userAgent)

  if (!isStandalone && !isMobileUA) return

  // Pre-create overlay saat install
  overlay = createOverlay()

  let fallbackTimer: ReturnType<typeof setTimeout> | null = null

  // ── KUNCI: { capture: true } ──
  // Listener capture phase jalan SEBELUM Inertia's bubble-phase popstate handler.
  window.addEventListener("popstate", () => {
    // Set flag SEBELUM React re-render — AppLayout akan membaca ini
    // saat motion.main re-mount dan skip entry animation
    isPopStateNavigation = true

    // Tampilkan overlay synchronously
    showOverlaySync()

    if (fallbackTimer) clearTimeout(fallbackTimer)
    fallbackTimer = setTimeout(() => {
      isPopStateNavigation = false
      hideOverlay()
      fallbackTimer = null
    }, 800)
  }, { capture: true })

  // Inertia v2: event "inertia:navigate" dispatch ke document setelah page swap
  document.addEventListener("inertia:navigate", () => {
    if (fallbackTimer) {
      clearTimeout(fallbackTimer)
      fallbackTimer = null
    }
    // rAF: konten baru sempat di-paint sebelum overlay fade-out dimulai
    requestAnimationFrame(() => {
      // Reset flag setelah React sudah render dengan konten baru
      isPopStateNavigation = false
      hideOverlay()
    })
  })
}
