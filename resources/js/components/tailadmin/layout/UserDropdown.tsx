import React, { useState, useRef } from "react"
import { Link, usePage, router } from "@inertiajs/react"
import { useClickOutside } from "@/hooks/useClickOutside"
import { useTheme } from "@/context/ThemeContext"
import { Dropdown } from "@/components/tailadmin/ui/dropdown/Dropdown"
import { User, LogOut, ChevronDown, Moon, Sun, Smartphone } from "lucide-react"

export const UserDropdown: React.FC = () => {
  const [isOpen, setIsOpen] = useState(false)
  const dropdownRef = useRef<HTMLDivElement>(null)
  const { url, props } = usePage()
  const { theme, toggleTheme } = useTheme()
  const auth = (props.auth as any) || {}
  const merchant = (props.merchant as any) || (props.wa_merchant as any) || null

  const isPortal =
    url.startsWith("/portal") ||
    auth.user?.role === "customer" ||
    Boolean((props as any)?.customer)

  const isCollector =
    !isPortal &&
    (url.startsWith("/kolektor") ||
    url.startsWith("/collector") ||
    auth.user?.role === "collector" ||
    auth.user?.role === "kolektor")

  const isTechnician =
    !isPortal &&
    (url.startsWith("/teknisi") ||
    url.startsWith("/technician") ||
    auth.user?.role === "technician" ||
    auth.user?.role === "teknisi")

  const isNoderaPay =
    !isCollector &&
    !isTechnician &&
    ((props as any)?.brand?.sub === "PAYMENT GATEWAY" ||
    url.startsWith("/noderapay") ||
    url.startsWith("/transactions") ||
    url.startsWith("/withdrawals") ||
    url.startsWith("/payment-methods") ||
    (typeof window !== "undefined" && window.location.host.includes("gateway.") && !url.startsWith("/wagateway")))

  const isVpn =
    !isCollector &&
    !isTechnician &&
    !isNoderaPay &&
    (url === "/dashboard" ||
      url.startsWith("/vpn") ||
      url.startsWith("/akun") ||
      url.startsWith("/mikhmon") ||
      url.startsWith("/bookkeeping") ||
      url.startsWith("/arisan") ||
      url.startsWith("/topup") ||
      url.startsWith("/history") ||
      url.startsWith("/semua-fitur") ||
      url.startsWith("/profil") ||
      url.startsWith("/referral") ||
      url.startsWith("/isp-billing"))

  const isWaGateway =
    !isCollector &&
    !isTechnician &&
    !isVpn &&
    !isNoderaPay &&
    (url.startsWith("/wagateway") ||
      url.startsWith("/devices") ||
      url.startsWith("/messages") ||
      url.startsWith("/billing") ||
      url.startsWith("/credentials") ||
      url.startsWith("/docs") ||
      url.startsWith("/settings") ||
      url.startsWith("/quick-send") ||
      url.startsWith("/media") ||
      url.startsWith("/broadcast") ||
      url.startsWith("/scheduled") ||
      url.startsWith("/auto-reply") ||
      url.startsWith("/activity-logs"))

  const npPrefix = url.startsWith("/noderapay") ? "/noderapay" : ""
  const waPrefix = url.startsWith("/wagateway") ? "/wagateway" : ""

  const isSuperadmin =
    !isCollector &&
    !isTechnician &&
    (auth.user?.role === "superadmin" ||
    url.startsWith("/superadmin") ||
    Boolean((props.user as any)?.is_superadmin))

  // Explicit authenticated user resolution with highest priority
  const getUserProfile = () => {
    // 0. Customer Portal session
    if (isPortal) {
      const customer = (props as any)?.customer || auth.user
      return {
        name: customer?.name || "Pelanggan",
        email: customer?.pppoe_username || customer?.phone || customer?.code || "Pelanggan Aktif",
        role: "Pelanggan",
        isPro: true,
      }
    }

    // 1. Primary authenticated user (Admin, Superadmin, Technician, Collector, Cloud Member)
    if (auth.user?.name || auth.user?.email) {
      const rawRole = (auth.user.role || "").toLowerCase()
      let roleLabel = "Administrator"
      if (isCollector || rawRole === "collector" || rawRole === "kolektor") roleLabel = "Kolektor"
      else if (isTechnician || rawRole === "technician" || rawRole === "teknisi") roleLabel = "Teknisi"
      else if (rawRole === "superadmin" || isSuperadmin) roleLabel = "Super Admin"
      else if (rawRole === "admin") roleLabel = "Administrator"
      else if (rawRole === "vpn_user" || isVpn) roleLabel = "Cloud Member"

      return {
        name: auth.user.name || (isCollector ? "Kolektor" : isTechnician ? "Teknisi" : "Administrator"),
        email: auth.user.email || auth.user.username || "Petugas Lapangan",
        role: roleLabel,
        isPro: true,
      }
    }

    // 2. Standalone merchant session (NoderaPay / WA Gateway)
    if (merchant) {
      return {
        name: merchant.name || merchant.merchant_code,
        email: merchant.email || merchant.phone,
        role: isNoderaPay ? "Payment Merchant" : merchant.plan_type ? `${merchant.plan_type.toUpperCase()} Merchant` : "Merchant",
        isPro: merchant.plan_type === "pro" || isNoderaPay,
      }
    }

    // 3. Fallback
    return {
      name: (props.user as any)?.name || "Administrator",
      email: (props.user as any)?.email || "admin@nodera.id",
      role: isSuperadmin ? "Super Admin" : "Administrator",
      isPro: true,
    }
  }

  const user = getUserProfile()

  useClickOutside(dropdownRef, () => {
    setIsOpen(false)
  })

  const toggleDropdown = () => {
    setIsOpen((prev) => !prev)
  }

  const closeDropdown = () => {
    setIsOpen(false)
  }

  const handleLogout = () => {
    closeDropdown()
    const logoutUrl = isPortal
      ? "/portal/logout"
      : isCollector
      ? "/kolektor/logout"
      : isTechnician
      ? "/teknisi/logout"
      : isNoderaPay
      ? `${npPrefix}/logout`
      : isWaGateway && !auth.user?.name
      ? (waPrefix ? `${waPrefix}/logout` : "/logout")
      : "/logout"
    router.post(logoutUrl)
  }

  const profileHref = isPortal
    ? "/portal/profile"
    : isCollector
    ? "/kolektor/profile"
    : isTechnician
    ? "/teknisi/profile"
    : isSuperadmin
    ? "/superadmin/settings"
    : isNoderaPay
    ? `${npPrefix}/settings`
    : isVpn && auth.user?.role === "vpn_user"
    ? "/profil"
    : isWaGateway && !auth.user?.name
    ? `${waPrefix}/settings?tab=profile`
    : "/admin/my-settings"

  const handleInstallApp = () => {
    closeDropdown()
    const promptEvent = (window as any).__pwaDeferredPrompt
    const isIos = /iphone|ipad|ipod/.test(window.navigator.userAgent.toLowerCase())
    const isStandalone =
      window.matchMedia("(display-mode: standalone)").matches ||
      window.matchMedia("(display-mode: fullscreen)").matches ||
      (navigator as any).standalone === true

    if (isStandalone) {
      alert("Aplikasi sudah terpasang di perangkat Anda.")
      return
    }

    if (promptEvent) {
      promptEvent.prompt()
      promptEvent.userChoice.then(() => {
        ;(window as any).__pwaDeferredPrompt = null
      })
    } else if (isIos) {
      alert("Untuk memasang aplikasi di iPhone/iPad:\n1. Ketuk tombol Share (kotak dengan panah ke atas di Safari)\n2. Gulir ke bawah lalu pilih 'Tambah ke Layar Utama' (Add to Home Screen)")
    } else {
      alert("Untuk memasang aplikasi:\nBuka menu browser (ikon titik 3 di kanan atas) lalu pilih 'Install Aplikasi' atau 'Tambahkan ke Layar Utama'.")
    }
  }

  return (
    <div className="relative" ref={dropdownRef}>
      <button
        onClick={toggleDropdown}
        className="dropdown-toggle flex items-center gap-2.5 rounded-xl p-1 text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/[0.04] focus:outline-none cursor-pointer group transition-colors"
      >
        <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-brand-500 text-white font-black text-sm shadow-sm">
          {user.name ? user.name.charAt(0).toUpperCase() : "A"}
        </span>

        <div className="hidden text-left lg:block">
          <span className="block text-theme-sm font-semibold text-gray-800 dark:text-white group-hover:text-brand-500 transition-colors truncate max-w-[130px]">
            {user.name}
          </span>
          <span className="block text-[10px] font-semibold text-gray-500 dark:text-gray-400">
            {user.role}
          </span>
        </div>

        <ChevronDown
          className={`h-4 w-4 text-gray-400 dark:text-gray-500 transition-transform duration-200 group-hover:text-gray-700 dark:group-hover:text-white ${
            isOpen ? "rotate-180" : ""
          }`}
        />
      </button>

      <Dropdown
        isOpen={isOpen}
        onClose={closeDropdown}
        className="absolute right-0 mt-3 flex w-64 flex-col rounded-2xl border border-gray-200 bg-white p-3 shadow-xl dark:border-gray-800 dark:bg-gray-900 z-50 animate-in fade-in zoom-in-95 duration-150"
      >
        <div className="border-b border-gray-100 dark:border-gray-800 pb-3 px-2">
          <div className="truncate">
            <span className="block text-theme-sm font-bold text-gray-900 dark:text-white truncate">
              {user.name}
            </span>
            <span className="mt-0.5 block text-theme-xs text-gray-500 dark:text-gray-400 truncate">
              {user.email}
            </span>
          </div>
        </div>

        <ul className="flex flex-col gap-1 border-b border-gray-100 dark:border-gray-800 py-2">
          <li>
            <Link
              href={profileHref}
              onClick={closeDropdown}
              className="flex items-center gap-3 rounded-xl px-3 py-2 text-theme-xs font-semibold text-gray-700 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-300 dark:hover:bg-white/[0.04] dark:hover:text-white transition-colors"
            >
              <User className="h-4 w-4 text-gray-400 dark:text-gray-500" />
              <span>Profil Pengguna</span>
            </Link>
          </li>
          <li>
            <button
              onClick={toggleTheme}
              className="flex w-full items-center justify-between rounded-xl px-3 py-2 text-theme-xs font-semibold text-gray-700 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-300 dark:hover:bg-white/[0.04] dark:hover:text-white transition-colors cursor-pointer"
            >
              <div className="flex items-center gap-3">
                {theme === "dark" ? (
                  <Sun className="h-4 w-4 text-amber-500" />
                ) : (
                  <Moon className="h-4 w-4 text-brand-500" />
                )}
                <span>Mode {theme === "dark" ? "Terang" : "Gelap"}</span>
              </div>
              <span className="rounded-md bg-gray-100 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 px-1.5 py-0.5 text-[9px] font-bold text-gray-700 dark:text-gray-300 uppercase">
                {theme === "dark" ? "Dark" : "Light"}
              </span>
            </button>
          </li>
          <li>
            <button
              onClick={handleInstallApp}
              className="flex w-full items-center justify-between rounded-xl px-3 py-2 text-theme-xs font-semibold text-gray-700 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-300 dark:hover:bg-white/[0.04] dark:hover:text-white transition-colors cursor-pointer"
            >
              <div className="flex items-center gap-3">
                <Smartphone className="h-4 w-4 text-brand-500" />
                <span>Install Aplikasi</span>
              </div>
              <span className="rounded-md bg-brand-50 dark:bg-brand-500/10 text-brand-600 dark:text-brand-400 border border-brand-200 dark:border-brand-500/20 px-1.5 py-0.5 text-[9px] font-bold uppercase">
                App
              </span>
            </button>
          </li>
        </ul>

        <div className="pt-2">
          <button
            onClick={handleLogout}
            className="flex w-full items-center gap-3 rounded-xl px-3 py-2 text-theme-xs font-bold text-rose-600 hover:bg-rose-50 dark:text-rose-400 dark:hover:bg-rose-500/10 transition-colors cursor-pointer"
          >
            <LogOut className="h-4 w-4 text-rose-500" />
            <span>Keluar Sesi</span>
          </button>
        </div>
      </Dropdown>
    </div>
  )
}

export default UserDropdown
