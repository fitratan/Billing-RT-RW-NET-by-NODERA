import { useForm, Link, Head, usePage } from "@inertiajs/react"
import {
  User,
  Building2,
  AtSign,
  Lock,
  Eye,
  EyeOff,
  Mail,
  Phone,
  Check,
  ShieldCheck,
  Globe,
  QrCode,
  ArrowRight,
  ArrowLeft,
  Activity,
  MapPin,
  Printer,
  MessageCircle,
  AlertCircle,
  Server,
  X,
  Tag,
  Info,
} from "lucide-react"
import { Button } from "@/components/ui/button"
import { Badge } from "@/components/ui/badge"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { LanguageSwitcher } from "@/components/layout/language-switcher"
import { PwaInstallBanner } from "@/components/ui/pwa-install-banner"
import { PageProps } from "@/types"
import { useState, useEffect } from "react"
import { cn, formatIDR } from "@/lib/utils"

interface Package {
  id: number
  name: string
  max_customers: number
  monthly_price: number
  semi_annual_price: number
  annual_price: number
  duration_options: string
}

interface BankInfo {
  id: number
  bank_name: string
  bank_account: string
  bank_holder: string
}

interface GatewayChannel {
  code: string
  name: string
  gateway: string
  channel: string
}

interface GatewayInfo {
  id: string
  name: string
  channels: GatewayChannel[]
}

interface PaymentConfig {
  enable_gateway?: boolean
  enable_qris_manual?: boolean
  enable_bank_manual?: boolean
  default_gateway?: string
  gateways?: GatewayInfo[]
}

