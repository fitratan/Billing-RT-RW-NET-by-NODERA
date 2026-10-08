import { useState, useMemo } from "react"
import { AppLayout } from "@/components/layout/app-layout"
import Chart from "react-apexcharts"
import type { ApexOptions } from "apexcharts"
import {
  Wallet,
  Calendar,
  Receipt,
  CheckCircle2,
  ChevronDown,
  Search,
  DollarSign,
  TrendingUp,
  Percent,
  User,
  ArrowUpRight,
  Filter,
} from "lucide-react"
import { PageProps } from "@/types"
import { cn, formatIDR, periodLabel } from "@/lib/utils"
import { collectorSidebarItems, collectorNavItems, collectorBrand } from "@/lib/collector-nav"
import { ViewModeSwitcher, type ViewMode } from "@/components/ui/view-mode-switcher"

interface InvoiceItem {
  id: number
  invoice_number: string
  customer_name: string
  customer_code: string
  amount: number
  commission: number
  paid_at: string
  period?: string
}

interface MonthlyEarning {
  month_key: string
  month_name: string
  total_invoices: number
  total_collected: number
  total_commission: number
  invoices?: InvoiceItem[]
}

export default function CollectorEarningsPage({
  collector,
  totalEarningsAllTime = 0,
  totalInvoicesAllTime = 0,
  monthlyEarnings = [],
}: PageProps<{
  collector: {
    name: string
    username: string
    commission_type: string
    commission_value: number
    commission_rate_label: string
  }
  totalEarningsAllTime: number
  totalInvoicesAllTime: number
  monthlyEarnings: MonthlyEarning[]
}>) {
  const [selectedMonthKey, setSelectedMonthKey] = useState<string>(
    monthlyEarnings[0]?.month_key ?? "all"
  )
  const [searchQuery, setSearchQuery] = useState("")
  const [viewMode, setViewMode] = useState<ViewMode>(() => {
    if (typeof window !== "undefined") {
      return (localStorage.getItem("nodera_collector_earnings_view") as ViewMode) || "table"
    }
    return "table"
  })

  const handleViewModeChange = (mode: ViewMode) => {
    setViewMode(mode)
    if (typeof window !== "undefined") {
      localStorage.setItem("nodera_collector_earnings_view", mode)
    }
  }

  // Active Month Data
  const currentMonthData = useMemo(() => {
    if (selectedMonthKey === "all") {
      return null
    }
    return monthlyEarnings.find((m) => m.month_key === selectedMonthKey) || monthlyEarnings[0] || null
  }, [monthlyEarnings, selectedMonthKey])

  // Flattened or Filtered Invoices
  const displayedInvoices = useMemo(() => {
    let list: InvoiceItem[] = []
    if (selectedMonthKey === "all") {
      monthlyEarnings.forEach((m) => {
        if (m.invoices) list.push(...m.invoices)
      })
    } else if (currentMonthData?.invoices) {
      list = [...currentMonthData.invoices]
    }

    if (searchQuery.trim()) {
      const q = searchQuery.toLowerCase()
      list = list.filter(
        (inv) =>
          inv.customer_name.toLowerCase().includes(q) ||
          inv.customer_code.toLowerCase().includes(q) ||
          inv.invoice_number.toLowerCase().includes(q)
      )
    }

    return list
  }, [monthlyEarnings, currentMonthData, selectedMonthKey, searchQuery])

  // ApexCharts Monthly Earnings Trend
  const chartCategories = useMemo(() => {
    return [...monthlyEarnings].reverse().map((m) => m.month_name)
  }, [monthlyEarnings])

  const chartSeries = useMemo(() => {
    return [
      {
        name: "Komisi Kolektor",
        data: [...monthlyEarnings].reverse().map((m) => m.total_commission),
      },
    ]
  }, [monthlyEarnings])

  const chartOptions: ApexOptions = {
    chart: {
      type: "area",
      fontFamily: "inherit",
      toolbar: { show: false },
      zoom: { enabled: false },
    },
    colors: ["#10B981"],
    stroke: { curve: "smooth", width: 3 },
    fill: {
      type: "gradient",
      gradient: {
        shadeIntensity: 1,
        opacityFrom: 0.45,
        opacityTo: 0.05,
        stops: [0, 90, 100],
      },
    },
    dataLabels: { enabled: false },
    xaxis: {
      categories: chartCategories,
      labels: {
        style: { colors: "#94A3B8", fontSize: "11px", fontWeight: 600 },
      },
      axisBorder: { show: false },
      axisTicks: { show: false },
    },
    yaxis: {
      labels: {
        formatter: (val) => `${Math.round(val / 1000)}k`,
        style: { colors: "#94A3B8", fontSize: "11px", fontWeight: 600 },
      },
    },
    tooltip: {
      theme: "light",
      y: {
        formatter: (val) => formatIDR(val),
      },
    },
    grid: {
      borderColor: "#F1F5F9",
      strokeDashArray: 4,
    },
  }

  return (
    <AppLayout
      title="Pendapatan & Komisi"
      brand={collectorBrand}
      sidebarItems={collectorSidebarItems}
      navItems={collectorNavItems}
    >
      <div className="space-y-4 sm:space-y-6 select-none pb-20">
        {/* ── TOP STATS OVERVIEW CARDS ── */}
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3.5 sm:gap-4">
          {/* Card 1: Total Komisi All-Time */}
          <div className="rounded-2xl border border-gray-200 bg-white p-4 sm:p-5 shadow-xs dark:border-gray-800 dark:bg-white/[0.03]">
            <div className="flex items-center justify-between">
              <span className="text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                Total Komisi Diterima
              </span>
              <div className="flex h-8 w-8 items-center justify-center rounded-xl bg-emerald-500 text-white shadow-xs">
                <Wallet className="h-4 w-4" />
              </div>
            </div>
            <div className="mt-3">
              <p className="text-xl sm:text-2xl font-black text-emerald-600 dark:text-emerald-400 truncate">
                {formatIDR(totalEarningsAllTime)}
              </p>
              <p className="text-xs text-gray-500 dark:text-gray-400 mt-1">
                Akumulasi seluruh tagihan lunas
              </p>
            </div>
          </div>

          {/* Card 2: Total Invoice Selesai */}
          <div className="rounded-2xl border border-gray-200 bg-white p-4 sm:p-5 shadow-xs dark:border-gray-800 dark:bg-white/[0.03]">
            <div className="flex items-center justify-between">
              <span className="text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                Total Tagihan Sukses
              </span>
              <div className="flex h-8 w-8 items-center justify-center rounded-xl bg-brand-500 text-white shadow-xs">
                <CheckCircle2 className="h-4 w-4" />
              </div>
            </div>
            <div className="mt-3">
              <p className="text-xl sm:text-2xl font-black text-gray-900 dark:text-white truncate">
                {totalInvoicesAllTime} Invoice
              </p>
              <p className="text-xs text-gray-500 dark:text-gray-400 mt-1">
                Berhasil ditagih di lapangan
              </p>
            </div>
          </div>

          {/* Card 3: Skema Komisi */}
          <div className="rounded-2xl border border-gray-200 bg-white p-4 sm:p-5 shadow-xs dark:border-gray-800 dark:bg-white/[0.03]">
            <div className="flex items-center justify-between">
              <span className="text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                Skema & Tarif Komisi
              </span>
              <div className="flex h-8 w-8 items-center justify-center rounded-xl bg-amber-500 text-white shadow-xs">
                <Percent className="h-4 w-4" />
              </div>
            </div>
            <div className="mt-3">
              <p className="text-xl sm:text-2xl font-black text-amber-600 dark:text-amber-400 truncate">
                {collector.commission_rate_label || `${formatIDR(collector.commission_value)} / invoice`}
              </p>
              <p className="text-xs text-gray-500 dark:text-gray-400 mt-1">
                Tarif resmi per transaksi lunas
              </p>
            </div>
          </div>

          {/* Card 4: Petugas Kolektor */}
          <div className="rounded-2xl border border-gray-200 bg-white p-4 sm:p-5 shadow-xs dark:border-gray-800 dark:bg-white/[0.03]">
            <div className="flex items-center justify-between">
              <span className="text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                Profil Petugas
              </span>
              <div className="flex h-8 w-8 items-center justify-center rounded-xl bg-purple-600 text-white shadow-xs">
                <User className="h-4 w-4" />
              </div>
            </div>
            <div className="mt-3">
              <p className="text-lg sm:text-xl font-black text-gray-900 dark:text-white truncate">
                {collector.name}
              </p>
              <p className="text-xs text-brand-600 dark:text-brand-400 font-bold mt-1">
                @{collector.username} • Petugas Aktif
              </p>
            </div>
          </div>
        </div>

        {/* ── GRAFIK TREN PENDAPATAN BULANAN ── */}
        {monthlyEarnings.length > 0 && (
          <div className="rounded-2xl border border-gray-200 bg-white p-4 sm:p-5 shadow-xs dark:border-gray-800 dark:bg-white/[0.03] space-y-3">
            <div className="flex items-center justify-between">
              <div className="flex items-center gap-2">
                <TrendingUp className="h-5 w-5 text-emerald-500" />
                <h3 className="font-bold text-sm sm:text-base text-gray-900 dark:text-white">
                  Tren Perolehan Komisi Bulanan
                </h3>
              </div>
              <span className="text-xs text-gray-500 dark:text-gray-400">
                {monthlyEarnings.length} Periode Tercatat
              </span>
            </div>
            <div className="h-52 w-full">
              <Chart options={chartOptions} series={chartSeries} type="area" height="100%" width="100%" />
            </div>
          </div>
        )}

        {/* ── 1-LINE SLEEK TOOLBAR (ADMIN BILLING STANDARD) ── */}
        <div className="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-2.5">
          <div className="flex flex-1 items-center gap-2 flex-wrap sm:flex-nowrap min-w-0">
            {/* Search Input */}
            <div className="relative flex-1 sm:w-64 sm:flex-initial min-w-[140px]">
              <Search className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
              <input
                type="text"
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                placeholder="Cari pelanggan, invoice..."
                className="h-10 w-full rounded-xl border border-gray-200 bg-white pl-9 pr-8 text-xs text-gray-900 placeholder:text-gray-400 focus:border-brand-500 focus:outline-hidden dark:border-gray-800 dark:bg-gray-900 dark:text-white dark:placeholder:text-gray-500 shadow-xs"
              />
              {searchQuery && (
                <button
                  type="button"
                  onClick={() => setSearchQuery("")}
                  className="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 text-sm font-bold"
                >
                  &times;
                </button>
              )}
            </div>

            {/* Filter Bulan Dropdown */}
            <select
              value={selectedMonthKey}
              onChange={(e) => setSelectedMonthKey(e.target.value)}
              className="h-10 px-3 py-2 text-xs font-bold rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 text-gray-900 dark:text-white cursor-pointer shadow-xs focus:ring-2 focus:ring-brand-500 focus:outline-hidden shrink-0"
            >
              <option value="all">Semua Periode ({monthlyEarnings.length} Bulan)</option>
              {monthlyEarnings.map((m) => (
                <option key={m.month_key} value={m.month_key}>
                  {m.month_name} ({formatIDR(m.total_commission)})
                </option>
              ))}
            </select>
          </div>

          <div className="flex items-center gap-2 justify-end shrink-0">
            <ViewModeSwitcher
              value={viewMode}
              onChange={handleViewModeChange}
              storageKey="nodera_collector_earnings_view"
              size="sm"
            />
          </div>
        </div>

        {/* ── TABEL ATAU GRID LOG PENDAPATAN ── */}
        <div className="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xs dark:border-gray-800 dark:bg-white/[0.03]">
          {displayedInvoices.length === 0 ? (
            <div className="p-10 sm:p-16 text-center space-y-3">
              <Receipt className="mx-auto h-10 w-10 text-gray-400 dark:text-gray-600" />
              <p className="text-sm font-bold text-gray-900 dark:text-white">
                Belum ada catatan komisi pada periode ini
              </p>
              <p className="text-xs text-gray-500 dark:text-gray-400 max-w-sm mx-auto">
                {searchQuery
                  ? "Tidak ada transaksi yang cocok dengan kata kunci pencarian."
                  : "Transaksi tagihan yang Anda selesaikan di lapangan akan otomatis tercatat di sini."}
              </p>
            </div>
          ) : viewMode === "table" ? (
            <div className="overflow-x-auto table-scrollbar">
              <table className="w-full text-start text-xs whitespace-nowrap border-collapse">
                <thead className="border-b border-gray-200 bg-gray-50/75 dark:border-gray-800 dark:bg-gray-800/50 text-gray-500 dark:text-gray-400 font-semibold">
                  <tr>
                    <th className="py-3 px-4 text-start font-bold">No Invoice</th>
                    <th className="py-3 px-4 text-start font-bold">Pelanggan</th>
                    <th className="py-3 px-4 text-start font-bold">Periode</th>
                    <th className="py-3 px-4 text-end font-bold">Nominal Tagihan</th>
                    <th className="py-3 px-4 text-end font-bold">Komisi Anda</th>
                    <th className="py-3 px-4 text-start font-bold">Waktu Pembayaran</th>
                    <th className="py-3 px-4 text-center font-bold">Status</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-gray-100 dark:divide-gray-800 text-gray-700 dark:text-gray-300">
                  {displayedInvoices.map((inv) => (
                    <tr key={inv.id} className="hover:bg-gray-50/75 dark:hover:bg-gray-800/40 transition-colors">
                      <td className="py-3 px-4 font-mono font-bold text-brand-600 dark:text-brand-400">
                        {inv.invoice_number}
                      </td>
                      <td className="py-3 px-4">
                        <div className="flex flex-col">
                          <span className="font-bold text-gray-900 dark:text-white">{inv.customer_name}</span>
                          <span className="text-[11px] font-mono text-gray-500 dark:text-gray-400">{inv.customer_code}</span>
                        </div>
                      </td>
                      <td className="py-3 px-4 font-medium text-gray-800 dark:text-gray-200">
                        {inv.period ? periodLabel(inv.period) : "-"}
                      </td>
                      <td className="py-3 px-4 text-end font-black text-gray-900 dark:text-white">
                        {formatIDR(inv.amount)}
                      </td>
                      <td className="py-3 px-4 text-end font-black text-emerald-600 dark:text-emerald-400">
                        +{formatIDR(inv.commission)}
                      </td>
                      <td className="py-3 px-4 text-gray-500 dark:text-gray-400 text-[11px]">
                        {inv.paid_at || "-"}
                      </td>
                      <td className="py-3 px-4 text-center">
                        <span className="inline-flex items-center gap-1 rounded-full bg-emerald-500 px-2.5 py-0.5 text-[10px] font-bold text-white shadow-xs">
                          <CheckCircle2 className="h-3 w-3" />
                          <span>Lunas</span>
                        </span>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          ) : (
            <div className="p-4 sm:p-5 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3.5">
              {displayedInvoices.map((inv) => (
                <div
                  key={inv.id}
                  className="p-4 rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-white/[0.03] space-y-3 shadow-xs"
                >
                  <div className="flex items-start justify-between gap-2">
                    <div>
                      <h4 className="font-bold text-sm text-gray-900 dark:text-white truncate">
                        {inv.customer_name}
                      </h4>
                      <p className="text-[11px] font-mono text-gray-500 dark:text-gray-400">
                        {inv.invoice_number} • {inv.customer_code}
                      </p>
                    </div>
                    <span className="inline-flex items-center gap-1 rounded-full bg-emerald-500 px-2 py-0.5 text-[10px] font-bold text-white shadow-xs shrink-0">
                      Lunas
                    </span>
                  </div>

                  <div className="rounded-xl bg-gray-50 dark:bg-gray-900/60 p-2.5 space-y-1.5 text-xs">
                    <div className="flex justify-between items-center text-gray-600 dark:text-gray-300">
                      <span>Tagihan:</span>
                      <span className="font-bold text-gray-900 dark:text-white">{formatIDR(inv.amount)}</span>
                    </div>
                    <div className="flex justify-between items-center text-gray-600 dark:text-gray-300">
                      <span>Komisi Anda:</span>
                      <span className="font-black text-emerald-600 dark:text-emerald-400">
                        +{formatIDR(inv.commission)}
                      </span>
                    </div>
                    <div className="flex justify-between items-center text-gray-600 dark:text-gray-300">
                      <span>Waktu Bayar:</span>
                      <span className="text-[11px] text-gray-500 dark:text-gray-400">{inv.paid_at || "-"}</span>
                    </div>
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>
      </div>
    </AppLayout>
  )
}
