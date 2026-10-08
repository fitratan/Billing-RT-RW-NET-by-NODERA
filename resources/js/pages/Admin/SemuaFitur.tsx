import { AppLayout } from "@/components/layout/app-layout"
import {
  Home,
  Receipt,
  Users,
  Package,
  Boxes,
  Settings,
  Wallet,
  TrendingDown,
  Banknote,
  Router,
  Wifi,
  KeyRound,
  Server,
  Gauge,
  MapPin,
  MessageSquare,
  Ticket,
  Puzzle,
  CreditCard,
  QrCode,
  Search,
  ShieldCheck,
  Radio,
  Layers,
  Webhook,
  X,
  ChevronLeft,
  Grid,
  SlidersHorizontal,
  FileText,
  History,
  LifeBuoy,
  UserCog,
  Cpu,
  ShoppingBag,
  Globe,
  Send,
  Terminal,
  Eye,
  EyeOff,
  Check,
  RotateCcw,
  CheckCircle2,
  Network,
  type LucideIcon,
} from "lucide-react"
import { PageProps } from "@/types"
import { useLanguage } from "@/lib/i18n"
import { adminSidebarItems, adminNavItems, adminBrand } from "@/lib/admin-nav"
import { Link, usePage } from "@inertiajs/react"
import { useState, useMemo, useDeferredValue, useEffect } from "react"
import { cn } from "@/lib/utils"

interface FeatureItem {
  href: string
  title: string
  desc: string
  icon: LucideIcon
  badge?: string
  gradient: string
}

interface FeatureGroup {
  id: string
  name: string
  icon: LucideIcon
  items: FeatureItem[]
}

