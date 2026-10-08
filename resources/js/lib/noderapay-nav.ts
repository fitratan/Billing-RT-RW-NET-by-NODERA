import {
  LayoutDashboard,
  CreditCard,
  Receipt,
  ArrowDownToLine,
  Key,
  Sliders,
  FileCode2,
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

export const noderapaySidebarItems: NavItem[] = [
  // Utama
  { href: "/dashboard", label: "Dashboard", icon: LayoutDashboard, active: ["/dashboard", "/noderapay/dashboard"], group: "Utama" },
  { href: "/payment-methods", label: "Metode Pembayaran", icon: CreditCard, active: ["/payment-methods", "/noderapay/payment-methods"], group: "Utama" },
  { href: "/transactions", label: "Data Transaksi", icon: Receipt, active: ["/transactions", "/noderapay/transactions"], group: "Transaksi" },
  { href: "/withdrawals", label: "Penarikan Saldo", icon: ArrowDownToLine, active: ["/withdrawals", "/noderapay/withdrawals"], group: "Keuangan" },
  { href: "/credentials", label: "Kredensial & Webhook", icon: Key, active: ["/credentials", "/noderapay/credentials"], group: "Integrasi" },
  { href: "/settings", label: "Pengaturan Toko", icon: Sliders, active: ["/settings", "/noderapay/settings"], group: "Akun" },
  { href: "/docs", label: "Dokumentasi API", icon: FileCode2, active: ["/docs", "/noderapay/docs"], group: "Integrasi" },
]

export const noderapayNavItems: NavItem[] = [
  { href: "/dashboard", label: "Dashboard", icon: LayoutDashboard, active: ["/dashboard", "/noderapay/dashboard"] },
  { href: "/payment-methods", label: "Metode Bayar", icon: CreditCard, active: ["/payment-methods", "/noderapay/payment-methods"] },
  { href: "/transactions", label: "Transaksi", icon: Receipt, active: ["/transactions", "/noderapay/transactions"] },
  { href: "/withdrawals", label: "Penarikan", icon: ArrowDownToLine, active: ["/withdrawals", "/noderapay/withdrawals"] },
  { href: "/credentials", label: "API & Webhook", icon: Key, active: ["/credentials", "/noderapay/credentials"] },
  { href: "/settings", label: "Pengaturan", icon: Sliders, active: ["/settings", "/noderapay/settings"] },
  { href: "/docs", label: "API Docs", icon: FileCode2, active: ["/docs", "/noderapay/docs"] },
]

export const noderapayBrand = {
  name: "NODERA PAY",
  sub: "Payment Gateway Hub",
  logo: "/images/logo-white.png?v=35",
}
