import React, { useState, useEffect, useRef, useMemo } from "react"
import { createPortal } from "react-dom"
import { Link, usePage, router } from "@inertiajs/react"
import { useSidebar } from "@/context/SidebarContext"
import { cn } from "@/lib/utils"
import {
  LayoutDashboard,
  Users,
  BookUser,
  Receipt,
  Wifi,
  CreditCard,
  Settings,
  Server,
  Activity,
  FileText,
  Shield,
  HelpCircle,
  QrCode,
  ChevronDown,
  Layers,
  Database,
  Radio,
  DollarSign,
  MapPin,
  ShieldCheck,
  Globe,
  Package,
  Boxes,
  KeyRound,
  ShoppingBag,
  Wallet,
  TrendingDown,
  Network,
  SlidersHorizontal,
  Gauge,
  LifeBuoy,
  UserCog,
  User,
  MessageSquare,
  Send,
  History,
  Puzzle,
  Webhook,
  Terminal,
  Smartphone,
  GraduationCap,
  X,
  ChevronLeft,
  ExternalLink,
  ShieldAlert,
  Check,
} from "lucide-react"

export interface SidebarSubItem {
  name: string
  href: string
  badge?: string
}

export interface NavItemConfig {
  name: string
  href?: string
  icon: React.ComponentType<{ className?: string }>
  badge?: string | number
  subItems?: SidebarSubItem[]
}

export interface NavGroupConfig {
  name: string
  items: NavItemConfig[]
}

interface AppSidebarProps {
  customItems?: {
    groups?: NavGroupConfig[]
    menu?: NavItemConfig[]
    others?: NavItemConfig[]
  }
  footer?: React.ReactNode
  brand?: {
    name: string
    sub?: string
    logo?: string
    href?: string
  }
}

// 1:1 TailAdmin Official Navigation Hierarchy (Module-level referential constants)
const defaultAdminGroups: NavGroupConfig[] = [
  {
    name: "MENU",
    items: [
      {
        name: "Dashboard",
        href: "/dashboard",
        icon: LayoutDashboard,
      },
      {
        name: "Billing & Pelanggan",
        icon: Users,
        subItems: [
          { name: "Semua Pelanggan", href: "/admin/billing/customers" },
          { name: "Invoice & Tagihan", href: "/admin/billing/invoices" },
          { name: "Paket Langganan", href: "/admin/billing/packages" },
          { name: "Payment Gateway", href: "/admin/payments/gateway" },
        ],
      },
      {
        name: "Toko Online",
        icon: ShoppingBag,
        subItems: [
          { name: "Katalog Produk", href: "/admin/shop/products" },
          { name: "Pesanan Masuk", href: "/admin/shop/orders" },
          { name: "Landing Page Portal", href: "/admin/landing-settings" },
        ],
      },
      {
        name: "Keuangan & Kas",
        icon: Wallet,
        subItems: [
          { name: "Laporan Keuangan", href: "/admin/finance" },
          { name: "Pengeluaran Kas", href: "/admin/expenses" },
        ],
      },
      {
        name: "Jaringan MikroTik",
        icon: Server,
        subItems: [
          { name: "Router MikroTik", href: "/admin/mikrotik/routers" },
          { name: "RADIUS Server & CoA", href: "/admin/radius" },
          { name: "PPPoE Secrets", href: "/admin/pppoe" },
          { name: "ARP Static & Binding", href: "/admin/arp" },
          { name: "Profil Bandwidth", href: "/admin/mikrotik/profiles" },
          { name: "Top Bandwidth", href: "/admin/top-bandwidth" },
          { name: "Studio Script & Tools", href: "/tools/mikrotik" },
        ],
      },
      {
        name: "Layanan Mikhmon",
        icon: Wifi,
        subItems: [
          { name: "Mikhmon Online (Cloud)", href: "/admin/mikhmon" },
          { name: "Mikhmon Offline (Lisensi)", href: "/admin/desktop-licenses" },
        ],
      },
      {
        name: "Fiber Optik & OLT",
        icon: Database,
        subItems: [
          { name: "OLT & ONU GPON/EPON", href: "/admin/olt" },
          { name: "GenieACS TR-069 (ONT)", href: "/admin/ont-devices" },
          { name: "Peta Jaringan GIS & ODP", href: "/admin/map" },
          { name: "VPN Remote MikroTik", href: "/admin/vpn" },
        ],
      },
      {
        name: "Operasional & Staff",
        icon: LifeBuoy,
        subItems: [
          { name: "Template Notifikasi WA", href: "/admin/whatsapp-templates" },
          { name: "Bot & Notifikasi Telegram", href: "/admin/telegram" },
          { name: "Tiket Gangguan", href: "/admin/trouble" },
          { name: "Inventory Gudang", href: "/admin/inventory" },
          { name: "Karyawan & Staff", href: "/admin/employees" },
          { name: "Log Aktivitas Sistem", href: "/admin/notifications" },
        ],
      },
      {
        name: "Add-ons & Ekosistem",
        icon: Puzzle,
        subItems: [
          { name: "GenieACS TR-069 & Cloud ONT", href: "#addon-genieacs" },
          { name: "Paket Isolir & Webproxy", href: "#addon-isolir" },
          { name: "Server MIKHMON Online", href: "#addon-mikhmon" },
          { name: "RADIUS Server & CoA", href: "#addon-radius" },
        ],
      },
    ],
  },
  {
    name: "OTHERS",
    items: [
      {
        name: "Pengaturan Sistem",
        icon: Settings,
        subItems: [
          { name: "Pembaruan Sistem (1-Klik)", href: "/admin/system-update" },
          { name: "Konfigurasi Usaha & Profil", href: "/admin/my-settings" },
          { name: "API Apps & Webhook", href: "/admin/api-apps" },
        ],
      },
    ],
  },
  {
    name: "EKOSISTEM & KOMUNITAS",
    items: [
      {
        name: "Ekosistem NODERA",
        icon: Globe,
        subItems: [
          {
            name: "Cloud Panel",
            href: "https://panel.dgtlnetsolution.com/login",
          },
          {
            name: "WhatsApp Gateway",
            href: "https://wa.dgtlnetsolution.com/login",
          },
          {
            name: "Payment Gateway",
            href: "https://gateway.dgtlnetsolution.com/login",
          },
        ],
      },
      {
        name: "Komunitas NODERA",
        href: "#community",
        icon: MessageSquare,
      },
    ],
  },
]

const defaultSuperadminGroups: NavGroupConfig[] = [
  {
    name: "UTAMA & OVERVIEW",
    items: [
      { name: "Dashboard Eksekutif", href: "/superadmin", icon: LayoutDashboard },
    ],
  },
  {
    name: "ISP BILLING SAAS & TENANT",
    items: [
      {
        name: "Kelola Tenant & Lisensi",
        icon: Users,
        subItems: [
          { name: "Daftar Tenants / ISP", href: "/superadmin/tenants" },
          { name: "Lisensi Source Code", href: "/superadmin/licenses" },
          { name: "Paket Langganan SaaS", href: "/superadmin/paket" },
          { name: "Kelola Add-ons", href: "/superadmin/addons" },
        ],
      },
    ],
  },
  {
    name: "CLOUD PANEL & INFRASTRUKTUR",
    items: [
      {
        name: "Layanan VPN",
        icon: Shield,
        subItems: [
          { name: "Live VPN Monitoring", href: "/superadmin/vpn/monitoring" },
          { name: "Akun & Layanan VPN", href: "/superadmin/vpn/accounts" },
          { name: "Pengguna & Member", href: "/superadmin/vpn/users" },
          { name: "Server VPN", href: "/superadmin/vpn-servers" },
          { name: "Auto Install CHR", href: "/superadmin/auto-install-chr" },
        ],
      },
      {
        name: "Layanan Mikhmon",
        icon: Wifi,
        subItems: [
          { name: "Mikhmon Online (Cloud)", href: "/superadmin/mikhmon" },
          { name: "Mikhmon Offline (Lisensi)", href: "/superadmin/desktop-licenses" },
        ],
      },
      {
        name: "Layanan Cloud",
        icon: Server,
        subItems: [
          { name: "Cloud Pembukuan", href: "/superadmin/bookkeeping" },
          { name: "Program Referral", href: "/superadmin/referrals" },
          { name: "GenieACS TR-069", href: "/superadmin/genieacs" },
          { name: "Docker & Daemon", href: "/superadmin/docker" },
        ],
      },
    ],
  },
  {
    name: "NODERA PAY GATEWAY",
    items: [
      {
        name: "NODERA PAY (QRIS)",
        icon: QrCode,
        subItems: [
          { name: "Dashboard Gateway", href: "/superadmin/noderapay" },
          { name: "Kelola Merchant", href: "/superadmin/noderapay/merchants" },
          { name: "Transaksi Gateway", href: "/superadmin/noderapay/transactions" },
          { name: "Penarikan Saldo (Payout)", href: "/superadmin/noderapay/withdrawals" },
          { name: "Konfigurasi Gateway", href: "/superadmin/noderapay/settings" },
        ],
      },
    ],
  },
  {
    name: "WHATSAPP GATEWAY",
    items: [
      {
        name: "WhatsApp Gateway",
        icon: Smartphone,
        subItems: [
          { name: "Dashboard WhatsApp", href: "/superadmin/wagateway/dashboard" },
          { name: "Kelola Merchant WA", href: "/superadmin/wagateway/merchants" },
          { name: "Antrean Pesan", href: "/superadmin/wagateway/queue" },
          { name: "Perangkat WhatsApp", href: "/superadmin/wagateway/devices" },
          { name: "Topup Saldo WA", href: "/superadmin/wagateway/topups" },
          { name: "Pengaturan WhatsApp", href: "/superadmin/wagateway/settings" },
        ],
      },
    ],
  },
  {
    name: "KEUANGAN & TOKO PLATFORM",
    items: [
      {
        name: "Finansial Platform",
        icon: Wallet,
        subItems: [
          { name: "Ringkasan Finansial", href: "/superadmin/finance" },
          { name: "Riwayat Pendapatan", href: "/superadmin/finance/history" },
          { name: "Pengeluaran Kas", href: "/superadmin/expenses" },
          { name: "Pesanan Toko", href: "/superadmin/shop/orders" },
          { name: "Katalog Produk Toko", href: "/superadmin/shop/products" },
        ],
      },
    ],
  },
  {
    name: "PENGATURAN PLATFORM",
    items: [
      {
        name: "Pengaturan Platform",
        icon: Settings,
        subItems: [
          { name: "Profil & Info Platform", href: "/superadmin/settings" },
          { name: "Payment Gateway Platform", href: "/superadmin/payment-gateway" },
          { name: "WhatsApp Gateway Platform", href: "/superadmin/whatsapp-gateway" },
          { name: "Template Notifikasi WA", href: "/superadmin/whatsapp-templates" },
          { name: "Keamanan 2FA Superadmin", href: "/superadmin/2fa/setup" },
          { name: "Backup Database", href: "/superadmin/backup" },
          { name: "Audit Logs Sistem", href: "/superadmin/audit-logs" },
          { name: "Client Logs", href: "/superadmin/client-logs" },
        ],
      },
    ],
  },
  {
    name: "UJIAN & MAGANG SISWA",
    items: [
      {
        name: "Ujian Magang Siswa",
        icon: GraduationCap,
        subItems: [
          { name: "Overview & Ringkasan", href: "/superadmin/pkl-exam" },
          { name: "Sesi Ujian & Timer", href: "/superadmin/pkl-exam?tab=sessions" },
          { name: "Bank Soal Ujian", href: "/superadmin/pkl-exam?tab=questions" },
          { name: "Peserta Siswa PKL", href: "/superadmin/pkl-exam?tab=students" },
          { name: "Hasil Ujian & PDF", href: "/superadmin/pkl-exam?tab=leaderboard" },
          { name: "Bot Telegram & Grup", href: "/superadmin/pkl-exam?tab=settings" },
        ],
      },
    ],
  },
  {
    name: "EKOSISTEM & KOMUNITAS",
    items: [
      {
        name: "Ekosistem NODERA",
        icon: Globe,
        subItems: [
          {
            name: "Cloud Panel",
            href: "https://panel.dgtlnetsolution.com/login",
          },
          {
            name: "WhatsApp Gateway",
            href: "https://wa.dgtlnetsolution.com/login",
          },
          {
            name: "Payment Gateway",
            href: "https://gateway.dgtlnetsolution.com/login",
          },
        ],
      },
      {
        name: "Komunitas NODERA",
        href: "#community",
        icon: MessageSquare,
      },
    ],
  },
]

