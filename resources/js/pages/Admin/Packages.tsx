import * as React from "react"
import { useState, useMemo, useDeferredValue, useEffect } from "react"
import { AppLayout } from "@/components/layout/app-layout"
import Chart from "react-apexcharts"
import type { ApexOptions } from "apexcharts"
import {
  Boxes,
  Plus,
  Pencil,
  Trash2,
  RefreshCw,
  ShieldAlert,
  ChevronDown,
  AlertTriangle,
  Users,
  Layers,
  Search,
  X,
  Radio,
  Tag,
  Wifi,
  Info,
  Server,
  Hash,
  Settings,
  BarChart3,
  PieChart,
  Wallet,
  Activity,
} from "lucide-react"
import { MetricCard } from "@/components/tailadmin/MetricCard"
import { EmptyState } from "@/components/ui/empty-state"
import { PageProps } from "@/types"
import { cn, formatIDR } from "@/lib/utils"
import { adminSidebarItems, adminNavItems, adminBrand } from "@/lib/admin-nav"
import { useForm, router, Link } from "@inertiajs/react"
import { CreateSheet } from "@/components/ui/create-sheet"
import { PriceInput } from "@/components/ui/price-input"
import { SyncPackageDialog } from "@/components/ui/sync-package-dialog"
import { SyncInfoModal } from "@/components/ui/sync-info-modal"
import { SyncOverlay } from "@/components/ui/sync-overlay"
import { useCardSelection } from "@/hooks/use-card-selection"
import { ViewModeSwitcher, type ViewMode } from "@/components/ui/view-mode-switcher"

interface Package {
  id: number
  name: string
  price: number
  promo_price: number
  promo_cycles: number
  admin_fee: number
  late_fee: number
  materai: number
  customers_count: number
  is_active: boolean
  router_id: number | null
  router_name: string | null
  profile_normal: string | null
  profile_isolir: string | null
  isolir_address_list?: string | null
  auto_isolir: boolean
}

