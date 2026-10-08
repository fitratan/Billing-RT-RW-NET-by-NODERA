import { useForm, usePage } from "@inertiajs/react"
import {
  ShieldCheck,
  User,
  Lock,
  Eye,
  EyeOff,
  ArrowRight,
  AlertTriangle,
  Info,
  Key,
  Copy,
  Check,
  ExternalLink,
  MessageCircle,
  X,
} from "lucide-react"
import { LanguageSwitcher } from "@/components/layout/language-switcher"
import { PwaInstallBanner } from "@/components/ui/pwa-install-banner"
import { PageProps } from "@/types"
import { useState } from "react"
import { cn } from "@/lib/utils"

export default function LoginPage({
  tenantName,
  tenantSlug,
  technicianLogin,
  superadminLogin,
  licenseKey: initialLicenseKey,
  hwid: initialHwid,
  flash: initialFlash,
}: PageProps<{
  tenantName: string
  tenantSlug: string | null
  technicianLogin?: boolean
  superadminLogin?: boolean
  licenseKey?: string
  hwid?: string
  flash?: { msg?: string | null; error?: string | null; info?: string | null; warning?: string | null }
}>) {
  const page = usePage<PageProps>()
  const flash = (page.props.flash || initialFlash) as { msg?: string | null; error?: string | null; info?: string | null; warning?: string | null } | undefined
  const [showPwd, setShowPwd] = useState(false)
  const [activeLicenseKey, setActiveLicenseKey] = useState(initialLicenseKey || "")
  const [licenseModalOpen, setLicenseModalOpen] = useState(false)
  const [inputLicenseKey, setInputLicenseKey] = useState(initialLicenseKey || "")
  const [savingLicense, setSavingLicense] = useState(false)
  const [licenseMessage, setLicenseMessage] = useState<{ type: "success" | "error"; text: string } | null>(null)
  const [copiedHwid, setCopiedHwid] = useState(false)

  const deviceHwid = initialHwid || "NDR-HWID-STANDALONE"

  const { data, setData, post, processing, errors } = useForm<{
    email: string
    password: string
    remember: boolean
    tenant_slug: string
  }>({
    email: "",
    password: "",
    remember: true,
    tenant_slug: tenantSlug ?? "",
  })

  const submit = (e: React.FormEvent) => {
    e.preventDefault()
    post(superadminLogin ? "/nodera/superadmin/login" : "/login", { preserveScroll: true })
  }

  const handleCopyHwid = () => {
    navigator.clipboard.writeText(deviceHwid)
    setCopiedHwid(true)
    setTimeout(() => setCopiedHwid(false), 2000)
  }

  const handleSaveLicense = async () => {
    setSavingLicense(true)
    setLicenseMessage(null)
    try {
      const res = await fetch("/auth/desktop-license", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "X-Requested-With": "XMLHttpRequest",
          "X-CSRF-TOKEN": (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || "",
        },
        body: JSON.stringify({
          license_key: inputLicenseKey.trim(),
        }),
      })

      const result = await res.json()
      if (res.ok && result.success !== false) {
        setLicenseMessage({
          type: "success",
          text: result.message || "Kunci lisensi berhasil disimpan & diverifikasi!",
        })
        setActiveLicenseKey(inputLicenseKey.trim())
        setTimeout(() => {
          setLicenseModalOpen(false)
          window.location.reload()
        }, 1200)
      } else {
        setLicenseMessage({
          type: "error",
          text: result.message || "Gagal memverifikasi kunci lisensi.",
        })
      }
    } catch {
      setLicenseMessage({
        type: "error",
        text: "Terjadi kesalahan koneksi saat menyimpan lisensi.",
      })
    } finally {
      setSavingLicense(false)
    }
  }

  const titleRole = superadminLogin ? "Superadmin Platform" : technicianLogin ? "Portal Teknisi" : "Admin & Operator"

  return (
    <div className="min-h-screen bg-[#10141A] text-slate-100 flex items-center justify-center p-4 sm:p-6 lg:p-10 select-none">
      <div className="w-full max-w-4xl mx-auto rounded-3xl border border-[#212B3B] bg-[#121720] shadow-2xl overflow-hidden grid lg:grid-cols-12">
        {/* LEFT PANEL: BRAND CANVAS */}
        <div className="lg:col-span-5 relative bg-[#0073C6] p-6 sm:p-8 text-white flex flex-col justify-between border-b lg:border-b-0 lg:border-r border-[#212B3B]">
          <div className="space-y-6">
            <div className="flex items-center justify-between gap-2">
              <div className="flex items-center gap-3">
                <img
                  src="/images/logo-white.png?v=36"
                  alt="NODERA"
                  className="h-8 w-8 object-contain shrink-0"
                />
                <div>
                  <div className="text-sm font-bold tracking-tight text-white uppercase">{superadminLogin ? "NODERA" : tenantName}</div>
                  <div className="text-xs text-sky-100">{titleRole}</div>
                </div>
              </div>
              <LanguageSwitcher />
            </div>

            <div className="pt-4 sm:pt-6 space-y-2">
              <h1 className="text-2xl sm:text-3xl font-bold tracking-tight text-white leading-tight">
                {superadminLogin ? "Master Platform" : "Billing & Jaringan"}
              </h1>
              <p className="text-xs sm:text-sm text-sky-100/90 leading-relaxed">
                {superadminLogin
                  ? "Pusat kontrol infrastruktur cloud, manajemen tenant, dan lisensi platform global."
                  : "Kelola pelanggan, isolir otomatis MikroTik, monitoring jaringan, dan rekonsiliasi keuangan."}
              </p>
            </div>
          </div>

          <div className="pt-6 mt-6 border-t border-white/20 text-xs text-sky-100 flex items-center justify-between">
            <span>Nodera Billing System</span>
            <span className="text-white font-mono font-bold">ISP Edition</span>
          </div>
        </div>

        {/* RIGHT PANEL: AUTH FORM */}
        <div className="lg:col-span-7 p-6 sm:p-8 sm:py-10 flex flex-col justify-center bg-[#121720]">
          <div className="pb-4 border-b border-[#212B3B] flex items-center justify-between gap-3">
            <div>
              <h2 className="text-base sm:text-lg font-bold text-white tracking-tight">Masuk ke Sistem</h2>
              <p className="text-xs text-slate-400 mt-0.5">Gunakan email atau username akun Anda</p>
            </div>
            {!superadminLogin && !technicianLogin && (
              <button
                type="button"
                onClick={() => {
                  setInputLicenseKey(activeLicenseKey)
                  setLicenseMessage(null)
                  setLicenseModalOpen(true)
                }}
                className="shrink-0 inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-[#161C26] hover:bg-[#1E2634] border border-[#212B3B] text-slate-300 hover:text-white text-xs font-semibold transition-all cursor-pointer"
              >
                <Key className="h-3.5 w-3.5 text-[#0073C6]" />
                <span>{activeLicenseKey ? "Ganti Lisensi" : "Aktivasi Lisensi"}</span>
              </button>
            )}
          </div>

          <div className="mt-3">
            <PwaInstallBanner />
          </div>

          {flash?.error && (
            <div className="mt-4 flex items-center gap-2.5 rounded-xl border border-rose-500/40 bg-rose-500/10 px-4 py-3 text-xs font-medium text-rose-400">
              <AlertTriangle className="h-4 w-4 shrink-0 text-rose-400" />
              <span>{flash.error}</span>
            </div>
          )}
          {flash?.warning && (
            <div className="mt-4 flex items-center gap-2.5 rounded-xl border border-amber-500/40 bg-amber-500/10 px-4 py-3 text-xs font-medium text-amber-300">
              <AlertTriangle className="h-4 w-4 shrink-0 text-amber-400" />
              <span>{flash.warning}</span>
            </div>
          )}
          {flash?.msg && (
            <div className="mt-4 flex items-center gap-2.5 rounded-xl border border-emerald-500/40 bg-emerald-500/10 px-4 py-3 text-xs font-medium text-emerald-400">
              <ShieldCheck className="h-4 w-4 shrink-0 text-emerald-400" />
              <span>{flash.msg}</span>
            </div>
          )}
          {flash?.info && (
            <div className="mt-4 flex items-center gap-2.5 rounded-xl border border-[#0073C6]/40 bg-[#0073C6]/15 px-4 py-3 text-xs font-medium text-[#00C2FF]">
              <Info className="h-4 w-4 shrink-0 text-[#00C2FF]" />
              <span>{flash.info}</span>
            </div>
          )}

          <form onSubmit={submit} className="space-y-4 sm:space-y-5 mt-5">
            <div className="space-y-1.5">
              <label htmlFor="email" className="text-xs font-semibold text-slate-300 flex items-center gap-1.5">
                <User className="h-3.5 w-3.5 text-[#0073C6]" />
                Email / Username
              </label>
              <input
                id="email"
                value={data.email}
                onChange={(e) => setData("email", e.target.value)}
                placeholder="admin@isp.com / username"
                className="w-full h-11 sm:h-12 rounded-xl bg-[#161C26] border border-[#212B3B] text-white placeholder:text-slate-500 text-sm px-3.5 focus:border-[#0073C6] outline-hidden transition-all"
                autoComplete="username"
                autoFocus
              />
              {errors.email && <p className="text-xs text-rose-500 font-medium">{errors.email}</p>}
            </div>

            <div className="space-y-1.5">
              <div className="flex items-center justify-between">
                <label htmlFor="password" className="text-xs font-semibold text-slate-300 flex items-center gap-1.5">
                  <Lock className="h-3.5 w-3.5 text-[#0073C6]" />
                  Kata Sandi
                </label>
                <button
                  type="button"
                  onClick={() => setShowPwd(!showPwd)}
                  className="flex items-center gap-1 text-[11px] font-semibold text-[#0073C6] hover:text-sky-300 transition-colors cursor-pointer"
                  tabIndex={-1}
                >
                  {showPwd ? <EyeOff className="h-3 w-3" /> : <Eye className="h-3 w-3" />}
                  <span>{showPwd ? "Sembunyikan" : "Tampilkan"}</span>
                </button>
              </div>
              <input
                id="password"
                type={showPwd ? "text" : "password"}
                value={data.password}
                onChange={(e) => setData("password", e.target.value)}
                placeholder="••••••••"
                className="w-full h-11 sm:h-12 rounded-xl bg-[#161C26] border border-[#212B3B] text-white placeholder:text-slate-500 text-sm px-3.5 focus:border-[#0073C6] outline-hidden transition-all"
                autoComplete="current-password"
              />
              {errors.password && <p className="text-xs text-rose-500 font-medium">{errors.password}</p>}
            </div>

            <div className="flex items-center justify-between pt-0.5">
              <label className="flex items-center gap-2 text-xs font-medium text-slate-400 cursor-pointer select-none">
                <input
                  type="checkbox"
                  checked={data.remember}
                  onChange={(e) => setData("remember", e.target.checked)}
                  className="h-4 w-4 rounded border-[#212B3B] bg-[#161C26] text-[#0073C6] accent-[#0073C6]"
                />
                Ingat Saya
              </label>
            </div>

            <button
              type="submit"
              className="w-full h-11 sm:h-12 rounded-xl bg-[#0073C6] hover:bg-[#0060A8] text-white font-bold text-sm shadow-sm active:scale-[0.99] transition-all cursor-pointer flex items-center justify-center gap-2"
              disabled={processing}
            >
              <span>{processing ? "Memverifikasi..." : "Masuk ke Panel"}</span>
              {!processing && <ArrowRight className="h-4 w-4" />}
            </button>
          </form>
        </div>
      </div>

      {/* MODAL AKTIVASI / GANTI LISENSI DESKTOP */}
      {licenseModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-xs p-4">
          <div className="w-full max-w-lg rounded-2xl border border-[#212B3B] bg-[#121720] shadow-2xl overflow-hidden animate-in fade-in zoom-in duration-200">
            {/* Modal Header */}
            <div className="flex items-center justify-between border-b border-[#212B3B] px-5 py-4 bg-[#161C26]">
              <div className="flex items-center gap-2.5">
                <div className="h-8 w-8 rounded-lg bg-[#0073C6]/20 border border-[#0073C6]/40 flex items-center justify-center">
                  <Key className="h-4 w-4 text-[#00C2FF]" />
                </div>
                <div>
                  <h3 className="text-sm font-bold text-white">Aktivasi / Ganti Lisensi Billing</h3>
                  <p className="text-[11px] text-slate-400">NODERA Digital Network Standalone</p>
                </div>
              </div>
              <button
                type="button"
                onClick={() => setLicenseModalOpen(false)}
                className="rounded-lg p-1.5 text-slate-400 hover:bg-white/10 hover:text-white transition-colors cursor-pointer"
              >
                <X className="h-4 w-4" />
              </button>
            </div>

            {/* Modal Body */}
            <div className="p-5 space-y-4">
              {licenseMessage && (
                <div
                  className={cn(
                    "flex items-center gap-2.5 rounded-xl border px-4 py-3 text-xs font-medium",
                    licenseMessage.type === "success"
                      ? "border-emerald-500/40 bg-emerald-500/10 text-emerald-400"
                      : "border-rose-500/40 bg-rose-500/10 text-rose-400"
                  )}
                >
                  {licenseMessage.type === "success" ? (
                    <ShieldCheck className="h-4 w-4 shrink-0 text-emerald-400" />
                  ) : (
                    <AlertTriangle className="h-4 w-4 shrink-0 text-rose-400" />
                  )}
                  <span>{licenseMessage.text}</span>
                </div>
              )}

              {/* Hardware ID (HWID) */}
              <div className="space-y-1.5">
                <label className="text-xs font-semibold text-slate-300">Hardware ID (HWID Perangkat Ini):</label>
                <div className="flex items-center gap-2">
                  <input
                    type="text"
                    readOnly
                    value={deviceHwid}
                    className="w-full h-10 rounded-xl bg-[#161C26] border border-[#212B3B] text-sky-300 font-mono text-xs px-3 font-bold select-all outline-hidden"
                  />
                  <button
                    type="button"
                    onClick={handleCopyHwid}
                    className="h-10 px-3 rounded-xl bg-[#1e2634] hover:bg-[#283244] border border-[#212B3B] text-white text-xs font-bold flex items-center gap-1.5 shrink-0 transition-colors cursor-pointer"
                    title="Salin HWID"
                  >
                    {copiedHwid ? <Check className="h-3.5 w-3.5 text-emerald-400" /> : <Copy className="h-3.5 w-3.5 text-slate-300" />}
                    <span>{copiedHwid ? "Tersalin" : "Salin"}</span>
                  </button>
                </div>
              </div>

              {/* License Key Input */}
              <div className="space-y-1.5">
                <label className="text-xs font-semibold text-slate-300">Kode Kunci Lisensi (License Key):</label>
                <input
                  type="text"
                  value={inputLicenseKey}
                  onChange={(e) => setInputLicenseKey(e.target.value)}
                  placeholder="Contoh: NDR-STD-XXXX-XXXX-XXXX-XXXX"
                  className="w-full h-11 rounded-xl bg-[#161C26] border border-[#0073C6]/50 focus:border-[#0073C6] text-white font-mono text-xs px-3.5 font-bold outline-hidden transition-colors"
                />
              </div>

              {/* Info Box */}
              <div className="rounded-xl border border-[#212B3B] bg-[#161C26]/80 p-3.5 text-xs text-slate-300 space-y-2">
                <div className="flex items-start gap-2 text-slate-400 text-[11.5px] leading-relaxed">
                  <Info className="h-4 w-4 text-[#0073C6] shrink-0 mt-0.5" />
                  <span>
                    Masukkan kunci lisensi dari{" "}
                    <a
                      href="https://panel.dgtlnetsolution.com/desktop-licenses"
                      target="_blank"
                      rel="noreferrer"
                      className="font-bold text-[#00C2FF] hover:underline inline-flex items-center gap-0.5"
                    >
                      Portal Cloud NODERA <ExternalLink className="h-3 w-3" />
                    </a>{" "}
                    atau hubungi bantuan{" "}
                    <a
                      href={`https://wa.me/6285155173547?text=${encodeURIComponent(
                        `Halo Admin NODERA, saya butuh bantuan aktivasi Lisensi Billing Standalone HWID: ${deviceHwid}`
                      )}`}
                      target="_blank"
                      rel="noreferrer"
                      className="font-bold text-emerald-400 hover:underline inline-flex items-center gap-0.5"
                    >
                      <MessageCircle className="h-3 w-3" /> CS WhatsApp (085155173547)
                    </a>
                    . Kosongkan untuk kembali ke Free Tier (35 pelanggan).
                  </span>
                </div>
              </div>
            </div>

            {/* Modal Footer */}
            <div className="flex items-center justify-end gap-2.5 border-t border-[#212B3B] px-5 py-4 bg-[#161C26]">
              <button
                type="button"
                onClick={() => setLicenseModalOpen(false)}
                className="h-9 px-4 rounded-xl bg-[#1e2634] hover:bg-[#283244] text-slate-300 text-xs font-semibold transition-colors cursor-pointer"
              >
                Batal
              </button>
              <button
                type="button"
                onClick={handleSaveLicense}
                disabled={savingLicense}
                className="h-9 px-4 rounded-xl bg-[#0073C6] hover:bg-[#0060A8] text-white text-xs font-bold flex items-center gap-1.5 transition-all cursor-pointer disabled:opacity-50"
              >
                {savingLicense ? (
                  <span>Memverifikasi...</span>
                ) : (
                  <>
                    <Check className="h-3.5 w-3.5" />
                    <span>Simpan &amp; Verifikasi</span>
                  </>
                )}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}
