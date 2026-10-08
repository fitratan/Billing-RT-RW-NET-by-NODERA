import {
  RefreshCw,
  Home,
  Users,
  Receipt,
  Package,
  CreditCard,
  Wallet,
  TrendingDown,
  Router,
  Terminal,
  KeyRound,
  SlidersHorizontal,
  Gauge,
  Radio,
  Server,
  Wifi,
  MapPin,
  ShieldCheck,
  MessageSquare,
  History,
  LifeBuoy,
  UserCog,
  Boxes,
  Puzzle,
  Webhook,
  Settings,
  ShoppingBag,
  Globe,
  Send,
  Network,
  Laptop,
  type LucideIcon,
} from "lucide-react"

export interface NavItem {
  href: string
  label: string
  icon: LucideIcon
  active?: string | string[]
  group?: string
}

export const adminSidebarItems: NavItem[] = [
  // Utama
  { href: "/dashboard", label: "Dashboard", icon: Home, active: ["/dashboard", "/admin/dashboard", "/admin"], group: "Utama" },

  // Billing
  { href: "/admin/billing/customers", label: "Pelanggan", icon: Users, active: ["/admin/billing/customers"], group: "Billing" },
  { href: "/admin/billing/invoices", label: "Invoice", icon: Receipt, active: ["/admin/billing/invoices"], group: "Billing" },
  { href: "/admin/billing/packages", label: "Paket Langganan", icon: Package, active: ["/admin/billing/packages"], group: "Billing" },
  { href: "/admin/payments/gateway", label: "Payment Gateway", icon: CreditCard, active: ["/admin/payments/gateway"], group: "Billing" },

  // Toko & E-Commerce
  { href: "/admin/shop/products", label: "Produk Toko", icon: ShoppingBag, active: ["/admin/shop/products"], group: "Toko Online" },
  { href: "/admin/shop/orders", label: "Pesanan Toko", icon: Receipt, active: ["/admin/shop/orders"], group: "Toko Online" },
  { href: "/admin/landing-settings", label: "Landing Page", icon: Globe, active: ["/admin/landing-settings"], group: "Toko Online" },

  // Keuangan
  { href: "/admin/finance", label: "Laporan Keuangan", icon: Wallet, active: ["/admin/finance"], group: "Keuangan" },
  { href: "/admin/expenses", label: "Pengeluaran Kas", icon: TrendingDown, active: ["/admin/expenses", "/admin/finance/expenses"], group: "Keuangan" },

  // Jaringan MikroTik
  { href: "/admin/mikrotik/routers", label: "Router MikroTik", icon: Router, active: ["/admin/mikrotik/routers", "/admin/mikrotik/router", "/admin/router", "/admin/mikrotik"], group: "MikroTik" },
  { href: "/admin/radius", label: "RADIUS Server & CoA", icon: Radio, active: ["/admin/radius"], group: "MikroTik" },
  { href: "/tools/mikrotik", label: "Studio Script & Tools", icon: Terminal, active: ["/tools/mikrotik", "/mikrotik-tools"], group: "MikroTik" },
  { href: "/admin/pppoe", label: "PPPoE Secrets", icon: KeyRound, active: ["/admin/pppoe"], group: "MikroTik" },
  { href: "/admin/arp", label: "ARP Static & Binding", icon: Network, active: ["/admin/arp", "/admin/arp-binding"], group: "MikroTik" },
  { href: "/admin/mikrotik/profiles", label: "Profil Bandwidth", icon: SlidersHorizontal, active: ["/admin/mikrotik/profiles"], group: "MikroTik" },
  { href: "/admin/top-bandwidth", label: "Top Bandwidth", icon: Gauge, active: ["/admin/top-bandwidth"], group: "MikroTik" },

  // Hotspot MIKHMON
  { href: "/admin/mikhmon", label: "Mikhmon Online (Cloud)", icon: Globe, active: ["/admin/mikhmon"], group: "Hotspot MIKHMON" },
  { href: "/admin/desktop-licenses", label: "Mikhmon Offline (Lisensi)", icon: Laptop, active: ["/admin/desktop-licenses", "/admin/mikhmon-offline"], group: "Hotspot MIKHMON" },

  // Fiber & OLT
  { href: "/admin/olt", label: "OLT & ONU", icon: Server, active: ["/admin/olt", "/admin/onus"], group: "Fiber & OLT" },
  { href: "/admin/ont-devices", label: "Manajemen ONT (GenieACS)", icon: Wifi, active: ["/admin/ont-devices", "/admin/genieacs"], group: "Fiber & OLT" },
  { href: "/admin/map", label: "Mapping Network & GIS", icon: MapPin, active: ["/admin/map", "/admin/odp"], group: "Fiber & OLT" },
  { href: "/admin/vpn", label: "VPN Remote", icon: ShieldCheck, active: ["/admin/vpn"], group: "Fiber & OLT" },

  // Operasional
  { href: "/admin/whatsapp-templates", label: "Template Notifikasi WA", icon: MessageSquare, active: ["/admin/whatsapp-templates", "/admin/broadcast"], group: "Operasional" },
  { href: "/admin/telegram", label: "Bot & Notifikasi Telegram", icon: Send, active: ["/admin/telegram"], group: "Operasional" },
  { href: "/admin/notifications", label: "Log Aktivitas", icon: History, active: ["/admin/notifications"], group: "Operasional" },
  { href: "/admin/trouble", label: "Tiket Gangguan", icon: LifeBuoy, active: ["/admin/trouble", "/admin/tickets"], group: "Operasional" },
  { href: "/admin/employees", label: "Karyawan & Staff", icon: UserCog, active: ["/admin/employees", "/admin/agents", "/admin/collectors", "/admin/technicians"], group: "Operasional" },
  { href: "/admin/inventory", label: "Inventory Gudang", icon: Boxes, active: ["/admin/inventory"], group: "Operasional" },

  // Add-ons & Ekosistem
  { href: "/admin/addons", label: "Katalog Add-ons", icon: Puzzle, active: ["/admin/addons"], group: "Add-ons & Ekosistem" },

  // Sistem
  { href: "/admin/system-update", label: "Pembaruan Sistem", icon: RefreshCw, active: ["/admin/system-update"], group: "Sistem" },
  { href: "/admin/api-apps", label: "API Apps & Webhook", icon: Webhook, active: ["/admin/api-apps"], group: "Sistem" },
  { href: "/admin/my-settings", label: "Pengaturan Sistem", icon: Settings, active: ["/admin/my-settings", "/admin/settings", "/admin/sidebar-settings"], group: "Sistem" },
]

export const adminNavItems: NavItem[] = [
  { href: "/dashboard", label: "Beranda", icon: Home, active: ["/dashboard", "/admin/dashboard"] },
  { href: "/admin/billing/customers", label: "Pelanggan", icon: Users, active: ["/admin/billing/customers", "/admin/customers"] },
  { href: "/admin/billing/invoices", label: "Invoice", icon: Receipt, active: ["/admin/billing/invoices", "/admin/invoices"] },
]

export const adminBrand = { name: "NODERA", sub: "Admin Panel", logo: "/images/logo-white.png?v=35" }
