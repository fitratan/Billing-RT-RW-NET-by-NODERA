import React, { useState } from "react"
import { router } from "@inertiajs/react"
import { ShieldAlert, KeyRound, Eye, EyeOff, CheckCircle2, AlertCircle, X } from "lucide-react"

interface DefaultPinModalProps {
  isOpen: boolean
  onClose: () => void
}

export function DefaultPinModal({ isOpen, onClose }: DefaultPinModalProps) {
  const [pin, setPin] = useState("")
  const [confirmPin, setConfirmPin] = useState("")
  const [showPin, setShowPin] = useState(false)
  const [loading, setLoading] = useState(false)
  const [errorMsg, setErrorMsg] = useState<string | null>(null)
  const [successMsg, setSuccessMsg] = useState<string | null>(null)

  if (!isOpen) return null

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    setErrorMsg(null)
    setSuccessMsg(null)

    const cleanPin = pin.replace(/\D/g, "")
    const cleanConfirm = confirmPin.replace(/\D/g, "")

    if (cleanPin.length !== 6) {
      setErrorMsg("PIN baru harus terdiri dari 6 digit angka.")
      return
    }

    if (cleanPin === "123456") {
      setErrorMsg("PIN tidak boleh menggunakan 123456 (PIN default).")
      return
    }

    if (cleanPin !== cleanConfirm) {
      setErrorMsg("Konfirmasi PIN tidak cocok.")
      return
    }

    setLoading(true)
    router.post(
      "/portal/pin",
      { pin: cleanPin },
      {
        preserveScroll: true,
        onSuccess: () => {
          setLoading(false)
          setSuccessMsg("PIN berhasil diperbarui!")
          setTimeout(() => {
            onClose()
          }, 1500)
        },
        onError: (err) => {
          setLoading(false)
          const firstErr = Object.values(err)[0] as string
          setErrorMsg(firstErr || "Gagal mengubah PIN.")
        },
      }
    )
  }

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs animate-in fade-in duration-200">
      <div className="w-full max-w-md rounded-2xl border border-gray-200 bg-white p-5 sm:p-6 shadow-xl dark:border-gray-800 dark:bg-[#10141A] space-y-4">
        {/* Header */}
        <div className="flex items-start justify-between gap-3">
          <div className="flex items-center gap-2.5">
            <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-amber-500 text-white font-bold shrink-0 shadow-xs">
              <ShieldAlert className="h-5 w-5" />
            </div>
            <div>
              <h3 className="text-base font-bold text-gray-900 dark:text-white leading-tight">
                Amankan Akun Anda
              </h3>
              <p className="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                PIN default terdeteksi
              </p>
            </div>
          </div>
          <button
            type="button"
            onClick={onClose}
            className="p-1 rounded-lg text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition cursor-pointer"
          >
            <X className="h-4 w-4" />
          </button>
        </div>

        <p className="text-xs text-gray-600 dark:text-gray-300 leading-relaxed">
          Anda saat ini menggunakan <strong>PIN default (123456)</strong>. Demi keamanan privasi dan data tagihan Anda, silakan buat PIN baru (6 digit angka).
        </p>

        {errorMsg && (
          <div className="p-3 rounded-xl bg-rose-600 text-white text-xs font-bold flex items-center gap-2 shadow-xs">
            <AlertCircle className="h-4 w-4 shrink-0 text-white" />
            <span>{errorMsg}</span>
          </div>
        )}

        {successMsg && (
          <div className="p-3 rounded-xl bg-emerald-600 text-white text-xs font-bold flex items-center gap-2 shadow-xs">
            <CheckCircle2 className="h-4 w-4 shrink-0 text-white" />
            <span>{successMsg}</span>
          </div>
        )}

        <form onSubmit={handleSubmit} className="space-y-3.5">
          {/* PIN Baru */}
          <div className="space-y-1">
            <label className="text-xs font-bold text-gray-700 dark:text-gray-300 flex items-center justify-between">
              <span>PIN Baru (6 Digit Angka)</span>
              <button
                type="button"
                onClick={() => setShowPin(!showPin)}
                className="text-[11px] text-brand-500 hover:text-brand-600 flex items-center gap-1 cursor-pointer"
              >
                {showPin ? <EyeOff className="h-3 w-3" /> : <Eye className="h-3 w-3" />}
                <span>{showPin ? "Sembunyikan" : "Tampilkan"}</span>
              </button>
            </label>
            <div className="relative">
              <input
                type={showPin ? "text" : "password"}
                inputMode="numeric"
                pattern="[0-9]*"
                maxLength={6}
                value={pin}
                onChange={(e) => setPin(e.target.value.replace(/\D/g, "").slice(0, 6))}
                placeholder="6 digit angka (contoh: 849201)"
                className="w-full px-3.5 py-2.5 text-sm bg-gray-50 border border-gray-200 rounded-xl text-gray-900 placeholder-gray-400 focus:bg-white focus:outline-none focus:border-brand-500 dark:bg-gray-900/80 dark:border-gray-700 dark:text-white dark:focus:bg-gray-900 font-mono tracking-widest transition"
                required
                autoFocus
              />
            </div>
          </div>

          {/* Konfirmasi PIN */}
          <div className="space-y-1">
            <label className="text-xs font-bold text-gray-700 dark:text-gray-300">
              Ulangi PIN Baru
            </label>
            <input
              type={showPin ? "text" : "password"}
              inputMode="numeric"
              pattern="[0-9]*"
              maxLength={6}
              value={confirmPin}
              onChange={(e) => setConfirmPin(e.target.value.replace(/\D/g, "").slice(0, 6))}
              placeholder="Ketik ulang 6 digit angka"
              className="w-full px-3.5 py-2.5 text-sm bg-gray-50 border border-gray-200 rounded-xl text-gray-900 placeholder-gray-400 focus:bg-white focus:outline-none focus:border-brand-500 dark:bg-gray-900/80 dark:border-gray-700 dark:text-white dark:focus:bg-gray-900 font-mono tracking-widest transition"
              required
            />
          </div>

          <div className="pt-2 flex flex-col sm:flex-row items-center gap-2">
            <button
              type="submit"
              disabled={loading}
              className="w-full flex-1 inline-flex items-center justify-center gap-2 rounded-xl bg-brand-600 hover:bg-brand-700 active:scale-95 text-white py-2.5 text-xs font-bold shadow-xs transition disabled:opacity-50 cursor-pointer"
            >
              <KeyRound className="h-4 w-4" />
              <span>{loading ? "Menyimpan PIN..." : "Simpan PIN Baru"}</span>
            </button>
            <button
              type="button"
              onClick={onClose}
              className="w-full sm:w-auto px-4 py-2.5 rounded-xl border border-gray-200 text-gray-600 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800 text-xs font-bold transition cursor-pointer"
            >
              Nanti Saja
            </button>
          </div>
        </form>
      </div>
    </div>
  )
}
