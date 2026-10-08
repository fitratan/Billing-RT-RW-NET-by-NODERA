import React, { useState } from "react"
import { Head, router } from "@inertiajs/react"
import {
  Key,
  Copy,
  Check,
  Download,
  Search,
  Laptop,
  CheckCircle2,
  XCircle,
  Clock,
  AlertTriangle,
  Cpu,
  ExternalLink,
} from "lucide-react"
import { AppLayout } from "@/components/layout/app-layout"
import { MetricCard } from "@/components/tailadmin/MetricCard"
import { Badge } from "@/components/ui/badge"
import { EmptyState } from "@/components/ui/empty-state"
import { cn } from "@/lib/utils"

interface DesktopLicense {
  id: number
  license_key: string
  product_name: string
  hwid: string | null
  device_name: string | null
  os_info: string | null
  ip_address: string | null
  status: "PENDING" | "ACTIVE" | "EXPIRED" | "SUSPENDED" | "REVOKED"
  activation_count: number
  max_activations: number
  activated_at: string | null
  expires_at: string | null
  last_heartbeat_at: string | null
  notes: string | null
  created_at: string
}

interface Props {
  licenses: {
    data: DesktopLicense[]
    current_page: number
    last_page: number
    total: number
  }
  filters: {
    search?: string
    status?: string
  }
  metrics: {
    total: number
    active: number
    expired: number
  }
  tenant?: any
}

