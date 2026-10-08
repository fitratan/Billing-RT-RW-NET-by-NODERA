import React, { useState, useEffect, useRef, useMemo } from "react"
import { AppLayout } from "@/components/layout/app-layout"
import Chart from "react-apexcharts"
import type { ApexOptions } from "apexcharts"
import {
  Wallet,
  Plus,
  Pencil,
  Trash2,
  Search,
  X,
  ChevronLeft,
  ChevronRight,
  TrendingDown,
  Clock,
  FileText,
  Calendar,
  CreditCard,
  Building,
  Settings,
  PieChart,
  Activity,
} from "lucide-react"
import { PageProps } from "@/types"
import { formatIDR, formatDate, cn } from "@/lib/utils"
import { adminSidebarItems, adminNavItems, adminBrand } from "@/lib/admin-nav"
import { router, useForm, Link } from "@inertiajs/react"
import MetricCard from "@/components/tailadmin/MetricCard"
import Modal from "@/components/tailadmin/Modal"
import { ViewModeSwitcher, type ViewMode } from "@/components/ui/view-mode-switcher"
import { EmptyState } from "@/components/ui/empty-state"

interface Expense {
  id: number
  description: string
  category: string | null
  amount: number
  date: string | null
  notes: string | null
  vendor: string | null
  payment_method?: string | null
}

const MONTH_NAMES = [
  "Januari", "Februari", "Maret", "April", "Mei", "Juni",
  "Juli", "Agustus", "September", "Oktober", "November", "Desember"
]

