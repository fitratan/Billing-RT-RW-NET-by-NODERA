import { AppLayout } from "@/components/layout/app-layout"
import {
  Handshake,
  Users,
  TrendingUp,
  Receipt,
  ArrowUpRight,
  Printer,
  Wallet,
} from "lucide-react"
import { EmptyState } from "@/components/ui/empty-state"
import { PageProps } from "@/types"
import { adminSidebarItems, adminNavItems, adminBrand } from "@/lib/admin-nav"
import { Link } from "@inertiajs/react"
import { MetricCard } from "@/components/tailadmin/MetricCard"

export default function AgentReportsPage({
  companyName = "NODERA Billing",
  tenantName,
}: PageProps<{
  companyName?: string
  tenantName?: string
}>) {
  return (
    <AppLayout
      title="Laporan Mitra & Agen"
      brand={adminBrand}
      sidebarItems={adminSidebarItems}
      navItems={adminNavItems}
    >
      <div className="space-y-6">
        {/* ── 4 TOP KPI METRICS ── */}
        <div className="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
          <MetricCard
            title="Mitra & Reseller"
            value="Aktif"
            icon={<Users className="h-6 w-6 text-brand-500" />}
            sub="Jaringan agen penjualan"
          />

          <MetricCard
            title="Komisi Agen"
            value="Realtime"
            icon={<Wallet className="h-6 w-6 text-emerald-500" />}
            sub="Perhitungan margin profit"
          />

          <MetricCard
            title="Distribusi Voucher"
            value="Tersinkron"
            icon={<Receipt className="h-6 w-6 text-purple-500" />}
            sub="Penjualan voucher hotspot"
          />

          <MetricCard
            title="Performa Penjualan"
            value="Terekam"
            icon={<TrendingUp className="h-6 w-6 text-amber-500" />}
            sub="Akumulasi omzet mitra"
          />
        </div>

        {/* ── MASTER CARD CONTAINER ── */}
        <div className="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xs dark:border-gray-800 dark:bg-white/[0.03]">
          {/* Header Bar */}
          <div className="p-4 sm:p-5 border-b border-gray-100 dark:border-gray-800 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
              <h3 className="text-base font-bold text-gray-800 dark:text-white/90">
                Laporan Aktivitas Transaksi Mitra &amp; Agen
              </h3>
              <p className="text-xs text-gray-500 dark:text-gray-400">
                Rekapitulasi penjualan voucher dan setoran komisi mitra agen
              </p>
            </div>

            <div className="flex items-center gap-2 flex-wrap sm:flex-nowrap">
              <button
                type="button"
                onClick={() => window.print()}
                className="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:hover:bg-gray-800 text-xs font-semibold text-gray-700 dark:text-gray-200 transition active:scale-95 shadow-2xs cursor-pointer"
                title="Cetak Laporan"
              >
                <Printer className="h-4 w-4 text-brand-500" />
                <span className="hidden sm:inline">Cetak</span>
              </button>
            </div>
          </div>

          {/* Body Content */}
          <div className="p-8 sm:p-12 text-center space-y-4">
            <div className="flex h-14 w-14 mx-auto items-center justify-center rounded-2xl bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-400 border border-brand-200 dark:border-brand-500/20">
              <Handshake className="h-7 w-7" />
            </div>
            <div className="space-y-1">
              <h3 className="text-base font-bold text-gray-900 dark:text-white">
                Pusat Pelaporan Transaksi Agen
              </h3>
              <p className="text-xs text-gray-500 dark:text-gray-400 max-w-md mx-auto leading-relaxed">
                Laporan aktivitas transaksi harian, riwayat deposit, dan cetak rekap komisi penjualan saldo voucher mitra dapat dikelola langsung dari menu manajemen Mitra &amp; Agen.
              </p>
            </div>
            <div className="pt-2">
              <Link
                href="/admin/semua-fitur"
                className="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-xl bg-brand-500 hover:bg-brand-600 active:scale-95 text-xs font-bold text-white shadow-xs transition cursor-pointer"
              >
                <span>Buka Menu Mitra &amp; Agen</span>
                <ArrowUpRight className="h-4 w-4" />
              </Link>
            </div>
          </div>
        </div>
      </div>
    </AppLayout>
  )
}
