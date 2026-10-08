import { useForm, usePage } from "@inertiajs/react"
import {
  ShieldCheck,
  KeyRound,
  Smartphone,
  Eye,
  EyeOff,
  AlertTriangle,
  Info,
  ArrowRight,
} from "lucide-react"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { LanguageSwitcher } from "@/components/layout/language-switcher"
import { PwaInstallBanner } from "@/components/ui/pwa-install-banner"
import { PageProps } from "@/types"
import { useState, useRef, useEffect } from "react"
import { cn } from "@/lib/utils"

export default function PortalLoginPage({ tenantName, flash: initialFlash }: PageProps<{
  tenantName: string
  flash?: { msg?: string | null; error?: string | null; info?: string | null; warning?: string | null }
}>) {
  const page = usePage<PageProps>()
  const flash = (page.props.flash || initialFlash) as { msg?: string | null; error?: string | null; info?: string | null; warning?: string | null } | undefined
  const [showPin, setShowPin] = useState(false)
  const [pinDigits, setPinDigits] = useState<string[]>(["", "", "", "", "", ""])
  const pinRefs = useRef<(HTMLInputElement | null)[]>([])

  const { data, setData, post, processing, errors } = useForm<{ phone: string; pin: string; remember: boolean }>({
    phone: "",
    pin: "",
    remember: true,
  })

  // Synchronize pinDigits if data.pin is cleared
  useEffect(() => {
    if (!data.pin) {
      setPinDigits(["", "", "", "", "", ""])
    }
  }, [data.pin])

  const updatePinValue = (newDigits: string[]) => {
    setPinDigits(newDigits)
    const combined = newDigits.join("")
    setData("pin", combined)
  }

  const handleDigitChange = (index: number, val: string) => {
    const clean = val.replace(/\D/g, "")
    if (!clean) {
      const next = [...pinDigits]
      next[index] = ""
      updatePinValue(next)
      return
    }

    // If multi-digit (paste or mobile autofill)
    if (clean.length > 1) {
      const chars = clean.slice(0, 6).split("")
      const next = [...pinDigits]
      chars.forEach((c, idx) => {
        if (index + idx < 6) {
          next[index + idx] = c
        }
      })
      updatePinValue(next)
      const nextFocus = Math.min(index + chars.length, 5)
      pinRefs.current[nextFocus]?.focus()
      return
    }

    // Single digit
    const next = [...pinDigits]
    next[index] = clean
    updatePinValue(next)
    if (index < 5) {
      pinRefs.current[index + 1]?.focus()
    }
  }

  const handleKeyDown = (index: number, e: React.KeyboardEvent<HTMLInputElement>) => {
    if (e.key === "Backspace") {
      if (pinDigits[index]) {
        const next = [...pinDigits]
        next[index] = ""
        updatePinValue(next)
      } else if (index > 0) {
        const next = [...pinDigits]
        next[index - 1] = ""
        updatePinValue(next)
        pinRefs.current[index - 1]?.focus()
      }
    } else if (e.key === "ArrowLeft" && index > 0) {
      pinRefs.current[index - 1]?.focus()
    } else if (e.key === "ArrowRight" && index < 5) {
      pinRefs.current[index + 1]?.focus()
    }
  }

  const handlePaste = (e: React.ClipboardEvent<HTMLInputElement>) => {
    e.preventDefault()
    const pasted = e.clipboardData.getData("text").replace(/\D/g, "").slice(0, 6)
    if (!pasted) return
    const next = ["", "", "", "", "", ""]
    pasted.split("").forEach((c, idx) => {
      if (idx < 6) next[idx] = c
    })
    updatePinValue(next)
    const focusIndex = Math.min(pasted.length, 5)
    pinRefs.current[focusIndex]?.focus()
  }

  const handleClearPin = () => {
    updatePinValue(["", "", "", "", "", ""])
    pinRefs.current[0]?.focus()
  }

  const submit = (e: React.FormEvent) => {
    e.preventDefault()
    post("/portal/login", { preserveScroll: true })
  }

  return (
    <div className="min-h-screen bg-[#10141A] text-slate-100 flex items-center justify-center p-4 sm:p-6 lg:p-10 select-none">
      {/* Main Unified Box Container */}
      <div className="w-full max-w-4xl mx-auto rounded-3xl border border-[#212B3B] bg-[#121720] shadow-2xl overflow-hidden grid lg:grid-cols-12">
        
        {/* =========================================================================
            LEFT PANEL: BRAND CANVAS
            ========================================================================= */}
        <div className="lg:col-span-5 relative bg-[#0073C6] p-6 sm:p-8 text-white flex flex-col justify-between border-b lg:border-b-0 lg:border-r border-[#212B3B]">
          <div className="space-y-6">
            <div className="flex items-center justify-between gap-2">
              <div className="flex items-center gap-3">
                <img
                  src={(page.props.tenantLogo as string) || "/images/logo-white.png?v=36"}
                  alt={tenantName}
                  className="h-8 w-8 object-contain shrink-0"
                />
                <div>
                  <div className="text-sm font-bold tracking-tight text-white uppercase">{tenantName}</div>
                  <div className="text-xs text-sky-100">Portal Pelanggan</div>
                </div>
              </div>
              <LanguageSwitcher />
            </div>

            <div className="pt-4 sm:pt-6 space-y-2">
              <h1 className="text-2xl sm:text-3xl font-bold tracking-tight text-white leading-tight">
                Layanan Mandiri Pelanggan
              </h1>
              <p className="text-xs sm:text-sm text-sky-100/90 leading-relaxed">
                Cek status tagihan bulanan, pantau koneksi internet Anda, dan lakukan pembayaran instan online.
              </p>
            </div>
          </div>

          <div className="pt-6 mt-6 border-t border-white/20 text-xs text-sky-100 flex items-center justify-between">
            <span>Customer Self-Service</span>
            <span className="text-white font-mono font-bold">Online 24/7</span>
          </div>
        </div>

        {/* =========================================================================
            RIGHT PANEL: AUTH FORM
            ========================================================================= */}
        <div className="lg:col-span-7 p-6 sm:p-8 sm:py-10 flex flex-col justify-center bg-[#121720]">
          <div className="pb-4 border-b border-[#212B3B]">
            <h2 className="text-base sm:text-lg font-bold text-white tracking-tight">Masuk ke Akun</h2>
            <p className="text-xs text-slate-400 mt-0.5">Gunakan ID Pelanggan / No. HP &amp; 6 Digit PIN</p>
          </div>

          <div className="mt-3">
            <PwaInstallBanner />
          </div>

          {/* Flash Notification Alerts */}
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

          {/* Login Form */}
          <form onSubmit={submit} className="mt-5 space-y-4 sm:space-y-5">
            {/* Field 1: Phone / Customer ID */}
            <div className="space-y-1.5">
              <Label htmlFor="phone" className="text-xs font-semibold text-slate-300 flex items-center gap-1.5">
                <Smartphone className="h-3.5 w-3.5 text-[#0073C6]" />
                ID Pelanggan / No. HP / WhatsApp
              </Label>
              <div className="relative">
                <Input
                  id="phone"
                  value={data.phone}
                  onChange={(e) => setData("phone", e.target.value)}
                  placeholder="cth: ID Pelanggan (1504), Username, atau No. HP"
                  className="h-11 sm:h-12 rounded-xl bg-[#161C26] border-[#212B3B] text-white placeholder:text-slate-500 text-sm focus:border-[#0073C6] focus:ring-1 focus:ring-[#0073C6]/30 transition-all"
                  autoComplete="username"
                  autoFocus
                />
              </div>
              {errors.phone && <p className="text-xs text-rose-400 font-medium">{errors.phone}</p>}
            </div>

            {/* Field 2: 6-Digit OTP Segmented Box PIN Input */}
            <div className="space-y-2">
              <div className="flex items-center justify-between">
                <Label htmlFor="pin-0" className="text-xs font-semibold text-slate-300 flex items-center gap-1.5">
                  <KeyRound className="h-3.5 w-3.5 text-[#0073C6]" />
                  PIN Login (6 Digit)
                </Label>
                <div className="flex items-center gap-2">
                  {data.pin.length > 0 && (
                    <button
                      type="button"
                      onClick={handleClearPin}
                      className="text-[11px] font-medium text-slate-400 hover:text-rose-400 transition-colors cursor-pointer"
                      tabIndex={-1}
                    >
                      Hapus
                    </button>
                  )}
                  <button
                    type="button"
                    onClick={() => setShowPin(!showPin)}
                    className="flex items-center gap-1 text-[11px] font-semibold text-sky-400 hover:text-sky-300 transition-colors cursor-pointer"
                    tabIndex={-1}
                    aria-label="Lihat PIN"
                  >
                    {showPin ? <EyeOff className="h-3 w-3" /> : <Eye className="h-3 w-3" />}
                    <span>{showPin ? "Sembunyikan" : "Tampilkan"}</span>
                  </button>
                </div>
              </div>

              {/* 6 Square Box Slots */}
              <div className="grid grid-cols-6 gap-2 sm:gap-2.5">
                {Array.from({ length: 6 }).map((_, idx) => {
                  const isFilled = Boolean(pinDigits[idx])
                  return (
                    <input
                      key={idx}
                      id={`pin-${idx}`}
                      ref={(el) => {
                        pinRefs.current[idx] = el
                      }}
                      type={showPin ? "text" : "password"}
                      inputMode="numeric"
                      pattern="[0-9]*"
                      maxLength={6}
                      value={pinDigits[idx]}
                      onChange={(e) => handleDigitChange(idx, e.target.value)}
                      onKeyDown={(e) => handleKeyDown(idx, e)}
                      onPaste={handlePaste}
                      onFocus={(e) => e.target.select()}
                      className={cn(
                        "flex h-11 sm:h-12 w-full items-center justify-center rounded-xl border text-center font-bold text-xl sm:text-2xl transition-all duration-150 outline-none select-none tabular-nums",
                        isFilled
                          ? "border-[#0073C6] bg-[#0073C6]/10 text-white ring-1 ring-[#0073C6]/40"
                          : "border-[#212B3B] bg-[#161C26] text-slate-300 hover:border-slate-700 focus:border-[#0073C6] focus:bg-[#161C26] focus:ring-1 focus:ring-[#0073C6]/30",
                        errors.pin && "border-rose-500/80 bg-rose-500/10 text-rose-400 focus:ring-rose-500/30"
                      )}
                      autoComplete="off"
                      aria-label={`Digit PIN ${idx + 1}`}
                    />
                  )
                })}
              </div>

              {errors.pin ? (
                <p className="text-xs text-rose-400 font-medium">{errors.pin}</p>
              ) : (
                <p className="text-[11px] text-slate-400">
                  Masukkan 6 digit PIN (PIN default: <span className="font-semibold text-sky-400">123456</span>)
                </p>
              )}
            </div>

            {/* Remember Device Checkbox */}
            <div className="flex items-center justify-between pt-0.5">
              <label className="flex items-center gap-2 text-xs font-medium text-slate-400 cursor-pointer select-none">
                <input
                  type="checkbox"
                  checked={data.remember ?? true}
                  onChange={(e) => setData("remember" as any, e.target.checked)}
                  className="h-4 w-4 rounded border-[#212B3B] bg-[#161C26] text-[#0073C6] accent-[#0073C6]"
                />
                Ingat Saya di Perangkat Ini
              </label>
            </div>

            {/* Submit Button */}
            <Button
              type="submit"
              className="w-full h-11 sm:h-12 rounded-xl bg-[#0073C6] hover:bg-[#0060A8] text-white font-bold text-sm shadow-sm active:scale-[0.99] transition-all cursor-pointer"
              size="lg"
              disabled={processing}
            >
              {processing ? "Memproses..." : "Masuk ke Portal"}
              {!processing && <ArrowRight className="h-4 w-4 ml-1.5" />}
            </Button>
          </form>

          {/* Security Footer Note */}
          <div className="mt-5 pt-3 border-t border-[#212B3B] flex items-center justify-center gap-1.5 text-[11px] text-slate-400 text-center">
            <ShieldCheck className="h-3.5 w-3.5 text-sky-400" />
            <span>Lupa PIN? Hubungi admin provider untuk reset</span>
          </div>
        </div>
      </div>
    </div>
  )
}
