import {
  Home,
  Building2,
  Wallet,
  Settings,
  Users,
  Boxes,
  Server,
  BarChart3,
  Key,
  Cpu,
  ShoppingCart,
  Gift,
  QrCode,
  Globe,
  Activity,
  Receipt,
  ArrowUpRight,
  Sliders,
  Smartphone,
  Clock,
  Coins,
  GraduationCap,
  Laptop,
  BookOpen,
  Radio,
  Shield,
  type LucideIcon,
} from "lucide-react"

export interface NavItem {
  href: string
  label: string
  icon: LucideIcon
  active?: string | string[]
  group?: string
}

export const superadminSidebarItems: NavItem[] = [
  // 1. Utama
  { href: "/superadmin", label: "Dashboard", icon: Home, group: "Utama" },

  // 2. ISP Billing SaaS
  { href: "/superadmin/tenants", label: "Tenant & Mitra ISP", icon: Building2, active: ["/superadmin/tenants", "/superadmin/registrasi", "/superadmin/admin-tenant"], group: "ISP Billing SaaS" },
  { href: "/superadmin/licenses", label: "Lisensi Source Code", icon: Key, active: ["/superadmin/licenses"], group: "ISP Billing SaaS" },
  { href: "/superadmin/invoices", label: "Invoices & Tagihan SaaS", icon: Receipt, active: ["/superadmin/invoices"], group: "ISP Billing SaaS" },

  // 3. Layanan VPN
  { href: "/superadmin/vpn/monitoring", label: "Monitoring Sesi VPN", icon: Activity, active: ["/superadmin/vpn/monitoring"], group: "Layanan VPN" },
  { href: "/superadmin/vpn/accounts", label: "Akun & Layanan VPN", icon: Globe, active: ["/superadmin/vpn/accounts"], group: "Layanan VPN" },
  { href: "/superadmin/vpn/users", label: "Pengguna & Member", icon: Users, active: ["/superadmin/vpn/users", "/superadmin/vpn/user-history"], group: "Layanan VPN" },
  { href: "/superadmin/vpn-servers", label: "Server VPN", icon: Server, active: ["/superadmin/vpn-servers", "/superadmin/vpn/servers"], group: "Layanan VPN" },
  { href: "/superadmin/auto-install-chr", label: "Auto Install CHR", icon: Cpu, active: ["/superadmin/auto-install-chr"], group: "Layanan VPN" },

  // 4. Layanan Cloud
  { href: "/superadmin/bookkeeping", label: "Cloud Pembukuan", icon: BookOpen, active: ["/superadmin/bookkeeping"], group: "Layanan Cloud" },
  { href: "/superadmin/referrals", label: "Program Referral", icon: Gift, active: ["/superadmin/referrals"], group: "Layanan Cloud" },
  { href: "/superadmin/genieacs", label: "GenieACS TR-069", icon: Radio, active: ["/superadmin/genieacs"], group: "Layanan Cloud" },
  { href: "/superadmin/docker", label: "Docker & Daemon", icon: Cpu, active: ["/superadmin/docker"], group: "Layanan Cloud" },

  // 5. Layanan Mikhmon
  { href: "/superadmin/mikhmon", label: "Mikhmon Online (Cloud)", icon: Globe, active: ["/superadmin/mikhmon"], group: "Layanan Mikhmon" },
  { href: "/superadmin/desktop-licenses", label: "Mikhmon Offline (Lisensi)", icon: Laptop, active: ["/superadmin/desktop-licenses"], group: "Layanan Mikhmon" },

  // 4. Member Merchant (NODERA PAY)
  { href: "/superadmin/noderapay", label: "Dashboard Gateway", icon: QrCode, active: ["/superadmin/noderapay"], group: "Member Merchant (NODERA PAY)" },
  { href: "/superadmin/noderapay/merchants", label: "Kelola Merchant", icon: Users, active: ["/superadmin/noderapay/merchants"], group: "Member Merchant (NODERA PAY)" },
  { href: "/superadmin/noderapay/transactions", label: "Riwayat Transaksi", icon: Receipt, active: ["/superadmin/noderapay/transactions"], group: "Member Merchant (NODERA PAY)" },
  { href: "/superadmin/noderapay/withdrawals", label: "Riwayat Penarikan", icon: ArrowUpRight, active: ["/superadmin/noderapay/withdrawals"], group: "Member Merchant (NODERA PAY)" },
  { href: "/superadmin/noderapay/settings", label: "Konfigurasi Gateway", icon: Sliders, active: ["/superadmin/noderapay/settings"], group: "Member Merchant (NODERA PAY)" },

  // 5. WhatsApp Gateway
  { href: "/superadmin/wagateway/dashboard", label: "Dashboard WhatsApp", icon: Smartphone, active: ["/superadmin/wagateway/dashboard"], group: "WhatsApp Gateway" },
  { href: "/superadmin/wagateway/merchants", label: "Kelola Merchant WA", icon: Users, active: ["/superadmin/wagateway/merchants"], group: "WhatsApp Gateway" },
  { href: "/superadmin/wagateway/queue", label: "Antrean Pesan", icon: Clock, active: ["/superadmin/wagateway/queue"], group: "WhatsApp Gateway" },
  { href: "/superadmin/wagateway/devices", label: "Perangkat WhatsApp", icon: Smartphone, active: ["/superadmin/wagateway/devices"], group: "WhatsApp Gateway" },
  { href: "/superadmin/wagateway/topups", label: "Topup Saldo WA", icon: Coins, active: ["/superadmin/wagateway/topups"], group: "WhatsApp Gateway" },
  { href: "/superadmin/wagateway/settings", label: "Pengaturan WhatsApp", icon: Settings, active: ["/superadmin/wagateway/settings"], group: "WhatsApp Gateway" },

  // 8. Sistem & Pengaturan
  { href: "/superadmin/docker", label: "Docker & Daemon", icon: Cpu, active: ["/superadmin/docker"], group: "Sistem & Pengaturan" },
  { href: "/superadmin/audit-logs", label: "Audit & Log Sistem", icon: BarChart3, active: ["/superadmin/audit-logs", "/superadmin/client-logs", "/superadmin/notifications"], group: "Sistem & Pengaturan" },
  { href: "/superadmin/settings", label: "Pengaturan Platform", icon: Settings, active: ["/superadmin/settings", "/superadmin/whatsapp-templates", "/superadmin/2fa/setup", "/superadmin/backup"], group: "Sistem & Pengaturan" },

  // 9. Ujian PKL & Siswa Magang
  { href: "/superadmin/pkl-exam", label: "Overview & Ringkasan", icon: GraduationCap, active: ["/superadmin/pkl-exam"], group: "Ujian PKL & Magang" },
  { href: "/superadmin/pkl-exam?tab=sessions", label: "Sesi Ujian & Timer", icon: GraduationCap, active: ["/superadmin/pkl-exam?tab=sessions"], group: "Ujian PKL & Magang" },
  { href: "/superadmin/pkl-exam?tab=questions", label: "Bank Soal Ujian", icon: GraduationCap, active: ["/superadmin/pkl-exam?tab=questions"], group: "Ujian PKL & Magang" },
  { href: "/superadmin/pkl-exam?tab=students", label: "Peserta Siswa PKL", icon: GraduationCap, active: ["/superadmin/pkl-exam?tab=students"], group: "Ujian PKL & Magang" },
  { href: "/superadmin/pkl-exam?tab=leaderboard", label: "Hasil Ujian & PDF", icon: GraduationCap, active: ["/superadmin/pkl-exam?tab=leaderboard"], group: "Ujian PKL & Magang" },
  { href: "/superadmin/pkl-exam?tab=settings", label: "Bot Telegram & Grup", icon: GraduationCap, active: ["/superadmin/pkl-exam?tab=settings"], group: "Ujian PKL & Magang" },
]

export const superadminNavItems: NavItem[] = [
  { href: "/superadmin", label: "Beranda", icon: Home, active: ["/superadmin"] },
  { href: "/superadmin/tenants", label: "Tenant", icon: Building2, active: ["/superadmin/tenants", "/superadmin/registrasi"] },
  { href: "/superadmin/vpn/monitoring", label: "Live VPN", icon: Activity, active: ["/superadmin/vpn/monitoring", "/superadmin/vpn/accounts"] },
  { href: "/superadmin/finance", label: "Finansial", icon: Wallet, active: ["/superadmin/finance", "/superadmin/finance/history", "/superadmin/expenses"] },
]

export const superadminBrand = { name: "NODERA", sub: "Superadmin", logo: "/images/logo-white.png?v=36" }
