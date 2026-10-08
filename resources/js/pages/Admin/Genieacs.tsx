import { useState, useEffect, useMemo, useDeferredValue } from "react"
import { AppLayout } from "@/components/layout/app-layout"
import {
  Radio,
  Search,
  Power,
  Settings as SettingsIcon,
  AlertTriangle,
  CheckCircle2,
  XCircle,
  Server,
  Phone,
  X,
  ExternalLink,
} from "lucide-react"
import { Head, router } from "@inertiajs/react"
import { PageProps } from "@/types"
import { adminSidebarItems, adminNavItems, adminBrand } from "@/lib/admin-nav"
import MetricCard from "@/components/tailadmin/MetricCard"
import Modal from "@/components/tailadmin/Modal"
import { ViewModeSwitcher, ViewMode } from "@/components/tailadmin/ui/view-mode-switcher"
import { ServiceSettingsSheet } from "@/components/layout/service-settings-sheet"

interface Device {
  _id: string
  serial_number?: string
  product_class?: string
  manufacturer?: string
  model_name?: string
  ip?: string
  mac?: string
  last_inform?: string
  status?: string
  customer_name?: string
  customer_phone?: string
  rx_power?: number
  tx_power?: number
  pon_mode?: string
  uptime?: string
  tags?: string[]
}

