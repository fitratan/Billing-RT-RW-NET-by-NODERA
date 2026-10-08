import { AppLayout } from "@/components/layout/app-layout"
import Chart from "react-apexcharts"
import type { ApexOptions } from "apexcharts"
import {
  UserPlus,
  Pencil,
  Plus,
  MapPin,
  Wallet,
  Copy,
  Check,
  Users,
  MessageCircle,
  Search,
  X,
  CheckCircle2,
  Globe,
  Settings,
  ChevronRight,
  BarChart3,
  PieChart,
} from "lucide-react"
import { EmptyState } from "@/components/ui/empty-state"
import { PageProps } from "@/types"
import { formatIDR, cn } from "@/lib/utils"
import { adminSidebarItems, adminNavItems, adminBrand } from "@/lib/admin-nav"
import { router, useForm } from "@inertiajs/react"
import { useState, useMemo } from "react"
import { MetricCard } from "@/components/tailadmin/MetricCard"
import { ViewModeSwitcher, type ViewMode } from "@/components/ui/view-mode-switcher"

interface Collector {
  id: number
  name: string
  username: string
  phone: string | null
  email: string | null
  collection_area: string | null
  commission_type: string
  commission_value: string
  is_active: boolean
  router: string | null
  balance: number
  paid_count?: number
  paid_amount?: number
  pending_count?: number
  pending_amount?: number
}

