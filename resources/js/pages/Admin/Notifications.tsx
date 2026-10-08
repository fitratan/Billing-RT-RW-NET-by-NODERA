import { AppLayout } from "@/components/layout/app-layout"
import {
  Bell,
  Search,
  CheckCircle2,
  AlertCircle,
  Clock,
  User,
  Users,
  CreditCard,
  Wifi,
  FileText,
  Trash2,
  ChevronLeft,
  X,
  Receipt,
  Ticket,
  Server,
  Activity,
} from "lucide-react"
import { PageProps } from "@/types"
import { adminSidebarItems, adminNavItems, adminBrand } from "@/lib/admin-nav"
import { router, Link } from "@inertiajs/react"
import { useState, useMemo, useDeferredValue } from "react"
import { cn, formatDate } from "@/lib/utils"
import { MetricCard } from "@/components/tailadmin/MetricCard"
import { ViewModeSwitcher, type ViewMode } from "@/components/ui/view-mode-switcher"
import { SlidersHorizontal } from "lucide-react"

interface NotificationItem {
  id: number
  category: "billing" | "customer" | "trouble" | "system" | "general"
  title: string
  description: string
  actor: string
  created_at: string
}

export default function NotificationsPage({
  notifications = [],
}: PageProps<{
  notifications: NotificationItem[]
}>) {
  const [viewMode, setViewMode] = useState<ViewMode>("table")
  const [searchQuery, setSearchQuery] = useState("")
  const [activeFilter, setActiveFilter] = useState<string>("all")
  const [selectedNotif, setSelectedNotif] = useState<NotificationItem | null>(null)
  const deferredSearch = useDeferredValue(searchQuery)

  const safeNotifications = Array.isArray(notifications) ? notifications : []

  const filteredFeed = useMemo(() => {
    return safeNotifications.filter((item) => {
      const matchCategory = activeFilter === "all" || item.category === activeFilter
      const query = deferredSearch.toLowerCase().trim()
      const matchSearch =
        !query ||
        item.title.toLowerCase().includes(query) ||
        item.description.toLowerCase().includes(query) ||
        item.actor.toLowerCase().includes(query)
      return matchCategory && matchSearch
    })
  }, [safeNotifications, activeFilter, deferredSearch])

  const getCategoryMeta = (cat: string) => {
    switch (cat) {
      case "billing":
        return {
          icon: Receipt,
          iconColor: "text-emerald-600 dark:text-emerald-400",
          iconBg: "bg-emerald-50 dark:bg-emerald-500/10",
          badge: "bg-emerald-500 text-white font-bold text-[10px] px-2 py-0.5 rounded-md shadow-xs",
          label: "Billing",
        }
      case "customer":
        return {
          icon: Users,
          iconColor: "text-brand-600 dark:text-brand-400",
          iconBg: "bg-brand-50 dark:bg-brand-500/10",
          badge: "bg-brand-500 text-white font-bold text-[10px] px-2 py-0.5 rounded-md shadow-xs",
          label: "Pelanggan",
        }
      case "trouble":
        return {
          icon: Ticket,
          iconColor: "text-amber-600 dark:text-amber-400",
          iconBg: "bg-amber-50 dark:bg-amber-500/10",
          badge: "bg-amber-500 text-white font-bold text-[10px] px-2 py-0.5 rounded-md shadow-xs",
          label: "Tiket",
        }
      case "system":
        return {
          icon: Server,
          iconColor: "text-purple-600 dark:text-purple-400",
          iconBg: "bg-purple-50 dark:bg-purple-500/10",
          badge: "bg-purple-600 text-white font-bold text-[10px] px-2 py-0.5 rounded-md shadow-xs",
          label: "Sistem",
        }
      default:
        return {
          icon: Activity,
          iconColor: "text-gray-600 dark:text-gray-400",
          iconBg: "bg-gray-100 dark:bg-gray-800",
          badge: "bg-gray-500 text-white font-bold text-[10px] px-2 py-0.5 rounded-md shadow-xs",
          label: "Aktivitas",
        }
    }
  }

  const formatRelative = (dateStr: string) => {
    try {
      const date = new Date(dateStr)
      const now = new Date()
      const diffMs = now.getTime() - date.getTime()
      const diffMins = Math.floor(diffMs / 60000)
      const diffHours = Math.floor(diffMins / 60)
      const diffDays = Math.floor(diffHours / 24)

      if (diffMins < 1) return "Baru saja"
      if (diffMins < 60) return `${diffMins} mnt lalu`
      if (diffHours < 24) return `${diffHours} jam lalu`
      if (diffDays === 1) return "Kemarin"
      if (diffDays < 7) return `${diffDays} hari lalu`
      return formatDate(dateStr)
    } catch {
      return dateStr
    }
  }

  return (
    <AppLayout
      title="Log Aktivitas &amp; Notifikasi"
            brand={adminBrand}
      sidebarItems={adminSidebarItems}
      navItems={adminNavItems}
    >
      <div className="space-y-6">
        {/* Header Title */}
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
          <div>
            <h3 className="text-base font-bold text-gray-800 dark:text-white/90">
              Audit Trail &amp; Aktivitas
            </h3>
            <p className="text-xs text-gray-500 dark:text-gray-400">
              {safeNotifications.length} rekaman audit aktivitas terekam dalam sistem
            </p>
          </div>
        </div>

        {/* ── TOP 4 METRIC CARDS ── */}
        <div className="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
          <MetricCard
            title="Total Log Aktivitas"
            value={`${safeNotifications.length} Log`}
            sub="Riwayat transaksi audit"
            icon={Activity}
            iconBgColor="bg-brand-50 dark:bg-brand-500/10"
            iconColor="text-brand-500 dark:text-brand-400"
            badge={{ text: "Semua", color: "info" }}
          />
          <MetricCard
            title="Transaksi Billing"
            value={`${safeNotifications.filter((n) => n.category === "billing").length} Event`}
            sub="Pembayaran, tagihan, invoice"
            icon={Receipt}
            iconBgColor="bg-emerald-50 dark:bg-emerald-500/10"
            iconColor="text-emerald-600 dark:text-emerald-400"
            badge={{ text: "Finance", color: "success" }}
          />
          <MetricCard
            title="Aktivitas Pelanggan"
            value={`${safeNotifications.filter((n) => n.category === "customer").length} Event`}
            sub="Pendaftaran &amp; perubahan paket"
            icon={Users}
            iconBgColor="bg-purple-50 dark:bg-purple-500/10"
            iconColor="text-purple-600 dark:text-purple-400"
            badge={{ text: "User", color: "info" }}
          />
          <MetricCard
            title="Tiket &amp; Gangguan"
            value={`${safeNotifications.filter((n) => n.category === "trouble").length} Event`}
            sub="Laporan teknis &amp; resolusi"
            icon={Ticket}
            iconBgColor="bg-amber-50 dark:bg-amber-500/10"
            iconColor="text-amber-600 dark:text-amber-400"
            badge={{ text: "Trouble", color: "warning" }}
          />
        </div>

        {/* ── MASTER CARD CONTAINER: TOOLBAR & FEED ── */}
        <div className="p-4 sm:p-5 rounded-2xl border border-gray-200 bg-white shadow-xs dark:border-gray-800 dark:bg-white/[0.03] space-y-4">
          {/* Responsive Header Toolbar */}
          <div className="flex flex-col gap-2.5 sm:gap-3 lg:flex-row lg:items-center lg:justify-between">
            {/* Left Side: Search & Filter */}
            <div className="flex flex-wrap items-center gap-2 sm:gap-3 flex-1 min-w-0">
              <div className="relative flex-1 min-w-[180px] sm:min-w-[220px] w-full sm:w-auto">
                <Search className="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                <input
                  value={searchQuery}
                  onChange={(e) => setSearchQuery(e.target.value)}
                  placeholder="Cari aktivitas, admin, atau rincian detail..."
                  className="h-10 w-full pl-9 pr-8 rounded-xl text-xs font-medium border border-gray-200 bg-gray-50/50 hover:bg-white focus:bg-white dark:border-gray-800 dark:bg-gray-900/50 dark:hover:bg-gray-900 text-gray-900 dark:text-white placeholder-gray-400 focus:outline-none focus:border-brand-500 transition"
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

              {/* Category Select Filter Dropdown */}
              <select
                value={activeFilter}
                onChange={(e) => setActiveFilter(e.target.value)}
                className="h-10 rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 px-3 text-xs font-semibold text-gray-700 dark:text-gray-200 cursor-pointer w-full sm:w-auto shrink-0 focus:outline-none focus:border-brand-500"
              >
                <option value="all">Semua Kategori ({safeNotifications.length})</option>
                <option value="billing">Billing ({safeNotifications.filter((n) => n.category === "billing").length})</option>
                <option value="customer">Pelanggan ({safeNotifications.filter((n) => n.category === "customer").length})</option>
                <option value="trouble">Tiket &amp; Gangguan ({safeNotifications.filter((n) => n.category === "trouble").length})</option>
                <option value="system">Sistem ({safeNotifications.filter((n) => n.category === "system").length})</option>
              </select>
            </div>

            {/* Right Side: ViewModeSwitcher */}
            <div className="flex items-center gap-2 shrink-0 w-full sm:w-auto justify-end">
              <ViewModeSwitcher
                value={viewMode}
                onChange={setViewMode}
              />
            </div>
          </div>

          {/* Timeline Feed or Table View */}
          {filteredFeed.length === 0 ? (
            <div className="p-12 text-center text-gray-400 border border-dashed border-gray-200 dark:border-gray-800 rounded-2xl">
              <Bell className="mx-auto h-10 w-10 text-gray-300 dark:text-gray-600 mb-2" />
              <p className="font-semibold text-sm text-gray-700 dark:text-gray-300">Tidak ada log aktivitas</p>
              <p className="text-xs text-gray-500 dark:text-gray-400 mt-1">Belum ada aktivitas yang tercatat untuk filter ini.</p>
            </div>
          ) : viewMode === "table" ? (
            <div className="w-full overflow-x-auto custom-scrollbar">
              <table className="w-full min-w-[950px] text-start text-xs border-collapse">
                <thead>
                  <tr className="border-b border-gray-200 dark:border-gray-800 text-gray-500 dark:text-gray-400 uppercase text-[11px] font-bold">
                    <th className="py-3 px-3.5 text-left">Waktu Kejadian</th>
                    <th className="py-3 px-3.5 text-left">Kategori</th>
                    <th className="py-3 px-3.5 text-left">Judul Log</th>
                    <th className="py-3 px-3.5 text-left">Rincian Deskripsi</th>
                    <th className="py-3 px-3.5 text-left">Aktor</th>
                    <th className="py-3 px-3.5 text-center">Aksi</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-gray-100 dark:divide-gray-800/60 font-medium">
                  {filteredFeed.map((item) => {
                    const meta = getCategoryMeta(item.category)
                    const Icon = meta.icon

                    return (
                      <tr key={item.id} className="hover:bg-gray-50/60 dark:hover:bg-gray-800/30 transition">
                        <td className="py-3 px-3.5 text-gray-500 dark:text-gray-400 font-mono text-[11px] whitespace-nowrap">
                          {formatDate(item.created_at)}
                        </td>
                        <td className="py-3 px-3.5 whitespace-nowrap">
                          <span className={cn("text-xs px-2.5 py-1 font-bold rounded-lg uppercase tracking-wider inline-flex items-center gap-1.5", meta.badge)}>
                            <Icon className="size-3.5" />
                            {meta.label}
                          </span>
                        </td>
                        <td className="py-3 px-3.5 font-bold text-gray-900 dark:text-white max-w-[200px] truncate">
                          {item.title}
                        </td>
                        <td className="py-3 px-3.5 text-gray-600 dark:text-gray-300 max-w-[320px] truncate">
                          {item.description}
                        </td>
                        <td className="py-3 px-3.5 text-gray-700 dark:text-gray-300 whitespace-nowrap font-medium">
                          {item.actor || "Sistem"}
                        </td>
                        <td className="py-3 px-3.5 text-center whitespace-nowrap">
                          <button
                            type="button"
                            onClick={() => setSelectedNotif(item)}
                            className="h-9 px-3.5 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:hover:bg-gray-800 text-xs font-bold text-gray-700 dark:text-gray-200 transition shadow-2xs inline-flex items-center gap-1.5 cursor-pointer"
                          >
                            <SlidersHorizontal className="size-3.5 text-brand-500" />
                            Kelola
                          </button>
                        </td>
                      </tr>
                    )
                  })}
                </tbody>
              </table>
            </div>
          ) : (
            <div className="relative pl-6 space-y-3.5 before:absolute before:left-2.5 before:top-2 before:bottom-2 before:w-0.5 before:bg-gray-200 dark:before:bg-gray-800 pt-2">
              {filteredFeed.map((item) => {
                const meta = getCategoryMeta(item.category)
                const Icon = meta.icon

                return (
                  <div key={item.id} className="relative group">
                    {/* Timeline Dot */}
                    <div
                      className={cn(
                        "absolute -left-6 top-3.5 h-3 w-3 rounded-full border-2 border-white dark:border-gray-900",
                        meta.iconBg
                      )}
                    />

                    {/* Feed Card */}
                    <div className="p-4 rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900/40 hover:border-gray-300 dark:hover:border-gray-700 transition shadow-xs">
                      <div className="flex items-start justify-between gap-2.5">
                        <div className="flex items-center gap-2.5 min-w-0">
                          <span
                            className={cn(
                              "flex h-7 w-7 shrink-0 items-center justify-center rounded-xl",
                              meta.iconBg,
                              meta.iconColor
                            )}
                          >
                            <Icon className="h-4 w-4" />
                          </span>
                          <h4 className="text-xs font-bold text-gray-900 dark:text-white truncate">
                            {item.title}
                          </h4>
                        </div>

                        <span className="text-[11px] text-gray-500 dark:text-gray-400 font-mono shrink-0">
                          {formatRelative(item.created_at)}
                        </span>
                      </div>

                      <p className="text-xs text-gray-600 dark:text-gray-300 mt-2 leading-relaxed">
                        {item.description}
                      </p>

                      <div className="mt-3 pt-2.5 border-t border-gray-100 dark:border-gray-800 flex items-center justify-between gap-2 text-[11px] text-gray-500 dark:text-gray-400">
                        <div className="flex items-center gap-2">
                          <span>
                            Aktor: <strong className="text-gray-900 dark:text-white font-medium">{item.actor || "Sistem"}</strong>
                          </span>
                          <span className={cn("text-xs px-2.5 py-1 font-bold rounded-lg uppercase tracking-wider", meta.badge)}>
                            {meta.label}
                          </span>
                        </div>
                        <button
                          type="button"
                          onClick={() => setSelectedNotif(item)}
                          className="h-9 px-3.5 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:hover:bg-gray-800 text-xs font-bold text-gray-700 dark:text-gray-200 transition shadow-2xs inline-flex items-center gap-1.5 cursor-pointer"
                        >
                          <SlidersHorizontal className="size-3.5 text-brand-500" />
                          Kelola
                        </button>
                      </div>
                    </div>
                  </div>
                )
              })}
            </div>
          )}
        </div>

      {/* ── MODAL DETAIL / KELOLA NOTIFIKASI ── */}
      {selectedNotif && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs animate-fadeIn">
          <div className="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 w-full max-w-md max-h-[92vh] overflow-y-auto custom-scrollbar p-6 space-y-5 shadow-2xl relative">
            <button
              type="button"
              onClick={() => setSelectedNotif(null)}
              className="absolute top-4 right-4 text-gray-400 hover:text-gray-600 dark:hover:text-white p-1 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 transition cursor-pointer"
            >
              <X className="size-5" />
            </button>

            <div className="space-y-1">
              <h3 className="text-lg font-bold text-gray-900 dark:text-white">Detail Log Aktivitas</h3>
              <p className="text-xs text-gray-500 dark:text-gray-400">Informasi lengkap rekaman audit sistem</p>
            </div>

            <div className="rounded-xl border border-gray-100 bg-gray-50/80 dark:border-gray-800 dark:bg-gray-900/50 p-4 space-y-3 text-xs">
              <div className="flex justify-between items-center">
                <span className="text-gray-500 dark:text-gray-400">Kategori</span>
                <span className={cn("text-xs px-2.5 py-1 font-bold rounded-lg uppercase tracking-wider", getCategoryMeta(selectedNotif.category).badge)}>
                  {getCategoryMeta(selectedNotif.category).label}
                </span>
              </div>
              <div className="flex justify-between items-center">
                <span className="text-gray-500 dark:text-gray-400">Aktor / Eksekutor</span>
                <span className="font-bold text-gray-900 dark:text-white">{selectedNotif.actor || "Sistem"}</span>
              </div>
              <div className="flex justify-between items-center">
                <span className="text-gray-500 dark:text-gray-400">Waktu Kejadian</span>
                <span className="font-mono text-gray-700 dark:text-gray-300">{formatDate(selectedNotif.created_at)}</span>
              </div>
            </div>

            <div className="space-y-1.5">
              <span className="text-xs font-bold text-gray-700 dark:text-gray-300 block">Judul Log</span>
              <p className="text-xs font-semibold text-gray-900 dark:text-white">{selectedNotif.title}</p>
            </div>

            <div className="space-y-1.5">
              <span className="text-xs font-bold text-gray-700 dark:text-gray-300 block">Deskripsi Lengkap</span>
              <div className="rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-950 p-3.5 text-xs text-gray-800 dark:text-gray-200 leading-relaxed">
                {selectedNotif.description}
              </div>
            </div>

            <div className="pt-2 border-t border-gray-100 dark:border-gray-800 flex justify-end">
              <button
                type="button"
                onClick={() => setSelectedNotif(null)}
                className="w-full h-10 inline-flex items-center justify-center px-4 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:hover:bg-gray-800 text-xs font-semibold text-gray-700 dark:text-gray-200 transition shadow-2xs cursor-pointer"
              >
                Tutup
              </button>
            </div>
          </div>
        </div>
      )}
      </div>
    </AppLayout>
  )
}
