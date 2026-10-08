import React, { useState, useEffect } from "react"
import { useForm, usePage, Link, Head } from "@inertiajs/react"
import {
  User,
  Lock,
  Tag,
  Mail,
  Phone,
  ArrowRight,
  AlertCircle,
  Check,
  Eye,
  EyeOff,
  Globe,
  Server,
  Activity,
} from "lucide-react"
import { LanguageSwitcher } from "@/components/layout/language-switcher"
import { PageProps } from "@/types"

export default function VpnRegisterPage({
  company,
  flash: initialFlash,
  initialRef,
}: PageProps<{
  company?: { name?: string; phone?: string }
  flash?: { error?: string | null; msg?: string | null; warning?: string | null; info?: string | null }
  initialRef?: string | null
}>) {
  const page = usePage<PageProps>()
  const flash = (page.props.flash || initialFlash) as
    | { error?: string | null; msg?: string | null; warning?: string | null; info?: string | null }
    | undefined
  const [showPwd, setShowPwd] = useState<Record<string, boolean>>({ pw: false, cf: false })
  const [showRefInput, setShowRefInput] = useState<boolean>(!!initialRef)
  const [clientError, setClientError] = useState<string | null>(null)

  const { data, setData, post, processing, errors } = useForm<{
    name: string
    email: string
    phone: string
    password: string
    password_confirmation: string
    referral_code: string
  }>({
    name: "",
    email: "",
    phone: "",
    password: "",
    password_confirmation: "",
    referral_code: initialRef || "",
  })

  // Watch for backend validation errors
  useEffect(() => {
    const errList = Object.values(errors).filter(Boolean)
    if (errList.length > 0) {
      setClientError(errList[0] as string)
    }
  }, [errors])

  // Watch for flash messages
  useEffect(() => {
    if (flash?.error) {
      setClientError(flash.error)
    }
  }, [flash])

  const submit = (e: React.FormEvent) => {
    e.preventDefault()
    setClientError(null)

    if (!data.name || data.name.trim().length < 2) {
      setClientError("Nama lengkap wajib diisi minimal 2 karakter.")
      return
    }

    if (!data.email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(data.email.trim())) {
      setClientError("Format email tidak valid atau belum diisi.")
      return
    }

    if (!data.phone || data.phone.trim().length < 5) {
      setClientError("Nomor WhatsApp / HP wajib diisi dengan benar.")
      return
    }

    if (!data.password || data.password.length < 6) {
      setClientError("Kata sandi minimal 6 karakter.")
      return
    }

    if (data.password !== data.password_confirmation) {
      setClientError("Konfirmasi kata sandi tidak cocok.")
      return
    }

    const isVpnPath = typeof window !== "undefined" && window.location.pathname.startsWith("/vpn")
    const targetUrl = isVpnPath ? "/vpn/register" : "/register"

    post(targetUrl, {
      preserveScroll: true,
      onError: (errs) => {
        const errorList = Object.values(errs).filter(Boolean)
        setClientError(errorList[0] || "Silakan periksa kembali formulir yang diisi.")
      },
    })
  }

  const isVpnPath = typeof window !== "undefined" && window.location.pathname.startsWith("/vpn")
  const loginUrl = isVpnPath ? "/vpn/login" : "/login"

  return (
    <div className="min-h-screen bg-[#10141A] text-slate-100 flex items-center justify-center p-4 sm:p-6 lg:p-10 font-sans select-none">
      <Head title="Daftar Akun Baru - NODERA Cloud Panel" />

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
                Mulai Kelola Cloud &amp; Aplikasi Anda
              </h1>
              <p className="text-xs sm:text-sm text-white/90 leading-relaxed">
                Daftar sekarang untuk mendapatkan akses instan ke seluruh ekosistem VPN Remote, server Mikhmon, billing ISP, pembukuan digital, dan aneka modul aplikasi cloud.
              </p>
            </div>

            {/* Feature Pills */}
            <div className="space-y-2 pt-2">
              <div className="flex items-center gap-2 text-xs text-white/95">
                <div className="flex h-5 w-5 items-center justify-center rounded-md bg-white/20">
                  <Server className="h-3 w-3" />
                </div>
                <span className="font-semibold">VPN Remote Multi-Device Dedicated SSTP</span>
              </div>
              <div className="flex items-center gap-2 text-xs text-white/95">
                <div className="flex h-5 w-5 items-center justify-center rounded-md bg-white/20">
                  <Globe className="h-3 w-3" />
                </div>
                <span className="font-semibold">Server Cloud Mikhmon &amp; Modul On-Demand</span>
              </div>
              <div className="flex items-center gap-2 text-xs text-white/95">
                <div className="flex h-5 w-5 items-center justify-center rounded-md bg-white/20">
                  <Activity className="h-3 w-3" />
                </div>
                <span className="font-semibold">Aktivasi Instan &amp; Ekosistem Fleksibel</span>
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
            <h2 className="text-xl font-bold text-white tracking-tight">Buat Akun Baru</h2>
            <p className="text-xs text-slate-400 mt-1">
              Lengkapi data berikut untuk mendaftar akun Cloud Panel
            </p>
          </div>

          {/* Google SSO Button */}
          <div className="mt-5">
            <a
              href={`/auth/google?context=vpn&intent=register${data.referral_code ? `&ref=${encodeURIComponent(data.referral_code)}` : ""}`}
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
              <span>Daftar Cepat dengan Google</span>
            </a>

            <div className="relative my-4 flex items-center justify-center">
              <div className="absolute inset-0 flex items-center">
                <div className="w-full border-t border-[#212B3B]"></div>
              </div>
              <div className="relative bg-[#121720] px-3 text-[11px] font-bold uppercase tracking-wider text-slate-500">
                Atau Lengkapi Data Manual
              </div>
            </div>
          </div>

          {/* Flash / Error Banner */}
          {(clientError || Object.keys(errors).length > 0) && (
            <div className="mb-4 flex items-start gap-2.5 rounded-xl border border-rose-500/30 bg-rose-500/10 px-4 py-3 text-xs font-medium text-rose-400 animate-in fade-in">
              <AlertCircle className="h-4 w-4 shrink-0 text-rose-500 mt-0.5" />
              <span>
                {clientError ||
                  Object.values(errors)[0] ||
                  "Silakan periksa kembali isian formulir di bawah."}
              </span>
            </div>
          )}

          <form onSubmit={submit} className="space-y-3.5">
            <div>
              <label className="block text-xs font-bold text-slate-300 mb-1">
                Nama Lengkap <span className="text-rose-500">*</span>
              </label>
              <div className="relative">
                <User className="absolute left-3.5 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-500" />
                <input
                  type="text"
                  value={data.name}
                  onChange={(e) => setData("name", e.target.value)}
                  placeholder="Nama lengkap Anda"
                  required
                  className="h-10 w-full rounded-xl border border-[#212B3B] bg-[#161C26] pl-10 pr-4 text-xs font-medium text-white placeholder:text-slate-500 focus:border-[#0073C6] focus:bg-[#121720] focus:outline-hidden transition"
                />
              </div>
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <div>
                <label className="block text-xs font-bold text-slate-300 mb-1">
                  Email Akun <span className="text-rose-500">*</span>
                </label>
                <div className="relative">
                  <Mail className="absolute left-3.5 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-500" />
                  <input
                    type="email"
                    value={data.email}
                    onChange={(e) => setData("email", e.target.value)}
                    placeholder="nama@email.com"
                    required
                    className="h-10 w-full rounded-xl border border-[#212B3B] bg-[#161C26] pl-10 pr-4 text-xs font-medium text-white placeholder:text-slate-500 focus:border-[#0073C6] focus:bg-[#121720] focus:outline-hidden transition"
                  />
                </div>
              </div>

              <div>
                <label className="block text-xs font-bold text-slate-300 mb-1">
                  No. WhatsApp / HP <span className="text-rose-500">*</span>
                </label>
                <div className="relative">
                  <Phone className="absolute left-3.5 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-500" />
                  <input
                    type="tel"
                    value={data.phone}
                    onChange={(e) => setData("phone", e.target.value)}
                    placeholder="08123456789"
                    required
                    className="h-10 w-full rounded-xl border border-[#212B3B] bg-[#161C26] pl-10 pr-4 text-xs font-medium text-white placeholder:text-slate-500 focus:border-[#0073C6] focus:bg-[#121720] focus:outline-hidden transition"
                  />
                </div>
              </div>
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <div>
                <label className="block text-xs font-bold text-slate-300 mb-1">
                  Kata Sandi <span className="text-rose-500">*</span>
                </label>
                <div className="relative">
                  <Lock className="absolute left-3.5 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-500" />
                  <input
                    type={showPwd.pw ? "text" : "password"}
                    value={data.password}
                    onChange={(e) => setData("password", e.target.value)}
                    placeholder="Min. 6 karakter"
                    required
                    className="h-10 w-full rounded-xl border border-[#212B3B] bg-[#161C26] pl-10 pr-10 text-xs font-medium text-white placeholder:text-slate-500 focus:border-[#0073C6] focus:bg-[#121720] focus:outline-hidden transition"
                  />
                  <button
                    type="button"
                    onClick={() => setShowPwd((prev) => ({ ...prev, pw: !prev.pw }))}
                    className="absolute right-3.5 top-1/2 -translate-y-1/2 text-slate-500 hover:text-slate-300 cursor-pointer"
                  >
                    {showPwd.pw ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                  </button>
                </div>
              </div>

              <div>
                <label className="block text-xs font-bold text-slate-300 mb-1">
                  Ulangi Sandi <span className="text-rose-500">*</span>
                </label>
                <div className="relative">
                  <Lock className="absolute left-3.5 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-500" />
                  <input
                    type={showPwd.cf ? "text" : "password"}
                    value={data.password_confirmation}
                    onChange={(e) => setData("password_confirmation", e.target.value)}
                    placeholder="Ulangi sandi"
                    required
                    className="h-10 w-full rounded-xl border border-[#212B3B] bg-[#161C26] pl-10 pr-10 text-xs font-medium text-white placeholder:text-slate-500 focus:border-[#0073C6] focus:bg-[#121720] focus:outline-hidden transition"
                  />
                  <button
                    type="button"
                    onClick={() => setShowPwd((prev) => ({ ...prev, cf: !prev.cf }))}
                    className="absolute right-3.5 top-1/2 -translate-y-1/2 text-slate-500 hover:text-slate-300 cursor-pointer"
                  >
                    {showPwd.cf ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                  </button>
                </div>
              </div>
            </div>

            {/* Referral Code Field / Toggle */}
            <div className="pt-1">
              {!showRefInput ? (
                <button
                  type="button"
                  onClick={() => setShowRefInput(true)}
                  className="text-xs text-[#0073C6] hover:underline flex items-center gap-1.5 font-medium transition-colors cursor-pointer"
                >
                  <Tag className="h-3.5 w-3.5" />
                  <span>Punya kode referral / mitra?</span>
                </button>
              ) : (
                <div className="space-y-1 rounded-xl border border-[#212B3B] bg-[#161C26] p-3 animate-in fade-in">
                  <div className="flex items-center justify-between">
                    <label className="text-xs font-bold text-slate-300 flex items-center gap-1.5">
                      <Tag className="h-3.5 w-3.5 text-emerald-400" />
                      <span>Kode Referral / Mitra</span>
                      <span className="text-[10px] text-slate-500 font-normal">(Opsional)</span>
                    </label>
                    {!initialRef && (
                      <button
                        type="button"
                        onClick={() => {
                          setData("referral_code", "")
                          setShowRefInput(false)
                        }}
                        className="text-[10px] text-slate-400 hover:text-rose-400 cursor-pointer"
                      >
                        Batal
                      </button>
                    )}
                  </div>
                  <input
                    type="text"
                    value={data.referral_code}
                    onChange={(e) => setData("referral_code", e.target.value.toUpperCase())}
                    placeholder="Contoh: MITRA123"
                    className="h-9 w-full rounded-lg border border-[#212B3B] bg-[#121720] px-3 text-xs font-mono font-bold text-white uppercase tracking-wider placeholder:text-slate-600 focus:border-[#0073C6] focus:outline-hidden"
                  />
                  {initialRef && (
                    <p className="text-[11px] text-emerald-400 flex items-center gap-1 mt-1 font-medium">
                      <Check className="h-3 w-3" /> Undangan mitra referral aktif
                    </p>
                  )}
                </div>
              )}
            </div>

            <button
              type="submit"
              disabled={processing}
              className="w-full mt-2 h-11 rounded-xl bg-[#0073C6] hover:bg-[#0060A6] active:scale-[0.99] font-bold text-xs sm:text-sm text-white shadow-theme-xs transition flex items-center justify-center gap-2 cursor-pointer disabled:opacity-60 disabled:cursor-not-allowed"
            >
              <span>{processing ? "Mendaftarkan..." : "Daftar Akun Sekarang"}</span>
              <ArrowRight className="h-4 w-4" />
            </button>
          </form>

          {/* Footer Login Link */}
          <div className="mt-5 pt-4 border-t border-[#212B3B] text-center">
            <p className="text-xs text-slate-400">
              Sudah memiliki akun?{" "}
              <Link
                href={loginUrl}
                className="font-bold text-[#0073C6] hover:underline"
              >
                Masuk di sini
              </Link>
            </p>
          </div>
        </div>
      </div>
    </div>
  )
}