export default function CollectorsPage({
  collectors = [],
  create = false,
  loginUrl,
  companyName = "NODERA Billing",
  tenantName,
}: PageProps<{
  collectors: Collector[]
  create: boolean
  loginUrl?: string
  companyName?: string
  tenantName?: string
}>) {
  const [modalOpen, setModalOpen] = useState(create ?? false)
  const [editing, setEditing] = useState<Collector | null>(null)
  const [managingCollector, setManagingCollector] = useState<Collector | null>(null)
  const [copiedKey, setCopiedKey] = useState<string | null>(null)
  const [searchQuery, setSearchQuery] = useState("")
  const [statusFilter, setStatusFilter] = useState<string>("all")
  const [viewMode, setViewMode] = useState<ViewMode>("table")

  const form = useForm({
    name: "",
    username: "",
    password: "",
    phone: "",
    email: "",
    collection_area: "",
    commission_type: "percent",
    commission_value: "0",
  })

  const openAdd = () => {
    setEditing(null)
    form.reset()
    setModalOpen(true)
  }

  const openEdit = (c: Collector) => {
    setEditing(c)
    form.setData({
      name: c.name,
      username: c.username,
      password: "",
      phone: c.phone ?? "",
      email: c.email ?? "",
      collection_area: c.collection_area ?? "",
      commission_type: c.commission_type ?? "percent",
      commission_value: c.commission_value ?? "0",
    })
    setModalOpen(true)
  }

  const submit = (e: React.FormEvent) => {
    e.preventDefault()
    if (editing) {
      form.post(`/admin/collectors/edit/${editing.id}`, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => setModalOpen(false),
      })
    } else {
      form.post("/admin/collectors/add", {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => setModalOpen(false),
      })
    }
  }

  const url = loginUrl ?? (typeof window !== "undefined" ? window.location.origin + "/kolektor/login" : "/kolektor/login")

  const copyToClipboard = (text: string, key: string) => {
    navigator.clipboard?.writeText(text).then(() => {
      setCopiedKey(key)
      setTimeout(() => setCopiedKey(null), 1800)
    })
  }

  const sendWaLink = (c: Collector) => {
    const text = `Halo ${c.name},\nBerikut link login ke portal Kolektor Anda:\nLink: ${url}\nUsername: ${c.username}`
    const cleanPhone = (c.phone ?? "").replace(/[^0-9]/g, "")
    const phoneWith62 = cleanPhone.startsWith("0") ? "62" + cleanPhone.slice(1) : cleanPhone
    window.open(`https://wa.me/${phoneWith62}?text=${encodeURIComponent(text)}`, "_blank")
  }

  const activeCount = useMemo(() => collectors.filter((c) => c.is_active).length, [collectors])
  const totalBalance = useMemo(
    () => collectors.reduce((sum, c) => sum + (Number(c.balance) || 0), 0),
    [collectors]
  )
  const totalAreas = useMemo(
    () => new Set(collectors.map((c) => c.collection_area).filter(Boolean)).size,
    [collectors]
  )

  const filteredCollectors = useMemo(() => {
    return collectors.filter((c) => {
      if (statusFilter === "active" && !c.is_active) return false
      if (statusFilter === "inactive" && c.is_active) return false
      if (searchQuery.trim()) {
        const q = searchQuery.toLowerCase().trim()
        const matchName = (c.name ?? "").toLowerCase().includes(q)
        const matchUser = (c.username ?? "").toLowerCase().includes(q)
        const matchPhone = (c.phone ?? "").toLowerCase().includes(q)
        const matchArea = (c.collection_area ?? "").toLowerCase().includes(q)
        if (!matchName && !matchUser && !matchPhone && !matchArea) return false
      }
      return true
    })
  }, [collectors, searchQuery, statusFilter])

  // ── COLLECTOR BALANCE BAR CHART ──
  const collectorBarData = useMemo(() => {
    const sorted = [...collectors].sort((a, b) => (Number(b.balance) || 0) - (Number(a.balance) || 0)).slice(0, 6)
    return {
      categories: sorted.length > 0 ? sorted.map((c) => c.name || c.username) : ["Belum ada data"],
      values: sorted.length > 0 ? sorted.map((c) => Number(c.balance) || 0) : [0],
    }
  }, [collectors])

  const collectorBarOptions: ApexOptions = useMemo(() => ({
    chart: { type: "bar", toolbar: { show: false }, fontFamily: "inherit" },
    colors: ["#0073C6"],
    plotOptions: {
      bar: {
        horizontal: true,
        borderRadius: 6,
        barHeight: "55%",
      },
    },
    dataLabels: {
      enabled: true,
      formatter: (val) => formatIDR(Number(val)),
      style: { fontSize: "10px", fontWeight: "bold" },
    },
    xaxis: {
      categories: collectorBarData.categories,
      labels: {
        style: { colors: "#64748B", fontSize: "11px" },
        formatter: (val) => formatIDR(Number(val)),
      },
      axisBorder: { show: false },
      axisTicks: { show: false },
    },
    yaxis: {
      labels: { style: { colors: "#64748B", fontSize: "11px" } },
    },
    grid: { borderColor: "#F1F5F9", strokeDashArray: 4 },
    tooltip: {
      theme: "light",
      y: { formatter: (val) => formatIDR(val) },
    },
  }), [collectorBarData])

  // ── COLLECTOR AREA BREAKDOWN DONUT CHART ──
  const areaDonutData = useMemo(() => {
    const map = new Map<string, number>()
    collectors.forEach((c) => {
      const area = c.collection_area?.trim() || "Umum / Semua Area"
      map.set(area, (map.get(area) || 0) + 1)
    })
    const sorted = Array.from(map.entries()).sort((a, b) => b[1] - a[1])
    return {
      labels: sorted.length > 0 ? sorted.map(([k]) => k) : ["Semua Area"],
      series: sorted.length > 0 ? sorted.map(([, v]) => v) : [0],
    }
  }, [collectors])

  const areaDonutOptions: ApexOptions = useMemo(() => ({
    chart: { type: "donut", fontFamily: "inherit" },
    labels: areaDonutData.labels,
    colors: ["#10B981", "#0073C6", "#8B5CF6", "#F59E0B", "#EC4899", "#6366F1"],
    legend: { position: "bottom", labels: { colors: "#64748B" } },
    dataLabels: { enabled: true, formatter: (val) => `${Number(val).toFixed(0)}%` },
    stroke: { width: 2, colors: ["transparent"] },
    plotOptions: {
      pie: {
        donut: {
          size: "68%",
          labels: {
            show: true,
            total: {
              show: true,
              label: "Total Kolektor",
              color: "#64748B",
              formatter: () => `${collectors.length} Orang`,
            },
          },
        },
      },
    },
    tooltip: {
      theme: "light",
      y: { formatter: (val) => `${val} Petugas` },
    },
  }), [areaDonutData, collectors.length])

  return (
    <AppLayout
      title="Petugas Kolektor"
      brand={adminBrand}
      sidebarItems={adminSidebarItems}
      navItems={adminNavItems}
    >
      <div className="space-y-6">
        {/* ── 4 TOP KPI METRICS ── */}
        <div className="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
          <MetricCard
            title="Total Kolektor"
            value={`${collectors.length} Petugas`}
            icon={<Users className="h-6 w-6 text-blue-500" />}
            sub="Petugas penagih terdaftar"
            onClick={() => setStatusFilter("all")}
            isActive={statusFilter === "all"}
          />

          <MetricCard
            title="Kolektor Aktif"
            value={`${activeCount} Petugas`}
            icon={<CheckCircle2 className="h-6 w-6 text-emerald-500" />}
            sub="Akun siap operasional"
            onClick={() => setStatusFilter(statusFilter === "active" ? "all" : "active")}
            isActive={statusFilter === "active"}
          />

          <MetricCard
            title="Total Saldo Kasir"
            value={formatIDR(totalBalance)}
            icon={<Wallet className="h-6 w-6 text-purple-500" />}
            sub="Uang dipegang kolektor"
          />

          <MetricCard
            title="Area Tercover"
            value={`${totalAreas} Wilayah`}
            icon={<MapPin className="h-6 w-6 text-amber-500" />}
            sub="Cakupan penagihan"
          />
        </div>

        {/* ── CHARTS SECTION: CASH ON HAND & AREA COVERAGE ── */}
        <div className="grid grid-cols-1 lg:grid-cols-12 gap-4 sm:gap-6">
          {/* Cash on Hand Bar Chart */}
          <div className="lg:col-span-7 rounded-2xl border border-gray-200 bg-white p-4 sm:p-5 shadow-xs dark:border-gray-800 dark:bg-white/[0.03]">
            <div className="flex items-center justify-between gap-2 mb-3 sm:mb-4">
              <div className="flex items-center gap-2">
                <div className="flex h-8 w-8 items-center justify-center rounded-xl bg-brand-50 text-brand-500 dark:bg-brand-500/10">
                  <BarChart3 className="h-4 w-4" />
                </div>
                <div>
                  <h3 className="text-sm sm:text-base font-bold text-gray-800 dark:text-white/90">
                    Leaderboard Saldo Kas Kolektor
                  </h3>
                  <p className="text-[11px] text-gray-500 dark:text-gray-400">
                    Dana kas tagihan yang sedang dipegang masing-masing petugas
                  </p>
                </div>
              </div>
            </div>
            <div className="h-[220px] sm:h-[240px] w-full">
              <Chart
                options={collectorBarOptions}
                series={[{ name: "Saldo Kas", data: collectorBarData.values }]}
                type="bar"
                height="100%"
                width="100%"
              />
            </div>
          </div>

          {/* Area Coverage Donut Chart */}
          <div className="lg:col-span-5 rounded-2xl border border-gray-200 bg-white p-4 sm:p-5 shadow-xs dark:border-gray-800 dark:bg-white/[0.03]">
            <div className="flex items-center justify-between gap-2 mb-3 sm:mb-4">
              <div className="flex items-center gap-2">
                <div className="flex h-8 w-8 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10">
                  <PieChart className="h-4 w-4" />
                </div>
                <div>
                  <h3 className="text-sm sm:text-base font-bold text-gray-800 dark:text-white/90">
                    Distribusi Wilayah Kolektor
                  </h3>
                  <p className="text-[11px] text-gray-500 dark:text-gray-400">
                    Penugasan area operasional penagihan
                  </p>
                </div>
              </div>
            </div>
            <div className="h-[220px] sm:h-[240px] w-full flex items-center justify-center">
              <Chart
                options={areaDonutOptions}
                series={areaDonutData.series}
                type="donut"
                height="100%"
                width="100%"
              />
            </div>
          </div>
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
                placeholder="Cari nama, username, no hp, atau area kolektor..."
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
              <option value="all">Semua Status ({collectors.length})</option>
              <option value="active">Aktif ({activeCount})</option>
              <option value="inactive">Nonaktif ({collectors.length - activeCount})</option>
            </select>
          </div>

          <div className="flex items-center gap-2 shrink-0 w-full lg:w-auto">
            <ViewModeSwitcher
              value={viewMode}
              onChange={setViewMode}
              storageKey="nodera_admin_collectors_view"
              size="sm"
              className="shrink-0"
            />

            {/* Link Portal Shortcut */}
            <button
              type="button"
              onClick={() => copyToClipboard(url, "main-url")}
              className="inline-flex h-10 items-center gap-1.5 px-3 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:hover:bg-gray-800 text-xs font-semibold text-gray-700 dark:text-gray-200 transition active:scale-95 shadow-2xs cursor-pointer shrink-0"
              title="Salin Link Portal Kolektor"
            >
              {copiedKey === "main-url" ? (
                <Check className="h-4 w-4 text-emerald-500" />
              ) : (
                <Globe className="h-4 w-4 text-brand-500" />
              )}
              <span>{copiedKey === "main-url" ? "Tersalin!" : "Link Portal"}</span>
            </button>

            {/* Tambah Kolektor Primary Button */}
            <button
              type="button"
              onClick={openAdd}
              className="h-10 px-3.5 sm:px-4 rounded-xl bg-brand-500 hover:bg-brand-600 active:scale-95 text-xs font-bold text-white shadow-xs flex items-center justify-center gap-1.5 flex-1 sm:flex-none cursor-pointer"
            >
              <Plus className="h-4 w-4" />
              <span>Tambah Kolektor</span>
            </button>
          </div>
        </div>

        {/* ── MASTER CARD CONTAINER ── */}
        <div className="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xs dark:border-gray-800 dark:bg-white/[0.03]">
          {/* Header Bar */}
          <div className="p-4 sm:p-5 border-b border-gray-100 dark:border-gray-800 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
              <h3 className="text-base font-bold text-gray-800 dark:text-white/90">
                Data Petugas Kolektor Tagihan
              </h3>
              <p className="text-xs text-gray-500 dark:text-gray-400">
                {filteredCollectors.length} dari {collectors.length} petugas kolektor ditampilkan
              </p>
            </div>
          </div>

          {/* ── TABLE VIEW ── */}
          {filteredCollectors.length === 0 ? (
            <div className="p-8 text-center">
              <EmptyState
                icon={<UserPlus className="h-8 w-8 text-brand-500" />}
                title="Belum ada data kolektor"
                description="Tambahkan petugas kolektor lapangan untuk membantu penagihan kas di area pelanggan."
              />
            </div>
          ) : viewMode === "table" ? (
            <div className="w-full overflow-x-auto custom-scrollbar">
              <table className="w-full text-left text-xs text-gray-600 dark:text-gray-300 min-w-[950px] border-collapse">
                <thead className="border-b border-gray-200 bg-gray-50/75 text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:border-gray-800 dark:bg-gray-900/50 dark:text-gray-400">
                  <tr>
                    <th className="px-4 py-3.5">Petugas Kolektor</th>
                    <th className="px-4 py-3.5">Area Wilayah</th>
                    <th className="px-4 py-3.5">Kinerja Penagihan</th>
                    <th className="px-4 py-3.5">Skema Komisi</th>
                    <th className="px-4 py-3.5">Saldo Kasir</th>
                    <th className="px-4 py-3.5">Status</th>
                    <th className="px-4 py-3.5 text-right">Aksi</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-gray-100 dark:divide-gray-800">
                  {filteredCollectors.map((c) => {
                    const isActive = c.is_active

                    return (
                      <tr key={c.id} className="hover:bg-gray-50/50 dark:hover:bg-white/[0.02] transition">
                        {/* Kolektor Column */}
                        <td className="px-4 py-3.5">
                          <div className="flex items-center gap-3">
                            <div className="relative flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-gray-100 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 font-bold text-xs text-gray-700 dark:text-gray-200">
                              <span>{c.name.slice(0, 2).toUpperCase()}</span>
                              <span
                                className={cn(
                                  "absolute -bottom-0.5 -right-0.5 h-2.5 w-2.5 rounded-full ring-2 ring-white dark:ring-gray-900",
                                  isActive ? "bg-emerald-500" : "bg-rose-500"
                                )}
                              />
                            </div>
                            <div className="min-w-0">
                              <div className="font-bold text-gray-900 dark:text-white truncate">
                                {c.name}
                              </div>
                              <div className="font-mono text-[11px] text-gray-500 dark:text-gray-400 truncate">
                                @{c.username} {c.phone ? `• ${c.phone}` : ""}
                              </div>
                            </div>
                          </div>
                        </td>

                        {/* Area Wilayah Column */}
                        <td className="px-4 py-3.5">
                          <div className="flex items-center gap-1.5 font-medium text-gray-800 dark:text-gray-200">
                            <MapPin className="h-3.5 w-3.5 text-gray-400 shrink-0" />
                            <span>{c.collection_area || "Semua Wilayah"}</span>
                          </div>
                        </td>

                        {/* Kinerja Penagihan Column */}
                        <td className="px-4 py-3.5">
                          <div className="flex flex-col gap-0.5">
                            <span className="text-xs font-bold text-emerald-600 dark:text-emerald-400 font-mono">
                              {c.paid_count ?? 0} lunas ({formatIDR(c.paid_amount ?? 0)})
                            </span>
                            <span className="text-[10px] text-amber-600 dark:text-amber-400 font-medium font-mono">
                              {c.pending_count ?? 0} pending ({formatIDR(c.pending_amount ?? 0)})
                            </span>
                          </div>
                        </td>

                        {/* Skema Komisi Column */}
                        <td className="px-4 py-3.5 font-mono text-gray-700 dark:text-gray-300">
                          {c.commission_type === "percent" ? (
                            <span className="font-semibold text-purple-600 dark:text-purple-400">
                              {c.commission_value}% / tagihan
                            </span>
                          ) : (
                            <span className="font-semibold text-purple-600 dark:text-purple-400">
                              {formatIDR(Number(c.commission_value))} tetap
                            </span>
                          )}
                        </td>

                        {/* Saldo Kasir Column */}
                        <td className="px-4 py-3.5 font-mono font-bold text-emerald-600 dark:text-emerald-400">
                          {formatIDR(c.balance || 0)}
                        </td>

                        {/* Status Column */}
                        <td className="px-4 py-3.5">
                          {isActive ? (
                            <span className="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-bold bg-emerald-500 text-white shadow-xs whitespace-nowrap">
                              Aktif
                            </span>
                          ) : (
                            <span className="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-bold bg-rose-500 text-white shadow-xs whitespace-nowrap">
                              Nonaktif
                            </span>
                          )}
                        </td>

                        {/* Single Action Button: Kelola (Solid Icon-Only) */}
                        <td className="px-4 py-3.5 text-right whitespace-nowrap">
                          <button
                            type="button"
                            onClick={() => setManagingCollector(c)}
                            className="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-brand-500 hover:bg-brand-600 active:scale-95 text-white shadow-xs transition cursor-pointer"
                            title="Kelola Kolektor"
                          >
                            <Settings className="h-4 w-4" />
                          </button>
                        </td>
                      </tr>
                    )
                  })}
                </tbody>
              </table>
            </div>
          ) : (
            /* Grid View */
            <div className="p-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3.5">
              {filteredCollectors.map((c) => {
                const isActive = c.is_active

                return (
                  <div
                    key={c.id}
                    className="rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-4 flex flex-col justify-between gap-3 shadow-xs hover:border-gray-300 dark:hover:border-gray-700 transition"
                  >
                    <div className="flex items-start justify-between gap-2">
                      <div className="flex items-center gap-2.5 min-w-0">
                        <div className="relative flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-gray-100 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 font-bold text-xs text-gray-700 dark:text-gray-200">
                          <span>{c.name.slice(0, 2).toUpperCase()}</span>
                          <span
                            className={cn(
                              "absolute -bottom-0.5 -right-0.5 h-2.5 w-2.5 rounded-full ring-2 ring-white dark:ring-gray-900",
                              isActive ? "bg-emerald-500" : "bg-rose-500"
                            )}
                          />
                        </div>
                        <div className="min-w-0">
                          <div className="font-bold text-gray-900 dark:text-white text-sm truncate">
                            {c.name}
                          </div>
                          <div className="text-[11px] text-gray-400 font-mono truncate">
                            @{c.username}
                          </div>
                        </div>
                      </div>
                      {isActive ? (
                        <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold bg-emerald-500 text-white shadow-xs shrink-0">
                          Aktif
                        </span>
                      ) : (
                        <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold bg-rose-500 text-white shadow-xs shrink-0">
                          Nonaktif
                        </span>
                      )}
                    </div>

                    <div className="grid grid-cols-2 gap-2 text-[11px] border-y border-gray-100 dark:border-gray-800/80 py-2.5">
                      <div>
                        <span className="text-gray-400 block text-[10px]">Wilayah</span>
                        <span className="font-medium text-gray-700 dark:text-gray-300 line-clamp-1">
                          {c.collection_area || "Semua"}
                        </span>
                      </div>
                      <div>
                        <span className="text-gray-400 block text-[10px]">Saldo Kasir</span>
                        <span className="font-mono font-bold text-emerald-600 dark:text-emerald-400 text-xs">
                          {formatIDR(c.balance || 0)}
                        </span>
                      </div>
                    </div>

                    <div className="flex items-center justify-between pt-1">
                      <div className="text-[11px] font-mono text-emerald-600 dark:text-emerald-400 font-semibold">
                        {c.paid_count ?? 0} Lunas • {c.pending_count ?? 0} Pending
                      </div>
                      <button
                        type="button"
                        onClick={() => setManagingCollector(c)}
                        className="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-brand-500 hover:bg-brand-600 active:scale-95 text-white shadow-xs transition cursor-pointer"
                        title="Kelola Kolektor"
                      >
                        <Settings className="h-4 w-4" />
                      </button>
                    </div>
                  </div>
                )
              })}
            </div>
          )}
        </div>
      </div>

      {/* ── MODAL INTERAKTIF: KELOLA KOLEKTOR ── */}
      {managingCollector && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-xs">
          <div className="w-full max-w-md rounded-2xl border border-gray-200 bg-white p-5 shadow-2xl dark:border-gray-800 dark:bg-gray-900 space-y-4 max-h-[92vh] overflow-y-auto">
            {/* Modal Header */}
            <div className="flex items-center justify-between border-b border-gray-100 pb-3 dark:border-gray-800">
              <div className="flex items-center gap-3">
                <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-purple-500 text-white shadow-xs font-bold text-sm">
                  {managingCollector.name ? managingCollector.name.slice(0, 2).toUpperCase() : "KL"}
                </div>
                <div className="min-w-0">
                  <h3 className="font-bold text-sm text-gray-900 dark:text-white truncate">
                    Kelola: {managingCollector.name}
                  </h3>
                  <span className="text-[11px] text-gray-500 font-mono">
                    @{managingCollector.username}
                  </span>
                </div>
              </div>
              <button
                type="button"
                onClick={() => setManagingCollector(null)}
                className="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-800 dark:hover:text-gray-200 transition cursor-pointer"
              >
                <X className="h-5 w-5" />
              </button>
            </div>

            {/* Quick Summary Card */}
            <div className="rounded-xl border border-gray-200/80 bg-gray-50/70 p-3.5 dark:border-gray-800 dark:bg-gray-800/40 space-y-2 text-xs">
              <div className="flex items-center justify-between">
                <span className="text-gray-500 dark:text-gray-400">Status Akun</span>
                {managingCollector.is_active ? (
                  <span className="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-bold bg-emerald-500 text-white shadow-xs whitespace-nowrap">
                    Aktif
                  </span>
                ) : (
                  <span className="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-bold bg-rose-500 text-white shadow-xs whitespace-nowrap">
                    Nonaktif
                  </span>
                )}
              </div>
              <div className="flex items-center justify-between">
                <span className="text-gray-500 dark:text-gray-400">Area Wilayah</span>
                <span className="font-semibold text-gray-900 dark:text-white">
                  {managingCollector.collection_area || "Semua Wilayah"}
                </span>
              </div>
              <div className="flex items-center justify-between">
                <span className="text-gray-500 dark:text-gray-400">Skema Komisi</span>
                <span className="font-semibold text-purple-600 dark:text-purple-400">
                  {managingCollector.commission_type === "percent"
                    ? `${managingCollector.commission_value}% / tagihan`
                    : `${formatIDR(Number(managingCollector.commission_value))} tetap`}
                </span>
              </div>
              <div className="flex items-center justify-between">
                <span className="text-gray-500 dark:text-gray-400">Saldo Kasir Dipegang</span>
                <span className="font-mono font-bold text-emerald-600 dark:text-emerald-400">
                  {formatIDR(managingCollector.balance || 0)}
                </span>
              </div>
              {managingCollector.phone && (
                <div className="flex items-center justify-between">
                  <span className="text-gray-500 dark:text-gray-400">No. WhatsApp</span>
                  <span className="font-mono font-semibold text-gray-800 dark:text-gray-200">
                    {managingCollector.phone}
                  </span>
                </div>
              )}
            </div>

            {/* Action Buttons List */}
            <div className="space-y-2 pt-1 text-xs">
              {/* Copy Portal Link */}
              <button
                type="button"
                onClick={() => {
                  const url = `${loginUrl || window.location.origin + "/collector/login"}?u=${managingCollector.username}`
                  copyToClipboard(url, "modal-copy")
                }}
                className="w-full group flex items-center justify-between p-3 rounded-xl bg-gray-50/70 hover:bg-brand-50/50 border border-gray-100 hover:border-brand-200 dark:bg-gray-800/40 dark:hover:bg-gray-800 dark:border-gray-800 dark:hover:border-brand-900/60 transition cursor-pointer"
              >
                <div className="flex items-center gap-3">
                  <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-brand-50 text-brand-600 dark:bg-brand-950 dark:text-brand-400 whitespace-nowrap">
                    {copiedKey === "modal-copy" ? <Check className="h-4 w-4 text-emerald-500" /> : <Copy className="h-4 w-4" />}
                  </div>
                  <div className="text-left">
                    <div className="font-bold text-gray-900 dark:text-white">
                      {copiedKey === "modal-copy" ? "Link Berhasil Disalin!" : "Salin Link Portal Kolektor"}
                    </div>
                    <div className="text-[10px] text-gray-500">Kredensial link akses mandiri</div>
                  </div>
                </div>
                <ChevronRight className="h-4 w-4 text-gray-400 group-hover:text-brand-500 transition-colors" />
              </button>

              {/* Send WA Link */}
              {managingCollector.phone && (
                <button
                  type="button"
                  onClick={() => sendWaLink(managingCollector)}
                  className="w-full group flex items-center justify-between p-3 rounded-xl bg-gray-50/70 hover:bg-emerald-50/50 border border-gray-100 hover:border-emerald-200 dark:bg-gray-800/40 dark:hover:bg-gray-800 dark:border-gray-800 dark:hover:border-emerald-900/60 transition cursor-pointer"
                >
                  <div className="flex items-center gap-3">
                    <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600 dark:bg-emerald-950 dark:text-emerald-400 whitespace-nowrap">
                      <MessageCircle className="h-4 w-4" />
                    </div>
                    <div className="text-left">
                      <div className="font-bold text-gray-900 dark:text-white">
                        Kirim Akses ke WhatsApp
                      </div>
                      <div className="text-[10px] text-gray-500">Kirim tautan login ke {managingCollector.phone}</div>
                    </div>
                  </div>
                  <ChevronRight className="h-4 w-4 text-gray-400 group-hover:text-emerald-500 transition-colors" />
                </button>
              )}

              {/* Edit Collector */}
              <button
                type="button"
                onClick={() => {
                  const target = managingCollector
                  setManagingCollector(null)
                  openEdit(target)
                }}
                className="w-full group flex items-center justify-between p-3 rounded-xl bg-gray-50/70 hover:bg-sky-50/50 border border-gray-100 hover:border-sky-200 dark:bg-gray-800/40 dark:hover:bg-gray-800 dark:border-gray-800 dark:hover:border-sky-900/60 transition cursor-pointer"
              >
                <div className="flex items-center gap-3">
                  <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-sky-50 text-sky-600 dark:bg-sky-950 dark:text-sky-400">
                    <Pencil className="h-4 w-4" />
                  </div>
                  <div className="text-left">
                    <div className="font-bold text-gray-900 dark:text-white">
                      Edit Data &amp; Komisi
                    </div>
                    <div className="text-[10px] text-gray-500">Ubah area wilayah, password, dan skema tarif</div>
                  </div>
                </div>
                <ChevronRight className="h-4 w-4 text-gray-400 group-hover:text-sky-500 transition-colors" />
              </button>
            </div>
          </div>
        </div>
      )}

      {/* ── MODAL TAMBAH / EDIT KOLEKTOR ── */}
      {modalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
          <div className="fixed inset-0 bg-black/60 backdrop-blur-sm" onClick={() => setModalOpen(false)} />
          <div className="relative flex max-h-[92vh] w-full max-w-md flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 text-gray-900 dark:text-white shadow-2xl animate-in zoom-in-95 duration-150">
            {/* Modal Header */}
            <div className="flex items-center justify-between border-b border-gray-100 dark:border-gray-800 px-5 py-4 shrink-0">
              <div className="flex items-center gap-3">
                <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-brand-50 dark:bg-brand-500/10 text-brand-500 border border-brand-200 dark:border-brand-500/20">
                  <UserPlus className="h-5 w-5" />
                </div>
                <div>
                  <h3 className="font-bold text-base text-gray-900 dark:text-white">
                    {editing ? "Edit Petugas Kolektor" : "Tambah Kolektor Baru"}
                  </h3>
                  <p className="text-xs text-gray-500 dark:text-gray-400">Atur hak akses login dan komisi lapangan</p>
                </div>
              </div>
              <button
                onClick={() => setModalOpen(false)}
                className="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-700 dark:hover:text-white"
              >
                <X className="h-4 w-4" />
              </button>
            </div>

            <form onSubmit={submit} className="flex min-h-0 flex-1 flex-col overflow-hidden">
              <div className="flex-1 overflow-y-auto p-5 space-y-4">
                <div className="space-y-1.5">
                  <label className="text-xs font-semibold text-gray-700 dark:text-gray-300">Nama Lengkap</label>
                  <input
                    value={form.data.name}
                    onChange={(e) => form.setData("name", e.target.value)}
                    placeholder="Nama lengkap kolektor"
                    required
                    className="w-full h-10 rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-950 px-3 text-xs text-gray-900 dark:text-white focus:outline-none focus:border-brand-500"
                  />
                </div>

                <div className="grid gap-3 sm:grid-cols-2">
                  <div className="space-y-1.5">
                    <label className="text-xs font-semibold text-gray-700 dark:text-gray-300">Username</label>
                    <input
                      value={form.data.username}
                      onChange={(e) => form.setData("username", e.target.value)}
                      placeholder="username"
                      required
                      className="w-full h-10 rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-950 px-3 text-xs text-gray-900 dark:text-white focus:outline-none focus:border-brand-500"
                    />
                  </div>

                  <div className="space-y-1.5">
                    <label className="text-xs font-semibold text-gray-700 dark:text-gray-300">
                      Password {editing ? "(Opsional)" : ""}
                    </label>
                    <input
                      type="password"
                      value={form.data.password}
                      onChange={(e) => form.setData("password", e.target.value)}
                      placeholder={editing ? "Kosongkan jika sama" : "Password login"}
                      required={!editing}
                      className="w-full h-10 rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-950 px-3 text-xs text-gray-900 dark:text-white focus:outline-none focus:border-brand-500"
                    />
                  </div>
                </div>

                <div className="grid gap-3 sm:grid-cols-2">
                  <div className="space-y-1.5">
                    <label className="text-xs font-semibold text-gray-700 dark:text-gray-300">No. WhatsApp</label>
                    <input
                      value={form.data.phone}
                      onChange={(e) => form.setData("phone", e.target.value)}
                      placeholder="08xxxxxxxxxx"
                      className="w-full h-10 rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-950 px-3 text-xs text-gray-900 dark:text-white focus:outline-none focus:border-brand-500"
                    />
                  </div>

                  <div className="space-y-1.5">
                    <label className="text-xs font-semibold text-gray-700 dark:text-gray-300">Area Wilayah</label>
                    <input
                      value={form.data.collection_area}
                      onChange={(e) => form.setData("collection_area", e.target.value)}
                      placeholder="cth: Perum Griya Indah"
                      className="w-full h-10 rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-950 px-3 text-xs text-gray-900 dark:text-white focus:outline-none focus:border-brand-500"
                    />
                  </div>
                </div>

                <div className="grid gap-3 sm:grid-cols-2">
                  <div className="space-y-1.5">
                    <label className="text-xs font-semibold text-gray-700 dark:text-gray-300">Tipe Komisi</label>
                    <select
                      value={form.data.commission_type}
                      onChange={(e) => form.setData("commission_type", e.target.value)}
                      className="w-full h-10 rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-950 px-3 text-xs text-gray-900 dark:text-white focus:outline-none focus:border-brand-500"
                    >
                      <option value="percent">Persen (%)</option>
                      <option value="fixed">Nominal Tetap (Rp)</option>
                    </select>
                  </div>

                  <div className="space-y-1.5">
                    <label className="text-xs font-semibold text-gray-700 dark:text-gray-300">
                      {form.data.commission_type === "percent" ? "Persentase (%)" : "Nominal (Rp)"}
                    </label>
                    <input
                      type="number"
                      value={form.data.commission_value}
                      onChange={(e) => form.setData("commission_value", e.target.value)}
                      placeholder="0"
                      className="w-full h-10 rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-950 px-3 text-xs text-gray-900 dark:text-white focus:outline-none focus:border-brand-500"
                    />
                  </div>
                </div>
              </div>

              {/* Bottom Actions */}
              <div className="border-t border-gray-100 dark:border-gray-800 p-4 bg-gray-50/50 dark:bg-gray-900/50 shrink-0 flex items-center gap-2">
                <button
                  type="button"
                  onClick={() => setModalOpen(false)}
                  className="flex-1 rounded-xl h-10 text-xs font-bold border border-gray-200 bg-white hover:bg-gray-100 dark:border-gray-800 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200 transition"
                >
                  Batal
                </button>
                <button
                  type="submit"
                  disabled={form.processing}
                  className="flex-1 rounded-xl h-10 text-xs font-bold bg-brand-500 hover:bg-brand-600 text-white transition flex items-center justify-center gap-1.5 disabled:opacity-50"
                >
                  {form.processing ? "Menyimpan..." : editing ? "Simpan Perubahan" : "Tambah Kolektor"}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </AppLayout>
  )
}
