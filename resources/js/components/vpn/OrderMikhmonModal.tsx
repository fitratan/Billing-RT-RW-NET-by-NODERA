import React, { useState } from "react"
import { createPortal } from "react-dom"
import { router } from "@inertiajs/react"
import { Globe, AlertCircle, X, Plus, Loader2, Check } from "lucide-react"
import { formatIDR } from "@/lib/utils"

interface OrderMikhmonModalProps {
  isOpen: boolean
  onClose: () => void
  userSaldo?: number
  monthlyPrice?: number
}

export function OrderMikhmonModal({
  isOpen,
  onClose,
  userSaldo = 0,
  monthlyPrice = 20000,
}: OrderMikhmonModalProps) {
  if (!isOpen) return null

  const [name, setName] = useState("")
  const [rosVersion, setRosVersion] = useState("6")
  const [processing, setProcessing] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const slug = name
    .toLowerCase()
    .replace(/^(mikhmon|hotspot)-+/g, "")
    .replace(/[^a-z0-9-]/g, "-")
    .replace(/-+/g, "-")
    .replace(/^-|-$/g, "")
    .slice(0, 30)
  const subdomain = `hotspot-${slug}`.replace(/^-+|-+$/g, "") || "hotspot"

  const isEnough = userSaldo >= monthlyPrice

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    if (!slug || slug.length < 3) {
      setError("Nama subdomain minimal 3 karakter huruf / angka.")
      return
    }
    setError(null)
    setProcessing(true)

    router.post(
      "/mikhmon/order",
      { subdomain, ros_version: rosVersion },
      {
        preserveScroll: true,
        onSuccess: () => {
          setProcessing(false)
          setName("")
          onClose()
        },
        onError: (errs) => {
          setProcessing(false)
          setError(Object.values(errs)[0] ?? "Terjadi kesalahan saat memproses order.")
        },
        onFinish: () => setProcessing(false),
      }
    )
  }

  return createPortal(
    <div className="fixed inset-0 z-[99999] flex items-center justify-center bg-black/70 p-4 backdrop-blur-sm animate-in fade-in duration-200">
      <div className="relative w-full max-w-lg rounded-2xl border border-gray-200 bg-white p-5 sm:p-6 shadow-2xl dark:border-gray-800 dark:bg-[#121720] space-y-5 max-h-[92vh] overflow-y-auto custom-scrollbar">
        {/* Header */}
        <div className="flex items-center justify-between pb-3 border-b border-gray-100 dark:border-gray-800">
          <div className="flex items-center gap-2.5">
            <div className="flex h-9 w-9 items-center justify-center rounded-xl bg-brand-500/10 text-brand-500">
              <Globe className="h-5 w-5" />
            </div>
            <div>
              <h3 className="font-bold text-sm sm:text-base text-gray-900 dark:text-white">
                Order Mikhmon Online Baru
              </h3>
              <p className="text-[11px] text-gray-500 dark:text-gray-400">
                Instance server Mikhmon dedicated online 24/7
              </p>
            </div>
          </div>
          <button
            type="button"
            onClick={onClose}
            disabled={processing}
            className="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 disabled:opacity-50 disabled:cursor-not-allowed disabled:pointer-events-none dark:hover:bg-gray-800 dark:hover:text-gray-200 transition cursor-pointer"
          >
            <X className="h-5 w-5" />
          </button>
        </div>

        {error && (
          <div className="flex items-start gap-2.5 rounded-xl border border-rose-200 bg-rose-50/80 p-3 text-xs text-rose-700 dark:border-rose-900/50 dark:bg-rose-950/40 dark:text-rose-300 font-medium">
            <AlertCircle className="h-4 w-4 shrink-0 mt-0.5 text-rose-500" />
            <span>{error}</span>
          </div>
        )}

        <form onSubmit={handleSubmit} className="space-y-4">
          {/* Subdomain Input */}
          <div>
            <label className="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-1.5">
              Nama Hotspot / Subdomain
            </label>
            <div className="flex items-center rounded-xl border border-gray-200 bg-gray-50/50 dark:border-gray-800 dark:bg-gray-900 focus-within:border-brand-500 focus-within:bg-white overflow-hidden transition">
              <span className="px-3 text-xs font-mono font-bold text-brand-600 dark:text-brand-400 bg-gray-100/80 dark:bg-gray-800/80 py-2.5 border-r border-gray-200 dark:border-gray-800 shrink-0">
                hotspot-
              </span>
              <input
                type="text"
                value={name}
                onChange={(e) => setName(e.target.value)}
                placeholder="namahotspotanda"
                required
                className="w-full bg-transparent px-3 py-2.5 text-xs font-mono font-bold text-gray-900 dark:text-white focus:outline-hidden"
              />
              <span className="px-3 text-xs font-mono text-gray-400 py-2.5 border-l border-gray-200 dark:border-gray-800 shrink-0 hidden sm:inline">
                .dgtlnetsolution.com
              </span>
            </div>
            <p className="mt-1 text-[11px] text-gray-400 font-mono">
              URL Akses: <span className="text-brand-500 font-bold">{subdomain}.dgtlnetsolution.com</span>
            </p>
          </div>

          {/* RouterOS Version */}
          <div>
            <label className="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-1.5">
              Versi RouterOS MikroTik
            </label>
            <div className="grid grid-cols-2 gap-3">
              {[
                { id: "6", label: "RouterOS v6", sub: "Mikhmon v3 Standard" },
                { id: "7", label: "RouterOS v7", sub: "Mikhmon v3 ROS7 Supported" },
              ].map((v) => (
                <div
                  key={v.id}
                  onClick={() => setRosVersion(v.id)}
                  className={`p-3 rounded-xl border text-left cursor-pointer transition flex items-center justify-between ${
                    rosVersion === v.id
                      ? "border-brand-500 bg-brand-500/10 text-brand-600 dark:text-brand-400"
                      : "border-gray-200 bg-gray-50/50 hover:bg-gray-100 dark:border-gray-800 dark:bg-gray-900 text-gray-700 dark:text-gray-300"
                  }`}
                >
                  <div>
                    <span className="text-xs font-bold block">{v.label}</span>
                    <span className="text-[10px] text-gray-400 block mt-0.5">{v.sub}</span>
                  </div>
                  {rosVersion === v.id && <Check className="h-4 w-4 text-brand-500 shrink-0" />}
                </div>
              ))}
            </div>
          </div>

          {/* Price Breakdown */}
          <div className="rounded-xl border border-gray-200 bg-gray-50/80 p-3.5 dark:border-gray-800 dark:bg-gray-800/40 space-y-2">
            <div className="flex items-center justify-between text-xs">
              <span className="text-gray-500 dark:text-gray-400 font-medium">Biaya Berlangganan</span>
              <span className="font-mono font-bold text-gray-900 dark:text-white">
                {formatIDR(monthlyPrice)} / bln
              </span>
            </div>
            <div className="flex items-center justify-between text-xs pt-2 border-t border-gray-200 dark:border-gray-700/60">
              <span className="text-gray-500 dark:text-gray-400 font-medium">Saldo Master Wallet</span>
              <div className="flex items-center gap-2">
                <span className={`font-mono font-bold ${isEnough ? "text-emerald-500" : "text-rose-500"}`}>
                  {formatIDR(userSaldo)}
                </span>
                {!isEnough && (
                  <button
                    type="button"
                    onClick={() => {
                      onClose()
                      router.visit("/topup")
                    }}
                    className="px-2 py-0.5 rounded-md bg-rose-500/10 hover:bg-rose-500/20 text-rose-600 dark:text-rose-400 text-[10px] font-bold border border-rose-200 dark:border-rose-900/40 transition cursor-pointer"
                  >
                    + Isi Saldo
                  </button>
                )}
              </div>
            </div>
            {!isEnough && (
              <p className="text-[11px] text-rose-500 dark:text-rose-400 font-medium pt-1">
                Saldo Anda belum mencukupi. Silakan lakukan deposit saldo terlebih dahulu untuk mengaktifkan server Mikhmon.
              </p>
            )}
          </div>

          {/* Action Buttons */}
          <div className="pt-2 flex items-center gap-3">
            <button
              type="button"
              onClick={onClose}
              disabled={processing}
              className="flex-1 h-10 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed disabled:pointer-events-none dark:border-gray-800 dark:bg-gray-800 dark:hover:bg-gray-700 text-xs font-bold text-gray-700 dark:text-gray-300 transition cursor-pointer"
            >
              Batal
            </button>

            <button
              type="submit"
              disabled={processing || !isEnough}
              className="flex-1 h-10 rounded-xl bg-brand-500 hover:bg-brand-600 disabled:opacity-50 disabled:cursor-not-allowed text-xs font-bold text-white shadow-theme-xs transition active:scale-98 cursor-pointer inline-flex items-center justify-center gap-2"
            >
              {processing ? (
                <>
                  <Loader2 className="h-4 w-4 animate-spin" />
                  <span>Memproses...</span>
                </>
              ) : (
                <>
                  <Plus className="h-4 w-4" />
                  <span>Konfirmasi Order</span>
                </>
              )}
            </button>
          </div>
        </form>
      </div>
    </div>,
    document.body
  )
}
