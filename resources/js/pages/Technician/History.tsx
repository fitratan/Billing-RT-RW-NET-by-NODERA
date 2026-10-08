import * as React from "react"
import { AppLayout } from "@/components/layout/app-layout"
import {
  CheckCircle2,
  Search,
  Receipt,
  Wrench,
  Calendar,
  Clock,
  User,
  ShieldCheck,
  MapPin,
  ChevronRight,
  FileCheck2,
  FolderCheck,
} from "lucide-react"
import { PageProps } from "@/types"
import { formatIDR, cn } from "@/lib/utils"
import { technicianSidebarItems, technicianNavItems, technicianBrand } from "@/lib/technician-nav"
import { collectorSidebarItems, collectorNavItems, collectorBrand } from "@/lib/collector-nav"
import { usePage, Link } from "@inertiajs/react"
import { ViewModeSwitcher, type ViewMode } from "@/components/ui/view-mode-switcher"

interface ResolvedTicket {
  id: number
  ticket_number: string
  customer_name: string
  customer_code: string
  customer_phone?: string | null
  customer_address?: string | null
  router_name?: string | null
  title: string
  description?: string | null
  type: string
  status: string
  priority: string
  resolved_at: string
  resolution_notes?: string | null
  technician_name?: string | null
}

interface HistoryProps extends PageProps {
  tickets: ResolvedTicket[]
  authTechnician?: {
    id: number
    name: string
  }
}

