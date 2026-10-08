import { AppLayout } from "@/components/layout/app-layout"
import {
  Ticket,
  Printer,
  Trash2,
  Search,
  X,
  SlidersHorizontal,
  RotateCcw,
  CheckSquare,
  Package,
  Clock,
  Database,
  Layers,
  Wifi,
  ChevronLeft,
  RefreshCw,
  Server,
  Radio,
  Plus,
  Copy,
  Check,
  Settings,
  Activity,
  Play,
} from "lucide-react"
import { PageProps } from "@/types"
import { formatIDR, formatDate, cn } from "@/lib/utils"
import { adminSidebarItems, adminNavItems, adminBrand } from "@/lib/admin-nav"
import { router, useForm, Link } from "@inertiajs/react"
import { useState, useEffect, useRef, useMemo } from "react"
import { useCardSelection } from "@/hooks/use-card-selection"
import { SelectionFloatingBar } from "@/components/ui/selection-floating-bar"
import { MetricCard } from "@/components/tailadmin/MetricCard"
import { ViewModeSwitcher } from "@/components/ui/view-mode-switcher"
import Checkbox from "@/components/tailadmin/Checkbox"

interface Voucher {
  id: number
  username: string
  password?: string
  profile: string
  price?: number
  time_limit?: string
  data_limit?: string
  comment?: string
  batch_id?: string
  router_id?: number
  used?: boolean
  created_at: string
}

interface Batch {
  batch_id: string
  total_vouchers: number
  created_at: string
  profile: string
}

interface VoucherPackage {
  id: number
  name: string
  profile: string
  time_limit?: string
  duration_days?: number
  data_limit?: number
  price: number
}

