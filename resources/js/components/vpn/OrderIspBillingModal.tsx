import React, { useState, useMemo, useEffect } from "react"
import { createPortal } from "react-dom"
import { router } from "@inertiajs/react"
import {
  Server,
  Cloud,
  AlertCircle,
  X,
  Loader2,
  Eye,
  EyeOff,
} from "lucide-react"
import { formatIDR, cn } from "@/lib/utils"

interface SaasPackage {
  id: number
  name: string
  monthly_price: number
  semi_annual_price?: number
  annual_price?: number
  price?: number
  duration_options?: string
  max_customers?: number
  max_routers?: number
  features?: string[]
}

interface StandalonePackage {
  id: number
  name: string
  type: string
  price: number
  max_customers?: number
  max_routers?: number
  has_source_code?: boolean
}

interface OrderIspBillingModalProps {
  isOpen: boolean
  onClose: () => void
  saasPackages?: SaasPackage[]
  standalonePackages?: StandalonePackage[]
  baseDomain?: string
  userSaldo?: number
}

export function OrderIspBillingModal({
  isOpen,
  onClose,
  saasPackages = [],
  standalonePackages = [],
  baseDomain = "dgtlnetsolution.com",
  userSaldo = 0,
}: OrderIspBillingModalProps) {
  if (!isOpen) return null

  const [orderType, setOrderType] = useState<"saas" | "standalone">("saas")
  const [selectedPkgId, setSelectedPkgId] = useState<number | string>(
    saasPackages[0]?.id || ""
  )
  const [duration, setDuration] = useState<number>(1)
  const [company, setCompany] = useState("")
  const [slug, setSlug] = useState("")
  const [username, setUsername] = useState("")
  const [password, setPassword] = useState("")
  const [phone, setPhone] = useState("")
  const [domain, setDomain] = useState("")
  const [showPassword, setShowPassword] = useState(false)

  const [processing, setProcessing] = useState(false)
  const [error, setError] = useState<string | null>(null)

  // Resolve active selected package
  const selectedSaasPkg = saasPackages.find(
    (p) => String(p.id) === String(selectedPkgId)
  )
  const selectedStandalonePkg = standalonePackages.find(
    (p) => String(p.id) === String(selectedPkgId)
  )

  // Dynamic Allowed Durations based on Package Configuration
  const allowedDurations = useMemo(() => {
    if (!selectedSaasPkg) return [1, 3, 6, 12]

    const raw = selectedSaasPkg.duration_options
    if (raw && typeof raw === "string" && raw.trim() !== "") {
      const list = raw
        .split(",")
        .map((s) => parseInt(s.trim(), 10))
        .filter((n) => !isNaN(n) && n > 0)
      if (list.length > 0) return list
    }

    const nameLower = (selectedSaasPkg.name || "").toLowerCase()
    if (
      nameLower.includes("trial") ||
      nameLower.includes("uji coba") ||
      nameLower.includes("gratis") ||
      nameLower.includes("demo")
    ) {
      return [1]
    }

    return [1, 3, 6, 12]
  }, [selectedSaasPkg])

  // Sync duration if not present in allowed list
  useEffect(() => {
    if (allowedDurations.length > 0 && !allowedDurations.includes(duration)) {
      setDuration(allowedDurations[0])
    }
  }, [allowedDurations, duration])

  const calculatedPrice = () => {
    if (orderType === "saas" && selectedSaasPkg) {
      if (duration === 12 && selectedSaasPkg.annual_price) {
        return Number(selectedSaasPkg.annual_price)
      }
      if (duration === 6 && selectedSaasPkg.semi_annual_price) {
        return Number(selectedSaasPkg.semi_annual_price)
      }
      const monthly = Number(selectedSaasPkg.monthly_price || selectedSaasPkg.price || 0)
      return monthly * duration
    }
    if (orderType === "standalone" && selectedStandalonePkg) {
      return Number(selectedStandalonePkg.price || 0)
    }
    return 0
  }

  const cleanSlug = slug
    .toLowerCase()
    .replace(/[^a-z0-9-]/g, "-")
    .replace(/-+/g, "-")
    .replace(/^-|-$/g, "")
    .slice(0, 30)

  const getDurationLabel = (m: number) => {
    if (m === 12) return "1 Thn"
    if (m >= 12 && m % 12 === 0) return `${m / 12} Thn`
    return `${m} Bln`
  }

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    setError(null)

    if (orderType === "saas") {
      if (!company.trim()) {
        setError("Nama Brand / ISP wajib diisi.")
        return
      }
      if (!cleanSlug || cleanSlug.length < 3) {
        setError("Subdomain minimal 3 karakter huruf / angka.")
        return
      }
      if (!username.trim() || username.length < 3) {
        setError("Username admin minimal 3 karakter.")
        return
      }
      if (!password || password.length < 6) {
        setError("Password admin minimal 6 karakter.")
        return
      }

      setProcessing(true)
      router.post(
        "/isp-billing/order-saas",
        {
          package_id: selectedPkgId,
          duration,
          company,
          slug: cleanSlug,
          username,
          password,
          phone,
        },
        {
          preserveScroll: true,
          onSuccess: () => {
            setProcessing(false)
            onClose()
          },
          onError: (errs) => {
            setProcessing(false)
            setError(Object.values(errs)[0] ?? "Gagal memproses order.")
          },
          onFinish: () => setProcessing(false),
        }
      )
    } else {
      if (!domain.trim()) {
        setError("Domain / Subdomain server wajib diisi.")
        return
      }
      setProcessing(true)
      router.post(
        "/isp-billing/order",
        {
          package_id: selectedPkgId,
          domain,
        },
        {
          preserveScroll: true,
          onSuccess: () => {
            setProcessing(false)
            onClose()
          },
          onError: (errs) => {
            setProcessing(false)
            setError(Object.values(errs)[0] ?? "Gagal memproses order.")
          },
          onFinish: () => setProcessing(false),
        }
      )
    }
  }

  const finalPrice = calculatedPrice()
  const isSaldoSufficient = Number(userSaldo) >= finalPrice

  return createPortal(
    <div className="fixed inset-0 z-[99999] flex items-center justify-center bg-black/70 p-4 backdrop-blur-sm animate-in fade-in duration-150">
      <div className="w-full max-w-lg max-h-[92vh] flex flex-col rounded-2xl border border-gray-200 bg-white shadow-2xl dark:border-gray-800 dark:bg-[#121720] overflow-hidden">
        {/* Modal Header */}
        <div className="flex items-center justify-between border-b border-gray-100 px-5 py-4 dark:border-gray-800 shrink-0">
          <div className="flex items-center gap-2.5">
            <div className="flex h-9 w-9 items-center justify-center rounded-xl bg-brand-500/10 text-brand-600 dark:text-brand-400">
              <Cloud className="h-5 w-5" />
            </div>
            <div>
              <h3 className="text-sm font-bold text-gray-900 dark:text-white">
                Order Lisensi ISP Billing
              </h3>
              <p className="text-xs text-gray-500 dark:text-gray-400">
                Sistem billing otomasi tagihan & manajemen pelanggan ISP
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

        {/* Modal Body with Internal Scroll */}
        <form onSubmit={handleSubmit} className="flex-1 overflow-y-auto p-5 space-y-4">
          {error && (
            <div className="flex items-center gap-2 rounded-xl bg-rose-500/10 p-3 text-xs font-bold text-rose-600 dark:text-rose-400">
              <AlertCircle className="h-4 w-4 shrink-0" />
              <span>{error}</span>
            </div>
          )}

          {/* Deployment Architecture Toggle */}
          <div>
            <label className="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-1.5">
              Tipe Infrastruktur
            </label>
            <div className="grid grid-cols-2 gap-2">
              <button
                type="button"
                onClick={() => {
                  setOrderType("saas")
                  if (saasPackages[0]?.id) setSelectedPkgId(saasPackages[0].id)
                }}
                className={`flex items-center justify-center gap-2 p-2.5 rounded-xl border text-xs font-bold transition cursor-pointer ${
                  orderType === "saas"
                    ? "border-brand-500 bg-brand-500/10 text-brand-600 dark:text-brand-400"
                    : "border-gray-200 bg-gray-50/50 text-gray-600 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-400"
                }`}
              >
                <Cloud className="h-4 w-4" />
                <span>Cloud Managed SaaS</span>
              </button>

              <button
                type="button"
                onClick={() => {
                  setOrderType("standalone")
                  if (standalonePackages[0]?.id) setSelectedPkgId(standalonePackages[0].id)
                }}
                className={`flex items-center justify-center gap-2 p-2.5 rounded-xl border text-xs font-bold transition cursor-pointer ${
                  orderType === "standalone"
                    ? "border-brand-500 bg-brand-500/10 text-brand-600 dark:text-brand-400"
                    : "border-gray-200 bg-gray-50/50 text-gray-600 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-400"
                }`}
              >
                <Server className="h-4 w-4" />
                <span>Self-Hosted Server</span>
              </button>
            </div>
          </div>

          {/* Package Selector */}
          <div>
            <label className="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-1.5">
              Pilih Paket Lisensi
            </label>
            <select
              value={selectedPkgId}
              onChange={(e) => setSelectedPkgId(e.target.value)}
              className="h-10 w-full rounded-xl border border-gray-200 bg-gray-50/50 px-3 text-xs font-bold text-gray-800 focus:border-brand-500 focus:bg-white focus:outline-hidden dark:border-gray-800 dark:bg-gray-900 dark:text-gray-200 cursor-pointer"
            >
              {orderType === "saas"
                ? saasPackages.map((pkg) => (
                    <option key={pkg.id} value={pkg.id}>
                      {pkg.name} — {formatIDR(pkg.monthly_price || pkg.price || 0)} /bln
                    </option>
                  ))
                : standalonePackages.map((pkg) => (
                    <option key={pkg.id} value={pkg.id}>
                      {pkg.name} ({pkg.type}) — {formatIDR(pkg.price)}
                    </option>
                  ))}
            </select>
          </div>

          {orderType === "saas" ? (
            <>
              {/* Dynamic Duration Selector */}
              <div>
                <label className="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-1.5">
                  Durasi Langganan
                </label>
                <div
                  className={cn(
                    "grid gap-2",
                    allowedDurations.length === 1
                      ? "grid-cols-1"
                      : allowedDurations.length === 2
                      ? "grid-cols-2"
                      : allowedDurations.length === 3
                      ? "grid-cols-3"
                      : "grid-cols-4"
                  )}
                >
                  {allowedDurations.map((val) => (
                    <button
                      key={val}
                      type="button"
                      onClick={() => setDuration(val)}
                      className={`p-2 rounded-xl border text-xs font-bold transition cursor-pointer text-center ${
                        duration === val
                          ? "border-brand-500 bg-brand-500 text-white"
                          : "border-gray-200 bg-gray-50/50 text-gray-700 hover:bg-gray-100 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800"
                      }`}
                    >
                      {getDurationLabel(val)}
                    </button>
                  ))}
                </div>
              </div>

              {/* Brand Name */}
              <div>
                <label className="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-1.5">
                  Nama Brand / Usaha ISP
                </label>
                <input
                  type="text"
                  value={company}
                  onChange={(e) => setCompany(e.target.value)}
                  placeholder="Contoh: DigitalNet Solusindo"
                  required
                  className="h-10 w-full rounded-xl border border-gray-200 bg-gray-50/50 px-3 text-xs font-medium text-gray-900 placeholder:text-gray-400 focus:border-brand-500 focus:bg-white focus:outline-hidden dark:border-gray-800 dark:bg-gray-900 dark:text-white"
                />
              </div>

              {/* Subdomain */}
              <div>
                <label className="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-1.5">
                  Subdomain Akses Sistem
                </label>
                <div className="flex items-center rounded-xl border border-gray-200 bg-gray-50/50 dark:border-gray-800 dark:bg-gray-900 overflow-hidden focus-within:border-brand-500 focus-within:bg-white">
                  <input
                    type="text"
                    value={slug}
                    onChange={(e) => setSlug(e.target.value)}
                    placeholder="nama-isp"
                    required
                    className="h-10 flex-1 bg-transparent px-3 text-xs font-bold text-gray-900 placeholder:text-gray-400 focus:outline-hidden dark:text-white"
                  />
                  <span className="bg-gray-100 px-3 py-2 text-xs font-bold text-gray-500 dark:bg-gray-800 dark:text-gray-400 border-l border-gray-200 dark:border-gray-700">
                    .{baseDomain}
                  </span>
                </div>
              </div>

              {/* Admin Username & Password */}
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                  <label className="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-1.5">
                    Username Admin
                  </label>
                  <input
                    type="text"
                    value={username}
                    onChange={(e) => setUsername(e.target.value)}
                    placeholder="admin"
                    required
                    className="h-10 w-full rounded-xl border border-gray-200 bg-gray-50/50 px-3 text-xs font-medium text-gray-900 placeholder:text-gray-400 focus:border-brand-500 focus:bg-white focus:outline-hidden dark:border-gray-800 dark:bg-gray-900 dark:text-white"
                  />
                </div>

                <div>
                  <label className="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-1.5">
                    Password Admin
                  </label>
                  <div className="relative">
                    <input
                      type={showPassword ? "text" : "password"}
                      value={password}
                      onChange={(e) => setPassword(e.target.value)}
                      placeholder="Minimal 6 karakter"
                      required
                      className="h-10 w-full rounded-xl border border-gray-200 bg-gray-50/50 px-3 pr-9 text-xs font-medium text-gray-900 placeholder:text-gray-400 focus:border-brand-500 focus:bg-white focus:outline-hidden dark:border-gray-800 dark:bg-gray-900 dark:text-white"
                    />
                    <button
                      type="button"
                      onClick={() => setShowPassword(!showPassword)}
                      className="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 cursor-pointer"
                    >
                      {showPassword ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                    </button>
                  </div>
                </div>
              </div>

              {/* Admin WhatsApp */}
              <div>
                <label className="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-1.5">
                  Nomor WhatsApp Admin (Opsional)
                </label>
                <input
                  type="text"
                  value={phone}
                  onChange={(e) => setPhone(e.target.value)}
                  placeholder="08xxxxxxxxxx"
                  className="h-10 w-full rounded-xl border border-gray-200 bg-gray-50/50 px-3 text-xs font-medium text-gray-900 placeholder:text-gray-400 focus:border-brand-500 focus:bg-white focus:outline-hidden dark:border-gray-800 dark:bg-gray-900 dark:text-white"
                />
              </div>
            </>
          ) : (
            <>
              {/* Standalone Domain Input */}
              <div>
                <label className="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-1.5">
                  Domain / IP Server Target
                </label>
                <input
                  type="text"
                  value={domain}
                  onChange={(e) => setDomain(e.target.value)}
                  placeholder="billing.ispanda.com atau 103.x.x.x"
                  required
                  className="h-10 w-full rounded-xl border border-gray-200 bg-gray-50/50 px-3 text-xs font-medium text-gray-900 placeholder:text-gray-400 focus:border-brand-500 focus:bg-white focus:outline-hidden dark:border-gray-800 dark:bg-gray-900 dark:text-white"
                />
                <p className="mt-1 text-[11px] text-gray-500 dark:text-gray-400">
                  Lisensi akan dikunci pada domain / IP yang didaftarkan.
                </p>
              </div>
            </>
          )}

          {/* Pricing & Saldo Info Box */}
          <div className="rounded-xl border border-gray-200/80 bg-gray-50/50 p-3.5 dark:border-gray-800 dark:bg-white/[0.02] space-y-1.5">
            <div className="flex items-center justify-between text-xs">
              <span className="text-gray-500 dark:text-gray-400">Total Biaya Lisensi:</span>
              <span className="font-bold text-gray-900 dark:text-white font-mono">
                {formatIDR(finalPrice)}
              </span>
            </div>
            <div className="flex items-center justify-between text-xs">
              <span className="text-gray-500 dark:text-gray-400">Saldo Master Wallet Anda:</span>
              <span
                className={`font-bold font-mono ${
                  isSaldoSufficient
                    ? "text-emerald-600 dark:text-emerald-400"
                    : "text-rose-600 dark:text-rose-400"
                }`}
              >
                {formatIDR(Number(userSaldo))}
              </span>
            </div>
          </div>

          {/* Modal Actions */}
          <div className="flex items-center gap-2.5 pt-2">
            <button
              type="button"
              onClick={onClose}
              disabled={processing}
              className="flex-1 h-10 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed disabled:pointer-events-none dark:border-gray-800 dark:bg-gray-800 dark:hover:bg-gray-700 text-xs font-bold text-gray-700 dark:text-gray-200 transition cursor-pointer"
            >
              Batal
            </button>
            <button
              type="submit"
              disabled={processing || !isSaldoSufficient}
              className="flex-1 inline-flex items-center justify-center gap-1.5 h-10 rounded-xl bg-brand-500 hover:bg-brand-600 disabled:opacity-50 disabled:cursor-not-allowed text-xs font-bold text-white shadow-theme-xs transition active:scale-98 cursor-pointer"
            >
              {processing ? (
                <>
                  <Loader2 className="h-4 w-4 animate-spin" />
                  <span>Memproses...</span>
                </>
              ) : (
                <span>
                  {isSaldoSufficient
                    ? `Order Sekarang (${formatIDR(finalPrice)})`
                    : "Saldo Tidak Cukup"}
                </span>
              )}
            </button>
          </div>
        </form>
      </div>
    </div>,
    document.body
  )
}

export default OrderIspBillingModal
