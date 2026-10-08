import React, { useState } from "react"
import { Head, router } from "@inertiajs/react"
import { AppLayout } from "@/components/layout/app-layout"
import { portalBrand, portalNavItems } from "@/lib/portal-nav"
import { formatCurrency } from "@/lib/utils"
import {
  User,
  Wifi,
  Phone,
  MapPin,
  Lock,
  CheckCircle2,
  AlertCircle,
  Copy,
  Check,
  Eye,
  EyeOff,
  Clock,
  Gauge,
} from "lucide-react"

interface CustomerProfile {
  id: number
  name: string
  code: string
  phone: string
  email: string
  address: string
  pppoe_username: string
  status: string
  due_date: number | string
  auto_isolate: boolean
  package_name: string
  package_price: number
  package_speed: string
  created_at: string
}

interface ProfileProps {
  customer: CustomerProfile
  companyName?: string
}

export default function ProfilePage({
  customer,
  companyName = "NODERA",
}: ProfileProps) {
  const [newPin, setNewPin] = useState("")
  const [confirmPin, setConfirmPin] = useState("")
  const [showPin, setShowPin] = useState(false)
  const [pinLoading, setPinLoading] = useState(false)
  const [pinMsg, setPinMsg] = useState<{
    type: "success" | "error"
    text: string
  } | null>(null)
  const [copiedField, setCopiedField] = useState<string | null>(null)

  const copyToClipboard = (text: string, field: string) => {
    if (!text || text === "-") return
    navigator.clipboard.writeText(text)
    setCopiedField(field)
    setTimeout(() => setCopiedField(null), 2000)
  }

  const handleUpdatePin = (e: React.FormEvent) => {
    e.preventDefault()
    setPinMsg(null)

    if (newPin.length < 4) {
      setPinMsg({
        type: "error",
        text: "PIN atau kata sandi minimal 4 karakter.",
      })
      return
    }

    if (newPin !== confirmPin) {
      setPinMsg({
        type: "error",
        text: "Konfirmasi PIN tidak cocok. Silakan periksa kembali.",
      })
      return
    }

    setPinLoading(true)
    router.post(
      "/portal/pin",
      { pin: newPin },
      {
        preserveScroll: true,
        onSuccess: () => {
          setPinLoading(false)
          setNewPin("")
          setConfirmPin("")
          setPinMsg({
            type: "success",
            text: "PIN login portal Anda berhasil diperbarui.",
          })
        },
        onError: (errors) => {
          setPinLoading(false)
          const errText =
            Object.values(errors)[0] || "Gagal memperbarui PIN portal."
          setPinMsg({
            type: "error",
            text: String(errText),
          })
        },
      }
    )
  }

  const isActive = customer.status === "active" || customer.status === "aktif"

  return (
    <AppLayout
      title="Profil Pengguna"
      subtitle={companyName}
      brand={portalBrand}
      navItems={portalNavItems}
      hideSidebar={true}
    >
      <Head title="Profil Pengguna — Portal Pelanggan" />

      <div className="space-y-4 max-w-2xl mx-auto pb-32 sm:pb-20 px-0 sm:px-2">
        {/* =========================================================================
            HEADER IDENTITY CARD (CLEAN FLAT TAILADMIN)
            ========================================================================= */}
        <div className="rounded-2xl border border-gray-200 bg-white p-5 shadow-xs dark:border-gray-800 dark:bg-white/[0.03]">
          <div className="flex items-center gap-3.5">
            {/* Clean Solid Avatar */}
            <div className="w-12 h-12 rounded-xl bg-brand-600 text-white flex items-center justify-center shrink-0 shadow-xs font-black text-xl">
              {customer.name ? customer.name.charAt(0).toUpperCase() : "U"}
            </div>

            <div className="flex-1 min-w-0">
              <div className="flex items-center justify-between gap-2">
                <h2 className="text-base sm:text-lg font-bold text-gray-900 dark:text-white tracking-tight truncate">
                  {customer.name || "Pelanggan"}
                </h2>
                {/* SOLID BADGE */}
                <span
                  className={`inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-black uppercase tracking-wider shrink-0 ${
                    isActive
                      ? "bg-emerald-600 text-white"
                      : "bg-rose-600 text-white"
                  }`}
                >
                  {isActive ? "Layanan Aktif" : "Isolir / Nonaktif"}
                </span>
              </div>

              <div className="flex items-center gap-2 mt-1 text-xs text-gray-500 dark:text-gray-400 flex-wrap">
                <div className="flex items-center gap-1 font-mono font-bold text-gray-700 dark:text-gray-300">
                  <span>ID: {customer.code || customer.id}</span>
                  <button
                    type="button"
                    onClick={() => copyToClipboard(customer.code || String(customer.id), "header_id")}
                    className="p-0.5 hover:bg-gray-100 dark:hover:bg-gray-800 rounded transition cursor-pointer"
                    title="Salin ID"
                  >
                    {copiedField === "header_id" ? (
                      <Check className="w-3 h-3 text-emerald-600" />
                    ) : (
                      <Copy className="w-3 h-3 text-gray-400 hover:text-gray-600" />
                    )}
                  </button>
                </div>
                <span>•</span>
                <span>Terdaftar: {customer.created_at}</span>
              </div>
            </div>
          </div>
        </div>

        {/* =========================================================================
            PAKET & LAYANAN (SOLID BADGES & ADAPTIVE CARDS)
            ========================================================================= */}
        <div className="rounded-2xl border border-gray-200 bg-white p-5 shadow-xs dark:border-gray-800 dark:bg-white/[0.03] space-y-4">
          <div className="flex items-center justify-between border-b border-gray-100 dark:border-gray-800 pb-3">
            <div className="flex items-center gap-2 text-sm font-bold text-gray-900 dark:text-white">
              <Wifi className="w-4 h-4 text-brand-500" />
              <span>Informasi Langganan & Paket</span>
            </div>
            <span className="bg-brand-600 text-white font-black text-[10px] px-2 py-0.5 rounded-md uppercase tracking-wider">
              {customer.package_name || "Internet"}
            </span>
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div className="rounded-xl border border-gray-100 bg-gray-50/80 p-3.5 dark:border-gray-800 dark:bg-white/[0.02]">
              <span className="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                Kecepatan Paket
              </span>
              <div className="text-base font-extrabold text-gray-900 dark:text-white mt-0.5 flex items-center gap-1.5">
                <Gauge className="w-4 h-4 text-brand-500" />
                <span>{customer.package_speed || "Up to 20 Mbps"}</span>
              </div>
              <p className="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5">
                Koneksi Unlimited Tanpa FUP
              </p>
            </div>

            <div className="rounded-xl border border-gray-100 bg-gray-50/80 p-3.5 dark:border-gray-800 dark:bg-white/[0.02]">
              <span className="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                Tarif Bulanan
              </span>
              <div className="text-base font-extrabold font-mono text-gray-900 dark:text-white mt-0.5">
                {formatCurrency(customer.package_price || 0)}
              </div>
              <p className="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5 flex items-center gap-1">
                <Clock className="w-3.5 h-3.5 text-gray-400" />
                <span>Jatuh tempo setiap tgl {customer.due_date || 20}</span>
              </p>
            </div>
          </div>
        </div>

        {/* =========================================================================
            DETAIL AKUN & KONTAK (CLEAN RIGHT-ALIGNED WRAP)
            ========================================================================= */}
        <div className="rounded-2xl border border-gray-200 bg-white p-5 shadow-xs dark:border-gray-800 dark:bg-white/[0.03] space-y-3">
          <div className="flex items-center gap-2 text-sm font-bold text-gray-900 dark:text-white border-b border-gray-100 dark:border-gray-800 pb-3">
            <User className="w-4 h-4 text-brand-500" />
            <span>Detail Akun & Kontak</span>
          </div>

          <div className="divide-y divide-gray-100 dark:divide-gray-800">
            <div className="flex items-center justify-between py-2.5 text-xs gap-3">
              <span className="text-gray-500 dark:text-gray-400 flex items-center gap-2 shrink-0">
                <Phone className="w-3.5 h-3.5 text-gray-400" />
                Nomor WhatsApp
              </span>
              <div className="flex items-center gap-1.5 font-semibold text-gray-900 dark:text-gray-200 text-right">
                <span>{customer.phone || "-"}</span>
                {customer.phone && customer.phone !== "-" && (
                  <button
                    type="button"
                    onClick={() => copyToClipboard(customer.phone, "phone_row")}
                    className="p-1 hover:bg-gray-100 dark:hover:bg-gray-800 rounded transition cursor-pointer"
                    title="Salin nomor"
                  >
                    {copiedField === "phone_row" ? (
                      <Check className="w-3 h-3 text-emerald-600" />
                    ) : (
                      <Copy className="w-3 h-3 text-gray-400 hover:text-gray-600" />
                    )}
                  </button>
                )}
              </div>
            </div>

            <div className="flex items-start justify-between py-2.5 text-xs gap-3">
              <span className="text-gray-500 dark:text-gray-400 flex items-center gap-2 shrink-0 pt-0.5">
                <MapPin className="w-3.5 h-3.5 text-gray-400" />
                Alamat Pasang
              </span>
              <div className="text-right flex-1 min-w-0">
                <span className="font-semibold text-gray-900 dark:text-gray-200 break-words text-right inline-block">
                  {customer.address || "-"}
                </span>
              </div>
            </div>
          </div>
        </div>

        {/* =========================================================================
            KEAMANAN & UBAH PIN PORTAL (TAILADMIN ADAPTIVE)
            ========================================================================= */}
        <div className="rounded-2xl border border-gray-200 bg-white p-5 shadow-xs dark:border-gray-800 dark:bg-white/[0.03] space-y-4">
          <div className="flex items-center gap-2 text-sm font-bold text-gray-900 dark:text-white border-b border-gray-100 dark:border-gray-800 pb-3">
            <Lock className="w-4 h-4 text-brand-500" />
            <span>Keamanan & PIN Login Portal</span>
          </div>

          <p className="text-xs text-gray-500 dark:text-gray-400">
            Ubah PIN 6 digit untuk masuk ke Portal Pelanggan Anda.
          </p>

          {pinMsg && (
            <div
              className={`p-3 rounded-xl text-xs flex items-center gap-2 ${
                pinMsg.type === "success"
                  ? "bg-emerald-600 text-white font-bold"
                  : "bg-rose-600 text-white font-bold"
              }`}
            >
              {pinMsg.type === "success" ? (
                <CheckCircle2 className="w-4 h-4 shrink-0 text-white" />
              ) : (
                <AlertCircle className="w-4 h-4 shrink-0 text-white" />
              )}
              <span>{pinMsg.text}</span>
            </div>
          )}

          <form onSubmit={handleUpdatePin} className="space-y-3">
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <div>
                <label className="text-[11px] font-bold text-gray-700 dark:text-gray-300 block mb-1">
                  PIN Baru (6 Digit Angka)
                </label>
                <div className="relative">
                  <input
                    type={showPin ? "text" : "password"}
                    inputMode="numeric"
                    pattern="[0-9]*"
                    maxLength={6}
                    value={newPin}
                    onChange={(e) => setNewPin(e.target.value.replace(/\D/g, "").slice(0, 6))}
                    placeholder="6 digit angka"
                    className="w-full px-3 py-2 text-xs bg-gray-50 border border-gray-200 rounded-xl text-gray-900 placeholder-gray-400 focus:bg-white focus:outline-none focus:border-brand-500 dark:bg-gray-900/60 dark:border-gray-700 dark:text-white dark:focus:bg-gray-900 pr-9 transition font-mono tracking-widest"
                    required
                  />
                  <button
                    type="button"
                    onClick={() => setShowPin(!showPin)}
                    className="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 cursor-pointer"
                  >
                    {showPin ? <EyeOff className="w-3.5 h-3.5" /> : <Eye className="w-3.5 h-3.5" />}
                  </button>
                </div>
              </div>

              <div>
                <label className="text-[11px] font-bold text-gray-700 dark:text-gray-300 block mb-1">
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
                  className="w-full px-3 py-2 text-xs bg-gray-50 border border-gray-200 rounded-xl text-gray-900 placeholder-gray-400 focus:bg-white focus:outline-none focus:border-brand-500 dark:bg-gray-900/60 dark:border-gray-700 dark:text-white dark:focus:bg-gray-900 transition font-mono tracking-widest"
                  required
                />
              </div>
            </div>

            <button
              type="submit"
              disabled={pinLoading}
              className="w-full sm:w-auto px-5 py-2.5 rounded-xl bg-brand-600 hover:bg-brand-700 active:scale-95 text-white font-bold text-xs shadow-xs transition disabled:opacity-50 cursor-pointer"
            >
              {pinLoading ? "Menyimpan PIN..." : "Simpan PIN Baru"}
            </button>
          </form>
        </div>
      </div>
    </AppLayout>
  )
}