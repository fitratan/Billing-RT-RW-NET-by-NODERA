import { useState, useMemo, useEffect, useRef, useCallback } from "react"
import { AppLayout } from "@/components/layout/app-layout"
import Chart from "react-apexcharts"
import type { ApexOptions } from "apexcharts"
import {
  Receipt,
  Search,
  CheckCircle2,
  Clock,
  Send,
  ChevronDown,
  DollarSign,
  Eye,
  Check,
  X,
  CreditCard,
  Calendar,
  ChevronLeft,
  ChevronRight,
  SlidersHorizontal,
  User,
  Users,
  MapPin,
  TrendingUp,
  Printer,
  Sparkles,
  Phone,
  ArrowUpRight,
  RotateCcw,
  CheckSquare,
} from "lucide-react"
import { PageProps } from "@/types"
import { cn, formatIDR, periodLabel } from "@/lib/utils"
import { collectorSidebarItems, collectorNavItems, collectorBrand } from "@/lib/collector-nav"
import { ViewModeSwitcher, type ViewMode } from "@/components/ui/view-mode-switcher"
import { router, Link } from "@inertiajs/react"
import { Checkbox } from "@/components/tailadmin/Checkbox"

interface BreakdownPeriod {
  period: string
  amount: number
  label?: string
}

interface CollectorCustomerItem {
  id: number
  name: string
  code: string
  phone: string | null
  address: string | null
  router: string | null
  package: string | null
  status: string
  assigned_collector_name?: string | null
  latest_invoice?: {
    id: number
    invoice_number: string
    amount: number
    paid: boolean
    due_date: string | null
    paid_at: string | null
    period: string
    periods_breakdown?: any
    processed_by?: string
    collector_id?: number
    assigned_collector_name?: string | null
  }
}

interface CollectorInvoicesProps extends PageProps {
  collector: {
    name: string
    username: string
    router?: string
  }
  selectedPeriod?: string
  selectedDate?: string | null
  selectedDay?: string | null
  initialDay?: string | null
  initialPeriod?: string
  filters?: {
    search?: string
    status?: string
    package?: string
    show_all?: boolean
    date?: string | null
    day?: string | null
    period?: string
  }
  stats: {
    totalCustomers: number
    paidCount: number
    unpaidCount: number
    paidAmount: number
    myPaidAmount: number
    myPaidCount: number
    unpaidAmount: number
    todayPaidAmount: number
    todayPaidCount?: number
  }
  todayPayments: Array<{
    id: number
    customer_name: string
    amount: number
    paid_at: string
    period: string
    assigned_collector_name?: string
  }>
  customers: CollectorCustomerItem[]
  packages: Array<{ id: number; name: string }>
}