export default function HistoryPage({ tickets = [] }: HistoryProps) {
  const { url } = usePage()
  const isCollector = url.startsWith("/kolektor")

  const brand = isCollector ? collectorBrand : technicianBrand
  const sidebarItems = isCollector ? collectorSidebarItems : technicianSidebarItems
  const navItems = isCollector ? collectorNavItems : technicianNavItems

  const [searchQuery, setSearchQuery] = React.useState("")
  const [typeFilter, setTypeFilter] = React.useState("all")
  const [viewMode, setViewMode] = React.useState<ViewMode>(() => {
    if (typeof window !== "undefined") {
      return (localStorage.getItem("nodera_history_view") as ViewMode) || "table"
    }
    return "table"
  })

  const handleViewModeChange = (mode: ViewMode) => {
    setViewMode(mode)
    if (typeof window !== "undefined") {
      localStorage.setItem("nodera_history_view", mode)
    }
  }

  const filteredTickets = React.useMemo(() => {
    return tickets.filter((t) => {
      const matchSearch =
        !searchQuery.trim() ||
        (t.ticket_number && t.ticket_number.toLowerCase().includes(searchQuery.toLowerCase())) ||
        (t.customer_name && t.customer_name.toLowerCase().includes(searchQuery.toLowerCase())) ||
        (t.customer_code && t.customer_code.toLowerCase().includes(searchQuery.toLowerCase())) ||
        (t.title && t.title.toLowerCase().includes(searchQuery.toLowerCase()))

      const matchType = typeFilter === "all" || t.type === typeFilter

      return matchSearch && matchType
    })
  }, [tickets, searchQuery, typeFilter])

  return (
    <AppLayout
      title="Riwayat Selesai"
      brand={brand}
      sidebarItems={sidebarItems}
      navItems={navItems}
    >
      <div className="space-y-4 sm:space-y-6 select-none pb-20">
        {/* ── TOP SUMMARY STATS ── */}
        <div className="grid grid-cols-1 sm:grid-cols-3 gap-3.5 sm:gap-4">
          <div className="rounded-2xl border border-gray-200 bg-white p-4 sm:p-5 shadow-xs dark:border-gray-800 dark:bg-white/[0.03]">
            <div className="flex items-center justify-between">
              <span className="text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                Total Pekerjaan Selesai
              </span>
              <div className="flex h-8 w-8 items-center justify-center rounded-xl bg-emerald-500 text-white shadow-xs">
                <FileCheck2 className="h-4 w-4" />
              </div>
            </div>
            <div className="mt-3">
              <p className="text-2xl sm:text-3xl font-black text-gray-900 dark:text-white">
                {tickets.length}
              </p>
              <p className="text-xs text-emerald-600 dark:text-emerald-400 font-bold mt-1">
                Riwayat tuntas & terverifikasi
              </p>
            </div>
          </div>

          <div className="rounded-2xl border border-gray-200 bg-white p-4 sm:p-5 shadow-xs dark:border-gray-800 dark:bg-white/[0.03]">
            <div className="flex items-center justify-between">
              <span className="text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                Pekerjaan Teknis
              </span>
              <div className="flex h-8 w-8 items-center justify-center rounded-xl bg-brand-500 text-white shadow-xs">
                <Wrench className="h-4 w-4" />
              </div>
            </div>
            <div className="mt-3">
              <p className="text-2xl sm:text-3xl font-black text-gray-900 dark:text-white">
                {tickets.filter((t) => t.type !== "billing").length}
              </p>
              <p className="text-xs text-gray-500 dark:text-gray-400 mt-1">
                Perbaikan, instalasi & LOS
              </p>
            </div>
          </div>

          <div className="rounded-2xl border border-gray-200 bg-white p-4 sm:p-5 shadow-xs dark:border-gray-800 dark:bg-white/[0.03]">
            <div className="flex items-center justify-between">
              <span className="text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                Penagihan / Billing
              </span>
              <div className="flex h-8 w-8 items-center justify-center rounded-xl bg-purple-600 text-white shadow-xs">
                <Receipt className="h-4 w-4" />
              </div>
            </div>
            <div className="mt-3">
              <p className="text-2xl sm:text-3xl font-black text-gray-900 dark:text-white">
                {tickets.filter((t) => t.type === "billing").length}
              </p>
              <p className="text-xs text-purple-600 dark:text-purple-400 font-bold mt-1">
                Koleksi & penyelesaian tagihan
              </p>
            </div>
          </div>
        </div>

        {/* ── 1-LINE SLEEK TOOLBAR (ADMIN BILLING STANDARD) ── */}
        <div className="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-2.5">
          <div className="flex flex-1 items-center gap-2 flex-wrap sm:flex-nowrap min-w-0">
            {/* Search Input */}
            <div className="relative flex-1 sm:w-64 sm:flex-initial min-w-[140px]">
              <Search className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
              <input
                type="text"
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                placeholder="Cari nomor tiket, pelanggan..."
                className="h-10 w-full rounded-xl border border-gray-200 bg-white pl-9 pr-8 text-xs text-gray-900 placeholder:text-gray-400 focus:border-brand-500 focus:outline-hidden dark:border-gray-800 dark:bg-gray-900 dark:text-white dark:placeholder:text-gray-500 shadow-xs"
              />
              {searchQuery && (
                <button
                  type="button"
                  onClick={() => setSearchQuery("")}
                  className="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 text-sm font-bold"
                >
                  &times;
                </button>
              )}
            </div>

            {/* Type Filter Dropdown */}
            <select
              value={typeFilter}
              onChange={(e) => setTypeFilter(e.target.value)}
              className="h-10 px-3 py-2 text-xs font-bold rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 text-gray-900 dark:text-white cursor-pointer shadow-xs focus:ring-2 focus:ring-brand-500 focus:outline-hidden shrink-0"
            >
              <option value="all">Semua Tipe Pekerjaan</option>
              <option value="los">Kabel Putus / LOS</option>
              <option value="redaman">Redaman Tinggi</option>
              <option value="billing">Penagihan / Billing</option>
              <option value="other">Lainnya</option>
            </select>
          </div>

          <div className="flex items-center gap-2 justify-end shrink-0">
            {/* ViewModeSwitcher */}
            <ViewModeSwitcher
              value={viewMode}
              onChange={handleViewModeChange}
              storageKey="nodera_history_view"
              size="sm"
            />
          </div>
        </div>

        {/* ── MASTER CONTAINER: TABLE VS GRID VIEW ── */}
        <div className="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xs dark:border-gray-800 dark:bg-white/[0.03]">
          {filteredTickets.length === 0 ? (
            <div className="p-10 sm:p-16 text-center space-y-3">
              <FolderCheck className="mx-auto h-10 w-10 text-gray-400 dark:text-gray-600" />
              <p className="text-sm font-bold text-gray-900 dark:text-white">
                Tidak ada riwayat pekerjaan ditemukan
              </p>
              <p className="text-xs text-gray-500 dark:text-gray-400 max-w-sm mx-auto">
                {searchQuery || typeFilter !== "all"
                  ? "Coba sesuaikan kata kunci pencarian atau reset filter tipe pekerjaan."
                  : "Belum ada tiket atau pekerjaan yang diselesaikan pada akun ini."}
              </p>
            </div>
          ) : viewMode === "table" ? (
            <div className="w-full min-w-0">
              <div className="w-full overflow-x-auto table-scrollbar">
                <table className="w-full min-w-[900px] text-start text-xs whitespace-nowrap border-collapse">
                  <thead className="border-b border-gray-200 bg-gray-50/75 dark:border-gray-800 dark:bg-gray-800/50 text-gray-500 dark:text-gray-400 font-semibold">
                    <tr>
                      <th className="py-3 px-4 text-start font-bold">No. Tiket</th>
                      <th className="py-3 px-4 text-start font-bold">Pelanggan</th>
                      <th className="py-3 px-4 text-start font-bold">Judul & Catatan Selesai</th>
                      <th className="py-3 px-4 text-center font-bold">Tipe</th>
                      <th className="py-3 px-4 text-center font-bold">Status</th>
                      <th className="py-3 px-4 text-start font-bold">Waktu Selesai</th>
                      <th className="py-3 px-4 text-end font-bold">Aksi</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-gray-100 dark:divide-gray-800 text-gray-700 dark:text-gray-300">
                    {filteredTickets.map((ticket) => (
                      <tr key={ticket.id} className="hover:bg-gray-50/75 dark:hover:bg-gray-800/40 transition-colors">
                        <td className="py-3 px-4 font-mono font-bold text-brand-600 dark:text-brand-400">
                          {ticket.ticket_number}
                        </td>
                        <td className="py-3 px-4">
                          <div className="font-bold text-gray-900 dark:text-white">
                            {ticket.customer_name}
                          </div>
                          <div className="text-[11px] font-mono text-gray-400">
                            {ticket.customer_code}
                          </div>
                        </td>
                        <td className="py-3 px-4 max-w-[300px] truncate">
                          <div className="font-semibold text-gray-900 dark:text-white truncate">
                            {ticket.title}
                          </div>
                          <div className="text-[11px] text-emerald-600 dark:text-emerald-400 truncate">
                            {ticket.resolution_notes || "Telah diselesaikan di lokasi"}
                          </div>
                        </td>
                        <td className="py-3 px-4 text-center">
                          <span className="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-600 text-white uppercase">
                            {ticket.type}
                          </span>
                        </td>
                        <td className="py-3 px-4 text-center">
                          <span className="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-500 text-white shadow-xs">
                            Selesai
                          </span>
                        </td>
                        <td className="py-3 px-4 text-[11px] text-gray-500 dark:text-gray-400">
                          {ticket.resolved_at}
                        </td>
                        <td className="py-3 px-4 text-end">
                          <Link
                            href={isCollector ? `/kolektor/tickets/${ticket.id}` : `/teknisi/tickets/${ticket.id}`}
                            className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-900 dark:text-white font-bold text-xs transition"
                          >
                            <span>Detail</span>
                            <ChevronRight className="h-3.5 w-3.5" />
                          </Link>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>
          ) : (
            <div className="p-4 sm:p-5">
              <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3.5 sm:gap-4">
                {filteredTickets.map((ticket) => (
                  <div
                    key={ticket.id}
                    className="p-4 rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.02] shadow-xs flex flex-col justify-between space-y-3"
                  >
                    <div className="space-y-2.5">
                      <div className="flex items-start justify-between gap-2">
                        <div>
                          <span className="font-mono text-xs font-bold text-brand-600 dark:text-brand-400">
                            {ticket.ticket_number}
                          </span>
                          <h4 className="font-bold text-sm text-gray-900 dark:text-white truncate mt-0.5">
                            {ticket.customer_name}
                          </h4>
                        </div>
                        <span className="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-500 text-white shadow-xs">
                          Selesai
                        </span>
                      </div>

                      <div className="rounded-xl bg-gray-50 dark:bg-gray-900/60 p-2.5 space-y-1 text-xs">
                        <p className="font-semibold text-gray-900 dark:text-white truncate">
                          {ticket.title}
                        </p>
                        <p className="text-[11px] text-emerald-600 dark:text-emerald-400 line-clamp-2">
                          {ticket.resolution_notes || "Telah diselesaikan di lokasi."}
                        </p>
                      </div>

                      <div className="text-[11px] text-gray-500 dark:text-gray-400 space-y-1 pt-1">
                        <div className="flex items-center justify-between text-[10px]">
                          <span>Selesai: {ticket.resolved_at}</span>
                          <span className="font-bold text-gray-700 dark:text-gray-300 uppercase">
                            {ticket.type}
                          </span>
                        </div>
                      </div>
                    </div>

                    <div className="pt-2 border-t border-gray-100 dark:border-gray-800 flex items-center justify-end">
                      <Link
                        href={isCollector ? `/kolektor/tickets/${ticket.id}` : `/teknisi/tickets/${ticket.id}`}
                        className="w-full inline-flex items-center justify-center gap-1.5 py-2 rounded-xl bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-900 dark:text-white font-bold text-xs transition"
                      >
                        <span>Lihat Detail Laporan</span>
                        <ChevronRight className="h-3.5 w-3.5" />
                      </Link>
                    </div>
                  </div>
                ))}
              </div>
            </div>
          )}
        </div>
      </div>
    </AppLayout>
  )
}
