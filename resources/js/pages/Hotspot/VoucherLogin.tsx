import { useState, useEffect } from "react"
import { Head, Link } from "@inertiajs/react"
import {
  Wifi,
  KeyRound,
  QrCode,
  Smartphone,
  CheckCircle2,
  Copy,
  ExternalLink,
  ArrowRight,
  ShieldCheck,
  Clock,
  Gauge,
  HelpCircle,
  MessageCircle,
  AlertCircle,
  RefreshCw,
  X,
  CreditCard,
  Building2,
  Check,
  Play,
} from "lucide-react"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { LanguageSwitcher } from "@/components/layout/language-switcher"

interface Package {
  id: number
  name: string
  profile?: string
  price: number
  time_limit?: string
  data_limit?: number | string | null
  bandwidth_down?: number | null
  bandwidth_up?: number | null
  description?: string | null
}

interface TenantItem {
  id: number
  name: string
  slug: string
  logo_url?: string
  logo?: string
  phone?: string
}

interface VoucherLoginProps {
  tenantId?: number | null
  currentTenant?: TenantItem | null
  availableTenants?: TenantItem[]
  tenantName: string
  tenantLogo?: string
  hotspotName: string
  hotspotDns: string
  packages: Package[]
  qrisImageUrl?: string | null
  qrisConfig?: {
    merchant_name?: string
    is_active?: boolean
  }
  companyPhone?: string
}

interface VoucherCheckResult {
  username: string
  password: string
  profile: string
  time_limit: string
  data_limit?: string | number | null
  price: number
  used: boolean
  is_online: boolean
  active_session?: {
    ip: string
    mac: string
    uptime: string
    bytes_in: number
    bytes_out: number
  } | null
}

interface OrderData {
  id: number
  order_number: string
  package_name: string
  customer_name?: string
  customer_phone: string
  amount: number
  unique_code: number
  total_amount: number
  payment_method: string
  payment_status: "unpaid" | "paid" | "cancelled" | "expired"
  voucher_code?: string
  voucher_password?: string
  voucher_timelimit?: string
  voucher_datalimit?: string
  connect_url?: string
}

