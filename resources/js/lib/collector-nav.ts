import {
  Home,
  Users,
  Activity,
  KeyRound,
  Wallet,
  MapPin,
  UserCog,
  Receipt,
  Inbox,
  History,
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

export const collectorNavItems: NavItem[] = [
  { key: "dashboard", href: "/kolektor/dashboard", label: "Beranda", icon: Home },
  { key: "invoices", href: "/kolektor/invoices", label: "Invoice", icon: Receipt, active: ["/kolektor/invoices"] },
  { key: "customers", href: "/kolektor/customers", label: "Pelanggan", icon: Users, active: ["/kolektor/customers"] },
  { key: "earnings", href: "/kolektor/earnings", label: "Pendapatan", icon: Wallet, active: ["/kolektor/earnings"] },
  { key: "map", href: "/kolektor/map", label: "Peta", icon: MapPin, active: ["/kolektor/map"] },
]

export const collectorSidebarItems: (SidebarItem & { key?: string })[] = [
  { key: "dashboard", href: "/kolektor/dashboard", label: "Beranda", icon: Home, active: ["/kolektor/dashboard"], group: "Utama" },
  { key: "invoices", href: "/kolektor/invoices", label: "Invoice & Tagihan", icon: Receipt, active: ["/kolektor/invoices"], group: "Utama" },
  { key: "customers", href: "/kolektor/customers", label: "Kelola Pelanggan", icon: Users, active: ["/kolektor/customers"], group: "Utama" },
  { key: "pool", href: "/kolektor/pool", label: "Pool Pekerjaan & Tiket", icon: Inbox, active: ["/kolektor/pool", "/teknisi/pool"], group: "Operasional" },
  { key: "pppoe", href: "/kolektor/pppoe", label: "Monitoring PPPoE", icon: KeyRound, active: ["/kolektor/pppoe"], group: "Operasional" },
  { key: "top_bandwidth", href: "/kolektor/top-bandwidth", label: "Top Bandwidth", icon: Activity, active: ["/kolektor/top-bandwidth"], group: "Operasional" },
  { key: "map", href: "/kolektor/map", label: "Peta Jaringan GIS", icon: MapPin, active: ["/kolektor/map"], group: "Operasional" },
  { key: "history", href: "/kolektor/history", label: "Riwayat Selesai", icon: History, active: ["/kolektor/history", "/teknisi/history"], group: "Laporan" },
  { key: "earnings", href: "/kolektor/earnings", label: "Pendapatan & Komisi", icon: Wallet, active: ["/kolektor/earnings"], group: "Laporan" },
  { key: "profile", href: "/kolektor/profile", label: "Profil & Keamanan", icon: UserCog, active: ["/kolektor/profile"], group: "Pengaturan" },
]

export const getCollectorSidebarItems = (perms?: Record<string, boolean>) => {
  if (!perms) return collectorSidebarItems
  return collectorSidebarItems.filter((item) => !item.key || perms[item.key] !== false)
}

export const getCollectorNavItems = (perms?: Record<string, boolean>) => {
  if (!perms) return collectorNavItems
  return collectorNavItems.filter((item) => !item.key || perms[item.key] !== false)
}

export const collectorBrand = { name: "NODERA", sub: "Kolektor", logo: "/images/logo-white.png?v=35" }
