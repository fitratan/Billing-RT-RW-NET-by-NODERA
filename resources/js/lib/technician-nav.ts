import {
  Home,
  Users,
  Inbox,
  Map,
  History,
  Wallet,
  KeyRound,
  UserCog,
  Receipt,
  Activity,
  Network,
  type LucideIcon,
} from "lucide-react"
import { type SidebarItem } from "@/components/layout/app-layout"

export interface NavItem {
  href: string
  label: string
  icon: LucideIcon
  active?: string | string[]
  key?: string
}

export const technicianNavItems: NavItem[] = [
  { key: "dashboard", href: "/teknisi/dashboard", label: "Beranda", icon: Home },
  { key: "customers", href: "/teknisi/customers", label: "Pelanggan", icon: Users, active: ["/teknisi/customers"] },
  { key: "pool", href: "/teknisi/pool", label: "Pool", icon: Inbox, active: ["/teknisi/pool"] },
  { key: "pppoe", href: "/teknisi/pppoe", label: "PPPoE", icon: KeyRound, active: ["/teknisi/pppoe"] },
  { key: "map", href: "/teknisi/map", label: "Peta", icon: Map, active: ["/teknisi/map"] },
]

export const technicianSidebarItems: (SidebarItem & { key?: string })[] = [
  { key: "dashboard", href: "/teknisi/dashboard", label: "Beranda Tiket", icon: Home, group: "Utama" },
  { key: "customers", href: "/teknisi/customers", label: "Kelola Pelanggan", icon: Users, active: ["/teknisi/customers"], group: "Utama" },
  { key: "collect_payment", href: "/kolektor/dashboard", label: "Invoice & Kasir POS", icon: Receipt, active: ["/kolektor/dashboard", "/teknisi/invoices"], group: "Utama" },
  { key: "pool", href: "/teknisi/pool", label: "Pool Gangguan & Pasang", icon: Inbox, active: ["/teknisi/pool"], group: "Operasional" },
  { key: "pppoe", href: "/teknisi/pppoe", label: "PPPoE & Redaman Optik", icon: KeyRound, active: ["/teknisi/pppoe"], group: "Operasional" },
  { key: "arp", href: "/teknisi/arp", label: "ARP Static & Binding", icon: Network, active: ["/teknisi/arp", "/teknisi/arp-binding"], group: "Operasional" },
  { key: "top_bandwidth", href: "/kolektor/top-bandwidth", label: "Top Bandwidth", icon: Activity, active: ["/kolektor/top-bandwidth", "/teknisi/top-bandwidth"], group: "Operasional" },
  { key: "map", href: "/teknisi/map", label: "Peta ODP/ONU GIS", icon: Map, active: ["/teknisi/map"], group: "Operasional" },
  { key: "history", href: "/teknisi/history", label: "Riwayat Selesai", icon: History, active: ["/teknisi/history"], group: "Laporan" },
  { key: "earnings", href: "/teknisi/earnings", label: "Pendapatan & Insentif", icon: Wallet, active: ["/teknisi/earnings"], group: "Laporan" },
  { key: "profile", href: "/teknisi/profile", label: "Profil & Keamanan", icon: UserCog, active: ["/teknisi/profile"], group: "Pengaturan" },
]

export const getTechnicianSidebarItems = (perms?: Record<string, boolean>) => {
  if (!perms) return technicianSidebarItems
  return technicianSidebarItems.filter((item) => !item.key || perms[item.key] !== false)
}

export const getTechnicianNavItems = (perms?: Record<string, boolean>) => {
  if (!perms) return technicianNavItems
  return technicianNavItems.filter((item) => !item.key || perms[item.key] !== false)
}

export const technicianBrand = { name: "NODERA", sub: "Teknisi", logo: "/images/logo-white.png?v=35" }