export default function RegisterPage({
  packages = [],
  company,
  app_domain,
  bank_accounts = [],
  qris,
  payment_config,
  selected_package_id,
  initial_ref,
  flash: initialFlash,
}: PageProps<{
  packages: Package[]
  company: { name: string }
  app_domain?: string
  bank_accounts?: BankInfo[]
  qris?: { image: string; text?: string | null } | null
  payment_config?: PaymentConfig
  selected_package_id?: string | number
  initial_ref?: string | null
  flash?: { msg?: string | null; error?: string | null; info?: string | null; warning?: string | null }
}>) {
  const page = usePage<PageProps<{ 
    googleRegisterData?: { name?: string; email?: string; google_id?: string; avatar?: string }
    flash?: { msg?: string | null; error?: string | null; info?: string | null; warning?: string | null }
  }>>()
  const googleData = page.props.googleRegisterData
  const flash = page.props.flash || initialFlash

  // Build available payment options
  const paymentOptions: Array<{ id: string; label: string; group: string; type: "gateway" | "qris" | "bank"; data?: any }> = []

  if (payment_config?.enable_gateway !== false && (payment_config?.gateways?.length ?? 0) > 0) {
    payment_config?.gateways?.forEach((gw) => {
      gw.channels.forEach((ch) => {
        paymentOptions.push({
          id: ch.code,
          label: ch.name + " (Instan & Otomatis)",
          group: gw.name,
          type: "gateway",
          data: ch,
        })
      })
    })
  }

  if (payment_config?.enable_qris_manual !== false && qris) {
    paymentOptions.push({
      id: "qris_manual",
      label: "QRIS Dinamis Superadmin (Verifikasi Kode Unik)",
      group: "QRIS",
      type: "qris",
    })
  }

  if (payment_config?.enable_bank_manual !== false && bank_accounts.length > 0) {
    paymentOptions.push({
      id: "bank_manual",
      label: "Transfer Rekening Bank Manual (BCA, BRI, Mandiri, BNI)",
      group: "Bank Manual",
      type: "bank",
    })
  }

  // Fallback if empty
  if (paymentOptions.length === 0) {
    paymentOptions.push({
      id: "qris_manual",
      label: "QRIS (Instan & Otomatis)",
      group: "QRIS",
      type: "qris",
    })
  }

  const [showPwd, setShowPwd] = useState(false)
  const [agreeTerms, setAgreeTerms] = useState(true)
  const rawHost = app_domain || (typeof window !== "undefined" ? window.location.hostname : "")
  const hostDomain = rawHost ? rawHost.replace(/^(panel|www)\./i, "") : (typeof window !== "undefined" ? window.location.hostname.replace(/^(panel|www)\./i, "") : "")

  const resolvedInitialRef = (() => {
    if (initial_ref) return initial_ref
    if (typeof window !== "undefined") {
      const urlParams = new URLSearchParams(window.location.search)
      return urlParams.get("ref") || urlParams.get("referral_code") || ""
    }
    return ""
  })()

  const [showRefInput, setShowRefInput] = useState<boolean>(!!resolvedInitialRef)

  const initialPkgId = (() => {
    if (selected_package_id && packages.some(p => String(p.id) === String(selected_package_id))) {
      return String(selected_package_id)
    }
    if (typeof window !== "undefined") {
      const urlParams = new URLSearchParams(window.location.search)
      const paramId = urlParams.get("package_id")
      if (paramId && packages.some(p => String(p.id) === paramId)) {
        return paramId
      }
    }
    return packages[0] ? String(packages[0].id) : ""
  })()

  const { data, setData, post, processing, errors } = useForm<{
    name: string
    company: string
    username: string
    slug: string
    password: string
    email: string
    phone: string
    package_id: string
    duration: string
    payment_method: string
    payment_bank: string
    referral_code: string
  }>({
    name: googleData?.name || "",
    company: "",
    username: "",
    slug: "",
    password: "",
    email: googleData?.email || "",
    phone: "",
    package_id: initialPkgId,
    duration: "1",
    payment_method: "auto",
    payment_bank: "QRIS",
    referral_code: resolvedInitialRef ? resolvedInitialRef.toUpperCase() : "",
  })

  const [clientError, setClientError] = useState<string | null>(null)
  const [toast, setToast] = useState<{
    type: "error" | "success" | "info"
    title: string
    message?: string
  } | null>(null)

  useEffect(() => {
    if (!toast) return
    const timer = setTimeout(() => {
      setToast(null)
    }, 6000)
    return () => clearTimeout(timer)
  }, [toast])

  // Watch for flash notifications (e.g. redirected from unauthenticated Google login)
  useEffect(() => {
    if (flash?.info || flash?.msg) {
      setToast({
        type: "info",
        title: "Pemberitahuan Pendaftaran",
        message: (flash.info || flash.msg) as string
      })
    } else if (flash?.error) {
      setToast({
        type: "error",
        title: "Pemberitahuan",
        message: flash.error
      })
    } else if (flash?.warning) {
      setToast({
        type: "error",
        title: "Peringatan",
        message: flash.warning
      })
    }
  }, [flash])

  // Auto-sync Google data to form fields
  useEffect(() => {
    if (googleData) {
      setData((prev) => ({
        ...prev,
        name: prev.name || googleData.name || "",
        email: prev.email || googleData.email || "",
      }))
    }
  }, [googleData])

  // Watch for backend validation errors
  useEffect(() => {
    const errList = Object.values(errors).filter(Boolean)
    if (errList.length > 0) {
      const firstMsg = errList[0] as string
      setClientError(firstMsg)
      setToast({
        type: "error",
        title: "Validasi Gagal",
        message: firstMsg
      })
    }
  }, [errors])

  const submit = (e: React.FormEvent) => {
    e.preventDefault()
    setClientError(null)

    if (!agreeTerms) {
      const msg = "Anda wajib menyetujui Syarat & Ketentuan serta Kebijakan Privasi terlebih dahulu."
      setClientError(msg)
      setToast({ type: "error", title: "Persetujuan Diperlukan", message: msg })
      return
    }

    if (!data.name || data.name.trim().length < 2) {
      const msg = "Nama penanggung jawab wajib diisi."
      setClientError(msg)
      setToast({ type: "error", title: "Nama Lengkap Wajib", message: msg })
      window.scrollTo({ top: 0, behavior: "smooth" })
      return
    }

    if (!data.company || data.company.trim().length < 2) {
      const msg = "Nama instansi / brand ISP wajib diisi."
      setClientError(msg)
      setToast({ type: "error", title: "Instansi Wajib", message: msg })
      window.scrollTo({ top: 0, behavior: "smooth" })
      return
    }

    if (!data.email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(data.email.trim())) {
      const msg = "Format email tidak valid atau belum diisi."
      setClientError(msg)
      setToast({ type: "error", title: "Email Tidak Valid", message: msg })
      window.scrollTo({ top: 0, behavior: "smooth" })
      return
    }

    if (!data.phone || data.phone.trim().length < 5) {
      const msg = "Nomor WhatsApp / telepon wajib diisi."
      setClientError(msg)
      setToast({ type: "error", title: "Nomor WhatsApp Wajib", message: msg })
      window.scrollTo({ top: 0, behavior: "smooth" })
      return
    }

    if (!data.username || data.username.trim().length < 3) {
      const msg = "Username login wajib diisi minimal 3 karakter."
      setClientError(msg)
      setToast({ type: "error", title: "Username Wajib", message: msg })
      window.scrollTo({ top: 0, behavior: "smooth" })
      return
    }

    if (!data.slug || data.slug.length < 3) {
      const msg = "Subdomain harus diisi minimal 3 karakter (1 kata saja)."
      setClientError(msg)
      setToast({ type: "error", title: "Subdomain Belum Sesuai", message: msg })
      window.scrollTo({ top: 0, behavior: "smooth" })
      return
    }

    if (/\s/.test(data.slug) || !/^[a-z0-9]+$/.test(data.slug)) {
      const msg = "Subdomain hanya boleh 1 kata (hanya huruf kecil dan angka tanpa spasi)."
      setClientError(msg)
      setToast({ type: "error", title: "Format Subdomain Salah", message: msg })
      window.scrollTo({ top: 0, behavior: "smooth" })
      return
    }

    if (!data.password || data.password.length < 6) {
      const msg = "Kata sandi minimal 6 karakter."
      setClientError(msg)
      setToast({ type: "error", title: "Password Terlalu Pendek", message: msg })
      window.scrollTo({ top: 0, behavior: "smooth" })
      return
    }

    post("/register", {
      preserveScroll: true,
      onError: (errs) => {
        const errorList = Object.values(errs).filter(Boolean)
        const firstMsg = errorList[0] || "Terdapat data yang belum sesuai. Mohon periksa kembali kolom formulir."
        setClientError(firstMsg)
        setToast({
          type: "error",
          title: "Validasi Gagal",
          message: firstMsg
        })
        window.scrollTo({ top: 0, behavior: "smooth" })
      }
    })
  }

  const selected = packages.find((p: Package) => String(p.id) === data.package_id)
  const availableDurations = selected?.duration_options
    ? selected.duration_options.split(",").map((s) => s.trim()).filter(Boolean)
    : ["1", "6", "12"]

  const durationLabels: Record<string, string> = {
    "1": "1 Bulan",
    "3": "3 Bulan",
    "6": "6 Bulan",
    "12": "1 Tahun",
  }

  const durNum = parseInt(data.duration, 10) || 1
  const rawPrice = selected
    ? data.duration === "12" 
      ? (selected.annual_price || selected.monthly_price * 12) 
      : data.duration === "6" 
      ? (selected.semi_annual_price || selected.monthly_price * 6) 
      : (selected.monthly_price * durNum)
    : 0

  const hasReferral = Boolean(data.referral_code && data.referral_code.trim().length > 0)
  const discountAmount = hasReferral ? Math.round(rawPrice * 0.10) : 0
  const finalPrice = Math.max(0, rawPrice - discountAmount)

  return (
    <div className="min-h-screen bg-[#10141A] text-slate-100 flex items-center justify-center p-4 sm:p-6 lg:p-10 relative overflow-hidden select-none">
      <Head title="Pendaftaran ISP & Billing RT RW Net | NODERA" />
      {/* Toast Notification (Top-Center Centered on Desktop & Mobile) */}
      {toast && (
        <div className="fixed top-4 inset-x-0 z-[99999] flex justify-center px-4 pointer-events-none">
          <div
            role="alert"
            className={cn(
              "pointer-events-auto w-full max-w-md flex items-center gap-3 rounded-2xl border p-4 shadow-sm backdrop-blur-2xl animate-in fade-in slide-in-from-top-4 duration-300",
              toast.type === "success" && "border-emerald-500/40 bg-[#10241A]/95 text-emerald-100 shadow-emerald-950/40",
              toast.type === "error" && "border-rose-500/40 bg-[#261217]/95 text-rose-100 shadow-rose-950/40",
              toast.type === "info" && "border-[#00C2FF]/40 bg-[#0C1E2E]/95 text-cyan-100 shadow-cyan-950/40"
            )}
          >
            {toast.type === "success" && (
              <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-emerald-500/20 text-emerald-400 border border-emerald-500/30">
                <Check className="h-5 w-5" />
              </div>
            )}
            {toast.type === "error" && (
              <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-rose-500/20 text-rose-400 border border-rose-500/30">
                <AlertCircle className="h-5 w-5" />
              </div>
            )}
            {toast.type === "info" && (
              <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#00C2FF]/20 text-[#00C2FF] border border-[#00C2FF]/30">
                <Info className="h-4 w-4" />
              </div>
            )}
            <div className="min-w-0 flex-1">
              <p className="text-xs sm:text-sm font-bold text-white leading-tight">{toast.title}</p>
              {toast.message && (
                <p className="text-[11px] text-slate-300 mt-1 leading-relaxed">{toast.message}</p>
              )}
            </div>
            <button
              type="button"
              onClick={() => setToast(null)}
              className="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-white/10 hover:bg-white/20 text-slate-400 hover:text-white transition-colors cursor-pointer"
            >
              <X className="h-3.5 w-3.5" />
            </button>
          </div>
        </div>
      )}

      {/* Main Unified Box Container */}
      <div className="w-full max-w-5xl mx-auto rounded-3xl border border-[#212B3B] bg-[#121720] shadow-2xl overflow-hidden grid lg:grid-cols-12">
        
        {/* =========================================================================
            LEFT PANEL: SOLID BLUE BRANDING HERO
            ========================================================================= */}
        <div className="lg:col-span-5 relative bg-[#0073C6] p-6 sm:p-8 text-white flex flex-col justify-between border-b lg:border-b-0 lg:border-r border-[#212B3B]">
          <div className="space-y-6">
            <div className="flex items-center justify-between gap-2">
              <div className="flex items-center gap-3 select-none">
                <img
                  src="/images/logo-white.png?v=36"
                  alt={company.name}
                  className="h-9 w-9 object-contain shrink-0"
                />
                <div>
                  <div className="text-sm font-black tracking-tight text-white uppercase leading-none">{company.name}</div>
                  <div className="text-[10px] text-white/80 font-medium mt-0.5">Pendaftaran Instansi ISP</div>
                </div>
              </div>
              <LanguageSwitcher />
            </div>

            <div className="pt-4 sm:pt-6 space-y-2.5">
              <h1 className="text-2xl sm:text-3xl font-black tracking-tight text-white leading-tight">
                Mulai Digitalisasi ISP &amp; RT/RW Net Anda
              </h1>
              <p className="text-xs sm:text-sm text-white/90 leading-relaxed">
                Kelola billing otomatis, pantau router MikroTik, petakan jalur kabel ODP GIS, dan aplikasi kasir lapangan dalam satu ekosistem terpadu.
              </p>
            </div>

            {/* Feature Checklist */}
            <div className="space-y-3 pt-2">
              {[
                { icon: MapPin, title: "Peta Topologi ODP & Jalur Fiber GIS", desc: "Petakan ODP, joint closure, dan kabel optik" },
                { icon: Activity, title: "Monitoring Throughput Real-Time", desc: "Traffic interface, CPU router, dan status klien" },
                { icon: ShieldCheck, title: "Auto-Isolir PPPoE & Hotspot", desc: "Pemutusan otomatis tagihan jatuh tempo" },
                { icon: Printer, title: "Aplikasi Kasir & Kolektor Lapangan", desc: "Cetak struk Bluetooth thermal dan rekap kas" },
                { icon: MessageCircle, title: "WhatsApp Gateway Notifikasi", desc: "Pengiriman otomatis invoice dan bukti bayar" },
              ].map((item, i) => {
                const Icon = item.icon
                return (
                  <div key={i} className="flex items-start gap-3 text-white">
                    <div className="flex h-7 w-7 items-center justify-center rounded-lg bg-white/15 border border-white/20 text-white shrink-0 mt-0.5">
                      <Icon className="h-4 w-4" />
                    </div>
                    <div className="leading-tight">
                      <div className="text-xs font-bold text-white">{item.title}</div>
                      <div className="text-[11px] text-white/80 mt-0.5">{item.desc}</div>
                    </div>
                  </div>
                )
              })}
            </div>
          </div>

          <div className="pt-6 mt-6 border-t border-white/20 text-[11px] text-white/80 flex items-center justify-between">
            <span>Nodera Billing System</span>
            <span className="font-mono">ISP Edition</span>
          </div>
        </div>

        {/* =========================================================================
            RIGHT PANEL: AUTH / REGISTER FORM
            ========================================================================= */}
        <div className="lg:col-span-7 p-6 sm:p-10 flex flex-col justify-center bg-[#121720]">
          <div className="pb-4 border-b border-[#212B3B]">
            <h2 className="text-xl font-bold text-white tracking-tight">Formulir Pendaftaran</h2>
            <p className="text-xs text-slate-400 mt-1">Lengkapi data usaha untuk aktivasi subdomain Anda</p>
          </div>

          <div className="mt-5">
            {googleData ? (
              <div className="rounded-2xl border border-[#0073C6]/40 bg-[#0073C6]/15 p-4 text-xs text-sky-200 shadow-sm flex items-start gap-3.5 mb-2 animate-in fade-in slide-in-from-top-2">
                <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#0073C6]/30 text-white border border-white/20 mt-0.5">
                  <ShieldCheck className="h-5 w-5" />
                </div>
                <div className="space-y-1">
                  <div className="flex items-center gap-2">
                    <p className="font-bold text-white text-sm">Akun Google Belum Terdaftar</p>
                    <span className="rounded-full bg-emerald-500/20 px-2 py-0.5 text-[10px] font-bold text-emerald-300 border border-emerald-500/30 flex items-center gap-1">
                      <ShieldCheck className="h-3 w-3" /> Terhubung
                    </span>
                  </div>
                  <p className="text-slate-300 text-xs leading-relaxed">
                    Akun Google <strong className="text-white">{googleData.email}</strong> belum terdaftar sebagai instansi ISP. Kami telah mengisikan nama (<strong className="text-white">{googleData.name || 'Pengguna Google'}</strong>) &amp; email Anda secara otomatis ke formulir. Silakan lengkapi data profil usaha di bawah untuk menyelesaikan pendaftaran.
                  </p>
                </div>
              </div>
            ) : (
              <>
                <a
                  href={`/auth/google?context=tenant&intent=register${data.referral_code ? `&ref=${encodeURIComponent(data.referral_code)}` : ''}`}
                  className="w-full h-11 sm:h-12 rounded-xl bg-[#161C26] hover:bg-[#1A2230] text-white font-bold text-xs sm:text-sm flex items-center justify-center gap-3 transition-all duration-200 cursor-pointer border border-[#212B3B] active:scale-[0.99]"
                >
                  <svg className="h-5 w-5 shrink-0" viewBox="0 0 24 24">
                    <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                    <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                    <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/>
                    <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/>
                  </svg>
                  <span>Daftar / Isi Cepat dengan Google</span>
                </a>

                <div className="relative my-4 flex items-center justify-center">
                  <div className="absolute inset-0 flex items-center">
                    <div className="w-full border-t border-[#212B3B]"></div>
                  </div>
                  <div className="relative bg-[#121720] px-3 text-[11px] font-bold uppercase tracking-wider text-slate-500">
                    Atau lengkapi formulir pendaftaran
                  </div>
                </div>
              </>
            )}
          </div>

          <form onSubmit={submit} className="mt-3 space-y-5">
            {/* Top Error Alert Banner */}
            {(clientError || Object.keys(errors).length > 0) && (
              <div className="rounded-xl border border-rose-500/40 bg-rose-500/10 p-3.5 text-rose-300 flex items-start gap-2.5 text-xs">
                <AlertCircle className="h-4 w-4 text-rose-400 shrink-0 mt-0.5" />
                <div className="space-y-0.5">
                  <p className="font-bold text-rose-200">Pendaftaran Belum Lengkap</p>
                  <p className="text-rose-300/90 text-[11px]">
                    {clientError || Object.values(errors)[0] || "Mohon periksa kembali kolom formulir yang belum sesuai."}
                  </p>
                </div>
              </div>
            )}

            {/* Section 1: Profil Usaha & Kontak */}
            <div className="space-y-3">
              <div className="flex items-center gap-2 border-b border-[#212B3B] pb-1.5">
                <Building2 className="h-3.5 w-3.5 text-sky-400" />
                <span className="text-xs font-bold uppercase tracking-wider text-slate-300">1. Profil Usaha &amp; Kontak</span>
              </div>

              <div className="grid gap-3 sm:grid-cols-2">
                <div className="space-y-1">
                  <Label className="text-xs font-medium text-slate-300">Nama Penanggung Jawab <span className="text-rose-400">*</span></Label>
                  <div className="relative">
                    <User className="pointer-events-none absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-slate-500" />
                    <Input
                      value={data.name}
                      onChange={(e) => setData("name", e.target.value)}
                      placeholder="Nama Lengkap"
                      className="h-10 rounded-xl bg-[#0B0F17] border-[#212B3B] pl-9 text-white placeholder:text-slate-500 text-xs focus:border-[#0073C6] focus:ring-2 focus:ring-[#0073C6]/20"
                    />
                  </div>
                  {errors.name && <p className="text-[11px] text-rose-400">{errors.name}</p>}
                </div>

                <div className="space-y-1">
                  <Label className="text-xs font-medium text-slate-300">Nama Brand / ISP <span className="text-rose-400">*</span></Label>
                  <div className="relative">
                    <Building2 className="pointer-events-none absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-slate-500" />
                    <Input
                      value={data.company}
                      onChange={(e) => setData("company", e.target.value)}
                      placeholder="PT / CV / Brand Net"
                      className="h-10 rounded-xl bg-[#0B0F17] border-[#212B3B] pl-9 text-white placeholder:text-slate-500 text-xs focus:border-[#0073C6] focus:ring-2 focus:ring-[#0073C6]/20"
                    />
                  </div>
                  {errors.company && <p className="text-[11px] text-rose-400">{errors.company}</p>}
                </div>
              </div>

              <div className="grid gap-3 sm:grid-cols-2">
                <div className="space-y-1">
                  <Label className="text-xs font-medium text-slate-300">Email Administrator <span className="text-rose-400">*</span></Label>
                  <div className="relative">
                    <Mail className="pointer-events-none absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-slate-500" />
                    <Input
                      type="email"
                      value={data.email}
                      onChange={(e) => setData("email", e.target.value)}
                      placeholder="admin@domain.com"
                      className="h-10 rounded-xl bg-[#0B0F17] border-[#212B3B] pl-9 text-white placeholder:text-slate-500 text-xs focus:border-[#0073C6] focus:ring-2 focus:ring-[#0073C6]/20"
                    />
                  </div>
                  {errors.email && <p className="text-[11px] text-rose-400">{errors.email}</p>}
                </div>

                <div className="space-y-1">
                  <Label className="text-xs font-medium text-slate-300">No. WhatsApp / HP <span className="text-rose-400">*</span></Label>
                  <div className="relative">
                    <Phone className="pointer-events-none absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-slate-500" />
                    <Input
                      value={data.phone}
                      onChange={(e) => setData("phone", e.target.value)}
                      placeholder="08123456789"
                      className="h-10 rounded-xl bg-[#0B0F17] border-[#212B3B] pl-9 text-white placeholder:text-slate-500 text-xs focus:border-[#0073C6] focus:ring-2 focus:ring-[#0073C6]/20"
                    />
                  </div>
                  {errors.phone && <p className="text-[11px] text-rose-400">{errors.phone}</p>}
                </div>
              </div>
            </div>

            {/* Section 2: Subdomain & Akses Panel */}
            <div className="space-y-3 pt-1">
              <div className="flex items-center gap-2 border-b border-[#212B3B] pb-1.5">
                <Globe className="h-3.5 w-3.5 text-sky-400" />
                <span className="text-xs font-bold uppercase tracking-wider text-slate-300">2. Akses Panel &amp; Subdomain</span>
              </div>

              <div className="grid gap-3 sm:grid-cols-2">
                <div className="space-y-1">
                  <Label className="text-xs font-medium text-slate-300">Username Admin <span className="text-rose-400">*</span></Label>
                  <div className="relative">
                    <AtSign className="pointer-events-none absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-slate-500" />
                    <Input
                      value={data.username}
                      onChange={(e) => setData("username", e.target.value.toLowerCase().replace(/[^a-z0-9_-]/g, ""))}
                      placeholder="adminpanel"
                      className="h-10 rounded-xl bg-[#0B0F17] border-[#212B3B] pl-9 text-white placeholder:text-slate-500 text-xs font-mono focus:border-[#0073C6] focus:ring-2 focus:ring-[#0073C6]/20"
                    />
                  </div>
                  {errors.username && <p className="text-[11px] text-rose-400">{errors.username}</p>}
                </div>

                <div className="space-y-1">
                  <div className="flex items-center justify-between">
                    <Label className="text-xs font-medium text-slate-300">Password Admin <span className="text-rose-400">*</span></Label>
                    <button
                      type="button"
                      onClick={() => setShowPwd(!showPwd)}
                      className="text-[10px] text-sky-400 hover:underline"
                      tabIndex={-1}
                    >
                      {showPwd ? "Hide" : "Show"}
                    </button>
                  </div>
                  <div className="relative">
                    <Lock className="pointer-events-none absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-slate-500" />
                    <Input
                      type={showPwd ? "text" : "password"}
                      value={data.password}
                      onChange={(e) => setData("password", e.target.value)}
                      placeholder="Min. 6 karakter"
                      className="h-10 rounded-xl bg-[#0B0F17] border-[#212B3B] pl-9 text-white placeholder:text-slate-500 text-xs focus:border-[#0073C6] focus:ring-2 focus:ring-[#0073C6]/20"
                    />
                  </div>
                  {errors.password && <p className="text-[11px] text-rose-400">{errors.password}</p>}
                </div>
              </div>

              <div className="space-y-1">
                <Label className="text-xs font-medium text-slate-300">Subdomain Akses Panel (1 Kata) <span className="text-rose-400">*</span></Label>
                <div className="flex rounded-xl overflow-hidden border border-[#212B3B] focus-within:border-[#0073C6] focus-within:ring-2 focus-within:ring-[#0073C6]/20">
                  <div className="relative flex-1">
                    <Globe className="pointer-events-none absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-slate-500" />
                    <Input
                      value={data.slug}
                      onChange={(e) => setData("slug", e.target.value.toLowerCase().replace(/[^a-z0-9]/g, ""))}
                      placeholder="namabrand"
                      className="h-10 border-0 bg-[#0B0F17] rounded-none pl-9 font-mono text-xs text-white placeholder:text-slate-500 focus:ring-0"
                    />
                  </div>
                  <span className="inline-flex items-center bg-[#161C26] px-3 font-mono text-xs text-sky-400 select-none border-l border-[#212B3B]">
                    .{hostDomain}
                  </span>
                </div>
                {errors.slug ? (
                  <p className="text-[11px] text-rose-400">{errors.slug}</p>
                ) : (
                  <p className="text-[10px] text-slate-400">
                    Akses URL: <span className="font-mono text-sky-400">https://{data.slug || "brand"}.{hostDomain}</span>
                  </p>
                )}
              </div>
            </div>

            {/* Section 3: Paket & Durasi */}
            <div className="space-y-3 pt-1">
              <div className="flex items-center gap-2 border-b border-[#212B3B] pb-1.5">
                <ShieldCheck className="h-3.5 w-3.5 text-sky-400" />
                <span className="text-xs font-bold uppercase tracking-wider text-slate-300">3. Paket Langganan &amp; Durasi</span>
              </div>

              <div className="grid gap-3 sm:grid-cols-2">
                <div className="space-y-1">
                  <Label className="text-xs font-medium text-slate-300">Pilih Paket Layanan <span className="text-rose-400">*</span></Label>
                  <select
                    value={data.package_id}
                    onChange={(e) => {
                      const newPkgId = e.target.value
                      const pkg = packages.find((item) => String(item.id) === newPkgId)
                      const pkgDurations = pkg?.duration_options ? pkg.duration_options.split(",").map((s) => s.trim()).filter(Boolean) : ["1", "6", "12"]
                      const nextDuration = pkgDurations.includes(data.duration) ? data.duration : (pkgDurations[0] ?? "1")
                      setData((prev) => ({ ...prev, package_id: newPkgId, duration: nextDuration }))
                    }}
                    className="w-full h-10 rounded-xl border border-[#212B3B] bg-[#0B0F17] px-3 text-xs font-semibold text-white focus:border-[#0073C6] focus:outline-none focus:ring-2 focus:ring-[#0073C6]/20"
                  >
                    {packages.map((p: Package) => (
                      <option key={p.id} value={p.id} className="bg-[#0B0F17] text-white">
                        {p.name} — {formatIDR(p.monthly_price)}/bln ({p.max_customers ? `${p.max_customers} User` : "Unlimited"})
                      </option>
                    ))}
                  </select>
                </div>

                <div className="space-y-1">
                  <Label className="text-xs font-medium text-slate-300">Masa Aktif Langganan <span className="text-rose-400">*</span></Label>
                  <select
                    value={data.duration}
                    onChange={(e) => setData("duration", e.target.value)}
                    className="w-full h-10 rounded-xl border border-[#212B3B] bg-[#0B0F17] px-3 text-xs font-semibold text-white focus:border-[#0073C6] focus:outline-none focus:ring-2 focus:ring-[#0073C6]/20"
                  >
                    {availableDurations.map((dur) => (
                      <option key={dur} value={dur} className="bg-[#0B0F17] text-white">
                        {durationLabels[dur] || `${dur} Bulan`}
                      </option>
                    ))}
                  </select>
                </div>
              </div>

              {/* Optional Referral Code Section */}
              <div className="rounded-2xl border border-[#212B3B] bg-[#0B0F17] p-3.5 space-y-2">
                <div className="flex items-center justify-between">
                  <div className="flex items-center gap-2">
                    <Tag className="h-3.5 w-3.5 text-sky-400" />
                    <span className="text-xs font-bold text-slate-300">Kode Referral Mitra (Opsional)</span>
                  </div>
                  {!showRefInput && !data.referral_code && (
                    <button
                      type="button"
                      onClick={() => setShowRefInput(true)}
                      className="text-[11px] font-semibold text-sky-400 hover:underline cursor-pointer"
                    >
                      + Masukkan Kode (Diskon 10%)
                    </button>
                  )}
                </div>

                {(showRefInput || !!data.referral_code) && (
                  <div className="space-y-1.5 pt-1">
                    <div className="relative">
                      <Input
                        value={data.referral_code}
                        onChange={(e) => setData("referral_code", e.target.value.toUpperCase().replace(/[^A-Z0-9_-]/g, ""))}
                        placeholder="Contoh: MITRA10 / REF-XXXX"
                        className="h-9 rounded-lg bg-[#161C26] border-[#212B3B] text-xs font-mono font-bold text-sky-400 placeholder:text-slate-500 uppercase focus:border-[#0073C6]"
                      />
                    </div>
                    <div className="flex items-center justify-between text-[10px]">
                      <span className="text-emerald-400 font-medium">
                        {data.referral_code.trim() ? "Diskon 10% otomatis diterapkan pada total tagihan aktivasi." : "Masukkan kode referral mitra untuk mendapatkan potongan 10%."}
                      </span>
                      {data.referral_code && (
                        <button
                          type="button"
                          onClick={() => {
                            setData("referral_code", "")
                            setShowRefInput(false)
                          }}
                          className="text-slate-400 hover:text-rose-400 text-[10px]"
                        >
                          Hapus
                        </button>
                      )}
                    </div>
                  </div>
                )}
              </div>
            </div>

            {/* Info Panel & Saldo */}
            <div className="space-y-3 pt-1">
              <div className="rounded-2xl border border-[#212B3B] bg-[#0B0F17] p-4 space-y-2.5">
                <div className="flex items-center justify-between">
                  <div className="inline-flex items-center gap-1.5 text-xs font-bold text-sky-400">
                    <ShieldCheck className="h-4 w-4" />
                    <span>Panel Khusus &amp; Manajemen Saldo</span>
                  </div>
                  <Badge className="bg-emerald-500/20 text-emerald-300 border-emerald-500/30 text-[10px] px-2 py-0.5 font-bold">
                    Aktivasi Otomatis
                  </Badge>
                </div>
                <p className="text-xs text-slate-300 leading-relaxed">
                  Setelah pendaftaran disetujui, setiap admin memiliki <strong className="text-white">Panel Dashboard Mandiri</strong> yang dilengkapi fitur pengisian saldo deposit untuk perpanjangan langganan, aktivasi add-ons, dan layanan cloud.
                </p>
                <div className="flex items-center gap-1.5 text-[11px] text-slate-400 pt-0.5 border-t border-[#212B3B]">
                  <QrCode className="h-3.5 w-3.5 text-sky-400 shrink-0" />
                  <span>Tagihan aktivasi awal langsung tampil otomatis (QRIS / Virtual Account) saat pendaftaran disubmit.</span>
                </div>
              </div>

              {/* Total Summary */}
              <div className="rounded-2xl border border-[#0073C6]/40 bg-[#0073C6]/15 p-4 flex items-center justify-between">
                <div>
                  <span className="text-[11px] text-slate-300">Total Tagihan Aktivasi:</span>
                  {discountAmount > 0 ? (
                    <div className="space-y-0.5 mt-0.5">
                      <div className="flex items-center gap-2">
                        <span className="text-xs text-slate-400 line-through">{formatIDR(rawPrice)}</span>
                        <Badge className="bg-emerald-500/20 text-emerald-300 border-emerald-500/30 text-[10px] px-1.5 py-0">
                          Diskon 10% (-{formatIDR(discountAmount)})
                        </Badge>
                      </div>
                      <p className="font-display text-xl font-black text-sky-400">{formatIDR(finalPrice)}</p>
                    </div>
                  ) : (
                    <p className="font-display text-xl font-black text-sky-400">{formatIDR(rawPrice)}</p>
                  )}
                </div>
                <div className="inline-flex items-center gap-1 rounded-full border border-emerald-500/40 bg-emerald-500/10 px-3 py-1 text-xs font-bold text-emerald-400">
                  <ShieldCheck className="h-3.5 w-3.5" />
                  <span>Aktivasi Instan</span>
                </div>
              </div>
            </div>

            {/* Terms & Privacy Checkbox */}
            <label className="flex items-start gap-2.5 cursor-pointer rounded-xl border border-[#212B3B] bg-[#0B0F17] p-3 hover:border-[#0073C6]/50 transition-all select-none">
              <input
                type="checkbox"
                checked={agreeTerms}
                onChange={(e) => setAgreeTerms(e.target.checked)}
                className="mt-0.5 h-4 w-4 rounded border-[#212B3B] bg-[#121720] text-[#0073C6] focus:ring-[#0073C6] focus:ring-offset-0 cursor-pointer accent-[#0073C6]"
              />
              <span className="text-xs text-slate-300 leading-snug">
                Saya telah membaca dan menyetujui{" "}
                <Link
                  href="/terms"
                  target="_blank"
                  onClick={(e) => e.stopPropagation()}
                  className="font-bold text-sky-400 hover:underline"
                >
                  Syarat &amp; Ketentuan
                </Link>{" "}
                serta{" "}
                <Link
                  href="/privacy"
                  target="_blank"
                  onClick={(e) => e.stopPropagation()}
                  className="font-bold text-sky-400 hover:underline"
                >
                  Kebijakan Privasi
                </Link>{" "}
                Nodera.
              </span>
            </label>

            {/* Submit Button */}
            <Button
              type="submit"
              disabled={processing || !agreeTerms}
              className="w-full h-11 sm:h-12 rounded-xl bg-[#0073C6] hover:bg-[#0060A8] text-white font-bold text-sm shadow-sm shadow-[#0073C6]/25 active:scale-[0.99] transition-all cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed"
              size="lg"
            >
              {processing ? "Memproses Pendaftaran..." : "Daftar Sekarang"}
              {!processing && <ArrowRight className="h-4 w-4 ml-1.5" />}
            </Button>

            {/* Bottom Form Footer Link */}
            <div className="pt-1 text-center text-xs text-slate-400">
              Sudah punya akun?{" "}
              <Link href="/login" className="font-bold text-sky-400 hover:underline ml-1">
                Masuk di sini
              </Link>
            </div>
          </form>
        </div>
      </div>
    </div>
  )
}
