import { AppLayout } from "@/components/layout/app-layout"
import {
  ScanLine,
  Search,
  Wallet,
  Clock,
  Printer,
  X,
  Settings,
  ChevronRight,
  ArrowDownCircle,
  Users,
} from "lucide-react"
import { EmptyState } from "@/components/ui/empty-state"
import { PageProps } from "@/types"
import { formatIDR, cn, formatDate } from "@/lib/utils"
import { adminSidebarItems, adminNavItems, adminBrand } from "@/lib/admin-nav"
import { useState, useMemo } from "react"
import { MetricCard } from "@/components/tailadmin/MetricCard"

interface Session {
  id: number
  cashier_name: string
  opened_at?: string
  closed_at?: string | null
  total_in?: number
  total_out?: number
  created_at?: string
}

export default function CashierReportsPage({
  sessions = [],
}: PageProps<{
  sessions: Session[]
  companyName?: string
  tenantName?: string
}>) {
  const [managingSession, setManagingSession] = useState<Session | null>(null)
  const [searchQuery, setSearchQuery] = useState("")
  const [statusFilter, setStatusFilter] = useState<string>("all")

  const totalIn = sessions.reduce((sum, s) => sum + Number(s.total_in ?? 0), 0)
  const totalOut = sessions.reduce((sum, s) => sum + Number(s.total_out ?? 0), 0)
  const activeSessionsCount = sessions.filter((s) => !s.closed_at).length

  const filteredSessions = useMemo(() => {
    return sessions.filter((s) => {
      const isActive = !s.closed_at
      if (statusFilter === "active" && !isActive) return false
      if (statusFilter === "closed" && isActive) return false
      if (searchQuery.trim()) {
        const q = searchQuery.toLowerCase().trim()
        const matchName = (s.cashier_name ?? "").toLowerCase().includes(q)
        const matchId = String(s.id).includes(q)
        if (!matchName && !matchId) return false
      }
      return true
    })
  }, [sessions, searchQuery, statusFilter])

  return (
    <AppLayout
      title="Laporan Kasir POS"
      brand={adminBrand}
      sidebarItems={adminSidebarItems}
      navItems={adminNavItems}
    >
      <div className="space-y-6">
        {/* ── 4 TOP KPI METRICS ── */}
        <div className="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
          <MetricCard
            title="Total Kas Masuk"
            value={formatIDR(totalIn)}
            icon={<Wallet className="h-6 w-6 text-emerald-500" />}
            sub="Pemasukan kasir POS"
          />

          <MetricCard
            title="Total Pengeluaran"
            value={formatIDR(totalOut)}
            icon={<ArrowDownCircle className="h-6 w-6 text-rose-500" />}
            sub="Kas keluar sesi"
          />

          <MetricCard
            title="Total Sesi"
            value={`${sessions.length} Sesi`}
            icon={<Users className="h-6 w-6 text-blue-500" />}
            sub="Riwayat register kasir"
            onClick={() => setStatusFilter("all")}
            isActive={statusFilter === "all"}
          />

          <MetricCard
            title="Sesi Aktif"
            value={`${activeSessionsCount} Berjalan`}
            icon={<Clock className="h-6 w-6 text-amber-500" />}
            sub="Sedang melayani"
            onClick={() => setStatusFilter(statusFilter === "active" ? "all" : "active")}
            isActive={statusFilter === "active"}
          />
        </div>

        {/* ── 1-LINE RESPONSIVE INTEGRATED TOOLBAR ── */}
        <div className="rounded-2xl border border-gray-200 bg-white p-3 sm:p-4 shadow-xs dark:border-gray-800 dark:bg-white/[0.03] flex flex-col gap-2.5 sm:gap-3 lg:flex-row lg:items-center lg:justify-between">
          <div className="flex flex-wrap items-center gap-2 sm:gap-3 flex-1 min-w-0">
            {/* Search Bar */}
            <div className="relative flex-1 min-w-[180px] sm:min-w-[220px] w-full sm:w-auto">
              <Search className="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400 dark:text-gray-500" />
              <input
                type="text"
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                placeholder="Cari nama kasir, ID register sesi..."
                className="h-10 w-full rounded-xl border border-gray-200 bg-gray-50/75 pl-9 pr-8 text-xs font-medium text-gray-900 placeholder:text-gray-400 focus:border-brand-500 focus:outline-none dark:border-gray-800 dark:bg-gray-900/50 dark:text-white dark:placeholder:text-gray-500"
              />
              {searchQuery && (
                <button
                  type="button"
                  onClick={() => setSearchQuery("")}
                  className="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 cursor-pointer"
                >
                  <X className="h-3.5 w-3.5" />
                </button>
              )}
            </div>

            {/* Status Filter */}
            <select
              value={statusFilter}
              onChange={(e) => setStatusFilter(e.target.value)}
              className="h-10 rounded-xl border border-gray-200 bg-white px-3 text-xs font-semibold text-gray-700 shadow-2xs focus:border-brand-500 focus:outline-hidden dark:border-gray-800 dark:bg-gray-900 dark:text-gray-200 cursor-pointer w-full sm:w-auto shrink-0"
            >
              <option value="all">Semua Sesi ({sessions.length})</option>
              <option value="active">Sesi Aktif ({activeSessionsCount})</option>
              <option value="closed">Selesai / Ditutup ({sessions.length - activeSessionsCount})</option>
            </select>
          </div>

          <div className="flex items-center gap-2 shrink-0 w-full sm:w-auto justify-end">
            <button
              type="button"
              onClick={() => window.print()}
              className="inline-flex h-10 items-center gap-1.5 px-3.5 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:hover:bg-gray-800 text-xs font-semibold text-gray-700 dark:text-gray-200 transition active:scale-95 shadow-2xs cursor-pointer shrink-0"
              title="Cetak Laporan Kasir"
            >
              <Printer className="h-4 w-4 text-brand-500" />
              <span>Cetak Laporan</span>
            </button>
          </div>
        </div>

        {/* ── MASTER CARD CONTAINER ── */}
        <div className="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xs dark:border-gray-800 dark:bg-white/[0.03]">
          {/* Header Bar */}
          <div className="p-4 sm:p-5 border-b border-gray-100 dark:border-gray-800 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
              <h3 className="text-base font-bold text-gray-800 dark:text-white/90">
                Riwayat Sesi Kasir POS ({filteredSessions.length})
              </h3>
              <p className="text-xs text-gray-500 dark:text-gray-400">
                Rekonsiliasi pembukaan dan penutupan kasir operasional
              </p>
            </div>
          </div>

          {/* ── TABLE VIEW ── */}
          {filteredSessions.length === 0 ? (
            <div className="p-8 text-center">
              <EmptyState
                icon={<ScanLine className="h-8 w-8 text-brand-500" />}
                title="Belum ada riwayat sesi kasir"
                description="Sesi kasir akan otomatis tercatat saat petugas membuka register kasir POS."
              />
            </div>
          ) : (
            <div className="w-full overflow-x-auto custom-scrollbar">
              <table className="w-full text-left text-xs text-gray-600 dark:text-gray-300 min-w-[700px] border-collapse">
                <thead className="border-b border-gray-200 bg-gray-50/75 text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:border-gray-800 dark:bg-gray-900/50 dark:text-gray-400">
                  <tr>
                    <th className="px-4 py-3.5">Petugas Kasir</th>
                    <th className="px-4 py-3.5">Waktu Buka</th>
                    <th className="px-4 py-3.5">Waktu Tutup</th>
                    <th className="px-4 py-3.5">Status</th>
                    <th className="px-4 py-3.5">Kas Masuk</th>
                    <th className="px-4 py-3.5">Kas Keluar</th>
                    <th className="px-4 py-3.5 text-right">Aksi</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-gray-100 dark:divide-gray-800">
                  {filteredSessions.map((s) => {
                    const isActive = !s.closed_at

                    return (
                      <tr key={s.id} className="hover:bg-gray-50/50 dark:hover:bg-white/[0.02] transition">
                        {/* Petugas Kasir Column */}
                        <td className="px-4 py-3.5">
                          <div className="flex items-center gap-3">
                            <div className="relative flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-gray-100 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 font-bold text-xs text-gray-700 dark:text-gray-200">
                              <span>{s.cashier_name ? s.cashier_name.slice(0, 2).toUpperCase() : "KS"}</span>
                              <span
                                className={cn(
                                  "absolute -bottom-0.5 -right-0.5 h-2.5 w-2.5 rounded-full ring-2 ring-white dark:ring-gray-900",
                                  isActive ? "bg-amber-500" : "bg-emerald-500"
                                )}
                              />
                            </div>
                            <div className="min-w-0">
                              <div className="font-bold text-gray-900 dark:text-white truncate">
                                {s.cashier_name ?? "Petugas Kasir"}
                              </div>
                              <div className="text-[11px] text-gray-500 dark:text-gray-400">
                                Sesi ID #{s.id}
                              </div>
                            </div>
                          </div>
                        </td>

                        {/* Waktu Buka Column */}
                        <td className="px-4 py-3.5 font-mono text-gray-700 dark:text-gray-300">
                          {s.opened_at ? formatDate(s.opened_at) : "-"}
                        </td>

                        {/* Waktu Tutup Column */}
                        <td className="px-4 py-3.5 font-mono text-gray-700 dark:text-gray-300">
                          {s.closed_at ? formatDate(s.closed_at) : (
                            <span className="text-amber-600 dark:text-amber-400 font-medium">Masih Aktif</span>
                          )}
                        </td>

                        {/* Status Column */}
                        <td className="px-4 py-3.5">
                          {isActive ? (
                            <span className="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-bold bg-amber-500 text-white shadow-xs whitespace-nowrap">
                              Sesi Berjalan
                            </span>
                          ) : (
                            <span className="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-bold bg-emerald-500 text-white shadow-xs whitespace-nowrap">
                              Selesai
                            </span>
                          )}
                        </td>

                        {/* Kas Masuk Column */}
                        <td className="px-4 py-3.5 font-mono font-bold text-sm text-emerald-600 dark:text-emerald-400">
                          {formatIDR(s.total_in ?? 0)}
                        </td>

                        {/* Kas Keluar Column */}
                        <td className="px-4 py-3.5 font-mono font-bold text-sm text-rose-600 dark:text-rose-400">
                          {formatIDR(s.total_out ?? 0)}
                        </td>

                        {/* Single Action Button: Kelola */}
                        <td className="px-4 py-3.5 text-right whitespace-nowrap">
                          <button
                            type="button"
                            onClick={() => setManagingSession(s)}
                            className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200 font-bold text-xs transition cursor-pointer"
                            title="Detail Sesi"
                          >
                            <Settings className="h-3.5 w-3.5" />
                            <span>Kelola</span>
                          </button>
                        </td>
                      </tr>
                    )
                  })}
                </tbody>
              </table>
            </div>
          )}
        </div>
      </div>

      {/* ── MODAL INTERAKTIF: DETAIL SESI KASIR ── */}
      {managingSession && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-xs">
          <div className="w-full max-w-md rounded-2xl border border-gray-200 bg-white p-5 shadow-2xl dark:border-gray-800 dark:bg-gray-900 space-y-4 max-h-[92vh] overflow-y-auto">
            {/* Modal Header */}
            <div className="flex items-center justify-between border-b border-gray-100 pb-3 dark:border-gray-800">
              <div className="flex items-center gap-3">
                <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-brand-500 text-white shadow-xs font-bold text-sm">
                  {managingSession.cashier_name ? managingSession.cashier_name.slice(0, 2).toUpperCase() : "KS"}
                </div>
                <div className="min-w-0">
                  <h3 className="font-bold text-sm text-gray-900 dark:text-white truncate">
                    Sesi: {managingSession.cashier_name}
                  </h3>
                  <span className="text-[11px] text-gray-500 font-mono">
                    Register Sesi #{managingSession.id}
                  </span>
                </div>
              </div>
              <button
                type="button"
                onClick={() => setManagingSession(null)}
                className="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-800 dark:hover:text-gray-200 transition cursor-pointer"
              >
                <X className="h-5 w-5" />
              </button>
            </div>

            {/* Quick Summary Card */}
            <div className="rounded-xl border border-gray-200/80 bg-gray-50/70 p-3.5 dark:border-gray-800 dark:bg-gray-800/40 space-y-2 text-xs">
              <div className="flex items-center justify-between">
                <span className="text-gray-500 dark:text-gray-400">Status Sesi</span>
                {!managingSession.closed_at ? (
                  <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold bg-amber-500 text-white shadow-xs whitespace-nowrap">
                    Sesi Berjalan
                  </span>
                ) : (
                  <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold bg-emerald-500 text-white shadow-xs whitespace-nowrap">
                    Selesai &amp; Ditutup
                  </span>
                )}
              </div>
              <div className="flex items-center justify-between">
                <span className="text-gray-500 dark:text-gray-400">Total Kas Masuk</span>
                <span className="font-mono font-bold text-emerald-600 dark:text-emerald-400 text-sm">
                  {formatIDR(managingSession.total_in ?? 0)}
                </span>
              </div>
              <div className="flex items-center justify-between">
                <span className="text-gray-500 dark:text-gray-400">Total Kas Keluar</span>
                <span className="font-mono font-bold text-rose-600 dark:text-rose-400 text-sm">
                  {formatIDR(managingSession.total_out ?? 0)}
                </span>
              </div>
              <div className="flex items-center justify-between">
                <span className="text-gray-500 dark:text-gray-400">Waktu Buka</span>
                <span className="font-mono text-gray-800 dark:text-gray-200">
                  {managingSession.opened_at ? formatDate(managingSession.opened_at) : "-"}
                </span>
              </div>
              {managingSession.closed_at && (
                <div className="flex items-center justify-between">
                  <span className="text-gray-500 dark:text-gray-400">Waktu Tutup</span>
                  <span className="font-mono text-gray-800 dark:text-gray-200">
                    {formatDate(managingSession.closed_at)}
                  </span>
                </div>
              )}
            </div>

            {/* Action Buttons List */}
            <div className="space-y-2 pt-1 text-xs">
              <button
                type="button"
                onClick={() => {
                  setManagingSession(null)
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
                      Cetak Rekap Sesi Kasir
                    </div>
                    <div className="text-[10px] text-gray-500">Cetak lembar rekonsiliasi kas masuk dan kas keluar</div>
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
