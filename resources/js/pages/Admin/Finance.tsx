import { useState, useMemo, useEffect } from "react"
import { AppLayout } from "@/components/layout/app-layout"
import Chart from "react-apexcharts"
import type { ApexOptions } from "apexcharts"
import {
  Receipt,
  Wallet,
  TrendingUp,
  ArrowDownCircle,
  Plus,
  Download,
  Users,
  Calendar,
  Activity,
  Wifi,
  ChevronLeft,
  ChevronRight,
  RefreshCw,
  PieChart,
  X,
} from "lucide-react"
import { PageProps } from "@/types"
import { cn, formatIDR } from "@/lib/utils"
import { adminSidebarItems, adminNavItems, adminBrand } from "@/lib/admin-nav"
import { router, Link } from "@inertiajs/react"
import { MetricCard } from "@/components/tailadmin/MetricCard"

interface CollectorCommissionRow {
  collector_name: string
  role_label?: string
  total_invoices: number
  total_amount: number
  estimated_commission: number
  commission_rate_label?: string
}

const MONTH_NAMES = [
  "Januari", "Februari", "Maret", "April", "Mei", "Juni",
  "Juli", "Agustus", "September", "Oktober", "November", "Desember"
]

export default function AdminFinancePage({
  year: initialYear = new Date().getFullYear(),
  month: initialMonth = new Date().getMonth() + 1,
  selectedPeriod: initialPeriod,
  selectedDay: initialDay,
  selectedDate: initialDate,
  totalRevenue = 0,
  invoiceRevenue = 0,
  voucherRevenue = 0,
  operationalExpenses = 0,
  totalCollectorCommission = 0,
  totalExpenses = 0,
  profitLoss = 0,
  dailyRevenue = [],
  categoryBreakdown = [],
  recentRevenue = [],
  collectorCommission = [],
}: PageProps<{
  year: number
  month: number
  selectedPeriod?: string
  selectedDay?: string | number
  selectedDate?: string
  totalRevenue: number
  invoiceRevenue?: number
  voucherRevenue?: number
  operationalExpenses: number
  totalCollectorCommission?: number
  totalExpenses: number
  expenses: { id: number; description: string; category: string; amount: number; date: string | null }[]
  profitLoss: number
  dailyRevenue: { date: string; total: number }[]
  categoryBreakdown: { category: string; total: number }[]
  recentRevenue: { id: number; invoice_number: string; customer_name: string; amount: number; paid_at: string | null }[]
  recentVouchers?: { id: number; username: string; profile: string; amount: number; used_at: string | null }[]
  collectorCommission?: CollectorCommissionRow[]
  commissionFeePerInvoice?: number
  companyName?: string
  tenantName?: string
}>) {
  const [selectedMonth, setSelectedMonth] = useState<number>(Number(initialMonth))
  const [selectedYear, setSelectedYear] = useState<number>(Number(initialYear))
  const [selectedPeriod, setSelectedPeriod] = useState<string>(() => {
    if (initialPeriod) return initialPeriod
    return `${initialYear}-${String(initialMonth).padStart(2, "0")}`
  })
  const [selectedDay, setSelectedDay] = useState<string | number | null>(initialDay && initialDay !== "all" ? initialDay : null)
  const [selectedDate, setSelectedDate] = useState<string | null>(initialDate || null)
  const [calendarOpen, setCalendarOpen] = useState(false)
  const [calendarYear, setCalendarYear] = useState(() => {
    if (initialPeriod && initialPeriod !== "all") {
      const parts = initialPeriod.split("-")
      if (parts.length >= 1) return parseInt(parts[0], 10)
    }
    return Number(initialYear) || new Date().getFullYear()
  })
  const [calendarTab, setCalendarTab] = useState<"month" | "date">("month")
  const [isRefreshing, setIsRefreshing] = useState(false)

  useEffect(() => {
    setSelectedMonth(Number(initialMonth))
    setSelectedYear(Number(initialYear))
    if (initialPeriod) setSelectedPeriod(initialPeriod)
    setSelectedDay(initialDay && initialDay !== "all" ? initialDay : null)
    setSelectedDate(initialDate || null)
    if (initialPeriod && initialPeriod !== "all") {
      const parts = initialPeriod.split("-")
      if (parts.length >= 1) setCalendarYear(parseInt(parts[0], 10))
    }
  }, [initialMonth, initialYear, initialPeriod, initialDay, initialDate])

  const handleSelectPeriod = (p: string | null) => {
    setSelectedPeriod(p || "all")
    setSelectedDay(null)
    setSelectedDate(null)
    setCalendarOpen(false)

    router.get(
      "/admin/finance",
      {
        period: p || "all",
        day: "all",
        ...(p && p !== "all"
          ? {
              year: p.split("-")[0],
              month: parseInt(p.split("-")[1], 10),
            }
          : {}),
      },
      { preserveState: true, preserveScroll: true, replace: true }
    )
  }

  const handleSelectDay = (dayNum: number | null) => {
    const curYear = calendarYear || new Date().getFullYear()
    const curMonth = selectedPeriod && selectedPeriod !== "all" ? parseInt(selectedPeriod.split("-")[1], 10) : selectedMonth || new Date().getMonth() + 1
    const pStr = `${curYear}-${String(curMonth).padStart(2, "0")}`

    if (!dayNum) {
      setSelectedDay(null)
      setSelectedDate(null)
      setCalendarOpen(false)
      router.get(
        "/admin/finance",
        {
          period: pStr,
          day: "all",
          year: curYear,
          month: curMonth,
        },
        { preserveState: true, preserveScroll: true, replace: true }
      )
      return
    }

    const dStr = `${pStr}-${String(dayNum).padStart(2, "0")}`
    setSelectedDay(dayNum)
    setSelectedDate(dStr)
    setSelectedPeriod(pStr)
    setCalendarOpen(false)

    router.get(
      "/admin/finance",
      {
        period: pStr,
        day: dayNum,
        date: dStr,
        year: curYear,
        month: curMonth,
      },
      { preserveState: true, preserveScroll: true, replace: true }
    )
  }

  const handleRefresh = () => {
    setIsRefreshing(true)
    router.reload({
      preserveScroll: true,
      preserveState: true,
      onFinish: () => setIsRefreshing(false),
    })
  }

  const handleDownloadPdf = () => {
    window.location.href = `/admin/finance/export-pdf?download=1&year=${selectedYear}&month=${selectedMonth}`
  }

  const periodLabel = (periodKey: string) => {
    if (!periodKey || periodKey === "all") return "Semua Bulan"
    const parts = periodKey.split("-")
    if (parts.length !== 2) return periodKey
    const year = parts[0]
    const mIndex = parseInt(parts[1], 10) - 1
    if (mIndex < 0 || mIndex >= MONTH_NAMES.length) return periodKey
    return `${MONTH_NAMES[mIndex]} ${year}`
  }

  // ── Smooth Spline Curve Chart Calculation ──
  const now = new Date()
  const currentYear = now.getFullYear()
  const currentMonth = now.getMonth() + 1
  const todayDate = now.getDate()

  const isCurrentMonth = selectedYear === currentYear && selectedMonth === currentMonth
  const isFutureMonth = selectedYear > currentYear || (selectedYear === currentYear && selectedMonth > currentMonth)
  const daysInMonth = new Date(selectedYear, selectedMonth, 0).getDate()

  const displayDaysCount = isFutureMonth
    ? 0
    : isCurrentMonth
      ? Math.min(todayDate, daysInMonth)
      : daysInMonth

  const dailyMap = useMemo(() => {
    const map: Record<number, number> = {}
    dailyRevenue.forEach((d) => {
      const parts = d.date.split("-")
      const dayNum = parseInt(parts[2], 10)
      if (!isNaN(dayNum)) map[dayNum] = (map[dayNum] || 0) + d.total
    })
    return map
  }, [dailyRevenue])

  const fullMonthData = useMemo(() => {
    if (displayDaysCount <= 0) return []
    return Array.from({ length: displayDaysCount }, (_, idx) => {
      const day = idx + 1
      const total = dailyMap[day] || 0
      return {
        day,
        date: `${selectedYear}-${String(selectedMonth).padStart(2, "0")}-${String(day).padStart(2, "0")}`,
        total,
      }
    })
  }, [displayDaysCount, dailyMap, selectedYear, selectedMonth])

  const maxDailyValue = Math.max(1, ...fullMonthData.map((d) => d.total))
  const highestDay = useMemo(() => {
    if (fullMonthData.length === 0) return { day: 1, total: 0 }
    return fullMonthData.reduce((prev, curr) => (curr.total > prev.total ? curr : prev), fullMonthData[0] || { day: 1, total: 0 })
  }, [fullMonthData])

  // ── ApexCharts Area Spline Configuration ──
  const chartOptions: ApexOptions = useMemo(() => ({
    chart: {
      type: "area",
      height: 250,
      fontFamily: "Outfit, Inter, sans-serif",
      toolbar: { show: false },
      sparkline: { enabled: false },
      zoom: { enabled: false },
      background: "transparent",
    },
    colors: ["#0073C6"],
    fill: {
      type: "gradient",
      gradient: {
        shadeIntensity: 1,
        opacityFrom: 0.45,
        opacityTo: 0.05,
        stops: [0, 90, 100],
      },
    },
    stroke: {
      curve: "smooth",
      width: 3,
    },
    dataLabels: { enabled: false },
    grid: {
      borderColor: "rgba(156, 163, 175, 0.15)",
      strokeDashArray: 4,
      padding: { top: 10, right: 15, bottom: 0, left: 15 },
    },
    xaxis: {
      categories: fullMonthData.map((d) => `Tgl ${d.day}`),
      axisBorder: { show: false },
      axisTicks: { show: false },
      labels: {
        rotate: 0,
        style: { colors: "#9ca3af", fontSize: "10px", fontWeight: 600 },
      },
    },
    yaxis: {
      labels: {
        formatter: (val: number) => {
          if (val >= 1000000) return `Rp ${(val / 1000000).toFixed(1)}M`
          if (val >= 1000) return `Rp ${(val / 1000).toFixed(0)}k`
          return `Rp ${val}`
        },
        style: { colors: "#9ca3af", fontSize: "10px", fontWeight: 600 },
      },
    },
    tooltip: {
      theme: "dark",
      y: { formatter: (val: number) => formatIDR(val) },
    },
  }), [fullMonthData])

  const chartSeries = useMemo(() => [
    {
      name: "Kas Masuk",
      data: fullMonthData.map((d) => d.total),
    },
  ], [fullMonthData])

  const computedInvoiceRevenue = invoiceRevenue || Math.max(0, totalRevenue - (voucherRevenue || 0))
  const computedVoucherRevenue = voucherRevenue || 0

  return (
    <AppLayout
      title="Laporan Keuangan"
      brand={adminBrand}
      sidebarItems={adminSidebarItems}
      navItems={adminNavItems}
    >
      <div className="space-y-4 sm:space-y-6">
        {/* ── 1-LINE RESPONSIVE TOOLBAR ── */}
        <div className="rounded-2xl border border-gray-200 bg-white p-3 sm:p-4 shadow-xs dark:border-gray-800 dark:bg-white/[0.03] flex items-center justify-between gap-2 sm:gap-3">
          {/* Left: Standard Calendar Icon Button */}
          <div className="flex items-center gap-2 min-w-0">
            <button
              type="button"
              onClick={() => setCalendarOpen(true)}
              title={
                selectedDay && selectedDay !== "all"
                  ? `Filter Tanggal ${selectedDay}${selectedPeriod && selectedPeriod !== "all" ? ` • ${periodLabel(selectedPeriod)}` : ""}`
                  : selectedPeriod && selectedPeriod !== "all"
                  ? `Filter Bulan: ${periodLabel(selectedPeriod)}`
                  : "Pilih Bulan / Tanggal Laporan"
              }
              className="inline-flex h-10 items-center justify-center gap-1.5 px-3 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:hover:bg-gray-800 text-gray-700 dark:text-gray-200 transition cursor-pointer shadow-2xs text-xs font-semibold shrink-0"
            >
              <Calendar className="h-4 w-4 shrink-0 text-brand-500" />
              <span className="truncate">
                {selectedDay && selectedDay !== "all"
                  ? `Tgl ${selectedDay}${selectedPeriod && selectedPeriod !== "all" ? ` • ${periodLabel(selectedPeriod)}` : ""}`
                  : (selectedPeriod && selectedPeriod !== "all")
                  ? periodLabel(selectedPeriod)
                  : "Semua Bulan"}
              </span>
            </button>
          </div>

          {/* Right: Actions */}
          <div className="flex items-center gap-1.5 sm:gap-2 shrink-0">
            <button
              type="button"
              onClick={handleRefresh}
              disabled={isRefreshing}
              title="Refresh Data"
              className="inline-flex h-10 w-10 sm:w-auto items-center justify-center gap-1.5 px-2.5 sm:px-3 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:hover:bg-gray-800 text-xs font-semibold text-gray-700 dark:text-gray-200 transition shadow-2xs disabled:opacity-50 cursor-pointer shrink-0"
            >
              <RefreshCw className={cn("h-4 w-4 shrink-0", isRefreshing && "animate-spin")} />
              <span className="hidden sm:inline">Refresh</span>
            </button>

            <button
              type="button"
              onClick={handleDownloadPdf}
              title="Unduh Laporan PDF"
              className="inline-flex h-10 w-10 sm:w-auto items-center justify-center gap-1.5 px-2.5 sm:px-3 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:hover:bg-gray-800 text-xs font-semibold text-gray-700 dark:text-gray-200 transition shadow-2xs cursor-pointer shrink-0"
            >
              <Download className="h-4 w-4 shrink-0" />
              <span className="hidden sm:inline">Unduh PDF</span>
            </button>

            <Link
              href="/admin/expenses"
              className="inline-flex h-10 items-center justify-center gap-1.5 px-3 sm:px-4 rounded-xl bg-brand-500 hover:bg-brand-600 active:scale-95 text-xs font-bold text-white shadow-xs transition cursor-pointer shrink-0"
            >
              <Plus className="h-4 w-4 shrink-0" />
              <span>Catat Kas</span>
            </Link>
          </div>
        </div>

        {/* ── CALENDAR & DATE SELECTOR MODAL DIALOG ── */}
        {calendarOpen && (
          <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-xs">
            <div className="w-full max-w-md overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-2xl dark:border-gray-800 dark:bg-gray-900">
              {/* Header */}
              <div className="flex items-center justify-between border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                <div className="flex items-center gap-2">
                  <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-brand-500/10 text-brand-600 dark:bg-brand-500/20 dark:text-brand-400">
                    <Calendar className="h-4 w-4" />
                  </div>
                  <div>
                    <h3 className="font-bold text-gray-900 dark:text-white text-sm">
                      Filter Waktu Laporan Keuangan
                    </h3>
                    <p className="text-[11px] text-gray-500 dark:text-gray-400">
                      Pilih berdasarkan periode bulan atau tanggal spesifik
                    </p>
                  </div>
                </div>
                <button
                  type="button"
                  onClick={() => setCalendarOpen(false)}
                  className="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 transition cursor-pointer"
                >
                  <X className="h-4 w-4" />
                </button>
              </div>

              {/* Sub-Nav Tabs */}
              <div className="flex border-b border-gray-100 dark:border-gray-800 bg-gray-50/70 dark:bg-gray-900/50 p-1.5 gap-1.5">
                <button
                  type="button"
                  onClick={() => setCalendarTab("month")}
                  className={cn(
                    "flex-1 py-1.5 text-xs font-bold rounded-lg transition text-center cursor-pointer",
                    calendarTab === "month"
                      ? "bg-white dark:bg-gray-800 text-brand-600 dark:text-brand-400 shadow-2xs"
                      : "text-gray-500 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-200"
                  )}
                >
                  Pilihan Bulan
                </button>
                <button
                  type="button"
                  onClick={() => setCalendarTab("date")}
                  className={cn(
                    "flex-1 py-1.5 text-xs font-bold rounded-lg transition text-center cursor-pointer",
                    calendarTab === "date"
                      ? "bg-white dark:bg-gray-800 text-brand-600 dark:text-brand-400 shadow-2xs"
                      : "text-gray-500 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-200"
                  )}
                >
                  Pilihan Tanggal
                </button>
              </div>

              <div className="p-5 text-xs">
                {calendarTab === "month" ? (
                  <>
                    {/* YEAR STEPPER */}
                    <div className="flex items-center justify-between rounded-xl border border-gray-200 bg-gray-50 p-2 dark:border-gray-800 dark:bg-gray-900">
                      <button
                        type="button"
                        onClick={() => setCalendarYear((y) => y - 1)}
                        className="flex h-7 w-7 items-center justify-center rounded-lg hover:bg-white dark:hover:bg-gray-800 cursor-pointer"
                      >
                        <ChevronLeft className="h-4 w-4" />
                      </button>
                      <span className="font-bold text-gray-900 dark:text-white">
                        Tahun {calendarYear}
                      </span>
                      <button
                        type="button"
                        onClick={() => setCalendarYear((y) => y + 1)}
                        className="flex h-7 w-7 items-center justify-center rounded-lg hover:bg-white dark:hover:bg-gray-800 cursor-pointer"
                      >
                        <ChevronRight className="h-4 w-4" />
                      </button>
                    </div>

                    {/* MONTHS GRID */}
                    <div className="mt-4 grid grid-cols-3 gap-2">
                      {MONTH_NAMES.map((mName, idx) => {
                        const mStr = `${calendarYear}-${String(idx + 1).padStart(2, "0")}`
                        const isSelected = selectedPeriod === mStr && (!selectedDay || selectedDay === "all")

                        return (
                          <button
                            key={mStr}
                            type="button"
                            onClick={() => handleSelectPeriod(mStr)}
                            className={cn(
                              "rounded-xl border p-3 text-center transition-all cursor-pointer",
                              isSelected
                                ? "border-brand-500 bg-brand-500 text-white shadow-xs font-bold"
                                : "border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800"
                            )}
                          >
                            <div className="font-bold">{mName}</div>
                            <div className={cn("text-[10px]", isSelected ? "text-white/80" : "text-gray-400")}>
                              {calendarYear}
                            </div>
                          </button>
                        )
                      })}
                    </div>

                    {/* QUICK BUTTON: ALL PERIODS */}
                    <div className="mt-4 border-t border-gray-100 pt-3 dark:border-gray-800">
                      <button
                        type="button"
                        onClick={() => handleSelectPeriod(null)}
                        className="flex w-full items-center justify-center gap-1.5 rounded-xl border border-gray-200 bg-gray-50 py-2.5 font-semibold text-gray-700 hover:bg-gray-100 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-200 cursor-pointer"
                      >
                        <span>Tampilkan Semua Bulan (Semua Waktu)</span>
                      </button>
                    </div>
                  </>
                ) : (
                  <div className="space-y-4">
                    {/* DAY 1-31 NUMBER GRID */}
                    <div>
                      <div className="flex items-center justify-between mb-2">
                        <label className="text-gray-700 dark:text-gray-300 font-semibold text-xs">
                          Pilih Tanggal Laporan (1 - 31)
                        </label>
                        {selectedDay && selectedDay !== "all" && (
                          <span className="text-[11px] font-bold text-brand-600 dark:text-brand-400">
                            Tanggal {selectedDay} Aktif
                          </span>
                        )}
                      </div>
                      <div className="grid grid-cols-7 gap-1.5">
                        {Array.from({ length: 31 }, (_, i) => i + 1).map((dNum) => {
                          const isSelected = String(selectedDay) === String(dNum)
                          return (
                            <button
                              key={dNum}
                              type="button"
                              onClick={() => handleSelectDay(dNum)}
                              className={cn(
                                "h-9 rounded-xl border text-center font-bold text-xs transition-all cursor-pointer",
                                isSelected
                                  ? "border-brand-500 bg-brand-500 text-white shadow-xs"
                                  : "border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800"
                              )}
                            >
                              {dNum}
                            </button>
                          )
                        })}
                      </div>
                    </div>

                    {/* QUICK BUTTON: ALL DAYS */}
                    <div className="border-t border-gray-100 dark:border-gray-800 pt-3">
                      <button
                        type="button"
                        onClick={() => handleSelectDay(null)}
                        className="flex w-full items-center justify-center gap-1.5 rounded-xl border border-gray-200 bg-gray-50 py-2.5 font-semibold text-gray-700 hover:bg-gray-100 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-200 cursor-pointer"
                      >
                        <span>Tampilkan Semua Tanggal (1 - 31)</span>
                      </button>
                    </div>
                  </div>
                )}
              </div>
            </div>
          </div>
        )}

        {/* Top 4 Official MetricCard Summary */}
        <div className="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
          <MetricCard
            title="Laba Bersih"
            value={formatIDR(profitLoss)}
            sub="Kas Bersih Operasional"
            icon={Wallet}
            iconBgColor={profitLoss >= 0 ? "bg-emerald-50 dark:bg-emerald-500/10" : "bg-rose-50 dark:bg-rose-500/10"}
            iconColor={profitLoss >= 0 ? "text-emerald-600 dark:text-emerald-400" : "text-rose-600 dark:text-rose-400"}
            badge={profitLoss >= 0 ? { text: "Surplus", color: "success" } : { text: "Defisit", color: "error" }}
          />
          <MetricCard
            title="Total Inflow"
            value={formatIDR(totalRevenue)}
            sub={`Tagihan ${formatIDR(computedInvoiceRevenue)}`}
            icon={TrendingUp}
            iconBgColor="bg-emerald-50 dark:bg-emerald-500/10"
            iconColor="text-emerald-600 dark:text-emerald-400"
            badge={{ text: "Inflow", color: "success" }}
          />
          <MetricCard
            title="Total Beban"
            value={formatIDR(totalExpenses)}
            sub={`Ops ${formatIDR(operationalExpenses || (totalExpenses - (totalCollectorCommission || 0)))}`}
            icon={ArrowDownCircle}
            iconBgColor="bg-rose-50 dark:bg-rose-500/10"
            iconColor="text-rose-600 dark:text-rose-400"
            badge={{ text: "Outflow", color: "error" }}
          />
          <MetricCard
            title="Komisi Kolektor"
            value={formatIDR(totalCollectorCommission || 0)}
            sub={`${collectorCommission?.length || 0} Petugas Penagih`}
            icon={Users}
            iconBgColor="bg-blue-50 dark:bg-blue-500/10"
            iconColor="text-blue-600 dark:text-blue-400"
            badge={{ text: "Petugas", color: "info" }}
          />
        </div>

        {/* Curved Daily Revenue Apex Area Spline Chart */}
        <div className="p-5 rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-white/[0.03] shadow-xs overflow-hidden space-y-4">
          <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-gray-100 dark:border-gray-800 pb-3">
            <div className="flex items-center gap-2">
              <Activity className="h-4 w-4 text-brand-500" />
              <h3 className="text-sm font-bold text-gray-900 dark:text-white">Tren Pendapatan Harian</h3>
            </div>
            <div className="text-xs text-gray-500 dark:text-gray-400">
              Puncak: <strong className="text-gray-900 dark:text-white">{formatIDR(highestDay.total)}</strong> (Tgl {highestDay.day})
            </div>
          </div>

          <div className="relative w-full overflow-hidden">
            {fullMonthData.length === 0 ? (
              <div className="h-[250px] flex items-center justify-center text-xs text-gray-400">
                Belum ada data pendapatan pada periode ini
              </div>
            ) : (
              <Chart
                options={chartOptions}
                series={chartSeries}
                type="area"
                height={250}
              />
            )}
          </div>
        </div>

        {/* Revenue Breakdown & Expense Category Matrix */}
        <div className="grid gap-4 lg:grid-cols-2">
          {/* Breakdown Sumber Pemasukan */}
          <div className="p-5 rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-white/[0.03] shadow-xs space-y-3.5">
            <div className="flex items-center justify-between border-b border-gray-100 dark:border-gray-800 pb-3">
              <div className="flex items-center gap-2">
                <PieChart className="h-4 w-4 text-brand-500" />
                <h3 className="text-sm font-bold text-gray-900 dark:text-white">Sumber Kas Masuk</h3>
              </div>
              <span className="text-xs font-mono text-emerald-600 dark:text-emerald-400 font-bold">{formatIDR(totalRevenue)}</span>
            </div>

            <div className="space-y-2.5">
              {/* Tagihan Pelanggan */}
              <div className="p-3.5 rounded-xl border border-gray-100 dark:border-gray-800 bg-gray-50/80 dark:bg-gray-900/50 flex items-center justify-between">
                <div className="flex items-center gap-3">
                  <div className="relative flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-brand-500 font-bold text-xs shadow-xs">
                    <Receipt className="h-4.5 w-4.5" />
                    <span className="absolute -bottom-0.5 -right-0.5 h-2.5 w-2.5 rounded-full bg-emerald-500 ring-2 ring-white dark:ring-gray-900 whitespace-nowrap" />
                  </div>
                  <div>
                    <h4 className="font-bold text-xs text-gray-900 dark:text-white">Tagihan Internet Bulanan</h4>
                    <p className="text-[11px] text-gray-500 dark:text-gray-400">Pelanggan PPPoE &amp; IP Statis</p>
                  </div>
                </div>
                <span className="font-mono font-bold text-xs text-gray-900 dark:text-white">{formatIDR(computedInvoiceRevenue)}</span>
              </div>

              {/* Voucher Hotspot */}
              <div className="p-3.5 rounded-xl border border-gray-100 dark:border-gray-800 bg-gray-50/80 dark:bg-gray-900/50 flex items-center justify-between">
                <div className="flex items-center gap-3">
                  <div className="relative flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-amber-500 font-bold text-xs shadow-xs">
                    <Wifi className="h-4.5 w-4.5" />
                    <span className="absolute -bottom-0.5 -right-0.5 h-2.5 w-2.5 rounded-full bg-emerald-500 ring-2 ring-white dark:ring-gray-900 whitespace-nowrap" />
                  </div>
                  <div>
                    <h4 className="font-bold text-xs text-gray-900 dark:text-white">Voucher Hotspot MIKHMON</h4>
                    <p className="text-[11px] text-gray-500 dark:text-gray-400">Penjualan Voucher WiFi</p>
                  </div>
                </div>
                <span className="font-mono font-bold text-xs text-gray-900 dark:text-white">{formatIDR(computedVoucherRevenue)}</span>
              </div>
            </div>
          </div>

          {/* Breakdown Kategori Beban Pengeluaran */}
          <div className="p-5 rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-white/[0.03] shadow-xs space-y-3.5">
            <div className="flex items-center justify-between border-b border-gray-100 dark:border-gray-800 pb-3">
              <div className="flex items-center gap-2">
                <ArrowDownCircle className="h-4 w-4 text-rose-500 dark:text-rose-400" />
                <h3 className="text-sm font-bold text-gray-900 dark:text-white">Kategori Beban Pengeluaran</h3>
              </div>
              <span className="text-xs font-mono text-rose-600 dark:text-rose-400 font-bold">{formatIDR(totalExpenses)}</span>
            </div>

            {categoryBreakdown.length === 0 ? (
              <div className="p-6 rounded-xl border border-dashed border-gray-200 dark:border-gray-800 bg-gray-50/80 dark:bg-gray-900/50 text-center text-xs text-gray-500 dark:text-gray-400">
                Belum ada catatan beban pengeluaran untuk periode ini.
              </div>
            ) : (
              <div className="space-y-2.5">
                {categoryBreakdown.map((cat, idx) => {
                  const percent = Math.round((cat.total / Math.max(1, totalExpenses)) * 100)
                  return (
                    <div key={idx} className="p-3 rounded-xl border border-gray-100 dark:border-gray-800 bg-gray-50/80 dark:bg-gray-900/50 space-y-1.5">
                      <div className="flex items-center justify-between text-xs">
                        <span className="font-bold text-gray-800 dark:text-gray-200 capitalize">{cat.category}</span>
                        <span className="font-mono font-bold text-gray-900 dark:text-white">{formatIDR(cat.total)} ({percent}%)</span>
                      </div>
                      <div className="h-1.5 w-full rounded-full bg-gray-200 dark:bg-gray-800 overflow-hidden">
                        <div className="h-full bg-rose-500 rounded-full" style={{ width: `${percent}%` }} />
                      </div>
                    </div>
                  )
                })}
              </div>
            )}
          </div>
        </div>

        {/* Komisi Kolektor / Kasir Ledger */}
        {collectorCommission && collectorCommission.length > 0 && (
          <div className="p-5 rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-white/[0.03] shadow-xs space-y-3.5">
            <div className="flex items-center justify-between border-b border-gray-100 dark:border-gray-800 pb-3">
              <div className="flex items-center gap-2">
                <Users className="h-4 w-4 text-brand-500" />
                <h3 className="text-sm font-bold text-gray-900 dark:text-white">Rekapitulasi Komisi Kolektor &amp; Kasir</h3>
              </div>
              <span className="text-xs text-gray-500 dark:text-gray-400 font-medium">
                Total Komisi: <strong className="text-gray-900 dark:text-white font-mono">{formatIDR(totalCollectorCommission || 0)}</strong>
              </span>
            </div>

            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
              {collectorCommission.map((col, idx) => (
                <div key={idx} className="p-3.5 rounded-xl border border-gray-100 dark:border-gray-800 bg-gray-50/80 dark:bg-gray-900/50 space-y-2">
                  <div className="flex items-center gap-2.5">
                    <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-800 dark:text-gray-200 font-bold text-xs shadow-xs">
                      {col.collector_name.slice(0, 2).toUpperCase()}
                    </div>
                    <div className="min-w-0 flex-1">
                      <h4 className="font-bold text-xs text-gray-900 dark:text-white truncate">{col.collector_name}</h4>
                      <p className="text-[10px] text-gray-500 dark:text-gray-400 font-mono">{col.total_invoices} Tagihan Tertagih</p>
                    </div>
                  </div>
                  <div className="pt-2 border-t border-gray-200 dark:border-gray-800 flex items-center justify-between text-xs">
                    <span className="text-gray-500 dark:text-gray-400">Total Diterima:</span>
                    <strong className="font-mono text-emerald-600 dark:text-emerald-400 font-bold">{formatIDR(col.estimated_commission)}</strong>
                  </div>
                </div>
              ))}
            </div>
          </div>
        )}

        {/* Transaksi Kas Masuk Terkini */}
        {recentRevenue && recentRevenue.length > 0 && (
          <div className="p-5 rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-white/[0.03] shadow-xs space-y-3.5">
            <div className="flex items-center justify-between border-b border-gray-100 dark:border-gray-800 pb-3">
              <div className="flex items-center gap-2">
                <Receipt className="h-4 w-4 text-brand-500" />
                <h3 className="text-sm font-bold text-gray-900 dark:text-white">Transaksi Tagihan Lunas Terkini</h3>
              </div>
              <Link href="/admin/billing/invoices" className="text-xs font-bold text-brand-600 dark:text-brand-400 hover:underline">
                Lihat Semua Tagihan
              </Link>
            </div>

            <div className="grid gap-2.5 sm:grid-cols-2 lg:grid-cols-3">
              {recentRevenue.slice(0, 6).map((rev) => (
                <div key={rev.id} className="p-3.5 rounded-xl border border-gray-100 dark:border-gray-800 bg-gray-50/80 dark:bg-gray-900/50 flex items-center justify-between">
                  <div className="flex items-center gap-2.5 min-w-0 flex-1">
                    <div className="relative flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-800 dark:text-gray-200 font-bold text-xs shadow-xs">
                      {rev.customer_name ? rev.customer_name.slice(0, 2).toUpperCase() : "CS"}
                      <span className="absolute -bottom-0.5 -right-0.5 h-2 w-2 rounded-full bg-emerald-500 ring-2 ring-white dark:ring-gray-900 whitespace-nowrap" />
                    </div>
                    <div className="min-w-0 flex-1">
                      <h4 className="font-bold text-xs text-gray-900 dark:text-white truncate">{rev.customer_name}</h4>
                      <div className="flex items-center gap-1.5 text-[10px] text-gray-500 dark:text-gray-400 font-mono mt-0.5">
                        <span className="inline-flex items-center px-1.5 py-0.2 rounded text-[10px] font-bold bg-emerald-500 text-white shadow-xs">Lunas</span>
                        <span>•</span>
                        <span className="truncate">{rev.invoice_number}</span>
                      </div>
                    </div>
                  </div>
                  <span className="font-mono font-bold text-xs text-gray-900 dark:text-white shrink-0 ml-2">{formatIDR(rev.amount)}</span>
                </div>
              ))}
            </div>
          </div>
        )}
      </div>
    </AppLayout>
  )
}
