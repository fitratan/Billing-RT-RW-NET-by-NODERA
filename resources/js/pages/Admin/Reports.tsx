import { AppLayout } from "@/components/layout/app-layout"
import {
  FileText,
  TrendingUp,
  ArrowDownCircle,
  Users,
  Printer,
  X,
  Settings,
  ChevronRight,
  Receipt,
  DollarSign,
  Activity,
} from "lucide-react"
import { EmptyState } from "@/components/ui/empty-state"
import { PageProps } from "@/types"
import { formatIDR, formatDate, cn } from "@/lib/utils"
import { adminSidebarItems, adminNavItems, adminBrand } from "@/lib/admin-nav"
import { Link } from "@inertiajs/react"
import { useState } from "react"
import { MetricCard } from "@/components/tailadmin/MetricCard"

interface ReportRow {
  id: number
  label: string
  amount: number
  date: string | null
}

export default function ReportsPage({
  type = "revenue",
  summary = {},
  rows = [],
}: PageProps<{
  type: string
  summary: Record<string, number>
  rows: ReportRow[]
  companyName?: string
  tenantName?: string
}>) {
  const [tab, setTab] = useState(type ?? "revenue")
  const [managingRow, setManagingRow] = useState<ReportRow | null>(null)
  const summaryEntries = Object.entries(summary ?? {})

  const totalAmount = rows.reduce((acc, r) => acc + (Number(r.amount) || 0), 0)

  return (
    <AppLayout
      title="Laporan Eksekutif"
      brand={adminBrand}
      sidebarItems={adminSidebarItems}
      navItems={adminNavItems}
    >
      <div className="space-y-6">
        {/* ── TOP KPI METRICS ── */}
        <div className="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
          <MetricCard
            title="Total Akumulasi"
            value={formatIDR(totalAmount)}
            icon={<DollarSign className="h-6 w-6 text-emerald-500" />}
            sub="Total nilai kategori aktif"
          />

          <MetricCard
            title="Jumlah Entri"
            value={`${rows.length} Data`}
            icon={<Receipt className="h-6 w-6 text-blue-500" />}
            sub="Rekapitulasi baris data"
          />

          {summaryEntries.slice(0, 2).map(([k, v]) => (
            <MetricCard
              key={k}
              title={k}
              value={formatIDR(v)}
              icon={<Activity className="h-6 w-6 text-purple-500" />}
              sub="Ringkasan pos metrik"
            />
          ))}

          {summaryEntries.length < 2 && (
            <MetricCard
              title="Rata-rata Nilai"
              value={formatIDR(rows.length ? Math.round(totalAmount / rows.length) : 0)}
              icon={<TrendingUp className="h-6 w-6 text-amber-500" />}
              sub="Rerata per transaksi"
            />
          )}
        </div>

        {/* ── MASTER CARD CONTAINER ── */}
        <div className="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xs dark:border-gray-800 dark:bg-white/[0.03]">
          {/* Header Bar */}
          <div className="p-4 sm:p-5 border-b border-gray-100 dark:border-gray-800 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
              <h3 className="text-base font-bold text-gray-800 dark:text-white/90">
                Rincian Entri Laporan ({rows.length})
              </h3>
              <p className="text-xs text-gray-500 dark:text-gray-400">
                Data rekapitulasi operasional dan keuangan periode aktif
              </p>
            </div>

            <div className="flex items-center gap-2 flex-wrap sm:flex-nowrap">
              <button
                type="button"
                onClick={() => window.print()}
                className="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:hover:bg-gray-800 text-xs font-semibold text-gray-700 dark:text-gray-200 transition active:scale-95 shadow-2xs cursor-pointer"
                title="Cetak Laporan"
              >
                <Printer className="h-4 w-4 text-brand-500" />
                <span className="hidden sm:inline">Cetak</span>
              </button>

              <Link
                href="/admin/finance"
                className="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-brand-500 hover:bg-brand-600 active:scale-95 text-xs font-bold text-white shadow-xs transition cursor-pointer"
              >
                <TrendingUp className="h-4 w-4" />
                <span>Arus Kas</span>
              </Link>
            </div>
          </div>

          {/* ── 1-LINE RESPONSIVE INTEGRATED TOOLBAR ── */}
          <div className="border-b border-gray-100 dark:border-gray-800 p-3 sm:p-4 bg-gray-50/50 dark:bg-gray-900/30">
            <div className="flex items-center gap-2 sm:gap-3 flex-wrap">
              {[
                { key: "revenue", label: "Pendapatan", icon: TrendingUp },
                { key: "expense", label: "Pengeluaran", icon: ArrowDownCircle },
                { key: "customers", label: "Pelanggan", icon: Users },
              ].map((t) => {
                const IconComp = t.icon
                const isActive = tab === t.key
                return (
                  <button
                    key={t.key}
                    type="button"
                    onClick={() => setTab(t.key)}
                    className={cn(
                      "inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-bold transition cursor-pointer active:scale-95",
                      isActive
                        ? "bg-brand-500 text-white shadow-xs"
                        : "border border-gray-200 bg-white text-gray-700 hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800"
                    )}
                  >
                    <IconComp className="h-3.5 w-3.5" />
                    <span>{t.label}</span>
                  </button>
                )
              })}
            </div>
          </div>

          {/* ── TABLE VIEW ── */}
          {rows.length === 0 ? (
            <div className="p-8 text-center">
              <EmptyState
                icon={<FileText className="h-8 w-8 text-brand-500" />}
                title="Tidak ada data rincian"
                description="Belum ada transaksi atau entri data untuk kategori laporan ini."
              />
            </div>
          ) : (
            <div className="w-full overflow-x-auto custom-scrollbar">
              <table className="w-full text-left text-xs text-gray-600 dark:text-gray-300 min-w-[700px] border-collapse">
                <thead className="border-b border-gray-200 bg-gray-50/75 text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:border-gray-800 dark:bg-gray-900/50 dark:text-gray-400">
                  <tr>
                    <th className="px-4 py-3.5">Nama Entri / Transaksi</th>
                    <th className="px-4 py-3.5">Tanggal</th>
                    <th className="px-4 py-3.5">Status</th>
                    <th className="px-4 py-3.5">Nominal Nilai</th>
                    <th className="px-4 py-3.5 text-right">Aksi</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-gray-100 dark:divide-gray-800">
                  {rows.map((r) => (
                    <tr key={r.id} className="hover:bg-gray-50/50 dark:hover:bg-white/[0.02] transition">
                      {/* Nama Entri Column */}
                      <td className="px-4 py-3.5">
                        <div className="flex items-center gap-3">
                          <div className="relative flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-gray-100 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 font-bold text-xs text-gray-700 dark:text-gray-200">
                            <span>{r.label ? r.label.slice(0, 2).toUpperCase() : "LP"}</span>
                            <span className="absolute -bottom-0.5 -right-0.5 h-2.5 w-2.5 rounded-full bg-emerald-500 ring-2 ring-white dark:ring-gray-900 whitespace-nowrap" />
                          </div>
                          <div className="min-w-0">
                            <div className="font-bold text-gray-900 dark:text-white truncate">
                              {r.label}
                            </div>
                            <div className="text-[11px] text-gray-500 dark:text-gray-400">
                              ID #{r.id}
                            </div>
                          </div>
                        </div>
                      </td>

                      {/* Tanggal Column */}
                      <td className="px-4 py-3.5 text-gray-600 dark:text-gray-300 font-mono">
                        {r.date ? formatDate(r.date) : "Hari Ini"}
                      </td>

                      {/* Status Column */}
                      <td className="px-4 py-3.5">
                        <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold bg-emerald-500 text-white shadow-xs whitespace-nowrap">
                          Selesai
                        </span>
                      </td>

                      {/* Nominal Nilai Column */}
                      <td className="px-4 py-3.5 font-mono font-bold text-sm text-emerald-600 dark:text-emerald-400">
                        {formatIDR(r.amount)}
                      </td>

                      {/* Single Action Button: Kelola */}
                      <td className="px-4 py-3.5 text-right whitespace-nowrap">
                        <button
                          type="button"
                          onClick={() => setManagingRow(r)}
                          className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200 font-bold text-xs transition cursor-pointer"
                          title="Detail Entri"
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

      {/* ── MODAL INTERAKTIF: DETAIL ENTRI LAPORAN ── */}
      {managingRow && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-xs">
          <div className="w-full max-w-md rounded-2xl border border-gray-200 bg-white p-5 shadow-2xl dark:border-gray-800 dark:bg-gray-900 space-y-4 max-h-[92vh] overflow-y-auto">
            {/* Modal Header */}
            <div className="flex items-center justify-between border-b border-gray-100 pb-3 dark:border-gray-800">
              <div className="flex items-center gap-3">
                <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-brand-500 text-white shadow-xs font-bold text-sm">
                  {managingRow.label ? managingRow.label.slice(0, 2).toUpperCase() : "LP"}
                </div>
                <div className="min-w-0">
                  <h3 className="font-bold text-sm text-gray-900 dark:text-white truncate">
                    Entri: {managingRow.label}
                  </h3>
                  <span className="text-[11px] text-gray-500 font-mono">
                    ID #{managingRow.id} • Kategori {tab}
                  </span>
                </div>
              </div>
              <button
                type="button"
                onClick={() => setManagingRow(null)}
                className="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-800 dark:hover:text-gray-200 transition cursor-pointer"
              >
                <X className="h-5 w-5" />
              </button>
            </div>

            {/* Quick Summary Card */}
            <div className="rounded-xl border border-gray-200/80 bg-gray-50/70 p-3.5 dark:border-gray-800 dark:bg-gray-800/40 space-y-2 text-xs">
              <div className="flex items-center justify-between">
                <span className="text-gray-500 dark:text-gray-400">Status Pembukuan</span>
                <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold bg-emerald-500 text-white shadow-xs whitespace-nowrap">
                  Selesai / Terverifikasi
                </span>
              </div>
              <div className="flex items-center justify-between">
                <span className="text-gray-500 dark:text-gray-400">Total Nominal</span>
                <span className="font-mono font-bold text-emerald-600 dark:text-emerald-400 text-sm">
                  {formatIDR(managingRow.amount)}
                </span>
              </div>
              <div className="flex items-center justify-between">
                <span className="text-gray-500 dark:text-gray-400">Tanggal Entri</span>
                <span className="font-mono text-gray-800 dark:text-gray-200">
                  {managingRow.date ? formatDate(managingRow.date) : "Hari Ini"}
                </span>
              </div>
            </div>

            {/* Action Buttons List */}
            <div className="space-y-2 pt-1 text-xs">
              <button
                type="button"
                onClick={() => {
                  setManagingRow(null)
                  window.print()
                }}
                className="w-full group flex items-center justify-between p-3 rounded-xl bg-gray-50/70 hover:bg-brand-50/50 border border-gray-100 hover:border-brand-200 dark:bg-gray-800/40 dark:hover:bg-gray-800 dark:border-gray-800 dark:hover:border-brand-900/60 transition cursor-pointer"
              >
                <div className="flex items-center gap-3">
                  <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-brand-50 text-brand-600 dark:bg-brand-950 dark:text-brand-400 whitespace-nowrap">
                    <Printer className="h-4 w-4" />
                  </div>
                  <div className="text-left">
                    <div className="font-bold text-gray-900 dark:text-white">
                      Cetak Detail Entri
                    </div>
                    <div className="text-[10px] text-gray-500">Cetak lembar rekapitulasi entri ini</div>
                  </div>
                </div>
                <ChevronRight className="h-4 w-4 text-gray-400 group-hover:text-brand-500 transition-colors" />
              </button>
            </div>
          </div>
        </div>
      )}
    </AppLayout>
  )
}