export default function SemuaFiturPage({
  tenantName = "NODERA Billing",
  adminName,
}: PageProps<{
  tenantName: string
  adminName: string
}>) {
  const { t } = useLanguage()
  const [search, setSearch] = useState<string>("")
  const [activeCategory, setActiveCategory] = useState<string>("all")
  const [visibilityModalOpen, setVisibilityModalOpen] = useState(false)
  const [modalSearch, setModalSearch] = useState("")
  const [modalCategory, setModalCategory] = useState("all")
  const [savedToast, setSavedToast] = useState(false)

  const [hiddenItems, setHiddenItems] = useState<Set<string>>(() => {
    if (typeof window === "undefined") return new Set()
    try {
      const saved = localStorage.getItem("nodera_hidden_sidebar_items")
      if (saved) return new Set(JSON.parse(saved))
    } catch {}
    return new Set()
  })

  useEffect(() => {
    const handleUpdate = () => {
      try {
        const saved = localStorage.getItem("nodera_hidden_sidebar_items")
        setHiddenItems(saved ? new Set(JSON.parse(saved)) : new Set())
      } catch {}
    }
    window.addEventListener("nodera-menu-visibility-change", handleUpdate)
    window.addEventListener("storage", handleUpdate)
    return () => {
      window.removeEventListener("nodera-menu-visibility-change", handleUpdate)
      window.removeEventListener("storage", handleUpdate)
    }
  }, [])

  const toggleFeatureVisibility = (href: string) => {
    setHiddenItems((prev) => {
      const next = new Set(prev)
      if (next.has(href)) {
        next.delete(href)
      } else {
        next.add(href)
      }
      localStorage.setItem("nodera_hidden_sidebar_items", JSON.stringify(Array.from(next)))
      window.dispatchEvent(new CustomEvent("nodera-menu-visibility-change"))
      return next
    })
  }

  const showAllFeatures = () => {
    const next = new Set<string>()
    setHiddenItems(next)
    localStorage.setItem("nodera_hidden_sidebar_items", JSON.stringify([]))
    window.dispatchEvent(new CustomEvent("nodera-menu-visibility-change"))
    setSavedToast(true)
    setTimeout(() => setSavedToast(false), 2000)
  }

  const showEssentialOnly = () => {
    const nonEssential = [
      "/admin/shop/products",
      "/admin/shop/orders",
      "/admin/landing-settings",
      "/admin/olt",
      "/admin/genieacs",
      "/admin/map",
      "/admin/vpn",
      "/admin/inventory",
      "/admin/employees",
      "/admin/addons",
      "/admin/api-apps",
    ]
    const next = new Set<string>(nonEssential)
    setHiddenItems(next)
    localStorage.setItem("nodera_hidden_sidebar_items", JSON.stringify(nonEssential))
    window.dispatchEvent(new CustomEvent("nodera-menu-visibility-change"))
    setSavedToast(true)
    setTimeout(() => setSavedToast(false), 2000)
  }

  const saveAndCloseModal = () => {
    localStorage.setItem("nodera_hidden_sidebar_items", JSON.stringify(Array.from(hiddenItems)))
    window.dispatchEvent(new CustomEvent("nodera-menu-visibility-change"))
    setSavedToast(true)
    setTimeout(() => {
      setSavedToast(false)
      setVisibilityModalOpen(false)
    }, 400)
  }

  const featureGroups: FeatureGroup[] = useMemo(
    () => [
      {
        id: "billing",
        name: "Billing & Tagihan",
        icon: Receipt,
        items: [
          {
            href: "/admin/billing/customers",
            title: "Pelanggan",
            desc: "Data langganan, status isolir, secret PPPoE dan koordinat",
            icon: Users,
            gradient: "bg-gradient-to-br from-[#00C2FF] to-[#0073C6] shadow-cyan-500/25",
          },
          {
            href: "/admin/billing/invoices",
            title: "Invoice",
            desc: "Manajemen tagihan bulanan, cetak struk dan reminder WA",
            icon: Receipt,
            badge: "Utama",
            gradient: "bg-gradient-to-br from-[#00B14F] to-[#00873C] shadow-emerald-500/25",
          },
          {
            href: "/admin/billing/packages",
            title: "Paket Langganan",
            desc: "Konfigurasi profil kecepatan bandwidth & harga paket",
            icon: Package,
            gradient: "bg-gradient-to-br from-[#10B981] to-[#059669] shadow-emerald-500/25",
          },
          {
            href: "/admin/payments/gateway",
            title: "Payment Gateway",
            desc: "Tripay, Duitku, Xendit konfirmasi otomatis 24/7",
            icon: CreditCard,
            badge: "Auto",
            gradient: "bg-gradient-to-br from-[#0073C6] to-[#005299] shadow-blue-500/25",
          },
          {
            href: "/admin/payments/qris",
            title: "QRIS Statis",
            desc: "Pembayaran barcode QRIS statis dan barcode dinamis",
            icon: QrCode,
            gradient: "bg-gradient-to-br from-[#F59E0B] to-[#D97706] shadow-amber-500/25",
          },
        ],
      },
      {
        id: "toko",
        name: "Toko Online & Subdomain",
        icon: ShoppingBag,
        items: [
          {
            href: "/admin/shop/products",
            title: "Produk & Voucher",
            desc: "Katalog voucher hotspot dan produk fisik jualan Anda",
            icon: ShoppingBag,
            badge: "Toko",
            gradient: "bg-gradient-to-br from-[#00C2FF] to-[#0073C6] shadow-cyan-500/25",
          },
          {
            href: "/admin/shop/orders",
            title: "Pesanan Masuk",
            desc: "Monitoring order masuk dari subdomain & ACC akun WiFi",
            icon: Receipt,
            badge: "Order",
            gradient: "bg-gradient-to-br from-[#10B981] to-[#059669] shadow-emerald-500/25",
          },
          {
            href: "/admin/landing-settings",
            title: "Landing Page & Telegram",
            desc: "Personalisasi tampilan toko dan notifikasi Telegram bot",
            icon: Globe,
            gradient: "bg-gradient-to-br from-[#8B5CF6] to-[#6D28D9] shadow-purple-500/25",
          },
        ],
      },
      {
        id: "keuangan",
        name: "Keuangan",
        icon: Wallet,
        items: [
          {
            href: "/admin/finance",
            title: "Laporan Keuangan",
            desc: "Ringkasan arus kas, pendapatan langganan, dan laba rugi usaha",
            icon: Wallet,
            badge: "Finance",
            gradient: "bg-gradient-to-br from-[#0073C6] to-[#005299] shadow-blue-500/25",
          },
          {
            href: "/admin/expenses",
            title: "Pengeluaran Kas",
            desc: "Pencatatan biaya operasional, pembelian material, dan kas keluar",
            icon: TrendingDown,
            gradient: "bg-gradient-to-br from-[#F43F5E] to-[#E11D48] shadow-rose-500/25",
          },
        ],
      },
      {
        id: "mikrotik",
        name: "Jaringan MikroTik",
        icon: Router,
        items: [
          {
            href: "/admin/mikrotik/routers",
            title: "Router MikroTik",
            desc: "Multi-Router ROS v6 dan v7, sync API gateway & traffic",
            icon: Router,
            badge: "Gateway",
            gradient: "bg-gradient-to-br from-[#F59E0B] to-[#D97706] shadow-amber-500/25",
          },
          {
            href: "/admin/radius",
            title: "RADIUS Server & CoA",
            desc: "FreeRADIUS AAA, MikroTik User Manager v7 & RFC 3576 Packet of Disconnect",
            icon: Radio,
            badge: "AAA",
            gradient: "bg-gradient-to-br from-[#6366F1] to-[#4F46E5] shadow-indigo-500/25",
          },
          {
            href: "/tools/mikrotik",
            title: "Studio Script & Tools",
            desc: "Generator script ROS, bypass speedtest, kalkulator QoS dan tools MikroTik",
            icon: Terminal,
            badge: "Tools",
            gradient: "bg-gradient-to-br from-[#10B981] to-[#059669] shadow-emerald-500/25",
          },
          {
            href: "/admin/pppoe",
            title: "PPPoE Secrets",
            desc: "Monitoring sesi secret, IP pool, uptime dan isolir otomatis",
            icon: KeyRound,
            gradient: "bg-gradient-to-br from-[#00C2FF] to-[#0096C7] shadow-cyan-500/25",
          },
          {
            href: "/admin/arp",
            title: "ARP Static & Binding",
            desc: "Monitoring host online/offline, IP-MAC binding statis, Simple Queue & isolir akses",
            icon: Network,
            badge: "Host NMS",
            gradient: "bg-gradient-to-br from-[#06B6D4] to-[#0891B2] shadow-cyan-500/25",
          },
          {
            href: "/admin/mikrotik/profiles",
            title: "Profil Bandwidth",
            desc: "Manajemen user profile PPPoE & Hotspot di MikroTik",
            icon: SlidersHorizontal,
            gradient: "bg-gradient-to-br from-[#8B5CF6] to-[#6D28D9] shadow-purple-500/25",
          },
          {
            href: "/admin/top-bandwidth",
            title: "Top Bandwidth",
            desc: "Monitoring throughput live dan leaderboard pemakaian bandwidth",
            icon: Gauge,
            badge: "Live",
            gradient: "bg-gradient-to-br from-[#38BDF8] to-[#0284C7] shadow-sky-500/25",
          },
        ],
      },
      {
        id: "mikhmon",
        name: "MIKHMON Hotspot",
        icon: Wifi,
        items: [
          {
            href: "/admin/mikhmon",
            title: "MIKHMON Hotspot",
            desc: "Instance MIKHMON dedicated per-tenant, generator voucher, dan monitoring hotspot",
            icon: Wifi,
            badge: "Dedicated",
            gradient: "bg-gradient-to-br from-[#00C2FF] to-[#0073C6] shadow-cyan-500/25",
          },
        ],
      },
      {
        id: "fiber",
        name: "Fiber Optik & OLT",
        icon: Server,
        items: [
          {
            href: "/admin/olt",
            title: "OLT & ONU",
            desc: "Monitoring redaman optik HIOSO, HSGQ, ZTE, Huawei",
            icon: Server,
            gradient: "bg-gradient-to-br from-[#8B5CF6] to-[#6D28D9] shadow-purple-500/25",
          },
          {
            href: "/admin/genieacs",
            title: "GenieACS TR-069",
            desc: "Auto-provisioning ONT dan manajemen parameter CWMP",
            icon: Wifi,
            badge: "CWMP",
            gradient: "bg-gradient-to-br from-[#38BDF8] to-[#0284C7] shadow-sky-500/25",
          },
          {
            href: "/admin/map",
            title: "Mapping Network & GIS",
            desc: "Peta interaktif GIS fiber, box ODP/HTB & sebaran pelanggan",
            icon: MapPin,
            badge: "GIS",
            gradient: "bg-gradient-to-br from-[#00C2FF] to-[#0073C6] shadow-cyan-500/25",
          },
          {
            href: "/admin/vpn",
            title: "VPN Remote",
            desc: "Akses remote router dan OLT via Nodera VPN",
            icon: ShieldCheck,
            badge: "Remote",
            gradient: "bg-gradient-to-br from-[#10B981] to-[#059669] shadow-emerald-500/25",
          },
        ],
      },
      {
        id: "operasional",
        name: "Operasional",
        icon: History,
        items: [
          {
            href: "/admin/whatsapp-templates",
            title: "Notifikasi WhatsApp",
            desc: "Template pesan tagihan, konfirmasi pembayaran, pemberitahuan isolir, dan pengingat otomatis",
            icon: MessageSquare,
            badge: "WhatsApp",
            gradient: "bg-gradient-to-br from-[#10B981] to-[#059669] shadow-emerald-500/25",
          },
          {
            href: "/admin/telegram",
            title: "Bot & Notifikasi Telegram",
            desc: "Konfigurasi bot Telegram, notifikasi pesanan 1-klik ACC, serta monitoring NMS router, PPPoE, & Hotspot",
            icon: Send,
            badge: "Telegram & NMS",
            gradient: "bg-gradient-to-br from-[#0088CC] to-[#0073C6] shadow-sky-500/25",
          },
          {
            href: "/admin/notifications",
            title: "Log Aktivitas",
            desc: "Audit rekam aktivitas admin, pembayaran tagihan dan log sistem",
            icon: History,
            badge: "Audit Log",
            gradient: "bg-gradient-to-br from-[#38BDF8] to-[#0284C7] shadow-sky-500/25",
          },
          {
            href: "/admin/trouble",
            title: "Tiket Gangguan",
            desc: "Helpdesk komplain pelanggan dan disposisi teknisi",
            icon: LifeBuoy,
            badge: "Helpdesk",
            gradient: "bg-gradient-to-br from-[#F43F5E] to-[#E11D48] shadow-rose-500/25",
          },
          {
            href: "/admin/employees",
            title: "Karyawan & Staff",
            desc: "Hak akses admin, teknisi dan kolektor lapangan",
            icon: UserCog,
            gradient: "bg-gradient-to-br from-[#00C2FF] to-[#0073C6] shadow-cyan-500/25",
          },
          {
            href: "/admin/inventory",
            title: "Inventory Gudang",
            desc: "Stok modem, kabel dropcore dan material instalasi",
            icon: Boxes,
            gradient: "bg-gradient-to-br from-[#A855F7] to-[#7E22CE] shadow-purple-500/25",
          },
        ],
      },
      {
        id: "sistem",
        name: "Sistem & Integrasi",
        icon: Settings,
        items: [
          {
            href: "/admin/addons",
            title: "Add-ons Platform",
            desc: "Ekstensi modular dan fitur tambahan sistem",
            icon: Puzzle,
            gradient: "bg-gradient-to-br from-[#6366F1] to-[#4F46E5] shadow-indigo-500/25",
          },
          {
            href: "/admin/api-apps",
            title: "API Apps & Webhook",
            desc: "Integrasi token API dan webhook eksternal",
            icon: Webhook,
            gradient: "bg-gradient-to-br from-[#0073C6] to-[#005299] shadow-blue-500/25",
          },
          {
            href: "/admin/my-settings",
            title: "Pengaturan Sistem",
            desc: "Profil ISP, konfigurasi umum dan template isolir",
            icon: Settings,
            badge: "Config",
            gradient: "bg-gradient-to-br from-[#475569] to-[#334155] shadow-slate-500/25",
          },
        ],
      },
    ],
    []
  )

  const { props } = usePage<any>()
  const lockedAddonRoutes: string[] = props?.lockedAddonRoutes || []
  const lockedRouteSet = useMemo(() => new Set(lockedAddonRoutes), [lockedAddonRoutes])

  const deferredSearch = useDeferredValue(search)
  const query = deferredSearch.toLowerCase().trim()

  const filteredGroups = featureGroups
    .filter((group) => activeCategory === "all" || group.id === activeCategory)
    .map((group) => ({
      ...group,
      items: group.items.filter(
        (item) =>
          !lockedRouteSet.has(item.href) &&
          !hiddenItems.has(item.href) &&
          (!query ||
            item.title.toLowerCase().includes(query) ||
            item.desc.toLowerCase().includes(query) ||
            group.name.toLowerCase().includes(query))
      ),
    }))
    .filter((group) => group.items.length > 0)

  return (
    <AppLayout
      title={t("nav.all_features", "Semua Fitur")}
      brand={adminBrand}
      sidebarItems={adminSidebarItems}
      navItems={adminNavItems}
      hideHeader={true}
      className="p-0 sm:p-0 max-w-none bg-[#0B0E14]"
    >
      <div className="min-h-screen bg-[#0B0E14] text-slate-100 select-none pb-36 sm:pb-44">
        {/* =========================================================================
            SECTION 1: TOP ELECTRIC BLUE CANVAS (Signature Fintech Standard)
            ========================================================================= */}
        <section className="relative bg-gradient-to-br from-[#0073C6] via-[#0056A0] to-[#003E78] px-4 pt-5 pb-10 sm:px-8 sm:pt-7 sm:pb-12 text-white shadow-sm rounded-b-[2rem] sm:rounded-b-[2.5rem] border-b border-white/15 overflow-hidden">
          <div className="relative max-w-7xl 2xl:max-w-[1600px] mx-auto space-y-4 sm:space-y-5">
            {/* Top Bar Header inside Canvas */}
            <div className="flex items-center justify-between gap-2.5 sm:gap-4">
              <div className="flex items-center gap-2.5 sm:gap-3 min-w-0 flex-1 overflow-hidden">
                <Link
                  href="/dashboard"
                  className="flex h-9 w-9 sm:h-10 sm:w-10 shrink-0 items-center justify-center rounded-full border border-white/20 bg-white/15 backdrop-blur-md text-white hover:bg-white/25 active:scale-95 lg:hidden"
                  title="Kembali ke Dashboard"
                >
                  <ChevronLeft className="h-5 w-5 shrink-0" />
                </Link>
                <div className="leading-tight min-w-0 flex-1 overflow-hidden flex flex-col justify-center">
                  <h1 className="text-sm sm:text-lg lg:text-xl font-bold lg:font-extrabold tracking-wider text-white truncate whitespace-nowrap block w-full">Semua Fitur NODERA</h1>
                  <p className="text-[10px] sm:text-xs text-white/80 truncate whitespace-nowrap">{tenantName}</p>
                </div>
              </div>

              {/* Action Button: Atur Visibilitas Menu & Sidebar */}
              <button
                type="button"
                onClick={() => setVisibilityModalOpen(true)}
                className="relative flex h-9 w-9 sm:h-10 sm:w-10 shrink-0 items-center justify-center rounded-full border border-white/20 bg-white/15 backdrop-blur-md text-white hover:bg-white/25 active:scale-95 transition-all"
                title="Atur Tampilan Menu"
              >
                <Settings className="h-4.5 w-4.5 sm:h-5 sm:w-5 text-white" />
                {hiddenItems.size > 0 && (
                  <span className="absolute -top-1 -right-1 flex h-4 w-4 items-center justify-center rounded-full bg-[#00C2FF] text-[9px] font-black text-slate-900 border border-[#0B0E14]">
                    {hiddenItems.size}
                  </span>
                )}
              </button>
            </div>

            {/* Hero Summary Card */}
            <div className="rounded-2xl border border-white/20 bg-white/10 p-5 sm:p-7 backdrop-blur-md shadow-sm text-white">
              <div className="relative z-10 flex flex-col gap-2.5">
                <div className="flex flex-wrap items-center gap-2">
                  <span className="flex items-center gap-1 rounded-full bg-white/15 border border-white/20 px-2.5 py-0.5 text-[10px] font-semibold text-white/90">
                    {tenantName}
                  </span>
                </div>

                <div>
                  <h1 className="font-display text-xl sm:text-2xl font-bold tracking-tight text-white">
                    Daftar Lengkap Fitur NODERA Enterprise
                  </h1>
                  <p className="mt-1 text-xs text-sky-100/90 font-normal leading-relaxed">
                    Akses cepat ke seluruh modul manajemen ISP, billing, hotspot, router, dan infrastruktur sistem.
                  </p>
                </div>
              </div>
            </div>
          </div>
        </section>

        {/* Page Content Body */}
        <div className="max-w-7xl 2xl:max-w-[1600px] mx-auto px-4 sm:px-8 pt-5 sm:pt-6 relative z-10 space-y-6">

          {/* ── TOOLBAR: SEARCH & CATEGORY FILTERS ── */}
          <div className="space-y-3">
            {/* Search Bar */}
            <div className="relative">
              <Search className="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
              <input
                type="text"
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                placeholder="Cari modul atau fitur (contoh: PPPoE, Hotspot, OLT, QRIS, Invoice, Notifikasi)..."
                className="w-full h-11 rounded-2xl border border-[#212B3B] bg-[#0B0E14] pl-10 pr-9 text-xs text-white placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-[#0073C6] shadow-sm font-medium"
              />
              {search && (
                <button
                  onClick={() => setSearch("")}
                  className="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-white"
                >
                  <X className="h-4 w-4" />
                </button>
              )}
            </div>

            {/* Category Filter Pills */}
            <div className="flex items-center gap-2 overflow-x-auto pb-1 no-scrollbar">
              {[
                { id: "all", label: "Semua Modul" },
                { id: "billing", label: "Billing & Pembayaran" },
                { id: "keuangan", label: "Keuangan & Kas" },
                { id: "mikrotik", label: "Jaringan MikroTik" },
                { id: "voucher", label: "Voucher Hotspot" },
                { id: "fiber", label: "Fiber & OLT" },
                { id: "operasional", label: "Operasional" },
                { id: "sistem", label: "Sistem & Integrasi" },
              ].map((cat) => {
                const isActive = activeCategory === cat.id
                return (
                  <button
                    key={cat.id}
                    onClick={() => setActiveCategory(cat.id)}
                    className={cn(
                      "shrink-0 rounded-xl px-3.5 py-1.5 text-xs font-bold transition-all active:scale-95 cursor-pointer",
                      isActive
                        ? "bg-[#0073C6] text-white border border-[#0094FF]/40"
                        : "border border-[#212B3B] bg-[#0B0E14] text-slate-400 hover:text-white hover:bg-[#121720]"
                    )}
                  >
                    {cat.label}
                  </button>
                )
              })}
            </div>
          </div>

        {/* ── GROUPS & COMPACT ICON LAUNCHER CARDS ── */}
        <div className="space-y-5">
          {filteredGroups.length === 0 ? (
            <div className="rounded-2xl border border-dashed border-[#1E2633] bg-[#0B0E14] p-12 text-center text-white space-y-2">
              <Grid className="mx-auto h-10 w-10 text-slate-500 mb-1" />
              <h3 className="text-sm font-bold text-white">Tidak ada modul yang cocok</h3>
              <p className="text-xs text-slate-400 max-w-sm mx-auto">
                Silakan coba kata kunci pencarian lain atau pilih kategori Semua Modul.
              </p>
            </div>
          ) : (
            filteredGroups.map((group) => {
              const GroupIcon = group.icon
              return (
                <div
                  key={group.id}
                  className="rounded-2xl border border-[#1E2633] bg-[#121720] p-4 sm:p-5 shadow-sm space-y-4 text-white"
                >
                  {/* Card Header per Group */}
                  <div className="flex items-center justify-between border-b border-[#1E2633]/80 pb-3">
                    <div className="flex items-center gap-2.5">
                      <div className="flex h-7 w-7 items-center justify-center rounded-xl bg-white/15 border border-white/20 text-[#00C2FF]">
                        <GroupIcon className="h-4 w-4" />
                      </div>
                      <h3 className="font-display text-xs font-bold uppercase tracking-wider text-slate-200">
                        {group.name}
                      </h3>
                    </div>
                    <span className="rounded-full border border-[#212B3B] bg-[#0B0E14] px-2.5 py-0.5 text-[10px] font-bold text-slate-400">
                      {group.items.length} Fitur
                    </span>
                  </div>

                  {/* Compact Icon Grid Launcher (4 cols Mobile, 5-7 Desktop) */}
                  <div className="grid grid-cols-4 sm:grid-cols-5 md:grid-cols-6 lg:grid-cols-7 gap-y-3 gap-x-2">
                    {group.items.map((item) => {
                      const Icon = item.icon

                      return (
                        <Link
                          key={item.href}
                          href={item.href}
                          className="flex flex-col items-center gap-1.5 text-center group active:scale-95 transition-transform"
                        >
                          <div
                            className={cn(
                              "flex h-12 w-12 items-center justify-center rounded-2xl text-white transition-transform group-hover:scale-110 shrink-0 shadow-md",
                              item.gradient
                            )}
                          >
                            <Icon className="h-6 w-6 text-white" />
                          </div>

                          <span className="text-[10px] font-semibold leading-tight line-clamp-2 w-full px-0.5 text-slate-300 group-hover:text-white transition-colors">
                            {item.title}
                          </span>
                        </Link>
                      )
                    })}
                  </div>
                </div>
              )
            })
          )}
        </div>
      </div>

    {/* =========================================================================
        MODAL: PENGATURAN VISIBILITAS MENU & SIDEBAR (Signature Mobile-First Bottom Sheet)
        ========================================================================= */}
    {visibilityModalOpen && (
      <div className="fixed inset-0 z-50 flex items-end sm:items-center justify-center bg-black/80 backdrop-blur-sm p-0 sm:p-4 animate-in fade-in duration-200">
        <div
          className="w-full sm:max-w-2xl max-h-[90vh] sm:max-h-[85vh] flex flex-col rounded-t-[2rem] sm:rounded-2xl border border-[#1E2633] bg-[#0E131B] text-white shadow-sm overflow-hidden"
          onClick={(e) => e.stopPropagation()}
        >
          {/* Modal Header */}
          <div className="flex items-center justify-between border-b border-[#1E2633] px-5 py-4 bg-[#121720] shrink-0">
            <div className="flex items-center gap-3">
              <div className="flex h-9 w-9 items-center justify-center rounded-xl bg-[#0073C6]/20 border border-[#0094FF]/40 text-[#00C2FF]">
                <SlidersHorizontal className="h-5 w-5" />
              </div>
              <div>
                <h3 className="font-display text-sm sm:text-base font-bold text-white">Atur Menu &amp; Fitur</h3>
                <p className="text-[11px] text-slate-400">Pilih menu yang ingin ditampilkan di Sidebar</p>
              </div>
            </div>
            <button
              type="button"
              onClick={() => setVisibilityModalOpen(false)}
              className="flex h-8 w-8 items-center justify-center rounded-xl border border-[#212B3B] bg-[#0B0E14] text-slate-400 hover:text-white hover:bg-[#18202C] transition-all"
            >
              <X className="h-4 w-4" />
            </button>
          </div>

          {/* Quick Presets Bar */}
          <div className="px-5 py-2.5 bg-[#0B0E14] border-b border-[#1E2633] flex items-center justify-between gap-2 overflow-x-auto shrink-0">
            <div className="flex items-center gap-2">
              <button
                type="button"
                onClick={showAllFeatures}
                className="rounded-xl border border-[#212B3B] bg-[#121720] hover:bg-[#1A2230] px-3 py-1.5 text-[11px] font-bold text-[#00C2FF] transition-all active:scale-95"
              >
                Tampilkan Semua
              </button>
              <button
                type="button"
                onClick={showEssentialOnly}
                className="rounded-xl border border-[#212B3B] bg-[#121720] hover:bg-[#1A2230] px-3 py-1.5 text-[11px] font-bold text-amber-400 transition-all active:scale-95"
              >
                Hanya Fitur Utama
              </button>
            </div>
            <span className="text-[11px] font-semibold text-slate-400 shrink-0">
              {hiddenItems.size > 0 ? `${hiddenItems.size} Menu Disembunyikan` : "Semua Menu Aktif"}
            </span>
          </div>

          {/* Search inside Modal */}
          <div className="px-5 py-3 border-b border-[#1E2633] bg-[#0E131B] shrink-0">
            <div className="relative">
              <Search className="pointer-events-none absolute left-3.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-slate-400" />
              <input
                type="text"
                value={modalSearch}
                onChange={(e) => setModalSearch(e.target.value)}
                placeholder="Cari fitur untuk diatur..."
                className="w-full h-9 rounded-xl border border-[#212B3B] bg-[#0B0E14] pl-9 pr-8 text-xs text-white placeholder:text-slate-500 focus:outline-none focus:ring-1 focus:ring-[#0073C6]"
              />
              {modalSearch && (
                <button
                  onClick={() => setModalSearch("")}
                  className="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-white"
                >
                  <X className="h-3.5 w-3.5" />
                </button>
              )}
            </div>
          </div>

          {/* Modal Scrollable Content */}
          <div className="flex-1 overflow-y-auto p-5 space-y-6 scrollbar-none">
            {featureGroups.map((group) => {
              const q = modalSearch.toLowerCase().trim()
              const matchingItems = group.items.filter(
                (item) =>
                  !lockedRouteSet.has(item.href) &&
                  (!q || item.title.toLowerCase().includes(q) || item.desc.toLowerCase().includes(q))
              )
              if (matchingItems.length === 0) return null

              return (
                <div key={group.id} className="space-y-2.5">
                  <div className="flex items-center gap-2 pb-1 border-b border-[#1E2633]">
                    <group.icon className="h-4 w-4 text-[#00C2FF]" />
                    <h4 className="text-xs font-bold uppercase tracking-wider text-slate-300">{group.name}</h4>
                  </div>

                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-2">
                    {matchingItems.map((item) => {
                      const isHidden = hiddenItems.has(item.href)
                      const isCore = item.href === "/dashboard" || item.href === "/admin/semua-fitur" || item.href === "/admin/my-settings"
                      const ItemIcon = item.icon

                      return (
                        <div
                          key={item.href}
                          onClick={() => {
                            if (!isCore) toggleFeatureVisibility(item.href)
                          }}
                          className={cn(
                            "flex items-center justify-between gap-3 p-2.5 rounded-2xl border transition-all cursor-pointer select-none",
                            isCore
                              ? "border-[#1E2633] bg-[#0B0E14]/70 opacity-80 cursor-not-allowed"
                              : isHidden
                              ? "border-[#212B3B] bg-[#0B0E14] text-slate-500 hover:border-slate-700"
                              : "border-[#0073C6]/40 bg-[#0B2138]/50 text-white hover:border-[#0094FF]/60"
                          )}
                        >
                          <div className="flex items-center gap-2.5 min-w-0">
                            <div
                              className={cn(
                                "flex h-8 w-8 items-center justify-center rounded-xl shrink-0 text-white",
                                item.gradient,
                                isHidden && "opacity-40 grayscale"
                              )}
                            >
                              <ItemIcon className="h-4 w-4" />
                            </div>
                            <div className="min-w-0">
                              <p className={cn("text-xs font-bold truncate", isHidden ? "text-slate-400" : "text-white")}>
                                {item.title}
                              </p>
                              <p className="text-[10px] text-slate-500 truncate">{item.href}</p>
                            </div>
                          </div>

                          <div className="shrink-0 flex items-center">
                            {isCore ? (
                              <span className="text-[9px] font-bold text-slate-500 px-2 py-0.5 rounded-md bg-[#121720] border border-[#212B3B]">
                                Wajib
                              </span>
                            ) : isHidden ? (
                              <div className="flex items-center gap-1 text-[10px] font-bold text-slate-500 px-2 py-1 rounded-lg bg-[#121720] border border-[#212B3B]">
                                <EyeOff className="h-3 w-3" />
                                <span>Sembunyi</span>
                              </div>
                            ) : (
                              <div className="flex items-center gap-1 text-[10px] font-bold text-[#00C2FF] px-2 py-1 rounded-lg bg-[#0073C6]/20 border border-[#0094FF]/40">
                                <Eye className="h-3 w-3" />
                                <span>Tampil</span>
                              </div>
                            )}
                          </div>
                        </div>
                      )
                    })}
                  </div>
                </div>
              )
            })}
          </div>

          {/* Modal Sticky Footer */}
          <div className="p-4 bg-[#121720] border-t border-[#1E2633] flex items-center justify-between gap-3 shrink-0">
            <button
              type="button"
              onClick={() => setVisibilityModalOpen(false)}
              className="h-10 px-4 rounded-xl border border-[#212B3B] bg-[#0B0E14] text-xs font-bold text-slate-300 hover:text-white transition-all active:scale-95"
            >
              Batal
            </button>
            <button
              type="button"
              onClick={saveAndCloseModal}
              className="flex-1 h-10 rounded-xl bg-gradient-to-r from-[#0073C6] to-[#0096C7] hover:from-[#0084E3] hover:to-[#00A8DE] border border-[#00C2FF]/40 text-white font-bold text-xs active:scale-95 transition-all flex items-center justify-center gap-2"
            >
              {savedToast ? (
                <>
                  <Check className="h-4 w-4 text-emerald-400" />
                  <span>Tersimpan!</span>
                </>
              ) : (
                <>
                  <Check className="h-4 w-4" />
                  <span>Simpan &amp; Terapkan ke Sidebar</span>
                </>
              )}
            </button>
          </div>
        </div>
      </div>
    )}
      </div>
    </AppLayout>
  )
}
