import { AppLayout } from "@/components/layout/app-layout"
import {
  Ticket,
  Plus,
  Phone,
  Wrench,
  CheckCircle2,
  X,
  Search,
  Clock,
  Settings,
  ChevronRight,
  MessageCircle,
} from "lucide-react"
import { EmptyState } from "@/components/ui/empty-state"
import { PageProps } from "@/types"
import { timeAgo, cn } from "@/lib/utils"
import { adminSidebarItems, adminNavItems, adminBrand } from "@/lib/admin-nav"
import { useForm, router } from "@inertiajs/react"
import { useState, useMemo } from "react"
import Chart from "react-apexcharts"
import { ViewModeSwitcher, type ViewMode } from "@/components/tailadmin/ui/view-mode-switcher"

interface TicketItem {
  id: number
  title: string
  description: string | null
  status: string
  priority: string | null
  customer_name: string | null
  customer_phone: string | null
  technician_name: string | null
  assigned_to: number | null
  router_name: string | null
  created_at: string | null
}

interface TechOpt {
  id: number
  name: string
}
interface CustOpt {
  id: number
  name: string
  phone: string | null
}
interface RouterOpt {
  id: number
  name: string
}

export default function TroublePage({
  tickets = [],
  technicians = [],
  customers = [],
  routers = [],
}: PageProps<{
  tickets: TicketItem[]
  technicians: TechOpt[]
  customers: CustOpt[]
  routers: RouterOpt[]
  companyName?: string
  tenantName?: string
}>) {
  const [filter, setFilter] = useState<string>("all")
  const [priorityFilter, setPriorityFilter] = useState<string>("all")
  const [searchQuery, setSearchQuery] = useState("")
  const [showForm, setShowForm] = useState(false)
  const [assigningMap, setAssigningMap] = useState<Record<number, string>>({})
  const [managingTicket, setManagingTicket] = useState<TicketItem | null>(null)
  const [viewMode, setViewMode] = useState<ViewMode>("table")

  const form = useForm({
    title: "",
    customer_id: "",
    assigned_to: "",
    description: "",
    priority: "medium",
    router_id: "",
  })

  const submit = (e: React.FormEvent) => {
    e.preventDefault()
    form.post("/admin/trouble/create", {
      preserveScroll: true,
      onSuccess: () => {
        setShowForm(false)
        form.reset()
      },
    })
  }

  const handleAssign = (ticketId: number) => {
    const techId = assigningMap[ticketId]
    if (!techId) {
      alert("Pilih teknisi terlebih dahulu.")
      return
    }
    router.post(`/admin/trouble/assign/${ticketId}`, { assigned_to: techId }, { preserveScroll: true })
  }

  const handleClose = (ticketId: number) => {
    if (confirm("Tandai tiket ini selesai dan tutup?")) {
      router.post(`/admin/trouble/close/${ticketId}`, {}, { preserveScroll: true })
    }
  }

  const counts = useMemo(() => {
    return {
      total: tickets.length,
      pending: tickets.filter((t) => t.status === "pending").length,
      in_progress: tickets.filter((t) => t.status === "in_progress").length,
      resolved: tickets.filter((t) => t.status === "resolved" || t.status === "closed").length,
    }
  }, [tickets])

  const resolutionRate = counts.total > 0 ? Math.round((counts.resolved / counts.total) * 100) : 0

  const statusDonutOptions = useMemo(() => ({
    chart: { type: "donut" as const, fontFamily: "Outfit, Inter, sans-serif" },
    labels: ["Selesai & Tutup", "Dalam Pengerjaan", "Pending (Antrian)"],
    colors: ["#10B981", "#06B6D4", "#F59E0B"],
    stroke: { show: false },
    dataLabels: { enabled: false },
    legend: { position: "bottom" as const, fontSize: "11px", fontWeight: 600, labels: { colors: "#64748B" } },
    plotOptions: {
      pie: {
        donut: {
          size: "72%",
          labels: {
            show: true,
            total: {
              show: true,
              label: "Total Tiket",
              fontSize: "11px",
              fontWeight: 600,
              color: "#64748B",
              formatter: () => `${counts.total}`,
            },
            value: { fontSize: "16px", fontWeight: 700, color: "#0F172A" },
          },
        },
      },
    },
    tooltip: {
      theme: "light",
      y: { formatter: (val: number) => `${val} Laporan` },
    },
  }), [counts])

  const slaRadialOptions = useMemo(() => ({
    chart: { type: "radialBar" as const, fontFamily: "Outfit, Inter, sans-serif", sparkline: { enabled: true } },
    plotOptions: {
      radialBar: {
        startAngle: -135,
        endAngle: 135,
        hollow: { size: "70%" },
        track: { background: "#F1F5F9", strokeWidth: "100%" },
        dataLabels: {
          name: { show: true, fontSize: "11px", color: "#64748B", offsetY: -8 },
          value: {
            show: true,
            fontSize: "20px",
            fontWeight: 700,
            color: resolutionRate >= 80 ? "#10B981" : resolutionRate >= 50 ? "#06B6D4" : "#F59E0B",
            offsetY: 4,
            formatter: (val: number) => `${val}%`,
          },
        },
      },
    },
    fill: {
      type: "gradient",
      gradient: {
        shade: "dark",
        type: "horizontal",
        gradientToColors: [resolutionRate >= 80 ? "#10B981" : "#06B6D4"],
        stops: [0, 100],
      },
    },
    stroke: { dashArray: 4 },
    colors: [resolutionRate >= 80 ? "#10B981" : "#0073C6"],
    labels: ["Tingkat Selesai"],
  }), [resolutionRate])

  const filtered = useMemo(() => {
    return tickets.filter((t) => {
      if (filter !== "all" && t.status !== filter) return false
      if (priorityFilter !== "all" && t.priority !== priorityFilter) return false
      if (searchQuery.trim()) {
        const q = searchQuery.toLowerCase().trim()
        const matchTitle = (t.title ?? "").toLowerCase().includes(q)
        const matchDesc = (t.description ?? "").toLowerCase().includes(q)
        const matchCust = (t.customer_name ?? "").toLowerCase().includes(q)
        const matchTech = (t.technician_name ?? "").toLowerCase().includes(q)
        const matchRouter = (t.router_name ?? "").toLowerCase().includes(q)
        if (!matchTitle && !matchDesc && !matchCust && !matchTech && !matchRouter) return false
      }
      return true
    })
  }, [tickets, filter, priorityFilter, searchQuery])

  return (
    <AppLayout
      title="Tiket Gangguan"
      brand={adminBrand}
      sidebarItems={adminSidebarItems}
      navItems={adminNavItems}
    >
      <div className="space-y-6">
        {/* ── Executive Multi-Type Visual Analytics ── */}
        <div className="grid grid-cols-1 lg:grid-cols-12 gap-4 sm:gap-5">
          {/* Card 1: Tingkat Penyelesaian SLA Tiket (5 Cols) */}
          <div className="lg:col-span-5 rounded-2xl border border-gray-200 bg-white p-4 sm:p-5 shadow-xs dark:border-gray-800 dark:bg-white/[0.03] flex flex-col justify-between">
            <div className="flex items-center justify-between border-b border-gray-100 dark:border-gray-800/80 pb-3">
              <div>
                <h3 className="text-sm font-bold text-gray-900 dark:text-white flex items-center gap-2">
                  <CheckCircle2 className="h-4 w-4 text-emerald-500" />
                  <span>SLA &amp; Penyelesaian Tiket</span>
                </h3>
                <p className="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                  Rasio penanganan gangguan oleh tim teknisi
                </p>
              </div>
              <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-bold bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400">
                {counts.resolved} / {counts.total} Selesai
              </span>
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-12 gap-3 items-center py-2">
              <div className="sm:col-span-5 flex justify-center">
                <Chart
                  options={slaRadialOptions as any}
                  series={[resolutionRate]}
                  type="radialBar"
                  height={150}
                  width="100%"
                />
              </div>
              <div className="sm:col-span-7 space-y-2">
                <button
                  type="button"
                  onClick={() => setFilter(filter === "resolved" ? "all" : "resolved")}
                  className={cn(
                    "w-full flex items-center justify-between rounded-xl p-2.5 transition text-left border cursor-pointer",
                    filter === "resolved"
                      ? "bg-emerald-50 border-emerald-300 dark:bg-emerald-500/15 dark:border-emerald-500/30"
                      : "bg-gray-50/80 border-gray-100 dark:bg-gray-900/50 dark:border-gray-800/60 hover:bg-gray-100 dark:hover:bg-gray-800/50"
                  )}
                >
                  <span className="text-xs text-gray-600 dark:text-gray-300 flex items-center gap-1.5">
                    <CheckCircle2 className="h-3.5 w-3.5 text-emerald-500" /> Selesai &amp; Tutup
                  </span>
                  <span className="font-bold text-emerald-600 dark:text-emerald-400 text-xs">{counts.resolved}</span>
                </button>

                <button
                  type="button"
                  onClick={() => setFilter(filter === "in_progress" ? "all" : "in_progress")}
                  className={cn(
                    "w-full flex items-center justify-between rounded-xl p-2.5 transition text-left border cursor-pointer",
                    filter === "in_progress"
                      ? "bg-cyan-50 border-cyan-300 dark:bg-cyan-500/15 dark:border-cyan-500/30"
                      : "bg-gray-50/80 border-gray-100 dark:bg-gray-900/50 dark:border-gray-800/60 hover:bg-gray-100 dark:hover:bg-gray-800/50"
                  )}
                >
                  <span className="text-xs text-gray-600 dark:text-gray-300 flex items-center gap-1.5">
                    <Wrench className="h-3.5 w-3.5 text-cyan-500" /> Sedang Dikerjakan
                  </span>
                  <span className="font-bold text-cyan-600 dark:text-cyan-400 text-xs">{counts.in_progress}</span>
                </button>

                <button
                  type="button"
                  onClick={() => setFilter(filter === "pending" ? "all" : "pending")}
                  className={cn(
                    "w-full flex items-center justify-between rounded-xl p-2.5 transition text-left border cursor-pointer",
                    filter === "pending"
                      ? "bg-amber-50 border-amber-300 dark:bg-amber-500/15 dark:border-amber-500/30"
                      : "bg-gray-50/80 border-gray-100 dark:bg-gray-900/50 dark:border-gray-800/60 hover:bg-gray-100 dark:hover:bg-gray-800/50"
                  )}
                >
                  <span className="text-xs text-gray-600 dark:text-gray-300 flex items-center gap-1.5">
                    <Clock className="h-3.5 w-3.5 text-amber-500" /> Pending (Antrian)
                  </span>
                  <span className="font-bold text-amber-600 dark:text-amber-400 text-xs">{counts.pending}</span>
                </button>
              </div>
            </div>
          </div>

          {/* Card 2: Sebaran Status Tiket & Komposisi (7 Cols) */}
          <div className="lg:col-span-7 rounded-2xl border border-gray-200 bg-white p-4 sm:p-5 shadow-xs dark:border-gray-800 dark:bg-white/[0.03] flex flex-col justify-between">
            <div className="flex items-center justify-between border-b border-gray-100 dark:border-gray-800/80 pb-3">
              <div>
                <h3 className="text-sm font-bold text-gray-900 dark:text-white flex items-center gap-2">
                  <Ticket className="h-4 w-4 text-brand-500" />
                  <span>Komposisi Status Tiket Gangguan</span>
                </h3>
                <p className="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                  Distribusi laporan operasional lapangan
                </p>
              </div>
              <button
                type="button"
                onClick={() => setFilter("all")}
                className={cn(
                  "inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-bold transition cursor-pointer",
                  filter === "all"
                    ? "bg-brand-500 text-white shadow-xs"
                    : "bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300 hover:bg-gray-200"
                )}
              >
                Semua ({counts.total})
              </button>
            </div>

            <div className="pt-2">
              <Chart
                options={statusDonutOptions as any}
                series={[counts.resolved, counts.in_progress, counts.pending]}
                type="donut"
                height={170}
                width="100%"
              />
            </div>
          </div>
        </div>

        {/* ── 1-LINE RESPONSIVE INTEGRATED TOOLBAR ── */}
        <div className="rounded-2xl border border-gray-200 bg-white p-3 sm:p-4 shadow-xs dark:border-gray-800 dark:bg-white/[0.03] flex flex-col gap-2.5 sm:gap-3 lg:flex-row lg:items-center lg:justify-between">
          {/* Left side: Search bar & Status / Priority dropdowns */}
          <div className="flex flex-wrap items-center gap-2 sm:gap-3 flex-1 min-w-0">
            {/* Search */}
            <div className="relative flex-1 min-w-[180px] sm:min-w-[220px] w-full sm:w-auto">
              <Search className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-gray-400" />
              <input
                type="text"
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                placeholder="Cari judul tiket, pelanggan, teknisi, router..."
                className="h-10 w-full rounded-xl border border-gray-200 bg-gray-50/75 dark:border-gray-800 dark:bg-gray-900/50 pl-9 pr-8 text-xs font-medium text-gray-900 dark:text-white placeholder:text-gray-400 focus:border-brand-500 focus:outline-hidden"
              />
              {searchQuery && (
                <button
                  type="button"
                  onClick={() => setSearchQuery("")}
                  className="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-white"
                >
                  <X className="size-3.5" />
                </button>
              )}
            </div>

            {/* Status & Priority Filter */}
            <div className="flex items-center gap-2 w-full sm:w-auto flex-1 sm:flex-none">
              <select
                value={filter}
                onChange={(e) => setFilter(e.target.value)}
                className="h-10 rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 px-3 text-xs font-semibold text-gray-700 dark:text-gray-200 cursor-pointer flex-1 sm:flex-none sm:w-auto shrink-0 focus:border-brand-500 focus:outline-hidden"
              >
                <option value="all">Semua Status</option>
                <option value="pending">Pending</option>
                <option value="in_progress">Dikerjakan</option>
                <option value="resolved">Selesai</option>
                <option value="closed">Tutup</option>
              </select>

              <select
                value={priorityFilter}
                onChange={(e) => setPriorityFilter(e.target.value)}
                className="h-10 rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 px-3 text-xs font-semibold text-gray-700 dark:text-gray-200 cursor-pointer flex-1 sm:flex-none sm:w-auto shrink-0 focus:border-brand-500 focus:outline-hidden"
              >
                <option value="all">Semua Prioritas</option>
                <option value="urgent">Urgent</option>
                <option value="high">High</option>
                <option value="medium">Medium</option>
                <option value="low">Low</option>
              </select>
            </div>
          </div>

          {/* Right side: ViewModeSwitcher + Buat Tiket Baru */}
          <div className="flex items-center gap-2 shrink-0 w-full lg:w-auto">
            <ViewModeSwitcher
              value={viewMode}
              onChange={setViewMode}
              storageKey="nodera_trouble_view_mode"
              className="shrink-0"
            />
            <button
              type="button"
              onClick={() => setShowForm(true)}
              className="h-10 px-3.5 sm:px-4 rounded-xl bg-brand-500 hover:bg-brand-600 active:scale-95 text-xs font-bold text-white shadow-xs flex items-center justify-center gap-1.5 flex-1 sm:flex-none cursor-pointer transition"
            >
              <Plus className="size-4" />
              <span>Buat Tiket Baru</span>
            </button>
          </div>
        </div>

        {/* ── MASTER CARD CONTAINER ── */}
        <div className="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xs dark:border-gray-800 dark:bg-white/[0.03]">

          {/* ── TABLE / GRID VIEW ── */}
          {filtered.length === 0 ? (
            <div className="p-8 text-center">
              <EmptyState
                icon={<Ticket className="h-8 w-8 text-brand-500" />}
                title="Tidak ada tiket gangguan"
                description="Tiket gangguan yang dilaporkan pelanggan atau teknisi akan muncul di sini."
              />
            </div>
          ) : viewMode === "table" ? (
            /* TABLE MODE */
            <div className="w-full overflow-x-auto custom-scrollbar">
              <table className="w-full text-left text-xs text-gray-600 dark:text-gray-300 min-w-[950px] border-collapse">
                <thead className="border-b border-gray-200 bg-gray-50/75 text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:border-gray-800 dark:bg-gray-900/50 dark:text-gray-400">
                  <tr>
                    <th className="px-4 py-3.5">Tiket & Laporan</th>
                    <th className="px-4 py-3.5">Pelanggan</th>
                    <th className="px-4 py-3.5">Router</th>
                    <th className="px-4 py-3.5">Prioritas</th>
                    <th className="px-4 py-3.5">Status</th>
                    <th className="px-4 py-3.5">Teknisi / Disposisi</th>
                    <th className="px-4 py-3.5 text-right">Aksi</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-gray-100 dark:divide-gray-800">
                  {filtered.map((t) => (
                    <tr key={t.id} className="hover:bg-gray-50/50 dark:hover:bg-white/[0.02] transition">
                      {/* Tiket Column */}
                      <td className="px-4 py-3.5">
                        <div className="flex items-start gap-2.5 max-w-sm">
                          <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-brand-50 dark:bg-brand-500/10 text-brand-500 border border-brand-200 dark:border-brand-500/20 mt-0.5">
                            <Ticket className="h-4 w-4" />
                          </div>
                          <div className="min-w-0">
                            <div className="font-bold text-gray-900 dark:text-white truncate">
                              {t.title || t.description}
                            </div>
                            {t.description && t.title && t.description !== t.title ? (
                              <div className="text-[11px] text-gray-500 dark:text-gray-400 line-clamp-1 mt-0.5">
                                {t.description}
                              </div>
                            ) : null}
                            <div className="text-[10px] text-gray-400 mt-0.5">
                              #{t.id} • {timeAgo(t.created_at)}
                            </div>
                          </div>
                        </div>
                      </td>

                      {/* Pelanggan Column */}
                      <td className="px-4 py-3.5">
                        <div className="font-semibold text-gray-900 dark:text-white">
                          {t.customer_name ?? "Pelanggan Umum"}
                        </div>
                        {t.customer_phone ? (
                          <div className="flex items-center gap-1 font-mono text-[11px] text-gray-500 dark:text-gray-400 mt-0.5">
                            <Phone className="h-3 w-3" />
                            <span>{t.customer_phone}</span>
                          </div>
                        ) : null}
                      </td>

                      {/* Router Column */}
                      <td className="px-4 py-3.5 font-medium text-gray-700 dark:text-gray-300">
                        {t.router_name ?? "Semua Router"}
                      </td>

                      {/* Prioritas Column */}
                      <td className="px-4 py-3.5">
                        {t.priority === "urgent" || t.priority === "high" ? (
                          <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold bg-rose-500 text-white shadow-xs capitalize whitespace-nowrap">
                            {t.priority}
                          </span>
                        ) : t.priority === "medium" ? (
                          <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold bg-amber-500 text-white shadow-xs capitalize whitespace-nowrap">
                            Medium
                          </span>
                        ) : (
                          <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold bg-gray-500 text-white shadow-xs capitalize whitespace-nowrap">
                            Low
                          </span>
                        )}
                      </td>

                      {/* Status Column */}
                      <td className="px-4 py-3.5">
                        {t.status === "pending" ? (
                          <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold bg-amber-500 text-white shadow-xs whitespace-nowrap">
                            Pending
                          </span>
                        ) : t.status === "in_progress" ? (
                          <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold bg-brand-500 text-white shadow-xs whitespace-nowrap">
                            Dikerjakan
                          </span>
                        ) : t.status === "resolved" ? (
                          <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold bg-emerald-500 text-white shadow-xs whitespace-nowrap">
                            Selesai
                          </span>
                        ) : (
                          <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold bg-gray-500 text-white shadow-xs whitespace-nowrap">
                            Tutup
                          </span>
                        )}
                      </td>

                      {/* Teknisi / Disposisi Column */}
                      <td className="px-4 py-3.5">
                        {t.technician_name ? (
                          <span className="font-semibold text-gray-900 dark:text-white">
                            {t.technician_name}
                          </span>
                        ) : (
                          <span className="text-gray-400 italic">Belum ditugaskan</span>
                        )}
                      </td>

                      {/* Single Action Button: Kelola (Solid Icon-Only) */}
                      <td className="px-4 py-3.5 text-right whitespace-nowrap">
                        <button
                          type="button"
                          onClick={() => setManagingTicket(t)}
                          className="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-brand-500 hover:bg-brand-600 active:scale-95 text-white shadow-xs transition cursor-pointer"
                          title="Kelola Tiket"
                        >
                          <Settings className="h-4 w-4" />
                        </button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          ) : (
            /* GRID / CARD MODE */
            <div className="p-4 sm:p-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
              {filtered.map((t) => (
                <div
                  key={t.id}
                  className="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900/40 p-4 transition shadow-xs space-y-3"
                >
                  <div className="flex items-start justify-between gap-2">
                    <div className="flex items-start gap-2.5 min-w-0 flex-1">
                      <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-brand-50 dark:bg-brand-500/10 text-brand-500 border border-brand-200 dark:border-brand-500/20">
                        <Ticket className="h-4.5 w-4.5" />
                      </div>
                      <div className="min-w-0">
                        <h4 className="font-bold text-sm text-gray-900 dark:text-white truncate">
                          {t.title || t.description}
                        </h4>
                        <div className="text-xs text-gray-500 dark:text-gray-400 truncate mt-0.5">
                          {t.customer_name ?? "Umum"} {t.customer_phone ? `• ${t.customer_phone}` : ""}
                        </div>
                      </div>
                    </div>
                  </div>

                  {/* Sunken Box */}
                  <div className="rounded-xl border border-gray-100 dark:border-gray-800 bg-gray-50/75 dark:bg-gray-900/60 p-2.5 space-y-1.5 text-xs text-gray-600 dark:text-gray-300">
                    <div className="flex items-center justify-between">
                      <span className="text-gray-400">Status</span>
                      {t.status === "pending" ? (
                        <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold bg-amber-500 text-white shadow-xs whitespace-nowrap">
                          Pending
                        </span>
                      ) : t.status === "in_progress" ? (
                        <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold bg-brand-500 text-white shadow-xs whitespace-nowrap">
                          Dikerjakan
                        </span>
                      ) : t.status === "resolved" ? (
                        <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold bg-emerald-500 text-white shadow-xs whitespace-nowrap">
                          Selesai
                        </span>
                      ) : (
                        <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold bg-gray-500 text-white shadow-xs whitespace-nowrap">
                          Tutup
                        </span>
                      )}
                    </div>

                    <div className="flex items-center justify-between">
                      <span className="text-gray-400">Prioritas</span>
                      <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold bg-brand-500 text-white shadow-xs uppercase whitespace-nowrap">
                        {t.priority || "Normal"}
                      </span>
                    </div>

                    <div className="flex items-center justify-between">
                      <span className="text-gray-400">Teknisi</span>
                      <span className="font-medium text-gray-800 dark:text-gray-200 truncate max-w-[120px]">
                        {t.technician_name || "Belum diassign"}
                      </span>
                    </div>
                  </div>

                  {/* Actions Row */}
                  <div className="pt-2 border-t border-gray-100 dark:border-gray-800 flex items-center justify-end">
                    <button
                      type="button"
                      onClick={() => setManagingTicket(t)}
                      className="w-full inline-flex items-center justify-center gap-1.5 px-3 py-1.5 rounded-lg bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200 font-bold text-xs transition cursor-pointer"
                    >
                      <Settings className="h-3.5 w-3.5" />
                      <span>Kelola Tiket</span>
                    </button>
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>
      </div>

      {/* ── MODAL INTERAKTIF: KELOLA TIKET GANGGUAN ── */}
      {managingTicket && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-xs">
          <div className="w-full max-w-md rounded-2xl border border-gray-200 bg-white p-5 shadow-2xl dark:border-gray-800 dark:bg-gray-900 space-y-4 max-h-[92vh] overflow-y-auto custom-scrollbar">
            {/* Modal Header */}
            <div className="flex items-center justify-between border-b border-gray-100 pb-3 dark:border-gray-800">
              <div className="flex items-center gap-3">
                <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-brand-500 text-white shadow-xs font-bold text-sm">
                  <Ticket className="h-5 w-5" />
                </div>
                <div className="min-w-0">
                  <h3 className="font-bold text-sm text-gray-900 dark:text-white truncate">
                    Tiket #{managingTicket.id}: {managingTicket.title}
                  </h3>
                  <span className="text-[11px] text-gray-500">
                    Pelanggan: {managingTicket.customer_name ?? "Pelanggan Umum"}
                  </span>
                </div>
              </div>
              <button
                type="button"
                onClick={() => setManagingTicket(null)}
                className="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-800 dark:hover:text-gray-200 transition cursor-pointer"
              >
                <X className="h-5 w-5" />
              </button>
            </div>

            {/* Quick Summary Card */}
            <div className="rounded-xl border border-gray-200/80 bg-gray-50/70 p-3.5 dark:border-gray-800 dark:bg-gray-800/40 space-y-2 text-xs">
              <div className="flex items-center justify-between">
                <span className="text-gray-500 dark:text-gray-400">Status Tiket</span>
                {managingTicket.status === "pending" ? (
                  <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold bg-amber-500 text-white shadow-xs whitespace-nowrap">
                    Pending
                  </span>
                ) : managingTicket.status === "in_progress" ? (
                  <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold bg-brand-500 text-white shadow-xs whitespace-nowrap">
                    Dikerjakan
                  </span>
                ) : managingTicket.status === "resolved" ? (
                  <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold bg-emerald-500 text-white shadow-xs whitespace-nowrap">
                    Selesai
                  </span>
                ) : (
                  <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold bg-gray-500 text-white shadow-xs whitespace-nowrap">
                    Tutup
                  </span>
                )}
              </div>
              <div className="flex items-center justify-between">
                <span className="text-gray-500 dark:text-gray-400">Tingkat Prioritas</span>
                <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold bg-rose-500 text-white shadow-xs uppercase whitespace-nowrap">
                  {managingTicket.priority || "Normal"}
                </span>
              </div>
              <div className="flex items-center justify-between">
                <span className="text-gray-500 dark:text-gray-400">Teknisi Bertugas</span>
                <span className="font-semibold text-gray-900 dark:text-white">
                  {managingTicket.technician_name || "Belum Ditugaskan"}
                </span>
              </div>
              <div className="flex items-center justify-between">
                <span className="text-gray-500 dark:text-gray-400">Router / Wilayah</span>
                <span className="font-medium text-gray-800 dark:text-gray-200">
                  {managingTicket.router_name ?? "Semua Router"}
                </span>
              </div>
              {managingTicket.description && (
                <div className="pt-1 border-t border-gray-200/60 dark:border-gray-800">
                  <span className="text-gray-500 dark:text-gray-400 block mb-1">Deskripsi Keluhan</span>
                  <p className="text-gray-800 dark:text-gray-200 bg-white dark:bg-gray-900 p-2 rounded-lg border border-gray-100 dark:border-gray-800">
                    {managingTicket.description}
                  </p>
                </div>
              )}
            </div>

            {/* Quick Action / Assign Controls */}
            <div className="space-y-2 pt-1 text-xs">
              {(managingTicket.status === "pending" || managingTicket.status === "in_progress") && (
                <div className="p-3 rounded-xl bg-gray-50/70 border border-gray-100 dark:bg-gray-800/40 dark:border-gray-800 space-y-2">
                  <span className="font-bold text-gray-900 dark:text-white block">
                    Disposisi Teknisi Lapangan
                  </span>
                  <div className="flex items-center gap-2">
                    <select
                      value={assigningMap[managingTicket.id] ?? (managingTicket.assigned_to ? String(managingTicket.assigned_to) : "")}
                      onChange={(e) => setAssigningMap((prev) => ({ ...prev, [managingTicket.id]: e.target.value }))}
                      className="h-9 flex-1 rounded-xl border border-gray-200 bg-white px-3 text-xs text-gray-800 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-200"
                    >
                      <option value="">-- Pilih Teknisi --</option>
                      {technicians.map((u) => (
                        <option key={u.id} value={String(u.id)}>
                          {u.name}
                        </option>
                      ))}
                    </select>
                    <button
                      type="button"
                      onClick={() => handleAssign(managingTicket.id)}
                      className="inline-flex items-center gap-1 px-3 py-2 rounded-xl bg-brand-500 hover:bg-brand-600 text-xs font-bold text-white shadow-xs transition cursor-pointer"
                    >
                      <Wrench className="h-3.5 w-3.5" />
                      <span>Assign</span>
                    </button>
                  </div>
                </div>
              )}

              {managingTicket.customer_phone && (
                <a
                  href={`https://wa.me/${managingTicket.customer_phone.replace(/[^0-9]/g, "").replace(/^0/, "62")}`}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="w-full group flex items-center justify-between p-3 rounded-xl bg-gray-50/70 hover:bg-emerald-50/50 border border-gray-100 hover:border-emerald-200 dark:bg-gray-800/40 dark:hover:bg-gray-800 dark:border-gray-800 dark:hover:border-emerald-900/60 transition cursor-pointer"
                >
                  <div className="flex items-center gap-3">
                    <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600 dark:bg-emerald-950 dark:text-emerald-400 whitespace-nowrap">
                      <MessageCircle className="h-4 w-4" />
                    </div>
                    <div className="text-left">
                      <div className="font-bold text-gray-900 dark:text-white">
                        Hubungi Pelanggan
                      </div>
                      <div className="text-[10px] text-gray-500">Kirim pesan WhatsApp ke {managingTicket.customer_phone}</div>
                    </div>
                  </div>
                  <ChevronRight className="h-4 w-4 text-gray-400 group-hover:text-emerald-500 transition-colors" />
                </a>
              )}

              {(managingTicket.status === "pending" || managingTicket.status === "in_progress") && (
                <button
                  type="button"
                  onClick={() => {
                    const id = managingTicket.id
                    setManagingTicket(null)
                    handleClose(id)
                  }}
                  className="w-full group flex items-center justify-between p-3 rounded-xl bg-emerald-50/70 hover:bg-emerald-100/70 border border-emerald-200 dark:bg-emerald-950/40 dark:hover:bg-emerald-900/60 dark:border-emerald-900/60 transition cursor-pointer"
                >
                  <div className="flex items-center gap-3">
                    <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-500 text-white shadow-xs whitespace-nowrap">
                      <CheckCircle2 className="h-4 w-4" />
                    </div>
                    <div className="text-left">
                      <div className="font-bold text-emerald-700 dark:text-emerald-300">
                        Tandai Tiket Selesai
                      </div>
                      <div className="text-[10px] text-emerald-600/80 dark:text-emerald-400/80">Selesaikan penanganan gangguan ini</div>
                    </div>
                  </div>
                  <ChevronRight className="h-4 w-4 text-emerald-500" />
                </button>
              )}
            </div>
          </div>
        </div>
      )}

      {/* ── MODAL BUAT TIKET GANGGUAN ── */}
      {showForm && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
          <div className="fixed inset-0 bg-black/60 backdrop-blur-sm" onClick={() => setShowForm(false)} />
          <div className="relative flex max-h-[92vh] w-full max-w-lg flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 text-gray-900 dark:text-white shadow-2xl animate-in zoom-in-95 duration-150">
            {/* Modal Header */}
            <div className="flex items-center justify-between border-b border-gray-100 dark:border-gray-800 px-5 py-4 shrink-0">
              <div className="flex items-center gap-3">
                <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-brand-50 dark:bg-brand-500/10 text-brand-500 border border-brand-200 dark:border-brand-500/20">
                  <Ticket className="h-5 w-5" />
                </div>
                <div>
                  <h3 className="font-bold text-base text-gray-900 dark:text-white">
                    Buat Tiket Gangguan Baru
                  </h3>
                  <p className="text-xs text-gray-500 dark:text-gray-400">Laporan keluhan pelanggan dan tugas teknisi</p>
                </div>
              </div>
              <button
                onClick={() => setShowForm(false)}
                className="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-700 dark:hover:text-white"
              >
                <X className="h-4 w-4" />
              </button>
            </div>

            <form onSubmit={submit} className="flex min-h-0 flex-1 flex-col overflow-hidden">
              <div className="flex-1 overflow-y-auto p-5 space-y-4">
                <div className="space-y-1.5">
                  <label className="text-xs font-semibold text-gray-700 dark:text-gray-300">
                    Judul Tiket / Topik Gangguan
                  </label>
                  <input
                    value={form.data.title}
                    onChange={(e) => form.setData("title", e.target.value)}
                    placeholder="cth: Internet Mati / Redaman Loss"
                    required
                    className="w-full h-10 rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-950 px-3 text-xs text-gray-900 dark:text-white placeholder:text-gray-400 focus:outline-none focus:border-brand-500"
                  />
                </div>

                <div className="grid gap-3 sm:grid-cols-2">
                  <div className="space-y-1.5">
                    <label className="text-xs font-semibold text-gray-700 dark:text-gray-300">
                      Pelanggan (Opsional)
                    </label>
                    <select
                      value={form.data.customer_id}
                      onChange={(e) => form.setData("customer_id", e.target.value)}
                      className="w-full h-10 rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-950 px-3 text-xs text-gray-900 dark:text-white focus:outline-none focus:border-brand-500"
                    >
                      <option value="">Pilih pelanggan...</option>
                      {customers.map((c) => (
                        <option key={c.id} value={String(c.id)}>
                          {c.name} {c.phone ? `(${c.phone})` : ""}
                        </option>
                      ))}
                    </select>
                  </div>

                  <div className="space-y-1.5">
                    <label className="text-xs font-semibold text-gray-700 dark:text-gray-300">
                      Tugaskan Teknisi (Opsional)
                    </label>
                    <select
                      value={form.data.assigned_to}
                      onChange={(e) => form.setData("assigned_to", e.target.value)}
                      className="w-full h-10 rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-950 px-3 text-xs text-gray-900 dark:text-white focus:outline-none focus:border-brand-500"
                    >
                      <option value="">Belum diassign (Pending)</option>
                      {technicians.map((u) => (
                        <option key={u.id} value={String(u.id)}>
                          {u.name}
                        </option>
                      ))}
                    </select>
                  </div>
                </div>

                <div className="grid gap-3 sm:grid-cols-2">
                  <div className="space-y-1.5">
                    <label className="text-xs font-semibold text-gray-700 dark:text-gray-300">
                      Tingkat Prioritas
                    </label>
                    <select
                      value={form.data.priority}
                      onChange={(e) => form.setData("priority", e.target.value)}
                      className="w-full h-10 rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-950 px-3 text-xs text-gray-900 dark:text-white focus:outline-none focus:border-brand-500"
                    >
                      <option value="low">Low (Rendah)</option>
                      <option value="medium">Medium (Normal)</option>
                      <option value="high">High (Tinggi)</option>
                      <option value="urgent">Urgent (Kritis)</option>
                    </select>
                  </div>

                  <div className="space-y-1.5">
                    <label className="text-xs font-semibold text-gray-700 dark:text-gray-300">
                      Router MikroTik (Opsional)
                    </label>
                    <select
                      value={form.data.router_id}
                      onChange={(e) => form.setData("router_id", e.target.value)}
                      className="w-full h-10 rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-950 px-3 text-xs text-gray-900 dark:text-white focus:outline-none focus:border-brand-500"
                    >
                      <option value="">Semua router</option>
                      {routers.map((r) => (
                        <option key={r.id} value={String(r.id)}>
                          {r.name}
                        </option>
                      ))}
                    </select>
                  </div>
                </div>

                <div className="space-y-1.5">
                  <label className="text-xs font-semibold text-gray-700 dark:text-gray-300">
                    Deskripsi / Keluhan
                  </label>
                  <textarea
                    value={form.data.description}
                    onChange={(e) => form.setData("description", e.target.value)}
                    placeholder="Detail kendala yang dilaporkan pelanggan..."
                    rows={3}
                    required
                    className="w-full rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-950 p-3 text-xs text-gray-900 dark:text-white placeholder:text-gray-400 focus:outline-none focus:border-brand-500"
                  />
                  {form.errors.description && (
                    <p className="text-xs text-rose-500">{form.errors.description}</p>
                  )}
                </div>
              </div>

              {/* Bottom Actions */}
              <div className="border-t border-gray-100 dark:border-gray-800 p-4 bg-gray-50/50 dark:bg-gray-900/50 shrink-0 flex items-center gap-2">
                <button
                  type="button"
                  onClick={() => setShowForm(false)}
                  className="flex-1 rounded-xl h-10 text-xs font-bold border border-gray-200 bg-white hover:bg-gray-100 dark:border-gray-800 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200 transition"
                >
                  Batal
                </button>
                <button
                  type="submit"
                  disabled={form.processing}
                  className="flex-1 rounded-xl h-10 text-xs font-bold bg-brand-500 hover:bg-brand-600 text-white transition flex items-center justify-center gap-1.5 disabled:opacity-50"
                >
                  {form.processing ? "Menyimpan..." : "Kirim Tiket ke Teknisi"}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </AppLayout>
  )
}
