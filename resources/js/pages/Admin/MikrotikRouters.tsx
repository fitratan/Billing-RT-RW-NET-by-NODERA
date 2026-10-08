import * as React from "react"
import { useState, useMemo } from "react"
import Chart from "react-apexcharts"
import { AppLayout } from "@/components/layout/app-layout"
import {
  Router as RouterIcon,
  Plus,
  Pencil,
  Wifi,
  RefreshCw,
  Trash2,
  Activity,
  Cpu,
  TrendingUp,
  Search,
  X,
  Info,
  Globe,
  Server,
  CheckCircle2,
  AlertTriangle,
  Layers,
  Settings,
  ExternalLink,
  Loader2,
} from "lucide-react"
import { EmptyState } from "@/components/ui/empty-state"
import { PageProps } from "@/types"
import { cn, formatUptime } from "@/lib/utils"
import { adminSidebarItems, adminNavItems, adminBrand } from "@/lib/admin-nav"
import { useForm, router, Link } from "@inertiajs/react"
import Modal from "@/components/tailadmin/Modal"
import { ViewModeSwitcher } from "@/components/ui/view-mode-switcher"

interface Mikrotik {
  id: number
  name: string
  host: string
  port: number
  username: string
  tenant_id: number | null
  tenant_name?: string | null
  is_active: boolean
  location: string | null
  resource?: {
    cpu_load: number | null
    memory_percent: number | null
    free_memory: string | null
    total_memory: string | null
    free_hdd?: string | null
    total_hdd?: string | null
    uptime: string | null
    version: string | null
    board_name: string | null
    temperature?: number | null
    voltage?: number | null
    online: boolean
  }
}

export function formatBytes(bytes: string | number | null | undefined): string {
  if (bytes == null) return "-"
  const n = Number(bytes)
  if (!n) return "-"
  const gb = n / 1024 / 1024 / 1024
  if (gb >= 1) return `${gb.toFixed(1)} GB`
  const mb = n / 1024 / 1024
  if (mb >= 1) return `${Math.round(mb)} MB`
  return `${Math.round(n / 1024)} KB`
}

