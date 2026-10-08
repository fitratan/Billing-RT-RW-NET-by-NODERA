import React, { useState } from "react"
import { KeyRound, CheckCircle2, Copy, Check, MessageCircle, ShieldCheck } from "lucide-react"

export interface ResetPasswordData {
  name: string
  username?: string
  email?: string
  phone?: string
  password: string
  role?: string
}

export function ResetPasswordModal({
  data,
  onClose,
}: {
  data: ResetPasswordData | null
  onClose: () => void
}) {
  const [copied, setCopied] = useState(false)

  if (!data) return null

  const handleCopy = (text: string) => {
    navigator.clipboard.writeText(text)
    setCopied(true)
    setTimeout(() => setCopied(false), 2500)
  }

  const handleSendWa = () => {
    if (!data.phone) return
    const cleanPhone = data.phone.replace(/[^0-9]/g, "").replace(/^0/, "62")
    const loginUser = data.username || data.email || data.name
    const msg = `Halo ${data.name},\n\nPassword akun Anda (${loginUser}) telah berhasil direset oleh Administrator.\n\n*Password Baru:* ${data.password}\n\nSilakan login dan segera ubah password Anda demi keamanan.`
    window.open(`https://wa.me/${cleanPhone}?text=${encodeURIComponent(msg)}`, "_blank")
  }

  const identifier = data.username ? `@${data.username}` : data.email || data.name

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
      <div className="fixed inset-0 bg-black/80 backdrop-blur-md" onClick={onClose} />
      <div className="relative w-full max-w-sm overflow-hidden rounded-3xl border border-[#1E334D] bg-gradient-to-br from-[#0D1D30] via-[#091523] to-[#060D17] p-6 shadow-2xl animate-in zoom-in-95 duration-200 text-center space-y-5 text-white z-10">
        {/* Header Icon */}
        <div className="relative mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-[#0B2138] border border-[#0073C6]/40 text-[#00C2FF] shadow-[#0073C6]/20">
          <KeyRound className="h-8 w-8 text-[#00C2FF]" />
          <CheckCircle2 className="absolute -bottom-1 -right-1 h-5 w-5 text-emerald-400 bg-[#0B0E14] rounded-full" />
        </div>

        {/* Title & Account Name */}
        <div className="space-y-1">
          <h3 className="font-display text-base sm:text-lg font-bold text-white">
            Password Berhasil Direset!
          </h3>
          <p className="text-xs text-slate-400">
            Kredensial login baru untuk{" "}
            <span className="font-semibold text-slate-200">{data.name}</span>{" "}
            <span className="text-[#00C2FF] font-mono font-medium">({identifier})</span>
          </p>
          {data.role && (
            <div className="pt-1">
              <span className="inline-flex items-center gap-1 rounded-full border border-slate-700 bg-slate-800/80 px-2.5 py-0.5 text-[10px] font-semibold text-slate-300">
                <ShieldCheck className="h-3 w-3 text-[#00C2FF]" />
                {data.role}
              </span>
            </div>
          )}
        </div>

        {/* Big Password Display Box with Copy Button */}
        <div className="rounded-2xl border border-[#212B3B] bg-[#0B0E14] p-4 space-y-2">
          <span className="text-[10px] uppercase font-bold text-slate-400 tracking-wider">
            Password Baru
          </span>
          <div className="flex items-center justify-center gap-3">
            <span className="font-mono text-2xl sm:text-3xl font-black text-[#00C2FF] tracking-[0.15em] break-all select-all">
              {data.password}
            </span>
            <button
              type="button"
              onClick={() => handleCopy(data.password)}
              className="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl border border-[#212B3B] bg-[#161B22] text-slate-300 hover:bg-[#1E2530] hover:text-white transition-all active:scale-95"
              title="Salin Password"
            >
              {copied ? <Check className="h-4 w-4 text-emerald-400" /> : <Copy className="h-4 w-4" />}
            </button>
          </div>
          {copied && (
            <span className="text-[10px] font-bold text-emerald-400 block animate-in fade-in">
              Password berhasil disalin ke clipboard!
            </span>
          )}
        </div>

        {/* Actions */}
        <div className="space-y-2 pt-1">
          {data.phone && (
            <button
              type="button"
              onClick={handleSendWa}
              className="w-full flex items-center justify-center gap-2 h-10 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-xs font-bold text-white transition-all active:scale-95"
            >
              <MessageCircle className="h-4 w-4" />
              <span>Kirim Password ke WhatsApp</span>
            </button>
          )}

          <button
            type="button"
            onClick={onClose}
            className="w-full flex items-center justify-center h-10 rounded-xl border border-[#212B3B] bg-[#161B22] text-xs font-bold text-slate-300 hover:bg-[#1E2530] hover:text-white transition-all"
          >
            Selesai / Tutup
          </button>
        </div>
      </div>
    </div>
  )
}
