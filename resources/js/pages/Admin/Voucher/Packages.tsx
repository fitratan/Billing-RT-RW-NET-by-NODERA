import React, { useState, useMemo } from "react"
import { AppLayout } from "@/components/layout/app-layout"
import {
  Layers,
  Plus,
  Pencil,
  Trash2,
  Search,
  X,
  Clock,
  RefreshCw,
  Save,
  Gauge,
  HardDrive,
  CalendarClock,
  Hash,
  Settings,
} from "lucide-react"
import { PageProps } from "@/types"
import { formatIDR } from "@/lib/utils"
import { adminSidebarItems, adminNavItems, adminBrand } from "@/lib/admin-nav"
import { router, useForm } from "@inertiajs/react"
import Checkbox from "@/components/tailadmin/Checkbox"
import { ViewModeSwitcher } from "@/components/ui/view-mode-switcher"

interface RouterItem {
  id: number
  name: string
  host?: string
}

interface VoucherPackage {
  id: number
  name: string
  profile?: string
  price: number
  duration_days: number
  time_limit?: string
  data_limit?: number | string
  bandwidth_up?: number | string
  bandwidth_down?: number | string
  router_id?: number
}

interface PackagesProps {
  packages: VoucherPackage[]
  profiles: string[]
  routers: RouterItem[]
  selectedRouterId?: number | null
}

