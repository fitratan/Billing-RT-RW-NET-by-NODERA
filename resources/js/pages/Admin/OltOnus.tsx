import { useState, useEffect, useMemo, useCallback } from "react"
import Chart from "react-apexcharts"
import { AppLayout } from "@/components/layout/app-layout"
import {
  RefreshCw,
  Search,
  Radio,
  Trash2,
  CheckCircle2,
  X,
  RotateCcw,
  Cpu,
  HardDrive,
  Thermometer,
  Clock,
  Server,
  User,
  Link as LinkIcon,
  Unlink,
  MapPin,
  AlertTriangle,
  WifiOff,
  Check,
  Activity,
} from "lucide-react"
import { PageProps } from "@/types"
import { cn } from "@/lib/utils"
import { adminSidebarItems, adminNavItems, adminBrand } from "@/lib/admin-nav"
import { router } from "@inertiajs/react"
import Modal from "@/components/tailadmin/Modal"
import { ViewModeSwitcher, ViewMode } from "@/components/tailadmin/ui/view-mode-switcher"
import { EmptyState } from "@/components/ui/empty-state"
import axios from "axios"

interface HardwareMetrics {
  cpu?: number | null
  ram?: number | null
  ram_used_mb?: number | null
  ram_total_mb?: number | null
  temp?: number | null
  uptime?: string | null
  sys_name?: string | null
  sys_descr?: string | null
  pon_ports?: Record<string, { total: number; online: number; offline: number }>
  updated_at?: string | null
}

interface OltInfo {
  id: number
  name: string
  host: string
  port: number
  snmp_port: number
  model?: string
  submodel?: string | null
  last_poll_status?: string | null
  last_poll_at?: string | null
  hardware_metrics?: HardwareMetrics | null
}

interface CustomerOption {
  id: number
  name: string
  pppoe_username?: string | null
  phone?: string | null
}

interface Onu {
  id: number
  name: string
  serial: string
  pon_port: string
  status: "online" | "offline"
  offline_reason?: string | null
  rx_power: number | null
  tx_power: number | null
  distance: number | null
  temperature?: number | null
  customer_id?: number | null
  customer_name?: string | null
  customer_pppoe?: string | null
  last_sync: string
}

