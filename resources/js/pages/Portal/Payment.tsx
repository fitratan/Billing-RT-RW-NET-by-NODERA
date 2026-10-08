import { AppLayout } from "@/components/layout/app-layout"
import {
  Landmark,
  QrCode,
  Wallet,
  Copy,
  Check,
  CreditCard,
  AlertCircle,
  ChevronLeft,
  Clock,
  LogOut,
  KeyRound,
  MessageCircle,
  ShieldCheck,
  Phone,
  Loader2,
  RefreshCw,
  ExternalLink,
  ChevronRight,
  Store,
  CheckCircle2,
  AlertTriangle,
  X,
} from "lucide-react"
import { PageProps } from "@/types"
import { formatCurrency, formatDate, cn } from "@/lib/utils"
import { router, Link } from "@inertiajs/react"
import { useState, useEffect, useMemo } from "react"
import { Button } from "@/components/ui/button"
import { Badge } from "@/components/ui/badge"
import { QrisFintechCheckout } from "@/components/QrisFintechCheckout"
import { portalNavItems, portalBrand } from "@/lib/portal-nav"

interface Channel {
  code: string
  name: string
  group: string
  fee_flat: number
  fee_percent: number
}

interface BankAccount {
  bank_name: string
  account_number: string
  account_name: string
}

interface ActivePayment {
  id: number
  method: string
  gateway?: string
  gateway_ref?: string
  channel?: string
  va_number?: string | null
  pay_code?: string | null
  qr_string?: string | null
  qr_image?: string | null
  total_amount: number
  expired_at?: string | null
  instructions?: any
  checkout_url?: string | null
  created_at?: string | null
}

type PaymentMethodId = "qris" | "bank" | "gateway"

