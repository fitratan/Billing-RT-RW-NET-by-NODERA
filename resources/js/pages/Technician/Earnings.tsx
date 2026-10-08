import * as React from "react"
import { AppLayout } from "@/components/layout/app-layout"
import {
  Wallet,
  Calendar,
  Receipt,
  Sparkles,
  CheckCircle2,
  ChevronDown,
  Search,
  RefreshCw,
  LogOut,
  ChevronRight,
  ChevronLeft,
  DollarSign,
  ArrowRight,
  Layers,
  Wrench,
} from "lucide-react"
import { Badge } from "@/components/ui/badge"
import { PageProps } from "@/types"
import { cn, formatIDR, periodLabel } from "@/lib/utils"
import { technicianSidebarItems, technicianNavItems, technicianBrand } from "@/lib/technician-nav"
import { triggerHaptic } from "@/lib/haptics"
import { router, Link } from "@inertiajs/react"

interface InvoiceItem {
  id: number
  invoice_number: string
  customer_name: string
  customer_code: string
  amount: number
  commission: number
  paid_at: string
  period?: string
}

interface MonthlyEarning {
  month_key: string
  month_name: string
  total_invoices: number
  total_collected: number
  total_commission: number
  invoices?: InvoiceItem[]
}

export default function TechnicianEarningsPage({
  technician,
  totalEarningsAllTime,
  totalInvoicesAllTime,
  monthlyEarnings = [],
}: PageProps<{
  technician: {
    name: string
    username: string
    commission_type: string
    commission_value: number
    commission_rate_label: string
  }
  totalEarningsAllTime: number
  totalInvoicesAllTime: number
  monthlyEarnings: MonthlyEarning[]
}>) {
  const [expandedMonth, setExpandedMonth] = React.useState<string | null>(
    monthlyEarnings[0]?.month_key ?? null
  )
  const [searchQuery, setSearchQuery] = React.useState("")
  const [isRefreshing, setIsRefreshing] = React.useState(false)

  const handleRefresh = () => {
    setIsRefreshing(true)
    router.reload({ onFinish: () => setIsRefreshing(false) })
  }

  const toggleMonth = (monthKey: string) => {
    triggerHaptic("selection")
    setExpandedMonth((prev) => (prev === monthKey ? null : monthKey))
    setSearchQuery("")
  }

  return (
    <AppLayout
      title="Pendapatan Teknisi"
      brand={technicianBrand}
      sidebarItems={technicianSidebarItems}
      navItems={technicianNavItems}
      hideHeader={true}
      className="p-0 sm:p-0 max-w-none bg-[#10141A]"
    >
      <div className="min-h-screen bg-[#10141A] text-slate-100 select-none pb-28 sm:pb-36">
        {/* =========================================================================
            SECTION 1: TOP ELECTRIC BLUE CANVAS (Obsidian Modern Standard)
            ========================================================================= */}
        <section className="relative bg-gradient-to-br from-[#0073C6] via-[#0056A0] to-[#003E78] px-4 pt-5 pb-6 sm:px-8 sm:pt-7 sm:pb-8 text-white shadow-sm rounded-b-[2rem] sm:rounded-b-[2.5rem] border-b border-white/15 overflow-hidden">
          <div className="absolute inset-0 overflow-hidden pointer-events-none">
            <div
              className="absolute inset-0 opacity-20"
              
            />
            <div className="absolute -top-12 -right-12 h-56 w-56 rounded-full border border-white/15 pointer-events-none" />
            <div className="absolute -top-6 -right-6 h-44 w-44 rounded-full border border-dashed border-white/10 pointer-events-none" />
          </div>

          <div className="relative max-w-7xl 2xl:max-w-[1700px] mx-auto space-y-4 sm:space-y-5">
            {/* Top Bar Header inside Canvas */}
            <div className="flex items-center justify-between gap-2.5 sm:gap-4">
              {/* Brand App Badge */}
              <div className="flex items-center gap-2.5 rounded-full border border-white/20 bg-black/25 px-3.5 py-1.5 backdrop-blur-md min-w-0 flex-1 sm:flex-initial overflow-hidden">
                <img
                  src="/images/logo-white.png?v=36"
                  alt="NODERA"
                  className="h-7 w-7 sm:h-8 sm:w-8 rounded-full object-contain bg-white/95 p-0.5 shrink-0"
                />
                <div className="leading-tight min-w-0 flex-1 overflow-hidden">
                  <div className="text-xs sm:text-sm font-black tracking-wider text-white truncate">
                    NODERA
                  </div>
                  <div className="mt-0.5 flex items-center gap-1.5">
                    <span className="rounded-md border border-[#00C2FF]/30 bg-[#00C2FF]/20 px-1.5 py-0.2 text-[9px] font-extrabold text-[#00C2FF] uppercase leading-tight shrink-0">
                      PENDAPATAN
                    </span>
                  </div>
                  <div className="text-[10px] sm:text-xs font-medium text-white/80 truncate">
                    {technician.name}
                  </div>
                </div>
              </div>

              {/* Actions Right: Refresh + Kembali / Logout */}
              <div className="flex items-center gap-1.5 sm:gap-2.5 shrink-0 [&>*]:shrink-0">
                <button
                  onClick={handleRefresh}
                  disabled={isRefreshing}
                  className="flex h-9 w-9 sm:h-10 sm:w-10 items-center justify-center rounded-full border border-white/20 bg-white/15 backdrop-blur-md text-white transition-all hover:bg-white/25 active:scale-95 disabled:opacity-50"
                  title="Muat Ulang Data"
                >
                  <RefreshCw className={cn("h-4 w-4 sm:h-5 sm:w-5", isRefreshing && "animate-spin")} />
                </button>

                <Link
                  href="/teknisi/dashboard"
                  className="flex h-9 w-9 sm:h-10 sm:w-10 shrink-0 items-center justify-center rounded-full border border-white/20 bg-white/15 backdrop-blur-md text-white hover:bg-white/25 active:scale-95 transition-all lg:hidden"
                  title="Kembali ke Dashboard"
                >
                  <ChevronLeft className="h-5 w-5 shrink-0" />
                </Link>
              </div>
            </div>

            {/* Hero Summary Card: Total Pendapatan Insentif */}
            <div className="rounded-2xl border border-white/20 bg-white/10 p-5 sm:p-7 backdrop-blur-md shadow-sm space-y-4">
              <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div className="space-y-1">
                  <div className="flex items-center gap-2">
                    <span className="flex h-2 w-2 rounded-full bg-emerald-400 animate-pulse" />
                    <span className="text-xs font-bold uppercase tracking-wider text-emerald-300">
                      Total Insentif &amp; Komisi Pekerjaan
                    </span>
                  </div>
                  <div className="text-3xl sm:text-5xl font-extrabold font-display tabular-nums tracking-tight text-white">
                    {formatIDR(totalEarningsAllTime)}
                  </div>
                  <p className="text-xs text-white/80">
                    Akumulasi insentif pasang baru dan komisi perbaikan gangguan yang berhasil diselesaikan
                  </p>
                </div>

                <div className="flex flex-wrap items-center gap-2 self-start sm:self-auto">
                  <span className="inline-flex items-center gap-1.5 rounded-xl border border-white/20 bg-black/25 px-3.5 py-1.5 text-xs font-bold text-white">
                    <Sparkles className="h-3.5 w-3.5 text-[#00C2FF]" />
                    Skema: {technician.commission_rate_label}
                  </span>
                  <span className="inline-flex items-center gap-1.5 rounded-xl border border-white/20 bg-black/25 px-3.5 py-1.5 text-xs font-bold text-emerald-300">
                    <Wrench className="h-3.5 w-3.5 text-emerald-400" />
                    {totalInvoicesAllTime} Pekerjaan Selesai
                  </span>
                </div>
              </div>
            </div>
          </div>
        </section>

        {/* =========================================================================
            SECTION 2: RINCIAN INSENTIF PER BULAN (Obsidian Dark Cards)
            ========================================================================= */}
        <div className="max-w-7xl 2xl:max-w-[1700px] mx-auto px-4 sm:px-8 mt-5 sm:mt-7 relative z-10 space-y-4">
          <div className="flex items-center justify-between pb-1">
            <h3 className="text-sm sm:text-base font-bold text-white flex items-center gap-2">
              <Calendar className="h-4 w-4 text-[#00C2FF]" />
              <span>Rincian Insentif Bulanan ({monthlyEarnings.length} Periode)</span>
            </h3>
          </div>

          {monthlyEarnings.length === 0 ? (
            <div className="p-8 rounded-2xl border border-dashed border-[#212B3B] bg-[#121720] text-center space-y-2 text-white">
              <Wallet className="mx-auto h-8 w-8 text-slate-500" />
              <p className="text-sm font-bold">Belum Ada Riwayat Insentif</p>
              <p className="text-xs text-slate-400">
                Insentif akan otomatis tercatat setiap kali tiket pekerjaan atau pasang baru berhasil Anda selesaikan.
              </p>
            </div>
          ) : (
            <div className="space-y-3">
              {monthlyEarnings.map((month) => {
                const isExpanded = expandedMonth === month.month_key
                const invoices = (month.invoices ?? []).filter((inv) => {
                  if (!searchQuery) return true
                  const q = searchQuery.toLowerCase()
                  return (
                    inv.customer_name.toLowerCase().includes(q) ||
                    inv.customer_code.toLowerCase().includes(q) ||
                    inv.invoice_number.toLowerCase().includes(q)
                  )
                })

                return (
                  <div
                    key={month.month_key}
                    className="overflow-hidden rounded-2xl border border-[#212B3B] bg-[#121720] transition-all select-none"
                  >
                    {/* Header Bulan */}
                    <button
                      type="button"
                      onClick={() => toggleMonth(month.month_key)}
                      className={cn(
                        "flex w-full items-center justify-between p-4 sm:p-5 text-left transition-colors cursor-pointer",
                        isExpanded ? "bg-[#18202C] border-b border-[#1E2633]" : "hover:bg-[#161B22]"
                      )}
                    >
                      <div className="flex items-center gap-3 min-w-0">
                        <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#0073C6]/15 border border-[#0073C6]/30 text-[#00C2FF]">
                          <Calendar className="h-5 w-5" />
                        </div>
                        <div className="min-w-0">
                          <h4 className="truncate text-sm sm:text-base font-bold text-white">
                            {month.month_name}
                          </h4>
                          <p className="text-xs text-slate-400 mt-0.5">
                            {month.total_invoices} Pekerjaan Diselesaikan · Nilai {formatIDR(month.total_collected)}
                          </p>
                        </div>
                      </div>

                      <div className="flex items-center gap-3 shrink-0 pl-2">
                        <div className="text-right">
                          <div className="text-sm sm:text-base font-extrabold font-display text-emerald-400 tabular-nums">
                            {formatIDR(month.total_commission)}
                          </div>
                          <span className="text-[10px] uppercase font-bold text-slate-400">Insentif</span>
                        </div>
                        <ChevronDown
                          className={cn(
                            "h-5 w-5 text-slate-400 transition-transform duration-200",
                            isExpanded ? "rotate-180 text-[#00C2FF]" : ""
                          )}
                        />
                      </div>
                    </button>

                    {/* Konten Terbuka (List Transaksi Pelanggan) */}
                    {isExpanded && (
                      <div className="p-4 sm:p-5 space-y-3 bg-[#0E1217]">
                        {/* Search in Month */}
                        <div className="relative">
                          <Search className="pointer-events-none absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-slate-400" />
                          <input
                            type="text"
                            value={searchQuery}
                            onChange={(e) => setSearchQuery(e.target.value)}
                            placeholder="Cari pelanggan / tiket di bulan ini..."
                            className="h-9 w-full rounded-xl border border-[#212B3B] bg-[#121720] pl-9 pr-3 text-xs text-white placeholder:text-slate-500 focus-visible:outline-none focus-visible:border-[#0073C6]"
                          />
                        </div>

                        {/* Transactions List */}
                        {invoices.length === 0 ? (
                          <div className="py-6 text-center text-xs text-slate-400">
                            {searchQuery ? "Tidak ditemukan transaksi sesuai pencarian" : "Belum ada rincian transaksi"}
                          </div>
                        ) : (
                          <div className="divide-y divide-[#1E2633] rounded-xl border border-[#1E2633] bg-[#121720] overflow-hidden">
                            {invoices.map((inv) => (
                              <div
                                key={inv.id}
                                className="flex items-center justify-between p-3 sm:p-3.5 text-xs text-slate-300 hover:bg-[#18202C] transition-colors"
                              >
                                <div className="min-w-0 flex-1 pr-2">
                                  <p className="font-bold text-white truncate">{inv.customer_name}</p>
                                  <p className="text-[11px] text-slate-400 mt-0.5 font-mono">
                                    {inv.customer_code} · {inv.invoice_number}
                                  </p>
                                  <p className="text-[10px] text-slate-500 mt-0.5">{inv.paid_at}</p>
                                </div>

                                <div className="text-right shrink-0">
                                  <div className="font-bold text-emerald-400 tabular-nums">
                                    +{formatIDR(inv.commission)}
                                  </div>
                                  <div className="text-[10px] text-slate-400 mt-0.5">
                                    Nilai Tagihan: {formatIDR(inv.amount)}
                                  </div>
                                </div>
                              </div>
                            ))}
                          </div>
                        )}
                      </div>
                    )}
                  </div>
                )
              })}
            </div>
          )}
        </div>
      </div>
    </AppLayout>
  )
}