export default function CollectorInvoicesPage({
  collector,
  selectedPeriod: serverPeriod,
  selectedDate: serverDate,
  selectedDay: serverDay,
  initialPeriod,
  filters: serverFilters,
  stats,
  todayPayments = [],
  customers = [],
  packages = [],
}: CollectorInvoicesProps) {
  const currentPeriodKey = new Date().toISOString().slice(0, 7)
  const [selectedPeriod, setSelectedPeriod] = useState<string>(serverPeriod || initialPeriod || currentPeriodKey)
  const [selectedDate, setSelectedDate] = useState<string | null>(serverDate || null)
  const [selectedDay, setSelectedDay] = useState<string | null>(serverDay || null)

  const [search, setSearch] = useState(serverFilters?.search || "")
  const [statusFilter, setStatusFilter] = useState<"unpaid" | "paid">(
    serverFilters?.status === "paid" ? "paid" : "unpaid"
  )
  const [packageFilter, setPackageFilter] = useState(serverFilters?.package || "all")
  const [viewMode, setViewMode] = useState<ViewMode>(() => {
    if (typeof window !== "undefined") {
      return (localStorage.getItem("nodera_collector_invoices_view") as ViewMode) || "table"
    }
    return "table"
  })

  const handleViewModeChange = (mode: ViewMode) => {
    setViewMode(mode)
    if (typeof window !== "undefined") {
      localStorage.setItem("nodera_collector_invoices_view", mode)
    }
  }

  // Multi-Selection State
  const [selectedIds, setSelectedIds] = useState<number[]>([])

  // Modals State
  const [calendarModalOpen, setCalendarModalOpen] = useState(false)
  const [calendarTab, setCalendarTab] = useState<"month" | "day">("month")
  const [calendarYear, setCalendarYear] = useState<number>(() => {
    const p = serverPeriod || currentPeriodKey
    const y = parseInt(p.split("-")[0], 10)
    return isNaN(y) ? new Date().getFullYear() : y
  })
  const [filterModalOpen, setFilterModalOpen] = useState(false)

  // Payment Confirmation Modal State
  const [managingItem, setManagingItem] = useState<CollectorCustomerItem | null>(null)
  const [paymentMethod, setPaymentMethod] = useState<"cash" | "transfer">("cash")
  const [isProcessing, setIsProcessing] = useState(false)
  const [selectedPeriods, setSelectedPeriods] = useState<string[]>([])
  const [sendWaNotif, setSendWaNotif] = useState(true)

  // Top Synchronized Scrollbar Runway
  const topScrollRef = useRef<HTMLDivElement>(null)
  const tableScrollRef = useRef<HTMLDivElement>(null)
  const [tableScrollWidth, setTableScrollWidth] = useState(1200)

  const syncTableScrollWidth = useCallback(() => {
    if (tableScrollRef.current) {
      setTableScrollWidth(tableScrollRef.current.scrollWidth)
    }
  }, [])

  useEffect(() => {
    syncTableScrollWidth()
    const observer = new ResizeObserver(syncTableScrollWidth)
    if (tableScrollRef.current) {
      observer.observe(tableScrollRef.current)
    }
    return () => observer.disconnect()
  }, [syncTableScrollWidth, customers, viewMode])

  const handleTopScroll = () => {
    if (topScrollRef.current && tableScrollRef.current) {
      tableScrollRef.current.scrollLeft = topScrollRef.current.scrollLeft
    }
  }

  const handleTableScroll = () => {
    if (topScrollRef.current && tableScrollRef.current) {
      topScrollRef.current.scrollLeft = tableScrollRef.current.scrollLeft
    }
  }

  // Filter customers locally based on search, status, and package
  const filteredCustomers = useMemo(() => {
    return customers.filter((item) => {
      const inv = item.latest_invoice
      if (statusFilter === "unpaid" && inv?.paid) return false
      if (statusFilter === "paid" && !inv?.paid) return false
      if (packageFilter !== "all" && item.package !== packageFilter) return false

      if (search.trim()) {
        const q = search.toLowerCase()
        const matchName = item.name.toLowerCase().includes(q)
        const matchCode = item.code.toLowerCase().includes(q)
        const matchPhone = item.phone?.toLowerCase().includes(q) || false
        const matchInv = inv?.invoice_number?.toLowerCase().includes(q) || false
        const matchCollector = (item.assigned_collector_name || inv?.assigned_collector_name || inv?.processed_by || "").toLowerCase().includes(q)
        if (!matchName && !matchCode && !matchPhone && !matchInv && !matchCollector) return false
      }
      return true
    })
  }, [customers, statusFilter, packageFilter, search])

  // Select all / clear
  const unpaidItems = useMemo(() => {
    return filteredCustomers.filter((c) => c.latest_invoice && !c.latest_invoice.paid)
  }, [filteredCustomers])

  const handleSelectAll = (checked: boolean) => {
    if (checked) {
      setSelectedIds(unpaidItems.map((c) => c.latest_invoice!.id))
    } else {
      setSelectedIds([])
    }
  }

  const handleToggleSelect = (invoiceId: number) => {
    setSelectedIds((prev) =>
      prev.includes(invoiceId) ? prev.filter((id) => id !== invoiceId) : [...prev, invoiceId]
    )
  }

  // Navigation handlers for Period and Date
  const handleSelectPeriod = (periodKey: string) => {
    setSelectedPeriod(periodKey)
    setSelectedDate(null)
    setSelectedDay(null)
    setCalendarModalOpen(false)
    router.get(
      "/kolektor/invoices",
      { period: periodKey, search, status: statusFilter, package: packageFilter },
      { preserveState: true, preserveScroll: true }
    )
  }

  const handleSelectDay = (dayNum: number) => {
    const dayStr = String(dayNum).padStart(2, "0")
    setSelectedDay(dayStr)
    const exactDate = `${calendarYear}-${String(selectedMonthIndex + 1).padStart(2, "0")}-${dayStr}`
    setSelectedDate(exactDate)
    setCalendarModalOpen(false)
    router.get(
      "/kolektor/invoices",
      { period: `${calendarYear}-${String(selectedMonthIndex + 1).padStart(2, "0")}`, day: dayStr, date: exactDate, search, status: statusFilter, package: packageFilter },
      { preserveState: true, preserveScroll: true }
    )
  }

  const selectedMonthIndex = useMemo(() => {
    const p = selectedPeriod || currentPeriodKey
    const m = parseInt(p.split("-")[1], 10) - 1
    return isNaN(m) ? new Date().getMonth() : m
  }, [selectedPeriod, currentPeriodKey])

  // Donut ApexCharts Configuration
  const donutSeries = useMemo(() => {
    const paid = stats.paidAmount || stats.myPaidAmount || 0
    const unpaid = stats.unpaidAmount || 0
    if (paid === 0 && unpaid === 0) return [1, 0]
    return [paid, unpaid]
  }, [stats])

  const donutOptions: ApexOptions = {
    chart: {
      type: "donut",
      fontFamily: "inherit",
      toolbar: { show: false },
      sparkline: { enabled: true },
    },
    labels: ["Terbayar", "Tertunggak"],
    colors: ["#10B981", "#F43F5E"],
    stroke: { width: 0 },
    legend: { show: false },
    tooltip: {
      theme: "light",
      y: {
        formatter: (val: number) => formatIDR(val),
      },
    },
    dataLabels: { enabled: false },
    plotOptions: {
      pie: {
        donut: {
          size: "74%",
          labels: {
            show: true,
            name: { show: false },
            value: {
              show: true,
              fontSize: "14px",
              fontWeight: 800,
              color: "#1E293B",
              formatter: () => `${stats.paidCount} Lunas (Saya)`,
            },
            total: {
              show: true,
              showAlways: true,
              label: "Koleksi",
              fontSize: "10px",
              fontWeight: 700,
              color: "#64748B",
              formatter: () => `${stats.paidCount}/${stats.totalCustomers}`,
            },
          },
        },
      },
    },
  }

  // Open managing modal
  const handleOpenManagingModal = (customerItem: CollectorCustomerItem) => {
    setManagingItem(customerItem)
    const inv = customerItem.latest_invoice
    if (inv?.periods_breakdown) {
      try {
        const parsed = typeof inv.periods_breakdown === "string"
          ? JSON.parse(inv.periods_breakdown)
          : inv.periods_breakdown
        if (Array.isArray(parsed) && parsed.length > 0) {
          setSelectedPeriods(parsed.map((p: any) => p.period))
        } else {
          setSelectedPeriods([inv.period])
        }
      } catch {
        setSelectedPeriods([inv.period])
      }
    } else if (inv) {
      setSelectedPeriods([inv.period])
    }
  }

  // Submit Payment for Single Customer
  const handleProcessPayment = () => {
    if (!managingItem) return
    setIsProcessing(true)

    router.post(
      `/kolektor/bayar/${managingItem.id}`,
      {
        method: paymentMethod,
        selected_periods: selectedPeriods,
        send_wa: sendWaNotif,
      },
      {
        preserveScroll: true,
        onSuccess: () => {
          setManagingItem(null)
          setIsProcessing(false)
        },
        onError: () => {
          setIsProcessing(false)
        },
      }
    )
  }

  // Submit Batch Payments
  const handleProcessBatchPayment = () => {
    if (selectedIds.length === 0) return
    if (!confirm(`Konfirmasi pembayaran untuk ${selectedIds.length} invoice terpilih?`)) return

    setIsProcessing(true)
    router.post(
      "/kolektor/bayar-batch",
      {
        invoice_ids: selectedIds,
        method: "cash",
      },
      {
        preserveScroll: true,
        onSuccess: () => {
          setSelectedIds([])
          setIsProcessing(false)
        },
        onError: () => {
          setIsProcessing(false)
        },
      }
    )
  }

  // Send WhatsApp Reminder
  const handleSendWa = (invoiceId: number) => {
    router.post(
      `/kolektor/invoices/${invoiceId}/send-wa`,
      {},
      { preserveScroll: true }
    )
  }

  const MONTH_NAMES = [
    "Januari", "Februari", "Maret", "April", "Mei", "Juni",
    "Juli", "Agustus", "September", "Oktober", "November", "Desember"
  ]

  return (
    <AppLayout
      title="Invoice & Tagihan"
      brand={collectorBrand}
      sidebarItems={collectorSidebarItems}
      navItems={collectorNavItems}
    >
      <div className="space-y-4 sm:space-y-6 select-none pb-20">
        {/* ── TOP DONUT & COLLECTION METRICS CONTAINER ── */}
        <div className="grid grid-cols-1 md:grid-cols-12 gap-3.5 sm:gap-4">
          {/* Chart Donut Overview (5 Cols) */}
          <div className="md:col-span-5 rounded-2xl border border-gray-200 bg-white p-4 sm:p-5 shadow-xs dark:border-gray-800 dark:bg-white/[0.03] flex items-center gap-4">
            <div className="h-28 w-28 shrink-0 flex items-center justify-center">
              <Chart options={donutOptions} series={donutSeries} type="donut" height={110} width={110} />
            </div>
            <div className="flex-1 min-w-0 space-y-2">
              <div className="flex items-center justify-between">
                <span className="text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                  Target & Koleksi Saya
                </span>
                <span className="inline-flex items-center gap-1 rounded-full bg-brand-500 text-white font-bold text-[10px] px-2 py-0.5 shadow-xs">
                  {stats.paidCount} / {stats.totalCustomers}
                </span>
              </div>
              <div>
                <p className="text-xs text-gray-500 dark:text-gray-400">Koleksi Saya Bulan Ini</p>
                <p className="text-base sm:text-lg font-black text-emerald-600 dark:text-emerald-400 truncate">
                  {formatIDR(stats.paidAmount || stats.myPaidAmount || 0)}
                </p>
              </div>
              <div className="flex items-center gap-3 pt-1 border-t border-gray-100 dark:border-gray-800 text-[11px]">
                <div className="flex items-center gap-1.5 min-w-0">
                  <span className="h-2 w-2 rounded-full bg-emerald-500 shrink-0" />
                  <span className="text-gray-600 dark:text-gray-300 truncate">{stats.paidCount} Lunas (Saya)</span>
                </div>
                <div className="flex items-center gap-1.5 min-w-0">
                  <span className="h-2 w-2 rounded-full bg-rose-500 shrink-0" />
                  <span className="text-gray-600 dark:text-gray-300 truncate">{stats.unpaidCount} Belum Lunas</span>
                </div>
              </div>
            </div>
          </div>

          {/* 3 Metric Summary Cards (7 Cols) */}
          <div className="md:col-span-7 grid grid-cols-1 sm:grid-cols-3 gap-3">
            {/* 1. Sisa Tertunggak */}
            <div className="rounded-2xl border border-gray-200 bg-white p-4 shadow-xs dark:border-gray-800 dark:bg-white/[0.03] flex flex-col justify-between">
              <div className="flex items-center justify-between">
                <span className="text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                  Sisa Tagihan
                </span>
                <div className="flex h-7 w-7 items-center justify-center rounded-lg bg-rose-500 text-white shadow-xs">
                  <Clock className="h-3.5 w-3.5" />
                </div>
              </div>
              <div className="mt-2">
                <p className="text-lg font-black text-rose-600 dark:text-rose-400 truncate">
                  {formatIDR(stats.unpaidAmount)}
                </p>
                <p className="text-[11px] font-bold text-gray-500 dark:text-gray-400 mt-0.5">
                  {stats.unpaidCount} invoice belum lunas
                </p>
              </div>
            </div>

            {/* 2. Koleksi Hari Ini */}
            <div className="rounded-2xl border border-gray-200 bg-white p-4 shadow-xs dark:border-gray-800 dark:bg-white/[0.03] flex flex-col justify-between">
              <div className="flex items-center justify-between">
                <span className="text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                  Koleksi Hari Ini
                </span>
                <div className="flex h-7 w-7 items-center justify-center rounded-lg bg-emerald-500 text-white shadow-xs">
                  <CheckCircle2 className="h-3.5 w-3.5" />
                </div>
              </div>
              <div className="mt-2">
                <p className="text-lg font-black text-emerald-600 dark:text-emerald-400 truncate">
                  {formatIDR(stats.todayPaidAmount)}
                </p>
                <p className="text-[11px] font-bold text-gray-500 dark:text-gray-400 mt-0.5">
                  {stats.todayPaidCount ?? todayPayments.length} transaksi hari ini
                </p>
              </div>
            </div>

            {/* 3. Petugas Kolektor Info */}
            <div className="rounded-2xl border border-gray-200 bg-white p-4 shadow-xs dark:border-gray-800 dark:bg-white/[0.03] flex flex-col justify-between">
              <div className="flex items-center justify-between">
                <span className="text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                  Petugas Penagih
                </span>
                <div className="flex h-7 w-7 items-center justify-center rounded-lg bg-brand-500 text-white shadow-xs">
                  <User className="h-3.5 w-3.5" />
                </div>
              </div>
              <div className="mt-2">
                <p className="text-sm font-black text-gray-900 dark:text-white truncate">
                  {collector.name}
                </p>
                <p className="text-[11px] font-bold text-brand-600 dark:text-brand-400 mt-0.5 truncate">
                  @{collector.username} {collector.router ? `• ${collector.router}` : ""}
                </p>
              </div>
            </div>
          </div>
        </div>

        {/* ── 1-LINE RESPONSIVE INTEGRATED TOOLBAR ── */}
        <div className="rounded-2xl border border-gray-200 bg-white p-3 sm:p-4 shadow-xs dark:border-gray-800 dark:bg-white/[0.03] flex flex-col gap-2.5 sm:gap-3 lg:flex-row lg:items-center lg:justify-between">
          <div className="flex items-center gap-2 sm:gap-3 flex-1 min-w-0">
            {/* Search Input */}
            <div className="relative flex-1 min-w-0">
              <Search className="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400 dark:text-gray-500" />
              <input
                type="text"
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                placeholder="Cari pelanggan, no invoice, telepon..."
                className="h-10 w-full rounded-xl border border-gray-200 bg-gray-50/75 pl-9 pr-8 text-xs text-gray-800 placeholder:text-gray-400 focus:border-brand-500 focus:outline-hidden dark:border-gray-800 dark:bg-gray-900/50 dark:text-gray-200 dark:placeholder:text-gray-500"
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

            {/* Unified Calendar Button */}
            <button
              type="button"
              onClick={() => setCalendarModalOpen(true)}
              className={cn(
                "inline-flex h-10 items-center justify-center gap-1.5 px-3 rounded-xl border text-xs font-semibold transition cursor-pointer shadow-2xs shrink-0",
                selectedDay || (selectedPeriod && selectedPeriod !== currentPeriodKey)
                  ? "border-brand-500 bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-400 font-bold"
                  : "border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:hover:bg-gray-800 text-gray-700 dark:text-gray-200"
              )}
            >
              <Calendar className="h-4 w-4" />
              <span className="hidden sm:inline">
                {selectedDay
                  ? `Tgl ${selectedDay} • ${periodLabel(selectedPeriod)}`
                  : periodLabel(selectedPeriod)}
              </span>
            </button>
          </div>

          <div className="flex items-center gap-2 w-full lg:w-auto justify-between lg:justify-end shrink-0">
            {/* Status Filter Dropdown */}
            <select
              value={statusFilter}
              onChange={(e) => setStatusFilter(e.target.value as "unpaid" | "paid")}
              className="h-10 px-3 py-2 text-xs font-semibold rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 text-gray-700 dark:text-gray-200 cursor-pointer shadow-2xs focus:ring-2 focus:ring-brand-500 focus:outline-hidden"
            >
              <option value="unpaid">Belum Bayar ({stats.unpaidCount})</option>
              <option value="paid">Lunas ({stats.paidCount})</option>
            </select>

            <div className="flex items-center gap-2 shrink-0">
              <ViewModeSwitcher value={viewMode} onChange={handleViewModeChange} storageKey="nodera_collector_invoices_view" size="sm" />

              {/* Filter Paket Modal */}
              <button
                type="button"
                onClick={() => setFilterModalOpen(true)}
                className={cn(
                  "inline-flex h-10 items-center justify-center gap-1.5 px-3 rounded-xl border text-xs font-semibold transition cursor-pointer shadow-2xs shrink-0",
                  packageFilter !== "all"
                    ? "border-brand-500 text-brand-500 font-bold bg-white dark:bg-gray-900"
                    : "border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:hover:bg-gray-800 text-gray-700 dark:text-gray-200"
                )}
              >
                <SlidersHorizontal className="h-4 w-4" />
                <span className="hidden sm:inline">Paket</span>
                {packageFilter !== "all" && (
                  <span className="h-2 w-2 rounded-full bg-brand-500" />
                )}
              </button>
            </div>
          </div>
        </div>

        {/* ── MASTER CONTAINER: TABLE VS GRID VIEW ── */}
        <div className="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xs dark:border-gray-800 dark:bg-white/[0.03]">
          {filteredCustomers.length === 0 ? (
            <div className="p-10 sm:p-16 text-center space-y-3">
              <Receipt className="mx-auto h-10 w-10 text-gray-400 dark:text-gray-600" />
              <p className="text-sm font-bold text-gray-900 dark:text-white">
                Tidak ada data invoice ditemukan
              </p>
              <p className="text-xs text-gray-500 dark:text-gray-400 max-w-sm mx-auto">
                {search || statusFilter !== "all" || packageFilter !== "all"
                  ? "Coba sesuaikan kata kunci pencarian atau reset filter untuk menampilkan data."
                  : "Belum ada tagihan pelanggan yang terdaftar pada periode ini."}
              </p>
            </div>
          ) : viewMode === "table" ? (
            <div className="w-full min-w-0">
              {/* Top Synchronized Scrollbar Runway */}
              <div
                ref={topScrollRef}
                onScroll={handleTopScroll}
                className="overflow-x-auto table-scrollbar w-full border-b border-gray-100 dark:border-gray-800 bg-gray-50/70 dark:bg-gray-900/50"
              >
                <div style={{ width: `${tableScrollWidth}px`, height: "8px" }} />
              </div>

              <div
                ref={tableScrollRef}
                onScroll={handleTableScroll}
                className="w-full overflow-x-auto table-scrollbar"
              >
                <table className="w-full min-w-[1000px] text-start text-xs whitespace-nowrap border-collapse">
                  <thead className="border-b border-gray-200 bg-gray-50/75 dark:border-gray-800 dark:bg-gray-800/50 text-gray-500 dark:text-gray-400 font-semibold">
                    <tr>
                      <th className="py-3 px-4 w-10 text-center">
                        <Checkbox
                          checked={selectedIds.length > 0 && selectedIds.length === unpaidItems.length}
                          onChange={(val: any) => handleSelectAll(typeof val === 'boolean' ? val : Boolean(val?.target?.checked))}
                          aria-label="Pilih Semua"
                        />
                      </th>
                      <th className="py-3 px-4 text-start font-bold">Pelanggan</th>
                      <th className="py-3 px-4 text-start font-bold">Paket / Layanan</th>
                      <th className="py-3 px-4 text-start font-bold">Periode & Jatuh Tempo</th>
                      <th className="py-3 px-4 text-end font-bold">Nominal</th>
                      <th className="py-3 px-4 text-center font-bold">Status Tagihan</th>
                      <th className="py-3 px-4 text-start font-bold">Penanggung Jawab</th>
                      <th className="py-3 px-4 text-end font-bold">Aksi</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-gray-100 dark:divide-gray-800 text-gray-700 dark:text-gray-300">
                    {filteredCustomers.map((item) => {
                      const inv = item.latest_invoice
                      const isPaid = inv?.paid ?? false
                      const isChecked = inv ? selectedIds.includes(inv.id) : false
                      const responsibleName = item.assigned_collector_name || inv?.assigned_collector_name || inv?.processed_by || collector.name

                      return (
                        <tr
                          key={item.id}
                          className={cn(
                            "hover:bg-gray-50/75 dark:hover:bg-gray-800/40 transition-colors",
                            isChecked && "bg-brand-50/40 dark:bg-brand-500/5"
                          )}
                        >
                          <td className="py-3 px-4 text-center">
                            {inv && !isPaid ? (
                              <Checkbox
                                checked={isChecked}
                                onChange={() => handleToggleSelect(inv.id)}
                              />
                            ) : (
                              <Check className="mx-auto h-4 w-4 text-emerald-500" />
                            )}
                          </td>
                          <td className="py-3 px-4">
                            <div className="flex flex-col">
                              <span className="font-bold text-gray-900 dark:text-white">
                                {item.name}
                              </span>
                              <div className="flex items-center gap-1.5 text-[11px] text-gray-500 dark:text-gray-400">
                                <span className="font-mono">{item.code}</span>
                                {item.phone && <span>• {item.phone}</span>}
                              </div>
                            </div>
                          </td>
                          <td className="py-3 px-4">
                            <div className="flex flex-col">
                              <span className="font-bold text-gray-900 dark:text-white">
                                {item.package || "-"}
                              </span>
                              <span className="text-[11px] text-gray-500 dark:text-gray-400">
                                {item.router || "Router Standar"}
                              </span>
                            </div>
                          </td>
                          <td className="py-3 px-4">
                            <div className="flex flex-col">
                              <span className="font-bold text-gray-900 dark:text-white">
                                {inv ? periodLabel(inv.period) : periodLabel(selectedPeriod)}
                              </span>
                              <span className="text-[11px] text-gray-500 dark:text-gray-400">
                                {inv?.due_date ? `Jatuh Tempo: ${inv.due_date}` : "-"}
                              </span>
                            </div>
                          </td>
                          <td className="py-3 px-4 text-end">
                            <span className="font-black text-sm text-gray-900 dark:text-white">
                              {inv ? formatIDR(inv.amount) : "-"}
                            </span>
                          </td>
                          <td className="py-3 px-4 text-center">
                            {isPaid ? (
                              <span className="inline-flex items-center gap-1 rounded-full bg-emerald-500 px-2.5 py-0.5 text-[11px] font-bold text-white shadow-xs">
                                <CheckCircle2 className="h-3 w-3" />
                                <span>Lunas</span>
                              </span>
                            ) : (
                              <span className="inline-flex items-center gap-1 rounded-full bg-rose-500 px-2.5 py-0.5 text-[11px] font-bold text-white shadow-xs">
                                <Clock className="h-3 w-3" />
                                <span>Belum Bayar</span>
                              </span>
                            )}
                          </td>
                          <td className="py-3 px-4">
                            <div className="flex items-center gap-1.5">
                              <span className="inline-flex items-center gap-1.5 rounded-lg bg-gray-100 dark:bg-gray-800 px-2 py-1 text-[11px] font-bold text-gray-800 dark:text-gray-200">
                                <User className="h-3 w-3 text-brand-500" />
                                <span>{responsibleName}</span>
                              </span>
                            </div>
                          </td>
                          <td className="py-3 px-4 text-end">
                            <div className="flex items-center justify-end gap-1.5">
                              {inv && (
                                <button
                                  type="button"
                                  onClick={() => handleSendWa(inv.id)}
                                  className="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-600 text-white shadow-xs hover:bg-emerald-700 transition"
                                  title="Kirim Notifikasi WhatsApp"
                                >
                                  <Send className="h-3.5 w-3.5" />
                                </button>
                              )}
                              <button
                                type="button"
                                onClick={() => handleOpenManagingModal(item)}
                                className="flex h-8 w-8 items-center justify-center rounded-lg bg-brand-500 text-white shadow-xs hover:bg-brand-600 transition"
                                title="Kelola & Bayar Tagihan"
                              >
                                <Receipt className="h-3.5 w-3.5" />
                              </button>
                            </div>
                          </td>
                        </tr>
                      )
                    })}
                  </tbody>
                </table>
              </div>
            </div>
          ) : (
            /* Grid Card View */
            <div className="p-4 sm:p-5 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3.5">
              {filteredCustomers.map((item) => {
                const inv = item.latest_invoice
                const isPaid = inv?.paid ?? false
                const isChecked = inv ? selectedIds.includes(inv.id) : false
                const responsibleName = item.assigned_collector_name || inv?.assigned_collector_name || inv?.processed_by || collector.name

                return (
                  <div
                    key={item.id}
                    className={cn(
                      "p-4 rounded-2xl border bg-white dark:bg-white/[0.03] space-y-3 transition shadow-xs flex flex-col justify-between",
                      isChecked
                        ? "border-brand-500 ring-1 ring-brand-500/40"
                        : isPaid
                        ? "border-gray-200 dark:border-gray-800"
                        : "border-rose-200 dark:border-rose-900/40"
                    )}
                  >
                    <div className="space-y-2.5">
                      <div className="flex items-start justify-between gap-2">
                        <div className="flex items-center gap-2 min-w-0">
                          {inv && !isPaid && (
                            <Checkbox
                              checked={isChecked}
                              onChange={() => handleToggleSelect(inv.id)}
                            />
                          )}
                          <div className="min-w-0">
                            <h4 className="font-bold text-sm text-gray-900 dark:text-white truncate">
                              {item.name}
                            </h4>
                            <p className="text-[11px] font-mono text-gray-500 dark:text-gray-400">
                              {item.code} {item.phone ? `• ${item.phone}` : ""}
                            </p>
                          </div>
                        </div>
                        {isPaid ? (
                          <span className="inline-flex items-center gap-1 rounded-full bg-emerald-500 px-2 py-0.5 text-[10px] font-bold text-white shadow-xs shrink-0">
                            Lunas
                          </span>
                        ) : (
                          <span className="inline-flex items-center gap-1 rounded-full bg-rose-500 px-2 py-0.5 text-[10px] font-bold text-white shadow-xs shrink-0">
                            Belum Bayar
                          </span>
                        )}
                      </div>

                      <div className="rounded-xl bg-gray-50 dark:bg-gray-900/60 p-2.5 space-y-1.5 text-xs">
                        <div className="flex justify-between items-center text-gray-600 dark:text-gray-300">
                          <span>Paket:</span>
                          <span className="font-bold text-gray-900 dark:text-white">{item.package || "-"}</span>
                        </div>
                        <div className="flex justify-between items-center text-gray-600 dark:text-gray-300">
                          <span>Periode:</span>
                          <span className="font-bold text-gray-900 dark:text-white">
                            {inv ? periodLabel(inv.period) : periodLabel(selectedPeriod)}
                          </span>
                        </div>
                        <div className="flex justify-between items-center text-gray-600 dark:text-gray-300">
                          <span>Nominal:</span>
                          <span className="font-black text-sm text-gray-900 dark:text-white">
                            {inv ? formatIDR(inv.amount) : "-"}
                          </span>
                        </div>
                      </div>

                      <div className="flex items-center justify-between text-[11px] text-gray-500 dark:text-gray-400 pt-1">
                        <span className="flex items-center gap-1">
                          <User className="h-3 w-3 text-brand-500" />
                          <strong className="text-gray-700 dark:text-gray-300">{responsibleName}</strong>
                        </span>
                        <span>{inv?.due_date ? `Due: ${inv.due_date}` : ""}</span>
                      </div>
                    </div>

                    <div className="flex items-center gap-2 pt-2 border-t border-gray-100 dark:border-gray-800">
                      {inv && (
                        <button
                          type="button"
                          onClick={() => handleSendWa(inv.id)}
                          className="flex-1 flex h-9 items-center justify-center gap-1 rounded-xl bg-emerald-600 text-white font-bold text-xs hover:bg-emerald-700 transition shadow-xs"
                        >
                          <Send className="h-3.5 w-3.5" />
                          <span>Kirim WA</span>
                        </button>
                      )}
                      <button
                        type="button"
                        onClick={() => handleOpenManagingModal(item)}
                        className="flex-1 flex h-9 items-center justify-center gap-1 rounded-xl bg-brand-500 text-white font-bold text-xs hover:bg-brand-600 transition shadow-xs"
                      >
                        <Receipt className="h-3.5 w-3.5" />
                        <span>{isPaid ? "Detail" : "Bayar"}</span>
                      </button>
                    </div>
                  </div>
                )
              })}
            </div>
          )}
        </div>

        {/* ── FLOATING BATCH PAYMENT BAR ── */}
        {selectedIds.length > 0 && (
          <div className="fixed bottom-6 left-1/2 -translate-x-1/2 z-50 flex items-center gap-3 rounded-2xl bg-gray-900/95 border border-gray-700 text-white px-5 py-3 shadow-2xl backdrop-blur-md animate-in fade-in slide-in-from-bottom-4 duration-200">
            <span className="text-xs font-bold">
              {selectedIds.length} invoice dipilih
            </span>
            <button
              type="button"
              onClick={handleProcessBatchPayment}
              disabled={isProcessing}
              className="flex items-center gap-1.5 rounded-xl bg-emerald-500 hover:bg-emerald-600 px-3.5 py-1.5 text-xs font-bold text-white shadow-xs transition active:scale-95 disabled:opacity-50 cursor-pointer"
            >
              <CheckCircle2 className="h-4 w-4" />
              <span>Bayar Sekaligus</span>
            </button>
            <button
              type="button"
              onClick={() => setSelectedIds([])}
              className="text-xs text-gray-400 hover:text-white"
            >
              Batal
            </button>
          </div>
        )}

        {/* ── UNIFIED CALENDAR MODAL (2 TABS: BULAN & TANGGAL 1-31) ── */}
        {calendarModalOpen && (
          <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-xs p-4 animate-in fade-in duration-150">
            <div className="relative w-full max-w-md rounded-2xl border border-gray-200 bg-white p-5 shadow-2xl dark:border-gray-800 dark:bg-gray-900 space-y-4 max-h-[92vh] overflow-y-auto">
              <div className="flex items-center justify-between border-b border-gray-100 dark:border-gray-800 pb-3">
                <div className="flex items-center gap-2">
                  <Calendar className="h-5 w-5 text-brand-500" />
                  <h3 className="font-bold text-base text-gray-900 dark:text-white">
                    Pilih Periode Tagihan
                  </h3>
                </div>
                <button
                  onClick={() => setCalendarModalOpen(false)}
                  className="rounded-lg p-1 text-gray-400 hover:text-gray-700 dark:hover:text-white"
                >
                  <X className="h-5 w-5" />
                </button>
              </div>

              {/* Tab Selector */}
              <div className="flex rounded-xl bg-gray-100 dark:bg-gray-800 p-1">
                <button
                  type="button"
                  onClick={() => setCalendarTab("month")}
                  className={cn(
                    "flex-1 py-1.5 rounded-lg text-xs font-bold transition",
                    calendarTab === "month"
                      ? "bg-white dark:bg-gray-900 text-gray-900 dark:text-white shadow-xs"
                      : "text-gray-500 hover:text-gray-900 dark:text-gray-400"
                  )}
                >
                  Pilihan Bulan
                </button>
                <button
                  type="button"
                  onClick={() => setCalendarTab("day")}
                  className={cn(
                    "flex-1 py-1.5 rounded-lg text-xs font-bold transition",
                    calendarTab === "day"
                      ? "bg-white dark:bg-gray-900 text-gray-900 dark:text-white shadow-xs"
                      : "text-gray-500 hover:text-gray-900 dark:text-gray-400"
                  )}
                >
                  Pilihan Tanggal (1-31)
                </button>
              </div>

              {/* Year Navigation */}
              <div className="flex items-center justify-between px-2">
                <button
                  type="button"
                  onClick={() => setCalendarYear((y) => y - 1)}
                  className="p-1 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 text-gray-600 dark:text-gray-300"
                >
                  <ChevronLeft className="h-4 w-4" />
                </button>
                <span className="font-black text-sm text-gray-900 dark:text-white">{calendarYear}</span>
                <button
                  type="button"
                  onClick={() => setCalendarYear((y) => y + 1)}
                  className="p-1 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 text-gray-600 dark:text-gray-300"
                >
                  <ChevronRight className="h-4 w-4" />
                </button>
              </div>

              {calendarTab === "month" ? (
                <div className="grid grid-cols-3 gap-2">
                  {MONTH_NAMES.map((mName, idx) => {
                    const pKey = `${calendarYear}-${String(idx + 1).padStart(2, "0")}`
                    const isSelected = selectedPeriod === pKey && !selectedDay
                    return (
                      <button
                        key={pKey}
                        type="button"
                        onClick={() => handleSelectPeriod(pKey)}
                        className={cn(
                          "py-2.5 px-2 rounded-xl text-xs font-bold transition border",
                          isSelected
                            ? "border-brand-500 bg-brand-500 text-white shadow-xs"
                            : "border-gray-200 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-800 text-gray-800 dark:text-gray-200"
                        )}
                      >
                        {mName}
                      </button>
                    )
                  })}
                </div>
              ) : (
                <div className="space-y-3">
                  <p className="text-xs text-gray-500 dark:text-gray-400 text-center">
                    Bulan: <strong>{MONTH_NAMES[selectedMonthIndex]} {calendarYear}</strong>
                  </p>
                  <div className="grid grid-cols-7 gap-1.5 text-center">
                    {Array.from({ length: 31 }, (_, i) => i + 1).map((dNum) => {
                      const dayStr = String(dNum).padStart(2, "0")
                      const isSelected = selectedDay === dayStr
                      return (
                        <button
                          key={dNum}
                          type="button"
                          onClick={() => handleSelectDay(dNum)}
                          className={cn(
                            "h-9 w-full rounded-xl text-xs font-bold transition border flex items-center justify-center",
                            isSelected
                              ? "border-brand-500 bg-brand-500 text-white shadow-xs"
                              : "border-gray-200 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-800 text-gray-800 dark:text-gray-200"
                          )}
                        >
                          {dNum}
                        </button>
                      )
                    })}
                  </div>
                </div>
              )}

              <div className="pt-2 border-t border-gray-100 dark:border-gray-800 flex justify-between items-center">
                <button
                  type="button"
                  onClick={() => handleSelectPeriod("all")}
                  className="text-xs font-bold text-brand-600 dark:text-brand-400 hover:underline"
                >
                  Tampilkan Semua Periode
                </button>
                <button
                  type="button"
                  onClick={() => setCalendarModalOpen(false)}
                  className="px-4 py-2 rounded-xl bg-gray-100 dark:bg-gray-800 text-xs font-bold text-gray-700 dark:text-gray-300"
                >
                  Tutup
                </button>
              </div>
            </div>
          </div>
        )}

        {/* ── FILTER PAKET MODAL ── */}
        {filterModalOpen && (
          <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-xs p-4 animate-in fade-in duration-150">
            <div className="relative w-full max-w-sm rounded-2xl border border-gray-200 bg-white p-5 shadow-2xl dark:border-gray-800 dark:bg-gray-900 space-y-4">
              <div className="flex items-center justify-between border-b border-gray-100 dark:border-gray-800 pb-3">
                <div className="flex items-center gap-2">
                  <SlidersHorizontal className="h-5 w-5 text-brand-500" />
                  <h3 className="font-bold text-base text-gray-900 dark:text-white">
                    Filter Berdasarkan Paket
                  </h3>
                </div>
                <button
                  onClick={() => setFilterModalOpen(false)}
                  className="rounded-lg p-1 text-gray-400 hover:text-gray-700 dark:hover:text-white"
                >
                  <X className="h-5 w-5" />
                </button>
              </div>

              <div className="space-y-2">
                <label className="text-xs font-bold text-gray-700 dark:text-gray-300">
                  Pilih Paket Layanan
                </label>
                <select
                  value={packageFilter}
                  onChange={(e) => setPackageFilter(e.target.value)}
                  className="h-10 w-full rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 px-3 text-xs font-bold text-gray-900 dark:text-white focus:outline-none focus:border-brand-500"
                >
                  <option value="all">Semua Paket ({packages.length})</option>
                  {packages.map((pkg) => (
                    <option key={pkg.id} value={pkg.name}>
                      {pkg.name}
                    </option>
                  ))}
                </select>
              </div>

              <div className="pt-3 border-t border-gray-100 dark:border-gray-800 flex justify-between items-center gap-2">
                <button
                  type="button"
                  onClick={() => {
                    setPackageFilter("all")
                    setFilterModalOpen(false)
                  }}
                  className="px-3.5 py-2 rounded-xl border border-gray-200 dark:border-gray-800 text-xs font-bold text-gray-700 dark:text-gray-300"
                >
                  Reset
                </button>
                <button
                  type="button"
                  onClick={() => setFilterModalOpen(false)}
                  className="px-4 py-2 rounded-xl bg-brand-500 text-white text-xs font-bold hover:bg-brand-600 shadow-xs"
                >
                  Terapkan
                </button>
              </div>
            </div>
          </div>
        )}

        {/* ── MODAL KELOLA & BAYAR TAGIHAN (FLAT & MULTI-PERIOD) ── */}
        {managingItem && (
          <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-xs p-4 animate-in fade-in duration-150">
            <div className="relative w-full max-w-lg rounded-2xl border border-gray-200 bg-white p-5 sm:p-6 shadow-2xl dark:border-gray-800 dark:bg-gray-900 space-y-4 max-h-[92vh] overflow-y-auto">
              <div className="flex items-center justify-between border-b border-gray-100 dark:border-gray-800 pb-3">
                <div className="flex items-center gap-2.5">
                  <div className="flex h-9 w-9 items-center justify-center rounded-xl bg-brand-500 text-white shadow-xs">
                    <Receipt className="h-5 w-5" />
                  </div>
                  <div>
                    <h3 className="font-bold text-base text-gray-900 dark:text-white">
                      {managingItem.name}
                    </h3>
                    <p className="text-xs text-gray-500 dark:text-gray-400 font-mono">
                      {managingItem.code} {managingItem.phone ? `• ${managingItem.phone}` : ""}
                    </p>
                  </div>
                </div>
                <button
                  onClick={() => setManagingItem(null)}
                  className="rounded-lg p-1 text-gray-400 hover:text-gray-700 dark:hover:text-white"
                >
                  <X className="h-5 w-5" />
                </button>
              </div>

              {/* Status and Responsible Information Box */}
              <div className="rounded-xl border border-gray-200 bg-gray-50/75 p-3.5 dark:border-gray-800 dark:bg-gray-800/40 space-y-2 text-xs">
                <div className="flex justify-between items-center">
                  <span className="text-gray-500 dark:text-gray-400">Status Tagihan:</span>
                  {managingItem.latest_invoice?.paid ? (
                    <span className="inline-flex items-center gap-1 rounded-full bg-emerald-500 px-2.5 py-0.5 text-[11px] font-bold text-white shadow-xs">
                      Lunas
                    </span>
                  ) : (
                    <span className="inline-flex items-center gap-1 rounded-full bg-rose-500 px-2.5 py-0.5 text-[11px] font-bold text-white shadow-xs">
                      Belum Lunas
                    </span>
                  )}
                </div>
                <div className="flex justify-between items-center">
                  <span className="text-gray-500 dark:text-gray-400">Penanggung Jawab:</span>
                  <span className="font-bold text-gray-900 dark:text-white flex items-center gap-1">
                    <User className="h-3.5 w-3.5 text-brand-500" />
                    <span>
                      {managingItem.assigned_collector_name ||
                        managingItem.latest_invoice?.assigned_collector_name ||
                        managingItem.latest_invoice?.processed_by ||
                        collector.name}
                    </span>
                  </span>
                </div>
                <div className="flex justify-between items-center">
                  <span className="text-gray-500 dark:text-gray-400">Paket & Router:</span>
                  <span className="font-bold text-gray-900 dark:text-white">
                    {managingItem.package || "-"} • {managingItem.router || "Standar"}
                  </span>
                </div>
                {managingItem.address && (
                  <div className="flex justify-between items-start gap-2 pt-1 border-t border-gray-200 dark:border-gray-700">
                    <span className="text-gray-500 dark:text-gray-400 shrink-0">Alamat:</span>
                    <span className="font-medium text-gray-700 dark:text-gray-300 text-right">
                      {managingItem.address}
                    </span>
                  </div>
                )}
              </div>

              {/* Multi-Period Selection if Unpaid */}
              {managingItem.latest_invoice && !managingItem.latest_invoice.paid && (
                <div className="space-y-3">
                  <label className="text-xs font-bold text-gray-900 dark:text-white">
                    Pilihan Periode Tagihan:
                  </label>
                  <div className="space-y-2">
                    {(() => {
                      const inv = managingItem.latest_invoice!
                      let periodsList: BreakdownPeriod[] = []
                      if (inv.periods_breakdown) {
                        try {
                          const parsed = typeof inv.periods_breakdown === "string"
                            ? JSON.parse(inv.periods_breakdown)
                            : inv.periods_breakdown
                          if (Array.isArray(parsed) && parsed.length > 0) {
                            periodsList = parsed
                          }
                        } catch {}
                      }
                      if (periodsList.length === 0) {
                        periodsList = [{ period: inv.period, amount: inv.amount }]
                      }

                      return periodsList.map((p) => {
                        const isSelected = selectedPeriods.includes(p.period)
                        return (
                          <label
                            key={p.period}
                            className={cn(
                              "flex items-center justify-between p-3 rounded-xl border transition cursor-pointer text-xs",
                              isSelected
                                ? "border-brand-500 bg-brand-50/50 dark:bg-brand-500/10 font-bold"
                                : "border-gray-200 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-800"
                            )}
                          >
                            <div className="flex items-center gap-2.5">
                              <Checkbox
                                checked={isSelected}
                                onChange={(val: any) => {
                                  const isChecked = typeof val === 'boolean' ? val : Boolean(val?.target?.checked)
                                  if (isChecked) {
                                    setSelectedPeriods((prev) => [...prev, p.period])
                                  } else {
                                    setSelectedPeriods((prev) => prev.filter((itemKey) => itemKey !== p.period))
                                  }
                                }}
                              />
                              <span className="text-gray-900 dark:text-white">
                                Periode {periodLabel(p.period)}
                              </span>
                            </div>
                            <span className="font-black text-gray-900 dark:text-white">
                              {formatIDR(p.amount)}
                            </span>
                          </label>
                        )
                      })
                    })()}
                  </div>

                  {/* Payment Method Selector */}
                  <div className="space-y-1.5 pt-1">
                    <label className="text-xs font-bold text-gray-900 dark:text-white">
                      Metode Pembayaran:
                    </label>
                    <div className="grid grid-cols-2 gap-2">
                      <button
                        type="button"
                        onClick={() => setPaymentMethod("cash")}
                        className={cn(
                          "py-2 px-3 rounded-xl border text-xs font-bold transition flex items-center justify-center gap-1.5",
                          paymentMethod === "cash"
                            ? "border-emerald-500 bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300"
                            : "border-gray-200 dark:border-gray-800 text-gray-700 dark:text-gray-300"
                        )}
                      >
                        <DollarSign className="h-4 w-4" />
                        <span>Tunai (Cash)</span>
                      </button>
                      <button
                        type="button"
                        onClick={() => setPaymentMethod("transfer")}
                        className={cn(
                          "py-2 px-3 rounded-xl border text-xs font-bold transition flex items-center justify-center gap-1.5",
                          paymentMethod === "transfer"
                            ? "border-brand-500 bg-brand-50 text-brand-700 dark:bg-brand-950/40 dark:text-brand-300"
                            : "border-gray-200 dark:border-gray-800 text-gray-700 dark:text-gray-300"
                        )}
                      >
                        <CreditCard className="h-4 w-4" />
                        <span>Transfer / QRIS</span>
                      </button>
                    </div>
                  </div>

                  {/* Send WA Switch */}
                  <label className="flex items-center gap-2 text-xs font-semibold text-gray-700 dark:text-gray-300 cursor-pointer pt-1">
                    <Checkbox
                      checked={sendWaNotif}
                      onChange={(val: any) => setSendWaNotif(typeof val === 'boolean' ? val : Boolean(val?.target?.checked))}
                    />
                    <span>Kirim struk / tanda terima via WhatsApp ke pelanggan</span>
                  </label>
                </div>
              )}

              {/* Action Buttons */}
              <div className="pt-3 border-t border-gray-100 dark:border-gray-800 flex items-center gap-2">
                {managingItem.latest_invoice && (
                  <>
                    <a
                      href={`/kolektor/invoices/print/${managingItem.latest_invoice.id}`}
                      target="_blank"
                      rel="noopener noreferrer"
                      className="flex h-10 items-center justify-center gap-1.5 px-3 rounded-xl border border-gray-200 dark:border-gray-800 text-gray-700 dark:text-gray-200 text-xs font-bold hover:bg-gray-50 dark:hover:bg-gray-800 transition"
                      title="Cetak Struk"
                    >
                      <Printer className="h-4 w-4" />
                      <span className="hidden sm:inline">Cetak</span>
                    </a>
                    <button
                      type="button"
                      onClick={() => handleSendWa(managingItem.latest_invoice!.id)}
                      className="flex h-10 items-center justify-center gap-1.5 px-3 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold transition shadow-xs"
                      title="Kirim Notifikasi WA"
                    >
                      <Send className="h-4 w-4" />
                      <span className="hidden sm:inline">Kirim WA</span>
                    </button>
                  </>
                )}

                {managingItem.latest_invoice && !managingItem.latest_invoice.paid ? (
                  <button
                    type="button"
                    disabled={isProcessing || selectedPeriods.length === 0}
                    onClick={handleProcessPayment}
                    className="flex-1 flex h-10 items-center justify-center gap-1.5 rounded-xl bg-emerald-500 hover:bg-emerald-600 text-white text-xs font-bold shadow-xs transition active:scale-95 disabled:opacity-50 ml-auto"
                  >
                    <CheckCircle2 className="h-4 w-4" />
                    <span>
                      {isProcessing ? "Memproses..." : "Konfirmasi Pembayaran"}
                    </span>
                  </button>
                ) : (
                  <button
                    type="button"
                    onClick={() => setManagingItem(null)}
                    className="flex-1 flex h-10 items-center justify-center rounded-xl bg-gray-100 dark:bg-gray-800 text-xs font-bold text-gray-700 dark:text-gray-300 ml-auto"
                  >
                    Tutup
                  </button>
                )}
              </div>
            </div>
          </div>
        )}
      </div>
    </AppLayout>
  )
}
