import { useState, useMemo } from "react"
import { AppLayout } from "@/components/layout/app-layout"
import {
  Wallet,
  Receipt,
  Users,
  MapPin,
  TrendingUp,
  Clock,
  CheckCircle2,
  DollarSign,
  ArrowUpRight,
  Layers,
  ChevronRight,
  Send,
  Calendar,
  CreditCard,
  Building,
  UserCheck,
} from "lucide-react"
import { PageProps } from "@/types"
import { formatIDR, cn } from "@/lib/utils"
import {
  collectorSidebarItems,
  collectorNavItems,
  collectorBrand,
} from "@/lib/collector-nav"
import { Link } from "@inertiajs/react"
import Chart from "react-apexcharts"

interface CollectorDashboardProps extends PageProps {
  collector: {
    name: string
    username: string
    router?: string | null
  }
  stats: {
    totalCustomers?: number
    paidCount?: number
    unpaidCount?: number
    paidAmount?: number
    myPaidAmount?: number
    myPaidCount?: number
    unpaidAmount?: number
    todayPaidAmount?: number
    todayPaidCount?: number
  }
  chartData?: {
    dailyTrend?: Array<{
      date: string
      label: string
      amount: number
      count: number
    }>
    paidRatio?: {
      paid: number
      unpaid: number
    }
  }
  recentPayments?: Array<{
    id: number
    invoice_number: string
    customer_name: string
    customer_code: string
    amount: number
    paid_at: string | null
    period: string | null
  }>
  todayPayments?: Array<{
    id: number
    customer_name: string
    amount: number
    paid_at: string | null
    period: string | null
  }>
  attendance?: {
    clock_in: string | null
    clock_out: string | null
    is_present: boolean
  } | null
}

