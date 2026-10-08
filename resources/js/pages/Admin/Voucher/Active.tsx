import { useState, useMemo, useEffect } from "react"
import { AppLayout } from "@/components/layout/app-layout"
import {
  UserCheck,
  Search,
  Clock,
  ArrowDownCircle,
  ArrowUpCircle,
  AlertCircle,
  Wifi,
  Power,
  Activity,
  X,
  Server,
  HardDrive,
  Settings,
  Play,
} from "lucide-react"
import { PageProps } from "@/types"
import { adminSidebarItems, adminNavItems, adminBrand } from "@/lib/admin-nav"
import { router } from "@inertiajs/react"
import { cn } from "@/lib/utils"
import MetricCard from "@/components/tailadmin/MetricCard"
import { ViewModeSwitcher } from "@/components/ui/view-mode-switcher"

interface HotspotActiveUser {
  id?: string
  user: string
  profile?: string
  address: string
  mac_address?: string
  uptime?: string
  session_time_left?: string
  idle_time?: string
  bytes_in?: number | string
  bytes_out?: number | string
  limit_bytes_total?: number | string
  server?: string
  server_profile?: string
  login_by?: string
}

function formatBytes(bytes: any = 0) {
  const num = parseInt(bytes) || 0
  if (num === 0) return "0 B"
  const k = 1024
  const sizes = ["B", "KB", "MB", "GB", "TB"]
  const i = Math.floor(Math.log(num) / Math.log(k))
  return parseFloat((num / Math.pow(k, i)).toFixed(2)) + " " + sizes[i]
}

