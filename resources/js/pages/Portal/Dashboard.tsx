import { AppLayout } from "@/components/layout/app-layout"
import {
  Receipt,
  Wallet,
  ChevronRight,
  RefreshCw,
  MessageCircle,
  CheckCircle2,
  Clock,
} from "lucide-react"
import { PageProps } from "@/types"
import { cn, formatCurrency, formatDate } from "@/lib/utils"
import { router, Link } from "@inertiajs/react"
import { useState } from "react"
import { PortalPaymentModal } from "@/components/portal/PortalPaymentModal"
import { DefaultPinModal } from "@/components/portal/DefaultPinModal"
import { portalNavItems, portalBrand } from "@/lib/portal-nav"

interface PortalInvoice {
  id: number
  invoice_number: string
  amount: number
  paid: boolean
  period: string | null
  created_at: string | null
  due_date: string | null
}

interface OnuData {
  serial?: string
  model?: string
  manufacturer?: string
  lastInform?: string
  online?: boolean
  rxPower?: string
  pppoeIP?: string
  ssid?: string
}

export default function PortalDashboardPage({
  customer,
  currentInvoice,
  invoices = [],
  adminWa,
  companyName = "NODERA",
  isDefaultPin = false,
}: PageProps<{
  customer: {
    id: number
    name: string
    pppoe_username: string | null
    phone: string | null
    address: string | null
    package: string | null
    isolation_date: number | null
  }
  currentInvoice: PortalInvoice | null
  invoices: PortalInvoice[]
  onuData?: OnuData | null
  genieacsConfigured?: boolean
  adminWa?: string | null
  companyName?: string
  isDefaultPin?: boolean
}>) {
  const [isRefreshing, setIsRefreshing] = useState(false)
  const [isPayModalOpen, setIsPayModalOpen] = useState(false)
  const [selectedInvoice, setSelectedInvoice] = useState<PortalInvoice | null>(null)
  const [showPinModal, setShowPinModal] = useState<boolean>(() => {
    if (!isDefaultPin) return false
    // Show only once per session or if user dismissed
    const dismissed = sessionStorage.getItem("nodera_pin_modal_dismissed")
    return !dismissed
  })

  const handleRefresh = () => {
    setIsRefreshing(true)
    router.reload({
      onFinish: () => setIsRefreshing(false),
    })
  }

  const handleOpenPayModal = (inv?: PortalInvoice | null) => {
    const target = inv || currentInvoice
    if (!target || target.paid || Number(target.amount) <= 0) return
    setSelectedInvoice(target)
    setIsPayModalOpen(true)
  }

  // Hanya tagihan belum lunas dengan nominal > 0 yang dianggap unpaid
  const isUnpaid = Boolean(currentInvoice && !currentInvoice.paid && Number(currentInvoice.amount) > 0)

  return (
    <AppLayout
      title="Beranda"
      subtitle={companyName}
      brand={portalBrand}
      navItems={portalNavItems}
      hideSidebar={true}
    >
      <div className="space-y-4 max-w-4xl mx-auto pb-32 sm:pb-20">
        {/* =========================================================================
            SECTION 1: HERO BILLING CARD (CLEAN FLAT TAILADMIN)
            ========================================================================= */}
        <div className="rounded-2xl border border-gray-200 bg-white p-5 sm:p-6 shadow-xs dark:border-gray-800 dark:bg-white/[0.03]">
          {/* Top Bar: Title & Status + Package & Refresh */}
          <div className="flex items-center justify-between gap-2 border-b border-gray-100 pb-3.5 dark:border-gray-800">
            <div className="flex items-center gap-2 flex-wrap">
              <span className="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                Tagihan Periode Ini
              </span>
              <span
                className={cn(
                  "text-[10px] font-black px-2.5 py-0.5 rounded-md uppercase tracking-wider",
                  isUnpaid ? "bg-rose-600 text-white" : "bg-emerald-600 text-white"
                )}
              >
                {isUnpaid ? "Belum Bayar" : "Lunas"}
              </span>
            </div>

            <div className="flex items-center gap-2">
              <span className="rounded-md bg-brand-600 px-2.5 py-0.5 text-[11px] font-black uppercase tracking-wider text-white">
                {customer.package ?? "Internet"}
              </span>
              <button
                type="button"
                onClick={handleRefresh}
                disabled={isRefreshing}
                className="p-1 rounded-lg text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition cursor-pointer"
                title="Segarkan data"
              >
                <RefreshCw className={cn("h-3.5 w-3.5", isRefreshing && "animate-spin")} />
              </button>
            </div>
          </div>

          {/* Amount & Description */}
          <div className="py-4">
            <div className="text-3xl sm:text-4xl font-black font-mono tracking-tight text-gray-900 dark:text-white">
              {isUnpaid && currentInvoice
                ? formatCurrency(currentInvoice.amount)
                : "Rp 0"}
            </div>
            <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
              {isUnpaid
                ? "Selesaikan pembayaran sebelum batas jatuh tempo agar koneksi tetap aktif."
                : "Layanan aktif normal. Tidak ada tagihan tertunda bulan ini."}
            </p>
          </div>

          {/* Bottom Row: Due Date & Action Button */}
          <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-t border-gray-100 pt-3.5 dark:border-gray-800">
            <div className="flex items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400">
              <Clock className="h-3.5 w-3.5 text-gray-400 shrink-0" />
              <span>
                {currentInvoice?.due_date
                  ? `Jatuh tempo: ${formatDate(currentInvoice.due_date)}`
                  : customer.isolation_date
                  ? `Batas isolir: Tgl ${customer.isolation_date}`
                  : "Jatuh tempo: Tgl 20 setiap bulan"}
              </span>
            </div>

            {isUnpaid && currentInvoice ? (
              <button
                type="button"
                onClick={() => handleOpenPayModal(currentInvoice)}
                className="inline-flex items-center justify-center gap-2 rounded-xl bg-brand-600 hover:bg-brand-700 text-white px-5 py-2.5 text-xs font-black shadow-xs active:scale-95 transition cursor-pointer"
              >
                <Wallet className="h-4 w-4" />
                <span>Bayar Sekarang</span>
              </button>
            ) : (
              <div className="inline-flex items-center justify-center gap-1.5 rounded-xl bg-emerald-600 text-white px-3.5 py-1.5 text-xs font-bold">
                <CheckCircle2 className="h-3.5 w-3.5" />
                <span>Layanan Aktif</span>
              </div>
            )}
          </div>
        </div>

        {/* =========================================================================
            SECTION 2: RECENT INVOICES
            ========================================================================= */}
        {invoices.length > 0 && (
          <div className="space-y-2.5">
            <div className="flex items-center justify-between px-1">
              <h2 className="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                Tagihan Terbaru
              </h2>
              <Link
                href="/portal/invoices"
                className="text-xs font-bold text-brand-600 dark:text-brand-400 hover:underline flex items-center gap-0.5"
              >
                <span>Lihat Semua</span>
                <ChevronRight className="h-3 w-3" />
              </Link>
            </div>

            <div className="rounded-2xl border border-gray-200 bg-white divide-y divide-gray-100 shadow-xs dark:border-gray-800 dark:bg-white/[0.03] dark:divide-gray-800 overflow-hidden">
              {invoices.slice(0, 4).map((i: PortalInvoice) => {
                const itemUnpaid = !i.paid && Number(i.amount) > 0
                return (
                  <div
                    key={i.id}
                    onClick={() => itemUnpaid && handleOpenPayModal(i)}
                    className={cn(
                      "flex items-center justify-between p-3.5 text-left transition-colors",
                      itemUnpaid
                        ? "hover:bg-gray-50 dark:hover:bg-white/[0.02] cursor-pointer"
                        : "cursor-default opacity-90"
                    )}
                  >
                    <div className="flex items-center gap-3 min-w-0">
                      <div
                        className={cn(
                          "flex h-9 w-9 shrink-0 items-center justify-center rounded-xl font-bold text-xs",
                          !itemUnpaid
                            ? "bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400"
                            : "bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400"
                        )}
                      >
                        <Receipt className="h-4 w-4" />
                      </div>
                      <div className="min-w-0">
                        <p className="truncate font-mono text-xs font-black text-gray-900 dark:text-white">
                          {i.invoice_number}
                        </p>
                        <p className="text-[10px] text-gray-500 dark:text-gray-400 mt-0.5">
                          {formatDate(i.created_at)} {i.period ? `• Periode ${i.period}` : ""}
                        </p>
                      </div>
                    </div>

                    <div className="shrink-0 text-right">
                      <p className="text-xs sm:text-sm font-black font-mono text-gray-900 dark:text-white tabular-nums">
                        {formatCurrency(i.amount)}
                      </p>
                      {/* SOLID BADGE */}
                      <span
                        className={cn(
                          "inline-block mt-0.5 text-[9px] font-black px-1.5 py-0.5 rounded uppercase",
                          !itemUnpaid
                            ? "bg-emerald-600 text-white"
                            : "bg-rose-600 text-white"
                        )}
                      >
                        {!itemUnpaid ? "Lunas" : "Belum Bayar"}
                      </span>
                    </div>
                  </div>
                )
              })}
            </div>
          </div>
        )}

        {/* =========================================================================
            SECTION 3: WHATSAPP CS SUPPORT
            ========================================================================= */}
        {adminWa && (
          <div className="rounded-2xl border border-emerald-200 bg-emerald-50/50 p-4 shadow-xs dark:border-emerald-500/20 dark:bg-emerald-500/5 flex items-center justify-between gap-3">
            <div className="flex items-center gap-3 min-w-0">
              <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-emerald-600 text-white shadow-xs">
                <MessageCircle className="h-5 w-5" />
              </div>
              <div className="min-w-0">
                <h3 className="text-xs sm:text-sm font-bold text-gray-900 dark:text-white truncate">
                  Butuh Bantuan?
                </h3>
                <p className="text-[10px] text-gray-500 dark:text-gray-400 truncate">
                  Hubungi customer service kami via WhatsApp
                </p>
              </div>
            </div>

            <a
              href={`https://wa.me/${adminWa.replace(/[^0-9]/g, "")}?text=${encodeURIComponent(
                `Halo Admin ${companyName}, saya ${customer.name || "Pelanggan"} butuh bantuan mengenai akun portal saya.`
              )}`}
              target="_blank"
              rel="noopener noreferrer"
              className="inline-flex items-center justify-center gap-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white px-3.5 py-2 text-xs font-bold shadow-xs active:scale-95 transition shrink-0 cursor-pointer"
            >
              <MessageCircle className="h-3.5 w-3.5" />
              <span>Chat CS</span>
            </a>
          </div>
        )}
      </div>

      {/* Pop-up Modal Pembayaran Tagihan Pop-up */}
      <PortalPaymentModal
        isOpen={isPayModalOpen}
        onClose={() => {
          setIsPayModalOpen(false)
          setSelectedInvoice(null)
        }}
        invoice={selectedInvoice || currentInvoice}
        customer={customer}
        adminWa={adminWa}
        companyName={companyName}
      />

      {/* Pop-up Modal Ganti PIN Default Otomatis */}
      <DefaultPinModal
        isOpen={showPinModal}
        onClose={() => {
          sessionStorage.setItem("nodera_pin_modal_dismissed", "true")
          setShowPinModal(false)
        }}
      />
    </AppLayout>
  )
}