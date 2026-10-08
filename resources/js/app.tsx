import "./bootstrap"
import "../css/app.css"
import { createRoot } from "react-dom/client"
import { createInertiaApp, router, usePage } from "@inertiajs/react"
import { resolvePageComponent } from "laravel-vite-plugin/inertia-helpers"
import { useEffect } from "react"
import type { ComponentType } from "react"
import type { PageProps } from "@inertiajs/core"
import { ThemeProvider } from "./context/ThemeContext"
import { LanguageProvider } from "./lib/i18n"
import { ErrorBoundary } from "./components/ui/error-boundary"
import { syncAccentFromStorage } from "./lib/theme"
import { installClientErrorReporting, reportClientError } from "./lib/client-error"
import { installPwaNavigationGuard } from "./lib/pwa-navigation-guard"

// Terapkan tema tersimpan SEBELUM render pertama (cegah flash putih)
syncAccentFromStorage()

// Pasang global handler uncaught exception / unhandled rejection
installClientErrorReporting()

// Samakan efek navigasi back native mobile dengan tombol back custom Inertia
installPwaNavigationGuard()

// Registrasi service worker (PWA + Push Notification).
// sw.js sudah include push handler — satu SW untuk semua.
if ("serviceWorker" in navigator) {
  window.addEventListener("load", async () => {
    try {
      // Unregister sw-push.js lama (jika ada) agar tidak konflik
      const regs = await navigator.serviceWorker.getRegistrations()
      for (const reg of regs) {
        if (reg.active?.scriptURL?.includes("sw-push.js")) {
          await reg.unregister()
        }
      }
      // Daftarkan sw.js utama (berisi cache + push handler)
      const reg = await navigator.serviceWorker.register("/sw.js", { scope: "/" })
      // Paksa update jika ada versi baru
      reg.update().catch(() => {})
    } catch {
      // SW error tidak menghentikan app
    }
  })
}

interface CustomPageProps extends PageProps {
  [key: string]: unknown
}

/** Sync <meta csrf-token> dengan token terbaru dari server. */
function syncCsrfMeta(token: string) {
  if (!token) return
  let meta = document.querySelector('meta[name="csrf-token"]')
  if (!meta) {
    meta = document.createElement("meta")
    meta.setAttribute("name", "csrf-token")

    document.head.appendChild(meta)
  }
  meta.setAttribute("content", token)
}

router.on("success", (event) => {
  const props = event.detail.page.props as Record<string, unknown>
  const token = props.csrf_token as string | undefined
  if (token) syncCsrfMeta(token)
  syncBrandMeta(props)
})

// Tangkap semua respon invalid / non-Inertia: cegah modal Whoops muncul ke pengguna, dan laporkan error ke server
router.on("invalid", (event) => {
  event.preventDefault()
  const status = event.detail.response?.status
  if (status === 419) {
    window.location.reload()
    return
  }
  // Laporkan HTTP error non-Inertia ke superadmin tanpa mengganggu user
  reportClientError(`Server responded with non-Inertia status ${status || 'unknown'}`, null, window.location.pathname)
})

router.on("exception" as any, (event: any) => {
  const err = event?.detail?.error
  console.warn("[Inertia Network Exception]:", err?.message || err)
  if (typeof event?.preventDefault === "function") {
    event.preventDefault()
  }
})

router.on("error", (event) => {
  const resp = (event.detail as any)?.response
  const status = (event.detail as any)?.statusCode ?? resp?.status
  if (status === 419 || status === 401) {
    const href = window.location.href
    const isSuperadmin = href.includes("/superadmin")
    const isPortal = href.includes("/portal")
    const isMobile = href.includes("/mobile")
    let login = "/login"
    if (isMobile) {
      // /mobile/login/{slug} — slug dari halaman saat ini
      const m = href.match(/\/mobile\/([^/?]+)/)
      login = m ? `/mobile/login/${m[1]}` : "/login"
    } else if (isPortal) {
      login = "/portal/login"
    } else if (isSuperadmin) {
      login = "/nodera/superadmin/login"
    }
    event.preventDefault()
    window.location.href = login + (href.includes("?") ? "&" : "?") + "expired=1"
  }
})

/** Sync brand favicon and title suffix dynamically with active tenant */
function syncBrandMeta(props: Record<string, unknown>) {
  if (!props) return
  const brandName = (props.companyName as string) || (props.tenantName as string) || ""
  const brandLogo = (props.tenantLogo as string) || ((props.company as Record<string, unknown>)?.logo as string) || ""

  // 1. Update favicon if custom tenant logo exists
  if (brandLogo) {
    let iconLink = document.querySelector<HTMLLinkElement>("link[rel~='icon']")
    if (!iconLink) {
      iconLink = document.createElement("link")
      iconLink.rel = "icon"
      document.head.appendChild(iconLink)
    }
    if (iconLink && iconLink.href !== brandLogo && !iconLink.href.endsWith(brandLogo)) {
      iconLink.href = brandLogo
    }

    let appleIcon = document.querySelector<HTMLLinkElement>("link[rel='apple-touch-icon']")
    if (appleIcon && appleIcon.href !== brandLogo && !appleIcon.href.endsWith(brandLogo)) {
      appleIcon.href = brandLogo
    }
  }

  // 2. Suffix brand name on title if set
  if (brandName && brandName !== "NODERA") {
    if (document.title.includes("NODERA")) {
      document.title = document.title.replace(new RegExp("NODERA", "g"), brandName)
    }
  }
}