export default function CollectorDashboardPage({
  collector,
  stats = {},
  chartData,
  recentPayments = [],
  todayPayments = [],
  attendance,
}: CollectorDashboardProps) {
  const totalCustomers = stats.totalCustomers ?? 0
  const paidCount = stats.myPaidCount ?? stats.paidCount ?? 0
  const unpaidCount = stats.unpaidCount ?? 0
  const myPaidAmount = stats.myPaidAmount ?? stats.paidAmount ?? 0
  const unpaidAmount = stats.unpaidAmount ?? 0
  const todayPaidAmount = stats.todayPaidAmount ?? 0
  const todayPaidCount = stats.todayPaidCount ?? 0

  // Spline Trend Chart
  const trendLabels = chartData?.dailyTrend?.map((d) => d.label) || []
  const trendAmounts = chartData?.dailyTrend?.map((d) => d.amount) || []

  const trendChartOptions: ApexCharts.ApexOptions = useMemo(
    () => ({
      chart: {
        type: "area",
        height: 260,
        toolbar: { show: false },
        fontFamily: "inherit",
      },
      colors: ["#0073C6"],
      fill: {
        type: "gradient",
        gradient: {
          shadeIntensity: 1,
          opacityFrom: 0.45,
          opacityTo: 0.05,
          stops: [0, 95, 100],
        },
      },
      dataLabels: { enabled: false },
      stroke: { curve: "smooth", width: 2.5 },
      xaxis: {
        categories: trendLabels,
        labels: {
          style: { colors: "#94a3b8", fontSize: "11px" },
        },
        axisBorder: { show: false },
        axisTicks: { show: false },
      },
      yaxis: {
        labels: {
          formatter: (v) => formatIDR(v),
          style: { colors: "#94a3b8", fontSize: "10px" },
        },
      },
      grid: {
        borderColor: "rgba(148, 163, 184, 0.15)",
        strokeDashArray: 4,
      },
      tooltip: {
        theme: "light",
        y: {
          formatter: (v) => formatIDR(v),
        },
      },
    }),
    [trendLabels]
  )

  // Donut Paid vs Unpaid Chart
  const ratioSeries = [paidCount, unpaidCount]
  const donutChartOptions: ApexCharts.ApexOptions = useMemo(
    () => ({
      chart: {
        type: "donut",
        height: 240,
        fontFamily: "inherit",
      },
      labels: ["Terbayar (Koleksi)", "Tertunggak"],
      colors: ["#10b981", "#f43f5e"],
      dataLabels: { enabled: false },
      plotOptions: {
        pie: {
          donut: {
            size: "70%",
            labels: {
              show: true,
              total: {
                show: true,
                label: "Total Tagihan",
                fontSize: "11px",
                color: "#94a3b8",
                formatter: () => `${paidCount + unpaidCount}`,
              },
            },
          },
        },
      },
      legend: {
        position: "bottom",
        labels: {
          colors: "#94a3b8",
        },
      },
      tooltip: {
        theme: "light",
        y: {
          formatter: (v) => `${v} Tagihan`,
        },
      },
    }),
    [paidCount, unpaidCount]
  )

  return (
    <AppLayout
      title="Dashboard Kolektor"
      brand={collectorBrand}
      sidebarItems={collectorSidebarItems}
      navItems={collectorNavItems}
    >
      <div className="space-y-4 sm:space-y-6">
        {/* ── WELCOME GREETING & ATTENDANCE BANNER ── */}
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 rounded-2xl border border-gray-200 bg-white p-4 sm:p-5 shadow-xs dark:border-gray-800 dark:bg-gray-900">
          <div className="flex items-center gap-3.5">
            <div className="flex h-12 w-12 items-center justify-center rounded-2xl bg-brand-500/10 text-brand-500 dark:bg-brand-500/20 font-black text-lg shadow-xs shrink-0">
              {collector?.name?.charAt(0) || "K"}
            </div>
            <div>
              <div className="flex items-center gap-2">
                <h2 className="text-base sm:text-lg font-bold text-gray-900 dark:text-white">
                  Halo, {collector?.name || "Petugas Kolektor"}!
                </h2>
                <span className="rounded-full bg-brand-500/10 px-2 py-0.5 text-[10px] font-bold text-brand-500 border border-brand-500/20">
                  KOLEKTOR
                </span>
              </div>
              <p className="text-xs text-gray-500 dark:text-gray-400">
                {collector?.router ? `Wilayah Router: ${collector.router}` : "Portal Penagihan Lapangan & Setoran"}
              </p>
            </div>
          </div>

          <div className="flex items-center gap-2">
            {attendance?.is_present ? (
              <div className="flex items-center gap-2 rounded-xl bg-emerald-50 px-3 py-2 text-xs font-bold text-emerald-700 dark:bg-emerald-950/30 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800/60">
                <UserCheck className="h-4 w-4 shrink-0" />
                <span>Hadir ({attendance.clock_in || "08:00"})</span>
              </div>
            ) : (
              <div className="flex items-center gap-2 rounded-xl bg-gray-100 px-3 py-2 text-xs font-semibold text-gray-600 dark:bg-gray-800 dark:text-gray-300">
                <Clock className="h-4 w-4 shrink-0" />
                <span>Shift Aktif</span>
              </div>
            )}
            <Link
              href="/kolektor/invoices"
              className="inline-flex items-center gap-1.5 rounded-xl bg-brand-500 px-3.5 py-2 text-xs font-bold text-white shadow-xs hover:bg-brand-600 transition"
            >
              <Receipt className="h-4 w-4" />
              <span>Buka Tagihan</span>
            </Link>
          </div>
        </div>

        {/* ── 4 SUMMARY METRICS CARDS: CLEAN NEUTRAL TAILADMIN ── */}
        <div className="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
          <div className="rounded-2xl border border-gray-200 bg-white p-4 shadow-xs dark:border-gray-800 dark:bg-gray-900">
            <span className="text-xs font-semibold text-gray-500 dark:text-gray-400">
              Total Tagihan Pelanggan
            </span>
            <div className="mt-2 flex items-baseline justify-between">
              <span className="text-xl sm:text-2xl font-bold text-gray-900 dark:text-white font-mono">
                {totalCustomers}
              </span>
              <span className="text-xs text-gray-400 font-medium">Pelanggan</span>
            </div>
          </div>

          <div className="rounded-2xl border border-gray-200 bg-white p-4 shadow-xs dark:border-gray-800 dark:bg-gray-900">
            <span className="text-xs font-semibold text-gray-500 dark:text-gray-400">
              Koleksi Terbayar ({paidCount})
            </span>
            <div className="mt-2 flex items-baseline justify-between">
              <span className="text-lg sm:text-xl font-bold text-gray-900 dark:text-white font-mono">
                {formatIDR(myPaidAmount)}
              </span>
              <span className="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-500 text-white">
                Lunas
              </span>
            </div>
          </div>

          <div className="rounded-2xl border border-gray-200 bg-white p-4 shadow-xs dark:border-gray-800 dark:bg-gray-900">
            <span className="text-xs font-semibold text-gray-500 dark:text-gray-400">
              Tertunggak ({unpaidCount})
            </span>
            <div className="mt-2 flex items-baseline justify-between">
              <span className="text-lg sm:text-xl font-bold text-gray-900 dark:text-white font-mono">
                {formatIDR(unpaidAmount)}
              </span>
              <span className="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-500 text-white">
                Pending
              </span>
            </div>
          </div>

          <div className="rounded-2xl border border-gray-200 bg-white p-4 shadow-xs dark:border-gray-800 dark:bg-gray-900">
            <span className="text-xs font-semibold text-gray-500 dark:text-gray-400">
              Koleksi Hari Ini ({todayPaidCount})
            </span>
            <div className="mt-2 flex items-baseline justify-between">
              <span className="text-lg sm:text-xl font-bold text-gray-900 dark:text-white font-mono">
                {formatIDR(todayPaidAmount)}
              </span>
              <span className="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-brand-500 text-white">
                Hari Ini
              </span>
            </div>
          </div>
        </div>

        {/* ── INTERACTIVE CHARTS (TREND & DONUT) ── */}
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-4">
          {/* Trend Chart */}
          <div className="lg:col-span-2 rounded-2xl border border-gray-200 bg-white p-4 sm:p-5 shadow-xs dark:border-gray-800 dark:bg-gray-900">
            <div className="flex items-center justify-between border-b border-gray-100 dark:border-gray-800 pb-3 mb-2">
              <div>
                <h3 className="text-sm font-bold text-gray-900 dark:text-white flex items-center gap-2">
                  <TrendingUp className="h-4 w-4 text-brand-500" />
                  <span>Tren Koleksi Penagihan (7 Hari Terakhir)</span>
                </h3>
              </div>
            </div>
            {trendAmounts.length > 0 ? (
              <Chart
                options={trendChartOptions}
                series={[{ name: "Nominal Terbayar", data: trendAmounts }]}
                type="area"
                height={260}
              />
            ) : (
              <div className="h-60 flex items-center justify-center text-xs text-gray-400">
                Belum ada data riwayat penagihan 7 hari terakhir.
              </div>
            )}
          </div>

          {/* Donut Ratio Chart */}
          <div className="rounded-2xl border border-gray-200 bg-white p-4 sm:p-5 shadow-xs dark:border-gray-800 dark:bg-gray-900">
            <div className="flex items-center justify-between border-b border-gray-100 dark:border-gray-800 pb-3 mb-2">
              <h3 className="text-sm font-bold text-gray-900 dark:text-white flex items-center gap-2">
                <Receipt className="h-4 w-4 text-emerald-500" />
                <span>Rasio Pembayaran</span>
              </h3>
            </div>
            {paidCount > 0 || unpaidCount > 0 ? (
              <Chart
                options={donutChartOptions}
                series={ratioSeries}
                type="donut"
                height={240}
              />
            ) : (
              <div className="h-60 flex items-center justify-center text-xs text-gray-400">
                Tidak ada tagihan aktif.
              </div>
            )}
          </div>
        </div>

        {/* ── QUICK ACCESS MENU CARDS ── */}
        <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
          <Link
            href="/kolektor/invoices"
            className="group rounded-2xl border border-gray-200 bg-white p-4 shadow-xs hover:border-brand-500/50 hover:shadow-md transition-all dark:border-gray-800 dark:bg-gray-900"
          >
            <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-brand-50 text-brand-600 dark:bg-brand-950/40 dark:text-brand-400 font-bold group-hover:scale-105 transition-transform">
              <Receipt className="h-5 w-5" />
            </div>
            <h4 className="mt-3 text-xs sm:text-sm font-bold text-gray-900 dark:text-white group-hover:text-brand-500 transition-colors">
              Kelola Tagihan
            </h4>
            <p className="mt-0.5 text-[11px] text-gray-500 dark:text-gray-400">
              Input pembayaran &amp; kirim WA
            </p>
          </Link>

          <Link
            href="/kolektor/customers"
            className="group rounded-2xl border border-gray-200 bg-white p-4 shadow-xs hover:border-brand-500/50 hover:shadow-md transition-all dark:border-gray-800 dark:bg-gray-900"
          >
            <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-purple-50 text-purple-600 dark:bg-purple-950/40 dark:text-purple-400 font-bold group-hover:scale-105 transition-transform">
              <Users className="h-5 w-5" />
            </div>
            <h4 className="mt-3 text-xs sm:text-sm font-bold text-gray-900 dark:text-white group-hover:text-purple-500 transition-colors">
              Data Pelanggan
            </h4>
            <p className="mt-0.5 text-[11px] text-gray-500 dark:text-gray-400">
              Daftar &amp; info langganan
            </p>
          </Link>

          <Link
            href="/kolektor/map"
            className="group rounded-2xl border border-gray-200 bg-white p-4 shadow-xs hover:border-brand-500/50 hover:shadow-md transition-all dark:border-gray-800 dark:bg-gray-900"
          >
            <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-cyan-50 text-cyan-600 dark:bg-cyan-950/40 dark:text-cyan-400 font-bold group-hover:scale-105 transition-transform">
              <MapPin className="h-5 w-5" />
            </div>
            <h4 className="mt-3 text-xs sm:text-sm font-bold text-gray-900 dark:text-white group-hover:text-cyan-500 transition-colors">
              Peta Jaringan GIS
            </h4>
            <p className="mt-0.5 text-[11px] text-gray-500 dark:text-gray-400">
              Lokasi ODP &amp; rumah pelanggan
            </p>
          </Link>

          <Link
            href="/kolektor/earnings"
            className="group rounded-2xl border border-gray-200 bg-white p-4 shadow-xs hover:border-brand-500/50 hover:shadow-md transition-all dark:border-gray-800 dark:bg-gray-900"
          >
            <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600 dark:bg-emerald-950/40 dark:text-emerald-400 font-bold group-hover:scale-105 transition-transform">
              <Wallet className="h-5 w-5" />
            </div>
            <h4 className="mt-3 text-xs sm:text-sm font-bold text-gray-900 dark:text-white group-hover:text-emerald-500 transition-colors">
              Komisi &amp; Setoran
            </h4>
            <p className="mt-0.5 text-[11px] text-gray-500 dark:text-gray-400">
              Rekap komisi &amp; riwayat kas
            </p>
          </Link>
        </div>

        {/* ── RECENT COLLECTION TRANSACTIONS TABLE ── */}
        <div className="rounded-2xl border border-gray-200 bg-white p-4 sm:p-5 shadow-xs dark:border-gray-800 dark:bg-gray-900 space-y-3">
          <div className="flex items-center justify-between border-b border-gray-100 dark:border-gray-800 pb-3">
            <h3 className="text-sm font-bold text-gray-900 dark:text-white">
              Transaksi Terakhir Koleksi Saya
            </h3>
            <Link
              href="/kolektor/invoices?status=paid"
              className="text-xs font-bold text-brand-600 hover:text-brand-700 dark:text-brand-400 flex items-center gap-1"
            >
              <span>Lihat Semua</span>
              <ChevronRight className="h-3.5 w-3.5" />
            </Link>
          </div>

          {recentPayments.length === 0 ? (
            <div className="p-8 text-center text-xs text-gray-400">
              Belum ada transaksi pembayaran yang diproses oleh akun ini.
            </div>
          ) : (
            <div className="table-scrollbar overflow-x-auto">
              <table className="w-full text-left border-collapse min-w-[600px]">
                <thead>
                  <tr className="border-b border-gray-100 bg-gray-50/50 text-[11px] font-semibold uppercase tracking-wider text-gray-400 dark:border-gray-800 dark:bg-gray-800/40">
                    <th className="py-2.5 px-3">Pelanggan</th>
                    <th className="py-2.5 px-3">No. Invoice</th>
                    <th className="py-2.5 px-3">Waktu Bayar</th>
                    <th className="py-2.5 px-3 text-right">Nominal</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-gray-100 text-xs dark:divide-gray-800">
                  {recentPayments.map((p) => (
                    <tr key={p.id} className="hover:bg-gray-50/60 dark:hover:bg-gray-800/30">
                      <td className="py-2.5 px-3">
                        <span className="font-bold text-gray-900 dark:text-white">
                          {p.customer_name}
                        </span>
                        <p className="text-[10px] text-gray-400 font-mono">{p.customer_code}</p>
                      </td>
                      <td className="py-2.5 px-3 font-mono font-bold text-gray-700 dark:text-gray-300">
                        {p.invoice_number}
                      </td>
                      <td className="py-2.5 px-3 text-gray-500 font-mono text-[11px]">
                        {p.paid_at || "-"}
                      </td>
                      <td className="py-2.5 px-3 text-right font-mono font-bold text-emerald-600 dark:text-emerald-400">
                        {formatIDR(p.amount)}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </div>
      </div>
    </AppLayout>
  )
}
