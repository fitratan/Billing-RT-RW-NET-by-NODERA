import {
  LayoutDashboard,
  Users,
  UserCheck,
  Receipt,
  Sparkles,
  Wallet,
  CreditCard,
  History,
  ShieldCheck,
  KeyRound,
  Home,
  type LucideIcon,
} from "lucide-react"
import { type SidebarItem } from "@/components/layout/app-layout"

export interface NavItem {
  href: string
  label: string
  icon: LucideIcon
  active?: string | string[]
}

export function getArisanAdminNav(subdomain: string): {
  navItems: NavItem[]
  sidebarItems: SidebarItem[]
  brand: { name: string; sub: string; logo: string }
} {
  const base = `/arisan-app/${subdomain}/admin`
  return {
    navItems: [
      { href: base, label: "Beranda", icon: LayoutDashboard, active: [base, `${base}/dashboard`] },
      { href: `${base}/groups`, label: "Kloter", icon: Users, active: [`${base}/groups`] },
      { href: `${base}/payments`, label: "Iuran", icon: Receipt, active: [`${base}/payments`] },
      { href: `${base}/draws`, label: "Undian", icon: Sparkles, active: [`${base}/draws`] },
      { href: `${base}/cashflow`, label: "Kas", icon: Wallet, active: [`${base}/cashflow`] },
    ],
    sidebarItems: [
      { href: base, label: "Ringkasan", icon: LayoutDashboard, active: [base, `${base}/dashboard`], group: "Utama" },
      { href: `${base}/groups`, label: "Kloter & Slot", icon: Users, active: [`${base}/groups`], group: "Pengelolaan" },
      { href: `${base}/members`, label: "Daftar Anggota", icon: UserCheck, active: [`${base}/members`], group: "Pengelolaan" },
      { href: `${base}/payments`, label: "Iuran & Pembayaran", icon: Receipt, active: [`${base}/payments`], group: "Transaksi" },
      { href: `${base}/draws`, label: "Undian Digital", icon: Sparkles, active: [`${base}/draws`], group: "Transaksi" },
      { href: `${base}/cashflow`, label: "Buku Kas", icon: Wallet, active: [`${base}/cashflow`], group: "Keuangan" },
      { href: `${base}/settings/payments`, label: "Rekening & QRIS", icon: CreditCard, active: [`${base}/settings/payments`], group: "Pengaturan" },
    ],
    brand: {
      name: "ARISAN",
      sub: "Pengelola Arisan",
      logo: "/images/logo-white.png?v=35",
    },
  }
}

export function getArisanMemberNav(subdomain: string): {
  navItems: NavItem[]
  sidebarItems: SidebarItem[]
  brand: { name: string; sub: string; logo: string }
} {
  const base = `/arisan-app/${subdomain}/member`
  return {
    navItems: [
      { href: `${base}/card`, label: "Kartu", icon: CreditCard, active: [`${base}/card`] },
      { href: `${base}/history`, label: "Riwayat", icon: History, active: [`${base}/history`] },
      { href: `${base}/transparency`, label: "Transparansi", icon: ShieldCheck, active: [`${base}/transparency`] },
      { href: `${base}/profile`, label: "Profil & PIN", icon: KeyRound, active: [`${base}/profile`] },
    ],
    sidebarItems: [
      { href: `${base}/card`, label: "Kartu Arisan", icon: CreditCard, active: [`${base}/card`], group: "Utama" },
      { href: `${base}/history`, label: "Riwayat Setoran", icon: History, active: [`${base}/history`], group: "Transaksi" },
      { href: `${base}/transparency`, label: "Transparansi Kloter", icon: ShieldCheck, active: [`${base}/transparency`], group: "Informasi" },
      { href: `${base}/profile`, label: "Profil & Ganti PIN", icon: KeyRound, active: [`${base}/profile`], group: "Akun" },
    ],
    brand: {
      name: "ARISAN",
      sub: "Portal Peserta",
      logo: "/images/logo-white.png?v=35",
    },
  }
}
