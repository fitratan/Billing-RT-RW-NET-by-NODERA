import * as React from "react"
import { AnimatePresence, motion } from "framer-motion"
import { usePage, router } from "@inertiajs/react"
import {
  AlertTriangle,
  ArrowLeft,
  CheckCircle2,
  ChevronUp,
  Info,
  RotateCw,
  Settings,
  ShieldAlert,
  X,
} from "lucide-react"
import { ThemeProvider } from "@/context/ThemeContext"
import { SidebarProvider, useSidebar } from "@/context/SidebarContext"
import { AppSidebar, type NavItemConfig } from "@/components/tailadmin/layout/AppSidebar"
import { AppHeader } from "@/components/tailadmin/layout/AppHeader"
import { MobileBottomNav } from "@/components/tailadmin/layout/MobileBottomNav"
import { PwaInstallBanner } from "@/components/ui/pwa-install-banner"
import { SyncOverlay } from "@/components/ui/sync-overlay"
import { ResetPasswordModal, type ResetPasswordData } from "@/components/ui/reset-password-modal"
import { ServiceSettingsSheet } from "./service-settings-sheet"
import { SystemInfoModal } from "./system-info-modal"
import { AutoInvoiceSettingsModal } from "./auto-invoice-settings-modal"
import { isPopStateNavigation } from "@/lib/pwa-navigation-guard"
import { cn } from "@/lib/utils"

export interface SidebarItem {
  href: string
  label: string
  icon: any
  active?: string | string[]
  group?: string
  badge?: string | number
  key?: string
}

export interface NavItem {
  href: string
  label: string
  icon: any
  active?: string | string[]
}

export interface AppLayoutProps {
  children: React.ReactNode
  title?: string
  subtitle?: string
  sidebarItems?: SidebarItem[]
  navItems?: NavItem[]
  onBack?: string
  hideBack?: boolean
  hideHeader?: boolean
  hideSidebar?: boolean
  brand?: { name: string; sub?: string; logo?: string; href?: string }
  fab?: { onClick: () => void; label?: string; actions?: { label: string; icon?: React.ReactNode; onClick: () => void; variant?: "primary" | "default" | "destructive" }[] }
  headerRight?: React.ReactNode
  header?: React.ReactNode
  className?: string
  hideBottomNav?: boolean
  hideFab?: boolean
}