export default function PackagesPage({
  packages = [],
  routers = [],
  mikrotikError,
  create = false,
  companyName = "NODERA Billing",
  tenantName,
}: PageProps<{
  packages: Package[]
  routers: { id: number; name: string }[]
  mikrotikError: string
  create: boolean
  companyName?: string
  tenantName?: string
}>) {
  const [sheetOpen, setSheetOpen] = useState(create ?? false)
  const [syncOpen, setSyncOpen] = useState(false)
  const [infoModalOpen, setInfoModalOpen] = useState(false)
  const [editing, setEditing] = useState<Package | null>(null)
  const [managingPackage, setManagingPackage] = useState<Package | null>(null)
  const [packageType, setPackageType] = useState<"arp" | "pppoe" | "hotspot">("arp")
  const [search, setSearch] = useState("")
  const [selectedRouter, setSelectedRouter] = useState<string>("all")
  const [viewMode, setViewMode] = useState<ViewMode>(() => {
    if (typeof window !== "undefined") {
      const saved = localStorage.getItem("nodera_packages_view_mode") as ViewMode | null
      if (saved === "table" || saved === "grid") return saved
    }
    return "table"
  })

  const handleSetViewMode = (mode: ViewMode) => {
    setViewMode(mode)
    if (typeof window !== "undefined") {
      localStorage.setItem("nodera_packages_view_mode", mode)
    }
  }

  const [expandedIds, setExpandedIds] = useState<Record<number, boolean>>({})
  const [syncInfo, setSyncInfo] = useState<{ show: boolean; title?: string; statusMessage?: string } | null>(null)
  const [liveProfiles, setLiveProfiles] = useState<{ name: string; rate_limit?: string }[]>([])
  const [liveAddressLists, setLiveAddressLists] = useState<string[]>([])
  const [loadingProfiles, setLoadingProfiles] = useState(false)
  const [deleteTarget, setDeleteTarget] = useState<Package | null>(null)
  const [deleteFromMikrotik, setDeleteFromMikrotik] = useState(true)
  const [deleting, setDeleting] = useState(false)
  const [localAutoIsolir, setLocalAutoIsolir] = useState<Record<number, boolean>>({})

  // Card Selection Hook
  const selection = useCardSelection()

  const toggleExpand = (id: number) => {
    setExpandedIds((prev) => ({ ...prev, [id]: !prev[id] }))
  }

  const getAutoIsolir = (p: Package) => {
    return localAutoIsolir[p.id] !== undefined ? localAutoIsolir[p.id] : Boolean(p.auto_isolir)
  }

  const handleToggleAutoIsolir = (e: React.MouseEvent, p: Package) => {
    e.stopPropagation()
    const nextVal = !getAutoIsolir(p)
    setLocalAutoIsolir((prev) => ({ ...prev, [p.id]: nextVal }))

    router.post(
      `/admin/billing/packages/auto-isolir/${p.id}`,
      { auto_isolir: nextVal },
      {
        preserveScroll: true,
        preserveState: true,
        onError: () => {
          setLocalAutoIsolir((prev) => ({ ...prev, [p.id]: !nextVal }))
        },
      }
    )
  }

  const deferredSearch = useDeferredValue(search)

  const filteredPackages = useMemo(() => {
    return packages.filter((p) => {
      if (selectedRouter !== "all") {
        if (String(p.router_id) !== selectedRouter) return false
      }
      if (!deferredSearch) return true
      const lower = deferredSearch.toLowerCase()
      return (
        p.name.toLowerCase().includes(lower) ||
        (p.router_name && p.router_name.toLowerCase().includes(lower)) ||
        (p.profile_normal && p.profile_normal.toLowerCase().includes(lower)) ||
        (p.profile_isolir && p.profile_isolir.toLowerCase().includes(lower))
      )
    })
  }, [packages, deferredSearch, selectedRouter])

  const form = useForm({
    name: "",
    price: "",
    router_id: routers.length > 0 ? String(routers[0].id) : "",
    profile_normal: "",
    profile_isolir: "isolir",
    isolir_address_list: "ISOLIR_LIST",
    auto_isolir: true,
    promo_price: "0",
    promo_cycles: "0",
    admin_fee: "0",
    late_fee: "0",
    materai: "0",
  })

  // Fetch live profiles & address-lists whenever form.data.router_id changes
  useEffect(() => {
    if (!form.data.router_id) {
      setLiveProfiles([])
      setLiveAddressLists([])
      return
    }

    setLoadingProfiles(true)
    fetch(`/admin/billing/packages/live-profiles?router_id=${form.data.router_id}`)
      .then((res) => res.json())
      .then((data) => {
        if (data.profiles && Array.isArray(data.profiles)) {
          setLiveProfiles(data.profiles)
        }
        if (data.address_lists && Array.isArray(data.address_lists)) {
          setLiveAddressLists(data.address_lists)
        }
      })
      .catch((err) => {
        console.error("Failed to load profiles:", err)
      })
      .finally(() => {
        setLoadingProfiles(false)
      })
  }, [form.data.router_id])

  const openAdd = () => {
    setEditing(null)
    setPackageType("arp")
    form.reset()
    form.setData({
      name: "",
      price: "",
      router_id: routers.length > 0 ? String(routers[0].id) : "",
      profile_normal: "2M/2M",
      profile_isolir: "512k/512k",
      isolir_address_list: "ISOLIR_LIST",
      auto_isolir: true,
      promo_price: "0",
      promo_cycles: "0",
      admin_fee: "0",
      late_fee: "0",
      materai: "0",
    })
    setSheetOpen(true)
  }

  const openEdit = (p: Package) => {
    setEditing(p)
    if (p.profile_normal && p.profile_normal.includes("/")) {
      setPackageType("arp")
    } else {
      setPackageType("pppoe")
    }

    form.setData({
      name: p.name,
      price: String(p.price),
      router_id: p.router_id ? String(p.router_id) : "",
      profile_normal: p.profile_normal || "",
      profile_isolir: p.profile_isolir || "isolir",
      isolir_address_list: p.isolir_address_list || "ISOLIR_LIST",
      auto_isolir: Boolean(p.auto_isolir),
      promo_price: String(p.promo_price || 0),
      promo_cycles: String(p.promo_cycles || 0),
      admin_fee: String(p.admin_fee || 0),
      late_fee: String(p.late_fee || 0),
      materai: String(p.materai || 0),
    })
    setSheetOpen(true)
  }

  const submit = (e: React.FormEvent) => {
    e.preventDefault()
    if (editing) {
      form.post(`/admin/billing/packages/edit/${editing.id}`, {
        preserveScroll: true,
        onSuccess: () => {
          setSheetOpen(false)
          setManagingPackage(null)
          form.reset()
        },
      })
    } else {
      form.post("/admin/billing/packages/add", {
        preserveScroll: true,
        onSuccess: () => {
          setSheetOpen(false)
          form.reset()
        },
      })
    }
  }

  // ── SUBSCRIBER DISTRIBUTION BAR CHART ──
  const subscriberBarData = useMemo(() => {
    const sorted = [...packages].sort((a, b) => (b.customers_count || 0) - (a.customers_count || 0)).slice(0, 6)
    return {
      categories: sorted.length > 0 ? sorted.map((p) => p.name) : ["Belum ada paket"],
      values: sorted.length > 0 ? sorted.map((p) => p.customers_count || 0) : [0],
    }
  }, [packages])

  const subscriberBarOptions: ApexOptions = useMemo(() => ({
    chart: { type: "bar", toolbar: { show: false }, fontFamily: "inherit" },
    colors: ["#0073C6"],
    plotOptions: {
      bar: {
        horizontal: true,
        borderRadius: 6,
        barHeight: "55%",
      },
    },
    dataLabels: {
      enabled: true,
      formatter: (val) => `${val} User`,
      style: { fontSize: "10px", fontWeight: "bold" },
    },
    xaxis: {
      categories: subscriberBarData.categories,
      labels: {
        style: { colors: "#64748B", fontSize: "11px" },
        formatter: (val) => `${val}`,
      },
      axisBorder: { show: false },
      axisTicks: { show: false },
    },
    yaxis: {
      labels: { style: { colors: "#64748B", fontSize: "11px" } },
    },
    grid: { borderColor: "#F1F5F9", strokeDashArray: 4 },
    tooltip: {
      theme: "light",
      y: { formatter: (val) => `${val} Pelanggan Aktif` },
    },
  }), [subscriberBarData])

  // ── REVENUE CONTRIBUTION DONUT CHART ──
  const revenueDonutData = useMemo(() => {
    const sorted = [...packages]
      .map((p) => ({
        name: p.name,
        revenue: (p.price || 0) * (p.customers_count || 0),
      }))
      .sort((a, b) => b.revenue - a.revenue)
    return {
      labels: sorted.length > 0 ? sorted.map((p) => p.name) : ["Belum ada paket"],
      series: sorted.length > 0 ? sorted.map((p) => p.revenue) : [0],
    }
  }, [packages])

  const totalEstimatedRevenue = useMemo(() => {
    return packages.reduce((acc, p) => acc + ((p.price || 0) * (p.customers_count || 0)), 0)
  }, [packages])

  const totalSubscribers = useMemo(() => {
    return packages.reduce((acc, p) => acc + (p.customers_count || 0), 0)
  }, [packages])

  const revenueDonutOptions: ApexOptions = useMemo(() => ({
    chart: { type: "donut", fontFamily: "inherit" },
    labels: revenueDonutData.labels,
    colors: ["#0073C6", "#8B5CF6", "#10B981", "#F59E0B", "#EC4899", "#6366F1"],
    legend: { position: "bottom", labels: { colors: "#64748B" } },
    dataLabels: { enabled: true, formatter: (val) => `${Number(val).toFixed(0)}%` },
    stroke: { width: 2, colors: ["transparent"] },
    plotOptions: {
      pie: {
        donut: {
          size: "68%",
          labels: {
            show: true,
            total: {
              show: true,
              label: "Potensi Omzet",
              color: "#64748B",
              formatter: () => formatIDR(totalEstimatedRevenue),
            },
          },
        },
      },
    },
    tooltip: {
      theme: "light",
      y: { formatter: (val) => formatIDR(val) },
    },
  }), [revenueDonutData, totalEstimatedRevenue])

  return (
    <AppLayout
      title="Paket Langganan"
      brand={adminBrand}
      sidebarItems={adminSidebarItems}
      navItems={adminNavItems}
      hideBottomNav={selection.isSelectMode}
    >
      <div className="space-y-4 sm:space-y-6">
        {mikrotikError && (
          <div className="rounded-xl border border-rose-200 bg-rose-50 p-3.5 text-xs font-semibold text-rose-700 dark:border-rose-900/50 dark:bg-rose-950/40 dark:text-rose-300">
            {mikrotikError}
          </div>
        )}

        {/* ── 3 TOP KPI METRICS ── */}
        <div className="grid grid-cols-1 sm:grid-cols-3 gap-3 sm:gap-4">
          <MetricCard
            title="Total Paket Profil"
            value={`${packages.length} Profil`}
            icon={<Boxes className="h-6 w-6 text-brand-500" />}
            sub="Tier layanan bandwidth"
          />

          <MetricCard
            title="Total Pelanggan Terdaftar"
            value={`${totalSubscribers} Pelanggan`}
            icon={<Users className="h-6 w-6 text-emerald-500" />}
            sub="Pelanggan aktif berpaket"
          />

          <MetricCard
            title="Potensi Omzet Bulanan"
            value={formatIDR(totalEstimatedRevenue)}
            icon={<Wallet className="h-6 w-6 text-purple-500" />}
            sub="Gross MRR langganan"
          />
        </div>

        {/* ── CHARTS SECTION: SUBSCRIBERS & REVENUE DONUT ── */}
        <div className="grid grid-cols-1 lg:grid-cols-12 gap-4 sm:gap-6">
          {/* Subscribers Distribution Bar Chart */}
          <div className="lg:col-span-7 rounded-2xl border border-gray-200 bg-white p-4 sm:p-5 shadow-xs dark:border-gray-800 dark:bg-white/[0.03]">
            <div className="flex items-center justify-between gap-2 mb-3 sm:mb-4">
              <div className="flex items-center gap-2">
                <div className="flex h-8 w-8 items-center justify-center rounded-xl bg-brand-50 text-brand-500 dark:bg-brand-500/10">
                  <BarChart3 className="h-4 w-4" />
                </div>
                <div>
                  <h3 className="text-sm sm:text-base font-bold text-gray-800 dark:text-white/90">
                    Distribusi Pelanggan per Paket
                  </h3>
                  <p className="text-[11px] text-gray-500 dark:text-gray-400">
                    Perbandingan jumlah pengguna aktif pada tiap profil kecepatan
                  </p>
                </div>
              </div>
            </div>
            <div className="h-[220px] sm:h-[240px] w-full">
              <Chart
                options={subscriberBarOptions}
                series={[{ name: "Pelanggan", data: subscriberBarData.values }]}
                type="bar"
                height="100%"
                width="100%"
              />
            </div>
          </div>

          {/* Revenue Contribution Donut Chart */}
          <div className="lg:col-span-5 rounded-2xl border border-gray-200 bg-white p-4 sm:p-5 shadow-xs dark:border-gray-800 dark:bg-white/[0.03]">
            <div className="flex items-center justify-between gap-2 mb-3 sm:mb-4">
              <div className="flex items-center gap-2">
                <div className="flex h-8 w-8 items-center justify-center rounded-xl bg-purple-50 text-purple-600 dark:bg-purple-500/10">
                  <PieChart className="h-4 w-4" />
                </div>
                <div>
                  <h3 className="text-sm sm:text-base font-bold text-gray-800 dark:text-white/90">
                    Kontribusi Omzet Paket
                  </h3>
                  <p className="text-[11px] text-gray-500 dark:text-gray-400">
                    Proporsi pendapatan kotor per tier paket
                  </p>
                </div>
              </div>
            </div>
            <div className="h-[220px] sm:h-[240px] w-full flex items-center justify-center">
              <Chart
                options={revenueDonutOptions}
                series={revenueDonutData.series}
                type="donut"
                height="100%"
                width="100%"
              />
            </div>
          </div>
        </div>

        {/* ── 1-LINE RESPONSIVE INTEGRATED TOOLBAR ── */}
        <div className="rounded-2xl border border-gray-200 bg-white p-3 sm:p-4 shadow-xs dark:border-gray-800 dark:bg-white/[0.03] flex flex-col gap-2.5 sm:gap-3 lg:flex-row lg:items-center lg:justify-between">
          <div className="flex flex-wrap items-center gap-2 sm:gap-3 flex-1 min-w-0">
            {/* Search Bar */}
            <div className="relative flex-1 min-w-[180px] sm:min-w-[220px] w-full sm:w-auto">
              <Search className="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400 dark:text-gray-500" />
              <input
                type="text"
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                placeholder="Cari nama paket, router, profil MikroTik..."
                className="h-10 w-full rounded-xl border border-gray-200 bg-gray-50/75 pl-9 pr-8 text-xs font-medium text-gray-900 placeholder:text-gray-400 focus:border-brand-500 focus:outline-none dark:border-gray-800 dark:bg-gray-900/50 dark:text-white dark:placeholder:text-gray-500"
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

            {/* Router Filter */}
            {routers.length > 0 && (
              <select
                value={selectedRouter}
                onChange={(e) => setSelectedRouter(e.target.value)}
                className="h-10 rounded-xl border border-gray-200 bg-white px-3 text-xs font-semibold text-gray-700 shadow-2xs focus:border-brand-500 focus:outline-hidden dark:border-gray-800 dark:bg-gray-900 dark:text-gray-200 cursor-pointer w-full sm:w-auto shrink-0"
              >
                <option value="all">Semua Router</option>
                {routers.map((r) => (
                  <option key={r.id} value={String(r.id)}>
                    {r.name}
                  </option>
                ))}
              </select>
            )}
          </div>

          <div className="flex items-center gap-2 shrink-0 w-full sm:w-auto justify-end">
            {/* ViewModeSwitcher */}
            <div className="shrink-0">
              <ViewModeSwitcher value={viewMode} onChange={handleSetViewMode} size="sm" />
            </div>

            <button
              type="button"
              onClick={() => setInfoModalOpen(true)}
              className="inline-flex h-10 items-center gap-1.5 px-3 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:hover:bg-gray-800 text-xs font-semibold text-gray-700 dark:text-gray-200 transition shadow-2xs cursor-pointer shrink-0"
              title="Informasi Sinkronisasi"
            >
              <Info className="h-4 w-4 text-brand-500" />
              <span className="hidden sm:inline">Petunjuk Sync</span>
            </button>

            <button
              type="button"
              onClick={() => setSyncOpen(true)}
              className="inline-flex h-10 items-center gap-1.5 px-3 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:hover:bg-gray-800 text-xs font-semibold text-gray-700 dark:text-gray-200 transition shadow-2xs cursor-pointer shrink-0"
              title="Sinkronkan dari Router MikroTik"
            >
              <RefreshCw className="h-4 w-4 text-brand-500" />
              <span className="hidden sm:inline">Sync Router</span>
            </button>

            <button
              type="button"
              onClick={openAdd}
              className="h-10 px-3.5 sm:px-4 rounded-xl bg-brand-500 hover:bg-brand-600 active:scale-95 text-xs font-bold text-white shadow-xs flex items-center justify-center gap-1.5 w-full sm:w-auto shrink-0 cursor-pointer"
              title="Tambah Paket Baru"
            >
              <Plus className="h-4 w-4" />
              <span>Tambah Paket</span>
            </button>
          </div>
        </div>

        {/* Master Card Container */}
        <div className="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xs dark:border-gray-800 dark:bg-white/[0.03]">
          {filteredPackages.length === 0 ? (
            <div className="p-8 sm:p-12 text-center space-y-4">
              <EmptyState
                icon={<Boxes className="h-8 w-8 text-brand-500" />}
                title="Belum ada paket langganan"
                description={
                  search || selectedRouter !== "all"
                    ? "Tidak ada paket yang cocok dengan filter yang dipilih."
                    : "Tambahkan paket langganan pertama Anda atau sinkronkan langsung dari profil router MikroTik."
                }
              />
              <div className="flex items-center justify-center gap-3 pt-2">
                <button
                  type="button"
                  onClick={() => setSyncOpen(true)}
                  className="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:hover:bg-gray-800 text-xs font-semibold text-gray-700 dark:text-gray-200 transition shadow-2xs cursor-pointer"
                >
                  <RefreshCw className="h-3.5 w-3.5 text-brand-500" />
                  <span>Sinkron dari MikroTik</span>
                </button>
                <button
                  type="button"
                  onClick={openAdd}
                  className="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-brand-500 hover:bg-brand-600 active:scale-95 text-xs font-bold text-white shadow-xs transition cursor-pointer"
                >
                  <Plus className="h-3.5 w-3.5" />
                  <span>Tambah Paket Baru</span>
                </button>
              </div>
            </div>
          ) : viewMode === "table" ? (
            /* High-Density TailAdmin Table View */
            <div className="w-full overflow-x-auto custom-scrollbar">
              <table className="w-full text-left text-xs min-w-[950px]">
                <thead>
                  <tr className="border-b border-gray-200 bg-gray-50/75 dark:border-gray-800 dark:bg-gray-800/50 text-gray-500 dark:text-gray-400 font-semibold">
                    <th className="py-3.5 px-4">Nama Paket</th>
                    <th className="py-3.5 px-3">Tarif Bulanan</th>
                    <th className="py-3.5 px-3">Router Server</th>
                    <th className="py-3.5 px-3">Profil Normal</th>
                    <th className="py-3.5 px-3">Profil Isolir</th>
                    <th className="py-3.5 px-3">Pelanggan</th>
                    <th className="py-3.5 px-3 text-center">Auto Isolir</th>
                    <th className="py-3.5 px-4 text-center">Aksi</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-gray-200 dark:divide-gray-800">
                  {filteredPackages.map((p) => {
                    const hasUsers = (p.customers_count || 0) > 0
                    const isAutoIsolirActive = getAutoIsolir(p)

                    return (
                      <tr key={p.id} className="hover:bg-gray-50/50 dark:hover:bg-white/[0.02] transition-colors">
                        <td className="py-3 px-4">
                          <div className="min-w-0">
                            <p className="font-bold text-gray-800 dark:text-white truncate">{p.name}</p>
                            <p className="text-[10px] text-gray-500 dark:text-gray-400 font-mono">ID: #{p.id}</p>
                          </div>
                        </td>

                        <td className="py-3 px-3">
                          <span className="font-bold text-emerald-600 dark:text-emerald-400 font-mono text-xs">
                            {formatIDR(p.price)}
                          </span>
                          <span className="text-[10px] text-gray-400 block">/ bulan</span>
                        </td>

                        <td className="py-3 px-3">
                          <span className="inline-flex items-center gap-1 font-medium text-gray-700 dark:text-gray-300">
                            <Server className="h-3 w-3 text-gray-400" />
                            {p.router_name || "Semua Router"}
                          </span>
                        </td>

                        <td className="py-3 px-3">
                          <span className="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-bold bg-brand-500 text-white shadow-xs font-mono whitespace-nowrap">
                            {p.profile_normal || "default"}
                          </span>
                        </td>

                        <td className="py-3 px-3">
                          <span className="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-bold bg-amber-500 text-white shadow-xs font-mono whitespace-nowrap">
                            {p.profile_isolir || "isolir"}
                          </span>
                        </td>

                        <td className="py-3 px-3">
                          <Link
                            href={`/admin/billing/customers?search=${encodeURIComponent(p.name)}`}
                            className={cn(
                              "inline-flex items-center gap-1 text-xs font-bold rounded-lg px-2.5 py-1 transition shadow-xs text-white",
                              hasUsers ? "bg-emerald-500 hover:bg-emerald-600" : "bg-gray-500 hover:bg-gray-600"
                            )}
                          >
                            <Users className="h-3.5 w-3.5" />
                            <span>{p.customers_count || 0} Klien</span>
                          </Link>
                        </td>

                        <td className="py-3 px-3 text-center">
                          <button
                            type="button"
                            role="switch"
                            aria-checked={isAutoIsolirActive}
                            onClick={(e) => handleToggleAutoIsolir(e, p)}
                            className={cn(
                              "relative inline-flex h-5 w-9 shrink-0 cursor-pointer items-center rounded-full border transition-colors duration-200 ease-in-out focus:outline-none",
                              isAutoIsolirActive
                                ? "bg-brand-500 border-brand-500"
                                : "bg-gray-200 border-gray-300 dark:bg-gray-700 dark:border-gray-600"
                            )}
                            title={isAutoIsolirActive ? "Auto Isolir Aktif" : "Auto Isolir Nonaktif"}
                          >
                            <span
                              className={cn(
                                "pointer-events-none inline-block h-3.5 w-3.5 rounded-full bg-white ring-0 transition duration-200 ease-in-out shadow-xs",
                                isAutoIsolirActive ? "translate-x-4" : "translate-x-0.5"
                              )}
                            />
                          </button>
                        </td>

                        <td className="py-3 px-4 text-center whitespace-nowrap">
                          <button
                            type="button"
                            onClick={() => setManagingPackage(p)}
                            className="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-brand-500 hover:bg-brand-600 active:scale-95 text-white shadow-xs transition cursor-pointer"
                            title="Kelola Paket"
                          >
                            <Settings className="h-4 w-4" />
                          </button>
                        </td>
                      </tr>
                    )
                  })}
                </tbody>
              </table>
            </div>
          ) : (
            /* Modern TailAdmin Grid Mode */
            <div className="grid gap-3 sm:gap-4 p-4 sm:p-5 grid-cols-1 md:grid-cols-2 xl:grid-cols-3">
              {filteredPackages.map((p) => {
                const hasUsers = (p.customers_count || 0) > 0

                return (
                  <div
                    key={p.id}
                    className="flex flex-col justify-between rounded-2xl border border-gray-200 bg-white p-4 shadow-xs transition-all dark:border-gray-800 dark:bg-white/[0.03] hover:border-brand-500/50 space-y-3"
                  >
                    <div className="flex items-start justify-between gap-3">
                      <div className="min-w-0 flex-1">
                        <h4 className="font-bold text-sm text-gray-900 dark:text-white truncate">
                          {p.name}
                        </h4>
                        <div className="mt-1 flex items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400 font-medium truncate">
                          <span className={cn("flex items-center gap-1 font-bold", hasUsers ? "text-emerald-600 dark:text-emerald-400" : "text-gray-500")}>
                            {p.customers_count || 0} Klien
                          </span>
                          <span>•</span>
                          <span className="truncate">{p.router_name || "Semua Router"}</span>
                        </div>
                      </div>

                      <button
                        type="button"
                        onClick={() => setManagingPackage(p)}
                        className="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-brand-500 hover:bg-brand-600 active:scale-95 text-white shadow-xs transition cursor-pointer shrink-0"
                        title="Kelola Paket"
                      >
                        <Settings className="h-4 w-4" />
                      </button>
                    </div>

                    {/* Sunken Summary Box */}
                    <div className="grid grid-cols-2 gap-2 rounded-xl border border-gray-100 bg-gray-50/80 dark:border-gray-800 dark:bg-gray-900/50 p-2.5 text-xs text-gray-600 dark:text-gray-400">
                      <div className="flex flex-col justify-center min-w-0">
                        <span className="text-[10px] text-gray-400 block mb-0.5">Tarif Bulanan</span>
                        <div className="font-bold text-emerald-600 dark:text-emerald-400 text-xs truncate">
                          {formatIDR(p.price)} <span className="text-[10px] text-gray-400 font-normal">/ bln</span>
                        </div>
                      </div>
                      <div className="text-right flex flex-col justify-center min-w-0">
                        <span className="text-[10px] text-gray-400 block mb-0.5">Profil MikroTik</span>
                        <span className="inline-flex items-center justify-end px-2 py-0.5 rounded-md text-[10px] font-bold bg-brand-500 text-white shadow-xs font-mono truncate self-end whitespace-nowrap">
                          {p.profile_normal || "default"}
                        </span>
                      </div>
                    </div>
                  </div>
                )
              })}
            </div>
          )}
        </div>
      </div>

      {/* ── INTERACTIVE KELOLA PAKET MODAL 1:1 SUPERADMIN ── */}
      {managingPackage && (
        <div
          className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-xs animate-in fade-in"
          onClick={() => setManagingPackage(null)}
        >
          <div
            className="w-full max-w-md rounded-2xl border border-gray-200 bg-white p-5 sm:p-6 shadow-2xl dark:border-gray-800 dark:bg-gray-900 space-y-4 max-h-[92vh] overflow-y-auto"
            onClick={(e) => e.stopPropagation()}
          >
            {/* Modal Header */}
            <div className="flex items-start justify-between border-b border-gray-100 pb-3 dark:border-gray-800">
              <div className="flex items-center gap-3">
                <div className="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-brand-50 dark:bg-brand-500/10 text-brand-500 font-bold">
                  <Boxes className="h-5 w-5" />
                </div>
                <div>
                  <h3 className="text-base font-bold text-gray-900 dark:text-white line-clamp-1">
                    {managingPackage.name}
                  </h3>
                  <div className="flex items-center gap-2 mt-0.5">
                    <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold bg-emerald-500 text-white shadow-xs whitespace-nowrap">
                      {managingPackage.is_active ? "Aktif" : "Nonaktif"}
                    </span>
                    <span className="text-[11px] text-gray-500 dark:text-gray-400">
                      {managingPackage.router_name || "Semua Router"}
                    </span>
                  </div>
                </div>
              </div>
              <button
                type="button"
                onClick={() => setManagingPackage(null)}
                className="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 transition cursor-pointer"
              >
                <X className="h-5 w-5" />
              </button>
            </div>

            {/* Price Banner */}
            <div className="rounded-xl border border-gray-100 bg-gray-50/80 dark:border-gray-800 dark:bg-gray-900/50 p-3.5 flex items-center justify-between">
              <div>
                <span className="text-[11px] text-gray-500 dark:text-gray-400 font-medium">Tarif Langganan Pokok</span>
                <div className="text-xl font-bold text-emerald-600 dark:text-emerald-400">
                  {formatIDR(managingPackage.price)} <span className="text-xs text-gray-400 font-normal">/ bulan</span>
                </div>
              </div>
              <Link
                href={`/admin/billing/customers?search=${encodeURIComponent(managingPackage.name)}`}
                className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-brand-500 hover:bg-brand-600 text-white text-xs font-bold shadow-xs transition whitespace-nowrap"
              >
                <Users className="h-3.5 w-3.5" />
                <span>{managingPackage.customers_count || 0} Klien</span>
              </Link>
            </div>

            {/* Details List */}
            <div className="rounded-xl border border-gray-100 bg-gray-50/80 dark:border-gray-800 dark:bg-gray-900/50 p-3.5 space-y-2.5 text-xs text-gray-600 dark:text-gray-300">
              <div className="flex items-center justify-between py-1 border-b border-gray-200/60 dark:border-gray-800">
                <span className="text-gray-500 flex items-center gap-1.5">
                  <Layers className="h-3.5 w-3.5 text-gray-400" /> Profil Normal
                </span>
                <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold bg-brand-500 text-white shadow-xs font-mono whitespace-nowrap">
                  {managingPackage.profile_normal || "default"}
                </span>
              </div>

              <div className="flex items-center justify-between py-1 border-b border-gray-200/60 dark:border-gray-800">
                <span className="text-gray-500 flex items-center gap-1.5">
                  <ShieldAlert className="h-3.5 w-3.5 text-gray-400" /> Profil Isolir
                </span>
                <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold bg-amber-500 text-white shadow-xs font-mono whitespace-nowrap">
                  {managingPackage.profile_isolir || "ISOLIR"}
                </span>
              </div>

              <div className="flex items-center justify-between py-1 border-b border-gray-200/60 dark:border-gray-800">
                <span className="text-gray-500 flex items-center gap-1.5">
                  <ShieldAlert className="h-3.5 w-3.5 text-gray-400" /> Address List Isolir
                </span>
                <span className="font-mono font-semibold text-gray-800 dark:text-gray-200">
                  {managingPackage.isolir_address_list || "ISOLIR_LIST"}
                </span>
              </div>

              <div className="flex items-center justify-between py-1 border-b border-gray-200/60 dark:border-gray-800">
                <span className="text-gray-500 flex items-center gap-1.5">
                  <Activity className="h-3.5 w-3.5 text-gray-400" /> Isolir Otomatis
                </span>
                <span className={cn(
                  "inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold text-white shadow-xs",
                  managingPackage.auto_isolir ? "bg-emerald-500" : "bg-gray-500"
                )}>
                  {managingPackage.auto_isolir ? "Aktif" : "Nonaktif"}
                </span>
              </div>
            </div>

            {/* Modal Actions (Solid Colors & Icon Only) */}
            <div className="flex items-center justify-between gap-2 pt-2 border-t border-gray-100 dark:border-gray-800">
              <button
                type="button"
                onClick={() => {
                  const p = managingPackage
                  setManagingPackage(null)
                  setDeleteTarget(p)
                  setDeleteFromMikrotik(true)
                }}
                className="flex h-10 w-10 items-center justify-center rounded-xl bg-rose-600 hover:bg-rose-700 text-white shadow-xs transition cursor-pointer shrink-0"
                title="Hapus Paket"
              >
                <Trash2 className="h-4 w-4" />
              </button>

              <button
                type="button"
                onClick={() => {
                  const p = managingPackage
                  setManagingPackage(null)
                  openEdit(p)
                }}
                className="flex h-10 w-10 items-center justify-center rounded-xl bg-brand-500 hover:bg-brand-600 active:scale-95 text-white shadow-xs transition cursor-pointer shrink-0"
                title="Edit Paket"
              >
                <Pencil className="h-4 w-4" />
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Create / Edit Sheet */}
      <CreateSheet
        open={sheetOpen}
        title={editing ? "Edit Paket Layanan" : "Tambah Paket Layanan"}
        description="Atur nama paket, harga langganan, limit kecepatan / profil MikroTik, dan isolir otomatis."
        onClose={() => setSheetOpen(false)}
      >
        <form onSubmit={submit} className="space-y-4 pt-1 max-h-[92vh] overflow-y-auto pr-1">
          {/* Method / Connection Type */}
          <div className="space-y-1.5">
            <label className="text-xs font-semibold text-gray-700 dark:text-gray-300 block">Metode Layanan</label>
            <div className="grid grid-cols-3 gap-2">
              {[
                { id: "arp", label: "ARP / Static IP", sub: "Simple Queue", icon: Activity },
                { id: "pppoe", label: "PPPoE", sub: "PPP Profile", icon: Wifi },
                { id: "hotspot", label: "Hotspot", sub: "User Profile", icon: Radio },
              ].map((t) => {
                const Icon = t.icon
                const active = packageType === t.id
                return (
                  <button
                    key={t.id}
                    type="button"
                    onClick={() => {
                      setPackageType(t.id as any)
                      if (t.id === "arp" && (!form.data.profile_normal || !form.data.profile_normal.includes("/"))) {
                        form.setData("profile_normal", "2M/2M")
                        if (form.data.profile_isolir === "isolir") form.setData("profile_isolir", "512k/512k")
                      }
                      if (t.id === "pppoe" && form.data.profile_normal.includes("/")) {
                        form.setData("profile_normal", liveProfiles[0]?.name || "default")
                        if (form.data.profile_isolir === "512k/512k") form.setData("profile_isolir", "isolir")
                      }
                    }}
                    className={cn(
                      "flex flex-col items-center justify-center p-2.5 rounded-xl border text-center transition-all cursor-pointer select-none",
                      active
                        ? "border-brand-500 bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-400 shadow-xs font-bold"
                        : "border-gray-200 bg-white text-gray-600 hover:border-gray-300 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-400"
                    )}
                  >
                    <Icon className={cn("h-4 w-4 mb-1", active ? "text-brand-500" : "text-gray-400")} />
                    <span className="text-xs font-bold leading-tight">{t.label}</span>
                    <span className="text-[10px] text-gray-500 dark:text-gray-400 leading-tight mt-0.5">{t.sub}</span>
                  </button>
                )
              })}
            </div>
          </div>

          {/* Package Name & Price */}
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div className="space-y-1.5">
              <label className="text-xs font-semibold text-gray-700 dark:text-gray-300 block">
                Nama Paket <span className="text-rose-500">*</span>
              </label>
              <input
                type="text"
                value={form.data.name}
                onChange={(e) => form.setData("name", e.target.value)}
                placeholder="cth: Basic 2M atau Paket 10 Mbps"
                className="h-10 w-full rounded-xl border border-gray-200 bg-white px-3 text-xs text-gray-800 placeholder:text-gray-400 focus:border-brand-500 focus:outline-none dark:border-gray-800 dark:bg-gray-900 dark:text-gray-200 dark:placeholder:text-gray-500"
                required
              />
              {form.errors.name && <p className="text-xs text-rose-500 mt-1">{form.errors.name}</p>}
            </div>

            <div className="space-y-1.5">
              <label className="text-xs font-semibold text-gray-700 dark:text-gray-300 block">
                Harga Bulanan (Rp) <span className="text-rose-500">*</span>
              </label>
              <PriceInput
                value={String(form.data.price)}
                onChange={(v) => form.setData("price", v)}
                placeholder="150000"
                className="h-10 w-full rounded-xl border border-gray-200 bg-white px-3 text-xs text-gray-800 placeholder:text-gray-400 focus:border-brand-500 focus:outline-none dark:border-gray-800 dark:bg-gray-900 dark:text-gray-200"
              />
              {form.errors.price && <p className="text-xs text-rose-500 mt-1">{form.errors.price}</p>}
            </div>
          </div>

          {/* Router Selection */}
          <div className="space-y-1.5">
            <label className="text-xs font-semibold text-gray-700 dark:text-gray-300 block">
              Router MikroTik <span className="text-rose-500">*</span>
            </label>
            <select
              value={form.data.router_id}
              onChange={(e) => form.setData("router_id", e.target.value)}
              className="h-10 w-full rounded-xl border border-gray-200 bg-white px-3 text-xs text-gray-800 focus:border-brand-500 focus:outline-none dark:border-gray-800 dark:bg-gray-900 dark:text-gray-200"
            >
              <option value="">Semua Router (Global)</option>
              {routers.map((r) => (
                <option key={r.id} value={String(r.id)}>
                  {r.name}
                </option>
              ))}
            </select>
          </div>

          {/* Controls based on Package Type */}
          {packageType === "arp" ? (
            <div className="rounded-xl border border-sky-200 bg-sky-50/50 p-3.5 space-y-3 dark:border-sky-500/20 dark:bg-sky-950/20">
              <div className="flex items-center gap-2">
                <Activity className="h-4 w-4 text-sky-600 dark:text-sky-400" />
                <span className="text-xs font-bold text-sky-900 dark:text-sky-300">Pengaturan Simple Queue / Bandwidth ARP</span>
              </div>

              <div className="space-y-1.5">
                <div className="flex items-center justify-between">
                  <label className="text-xs font-semibold text-gray-700 dark:text-gray-300 block">
                    Limit Kecepatan (Upload / Download) <span className="text-rose-500">*</span>
                  </label>
                  <span className="text-[10px] text-sky-600 dark:text-sky-400 font-mono">Format: [Upload]/[Download]</span>
                </div>
                <input
                  type="text"
                  value={form.data.profile_normal}
                  onChange={(e) => form.setData("profile_normal", e.target.value)}
                  placeholder="cth: 2M/2M, 10M/10M, 512k/512k"
                  className="h-10 w-full rounded-xl border border-gray-200 bg-white px-3 text-xs font-mono font-bold text-sky-600 placeholder:text-gray-400 focus:border-brand-500 focus:outline-none dark:border-gray-800 dark:bg-gray-900 dark:text-sky-400"
                  required
                />
                {form.errors.profile_normal && <p className="text-xs text-rose-500 mt-1">{form.errors.profile_normal}</p>}

                <div className="flex flex-wrap gap-1.5 pt-1">
                  {["2M/2M", "3M/3M", "5M/5M", "10M/10M", "15M/15M", "20M/20M", "30M/30M", "50M/50M", "100M/100M"].map((spd) => (
                    <button
                      key={spd}
                      type="button"
                      onClick={() => {
                        form.setData("profile_normal", spd)
                        if (!form.data.name || form.data.name.startsWith("Paket ") || form.data.name.includes("M/")) {
                          form.setData("name", `Paket ${spd.split("/")[1] || spd}`)
                        }
                      }}
                      className={cn(
                        "px-2.5 py-1 rounded-lg text-xs font-bold font-mono transition border cursor-pointer",
                        form.data.profile_normal === spd
                          ? "bg-brand-500 text-white border-brand-500 shadow-xs"
                          : "bg-white text-gray-700 border-gray-200 hover:border-brand-500 dark:bg-gray-900 dark:text-gray-300 dark:border-gray-800"
                      )}
                    >
                      {spd}
                    </button>
                  ))}
                </div>
              </div>

              <div className="space-y-1.5 pt-1 border-t border-sky-100 dark:border-sky-500/20">
                <label className="text-xs font-semibold text-gray-700 dark:text-gray-300 block">
                  Limit Kecepatan Saat Isolir <span className="text-rose-500">*</span>
                </label>
                <input
                  type="text"
                  value={form.data.profile_isolir}
                  onChange={(e) => form.setData("profile_isolir", e.target.value)}
                  placeholder="cth: 512k/512k, 256k/256k, atau isolir"
                  className="h-9 w-full rounded-xl border border-gray-200 bg-white px-3 text-xs font-mono text-gray-800 focus:border-brand-500 focus:outline-none dark:border-gray-800 dark:bg-gray-900 dark:text-gray-200"
                  required
                />
              </div>

              <div className="space-y-1.5 pt-1 border-t border-sky-100 dark:border-sky-500/20">
                <label className="text-xs font-semibold text-gray-700 dark:text-gray-300 block">
                  Address-List Isolir (Firewall MikroTik)
                </label>
                <input
                  type="text"
                  value={form.data.isolir_address_list || ""}
                  onChange={(e) => form.setData("isolir_address_list", e.target.value)}
                  placeholder="ISOLIR_LIST"
                  className="h-9 w-full rounded-xl border border-gray-200 bg-white px-3 text-xs font-mono text-gray-800 focus:border-brand-500 focus:outline-none dark:border-gray-800 dark:bg-gray-900 dark:text-gray-200"
                />
              </div>
            </div>
          ) : (
            <div className="rounded-xl border border-indigo-200 bg-indigo-50/50 p-3.5 space-y-3 dark:border-indigo-500/20 dark:bg-indigo-950/20">
              <div className="flex items-center gap-2">
                <Wifi className="h-4 w-4 text-indigo-600 dark:text-indigo-400" />
                <span className="text-xs font-bold text-indigo-900 dark:text-indigo-300">
                  {packageType === "pppoe" ? "Pengaturan Profil PPPoE Router" : "Pengaturan Profil Hotspot Router"}
                </span>
              </div>

              <div className="space-y-1.5">
                <label className="text-xs font-semibold text-gray-700 dark:text-gray-300 block">
                  {loadingProfiles ? "Profil Normal (Memuat...)" : "Profil MikroTik Normal"} <span className="text-rose-500">*</span>
                </label>
                <div className="flex gap-2">
                  <input
                    type="text"
                    value={form.data.profile_normal}
                    onChange={(e) => form.setData("profile_normal", e.target.value)}
                    placeholder="Pilih atau ketik profil..."
                    className="h-10 flex-1 rounded-xl border border-gray-200 bg-white px-3 text-xs text-gray-800 focus:border-brand-500 focus:outline-none dark:border-gray-800 dark:bg-gray-900 dark:text-gray-200"
                    required
                  />
                  {liveProfiles.length > 0 && (
                    <select
                      value=""
                      onChange={(e) => {
                        if (e.target.value) {
                          form.setData("profile_normal", e.target.value)
                          if (!form.data.name) form.setData("name", `Paket ${e.target.value}`)
                        }
                      }}
                      className="h-10 w-36 rounded-xl border border-gray-200 bg-white px-2 text-xs text-gray-700 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300"
                    >
                      <option value="">Pilih Profil...</option>
                      {liveProfiles.map((p) => (
                        <option key={p.name} value={p.name}>
                          {p.name} {p.rate_limit ? `(${p.rate_limit})` : ""}
                        </option>
                      ))}
                    </select>
                  )}
                </div>
                {form.errors.profile_normal && <p className="text-xs text-rose-500 mt-1">{form.errors.profile_normal}</p>}
              </div>

              <div className="space-y-1.5 pt-1 border-t border-indigo-100 dark:border-indigo-500/20">
                <label className="text-xs font-semibold text-gray-700 dark:text-gray-300 block">
                  Profil MikroTik Isolir <span className="text-rose-500">*</span>
                </label>
                <input
                  type="text"
                  value={form.data.profile_isolir}
                  onChange={(e) => form.setData("profile_isolir", e.target.value)}
                  placeholder="cth: isolir atau expired"
                  className="h-9 w-full rounded-xl border border-gray-200 bg-white px-3 text-xs text-gray-800 focus:border-brand-500 focus:outline-none dark:border-gray-800 dark:bg-gray-900 dark:text-gray-200"
                  required
                />
              </div>
            </div>
          )}

          {/* Auto Isolir Toggle */}
          <div className="flex items-center justify-between rounded-xl border border-gray-200 bg-gray-50/50 p-3 dark:border-gray-800 dark:bg-gray-900/50">
            <div>
              <span className="text-xs font-bold text-gray-800 dark:text-white block">Isolir Otomatis Pelanggan</span>
              <span className="text-[11px] text-gray-500 dark:text-gray-400">
                Otomatis ubah profil/speed ke mode isolir saat invoice jatuh tempo
              </span>
            </div>
            <button
              type="button"
              role="switch"
              aria-checked={form.data.auto_isolir}
              onClick={() => form.setData("auto_isolir", !form.data.auto_isolir)}
              className={cn(
                "relative inline-flex h-5 w-9 shrink-0 cursor-pointer items-center rounded-full border transition-colors duration-200 ease-in-out focus:outline-none",
                form.data.auto_isolir
                  ? "bg-brand-500 border-brand-500"
                  : "bg-gray-200 border-gray-300 dark:bg-gray-700 dark:border-gray-600"
              )}
            >
              <span
                className={cn(
                  "pointer-events-none inline-block h-3.5 w-3.5 rounded-full bg-white ring-0 transition duration-200 ease-in-out shadow-xs",
                  form.data.auto_isolir ? "translate-x-4" : "translate-x-0.5"
                )}
              />
            </button>
          </div>

          <div className="pt-2 border-t border-gray-200 dark:border-gray-800 flex items-center justify-end gap-2">
            <button
              type="button"
              onClick={() => setSheetOpen(false)}
              className="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:hover:bg-gray-800 text-xs font-semibold text-gray-700 dark:text-gray-200 transition shadow-2xs cursor-pointer"
            >
              Batal
            </button>
            <button
              type="submit"
              disabled={form.processing || !form.data.name || !form.data.price}
              className="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-brand-500 hover:bg-brand-600 active:scale-95 text-xs font-bold text-white shadow-xs transition cursor-pointer disabled:opacity-50"
            >
              {form.processing ? "Menyimpan..." : editing ? "Simpan Perubahan" : "Simpan Paket"}
            </button>
          </div>
        </form>
      </CreateSheet>

      {/* Sync Package Dialog */}
      <SyncPackageDialog
        open={syncOpen}
        onClose={() => setSyncOpen(false)}
        routers={routers}
      />

      {/* Info Modal */}
      <SyncInfoModal
        open={infoModalOpen}
        onClose={() => setInfoModalOpen(false)}
      />

      {/* MikroTik Sync Overlay */}
      <SyncOverlay
        show={Boolean(syncInfo?.show)}
        title={syncInfo?.title}
        statusMessage={syncInfo?.statusMessage}
      />

      {/* Delete Confirmation Modal */}
      {deleteTarget && (
        <div className="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-0 sm:p-4">
          <div className="fixed inset-0 bg-black/60 backdrop-blur-xs" onClick={() => !deleting && setDeleteTarget(null)} />
          <div className="relative flex w-full max-w-md flex-col overflow-hidden rounded-t-3xl sm:rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 shadow-xl animate-in slide-in-from-bottom-4 duration-200">
            <div className="flex items-center justify-between border-b border-gray-200 dark:border-gray-800 px-5 py-4 shrink-0">
              <div className="flex items-center gap-2.5">
                <div className="flex h-9 w-9 items-center justify-center rounded-xl bg-rose-50 text-rose-600 dark:bg-rose-500/10 dark:text-rose-400">
                  <Trash2 className="h-4.5 w-4.5" />
                </div>
                <div>
                  <h3 className="font-bold text-gray-800 dark:text-white">Hapus Paket Langganan</h3>
                  <p className="text-[11px] text-gray-500 dark:text-gray-400">{deleteTarget.name}</p>
                </div>
              </div>
              <button
                type="button"
                onClick={() => !deleting && setDeleteTarget(null)}
                className="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-gray-800 dark:hover:text-white"
              >
                <X className="h-4 w-4" />
              </button>
            </div>

            <div className="p-5 space-y-4 max-h-[92vh] overflow-y-auto">
              <p className="text-xs text-gray-600 dark:text-gray-300 leading-relaxed">
                Apakah Anda yakin ingin menghapus paket <strong className="text-gray-900 dark:text-white">{deleteTarget.name}</strong> dari sistem?
              </p>

              <div className="rounded-xl border border-gray-200 bg-gray-50/75 p-3 text-left space-y-2 text-xs text-gray-700 dark:border-gray-800 dark:bg-gray-800/50 dark:text-gray-300">
                <div className="flex justify-between items-center">
                  <span className="text-gray-500">Tarif Bulanan</span>
                  <span className="text-emerald-600 dark:text-emerald-400 font-bold font-mono">{formatIDR(deleteTarget.price)}</span>
                </div>
                <div className="flex justify-between items-center">
                  <span className="text-gray-500">Profil PPP Normal</span>
                  <span className="font-mono">{deleteTarget.profile_normal || "default"}</span>
                </div>
                <div className="flex justify-between items-center">
                  <span className="text-gray-500">Pelanggan Terdaftar</span>
                  <span className={cn("font-bold", (deleteTarget.customers_count || 0) > 0 ? "text-amber-600" : "text-gray-500")}>
                    {deleteTarget.customers_count || 0} Pelanggan
                  </span>
                </div>
              </div>

              {(deleteTarget.customers_count || 0) > 0 && (
                <div className="rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-300 flex items-start gap-2.5">
                  <AlertTriangle className="h-4 w-4 shrink-0 mt-0.5 text-amber-600 dark:text-amber-400" />
                  <div>
                    <span className="font-bold block">Paket Masih Digunakan</span>
                    <span className="text-[11px] leading-relaxed block mt-0.5">
                      Paket ini masih digunakan oleh {deleteTarget.customers_count} pelanggan. Anda harus memindahkan pelanggan ke paket lain terlebih dahulu.
                    </span>
                  </div>
                </div>
              )}

              <label className="flex items-start gap-3 cursor-pointer select-none rounded-xl border border-gray-200 p-3 bg-gray-50 dark:border-gray-800 dark:bg-gray-800/30">
                <input
                  type="checkbox"
                  checked={deleteFromMikrotik}
                  onChange={(e) => setDeleteFromMikrotik(e.target.checked)}
                  disabled={(deleteTarget.customers_count || 0) > 0}
                  className="mt-0.5 h-4 w-4 rounded border-gray-300 text-brand-500 focus:ring-brand-500"
                />
                <div className="text-xs">
                  <span className="font-bold text-gray-800 dark:text-white block">Hapus juga Profile dari Router MikroTik</span>
                  <span className="text-[11px] text-gray-500 dark:text-gray-400 block mt-0.5">
                    Profile PPPoE <code className="text-brand-500">{deleteTarget.profile_normal || deleteTarget.name}</code> akan dihapus dari router MikroTik.
                  </span>
                </div>
              </label>
            </div>

            <div className="border-t border-gray-200 dark:border-gray-800 p-4 shrink-0 flex items-center gap-2">
              <button
                type="button"
                className="flex-1 rounded-xl h-10 text-xs font-bold border border-gray-200 bg-white text-gray-700 hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800 transition cursor-pointer"
                onClick={() => setDeleteTarget(null)}
                disabled={deleting}
              >
                Batal
              </button>
              <button
                type="button"
                className="flex-1 rounded-xl h-10 text-xs font-bold bg-rose-500 hover:bg-rose-600 text-white transition shadow-xs flex items-center justify-center gap-1.5 disabled:opacity-50 cursor-pointer"
                disabled={deleting || (deleteTarget.customers_count || 0) > 0}
                onClick={() => {
                  setDeleting(true)
                  setSyncInfo({
                    show: true,
                    title: "Hapus Paket",
                    statusMessage: `Menghapus paket ${deleteTarget.name}...`,
                  })
                  router.post(
                    `/admin/billing/packages/delete/${deleteTarget.id}`,
                    {
                      delete_router: deleteFromMikrotik,
                      delete_mikrotik: deleteFromMikrotik,
                    },
                    {
                      preserveScroll: true,
                      onFinish: () => {
                        setDeleting(false)
                        setSyncInfo(null)
                        setDeleteTarget(null)
                      },
                    }
                  )
                }}
              >
                {deleting ? "Menghapus..." : "Ya, Hapus Paket"}
              </button>
            </div>
          </div>
        </div>
      )}
    </AppLayout>
  )
}
