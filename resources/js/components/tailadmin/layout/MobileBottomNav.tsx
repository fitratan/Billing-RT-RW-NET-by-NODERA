import React from "react"
import { Link, usePage } from "@inertiajs/react"
import { useSidebar } from "@/context/SidebarContext"
import { cn } from "@/lib/utils"
import {
  LayoutDashboard,
  Home,
  Users,
  Wifi,
  Wallet,
  Receipt,
  Smartphone,
  Send,
  Server,
  Layers,
  MapPin,
  HelpCircle,
  LifeBuoy,
  Menu,
  Activity,
} from "lucide-react"

export interface MobileBottomNavProps {
  customItems?: Array<{
    label: string
    href: string
    icon: React.ComponentType<{ className?: string; strokeWidth?: number }>
    active?: boolean | string | string[]
  }>
}

export const MobileBottomNav: React.FC<MobileBottomNavProps> = ({ customItems }) => {
  const { url, component } = usePage()
  const { toggleMobileSidebar } = useSidebar()

  // Clean path matching
  const currentPath = (url || "").split("?")[0].replace(/\/$/, "") || "/"
  const comp = (component as string) || ""

  // Host detection
  const host = typeof window !== "undefined" ? window.location.host : ""
  const isPanelHost = host.includes("panel.") || host.includes("nel.") || host.includes("vpn.")
  const isGatewayHost = host.includes("gateway.") || host.includes("pay.")
  const isWaHost = host.includes("wa.") || host.includes("whatsapp.")

  // 1. Superadmin Portal (Unified and strict across all /superadmin/* pages)
  const isSuperadmin = comp.startsWith("Superadmin/") || url.startsWith("/superadmin")

  // 2. Nodera Pay Merchant Portal (Standalone or prefixed)
  const isNoderaPay =
    !isSuperadmin &&
    (comp.startsWith("NoderaPay/") ||
      url.startsWith("/noderapay") ||
      (isGatewayHost && !url.startsWith("/wagateway")))

  // 3. Nodera WhatsApp Gateway Portal (Standalone or prefixed)
  const isWaGateway =
    !isSuperadmin &&
    !isNoderaPay &&
    (comp.startsWith("WaGateway/") ||
      url.startsWith("/wagateway") ||
      isWaHost)

  // 4. Portal Pelanggan
  const isPortal = comp.startsWith("Portal/") || url.startsWith("/portal")

  // 5. Portal Teknisi
  const isTeknisi = comp.startsWith("Teknisi/") || url.startsWith("/teknisi")

  // 6. Portal Kolektor
  const isKolektor = comp.startsWith("Kolektor/") || url.startsWith("/kolektor")

  // 7. VPN / Cloud Member Portal (Strict check for Vpn pages and member routes)
  const isVpn =
    !isSuperadmin &&
    !isNoderaPay &&
    !isWaGateway &&
    !isPortal &&
    !isTeknisi &&
    !isKolektor &&
    (comp.startsWith("Vpn/") ||
      url.startsWith("/vpn") ||
      url.startsWith("/akun") ||
      (url.startsWith("/mikhmon") && !url.startsWith("/admin/mikhmon")) ||
      url.startsWith("/topup") ||
      url.startsWith("/referral") ||
      url.startsWith("/bookkeeping") ||
      url.startsWith("/arisan") ||
      isPanelHost)

  const checkIsActive = (itemHref: string, itemActive?: boolean | string | string[]): boolean => {
    if (typeof itemActive === "boolean") return itemActive

    const testPattern = (pattern: string) => {
      const clean = pattern.split("?")[0].replace(/\/$/, "")
      if (!clean) return false
      // Dashboard / Home root paths must match strictly (not prefix match on all sub-routes)
      if (
        clean === "" ||
        clean === "/" ||
        clean === "/dashboard" ||
        clean === "/admin/dashboard" ||
        clean === "/admin" ||
        clean === "/superadmin" ||
        clean === "/portal" ||
        clean === "/portal/dashboard" ||
        clean === "/teknisi" ||
        clean === "/teknisi/dashboard" ||
        clean === "/kolektor" ||
        clean === "/kolektor/dashboard" ||
        clean === "/vpn" ||
        clean === "/noderapay" ||
        clean === "/noderapay/dashboard" ||
        clean === "/wagateway" ||
        clean === "/wagateway/dashboard"
      ) {
        if (clean === "/dashboard" || clean === "/admin/dashboard" || clean === "/admin") {
          return (
            currentPath === "/dashboard" ||
            currentPath === "/admin" ||
            currentPath === "/admin/dashboard"
          )
        }
        return currentPath === clean
      }
      return currentPath === clean || currentPath.startsWith(clean + "/")
    }

    if (Array.isArray(itemActive)) {
      return itemActive.some((pattern) => testPattern(pattern))
    }
    if (typeof itemActive === "string") {
      return testPattern(itemActive)
    }

    return testPattern(itemHref)
  }

  const getNavItems = () => {
    if (customItems && customItems.length > 0) {
      const maxCount = isPortal || customItems.length >= 4 ? 4 : 3
      return customItems.slice(0, maxCount).map((item) => ({
        ...item,
        active: checkIsActive(item.href, item.active),
      }))
    }

    // ── SUPERADMIN PORTAL (STABLE ACROSS ALL SUPERADMIN PAGES) ──
    if (isSuperadmin) {
      return [
        {
          label: "Dashboard",
          href: "/superadmin",
          icon: LayoutDashboard,
          active: currentPath === "/superadmin",
        },
        {
          label: "Tenants",
          href: "/superadmin/tenants",
          icon: Users,
          active: currentPath.startsWith("/superadmin/tenants"),
        },
        {
          label: "Mikhmon",
          href: "/superadmin/mikhmon",
          icon: Wifi,
          active: currentPath.startsWith("/superadmin/mikhmon"),
        },
      ]
    }

    // ── TEKNISI PORTAL ──
    if (isTeknisi) {
      return [
        {
          label: "Dashboard",
          href: "/teknisi/dashboard",
          icon: LayoutDashboard,
          active: currentPath === "/teknisi/dashboard" || currentPath === "/teknisi",
        },
        {
          label: "Pool Tiket",
          href: "/teknisi/pool",
          icon: Layers,
          active: currentPath.startsWith("/teknisi/pool"),
        },
        {
          label: "Peta Jaringan",
          href: "/teknisi/map",
          icon: MapPin,
          active: currentPath.startsWith("/teknisi/map"),
        },
      ]
    }

    // ── KOLEKTOR PORTAL ──
    if (isKolektor) {
      return [
        {
          label: "Tagihan",
          href: "/kolektor/dashboard",
          icon: Receipt,
          active: currentPath === "/kolektor/dashboard" || currentPath === "/kolektor",
        },
        {
          label: "Pool Tugas",
          href: "/kolektor/pool",
          icon: Layers,
          active: currentPath.startsWith("/kolektor/pool"),
        },
        {
          label: "Riwayat",
          href: "/kolektor/history",
          icon: Activity,
          active: currentPath.startsWith("/kolektor/history"),
        },
      ]
    }

    // ── PORTAL PELANGGAN ──
    if (isPortal) {
      return [
        {
          label: "Beranda",
          href: "/portal",
          icon: Home,
          active: currentPath === "/portal",
        },
        {
          label: "Tagihan",
          href: "/portal/invoices",
          icon: Receipt,
          active: currentPath.startsWith("/portal/invoices") || currentPath.startsWith("/portal/payment"),
        },
        {
          label: "Internet",
          href: "/portal/usage",
          icon: Wifi,
          active: currentPath.startsWith("/portal/usage") || currentPath.startsWith("/portal/wifi"),
        },
        {
          label: "Bantuan",
          href: "/portal/laporan",
          icon: LifeBuoy,
          active: currentPath.startsWith("/portal/laporan") || currentPath.startsWith("/portal/tos"),
        },
      ]
    }

    // ── NODERA PAY (MERCHANT GATEWAY PORTAL) ──
    if (isNoderaPay) {
      const npPrefix = url.startsWith("/noderapay") ? "/noderapay" : ""
      return [
        {
          label: "Dashboard",
          href: npPrefix ? `${npPrefix}/dashboard` : "/dashboard",
          icon: LayoutDashboard,
          active:
            currentPath === `${npPrefix}/dashboard` ||
            currentPath === "/noderapay" ||
            currentPath === `${npPrefix}`,
        },
        {
          label: "Transaksi",
          href: npPrefix ? `${npPrefix}/transactions` : "/transactions",
          icon: Receipt,
          active: currentPath.includes("/transactions"),
        },
        {
          label: "Pencairan",
          href: npPrefix ? `${npPrefix}/withdrawals` : "/withdrawals",
          icon: Wallet,
          active: currentPath.includes("/withdrawals"),
        },
      ]
    }

    // ── NODERA WA GATEWAY PORTAL ──
    if (isWaGateway) {
      const waPrefix = url.startsWith("/wagateway") ? "/wagateway" : ""
      return [
        {
          label: "Dashboard",
          href: waPrefix ? `${waPrefix}/dashboard` : "/dashboard",
          icon: LayoutDashboard,
          active:
            currentPath === `${waPrefix}/dashboard` ||
            currentPath === "/wagateway" ||
            currentPath === `${waPrefix}`,
        },
        {
          label: "Perangkat",
          href: waPrefix ? `${waPrefix}/devices` : "/devices",
          icon: Smartphone,
          active: currentPath.includes("/devices"),
        },
        {
          label: "Kirim Pesan",
          href: waPrefix ? `${waPrefix}/quick-send` : "/quick-send",
          icon: Send,
          active: currentPath.includes("/quick-send"),
        },
      ]
    }

    // ── VPN / CLOUD MEMBER PORTAL ──
    if (isVpn) {
      const vpnPrefix = url.startsWith("/vpn") ? "/vpn" : ""
      return [
        {
          label: "Dashboard",
          href: vpnPrefix ? `${vpnPrefix}` : "/dashboard",
          icon: LayoutDashboard,
          active:
            currentPath === `${vpnPrefix}` ||
            currentPath === "/dashboard" ||
            currentPath === "/vpn" ||
            currentPath === "",
        },
        {
          label: "Layanan VPN",
          href: vpnPrefix ? `${vpnPrefix}/accounts` : "/akun",
          icon: Server,
          active:
            currentPath.startsWith("/akun") ||
            currentPath.startsWith(`${vpnPrefix}/accounts`),
        },
        {
          label: "Topup Saldo",
          href: vpnPrefix ? `${vpnPrefix}/topup` : "/topup",
          icon: Wallet,
          active:
            currentPath.startsWith("/topup") ||
            currentPath.startsWith(`${vpnPrefix}/topup`),
        },
      ]
    }

    // ── DEFAULT ISP BILLING / TENANT ADMIN PORTAL (3 items + 1 Menu button = 4 items total) ──
    return [
      {
        label: "Beranda",
        href: "/dashboard",
        icon: LayoutDashboard,
        active: checkIsActive("/dashboard", ["/dashboard", "/admin/dashboard"]),
      },
      {
        label: "Pelanggan",
        href: "/admin/billing/customers",
        icon: Users,
        active: checkIsActive("/admin/billing/customers", ["/admin/billing/customers", "/admin/customers"]),
      },
      {
        label: "Invoice",
        href: "/admin/billing/invoices",
        icon: Receipt,
        active: checkIsActive("/admin/billing/invoices", ["/admin/billing/invoices", "/admin/invoices"]),
      },
    ]
  }

  const navItems = getNavItems()

  if (!navItems || navItems.length === 0) {
    return null
  }

  return (
    <nav
      role="navigation"
      aria-label="Mobile Navigation"
      className="fixed inset-x-0 bottom-0 z-40 select-none xl:hidden border-t border-gray-200 bg-white/95 backdrop-blur-xl dark:border-gray-800 dark:bg-gray-900/95 shadow-lg pb-[env(safe-area-inset-bottom,0px)] transition-colors"
    >
      <div className="grid grid-cols-4 items-center h-16 max-w-lg mx-auto px-1">
        {navItems.map((item) => {
          const Icon = item.icon
          return (
            <Link
              key={item.href}
              href={item.href}
              className={cn(
                "group relative flex flex-col items-center justify-center h-full flex-1 py-1.5 px-1 transition-all duration-150 active:scale-95 focus:outline-hidden cursor-pointer",
                item.active
                  ? "text-brand-500 dark:text-brand-400 font-bold"
                  : "text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white font-medium"
              )}
            >
              {item.active && (
                <span className="absolute top-0 inset-x-2 sm:inset-x-3 h-0.5 rounded-full bg-brand-500 dark:bg-brand-400" />
              )}
              <Icon
                className={cn(
                  "h-5 w-5 mb-0.5 transition-transform duration-150 shrink-0",
                  item.active ? "scale-110" : "opacity-80 group-hover:opacity-100"
                )}
                strokeWidth={item.active ? 2.4 : 1.8}
              />
              <span className="text-[10px] truncate max-w-[64px] leading-tight">
                {item.label}
              </span>
            </Link>
          )
        })}

        {/* ── 4TH ITEM (RIGHTMOST): OPEN SIDEBAR MENU BUTTON (ONLY WHEN SIDEBAR EXISTS & NAV HAS 3 ITEMS) ── */}
        {!isPortal && navItems.length <= 3 && (
          <button
            type="button"
            onClick={toggleMobileSidebar}
            aria-label="Buka Menu Sidebar"
            className="group relative flex flex-col items-center justify-center h-full flex-1 py-1.5 px-1 transition-all duration-150 active:scale-95 text-gray-500 hover:text-brand-500 dark:text-gray-400 dark:hover:text-brand-400 cursor-pointer focus:outline-hidden"
          >
            <Menu
              className="h-5 w-5 mb-0.5 transition-transform duration-150 shrink-0 opacity-80 group-hover:opacity-100 group-hover:scale-110"
              strokeWidth={1.8}
            />
            <span className="text-[10px] truncate max-w-[64px] font-medium leading-tight">
              Menu
            </span>
          </button>
        )}
      </div>
    </nav>
  )
}

export default MobileBottomNav