export default function OltOnusPage({
  olt,
  onus = [],
  resources,
  customers = [],
}: PageProps<{
  olt: OltInfo
  onus: Onu[]
  resources?: HardwareMetrics | null
  customers?: CustomerOption[]
  companyName?: string
}>) {
  const [q, setQ] = useState("")
  const [statusFilter, setStatusFilter] = useState<"all" | "online" | "offline">("all")
  const [signalFilter, setSignalFilter] = useState<"all" | "good" | "warning" | "bad">("all")
  const [ponFilter, setPonFilter] = useState("all")

  const [managingOnu, setManagingOnu] = useState<Onu | null>(null)
  const [syncing, setSyncing] = useState(false)
  const [autoSync, setAutoSync] = useState(true)
  const [countdown, setCountdown] = useState(15)
  const [isAutoSyncing, setIsAutoSyncing] = useState(false)

  const [viewMode, setViewMode] = useState<ViewMode>(() => {
    if (typeof window !== "undefined") {
      const saved = localStorage.getItem("nodera_olt_onus_view_mode") as ViewMode | null
      if (saved === "table" || saved === "grid") return saved
    }
    return "grid"
  })

  // Live state updated via background polling
  const [liveOnus, setLiveOnus] = useState<Onu[]>(onus)
  const [liveMetrics, setLiveMetrics] = useState<HardwareMetrics | null | undefined>(resources || olt.hardware_metrics)

  useEffect(() => {
    setLiveOnus(onus)
    if (onus.length === 0) {
      performLiveSync()
    }
  }, [onus])

  useEffect(() => {
    setLiveMetrics(resources || olt.hardware_metrics)
  }, [resources, olt.hardware_metrics])

  // Linking customer modal
  const [linkingOnu, setLinkingOnu] = useState<Onu | null>(null)
  const [customerSearch, setCustomerSearch] = useState("")

  const metrics = liveMetrics || resources || olt.hardware_metrics

  // Base scope for counts (if PON port is filtered, cards adapt to that PON scope!)
  const scopedOnus = ponFilter === "all" ? liveOnus : liveOnus.filter((o) => o.pon_port === ponFilter)

  const totalScoped = scopedOnus.length
  const onlineScoped = scopedOnus.filter((o) => o.status === "online").length
  const offlineScoped = scopedOnus.filter((o) => o.status === "offline").length

  // Redaman Statistics (Scoped by PON Filter if active)
  const goodScoped = scopedOnus.filter(
    (o) => o.status === "online" && o.rx_power != null && o.rx_power >= -23 && o.rx_power <= -10
  ).length

  const warningScoped = scopedOnus.filter(
    (o) => o.status === "online" && o.rx_power != null && o.rx_power >= -25 && o.rx_power < -23
  ).length

  const badScoped = scopedOnus.filter(
    (o) => o.status === "online" && (o.rx_power == null || o.rx_power < -25 || o.rx_power > -10)
  ).length

  // Extract all unique PON Ports for dynamic dropdown
  const ponPorts = useMemo(() => {
    const set = new Set<string>()
    liveOnus.forEach((o) => {
      if (o.pon_port) set.add(o.pon_port)
    })
    return Array.from(set).sort((a, b) => a.localeCompare(b, undefined, { numeric: true, sensitivity: "base" }))
  }, [liveOnus])

  // Filtered ONU list
  const filtered = useMemo(() => {
    return liveOnus.filter((o) => {
      if (statusFilter !== "all" && o.status !== statusFilter) return false
      if (ponFilter !== "all" && o.pon_port !== ponFilter) return false

      if (signalFilter !== "all") {
        if (o.status !== "online") return false
        if (o.rx_power == null) return signalFilter === "bad"
        if (signalFilter === "good" && (o.rx_power < -23 || o.rx_power > -10)) return false
        if (signalFilter === "warning" && (o.rx_power < -25 || o.rx_power >= -23)) return false
        if (signalFilter === "bad" && (o.rx_power >= -25 && o.rx_power <= -10)) return false
      }

      if (!q.trim()) return true
      const s = q.toLowerCase().trim()
      const name = (o.name || "").toLowerCase()
      const serial = (o.serial || "").toLowerCase()
      const cleanSerial = serial.replace(/[^a-z0-9]/g, "")
      const pon = (o.pon_port || "").toLowerCase()
      const custName = (o.customer_name || "").toLowerCase()
      const custPppoe = (o.customer_pppoe || "").toLowerCase()

      return (
        name.includes(s) ||
        serial.includes(s) ||
        cleanSerial.includes(s) ||
        pon.includes(s) ||
        custName.includes(s) ||
        custPppoe.includes(s)
      )
    })
  }, [liveOnus, statusFilter, ponFilter, signalFilter, q])

  const [visibleLimit, setVisibleLimit] = useState(60)

  useEffect(() => {
    setVisibleLimit(60)
  }, [statusFilter, ponFilter, signalFilter, q])

  const displayedOnus = useMemo(() => filtered.slice(0, visibleLimit), [filtered, visibleLimit])

  // Background Auto-sync Poller
  const performLiveSync = useCallback(async () => {
    if (isAutoSyncing || syncing) return
    setIsAutoSyncing(true)
    try {
      const res = await axios.post(`/admin/olt/sync/${olt.id}`, {}, {
        headers: { "X-Live-Sync": "true" },
      })
      if (res.data && res.data.success && Array.isArray(res.data.onus)) {
        setLiveOnus(res.data.onus)
        if (res.data.hardware_metrics) {
          setLiveMetrics(res.data.hardware_metrics)
        }
      }
    } catch (err) {
      console.warn("Auto-sync OLT failed quietly:", err)
    } finally {
      setIsAutoSyncing(false)
      setCountdown(15)
    }
  }, [olt.id, isAutoSyncing, syncing])

  useEffect(() => {
    if (!autoSync) return
    const timer = setInterval(() => {
      setCountdown((prev) => {
        if (prev <= 1) {
          performLiveSync()
          return 15
        }
        return prev - 1
      })
    }, 1000)
    return () => clearInterval(timer)
  }, [autoSync, performLiveSync])

  // Manual Sync trigger
  const triggerSync = async () => {
    if (syncing) return
    setSyncing(true)
    try {
      const res = await axios.post(`/admin/olt/sync/${olt.id}`, {}, {
        headers: { "X-Live-Sync": "true" },
      })
      if (res.data && res.data.success && Array.isArray(res.data.onus)) {
        setLiveOnus(res.data.onus)
        if (res.data.hardware_metrics) {
          setLiveMetrics(res.data.hardware_metrics)
        }
        setCountdown(15)
      } else {
        router.reload({ only: ["onus", "olt", "resources"] })
      }
    } catch (e) {
      router.reload({ only: ["onus", "olt", "resources"] })
    } finally {
      setSyncing(false)
    }
  }

  const rebootOlt = () => {
    if (confirm(`Reboot OLT ${olt.name}? Seluruh koneksi pelanggan pada OLT ini akan restart sementara.`)) {
      router.post(`/admin/olt/reboot/${olt.id}`, {}, { preserveScroll: true })
    }
  }

  const rebootOnu = (onuId: number, name: string) => {
    if (confirm(`Reboot ONU ${name}? Sinyal koneksi akan restart selama 1 menit.`)) {
      setManagingOnu(null)
      router.post(`/admin/olt/onus/${olt.id}/reboot/${onuId}`, {}, { preserveScroll: true })
    }
  }

  const deleteOnu = (onuId: number, name: string) => {
    if (confirm(`Hapus ONU ${name} dari daftar OLT?`)) {
      setManagingOnu(null)
      router.post(`/admin/olt/onus/${olt.id}/delete/${onuId}`, {}, { preserveScroll: true })
    }
  }

  const linkCustomerToOnu = (customerId: number | null, targetOnuId?: number) => {
    const onuId = targetOnuId || linkingOnu?.id
    if (!onuId) return
    router.post(
      `/admin/olt/onus/${olt.id}/link-customer/${onuId}`,
      { customer_id: customerId },
      {
        preserveScroll: true,
        onSuccess: () => setLinkingOnu(null),
      }
    )
  }

  const getPowerSolidBadge = (power: number | null) => {
    if (power == null) {
      return "bg-gray-500 text-white font-bold text-[10px] uppercase px-2 py-0.5 rounded-md shadow-xs font-mono inline-flex items-center"
    }
    if (power >= -23 && power <= -10) {
      return "bg-emerald-500 text-white font-bold text-[10px] uppercase px-2 py-0.5 rounded-md shadow-xs font-mono inline-flex items-center"
    }
    if (power >= -25 && power < -23) {
      return "bg-amber-500 text-white font-bold text-[10px] uppercase px-2 py-0.5 rounded-md shadow-xs font-mono inline-flex items-center"
    }
    return "bg-rose-500 text-white font-bold text-[10px] uppercase px-2 py-0.5 rounded-md shadow-xs font-mono inline-flex items-center"
  }

  const formatDeviceIdentifier = (serial: string | null | undefined): { type: "MAC" | "SN"; formatted: string } => {
    if (!serial || serial === "-") return { type: "SN", formatted: "-" }
    const clean = serial.replace(/[^a-zA-Z0-9]/g, "")
    if (clean.length === 12 && /^[0-9a-fA-F]{12}$/.test(clean)) {
      const formatted = clean.match(/.{1,2}/g)?.join(":").toUpperCase() || clean.toUpperCase()
      return { type: "MAC", formatted }
    }
    return { type: "SN", formatted: serial.toUpperCase() }
  }

  // ── Executive Optical Quality & Status Visualizations ──
  const optimalRate = totalScoped > 0 ? Math.round((goodScoped / totalScoped) * 100) : 0

  const onuStatusDonutOptions = useMemo(() => ({
    chart: { type: "donut" as const, fontFamily: "Outfit, Inter, sans-serif" },
    labels: ["Bagus (Optimal)", "Sedang", "Kritis (Drop)", "Disconnect (LOS)"],
    colors: ["#10B981", "#F59E0B", "#F43F5E", "#64748B"],
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
              label: "Total ONU",
              fontSize: "11px",
              fontWeight: 600,
              color: "#64748B",
              formatter: () => `${totalScoped} ONU`,
            },
            value: { fontSize: "16px", fontWeight: 700, color: "#0F172A" },
          },
        },
      },
    },
    tooltip: {
      theme: "light",
      y: { formatter: (val: number) => `${val} Unit` },
    },
  }), [totalScoped])

  const opticalHealthRadialOptions = useMemo(() => ({
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
            color: optimalRate >= 80 ? "#10B981" : optimalRate >= 60 ? "#F59E0B" : "#F43F5E",
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
        gradientToColors: [optimalRate >= 80 ? "#10B981" : "#F59E0B"],
        stops: [0, 100],
      },
    },
    stroke: { dashArray: 4 },
    colors: [optimalRate >= 80 ? "#10B981" : "#0073C6"],
    labels: ["Sinyal Optimal"],
  }), [optimalRate])

  const filteredCustomerList = customers.filter(
    (c) =>
      !customerSearch ||
      c.name.toLowerCase().includes(customerSearch.toLowerCase()) ||
      (c.pppoe_username && c.pppoe_username.toLowerCase().includes(customerSearch.toLowerCase())) ||
      (c.phone && c.phone.includes(customerSearch))
  )

  return (
    <AppLayout
      title={`Daftar ONU — ${olt.name}`}
      brand={adminBrand}
      sidebarItems={adminSidebarItems}
      navItems={adminNavItems}
    >
      <div className="space-y-6">
        {/* Hardware Stats Runway (CPU, RAM, Temp, Uptime) */}
        <div className="overflow-hidden rounded-2xl border border-gray-200 bg-white p-4 sm:p-5 shadow-xs dark:border-gray-800 dark:bg-white/[0.03]">
          <div className="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
            <div className="space-y-1.5 flex-1 min-w-0">
              <div className="flex items-center gap-2 flex-wrap">
                <span className="inline-flex items-center gap-1.5 rounded-md bg-brand-500 text-white px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wider font-mono shadow-xs whitespace-nowrap">
                  <Radio className="h-3 w-3" /> {olt.host}:{olt.snmp_port || 161}
                </span>
                <span className="text-xs text-gray-500 dark:text-gray-400 font-mono uppercase">
                  {olt.model ? olt.model.toUpperCase() : "EPON/GPON"}
                </span>
                {ponFilter !== "all" && (
                  <span className="inline-flex items-center gap-1 rounded-md bg-brand-500 text-white px-2 py-0.5 text-[10.5px] font-mono font-bold shadow-xs whitespace-nowrap">
                    Port: {ponFilter}
                  </span>
                )}
              </div>

              <div className="text-lg sm:text-xl font-bold text-gray-900 dark:text-white truncate">
                {olt.name}
              </div>

              <p className="text-xs text-gray-500 dark:text-gray-400 line-clamp-1 font-mono text-[11px]">
                {metrics?.sys_descr || metrics?.sys_name || "SNMP OLT Agent Connected"}
              </p>
            </div>

            {/* Real-time Hardware Meters */}
            <div className="grid grid-cols-2 sm:grid-cols-4 gap-2.5 shrink-0">
              <div className="rounded-xl border border-gray-100 bg-gray-50/80 p-2.5 dark:border-gray-800 dark:bg-gray-900/50 flex flex-col justify-between min-w-[100px]">
                <div className="flex items-center justify-between text-gray-500 dark:text-gray-400 text-[10.5px] font-semibold">
                  <span>CPU</span>
                  <Cpu className="h-3.5 w-3.5 text-brand-500" />
                </div>
                <div className="text-base font-bold font-mono text-gray-900 dark:text-white mt-0.5">
                  {metrics?.cpu != null ? `${metrics.cpu}%` : "—"}
                </div>
              </div>

              <div className="rounded-xl border border-gray-100 bg-gray-50/80 p-2.5 dark:border-gray-800 dark:bg-gray-900/50 flex flex-col justify-between min-w-[100px]">
                <div className="flex items-center justify-between text-gray-500 dark:text-gray-400 text-[10.5px] font-semibold">
                  <span>RAM</span>
                  <HardDrive className="h-3.5 w-3.5 text-purple-500" />
                </div>
                <div className="text-base font-bold font-mono text-gray-900 dark:text-white mt-0.5">
                  {metrics?.ram != null ? `${metrics.ram}%` : "—"}
                </div>
              </div>

              <div className="rounded-xl border border-gray-100 bg-gray-50/80 p-2.5 dark:border-gray-800 dark:bg-gray-900/50 flex flex-col justify-between min-w-[100px]">
                <div className="flex items-center justify-between text-gray-500 dark:text-gray-400 text-[10.5px] font-semibold">
                  <span>Suhu</span>
                  <Thermometer className="h-3.5 w-3.5 text-amber-500" />
                </div>
                <div className="text-base font-bold font-mono text-gray-900 dark:text-white mt-0.5">
                  {metrics?.temp != null ? `${metrics.temp}°C` : "—"}
                </div>
              </div>

              <div className="rounded-xl border border-gray-100 bg-gray-50/80 p-2.5 dark:border-gray-800 dark:bg-gray-900/50 flex flex-col justify-between min-w-[100px]">
                <div className="flex items-center justify-between text-gray-500 dark:text-gray-400 text-[10.5px] font-semibold">
                  <span>Uptime</span>
                  <Clock className="h-3.5 w-3.5 text-emerald-500" />
                </div>
                <div className="text-xs font-bold font-mono text-emerald-600 dark:text-emerald-400 mt-0.5 truncate">
                  {metrics?.uptime || "Active"}
                </div>
              </div>
            </div>
          </div>
        </div>

        {/* ── Executive Multi-Type Visual Analytics ── */}
        <div className="grid grid-cols-1 lg:grid-cols-12 gap-4 sm:gap-5">
          {/* Card 1: Kualitas Redaman Optik & Indeks Sinyal (5 Cols) */}
          <div className="lg:col-span-5 rounded-2xl border border-gray-200 bg-white p-4 sm:p-5 shadow-xs dark:border-gray-800 dark:bg-white/[0.03] flex flex-col justify-between">
            <div className="flex items-center justify-between border-b border-gray-100 dark:border-gray-800/80 pb-3">
              <div>
                <h3 className="text-sm font-bold text-gray-900 dark:text-white flex items-center gap-2">
                  <CheckCircle2 className="h-4 w-4 text-emerald-500" />
                  <span>Kesehatan Redaman Optik (Rx)</span>
                </h3>
                <p className="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                  Indeks redaman sinyal optimal &gt; -23 dBm
                </p>
              </div>
              <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-bold bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400">
                {goodScoped} / {totalScoped} Bagus
              </span>
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-12 gap-3 items-center py-2">
              <div className="sm:col-span-5 flex justify-center">
                <Chart
                  options={opticalHealthRadialOptions as any}
                  series={[optimalRate]}
                  type="radialBar"
                  height={150}
                  width="100%"
                />
              </div>
              <div className="sm:col-span-7 space-y-2">
                <button
                  type="button"
                  onClick={() => {
                    setStatusFilter("online")
                    setSignalFilter(signalFilter === "good" ? "all" : "good")
                  }}
                  className={cn(
                    "w-full flex items-center justify-between rounded-xl p-2.5 transition text-left border cursor-pointer",
                    signalFilter === "good"
                      ? "bg-emerald-50 border-emerald-300 dark:bg-emerald-500/15 dark:border-emerald-500/30"
                      : "bg-gray-50/80 border-gray-100 dark:bg-gray-900/50 dark:border-gray-800/60 hover:bg-gray-100 dark:hover:bg-gray-800/50"
                  )}
                >
                  <span className="text-xs text-gray-600 dark:text-gray-300 flex items-center gap-1.5">
                    <CheckCircle2 className="h-3.5 w-3.5 text-emerald-500" /> Optimal (-10..-23 dBm)
                  </span>
                  <span className="font-bold text-emerald-600 dark:text-emerald-400 text-xs">{goodScoped}</span>
                </button>

                <button
                  type="button"
                  onClick={() => {
                    setStatusFilter("online")
                    setSignalFilter(signalFilter === "warning" ? "all" : "warning")
                  }}
                  className={cn(
                    "w-full flex items-center justify-between rounded-xl p-2.5 transition text-left border cursor-pointer",
                    signalFilter === "warning"
                      ? "bg-amber-50 border-amber-300 dark:bg-amber-500/15 dark:border-amber-500/30"
                      : "bg-gray-50/80 border-gray-100 dark:bg-gray-900/50 dark:border-gray-800/60 hover:bg-gray-100 dark:hover:bg-gray-800/50"
                  )}
                >
                  <span className="text-xs text-gray-600 dark:text-gray-300 flex items-center gap-1.5">
                    <AlertTriangle className="h-3.5 w-3.5 text-amber-500" /> Sedang (-23..-25 dBm)
                  </span>
                  <span className="font-bold text-amber-600 dark:text-amber-400 text-xs">{warningScoped}</span>
                </button>

                <button
                  type="button"
                  onClick={() => {
                    setStatusFilter("online")
                    setSignalFilter(signalFilter === "bad" ? "all" : "bad")
                  }}
                  className={cn(
                    "w-full flex items-center justify-between rounded-xl p-2.5 transition text-left border cursor-pointer",
                    signalFilter === "bad"
                      ? "bg-rose-50 border-rose-300 dark:bg-rose-500/15 dark:border-rose-500/30"
                      : "bg-gray-50/80 border-gray-100 dark:bg-gray-900/50 dark:border-gray-800/60 hover:bg-gray-100 dark:hover:bg-gray-800/50"
                  )}
                >
                  <span className="text-xs text-gray-600 dark:text-gray-300 flex items-center gap-1.5">
                    <Activity className="h-3.5 w-3.5 text-rose-500" /> Kritis (&lt; -25 dBm)
                  </span>
                  <span className="font-bold text-rose-600 dark:text-rose-400 text-xs">{badScoped}</span>
                </button>
              </div>
            </div>
          </div>

          {/* Card 2: Sebaran Status Koneksi & LOS (7 Cols) */}
          <div className="lg:col-span-7 rounded-2xl border border-gray-200 bg-white p-4 sm:p-5 shadow-xs dark:border-gray-800 dark:bg-white/[0.03] flex flex-col justify-between">
            <div className="flex items-center justify-between border-b border-gray-100 dark:border-gray-800/80 pb-3">
              <div>
                <h3 className="text-sm font-bold text-gray-900 dark:text-white flex items-center gap-2">
                  <Server className="h-4 w-4 text-brand-500" />
                  <span>Komposisi Status ONU &amp; Disconnect</span>
                </h3>
                <p className="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                  Monitoring ONU online aktif vs power down / kabel putus (LOS)
                </p>
              </div>
              <button
                type="button"
                onClick={() => {
                  setSignalFilter("all")
                  setStatusFilter(statusFilter === "offline" ? "all" : "offline")
                }}
                className={cn(
                  "inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-bold transition cursor-pointer",
                  statusFilter === "offline"
                    ? "bg-rose-500 text-white shadow-xs"
                    : "bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300 hover:bg-gray-200"
                )}
              >
                <WifiOff className="h-3.5 w-3.5" />
                {offlineScoped} Disconnect
              </button>
            </div>

            <div className="pt-2">
              <Chart
                options={onuStatusDonutOptions as any}
                series={[goodScoped, warningScoped, badScoped, offlineScoped]}
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
                placeholder="Cari nama ONU, SN, MAC, PPPoE username, pelanggan..."
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

            {/* Status Filter */}
            <select
              value={statusFilter}
              onChange={(e) => {
                setStatusFilter(e.target.value as any)
                if (e.target.value === "offline") setSignalFilter("all")
              }}
              className="h-10 rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 px-3 text-xs font-semibold text-gray-700 dark:text-gray-200 cursor-pointer w-full sm:w-auto shrink-0 shadow-2xs"
            >
              <option value="all">Semua Status ({liveOnus.length})</option>
              <option value="online">Online ({onlineScoped})</option>
              <option value="offline">Offline ({offlineScoped})</option>
            </select>

            {/* Redaman Signal Filter */}
            <select
              value={signalFilter}
              onChange={(e) => {
                setSignalFilter(e.target.value as any)
                if (e.target.value !== "all") setStatusFilter("online")
              }}
              className="h-10 rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 px-3 text-xs font-semibold text-gray-700 dark:text-gray-200 cursor-pointer w-full sm:w-auto shrink-0 shadow-2xs"
            >
              <option value="all">Semua Redaman</option>
              <option value="good">Bagus (≤ -24 dBm) ({goodScoped})</option>
              <option value="warning">Sedang (-24 ~ -27) ({warningScoped})</option>
              <option value="bad">Kritis (&gt; -27 dBm) ({badScoped})</option>
            </select>

            {/* Port PON Filter */}
            {ponPorts.length > 0 && (
              <select
                value={ponFilter}
                onChange={(e) => setPonFilter(e.target.value)}
                className="h-10 rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 px-3 text-xs font-semibold text-gray-700 dark:text-gray-200 cursor-pointer w-full sm:w-auto shrink-0 shadow-2xs max-w-[170px] truncate"
              >
                <option value="all">Semua Port PON ({ponPorts.length})</option>
                {ponPorts.map((port) => (
                  <option key={port} value={port}>
                    PON: {port} ({liveOnus.filter((o) => o.pon_port === port).length})
                  </option>
                ))}
              </select>
            )}
          </div>

          <div className="flex items-center gap-2 shrink-0 w-full sm:w-auto justify-end">
            <button
              type="button"
              onClick={() => setAutoSync(!autoSync)}
              className={cn(
                "h-10 inline-flex items-center gap-1.5 px-3 rounded-xl border text-xs font-semibold transition active:scale-95 shadow-2xs shrink-0",
                autoSync
                  ? "border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-800/50 dark:bg-emerald-950/40 dark:text-emerald-300"
                  : "border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:hover:bg-gray-800 text-gray-700 dark:text-gray-200"
              )}
              title="Sinkronisasi otomatis di latar belakang setiap 15 detik"
            >
              <span className={cn("h-2 w-2 rounded-full shrink-0", autoSync ? "bg-emerald-500 animate-pulse" : "bg-gray-400")} />
              <span className="font-mono text-[11px] font-bold whitespace-nowrap">
                {autoSync ? (isAutoSyncing ? "Sync..." : `Live ${countdown}s`) : "Auto Off"}
              </span>
            </button>

            <button
              type="button"
              onClick={triggerSync}
              disabled={syncing}
              className="h-10 inline-flex items-center gap-1.5 px-3 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:hover:bg-gray-800 text-xs font-semibold text-gray-700 dark:text-gray-200 transition active:scale-95 shadow-2xs disabled:opacity-50 cursor-pointer shrink-0"
              title="Sinkronisasi Manual SNMP Sekarang"
            >
              <RefreshCw className={cn("h-4 w-4 text-brand-500", syncing && "animate-spin")} />
              <span className="hidden sm:inline">{syncing ? "Sinkron..." : "Sync OLT"}</span>
            </button>

            <button
              type="button"
              onClick={rebootOlt}
              className="h-10 inline-flex items-center gap-1.5 px-3.5 rounded-xl bg-rose-500 hover:bg-rose-600 text-xs font-bold text-white shadow-xs transition active:scale-95 cursor-pointer shrink-0"
              title="Reboot Perangkat OLT"
            >
              <RotateCcw className="h-4 w-4" />
              <span className="hidden sm:inline">Reboot OLT</span>
            </button>

            <div className="shrink-0">
              <ViewModeSwitcher
                value={viewMode}
                onChange={setViewMode}
                storageKey="nodera_olt_onus_view_mode"
                size="sm"
              />
            </div>
          </div>
        </div>

        {/* Master Card with Stream */}
        <div className="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xs dark:border-gray-800 dark:bg-white/[0.03]">

          {/* Content Body: Empty State or Table / Grid Mode */}
          {filtered.length === 0 ? (
            <div className="p-8 text-center">
              <EmptyState
                icon={<Radio className="h-8 w-8 text-brand-500" />}
                title="Tidak ada ONU ditemukan"
                description={q ? `Tidak ada ONU yang cocok dengan pencarian "${q}".` : "Belum ada data ONU yang tersinkronisasi untuk OLT ini."}
              />
              <div className="mt-4 flex justify-center">
                <button
                  type="button"
                  onClick={performLiveSync}
                  disabled={syncing || isAutoSyncing}
                  className="inline-flex items-center gap-2 rounded-xl bg-brand-500 hover:bg-brand-600 px-4 py-2 text-xs font-bold text-white shadow-xs transition active:scale-95 disabled:opacity-50"
                >
                  <RefreshCw className={cn("h-4 w-4", (syncing || isAutoSyncing) && "animate-spin")} />
                  <span>{syncing || isAutoSyncing ? "Sedang Sinkronisasi..." : "Sinkronisasi ONU Sekarang"}</span>
                </button>
              </div>
            </div>
          ) : viewMode === "table" ? (
            /* TABLE MODE */
            <div className="w-full overflow-x-auto custom-scrollbar">
              <table className="w-full border-collapse text-left text-xs min-w-[1050px]">
                <thead>
                  <tr className="border-b border-gray-200 bg-gray-50/75 dark:border-gray-800 dark:bg-white/[0.02] text-gray-500 dark:text-gray-400 font-bold uppercase tracking-wider text-[10px]">
                    <th className="py-3 px-4">Status Koneksi</th>
                    <th className="py-3 px-4">Nama ONU</th>
                    <th className="py-3 px-4">Pelanggan Terikat</th>
                    <th className="py-3 px-4">Port PON</th>
                    <th className="py-3 px-4">Serial / MAC</th>
                    <th className="py-3 px-4">Jarak</th>
                    <th className="py-3 px-4">RX Optical</th>
                    <th className="py-3 px-4">TX Optical</th>
                    <th className="py-3 px-4">Suhu</th>
                    <th className="py-3 px-4">Terakhir Sync</th>
                    <th className="py-3 px-4 text-end sm:px-6">Aksi</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-gray-100 dark:divide-gray-800">
                  {displayedOnus.map((onu) => {
                    const isOnline = onu.status === "online"
                    const isDown = !isOnline && (
                      onu.offline_reason === "down" ||
                      onu.offline_reason === "los" ||
                      onu.offline_reason === "wire_down" ||
                      (onu.offline_reason ? onu.offline_reason.toLowerCase().includes("down") && !onu.offline_reason.toLowerCase().includes("pwr") : false)
                    )
                    const isPwrDown = !isOnline && !isDown
                    const devId = formatDeviceIdentifier(onu.serial)

                    return (
                      <tr
                        key={onu.id}
                        className="hover:bg-gray-50 dark:hover:bg-white/[0.02] transition"
                      >
                        <td className="py-3 px-4 whitespace-nowrap">
                          {isOnline ? (
                            <span className="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-bold bg-emerald-500 text-white shadow-xs whitespace-nowrap">
                              Online
                            </span>
                          ) : isPwrDown ? (
                            <span className="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-bold bg-amber-500 text-white shadow-xs whitespace-nowrap">
                              PwrDown
                            </span>
                          ) : (
                            <span className="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-bold bg-rose-500 text-white shadow-xs whitespace-nowrap">
                              Down (LOS)
                            </span>
                          )}
                        </td>

                        <td className="py-3 px-4 font-bold text-gray-900 dark:text-white font-mono">
                          {onu.name}
                        </td>

                        <td className="py-3 px-4 min-w-[180px]">
                          {onu.customer_name ? (
                            <div className="text-xs text-brand-600 dark:text-brand-400 font-medium flex items-center gap-1">
                              <User className="h-3.5 w-3.5" />
                              <span className="font-semibold text-gray-900 dark:text-white">{onu.customer_name}</span>
                              {onu.customer_pppoe && (
                                <span className="text-gray-400 font-mono text-[11px]">({onu.customer_pppoe})</span>
                              )}
                            </div>
                          ) : (
                            <span className="text-[11px] text-gray-400 italic">
                              Belum terikat
                            </span>
                          )}
                        </td>

                        <td className="py-3 px-4 whitespace-nowrap font-mono font-bold text-gray-800 dark:text-gray-200">
                          {onu.pon_port}
                        </td>

                        <td className="py-3 px-4 whitespace-nowrap font-mono text-[11px] text-gray-600 dark:text-gray-300">
                          {devId.formatted}
                        </td>

                        <td className="py-3 px-4 whitespace-nowrap font-mono text-gray-700 dark:text-gray-300">
                          {onu.distance != null ? `${(onu.distance / 1000).toFixed(2)} km` : "—"}
                        </td>

                        <td className="py-3 px-4 whitespace-nowrap">
                          {isOnline && onu.rx_power != null ? (
                            <span className={getPowerSolidBadge(onu.rx_power)}>
                              {onu.rx_power} dBm
                            </span>
                          ) : (
                            <span className="text-gray-400 font-mono">—</span>
                          )}
                        </td>

                        <td className="py-3 px-4 whitespace-nowrap font-mono text-gray-700 dark:text-gray-300">
                          {onu.tx_power != null ? `${onu.tx_power} dBm` : "—"}
                        </td>

                        <td className="py-3 px-4 whitespace-nowrap font-mono text-gray-700 dark:text-gray-300">
                          {onu.temperature != null ? `${onu.temperature}°C` : "—"}
                        </td>

                        <td className="py-3 px-4 whitespace-nowrap text-gray-500 dark:text-gray-400 text-[11px]">
                          {onu.last_sync || "Baru saja"}
                        </td>

                        <td className="py-3 px-4 sm:px-6 whitespace-nowrap text-end">
                          <button
                            type="button"
                            onClick={() => setManagingOnu(onu)}
                            className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200 font-bold text-xs transition cursor-pointer"
                          >
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
            /* GRID MODE */
            <div className="p-4 sm:p-5">
              <div className="grid items-start gap-4 sm:grid-cols-2 lg:grid-cols-3">
                {displayedOnus.map((onu) => {
                  const isOnline = onu.status === "online"
                  const isDown = !isOnline && (
                    onu.offline_reason === "down" ||
                    onu.offline_reason === "los" ||
                    onu.offline_reason === "wire_down" ||
                    (onu.offline_reason ? onu.offline_reason.toLowerCase().includes("down") && !onu.offline_reason.toLowerCase().includes("pwr") : false)
                  )
                  const isPwrDown = !isOnline && !isDown
                  const devId = formatDeviceIdentifier(onu.serial)

                  const cleanNameForMonogram = onu.name
                    .replace(/^(gpon-onu_|epon-onu_|onu_|pon_)/i, "")
                    .trim()
                  const nameWords = cleanNameForMonogram.split(/[\s\-_/:]+/).filter(Boolean)
                  const monogram = (
                    nameWords.length >= 2
                      ? nameWords[0][0] + nameWords[1][0]
                      : (cleanNameForMonogram.slice(0, 2) || "ON")
                  ).toUpperCase()

                  return (
                    <div
                      key={onu.id}
                      className="rounded-2xl border border-gray-200 bg-white p-4 sm:p-5 transition shadow-xs dark:border-gray-800 dark:bg-white/[0.02] space-y-3.5"
                    >
                      {/* Top Header Card */}
                      <div className="flex items-start justify-between gap-2.5">
                        <div className="flex items-center gap-3 min-w-0 flex-1">
                          <div className="relative flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gray-100 border border-gray-200 text-gray-700 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-200 font-bold text-xs">
                            <span>{monogram || "ON"}</span>
                            {isOnline ? (
                              <span className="absolute -bottom-0.5 -right-0.5 h-2.5 w-2.5 rounded-full bg-emerald-500 ring-2 ring-white dark:ring-gray-900 whitespace-nowrap" />
                            ) : isPwrDown ? (
                              <span className="absolute -bottom-0.5 -right-0.5 h-2.5 w-2.5 rounded-full bg-amber-500 ring-2 ring-white dark:ring-gray-900 whitespace-nowrap" />
                            ) : (
                              <span className="absolute -bottom-0.5 -right-0.5 h-2.5 w-2.5 rounded-full bg-rose-500 ring-2 ring-white dark:ring-gray-900 whitespace-nowrap" />
                            )}
                          </div>

                          <div className="min-w-0 flex-1">
                            <h4 className="font-bold text-sm text-gray-900 dark:text-white truncate">
                              {onu.name}
                            </h4>

                            <div className="mt-1 flex items-center gap-2 text-xs font-medium text-gray-500 dark:text-gray-400 flex-wrap">
                              <span className="font-mono text-[11px] text-gray-600 dark:text-gray-300">{onu.pon_port}</span>

                              {onu.customer_name && (
                                <>
                                  <span className="text-gray-300 dark:text-gray-600">•</span>
                                  <span className="text-brand-600 dark:text-brand-400 font-semibold text-[11px] truncate max-w-[120px] flex items-center gap-1">
                                    <User className="h-3 w-3" />
                                    {onu.customer_name}
                                  </span>
                                </>
                              )}
                            </div>
                          </div>
                        </div>

                        <div className="shrink-0">
                          {isOnline ? (
                            <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold bg-emerald-500 text-white shadow-xs whitespace-nowrap">
                              Online
                            </span>
                          ) : isPwrDown ? (
                            <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold bg-amber-500 text-white shadow-xs whitespace-nowrap">
                              PwrDown
                            </span>
                          ) : (
                            <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold bg-rose-500 text-white shadow-xs whitespace-nowrap">
                              Down
                            </span>
                          )}
                        </div>
                      </div>

                      {/* Sunken Box for Jarak & RX Power */}
                      <div className="rounded-xl border border-gray-100 bg-gray-50/80 dark:border-gray-800 dark:bg-gray-900/50 px-3 py-2 flex items-center justify-between text-xs">
                        <div className="flex items-center gap-2 min-w-0">
                          <MapPin className="h-3.5 w-3.5 text-brand-500 shrink-0" />
                          <span className="font-mono text-xs font-medium text-gray-700 dark:text-gray-300 truncate">
                            {onu.distance != null ? (
                              <span>
                                Jarak: <strong className="text-gray-900 dark:text-white font-bold">{(onu.distance / 1000).toFixed(2)}</strong> km
                              </span>
                            ) : (
                              <span className="text-gray-400">Jarak: —</span>
                            )}
                          </span>
                        </div>

                        <div className="flex items-center gap-1.5 shrink-0 ml-2">
                          {isOnline && onu.rx_power != null ? (
                            <span className={getPowerSolidBadge(onu.rx_power)}>
                              {onu.rx_power} dBm
                            </span>
                          ) : (
                            <span className="text-[10px] font-mono font-bold px-2 py-0.5 rounded-md text-white bg-gray-500 shadow-xs whitespace-nowrap">
                              —
                            </span>
                          )}
                        </div>
                      </div>

                      {/* Action Row */}
                      <div className="pt-2 border-t border-gray-100 dark:border-gray-800 flex items-center justify-between">
                        <span className="font-mono text-[11px] text-gray-500 dark:text-gray-400">
                          {devId.formatted}
                        </span>

                        <button
                          type="button"
                          onClick={() => setManagingOnu(onu)}
                          className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200 font-bold text-xs transition cursor-pointer"
                        >
                          <span>Kelola</span>
                        </button>
                      </div>
                    </div>
                  )
                })}
              </div>
            </div>
          )}

          {/* Pagination / Show More */}
          {filtered.length > visibleLimit && (
            <div className="p-4 sm:p-5 border-t border-gray-100 dark:border-gray-800 bg-gray-50/50 dark:bg-white/[0.01] flex flex-col sm:flex-row items-center justify-between gap-3">
              <p className="text-xs text-gray-500 dark:text-gray-400 font-medium">
                Menampilkan <span className="text-gray-900 dark:text-white font-bold">{displayedOnus.length}</span> dari <span className="text-gray-900 dark:text-white font-bold">{filtered.length}</span> ONU
              </p>
              <div className="flex items-center gap-2">
                <button
                  type="button"
                  onClick={() => setVisibleLimit((prev) => prev + 60)}
                  className="px-4 py-2 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 text-gray-700 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800 text-xs font-semibold transition active:scale-95 shadow-2xs"
                >
                  +60 ONU Berikutnya
                </button>
                <button
                  type="button"
                  onClick={() => setVisibleLimit(filtered.length)}
                  className="px-4 py-2 rounded-xl bg-brand-500 hover:bg-brand-600 text-white text-xs font-bold transition active:scale-95 shadow-xs"
                >
                  Tampilkan Semua ({filtered.length})
                </button>
              </div>
            </div>
          )}
        </div>
      </div>

      {/* ── INTERACTIVE KELOLA MODAL FOR ONU (1:1 SUPERADMIN TAILADMIN) ── */}
      {managingOnu && (
        <div
          className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-xs animate-in fade-in"
          onClick={() => setManagingOnu(null)}
        >
          <div
            className="w-full max-w-md max-h-[92vh] overflow-y-auto rounded-2xl border border-gray-200 bg-white p-6 shadow-2xl dark:border-gray-800 dark:bg-gray-900 space-y-5"
            onClick={(e) => e.stopPropagation()}
          >
            {/* Modal Header */}
            <div className="flex items-start justify-between">
              <div className="flex items-center gap-3">
                <div className="flex h-12 w-12 items-center justify-center rounded-2xl bg-brand-500/10 text-brand-500 font-bold">
                  <Radio className="h-6 w-6" />
                </div>
                <div>
                  <h3 className="text-base font-bold text-gray-900 dark:text-white">
                    {managingOnu.name}
                  </h3>
                  <p className="text-xs font-mono text-gray-500 dark:text-gray-400">
                    {formatDeviceIdentifier(managingOnu.serial).formatted} • Port {managingOnu.pon_port}
                  </p>
                </div>
              </div>
              <button
                type="button"
                onClick={() => setManagingOnu(null)}
                className="rounded-xl p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-800 cursor-pointer"
              >
                <X className="h-5 w-5" />
              </button>
            </div>

            {/* Status Badges Row */}
            <div className="grid grid-cols-2 gap-2 text-xs">
              <div className="rounded-xl border border-gray-100 bg-gray-50/80 p-2.5 dark:border-gray-800 dark:bg-gray-900/50">
                <span className="text-[10px] text-gray-400 font-medium">Status Koneksi</span>
                <div className="mt-1">
                  {managingOnu.status === "online" ? (
                    <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold bg-emerald-500 text-white shadow-xs whitespace-nowrap">
                      Online
                    </span>
                  ) : (
                    <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold bg-rose-500 text-white shadow-xs whitespace-nowrap">
                      Offline
                    </span>
                  )}
                </div>
              </div>
              <div className="rounded-xl border border-gray-100 bg-gray-50/80 p-2.5 dark:border-gray-800 dark:bg-gray-900/50">
                <span className="text-[10px] text-gray-400 font-medium">RX Optical Power</span>
                <div className="mt-1">
                  {managingOnu.status === "online" && managingOnu.rx_power != null ? (
                    <span className={getPowerSolidBadge(managingOnu.rx_power)}>
                      {managingOnu.rx_power} dBm
                    </span>
                  ) : (
                    <span className="font-mono text-xs text-gray-400">—</span>
                  )}
                </div>
              </div>
            </div>

            {/* Telemetry Detail Grid */}
            <div className="rounded-xl border border-gray-100 bg-gray-50/80 p-3.5 dark:border-gray-800 dark:bg-gray-900/50 space-y-2 text-xs">
              <div className="flex items-center justify-between">
                <span className="text-gray-500 dark:text-gray-400">TX Optical:</span>
                <strong className="font-mono text-gray-900 dark:text-white">
                  {managingOnu.tx_power != null ? `${managingOnu.tx_power} dBm` : "—"}
                </strong>
              </div>
              <div className="flex items-center justify-between">
                <span className="text-gray-500 dark:text-gray-400">Jarak Fiber:</span>
                <strong className="font-mono text-gray-900 dark:text-white">
                  {managingOnu.distance != null ? `${(managingOnu.distance / 1000).toFixed(2)} km (${managingOnu.distance} m)` : "—"}
                </strong>
              </div>
              <div className="flex items-center justify-between">
                <span className="text-gray-500 dark:text-gray-400">Suhu Perangkat:</span>
                <strong className="font-mono text-gray-900 dark:text-white">
                  {managingOnu.temperature != null ? `${managingOnu.temperature}°C` : "—"}
                </strong>
              </div>
              <div className="flex items-center justify-between">
                <span className="text-gray-500 dark:text-gray-400">Pelanggan Terhubung:</span>
                <strong className="text-brand-600 dark:text-brand-400 truncate max-w-[180px]">
                  {managingOnu.customer_name || "Belum Dihubungkan"}
                </strong>
              </div>
              <div className="flex items-center justify-between">
                <span className="text-gray-500 dark:text-gray-400">Terakhir Sinkron:</span>
                <strong className="text-gray-900 dark:text-white">{managingOnu.last_sync || "Baru saja"}</strong>
              </div>
            </div>

            {/* Actions List */}
            <div className="space-y-2">
              <p className="text-xs font-bold text-gray-500 uppercase tracking-wider">Aksi &amp; Konfigurasi</p>

              <button
                type="button"
                onClick={() => {
                  const onu = managingOnu
                  setManagingOnu(null)
                  setLinkingOnu(onu)
                  setCustomerSearch("")
                }}
                className="w-full flex items-center justify-between px-4 py-2.5 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:hover:bg-gray-800 text-gray-700 dark:text-gray-200 text-xs font-semibold transition shadow-2xs cursor-pointer"
              >
                <div className="flex items-center gap-2.5">
                  <LinkIcon className="h-4 w-4 text-brand-500" />
                  <span>{managingOnu.customer_name ? "Ubah Pelanggan Terhubung" : "Hubungkan ke Pelanggan"}</span>
                </div>
                <span className="text-[11px] text-brand-600 font-bold">Pilih</span>
              </button>

              {managingOnu.customer_name && (
                <button
                  type="button"
                  onClick={() => {
                    const onu = managingOnu
                    if (confirm(`Putus relasi pelanggan "${onu.customer_name}" dari ONU ${onu.name}?`)) {
                      setManagingOnu(null)
                      linkCustomerToOnu(null, onu.id)
                    }
                  }}
                  className="w-full flex items-center justify-between px-4 py-2.5 rounded-xl border border-rose-200 bg-rose-50 hover:bg-rose-100 dark:border-rose-900/30 dark:bg-rose-950/20 text-rose-600 dark:text-rose-400 text-xs font-semibold transition shadow-2xs cursor-pointer"
                >
                  <div className="flex items-center gap-2.5">
                    <Unlink className="h-4 w-4" />
                    <span>Putus Relasi Pelanggan ({managingOnu.customer_name})</span>
                  </div>
                  <span className="text-[11px] text-rose-500">Putus</span>
                </button>
              )}

              <button
                type="button"
                onClick={() => rebootOnu(managingOnu.id, managingOnu.name)}
                className="w-full flex items-center justify-between px-4 py-2.5 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:hover:bg-gray-800 text-gray-700 dark:text-gray-200 text-xs font-semibold transition shadow-2xs cursor-pointer"
              >
                <div className="flex items-center gap-2.5">
                  <RotateCcw className="h-4 w-4 text-amber-500" />
                  <span>Reboot Perangkat ONU</span>
                </div>
                <span className="text-[11px] text-gray-400">Reboot</span>
              </button>

              <button
                type="button"
                onClick={() => deleteOnu(managingOnu.id, managingOnu.name)}
                className="w-full flex items-center justify-between px-4 py-2.5 rounded-xl bg-rose-500 hover:bg-rose-600 text-white text-xs font-bold transition shadow-xs cursor-pointer"
              >
                <div className="flex items-center gap-2.5">
                  <Trash2 className="h-4 w-4" />
                  <span>Hapus Data ONU</span>
                </div>
                <span className="text-[11px] text-white/80">Hapus</span>
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Modal: Hubungkan ONU ke Pelanggan */}
      <Modal
        isOpen={!!linkingOnu}
        onClose={() => setLinkingOnu(null)}
        title="Hubungkan ONU ke Pelanggan"
        description={`Pilih pelanggan yang menggunakan ONU ${linkingOnu?.name || ""}`}
        maxWidth="lg"
        footer={
          <button
            type="button"
            onClick={() => setLinkingOnu(null)}
            className="px-5 py-2 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 text-xs font-semibold text-gray-700 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800 transition shadow-2xs"
          >
            Tutup
          </button>
        }
      >
        <div className="space-y-4 py-1 text-xs">
          {linkingOnu?.customer_name && (
            <div className="rounded-xl border border-rose-200 bg-rose-50 p-3 flex items-center justify-between dark:border-rose-900/30 dark:bg-rose-950/20">
              <div>
                <div className="text-[11px] text-gray-500 dark:text-gray-400">Saat ini terhubung dengan</div>
                <div className="font-bold text-rose-700 dark:text-rose-300 text-xs mt-0.5">{linkingOnu.customer_name}</div>
              </div>
              <button
                type="button"
                onClick={() => {
                  if (confirm(`Putus relasi pelanggan "${linkingOnu.customer_name}" dari ONU ${linkingOnu.name}?`)) {
                    linkCustomerToOnu(null, linkingOnu.id)
                  }
                }}
                className="flex items-center gap-1.5 h-8 px-3 rounded-lg border border-rose-200 bg-white hover:bg-rose-100 text-rose-600 dark:border-rose-900/40 dark:bg-rose-900/30 dark:text-rose-300 text-[11px] font-bold transition active:scale-95 shadow-2xs whitespace-nowrap"
              >
                <Unlink className="h-3.5 w-3.5" />
                <span>Putus Relasi</span>
              </button>
            </div>
          )}

          {/* Search */}
          <div className="relative">
            <Search className="pointer-events-none absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-gray-400" />
            <input
              type="text"
              value={customerSearch}
              onChange={(e) => setCustomerSearch(e.target.value)}
              placeholder="Cari nama pelanggan, PPPoE username, atau no HP..."
              className="h-10 w-full rounded-xl border border-gray-200 bg-white pl-9 pr-3.5 text-xs text-gray-900 placeholder:text-gray-400 focus:outline-none focus:border-brand-500 dark:border-gray-800 dark:bg-gray-900 dark:text-white dark:placeholder:text-gray-500 font-medium"
            />
          </div>

          {/* Customer Selection List */}
          <div className="max-h-64 overflow-y-auto custom-scrollbar space-y-1.5 pr-1">
            {filteredCustomerList.length === 0 ? (
              <div className="text-center py-6 text-gray-400 dark:text-gray-500 text-xs">
                Tidak ada pelanggan ditemukan.
              </div>
            ) : (
              filteredCustomerList.map((cust) => {
                const isCurrent = linkingOnu?.customer_id === cust.id
                return (
                  <div
                    key={cust.id}
                    onClick={() => linkCustomerToOnu(cust.id, linkingOnu?.id)}
                    className={cn(
                      "flex items-center justify-between p-3 rounded-xl border transition cursor-pointer",
                      isCurrent
                        ? "border-brand-500 bg-brand-50 dark:border-brand-500/50 dark:bg-brand-500/10"
                        : "border-gray-200 bg-white hover:border-gray-300 hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:hover:bg-gray-800/80"
                    )}
                  >
                    <div>
                      <div className="font-bold text-gray-900 dark:text-white text-xs">{cust.name}</div>
                      <div className="text-[11px] text-gray-500 dark:text-gray-400 font-mono flex items-center gap-2 mt-0.5">
                        {cust.pppoe_username && <span>PPPoE: {cust.pppoe_username}</span>}
                        {cust.phone && <span>• {cust.phone}</span>}
                      </div>
                    </div>

                    {isCurrent ? (
                      <span className="flex h-6 w-6 items-center justify-center rounded-full bg-brand-500 text-white whitespace-nowrap">
                        <Check className="h-3.5 w-3.5" />
                      </span>
                    ) : (
                      <button
                        type="button"
                        className="h-7 px-2.5 rounded-lg border border-gray-200 bg-gray-50 text-gray-700 text-[11px] font-bold hover:bg-gray-100 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"
                      >
                        Pilih
                      </button>
                    )}
                  </div>
                )
              })
            )}
          </div>
        </div>
      </Modal>
    </AppLayout>
  )
}
