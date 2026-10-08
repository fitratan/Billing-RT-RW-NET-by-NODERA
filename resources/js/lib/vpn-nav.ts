import {
  Home,
  Server,
  History,
  Wallet,
  User,
  Wifi,
  Grid,
  BookOpen,
  ShieldCheck,
  CreditCard,
  Send,
  Plus,
  Radio,
  Gift,
  QrCode,
  Laptop,
  type LucideIcon,
} from "lucide-react"

export interface NavItem {
  href: string
  label: string
  icon: LucideIcon
  active?: string | string[]
  group?: string
  badge?: string | number
}

export const vpnSidebarItems: NavItem[] = [
  // Utama
  { href: "/dashboard", label: "Beranda", icon: Home, active: ["/dashboard", "/vpn/dashboard", "/vpn"], group: "Utama" },
  { href: "/semua-fitur", label: "Semua Layanan", icon: Grid, active: ["/semua-fitur", "/vpn/semua-fitur"], group: "Utama" },

  // Layanan Cloud
  { href: "/noderapay", label: "NODERA Pay", icon: QrCode, active: ["/noderapay", "/noderapay/order"], group: "Layanan Cloud" },
  { href: "/akun", label: "VPN Remote", icon: Server, active: ["/akun", "/akun/order", "/akun/edit-port"], group: "Layanan Cloud" },
  { href: "/isp-billing", label: "Billing Management", icon: ShieldCheck, active: ["/isp-billing", "/isp-billing/order"], group: "Layanan Cloud" },
  { href: "/bookkeeping", label: "Pembukuan", icon: BookOpen, active: ["/bookkeeping", "/bookkeeping/order", "/vpn/bookkeeping"], group: "Layanan Cloud" },
  { href: "/arisan", label: "Pembukuan Arisan", icon: CreditCard, active: ["/arisan", "/arisan/order", "/vpn/arisan"], group: "Layanan Cloud" },

  // Layanan Mikhmon
  { href: "/mikhmon", label: "Mikhmon Online (Cloud)", icon: Wifi, active: ["/mikhmon", "/mikhmon/order"], group: "Layanan Mikhmon" },
  { href: "/desktop-licenses", label: "Mikhmon Offline (Lisensi)", icon: Laptop, active: ["/desktop-licenses"], group: "Layanan Mikhmon" },

  // Keuangan
  { href: "/topup", label: "Topup Saldo", icon: Wallet, active: ["/topup", "/topup/create", "/topup/confirm", "/topup/history"], group: "Keuangan" },
  { href: "/referral", label: "Referral", icon: Gift, active: ["/referral", "/vpn/referral"], group: "Keuangan" },
  { href: "/history", label: "Riwayat Transaksi", icon: History, active: ["/history"], group: "Keuangan" },

  // Akun
  { href: "/profil", label: "Profil & Akun", icon: User, active: ["/profil"], group: "Akun" },
]

export const vpnNavItems: NavItem[] = [
  { href: "/dashboard", label: "Beranda", icon: Home },
  { href: "/akun", label: "VPN", icon: Server, active: ["/akun"] },
  { href: "/mikhmon", label: "Mikhmon", icon: Wifi, active: ["/mikhmon"] },
  { href: "/topup", label: "Topup", icon: Wallet, active: ["/topup"] },
  { href: "/profil", label: "Profil", icon: User, active: ["/profil"] },
]

export const vpnBrand = { name: "NODERA", sub: "Layanan Cloud", logo: "/images/logo-white.png?v=35" }

