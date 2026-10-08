import { useState, useEffect, useRef, useMemo, useCallback } from "react"
import { AppLayout } from "@/components/layout/app-layout"
import {
  Server,
  Activity,
  RefreshCw,
  Cpu,
  HardDrive,
  Clock,
  Wifi,
  ShieldCheck,
  Radio,
  FileText,
  Terminal,
  ArrowUpDown,
  Search,
  CheckCircle2,
  X,
  XCircle,
  AlertTriangle,
  Play,
  Pause,
  RotateCcw,
  Power,
  ChevronDown,
  ChevronUp,
  Layers,
  ArrowRight,
  TrendingUp,
  TrendingDown,
  Info,
  ExternalLink,
  Users,
  Eye,
  Sliders,
} from "lucide-react"
import { Head, router as inertiaRouter } from "@inertiajs/react"
import Modal from "@/components/tailadmin/Modal"
import { cn } from "@/lib/utils"
import axios from "axios"
import Chart from "react-apexcharts"
import type { ApexOptions } from "apexcharts"

interface Props {
  router: {
    id: number
    name: string
    host?: string
    ip_address?: string
    port?: number
    api_port?: number
    username?: string
    is_active?: boolean
    auto_isolate?: boolean
    vpn_type?: string
    created_at?: string
    last_seen_at?: string
  }
  resource?: any
  interfaces?: any[]
  users?: any[]
  profiles?: any[]
  active?: any[]
  hotspotUsers?: any[]
  hotspotActive?: any[]
  arpEntries?: any[]
  error?: string | null
  brand?: {
    name?: string
    logo?: string
  }
  sidebarItems?: any[]
  navItems?: any[]
}

type TabType = "interfaces" | "pppoe" | "hotspot" | "dhcp" | "ip" | "logs" | "tools"

interface TrafficHistoryPoint {
  time: string
  rx: number
  tx: number
}

