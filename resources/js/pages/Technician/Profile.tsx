import * as React from "react"
import { AppLayout } from "@/components/layout/app-layout"
import {
  User,
  KeyRound,
  Eye,
  EyeOff,
  MapPinned,
  Phone,
  ShieldCheck,
  RefreshCw,
  LogOut,
  ChevronRight,
  UserCog,
  Save,
  Lock,
  Wrench,
  AtSign,
} from "lucide-react"
import { Badge } from "@/components/ui/badge"
import { PageProps } from "@/types"
import { technicianSidebarItems, technicianNavItems, technicianBrand } from "@/lib/technician-nav"
import { useForm, router, Link } from "@inertiajs/react"
import { cn } from "@/lib/utils"

export default function TechnicianProfilePage({
  technician,
}: PageProps<{
  technician: {
    id: number
    name: string
    username: string
    email: string | null
    phone: string | null
    routers: string
    commission_type: string
    commission_value: number
  }
}>) {
  const [showPwd, setShowPwd] = React.useState<Record<string, boolean>>({
    current: false,
    new: false,
    confirm: false,
  })

  const profile = useForm<{ username: string; phone: string }>({
    username: technician.username,
    phone: technician.phone ?? "",
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
    profile.post("/teknisi/profile", {
      preserveScroll: true,
    })
  }

  const handlePasswordSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    pwd.post("/teknisi/profile", {
      preserveScroll: true,
      onSuccess: () => pwd.reset(),
    })
  }

  return (
    <AppLayout
      title="Profil & Keamanan Teknisi"
      brand={technicianBrand}
      sidebarItems={technicianSidebarItems}
      navItems={technicianNavItems}
      hideHeader={true}
      className="p-0 sm:p-0 max-w-none bg-[#10141A]"
    >
      <div className="min-h-screen bg-[#10141A] text-slate-100 select-none pb-28 sm:pb-36">
        {/* =========================================================================
            SECTION 1: TOP ELECTRIC BLUE CANVAS (Obsidian Modern Standard)
            ========================================================================= */}
        <section className="relative bg-gradient-to-br from-[#0073C6] via-[#0056A0] to-[#003E78] px-4 pt-5 pb-10 sm:px-8 sm:pt-7 sm:pb-12 text-white shadow-sm rounded-b-[2rem] sm:rounded-b-[2.5rem] border-b border-white/15 overflow-hidden">
          <div className="absolute inset-0 overflow-hidden pointer-events-none">
            <div
              className="absolute inset-0 opacity-20"
              
            />
            <div className="absolute -top-12 -right-12 h-56 w-56 rounded-full border border-white/15 pointer-events-none" />
            <div className="absolute -top-6 -right-6 h-44 w-44 rounded-full border border-dashed border-white/10 pointer-events-none" />
          </div>

          <div className="relative max-w-7xl 2xl:max-w-[1700px] mx-auto space-y-4 sm:space-y-5">
            {/* Top Bar Header inside Canvas */}
            <div className="flex items-center justify-between gap-2.5 sm:gap-4">
              {/* Brand App Badge */}
              <div className="flex items-center gap-2.5 rounded-full border border-white/20 bg-black/25 px-3.5 py-1.5 backdrop-blur-md min-w-0 flex-1 sm:flex-initial overflow-hidden">
                <img
                  src="/images/logo-white.png?v=36"
                  alt="NODERA"
                  className="h-7 w-7 sm:h-8 sm:w-8 rounded-full object-contain bg-white/95 p-0.5 shrink-0"
                />
                <div className="leading-tight min-w-0 flex-1 overflow-hidden">
                  <div className="text-xs sm:text-sm font-black tracking-wider text-white truncate">
                    NODERA
                  </div>
                  <div className="mt-0.5 flex items-center gap-1.5">
                    <span className="rounded-md border border-[#00C2FF]/30 bg-[#00C2FF]/20 px-1.5 py-0.2 text-[9px] font-extrabold text-[#00C2FF] uppercase leading-tight shrink-0">
                      PROFIL
                    </span>
                  </div>
                  <div className="text-[10px] sm:text-xs font-medium text-white/80 truncate">
                    {technician.name}
                  </div>
                </div>
              </div>

              {/* Actions Right: Back to Dashboard */}
              <div className="flex items-center gap-1.5 sm:gap-2.5 shrink-0 [&>*]:shrink-0">
                <Link
                  href="/teknisi/dashboard"
                  className="flex h-9 sm:h-10 items-center gap-1.5 rounded-full border border-white/20 bg-white/15 hover:bg-white/25 text-white px-3.5 sm:px-4 text-xs sm:text-sm font-bold backdrop-blur-md transition-all active:scale-95"
                >
                  <span>Dashboard</span>
                  <ChevronRight className="h-3.5 w-3.5 sm:h-4 sm:w-4" />
                </Link>
              </div>
            </div>

            {/* Hero Profile Summary Card */}
            <div className="rounded-2xl border border-white/20 bg-white/10 p-5 sm:p-7 backdrop-blur-md shadow-sm">
              <div className="flex flex-col sm:flex-row items-start sm:items-center gap-4">
                <div className="flex h-16 w-16 shrink-0 items-center justify-center rounded-2xl bg-white text-[#0073C6] font-display text-2xl font-black">
                  {technician.name.slice(0, 2).toUpperCase()}
                </div>
                <div className="min-w-0 flex-1 space-y-1">
                  <div className="flex flex-wrap items-center gap-2">
                    <h2 className="font-display text-xl sm:text-2xl font-bold text-white">
                      {technician.name}
                    </h2>
                    <span className="rounded-md border border-[#00C2FF]/40 bg-[#00C2FF]/25 px-2.5 py-0.5 text-xs font-bold text-[#00C2FF]">
                      Teknisi Jaringan
                    </span>
                  </div>
                  <p className="font-mono text-xs text-white/80">@{technician.username}</p>
                  <div className="mt-2 flex flex-wrap items-center gap-2 text-xs text-white/90">
                    <span className="flex items-center gap-1.5 rounded-xl border border-white/20 bg-black/25 px-3 py-1">
                      <Wrench className="h-3.5 w-3.5 text-[#00C2FF]" />
                      Wilayah Router: <strong>{technician.routers}</strong>
                    </span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </section>

        {/* =========================================================================
            SECTION 2: EDIT USERNAME & GANTI PASSWORD (Obsidian Dark Cards)
            ========================================================================= */}
        <div className="max-w-7xl 2xl:max-w-[1700px] mx-auto px-4 sm:px-8 -mt-6 sm:-mt-8 relative z-10 space-y-5">
          <div className="grid gap-5 lg:grid-cols-2">
            {/* Form 1: Ubah Username Login & Kontak */}
            <div className="rounded-2xl border border-[#212B3B] bg-[#121720] p-5 sm:p-6 space-y-4">
              <div className="flex items-center gap-2.5 pb-3 border-b border-[#1E2633]">
                <div className="flex h-9 w-9 items-center justify-center rounded-xl bg-[#0073C6]/15 border border-[#0073C6]/30 text-[#00C2FF]">
                  <AtSign className="h-4 w-4" />
                </div>
                <div>
                  <h3 className="text-sm font-bold text-white">Username &amp; Kontak</h3>
                  <p className="text-[11px] text-slate-400">Ubah username login dan nomor WhatsApp</p>
                </div>
              </div>

              <form onSubmit={handleProfileSubmit} className="space-y-4">
                <div className="space-y-1.5">
                  <label className="text-xs font-semibold text-slate-400">Nama Teknisi (Oleh Admin)</label>
                  <input
                    type="text"
                    value={technician.name}
                    disabled
                    className="h-10 w-full rounded-xl border border-[#212B3B] bg-[#0B0E14] px-3 text-xs text-slate-400 cursor-not-allowed opacity-80"
                  />
                  <p className="text-[10px] text-slate-500">Nama resmi teknisi dikelola oleh Administrator.</p>
                </div>

                <div className="space-y-1.5">
                  <label className="text-xs font-semibold text-slate-300">Username Login</label>
                  <input
                    type="text"
                    value={profile.data.username}
                    onChange={(e) => profile.setData("username", e.target.value)}
                    required
                    className="h-10 w-full rounded-xl border border-[#212B3B] bg-[#0B0E14] px-3 font-mono text-xs text-white placeholder:text-slate-500 focus-visible:outline-none focus-visible:border-[#0073C6]"
                  />
                  {profile.errors.username && (
                    <p className="text-[10px] text-rose-400">{profile.errors.username}</p>
                  )}
                </div>

                <div className="space-y-1.5">
                  <label className="text-xs font-semibold text-slate-300">Nomor WhatsApp / HP</label>
                  <input
                    type="text"
                    value={profile.data.phone}
                    onChange={(e) => profile.setData("phone", e.target.value)}
                    placeholder="08xxxxxxxxxx"
                    className="h-10 w-full rounded-xl border border-[#212B3B] bg-[#0B0E14] px-3 text-xs text-white placeholder:text-slate-500 focus-visible:outline-none focus-visible:border-[#0073C6]"
                  />
                  {profile.errors.phone && (
                    <p className="text-[10px] text-rose-400">{profile.errors.phone}</p>
                  )}
                </div>

                <button
                  type="submit"
                  disabled={profile.processing}
                  className="flex h-10 w-full items-center justify-center gap-1.5 rounded-xl bg-[#0073C6] hover:bg-[#0084E3] disabled:opacity-50 text-xs font-bold text-white transition-all active:scale-95"
                >
                  <Save className="h-4 w-4" />
                  <span>{profile.processing ? "Menyimpan..." : "Simpan Perubahan Username"}</span>
                </button>
              </form>
            </div>

            {/* Form 2: Ganti Password */}
            <div className="rounded-2xl border border-[#212B3B] bg-[#121720] p-5 sm:p-6 space-y-4">
              <div className="flex items-center gap-2.5 pb-3 border-b border-[#1E2633]">
                <div className="flex h-9 w-9 items-center justify-center rounded-xl bg-amber-500/15 border border-amber-500/30 text-amber-400">
                  <Lock className="h-4 w-4" />
                </div>
                <div>
                  <h3 className="text-sm font-bold text-white">Keamanan &amp; Password</h3>
                  <p className="text-[11px] text-slate-400">Ganti kata sandi login akun teknisi Anda</p>
                </div>
              </div>

              <form onSubmit={handlePasswordSubmit} className="space-y-4">
                <div className="space-y-1.5">
                  <label className="text-xs font-semibold text-slate-300">Password Saat Ini</label>
                  <div className="relative">
                    <input
                      type={showPwd.current ? "text" : "password"}
                      value={pwd.data.current_password}
                      onChange={(e) => pwd.setData("current_password", e.target.value)}
                      required
                      placeholder="Masukkan password lama..."
                      className="h-10 w-full rounded-xl border border-[#212B3B] bg-[#0B0E14] pl-3 pr-10 text-xs text-white placeholder:text-slate-500 focus-visible:outline-none focus-visible:border-[#0073C6]"
                    />
                    <button
                      type="button"
                      onClick={() => toggle("current")}
                      className="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-white"
                    >
                      {showPwd.current ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                    </button>
                  </div>
                  {pwd.errors.current_password && (
                    <p className="text-[10px] text-rose-400">{pwd.errors.current_password}</p>
                  )}
                </div>

                <div className="space-y-1.5">
                  <label className="text-xs font-semibold text-slate-300">Password Baru</label>
                  <div className="relative">
                    <input
                      type={showPwd.new ? "text" : "password"}
                      value={pwd.data.new_password}
                      onChange={(e) => pwd.setData("new_password", e.target.value)}
                      required
                      placeholder="Minimal 6 karakter..."
                      className="h-10 w-full rounded-xl border border-[#212B3B] bg-[#0B0E14] pl-3 pr-10 text-xs text-white placeholder:text-slate-500 focus-visible:outline-none focus-visible:border-[#0073C6]"
                    />
                    <button
                      type="button"
                      onClick={() => toggle("new")}
                      className="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-white"
                    >
                      {showPwd.new ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                    </button>
                  </div>
                  {pwd.errors.new_password && (
                    <p className="text-[10px] text-rose-400">{pwd.errors.new_password}</p>
                  )}
                </div>

                <div className="space-y-1.5">
                  <label className="text-xs font-semibold text-slate-300">Konfirmasi Password Baru</label>
                  <div className="relative">
                    <input
                      type={showPwd.confirm ? "text" : "password"}
                      value={pwd.data.new_password_confirmation}
                      onChange={(e) => pwd.setData("new_password_confirmation", e.target.value)}
                      required
                      placeholder="Ulangi password baru..."
                      className="h-10 w-full rounded-xl border border-[#212B3B] bg-[#0B0E14] pl-3 pr-10 text-xs text-white placeholder:text-slate-500 focus-visible:outline-none focus-visible:border-[#0073C6]"
                    />
                    <button
                      type="button"
                      onClick={() => toggle("confirm")}
                      className="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-white"
                    >
                      {showPwd.confirm ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                    </button>
                  </div>
                </div>

                <button
                  type="submit"
                  disabled={pwd.processing}
                  className="flex h-10 w-full items-center justify-center gap-1.5 rounded-xl bg-amber-600 hover:bg-amber-500 disabled:opacity-50 text-xs font-bold text-white transition-all active:scale-95"
                >
                  <KeyRound className="h-4 w-4" />
                  <span>{pwd.processing ? "Memperbarui..." : "Update Password Baru"}</span>
                </button>
              </form>
            </div>
          </div>

          {/* Logout Section */}
          <div className="rounded-2xl border border-rose-500/20 bg-[#121720] p-5 sm:p-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div className="space-y-1">
              <h4 className="text-sm font-bold text-white flex items-center gap-2">
                <LogOut className="h-4 w-4 text-rose-400" />
                <span>Keluar dari Akun</span>
              </h4>
              <p className="text-xs text-slate-400">
                Akhiri sesi kerja teknisi pada perangkat ini dengan aman.
              </p>
            </div>

            <form action="/teknisi/logout" method="POST">
              <input type="hidden" name="_token" value={csrf()} />
              <button
                type="submit"
                className="flex items-center gap-2 rounded-xl border border-rose-500/40 bg-rose-500/15 hover:bg-rose-500/25 px-5 py-2.5 text-xs font-bold text-rose-300 transition-all active:scale-95"
              >
                <LogOut className="h-4 w-4" />
                <span>Logout Sekarang</span>
              </button>
            </form>
          </div>
        </div>
      </div>
    </AppLayout>
  )
}

function csrf(): string {
  const meta = document.querySelector('meta[name="csrf-token"]')
  return meta ? meta.getAttribute("content") ?? "" : ""
}
