import * as React from "react"
import { AppLayout } from "@/components/layout/app-layout"
import { usePage, Link, router } from "@inertiajs/react"
import {
  Inbox,
  Ticket,
  Wrench,
  Clock,
  CheckCircle2,
  AlertTriangle,
  Search,
  User,
  MapPin,
  Calendar,
  ChevronRight,
  Sparkles,
  ArrowRight,
  ShieldAlert,
  Activity,
} from "lucide-react"
import { PageProps } from "@/types"
import { formatIDR, cn } from "@/lib/utils"
import { technicianSidebarItems, technicianNavItems, technicianBrand } from "@/lib/technician-nav"
import { collectorSidebarItems, collectorNavItems, collectorBrand } from "@/lib/collector-nav"
import { ViewModeSwitcher, type ViewMode } from "@/components/ui/view-mode-switcher"

interface TroubleTicket {
  id: number
  ticket_number: string
  customer_id: number
  customer_name: string
  customer_code: string
  customer_phone?: string | null
  customer_address?: string | null
  router_name?: string | null
  odp_name?: string | null
  title: string
  description?: string | null
  status: "open" | "in_progress" | "resolved" | "closed"
  priority: "low" | "medium" | "high" | "urgent"
  type: string
  assigned_to?: number | null
  technician_name?: string | null
  created_at: string
  reported_at?: string | null
}

interface PoolProps extends PageProps {
  openTickets: TroubleTicket[]
  inProgressTickets: TroubleTicket[]
  myInProgressTickets?: TroubleTicket[]
  authTechnician?: {
    id: number
    name: string
    role: string
  }
}