function AppLayoutInner({
  children,
  title,
  subtitle,
  sidebarItems = [],
  navItems = [],
  brand,
  fab,
  onBack,
  hideBack,
  hideHeader,
  hideSidebar,
  headerRight,
  header,
  className,
  hideBottomNav,
  hideFab,
}: AppLayoutProps) {
  const { isExpanded, isHovered, isMobileOpen, setIsMobileOpen } = useSidebar()
  const isFullOpen = isExpanded || isHovered

  const { flash } = usePage().props as {
    flash?: {
      msg?: string | null
      message?: string | null
      success?: string | null
      status?: string | null
      error?: string | null
      info?: string | null
      warning?: string | null
      reset_password_data?: ResetPasswordData | null
    }
  }
  const { url, props } = usePage()
  const appVersion = (props as Record<string, unknown>).appVersion as string | undefined
  const impersonating = Boolean((props as Record<string, unknown>).impersonating)
  const [versionBanner, setVersionBanner] = React.useState<string | null>(null)
  const [resetPwdData, setResetPwdData] = React.useState<ResetPasswordData | null>(null)

  React.useEffect(() => {
    if (flash?.reset_password_data) {
      setResetPwdData(flash.reset_password_data as ResetPasswordData)
    }
  }, [flash?.reset_password_data])

  // Deteksi force-update: kalau versi server beda dari yang pernah disimpan di localStorage
  React.useEffect(() => {
    if (!appVersion) return
    const prev = localStorage.getItem("nodera_app_version")
    if (prev && prev !== appVersion) {
      setVersionBanner(appVersion)
    } else if (!prev) {
      localStorage.setItem("nodera_app_version", appVersion)
    }
  }, [appVersion])

  // Floating Viewport Alert / Toast
  const [activeAlert, setActiveAlert] = React.useState<{
    type: "error" | "success" | "info" | "warning"
    message: string
  } | null>(null)

  // Global Action & Result Modal Overlay
  const [globalLoading, setGlobalLoading] = React.useState(false)
  const [globalLoadingTitle, setGlobalLoadingTitle] = React.useState("Memproses Permintaan")
  const [globalLoadingMessage, setGlobalLoadingMessage] = React.useState("Menyinkronkan data dengan sistem & router...")
  const [resultOverlay, setResultOverlay] = React.useState<{
    type: "success" | "error"
    title: string
    message: string
  } | null>(null)

  // Listen to Inertia router mutations globally
  React.useEffect(() => {
    const unregisterStart = router.on("start", (event) => {
      const method = (event.detail.visit.method || "get").toLowerCase()
      const path = (event.detail.visit.url.pathname || event.detail.visit.url.href || "").toLowerCase()

      // Skip overlay for quick silent toggles, background polls, or OLT syncs
      if (
        path.includes("/auto-isolir") ||
        path.includes("/toggle") ||
        path.includes("/save-notif-settings") ||
        path.includes("/toggle-active") ||
        path.includes("/olt/sync") ||
        (event.detail.visit.only && event.detail.visit.only.length > 0)
      ) {
        return
      }

      // Show overlay for heavy mutating request or explicit action
      if (method !== "get" || path.includes("/generate") || path.includes("/sync") || path.includes("/reload")) {
        let loadTitle = "Memproses Permintaan"
        let msg = "Menyinkronkan data..."

        if (path.includes("/generate")) {
          loadTitle = "Generate Tagihan"
          msg = "Memproses pembuatan tagihan pelanggan..."
        } else if (path.includes("/reset-pin")) {
          loadTitle = "Reset PIN"
          msg = "Mereset PIN akses portal pelanggan..."
        } else if (path.includes("/isolate") || path.includes("/isolir")) {
          loadTitle = "Isolir Layanan"
          msg = "Memproses isolir layanan pelanggan..."
        } else if (path.includes("/unisolate")) {
          loadTitle = "Buka Isolir"
          msg = "Membuka isolir layanan pelanggan..."
        } else if (path.includes("/pppoe/kick")) {
          loadTitle = "Reset Sesi"
          msg = "Memproses reconnect koneksi pelanggan..."
        } else if (path.includes("/pay")) {
          loadTitle = "Pembayaran Tagihan"
          msg = "Menyimpan transaksi pembayaran & update status..."
        } else if (path.includes("/cancel")) {
          loadTitle = "Reset Status Tagihan"
          msg = "Mengembalikan status invoice ke belum bayar..."
        } else if (path.includes("/delete") || path.includes("/destroy")) {
          loadTitle = "Menghapus Data"
          msg = "Menghapus data dari sistem..."
        } else if (path.includes("/sync")) {
          loadTitle = "Sinkronisasi Data"
          msg = "Menyinkronkan data..."
        }

        setGlobalLoadingTitle(loadTitle)
        setGlobalLoadingMessage(msg)
        setGlobalLoading(true)
      }
    })

    const unregisterFinish = router.on("finish", () => {
      setGlobalLoading(false)
    })

    return () => {
      unregisterStart()
      unregisterFinish()
    }
  }, [])

  React.useEffect(() => {
    if (flash?.reset_password_data) {
      setActiveAlert(null)
      return
    }

    const errorMsg = flash?.error
    const successMsg = flash?.msg || flash?.message || flash?.success || flash?.status
    const infoMsg = flash?.info
    const warningMsg = flash?.warning

    if (errorMsg) {
      setActiveAlert({ type: "error", message: errorMsg })
    } else if (successMsg) {
      setActiveAlert({ type: "success", message: successMsg })
    } else if (warningMsg) {
      setActiveAlert({ type: "warning", message: warningMsg })
    } else if (infoMsg) {
      setActiveAlert({ type: "info", message: infoMsg })
    } else {
      setActiveAlert(null)
      return
    }

    const timer = setTimeout(() => {
      setActiveAlert(null)
      setResultOverlay(null)
    }, 4500)

    return () => clearTimeout(timer)
  }, [flash?.error, flash?.msg, flash?.message, flash?.success, flash?.status, flash?.info, flash?.warning, flash?.reset_password_data])

  const handleUpdateNow = () => {
    localStorage.setItem("nodera_app_version", appVersion ?? "")
    window.location.reload()
  }

  const userPermissions = React.useMemo(() => {
    return {
      ...((props as any)?.permissions || {}),
      ...((props as any)?.userPermissions || {}),
    } as Record<string, boolean>
  }, [props])

  const filteredSidebar = React.useMemo(() => {
    const items = sidebarItems || []
    if (!userPermissions || Object.keys(userPermissions).length === 0) return items
    return items.filter((item) => {
      const key = (item as any).key
      if (key && userPermissions[key] === false) return false
      return true
    })
  }, [sidebarItems, userPermissions])

  // Custom sidebar groups if custom sidebarItems provided (only for specialized roles like kolektor/teknisi/kasir/agent)
  const isSpecialRole = React.useMemo(() => {
    const currentUrl = url || ""
    if (
      currentUrl.startsWith("/kolektor") ||
      currentUrl.startsWith("/teknisi") ||
      currentUrl.startsWith("/kasir") ||
      currentUrl.startsWith("/agent") ||
      currentUrl.startsWith("/portal") ||
      currentUrl.startsWith("/arisan")
    ) {
      return true
    }
    const role = String((props as any)?.auth?.user?.role || (props as any)?.role || "").toLowerCase()
    return ["collector", "kolektor", "technician", "teknisi", "cashier", "kasir", "agent"].includes(role)
  }, [url, props])

  const customSidebarGroups = React.useMemo(() => {
    if (!isSpecialRole) {
      return undefined // Let AppSidebar use the canonical hierarchical defaultAdminGroups
    }
    if (!filteredSidebar || filteredSidebar.length === 0) return undefined
    const grouped: Record<string, NavItemConfig[]> = {}
    filteredSidebar.forEach((item) => {
      const groupName = item.group || "MENU"
      if (!grouped[groupName]) grouped[groupName] = []
      grouped[groupName].push({
        name: item.label,
        href: item.href,
        icon: item.icon,
        badge: item.badge,
      })
    })
    return Object.entries(grouped).map(([name, items]) => ({
      name,
      items,
    }))
  }, [filteredSidebar, isSpecialRole])

  const mobileCustomNav = React.useMemo(() => {
    if (!navItems || navItems.length === 0) return undefined
    return navItems.map((item) => ({
      label: item.label,
      href: item.href,
      icon: item.icon,
      active: item.active,
    }))
  }, [navItems])

  const [reloading, setReloading] = React.useState(false)
  const [reloadFlash, setReloadFlash] = React.useState(false)
  const reloadTriggered = React.useRef(false)
  const [showScrollTop, setShowScrollTop] = React.useState(false)
  const rafRef = React.useRef<number | null>(null)

  const handleScroll = React.useCallback(() => {
    if (rafRef.current !== null) return
    rafRef.current = requestAnimationFrame(() => {
      setShowScrollTop(window.scrollY > 300)
      rafRef.current = null
    })
  }, [])

  React.useEffect(() => {
    window.addEventListener("scroll", handleScroll, { passive: true })
    return () => {
      window.removeEventListener("scroll", handleScroll)
      if (rafRef.current !== null) {
        cancelAnimationFrame(rafRef.current)
        rafRef.current = null
      }
    }
  }, [handleScroll])

  const scrollToTop = () => {
    window.scrollTo({ top: 0, behavior: "smooth" })
  }

  React.useEffect(() => {
    const start = () => { if (reloadTriggered.current) setReloading(true) }
    const finish = () => {
      setReloading(false)
      if (reloadTriggered.current) {
        reloadTriggered.current = false
        setReloadFlash(true)
        setTimeout(() => setReloadFlash(false), 1400)
      }
    }
    const offStart = router.on("start", start)
    const offFinish = router.on("finish", finish)
    return () => { offStart(); offFinish() }
  }, [])

  const isAdminPanel = url.startsWith("/admin") || url.startsWith("/dashboard") || url.startsWith("/superadmin")

  const serviceKey: "genieacs" | "whatsapp" | null = url.startsWith("/superadmin")
    ? null
    : url.startsWith("/admin/genieacs")
      ? "genieacs"
      : url.startsWith("/admin/whatsapp-templates") || url.startsWith("/admin/broadcast")
        ? "whatsapp"
        : null

  const [serviceSheet, setServiceSheet] = React.useState<"genieacs" | "whatsapp" | null>(null)
  const [systemInfoOpen, setSystemInfoOpen] = React.useState(false)

  const handleReload = () => {
    if (reloading) return
    reloadTriggered.current = true
    router.reload({ preserveScroll: true } as never)
  }

  const headerRightNode = headerRight ? <>{headerRight}</> : undefined

  return (
    <div className="min-h-screen bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-gray-100 xl:flex">
      {/* Mobile Backdrop Overlay */}
      {isMobileOpen && (
        <div
          className="fixed inset-0 z-40 bg-gray-900/50 backdrop-blur-xs xl:hidden"
          onClick={() => setIsMobileOpen(false)}
        />
      )}

      {/* TailAdmin Canonical Sidebar */}
      {!hideSidebar && (
        <AppSidebar
          brand={brand}
          customItems={customSidebarGroups ? { groups: customSidebarGroups } : undefined}
        />
      )}

      {/* Main Content Area - 1:1 TailAdmin Official Responsive Dimensions */}
      <div
        className={cn(
          "flex flex-1 flex-col min-h-screen min-w-0 transition-all duration-300 ease-in-out bg-gray-50 dark:bg-gray-900",
          hideSidebar
            ? "w-full ml-0 xl:ml-0"
            : isFullOpen
            ? "xl:ml-[290px]"
            : "xl:ml-[90px]"
        )}
      >
        {header ?? (!hideHeader && (
          <AppHeader
            title={title}
            subtitle={subtitle}
            onBack={onBack}
            hideBack={hideBack}
            right={headerRightNode}
          />
        ))}

        {/* Impersonation Banner */}
        {impersonating && (
          <div className="mx-4 mt-4 sm:mx-6 lg:mx-8 flex items-center justify-between gap-3 rounded-2xl border border-amber-500/40 bg-amber-500/10 px-4 py-3 backdrop-blur-md text-amber-900 dark:text-amber-200 shadow-sm">
            <div className="flex items-center gap-2.5 min-w-0">
              <ShieldAlert className="h-5 w-5 shrink-0 text-amber-600 dark:text-amber-400" />
              <div className="min-w-0 text-xs sm:text-sm font-semibold truncate">
                Anda sedang masuk sebagai Administrator Billing
              </div>
            </div>
            <button
              onClick={() => router.post("/superadmin/stop-impersonate")}
              className="inline-flex shrink-0 items-center gap-1.5 rounded-xl bg-amber-600 hover:bg-amber-700 px-3 py-1.5 text-xs font-bold text-white shadow-xs transition-all active:scale-95 cursor-pointer"
            >
              <ArrowLeft className="h-3.5 w-3.5" />
              Kembali
            </button>
          </div>
        )}

        {/* Version Update Banner */}
        {versionBanner && (
          <div className="mx-4 mt-4 sm:mx-6 lg:mx-8 flex items-center justify-between gap-3 rounded-2xl border border-blue-500/30 bg-blue-500/10 px-4 py-3 backdrop-blur-md text-blue-900 dark:text-blue-100 shadow-sm">
            <div className="min-w-0">
              <p className="text-xs sm:text-sm font-bold">Aplikasi Diperbarui</p>
              <p className="text-xs text-blue-700 dark:text-blue-300">Versi baru sudah tersedia. Muat ulang untuk mendapatkan pembaruan terkini.</p>
            </div>
            <button
              onClick={handleUpdateNow}
              className="inline-flex shrink-0 items-center gap-1.5 rounded-xl bg-brand-500 hover:bg-brand-600 px-3.5 py-1.5 text-xs font-bold text-white shadow-xs transition-all active:scale-95 cursor-pointer"
            >
              <RotateCw className="h-3.5 w-3.5" />
              Muat Ulang
            </button>
          </div>
        )}

        {/* PWA Install Banner */}
        <div className="px-4 sm:px-6 lg:px-8 pt-2">
          <PwaInstallBanner />
        </div>

        {/* Main Content Area */}
        <main className={cn("flex-1 p-4 pb-20 xl:pb-6 mx-auto w-full max-w-(--breakpoint-2xl) md:p-6", className)}>
          <motion.div
            key={url.split("?")[0]}
            initial={isPopStateNavigation ? false : { opacity: 0.94, y: 2 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.12, ease: "easeOut" }}
            className="w-full min-w-0"
          >
            {children}
          </motion.div>
        </main>

        {/* Seamless TailAdmin Official Footer */}
        <footer className="mt-auto border-t border-gray-200 dark:border-gray-800 px-4 sm:px-6 lg:px-8 py-4 text-xs text-gray-500 dark:text-gray-400 select-none">
          <div className="mx-auto flex w-full max-w-(--breakpoint-2xl) flex-col sm:flex-row items-center justify-between gap-2.5 text-center sm:text-left">
            <div className="flex flex-wrap items-center justify-center sm:justify-start gap-2">
              <span>© {new Date().getFullYear()} {brand?.name || "NODERA System"}</span>
              <span className="text-gray-300 dark:text-gray-700 hidden sm:inline">•</span>
              <span className="font-mono text-gray-400 dark:text-gray-500">{appVersion || "v2.5.0"}</span>
            </div>
            <div className="flex items-center justify-center sm:justify-end gap-1.5">
              <span>Created by</span>
              <a
                href="https://dgtlnetsolution.com"
                target="_blank"
                rel="noopener noreferrer"
                className="font-bold text-brand-500 hover:text-brand-600 dark:text-brand-400 transition-colors"
              >
                NODERA
              </a>
            </div>
          </div>
        </footer>

        {/* Floating Action Button (FAB) */}
        {!hideFab && fab && (
          <div className="fixed bottom-20 right-4 sm:bottom-8 sm:right-8 z-40">
            <button
              type="button"
              onClick={fab.onClick}
              className="flex h-12 w-12 sm:h-14 sm:w-14 items-center justify-center rounded-full bg-brand-500 hover:bg-brand-600 text-white shadow-xl hover:shadow-2xl transition active:scale-95 cursor-pointer"
              title={fab.label || "Action"}
              aria-label={fab.label || "Action"}
            >
              <span className="text-2xl font-bold leading-none">+</span>
            </button>
          </div>
        )}

        {/* Mobile Bottom Navigation (4-Item Fixed Bar) */}
        {!hideBottomNav && <MobileBottomNav customItems={mobileCustomNav} />}
      </div>

      {/* Sleek Floating Toast Notification */}
      <AnimatePresence>
        {activeAlert && (
          <motion.div
            initial={{ opacity: 0, y: -16, scale: 0.96 }}
            animate={{ opacity: 1, y: 0, scale: 1 }}
            exit={{ opacity: 0, y: -12, scale: 0.95 }}
            transition={{ type: "spring", stiffness: 450, damping: 30 }}
            className="fixed top-4 inset-x-3.5 sm:top-20 sm:right-6 sm:left-auto sm:inset-x-auto sm:max-w-md sm:w-full z-[99999] pointer-events-auto select-none"
          >
            <div
              className={cn(
                "flex items-start gap-3 rounded-2xl p-4 shadow-2xl border backdrop-blur-md",
                activeAlert.type === "success" && "bg-emerald-600 text-white border-emerald-700",
                activeAlert.type === "error" && "bg-rose-600 text-white border-rose-700",
                activeAlert.type === "warning" && "bg-amber-600 text-white border-amber-700",
                activeAlert.type === "info" && "bg-sky-600 text-white border-sky-700"
              )}
            >
              <div className="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-white/20 text-white mt-0.5">
                {activeAlert.type === "success" && <CheckCircle2 className="h-4.5 w-4.5" />}
                {activeAlert.type === "error" && <ShieldAlert className="h-4.5 w-4.5" />}
                {activeAlert.type === "warning" && <AlertTriangle className="h-4.5 w-4.5" />}
                {activeAlert.type === "info" && <Info className="h-4.5 w-4.5" />}
              </div>

              <div className="flex-1 min-w-0 pr-1">
                <h4 className="text-xs font-bold text-white tracking-tight">
                  {activeAlert.type === "success"
                    ? "Berhasil"
                    : activeAlert.type === "error"
                    ? "Pemberitahuan Sistem"
                    : activeAlert.type === "warning"
                    ? "Peringatan"
                    : "Informasi"}
                </h4>
                <div
                  className="text-xs text-white/90 leading-relaxed mt-0.5 max-h-36 overflow-y-auto font-medium break-words [&_strong]:font-bold [&_strong]:text-white [&_b]:font-bold [&_b]:text-white"
                  dangerouslySetInnerHTML={{ __html: activeAlert.message }}
                />
              </div>

              <button
                type="button"
                onClick={() => setActiveAlert(null)}
                className="shrink-0 p-1 text-white/80 hover:text-white rounded-lg hover:bg-white/20 transition cursor-pointer"
                aria-label="Tutup"
              >
                <X className="h-4 w-4" />
              </button>
            </div>
          </motion.div>
        )}
      </AnimatePresence>

      {/* Floating Reload Flash Toast */}
      <AnimatePresence>
        {reloadFlash && (
          <motion.div
            initial={{ opacity: 0, y: -12 }}
            animate={{ opacity: 1, y: 0 }}
            exit={{ opacity: 0, y: -12 }}
            className="fixed left-1/2 top-3 z-[70] -translate-x-1/2 rounded-full border border-emerald-500/30 bg-white/95 dark:bg-gray-800/95 px-4 py-2 text-xs font-bold text-emerald-600 dark:text-emerald-400 shadow-md backdrop-blur-md"
          >
            <span className="flex items-center gap-1.5">
              <CheckCircle2 className="h-3.5 w-3.5" /> Data berhasil dimuat ulang
            </span>
          </motion.div>
        )}
      </AnimatePresence>

      {/* Floating Scroll to Top Button */}
      <AnimatePresence>
        {showScrollTop && (
          <motion.button
            initial={{ opacity: 0, scale: 0.7, y: 10 }}
            animate={{ opacity: 1, scale: 1, y: 0 }}
            exit={{ opacity: 0, scale: 0.7, y: 10 }}
            whileTap={{ scale: 0.9 }}
            onClick={scrollToTop}
            className={cn(
              "fixed z-40 flex h-10 w-10 sm:h-11 sm:w-11 items-center justify-center rounded-2xl border border-gray-200 dark:border-gray-700 bg-white/95 dark:bg-gray-800/95 text-brand-500 dark:text-brand-400 shadow-lg backdrop-blur-xl transition-all hover:bg-gray-50 dark:hover:bg-gray-700 active:scale-95 cursor-pointer",
              onBack ? "bottom-8 right-4 sm:bottom-8 sm:right-8" : "bottom-20 right-4 sm:bottom-8 sm:right-8"
            )}
            title="Kembali ke Atas"
            aria-label="Kembali ke Atas"
          >
            <ChevronUp className="h-5 w-5 stroke-[2.5]" />
          </motion.button>
        )}
      </AnimatePresence>

      {/* Global Inertia Sync Overlay */}
      <SyncOverlay
        show={globalLoading}
        title={globalLoadingTitle}
        statusMessage={globalLoadingMessage}
      />

      {/* Global Action Result Modal Overlay */}
      <AnimatePresence>
        {resultOverlay && (
          <motion.div
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            exit={{ opacity: 0 }}
            transition={{ duration: 0.18 }}
            className="fixed inset-0 z-[99999] flex items-center justify-center bg-black/60 p-4 backdrop-blur-xs select-none pointer-events-auto"
            onClick={() => setResultOverlay(null)}
          >
            <motion.div
              initial={{ opacity: 0, scale: 0.92, y: 14 }}
              animate={{ opacity: 1, scale: 1, y: 0 }}
              exit={{ opacity: 0, scale: 0.94, y: 8 }}
              transition={{ type: "spring", stiffness: 450, damping: 30 }}
              onClick={(e) => e.stopPropagation()}
              className="w-full max-w-sm rounded-3xl border border-gray-200 dark:border-[#212B3B] bg-white dark:bg-[#121720] p-6 text-center text-gray-900 dark:text-white shadow-2xl space-y-4"
            >
              <div
                className={cn(
                  "relative mx-auto flex h-16 w-16 items-center justify-center rounded-2xl border shadow-xs",
                  resultOverlay.type === "success"
                    ? "bg-emerald-50 dark:bg-emerald-950/30 border-emerald-200 dark:border-emerald-500/40 text-emerald-600 dark:text-emerald-400"
                    : "bg-rose-50 dark:bg-rose-950/30 border-rose-200 dark:border-rose-500/40 text-rose-600 dark:text-rose-400"
                )}
              >
                {resultOverlay.type === "success" ? (
                  <CheckCircle2 className="h-8 w-8 text-emerald-500" />
                ) : (
                  <ShieldAlert className="h-8 w-8 text-rose-500" />
                )}
              </div>

              <div className="space-y-1">
                <h3 className="font-bold text-base sm:text-lg text-gray-900 dark:text-white tracking-tight">
                  {resultOverlay.title}
                </h3>
                <p className="text-xs text-gray-600 dark:text-slate-300 leading-relaxed max-h-40 overflow-y-auto font-medium">
                  {resultOverlay.message}
                </p>
              </div>

              <div className="pt-2">
                <button
                  type="button"
                  onClick={() => setResultOverlay(null)}
                  className={cn(
                    "w-full h-10 rounded-xl text-xs font-bold text-white transition-all active:scale-95 cursor-pointer shadow-xs",
                    resultOverlay.type === "success"
                      ? "bg-brand-500 hover:bg-brand-600"
                      : "bg-rose-600 hover:bg-rose-700"
                  )}
                >
                  {resultOverlay.type === "success" ? "Tutup / Selesai" : "Tutup & Periksa"}
                </button>
              </div>
            </motion.div>
          </motion.div>
        )}
      </AnimatePresence>

      {/* Modals & Sheets */}
      <ServiceSettingsSheet service={serviceSheet} onClose={() => setServiceSheet(null)} />
      {isAdminPanel && <AutoInvoiceSettingsModal />}
      <ResetPasswordModal data={resetPwdData} onClose={() => setResetPwdData(null)} />
      <SystemInfoModal open={systemInfoOpen} onClose={() => setSystemInfoOpen(false)} />
    </div>
  )
}

export function AppLayout(props: AppLayoutProps) {
  return (
    <ThemeProvider>
      <SidebarProvider>
        <AppLayoutInner {...props} />
      </SidebarProvider>
    </ThemeProvider>
  )
}

export default AppLayout