const defaultVpnGroups: NavGroupConfig[] = [
  {
    name: "LAYANAN CLOUD",
    items: [
      { name: "Dashboard", href: "/dashboard", icon: LayoutDashboard },
      {
        name: "Layanan VPN",
        icon: Shield,
        subItems: [
          { name: "VPN Remot", href: "/akun" },
          { name: "VPN Interkoneksi", href: "/interkoneksi" },
        ],
      },
      {
        name: "Layanan Mikhmon",
        icon: Wifi,
        subItems: [
          { name: "Mikhmon Online (Cloud)", href: "/mikhmon" },
          { name: "Mikhmon Offline (Lisensi)", href: "/desktop-licenses" },
        ],
      },
      {
        name: "Layanan Cloud",
        icon: Server,
        subItems: [
          { name: "SaaS Billing ISP", href: "/isp-billing" },
          { name: "Kas & Pembukuan", href: "/bookkeeping" },
          { name: "Aplikasi Arisan", href: "/arisan" },
        ],
      },
      {
        name: "Keuangan & Saldo",
        icon: DollarSign,
        subItems: [
          { name: "Topup Saldo", href: "/topup" },
          { name: "Riwayat Transaksi", href: "/history" },
          { name: "Program Referral", href: "/referral" },
        ],
      },
    ],
  },
  {
    name: "EKOSISTEM & KOMUNITAS",
    items: [
      {
        name: "Ekosistem NODERA",
        icon: Globe,
        subItems: [
          {
            name: "WhatsApp Gateway",
            href: "https://wa.dgtlnetsolution.com/login",
          },
          {
            name: "Payment Gateway",
            href: "https://gateway.dgtlnetsolution.com/login",
          },
        ],
      },
      {
        name: "Komunitas NODERA",
        href: "#community",
        icon: MessageSquare,
      },
    ],
  },
  {
    name: "PENGATURAN",
    items: [
      {
        name: "Profil Akun",
        href: "/profil",
        icon: User,
      },
    ],
  },
]

const defaultPortalGroups: NavGroupConfig[] = [
  {
    name: "MENU",
    items: [
      { name: "Dashboard", href: "/portal", icon: LayoutDashboard },
      {
        name: "Layanan Internet",
        icon: Activity,
        subItems: [
          { name: "Tagihan & Invoice", href: "/portal/invoices" },
          { name: "Penggunaan Kuota (FUP)", href: "/portal/usage" },
        ],
      },
    ],
  },
  {
    name: "OTHERS",
    items: [
      {
        name: "Pusat Bantuan & Akun",
        icon: HelpCircle,
        subItems: [
          { name: "Ganti Sandi WiFi", href: "/portal/edit-wifi" },
          { name: "Buat Laporan Gangguan", href: "/portal/laporan" },
          { name: "Syarat & Ketentuan", href: "/portal/tos" },
        ],
      },
    ],
  },
]

const defaultTeknisiGroups: NavGroupConfig[] = [
  {
    name: "MENU",
    items: [
      { name: "Beranda Tiket", href: "/teknisi/dashboard", icon: LayoutDashboard },
      {
        name: "Tugas Lapangan",
        icon: Layers,
        subItems: [
          { name: "Pool Tiket Gangguan", href: "/teknisi/pool" },
          { name: "PPPoE & Redaman Optik", href: "/teknisi/pppoe" },
        ],
      },
      {
        name: "Data & Pemetaan",
        icon: MapPin,
        subItems: [
          { name: "Kelola Pelanggan", href: "/teknisi/customers" },
          { name: "Peta Jaringan GIS", href: "/teknisi/map" },
          { name: "Riwayat Pekerjaan", href: "/teknisi/history" },
        ],
      },
    ],
  },
  {
    name: "OTHERS",
    items: [
      {
        name: "Akun & Komisi",
        icon: Settings,
        subItems: [
          { name: "Komisi & Insentif", href: "/teknisi/earnings" },
          { name: "Profil Teknisi", href: "/teknisi/profile" },
        ],
      },
    ],
  },
]

const defaultKolektorGroups: NavGroupConfig[] = [
  {
    name: "MENU",
    items: [
      { name: "Dashboard", href: "/kolektor/dashboard", icon: LayoutDashboard },
      { name: "Invoice", href: "/kolektor/invoices", icon: Receipt },
      {
        name: "Penagihan Lapangan",
        icon: Layers,
        subItems: [
          { name: "Kelola Pelanggan", href: "/kolektor/customers" },
          { name: "Pool Pekerjaan Penagihan", href: "/kolektor/pool" },
          { name: "Peta Pelanggan GIS", href: "/kolektor/map" },
        ],
      },
    ],
  },
  {
    name: "OTHERS",
    items: [
      {
        name: "Setoran & Komisi",
        icon: Settings,
        subItems: [
          { name: "Riwayat Selesai", href: "/kolektor/history" },
          { name: "Pendapatan Komisi", href: "/kolektor/earnings" },
          { name: "Profil Kolektor", href: "/kolektor/profile" },
        ],
      },
    ],
  },
]

