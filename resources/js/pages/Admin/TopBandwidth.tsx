import { useState, useEffect, useMemo } from "react"
import Chart from "react-apexcharts"
import { AppLayout } from "@/components/layout/app-layout"
import {
  Cpu,
  Wifi,
  RefreshCw,
  ArrowDown,
  ArrowUp,
  AlertTriangle,
  Search,
  Activity,
  Gauge,
  Server,
  Users,
  Clock,
  ChevronDown,
  Crown,
  Network,
  Download,
  Upload,
  Settings,
  X,
  Copy,
  Check,
} from "lucide-react"
import { PageProps } from "@/types"
import { cn, formatUptime } from "@/lib/utils"
import { adminSidebarItems, adminNavItems, adminBrand } from "@/lib/admin-nav"
import { collectorNavItems, collectorSidebarItems, collectorBrand } from "@/lib/collector-nav"
import { ViewModeSwitcher } from "@/components/ui/view-mode-switcher"
import { EmptyState } from "@/components/ui/empty-state"
import { motion, AnimatePresence } from "framer-motion"

interface TopUser {
  name: string
  address: string
  ip?: string
  mac?: string
  uptime: string
  is_online?: boolean
  package?: string
  tx: string
  rx: string
  total: string
  total_bytes: number
}

interface BandwidthResponse {
  success: boolean
  type: string
  timeframe?: string
  period_label?: string
  data: TopUser[]
  grand_total?: string
  message?: string
  hotspot_not_setup?: boolean
}

