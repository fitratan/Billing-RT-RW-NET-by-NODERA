import { AppLayout } from "@/components/layout/app-layout"
import {
  Receipt,
  CheckCircle2,
  Clock,
  Wallet,
  Calendar,
} from "lucide-react"
import { PageProps } from "@/types"
import { cn, formatCurrency, formatDate } from "@/lib/utils"
import { useState } from "react"
import { PortalPaymentModal } from "@/components/portal/PortalPaymentModal"
import { portalNavItems, portalBrand } from "@/lib/portal-nav"

interface PortalInvoice {
  id: number
  invoice_number: string
  amount: number
  paid: boolean
  period: string | null
  due_date: string | null
  paid_at: string | null
  created_at?: string | null
  package_name?: string | null
}

export default function PortalInvoicesPage({
  invoices = [],
  customer,
  adminWa,
  companyName = "NODERA",
}: PageProps<{
  invoices: PortalInvoice[]
  customer?: { id?: number; name?: string | null; phone?: string | null; package?: string | null } | null
  adminWa?: string | null
  companyName?: string
}>) {
  const [isPayModalOpen, setIsPayModalOpen] = useState(false)
  const [selectedInvoice, setSelectedInvoice] = useState<PortalInvoice | null>(null)

  const unpaidInvoices = invoices.filter((i) => !i.paid && Number(i.amount) > 0)
  const unpaidCount = unpaidInvoices.length
  const totalUnpaidAmount = unpaidInvoices.reduce(
    (acc, i) => acc + Number(i.amount || 0),
    0
  )

  const handleOpenPayModal = (inv: PortalInvoice) => {
    if (inv.paid || Number(inv.amount) <= 0) return
    setSelectedInvoice(inv)
    setIsPayModalOpen(true)
  }

  return (
    <AppLayout
      title="Tagihan & Pembayaran"
      subtitle={companyName}
      brand={portalBrand}
      navItems={portalNavItems}
      hideSidebar={true}
    >
      <div className="space-y-4 max-w-4xl mx-auto pb-32 sm:pb-20">
        {/* =========================================================================
            SECTION 1: UNPAID SUMMARY CARD
            ========================================================================= */}
        <div className="rounded-2xl border border-gray-200 bg-white p-4 sm:p-5 shadow-xs dark:border-gray-800 dark:bg-white/[0.03] flex items-center justify-between gap-3">
          <div className="space-y-0.5 min-w-0">
            <span className="text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 block">
              Total Tagihan Tertunda
            </span>
            <div className="text-xl sm:text-2xl font-black font-mono tracking-tight text-gray-900 dark:text-white truncate">
              {formatCurrency(totalUnpaidAmount)}
            </div>
            <p className="text-[11px] text-gray-500 dark:text-gray-400 truncate">
              {unpaidCount > 0
                ? `${unpaidCount} invoice belum terbayar`
                : "Semua invoice telah lunas"}
            </p>
          </div>

          <div className="shrink-0">
            {unpaidCount > 0 ? (
              <div className="inline-flex items-center gap-1.5 rounded-md bg-rose-600 text-white px-2.5 py-1 text-xs font-black uppercase tracking-wider">
                <span>{unpaidCount} Belum Bayar</span>
              </div>
            ) : (
              <div className="inline-flex items-center gap-1.5 rounded-md bg-emerald-600 text-white px-2.5 py-1 text-xs font-black uppercase tracking-wider">
                <CheckCircle2 className="h-3.5 w-3.5" />
                <span>Lunas</span>
              </div>
            )}
          </div>
        </div>

        {/* =========================================================================
            SECTION 2: INVOICE LIST (Mobile-First 2-Row Fintech Cards)
            ========================================================================= */}
        <div className="space-y-2.5">
          <div className="flex items-center justify-between px-1">
            <h2 className="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 flex items-center gap-1.5">
              <Receipt className="h-3.5 w-3.5 text-brand-500" />
              <span>Daftar Invoice</span>
            </h2>
            <span className="text-[11px] font-medium text-gray-400">
              Total {invoices.length} tagihan
            </span>
          </div>

          {invoices.length === 0 ? (
            <div className="rounded-2xl border border-gray-200 bg-white p-8 text-center text-gray-500 shadow-xs dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-400 space-y-2">
              <div className="flex h-12 w-12 mx-auto items-center justify-center rounded-2xl bg-gray-100 dark:bg-gray-800 text-gray-400">
                <Receipt className="h-6 w-6" />
              </div>
              <p className="text-sm font-bold text-gray-900 dark:text-white">Belum Ada Tagihan</p>
              <p className="text-xs">Riwayat invoice Anda akan otomatis muncul di sini.</p>
            </div>
          ) : (
            <div className="space-y-3">
              {invoices.map((i: PortalInvoice) => {
                const isItemUnpaid = !i.paid && Number(i.amount) > 0
                return (
                  <div
                    key={i.id}
                    onClick={() => isItemUnpaid && handleOpenPayModal(i)}
                    className={cn(
                      "rounded-2xl border bg-white p-4 transition-all shadow-xs space-y-3 dark:bg-white/[0.03]",
                      isItemUnpaid
                        ? "border-amber-500/30 dark:border-amber-500/20 hover:border-brand-500 hover:shadow-md cursor-pointer"
                        : "border-gray-200/80 dark:border-gray-800/80 opacity-95"
                    )}
                  >
                    {/* Card Top Row: Invoice Number + Status Badge */}
                    <div className="flex items-center justify-between gap-2">
                      <div className="flex items-center gap-2 min-w-0">
                        <div
                          className={cn(
                            "flex h-8 w-8 shrink-0 items-center justify-center rounded-lg font-bold text-xs",
                            !isItemUnpaid
                              ? "bg-emerald-50 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-400"
                              : "bg-amber-50 text-amber-600 dark:bg-amber-500/15 dark:text-amber-400"
                          )}
                        >
                          {!isItemUnpaid ? (
                            <CheckCircle2 className="h-4 w-4" />
                          ) : (
                            <Clock className="h-4 w-4" />
                          )}
                        </div>
                        <div className="min-w-0">
                          <span className="font-mono text-xs sm:text-sm font-black text-gray-900 dark:text-white block truncate">
                            {i.invoice_number}
                          </span>
                          <span className="text-[10px] text-gray-500 dark:text-gray-400 block truncate">
                            Periode {i.period || "Bulan Ini"}
                          </span>
                        </div>
                      </div>

                      <span
                        className={cn(
                          "text-[9px] font-black px-2 py-0.5 rounded-md uppercase tracking-wider shrink-0",
                          !isItemUnpaid
                            ? "bg-emerald-600 text-white"
                            : "bg-rose-600 text-white"
                        )}
                      >
                        {!isItemUnpaid ? "Lunas" : "Belum Bayar"}
                      </span>
                    </div>

                    {/* Card Divider */}
                    <div className="border-t border-gray-100 dark:border-gray-800/60" />

                    {/* Card Bottom Row: Nominal & Clean Action Button */}
                    <div className="flex items-center justify-between gap-3 pt-0.5">
                      <div className="space-y-0.5">
                        <span className="text-[10px] font-bold text-gray-400 uppercase tracking-wider block">
                          Total Tagihan
                        </span>
                        <span className="text-base font-black font-mono text-gray-900 dark:text-white block">
                          {formatCurrency(i.amount)}
                        </span>
                        <div className="flex items-center gap-1 text-[10px] text-gray-500 dark:text-gray-400">
                          <Calendar className="h-3 w-3 shrink-0 text-gray-400" />
                          <span>
                            {i.due_date
                              ? `Tempo: ${formatDate(i.due_date)}`
                              : "Jatuh tempo: Tgl 20"}
                          </span>
                        </div>
                      </div>

                      {/* Action Button */}
                      <div className="shrink-0">
                        {isItemUnpaid ? (
                          <button
                            type="button"
                            onClick={(e) => {
                              e.stopPropagation()
                              handleOpenPayModal(i)
                            }}
                            className="inline-flex items-center gap-1.5 rounded-xl bg-brand-600 hover:bg-brand-700 text-white px-4 py-2.5 text-xs font-black shadow-xs active:scale-95 transition cursor-pointer"
                          >
                            <Wallet className="h-3.5 w-3.5" />
                            <span>Bayar</span>
                          </button>
                        ) : (
                          <div className="inline-flex items-center gap-1 rounded-lg bg-gray-100 dark:bg-gray-800 px-2.5 py-1 text-[10px] font-bold text-gray-600 dark:text-gray-400">
                            <CheckCircle2 className="h-3 w-3 text-emerald-500" />
                            <span>Terverifikasi</span>
                          </div>
                        )}
                      </div>
                    </div>
                  </div>
                )
              })}
            </div>
          )}
        </div>
      </div>

      {/* Pop-up Modal Pembayaran Tagihan Instant */}
      <PortalPaymentModal
        isOpen={isPayModalOpen}
        onClose={() => {
          setIsPayModalOpen(false)
          setSelectedInvoice(null)
        }}
        invoice={selectedInvoice}
        customer={customer}
        adminWa={adminWa}
        companyName={companyName}
      />
    </AppLayout>
  )
}