export default function AdminVoucherActivePage({
  routers = [],
  selectedRouterId = null,
  activeUsers = [],
  totalActive = 0,
  servers = [],
  serverProfiles = [],
  mikrotikError = null,
}: PageProps<{
  routers: { id: number; name: string; host: string }[]
  selectedRouterId: number | null
  activeUsers: HotspotActiveUser[]
  totalActive: number
  servers: string[]
  serverProfiles: string[]
  mikrotikError: string | null
}>) {
  const [viewMode, setViewMode] = useState<"table" | "grid">("table")
  const [search, setSearch] = useState("")
  const [selectedServerFilter, setSelectedServerFilter] = useState("")
  const [autoRefresh, setAutoRefresh] = useState(true)
  const [cleaningExpired, setCleaningExpired] = useState(false)
  const [disconnectingId, setDisconnectingId] = useState<string | null>(null)
  const [manageUser, setManageUser] = useState<HotspotActiveUser | null>(null)

  const [countdown, setCountdown] = useState(10)
  const [isAutoSyncing, setIsAutoSyncing] = useState(false)

  // Auto Refresh interval with countdown (10s)
  useEffect(() => {
    if (!autoRefresh) return
    const timer = setInterval(() => {
      setCountdown((prev) => {
        if (prev <= 1) {
          setIsAutoSyncing(true)
          router.reload({
            only: ["activeUsers", "totalActive", "mikrotikError"],
            preserveState: true,
            onFinish: () => setIsAutoSyncing(false),
          } as any)
          return 10
        }
        return prev - 1
      })
    }, 1000)
    return () => clearInterval(timer)
  }, [autoRefresh])

  const handleDisconnect = (u: HotspotActiveUser) => {
    if (!confirm(`Putus koneksi sesi login '${u.user}'?`)) return
    const key = u.id || u.user
    setDisconnectingId(key)
    router.post(
      "/admin/voucher/active/disconnect",
      {
        id: u.id,
        user: u.user,
        router_id: selectedRouterId,
      },
      {
        preserveScroll: true,
        onFinish: () => {
          setDisconnectingId(null)
          setManageUser(null)
        },
      }
    )
  }

  const handleCleanExpired = () => {
    if (!confirm("Putus semua sesi pengguna yang waktu aktifnya sudah expired (0s)?")) return
    setCleaningExpired(true)
    router.post(
      "/admin/voucher/active/clean-expired",
      {
        router_id: selectedRouterId,
      },
      {
        preserveScroll: true,
        onFinish: () => setCleaningExpired(false),
      }
    )
  }

  const handleRouterChange = (routerId: string) => {
    router.get(`/admin/voucher/active?router_id=${routerId}`)
  }

  const availableServerFilters = useMemo(() => {
    const set = new Set<string>()
    serverProfiles.forEach((p) => p && set.add(p))
    servers.forEach((s) => s && set.add(s))
    activeUsers.forEach((u) => {
      if (u.server_profile) set.add(u.server_profile)
      if (u.server) set.add(u.server)
    })
    return Array.from(set).filter(Boolean)
  }, [serverProfiles, servers, activeUsers])

  const filteredUsers = useMemo(() => {
    const q = search.toLowerCase().trim()
    return activeUsers.filter((u) => {
      const matchSearch =
        !q ||
        (u.user && u.user.toLowerCase().includes(q)) ||
        (u.profile && u.profile.toLowerCase().includes(q)) ||
        (u.address && u.address.toLowerCase().includes(q)) ||
        (u.mac_address && u.mac_address.toLowerCase().includes(q)) ||
        (u.server && u.server.toLowerCase().includes(q)) ||
        (u.server_profile && u.server_profile.toLowerCase().includes(q)) ||
        (u.login_by && u.login_by.toLowerCase().includes(q))

      const matchServer =
        !selectedServerFilter ||
        u.server_profile === selectedServerFilter ||
        u.server === selectedServerFilter

      return matchSearch && matchServer
    })
  }, [activeUsers, search, selectedServerFilter])

  const [visibleLimit, setVisibleLimit] = useState(60)

  useEffect(() => {
    setVisibleLimit(60)
  }, [search, selectedServerFilter])

  const displayedUsers = useMemo(() => filteredUsers.slice(0, visibleLimit), [filteredUsers, visibleLimit])

  const totalBytesIn = useMemo(() => {
    return activeUsers.reduce((acc, u) => acc + (parseInt(String(u.bytes_in || 0)) || 0), 0)
  }, [activeUsers])

  const totalBytesOut = useMemo(() => {
    return activeUsers.reduce((acc, u) => acc + (parseInt(String(u.bytes_out || 0)) || 0), 0)
  }, [activeUsers])

  const expiredCount = useMemo(() => {
    return activeUsers.filter((u) => u.session_time_left === "0s").length
  }, [activeUsers])

  return (
    <AppLayout
      title="Pengguna Aktif Hotspot"
      brand={adminBrand}
      sidebarItems={adminSidebarItems}
      navItems={adminNavItems}
    >
      <div className="space-y-6">
        {/* Header Actions & Router Selector */}
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
          <div>
            <h3 className="text-base font-bold text-gray-800 dark:text-white/90">
              Live Sesi Hotspot
            </h3>
            <p className="text-xs text-gray-500 dark:text-gray-400">
              {totalActive} pengguna terhubung aktif di jaringan hotspot
            </p>
          </div>

          <div className="flex items-center gap-2 flex-wrap sm:flex-nowrap">
            {routers.length > 0 && (
              <select
                value={selectedRouterId ? String(selectedRouterId) : ""}
                onChange={(e) => handleRouterChange(e.target.value)}
                className="h-10 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:hover:bg-gray-800 px-3 text-xs font-semibold text-gray-700 dark:text-gray-200 transition focus:outline-none focus:border-brand-500 shadow-2xs"
              >
                {routers.map((r) => (
                  <option key={r.id} value={r.id}>
                    {r.name}
                  </option>
                ))}
              </select>
            )}

            <button
              type="button"
              onClick={handleCleanExpired}
              disabled={cleaningExpired}
              className="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-amber-500 hover:bg-amber-600 text-white text-xs font-bold transition disabled:opacity-50 shadow-xs active:scale-95"
              title="Putus seluruh sesi login yang sisa waktunya sudah habis"
            >
              <Play className={`h-4 w-4 ${cleaningExpired ? "animate-spin" : ""}`} />
              <span>Kick Expired ({expiredCount})</span>
            </button>
          </div>
        </div>

        {mikrotikError && (
          <div className="flex items-center gap-2.5 rounded-2xl border border-amber-200 bg-amber-50 dark:border-amber-900/40 dark:bg-amber-950/20 p-4 text-xs text-amber-800 dark:text-amber-300">
            <AlertCircle className="h-4 w-4 shrink-0" />
            <p className="truncate">Peringatan MikroTik: {mikrotikError}</p>
          </div>
        )}

        {/* ── TOP 4 KPI METRICS ── */}
        <div className="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
          <MetricCard
            title="Pengguna Aktif"
            value={totalActive}
            sub="Sesi login aktif"
            icon={Activity}
            iconBgColor="bg-emerald-50 dark:bg-emerald-500/10"
            iconColor="text-emerald-600 dark:text-emerald-400"
            badge={{ text: "Online", color: "success" }}
          />
          <MetricCard
            title="Total Download"
            value={formatBytes(totalBytesIn)}
            sub="Traffic Rx agregat"
            icon={ArrowDownCircle}
            iconBgColor="bg-brand-50 dark:bg-brand-500/10"
            iconColor="text-brand-500 dark:text-brand-400"
            badge={{ text: "Download", color: "info" }}
          />
          <MetricCard
            title="Total Upload"
            value={formatBytes(totalBytesOut)}
            sub="Traffic Tx agregat"
            icon={ArrowUpCircle}
            iconBgColor="bg-purple-50 dark:bg-purple-500/10"
            iconColor="text-purple-600 dark:text-purple-400"
            badge={{ text: "Upload", color: "info" }}
          />
          <MetricCard
            title="Sesi Habis Waktu"
            value={expiredCount}
            sub="Sisa waktu 0 detik"
            icon={Clock}
            iconBgColor="bg-amber-50 dark:bg-amber-500/10"
            iconColor="text-amber-600 dark:text-amber-400"
            badge={{ text: "Expired", color: "warning" }}
          />
        </div>

        {/* ── TOOLBAR: SEARCH + SERVER FILTER + AUTO REFRESH ── */}
        <div className="rounded-2xl border border-gray-200 bg-white p-3 sm:p-4 shadow-xs dark:border-gray-800 dark:bg-white/[0.03] flex flex-col gap-2.5 sm:gap-3 lg:flex-row lg:items-center lg:justify-between">
          {/* Left Side: Search & Filter */}
          <div className="flex flex-wrap items-center gap-2 sm:gap-3 flex-1 min-w-0">
            <div className="relative flex-1 min-w-[180px] sm:min-w-[220px] w-full sm:w-auto">
              <Search className="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
              <input
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                placeholder="Cari user, IP, MAC address, server..."
                className="h-10 w-full pl-9 pr-8 rounded-xl text-xs font-medium border border-gray-200 bg-gray-50/50 hover:bg-white focus:bg-white dark:border-gray-800 dark:bg-gray-900/50 dark:hover:bg-gray-900 text-gray-900 dark:text-white placeholder-gray-400 focus:outline-none focus:border-brand-500 transition"
              />
              {search && (
                <button
                  type="button"
                  onClick={() => setSearch("")}
                  className="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"
                >
                  <X className="h-3.5 w-3.5" />
                </button>
              )}
            </div>

            {/* Filter Server Profile */}
            <select
              value={selectedServerFilter}
              onChange={(e) => setSelectedServerFilter(e.target.value)}
              className="h-10 rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 px-3 text-xs font-semibold text-gray-700 dark:text-gray-200 cursor-pointer w-full sm:w-auto shrink-0 focus:outline-none focus:border-brand-500"
            >
              <option value="">Semua Server</option>
              {availableServerFilters.map((sp) => (
                <option key={sp} value={sp}>
                  {sp}
                </option>
              ))}
            </select>
          </div>

          {/* Right Side: ViewModeSwitcher & Auto Refresh */}
          <div className="flex items-center gap-2 shrink-0 w-full sm:w-auto justify-end">
            <ViewModeSwitcher value={viewMode} onChange={setViewMode} />

            {/* Auto Refresh Toggle */}
            <button
              type="button"
              onClick={() => setAutoRefresh(!autoRefresh)}
              className={cn(
                "h-10 px-3.5 text-xs font-semibold gap-1.5 rounded-xl shrink-0 flex items-center justify-center border transition shadow-2xs cursor-pointer",
                autoRefresh
                  ? "border-emerald-500 bg-emerald-500 text-white font-bold"
                  : "border-gray-200 bg-white hover:bg-gray-50 text-gray-700 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800"
              )}
              title="Auto Refresh data tiap 10 detik"
            >
              <div className={cn("h-2 w-2 rounded-full shrink-0", autoRefresh ? "bg-white animate-pulse" : "bg-gray-400")} />
              <span className="font-mono text-[11px] font-bold">
                {autoRefresh ? (isAutoSyncing ? "Sync..." : `Live ${countdown}s`) : "Auto Off"}
              </span>
            </button>
          </div>
        </div>

        {/* ── ACTIVE USERS DATA (TABLE / GRID) ── */}
        {filteredUsers.length === 0 ? (
          <div className="rounded-2xl border border-dashed border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900/30 p-12 text-center space-y-2">
            <UserCheck className="mx-auto h-10 w-10 text-gray-400 dark:text-gray-500 mb-1" />
            <h3 className="text-sm font-bold text-gray-800 dark:text-white">Tidak ada pengguna hotspot yang sedang aktif</h3>
            <p className="text-xs text-gray-500 dark:text-gray-400 max-w-sm mx-auto">
              {search || selectedServerFilter
                ? "Tidak ada sesi aktif yang cocok dengan kata kunci atau filter server terpilih."
                : "Ketika voucher digunakan login oleh pengguna, sesi aktif akan muncul di sini secara real-time."}
            </p>
          </div>
        ) : viewMode === "table" ? (
          <div className="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xs dark:border-gray-800 dark:bg-white/[0.03]">
            <div className="w-full overflow-x-auto custom-scrollbar">
              <table className="w-full min-w-[1050px] text-left text-xs border-collapse">
                <thead className="border-b border-gray-200 bg-gray-50/75 text-[11px] font-bold text-gray-500 uppercase tracking-wider dark:border-gray-800 dark:bg-gray-900/50 dark:text-gray-400">
                  <tr>
                    <th className="py-3 px-4">User / Voucher</th>
                    <th className="py-3 px-4">IP Address</th>
                    <th className="py-3 px-4">MAC Address</th>
                    <th className="py-3 px-4">Profil Hotspot</th>
                    <th className="py-3 px-4">Waktu Aktif (Uptime)</th>
                    <th className="py-3 px-4">Sisa Sesi</th>
                    <th className="py-3 px-4">Download</th>
                    <th className="py-3 px-4">Upload</th>
                    <th className="py-3 px-4">Server</th>
                    <th className="py-3 px-4">Status</th>
                    <th className="py-3 px-4 text-center">Aksi</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-gray-100 dark:divide-gray-800/60">
                  {displayedUsers.map((u, i) => {
                    const cardKey = String(u.id || u.user || i)
                    return (
                      <tr key={cardKey} className="hover:bg-gray-50/50 dark:hover:bg-white/[0.02] transition-colors">
                        <td className="py-3 px-4 font-mono font-bold text-gray-900 dark:text-white">
                          <div className="flex items-center gap-2">
                            <div className="flex h-7 w-7 items-center justify-center rounded-lg bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-400 shrink-0">
                              <Wifi className="h-3.5 w-3.5" />
                            </div>
                            <span>{u.user}</span>
                          </div>
                        </td>
                        <td className="py-3 px-4 font-mono text-gray-700 dark:text-gray-300">
                          {u.address}
                        </td>
                        <td className="py-3 px-4 font-mono text-gray-500 dark:text-gray-400">
                          {u.mac_address || "-"}
                        </td>
                        <td className="py-3 px-4">
                          <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold bg-brand-500 text-white shadow-xs whitespace-nowrap">
                            {u.profile || "default"}
                          </span>
                        </td>
                        <td className="py-3 px-4 font-mono text-gray-700 dark:text-gray-300">
                          {u.uptime || "-"}
                        </td>
                        <td className="py-3 px-4 font-mono">
                          <span
                            className={cn(
                              "font-semibold",
                              u.session_time_left === "0s"
                                ? "text-rose-600 dark:text-rose-400 font-bold"
                                : "text-amber-600 dark:text-amber-400"
                            )}
                          >
                            {u.session_time_left || "Unlimited"}
                          </span>
                        </td>
                        <td className="py-3 px-4 font-mono font-semibold text-emerald-600 dark:text-emerald-400">
                          {formatBytes(u.bytes_out)}
                        </td>
                        <td className="py-3 px-4 font-mono font-semibold text-sky-600 dark:text-sky-400">
                          {formatBytes(u.bytes_in)}
                        </td>
                        <td className="py-3 px-4 text-gray-600 dark:text-gray-300">
                          <span className="truncate max-w-[100px] block">{u.server || "default"}</span>
                        </td>
                        <td className="py-3 px-4">
                          <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold bg-emerald-500 text-white shadow-xs whitespace-nowrap">
                            Online
                          </span>
                        </td>
                        <td className="py-3 px-4 text-center">
                          <button
                            type="button"
                            onClick={() => setManageUser(u)}
                            className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200 font-bold text-xs transition cursor-pointer"
                            title="Kelola"
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
          </div>
        ) : (
          <div className="grid gap-3.5 grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-3 2xl:grid-cols-4 items-start">
            {displayedUsers.map((u, i) => {
              const cardKey = String(u.id || u.user || i)

              return (
                <div
                  key={cardKey}
                  className="p-4 sm:p-5 rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03] shadow-xs space-y-3.5 transition hover:border-gray-300 dark:hover:border-gray-700"
                >
                  <div>
                    {/* Header */}
                    <div className="flex items-start justify-between gap-2">
                      <div className="flex items-center gap-2.5 min-w-0 flex-1">
                        <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-400 font-bold text-xs">
                          <Wifi className="h-4 w-4" />
                        </div>
                        <div className="min-w-0 flex-1">
                          <p className="font-mono text-xs font-bold text-gray-900 dark:text-white truncate">{u.user}</p>
                          <div className="mt-0.5 flex items-center gap-1.5 text-[11px] text-gray-500 dark:text-gray-400 font-medium">
                            <span className="font-mono text-gray-700 dark:text-gray-300 truncate">{u.address}</span>
                            <span>•</span>
                            <span className="inline-flex items-center px-1.5 py-0.2 rounded text-[10px] font-bold bg-brand-500 text-white shadow-xs truncate">
                              {u.profile || "default"}
                            </span>
                          </div>
                        </div>
                      </div>

                      <button
                        type="button"
                        onClick={() => setManageUser(u)}
                        className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200 font-bold text-xs transition cursor-pointer shrink-0"
                      >
                        <Settings className="h-3.5 w-3.5" />
                        <span>Kelola</span>
                      </button>
                    </div>

                    {/* Compact Metric Bar */}
                    <div className="mt-3 grid grid-cols-2 gap-2 rounded-xl border border-gray-100 bg-gray-50/80 dark:border-gray-800 dark:bg-gray-900/50 p-2.5 text-xs text-gray-600 dark:text-gray-300">
                      <div className="flex flex-col justify-center min-w-0">
                        <span className="text-[10px] text-gray-400 dark:text-gray-500 block mb-0.5">Uptime</span>
                        <div className="font-mono font-bold text-gray-900 dark:text-white text-xs truncate">
                          {u.uptime || "-"}
                        </div>
                      </div>
                      <div className="text-right flex flex-col justify-center min-w-0">
                        <span className="text-[10px] text-gray-400 dark:text-gray-500 block mb-0.5">Sisa Waktu</span>
                        <span
                          className={cn(
                            "font-mono font-semibold truncate",
                            u.session_time_left === "0s" ? "text-rose-600 dark:text-rose-400 font-bold" : "text-amber-600 dark:text-amber-400"
                          )}
                        >
                          {u.session_time_left || "Unlimited"}
                        </span>
                      </div>
                    </div>
                  </div>
                </div>
              )
            })}
          </div>
        )}
      </div>

      {/* ── MODAL: KELOLA SESI AKTIF HOTSPOT ── */}
      {manageUser && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
          <div className="fixed inset-0 bg-black/70 backdrop-blur-sm" onClick={() => setManageUser(null)} />
          <div className="relative w-full max-w-md max-h-[92vh] flex flex-col rounded-2xl border border-gray-200 bg-white shadow-2xl dark:border-gray-800 dark:bg-gray-900 text-gray-900 dark:text-white overflow-hidden animate-in fade-in zoom-in-95 duration-200">
            <div className="flex items-center justify-between border-b border-gray-100 dark:border-gray-800 px-5 py-4 shrink-0">
              <div className="flex items-center gap-2.5">
                <div className="flex h-9 w-9 items-center justify-center rounded-xl bg-brand-500/10 text-brand-500">
                  <Activity className="h-5 w-5" />
                </div>
                <div>
                  <h3 className="text-sm font-bold">Kelola Sesi Hotspot</h3>
                  <p className="text-[11px] text-gray-500 dark:text-gray-400">Detail telemetri sesi dan pemutusan koneksi</p>
                </div>
              </div>
              <button
                type="button"
                onClick={() => setManageUser(null)}
                className="rounded-lg p-1.5 text-gray-400 hover:text-gray-600 hover:bg-gray-100 dark:hover:text-white dark:hover:bg-gray-800"
              >
                <X className="h-4 w-4" />
              </button>
            </div>

            <div className="flex-1 overflow-y-auto p-5 space-y-4">
              <div className="rounded-xl border border-gray-100 bg-gray-50/80 dark:border-gray-800 dark:bg-gray-900/50 p-4 space-y-3">
                <div className="flex items-center justify-between">
                  <span className="text-xs text-gray-500 dark:text-gray-400">Username Hotspot</span>
                  <span className="font-mono font-bold text-sm text-gray-900 dark:text-white">{manageUser.user}</span>
                </div>
                <div className="flex items-center justify-between border-t border-gray-200/60 dark:border-gray-800/60 pt-2">
                  <span className="text-xs text-gray-500 dark:text-gray-400">IP Address</span>
                  <span className="font-mono font-semibold text-xs text-gray-900 dark:text-white">{manageUser.address}</span>
                </div>
                <div className="flex items-center justify-between border-t border-gray-200/60 dark:border-gray-800/60 pt-2">
                  <span className="text-xs text-gray-500 dark:text-gray-400">Profil Paket</span>
                  <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold bg-brand-500 text-white shadow-xs whitespace-nowrap">
                    {manageUser.profile || "default"}
                  </span>
                </div>
              </div>

              <div className="space-y-2 text-xs text-gray-600 dark:text-gray-300">
                <div className="flex items-center justify-between py-1.5 border-b border-gray-100 dark:border-gray-800">
                  <span className="text-gray-500 dark:text-gray-400">MAC Address</span>
                  <span className="font-mono text-gray-900 dark:text-white font-medium">{manageUser.mac_address || "-"}</span>
                </div>
                <div className="flex items-center justify-between py-1.5 border-b border-gray-100 dark:border-gray-800">
                  <span className="text-gray-500 dark:text-gray-400">Uptime</span>
                  <span className="font-mono font-bold text-gray-900 dark:text-white">{manageUser.uptime || "-"}</span>
                </div>
                <div className="flex items-center justify-between py-1.5 border-b border-gray-100 dark:border-gray-800">
                  <span className="text-gray-500 dark:text-gray-400">Sisa Waktu</span>
                  <span
                    className={cn(
                      "font-mono font-bold",
                      manageUser.session_time_left === "0s" ? "text-rose-600 dark:text-rose-400" : "text-amber-600 dark:text-amber-400"
                    )}
                  >
                    {manageUser.session_time_left || "Unlimited"}
                  </span>
                </div>
                <div className="flex items-center justify-between py-1.5 border-b border-gray-100 dark:border-gray-800">
                  <span className="text-gray-500 dark:text-gray-400">Download (Rx)</span>
                  <span className="font-mono text-emerald-600 dark:text-emerald-400 font-bold">{formatBytes(manageUser.bytes_in)}</span>
                </div>
                <div className="flex items-center justify-between py-1.5 border-b border-gray-100 dark:border-gray-800">
                  <span className="text-gray-500 dark:text-gray-400">Upload (Tx)</span>
                  <span className="font-mono text-brand-600 dark:text-brand-400 font-bold">{formatBytes(manageUser.bytes_out)}</span>
                </div>
                <div className="flex items-center justify-between py-1.5 border-b border-gray-100 dark:border-gray-800">
                  <span className="text-gray-500 dark:text-gray-400">Server / Profile</span>
                  <span className="font-mono text-purple-600 dark:text-purple-400 font-medium">
                    {manageUser.server || "all"} / {manageUser.server_profile || "-"}
                  </span>
                </div>
                <div className="flex items-center justify-between py-1.5 border-b border-gray-100 dark:border-gray-800">
                  <span className="text-gray-500 dark:text-gray-400">Idle Time</span>
                  <span className="font-mono text-gray-900 dark:text-white">{manageUser.idle_time || "-"}</span>
                </div>
                <div className="flex items-center justify-between py-1.5 border-b border-gray-100 dark:border-gray-800">
                  <span className="text-gray-500 dark:text-gray-400">Metode Login</span>
                  <span className="font-mono text-gray-900 dark:text-white uppercase font-semibold">{manageUser.login_by || "http-chap"}</span>
                </div>
              </div>

              {/* Action Buttons */}
              <div className="space-y-2 pt-2 border-t border-gray-100 dark:border-gray-800">
                <button
                  type="button"
                  onClick={() => handleDisconnect(manageUser)}
                  disabled={disconnectingId === (manageUser.id || manageUser.user)}
                  className="w-full inline-flex items-center justify-center gap-1.5 px-3.5 py-2.5 rounded-xl bg-rose-500 hover:bg-rose-600 active:scale-95 text-xs font-bold text-white shadow-xs transition disabled:opacity-50"
                >
                  <Power className="h-4 w-4" />
                  <span>{disconnectingId === (manageUser.id || manageUser.user) ? "Memutus Sesi..." : "Putus Sesi (Kick Hotspot)"}</span>
                </button>

                <button
                  type="button"
                  onClick={() => setManageUser(null)}
                  className="w-full inline-flex items-center justify-center px-4 py-2 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:hover:bg-gray-800 text-xs font-semibold text-gray-700 dark:text-gray-200 transition shadow-2xs"
                >
                  Tutup
                </button>
              </div>
            </div>
          </div>
        </div>
      )}
    </AppLayout>
  )
}