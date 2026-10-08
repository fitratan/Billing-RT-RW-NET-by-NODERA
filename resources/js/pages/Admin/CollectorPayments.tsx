import { AppLayout } from "@/components/layout/app-layout"
import Chart from "react-apexcharts"
import type { ApexOptions } from "apexcharts"
import {
  Wallet,
  Search,
  Users,
  Printer,
  Receipt,
  X,
  Settings,
  ChevronRight,
  BarChart3,
  PieChart,
} from "lucide-react"
import { EmptyState } from "@/components/ui/empty-state"
import { PageProps } from "@/types"
import { formatIDR, formatDate } from "@/lib/utils"
import { adminSidebarItems, adminNavItems, adminBrand } from "@/lib/admin-nav"
import { useState, useMemo, useDeferredValue } from "react"
import { MetricCard } from "@/components/tailadmin/MetricCard"
import { ViewModeSwitcher, type ViewMode } from "@/components/ui/view-mode-switcher"

interface Payment {
  id: number
  invoice_number: string
  customer_name: string | null
  amount: number
  paid_at: string | null
  collector: string | null
}

export default function CollectorPaymentsPage({
  payments = [],
}: PageProps<{
  payments: Payment[]
  companyName?: string
  tenantName?: string
}>) {
  const [search, setSearch] = useState("")
  const [managingPayment, setManagingPayment] = useState<Payment | null>(null)
  const [collectorFilter, setCollectorFilter] = useState<string>("all")
  const [viewMode, setViewMode] = useState<ViewMode>("table")

  const deferredSearch = useDeferredValue(search)

  const uniqueCollectors = useMemo(() => {
    const list = payments.map((p) => p.collector).filter(Boolean) as string[]
    return Array.from(new Set(list))
  }, [payments])

  const filtered = useMemo(() => {
    return payments.filter((p) => {
      if (collectorFilter !== "all" && p.collector !== collectorFilter) return false
      if (!deferredSearch) return true
      const q = deferredSearch.toLowerCase().trim()
      const matchCustomer = (p.customer_name ?? "").toLowerCase().includes(q)
      const matchInvoice = (p.invoice_number ?? "").toLowerCase().includes(q)
      const matchCollector = (p.collector ?? "").toLowerCase().includes(q)
      return matchCustomer || matchInvoice || matchCollector
    })
  }, [payments, deferredSearch, collectorFilter])

  const totalAmount = useMemo(
    () => payments.reduce((sum, p) => sum + (Number(p.amount) || 0), 0),
    [payments]
  )

  // ── COLLECTOR DEPOSIT BAR CHART ──
  const collectorBarData = useMemo(() => {
    const map = new Map<string, number>()
    payments.forEach((p) => {
      const col = p.collector?.trim() || "Kolektor Umum"
      map.set(col, (map.get(col) || 0) + (Number(p.amount) || 0))
    })
    const sorted = Array.from(map.entries()).sort((a, b) => b[1] - a[1]).slice(0, 6)
    return {
      categories: sorted.length > 0 ? sorted.map(([k]) => k) : ["Belum ada data"],
      values: sorted.length > 0 ? sorted.map(([, v]) => v) : [0],
    }
  }, [payments])

  const collectorBarOptions: ApexOptions = useMemo(() => ({
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
      formatter: (val) => formatIDR(Number(val)),
      style: { fontSize: "10px", fontWeight: "bold" },
    },
    xaxis: {
      categories: collectorBarData.categories,
      labels: {
        style: { colors: "#64748B", fontSize: "11px" },
        formatter: (val) => formatIDR(Number(val)),
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
      y: { formatter: (val) => formatIDR(val) },
    },
  }), [collectorBarData])

  // ── COLLECTOR CONTRIBUTION DONUT CHART ──
  const collectorDonutData = useMemo(() => {
    const map = new Map<string, number>()
    payments.forEach((p) => {
      const col = p.collector?.trim() || "Kolektor Umum"
      map.set(col, (map.get(col) || 0) + (Number(p.amount) || 0))
    })
    const sorted = Array.from(map.entries()).sort((a, b) => b[1] - a[1])
    return {
      labels: sorted.length > 0 ? sorted.map(([k]) => k) : ["Belum ada setoran"],
      series: sorted.length > 0 ? sorted.map(([, v]) => v) : [0],
    }
  }, [payments])

  const collectorDonutOptions: ApexOptions = useMemo(() => ({
    chart: { type: "donut", fontFamily: "inherit" },
    labels: collectorDonutData.labels,
    colors: ["#10B981", "#0073C6", "#8B5CF6", "#F59E0B", "#EC4899", "#6366F1"],
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
              label: "Total Setoran",
              color: "#64748B",
              formatter: () => formatIDR(totalAmount),
            },
          },
        },
      },
    },
    tooltip: {
      theme: "light",
      y: { formatter: (val) => formatIDR(val) },
    },
  }), [collectorDonutData, totalAmount])

  return (
    <AppLayout
      title="Setoran Kolektor"
      brand={adminBrand}
      sidebarItems={adminSidebarItems}
      navItems={adminNavItems}
    >
      <div className="space-y-6">
        {/* ── 3 TOP KPI METRICS ── */}
        <div className="grid grid-cols-1 sm:grid-cols-3 gap-3 sm:gap-4">
          <MetricCard
            title="Total Setoran"
            value={formatIDR(totalAmount)}
            icon={<Wallet className="h-6 w-6 text-emerald-500" />}
            sub="Kas tagihan masuk"
            onClick={() => setCollectorFilter("all")}
            isActive={collectorFilter === "all"}
          />

          <MetricCard
            title="Total Transaksi"
            value={`${payments.length} Setoran`}
            icon={<Receipt className="h-6 w-6 text-blue-500" />}
            sub="Invoice tertagih"
          />

          <MetricCard
            title="Kolektor Aktif"
            value={`${uniqueCollectors.length} Kolektor`}
            icon={<Users className="h-6 w-6 text-purple-500" />}
            sub="Petugas penyetor kas"
          />
        </div>

        {/* ── CHARTS SECTION: DEPOSIT LEADERBOARD & RATIO ── */}
        <div className="grid grid-cols-1 lg:grid-cols-12 gap-4 sm:gap-6">
          {/* Deposit Leaderboard Bar Chart */}
          <div className="lg:col-span-7 rounded-2xl border border-gray-200 bg-white p-4 sm:p-5 shadow-xs dark:border-gray-800 dark:bg-white/[0.03]">
            <div className="flex items-center justify-between gap-2 mb-3 sm:mb-4">
              <div className="flex items-center gap-2">
                <div className="flex h-8 w-8 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10">
                  <BarChart3 className="h-4 w-4" />
                </div>
                <div>
                  <h3 className="text-sm sm:text-base font-bold text-gray-800 dark:text-white/90">
                    Leaderboard Setoran Kas
                  </h3>
                  <p className="text-[11px] text-gray-500 dark:text-gray-400">
                    Total penerimaan kas tagihan per petugas kolektor
                  </p>
                </div>
              </div>
            </div>
            <div className="h-[220px] sm:h-[240px] w-full">
              <Chart
                options={collectorBarOptions}
                series={[{ name: "Total Setoran", data: collectorBarData.values }]}
                type="bar"
                height="100%"
                width="100%"
              />
            </div>
          </div>

          {/* Contribution Ratio Donut Chart */}
          <div className="lg:col-span-5 rounded-2xl border border-gray-200 bg-white p-4 sm:p-5 shadow-xs dark:border-gray-800 dark:bg-white/[0.03]">
            <div className="flex items-center justify-between gap-2 mb-3 sm:mb-4">
              <div className="flex items-center gap-2">
                <div className="flex h-8 w-8 items-center justify-center rounded-xl bg-brand-50 text-brand-500 dark:bg-brand-500/10">
                  <PieChart className="h-4 w-4" />
                </div>
                <div>
                  <h3 className="text-sm sm:text-base font-bold text-gray-800 dark:text-white/90">
                    Proporsi Setoran Kolektor
                  </h3>
                  <p className="text-[11px] text-gray-500 dark:text-gray-400">
                    Persentase kontribusi kas tagihan masuk
                  </p>
                </div>
              </div>
            </div>
            <div className="h-[220px] sm:h-[240px] w-full flex items-center justify-center">
              <Chart
                options={collectorDonutOptions}
                series={collectorDonutData.series}
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
                placeholder="Cari nama pelanggan, no invoice, atau kolektor..."
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

            {/* Collector Filter */}
            <select
              value={collectorFilter}
              onChange={(e) => setCollectorFilter(e.target.value)}
              className="h-10 rounded-xl border border-gray-200 bg-white px-3 text-xs font-semibold text-gray-700 shadow-2xs focus:border-brand-500 focus:outline-hidden dark:border-gray-800 dark:bg-gray-900 dark:text-gray-200 cursor-pointer w-full sm:w-auto shrink-0"
            >
              <option value="all">Semua Kolektor</option>
              {uniqueCollectors.map((c) => (
                <option key={c} value={c}>
                  {c}
                </option>
              ))}
            </select>
          </div>

          <div className="flex items-center gap-2 shrink-0 w-full sm:w-auto justify-end flex-wrap sm:flex-nowrap">
            <ViewModeSwitcher
              value={viewMode}
              onChange={setViewMode}
              storageKey="nodera_admin_collector_payments_view"
              size="sm"
            />

            <button
              type="button"
              onClick={() => window.print()}
              className="inline-flex h-10 items-center gap-1.5 px-3 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:hover:bg-gray-800 text-xs font-semibold text-gray-700 dark:text-gray-200 transition active:scale-95 shadow-2xs cursor-pointer shrink-0"
              title="Cetak Rekap Setoran"
            >
              <Printer className="h-4 w-4 text-brand-500" />
              <span>Cetak Rekap</span>
            </button>
          </div>
        </div>

        {/* ── MASTER CARD CONTAINER ── */}
        <div className="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xs dark:border-gray-800 dark:bg-white/[0.03]">
          {/* Header Bar */}
          <div className="p-4 sm:p-5 border-b border-gray-100 dark:border-gray-800 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
              <h3 className="text-base font-bold text-gray-800 dark:text-white/90">
                Riwayat Setoran Tagihan Kolektor
              </h3>
              <p className="text-xs text-gray-500 dark:text-gray-400">
                {filtered.length} dari {payments.length} transaksi setoran ditampilkan
              </p>
            </div>
          </div>

          {/* ── TABLE VIEW ── */}
          {filtered.length === 0 ? (
            <div className="p-8 text-center">
              <EmptyState
                icon={<Wallet className="h-8 w-8 text-brand-500" />}
                title="Tidak ada data pembayaran kolektor"
                description={
                  search
                    ? "Tidak ada setoran yang cocok dengan kata kunci pencarian."
                    : "Belum ada transaksi pembayaran yang diproses oleh petugas kolektor."
                }
              />
            </div>
          ) : viewMode === "table" ? (
            <div className="w-full overflow-x-auto custom-scrollbar">
              <table className="w-full text-left text-xs text-gray-600 dark:text-gray-300 min-w-[950px] border-collapse">
                <thead className="border-b border-gray-200 bg-gray-50/75 text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:border-gray-800 dark:bg-gray-900/50 dark:text-gray-400">
                  <tr>
                    <th className="px-4 py-3.5">Invoice</th>
                    <th className="px-4 py-3.5">Pelanggan</th>
                    <th className="px-4 py-3.5">Petugas Kolektor</th>
                    <th className="px-4 py-3.5">Waktu Pembayaran</th>
                    <th className="px-4 py-3.5">Nominal Setoran</th>
                    <th className="px-4 py-3.5">Status</th>
                    <th className="px-4 py-3.5 text-right">Aksi</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-gray-100 dark:divide-gray-800">
                  {filtered.map((p) => (
                    <tr key={p.id} className="hover:bg-gray-50/50 dark:hover:bg-white/[0.02] transition">
                      {/* Invoice Column */}
                      <td className="px-4 py-3.5 font-mono font-bold text-brand-600 dark:text-brand-400">
                        {p.invoice_number}
                      </td>

                      {/* Pelanggan Column */}
                      <td className="px-4 py-3.5">
                        <div className="font-bold text-gray-900 dark:text-white">
                          {p.customer_name ?? "Pelanggan Umum"}
                        </div>
                      </td>

                      {/* Kolektor Column */}
                      <td className="px-4 py-3.5">
                        <span className="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-bold bg-purple-600 text-white shadow-xs whitespace-nowrap">
                          {p.collector ?? "Kolektor"}
                        </span>
                      </td>

                      {/* Waktu Pembayaran Column */}
                      <td className="px-4 py-3.5 text-gray-600 dark:text-gray-300">
                        {p.paid_at ? formatDate(p.paid_at) : "-"}
                      </td>

                      {/* Nominal Column */}
                      <td className="px-4 py-3.5 font-mono font-bold text-sm text-emerald-600 dark:text-emerald-400">
                        {formatIDR(p.amount)}
                      </td>

                      {/* Status Column */}
                      <td className="px-4 py-3.5">
                        <span className="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-bold bg-emerald-500 text-white shadow-xs whitespace-nowrap">
                          Lunas
                        </span>
                      </td>

                      {/* Single Action Button: Kelola (Solid Icon-Only) */}
                      <td className="px-4 py-3.5 text-right whitespace-nowrap">
                        <button
                          type="button"
                          onClick={() => setManagingPayment(p)}
                          className="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-brand-500 hover:bg-brand-600 active:scale-95 text-white shadow-xs transition cursor-pointer"
                          title="Detail Setoran"
                        >
                          <Settings className="h-4 w-4" />
                        </button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          ) : (
            /* Grid View */
            <div className="p-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3.5">
              {filtered.map((p) => (
                <div
                  key={p.id}
                  className="rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-4 flex flex-col justify-between gap-3 shadow-xs hover:border-gray-300 dark:hover:border-gray-700 transition"
                >
                  <div className="flex items-start justify-between gap-2">
                    <div>
                      <div className="font-mono font-bold text-brand-600 dark:text-brand-400 text-xs">
                        {p.invoice_number}
                      </div>
                      <div className="font-bold text-gray-900 dark:text-white text-sm mt-0.5">
                        {p.customer_name ?? "Pelanggan Umum"}
                      </div>
                    </div>
                    <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold bg-purple-600 text-white shadow-xs shrink-0">
                      {p.collector ?? "Kolektor"}
                    </span>
                  </div>

                  <div className="grid grid-cols-2 gap-2 text-[11px] border-y border-gray-100 dark:border-gray-800/80 py-2.5">
                    <div>
                      <span className="text-gray-400 block text-[10px]">Waktu Bayar</span>
                      <span className="font-medium text-gray-700 dark:text-gray-300 text-[10px]">
                        {p.paid_at ? formatDate(p.paid_at) : "-"}
                      </span>
                    </div>
                    <div>
                      <span className="text-gray-400 block text-[10px]">Status</span>
                      <span className="font-bold text-emerald-600 dark:text-emerald-400 text-[10px]">
                        Lunas
                      </span>
                    </div>
                  </div>

                  <div className="flex items-center justify-between pt-1">
                    <span className="font-mono font-bold text-emerald-600 dark:text-emerald-400 text-sm">
                      {formatIDR(p.amount)}
                    </span>
                    <button
                      type="button"
                      onClick={() => setManagingPayment(p)}
                      className="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-brand-500 hover:bg-brand-600 active:scale-95 text-white shadow-xs transition cursor-pointer"
                      title="Detail Setoran"
                    >
                      <Settings className="h-4 w-4" />
                    </button>
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>
      </div>

      {/* ── MODAL INTERAKTIF: DETAIL SETORAN ── */}
      {managingPayment && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-xs">
          <div className="w-full max-w-md rounded-2xl border border-gray-200 bg-white p-5 shadow-2xl dark:border-gray-800 dark:bg-gray-900 space-y-4 max-h-[92vh] overflow-y-auto">
            {/* Modal Header */}
            <div className="flex items-center justify-between border-b border-gray-100 pb-3 dark:border-gray-800">
              <div className="flex items-center gap-3">
                <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-purple-500 text-white shadow-xs font-bold text-sm">
                  <Receipt className="h-5 w-5" />
                </div>
                <div className="min-w-0">
                  <h3 className="font-bold text-sm text-gray-900 dark:text-white truncate">
                    Setoran: {managingPayment.invoice_number}
                  </h3>
                  <span className="text-[11px] text-gray-500 font-mono">
                    ID #{managingPayment.id} • {managingPayment.customer_name ?? "Pelanggan"}
                  </span>
                </div>
              </div>
              <button
                type="button"
                onClick={() => setManagingPayment(null)}
                className="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-800 dark:hover:text-gray-200 transition cursor-pointer"
              >
                <X className="h-5 w-5" />
              </button>
            </div>

            {/* Quick Summary Card */}
            <div className="rounded-xl border border-gray-200/80 bg-gray-50/70 p-3.5 dark:border-gray-800 dark:bg-gray-800/40 space-y-2 text-xs">
              <div className="flex items-center justify-between">
                <span className="text-gray-500 dark:text-gray-400">Status Setoran</span>
                <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold bg-emerald-500 text-white shadow-xs whitespace-nowrap">
                  Lunas Terverifikasi
                </span>
              </div>
              <div className="flex items-center justify-between">
                <span className="text-gray-500 dark:text-gray-400">Nominal Setoran</span>
                <span className="font-mono font-bold text-emerald-600 dark:text-emerald-400 text-sm">
                  {formatIDR(managingPayment.amount)}
                </span>
              </div>
              <div className="flex items-center justify-between">
                <span className="text-gray-500 dark:text-gray-400">Petugas Kolektor</span>
                <span className="font-semibold text-purple-600 dark:text-purple-400">
                  {managingPayment.collector ?? "Petugas Kolektor"}
                </span>
              </div>
              <div className="flex items-center justify-between">
                <span className="text-gray-500 dark:text-gray-400">Nama Pelanggan</span>
                <span className="font-semibold text-gray-900 dark:text-white">
                  {managingPayment.customer_name ?? "Pelanggan Umum"}
                </span>
              </div>
              <div className="flex items-center justify-between">
                <span className="text-gray-500 dark:text-gray-400">Waktu Pembayaran</span>
                <span className="font-mono text-gray-800 dark:text-gray-200">
                  {managingPayment.paid_at ? formatDate(managingPayment.paid_at) : "-"}
                </span>
              </div>
            </div>

            {/* Action Buttons List */}
            <div className="space-y-2 pt-1 text-xs">
              <button
                type="button"
                onClick={() => {
                  if (managingPayment.customer_name) {
                    setSearch(managingPayment.customer_name)
                  }
                  setManagingPayment(null)
                }}
                className="w-full group flex items-center justify-between p-3 rounded-xl bg-gray-50/70 hover:bg-brand-50/50 border border-gray-100 hover:border-brand-200 dark:bg-gray-800/40 dark:hover:bg-gray-800 dark:border-gray-800 dark:hover:border-brand-900/60 transition cursor-pointer"
              >
                <div className="flex items-center gap-3">
                  <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-brand-50 text-brand-600 dark:bg-brand-950 dark:text-brand-400 whitespace-nowrap">
                    <Search className="h-4 w-4" />
                  </div>
                  <div className="text-left">
                    <div className="font-bold text-gray-900 dark:text-white">
                      Filter Transaksi Pelanggan Ini
                    </div>
                    <div className="text-[10px] text-gray-500">Cari seluruh histori setoran dari {managingPayment.customer_name}</div>
                  </div>
                </div>
                <ChevronRight className="h-4 w-4 text-gray-400 group-hover:text-brand-500 transition-colors" />
              </button>
            </div>
          </div>
        </div>
      )}
    </AppLayout>
  )
}