export default function AdminVoucherPackagesPage({
  packages = [],
  profiles = [],
  routers = [],
  selectedRouterId = null,
}: PageProps<PackagesProps>) {
  const [viewMode, setViewMode] = useState<"table" | "grid">("table")
  const [modalOpen, setModalOpen] = useState(false)
  const [editingPkg, setEditingPkg] = useState<VoucherPackage | null>(null)
  const [syncing, setSyncing] = useState(false)
  const [search, setSearch] = useState("")
  const [deletePkgTarget, setDeletePkgTarget] = useState<VoucherPackage | null>(null)
  const [deletePkgMikrotik, setDeletePkgMikrotik] = useState(true)
  const [managePkg, setManagePkg] = useState<VoucherPackage | null>(null)

  const form = useForm({
    name: "",
    profile: profiles[0] || "default",
    price: "" as string | number,
    duration_days: 1 as string | number,
    time_limit: "",
    data_limit: "" as string | number,
    bandwidth_up: "" as string | number,
    bandwidth_down: "" as string | number,
    router_id: selectedRouterId ? String(selectedRouterId) : (routers[0]?.id ? String(routers[0].id) : ""),
  })

  const openCreate = () => {
    setEditingPkg(null)
    form.setData({
      name: "",
      profile: profiles[0] || "default",
      price: "",
      duration_days: 1,
      time_limit: "",
      data_limit: "",
      bandwidth_up: "",
      bandwidth_down: "",
      router_id: selectedRouterId ? String(selectedRouterId) : (routers[0]?.id ? String(routers[0].id) : ""),
    })
    setModalOpen(true)
  }

  const openEdit = (pkg: VoucherPackage) => {
    setEditingPkg(pkg)
    form.setData({
      name: pkg.name,
      profile: pkg.profile || profiles[0] || "default",
      price: pkg.price || "",
      duration_days: pkg.duration_days || 1,
      time_limit: pkg.time_limit || "",
      data_limit: pkg.data_limit || "",
      bandwidth_up: pkg.bandwidth_up || "",
      bandwidth_down: pkg.bandwidth_down || "",
      router_id: pkg.router_id ? String(pkg.router_id) : (selectedRouterId ? String(selectedRouterId) : ""),
    })
    setModalOpen(true)
  }

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    if (editingPkg) {
      form.post(`/admin/voucher/packages/edit/${editingPkg.id}`, {
        preserveScroll: true,
        onSuccess: () => setModalOpen(false),
      })
    } else {
      form.post("/admin/voucher/packages/add", {
        preserveScroll: true,
        onSuccess: () => setModalOpen(false),
      })
    }
  }

  const handleDelete = (pkg: VoucherPackage) => {
    setDeletePkgTarget(pkg)
  }

  const handleConfirmDelete = () => {
    if (!deletePkgTarget) return
    router.post(
      `/admin/voucher/packages/delete/${deletePkgTarget.id}`,
      { delete_mikrotik: deletePkgMikrotik },
      {
        preserveScroll: true,
        onSuccess: () => setDeletePkgTarget(null),
      }
    )
  }

  const handleSyncMikrotik = () => {
    const rId = selectedRouterId || routers[0]?.id
    if (!rId) {
      alert("Pilih router target terlebih dahulu untuk melakukan sinkronisasi.")
      return
    }

    setSyncing(true)
    router.post(
      "/admin/voucher/packages/sync",
      { router_id: rId },
      {
        preserveScroll: true,
        onFinish: () => setSyncing(false),
      }
    )
  }

  const handleRouterChange = (routerId: string) => {
    router.get(`/admin/voucher/packages?router_id=${routerId}`)
  }

  const filteredPackages = useMemo(() => {
    if (!search) return packages
    const q = search.toLowerCase()
    return packages.filter(
      (pkg) =>
        pkg.name.toLowerCase().includes(q) ||
        (pkg.profile && pkg.profile.toLowerCase().includes(q)) ||
        (pkg.time_limit && pkg.time_limit.toLowerCase().includes(q))
    )
  }, [packages, search])

  return (
    <AppLayout
      title="Paket & Profil Hotspot"
      brand={adminBrand}
      sidebarItems={adminSidebarItems}
      navItems={adminNavItems}
    >
      <div className="space-y-6">
        {/* Header Title */}
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
          <div>
            <h3 className="text-base font-bold text-gray-800 dark:text-white/90">
              Manajemen Paket Tarif Hotspot
            </h3>
            <p className="text-xs text-gray-500 dark:text-gray-400">
              {packages.length} template paket terdaftar di sistem
            </p>
          </div>
        </div>

        {/* ── TOOLBAR: SEARCH, ROUTER SELECT & ACTIONS ── */}
        <div className="rounded-2xl border border-gray-200 bg-white p-3 sm:p-4 shadow-xs dark:border-gray-800 dark:bg-white/[0.03] flex flex-col gap-2.5 sm:gap-3 lg:flex-row lg:items-center lg:justify-between">
          <div className="flex flex-wrap items-center gap-2 sm:gap-3 flex-1 min-w-0">
            <div className="relative flex-1 min-w-[180px] sm:min-w-[220px] w-full sm:w-auto">
              <Search className="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
              <input
                type="text"
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                placeholder="Cari nama paket, profil MikroTik, atau batas waktu..."
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

            {/* Router Selector */}
            {routers.length > 0 && (
              <select
                value={selectedRouterId ? String(selectedRouterId) : ""}
                onChange={(e) => handleRouterChange(e.target.value)}
                className="h-10 rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 px-3 text-xs font-semibold text-gray-700 dark:text-gray-200 cursor-pointer w-full sm:w-auto shrink-0 focus:outline-none focus:border-brand-500"
              >
                {routers.map((r) => (
                  <option key={r.id} value={r.id}>
                    {r.name}
                  </option>
                ))}
              </select>
            )}
          </div>

          <div className="flex items-center gap-2 shrink-0 w-full sm:w-auto justify-end flex-wrap sm:flex-nowrap">
            <ViewModeSwitcher value={viewMode} onChange={setViewMode} />

            <button
              type="button"
              onClick={handleSyncMikrotik}
              disabled={syncing || routers.length === 0}
              className="h-10 inline-flex items-center justify-center gap-1.5 px-3.5 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:hover:bg-gray-800 text-xs font-semibold text-gray-700 dark:text-gray-200 transition disabled:opacity-50 shadow-2xs cursor-pointer shrink-0"
              title="Tarik seluruh profil hotspot dari router MikroTik"
            >
              <RefreshCw className={`h-4 w-4 text-brand-500 ${syncing ? "animate-spin" : ""}`} />
              <span>{syncing ? "Sync..." : "Sync MikroTik"}</span>
            </button>

            <button
              type="button"
              onClick={openCreate}
              className="h-10 px-3.5 sm:px-4 rounded-xl bg-brand-500 hover:bg-brand-600 active:scale-95 text-xs font-bold text-white shadow-xs flex items-center justify-center gap-1.5 w-full sm:w-auto shrink-0 cursor-pointer transition"
            >
              <Plus className="h-4 w-4" />
              <span>Tambah Paket</span>
            </button>
          </div>
        </div>

        {/* ── PACKAGES DATA (TABLE / GRID) ── */}
        {filteredPackages.length === 0 ? (
          <div className="rounded-2xl border border-dashed border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900/30 p-12 text-center space-y-3">
            <Layers className="mx-auto h-10 w-10 text-gray-400 dark:text-gray-500 mb-1" />
            <h3 className="text-sm font-bold text-gray-800 dark:text-white">
              {search ? "Tidak ada paket yang cocok dengan pencarian" : "Belum ada template paket hotspot"}
            </h3>
            <p className="text-xs text-gray-500 dark:text-gray-400 max-w-sm mx-auto">
              {search
                ? "Coba kata kunci lain atau bersihkan kotak pencarian."
                : "Klik Sync MikroTik untuk menarik profil langsung dari router atau buat paket baru manual."}
            </p>
            {!search && (
              <button
                type="button"
                onClick={handleSyncMikrotik}
                disabled={syncing}
                className="inline-flex items-center gap-1.5 h-10 px-4 rounded-xl border border-brand-200 bg-brand-50 hover:bg-brand-100 text-brand-700 dark:border-brand-800 dark:bg-brand-950/30 dark:text-brand-300 font-bold text-xs transition shadow-2xs"
              >
                <RefreshCw className={`h-3.5 w-3.5 ${syncing ? "animate-spin" : ""}`} />
                <span>Sync dari MikroTik Sekarang</span>
              </button>
            )}
          </div>
        ) : viewMode === "table" ? (
          <div className="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xs dark:border-gray-800 dark:bg-white/[0.03]">
            <div className="w-full overflow-x-auto custom-scrollbar">
              <table className="w-full min-w-[1000px] text-left text-xs border-collapse">
                <thead className="border-b border-gray-200 bg-gray-50/75 text-[11px] font-bold text-gray-500 uppercase tracking-wider dark:border-gray-800 dark:bg-gray-900/50 dark:text-gray-400">
                  <tr>
                    <th className="py-3 px-4">Nama Paket</th>
                    <th className="py-3 px-4">Profil MikroTik</th>
                    <th className="py-3 px-4">Harga Jual</th>
                    <th className="py-3 px-4">Masa Aktif</th>
                    <th className="py-3 px-4">Batas Waktu</th>
                    <th className="py-3 px-4">Batas Kuota</th>
                    <th className="py-3 px-4">Bandwidth Up/Down</th>
                    <th className="py-3 px-4">Router Target</th>
                    <th className="py-3 px-4 text-center">Aksi</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-gray-100 dark:divide-gray-800/60">
                  {filteredPackages.map((pkg) => {
                    const rName = routers.find((r) => r.id === pkg.router_id)?.name || "Semua Router"
                    return (
                      <tr key={pkg.id} className="hover:bg-gray-50/50 dark:hover:bg-white/[0.02] transition-colors">
                        <td className="py-3 px-4 font-bold text-gray-900 dark:text-white">
                          <div className="flex items-center gap-2">
                            <div className="flex h-7 w-7 items-center justify-center rounded-lg bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-400 shrink-0">
                              <Layers className="h-3.5 w-3.5" />
                            </div>
                            <span className="truncate">{pkg.name}</span>
                          </div>
                        </td>
                        <td className="py-3 px-4">
                          <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold bg-brand-500 text-white shadow-xs whitespace-nowrap">
                            {pkg.profile || "default"}
                          </span>
                        </td>
                        <td className="py-3 px-4 font-mono font-bold text-emerald-600 dark:text-emerald-400">
                          {pkg.price ? formatIDR(pkg.price) : "Rp 0 (Gratis)"}
                        </td>
                        <td className="py-3 px-4 font-mono text-gray-700 dark:text-gray-300">
                          {pkg.duration_days} Hari
                        </td>
                        <td className="py-3 px-4 font-mono text-gray-700 dark:text-gray-300">
                          {pkg.time_limit ? (
                            <span className="inline-flex items-center gap-1 font-mono text-amber-600 dark:text-amber-400 font-semibold">
                              <Clock className="h-3 w-3" />
                              <span>{pkg.time_limit}</span>
                            </span>
                          ) : (
                            <span className="text-gray-400">-</span>
                          )}
                        </td>
                        <td className="py-3 px-4 font-mono text-gray-700 dark:text-gray-300">
                          {pkg.data_limit || <span className="text-gray-400">-</span>}
                        </td>
                        <td className="py-3 px-4 font-mono text-gray-700 dark:text-gray-300">
                          {pkg.bandwidth_up || pkg.bandwidth_down ? `${pkg.bandwidth_up || 0}M / ${pkg.bandwidth_down || 0}M` : <span className="text-gray-400">-</span>}
                        </td>
                        <td className="py-3 px-4 text-gray-600 dark:text-gray-300">
                          <span className="truncate max-w-[120px] block">{rName}</span>
                        </td>
                        <td className="py-3 px-4 text-center">
                          <button
                            type="button"
                            onClick={() => setManagePkg(pkg)}
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
            {filteredPackages.map((pkg) => (
              <div
                key={pkg.id}
                className="p-4 sm:p-5 rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03] shadow-xs space-y-3.5 transition hover:border-gray-300 dark:hover:border-gray-700"
              >
                {/* Top Header Card */}
                <div className="flex items-start justify-between gap-2.5">
                  <div className="flex items-center gap-3 min-w-0 flex-1">
                    <div className="relative flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-400 font-bold text-xs">
                      <Layers className="h-5 w-5" />
                    </div>

                    <div className="min-w-0 flex-1">
                      <h4 className="text-sm font-bold text-gray-900 dark:text-white truncate">
                        {pkg.name}
                      </h4>
                      <div className="mt-1 flex items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400 font-medium truncate">
                        <span className="text-emerald-600 dark:text-emerald-400 font-bold font-mono">
                          {pkg.price ? formatIDR(pkg.price) : "Rp 0 (Gratis)"}
                        </span>
                        <span>•</span>
                        <span className="inline-flex items-center px-1.5 py-0.2 rounded text-[10px] font-bold bg-brand-500 text-white shadow-xs truncate">
                          {pkg.profile || "default"}
                        </span>
                      </div>
                    </div>
                  </div>

                  <button
                    type="button"
                    onClick={() => setManagePkg(pkg)}
                    className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200 font-bold text-xs transition cursor-pointer shrink-0"
                  >
                    <Settings className="h-3.5 w-3.5" />
                    <span>Kelola</span>
                  </button>
                </div>

                {/* Compact Info Box */}
                <div className="grid grid-cols-2 gap-2 rounded-xl border border-gray-100 bg-gray-50/80 dark:border-gray-800 dark:bg-gray-900/50 p-2.5 text-xs text-gray-600 dark:text-gray-300">
                  <div className="flex flex-col justify-center min-w-0">
                    <span className="text-[10px] text-gray-400 dark:text-gray-500 block mb-0.5">Harga Jual</span>
                    <div className="font-bold text-emerald-600 dark:text-emerald-400 text-xs truncate">
                      {pkg.price ? formatIDR(pkg.price) : "Rp 0"}
                    </div>
                  </div>
                  <div className="text-right flex flex-col justify-center min-w-0">
                    <span className="text-[10px] text-gray-400 dark:text-gray-500 block mb-0.5">Batas Waktu</span>
                    <span className="font-mono font-semibold text-amber-600 dark:text-amber-400 truncate">
                      {pkg.time_limit ? pkg.time_limit : `${pkg.duration_days} Hari`}
                    </span>
                  </div>
                </div>
              </div>
            ))}
          </div>
        )}
      </div>

      {/* ── MODAL: KELOLA PAKET ── */}
      {managePkg && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
          <div className="fixed inset-0 bg-black/70 backdrop-blur-sm" onClick={() => setManagePkg(null)} />
          <div className="relative w-full max-w-md max-h-[92vh] flex flex-col rounded-2xl border border-gray-200 bg-white shadow-2xl dark:border-gray-800 dark:bg-gray-900 text-gray-900 dark:text-white overflow-hidden animate-in fade-in zoom-in-95 duration-200">
            <div className="flex items-center justify-between border-b border-gray-100 dark:border-gray-800 px-5 py-4 shrink-0">
              <div className="flex items-center gap-2.5">
                <div className="flex h-9 w-9 items-center justify-center rounded-xl bg-brand-500/10 text-brand-500">
                  <Layers className="h-5 w-5" />
                </div>
                <div>
                  <h3 className="text-sm font-bold">Kelola Template Paket</h3>
                  <p className="text-[11px] text-gray-500 dark:text-gray-400">Detail parameter dan tindakan konfigurasi</p>
                </div>
              </div>
              <button
                type="button"
                onClick={() => setManagePkg(null)}
                className="rounded-lg p-1.5 text-gray-400 hover:text-gray-600 hover:bg-gray-100 dark:hover:text-white dark:hover:bg-gray-800"
              >
                <X className="h-4 w-4" />
              </button>
            </div>

            <div className="flex-1 overflow-y-auto p-5 space-y-4">
              <div className="rounded-xl border border-gray-100 bg-gray-50/80 dark:border-gray-800 dark:bg-gray-900/50 p-4 space-y-3">
                <div className="flex items-center justify-between">
                  <span className="text-xs text-gray-500 dark:text-gray-400">Nama Paket</span>
                  <span className="font-bold text-sm text-gray-900 dark:text-white">{managePkg.name}</span>
                </div>
                <div className="flex items-center justify-between border-t border-gray-200/60 dark:border-gray-800/60 pt-2">
                  <span className="text-xs text-gray-500 dark:text-gray-400">Profil MikroTik</span>
                  <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold bg-brand-500 text-white shadow-xs whitespace-nowrap">
                    {managePkg.profile || "default"}
                  </span>
                </div>
                <div className="flex items-center justify-between border-t border-gray-200/60 dark:border-gray-800/60 pt-2">
                  <span className="text-xs text-gray-500 dark:text-gray-400">Harga Jual</span>
                  <span className="font-mono font-bold text-sm text-emerald-600 dark:text-emerald-400">
                    {managePkg.price ? formatIDR(managePkg.price) : "Rp 0 (Gratis)"}
                  </span>
                </div>
              </div>

              <div className="space-y-2 text-xs text-gray-600 dark:text-gray-300">
                <div className="flex items-center justify-between py-1.5 border-b border-gray-100 dark:border-gray-800">
                  <span className="text-gray-500 dark:text-gray-400 flex items-center gap-1.5 text-xs">
                    <Hash className="h-3.5 w-3.5 text-gray-400" /> ID Template
                  </span>
                  <span className="font-bold text-gray-900 dark:text-white font-mono">#{managePkg.id}</span>
                </div>
                <div className="flex items-center justify-between py-1.5 border-b border-gray-100 dark:border-gray-800">
                  <span className="text-gray-500 dark:text-gray-400 flex items-center gap-1.5 text-xs">
                    <Clock className="h-3.5 w-3.5 text-gray-400" /> Limit Uptime
                  </span>
                  <span className="font-mono text-amber-600 dark:text-amber-400 font-semibold">
                    {managePkg.time_limit || "Tidak Dibatasi"}
                  </span>
                </div>
                <div className="flex items-center justify-between py-1.5 border-b border-gray-100 dark:border-gray-800">
                  <span className="text-gray-500 dark:text-gray-400 flex items-center gap-1.5 text-xs">
                    <HardDrive className="h-3.5 w-3.5 text-gray-400" /> Kuota Data
                  </span>
                  <span className="font-mono text-gray-900 dark:text-white">
                    {managePkg.data_limit ? `${managePkg.data_limit} MB` : "Unlimited"}
                  </span>
                </div>
                <div className="flex items-center justify-between py-1.5 border-b border-gray-100 dark:border-gray-800">
                  <span className="text-gray-500 dark:text-gray-400 flex items-center gap-1.5 text-xs">
                    <CalendarClock className="h-3.5 w-3.5 text-gray-400" /> Masa Aktif
                  </span>
                  <span className="font-medium text-gray-900 dark:text-white">{managePkg.duration_days} Hari</span>
                </div>
                <div className="flex items-center justify-between py-1.5 border-b border-gray-100 dark:border-gray-800">
                  <span className="text-gray-500 dark:text-gray-400 flex items-center gap-1.5 text-xs">
                    <Gauge className="h-3.5 w-3.5 text-gray-400" /> Rate Limit (UL/DL)
                  </span>
                  <span className="font-mono text-brand-600 dark:text-brand-400 font-semibold">
                    {managePkg.bandwidth_up || 0}M / {managePkg.bandwidth_down || 0}M
                  </span>
                </div>
              </div>

              {/* Action Buttons */}
              <div className="space-y-2 pt-2 border-t border-gray-100 dark:border-gray-800">
                <button
                  type="button"
                  onClick={() => {
                    const target = managePkg
                    setManagePkg(null)
                    openEdit(target)
                  }}
                  className="w-full inline-flex items-center justify-center gap-1.5 px-3.5 py-2.5 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:hover:bg-gray-800 text-xs font-semibold text-gray-700 dark:text-gray-200 transition shadow-2xs"
                >
                  <Pencil className="h-4 w-4 text-brand-500" />
                  <span>Edit Template Paket</span>
                </button>

                <button
                  type="button"
                  onClick={() => {
                    const target = managePkg
                    setManagePkg(null)
                    handleDelete(target)
                  }}
                  className="w-full inline-flex items-center justify-center gap-1.5 px-3.5 py-2.5 rounded-xl bg-rose-500 hover:bg-rose-600 active:scale-95 text-xs font-bold text-white shadow-xs transition"
                >
                  <Trash2 className="h-4 w-4" />
                  <span>Hapus Template Paket</span>
                </button>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* ── MODAL: FORM TAMBAH / EDIT PAKET ── */}
      {modalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4">
          <div className="fixed inset-0 bg-black/70 backdrop-blur-sm" onClick={() => setModalOpen(false)} />
          <div className="relative flex max-h-[92vh] w-full max-w-md flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-2xl dark:border-gray-800 dark:bg-gray-900 text-gray-900 dark:text-white">
            {/* Header */}
            <div className="flex items-center justify-between border-b border-gray-100 dark:border-gray-800 px-5 py-4 shrink-0">
              <div className="flex items-center gap-2.5 min-w-0">
                <div className="flex h-8 w-8 items-center justify-center rounded-xl bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-400">
                  <Layers className="h-4 w-4" />
                </div>
                <div className="min-w-0">
                  <h3 className="font-display text-sm font-bold truncate">
                    {editingPkg ? "Edit Template Paket Hotspot" : "Tambah Template Paket Hotspot"}
                  </h3>
                  <p className="text-[11px] text-gray-500 dark:text-gray-400 truncate">
                    Konfigurasi parameter tarif dan profil bandwidth
                  </p>
                </div>
              </div>
              <button
                type="button"
                onClick={() => setModalOpen(false)}
                className="rounded-lg p-1.5 text-gray-400 hover:text-gray-600 hover:bg-gray-100 dark:hover:text-white dark:hover:bg-gray-800"
              >
                <X className="h-4 w-4" />
              </button>
            </div>

            {/* Form */}
            <form onSubmit={handleSubmit} className="flex min-h-0 flex-1 flex-col overflow-hidden">
              <div className="flex-1 overflow-y-auto p-5 space-y-4 min-h-0">
                <div className="space-y-1.5">
                  <label className="text-xs font-semibold text-gray-700 dark:text-gray-300">Nama Paket / Voucher</label>
                  <input
                    value={form.data.name}
                    onChange={(e) => form.setData("name", e.target.value)}
                    placeholder="cth: Paket 3 Jam Hemat"
                    className="h-10 w-full rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 px-3 text-xs font-semibold text-gray-900 dark:text-white focus:outline-none focus:border-brand-500"
                    required
                  />
                </div>

                <div className="space-y-1.5">
                  <label className="text-xs font-semibold text-gray-700 dark:text-gray-300">Profil User MikroTik</label>
                  <select
                    value={form.data.profile}
                    onChange={(e) => form.setData("profile", e.target.value)}
                    className="h-10 w-full rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 px-3 text-xs font-semibold text-gray-900 dark:text-white focus:outline-none focus:border-brand-500"
                    required
                  >
                    {profiles.length > 0 ? (
                      profiles.map((p) => (
                        <option key={p} value={p}>{p}</option>
                      ))
                    ) : (
                      <option value="default">default</option>
                    )}
                  </select>
                </div>

                <div className="space-y-1.5">
                  <label className="text-xs font-semibold text-gray-700 dark:text-gray-300">Harga Jual (Rp)</label>
                  <input
                    type="number"
                    min={0}
                    value={form.data.price}
                    onChange={(e) => form.setData("price", e.target.value)}
                    placeholder="5000"
                    className="h-10 w-full rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 px-3 text-xs font-mono font-bold text-gray-900 dark:text-white focus:outline-none focus:border-brand-500"
                    required
                  />
                </div>

                <div className="grid grid-cols-2 gap-3">
                  <div className="space-y-1.5">
                    <label className="text-xs font-semibold text-gray-700 dark:text-gray-300">Masa Berlaku (Hari)</label>
                    <input
                      type="number"
                      min={1}
                      value={form.data.duration_days}
                      onChange={(e) => form.setData("duration_days", Number(e.target.value))}
                      className="h-10 w-full rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 px-3 text-xs font-mono text-gray-900 dark:text-white focus:outline-none focus:border-brand-500"
                    />
                  </div>

                  <div className="space-y-1.5">
                    <label className="text-xs font-semibold text-gray-700 dark:text-gray-300">Batas Waktu (Limit Uptime)</label>
                    <input
                      value={form.data.time_limit}
                      onChange={(e) => form.setData("time_limit", e.target.value)}
                      placeholder="cth: 3h, 1d, 30m"
                      className="h-10 w-full rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 px-3 text-xs font-mono text-gray-900 dark:text-white placeholder-gray-400 focus:outline-none focus:border-brand-500"
                    />
                  </div>
                </div>

                <div className="space-y-1.5">
                  <label className="text-xs font-semibold text-gray-700 dark:text-gray-300">Batas Kuota (MB)</label>
                  <input
                    type="number"
                    min={0}
                    value={form.data.data_limit}
                    onChange={(e) => form.setData("data_limit", e.target.value)}
                    placeholder="cth: 1024 (kosongkan jika tanpa kuota)"
                    className="h-10 w-full rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 px-3 text-xs font-mono text-gray-900 dark:text-white placeholder-gray-400 focus:outline-none focus:border-brand-500"
                  />
                </div>

                <div className="grid grid-cols-2 gap-3">
                  <div className="space-y-1.5">
                    <label className="text-xs font-semibold text-gray-700 dark:text-gray-300">Upload Rate (Mbps)</label>
                    <input
                      type="number"
                      min={0}
                      value={form.data.bandwidth_up}
                      onChange={(e) => form.setData("bandwidth_up", e.target.value)}
                      placeholder="cth: 2"
                      className="h-10 w-full rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 px-3 text-xs font-mono text-gray-900 dark:text-white placeholder-gray-400 focus:outline-none focus:border-brand-500"
                    />
                  </div>

                  <div className="space-y-1.5">
                    <label className="text-xs font-semibold text-gray-700 dark:text-gray-300">Download Rate (Mbps)</label>
                    <input
                      type="number"
                      min={0}
                      value={form.data.bandwidth_down}
                      onChange={(e) => form.setData("bandwidth_down", e.target.value)}
                      placeholder="cth: 5"
                      className="h-10 w-full rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 px-3 text-xs font-mono text-gray-900 dark:text-white placeholder-gray-400 focus:outline-none focus:border-brand-500"
                    />
                  </div>
                </div>
              </div>

              {/* Footer */}
              <div className="flex items-center gap-3 border-t border-gray-100 dark:border-gray-800 bg-gray-50/50 dark:bg-gray-900/50 p-4 shrink-0">
                <button
                  type="button"
                  className="flex-1 h-10 text-xs font-bold rounded-xl border border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200 transition"
                  onClick={() => setModalOpen(false)}
                >
                  Batal
                </button>
                <button
                  type="submit"
                  className="flex-1 gap-1.5 h-10 text-xs font-bold bg-brand-500 hover:bg-brand-600 active:scale-95 text-white rounded-xl flex items-center justify-center transition disabled:opacity-50 shadow-xs"
                  disabled={form.processing}
                >
                  <Save className="h-4 w-4" />
                  <span>{form.processing ? "Menyimpan..." : "Simpan Paket"}</span>
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* ── MODAL KONFIRMASI HAPUS PAKET ── */}
      {deletePkgTarget && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
          <div className="fixed inset-0 bg-black/70 backdrop-blur-sm" onClick={() => setDeletePkgTarget(null)} />
          <div className="relative w-full max-w-sm rounded-2xl border border-gray-200 bg-white p-6 shadow-2xl dark:border-gray-800 dark:bg-gray-900 text-gray-900 dark:text-white">
            <button
              type="button"
              onClick={() => setDeletePkgTarget(null)}
              className="absolute right-4 top-4 rounded-lg p-1 text-gray-400 hover:text-gray-600 hover:bg-gray-100 dark:hover:text-white dark:hover:bg-gray-800"
            >
              <X className="h-4 w-4" />
            </button>
            <div className="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-2xl bg-rose-50 text-rose-600 dark:bg-rose-500/10 dark:text-rose-400">
              <Trash2 className="h-6 w-6" />
            </div>
            <h3 className="text-center font-display text-base font-bold">Hapus Paket {deletePkgTarget.name}</h3>
            <p className="mt-1 text-center text-xs text-gray-500 dark:text-gray-400">
              Apakah Anda yakin ingin menghapus paket tarif hotspot ini dari sistem?
            </p>

            <div className="mt-4 rounded-2xl border border-gray-200 bg-gray-50 dark:border-gray-800 dark:bg-gray-900/50 p-3.5">
              <Checkbox
                checked={deletePkgMikrotik}
                onChange={setDeletePkgMikrotik}
                label="Hapus juga profil dari router MikroTik"
                description="Menghapus profil user hotspot langsung di RouterOS"
              />
            </div>

            <div className="mt-5 flex gap-2">
              <button
                type="button"
                onClick={() => setDeletePkgTarget(null)}
                className="flex-1 h-10 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-800 text-xs font-bold text-gray-700 dark:text-gray-200 transition"
              >
                Batal
              </button>
              <button
                type="button"
                onClick={handleConfirmDelete}
                className="flex-1 h-10 rounded-xl bg-rose-500 hover:bg-rose-600 text-xs font-bold text-white flex items-center justify-center gap-1.5 active:scale-95 transition shadow-xs"
              >
                <Trash2 className="h-3.5 w-3.5" />
                <span>Hapus Paket</span>
              </button>
            </div>
          </div>
        </div>
      )}
    </AppLayout>
  )
}