import { AppLayout } from "@/components/layout/app-layout"
import {
  Wifi,
  UserPlus,
  Users,
  Activity,
  Server,
  AlertTriangle,
  Search,
  X,
  Settings,
  Copy,
  Check,
} from "lucide-react"
import { EmptyState } from "@/components/ui/empty-state"
import { PageProps } from "@/types"
import { adminSidebarItems, adminNavItems, adminBrand } from "@/lib/admin-nav"
import { cn } from "@/lib/utils"
import { router, Link } from "@inertiajs/react"
import { useState, useEffect, useMemo } from "react"
import { MetricCard } from "@/components/tailadmin/MetricCard"
import { ViewModeSwitcher } from "@/components/ui/view-mode-switcher"
import { motion, AnimatePresence } from "framer-motion"

interface HotspotUser {
  [k: string]: unknown
}

export default function HotspotPage({
  users = [],
  active = [],
  error,
  routers = [],
  routerId,
  companyName = "NODERA Billing",
  tenantName,
}: PageProps<{
  users: HotspotUser[]
  active: HotspotUser[]
  error: string
  routers: { id: number; name: string }[]
  routerId: string | null
  companyName?: string
  tenantName?: string
}>) {
  const [tab, setTab] = useState<"users" | "active">("active")
  const [autoRefresh, setAutoRefresh] = useState(true)
  const [countdown, setCountdown] = useState(10)
  const [isAutoSyncing, setIsAutoSyncing] = useState(false)
  const [searchQuery, setSearchQuery] = useState("")
  const [viewMode, setViewMode] = useState<"table" | "grid">("grid")
  const [managingHotspot, setManagingHotspot] = useState<HotspotUser | null>(null)
  const [copied, setCopied] = useState(false)
  const [visibleLimit, setVisibleLimit] = useState(60)

  useEffect(() => {
    if (!autoRefresh || !routerId) return
    const interval = setInterval(() => {
      setCountdown((prev) => {
        if (prev <= 1) {
          setIsAutoSyncing(true)
          router.reload({
            only: ["users", "active", "error"],
            preserveState: true,
            onFinish: () => setIsAutoSyncing(false),
          } as any)
          return 10
        }
        return prev - 1
      })
    }, 1000)
    return () => clearInterval(interval)
  }, [autoRefresh, routerId])

  const currentList = tab === "users" ? users : active

  const filteredList = useMemo(() => {
    if (!searchQuery.trim()) return currentList
    const q = searchQuery.toLowerCase().trim()
    return currentList.filter((item) => {
      const name = String(item.name || item.user || "").toLowerCase()
      const profile = String(item.profile || "").toLowerCase()
      const ip = String(item.address || item["ip-address"] || "").toLowerCase()
      const mac = String(item["mac-address"] || "").toLowerCase()
      const comment = String(item.comment || "").toLowerCase()
      return (
        name.includes(q) ||
        profile.includes(q) ||
        ip.includes(q) ||
        mac.includes(q) ||
        comment.includes(q)
      )
    })
  }, [currentList, searchQuery])

  useEffect(() => {
    setVisibleLimit(60)
  }, [tab, routerId, searchQuery])

  const displayedList = filteredList.slice(0, visibleLimit)
  const selectedRouter = routers.find((r) => String(r.id) === String(routerId))

  const handleCopy = (text: string) => {
    navigator.clipboard.writeText(text)
    setCopied(true)
    setTimeout(() => setCopied(false), 2000)
  }

  return (
    <AppLayout
      title="Hotspot &amp; Voucher"
      brand={adminBrand}
      sidebarItems={adminSidebarItems}
      navItems={adminNavItems}
    >
      <div className="space-y-4 sm:space-y-6">
        {/* Metric Cards Row */}
        <div className="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
          <MetricCard
            title="Sesi Online Aktif"
            value={active.length}
            icon={Wifi}
            iconBgColor="bg-emerald-50 dark:bg-emerald-500/10"
            iconColor="text-emerald-500"
            description="Pengguna hotspot tersambung"
          />
          <MetricCard
            title="Database Voucher / User"
            value={users.length}
            icon={Users}
            iconBgColor="bg-blue-50 dark:bg-blue-500/10"
            iconColor="text-blue-500"
            description="Total akun terdaftar di ROS"
          />
          <MetricCard
            title="Router Gateway"
            value={selectedRouter ? selectedRouter.name : "Pilih Router"}
            icon={Server}
            iconBgColor="bg-indigo-50 dark:bg-indigo-500/10"
            iconColor="text-indigo-500"
            description={routerId ? "Tersambung ke API MikroTik" : "Belum memilih router"}
          />
          <MetricCard
            title="Status Hotspot Server"
            value={error ? "Terkendala" : "Normal"}
            icon={Activity}
            iconBgColor={error ? "bg-rose-50 dark:bg-rose-500/10" : "bg-emerald-50 dark:bg-emerald-500/10"}
            iconColor={error ? "text-rose-500" : "text-emerald-500"}
            description={tenantName || companyName}
          />
        </div>

        {/* Error Alert */}
        {error && (
          <div className="flex items-center gap-2.5 rounded-2xl border border-rose-200 bg-rose-50 p-4 text-xs font-semibold text-rose-800 dark:border-rose-900/40 dark:bg-rose-950/20 dark:text-rose-400">
            <AlertTriangle className="h-4 w-4 shrink-0 text-rose-600 dark:text-rose-400" />
            <span>{error}</span>
          </div>
        )}

        {/* ── 1-LINE RESPONSIVE INTEGRATED TOOLBAR ── */}
        <div className="rounded-2xl border border-gray-200 bg-white p-3 sm:p-4 shadow-xs dark:border-gray-800 dark:bg-white/[0.03] flex flex-col gap-2.5 sm:gap-3 lg:flex-row lg:items-center lg:justify-between">
          <div className="flex flex-wrap items-center gap-2 sm:gap-3 flex-1 min-w-0">
            {/* Search Input Bar */}
            <div className="relative flex-1 min-w-[180px] sm:min-w-[220px] w-full sm:w-auto">
              <Search className="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400 dark:text-gray-500" />
              <input
                type="text"
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                placeholder="Cari username voucher, profile, IP, MAC, komentar..."
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

            {/* Status Select */}
            <select
              value={tab}
              onChange={(e) => setTab(e.target.value as any)}
              className="h-10 rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 px-3 text-xs font-semibold text-gray-700 dark:text-gray-200 cursor-pointer w-full sm:w-auto shrink-0 shadow-2xs"
            >
              <option value="active">Sesi Online ({active.length})</option>
              <option value="users">User / Voucher ({users.length})</option>
            </select>

            {/* Router Selector */}
            {routers.length > 0 && (
              <select
                value={routerId ?? ""}
                onChange={(e) =>
                  router.get(
                    "/admin/hotspot",
                    { router: e.target.value },
                    { preserveState: true, replace: true }
                  )
                }
                className="h-10 rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 px-3 text-xs font-semibold text-gray-700 dark:text-gray-200 cursor-pointer w-full sm:w-auto shrink-0 shadow-2xs max-w-[200px] truncate"
              >
                <option value="">-- Pilih Router MikroTik --</option>
                {routers.map((r) => (
                  <option key={r.id} value={r.id}>
                    {r.name}
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
              title={autoRefresh ? "Auto Refresh Aktif - Klik untuk Matikan" : "Auto Refresh Nonaktif - Klik untuk Aktifkan"}
            >
              <span className={cn("h-2 w-2 rounded-full shrink-0", autoRefresh ? "bg-emerald-500 animate-pulse" : "bg-gray-400")} />
              <span className="hidden sm:inline">{autoRefresh ? (isAutoSyncing ? "Sync..." : `Auto (${countdown}s)`) : "Auto Refresh"}</span>
            </button>

            <div className="shrink-0">
              <ViewModeSwitcher value={viewMode} onChange={setViewMode} size="sm" />
            </div>

            <Link
              href="/admin/voucher"
              className="h-10 px-3.5 sm:px-4 rounded-xl bg-brand-500 hover:bg-brand-600 active:scale-95 text-xs font-bold text-white shadow-xs flex items-center justify-center gap-1.5 w-full sm:w-auto shrink-0 cursor-pointer"
            >
              <UserPlus className="h-4 w-4" />
              <span>Buat Voucher</span>
            </Link>
          </div>
        </div>

        {/* Master Card Container */}
        <div className="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xs dark:border-gray-800 dark:bg-white/[0.03]">
          {/* Body Content */}
          {!routerId ? (
            <div className="p-8 sm:p-12 text-center">
              <EmptyState
                icon={<Server className="h-10 w-10 text-brand-500" />}
                title="Pilih Router MikroTik"
                description="Pilih router gateway pada dropdown di atas untuk memuat daftar hotspot dan voucher aktif."
              />
            </div>
          ) : filteredList.length === 0 ? (
            <div className="p-8 sm:p-12 text-center">
              <EmptyState
                icon={<Wifi className="h-10 w-10 text-brand-500" />}
                title="Tidak Ada Data Hotspot"
                description={
                  searchQuery
                    ? `Tidak ada data yang cocok dengan kata kunci "${searchQuery}".`
                    : tab === "active"
                    ? "Belum ada sesi hotspot online yang sedang aktif di router ini."
                    : "Belum ada database user/voucher yang tersimpan di router ini."
                }
              />
            </div>
          ) : viewMode === "grid" ? (
            <div className="p-4 sm:p-6 grid gap-3.5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-3 2xl:grid-cols-4">
              {displayedList.map((u: HotspotUser, i) => {
                const uname = String(u.user ?? u.name ?? "user")
                const addr = u.address ? String(u.address) : null
                const prof = u.profile ? String(u.profile) : null
                const uptime = u.uptime ? String(u.uptime) : null
                const cardKey = String(u.id ?? u.user ?? u.name ?? i)

                return (
                  <div
                    key={cardKey}
                    className="p-4 sm:p-5 rounded-2xl border border-gray-200 bg-white hover:border-brand-500/30 dark:border-gray-800 dark:bg-white/[0.02] dark:hover:border-brand-500/30 transition-all space-y-3 group"
                  >
                    <div className="flex items-start justify-between gap-2.5">
                      <div className="flex items-center gap-3 min-w-0 flex-1">
                        <div className="relative flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-400 border border-brand-200/50 dark:border-brand-500/20 font-bold text-xs group-hover:scale-105 transition-transform">
                          <span>{uname.slice(0, 2).toUpperCase()}</span>
                          <span
                            className={cn(
                              "absolute -bottom-0.5 -right-0.5 h-2.5 w-2.5 rounded-full ring-2 ring-white dark:ring-gray-900",
                              tab === "active" ? "bg-emerald-500" : "bg-brand-500"
                            )}
                          />
                        </div>

                        <div className="min-w-0 flex-1">
                          <h4 className="font-mono font-bold text-sm text-gray-900 dark:text-white truncate group-hover:text-brand-500 transition-colors">
                            {uname}
                          </h4>
                          <div className="mt-1 flex items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400 font-mono truncate">
                            <span
                              className={cn(
                                "inline-flex items-center px-1.5 py-0.5 rounded-md text-[10px] font-bold text-white shadow-xs",
                                tab === "active" ? "bg-emerald-500" : "bg-brand-500"
                              )}
                            >
                              {tab === "active" ? "Online" : "Voucher"}
                            </span>
                            {prof && (
                              <>
                                <span>•</span>
                                <span className="text-gray-700 dark:text-gray-300 font-semibold truncate">{prof}</span>
                              </>
                            )}
                          </div>
                        </div>
                      </div>
                    </div>

                    {/* Sunken Box */}
                    <div className="rounded-xl border border-gray-100 bg-gray-50/80 p-3 space-y-1.5 text-xs dark:border-gray-800/80 dark:bg-gray-900/50">
                      {addr && (
                        <div className="flex items-center justify-between">
                          <span className="text-gray-500 dark:text-gray-400">IP Address</span>
                          <span className="font-mono text-gray-800 dark:text-gray-200 font-semibold">{addr}</span>
                        </div>
                      )}
                      {uptime && (
                        <div className="flex items-center justify-between pt-1 border-t border-gray-200/50 dark:border-gray-800/60">
                          <span className="text-gray-500 dark:text-gray-400">Durasi Aktif</span>
                          <span className="font-mono text-emerald-600 dark:text-emerald-400 font-semibold">{uptime}</span>
                        </div>
                      )}
                      {Boolean(u.comment) && (
                        <div className="flex items-center justify-between pt-1 border-t border-gray-200/50 dark:border-gray-800/60">
                          <span className="text-gray-500 dark:text-gray-400">Keterangan</span>
                          <span className="truncate max-w-[140px] text-gray-700 dark:text-gray-300">{String(u.comment)}</span>
                        </div>
                      )}
                    </div>

                    {/* Single Kelola Button */}
                    <div className="pt-1">
                      <button
                        type="button"
                        onClick={() => setManagingHotspot(u)}
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
          ) : (
            <div className="w-full overflow-x-auto custom-scrollbar">
              <table className="w-full text-left text-xs sm:text-sm min-w-[950px] border-collapse">
                <thead className="border-b border-gray-200 bg-gray-50/75 text-xs font-semibold text-gray-500 uppercase tracking-wider dark:border-gray-800 dark:bg-gray-900/50 dark:text-gray-400">
                  <tr>
                    <th className="px-4 py-3 sm:px-6">Status Sesi</th>
                    <th className="px-4 py-3">Username / Voucher</th>
                    <th className="px-4 py-3">Profil Paket</th>
                    <th className="px-4 py-3">IP Address</th>
                    <th className="px-4 py-3">MAC Address</th>
                    <th className="px-4 py-3">Durasi Uptime</th>
                    <th className="px-4 py-3 text-end sm:px-6">Aksi</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-gray-100 dark:divide-gray-800/60">
                  {displayedList.map((u: HotspotUser, i) => {
                    const uname = String(u.user ?? u.name ?? "user")
                    const addr = u.address ? String(u.address) : "-"
                    const prof = u.profile ? String(u.profile) : "-"
                    const uptime = u.uptime ? String(u.uptime) : "-"
                    const mac = u["mac-address"] ? String(u["mac-address"]) : "-"

                    return (
                      <tr key={i} className="hover:bg-gray-50/50 dark:hover:bg-white/[0.02] transition-colors">
                        <td className="px-4 py-3.5 sm:px-6">
                          <span
                            className={cn(
                              "inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold text-white shadow-xs whitespace-nowrap",
                              tab === "active" ? "bg-emerald-500" : "bg-brand-500"
                            )}
                          >
                            {tab === "active" ? "Online" : "Voucher"}
                          </span>
                        </td>
                        <td className="px-4 py-3.5 font-mono font-bold text-gray-900 dark:text-white">
                          {uname}
                        </td>
                        <td className="px-4 py-3.5">
                          <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold bg-brand-500 text-white shadow-xs whitespace-nowrap">
                            {prof}
                          </span>
                        </td>
                        <td className="px-4 py-3.5 font-mono text-xs text-gray-700 dark:text-gray-300">
                          {addr}
                        </td>
                        <td className="px-4 py-3.5 font-mono text-xs text-gray-500 dark:text-gray-400">
                          {mac}
                        </td>
                        <td className="px-4 py-3.5 font-mono text-xs text-gray-700 dark:text-gray-300">
                          {uptime}
                        </td>
                        <td className="px-4 py-3.5 sm:px-6 text-end">
                          <button
                            type="button"
                            onClick={() => setManagingHotspot(u)}
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
          )}

          {/* Pagination Footer */}
          {currentList.length > visibleLimit && (
            <div className="flex flex-col sm:flex-row items-center justify-between gap-3 p-4 border-t border-gray-100 dark:border-gray-800/80 bg-gray-50/50 dark:bg-gray-900/30">
              <p className="text-xs text-gray-500 dark:text-gray-400 font-medium">
                Menampilkan <strong className="text-gray-900 dark:text-white font-bold">{displayedList.length}</strong> dari{" "}
                <strong className="text-gray-900 dark:text-white font-bold">{currentList.length}</strong> data hotspot
              </p>
              <div className="flex items-center gap-2">
                <button
                  type="button"
                  onClick={() => setVisibleLimit((prev) => prev + 60)}
                  className="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:hover:bg-gray-800 text-xs font-semibold text-gray-700 dark:text-gray-200 transition shadow-2xs cursor-pointer"
                >
                  +60 Data Berikutnya
                </button>
                <button
                  type="button"
                  onClick={() => setVisibleLimit(currentList.length)}
                  className="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-brand-500 hover:bg-brand-600 active:scale-95 text-xs font-bold text-white shadow-xs transition cursor-pointer"
                >
                  Tampilkan Semua ({currentList.length})
                </button>
              </div>
            </div>
          )}
        </div>
      </div>

      {/* POP-UP MODAL KELOLA HOTSPOT */}
      <AnimatePresence>
        {managingHotspot && (
          <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
            <motion.div
              initial={{ opacity: 0 }}
              animate={{ opacity: 1 }}
              exit={{ opacity: 0 }}
              onClick={() => setManagingHotspot(null)}
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
                    <Wifi className="h-5 w-5" />
                  </div>
                  <div>
                    <h3 className="text-base font-bold text-gray-800 dark:text-white font-mono">
                      {String(managingHotspot.user ?? managingHotspot.name ?? "User")}
                    </h3>
                    <div className="flex items-center gap-2 mt-1">
                      <span
                        className={cn(
                          "inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold text-white shadow-xs",
                          tab === "active" ? "bg-emerald-500" : "bg-brand-500"
                        )}
                      >
                        {tab === "active" ? "Online" : "Voucher"}
                      </span>
                      {managingHotspot.profile !== undefined && managingHotspot.profile !== null && (
                        <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold bg-amber-500 text-white shadow-xs whitespace-nowrap">
                          {String(managingHotspot.profile)}
                        </span>
                      )}
                    </div>
                  </div>
                </div>
                <button
                  type="button"
                  onClick={() => setManagingHotspot(null)}
                  className="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 dark:text-gray-500 transition cursor-pointer"
                >
                  <X className="h-5 w-5" />
                </button>
              </div>

              {/* Info Details */}
              <div className="space-y-2.5">
                <div className="rounded-xl border border-gray-100 bg-gray-50/80 dark:border-gray-800 dark:bg-gray-900/50 p-3 space-y-2 text-xs">
                  <div className="flex justify-between items-center py-1 border-b border-gray-200/50 dark:border-gray-800/50">
                    <span className="text-gray-500 dark:text-gray-400 font-medium">IP Address</span>
                    <strong className="font-mono text-gray-800 dark:text-gray-200">
                      {String(managingHotspot.address || managingHotspot["ip-address"] || "-")}
                    </strong>
                  </div>
                  {managingHotspot["mac-address"] !== undefined && managingHotspot["mac-address"] !== null && (
                    <div className="flex justify-between items-center py-1 border-b border-gray-200/50 dark:border-gray-800/50">
                      <span className="text-gray-500 dark:text-gray-400 font-medium">MAC Address</span>
                      <span className="font-mono text-gray-800 dark:text-gray-200">
                        {String(managingHotspot["mac-address"])}
                      </span>
                    </div>
                  )}
                  {managingHotspot.uptime !== undefined && managingHotspot.uptime !== null && (
                    <div className="flex justify-between items-center py-1 border-b border-gray-200/50 dark:border-gray-800/50">
                      <span className="text-gray-500 dark:text-gray-400 font-medium">Durasi Uptime Sesi</span>
                      <span className="font-mono font-bold text-emerald-600 dark:text-emerald-400">
                        {String(managingHotspot.uptime)}
                      </span>
                    </div>
                  )}
                  {managingHotspot["bytes-in"] !== undefined && (
                    <div className="flex justify-between items-center py-1 border-b border-gray-200/50 dark:border-gray-800/50">
                      <span className="text-gray-500 dark:text-gray-400 font-medium">Traffic In / Out</span>
                      <span className="font-mono text-gray-800 dark:text-gray-200">
                        ↓ {String(managingHotspot["bytes-in"] || "0")} / ↑ {String(managingHotspot["bytes-out"] || "0")}
                      </span>
                    </div>
                  )}
                  {managingHotspot.comment !== undefined && managingHotspot.comment !== null && (
                    <div className="flex justify-between items-center py-1">
                      <span className="text-gray-500 dark:text-gray-400 font-medium">Komentar / Note</span>
                      <span className="text-gray-800 dark:text-gray-200">
                        {String(managingHotspot.comment)}
                      </span>
                    </div>
                  )}
                </div>
              </div>

              {/* Action Buttons */}
              <div className="space-y-2 pt-2">
                <button
                  type="button"
                  onClick={() => {
                    const text = String(managingHotspot.user ?? managingHotspot.name ?? "")
                    handleCopy(text)
                  }}
                  className="w-full inline-flex items-center justify-center gap-1.5 px-3.5 py-2.5 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:hover:bg-gray-800 text-xs font-semibold text-gray-700 dark:text-gray-200 transition shadow-2xs cursor-pointer"
                >
                  {copied ? <Check className="h-4 w-4 text-emerald-500" /> : <Copy className="h-4 w-4 text-gray-400" />}
                  <span>{copied ? "Kode Voucher Tersalin!" : "Salin Username / Kode Voucher"}</span>
                </button>

                <Link
                  href="/admin/voucher"
                  className="w-full inline-flex items-center justify-center gap-1.5 px-3.5 py-2.5 rounded-xl bg-brand-500 hover:bg-brand-600 active:scale-95 text-xs font-bold text-white shadow-xs transition cursor-pointer"
                >
                  <UserPlus className="h-4 w-4" />
                  <span>Kelola Database Voucher</span>
                </Link>
              </div>
            </motion.div>
          </div>
        )}
      </AnimatePresence>
    </AppLayout>
  )
}
