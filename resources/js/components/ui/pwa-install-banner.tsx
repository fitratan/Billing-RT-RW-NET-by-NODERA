import * as React from "react"
import { AnimatePresence, motion } from "framer-motion"
import { Download, WifiOff, Wifi, Smartphone, X, Share } from "lucide-react"

export function PwaInstallBanner() {
  const [deferredPrompt, setDeferredPrompt] = React.useState<any>(null)
  const [showPwaBanner, setShowPwaBanner] = React.useState(false)
  const [isStandalone, setIsStandalone] = React.useState(false)
  const [isIos, setIsIos] = React.useState(false)

  React.useEffect(() => {
    // Deteksi jika berjalan dalam mode Standalone PWA (sudah terpasang di layar utama)
    const standalone =
      window.matchMedia("(display-mode: standalone)").matches ||
      window.matchMedia("(display-mode: fullscreen)").matches ||
      window.matchMedia("(display-mode: minimal-ui)").matches ||
      (navigator as any).standalone === true ||
      document.referrer.includes("android-app://") ||
      window.location.search.includes("pwa=")
    setIsStandalone(standalone)

    const currentPath = window.location.pathname
    let roleKey = "admin"
    if (currentPath.includes("/kolektor")) roleKey = "kolektor"
    else if (currentPath.includes("/teknisi")) roleKey = "teknisi"
    else if (currentPath.includes("/portal") || currentPath.includes("/pelanggan")) roleKey = "customer"
    else if (currentPath.includes("/cashier")) roleKey = "cashier"

    // Cek status lokal per-role
    const isInstalledLocally =
      localStorage.getItem(`nodera_pwa_installed_${roleKey}`) === "true" ||
      localStorage.getItem("nodera_pwa_installed") === "true"
    const isDismissedLocally =
      localStorage.getItem(`nodera_pwa_dismissed_${roleKey}`) === "true"

    if (standalone || isInstalledLocally || isDismissedLocally) {
      setShowPwaBanner(false)
    }

    // Cek apakah perangkat iOS (Safari)
    const userAgent = window.navigator.userAgent.toLowerCase()
    const ios = /iphone|ipad|ipod/.test(userAgent)
    setIsIos(ios)

    // Deteksi jika aplikasi sudah terpasang via getInstalledRelatedApps
    if ("getInstalledRelatedApps" in navigator) {
      ;(navigator as any).getInstalledRelatedApps().then((relatedApps: any[]) => {
        if (relatedApps && relatedApps.length > 0) {
          localStorage.setItem(`nodera_pwa_installed_${roleKey}`, "true")
          localStorage.setItem("nodera_pwa_installed", "true")
          setShowPwaBanner(false)
        }
      }).catch(() => {})
    }

    // Handler event beforeinstallprompt
    const installPromptHandler = (e: Event) => {
      e.preventDefault()
      setDeferredPrompt(e)
      if (!standalone && !isInstalledLocally && !isDismissedLocally) {
        setShowPwaBanner(true)
      }
    }

    // Handler event ketika PWA berhasil dipasang
    const appInstalledHandler = () => {
      localStorage.setItem(`nodera_pwa_installed_${roleKey}`, "true")
      localStorage.setItem("nodera_pwa_installed", "true")
      setShowPwaBanner(false)
      setDeferredPrompt(null)
    }

    // Khusus iOS Safari (yang tidak mendukung beforeinstallprompt): tampilkan hanya jika belum standalone & belum dismiss/installed
    if (ios && !standalone && !isInstalledLocally && !isDismissedLocally) {
      setShowPwaBanner(true)
    }

    window.addEventListener("beforeinstallprompt", installPromptHandler)
    window.addEventListener("appinstalled", appInstalledHandler)

    return () => {
      window.removeEventListener("beforeinstallprompt", installPromptHandler)
      window.removeEventListener("appinstalled", appInstalledHandler)
    }
  }, [])

  const handleInstallPwa = async () => {
    const currentPath = window.location.pathname
    let roleKey = "admin"
    if (currentPath.includes("/kolektor")) roleKey = "kolektor"
    else if (currentPath.includes("/teknisi")) roleKey = "teknisi"
    else if (currentPath.includes("/portal") || currentPath.includes("/pelanggan")) roleKey = "customer"
    else if (currentPath.includes("/cashier")) roleKey = "cashier"

    if (deferredPrompt) {
      deferredPrompt.prompt()
      const { outcome } = await deferredPrompt.userChoice
      if (outcome === "accepted") {
        localStorage.setItem(`nodera_pwa_installed_${roleKey}`, "true")
        localStorage.setItem("nodera_pwa_installed", "true")
        setShowPwaBanner(false)
      }
      setDeferredPrompt(null)
    } else if (isIos) {
      alert("Untuk memasang di iPhone/iPad:\n1. Tap tombol Share (bagian bawah Safari)\n2. Pilih 'Tambah ke Layar Utama' (Add to Home Screen)")
    } else {
      alert("Untuk memasang aplikasi:\nBuka menu browser (titik 3 di kanan atas) lalu pilih 'Install Aplikasi' atau 'Tambahkan ke Layar Utama'.")
    }
  }

  const dismissPwaBanner = () => {
    const currentPath = window.location.pathname
    let roleKey = "admin"
    if (currentPath.includes("/kolektor")) roleKey = "kolektor"
    else if (currentPath.includes("/teknisi")) roleKey = "teknisi"
    else if (currentPath.includes("/portal") || currentPath.includes("/pelanggan")) roleKey = "customer"
    else if (currentPath.includes("/cashier")) roleKey = "cashier"

    setShowPwaBanner(false)
    localStorage.setItem(`nodera_pwa_dismissed_${roleKey}`, "true")
  }

  // Network offline status
  const [isOffline, setIsOffline] = React.useState(!navigator.onLine)
  const [justReconnected, setJustReconnected] = React.useState(false)

  React.useEffect(() => {
    const handleOffline = () => {
      setIsOffline(true)
      setJustReconnected(false)
    }
    const handleOnline = () => {
      setIsOffline(false)
      setJustReconnected(true)
      setTimeout(() => setJustReconnected(false), 3000)
    }

    window.addEventListener("offline", handleOffline)
    window.addEventListener("online", handleOnline)
    return () => {
      window.removeEventListener("offline", handleOffline)
      window.removeEventListener("online", handleOnline)
    }
  }, [])

  if (isStandalone) return null

  return (
    <AnimatePresence>
      {isOffline ? (
        <motion.div
          key="offline-banner"
          initial={{ opacity: 0, y: -10 }}
          animate={{ opacity: 1, y: 0 }}
          exit={{ opacity: 0, y: -10 }}
          className="mb-4 flex items-center gap-3 rounded-2xl border border-destructive/30 bg-destructive/10 px-4 py-3 text-destructive animate-pulse backdrop-blur-sm"
        >
          <WifiOff className="h-5 w-5 shrink-0" />
          <div className="min-w-0 flex-1 text-xs font-medium leading-normal">
            Koneksi terputus — Anda sedang offline. Beberapa fitur mungkin terbatas sampai koneksi pulih.
          </div>
        </motion.div>
      ) : justReconnected ? (
        <motion.div
          key="online-banner"
          initial={{ opacity: 0, y: -10 }}
          animate={{ opacity: 1, y: 0 }}
          exit={{ opacity: 0, y: -10 }}
          className="mb-4 flex items-center gap-3 rounded-2xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-emerald-500 backdrop-blur-sm"
        >
          <Wifi className="h-5 w-5 shrink-0" />
          <div className="min-w-0 flex-1 text-xs font-medium">
            Koneksi terhubung kembali!
          </div>
        </motion.div>
      ) : null}

      {showPwaBanner ? (
        <motion.div
          key="pwa-install-banner"
          initial={{ opacity: 0, scale: 0.95, y: -8 }}
          animate={{ opacity: 1, scale: 1, y: 0 }}
          exit={{ opacity: 0, scale: 0.95, y: -8 }}
          transition={{ duration: 0.2 }}
          className="mb-5 overflow-hidden rounded-2xl border border-gray-200 bg-white p-4 shadow-xl dark:border-gray-800 dark:bg-gray-900 backdrop-blur-md"
        >
          <div className="flex items-start gap-3.5">
            <div className="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-500 dark:bg-brand-500/10">
              <Smartphone className="h-5 w-5" />
            </div>

            <div className="min-w-0 flex-1">
              <div className="flex items-center justify-between gap-2">
                <h3 className="text-sm font-bold tracking-tight text-gray-900 dark:text-white">
                  Install Aplikasi NODERA
                </h3>
                <button
                  onClick={dismissPwaBanner}
                  className="rounded-lg p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-white/[0.04] dark:hover:text-gray-300 transition-colors cursor-pointer"
                  aria-label="Tutup banner"
                >
                  <X className="h-4 w-4" />
                </button>
              </div>
              <p className="mt-0.5 text-xs text-gray-500 dark:text-gray-400 leading-relaxed">
                {isIos
                  ? "Pasang di iPhone/iPad: Ketuk icon Share di bawah -> 'Tambah ke Layar Utama'."
                  : "Pasang di HP/Desktop untuk akses cepat, stabil, dan nyaman layaknya aplikasi bawaan."}
              </p>

              <div className="mt-3 flex items-center justify-end gap-2">
                <button
                  onClick={dismissPwaBanner}
                  className="inline-flex h-8 items-center justify-center rounded-xl px-3 text-xs font-semibold text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-white/[0.04] dark:hover:text-white transition-colors cursor-pointer"
                >
                  Nanti
                </button>
                <button
                  onClick={handleInstallPwa}
                  className="inline-flex h-8.5 items-center justify-center gap-1.5 rounded-xl bg-brand-500 px-4 text-xs font-bold text-white shadow-sm transition-all hover:bg-brand-600 active:scale-95 cursor-pointer"
                >
                  {isIos ? <Share className="h-3.5 w-3.5" /> : <Download className="h-3.5 w-3.5" />}
                  {isIos ? "Cara Install" : "Install Sekarang"}
                </button>
              </div>
            </div>
          </div>
        </motion.div>
      ) : null}
    </AnimatePresence>
  )
}
