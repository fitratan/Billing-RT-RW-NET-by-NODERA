import { useState, useEffect, useMemo, useDeferredValue, useRef } from "react"
import { AppLayout } from "@/components/layout/app-layout"
import {
  Network,
  Wifi,
  WifiOff,
  RefreshCw,
  Search,
  Activity,
  User,
  Radio,
  Phone,
  ChevronDown,
  X,
  Power,
  Hash,
  Layers,
  ArrowDownLeft,
  ArrowUpRight,
  ExternalLink,
  Trash2,
  Lock,
  ShieldCheck,
  Plus,
  Copy,
  Check,
  Terminal,
  Settings,
} from "lucide-react"
import { EmptyState } from "@/components/ui/empty-state"
import { PageProps } from "@/types"
import { adminSidebarItems, adminNavItems, adminBrand } from "@/lib/admin-nav"
import { collectorNavItems, collectorSidebarItems, collectorBrand } from "@/lib/collector-nav"
import { technicianNavItems, technicianSidebarItems, technicianBrand } from "@/lib/technician-nav"
import { cn } from "@/lib/utils"
import { SyncOverlay } from "@/components/ui/sync-overlay"
import { MetricCard } from "@/components/tailadmin/MetricCard"
import { ViewModeSwitcher } from "@/components/ui/view-mode-switcher"
import { router, Link } from "@inertiajs/react"
import { motion, AnimatePresence } from "framer-motion"

interface ArpCustomer {
  id: number
  name: string
  code?: string | null
  phone?: string | null
  ip_address?: string | null
  mac_address?: string | null
  arp_interface?: string | null
  connection_type?: string | null
  status?: string | null
  package_name?: string | null
  package_price?: number | null
  onu_rx_power?: number | null
  onu_status?: string | null
}

interface ArpEntry {
  id?: string | null
  router_id: number
  router_name: string
  address: string
  mac_address: string
  interface: string
  comment?: string | null
  is_dynamic: boolean
  is_disabled: boolean
  is_complete: boolean
  is_invalid: boolean
  is_online: boolean
  customer?: ArpCustomer | null
  queue_name?: string | null
  max_limit?: string | null
  rate?: string | null
  rx_bytes: number
  tx_bytes: number
  total_bytes: number
}

interface ArpStats {
  total: number
  online: number
  offline: number
  static: number
  dynamic: number
  disabled: number
  linked: number
}

interface ArpPageProps extends PageProps {
  routerId: string | null
  routers: { id: number; name: string }[]
  users?: ArpEntry[]
  active?: ArpEntry[]
  inactive?: ArpEntry[]
  interfaces?: { name: string; type: string; running: boolean }[]
  customers?: { id: number; name: string; ip?: string | null; mac?: string | null }[]
  stats?: ArpStats
  error: string | null
  mode?: "admin" | "collector" | "technician"
  companyName?: string
  tenantName?: string
}

function formatBytes(bytes?: number): string {
  if (!bytes || bytes <= 0) return "0 B"
  const k = 1024
  const sizes = ["B", "KB", "MB", "GB", "TB"]
  const i = Math.floor(Math.log(bytes) / Math.log(k))
  return `${parseFloat((bytes / Math.pow(k, i)).toFixed(1))} ${sizes[i]}`
}