export default function PoolPage({
  openTickets = [],
  inProgressTickets = [],
  myInProgressTickets = [],
  authTechnician,
}: PoolProps) {
  const { url } = usePage()
  const isCollector = url.startsWith("/kolektor")

  const brand = isCollector ? collectorBrand : technicianBrand
  const sidebarItems = isCollector ? collectorSidebarItems : technicianSidebarItems
  const navItems = isCollector ? collectorNavItems : technicianNavItems

  const [activeTab, setActiveTab] = React.useState<"open" | "in_progress">("open")
  const [searchQuery, setSearchQuery] = React.useState("")
  const [priorityFilter, setPriorityFilter] = React.useState("all")
  const [viewMode, setViewMode] = React.useState<ViewMode>(() => {
    if (typeof window !== "undefined") {
      return (localStorage.getItem("nodera_pool_view") as ViewMode) || "table"
    }
    return "table"
  })

  const handleViewModeChange = (mode: ViewMode) => {
    setViewMode(mode)
    if (typeof window !== "undefined") {
      localStorage.setItem("nodera_pool_view", mode)
    }
  }

  const currentList = activeTab === "open" ? openTickets : inProgressTickets

  const filteredTickets = React.useMemo(() => {
    return currentList.filter((t) => {
      const matchSearch =
        !searchQuery.trim() ||
        (t.ticket_number && t.ticket_number.toLowerCase().includes(searchQuery.toLowerCase())) ||
        (t.customer_name && t.customer_name.toLowerCase().includes(searchQuery.toLowerCase())) ||
        (t.customer_code && t.customer_code.toLowerCase().includes(searchQuery.toLowerCase())) ||
        (t.title && t.title.toLowerCase().includes(searchQuery.toLowerCase())) ||
        (t.customer_address && t.customer_address.toLowerCase().includes(searchQuery.toLowerCase()))

      const matchPriority = priorityFilter === "all" || t.priority === priorityFilter

      return matchSearch && matchPriority
    })
  }, [currentList, searchQuery, priorityFilter])

  const handleClaimTicket = (ticketId: number) => {
    router.post(
      isCollector ? `/kolektor/tickets/${ticketId}/claim` : `/teknisi/tickets/${ticketId}/claim`,
      {},
      {
        preserveScroll: true,
      }
    )
  }

  const getPriorityBadge = (priority: string) => {
    switch (priority) {
      case "urgent":
        return <span className="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-600 text-white shadow-xs">URGENT</span>
      case "high":
        return <span className="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-500 text-white shadow-xs">TINGGI</span>
      case "medium":
        return <span className="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-brand-500 text-white shadow-xs">SEDANG</span>
      default:
        return <span className="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-600 text-white shadow-xs">NORMAL</span>
    }
  }

  return (
    <AppLayout
      title="Pool Pekerjaan & Tiket"
      brand={brand}
      sidebarItems={sidebarItems}
      navItems={navItems}
    >
      <div className="space-y-4 sm:space-y-6 select-none pb-20">
        {/* ── TOP STATS OVERVIEW ── */}
        <div className="grid grid-cols-1 sm:grid-cols-3 gap-3.5 sm:gap-4">
          <div className="rounded-2xl border border-gray-200 bg-white p-4 sm:p-5 shadow-xs dark:border-gray-800 dark:bg-white/[0.03]">
            <div className="flex items-center justify-between">
              <span className="text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                Antrean Tiket Baru
              </span>
              <div className="flex h-8 w-8 items-center justify-center rounded-xl bg-amber-500 text-white shadow-xs">
                <AlertTriangle className="h-4 w-4" />
              </div>
            </div>
            <div className="mt-3">
              <p className="text-2xl sm:text-3xl font-black text-gray-900 dark:text-white">
                {openTickets.length} Tiket
              </p>
              <p className="text-xs text-gray-500 dark:text-gray-400 mt-1">
                Tersedia untuk diambil petugas
              </p>
            </div>
          </div>

          <div className="rounded-2xl border border-gray-200 bg-white p-4 sm:p-5 shadow-xs dark:border-gray-800 dark:bg-white/[0.03]">
            <div className="flex items-center justify-between">
              <span className="text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                Sedang Dikerjakan
              </span>
              <div className="flex h-8 w-8 items-center justify-center rounded-xl bg-brand-500 text-white shadow-xs">
                <Wrench className="h-4 w-4" />
              </div>
            </div>
            <div className="mt-3">
              <p className="text-2xl sm:text-3xl font-black text-gray-900 dark:text-white">
                {inProgressTickets.length} Tiket
              </p>
              <p className="text-xs text-gray-500 dark:text-gray-400 mt-1">
                Proses penanganan lapangan
              </p>
            </div>
          </div>

          <div className="rounded-2xl border border-gray-200 bg-white p-4 sm:p-5 shadow-xs dark:border-gray-800 dark:bg-white/[0.03]">
            <div className="flex items-center justify-between">
              <span className="text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                Total Tiket Pool
              </span>
              <div className="flex h-8 w-8 items-center justify-center rounded-xl bg-purple-600 text-white shadow-xs">
                <Ticket className="h-4 w-4" />
              </div>
            </div>
            <div className="mt-3">
              <p className="text-2xl sm:text-3xl font-black text-gray-900 dark:text-white">
                {openTickets.length + inProgressTickets.length}
              </p>
              <p className="text-xs text-emerald-600 dark:text-emerald-400 font-bold mt-1">
                Pool pekerjaan aktif
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
                placeholder="Cari kendala, pelanggan..."
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

            {/* Status Dropdown */}
            <select
              value={activeTab}
              onChange={(e) => setActiveTab(e.target.value as "open" | "in_progress")}
              className="h-10 px-3 py-2 text-xs font-bold rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 text-gray-900 dark:text-white cursor-pointer shadow-xs focus:ring-2 focus:ring-brand-500 focus:outline-hidden shrink-0"
            >
              <option value="open">Antrean Baru ({openTickets.length})</option>
              <option value="in_progress">Sedang Dikerjakan ({inProgressTickets.length})</option>
            </select>

            {/* Priority Dropdown */}
            <select
              value={priorityFilter}
              onChange={(e) => setPriorityFilter(e.target.value)}
              className="h-10 px-3 py-2 text-xs font-bold rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 text-gray-900 dark:text-white cursor-pointer shadow-xs focus:ring-2 focus:ring-brand-500 focus:outline-hidden shrink-0"
            >
              <option value="all">Semua Prioritas</option>
              <option value="urgent">Urgent</option>
              <option value="high">Tinggi</option>
              <option value="medium">Sedang</option>
              <option value="low">Normal</option>
            </select>
          </div>

          <div className="flex items-center gap-2 justify-end shrink-0">
            {/* ViewModeSwitcher */}
            <ViewModeSwitcher
              value={viewMode}
              onChange={handleViewModeChange}
              storageKey="nodera_pool_view"
              size="sm"
            />
          </div>
        </div>

        {/* ── MASTER CONTAINER: TABLE VS GRID VIEW ── */}
        <div className="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xs dark:border-gray-800 dark:bg-white/[0.03]">
          {filteredTickets.length === 0 ? (
            <div className="p-10 sm:p-16 text-center space-y-3">
              <Inbox className="mx-auto h-10 w-10 text-gray-400 dark:text-gray-600" />
              <p className="text-sm font-bold text-gray-900 dark:text-white">
                Tidak ada tiket pada kategori ini
              </p>
              <p className="text-xs text-gray-500 dark:text-gray-400 max-w-sm mx-auto">
                {searchQuery || priorityFilter !== "all"
                  ? "Coba sesuaikan kata kunci pencarian atau filter prioritas."
                  : activeTab === "open"
                  ? "Semua antrean tiket telah ditangani atau belum ada laporan baru."
                  : "Belum ada tiket yang sedang dalam proses penanganan."}
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
                      <th className="py-3 px-4 text-start font-bold">Kendala & Deskripsi</th>
                      <th className="py-3 px-4 text-center font-bold">Prioritas</th>
                      <th className="py-3 px-4 text-start font-bold">Petugas / PIC</th>
                      <th className="py-3 px-4 text-start font-bold">Waktu Lapor</th>
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
                            {ticket.customer_code} {ticket.customer_phone ? `• ${ticket.customer_phone}` : ""}
                          </div>
                        </td>
                        <td className="py-3 px-4 max-w-[280px] truncate">
                          <div className="font-semibold text-gray-900 dark:text-white truncate">
                            {ticket.title}
                          </div>
                          <div className="text-[11px] text-gray-500 dark:text-gray-400 truncate">
                            {ticket.description || "Tidak ada rincian tambahan"}
                          </div>
                        </td>
                        <td className="py-3 px-4 text-center">
                          {getPriorityBadge(ticket.priority)}
                        </td>
                        <td className="py-3 px-4 font-medium">
                          {ticket.technician_name ? (
                            <span className="inline-flex items-center gap-1 text-gray-900 dark:text-white">
                              <User className="h-3.5 w-3.5 text-brand-500" />
                              {ticket.technician_name}
                            </span>
                          ) : (
                            <span className="text-gray-400 italic">Belum diambil</span>
                          )}
                        </td>
                        <td className="py-3 px-4 text-[11px] text-gray-500 dark:text-gray-400">
                          {ticket.created_at}
                        </td>
                        <td className="py-3 px-4 text-end">
                          {activeTab === "open" ? (
                            <button
                              type="button"
                              onClick={() => handleClaimTicket(ticket.id)}
                              className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-brand-500 hover:bg-brand-600 text-white font-bold text-xs shadow-xs transition cursor-pointer"
                            >
                              <Activity className="h-3.5 w-3.5" />
                              <span>Ambil</span>
                            </button>
                          ) : (
                            <Link
                              href={isCollector ? `/kolektor/tickets/${ticket.id}` : `/teknisi/tickets/${ticket.id}`}
                              className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-900 dark:text-white font-bold text-xs transition"
                            >
                              <span>Detail</span>
                              <ChevronRight className="h-3.5 w-3.5" />
                            </Link>
                          )}
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
                        {getPriorityBadge(ticket.priority)}
                      </div>

                      <div className="rounded-xl bg-gray-50 dark:bg-gray-900/60 p-2.5 space-y-1 text-xs">
                        <p className="font-semibold text-gray-900 dark:text-white truncate">
                          {ticket.title}
                        </p>
                        <p className="text-[11px] text-gray-500 dark:text-gray-400 line-clamp-2">
                          {ticket.description || "Tidak ada keterangan tambahan."}
                        </p>
                      </div>

                      <div className="text-[11px] text-gray-500 dark:text-gray-400 space-y-1 pt-1">
                        {ticket.customer_address && (
                          <div className="flex items-center gap-1 truncate">
                            <MapPin className="h-3 w-3 text-gray-400 shrink-0" />
                            <span className="truncate">{ticket.customer_address}</span>
                          </div>
                        )}
                        <div className="flex items-center justify-between text-[10px]">
                          <span>Lapor: {ticket.created_at}</span>
                          {ticket.technician_name && (
                            <span className="font-bold text-gray-700 dark:text-gray-300">
                              PIC: {ticket.technician_name}
                            </span>
                          )}
                        </div>
                      </div>
                    </div>

                    <div className="pt-2 border-t border-gray-100 dark:border-gray-800 flex items-center justify-end">
                      {activeTab === "open" ? (
                        <button
                          type="button"
                          onClick={() => handleClaimTicket(ticket.id)}
                          className="w-full inline-flex items-center justify-center gap-1.5 py-2 rounded-xl bg-brand-500 hover:bg-brand-600 text-white font-bold text-xs shadow-xs transition cursor-pointer"
                        >
                          <Activity className="h-3.5 w-3.5" />
                          <span>Ambil Pekerjaan Ini</span>
                        </button>
                      ) : (
                        <Link
                          href={isCollector ? `/kolektor/tickets/${ticket.id}` : `/teknisi/tickets/${ticket.id}`}
                          className="w-full inline-flex items-center justify-center gap-1.5 py-2 rounded-xl bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-900 dark:text-white font-bold text-xs transition"
                        >
                          <span>Kelola Tiket</span>
                          <ChevronRight className="h-3.5 w-3.5" />
                        </Link>
                      )}
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
