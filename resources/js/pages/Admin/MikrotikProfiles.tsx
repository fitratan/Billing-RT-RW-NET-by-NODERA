import { useState, useMemo, useDeferredValue } from "react"
import { AppLayout } from "@/components/layout/app-layout"
import {
  Server,
  Gauge,
  Activity,
  Layers,
  Search,
  ChevronLeft,
  HardDrive,
  Settings,
  X,
  Copy,
  Check,
} from "lucide-react"
import { cn } from "@/lib/utils"
import { EmptyState } from "@/components/ui/empty-state"
import { PageProps } from "@/types"
import { adminSidebarItems, adminNavItems, adminBrand } from "@/lib/admin-nav"
import { Link } from "@inertiajs/react"
import { MetricCard } from "@/components/tailadmin/MetricCard"
import { ViewModeSwitcher } from "@/components/ui/view-mode-switcher"
import { motion, AnimatePresence } from "framer-motion"

export default function MikrotikProfilesPage({
  profiles = [],
}: PageProps<{
  profiles: Record<string, unknown>[]
  companyName?: string
  tenantName?: string
}>) {
  const [searchQuery, setSearchQuery] = useState("")
  const [viewMode, setViewMode] = useState<"table" | "grid">("grid")
  const [managingProfile, setManagingProfile] = useState<Record<string, unknown> | null>(null)
  const [copied, setCopied] = useState(false)
  const deferredSearch = useDeferredValue(searchQuery)

  // Filtered profiles
  const filteredProfiles = useMemo(() => {
    const q = deferredSearch.trim().toLowerCase()
    if (!q) return profiles

    return profiles.filter((p) => {
      const name = String(p.name ?? "").toLowerCase()
      const rate = String(p.rate_limit ?? "").toLowerCase()
      const local = String(p["local-address"] ?? "").toLowerCase()
      const remote = String(p["remote-address"] ?? "").toLowerCase()
      return (
        name.includes(q) ||
        rate.includes(q) ||
        local.includes(q) ||
        remote.includes(q)
      )
    })
  }, [profiles, deferredSearch])

  // Stats calculation
  const stats = useMemo(() => {
    const total = profiles.length
    const withRate = profiles.filter((p) => Boolean(p.rate_limit)).length
    const withLocal = profiles.filter((p) => Boolean(p["local-address"])).length
    const withRemote = profiles.filter((p) => Boolean(p["remote-address"])).length

    return { total, withRate, withLocal, withRemote }
  }, [profiles])

  const handleCopy = (text: string) => {
    navigator.clipboard.writeText(text)
    setCopied(true)
    setTimeout(() => setCopied(false), 2000)
  }

  return (
    <AppLayout
      title="Profil Paket MikroTik"
      brand={adminBrand}
      sidebarItems={adminSidebarItems}
      navItems={adminNavItems}
    >
      <div className="space-y-4 sm:space-y-6">
        {/* Metric Cards Grid */}
        <div className="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
          <MetricCard
            title="Total Profil"
            value={stats.total}
            icon={Layers}
            iconBgColor="bg-blue-50 dark:bg-blue-500/10"
            iconColor="text-blue-500"
            description="Profil PPPoE terdaftar"
          />
          <MetricCard
            title="Profil Rate Limit"
            value={stats.withRate}
            icon={Gauge}
            iconBgColor="bg-indigo-50 dark:bg-indigo-500/10"
            iconColor="text-indigo-500"
            description="Dibatasi bandwidth"
          />
          <MetricCard
            title="Local Address"
            value={stats.withLocal}
            icon={HardDrive}
            iconBgColor="bg-emerald-50 dark:bg-emerald-500/10"
            iconColor="text-emerald-500"
            description="Static gateway terpasang"
          />
          <MetricCard
            title="Remote IP Pool"
            value={stats.withRemote}
            icon={Activity}
            iconBgColor="bg-amber-50 dark:bg-amber-500/10"
            iconColor="text-amber-500"
            description="Pool IP dinamik terikat"
          />
        </div>

        {/* ── 1-LINE RESPONSIVE INTEGRATED TOOLBAR ── */}
        <div className="rounded-2xl border border-gray-200 bg-white p-3 sm:p-4 shadow-xs dark:border-gray-800 dark:bg-white/[0.03] flex flex-col gap-2.5 sm:gap-3 lg:flex-row lg:items-center lg:justify-between">
          <div className="flex flex-wrap items-center gap-2 sm:gap-3 flex-1 min-w-0">
            <Link
              href="/admin/mikrotik/routers"
              className="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-gray-200 bg-white text-gray-700 hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800 transition shadow-2xs cursor-pointer"
              title="Kembali ke Daftar Router"
            >
              <ChevronLeft className="h-4 w-4" />
            </Link>

            <div className="relative flex-1 min-w-[180px] sm:min-w-[220px] w-full sm:w-auto">
              <Search className="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400 dark:text-gray-500" />
              <input
                type="text"
                placeholder="Cari nama profil, rate limit, IP pool..."
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
          </div>

          <div className="flex items-center gap-2 shrink-0 w-full sm:w-auto justify-end">
            <div className="shrink-0">
              <ViewModeSwitcher value={viewMode} onChange={setViewMode} size="sm" />
            </div>
          </div>
        </div>

        {/* Master Card Table / Grid Stream */}
        <div className="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xs dark:border-gray-800 dark:bg-white/[0.03]">
          {/* Body content */}
          {filteredProfiles.length === 0 ? (
            <div className="p-8 sm:p-12 text-center">
              <EmptyState
                icon={<Gauge className="h-10 w-10 text-brand-500" />}
                title="Tidak ada profil ditemukan"
                description={
                  searchQuery
                    ? `Tidak ada profil yang cocok dengan kata kunci "${searchQuery}"`
                    : "Belum ada konfigurasi profile PPPoE pada router MikroTik Anda."
                }
              />
            </div>
          ) : viewMode === "grid" ? (
            <div className="p-4 sm:p-6 grid gap-3.5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-3 2xl:grid-cols-4">
              {filteredProfiles.map((p, i) => {
                const nameStr = String(p.name ?? "profile")
                const rateStr = p.rate_limit ? String(p.rate_limit) : null
                const localStr = p["local-address"] ? String(p["local-address"]) : null
                const remoteStr = p["remote-address"] ? String(p["remote-address"]) : null

                return (
                  <div
                    key={i}
                    className="p-4 sm:p-5 rounded-2xl border border-gray-200 bg-white hover:border-brand-500/30 dark:border-gray-800 dark:bg-white/[0.02] dark:hover:border-brand-500/30 transition-all space-y-3 group"
                  >
                    <div className="flex items-center gap-3">
                      <div className="relative flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-brand-50 dark:bg-brand-500/10 border border-brand-200/50 dark:border-brand-500/20 text-brand-600 dark:text-brand-400 font-bold text-xs group-hover:scale-105 transition-transform">
                        <Server className="h-5 w-5" />
                        <span className="absolute -bottom-0.5 -right-0.5 h-2.5 w-2.5 rounded-full bg-emerald-500 ring-2 ring-white dark:ring-gray-900 whitespace-nowrap" />
                      </div>

                      <div className="min-w-0 flex-1">
                        <h4 className="font-mono font-bold text-sm text-gray-900 dark:text-white truncate group-hover:text-brand-500 transition-colors">
                          {nameStr}
                        </h4>
                        <div className="mt-1 flex items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400 font-mono truncate">
                          <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold bg-emerald-500 text-white shadow-xs whitespace-nowrap">
                            MikroTik ROS
                          </span>
                          {rateStr && (
                            <>
                              <span>•</span>
                              <span className="text-brand-600 dark:text-brand-400 font-bold">{rateStr}</span>
                            </>
                          )}
                        </div>
                      </div>
                    </div>

                    {/* Detail Sunken Box */}
                    <div className="rounded-xl border border-gray-100 bg-gray-50/80 p-3 space-y-1.5 text-xs dark:border-gray-800/80 dark:bg-gray-900/50">
                      <div className="flex items-center justify-between">
                        <span className="text-gray-500 dark:text-gray-400">Rate Limit</span>
                        <span className="font-mono font-semibold text-gray-900 dark:text-gray-200">
                          {rateStr || "Unlimited"}
                        </span>
                      </div>
                      {localStr && (
                        <div className="flex items-center justify-between pt-1 border-t border-gray-200/50 dark:border-gray-800/60">
                          <span className="text-gray-500 dark:text-gray-400">Local Address</span>
                          <span className="font-mono text-gray-800 dark:text-gray-300">{localStr}</span>
                        </div>
                      )}
                      {remoteStr && (
                        <div className="flex items-center justify-between pt-1 border-t border-gray-200/50 dark:border-gray-800/60">
                          <span className="text-gray-500 dark:text-gray-400">Remote Pool</span>
                          <span className="font-mono text-gray-800 dark:text-gray-300">{remoteStr}</span>
                        </div>
                      )}
                    </div>

                    {/* Single Kelola Button */}
                    <div className="pt-1">
                      <button
                        type="button"
                        onClick={() => setManagingProfile(p)}
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
                    <th className="px-4 py-3 sm:px-6">Nama Profil</th>
                    <th className="px-4 py-3 sm:px-6">Rate Limit (Bandwidth)</th>
                    <th className="px-4 py-3 sm:px-6">Local Address (Gateway)</th>
                    <th className="px-4 py-3 sm:px-6">Remote Address (IP Pool)</th>
                    <th className="px-4 py-3 sm:px-6">Tipe Router</th>
                    <th className="px-4 py-3 sm:px-6 text-right">Aksi</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-gray-100 dark:divide-gray-800/60">
                  {filteredProfiles.map((p, i) => {
                    const nameStr = String(p.name ?? "profile")
                    const rateStr = p.rate_limit ? String(p.rate_limit) : null
                    const localStr = p["local-address"] ? String(p["local-address"]) : "-"
                    const remoteStr = p["remote-address"] ? String(p["remote-address"]) : "-"

                    return (
                      <tr
                        key={i}
                        className="hover:bg-gray-50/50 dark:hover:bg-white/[0.02] transition-colors"
                      >
                        <td className="px-4 py-3.5 sm:px-6 font-mono font-bold text-gray-900 dark:text-white">
                          <div className="flex items-center gap-2">
                            <Server className="h-4 w-4 text-brand-500 shrink-0" />
                            <span>{nameStr}</span>
                          </div>
                        </td>
                        <td className="px-4 py-3.5 sm:px-6">
                          {rateStr ? (
                            <span className="inline-flex items-center gap-1 font-mono font-bold text-brand-600 dark:text-brand-400">
                              <Gauge className="h-3.5 w-3.5" />
                              {rateStr}
                            </span>
                          ) : (
                            <span className="text-gray-400 dark:text-gray-500">Unlimited</span>
                          )}
                        </td>
                        <td className="px-4 py-3.5 sm:px-6 font-mono text-gray-700 dark:text-gray-300">
                          {localStr}
                        </td>
                        <td className="px-4 py-3.5 sm:px-6 font-mono text-gray-700 dark:text-gray-300">
                          {remoteStr}
                        </td>
                        <td className="px-4 py-3.5 sm:px-6">
                          <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold bg-emerald-500 text-white shadow-xs whitespace-nowrap">
                            MikroTik RouterOS
                          </span>
                        </td>
                        <td className="px-4 py-3.5 sm:px-6 text-right">
                          <button
                            type="button"
                            onClick={() => setManagingProfile(p)}
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
        </div>
      </div>

      {/* MODAL KELOLA PROFIL MIKROTIK */}
      <AnimatePresence>
        {managingProfile && (
          <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
            <motion.div
              initial={{ opacity: 0 }}
              animate={{ opacity: 1 }}
              exit={{ opacity: 0 }}
              onClick={() => setManagingProfile(null)}
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
                    <Server className="h-5 w-5" />
                  </div>
                  <div>
                    <h3 className="text-base font-bold text-gray-800 dark:text-white font-mono">
                      {String(managingProfile.name ?? "Profile")}
                    </h3>
                    <div className="flex items-center gap-2 mt-1">
                      <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold bg-emerald-500 text-white shadow-xs whitespace-nowrap">
                        MikroTik ROS
                      </span>
                    </div>
                  </div>
                </div>
                <button
                  type="button"
                  onClick={() => setManagingProfile(null)}
                  className="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 dark:text-gray-500 transition cursor-pointer"
                >
                  <X className="h-5 w-5" />
                </button>
              </div>

              {/* Info Details */}
              <div className="space-y-2.5">
                <div className="rounded-xl border border-gray-100 bg-gray-50/80 dark:border-gray-800 dark:bg-gray-900/50 p-3 space-y-2 text-xs">
                  <div className="flex justify-between items-center py-1 border-b border-gray-200/50 dark:border-gray-800/50">
                    <span className="text-gray-500 dark:text-gray-400 font-medium">Rate Limit</span>
                    <strong className="font-mono text-sm font-bold text-brand-600 dark:text-brand-400">
                      {String(managingProfile.rate_limit || "Unlimited")}
                    </strong>
                  </div>
                  <div className="flex justify-between items-center py-1 border-b border-gray-200/50 dark:border-gray-800/50">
                    <span className="text-gray-500 dark:text-gray-400 font-medium">Local Address (Gateway)</span>
                    <span className="font-mono font-bold text-gray-800 dark:text-gray-200">
                      {String(managingProfile["local-address"] || "-")}
                    </span>
                  </div>
                  <div className="flex justify-between items-center py-1 border-b border-gray-200/50 dark:border-gray-800/50">
                    <span className="text-gray-500 dark:text-gray-400 font-medium">Remote Pool (IP Pelanggan)</span>
                    <span className="font-mono font-bold text-gray-800 dark:text-gray-200">
                      {String(managingProfile["remote-address"] || "-")}
                    </span>
                  </div>
                  {managingProfile["dns-server"] !== undefined && managingProfile["dns-server"] !== null && (
                    <div className="flex justify-between items-center py-1 border-b border-gray-200/50 dark:border-gray-800/50">
                      <span className="text-gray-500 dark:text-gray-400 font-medium">DNS Server</span>
                      <span className="font-mono text-gray-800 dark:text-gray-200">
                        {String(managingProfile["dns-server"])}
                      </span>
                    </div>
                  )}
                  {managingProfile["parent-queue"] !== undefined && managingProfile["parent-queue"] !== null && (
                    <div className="flex justify-between items-center py-1">
                      <span className="text-gray-500 dark:text-gray-400 font-medium">Parent Queue</span>
                      <span className="font-mono text-gray-800 dark:text-gray-200">
                        {String(managingProfile["parent-queue"])}
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
                    const text = String(managingProfile.rate_limit || managingProfile.name || "")
                    handleCopy(text)
                  }}
                  className="w-full inline-flex items-center justify-center gap-1.5 px-3.5 py-2.5 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:hover:bg-gray-800 text-xs font-semibold text-gray-700 dark:text-gray-200 transition shadow-2xs cursor-pointer"
                >
                  {copied ? <Check className="h-4 w-4 text-emerald-500" /> : <Copy className="h-4 w-4 text-gray-400" />}
                  <span>{copied ? "Tersalin ke Clipboard!" : "Salin Konfigurasi Rate Limit"}</span>
                </button>

                <Link
                  href={`/admin/pppoe?search=${encodeURIComponent(String(managingProfile.name ?? ""))}`}
                  className="w-full inline-flex items-center justify-center gap-1.5 px-3.5 py-2.5 rounded-xl bg-brand-500 hover:bg-brand-600 active:scale-95 text-xs font-bold text-white shadow-xs transition cursor-pointer"
                >
                  <Search className="h-4 w-4" />
                  <span>Lihat Pelanggan dengan Profil Ini</span>
                </Link>
              </div>
            </motion.div>
          </div>
        )}
      </AnimatePresence>
    </AppLayout>
  )
}
