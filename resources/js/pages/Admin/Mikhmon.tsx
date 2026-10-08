import { useState } from "react"
import { AppLayout } from "@/components/layout/app-layout"
import {
  Wifi,
  KeyRound,
  Copy,
  Check,
  CheckCircle2,
  Server,
  Layers,
  HelpCircle,
  Lock,
  ArrowUpRight,
  AlertTriangle,
  Wallet,
  RefreshCw,
  RotateCcw,
  Activity,
  Banknote,
  Receipt,
  Clock,
  TrendingUp,
} from "lucide-react"
import { Head, router, Link } from "@inertiajs/react"
import { PageProps } from "@/types"
import { formatIDR } from "@/lib/utils"
import { adminSidebarItems, adminNavItems, adminBrand } from "@/lib/admin-nav"
import MetricCard from "@/components/tailadmin/MetricCard"
import Modal from "@/components/tailadmin/Modal"
import { ComponentCard } from "@/components/tailadmin/ComponentCard"

interface MikhmonAdminProps extends PageProps {
  isLocked?: boolean
  mikhmonUrl?: string
  adminUrl?: string
  buyUrl?: string
  subpathUrl?: string
  folderName?: string
  rosVersion?: string
  masterSaldo?: number
  hasVpnUser?: boolean
  financialIncome?: {
    today_vouchers: number
    today_income: number
    month_vouchers: number
    month_income: number
    updated_at: string
    currency: string
  }
  tenantAddon?: {
    id?: number
    addon_id?: number
    is_active: boolean
    status: string
    auto_renew?: boolean
    expired_at?: string | null
    is_expired?: boolean
  } | null
  addon?: {
    id: number
    name: string
    slug: string
    description?: string
    price: number
    billing_cycle?: string
  } | null
  tenant: {
    name: string
    slug: string
    is_active?: boolean
    is_expired?: boolean
    expires_at?: string | null
    expires_formatted?: string | null
  }
  defaultCredentials?: {
    username: string
    password: string
  }
}