export default function MikrotikRoutersPage({
  routers = [],
  is_superadmin = false,
  tenants = [],
}: PageProps<{
  routers: Mikrotik[]
  is_superadmin: boolean
  tenants: { id: number; name: string }[]
}>) {
  const [modalOpen, setModalOpen] = useState(false)
  const [infoModalOpen, setInfoModalOpen] = useState(false)
  const [deleteConfirmModal, setDeleteConfirmModal] = useState<Mikrotik | null>(null)
  const [editing, setEditing] = useState<Mikrotik | null>(null)
  const [managingRouter, setManagingRouter] = useState<Mikrotik | null>(null)
  const [viewMode, setViewMode] = useState<"table" | "grid">("grid")
  const [search, setSearch] = useState("")
  const [testingId, setTestingId] = useState<number | null>(null)
  const [liveTestResult, setLiveTestResult] = useState<{
    id: number
    success: boolean
    message: string
    latency_ms?: number
    board_name?: string
  } | null>(null)

  const handleTestRouter = async (r: Mikrotik) => {
    setTestingId(r.id)
    setLiveTestResult(null)
    try {
      const res = await fetch("/admin/mikrotik/router/test-live", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "X-CSRF-TOKEN": (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || "",
        },
        body: JSON.stringify({
          host: r.host,
          port: r.port ?? 8728,
          username: r.username,
          router_id: r.id,
        }),
      })
      const data = await res.json()
      setLiveTestResult({
        id: r.id,
        success: data.success,
        message: data.message,
        latency_ms: data.latency_ms,
        board_name: data.board_name,
      })
      if (data.success) {
        setTimeout(() => {
          router.reload({ preserveScroll: true, preserveState: true })
        }, 1200)
      }
    } catch (e: any) {
      setLiveTestResult({
        id: r.id,
        success: false,
        message: e.message || "Gagal menghubungi API MikroTik",
      })
    } finally {
      setTestingId(null)
    }
  }

  const form = useForm({
    name: "",
    host: "",
    port: "8728",
    username: "",
    password: "",
    location: "",
    tenant_id: "",
    initial_sync_mode: "pppoe",
  })

  const openAdd = () => {
    setEditing(null)
    form.setData({
      name: "",
      host: "",
      port: "8728",
      username: "",
      password: "",
      location: "",
      tenant_id: tenants[0]?.id ? String(tenants[0].id) : "",
      initial_sync_mode: "pppoe",
    })
    setModalOpen(true)
  }

  const openEdit = (r: Mikrotik) => {
    setManagingRouter(null)
    setEditing(r)
    form.setData({
      name: r.name,
      host: r.host,
      port: String(r.port ?? 8728),
      username: r.username,
      password: "",
      location: r.location ?? "",
      tenant_id: r.tenant_id ? String(r.tenant_id) : "",
      initial_sync_mode: "none",
    })
    setModalOpen(true)
  }

  const submit = (e: React.FormEvent) => {
    e.preventDefault()
    if (editing) {
      form.post(`/admin/mikrotik/router/edit/${editing.id}`, {
        preserveScroll: true,
        onSuccess: () => {
          setModalOpen(false)
        },
      })
    } else {
      form.post("/admin/mikrotik/router/add", {
        preserveScroll: true,
        onSuccess: () => {
          setModalOpen(false)
        },
      })
    }
  }

  const handleDelete = () => {
    if (!deleteConfirmModal) return
    router.post(`/admin/mikrotik/router/delete/${deleteConfirmModal.id}`, {}, {
      preserveScroll: true,
      onSuccess: () => setDeleteConfirmModal(null),
    })
  }

  const filtered = useMemo(() => {
    if (!search.trim()) return routers
    const q = search.toLowerCase()
    return routers.filter(
      (r) =>
        r.name.toLowerCase().includes(q) ||
        r.host.toLowerCase().includes(q) ||
        (r.location && r.location.toLowerCase().includes(q)) ||
        (r.resource?.board_name && r.resource.board_name.toLowerCase().includes(q)) ||
        (r.tenant_name && r.tenant_name.toLowerCase().includes(q))
    )
  }, [routers, search])

  const totalOnline = routers.filter((r) => r.resource?.online).length
  const totalOffline = routers.length - totalOnline
  const onlineRate = routers.length > 0 ? Math.round((totalOnline / routers.length) * 100) : 0

  const avgCpu = useMemo(() => {
    const validLoads = routers
      .map((r) => r.resource?.cpu_load)
      .filter((l): l is number => typeof l === "number")
    if (validLoads.length === 0) return 0
    return Math.round(validLoads.reduce((a, b) => a + b, 0) / validLoads.length)
  }, [routers])

  const avgRam = useMemo(() => {
    const validLoads = routers
      .map((r) => r.resource?.memory_percent)
      .filter((l): l is number => typeof l === "number")
    if (validLoads.length === 0) return 0
    return Math.round(validLoads.reduce((a, b) => a + b, 0) / validLoads.length)
  }, [routers])

  const routerStatusDonutOptions = useMemo(() => ({
    chart: { type: "donut" as const, fontFamily: "Outfit, Inter, sans-serif" },
    labels: ["Router Online (API Aktif)", "Router Terputus / Timeout"],
    colors: ["#10B981", "#F43F5E"],
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
              label: "Total Gateway",
              fontSize: "11px",
              fontWeight: 600,
              color: "#64748B",
              formatter: () => `${routers.length} Router`,
            },
            value: { fontSize: "16px", fontWeight: 700, color: "#0F172A" },
          },
        },
      },
    },
    tooltip: {
      theme: "light",
      y: { formatter: (val: number) => `${val} Gateway` },
    },
  }), [routers.length])

  const fleetCpuRadialOptions = useMemo(() => ({
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
            color: avgCpu <= 60 ? "#10B981" : avgCpu <= 85 ? "#F59E0B" : "#F43F5E",
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
        gradientToColors: [avgCpu <= 60 ? "#10B981" : "#F59E0B"],
        stops: [0, 100],
      },
    },
    stroke: { dashArray: 4 },
    colors: [avgCpu <= 60 ? "#10B981" : "#0073C6"],
    labels: ["Rata-rata CPU"],
  }), [avgCpu])

  return (
    <AppLayout
      title="Router Gateway MikroTik"
      brand={adminBrand}
      sidebarItems={adminSidebarItems}
      navItems={adminNavItems}
    >
      <div className="space-y-4 sm:space-y-6">
        {/* ── Executive Multi-Type Visual Analytics ── */}
        <div className="grid grid-cols-1 lg:grid-cols-12 gap-4 sm:gap-5">
          {/* Card 1: Komputasi & Telemetri Fleet Gateway (5 Cols) */}
          <div className="lg:col-span-5 rounded-2xl border border-gray-200 bg-white p-4 sm:p-5 shadow-xs dark:border-gray-800 dark:bg-white/[0.03] flex flex-col justify-between">
            <div className="flex items-center justify-between border-b border-gray-100 dark:border-gray-800/80 pb-3">
              <div>
                <h3 className="text-sm font-bold text-gray-900 dark:text-white flex items-center gap-2">
                  <Cpu className="h-4 w-4 text-brand-500" />
                  <span>Beban Komputasi Fleet Gateway</span>
                </h3>
                <p className="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                  Rata-rata beban hardware seluruh router
                </p>
              </div>
              <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-bold bg-brand-50 text-brand-700 dark:bg-brand-500/10 dark:text-brand-400">
                {routers.length} Gateway
              </span>
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-12 gap-3 items-center py-2">
              <div className="sm:col-span-5 flex justify-center">
                <Chart
                  options={fleetCpuRadialOptions as any}
                  series={[avgCpu]}
                  type="radialBar"
                  height={150}
                  width="100%"
                />
              </div>
              <div className="sm:col-span-7 space-y-2">
                <div className="flex items-center justify-between rounded-xl bg-gray-50/80 p-2.5 dark:bg-gray-900/50 border border-gray-100 dark:border-gray-800/60">
                  <span className="text-xs text-gray-500 dark:text-gray-400 flex items-center gap-1.5">
                    <Cpu className="h-3.5 w-3.5 text-brand-500" /> Rata-rata CPU Fleet
                  </span>
                  <span className="font-bold text-gray-900 dark:text-white text-xs">{avgCpu}%</span>
                </div>
                <div className="flex items-center justify-between rounded-xl bg-gray-50/80 p-2.5 dark:bg-gray-900/50 border border-gray-100 dark:border-gray-800/60">
                  <span className="text-xs text-gray-500 dark:text-gray-400 flex items-center gap-1.5">
                    <Activity className="h-3.5 w-3.5 text-purple-500" /> Rata-rata RAM Load
                  </span>
                  <span className="font-bold text-purple-600 dark:text-purple-400 text-xs">{avgRam > 0 ? `${avgRam}%` : "Normal"}</span>
                </div>
                <div className="flex items-center justify-between rounded-xl bg-gray-50/80 p-2.5 dark:bg-gray-900/50 border border-gray-100 dark:border-gray-800/60">
                  <span className="text-xs text-gray-500 dark:text-gray-400 flex items-center gap-1.5">
                    <Wifi className="h-3.5 w-3.5 text-emerald-500" /> Rasio Online Fleet
                  </span>
                  <span className="font-bold text-emerald-600 dark:text-emerald-400 text-xs">{onlineRate}%</span>
                </div>
              </div>
            </div>
          </div>

          {/* Card 2: Sebaran Status Koneksi API Gateway (7 Cols) */}
          <div className="lg:col-span-7 rounded-2xl border border-gray-200 bg-white p-4 sm:p-5 shadow-xs dark:border-gray-800 dark:bg-white/[0.03] flex flex-col justify-between">
            <div className="flex items-center justify-between border-b border-gray-100 dark:border-gray-800/80 pb-3">
              <div>
                <h3 className="text-sm font-bold text-gray-900 dark:text-white flex items-center gap-2">
                  <RouterIcon className="h-4 w-4 text-emerald-500" />
                  <span>Status Konektivitas API Gateway</span>
                </h3>
                <p className="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                  Pemantauan status socket API &amp; ketersediaan router
                </p>
              </div>
              <div className="flex items-center gap-1.5">
                <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-bold bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400">
                  <span className="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse" />
                  {totalOnline} Terhubung
                </span>
                {totalOffline > 0 && (
                  <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-bold bg-rose-50 text-rose-700 dark:bg-rose-500/10 dark:text-rose-400">
                    {totalOffline} Terputus
                  </span>
                )}
              </div>
            </div>

            <div className="pt-2">
              <Chart
                options={routerStatusDonutOptions as any}
                series={[totalOnline, totalOffline]}
                type="donut"
                height={170}
                width="100%"
              />
            </div>
          </div>
        </div>

        {/* ── 1-LINE RESPONSIVE INTEGRATED TOOLBAR ── */}
        <div className="rounded-2xl border border-gray-200 bg-white p-3 sm:p-4 shadow-xs dark:border-gray-800 dark:bg-white/[0.03] flex flex-col gap-2.5 sm:gap-3 lg:flex-row lg:items-center lg:justify-between">
          <div className="flex flex-wrap items-center gap-2 sm:gap-3 flex-1 min-w-0">
            <div className="relative flex-1 min-w-[180px] sm:min-w-[220px] w-full sm:w-auto">
              <Search className="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400 dark:text-gray-500" />
              <input
                type="text"
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                placeholder="Cari nama router, host IP, tipe board, atau lokasi..."
                className="h-10 w-full rounded-xl border border-gray-200 bg-gray-50/75 pl-9 pr-8 text-xs text-gray-800 placeholder:text-gray-400 focus:border-brand-500 focus:outline-none dark:border-gray-800 dark:bg-gray-900/50 dark:text-gray-200 dark:placeholder:text-gray-500 font-medium transition"
              />
              {search && (
                <button
                  type="button"
                  onClick={() => setSearch("")}
                  className="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 cursor-pointer"
                >
                  <X className="h-3.5 w-3.5" />
                </button>
              )}
            </div>
          </div>

          <div className="flex items-center gap-2 shrink-0 w-full sm:w-auto justify-end">
            <div className="shrink-0">
              <ViewModeSwitcher value={viewMode} onChange={setViewMode} size="sm" />
            </div>

            <button
              type="button"
              onClick={() => setInfoModalOpen(true)}
              className="h-10 inline-flex items-center gap-1.5 px-3 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:hover:bg-gray-800 text-xs font-semibold text-gray-700 dark:text-gray-200 transition shadow-2xs cursor-pointer shrink-0"
              title="Petunjuk Port API"
            >
              <Info className="h-4 w-4 text-brand-500" />
              <span className="hidden sm:inline">Panduan API</span>
            </button>

            <button
              type="button"
              onClick={openAdd}
              className="h-10 px-3.5 sm:px-4 rounded-xl bg-brand-500 hover:bg-brand-600 active:scale-95 text-xs font-bold text-white shadow-xs flex items-center justify-center gap-1.5 w-full sm:w-auto shrink-0 cursor-pointer"
            >
              <Plus className="h-4 w-4" />
              <span>Tambah Router</span>
            </button>
          </div>
        </div>

        {/* Master Container */}
        <div className="rounded-2xl border border-gray-200 bg-white shadow-xs dark:border-gray-800 dark:bg-white/[0.03] overflow-hidden">
          {/* Router Content Stream */}
          {filtered.length === 0 ? (
            <div className="p-8 text-center">
              <EmptyState
                icon={<RouterIcon className="h-8 w-8 text-brand-500" />}
                title="Tidak ada router ditemukan"
                description={search ? "Tidak ada router yang cocok dengan pencarian." : "Tambah router MikroTik untuk mulai integrasi gateway."}
              />
            </div>
          ) : viewMode === "grid" ? (
            <div className="p-4 sm:p-5 grid gap-3.5 sm:grid-cols-2 lg:grid-cols-3">
              {filtered.map((r: Mikrotik) => {
                const isOnline = Boolean(r.resource?.online)

                return (
                  <div
                    key={r.id}
                    className="relative rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03] p-4 sm:p-5 shadow-xs transition-all hover:border-brand-500/40 dark:hover:border-brand-500/40 space-y-3.5 select-none"
                  >
                    {/* Header Card */}
                    <div className="flex items-start justify-between gap-2.5">
                      <div className="flex items-center gap-3 min-w-0 flex-1 overflow-hidden">
                        <div className="relative flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-400 font-bold text-xs">
                          <RouterIcon className="h-5 w-5" />
                          {r.is_active && (
                            <span
                              className={cn(
                                "absolute -bottom-0.5 -right-0.5 h-2.5 w-2.5 rounded-full ring-2 ring-white dark:ring-gray-900",
                                isOnline ? "bg-emerald-500" : "bg-rose-500"
                              )}
                            />
                          )}
                        </div>

                        <div className="min-w-0 flex-1">
                          <div className="flex items-center gap-2 flex-wrap min-w-0">
                            <h4 className="font-bold text-sm text-gray-900 dark:text-white truncate">
                              {r.name}
                            </h4>
                            {r.tenant_name && (
                              <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold bg-brand-500 text-white shadow-xs whitespace-nowrap">
                                {r.tenant_name}
                              </span>
                            )}
                          </div>
                          <p className="mt-0.5 text-xs text-gray-500 dark:text-gray-400 font-medium truncate">
                            {r.resource?.board_name || r.location || "RouterOS Gateway"}
                          </p>
                        </div>
                      </div>

                      {/* Solid Status Badge */}
                      <div className="shrink-0">
                        {isOnline ? (
                          <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold bg-emerald-500 text-white shadow-xs whitespace-nowrap">
                            Online
                          </span>
                        ) : r.is_active ? (
                          <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold bg-rose-500 text-white shadow-xs whitespace-nowrap">
                            Offline
                          </span>
                        ) : (
                          <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold bg-gray-500 text-white shadow-xs whitespace-nowrap">
                            Nonaktif
                          </span>
                        )}
                      </div>
                    </div>

                    {/* Sunken Telemetry Box */}
                    <div className="rounded-xl border border-gray-100 bg-gray-50/80 dark:border-gray-800 dark:bg-gray-900/50 p-3 flex items-center justify-between text-xs gap-2 w-full min-w-0">
                      <div className="flex items-center gap-2 min-w-0 flex-1 overflow-hidden">
                        <Activity className={cn("h-3.5 w-3.5 shrink-0", isOnline ? "text-emerald-500" : "text-gray-400")} />
                        <span className="font-mono text-xs font-semibold text-gray-800 dark:text-gray-200 tracking-wide truncate">
                          {r.host}:{r.port}
                        </span>
                      </div>

                      <div className="flex items-center gap-1.5 shrink-0">
                        <span className="rounded-md bg-gray-200/80 dark:bg-gray-800 px-2 py-0.5 text-[11px] font-semibold text-gray-700 dark:text-gray-300 font-mono">
                          {r.resource?.cpu_load != null
                            ? `CPU ${r.resource.cpu_load}%`
                            : r.resource?.version
                            ? `v${r.resource.version}`
                            : `Port ${r.port}`}
                        </span>
                      </div>
                    </div>

                    {/* Bottom Action Row: Single Kelola Button */}
                    <div className="pt-2 border-t border-gray-100 dark:border-gray-800 flex items-center justify-between">
                      <span className="text-[11px] text-gray-500 dark:text-gray-400 font-mono">
                        {r.resource?.uptime ? formatUptime(r.resource.uptime) : "Uptime: -"}
                      </span>

                      <button
                        type="button"
                        onClick={() => setManagingRouter(r)}
                        className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200 font-bold text-xs transition cursor-pointer"
                        title="Kelola Router"
                      >
                        <Settings className="h-3.5 w-3.5" />
                        <span>Kelola</span>
                      </button>
                    </div>
                  </div>
                )
              })}
            </div>
          ) : (
            <div className="w-full overflow-x-auto custom-scrollbar">
              <table className="w-full min-w-[950px] text-left text-xs text-gray-700 dark:text-gray-200">
                <thead className="border-b border-gray-200 bg-gray-50/75 dark:border-gray-800 dark:bg-gray-800/40 text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                  <tr>
                    <th className="py-3 px-4 sm:px-6">Router / Model</th>
                    <th className="py-3 px-4">IP Host</th>
                    <th className="py-3 px-4">Port API</th>
                    <th className="py-3 px-4">Status Koneksi</th>
                    <th className="py-3 px-4">CPU &amp; RAM</th>
                    <th className="py-3 px-4">Uptime &amp; Versi</th>
                    <th className="py-3 px-4 text-end sm:px-6">Aksi</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-gray-100 dark:divide-gray-800">
                  {filtered.map((r: Mikrotik) => {
                    const isOnline = Boolean(r.resource?.online)
                    return (
                      <tr key={r.id} className="hover:bg-gray-50/50 dark:hover:bg-white/[0.02] transition-colors">
                        <td className="py-3.5 px-4 sm:px-6">
                          <div className="flex items-center gap-3">
                            <div className="relative flex h-8.5 w-8.5 shrink-0 items-center justify-center rounded-lg bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-400 font-bold text-xs whitespace-nowrap">
                              <RouterIcon className="h-4 w-4" />
                            </div>
                            <div className="min-w-0">
                              <div className="font-bold text-gray-900 dark:text-white flex items-center gap-1.5">
                                <span>{r.name}</span>
                                {r.tenant_name && (
                                  <span className="inline-flex items-center px-1.5 py-0.2 rounded text-[10px] font-bold bg-brand-500 text-white shadow-xs">
                                    {r.tenant_name}
                                  </span>
                                )}
                              </div>
                              <div className="text-[11px] text-gray-500 dark:text-gray-400 font-medium truncate">
                                {r.resource?.board_name || r.location || "RouterOS"}
                              </div>
                            </div>
                          </div>
                        </td>
                        <td className="py-3.5 px-4 font-mono font-semibold text-gray-800 dark:text-gray-200">
                          {r.host}
                        </td>
                        <td className="py-3.5 px-4 font-mono font-semibold text-gray-800 dark:text-gray-200">
                          {r.port}
                        </td>
                        <td className="py-3.5 px-4">
                          <div className="flex items-center gap-2">
                            {isOnline ? (
                              <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold bg-emerald-500 text-white shadow-xs whitespace-nowrap">
                                Online
                              </span>
                            ) : r.is_active ? (
                              <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold bg-rose-500 text-white shadow-xs whitespace-nowrap">
                                Offline
                              </span>
                            ) : (
                              <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold bg-gray-500 text-white shadow-xs whitespace-nowrap">
                                Nonaktif
                              </span>
                            )}
                          </div>
                        </td>
                        <td className="py-3.5 px-4 font-mono text-[11px] text-gray-700 dark:text-gray-300">
                          <div>CPU: <span className="font-semibold text-gray-900 dark:text-white">{r.resource?.cpu_load != null ? `${r.resource.cpu_load}%` : "-"}</span></div>
                          <div className="text-[10px] text-gray-500 dark:text-gray-400">RAM: {r.resource?.memory_percent != null ? `${r.resource.memory_percent}%` : "-"}</div>
                        </td>
                        <td className="py-3.5 px-4 font-mono text-[11px] text-gray-600 dark:text-gray-400">
                          <div>{r.resource?.uptime ? formatUptime(r.resource.uptime) : "-"}</div>
                          <div className="text-[10px] text-gray-400">v{r.resource?.version || "-"}</div>
                        </td>
                        <td className="py-3.5 px-4 sm:px-6 text-end">
                          <button
                            type="button"
                            onClick={() => setManagingRouter(r)}
                            className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200 font-bold text-xs transition cursor-pointer"
                            title="Kelola Router"
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

      {/* =========================================================================
          MODAL INTERAKTIF: KELOLA ROUTER (1:1 Superadmin Standard)
          ========================================================================= */}
      {managingRouter && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-xs">
          <div className="w-full max-w-md rounded-2xl border border-gray-200 bg-white p-5 shadow-2xl dark:border-gray-800 dark:bg-gray-900 space-y-4 max-h-[92vh] overflow-y-auto">
            {/* Modal Header */}
            <div className="flex items-center justify-between border-b border-gray-100 pb-3 dark:border-gray-800">
              <div className="flex items-center gap-3">
                <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-brand-500 text-white shadow-xs">
                  <RouterIcon className="h-5 w-5" />
                </div>
                <div>
                  <h3 className="font-bold text-sm text-gray-900 dark:text-white flex items-center gap-2">
                    <span>{managingRouter.name}</span>
                    {managingRouter.resource?.online ? (
                      <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold bg-emerald-500 text-white shadow-xs whitespace-nowrap">
                        Online
                      </span>
                    ) : (
                      <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold bg-rose-500 text-white shadow-xs whitespace-nowrap">
                        Offline
                      </span>
                    )}
                  </h3>
                  <span className="text-[11px] text-gray-500 font-mono">
                    {managingRouter.host}:{managingRouter.port}
                  </span>
                </div>
              </div>
              <button
                type="button"
                onClick={() => setManagingRouter(null)}
                className="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-800 dark:hover:text-gray-200 transition cursor-pointer"
              >
                <X className="h-5 w-5" />
              </button>
            </div>

            {/* Quick Summary Box */}
            <div className="rounded-xl border border-gray-200/80 bg-gray-50/70 p-3.5 dark:border-gray-800 dark:bg-gray-800/40 space-y-2 text-xs">
              <div className="flex items-center justify-between">
                <span className="text-gray-500 dark:text-gray-400">Model Perangkat</span>
                <span className="font-semibold text-gray-900 dark:text-white">
                  {managingRouter.resource?.board_name || managingRouter.name}
                </span>
              </div>
              <div className="flex items-center justify-between">
                <span className="text-gray-500 dark:text-gray-400">RouterOS Version</span>
                <span className="font-mono font-semibold text-gray-900 dark:text-white">
                  v{managingRouter.resource?.version || "-"}
                </span>
              </div>
              <div className="flex items-center justify-between">
                <span className="text-gray-500 dark:text-gray-400">Beban CPU &amp; RAM</span>
                <span className="font-mono font-semibold text-gray-900 dark:text-white">
                  CPU {managingRouter.resource?.cpu_load ?? "-"}% / RAM {managingRouter.resource?.memory_percent ?? "-"}%
                </span>
              </div>
              <div className="flex items-center justify-between">
                <span className="text-gray-500 dark:text-gray-400">Memori Bebas</span>
                <span className="font-mono font-semibold text-gray-900 dark:text-white">
                  {formatBytes(managingRouter.resource?.free_memory)} / {formatBytes(managingRouter.resource?.total_memory)}
                </span>
              </div>
              <div className="flex items-center justify-between">
                <span className="text-gray-500 dark:text-gray-400">Uptime Sistem</span>
                <span className="font-mono font-medium text-gray-900 dark:text-white">
                  {formatUptime(managingRouter.resource?.uptime) ?? "-"}
                </span>
              </div>
              {managingRouter.location && (
                <div className="flex items-center justify-between">
                  <span className="text-gray-500 dark:text-gray-400">Lokasi / POP</span>
                  <span className="font-medium text-gray-900 dark:text-white">
                    {managingRouter.location}
                  </span>
                </div>
              )}
            </div>

            {/* Live Test Feedback Banner inside Modal */}
            {liveTestResult?.id === managingRouter.id && (
              <div
                className={cn(
                  "flex items-start gap-2.5 rounded-xl border p-3 text-xs animate-in fade-in duration-200",
                  liveTestResult.success
                    ? "border-emerald-500/30 bg-emerald-50 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300"
                    : "border-rose-500/30 bg-rose-50 text-rose-800 dark:bg-rose-950/40 dark:text-rose-300"
                )}
              >
                {liveTestResult.success ? (
                  <CheckCircle2 className="h-4 w-4 shrink-0 text-emerald-500 mt-0.5" />
                ) : (
                  <AlertTriangle className="h-4 w-4 shrink-0 text-rose-500 mt-0.5" />
                )}
                <div className="flex-1 min-w-0">
                  <div className="font-bold flex items-center gap-1.5">
                    <span>{liveTestResult.success ? "Koneksi API Berhasil" : "Koneksi API Gagal"}</span>
                    {liveTestResult.latency_ms && (
                      <span className="font-mono text-[11px] font-semibold">
                        ({liveTestResult.latency_ms}ms)
                      </span>
                    )}
                  </div>
                  <p className="mt-0.5 text-[11px] opacity-90">{liveTestResult.message}</p>
                </div>
              </div>
            )}

            {/* Action Buttons List */}
            <div className="space-y-2 pt-1 text-xs">
              {/* Detail & Live Traffic */}
              <button
                type="button"
                onClick={() => router.visit(`/admin/mikrotik/router/${managingRouter.id}`)}
                className="w-full group flex items-center justify-between p-3 rounded-xl bg-gray-50/70 hover:bg-brand-50/50 border border-gray-100 hover:border-brand-200 dark:bg-gray-800/40 dark:hover:bg-gray-800 dark:border-gray-800 dark:hover:border-brand-900/60 transition cursor-pointer"
              >
                <div className="flex items-center gap-2.5">
                  <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-brand-500 text-white shadow-2xs whitespace-nowrap">
                    <TrendingUp className="h-4 w-4" />
                  </div>
                  <div className="text-left">
                    <div className="font-bold text-gray-900 dark:text-white">Detail &amp; Live Traffic</div>
                    <div className="text-[11px] text-gray-500 dark:text-gray-400">Monitoring interface, bandwidth &amp; telemetri</div>
                  </div>
                </div>
                <ExternalLink className="h-4 w-4 text-gray-400 group-hover:text-brand-500 transition-colors" />
              </button>

              {/* Uji Koneksi API */}
              <button
                type="button"
                disabled={testingId === managingRouter.id}
                onClick={() => handleTestRouter(managingRouter)}
                className="w-full group flex items-center justify-between p-3 rounded-xl bg-gray-50/70 hover:bg-brand-50/50 border border-gray-100 hover:border-brand-200 dark:bg-gray-800/40 dark:hover:bg-gray-800 dark:border-gray-800 dark:hover:border-brand-900/60 transition cursor-pointer disabled:opacity-50"
              >
                <div className="flex items-center gap-2.5">
                  <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-500 text-white shadow-2xs whitespace-nowrap">
                    {testingId === managingRouter.id ? (
                      <Loader2 className="h-4 w-4 animate-spin" />
                    ) : (
                      <Activity className="h-4 w-4" />
                    )}
                  </div>
                  <div className="text-left">
                    <div className="font-bold text-gray-900 dark:text-white">
                      {testingId === managingRouter.id ? "Menguji Koneksi..." : "Uji Koneksi API"}
                    </div>
                    <div className="text-[11px] text-gray-500 dark:text-gray-400">Ping API port {managingRouter.port} &amp; cek latency</div>
                  </div>
                </div>
                <RefreshCw className={cn("h-4 w-4 text-gray-400 group-hover:text-emerald-500 transition-colors", testingId === managingRouter.id && "animate-spin")} />
              </button>

              {/* Edit Router */}
              <button
                type="button"
                onClick={() => openEdit(managingRouter)}
                className="w-full group flex items-center justify-between p-3 rounded-xl bg-gray-50/70 hover:bg-brand-50/50 border border-gray-100 hover:border-brand-200 dark:bg-gray-800/40 dark:hover:bg-gray-800 dark:border-gray-800 dark:hover:border-brand-900/60 transition cursor-pointer"
              >
                <div className="flex items-center gap-2.5">
                  <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-indigo-500 text-white shadow-2xs">
                    <Pencil className="h-4 w-4" />
                  </div>
                  <div className="text-left">
                    <div className="font-bold text-gray-900 dark:text-white">Edit Pengaturan Router</div>
                    <div className="text-[11px] text-gray-500 dark:text-gray-400">Ubah host IP, port, kredensial &amp; lokasi</div>
                  </div>
                </div>
                <Settings className="h-4 w-4 text-gray-400 group-hover:text-indigo-500 transition-colors" />
              </button>

              {/* Hapus Router */}
              <button
                type="button"
                onClick={() => {
                  setDeleteConfirmModal(managingRouter)
                  setManagingRouter(null)
                }}
                className="w-full group flex items-center justify-between p-3 rounded-xl bg-gray-50/70 hover:bg-rose-50/50 border border-gray-100 hover:border-rose-200 dark:bg-gray-800/40 dark:hover:bg-gray-800 dark:border-gray-800 dark:hover:border-rose-900/60 transition cursor-pointer"
              >
                <div className="flex items-center gap-2.5">
                  <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-rose-500 text-white shadow-2xs whitespace-nowrap">
                    <Trash2 className="h-4 w-4" />
                  </div>
                  <div className="text-left">
                    <div className="font-bold text-rose-600 dark:text-rose-400">Hapus Router</div>
                    <div className="text-[11px] text-gray-500 dark:text-gray-400">Hapus router ini dari database sistem</div>
                  </div>
                </div>
                <Trash2 className="h-4 w-4 text-rose-400 group-hover:text-rose-500 transition-colors" />
              </button>
            </div>
          </div>
        </div>
      )}

      {/* =========================================================================
          ADD / EDIT ROUTER MODAL
          ========================================================================= */}
      <Modal
        isOpen={modalOpen}
        onClose={() => setModalOpen(false)}
        title={editing ? "Edit Router Gateway" : "Tambah Router Gateway MikroTik"}
        description="Konfigurasi parameter autentikasi API RouterOS v6 / v7"
        maxWidth="2xl"
      >
        <form onSubmit={submit} className="space-y-4 max-h-[92vh] overflow-y-auto pr-1">
          {is_superadmin && (
            <div className="space-y-1.5">
              <label className="text-xs font-semibold text-gray-700 dark:text-gray-300">
                Tenant Pemilik Router <span className="text-rose-500">*</span>
              </label>
              <select
                value={form.data.tenant_id}
                onChange={(e) => form.setData("tenant_id", e.target.value)}
                className="w-full h-10 rounded-xl border border-gray-300 bg-white px-3 text-xs text-gray-900 focus:border-brand-500 focus:outline-none dark:border-gray-800 dark:bg-gray-900 dark:text-white"
                required
              >
                <option value="">Pilih Tenant...</option>
                {tenants.map((t) => (
                  <option key={t.id} value={t.id}>
                    {t.name} (ID: {t.id})
                  </option>
                ))}
              </select>
              {form.errors.tenant_id && <p className="text-[11px] text-rose-500">{form.errors.tenant_id}</p>}
            </div>
          )}

          <div className="space-y-1.5">
            <label className="text-xs font-semibold text-gray-700 dark:text-gray-300">
              Nama Router <span className="text-rose-500">*</span>
            </label>
            <input
              type="text"
              value={form.data.name}
              onChange={(e) => form.setData("name", e.target.value)}
              placeholder="cth: Router-Utama atau CCR1009-POP1"
              required
              className="w-full h-10 rounded-xl border border-gray-300 bg-white px-3 text-xs text-gray-900 placeholder:text-gray-400 focus:border-brand-500 focus:outline-none dark:border-gray-800 dark:bg-gray-900 dark:text-white dark:placeholder:text-gray-500 font-medium"
            />
            {form.errors.name && <p className="text-[11px] text-rose-500">{form.errors.name}</p>}
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div className="sm:col-span-2 space-y-1.5">
              <label className="text-xs font-semibold text-gray-700 dark:text-gray-300">
                Host / IP Publik / Domain VPN <span className="text-rose-500">*</span>
              </label>
              <input
                type="text"
                value={form.data.host}
                onChange={(e) => form.setData("host", e.target.value)}
                placeholder="cth: 103.11.41.43 atau vpn.dgtlnetsolution.com"
                required
                className="w-full h-10 rounded-xl border border-gray-300 bg-white px-3 text-xs text-gray-900 placeholder:text-gray-400 focus:border-brand-500 focus:outline-none dark:border-gray-800 dark:bg-gray-900 dark:text-white dark:placeholder:text-gray-500 font-mono font-medium"
              />
              {form.errors.host && <p className="text-[11px] text-rose-500">{form.errors.host}</p>}
            </div>

            <div className="space-y-1.5">
              <label className="text-xs font-semibold text-gray-700 dark:text-gray-300">
                Port API <span className="text-rose-500">*</span>
              </label>
              <input
                type="number"
                value={form.data.port}
                onChange={(e) => form.setData("port", e.target.value)}
                placeholder="8728"
                required
                className="w-full h-10 rounded-xl border border-gray-300 bg-white px-3 text-xs text-gray-900 placeholder:text-gray-400 focus:border-brand-500 focus:outline-none dark:border-gray-800 dark:bg-gray-900 dark:text-white dark:placeholder:text-gray-500 font-mono font-medium"
              />
              {form.errors.port && <p className="text-[11px] text-rose-500">{form.errors.port}</p>}
            </div>
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div className="space-y-1.5">
              <label className="text-xs font-semibold text-gray-700 dark:text-gray-300">
                Username API MikroTik <span className="text-rose-500">*</span>
              </label>
              <input
                type="text"
                value={form.data.username}
                onChange={(e) => form.setData("username", e.target.value)}
                placeholder="cth: admin atau nodera"
                required
                className="w-full h-10 rounded-xl border border-gray-300 bg-white px-3 text-xs text-gray-900 placeholder:text-gray-400 focus:border-brand-500 focus:outline-none dark:border-gray-800 dark:bg-gray-900 dark:text-white dark:placeholder:text-gray-500 font-medium"
              />
              {form.errors.username && <p className="text-[11px] text-rose-500">{form.errors.username}</p>}
            </div>

            <div className="space-y-1.5">
              <label className="text-xs font-semibold text-gray-700 dark:text-gray-300">
                Password API {editing && <span className="text-gray-400 text-[10px] font-normal">(Opsional)</span>}
              </label>
              <input
                type="password"
                value={form.data.password}
                onChange={(e) => form.setData("password", e.target.value)}
                placeholder={editing ? "Kosongkan jika tidak diubah" : "Password user MikroTik"}
                className="w-full h-10 rounded-xl border border-gray-300 bg-white px-3 text-xs text-gray-900 placeholder:text-gray-400 focus:border-brand-500 focus:outline-none dark:border-gray-800 dark:bg-gray-900 dark:text-white dark:placeholder:text-gray-500 font-medium"
              />
              {form.errors.password && <p className="text-[11px] text-rose-500">{form.errors.password}</p>}
            </div>
          </div>

          <div className="space-y-1.5">
            <label className="text-xs font-semibold text-gray-700 dark:text-gray-300">
              Lokasi / POP / Wilayah
            </label>
            <input
              type="text"
              value={form.data.location}
              onChange={(e) => form.setData("location", e.target.value)}
              placeholder="cth: Kantor NOC / Tower POP A"
              className="w-full h-10 rounded-xl border border-gray-300 bg-white px-3 text-xs text-gray-900 placeholder:text-gray-400 focus:border-brand-500 focus:outline-none dark:border-gray-800 dark:bg-gray-900 dark:text-white dark:placeholder:text-gray-500 font-medium"
            />
          </div>

          {!editing && (
            <div className="space-y-1.5">
              <label className="text-xs font-semibold text-gray-700 dark:text-gray-300">
                Sinkronisasi Otomatis Awal
              </label>
              <select
                value={form.data.initial_sync_mode}
                onChange={(e) => form.setData("initial_sync_mode", e.target.value)}
                className="w-full h-10 rounded-xl border border-gray-300 bg-white px-3 text-xs text-gray-900 focus:border-brand-500 focus:outline-none dark:border-gray-800 dark:bg-gray-900 dark:text-white"
              >
                <option value="pppoe">Pelanggan &amp; Paket PPPoE (PPP Secrets + Profiles)</option>
                <option value="arp">Pelanggan &amp; Paket IP Statis (ARP Binding + Simple Queues)</option>
                <option value="all">Semua Pelanggan &amp; Paket (PPPoE + IP Statis)</option>
                <option value="none">Lewati Sinkronisasi (Hanya Simpan Router)</option>
              </select>
              <p className="text-[11px] text-gray-500 dark:text-gray-400">
                Sistem otomatis menarik profil paket bandwidth sebelum mendaftarkan pelanggan ke database tagihan.
              </p>
            </div>
          )}

          <div className="pt-4 flex items-center justify-end gap-2 border-t border-gray-200 dark:border-gray-800">
            <button
              type="button"
              onClick={() => setModalOpen(false)}
              className="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:hover:bg-gray-800 text-xs font-semibold text-gray-700 dark:text-gray-200 transition shadow-2xs cursor-pointer"
            >
              Batal
            </button>
            <button
              type="submit"
              disabled={form.processing}
              className="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-brand-500 hover:bg-brand-600 active:scale-95 text-xs font-bold text-white shadow-xs transition cursor-pointer disabled:opacity-50"
            >
              {form.processing ? "Menyimpan..." : editing ? "Perbarui Router" : "Simpan & Hubungkan"}
            </button>
          </div>
        </form>
      </Modal>

      {/* =========================================================================
          DELETE CONFIRMATION MODAL
          ========================================================================= */}
      <Modal
        isOpen={Boolean(deleteConfirmModal)}
        onClose={() => setDeleteConfirmModal(null)}
        title="Hapus Router Gateway"
        description="Konfirmasi penghapusan router dari sistem billing"
        maxWidth="md"
      >
        <div className="space-y-4">
          <p className="text-xs text-gray-600 dark:text-gray-300 leading-relaxed">
            Apakah Anda yakin ingin menghapus router{" "}
            <strong className="text-gray-900 dark:text-white font-mono font-bold">{deleteConfirmModal?.name}</strong> (
            {deleteConfirmModal?.host}:{deleteConfirmModal?.port})?
          </p>
          <div className="flex items-center justify-end gap-2 pt-3 border-t border-gray-200 dark:border-gray-800">
            <button
              type="button"
              onClick={() => setDeleteConfirmModal(null)}
              className="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:hover:bg-gray-800 text-xs font-semibold text-gray-700 dark:text-gray-200 transition shadow-2xs cursor-pointer"
            >
              Batal
            </button>
            <button
              type="button"
              onClick={handleDelete}
              className="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-rose-500 hover:bg-rose-600 active:scale-95 text-xs font-bold text-white shadow-xs transition cursor-pointer"
            >
              Ya, Hapus Router
            </button>
          </div>
        </div>
      </Modal>

      {/* =========================================================================
          INFO MODAL: PANDUAN PENGISIAN HOST & PORT API MIKROTIK
          ========================================================================= */}
      <Modal
        isOpen={infoModalOpen}
        onClose={() => setInfoModalOpen(false)}
        title="Panduan Host & Port API MikroTik"
        description="Petunjuk format penulisan untuk menghubungkan Router ke Billing"
        maxWidth="2xl"
      >
        <div className="space-y-4 text-xs leading-relaxed max-h-[92vh] overflow-y-auto pr-1">
          {/* Urutan Sinkronisasi */}
          <div className="rounded-2xl border border-indigo-500/20 bg-indigo-50/50 dark:bg-indigo-500/10 p-4 space-y-3">
            <div className="flex items-center gap-2 text-xs font-bold text-indigo-700 dark:text-indigo-300 uppercase tracking-wider">
              <Layers className="h-4 w-4 text-indigo-500" />
              <span>Urutan Sinkronisasi &amp; Alur Kerja (Penting)</span>
            </div>

            <div className="space-y-2">
              <div className="flex items-start gap-3 p-3 rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800">
                <span className="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-indigo-600 text-white font-bold text-xs">
                  1
                </span>
                <div className="space-y-0.5 flex-1">
                  <h4 className="font-bold text-gray-900 dark:text-white text-xs flex items-center justify-between">
                    <span>Hubungkan Router Gateway</span>
                    <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold bg-emerald-500 text-white shadow-xs whitespace-nowrap">
                      Langkah 1
                    </span>
                  </h4>
                  <p className="text-[11px] text-gray-600 dark:text-gray-300">
                    Daftarkan router menggunakan Host &amp; Port API yang sesuai, lalu pastikan koneksi router berstatus Online.
                  </p>
                </div>
              </div>

              <div className="flex items-start gap-3 p-3 rounded-xl bg-white dark:bg-gray-900 border border-indigo-500/30">
                <span className="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-cyan-600 text-white font-bold text-xs">
                  2
                </span>
                <div className="space-y-0.5 flex-1">
                  <h4 className="font-bold text-cyan-600 dark:text-cyan-400 text-xs flex items-center justify-between">
                    <span>Sync / Buat Paket Langganan Dulu</span>
                    <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold bg-brand-500 text-white shadow-xs whitespace-nowrap">
                      Wajib Awal
                    </span>
                  </h4>
                  <p className="text-[11px] text-gray-600 dark:text-gray-300">
                    Buka menu Paket Langganan &rarr; Lakukan Sinkronisasi Profil PPP/Hotspot dari MikroTik atau tambahkan paket baru.
                  </p>
                </div>
              </div>

              <div className="flex items-start gap-3 p-3 rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800">
                <span className="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-indigo-600 text-white font-bold text-xs">
                  3
                </span>
                <div className="space-y-0.5 flex-1">
                  <h4 className="font-bold text-gray-900 dark:text-white text-xs flex items-center justify-between">
                    <span>Sync / Daftarkan Data Pelanggan</span>
                    <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold bg-gray-500 text-white shadow-xs whitespace-nowrap">
                      Langkah 3
                    </span>
                  </h4>
                  <p className="text-[11px] text-gray-600 dark:text-gray-300">
                    Setelah paket siap, buka menu Pelanggan atau PPPoE Secrets untuk import/sync user dari MikroTik.
                  </p>
                </div>
              </div>

              <div className="flex items-start gap-3 p-3 rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800">
                <span className="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-emerald-600 text-white font-bold text-xs whitespace-nowrap">
                  4
                </span>
                <div className="space-y-0.5 flex-1">
                  <h4 className="font-bold text-emerald-600 dark:text-emerald-400 text-xs flex items-center justify-between">
                    <span>Otomatisasi Tagihan &amp; Isolir Berjalan</span>
                    <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold bg-emerald-500 text-white shadow-xs whitespace-nowrap">
                      Aktif Otomatis
                    </span>
                  </h4>
                  <p className="text-[11px] text-gray-600 dark:text-gray-300">
                    Invoice bulanan, notifikasi WhatsApp pembayaran, serta pemblokiran/isolir secret PPPoE otomatis dieksekusi oleh sistem billing.
                  </p>
                </div>
              </div>
            </div>
          </div>

          {/* Opsi 1: VPN Remote */}
          <div className="rounded-2xl border border-brand-500/20 bg-brand-50/50 dark:bg-brand-500/10 p-4 space-y-2.5">
            <div className="flex items-center gap-2 text-xs font-bold text-brand-600 dark:text-brand-400 uppercase tracking-wider">
              <Server className="h-4 w-4" />
              <span>Opsi 1: Menggunakan Akun VPN Remote (Tanpa IP Publik)</span>
            </div>
            <p className="text-gray-700 dark:text-gray-300">
              Gunakan opsi ini jika router berada di balik modem ISP/NAT (tanpa IP Publik dedicated):
            </p>
            <div className="grid gap-2 sm:grid-cols-2 pt-1 font-mono">
              <div className="p-2.5 rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 space-y-1">
                <span className="text-[10px] text-gray-500 dark:text-gray-400 font-sans uppercase font-bold block">
                  Contoh Host / Domain
                </span>
                <span className="text-gray-900 dark:text-white font-bold text-xs select-all">vpn.dgtlnetsolution.com</span>
                <p className="text-[10px] text-gray-500 dark:text-gray-400 font-sans mt-0.5">
                  atau IP Server VPN <code className="text-brand-600 dark:text-brand-400">103.11.41.43</code>
                </p>
              </div>
              <div className="p-2.5 rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 space-y-1">
                <span className="text-[10px] text-gray-500 dark:text-gray-400 font-sans uppercase font-bold block">
                  Contoh API Port
                </span>
                <span className="text-brand-600 dark:text-brand-400 font-bold text-xs select-all">56347</span>
                <p className="text-[10px] text-gray-500 dark:text-gray-400 font-sans mt-0.5">Port remote forward ke port 8728</p>
              </div>
            </div>
          </div>

          {/* Opsi 2: IP Publik Statis */}
          <div className="rounded-2xl border border-emerald-500/20 bg-emerald-50/50 dark:bg-emerald-500/10 p-4 space-y-2.5">
            <div className="flex items-center gap-2 text-xs font-bold text-emerald-700 dark:text-emerald-400 uppercase tracking-wider">
              <Globe className="h-4 w-4" />
              <span>Opsi 2: Menggunakan IP Publik Statis / Jaringan Lokal</span>
            </div>
            <p className="text-gray-700 dark:text-gray-300">
              Gunakan opsi ini jika router memiliki IP Publik langsung atau satu jaringan lokal dengan server:
            </p>
            <div className="grid gap-2 sm:grid-cols-2 pt-1 font-mono">
              <div className="p-2.5 rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 space-y-1">
                <span className="text-[10px] text-gray-500 dark:text-gray-400 font-sans uppercase font-bold block">
                  Contoh Host / IP
                </span>
                <span className="text-gray-900 dark:text-white font-bold text-xs select-all">103.11.41.43</span>
                <p className="text-[10px] text-gray-500 dark:text-gray-400 font-sans mt-0.5">
                  atau IP lokal <code className="text-emerald-600 dark:text-emerald-400">192.168.88.1</code>
                </p>
              </div>
              <div className="p-2.5 rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 space-y-1">
                <span className="text-[10px] text-gray-500 dark:text-gray-400 font-sans uppercase font-bold block">
                  Contoh API Port
                </span>
                <span className="text-emerald-600 dark:text-emerald-400 font-bold text-xs select-all">8728</span>
                <p className="text-[10px] text-gray-500 dark:text-gray-400 font-sans mt-0.5">Port API default MikroTik</p>
              </div>
            </div>
          </div>

          {/* Checklist Pengaturan MikroTik */}
          <div className="rounded-2xl border border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-900/50 p-4 space-y-2">
            <div className="text-xs font-bold text-amber-700 dark:text-amber-400 uppercase tracking-wider flex items-center gap-2">
              <CheckCircle2 className="h-4 w-4" />
              <span>Checklist Pengaturan di Winbox RouterOS</span>
            </div>
            <ul className="space-y-1.5 text-gray-700 dark:text-gray-300 pl-1 list-none">
              <li className="flex items-start gap-2">
                <span className="h-1.5 w-1.5 rounded-full bg-brand-500 mt-1.5 shrink-0 whitespace-nowrap" />
                <span>
                  Buka Winbox &rarr; Menu <b>IP &rarr; Services</b> &rarr; Pastikan service <b>api</b> aktif (<b>Enable</b>).
                </span>
              </li>
              <li className="flex items-start gap-2">
                <span className="h-1.5 w-1.5 rounded-full bg-brand-500 mt-1.5 shrink-0 whitespace-nowrap" />
                <span>
                  Buka Menu <b>System &rarr; Users</b> &rarr; Pastikan user yang didaftarkan memiliki group <b>full</b> atau <b>write</b>.
                </span>
              </li>
              <li className="flex items-start gap-2">
                <span className="h-1.5 w-1.5 rounded-full bg-brand-500 mt-1.5 shrink-0 whitespace-nowrap" />
                <span>
                  Jika menggunakan VPN Remote, pastikan script koneksi VPN sudah dijalankan di Terminal MikroTik dan akun berstatus <b>Connected</b>.
                </span>
              </li>
            </ul>
          </div>
        </div>

        <div className="pt-4 flex justify-end border-t border-gray-200 dark:border-gray-800">
          <button
            type="button"
            onClick={() => setInfoModalOpen(false)}
            className="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-brand-500 hover:bg-brand-600 active:scale-95 text-xs font-bold text-white shadow-xs transition cursor-pointer"
          >
            Saya Mengerti
          </button>
        </div>
      </Modal>
    </AppLayout>
  )
}
