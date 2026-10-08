import { AppLayout } from "@/components/layout/app-layout"
import {
  CreditCard,
  Save,
  Wallet,
  Lock,
  Plus,
  Pencil,
  Trash2,
  CheckCircle2,
  AlertCircle,
  X,
  Globe,
  Copy,
  Check,
  ChevronLeft,
  Link2,
  ShoppingCart,
  Receipt,
  Power,
  CheckSquare,
  Square,
  ExternalLink,
  ShieldCheck,
  Settings2,
  Play,
  Eye,
  EyeOff,
} from "lucide-react"
import { Label } from "@/components/ui/label"
import { Switch } from "@/components/tailadmin/Switch"
import { PageProps } from "@/types"
import { adminSidebarItems, adminNavItems, adminBrand } from "@/lib/admin-nav"
import { useForm, router, Link } from "@inertiajs/react"
import { useState } from "react"
import { cn } from "@/lib/utils"

const NODERAPAY_CHANNELS = [
  { code: "QRIS", name: "QRIS Realtime 0-Detik", group: "QRIS & E-Wallet", badge: "Semua Bank & E-Wallet" },
  { code: "BCAVA", name: "BCA Virtual Account", group: "Virtual Account", badge: "BCA" },
  { code: "BNIVA", name: "BNI Virtual Account", group: "Virtual Account", badge: "BNI" },
  { code: "BRIVA", name: "BRI Virtual Account", group: "Virtual Account", badge: "BRI" },
  { code: "MANDIRIVA", name: "Mandiri VA", group: "Virtual Account", badge: "Mandiri" },
  { code: "PERMATAVA", name: "Permata VA", group: "Virtual Account", badge: "Permata" },
  { code: "ALFAMART", name: "Alfamart / Alfa Group", group: "Retail", badge: "Retail" },
  { code: "INDOMARET", name: "Indomaret", group: "Retail", badge: "Retail" },
]

const WIJAYAPAY_CHANNELS = [
  { code: "QRIS", name: "QRIS Realtime", group: "QRIS & E-Wallet", badge: "Semua Bank" },
  { code: "BCAVA", name: "BCA Virtual Account", group: "Virtual Account", badge: "BCA" },
  { code: "BNIVA", name: "BNI Virtual Account", group: "Virtual Account", badge: "BNI" },
  { code: "BRIVA", name: "BRI Virtual Account", group: "Virtual Account", badge: "BRI" },
  { code: "MANDIRIVA", name: "Mandiri VA", group: "Virtual Account", badge: "Mandiri" },
  { code: "PERMATAVA", name: "Permata VA", group: "Virtual Account", badge: "Permata" },
  { code: "CIMBVA", name: "CIMB Niaga VA", group: "Virtual Account", badge: "CIMB" },
  { code: "BSIVA", name: "BSI Virtual Account", group: "Virtual Account", badge: "BSI" },
  { code: "ALFAMART", name: "Alfamart / Alfa Group", group: "Retail", badge: "Retail" },
  { code: "INDOMARET", name: "Indomaret", group: "Retail", badge: "Retail" },
]

const TRIPAY_CHANNELS = [
  { code: "QRIS", name: "QRIS Realtime", group: "QRIS", badge: "Semua Bank" },
  { code: "QRISC", name: "QRIS Custom / Dinamis", group: "QRIS", badge: "Dinamis" },
  { code: "BRIVA", name: "BRI Virtual Account", group: "Virtual Account", badge: "BRI" },
  { code: "BCAVA", name: "BCA Virtual Account", group: "Virtual Account", badge: "BCA" },
  { code: "BNIVA", name: "BNI Virtual Account", group: "Virtual Account", badge: "BNI" },
  { code: "MANDIRIVA", name: "Mandiri VA", group: "Virtual Account", badge: "Mandiri" },
  { code: "PERMATAVA", name: "Permata VA", group: "Virtual Account", badge: "Permata" },
  { code: "BSIVA", name: "BSI Virtual Account", group: "Virtual Account", badge: "BSI" },
  { code: "ALFAMART", name: "Alfamart", group: "Retail", badge: "Retail" },
  { code: "INDOMARET", name: "Indomaret", group: "Retail", badge: "Retail" },
]

const MIDTRANS_CHANNELS = [
  { code: "midtrans:qris", name: "GoPay / QRIS Realtime", group: "QRIS", badge: "GoPay / QRIS" },
  { code: "midtrans:shopeepay", name: "ShopeePay", group: "E-Wallet", badge: "ShopeePay" },
  { code: "midtrans:bca_va", name: "BCA Virtual Account", group: "Virtual Account", badge: "BCA" },
  { code: "midtrans:bni_va", name: "BNI Virtual Account", group: "Virtual Account", badge: "BNI" },
  { code: "midtrans:bri_va", name: "BRI Virtual Account", group: "Virtual Account", badge: "BRI" },
  { code: "midtrans:mandiri_bill", name: "Mandiri Bill Payment", group: "Virtual Account", badge: "Mandiri" },
  { code: "midtrans:indomaret", name: "Indomaret", group: "Retail", badge: "Retail" },
  { code: "midtrans:alfamart", name: "Alfamart", group: "Retail", badge: "Retail" },
]

const DOKU_CHANNELS = [
  { code: "doku:qris", name: "DOKU QRIS Realtime", group: "QRIS", badge: "QRIS" },
  { code: "doku:va_bca", name: "BCA Virtual Account", group: "Virtual Account", badge: "BCA" },
  { code: "doku:va_mandiri", name: "Mandiri Virtual Account", group: "Virtual Account", badge: "Mandiri" },
  { code: "doku:va_bni", name: "BNI Virtual Account", group: "Virtual Account", badge: "BNI" },
  { code: "doku:va_bri", name: "BRI Virtual Account", group: "Virtual Account", badge: "BRI" },
  { code: "doku:cards", name: "Credit/Debit Cards", group: "Cards", badge: "Cards" },
  { code: "doku:ovo", name: "OVO", group: "E-Wallet", badge: "OVO" },
  { code: "doku:shopeepay", name: "ShopeePay", group: "E-Wallet", badge: "ShopeePay" },
  { code: "doku:indomaret", name: "Indomaret", group: "Retail", badge: "Retail" },
  { code: "doku:alfamart", name: "Alfamart", group: "Retail", badge: "Retail" },
]

