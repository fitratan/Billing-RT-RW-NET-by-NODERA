import React, { useState, useRef, useEffect } from "react"
import { AppLayout } from "@/components/layout/app-layout"
import {
  User,
  Banknote,
  ShieldCheck,
  Clock,
  Plus,
  Trash2,
  ChevronDown,
  Lock,
  Users,
  Router,
  AlertTriangle,
  ArrowUpRight,
  Wallet,
  CheckCircle2,
  History,
  Receipt,
  Building2,
  Image as ImageIcon,
  Upload,
  X,
  Save,
  CreditCard,
  Landmark,
  Globe,
  Check,
  RefreshCw,
  SlidersHorizontal,
  Activity,
} from "lucide-react"
import { Label } from "@/components/ui/label"
import { PageProps } from "@/types"
import { formatDate, formatIDR, cn } from "@/lib/utils"
import { adminSidebarItems, adminNavItems, adminBrand } from "@/lib/admin-nav"
import { useForm, router, Link } from "@inertiajs/react"
import { SystemUpdaterCard } from "@/components/system-updater-card"
import { Switch } from "@/components/tailadmin/Switch"
import MetricCard from "@/components/tailadmin/MetricCard"

interface BankAccount {
  id: number
  bank_name: string
  account_number: string
  account_name: string
  sort_order?: number
  is_active?: boolean
}

interface SuperadminBank {
  id: number
  bank_name: string
  account_number: string
  account_name: string
}

interface PackageItem {
  id: number
  name: string
  max_customers: number
  max_routers: number
  monthly_price: number
  semi_annual_price: number
  annual_price: number
  duration_options?: string
  description?: string
}

interface VpnUserProp {
  id: number
  name: string
  email: string
  phone: string | null
  saldo: number
}

interface TopupHistoryItem {
  id: number
  invoice_number: string
  amount: number
  total_amount: number
  unique_code?: number
  bank_destination: string
  status: string
  admin_note?: string | null
  created_at: string | null
  verified_at?: string | null
}

interface PendingTopupItem {
  id: number
  invoice_number: string
  amount: number
  total_amount: number
  bank_destination: string
  status: string
  created_at: string | null
}

interface SystemVersionProp {
  version: string
  release_date?: string
  codename?: string
  changelog?: string[]
  is_standalone?: boolean
}

interface CompanyProp {
  name?: string
  address?: string
  phone?: string
  email?: string
  logo?: string | null
  raw_logo?: string | null
}

interface PaymentSettingsProp {
  enable_gateway?: boolean
  enable_qris_manual?: boolean
  enable_bank_manual?: boolean
  default_gateway?: string
  expiry_minutes?: number
}

interface ConfiguredGatewayProp {
  gateway: string
  name: string
  is_active: boolean
}