export default function TopBandwidthPage({
  routers = [],
  mode,
}: PageProps<{ routers: { id: number; name: string; host?: string }[]; mode?: "admin" | "collector" }>) {
  const isCollector = mode === "collector"
  const [routerId, setRouterId] = useState<number | null>(routers[0]?.id ?? null)
  const [type, setType] = useState<"pppoe" | "hotspot" | "arp">("pppoe")
  const [data, setData] = useState<BandwidthResponse | null>(null)
  const [loading, setLoading] = useState(false)
  const [autoRefresh, setAutoRefresh] = useState(true)
  const [search, setSearch] = useState("")
  const [expandedKey, setExpandedKey] = useState<string | null>(null)
  const [viewMode, setViewMode] = useState<"table" | "grid">("grid")
  const [countdown, setCountdown] = useState(15)
  const [managingUser, setManagingUser] = useState<{ user: TopUser; rank: number; pct: number } | null>(null)
  const [copied, setCopied] = useState(false)

  const load = async () => {
    if (!routerId) return
    setLoading(true)
    try {
      const base = isCollector ? "/kolektor/top-bandwidth/data" : "/admin/top-bandwidth/data"
      const res = await fetch(`${base}?router_id=${routerId}&type=${type}`)
      const json: BandwidthResponse = await res.json()
      setData(json)
    } catch {
      setData({ success: false, type, data: [], message: "Gagal mengambil data dari MikroTik / Database" })
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    load()
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [routerId, type])

  // Auto refresh countdown 15 detik
  useEffect(() => {
    if (!autoRefresh || !routerId) return
    const t = setInterval(() => {
      setCountdown((prev) => {
        if (prev <= 1) {
          load()
          return 15
        }
        return prev - 1
      })
    }, 1000)
    return () => clearInterval(t)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [routerId, type, autoRefresh])

  const maxBytes = Math.max(1, ...(data?.data ?? []).map((u) => u.total_bytes))

  const filtered = useMemo(() => {
    return (data?.data ?? []).filter((u) => {
      const q = search.toLowerCase().trim()
      if (!q) return true
      return (
        u.name.toLowerCase().includes(q) ||
        (u.ip && u.ip.toLowerCase().includes(q)) ||
        (u.mac && u.mac.toLowerCase().includes(q)) ||
        (u.address && u.address.toLowerCase().includes(q)) ||
        (u.package && u.package.toLowerCase().includes(q))
      )
    })
  }, [data, search])

  // Stats calculation
  const stats = useMemo(() => {
    const list = data?.data ?? []
    const totalUsers = list.length
    const onlineUsers = list.filter((u) => u.is_online).length
    const topUsage = list[0]?.total || "0 B"
    const grandTotal = data?.grand_total || "0 B"

    return { totalUsers, onlineUsers, topUsage, grandTotal }
  }, [data])

  const leaderboardChartData = useMemo(() => {
    const list = (data?.data ?? []).slice(0, 7)
    const categories = list.map((u) => (u.name.length > 14 ? u.name.slice(0, 14) + "..." : u.name))

    const parseSizeInMB = (str?: string) => {
      if (!str) return 0
      const clean = str.trim().toUpperCase()
      const val = parseFloat(clean) || 0
      if (clean.includes("GB") || clean.includes("GIB") || clean.includes("G")) return +(val * 1024).toFixed(1)
      if (clean.includes("TB") || clean.includes("TIB") || clean.includes("T")) return +(val * 1024 * 1024).toFixed(1)
      if (clean.includes("KB") || clean.includes("KIB") || clean.includes("K")) return +(val / 1024).toFixed(2)
      return +val.toFixed(1)
    }

    const rxSeries = list.map((u) => parseSizeInMB(u.rx))
    const txSeries = list.map((u) => parseSizeInMB(u.tx))

    return {
      categories: categories.length > 0 ? categories : ["Tidak Ada Data"],
      rxSeries: rxSeries.length > 0 ? rxSeries : [0],
      txSeries: txSeries.length > 0 ? txSeries : [0],
    }
  }, [data])

  const barChartOptions = useMemo(() => ({
    chart: {
      type: "bar" as const,
      fontFamily: "Outfit, Inter, sans-serif",
      toolbar: { show: false },
      stacked: true,
    },
    plotOptions: {
      bar: {
        horizontal: true,
        borderRadius: 4,
        barHeight: "55%",
      },
    },
    colors: ["#0073C6", "#10B981"],
    dataLabels: { enabled: false },
    xaxis: {
      categories: leaderboardChartData.categories,
      labels: {
        style: { colors: "#64748B", fontSize: "10px" },
        formatter: (val: number) => (val >= 1024 ? `${(val / 1024).toFixed(1)} GB` : `${val} MB`),
      },
      axisBorder: { show: false },
      axisTicks: { show: false },
    },
    yaxis: {
      labels: {
        style: { colors: "#64748B", fontSize: "11px", fontWeight: 600 },
      },
    },
    grid: {
      borderColor: "#F1F5F9",
      strokeDashArray: 3,
      xaxis: { lines: { show: true } },
      yaxis: { lines: { show: false } },
    },
    legend: {
      position: "top" as const,
      horizontalAlign: "right" as const,
      fontSize: "11px",
      fontWeight: 600,
      labels: { colors: "#64748B" },
    },
    tooltip: {
      theme: "light",
      y: {
        formatter: (val: number) => (val >= 1024 ? `${(val / 1024).toFixed(2)} GB` : `${val} MB`),
      },
    },
  }), [leaderboardChartData])

  const trafficSplit = useMemo(() => {
    const list = data?.data ?? []
    let totalRxMB = 0
    let totalTxMB = 0
    const parseSizeInMB = (str?: string) => {
      if (!str) return 0
      const clean = str.trim().toUpperCase()
      const val = parseFloat(clean) || 0
      if (clean.includes("GB") || clean.includes("GIB") || clean.includes("G")) return val * 1024
      if (clean.includes("TB") || clean.includes("TIB") || clean.includes("T")) return val * 1024 * 1024
      if (clean.includes("KB") || clean.includes("KIB") || clean.includes("K")) return val / 1024
      return val
    }
    list.forEach((u) => {
      totalRxMB += parseSizeInMB(u.rx)
      totalTxMB += parseSizeInMB(u.tx)
    })
    const sum = totalRxMB + totalTxMB
    return {
      rxMB: totalRxMB,
      txMB: totalTxMB,
      rxPercent: sum > 0 ? Math.round((totalRxMB / sum) * 100) : 75,
      txPercent: sum > 0 ? Math.round((totalTxMB / sum) * 100) : 25,
    }
  }, [data])

  const donutOptions = useMemo(() => ({
    chart: { type: "donut" as const, fontFamily: "Outfit, Inter, sans-serif" },
    labels: ["Download (RX)", "Upload (TX)"],
    colors: ["#0073C6", "#10B981"],
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
              label: "Total Bandwidth",
              fontSize: "11px",
              fontWeight: 600,
              color: "#64748B",
              formatter: () => stats.grandTotal,
            },
            value: { fontSize: "15px", fontWeight: 700, color: "#0F172A" },
          },
        },
      },
    },
    tooltip: {
      theme: "light",
      y: { formatter: (val: number) => `${val}%` },
    },
  }), [stats.grandTotal])

  const handleCopy = (text: string) => {
    navigator.clipboard.writeText(text)
    setCopied(true)
    setTimeout(() => setCopied(false), 2000)
  }

  return (
    <AppLayout
      title="Top Bandwidth"
      brand={isCollector ? collectorBrand : adminBrand}
      sidebarItems={isCollector ? collectorSidebarItems : adminSidebarItems}
      navItems={isCollector ? collectorNavItems : adminNavItems}
    >
      <div className="space-y-4 sm:space-y-6">
        {/* ── Executive Multi-Type Visual Analytics ── */}
        <div className="grid grid-cols-1 lg:grid-cols-12 gap-4 sm:gap-5">
          {/* Card 1: Horizontal Bar Leaderboard Top 7 Users (7 Cols) */}
          <div className="lg:col-span-7 rounded-2xl border border-gray-200 bg-white p-4 sm:p-5 shadow-xs dark:border-gray-800 dark:bg-white/[0.03] flex flex-col justify-between">
            <div className="flex items-center justify-between border-b border-gray-100 dark:border-gray-800/80 pb-3">
              <div>
                <h3 className="text-sm font-bold text-gray-900 dark:text-white flex items-center gap-2">
                  <Crown className="h-4 w-4 text-amber-500" />
                  <span>Top 7 Pengguna Bandwidth Tertinggi</span>
                </h3>
                <p className="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                  Download (RX) &amp; Upload (TX) pengguna puncak
                </p>
              </div>
              <span className="text-xs font-bold text-gray-600 dark:text-gray-300">
                Puncak: {stats.topUsage}
              </span>
            </div>

            <div className="pt-2">
              <Chart
                options={barChartOptions as any}
                series={[
                  { name: "Download (RX)", data: leaderboardChartData.rxSeries },
                  { name: "Upload (TX)", data: leaderboardChartData.txSeries },
                ]}
                type="bar"
                height={200}
                width="100%"
              />
            </div>
          </div>

          {/* Card 2: Komposisi Volume Traffic & Sesi (5 Cols) */}
          <div className="lg:col-span-5 rounded-2xl border border-gray-200 bg-white p-4 sm:p-5 shadow-xs dark:border-gray-800 dark:bg-white/[0.03] flex flex-col justify-between">
            <div className="flex items-center justify-between border-b border-gray-100 dark:border-gray-800/80 pb-3">
              <div>
                <h3 className="text-sm font-bold text-gray-900 dark:text-white flex items-center gap-2">
                  <Activity className="h-4 w-4 text-blue-500" />
                  <span>Distribusi Rasio RX &amp; TX</span>
                </h3>
                <p className="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                  Akumulasi total traffic jaringan
                </p>
              </div>
              <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-bold bg-blue-50 text-blue-700 dark:bg-blue-500/10 dark:text-blue-400">
                {stats.totalUsers} Pelanggan
              </span>
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-12 gap-3 items-center py-2">
              <div className="sm:col-span-6 flex justify-center">
                <Chart
                  options={donutOptions as any}
                  series={[trafficSplit.rxPercent, trafficSplit.txPercent]}
                  type="donut"
                  height={160}
                  width="100%"
                />
              </div>
              <div className="sm:col-span-6 space-y-2">
                <div className="flex items-center justify-between rounded-xl bg-gray-50/80 p-2 dark:bg-gray-900/50 border border-gray-100 dark:border-gray-800/60">
                  <span className="text-xs text-gray-500 dark:text-gray-400 flex items-center gap-1.5">
                    <Download className="h-3.5 w-3.5 text-blue-500" /> Download RX
                  </span>
                  <span className="font-bold text-gray-900 dark:text-white text-xs">{trafficSplit.rxPercent}%</span>
                </div>
                <div className="flex items-center justify-between rounded-xl bg-gray-50/80 p-2 dark:bg-gray-900/50 border border-gray-100 dark:border-gray-800/60">
                  <span className="text-xs text-gray-500 dark:text-gray-400 flex items-center gap-1.5">
                    <Upload className="h-3.5 w-3.5 text-emerald-500" /> Upload TX
                  </span>
                  <span className="font-bold text-gray-900 dark:text-white text-xs">{trafficSplit.txPercent}%</span>
                </div>
                <div className="flex items-center justify-between rounded-xl bg-gray-50/80 p-2 dark:bg-gray-900/50 border border-gray-100 dark:border-gray-800/60">
                  <span className="text-xs text-gray-500 dark:text-gray-400 flex items-center gap-1.5">
                    <Wifi className="h-3.5 w-3.5 text-emerald-500" /> Sesi Online
                  </span>
                  <span className="font-bold text-emerald-600 dark:text-emerald-400 text-xs">{stats.onlineUsers}</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        {/* Master Card Table / List Stream */}
        <div className="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xs dark:border-gray-800 dark:bg-white/[0.03]">
          {/* 1-Line Header Controls Toolbar */}
          <div className="flex flex-col gap-3 p-4 sm:p-5 border-b border-gray-100 dark:border-gray-800/80">
            <div className="flex flex-wrap items-center justify-between gap-3">
              {/* Left Controls: Router Selector & Type Switcher */}
              <div className="flex flex-wrap items-center gap-2.5 sm:gap-3 flex-1 min-w-[280px]">
                {/* Router Selector */}
                <div className="relative flex items-center min-w-[180px] sm:min-w-[220px]">
                  <Server className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400 pointer-events-none" />
                  <select
                    value={routerId ?? ""}
                    onChange={(e) => setRouterId(Number(e.target.value))}
                    className="w-full h-9 rounded-xl border border-gray-200 bg-gray-50/50 py-1.5 pl-9 pr-8 text-xs font-semibold text-gray-800 outline-none transition focus:border-brand-500 focus:bg-white focus:ring-2 focus:ring-brand-500/10 dark:border-gray-800 dark:bg-gray-900/50 dark:text-white/90"
                  >
                    {routers.map((r) => (
                      <option key={r.id} value={r.id}>
                        {r.name}
                      </option>
                    ))}
                  </select>
                </div>

                {/* Service Type Switcher Tabs */}
                <div className="inline-flex rounded-xl border border-gray-200 bg-gray-50/80 p-1 dark:border-gray-800 dark:bg-gray-900/80">
                  <button
                    type="button"
                    onClick={() => setType("pppoe")}
                    className={cn(
                      "flex items-center gap-1.5 rounded-lg px-2.5 py-1 text-xs font-semibold transition-all cursor-pointer",
                      type === "pppoe"
                        ? "bg-white text-brand-600 shadow-xs dark:bg-gray-800 dark:text-white"
                        : "text-gray-500 hover:text-gray-800 dark:text-gray-400 dark:hover:text-white"
                    )}
                  >
                    <Cpu className="h-3.5 w-3.5" />
                    <span>PPPoE</span>
                  </button>
                  <button
                    type="button"
                    onClick={() => setType("hotspot")}
                    className={cn(
                      "flex items-center gap-1.5 rounded-lg px-2.5 py-1 text-xs font-semibold transition-all cursor-pointer",
                      type === "hotspot"
                        ? "bg-white text-brand-600 shadow-xs dark:bg-gray-800 dark:text-white"
                        : "text-gray-500 hover:text-gray-800 dark:text-gray-400 dark:hover:text-white"
                    )}
                  >
                    <Wifi className="h-3.5 w-3.5" />
                    <span>Hotspot</span>
                  </button>
                  <button
                    type="button"
                    onClick={() => setType("arp")}
                    className={cn(
                      "flex items-center gap-1.5 rounded-lg px-2.5 py-1 text-xs font-semibold transition-all cursor-pointer",
                      type === "arp"
                        ? "bg-white text-brand-600 shadow-xs dark:bg-gray-800 dark:text-white"
                        : "text-gray-500 hover:text-gray-800 dark:text-gray-400 dark:hover:text-white"
                    )}
                  >
                    <Network className="h-3.5 w-3.5" />
                    <span>Static ARP</span>
                  </button>
                </div>
              </div>

              {/* Right Controls: Auto-Refresh & ViewMode */}
              <div className="flex items-center gap-2">
                <ViewModeSwitcher value={viewMode} onChange={setViewMode} />

                {/* Refresh + Auto-Refresh */}
                <div className="inline-flex items-center rounded-xl border border-gray-200 bg-gray-50/50 p-0.5 dark:border-gray-800 dark:bg-gray-900/50">
                  <button
                    type="button"
                    onClick={load}
                    disabled={loading}
                    className="inline-flex h-8 items-center gap-1.5 rounded-lg bg-brand-500 hover:bg-brand-600 active:scale-95 px-2.5 text-xs font-bold text-white shadow-xs transition disabled:opacity-50 cursor-pointer whitespace-nowrap"
                    title="Refresh Data Sekarang"
                  >
                    <RefreshCw className={cn("h-3.5 w-3.5", loading && "animate-spin")} />
                    <span className="hidden sm:inline">Refresh</span>
                  </button>

                  <button
                    type="button"
                    onClick={() => setAutoRefresh(!autoRefresh)}
                    className={cn(
                      "inline-flex h-8 items-center gap-1.5 rounded-lg px-2.5 text-xs font-semibold transition-all ml-0.5 cursor-pointer",
                      autoRefresh
                        ? "bg-emerald-500 text-white font-bold shadow-xs"
                        : "text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
                    )}
                    title={autoRefresh ? "Auto Refresh Aktif - Klik untuk Matikan" : "Auto Refresh Nonaktif - Klik untuk Aktifkan"}
                  >
                    <span className={cn("h-2 w-2 rounded-full shrink-0", autoRefresh ? "bg-white animate-pulse" : "bg-gray-400")} />
                    <span className="text-[11px] font-mono">{autoRefresh ? (loading ? "Sync..." : `${countdown}s`) : "Off"}</span>
                  </button>
                </div>
              </div>
            </div>

            {/* Search Input Bar */}
            <div className="relative w-full">
              <Search className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400 dark:text-gray-500" />
              <input
                type="text"
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                placeholder="Cari nama pelanggan, username, paket, IP, atau MAC address..."
                className="w-full h-9 rounded-xl border border-gray-200 bg-gray-50/50 pl-9 pr-4 text-xs sm:text-sm text-gray-800 placeholder-gray-400 outline-none transition focus:border-brand-500 focus:bg-white focus:ring-2 focus:ring-brand-500/10 dark:border-gray-800 dark:bg-gray-900/50 dark:text-white/90 dark:placeholder-gray-500"
              />
            </div>
          </div>

          {/* Body Content */}
          {!routerId ? (
            <div className="p-8 sm:p-12 text-center">
              <EmptyState
                icon={<Server className="h-10 w-10 text-brand-500" />}
                title="Pilih Router MikroTik"
                description="Pilih router gateway di atas untuk memuat data live traffic pemakaian bandwidth."
              />
            </div>
          ) : data && !data.success ? (
            <div className="m-4 sm:m-6 rounded-2xl border border-rose-200 bg-rose-50/50 p-5 dark:border-rose-900/40 dark:bg-rose-950/20">
              <div className="flex items-center gap-2 text-rose-600 dark:text-rose-400 font-bold text-sm">
                <AlertTriangle className="h-4 w-4 shrink-0" />
                <span>Koneksi Router Terkendala</span>
              </div>
              <p className="mt-1 text-xs text-rose-700 dark:text-rose-300 leading-relaxed">
                {data.message ?? "Tidak dapat mengambil data dari router MikroTik."}
              </p>
            </div>
          ) : filtered.length === 0 ? (
            <div className="p-8 sm:p-12 text-center">
              <EmptyState
                icon={<Users className="h-10 w-10 text-brand-500" />}
                title={search ? "Pengguna Tidak Ditemukan" : "Belum Ada Data Pemakaian"}
                description={
                  search
                    ? `Tidak ada pengguna yang cocok dengan kata kunci "${search}".`
                    : "Belum ada catatan traffic pada periode bulan berjalan ini."
                }
              />
            </div>
          ) : viewMode === "table" ? (
            <div className="w-full overflow-x-auto custom-scrollbar">
              <table className="w-full text-left text-xs sm:text-sm min-w-[950px] border-collapse">
                <thead className="border-b border-gray-200 bg-gray-50/75 text-xs font-semibold text-gray-500 uppercase tracking-wider dark:border-gray-800 dark:bg-gray-900/50 dark:text-gray-400">
                  <tr>
                    <th className="px-4 py-3 sm:px-6">Peringkat &amp; Pengguna</th>
                    <th className="px-4 py-3 sm:px-6">Paket / Layanan</th>
                    <th className="px-4 py-3 sm:px-6">IP / MAC Address</th>
                    <th className="px-4 py-3 sm:px-6">Download (TX)</th>
                    <th className="px-4 py-3 sm:px-6">Upload (RX)</th>
                    <th className="px-4 py-3 sm:px-6">Total Traffic</th>
                    <th className="px-4 py-3 sm:px-6">Status Sesi</th>
                    <th className="px-4 py-3 sm:px-6 text-right">Aksi</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-gray-100 dark:divide-gray-800/60">
                  {filtered.map((u, i) => {
                    const pct = Math.max(4, Math.round((u.total_bytes / maxBytes) * 100))
                    const ipClean = u.ip || (u.address?.includes("(") ? u.address.split("(")[0].trim() : u.address) || "-"

                    return (
                      <tr key={i} className="hover:bg-gray-50/50 dark:hover:bg-white/[0.02] transition-colors">
                        <td className="px-4 py-3.5 sm:px-6">
                          <div className="flex items-center gap-3">
                            <span
                              className={cn(
                                "flex h-7 w-7 shrink-0 items-center justify-center rounded-lg text-xs font-bold",
                                i === 0 && "bg-amber-500 text-white shadow-xs font-black",
                                i === 1 && "bg-gray-400 text-white",
                                i === 2 && "bg-amber-700 text-white",
                                i >= 3 && "bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400"
                              )}
                            >
                              {i === 0 ? <Crown className="h-4 w-4" /> : `#${i + 1}`}
                            </span>
                            <div className="min-w-0">
                              <span className="font-semibold text-gray-900 dark:text-white block truncate">
                                {u.name}
                              </span>
                              <div className="mt-0.5 w-24 sm:w-32 h-1.5 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800">
                                <div
                                  className={cn(
                                    "h-full rounded-full",
                                    i === 0 ? "bg-amber-500" : "bg-brand-500"
                                  )}
                                  style={{ width: `${pct}%` }}
                                />
                              </div>
                            </div>
                          </div>
                        </td>
                        <td className="px-4 py-3.5 sm:px-6">
                          {u.package ? (
                            <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold bg-brand-500 text-white shadow-xs whitespace-nowrap">
                              {u.package}
                            </span>
                          ) : (
                            <span className="text-gray-400 dark:text-gray-500">-</span>
                          )}
                        </td>
                        <td className="px-4 py-3.5 sm:px-6 font-mono text-xs text-gray-700 dark:text-gray-300">
                          {ipClean}
                        </td>
                        <td className="px-4 py-3.5 sm:px-6 font-mono font-semibold text-emerald-600 dark:text-emerald-400">
                          <span className="inline-flex items-center gap-1">
                            <ArrowDown className="h-3 w-3" />
                            {u.tx}
                          </span>
                        </td>
                        <td className="px-4 py-3.5 sm:px-6 font-mono font-semibold text-sky-600 dark:text-sky-400">
                          <span className="inline-flex items-center gap-1">
                            <ArrowUp className="h-3 w-3" />
                            {u.rx}
                          </span>
                        </td>
                        <td className="px-4 py-3.5 sm:px-6 font-mono font-bold text-gray-900 dark:text-white">
                          <span className={cn(i === 0 && "text-amber-600 dark:text-amber-400")}>{u.total}</span>
                        </td>
                        <td className="px-4 py-3.5 sm:px-6">
                          <span
                            className={cn(
                              "inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold text-white shadow-xs",
                              u.is_online ? "bg-emerald-500" : "bg-gray-500"
                            )}
                          >
                            {u.is_online ? "Online" : "Offline"}
                          </span>
                        </td>
                        <td className="px-4 py-3.5 sm:px-6 text-right">
                          <button
                            type="button"
                            onClick={() => setManagingUser({ user: u, rank: i + 1, pct })}
                            className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200 font-bold text-xs transition cursor-pointer"
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
          ) : (
            <div className="p-4 sm:p-6 space-y-3">
              {filtered.map((u, i) => {
                const pct = Math.max(4, Math.round((u.total_bytes / maxBytes) * 100))
                const cardKey = `${u.name}-${i}`
                const isExpanded = expandedKey === cardKey

                return (
                  <motion.div
                    key={cardKey}
                    initial={{ opacity: 0, y: 6 }}
                    animate={{ opacity: 1, y: 0 }}
                    transition={{ duration: 0.15, delay: Math.min(i * 0.02, 0.2) }}
                    className={cn(
                      "group relative overflow-hidden rounded-2xl border p-4 transition-all shadow-xs select-none space-y-3",
                      isExpanded
                        ? "border-brand-500/60 bg-brand-50/20 dark:bg-brand-500/5 dark:border-brand-500/50"
                        : i === 0
                        ? "border-amber-400/60 bg-amber-50/30 dark:bg-amber-500/5 dark:border-amber-500/40"
                        : "border-gray-200 bg-white hover:border-gray-300 dark:border-gray-800 dark:bg-white/[0.02] dark:hover:border-gray-700"
                    )}
                  >
                    <div className="flex items-center gap-3">
                      {/* Rank Badge */}
                      <div
                        className={cn(
                          "flex h-10 w-10 shrink-0 flex-col items-center justify-center rounded-xl font-bold transition-all",
                          i === 0 && "bg-amber-500 text-white shadow-xs",
                          i === 1 && "bg-gray-400 text-white",
                          i === 2 && "bg-amber-700 text-white",
                          i >= 3 && "bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300"
                        )}
                      >
                        {i === 0 ? (
                          <div className="flex items-center gap-0.5">
                            <Crown className="h-4 w-4" />
                            <span className="text-[11px] font-black">#1</span>
                          </div>
                        ) : (
                          <span className="text-xs">#{i + 1}</span>
                        )}
                      </div>

                      {/* User Info & Live Meter */}
                      <div className="min-w-0 flex-1 space-y-1.5">
                        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1">
                          <div className="flex items-center gap-2 min-w-0">
                            {u.is_online !== undefined && (
                              <span
                                className={cn(
                                  "h-2 w-2 rounded-full shrink-0",
                                  u.is_online ? "bg-emerald-500 animate-pulse" : "bg-gray-400"
                                )}
                                title={u.is_online ? "Online" : "Offline"}
                              />
                            )}
                            <span className="font-semibold text-sm text-gray-900 dark:text-white truncate max-w-[220px] sm:max-w-md">
                              {u.name}
                            </span>
                            {u.package && (
                              <span className="hidden sm:inline-block rounded-md bg-brand-500 text-white px-2 py-0.5 text-[10px] font-bold shadow-xs truncate max-w-[130px] whitespace-nowrap">
                                {u.package}
                              </span>
                            )}
                          </div>

                          <div className="flex items-center gap-1.5 shrink-0">
                            <span className="text-xs text-gray-500 dark:text-gray-400">Total:</span>
                            <span
                              className={cn(
                                "font-bold text-sm font-mono tabular-nums",
                                i === 0 ? "text-amber-600 dark:text-amber-400 font-black" : "text-gray-900 dark:text-white"
                              )}
                            >
                              {u.total}
                            </span>
                          </div>
                        </div>

                        {/* Progress Bar */}
                        <div className="relative h-2 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800">
                          <motion.div
                            className={cn(
                              "h-full rounded-full transition-all duration-500",
                              i === 0
                                ? "bg-gradient-to-r from-amber-500 to-yellow-400"
                                : "bg-gradient-to-r from-brand-600 to-brand-400"
                            )}
                            style={{ transformOrigin: "left" }}
                            initial={{ scaleX: 0 }}
                            animate={{ scaleX: pct / 100 }}
                            transition={{ duration: 0.5, ease: "easeOut" }}
                          />
                        </div>
                      </div>

                      {/* Kelola Button & Chevron Toggle */}
                      <div className="shrink-0 flex items-center gap-2">
                        <button
                          type="button"
                          onClick={() => setManagingUser({ user: u, rank: i + 1, pct })}
                          className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200 font-bold text-xs transition cursor-pointer"
                        >
                          <Settings className="h-3.5 w-3.5" />
                          <span>Kelola</span>
                        </button>

                        <button
                          type="button"
                          onClick={() => setExpandedKey(isExpanded ? null : cardKey)}
                          className="p-1.5 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 text-gray-400 transition cursor-pointer"
                          title="Detail Traffic"
                        >
                          <ChevronDown
                            className={cn(
                              "h-4 w-4 text-gray-400 transition-transform duration-200",
                              isExpanded && "rotate-180 text-gray-700 dark:text-gray-200"
                            )}
                          />
                        </button>
                      </div>
                    </div>

                    {/* Expanded Detail Accordion */}
                    {isExpanded && (
                      <div
                        className="pt-3 border-t border-gray-100 dark:border-gray-800/80 space-y-2.5 animate-in fade-in-50 duration-150"
                        onClick={(e) => e.stopPropagation()}
                      >
                        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-2 text-xs">
                          {/* Durasi / Status Sesi */}
                          <div className="flex items-center justify-between p-2.5 rounded-xl bg-gray-50 dark:bg-gray-900/60 border border-gray-100 dark:border-gray-800">
                            <span className="text-gray-500 dark:text-gray-400 flex items-center gap-1.5">
                              <Clock className="h-3.5 w-3.5 text-gray-400" /> Sesi
                            </span>
                            <span className={cn("font-mono font-bold", u.is_online ? "text-emerald-600 dark:text-emerald-400" : "text-gray-500")}>
                              {formatUptime(u.uptime)}
                            </span>
                          </div>

                          {/* Alamat IP */}
                          <div className="flex items-center justify-between p-2.5 rounded-xl bg-gray-50 dark:bg-gray-900/60 border border-gray-100 dark:border-gray-800">
                            <span className="text-gray-500 dark:text-gray-400 flex items-center gap-1.5">
                              <Activity className="h-3.5 w-3.5 text-brand-500" /> IP
                            </span>
                            <span className="font-mono font-bold text-gray-800 dark:text-gray-200">
                              {u.ip || (u.address?.includes("(") ? u.address.split("(")[0].trim() : u.address) || "-"}
                            </span>
                          </div>

                          {/* Download (TX) */}
                          <div className="flex items-center justify-between p-2.5 rounded-xl bg-gray-50 dark:bg-gray-900/60 border border-gray-100 dark:border-gray-800">
                            <span className="text-gray-500 dark:text-gray-400 flex items-center gap-1.5">
                              <Download className="h-3.5 w-3.5 text-emerald-500" /> Download
                            </span>
                            <span className="font-mono font-bold text-emerald-600 dark:text-emerald-400">
                              {u.tx}
                            </span>
                          </div>

                          {/* Upload (RX) */}
                          <div className="flex items-center justify-between p-2.5 rounded-xl bg-gray-50 dark:bg-gray-900/60 border border-gray-100 dark:border-gray-800">
                            <span className="text-gray-500 dark:text-gray-400 flex items-center gap-1.5">
                              <Upload className="h-3.5 w-3.5 text-sky-500" /> Upload
                            </span>
                            <span className="font-mono font-bold text-sky-600 dark:text-sky-400">
                              {u.rx}
                            </span>
                          </div>
                        </div>

                        {/* Traffic Share */}
                        <div className="flex items-center justify-between px-3 py-2 rounded-xl bg-gray-50 dark:bg-gray-900/60 border border-gray-100 dark:border-gray-800 text-xs">
                          <span className="text-gray-500 dark:text-gray-400 flex items-center gap-1.5">
                            <Gauge className="h-3.5 w-3.5 text-brand-500" /> Rasio Traffic Puncak
                          </span>
                          <span className="font-mono font-bold text-brand-600 dark:text-brand-400">
                            {pct}% dari traffic puncak
                          </span>
                        </div>
                      </div>
                    )}
                  </motion.div>
                )
              })}

              {data?.grand_total && (
                <div className="pt-2 text-center text-xs text-gray-500 dark:text-gray-400 font-medium">
                  Total Akumulasi: <span className="font-bold text-gray-900 dark:text-white font-mono">{data.grand_total}</span> pada{" "}
                  <span className="font-bold text-brand-600 dark:text-brand-400 font-mono">{filtered.length}</span> pelanggan
                </div>
              )}
            </div>
          )}
        </div>
      </div>

      {/* POP-UP MODAL KELOLA TOP BANDWIDTH */}
      <AnimatePresence>
        {managingUser && (
          <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
            <motion.div
              initial={{ opacity: 0 }}
              animate={{ opacity: 1 }}
              exit={{ opacity: 0 }}
              onClick={() => setManagingUser(null)}
              className="fixed inset-0 bg-black/60 backdrop-blur-xs"
            />
            <motion.div
              initial={{ opacity: 0, scale: 0.95, y: 10 }}
              animate={{ opacity: 1, scale: 1, y: 0 }}
              exit={{ opacity: 0, scale: 0.95, y: 10 }}
              className="relative w-full max-w-md max-h-[92vh] overflow-y-auto rounded-2xl border border-gray-200 bg-white p-6 shadow-2xl dark:border-gray-800 dark:bg-gray-900 z-10 space-y-4"
            >
              {/* Header */}
              <div className="flex items-start justify-between gap-3 border-b border-gray-100 pb-4 dark:border-gray-800">
                <div className="flex items-center gap-3">
                  <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-brand-500 text-white font-black text-sm shadow-xs">
                    #{managingUser.rank}
                  </div>
                  <div>
                    <h3 className="text-base font-bold text-gray-800 dark:text-white">
                      {managingUser.user.name}
                    </h3>
                    <div className="flex items-center gap-2 mt-1">
                      <span
                        className={cn(
                          "inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold text-white shadow-xs",
                          managingUser.user.is_online ? "bg-emerald-500" : "bg-gray-500"
                        )}
                      >
                        {managingUser.user.is_online ? "Online" : "Offline"}
                      </span>
                      <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold bg-brand-500 text-white shadow-xs uppercase whitespace-nowrap">
                        {type}
                      </span>
                    </div>
                  </div>
                </div>
                <button
                  type="button"
                  onClick={() => setManagingUser(null)}
                  className="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 dark:text-gray-500 transition cursor-pointer"
                >
                  <X className="h-5 w-5" />
                </button>
              </div>

              {/* Info Grid */}
              <div className="space-y-2.5">
                <div className="rounded-xl border border-gray-100 bg-gray-50/80 dark:border-gray-800 dark:bg-gray-900/50 p-3 space-y-2 text-xs">
                  <div className="flex justify-between items-center py-1 border-b border-gray-200/50 dark:border-gray-800/50">
                    <span className="text-gray-500 dark:text-gray-400 font-medium">Total Akumulasi Traffic</span>
                    <strong className="font-mono text-sm font-bold text-brand-600 dark:text-brand-400">
                      {managingUser.user.total}
                    </strong>
                  </div>
                  <div className="flex justify-between items-center py-1 border-b border-gray-200/50 dark:border-gray-800/50">
                    <span className="text-gray-500 dark:text-gray-400 font-medium">Download (TX)</span>
                    <span className="font-mono font-bold text-emerald-600 dark:text-emerald-400">
                      {managingUser.user.tx}
                    </span>
                  </div>
                  <div className="flex justify-between items-center py-1 border-b border-gray-200/50 dark:border-gray-800/50">
                    <span className="text-gray-500 dark:text-gray-400 font-medium">Upload (RX)</span>
                    <span className="font-mono font-bold text-sky-600 dark:text-sky-400">
                      {managingUser.user.rx}
                    </span>
                  </div>
                  <div className="flex justify-between items-center py-1 border-b border-gray-200/50 dark:border-gray-800/50">
                    <span className="text-gray-500 dark:text-gray-400 font-medium">IP Address / Host</span>
                    <span className="font-mono font-bold text-gray-800 dark:text-gray-200">
                      {managingUser.user.ip || (managingUser.user.address?.includes("(") ? managingUser.user.address.split("(")[0].trim() : managingUser.user.address) || "-"}
                    </span>
                  </div>
                  <div className="flex justify-between items-center py-1 border-b border-gray-200/50 dark:border-gray-800/50">
                    <span className="text-gray-500 dark:text-gray-400 font-medium">Paket Profil</span>
                    <span className="font-semibold text-gray-800 dark:text-gray-200">
                      {managingUser.user.package || "-"}
                    </span>
                  </div>
                  <div className="flex justify-between items-center py-1">
                    <span className="text-gray-500 dark:text-gray-400 font-medium">Durasi Uptime Sesi</span>
                    <span className="font-mono text-gray-700 dark:text-gray-300">
                      {formatUptime(managingUser.user.uptime)}
                    </span>
                  </div>
                </div>
              </div>

              {/* Action Buttons */}
              <div className="space-y-2 pt-2">
                <button
                  type="button"
                  onClick={() => {
                    const ip = managingUser.user.ip || (managingUser.user.address?.includes("(") ? managingUser.user.address.split("(")[0].trim() : managingUser.user.address) || ""
                    handleCopy(ip)
                  }}
                  className="w-full inline-flex items-center justify-center gap-1.5 px-3.5 py-2.5 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:hover:bg-gray-800 text-xs font-semibold text-gray-700 dark:text-gray-200 transition shadow-2xs cursor-pointer"
                >
                  {copied ? <Check className="h-4 w-4 text-emerald-500" /> : <Copy className="h-4 w-4 text-gray-400" />}
                  <span>{copied ? "IP Tersalin ke Clipboard!" : "Salin IP Pelanggan"}</span>
                </button>

                <button
                  type="button"
                  onClick={() => {
                    setSearch(managingUser.user.name)
                    setManagingUser(null)
                  }}
                  className="w-full inline-flex items-center justify-center gap-1.5 px-3.5 py-2.5 rounded-xl bg-brand-500 hover:bg-brand-600 active:scale-95 text-xs font-bold text-white shadow-xs transition cursor-pointer"
                >
                  <Search className="h-4 w-4" />
                  <span>Filter Hanya Pengguna Ini</span>
                </button>
              </div>
            </motion.div>
          </div>
        )}
      </AnimatePresence>
    </AppLayout>
  )
}
