import { useState, useMemo } from "react"
import { AppLayout } from "@/components/layout/app-layout"
import {
  Server,
  UsersIcon,
  Activity,
  Settings,
  X,
  ExternalLink,
  Search,
} from "lucide-react"
import { EmptyState } from "@/components/ui/empty-state"
import { PageProps } from "@/types"
import { adminSidebarItems, adminNavItems, adminBrand } from "@/lib/admin-nav"
import { MetricCard } from "@/components/tailadmin/MetricCard"
import { ViewModeSwitcher } from "@/components/ui/view-mode-switcher"
import { Link } from "@inertiajs/react"
import { motion, AnimatePresence } from "framer-motion"

interface Router {
  id: number
  name: string
  host: string
  customers_count: number
}

export default function MikrotikDisplayPage({ routers = [] }: PageProps<{ routers: Router[] }>) {
  const [managingRouter, setManagingRouter] = useState<Router | null>(null)
  const [search, setSearch] = useState("")
  const [viewMode, setViewMode] = useState<"table" | "grid">("grid")

  const filtered = useMemo(() => {
    if (!search.trim()) return routers
    const q = search.toLowerCase()
    return routers.filter(
      (r) => r.name.toLowerCase().includes(q) || r.host.toLowerCase().includes(q)
    )
  }, [routers, search])

  const totalCustomers = routers.reduce((acc, r) => acc + (r.customers_count || 0), 0)
  const maxRouter = routers.length > 0 ? [...routers].sort((a, b) => (b.customers_count || 0) - (a.customers_count || 0))[0] : null

  return (
    <AppLayout
      title="MikroTik Display"
      brand={adminBrand}
      sidebarItems={adminSidebarItems}
      navItems={adminNavItems}
    >
      <div className="space-y-4 sm:space-y-6">
        {/* Metric Cards */}
        <div className="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
          <MetricCard
            title="Total Router"
            value={routers.length}
            icon={Server}
            iconBgColor="bg-blue-50 dark:bg-blue-500/10"
            iconColor="text-blue-500"
            description="Perangkat gateway aktif"
          />
          <MetricCard
            title="Total Pelanggan"
            value={totalCustomers}
            icon={UsersIcon}
            iconBgColor="bg-indigo-50 dark:bg-indigo-500/10"
            iconColor="text-indigo-500"
            description="Terhubung ke seluruh router"
          />
          <MetricCard
            title="Router Terpadat"
            value={maxRouter ? maxRouter.name : "-"}
            icon={Activity}
            iconBgColor="bg-amber-50 dark:bg-amber-500/10"
            iconColor="text-amber-500"
            description={maxRouter ? `${maxRouter.customers_count} Pelanggan` : "Belum ada router"}
          />
          <MetricCard
            title="Konektivitas Network"
            value="Online"
            icon={Activity}
            iconBgColor="bg-emerald-50 dark:bg-emerald-500/10"
            iconColor="text-emerald-500"
            description="Fleet MikroTik terintegrasi"
          />
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
                placeholder="Cari nama router atau host IP..."
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

            <Link
              href="/admin/mikrotik/routers"
              className="h-10 px-3.5 sm:px-4 rounded-xl bg-brand-500 hover:bg-brand-600 active:scale-95 text-xs font-bold text-white shadow-xs flex items-center justify-center gap-1.5 w-full sm:w-auto shrink-0 cursor-pointer"
            >
              <Server className="h-4 w-4" />
              <span>Kelola Fleet</span>
            </Link>
          </div>
        </div>

        {/* Master Container */}
        <div className="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xs dark:border-gray-800 dark:bg-white/[0.03]">
          {filtered.length === 0 ? (
            <div className="p-8 sm:p-12 text-center">
              <EmptyState
                icon={<Server className="h-10 w-10 text-brand-500" />}
                title="Belum Ada Router MikroTik"
                description={search ? "Tidak ada router yang cocok dengan pencarian." : "Tambahkan router MikroTik pertama Anda di menu Manajemen Router."}
              />
            </div>
          ) : viewMode === "grid" ? (
            <div className="p-4 sm:p-6 grid gap-3.5 sm:grid-cols-2 lg:grid-cols-3">
              {filtered.map((r: Router) => (
                <div
                  key={r.id}
                  className="p-4 sm:p-5 rounded-2xl border border-gray-200 bg-white hover:border-brand-500/30 dark:border-gray-800 dark:bg-white/[0.02] dark:hover:border-brand-500/30 transition-all space-y-3 group"
                >
                  <div className="flex items-center gap-3">
                    <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-400 border border-brand-200/50 dark:border-brand-500/20 font-bold group-hover:scale-105 transition-transform">
                      <Server className="h-5 w-5" />
                    </div>
                    <div className="min-w-0 flex-1">
                      <p className="truncate text-sm font-bold text-gray-900 dark:text-white group-hover:text-brand-500 transition-colors">
                        {r.name}
                      </p>
                      <p className="truncate font-mono text-xs text-gray-500 dark:text-gray-400">
                        {r.host}
                      </p>
                    </div>
                    <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-bold bg-brand-500 text-white shadow-xs shrink-0 whitespace-nowrap">
                      <UsersIcon className="h-3 w-3" />
                      <span>{r.customers_count}</span>
                    </span>
                  </div>

                  <div className="pt-2 border-t border-gray-100 dark:border-gray-800/80 flex items-center justify-between gap-2">
                    <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold bg-emerald-500 text-white shadow-xs whitespace-nowrap">
                      Online
                    </span>
                    <button
                      type="button"
                      onClick={() => setManagingRouter(r)}
                      className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200 font-bold text-xs transition cursor-pointer"
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
              <table className="w-full text-left text-xs sm:text-sm min-w-[950px] border-collapse">
                <thead className="border-b border-gray-200 bg-gray-50/75 text-xs font-semibold text-gray-500 uppercase tracking-wider dark:border-gray-800 dark:bg-gray-900/50 dark:text-gray-400">
                  <tr>
                    <th className="px-4 py-3 sm:px-6">Nama Router</th>
                    <th className="px-4 py-3 sm:px-6">IP Host / Domain</th>
                    <th className="px-4 py-3 sm:px-6">Pelanggan Terikat</th>
                    <th className="px-4 py-3 sm:px-6">Status Koneksi</th>
                    <th className="px-4 py-3 sm:px-6">Integrasi API</th>
                    <th className="px-4 py-3 sm:px-6 text-right">Aksi</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-gray-100 dark:divide-gray-800/60">
                  {filtered.map((r: Router) => (
                    <tr
                      key={r.id}
                      className="hover:bg-gray-50/50 dark:hover:bg-white/[0.02] transition-colors"
                    >
                      <td className="px-4 py-3.5 sm:px-6 font-bold text-gray-900 dark:text-white">
                        <div className="flex items-center gap-2">
                          <Server className="h-4 w-4 text-brand-500 shrink-0" />
                          <span>{r.name}</span>
                        </div>
                      </td>
                      <td className="px-4 py-3.5 sm:px-6 font-mono font-semibold text-gray-700 dark:text-gray-300">
                        {r.host}
                      </td>
                      <td className="px-4 py-3.5 sm:px-6">
                        <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-md text-[11px] font-bold bg-brand-500 text-white shadow-xs">
                          <UsersIcon className="h-3 w-3" />
                          <span>{r.customers_count} Pelanggan</span>
                        </span>
                      </td>
                      <td className="px-4 py-3.5 sm:px-6">
                        <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold bg-emerald-500 text-white shadow-xs whitespace-nowrap">
                          Online
                        </span>
                      </td>
                      <td className="px-4 py-3.5 sm:px-6 text-xs text-gray-600 dark:text-gray-300">
                        RouterOS API Active
                      </td>
                      <td className="px-4 py-3.5 sm:px-6 text-right">
                        <button
                          type="button"
                          onClick={() => setManagingRouter(r)}
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
          )}
        </div>
      </div>

      {/* POP-UP MODAL KELOLA ROUTER DISPLAY */}
      <AnimatePresence>
        {managingRouter && (
          <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
            <motion.div
              initial={{ opacity: 0 }}
              animate={{ opacity: 1 }}
              exit={{ opacity: 0 }}
              onClick={() => setManagingRouter(null)}
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
                    <h3 className="text-base font-bold text-gray-800 dark:text-white">
                      {managingRouter.name}
                    </h3>
                    <div className="flex items-center gap-2 mt-1">
                      <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold bg-emerald-500 text-white shadow-xs whitespace-nowrap">
                        Aktif
                      </span>
                      <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold bg-brand-500 text-white shadow-xs whitespace-nowrap">
                        {managingRouter.customers_count} Pelanggan
                      </span>
                    </div>
                  </div>
                </div>
                <button
                  type="button"
                  onClick={() => setManagingRouter(null)}
                  className="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 dark:text-gray-500 transition cursor-pointer"
                >
                  <X className="h-5 w-5" />
                </button>
              </div>

              {/* Info Details */}
              <div className="space-y-2.5">
                <div className="rounded-xl border border-gray-100 bg-gray-50/80 dark:border-gray-800 dark:bg-gray-900/50 p-3 space-y-2 text-xs">
                  <div className="flex justify-between items-center py-1 border-b border-gray-200/50 dark:border-gray-800/50">
                    <span className="text-gray-500 dark:text-gray-400 font-medium">Host / IP Gateway</span>
                    <strong className="font-mono text-gray-800 dark:text-gray-200">
                      {managingRouter.host}
                    </strong>
                  </div>
                  <div className="flex justify-between items-center py-1 border-b border-gray-200/50 dark:border-gray-800/50">
                    <span className="text-gray-500 dark:text-gray-400 font-medium">Total Pelanggan Terikat</span>
                    <span className="font-bold text-brand-600 dark:text-brand-400 font-mono">
                      {managingRouter.customers_count} Sesi
                    </span>
                  </div>
                  <div className="flex justify-between items-center py-1">
                    <span className="text-gray-500 dark:text-gray-400 font-medium">Status Integrasi</span>
                    <span className="font-semibold text-emerald-600 dark:text-emerald-400">
                      Tersambung API MikroTik
                    </span>
                  </div>
                </div>
              </div>

              {/* Action Buttons */}
              <div className="space-y-2 pt-2">
                <Link
                  href={`/admin/mikrotik/router/${managingRouter.id}`}
                  className="w-full inline-flex items-center justify-center gap-1.5 px-3.5 py-2.5 rounded-xl bg-brand-500 hover:bg-brand-600 active:scale-95 text-xs font-bold text-white shadow-xs transition cursor-pointer"
                >
                  <ExternalLink className="h-4 w-4" />
                  <span>Buka Telemetri &amp; Detail Router</span>
                </Link>

                <Link
                  href={`/admin/pppoe?search=${encodeURIComponent(managingRouter.name)}`}
                  className="w-full inline-flex items-center justify-center gap-1.5 px-3.5 py-2.5 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:hover:bg-gray-800 text-xs font-semibold text-gray-700 dark:text-gray-200 transition shadow-2xs cursor-pointer"
                >
                  <UsersIcon className="h-4 w-4 text-brand-500" />
                  <span>Lihat Pelanggan PPPoE</span>
                </Link>
              </div>
            </motion.div>
          </div>
        )}
      </AnimatePresence>
    </AppLayout>
  )
}