export default function AdminPaymentGatewayPage({
  balance,
  noderapayConfig,
  tripayConfig,
  midtransConfig,
  dokuConfig,
  xenditConfig,
  duitkuConfig,
  wijayapayConfig,
  paydisiniConfig,
  pakasirConfig,
  activeStatus,
  usageSettings,
  auth,
}: PageProps<{
  balance: string | null
  noderapayConfig: any | null
  tripayConfig: any | null
  midtransConfig: any | null
  dokuConfig?: any | null
  xenditConfig: any | null
  duitkuConfig: any | null
  wijayapayConfig: any | null
  paydisiniConfig: any | null
  pakasirConfig: any | null
  activeStatus?: Record<string, boolean> | null
  usageSettings?: {
    enable_checkout_landing?: boolean
    enable_invoice_payment?: boolean
    enable_topup_deposit?: boolean
    default_gateway?: string
  } | null
}>) {
  const [modalOpen, setModalOpen] = useState(false)
  const [selectedGateway, setSelectedGateway] = useState<"noderapay" | "wijayapay" | "tripay" | "midtrans" | "doku" | "duitku" | "xendit" | "paydisini" | "pakasir">("noderapay")
  const [managingGateway, setManagingGateway] = useState<string | null>(null)
  const [copied, setCopied] = useState<string | null>(null)
  const [testingGw, setTestingGw] = useState<string | null>(null)
  const [testResult, setTestResult] = useState<{ gateway: string; success: boolean; message: string } | null>(null)
  const [showMidtransKey, setShowMidtransKey] = useState(false)

  const usage = useForm({
    enable_checkout_landing: usageSettings?.enable_checkout_landing ?? true,
    enable_invoice_payment: usageSettings?.enable_invoice_payment ?? true,
    enable_topup_deposit: usageSettings?.enable_topup_deposit ?? true,
    default_gateway: usageSettings?.default_gateway ?? "noderapay",
  })

  const getIsActive = (gwKey: string, config: any) => {
    if (activeStatus && typeof activeStatus[gwKey] === "boolean") return activeStatus[gwKey]
    if (activeStatus && typeof activeStatus[gwKey + "Active"] === "boolean") return activeStatus[gwKey + "Active"]
    if (config && typeof config.is_active === "boolean") return config.is_active
    return true
  }

  const noderapay = useForm({
    NODERAPAY_MERCHANT_CODE: noderapayConfig?.NODERAPAY_MERCHANT_CODE ?? noderapayConfig?.merchant_code ?? "",
    NODERAPAY_API_KEY: noderapayConfig?.NODERAPAY_API_KEY ?? noderapayConfig?.api_key ?? "",
    NODERAPAY_MERCHANT_NAME: noderapayConfig?.NODERAPAY_MERCHANT_NAME ?? noderapayConfig?.merchant_name ?? "",
    enabled_channels: (noderapayConfig?.enabled_channels as string[]) ?? NODERAPAY_CHANNELS.map((c) => c.code),
    is_active: getIsActive("noderapay", noderapayConfig),
  })

  const tripay = useForm({
    TRIPAY_MERCHANT_CODE: tripayConfig?.TRIPAY_MERCHANT_CODE ?? tripayConfig?.merchant_code ?? "",
    TRIPAY_API_KEY: tripayConfig?.TRIPAY_API_KEY ?? tripayConfig?.api_key ?? "",
    TRIPAY_PRIVATE_KEY: tripayConfig?.TRIPAY_PRIVATE_KEY ?? tripayConfig?.private_key ?? "",
    TRIPAY_MODE: tripayConfig?.TRIPAY_MODE ?? tripayConfig?.mode ?? "sandbox",
    enabled_channels: (tripayConfig?.enabled_channels as string[]) ?? TRIPAY_CHANNELS.map((c) => c.code),
    is_active: getIsActive("tripay", tripayConfig),
  })

  const isMidtransProduction = midtransConfig?.MIDTRANS_IS_PRODUCTION === true
    || midtransConfig?.is_production === true
    || String(midtransConfig?.MIDTRANS_MODE).toLowerCase() === "production"
    || String(midtransConfig?.mode).toLowerCase() === "production"

  const midtrans = useForm({
    MIDTRANS_SERVER_KEY: midtransConfig?.MIDTRANS_SERVER_KEY ?? midtransConfig?.server_key ?? "",
    MIDTRANS_CLIENT_KEY: midtransConfig?.MIDTRANS_CLIENT_KEY ?? midtransConfig?.client_key ?? "",
    MIDTRANS_MERCHANT_ID: midtransConfig?.MIDTRANS_MERCHANT_ID ?? midtransConfig?.merchant_id ?? "",
    MIDTRANS_IS_PRODUCTION: isMidtransProduction,
    MIDTRANS_MODE: isMidtransProduction ? "production" : "sandbox",
    enabled_channels: (midtransConfig?.enabled_channels as string[]) ?? MIDTRANS_CHANNELS.map((c) => c.code),
    is_active: getIsActive("midtrans", midtransConfig),
  })

  const doku = useForm({
    DOKU_CLIENT_ID: dokuConfig?.DOKU_CLIENT_ID ?? dokuConfig?.client_id ?? "",
    DOKU_SECRET_KEY: dokuConfig?.DOKU_SECRET_KEY ?? dokuConfig?.secret_key ?? "",
    DOKU_MODE: dokuConfig?.DOKU_MODE ?? dokuConfig?.mode ?? "sandbox",
    enabled_channels: (dokuConfig?.enabled_channels as string[]) ?? DOKU_CHANNELS.map((c) => c.code),
    is_active: getIsActive("doku", dokuConfig),
  })

  const xendit = useForm({
    XENDIT_SECRET_KEY: xenditConfig?.XENDIT_SECRET_KEY ?? xenditConfig?.secret_key ?? "",
    XENDIT_PUBLIC_KEY: xenditConfig?.XENDIT_PUBLIC_KEY ?? xenditConfig?.public_key ?? "",
    is_active: getIsActive("xendit", xenditConfig),
  })

  const duitku = useForm({
    DUITKU_MERCHANT_CODE: duitkuConfig?.DUITKU_MERCHANT_CODE ?? duitkuConfig?.merchant_code ?? "",
    DUITKU_API_KEY: duitkuConfig?.DUITKU_API_KEY ?? duitkuConfig?.api_key ?? "",
    DUITKU_ENV: duitkuConfig?.DUITKU_ENV ?? duitkuConfig?.env ?? "sandbox",
    is_active: getIsActive("duitku", duitkuConfig),
  })

  const wijayapay = useForm({
    WIJAYAPAY_CODE_MERCHANT: wijayapayConfig?.WIJAYAPAY_CODE_MERCHANT ?? wijayapayConfig?.code_merchant ?? "",
    WIJAYAPAY_API_KEY: wijayapayConfig?.WIJAYAPAY_API_KEY ?? wijayapayConfig?.api_key ?? "",
    WIJAYAPAY_MODE: wijayapayConfig?.WIJAYAPAY_MODE ?? wijayapayConfig?.mode ?? "production",
    enabled_channels: (wijayapayConfig?.enabled_channels as string[]) ?? WIJAYAPAY_CHANNELS.map((c) => c.code),
    is_active: getIsActive("wijayapay", wijayapayConfig),
  })

  const paydisini = useForm({
    PAYDISINI_API_KEY: paydisiniConfig?.PAYDISINI_API_KEY ?? paydisiniConfig?.api_key ?? "",
    PAYDISINI_SERVICE_ID: paydisiniConfig?.PAYDISINI_SERVICE_ID ?? paydisiniConfig?.service_id ?? "",
    is_active: getIsActive("paydisini", paydisiniConfig),
  })

  const pakasir = useForm({
    PAKASIR_API_KEY: pakasirConfig?.PAKASIR_API_KEY ?? pakasirConfig?.api_key ?? "",
    PAKASIR_SLUG: pakasirConfig?.PAKASIR_SLUG ?? pakasirConfig?.slug ?? "",
    is_active: getIsActive("pakasir", pakasirConfig),
  })

  const handleCopy = (text: string, key: string) => {
    navigator.clipboard.writeText(text)
    setCopied(key)
    setTimeout(() => setCopied(null), 2000)
  }

  const handleTestConnection = async (gw: string) => {
    setTestingGw(gw)
    setTestResult(null)
    try {
      const csrfToken = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || ""
      const res = await fetch(`/admin/payments/gateway/test/${gw}`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "Accept": "application/json",
          "X-CSRF-TOKEN": csrfToken,
          "X-Requested-With": "XMLHttpRequest",
        },
      })
      const data = await res.json()
      setTestingGw(null)
      if (data.success) {
        setTestResult({
          gateway: gw,
          success: true,
          message: data.message || "Koneksi gateway berhasil terhubung!",
        })
      } else {
        setTestResult({
          gateway: gw,
          success: false,
          message: data.message || "Gagal terhubung ke API Payment Gateway.",
        })
      }
    } catch (err: any) {
      setTestingGw(null)
      setTestResult({
        gateway: gw,
        success: false,
        message: err.message || "Gagal memproses pengujian koneksi gateway.",
      })
    }
  }

  const handleDelete = (name: string, gwKey: string) => {
    if (confirm(`Apakah Anda yakin ingin menghapus konfigurasi ${name}? Gateway ini tidak akan dapat memproses pembayaran lagi.`)) {
      router.post(`/admin/payments/gateway/${gwKey}/delete`, {}, {
        preserveScroll: true,
      })
    }
  }

  const toggleGatewayStatus = (gwKey: string) => {
    router.post(`/admin/payments/gateway/toggle/${gwKey}`, {}, {
      preserveScroll: true,
    })
  }

  const handleSaveUsage = () => {
    usage.post("/admin/payments/gateway/usage/save", {
      preserveScroll: true,
    })
  }

  const openGatewayModal = (gw: "noderapay" | "wijayapay" | "tripay" | "midtrans" | "doku" | "duitku" | "xendit" | "paydisini" | "pakasir") => {
    setSelectedGateway(gw)
    setModalOpen(true)
  }

  const isNoderapayConfigured = Boolean(noderapay.data.NODERAPAY_API_KEY || noderapayConfig?.is_configured)
  const isTripayConfigured = Boolean(tripay.data.TRIPAY_API_KEY || tripayConfig?.is_configured)
  const isMidtransConfigured = Boolean(midtrans.data.MIDTRANS_SERVER_KEY || midtransConfig?.is_configured)
  const isDokuConfigured = Boolean(doku.data.DOKU_CLIENT_ID || dokuConfig?.is_configured)
  const isXenditConfigured = Boolean(xendit.data.XENDIT_SECRET_KEY || xenditConfig?.is_configured)
  const isDuitkuConfigured = Boolean(duitku.data.DUITKU_API_KEY || duitkuConfig?.is_configured)
  const isWijayapayConfigured = Boolean(wijayapay.data.WIJAYAPAY_API_KEY || wijayapayConfig?.is_configured)
  const isPaydisiniConfigured = Boolean(paydisini.data.PAYDISINI_API_KEY || paydisiniConfig?.is_configured)
  const isPakasirConfigured = Boolean(pakasir.data.PAKASIR_API_KEY || pakasirConfig?.is_configured)

  const configuredGateways = [
    { key: "noderapay", name: "NODERA PAY", configured: isNoderapayConfigured, merchant: noderapay.data.NODERAPAY_MERCHANT_CODE },
    { key: "wijayapay", name: "WijayaPay", configured: isWijayapayConfigured, merchant: wijayapay.data.WIJAYAPAY_CODE_MERCHANT },
    { key: "tripay", name: "Tripay", configured: isTripayConfigured, merchant: tripay.data.TRIPAY_MERCHANT_CODE },
    { key: "midtrans", name: "Midtrans Snap", configured: isMidtransConfigured, merchant: midtrans.data.MIDTRANS_MERCHANT_ID },
    { key: "doku", name: "DOKU Checkout", configured: isDokuConfigured, merchant: doku.data.DOKU_CLIENT_ID },
    { key: "duitku", name: "Duitku", configured: isDuitkuConfigured, merchant: duitku.data.DUITKU_MERCHANT_CODE },
    { key: "xendit", name: "Xendit", configured: isXenditConfigured, merchant: xendit.data.XENDIT_PUBLIC_KEY ? "Terkonfigurasi" : "-" },
    { key: "paydisini", name: "Paydisini", configured: isPaydisiniConfigured, merchant: paydisini.data.PAYDISINI_SERVICE_ID },
    { key: "pakasir", name: "Pakasir", configured: isPakasirConfigured, merchant: pakasir.data.PAKASIR_SLUG },
  ]

  const rawDefaultGw = usage.data.default_gateway || "noderapay"
  const isRawConfigured = configuredGateways.find((g) => g.key === rawDefaultGw)?.configured
  const firstConfigured = configuredGateways.find((g) => g.configured)
  const activeDefaultGwKey = isRawConfigured ? rawDefaultGw : (firstConfigured?.key || rawDefaultGw)
  const activeDefaultGwInfo = configuredGateways.find((g) => g.key === activeDefaultGwKey)

  const hasAnyGateway = isNoderapayConfigured || isWijayapayConfigured || isTripayConfigured || isMidtransConfigured || isDokuConfigured || isXenditConfigured || isDuitkuConfigured || isPaydisiniConfigured || isPakasirConfigured

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    if (selectedGateway === "noderapay") {
      noderapay.post("/admin/payments/gateway/noderapay/save", { onSuccess: () => setModalOpen(false), preserveScroll: true })
    } else if (selectedGateway === "wijayapay") {
      wijayapay.post("/admin/payments/gateway/wijayapay/save", { onSuccess: () => setModalOpen(false), preserveScroll: true })
    } else if (selectedGateway === "tripay") {
      tripay.post("/admin/payments/gateway/tripay/save", { onSuccess: () => setModalOpen(false), preserveScroll: true })
    } else if (selectedGateway === "midtrans") {
      midtrans.post("/admin/payments/gateway/midtrans/save", { onSuccess: () => setModalOpen(false), preserveScroll: true })
    } else if (selectedGateway === "doku") {
      doku.post("/admin/payments/gateway/doku/save", { onSuccess: () => setModalOpen(false), preserveScroll: true })
    } else if (selectedGateway === "duitku") {
      duitku.post("/admin/payments/gateway/duitku/save", { onSuccess: () => setModalOpen(false), preserveScroll: true })
    } else if (selectedGateway === "xendit") {
      xendit.post("/admin/payments/gateway/xendit/save", { onSuccess: () => setModalOpen(false), preserveScroll: true })
    } else if (selectedGateway === "paydisini") {
      paydisini.post("/admin/payments/gateway/paydisini/save", { onSuccess: () => setModalOpen(false), preserveScroll: true })
    } else if (selectedGateway === "pakasir") {
      pakasir.post("/admin/payments/gateway/pakasir/save", { onSuccess: () => setModalOpen(false), preserveScroll: true })
    }
  }

  const toggleChannel = (form: any, code: string) => {
    const cur = form.data.enabled_channels || []
    if (cur.includes(code)) {
      form.setData("enabled_channels", cur.filter((c: string) => c !== code))
    } else {
      form.setData("enabled_channels", [...cur, code])
    }
  }

  const isProcessing = noderapay.processing || tripay.processing || midtrans.processing || doku.processing || duitku.processing || xendit.processing || wijayapay.processing || paydisini.processing || pakasir.processing

  // Gateway metadata helper
  const getGatewayInfo = (key: string) => {
    switch (key) {
      case "noderapay":
        return {
          name: "NODERA PAY",
          sub: "Autonomous Fintech Gateway (QRIS & VA)",
          active: getIsActive("noderapay", noderapayConfig),
          configured: isNoderapayConfigured,
          merchant: noderapay.data.NODERAPAY_MERCHANT_CODE || "-",
          mode: "Production",
          channels: noderapay.data.enabled_channels || [],
          form: noderapay,
        }
      case "wijayapay":
        return {
          name: "WijayaPay",
          sub: "QRIS Dinamis 0-Detik, VA Bank, Retail",
          active: getIsActive("wijayapay", wijayapayConfig),
          configured: isWijayapayConfigured,
          merchant: wijayapay.data.WIJAYAPAY_CODE_MERCHANT || "-",
          mode: wijayapay.data.WIJAYAPAY_MODE,
          channels: wijayapay.data.enabled_channels || [],
          form: wijayapay,
        }
      case "tripay":
        return {
          name: "Tripay",
          sub: "Multi-channel Payment Gateway",
          active: getIsActive("tripay", tripayConfig),
          configured: isTripayConfigured,
          merchant: tripay.data.TRIPAY_MERCHANT_CODE || "-",
          mode: tripay.data.TRIPAY_MODE,
          channels: tripay.data.enabled_channels || [],
          form: tripay,
        }
      case "midtrans":
        return {
          name: "Midtrans Snap",
          sub: "Snap API Payment Gateway",
          active: getIsActive("midtrans", midtransConfig),
          configured: isMidtransConfigured,
          merchant: midtrans.data.MIDTRANS_MERCHANT_ID || "-",
          mode: midtrans.data.MIDTRANS_IS_PRODUCTION ? "Production" : "Sandbox",
          channels: midtrans.data.enabled_channels || [],
          form: midtrans,
        }
      case "doku":
        return {
          name: "DOKU Checkout",
          sub: "Jokul Pop-up & Direct API",
          active: getIsActive("doku", dokuConfig),
          configured: isDokuConfigured,
          merchant: doku.data.DOKU_CLIENT_ID || "-",
          mode: doku.data.DOKU_MODE === "production" ? "Production" : "Sandbox",
          channels: doku.data.enabled_channels || [],
          form: doku,
        }
      case "duitku":
        return {
          name: "Duitku",
          sub: "Payment Gateway Populer",
          active: getIsActive("duitku", duitkuConfig),
          configured: isDuitkuConfigured,
          merchant: duitku.data.DUITKU_MERCHANT_CODE || "-",
          mode: duitku.data.DUITKU_ENV,
          channels: [],
          form: duitku,
        }
      case "xendit":
        return {
          name: "Xendit",
          sub: "Enterprise Payment Gateway",
          active: getIsActive("xendit", xenditConfig),
          configured: isXenditConfigured,
          merchant: xendit.data.XENDIT_PUBLIC_KEY ? "Terkonfigurasi" : "-",
          mode: "Production",
          channels: [],
          form: xendit,
        }
      case "paydisini":
        return {
          name: "Paydisini",
          sub: "Payment Gateway QRIS Instan",
          active: getIsActive("paydisini", paydisiniConfig),
          configured: isPaydisiniConfigured,
          merchant: paydisini.data.PAYDISINI_SERVICE_ID || "-",
          mode: "Production",
          channels: [],
          form: paydisini,
        }
      case "pakasir":
        return {
          name: "Pakasir",
          sub: "Payment Gateway Sederhana",
          active: getIsActive("pakasir", pakasirConfig),
          configured: isPakasirConfigured,
          merchant: pakasir.data.PAKASIR_SLUG || "-",
          mode: "Production",
          channels: [],
          form: pakasir,
        }
      default:
        return null
    }
  }

  const activeManagingGw = managingGateway ? getGatewayInfo(managingGateway) : null

  return (
    <AppLayout
      title="Payment Gateway"
      brand={adminBrand}
      sidebarItems={adminSidebarItems}
      navItems={adminNavItems}
      user={auth?.user}
    >
      <div className="space-y-6">
        {/* Header Toolbar */}
        <div className="flex flex-col gap-2.5 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <h1 className="text-xl font-bold text-gray-900 dark:text-white">Payment Gateway Otomatis</h1>
            <p className="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
              Integrasi payment gateway QRIS dinamis, Virtual Account, dan E-Wallet otomatis
            </p>
          </div>
          <button
            type="button"
            onClick={() => {
              setSelectedGateway("noderapay")
              setModalOpen(true)
            }}
            className="h-10 inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-brand-500 hover:bg-brand-600 active:scale-95 text-xs font-bold text-white shadow-xs transition cursor-pointer self-start sm:self-auto"
          >
            <Plus className="h-4 w-4" />
            <span>Tambah Gateway</span>
          </button>
        </div>

        {/* ── TOP TELEMETRY & OVERVIEW ROW ── */}
        <div className="grid grid-cols-1 md:grid-cols-3 gap-3 sm:gap-4">
          {/* Card 1: Default / Main Gateway Status */}
          <div className="rounded-2xl border border-gray-200 bg-white p-5 shadow-xs dark:border-gray-800 dark:bg-white/[0.03] flex flex-col justify-between">
            <div>
              <div className="flex items-center justify-between gap-2">
                <span className="text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                  Gateway Utama
                </span>
                <span
                  className={cn(
                    "inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-[11px] font-bold text-white shadow-xs",
                    hasAnyGateway ? "bg-emerald-500" : "bg-gray-500"
                  )}
                >
                  <span className="h-1.5 w-1.5 rounded-full bg-white animate-pulse" />
                  {hasAnyGateway ? "Terkonfigurasi" : "Belum Siap"}
                </span>
              </div>

              <div className="mt-4 flex items-center gap-3">
                <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl border border-brand-200 bg-brand-50 text-brand-600 dark:border-brand-900/40 dark:bg-brand-950/20 dark:text-brand-400">
                  <CreditCard className="h-6 w-6" />
                </div>
                <div className="min-w-0 flex-1">
                  <div className="text-xs text-gray-500 dark:text-gray-400 font-medium">Default Provider</div>
                  <div className="text-sm font-bold text-gray-900 dark:text-white uppercase truncate">
                    {activeDefaultGwInfo?.name || (usage.data.default_gateway ? usage.data.default_gateway.toUpperCase() : "NODERA PAY")}
                  </div>
                </div>
              </div>

              <div className="mt-4 rounded-xl border border-gray-100 bg-gray-50/80 p-2.5 dark:border-gray-800 dark:bg-gray-900/50 flex items-center justify-between gap-2">
                <span className="text-[11px] font-medium text-gray-500 dark:text-gray-400">
                  Merchant ID
                </span>
                <span className="font-mono text-xs font-bold text-brand-600 dark:text-brand-400 truncate max-w-[160px]">
                  {activeDefaultGwInfo?.merchant || "Standar Sistem"}
                </span>
              </div>
            </div>
          </div>

          {/* Card 2: Cakupan Sistem Aktif */}
          <div className="rounded-2xl border border-gray-200 bg-white p-5 shadow-xs dark:border-gray-800 dark:bg-white/[0.03] flex flex-col justify-between">
            <div>
              <div className="flex items-center justify-between">
                <span className="text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                  Cakupan Sistem
                </span>
                <div className="flex h-7 w-7 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600 dark:bg-indigo-500/10 dark:text-indigo-400">
                  <Globe className="h-4 w-4" />
                </div>
              </div>

              <div className="mt-3 space-y-2">
                <div className="flex items-center justify-between text-xs">
                  <span className="text-gray-600 dark:text-gray-300 font-medium">Checkout Toko & Voucher</span>
                  <span className={cn("font-bold text-[10px] px-2 py-0.5 rounded text-white shadow-xs", usage.data.enable_checkout_landing ? "bg-emerald-500" : "bg-gray-500")}>
                    {usage.data.enable_checkout_landing ? "Aktif" : "Mati"}
                  </span>
                </div>
                <div className="flex items-center justify-between text-xs">
                  <span className="text-gray-600 dark:text-gray-300 font-medium">Tagihan Pelanggan PPPoE</span>
                  <span className={cn("font-bold text-[10px] px-2 py-0.5 rounded text-white shadow-xs", usage.data.enable_invoice_payment ? "bg-emerald-500" : "bg-gray-500")}>
                    {usage.data.enable_invoice_payment ? "Aktif" : "Mati"}
                  </span>
                </div>
                <div className="flex items-center justify-between text-xs">
                  <span className="text-gray-600 dark:text-gray-300 font-medium">Topup Saldo Reseller / Kasir</span>
                  <span className={cn("font-bold text-[10px] px-2 py-0.5 rounded text-white shadow-xs", usage.data.enable_topup_deposit ? "bg-emerald-500" : "bg-gray-500")}>
                    {usage.data.enable_topup_deposit ? "Aktif" : "Mati"}
                  </span>
                </div>
              </div>
            </div>
          </div>

          {/* Card 3: Saldo / Total Gateway */}
          <div className="rounded-2xl border border-gray-200 bg-white p-5 shadow-xs dark:border-gray-800 dark:bg-white/[0.03] flex flex-col justify-between">
            <div>
              <div className="flex items-center justify-between">
                <span className="text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                  {balance ? "Saldo Gateway" : "Kanal Pembayaran"}
                </span>
                <div className="flex h-7 w-7 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400">
                  <Wallet className="h-4 w-4" />
                </div>
              </div>

              {balance ? (
                <div className="mt-3">
                  <p className="text-xs text-gray-500 dark:text-gray-400 font-medium">Saldo Akun Merchant</p>
                  <p className="font-display text-2xl font-bold text-gray-900 dark:text-white mt-1">{balance}</p>
                </div>
              ) : (
                <div className="mt-3 space-y-1.5">
                  <div className="flex items-center gap-1.5 text-xs text-gray-700 dark:text-gray-300 font-medium">
                    <CheckCircle2 className="h-3.5 w-3.5 text-emerald-500" />
                    <span>QRIS Dinamis 0-Detik</span>
                  </div>
                  <div className="flex items-center gap-1.5 text-xs text-gray-700 dark:text-gray-300 font-medium">
                    <CheckCircle2 className="h-3.5 w-3.5 text-emerald-500" />
                    <span>Virtual Account Bank Otomatis</span>
                  </div>
                  <div className="flex items-center gap-1.5 text-xs text-gray-700 dark:text-gray-300 font-medium">
                    <CheckCircle2 className="h-3.5 w-3.5 text-emerald-500" />
                    <span>E-Wallet &amp; Gerai Retail</span>
                  </div>
                </div>
              )}
            </div>
          </div>
        </div>

        {/* Cakupan Penggunaan Payment Gateway */}
        <div className="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03] p-5 shadow-xs space-y-4">
          <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-gray-100 dark:border-gray-800 pb-3.5">
            <div className="flex items-center gap-2.5">
              <div className="flex h-9 w-9 items-center justify-center rounded-xl bg-brand-50 text-brand-500 dark:bg-brand-500/10 font-bold">
                <Globe className="h-4 w-4" />
              </div>
              <div>
                <h3 className="text-sm sm:text-base font-bold text-gray-900 dark:text-white">Cakupan Penggunaan Payment Gateway</h3>
                <p className="text-xs text-gray-500 dark:text-gray-400">Pilih di mana saja sistem pembayaran otomatis (QRIS / VA / E-Wallet) aktif</p>
              </div>
            </div>
            <button
              type="button"
              onClick={handleSaveUsage}
              disabled={usage.processing}
              className="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-brand-500 hover:bg-brand-600 active:scale-95 text-xs font-bold text-white shadow-xs transition cursor-pointer self-start sm:self-auto disabled:opacity-50"
            >
              <Save className="h-3.5 w-3.5" />
              <span>{usage.processing ? "Menyimpan..." : "Simpan Cakupan"}</span>
            </button>
          </div>

          <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 pt-1">
            {/* Toggle 1: Toko Online */}
            <div className="flex items-start justify-between gap-3 rounded-xl border border-gray-100 bg-gray-50/80 dark:border-gray-800 dark:bg-gray-900/50 p-3.5">
              <div className="space-y-1 pr-2">
                <div className="flex items-center gap-1.5">
                  <ShoppingCart className="h-3.5 w-3.5 text-sky-500" />
                  <span className="text-xs font-bold text-gray-900 dark:text-white">Checkout Toko &amp; Voucher</span>
                </div>
                <p className="text-[11px] text-gray-500 dark:text-gray-400 leading-relaxed">
                  Gunakan gateway saat pembeli memesan voucher di storefront.
                </p>
              </div>
              <Switch
                checked={usage.data.enable_checkout_landing}
                onChange={(checked) => usage.setData("enable_checkout_landing", checked)}
              />
            </div>

            {/* Toggle 2: Tagihan Pelanggan */}
            <div className="flex items-start justify-between gap-3 rounded-xl border border-gray-100 bg-gray-50/80 dark:border-gray-800 dark:bg-gray-900/50 p-3.5">
              <div className="space-y-1 pr-2">
                <div className="flex items-center gap-1.5">
                  <Receipt className="h-3.5 w-3.5 text-indigo-500" />
                  <span className="text-xs font-bold text-gray-900 dark:text-white">Tagihan Pelanggan PPPoE</span>
                </div>
                <p className="text-[11px] text-gray-500 dark:text-gray-400 leading-relaxed">
                  Tampilkan tombol bayar instan otomatis QRIS/VA pada invoice portal pelanggan.
                </p>
              </div>
              <Switch
                checked={usage.data.enable_invoice_payment}
                onChange={(checked) => usage.setData("enable_invoice_payment", checked)}
              />
            </div>

            {/* Toggle 3: Topup Saldo */}
            <div className="flex items-start justify-between gap-3 rounded-xl border border-gray-100 bg-gray-50/80 dark:border-gray-800 dark:bg-gray-900/50 p-3.5">
              <div className="space-y-1 pr-2">
                <div className="flex items-center gap-1.5">
                  <Wallet className="h-3.5 w-3.5 text-emerald-500" />
                  <span className="text-xs font-bold text-gray-900 dark:text-white">Topup Saldo Reseller / Kasir</span>
                </div>
                <p className="text-[11px] text-gray-500 dark:text-gray-400 leading-relaxed">
                  Izinkan pengisian deposit saldo agen voucher secara instan via Payment Gateway.
                </p>
              </div>
              <Switch
                checked={usage.data.enable_topup_deposit}
                onChange={(checked) => usage.setData("enable_topup_deposit", checked)}
              />
            </div>
          </div>

          <div className="pt-3 border-t border-gray-100 dark:border-gray-800 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div className="space-y-0.5">
              <span className="text-xs font-bold text-gray-900 dark:text-white">Gateway Utama (Default Provider)</span>
              <p className="text-[11px] text-gray-500 dark:text-gray-400">Pilih gateway default yang diprioritaskan saat invoice tagihan diproses</p>
            </div>
            <div className="w-full sm:w-64">
              <select
                value={usage.data.default_gateway || activeDefaultGwKey}
                onChange={(e) => usage.setData("default_gateway", e.target.value)}
                className="w-full h-9 px-3 rounded-xl border border-gray-200 bg-gray-50/50 dark:border-gray-700 dark:bg-gray-800 text-xs font-semibold text-gray-900 dark:text-white focus:border-brand-500 focus:outline-none"
              >
                {configuredGateways.map((g) => (
                  <option key={g.key} value={g.key}>
                    {g.name} {g.configured ? "✓ Terkonfigurasi" : "(Belum Setting)"}
                  </option>
                ))}
              </select>
            </div>
          </div>
        </div>

        {/* Empty Condition */}
        {!hasAnyGateway ? (
          <div className="p-8 rounded-2xl border border-dashed border-gray-200 dark:border-gray-800 bg-white dark:bg-white/[0.02] text-center text-gray-500 space-y-3">
            <CreditCard className="h-10 w-10 mx-auto text-gray-400" />
            <p className="text-sm font-bold text-gray-900 dark:text-white">Belum ada Payment Gateway</p>
            <p className="text-xs text-gray-500 dark:text-gray-400 max-w-md mx-auto">
              Belum ada payment gateway yang dikonfigurasi. Tambahkan gateway untuk menerima pembayaran tagihan secara otomatis melalui QRIS, Virtual Account, Minimarket, atau E-Wallet.
            </p>
            <button
              type="button"
              onClick={() => openGatewayModal("noderapay")}
              className="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-brand-500 hover:bg-brand-600 active:scale-95 text-xs font-bold text-white shadow-xs transition cursor-pointer"
            >
              <Plus className="h-4 w-4" />
              <span>Tambah Payment Gateway</span>
            </button>
          </div>
        ) : (
          /* Grid Kartu Gateway */
          <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            {/* Helper renderer for configured cards */}
            {[
              "noderapay",
              "wijayapay",
              "tripay",
              "midtrans",
              "doku",
              "duitku",
              "xendit",
              "paydisini",
              "pakasir",
            ].map((gwKey) => {
              const info = getGatewayInfo(gwKey)
              if (!info || !info.configured) return null
              return (
                <div
                  key={gwKey}
                  className="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03] shadow-xs p-5 flex flex-col justify-between space-y-4 hover:border-brand-500/50 transition-all duration-200"
                >
                  <div className="space-y-3">
                    <div className="flex items-start justify-between gap-2">
                      <div className="flex items-center gap-3 min-w-0">
                        <div className="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-brand-50 text-brand-500 dark:bg-brand-500/10 font-bold font-display text-sm">
                          {gwKey.substring(0, 2).toUpperCase()}
                        </div>
                        <div className="min-w-0">
                          <div className="flex items-center gap-2">
                            <h3 className="font-display text-base font-bold text-gray-900 dark:text-white">
                              {info.name}
                            </h3>
                          </div>
                          <p className="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{info.sub}</p>
                        </div>
                      </div>
                      <div className="flex items-center gap-2 shrink-0">
                        <span className={cn("text-[11px] font-bold", info.active ? "text-emerald-600 dark:text-emerald-400" : "text-gray-400 dark:text-gray-500")}>
                          {info.active ? "Aktif" : "Nonaktif"}
                        </span>
                        <button
                          type="button"
                          onClick={() => toggleGatewayStatus(gwKey)}
                          title={info.active ? "Klik untuk menonaktifkan gateway" : "Klik untuk mengaktifkan gateway"}
                          className={cn(
                            "relative inline-flex h-5 w-9 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none shadow-xs",
                            info.active ? "bg-emerald-500" : "bg-gray-300 dark:bg-gray-700"
                          )}
                        >
                          <span
                            className={cn(
                              "pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow-md ring-0 transition duration-200 ease-in-out",
                              info.active ? "translate-x-4" : "translate-x-0"
                            )}
                          />
                        </button>
                      </div>
                    </div>

                    <div className="rounded-xl border border-gray-100 bg-gray-50/80 dark:border-gray-800 dark:bg-gray-900/50 p-3 space-y-1.5 text-xs">
                      <div className="flex justify-between items-center">
                        <span className="text-gray-500 dark:text-gray-400">Merchant / ID</span>
                        <span className="font-mono font-bold text-gray-900 dark:text-white">{info.merchant}</span>
                      </div>
                      <div className="flex justify-between items-center">
                        <span className="text-gray-500 dark:text-gray-400">Mode</span>
                        <span className="font-bold text-brand-500 uppercase">{info.mode}</span>
                      </div>
                      {info.channels.length > 0 && (
                        <div className="pt-1 flex flex-wrap gap-1">
                          {info.channels.slice(0, 3).map((ch: string) => (
                            <span
                              key={ch}
                              className="inline-flex items-center px-1.5 py-0.5 rounded text-[9px] font-bold bg-brand-500 text-white shadow-xs"
                            >
                              {ch}
                            </span>
                          ))}
                          {info.channels.length > 3 && (
                            <span className="inline-flex items-center px-1.5 py-0.5 rounded text-[9px] font-bold bg-gray-500 text-white shadow-xs">
                              +{info.channels.length - 3} lainnya
                            </span>
                          )}
                        </div>
                      )}
                    </div>

                    {testResult?.gateway === gwKey && (
                      <div
                        className={cn(
                          "p-2.5 rounded-xl border text-xs flex items-start gap-2",
                          testResult.success
                            ? "bg-emerald-500/10 border-emerald-500/30 text-emerald-700 dark:text-emerald-300"
                            : "bg-rose-500/10 border-rose-500/30 text-rose-700 dark:text-rose-300"
                        )}
                      >
                        {testResult.success ? (
                          <CheckCircle2 className="h-4 w-4 shrink-0 mt-0.5 text-emerald-500" />
                        ) : (
                          <AlertCircle className="h-4 w-4 shrink-0 mt-0.5 text-rose-500" />
                        )}
                        <span className="break-words">{testResult.message}</span>
                      </div>
                    )}
                  </div>

                  {/* Actions Row */}
                  <div className="flex items-center justify-end gap-2 border-t border-gray-100 dark:border-gray-800 pt-3">
                    <button
                      type="button"
                      onClick={() => handleTestConnection(gwKey)}
                      disabled={testingGw !== null}
                      className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:hover:bg-gray-800 text-gray-700 dark:text-gray-200 font-semibold text-xs transition cursor-pointer disabled:opacity-50"
                    >
                      <Play className="h-3.5 w-3.5 text-amber-500" />
                      <span>{testingGw === gwKey ? "Menguji..." : "Uji Koneksi"}</span>
                    </button>
                    <button
                      type="button"
                      onClick={() => setManagingGateway(gwKey)}
                      className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200 font-bold text-xs transition cursor-pointer"
                    >
                      <Settings2 className="h-3.5 w-3.5" />
                      <span>Kelola</span>
                    </button>
                  </div>
                </div>
              )
            })}
          </div>
        )}

        {/* ═══════════════════════════════════════════════════════════════════ */}
        {/* POPUP MODAL KELOLA GATEWAY (INTERACTIVE POPUP MODAL) */}
        {/* ═══════════════════════════════════════════════════════════════════ */}
        {activeManagingGw && (
          <div
            className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-xs animate-in fade-in"
            onClick={() => setManagingGateway(null)}
          >
            <div
              className="w-full max-w-md rounded-2xl border border-gray-200 bg-white p-5 sm:p-6 shadow-2xl dark:border-gray-800 dark:bg-gray-900 space-y-4 max-h-[92vh] overflow-y-auto custom-scrollbar"
              onClick={(e) => e.stopPropagation()}
            >
              <div className="flex items-start justify-between border-b border-gray-100 pb-3 dark:border-gray-800">
                <div className="flex items-center gap-3">
                  <div className="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-brand-50 dark:bg-brand-500/10 text-brand-500 font-bold font-display text-sm">
                    {managingGateway?.substring(0, 2).toUpperCase()}
                  </div>
                  <div>
                    <h3 className="text-base font-bold text-gray-900 dark:text-white">
                      {activeManagingGw.name}
                    </h3>
                    <div className="flex items-center gap-2 mt-0.5">
                      <span
                        className={cn(
                          "inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold text-white shadow-xs",
                          activeManagingGw.active ? "bg-emerald-500" : "bg-gray-500"
                        )}
                      >
                        {activeManagingGw.active ? "Aktif" : "Nonaktif"}
                      </span>
                      <span className="text-[11px] text-gray-500 dark:text-gray-400">
                        {activeManagingGw.mode}
                      </span>
                    </div>
                  </div>
                </div>
                <button
                  type="button"
                  onClick={() => setManagingGateway(null)}
                  className="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 transition cursor-pointer"
                >
                  <X className="h-5 w-5" />
                </button>
              </div>

              {/* Details Box */}
              <div className="rounded-xl border border-gray-100 bg-gray-50/80 dark:border-gray-800 dark:bg-gray-900/50 p-3.5 space-y-2.5 text-xs text-gray-600 dark:text-gray-300">
                <div className="flex items-center justify-between py-1 border-b border-gray-200/60 dark:border-gray-800">
                  <span className="text-gray-500">Merchant Code / ID</span>
                  <span className="font-mono font-bold text-gray-900 dark:text-white">
                    {activeManagingGw.merchant}
                  </span>
                </div>

                <div className="flex items-center justify-between py-1 border-b border-gray-200/60 dark:border-gray-800">
                  <span className="text-gray-500">Status Gateway</span>
                  <button
                    type="button"
                    onClick={() => {
                      if (managingGateway) toggleGatewayStatus(managingGateway)
                    }}
                    className={cn(
                      "inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-bold text-white shadow-xs transition cursor-pointer",
                      activeManagingGw.active ? "bg-emerald-500 hover:bg-emerald-600" : "bg-gray-500 hover:bg-gray-600"
                    )}
                  >
                    <Power className="h-3 w-3" />
                    <span>{activeManagingGw.active ? "Aktif (Klik ubah)" : "Nonaktif (Klik aktifkan)"}</span>
                  </button>
                </div>

                <div className="space-y-1.5 pt-1">
                  <span className="text-gray-500 block">Webhook Callback URL</span>
                  <div className="flex items-center justify-between rounded-lg border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800 px-2.5 py-1.5 text-[11px] font-mono">
                    <span className="truncate pr-2 text-gray-700 dark:text-gray-300">
                      {window.location.origin}/api/payment/callback/{managingGateway}
                    </span>
                    <button
                      type="button"
                      onClick={() => handleCopy(`${window.location.origin}/api/payment/callback/${managingGateway}`, "modal-webhook")}
                      className="text-brand-500 hover:text-brand-600 shrink-0 font-bold"
                    >
                      {copied === "modal-webhook" ? <Check className="h-3.5 w-3.5 text-emerald-500" /> : <Copy className="h-3.5 w-3.5" />}
                    </button>
                  </div>
                </div>

                {activeManagingGw.channels.length > 0 && (
                  <div className="space-y-1.5 pt-1">
                    <span className="text-gray-500 block">Channel Pembayaran Aktif ({activeManagingGw.channels.length})</span>
                    <div className="flex flex-wrap gap-1">
                      {activeManagingGw.channels.map((ch: string) => (
                        <span key={ch} className="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold bg-brand-500 text-white shadow-xs">
                          {ch}
                        </span>
                      ))}
                    </div>
                  </div>
                )}
              </div>

              {/* Action Buttons in Modal */}
              <div className="flex items-center justify-between gap-2 pt-2 border-t border-gray-100 dark:border-gray-800">
                <button
                  type="button"
                  onClick={() => {
                    const gw = managingGateway
                    const name = activeManagingGw.name
                    setManagingGateway(null)
                    if (gw) handleDelete(name, gw)
                  }}
                  className="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-rose-500 hover:bg-rose-600 text-xs font-bold text-white shadow-xs transition cursor-pointer"
                >
                  <Trash2 className="h-4 w-4" />
                  <span>Hapus</span>
                </button>

                <div className="flex items-center gap-2">
                  <button
                    type="button"
                    onClick={() => {
                      if (managingGateway) handleTestConnection(managingGateway)
                    }}
                    disabled={testingGw === managingGateway}
                    className="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:hover:bg-gray-800 text-xs font-semibold text-gray-700 dark:text-gray-200 transition shadow-2xs cursor-pointer disabled:opacity-50"
                  >
                    <Play className="h-3.5 w-3.5 text-brand-500" />
                    <span>{testingGw === managingGateway ? "Menguji..." : "Uji Koneksi"}</span>
                  </button>

                  <button
                    type="button"
                    onClick={() => {
                      const gw = managingGateway as any
                      setManagingGateway(null)
                      openGatewayModal(gw)
                    }}
                    className="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-brand-500 hover:bg-brand-600 active:scale-95 text-xs font-bold text-white shadow-xs transition cursor-pointer"
                  >
                    <Pencil className="h-4 w-4" />
                    <span>Edit Konfigurasi</span>
                  </button>
                </div>
              </div>
            </div>
          </div>
        )}

        {/* ═══════════════════════════════════════════════════════════════════ */}
        {/* RESPONSIVE MODAL KONFIGURASI FORM PAYMENT GATEWAY */}
        {/* ═══════════════════════════════════════════════════════════════════ */}
        {modalOpen && (
          <div className="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 animate-in fade-in">
            <div className="fixed inset-0 bg-black/60 backdrop-blur-xs" onClick={() => setModalOpen(false)} />
            <div className="relative flex max-h-[92vh] w-full max-w-xl flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-2xl dark:border-gray-800 dark:bg-gray-900 text-gray-900 dark:text-white">
              {/* Modal Header */}
              <div className="flex items-center justify-between border-b border-gray-100 px-5 py-4 shrink-0 dark:border-gray-800">
                <div className="flex items-center gap-2.5">
                  <div className="flex h-9 w-9 items-center justify-center rounded-xl bg-brand-50 text-brand-500 dark:bg-brand-500/10 font-bold">
                    <CreditCard className="h-5 w-5" />
                  </div>
                  <div>
                    <h2 className="text-base font-bold text-gray-900 dark:text-white">Pengaturan Payment Gateway</h2>
                    <p className="text-xs text-gray-500 dark:text-gray-400">Atur kredensial dan pilih channel pembayaran aktif</p>
                  </div>
                </div>
                <button
                  type="button"
                  onClick={() => setModalOpen(false)}
                  className="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 transition cursor-pointer"
                >
                  <X className="h-4 w-4" />
                </button>
              </div>

              {/* Scrollable Modal Form Body */}
              <form onSubmit={handleSubmit} className="flex min-h-0 flex-1 flex-col overflow-hidden">
                <div className="flex-1 overflow-y-auto custom-scrollbar p-5 space-y-4">
                  {/* Gateway Dropdown Selector */}
                  <div className="space-y-1.5">
                    <Label className="text-xs font-bold text-gray-700 dark:text-gray-300">Pilih Provider Payment Gateway</Label>
                    <select
                      value={selectedGateway}
                      onChange={(e) => setSelectedGateway(e.target.value as any)}
                      className="h-11 w-full rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 px-3.5 text-xs font-semibold text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-500"
                    >
                      <option value="noderapay">NODERA PAY (Universal Gateway — QRIS Realtime &amp; VA)</option>
                      <option value="wijayapay">WijayaPay (QRIS Realtime 0-Detik &amp; VA)</option>
                      <option value="tripay">Tripay Payment Gateway (Multi-Channel)</option>
                      <option value="midtrans">Midtrans Payment Gateway (Snap API)</option>
                      <option value="doku">DOKU Checkout (Jokul Pop-up, VA, Cards, QRIS)</option>
                      <option value="duitku">Duitku Payment Gateway</option>
                      <option value="xendit">Xendit Payment Gateway</option>
                      <option value="paydisini">Paydisini Payment Gateway</option>
                      <option value="pakasir">Pakasir Payment Gateway</option>
                    </select>
                  </div>

                  {/* NODERA PAY Form */}
                  {selectedGateway === "noderapay" && (
                    <div className="space-y-3.5 rounded-2xl border border-brand-500/20 bg-brand-500/5 p-4">
                      <div className="flex items-center justify-between">
                        <span className="text-xs font-bold text-brand-600 dark:text-brand-400">Pengaturan NODERA PAY</span>
                        <span className="font-mono text-[10px] text-gray-400">gateway.dgtlnetsolution.com</span>
                      </div>

                      <div className="space-y-1.5">
                        <Label className="text-xs text-gray-700 dark:text-gray-300">Kode Merchant (Merchant Code)</Label>
                        <input
                          type="text"
                          value={noderapay.data.NODERAPAY_MERCHANT_CODE}
                          onChange={(e) => noderapay.setData("NODERAPAY_MERCHANT_CODE", e.target.value)}
                          placeholder="NP-123456"
                          required
                          className="h-10 w-full rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 px-3.5 text-xs font-mono text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-500"
                        />
                      </div>

                      <div className="space-y-1.5">
                        <Label className="text-xs text-gray-700 dark:text-gray-300">API Key Secret</Label>
                        <input
                          type="password"
                          value={noderapay.data.NODERAPAY_API_KEY}
                          onChange={(e) => noderapay.setData("NODERAPAY_API_KEY", e.target.value)}
                          placeholder="np_sec_live_..."
                          required
                          className="h-10 w-full rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 px-3.5 text-xs font-mono text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-500"
                        />
                      </div>

                      <div className="space-y-1.5">
                        <Label className="text-xs text-gray-700 dark:text-gray-300">Nama Usaha / Toko (Tampil di QRIS)</Label>
                        <input
                          type="text"
                          value={noderapay.data.NODERAPAY_MERCHANT_NAME}
                          onChange={(e) => noderapay.setData("NODERAPAY_MERCHANT_NAME", e.target.value)}
                          placeholder="NODERA NETWORK"
                          className="h-10 w-full rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 px-3.5 text-xs text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-500"
                        />
                      </div>

                      {/* Channels */}
                      <div className="space-y-2 pt-2 border-t border-gray-200/60 dark:border-gray-800">
                        <Label className="text-xs font-bold text-gray-700 dark:text-gray-300">Metode &amp; Channel Pembayaran Aktif</Label>
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-2">
                          {NODERAPAY_CHANNELS.map((ch) => {
                            const isChecked = noderapay.data.enabled_channels?.includes(ch.code)
                            return (
                              <button
                                key={ch.code}
                                type="button"
                                onClick={() => toggleChannel(noderapay, ch.code)}
                                className={cn(
                                  "flex items-center gap-2 rounded-xl border p-2.5 text-left text-xs transition-all cursor-pointer",
                                  isChecked
                                    ? "border-brand-500 bg-brand-50 dark:bg-brand-500/10 text-brand-700 dark:text-brand-300 font-semibold"
                                    : "border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 text-gray-600 dark:text-gray-400"
                                )}
                              >
                                {isChecked ? <CheckSquare className="h-4 w-4 text-brand-500" /> : <Square className="h-4 w-4 text-gray-400" />}
                                <div className="min-w-0 flex-1">
                                  <div className="truncate">{ch.name}</div>
                                  <div className="text-[10px] text-gray-400">{ch.badge}</div>
                                </div>
                              </button>
                            )
                          })}
                        </div>
                      </div>
                    </div>
                  )}

                  {/* WijayaPay Form */}
                  {selectedGateway === "wijayapay" && (
                    <div className="space-y-3.5 rounded-2xl border border-cyan-500/20 bg-cyan-500/5 p-4">
                      <div className="flex items-center justify-between">
                        <span className="text-xs font-bold text-cyan-700 dark:text-cyan-400">Pengaturan WijayaPay</span>
                        <span className="font-mono text-[10px] text-gray-400">wijayapay.com</span>
                      </div>

                      <div className="space-y-1.5">
                        <Label className="text-xs text-gray-700 dark:text-gray-300">Kode Merchant (Code Merchant)</Label>
                        <input
                          type="text"
                          value={wijayapay.data.WIJAYAPAY_CODE_MERCHANT}
                          onChange={(e) => wijayapay.setData("WIJAYAPAY_CODE_MERCHANT", e.target.value)}
                          placeholder="M123456"
                          required
                          className="h-10 w-full rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 px-3.5 text-xs font-mono text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-500"
                        />
                      </div>

                      <div className="space-y-1.5">
                        <Label className="text-xs text-gray-700 dark:text-gray-300">API Key</Label>
                        <input
                          type="password"
                          value={wijayapay.data.WIJAYAPAY_API_KEY}
                          onChange={(e) => wijayapay.setData("WIJAYAPAY_API_KEY", e.target.value)}
                          placeholder="api_key_..."
                          required
                          className="h-10 w-full rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 px-3.5 text-xs font-mono text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-500"
                        />
                      </div>

                      <div className="space-y-1.5">
                        <Label className="text-xs text-gray-700 dark:text-gray-300">Environment Mode</Label>
                        <select
                          value={wijayapay.data.WIJAYAPAY_MODE}
                          onChange={(e) => wijayapay.setData("WIJAYAPAY_MODE", e.target.value)}
                          className="h-10 w-full rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 px-3.5 text-xs text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-500"
                        >
                          <option value="production">Production (Live)</option>
                          <option value="sandbox">Sandbox (Testing)</option>
                        </select>
                      </div>

                      {/* Channels */}
                      <div className="space-y-2 pt-2 border-t border-gray-200/60 dark:border-gray-800">
                        <Label className="text-xs font-bold text-gray-700 dark:text-gray-300">Metode &amp; Channel Pembayaran</Label>
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-2">
                          {WIJAYAPAY_CHANNELS.map((ch) => {
                            const isChecked = wijayapay.data.enabled_channels?.includes(ch.code)
                            return (
                              <button
                                key={ch.code}
                                type="button"
                                onClick={() => toggleChannel(wijayapay, ch.code)}
                                className={cn(
                                  "flex items-center gap-2 rounded-xl border p-2.5 text-left text-xs transition-all cursor-pointer",
                                  isChecked
                                    ? "border-cyan-500 bg-cyan-50 dark:bg-cyan-500/10 text-cyan-800 dark:text-cyan-300 font-semibold"
                                    : "border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 text-gray-600 dark:text-gray-400"
                                )}
                              >
                                {isChecked ? <CheckSquare className="h-4 w-4 text-cyan-500" /> : <Square className="h-4 w-4 text-gray-400" />}
                                <div className="min-w-0 flex-1">
                                  <div className="truncate">{ch.name}</div>
                                  <div className="text-[10px] text-gray-400">{ch.badge}</div>
                                </div>
                              </button>
                            )
                          })}
                        </div>
                      </div>
                    </div>
                  )}

                  {/* Tripay Form */}
                  {selectedGateway === "tripay" && (
                    <div className="space-y-3.5 rounded-2xl border border-amber-500/20 bg-amber-500/5 p-4">
                      <div className="flex items-center justify-between">
                        <span className="text-xs font-bold text-amber-700 dark:text-amber-400">Pengaturan Tripay</span>
                        <span className="font-mono text-[10px] text-gray-400">tripay.co.id</span>
                      </div>

                      <div className="space-y-1.5">
                        <Label className="text-xs text-gray-700 dark:text-gray-300">Kode Merchant (Merchant Code)</Label>
                        <input
                          type="text"
                          value={tripay.data.TRIPAY_MERCHANT_CODE}
                          onChange={(e) => tripay.setData("TRIPAY_MERCHANT_CODE", e.target.value)}
                          placeholder="T12345"
                          required
                          className="h-10 w-full rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 px-3.5 text-xs font-mono text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-500"
                        />
                      </div>

                      <div className="space-y-1.5">
                        <Label className="text-xs text-gray-700 dark:text-gray-300">API Key</Label>
                        <input
                          type="password"
                          value={tripay.data.TRIPAY_API_KEY}
                          onChange={(e) => tripay.setData("TRIPAY_API_KEY", e.target.value)}
                          placeholder="DEV-..."
                          required
                          className="h-10 w-full rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 px-3.5 text-xs font-mono text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-500"
                        />
                      </div>

                      <div className="space-y-1.5">
                        <Label className="text-xs text-gray-700 dark:text-gray-300">Private Key</Label>
                        <input
                          type="password"
                          value={tripay.data.TRIPAY_PRIVATE_KEY}
                          onChange={(e) => tripay.setData("TRIPAY_PRIVATE_KEY", e.target.value)}
                          placeholder="private_key_..."
                          required
                          className="h-10 w-full rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 px-3.5 text-xs font-mono text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-500"
                        />
                      </div>

                      <div className="space-y-1.5">
                        <Label className="text-xs text-gray-700 dark:text-gray-300">Environment Mode</Label>
                        <select
                          value={tripay.data.TRIPAY_MODE}
                          onChange={(e) => tripay.setData("TRIPAY_MODE", e.target.value)}
                          className="h-10 w-full rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 px-3.5 text-xs text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-500"
                        >
                          <option value="production">Production (Live)</option>
                          <option value="sandbox">Sandbox (Testing)</option>
                        </select>
                      </div>

                      {/* Channels */}
                      <div className="space-y-2 pt-2 border-t border-gray-200/60 dark:border-gray-800">
                        <Label className="text-xs font-bold text-gray-700 dark:text-gray-300">Metode &amp; Channel Pembayaran</Label>
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-2">
                          {TRIPAY_CHANNELS.map((ch) => {
                            const isChecked = tripay.data.enabled_channels?.includes(ch.code)
                            return (
                              <button
                                key={ch.code}
                                type="button"
                                onClick={() => toggleChannel(tripay, ch.code)}
                                className={cn(
                                  "flex items-center gap-2 rounded-xl border p-2.5 text-left text-xs transition-all cursor-pointer",
                                  isChecked
                                    ? "border-amber-500 bg-amber-50 dark:bg-amber-500/10 text-amber-800 dark:text-amber-300 font-semibold"
                                    : "border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 text-gray-600 dark:text-gray-400"
                                )}
                              >
                                {isChecked ? <CheckSquare className="h-4 w-4 text-amber-500" /> : <Square className="h-4 w-4 text-gray-400" />}
                                <div className="min-w-0 flex-1">
                                  <div className="truncate">{ch.name}</div>
                                  <div className="text-[10px] text-gray-400">{ch.badge}</div>
                                </div>
                              </button>
                            )
                          })}
                        </div>
                      </div>
                    </div>
                  )}

                  {/* Midtrans Form */}
                  {selectedGateway === "midtrans" && (
                    <div className="space-y-3.5 rounded-2xl border border-blue-500/20 bg-blue-500/5 p-4">
                      <div className="flex items-center justify-between">
                        <span className="text-xs font-bold text-blue-700 dark:text-blue-400">Pengaturan Midtrans Snap</span>
                        <span className="font-mono text-[10px] text-gray-400">midtrans.com</span>
                      </div>

                      <div className="space-y-1.5">
                        <div className="flex items-center justify-between">
                          <Label className="text-xs text-gray-700 dark:text-gray-300">Server Key</Label>
                          <button
                            type="button"
                            onClick={() => setShowMidtransKey(!showMidtransKey)}
                            className="text-[11px] text-brand-600 hover:text-brand-700 dark:text-brand-400 flex items-center gap-1 cursor-pointer"
                          >
                            {showMidtransKey ? <EyeOff className="w-3 h-3" /> : <Eye className="w-3 h-3" />}
                            <span>{showMidtransKey ? "Sembunyikan" : "Lihat Key"}</span>
                          </button>
                        </div>
                        <input
                          type={showMidtransKey ? "text" : "password"}
                          value={midtrans.data.MIDTRANS_SERVER_KEY}
                          onChange={(e) => midtrans.setData("MIDTRANS_SERVER_KEY", e.target.value)}
                          placeholder="Mid-server-..."
                          required
                          className="h-10 w-full rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 px-3.5 text-xs font-mono text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-500"
                        />
                        {midtrans.data.MIDTRANS_SERVER_KEY?.startsWith("Mid-client-") && (
                          <p className="text-[11px] text-rose-500 font-medium">
                            ⚠️ Perhatian: Key ini diawali "Mid-client-". Ini adalah Client Key, bukan Server Key!
                          </p>
                        )}
                        {midtrans.data.MIDTRANS_SERVER_KEY?.startsWith("SB-Mid-client-") && (
                          <p className="text-[11px] text-rose-500 font-medium">
                            ⚠️ Perhatian: Key ini diawali "SB-Mid-client-". Ini adalah Sandbox Client Key, bukan Server Key!
                          </p>
                        )}
                      </div>

                      <div className="space-y-1.5">
                        <Label className="text-xs text-gray-700 dark:text-gray-300">Client Key</Label>
                        <input
                          type="text"
                          value={midtrans.data.MIDTRANS_CLIENT_KEY}
                          onChange={(e) => midtrans.setData("MIDTRANS_CLIENT_KEY", e.target.value)}
                          placeholder="Mid-client-..."
                          className="h-10 w-full rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 px-3.5 text-xs font-mono text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-500"
                        />
                      </div>

                      <div className="space-y-1.5">
                        <Label className="text-xs text-gray-700 dark:text-gray-300">Merchant ID</Label>
                        <input
                          type="text"
                          value={midtrans.data.MIDTRANS_MERCHANT_ID}
                          onChange={(e) => midtrans.setData("MIDTRANS_MERCHANT_ID", e.target.value)}
                          placeholder="G12345678"
                          className="h-10 w-full rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 px-3.5 text-xs font-mono text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-500"
                        />
                      </div>

                      <div className="space-y-1.5">
                        <Label className="text-xs text-gray-700 dark:text-gray-300">Environment</Label>
                        <select
                          value={midtrans.data.MIDTRANS_IS_PRODUCTION ? "true" : "false"}
                          onChange={(e) => midtrans.setData("MIDTRANS_IS_PRODUCTION", e.target.value === "true")}
                          className="h-10 w-full rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 px-3.5 text-xs text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-500"
                        >
                          <option value="false">Sandbox (Testing)</option>
                          <option value="true">Production (Live)</option>
                        </select>
                      </div>

                      {/* Channels */}
                      <div className="space-y-2 pt-2 border-t border-gray-200/60 dark:border-gray-800">
                        <Label className="text-xs font-bold text-gray-700 dark:text-gray-300">Metode &amp; Channel Pembayaran</Label>
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-2">
                          {MIDTRANS_CHANNELS.map((ch) => {
                            const isChecked = midtrans.data.enabled_channels?.includes(ch.code)
                            return (
                              <button
                                key={ch.code}
                                type="button"
                                onClick={() => toggleChannel(midtrans, ch.code)}
                                className={cn(
                                  "flex items-center gap-2 rounded-xl border p-2.5 text-left text-xs transition-all cursor-pointer",
                                  isChecked
                                    ? "border-blue-500 bg-blue-50 dark:bg-blue-500/10 text-blue-800 dark:text-blue-300 font-semibold"
                                    : "border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 text-gray-600 dark:text-gray-400"
                                )}
                              >
                                {isChecked ? <CheckSquare className="h-4 w-4 text-blue-500" /> : <Square className="h-4 w-4 text-gray-400" />}
                                <div className="min-w-0 flex-1">
                                  <div className="truncate">{ch.name}</div>
                                  <div className="text-[10px] text-gray-400">{ch.badge}</div>
                                </div>
                              </button>
                            )
                          })}
                        </div>
                      </div>
                    </div>
                  )}

                  {/* DOKU Form */}
                  {selectedGateway === "doku" && (
                    <div className="space-y-3.5 rounded-2xl border border-red-500/20 bg-red-500/5 p-4">
                      <div className="flex items-center justify-between">
                        <span className="text-xs font-bold text-red-700 dark:text-red-400">Pengaturan DOKU Checkout</span>
                        <span className="font-mono text-[10px] text-gray-400">doku.com</span>
                      </div>

                      <div className="space-y-1.5">
                        <Label className="text-xs text-gray-700 dark:text-gray-300">Client ID DOKU</Label>
                        <input
                          type="text"
                          value={doku.data.DOKU_CLIENT_ID}
                          onChange={(e) => doku.setData("DOKU_CLIENT_ID", e.target.value)}
                          placeholder="MCH-123456"
                          required
                          className="h-10 w-full rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 px-3.5 text-xs font-mono text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-500"
                        />
                      </div>

                      <div className="space-y-1.5">
                        <Label className="text-xs text-gray-700 dark:text-gray-300">Secret Key / Shared Key</Label>
                        <input
                          type="password"
                          value={doku.data.DOKU_SECRET_KEY}
                          onChange={(e) => doku.setData("DOKU_SECRET_KEY", e.target.value)}
                          placeholder="SK-..."
                          required
                          className="h-10 w-full rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 px-3.5 text-xs font-mono text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-500"
                        />
                      </div>

                      <div className="space-y-1.5">
                        <Label className="text-xs text-gray-700 dark:text-gray-300">Environment</Label>
                        <select
                          value={doku.data.DOKU_MODE}
                          onChange={(e) => doku.setData("DOKU_MODE", e.target.value)}
                          className="h-10 w-full rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 px-3.5 text-xs text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-500"
                        >
                          <option value="sandbox">Sandbox (Testing)</option>
                          <option value="production">Production (Live)</option>
                        </select>
                      </div>

                      {/* Channels */}
                      <div className="space-y-2 pt-2 border-t border-gray-200/60 dark:border-gray-800">
                        <Label className="text-xs font-bold text-gray-700 dark:text-gray-300">Metode &amp; Channel Pembayaran</Label>
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-2">
                          {DOKU_CHANNELS.map((ch) => {
                            const isChecked = doku.data.enabled_channels?.includes(ch.code)
                            return (
                              <button
                                key={ch.code}
                                type="button"
                                onClick={() => toggleChannel(doku, ch.code)}
                                className={cn(
                                  "flex items-center gap-2 rounded-xl border p-2.5 text-left text-xs transition-all cursor-pointer",
                                  isChecked
                                    ? "border-red-500 bg-red-50 dark:bg-red-500/10 text-red-800 dark:text-red-300 font-semibold"
                                    : "border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 text-gray-600 dark:text-gray-400"
                                )}
                              >
                                {isChecked ? <CheckSquare className="h-4 w-4 text-red-500" /> : <Square className="h-4 w-4 text-gray-400" />}
                                <div className="min-w-0 flex-1">
                                  <div className="truncate">{ch.name}</div>
                                  <div className="text-[10px] text-gray-400">{ch.badge}</div>
                                </div>
                              </button>
                            )
                          })}
                        </div>
                      </div>
                    </div>
                  )}

                  {/* Duitku Form */}
                  {selectedGateway === "duitku" && (
                    <div className="space-y-3.5 rounded-2xl border border-emerald-500/20 bg-emerald-500/5 p-4">
                      <div className="flex items-center justify-between">
                        <span className="text-xs font-bold text-emerald-700 dark:text-emerald-400">Pengaturan Duitku</span>
                        <span className="font-mono text-[10px] text-gray-400">duitku.com</span>
                      </div>

                      <div className="space-y-1.5">
                        <Label className="text-xs text-gray-700 dark:text-gray-300">Merchant Code</Label>
                        <input
                          type="text"
                          value={duitku.data.DUITKU_MERCHANT_CODE}
                          onChange={(e) => duitku.setData("DUITKU_MERCHANT_CODE", e.target.value)}
                          placeholder="D1234"
                          required
                          className="h-10 w-full rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 px-3.5 text-xs font-mono text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-500"
                        />
                      </div>

                      <div className="space-y-1.5">
                        <Label className="text-xs text-gray-700 dark:text-gray-300">API Key</Label>
                        <input
                          type="password"
                          value={duitku.data.DUITKU_API_KEY}
                          onChange={(e) => duitku.setData("DUITKU_API_KEY", e.target.value)}
                          placeholder="api_key_..."
                          required
                          className="h-10 w-full rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 px-3.5 text-xs font-mono text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-500"
                        />
                      </div>

                      <div className="space-y-1.5">
                        <Label className="text-xs text-gray-700 dark:text-gray-300">Environment</Label>
                        <select
                          value={duitku.data.DUITKU_ENV}
                          onChange={(e) => duitku.setData("DUITKU_ENV", e.target.value)}
                          className="h-10 w-full rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 px-3.5 text-xs text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-500"
                        >
                          <option value="production">Production (Live)</option>
                          <option value="sandbox">Sandbox (Testing)</option>
                        </select>
                      </div>
                    </div>
                  )}

                  {/* Xendit Form */}
                  {selectedGateway === "xendit" && (
                    <div className="space-y-3.5 rounded-2xl border border-purple-500/20 bg-purple-500/5 p-4">
                      <div className="flex items-center justify-between">
                        <span className="text-xs font-bold text-purple-700 dark:text-purple-400">Pengaturan Xendit</span>
                        <span className="font-mono text-[10px] text-gray-400">xendit.co</span>
                      </div>

                      <div className="space-y-1.5">
                        <Label className="text-xs text-gray-700 dark:text-gray-300">Secret API Key</Label>
                        <input
                          type="password"
                          value={xendit.data.XENDIT_SECRET_KEY}
                          onChange={(e) => xendit.setData("XENDIT_SECRET_KEY", e.target.value)}
                          placeholder="xnd_development_..."
                          required
                          className="h-10 w-full rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 px-3.5 text-xs font-mono text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-500"
                        />
                      </div>

                      <div className="space-y-1.5">
                        <Label className="text-xs text-gray-700 dark:text-gray-300">Public Key (Opsional)</Label>
                        <input
                          type="text"
                          value={xendit.data.XENDIT_PUBLIC_KEY}
                          onChange={(e) => xendit.setData("XENDIT_PUBLIC_KEY", e.target.value)}
                          placeholder="xnd_public_..."
                          className="h-10 w-full rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 px-3.5 text-xs font-mono text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-500"
                        />
                      </div>
                    </div>
                  )}

                  {/* Paydisini Form */}
                  {selectedGateway === "paydisini" && (
                    <div className="space-y-3.5 rounded-2xl border border-teal-500/20 bg-teal-500/5 p-4">
                      <div className="flex items-center justify-between">
                        <span className="text-xs font-bold text-teal-700 dark:text-teal-400">Pengaturan Paydisini</span>
                        <span className="font-mono text-[10px] text-gray-400">paydisini.co.id</span>
                      </div>

                      <div className="space-y-1.5">
                        <Label className="text-xs text-gray-700 dark:text-gray-300">API Key</Label>
                        <input
                          type="password"
                          value={paydisini.data.PAYDISINI_API_KEY}
                          onChange={(e) => paydisini.setData("PAYDISINI_API_KEY", e.target.value)}
                          placeholder="api_key_..."
                          required
                          className="h-10 w-full rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 px-3.5 text-xs font-mono text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-500"
                        />
                      </div>

                      <div className="space-y-1.5">
                        <Label className="text-xs text-gray-700 dark:text-gray-300">Service ID (Metode default: 11 / QRIS)</Label>
                        <input
                          type="text"
                          value={paydisini.data.PAYDISINI_SERVICE_ID}
                          onChange={(e) => paydisini.setData("PAYDISINI_SERVICE_ID", e.target.value)}
                          placeholder="11"
                          className="h-10 w-full rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 px-3.5 text-xs font-mono text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-500"
                        />
                      </div>
                    </div>
                  )}

                  {/* Pakasir Form */}
                  {selectedGateway === "pakasir" && (
                    <div className="space-y-3.5 rounded-2xl border border-rose-500/20 bg-rose-500/5 p-4">
                      <div className="flex items-center justify-between">
                        <span className="text-xs font-bold text-rose-700 dark:text-rose-400">Pengaturan Pakasir</span>
                        <span className="font-mono text-[10px] text-gray-400">pakasir.com</span>
                      </div>

                      <div className="space-y-1.5">
                        <Label className="text-xs text-gray-700 dark:text-gray-300">API Key</Label>
                        <input
                          type="password"
                          value={pakasir.data.PAKASIR_API_KEY}
                          onChange={(e) => pakasir.setData("PAKASIR_API_KEY", e.target.value)}
                          placeholder="api_key_..."
                          required
                          className="h-10 w-full rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 px-3.5 text-xs font-mono text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-500"
                        />
                      </div>

                      <div className="space-y-1.5">
                        <Label className="text-xs text-gray-700 dark:text-gray-300">Slug Merchant</Label>
                        <input
                          type="text"
                          value={pakasir.data.PAKASIR_SLUG}
                          onChange={(e) => pakasir.setData("PAKASIR_SLUG", e.target.value)}
                          placeholder="toko-anda"
                          className="h-10 w-full rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 px-3.5 text-xs font-mono text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-500"
                        />
                      </div>
                    </div>
                  )}

                  {/* Webhook Callback Info Box */}
                  <div className="rounded-xl border border-gray-100 bg-gray-50/80 dark:border-gray-800 dark:bg-gray-900/50 p-3.5 space-y-1.5 text-xs">
                    <div className="flex items-center justify-between">
                      <span className="font-bold text-gray-700 dark:text-gray-300">URL Callback / Webhook</span>
                      <button
                        type="button"
                        onClick={() => handleCopy(`${window.location.origin}/api/payment/callback/${selectedGateway}`, "webhook")}
                        className="inline-flex items-center gap-1 font-bold text-brand-500 hover:text-brand-600 cursor-pointer"
                      >
                        {copied === "webhook" ? <Check className="h-3.5 w-3.5 text-emerald-500" /> : <Copy className="h-3.5 w-3.5" />}
                        <span>{copied === "webhook" ? "Disalin!" : "Salin URL"}</span>
                      </button>
                    </div>
                    <code className="block rounded-lg bg-gray-200/60 dark:bg-gray-800 p-2 font-mono text-[11px] text-gray-800 dark:text-gray-200 break-all select-all">
                      {window.location.origin}/api/payment/callback/{selectedGateway}
                    </code>
                    <p className="text-[11px] text-gray-500 dark:text-gray-400">
                      Tempel URL ini pada dashboard {selectedGateway} Anda agar status invoice terupdate otomatis seketika saat pelanggan membayar.
                    </p>
                  </div>
                </div>

                {/* Modal Footer */}
                <div className="flex items-center justify-between border-t border-gray-100 px-5 py-3.5 shrink-0 dark:border-gray-800">
                  <button
                    type="button"
                    onClick={() => handleTestConnection(selectedGateway)}
                    disabled={testingGw === selectedGateway}
                    className="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:hover:bg-gray-800 text-xs font-semibold text-gray-700 dark:text-gray-200 transition shadow-2xs cursor-pointer disabled:opacity-50"
                  >
                    <Play className="h-3.5 w-3.5 text-brand-500" />
                    <span>{testingGw === selectedGateway ? "Menguji..." : "Uji Kredensial"}</span>
                  </button>

                  <div className="flex items-center gap-2">
                    <button
                      type="button"
                      onClick={() => setModalOpen(false)}
                      className="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:hover:bg-gray-800 text-xs font-semibold text-gray-700 dark:text-gray-200 transition shadow-2xs cursor-pointer"
                    >
                      Batal
                    </button>
                    <button
                      type="submit"
                      disabled={isProcessing}
                      className="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-brand-500 hover:bg-brand-600 active:scale-95 text-xs font-bold text-white shadow-xs transition cursor-pointer disabled:opacity-50"
                    >
                      <Save className="h-3.5 w-3.5" />
                      <span>{isProcessing ? "Menyimpan..." : "Simpan Gateway"}</span>
                    </button>
                  </div>
                </div>
              </form>
            </div>
          </div>
        )}
      </div>
    </AppLayout>
  )
}
