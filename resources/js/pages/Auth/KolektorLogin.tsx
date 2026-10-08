import { useForm, usePage } from "@inertiajs/react"
import {
  ShieldCheck,
  User,
  Lock,
  Eye,
  EyeOff,
  AlertTriangle,
  Info,
  ArrowRight,
} from "lucide-react"
import { LanguageSwitcher } from "@/components/layout/language-switcher"
import { PwaInstallBanner } from "@/components/ui/pwa-install-banner"
import { PageProps } from "@/types"
import { useState } from "react"

export default function KolektorLogin({ tenantName, flash: initialFlash }: PageProps<{
  tenantName: string
  flash?: { error?: string | null; msg?: string | null; warning?: string | null; info?: string | null }
}>) {
  const page = usePage<PageProps>()
  const flash = (page.props.flash || initialFlash) as { error?: string | null; msg?: string | null; warning?: string | null; info?: string | null } | undefined
  const [showPwd, setShowPwd] = useState(false)
  const { data, setData, post, processing, errors } = useForm<{ username: string; password: string; remember: boolean }>({
    username: "",
    password: "",
    remember: true,
  })

  const submit = (e: React.FormEvent) => {
    e.preventDefault()
    post("/kolektor/login", { preserveScroll: true })
  }

  return (
    <div className="min-h-screen bg-[#10141A] text-slate-100 flex items-center justify-center p-4 sm:p-6 lg:p-10 select-none">
      <div className="w-full max-w-4xl mx-auto rounded-3xl border border-[#212B3B] bg-[#121720] shadow-2xl overflow-hidden grid lg:grid-cols-12">
        {/* LEFT PANEL */}
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
                  <div className="text-sm font-bold tracking-tight text-white uppercase">{tenantName}</div>
                  <div className="text-xs text-sky-100">Kolektor Tagihan</div>
                </div>
              </div>
              <LanguageSwitcher />
            </div>

            <div className="pt-4 sm:pt-6 space-y-2">
              <h1 className="text-2xl sm:text-3xl font-bold tracking-tight text-white leading-tight">
                Penagihan Lapangan
              </h1>
              <p className="text-xs sm:text-sm text-sky-100/90 leading-relaxed">
                Setor tagihan keliling, cetak bukti bayar langsung, dan pantau performa target setoran harian.
              </p>
            </div>
          </div>

          <div className="pt-6 mt-6 border-t border-white/20 text-xs text-sky-100 flex items-center justify-between">
            <span>Nodera Billing System</span>
            <span className="text-white font-mono font-bold">ISP Portal</span>
          </div>
        </div>

        {/* RIGHT PANEL */}
        <div className="lg:col-span-7 p-6 sm:p-8 sm:py-10 flex flex-col justify-center bg-[#121720]">
          <div className="pb-4 border-b border-[#212B3B]">
            <h2 className="text-base sm:text-lg font-bold text-white tracking-tight">Login Kolektor</h2>
            <p className="text-xs text-slate-400 mt-0.5">Gunakan akun akses resmi Anda</p>
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

          <form onSubmit={submit} className="mt-5 space-y-4 sm:space-y-5">
            <div className="space-y-1.5">
              <label htmlFor="username" className="text-xs font-semibold text-slate-300 flex items-center gap-1.5">
                <User className="h-3.5 w-3.5 text-[#0073C6]" />
                Username / ID Petugas
              </label>
              <input
                id="username"
                value={data.username}
                onChange={(e) => setData("username", e.target.value)}
                placeholder="ID Petugas..."
                className="w-full h-11 sm:h-12 rounded-xl bg-[#161C26] border border-[#212B3B] text-white placeholder:text-slate-500 text-sm px-3.5 focus:border-[#0073C6] outline-hidden transition-all"
                autoComplete="username"
                autoFocus
              />
              {errors.username && <p className="text-xs text-rose-500 font-medium">{errors.username}</p>}
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
              <span>{processing ? "Memverifikasi..." : "Masuk ke Sistem"}</span>
              {!processing && <ArrowRight className="h-4 w-4" />}
            </button>
          </form>
        </div>
      </div>
    </div>
  )
}