export default function ArpPage({
  routerId,
  routers = [],
  users = [],
  active = [],
  inactive = [],
  interfaces = [],
  customers = [],
  stats = {
    total: 0,
    online: 0,
    offline: 0,
    static: 0,
    dynamic: 0,
    disabled: 0,
    linked: 0,
  },
  error,
  mode = "admin",
}: ArpPageProps) {
  const isCollector = mode === "collector"
  const isTechnician = mode === "technician"
  const brand = isCollector ? collectorBrand : isTechnician ? technicianBrand : adminBrand
  const sidebarItems = isCollector
    ? collectorSidebarItems
    : isTechnician
    ? technicianSidebarItems
    : adminSidebarItems
  const navItems = isCollector ? collectorNavItems : isTechnician ? technicianNavItems : adminNavItems

  const [tab, setTab] = useState<"active" | "offline" | "static" | "users">("active")
  const [q, setQ] = useState("")
  const deferredQ = useDeferredValue(q)
  const [selectedInterface, setSelectedInterface] = useState<string>("all")
  const [viewMode, setViewMode] = useState<"table" | "grid">("grid")
  const [expandedId, setExpandedId] = useState<string | null>(null)
  const [managingArp, setManagingArp] = useState<ArpEntry | null>(null)
  const [copiedText, setCopiedText] = useState<string | null>(null)
  const [autoRefresh, setAutoRefresh] = useState(true)
  const [countdown, setCountdown] = useState(15)
  const [isAutoSyncing, setIsAutoSyncing] = useState(false)
  const [visibleLimit, setVisibleLimit] = useState(60)

  // Modals state
  const [isAddModalOpen, setIsAddModalOpen] = useState(false)
  const [formData, setFormData] = useState({
    router_id: routerId && routerId !== "all" ? routerId : routers[0]?.id ? String(routers[0].id) : "",
    address: "",
    mac_address: "",
    interface: interfaces[0]?.name || "ether1",
    customer_id: "",
    comment: "",
  })
  const [formSubmitting, setFormSubmitting] = useState(false)

  // Ping Modal state
  const [isPingModalOpen, setIsPingModalOpen] = useState(false)
  const [pingTarget, setPingTarget] = useState<ArpEntry | null>(null)
  const [pingLoading, setPingLoading] = useState(false)
  const [pingResult, setPingResult] = useState<any>(null)

  // Sync Overlay
  const [syncInfo, setSyncInfo] = useState<{ show: boolean; title?: string; statusMessage?: string } | null>(null)

  const copyToClipboard = (text: string) => {
    navigator.clipboard.writeText(text)
    setCopiedText(text)
    setTimeout(() => setCopiedText(null), 2000)
  }

  // Auto refresh timer
  useEffect(() => {
    if (!autoRefresh) return
    const interval = setInterval(() => {
      setCountdown((prev) => {
        if (prev <= 1) {
          setIsAutoSyncing(true)
          router.reload({
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
  }, [autoRefresh])

  // Filter list
  const currentItems = useMemo(() => {
    let list: ArpEntry[] = []
    if (tab === "active") list = active
    else if (tab === "offline") list = inactive
    else if (tab === "static") list = users.filter((u) => !u.is_dynamic)
    else list = users

    if (selectedInterface !== "all") {
      list = list.filter((item) => item.interface === selectedInterface)
    }

    if (deferredQ.trim()) {
      const search = deferredQ.toLowerCase().trim()
      list = list.filter((item) => {
        const ip = (item.address || "").toLowerCase()
        const mac = (item.mac_address || "").toLowerCase()
        const cust = (item.customer?.name || "").toLowerCase()
        const comment = (item.comment || "").toLowerCase()
        const rName = (item.router_name || "").toLowerCase()
        return (
          ip.includes(search) ||
          mac.includes(search) ||
          cust.includes(search) ||
          comment.includes(search) ||
          rName.includes(search)
        )
      })
    }

    return list
  }, [tab, active, inactive, users, selectedInterface, deferredQ])

  const availableInterfaces = useMemo(() => {
    if (interfaces.length > 0) return interfaces.map((i) => i.name)
    const set = new Set<string>()
    users.forEach((u) => u.interface && set.add(u.interface))
    return Array.from(set)
  }, [interfaces, users])

  const apiPrefix = isCollector ? "/kolektor/arp" : isTechnician ? "/teknisi/arp" : "/admin/arp"

  // Make Static Handler
  const handleMakeStatic = async (entry: ArpEntry) => {
    setSyncInfo({
      show: true,
      title: "Mengunci Static ARP",
      statusMessage: `Menyimpan ${entry.address} (${entry.mac_address}) sebagai Static IP Binding...`,
    })
    try {
      await router.post(
        `${apiPrefix}/make-static`,
        {
          router_id: entry.router_id,
          address: entry.address,
          mac_address: entry.mac_address,
          interface: entry.interface,
          comment: entry.comment || `NODERA-STATIC-${entry.address}`,
        },
        {
          preserveScroll: true,
          preserveState: true,
          onFinish: () => {
            setSyncInfo(null)
            if (managingArp) setManagingArp(null)
          },
        }
      )
    } catch {
      setSyncInfo(null)
    }
  }

  // Toggle Disabled Handler
  const handleToggleDisabled = async (entry: ArpEntry) => {
    const nextState = !entry.is_disabled
    setSyncInfo({
      show: true,
      title: nextState ? "Menonaktifkan ARP" : "Mengaktifkan ARP",
      statusMessage: `Mengubah status entri ${entry.address}...`,
    })
    try {
      await router.post(
        `${apiPrefix}/toggle-disabled`,
        {
          router_id: entry.router_id,
          address: entry.address,
          mac_address: entry.mac_address,
          interface: entry.interface,
          disabled: nextState,
        },
        {
          preserveScroll: true,
          preserveState: true,
          onFinish: () => {
            setSyncInfo(null)
            if (managingArp) setManagingArp(null)
          },
        }
      )
    } catch {
      setSyncInfo(null)
    }
  }

  // Delete ARP Handler
  const handleDeleteArp = async (entry: ArpEntry) => {
    if (!confirm(`Yakin ingin menghapus binding ARP ${entry.address} (${entry.mac_address})?`)) return
    setSyncInfo({
      show: true,
      title: "Menghapus Binding ARP",
      statusMessage: `Menghapus entri ${entry.address} dari tabel router...`,
    })
    try {
      await router.post(
        `${apiPrefix}/delete`,
        {
          router_id: entry.router_id,
          address: entry.address,
          mac_address: entry.mac_address,
          interface: entry.interface,
        },
        {
          preserveScroll: true,
          preserveState: true,
          onFinish: () => {
            setSyncInfo(null)
            if (managingArp) setManagingArp(null)
          },
        }
      )
    } catch {
      setSyncInfo(null)
    }
  }

  // Ping Diagnostic
  const handleOpenPing = (entry: ArpEntry) => {
    setPingTarget(entry)
    setPingResult(null)
    setIsPingModalOpen(true)
    runPingTest(entry)
  }

  const runPingTest = async (entry: ArpEntry) => {
    setPingLoading(true)
    try {
      const res = await fetch(
        `${apiPrefix}/ping-test?router_id=${entry.router_id}&address=${encodeURIComponent(
          entry.address
        )}&count=4`,
        {
          headers: {
            Accept: "application/json",
          },
        }
      )
      const data = await res.json()
      setPingResult(data)
    } catch (err: any) {
      setPingResult({ success: false, message: err?.message || "Gagal terhubung ke service ping router." })
    } finally {
      setPingLoading(false)
    }
  }

  // Add Static Form Submit
  const handleAddSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    setFormSubmitting(true)
    router.post(`${apiPrefix}/add-static`, formData, {
      preserveScroll: true,
      onSuccess: () => {
        setIsAddModalOpen(false)
        setFormData({
          router_id: routerId && routerId !== "all" ? routerId : routers[0]?.id ? String(routers[0].id) : "",
          address: "",
          mac_address: "",
          interface: interfaces[0]?.name || "ether1",
          customer_id: "",
          comment: "",
        })
      },
      onFinish: () => setFormSubmitting(false),
    })
  }

  const currentTotal = currentItems.length
  const displayedItems = currentItems.slice(0, visibleLimit)

  return (
    <AppLayout
      title="Monitoring ARP & Static IP"
      brand={brand}
      sidebarItems={sidebarItems}
      navItems={navItems}
    >
      <div className="space-y-4 sm:space-y-6">
        {/* Metric Cards Grid */}
        <div className="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
          <MetricCard
            title="Host Online"
            value={stats.online}
            icon={Wifi}
            iconBgColor="bg-emerald-50 dark:bg-emerald-500/10"
            iconColor="text-emerald-500"
            description="Aktif berkomunikasi"
          />
          <MetricCard
            title="Host Offline"
            value={stats.offline}
            icon={WifiOff}
            iconBgColor="bg-rose-50 dark:bg-rose-500/10"
            iconColor="text-rose-500"
            description="Tidak merespon traffic"
          />
          <MetricCard
            title="Static Binding"
            value={stats.static}
            icon={Lock}
            iconBgColor="bg-amber-50 dark:bg-amber-500/10"
            iconColor="text-amber-500"
            description="IP terkunci permanen"
          />
          <MetricCard
            title="Total ARP Router"
            value={stats.total}
            icon={Network}
            iconBgColor="bg-blue-50 dark:bg-blue-500/10"
            iconColor="text-blue-500"
            description={`${stats.dynamic} DHCP dinamis`}
          />
        </div>

        {/* Error Alert */}
        {error && (
          <div className="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-xs font-semibold text-rose-800 dark:border-rose-900/40 dark:bg-rose-950/20 dark:text-rose-400">
            {error}
          </div>
        )}

        {/* ── 1-LINE RESPONSIVE INTEGRATED TOOLBAR ── */}
        <div className="rounded-2xl border border-gray-200 bg-white p-3 sm:p-4 shadow-xs dark:border-gray-800 dark:bg-white/[0.03] flex flex-col xl:flex-row xl:items-center xl:justify-between gap-2.5 sm:gap-3 w-full min-w-0">
          <div className="flex flex-wrap sm:flex-nowrap items-center gap-2 flex-1 min-w-0">
            {/* Search Bar */}
            <div className="relative flex-1 min-w-[140px] sm:min-w-[180px]">
              <Search className="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400 dark:text-gray-500" />
              <input
                type="text"
                value={q}
                onChange={(e) => setQ(e.target.value)}
                placeholder="Cari IP, MAC, nama, comment..."
                className="h-10 w-full rounded-xl border border-gray-200 bg-gray-50/75 pl-9 pr-8 text-xs text-gray-800 placeholder:text-gray-400 focus:border-brand-500 focus:outline-none dark:border-gray-800 dark:bg-gray-900/50 dark:text-gray-200 dark:placeholder:text-gray-500 font-medium transition"
              />
              {q && (
                <button
                  type="button"
                  onClick={() => setQ("")}
                  className="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-700 dark:hover:text-white cursor-pointer"
                >
                  <X className="h-3.5 w-3.5" />
                </button>
              )}
            </div>

            {/* Status Select */}
            <select
              value={tab}
              onChange={(e) => {
                setTab(e.target.value as any)
                setExpandedId(null)
              }}
              className="h-10 rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 px-3 text-xs font-semibold text-gray-700 dark:text-gray-200 cursor-pointer shrink-0 shadow-2xs max-w-[130px] truncate"
            >
              <option value="active">Online ({stats.online})</option>
              <option value="offline">Offline ({stats.offline})</option>
              <option value="static">Static ({stats.static})</option>
              <option value="users">Semua ({stats.total})</option>
            </select>

            {/* Router Selector */}
            {routers.length > 0 && (
              <select
                value={routerId || ""}
                onChange={(e) => {
                  const val = e.target.value
                  router.get(
                    apiPrefix,
                    val ? { router_id: val } : {},
                    { preserveState: true, preserveScroll: true }
                  )
                }}
                className="h-10 rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 px-3 text-xs font-semibold text-gray-700 dark:text-gray-200 cursor-pointer shrink-0 shadow-2xs max-w-[140px] truncate"
              >
                {routers.length > 1 && (
                  <option value="all">Semua Router ({routers.length})</option>
                )}
                {routers.map((r) => (
                  <option key={r.id} value={r.id}>
                    {r.name}
                  </option>
                ))}
              </select>
            )}

            {/* Interface Filter */}
            {availableInterfaces.length > 0 && (
              <select
                value={selectedInterface}
                onChange={(e) => setSelectedInterface(e.target.value)}
                className="h-10 rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 px-3 text-xs font-semibold text-gray-700 dark:text-gray-200 cursor-pointer shrink-0 shadow-2xs max-w-[140px] truncate"
              >
                <option value="all">Semua Interface ({availableInterfaces.length})</option>
                {availableInterfaces.map((iface) => (
                  <option key={iface} value={iface}>
                    {iface}
                  </option>
                ))}
              </select>
            )}
          </div>

          <div className="flex items-center gap-2 shrink-0 justify-end">
            <button
              type="button"
              onClick={() => setAutoRefresh(!autoRefresh)}
              className={cn(
                "h-10 inline-flex items-center gap-1.5 px-3 rounded-xl border text-xs font-semibold transition-all cursor-pointer shadow-2xs shrink-0",
                autoRefresh
                  ? "bg-emerald-50 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-500/30 font-bold"
                  : "border-gray-200 bg-white text-gray-700 hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-200"
              )}
              title={autoRefresh ? "Auto Refresh Aktif (15 Detik)" : "Auto Refresh Nonaktif"}
            >
              <span className={cn("h-2 w-2 rounded-full shrink-0", autoRefresh ? "bg-emerald-500 animate-pulse" : "bg-gray-400")} />
              <span className="hidden sm:inline">{autoRefresh ? (isAutoSyncing ? "Sync..." : `Auto (${countdown}s)`) : "Auto Refresh"}</span>
            </button>

            <div className="shrink-0">
              <ViewModeSwitcher value={viewMode} onChange={setViewMode} size="sm" />
            </div>

            <button
              type="button"
              onClick={() => setIsAddModalOpen(true)}
              className="h-10 px-3.5 sm:px-4 rounded-xl bg-brand-500 hover:bg-brand-600 active:scale-95 text-xs font-bold text-white shadow-xs flex items-center justify-center gap-1.5 shrink-0 cursor-pointer"
            >
              <Plus className="h-4 w-4" />
              <span>Tambah Static ARP</span>
            </button>
          </div>
        </div>

        {/* Master Card Table / Stream */}
        <div className="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xs dark:border-gray-800 dark:bg-white/[0.03]">
          {/* Body Content */}
          {displayedItems.length === 0 ? (
            <div className="p-8 sm:p-12 text-center">
              <EmptyState
                icon={<Network className="h-10 w-10 text-brand-500" />}
                title="Tidak ada entri ARP ditemukan"
                description={
                  q
                    ? `Tidak ada data host yang cocok dengan kata kunci "${q}"`
                    : "Belum ada entri perangkat ARP yang terbaca pada router MikroTik ini."
                }
                action={
                  tab === "static" ? (
                    <button
                      type="button"
                      onClick={() => setIsAddModalOpen(true)}
                      className="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-brand-500 text-white font-bold text-xs hover:bg-brand-600 transition shadow-xs cursor-pointer"
                    >
                      <Plus className="h-4 w-4" /> Tambah Static ARP Sekarang
                    </button>
                  ) : undefined
                }
              />
            </div>
          ) : viewMode === "table" ? (
            <div className="w-full overflow-x-auto custom-scrollbar">
              <table className="w-full text-left text-xs sm:text-sm min-w-[700px] border-collapse">
                <thead className="border-b border-gray-200 bg-gray-50/75 text-xs font-semibold text-gray-500 uppercase tracking-wider dark:border-gray-800 dark:bg-gray-900/50 dark:text-gray-400">
                  <tr>
                    <th className="px-4 py-3 sm:px-6">IP &amp; MAC Address</th>
                    <th className="px-4 py-3 sm:px-6">Pelanggan / Keterangan</th>
                    <th className="px-4 py-3 sm:px-6">Interface</th>
                    <th className="px-4 py-3 sm:px-6">Router</th>
                    <th className="px-4 py-3 sm:px-6">Tipe Binding</th>
                    <th className="px-4 py-3 sm:px-6">Status</th>
                    <th className="px-4 py-3 sm:px-6 text-right">Aksi</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-gray-100 dark:divide-gray-800/60">
                  {displayedItems.map((item, idx) => (
                    <tr
                      key={idx}
                      className="hover:bg-gray-50/50 dark:hover:bg-white/[0.02] transition-colors"
                    >
                      <td className="px-4 py-3.5 sm:px-6">
                        <div className="font-mono font-bold text-gray-900 dark:text-white flex items-center gap-1.5">
                          <span>{item.address}</span>
                          <button
                            type="button"
                            onClick={() => copyToClipboard(item.address)}
                            className="p-1 text-gray-400 hover:text-gray-700 dark:hover:text-white rounded"
                            title="Salin IP"
                          >
                            {copiedText === item.address ? <Check className="h-3 w-3 text-emerald-500" /> : <Copy className="h-3 w-3" />}
                          </button>
                        </div>
                        <div className="font-mono text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                          {item.mac_address || "00:00:00:00:00:00"}
                        </div>
                      </td>
                      <td className="px-4 py-3.5 sm:px-6">
                        {item.customer ? (
                          <Link
                            href={`/admin/billing/customers?search=${encodeURIComponent(item.customer.name)}`}
                            className="font-semibold text-brand-600 dark:text-brand-400 hover:underline inline-flex items-center gap-1 truncate max-w-[200px]"
                          >
                            <span>{item.customer.name}</span>
                            <ExternalLink className="h-3 w-3" />
                          </Link>
                        ) : item.comment ? (
                          <span className="font-mono text-gray-700 dark:text-gray-300">{item.comment}</span>
                        ) : (
                          <span className="text-gray-400">-</span>
                        )}
                      </td>
                      <td className="px-4 py-3.5 sm:px-6 font-mono text-gray-700 dark:text-gray-300">
                        {item.interface || "-"}
                      </td>
                      <td className="px-4 py-3.5 sm:px-6 text-gray-700 dark:text-gray-300 font-medium">
                        {item.router_name || "-"}
                      </td>
                      <td className="px-4 py-3.5 sm:px-6">
                        <span
                          className={cn(
                            "inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold text-white shadow-xs",
                            item.is_dynamic
                              ? "bg-gray-500"
                              : "bg-brand-500"
                          )}
                        >
                          {item.is_dynamic ? "Dynamic" : "Static"}
                        </span>
                      </td>
                      <td className="px-4 py-3.5 sm:px-6">
                        <span
                          className={cn(
                            "inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold text-white shadow-xs",
                            item.is_disabled
                              ? "bg-rose-500"
                              : item.is_online
                              ? "bg-emerald-500"
                              : "bg-rose-500"
                          )}
                        >
                          {item.is_disabled ? "Disabled" : item.is_online ? "Online" : "Offline"}
                        </span>
                      </td>
                      <td className="px-4 py-3.5 sm:px-6 text-right">
                        <button
                          type="button"
                          onClick={() => setManagingArp(item)}
                          className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200 font-bold text-xs transition cursor-pointer"
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
          ) : (
            <div className="p-4 sm:p-6 grid grid-cols-1 items-start gap-3.5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-3 2xl:grid-cols-4 w-full min-w-0">
              {displayedItems.map((item, idx) => {
                const cardKey = `${item.router_id}-${item.address}-${item.mac_address}-${idx}`
                const isExpanded = expandedId === cardKey

                return (
                  <div
                    key={cardKey}
                    className={cn(
                      "p-4 sm:p-5 rounded-2xl border bg-white dark:bg-white/[0.02] transition-all space-y-3 shadow-xs",
                      isExpanded
                        ? "border-brand-500 ring-1 ring-brand-500/30"
                        : "border-gray-200 dark:border-gray-800 hover:border-brand-500/30"
                    )}
                  >
                    <div className="flex items-start justify-between gap-2.5">
                      <div className="flex items-center gap-3 min-w-0 flex-1">
                        <div
                          className={cn(
                            "relative flex h-10 w-10 shrink-0 items-center justify-center rounded-xl font-bold text-xs shadow-xs",
                            item.is_disabled
                              ? "bg-gray-100 text-gray-500 dark:bg-gray-800"
                              : item.is_online
                              ? "bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400 border border-emerald-200/50 dark:border-emerald-500/20"
                              : "bg-rose-50 text-rose-600 dark:bg-rose-500/10 dark:text-rose-400 border border-rose-200/50 dark:border-rose-500/20"
                          )}
                        >
                          <Network className="h-5 w-5" />
                          <span
                            className={cn(
                              "absolute -bottom-0.5 -right-0.5 h-2.5 w-2.5 rounded-full ring-2 ring-white dark:ring-gray-900",
                              item.is_disabled ? "bg-gray-400" : item.is_online ? "bg-emerald-500" : "bg-rose-500"
                            )}
                          />
                        </div>

                        <div className="min-w-0 flex-1">
                          <h4 className="font-mono font-bold text-sm text-gray-900 dark:text-white truncate">
                            {item.address}
                          </h4>
                          <div className="mt-1 flex items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400 font-mono truncate">
                            <span
                              className={cn(
                                "inline-flex items-center px-1.5 py-0.5 rounded-md text-[10px] font-bold text-white shadow-xs",
                                item.is_disabled ? "bg-gray-500" : item.is_online ? "bg-emerald-500" : "bg-rose-500"
                              )}
                            >
                              {item.is_disabled ? "Disabled" : item.is_online ? "Online" : "Offline"}
                            </span>
                            <span>•</span>
                            <span className="text-gray-700 dark:text-gray-300 font-semibold">{item.interface}</span>
                          </div>
                        </div>
                      </div>

                      <button
                        type="button"
                        onClick={() => setExpandedId(isExpanded ? null : cardKey)}
                        className="p-1 rounded-lg text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 transition"
                      >
                        <ChevronDown
                          className={cn(
                            "h-4 w-4 text-gray-400 transition-transform duration-200",
                            isExpanded && "rotate-180 text-brand-500"
                          )}
                        />
                      </button>
                    </div>

                    {/* Sunken Box */}
                    <div className="rounded-xl border border-gray-100 bg-gray-50/80 p-3 space-y-1.5 text-xs dark:border-gray-800/80 dark:bg-gray-900/50">
                      <div className="flex items-center justify-between">
                        <span className="text-gray-500 dark:text-gray-400">MAC</span>
                        <span className="font-mono text-gray-800 dark:text-gray-200 font-semibold truncate max-w-[150px]">
                          {item.mac_address || "00:00:00:00:00:00"}
                        </span>
                      </div>
                      <div className="flex items-center justify-between pt-1 border-t border-gray-200/50 dark:border-gray-800/60">
                        <span className="text-gray-500 dark:text-gray-400">Router</span>
                        <span className="font-medium text-gray-700 dark:text-gray-300 truncate max-w-[150px]">
                          {item.router_name || "-"}
                        </span>
                      </div>
                      {item.customer && (
                        <div className="flex items-center justify-between pt-1 border-t border-gray-200/50 dark:border-gray-800/60">
                          <span className="text-gray-500 dark:text-gray-400">Pelanggan</span>
                          <span className="font-semibold text-brand-600 dark:text-brand-400 truncate max-w-[150px]">
                            {item.customer.name}
                          </span>
                        </div>
                      )}
                    </div>

                    {/* Expanded details */}
                    {isExpanded && (
                      <div className="pt-2 border-t border-gray-100 dark:border-gray-800/80 space-y-2 text-xs">
                        <div className="flex justify-between items-center py-1">
                          <span className="text-gray-500">Tipe Binding:</span>
                          <span className="font-bold text-gray-800 dark:text-gray-200">
                            {item.is_dynamic ? "Dynamic (DHCP)" : "Static Binding"}
                          </span>
                        </div>
                        {item.comment && (
                          <div className="flex justify-between items-center py-1 border-t border-gray-100 dark:border-gray-800/60">
                            <span className="text-gray-500">Komentar:</span>
                            <span className="font-mono text-gray-700 dark:text-gray-300 truncate max-w-[160px]">{item.comment}</span>
                          </div>
                        )}
                        {(item.total_bytes > 0 || item.max_limit) && (
                          <div className="p-2 rounded-lg bg-gray-50 dark:bg-gray-900/60 border border-gray-100 dark:border-gray-800 text-[11px] space-y-1">
                            <div className="flex justify-between">
                              <span className="text-emerald-600">Download: {formatBytes(item.tx_bytes)}</span>
                              <span className="text-sky-600">Upload: {formatBytes(item.rx_bytes)}</span>
                            </div>
                            {item.max_limit && (
                              <div className="text-gray-500 border-t border-gray-200/50 dark:border-gray-800 pt-1">
                                Queue Limit: <span className="font-mono font-bold text-gray-800 dark:text-white">{item.max_limit}</span>
                              </div>
                            )}
                          </div>
                        )}
                      </div>
                    )}

                    {/* Single Kelola Button */}
                    <div className="pt-1">
                      <button
                        type="button"
                        onClick={() => setManagingArp(item)}
                        className="w-full inline-flex items-center justify-center gap-1.5 px-3 py-1.5 rounded-lg bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200 font-bold text-xs transition cursor-pointer"
                      >
                        <Settings className="h-3.5 w-3.5" />
                        <span>Kelola</span>
                      </button>
                    </div>
                  </div>
                )
              })}
            </div>
          )}

          {/* Footer pagination info */}
          {currentTotal > visibleLimit && (
            <div className="p-4 border-t border-gray-100 dark:border-gray-800/80 text-center">
              <button
                type="button"
                onClick={() => setVisibleLimit((prev) => prev + 60)}
                className="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:hover:bg-gray-800 text-xs font-semibold text-gray-700 dark:text-gray-200 transition shadow-2xs cursor-pointer"
              >
                Muat Lebih Banyak ({displayedItems.length} dari {currentTotal} entri)
              </button>
            </div>
          )}
        </div>

        {/* ════════════════════════════════════════════════════════════════════════ */}
        {/* ── MODAL KELOLA ARP INTERAKTIF ── */}
        {/* ════════════════════════════════════════════════════════════════════════ */}
        <AnimatePresence>
          {managingArp && (
            <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
              <motion.div
                initial={{ opacity: 0 }}
                animate={{ opacity: 1 }}
                exit={{ opacity: 0 }}
                onClick={() => setManagingArp(null)}
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
                    <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-400 border border-brand-200/50 dark:border-brand-500/20 font-bold text-sm shadow-xs">
                      <Network className="h-5 w-5" />
                    </div>
                    <div>
                      <h3 className="text-base font-bold text-gray-800 dark:text-white font-mono">
                        {managingArp.address}
                      </h3>
                      <div className="flex items-center gap-2 mt-1">
                        <span
                          className={cn(
                            "inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold text-white shadow-xs",
                            managingArp.is_disabled ? "bg-rose-500" : managingArp.is_online ? "bg-emerald-500" : "bg-rose-500"
                          )}
                        >
                          {managingArp.is_disabled ? "Disabled" : managingArp.is_online ? "Online" : "Offline"}
                        </span>
                        <span
                          className={cn(
                            "inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold text-white shadow-xs",
                            managingArp.is_dynamic ? "bg-gray-500" : "bg-brand-500"
                          )}
                        >
                          {managingArp.is_dynamic ? "Dynamic" : "Static"}
                        </span>
                      </div>
                    </div>
                  </div>
                  <button
                    type="button"
                    onClick={() => setManagingArp(null)}
                    className="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 dark:text-gray-500 transition cursor-pointer"
                  >
                    <X className="h-5 w-5" />
                  </button>
                </div>

                {/* Info Details */}
                <div className="space-y-2.5">
                  <div className="rounded-xl border border-gray-100 bg-gray-50/80 dark:border-gray-800 dark:bg-gray-900/50 p-3 space-y-2 text-xs">
                    <div className="flex justify-between items-center py-1 border-b border-gray-200/50 dark:border-gray-800/50">
                      <span className="text-gray-500 dark:text-gray-400 font-medium">MAC Address</span>
                      <div className="flex items-center gap-1.5">
                        <span className="font-mono font-bold text-gray-800 dark:text-gray-200">
                          {managingArp.mac_address || "00:00:00:00:00:00"}
                        </span>
                        <button
                          type="button"
                          onClick={() => copyToClipboard(managingArp.mac_address)}
                          className="p-1 text-gray-400 hover:text-gray-700 dark:hover:text-white rounded"
                        >
                          {copiedText === managingArp.mac_address ? <Check className="h-3 w-3 text-emerald-500" /> : <Copy className="h-3 w-3" />}
                        </button>
                      </div>
                    </div>
                    <div className="flex justify-between items-center py-1 border-b border-gray-200/50 dark:border-gray-800/50">
                      <span className="text-gray-500 dark:text-gray-400 font-medium">Interface / Router</span>
                      <span className="font-semibold text-gray-800 dark:text-gray-200">
                        {managingArp.interface} ({managingArp.router_name})
                      </span>
                    </div>
                    {managingArp.customer && (
                      <div className="flex justify-between items-center py-1 border-b border-gray-200/50 dark:border-gray-800/50">
                        <span className="text-gray-500 dark:text-gray-400 font-medium">Pelanggan Terhubung</span>
                        <span className="font-bold text-brand-600 dark:text-brand-400">
                          {managingArp.customer.name}
                        </span>
                      </div>
                    )}
                    {managingArp.comment && (
                      <div className="flex justify-between items-center py-1 border-b border-gray-200/50 dark:border-gray-800/50">
                        <span className="text-gray-500 dark:text-gray-400 font-medium">Komentar / Note</span>
                        <span className="text-gray-700 dark:text-gray-300 font-mono">
                          {managingArp.comment}
                        </span>
                      </div>
                    )}
                    {managingArp.max_limit && (
                      <div className="flex justify-between items-center py-1">
                        <span className="text-gray-500 dark:text-gray-400 font-medium">Queue Bandwidth Limit</span>
                        <span className="font-mono font-bold text-brand-600 dark:text-brand-400">
                          {managingArp.max_limit}
                        </span>
                      </div>
                    )}
                  </div>
                </div>

                {/* Actions */}
                <div className="space-y-2 pt-2">
                  <button
                    type="button"
                    onClick={() => {
                      const item = managingArp
                      setManagingArp(null)
                      handleOpenPing(item)
                    }}
                    className="w-full inline-flex items-center justify-center gap-1.5 px-3.5 py-2.5 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:hover:bg-gray-800 text-xs font-semibold text-gray-700 dark:text-gray-200 transition shadow-2xs cursor-pointer"
                  >
                    <Activity className="h-4 w-4 text-brand-500" />
                    <span>Uji Ping Diagnostik ICMP</span>
                  </button>

                  {managingArp.is_dynamic && (
                    <button
                      type="button"
                      onClick={() => handleMakeStatic(managingArp)}
                      className="w-full inline-flex items-center justify-center gap-1.5 px-3.5 py-2.5 rounded-xl bg-amber-500 hover:bg-amber-600 active:scale-95 text-xs font-bold text-white shadow-xs transition cursor-pointer"
                    >
                      <Lock className="h-4 w-4" />
                      <span>Kunci Jadi Static IP Binding</span>
                    </button>
                  )}

                  <button
                    type="button"
                    onClick={() => handleToggleDisabled(managingArp)}
                    className={cn(
                      "w-full inline-flex items-center justify-center gap-1.5 px-3.5 py-2.5 rounded-xl text-xs font-bold text-white shadow-xs transition cursor-pointer",
                      managingArp.is_disabled ? "bg-emerald-500 hover:bg-emerald-600" : "bg-amber-500 hover:bg-amber-600"
                    )}
                  >
                    <Power className="h-4 w-4" />
                    <span>{managingArp.is_disabled ? "Aktifkan Kembali Binding" : "Nonaktifkan (Disable) Host"}</span>
                  </button>

                  {managingArp.customer && (
                    <Link
                      href={`/admin/billing/customers?search=${encodeURIComponent(managingArp.customer.name)}`}
                      className="w-full inline-flex items-center justify-center gap-1.5 px-3.5 py-2.5 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:hover:bg-gray-800 text-xs font-semibold text-gray-700 dark:text-gray-200 transition shadow-2xs cursor-pointer"
                    >
                      <ExternalLink className="h-4 w-4 text-brand-500" />
                      <span>Buka Profil Pelanggan</span>
                    </Link>
                  )}

                  <button
                    type="button"
                    onClick={() => handleDeleteArp(managingArp)}
                    className="w-full inline-flex items-center justify-center gap-1.5 px-3.5 py-2.5 rounded-xl bg-rose-500 hover:bg-rose-600 active:scale-95 text-xs font-bold text-white shadow-xs transition cursor-pointer"
                  >
                    <Trash2 className="h-4 w-4" />
                    <span>Hapus Entri Binding Ini</span>
                  </button>
                </div>
              </motion.div>
            </div>
          )}
        </AnimatePresence>

        {/* ════════════════════════════════════════════════════════════════════════ */}
        {/* ── MODAL: TAMBAH STATIC ARP ── */}
        {/* ════════════════════════════════════════════════════════════════════════ */}
        {isAddModalOpen && (
          <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs animate-in fade-in-50">
            <div className="w-full max-w-lg max-h-[92vh] flex flex-col rounded-2xl border border-gray-200 bg-white shadow-xl dark:border-gray-800 dark:bg-gray-900 text-gray-900 dark:text-white">
              <div className="flex items-center justify-between border-b border-gray-100 dark:border-gray-800/80 p-5">
                <div className="flex items-center gap-3">
                  <div className="p-2 rounded-xl bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-400">
                    <ShieldCheck className="h-5 w-5" />
                  </div>
                  <div>
                    <h3 className="font-bold text-base text-gray-900 dark:text-white">Tambah Static ARP Binding</h3>
                    <p className="text-xs text-gray-500 dark:text-gray-400">Kunci IP &amp; MAC Address di router MikroTik</p>
                  </div>
                </div>
                <button
                  type="button"
                  onClick={() => setIsAddModalOpen(false)}
                  className="p-1.5 text-gray-400 hover:text-gray-700 dark:hover:text-white rounded-xl transition-colors cursor-pointer"
                >
                  <X className="h-5 w-5" />
                </button>
              </div>

              <form onSubmit={handleAddSubmit} className="overflow-y-auto p-5 space-y-4">
                {/* Router Selection */}
                <div>
                  <label className="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1.5">Router MikroTik</label>
                  <select
                    value={formData.router_id}
                    onChange={(e) => setFormData({ ...formData, router_id: e.target.value })}
                    className="w-full h-10 rounded-xl border border-gray-200 bg-gray-50/50 px-3 text-xs font-semibold text-gray-800 outline-none transition focus:border-brand-500 focus:bg-white focus:ring-2 focus:ring-brand-500/10 dark:border-gray-800 dark:bg-gray-900/50 dark:text-white/90 cursor-pointer"
                    required
                  >
                    {routers.map((r) => (
                      <option key={r.id} value={r.id}>
                        {r.name}
                      </option>
                    ))}
                  </select>
                </div>

                <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                  {/* IP Address */}
                  <div>
                    <label className="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1.5">IP Address Target</label>
                    <input
                      type="text"
                      value={formData.address}
                      onChange={(e) => setFormData({ ...formData, address: e.target.value })}
                      placeholder="192.168.1.100"
                      className="w-full h-10 rounded-xl border border-gray-200 bg-gray-50/50 px-3 text-xs font-mono text-gray-800 outline-none transition focus:border-brand-500 focus:bg-white focus:ring-2 focus:ring-brand-500/10 dark:border-gray-800 dark:bg-gray-900/50 dark:text-white/90"
                      required
                    />
                  </div>

                  {/* MAC Address */}
                  <div>
                    <label className="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1.5">MAC Address</label>
                    <input
                      type="text"
                      value={formData.mac_address}
                      onChange={(e) => setFormData({ ...formData, mac_address: e.target.value.toUpperCase() })}
                      placeholder="AA:BB:CC:DD:EE:FF"
                      className="w-full h-10 rounded-xl border border-gray-200 bg-gray-50/50 px-3 text-xs font-mono text-gray-800 outline-none transition focus:border-brand-500 focus:bg-white focus:ring-2 focus:ring-brand-500/10 dark:border-gray-800 dark:bg-gray-900/50 dark:text-white/90"
                      required
                    />
                  </div>
                </div>

                <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                  {/* Interface */}
                  <div>
                    <label className="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1.5">Interface MikroTik</label>
                    <select
                      value={formData.interface}
                      onChange={(e) => setFormData({ ...formData, interface: e.target.value })}
                      className="w-full h-10 rounded-xl border border-gray-200 bg-gray-50/50 px-3 text-xs font-semibold text-gray-800 outline-none transition focus:border-brand-500 focus:bg-white focus:ring-2 focus:ring-brand-500/10 dark:border-gray-800 dark:bg-gray-900/50 dark:text-white/90 cursor-pointer"
                      required
                    >
                      {availableInterfaces.map((iface) => (
                        <option key={iface} value={iface}>
                          {iface}
                        </option>
                      ))}
                    </select>
                  </div>

                  {/* Customer Link (Optional) */}
                  <div>
                    <label className="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1.5">Hubungkan Pelanggan</label>
                    <select
                      value={formData.customer_id}
                      onChange={(e) => {
                        const val = e.target.value
                        const cust = customers.find((c) => String(c.id) === val)
                        setFormData({
                          ...formData,
                          customer_id: val,
                          address: cust?.ip ? cust.ip : formData.address,
                          mac_address: cust?.mac ? cust.mac : formData.mac_address,
                          comment: cust ? `NODERA-${cust.name}` : formData.comment,
                        })
                      }}
                      className="w-full h-10 rounded-xl border border-gray-200 bg-gray-50/50 px-3 text-xs font-semibold text-gray-800 outline-none transition focus:border-brand-500 focus:bg-white focus:ring-2 focus:ring-brand-500/10 dark:border-gray-800 dark:bg-gray-900/50 dark:text-white/90 cursor-pointer"
                    >
                      <option value="">-- Pilih Pelanggan (Opsional) --</option>
                      {customers.map((c) => (
                        <option key={c.id} value={c.id}>
                          {c.name}
                        </option>
                      ))}
                    </select>
                  </div>
                </div>

                {/* Comment */}
                <div>
                  <label className="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1.5">Keterangan / Comment</label>
                  <input
                    type="text"
                    value={formData.comment}
                    onChange={(e) => setFormData({ ...formData, comment: e.target.value })}
                    placeholder="Contoh: NODERA-STATIC-192.168.1.100"
                    className="w-full h-10 rounded-xl border border-gray-200 bg-gray-50/50 px-3 text-xs text-gray-800 outline-none transition focus:border-brand-500 focus:bg-white focus:ring-2 focus:ring-brand-500/10 dark:border-gray-800 dark:bg-gray-900/50 dark:text-white/90"
                  />
                </div>

                {/* Form Buttons */}
                <div className="flex items-center justify-end gap-2.5 pt-4 border-t border-gray-100 dark:border-gray-800/80">
                  <button
                    type="button"
                    onClick={() => setIsAddModalOpen(false)}
                    className="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:hover:bg-gray-800 text-xs font-semibold text-gray-700 dark:text-gray-200 transition shadow-2xs cursor-pointer"
                  >
                    Batal
                  </button>
                  <button
                    type="submit"
                    disabled={formSubmitting}
                    className="inline-flex items-center gap-1.5 px-4.5 py-2.5 rounded-xl bg-brand-500 hover:bg-brand-600 active:scale-95 text-xs font-bold text-white shadow-xs transition disabled:opacity-50 cursor-pointer"
                  >
                    {formSubmitting ? "Menyimpan..." : "Simpan Static Binding"}
                  </button>
                </div>
              </form>
            </div>
          </div>
        )}

        {/* ════════════════════════════════════════════════════════════════════════ */}
        {/* ── MODAL: PING DIAGNOSTIC ── */}
        {/* ════════════════════════════════════════════════════════════════════════ */}
        {isPingModalOpen && pingTarget && (
          <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs animate-in fade-in-50">
            <div className="w-full max-w-lg max-h-[92vh] flex flex-col rounded-2xl border border-gray-200 bg-white shadow-xl dark:border-gray-800 dark:bg-gray-900 text-gray-900 dark:text-white">
              <div className="flex items-center justify-between border-b border-gray-100 dark:border-gray-800/80 p-5">
                <div className="flex items-center gap-3">
                  <div className="p-2 rounded-xl bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-400">
                    <Activity className="h-5 w-5" />
                  </div>
                  <div>
                    <h3 className="font-bold text-base text-gray-900 dark:text-white">Ping Diagnostik Host</h3>
                    <p className="text-xs text-gray-500 dark:text-gray-400 font-mono">
                      Target: {pingTarget.address} ({pingTarget.mac_address})
                    </p>
                  </div>
                </div>
                <button
                  type="button"
                  onClick={() => setIsPingModalOpen(false)}
                  className="p-1.5 text-gray-400 hover:text-gray-700 dark:hover:text-white rounded-xl transition-colors cursor-pointer"
                >
                  <X className="h-5 w-5" />
                </button>
              </div>

              {/* Ping Terminal Output */}
              <div className="p-5 space-y-4">
                <div className="rounded-2xl border border-gray-200 bg-gray-950 p-4 font-mono text-xs space-y-2 min-h-[160px] max-h-[260px] overflow-y-auto text-gray-200">
                  <div className="text-gray-400 flex items-center gap-2 border-b border-gray-800 pb-2">
                    <Terminal className="h-3.5 w-3.5 text-brand-400" />
                    <span>
                      MikroTik [{pingTarget.router_name}] ping {pingTarget.address}:
                    </span>
                  </div>

                  {pingLoading ? (
                    <div className="py-6 flex flex-col items-center justify-center text-center text-gray-400 space-y-2">
                      <RefreshCw className="h-5 w-5 animate-spin text-brand-400" />
                      <span className="text-xs">Mengirim ICMP echo request ke host...</span>
                    </div>
                  ) : pingResult ? (
                    <div className="space-y-1.5">
                      {pingResult.results?.map((r: any, idx: number) => (
                        <div
                          key={idx}
                          className={cn(
                            "flex items-center justify-between text-xs py-0.5",
                            r.status === "success" ? "text-emerald-400" : "text-rose-400"
                          )}
                        >
                          <span>
                            seq={r.seq} from {r.host}: size={r.size || 56} ttl={r.ttl || 64}
                          </span>
                          <span className="font-bold">time={r.time}</span>
                        </div>
                      ))}

                      <div className="pt-2 border-t border-gray-800 text-[11px] text-gray-300">
                        <p className="font-bold text-white">{pingResult.message}</p>
                        {pingResult.packet_loss !== undefined && (
                          <p className="text-gray-400">
                            Packets: Sent = {pingResult.transmitted}, Received = {pingResult.received}, Lost ={" "}
                            {pingResult.packet_loss}%
                          </p>
                        )}
                      </div>
                    </div>
                  ) : (
                    <div className="py-6 text-center text-gray-500 text-xs">Siap melakukan ping...</div>
                  )}
                </div>

                {/* Modal Actions */}
                <div className="flex items-center justify-end gap-2 pt-2 border-t border-gray-100 dark:border-gray-800/80">
                  <button
                    type="button"
                    onClick={() => setIsPingModalOpen(false)}
                    className="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:hover:bg-gray-800 text-xs font-semibold text-gray-700 dark:text-gray-200 transition shadow-2xs cursor-pointer"
                  >
                    Tutup
                  </button>
                  <button
                    type="button"
                    disabled={pingLoading}
                    onClick={() => runPingTest(pingTarget)}
                    className="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-brand-500 hover:bg-brand-600 active:scale-95 text-xs font-bold text-white shadow-xs transition disabled:opacity-50 cursor-pointer"
                  >
                    <RefreshCw className={cn("h-3.5 w-3.5", pingLoading && "animate-spin")} />
                    Ping Lagi
                  </button>
                </div>
              </div>
            </div>
          </div>
        )}

        {/* Sync Overlay */}
        {syncInfo?.show && (
          <SyncOverlay
            show={syncInfo.show}
            title={syncInfo.title || "Sinkronisasi MikroTik"}
            statusMessage={syncInfo.statusMessage || "Memproses perintah di router..."}
          />
        )}
      </div>
    </AppLayout>
  )
}
