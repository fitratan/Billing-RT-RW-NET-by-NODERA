import { AppLayout } from "@/components/layout/app-layout"
import {
  Users,
  Wifi,
  TrendingUp,
  Wallet,
  Plus,
  Router,
  Activity,
  LayoutGrid,
  QrCode,
  BarChart3,
  KeyRound,
  Ticket,
  Server,
  Boxes,
  X,
  Save,
  ChevronUp,
  ChevronDown,
  ChevronLeft,
  ChevronRight,
  SlidersHorizontal,
  Megaphone,
  ShieldCheck,
  AlertTriangle,
  ArrowRight,
  Clock,
  Bell,
  Cpu,
  MapPin,
  Globe,
  Check,
  Search,
  Layers,
  UserCheck,
  Radio,
  Puzzle,
  Settings,
  Gauge,
  LifeBuoy,
  UserCog,
  TrendingDown,
  Webhook,
  History,
  LogOut,
  ArrowUpRight,
  ExternalLink,
  Sparkles,
  Building2,
  Phone,
  Send,
  type LucideIcon,
} from "lucide-react"
import { PageProps } from "@/types"
import { cn, formatIDR } from "@/lib/utils"
import { useLanguage } from "@/lib/i18n"
import { adminSidebarItems, adminNavItems, adminBrand } from "@/lib/admin-nav"
import { usePage, Link } from "@inertiajs/react"
import { useEffect, useState, useMemo } from "react"
import Chart from "react-apexcharts"
import type { ApexOptions } from "apexcharts"

export interface PendingInvoice {
  id: number
  invoice_number: string
  customer_name: string | null
  customer_code: string | null
  customer_phone?: string | null
  package_name: string | null
  amount: number
  due_date: string | null
  status: string
}

export interface TenantQuota {
  packageName: string
  totalCustomers: number
  maxCustomers: number
  customerUsagePct?: number
  remaining?: number
  percentage?: number
  isNearLimit: boolean
  isLimitReached: boolean
  totalRouters?: number
  maxRouters?: number
  isTrial?: boolean
  trialEndsAt?: string | null
  expiredAt?: string | null
}

export interface Stats {
  totalPelanggan: number
  onlinePppoe: number
  totalMikrotik?: number
  totalOnus?: number
  invoiceTertunda: number
  invoiceTertundaNominal?: number
  pendapatanBulanIni: number
  pendapatanHariIni: number
  pengeluaranBulanIni: number
  tiketGangguan: number
  totalInvoiceLunas: number
  tagihanTertunda: PendingInvoice[]
  tenantQuota?: TenantQuota
  revenueChart?: { month: string; amount: number }[]
  expenseChart?: { month: string; amount: number }[]
}

export interface AnnouncementItem {
  id: string
  title: string
  date: string
  body: string
  isUnread: boolean
}

export interface LiveRouterStats {
  id: number
  name: string
  ip_address: string
  status: string
  model?: string
  ros_version?: string
  cpu_load?: number
  uptime?: string
  active_pppoe?: number
  total_secrets?: number
}