export default function VoucherLoginPage({
  tenantId,
  currentTenant,
  availableTenants = [],
  tenantName,
  tenantLogo = "/images/logo-white.png?v=36",
  hotspotName,
  hotspotDns = "nodera.login",
  packages = [],
  qrisImageUrl,
  companyPhone = "+6285155173547",
}: VoucherLoginProps) {
  const [activeTab, setActiveTab] = useState<"buy" | "check">("buy")
  const [tenantSelectorOpen, setTenantSelectorOpen] = useState(false)

  // Buy form state
  const [selectedPkgId, setSelectedPkgId] = useState<number>(packages[0]?.id || 1)
  const [customerPhone, setCustomerPhone] = useState("")
  const [customerName, setCustomerName] = useState("")
  const [paymentMethod, setPaymentMethod] = useState<"qris" | "cash">("qris")
  const [isOrdering, setIsOrdering] = useState(false)
  const [buyError, setBuyError] = useState<string | null>(null)

  // Order & Modal state
  const [activeOrder, setActiveOrder] = useState<OrderData | null>(null)
  const [isModalOpen, setIsModalOpen] = useState(false)
  const [copiedCode, setCopiedCode] = useState(false)
  const [copiedNominal, setCopiedNominal] = useState(false)
  const [isVerifying, setIsVerifying] = useState(false)

  // Check voucher state
  const [checkCode, setCheckCode] = useState("")
  const [isChecking, setIsChecking] = useState(false)
  const [checkResult, setCheckResult] = useState<VoucherCheckResult | null>(null)
  const [checkError, setCheckError] = useState<string | null>(null)
  const [checkConnectUrl, setCheckConnectUrl] = useState<string | null>(null)

  const selectedPackage = packages.find((p) => p.id === selectedPkgId) || packages[0]

  // Poll order status when modal is open and order is unpaid
  useEffect(() => {
    if (!isModalOpen || !activeOrder || activeOrder.payment_status === "paid") return

    const interval = setInterval(async () => {
      try {
        const res = await fetch(`/voucher/order/${activeOrder.order_number}/status`)
        const data = await res.json()
        if (data.success && data.order) {
          if (data.order.payment_status === "paid") {
            setActiveOrder(data.order)
          }
        }
      } catch (err) {
        // silent fail during polling
      }
    }, 3000)

    return () => clearInterval(interval)
  }, [isModalOpen, activeOrder])

  const handleBuySubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    setBuyError(null)

    if (!customerPhone.trim()) {
      setBuyError("Mohon masukkan nomor WhatsApp / HP Anda.")
      return
    }

    setIsOrdering(true)
    try {
      const res = await fetch("/voucher/buy", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "X-CSRF-TOKEN": (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || "",
        },
        body: JSON.stringify({
          package_id: selectedPkgId,
          customer_phone: customerPhone.trim(),
          customer_name: customerName.trim(),
          payment_method: paymentMethod,
          tenant_id: currentTenant?.id || tenantId,
          tenant: currentTenant?.slug,
        }),
      })

      const data = await res.json()
      if (data.success && data.order) {
        setActiveOrder(data.order)
        setIsModalOpen(true)
      } else {
        setBuyError(data.message || "Gagal membuat pesanan voucher. Coba lagi.")
      }
    } catch (err: any) {
      setBuyError(err.message || "Terjadi kesalahan jaringan.")
    } finally {
      setIsOrdering(false)
    }
  }

  const handleCheckSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    setCheckError(null)
    setCheckResult(null)
    setCheckConnectUrl(null)

    if (!checkCode.trim()) {
      setCheckError("Masukkan kode voucher terlebih dahulu.")
      return
    }

    setIsChecking(true)
    try {
      const res = await fetch("/voucher/check", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "X-CSRF-TOKEN": (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || "",
        },
        body: JSON.stringify({
          code: checkCode.trim(),
          tenant_id: currentTenant?.id || tenantId,
          tenant: currentTenant?.slug,
        }),
      })

      const data = await res.json()
      if (data.success && data.voucher) {
        setCheckResult(data.voucher)
        setCheckConnectUrl(data.connect_url)
      } else {
        setCheckError(data.message || "Kode voucher tidak ditemukan atau tidak valid.")
      }
    } catch (err: any) {
      setCheckError(err.message || "Gagal memeriksa status voucher.")
    } finally {
      setIsChecking(false)
    }
  }

  const handleManualVerify = async () => {
    if (!activeOrder) return
    setIsVerifying(true)
    try {
      const res = await fetch(`/voucher/order/${activeOrder.order_number}/auto-verify`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "X-CSRF-TOKEN": (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || "",
        },
      })
      const data = await res.json()
      if (data.success && data.order) {
        setActiveOrder(data.order)
      }
    } catch (err) {
      // ignore
    } finally {
      setIsVerifying(false)
    }
  }

  const copyToClipboard = (text: string, type: "code" | "nominal") => {
    navigator.clipboard.writeText(text)
    if (type === "code") {
      setCopiedCode(true)
      setTimeout(() => setCopiedCode(false), 2000)
    } else {
      setCopiedNominal(true)
      setTimeout(() => setCopiedNominal(false), 2000)
    }
  }

  const formatRupiah = (val: number) => {
    return new Intl.NumberFormat("id-ID", {
      style: "currency",
      currency: "IDR",
      maximumFractionDigits: 0,
    }).format(val)
  }

  return (
    <>
      <Head title={`Beli & Masuk Voucher WiFi - ${hotspotName}`} />

      <div className="min-h-screen bg-[#070D12] text-slate-100 flex flex-col justify-between selection:bg-cyan-500 selection:text-black">
        {/* Background glow ambient */}
        <div className="fixed inset-0 pointer-events-none overflow-hidden -z-10">
          <div className="absolute -top-40 left-1/2 -translate-x-1/2 w-[700px] h-[450px] bg-gradient-to-b from-cyan-500/20 via-blue-600/10 to-transparent blur-[120px] rounded-full" />
          <div className="absolute bottom-0 right-0 w-[400px] h-[400px] bg-emerald-500/10 blur-[130px] rounded-full" />
        </div>

        {/* ================= HEADER NAVBAR ================= */}
        <header className="border-b border-white/10 bg-[#0B131A]/80 backdrop-blur-md sticky top-0 z-40">
          <div className="max-w-7xl 2xl:max-w-[1700px] mx-auto px-4 sm:px-6 h-16 flex items-center justify-between">
            <div className="flex items-center gap-3">
              <div className="w-10 h-10 rounded-2xl bg-cyan-500/20 border border-cyan-500/30 p-1.5 flex items-center justify-center">
                <img src={tenantLogo} alt={tenantName} className="w-full h-full object-contain" />
              </div>
              <div>
                <div className="flex items-center gap-2">
                  <span className="font-extrabold text-white text-base tracking-tight">{hotspotName}</span>
                  <span className="text-[10px] uppercase font-bold tracking-wider bg-cyan-500/20 text-cyan-400 border border-cyan-500/30 px-2 py-0.5 rounded-full flex items-center gap-1">
                    <Wifi className="w-2.5 h-2.5" /> Hotspot
                  </span>
                </div>
                <p className="text-[11px] text-slate-400">Portal Akses Internet &amp; Voucher</p>
              </div>
            </div>

            <div className="flex items-center gap-2 sm:gap-3">
              <LanguageSwitcher />
              <Link
                href="/pelanggan/login"
                className="hidden sm:inline-flex items-center gap-1.5 text-xs font-semibold text-slate-300 hover:text-white bg-white/5 hover:bg-white/10 border border-white/10 px-3 py-2 rounded-xl transition-all"
              >
                <Building2 className="w-3.5 h-3.5 text-cyan-400" />
                <span>Portal PPPoE / Bulanan</span>
              </Link>
            </div>
          </div>
        </header>

        {/* ================= MAIN CONTENT ================= */}
        <main className="max-w-4xl mx-auto px-4 sm:px-6 py-8 sm:py-12 w-full flex-1">
          {/* Hero text */}
          <div className="text-center space-y-3 mb-8">
            <div className="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-cyan-500/10 border border-cyan-500/30 text-cyan-400 text-xs font-semibold">
              <Play className="h-3.5 w-3.5" />
              <span>Internet WiFi Cepat &amp; Kuota Stabil</span>
            </div>
            <h1 className="text-2xl sm:text-4xl font-extrabold text-white tracking-tight">
              Beli &amp; Hubungkan Voucher WiFi
            </h1>
            <p className="text-sm text-slate-400 max-w-lg mx-auto leading-relaxed">
              Beli voucher instan dengan QRIS atau masukkan kode voucher yang sudah Anda miliki untuk langsung terhubung ke internet.
            </p>
          </div>

          {/* Switch Tab Card */}
          <div className="flex p-1.5 bg-[#0D1822] border border-white/10 rounded-2xl max-w-md mx-auto mb-8 shadow-inner">
            <button
              onClick={() => setActiveTab("buy")}
              className={`flex-1 flex items-center justify-center gap-2 py-3 rounded-xl font-bold text-xs sm:text-sm transition-all ${
                activeTab === "buy"
                  ? "bg-gradient-to-r from-cyan-500 to-blue-600 text-white"
                  : "text-slate-400 hover:text-white hover:bg-white/5"
              }`}
            >
              <Play className="w-4 h-4" />
              <span>Beli Voucher Online</span>
            </button>
            <button
              onClick={() => setActiveTab("check")}
              className={`flex-1 flex items-center justify-center gap-2 py-3 rounded-xl font-bold text-xs sm:text-sm transition-all ${
                activeTab === "check"
                  ? "bg-gradient-to-r from-cyan-500 to-blue-600 text-white"
                  : "text-slate-400 hover:text-white hover:bg-white/5"
              }`}
            >
              <KeyRound className="w-4 h-4" />
              <span>Cek / Masuk Kode</span>
            </button>
          </div>

          {/* ================= TAB 1: BELI VOUCHER ONLINE ================= */}
          {activeTab === "buy" && (
            <div className="space-y-8 animate-in fade-in slide-in-from-bottom-2 duration-300">
              {/* Step 1: Package Selection */}
              <div className="space-y-4">
                <div className="flex items-center justify-between">
                  <div className="flex items-center gap-2">
                    <span className="w-6 h-6 rounded-full bg-cyan-500/20 text-cyan-400 text-xs font-bold flex items-center justify-center border border-cyan-500/30">
                      1
                    </span>
                    <h2 className="text-base font-bold text-white">Pilih Paket Voucher</h2>
                  </div>
                  <span className="text-xs text-slate-400">Pilih durasi sesuai kebutuhan</span>
                </div>

                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3.5">
                  {packages.map((pkg) => {
                    const isSelected = selectedPkgId === pkg.id
                    return (
                      <button
                        key={pkg.id}
                        type="button"
                        onClick={() => setSelectedPkgId(pkg.id)}
                        className={`relative p-5 rounded-2xl text-left transition-all border ${
                          isSelected
                            ? "bg-cyan-500/10 border-cyan-500 shadow-sm shadow-cyan-500/15 scale-[1.02]"
                            : "bg-[#0B131A] border-white/10 hover:border-white/20 hover:bg-[#0E1822]"
                        }`}
                      >
                        {isSelected && (
                          <div className="absolute top-3 right-3 w-6 h-6 rounded-full bg-cyan-500 text-black flex items-center justify-center">
                            <Check className="w-3.5 h-3.5 stroke-[3]" />
                          </div>
                        )}

                        <div className="space-y-2">
                          <div className="inline-block px-2.5 py-0.5 rounded-lg bg-white/10 text-[11px] font-bold text-slate-200 uppercase tracking-wider">
                            {pkg.time_limit || "Akses WiFi"}
                          </div>

                          <h3 className="font-bold text-lg text-white">{pkg.name}</h3>

                          <div className="text-xl font-extrabold text-cyan-400">
                            {formatRupiah(pkg.price)}
                          </div>

                          <div className="pt-2 border-t border-white/10 space-y-1 text-xs text-slate-400">
                            <div className="flex items-center gap-1.5">
                              <Gauge className="w-3.5 h-3.5 text-cyan-400" />
                              <span>
                                {pkg.bandwidth_down ? `Speed s/d ${pkg.bandwidth_down} Mbps` : "Kecepatan Maksimal"}
                              </span>
                            </div>
                            <div className="flex items-center gap-1.5">
                              <Wifi className="w-3.5 h-3.5 text-emerald-400" />
                              <span>{pkg.data_limit ? `Kuota ${pkg.data_limit}` : "Kuota Unlimited"}</span>
                            </div>
                          </div>
                        </div>
                      </button>
                    )
                  })}
                </div>
              </div>

              {/* Step 2: Customer Data & Payment */}
              <form onSubmit={handleBuySubmit} className="space-y-6">
                <div className="bg-[#0B131A] border border-white/10 rounded-2xl p-6 sm:p-8 space-y-6 shadow-sm">
                  <div className="flex items-center gap-2">
                    <span className="w-6 h-6 rounded-full bg-cyan-500/20 text-cyan-400 text-xs font-bold flex items-center justify-center border border-cyan-500/30">
                      2
                    </span>
                    <h2 className="text-base font-bold text-white">Informasi Pembeli &amp; Pembayaran</h2>
                  </div>

                  {buyError && (
                    <div className="p-4 rounded-2xl bg-rose-500/10 border border-rose-500/30 text-rose-400 text-xs flex items-center gap-2.5">
                      <AlertCircle className="w-4 h-4 shrink-0" />
                      <span>{buyError}</span>
                    </div>
                  )}

                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div className="space-y-2">
                      <Label className="text-xs font-semibold text-slate-300">
                        Nomor WhatsApp / HP <span className="text-rose-400">*</span>
                      </Label>
                      <div className="relative">
                        <Input
                          type="tel"
                          placeholder="08123456789"
                          value={customerPhone}
                          onChange={(e) => setCustomerPhone(e.target.value)}
                          className="bg-[#070D12] border-white/10 text-white rounded-xl pl-10 focus:border-cyan-500"
                          required
                        />
                        <Smartphone className="w-4 h-4 text-slate-500 absolute left-3 top-1/2 -translate-y-1/2" />
                      </div>
                      <p className="text-[11px] text-slate-500">
                        Kode voucher &amp; link login otomatis akan dikirim ke WhatsApp ini.
                      </p>
                    </div>

                    <div className="space-y-2">
                      <Label className="text-xs font-semibold text-slate-300">Nama Pembeli (Opsional)</Label>
                      <Input
                        type="text"
                        placeholder="Contoh: Budi"
                        value={customerName}
                        onChange={(e) => setCustomerName(e.target.value)}
                        className="bg-[#070D12] border-white/10 text-white rounded-xl focus:border-cyan-500"
                      />
                    </div>
                  </div>

                  {/* Payment method selector */}
                  <div className="space-y-3">
                    <Label className="text-xs font-semibold text-slate-300">Pilih Metode Pembayaran</Label>
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                      <label
                        className={`flex items-center gap-3 p-4 rounded-2xl border cursor-pointer transition-all ${
                          paymentMethod === "qris"
                            ? "bg-cyan-500/10 border-cyan-500 text-white"
                            : "bg-[#070D12] border-white/10 text-slate-300 hover:border-white/20"
                        }`}
                      >
                        <input
                          type="radio"
                          name="paymentMethod"
                          checked={paymentMethod === "qris"}
                          onChange={() => setPaymentMethod("qris")}
                          className="sr-only"
                        />
                        <div className="w-8 h-8 rounded-xl bg-cyan-500/20 text-cyan-400 flex items-center justify-center shrink-0">
                          <QrCode className="w-4 h-4" />
                        </div>
                        <div className="flex-1 min-w-0">
                          <div className="font-bold text-sm">QRIS Realtime</div>
                          <div className="text-[11px] text-slate-400">BCA, Mandiri, BRI, Dana, GoPay, OVO, Shopee</div>
                        </div>
                        <div className={`w-4 h-4 rounded-full border flex items-center justify-center ${paymentMethod === "qris" ? "border-cyan-400 bg-cyan-400" : "border-slate-600"}`}>
                          {paymentMethod === "qris" && <div className="w-1.5 h-1.5 rounded-full bg-black" />}
                        </div>
                      </label>

                      <label
                        className={`flex items-center gap-3 p-4 rounded-2xl border cursor-pointer transition-all ${
                          paymentMethod === "cash"
                            ? "bg-cyan-500/10 border-cyan-500 text-white"
                            : "bg-[#070D12] border-white/10 text-slate-300 hover:border-white/20"
                        }`}
                      >
                        <input
                          type="radio"
                          name="paymentMethod"
                          checked={paymentMethod === "cash"}
                          onChange={() => setPaymentMethod("cash")}
                          className="sr-only"
                        />
                        <div className="w-8 h-8 rounded-xl bg-emerald-500/20 text-emerald-400 flex items-center justify-center shrink-0">
                          <CreditCard className="w-4 h-4" />
                        </div>
                        <div className="flex-1 min-w-0">
                          <div className="font-bold text-sm">Bayar ke Kasir / Tunai</div>
                          <div className="text-[11px] text-slate-400">Langsung aktif melalui kasir atau gratis demo</div>
                        </div>
                        <div className={`w-4 h-4 rounded-full border flex items-center justify-center ${paymentMethod === "cash" ? "border-cyan-400 bg-cyan-400" : "border-slate-600"}`}>
                          {paymentMethod === "cash" && <div className="w-1.5 h-1.5 rounded-full bg-black" />}
                        </div>
                      </label>
                    </div>
                  </div>

                  {/* Summary & Submit */}
                  <div className="pt-4 border-t border-white/10 flex flex-col sm:flex-row items-center justify-between gap-4">
                    <div>
                      <div className="text-xs text-slate-400">Total Pembayaran:</div>
                      <div className="text-2xl font-extrabold text-white">
                        {formatRupiah(selectedPackage?.price || 0)}
                      </div>
                    </div>

                    <Button
                      type="submit"
                      disabled={isOrdering}
                      className="w-full sm:w-auto px-8 py-6 rounded-2xl bg-gradient-to-r from-cyan-500 to-blue-600 hover:from-cyan-400 hover:to-blue-500 text-white font-bold text-base shadow-sm shadow-cyan-500/25 transition-all flex items-center justify-center gap-2"
                    >
                      {isOrdering ? (
                        <>
                          <RefreshCw className="w-4 h-4 animate-spin" />
                          <span>Memproses Pesanan...</span>
                        </>
                      ) : (
                        <>
                          <span>Lanjutkan Pembayaran</span>
                          <ArrowRight className="w-4 h-4" />
                        </>
                      )}
                    </Button>
                  </div>
                </div>
              </form>
            </div>
          )}

          {/* ================= TAB 2: CEK & MASUK KODE VOUCHER ================= */}
          {activeTab === "check" && (
            <div className="space-y-6 animate-in fade-in slide-in-from-bottom-2 duration-300 max-w-xl mx-auto">
              <div className="bg-[#0B131A] border border-white/10 rounded-2xl p-6 sm:p-8 space-y-6 shadow-sm">
                <div>
                  <h2 className="text-lg font-bold text-white">Cek Status Voucher Anda</h2>
                  <p className="text-xs text-slate-400 mt-1">
                    Masukkan kode voucher yang tertera pada struk pembelian atau pesan WhatsApp.
                  </p>
                </div>

                {checkError && (
                  <div className="p-4 rounded-2xl bg-rose-500/10 border border-rose-500/30 text-rose-400 text-xs flex items-center gap-2.5">
                    <AlertCircle className="w-4 h-4 shrink-0" />
                    <span>{checkError}</span>
                  </div>
                )}

                <form onSubmit={handleCheckSubmit} className="space-y-4">
                  <div className="space-y-2">
                    <Label className="text-xs font-semibold text-slate-300">Kode Voucher</Label>
                    <div className="relative">
                      <Input
                        type="text"
                        placeholder="Contoh: A9B8C7"
                        value={checkCode}
                        onChange={(e) => setCheckCode(e.target.value.toUpperCase())}
                        className="bg-[#070D12] border-white/10 text-white font-mono uppercase tracking-widest text-lg h-14 pl-12 rounded-2xl focus:border-cyan-500"
                        required
                      />
                      <KeyRound className="w-5 h-5 text-cyan-400 absolute left-4 top-1/2 -translate-y-1/2" />
                    </div>
                  </div>

                  <Button
                    type="submit"
                    disabled={isChecking}
                    className="w-full py-6 rounded-2xl bg-gradient-to-r from-cyan-500 to-blue-600 hover:from-cyan-400 hover:to-blue-500 text-white font-bold text-base shadow-sm shadow-cyan-500/25 transition-all flex items-center justify-center gap-2"
                  >
                    {isChecking ? (
                      <>
                        <RefreshCw className="w-4 h-4 animate-spin" />
                        <span>Mengecek Voucher...</span>
                      </>
                    ) : (
                      <>
                        <Wifi className="w-4 h-4" />
                        <span>Cek &amp; Hubungkan WiFi</span>
                      </>
                    )}
                  </Button>
                </form>

                {/* Result box */}
                {checkResult && (
                  <div className="mt-6 p-5 rounded-2xl bg-white/[0.04] border border-white/15 space-y-4 animate-in fade-in">
                    <div className="flex items-center justify-between">
                      <span className="text-xs text-slate-400">Status Voucher:</span>
                      {checkResult.is_online ? (
                        <span className="px-2.5 py-1 rounded-full bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 text-xs font-bold flex items-center gap-1.5">
                          <span className="w-2 h-2 rounded-full bg-emerald-400 animate-ping" />
                          Online / Aktif Terhubung
                        </span>
                      ) : checkResult.used ? (
                        <span className="px-2.5 py-1 rounded-full bg-slate-500/20 text-slate-400 border border-slate-500/30 text-xs font-bold">
                          Sudah Digunakan
                        </span>
                      ) : (
                        <span className="px-2.5 py-1 rounded-full bg-cyan-500/20 text-cyan-400 border border-cyan-500/30 text-xs font-bold flex items-center gap-1.5">
                          <CheckCircle2 className="w-3.5 h-3.5" />
                          Siap Digunakan
                        </span>
                      )}
                    </div>

                    <div className="grid grid-cols-2 gap-3 text-xs">
                      <div className="bg-[#070D12] p-3 rounded-xl border border-white/5 space-y-1">
                        <span className="text-slate-500 text-[11px]">Profil Paket:</span>
                        <div className="font-bold text-white">{checkResult.profile}</div>
                      </div>
                      <div className="bg-[#070D12] p-3 rounded-xl border border-white/5 space-y-1">
                        <span className="text-slate-500 text-[11px]">Masa Berlaku:</span>
                        <div className="font-bold text-cyan-400">{checkResult.time_limit}</div>
                      </div>
                    </div>

                    {checkResult.active_session && (
                      <div className="bg-[#070D12] p-3.5 rounded-xl border border-cyan-500/20 space-y-2 text-xs">
                        <div className="font-semibold text-cyan-300 flex items-center gap-1.5">
                          <ShieldCheck className="w-4 h-4 text-cyan-400" />
                          <span>Sesi Aktif di Jaringan:</span>
                        </div>
                        <div className="grid grid-cols-2 gap-2 text-[11px] text-slate-400">
                          <div>IP: <span className="text-white font-mono">{checkResult.active_session.ip}</span></div>
                          <div>Uptime: <span className="text-white font-mono">{checkResult.active_session.uptime}</span></div>
                          <div className="col-span-2">MAC: <span className="text-white font-mono">{checkResult.active_session.mac}</span></div>
                        </div>
                      </div>
                    )}

                    {checkConnectUrl && (
                      <a
                        href={checkConnectUrl}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="w-full py-3.5 rounded-xl bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-bold text-sm transition-all flex items-center justify-center gap-2 block text-center"
                      >
                        <ExternalLink className="w-4 h-4" />
                        <span>Login ke Jaringan WiFi Sekarang</span>
                      </a>
                    )}
                  </div>
                )}
              </div>
            </div>
          )}
        </main>

        {/* ================= PAYMENT MODAL / SUCCESS OVERLAY ================= */}
        {isModalOpen && activeOrder && (
          <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-md animate-in fade-in duration-200">
            <div className="relative w-full max-w-lg rounded-2xl bg-[#0B131A] border border-white/15 p-6 sm:p-8 shadow-sm space-y-6">
              {/* Close Button */}
              <button
                onClick={() => setIsModalOpen(false)}
                className="absolute top-5 right-5 p-2 rounded-full bg-white/5 hover:bg-white/10 text-slate-400 hover:text-white"
              >
                <X className="w-5 h-5" />
              </button>

              {/* UNPAID SCREEN (QRIS PAYMENT) */}
              {activeOrder.payment_status === "unpaid" ? (
                <div className="space-y-6">
                  <div>
                    <div className="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-md bg-amber-500/20 text-amber-400 border border-amber-500/30 text-[11px] font-bold mb-2">
                      <Clock className="w-3 h-3" /> Menunggu Pembayaran
                    </div>
                    <h3 className="text-xl font-bold text-white">Scan QRIS untuk Membayar</h3>
                    <p className="text-xs text-slate-400 mt-1">
                      Pesanan <span className="font-mono text-cyan-400">{activeOrder.order_number}</span> • {activeOrder.package_name}
                    </p>
                  </div>

                  {/* QRIS Code Image Box */}
                  <div className="flex flex-col items-center justify-center p-6 rounded-2xl bg-white text-slate-900 shadow-sm space-y-3">
                    {qrisImageUrl ? (
                      <img
                        src={qrisImageUrl}
                        alt="QRIS Pembayaran"
                        className="w-56 h-56 object-contain rounded-lg"
                      />
                    ) : (
                      <div className="w-56 h-56 rounded-lg bg-slate-100 flex flex-col items-center justify-center text-center p-4 border-2 border-dashed border-slate-300">
                        <QrCode className="w-16 h-16 text-slate-400 mb-2" />
                        <span className="text-xs font-semibold text-slate-600">Scan QRIS Kasir / Merchant</span>
                      </div>
                    )}
                    <span className="text-[11px] font-semibold text-slate-500 uppercase tracking-wider">
                      Mendukung Semua E-Wallet &amp; Mobile Banking
                    </span>
                  </div>

                  {/* Nominal info */}
                  <div className="p-4 rounded-2xl bg-[#070D12] border border-white/10 flex items-center justify-between">
                    <div>
                      <span className="text-xs text-slate-400">Total Nominal Pembayaran:</span>
                      <div className="text-xl font-extrabold text-cyan-400">
                        {formatRupiah(activeOrder.total_amount)}
                      </div>
                    </div>

                    <button
                      type="button"
                      onClick={() => copyToClipboard(String(Math.round(activeOrder.total_amount)), "nominal")}
                      className="px-3 py-2 rounded-xl bg-white/5 hover:bg-white/10 border border-white/10 text-xs font-semibold text-white flex items-center gap-1.5 transition-all"
                    >
                      {copiedNominal ? (
                        <>
                          <Check className="w-3.5 h-3.5 text-emerald-400" />
                          <span className="text-emerald-400">Tersalin</span>
                        </>
                      ) : (
                        <>
                          <Copy className="w-3.5 h-3.5" />
                          <span>Salin Jumlah</span>
                        </>
                      )}
                    </button>
                  </div>

                  {/* Polling bar & verify action */}
                  <div className="space-y-3">
                    <div className="flex items-center justify-center gap-2 text-xs text-slate-400">
                      <RefreshCw className="w-3.5 h-3.5 animate-spin text-cyan-400" />
                      <span>Sistem otomatis mendeteksi pembayaran Anda...</span>
                    </div>

                    <Button
                      type="button"
                      onClick={handleManualVerify}
                      disabled={isVerifying}
                      className="w-full py-5 rounded-2xl bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-bold text-sm transition-all flex items-center justify-center gap-2"
                    >
                      {isVerifying ? (
                        <>
                          <RefreshCw className="w-4 h-4 animate-spin" />
                          <span>Memeriksa...</span>
                        </>
                      ) : (
                        <>
                          <CheckCircle2 className="w-4 h-4" />
                          <span>Saya Sudah Membayar</span>
                        </>
                      )}
                    </Button>
                  </div>
                </div>
              ) : (
                /* PAID SCREEN / CELEBRATION VOUCHER CODE */
                <div className="space-y-6 text-center animate-in zoom-in-95 duration-200">
                  <div className="w-16 h-16 rounded-2xl bg-emerald-500/20 border border-emerald-500/30 text-emerald-400 mx-auto flex items-center justify-center shadow-sm shadow-emerald-500/20">
                    <CheckCircle2 className="w-8 h-8 stroke-[2.5]" />
                  </div>

                  <div className="space-y-1">
                    <h3 className="text-2xl font-extrabold text-white">Pembayaran Berhasil!</h3>
                    <p className="text-xs text-slate-400">
                      Voucher WiFi Anda telah aktif dan siap digunakan sekarang.
                    </p>
                  </div>

                  {/* Big voucher code card */}
                  <div className="p-6 rounded-2xl bg-gradient-to-b from-cyan-500/15 via-blue-600/10 to-transparent border border-cyan-500/30 space-y-4">
                    <div className="text-xs font-semibold text-cyan-300 uppercase tracking-wider">
                      Kode Voucher WiFi Anda
                    </div>

                    <div className="text-3xl sm:text-4xl font-mono font-extrabold text-white tracking-widest bg-black/40 py-3.5 px-4 rounded-2xl border border-white/10 select-all">
                      {activeOrder.voucher_code || "KODE-AKTIF"}
                    </div>

                    <div className="flex items-center justify-center gap-2">
                      <button
                        type="button"
                        onClick={() => copyToClipboard(activeOrder.voucher_code || "", "code")}
                        className="px-4 py-2.5 rounded-xl bg-cyan-500/20 hover:bg-cyan-500/30 text-cyan-300 border border-cyan-500/30 text-xs font-bold flex items-center gap-1.5 transition-all"
                      >
                        {copiedCode ? (
                          <>
                            <Check className="w-3.5 h-3.5 text-emerald-400" />
                            <span className="text-emerald-400">Kode Tersalin!</span>
                          </>
                        ) : (
                          <>
                            <Copy className="w-3.5 h-3.5" />
                            <span>Salin Kode Voucher</span>
                          </>
                        )}
                      </button>
                    </div>

                    <div className="pt-2 border-t border-white/10 text-xs text-slate-400 flex items-center justify-around">
                      <span>Paket: <strong className="text-white">{activeOrder.package_name}</strong></span>
                      <span>Masa Berlaku: <strong className="text-cyan-400">{activeOrder.voucher_timelimit || "Aktif"}</strong></span>
                    </div>
                  </div>

                  {/* Direct connect button */}
                  <div className="space-y-3">
                    {activeOrder.connect_url ? (
                      <a
                        href={activeOrder.connect_url}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="w-full py-4 rounded-2xl bg-gradient-to-r from-emerald-500 to-teal-600 hover:from-emerald-400 hover:to-teal-500 text-slate-950 font-extrabold text-sm shadow-sm shadow-emerald-500/25 transition-all flex items-center justify-center gap-2 block text-center"
                      >
                        <ExternalLink className="w-4 h-4" />
                        <span>Hubungkan ke WiFi Sekarang</span>
                      </a>
                    ) : (
                      <a
                        href={`http://${hotspotDns}/login?username=${activeOrder.voucher_code}&password=${activeOrder.voucher_password || activeOrder.voucher_code}`}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="w-full py-4 rounded-2xl bg-gradient-to-r from-emerald-500 to-teal-600 hover:from-emerald-400 hover:to-teal-500 text-slate-950 font-extrabold text-sm shadow-sm shadow-emerald-500/25 transition-all flex items-center justify-center gap-2 block text-center"
                      >
                        <ExternalLink className="w-4 h-4" />
                        <span>Hubungkan ke WiFi Sekarang</span>
                      </a>
                    )}

                    <p className="text-[11px] text-slate-400 flex items-center justify-center gap-1.5">
                      <MessageCircle className="w-3.5 h-3.5 text-emerald-400" />
                      <span>Rincian voucher telah dikirim otomatis ke nomor WhatsApp Anda.</span>
                    </p>
                  </div>
                </div>
              )}
            </div>
          </div>
        )}

        {/* ================= FOOTER ================= */}
        <footer className="border-t border-white/10 bg-[#0B131A] py-6 text-center text-xs text-slate-500">
          <div className="max-w-7xl 2xl:max-w-[1700px] mx-auto px-4 space-y-2">
            <div className="flex items-center justify-center gap-4 text-slate-400 text-xs">
              <a
                href={`https://wa.me/${companyPhone.replace(/[^0-9]/g, "").replace(/^0/, "62")}`}
                target="_blank"
                rel="noopener noreferrer"
                className="hover:text-cyan-400 flex items-center gap-1.5 transition-colors"
              >
                <MessageCircle className="w-3.5 h-3.5 text-emerald-400" />
                <span>Bantuan WhatsApp</span>
              </a>
              <span>•</span>
              <Link href="/pelanggan/login" className="hover:text-cyan-400 transition-colors">
                Portal Pelanggan PPPoE
              </Link>
            </div>
            <div>
              © {new Date().getFullYear()} {tenantName}. Powered by <strong>NODERA Hotspot Engine</strong>.
            </div>
          </div>
        </footer>
      </div>
    </>
  )
}