export default function PortalPaymentPage({
  invoice,
  customer,
  paymentMethods,
  activePayment: initialActivePayment = null,
  qrisPayload = null,
  uniqueAmount = 0,
  enableQris = true,
  enableBank = true,
  enableGateway = true,
  companyName = "NODERA ISP",
  adminWa,
}: PageProps<{
  invoice: {
    id: number
    invoice_number: string
    amount: number
    unique_amount?: number
    paid: boolean
    period: string | null
    due_date: string | null
    payment_method?: string | null
  }
  customer: {
    id: number
    name: string
    pppoe_username: string | null
    phone: string | null
    code: string | null
    package: string | null
  }
  paymentMethods?: {
    qris?: { image_url?: string; static_qr?: string } | null
    bank_accounts?: BankAccount[]
    channels?: Channel[]
    gateway_configured?: boolean
  } | null
  activePayment?: ActivePayment | null
  qrisPayload?: any
  uniqueAmount?: number
  enableQris?: boolean
  enableBank?: boolean
  enableGateway?: boolean
  companyName?: string
  adminWa?: string
}>) {
  const [activePayment, setActivePayment] = useState<ActivePayment | null>(initialActivePayment)
  const [isPaid, setIsPaid] = useState<boolean>(Boolean(invoice.paid))
  const [loading, setLoading] = useState<boolean>(false)
  const [selectedChannel, setSelectedChannel] = useState<string>("")
  const [copiedKey, setCopiedKey] = useState<string | null>(null)
  const [errorMsg, setErrorMsg] = useState<string | null>(null)

  const bankAccounts = paymentMethods?.bank_accounts || []
  const channels = paymentMethods?.channels || []
  const gatewayConfigured = Boolean(paymentMethods?.gateway_configured)
  const cleanAdminWa = (adminWa || "").replace(/[^0-9]/g, "")

  const isQrisActive = Boolean(
    activePayment &&
      !activePayment.va_number &&
      !activePayment.pay_code &&
      (activePayment.qr_image || activePayment.qr_string || (activePayment.channel && activePayment.channel.toUpperCase().includes("QRIS")))
  )

  const paymentOptions = useMemo(() => {
    const list: { id: PaymentMethodId; label: string; desc: string; icon: any }[] = []
    if (enableQris) {
      list.push({
        id: "qris",
        label: "QRIS Instan",
        desc: "Scan otomatis via BCA, GoPay, OVO, DANA, ShopeePay & Semua Bank",
        icon: QrCode,
      })
    }
    if (enableGateway && (gatewayConfigured || channels.length > 0 || activePayment)) {
      list.push({
        id: "gateway",
        label: "Virtual Account & Retail",
        desc: "BCA, BRI, Mandiri, BNI, Permata, Alfamart & Indomaret",
        icon: CreditCard,
      })
    }
    if (enableBank && bankAccounts.length > 0) {
      list.push({
        id: "bank",
        label: "Transfer Bank Manual",
        desc: "Transfer manual ke rekening bank admin & konfirmasi bukti via WhatsApp",
        icon: Landmark,
      })
    }
    return list
  }, [enableQris, enableGateway, gatewayConfigured, channels.length, activePayment, enableBank, bankAccounts.length])

  const [activeTab, setActiveTab] = useState<PaymentMethodId>(() => {
    if (activePayment) return "gateway"
    return paymentOptions[0]?.id || "qris"
  })

  // Real-time payment verification polling
  useEffect(() => {
    if (isPaid) return
    const interval = setInterval(async () => {
      try {
        const res = await fetch(`/portal/payment/${invoice.id}/status`)
        if (res.ok) {
          const data = await res.json()
          if (data.paid) {
            setIsPaid(true)
            router.reload()
          }
        }
      } catch {
        // Silent poll error
      }
    }, 4000)
    return () => clearInterval(interval)
  }, [invoice.id, isPaid])

  const copyText = (val: string, key: string) => {
    navigator.clipboard.writeText(val)
    setCopiedKey(key)
    setTimeout(() => setCopiedKey(null), 2000)
  }

  const handleProcessGateway = (channelCode: string) => {
    setLoading(true)
    setErrorMsg(null)
    router.post(
      "/portal/processPayment",
      {
        invoice_id: invoice.id,
        method: "gateway",
        channel: channelCode,
      },
      {
        preserveScroll: true,
        onError: (errs) => {
          setErrorMsg(Object.values(errs)[0] as string || "Gagal memproses pembayaran.")
          setLoading(false)
        },
        onFinish: () => setLoading(false),
      }
    )
  }

  const handleCancelActivePayment = () => {
    if (!confirm("Batalkan transaksi pembayaran aktif ini?")) return
    setLoading(true)
    router.post(
      `/portal/payment/${invoice.id}/cancel`,
      {},
      {
        preserveScroll: true,
        onFinish: () => setLoading(false),
      }
    )
  }

  const payableAmount = uniqueAmount || invoice.unique_amount || invoice.amount

  return (
    <AppLayout
      title="Pembayaran Tagihan"
      subtitle={`Invoice #${invoice.invoice_number} • Periode ${invoice.period || "Bulan Ini"}`}
      brand={portalBrand}
      navItems={portalNavItems}
      hideSidebar={true}
      hideBottomNav={true}
    >
      <div className="space-y-4 max-w-4xl mx-auto">
        {/* Back Link */}
        <div className="flex items-center justify-between">
          <Link
            href="/portal/invoices"
            className="inline-flex items-center gap-1.5 text-xs sm:text-sm font-semibold text-gray-500 hover:text-brand-500 transition-colors"
          >
            <ChevronLeft className="h-4 w-4" />
            Kembali ke Daftar Tagihan
          </Link>
          <div className="flex items-center gap-2">
            <span className="text-xs text-gray-500 dark:text-gray-400 font-medium">Jatuh Tempo:</span>
            <span className="text-xs font-bold text-gray-900 dark:text-white">
              {formatDate(invoice.due_date)}
            </span>
          </div>
        </div>

        {/* =========================================================================
            SUCCESS STATE: LUNAS
            ========================================================================= */}
        {isPaid ? (
          <div className="rounded-2xl border border-emerald-200 bg-emerald-50/50 p-6 sm:p-8 text-center space-y-4 dark:border-emerald-900/40 dark:bg-emerald-950/20">
            <div className="flex h-14 w-14 sm:h-16 sm:w-16 items-center justify-center rounded-full bg-emerald-500 text-white mx-auto shadow-md shadow-emerald-500/20">
              <CheckCircle2 className="h-8 w-8 sm:h-9 sm:w-9" />
            </div>
            <div className="space-y-1">
              <h2 className="text-xl sm:text-2xl font-bold text-emerald-800 dark:text-emerald-300">
                Pembayaran Berhasil &amp; Terverifikasi!
              </h2>
              <p className="text-xs sm:text-sm text-emerald-700/90 dark:text-emerald-400">
                Invoice <b>#{invoice.invoice_number}</b> sebesar <b>{formatCurrency(invoice.amount)}</b> telah lunas. Layanan internet Anda aktif normal.
              </p>
            </div>
            <div className="pt-2 flex flex-col sm:flex-row items-center justify-center gap-3">
              <Button
                onClick={() => router.visit("/portal")}
                className="w-full sm:w-auto rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs sm:text-sm px-6"
              >
                Kembali ke Dashboard
              </Button>
              <Button
                variant="outline"
                onClick={() => router.visit("/portal/invoices")}
                className="w-full sm:w-auto rounded-xl border-emerald-300 dark:border-emerald-800 text-emerald-800 dark:text-emerald-300 font-bold text-xs sm:text-sm px-6"
              >
                Lihat Semua Invoice
              </Button>
            </div>
          </div>
        ) : (
          <>
            {/* =========================================================================
                HERO INVOICE SUMMARY CARD (TailAdmin Standard)
                ========================================================================= */}
            <div className="rounded-2xl border border-gray-200 bg-white p-5 sm:p-6 shadow-xs dark:border-gray-800 dark:bg-white/[0.03] flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
              <div className="space-y-1 min-w-0">
                <div className="flex items-center gap-2">
                  <Badge variant="warning" className="text-[11px] px-2.5 py-0.5">
                    Menunggu Pembayaran
                  </Badge>
                  <span className="text-xs font-medium text-gray-500 dark:text-gray-400">
                    {invoice.period ? `Periode ${invoice.period}` : "Invoice Bulanan"}
                  </span>
                </div>
                <div className="text-2xl sm:text-3xl font-extrabold font-display tabular-nums tracking-tight text-gray-900 dark:text-white">
                  {formatCurrency(payableAmount)}
                </div>
                <p className="text-xs text-gray-500 dark:text-gray-400">
                  Paket: <span className="font-semibold text-gray-700 dark:text-gray-300">{customer.package || "Internet Provider"}</span> • Atas Nama: <span className="font-semibold text-gray-700 dark:text-gray-300">{customer.name}</span>
                </p>
              </div>

              {cleanAdminWa && (
                <a
                  href={`https://wa.me/${cleanAdminWa}?text=${encodeURIComponent(
                    `Halo Admin, saya ${customer.name} (${customer.pppoe_username || customer.code || ""}) ingin konfirmasi pembayaran invoice #${invoice.invoice_number} sebesar ${formatCurrency(payableAmount)}.`
                  )}`}
                  target="_blank"
                  rel="noreferrer"
                  className="inline-flex items-center justify-center gap-1.5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-xs font-bold text-emerald-700 hover:bg-emerald-100 dark:border-emerald-900/50 dark:bg-emerald-950/40 dark:text-emerald-400 transition-all shrink-0"
                >
                  <MessageCircle className="h-4 w-4 text-emerald-600 dark:text-emerald-400" />
                  Konfirmasi WA Admin
                </a>
              )}
            </div>

            {/* Error Alert */}
            {errorMsg && (
              <div className="flex items-center gap-2.5 rounded-xl border border-rose-200 bg-rose-50 p-4 text-xs font-semibold text-rose-700 dark:border-rose-900/40 dark:bg-rose-950/30 dark:text-rose-400">
                <AlertTriangle className="h-4 w-4 shrink-0" />
                <span>{errorMsg}</span>
              </div>
            )}

            {/* =========================================================================
                ACTIVE PAYMENT INSTRUCTIONS (VA / QRIS / Retail)
                ========================================================================= */}
            {activePayment && (
              <div className="rounded-2xl border-2 border-brand-500 bg-brand-50/20 p-5 sm:p-6 shadow-sm dark:border-brand-600 dark:bg-brand-950/20 space-y-4">
                <div className="flex items-center justify-between gap-2 border-b border-brand-100 pb-3 dark:border-brand-900/50">
                  <div className="flex items-center gap-2">
                    <span className="flex h-2.5 w-2.5 rounded-full bg-brand-500 animate-ping" />
                    <h3 className="text-sm font-bold text-brand-950 dark:text-white uppercase tracking-wider">
                      Instruksi Pembayaran Aktif ({activePayment.channel ? activePayment.channel.toUpperCase() : "Gateway"})
                    </h3>
                  </div>
                  <Button
                    size="sm"
                    variant="ghost"
                    onClick={handleCancelActivePayment}
                    disabled={loading}
                    className="h-8 text-xs text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/30"
                  >
                    <X className="h-3.5 w-3.5 mr-1" />
                    Batalkan
                  </Button>
                </div>

                {/* Case 1: Virtual Account / Pay Code */}
                {(activePayment.va_number || activePayment.pay_code) && (
                  <div className="space-y-3">
                    <div className="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                      <div>
                        <div className="text-xs text-gray-500 dark:text-gray-400 font-medium">
                          Nomor Virtual Account / Kode Bayar:
                        </div>
                        <div className="text-xl sm:text-2xl font-black font-mono tracking-widest text-brand-600 dark:text-brand-400 mt-0.5">
                          {activePayment.va_number || activePayment.pay_code}
                        </div>
                      </div>
                      <Button
                        size="sm"
                        onClick={() => copyText(activePayment.va_number || activePayment.pay_code || "", "va")}
                        className="rounded-xl bg-brand-500 hover:bg-brand-600 text-white text-xs font-bold shrink-0"
                      >
                        {copiedKey === "va" ? <Check className="h-3.5 w-3.5 mr-1" /> : <Copy className="h-3.5 w-3.5 mr-1" />}
                        {copiedKey === "va" ? "Tersalin!" : "Salin Kode"}
                      </Button>
                    </div>

                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                      <div className="rounded-xl border border-gray-200 bg-white p-3.5 dark:border-gray-800 dark:bg-gray-900">
                        <span className="text-gray-500 dark:text-gray-400">Total Nominal Pembayaran:</span>
                        <div className="font-bold text-gray-900 dark:text-white text-sm mt-0.5 tabular-nums">
                          {formatCurrency(activePayment.total_amount || payableAmount)}
                        </div>
                      </div>
                      <div className="rounded-xl border border-gray-200 bg-white p-3.5 dark:border-gray-800 dark:bg-gray-900">
                        <span className="text-gray-500 dark:text-gray-400">Batas Waktu Bayar:</span>
                        <div className="font-bold text-amber-600 dark:text-amber-400 text-sm mt-0.5">
                          {activePayment.expired_at ? formatDate(activePayment.expired_at) : "Segera Lakukan Pembayaran"}
                        </div>
                      </div>
                    </div>
                  </div>
                )}

                {/* Case 2: QRIS Image / Payload */}
                {isQrisActive && (
                  <div className="flex flex-col items-center justify-center p-2 bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 space-y-4">
                    <QrisFintechCheckout
                      merchantName={companyName}
                      orderId={invoice.invoice_number}
                      amount={activePayment.total_amount || payableAmount}
                      qrImageUrl={activePayment.qr_image || ""}
                      qrSvg={activePayment.qr_string || ""}
                      expiresAt={activePayment.expired_at}
                    />
                  </div>
                )}
              </div>
            )}

            {/* =========================================================================
                PAYMENT METHODS SELECTOR TABS (TailAdmin Standard)
                ========================================================================= */}
            <div className="rounded-2xl border border-gray-200 bg-white p-5 sm:p-6 shadow-xs dark:border-gray-800 dark:bg-white/[0.03] space-y-6">
              <div>
                <h3 className="text-base font-bold text-gray-900 dark:text-white">
                  Pilih Metode Pembayaran
                </h3>
                <p className="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                  Tersedia pembayaran otomatis instan via QRIS, Virtual Account bank, dan transfer manual.
                </p>
              </div>

              {/* Navigation Pill Tabs */}
              <div className="grid grid-cols-1 sm:grid-cols-3 gap-2 border-b border-gray-100 pb-4 dark:border-gray-800">
                {paymentOptions.map((opt) => {
                  const Icon = opt.icon
                  const isActive = activeTab === opt.id
                  return (
                    <button
                      key={opt.id}
                      type="button"
                      onClick={() => setActiveTab(opt.id)}
                      className={cn(
                        "flex items-center gap-3 p-3.5 rounded-xl border text-left transition-all cursor-pointer",
                        isActive
                          ? "border-brand-500 bg-brand-50/40 text-brand-950 dark:border-brand-500 dark:bg-brand-950/20 dark:text-white ring-1 ring-brand-500"
                          : "border-gray-200 bg-gray-50/50 hover:bg-gray-100/80 text-gray-700 dark:border-gray-800 dark:bg-white/[0.02] dark:text-gray-300 dark:hover:bg-white/[0.05]"
                      )}
                    >
                      <div
                        className={cn(
                          "flex h-9 w-9 shrink-0 items-center justify-center rounded-lg font-bold",
                          isActive
                            ? "bg-brand-500 text-white"
                            : "bg-gray-200 text-gray-600 dark:bg-gray-800 dark:text-gray-400"
                        )}
                      >
                        <Icon className="h-5 w-5" />
                      </div>
                      <div className="min-w-0 flex-1">
                        <div className="text-xs font-bold truncate">{opt.label}</div>
                        <div className="text-[10px] text-gray-500 dark:text-gray-400 line-clamp-1 mt-0.5">
                          {opt.desc}
                        </div>
                      </div>
                    </button>
                  )
                })}
              </div>

              {/* TAB 1: QRIS INSTAN */}
              {activeTab === "qris" && (
                <div className="space-y-4">
                  <div className="rounded-xl border border-gray-200 bg-gray-50/50 p-4 dark:border-gray-800 dark:bg-white/[0.02] space-y-2">
                    <div className="flex items-center gap-2 font-bold text-xs text-gray-900 dark:text-white">
                      <QrCode className="h-4 w-4 text-brand-500" />
                      Pembayaran QRIS Nasional (Verifikasi Otomatis)
                    </div>
                    <p className="text-xs text-gray-500 dark:text-gray-400">
                      Gunakan aplikasi e-wallet apa saja (GoPay, OVO, DANA, ShopeePay, LinkAja) atau Mobile Banking (BCA, Mandiri, BRI, BNI, Jago, Seabank) untuk memindai kode QRIS.
                    </p>
                  </div>

                  <div className="flex justify-center pt-2">
                    <QrisFintechCheckout
                      merchantName={companyName}
                      orderId={invoice.invoice_number}
                      amount={payableAmount}
                      qrImageUrl={qrisPayload?.qr_image || paymentMethods?.qris?.image_url || ""}
                      qrSvg={qrisPayload?.qr_string || paymentMethods?.qris?.static_qr || ""}
                    />
                  </div>
                </div>
              )}

              {/* TAB 2: VIRTUAL ACCOUNT & PAYMENT GATEWAY */}
              {activeTab === "gateway" && (
                <div className="space-y-4">
                  <div className="text-xs text-gray-500 dark:text-gray-400">
                    Pilih salah satu channel bank / outlet pembayaran di bawah ini untuk mendapatkan nomor Virtual Account atau Kode Pembayaran:
                  </div>

                  {channels.length > 0 ? (
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                      {channels.map((ch) => (
                        <button
                          key={ch.code}
                          type="button"
                          disabled={loading}
                          onClick={() => handleProcessGateway(ch.code)}
                          className={cn(
                            "flex items-center justify-between p-4 rounded-xl border border-gray-200 bg-white hover:border-brand-500 hover:bg-brand-50/20 text-left transition-all cursor-pointer dark:border-gray-800 dark:bg-gray-900 dark:hover:border-brand-500 dark:hover:bg-brand-950/20 group",
                            loading && "opacity-60 pointer-events-none"
                          )}
                        >
                          <div className="space-y-1 min-w-0 pr-2">
                            <div className="text-xs font-bold text-gray-900 dark:text-white group-hover:text-brand-500 transition-colors">
                              {ch.name}
                            </div>
                            <div className="text-[11px] text-gray-500 dark:text-gray-400">
                              Kategori: {ch.group ? ch.group.toUpperCase() : "Virtual Account"}
                            </div>
                          </div>
                          <div className="flex items-center gap-1.5 shrink-0 text-brand-500 font-bold text-xs">
                            <span>Bayar</span>
                            <ChevronRight className="h-4 w-4 group-hover:translate-x-0.5 transition-transform" />
                          </div>
                        </button>
                      ))}
                    </div>
                  ) : (
                    <div className="rounded-xl border border-dashed border-gray-200 p-6 text-center text-xs text-gray-500 dark:border-gray-800">
                      Channel Virtual Account sedang offline atau dalam pemeliharaan. Silakan gunakan metode QRIS Instan atau Transfer Manual.
                    </div>
                  )}
                </div>
              )}

              {/* TAB 3: TRANSFER BANK MANUAL */}
              {activeTab === "bank" && (
                <div className="space-y-4">
                  <div className="text-xs text-gray-500 dark:text-gray-400">
                    Silakan transfer tepat sebesar nominal di bawah ini ke salah satu rekening resmi kami:
                  </div>

                  <div className="space-y-3">
                    {bankAccounts.map((acc, idx) => (
                      <div
                        key={idx}
                        className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 p-4 rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900"
                      >
                        <div className="space-y-0.5 min-w-0">
                          <div className="text-xs font-bold text-gray-900 dark:text-white uppercase tracking-wider">
                            {acc.bank_name}
                          </div>
                          <div className="text-lg font-extrabold font-mono tracking-wider text-brand-600 dark:text-brand-400">
                            {acc.account_number}
                          </div>
                          <div className="text-xs text-gray-500 dark:text-gray-400">
                            Atas Nama: <span className="font-semibold text-gray-800 dark:text-gray-200">{acc.account_name}</span>
                          </div>
                        </div>

                        <Button
                          size="sm"
                          onClick={() => copyText(acc.account_number, `bank-${idx}`)}
                          className="rounded-xl bg-gray-100 hover:bg-gray-200 text-gray-800 dark:bg-gray-800 dark:hover:bg-gray-700 dark:text-white text-xs font-bold shrink-0 self-start sm:self-auto"
                        >
                          {copiedKey === `bank-${idx}` ? (
                            <Check className="h-3.5 w-3.5 mr-1" />
                          ) : (
                            <Copy className="h-3.5 w-3.5 mr-1" />
                          )}
                          {copiedKey === `bank-${idx}` ? "Tersalin!" : "Salin Rekening"}
                        </Button>
                      </div>
                    ))}
                  </div>

                  {cleanAdminWa && (
                    <div className="pt-2">
                      <a
                        href={`https://wa.me/${cleanAdminWa}?text=${encodeURIComponent(
                          `Halo Admin, saya ${customer.name} (${customer.pppoe_username || customer.code || ""}) sudah melakukan transfer pembayaran manual untuk invoice #${invoice.invoice_number} sebesar ${formatCurrency(payableAmount)}. Mohon bantuan verifikasi.`
                        )}`}
                        target="_blank"
                        rel="noreferrer"
                        className="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white p-3 text-xs sm:text-sm font-bold shadow-xs transition-all"
                      >
                        <MessageCircle className="h-4 w-4 text-white" />
                        Kirim Bukti Transfer ke WhatsApp Admin
                      </a>
                    </div>
                  )}
                </div>
              )}
            </div>
          </>
        )}
      </div>
    </AppLayout>
  )
}