export default function AdminDashboard(props: PageProps<{
  stats?: Stats
  quick_pay_enabled?: boolean
  userName?: string
  companyName?: string
  tenantName?: string
  tenantLogo?: string
  announcements?: AnnouncementItem[]
  liveRouters?: LiveRouterStats[]
}>) {
  const { t } = useLanguage()
  const { auth } = usePage().props as unknown as { auth?: { user?: { name?: string; role?: string } } }
  const authUser = auth?.user

  const s = props.stats || {
    totalPelanggan: 0,
    onlinePppoe: 0,
    totalMikrotik: 0,
    totalOnus: 0,
    invoiceTertunda: 0,
    invoiceTertundaNominal: 0,
    pendapatanBulanIni: 0,
    pendapatanHariIni: 0,
    pengeluaranBulanIni: 0,
    tiketGangguan: 0,
    totalInvoiceLunas: 0,
    tagihanTertunda: [],
  }

  const [statsData, setStatsData] = useState<Stats>(s)
  const [quickPayOpen, setQuickPayOpen] = useState(false)
  const [selectedAnn, setSelectedAnn] = useState<AnnouncementItem | null>(null)
  const [activeTab, setActiveTab] = useState<"overview" | "finance" | "network">("overview")

  const pendingInvoicesList = useMemo(() => {
    return statsData.tagihanTertunda || s.tagihanTertunda || []
  }, [statsData, s])

  const pendingTotalNominal = useMemo(() => {
    if (s.invoiceTertundaNominal && s.invoiceTertundaNominal > 0) return s.invoiceTertundaNominal
    return pendingInvoicesList.reduce((acc, inv) => acc + (inv.amount || 0), 0)
  }, [s, pendingInvoicesList])

  const effectiveTenantName = props.tenantName || props.companyName || "Nodera Billing"
  const currentMonthYear = new Intl.DateTimeFormat("id-ID", { month: "long", year: "numeric" }).format(new Date())

  // Revenue vs Expense Chart Setup
  const chartCategories = useMemo(() => {
    if (s.revenueChart && s.revenueChart.length > 0) {
      return s.revenueChart.map((item) => item.month)
    }
    return ["Jan", "Feb", "Mar", "Apr", "Mei", "Jun", "Jul", "Agu", "Sep", "Okt", "Nov", "Des"]
  }, [s.revenueChart])

  const chartSeries = useMemo(() => {
    const revData = s.revenueChart && s.revenueChart.length > 0
      ? s.revenueChart.map((item) => item.amount)
      : [0, 0, 0, 0, 0, 0, 0, 0, 0, s.pendapatanBulanIni || 0, 0, 0]

    const expData = s.expenseChart && s.expenseChart.length > 0
      ? s.expenseChart.map((item) => item.amount)
      : [0, 0, 0, 0, 0, 0, 0, 0, 0, s.pengeluaranBulanIni || 0, 0, 0]

    return [
      { name: "Kas Masuk (Pendapatan)", data: revData },
      { name: "Pengeluaran (Beban)", data: expData },
    ]
  }, [s.revenueChart, s.expenseChart, s.pendapatanBulanIni, s.pengeluaranBulanIni])

  const areaChartOptions = useMemo(() => ({
    chart: {
      type: "area" as const,
      fontFamily: "Outfit, Inter, sans-serif",
      toolbar: { show: false },
      sparkline: { enabled: false },
    },
    colors: ["#0073C6", "#EF4444"],
    fill: {
      type: "gradient",
      gradient: {
        shadeIntensity: 1,
        opacityFrom: 0.35,
        opacityTo: 0.02,
        stops: [0, 90, 100],
      },
    },
    dataLabels: { enabled: false },
    stroke: { curve: "smooth" as const, width: 2.5 },
    xaxis: {
      categories: chartCategories,
      labels: {
        style: { colors: "#64748B", fontSize: "11px", fontWeight: 500 },
      },
      axisBorder: { show: false },
      axisTicks: { show: false },
    },
    yaxis: {
      labels: {
        style: { colors: "#64748B", fontSize: "11px", fontWeight: 500 },
        formatter: (val: number) => {
          if (val >= 1000000) return `${(val / 1000000).toFixed(1)}M`
          if (val >= 1000) return `${(val / 1000).toFixed(0)}k`
          return String(val)
        },
      },
    },
    tooltip: {
      theme: "light",
      y: { formatter: (val: number) => formatIDR(val) },
    },
    grid: {
      borderColor: "#E2E8F0",
      strokeDashArray: 4,
      xaxis: { lines: { show: false } },
    },
    legend: {
      position: "top" as const,
      horizontalAlign: "right" as const,
      fontSize: "12px",
      fontWeight: 600,
      labels: { colors: "#64748B" },
    },
  }), [chartCategories])

  // ── Executive Multi-Type Charts ──
  const totalBilled = (s.pendapatanBulanIni || 0) + pendingTotalNominal
  const collectionRate = totalBilled > 0 ? Math.round(((s.pendapatanBulanIni || 0) / totalBilled) * 100) : 0

  const collectionDonutOptions: ApexOptions = useMemo(() => ({
    chart: {
      type: "donut",
      fontFamily: "Outfit, Inter, sans-serif",
      background: "transparent",
    },
    labels: ["Kas Masuk (Lunas)", "Belum Bayar (Pending)"],
    colors: ["#10B981", "#F59E0B"],
    stroke: { show: false },
    dataLabels: { enabled: false },
    legend: { show: false },
    plotOptions: {
      pie: {
        donut: {
          size: "74%",
          labels: {
            show: true,
            total: {
              show: true,
              label: "TERTAGIH",
              color: "#94A3B8",
              fontSize: "10px",
              fontWeight: 700,
              formatter: () => `${collectionRate}%`,
            },
            value: {
              fontSize: "12px",
              fontWeight: 800,
              color: "#10B981",
              formatter: () => formatIDR(s.pendapatanBulanIni || 0),
            },
          },
        },
      },
    },
    tooltip: {
      theme: "light",
      y: { formatter: (val: number) => formatIDR(val) },
    },
  }), [collectionRate, s.pendapatanBulanIni, pendingTotalNominal])

  const collectionDonutSeries = useMemo(() => [
    s.pendapatanBulanIni || 0,
    pendingTotalNominal || 0,
  ], [s.pendapatanBulanIni, pendingTotalNominal])

  const onlineCount = s.onlinePppoe ?? 0
  const offlineCount = Math.max(0, (s.totalPelanggan || 0) - onlineCount)

  const subscriberDonutOptions: ApexOptions = useMemo(() => ({
    chart: {
      type: "donut",
      fontFamily: "Outfit, Inter, sans-serif",
      background: "transparent",
    },
    labels: ["PPPoE Online Aktif", "Standby / Offline"],
    colors: ["#0073C6", "#94A3B8"],
    stroke: { show: false },
    dataLabels: { enabled: false },
    legend: { show: false },
    plotOptions: {
      pie: {
        donut: {
          size: "74%",
          labels: {
            show: true,
            total: {
              show: true,
              label: "ONLINE",
              color: "#94A3B8",
              fontSize: "10px",
              fontWeight: 700,
              formatter: () => s.totalPelanggan > 0 ? `${Math.round((onlineCount / s.totalPelanggan) * 100)}%` : "0%",
            },
            value: {
              fontSize: "12px",
              fontWeight: 800,
              color: "#0073C6",
              formatter: () => `${onlineCount} User`,
            },
          },
        },
      },
    },
    tooltip: {
      theme: "dark",
      y: { formatter: (val: number) => `${val} Pelanggan` },
    },
  }), [s.totalPelanggan, onlineCount, offlineCount])

  const subscriberDonutSeries = useMemo(() => [
    onlineCount,
    offlineCount,
  ], [onlineCount, offlineCount])

  const slaPercentage = (s.tiketGangguan || 0) === 0 ? 100 : Math.max(20, 100 - (s.tiketGangguan || 0) * 15)

  const networkRadialOptions: ApexOptions = useMemo(() => ({
    chart: {
      type: "radialBar",
      fontFamily: "Outfit, Inter, sans-serif",
      background: "transparent",
    },
    plotOptions: {
      radialBar: {
        hollow: { size: "62%" },
        track: { background: "rgba(148, 163, 184, 0.15)" },
        dataLabels: {
          name: {
            show: true,
            fontSize: "10px",
            fontWeight: 700,
            color: "#94A3B8",
            offsetY: -6,
          },
          value: {
            show: true,
            fontSize: "15px",
            fontWeight: 800,
            color: (s.tiketGangguan || 0) === 0 ? "#10B981" : "#EF4444",
            offsetY: 4,
            formatter: () => `${slaPercentage}%`,
          },
        },
      },
    },
    colors: [(s.tiketGangguan || 0) === 0 ? "#10B981" : "#EF4444"],
    labels: ["SLA HEALTH"],
    stroke: { lineCap: "round" },
  }), [s.tiketGangguan, slaPercentage])

  const networkRadialSeries = useMemo(() => [
    slaPercentage,
  ], [slaPercentage])

  // Quota calculation
  const quota = s.tenantQuota
  const isTrial = quota?.isTrial || false

  return (
    <AppLayout
      title={t("dash.title", "Dashboard")}
      sidebarItems={adminSidebarItems}
      navItems={adminNavItems}
      brand={adminBrand}
    >
      <div className="space-y-6">
        {/* Top Executive Banner 1:1 Superadmin */}
        <div className="rounded-2xl bg-brand-500 text-white p-5 sm:p-6 shadow-theme-xs">
          <div className="space-y-1">
            <h2 className="text-xl sm:text-2xl font-bold tracking-tight">
              Ringkasan Operasional & Tagihan ISP
            </h2>
            <p className="text-xs sm:text-sm text-white/80 max-w-2xl">
              Pantau arus kas penagihan, status router core MikroTik, dan aktivitas pelanggan aktif secara real-time.
            </p>
          </div>
        </div>

        {/* ── TOP EXECUTIVE MULTI-CHART WIDGETS ── */}
        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
          {/* Widget 1: Realisasi Kas Penagihan (Donut Chart) */}
          <div className="rounded-2xl border border-gray-200 bg-white p-4 shadow-xs dark:border-gray-800 dark:bg-white/[0.03] flex flex-col justify-between">
            <div className="flex items-center justify-between pb-2 border-b border-gray-100 dark:border-gray-800">
              <div className="flex items-center gap-2">
                <Wallet className="h-4 w-4 text-emerald-500" />
                <h3 className="text-xs font-bold text-gray-900 dark:text-white uppercase tracking-wider">Realisasi Tagihan</h3>
              </div>
              <Link href="/admin/billing/invoices" className="text-[11px] font-bold text-brand-500 hover:text-brand-600 transition">
                Invoices &rarr;
              </Link>
            </div>

            <div className="py-2 flex items-center justify-between gap-2">
              <div className="space-y-1.5 flex-1 min-w-0">
                <div>
                  <span className="text-[10px] font-semibold text-gray-400 dark:text-gray-500 uppercase">Kas Masuk Lunas</span>
                  <div className="text-sm font-bold text-emerald-600 dark:text-emerald-400 tabular-nums">
                    {formatIDR(s.pendapatanBulanIni || 0)}
                  </div>
                </div>
                <div>
                  <span className="text-[10px] font-semibold text-gray-400 dark:text-gray-500 uppercase">Pending / Tertunda</span>
                  <div className="text-xs font-bold text-amber-600 dark:text-amber-400 tabular-nums">
                    {formatIDR(pendingTotalNominal)}
                  </div>
                </div>
              </div>
              <div className="w-[120px] h-[120px] shrink-0 flex items-center justify-center">
                <Chart
                  options={collectionDonutOptions}
                  series={collectionDonutSeries}
                  type="donut"
                  height={120}
                  width={120}
                />
              </div>
            </div>

            <div className="pt-2 border-t border-gray-100 dark:border-gray-800 flex items-center justify-between text-[11px] text-gray-500 dark:text-gray-400">
              <span>Efisiensi: <strong className="text-emerald-600 dark:text-emerald-400 font-bold">{collectionRate}%</strong></span>
              <span>{s.totalInvoiceLunas || 0} Invoice Lunas</span>
            </div>
          </div>

          {/* Widget 2: Distribusi Pelanggan & PPPoE (Donut Chart) */}
          <div className="rounded-2xl border border-gray-200 bg-white p-4 shadow-xs dark:border-gray-800 dark:bg-white/[0.03] flex flex-col justify-between">
            <div className="flex items-center justify-between pb-2 border-b border-gray-100 dark:border-gray-800">
              <div className="flex items-center gap-2">
                <Users className="h-4 w-4 text-brand-500" />
                <h3 className="text-xs font-bold text-gray-900 dark:text-white uppercase tracking-wider">Status Pelanggan</h3>
              </div>
              <Link href="/admin/billing/customers" className="text-[11px] font-bold text-brand-500 hover:text-brand-600 transition">
                Data &rarr;
              </Link>
            </div>

            <div className="py-2 flex items-center justify-between gap-2">
              <div className="space-y-1.5 flex-1 min-w-0">
                <div>
                  <span className="text-[10px] font-semibold text-gray-400 dark:text-gray-500 uppercase">PPPoE Online Aktif</span>
                  <div className="text-sm font-bold text-brand-600 dark:text-brand-400 tabular-nums">
                    {onlineCount} User Online
                  </div>
                </div>
                <div>
                  <span className="text-[10px] font-semibold text-gray-400 dark:text-gray-500 uppercase">Total Terdaftar</span>
                  <div className="text-xs font-bold text-gray-700 dark:text-gray-300 tabular-nums">
                    {s.totalPelanggan || 0} Pelanggan
                  </div>
                </div>
              </div>
              <div className="w-[120px] h-[120px] shrink-0 flex items-center justify-center">
                <Chart
                  options={subscriberDonutOptions}
                  series={subscriberDonutSeries}
                  type="donut"
                  height={120}
                  width={120}
                />
              </div>
            </div>

            <div className="pt-2 border-t border-gray-100 dark:border-gray-800 flex items-center justify-between text-[11px] text-gray-500 dark:text-gray-400">
              <span>Aktivitas: <strong className="text-brand-600 dark:text-brand-400 font-bold">{s.totalPelanggan > 0 ? Math.round((onlineCount / s.totalPelanggan) * 100) : 0}%</strong></span>
              <span>{offlineCount} Standby/Isolir</span>
            </div>
          </div>

          {/* Widget 3: Infrastruktur & SLA Health (RadialBar Chart) */}
          <div className="rounded-2xl border border-gray-200 bg-white p-4 shadow-xs dark:border-gray-800 dark:bg-white/[0.03] flex flex-col justify-between">
            <div className="flex items-center justify-between pb-2 border-b border-gray-100 dark:border-gray-800">
              <div className="flex items-center gap-2">
                <Server className="h-4 w-4 text-purple-500" />
                <h3 className="text-xs font-bold text-gray-900 dark:text-white uppercase tracking-wider">Kesehatan Jaringan</h3>
              </div>
              <Link href="/admin/mikrotik/routers" className="text-[11px] font-bold text-brand-500 hover:text-brand-600 transition">
                Router &rarr;
              </Link>
            </div>

            <div className="py-2 flex items-center justify-between gap-2">
              <div className="space-y-1.5 flex-1 min-w-0">
                <div>
                  <span className="text-[10px] font-semibold text-gray-400 dark:text-gray-500 uppercase">Core Router Online</span>
                  <div className="text-sm font-bold text-purple-600 dark:text-purple-400 tabular-nums">
                    {s.totalMikrotik || 0} Unit Aktif
                  </div>
                </div>
                <div>
                  <span className="text-[10px] font-semibold text-gray-400 dark:text-gray-500 uppercase">GPON ONU Terpasang</span>
                  <div className="text-xs font-bold text-gray-700 dark:text-gray-300 tabular-nums">
                    {s.totalOnus || 0} Perangkat
                  </div>
                </div>
              </div>
              <div className="w-[120px] h-[120px] shrink-0 flex items-center justify-center">
                <Chart
                  options={networkRadialOptions}
                  series={networkRadialSeries}
                  type="radialBar"
                  height={130}
                  width={120}
                />
              </div>
            </div>

            <div className="pt-2 border-t border-gray-100 dark:border-gray-800 flex items-center justify-between text-[11px] text-gray-500 dark:text-gray-400">
              <span>Status Tiket: <strong className={cn("font-bold", (s.tiketGangguan || 0) > 0 ? "text-rose-600 dark:text-rose-400" : "text-emerald-600 dark:text-emerald-400")}>{(s.tiketGangguan || 0) > 0 ? `${s.tiketGangguan} Gangguan` : "Normal"}</strong></span>
              <span>SLA {slaPercentage}%</span>
            </div>
          </div>
        </div>

        {/* Financial Chart & Quick Health Grid 1:1 Superadmin */}
        <div className="grid grid-cols-1 gap-6 lg:grid-cols-12">
          {/* Main Financial Trend Chart (8 cols) */}
          <div className="lg:col-span-8 rounded-2xl border border-gray-200 bg-white p-5 shadow-xs dark:border-gray-800 dark:bg-white/[0.03]">
            <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-4">
              <div>
                <h3 className="text-base font-bold text-gray-800 dark:text-white/90">
                  Arus Finansial Bulanan
                </h3>
                <p className="text-xs text-gray-500 dark:text-gray-400">
                  Perbandingan kas masuk langganan vs beban operasional ISP
                </p>
              </div>
              <div className="flex items-center gap-2">
                <Link
                  href="/admin/finance"
                  className="inline-flex items-center gap-1 text-xs font-semibold text-brand-500 hover:text-brand-600 transition"
                >
                  <span>Laporan Detail</span>
                  <ArrowRight className="h-3.5 w-3.5" />
                </Link>
              </div>
            </div>

            <div className="min-h-[280px]">
              <Chart
                options={areaChartOptions}
                series={chartSeries}
                type="area"
                height={280}
              />
            </div>
          </div>

          {/* Router Live Telemetry & Summary (4 cols) */}
          <div className="lg:col-span-4 rounded-2xl border border-gray-200 bg-white p-5 shadow-xs dark:border-gray-800 dark:bg-white/[0.03] flex flex-col justify-between space-y-4">
            <div>
              <div className="flex items-center justify-between mb-1">
                <h3 className="text-base font-bold text-gray-800 dark:text-white/90">
                  Core Router Health
                </h3>
                <span className="flex h-2 w-2 rounded-full bg-emerald-500 animate-pulse whitespace-nowrap" />
              </div>
              <p className="text-xs text-gray-500 dark:text-gray-400">
                Status telemetri live router & tunnel aktif
              </p>
            </div>

            <div className="space-y-3">
              <div className="p-3 rounded-xl border border-gray-100 bg-gray-50/50 dark:border-gray-800 dark:bg-gray-900/50 flex items-center justify-between">
                <div>
                  <div className="text-[11px] text-gray-500 dark:text-gray-400 font-medium">Router Terhubung</div>
                  <div className="text-base font-bold text-gray-900 dark:text-white mt-0.5">
                    {s.totalMikrotik || 0} Unit Online
                  </div>
                </div>
                <div className="flex h-9 w-9 items-center justify-center rounded-xl bg-purple-50 dark:bg-purple-500/10 text-purple-600 dark:text-purple-400">
                  <Server className="h-5 w-5" />
                </div>
              </div>

              <div className="p-3 rounded-xl border border-gray-100 bg-gray-50/50 dark:border-gray-800 dark:bg-gray-900/50 flex items-center justify-between">
                <div>
                  <div className="text-[11px] text-gray-500 dark:text-gray-400 font-medium">Sesi PPPoE Aktif</div>
                  <div className="text-base font-bold text-emerald-600 dark:text-emerald-400 mt-0.5">
                    {s.onlinePppoe || 0} User Connected
                  </div>
                </div>
                <div className="flex h-9 w-9 items-center justify-center rounded-xl bg-emerald-50 dark:bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                  <KeyRound className="h-5 w-5" />
                </div>
              </div>

              <div className="p-3 rounded-xl border border-gray-100 bg-gray-50/50 dark:border-gray-800 dark:bg-gray-900/50 flex items-center justify-between">
                <div>
                  <div className="text-[11px] text-gray-500 dark:text-gray-400 font-medium">Tiket Kendala Layanan</div>
                  <div className={cn("text-base font-bold mt-0.5", (s.tiketGangguan || 0) > 0 ? "text-rose-600 dark:text-rose-400" : "text-gray-900 dark:text-white")}>
                    {s.tiketGangguan || 0} Kasus Terbuka
                  </div>
                </div>
                <div className="flex h-9 w-9 items-center justify-center rounded-xl bg-amber-50 dark:bg-amber-500/10 text-amber-600 dark:text-amber-400">
                  <Ticket className="h-5 w-5" />
                </div>
              </div>
            </div>

            <Link
              href="/admin/mikrotik/routers"
              className="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:hover:bg-gray-800 text-xs font-semibold text-gray-700 dark:text-gray-200 transition active:scale-95"
            >
              <Activity className="h-4 w-4 text-brand-500" />
              <span>Buka Live Monitoring Router</span>
            </Link>
          </div>
        </div>
      </div>
    </AppLayout>
  )
}
