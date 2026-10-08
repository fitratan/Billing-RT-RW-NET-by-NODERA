import { useState, useEffect, useMemo, useDeferredValue, useRef } from "react"
import Chart from "react-apexcharts"
import { AppLayout } from "@/components/layout/app-layout"
import {
  KeyRound,
  Wifi,
  WifiOff,
  Clock,
  Search,
  Activity,
  User,
  Radio,
  Server,
  Phone,
  X,
  Power,
  MessageCircle,
  Hash,
  Layers,
  ArrowDownLeft,
  ArrowUpRight,
  ExternalLink,
  Trash2,
  Filter,
  Settings,
  ShieldCheck,
  CheckCircle2,
  AlertTriangle,
} from "lucide-react"
import { EmptyState } from "@/components/ui/empty-state"
import { PageProps } from "@/types"
import { adminSidebarItems, adminNavItems, adminBrand } from "@/lib/admin-nav"
import { collectorNavItems, collectorSidebarItems, collectorBrand } from "@/lib/collector-nav"
import { technicianNavItems, technicianSidebarItems, technicianBrand } from "@/lib/technician-nav"
import { formatUptime, cn } from "@/lib/utils"
import { SyncOverlay } from "@/components/ui/sync-overlay"
import { ViewModeSwitcher } from "@/components/ui/view-mode-switcher"
import { router, Link } from "@inertiajs/react"

interface PppoeUser {
  name: string
  router_id?: number | null
  router_name?: string | null
  password?: string
  profile?: string
  disabled: boolean
  last_logged_out?: string | null
  customer_name?: string | null
  customer_id?: number | null
  phone?: string | null
  onu_status?: string | null
  onu_rx_power?: number | null
  onu_pon?: string | null
  onu_name?: string | null
  olt_name?: string | null
  bytes_in?: number
  bytes_out?: number
  total_bytes?: number
}

interface ActiveUser {
  name: string
  router_id?: number | null
  router_name?: string | null
  address: string
  uptime: string
  bytes_in?: number
  bytes_out?: number
  total_bytes?: number
  customer_name?: string | null
  customer_id?: number | null
  phone?: string | null
  onu_status?: string | null
  onu_rx_power?: number | null
  onu_pon?: string | null
  onu_name?: string | null
  olt_name?: string | null
}

interface InactiveUser {
  name: string
  router_id?: number | null
  router_name?: string | null
  profile?: string
  last_logged_out?: string | null
  customer_name?: string | null
  customer_id?: number | null
  phone?: string | null
  onu_status?: string | null
  onu_rx_power?: number | null
  onu_pon?: string | null
  onu_name?: string | null
  olt_name?: string | null
  bytes_in?: number
  bytes_out?: number
  total_bytes?: number
}

function formatBytes(bytes?: number): string {
  if (!bytes || bytes <= 0) return "0 B"
  const k = 1024
  const sizes = ["B", "KB", "MB", "GB", "TB"]
  const i = Math.floor(Math.log(bytes) / Math.log(k))
  return `${parseFloat((bytes / Math.pow(k, i)).toFixed(1))} ${sizes[i]}`
}