export default function TenantDesktopLicenses({
  licenses,
  filters,
  metrics,
}: Props) {
  const [search, setSearch] = useState(filters.search || "")
  const [statusFilter, setStatusFilter] = useState(filters.status || "")
  const [copiedKey, setCopiedKey] = useState<string | null>(null)

  const handleCopy = (text: string) => {
    navigator.clipboard.writeText(text)
    setCopiedKey(text)
    setTimeout(() => setCopiedKey(null), 2000)
  }

  const handleSearch = (e: React.FormEvent) => {
    e.preventDefault()
    router.get(
      "/admin/desktop-licenses",
      { search, status: statusFilter },
      { preserveState: true }
    )
  }

  const handleStatusChange = (status: string) => {
    setStatusFilter(status)
    router.get(
      "/admin/desktop-licenses",
      { search, status },
      { preserveState: true }
    )
  }

  const getStatusBadge = (status: string, expiresAt: string | null) => {
    if (status === "ACTIVE") {
      if (expiresAt && new Date(expiresAt) < new Date()) {
        return (
          <Badge variant="destructive" className="flex items-center gap-1 font-bold">
            <Clock className="w-3 h-3" />
            KADALUWARSA
          </Badge>
        )
      }
      return (
        <Badge variant="outline" className="bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-400 dark:border-emerald-800 flex items-center gap-1 font-bold">
          <CheckCircle2 className="w-3 h-3" />
          AKTIF
        </Badge>
      )
    }
    if (status === "PENDING") {
      return (
        <Badge variant="outline" className="bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/40 dark:text-amber-400 dark:border-amber-800 flex items-center gap-1 font-bold">
          <Clock className="w-3 h-3" />
          BELUM AKTIF
        </Badge>
      )
    }
    if (status === "SUSPENDED") {
      return (
        <Badge variant="destructive" className="flex items-center gap-1 font-bold">
          <AlertTriangle className="w-3 h-3" />
          DITANGGUHKAN
        </Badge>
      )
    }
    return (
      <Badge variant="destructive" className="flex items-center gap-1 font-bold">
        <XCircle className="w-3 h-3" />
        {status}
      </Badge>
    )
  }

  return (
    <AppLayout>
      <Head title="Mikhmon Offline (Lisensi Desktop) — NODERA" />

      <div className="p-4 sm:p-6 lg:p-8 space-y-6 max-w-7xl mx-auto">
        {/* Header Banner */}
        <div className="relative overflow-hidden rounded-2xl bg-gradient-to-br from-brand-900 via-brand-800 to-slate-900 p-6 sm:p-8 text-white shadow-xl">
          <div className="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
            <div className="space-y-2 max-w-2xl">
              <div className="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white/10 backdrop-blur-md text-xs font-semibold text-brand-200 border border-white/10">
                <Laptop className="w-3.5 h-3.5" />
                Mikhmon Standalone Desktop App (.exe)
              </div>
              <h1 className="text-2xl sm:text-3xl font-extrabold tracking-tight">
                Mikhmon Offline (Lisensi Desktop)
              </h1>
              <p className="text-sm text-brand-100/80 leading-relaxed">
                Gunakan aplikasi Mikhmon Desktop standalone langsung di komputer kasir / admin Anda tanpa perlu install web server eksternal. Hubungkan langsung ke IP & Port MikroTik lokal secara instan.
              </p>
            </div>

            <div className="flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
              <a
                href="https://github.com/fitratan/MIKHMON-by-NODERA/archive/refs/heads/main.zip"
                target="_blank"
                rel="noopener noreferrer"
                className="inline-flex items-center justify-center gap-2 px-5 py-3 rounded-xl bg-white text-brand-900 font-bold text-sm shadow-lg hover:bg-brand-50 transition active:scale-95"
              >
                <Download className="w-4 h-4" />
                Download Mikhmon Desktop (.zip)
              </a>
              <a
                href="https://github.com/fitratan/MIKHMON-by-NODERA"
                target="_blank"
                rel="noopener noreferrer"
                className="inline-flex items-center justify-center gap-2 px-4 py-3 rounded-xl bg-white/10 text-white hover:bg-white/20 font-semibold text-sm border border-white/20 transition active:scale-95"
              >
                <ExternalLink className="w-4 h-4" />
                GitHub Repository
              </a>
            </div>
          </div>
          <div className="absolute -right-12 -bottom-12 w-64 h-64 bg-brand-500/20 rounded-full blur-3xl pointer-events-none" />
        </div>

        {/* Metrics Overview */}
        <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
          <MetricCard
            title="Total Lisensi Saya"
            value={metrics.total.toString()}
            icon={Key}
          />
          <MetricCard
            title="Lisensi Aktif"
            value={metrics.active.toString()}
            icon={CheckCircle2}
          />
          <MetricCard
            title="Lisensi Kadaluwarsa"
            value={metrics.expired.toString()}
            icon={Clock}
          />
        </div>

        {/* Search & Filters */}
        <div className="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-4 bg-white dark:bg-gray-900 p-4 rounded-xl border border-gray-100 dark:border-gray-800 shadow-xs">
          <form onSubmit={handleSearch} className="relative flex-1">
            <Search className="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
            <input
              type="text"
              placeholder="Cari license key, HWID, atau nama perangkat..."
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              className="w-full pl-10 pr-4 py-2 text-sm rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-800 focus:bg-white dark:focus:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 transition"
            />
          </form>

          <div className="flex items-center gap-2 overflow-x-auto">
            {["", "ACTIVE", "PENDING", "EXPIRED"].map((status) => (
              <button
                key={status}
                type="button"
                onClick={() => handleStatusChange(status)}
                className={cn(
                  "px-3 py-1.5 text-xs font-semibold rounded-lg transition shrink-0",
                  statusFilter === status
                    ? "bg-brand-500 text-white shadow-xs"
                    : "bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700"
                )}
              >
                {status === ""
                  ? "Semua"
                  : status === "ACTIVE"
                  ? "Aktif"
                  : status === "PENDING"
                  ? "Belum Aktif"
                  : "Expired"}
              </button>
            ))}
          </div>
        </div>

        {/* License Table / List */}
        <div className="bg-white dark:bg-gray-900 rounded-2xl border border-gray-100 dark:border-gray-800 shadow-xs overflow-hidden">
          {licenses.data.length === 0 ? (
            <div className="p-12 text-center">
              <EmptyState
                icon={<Key className="w-12 h-12 text-gray-400" />}
                title="Belum Ada Lisensi Mikhmon Desktop"
                description="Anda belum memiliki lisensi Mikhmon Desktop offline. Hubungi Superadmin atau order lisensi baru untuk mengaktifkan aplikasi desktop di komputer kasir Anda."
              />
            </div>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full text-left text-sm text-gray-600 dark:text-gray-300">
                <thead className="bg-gray-50/75 dark:bg-gray-800/60 text-xs uppercase font-bold text-gray-500 dark:text-gray-400 border-b border-gray-100 dark:border-gray-800">
                  <tr>
                    <th className="px-6 py-4">Lisensi & Aplikasi</th>
                    <th className="px-6 py-4">Perangkat & HWID</th>
                    <th className="px-6 py-4">Status</th>
                    <th className="px-6 py-4">Masa Aktif</th>
                    <th className="px-6 py-4 text-right">Aksi</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-gray-100 dark:divide-gray-800">
                  {licenses.data.map((lic) => (
                    <tr
                      key={lic.id}
                      className="hover:bg-gray-50/50 dark:hover:bg-gray-800/40 transition"
                    >
                      <td className="px-6 py-4">
                        <div className="space-y-1">
                          <div className="flex items-center gap-2">
                            <span className="font-bold text-gray-900 dark:text-white font-mono tracking-wider">
                              {lic.license_key}
                            </span>
                            <button
                              type="button"
                              onClick={() => handleCopy(lic.license_key)}
                              className="p-1 text-gray-400 hover:text-brand-600 dark:hover:text-brand-400 transition"
                              title="Salin License Key"
                            >
                              {copiedKey === lic.license_key ? (
                                <Check className="w-4 h-4 text-emerald-500" />
                              ) : (
                                <Copy className="w-4 h-4" />
                              )}
                            </button>
                          </div>
                          <div className="text-xs text-gray-500 dark:text-gray-400 flex items-center gap-1.5">
                            <Laptop className="w-3.5 h-3.5" />
                            {lic.product_name}
                          </div>
                        </div>
                      </td>

                      <td className="px-6 py-4">
                        {lic.hwid ? (
                          <div className="space-y-1">
                            <div className="text-xs font-semibold text-gray-900 dark:text-white flex items-center gap-1.5">
                              <Cpu className="w-3.5 h-3.5 text-brand-500" />
                              {lic.device_name || "Komputer Kasir / Admin"}
                            </div>
                            <div className="text-[11px] font-mono text-gray-500 dark:text-gray-400 truncate max-w-[200px]" title={lic.hwid}>
                              HWID: {lic.hwid}
                            </div>
                          </div>
                        ) : (
                          <span className="inline-flex items-center gap-1 text-xs text-gray-400 italic">
                            <Clock className="w-3.5 h-3.5" />
                            Belum terikat perangkat
                          </span>
                        )}
                      </td>

                      <td className="px-6 py-4">
                        {getStatusBadge(lic.status, lic.expires_at)}
                      </td>

                      <td className="px-6 py-4">
                        <div className="space-y-0.5">
                          <div className="text-xs font-bold text-gray-900 dark:text-white">
                            {lic.expires_at
                              ? new Date(lic.expires_at).toLocaleDateString("id-ID", {
                                  day: "numeric",
                                  month: "short",
                                  year: "numeric",
                                })
                              : "Lifetime (Permanen)"}
                          </div>
                          {lic.activated_at && (
                            <div className="text-[11px] text-gray-400">
                              Aktif sejak:{" "}
                              {new Date(lic.activated_at).toLocaleDateString("id-ID", {
                                day: "numeric",
                                month: "short",
                                year: "numeric",
                              })}
                            </div>
                          )}
                        </div>
                      </td>

                      <td className="px-6 py-4 text-right">
                        <button
                          type="button"
                          onClick={() => handleCopy(lic.license_key)}
                          className="p-1.5 rounded-lg bg-brand-600 hover:bg-brand-700 text-white shadow-xs transition-all active:scale-95 cursor-pointer inline-flex items-center justify-center"
                          title="Salin License Key"
                        >
                          {copiedKey === lic.license_key ? (
                            <Check className="w-4 h-4 text-white" />
                          ) : (
                            <Copy className="w-4 h-4 text-white" />
                          )}
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
    </AppLayout>
  )
}
