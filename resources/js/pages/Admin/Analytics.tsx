import { AppLayout } from "@/components/layout/app-layout"
import {
  Users,
  TrendingUp,
  Wallet,
  RefreshCw,
  Receipt,
  ArrowUpRight,
  X,
  Settings,
  ChevronRight,
  DollarSign,
  AlertCircle,
} from "lucide-react"
import { EmptyState } from "@/components/ui/empty-state"
import { PageProps } from "@/types"
import { cn, formatIDR, formatDate } from "@/lib/utils"
import { adminSidebarItems, adminNavItems, adminBrand } from "@/lib/admin-nav"
import { router, Link } from "@inertiajs/react"
import { useState } from "react"
import { MetricCard } from "@/components/tailadmin/MetricCard"

interface Payment {
  id: number
  invoice_number: string
  amount: number
  customer_name: string | null
  created_at: string | null
}

export default function AnalyticsPage({
  revenueThisMonth = 0,
  paidInvoices = 0,
  unpaidInvoices = 0,
  totalCustomers = 0,
  recentPayments = [],
}: PageProps<{
  revenueThisMonth: number
  paidInvoices: number
  unpaidInvoices: number
  totalCustomers: number
  recentPayments: Payment[]
  companyName?: string
  tenantName?: string
}>) {
  const [isRefreshing, setIsRefreshing] = useState(false)
  const [managingPayment, setManagingPayment] = useState<Payment | null>(null)

  const handleRefresh = () => {
    setIsRefreshing(true)
    router.reload({
      only: ["revenueThisMonth", "paidInvoices", "unpaidInvoices", "totalCustomers", "recentPayments"],
      onFinish: () => setIsRefreshing(false),
    })
  }

  const recoveryRate = paidInvoices + unpaidInvoices > 0
    ? Math.round((paidInvoices / (paidInvoices + unpaidInvoices)) * 100)
    : 100

  return (
    <AppLayout
      title="Analitik Bisnis"
      brand={adminBrand}
      sidebarItems={adminSidebarItems}
      navItems={adminNavItems}
    >
      <div className="space-y-6">
        {/* ── 4 TOP KPI METRICS ── */}
        <div className="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
          <MetricCard
            title="Omzet Bulan Ini"
            value={formatIDR(revenueThisMonth)}
            icon={<DollarSign className="h-6 w-6 text-emerald-500" />}
            sub={`Rasio pelunasan: ${recoveryRate}%`}
          />

          <MetricCard
            title="Invoice Lunas"
            value={`${paidInvoices} Tagihan`}
            icon={<Receipt className="h-6 w-6 text-blue-500" />}
            sub="Terverifikasi masuk kas"
          />

          <MetricCard
            title="Tertunda / Unpaid"
            value={`${unpaidInvoices} Tagihan`}
            icon={<AlertCircle className="h-6 w-6 text-amber-500" />}
            sub="Menunggu pembayaran"
          />

          <MetricCard
            title="Basis Pelanggan"
            value={`${totalCustomers} Pelanggan`}
            icon={<Users className="h-6 w-6 text-purple-500" />}
            sub="Langganan aktif ISP"
          />
        </div>

        {/* ── MASTER CARD CONTAINER ── */}
        <div className="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xs dark:border-gray-800 dark:bg-white/[0.03]">
          {/* Header Bar */}
          <div className="p-4 sm:p-5 border-b border-gray-100 dark:border-gray-800 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
              <h3 className="text-base font-bold text-gray-800 dark:text-white/90">
                Pembayaran Tagihan Terkini ({recentPayments.length})
              </h3>
              <p className="text-xs text-gray-500 dark:text-gray-400">
                Log real-time transaksi tagihan pelanggan yang terverifikasi
              </p>
            </div>

            <div className="flex items-center gap-2 flex-wrap sm:flex-nowrap">
              <Link
                href="/admin/billing/invoices"
                className="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:hover:bg-gray-800 text-xs font-semibold text-gray-700 dark:text-gray-200 transition active:scale-95 shadow-2xs cursor-pointer"
              >
                <span>Semua Invoice</span>
                <ArrowUpRight className="h-3.5 w-3.5" />
              </Link>

              <Link
                href="/admin/finance"
                className="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-brand-500 hover:bg-brand-600 active:scale-95 text-xs font-bold text-white shadow-xs transition cursor-pointer"
              >
                <TrendingUp className="h-4 w-4" />
                <span>Laporan Arus Kas</span>
              </Link>

              <button
                type="button"
                onClick={handleRefresh}
                disabled={isRefreshing}
                className="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:hover:bg-gray-800 text-xs font-semibold text-gray-700 dark:text-gray-200 transition active:scale-95 shadow-2xs cursor-pointer disabled:opacity-50"
                title="Refresh Data"
              >
                <RefreshCw className={cn("h-4 w-4 text-brand-500", isRefreshing && "animate-spin")} />
                <span className="hidden sm:inline">Refresh</span>
              </button>
            </div>
          </div>

          {/* ── TABLE VIEW ── */}
          {recentPayments.length === 0 ? (
            <div className="p-8 text-center">
              <EmptyState
                icon={<Wallet className="h-8 w-8 text-brand-500" />}
                title="Belum ada transaksi pembayaran"
                description="Pembayaran yang diselesaikan melalui kasir POS atau payment gateway akan tampil di sini."
              />
            </div>
          ) : (
            <div className="w-full overflow-x-auto custom-scrollbar">
              <table className="w-full text-left text-xs text-gray-600 dark:text-gray-300 min-w-[700px] border-collapse">
                <thead className="border-b border-gray-200 bg-gray-50/75 text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:border-gray-800 dark:bg-gray-900/50 dark:text-gray-400">
                  <tr>
                    <th className="px-4 py-3.5">Invoice</th>
                    <th className="px-4 py-3.5">Pelanggan</th>
                    <th className="px-4 py-3.5">Waktu Transaksi</th>
                    <th className="px-4 py-3.5">Status</th>
                    <th className="px-4 py-3.5">Nominal Lunas</th>
                    <th className="px-4 py-3.5 text-right">Aksi</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-gray-100 dark:divide-gray-800">
                  {recentPayments.map((p) => (
                    <tr key={p.id} className="hover:bg-gray-50/50 dark:hover:bg-white/[0.02] transition">
                      {/* Invoice Column */}
                      <td className="px-4 py-3.5 font-mono font-bold text-brand-600 dark:text-brand-400">
                        {p.invoice_number}
                      </td>

                      {/* Pelanggan Column */}
                      <td className="px-4 py-3.5">
                        <div className="font-bold text-gray-900 dark:text-white">
                          {p.customer_name ?? "Pelanggan Umum"}
                        </div>
                      </td>

                      {/* Waktu Column */}
                      <td className="px-4 py-3.5 text-gray-600 dark:text-gray-300 font-mono">
                        {p.created_at ? formatDate(p.created_at) : "Baru saja"}
                      </td>

                      {/* Status Column */}
                      <td className="px-4 py-3.5">
                        <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold bg-emerald-500 text-white shadow-xs whitespace-nowrap">
                          Lunas
                        </span>
                      </td>

                      {/* Nominal Column */}
                      <td className="px-4 py-3.5 font-mono font-bold text-sm text-emerald-600 dark:text-emerald-400">
                        {formatIDR(p.amount)}
                      </td>

                      {/* Single Action Button: Kelola */}
                      <td className="px-4 py-3.5 text-right whitespace-nowrap">
                        <button
                          type="button"
                          onClick={() => setManagingPayment(p)}
                          className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200 font-bold text-xs transition cursor-pointer"
                          title="Detail Pembayaran"
                        >
                          <Settings className="h-3.5 w-3.5" />
                          <span>Kelola</span>
                        </button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </div>
      </div>

      {/* ── MODAL INTERAKTIF: DETAIL TRANSAKSI ── */}
      {managingPayment && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-xs">
          <div className="w-full max-w-md rounded-2xl border border-gray-200 bg-white p-5 shadow-2xl dark:border-gray-800 dark:bg-gray-900 space-y-4 max-h-[92vh] overflow-y-auto">
            {/* Modal Header */}
            <div className="flex items-center justify-between border-b border-gray-100 pb-3 dark:border-gray-800">
              <div className="flex items-center gap-3">
                <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-brand-500 text-white shadow-xs font-bold text-sm">
                  <Receipt className="h-5 w-5" />
                </div>
                <div className="min-w-0">
                  <h3 className="font-bold text-sm text-gray-900 dark:text-white truncate">
                    Invoice: {managingPayment.invoice_number}
                  </h3>
                  <span className="text-[11px] text-gray-500 font-mono">
                    ID #{managingPayment.id} • {managingPayment.customer_name ?? "Pelanggan"}
                  </span>
                </div>
              </div>
              <button
                type="button"
                onClick={() => setManagingPayment(null)}
                className="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-800 dark:hover:text-gray-200 transition cursor-pointer"
              >
                <X className="h-5 w-5" />
              </button>
            </div>

            {/* Quick Summary Card */}
            <div className="rounded-xl border border-gray-200/80 bg-gray-50/70 p-3.5 dark:border-gray-800 dark:bg-gray-800/40 space-y-2 text-xs">
              <div className="flex items-center justify-between">
                <span className="text-gray-500 dark:text-gray-400">Status Invoice</span>
                <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold bg-emerald-500 text-white shadow-xs whitespace-nowrap">
                  Lunas Terverifikasi
                </span>
              </div>
              <div className="flex items-center justify-between">
                <span className="text-gray-500 dark:text-gray-400">Total Nominal</span>
                <span className="font-mono font-bold text-emerald-600 dark:text-emerald-400 text-sm">
                  {formatIDR(managingPayment.amount)}
                </span>
              </div>
              <div className="flex items-center justify-between">
                <span className="text-gray-500 dark:text-gray-400">Pelanggan</span>
                <span className="font-semibold text-gray-900 dark:text-white">
                  {managingPayment.customer_name ?? "Pelanggan Umum"}
                </span>
              </div>
              <div className="flex items-center justify-between">
                <span className="text-gray-500 dark:text-gray-400">Waktu Pembayaran</span>
                <span className="font-mono text-gray-800 dark:text-gray-200">
                  {managingPayment.created_at ? formatDate(managingPayment.created_at) : "Baru saja"}
                </span>
              </div>
            </div>

            {/* Action Buttons List */}
            <div className="space-y-2 pt-1 text-xs">
              <Link
                href={`/admin/billing/invoices?q=${encodeURIComponent(managingPayment.invoice_number)}`}
                className="w-full group flex items-center justify-between p-3 rounded-xl bg-gray-50/70 hover:bg-brand-50/50 border border-gray-100 hover:border-brand-200 dark:bg-gray-800/40 dark:hover:bg-gray-800 dark:border-gray-800 dark:hover:border-brand-900/60 transition cursor-pointer"
              >
                <div className="flex items-center gap-3">
                  <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-brand-50 text-brand-600 dark:bg-brand-950 dark:text-brand-400 whitespace-nowrap">
                    <Receipt className="h-4 w-4" />
                  </div>
                  <div className="text-left">
                    <div className="font-bold text-gray-900 dark:text-white">
                      Buka Rincian Tagihan
                    </div>
                    <div className="text-[10px] text-gray-500">Lihat rincian invoice pada modul billing</div>
                  </div>
                </div>
                <ChevronRight className="h-4 w-4 text-gray-400 group-hover:text-brand-500 transition-colors" />
              </Link>
            </div>
          </div>
        </div>
      )}
    </AppLayout>
  )
}