export default function PppoePage({
  users = [],
  active = [],
  inactive = [],
  error,
  routers = [],
  routerId,
  mode,
  companyName = "NODERA Billing",
  tenantName,
}: PageProps<{
  users: PppoeUser[]
  active: ActiveUser[]
  inactive?: InactiveUser[]
  error: string | null
  routers: { id: number; name: string }[]
  routerId: string | null
  mode?: "admin" | "collector" | "technician"
  companyName?: string
  tenantName?: string
}>) {
  const [tab, setTab] = useState<"active" | "offline" | "users">("active")
  const [viewMode, setViewMode] = useState<"table" | "grid">("grid")
  const [profileFilter, setProfileFilter] = useState("all")
  const [q, setQ] = useState("")
  const [loading, setLoading] = useState(false)
  const [autoRefresh, setAutoRefresh] = useState(true)
  const [countdown, setCountdown] = useState(15)
  const [visibleLimit, setVisibleLimit] = useState(40)
  const [isAutoSyncing, setIsAutoSyncing] = useState(false)
  const [managingPppoe, setManagingPppoe] = useState<any | null>(null)
  const [deleteConfirmUser, setDeleteConfirmUser] = useState<any | null>(null)

  const isCollector = mode === "collector"
  const isTechnician = mode === "technician"

  const handleRefresh = () => {
    setLoading(true)
    router.reload({
      preserveScroll: true,
      preserveState: true,
      onFinish: () => setLoading(false),
    })
  }

  const handleKick = (username: string, targetRouterId?: number | string | null) => {
    const effectiveRouterId = targetRouterId || routerId
    if (!effectiveRouterId || effectiveRouterId === "all") {
      alert("Pilih spesifik router terlebih dahulu untuk memutuskan sesi")
      return
    }
    const endpoint =
      mode === "technician"
        ? `/teknisi/pppoe/kick/${username}`
        : mode === "collector"
        ? `/kolektor/pppoe/kick/${username}`
        : `/admin/pppoe/kick/${username}`

    router.post(
      endpoint,
      { router_id: effectiveRouterId },
      {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
          setManagingPppoe(null)
          handleRefresh()
        },
      }
    )
  }

  const handleDeleteSecret = (username: string, targetRouterId?: number | string | null) => {
    const effectiveRouterId = targetRouterId || routerId
    if (!effectiveRouterId || effectiveRouterId === "all") {
      alert("Pilih spesifik router terlebih dahulu untuk menghapus secret")
      return
    }
    const endpoint =
      mode === "technician"
        ? `/teknisi/pppoe/secret/delete/${username}`
        : mode === "collector"
        ? `/kolektor/pppoe/secret/delete/${username}`
        : `/admin/pppoe/secret/delete/${username}`

    router.post(
      endpoint,
      { router_id: effectiveRouterId },
      {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
          setManagingPppoe(null)
          setDeleteConfirmUser(null)
          handleRefresh()
        },
      }
    )
  }

  // Auto-refresh countdown
  useEffect(() => {
    if (!autoRefresh || !routerId || loading) return
    const interval = setInterval(() => {
      setCountdown((prev) => {
        if (prev <= 1) {
          setIsAutoSyncing(true)
          router.reload({
            only: ["users", "active", "inactive", "error"],
            preserveScroll: true,
            preserveState: true,
            onFinish: () => setIsAutoSyncing(false),
          })
          return 15
        }
        return prev - 1
      })
    }, 1000)
    return () => clearInterval(interval)
  }, [autoRefresh, routerId, loading])

  const getPowerMeta = (power: number | null | undefined) => {
    if (power == null)
      return {
        badgeCls: "bg-gray-500 text-white shadow-xs",
        label: "No Data",
      }
    if (power >= -23 && power <= -10)
      return {
        badgeCls: "bg-emerald-500 text-white shadow-xs",
        label: "Bagus",
      }
    if (power >= -25 && power < -23)
      return {
        badgeCls: "bg-amber-500 text-white shadow-xs",
        label: "Sedang",
      }
    return {
      badgeCls: "bg-rose-500 text-white shadow-xs",
      label: "Redaman Jelek",
    }
  }

  const availableProfiles = useMemo(() => {
    const set = new Set<string>()
    users.forEach((u) => {
      if (u.profile) set.add(u.profile)
    })
    inactive?.forEach((u) => {
      if (u.profile) set.add(u.profile)
    })
    return Array.from(set).sort()
  }, [users, inactive])

  const deferredQuery = useDeferredValue(q)

  const filteredActive = useMemo(() => {
    let list = active
    if (profileFilter !== "all") {
      const userProfileMap = new Map<string, string>()
      users.forEach((u) => userProfileMap.set(u.name, u.profile || "default"))
      list = list.filter((a) => (userProfileMap.get(a.name) || "default") === profileFilter)
    }
    if (!deferredQuery) return list
    const lower = deferredQuery.toLowerCase()
    return list.filter(
      (a) =>
        a.name.toLowerCase().includes(lower) ||
        (a.customer_name && a.customer_name.toLowerCase().includes(lower)) ||
        a.address.toLowerCase().includes(lower) ||
        (a.phone && a.phone.includes(lower))
    )
  }, [active, users, profileFilter, deferredQuery])

  const displayedActive = useMemo(() => filteredActive.slice(0, visibleLimit), [filteredActive, visibleLimit])

  const filteredInactive = useMemo(() => {
    let list = inactive || []
    if (profileFilter !== "all") {
      list = list.filter((u) => (u.profile || "default") === profileFilter)
    }
    if (!deferredQuery) return list
    const lower = deferredQuery.toLowerCase()
    return list.filter(
      (u) =>
        u.name.toLowerCase().includes(lower) ||
        (u.customer_name && u.customer_name.toLowerCase().includes(lower)) ||
        (u.profile && u.profile.toLowerCase().includes(lower)) ||
        (u.phone && u.phone.includes(lower))
    )
  }, [inactive, profileFilter, deferredQuery])

  const displayedInactive = useMemo(() => filteredInactive.slice(0, visibleLimit), [filteredInactive, visibleLimit])

  const filteredUsers = useMemo(() => {
    let list = users
    if (profileFilter !== "all") {
      list = list.filter((u) => (u.profile || "default") === profileFilter)
    }
    if (!deferredQuery) return list
    const lower = deferredQuery.toLowerCase()
    return list.filter(
      (u) =>
        u.name.toLowerCase().includes(lower) ||
        (u.customer_name && u.customer_name.toLowerCase().includes(lower)) ||
        (u.profile && u.profile.toLowerCase().includes(lower)) ||
        (u.phone && u.phone.includes(lower))
    )
  }, [users, profileFilter, deferredQuery])

  const displayedUsers = useMemo(() => filteredUsers.slice(0, visibleLimit), [filteredUsers, visibleLimit])

  // ── Executive ApexCharts Data & Options ──
  const profileStats = useMemo(() => {
    const counts: Record<string, number> = {}
    users.forEach((u) => {
      const p = u.profile || "default"
      counts[p] = (counts[p] || 0) + 1
    })
    const sorted = Object.entries(counts).sort((a, b) => b[1] - a[1])
    const labels = sorted.slice(0, 5).map(([k]) => k)
    const series = sorted.slice(0, 5).map(([, v]) => v)
    const otherCount = sorted.slice(5).reduce((acc, [, v]) => acc + v, 0)
    if (otherCount > 0) {
      labels.push("Lainnya")
      series.push(otherCount)
    }
    return { labels: labels.length > 0 ? labels : ["Default"], series: series.length > 0 ? series : [users.length || 1] }
  }, [users])

  const totalSecrets = users.length || 1
  const onlineCount = active.length
  const offlineCount = inactive?.length || 0
  const onlineRate = Math.round((onlineCount / totalSecrets) * 100)

  const profileDonutOptions = useMemo(() => ({
    chart: { type: "donut" as const, fontFamily: "Outfit, Inter, sans-serif" },
    labels: profileStats.labels,
    colors: ["#0073C6", "#10B981", "#F59E0B", "#8B5CF6", "#EC4899", "#64748B"],
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
              label: "Total Secret",
              fontSize: "11px",
              fontWeight: 600,
              color: "#64748B",
              formatter: () => `${users.length}`,
            },
            value: { fontSize: "18px", fontWeight: 700, color: "#0F172A" },
          },
        },
      },
    },
    tooltip: { theme: "light" },
  }), [profileStats, users.length])

  const sessionRadialOptions = useMemo(() => ({
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
            color: onlineRate >= 80 ? "#10B981" : "#0073C6",
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
        gradientToColors: ["#10B981"],
        stops: [0, 100],
      },
    },
    stroke: { dashArray: 4 },
    colors: ["#0073C6"],
    labels: ["Keaktifan"],
  }), [onlineRate])

  const sidebarItems = isCollector
    ? collectorSidebarItems
    : isTechnician
    ? technicianNavItems
    : adminSidebarItems
  const navItems = isCollector
    ? collectorNavItems
    : isTechnician
    ? technicianNavItems
    : adminNavItems
  const brand = isCollector
    ? collectorBrand
    : isTechnician
    ? technicianBrand
    : adminBrand

  return (
    <AppLayout
      title="Monitoring PPPoE"
      brand={brand}
      sidebarItems={sidebarItems}
      navItems={navItems}
    >
      <div className="space-y-4 sm:space-y-6">
        {/* ── Executive Multi-Type Visual Analytics ── */}
        <div className="grid grid-cols-1 lg:grid-cols-12 gap-4 sm:gap-5">
          {/* Card 1: Rasio Sesi Online & Telemetri Gateway (5 Cols) */}
          <div className="lg:col-span-5 rounded-2xl border border-gray-200 bg-white p-4 sm:p-5 shadow-xs dark:border-gray-800 dark:bg-white/[0.03] flex flex-col justify-between">
            <div className="flex items-center justify-between border-b border-gray-100 dark:border-gray-800/80 pb-3">
              <div>
                <h3 className="text-sm font-bold text-gray-900 dark:text-white flex items-center gap-2">
                  <Wifi className="h-4 w-4 text-emerald-500" />
                  <span>Sesi Aktif &amp; Keaktifan PPPoE</span>
                </h3>
                <p className="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                  {tenantName || companyName}
                </p>
              </div>
              <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-bold bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400">
                <span className="h-2 w-2 rounded-full bg-emerald-500 animate-pulse" />
                {onlineCount} Online
              </span>
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-12 gap-3 items-center py-2">
              <div className="sm:col-span-5 flex justify-center">
                <Chart
                  options={sessionRadialOptions as any}
                  series={[onlineRate]}
                  type="radialBar"
                  height={150}
                  width="100%"
                />
              </div>
              <div className="sm:col-span-7 space-y-2">
                <div className="flex items-center justify-between rounded-xl bg-gray-50/80 p-2.5 dark:bg-gray-900/50 border border-gray-100 dark:border-gray-800/60">
                  <span className="text-xs text-gray-500 dark:text-gray-400 flex items-center gap-1.5">
                    <Wifi className="h-3.5 w-3.5 text-emerald-500" /> Terhubung (Active)
                  </span>
                  <span className="font-bold text-gray-900 dark:text-white text-xs">{onlineCount}</span>
                </div>
                <div className="flex items-center justify-between rounded-xl bg-gray-50/80 p-2.5 dark:bg-gray-900/50 border border-gray-100 dark:border-gray-800/60">
                  <span className="text-xs text-gray-500 dark:text-gray-400 flex items-center gap-1.5">
                    <WifiOff className="h-3.5 w-3.5 text-rose-500" /> Standby / Offline
                  </span>
                  <span className="font-bold text-rose-600 dark:text-rose-400 text-xs">{offlineCount}</span>
                </div>
                <div className="flex items-center justify-between rounded-xl bg-gray-50/80 p-2.5 dark:bg-gray-900/50 border border-gray-100 dark:border-gray-800/60">
                  <span className="text-xs text-gray-500 dark:text-gray-400 flex items-center gap-1.5">
                    <Server className="h-3.5 w-3.5 text-amber-500" /> Total Secret Terdaftar
                  </span>
                  <span className="font-bold text-gray-900 dark:text-white text-xs">{users.length}</span>
                </div>
              </div>
            </div>
          </div>

          {/* Card 2: Distribusi Profil Paket PPPoE (7 Cols) */}
          <div className="lg:col-span-7 rounded-2xl border border-gray-200 bg-white p-4 sm:p-5 shadow-xs dark:border-gray-800 dark:bg-white/[0.03] flex flex-col justify-between">
            <div className="flex items-center justify-between border-b border-gray-100 dark:border-gray-800/80 pb-3">
              <div>
                <h3 className="text-sm font-bold text-gray-900 dark:text-white flex items-center gap-2">
                  <KeyRound className="h-4 w-4 text-brand-500" />
                  <span>Komposisi Paket / Profil PPPoE</span>
                </h3>
                <p className="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                  Proporsi paket langganan pelanggan aktif &amp; secret
                </p>
              </div>
              <div className="flex items-center gap-1.5">
                <span className="text-xs font-semibold text-gray-500 dark:text-gray-400">
                  {availableProfiles.length} Profil
                </span>
              </div>
            </div>

            <div className="pt-2">
              <Chart
                options={profileDonutOptions as any}
                series={profileStats.series}
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
            {/* Search Bar */}
            <div className="relative flex-1 min-w-[180px] sm:min-w-[220px] w-full sm:w-auto">
              <Search className="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400 dark:text-gray-500" />
              <input
                type="text"
                value={q}
                onChange={(e) => setQ(e.target.value)}
                placeholder="Cari username PPPoE, nama pelanggan, IP, no HP..."
                className="h-10 w-full rounded-xl border border-gray-200 bg-gray-50/75 pl-9 pr-8 text-xs text-gray-800 placeholder:text-gray-400 focus:border-brand-500 focus:outline-none dark:border-gray-800 dark:bg-gray-900/50 dark:text-gray-200 dark:placeholder:text-gray-500 font-medium transition"
              />
              {q && (
                <button
                  type="button"
                  onClick={() => setQ("")}
                  className="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 cursor-pointer"
                >
                  <X className="h-3.5 w-3.5" />
                </button>
              )}
            </div>

            {/* Status Select */}
            <select
              value={tab}
              onChange={(e) => setTab(e.target.value as any)}
              className="h-10 rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 px-3 text-xs font-semibold text-gray-700 dark:text-gray-200 cursor-pointer w-full sm:w-auto shrink-0 shadow-2xs"
            >
              <option value="active">Online ({active.length})</option>
              <option value="offline">Offline ({inactive?.length || 0})</option>
              <option value="users">Semua Secret ({users.length})</option>
            </select>

            {/* Router Selector */}
            {routers.length > 0 && (
              <select
                value={routerId || ""}
                onChange={(e) => {
                  const val = e.target.value
                  const endpoint =
                    mode === "technician"
                      ? "/teknisi/pppoe"
                      : mode === "collector"
                      ? "/kolektor/pppoe"
                      : "/admin/pppoe"
                  router.get(endpoint, val ? { router_id: val } : {}, {
                    preserveState: true,
                    preserveScroll: true,
                  })
                }}
                className="h-10 rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 px-3 text-xs font-semibold text-gray-700 dark:text-gray-200 cursor-pointer w-full sm:w-auto shrink-0 shadow-2xs max-w-[200px] truncate"
              >
                {routers.length > 1 && (
                  <option value="all">Semua Router ({routers.length})</option>
                )}
                {routers.map((r) => (
                  <option key={r.id} value={r.id}>
                    Router: {r.name}
                  </option>
                ))}
              </select>
            )}

            {/* Profile Filter */}
            {availableProfiles.length > 0 && (
              <select
                value={profileFilter}
                onChange={(e) => setProfileFilter(e.target.value)}
                className="h-10 rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 px-3 text-xs font-semibold text-gray-700 dark:text-gray-200 cursor-pointer w-full sm:w-auto shrink-0 shadow-2xs max-w-[180px] truncate"
              >
                <option value="all">Semua Profil ({availableProfiles.length})</option>
                {availableProfiles.map((prof) => (
                  <option key={prof} value={prof}>
                    {prof}
                  </option>
                ))}
              </select>
            )}
          </div>

          <div className="flex items-center gap-2 shrink-0 w-full sm:w-auto justify-end">
            <button
              type="button"
              onClick={() => setAutoRefresh(!autoRefresh)}
              className={cn(
                "h-10 inline-flex items-center gap-1.5 px-3 rounded-xl border text-xs font-semibold transition cursor-pointer shadow-2xs shrink-0",
                autoRefresh
                  ? "border-emerald-500/40 bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400 font-bold"
                  : "border-gray-200 bg-white text-gray-700 hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-200"
              )}
              title={autoRefresh ? "Auto Refresh Aktif" : "Auto Refresh Nonaktif"}
            >
              <span className={cn("h-2 w-2 rounded-full shrink-0", autoRefresh ? "bg-emerald-500 animate-pulse" : "bg-gray-400")} />
              <span className="hidden sm:inline">{autoRefresh ? `Auto (${countdown}s)` : "Auto Refresh"}</span>
            </button>

            <div className="shrink-0">
              <ViewModeSwitcher value={viewMode} onChange={setViewMode} size="sm" />
            </div>
          </div>
        </div>

        {/* Master Container */}
        <div className="rounded-2xl border border-gray-200 bg-white shadow-xs dark:border-gray-800 dark:bg-white/[0.03] overflow-hidden">
          {/* Feedback Error Banner */}
          {error && (
            <div className="p-4 bg-rose-50 dark:bg-rose-950/40 border-b border-rose-200 dark:border-rose-900/50 text-xs font-semibold text-rose-700 dark:text-rose-300">
              {error}
            </div>
          )}

          {/* Content Stream */}
          {tab === "active" && (
            displayedActive.length === 0 ? (
              <div className="p-8 text-center">
                <EmptyState
                  icon={<Wifi className="h-8 w-8 text-emerald-500" />}
                  title="Tidak ada sesi aktif online"
                  description={q ? "Tidak ada sesi yang cocok dengan pencarian." : "Belum ada klien PPPoE yang terhubung aktif ke router."}
                />
              </div>
            ) : viewMode === "grid" ? (
              <div className="p-4 sm:p-5 grid gap-3.5 sm:grid-cols-2 lg:grid-cols-3">
                {displayedActive.map((a, idx) => {
                  const power = getPowerMeta(a.onu_rx_power)
                  return (
                    <div
                      key={idx}
                      className="relative rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03] p-4 sm:p-5 shadow-xs transition-all hover:border-brand-500/40 dark:hover:border-brand-500/40 space-y-3.5 select-none"
                    >
                      <div className="flex items-start justify-between gap-2.5">
                        <div className="flex items-center gap-3 min-w-0 flex-1 overflow-hidden">
                          <div className="relative flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400 font-bold text-xs">
                            <Wifi className="h-5 w-5" />
                            <span className="absolute -bottom-0.5 -right-0.5 h-2.5 w-2.5 rounded-full bg-emerald-500 ring-2 ring-white dark:ring-gray-900 animate-pulse whitespace-nowrap" />
                          </div>

                          <div className="min-w-0 flex-1">
                            <h4 className="font-bold text-sm text-gray-900 dark:text-white truncate">
                              {a.name}
                            </h4>
                            <div className="mt-0.5 flex items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400 font-medium truncate">
                              <span className="font-mono text-emerald-600 dark:text-emerald-400 font-semibold">{a.address}</span>
                              {a.customer_name && (
                                <>
                                  <span>•</span>
                                  <span className="truncate">{a.customer_name}</span>
                                </>
                              )}
                            </div>
                          </div>
                        </div>

                        <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold bg-emerald-500 text-white shadow-xs shrink-0 whitespace-nowrap">
                          Online
                        </span>
                      </div>

                      {/* Sunken Box for Telemetry & Signal */}
                      <div className="rounded-xl border border-gray-100 bg-gray-50/80 dark:border-gray-800 dark:bg-gray-900/50 p-3 grid grid-cols-2 gap-2 text-xs">
                        <div>
                          <span className="text-[10px] text-gray-500 dark:text-gray-400 block mb-0.5">Sinyal ONU</span>
                          {a.onu_rx_power != null ? (
                            <span className={cn("inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold", power.badgeCls)}>
                              {a.onu_rx_power} dBm ({power.label})
                            </span>
                          ) : (
                            <span className="font-mono text-gray-400 text-[11px]">- (Tanpa ONU)</span>
                          )}
                        </div>
                        <div>
                          <span className="text-[10px] text-gray-500 dark:text-gray-400 block mb-0.5">Durasi Online</span>
                          <span className="font-mono font-bold text-gray-800 dark:text-gray-200">
                            {formatUptime(a.uptime)}
                          </span>
                        </div>
                      </div>

                      {/* Bottom Action: Single Kelola Button */}
                      <div className="pt-2 border-t border-gray-100 dark:border-gray-800 flex items-center justify-between">
                        <span className="text-[11px] text-gray-500 dark:text-gray-400 font-mono">
                          {a.router_name || "MikroTik Gateway"}
                        </span>

                        <button
                          type="button"
                          onClick={() => setManagingPppoe(a)}
                          className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200 font-bold text-xs transition cursor-pointer"
                          title="Kelola Sesi"
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
                <table className="w-full min-w-[1000px] text-left text-xs text-gray-700 dark:text-gray-200">
                  <thead className="border-b border-gray-200 bg-gray-50/75 dark:border-gray-800 dark:bg-gray-800/40 text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                    <tr>
                      <th className="py-3 px-4 sm:px-6">Status Sesi</th>
                      <th className="py-3 px-4">Username PPPoE</th>
                      <th className="py-3 px-4">Pelanggan Terikat</th>
                      <th className="py-3 px-4">IP Address</th>
                      <th className="py-3 px-4">Sinyal Optik</th>
                      <th className="py-3 px-4">Uptime Online</th>
                      <th className="py-3 px-4">Router Gateway</th>
                      <th className="py-3 px-4 text-end sm:px-6">Aksi</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-gray-100 dark:divide-gray-800">
                    {displayedActive.map((a, idx) => {
                      const power = getPowerMeta(a.onu_rx_power)
                      return (
                        <tr key={idx} className="hover:bg-gray-50/50 dark:hover:bg-white/[0.02] transition-colors">
                          <td className="py-3.5 px-4 sm:px-6">
                            <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold bg-emerald-500 text-white shadow-xs whitespace-nowrap">
                              Online
                            </span>
                          </td>
                          <td className="py-3.5 px-4 font-bold text-gray-900 dark:text-white font-mono">
                            {a.name}
                          </td>
                          <td className="py-3.5 px-4 text-xs font-medium text-gray-600 dark:text-gray-300">
                            {a.customer_name || a.phone || <span className="text-gray-400 italic">Umum / Tidak terikat</span>}
                          </td>
                          <td className="py-3.5 px-4 font-mono font-bold text-emerald-600 dark:text-emerald-400">
                            {a.address || "-"}
                          </td>
                          <td className="py-3.5 px-4">
                            {a.onu_rx_power != null ? (
                              <span className={cn("inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold", power.badgeCls)}>
                                {a.onu_rx_power} dBm
                              </span>
                            ) : (
                              <span className="text-gray-400 font-mono text-[11px]">-</span>
                            )}
                          </td>
                          <td className="py-3.5 px-4 font-mono text-[11px] text-gray-600 dark:text-gray-400">
                            {formatUptime(a.uptime)}
                          </td>
                          <td className="py-3.5 px-4 text-gray-500 dark:text-gray-400 text-xs font-mono">
                            {a.router_name || "Router"}
                          </td>
                          <td className="py-3.5 px-4 sm:px-6 text-end">
                            <button
                              type="button"
                              onClick={() => setManagingPppoe(a)}
                              className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200 font-bold text-xs transition cursor-pointer"
                              title="Kelola Sesi"
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
            )
          )}

          {tab === "offline" && (
            displayedInactive.length === 0 ? (
              <div className="p-8 text-center">
                <EmptyState
                  icon={<WifiOff className="h-8 w-8 text-rose-500" />}
                  title="Tidak ada sesi offline"
                  description={q ? "Tidak ada sesi offline yang cocok." : "Semua klien sedang aktif online terhubung."}
                />
              </div>
            ) : viewMode === "grid" ? (
              <div className="p-4 sm:p-5 grid gap-3.5 sm:grid-cols-2 lg:grid-cols-3">
                {displayedInactive.map((u, idx) => (
                  <div
                    key={idx}
                    className="relative rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03] p-4 sm:p-5 shadow-xs transition-all hover:border-brand-500/40 dark:hover:border-brand-500/40 space-y-3.5 select-none"
                  >
                    <div className="flex items-start justify-between gap-2.5">
                      <div className="flex items-center gap-3 min-w-0 flex-1 overflow-hidden">
                        <div className="relative flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-rose-50 text-rose-600 dark:bg-rose-500/10 dark:text-rose-400 font-bold text-xs">
                          <WifiOff className="h-5 w-5" />
                        </div>

                        <div className="min-w-0 flex-1">
                          <h4 className="font-bold text-sm text-gray-900 dark:text-white truncate">
                            {u.name}
                          </h4>
                          <p className="mt-0.5 text-xs text-gray-500 dark:text-gray-400 font-medium truncate">
                            {u.customer_name || u.phone || "Pelanggan Offline"}
                          </p>
                        </div>
                      </div>

                      <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold bg-rose-500 text-white shadow-xs shrink-0 whitespace-nowrap">
                        Offline
                      </span>
                    </div>

                    <div className="rounded-xl border border-gray-100 bg-gray-50/80 dark:border-gray-800 dark:bg-gray-900/50 p-3 flex items-center justify-between text-xs">
                      <div>
                        <span className="text-[10px] text-gray-500 dark:text-gray-400 block mb-0.5">Profil Paket</span>
                        <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold bg-brand-500 text-white shadow-xs whitespace-nowrap">
                          {u.profile || "default"}
                        </span>
                      </div>
                      <div className="text-right">
                        <span className="text-[10px] text-gray-500 dark:text-gray-400 block mb-0.5">Terakhir Logout</span>
                        <span className="font-mono text-gray-700 dark:text-gray-300 text-[11px]">
                          {u.last_logged_out || "-"}
                        </span>
                      </div>
                    </div>

                    <div className="pt-2 border-t border-gray-100 dark:border-gray-800 flex items-center justify-between">
                      <span className="text-[11px] text-gray-500 dark:text-gray-400 font-mono">
                        {u.router_name || "MikroTik"}
                      </span>

                      <button
                        type="button"
                        onClick={() => setManagingPppoe(u)}
                        className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200 font-bold text-xs transition cursor-pointer"
                        title="Kelola Akun"
                      >
                        <Settings className="h-3.5 w-3.5" />
                        <span>Kelola</span>
                      </button>
                    </div>
                  </div>
                ))}
              </div>
            ) : (
              <div className="w-full overflow-x-auto custom-scrollbar">
                <table className="w-full min-w-[1000px] text-left text-xs text-gray-700 dark:text-gray-200">
                  <thead className="border-b border-gray-200 bg-gray-50/75 dark:border-gray-800 dark:bg-gray-800/40 text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                    <tr>
                      <th className="py-3 px-4 sm:px-6">Status Sesi</th>
                      <th className="py-3 px-4">Username PPPoE</th>
                      <th className="py-3 px-4">Pelanggan Terikat</th>
                      <th className="py-3 px-4">Profil Paket</th>
                      <th className="py-3 px-4">Terakhir Logout</th>
                      <th className="py-3 px-4">Router Gateway</th>
                      <th className="py-3 px-4 text-end sm:px-6">Aksi</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-gray-100 dark:divide-gray-800">
                    {displayedInactive.map((u, idx) => (
                      <tr key={idx} className="hover:bg-gray-50/50 dark:hover:bg-white/[0.02] transition-colors">
                        <td className="py-3.5 px-4 sm:px-6">
                          <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold bg-rose-500 text-white shadow-xs whitespace-nowrap">
                            Offline
                          </span>
                        </td>
                        <td className="py-3.5 px-4 font-bold text-gray-900 dark:text-white font-mono">
                          {u.name}
                        </td>
                        <td className="py-3.5 px-4 text-xs font-medium text-gray-600 dark:text-gray-300">
                          {u.customer_name || u.phone || <span className="text-gray-400 italic">Umum / Tidak terikat</span>}
                        </td>
                        <td className="py-3.5 px-4">
                          <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold bg-brand-500 text-white shadow-xs whitespace-nowrap">
                            {u.profile || "default"}
                          </span>
                        </td>
                        <td className="py-3.5 px-4 font-mono text-[11px] text-gray-600 dark:text-gray-400">
                          {u.last_logged_out || "-"}
                        </td>
                        <td className="py-3.5 px-4 text-gray-500 dark:text-gray-400 text-xs font-mono">
                          {u.router_name || "Router"}
                        </td>
                        <td className="py-3.5 px-4 sm:px-6 text-end">
                          <button
                            type="button"
                            onClick={() => setManagingPppoe(u)}
                            className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200 font-bold text-xs transition cursor-pointer"
                            title="Kelola Akun"
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
            )
          )}

          {tab === "users" && (
            displayedUsers.length === 0 ? (
              <div className="p-8 text-center">
                <EmptyState
                  icon={<KeyRound className="h-8 w-8 text-brand-500" />}
                  title="Tidak ada secret PPPoE"
                  description={q ? "Tidak ada secret yang cocok." : "Belum ada secret yang tersimpan di database router."}
                />
              </div>
            ) : viewMode === "grid" ? (
              <div className="p-4 sm:p-5 grid gap-3.5 sm:grid-cols-2 lg:grid-cols-3">
                {displayedUsers.map((u, idx) => {
                  const isOnline = active.some((a) => a.name === u.name)
                  return (
                    <div
                      key={idx}
                      className="relative rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03] p-4 sm:p-5 shadow-xs transition-all hover:border-brand-500/40 dark:hover:border-brand-500/40 space-y-3.5 select-none"
                    >
                      <div className="flex items-start justify-between gap-2.5">
                        <div className="flex items-center gap-3 min-w-0 flex-1 overflow-hidden">
                          <div className="relative flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-400 font-bold text-xs">
                            <KeyRound className="h-5 w-5" />
                            {isOnline && (
                              <span className="absolute -bottom-0.5 -right-0.5 h-2.5 w-2.5 rounded-full bg-emerald-500 ring-2 ring-white dark:ring-gray-900 animate-pulse whitespace-nowrap" />
                            )}
                          </div>

                          <div className="min-w-0 flex-1">
                            <h4 className="font-bold text-sm text-gray-900 dark:text-white truncate">
                              {u.name}
                            </h4>
                            <p className="mt-0.5 text-xs text-gray-500 dark:text-gray-400 font-medium truncate">
                              {u.customer_name || u.phone || "Secret PPPoE"}
                            </p>
                          </div>
                        </div>

                        {u.disabled ? (
                          <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold bg-rose-500 text-white shadow-xs shrink-0 whitespace-nowrap">
                            Isolir
                          </span>
                        ) : isOnline ? (
                          <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold bg-emerald-500 text-white shadow-xs shrink-0 whitespace-nowrap">
                            Online
                          </span>
                        ) : (
                          <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold bg-gray-500 text-white shadow-xs shrink-0 whitespace-nowrap">
                            Offline
                          </span>
                        )}
                      </div>

                      <div className="rounded-xl border border-gray-100 bg-gray-50/80 dark:border-gray-800 dark:bg-gray-900/50 p-3 flex items-center justify-between text-xs">
                        <div>
                          <span className="text-[10px] text-gray-500 dark:text-gray-400 block mb-0.5">Profil Paket</span>
                          <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold bg-brand-500 text-white shadow-xs whitespace-nowrap">
                            {u.profile || "default"}
                          </span>
                        </div>
                        <div className="text-right">
                          <span className="text-[10px] text-gray-500 dark:text-gray-400 block mb-0.5">Password</span>
                          <span className="font-mono text-gray-700 dark:text-gray-300 text-[11px]">
                            {u.password ? "••••••" : "-"}
                          </span>
                        </div>
                      </div>

                      <div className="pt-2 border-t border-gray-100 dark:border-gray-800 flex items-center justify-between">
                        <span className="text-[11px] text-gray-500 dark:text-gray-400 font-mono">
                          {u.router_name || "MikroTik"}
                        </span>

                        <button
                          type="button"
                          onClick={() => setManagingPppoe(u)}
                          className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200 font-bold text-xs transition cursor-pointer"
                          title="Kelola Secret"
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
                <table className="w-full min-w-[1000px] text-left text-xs text-gray-700 dark:text-gray-200">
                  <thead className="border-b border-gray-200 bg-gray-50/75 dark:border-gray-800 dark:bg-gray-800/40 text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                    <tr>
                      <th className="py-3 px-4 sm:px-6">Status Akun</th>
                      <th className="py-3 px-4">Username PPPoE</th>
                      <th className="py-3 px-4">Pelanggan Terikat</th>
                      <th className="py-3 px-4">Profil Paket</th>
                      <th className="py-3 px-4">Router Gateway</th>
                      <th className="py-3 px-4 text-end sm:px-6">Aksi</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-gray-100 dark:divide-gray-800">
                    {displayedUsers.map((u, idx) => {
                      const isOnline = active.some((a) => a.name === u.name)
                      return (
                        <tr key={idx} className="hover:bg-gray-50/50 dark:hover:bg-white/[0.02] transition-colors">
                          <td className="py-3.5 px-4 sm:px-6">
                            {u.disabled ? (
                              <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold bg-rose-500 text-white shadow-xs whitespace-nowrap">
                                Isolir
                              </span>
                            ) : isOnline ? (
                              <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold bg-emerald-500 text-white shadow-xs whitespace-nowrap">
                                Online
                              </span>
                            ) : (
                              <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold bg-gray-500 text-white shadow-xs whitespace-nowrap">
                                Offline
                              </span>
                            )}
                          </td>
                          <td className="py-3.5 px-4 font-bold text-gray-900 dark:text-white font-mono">
                            {u.name}
                          </td>
                          <td className="py-3.5 px-4 text-gray-600 dark:text-gray-300 text-xs">
                            {u.customer_name ? (
                              <span className="font-semibold text-gray-900 dark:text-white">{u.customer_name}</span>
                            ) : (
                              <span className="text-gray-400 italic">Belum terikat</span>
                            )}
                          </td>
                          <td className="py-3.5 px-4">
                            <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold bg-brand-500 text-white shadow-xs whitespace-nowrap">
                              {u.profile || "default"}
                            </span>
                          </td>
                          <td className="py-3.5 px-4 text-gray-500 dark:text-gray-400 text-xs font-mono">
                            {u.router_name || "Router"}
                          </td>
                          <td className="py-3.5 px-4 sm:px-6 text-end">
                            <button
                              type="button"
                              onClick={() => setManagingPppoe(u)}
                              className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200 font-bold text-xs transition cursor-pointer"
                              title="Kelola Secret"
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
            )
          )}
        </div>
      </div>

      {/* =========================================================================
          MODAL INTERAKTIF: KELOLA SESI & SECRET PPPOE (1:1 Superadmin Standard)
          ========================================================================= */}
      {managingPppoe && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-xs">
          <div className="w-full max-w-md rounded-2xl border border-gray-200 bg-white p-5 shadow-2xl dark:border-gray-800 dark:bg-gray-900 space-y-4 max-h-[92vh] overflow-y-auto">
            {/* Modal Header */}
            <div className="flex items-center justify-between border-b border-gray-100 pb-3 dark:border-gray-800">
              <div className="flex items-center gap-3">
                <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-brand-500 text-white shadow-xs">
                  <KeyRound className="h-5 w-5" />
                </div>
                <div>
                  <h3 className="font-bold text-sm text-gray-900 dark:text-white flex items-center gap-2">
                    <span>{managingPppoe.name}</span>
                    {managingPppoe.address ? (
                      <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold bg-emerald-500 text-white shadow-xs whitespace-nowrap">
                        Online
                      </span>
                    ) : managingPppoe.disabled ? (
                      <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold bg-rose-500 text-white shadow-xs whitespace-nowrap">
                        Isolir
                      </span>
                    ) : (
                      <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold bg-gray-500 text-white shadow-xs whitespace-nowrap">
                        Offline
                      </span>
                    )}
                  </h3>
                  <span className="text-[11px] text-gray-500 font-mono">
                    {managingPppoe.router_name || "MikroTik Core Gateway"}
                  </span>
                </div>
              </div>
              <button
                type="button"
                onClick={() => setManagingPppoe(null)}
                className="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-800 dark:hover:text-gray-200 transition cursor-pointer"
              >
                <X className="h-5 w-5" />
              </button>
            </div>

            {/* Quick Summary Box */}
            <div className="rounded-xl border border-gray-200/80 bg-gray-50/70 p-3.5 dark:border-gray-800 dark:bg-gray-800/40 space-y-2 text-xs">
              {managingPppoe.customer_name && (
                <div className="flex items-center justify-between">
                  <span className="text-gray-500 dark:text-gray-400">Nama Pelanggan</span>
                  <span className="font-semibold text-gray-900 dark:text-white">
                    {managingPppoe.customer_name}
                  </span>
                </div>
              )}
              {managingPppoe.phone && (
                <div className="flex items-center justify-between">
                  <span className="text-gray-500 dark:text-gray-400">Nomor WhatsApp</span>
                  <span className="font-mono font-semibold text-brand-600 dark:text-brand-400">
                    {managingPppoe.phone}
                  </span>
                </div>
              )}
              {managingPppoe.address && (
                <div className="flex items-center justify-between">
                  <span className="text-gray-500 dark:text-gray-400">IP Remote PPPoE</span>
                  <span className="font-mono font-bold text-emerald-600 dark:text-emerald-400">
                    {managingPppoe.address}
                  </span>
                </div>
              )}
              {managingPppoe.profile && (
                <div className="flex items-center justify-between">
                  <span className="text-gray-500 dark:text-gray-400">Profil Paket</span>
                  <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold bg-brand-500 text-white shadow-xs whitespace-nowrap">
                    {managingPppoe.profile}
                  </span>
                </div>
              )}
              {managingPppoe.uptime && (
                <div className="flex items-center justify-between">
                  <span className="text-gray-500 dark:text-gray-400">Durasi Online</span>
                  <span className="font-mono font-semibold text-gray-900 dark:text-white">
                    {formatUptime(managingPppoe.uptime)}
                  </span>
                </div>
              )}
              {managingPppoe.last_logged_out && (
                <div className="flex items-center justify-between">
                  <span className="text-gray-500 dark:text-gray-400">Terakhir Logout</span>
                  <span className="font-mono text-gray-700 dark:text-gray-300">
                    {managingPppoe.last_logged_out}
                  </span>
                </div>
              )}
              {managingPppoe.onu_rx_power != null && (
                <div className="flex items-center justify-between">
                  <span className="text-gray-500 dark:text-gray-400">Sinyal Optik (RX Power)</span>
                  <span className={cn("inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold", getPowerMeta(managingPppoe.onu_rx_power).badgeCls)}>
                    {managingPppoe.onu_rx_power} dBm ({getPowerMeta(managingPppoe.onu_rx_power).label})
                  </span>
                </div>
              )}
            </div>

            {/* Action Buttons List */}
            <div className="space-y-2 pt-1 text-xs">
              {/* Putuskan Sesi (Kick) */}
              {managingPppoe.address && (
                <button
                  type="button"
                  onClick={() => handleKick(managingPppoe.name, managingPppoe.router_id)}
                  className="w-full group flex items-center justify-between p-3 rounded-xl bg-gray-50/70 hover:bg-amber-50/50 border border-gray-100 hover:border-amber-200 dark:bg-gray-800/40 dark:hover:bg-gray-800 dark:border-gray-800 dark:hover:border-amber-900/60 transition cursor-pointer"
                >
                  <div className="flex items-center gap-2.5">
                    <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-amber-500 text-white shadow-2xs whitespace-nowrap">
                      <Power className="h-4 w-4" />
                    </div>
                    <div className="text-left">
                      <div className="font-bold text-amber-600 dark:text-amber-400">Putuskan Sesi (Kick)</div>
                      <div className="text-[11px] text-gray-500 dark:text-gray-400">Disconnect sesi realtime &amp; paksa re-autentikasi</div>
                    </div>
                  </div>
                  <Power className="h-4 w-4 text-amber-500 group-hover:scale-110 transition-transform" />
                </button>
              )}

              {/* Chat WhatsApp Pelanggan */}
              {managingPppoe.phone && (
                <a
                  href={`https://wa.me/${managingPppoe.phone.replace(/[^0-9]/g, "")}`}
                  target="_blank"
                  rel="noreferrer"
                  className="w-full group flex items-center justify-between p-3 rounded-xl bg-gray-50/70 hover:bg-emerald-50/50 border border-gray-100 hover:border-emerald-200 dark:bg-gray-800/40 dark:hover:bg-gray-800 dark:border-gray-800 dark:hover:border-emerald-900/60 transition cursor-pointer"
                >
                  <div className="flex items-center gap-2.5">
                    <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-500 text-white shadow-2xs whitespace-nowrap">
                      <MessageCircle className="h-4 w-4" />
                    </div>
                    <div className="text-left">
                      <div className="font-bold text-emerald-600 dark:text-emerald-400">Chat WhatsApp</div>
                      <div className="text-[11px] text-gray-500 dark:text-gray-400">Hubungi pelanggan {managingPppoe.phone}</div>
                    </div>
                  </div>
                  <ExternalLink className="h-4 w-4 text-emerald-500 group-hover:scale-110 transition-transform" />
                </a>
              )}

              {/* Hapus Akun Secret */}
              <button
                type="button"
                onClick={() => {
                  setDeleteConfirmUser(managingPppoe)
                  setManagingPppoe(null)
                }}
                className="w-full group flex items-center justify-between p-3 rounded-xl bg-gray-50/70 hover:bg-rose-50/50 border border-gray-100 hover:border-rose-200 dark:bg-gray-800/40 dark:hover:bg-gray-800 dark:border-gray-800 dark:hover:border-rose-900/60 transition cursor-pointer"
              >
                <div className="flex items-center gap-2.5">
                  <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-rose-500 text-white shadow-2xs whitespace-nowrap">
                    <Trash2 className="h-4 w-4" />
                  </div>
                  <div className="text-left">
                    <div className="font-bold text-rose-600 dark:text-rose-400">Hapus Secret PPPoE</div>
                    <div className="text-[11px] text-gray-500 dark:text-gray-400">Hapus secret akun dari MikroTik RouterOS</div>
                  </div>
                </div>
                <Trash2 className="h-4 w-4 text-rose-400 group-hover:text-rose-500 transition-colors" />
              </button>
            </div>
          </div>
        </div>
      )}

      {/* MODAL KONFIRMASI HAPUS SECRET */}
      {deleteConfirmUser && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-xs">
          <div className="w-full max-w-md rounded-2xl border border-gray-200 bg-white p-5 shadow-2xl dark:border-gray-800 dark:bg-gray-900 space-y-4">
            <h3 className="font-bold text-sm text-gray-900 dark:text-white">
              Hapus Secret PPPoE: {deleteConfirmUser.name}
            </h3>
            <p className="text-xs text-gray-600 dark:text-gray-300 leading-relaxed">
              Apakah Anda yakin ingin menghapus akun secret PPPoE <strong>{deleteConfirmUser.name}</strong> dari router? Klien tidak akan dapat terhubung kembali.
            </p>
            <div className="flex items-center justify-end gap-2 pt-3 border-t border-gray-100 dark:border-gray-800">
              <button
                type="button"
                onClick={() => setDeleteConfirmUser(null)}
                className="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:hover:bg-gray-800 text-xs font-semibold text-gray-700 dark:text-gray-200 transition shadow-2xs cursor-pointer"
              >
                Batal
              </button>
              <button
                type="button"
                onClick={() => handleDeleteSecret(deleteConfirmUser.name, deleteConfirmUser.router_id)}
                className="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-rose-500 hover:bg-rose-600 active:scale-95 text-xs font-bold text-white shadow-xs transition cursor-pointer"
              >
                Ya, Hapus Secret
              </button>
            </div>
          </div>
        </div>
      )}

      {isAutoSyncing && <SyncOverlay text="Menyinkronkan data PPPoE realtime..." />}
    </AppLayout>
  )
}
