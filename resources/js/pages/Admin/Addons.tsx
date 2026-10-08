import { useState } from "react"
import { AppLayout } from "@/components/layout/app-layout"
import {
  Package,
  CheckCircle2,
  Download,
  Layers,
  Search,
  Settings as SettingsIcon,
  X,
  AlertCircle,
  Loader2,
  Check,
  ShieldCheck,
  ExternalLink,
  Globe,
  Radio,
  Clock,
  Terminal,
  Play,
  SkipForward,
  Copy,
  Info,
  Building2,
  CreditCard,
  ChevronDown,
  RefreshCw,
  Router as RouterIcon,
  Activity,
} from "lucide-react"
import { Head, router } from "@inertiajs/react"
import { MetricCard } from "@/components/tailadmin/MetricCard"
import { cn, formatRupiah } from "@/lib/utils"
import axios from "axios"

interface Addon {
  id: string
  name: string
  category: "network" | "payment" | "billing" | "system" | "marketing"
  description: string
  price: number
  billing_cycle: "once" | "monthly" | "yearly"
  is_installed: boolean
  is_active: boolean
  has_config: boolean
  icon: string
  features: string[]
  installed_at?: string
  expires_at?: string
  is_free?: boolean
  is_installed_by_system?: boolean
}

interface BankAccount {
  id: number
  bank_name: string
  account_number: string
  account_name: string
  is_active: boolean
}

interface RegisteredRouter {
  id: number
  name: string
  host?: string
  username?: string
  ros_version?: string
}

interface IsolirSettings {
  dns_name?: string
  dns_ip?: string
  port?: number
  company_name?: string
  cs_phone?: string
  payment_type?: "portal" | "bank_transfer"
  payment_url?: string
  bank_name?: string
  bank_account_number?: string
  bank_account_holder?: string
  bank_info_notes?: string
  custom_message?: string
}

interface AddonsProps {
  addons?: Addon[]
  walletBalance?: number
  isMasterAccount?: boolean
  bankAccounts?: BankAccount[]
  registeredRouters?: RegisteredRouter[]
  isolirSettings?: IsolirSettings
}