export default function GenieacsPage({
  devices = [],
  companyName = "NODERA Billing",
  tenantName,
  configured = false,
  connectionError = null,
  genieacsUrl = "",
}: PageProps<{
  devices: Device[]
  companyName?: string
  tenantName?: string
  configured?: boolean
  connectionError?: string | null
  genieacsUrl?: string
}>) {
  const [viewMode, setViewMode] = useState<ViewMode>("table")
  const [search, setSearch] = useState("")
  const [statusFilter, setStatusFilter] = useState<string>("all")
  const [genieacsSettingsOpen, setGenieacsSettingsOpen] = useState(false)
  const [managingDevice, setManagingDevice] = useState<Device | null>(null)
  const [visibleLimit, setVisibleLimit] = useState(60)

  const deferredSearch = useDeferredValue(search)

  const filtered = useMemo(() => {
    return devices.filter((d) => {
      if (statusFilter !== "all" && d.status !== statusFilter) return false
      if (!deferredSearch) return true
      const q = deferredSearch.toLowerCase()
      return (
        (d.serial_number ?? "").toLowerCase().includes(q) ||
        (d.customer_name ?? "").toLowerCase().includes(q) ||
        (d.ip ?? "").toLowerCase().includes(q) ||
        (d.manufacturer ?? "").toLowerCase().includes(q) ||
        (d.model_name ?? "").toLowerCase().includes(q)
      )
    })
  }, [devices, statusFilter, deferredSearch])

  useEffect(() => {
    setVisibleLimit(60)
  }, [statusFilter, deferredSearch])

  const displayedDevices = useMemo(() => filtered.slice(0, visibleLimit), [filtered, visibleLimit])

  const onlineCount = devices.filter((d) => d.status === "online").length
  const offlineCount = devices.length - onlineCount

  const handleReboot = (deviceId: string, sn?: string) => {
    if (confirm(`Kirim perintah reboot TR-069 ke modem ${sn || deviceId}?`)) {
      router.post(`/admin/genieacs/reboot/${encodeURIComponent(deviceId)}`, {}, { preserveScroll: true })
      setManagingDevice(null)
    }
  }

  // Helper optical badge (SOLID BADGE)
  const getRxPowerBadge = (rx: number | undefined) => {
    if (rx == null || isNaN(rx)) {
      return (
        <span className="font-mono text-xs text-gray-400 dark:text-gray-500">
          -
        </span>
      )
    }
    if (rx >= -23 && rx <= -10) {
      return (
        <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-mono font-bold bg-emerald-500 text-white shadow-xs whitespace-nowrap">
          {rx.toFixed(2)} dBm
        </span>
      )
    }
    if (rx < -23 && rx >= -26) {
      return (
        <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-mono font-bold bg-amber-500 text-white shadow-xs whitespace-nowrap">
          {rx.toFixed(2)} dBm
        </span>
      )
    }
    return (
      <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-mono font-bold bg-rose-500 text-white shadow-xs whitespace-nowrap">
        {rx.toFixed(2)} dBm
      </span>
    )
  }

  return (
    <AppLayout
      title="TR-069 GenieACS"
      brand={adminBrand}
      sidebarItems={adminSidebarItems}
      navItems={adminNavItems}
    >
      <Head title="TR-069 GenieACS — Terminal ONT" />

      <div className="space-y-6">
        {/* Connection Notice if Not Configured or Error */}
        {(!configured || connectionError) && (
          <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-4 dark:border-amber-900/50 dark:bg-amber-950/40 text-amber-900 dark:text-amber-200 shadow-xs">
            <div className="flex items-center gap-3">
              <AlertTriangle className="size-5 text-amber-600 dark:text-amber-400 shrink-0" />
              <div>
                <p className="text-sm font-bold text-amber-950 dark:text-amber-100">
                  {!configured ? "GenieACS Belum Dikonfigurasi" : "Gagal Terhubung ke Server GenieACS"}
                </p>
                <p className="text-xs text-amber-800/80 dark:text-amber-300/80 mt-0.5">
                  {connectionError || "Silakan atur URL REST API GenieACS NBI (biasanya port :7557) untuk memulai sinkronisasi modem."}
                </p>
              </div>
            </div>
            <button
              onClick={() => setGenieacsSettingsOpen(true)}
              className="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-amber-500 hover:bg-amber-600 text-xs font-bold text-white shadow-xs transition shrink-0 active:scale-95"
            >
              <SettingsIcon className="size-3.5" />
              Atur Koneksi Sekarang
            </button>
          </div>
        )}

        {/* Top 4 KPI Metrics */}
        <div className="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
          <MetricCard
            title="Total Perangkat ONT"
            value={devices.length}
            icon={<Radio className="size-5" />}
            variant="primary"
            description="Terdaftar di GenieACS NBI"
          />
          <MetricCard
            title="Modem Online"
            value={onlineCount}
            icon={<CheckCircle2 className="size-5" />}
            variant="success"
            description={devices.length > 0 ? `${Math.round((onlineCount / devices.length) * 100)}% perangkat aktif` : "Tidak ada ONT"}
          />
          <MetricCard
            title="Modem Offline"
            value={offlineCount}
            icon={<XCircle className="size-5" />}
            variant={offlineCount > 0 ? "danger" : "neutral"}
            description="Perangkat tidak merespon"
          />
          <MetricCard
            title="Status Koneksi NBI"
            value={configured && !connectionError ? "Terkoneksi" : "Terputus"}
            icon={<Server className="size-5" />}
            variant={configured && !connectionError ? "success" : "danger"}
            description={genieacsUrl || "Port :7557"}
          />
        </div>

        {/* ── 1-LINE RESPONSIVE INTEGRATED TOOLBAR ── */}
        <div className="rounded-2xl border border-gray-200 bg-white p-3 sm:p-4 shadow-xs dark:border-gray-800 dark:bg-white/[0.03] flex flex-col gap-2.5 sm:gap-3 lg:flex-row lg:items-center lg:justify-between">
          <div className="flex flex-wrap items-center gap-2 sm:gap-3 flex-1 min-w-0">
            {/* Search Bar */}
            <div className="relative flex-1 min-w-[180px] sm:min-w-[220px] w-full sm:w-auto">
              <Search className="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400 dark:text-gray-500" />
              <input
                type="text"
                placeholder="Cari SN, pelanggan, IP, model..."
                value={search}
                onChange={(e) => setSearch(e.target.value)}
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

            {/* Status Filter */}
            <select
              value={statusFilter}
              onChange={(e) => setStatusFilter(e.target.value as any)}
              className="h-10 rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 px-3 text-xs font-semibold text-gray-700 dark:text-gray-200 cursor-pointer w-full sm:w-auto shrink-0 shadow-2xs"
            >
              <option value="all">Semua Status ({devices.length})</option>
              <option value="online">Online ({onlineCount})</option>
              <option value="offline">Offline ({offlineCount})</option>
            </select>
          </div>

          <div className="flex items-center gap-2 shrink-0 w-full sm:w-auto justify-end">
            <div className="shrink-0">
              <ViewModeSwitcher
                value={viewMode}
                onChange={setViewMode}
                storageKey="nodera_genieacs_view_mode"
                size="sm"
              />
            </div>

            <button
              type="button"
              onClick={() => setGenieacsSettingsOpen(true)}
              className="h-10 px-3.5 sm:px-4 rounded-xl bg-brand-500 hover:bg-brand-600 active:scale-95 text-xs font-bold text-white shadow-xs flex items-center justify-center gap-1.5 w-full sm:w-auto shrink-0 cursor-pointer"
            >
              <SettingsIcon className="h-4 w-4" />
              <span>Pengaturan API</span>
            </button>
          </div>
        </div>

        {/* Master Card: Devices Catalog */}
        <div className="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xs dark:border-gray-800 dark:bg-white/[0.03]">

          {/* TABLE VIEW */}
          {viewMode === "table" && (
            <div className="w-full overflow-x-auto custom-scrollbar">
              <table className="w-full text-left text-sm min-w-[700px] border-collapse">
                <thead className="border-b border-gray-200 bg-gray-50/75 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:border-gray-800 dark:bg-gray-800/40 dark:text-gray-400">
                  <tr>
                    <th className="px-4 py-3.5">Status</th>
                    <th className="px-4 py-3.5">Nama / Pelanggan</th>
                    <th className="px-4 py-3.5">Serial Number</th>
                    <th className="px-4 py-3.5">Model & Produsen</th>
                    <th className="px-4 py-3.5">IP Address</th>
                    <th className="px-4 py-3.5">MAC Address</th>
                    <th className="px-4 py-3.5">RX Power</th>
                    <th className="px-4 py-3.5">TX Power</th>
                    <th className="px-4 py-3.5">Terakhir Inform</th>
                    <th className="px-4 py-3.5 text-right">Aksi</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-gray-100 dark:divide-gray-800/60">
                  {displayedDevices.length === 0 ? (
                    <tr>
                      <td colSpan={10} className="px-4 py-12 text-center text-sm text-gray-500">
                        <Radio className="size-8 mx-auto mb-2 text-gray-400" />
                        <p className="font-semibold text-gray-700 dark:text-gray-300">Tidak ada perangkat TR-069 ditemukan</p>
                        <p className="text-xs text-gray-400 mt-1">
                          {search ? "Ubah kata kunci pencarian." : "Pastikan modem telah mengarah ke URL ACS server ini."}
                        </p>
                      </td>
                    </tr>
                  ) : (
                    displayedDevices.map((d) => {
                      const isOnline = d.status === "online"

                      return (
                        <tr key={d._id} className="hover:bg-gray-50/50 dark:hover:bg-white/[0.02] transition-colors">
                          <td className="px-4 py-3.5">
                            {isOnline ? (
                              <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold bg-emerald-500 text-white shadow-xs whitespace-nowrap">
                                Online
                              </span>
                            ) : (
                              <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold bg-rose-500 text-white shadow-xs whitespace-nowrap">
                                Offline
                              </span>
                            )}
                          </td>
                          <td className="px-4 py-3.5">
                            <div className="font-semibold text-gray-900 dark:text-white">
                              {d.customer_name || d.serial_number || d._id}
                            </div>
                            {d.customer_phone && (
                              <div className="text-[11px] text-gray-500 dark:text-gray-400">
                                {d.customer_phone}
                              </div>
                            )}
                          </td>
                          <td className="px-4 py-3.5 font-mono text-xs font-semibold text-gray-900 dark:text-white">
                            {d.serial_number || "-"}
                          </td>
                          <td className="px-4 py-3.5 text-xs text-gray-700 dark:text-gray-300">
                            <div>{d.manufacturer || "ONT"}</div>
                            <div className="text-[11px] text-gray-500 dark:text-gray-400">{d.model_name || "-"}</div>
                          </td>
                          <td className="px-4 py-3.5 font-mono text-xs font-semibold text-emerald-600 dark:text-emerald-400">
                            {d.ip || "-"}
                          </td>
                          <td className="px-4 py-3.5 font-mono text-xs text-gray-600 dark:text-gray-400">
                            {d.mac || "-"}
                          </td>
                          <td className="px-4 py-3.5">
                            {getRxPowerBadge(d.rx_power)}
                          </td>
                          <td className="px-4 py-3.5 font-mono text-xs text-brand-600 dark:text-brand-400 font-semibold">
                            {d.tx_power != null ? `${d.tx_power.toFixed(2)} dBm` : "-"}
                          </td>
                          <td className="px-4 py-3.5 text-xs text-gray-500 dark:text-gray-400">
                            {d.last_inform ? new Date(d.last_inform).toLocaleString("id-ID") : "-"}
                          </td>
                          <td className="px-4 py-3.5 text-right">
                            <button
                              type="button"
                              onClick={() => setManagingDevice(d)}
                              className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200 font-bold text-xs transition cursor-pointer"
                            >
                              <span>Kelola</span>
                            </button>
                          </td>
                        </tr>
                      )
                    })
                  )}
                </tbody>
              </table>
            </div>
          )}

          {/* GRID VIEW */}
          {viewMode === "grid" && (
            <div className="p-4 sm:p-6">
              {displayedDevices.length === 0 ? (
                <div className="py-12 text-center text-sm text-gray-500">
                  <Radio className="size-8 mx-auto mb-2 text-gray-400" />
                  <p className="font-semibold text-gray-700 dark:text-gray-300">Tidak ada perangkat TR-069 ditemukan</p>
                </div>
              ) : (
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                  {displayedDevices.map((d) => {
                    const isOnline = d.status === "online"

                    return (
                      <div
                        key={d._id}
                        className={`rounded-2xl border p-4 transition-all shadow-xs ${
                          isOnline
                            ? "border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.02]"
                            : "border-gray-200/80 bg-gray-50/50 dark:border-gray-800 dark:bg-white/[0.01]"
                        }`}
                      >
                        {/* Card Header */}
                        <div className="flex items-start justify-between gap-3">
                          <div className="flex items-center gap-2.5 min-w-0">
                            <div className={`flex size-10 shrink-0 items-center justify-center rounded-xl font-bold ${
                              isOnline ? "bg-brand-50 text-brand-600 dark:bg-brand-950/50 dark:text-brand-400" : "bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400"
                            }`}>
                              <Radio className="size-5" />
                            </div>
                            <div className="min-w-0">
                              <h4 className="font-bold text-sm text-gray-900 dark:text-white truncate">
                                {d.customer_name || d.serial_number || d._id}
                              </h4>
                              <p className="text-xs text-gray-500 dark:text-gray-400 truncate">
                                {d.manufacturer || "ONT"} {d.model_name ? `· ${d.model_name}` : ""}
                              </p>
                            </div>
                          </div>

                          <span className={`inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold text-white shadow-xs ${
                            isOnline ? "bg-emerald-500" : "bg-rose-500"
                          }`}>
                            {isOnline ? "Online" : "Offline"}
                          </span>
                        </div>

                        {/* Specs Strip */}
                        <div className="mt-3 grid grid-cols-2 gap-2 rounded-xl border border-gray-100 bg-gray-50/80 p-2.5 text-xs dark:border-gray-800 dark:bg-gray-900/50">
                          <div>
                            <span className="text-[10px] font-medium text-gray-500 dark:text-gray-400 block">SN Modem</span>
                            <span className="font-mono font-bold text-gray-900 dark:text-white truncate block">
                              {d.serial_number || "-"}
                            </span>
                          </div>
                          <div className="text-right">
                            <span className="text-[10px] font-medium text-gray-500 dark:text-gray-400 block">IP Address</span>
                            <span className="font-mono font-bold text-emerald-600 dark:text-emerald-400 truncate block">
                              {d.ip || "-"}
                            </span>
                          </div>
                        </div>

                        {/* Optical Signal */}
                        <div className="mt-2.5 flex items-center justify-between text-xs px-1">
                          <span className="text-gray-500 dark:text-gray-400">RX Power:</span>
                          <div>{getRxPowerBadge(d.rx_power)}</div>
                        </div>

                        {/* Actions Footer: SINGLE KELOLA BUTTON */}
                        <div className="mt-3 pt-3 border-t border-gray-100 dark:border-gray-800 flex items-center justify-end">
                          <button
                            type="button"
                            onClick={() => setManagingDevice(d)}
                            className="w-full justify-center inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200 font-bold text-xs transition cursor-pointer"
                          >
                            <span>Kelola ONT</span>
                          </button>
                        </div>
                      </div>
                    )
                  })}
                </div>
              )}
            </div>
          )}

          {/* Load More Pagination Strip */}
          {filtered.length > visibleLimit && (
            <div className="border-t border-gray-200 bg-gray-50/50 p-4 dark:border-gray-800 dark:bg-gray-800/20 flex flex-col sm:flex-row items-center justify-between gap-3">
              <p className="text-xs font-medium text-gray-500 dark:text-gray-400">
                Menampilkan <strong className="text-gray-900 dark:text-white">{displayedDevices.length}</strong> dari <strong className="text-gray-900 dark:text-white">{filtered.length}</strong> perangkat TR-069
              </p>
              <div className="flex items-center gap-2">
                <button
                  type="button"
                  onClick={() => setVisibleLimit((prev) => prev + 60)}
                  className="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:hover:bg-gray-800 text-xs font-semibold text-gray-700 dark:text-gray-200 transition shadow-2xs"
                >
                  +60 Perangkat Berikutnya
                </button>
                <button
                  type="button"
                  onClick={() => setVisibleLimit(filtered.length)}
                  className="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-brand-500 hover:bg-brand-600 active:scale-95 text-xs font-bold text-white shadow-xs transition"
                >
                  Tampilkan Semua ({filtered.length})
                </button>
              </div>
            </div>
          )}
        </div>
      </div>

      {/* POPUP MODAL KELOLA TR-069 DEVICE */}
      {managingDevice && (
        <Modal
          isOpen={true}
          onClose={() => setManagingDevice(null)}
          title="Kelola Perangkat TR-069"
          maxWidth="md"
        >
          <div className="p-5 max-h-[92vh] overflow-y-auto space-y-4">
            {/* Header info */}
            <div className="flex items-start justify-between gap-3 border-b border-gray-100 dark:border-gray-800 pb-3">
              <div className="min-w-0">
                <h4 className="font-bold text-sm text-gray-900 dark:text-white truncate">
                  {managingDevice.customer_name || managingDevice.serial_number || managingDevice._id}
                </h4>
                <p className="text-xs text-gray-500 dark:text-gray-400 truncate">
                  {managingDevice.manufacturer || "ONT"} {managingDevice.model_name ? `· ${managingDevice.model_name}` : ""}
                </p>
              </div>
              <span className={`inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold text-white shadow-xs shrink-0 ${
                managingDevice.status === "online" ? "bg-emerald-500" : "bg-rose-500"
              }`}>
                {managingDevice.status === "online" ? "Online" : "Offline"}
              </span>
            </div>

            {/* Metrics Info Grid */}
            <div className="rounded-xl border border-gray-100 bg-gray-50/80 p-3 dark:border-gray-800 dark:bg-gray-900/50 space-y-2 text-xs">
              <div className="flex items-center justify-between">
                <span className="text-gray-500 dark:text-gray-400">Serial Number</span>
                <span className="font-mono font-bold text-gray-900 dark:text-white">{managingDevice.serial_number || "-"}</span>
              </div>
              <div className="flex items-center justify-between">
                <span className="text-gray-500 dark:text-gray-400">IP Address</span>
                <span className="font-mono font-bold text-emerald-600 dark:text-emerald-400">{managingDevice.ip || "-"}</span>
              </div>
              <div className="flex items-center justify-between">
                <span className="text-gray-500 dark:text-gray-400">MAC Address</span>
                <span className="font-mono text-gray-700 dark:text-gray-300">{managingDevice.mac || "-"}</span>
              </div>
              <div className="flex items-center justify-between">
                <span className="text-gray-500 dark:text-gray-400">RX Power</span>
                <div>{getRxPowerBadge(managingDevice.rx_power)}</div>
              </div>
              <div className="flex items-center justify-between">
                <span className="text-gray-500 dark:text-gray-400">TX Power</span>
                <span className="font-mono font-semibold text-brand-600 dark:text-brand-400">{managingDevice.tx_power != null ? `${managingDevice.tx_power.toFixed(2)} dBm` : "-"}</span>
              </div>
              {managingDevice.customer_phone && (
                <div className="flex items-center justify-between">
                  <span className="text-gray-500 dark:text-gray-400">Telepon</span>
                  <span className="text-gray-900 dark:text-white font-medium">{managingDevice.customer_phone}</span>
                </div>
              )}
              <div className="flex items-center justify-between">
                <span className="text-gray-500 dark:text-gray-400">Terakhir Inform</span>
                <span className="text-gray-700 dark:text-gray-300">{managingDevice.last_inform ? new Date(managingDevice.last_inform).toLocaleString("id-ID") : "-"}</span>
              </div>
            </div>

            {/* Quick Action Buttons */}
            <div className="pt-2 space-y-2">
              <button
                type="button"
                onClick={() => handleReboot(managingDevice._id, managingDevice.serial_number)}
                className="w-full inline-flex items-center justify-center gap-1.5 px-3.5 py-2 rounded-xl bg-rose-500 hover:bg-rose-600 text-xs font-bold text-white shadow-xs transition active:scale-95"
              >
                <Power className="size-4" />
                <span>Reboot Modem TR-069</span>
              </button>

              <button
                type="button"
                onClick={() => setManagingDevice(null)}
                className="w-full inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:hover:bg-gray-800 text-xs font-semibold text-gray-700 dark:text-gray-200 transition shadow-2xs"
              >
                <span>Tutup</span>
              </button>
            </div>
          </div>
        </Modal>
      )}

      {/* Slide-over Sheet for GenieACS Settings */}
      <ServiceSettingsSheet
        service={genieacsSettingsOpen ? "genieacs" : null}
        onClose={() => {
          setGenieacsSettingsOpen(false)
          router.reload({ preserveScroll: true })
        }}
      />
    </AppLayout>
  )
}