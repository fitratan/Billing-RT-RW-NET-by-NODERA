import { Home, Receipt, Wifi, LifeBuoy, KeyRound, User, type LucideIcon } from "lucide-react"
import { type SidebarItem } from "@/components/layout/app-layout"

export interface NavItem {
  href: string
  label: string
  icon: LucideIcon
  active?: string | string[]
}

export const portalNavItems: NavItem[] = [
  { href: "/portal", label: "Beranda", icon: Home, active: ["/portal"] },
  { href: "/portal/invoices", label: "Tagihan", icon: Receipt, active: ["/portal/invoices", "/portal/payment"] },
  { href: "/portal/usage", label: "Internet", icon: Wifi, active: ["/portal/usage", "/portal/wifi"] },
  { href: "/portal/laporan", label: "Bantuan", icon: LifeBuoy, active: ["/portal/laporan", "/portal/tos"] },
]

export const portalSidebarItems: SidebarItem[] = [
  { href: "/portal", label: "Beranda", icon: Home, active: ["/portal"], group: "Utama" },
  { href: "/portal/invoices", label: "Tagihan", icon: Receipt, active: ["/portal/invoices", "/portal/payment"], group: "Utama" },
  { href: "/portal/usage", label: "Status Sesi & Kuota", icon: Wifi, active: ["/portal/usage"], group: "Layanan" },
  { href: "/portal/wifi", label: "Pengaturan WiFi & PIN", icon: KeyRound, active: ["/portal/wifi"], group: "Layanan" },
  { href: "/portal/profile", label: "Profil Pengguna", icon: User, active: ["/portal/profile"], group: "Akun" },
  { href: "/portal/laporan", label: "Pusat Bantuan & Tiket", icon: LifeBuoy, active: ["/portal/laporan"], group: "Bantuan" },
]

export const portalBrand = { name: "NODERA", sub: "Portal Pelanggan", logo: "/images/logo-white.png?v=35" }
