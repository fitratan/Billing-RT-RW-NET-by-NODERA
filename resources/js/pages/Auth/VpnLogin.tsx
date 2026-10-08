import React, { useState } from "react"
import { useForm, usePage, Link, Head } from "@inertiajs/react"
import {
  ShieldCheck,
  Lock,
  Eye,
  EyeOff,
  AlertCircle,
  ArrowRight,
  Mail,
  Globe,
  Server,
  Activity,
} from "lucide-react"
import { LanguageSwitcher } from "@/components/layout/language-switcher"
import { PwaInstallBanner } from "@/components/ui/pwa-install-banner"
import { PageProps } from "@/types"

export default function VpnLoginPage({
  company,
  flash: initialFlash,
}: PageProps<{
  company?: { name?: string; phone?: string }
  flash?: { error?: string | null; msg?: string | null; warning?: string | null; info?: string | null }
}>) {
  const page = usePage<PageProps>()
  const flash = (page.props.flash || initialFlash) as
    | { error?: string | null; msg?: string | null; warning?: string | null; info?: string | null }
    | undefined
  const [showPwd, setShowPwd] = useState(false)
  const { data, setData, post, processing, errors } = useForm<{
    email: string
    password: string
    remember: boolean
  }>({
    email: "",
    password: "",
    remember: true,
  })

  const submit = (e: React.FormEvent) => {
    e.preventDefault()
    const isVpnPath = typeof window !== "undefined" && window.location.pathname.startsWith("/vpn")
    post(isVpnPath ? "/vpn/login" : "/login", { preserveScroll: true })
  }

  const isVpnPath = typeof window !== "undefined" && window.location.pathname.startsWith("/vpn")
  const registerUrl = isVpnPath ? "/vpn/register" : "/register"

  return (
    <div className="min-h-screen bg-[#10141A] text-slate-100 flex items-center justify-center p-4 sm:p-6 lg:p-10 font-sans select-none">
      <Head title="Masuk Akun - NODERA Cloud Panel" />

      {/* Main Unified Box Container */}
      <div className="w-full max-w-4xl mx-auto rounded-3xl border border-[#212B3B] bg-[#121720] shadow-2xl overflow-hidden grid lg:grid-cols-12">
        {/* LEFT PANEL - SOLID BRAND HERO */}
        <div className="lg:col-span-5 relative bg-[#0073C6] p-6 sm:p-8 text-white flex flex-col justify-between border-b lg:border-b-0 lg:border-r border-[#212B3B]">
          <div className="space-y-6">
            <div className="flex items-center justify-between gap-2">
              <div className="flex items-center gap-3 select-none">
                <img
                  src="/images/logo-white.png?v=36"
                  alt="NODERA"
                  className="h-9 w-9 object-contain shrink-0"
                  onError={(e) => {
                    ;(e.target as HTMLElement).style.display = "none"
                  }}
                />
                <div>
                  <div className="text-sm font-black tracking-tight text-white uppercase leading-none">
                    {company?.name || "NODERA"}
                  </div>
                  <div className="text-[10px] text-white/80 font-medium mt-0.5">
                    CLOUD MANAGEMENT HUB
                  </div>
                </div>
              </div>
              <LanguageSwitcher />
            </div>

            <div className="pt-4 sm:pt-6 space-y-2.5">
              <h1 className="text-2xl sm:text-3xl font-black tracking-tight text-white leading-tight">
                Pusat Kendali Cloud &amp; Multi-Aplikasi Bisnis
              </h1>
              <p className="text-xs sm:text-sm text-white/90 leading-relaxed">
                Satu platform terintegrasi untuk VPN Remote multi-device, server Mikhmon, SaaS billing ISP, kas pembukuan, serta aneka modul aplikasi cloud sesuai kebutuhan Anda.
              </p>
            </div>

            {/* Feature Pills */}
            <div className="space-y-2 pt-2">
              <div className="flex items-center gap-2 text-xs text-white/95">
                <div className="flex h-5 w-5 items-center justify-center rounded-md bg-white/20">
                  <Server className="h-3 w-3" />
                </div>
                <span className="font-semibold">VPN Remote Multi-Device (Router, OLT &amp; Server)</span>
              </div>
              <div className="flex items-center gap-2 text-xs text-white/95">
                <div className="flex h-5 w-5 items-center justify-center rounded-md bg-white/20">
                  <Globe className="h-3 w-3" />
                </div>
                <span className="font-semibold">Server Cloud Mikhmon &amp; Aplikasi On-Demand</span>
              </div>
              <div className="flex items-center gap-2 text-xs text-white/95">
                <div className="flex h-5 w-5 items-center justify-center rounded-md bg-white/20">
                  <Activity className="h-3 w-3" />
                </div>
                <span className="font-semibold">SaaS Billing ISP, Pembukuan &amp; Master Wallet</span>
              </div>
            </div>
          </div>

          <div className="pt-6 mt-6 border-t border-white/20 text-[11px] text-white/80 flex items-center justify-between">
            <span>NODERA Cloud Ecosystem</span>
            <span className="font-mono">v4.0</span>
          </div>
        </div>

        {/* RIGHT PANEL - CLEAN TAILADMIN DARK FORM */}
        <div className="lg:col-span-7 p-6 sm:p-10 flex flex-col justify-center bg-[#121720]">
          <div className="pb-4 border-b border-[#212B3B]">
            <h2 className="text-xl font-bold text-white tracking-tight">Masuk ke Cloud Panel</h2>
            <p className="text-xs text-slate-400 mt-1">
              Masukkan email dan kata sandi akun Anda untuk melanjutkan
            </p>
          </div>

          <div className="mt-3">
            <PwaInstallBanner />
          </div>

          {/* Flash / Error Banner */}
          {flash?.error && (
            <div className="mt-4 flex items-start gap-2.5 rounded-xl border border-rose-500/30 bg-rose-500/10 px-4 py-3 text-xs font-medium text-rose-400">
              <AlertCircle className="h-4 w-4 shrink-0 text-rose-500 mt-0.5" />
              <span>{flash.error}</span>
            </div>
          )}

          {flash?.msg && (
            <div className="mt-4 flex items-start gap-2.5 rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-xs font-medium text-emerald-400">
              <ShieldCheck className="h-4 w-4 shrink-0 text-emerald-500 mt-0.5" />
              <span>{flash.msg}</span>
            </div>
          )}

          {errors.email && (
            <div className="mt-4 flex items-start gap-2.5 rounded-xl border border-rose-500/30 bg-rose-500/10 px-4 py-3 text-xs font-medium text-rose-400">
              <AlertCircle className="h-4 w-4 shrink-0 text-rose-500 mt-0.5" />
              <span>{errors.email}</span>
            </div>
          )}

          {/* Google SSO Button */}
          <div className="mt-5">
            <a
              href="/auth/google?context=vpn&intent=login"
              className="w-full h-11 sm:h-12 rounded-xl bg-[#161C26] hover:bg-[#1A2230] text-white font-bold text-xs sm:text-sm flex items-center justify-center gap-3 transition-all duration-200 cursor-pointer border border-[#212B3B] active:scale-[0.99]"
            >
              <svg className="h-5 w-5 shrink-0" viewBox="0 0 24 24">
                <path
                  fill="#4285F4"
                  d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"
                />
                <path
                  fill="#34A853"
                  d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"
                />
                <path
                  fill="#FBBC05"
                  d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"
                />
                <path
                  fill="#EA4335"
                  d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"
                />
              </svg>
              <span>Masuk dengan Google</span>
            </a>

            <div className="relative my-4 flex items-center justify-center">
              <div className="absolute inset-0 flex items-center">
                <div className="w-full border-t border-[#212B3B]"></div>
              </div>
              <div className="relative bg-[#121720] px-3 text-[11px] font-bold uppercase tracking-wider text-slate-500">
                Atau dengan Email Akun
              </div>
            </div>
          </div>

          <form onSubmit={submit} className="space-y-4">
            <div>
              <label className="block text-xs font-bold text-slate-300 mb-1.5">
                Email / Username Akun <span className="text-rose-500">*</span>
              </label>
              <div className="relative">
                <Mail className="absolute left-3.5 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-500" />
                <input
                  type="text"
                  value={data.email}
                  onChange={(e) => setData("email", e.target.value)}
                  placeholder="nama@email.com / username"
                  required
                  className="h-11 w-full rounded-xl border border-[#212B3B] bg-[#161C26] pl-10 pr-4 text-xs font-medium text-white placeholder:text-slate-500 focus:border-[#0073C6] focus:bg-[#121720] focus:outline-hidden transition"
                />
              </div>
            </div>

            <div>
              <div className="flex items-center justify-between mb-1.5">
                <label className="block text-xs font-bold text-slate-300">
                  Kata Sandi <span className="text-rose-500">*</span>
                </label>
                <Link
                  href="/forgot-password"
                  className="text-xs font-semibold text-[#0073C6] hover:underline"
                >
                  Lupa sandi?
                </Link>
              </div>
              <div className="relative">
                <Lock className="absolute left-3.5 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-500" />
                <input
                  type={showPwd ? "text" : "password"}
                  value={data.password}
                  onChange={(e) => setData("password", e.target.value)}
                  placeholder="Masukkan kata sandi"
                  required
                  className="h-11 w-full rounded-xl border border-[#212B3B] bg-[#161C26] pl-10 pr-10 text-xs font-medium text-white placeholder:text-slate-500 focus:border-[#0073C6] focus:bg-[#121720] focus:outline-hidden transition"
                />
                <button
                  type="button"
                  onClick={() => setShowPwd(!showPwd)}
                  className="absolute right-3.5 top-1/2 -translate-y-1/2 text-slate-500 hover:text-slate-300 cursor-pointer"
                >
                  {showPwd ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                </button>
              </div>
            </div>

            <div className="flex items-center justify-between pt-1">
              <label className="flex items-center gap-2 cursor-pointer select-none">
                <input
                  type="checkbox"
                  checked={data.remember}
                  onChange={(e) => setData("remember", e.target.checked)}
                  className="h-4 w-4 rounded border-[#212B3B] bg-[#161C26] text-[#0073C6] focus:ring-0 cursor-pointer"
                />
                <span className="text-xs font-medium text-slate-400">Ingat sesi saya</span>
              </label>
            </div>

            <button
              type="submit"
              disabled={processing}
              className="w-full h-11 rounded-xl bg-[#0073C6] hover:bg-[#0060A6] active:scale-[0.99] font-bold text-xs sm:text-sm text-white shadow-theme-xs transition flex items-center justify-center gap-2 cursor-pointer disabled:opacity-60 disabled:cursor-not-allowed"
            >
              <span>{processing ? "Memverifikasi..." : "Masuk ke Dashboard"}</span>
              <ArrowRight className="h-4 w-4" />
            </button>
          </form>

          {/* Footer Register Link */}
          <div className="mt-6 pt-5 border-t border-[#212B3B] text-center">
            <p className="text-xs text-slate-400">
              Belum memiliki akun NODERA Cloud?{" "}
              <Link
                href={registerUrl}
                className="font-bold text-[#0073C6] hover:underline"
              >
                Daftar Akun Baru
              </Link>
            </p>
          </div>
        </div>
      </div>
    </div>
  )
}