export default function AdminMikhmonPage({
  isLocked = false,
  mikhmonUrl = "",
  adminUrl = "",
  buyUrl = "",
  subpathUrl,
  folderName = "hotspot-admin",
  rosVersion = "6",
  masterSaldo = 0,
  hasVpnUser = false,
  financialIncome = {
    today_vouchers: 0,
    today_income: 0,
    month_vouchers: 0,
    month_income: 0,
    updated_at: new Date().toISOString().replace("T", " ").substring(0, 19),
    currency: "Rp",
  },
  tenantAddon,
  addon,
  tenant,
  defaultCredentials = { username: "nodera", password: "nodera" },
}: MikhmonAdminProps) {
  const [selectedRos, setSelectedRos] = useState<"6" | "7">(
    (rosVersion === "7" ? "7" : "6") as "6" | "7"
  )
  const [isSwitchingRos, setIsSwitchingRos] = useState(false)
  const [isResettingPass, setIsResettingPass] = useState(false)
  const [isPurchasing, setIsPurchasing] = useState(false)
  const [isTogglingRenew, setIsTogglingRenew] = useState(false)
  const [copiedKey, setCopiedKey] = useState<string | null>(null)
  
  // Financial Live Income State
  const [income, setIncome] = useState(financialIncome)
  const [isRefreshingIncome, setIsRefreshingIncome] = useState(false)

  const handleRefreshIncome = async () => {
    if (isRefreshingIncome) return
    setIsRefreshingIncome(true)
    try {
      const res = await fetch("/admin/mikhmon/live-income", {
        headers: { Accept: "application/json" },
      })
      const data = await res.json()
      if (data?.success && data?.income) {
        setIncome(data.income)
      }
    } catch (e) {
      console.error("Failed to refresh live income:", e)
    } finally {
      setIsRefreshingIncome(false)
    }
  }
  
  // Reset Password Modal
  const [isResetModalOpen, setIsResetModalOpen] = useState(false)
  // Buy Add-on Modal
  const [isBuyModalOpen, setIsBuyModalOpen] = useState(false)

  const isExpired = !!tenantAddon?.is_expired || !!tenant.is_expired
  const isSuspended = tenant.is_active === false
  const isHealthy = !isLocked && !isExpired && !isSuspended

  const copyToClipboard = (text: string, key: string) => {
    if (navigator.clipboard) {
      navigator.clipboard.writeText(text)
      setCopiedKey(key)
      setTimeout(() => setCopiedKey(null), 2000)
    }
  }

  const handleSwitchRos = (version: "6" | "7") => {
    if (version === selectedRos || isSwitchingRos) return
    setIsSwitchingRos(true)
    setSelectedRos(version)
    router.post(
      "/admin/mikhmon/switch-ros-version",
      { ros_version: version },
      {
        preserveScroll: true,
        onFinish: () => setIsSwitchingRos(false),
        onError: () => setIsSwitchingRos(false),
      }
    )
  }

  const handleResetPasswordConfirm = () => {
    setIsResettingPass(true)
    router.post(
      "/admin/mikhmon/reset-password",
      {},
      {
        preserveScroll: true,
        onFinish: () => {
          setIsResettingPass(false)
          setIsResetModalOpen(false)
        },
        onError: () => setIsResettingPass(false),
      }
    )
  }

  const handleBuyAddonConfirm = () => {
    if (!addon) return
    setIsPurchasing(true)
    router.post(
      `/admin/addons/buy/${addon.id}`,
      { auto_renew: true },
      {
        preserveScroll: true,
        onFinish: () => {
          setIsPurchasing(false)
          setIsBuyModalOpen(false)
        },
      }
    )
  }

  const handleToggleAutoRenew = () => {
    if (!addon) return
    setIsTogglingRenew(true)
    router.post(
      `/admin/addons/toggle-auto-renew/${addon.id}`,
      {},
      {
        preserveScroll: true,
        onFinish: () => setIsTogglingRenew(false),
      }
    )
  }

  // --- LOCKED PAYWALL VIEW ---
  if (isLocked && addon) {
    const canAfford = masterSaldo >= addon.price

    return (
      <AppLayout
        title="MIKHMON Online"
        sidebarItems={adminSidebarItems}
        navItems={adminNavItems}
        brand={adminBrand}
      >
        <Head title="MIKHMON Online — Aktivasi Add-on" />

        <div className="space-y-6 max-w-4xl mx-auto py-2">
          {/* Top Metric Cards */}
          <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <MetricCard
              title="Biaya Add-on"
              value={formatIDR(addon.price)}
              icon={Activity}
              iconBgColor="bg-brand-50 dark:bg-brand-500/10"
              iconColor="text-brand-500 dark:text-brand-400"
              description={`Per ${addon.billing_cycle === "lifetime" ? "sekali bayar" : "bulan"}`}
            />
            <MetricCard
              title="Saldo Dompet Utama"
              value={formatIDR(masterSaldo)}
              icon={Wallet}
              iconBgColor={canAfford ? "bg-emerald-50 dark:bg-emerald-500/10" : "bg-amber-50 dark:bg-amber-500/10"}
              iconColor={canAfford ? "text-emerald-500 dark:text-emerald-400" : "text-amber-500 dark:text-amber-400"}
              description={canAfford ? "Saldo mencukupi" : `Kurang Rp ${(addon.price - masterSaldo).toLocaleString("id-ID")}`}
            />
            <MetricCard
              title="Status Fitur"
              value="Terkunci"
              icon={Lock}
              iconBgColor="bg-rose-50 dark:bg-rose-500/10"
              iconColor="text-rose-500 dark:text-rose-400"
              description="Memerlukan aktivasi add-on"
            />
          </div>

          {/* Master Card Paywall */}
          <div className="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xs dark:border-gray-800 dark:bg-white/[0.03] p-6 sm:p-8 space-y-6">
            <div className="flex items-start gap-4">
              <div className="flex size-12 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-500 dark:bg-brand-950/50 dark:text-brand-400">
                <Wifi className="size-6" />
              </div>
              <div>
                <div className="flex items-center gap-2">
                  <span className="inline-flex items-center gap-1 rounded-md bg-amber-500 px-2 py-0.5 text-[10px] font-bold uppercase text-white shadow-xs whitespace-nowrap">
                    <Lock className="size-3" /> Fitur Add-on
                  </span>
                  <span className="inline-flex items-center gap-1 rounded-md bg-brand-500 px-2 py-0.5 text-[10px] font-bold uppercase text-white shadow-xs whitespace-nowrap">
                    1-Click Potong Saldo
                  </span>
                </div>
                <h2 className="text-xl font-bold text-gray-900 dark:text-white mt-2">
                  {addon.name}
                </h2>
                <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                  {addon.description || "Kelola voucher hotspot dan user MikroTik secara online dari mana saja tanpa IP publik statis. Terisolasi per tenant dengan auto-heal template & multi-session."}
                </p>
              </div>
            </div>

            {/* Feature highlights */}
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-2">
              <div className="flex items-start gap-3 rounded-xl border border-gray-100 bg-gray-50/80 p-3.5 dark:border-gray-800 dark:bg-gray-900/50">
                <CheckCircle2 className="size-5 text-emerald-500 shrink-0 mt-0.5" />
                <div>
                  <h4 className="text-xs font-bold text-gray-900 dark:text-white">Multi-Session & Isolated Runtime</h4>
                  <p className="text-[11px] text-gray-500 dark:text-gray-400">Instance MIKHMON tersendiri tanpa bercampur antar tenant.</p>
                </div>
              </div>
              <div className="flex items-start gap-3 rounded-xl border border-gray-100 bg-gray-50/80 p-3.5 dark:border-gray-800 dark:bg-gray-900/50">
                <CheckCircle2 className="size-5 text-emerald-500 shrink-0 mt-0.5" />
                <div>
                  <h4 className="text-xs font-bold text-gray-900 dark:text-white">Dukungan RouterOS v6 & v7</h4>
                  <p className="text-[11px] text-gray-500 dark:text-gray-400">Kompatibel penuh dengan MD5 challenge v6 dan REST/Plaintext v7.</p>
                </div>
              </div>
              <div className="flex items-start gap-3 rounded-xl border border-gray-100 bg-gray-50/80 p-3.5 dark:border-gray-800 dark:bg-gray-900/50">
                <CheckCircle2 className="size-5 text-emerald-500 shrink-0 mt-0.5" />
                <div>
                  <h4 className="text-xs font-bold text-gray-900 dark:text-white">Auto-Healing Engine Template</h4>
                  <p className="text-[11px] text-gray-500 dark:text-gray-400">Perbaikan konfigurasi otomatis menjaga uptime panel 24/7.</p>
                </div>
              </div>
              <div className="flex items-start gap-3 rounded-xl border border-gray-100 bg-gray-50/80 p-3.5 dark:border-gray-800 dark:bg-gray-900/50">
                <CheckCircle2 className="size-5 text-emerald-500 shrink-0 mt-0.5" />
                <div>
                  <h4 className="text-xs font-bold text-gray-900 dark:text-white">Aktivasi Instan & Auto-Renew</h4>
                  <p className="text-[11px] text-gray-500 dark:text-gray-400">Langsung aktif saat transaksi berhasil tanpa tunggu verifikasi manual.</p>
                </div>
              </div>
            </div>

            {/* Action Bar */}
            <div className="flex flex-col sm:flex-row items-center justify-between gap-4 pt-4 border-t border-gray-200 dark:border-gray-800">
              <Link
                href="/admin/addons"
                className="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:hover:bg-gray-800 text-xs font-semibold text-gray-700 dark:text-gray-200 transition shadow-2xs"
              >
                Lihat Semua Add-on
              </Link>

              <div className="flex items-center gap-3">
                {canAfford ? (
                  <button
                    type="button"
                    onClick={() => setIsBuyModalOpen(true)}
                    className="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-brand-500 hover:bg-brand-600 active:scale-95 text-xs font-bold text-white shadow-xs transition"
                  >
                    <Activity className="size-4" />
                    <span>Aktifkan via Potong Saldo</span>
                  </button>
                ) : (
                  <Link
                    href="/admin/topup/create"
                    className="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-amber-500 hover:bg-amber-600 active:scale-95 text-xs font-bold text-white shadow-xs transition"
                  >
                    <Wallet className="size-4" />
                    <span>Top Up Saldo Sekarang</span>
                  </Link>
                )}
              </div>
            </div>
          </div>
        </div>

        {/* Modal Buy Add-on */}
        <Modal
          isOpen={isBuyModalOpen}
          onClose={() => setIsBuyModalOpen(false)}
          title="Konfirmasi Aktivasi Add-on MIKHMON"
          maxWidth="md"
        >
          <div className="p-5 max-h-[92vh] overflow-y-auto space-y-4">
            <p className="text-sm text-gray-600 dark:text-gray-300">
              Apakah Anda yakin ingin mengaktifkan <strong>{addon.name}</strong> dengan memotong saldo dompet sebesar <strong>{formatIDR(addon.price)}</strong>?
            </p>
            <div className="rounded-xl border border-gray-100 bg-gray-50/80 p-3 text-xs text-gray-500 dark:border-gray-800 dark:bg-gray-900/50 dark:text-gray-400">
              Masa aktif add-on akan berlaku selama 30 hari ke depan dengan opsi perpanjangan otomatis.
            </div>
            <div className="flex items-center justify-end gap-2.5 pt-4 border-t border-gray-200 dark:border-gray-800">
              <button
                type="button"
                onClick={() => setIsBuyModalOpen(false)}
                className="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:hover:bg-gray-800 text-xs font-semibold text-gray-700 dark:text-gray-200 transition shadow-2xs"
              >
                Batal
              </button>
              <button
                type="button"
                onClick={handleBuyAddonConfirm}
                disabled={isPurchasing}
                className="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl bg-brand-500 hover:bg-brand-600 active:scale-95 text-xs font-bold text-white shadow-xs transition disabled:opacity-50"
              >
                {isPurchasing ? <RefreshCw className="size-3.5 animate-spin" /> : <Activity className="size-3.5" />}
                {isPurchasing ? "Memproses..." : "Ya, Bayar & Aktifkan"}
              </button>
            </div>
          </div>
        </Modal>
      </AppLayout>
    )
  }

  // --- ACTIVE MIKHMON MANAGEMENT VIEW ---
  return (
    <AppLayout
      title="Server MIKHMON"
      sidebarItems={adminSidebarItems}
      navItems={adminNavItems}
      brand={adminBrand}
    >
      <Head title="Server MIKHMON — Hotspot Voucher" />

      <div className="space-y-6">
        {/* Top 4 KPI Metrics */}
        <div className="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
          <MetricCard
            title="Status Instance"
            value={isHealthy ? "Aktif & Siap" : isSuspended ? "Ditangguhkan" : "Kadaluarsa"}
            icon={Server}
            iconBgColor={isHealthy ? "bg-emerald-50 dark:bg-emerald-500/10" : "bg-rose-50 dark:bg-rose-500/10"}
            iconColor={isHealthy ? "text-emerald-500 dark:text-emerald-400" : "text-rose-500 dark:text-rose-400"}
            description={tenantAddon?.expired_at ? `Aktif s/d ${tenantAddon.expired_at}` : "Dedicated Runtime"}
          />
          <MetricCard
            title="Engine RouterOS"
            value={`RouterOS v${selectedRos}`}
            icon={Layers}
            iconBgColor="bg-brand-50 dark:bg-brand-500/10"
            iconColor="text-brand-500 dark:text-brand-400"
            description={selectedRos === "7" ? "Plaintext / REST API" : "MD5 Challenge Engine"}
          />
          <MetricCard
            title="Folder Instance"
            value={folderName}
            icon={Wifi}
            iconBgColor="bg-gray-100 dark:bg-gray-800"
            iconColor="text-gray-600 dark:text-gray-300"
            description={`Tenant: ${tenant.name}`}
          />
          <MetricCard
            title="Saldo Akun Utama"
            value={formatIDR(masterSaldo)}
            icon={Wallet}
            iconBgColor="bg-sky-50 dark:bg-sky-500/10"
            iconColor="text-sky-500 dark:text-sky-400"
            description="Saldo dompet billing"
          />
        </div>

        {/* Live Financial Income / Voucher Sales Report Card */}
        <div className="rounded-2xl border border-gray-200 bg-white p-5 shadow-xs dark:border-gray-800 dark:bg-gray-900">
          <div className="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 pb-4 dark:border-gray-800">
            <div className="flex items-center gap-3">
              <div className="flex size-10 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400">
                <Banknote className="size-5" />
              </div>
              <div>
                <h3 className="text-base font-bold text-gray-900 dark:text-white flex items-center gap-2">
                  Financial Income
                  <span className="inline-flex items-center rounded-md bg-emerald-500 px-2 py-0.5 text-xs font-semibold text-white">
                    Live Report
                  </span>
                </h3>
                <p className="text-xs text-gray-500 dark:text-gray-400">
                  Laporan rekapitulasi penjualan voucher hotspot live dari seluruh router MikroTik
                </p>
              </div>
            </div>

            <button
              onClick={handleRefreshIncome}
              disabled={isRefreshingIncome}
              className="inline-flex items-center gap-1.5 rounded-xl border border-gray-200 bg-gray-50 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-100 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700/80 transition-colors disabled:opacity-60"
            >
              <RefreshCw className={`size-3.5 ${isRefreshingIncome ? "animate-spin text-brand-500" : ""}`} />
              {isRefreshingIncome ? "Memuat..." : "Segarkan"}
            </button>
          </div>

          {/* Grid Today & This Month Metrics */}
          <div className="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
            {/* Today Card */}
            <div className="rounded-xl border border-emerald-100 bg-emerald-50/50 p-4 dark:border-emerald-950/50 dark:bg-emerald-950/20">
              <div className="flex items-center justify-between">
                <span className="text-xs font-medium text-emerald-800 dark:text-emerald-400 flex items-center gap-1.5">
                  <Clock className="size-3.5" />
                  Today (Hari Ini)
                </span>
                <span className="rounded-md bg-emerald-600 px-2 py-0.5 text-xs font-bold text-white">
                  {income.today_vouchers} vcr
                </span>
              </div>
              <div className="mt-2 text-2xl font-black tracking-tight text-emerald-950 dark:text-emerald-100">
                {formatIDR(income.today_income)}
              </div>
              <p className="mt-1 text-[11px] text-emerald-700/80 dark:text-emerald-400/80">
                Total penjualan voucher hotspot hari ini
              </p>
            </div>

            {/* This Month Card */}
            <div className="rounded-xl border border-brand-100 bg-brand-50/50 p-4 dark:border-brand-950/50 dark:bg-brand-950/20">
              <div className="flex items-center justify-between">
                <span className="text-xs font-medium text-brand-800 dark:text-brand-400 flex items-center gap-1.5">
                  <TrendingUp className="size-3.5" />
                  This Month (Bulan Ini)
                </span>
                <span className="rounded-md bg-brand-600 px-2 py-0.5 text-xs font-bold text-white">
                  {income.month_vouchers} vcr
                </span>
              </div>
              <div className="mt-2 text-2xl font-black tracking-tight text-brand-950 dark:text-brand-100">
                {formatIDR(income.month_income)}
              </div>
              <p className="mt-1 text-[11px] text-brand-700/80 dark:text-brand-400/80">
                Total akumulasi penjualan voucher hotspot bulan ini
              </p>
            </div>
          </div>

          {/* Footer Live Timestamp */}
          <div className="mt-4 flex flex-wrap items-center justify-between gap-2 border-t border-gray-100 pt-3 text-[11px] text-gray-500 dark:border-gray-800 dark:text-gray-400">
            <span className="flex items-center gap-1.5">
              <span className="size-2 rounded-full bg-emerald-500 animate-pulse" />
              Updated : {income.updated_at || "Baru saja"}
            </span>
            {isRefreshingIncome && (
              <span className="flex items-center gap-1.5 text-brand-500">
                <RefreshCw className="size-3 animate-spin" />
                loading...
              </span>
            )}
          </div>
        </div>

        {/* Expiration Alert Banner if not healthy */}
        {!isHealthy && (
          <div className="flex items-start gap-4 rounded-2xl border border-rose-200 bg-rose-50 p-4 text-rose-800 dark:border-rose-900/50 dark:bg-rose-950/40 dark:text-rose-300 shadow-xs">
            <AlertTriangle className="size-5 text-rose-600 dark:text-rose-400 shrink-0 mt-0.5" />
            <div>
              <h4 className="text-sm font-bold text-rose-950 dark:text-rose-100">
                {isSuspended ? "Tenant Ditangguhkan (Suspended)" : "Langganan Add-on Kadaluarsa"}
              </h4>
              <p className="text-xs text-rose-800/80 dark:text-rose-300/80 mt-1">
                {isSuspended
                  ? "Akun tenant Anda sedang dinonaktifkan. Akses ke panel MIKHMON ditutup sementara."
                  : `Masa aktif langganan Add-on MIKHMON Anda telah berakhir${tenantAddon?.expired_at ? ` pada ${tenantAddon.expired_at}` : ""}. Silakan perpanjang via potong saldo untuk membuka kembali.`}
              </p>
            </div>
          </div>
        )}

        {/* Addon Status & Auto-Renew Card (if Addon is registered) */}
        {addon && (
          <ComponentCard title="Status Berlangganan Add-on MIKHMON" className="p-4 sm:p-5">
            <div className="flex flex-col sm:flex-row items-center justify-between gap-4">
              <div className="flex items-center gap-3">
                <div className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-500 dark:bg-brand-950/50 dark:text-brand-400">
                  <Activity className="size-5" />
                </div>
                <div>
                  <div className="flex items-center gap-2">
                    <h4 className="text-sm font-bold text-gray-900 dark:text-white">Add-on MIKHMON Dedicated</h4>
                    <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold uppercase bg-emerald-500 text-white shadow-xs whitespace-nowrap">
                      Aktif
                    </span>
                  </div>
                  <p className="text-xs text-gray-500 dark:text-gray-400">
                    {tenantAddon?.expired_at
                      ? `Masa aktif berlaku hingga ${tenantAddon.expired_at}`
                      : "Masa aktif aktif seumur hidup / billing cycle"}
                  </p>
                </div>
              </div>

              <div className="flex items-center gap-2.5 w-full sm:w-auto justify-between sm:justify-end">
                <button
                  type="button"
                  onClick={handleToggleAutoRenew}
                  disabled={isTogglingRenew}
                  className={`inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-bold shadow-xs transition active:scale-95 ${
                    tenantAddon?.auto_renew
                      ? "bg-emerald-500 hover:bg-emerald-600 text-white"
                      : "border border-gray-200 bg-white text-gray-700 hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300"
                  }`}
                >
                  <RefreshCw className={`size-3.5 ${isTogglingRenew ? "animate-spin" : ""}`} />
                  <span>Auto-Renew {tenantAddon?.auto_renew ? "ON" : "OFF"}</span>
                </button>

                <button
                  type="button"
                  onClick={() => setIsBuyModalOpen(true)}
                  className="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-brand-500 hover:bg-brand-600 text-xs font-bold text-white shadow-xs transition active:scale-95"
                >
                  <Activity className="size-3.5" />
                  <span>Perpanjang (+30 Hari)</span>
                </button>
              </div>
            </div>
          </ComponentCard>
        )}

        {/* Master Card: Configuration & Credentials */}
        <div className="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xs dark:border-gray-800 dark:bg-white/[0.03]">
          {/* Card Header with Panel Launcher Button */}
          <div className="border-b border-gray-100 dark:border-gray-800 p-3 sm:p-4 flex flex-col gap-2.5 sm:gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
              <h3 className="text-sm sm:text-base font-bold text-gray-900 dark:text-white flex items-center gap-2">
                <Server className="size-5 text-brand-500" />
                Konfigurasi &amp; Akses Server MIKHMON
              </h3>
              <p className="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                Folder Runtime: <strong className="font-mono text-gray-700 dark:text-gray-300">{folderName}</strong> | Engine: <strong className="font-mono text-brand-600 dark:text-brand-400">RouterOS v{selectedRos}</strong>
              </p>
            </div>

            <a
              href={mikhmonUrl}
              target="_blank"
              rel="noopener noreferrer"
              className="h-10 px-3.5 sm:px-4 rounded-xl bg-brand-500 hover:bg-brand-600 active:scale-95 text-xs font-bold text-white shadow-xs flex items-center justify-center gap-1.5 w-full sm:w-auto shrink-0 cursor-pointer transition"
            >
              <span>Buka Panel MIKHMON</span>
              <ArrowUpRight className="size-4" />
            </a>
          </div>

          <div className="p-4 sm:p-6 space-y-6">
            {/* Direct Access URLs */}
            <div className="space-y-4">
              {/* Admin URL */}
              <div className="space-y-1.5">
                <div className="flex items-center justify-between">
                  <label className="text-xs font-semibold text-gray-700 dark:text-gray-300 flex items-center gap-1.5">
                    <span className="size-2 rounded-full bg-brand-500 whitespace-nowrap" />
                    <span>URL Login Admin MIKHMON</span>
                  </label>
                  <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold bg-brand-500 text-white shadow-xs whitespace-nowrap">
                    Khusus Operator / Admin
                  </span>
                </div>
                <div className="flex items-center justify-between rounded-xl border border-gray-200 bg-gray-50/80 px-3.5 py-2 font-mono text-xs text-gray-900 dark:border-gray-800 dark:bg-gray-900/50 dark:text-white shadow-2xs">
                  <span className="truncate">{adminUrl || mikhmonUrl}</span>
                  <button
                    type="button"
                    onClick={() => copyToClipboard(adminUrl || mikhmonUrl, "url-main")}
                    className="ml-2 text-gray-400 hover:text-brand-500 transition-colors p-1 shrink-0"
                    title="Salin URL"
                  >
                    {copiedKey === "url-main" ? <Check className="size-4 text-emerald-500" /> : <Copy className="size-4" />}
                  </button>
                </div>
              </div>

              {/* Public Portal Buy Voucher URL */}
              {buyUrl && (
                <div className="space-y-1.5">
                  <div className="flex items-center justify-between">
                    <label className="text-xs font-semibold text-gray-700 dark:text-gray-300 flex items-center gap-1.5">
                      <span className="size-2 rounded-full bg-emerald-500 whitespace-nowrap" />
                      <span>URL Portal Beli Voucher Hotspot Publik</span>
                    </label>
                    <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold bg-emerald-500 text-white shadow-xs whitespace-nowrap">
                      Bisa Diakses Pelanggan Umum
                    </span>
                  </div>
                  <div className="flex items-center justify-between rounded-xl border border-gray-200 bg-gray-50/80 px-3.5 py-2 font-mono text-xs text-gray-900 dark:border-gray-800 dark:bg-gray-900/50 dark:text-white shadow-2xs">
                    <span className="truncate">{buyUrl}</span>
                    <button
                      type="button"
                      onClick={() => copyToClipboard(buyUrl, "url-buy")}
                      className="ml-2 text-gray-400 hover:text-emerald-500 transition-colors p-1 shrink-0"
                      title="Salin URL Portal"
                    >
                      {copiedKey === "url-buy" ? <Check className="size-4 text-emerald-500" /> : <Copy className="size-4" />}
                    </button>
                  </div>
                </div>
              )}
            </div>

            {/* Default Credentials */}
            <div className="rounded-2xl border border-gray-200 bg-gray-50/50 p-4 dark:border-gray-800 dark:bg-gray-800/20 space-y-3">
              <div className="flex items-center justify-between">
                <div className="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300">
                  <KeyRound className="size-4 text-brand-500" />
                  <span>Kredensial Login Bawaan MIKHMON</span>
                </div>
                <button
                  type="button"
                  onClick={() => setIsResetModalOpen(true)}
                  className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-rose-500 hover:bg-rose-600 text-xs font-bold text-white shadow-xs transition active:scale-95"
                >
                  <RotateCcw className="size-3.5" />
                  <span>Reset Password</span>
                </button>
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div className="rounded-xl border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-800 shadow-2xs">
                  <span className="text-[11px] font-medium text-gray-500 dark:text-gray-400 block mb-1">Username Default</span>
                  <div className="flex items-center justify-between">
                    <code className="font-mono text-sm font-bold text-gray-900 dark:text-white">
                      {defaultCredentials.username}
                    </code>
                    <button
                      type="button"
                      onClick={() => copyToClipboard(defaultCredentials.username, "cred-user")}
                      className="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 p-1"
                    >
                      {copiedKey === "cred-user" ? <Check className="size-3.5 text-emerald-500" /> : <Copy className="size-3.5" />}
                    </button>
                  </div>
                </div>

                <div className="rounded-xl border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-800 shadow-2xs">
                  <span className="text-[11px] font-medium text-gray-500 dark:text-gray-400 block mb-1">Password Default</span>
                  <div className="flex items-center justify-between">
                    <code className="font-mono text-sm font-bold text-gray-900 dark:text-white">
                      {defaultCredentials.password}
                    </code>
                    <button
                      type="button"
                      onClick={() => copyToClipboard(defaultCredentials.password, "cred-pass")}
                      className="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 p-1"
                    >
                      {copiedKey === "cred-pass" ? <Check className="size-3.5 text-emerald-500" /> : <Copy className="size-3.5" />}
                    </button>
                  </div>
                </div>
              </div>
            </div>

            {/* ROS Engine Switcher */}
            <div className="rounded-2xl border border-gray-200 bg-gray-50/50 p-4 dark:border-gray-800 dark:bg-gray-800/20 space-y-3">
              <div className="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300">
                <Layers className="size-4 text-brand-500" />
                <span>Versi Engine RouterOS MikroTik</span>
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <button
                  type="button"
                  onClick={() => handleSwitchRos("6")}
                  disabled={isSwitchingRos}
                  className={`flex flex-col items-start text-left p-4 rounded-xl border transition-all ${
                    selectedRos === "6"
                      ? "border-brand-500 bg-brand-50/50 dark:border-brand-500 dark:bg-brand-950/30 shadow-xs"
                      : "border-gray-200 bg-white hover:border-gray-300 dark:border-gray-700 dark:bg-gray-800 shadow-2xs"
                  }`}
                >
                  <div className="flex items-center justify-between w-full mb-1">
                    <span className="font-bold text-sm text-gray-900 dark:text-white">RouterOS v6</span>
                    {selectedRos === "6" && <span className="size-2 rounded-full bg-brand-500 whitespace-nowrap" />}
                  </div>
                  <p className="text-xs text-gray-500 dark:text-gray-400">
                    Engine klasik MD5 Challenge. Cocok untuk RouterOS versi 6.4x ke bawah.
                  </p>
                </button>

                <button
                  type="button"
                  onClick={() => handleSwitchRos("7")}
                  disabled={isSwitchingRos}
                  className={`flex flex-col items-start text-left p-4 rounded-xl border transition-all ${
                    selectedRos === "7"
                      ? "border-brand-500 bg-brand-50/50 dark:border-brand-500 dark:bg-brand-950/30 shadow-xs"
                      : "border-gray-200 bg-white hover:border-gray-300 dark:border-gray-700 dark:bg-gray-800 shadow-2xs"
                  }`}
                >
                  <div className="flex items-center justify-between w-full mb-1">
                    <span className="font-bold text-sm text-gray-900 dark:text-white">RouterOS v7</span>
                    {selectedRos === "7" && <span className="size-2 rounded-full bg-brand-500 whitespace-nowrap" />}
                  </div>
                  <p className="text-xs text-gray-500 dark:text-gray-400">
                    Engine modern Plaintext Challenge & REST API. Direkomendasikan untuk RouterOS 7.x.
                  </p>
                </button>
              </div>

              {isSwitchingRos && (
                <div className="flex items-center gap-2 text-xs text-brand-600 dark:text-brand-400 font-medium">
                  <RefreshCw className="size-3.5 animate-spin" />
                  <span>Sedang memperbarui template instance MIKHMON...</span>
                </div>
              )}
            </div>

            {/* Helper Notes Strip */}
            <div className="flex items-start gap-3 rounded-xl border border-brand-100 bg-brand-50/40 p-4 text-xs text-brand-900 dark:border-brand-900/50 dark:bg-brand-950/30 dark:text-brand-300 shadow-xs">
              <HelpCircle className="size-5 text-brand-500 shrink-0 mt-0.5" />
              <div>
                <p className="font-bold text-brand-950 dark:text-brand-200 mb-1">
                  Petunjuk Akses Multi-Tenant:
                </p>
                <p className="text-brand-800/80 dark:text-brand-300/80 leading-relaxed">
                  Setiap tenant mendapatkan direktori runtime MIKHMON tersendiri dengan konfigurasi sesi yang terisolasi. Jika Anda mengubah password dari dalam panel MIKHMON, Anda dapat meresetnya kembali kapan saja melalui tombol di atas.
                </p>
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* Modal Reset Password Confirmation */}
      <Modal
        isOpen={isResetModalOpen}
        onClose={() => setIsResetModalOpen(false)}
        title="Reset Password Default Admin MIKHMON"
        maxWidth="md"
      >
        <div className="p-5 max-h-[92vh] overflow-y-auto space-y-4">
          <p className="text-sm text-gray-600 dark:text-gray-300 leading-relaxed">
            Apakah Anda yakin ingin mereset kredensial admin MIKHMON ke nilai default username: <strong>nodera</strong> dan password: <strong>nodera</strong>?
          </p>
          <div className="flex items-center justify-end gap-2.5 pt-4 border-t border-gray-200 dark:border-gray-800">
            <button
              type="button"
              onClick={() => setIsResetModalOpen(false)}
              className="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:hover:bg-gray-800 text-xs font-semibold text-gray-700 dark:text-gray-200 transition shadow-2xs"
            >
              Batal
            </button>
            <button
              type="button"
              onClick={handleResetPasswordConfirm}
              disabled={isResettingPass}
              className="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl bg-rose-500 hover:bg-rose-600 text-xs font-bold text-white shadow-xs transition active:scale-95 disabled:opacity-50"
            >
              {isResettingPass ? <RefreshCw className="size-3.5 animate-spin" /> : <RotateCcw className="size-3.5" />}
              {isResettingPass ? "Mereset..." : "Ya, Reset Password"}
            </button>
          </div>
        </div>
      </Modal>

      {/* Modal Buy Add-on (For active view perpanjangan) */}
      <Modal
        isOpen={isBuyModalOpen}
        onClose={() => setIsBuyModalOpen(false)}
        title="Perpanjang Masa Aktif Add-on MIKHMON"
        maxWidth="md"
      >
        <div className="p-5 max-h-[92vh] overflow-y-auto space-y-4">
          <p className="text-sm text-gray-600 dark:text-gray-300">
            Perpanjang masa aktif <strong>{addon?.name || "MIKHMON Online"}</strong> selama 30 hari ke depan dengan memotong saldo dompet sebesar <strong>{formatIDR(addon?.price || 0)}</strong>?
          </p>
          <div className="flex items-center justify-end gap-2.5 pt-4 border-t border-gray-200 dark:border-gray-800">
            <button
              type="button"
              onClick={() => setIsBuyModalOpen(false)}
              className="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:hover:bg-gray-800 text-xs font-semibold text-gray-700 dark:text-gray-200 transition shadow-2xs"
            >
              Batal
            </button>
            <button
              type="button"
              onClick={handleBuyAddonConfirm}
              disabled={isPurchasing}
              className="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl bg-brand-500 hover:bg-brand-600 active:scale-95 text-xs font-bold text-white shadow-xs transition disabled:opacity-50"
            >
              {isPurchasing ? <RefreshCw className="size-3.5 animate-spin" /> : <Activity className="size-3.5" />}
              {isPurchasing ? "Memproses..." : "Ya, Perpanjang"}
            </button>
          </div>
        </div>
      </Modal>
    </AppLayout>
  )
}