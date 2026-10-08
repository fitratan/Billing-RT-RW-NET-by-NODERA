import * as React from "react"
import { AppLayout } from "@/components/layout/app-layout"
import {
  User,
  KeyRound,
  Eye,
  EyeOff,
  Phone,
  ShieldCheck,
  Save,
  Lock,
  DollarSign,
  AtSign,
  Router as RouterIcon,
  Wallet,
  CheckCircle2,
} from "lucide-react"
import { PageProps } from "@/types"
import { collectorSidebarItems, collectorNavItems, collectorBrand } from "@/lib/collector-nav"
import { useForm } from "@inertiajs/react"
import { formatIDR } from "@/lib/utils"

export default function CollectorProfilePage({
  collector,
}: PageProps<{
  collector: {
    id: number
    name: string
    username: string
    phone: string | null
    email: string | null
    router_name: string
    commission_type: string
    commission_value: number
    balance: number
  }
}>) {
  const [showPwd, setShowPwd] = React.useState<Record<string, boolean>>({
    current: false,
    new: false,
    confirm: false,
  })

  const profile = useForm<{ username: string; phone: string }>({
    username: collector.username,
    phone: collector.phone ?? "",
  })

  const pwd = useForm<{
    current_password: string
    new_password: string
    new_password_confirmation: string
  }>({
    current_password: "",
    new_password: "",
    new_password_confirmation: "",
  })

  const toggle = (k: string) => setShowPwd((s) => ({ ...s, [k]: !s[k] }))

  const handleProfileSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    profile.post("/kolektor/profile", {
      preserveScroll: true,
    })
  }

  const handlePasswordSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    pwd.post("/kolektor/profile", {
      preserveScroll: true,
      onSuccess: () => pwd.reset(),
    })
  }

  return (
    <AppLayout
      title="Profil & Keamanan"
      brand={collectorBrand}
      sidebarItems={collectorSidebarItems}
      navItems={collectorNavItems}
    >
      <div className="space-y-4 sm:space-y-6 select-none pb-20 max-w-5xl">
        {/* ── TOP SUMMARY CARD ── */}
        <div className="rounded-2xl border border-gray-200 bg-white p-5 sm:p-6 shadow-xs dark:border-gray-800 dark:bg-white/[0.03]">
          <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
            <div className="flex items-center gap-4 min-w-0">
              <div className="flex h-14 w-14 sm:h-16 sm:w-16 shrink-0 items-center justify-center rounded-2xl bg-brand-500 text-white font-black text-2xl shadow-sm">
                {collector.name.charAt(0).toUpperCase()}
              </div>
              <div className="min-w-0">
                <div className="flex items-center gap-2">
                  <h2 className="text-lg sm:text-xl font-bold text-gray-900 dark:text-white truncate">
                    {collector.name}
                  </h2>
                  <span className="inline-flex items-center gap-1 rounded-full bg-emerald-500 px-2.5 py-0.5 text-[10px] font-bold text-white shadow-xs shrink-0">
                    <ShieldCheck className="h-3 w-3" />
                    <span>Petugas Kolektor</span>
                  </span>
                </div>
                <p className="text-xs text-gray-500 dark:text-gray-400 font-mono mt-0.5">
                  @{collector.username} {collector.phone ? `• ${collector.phone}` : ""}
                </p>
                <div className="flex flex-wrap items-center gap-2 mt-2">
                  <span className="inline-flex items-center gap-1 text-xs font-semibold text-gray-700 dark:text-gray-300">
                    <RouterIcon className="h-3.5 w-3.5 text-brand-500" />
                    <span>Area: <strong>{collector.router_name || "Semua Router"}</strong></span>
                  </span>
                  <span className="text-gray-300 dark:text-gray-700">•</span>
                  <span className="inline-flex items-center gap-1 text-xs font-semibold text-gray-700 dark:text-gray-300">
                    <DollarSign className="h-3.5 w-3.5 text-emerald-500" />
                    <span>Komisi: <strong>{collector.commission_type === "percentage" ? `${collector.commission_value}%` : `${formatIDR(collector.commission_value)} / inv`}</strong></span>
                  </span>
                </div>
              </div>
            </div>

            <div className="rounded-xl border border-gray-100 dark:border-gray-800 bg-gray-50/75 dark:bg-gray-800/40 p-3 sm:text-right w-full sm:w-auto">
              <span className="text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 block">
                Saldo Kas / Komisi
              </span>
              <span className="text-lg sm:text-xl font-black text-emerald-600 dark:text-emerald-400 block mt-0.5">
                {formatIDR(collector.balance || 0)}
              </span>
            </div>
          </div>
        </div>

        <div className="grid grid-cols-1 md:grid-cols-2 gap-4 sm:gap-6">
          {/* ── FORM 1: INFORMASI PROFIL & KONTAK ── */}
          <div className="rounded-2xl border border-gray-200 bg-white p-5 sm:p-6 shadow-xs dark:border-gray-800 dark:bg-white/[0.03] space-y-4">
            <div className="flex items-center gap-2.5 pb-3 border-b border-gray-100 dark:border-gray-800">
              <div className="flex h-8 w-8 items-center justify-center rounded-xl bg-brand-500 text-white shadow-xs">
                <User className="h-4 w-4" />
              </div>
              <div>
                <h3 className="font-bold text-sm sm:text-base text-gray-900 dark:text-white">
                  Informasi Kontak
                </h3>
                <p className="text-xs text-gray-500 dark:text-gray-400">
                  Perbarui username dan nomor WhatsApp aktif Anda
                </p>
              </div>
            </div>

            <form onSubmit={handleProfileSubmit} className="space-y-4">
              <div className="space-y-1.5">
                <label className="text-xs font-bold text-gray-700 dark:text-gray-300">
                  Username Login
                </label>
                <div className="relative">
                  <AtSign className="absolute left-3.5 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
                  <input
                    type="text"
                    value={profile.data.username}
                    onChange={(e) => profile.setData("username", e.target.value)}
                    placeholder="Username kolektor"
                    className="h-10 w-full rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 pl-10 pr-3 text-xs text-gray-900 dark:text-white font-medium focus:outline-none focus:border-brand-500 transition"
                  />
                </div>
                {profile.errors.username && (
                  <p className="text-[11px] font-bold text-rose-500">{profile.errors.username}</p>
                )}
              </div>

              <div className="space-y-1.5">
                <label className="text-xs font-bold text-gray-700 dark:text-gray-300">
                  Nomor Telepon / WhatsApp
                </label>
                <div className="relative">
                  <Phone className="absolute left-3.5 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
                  <input
                    type="text"
                    value={profile.data.phone}
                    onChange={(e) => profile.setData("phone", e.target.value)}
                    placeholder="08123456789"
                    className="h-10 w-full rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 pl-10 pr-3 text-xs text-gray-900 dark:text-white font-medium focus:outline-none focus:border-brand-500 transition"
                  />
                </div>
                {profile.errors.phone && (
                  <p className="text-[11px] font-bold text-rose-500">{profile.errors.phone}</p>
                )}
              </div>

              <div className="pt-2">
                <button
                  type="submit"
                  disabled={profile.processing}
                  className="flex h-10 w-full items-center justify-center gap-1.5 rounded-xl bg-brand-500 hover:bg-brand-600 active:scale-95 text-xs font-bold text-white shadow-xs transition disabled:opacity-50 cursor-pointer"
                >
                  <Save className="h-4 w-4" />
                  <span>{profile.processing ? "Menyimpan..." : "Simpan Perubahan Kontak"}</span>
                </button>
              </div>
            </form>
          </div>

          {/* ── FORM 2: GANTI PASSWORD AKUN ── */}
          <div className="rounded-2xl border border-gray-200 bg-white p-5 sm:p-6 shadow-xs dark:border-gray-800 dark:bg-white/[0.03] space-y-4">
            <div className="flex items-center gap-2.5 pb-3 border-b border-gray-100 dark:border-gray-800">
              <div className="flex h-8 w-8 items-center justify-center rounded-xl bg-amber-500 text-white shadow-xs">
                <Lock className="h-4 w-4" />
              </div>
              <div>
                <h3 className="font-bold text-sm sm:text-base text-gray-900 dark:text-white">
                  Ganti Password
                </h3>
                <p className="text-xs text-gray-500 dark:text-gray-400">
                  Amankan akun penagihan Anda secara berkala
                </p>
              </div>
            </div>

            <form onSubmit={handlePasswordSubmit} className="space-y-4">
              <div className="space-y-1.5">
                <label className="text-xs font-bold text-gray-700 dark:text-gray-300">
                  Password Lama
                </label>
                <div className="relative">
                  <input
                    type={showPwd.current ? "text" : "password"}
                    value={pwd.data.current_password}
                    onChange={(e) => pwd.setData("current_password", e.target.value)}
                    placeholder="Masukkan password saat ini"
                    className="h-10 w-full rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 px-3 pr-10 text-xs text-gray-900 dark:text-white font-medium focus:outline-none focus:border-brand-500 transition"
                  />
                  <button
                    type="button"
                    onClick={() => toggle("current")}
                    className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"
                  >
                    {showPwd.current ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                  </button>
                </div>
                {pwd.errors.current_password && (
                  <p className="text-[11px] font-bold text-rose-500">{pwd.errors.current_password}</p>
                )}
              </div>

              <div className="space-y-1.5">
                <label className="text-xs font-bold text-gray-700 dark:text-gray-300">
                  Password Baru
                </label>
                <div className="relative">
                  <input
                    type={showPwd.new ? "text" : "password"}
                    value={pwd.data.new_password}
                    onChange={(e) => pwd.setData("new_password", e.target.value)}
                    placeholder="Minimal 6 karakter"
                    className="h-10 w-full rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 px-3 pr-10 text-xs text-gray-900 dark:text-white font-medium focus:outline-none focus:border-brand-500 transition"
                  />
                  <button
                    type="button"
                    onClick={() => toggle("new")}
                    className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"
                  >
                    {showPwd.new ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                  </button>
                </div>
                {pwd.errors.new_password && (
                  <p className="text-[11px] font-bold text-rose-500">{pwd.errors.new_password}</p>
                )}
              </div>

              <div className="space-y-1.5">
                <label className="text-xs font-bold text-gray-700 dark:text-gray-300">
                  Konfirmasi Password Baru
                </label>
                <div className="relative">
                  <input
                    type={showPwd.confirm ? "text" : "password"}
                    value={pwd.data.new_password_confirmation}
                    onChange={(e) => pwd.setData("new_password_confirmation", e.target.value)}
                    placeholder="Ulangi password baru"
                    className="h-10 w-full rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 px-3 pr-10 text-xs text-gray-900 dark:text-white font-medium focus:outline-none focus:border-brand-500 transition"
                  />
                  <button
                    type="button"
                    onClick={() => toggle("confirm")}
                    className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"
                  >
                    {showPwd.confirm ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                  </button>
                </div>
              </div>

              <div className="pt-2">
                <button
                  type="submit"
                  disabled={pwd.processing}
                  className="flex h-10 w-full items-center justify-center gap-1.5 rounded-xl bg-amber-500 hover:bg-amber-600 active:scale-95 text-xs font-bold text-white shadow-xs transition disabled:opacity-50 cursor-pointer"
                >
                  <KeyRound className="h-4 w-4" />
                  <span>{pwd.processing ? "Menyimpan..." : "Update Password Baru"}</span>
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </AppLayout>
  )
}
