import { AppLayout } from "@/components/layout/app-layout"
import Chart from "react-apexcharts"
import type { ApexOptions } from "apexcharts"
import {
  Package,
  Plus,
  Pencil,
  Search,
  Boxes,
  Wallet,
  AlertTriangle,
  X,
  Tag,
  Trash2,
  PieChart,
  BarChart3,
} from "lucide-react"
import { EmptyState } from "@/components/ui/empty-state"
import { PageProps } from "@/types"
import { formatIDR, cn } from "@/lib/utils"
import { adminSidebarItems, adminNavItems, adminBrand } from "@/lib/admin-nav"
import { useForm, router } from "@inertiajs/react"
import { CreateSheet, type FieldDef } from "@/components/ui/create-sheet"
import { useState, useMemo, useDeferredValue } from "react"
import { MetricCard } from "@/components/tailadmin/MetricCard"
import Modal from "@/components/tailadmin/Modal"
import { ViewModeSwitcher, type ViewMode } from "@/components/tailadmin/ui/view-mode-switcher"

interface Item {
  id: number
  name: string
  sku: string | null
  category: string | null
  category_id: number | null
  unit: string
  quantity: number
  price: number
  status: string
}

export default function InventoryPage({
  items = [],
  categories = [],
  create = false,
}: PageProps<{
  items: Item[]
  categories: { id: number; name: string }[]
  create: boolean
  companyName?: string
  tenantName?: string
}>) {
  const [sheetOpen, setSheetOpen] = useState(create ?? false)
  const [editing, setEditing] = useState<Item | null>(null)
  const [managingItem, setManagingItem] = useState<Item | null>(null)
  const [search, setSearch] = useState("")
  const [categoryFilter, setCategoryFilter] = useState<string>("all")
  const [stockStatusFilter, setStockStatusFilter] = useState<string>("all")
  const [viewMode, setViewMode] = useState<ViewMode>("table")

  const form = useForm({
    name: "",
    sku: "",
    category_id: "",
    quantity: "0",
    unit: "unit",
    price: "",
  })

  const totalValue = useMemo(() => {
    return items.reduce((acc, i) => acc + ((i.quantity || 0) * (i.price || 0)), 0)
  }, [items])

  const totalUnits = useMemo(() => {
    return items.reduce((acc, i) => acc + (Number(i.quantity) || 0), 0)
  }, [items])

  const lowStockCount = useMemo(() => {
    return items.filter((i) => (i.quantity || 0) <= 3).length
  }, [items])

  const openAdd = () => {
    setEditing(null)
    form.reset()
    setSheetOpen(true)
  }

  const openEdit = (i: Item) => {
    setManagingItem(null)
    setEditing(i)
    form.setData({
      name: i.name,
      sku: i.sku ?? "",
      category_id: i.category_id ? String(i.category_id) : "",
      quantity: String(i.quantity),
      unit: i.unit ?? "unit",
      price: String(i.price),
    })
    setSheetOpen(true)
  }

  const handleDelete = (id: number) => {
    if (confirm("Hapus barang dari inventaris gudang ini?")) {
      router.post(`/admin/inventory/item/delete/${id}`, {}, {
        preserveScroll: true,
        onSuccess: () => setManagingItem(null),
      })
    }
  }

  const submit = (e: React.FormEvent) => {
    e.preventDefault()
    if (editing) {
      form.post(`/admin/inventory/item/edit/${editing.id}`, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
          setSheetOpen(false)
        },
      })
    } else {
      form.post("/admin/inventory/item/add", {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
          setSheetOpen(false)
        },
      })
    }
  }

  const fields: FieldDef[] = [
    { name: "name", label: "Nama Barang / Perangkat", required: true, placeholder: "cth: Modem GPON ZTE F660" },
    { name: "sku", label: "Kode SKU / Part No", placeholder: "GPON-001" },
    {
      name: "category_id",
      label: "Kategori Barang",
      type: "select",
      options: categories.map((c) => ({ value: String(c.id), label: c.name })),
    },
    { name: "quantity", label: "Jumlah Stok Fisik", type: "number", required: true },
    { name: "unit", label: "Satuan", required: true, placeholder: "unit / pcs / roll / meter" },
    { name: "price", label: "Harga Beli / Satuan (Rp)", type: "price", required: true },
  ]

  const deferredSearch = useDeferredValue(search)

  const filtered = useMemo(() => {
    return items.filter((i) => {
      if (categoryFilter !== "all" && String(i.category_id) !== categoryFilter && i.category !== categoryFilter) {
        return false
      }
      const isLow = (i.quantity || 0) <= 3
      if (stockStatusFilter === "low" && !isLow) return false
      if (stockStatusFilter === "safe" && isLow) return false

      if (deferredSearch.trim()) {
        const q = deferredSearch.toLowerCase().trim()
        const matchName = i.name.toLowerCase().includes(q)
        const matchSku = (i.sku ?? "").toLowerCase().includes(q)
        const matchCat = (i.category ?? "").toLowerCase().includes(q)
        if (!matchName && !matchSku && !matchCat) return false
      }
      return true
    })
  }, [items, deferredSearch, categoryFilter, stockStatusFilter])

  // ── INVENTORY VALUATION DONUT & QUANTITY BAR CHARTS ──
  const categoryDonutData = useMemo(() => {
    const catMap: Record<string, number> = {}
    items.forEach((item) => {
      const cat = item.category || "Tanpa Kategori"
      const val = (Number(item.quantity) || 0) * (Number(item.price) || 0)
      catMap[cat] = (catMap[cat] || 0) + val
    })
    const entries = Object.entries(catMap).sort((a, b) => b[1] - a[1])
    return {
      labels: entries.length > 0 ? entries.map((e) => e[0]) : ["Belum ada kategori"],
      series: entries.length > 0 ? entries.map((e) => e[1]) : [0],
    }
  }, [items])

  const categoryDonutOptions: ApexOptions = useMemo(() => ({
    chart: { type: "donut", fontFamily: "inherit" },
    labels: categoryDonutData.labels,
    colors: ["#0073C6", "#10B981", "#8B5CF6", "#F59E0B", "#EC4899", "#6366F1"],
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
              label: "Valuasi Total",
              color: "#64748B",
              formatter: () => formatIDR(totalValue),
            },
          },
        },
      },
    },
    tooltip: {
      theme: "light",
      y: { formatter: (val) => formatIDR(val) },
    },
  }), [categoryDonutData, totalValue])

  const categoryUnitBarData = useMemo(() => {
    const catMap: Record<string, number> = {}
    items.forEach((item) => {
      const cat = item.category || "Tanpa Kategori"
      catMap[cat] = (catMap[cat] || 0) + (Number(item.quantity) || 0)
    })
    const entries = Object.entries(catMap).sort((a, b) => b[1] - a[1]).slice(0, 6)
    return {
      categories: entries.length > 0 ? entries.map((e) => e[0]) : ["Belum ada kategori"],
      values: entries.length > 0 ? entries.map((e) => e[1]) : [0],
    }
  }, [items])

  const categoryUnitBarOptions: ApexOptions = useMemo(() => ({
    chart: { type: "bar", toolbar: { show: false }, fontFamily: "inherit" },
    colors: ["#10B981"],
    plotOptions: {
      bar: {
        horizontal: true,
        borderRadius: 6,
        barHeight: "55%",
      },
    },
    dataLabels: {
      enabled: true,
      formatter: (val) => `${val} Unit`,
      style: { fontSize: "10px", fontWeight: "bold" },
    },
    xaxis: {
      categories: categoryUnitBarData.categories,
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
      y: { formatter: (val) => `${val} Unit Fisik` },
    },
  }), [categoryUnitBarData])

  return (
    <AppLayout
      title="Inventaris Gudang"
      brand={adminBrand}
      sidebarItems={adminSidebarItems}
      navItems={adminNavItems}
    >
      <div className="space-y-6">
        {/* ── 4 TOP KPI METRICS ── */}
        <div className="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
          <MetricCard
            title="Valuasi Total Stok"
            value={formatIDR(totalValue)}
            icon={<Wallet className="size-5 text-emerald-500" />}
            iconBgColor="bg-emerald-50 dark:bg-emerald-500/10"
            iconColor="text-emerald-500 dark:text-emerald-400"
            sub={`${items.length} SKU terdaftar`}
            onClick={() => {
              setStockStatusFilter("all")
              setCategoryFilter("all")
            }}
            isActive={stockStatusFilter === "all" && categoryFilter === "all"}
          />

          <MetricCard
            title="Total Unit Fisik"
            value={`${totalUnits} Unit`}
            icon={<Boxes className="size-5 text-blue-500" />}
            iconBgColor="bg-blue-50 dark:bg-blue-500/10"
            iconColor="text-blue-500 dark:text-blue-400"
            sub="Kuantitas akumulatif material"
          />

          <MetricCard
            title="Stok Menipis"
            value={`${lowStockCount} SKU`}
            icon={<AlertTriangle className="size-5 text-rose-500" />}
            iconBgColor="bg-rose-50 dark:bg-rose-500/10"
            iconColor="text-rose-500 dark:text-rose-400"
            sub="Stok <= 3 unit (Perlu Restock)"
            onClick={() => {
              setStockStatusFilter(stockStatusFilter === "low" ? "all" : "low")
            }}
            isActive={stockStatusFilter === "low"}
          />

          <MetricCard
            title="Kategori Barang"
            value={`${categories.length} Kategori`}
            icon={<Package className="size-5 text-purple-500" />}
            iconBgColor="bg-purple-50 dark:bg-purple-500/10"
            iconColor="text-purple-500 dark:text-purple-400"
            sub="Klasifikasi jenis material"
          />
        </div>

        {/* ── CHARTS SECTION: VALUATION DONUT & UNITS BAR ── */}
        <div className="grid grid-cols-1 lg:grid-cols-12 gap-4 sm:gap-6">
          {/* Inventory Valuation Donut */}
          <div className="lg:col-span-6 rounded-2xl border border-gray-200 bg-white p-4 sm:p-5 shadow-xs dark:border-gray-800 dark:bg-white/[0.03]">
            <div className="flex items-center justify-between gap-2 mb-3">
              <div className="flex items-center gap-2">
                <div className="flex h-8 w-8 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10">
                  <PieChart className="h-4 w-4" />
                </div>
                <div>
                  <h3 className="text-sm sm:text-base font-bold text-gray-800 dark:text-white/90">
                    Proporsi Valuasi Stok per Kategori
                  </h3>
                  <p className="text-[11px] text-gray-500 dark:text-gray-400">
                    Distribusi aset berdasarkan nilai modal barang
                  </p>
                </div>
              </div>
            </div>
            <div className="h-[220px] w-full flex items-center justify-center">
              <Chart
                options={categoryDonutOptions}
                series={categoryDonutData.series}
                type="donut"
                height="100%"
                width="100%"
              />
            </div>
          </div>

          {/* Unit Quantity Bar Chart */}
          <div className="lg:col-span-6 rounded-2xl border border-gray-200 bg-white p-4 sm:p-5 shadow-xs dark:border-gray-800 dark:bg-white/[0.03]">
            <div className="flex items-center justify-between gap-2 mb-3">
              <div className="flex items-center gap-2">
                <div className="flex h-8 w-8 items-center justify-center rounded-xl bg-blue-50 text-blue-600 dark:bg-blue-500/10">
                  <BarChart3 className="h-4 w-4" />
                </div>
                <div>
                  <h3 className="text-sm sm:text-base font-bold text-gray-800 dark:text-white/90">
                    Kuantitas Stok Fisik per Kategori
                  </h3>
                  <p className="text-[11px] text-gray-500 dark:text-gray-400">
                    Total volume barang tersedia di gudang
                  </p>
                </div>
              </div>
            </div>
            <div className="h-[220px] w-full">
              <Chart
                options={categoryUnitBarOptions}
                series={[{ name: "Jumlah Unit", data: categoryUnitBarData.values }]}
                type="bar"
                height="100%"
                width="100%"
              />
            </div>
          </div>
        </div>

        {/* ── 1-LINE RESPONSIVE INTEGRATED TOOLBAR ── */}
        <div className="rounded-2xl border border-gray-200 bg-white p-3 sm:p-4 shadow-xs dark:border-gray-800 dark:bg-white/[0.03] flex flex-col gap-2.5 sm:gap-3 lg:flex-row lg:items-center lg:justify-between">
          {/* Left side: Search & Filter dropdowns */}
          <div className="flex flex-wrap items-center gap-2 sm:gap-3 flex-1 min-w-0">
            <div className="relative flex-1 min-w-[180px] sm:min-w-[220px] w-full sm:w-auto">
              <Search className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-gray-400" />
              <input
                type="text"
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                placeholder="Cari nama barang, kode SKU, atau kategori..."
                className="h-10 w-full rounded-xl border border-gray-200 bg-gray-50/75 dark:border-gray-800 dark:bg-gray-900/50 pl-9 pr-8 text-xs font-medium text-gray-900 dark:text-white placeholder:text-gray-400 focus:border-brand-500 focus:outline-hidden"
              />
              {search && (
                <button
                  type="button"
                  onClick={() => setSearch("")}
                  className="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-white"
                >
                  <X className="size-3.5" />
                </button>
              )}
            </div>

            {/* Category & Stock Filter */}
            <div className="flex items-center gap-2 w-full sm:w-auto flex-1 sm:flex-none">
              <select
                value={categoryFilter}
                onChange={(e) => setCategoryFilter(e.target.value)}
                className="h-10 rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 px-3 text-xs font-semibold text-gray-700 dark:text-gray-200 cursor-pointer flex-1 sm:flex-none sm:w-auto shrink-0 focus:border-brand-500 focus:outline-hidden"
              >
                <option value="all">Semua Kategori</option>
                {categories.map((c) => (
                  <option key={c.id} value={String(c.id)}>
                    {c.name}
                  </option>
                ))}
              </select>

              <select
                value={stockStatusFilter}
                onChange={(e) => setStockStatusFilter(e.target.value)}
                className="h-10 rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 px-3 text-xs font-semibold text-gray-700 dark:text-gray-200 cursor-pointer flex-1 sm:flex-none sm:w-auto shrink-0 focus:border-brand-500 focus:outline-hidden"
              >
                <option value="all">Semua Stok</option>
                <option value="safe">Stok Aman (&gt; 3)</option>
                <option value="low">Stok Menipis (&le; 3)</option>
              </select>
            </div>
          </div>

          {/* Right side: ViewModeSwitcher + Tambah Item */}
          <div className="flex items-center gap-2 shrink-0 w-full lg:w-auto">
            <ViewModeSwitcher
              value={viewMode}
              onChange={setViewMode}
              storageKey="nodera_inventory_view_mode"
              className="shrink-0"
            />
            <button
              type="button"
              onClick={openAdd}
              className="h-10 px-3.5 sm:px-4 rounded-xl bg-brand-500 hover:bg-brand-600 active:scale-95 text-xs font-bold text-white shadow-xs flex items-center justify-center gap-1.5 flex-1 sm:flex-none cursor-pointer transition"
            >
              <Plus className="size-4" />
              <span>Tambah Item</span>
            </button>
          </div>
        </div>

        {/* ── MASTER CARD CONTAINER ── */}
        <div className="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xs dark:border-gray-800 dark:bg-white/[0.03]">

          {/* ── TABLE / GRID VIEW ── */}
          {filtered.length === 0 ? (
            <div className="p-8 text-center">
              <EmptyState
                icon={<Boxes className="size-8 text-brand-500" />}
                title="Tidak ada barang di inventaris"
                description="Tambahkan modem ONT, kabel FO, adaptor, atau router ke dalam stok gudang."
              />
            </div>
          ) : viewMode === "table" ? (
            /* TABLE MODE */
            <div className="w-full overflow-x-auto custom-scrollbar">
              <table className="w-full text-left text-xs text-gray-600 dark:text-gray-300 min-w-[700px] border-collapse">
                <thead className="border-b border-gray-200 bg-gray-50/75 text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:border-gray-800 dark:bg-gray-900/50 dark:text-gray-400">
                  <tr>
                    <th className="px-4 py-3.5">Nama Barang & SKU</th>
                    <th className="px-4 py-3.5">Kategori</th>
                    <th className="px-4 py-3.5">Stok Fisik</th>
                    <th className="px-4 py-3.5">Harga Beli Satuan</th>
                    <th className="px-4 py-3.5">Valuasi Total</th>
                    <th className="px-4 py-3.5">Status Ketersediaan</th>
                    <th className="px-4 py-3.5 text-right">Aksi</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-gray-100 dark:divide-gray-800">
                  {filtered.map((item) => {
                    const isLow = (item.quantity || 0) <= 3

                    return (
                      <tr key={item.id} className="hover:bg-gray-50/50 dark:hover:bg-white/[0.02] transition">
                        {/* Nama Barang & SKU Column */}
                        <td className="px-4 py-3.5">
                          <div className="flex items-center gap-3">
                            <div className="relative flex size-9 shrink-0 items-center justify-center rounded-xl bg-gray-100 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 font-bold text-xs text-gray-700 dark:text-gray-200">
                              <Boxes className="size-4.5 text-gray-600 dark:text-gray-300" />
                              <span
                                className={cn(
                                  "absolute -bottom-0.5 -right-0.5 size-2.5 rounded-full ring-2 ring-white dark:ring-gray-900",
                                  isLow ? "bg-rose-500" : "bg-emerald-500"
                                )}
                              />
                            </div>
                            <div className="min-w-0">
                              <div className="font-bold text-gray-900 dark:text-white truncate">
                                {item.name}
                              </div>
                              <div className="font-mono text-[11px] text-gray-500 dark:text-gray-400 truncate">
                                SKU: {item.sku || "NO-SKU"}
                              </div>
                            </div>
                          </div>
                        </td>

                        {/* Kategori Column */}
                        <td className="px-4 py-3.5">
                          <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-bold bg-brand-500 text-white shadow-xs whitespace-nowrap">
                            <Tag className="size-3" />
                            {item.category || "Umum"}
                          </span>
                        </td>

                        {/* Stok Fisik Column */}
                        <td className="px-4 py-3.5 font-bold font-mono text-gray-900 dark:text-white">
                          {item.quantity} {item.unit}
                        </td>

                        {/* Harga Satuan Column */}
                        <td className="px-4 py-3.5 font-mono text-gray-700 dark:text-gray-300">
                          {formatIDR(item.price)}
                        </td>

                        {/* Valuasi Total Column */}
                        <td className="px-4 py-3.5 font-mono font-bold text-sm text-emerald-600 dark:text-emerald-400">
                          {formatIDR(item.quantity * item.price)}
                        </td>

                        {/* Status Column */}
                        <td className="px-4 py-3.5">
                          {isLow ? (
                            <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold bg-rose-500 text-white shadow-xs whitespace-nowrap">
                              Menipis
                            </span>
                          ) : (
                            <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold bg-emerald-500 text-white shadow-xs whitespace-nowrap">
                              Stok Aman
                            </span>
                          )}
                        </td>

                        {/* Aksi Column - SINGLE KELOLA BUTTON */}
                        <td className="px-4 py-3.5 text-right">
                          <button
                            type="button"
                            onClick={() => setManagingItem(item)}
                            className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200 font-bold text-xs transition cursor-pointer"
                          >
                            <span>Kelola</span>
                          </button>
                        </td>
                      </tr>
                    )
                  })}
                </tbody>
              </table>
            </div>
          ) : (
            /* GRID / CARD MODE */
            <div className="p-4 sm:p-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
              {filtered.map((item) => {
                const isLow = (item.quantity || 0) <= 3

                return (
                  <div
                    key={item.id}
                    className="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.02] p-4 transition shadow-xs space-y-3"
                  >
                    <div className="flex items-start justify-between gap-2">
                      <div className="flex items-center gap-3 min-w-0 flex-1">
                        <div className="relative flex size-10 shrink-0 items-center justify-center rounded-xl bg-gray-100 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 font-bold text-xs text-gray-700 dark:text-gray-200">
                          <Boxes className="size-5 text-gray-600 dark:text-gray-300" />
                          <span
                            className={cn(
                              "absolute -bottom-0.5 -right-0.5 size-2.5 rounded-full ring-2 ring-white dark:ring-gray-900",
                              isLow ? "bg-rose-500" : "bg-emerald-500"
                            )}
                          />
                        </div>

                        <div className="min-w-0 flex-1">
                          <h4 className="font-bold text-sm text-gray-900 dark:text-white truncate">
                            {item.name}
                          </h4>
                          <div className="mt-0.5 flex items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400 truncate">
                            <span className="font-mono">{item.sku || "NO-SKU"}</span>
                            <span>•</span>
                            <span className="truncate">{item.category || "Umum"}</span>
                          </div>
                        </div>
                      </div>

                      <div className="shrink-0">
                        {isLow ? (
                          <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold bg-rose-500 text-white shadow-xs whitespace-nowrap">
                            {item.quantity} {item.unit}
                          </span>
                        ) : (
                          <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold bg-emerald-500 text-white shadow-xs whitespace-nowrap">
                            {item.quantity} {item.unit}
                          </span>
                        )}
                      </div>
                    </div>

                    {/* Sunken Box */}
                    <div className="rounded-xl border border-gray-100 bg-gray-50/80 p-2.5 grid grid-cols-2 gap-2 text-xs dark:border-gray-800 dark:bg-gray-900/50">
                      <div>
                        <span className="text-[10px] text-gray-500 dark:text-gray-400 block mb-0.5">Harga Beli:</span>
                        <strong className="font-mono text-gray-900 dark:text-white">{formatIDR(item.price)}</strong>
                      </div>
                      <div className="text-right">
                        <span className="text-[10px] text-gray-500 dark:text-gray-400 block mb-0.5">Valuasi:</span>
                        <strong className="font-mono font-bold text-emerald-600 dark:text-emerald-400">{formatIDR(item.quantity * item.price)}</strong>
                      </div>
                    </div>

                    {/* Action Row - SINGLE KELOLA BUTTON */}
                    <div className="pt-2 border-t border-gray-100 dark:border-gray-800 flex items-center justify-between">
                      <span className="text-xs text-gray-500 dark:text-gray-400">
                        Status: <strong className={isLow ? "text-rose-500" : "text-emerald-500"}>{isLow ? "Menipis" : "Aman"}</strong>
                      </span>
                      <button
                        type="button"
                        onClick={() => setManagingItem(item)}
                        className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200 font-bold text-xs transition cursor-pointer"
                      >
                        <span>Kelola</span>
                      </button>
                    </div>
                  </div>
                )
              })}
            </div>
          )}
        </div>
      </div>

      {/* POPUP MODAL KELOLA ITEM INVENTARIS */}
      {managingItem && (
        <Modal
          isOpen={true}
          onClose={() => setManagingItem(null)}
          title="Kelola Barang Inventaris"
          maxWidth="md"
        >
          <div className="p-5 max-h-[92vh] overflow-y-auto space-y-4">
            <div className="flex items-start justify-between gap-3 border-b border-gray-100 dark:border-gray-800 pb-3">
              <div>
                <h4 className="font-bold text-sm text-gray-900 dark:text-white">{managingItem.name}</h4>
                <p className="text-xs text-gray-500 dark:text-gray-400 font-mono mt-0.5">SKU: {managingItem.sku || "NO-SKU"}</p>
              </div>
              <span className={`inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold text-white shadow-xs ${
                (managingItem.quantity || 0) <= 3 ? "bg-rose-500" : "bg-emerald-500"
              }`}>
                {(managingItem.quantity || 0) <= 3 ? "Menipis" : "Stok Aman"}
              </span>
            </div>

            <div className="rounded-xl border border-gray-100 bg-gray-50/80 p-3 dark:border-gray-800 dark:bg-gray-900/50 space-y-2 text-xs">
              <div className="flex items-center justify-between">
                <span className="text-gray-500 dark:text-gray-400">Kategori</span>
                <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-bold bg-brand-500 text-white shadow-xs whitespace-nowrap">
                  {managingItem.category || "Umum"}
                </span>
              </div>
              <div className="flex items-center justify-between">
                <span className="text-gray-500 dark:text-gray-400">Stok Tersedia</span>
                <span className="font-mono font-bold text-gray-900 dark:text-white text-sm">{managingItem.quantity} {managingItem.unit}</span>
              </div>
              <div className="flex items-center justify-between">
                <span className="text-gray-500 dark:text-gray-400">Harga Beli Satuan</span>
                <span className="font-mono font-medium text-gray-900 dark:text-white">{formatIDR(managingItem.price)}</span>
              </div>
              <div className="flex items-center justify-between">
                <span className="text-gray-500 dark:text-gray-400">Total Valuasi Aset</span>
                <span className="font-mono font-bold text-emerald-600 dark:text-emerald-400 text-sm">{formatIDR(managingItem.quantity * managingItem.price)}</span>
              </div>
            </div>

            <div className="pt-2 space-y-2">
              <button
                type="button"
                onClick={() => openEdit(managingItem)}
                className="w-full inline-flex items-center justify-center gap-1.5 px-3.5 py-2 rounded-xl bg-brand-500 hover:bg-brand-600 active:scale-95 text-xs font-bold text-white shadow-xs transition cursor-pointer"
              >
                <Pencil className="size-4" />
                <span>Edit Informasi Barang</span>
              </button>

              <button
                type="button"
                onClick={() => handleDelete(managingItem.id)}
                className="w-full inline-flex items-center justify-center gap-1.5 px-3.5 py-2 rounded-xl bg-rose-500 hover:bg-rose-600 active:scale-95 text-xs font-bold text-white shadow-xs transition cursor-pointer"
              >
                <Trash2 className="size-4" />
                <span>Hapus Barang dari Gudang</span>
              </button>

              <button
                type="button"
                onClick={() => setManagingItem(null)}
                className="w-full inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:hover:bg-gray-800 text-xs font-semibold text-gray-700 dark:text-gray-200 transition shadow-2xs"
              >
                <span>Tutup</span>
              </button>
            </div>
          </div>
        </Modal>
      )}

      <CreateSheet
        open={sheetOpen}
        title={editing ? "Edit Item Gudang" : "Tambah Item Baru"}
        onClose={() => setSheetOpen(false)}
        onSubmit={submit}
        processing={form.processing}
        fields={fields}
        values={form.data}
        setValue={(n, v) => form.setData(n as never, v as never)}
        errors={form.errors}
      />
    </AppLayout>
  )
}