export default function VoucherListPage({
  vouchers,
  batches = [],
  routers = [],
  selectedRouterId,
  profiles = [],
  packages = [],
  counts = { total: 0, online: 0, expired: 0, available: 0, recent: 0 },
  filters = {},
}: PageProps<{
  vouchers: { data: Voucher[]; total: number; current_page: number; last_page: number; next_page_url: string | null; prev_page_url: string | null }
  batches: Batch[]
  routers: { id: number; name: string; host: string }[]
  selectedRouterId: number | null
  profiles: string[]
  packages: VoucherPackage[]
  counts: { total: number; online: number; expired: number; available: number; recent: number }
  filters: { batch?: string; profile?: string; search?: string; router_id?: string; status?: string }
}>) {
  const [search, setSearch] = useState(filters.search || "")
  const [selectedBatch, setSelectedBatch] = useState(filters.batch || "")
  const [selectedProfile, setSelectedProfile] = useState(filters.profile || "")
  const [selectedStatus, setSelectedStatus] = useState(filters.status || "")
  const [showFilterBar, setShowFilterBar] = useState(Boolean(filters.batch || filters.profile || filters.status))
  const isFirstRender = useRef(true)

  useEffect(() => {
    if (isFirstRender.current) {
      isFirstRender.current = false
      return
    }
    const timeout = setTimeout(() => {
      const params = new URLSearchParams()
      if (selectedBatch) params.set("batch", selectedBatch)
      if (selectedProfile) params.set("profile", selectedProfile)
      if (selectedStatus) params.set("status", selectedStatus)
      if (search) params.set("search", search)
      if (selectedRouterId) params.set("router_id", selectedRouterId.toString())
      router.get(`/admin/voucher?${params.toString()}`, {}, { preserveState: true, preserveScroll: true, replace: true })
    }, 300)
    return () => clearTimeout(timeout)
  }, [search])

  const [viewMode, setViewMode] = useState<"table" | "grid">("table")
  const [generateModalOpen, setGenerateModalOpen] = useState(false)
  const [generateMode, setGenerateMode] = useState<"package" | "custom">(packages.length > 0 ? "package" : "custom")
  const [batchDeleteOpen, setBatchDeleteOpen] = useState(false)
  const [batchDeleteMikrotik, setBatchDeleteMikrotik] = useState(true)
  const [batchDeleting, setBatchDeleting] = useState(false)
  const [manageVoucher, setManageVoucher] = useState<Voucher | null>(null)
  const [copiedText, setCopiedText] = useState<string | null>(null)
  const [expandedId, setExpandedId] = useState<number | null>(null)

  const handleCopy = (text: string, _label: string) => {
    navigator.clipboard.writeText(text)
    setCopiedText(text)
    setTimeout(() => setCopiedText(null), 2000)
  }
  const [modalProfiles, setModalProfiles] = useState<string[]>(profiles)
  const [modalServers, setModalServers] = useState<string[]>([])
  const [loadingModalProfiles, setLoadingModalProfiles] = useState(false)

  // Multi-card selection hook
  const selection = useCardSelection()

  const isAllFilteredSelected = useMemo(
    () => vouchers.data.length > 0 && vouchers.data.every((v) => selection.isSelected(v.id)),
    [vouchers.data, selection]
  )

  const activeFilterCount = (selectedBatch ? 1 : 0) + (selectedProfile ? 1 : 0) + (selectedStatus ? 1 : 0)

  const generateForm = useForm<{
    router_id: string
    package_preset_id: string
    server: string
    profile: string
    quantity: number
    user_mode: string
    code_length: number
    prefix: string
    char_pattern: string
    time_limit: string
    data_limit_value: string
    data_limit_unit: string
    price: number
    comment: string
  }>({
    router_id: selectedRouterId ? String(selectedRouterId) : (routers[0]?.id ? String(routers[0].id) : ""),
    package_preset_id: packages[0] ? String(packages[0].id) : "",
    server: "all",
    profile: packages[0]?.profile || packages[0]?.name || (profiles[0] || "default"),
    quantity: 10,
    user_mode: "vc", // 'vc' = User=Pass, 'up' = User & Pass
    code_length: 6,
    prefix: "",
    char_pattern: "mix1", // 'num' | 'lower' | 'upper' | 'mix' | 'mix1'
    time_limit: packages[0]?.time_limit || (packages[0]?.duration_days ? `${packages[0].duration_days}d` : ""),
    data_limit_value: packages[0]?.data_limit ? String(packages[0].data_limit) : "",
    data_limit_unit: "MB",
    price: Number(packages[0]?.price) || 5000,
    comment: packages[0] ? `Paket ${packages[0].name}` : "",
  })

  const fetchModalProfiles = async (routerId: string) => {
    if (!routerId) return
    setLoadingModalProfiles(true)
    try {
      const res = await fetch(`/admin/voucher/profiles-json?router_id=${routerId}`, {
        headers: { Accept: "application/json" },
      })
      if (res.ok) {
        const data = await res.json()
        if (Array.isArray(data.profiles)) {
          setModalProfiles(data.profiles)
          if (data.profiles.length > 0 && !data.profiles.includes(generateForm.data.profile)) {
            generateForm.setData("profile", data.profiles[0])
          }
        }
        if (Array.isArray(data.servers)) {
          setModalServers(data.servers)
        }
      }
    } catch {
      // ignore
    } finally {
      setLoadingModalProfiles(false)
    }
  }

  const handleModalRouterChange = (rId: string) => {
    generateForm.setData("router_id", rId)
    fetchModalProfiles(rId)
  }

  const openGenerateModal = () => {
    const targetRouterId = selectedRouterId ? String(selectedRouterId) : (routers[0]?.id ? String(routers[0].id) : "")
    generateForm.setData({
      router_id: targetRouterId,
      package_preset_id: packages[0] ? String(packages[0].id) : "",
      server: "all",
      profile: packages[0]?.profile || packages[0]?.name || (profiles[0] || "default"),
      quantity: 10,
      user_mode: "vc",
      code_length: 6,
      prefix: "",
      char_pattern: "mix1",
      time_limit: packages[0]?.time_limit || (packages[0]?.duration_days ? `${packages[0].duration_days}d` : ""),
      data_limit_value: packages[0]?.data_limit ? String(packages[0].data_limit) : "",
      data_limit_unit: "MB",
      price: packages[0]?.price || 5000,
      comment: packages[0] ? `Paket ${packages[0].name}` : "",
    })
    setGenerateMode(packages.length > 0 ? "package" : "custom")
    setGenerateModalOpen(true)
    if (targetRouterId) {
      fetchModalProfiles(targetRouterId)
    }
  }

  const handleSelectPackage = (pkgId: string) => {
    const pkg = packages.find((p) => String(p.id) === String(pkgId))
    if (!pkg) return
    generateForm.setData({
      ...generateForm.data,
      package_preset_id: String(pkg.id),
      profile: pkg.profile || pkg.name,
      price: pkg.price || 0,
      time_limit: pkg.time_limit || (pkg.duration_days ? `${pkg.duration_days}d` : ""),
      data_limit_value: pkg.data_limit ? String(pkg.data_limit) : "",
      comment: `Paket ${pkg.name}`,
    })
  }

  const selectedPkgObj = packages.find((p) => String(p.id) === String(generateForm.data.package_preset_id))

  const handleGenerateSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    generateForm.post("/admin/voucher/generate", {
      preserveScroll: true,
      onSuccess: () => {
        setGenerateModalOpen(false)
      },
    })
  }

  const handleFilter = (batch?: string, profile?: string, status?: string) => {
    const params = new URLSearchParams()
    if (batch) params.set("batch", batch)
    if (profile) params.set("profile", profile)
    if (status) params.set("status", status)
    if (search) params.set("search", search)
    if (selectedRouterId) params.set("router_id", selectedRouterId.toString())
    router.get(`/admin/voucher?${params.toString()}`)
  }

  const handleResetFilter = () => {
    setSelectedBatch("")
    setSelectedProfile("")
    setSelectedStatus("")
    const params = new URLSearchParams()
    if (search) params.set("search", search)
    if (selectedRouterId) params.set("router_id", selectedRouterId.toString())
    router.get(`/admin/voucher?${params.toString()}`)
  }

  const handleDelete = (id: number) => {
    if (confirm("Hapus voucher ini?")) {
      router.post(`/admin/voucher/delete/${id}`, {}, { preserveScroll: true })
    }
  }

  const handleDeleteBatch = (batchId: string) => {
    if (confirm(`Hapus SEMUA voucher dalam batch ${batchId}?`)) {
      router.post(`/admin/voucher/delete-batch/${batchId}`, {}, { preserveScroll: true })
    }
  }

  const handleBatchDeleteSubmit = () => {
    if (selection.selectedIds.length === 0) return
    setBatchDeleting(true)
    router.post(
      "/admin/voucher/delete-selected",
      {
        ids: selection.selectedIds,
        delete_mikrotik: batchDeleteMikrotik,
      },
      {
        preserveScroll: true,
        onFinish: () => {
          setBatchDeleting(false)
          setBatchDeleteOpen(false)
          selection.deselectAll()
        },
      }
    )
  }

  const [syncingBatch, setSyncingBatch] = useState(false)
  const [syncingSelected, setSyncingSelected] = useState(false)

  const handleSyncBatch = (batchId: string) => {
    if (confirm(`Pusatkan dan sinkronisasikan semua voucher batch ${batchId} ke router MikroTik?`)) {
      setSyncingBatch(true)
      router.post(
        `/admin/voucher/sync-batch/${batchId}`,
        { router_id: selectedRouterId },
        {
          preserveScroll: true,
          onFinish: () => setSyncingBatch(false),
        }
      )
    }
  }

  const handleSyncSelected = () => {
    if (selection.selectedIds.length === 0) return
    if (confirm(`Sinkronkan ${selection.selectedIds.length} voucher terpilih ke router MikroTik?`)) {
      setSyncingSelected(true)
      router.post(
        "/admin/voucher/sync-selected",
        {
          ids: selection.selectedIds,
        },
        {
          preserveScroll: true,
          onFinish: () => {
            setSyncingSelected(false)
            selection.deselectAll()
          },
        }
      )
    }
  }

  const handlePrintSelected = () => {
    if (selection.selectedIds.length === 0) return
    window.open(`/admin/print-vouchers?ids=${selection.selectedIds.join(",")}`, "_blank")
  }

  return (
    <AppLayout
      title="Daftar Voucher Hotspot"
      brand={adminBrand}
      sidebarItems={adminSidebarItems}
      navItems={adminNavItems}
      hideBottomNav={selection.isSelectMode}
    >
      <div className="space-y-6">
        {/* Header Action Bar & Router Switcher */}
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
          <div className="flex items-center gap-2.5">
            <div>
              <h3 className="text-base font-bold text-gray-800 dark:text-white/90">
                Manajemen Voucher &amp; Batch
              </h3>
              <p className="text-xs text-gray-500 dark:text-gray-400">
                {vouchers.total || 0} total voucher terdata • {counts.online} sesi aktif
              </p>
            </div>
          </div>

          <div className="flex items-center gap-2 flex-wrap sm:flex-nowrap">
            {/* Router Selector */}
            {routers.length > 0 && (
              <select
                value={selectedRouterId || ""}
                onChange={(e) => {
                  const val = e.target.value
                  const params = new URLSearchParams()
                  if (val) params.set("router_id", val)
                  if (selectedBatch) params.set("batch", selectedBatch)
                  if (selectedProfile) params.set("profile", selectedProfile)
                  if (search) params.set("search", search)
                  router.get(`/admin/voucher?${params.toString()}`)
                }}
                className="h-10 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:hover:bg-gray-800 px-3 text-xs font-semibold text-gray-700 dark:text-gray-200 transition focus:outline-none focus:border-brand-500"
              >
                <option value="">Semua Router ({routers.length})</option>
                {routers.map((r) => (
                  <option key={r.id} value={r.id}>
                    {r.name}
                  </option>
                ))}
              </select>
            )}

            <Link
              href={`/admin/voucher/packages${selectedRouterId ? `?router_id=${selectedRouterId}` : ""}`}
              className="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:hover:bg-gray-800 text-xs font-semibold text-gray-700 dark:text-gray-200 transition"
            >
              <Package className="h-4 w-4 text-brand-500" />
              <span className="hidden sm:inline">Paket</span>
            </Link>

            <Link
              href={`/admin/voucher/active${selectedRouterId ? `?router_id=${selectedRouterId}` : ""}`}
              className="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:hover:bg-gray-800 text-xs font-semibold text-gray-700 dark:text-gray-200 transition"
            >
              <Wifi className="h-4 w-4 text-emerald-500" />
              <span className="hidden sm:inline">Online ({counts.online})</span>
            </Link>

            <a
              href={selectedBatch ? `/admin/print-vouchers?batch=${encodeURIComponent(selectedBatch)}&autoprint=1` : "/admin/print-vouchers?autoprint=1"}
              target="_blank"
              rel="noopener noreferrer"
              className="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:hover:bg-gray-800 text-xs font-semibold text-gray-700 dark:text-gray-200 transition"
            >
              <Printer className="h-4 w-4 text-brand-500" />
              <span className="hidden sm:inline">Cetak</span>
            </a>

            <button
              type="button"
              onClick={openGenerateModal}
              className="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-brand-500 hover:bg-brand-600 text-xs font-bold text-white shadow-xs transition active:scale-95"
            >
              <Play className="h-4 w-4" />
              <span>Generate</span>
            </button>
          </div>
        </div>

        {/* ── TOP 4 KPI METRICS (INTERACTIVE CLICKABLE) ── */}
        <div className="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
          <MetricCard
            title="Total Voucher"
            value={counts.total}
            sub="Seluruh batch voucher"
            icon={Ticket}
            iconBgColor="bg-brand-50 dark:bg-brand-500/10"
            iconColor="text-brand-500 dark:text-brand-400"
            badge={{ text: "Semua", color: "info" }}
            onClick={() => handleFilter(selectedBatch, selectedProfile, "")}
            isActive={!selectedStatus}
          />
          <MetricCard
            title="Sedang Online"
            value={counts.online}
            sub="Sesi login aktif"
            icon={Wifi}
            iconBgColor="bg-emerald-50 dark:bg-emerald-500/10"
            iconColor="text-emerald-600 dark:text-emerald-400"
            badge={{ text: "Online", color: "success" }}
            onClick={() => router.get(`/admin/voucher/active${selectedRouterId ? `?router_id=${selectedRouterId}` : ""}`)}
          />
          <MetricCard
            title="Expired / Terpakai"
            value={counts.expired}
            sub="Habis batas waktu/kuota"
            icon={Clock}
            iconBgColor="bg-rose-50 dark:bg-rose-500/10"
            iconColor="text-rose-600 dark:text-rose-400"
            badge={{ text: "Expired", color: "error" }}
            onClick={() => handleFilter(selectedBatch, selectedProfile, selectedStatus === "expired" ? "" : "expired")}
            isActive={selectedStatus === "expired"}
          />
          <MetricCard
            title="Baru Hari Ini"
            value={counts.recent}
            sub="Dibuat dalam 24 jam"
            icon={Activity}
            iconBgColor="bg-amber-50 dark:bg-amber-500/10"
            iconColor="text-amber-600 dark:text-amber-400"
            badge={{ text: "Hari Ini", color: "warning" }}
            onClick={() => handleFilter(selectedBatch, selectedProfile, selectedStatus === "recent" ? "" : "recent")}
            isActive={selectedStatus === "recent"}
          />
        </div>

        {/* ── MASTER TOOLBAR: 1-LINE SEARCH + FILTER + ACTIONS ── */}
        <div className="rounded-2xl border border-gray-200 bg-white p-3 sm:p-4 shadow-xs dark:border-gray-800 dark:bg-white/[0.03] space-y-3">
          <div className="flex flex-col gap-2.5 sm:gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div className="flex flex-wrap items-center gap-2 sm:gap-3 flex-1 min-w-0">
              <div className="relative flex-1 min-w-[180px] sm:min-w-[220px] w-full sm:w-auto">
                <Search className="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                <input
                  value={search}
                  onChange={(e) => setSearch(e.target.value)}
                  onKeyDown={(e) => { if (e.key === "Enter") handleFilter(selectedBatch, selectedProfile, selectedStatus) }}
                  placeholder="Cari kode voucher, batch ID, atau komentar..."
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

              {/* Status Select Filter Dropdown */}
              <select
                value={selectedStatus}
                onChange={(e) => {
                  setSelectedStatus(e.target.value)
                  handleFilter(selectedBatch, selectedProfile, e.target.value)
                }}
                className="h-10 rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 px-3 text-xs font-semibold text-gray-700 dark:text-gray-200 cursor-pointer w-full sm:w-auto shrink-0 focus:outline-none focus:border-brand-500"
              >
                <option value="">Semua Status</option>
                <option value="available">Tersedia (Aktif)</option>
                <option value="expired">Expired / Terpakai</option>
                <option value="recent">Baru Hari Ini</option>
              </select>

              {/* Profile Select Filter Dropdown */}
              {profiles.length > 0 && (
                <select
                  value={selectedProfile}
                  onChange={(e) => {
                    setSelectedProfile(e.target.value)
                    handleFilter(selectedBatch, e.target.value, selectedStatus)
                  }}
                  className="h-10 rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 px-3 text-xs font-semibold text-gray-700 dark:text-gray-200 cursor-pointer w-full sm:w-auto shrink-0 focus:outline-none focus:border-brand-500"
                >
                  <option value="">Semua Profil ({profiles.length})</option>
                  {profiles.map((p) => (
                    <option key={p} value={p}>
                      {p}
                    </option>
                  ))}
                </select>
              )}
            </div>

            <div className="flex items-center gap-2 shrink-0 w-full sm:w-auto justify-end">
              <ViewModeSwitcher value={viewMode} onChange={setViewMode} />

              <button
                type="button"
                onClick={() => setShowFilterBar(!showFilterBar)}
                className={cn(
                  "h-10 px-3.5 text-xs font-semibold gap-1.5 rounded-xl shrink-0 flex items-center justify-center border transition cursor-pointer",
                  showFilterBar || activeFilterCount > 0
                    ? "bg-brand-500 border-brand-600 text-white shadow-xs"
                    : "border-gray-200 bg-white hover:bg-gray-50 text-gray-700 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800"
                )}
              >
                <SlidersHorizontal className="h-3.5 w-3.5" />
                <span>Filter Lanjut</span>
                {activeFilterCount > 0 && (
                  <span className="flex h-4 w-4 items-center justify-center rounded-full bg-white text-brand-600 text-[9px] font-black">
                    {activeFilterCount}
                  </span>
                )}
              </button>

              <button
                type="button"
                onClick={() => {
                  if (selection.isSelectMode) {
                    selection.deselectAll()
                  } else {
                    selection.selectAll(vouchers.data.map((v) => v.id))
                  }
                }}
                className={cn(
                  "h-10 px-3.5 text-xs font-semibold gap-1.5 rounded-xl shrink-0 flex items-center justify-center border transition cursor-pointer",
                  selection.isSelectMode
                    ? "bg-brand-500 border-brand-600 text-white shadow-xs"
                    : "border-gray-200 bg-white hover:bg-gray-50 text-gray-700 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800"
                )}
                title="Pilih Beberapa Voucher"
              >
                <CheckSquare className="h-3.5 w-3.5" />
                <span>{selection.isSelectMode ? "Batal Pilih" : "Mode Pilih"}</span>
              </button>
            </div>
          </div>

          {/* ── KOTAK FILTER EXPANDABLE ── */}
          {showFilterBar && (
            <div className="pt-3 border-t border-gray-100 dark:border-gray-800/80 space-y-3">
              <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div className="space-y-1">
                  <label className="text-[11px] font-semibold text-gray-600 dark:text-gray-300">Filter Batch</label>
                  <select
                    value={selectedBatch}
                    onChange={(e) => {
                      setSelectedBatch(e.target.value)
                      handleFilter(e.target.value, selectedProfile, selectedStatus)
                    }}
                    className="h-9 w-full rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 px-3 text-xs font-medium text-gray-900 dark:text-white focus:outline-none focus:border-brand-500"
                  >
                    <option value="">Semua Batch</option>
                    {batches.map((b) => (
                      <option key={b.batch_id} value={b.batch_id}>
                        {b.batch_id} ({b.total_vouchers} vch - {b.profile})
                      </option>
                    ))}
                  </select>
                </div>

                <div className="space-y-1">
                  <label className="text-[11px] font-semibold text-gray-600 dark:text-gray-300">Filter Profil</label>
                  <select
                    value={selectedProfile}
                    onChange={(e) => {
                      setSelectedProfile(e.target.value)
                      handleFilter(selectedBatch, e.target.value, selectedStatus)
                    }}
                    className="h-9 w-full rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 px-3 text-xs font-medium text-gray-900 dark:text-white focus:outline-none focus:border-brand-500"
                  >
                    <option value="">Semua Profil</option>
                    {profiles.map((p) => (
                      <option key={p} value={p}>{p}</option>
                    ))}
                  </select>
                </div>

                <div className="space-y-1">
                  <label className="text-[11px] font-semibold text-gray-600 dark:text-gray-300">Filter Status</label>
                  <select
                    value={selectedStatus}
                    onChange={(e) => {
                      setSelectedStatus(e.target.value)
                      handleFilter(selectedBatch, selectedProfile, e.target.value)
                    }}
                    className="h-9 w-full rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 px-3 text-xs font-medium text-gray-900 dark:text-white focus:outline-none focus:border-brand-500"
                  >
                    <option value="">Semua Status</option>
                    <option value="available">Tersedia (Aktif)</option>
                    <option value="expired">Expired / Terpakai</option>
                  </select>
                </div>
              </div>

              {activeFilterCount > 0 && (
                <div className="flex justify-end pt-1">
                  <button
                    type="button"
                    onClick={handleResetFilter}
                    className="h-8 px-3 rounded-lg border border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:hover:bg-gray-800 text-xs font-semibold text-gray-700 dark:text-gray-200 flex items-center gap-1.5 transition"
                  >
                    <RotateCcw className="h-3 w-3" />
                    <span>Reset Filter</span>
                  </button>
                </div>
              )}
            </div>
          )}
        </div>

        {/* ── BATCH BANNER AKTIF ── */}
        {selectedBatch ? (
          <div className="flex items-center justify-between gap-2 rounded-2xl border border-brand-200 bg-brand-50/50 p-3.5 text-xs text-brand-900 dark:border-brand-800/40 dark:bg-brand-950/20 dark:text-brand-300 shadow-xs">
            <div className="flex items-center gap-2">
              <span className="font-medium text-gray-600 dark:text-gray-400">Filter Batch:</span>
              <span className="font-mono font-bold text-brand-600 dark:text-brand-400">{selectedBatch}</span>
            </div>
            <div className="flex items-center gap-1.5">
              <button
                type="button"
                onClick={() => handleSyncBatch(selectedBatch)}
                disabled={syncingBatch}
                className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-brand-300 bg-white hover:bg-brand-50 text-brand-700 dark:border-brand-700 dark:bg-gray-900 dark:hover:bg-gray-800 dark:text-brand-300 font-semibold text-xs transition disabled:opacity-50 whitespace-nowrap"
                title="Sinkronisasi batch ini ke router MikroTik"
              >
                <RefreshCw className={cn("h-3.5 w-3.5", syncingBatch && "animate-spin")} />
                <span>Sync Router</span>
              </button>
              <a
                href={`/admin/print-vouchers?batch=${encodeURIComponent(selectedBatch)}&autoprint=1`}
                target="_blank"
                rel="noopener noreferrer"
                className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-gray-200 bg-white hover:bg-gray-50 text-gray-700 dark:border-gray-800 dark:bg-gray-900 dark:hover:bg-gray-800 dark:text-gray-200 font-semibold text-xs transition"
                title="Cetak seluruh voucher batch ini"
              >
                <Printer className="h-3.5 w-3.5" />
                <span>Cetak Batch</span>
              </a>
              <button
                type="button"
                onClick={() => handleDeleteBatch(selectedBatch)}
                className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-rose-200 bg-white hover:bg-rose-50 text-rose-600 dark:border-rose-900/50 dark:bg-gray-900 dark:hover:bg-rose-950/30 dark:text-rose-400 font-semibold text-xs transition whitespace-nowrap"
                title="Hapus batch voucher ini"
              >
                <Trash2 className="h-3.5 w-3.5" />
                <span>Hapus Batch</span>
              </button>
            </div>
          </div>
        ) : null}

        {/* ── VOUCHER CARDS GRID ── */}
        {vouchers.data.length === 0 ? (
          <div className="rounded-2xl border border-dashed border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900/30 p-12 text-center space-y-3">
            <Ticket className="mx-auto h-10 w-10 text-gray-400 dark:text-gray-500 mb-1" />
            <h3 className="text-sm font-bold text-gray-800 dark:text-white">Tidak ada voucher ditemukan</h3>
            <p className="text-xs text-gray-500 dark:text-gray-400 max-w-sm mx-auto">
              Belum ada voucher yang cocok dengan filter aktif atau belum ada voucher yang digenerate.
            </p>
            <button
              type="button"
              onClick={openGenerateModal}
              className="inline-flex items-center gap-1.5 h-10 px-4 rounded-xl bg-brand-500 hover:bg-brand-600 text-xs font-bold text-white active:scale-95 transition shadow-xs"
            >
              <Play className="h-3.5 w-3.5" />
              <span>Generate Voucher Sekarang</span>
            </button>
          </div>
        ) : viewMode === "table" ? (
          <div className="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xs dark:border-gray-800 dark:bg-white/[0.03]">
            <div className="w-full overflow-x-auto custom-scrollbar">
              <table className="w-full min-w-[1050px] text-left text-xs border-collapse">
                <thead className="border-b border-gray-200 bg-gray-50/75 text-[11px] font-bold text-gray-500 uppercase tracking-wider dark:border-gray-800 dark:bg-gray-900/50 dark:text-gray-400">
                  <tr>
                    <th className="py-3 px-4 w-10">
                      <Checkbox
                        checked={isAllFilteredSelected}
                        onChange={(checked) => {
                          if (checked) {
                            selection.selectAll(vouchers.data.map((v) => v.id))
                          } else {
                            selection.deselectAll()
                          }
                        }}
                      />
                    </th>
                    <th className="py-3 px-4">Kode Voucher</th>
                    <th className="py-3 px-4">Password</th>
                    <th className="py-3 px-4">Profil / Paket</th>
                    <th className="py-3 px-4">Batas Waktu</th>
                    <th className="py-3 px-4">Batas Kuota</th>
                    <th className="py-3 px-4">Harga / Tarif</th>
                    <th className="py-3 px-4">Batch ID / Komentar</th>
                    <th className="py-3 px-4">Status</th>
                    <th className="py-3 px-4 text-center">Aksi</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-gray-100 dark:divide-gray-800/60">
                  {vouchers.data.map((v) => {
                    const isSelected = selection.isSelected(v.id)
                    return (
                      <tr
                        key={v.id}
                        onClick={() => selection.isSelectMode && selection.toggleSelect(v.id)}
                        className={cn(
                          "hover:bg-gray-50/50 dark:hover:bg-white/[0.02] transition-colors",
                          isSelected && "bg-brand-50/40 dark:bg-brand-950/20"
                        )}
                      >
                        <td className="py-3 px-4">
                          <Checkbox
                            checked={isSelected}
                            onChange={() => selection.toggleSelect(v.id)}
                          />
                        </td>
                        <td className="py-3 px-4 font-mono font-bold text-gray-900 dark:text-white">
                          <div className="flex items-center gap-1.5">
                            <span>{v.username}</span>
                            <button
                              type="button"
                              onClick={(e) => {
                                e.stopPropagation()
                                handleCopy(v.username, "Username")
                              }}
                              className="text-gray-400 hover:text-brand-500 transition-colors p-1"
                              title="Salin Kode Voucher"
                            >
                              {copiedText === v.username ? (
                                <Check className="h-3.5 w-3.5 text-emerald-500" />
                              ) : (
                                <Copy className="h-3.5 w-3.5" />
                              )}
                            </button>
                          </div>
                        </td>
                        <td className="py-3 px-4 font-mono text-gray-600 dark:text-gray-300">
                          {v.password && v.password !== v.username ? v.password : "-"}
                        </td>
                        <td className="py-3 px-4">
                          <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold bg-brand-500 text-white shadow-xs whitespace-nowrap">
                            {v.profile}
                          </span>
                        </td>
                        <td className="py-3 px-4 font-mono text-gray-700 dark:text-gray-300">
                          {v.time_limit ? (
                            <span className="inline-flex items-center gap-1 font-mono text-amber-600 dark:text-amber-400 font-semibold">
                              <Clock className="h-3 w-3" />
                              <span>{v.time_limit}</span>
                            </span>
                          ) : (
                            <span className="text-gray-400">-</span>
                          )}
                        </td>
                        <td className="py-3 px-4 font-mono text-gray-700 dark:text-gray-300">
                          {v.data_limit || <span className="text-gray-400">-</span>}
                        </td>
                        <td className="py-3 px-4 font-mono font-bold text-emerald-600 dark:text-emerald-400">
                          {v.price ? formatIDR(v.price) : "Gratis"}
                        </td>
                        <td className="py-3 px-4 font-mono text-xs text-gray-500 dark:text-gray-400 truncate max-w-[140px]">
                          {v.batch_id || v.comment || "-"}
                        </td>
                        <td className="py-3 px-4">
                          <span
                            className={cn(
                              "inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold text-white shadow-xs whitespace-nowrap",
                              v.used ? "bg-rose-500" : "bg-emerald-500"
                            )}
                          >
                            {v.used ? "Expired" : "Tersedia"}
                          </span>
                        </td>
                        <td className="py-3 px-4 text-center">
                          <button
                            type="button"
                            onClick={(e) => {
                              e.stopPropagation()
                              setManageVoucher(v)
                            }}
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
          <div className="grid gap-3.5 grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5 items-start">
            {vouchers.data.map((v) => {
              const isSelected = selection.isSelected(v.id)
              const isExpanded = expandedId === v.id
              const handlers = selection.getCardHandlers(v.id, () => {
                setExpandedId(isExpanded ? null : v.id)
              })

              return (
                <div
                  key={v.id}
                  onContextMenu={handlers.onContextMenu}
                  onTouchStart={handlers.onTouchStart}
                  onTouchEnd={handlers.onTouchEnd}
                  onTouchMove={handlers.onTouchMove}
                  onClick={handlers.onClick}
                  className={cn(
                    "p-4 rounded-2xl border transition-all flex flex-col justify-between shadow-xs select-none touch-manipulation cursor-pointer relative bg-white dark:bg-gray-900",
                    isSelected
                      ? "border-brand-500 ring-2 ring-brand-500/20 dark:border-brand-500"
                      : isExpanded
                        ? "border-brand-400 dark:border-brand-600"
                        : "border-gray-200 dark:border-gray-800 hover:border-gray-300 dark:hover:border-gray-700"
                  )}
                >
                  <div>
                    <div className="flex items-start justify-between gap-2">
                      <div className="flex items-center gap-2.5 min-w-0 flex-1">
                        {/* Multi-Select Indicator / Icon */}
                        {selection.isSelectMode ? (
                          <div
                            className={cn(
                              "flex h-8 w-8 shrink-0 items-center justify-center rounded-xl border transition-colors",
                              isSelected
                                ? "bg-brand-500 border-brand-600 text-white font-bold"
                                : "border-gray-300 bg-gray-50 text-transparent dark:border-gray-700 dark:bg-gray-800"
                            )}
                          >
                            <CheckSquare className="h-4 w-4" />
                          </div>
                        ) : (
                          <div
                            className={cn(
                              "flex h-8 w-8 shrink-0 items-center justify-center rounded-xl font-bold",
                              v.used
                                ? "bg-rose-500 text-white"
                                : "bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-400"
                            )}
                          >
                            <Ticket className="h-4 w-4" />
                          </div>
                        )}

                        <div className="min-w-0 flex-1">
                          <div className="flex items-center gap-1.5 flex-wrap">
                            <p className="font-mono text-xs font-bold text-gray-900 dark:text-white truncate">{v.username}</p>
                            {v.used ? (
                              <span className="bg-rose-500 text-white font-bold text-[9px] uppercase tracking-wider px-2 py-0.5 rounded-md shadow-xs shrink-0">
                                Expired
                              </span>
                            ) : (
                              <span className="bg-emerald-500 text-white font-bold text-[9px] uppercase tracking-wider px-2 py-0.5 rounded-md shadow-xs shrink-0">
                                Tersedia
                              </span>
                            )}
                          </div>
                          {v.password && v.password !== v.username && (
                            <p className="font-mono text-[10px] text-gray-500 dark:text-gray-400 truncate">Pass: {v.password}</p>
                          )}
                          <p className="text-[10px] text-gray-500 dark:text-gray-400 truncate font-mono mt-0.5">
                            {v.batch_id || v.comment || "-"}
                          </p>
                        </div>
                      </div>

                      <div className="flex items-center gap-1 shrink-0">
                        {!selection.isSelectMode && (
                          <button
                            type="button"
                            onClick={(e) => {
                              e.stopPropagation()
                              setManageVoucher(v)
                            }}
                            className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200 font-bold text-xs transition cursor-pointer"
                          >
                            <Settings className="h-3.5 w-3.5" />
                            <span>Kelola</span>
                          </button>
                        )}
                      </div>
                    </div>
                  </div>

                  {/* Badges: Profil, Limits, Price */}
                  <div className="mt-3 flex items-center justify-between border-t border-gray-100 dark:border-gray-800 pt-2 text-[10px] gap-1.5">
                    <div className="flex items-center gap-1 flex-wrap min-w-0">
                      <span className="bg-brand-500 text-white font-bold text-[9px] uppercase tracking-wider px-2 py-0.5 rounded-md shadow-xs truncate max-w-[85px]">
                        {v.profile}
                      </span>
                      {v.time_limit && (
                        <span className="bg-amber-500 text-white font-bold text-[9px] uppercase tracking-wider px-2 py-0.5 rounded-md shadow-xs truncate flex items-center gap-0.5 font-mono">
                          <Clock className="h-2.5 w-2.5" />
                          <span>{v.time_limit}</span>
                        </span>
                      )}
                      {v.data_limit && (
                        <span className="bg-purple-600 text-white font-bold text-[9px] uppercase tracking-wider px-2 py-0.5 rounded-md shadow-xs truncate font-mono">
                          {v.data_limit}
                        </span>
                      )}
                    </div>
                    <span className="font-bold shrink-0 font-mono text-xs text-emerald-600 dark:text-emerald-400">
                      {v.price ? formatIDR(v.price) : "Gratis"}
                    </span>
                  </div>

                  {/* Expanded Details Section */}
                  {isExpanded && !selection.isSelectMode && (
                    <div
                      className="mt-2.5 pt-2 border-t border-gray-100 dark:border-gray-800 space-y-1.5 text-xs text-gray-600 dark:text-gray-300 animate-in fade-in-50 duration-200"
                      onClick={(e) => e.stopPropagation()}
                    >
                      <div className="flex items-center justify-between text-[11px]">
                        <span className="text-gray-500 dark:text-gray-400">Dibuat</span>
                        <span className="font-mono text-gray-700 dark:text-gray-200">{v.created_at?.slice(0, 10) || "-"}</span>
                      </div>
                      {v.batch_id && (
                        <div className="flex items-center justify-between text-[11px]">
                          <span className="text-gray-500 dark:text-gray-400">Batch ID</span>
                          <span className="font-mono text-brand-600 dark:text-brand-400 truncate max-w-[120px]">{v.batch_id}</span>
                        </div>
                      )}
                      <div className="pt-1 flex items-center justify-end">
                        <a
                          href={`/admin/print-vouchers?ids=${v.id}&autoprint=1`}
                          target="_blank"
                          rel="noopener noreferrer"
                          className="flex h-8 w-8 items-center justify-center rounded-xl border border-gray-200 bg-gray-50 text-gray-700 hover:bg-gray-100 dark:border-gray-800 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700 transition"
                          title="Cetak Voucher"
                        >
                          <Printer className="h-4 w-4" />
                        </a>
                      </div>
                    </div>
                  )}
                </div>
              )
            })}
          </div>
        )}

        {/* ── PAGINATION ── */}
        {vouchers.last_page > 1 && (
          <div className="flex items-center justify-between pt-2">
            <button
              disabled={!vouchers.prev_page_url}
              onClick={() => vouchers.prev_page_url && router.visit(vouchers.prev_page_url)}
              className="h-9 px-3 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:hover:bg-gray-800 text-xs font-semibold text-gray-700 dark:text-gray-200 transition disabled:opacity-50"
            >
              Sebelumnya
            </button>
            <span className="text-xs text-gray-500 dark:text-gray-400 font-medium">
              Halaman {vouchers.current_page} dari {vouchers.last_page} ({vouchers.total} voucher)
            </span>
            <button
              disabled={!vouchers.next_page_url}
              onClick={() => vouchers.next_page_url && router.visit(vouchers.next_page_url)}
              className="h-9 px-3 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:hover:bg-gray-800 text-xs font-semibold text-gray-700 dark:text-gray-200 transition disabled:opacity-50"
            >
              Selanjutnya
            </button>
          </div>
        )}
      </div>

      {/* ── FLOATING SELECTION BAR (MULTI-SELECT VOUCHERS) ── */}
      <SelectionFloatingBar
        selectedCount={selection.selectedIds.length}
        totalCount={vouchers.data.length}
        onSelectAll={() => selection.selectAll(vouchers.data.map((v) => v.id))}
        onDeselectAll={selection.deselectAll}
        label="Voucher Dipilih"
      >
        <div className="flex items-center gap-1.5">
          <button
            type="button"
            onClick={handleSyncSelected}
            disabled={syncingSelected}
            className="h-8 px-3 text-xs font-bold gap-1.5 rounded-xl bg-brand-500 hover:bg-brand-600 text-white flex items-center shrink-0 transition shadow-xs whitespace-nowrap"
            title="Pusatkan voucher terpilih ke router MikroTik"
          >
            <RefreshCw className={cn("h-3.5 w-3.5", syncingSelected && "animate-spin")} />
            <span className="hidden sm:inline">Sync MikroTik</span>
          </button>

          <button
            type="button"
            onClick={handlePrintSelected}
            className="h-8 px-3 text-xs font-bold gap-1.5 rounded-xl bg-[#052A4E] hover:bg-[#073866] text-white flex items-center shrink-0 transition shadow-xs"
            title="Cetak Voucher Terpilih"
          >
            <Printer className="h-3.5 w-3.5" />
            <span className="hidden sm:inline">Cetak</span>
          </button>

          <button
            type="button"
            onClick={() => setBatchDeleteOpen(true)}
            className="h-8 px-3 text-xs font-bold gap-1.5 rounded-xl bg-rose-500 hover:bg-rose-600 text-white flex items-center justify-center shrink-0 transition shadow-xs active:scale-95 whitespace-nowrap"
            title={`Hapus ${selection.selectedIds.length} Voucher Terpilih`}
          >
            <Trash2 className="h-3.5 w-3.5" />
            <span className="hidden sm:inline">Hapus ({selection.selectedIds.length})</span>
          </button>
        </div>
      </SelectionFloatingBar>

      {/* ── MODAL KONFIRMASI HAPUS BATCH / TERPILIH ── */}
      {batchDeleteOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
          <div className="fixed inset-0 bg-black/70 backdrop-blur-sm" onClick={() => setBatchDeleteOpen(false)} />
          <div className="relative w-full max-w-sm rounded-2xl border border-gray-200 bg-white p-6 shadow-2xl dark:border-gray-800 dark:bg-gray-900 text-gray-900 dark:text-white">
            <button
              type="button"
              onClick={() => setBatchDeleteOpen(false)}
              className="absolute right-4 top-4 rounded-lg p-1 text-gray-400 hover:text-gray-600 hover:bg-gray-100 dark:hover:text-white dark:hover:bg-gray-800"
            >
              <X className="h-4 w-4" />
            </button>
            <div className="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-2xl bg-rose-50 text-rose-600 dark:bg-rose-500/10 dark:text-rose-400">
              <Trash2 className="h-6 w-6" />
            </div>
            <h3 className="text-center font-display text-base font-bold">Hapus {selection.selectedIds.length} Voucher</h3>
            <p className="mt-1 text-center text-xs text-gray-500 dark:text-gray-400">
              Apakah Anda yakin ingin menghapus {selection.selectedIds.length} voucher terpilih dari sistem?
            </p>

            <div className="mt-4 flex cursor-pointer items-start gap-3 rounded-2xl border border-gray-200 bg-gray-50 dark:border-gray-800 dark:bg-gray-900/50 px-3.5 py-3 transition-colors hover:border-gray-300 dark:hover:border-gray-700">
              <Checkbox
                checked={batchDeleteMikrotik}
                onChange={(checked) => setBatchDeleteMikrotik(checked)}
              />
              <span className="flex-1 text-xs font-semibold text-gray-900 dark:text-white select-none">
                Hapus juga dari router MikroTik
                <span className="block text-[10px] font-normal text-gray-500 dark:text-gray-400">Menghapus akun hotspot langsung dari tabel router MikroTik</span>
              </span>
            </div>

            <div className="mt-5 flex gap-2">
              <button
                type="button"
                onClick={() => setBatchDeleteOpen(false)}
                className="flex-1 h-10 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-800 text-xs font-bold text-gray-700 dark:text-gray-200 transition"
              >
                Batal
              </button>
              <button
                type="button"
                disabled={batchDeleting}
                onClick={handleBatchDeleteSubmit}
                className="flex-1 h-10 rounded-xl bg-rose-600 hover:bg-rose-700 text-xs font-bold text-white flex items-center justify-center gap-1.5 active:scale-95 transition disabled:opacity-50"
              >
                <Trash2 className="h-3.5 w-3.5" />
                <span>{batchDeleting ? "Menghapus..." : "Ya, Hapus"}</span>
              </button>
            </div>
          </div>
        </div>
      )}

      {/* ── MODAL GENERATE VOUCHER (TAILADMIN STANDARD) ── */}
      {generateModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4">
          <div className="fixed inset-0 bg-black/70 backdrop-blur-sm" onClick={() => setGenerateModalOpen(false)} />
          <div className="relative flex max-h-[92vh] w-full max-w-md flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-2xl dark:border-gray-800 dark:bg-gray-900 text-gray-900 dark:text-white">
            {/* Header */}
            <div className="flex items-center justify-between border-b border-gray-100 dark:border-gray-800 px-5 py-4 shrink-0">
              <div className="flex items-center gap-2.5 min-w-0">
                <div className="flex h-8 w-8 items-center justify-center rounded-xl bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-400">
                  <Play className="h-4 w-4" />
                </div>
                <div className="min-w-0">
                  <h3 className="font-display text-sm font-bold text-gray-900 dark:text-white truncate">Generate Voucher Hotspot</h3>
                  <p className="text-[11px] text-gray-500 dark:text-gray-400 truncate">Buat batch voucher instan untuk MikroTik</p>
                </div>
              </div>
              <button
                type="button"
                onClick={() => setGenerateModalOpen(false)}
                className="rounded-lg p-1.5 text-gray-400 hover:text-gray-600 hover:bg-gray-100 dark:hover:text-white dark:hover:bg-gray-800 shrink-0"
              >
                <X className="h-4 w-4" />
              </button>
            </div>

            {/* Form */}
            <form onSubmit={handleGenerateSubmit} className="flex min-h-0 flex-1 flex-col overflow-hidden">
              <div className="flex-1 overflow-y-auto overflow-x-hidden p-5 space-y-4 min-h-0">
                
                {/* 1. Pilih Router MikroTik */}
                <div className="space-y-1.5">
                  <div className="flex items-center justify-between">
                    <label className="text-xs font-semibold text-gray-700 dark:text-gray-300">Router MikroTik *</label>
                    {loadingModalProfiles && (
                      <span className="text-[11px] text-brand-500 animate-pulse">Memuat profil router...</span>
                    )}
                  </div>
                  <select
                    value={generateForm.data.router_id}
                    onChange={(e) => handleModalRouterChange(e.target.value)}
                    className="h-10 w-full rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 px-3 text-xs font-semibold text-gray-900 dark:text-white focus:outline-none focus:border-brand-500"
                    required
                  >
                    {routers.length === 0 ? (
                      <option value="">Tidak ada router tersedia</option>
                    ) : (
                      routers.map((r) => (
                        <option key={r.id} value={r.id}>
                          {r.name} {r.host ? `(${r.host})` : ""}
                        </option>
                      ))
                    )}
                  </select>
                </div>

                {/* Hotspot Server Selection */}
                {modalServers.length > 0 && (
                  <div className="space-y-1.5">
                    <label className="text-xs font-semibold text-gray-700 dark:text-gray-300">Server Hotspot</label>
                    <select
                      value={generateForm.data.server}
                      onChange={(e) => generateForm.setData("server", e.target.value)}
                      className="h-10 w-full rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 px-3 text-xs text-gray-900 dark:text-white focus:outline-none focus:border-brand-500"
                    >
                      <option value="all">Semua Server (all)</option>
                      {modalServers.map((s) => (
                        <option key={s} value={s}>{s}</option>
                      ))}
                    </select>
                  </div>
                )}

                {/* 2. Dropdown Pemilihan Mode Template vs Kustom */}
                <div className="space-y-1.5">
                  <label className="text-xs font-semibold text-gray-700 dark:text-gray-300">Mode Pembuatan Voucher *</label>
                  <select
                    value={generateMode}
                    onChange={(e) => {
                      const mode = e.target.value as "package" | "custom"
                      setGenerateMode(mode)
                      if (mode === "package" && packages[0]) {
                        handleSelectPackage(String(packages[0].id))
                      } else if (mode === "custom") {
                        generateForm.setData("package_preset_id", "")
                      }
                    }}
                    className="h-10 w-full rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 px-3 text-xs font-semibold text-gray-900 dark:text-white focus:outline-none focus:border-brand-500"
                  >
                    {packages.length > 0 && (
                      <option value="package">Gunakan Template Paket Hotspot</option>
                    )}
                    <option value="custom">Kustom / Manual (Input Bebas)</option>
                  </select>
                </div>

                {/* ── JIKA MODE TEMPLATE PAKET ── */}
                {generateMode === "package" && packages.length > 0 ? (
                  <div className="space-y-3">
                    <div className="space-y-1.5">
                      <label className="text-xs font-semibold text-gray-700 dark:text-gray-300">Pilih Paket Hotspot *</label>
                      <select
                        value={generateForm.data.package_preset_id}
                        onChange={(e) => handleSelectPackage(e.target.value)}
                        className="h-10 w-full rounded-xl border border-brand-500 bg-white dark:border-brand-500 dark:bg-gray-900 px-3 text-xs font-semibold text-gray-900 dark:text-white focus:outline-none focus:border-brand-500"
                        required
                      >
                        {packages.map((pkg) => {
                          const timeLabel = pkg.time_limit
                            ? pkg.time_limit
                            : (pkg.duration_days ? `${pkg.duration_days} Hari` : "Tanpa Limit")
                          return (
                            <option key={pkg.id} value={pkg.id}>
                              {pkg.name} — {formatIDR(pkg.price)} ({timeLabel})
                            </option>
                          )
                        })}
                      </select>
                    </div>

                    {/* Ringkasan Parameter Paket Terpilih */}
                    {selectedPkgObj && (
                      <div className="space-y-1.5 rounded-2xl border border-brand-100 bg-brand-50/40 p-3.5 text-xs dark:border-brand-900/30 dark:bg-brand-950/20">
                        <div className="flex items-center justify-between">
                          <span className="text-gray-500 dark:text-gray-400 text-[11px]">Profil Bandwidth</span>
                          <span className="font-mono font-bold text-brand-600 dark:text-brand-400">{selectedPkgObj.profile || selectedPkgObj.name}</span>
                        </div>
                        <div className="flex items-center justify-between">
                          <span className="text-gray-500 dark:text-gray-400 text-[11px]">Harga Jual</span>
                          <span className="font-mono font-bold text-emerald-600 dark:text-emerald-400">{formatIDR(selectedPkgObj.price || 0)}</span>
                        </div>
                        <div className="flex items-center justify-between">
                          <span className="text-gray-500 dark:text-gray-400 text-[11px]">Batas Waktu</span>
                          <span className="font-mono font-semibold text-amber-600 dark:text-amber-400">
                            {selectedPkgObj.time_limit
                              ? selectedPkgObj.time_limit
                              : (selectedPkgObj.duration_days ? `${selectedPkgObj.duration_days} Hari (${selectedPkgObj.duration_days}d)` : "Tanpa Batas")}
                          </span>
                        </div>
                        <div className="flex items-center justify-between">
                          <span className="text-gray-500 dark:text-gray-400 text-[11px]">Batas Kuota</span>
                          <span className="font-mono font-semibold text-blue-600 dark:text-blue-400">
                            {selectedPkgObj.data_limit ? `${selectedPkgObj.data_limit} MB` : "Unlimited Kuota"}
                          </span>
                        </div>
                      </div>
                    )}
                  </div>
                ) : (
                  /* ── JIKA MODE MANUAL ── */
                  <div className="space-y-3 rounded-2xl border border-gray-200 bg-gray-50/50 p-3.5 dark:border-gray-800 dark:bg-gray-900/50">
                    <div className="space-y-1.5">
                      <label className="text-xs font-semibold text-gray-700 dark:text-gray-300">Profil Bandwidth MikroTik *</label>
                      <select
                        value={generateForm.data.profile}
                        onChange={(e) => generateForm.setData("profile", e.target.value)}
                        className="h-10 w-full rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 px-3 text-xs text-gray-900 dark:text-white focus:outline-none focus:border-brand-500"
                        required
                      >
                        {modalProfiles.length > 0 ? (
                          modalProfiles.map((p) => (
                            <option key={p} value={p}>{p}</option>
                          ))
                        ) : (
                          <option value="default">default</option>
                        )}
                      </select>
                    </div>

                    <div className="space-y-1.5">
                      <label className="text-xs font-semibold text-gray-700 dark:text-gray-300">Batas Waktu (Limit Uptime)</label>
                      <input
                        value={generateForm.data.time_limit}
                        onChange={(e) => generateForm.setData("time_limit", e.target.value)}
                        placeholder="cth: 3h, 12h, 1d"
                        className="h-10 w-full rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 px-3 text-xs font-mono text-gray-900 dark:text-white placeholder-gray-400 focus:outline-none focus:border-brand-500"
                      />
                    </div>

                    <div className="space-y-1.5">
                      <label className="text-xs font-semibold text-gray-700 dark:text-gray-300">Batas Kuota (Opsional)</label>
                      <div className="flex gap-2">
                        <input
                          type="number"
                          step="any"
                          min={0}
                          value={generateForm.data.data_limit_value}
                          onChange={(e) => generateForm.setData("data_limit_value", e.target.value.replace(/^0+(?=\d)/, ""))}
                          placeholder="cth: 1 atau 500"
                          className="h-10 flex-1 rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 px-3 text-xs text-gray-900 dark:text-white placeholder-gray-400 focus:outline-none focus:border-brand-500"
                        />
                        <select
                          value={generateForm.data.data_limit_unit}
                          onChange={(e) => generateForm.setData("data_limit_unit", e.target.value as "MB" | "GB")}
                          className="h-10 w-20 rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 px-2 text-xs font-bold text-gray-900 dark:text-white focus:outline-none focus:border-brand-500 shrink-0"
                        >
                          <option value="GB">GB</option>
                          <option value="MB">MB</option>
                        </select>
                      </div>
                    </div>

                    <div className="space-y-1.5">
                      <label className="text-xs font-semibold text-gray-700 dark:text-gray-300">Harga Jual (Rp) *</label>
                      <input
                        type="number"
                        min={0}
                        value={generateForm.data.price}
                        onChange={(e) => generateForm.setData("price", Number(e.target.value.replace(/^0+(?=\d)/, "")) || 0)}
                        placeholder="5000"
                        className="h-10 w-full rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 px-3 text-xs text-gray-900 dark:text-white placeholder-gray-400 focus:outline-none focus:border-brand-500"
                        required
                      />
                    </div>
                  </div>
                )}

                {/* ── PARAMETER UMUM VOUCHER ── */}
                <div className="space-y-1.5">
                  <label className="text-xs font-semibold text-gray-700 dark:text-gray-300">Jumlah Voucher (Qty) *</label>
                  <input
                    type="number"
                    min={1}
                    max={500}
                    value={generateForm.data.quantity}
                    onChange={(e) => generateForm.setData("quantity", Number(e.target.value))}
                    className="h-10 w-full rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 px-3 text-xs font-bold text-gray-900 dark:text-white focus:outline-none focus:border-brand-500"
                    required
                  />
                  <p className="text-[10px] text-gray-500 dark:text-gray-400">Maksimal 500 voucher per batch.</p>
                </div>

                <div className="space-y-1.5">
                  <label className="text-xs font-semibold text-gray-700 dark:text-gray-300">Format Akun *</label>
                  <select
                    value={generateForm.data.user_mode}
                    onChange={(e) => generateForm.setData("user_mode", e.target.value as "vc" | "up")}
                    className="h-10 w-full rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 px-3 text-xs text-gray-900 dark:text-white focus:outline-none focus:border-brand-500 font-medium"
                  >
                    <option value="vc">User = Pass (Kode Tunggal)</option>
                    <option value="up">User &amp; Pass Berbeda</option>
                  </select>
                </div>

                <div className="space-y-1.5">
                  <label className="text-xs font-semibold text-gray-700 dark:text-gray-300">Panjang Kode *</label>
                  <input
                    type="number"
                    min={3}
                    max={12}
                    value={generateForm.data.code_length}
                    onChange={(e) => generateForm.setData("code_length", Number(e.target.value))}
                    className="h-10 w-full rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 px-3 text-xs font-mono text-gray-900 dark:text-white focus:outline-none focus:border-brand-500"
                    required
                  />
                </div>

                <div className="space-y-1.5">
                  <label className="text-xs font-semibold text-gray-700 dark:text-gray-300">Pola Karakter</label>
                  <select
                    value={generateForm.data.char_pattern}
                    onChange={(e) => generateForm.setData("char_pattern", e.target.value as any)}
                    className="h-10 w-full rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 px-3 text-xs text-gray-900 dark:text-white focus:outline-none focus:border-brand-500"
                  >
                    <option value="mix">Acak Huruf &amp; Angka (a3b9)</option>
                    <option value="mix1">Acak Kapital &amp; Angka (A3B9)</option>
                    <option value="num">Hanya Angka (8492)</option>
                    <option value="lower">Hanya Huruf Kecil (abcd)</option>
                    <option value="upper">Hanya Kapital (ABCD)</option>
                  </select>
                </div>

                <div className="space-y-1.5">
                  <label className="text-xs font-semibold text-gray-700 dark:text-gray-300">Prefix (Opsional)</label>
                  <input
                    value={generateForm.data.prefix}
                    onChange={(e) => generateForm.setData("prefix", e.target.value)}
                    placeholder="cth: VC-"
                    className="h-10 w-full rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 px-3 text-xs font-mono text-gray-900 dark:text-white placeholder-gray-400 focus:outline-none focus:border-brand-500"
                  />
                </div>

                <div className="space-y-1.5">
                  <label className="text-xs font-semibold text-gray-700 dark:text-gray-300">Komentar / Batch Tag (Opsional)</label>
                  <input
                    value={generateForm.data.comment}
                    onChange={(e) => generateForm.setData("comment", e.target.value)}
                    placeholder="cth: Warung Bu Ani / Promo"
                    className="h-10 w-full rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 px-3 text-xs text-gray-900 dark:text-white placeholder-gray-400 focus:outline-none focus:border-brand-500"
                  />
                </div>
              </div>

              {/* Footer */}
              <div className="flex items-center gap-3 border-t border-gray-100 dark:border-gray-800 bg-gray-50/50 dark:bg-gray-900/50 p-4 shrink-0">
                <button
                  type="button"
                  className="flex-1 h-10 text-xs font-bold rounded-xl border border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200 transition"
                  onClick={() => setGenerateModalOpen(false)}
                >
                  Batal
                </button>
                <button
                  type="submit"
                  className="flex-1 gap-1.5 h-10 text-xs font-bold bg-brand-500 hover:bg-brand-600 text-white rounded-xl flex items-center justify-center transition disabled:opacity-50 shadow-xs"
                  disabled={generateForm.processing}
                >
                  <Play className="h-3.5 w-3.5" />
                  <span>{generateForm.processing ? "Membuat..." : "Generate Voucher"}</span>
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* ── MODAL KELOLA VOUCHER ── */}
      {manageVoucher && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
          <div className="fixed inset-0 bg-black/70 backdrop-blur-sm" onClick={() => setManageVoucher(null)} />
          <div className="relative w-full max-w-md max-h-[92vh] flex flex-col rounded-2xl border border-gray-200 bg-white shadow-2xl dark:border-gray-800 dark:bg-gray-900 text-gray-900 dark:text-white overflow-hidden animate-in fade-in zoom-in-95 duration-200">
            <div className="flex items-center justify-between border-b border-gray-100 dark:border-gray-800 px-5 py-4 shrink-0">
              <div className="flex items-center gap-2.5">
                <div className="flex h-9 w-9 items-center justify-center rounded-xl bg-brand-500/10 text-brand-500">
                  <Ticket className="h-5 w-5" />
                </div>
                <div>
                  <h3 className="text-sm font-bold">Kelola Voucher Hotspot</h3>
                  <p className="text-[11px] text-gray-500 dark:text-gray-400">Detail kode voucher dan tindakan cepat</p>
                </div>
              </div>
              <button
                type="button"
                onClick={() => setManageVoucher(null)}
                className="rounded-lg p-1.5 text-gray-400 hover:text-gray-600 hover:bg-gray-100 dark:hover:text-white dark:hover:bg-gray-800"
              >
                <X className="h-4 w-4" />
              </button>
            </div>

            <div className="flex-1 overflow-y-auto p-5 space-y-4">
              {/* Credentials Box */}
              <div className="rounded-xl border border-gray-100 bg-gray-50/80 dark:border-gray-800 dark:bg-gray-900/50 p-4 space-y-3">
                <div className="flex items-center justify-between">
                  <span className="text-xs text-gray-500 dark:text-gray-400">Username</span>
                  <div className="flex items-center gap-2">
                    <span className="font-mono font-bold text-sm text-gray-900 dark:text-white">{manageVoucher.username}</span>
                    <button
                      type="button"
                      onClick={() => handleCopy(manageVoucher.username, "Username")}
                      className="p-1 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 rounded-md hover:bg-gray-200 dark:hover:bg-gray-800"
                      title="Salin Username"
                    >
                      {copiedText === manageVoucher.username ? (
                        <Check className="h-3.5 w-3.5 text-emerald-500" />
                      ) : (
                        <Copy className="h-3.5 w-3.5" />
                      )}
                    </button>
                  </div>
                </div>

                {manageVoucher.password && (
                  <div className="flex items-center justify-between border-t border-gray-200/60 dark:border-gray-800/60 pt-2">
                    <span className="text-xs text-gray-500 dark:text-gray-400">Password</span>
                    <div className="flex items-center gap-2">
                      <span className="font-mono font-bold text-sm text-gray-900 dark:text-white">{manageVoucher.password}</span>
                      <button
                        type="button"
                        onClick={() => handleCopy(manageVoucher.password!, "Password")}
                        className="p-1 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 rounded-md hover:bg-gray-200 dark:hover:bg-gray-800"
                        title="Salin Password"
                      >
                        {copiedText === manageVoucher.password ? (
                          <Check className="h-3.5 w-3.5 text-emerald-500" />
                        ) : (
                          <Copy className="h-3.5 w-3.5" />
                        )}
                      </button>
                    </div>
                  </div>
                )}

                <div className="flex items-center justify-between border-t border-gray-200/60 dark:border-gray-800/60 pt-2">
                  <span className="text-xs text-gray-500 dark:text-gray-400">Status</span>
                  <span
                    className={cn(
                      "px-2 py-0.5 rounded-md text-[11px] font-bold text-white shadow-xs",
                      manageVoucher.used ? "bg-rose-500" : "bg-emerald-500"
                    )}
                  >
                    {manageVoucher.used ? "Sudah Digunakan" : "Aktif / Tersedia"}
                  </span>
                </div>
              </div>

              {/* Detail Info Grid */}
              <div className="grid grid-cols-2 gap-2 text-xs">
                <div className="p-3 rounded-xl border border-gray-100 bg-gray-50/80 dark:border-gray-800 dark:bg-gray-900/50">
                  <span className="text-[10px] text-gray-500 dark:text-gray-400 block">Profil Paket</span>
                  <span className="font-bold text-gray-900 dark:text-white inline-flex items-center px-2 py-0.5 mt-1 rounded-md text-[11px] bg-brand-500 text-white shadow-xs whitespace-nowrap">
                    {manageVoucher.profile}
                  </span>
                </div>
                <div className="p-3 rounded-xl border border-gray-100 bg-gray-50/80 dark:border-gray-800 dark:bg-gray-900/50">
                  <span className="text-[10px] text-gray-500 dark:text-gray-400 block">Harga</span>
                  <span className="font-bold text-emerald-600 dark:text-emerald-400 block mt-1">
                    {manageVoucher.price ? formatIDR(manageVoucher.price) : "Gratis"}
                  </span>
                </div>
                <div className="p-3 rounded-xl border border-gray-100 bg-gray-50/80 dark:border-gray-800 dark:bg-gray-900/50">
                  <span className="text-[10px] text-gray-500 dark:text-gray-400 block">Batas Waktu</span>
                  <span className="font-semibold text-gray-900 dark:text-white block mt-0.5">
                    {manageVoucher.time_limit || "Tidak Terbatas"}
                  </span>
                </div>
                <div className="p-3 rounded-xl border border-gray-100 bg-gray-50/80 dark:border-gray-800 dark:bg-gray-900/50">
                  <span className="text-[10px] text-gray-500 dark:text-gray-400 block">Batas Kuota</span>
                  <span className="font-semibold text-gray-900 dark:text-white block mt-0.5">
                    {manageVoucher.data_limit || "Tidak Terbatas"}
                  </span>
                </div>
              </div>

              {manageVoucher.batch_id && (
                <div className="text-xs text-gray-500 dark:text-gray-400 flex items-center justify-between px-1">
                  <span>Batch ID: <strong className="text-gray-700 dark:text-gray-300 font-mono">{manageVoucher.batch_id}</strong></span>
                  <span>Dibuat: {manageVoucher.created_at ? formatDate(manageVoucher.created_at) : "-"}</span>
                </div>
              )}

              {/* Action Buttons */}
              <div className="space-y-2 pt-2 border-t border-gray-100 dark:border-gray-800">
                <a
                  href={`/admin/print-vouchers?ids=${manageVoucher.id}&autoprint=1`}
                  target="_blank"
                  rel="noreferrer"
                  className="w-full inline-flex items-center justify-center gap-1.5 px-3.5 py-2.5 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:hover:bg-gray-800 text-xs font-semibold text-gray-700 dark:text-gray-200 transition shadow-2xs"
                >
                  <Printer className="h-4 w-4 text-brand-500" />
                  <span>Cetak Voucher Satuan</span>
                </a>

                <button
                  type="button"
                  onClick={() => {
                    const id = manageVoucher.id
                    setManageVoucher(null)
                    handleDelete(id)
                  }}
                  className="w-full inline-flex items-center justify-center gap-1.5 px-3.5 py-2.5 rounded-xl bg-rose-500 hover:bg-rose-600 active:scale-95 text-xs font-bold text-white shadow-xs transition"
                >
                  <Trash2 className="h-4 w-4" />
                  <span>Hapus Voucher Ini</span>
                </button>
              </div>
            </div>
          </div>
        </div>
      )}
    </AppLayout>
  )
}