export default function MikrotikRouterDetail({
  router,
  resource,
  interfaces: initialInterfaces = [],
  users = [],
  profiles = [],
  active = [],
  hotspotUsers = [],
  hotspotActive: initialHotspotActive = [],
  arpEntries = [],
  error: serverError,
  brand,
  sidebarItems,
  navItems
}: Props) {
  const hostAddress = router.host || router.ip_address || "-"
  const hostPort = router.port || router.api_port || 8728

  const [activeTab, setActiveTab] = useState<TabType>("interfaces")
  const [loadingResources, setLoadingResources] = useState<boolean>(false)
  const [resources, setResources] = useState<any>(resource || null)
  
  // Data tabs initialized from server Inertia props
  const [interfaces, setInterfaces] = useState<any[]>(initialInterfaces)
  const [pppoeSecrets, setPppoeSecrets] = useState<any[]>(users)
  const [pppoeActive, setPppoeActive] = useState<any[]>(active)
  const [hotspotActive, setHotspotActive] = useState<any[]>(initialHotspotActive)
  const [dhcpLeases, setDhcpLeases] = useState<any[]>(arpEntries)
  const [ipAddresses, setIpAddresses] = useState<any[]>([])
  const [logs, setLogs] = useState<any[]>([])
  
  // Traffic monitor state
  const [selectedInterface, setSelectedInterface] = useState<string>("")
  const [trafficRxRate, setTrafficRxRate] = useState<number>(0)
  const [trafficTxRate, setTrafficTxRate] = useState<number>(0)
  const [trafficHistory, setTrafficHistory] = useState<TrafficHistoryPoint[]>([])
  const [isTrafficPolling, setIsTrafficPolling] = useState<boolean>(true)
  
  // Tools state
  const [pingHost, setPingHost] = useState<string>("")
  const [pingCount, setPingCount] = useState<number>(4)
  const [pingRunning, setPingRunning] = useState<boolean>(false)
  const [pingResults, setPingResults] = useState<string[]>([])
  
  // Search & Filter
  const [searchQuery, setSearchQuery] = useState<string>("")
  const [filterType, setFilterType] = useState<string>("all")
  
  // Modals & Action confirmations
  const [isRebootModalOpen, setIsRebootModalOpen] = useState<boolean>(false)
  const [rebooting, setRebooting] = useState<boolean>(false)
  const [actionSuccess, setActionSuccess] = useState<string | null>(null)
  const [actionError, setActionError] = useState<string | null>(null)

  // Polling ref
  const trafficIntervalRef = useRef<NodeJS.Timeout | null>(null)

  // Sync server props when refreshed
  useEffect(() => {
    if (resource) setResources(resource)
  }, [resource])

  useEffect(() => {
    if (initialInterfaces) {
      setInterfaces(initialInterfaces)
      if (!selectedInterface && initialInterfaces.length > 0) {
        const defaultIf = initialInterfaces.find((i: any) => !i.disabled && (i.name?.includes("ether1") || i.name?.includes("sfp") || i.type === "ether" || i.running)) || initialInterfaces[0]
        if (defaultIf) setSelectedInterface(defaultIf.name)
      }
    }
  }, [initialInterfaces])

  useEffect(() => {
    if (users) setPppoeSecrets(users)
  }, [users])

  useEffect(() => {
    if (active) setPppoeActive(active)
  }, [active])

  useEffect(() => {
    if (initialHotspotActive) setHotspotActive(initialHotspotActive)
  }, [initialHotspotActive])

  useEffect(() => {
    if (arpEntries) setDhcpLeases(arpEntries)
  }, [arpEntries])

  // Fetch Resources Info / Reload Inertia
  const fetchResources = useCallback(() => {
    setLoadingResources(true)
    inertiaRouter.reload({
      onFinish: () => setLoadingResources(false)
    })
  }, [])

  // Fetch Tab Specific Data
  const fetchTabData = useCallback((tab: TabType) => {
    fetchResources()
  }, [fetchResources])

  // Poll Traffic for selected interface via server route
  const pollTraffic = useCallback(async () => {
    if (!selectedInterface || !isTrafficPolling) return
    try {
      const res = await axios.get(`/admin/mikrotik/router/${router.id}/traffic`, {
        params: { interface: selectedInterface }
      })
      if (res.data?.success) {
        const rx = Number(res.data.rx_bps || 0)
        const tx = Number(res.data.tx_bps || 0)
        setTrafficRxRate(rx)
        setTrafficTxRate(tx)
        
        const now = res.data.timestamp || new Date().toLocaleTimeString("id-ID", { hour12: false })
        setTrafficHistory(prev => {
          const next = [...prev, { time: now, rx, tx }]
          return next.slice(-20) // Keep last 20 ticks
        })
      }
    } catch (err) {
      // Traffic polling error
    }
  }, [router.id, selectedInterface, isTrafficPolling])

  useEffect(() => {
    if (activeTab === "interfaces" && selectedInterface && isTrafficPolling) {
      pollTraffic()
      trafficIntervalRef.current = setInterval(pollTraffic, 2000)
      return () => {
        if (trafficIntervalRef.current) clearInterval(trafficIntervalRef.current)
      }
    }
  }, [activeTab, selectedInterface, isTrafficPolling, pollTraffic])

  const handleTabChange = (newTab: TabType) => {
    setActiveTab(newTab)
    setSearchQuery("")
    setFilterType("all")
    fetchTabData(newTab)
  }

  // Format Helper
  const formatBytes = (bytes: number | string | undefined): string => {
    const b = Number(bytes)
    if (isNaN(b) || b <= 0) return "0 B"
    const units = ["B", "KB", "MB", "GB", "TB"]
    const i = Math.floor(Math.log(b) / Math.log(1024))
    return `${(b / Math.pow(1024, i)).toFixed(2)} ${units[i]}`
  }

  const formatBps = (bps: number): string => {
    if (isNaN(bps) || bps <= 0) return "0 bps"
    if (bps >= 1_000_000_000) return `${(bps / 1_000_000_000).toFixed(2)} Gbps`
    if (bps >= 1_000_000) return `${(bps / 1_000_000).toFixed(2)} Mbps`
    if (bps >= 1_000) return `${(bps / 1_000).toFixed(2)} Kbps`
    return `${bps.toFixed(0)} bps`
  }

  // Handle Reboot
  const handleReboot = async () => {
    setRebooting(true)
    try {
      const res = await axios.post(`/admin/mikrotik-routers/${router.id}/reboot`)
      if (res.data?.success) {
        setActionSuccess("Perintah Reboot MikroTik berhasil dikirim.")
        setIsRebootModalOpen(false)
      } else {
        setActionError(res.data?.message || "Gagal mengirim perintah reboot.")
      }
    } catch (err: any) {
      setActionError(err?.response?.data?.message || "Koneksi ke router gagal saat reboot.")
    } finally {
      setRebooting(false)
    }
  }

  // Disconnect PPPoE Active
  const handleDisconnectPppoe = async (id: string, name: string) => {
    if (!confirm(`Putuskan sesi PPPoE aktif untuk user "${name}"?`)) return
    try {
      const res = await axios.post(`/admin/mikrotik-routers/${router.id}/pppoe-disconnect`, { id })
      if (res.data?.success) {
        setActionSuccess(`Sesi user "${name}" berhasil diputus.`)
        fetchTabData("pppoe")
      } else {
        setActionError(res.data?.message || "Gagal memutuskan sesi.")
      }
    } catch (err: any) {
      setActionError(err?.response?.data?.message || "Terjadi kesalahan saat memutus sesi.")
    }
  }

  // Disconnect Hotspot Active
  const handleDisconnectHotspot = async (id: string, user: string) => {
    if (!confirm(`Keluarkan user Hotspot "${user}" dari sesi aktif?`)) return
    try {
      const res = await axios.post(`/admin/mikrotik-routers/${router.id}/hotspot-disconnect`, { id })
      if (res.data?.success) {
        setActionSuccess(`User "${user}" berhasil di-logout.`)
        fetchTabData("hotspot")
      } else {
        setActionError(res.data?.message || "Gagal me-logout user.")
      }
    } catch (err: any) {
      setActionError(err?.response?.data?.message || "Terjadi kesalahan saat logout user.")
    }
  }

  // Run Ping Tool
  const handleRunPing = async (e: React.FormEvent) => {
    e.preventDefault()
    if (!pingHost) return
    setPingRunning(true)
    setPingResults([`Memulai ping ke ${pingHost} (${pingCount} packet)...`])
    try {
      const res = await axios.post(`/admin/mikrotik-routers/${router.id}/ping`, {
        host: pingHost,
        count: pingCount
      })
      if (res.data?.success && Array.isArray(res.data.data)) {
        const formatted = res.data.data.map((p: any, idx: number) => {
          return `#${idx + 1} Host: ${p.host || pingHost} | Time: ${p.time || p["round-trip-time"] || "timeout"} | TTL: ${p.ttl || "-"} | Status: ${p.status || "OK"}`
        })
        setPingResults(formatted)
      } else {
        setPingResults([res.data?.message || "Ping gagal dieksekusi."])
      }
    } catch (err: any) {
      setPingResults([`Error: ${err?.response?.data?.message || "Gagal menghubungi API router."}`])
    } finally {
      setPingRunning(false)
    }
  }

  // Computed CPU, Memory & Storage
  const cpuLoad = Number(resources?.cpu_load ?? resources?.["cpu-load"] ?? 0)
  const freeMem = Number(resources?.free_memory ?? resources?.["free-memory"] ?? 0)
  const totalMem = Number(resources?.total_memory ?? resources?.["total-memory"] ?? 0)
  const memUsagePercent = Number(resources?.memory_percent ?? (totalMem > 0 ? Math.round(((totalMem - freeMem) / totalMem) * 100) : 0))
  
  const freeHdd = Number(resources?.free_hdd ?? resources?.["free-hdd-space"] ?? 0)
  const totalHdd = Number(resources?.total_hdd ?? resources?.["total-hdd-space"] ?? 0)
  const hddUsagePercent = Number(resources?.hdd_percent ?? (totalHdd > 0 ? Math.round(((totalHdd - freeHdd) / totalHdd) * 100) : 0))

  return (
    <AppLayout
      title={`Router — ${router.name}`}
      brand={brand}
      sidebarItems={sidebarItems}
      navItems={navItems}
    >
      <Head title={`Router ${router.name} — Detail Telemetri`} />

      <div className="space-y-4 sm:space-y-6">
        {/* Banner Alert Feedback */}
        {actionSuccess && (
          <div className="flex items-center justify-between gap-3 rounded-xl border border-emerald-200 bg-emerald-50/90 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900/50 dark:bg-emerald-950/40 dark:text-emerald-300">
            <div className="flex items-center gap-2 font-medium">
              <CheckCircle2 className="size-4 shrink-0 text-emerald-600 dark:text-emerald-400" />
              {actionSuccess}
            </div>
            <button
              onClick={() => setActionSuccess(null)}
              className="text-xs font-semibold text-emerald-700 hover:text-emerald-900 dark:text-emerald-400 dark:hover:text-emerald-200"
            >
              Tutup
            </button>
          </div>
        )}

        {actionError && (
          <div className="flex items-center justify-between gap-3 rounded-xl border border-rose-200 bg-rose-50/90 px-4 py-3 text-sm text-rose-800 dark:border-rose-900/50 dark:bg-rose-950/40 dark:text-rose-300">
            <div className="flex items-center gap-2 font-medium">
              <AlertTriangle className="size-4 shrink-0 text-rose-600 dark:text-rose-400" />
              {actionError}
            </div>
            <button
              onClick={() => setActionError(null)}
              className="text-xs font-semibold text-rose-700 hover:text-rose-900 dark:text-rose-400 dark:hover:text-rose-200"
            >
              Tutup
            </button>
          </div>
        )}

        {serverError && !actionError && (
          <div className="flex items-center justify-between gap-3 rounded-xl border border-amber-200 bg-amber-50/90 px-4 py-3 text-sm text-amber-800 dark:border-amber-900/50 dark:bg-amber-950/40 dark:text-amber-300">
            <div className="flex items-center gap-2 font-medium">
              <AlertTriangle className="size-4 shrink-0 text-amber-600 dark:text-amber-400" />
              <span>Status Koneksi: {serverError}</span>
            </div>
          </div>
        )}

        {/* Top Header Card */}
        <div className="rounded-2xl border border-gray-200 bg-white p-4 sm:p-5 shadow-xs dark:border-gray-800 dark:bg-white/[0.03]">
          <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
              <div className="flex items-center gap-2">
                <h2 className="text-lg font-bold text-gray-900 dark:text-white">
                  {router.name}
                </h2>
                {resources?.uptime || resources?.online ? (
                  <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold bg-emerald-500 text-white shadow-xs">
                    Online
                  </span>
                ) : (
                  <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold bg-rose-500 text-white shadow-xs">
                    Offline
                  </span>
                )}
                {router.auto_isolate && (
                  <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold bg-blue-500 text-white shadow-xs">
                    Auto-Isolir
                  </span>
                )}
              </div>
              <p className="mt-1 text-xs text-gray-500 dark:text-gray-400 font-mono">
                Host: <span className="font-semibold text-gray-700 dark:text-gray-300">{hostAddress}:{hostPort}</span>
                {resources?.board_name && <> • Model: <span className="font-semibold text-gray-700 dark:text-gray-300">{resources.board_name}</span></>}
                {resources?.version && <> • RouterOS: <span className="font-semibold text-gray-700 dark:text-gray-300">v{resources.version}</span></>}
                {resources?.uptime && <> • Uptime: <span className="font-semibold text-gray-700 dark:text-gray-300">{resources.uptime}</span></>}
              </p>
            </div>

            <div className="flex items-center gap-2 shrink-0">
              <button
                type="button"
                onClick={() => fetchResources()}
                className="inline-flex h-9 items-center gap-1.5 px-3 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:hover:bg-gray-800 text-xs font-semibold text-gray-700 dark:text-gray-200 transition shadow-2xs cursor-pointer"
              >
                <RefreshCw className={cn("size-3.5 text-brand-500", loadingResources && "animate-spin")} />
                <span>Segarkan</span>
              </button>

              <button
                type="button"
                onClick={() => setIsRebootModalOpen(true)}
                className="inline-flex h-9 items-center gap-1.5 px-3 rounded-xl bg-rose-500 hover:bg-rose-600 active:scale-95 text-xs font-bold text-white shadow-xs transition cursor-pointer"
              >
                <Power className="size-3.5" />
                <span>Reboot</span>
              </button>

              <button
                type="button"
                onClick={() => inertiaRouter.visit("/admin/mikrotik/routers")}
                className="inline-flex h-9 items-center gap-1.5 px-3 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:hover:bg-gray-800 text-xs font-semibold text-gray-700 dark:text-gray-200 transition shadow-2xs cursor-pointer"
              >
                <span>Kembali</span>
              </button>
            </div>
          </div>

          {/* Conditional Telemetry Strip (Only when online and resources available) */}
          {resources?.uptime && (
            <div className="mt-4 pt-4 border-t border-gray-100 dark:border-gray-800/80 grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-6 gap-3">
              <div className="rounded-xl border border-gray-100 bg-gray-50/60 p-2.5 dark:border-gray-800 dark:bg-gray-900/40">
                <span className="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Beban CPU</span>
                <div className="mt-1 flex items-baseline gap-1.5">
                  <span className="text-base font-bold text-gray-900 dark:text-white tabular-nums">{cpuLoad}%</span>
                  <span className="text-[10px] text-gray-400">{resources.cpu || "CPU"}</span>
                </div>
              </div>

              <div className="rounded-xl border border-gray-100 bg-gray-50/60 p-2.5 dark:border-gray-800 dark:bg-gray-900/40">
                <span className="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Penggunaan RAM</span>
                <div className="mt-1 flex items-baseline gap-1.5">
                  <span className="text-base font-bold text-gray-900 dark:text-white tabular-nums">{memUsagePercent}%</span>
                  <span className="text-[10px] text-gray-400">({formatBytes(freeMem)} free)</span>
                </div>
              </div>

              <div className="rounded-xl border border-gray-100 bg-gray-50/60 p-2.5 dark:border-gray-800 dark:bg-gray-900/40">
                <span className="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Penyimpanan HDD</span>
                <div className="mt-1 flex items-baseline gap-1.5">
                  <span className="text-base font-bold text-gray-900 dark:text-white tabular-nums">{hddUsagePercent}%</span>
                  <span className="text-[10px] text-gray-400">({formatBytes(freeHdd)} free)</span>
                </div>
              </div>

              <div className="rounded-xl border border-gray-100 bg-gray-50/60 p-2.5 dark:border-gray-800 dark:bg-gray-900/40">
                <span className="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Arsitektur</span>
                <div className="mt-1 text-sm font-bold text-gray-900 dark:text-white truncate">
                  {resources.architecture_name || "-"}
                </div>
              </div>

              <div className="rounded-xl border border-gray-100 bg-gray-50/60 p-2.5 dark:border-gray-800 dark:bg-gray-900/40">
                <span className="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Suhu / Voltase</span>
                <div className="mt-1 text-sm font-bold text-gray-900 dark:text-white">
                  {resources.temperature || resources["cpu-temperature"] ? `${resources.temperature || resources["cpu-temperature"]}°C` : "-"}
                  {resources.voltage ? ` / ${resources.voltage}V` : ""}
                </div>
              </div>

              <div className="rounded-xl border border-gray-100 bg-gray-50/60 p-2.5 dark:border-gray-800 dark:bg-gray-900/40">
                <span className="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Status VPN</span>
                <div className="mt-1 text-xs font-bold text-gray-900 dark:text-white truncate">
                  {router.vpn_type || "Direct API"}
                </div>
              </div>
            </div>
          )}
        </div>

        {/* Master Card with Clean Underline Tabs */}
        <div className="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xs dark:border-gray-800 dark:bg-white/[0.03]">
          {/* Underline Tabs Header (Scrollable on mobile) */}
          <div className="border-b border-gray-200 dark:border-gray-800 px-4 sm:px-6 bg-white dark:bg-gray-900/50">
            <div className="flex items-center justify-between gap-4 overflow-x-auto table-scrollbar">
              <nav className="flex space-x-1 sm:space-x-4 min-w-max py-2">
                {[
                  { id: "interfaces", label: "Interface & Trafik", icon: Activity, count: interfaces.length },
                  { id: "pppoe", label: "PPPoE Secret & Aktif", icon: ShieldCheck, count: `${pppoeActive.length}/${pppoeSecrets.length}` },
                  { id: "hotspot", label: "Hotspot Aktif", icon: Wifi, count: hotspotActive.length },
                  { id: "dhcp", label: "DHCP Leases", icon: Server, count: dhcpLeases.length },
                  { id: "ip", label: "IP Addresses", icon: Layers, count: ipAddresses.length },
                  { id: "logs", label: "Log Router", icon: FileText },
                  { id: "tools", label: "Tools & Ping", icon: Terminal },
                ].map((tab) => {
                  const Icon = tab.icon
                  const isActive = activeTab === tab.id
                  return (
                    <button
                      key={tab.id}
                      type="button"
                      onClick={() => handleTabChange(tab.id as TabType)}
                      className={cn(
                        "flex items-center gap-2 py-2 px-3 text-xs sm:text-sm font-semibold border-b-2 transition-all cursor-pointer whitespace-nowrap",
                        isActive
                          ? "border-brand-500 text-brand-600 dark:text-brand-400 font-bold"
                          : "border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
                      )}
                    >
                      <Icon className="size-4" />
                      <span>{tab.label}</span>
                      {tab.count !== undefined && (
                        <span className={cn(
                          "rounded-full px-2 py-0.2 text-[10px] font-bold",
                          isActive
                            ? "bg-brand-50 text-brand-600 dark:bg-brand-950 dark:text-brand-300"
                            : "bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400"
                        )}>
                          {tab.count}
                        </span>
                      )}
                    </button>
                  )
                })}
              </nav>

              <button
                type="button"
                onClick={() => fetchTabData(activeTab)}
                className="inline-flex items-center gap-1.5 rounded-xl border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 shadow-2xs hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800 transition cursor-pointer shrink-0"
              >
                <RefreshCw className="size-3.5 text-brand-500" />
                <span className="hidden sm:inline">Segarkan Tab</span>
              </button>
            </div>
          </div>

          {/* TAB 1: INTERFACES & TRAFFIC */}
          {activeTab === "interfaces" && (
            <div className="p-4 sm:p-6 space-y-6">
              {/* Traffic Live Graph Card */}
              <div className="rounded-xl border border-gray-200 bg-gray-50/50 p-4 sm:p-5 dark:border-gray-800 dark:bg-gray-900/30">
                <div className="flex flex-wrap items-center justify-between gap-3 mb-4">
                  <div className="flex items-center gap-3">
                    <div className="rounded-lg bg-brand-500/10 p-2 text-brand-600 dark:text-brand-400 whitespace-nowrap">
                      <ArrowUpDown className="size-5" />
                    </div>
                    <div>
                      <h4 className="text-sm font-bold text-gray-900 dark:text-white">
                        Live Bandwidth Monitor — {selectedInterface || "Pilih Interface"}
                      </h4>
                      <p className="text-xs text-gray-500 dark:text-gray-400">
                        Kecepatan transmisi data real-time interval 2 detik
                      </p>
                    </div>
                  </div>

                  <div className="flex items-center gap-2">
                    {/* Interface Selector Dropdown */}
                    <select
                      value={selectedInterface}
                      onChange={(e) => {
                        setSelectedInterface(e.target.value)
                        setTrafficHistory([])
                      }}
                      className="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold text-gray-900 shadow-xs focus:border-brand-500 focus:outline-hidden dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                    >
                      {interfaces.map((ifItem) => (
                        <option key={ifItem[".id"] || ifItem.name} value={ifItem.name}>
                          {ifItem.name} ({ifItem.type})
                        </option>
                      ))}
                    </select>

                    <button
                      type="button"
                      onClick={() => setIsTrafficPolling(!isTrafficPolling)}
                      className={`inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold shadow-xs transition-colors ${
                        isTrafficPolling
                          ? "bg-emerald-600 text-white hover:bg-emerald-700"
                          : "bg-gray-200 text-gray-700 hover:bg-gray-300 dark:bg-gray-700 dark:text-gray-200"
                      }`}
                    >
                      {isTrafficPolling ? <Pause className="size-3.5" /> : <Play className="size-3.5" />}
                      {isTrafficPolling ? "Jeda" : "Mulai"}
                    </button>
                  </div>
                </div>

                {/* Rate Indicators */}
                <div className="grid grid-cols-2 gap-3 sm:grid-cols-4 mb-4">
                  <div className="rounded-lg border border-emerald-200/60 bg-emerald-50/50 p-3 dark:border-emerald-900/40 dark:bg-emerald-950/20 whitespace-nowrap">
                    <p className="text-[11px] font-medium text-emerald-800 dark:text-emerald-300">RX (Download / In)</p>
                    <p className="mt-1 text-lg font-bold font-mono text-emerald-700 dark:text-emerald-400">
                      {formatBps(trafficRxRate)}
                    </p>
                  </div>
                  <div className="rounded-lg border border-blue-200/60 bg-blue-50/50 p-3 dark:border-blue-900/40 dark:bg-blue-950/20">
                    <p className="text-[11px] font-medium text-blue-800 dark:text-blue-300">TX (Upload / Out)</p>
                    <p className="mt-1 text-lg font-bold font-mono text-blue-700 dark:text-blue-400">
                      {formatBps(trafficTxRate)}
                    </p>
                  </div>
                  <div className="rounded-lg border border-gray-200 bg-white p-3 dark:border-gray-800 dark:bg-gray-800">
                    <p className="text-[11px] font-medium text-gray-500 dark:text-gray-400">Interface Type</p>
                    <p className="mt-1 text-sm font-bold text-gray-900 dark:text-white capitalize">
                      {interfaces.find(i => i.name === selectedInterface)?.type || "ether"}
                    </p>
                  </div>
                  <div className="rounded-lg border border-gray-200 bg-white p-3 dark:border-gray-800 dark:bg-gray-800">
                    <p className="text-[11px] font-medium text-gray-500 dark:text-gray-400">MAC Address</p>
                    <p className="mt-1 text-xs font-mono font-semibold text-gray-700 dark:text-gray-300">
                      {interfaces.find(i => i.name === selectedInterface)?.mac_address || "-"}
                    </p>
                  </div>
                </div>

                {/* Live Realtime Apex Area Spline Chart */}
                <div className="rounded-xl border border-gray-200 bg-white p-3 dark:border-gray-800 dark:bg-gray-900/60 shadow-2xs">
                  {trafficHistory.length === 0 ? (
                    <div className="h-[220px] w-full flex flex-col items-center justify-center gap-2 text-xs text-gray-400">
                      <Activity className="size-5 text-brand-500 animate-pulse" />
                      <span>Mengumpulkan telemetri realtime trafik data...</span>
                    </div>
                  ) : (
                    <Chart
                      options={{
                        chart: {
                          type: "area",
                          height: 220,
                          fontFamily: "Outfit, Inter, sans-serif",
                          toolbar: { show: false },
                          animations: {
                            enabled: true,
                            easing: "linear",
                            dynamicAnimation: { speed: 1000 }
                          },
                          sparkline: { enabled: false },
                          zoom: { enabled: false }
                        },
                        colors: ["#10B981", "#3B82F6"],
                        fill: {
                          type: "gradient",
                          gradient: {
                            type: "vertical",
                            shadeIntensity: 1,
                            opacityFrom: 0.4,
                            opacityTo: 0.03,
                            stops: [0, 90, 100]
                          }
                        },
                        stroke: {
                          curve: "smooth",
                          width: 2.5
                        },
                        dataLabels: { enabled: false },
                        grid: {
                          borderColor: "#e5e7eb",
                          strokeDashArray: 4,
                          padding: { top: 10, right: 15, bottom: 0, left: 15 }
                        },
                        xaxis: {
                          categories: trafficHistory.map(p => p.time),
                          labels: {
                            show: true,
                            rotate: 0,
                            style: { colors: "#9ca3af", fontSize: "10px", fontWeight: 600 }
                          },
                          axisBorder: { show: false },
                          axisTicks: { show: false },
                          tooltip: { enabled: false }
                        },
                        yaxis: {
                          labels: {
                            formatter: (val) => formatBps(val),
                            style: { colors: "#9ca3af", fontSize: "10px", fontWeight: 600 }
                          }
                        },
                        tooltip: {
                          theme: "dark",
                          x: { show: true },
                          y: {
                            formatter: (val) => formatBps(val)
                          }
                        },
                        legend: {
                          position: "top",
                          horizontalAlign: "right",
                          fontSize: "12px",
                          fontWeight: 600,
                          labels: { colors: "#6b7280" },
                          markers: { size: 5 }
                        }
                      }}
                      series={[
                        {
                          name: "RX (Download / In)",
                          data: trafficHistory.map(p => p.rx)
                        },
                        {
                          name: "TX (Upload / Out)",
                          data: trafficHistory.map(p => p.tx)
                        }
                      ]}
                      type="area"
                      height={220}
                    />
                  )}
                </div>
              </div>

              {/* Interfaces Table */}
              <div className="space-y-3">
                <div className="flex items-center justify-between">
                  <h4 className="text-sm font-bold text-gray-900 dark:text-white">
                    Daftar Interface Fisik &amp; Virtual ({interfaces.length})
                  </h4>
                </div>

                <div className="w-full overflow-x-auto table-scrollbar">
                  <table className="w-full min-w-[1000px] text-left text-xs whitespace-nowrap border-collapse">
                    <thead className="border-b border-gray-200 bg-gray-50/75 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:border-gray-800 dark:bg-gray-800/40 dark:text-gray-400">
                      <tr>
                        <th className="px-4 py-3.5">Status</th>
                        <th className="px-4 py-3.5">Nama Interface</th>
                        <th className="px-4 py-3.5">Tipe</th>
                        <th className="px-4 py-3.5">MTU</th>
                        <th className="px-4 py-3.5">Total RX</th>
                        <th className="px-4 py-3.5">Total TX</th>
                        <th className="px-4 py-3.5">Komentar</th>
                        <th className="px-4 py-3.5 text-right">Aksi</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-100 dark:divide-gray-800/60">
                      {interfaces.length === 0 ? (
                        <tr>
                          <td colSpan={8} className="px-4 py-8 text-center text-sm text-gray-500">
                            Tidak ada interface ditemukan atau gagal terhubung ke router.
                          </td>
                        </tr>
                      ) : (
                        interfaces.map((item) => {
                          const isRunning = item.running === true || item.running === "true"
                          const isDisabled = item.disabled === true || item.disabled === "true"

                          return (
                            <tr key={item[".id"] || item.name} className="hover:bg-gray-50/50 dark:hover:bg-white/[0.02] transition-colors">
                              <td className="px-4 py-3.5">
                                {isDisabled ? (
                                  <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold bg-rose-500 text-white shadow-xs whitespace-nowrap">
                                    Disabled
                                  </span>
                                ) : isRunning ? (
                                  <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold bg-emerald-500 text-white shadow-xs whitespace-nowrap">
                                    Running
                                  </span>
                                ) : (
                                  <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold bg-amber-500 text-white shadow-xs whitespace-nowrap">
                                    Link Down
                                  </span>
                                )}
                              </td>
                              <td className="px-4 py-3.5">
                                <div className="font-semibold text-gray-900 dark:text-white">
                                  {item.name}
                                </div>
                                {item.mac_address && (
                                  <div className="text-[11px] font-mono text-gray-500 dark:text-gray-400">
                                    {item.mac_address}
                                  </div>
                                )}
                              </td>
                              <td className="px-4 py-3.5 capitalize text-gray-700 dark:text-gray-300">
                                {item.type}
                              </td>
                              <td className="px-4 py-3.5 font-mono text-xs text-gray-700 dark:text-gray-300">
                                {item.actual_mtu || "1500"}
                              </td>
                              <td className="px-4 py-3.5 font-mono text-xs text-emerald-600 dark:text-emerald-400">
                                {formatBytes(item["rx-byte"])}
                              </td>
                              <td className="px-4 py-3.5 font-mono text-xs text-blue-600 dark:text-blue-400">
                                {formatBytes(item["tx-byte"])}
                              </td>
                              <td className="px-4 py-3.5 text-xs text-gray-500 dark:text-gray-400 max-w-xs truncate">
                                {item.comment || "-"}
                              </td>
                              <td className="px-4 py-3.5 text-right">
                                <button
                                  type="button"
                                  onClick={() => {
                                    setSelectedInterface(item.name)
                                    window.scrollTo({ top: 300, behavior: "smooth" })
                                  }}
                                  className={`inline-flex items-center gap-1 rounded-lg px-2.5 py-1 text-xs font-semibold shadow-xs transition-colors ${
                                    selectedInterface === item.name
                                      ? "bg-brand-600 text-white"
                                      : "border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"
                                  }`}
                                >
                                  <Activity className="size-3" />
                                  Pilih Grafik
                                </button>
                              </td>
                            </tr>
                          )
                        })
                      )}
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          )}

          {/* TAB 2: PPPOE SECRETS & ACTIVE */}
          {activeTab === "pppoe" && (
            <div className="p-4 sm:p-6 space-y-6">
              {/* Filter & Search Toolbar */}
              <div className="flex flex-col gap-2.5 sm:flex-row sm:items-center sm:justify-between">
                <div className="relative w-full sm:max-w-xs">
                  <Search className="pointer-events-none absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-gray-400 dark:text-gray-500" />
                  <input
                    type="text"
                    placeholder="Cari username PPPoE..."
                    value={searchQuery}
                    onChange={(e) => setSearchQuery(e.target.value)}
                    className="h-10 w-full rounded-xl border border-gray-200 bg-gray-50/75 pl-9 pr-8 text-xs text-gray-800 placeholder:text-gray-400 focus:border-brand-500 focus:outline-none dark:border-gray-800 dark:bg-gray-900/50 dark:text-gray-200 dark:placeholder:text-gray-500 font-medium transition"
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
                <div className="flex items-center gap-2">
                  <span className="text-xs font-semibold text-gray-600 dark:text-gray-300">
                    Sesi Aktif: <strong className="text-emerald-600 dark:text-emerald-400">{pppoeActive.length}</strong> | Total Secret: <strong>{pppoeSecrets.length}</strong>
                  </span>
                </div>
              </div>

              {/* Active PPPoE Connections */}
              <div className="space-y-3">
                <h4 className="text-sm font-bold text-gray-900 dark:text-white flex items-center gap-2">
                  <span className="size-2 rounded-full bg-emerald-500 animate-pulse whitespace-nowrap" />
                  Sesi PPPoE Online Saat Ini ({pppoeActive.length})
                </h4>

                <div className="w-full overflow-x-auto table-scrollbar">
                  <table className="w-full min-w-[1000px] text-left text-xs whitespace-nowrap border-collapse">
                    <thead className="border-b border-gray-200 bg-gray-50/75 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:border-gray-800 dark:bg-gray-800/40 dark:text-gray-400">
                      <tr>
                        <th className="px-4 py-3">Status</th>
                        <th className="px-4 py-3">Username</th>
                        <th className="px-4 py-3">IP Address</th>
                        <th className="px-4 py-3">Caller ID (MAC)</th>
                        <th className="px-4 py-3">Uptime</th>
                        <th className="px-4 py-3">Service</th>
                        <th className="px-4 py-3 text-right">Aksi</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-100 dark:divide-gray-800">
                      {pppoeActive.length === 0 ? (
                        <tr>
                          <td colSpan={7} className="px-4 py-6 text-center text-xs text-gray-500">
                            Tidak ada sesi PPPoE yang sedang aktif saat ini.
                          </td>
                        </tr>
                      ) : (
                        pppoeActive
                          .filter(a => a.name.toLowerCase().includes(searchQuery.toLowerCase()) || (a.address && a.address.includes(searchQuery)))
                          .map((act) => (
                            <tr key={act[".id"] || act.name} className="hover:bg-gray-50/50 dark:hover:bg-white/[0.02] transition-colors">
                              <td className="px-4 py-3">
                                <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold bg-emerald-500 text-white shadow-xs whitespace-nowrap">
                                  Online
                                </span>
                              </td>
                              <td className="px-4 py-3 font-semibold text-gray-900 dark:text-white">
                                {act.name}
                              </td>
                              <td className="px-4 py-3 font-mono text-xs font-semibold text-emerald-700 dark:text-emerald-400">
                                {act.address || "-"}
                              </td>
                              <td className="px-4 py-3 font-mono text-xs text-gray-600 dark:text-gray-400">
                                {act.caller_id || "-"}
                              </td>
                              <td className="px-4 py-3 text-xs text-gray-700 dark:text-gray-300 font-medium">
                                {act.uptime || "-"}
                              </td>
                              <td className="px-4 py-3 text-xs text-gray-600 dark:text-gray-400 capitalize">
                                {act.service || "pppoe"}
                              </td>
                              <td className="px-4 py-3 text-right">
                                <button
                                  type="button"
                                  onClick={() => handleDisconnectPppoe(act[".id"], act.name)}
                                  className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-rose-500 hover:bg-rose-600 text-white font-bold text-xs transition shadow-xs cursor-pointer whitespace-nowrap"
                                >
                                  <Power className="size-3" />
                                  Putuskan
                                </button>
                              </td>
                            </tr>
                          ))
                      )}
                    </tbody>
                  </table>
                </div>
              </div>

              {/* Secrets List */}
              <div className="space-y-3 pt-4 border-t border-gray-200 dark:border-gray-800">
                <h4 className="text-sm font-bold text-gray-900 dark:text-white">
                  Daftar PPPoE Secrets / Akun Pelanggan ({pppoeSecrets.length})
                </h4>

                <div className="w-full overflow-x-auto table-scrollbar">
                  <table className="w-full min-w-[1000px] text-left text-xs whitespace-nowrap border-collapse">
                    <thead className="border-b border-gray-200 bg-gray-50/75 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:border-gray-800 dark:bg-gray-800/40 dark:text-gray-400">
                      <tr>
                        <th className="px-4 py-3.5">Status Akun</th>
                        <th className="px-4 py-3.5">Username</th>
                        <th className="px-4 py-3.5">Profile Paket</th>
                        <th className="px-4 py-3.5">Remote Address (IP)</th>
                        <th className="px-4 py-3.5">Komentar</th>
                        <th className="px-4 py-3.5">Terakhir Logout</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-100 dark:divide-gray-800/60">
                      {pppoeSecrets.length === 0 ? (
                        <tr>
                          <td colSpan={6} className="px-4 py-6 text-center text-xs text-gray-500">
                            Tidak ada secret PPPoE yang tersimpan di router.
                          </td>
                        </tr>
                      ) : (
                        pppoeSecrets
                          .filter(s => s.name.toLowerCase().includes(searchQuery.toLowerCase()) || (s.comment && s.comment.toLowerCase().includes(searchQuery.toLowerCase())))
                          .map((sec) => {
                            const isDisabled = sec.disabled === true || sec.disabled === "true"
                            const isOnline = pppoeActive.some(a => a.name === sec.name)

                            return (
                              <tr key={sec[".id"] || sec.name} className="hover:bg-gray-50/50 dark:hover:bg-white/[0.02] transition-colors">
                                <td className="px-4 py-3.5">
                                  {isDisabled ? (
                                    <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold bg-rose-500 text-white shadow-xs whitespace-nowrap">
                                      Disabled
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
                                <td className="px-4 py-3.5 font-semibold text-gray-900 dark:text-white">
                                  {sec.name}
                                </td>
                                <td className="px-4 py-3.5">
                                  <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold bg-brand-500 text-white shadow-xs whitespace-nowrap">
                                    {sec.profile || "default"}
                                  </span>
                                </td>
                                <td className="px-4 py-3.5 font-mono text-xs text-gray-700 dark:text-gray-300">
                                  {sec["remote-address"] || "Dynamic Pool"}
                                </td>
                                <td className="px-4 py-3.5 text-xs text-gray-500 dark:text-gray-400 max-w-xs truncate">
                                  {sec.comment || "-"}
                                </td>
                                <td className="px-4 py-3.5 text-xs text-gray-500 dark:text-gray-400">
                                  {sec["last-logged-out"] || "-"}
                                </td>
                              </tr>
                            )
                          })
                      )}
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          )}

          {/* TAB 3: HOTSPOT ACTIVE */}
          {activeTab === "hotspot" && (
            <div className="p-4 sm:p-6 space-y-4">
              <div className="flex flex-col gap-2.5 sm:flex-row sm:items-center sm:justify-between">
                <div className="relative w-full sm:max-w-xs">
                  <Search className="pointer-events-none absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-gray-400 dark:text-gray-500" />
                  <input
                    type="text"
                    placeholder="Cari user Hotspot..."
                    value={searchQuery}
                    onChange={(e) => setSearchQuery(e.target.value)}
                    className="h-10 w-full rounded-xl border border-gray-200 bg-gray-50/75 pl-9 pr-8 text-xs text-gray-800 placeholder:text-gray-400 focus:border-brand-500 focus:outline-none dark:border-gray-800 dark:bg-gray-900/50 dark:text-gray-200 dark:placeholder:text-gray-500 font-medium transition"
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
                <span className="text-xs font-semibold text-gray-600 dark:text-gray-300">
                  Total Pengguna Terhubung: <strong className="text-brand-600 dark:text-brand-400">{hotspotActive.length}</strong>
                </span>
              </div>

              <div className="w-full overflow-x-auto table-scrollbar">
                <table className="w-full min-w-[1000px] text-left text-xs whitespace-nowrap border-collapse">
                  <thead className="border-b border-gray-200 bg-gray-50/75 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:border-gray-800 dark:bg-gray-800/40 dark:text-gray-400">
                    <tr>
                      <th className="px-4 py-3.5">Status</th>
                      <th className="px-4 py-3.5">Username / Kode Voucher</th>
                      <th className="px-4 py-3.5">IP Address</th>
                      <th className="px-4 py-3.5">MAC Address</th>
                      <th className="px-4 py-3.5">Uptime</th>
                      <th className="px-4 py-3.5">Sisa Waktu</th>
                      <th className="px-4 py-3.5">Bytes In / Out</th>
                      <th className="px-4 py-3.5 text-right">Aksi</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-gray-100 dark:divide-gray-800/60">
                    {hotspotActive.length === 0 ? (
                      <tr>
                        <td colSpan={8} className="px-4 py-8 text-center text-xs text-gray-500">
                          Tidak ada sesi pengguna hotspot aktif saat ini.
                        </td>
                      </tr>
                    ) : (
                      hotspotActive
                        .filter(h => h.user.toLowerCase().includes(searchQuery.toLowerCase()) || (h.address && h.address.includes(searchQuery)))
                        .map((hot) => (
                          <tr key={hot[".id"] || hot.user} className="hover:bg-gray-50/50 dark:hover:bg-white/[0.02] transition-colors">
                            <td className="px-4 py-3.5">
                              <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold bg-emerald-500 text-white shadow-xs whitespace-nowrap">
                                Aktif
                              </span>
                            </td>
                            <td className="px-4 py-3.5 font-bold font-mono text-gray-900 dark:text-white">
                              {hot.user}
                            </td>
                            <td className="px-4 py-3.5 font-mono text-xs text-brand-600 dark:text-brand-400 font-semibold">
                              {hot.address || "-"}
                            </td>
                            <td className="px-4 py-3.5 font-mono text-xs text-gray-600 dark:text-gray-400">
                              {hot.mac_address || "-"}
                            </td>
                            <td className="px-4 py-3.5 text-xs text-gray-700 dark:text-gray-300">
                              {hot.uptime || "-"}
                            </td>
                            <td className="px-4 py-3.5 text-xs font-semibold text-amber-600 dark:text-amber-400">
                              {hot["session-time-left"] || "Unlimited"}
                            </td>
                            <td className="px-4 py-3.5 font-mono text-xs text-gray-600 dark:text-gray-400">
                              ↓ {formatBytes(hot.bytes_in)} / ↑ {formatBytes(hot.bytes_out)}
                            </td>
                            <td className="px-4 py-3.5 text-right">
                              <button
                                type="button"
                                onClick={() => handleDisconnectHotspot(hot[".id"], hot.user)}
                                className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-rose-500 hover:bg-rose-600 text-white font-bold text-xs transition shadow-xs cursor-pointer whitespace-nowrap"
                              >
                                <Power className="size-3" />
                                Logout
                              </button>
                            </td>
                          </tr>
                        ))
                    )}
                  </tbody>
                </table>
              </div>
            </div>
          )}

          {/* TAB 4: DHCP LEASES */}
          {activeTab === "dhcp" && (
            <div className="p-4 sm:p-6 space-y-4">
              <div className="flex flex-col gap-2.5 sm:flex-row sm:items-center sm:justify-between">
                <div className="relative w-full sm:max-w-xs">
                  <Search className="pointer-events-none absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-gray-400 dark:text-gray-500" />
                  <input
                    type="text"
                    placeholder="Cari IP, Hostname, atau MAC..."
                    value={searchQuery}
                    onChange={(e) => setSearchQuery(e.target.value)}
                    className="h-10 w-full rounded-xl border border-gray-200 bg-gray-50/75 pl-9 pr-8 text-xs text-gray-800 placeholder:text-gray-400 focus:border-brand-500 focus:outline-none dark:border-gray-800 dark:bg-gray-900/50 dark:text-gray-200 dark:placeholder:text-gray-500 font-medium transition"
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
                <span className="text-xs font-semibold text-gray-600 dark:text-gray-300">
                  Total DHCP Leases: <strong>{dhcpLeases.length}</strong>
                </span>
              </div>

              <div className="w-full overflow-x-auto table-scrollbar">
                <table className="w-full min-w-[1000px] text-left text-xs whitespace-nowrap border-collapse">
                  <thead className="border-b border-gray-200 bg-gray-50/75 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:border-gray-800 dark:bg-gray-800/40 dark:text-gray-400">
                    <tr>
                      <th className="px-4 py-3.5">Status</th>
                      <th className="px-4 py-3.5">IP Address</th>
                      <th className="px-4 py-3.5">MAC Address</th>
                      <th className="px-4 py-3.5">Host Name</th>
                      <th className="px-4 py-3.5">Tipe</th>
                      <th className="px-4 py-3.5">Masa Berlaku (Expires)</th>
                      <th className="px-4 py-3.5">Komentar</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-gray-100 dark:divide-gray-800/60">
                    {dhcpLeases.length === 0 ? (
                      <tr>
                        <td colSpan={7} className="px-4 py-8 text-center text-xs text-gray-500">
                          Tidak ada data DHCP Leases ditemukan di router.
                        </td>
                      </tr>
                    ) : (
                      dhcpLeases
                        .filter(d => 
                          d.address.includes(searchQuery) || 
                          d.mac_address.toLowerCase().includes(searchQuery.toLowerCase()) || 
                          (d["host-name"] && d["host-name"].toLowerCase().includes(searchQuery.toLowerCase()))
                        )
                        .map((lease) => {
                          const isDynamic = lease.dynamic === true || lease.dynamic === "true"
                          const isBound = lease.status === "bound"

                          return (
                            <tr key={lease[".id"] || lease.mac_address} className="hover:bg-gray-50/50 dark:hover:bg-white/[0.02] transition-colors">
                              <td className="px-4 py-3.5">
                                {isBound ? (
                                  <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold bg-emerald-500 text-white shadow-xs whitespace-nowrap">
                                    Bound
                                  </span>
                                ) : (
                                  <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold bg-amber-500 text-white shadow-xs whitespace-nowrap">
                                    {lease.status || "Waiting"}
                                  </span>
                                )}
                              </td>
                              <td className="px-4 py-3.5 font-mono font-semibold text-gray-900 dark:text-white">
                                {lease["active-address"] || lease.address}
                              </td>
                              <td className="px-4 py-3.5 font-mono text-xs text-gray-600 dark:text-gray-400">
                                {lease["active-mac-address"] || lease.mac_address}
                              </td>
                              <td className="px-4 py-3.5 text-xs font-medium text-gray-900 dark:text-white">
                                {lease["host-name"] || "-"}
                              </td>
                              <td className="px-4 py-3.5">
                                <span className={`inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold text-white shadow-xs ${isDynamic ? "bg-brand-500" : "bg-purple-600"}`}>
                                  {isDynamic ? "Dynamic" : "Static"}
                                </span>
                              </td>
                              <td className="px-4 py-3.5 text-xs text-gray-500 dark:text-gray-400">
                                {lease.expires_after || "Permanent"}
                              </td>
                              <td className="px-4 py-3.5 text-xs text-gray-500 dark:text-gray-400 max-w-xs truncate">
                                {lease.comment || "-"}
                              </td>
                            </tr>
                          )
                        })
                    )}
                  </tbody>
                </table>
              </div>
            </div>
          )}

          {/* TAB 5: IP ADDRESSES */}
          {activeTab === "ip" && (
            <div className="p-4 sm:p-6 space-y-4">
              <div className="flex items-center justify-between">
                <h4 className="text-sm font-bold text-gray-900 dark:text-white">
                  Daftar IP Address &amp; Subnet Terkonfigurasi ({ipAddresses.length})
                </h4>
              </div>

              <div className="w-full overflow-x-auto table-scrollbar">
                <table className="w-full min-w-[1000px] text-left text-xs whitespace-nowrap border-collapse">
                  <thead className="border-b border-gray-200 bg-gray-50/75 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:border-gray-800 dark:bg-gray-800/40 dark:text-gray-400">
                    <tr>
                      <th className="px-4 py-3.5">IP / Netmask</th>
                      <th className="px-4 py-3.5">Network</th>
                      <th className="px-4 py-3.5">Interface Terikat</th>
                      <th className="px-4 py-3.5">Tipe</th>
                      <th className="px-4 py-3.5">Status</th>
                      <th className="px-4 py-3.5">Komentar</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-gray-100 dark:divide-gray-800/60">
                    {ipAddresses.length === 0 ? (
                      <tr>
                        <td colSpan={6} className="px-4 py-8 text-center text-xs text-gray-500">
                          Tidak ada data IP Address terpasang.
                        </td>
                      </tr>
                    ) : (
                      ipAddresses.map((ip) => {
                        const isDisabled = ip.disabled === true || ip.disabled === "true"
                        const isDynamic = ip.dynamic === true || ip.dynamic === "true"

                        return (
                          <tr key={ip[".id"] || ip.address} className="hover:bg-gray-50/50 dark:hover:bg-white/[0.02] transition-colors">
                            <td className="px-4 py-3.5 font-mono font-bold text-gray-900 dark:text-white">
                              {ip.address}
                            </td>
                            <td className="px-4 py-3.5 font-mono text-xs text-gray-600 dark:text-gray-400">
                              {ip.network || "-"}
                            </td>
                            <td className="px-4 py-3.5 font-semibold text-brand-600 dark:text-brand-400">
                              {ip.interface}
                            </td>
                            <td className="px-4 py-3.5">
                              <span className={`inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold text-white shadow-xs ${isDynamic ? "bg-brand-500" : "bg-purple-600"}`}>
                                {isDynamic ? "Dynamic" : "Static"}
                              </span>
                            </td>
                            <td className="px-4 py-3.5">
                              {isDisabled ? (
                                <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold bg-rose-500 text-white shadow-xs whitespace-nowrap">
                                  Disabled
                                </span>
                              ) : (
                                <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold bg-emerald-500 text-white shadow-xs whitespace-nowrap">
                                  Aktif
                                </span>
                              )}
                            </td>
                            <td className="px-4 py-3.5 text-xs text-gray-500 dark:text-gray-400 max-w-xs truncate">
                              {ip.comment || "-"}
                            </td>
                          </tr>
                        )
                      })
                    )}
                  </tbody>
                </table>
              </div>
            </div>
          )}

          {/* TAB 6: SYSTEM LOGS */}
          {activeTab === "logs" && (
            <div className="p-4 sm:p-6 space-y-4">
              <div className="flex items-center justify-between">
                <h4 className="text-sm font-bold text-gray-900 dark:text-white">
                  Log Aktivitas & Event MikroTik ({logs.length})
                </h4>
                <button
                  type="button"
                  onClick={() => fetchTabData("logs")}
                  className="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-1 text-xs font-semibold text-gray-700 shadow-xs hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"
                >
                  <RefreshCw className="size-3" />
                  Segarkan Log
                </button>
              </div>

              <div className="rounded-xl border border-gray-200 bg-gray-950 p-4 font-mono text-xs text-gray-200 shadow-inner dark:border-gray-800 max-h-[500px] overflow-y-auto space-y-1.5">
                {logs.length === 0 ? (
                  <p className="text-gray-500 py-4 text-center">Tidak ada catatan log terkini.</p>
                ) : (
                  logs.map((lg, idx) => {
                    const isError = lg.topics.includes("error") || lg.topics.includes("critical")
                    const isWarn = lg.topics.includes("warning")
                    const isPppoe = lg.topics.includes("pppoe") || lg.topics.includes("ppp")

                    return (
                      <div key={lg[".id"] || idx} className="flex items-start gap-3 py-0.5 hover:bg-white/5 px-2 rounded">
                        <span className="text-gray-500 shrink-0 select-none">[{lg.time}]</span>
                        <span className={`shrink-0 font-bold ${
                          isError ? "text-rose-400" : isWarn ? "text-amber-400" : isPppoe ? "text-cyan-400" : "text-emerald-400"
                        }`}>
                          &lt;{lg.topics}&gt;
                        </span>
                        <span className="text-gray-300 break-all">{lg.message}</span>
                      </div>
                    )
                  })
                )}
              </div>
            </div>
          )}

          {/* TAB 7: TOOLS & PING */}
          {activeTab === "tools" && (
            <div className="p-4 sm:p-6 space-y-6">
              <div className="max-w-2xl space-y-4">
                <h4 className="text-sm font-bold text-gray-900 dark:text-white flex items-center gap-2">
                  <Terminal className="size-4 text-brand-500" />
                  Alat Diagnostik Jaringan — ICMP Ping
                </h4>
                <p className="text-xs text-gray-500 dark:text-gray-400">
                  Kirim paket ping langsung dari router MikroTik untuk memeriksa latensi dan konektivitas gateway upstream atau IP pelanggan.
                </p>

                <form onSubmit={handleRunPing} className="flex flex-wrap items-end gap-3">
                  <div className="flex-1 min-w-[200px]">
                    <label className="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">
                      Host / IP Target
                    </label>
                    <input
                      type="text"
                      placeholder="e.g. 8.8.8.8 atau 1.1.1.1"
                      value={pingHost}
                      onChange={(e) => setPingHost(e.target.value)}
                      required
                      className="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs font-mono text-gray-900 shadow-xs focus:border-brand-500 focus:outline-hidden dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                    />
                  </div>
                  <div className="w-24">
                    <label className="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">
                      Count
                    </label>
                    <input
                      type="number"
                      min={1}
                      max={20}
                      value={pingCount}
                      onChange={(e) => setPingCount(Number(e.target.value))}
                      className="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs font-mono text-gray-900 shadow-xs focus:border-brand-500 focus:outline-hidden dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                    />
                  </div>
                  <button
                    type="submit"
                    disabled={pingRunning || !pingHost}
                    className="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-brand-500 hover:bg-brand-600 active:scale-95 text-xs font-bold text-white shadow-xs transition cursor-pointer disabled:opacity-50"
                  >
                    {pingRunning ? <RefreshCw className="size-3.5 animate-spin" /> : <Play className="size-3.5" />}
                    {pingRunning ? "Memproses..." : "Eksekusi Ping"}
                  </button>
                </form>

                {/* Ping Console Output */}
                <div className="mt-4 rounded-xl border border-gray-200 bg-gray-950 p-4 font-mono text-xs text-gray-200 shadow-inner dark:border-gray-800 min-h-[160px] space-y-1">
                  {pingResults.length === 0 ? (
                    <p className="text-gray-500 text-center py-6">Masukkan target host dan klik tombol Eksekusi Ping.</p>
                  ) : (
                    pingResults.map((line, idx) => (
                      <div key={idx} className="text-emerald-400">{line}</div>
                    ))
                  )}
                </div>
              </div>
            </div>
          )}
        </div>
      </div>

      {/* MODAL REBOOT CONFIRMATION */}
      <Modal
        isOpen={isRebootModalOpen}
        onClose={() => setIsRebootModalOpen(false)}
        title="Konfirmasi Reboot Router MikroTik"
        description="Peringatan restart sistem gateway core router"
        maxWidth="md"
      >
        <div className="space-y-4">
          <div className="flex items-center gap-3 rounded-xl bg-amber-50 p-3.5 text-amber-800 dark:bg-amber-950/40 dark:text-amber-300 border border-amber-200 dark:border-amber-900">
            <AlertTriangle className="size-5 shrink-0 text-amber-600 dark:text-amber-400" />
            <p className="text-xs leading-relaxed">
              Tindakan reboot akan merestart sistem MikroTik <strong>{router.name} ({router.ip_address})</strong>. Seluruh sesi koneksi pelanggan PPPoE dan Hotspot akan terputus sementara selama proses restart router.
            </p>
          </div>

          <p className="text-xs text-gray-600 dark:text-gray-400">
            Apakah Anda yakin ingin melanjutkan reboot perangkat MikroTik sekarang?
          </p>

          <div className="flex items-center justify-end gap-2 pt-4 border-t border-gray-200 dark:border-gray-800">
            <button
              type="button"
              onClick={() => setIsRebootModalOpen(false)}
              disabled={rebooting}
              className="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:hover:bg-gray-800 text-xs font-semibold text-gray-700 dark:text-gray-200 transition shadow-2xs cursor-pointer"
            >
              Batal
            </button>
            <button
              type="button"
              onClick={handleReboot}
              disabled={rebooting}
              className="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-rose-500 hover:bg-rose-600 active:scale-95 text-xs font-bold text-white shadow-xs transition cursor-pointer disabled:opacity-50"
            >
              {rebooting ? <RefreshCw className="size-3.5 animate-spin" /> : <Power className="size-3.5" />}
              {rebooting ? "Sedang Reboot..." : "Ya, Reboot Sekarang"}
            </button>
          </div>
        </div>
      </Modal>
    </AppLayout>
  )
}