export default function Addons({
  addons = [],
  walletBalance = 0,
  isMasterAccount = false,
  bankAccounts = [],
  registeredRouters = [],
  isolirSettings = {},
}: AddonsProps) {
  const [search, setSearch] = useState("")
  const [selectedCategory, setSelectedCategory] = useState<string>("all")
  const [activateModalAddon, setActivateModalAddon] = useState<Addon | null>(null)
  const [manageAddon, setManageAddon] = useState<Addon | null>(null)
  const [configModalAddon, setConfigModalAddon] = useState<Addon | null>(null)
  const [isSubmitting, setIsSubmitting] = useState(false)

  // State for Isolir Config Addon
  const [isolirForm, setIsolirForm] = useState<IsolirSettings>({
    dns_name: isolirSettings.dns_name || "isolir.net",
    dns_ip: isolirSettings.dns_ip || "192.168.88.254",
    port: isolirSettings.port || 8088,
    company_name: isolirSettings.company_name || "",
    cs_phone: isolirSettings.cs_phone || "",
    payment_type: isolirSettings.payment_type || "bank_transfer",
    payment_url: isolirSettings.payment_url || "",
    bank_name: isolirSettings.bank_name || "",
    bank_account_number: isolirSettings.bank_account_number || "",
    bank_account_holder: isolirSettings.bank_account_holder || "",
    bank_info_notes: isolirSettings.bank_info_notes || "",
    custom_message: isolirSettings.custom_message || "",
  })

  // Accordion open states for Isolir
  const [openSections, setOpenSections] = useState<{ [key: string]: boolean }>({
    params: true,
    info: true,
    router: true,
    commands: false,
    guide: false,
  })

  const toggleSection = (key: string) => {
    setOpenSections((prev) => ({ ...prev, [key]: !prev[key] }))
  }

  // Router API Execution States for Isolir
  const [selectedRouterId, setSelectedRouterId] = useState<string | number>(
    registeredRouters.length > 0 ? registeredRouters[0].id : ""
  )
  const [rosVersion, setRosVersion] = useState<"v7" | "v6">("v7")
  const [isTestingConn, setIsTestingConn] = useState(false)
  const [connStatus, setConnStatus] = useState<"idle" | "connected" | "failed">("idle")
  const [connMessage, setConnMessage] = useState("")
  const [routerInfo, setRouterInfo] = useState<{ identity?: string; version?: string } | null>(null)

  const [isExecuting, setIsExecuting] = useState(false)
  const [stepMode, setStepMode] = useState(false)
  const [currentStepIndex, setCurrentStepIndex] = useState(0)
  const [copiedScript, setCopiedScript] = useState(false)
  const [liveLogs, setLiveLogs] = useState<string[]>([])

  const [isUploadingHtml, setIsUploadingHtml] = useState(false)
  const [uploadHtmlStatus, setUploadHtmlStatus] = useState<"idle" | "success" | "failed">("idle")
  const [uploadHtmlMsg, setUploadHtmlMsg] = useState("")

  // Command items for Isolir
  const [commandItems, setCommandItems] = useState<
    Array<{
      id: number
      label: string
      command: string
      status: "idle" | "running" | "success" | "failed" | "skipped"
      message?: string
    }>
  >([])

  // Raw Mikrotik Commands generator
  const rawCommands = [
    `/ip proxy set enabled=yes port=${isolirForm.port || 8088} max-cache-size=none`,
    `/ip proxy access remove [find comment="NODERA_ISOLIR_RULE"]`,
    `/ip proxy access add action=deny comment="NODERA_ISOLIR_RULE" disabled=no dst-port="" redirect-to="${isolirForm.dns_name || "isolir.net"}"`,
    `/ip firewall nat remove [find comment="NODERA_ISOLIR_REDIRECT"]`,
    rosVersion === "v7"
      ? `/ip firewall nat add chain=dstnat protocol=tcp dst-port=80,8080,8000,8888 src-address-list=ISOLIR action=redirect to-ports=${isolirForm.port || 8088} comment="NODERA_ISOLIR_REDIRECT"`
      : `/ip firewall nat add chain=dstnat protocol=tcp dst-port=80,8080,8000 src-address-list=ISOLIR action=redirect to-ports=${isolirForm.port || 8088} comment="NODERA_ISOLIR_REDIRECT"`,
    `/ip firewall filter remove [find comment="NODERA_ISOLIR_DROP_HTTPS"]`,
    `/ip firewall filter add chain=forward protocol=tcp dst-port=443 src-address-list=ISOLIR action=drop comment="NODERA_ISOLIR_DROP_HTTPS" place-before=0`,
    `/ip firewall filter remove [find comment="NODERA_ISOLIR_DROP_NON_HTTP"]`,
    `/ip firewall filter add chain=forward src-address-list=ISOLIR protocol=!tcp action=drop comment="NODERA_ISOLIR_DROP_NON_HTTP" place-before=1`,
    `/ip dns static remove [find comment="NODERA_ISOLIR_DNS"]`,
    `/ip dns static add name="${isolirForm.dns_name || "isolir.net"}" address="${isolirForm.dns_ip || "192.168.88.254"}" comment="NODERA_ISOLIR_DNS"`,
  ]

  // Initialize command items when form changes
  const initCommands = () => {
    const labels = [
      "1. Aktifkan & Konfigurasi Web Proxy MikroTik",
      "2. Hapus Rule Proxy Access Isolir Lama",
      "3. Tambah Rule Proxy Access Redirect ke Host Isolir",
      "4. Hapus Rule Firewall NAT Isolir Lama",
      "5. Tambah Rule NAT Redirect Port HTTP Pelanggan Isolir",
      "6. Hapus Rule Firewall Drop HTTPS Isolir Lama",
      "7. Drop Port 443 HTTPS Pelanggan Isolir",
      "8. Drop Traffic Non-TCP Lainnya Pelanggan Isolir",
      "9. Hapus Static DNS Isolir Lama",
      "10. Buat Static DNS Record untuk Domain Isolir",
    ]

    setCommandItems(
      rawCommands.map((cmd, idx) => ({
        id: idx + 1,
        label: labels[idx] || `Perintah #${idx + 1}`,
        command: cmd,
        status: "idle",
      }))
    )
  }

  // Filtered addons
  const filteredAddons = addons.filter((addon) => {
    const matchesSearch =
      addon.name.toLowerCase().includes(search.toLowerCase()) ||
      addon.description.toLowerCase().includes(search.toLowerCase())
    const matchesCategory =
      selectedCategory === "all" || addon.category === selectedCategory
    return matchesSearch && matchesCategory
  })

  const activeAddonsCount = addons.filter((a) => a.is_installed && a.is_active).length
  const availableAddonsCount = addons.filter((a) => !a.is_installed).length

  // Handlers
  const handleInstallAddon = (addon: Addon) => {
    setIsSubmitting(true)
    router.post(
      `/admin/addons/${addon.id}/install`,
      {},
      {
        onFinish: () => {
          setIsSubmitting(false)
          setActivateModalAddon(null)
        },
      }
    )
  }

  const handleToggleAddon = (addon: Addon) => {
    router.post(
      `/admin/addons/${addon.id}/toggle`,
      {},
      {
        preserveScroll: true,
      }
    )
  }

  const handleUninstallAddon = (addon: Addon) => {
    if (confirm(`Apakah Anda yakin ingin menghapus add-on ${addon.name}?`)) {
      router.delete(`/admin/addons/${addon.id}`, {
        preserveScroll: true,
      })
    }
  }

  const handleSaveIsolirConfig = (e: React.FormEvent) => {
    e.preventDefault()
    setIsSubmitting(true)
    router.post("/admin/addons/isolir/settings", isolirForm as any, {
      onFinish: () => {
        setIsSubmitting(false)
        setConfigModalAddon(null)
      },
    })
  }

  const handleTestConnection = async () => {
    if (!selectedRouterId) return
    setIsTestingConn(true)
    setConnStatus("idle")
    setConnMessage("")
    try {
      const res = await axios.post(`/admin/mikrotik-routers/${selectedRouterId}/test-connection`)
      if (res.data.success) {
        setConnStatus("connected")
        setConnMessage("Koneksi API MikroTik Berhasil Terhubung!")
        setRouterInfo(res.data.data)
      } else {
        setConnStatus("failed")
        setConnMessage(res.data.message || "Gagal terhubung ke router MikroTik.")
      }
    } catch (err: any) {
      setConnStatus("failed")
      setConnMessage(err.response?.data?.message || "Terjadi kesalahan saat menguji koneksi.")
    } finally {
      setIsTestingConn(false)
    }
  }

  const copyMikrotikScript = () => {
    navigator.clipboard.writeText(rawCommands.join("\n"))
    setCopiedScript(true)
    setTimeout(() => setCopiedScript(false), 2000)
  }

  const handleUploadIsolirHtml = async () => {
    if (!selectedRouterId) return
    setIsUploadingHtml(true)
    setUploadHtmlStatus("idle")
    setUploadHtmlMsg("")
    try {
      const res = await axios.post(`/admin/addons/isolir/upload-html`, {
        router_id: selectedRouterId,
        ...isolirForm,
      })
      if (res.data.success) {
        setUploadHtmlStatus("success")
        setUploadHtmlMsg("File error.html berhasil diunggah dan disetel ke webproxy MikroTik!")
      } else {
        setUploadHtmlStatus("failed")
        setUploadHtmlMsg(res.data.message || "Gagal mengunggah file error.html.")
      }
    } catch (err: any) {
      setUploadHtmlStatus("failed")
      setUploadHtmlMsg(err.response?.data?.message || "Terjadi kesalahan saat mengunggah error.html.")
    } finally {
      setIsUploadingHtml(false)
    }
  }

  const handleExecuteAll = async () => {
    if (!selectedRouterId) return
    setIsExecuting(true)
    setLiveLogs((prev) => [...prev, `[${new Date().toLocaleTimeString()}] Memulai eksekusi seluruh rule isolir ke router...`])
    try {
      const res = await axios.post(`/admin/addons/isolir/execute-script`, {
        router_id: selectedRouterId,
        commands: rawCommands,
        ros_version: rosVersion,
      })
      if (res.data.success) {
        setLiveLogs((prev) => [...prev, `[${new Date().toLocaleTimeString()}] Seluruh rule isolir berhasil diterapkan.`])
        setCommandItems((prev) => prev.map((item) => ({ ...item, status: "success" })))
      } else {
        setLiveLogs((prev) => [...prev, `[${new Date().toLocaleTimeString()}] Gagal: ${res.data.message}`])
      }
    } catch (err: any) {
      setLiveLogs((prev) => [...prev, `[${new Date().toLocaleTimeString()}] Error: ${err.response?.data?.message || err.message}`])
    } finally {
      setIsExecuting(false)
    }
  }

  const handleExecuteCurrentStep = async () => {
    if (!selectedRouterId || currentStepIndex >= commandItems.length) return
    const item = commandItems[currentStepIndex]
    setIsExecuting(true)
    setCommandItems((prev) =>
      prev.map((cmd, idx) => (idx === currentStepIndex ? { ...cmd, status: "running" } : cmd))
    )
    try {
      const res = await axios.post(`/admin/addons/isolir/execute-single`, {
        router_id: selectedRouterId,
        command: item.command,
      })
      if (res.data.success) {
        setCommandItems((prev) =>
          prev.map((cmd, idx) => (idx === currentStepIndex ? { ...cmd, status: "success", message: "Berhasil diterapkan" } : cmd))
        )
        setLiveLogs((prev) => [...prev, `[${new Date().toLocaleTimeString()}] Langkah #${item.id} Berhasil: ${item.command}`])
        if (currentStepIndex < commandItems.length - 1) {
          setCurrentStepIndex(currentStepIndex + 1)
        }
      } else {
        setCommandItems((prev) =>
          prev.map((cmd, idx) => (idx === currentStepIndex ? { ...cmd, status: "failed", message: res.data.message } : cmd))
        )
      }
    } catch (err: any) {
      setCommandItems((prev) =>
        prev.map((cmd, idx) => (idx === currentStepIndex ? { ...cmd, status: "failed", message: err.message } : cmd))
      )
    } finally {
      setIsExecuting(false)
    }
  }

  const handleSkipCurrentStep = () => {
    setCommandItems((prev) =>
      prev.map((cmd, idx) => (idx === currentStepIndex ? { ...cmd, status: "skipped" } : cmd))
    )
    if (currentStepIndex < commandItems.length - 1) {
      setCurrentStepIndex(currentStepIndex + 1)
    }
  }

  const handleExecuteSingle = async (index: number) => {
    if (!selectedRouterId) return
    const item = commandItems[index]
    setIsExecuting(true)
    setCommandItems((prev) =>
      prev.map((cmd, idx) => (idx === index ? { ...cmd, status: "running" } : cmd))
    )
    try {
      const res = await axios.post(`/admin/addons/isolir/execute-single`, {
        router_id: selectedRouterId,
        command: item.command,
      })
      if (res.data.success) {
        setCommandItems((prev) =>
          prev.map((cmd, idx) => (idx === index ? { ...cmd, status: "success", message: "Berhasil diterapkan" } : cmd))
        )
      } else {
        setCommandItems((prev) =>
          prev.map((cmd, idx) => (idx === index ? { ...cmd, status: "failed", message: res.data.message } : cmd))
        )
      }
    } catch (err: any) {
      setCommandItems((prev) =>
        prev.map((cmd, idx) => (idx === index ? { ...cmd, status: "failed", message: err.message } : cmd))
      )
    } finally {
      setIsExecuting(false)
    }
  }

  const handleSkipSingle = (index: number) => {
    setCommandItems((prev) =>
      prev.map((cmd, idx) => (idx === index ? { ...cmd, status: "skipped" } : cmd))
    )
  }

  return (
    <AppLayout
      title="Add-ons Platform"
          >
      <Head title="Add-ons Platform - Admin" />

      <div className="space-y-6">
        {/* Top 4 KPI Metric Cards */}
        <div className="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
          <MetricCard
            title="Total Add-ons"
            value={addons.length}
            description="Modul Tersedia"
            icon={Package}
            iconBgColor="bg-brand-50 dark:bg-brand-500/10"
            iconColor="text-brand-500 dark:text-brand-400"
          />
          <MetricCard
            title="Terpasang & Aktif"
            value={activeAddonsCount}
            description="Modul Berjalan"
            icon={CheckCircle2}
            iconBgColor="bg-emerald-50 dark:bg-emerald-500/10"
            iconColor="text-emerald-500 dark:text-emerald-400"
          />
          <MetricCard
            title="Siap Dipasang"
            value={availableAddonsCount}
            description="Modul Belum Aktif"
            icon={Download}
            iconBgColor="bg-purple-50 dark:bg-purple-500/10"
            iconColor="text-purple-500 dark:text-purple-400"
          />
          <MetricCard
            title="Saldo Master"
            value={formatRupiah(walletBalance)}
            description="Untuk Aktivasi Add-on"
            icon={Layers}
            iconBgColor="bg-amber-50 dark:bg-amber-500/10"
            iconColor="text-amber-500 dark:text-amber-400"
          />
        </div>

        {/* ── 1-LINE RESPONSIVE INTEGRATED TOOLBAR ── */}
        <div className="rounded-2xl border border-gray-200 bg-white p-3 sm:p-4 shadow-xs dark:border-gray-800 dark:bg-white/[0.03] flex flex-col gap-2.5 sm:gap-3 lg:flex-row lg:items-center lg:justify-between">
          <div className="flex flex-wrap items-center gap-2 sm:gap-3 flex-1 min-w-0">
            {/* Search Bar */}
            <div className="relative flex-1 min-w-[180px] sm:min-w-[220px] w-full sm:w-auto">
              <Search className="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400 dark:text-gray-500" />
              <input
                type="text"
                placeholder="Cari add-on, fitur, atau integrasi..."
                value={search}
                onChange={(e) => setSearch(e.target.value)}
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

            {/* Category Filter Dropdown */}
            <select
              value={selectedCategory}
              onChange={(e) => setSelectedCategory(e.target.value)}
              className="h-10 rounded-xl border border-gray-200 bg-white px-3 text-xs font-semibold text-gray-700 shadow-2xs focus:border-brand-500 focus:outline-hidden dark:border-gray-800 dark:bg-gray-900 dark:text-gray-200 cursor-pointer w-full sm:w-auto shrink-0"
            >
              <option value="all">Semua Kategori ({addons.length})</option>
              <option value="network">Jaringan</option>
              <option value="payment">Pembayaran</option>
              <option value="billing">Billing</option>
              <option value="system">Sistem</option>
              <option value="marketing">Marketing</option>
            </select>
          </div>

          <div className="flex items-center gap-2 shrink-0 w-full sm:w-auto justify-end">
            <span className="text-xs font-semibold text-gray-500 dark:text-gray-400">
              {activeAddonsCount} dari {addons.length} Add-on Aktif
            </span>
          </div>
        </div>

        {/* ── MASTER CARD CONTAINER ── */}
        <div className="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xs dark:border-gray-800 dark:bg-white/[0.03]">
          {/* Header Bar */}
          <div className="p-4 sm:p-5 border-b border-gray-100 dark:border-gray-800 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
              <h3 className="text-base font-bold text-gray-800 dark:text-white/90">
                Katalog Modul &amp; Integrasi ({filteredAddons.length})
              </h3>
              <p className="text-xs text-gray-500 dark:text-gray-400">
                Ekstensi resmi NODERA Billing untuk optimasi layanan ISP Anda
              </p>
            </div>
          </div>

          {/* Add-ons Grid */}
          <div className="p-4 sm:p-6">
            {filteredAddons.length === 0 ? (
              <div className="flex flex-col items-center justify-center py-12 text-center">
                <Package className="h-12 w-12 text-gray-400 dark:text-gray-600 mb-3" />
                <h4 className="text-sm font-bold text-gray-900 dark:text-white">Tidak ada add-on ditemukan</h4>
                <p className="text-xs text-gray-500 dark:text-gray-400 mt-1 max-w-sm">
                  Coba sesuaikan kata kunci pencarian atau kategori yang dipilih.
                </p>
              </div>
            ) : (
              <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-6">
                {filteredAddons.map((addon) => (
                  <div
                    key={addon.id}
                    className="flex flex-col justify-between rounded-2xl border border-gray-200 bg-white p-5 shadow-xs transition-all hover:border-brand-500/50 hover:shadow-md dark:border-gray-800 dark:bg-white/[0.02] dark:hover:border-brand-500/30"
                  >
                    <div>
                      {/* Top Header Card */}
                      <div className="flex items-start justify-between gap-3 mb-3.5">
                        <div className="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-brand-50 border border-brand-100 text-brand-500 dark:bg-brand-500/10 dark:border-brand-500/20 dark:text-brand-400">
                          {addon.id === "isolir" ? (
                            <Globe className="h-5 w-5" />
                          ) : addon.id === "telegram-bot" ? (
                            <Radio className="h-5 w-5" />
                          ) : (
                            <Package className="h-5 w-5" />
                          )}
                        </div>

                        {/* Status Badges */}
                        <div className="flex items-center gap-1.5 flex-wrap justify-end">
                          {addon.is_free ? (
                            <span className="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-bold bg-blue-500 text-white shadow-xs whitespace-nowrap">
                              Gratis
                            </span>
                          ) : (
                            <span className="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-bold bg-purple-500 text-white shadow-xs whitespace-nowrap">
                              {formatRupiah(addon.price)}
                              {addon.billing_cycle === "monthly" ? "/bln" : ""}
                            </span>
                          )}

                          {addon.is_installed ? (
                            addon.is_active ? (
                              <span className="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-bold bg-emerald-500 text-white shadow-xs whitespace-nowrap">
                                Aktif
                              </span>
                            ) : (
                              <span className="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-bold bg-gray-500 text-white shadow-xs whitespace-nowrap">
                                Terpasang (Mati)
                              </span>
                            )
                          ) : (
                            <span className="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-bold bg-gray-400 text-white shadow-xs whitespace-nowrap">
                              Belum Dipasang
                            </span>
                          )}
                        </div>
                      </div>

                      {/* Title & Description */}
                      <h3 className="text-sm font-bold text-gray-900 dark:text-white">
                        {addon.name}
                      </h3>
                      <p className="mt-1 text-xs text-gray-600 dark:text-gray-400 line-clamp-2 leading-relaxed">
                        {addon.description}
                      </p>

                      {/* Feature Bullet Points */}
                      {addon.features && addon.features.length > 0 && (
                        <ul className="mt-3.5 space-y-1.5 border-t border-gray-100 pt-3 dark:border-gray-800">
                          {addon.features.slice(0, 3).map((feature, idx) => (
                            <li
                              key={idx}
                              className="flex items-center gap-2 text-[11px] text-gray-600 dark:text-gray-400"
                            >
                              <CheckCircle2 className="h-3.5 w-3.5 text-emerald-500 shrink-0" />
                              <span className="truncate">{feature}</span>
                            </li>
                          ))}
                        </ul>
                      )}
                    </div>

                    {/* Card Actions Footer with Single Kelola Button */}
                    <div className="mt-5 border-t border-gray-100 pt-4 dark:border-gray-800 flex items-center justify-between gap-2">
                      <span className="text-[11px] font-mono text-gray-400">{addon.id}</span>
                      <button
                        type="button"
                        onClick={() => setManageAddon(addon)}
                        className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200 font-bold text-xs transition cursor-pointer"
                      >
                        Kelola
                      </button>
                    </div>
                  </div>
                ))}
              </div>
            )}
          </div>
        </div>
      </div>

      {/* ── MODAL: AKTIVASI ADD-ON ── */}
      {activateModalAddon && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs animate-in fade-in duration-200">
          <div className="relative w-full max-w-md overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-2xl dark:border-gray-800 dark:bg-gray-900">
            <div className="flex items-center justify-between border-b border-gray-200 p-5 dark:border-gray-800">
              <h3 className="text-base font-bold text-gray-900 dark:text-white flex items-center gap-2">
                <Package className="h-5 w-5 text-brand-500" />
                <span>Aktivasi {activateModalAddon.name}</span>
              </h3>
              <button
                type="button"
                onClick={() => setActivateModalAddon(null)}
                className="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-800"
              >
                <X className="h-5 w-5" />
              </button>
            </div>

            <div className="p-5 space-y-4">
              <p className="text-xs text-gray-600 dark:text-gray-300 leading-relaxed">
                {activateModalAddon.description}
              </p>

              <div className="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-800 dark:bg-white/[0.02] space-y-2">
                <div className="flex items-center justify-between text-xs">
                  <span className="text-gray-500 dark:text-gray-400">Harga Modul:</span>
                  <span className="font-bold text-gray-900 dark:text-white">
                    {activateModalAddon.is_free ? "Gratis" : formatRupiah(activateModalAddon.price)}
                  </span>
                </div>
                <div className="flex items-center justify-between text-xs">
                  <span className="text-gray-500 dark:text-gray-400">Periode:</span>
                  <span className="font-bold text-gray-900 dark:text-white">
                    {activateModalAddon.billing_cycle === "monthly" ? "Bulanan" : "Sekali Bayar"}
                  </span>
                </div>
                <div className="flex items-center justify-between text-xs pt-2 border-t border-gray-200 dark:border-gray-800">
                  <span className="text-gray-500 dark:text-gray-400">Saldo Master Anda:</span>
                  <span className="font-bold text-emerald-600 dark:text-emerald-400">
                    {formatRupiah(walletBalance)}
                  </span>
                </div>
              </div>

              {!activateModalAddon.is_free && walletBalance < activateModalAddon.price && (
                <div className="rounded-xl border border-rose-200 bg-rose-50 p-3 text-xs text-rose-600 dark:border-rose-900/30 dark:bg-rose-500/10 dark:text-rose-400 flex items-center gap-2">
                  <AlertCircle className="h-4 w-4 shrink-0" />
                  <span>Saldo Master Anda tidak mencukupi untuk melakukan pembelian ini.</span>
                </div>
              )}
            </div>

            <div className="flex items-center justify-end gap-2.5 border-t border-gray-200 p-5 dark:border-gray-800 bg-gray-50 dark:bg-white/[0.02]">
              <button
                type="button"
                onClick={() => setActivateModalAddon(null)}
                className="rounded-xl border border-gray-200 bg-white px-4 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800"
              >
                Batal
              </button>
              <button
                type="button"
                disabled={isSubmitting || (!activateModalAddon.is_free && walletBalance < activateModalAddon.price)}
                onClick={() => handleInstallAddon(activateModalAddon)}
                className="flex items-center gap-1.5 rounded-xl bg-brand-500 px-5 py-2 text-xs font-bold text-white shadow-xs hover:bg-brand-600 disabled:opacity-50"
              >
                {isSubmitting ? (
                  <>
                    <Loader2 className="h-4 w-4 animate-spin" />
                    <span>Memproses...</span>
                  </>
                ) : (
                  <>
                    <Check className="h-4 w-4" />
                    <span>Konfirmasi Pasang</span>
                  </>
                )}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* ── MODAL: PENGATURAN ISOLIR ADD-ON ── */}
      {configModalAddon && configModalAddon.id === "isolir" && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-black/70 backdrop-blur-xs animate-in fade-in duration-200">
          <div className="relative w-full max-w-4xl max-h-[92vh] flex flex-col rounded-2xl border border-gray-200 bg-white shadow-2xl dark:border-gray-800 dark:bg-gray-900 overflow-hidden">
            {/* Modal Header */}
            <div className="flex items-center justify-between border-b border-gray-200 px-5 py-4 dark:border-gray-800 shrink-0">
              <div className="flex items-center gap-3">
                <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-brand-50 border border-brand-100 text-brand-500 dark:bg-brand-500/10 dark:border-brand-500/20 dark:text-brand-400">
                  <Globe className="h-5 w-5" />
                </div>
                <div>
                  <h3 className="text-base font-bold text-gray-900 dark:text-white">
                    Konfigurasi Halaman & Sistem Isolir Otomatis
                  </h3>
                  <p className="text-xs text-gray-500 dark:text-gray-400">
                    Kustomisasi tampilan pesan isolir, DNS redirect, dan injeksi rule proxy ke MikroTik
                  </p>
                </div>
              </div>
              <button
                type="button"
                onClick={() => setConfigModalAddon(null)}
                className="rounded-lg p-2 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-800"
              >
                <X className="h-5 w-5" />
              </button>
            </div>

            {/* Modal Body with Internal Scroll */}
            <form onSubmit={handleSaveIsolirConfig} className="flex flex-col flex-1 overflow-hidden">
              <div className="flex-1 overflow-y-auto p-5 space-y-4">
                {/* SECTION 1: PARAMETER TEKNIS ISOLIR */}
                <div className="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xs dark:border-gray-800 dark:bg-white/[0.03]">
                  <div
                    onClick={() => toggleSection("params")}
                    className="flex items-center justify-between p-4 cursor-pointer select-none hover:bg-gray-50 dark:hover:bg-white/[0.02]"
                  >
                    <div className="flex items-center gap-3">
                      <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-50 text-blue-500 dark:bg-blue-500/10 dark:text-blue-400">
                        <Activity className="h-4 w-4" />
                      </div>
                      <div>
                        <h4 className="text-xs sm:text-sm font-bold text-gray-900 dark:text-white">
                          1. Parameter Teknis Isolir (MikroTik Web Proxy & DNS)
                        </h4>
                        <p className="text-[11px] text-gray-500 dark:text-gray-400">
                          Domain redirect dan port Web Proxy pada router
                        </p>
                      </div>
                    </div>
                    <ChevronDown
                      className={cn(
                        "h-4 w-4 text-gray-400 transition-transform",
                        openSections.params && "rotate-180"
                      )}
                    />
                  </div>

                  {openSections.params && (
                    <div className="border-t border-gray-200 p-4 dark:border-gray-800 space-y-3">
                      <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div className="space-y-1">
                          <label className="text-xs font-semibold text-gray-700 dark:text-gray-300">
                            Domain Isolir (Static DNS) *
                          </label>
                          <input
                            type="text"
                            value={isolirForm.dns_name}
                            onChange={(e) => setIsolirForm({ ...isolirForm, dns_name: e.target.value })}
                            placeholder="isolir.net"
                            className="h-9 w-full rounded-xl border border-gray-200 bg-transparent px-3 text-xs text-gray-900 font-mono focus:border-brand-500 dark:border-gray-800 dark:text-white"
                            required
                          />
                        </div>

                        <div className="space-y-1">
                          <label className="text-xs font-semibold text-gray-700 dark:text-gray-300">
                            IP Address DNS Isolir *
                          </label>
                          <input
                            type="text"
                            value={isolirForm.dns_ip}
                            onChange={(e) => setIsolirForm({ ...isolirForm, dns_ip: e.target.value })}
                            placeholder="192.168.88.254"
                            className="h-9 w-full rounded-xl border border-gray-200 bg-transparent px-3 text-xs text-gray-900 font-mono focus:border-brand-500 dark:border-gray-800 dark:text-white"
                            required
                          />
                        </div>

                        <div className="space-y-1">
                          <label className="text-xs font-semibold text-gray-700 dark:text-gray-300">
                            Port Web Proxy MikroTik *
                          </label>
                          <input
                            type="number"
                            value={isolirForm.port}
                            onChange={(e) => setIsolirForm({ ...isolirForm, port: parseInt(e.target.value) || 8088 })}
                            placeholder="8088"
                            className="h-9 w-full rounded-xl border border-gray-200 bg-transparent px-3 text-xs text-gray-900 font-mono focus:border-brand-500 dark:border-gray-800 dark:text-white"
                            required
                          />
                        </div>
                      </div>
                    </div>
                  )}
                </div>

                {/* SECTION 2: INFORMASI KONTEN & REKENING PEMBAYARAN */}
                <div className="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xs dark:border-gray-800 dark:bg-white/[0.03]">
                  <div
                    onClick={() => toggleSection("info")}
                    className="flex items-center justify-between p-4 cursor-pointer select-none hover:bg-gray-50 dark:hover:bg-white/[0.02]"
                  >
                    <div className="flex items-center gap-3">
                      <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-50 text-emerald-500 dark:bg-emerald-500/10 dark:text-emerald-400 whitespace-nowrap">
                        <CreditCard className="h-4 w-4" />
                      </div>
                      <div>
                        <h4 className="text-xs sm:text-sm font-bold text-gray-900 dark:text-white">
                          2. Informasi Brand & Rekening Pembayaran Isolir
                        </h4>
                        <p className="text-[11px] text-gray-500 dark:text-gray-400">
                          Nama instansi, nomor CS konfirmasi, dan rekening bank di layar pelanggan
                        </p>
                      </div>
                    </div>
                    <ChevronDown
                      className={cn(
                        "h-4 w-4 text-gray-400 transition-transform",
                        openSections.info && "rotate-180"
                      )}
                    />
                  </div>

                  {openSections.info && (
                    <div className="border-t border-gray-200 p-4 dark:border-gray-800 space-y-4">
                      {/* Pilihan Mode Pembayaran */}
                      <div className="space-y-2">
                        <label className="text-xs font-semibold text-gray-700 dark:text-gray-300">
                          Pilihan Tampilan / Metode Pembayaran di Halaman Isolir *
                        </label>
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                          <label
                            className={cn(
                              "relative flex items-start gap-3 p-3.5 rounded-xl border cursor-pointer transition-all",
                              isolirForm.payment_type === "portal"
                                ? "border-brand-500 bg-brand-50/50 dark:bg-brand-500/10 ring-1 ring-brand-500"
                                : "border-gray-200 bg-gray-50/50 dark:border-gray-800 dark:bg-gray-950"
                            )}
                          >
                            <input
                              type="radio"
                              name="payment_type"
                              value="portal"
                              checked={isolirForm.payment_type === "portal"}
                              onChange={() => setIsolirForm({ ...isolirForm, payment_type: "portal" })}
                              className="mt-0.5 text-brand-500 focus:ring-0"
                            />
                            <div className="text-xs">
                              <div className="font-bold text-gray-900 dark:text-white">Arahkan ke Portal Pelanggan</div>
                              <div className="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5">
                                Menampilkan tombol link login portal ISP.
                              </div>
                            </div>
                          </label>

                          <label
                            className={cn(
                              "relative flex items-start gap-3 p-3.5 rounded-xl border cursor-pointer transition-all",
                              isolirForm.payment_type === "bank_transfer"
                                ? "border-emerald-500 bg-emerald-50/50 dark:bg-emerald-500/10 ring-1 ring-emerald-500"
                                : "border-gray-200 bg-gray-50/50 dark:border-gray-800 dark:bg-gray-950"
                            )}
                          >
                            <input
                              type="radio"
                              name="payment_type"
                              value="bank_transfer"
                              checked={isolirForm.payment_type === "bank_transfer"}
                              onChange={() => setIsolirForm({ ...isolirForm, payment_type: "bank_transfer" })}
                              className="mt-0.5 text-emerald-500 focus:ring-0"
                            />
                            <div className="text-xs">
                              <div className="font-bold text-gray-900 dark:text-white flex items-center gap-1.5">
                                <span>Tampilkan Rekening Bank & WA</span>
                                <span className="bg-emerald-600 text-white font-bold text-[9px] px-1.5 py-0.5 rounded-md">
                                  Direkomendasikan
                                </span>
                              </div>
                              <div className="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5">
                                Tampilkan nomor rekening dan WA admin langsung di layar isolir.
                              </div>
                            </div>
                          </label>
                        </div>
                      </div>

                      {/* Bank Transfer Inputs */}
                      {isolirForm.payment_type === "bank_transfer" && (
                        <div className="rounded-xl border border-emerald-200 bg-emerald-50/30 p-3.5 dark:border-emerald-900/30 dark:bg-emerald-500/5 space-y-3">
                          <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                            <span className="text-xs font-bold text-emerald-700 dark:text-emerald-400 flex items-center gap-1.5">
                              <Building2 className="h-4 w-4" />
                              <span>Rincian Rekening Bank Pembayaran</span>
                            </span>

                            {bankAccounts.length > 0 && (
                              <div className="flex items-center gap-2">
                                <span className="text-[11px] text-gray-500 dark:text-gray-400">Pilih Master:</span>
                                <select
                                  onChange={(e) => {
                                    const sel = bankAccounts.find((b) => String(b.id) === e.target.value)
                                    if (sel) {
                                      setIsolirForm({
                                        ...isolirForm,
                                        bank_name: sel.bank_name,
                                        bank_account_number: sel.account_number,
                                        bank_account_holder: sel.account_name,
                                      })
                                    }
                                  }}
                                  className="h-7.5 rounded-lg border border-gray-200 bg-white px-2 text-[11px] text-gray-900 dark:border-gray-800 dark:bg-gray-900 dark:text-white"
                                >
                                  <option value="">-- Rekening Terdaftar --</option>
                                  {bankAccounts.map((b) => (
                                    <option key={b.id} value={b.id}>
                                      {b.bank_name} - {b.account_number} ({b.account_name})
                                    </option>
                                  ))}
                                </select>
                              </div>
                            )}
                          </div>

                          <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div className="space-y-1">
                              <label className="text-[11px] font-semibold text-gray-700 dark:text-gray-300">
                                Nama Bank / E-Wallet *
                              </label>
                              <input
                                type="text"
                                value={isolirForm.bank_name}
                                onChange={(e) => setIsolirForm({ ...isolirForm, bank_name: e.target.value })}
                                placeholder="BCA / BRI / Mandiri"
                                className="h-8.5 w-full rounded-xl border border-gray-200 bg-white px-3 text-xs text-gray-900 dark:border-gray-800 dark:bg-gray-900 dark:text-white"
                              />
                            </div>
                            <div className="space-y-1">
                              <label className="text-[11px] font-semibold text-gray-700 dark:text-gray-300">
                                Nomor Rekening *
                              </label>
                              <input
                                type="text"
                                value={isolirForm.bank_account_number}
                                onChange={(e) => setIsolirForm({ ...isolirForm, bank_account_number: e.target.value })}
                                placeholder="8293-0192-38"
                                className="h-8.5 w-full rounded-xl border border-gray-200 bg-white px-3 text-xs font-mono font-bold text-gray-900 dark:border-gray-800 dark:bg-gray-900 dark:text-white"
                              />
                            </div>
                            <div className="space-y-1">
                              <label className="text-[11px] font-semibold text-gray-700 dark:text-gray-300">
                                Atas Nama *
                              </label>
                              <input
                                type="text"
                                value={isolirForm.bank_account_holder}
                                onChange={(e) => setIsolirForm({ ...isolirForm, bank_account_holder: e.target.value })}
                                placeholder="PT NODERA DIGITAL"
                                className="h-8.5 w-full rounded-xl border border-gray-200 bg-white px-3 text-xs text-gray-900 dark:border-gray-800 dark:bg-gray-900 dark:text-white"
                              />
                            </div>
                          </div>
                        </div>
                      )}

                      <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div className="space-y-1">
                          <label className="text-xs font-semibold text-gray-700 dark:text-gray-300">
                            Nama Provider / Brand ISP *
                          </label>
                          <input
                            type="text"
                            value={isolirForm.company_name}
                            onChange={(e) => setIsolirForm({ ...isolirForm, company_name: e.target.value })}
                            placeholder="Nama ISP / Brand Anda"
                            className="h-9 w-full rounded-xl border border-gray-200 bg-transparent px-3 text-xs text-gray-900 dark:border-gray-800 dark:text-white"
                            required
                          />
                        </div>

                        <div className="space-y-1">
                          <label className="text-xs font-semibold text-gray-700 dark:text-gray-300">
                            WhatsApp Admin (Konfirmasi) *
                          </label>
                          <input
                            type="text"
                            value={isolirForm.cs_phone}
                            onChange={(e) => setIsolirForm({ ...isolirForm, cs_phone: e.target.value })}
                            placeholder="081234567890"
                            className="h-9 w-full rounded-xl border border-gray-200 bg-transparent px-3 text-xs font-mono font-bold text-gray-900 dark:border-gray-800 dark:text-white"
                            required
                          />
                        </div>

                        <div className="space-y-1 sm:col-span-2">
                          <label className="text-xs font-semibold text-gray-700 dark:text-gray-300">
                            Pesan Pemberitahuan di Halaman Isolir
                          </label>
                          <textarea
                            rows={2}
                            value={isolirForm.custom_message}
                            onChange={(e) => setIsolirForm({ ...isolirForm, custom_message: e.target.value })}
                            placeholder="Tuliskan pesan pemberitahuan yang tampil kepada pelanggan..."
                            className="w-full rounded-xl border border-gray-200 bg-transparent p-3 text-xs text-gray-900 dark:border-gray-800 dark:text-white resize-none"
                          />
                        </div>
                      </div>
                    </div>
                  )}
                </div>

                {/* SECTION 3: TARGET ROUTER MIKROTIK (DIRECT API) */}
                <div className="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xs dark:border-gray-800 dark:bg-white/[0.03]">
                  <div
                    onClick={() => toggleSection("router")}
                    className="flex items-center justify-between p-4 cursor-pointer select-none hover:bg-gray-50 dark:hover:bg-white/[0.02]"
                  >
                    <div className="flex items-center gap-3">
                      <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-purple-50 text-purple-500 dark:bg-purple-500/10 dark:text-purple-400 whitespace-nowrap">
                        <RouterIcon className="h-4 w-4" />
                      </div>
                      <div>
                        <h4 className="text-xs sm:text-sm font-bold text-gray-900 dark:text-white">
                          3. Target Router MikroTik & Injeksi Rules
                        </h4>
                        <p className="text-[11px] text-gray-500 dark:text-gray-400">
                          Router: {registeredRouters.find((r) => String(r.id) === String(selectedRouterId))?.name || "Pilih Router"} • ROS: {rosVersion.toUpperCase()}
                        </p>
                      </div>
                    </div>
                    <ChevronDown
                      className={cn(
                        "h-4 w-4 text-gray-400 transition-transform",
                        openSections.router && "rotate-180"
                      )}
                    />
                  </div>

                  {openSections.router && (
                    <div className="border-t border-gray-200 p-4 dark:border-gray-800 space-y-4">
                      <div className="flex flex-wrap items-center justify-between gap-3">
                        <p className="text-xs text-gray-500 dark:text-gray-400">
                          Pilih router untuk menerapkan konfigurasi isolir langsung via API.
                        </p>
                        <div className="flex items-center gap-1 bg-gray-100 dark:bg-gray-800 p-1 rounded-xl">
                          <button
                            type="button"
                            onClick={() => setRosVersion("v7")}
                            className={cn(
                              "px-3 py-1 rounded-lg text-xs font-bold transition-all",
                              rosVersion === "v7" ? "bg-brand-500 text-white" : "text-gray-600 dark:text-gray-400"
                            )}
                          >
                            RouterOS v7
                          </button>
                          <button
                            type="button"
                            onClick={() => setRosVersion("v6")}
                            className={cn(
                              "px-3 py-1 rounded-lg text-xs font-bold transition-all",
                              rosVersion === "v6" ? "bg-brand-500 text-white" : "text-gray-600 dark:text-gray-400"
                            )}
                          >
                            RouterOS v6
                          </button>
                        </div>
                      </div>

                      <div className="grid grid-cols-1 sm:grid-cols-12 gap-3 items-end">
                        <div className="sm:col-span-8 space-y-1">
                          <label className="text-xs font-semibold text-gray-700 dark:text-gray-300">
                            Pilih Router MikroTik
                          </label>
                          {registeredRouters.length > 0 ? (
                            <select
                              value={selectedRouterId}
                              onChange={(e) => {
                                setSelectedRouterId(e.target.value)
                                setConnStatus("idle")
                              }}
                              className="h-10 w-full rounded-xl border border-gray-200 bg-transparent px-3 text-xs text-gray-900 dark:border-gray-800 dark:bg-gray-900 dark:text-white"
                            >
                              {registeredRouters.map((r) => (
                                <option key={r.id} value={r.id} className="dark:bg-gray-900">
                                  {r.name} ({r.host || "IP N/A"})
                                </option>
                              ))}
                            </select>
                          ) : (
                            <div className="p-2.5 rounded-xl border border-amber-200 bg-amber-50 text-xs text-amber-700 dark:border-amber-900/30 dark:bg-amber-500/10 dark:text-amber-400">
                              Belum ada router terdaftar.
                            </div>
                          )}
                        </div>

                        <div className="sm:col-span-4">
                          <button
                            type="button"
                            onClick={handleTestConnection}
                            disabled={isTestingConn || registeredRouters.length === 0}
                            className="flex h-10 w-full items-center justify-center gap-1.5 rounded-xl border border-gray-200 bg-gray-50 px-3 text-xs font-bold text-gray-700 hover:bg-gray-100 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800 disabled:opacity-50"
                          >
                            {isTestingConn ? (
                              <>
                                <RefreshCw className="h-3.5 w-3.5 animate-spin" />
                                <span>Menguji...</span>
                              </>
                            ) : (
                              <>
                                <Activity className="h-3.5 w-3.5 text-emerald-500" />
                                <span>Tes Koneksi API</span>
                              </>
                            )}
                          </button>
                        </div>
                      </div>

                      {connStatus !== "idle" && (
                        <div
                          className={cn(
                            "p-3 rounded-xl border text-xs flex items-center justify-between gap-2",
                            connStatus === "connected"
                              ? "border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900/30 dark:bg-emerald-500/10 dark:text-emerald-400"
                              : "border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-900/30 dark:bg-rose-500/10 dark:text-rose-400"
                          )}
                        >
                          <div className="flex items-center gap-2">
                            {connStatus === "connected" ? (
                              <Check className="h-4 w-4 text-emerald-500 shrink-0" />
                            ) : (
                              <AlertCircle className="h-4 w-4 text-rose-500 shrink-0" />
                            )}
                            <span>{connMessage}</span>
                          </div>
                          {routerInfo && (
                            <span className="font-mono text-[10px] bg-white dark:bg-gray-900 px-2 py-0.5 rounded border border-gray-200 dark:border-gray-800">
                              {routerInfo.identity || "MikroTik"} ({routerInfo.version || ""})
                            </span>
                          )}
                        </div>
                      )}

                      {/* Action buttons */}
                      <div className="flex flex-wrap items-center gap-2 pt-2 border-t border-gray-100 dark:border-gray-800">
                        <button
                          type="button"
                          onClick={copyMikrotikScript}
                          className="flex h-9 items-center gap-1.5 px-3 rounded-xl text-xs font-semibold border border-gray-200 bg-gray-50 text-gray-700 hover:bg-gray-100 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800"
                        >
                          {copiedScript ? <Check className="h-3.5 w-3.5 text-emerald-500" /> : <Copy className="h-3.5 w-3.5" />}
                          <span>{copiedScript ? "Tersalin!" : "Salin Script (.rsc)"}</span>
                        </button>

                        <button
                          type="button"
                          onClick={handleUploadIsolirHtml}
                          disabled={isUploadingHtml || registeredRouters.length === 0}
                          className="flex h-9 items-center gap-1.5 px-3.5 rounded-xl text-xs font-bold border border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100 dark:border-emerald-900/30 dark:bg-emerald-500/10 dark:text-emerald-400 disabled:opacity-50"
                        >
                          {isUploadingHtml ? (
                            <>
                              <RefreshCw className="h-3.5 w-3.5 animate-spin text-emerald-500" />
                              <span>Mengunggah...</span>
                            </>
                          ) : (
                            <>
                              <Activity className="h-3.5 w-3.5 text-emerald-500" />
                              <span>Inject error.html ke Router</span>
                            </>
                          )}
                        </button>

                        <button
                          type="button"
                          onClick={handleExecuteAll}
                          disabled={isExecuting || registeredRouters.length === 0}
                          className="flex h-9 items-center gap-1.5 px-4 rounded-xl text-xs font-bold bg-brand-500 hover:bg-brand-600 text-white disabled:opacity-50"
                        >
                          {isExecuting ? (
                            <>
                              <RefreshCw className="h-3.5 w-3.5 animate-spin" />
                              <span>Menerapkan...</span>
                            </>
                          ) : (
                            <>
                              <Play className="h-3.5 w-3.5 fill-current" />
                              <span>Terapkan Semua Rules</span>
                            </>
                          )}
                        </button>
                      </div>

                      {uploadHtmlStatus !== "idle" && (
                        <div
                          className={cn(
                            "p-3 rounded-xl border text-xs flex items-center gap-2",
                            uploadHtmlStatus === "success"
                              ? "border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900/30 dark:bg-emerald-500/10 dark:text-emerald-400"
                              : "border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-900/30 dark:bg-rose-500/10 dark:text-rose-400"
                          )}
                        >
                          <Check className="h-4 w-4 text-emerald-500 shrink-0" />
                          <span>{uploadHtmlMsg}</span>
                        </div>
                      )}
                    </div>
                  )}
                </div>
              </div>

              {/* Modal Footer */}
              <div className="border-t border-gray-200 px-5 py-4 dark:border-gray-800 shrink-0 flex items-center justify-between gap-3 bg-gray-50 dark:bg-white/[0.02]">
                <span className="text-xs text-gray-500 dark:text-gray-400 hidden sm:inline">
                  Pastikan konfigurasi disimpan sebelum menutup.
                </span>
                <div className="flex items-center gap-2.5 ml-auto">
                  <button
                    type="button"
                    className="rounded-xl border border-gray-200 bg-white px-4 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800"
                    onClick={() => setConfigModalAddon(null)}
                  >
                    Tutup
                  </button>
                  <button
                    type="submit"
                    disabled={isSubmitting}
                    className="flex items-center gap-1.5 rounded-xl bg-brand-500 px-5 py-2 text-xs font-bold text-white shadow-xs hover:bg-brand-600 active:scale-95 disabled:opacity-50"
                  >
                    {isSubmitting ? (
                      <>
                        <Loader2 className="h-4 w-4 animate-spin" />
                        <span>Menyimpan...</span>
                      </>
                    ) : (
                      <>
                        <Check className="h-4 w-4" />
                        <span>Simpan Pengaturan</span>
                      </>
                    )}
                  </button>
                </div>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* ── MODAL KELOLA ADD-ON ── */}
      {manageAddon && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs animate-fadeIn">
          <div className="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 w-full max-w-md max-h-[92vh] overflow-y-auto p-6 space-y-5 shadow-2xl relative">
            <button
              type="button"
              onClick={() => setManageAddon(null)}
              className="absolute top-4 right-4 text-gray-400 hover:text-gray-600 dark:hover:text-white p-1 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 transition cursor-pointer"
            >
              <X className="size-5" />
            </button>

            <div className="space-y-1">
              <h3 className="text-lg font-bold text-gray-900 dark:text-white">Kelola Add-on: {manageAddon.name}</h3>
              <p className="text-xs text-gray-500 dark:text-gray-400">{manageAddon.description}</p>
            </div>

            {/* Info Box */}
            <div className="rounded-xl border border-gray-100 bg-gray-50/80 dark:border-gray-800 dark:bg-gray-900/50 p-3.5 space-y-2 text-xs">
              <div className="flex justify-between items-center">
                <span className="text-gray-500 dark:text-gray-400">Status Pemasangan</span>
                <span className={`inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold text-white shadow-xs ${manageAddon.is_installed ? (manageAddon.is_active ? "bg-emerald-500" : "bg-amber-500") : "bg-gray-500"}`}>
                  {manageAddon.is_installed ? (manageAddon.is_active ? "Aktif & Beroperasi" : "Terpasang (Nonaktif)") : "Belum Dipasang"}
                </span>
              </div>
              <div className="flex justify-between items-center">
                <span className="text-gray-500 dark:text-gray-400">Tarif Lisensi</span>
                <span className="font-bold text-gray-900 dark:text-white">
                  {manageAddon.is_free ? "Gratis Termasuk" : `${formatRupiah(manageAddon.price)}${manageAddon.billing_cycle === "monthly" ? "/bln" : ""}`}
                </span>
              </div>
            </div>

            {/* Feature list */}
            {manageAddon.features && manageAddon.features.length > 0 && (
              <div className="space-y-1.5">
                <span className="text-xs font-bold text-gray-700 dark:text-gray-300 block">Fitur Unggulan:</span>
                <ul className="space-y-1 text-xs text-gray-600 dark:text-gray-400">
                  {manageAddon.features.map((feat, idx) => (
                    <li key={idx} className="flex items-center gap-2">
                      <CheckCircle2 className="size-3.5 text-emerald-500 shrink-0" />
                      <span>{feat}</span>
                    </li>
                  ))}
                </ul>
              </div>
            )}

            {/* Quick Actions */}
            <div className="space-y-2 pt-2 border-t border-gray-100 dark:border-gray-800">
              {manageAddon.is_installed ? (
                <>
                  {manageAddon.has_config && (
                    <button
                      type="button"
                      onClick={() => {
                        const target = manageAddon
                        setManageAddon(null)
                        setConfigModalAddon(target)
                        if (target.id === "isolir") initCommands()
                      }}
                      className="w-full inline-flex items-center justify-center gap-1.5 px-3.5 py-2.5 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:hover:bg-gray-800 text-xs font-semibold text-gray-700 dark:text-gray-200 transition shadow-2xs cursor-pointer"
                    >
                      <SettingsIcon className="size-4 text-brand-500" /> Buka Konfigurasi &amp; Pengaturan
                    </button>
                  )}

                  <button
                    type="button"
                    onClick={() => {
                      handleToggleAddon(manageAddon)
                      setManageAddon(null)
                    }}
                    className={`w-full inline-flex items-center justify-center gap-1.5 px-3.5 py-2.5 rounded-xl text-xs font-bold text-white shadow-xs transition cursor-pointer ${manageAddon.is_active ? "bg-amber-500 hover:bg-amber-600" : "bg-emerald-500 hover:bg-emerald-600"}`}
                  >
                    {manageAddon.is_active ? "Nonaktifkan Sementara" : "Aktifkan Add-on"}
                  </button>

                  {!manageAddon.is_installed_by_system && (
                    <button
                      type="button"
                      onClick={() => {
                        handleUninstallAddon(manageAddon)
                        setManageAddon(null)
                      }}
                      className="w-full inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl border border-rose-200 text-rose-600 hover:bg-rose-50 dark:border-rose-900/40 dark:text-rose-400 dark:hover:bg-rose-950/20 text-xs font-semibold transition cursor-pointer"
                    >
                      <Trash2 className="size-3.5" /> Hapus / Copot Add-on
                    </button>
                  )}
                </>
              ) : (
                <button
                  type="button"
                  onClick={() => {
                    const target = manageAddon
                    setManageAddon(null)
                    setActivateModalAddon(target)
                  }}
                  className="w-full inline-flex items-center justify-center gap-1.5 px-3.5 py-2.5 rounded-xl bg-brand-500 hover:bg-brand-600 active:scale-95 text-xs font-bold text-white shadow-xs transition cursor-pointer"
                >
                  <Download className="size-4" /> {manageAddon.is_free ? "Pasang Add-on Gratis" : "Beli & Pasang Add-on"}
                </button>
              )}

              <button
                type="button"
                onClick={() => setManageAddon(null)}
                className="w-full inline-flex items-center justify-center px-4 py-2 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:hover:bg-gray-800 text-xs font-semibold text-gray-700 dark:text-gray-200 transition shadow-2xs cursor-pointer"
              >
                Tutup
              </button>
            </div>
          </div>
        </div>
      )}
    </AppLayout>
  )
}