export const AppSidebar: React.FC<AppSidebarProps> = ({ customItems, footer, brand }) => {
  const { isExpanded, isMobileOpen, isHovered, setIsHovered, setIsMobileOpen, toggleSidebar } = useSidebar()
  const { url, props } = usePage()
  const monthlyRevenue = Number(
    (props as any)?.monthly_revenue ??
    (props as any)?.monthlyRevenue ??
    (props as any)?.pendapatanBulanIni ??
    (props as any)?.totalRevenue ??
    0
  )

  // Auto close mobile sidebar on navigation
  useEffect(() => {
    if (isMobileOpen) {
      setIsMobileOpen(false)
    }
  }, [url])

  const isParentActive = (item: NavItemConfig) => {
    if (item.href) {
      const currentPath = url.split("?")[0].replace(/\/+$/, "")
      const targetPath = item.href.split("?")[0].replace(/\/+$/, "")
      if (
        targetPath === "/admin" ||
        targetPath === "/dashboard" ||
        targetPath === "/superadmin" ||
        targetPath === "/vpn" ||
        targetPath === "/portal"
      ) {
        return currentPath === targetPath || (targetPath === "/dashboard" && currentPath === "/admin")
      }
      return currentPath === targetPath || currentPath.startsWith(targetPath + "/")
    }
    if (item.subItems) {
      return item.subItems.some((sub) => isSubActive(sub.href))
    }
    return false
  }

  const { cloud_panel_balance, active_addons } = (props as any) || {}
  const cloudPanelBalance = Number(cloud_panel_balance ?? 0)

  const isAddonActive = (key: string) => {
    if (!active_addons || !Array.isArray(active_addons)) return false
    const k = key.toLowerCase().replace("#addon-", "")
    return active_addons.some((a: any) => {
      const s = String(a.slug || a.name || "").toLowerCase()
      if (k === "genieacs" || k === "ont") {
        return s.includes("genieacs") || s.includes("ont")
      }
      if (k === "isolir" || k === "proxy") {
        return s.includes("isolir") || s.includes("proxy")
      }
      if (k === "mikhmon") {
        return s.includes("mikhmon") && !s.includes("warung")
      }
      if (k === "olt") {
        return s.includes("olt")
      }
      if (k === "radius") {
        return s.includes("radius")
      }
      if (k === "map" || k === "gis") {
        return s.includes("map") || s.includes("gis")
      }
      return s === k || s.includes(k)
    })
  }

  const [ecosystemInfo, setEcosystemInfo] = useState<{
    isOpen: boolean
    title: string
    badge: string
    description: string
    subdomainUrl: string
    features: string[]
  } | null>(null)

  const [addonModalInfo, setAddonModalInfo] = useState<{
    isOpen: boolean
    slug: string
    title: string
    badge: string
    price: string
    rawPrice: number
    billingCycle: string
    isMonthly: boolean
    description: string
    routeUrl: string
    icon: React.ComponentType<{ className?: string }>
    features: string[]
    isActive: boolean
  } | null>(null)

  const [agreedMonthly, setAgreedMonthly] = useState(false)
  const [isPurchasing, setIsPurchasing] = useState(false)
  const [actionError, setActionError] = useState<string | null>(null)

  const [showCommunityModal, setShowCommunityModal] = useState(false)

  const handleActivateAddon = (slug: string, isMonthly: boolean) => {
    setIsPurchasing(true)
    setActionError(null)
    router.post(
      `/admin/addons/buy/${slug}`,
      { auto_renew: isMonthly },
      {
        preserveScroll: true,
        onSuccess: () => {
          setIsPurchasing(false)
          setAddonModalInfo(null)
        },
        onError: (err: any) => {
          setIsPurchasing(false)
          setActionError(
            err?.error ||
              err?.message ||
              "Gagal mengaktifkan add-on. Pastikan saldo Cloud Panel mencukupi."
          )
        },
      }
    )
  }

  const handleAddonClick = (slugOrHref: string) => {
    setAgreedMonthly(false)
    setIsPurchasing(false)
    setActionError(null)

    const key = slugOrHref.replace("#addon-", "").toLowerCase()

    if (key.includes("genieacs") || key.includes("ont")) {
      setAddonModalInfo({
        isOpen: true,
        slug: "genieacs_management",
        title: "GenieACS TR-069 & Cloud ONT",
        badge: "PAYG CLOUD ONT",
        price: "Rp 1.000 / modem / bulan",
        rawPrice: 0,
        billingCycle: "PAYG Bulanan (10 Modem Pertama Gratis)",
        isMonthly: true,
        description:
          "Sistem manajemen massal dan otomatisasi ONT/Modem pelanggan (ZTE, Huawei, Fiberhome, VSOL, dsb) berbasis protokol standar TR-069 CWMP terintegrasi ke Nodera Cloud. Menghemat waktu teknisi dengan kontrol jarak jauh tanpa perlu kunjungan fisik ke rumah pelanggan.",
        routeUrl: "/admin/ont-devices",
        icon: Wifi,
        isActive: isAddonActive("genieacs"),
        features: [
          "Multi-Vendor ONT: Terhubung seragam ke semua merk modem (ZTE F609/F670L, Huawei HG8245H5/EG8145V5, Fiberhome, VSOL, dll).",
          "Telemetri Optik Real-time: Pantau Rx/Tx Optical Power (dBm), status redaman (Bagus/Waspada/Kritis), suhu & uptime modem.",
          "Remote Kontrol WiFi & Reboot: Ganti Nama WiFi (SSID) & Password langsung dari panel, serta Remote Reboot 1-klik.",
          "Skema PAYG Hemat: 10 Modem pertama 100% GRATIS selamanya. Modem aktif berikutnya hanya Rp 1.000 / modem / bulan.",
        ],
      })
    } else if (key.includes("isolir") || key.includes("proxy")) {
      setAddonModalInfo({
        isOpen: true,
        slug: "paket_isolir",
        title: "Paket Isolir & Web Proxy Otomatis",
        badge: "MIKROTIK AUTO-ISOLIR",
        price: "Rp 10.000",
        rawPrice: 10000,
        billingCycle: "Sekali Bayar (Lifetime)",
        isMonthly: false,
        description:
          "Sistem isolir otomatis pelanggan menunggak dengan halaman peringatan interaktif error.html, redirect port 80/443, dan script generator MikroTik instan.",
        routeUrl: "/admin/addons",
        icon: ShieldAlert,
        isActive: isAddonActive("isolir"),
        features: [
          "Halaman Tagihan Interaktif error.html Khusus Pelanggan Menunggak",
          "Bypass Pembayaran QRIS & Virtual Account Tanpa Buka Isolir Manual",
          "Script Generator Firewall NAT & Filter MikroTik Sekali Klik",
        ],
      })
    } else if (key.includes("mikhmon") && !key.includes("warung")) {
      setAddonModalInfo({
        isOpen: true,
        slug: "mikhmon_online",
        title: "Server MIKHMON Online Cloud",
        badge: "CLOUD HOTSPOT ENGINE",
        price: "Rp 10.000 / bulan",
        rawPrice: 10000,
        billingCycle: "Langganan Bulanan (Per Router)",
        isMonthly: true,
        description:
          "Server MIKHMON Online berbasis cloud terisolasi per tenant. Cetak voucher hotspot, pantau sesi user aktif, dan transaksi dari mana saja tanpa butuh IP publik statis.",
        routeUrl: "/admin/mikhmon",
        icon: Wifi,
        isActive: isAddonActive("mikhmon"),
        features: [
          "Akses Dashboard Mikhmon Online dari Luar Jaringan Tanpa VPN/IP Publik",
          "Cetak Voucher Hotspot Cepat & Template Voucher Kustom Responsif",
          "Sinkronisasi Multi-Router MikroTik & Log Penjualan Realtime",
        ],
      })
    } else if (key.includes("olt")) {
      setAddonModalInfo({
        isOpen: true,
        slug: "olt_management",
        title: "OLT PON & ONU Management",
        badge: "EPON & GPON NMS",
        price: "Rp 25.000 / bulan",
        rawPrice: 25000,
        billingCycle: "Langganan Bulanan",
        isMonthly: true,
        description:
          "Sistem monitoring dan manajemen OLT EPON / GPON terpadu (HiOSO, VSOL, ZTE, Huawei, HSGQ). Pantau optical power Rx/Tx port PON, temukan ONU baru unconfigured secara otomatis, dan reboot ONU jarak jauh.",
        routeUrl: "/admin/olt",
        icon: Server,
        isActive: isAddonActive("olt"),
        features: [
          "Auto Discovery ONU Baru Unconfigured di Setiap Port PON",
          "Monitoring Optical Power (Rx/Tx dBm) & Status Link Fiber Realtime",
          "Provisioning Profil Bandwidth & Remote Reboot ONU Terintegrasi",
        ],
      })
    } else if (key.includes("radius")) {
      setAddonModalInfo({
        isOpen: true,
        slug: "radius_server",
        title: "RADIUS Server & RFC 3576 CoA",
        badge: "ENTERPRISE AAA",
        price: "Rp 20.000 / bulan",
        rawPrice: 20000,
        billingCycle: "Langganan Bulanan",
        isMonthly: true,
        description:
          "Sistem otentikasi terpusat AAA (Authentication, Authorization, Accounting) dengan dukungan RFC 3576 CoA Disconnect Message untuk manajemen ribuan sesi PPPoE dan Hotspot secara instan.",
        routeUrl: "/admin/radius",
        icon: Radio,
        isActive: isAddonActive("radius"),
        features: [
          "Otentikasi Terpusat PPPoE / IPoE & Hotspot Skala Ribuan User",
          "RFC 3576 CoA: Putus atau Ubah Kecepatan User Realtime Tanpa Reconnect Manual",
          "High Availability & Accounting Sesi Pelanggan Otomatis",
        ],
      })
    } else if (key.includes("map") || key.includes("gis")) {
      setAddonModalInfo({
        isOpen: true,
        slug: "mapping_gis",
        title: "Peta Jaringan GIS & FTTH ODP",
        badge: "GEOGRAPHIC NETWORK",
        price: "Rp 15.000 / bulan",
        rawPrice: 15000,
        billingCycle: "Langganan Bulanan",
        isMonthly: true,
        description:
          "Peta geospasial interaktif FTTH untuk mendokumentasikan jalur kabel fiber optik, tiang, ODC, ODP, dan drop core ke pelanggan. Dilengkapi visualisasi kapasitas port dan kalkulator estimasi jarak OTDR.",
        routeUrl: "/admin/map",
        icon: MapPin,
        isActive: isAddonActive("map"),
        features: [
          "Visualisasi Jalur Kabel Fiber Optik & Titik Sebaran ODP Interaktif",
          "Manajemen Port ODP (Tersedia, Terpakai, Rusak) & Redaman Tiap Titik",
          "Estimasi Lacak Lokasi Putus Kabel (OTDR Distance Locator)",
        ],
      })
    } else {
      setAddonModalInfo({
        isOpen: true,
        slug: "addon_platform",
        title: "NODERA Add-ons Platform",
        badge: "MODUL TAMBAHAN",
        price: "Fleksibel",
        rawPrice: 0,
        billingCycle: "Sesuai Kebutuhan",
        isMonthly: false,
        description:
          "Ekosistem modul tambahan resmi NODERA untuk memperluas fungsionalitas ISP billing, otomasi jaringan MikroTik, dan layanan pelanggan Anda.",
        routeUrl: "/admin/addons",
        icon: Puzzle,
        isActive: false,
        features: [
          "Aktivasi Modular Sesuai Pertumbuhan Bisnis ISP Anda",
          "Terintegrasi Penuh dengan Billing, WhatsApp Gateway & MikroTik",
          "Dukungan Teknis & Pembaruan Otomatis Tanpa Biaya Tambahan",
        ],
      })
    }
  }

  const handleEcosystemClick = (name: string, url: string, badge?: string) => {
    if (name.toLowerCase().includes("whatsapp")) {
      setEcosystemInfo({
        isOpen: true,
        title: "NODERA WhatsApp Gateway",
        badge: "UNIVERSAL WA API",
        description:
          "Platform WhatsApp Gateway & API Multi-Device universal untuk segala jenis aplikasi, website, dan sistem backend. Kirim broadcast massal, OTP verifikasi, notifikasi transaksi, dan bot interaktif otomatis dengan integrasi REST API yang mudah dan stabil.",
        subdomainUrl: url,
        features: [
          "Koneksi Multi-Device & REST API Universal untuk Semua Aplikasi",
          "Broadcast Massal, Notifikasi Transaksi, OTP & Pengingat Otomatis",
          "Dukungan Webhook Interaktif, Auto-Reply Bot, & Dokumentasi Lengkap",
        ],
      })
    } else if (name.toLowerCase().includes("cloud") || name.toLowerCase().includes("vpn") || name.toLowerCase().includes("panel")) {
      setEcosystemInfo({
        isOpen: true,
        title: "NODERA Cloud Panel",
        badge: "MULTI-APP CLOUD PLATFORM",
        description:
          "Pusat ekosistem berbagai aplikasi cloud dan modul bisnis digital terintegrasi (VPN Remote, Mikhmon Online, SaaS Billing ISP, Kas & Pembukuan Digital, Arisan Online, GenieACS, dll.) dalam satu dashboard modern dan terpusat.",
        subdomainUrl: url,
        features: [
          "Akses & Kelola Aneka Aplikasi Cloud Modular dalam Satu Panel Terpadu",
          "Ekosistem Server VPN Remote, Mikhmon Online, SaaS Billing, & Tools Bisnis",
          "Deploy Cepat, Terisolasi, dan Siap Pakai Tanpa Setup Server Rumit",
        ],
      })
    } else {
      setEcosystemInfo({
        isOpen: true,
        title: "NODERA Payment Gateway",
        badge: "UNIVERSAL PAYMENT API",
        description:
          "Payment Gateway universal untuk menerima pembayaran otomatis di berbagai platform bisnis, website e-commerce, SaaS, dan aplikasi mobile. Mendukung QRIS Realtime, Virtual Account Bank Nasional, dan E-Wallet dengan auto-settlement instan serta API checkout yang fleksibel.",
        subdomainUrl: url,
        features: [
          "Menerima QRIS Dinamis & Virtual Account Seluruh Bank Nasional",
          "Integrasi REST API, SDK, & Webhook Callback Realtime",
          "Auto-Settlement, Dashboard Analitik Transaksi, & Payout Otomatis",
        ],
      })
    }
  }

  // Host & Subdomain Detection
  const host = typeof window !== "undefined" ? window.location.host : ""
  const isWaHost = host.includes("wa.") || host.includes("whatsapp.")
  const isGatewayHost = host.includes("gateway.") || host.includes("pay.")

  // Route Scope Detection
  const isSuperadmin = brand?.sub === "SUPERADMIN" || url.startsWith("/superadmin")

  const isWaGateway =
    !isSuperadmin &&
    (brand?.sub === "WA GATEWAY" ||
      isWaHost ||
      url.startsWith("/wagateway") ||
      url.startsWith("/devices") ||
      url.startsWith("/messages") ||
      url.startsWith("/broadcast") ||
      url.startsWith("/scheduled") ||
      url.startsWith("/contacts") ||
      url.startsWith("/phonebook") ||
      url.startsWith("/auto-reply") ||
      (url.startsWith("/billing") && !url.startsWith("/admin/billing") && !isGatewayHost))

  const isNoderaPay =
    !isSuperadmin &&
    !isWaGateway &&
    (brand?.sub === "PAYMENT GATEWAY" ||
      isGatewayHost ||
      url.startsWith("/noderapay") ||
      url.startsWith("/transactions") ||
      url.startsWith("/withdrawals") ||
      url.startsWith("/payment-methods"))

  const isPortal = brand?.sub === "PORTAL PELANGGAN" || url.startsWith("/portal")
  const isTeknisi = brand?.sub === "PORTAL TEKNISI" || url.startsWith("/teknisi")
  const isKolektor = brand?.sub === "PORTAL KOLEKTOR" || url.startsWith("/kolektor")

  const isVpn =
    !isSuperadmin &&
    !isNoderaPay &&
    !isWaGateway &&
    !isPortal &&
    !isTeknisi &&
    !isKolektor &&
    (brand?.sub === "CLOUD PANEL" ||
      brand?.sub === "VPN" ||
      url.startsWith("/vpn") ||
      url.startsWith("/akun") ||
      (url.startsWith("/mikhmon") && !url.startsWith("/admin/mikhmon")) ||
      url.startsWith("/topup") ||
      (url.startsWith("/referral") && !url.startsWith("/admin")) ||
      url.startsWith("/bookkeeping") ||
      url.startsWith("/arisan") ||
      (typeof window !== "undefined" && (window.location.host.includes("panel.") || window.location.host.includes("nel."))))

  const npPrefix = url.startsWith("/noderapay") ? "/noderapay" : ""
  const waPrefix = url.startsWith("/wagateway") ? "/wagateway" : ""

  // Resolve Groups memoized to prevent unnecessary re-evaluations
  const groups: NavGroupConfig[] = useMemo(() => {
    if (customItems?.groups && customItems.groups.length > 0) {
      return customItems.groups
    }
    if (isSuperadmin) return defaultSuperadminGroups
    if (isPortal) return defaultPortalGroups
    if (isTeknisi) return defaultTeknisiGroups
    if (isKolektor) return defaultKolektorGroups
    if (isNoderaPay) {
      return [
        {
          name: "MENU UTAMA",
          items: [
            { name: "Dashboard Gateway", href: `${npPrefix}/dashboard`, icon: LayoutDashboard },
            {
              name: "Transaksi & Saldo",
              icon: CreditCard,
              subItems: [
                { name: "Metode Pembayaran", href: `${npPrefix}/payment-methods` },
                { name: "Data Transaksi", href: `${npPrefix}/transactions` },
                { name: "Penarikan Saldo", href: `${npPrefix}/withdrawals` },
              ],
            },
          ],
        },
        {
          name: "PENGEMBANG & INTEGRASI",
          items: [
            {
              name: "Integrasi REST API",
              icon: SlidersHorizontal,
              subItems: [
                { name: "Kredensial API", href: `${npPrefix}/credentials` },
                { name: "Dokumentasi API", href: `${npPrefix}/docs` },
              ],
            },
          ],
        },
        {
          name: "PENGATURAN",
          items: [
            {
              name: "Pengaturan Toko",
              href: `${npPrefix}/settings`,
              icon: Settings,
            },
          ],
        },
        {
          name: "EKOSISTEM & KOMUNITAS",
          items: [
            {
              name: "Ekosistem NODERA",
              icon: Globe,
              subItems: [
                {
                  name: "Cloud Panel",
                  href: "https://panel.dgtlnetsolution.com/login",
                },
                {
                  name: "WhatsApp Gateway",
                  href: "https://wa.dgtlnetsolution.com/login",
                },
              ],
            },
            {
              name: "Komunitas NODERA",
              href: "#community",
              icon: MessageSquare,
            },
          ],
        },
      ]
    }
    if (isWaGateway) {
      return [
        {
          name: "MENU UTAMA",
          items: [
            { name: "Dashboard", href: `${waPrefix}/dashboard`, icon: LayoutDashboard },
            { name: "Perangkat WhatsApp", href: `${waPrefix}/devices`, icon: Smartphone },
            {
              name: "Phonebook",
              icon: BookUser,
              subItems: [
                { name: "Grup WA", href: `${waPrefix}/phonebook/wa-groups` },
                { name: "Group", href: `${waPrefix}/phonebook/groups` },
                { name: "Contact", href: `${waPrefix}/phonebook/contacts` },
              ],
            },
            {
              name: "Fitur Kirim Pesan",
              icon: Send,
              subItems: [
                { name: "Kirim Cepat", href: `${waPrefix}/quick-send` },
                { name: "Media Gallery", href: `${waPrefix}/media` },
                { name: "Broadcast", href: `${waPrefix}/broadcast` },
                { name: "Pesan Terjadwal", href: `${waPrefix}/scheduled` },
                { name: "Antrean Pesan", href: `${waPrefix}/queue` },
                { name: "Auto Reply", href: `${waPrefix}/auto-reply` },
              ],
            },
            {
              name: "Riwayat & Log",
              icon: MessageSquare,
              subItems: [
                { name: "Log Pesan Keluar", href: `${waPrefix}/messages` },
                { name: "Log Aktivitas", href: `${waPrefix}/activity-logs` },
              ],
            },
          ],
        },
        {
          name: "INTEGRASI & DEVELOPER",
          items: [
            { name: "Kredensial API", href: `${waPrefix}/credentials`, icon: KeyRound },
            { name: "Dokumentasi REST API", href: `${waPrefix}/docs`, icon: FileText },
          ],
        },
        {
          name: "BILLING & PENGATURAN",
          items: [
            { name: "Tagihan & Saldo", href: `${waPrefix}/billing`, icon: CreditCard },
            {
              name: "Pengaturan",
              icon: Settings,
              subItems: [
                { name: "Profil & Usaha", href: `${waPrefix}/settings?tab=profile` },
                { name: "Gateway & Anti-Spam", href: `${waPrefix}/settings?tab=antispam` },
                { name: "Pembersihan Riwayat", href: `${waPrefix}/settings?tab=cleanup` },
                { name: "Keamanan Akun", href: `${waPrefix}/settings?tab=security` },
              ],
            },
          ],
        },
        {
          name: "EKOSISTEM & KOMUNITAS",
          items: [
            {
              name: "Ekosistem NODERA",
              icon: Globe,
              subItems: [
                {
                  name: "Cloud Panel",
                  href: "https://panel.dgtlnetsolution.com/login",
                },
                {
                  name: "Payment Gateway",
                  href: "https://gateway.dgtlnetsolution.com/login",
                },
              ],
            },
            {
              name: "Komunitas NODERA",
              href: "#community",
              icon: MessageSquare,
            },
          ],
        },
      ]
    }
    if (isVpn) return defaultVpnGroups

    // Dynamic Admin navigation groups based on active_addons
    const groups: NavGroupConfig[] = defaultAdminGroups.map((g) => ({
      ...g,
      items: g.items.map((item) => ({
        ...item,
        subItems: item.subItems ? [...item.subItems] : undefined,
      })),
    }))
    const menuSection = groups.find((g) => g.name === "MENU")
    if (menuSection) {
      const hasGenieacs = isAddonActive("genieacs")
      const hasIsolir = isAddonActive("isolir")
      const hasMikhmon = isAddonActive("mikhmon")
      const hasRadius = isAddonActive("radius")

      // 1. Jaringan MikroTik
      const netItem = menuSection.items.find((i) => i.name === "Jaringan MikroTik")
      if (netItem && netItem.subItems) {
        netItem.subItems = netItem.subItems.filter(
          (s) =>
            !s.name.includes("MIKHMON") &&
            !s.name.includes("RADIUS") &&
            !s.name.includes("Paket Isolir")
        )
        if (hasRadius) {
          netItem.subItems.splice(1, 0, { name: "RADIUS Server & CoA", href: "/admin/radius" })
        }
        if (hasIsolir) {
          netItem.subItems.splice(2, 0, { name: "Paket Isolir & Webproxy", href: "/admin/addons" })
        }
      }

      // Layanan Mikhmon Dropdown
      const mikhmonItem = menuSection.items.find((i) => i.name === "Layanan Mikhmon")
      if (mikhmonItem) {
        mikhmonItem.subItems = [
          { name: "Mikhmon Online (Cloud)", href: hasMikhmon ? "/admin/mikhmon" : "#addon-mikhmon" },
          { name: "Mikhmon Offline (Lisensi)", href: "/admin/desktop-licenses" },
        ]
      }

      // 2. Fiber Optik & OLT (Standard Built-in Features - Always Visible)
      const oltItem = menuSection.items.find((i) => i.name === "Fiber Optik & OLT")
      if (oltItem && oltItem.subItems) {
        oltItem.subItems = [
          { name: "OLT & ONU GPON/EPON", href: "/admin/olt" },
          { name: "GenieACS TR-069 (ONT)", href: "/admin/ont-devices" },
          { name: "Peta Jaringan GIS & ODP", href: "/admin/map" },
          { name: "VPN Remote MikroTik", href: "/admin/vpn" },
        ]
      }

      // 3. Inactive dropdown inside Add-ons & Ekosistem (Only 4 Official Add-ons)
      const addonItem = menuSection.items.find((i) => i.name === "Add-ons & Ekosistem")
      if (addonItem) {
        const inactiveList = []
        if (!hasGenieacs) inactiveList.push({ name: "GenieACS TR-069 Cloud Engine", href: "#addon-genieacs" })
        if (!hasIsolir) inactiveList.push({ name: "Paket Isolir & Webproxy", href: "#addon-isolir" })
        if (!hasMikhmon) inactiveList.push({ name: "Server MIKHMON Online", href: "#addon-mikhmon" })
        if (!hasRadius) inactiveList.push({ name: "RADIUS Server & CoA", href: "#addon-radius" })

        if (inactiveList.length > 0) {
          addonItem.subItems = inactiveList
        } else {
          addonItem.subItems = [{ name: "Semua Add-on Aktif ✓", href: "/admin/addons" }]
        }
      }
    }

    return groups
  }, [customItems, isSuperadmin, isVpn, isPortal, isTeknisi, isKolektor, isNoderaPay, isWaGateway, npPrefix, waPrefix, active_addons])

  // Precision active route matcher
  const isSubActive = (href: string) => {
    if (!href) return false
    const currentUrl = url
    const [currentPathRaw, currentQuery] = currentUrl.split("?")
    const [targetPathRaw, targetQuery] = href.split("?")
    const currentPath = currentPathRaw.replace(/\/+$/, "")
    const targetPath = targetPathRaw.replace(/\/+$/, "")

    if (currentPath !== targetPath) {
      return false
    }

    // Both on the same path: compare query params precision
    const currentParams = new URLSearchParams(currentQuery || "")
    const targetParams = new URLSearchParams(targetQuery || "")

    const currentTab = currentParams.get("tab")
    const targetTab = targetParams.get("tab")

    if (targetTab || currentTab) {
      if (!targetTab) {
        return !currentTab || currentTab === "profile" || currentTab === "overview" || currentTab === "summary"
      }
      return targetTab === currentTab
    }

    if (targetQuery || currentQuery) {
      return (currentQuery || "") === (targetQuery || "")
    }

    return true
  }

  // Auto detect active submenu group name on render
  const detectedActiveSubmenu = useMemo(() => {
    for (const grp of groups) {
      for (const item of grp.items) {
        if (item.subItems?.some((sub) => isSubActive(sub.href))) {
          return item.name
        }
      }
    }
    return null
  }, [url, groups])

  const [openSubmenu, setOpenSubmenu] = useState<string | null>(detectedActiveSubmenu)
  const navContainerRef = useRef<HTMLDivElement>(null)

  // Keep submenu synced with active route
  useEffect(() => {
    if (detectedActiveSubmenu) {
      setOpenSubmenu(detectedActiveSubmenu)
    }
  }, [detectedActiveSubmenu])

  // Restore & persist sidebar scroll position across navigation
  useEffect(() => {
    const el = navContainerRef.current
    if (!el) return

    const restorePos = () => {
      const savedPos = sessionStorage.getItem("nodera_sidebar_scroll_pos")
      if (savedPos !== null && Number(savedPos) > 0) {
        el.scrollTop = Number(savedPos)
      } else {
        const activeItem = el.querySelector("[data-sidebar-active='true']")
        if (activeItem) {
          activeItem.scrollIntoView({ block: "nearest", behavior: "auto" })
        }
      }
    }

    restorePos()
    const t1 = setTimeout(restorePos, 50)
    const t2 = setTimeout(restorePos, 150)

    const onScroll = () => {
      sessionStorage.setItem("nodera_sidebar_scroll_pos", String(el.scrollTop))
    }

    el.addEventListener("scroll", onScroll, { passive: true })
    return () => {
      clearTimeout(t1)
      clearTimeout(t2)
      el.removeEventListener("scroll", onScroll)
    }
  }, [url, openSubmenu])

  const isFullOpen = isExpanded || isHovered || isMobileOpen

  const toggleSubmenu = (itemName: string) => {
    setOpenSubmenu((prev) => (prev === itemName ? null : itemName))
  }

  const renderNavItem = (item: NavItemConfig) => {
    const Icon = item.icon
    const hasSub = Boolean(item.subItems && item.subItems.length > 0)
    const isSubOpen = openSubmenu === item.name
    const isParentSelected = isParentActive(item)

    if (hasSub) {
      return (
        <li key={item.name} className="relative">
          <button
            type="button"
            title={!isFullOpen ? item.name : undefined}
            onClick={() => {
              if (!isFullOpen) {
                toggleSidebar()
                setOpenSubmenu(item.name)
              } else {
                toggleSubmenu(item.name)
              }
            }}
            className={cn(
              "group flex w-full items-center rounded-xl py-2.5 text-sm transition-all duration-200 ease-out cursor-pointer select-none",
              isFullOpen ? "gap-3 px-3 text-left" : "justify-center px-0 text-center",
              isParentSelected
                ? "bg-white/15 text-white font-semibold shadow-xs"
                : "text-white/85 hover:bg-white/15 hover:text-white font-medium hover:shadow-xs"
            )}
          >
            <Icon
              className={cn(
                "h-5 w-5 shrink-0 transition-all duration-200 ease-out group-hover:scale-110",
                isParentSelected
                  ? "text-white"
                  : "text-white/80 group-hover:text-white"
              )}
            />
            {isFullOpen && (
              <span
                className={cn(
                  "flex-1 text-left truncate transition-all duration-200 ease-out origin-left group-hover:scale-[1.03] group-hover:translate-x-0.5 group-hover:font-semibold",
                  isParentSelected
                    ? "text-white font-semibold"
                    : "group-hover:text-white"
                )}
              >
                {item.name}
              </span>
            )}
            {isFullOpen && item.badge && (
              <span
                className={cn(
                  "rounded-md px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wider shadow-xs transition-transform duration-200 group-hover:scale-110",
                  isParentSelected ? "bg-white/20 text-white" : "bg-emerald-500 text-white"
                )}
              >
                {item.badge}
              </span>
            )}
            {isFullOpen && (
              <ChevronDown
                className={cn(
                  "h-3.5 w-3.5 stroke-[2] transition-all duration-200 shrink-0 group-hover:scale-110",
                  isParentSelected
                    ? "text-white"
                    : "text-white/70 group-hover:text-white",
                  isSubOpen && "rotate-180"
                )}
              />
            )}
          </button>

          {/* Smooth Collapsible CSS Grid Height Accordion with Branch Line */}
          {isFullOpen && (
            <div
              className={cn(
                "grid transition-all duration-200 ease-in-out",
                isSubOpen ? "grid-rows-[1fr] opacity-100 mt-1" : "grid-rows-[0fr] opacity-0"
              )}
            >
              <div className="overflow-hidden">
                <ul className="relative ml-4 pl-3.5 my-1 space-y-1 border-l-2 border-white/25">
                  {item.subItems?.map((sub) => {
                    const subActive = isSubActive(sub.href)
                    const isExternal = sub.href.startsWith("http")
                    const isAddonTrigger = sub.href && sub.href.startsWith("#addon-")

                    if (isAddonTrigger) {
                      return (
                        <li key={sub.name}>
                          <button
                            type="button"
                            onClick={() => handleAddonClick(sub.href)}
                            className="group flex w-full items-center justify-between rounded-lg px-2.5 py-2 text-xs sm:text-sm font-medium text-white/80 hover:text-white hover:bg-white/15 transition-all duration-200 ease-out cursor-pointer text-left"
                          >
                            <span className="truncate transition-all duration-200 ease-out origin-left group-hover:scale-105 group-hover:translate-x-1 group-hover:text-white group-hover:font-semibold">
                              {sub.name}
                            </span>
                          </button>
                        </li>
                      )
                    }

                    if (isExternal) {
                      return (
                        <li key={sub.name}>
                          <button
                            type="button"
                            onClick={() => handleEcosystemClick(sub.name, sub.href)}
                            className="group flex w-full items-center justify-between rounded-lg px-2.5 py-2 text-xs sm:text-sm font-medium text-white/80 hover:text-white hover:bg-white/15 transition-all duration-200 ease-out cursor-pointer text-left"
                          >
                            <span className="truncate transition-all duration-200 ease-out origin-left group-hover:scale-105 group-hover:translate-x-1 group-hover:text-white group-hover:font-semibold">
                              {sub.name}
                            </span>
                            <ExternalLink className="h-3.5 w-3.5 text-white/60 opacity-60 group-hover:opacity-100 group-hover:scale-110 transition-all duration-200" />
                          </button>
                        </li>
                      )
                    }

                    return (
                      <li key={sub.name}>
                        <Link
                          href={sub.href}
                          preserveScroll={true}
                          data-sidebar-active={subActive ? "true" : undefined}
                          onClick={() => {
                            if (navContainerRef.current) {
                              sessionStorage.setItem("nodera_sidebar_scroll_pos", String(navContainerRef.current.scrollTop))
                            }
                          }}
                          className={cn(
                            "group flex items-center justify-between rounded-lg px-2.5 py-2 text-xs sm:text-sm transition-all duration-200 ease-out cursor-pointer",
                            subActive
                              ? "bg-white text-[#465FFF] dark:text-[#0073C6] font-bold translate-x-0.5 shadow-xs"
                              : "text-white/80 hover:text-white hover:bg-white/15 font-medium"
                          )}
                        >
                          <span
                            className={cn(
                              "truncate transition-all duration-200 ease-out origin-left group-hover:scale-105 group-hover:translate-x-1 group-hover:font-semibold",
                              subActive
                                ? "text-[#465FFF] dark:text-[#0073C6]"
                                : "group-hover:text-white"
                            )}
                          >
                            {sub.name}
                          </span>
                          {sub.badge && (
                            <span
                              className={cn(
                                "rounded-md px-1.5 py-0.2 text-[9px] font-bold uppercase tracking-wider shadow-xs transition-transform duration-200 group-hover:scale-110",
                                subActive ? "bg-[#465FFF] dark:bg-[#0073C6] text-white" : "bg-emerald-500 text-white"
                              )}
                            >
                              {sub.badge}
                            </span>
                          )}
                        </Link>
                      </li>
                    )
                  })}
                </ul>
              </div>
            </div>
          )}
        </li>
      )
    }

    if (item.href === "#community" || item.name.toLowerCase().includes("komunitas")) {
      return (
        <li key={item.name}>
          <button
            type="button"
            title={!isFullOpen ? item.name : undefined}
            onClick={() => setShowCommunityModal(true)}
            className={cn(
              "group flex w-full items-center rounded-xl py-2.5 text-sm transition-all duration-200 ease-out cursor-pointer text-left select-none",
              isFullOpen ? "gap-3 px-3" : "justify-center px-0 text-center",
              "text-white/85 hover:bg-white/15 hover:text-white font-medium hover:shadow-xs"
            )}
          >
            <Icon
              className="h-5 w-5 shrink-0 text-white/80 group-hover:text-white transition-all duration-200 ease-out group-hover:scale-110"
            />
            {isFullOpen && (
              <span className="flex-1 truncate transition-all duration-200 ease-out origin-left group-hover:scale-[1.03] group-hover:translate-x-0.5 group-hover:font-semibold">
                {item.name}
              </span>
            )}
            {isFullOpen && (
              <span className="rounded-md px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wider bg-emerald-500 text-white shadow-xs transition-transform duration-200 group-hover:scale-110">
                {item.badge || "JOIN"}
              </span>
            )}
          </button>
        </li>
      )
    }

    return (
      <li key={item.name}>
        <Link
          href={item.href || "#"}
          preserveScroll={true}
          data-sidebar-active={isParentSelected ? "true" : undefined}
          onClick={() => {
            if (navContainerRef.current) {
              sessionStorage.setItem("nodera_sidebar_scroll_pos", String(navContainerRef.current.scrollTop))
            }
          }}
          title={!isFullOpen ? item.name : undefined}
          className={cn(
            "group flex items-center rounded-xl py-2.5 text-sm transition-all duration-200 ease-out cursor-pointer",
            isFullOpen ? "gap-3 px-3 text-left" : "justify-center px-0 text-center",
            isParentSelected
              ? "bg-white text-[#465FFF] dark:text-[#0073C6] font-bold shadow-md shadow-black/10"
              : "text-white/85 hover:bg-white/15 hover:text-white font-medium hover:shadow-xs"
          )}
        >
          <Icon
            className={cn(
              "h-5 w-5 shrink-0 transition-all duration-200 ease-out group-hover:scale-110",
              isParentSelected
                ? "text-[#465FFF] dark:text-[#0073C6]"
                : "text-white/80 group-hover:text-white"
            )}
          />
          {isFullOpen && (
            <span
              className={cn(
                "flex-1 truncate transition-all duration-200 ease-out origin-left group-hover:scale-[1.03] group-hover:translate-x-0.5 group-hover:font-semibold",
                isParentSelected
                  ? "text-[#465FFF] dark:text-[#0073C6]"
                  : "group-hover:text-white"
              )}
            >
              {item.name}
            </span>
          )}
          {isFullOpen && item.badge && (
            <span
              className={cn(
                "rounded-md px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wider shadow-xs transition-transform duration-200 group-hover:scale-110",
                isParentSelected ? "bg-[#465FFF] dark:bg-[#0073C6] text-white" : "bg-emerald-500 text-white"
              )}
            >
              {item.badge}
            </span>
          )}
        </Link>
      </li>
    )
  }

  const merchant = (props as any)?.merchant || (props as any)?.wa_merchant
  const p = props as any
  const userSaldo = Number(
    p?.user?.saldo ??
    p?.user?.total_saldo ??
    p?.stats?.saldo ??
    p?.auth?.user?.saldo ??
    p?.auth?.user?.total_saldo ??
    0
  )

  return (
    <aside
      className={cn(
        "fixed top-0 left-0 z-50 flex flex-col h-[100dvh] max-h-[100dvh] border-r border-white/10 bg-[#465FFF] dark:bg-[#0073C6] text-white transition-all duration-300 ease-in-out shadow-2xl",
        isMobileOpen ? "translate-x-0 w-[290px] max-w-[85vw] shadow-2xl" : "-translate-x-full xl:translate-x-0",
        isFullOpen ? "xl:w-[290px]" : "xl:w-[90px]"
      )}
      onMouseEnter={() => {
        if (!isExpanded) {
          setIsHovered(true)
        }
      }}
      onMouseLeave={() => {
        setIsHovered(false)
      }}
    >
      {/* 1:1 TailAdmin Header Brand Logo & Sidebar Toggle Controls */}
      <div
        className={cn(
          "flex h-16 items-center border-b border-white/10 shrink-0 transition-all duration-200",
          isFullOpen ? "justify-between px-5" : "justify-center px-0"
        )}
      >
        <Link
          href={brand?.href || (isVpn ? "/dashboard" : isWaGateway ? "/dashboard" : "/admin")}
          className={cn(
            "flex items-center transition-all",
            isFullOpen ? "gap-3 truncate" : "justify-center mx-auto"
          )}
        >
          <img
            src={brand?.logo || "/images/logo-white.png?v=36"}
            alt={brand?.name || "NODERA"}
            className="h-8 w-8 object-contain shrink-0"
            onError={(e) => {
              ;(e.target as HTMLImageElement).src = "/images/logo-white.png?v=36"
            }}
          />
          {isFullOpen && (
            <div className="flex flex-col truncate text-left">
              <span className="font-bold text-sm tracking-tight text-white uppercase leading-none truncate">
                {brand?.name || "Nodera System"}
              </span>
              <span className="text-[10px] font-bold tracking-wider text-white/75 uppercase mt-1">
                {brand?.sub || (isVpn ? "High-Performance Network" : isNoderaPay ? "PAYMENT GATEWAY" : isWaGateway ? "WA GATEWAY" : "ISP BILLING")}
              </span>
            </div>
          )}
        </Link>

        {/* Sidebar Controls */}
        <div className={cn("flex items-center", !isFullOpen && "hidden")}>
          {/* Mobile Close Button (X) */}
          <button
            type="button"
            onClick={() => setIsMobileOpen(false)}
            aria-label="Tutup Sidebar"
            className="xl:hidden flex h-8 w-8 items-center justify-center rounded-lg text-white/80 hover:bg-white/15 hover:text-white cursor-pointer"
          >
            <X className="h-5 w-5" />
          </button>

          {/* Desktop Collapse Toggle Button */}
          <button
            type="button"
            onClick={toggleSidebar}
            aria-label="Toggle Collapse Sidebar"
            className="hidden xl:flex h-8 w-8 items-center justify-center rounded-lg text-white/80 hover:bg-white/15 hover:text-white transition cursor-pointer"
            title={isExpanded ? "Kecilkan Sidebar" : "Perbesar Sidebar"}
          >
            <ChevronLeft className={cn("h-4 w-4 transition-transform duration-200", !isExpanded && "rotate-180")} />
          </button>
        </div>
      </div>

      {/* 1:1 TailAdmin Sidebar Navigation Menu Groups */}
      <div ref={navContainerRef} className="flex-1 min-h-0 overflow-y-auto px-3.5 py-4 pb-6 custom-scrollbar space-y-5">
        {groups.map((group) => (
          <div key={group.name} className="space-y-1">
            <h3
              className={cn(
                "mb-1.5 px-3 text-[10px] font-bold tracking-wider text-white/70 uppercase",
                !isFullOpen && "xl:hidden"
              )}
            >
              {group.name}
            </h3>
            <ul className="space-y-0.5">{group.items.map(renderNavItem)}</ul>
          </div>
        ))}
      </div>

      {/* Footer / Saldo Card */}
      {isFullOpen ? (
        <div className="p-3.5 border-t border-white/10 shrink-0">
          {footer ? (
            footer
          ) : isVpn ? (
            <div className="rounded-2xl bg-white/15 backdrop-blur-md border border-white/20 p-3.5 space-y-2.5 text-white shadow-lg">
              <div className="flex items-center justify-between">
                <span className="text-[10px] font-bold text-white/80 uppercase tracking-wider">
                  Master Wallet
                </span>
                <span className="rounded px-1.5 py-0.5 text-[9px] font-bold bg-emerald-500 text-white">
                  ONLINE
                </span>
              </div>
              <div className="text-lg font-black tracking-tight text-white tabular-nums font-mono">
                Rp {userSaldo.toLocaleString("id-ID")}
              </div>
              <Link
                href="/topup"
                className="flex w-full items-center justify-center gap-1.5 rounded-xl bg-white text-[#465FFF] dark:text-[#0073C6] hover:bg-white/95 active:bg-white/90 py-2 px-3 text-xs font-bold shadow-md transition cursor-pointer active:scale-98"
              >
                <span>+ Isi Saldo</span>
              </Link>
            </div>
          ) : isNoderaPay ? (
            <div className="rounded-2xl bg-white/15 backdrop-blur-md border border-white/20 p-3.5 space-y-2.5 text-white shadow-lg">
              <div className="flex items-center justify-between">
                <span className="text-[10px] font-bold text-white/80 uppercase tracking-wider">
                  Saldo Tersedia
                </span>
                <span className="rounded px-1.5 py-0.5 text-[9px] font-bold bg-emerald-500 text-white">
                  AKTIF
                </span>
              </div>
              <div className="text-lg font-black tracking-tight text-white tabular-nums font-mono">
                Rp {Number((merchant as any)?.balance ?? (merchant as any)?.saldo ?? userSaldo ?? 0).toLocaleString("id-ID")}
              </div>
              <Link
                href={`${npPrefix}/withdrawals`}
                className="flex w-full items-center justify-center gap-1.5 rounded-xl bg-white text-[#465FFF] dark:text-[#0073C6] hover:bg-white/95 active:bg-white/90 py-2 px-3 text-xs font-bold shadow-md transition cursor-pointer active:scale-98"
              >
                <span>Tarik Dana</span>
              </Link>
            </div>
          ) : isWaGateway && merchant ? (
            <div className="rounded-2xl bg-white/15 backdrop-blur-md border border-white/20 p-3.5 space-y-2 text-white shadow-lg">
              <div className="flex items-center justify-between">
                <span className="text-[10px] font-bold text-white/80 uppercase tracking-wider">
                  Saldo Akun
                </span>
                <span
                  className={cn(
                    "rounded px-1.5 py-0.5 text-[9px] font-bold uppercase shadow-2xs",
                    merchant.credit_balance > 0
                      ? "bg-emerald-500 text-white"
                      : "bg-white/20 text-white"
                  )}
                >
                  {merchant.credit_balance > 0 ? "AKTIF" : "FREE TIER"}
                </span>
              </div>
              <div className="text-base font-black tracking-tight text-white tabular-nums font-mono">
                Rp {Number(merchant.credit_balance || 0).toLocaleString("id-ID")}
              </div>
              <Link
                href={url.startsWith("/wagateway") ? "/wagateway/billing" : "/billing"}
                className="flex w-full items-center justify-center gap-1.5 rounded-xl bg-white text-[#465FFF] dark:text-[#0073C6] hover:bg-white/95 active:bg-white/90 py-2 px-3 text-xs font-bold shadow-md transition cursor-pointer active:scale-98"
              >
                <span>+ Topup Saldo</span>
              </Link>
            </div>
          ) : isPortal ? (
            <div className="rounded-xl bg-white/10 backdrop-blur-md p-2.5 text-left border border-white/15 shadow-xs space-y-1 text-white">
              <div className="flex items-center justify-between">
                <span className="text-[10px] font-bold uppercase tracking-wider text-white/85">
                  Layanan Pelanggan
                </span>
                <span className="inline-flex items-center px-1.5 py-0.5 rounded text-[9px] font-bold bg-emerald-500 text-white">
                  ONLINE
                </span>
              </div>
              <p className="text-xs font-bold text-white truncate">
                {brand?.name || "Portal Mandiri"}
              </p>
              <p className="text-[10px] text-white/70 font-medium truncate">
                Akses Mandiri Pelanggan
              </p>
            </div>
          ) : isTeknisi ? (
            <div className="rounded-xl bg-white/10 backdrop-blur-md p-2.5 text-left border border-white/15 shadow-xs space-y-1 text-white">
              <div className="flex items-center justify-between">
                <span className="text-[10px] font-bold uppercase tracking-wider text-white/85">
                  Portal Teknisi
                </span>
                <span className="inline-flex items-center px-1.5 py-0.5 rounded text-[9px] font-bold bg-emerald-500 text-white">
                  ON-DUTY
                </span>
              </div>
              <p className="text-xs font-bold text-white truncate">
                {brand?.name || "Operasional Lapangan"}
              </p>
            </div>
          ) : isKolektor ? (
            <div className="rounded-xl bg-white/10 backdrop-blur-md p-2.5 text-left border border-white/15 shadow-xs space-y-1 text-white">
              <div className="flex items-center justify-between">
                <span className="text-[10px] font-bold uppercase tracking-wider text-white/85">
                  Portal Kolektor
                </span>
                <span className="inline-flex items-center px-1.5 py-0.5 rounded text-[9px] font-bold bg-emerald-500 text-white">
                  LAPANGAN
                </span>
              </div>
              <p className="text-xs font-bold text-white truncate">
                {brand?.name || "Penagihan"}
              </p>
            </div>
          ) : (
            <div className="rounded-xl bg-white/15 backdrop-blur-md p-2.5 text-left border border-white/20 shadow-xs space-y-1 text-white">
              <div className="flex items-center justify-between">
                <span className="text-[10px] font-bold uppercase tracking-wider text-white/85">
                  Pendapatan Bulan Aktif
                </span>
                <span className="inline-flex items-center px-1.5 py-0.5 rounded text-[9px] font-bold bg-emerald-500 text-white">
                  BULAN INI
                </span>
              </div>
              <div className="text-base font-black tracking-tight text-white font-mono tabular-nums">
                Rp {monthlyRevenue.toLocaleString("id-ID")}
              </div>
              <p className="text-[10px] text-white/80 font-medium truncate">
                {brand?.name || "Nodera System"}
              </p>
            </div>
          )}
        </div>
      ) : (
        <div className="p-2 border-t border-white/10 flex justify-center shrink-0">
          {!isPortal && !isTeknisi && !isKolektor && (
            <Link
              href={isVpn ? "/topup" : isNoderaPay ? `${npPrefix}/withdrawals` : url.startsWith("/wagateway") ? "/wagateway/billing" : "/billing"}
              title={`Saldo: Rp ${(isVpn ? userSaldo : isNoderaPay ? Number((merchant as any)?.balance ?? (merchant as any)?.saldo ?? userSaldo ?? 0) : Number(merchant?.credit_balance || 0)).toLocaleString("id-ID")}`}
              className="flex h-10 w-10 items-center justify-center rounded-xl bg-white/15 hover:bg-white/25 text-white transition cursor-pointer"
            >
              <CreditCard className="h-5 w-5" />
            </Link>
          )}
        </div>
      )}

      {/* Add-on Pop-up Modal (Rendered to body via createPortal for true full-screen popup) */}
      {addonModalInfo &&
        addonModalInfo.isOpen &&
        typeof document !== "undefined" &&
        createPortal(
          <div className="fixed inset-0 z-[99999] flex items-center justify-center bg-black/60 p-4 backdrop-blur-xs animate-in fade-in duration-200">
            <div className="w-full max-w-md rounded-2xl border border-gray-200 bg-white dark:border-[#212B3B] dark:bg-[#121720] p-5 sm:p-6 shadow-2xl text-gray-900 dark:text-white space-y-4 animate-in zoom-in-95 duration-200 max-h-[92vh] overflow-y-auto custom-scrollbar">
              <div className="flex items-center justify-between border-b border-gray-100 dark:border-[#212B3B] pb-3">
                <div className="flex items-center gap-2.5">
                  <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-blue-50 text-blue-600 dark:bg-blue-600/15 dark:text-blue-400 border border-blue-200 dark:border-blue-500/20 shadow-xs">
                    <addonModalInfo.icon className="h-5 w-5" />
                  </div>
                  <div>
                    <h3 className="font-bold text-sm sm:text-base text-gray-900 dark:text-white">
                      {addonModalInfo.title}
                    </h3>
                    <div className="flex items-center gap-1.5 mt-0.5">
                      <span className="inline-flex items-center px-1.5 py-0.5 rounded text-[9px] font-bold uppercase tracking-wider bg-blue-600 text-white shadow-xs">
                        {addonModalInfo.badge}
                      </span>
                      <span className="text-[10px] font-semibold text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-950/30 px-1.5 py-0.5 rounded">
                        {addonModalInfo.price}
                      </span>
                    </div>
                  </div>
                </div>
                <button
                  type="button"
                  onClick={() => setAddonModalInfo(null)}
                  className="rounded-lg p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-[#161D2A] dark:hover:text-white transition cursor-pointer"
                >
                  <X className="h-5 w-5" />
                </button>
              </div>

              {/* Saldo Cloud Panel Information Card */}
              <div className="rounded-xl border border-gray-200 bg-gray-50/90 dark:border-[#212B3B] dark:bg-[#10141A] p-3.5 flex items-center justify-between shadow-2xs">
                <div className="flex items-center gap-2.5">
                  <div className="flex h-9 w-9 items-center justify-center rounded-lg bg-blue-50 text-blue-600 dark:bg-blue-600/15 dark:text-blue-400 border border-blue-200 dark:border-blue-500/20">
                    <Wallet className="h-4 w-4" />
                  </div>
                  <div>
                    <span className="text-[10px] font-bold text-gray-500 dark:text-slate-400 uppercase tracking-wider block">
                      Saldo Cloud Panel Anda:
                    </span>
                    <span className="text-sm font-extrabold text-gray-900 dark:text-white">
                      {new Intl.NumberFormat("id-ID", {
                        style: "currency",
                        currency: "IDR",
                        minimumFractionDigits: 0,
                      }).format(cloudPanelBalance)}
                    </span>
                  </div>
                </div>
                {cloudPanelBalance >= addonModalInfo.rawPrice || addonModalInfo.rawPrice === 0 ? (
                  <span className="inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-[10px] font-bold text-emerald-700 bg-emerald-100 dark:bg-emerald-950/50 dark:text-emerald-300">
                    <ShieldCheck className="h-3 w-3" />
                    <span>Saldo Cukup</span>
                  </span>
                ) : (
                  <div className="flex flex-col items-end gap-1">
                    <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-bold text-rose-700 bg-rose-100 dark:bg-rose-950/50 dark:text-rose-300">
                      Saldo Kurang
                    </span>
                    <a
                      href="/admin/topup"
                      className="text-[10px] font-bold text-blue-600 dark:text-blue-400 hover:underline flex items-center gap-0.5"
                    >
                      <span>Top Up</span>
                      <ExternalLink className="h-2.5 w-2.5" />
                    </a>
                  </div>
                )}
              </div>

              <p className="text-xs text-gray-600 dark:text-slate-300 leading-relaxed">
                {addonModalInfo.description}
              </p>

              <div className="rounded-xl border border-gray-200 bg-gray-50/80 dark:border-[#212B3B] dark:bg-[#10141A] p-3.5 space-y-2">
                <div className="flex items-center justify-between">
                  <span className="text-[11px] font-bold text-gray-500 dark:text-slate-400 uppercase tracking-wider block">
                    Fitur &amp; Keunggulan Modul:
                  </span>
                  <span className="text-[10px] font-medium text-gray-400 dark:text-slate-500">
                    {addonModalInfo.billingCycle}
                  </span>
                </div>
                <ul className="space-y-1.5 text-xs text-gray-700 dark:text-slate-300 font-medium">
                  {addonModalInfo.features.map((feat, idx) => (
                    <li key={idx} className="flex items-start gap-2">
                      <span className="h-1.5 w-1.5 rounded-full bg-blue-600 mt-1.5 shrink-0" />
                      <span>{feat}</span>
                    </li>
                  ))}
                </ul>
              </div>

              {/* Payment Terms & Agreement */}
              {addonModalInfo.isMonthly ? (
                <div
                  onClick={() => setAgreedMonthly(!agreedMonthly)}
                  className="flex items-start gap-3 p-3.5 rounded-xl border border-blue-200 bg-blue-50/60 dark:border-blue-900/40 dark:bg-blue-950/20 cursor-pointer select-none transition hover:bg-blue-50 dark:hover:bg-blue-950/30"
                >
                  <div className="relative flex items-center justify-center mt-0.5 shrink-0">
                    <input
                      type="checkbox"
                      checked={agreedMonthly}
                      onChange={(e) => setAgreedMonthly(e.target.checked)}
                      className="sr-only"
                    />
                    <div
                      className={cn(
                        "flex h-5 w-5 items-center justify-center rounded-md border transition-all duration-150",
                        agreedMonthly
                          ? "border-blue-600 bg-blue-600 text-white shadow-xs"
                          : "border-gray-300 bg-white dark:border-gray-600 dark:bg-[#151D2A]"
                      )}
                    >
                      {agreedMonthly && (
                        <Check className="h-3.5 w-3.5 stroke-[3] text-white" />
                      )}
                    </div>
                  </div>
                  <div className="text-xs text-gray-700 dark:text-slate-300 leading-relaxed">
                    <span className="font-bold text-gray-900 dark:text-white block mb-0.5">
                      Persetujuan Pemotongan Saldo Bulanan / PAYG
                    </span>
                    Saya menyetujui aktivasi Add-on ini dan pemotongan saldo Cloud Panel secara otomatis sesuai skema tarif yang berlaku.
                  </div>
                </div>
              ) : (
                <div className="flex items-center gap-2 p-3 rounded-xl border border-emerald-200 bg-emerald-50/50 dark:border-emerald-900/40 dark:bg-emerald-950/20 text-xs text-emerald-800 dark:text-emerald-300">
                  <ShieldCheck className="h-4 w-4 shrink-0 text-emerald-600 dark:text-emerald-400" />
                  <span>
                    Add-on ini adalah <strong>Sekali Bayar (Lifetime)</strong>. Pembayaran sebesar {addonModalInfo.price} akan dipotong langsung 1x dari Saldo Cloud Panel Anda.
                  </span>
                </div>
              )}

              {/* Action Error Callout */}
              {actionError && (
                <div className="p-2.5 rounded-xl border border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-900/40 dark:bg-rose-950/30 dark:text-rose-300 text-xs font-medium">
                  {actionError}
                </div>
              )}

              <div className="flex items-center gap-2.5 pt-2">
                <button
                  type="button"
                  onClick={() => setAddonModalInfo(null)}
                  className="flex-1 h-10 rounded-xl border border-gray-200 bg-gray-100 hover:bg-gray-200 dark:border-[#212B3B] dark:bg-[#161D2A] dark:hover:bg-[#1C2536] text-xs font-bold text-gray-700 dark:text-slate-300 transition cursor-pointer"
                >
                  {addonModalInfo.isActive ? "Tutup" : "Batal"}
                </button>
                {addonModalInfo.isActive ? (
                  <Link
                    href={addonModalInfo.routeUrl}
                    preserveScroll={true}
                    onClick={() => setAddonModalInfo(null)}
                    className="flex-1 inline-flex items-center justify-center gap-1.5 h-10 rounded-xl bg-blue-600 hover:bg-blue-700 text-xs font-bold text-white shadow-sm transition active:scale-98 cursor-pointer"
                  >
                    <span>Buka Modul</span>
                    <ExternalLink className="h-3.5 w-3.5" />
                  </Link>
                ) : (
                  <button
                    type="button"
                    disabled={
                      isPurchasing ||
                      (addonModalInfo.isMonthly && !agreedMonthly) ||
                      (addonModalInfo.rawPrice > 0 && cloudPanelBalance < addonModalInfo.rawPrice)
                    }
                    onClick={() =>
                      handleActivateAddon(addonModalInfo.slug, addonModalInfo.isMonthly)
                    }
                    className={cn(
                      "flex-1 inline-flex items-center justify-center gap-1.5 h-10 rounded-xl text-xs font-bold text-white shadow-sm transition active:scale-98 cursor-pointer",
                      isPurchasing ||
                        (addonModalInfo.isMonthly && !agreedMonthly) ||
                        (addonModalInfo.rawPrice > 0 &&
                          cloudPanelBalance < addonModalInfo.rawPrice)
                        ? "bg-gray-400 dark:bg-gray-700 opacity-60 cursor-not-allowed"
                        : "bg-blue-600 hover:bg-blue-700"
                    )}
                  >
                    {isPurchasing ? (
                      <span>Memproses Aktivasi...</span>
                    ) : (
                      <>
                        <span>
                          {addonModalInfo.isMonthly
                            ? "Aktifkan Add-on"
                            : `Aktifkan (${addonModalInfo.price})`}
                        </span>
                        <ExternalLink className="h-3.5 w-3.5" />
                      </>
                    )}
                  </button>
                )}
              </div>
            </div>
          </div>,
          document.body
        )}

      {/* Ecosystem Service Info Modal (Rendered to body via createPortal for true full-screen popup) */}
      {ecosystemInfo &&
        ecosystemInfo.isOpen &&
        typeof document !== "undefined" &&
        createPortal(
          <div className="fixed inset-0 z-[99999] flex items-center justify-center bg-black/60 p-4 backdrop-blur-xs animate-in fade-in duration-200">
            <div className="w-full max-w-md rounded-2xl border border-gray-200 bg-white dark:border-[#212B3B] dark:bg-[#121720] p-5 sm:p-6 shadow-2xl text-gray-900 dark:text-white space-y-4 animate-in zoom-in-95 duration-200">
              <div className="flex items-center justify-between border-b border-gray-100 dark:border-[#212B3B] pb-3">
                <div className="flex items-center gap-2.5">
                  <div className="flex h-9 w-9 items-center justify-center rounded-xl bg-blue-50 text-blue-600 dark:bg-blue-600/15 dark:text-blue-400 border border-blue-200 dark:border-blue-500/20 shadow-xs">
                    <Globe className="h-5 w-5" />
                  </div>
                  <div>
                    <h3 className="font-bold text-sm text-gray-900 dark:text-white">
                      {ecosystemInfo.title}
                    </h3>
                    <span className="inline-flex items-center px-1.5 py-0.5 rounded text-[9px] font-bold uppercase tracking-wider bg-blue-600 text-white shadow-xs">
                      {ecosystemInfo.badge}
                    </span>
                  </div>
                </div>
                <button
                  type="button"
                  onClick={() => setEcosystemInfo(null)}
                  className="rounded-lg p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-[#161D2A] dark:hover:text-white transition cursor-pointer"
                >
                  <X className="h-5 w-5" />
                </button>
              </div>

              <p className="text-xs text-gray-600 dark:text-slate-300 leading-relaxed">
                {ecosystemInfo.description}
              </p>

              <div className="rounded-xl border border-gray-200 bg-gray-50/80 dark:border-[#212B3B] dark:bg-[#10141A] p-3.5 space-y-2">
                <span className="text-[11px] font-bold text-gray-500 dark:text-slate-400 uppercase tracking-wider block">
                  Keunggulan &amp; Fitur Utama:
                </span>
                <ul className="space-y-1.5 text-xs text-gray-700 dark:text-slate-300 font-medium">
                  {ecosystemInfo.features.map((feat, idx) => (
                    <li key={idx} className="flex items-start gap-2">
                      <span className="h-1.5 w-1.5 rounded-full bg-blue-600 mt-1.5 shrink-0" />
                      <span>{feat}</span>
                    </li>
                  ))}
                </ul>
              </div>

              <div className="flex items-center gap-2.5 pt-2">
                <button
                  type="button"
                  onClick={() => setEcosystemInfo(null)}
                  className="flex-1 h-10 rounded-xl border border-gray-200 bg-gray-100 hover:bg-gray-200 dark:border-[#212B3B] dark:bg-[#161D2A] dark:hover:bg-[#1C2536] text-xs font-bold text-gray-700 dark:text-slate-300 transition cursor-pointer"
                >
                  Tutup
                </button>
                <a
                  href={ecosystemInfo.subdomainUrl}
                  target="_blank"
                  rel="noopener noreferrer"
                  onClick={() => setEcosystemInfo(null)}
                  className="flex-1 inline-flex items-center justify-center gap-1.5 h-10 rounded-xl bg-blue-600 hover:bg-blue-700 text-xs font-bold text-white shadow-sm transition active:scale-98 cursor-pointer"
                >
                  <span>Buka Layanan</span>
                  <ExternalLink className="h-3.5 w-3.5" />
                </a>
              </div>
            </div>
          </div>,
          document.body
        )}
        {/* Komunitas NODERA Pop-up Modal */}
        {showCommunityModal &&
          createPortal(
            <div className="fixed inset-0 z-[99999] flex items-center justify-center bg-black/60 p-4 backdrop-blur-xs animate-in fade-in duration-200">
              <div className="relative w-full max-w-md rounded-2xl border border-gray-200 bg-white dark:border-[#212B3B] dark:bg-[#121720] p-5 sm:p-6 shadow-2xl text-gray-900 dark:text-white space-y-4 max-h-[92vh] overflow-y-auto custom-scrollbar">
                <div className="flex items-center justify-between pb-3 border-b border-gray-100 dark:border-[#212B3B]">
                  <div className="flex items-center gap-2.5">
                    <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-blue-50 text-blue-600 dark:bg-blue-600/15 dark:text-blue-400 border border-blue-200 dark:border-blue-500/20 shadow-xs">
                      <MessageSquare className="h-5 w-5" />
                    </div>
                    <div>
                      <h3 className="font-bold text-sm sm:text-base text-gray-900 dark:text-white">
                        Komunitas Resmi NODERA
                      </h3>
                      <p className="text-[11px] text-gray-500 dark:text-slate-400">
                        Forum diskusi &amp; sharing sesama praktisi RT/RW Net
                      </p>
                    </div>
                  </div>
                  <button
                    type="button"
                    onClick={() => setShowCommunityModal(false)}
                    className="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-[#161D2A] dark:hover:text-white transition cursor-pointer"
                  >
                    <X className="h-5 w-5" />
                  </button>
                </div>

                <div className="space-y-3">
                  {/* WhatsApp Group Card */}
                  <a
                    href="https://chat.whatsapp.com/KdrgfTVGre54MpCcE7UuI8"
                    target="_blank"
                    rel="noopener noreferrer"
                    className="group flex items-center justify-between p-3.5 sm:p-4 rounded-xl border border-emerald-200 bg-emerald-50/60 dark:border-emerald-500/30 dark:bg-emerald-950/20 hover:border-emerald-500 transition-all cursor-pointer shadow-xs"
                  >
                    <div className="flex items-center gap-3">
                      <div className="flex h-10 w-10 sm:h-11 sm:w-11 shrink-0 items-center justify-center rounded-xl bg-[#25D366] text-white shadow-sm">
                        <MessageSquare className="h-5 w-5 sm:h-6 sm:w-6" />
                      </div>
                      <div>
                        <h4 className="text-xs font-bold text-gray-900 dark:text-white group-hover:text-emerald-600 dark:group-hover:text-emerald-400 transition-colors">
                          Grup WhatsApp Komunitas
                        </h4>
                        <p className="text-[11px] text-gray-500 dark:text-slate-400">
                          Diskusi harian &amp; tanya jawab RT/RW Net
                        </p>
                      </div>
                    </div>
                    <span className="flex items-center gap-1 rounded-lg bg-[#25D366] px-3 py-1.5 text-xs font-bold text-white shadow-xs group-hover:bg-[#20bd5a] transition shrink-0">
                      <span>Gabung</span>
                      <ExternalLink className="h-3.5 w-3.5" />
                    </span>
                  </a>

                  {/* Telegram Group Card */}
                  <a
                    href="https://t.me/dnsolutionchat"
                    target="_blank"
                    rel="noopener noreferrer"
                    className="group flex items-center justify-between p-3.5 sm:p-4 rounded-xl border border-sky-200 bg-sky-50/60 dark:border-sky-500/30 dark:bg-sky-950/20 hover:border-sky-500 transition-all cursor-pointer shadow-xs"
                  >
                    <div className="flex items-center gap-3">
                      <div className="flex h-10 w-10 sm:h-11 sm:w-11 shrink-0 items-center justify-center rounded-xl bg-[#229ED9] text-white shadow-sm">
                        <Send className="h-5 w-5 sm:h-6 sm:w-6" />
                      </div>
                      <div>
                        <h4 className="text-xs font-bold text-gray-900 dark:text-white group-hover:text-sky-600 dark:group-hover:text-sky-400 transition-colors">
                          Grup Telegram Diskusi
                        </h4>
                        <p className="text-[11px] text-gray-500 dark:text-slate-400">
                          Sharing script MikroTik &amp; live update
                        </p>
                      </div>
                    </div>
                    <span className="flex items-center gap-1 rounded-lg bg-[#229ED9] px-3 py-1.5 text-xs font-bold text-white shadow-xs group-hover:bg-[#1e8bc0] transition shrink-0">
                      <span>Gabung</span>
                      <ExternalLink className="h-3.5 w-3.5" />
                    </span>
                  </a>
                </div>

                <div className="pt-2 border-t border-gray-100 dark:border-[#212B3B] flex justify-end">
                  <button
                    type="button"
                    onClick={() => setShowCommunityModal(false)}
                    className="h-9 px-4 rounded-xl border border-gray-200 bg-gray-100 text-xs font-bold text-gray-700 hover:bg-gray-200 dark:border-[#212B3B] dark:bg-[#161D2A] dark:text-slate-300 dark:hover:bg-[#1C2536] transition cursor-pointer"
                  >
                    Tutup
                  </button>
                </div>
              </div>
            </div>,
            document.body
          )}
    </aside>
  )
}

export default AppSidebar