function CsrfSync() {
  const props = usePage().props as Record<string, unknown>
  const token = (props.csrf_token as string) || ""
  useEffect(() => {
    syncCsrfMeta(token)
    syncBrandMeta(props)
  }, [token, props])
  return null
}

createInertiaApp<CustomPageProps>({
  title: (title: string) => {
    let brand = "NODERA"
    try {
      const p = (window as any)?.__inertiaPage?.props || (window as any)?.__inertiaProps
      if (p?.companyName || p?.tenantName) {
        brand = p.companyName || p.tenantName
      }
    } catch {}
    return title ? `${title} — ${brand}` : brand
  },
  resolve: async (name: string) => {
    const pages = import.meta.glob("./pages/**/*.tsx")
    const pageKey = `./pages/${name}.tsx`
    let importFn = pages[pageKey]
    if (!importFn) {
      const foundKey = Object.keys(pages).find(k => k.toLowerCase() === pageKey.toLowerCase())
      if (foundKey) {
        importFn = pages[foundKey]
      }
    }
    if (!importFn) {
      if (typeof window !== "undefined") {
        const reloadKey = "nodera_stale_chunk_reload"
        const lastReload = Number(sessionStorage.getItem(reloadKey) || "0")
        if (Date.now() - lastReload > 8000) {
          sessionStorage.setItem(reloadKey, String(Date.now()))
          if ("caches" in window) {
            try {
              const keys = await caches.keys()
              await Promise.all(keys.map(k => caches.delete(k)))
            } catch {}
          }
          window.location.reload()
          return new Promise(() => {})
        }
      }
      throw new Error(`Page not found: ${name}`)
    }

    const loadModule = async () => {
      try {
        const mod: any = await importFn()
        if (mod && (mod.default || typeof mod === "function" || typeof mod.render === "function")) {
          return mod.default ? mod : { default: mod }
        }
      } catch {}
      return null
    }

    let result = await loadModule()

    // Retry sekali jika gagal karena network drop sesaat atau evaluasi module kosong
    if (!result) {
      await new Promise(r => setTimeout(r, 250))
      result = await loadModule()
    }

    if (result) {
      return result
    }

    // Jika masih gagal setelah retry, coba bersihkan cache & reload jika chunk usang
    if (typeof window !== "undefined") {
      const reloadKey = "nodera_stale_chunk_reload"
      const lastReload = Number(sessionStorage.getItem(reloadKey) || "0")
      if (Date.now() - lastReload > 8000) {
        sessionStorage.setItem(reloadKey, String(Date.now()))
        if ("caches" in window) {
          try {
            const keys = await caches.keys()
            await Promise.all(keys.map(k => caches.delete(k)))
          } catch {}
        }
        window.location.reload()
        return new Promise(() => {})
      }
    }

    throw new Error(`Module chunk returned empty or undefined for ${name}`)
  },
  setup({ el, App, props }: { el: HTMLElement | null; App: any; props: any }) {
    if (!el) return
    const root = createRoot(el)
    root.render(
      <ThemeProvider>
        <LanguageProvider>
          <ErrorBoundary>
            <App {...props} />
          </ErrorBoundary>
        </LanguageProvider>
      </ThemeProvider>
    )

    // Instant Splash Dismissal — Langsung hilangkan splash screen begitu React siap
    const splash = document.getElementById("pwa-splash")
    if (splash) {
      splash.style.opacity = "0"
      splash.style.pointerEvents = "none"
      try { sessionStorage.setItem("nodera_splash_seen", "1") } catch {}
      setTimeout(() => {
        if (splash.parentNode) splash.remove()
      }, 150)
    }
  },
  progress: false,
})

/**
 * Idle prefetch — setelah app mount dan idle, download chunk halaman yang
 * paling sering dikunjungi sesuai role saat ini. Ini menghilangkan delay
 * "beberapa detik" saat pertama kali klik navigasi ke halaman tersebut.
 */
function scheduleIdlePrefetch() {
  const path = window.location.pathname
  let targets: (() => Promise<any>)[] = []

  if (path.startsWith("/admin") || path === "/dashboard" || path === "/login") {
    targets = [
      () => import("./pages/Admin/Dashboard"),
      () => import("./pages/Admin/Map"),
      () => import("./pages/Admin/Customers"),
      () => import("./pages/Admin/Invoices"),
      () => import("./pages/Admin/MikrotikRouters"),
      () => import("./pages/Admin/Voucher/List"),
      () => import("./pages/Admin/Pppoe"),
      () => import("./pages/Admin/TopBandwidth"),
    ]
  } else if (path.startsWith("/kolektor")) {
    targets = [
      () => import("./pages/Collector/Dashboard"),
    ]
  } else if (path.startsWith("/teknisi")) {
    targets = [
      () => import("./pages/Technician/Dashboard"),
    ]
  } else if (path.startsWith("/portal")) {
    targets = [
      () => import("./pages/Portal/Dashboard"),
      () => import("./pages/Portal/Invoices"),
    ]
  }

  if (!targets.length) return

  // Sequential prefetch saat browser idle — tidak ganggu user interaction
  let i = 0
  const next = () => {
    if (i >= targets.length) return
    targets[i++]().catch(() => {}).then(next)
  }

  if ("requestIdleCallback" in window) {
    (window as any).requestIdleCallback(() => next(), { timeout: 8000 })
  } else {
    setTimeout(next, 3000)
  }
}

// Jalankan idle prefetch setelah app siap
if (typeof window !== "undefined") {
  if (document.readyState === "complete") {
    scheduleIdlePrefetch()
  } else {
    window.addEventListener("load", scheduleIdlePrefetch, { once: true })
  }
}