export default function SettingsPage({
  settings: _settings,
  paymentSettings,
  configuredGateways = [],
  adminUser,
  tenant,
  company,
  vpnUser,
  masterSaldo: masterSaldoProp,
  topupHistory = [],
  pendingTopup = null,
  autoRenew: initialAutoRenew = false,
  availablePackages = [],
  superadminBankAccounts: _superadminBankAccounts = [],
  superadminQris: _superadminQris = null,
  panelUrl: _panelUrl = "https://panel.dgtlnetsolution.com",
  packageName,
  customersCount,
  maxCustomers,
  routersCount,
  maxRouters,
  superadminPhone,
  bankAccounts = [],
  systemVersion,
  companyName: _companyName = "NODERA Billing",
  tenantName: _tenantName,
}: PageProps<{
  settings: Record<string, string>
  paymentSettings?: PaymentSettingsProp
  configuredGateways?: ConfiguredGatewayProp[]
  adminUser: { id: number; name: string; username: string; email: string; phone: string | null } | null
  tenant: {
    id?: number
    name: string
    slug: string
    address?: string
    phone?: string
    email?: string
    logo?: string | null
    raw_logo?: string | null
    expired_at: string | null
    trial_ends_at?: string | null
    is_active?: boolean
    auto_renew?: boolean
    package_name?: string
    max_customers?: number
    max_routers?: number
    customers_count?: number
    routers_count?: number
  } | null
  company?: CompanyProp | null
  vpnUser?: VpnUserProp | null
  masterSaldo?: number
  topupHistory?: TopupHistoryItem[]
  pendingTopup?: PendingTopupItem | null
  autoRenew?: boolean
  availablePackages?: PackageItem[]
  superadminBankAccounts?: SuperadminBank[]
  superadminQris?: { image: string; text?: string | null } | null
  panelUrl?: string
  packageName: string
  customersCount?: number
  maxCustomers?: number
  routersCount?: number
  maxRouters?: number
  superadminPhone?: string
  bankAccounts: BankAccount[]
  systemVersion?: SystemVersionProp
  companyName?: string
  tenantName?: string
}>) {
  const [openCard, setOpenCard] = useState<string | null>("company")
  const [isTogglingAutoRenew, setIsTogglingAutoRenew] = useState(false)
  const [manageBank, setManageBank] = useState<BankAccount | null>(null)
  const fileInputRef = useRef<HTMLInputElement>(null)

  const toggleCard = (cardKey: string) => {
    setOpenCard(openCard === cardKey ? null : cardKey)
  }

  const openAndScrollTo = (cardKey: string) => {
    setOpenCard(cardKey)
    setTimeout(() => {
      const el = document.getElementById(`card-${cardKey}`)
      if (el) {
        el.scrollIntoView({ behavior: "smooth", block: "start" })
      }
    }, 100)
  }

  // 1. Company Profile & Isolated Tenant Logo Form
  const companyForm = useForm<{
    company_name: string
    company_address: string
    company_phone: string
    company_email: string
    logo: File | null
    remove_logo: boolean
  }>({
    company_name: company?.name ?? tenant?.name ?? _settings?.COMPANY_NAME ?? "",
    company_address: company?.address ?? tenant?.address ?? _settings?.COMPANY_ADDRESS ?? "",
    company_phone: company?.phone ?? tenant?.phone ?? _settings?.COMPANY_PHONE ?? "",
    company_email: company?.email ?? tenant?.email ?? _settings?.COMPANY_EMAIL ?? "",
    logo: null,
    remove_logo: false,
  })

  const [logoPreview, setLogoPreview] = useState<string | null>(company?.logo ?? tenant?.logo ?? null)
  const [logoLoadError, setLogoLoadError] = useState(false)

  useEffect(() => {
    if (!companyForm.data.logo && !companyForm.data.remove_logo) {
      setLogoPreview(company?.logo ?? tenant?.logo ?? null)
      setLogoLoadError(false)
    }
  }, [company?.logo, tenant?.logo])

  const handleLogoChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0]
    if (file) {
      companyForm.setData("logo", file)
      companyForm.setData("remove_logo", false)
      setLogoLoadError(false)
      const reader = new FileReader()
      reader.onload = (ev) => {
        setLogoPreview(ev.target?.result as string)
      }
      reader.readAsDataURL(file)
    }
  }

  const handleRemoveLogo = () => {
    companyForm.setData("logo", null)
    companyForm.setData("remove_logo", true)
    setLogoPreview(null)
    setLogoLoadError(false)
    if (fileInputRef.current) {
      fileInputRef.current.value = ""
    }
  }

  const handleCompanySubmit = (e: React.FormEvent) => {
    e.preventDefault()
    companyForm.post("/admin/my-settings/company", {
      preserveScroll: true,
      forceFormData: true,
      onSuccess: () => {
        companyForm.setData("logo", null)
        companyForm.setData("remove_logo", false)
        if (fileInputRef.current) {
          fileInputRef.current.value = ""
        }
      },
    })
  }

  // 2. Administrator Profile Form
  const profile = useForm({
    name: adminUser?.name ?? "",
    username: adminUser?.username ?? "",
    email: adminUser?.email ?? "",
    phone: adminUser?.phone ?? "",
  })

  // 3. Password Form
  const password = useForm({
    current_password: "",
    new_password: "",
    new_password_confirmation: "",
  })

  // 4. Bank Form
  const bank = useForm({
    bank_name: "",
    account_number: "",
    account_name: "",
    sort_order: "0",
  })

  // 4b. Customer Payment Methods Form
  const initialDefaultGw = paymentSettings?.default_gateway || (configuredGateways.length > 0 ? configuredGateways[0].gateway : "noderapay")
  const paymentMethodsForm = useForm({
    enable_gateway: paymentSettings?.enable_gateway ?? true,
    enable_qris_manual: paymentSettings?.enable_qris_manual ?? true,
    enable_bank_manual: paymentSettings?.enable_bank_manual ?? true,
    default_gateway: initialDefaultGw,
    expiry_minutes: paymentSettings?.expiry_minutes ?? 15,
  })

  // 5. Upgrade Package Form (Strictly via Saldo Master Wallet + Dropdown Selection)
  const defaultPackageId = availablePackages[0]?.id ?? 0
  const upgradeForm = useForm<{
    package_id: number
    duration: number
    payment_method: "balance"
  }>({
    package_id: defaultPackageId,
    duration: 1,
    payment_method: "balance",
  })

  const currentCust = Number(customersCount ?? tenant?.customers_count ?? 0)
  const limitCust = Number(maxCustomers ?? tenant?.max_customers ?? 0)
  const currentRouters = Number(routersCount ?? tenant?.routers_count ?? 0)
  const limitRouters = Number(maxRouters ?? tenant?.max_routers ?? 0)
  const activePackage = (packageName && packageName !== "-") ? packageName : (tenant?.package_name || "Enterprise Edition")

  const custUsagePercent = limitCust > 0 ? Math.min(100, Math.round((currentCust / limitCust) * 100)) : 0
  const isCustNearLimit = limitCust > 0 && custUsagePercent >= 80 && custUsagePercent < 100
  const isCustFull = limitCust > 0 && currentCust >= limitCust

  const targetSuperadminWa = (superadminPhone || _settings?.SUPERADMIN_PHONE || "6285155173547").replace(/[^0-9]/g, "").replace(/^0/, "62")
  const upgradeWaUrl = `https://wa.me/${targetSuperadminWa}?text=${encodeURIComponent(
    `Halo Superadmin NODERA, saya Admin ${adminUser?.name ? `(${adminUser.name})` : ""} dari *${tenant?.name || "NODERA"}* (Slug: ${tenant?.slug || "-"}).\n\nSaya ingin konsultasi perpanjangan / upgrade paket layanan:\n- Paket Saat Ini: *${activePackage}*\n- Kuota Pelanggan: *${currentCust} / ${limitCust > 0 ? limitCust : "Unlimited"}* Pelanggan\n- Kuota Router: *${currentRouters} / ${limitRouters > 0 ? limitRouters : "Unlimited"}* Router\n\nMohon info pilihan paket upgrade kustom yang tersedia. Terima kasih!`
  )}`

  const expiryDate = tenant?.expired_at ?? tenant?.trial_ends_at ?? null
  const daysLeft = expiryDate
    ? Math.ceil((new Date(expiryDate).getTime() - Date.now()) / (1000 * 60 * 60 * 24))
    : null

  const deleteBank = (id: number) => {
    if (confirm("Hapus rekening ini?")) {
      router.post(`/admin/my-settings/bank/delete/${id}`, {}, { preserveScroll: true })
    }
  }

  const handleToggleAutoRenew = () => {
    setIsTogglingAutoRenew(true)
    router.post("/admin/my-settings/toggle-auto-renew", {}, {
      preserveScroll: true,
      onFinish: () => setIsTogglingAutoRenew(false),
    })
  }

  // Selected package for calculation
  const selectedPkg = availablePackages.find((p) => p.id === Number(upgradeForm.data.package_id)) || availablePackages[0]
  
  const availableDurations = (selectedPkg?.duration_options
    ? selectedPkg.duration_options.split(",").map((s) => Number(s.trim())).filter((n) => !isNaN(n) && n > 0)
    : [1, 3, 6, 12]
  )

  const durationLabels: Record<number, string> = {
    1: "1 Bulan",
    3: "3 Bulan",
    6: "6 Bulan (Diskon 6 Bln)",
    12: "12 Bulan / 1 Tahun (Hemat 1 Thn)",
  }

  const calculatePrice = (pkg: PackageItem | undefined, duration: number) => {
    if (!pkg) return 0
    if (duration === 6 && pkg.semi_annual_price && pkg.semi_annual_price > 0) {
      return pkg.semi_annual_price
    }
    if (duration === 12 && pkg.annual_price && pkg.annual_price > 0) {
      return pkg.annual_price
    }
    return pkg.monthly_price * duration
  }

  const calculatedTotal = calculatePrice(selectedPkg, upgradeForm.data.duration)
  const masterSaldo = Number(masterSaldoProp ?? vpnUser?.saldo ?? 0)
  const isSaldoSufficient = masterSaldo >= calculatedTotal

  const handleUpgradeSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    upgradeForm.post("/admin/my-settings/upgrade-package", {
      preserveScroll: true,
      onSuccess: () => {
        upgradeForm.reset()
      },
    })
  }

  const isDemo = tenant?.slug === "demo" || adminUser?.username === "demo"

  return (
    <AppLayout
      title="Pengaturan Usaha & Profil"
            brand={adminBrand}
      sidebarItems={adminSidebarItems}
      navItems={adminNavItems}
    >
      <div className="space-y-6">
        {/* Top 4 KPI Metrics */}
        <div className="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
          <MetricCard
            title="Paket Layanan"
            value={activePackage}
            description={tenant?.is_active !== false ? "Langganan Aktif" : "Nonaktif"}
            icon={Activity}
          />
          <MetricCard
            title="Kuota Pelanggan"
            value={`${currentCust} / ${limitCust > 0 ? limitCust : "∞"}`}
            description={limitCust > 0 ? `${custUsagePercent}% kapasitas terpakai` : "Tanpa Batas Kuota"}
            icon={Users}
          />
          <MetricCard
            title="Kapasitas Router"
            value={`${currentRouters} / ${limitRouters > 0 ? limitRouters : "∞"}`}
            description="MikroTik Terkoneksi"
            icon={Router}
          />
          <MetricCard
            title="Masa Berlaku"
            value={daysLeft !== null ? (daysLeft > 0 ? `${daysLeft} Hari Lagi` : "Expired") : "Aktif"}
            description={expiryDate ? formatDate(expiryDate) : "Permanen / Lifetime"}
            icon={Clock}
          />
        </div>

        {/* Master Card: Status Langganan & Saldo Wallet */}
        <div className="overflow-hidden rounded-2xl border border-gray-200 bg-white p-5 shadow-xs dark:border-gray-800 dark:bg-white/[0.03] space-y-4">
          <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-gray-100 dark:border-gray-800">
            <div className="flex items-center gap-3.5 min-w-0">
              <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-brand-50 text-brand-500 dark:bg-brand-500/10 dark:text-brand-400">
                <ShieldCheck className="h-6 w-6" />
              </div>
              <div className="min-w-0 flex-1">
                <div className="flex items-center gap-2 flex-wrap">
                  <h3 className="text-base font-bold text-gray-900 dark:text-white truncate">
                    {tenant?.name ?? "NODERA Billing"}
                  </h3>
                  {tenant?.slug && (
                    <span className="text-[11px] px-2 py-0.5 rounded-md bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 font-mono border border-gray-200 dark:border-gray-700">
                      @{tenant.slug}
                    </span>
                  )}
                  <span className={cn(
                    "rounded-md px-2 py-0.5 text-[10px] font-bold text-white uppercase tracking-wider",
                    tenant?.is_active !== false ? "bg-emerald-600" : "bg-rose-600"
                  )}>
                    {tenant?.is_active !== false ? "AKTIF" : "NONAKTIF"}
                  </span>
                </div>
                <p className="text-xs text-gray-500 dark:text-gray-400 mt-0.5 truncate">
                  Status Layanan &amp; Limitasi Kapasitas ISP Cloud Platform
                </p>
              </div>
            </div>
          </div>

          {/* Panel Wallet & Auto-Debit Renewal Row */}
          <div className="rounded-xl border border-gray-100 bg-gray-50/50 dark:border-gray-800 dark:bg-gray-900/50 p-4 flex flex-col lg:flex-row items-stretch lg:items-center justify-between gap-4">
            <div className="flex items-center gap-3.5">
              <div className="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-brand-500/10 text-brand-500 dark:bg-brand-500/20 dark:text-brand-400">
                <Wallet className="h-5 w-5" />
              </div>
              <div>
                <div className="flex items-center gap-2">
                  <span className="text-xs text-gray-500 dark:text-gray-400">Saldo Master Wallet Panel</span>
                  {vpnUser && (
                    <span className="text-[10px] px-2 py-0.5 rounded bg-emerald-600 text-white font-bold">
                      Tersinkronisasi
                    </span>
                  )}
                </div>
                <div className="flex items-baseline gap-2 mt-0.5">
                  <span className="text-lg font-bold font-mono text-emerald-600 dark:text-emerald-400">
                    {formatIDR(masterSaldo)}
                  </span>
                  <Link
                    href="/admin/topup/create"
                    className="text-[11px] text-brand-500 hover:text-brand-600 dark:text-brand-400 font-semibold inline-flex items-center gap-0.5"
                  >
                    <span>Top Up Saldo</span>
                    <ArrowUpRight className="h-3 w-3" />
                  </Link>
                </div>
              </div>
            </div>

            <div className="flex items-center justify-between lg:justify-end gap-3 pt-3 lg:pt-0 border-t lg:border-t-0 border-gray-200 dark:border-gray-800">
              <div className="text-left lg:text-right">
                <div className="text-xs font-semibold text-gray-900 dark:text-white flex items-center lg:justify-end gap-1.5">
                  <span>Auto-Debit Perpanjangan</span>
                  <span className={cn(
                    "text-[10px] font-bold px-1.5 py-0.2 rounded font-mono text-white",
                    initialAutoRenew ? "bg-emerald-600" : "bg-gray-400 dark:bg-gray-600"
                  )}>
                    {initialAutoRenew ? "AKTIF" : "NONAKTIF"}
                  </span>
                </div>
                <p className="text-[11px] text-gray-500 dark:text-gray-400 max-w-sm">
                  Perpanjang masa aktif otomatis dari saldo master wallet saat paket berakhir
                </p>
              </div>
              <Switch
                checked={initialAutoRenew}
                disabled={isTogglingAutoRenew}
                onCheckedChange={handleToggleAutoRenew}
              />
            </div>
          </div>

          {/* Quota Alert Banner */}
          {(isCustFull || isCustNearLimit) && (
            <div className={cn(
              "flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3.5 rounded-xl p-4 border",
              isCustFull
                ? "border-rose-200 bg-rose-50 text-rose-800 dark:border-rose-900/50 dark:bg-rose-950/40 dark:text-rose-200"
                : "border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-900/50 dark:bg-amber-950/40 dark:text-amber-200"
            )}>
              <div className="flex items-start sm:items-center gap-3">
                <div className={cn(
                  "p-2 rounded-xl shrink-0 text-white",
                  isCustFull ? "bg-rose-600" : "bg-amber-600"
                )}>
                  <AlertTriangle className="h-5 w-5" />
                </div>
                <div>
                  <h4 className="text-xs font-bold">
                    {isCustFull ? "Batas Kuota Pelanggan Tercapai" : "Peringatan Kuota Pelanggan"}
                  </h4>
                  <p className="text-xs mt-0.5 leading-relaxed opacity-90">
                    {isCustFull
                      ? `Kuota pelanggan Anda telah terpakai penuh (${currentCust}/${limitCust}). Proses sinkronisasi dari MikroTik dan penambahan pelanggan baru dibatasi sampai paket diupgrade.`
                      : `Kuota pelanggan Anda telah terpakai ${custUsagePercent}% (${currentCust}/${limitCust}). Segera upgrade paket sebelum batas kuota habis.`}
                  </p>
                </div>
              </div>
              <button
                type="button"
                onClick={() => openAndScrollTo("upgrade")}
                className="shrink-0 inline-flex items-center gap-1.5 rounded-xl bg-brand-500 hover:bg-brand-600 px-3.5 py-2 text-xs font-bold text-white transition-all shadow-xs active:scale-95"
              >
                <Activity className="h-3.5 w-3.5" />
                Upgrade Sekarang
                <ArrowUpRight className="h-3.5 w-3.5 opacity-80" />
              </button>
            </div>
          )}
        </div>

        {/* Accordion Settings Cards */}
        <div className="space-y-4">
          {/* 1. Identitas Usaha & Logo Brand */}
          <div id="card-company" className="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xs dark:border-gray-800 dark:bg-white/[0.03] scroll-mt-20">
            <button
              type="button"
              onClick={() => toggleCard("company")}
              className="flex w-full items-center justify-between p-4 sm:p-5 text-left hover:bg-gray-50/50 dark:hover:bg-white/[0.02] transition-colors"
            >
              <div className="flex items-center gap-3.5 min-w-0">
                <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-500 dark:bg-brand-500/10 dark:text-brand-400">
                  <Building2 className="h-5 w-5" />
                </div>
                <div className="min-w-0">
                  <h3 className="text-sm font-bold text-gray-900 dark:text-white">Identitas Usaha &amp; Logo Brand</h3>
                  <p className="text-xs text-gray-500 dark:text-gray-400 mt-0.5 truncate">
                    Logo usaha, nama instansi/ISP, alamat &amp; kontak (Tampil di Bukti Pembayaran / Invoice &amp; Header)
                  </p>
                </div>
              </div>

              <div className={cn("p-1 rounded-lg text-gray-400 transition-transform duration-200", openCard === "company" && "rotate-180 text-gray-700 dark:text-gray-200")}>
                <ChevronDown className="h-4 w-4" />
              </div>
            </button>

            {openCard === "company" && (
              <div className="border-t border-gray-100 dark:border-gray-800 p-4 sm:p-5">
                <form onSubmit={handleCompanySubmit} className="space-y-5">
                  {/* Logo Section */}
                  <div className="rounded-xl border border-gray-200 bg-gray-50/50 dark:border-gray-800 dark:bg-gray-900/50 p-4 sm:p-5 space-y-4">
                    <div className="flex items-center justify-between border-b border-gray-200 dark:border-gray-800 pb-3">
                      <div className="flex items-center gap-2">
                        <ImageIcon className="h-4 w-4 text-brand-500" />
                        <Label className="text-xs font-bold text-gray-900 dark:text-white uppercase tracking-wider">
                          Logo Usaha / Brand Anda
                        </Label>
                      </div>
                      <span className="text-[10px] text-brand-500 font-semibold">Branding Otomatis</span>
                    </div>

                    <div className="flex flex-col sm:flex-row items-center gap-5">
                      <div className="relative flex h-28 w-28 sm:h-32 sm:w-32 shrink-0 items-center justify-center rounded-2xl border-2 border-dashed border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-950 p-2 overflow-hidden shadow-xs group">
                        {logoPreview && !logoLoadError ? (
                          <>
                            <img
                              src={logoPreview}
                              alt="Logo Usaha"
                              className="h-full w-full object-contain"
                              onError={() => {
                                setLogoLoadError(true)
                              }}
                            />
                            <button
                              type="button"
                              onClick={handleRemoveLogo}
                              className="absolute inset-0 bg-black/70 flex flex-col items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity text-rose-400 font-bold text-[10px] gap-1"
                              title="Hapus Logo"
                            >
                              <Trash2 className="h-4 w-4" />
                              <span>Hapus</span>
                            </button>
                          </>
                        ) : (
                          <div className="flex flex-col items-center justify-center text-center text-gray-400 dark:text-gray-500 p-2">
                            <Building2 className="h-8 w-8 mb-1" />
                            <span className="text-[10px] leading-tight font-medium">
                              {logoLoadError ? "Gagal Memuat Logo" : "Belum Ada Logo"}
                            </span>
                            {logoLoadError && (
                              <button
                                type="button"
                                onClick={handleRemoveLogo}
                                className="mt-1 text-[9px] text-rose-500 hover:underline"
                              >
                                Reset Logo
                              </button>
                            )}
                          </div>
                        )}
                      </div>

                      <div className="flex-1 space-y-2 text-center sm:text-left">
                        <div className="flex items-center justify-center sm:justify-start gap-2.5">
                          <input
                            type="file"
                            ref={fileInputRef}
                            onChange={handleLogoChange}
                            accept="image/png,image/jpeg,image/jpg,image/webp,image/svg+xml"
                            className="hidden"
                            id="company-logo-file"
                          />
                          <label
                            htmlFor="company-logo-file"
                            className="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-brand-500 hover:bg-brand-600 text-white text-xs font-bold cursor-pointer transition-all shadow-xs active:scale-95"
                          >
                            <Upload className="h-3.5 w-3.5" />
                            <span>{logoPreview ? "Ganti Logo Usaha" : "Upload Logo Usaha"}</span>
                          </label>

                          {logoPreview && (
                            <button
                              type="button"
                              onClick={handleRemoveLogo}
                              className="inline-flex items-center gap-1 px-3 py-2 rounded-xl border border-rose-200 bg-rose-50 hover:bg-rose-100 text-rose-600 dark:border-rose-900/40 dark:bg-rose-950/40 dark:text-rose-300 text-xs font-semibold transition-all active:scale-95"
                            >
                              <X className="h-3.5 w-3.5" />
                              <span>Hapus</span>
                            </button>
                          )}
                        </div>
                        <p className="text-[11px] text-gray-500 dark:text-gray-400 leading-relaxed">
                          Format didukung: <strong>PNG, JPG, WebP, SVG</strong> (Maks. 3MB). Logo ini digunakan secara otomatis sebagai bukti pembayaran resmi (Invoice Digital &amp; Thermal), kuitansi cetak, dan branding header aplikasi.
                        </p>
                      </div>
                    </div>
                  </div>

                  {/* Company Details Inputs */}
                  <div className="grid gap-3.5 sm:grid-cols-2">
                    <div className="space-y-1.5">
                      <Label className="text-xs font-semibold text-gray-700 dark:text-gray-300">Nama Usaha / Brand / ISP</Label>
                      <input
                        value={companyForm.data.company_name}
                        onChange={(e) => companyForm.setData("company_name", e.target.value)}
                        placeholder="Contoh: PT Digital Net Solution / ISP Cyber"
                        className="w-full h-10 rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-950 px-3 text-xs text-gray-900 dark:text-white placeholder:text-gray-400 focus:outline-none focus:border-brand-500"
                        required
                      />
                    </div>

                    <div className="space-y-1.5">
                      <Label className="text-xs font-semibold text-gray-700 dark:text-gray-300">No. WhatsApp / Kontak CS</Label>
                      <input
                        value={companyForm.data.company_phone}
                        onChange={(e) => companyForm.setData("company_phone", e.target.value)}
                        placeholder="Contoh: 081234567890"
                        className="w-full h-10 rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-950 px-3 text-xs text-gray-900 dark:text-white placeholder:text-gray-400 focus:outline-none focus:border-brand-500"
                      />
                    </div>
                  </div>

                  <div className="grid gap-3.5 sm:grid-cols-2">
                    <div className="space-y-1.5">
                      <Label className="text-xs font-semibold text-gray-700 dark:text-gray-300">Email Usaha / Penagihan</Label>
                      <input
                        type="email"
                        value={companyForm.data.company_email}
                        onChange={(e) => companyForm.setData("company_email", e.target.value)}
                        placeholder="Contoh: billing@perusahaan.com"
                        className="w-full h-10 rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-950 px-3 text-xs text-gray-900 dark:text-white placeholder:text-gray-400 focus:outline-none focus:border-brand-500"
                      />
                    </div>

                    <div className="space-y-1.5">
                      <Label className="text-xs font-semibold text-gray-700 dark:text-gray-300">Alamat Lengkap Kantor / ISP</Label>
                      <input
                        value={companyForm.data.company_address}
                        onChange={(e) => companyForm.setData("company_address", e.target.value)}
                        placeholder="Contoh: Jl. Raya Jenderal Sudirman No. 88, Jakarta"
                        className="w-full h-10 rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-950 px-3 text-xs text-gray-900 dark:text-white placeholder:text-gray-400 focus:outline-none focus:border-brand-500"
                      />
                    </div>
                  </div>

                  <div className="pt-2 flex justify-end">
                    <button
                      type="submit"
                      disabled={companyForm.processing || isDemo}
                      className="rounded-xl h-10 px-5 text-xs font-bold bg-brand-500 hover:bg-brand-600 text-white transition-all disabled:opacity-50 inline-flex items-center gap-1.5 shadow-xs active:scale-95"
                    >
                      {companyForm.processing ? (
                        <>
                          <RefreshCw className="h-3.5 w-3.5 animate-spin" />
                          <span>Menyimpan...</span>
                        </>
                      ) : (
                        <>
                          <Save className="h-3.5 w-3.5" />
                          <span>Simpan Identitas Usaha</span>
                        </>
                      )}
                    </button>
                  </div>
                </form>
              </div>
            )}
          </div>

          {/* 2. Profil Administrator */}
          <div id="card-profile" className="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xs dark:border-gray-800 dark:bg-white/[0.03] scroll-mt-20">
            <button
              type="button"
              onClick={() => toggleCard("profile")}
              className="flex w-full items-center justify-between p-4 sm:p-5 text-left hover:bg-gray-50/50 dark:hover:bg-white/[0.02] transition-colors"
            >
              <div className="flex items-center gap-3.5 min-w-0">
                <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-500 dark:bg-brand-500/10 dark:text-brand-400">
                  <User className="h-5 w-5" />
                </div>
                <div className="min-w-0">
                  <h3 className="text-sm font-bold text-gray-900 dark:text-white">Profil Administrator</h3>
                  <p className="text-xs text-gray-500 dark:text-gray-400 mt-0.5 truncate">
                    Nama lengkap, username, email &amp; no WhatsApp (Tersinkron dengan Panel)
                  </p>
                </div>
              </div>

              <div className={cn("p-1 rounded-lg text-gray-400 transition-transform duration-200", openCard === "profile" && "rotate-180 text-gray-700 dark:text-gray-200")}>
                <ChevronDown className="h-4 w-4" />
              </div>
            </button>

            {openCard === "profile" && (
              <div className="border-t border-gray-100 dark:border-gray-800 p-4 sm:p-5">
                <form
                  onSubmit={(e) => {
                    e.preventDefault()
                    profile.post("/admin/my-settings/profile", { preserveScroll: true })
                  }}
                  className="space-y-4"
                >
                  <div className="grid gap-3 sm:grid-cols-2">
                    <div className="space-y-1.5">
                      <Label className="text-xs font-semibold text-gray-700 dark:text-gray-300">Nama Lengkap</Label>
                      <input
                        value={profile.data.name}
                        onChange={(e) => profile.setData("name", e.target.value)}
                        className="w-full h-10 rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-950 px-3 text-xs text-gray-900 dark:text-white placeholder:text-gray-400 focus:outline-none focus:border-brand-500"
                      />
                    </div>
                    <div className="space-y-1.5">
                      <Label className="text-xs font-semibold text-gray-700 dark:text-gray-300">Username Login</Label>
                      <input
                        value={profile.data.username}
                        onChange={(e) => profile.setData("username", e.target.value)}
                        className="w-full h-10 rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-950 px-3 text-xs text-gray-900 dark:text-white font-mono placeholder:text-gray-400 focus:outline-none focus:border-brand-500"
                      />
                    </div>
                  </div>

                  <div className="grid gap-3 sm:grid-cols-2">
                    <div className="space-y-1.5">
                      <div className="flex items-center justify-between">
                        <Label className="text-xs font-semibold text-gray-700 dark:text-gray-300">Email</Label>
                        <span className="text-[10px] text-brand-500 font-semibold">Sinkron ke Panel Klien</span>
                      </div>
                      <input
                        type="email"
                        value={profile.data.email}
                        onChange={(e) => profile.setData("email", e.target.value)}
                        className="w-full h-10 rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-950 px-3 text-xs text-gray-900 dark:text-white placeholder:text-gray-400 focus:outline-none focus:border-brand-500"
                      />
                    </div>
                    <div className="space-y-1.5">
                      <div className="flex items-center justify-between">
                        <Label className="text-xs font-semibold text-gray-700 dark:text-gray-300">No. WhatsApp</Label>
                        <span className="text-[10px] text-brand-500 font-semibold">Sinkron ke Panel Klien</span>
                      </div>
                      <input
                        value={profile.data.phone}
                        onChange={(e) => profile.setData("phone", e.target.value)}
                        className="w-full h-10 rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-950 px-3 text-xs text-gray-900 dark:text-white placeholder:text-gray-400 focus:outline-none focus:border-brand-500"
                      />
                    </div>
                  </div>

                  <div className="pt-2 flex justify-end">
                    <button
                      type="submit"
                      disabled={profile.processing || isDemo}
                      className="rounded-xl h-10 px-5 text-xs font-bold bg-brand-500 hover:bg-brand-600 text-white transition-all disabled:opacity-50"
                    >
                      {profile.processing ? "Menyimpan..." : "Simpan Profil"}
                    </button>
                  </div>
                </form>
              </div>
            )}
          </div>

          {/* 3. Metode Pembayaran Pelanggan */}
          <div id="card-payment-methods" className="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xs dark:border-gray-800 dark:bg-white/[0.03] scroll-mt-20">
            <button
              type="button"
              onClick={() => toggleCard("payment_methods")}
              className="flex w-full items-center justify-between p-4 sm:p-5 text-left hover:bg-gray-50/50 dark:hover:bg-white/[0.02] transition-colors"
            >
              <div className="flex items-center gap-3.5 min-w-0">
                <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-500 dark:bg-brand-500/10 dark:text-brand-400">
                  <CreditCard className="h-5 w-5" />
                </div>
                <div className="min-w-0">
                  <h3 className="text-sm font-bold text-gray-900 dark:text-white">Metode Pembayaran Pelanggan</h3>
                  <p className="text-xs text-gray-500 dark:text-gray-400 mt-0.5 truncate">
                    Atur metode pembayaran apa saja yang aktif ditampilkan di portal tagihan invoice pelanggan (/pay)
                  </p>
                </div>
              </div>

              <div className={cn("p-1 rounded-lg text-gray-400 transition-transform duration-200", openCard === "payment_methods" && "rotate-180 text-gray-700 dark:text-gray-200")}>
                <ChevronDown className="h-4 w-4" />
              </div>
            </button>

            {openCard === "payment_methods" && (
              <div className="border-t border-gray-100 dark:border-gray-800 p-4 sm:p-5 space-y-4">
                <form
                  onSubmit={(e) => {
                    e.preventDefault()
                    paymentMethodsForm.post("/admin/my-settings/payment-methods", {
                      preserveScroll: true,
                      preserveState: true,
                    })
                  }}
                  className="space-y-4"
                >
                  <div className="grid gap-3 sm:grid-cols-2">
                    {/* Switch 1: Payment Gateway Otomatis */}
                    <div className="flex items-start justify-between gap-3 rounded-xl border border-gray-200 bg-gray-50/50 dark:border-gray-800 dark:bg-gray-900/50 p-3.5">
                      <div className="space-y-1 pr-2">
                        <div className="flex items-center gap-1.5">
                          <CreditCard className="h-3.5 w-3.5 text-brand-500" />
                          <span className="text-xs font-bold text-gray-900 dark:text-white">Payment Gateway Otomatis</span>
                        </div>
                        <p className="text-[11px] text-gray-500 dark:text-gray-400 leading-relaxed">
                          QRIS Realtime 0-detik &amp; Virtual Account dari NODERA PAY / WijayaPay / TriPay / Midtrans / Duitku / Xendit.
                        </p>
                        <div className="pt-1">
                          <Link
                            href="/admin/payments/gateway"
                            className="inline-flex items-center gap-1 text-[11px] font-medium text-brand-600 dark:text-brand-400 hover:underline"
                          >
                            <span>Atur Kredensial &amp; Akun Gateway</span>
                            <span>&rarr;</span>
                          </Link>
                        </div>
                      </div>
                      <Switch
                        checked={paymentMethodsForm.data.enable_gateway}
                        onCheckedChange={(checked) => paymentMethodsForm.setData("enable_gateway", checked)}
                      />
                    </div>

                    {/* Switch 2: Transfer Bank Manual */}
                    <div className="flex items-start justify-between gap-3 rounded-xl border border-gray-200 bg-gray-50/50 dark:border-gray-800 dark:bg-gray-900/50 p-3.5">
                      <div className="space-y-1 pr-2">
                        <div className="flex items-center gap-1.5">
                          <Landmark className="h-3.5 w-3.5 text-emerald-500" />
                          <span className="text-xs font-bold text-gray-900 dark:text-white">Transfer Bank Manual</span>
                        </div>
                        <p className="text-[11px] text-gray-500 dark:text-gray-400 leading-relaxed">
                          Nomor rekening bank instansi untuk transfer manual pelanggan &amp; verifikasi kasir.
                        </p>
                      </div>
                      <Switch
                        checked={paymentMethodsForm.data.enable_bank_manual}
                        onCheckedChange={(checked) => paymentMethodsForm.setData("enable_bank_manual", checked)}
                      />
                    </div>
                  </div>

                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-3 border-t border-gray-100 dark:border-gray-800">
                    <div className="flex flex-col gap-1.5">
                      <Label className="text-xs text-gray-700 dark:text-gray-300 font-semibold flex items-center gap-1.5">
                        <Clock className="h-3.5 w-3.5 text-brand-500" />
                        <span>Batas Waktu Pembayaran (Waktu Tunggu):</span>
                      </Label>
                      <div className="flex items-center gap-2">
                        <input
                          type="number"
                          min="1"
                          max="1440"
                          value={paymentMethodsForm.data.expiry_minutes}
                          onChange={(e) => paymentMethodsForm.setData("expiry_minutes", parseInt(e.target.value) || 15)}
                          className="h-8 w-24 rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-950 px-3 text-xs text-gray-900 dark:text-white focus:outline-none focus:border-brand-500 font-mono text-center font-bold"
                        />
                        <span className="text-xs text-gray-500 dark:text-gray-400">Menit (Default: 15 Menit)</span>
                      </div>
                      <p className="text-[11px] text-gray-400">
                        Waktu kedaluwarsa sesi pembayaran invoice QRIS / Virtual Account pelanggan.
                      </p>
                    </div>

                    <div className="flex flex-col gap-1.5">
                      <div className="flex items-center justify-between">
                        <Label className="text-xs text-gray-700 dark:text-gray-300 font-semibold flex items-center gap-1.5">
                          <Globe className="h-3.5 w-3.5 text-brand-500" />
                          <span>Payment Gateway Utama Pelanggan:</span>
                        </Label>
                        <Link
                          href="/admin/payments/gateway"
                          className="text-[11px] font-semibold text-brand-500 hover:underline flex items-center gap-1"
                        >
                          <span>Kelola Gateway</span>
                          <ArrowUpRight className="h-3 w-3" />
                        </Link>
                      </div>
                      {configuredGateways && configuredGateways.length > 0 ? (
                        <select
                          value={paymentMethodsForm.data.default_gateway}
                          onChange={(e) => paymentMethodsForm.setData("default_gateway", e.target.value)}
                          className="h-8 rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-950 px-2.5 text-xs text-gray-900 dark:text-white focus:outline-none focus:border-brand-500"
                        >
                          {configuredGateways.map((gw) => (
                            <option key={gw.gateway} value={gw.gateway}>
                              {gw.name}
                            </option>
                          ))}
                        </select>
                      ) : (
                        <div className="space-y-2">
                          <select
                            value=""
                            disabled
                            className="h-8 w-full rounded-lg border border-dashed border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-900 px-2.5 text-xs text-gray-400 cursor-not-allowed"
                          >
                            <option value="">-- Belum ada payment gateway yang ditambahkan --</option>
                          </select>
                          <div className="flex items-center justify-between rounded-xl border border-amber-200 bg-amber-50 dark:border-amber-900/50 dark:bg-amber-950/40 p-2.5 text-xs text-amber-800 dark:text-amber-200">
                            <div className="flex items-center gap-2">
                              <AlertTriangle className="h-4 w-4 shrink-0 text-amber-500" />
                              <span>Belum ada payment gateway yang dikonfigurasi.</span>
                            </div>
                            <Link
                              href="/admin/payments/gateway"
                              className="inline-flex items-center gap-1 rounded-lg bg-brand-500 px-2.5 py-1 text-[11px] font-bold text-white hover:bg-brand-600 transition-all whitespace-nowrap"
                            >
                              <span>+ Tambah Gateway</span>
                            </Link>
                          </div>
                        </div>
                      )}
                      <p className="text-[11px] text-gray-400">
                        Gateway pembayaran prioritas yang digunakan saat pelanggan membuka link pembayaran invoice / tagihan.
                      </p>
                    </div>
                  </div>

                  <div className="flex justify-end pt-2">
                    <button
                      type="submit"
                      disabled={paymentMethodsForm.processing}
                      className="flex h-9 items-center justify-center gap-1.5 rounded-xl bg-brand-500 hover:bg-brand-600 px-4 text-xs font-bold text-white transition-all disabled:opacity-50 active:scale-95"
                    >
                      <Save className="h-3.5 w-3.5" />
                      <span>{paymentMethodsForm.processing ? "Menyimpan..." : "Simpan Pengaturan Pembayaran"}</span>
                    </button>
                  </div>
                </form>
              </div>
            )}
          </div>

          {/* 4. Rekening Bank Pembayaran */}
          <div id="card-bank" className="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xs dark:border-gray-800 dark:bg-white/[0.03] scroll-mt-20">
            <button
              type="button"
              onClick={() => toggleCard("bank")}
              className="flex w-full items-center justify-between p-4 sm:p-5 text-left hover:bg-gray-50/50 dark:hover:bg-white/[0.02] transition-colors"
            >
              <div className="flex items-center gap-3.5 min-w-0">
                <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400">
                  <Banknote className="h-5 w-5" />
                </div>
                <div className="min-w-0">
                  <h3 className="text-sm font-bold text-gray-900 dark:text-white">Rekening Bank Pembayaran</h3>
                  <p className="text-xs text-gray-500 dark:text-gray-400 mt-0.5 truncate">
                    Rekening tujuan transfer manual pelanggan ({bankAccounts.length} Rekening)
                  </p>
                </div>
              </div>

              <div className={cn("p-1 rounded-lg text-gray-400 transition-transform duration-200", openCard === "bank" && "rotate-180 text-gray-700 dark:text-gray-200")}>
                <ChevronDown className="h-4 w-4" />
              </div>
            </button>

            {openCard === "bank" && (
              <div className="border-t border-gray-100 dark:border-gray-800 p-4 sm:p-5 space-y-4">
                {/* Bank list */}
                <div className="space-y-2">
                  {bankAccounts.map((b) => (
                    <div key={b.id} className="flex items-center justify-between p-3 rounded-xl border border-gray-200 bg-gray-50/50 dark:border-gray-800 dark:bg-gray-900/50">
                      <div>
                        <p className="text-xs font-bold text-gray-900 dark:text-white">{b.bank_name} - <span className="font-mono">{b.account_number}</span></p>
                        <p className="text-[11px] text-gray-500 dark:text-gray-400">a.n. {b.account_name}</p>
                      </div>
                      <button
                        type="button"
                        onClick={() => setManageBank(b)}
                        className="h-9 px-3.5 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:hover:bg-gray-800 text-xs font-bold text-gray-700 dark:text-gray-200 transition shadow-2xs inline-flex items-center gap-1.5 cursor-pointer"
                      >
                        <SlidersHorizontal className="size-3.5 text-brand-500" />
                        Kelola
                      </button>
                    </div>
                  ))}
                </div>

                {/* Add bank form */}
                <form
                  onSubmit={(e) => {
                    e.preventDefault()
                    bank.post("/admin/my-settings/bank/store", {
                      preserveScroll: true,
                      onSuccess: () => bank.reset(),
                    })
                  }}
                  className="pt-2 border-t border-gray-100 dark:border-gray-800 space-y-3"
                >
                  <div className="grid gap-3 sm:grid-cols-3">
                    <input
                      placeholder="Nama Bank (cth: BCA / BRI)"
                      value={bank.data.bank_name}
                      onChange={(e) => bank.setData("bank_name", e.target.value)}
                      className="h-10 rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-950 px-3 text-xs text-gray-900 dark:text-white placeholder:text-gray-400 focus:outline-none focus:border-brand-500"
                    />
                    <input
                      placeholder="Nomor Rekening"
                      value={bank.data.account_number}
                      onChange={(e) => bank.setData("account_number", e.target.value)}
                      className="h-10 rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-950 px-3 text-xs text-gray-900 dark:text-white font-mono placeholder:text-gray-400 focus:outline-none focus:border-brand-500"
                    />
                    <input
                      placeholder="Atas Nama (a.n.)"
                      value={bank.data.account_name}
                      onChange={(e) => bank.setData("account_name", e.target.value)}
                      className="h-10 rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-950 px-3 text-xs text-gray-900 dark:text-white placeholder:text-gray-400 focus:outline-none focus:border-brand-500"
                    />
                  </div>
                  <div className="flex justify-end">
                    <button
                      type="submit"
                      disabled={bank.processing || isDemo}
                      className="rounded-xl h-9 px-4 text-xs font-bold bg-brand-500 hover:bg-brand-600 text-white transition-all flex items-center shadow-xs"
                    >
                      <Plus className="h-3.5 w-3.5 mr-1" /> Tambah Rekening
                    </button>
                  </div>
                </form>
              </div>
            )}
          </div>

          {/* 5. Keamanan & Password */}
          <div id="card-password" className="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xs dark:border-gray-800 dark:bg-white/[0.03] scroll-mt-20">
            <button
              type="button"
              onClick={() => toggleCard("password")}
              className="flex w-full items-center justify-between p-4 sm:p-5 text-left hover:bg-gray-50/50 dark:hover:bg-white/[0.02] transition-colors"
            >
              <div className="flex items-center gap-3.5 min-w-0">
                <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400">
                  <Lock className="h-5 w-5" />
                </div>
                <div className="min-w-0">
                  <h3 className="text-sm font-bold text-gray-900 dark:text-white">Keamanan &amp; Password</h3>
                  <p className="text-xs text-gray-500 dark:text-gray-400 mt-0.5 truncate">
                    Perbarui kata sandi akun admin (Otomatis sinkron ke akun Panel)
                  </p>
                </div>
              </div>

              <div className={cn("p-1 rounded-lg text-gray-400 transition-transform duration-200", openCard === "password" && "rotate-180 text-gray-700 dark:text-gray-200")}>
                <ChevronDown className="h-4 w-4" />
              </div>
            </button>

            {openCard === "password" && (
              <div className="border-t border-gray-100 dark:border-gray-800 p-4 sm:p-5">
                <form
                  onSubmit={(e) => {
                    e.preventDefault()
                    password.post("/admin/my-settings/password", {
                      preserveScroll: true,
                      onSuccess: () => password.reset(),
                    })
                  }}
                  className="space-y-4"
                >
                  <div className="space-y-1.5">
                    <Label className="text-xs font-semibold text-gray-700 dark:text-gray-300">Password Saat Ini</Label>
                    <input
                      type="password"
                      value={password.data.current_password}
                      onChange={(e) => password.setData("current_password", e.target.value)}
                      className="w-full h-10 rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-950 px-3 text-xs text-gray-900 dark:text-white placeholder:text-gray-400 focus:outline-none focus:border-brand-500"
                    />
                  </div>

                  <div className="grid gap-3 sm:grid-cols-2">
                    <div className="space-y-1.5">
                      <Label className="text-xs font-semibold text-gray-700 dark:text-gray-300">Password Baru</Label>
                      <input
                        type="password"
                        value={password.data.new_password}
                        onChange={(e) => password.setData("new_password", e.target.value)}
                        className="w-full h-10 rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-950 px-3 text-xs text-gray-900 dark:text-white placeholder:text-gray-400 focus:outline-none focus:border-brand-500"
                      />
                    </div>
                    <div className="space-y-1.5">
                      <Label className="text-xs font-semibold text-gray-700 dark:text-gray-300">Konfirmasi Password Baru</Label>
                      <input
                        type="password"
                        value={password.data.new_password_confirmation}
                        onChange={(e) => password.setData("new_password_confirmation", e.target.value)}
                        className="w-full h-10 rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-950 px-3 text-xs text-gray-900 dark:text-white placeholder:text-gray-400 focus:outline-none focus:border-brand-500"
                      />
                    </div>
                  </div>

                  <div className="pt-2 flex justify-end">
                    <button
                      type="submit"
                      disabled={password.processing || isDemo}
                      className="rounded-xl h-10 px-5 text-xs font-bold bg-brand-500 hover:bg-brand-600 text-white transition-all disabled:opacity-50"
                    >
                      {password.processing ? "Menyimpan..." : "Ubah Password"}
                    </button>
                  </div>
                </form>
              </div>
            )}
          </div>

          {/* 6. Upgrade & Perpanjang Paket Layanan */}
          <div id="card-upgrade" className="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xs dark:border-gray-800 dark:bg-white/[0.03] scroll-mt-20">
            <button
              type="button"
              onClick={() => toggleCard("upgrade")}
              className="flex w-full items-center justify-between p-4 sm:p-5 text-left hover:bg-gray-50/50 dark:hover:bg-white/[0.02] transition-colors"
            >
              <div className="flex items-center gap-3.5 min-w-0">
                <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400">
                  <Activity className="h-5 w-5" />
                </div>
                <div className="min-w-0">
                  <div className="flex items-center gap-2">
                    <h3 className="text-sm font-bold text-gray-900 dark:text-white">Upgrade &amp; Perpanjang Paket Layanan</h3>
                    {openCard === "upgrade" && (
                      <span className="text-[10px] px-2 py-0.5 rounded bg-amber-600 text-white font-bold">
                        {activePackage}
                      </span>
                    )}
                  </div>
                  <p className="text-xs text-gray-500 dark:text-gray-400 mt-0.5 truncate">
                    Pilih paket layanan &amp; durasi langganan via Saldo Master Wallet
                  </p>
                </div>
              </div>

              <div className={cn("p-1 rounded-lg text-gray-400 transition-transform duration-200", openCard === "upgrade" && "rotate-180 text-gray-700 dark:text-gray-200")}>
                <ChevronDown className="h-4 w-4" />
              </div>
            </button>

            {openCard === "upgrade" && (
              <div className="border-t border-gray-100 dark:border-gray-800 p-4 sm:p-5">
                <form onSubmit={handleUpgradeSubmit} className="space-y-5">
                  <div className="grid gap-3.5 sm:grid-cols-2">
                    <div className="space-y-1.5">
                      <Label className="text-xs font-semibold text-gray-700 dark:text-gray-300">
                        Pilih Paket Layanan <span className="text-rose-500">*</span>
                      </Label>
                      <select
                        value={upgradeForm.data.package_id}
                        onChange={(e) => {
                          const newPkgId = Number(e.target.value)
                          const pkg = availablePackages.find((item) => item.id === newPkgId)
                          const pkgDurations = pkg?.duration_options
                            ? pkg.duration_options.split(",").map((s) => Number(s.trim())).filter((n) => !isNaN(n) && n > 0)
                            : [1, 3, 6, 12]
                          const nextDuration = pkgDurations.includes(upgradeForm.data.duration) ? upgradeForm.data.duration : (pkgDurations[0] ?? 1)
                          upgradeForm.setData((prev) => ({ ...prev, package_id: newPkgId, duration: nextDuration }))
                        }}
                        className="w-full h-10 rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-950 px-3 text-xs font-semibold text-gray-900 dark:text-white focus:outline-none focus:border-brand-500"
                      >
                        {availablePackages.map((pkg) => (
                          <option key={pkg.id} value={pkg.id}>
                            {pkg.name} — {formatIDR(pkg.monthly_price)} / bln ({pkg.max_customers > 0 ? `${pkg.max_customers} Cust` : "Unlimited"})
                          </option>
                        ))}
                      </select>
                    </div>

                    <div className="space-y-1.5">
                      <Label className="text-xs font-semibold text-gray-700 dark:text-gray-300">
                        Masa Aktif Langganan <span className="text-rose-500">*</span>
                      </Label>
                      <select
                        value={upgradeForm.data.duration}
                        onChange={(e) => upgradeForm.setData("duration", Number(e.target.value))}
                        className="w-full h-10 rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-950 px-3 text-xs font-semibold text-gray-900 dark:text-white focus:outline-none focus:border-brand-500"
                      >
                        {availableDurations.map((dur) => (
                          <option key={dur} value={dur}>
                            {durationLabels[dur] || `${dur} Bulan`} — {formatIDR(calculatePrice(selectedPkg, dur))}
                          </option>
                        ))}
                      </select>
                    </div>
                  </div>

                  {selectedPkg && (
                    <div className="rounded-xl border border-gray-200 bg-gray-50/50 dark:border-gray-800 dark:bg-gray-900/50 p-4 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 text-xs">
                      <div className="flex items-center gap-3">
                        <div className="p-2 rounded-xl bg-brand-50 text-brand-500 dark:bg-brand-500/10 dark:text-brand-400 shrink-0">
                          <Activity className="h-3.5 w-3.5" />
                        </div>
                        <div>
                          <div className="flex items-center gap-2">
                            <span className="font-bold text-gray-900 dark:text-white text-sm">{selectedPkg.name}</span>
                            <span className="font-mono text-emerald-600 dark:text-emerald-400 font-bold text-xs">
                              {formatIDR(selectedPkg.monthly_price)} / bulan
                            </span>
                          </div>
                          <p className="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5">
                            {selectedPkg.description || "Paket langganan resmi NODERA Cloud ISP Platform"}
                          </p>
                        </div>
                      </div>
                      <div className="flex items-center gap-3 text-[11px] text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 px-3 py-1.5 rounded-xl border border-gray-200 dark:border-gray-700">
                        <span>Pelanggan: <strong className="text-gray-900 dark:text-white">{selectedPkg.max_customers > 0 ? `${selectedPkg.max_customers} User` : "Unlimited"}</strong></span>
                        <span>•</span>
                        <span>Router: <strong className="text-gray-900 dark:text-white">{selectedPkg.max_routers > 0 ? `${selectedPkg.max_routers} Unit` : "Unlimited"}</strong></span>
                      </div>
                    </div>
                  )}

                  <div className="rounded-xl border border-gray-200 bg-gray-50/50 dark:border-gray-800 dark:bg-gray-900/50 p-4 sm:p-5 space-y-4">
                    <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 pb-3 border-b border-gray-200 dark:border-gray-800">
                      <div>
                        <span className="text-xs text-gray-500 dark:text-gray-400">Total Biaya Perpanjangan ({upgradeForm.data.duration} Bulan):</span>
                        <div className="text-lg font-bold font-mono text-gray-900 dark:text-white mt-0.5">
                          {formatIDR(calculatedTotal)}
                        </div>
                      </div>

                      <div className="text-left sm:text-right">
                        <span className="text-xs text-gray-500 dark:text-gray-400">Saldo Master Wallet Anda:</span>
                        <div className="text-lg font-bold font-mono text-emerald-600 dark:text-emerald-400 mt-0.5">
                          {formatIDR(masterSaldo)}
                        </div>
                      </div>
                    </div>

                    {isSaldoSufficient ? (
                      <div className="p-3.5 rounded-xl bg-emerald-50 border border-emerald-200 dark:bg-emerald-950/40 dark:border-emerald-500/30 text-xs text-emerald-800 dark:text-emerald-300 flex items-start sm:items-center justify-between gap-3 flex-wrap">
                        <div className="flex items-center gap-2.5">
                          <CheckCircle2 className="h-4 w-4 shrink-0 text-emerald-600 dark:text-emerald-400" />
                          <span>Saldo Master Wallet Anda mencukupi. Biaya akan dipotong langsung dan paket aktif seketika.</span>
                        </div>
                        <div className="text-[11px] font-mono">
                          Sisa saldo: <strong className="text-emerald-600 dark:text-emerald-400">{formatIDR(masterSaldo - calculatedTotal)}</strong>
                        </div>
                      </div>
                    ) : (
                      <div className="p-3.5 rounded-xl bg-amber-50 border border-amber-200 dark:bg-amber-950/40 dark:border-amber-500/30 text-xs text-amber-800 dark:text-amber-300 space-y-2.5">
                        <div className="flex items-start gap-2.5">
                          <AlertTriangle className="h-4 w-4 shrink-0 text-amber-600 dark:text-amber-400 mt-0.5" />
                          <div>
                            <p className="font-semibold">
                              Saldo Master Wallet tidak mencukupi untuk melakukan upgrade/perpanjangan paket ini.
                            </p>
                            <p className="text-[11px] mt-0.5">
                              Diperlukan <strong>{formatIDR(calculatedTotal)}</strong>, saldo aktif <strong>{formatIDR(masterSaldo)}</strong> (Kurang <strong>{formatIDR(calculatedTotal - masterSaldo)}</strong>).
                            </p>
                          </div>
                        </div>
                        <div className="pt-1 flex items-center gap-3">
                          <Link
                            href="/admin/topup/create"
                            className="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-lg bg-amber-500 hover:bg-amber-600 text-white font-bold text-xs transition-all shadow-xs active:scale-95 whitespace-nowrap"
                          >
                            <Plus className="h-3.5 w-3.5" />
                            <span>Isi Saldo (Top Up Sekarang)</span>
                            <ArrowUpRight className="h-3.5 w-3.5" />
                          </Link>
                        </div>
                      </div>
                    )}
                  </div>

                  <div className="pt-2 flex flex-col sm:flex-row gap-3 justify-between items-center border-t border-gray-100 dark:border-gray-800">
                    <a
                      href={upgradeWaUrl}
                      target="_blank"
                      rel="noopener noreferrer"
                      className="text-xs text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white inline-flex items-center gap-1"
                    >
                      <span>Butuh paket custom? Hubungi Superadmin via WhatsApp</span>
                      <ArrowUpRight className="h-3 w-3" />
                    </a>

                    <button
                      type="submit"
                      disabled={upgradeForm.processing || !isSaldoSufficient || isDemo}
                      className="w-full sm:w-auto px-6 py-2.5 rounded-xl text-xs font-bold bg-brand-500 hover:bg-brand-600 text-white shadow-xs active:scale-95 transition-all disabled:opacity-50 flex items-center justify-center gap-2"
                    >
                      {upgradeForm.processing ? (
                        <>
                          <RefreshCw className="h-3.5 w-3.5 animate-spin" />
                          <span>Memproses Pembayaran...</span>
                        </>
                      ) : (
                        <>
                          <Activity className="h-3.5 w-3.5" />
                          <span>Bayar &amp; Perpanjang Paket ({formatIDR(calculatedTotal)})</span>
                        </>
                      )}
                    </button>
                  </div>
                </form>
              </div>
            )}
          </div>

          {/* 7. Changelog & Catatan Rilis Sistem */}
          <div id="card-changelog" className="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xs dark:border-gray-800 dark:bg-white/[0.03] scroll-mt-20">
            <button
              type="button"
              onClick={() => toggleCard("changelog")}
              className="flex w-full items-center justify-between p-4 sm:p-5 text-left hover:bg-gray-50/50 dark:hover:bg-white/[0.02] transition-colors"
            >
              <div className="flex items-center gap-3.5 min-w-0">
                <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-500 dark:bg-brand-500/10 dark:text-brand-400">
                  <Activity className="h-3.5 w-3.5" />
                </div>
                <div className="min-w-0">
                  <div className="flex items-center gap-2 flex-wrap">
                    <h3 className="text-sm font-bold text-gray-900 dark:text-white">Changelog &amp; Catatan Rilis Sistem</h3>
                    {openCard === "changelog" && (
                      <span className="font-mono text-[10px] px-2 py-0.5 rounded bg-brand-500 text-white font-bold">
                        v{systemVersion?.version || "2.5.0"}
                      </span>
                    )}
                  </div>
                  <p className="text-xs text-gray-500 dark:text-gray-400 mt-0.5 truncate">
                    {systemVersion?.release_date ? `Rilis: ${formatDate(systemVersion.release_date)}` : "Pembaruan Terkini"} • {systemVersion?.codename || "Enterprise Edition"}
                  </p>
                </div>
              </div>

              <div className={cn("p-1 rounded-lg text-gray-400 transition-transform duration-200", openCard === "changelog" && "rotate-180 text-gray-700 dark:text-gray-200")}>
                <ChevronDown className="h-4 w-4" />
              </div>
            </button>

            {openCard === "changelog" && (
              <div className="border-t border-gray-100 dark:border-gray-800 p-4 sm:p-5 space-y-4">
                <div className="rounded-xl border border-gray-200 bg-gray-50/50 dark:border-gray-800 dark:bg-gray-900/50 p-4 space-y-3">
                  <div className="flex items-center justify-between flex-wrap gap-2 pb-2 border-b border-gray-200 dark:border-gray-800">
                    <h4 className="text-xs font-bold text-brand-500 uppercase tracking-wider">
                      Fitur &amp; Peningkatan Rilis Terkini
                    </h4>
                    <span className="text-[11px] text-gray-500 dark:text-gray-400 font-mono">
                      {systemVersion?.codename || "Enterprise ISP & Multi-OLT NMS Edition"}
                    </span>
                  </div>

                  <ul className="space-y-2.5 text-xs text-gray-700 dark:text-gray-300">
                    {(systemVersion?.changelog && systemVersion.changelog.length > 0) ? (
                      systemVersion.changelog.map((item, idx) => (
                        <li key={idx} className="flex items-start gap-2.5 leading-relaxed">
                          <div className="flex h-4 w-4 shrink-0 items-center justify-center rounded-full bg-emerald-500 text-white mt-0.5 whitespace-nowrap">
                            <Check className="h-2.5 w-2.5" />
                          </div>
                          <span>{item}</span>
                        </li>
                      ))
                    ) : (
                      <li className="text-gray-400 italic">Belum ada catatan rilis tambahan.</li>
                    )}
                  </ul>
                </div>

                <div className="rounded-xl border border-gray-200 dark:border-gray-800 p-3.5 text-xs text-gray-500 dark:text-gray-400 flex items-center justify-between flex-wrap gap-2">
                  <span>Status Lisensi Sistem: <strong className="text-gray-900 dark:text-white font-mono">Aktif &amp; Terhubung</strong></span>
                  <span className="text-emerald-600 dark:text-emerald-400 font-semibold flex items-center gap-1.5">
                    <CheckCircle2 className="h-3.5 w-3.5" /> Tersinkronisasi Otomatis
                  </span>
                </div>
              </div>
            )}
          </div>

          {/* 8. System Updater (Hanya di Standalone Mode) */}
          {systemVersion?.is_standalone && (
            <SystemUpdaterCard
              initialVersion={systemVersion}
              isOpen={openCard === "updater"}
              onToggle={() => toggleCard("updater")}
            />
          )}

          {/* 9. Riwayat & Top Up Saldo Master Wallet */}
          <div id="card-topup" className="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xs dark:border-gray-800 dark:bg-white/[0.03] scroll-mt-20">
            <button
              type="button"
              onClick={() => toggleCard("topup")}
              className="flex w-full items-center justify-between p-4 sm:p-5 text-left hover:bg-gray-50/50 dark:hover:bg-white/[0.02] transition-colors"
            >
              <div className="flex items-center gap-3.5 min-w-0">
                <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400">
                  <Wallet className="h-5 w-5" />
                </div>
                <div className="min-w-0">
                  <div className="flex items-center gap-2">
                    <h3 className="text-sm font-bold text-gray-900 dark:text-white">Riwayat &amp; Top Up Saldo Master Wallet</h3>
                    {openCard === "topup" && pendingTopup && (
                      <span className="text-[10px] px-2 py-0.5 rounded bg-amber-600 text-white font-bold animate-pulse">
                        Ada Topup Pending
                      </span>
                    )}
                  </div>
                  <p className="text-xs text-gray-500 dark:text-gray-400 mt-0.5 truncate">
                    Saldo Master: <strong className="text-emerald-600 dark:text-emerald-400 font-mono">{formatIDR(masterSaldo)}</strong> • Kelola pengisian saldo &amp; log transaksi pembayaran cloud
                  </p>
                </div>
              </div>

              <div className={cn("p-1 rounded-lg text-gray-400 transition-transform duration-200", openCard === "topup" && "rotate-180 text-gray-700 dark:text-gray-200")}>
                <ChevronDown className="h-4 w-4" />
              </div>
            </button>

            {openCard === "topup" && (
              <div className="border-t border-gray-100 dark:border-gray-800 p-4 sm:p-5 space-y-4">
                <div className="rounded-xl border border-gray-200 bg-gray-50/50 dark:border-gray-800 dark:bg-gray-900/50 p-4 flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-4">
                  <div className="flex items-center gap-3.5">
                    <div className="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-500 dark:bg-brand-500/10 dark:text-brand-400">
                      <Wallet className="h-5 w-5" />
                    </div>
                    <div>
                      <div className="flex items-center gap-2">
                        <span className="text-xs text-gray-500 dark:text-gray-400">Saldo Master Wallet Aktif</span>
                        {vpnUser && (
                          <span className="text-[10px] px-2 py-0.5 rounded bg-emerald-600 text-white font-bold">
                            Tersinkronisasi
                          </span>
                        )}
                      </div>
                      <div className="text-xl font-bold font-mono text-emerald-600 dark:text-emerald-400 mt-0.5">
                        {formatIDR(masterSaldo)}
                      </div>
                    </div>
                  </div>

                  <div className="flex items-center gap-2.5">
                    <Link
                      href="/admin/topup/create"
                      className="w-full sm:w-auto inline-flex items-center justify-center gap-1.5 rounded-xl bg-brand-500 hover:bg-brand-600 text-white px-4 py-2.5 text-xs font-bold transition-all shadow-xs active:scale-95"
                    >
                      <Plus className="h-4 w-4" />
                      <span>Top Up Saldo Sekarang</span>
                    </Link>
                  </div>
                </div>

                {pendingTopup && (
                  <div className="rounded-xl border border-amber-200 bg-amber-50 dark:border-amber-900/50 dark:bg-amber-950/40 p-4 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3.5">
                    <div className="flex items-center gap-3">
                      <div className="p-2.5 rounded-xl bg-amber-500 text-white shrink-0">
                        <Clock className="h-5 w-5" />
                      </div>
                      <div>
                        <div className="flex items-center gap-2">
                          <h4 className="text-xs font-bold text-amber-900 dark:text-amber-200">Menunggu Pembayaran Topup</h4>
                          <span className="font-mono text-[10px] text-amber-700 dark:text-amber-300 font-bold">
                            {pendingTopup.invoice_number}
                          </span>
                        </div>
                        <p className="text-xs text-gray-700 dark:text-gray-300 mt-0.5">
                          Nominal: <strong className="font-mono">{formatIDR(pendingTopup.total_amount || pendingTopup.amount)}</strong> via <span className="font-semibold">{pendingTopup.bank_destination}</span>
                        </p>
                      </div>
                    </div>

                    <Link
                      href={`/admin/topup/confirm/${pendingTopup.id}`}
                      className="w-full sm:w-auto shrink-0 inline-flex items-center justify-center gap-1.5 rounded-xl bg-amber-500 hover:bg-amber-600 text-white font-bold px-4 py-2 text-xs transition-all active:scale-95 shadow-xs"
                    >
                      <span>Lanjutkan Pembayaran</span>
                      <ArrowUpRight className="h-3.5 w-3.5" />
                    </Link>
                  </div>
                )}

                {/* History Table */}
                <div className="space-y-2">
                  <div className="flex items-center justify-between pb-1">
                    <h4 className="text-xs font-bold text-gray-900 dark:text-white uppercase tracking-wider flex items-center gap-1.5">
                      <History className="h-3.5 w-3.5 text-brand-500" />
                      <span>Riwayat Pengisian Saldo</span>
                    </h4>
                    <span className="text-[11px] text-gray-500 dark:text-gray-400">
                      {topupHistory.length} Transaksi Terakhir
                    </span>
                  </div>

                  {topupHistory.length === 0 ? (
                    <div className="rounded-xl border border-dashed border-gray-200 dark:border-gray-800 p-8 text-center space-y-2">
                      <Receipt className="h-8 w-8 text-gray-400 mx-auto" />
                      <p className="text-xs text-gray-500 dark:text-gray-400">Belum ada riwayat permintaan topup saldo.</p>
                      <Link
                        href="/admin/topup/create"
                        className="inline-flex items-center gap-1.5 text-xs font-semibold text-brand-500 hover:underline pt-1"
                      >
                        <Plus className="h-3.5 w-3.5" /> Isi Saldo Pertama Kali
                      </Link>
                    </div>
                  ) : (
                    <div className="w-full overflow-x-auto custom-scrollbar rounded-xl border border-gray-200 dark:border-gray-800">
                      <table className="w-full min-w-[750px] text-left border-collapse text-xs">
                        <thead>
                          <tr className="border-b border-gray-200 bg-gray-50/50 text-gray-500 dark:border-gray-800 dark:bg-gray-900/50 dark:text-gray-400 text-[11px] uppercase font-bold">
                            <th className="py-3 px-3.5">No. Invoice</th>
                            <th className="py-3 px-3.5">Tanggal</th>
                            <th className="py-3 px-3.5">Metode / Bank</th>
                            <th className="py-3 px-3.5">Nominal</th>
                            <th className="py-3 px-3.5 text-center">Status</th>
                            <th className="py-3 px-3.5 text-right">Aksi</th>
                          </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-200 dark:divide-gray-800 font-medium">
                          {topupHistory.map((item) => {
                            const isPaid = ["verified", "approved", "paid"].includes(item.status)
                            const isPending = item.status === "pending"
                            const isExpired = ["expired", "cancelled"].includes(item.status)
                            const isRejected = item.status === "rejected"

                            return (
                              <tr key={item.id} className="hover:bg-gray-50/50 dark:hover:bg-white/[0.02] transition-colors">
                                <td className="py-3 px-3.5 font-mono font-medium text-gray-900 dark:text-white">
                                  {item.invoice_number}
                                </td>
                                <td className="py-3 px-3.5 text-gray-500 dark:text-gray-400 whitespace-nowrap">
                                  {item.created_at ? formatDate(item.created_at) : "-"}
                                </td>
                                <td className="py-3 px-3.5 text-gray-700 dark:text-gray-300">
                                  <span className="font-semibold">{item.bank_destination}</span>
                                </td>
                                <td className="py-3 px-3.5 font-mono font-bold text-gray-900 dark:text-white whitespace-nowrap">
                                  {formatIDR(item.total_amount || item.amount)}
                                  {item.unique_code && item.unique_code > 0 && (
                                    <span className="text-[10px] text-amber-500 ml-1 font-normal">
                                      (+{item.unique_code})
                                    </span>
                                  )}
                                </td>
                                <td className="py-3 px-3.5 text-center whitespace-nowrap">
                                  {isPaid && (
                                    <span className="inline-flex items-center gap-1 text-xs font-bold px-2.5 py-1 rounded-lg bg-emerald-500 text-white whitespace-nowrap shadow-xs">
                                      <CheckCircle2 className="h-3.5 w-3.5" /> Berhasil
                                    </span>
                                  )}
                                  {isPending && (
                                    <span className="inline-flex items-center gap-1 text-xs font-bold px-2.5 py-1 rounded-lg bg-amber-500 text-white animate-pulse whitespace-nowrap shadow-xs">
                                      <Clock className="h-3.5 w-3.5" /> Menunggu
                                    </span>
                                  )}
                                  {isExpired && (
                                    <span className="inline-flex items-center gap-1 text-xs font-bold px-2.5 py-1 rounded-lg bg-gray-500 text-white whitespace-nowrap shadow-xs">
                                      Kedaluwarsa
                                    </span>
                                  )}
                                  {isRejected && (
                                    <span className="inline-flex items-center gap-1 text-xs font-bold px-2.5 py-1 rounded-lg bg-rose-500 text-white whitespace-nowrap shadow-xs">
                                      Ditolak
                                    </span>
                                  )}
                                </td>
                                <td className="py-3 px-3.5 text-right whitespace-nowrap">
                                  <Link
                                    href={`/admin/topup/confirm/${item.id}`}
                                    className={cn(
                                      "h-8 inline-flex items-center gap-1 px-3 rounded-lg text-xs font-bold transition-all shadow-2xs",
                                      isPending
                                        ? "bg-amber-500 text-white hover:bg-amber-600"
                                        : "bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
                                    )}
                                  >
                                    <span>{isPending ? "Bayar" : "Detail"}</span>
                                    <ArrowUpRight className="h-3 w-3" />
                                  </Link>
                                </td>
                              </tr>
                            )
                          })}
                        </tbody>
                      </table>
                    </div>
                  )}
                </div>
              </div>
            )}
          </div>
        </div>

      {/* ── MODAL KELOLA REKENING BANK ── */}
      {manageBank && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs animate-fadeIn">
          <div className="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 w-full max-w-md max-h-[92vh] overflow-y-auto custom-scrollbar p-6 space-y-5 shadow-2xl relative">
            <button
              type="button"
              onClick={() => setManageBank(null)}
              className="absolute top-4 right-4 text-gray-400 hover:text-gray-600 dark:hover:text-white p-1 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 transition cursor-pointer"
            >
              <X className="size-5" />
            </button>

            <div className="space-y-1">
              <h3 className="text-lg font-bold text-gray-900 dark:text-white">Kelola Rekening Bank</h3>
              <p className="text-xs text-gray-500 dark:text-gray-400">Detail dan aksi rekening tujuan transfer tagihan</p>
            </div>

            <div className="rounded-xl border border-gray-100 bg-gray-50/80 dark:border-gray-800 dark:bg-gray-900/50 p-4 space-y-2 text-xs">
              <div className="flex justify-between items-center">
                <span className="text-gray-500 dark:text-gray-400">Nama Bank</span>
                <span className="font-bold text-gray-900 dark:text-white">{manageBank.bank_name}</span>
              </div>
              <div className="flex justify-between items-center">
                <span className="text-gray-500 dark:text-gray-400">Nomor Rekening</span>
                <span className="font-mono font-bold text-gray-900 dark:text-white text-sm">{manageBank.account_number}</span>
              </div>
              <div className="flex justify-between items-center">
                <span className="text-gray-500 dark:text-gray-400">Atas Nama</span>
                <span className="font-medium text-gray-900 dark:text-white">{manageBank.account_name}</span>
              </div>
              <div className="flex justify-between items-center pt-2 border-t border-gray-200/60 dark:border-gray-800/60">
                <span className="text-gray-500 dark:text-gray-400">Status</span>
                <span className="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-bold bg-emerald-500 text-white shadow-xs whitespace-nowrap">
                  Aktif Ditampilkan
                </span>
              </div>
            </div>

            <div className="space-y-2 pt-2 border-t border-gray-100 dark:border-gray-800">
              <button
                type="button"
                onClick={() => {
                  deleteBank(manageBank.id)
                  setManageBank(null)
                }}
                disabled={isDemo}
                className="w-full h-10 inline-flex items-center justify-center gap-1.5 px-3.5 rounded-xl bg-rose-500 hover:bg-rose-600 active:scale-95 text-xs font-bold text-white shadow-xs transition cursor-pointer disabled:opacity-50"
              >
                <Trash2 className="size-4" /> Hapus Rekening Ini
              </button>

              <button
                type="button"
                onClick={() => setManageBank(null)}
                className="w-full h-10 inline-flex items-center justify-center px-4 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:hover:bg-gray-800 text-xs font-semibold text-gray-700 dark:text-gray-200 transition shadow-2xs cursor-pointer"
              >
                Tutup
              </button>
            </div>
          </div>
        </div>
      )}
      </div>
    </AppLayout>
  )
}