export default function AdminExpensesPage({
  expenses,
  totalFiltered = 0,
  totalThisMonth = 0,
  totalAll: _totalAll = 0,
  categories = [],
  year: initialYear = new Date().getFullYear(),
  month: initialMonth = new Date().getMonth() + 1,
  selectedPeriod: initialPeriod,
  selectedDay: initialDay,
  selectedDate: initialDate,
  startDate = "",
  endDate = "",
  category = "",
  search = "",
  companyName = "NODERA Billing",
  tenantName,
}: PageProps<{
  expenses: {
    data: Expense[]
    pagination: {
      current_page: number
      last_page: number
      total: number
      next_page_url?: string | null
      prev_page_url?: string | null
    }
  }
  totalFiltered: number
  totalThisMonth: number
  totalAll: number
  categories: string[]
  year: number
  month: number
  selectedPeriod?: string
  selectedDay?: string | number
  selectedDate?: string
  startDate: string
  endDate: string
  category: string
  search: string
  companyName?: string
  tenantName?: string
}>) {
  const [modalOpen, setModalOpen] = useState(false)
  const [editingExpense, setEditingExpense] = useState<Expense | null>(null)
  const [managingExpense, setManagingExpense] = useState<Expense | null>(null)
  const [viewMode, setViewMode] = useState<ViewMode>("table")

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

  const [filterCategory, setFilterCategory] = useState(category || "")
  const [filterSearch, setFilterSearch] = useState(search || "")
  const isFirstRender = useRef(true)

  useEffect(() => {
    if (initialPeriod) setSelectedPeriod(initialPeriod)
    setSelectedDay(initialDay && initialDay !== "all" ? initialDay : null)
    setSelectedDate(initialDate || null)
    if (initialPeriod && initialPeriod !== "all") {
      const parts = initialPeriod.split("-")
      if (parts.length >= 1) setCalendarYear(parseInt(parts[0], 10))
    }
  }, [initialPeriod, initialDay, initialDate])

  useEffect(() => {
    if (isFirstRender.current) {
      isFirstRender.current = false
      return
    }
    const timeout = setTimeout(() => {
      router.get(
        "/admin/expenses",
        {
          period: selectedPeriod || undefined,
          day: selectedDay || undefined,
          date: selectedDate || undefined,
          category: filterCategory || undefined,
          search: filterSearch || undefined,
        },
        { preserveState: true, preserveScroll: true, replace: true }
      )
    }, 300)
    return () => clearTimeout(timeout)
  }, [filterSearch, filterCategory])

  const handleSelectPeriod = (p: string | null) => {
    setSelectedPeriod(p || "all")
    setSelectedDay(null)
    setSelectedDate(null)
    setCalendarOpen(false)

    router.get(
      "/admin/expenses",
      {
        period: p || "all",
        day: "all",
        category: filterCategory || undefined,
        search: filterSearch || undefined,
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
    const curMonth = selectedPeriod && selectedPeriod !== "all" ? parseInt(selectedPeriod.split("-")[1], 10) : new Date().getMonth() + 1
    const pStr = `${curYear}-${String(curMonth).padStart(2, "0")}`

    if (!dayNum) {
      setSelectedDay(null)
      setSelectedDate(null)
      setCalendarOpen(false)
      router.get(
        "/admin/expenses",
        {
          period: pStr,
          day: "all",
          year: curYear,
          month: curMonth,
          category: filterCategory || undefined,
          search: filterSearch || undefined,
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
      "/admin/expenses",
      {
        period: pStr,
        day: dayNum,
        date: dStr,
        year: curYear,
        month: curMonth,
        category: filterCategory || undefined,
        search: filterSearch || undefined,
      },
      { preserveState: true, preserveScroll: true, replace: true }
    )
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

  const form = useForm({
    description: "",
    amount: "" as string | number,
    category: "Operasional",
    date: new Date().toISOString().slice(0, 10),
    notes: "",
    vendor: "",
    payment_method: "cash",
  })

  const openCreateModal = () => {
    setEditingExpense(null)
    form.setData({
      description: "",
      amount: "",
      category: "Operasional",
      date: new Date().toISOString().slice(0, 10),
      notes: "",
      vendor: "",
      payment_method: "cash",
    })
    setModalOpen(true)
  }

  const openEditModal = (exp: Expense) => {
    setEditingExpense(exp)
    form.setData({
      description: exp.description,
      amount: exp.amount,
      category: exp.category || "Operasional",
      date: exp.date || new Date().toISOString().slice(0, 10),
      notes: exp.notes || "",
      vendor: exp.vendor || "",
      payment_method: exp.payment_method || "cash",
    })
    setModalOpen(true)
  }

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    if (editingExpense) {
      form.post(`/admin/expenses/edit/${editingExpense.id}`, {
        preserveScroll: true,
        onSuccess: () => {
          setModalOpen(false)
          setManagingExpense(null)
          form.reset()
        },
      })
    } else {
      form.post("/admin/expenses/add", {
        preserveScroll: true,
        onSuccess: () => {
          setModalOpen(false)
          form.reset()
        },
      })
    }
  }

  const handleDelete = (exp: Expense) => {
    if (confirm(`Yakin ingin menghapus catatan pengeluaran "${exp.description}"?`)) {
      router.post(`/admin/expenses/delete/${exp.id}`, {}, {
        preserveScroll: true,
        onSuccess: () => setManagingExpense(null),
      })
    }
  }

  const getCategoryBadgeColor = (cat: string | null) => {
    switch ((cat || "").toLowerCase()) {
      case "operasional":
        return "bg-brand-500 text-white font-bold px-2 py-0.5 rounded-md text-[11px] whitespace-nowrap shadow-xs"
      case "bandwidth":
      case "backbone":
        return "bg-purple-600 text-white font-bold px-2 py-0.5 rounded-md text-[11px] whitespace-nowrap shadow-xs"
      case "gaji":
      case "komisi":
        return "bg-emerald-500 text-white font-bold px-2 py-0.5 rounded-md text-[11px] whitespace-nowrap shadow-xs"
      case "peralatan":
      case "material":
        return "bg-amber-500 text-white font-bold px-2 py-0.5 rounded-md text-[11px] whitespace-nowrap shadow-xs"
      case "pemeliharaan":
        return "bg-sky-500 text-white font-bold px-2 py-0.5 rounded-md text-[11px] whitespace-nowrap shadow-xs"
      default:
        return "bg-gray-500 text-white font-bold px-2 py-0.5 rounded-md text-[11px] whitespace-nowrap shadow-xs"
    }
  }

  const totalCount = expenses.pagination?.total ?? expenses.data?.length ?? 0

  // ── CATEGORY BREAKDOWN DONUT CHART ──
  const categoryBreakdown = useMemo(() => {
    const map = new Map<string, number>()
    expenses.data.forEach((e) => {
      const cat = e.category?.trim() || "Operasional"
      map.set(cat, (map.get(cat) || 0) + (Number(e.amount) || 0))
    })
    const sorted = Array.from(map.entries()).sort((a, b) => b[1] - a[1])
    return {
      labels: sorted.length > 0 ? sorted.map(([k]) => k) : ["Operasional"],
      series: sorted.length > 0 ? sorted.map(([, v]) => v) : [0],
    }
  }, [expenses.data])

  const donutOptions: ApexOptions = useMemo(() => ({
    chart: { type: "donut", fontFamily: "inherit" },
    labels: categoryBreakdown.labels,
    colors: ["#0073C6", "#8B5CF6", "#F59E0B", "#10B981", "#EC4899", "#6366F1", "#14B8A6"],
    legend: { position: "bottom", labels: { colors: "#64748B" } },
    dataLabels: { enabled: true, formatter: (val) => `${Number(val).toFixed(1)}%` },
    stroke: { width: 2, colors: ["transparent"] },
    plotOptions: {
      pie: {
        donut: {
          size: "68%",
          labels: {
            show: true,
            total: {
              show: true,
              label: "Total Beban",
              color: "#64748B",
              formatter: () => formatIDR(totalFiltered || totalThisMonth || categoryBreakdown.series.reduce((a, b) => a + b, 0)),
            },
          },
        },
      },
    },
    tooltip: {
      theme: "light",
      y: { formatter: (val) => formatIDR(val) },
    },
  }), [categoryBreakdown, totalFiltered, totalThisMonth])

  // ── TIMELINE AREA CHART ──
  const timelineData = useMemo(() => {
    const map = new Map<string, number>()
    const sorted = [...expenses.data].sort((a, b) => (a.date || "").localeCompare(b.date || ""))
    sorted.forEach((e) => {
      const d = e.date ? e.date.slice(5) : "Hari ini"
      map.set(d, (map.get(d) || 0) + (Number(e.amount) || 0))
    })
    const categories = Array.from(map.keys())
    const values = Array.from(map.values())
    return {
      categories: categories.length > 0 ? categories : ["Hari ini"],
      values: values.length > 0 ? values : [0],
    }
  }, [expenses.data])

  const timelineOptions: ApexOptions = useMemo(() => ({
    chart: { type: "area", toolbar: { show: false }, fontFamily: "inherit" },
    colors: ["#0073C6"],
    fill: {
      type: "gradient",
      gradient: { shadeIntensity: 1, opacityFrom: 0.45, opacityTo: 0.05, stops: [0, 95, 100] },
    },
    dataLabels: { enabled: false },
    stroke: { curve: "smooth", width: 3 },
    xaxis: {
      categories: timelineData.categories,
      labels: { style: { colors: "#64748B", fontSize: "11px" } },
      axisBorder: { show: false },
      axisTicks: { show: false },
    },
    yaxis: {
      labels: {
        style: { colors: "#64748B", fontSize: "11px" },
        formatter: (v) => formatIDR(v),
      },
    },
    grid: { borderColor: "#F1F5F9", strokeDashArray: 4 },
    tooltip: {
      theme: "light",
      y: { formatter: (v) => formatIDR(v) },
    },
  }), [timelineData])

  return (
    <AppLayout
      title="Pengeluaran Kas"
      brand={adminBrand}
      sidebarItems={adminSidebarItems}
      navItems={adminNavItems}
    >
      <div className="space-y-4 sm:space-y-6">
        {/* Top Row: Purpose-Built 3 MetricCards */}
        <div className="grid grid-cols-1 sm:grid-cols-3 gap-3 sm:gap-4">
          <MetricCard
            title="Biaya Bulan Ini"
            value={formatIDR(totalThisMonth)}
            sub="Kas keluar bulan berjalan"
            icon={<Clock className="h-5 w-5 sm:h-6 sm:w-6 text-brand-500" />}
            iconBgColor="bg-brand-50 dark:bg-brand-500/10"
            iconColor="text-brand-500 dark:text-brand-400"
          />
          <MetricCard
            title="Total Terfilter"
            value={formatIDR(totalFiltered)}
            sub="Sesuai rentang & kriteria"
            icon={<TrendingDown className="h-5 w-5 sm:h-6 sm:w-6 text-rose-500" />}
            iconBgColor="bg-rose-50 dark:bg-rose-500/10"
            iconColor="text-rose-500 dark:text-rose-400"
          />
          <MetricCard
            title="Total Transaksi"
            value={`${totalCount} Transaksi`}
            sub="Jumlah bukti kas keluar"
            icon={<FileText className="h-5 w-5 sm:h-6 sm:w-6 text-emerald-500" />}
            iconBgColor="bg-emerald-50 dark:bg-emerald-500/10"
            iconColor="text-emerald-500 dark:text-emerald-400"
          />
        </div>

        {/* ── CHARTS SECTION: DONUT & TIMELINE ── */}
        <div className="grid grid-cols-1 lg:grid-cols-12 gap-4 sm:gap-6">
          {/* Timeline Spline Chart */}
          <div className="lg:col-span-7 rounded-2xl border border-gray-200 bg-white p-4 sm:p-5 shadow-xs dark:border-gray-800 dark:bg-white/[0.03]">
            <div className="flex items-center justify-between gap-2 mb-3 sm:mb-4">
              <div className="flex items-center gap-2">
                <div className="flex h-8 w-8 items-center justify-center rounded-xl bg-brand-50 text-brand-500 dark:bg-brand-500/10">
                  <Activity className="h-4 w-4" />
                </div>
                <div>
                  <h3 className="text-sm sm:text-base font-bold text-gray-800 dark:text-white/90">
                    Tren Pengeluaran Kas
                  </h3>
                  <p className="text-[11px] text-gray-500 dark:text-gray-400">
                    Fluktuasi kas keluar riil berdasarkan tanggal transaksi
                  </p>
                </div>
              </div>
            </div>
            <div className="h-[220px] sm:h-[240px] w-full">
              <Chart
                options={timelineOptions}
                series={[{ name: "Pengeluaran", data: timelineData.values }]}
                type="area"
                height="100%"
                width="100%"
              />
            </div>
          </div>

          {/* Donut Chart Category Breakdown */}
          <div className="lg:col-span-5 rounded-2xl border border-gray-200 bg-white p-4 sm:p-5 shadow-xs dark:border-gray-800 dark:bg-white/[0.03]">
            <div className="flex items-center justify-between gap-2 mb-3 sm:mb-4">
              <div className="flex items-center gap-2">
                <div className="flex h-8 w-8 items-center justify-center rounded-xl bg-purple-50 text-purple-600 dark:bg-purple-500/10">
                  <PieChart className="h-4 w-4" />
                </div>
                <div>
                  <h3 className="text-sm sm:text-base font-bold text-gray-800 dark:text-white/90">
                    Komposisi Beban Biaya
                  </h3>
                  <p className="text-[11px] text-gray-500 dark:text-gray-400">
                    Proporsi per kategori operasional ISP
                  </p>
                </div>
              </div>
            </div>
            <div className="h-[220px] sm:h-[240px] w-full flex items-center justify-center">
              <Chart
                options={donutOptions}
                series={categoryBreakdown.series}
                type="donut"
                height="100%"
                width="100%"
              />
            </div>
          </div>
        </div>

        {/* ── 1-LINE RESPONSIVE INTEGRATED TOOLBAR ── */}
        <div className="rounded-2xl border border-gray-200 bg-white p-3 sm:p-4 shadow-xs dark:border-gray-800 dark:bg-white/[0.03] flex flex-col gap-2.5 sm:gap-3 lg:flex-row lg:items-center lg:justify-between">
          {/* Search & Standard Calendar Button */}
          <div className="flex items-center gap-2 sm:gap-3 flex-1 min-w-0">
            {/* Search */}
            <div className="relative flex-1 min-w-[150px]">
              <Search className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-gray-400" />
              <input
                type="text"
                value={filterSearch}
                onChange={(e) => setFilterSearch(e.target.value)}
                placeholder="Cari deskripsi / vendor..."
                className="h-10 w-full rounded-xl border border-gray-200 bg-gray-50/75 dark:border-gray-800 dark:bg-gray-900/50 pl-9 pr-8 text-xs font-medium text-gray-900 dark:text-white placeholder:text-gray-400 focus:border-brand-500 focus:outline-hidden"
              />
              {filterSearch && (
                <button
                  type="button"
                  onClick={() => setFilterSearch("")}
                  className="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-white cursor-pointer"
                >
                  <X className="size-3.5" />
                </button>
              )}
            </div>

            {/* Standard Calendar Icon Button */}
            <button
              type="button"
              onClick={() => setCalendarOpen(true)}
              title={
                selectedDay && selectedDay !== "all"
                  ? `Filter Tanggal ${selectedDay}${selectedPeriod && selectedPeriod !== "all" ? ` • ${periodLabel(selectedPeriod)}` : ""}`
                  : selectedPeriod && selectedPeriod !== "all"
                  ? `Filter Bulan: ${periodLabel(selectedPeriod)}`
                  : "Pilih Bulan / Tanggal Pengeluaran"
              }
              className="inline-flex h-10 items-center justify-center gap-1.5 px-3 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:hover:bg-gray-800 text-gray-700 dark:text-gray-200 transition cursor-pointer shadow-2xs text-xs font-semibold shrink-0"
            >
              <Calendar className="h-4 w-4 shrink-0 text-brand-500" />
              <span className="hidden sm:inline">
                {selectedDay && selectedDay !== "all"
                  ? `Tgl ${selectedDay}${selectedPeriod && selectedPeriod !== "all" ? ` • ${periodLabel(selectedPeriod)}` : ""}`
                  : (selectedPeriod && selectedPeriod !== "all")
                  ? periodLabel(selectedPeriod)
                  : "Semua Bulan"}
              </span>
            </button>
          </div>

          {/* Category Filter & Action Button */}
          <div className="flex items-center gap-2 shrink-0 w-full lg:w-auto justify-between lg:justify-end flex-wrap sm:flex-nowrap">
            <ViewModeSwitcher
              value={viewMode}
              onChange={setViewMode}
              storageKey="nodera_admin_expenses_view"
              size="sm"
            />

            <select
              value={filterCategory}
              onChange={(e) => setFilterCategory(e.target.value)}
              className="h-10 rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 px-3 text-xs font-semibold text-gray-700 dark:text-gray-200 cursor-pointer flex-1 sm:flex-initial shrink-0 focus:border-brand-500 focus:outline-hidden shadow-2xs"
            >
              <option value="">Semua Kategori</option>
              {categories.map((c) => (
                <option key={c} value={c}>
                  {c}
                </option>
              ))}
            </select>

            <button
              type="button"
              onClick={openCreateModal}
              className="h-10 px-3.5 sm:px-4 rounded-xl bg-brand-500 hover:bg-brand-600 active:scale-95 text-xs font-bold text-white shadow-xs flex items-center justify-center gap-1.5 shrink-0 cursor-pointer transition"
            >
              <Plus className="size-4 shrink-0" />
              <span>Catat Pengeluaran</span>
            </button>
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
                      Filter Waktu Pengeluaran
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
                          Pilih Tanggal Pengeluaran (1 - 31)
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

        {/* Master Card with Table */}
        <div className="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xs dark:border-gray-800 dark:bg-white/[0.03]">

          {/* Table Data */}
          {expenses.data.length === 0 ? (
            <div className="p-8 sm:p-12 text-center">
              <EmptyState
                icon={<Wallet className="h-8 w-8 text-brand-500" />}
                title="Belum ada catatan pengeluaran"
                description={
                  search || category || startDate || endDate
                    ? "Tidak ada data pengeluaran yang cocok dengan kriteria filter."
                    : "Catat biaya operasional, gaji, bandwidth, atau perbaikan jaringan Anda."
                }
              />
            </div>
          ) : viewMode === "table" ? (
            <div className="w-full overflow-x-auto custom-scrollbar">
              <table className="w-full text-left text-xs min-w-[950px] border-collapse">
                <thead className="border-b border-gray-200 bg-gray-50/75 dark:border-gray-800 dark:bg-white/[0.02]">
                  <tr>
                    <th className="px-4 py-3.5 font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                      Tanggal
                    </th>
                    <th className="px-4 py-3.5 font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                      Deskripsi Pengeluaran
                    </th>
                    <th className="px-4 py-3.5 font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                      Kategori
                    </th>
                    <th className="px-4 py-3.5 font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                      Metode
                    </th>
                    <th className="px-4 py-3.5 font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                      Vendor / Penerima
                    </th>
                    <th className="px-4 py-3.5 text-right font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                      Nominal
                    </th>
                    <th className="px-4 py-3.5 text-center font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                      Aksi
                    </th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-gray-200 dark:divide-gray-800">
                  {expenses.data.map((exp: Expense) => (
                    <tr
                      key={exp.id}
                      className="hover:bg-gray-50/80 transition-colors dark:hover:bg-white/[0.02]"
                    >
                      <td className="whitespace-nowrap px-4 py-3.5 font-medium text-gray-600 dark:text-gray-400">
                        {exp.date ? formatDate(exp.date) : "-"}
                      </td>
                      <td className="px-4 py-3.5">
                        <div className="font-semibold text-gray-900 dark:text-white">
                          {exp.description}
                        </div>
                        {exp.notes && (
                          <div className="mt-0.5 text-[11px] text-gray-500 dark:text-gray-400">
                            {exp.notes}
                          </div>
                        )}
                      </td>
                      <td className="whitespace-nowrap px-4 py-3.5">
                        <span
                          className={cn(
                            "inline-flex items-center shadow-xs",
                            getCategoryBadgeColor(exp.category)
                          )}
                        >
                          {exp.category || "Operasional"}
                        </span>
                      </td>
                      <td className="whitespace-nowrap px-4 py-3.5 uppercase font-medium text-gray-600 dark:text-gray-300 text-[11px]">
                        {exp.payment_method || "cash"}
                      </td>
                      <td className="whitespace-nowrap px-4 py-3.5 text-gray-600 dark:text-gray-400">
                        {exp.vendor || "-"}
                      </td>
                      <td className="whitespace-nowrap px-4 py-3.5 text-right font-mono font-bold text-rose-600 dark:text-rose-400">
                        -{formatIDR(exp.amount)}
                      </td>
                      <td className="whitespace-nowrap px-4 py-3.5 text-center">
                        <button
                          type="button"
                          onClick={() => setManagingExpense(exp)}
                          className="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-brand-500 hover:bg-brand-600 active:scale-95 text-white shadow-xs transition cursor-pointer"
                          title="Kelola Pengeluaran"
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
              {expenses.data.map((exp: Expense) => (
                <div
                  key={exp.id}
                  className="rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-4 flex flex-col justify-between gap-3 shadow-xs hover:border-gray-300 dark:hover:border-gray-700 transition"
                >
                  <div className="flex items-start justify-between gap-2">
                    <div>
                      <div className="font-bold text-gray-900 dark:text-white text-sm line-clamp-1">
                        {exp.description}
                      </div>
                      <div className="text-[11px] text-gray-400 mt-0.5">
                        {exp.date ? formatDate(exp.date) : "-"}
                      </div>
                    </div>
                    <span
                      className={cn(
                        "inline-flex items-center text-[10px] shadow-xs shrink-0",
                        getCategoryBadgeColor(exp.category)
                      )}
                    >
                      {exp.category || "Operasional"}
                    </span>
                  </div>

                  <div className="grid grid-cols-2 gap-2 text-[11px] border-y border-gray-100 dark:border-gray-800/80 py-2.5">
                    <div>
                      <span className="text-gray-400 block text-[10px]">Vendor</span>
                      <span className="font-medium text-gray-700 dark:text-gray-300 line-clamp-1">
                        {exp.vendor || "-"}
                      </span>
                    </div>
                    <div>
                      <span className="text-gray-400 block text-[10px]">Metode</span>
                      <span className="font-semibold uppercase text-gray-700 dark:text-gray-300 text-[10px]">
                        {exp.payment_method || "cash"}
                      </span>
                    </div>
                  </div>

                  <div className="flex items-center justify-between pt-1">
                    <span className="font-mono font-bold text-rose-600 dark:text-rose-400 text-sm">
                      -{formatIDR(exp.amount)}
                    </span>
                    <button
                      type="button"
                      onClick={() => setManagingExpense(exp)}
                      className="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-brand-500 hover:bg-brand-600 active:scale-95 text-white shadow-xs transition cursor-pointer"
                      title="Kelola Pengeluaran"
                    >
                      <Settings className="h-4 w-4" />
                    </button>
                  </div>
                </div>
              ))}
            </div>
          )}

          {/* Pagination */}
          {expenses.pagination && expenses.pagination.last_page > 1 && (
            <div className="flex items-center justify-between border-t border-gray-200 p-4 dark:border-gray-800">
              <span className="text-xs text-gray-500 dark:text-gray-400">
                Halaman {expenses.pagination.current_page} dari {expenses.pagination.last_page} (Total {expenses.pagination.total} Catatan)
              </span>
              <div className="flex items-center gap-2">
                {expenses.pagination.prev_page_url && (
                  <Link
                    href={expenses.pagination.prev_page_url}
                    className="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:hover:bg-gray-800 text-xs font-semibold text-gray-700 dark:text-gray-200 transition shadow-2xs"
                  >
                    <ChevronLeft className="h-3.5 w-3.5" /> Sebelumnya
                  </Link>
                )}
                {expenses.pagination.next_page_url && (
                  <Link
                    href={expenses.pagination.next_page_url}
                    className="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:hover:bg-gray-800 text-xs font-semibold text-gray-700 dark:text-gray-200 transition shadow-2xs"
                  >
                    Selanjutnya <ChevronRight className="h-3.5 w-3.5" />
                  </Link>
                )}
              </div>
            </div>
          )}
        </div>
      </div>

      {/* ── INTERACTIVE KELOLA EXPENSE MODAL 1:1 SUPERADMIN ── */}
      {managingExpense && (
        <div
          className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-xs animate-in fade-in"
          onClick={() => setManagingExpense(null)}
        >
          <div
            className="w-full max-w-md rounded-2xl border border-gray-200 bg-white p-5 sm:p-6 shadow-2xl dark:border-gray-800 dark:bg-gray-900 space-y-4 max-h-[92vh] overflow-y-auto"
            onClick={(e) => e.stopPropagation()}
          >
            {/* Modal Header */}
            <div className="flex items-start justify-between border-b border-gray-100 pb-3 dark:border-gray-800">
              <div className="flex items-center gap-3">
                <div className="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-rose-50 dark:bg-rose-500/10 text-rose-600 dark:text-rose-400 font-bold">
                  <Wallet className="h-5 w-5" />
                </div>
                <div>
                  <h3 className="text-sm sm:text-base font-bold text-gray-900 dark:text-white line-clamp-1">
                    {managingExpense.description}
                  </h3>
                  <div className="flex items-center gap-2 mt-0.5">
                    <span
                      className={cn(
                        "inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold shadow-xs",
                        getCategoryBadgeColor(managingExpense.category)
                      )}
                    >
                      {managingExpense.category || "Operasional"}
                    </span>
                    <span className="text-[11px] text-gray-500 dark:text-gray-400">
                      ID #{managingExpense.id}
                    </span>
                  </div>
                </div>
              </div>
              <button
                type="button"
                onClick={() => setManagingExpense(null)}
                className="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 transition cursor-pointer"
              >
                <X className="h-5 w-5" />
              </button>
            </div>

            {/* Nominal Banner */}
            <div className="rounded-xl border border-rose-100 bg-rose-50/80 dark:border-rose-900/30 dark:bg-rose-950/20 p-3.5 flex items-center justify-between">
              <div>
                <span className="text-[11px] text-rose-700 dark:text-rose-400 font-medium">Nominal Kas Keluar</span>
                <div className="text-xl font-mono font-black text-rose-600 dark:text-rose-400">
                  -{formatIDR(managingExpense.amount)}
                </div>
              </div>
              <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold bg-rose-500 text-white shadow-xs uppercase whitespace-nowrap">
                Kas Keluar
              </span>
            </div>

            {/* Sunken Detail Box */}
            <div className="rounded-xl border border-gray-100 bg-gray-50/80 dark:border-gray-800 dark:bg-gray-900/50 p-3.5 space-y-2.5 text-xs text-gray-600 dark:text-gray-300">
              <div className="flex items-center justify-between py-1 border-b border-gray-200/60 dark:border-gray-800">
                <span className="text-gray-500 flex items-center gap-1.5">
                  <Calendar className="h-3.5 w-3.5 text-gray-400" /> Tanggal
                </span>
                <span className="font-semibold text-gray-900 dark:text-white">
                  {managingExpense.date ? formatDate(managingExpense.date) : "-"}
                </span>
              </div>

              <div className="flex items-center justify-between py-1 border-b border-gray-200/60 dark:border-gray-800">
                <span className="text-gray-500 flex items-center gap-1.5">
                  <CreditCard className="h-3.5 w-3.5 text-gray-400" /> Metode Pembayaran
                </span>
                <span className="font-bold uppercase text-gray-900 dark:text-white font-mono">
                  {managingExpense.payment_method || "cash"}
                </span>
              </div>

              <div className="flex items-center justify-between py-1 border-b border-gray-200/60 dark:border-gray-800">
                <span className="text-gray-500 flex items-center gap-1.5">
                  <Building className="h-3.5 w-3.5 text-gray-400" /> Vendor / Penerima
                </span>
                <span className="font-semibold text-gray-900 dark:text-white">
                  {managingExpense.vendor || "-"}
                </span>
              </div>

              {managingExpense.notes && (
                <div className="pt-1">
                  <span className="text-gray-500 block text-[11px] mb-1">Catatan Tambahan:</span>
                  <p className="font-medium text-gray-900 dark:text-white bg-white dark:bg-gray-900 p-2.5 rounded-lg border border-gray-200 dark:border-gray-800 text-[11px] leading-relaxed">
                    {managingExpense.notes}
                  </p>
                </div>
              )}
            </div>

            {/* Quick Actions (Solid Colors & Icon Only) */}
            <div className="flex items-center justify-between gap-2 pt-2 border-t border-gray-100 dark:border-gray-800">
              <button
                type="button"
                onClick={() => handleDelete(managingExpense)}
                className="flex h-10 w-10 items-center justify-center rounded-xl bg-rose-600 hover:bg-rose-700 text-white shadow-xs transition cursor-pointer shrink-0"
                title="Hapus Pengeluaran"
              >
                <Trash2 className="h-4 w-4" />
              </button>

              <button
                type="button"
                onClick={() => {
                  const exp = managingExpense
                  setManagingExpense(null)
                  openEditModal(exp)
                }}
                className="flex h-10 w-10 items-center justify-center rounded-xl bg-brand-500 hover:bg-brand-600 active:scale-95 text-white shadow-xs transition cursor-pointer shrink-0"
                title="Edit Pengeluaran"
              >
                <Pencil className="h-4 w-4" />
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Modal Catat / Edit Pengeluaran */}
      <Modal
        isOpen={modalOpen}
        onClose={() => setModalOpen(false)}
        title={editingExpense ? "Edit Catatan Pengeluaran" : "Catat Pengeluaran Baru"}
        description="Form pencatatan kas keluar dan beban operasional usaha"
        maxWidth="lg"
      >
        <form onSubmit={handleSubmit} className="space-y-4 text-xs max-h-[92vh] overflow-y-auto pr-1">
          <div>
            <label className="mb-1 block font-semibold text-gray-700 dark:text-gray-300">
              Deskripsi Pengeluaran *
            </label>
            <input
              type="text"
              value={form.data.description}
              onChange={(e) => form.setData("description", e.target.value)}
              placeholder="cth: Beli Kabel Dropcore 1 Roll / Bensin Teknisi"
              className="h-10 w-full rounded-xl border border-gray-300 bg-transparent px-3.5 text-xs text-gray-900 placeholder:text-gray-400 focus:border-brand-500 focus:outline-hidden dark:border-gray-700 dark:text-white dark:placeholder:text-gray-500"
              required
            />
          </div>

          <div className="grid grid-cols-2 gap-3">
            <div>
              <label className="mb-1 block font-semibold text-gray-700 dark:text-gray-300">
                Nominal (Rp) *
              </label>
              <input
                type="number"
                value={form.data.amount}
                onChange={(e) => form.setData("amount", e.target.value)}
                placeholder="cth: 250000"
                className="h-10 w-full rounded-xl border border-gray-300 bg-transparent px-3.5 font-mono text-xs font-bold text-gray-900 placeholder:text-gray-400 focus:border-brand-500 focus:outline-hidden dark:border-gray-700 dark:text-white dark:placeholder:text-gray-500"
                required
              />
            </div>

            <div>
              <label className="mb-1 block font-semibold text-gray-700 dark:text-gray-300">
                Kategori
              </label>
              <select
                value={form.data.category}
                onChange={(e) => form.setData("category", e.target.value)}
                className="h-10 w-full rounded-xl border border-gray-300 bg-white px-3 text-xs text-gray-900 focus:border-brand-500 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white"
              >
                <option value="Operasional">Operasional</option>
                <option value="Bandwidth">Bandwidth / Backbone</option>
                <option value="Peralatan">Peralatan &amp; Material</option>
                <option value="Gaji">Gaji &amp; Komisi</option>
                <option value="Pemeliharaan">Pemeliharaan Jaringan</option>
                <option value="Lainnya">Lainnya</option>
              </select>
            </div>
          </div>

          <div className="grid grid-cols-2 gap-3">
            <div>
              <label className="mb-1 block font-semibold text-gray-700 dark:text-gray-300">
                Tanggal *
              </label>
              <input
                type="date"
                value={form.data.date}
                onChange={(e) => form.setData("date", e.target.value)}
                className="h-10 w-full rounded-xl border border-gray-300 bg-white px-3 text-xs text-gray-900 focus:border-brand-500 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                required
              />
            </div>

            <div>
              <label className="mb-1 block font-semibold text-gray-700 dark:text-gray-300">
                Metode Pembayaran
              </label>
              <select
                value={form.data.payment_method}
                onChange={(e) => form.setData("payment_method", e.target.value)}
                className="h-10 w-full rounded-xl border border-gray-300 bg-white px-3 text-xs text-gray-900 focus:border-brand-500 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white"
              >
                <option value="cash">Tunai (Cash)</option>
                <option value="transfer">Transfer Bank</option>
                <option value="qris">QRIS</option>
              </select>
            </div>
          </div>

          <div>
            <label className="mb-1 block font-semibold text-gray-700 dark:text-gray-300">
              Vendor / Toko / Penerima (Opsional)
            </label>
            <input
              type="text"
              value={form.data.vendor}
              onChange={(e) => form.setData("vendor", e.target.value)}
              placeholder="cth: Toko Fiber Optik Jaya"
              className="h-10 w-full rounded-xl border border-gray-300 bg-transparent px-3.5 text-xs text-gray-900 placeholder:text-gray-400 focus:border-brand-500 focus:outline-hidden dark:border-gray-700 dark:text-white dark:placeholder:text-gray-500"
            />
          </div>

          <div>
            <label className="mb-1 block font-semibold text-gray-700 dark:text-gray-300">
              Catatan Tambahan (Opsional)
            </label>
            <textarea
              rows={3}
              value={form.data.notes}
              onChange={(e) => form.setData("notes", e.target.value)}
              placeholder="Keterangan rincian barang atau keperluan..."
              className="w-full rounded-xl border border-gray-300 bg-transparent p-3 text-xs text-gray-900 placeholder:text-gray-400 focus:border-brand-500 focus:outline-hidden dark:border-gray-700 dark:text-white dark:placeholder:text-gray-500"
            />
          </div>

          <div className="flex items-center justify-end gap-2 pt-3 border-t border-gray-200 dark:border-gray-800">
            <button
              type="button"
              onClick={() => setModalOpen(false)}
              className="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:hover:bg-gray-800 text-xs font-semibold text-gray-700 dark:text-gray-200 transition shadow-2xs cursor-pointer"
            >
              Batal
            </button>
            <button
              type="submit"
              disabled={form.processing || !form.data.description || !form.data.amount}
              className="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-brand-500 hover:bg-brand-600 active:scale-95 text-xs font-bold text-white shadow-xs transition cursor-pointer disabled:opacity-50"
            >
              {form.processing ? "Menyimpan..." : editingExpense ? "Simpan Perubahan" : "Tambah Pengeluaran"}
            </button>
          </div>
        </form>
      </Modal>
    </AppLayout>
  )
}
