import { Switch } from "@/components/tailadmin/Switch"
import { AppLayout } from "@/components/layout/app-layout"
import {
  MessageSquare,
  Save,
  RotateCcw,
  Sparkles,
  Info,
  CheckCircle2,
  AlertCircle,
  Smartphone,
  ChevronDown,
  Layers,
  Search,
  Check,
  X,
  Clock,
  Radio,
  FileText,
  Copy,
  ShieldCheck,
  Send,
  Eye,
  EyeOff,
  Code,
  Unlock,
  SlidersHorizontal,
  Activity,
  Settings2,
  Sliders,
  Globe,
  Key,
  Hash,
} from "lucide-react"
import { PageProps } from "@/types"
import { adminSidebarItems, adminNavItems, adminBrand } from "@/lib/admin-nav"
import { superadminSidebarItems, superadminNavItems, superadminBrand } from "@/lib/superadmin-nav"
import { router, Link } from "@inertiajs/react"
import { useState, useMemo } from "react"
import { cn } from "@/lib/utils"
import { ServiceSettingsSheet } from "@/components/layout/service-settings-sheet"
import { ViewModeSwitcher, type ViewMode } from "@/components/ui/view-mode-switcher"
import Chart from "react-apexcharts"
import type { ApexOptions } from "apexcharts"

interface Template {
  id: number
  key: string
  name: string
  category: "billing" | "isolir" | "pppoe" | "voucher" | "member" | "vpn" | "mikhmon" | "referral" | "general"
  message: string
  description?: string
  variables?: string[]
  is_active: boolean
  updated_at?: string
}

interface NotifSettings {
  reminder_enabled: boolean
  reminder_days_before?: number
  reminder_time?: string
  isolir_enabled: boolean
  isolir_time?: string
}

interface WaSettings {
  is_configured: boolean
  is_active: boolean
  provider?: string
  sender_phone?: string
  api_url?: string
  token?: string
  auto_typing?: boolean
}

interface DeviceStatus {
  is_configured: boolean
  is_connected: boolean
  status: "connected" | "disconnected" | "connecting" | "unconfigured" | "error"
  status_label: string
  provider: string
  sender_id: string
  details?: string
  checked_at?: string
}

const DYNAMIC_VARIABLES = [
  // General & Company
  { tag: "{company_name}", label: "Nama Perusahaan / ISP", desc: "Nama branding tenant atau platform", group: "general" },
  { tag: "{company_phone}", label: "No HP / CS Kantor", desc: "Nomor WhatsApp customer service kantor", group: "general" },
  { tag: "{company_address}", label: "Alamat Kantor", desc: "Alamat domisili kantor billing / ISP", group: "general" },
  { tag: "{tanggal}", label: "Tanggal Saat Ini", desc: "Format: DD MMMM YYYY", group: "general" },
  { tag: "{waktu}", label: "Waktu Saat Ini", desc: "Format: HH:mm WIB", group: "general" },

  // Pelanggan & User
  { tag: "{nama}", label: "Nama Lengkap Pelanggan", desc: "Contoh: Budi Santoso", group: "general" },
  { tag: "{username}", label: "Username / ID Pelanggan", desc: "Username PPPoE / Hotspot / Member", group: "general" },
  { tag: "{password}", label: "Password Akun", desc: "Kata sandi login pelanggan", group: "general" },
  { tag: "{paket}", label: "Nama Paket Internet", desc: "Contoh: Home 20 Mbps / Voucher 5 Jam", group: "general" },
  { tag: "{tagihan}", label: "Nominal Tagihan (Rupiah)", desc: "Contoh: Rp 150.000", group: "general" },

  // Tagihan & Jatuh Tempo
  { tag: "{periode}", label: "Periode Bulan Tagihan", desc: "Contoh: Oktober 2026", group: "billing" },
  { tag: "{jatuh_tempo}", label: "Tanggal Jatuh Tempo", desc: "Contoh: 20 Oktober 2026", group: "billing" },
  { tag: "{no_invoice}", label: "Nomor Invoice / Faktur", desc: "Contoh: INV-202610-0012", group: "billing" },
  { tag: "{status_bayar}", label: "Status Pembayaran", desc: "LUNAS atau BELUM LUNAS", group: "billing" },
  { tag: "{metode_bayar}", label: "Metode Pembayaran", desc: "Contoh: QRIS / Transfer BCA / Tunai", group: "billing" },
  { tag: "{link_pembayaran}", label: "Tautan Portal Pembayaran", desc: "URL invoice pembayaran online mandiri", group: "billing" },

  // Layanan PPPoE & Hotspot
  { tag: "{ip_address}", label: "IP Address Terkoneksi", desc: "IP Address aktif pelanggan", group: "pppoe" },
  { tag: "{mac_address}", label: "MAC Address Perangkat", desc: "MAC address interface / router pelanggan", group: "pppoe" },
  { tag: "{expired}", label: "Waktu Kedaluwarsa", desc: "Masa berlaku voucher atau layanan", group: "voucher" },
  { tag: "{uptime}", label: "Durasi Aktif Penggunaan", desc: "Total lama waktu terhubung", group: "voucher" },
  { tag: "{kuota}", label: "Batas Total Kuota Data", desc: "Contoh: 10 GB / Unlimited", group: "voucher" },
  { tag: "{router_name}", label: "Nama Router MikroTik", desc: "Nama router yang menangani pelanggan", group: "pppoe" },

  // Member & Deposit
  { tag: "{email}", label: "Alamat Email", desc: "Contoh: user@gmail.com", group: "member" },
  { tag: "{phone}", label: "Nomor WhatsApp", desc: "Contoh: 081234567890", group: "member" },
  { tag: "{saldo}", label: "Saldo Awal / Bonus", desc: "Contoh: 10.000", group: "member" },
  { tag: "{nominal}", label: "Nominal Transaksi / Deposit", desc: "Contoh: 50.000", group: "member" },
  { tag: "{nominal_topup}", label: "Nominal Pokok Deposit", desc: "Contoh: 100.000", group: "member" },
  { tag: "{kode_unik}", label: "Kode Unik Verifikasi", desc: "Contoh: 234", group: "member" },
  { tag: "{saldo_sebelum}", label: "Saldo Awal Sebelum Transaksi", desc: "Contoh: 50.000", group: "member" },
  { tag: "{saldo_sesudah}", label: "Saldo Akhir Setelah Transaksi", desc: "Contoh: 150.000", group: "member" },
  { tag: "{total_transfer}", label: "Total Yang Harus Ditransfer", desc: "Contoh: 100.234", group: "member" },
  { tag: "{rekening_tujuan}", label: "Nomor & Rekening Tujuan Bank", desc: "Contoh: BCA 123456789 a/n NODERA", group: "member" },
  { tag: "{saldo_sekarang}", label: "Total Saldo Member Terkini", desc: "Contoh: 75.000", group: "member" },
  { tag: "{topup_url}", label: "Tautan Konfirmasi Deposit", desc: "URL cek status pembayaran deposit", group: "member" },
  { tag: "{alasan}", label: "Alasan Penolakan", desc: "Catatan atau alasan dari admin", group: "member" },

  // Cloud VPN & Mikhmon
  { tag: "{server_name}", label: "Nama Server VPN", desc: "Contoh: SG-Cloud-1", group: "vpn" },
  { tag: "{host}", label: "Host Domain / IP Server", desc: "Contoh: vpn.nodera.id", group: "vpn" },
  { tag: "{protocol}", label: "Protokol VPN", desc: "Contoh: L2TP / SSTP / OVPN / WG", group: "vpn" },
  { tag: "{ip_static}", label: "IP Statis VPN", desc: "Contoh: 10.254.1.5", group: "vpn" },
  { tag: "{ports}", label: "Daftar Port Remote MikroTik", desc: "Port Winbox, API, Web, SSH, OLT", group: "vpn" },
  { tag: "{detail_url}", label: "Tautan Detail & Script Akun", desc: "URL melihat script otomatis MikroTik", group: "vpn" },
  { tag: "{auto_renew}", label: "Status Perpanjangan Otomatis", desc: "AKTIF atau NONAKTIF", group: "vpn" },
  { tag: "{url}", label: "URL Akses Server Mikhmon", desc: "Contoh: https://subdomain.nodera.id", group: "mikhmon" },
  { tag: "{ros_version}", label: "Versi RouterOS Mikhmon", desc: "v6 atau v7", group: "mikhmon" },

  // Referral Program
  { tag: "{nama_mitra}", label: "Nama Mitra Referral", desc: "Nama pemilik akun afiliasi", group: "referral" },
  { tag: "{kode_referral}", label: "Kode Kupon / Referral Mitra", desc: "Contoh: MITRA123", group: "referral" },
  { tag: "{rate}", label: "Persentase Komisi (%)", desc: "Contoh: 10", group: "referral" },
  { tag: "{link_referral}", label: "Link Promosi Referral", desc: "Contoh: https://nodera.id/ref/MITRA123", group: "referral" },
  { tag: "{portal_url}", label: "URL Portal Afiliasi", desc: "Tautan dashboard komisi mitra", group: "referral" },
  { tag: "{downline}", label: "Nama Member Downline", desc: "Nama member yang diajak", group: "referral" },
  { tag: "{nominal_topup}", label: "Nominal Deposit Downline", desc: "Contoh: 100.000", group: "referral" },
  { tag: "{nominal_komisi}", label: "Nominal Komisi Didapatkan", desc: "Contoh: 10.000", group: "referral" },
  { tag: "{saldo_komisi}", label: "Sisa Saldo Komisi Mitra", desc: "Contoh: 50.000", group: "referral" },
  { tag: "{bank}", label: "Nama Bank / E-Wallet Tujuan", desc: "Contoh: BCA / Dana / Wave", group: "referral" },
  { tag: "{rekening}", label: "Nomor Rekening / HP Pencairan", desc: "Contoh: 1234567890", group: "referral" },
  { tag: "{atas_nama}", label: "Nama Pemilik Rekening", desc: "Nama sesuai buku tabungan", group: "referral" },
  { tag: "{catatan}", label: "Catatan Admin Pencairan", desc: "Keterangan mutasi / transfer", group: "referral" },
]

export default function WhatsappTemplatesPage({
  templates: initialTemplates = [],
  is_superadmin = false,
  is_locked = true,
  notifSettings: initialNotifSettings,
  waSettings,
  deviceStatus: initialDeviceStatus,
  companyName = "NODERA Billing",
}: PageProps<{
  templates: Template[]
  is_superadmin?: boolean
  is_locked?: boolean
  notifSettings?: NotifSettings
  waSettings?: WaSettings
  deviceStatus?: DeviceStatus
  companyName?: string
}>) {
  const [templates, setTemplates] = useState<Template[]>(initialTemplates)
  const [notifSettings, setNotifSettings] = useState<NotifSettings>(
    initialNotifSettings || {
      reminder_enabled: true,
      isolir_enabled: true,
    }
  )

  const [deviceStatus, setDeviceStatus] = useState<DeviceStatus | null>(initialDeviceStatus || null)
  const [isCheckingDevice, setIsCheckingDevice] = useState(false)

  const [activeCategory, setActiveCategory] = useState<string>("all")
  const [viewMode, setViewMode] = useState<ViewMode>("table")
  const [searchQuery, setSearchQuery] = useState<string>("")
  const [expandedTemplateId, setExpandedTemplateId] = useState<number | string | null>(initialTemplates[0]?.id || null)
  const [showVariablesCard, setShowVariablesCard] = useState(false)
  const [waSettingsOpen, setWaSettingsOpen] = useState(false)
  const [savingId, setSavingId] = useState<number | null>(null)
  const [resetting, setResetting] = useState(false)
  const [feedback, setFeedback] = useState<{ type: "success" | "error" | "info"; message: string } | null>(null)
  const [copiedTag, setCopiedTag] = useState<string | null>(null)
  const [copiedTextId, setCopiedTextId] = useState<number | null>(null)

  // Feedback timer
  const showToast = (type: "success" | "error" | "info", message: string) => {
    setFeedback({ type, message })
    setTimeout(() => {
      setFeedback(null)
    }, 4000)
  }

  // Handle Probe Device Status
  const handleProbeDeviceStatus = async () => {
    setIsCheckingDevice(true)
    try {
      const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") ?? ""
      const res = await fetch("/admin/whatsapp/device-status", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Accept: "application/json",
          "X-CSRF-TOKEN": csrfToken,
        },
      })
      const data = await res.json()
      if (data.success && data.data) {
        setDeviceStatus(data.data)
        if (data.data.is_connected) {
          showToast("success", `Perangkat WhatsApp (${data.data.provider}) online dan terhubung!`)
        } else {
          showToast("error", data.data.details || `Sesi WhatsApp (${data.data.provider}) terputus / tidak aktif.`)
        }
      } else {
        showToast("error", "Gagal membaca status perangkat WhatsApp.")
      }
    } catch (err: any) {
      showToast("error", "Gagal menghubungi server WA Gateway: " + (err?.message || "Koneksi terputus"))
    } finally {
      setIsCheckingDevice(false)
    }
  }

  // In-Page Gateway Configuration State
  const [gatewayModalOpen, setGatewayModalOpen] = useState(false)
  const [gatewayForm, setGatewayForm] = useState({
    provider: waSettings?.provider || "nodera",
    api_url: waSettings?.api_url || "https://wa.dgtlnetsolution.com/api/send-message",
    token: waSettings?.token || "",
    sender_phone: waSettings?.sender_phone || "",
    auto_typing: waSettings?.auto_typing ?? true,
  })
  const [showToken, setShowToken] = useState(false)
  const [savingGateway, setSavingGateway] = useState(false)
  const [gatewayError, setGatewayError] = useState<string | null>(null)

  interface ProviderPreset {
    label: string
    defaultUrl: string
    guideText: string
    hasToken: boolean
    tokenLabel: string
    tokenPlaceholder: string
    tokenHint: string
    tokenRequired?: boolean
    hasSender: boolean
    senderRequired?: boolean
    senderLabel: string
    senderPlaceholder: string
    senderHint: string
    hasUrl: boolean
    urlLabel: string
    urlPlaceholder: string
    urlHint?: string
    urlOptions?: { value: string; label: string }[]
  }

  const providerPresets: Record<string, ProviderPreset> = {
    mhwa: {
      label: "MHWA Gateway (mhwa.biz.id)",
      defaultUrl: "https://mhwa.biz.id/api/message/send",
      guideText: "Masukkan API Key dan Session ID perangkat WhatsApp Anda yang aktif di dashboard MHWA.",
      hasToken: true,
      tokenLabel: "API Key (x-api-key)",
      tokenPlaceholder: "Masukkan x-api-key dari menu API di mhwa.biz.id",
      tokenHint: "Kunci autentikasi API akun MHWA Anda.",
      tokenRequired: true,
      hasSender: true,
      senderRequired: true,
      senderLabel: "Session ID",
      senderPlaceholder: "Contoh: dev_21_1789624375160",
      senderHint: "ID sesi perangkat persis seperti yang tertera di menu Sesi/Device MHWA.",
      hasUrl: true,
      urlLabel: "Endpoint API URL",
      urlPlaceholder: "https://mhwa.biz.id/api/message/send",
      urlHint: "Endpoint POST pesan default MHWA Gateway.",
    },
    fonnte: {
      label: "Fonnte (fonnte.com)",
      defaultUrl: "https://api.fonnte.com/send",
      guideText: "Di Fonnte, Anda cukup memasukkan Account API Token perangkat. Nomor pengirim otomatis terikat ke token.",
      hasToken: true,
      tokenLabel: "API Token Fonnte",
      tokenPlaceholder: "Contoh: pz#1234567890abcdef...",
      tokenHint: "Salin Token ini dari menu 'Device' di dashboard Fonnte Anda.",
      tokenRequired: true,
      hasSender: false,
      senderLabel: "",
      senderPlaceholder: "",
      senderHint: "",
      hasUrl: false,
      urlLabel: "",
      urlPlaceholder: "",
    },
    nodera: {
      label: "NODERA Official Gateway (High-Speed Engine)",
      defaultUrl: "https://wa.dgtlnetsolution.com/api/send-message",
      guideText: "Gateway resmi NODERA dengan latensi ultra-cepat dan auto-queue.",
      hasToken: true,
      tokenLabel: "API Key / Secret Token",
      tokenPlaceholder: "Masukkan API Key dari dashboard NODERA WA Gateway",
      tokenHint: "Kunci autentikasi API akun NODERA WA Anda.",
      tokenRequired: true,
      hasSender: true,
      senderRequired: false,
      senderLabel: "Session ID (Opsional)",
      senderPlaceholder: "Contoh: session_master atau 08123456789",
      senderHint: "ID sesi perangkat jika Anda menggunakan multi-device.",
      hasUrl: true,
      urlLabel: "Endpoint Gateway URL",
      urlPlaceholder: "https://wa.dgtlnetsolution.com/api/send-message",
    },
    wablas: {
      label: "Wablas (wablas.com)",
      defaultUrl: "https://kudus.wablas.com/api/send-message",
      guideText: "Pilih domain server Wablas akun Anda, masukkan Authorization Token, dan nomor WhatsApp pengirim.",
      hasToken: true,
      tokenLabel: "API Token Authorization",
      tokenPlaceholder: "Masukkan Authorization Token dari dashboard Wablas",
      tokenHint: "Dapatkan token dari menu Integrasi / API di Wablas.",
      tokenRequired: true,
      hasSender: true,
      senderRequired: true,
      senderLabel: "Nomor WhatsApp Pengirim",
      senderPlaceholder: "Contoh: 08123456789",
      senderHint: "Nomor WhatsApp ponsel yang tersambung di device Wablas.",
      hasUrl: true,
      urlLabel: "Pilih Server Domain Wablas",
      urlPlaceholder: "https://kudus.wablas.com/api/send-message",
      urlOptions: [
        { value: "https://kudus.wablas.com/api/send-message", label: "Server Kudus (kudus.wablas.com)" },
        { value: "https://solo.wablas.com/api/send-message", label: "Server Solo (solo.wablas.com)" },
        { value: "https://jogja.wablas.com/api/send-message", label: "Server Jogja (jogja.wablas.com)" },
        { value: "https://jakarta.wablas.com/api/send-message", label: "Server Jakarta (jakarta.wablas.com)" },
        { value: "https://medan.wablas.com/api/send-message", label: "Server Medan (medan.wablas.com)" },
        { value: "https://borneo.wablas.com/api/send-message", label: "Server Borneo (borneo.wablas.com)" },
      ],
    },
    starsender: {
      label: "Starsender (starsender.online)",
      defaultUrl: "https://starsender.online/api/sendText",
      guideText: "Cukup masukkan API Key dari dashboard Starsender Anda.",
      hasToken: true,
      tokenLabel: "API Key Starsender",
      tokenPlaceholder: "Masukkan API Key akun Starsender Anda",
      tokenHint: "Salin API Key dari dashboard profil Starsender.",
      tokenRequired: true,
      hasSender: false,
      senderLabel: "",
      senderPlaceholder: "",
      senderHint: "",
      hasUrl: false,
      urlLabel: "",
      urlPlaceholder: "",
    },
    kirimi: {
      label: "Kirimi.id (kirimi.id)",
      defaultUrl: "https://api.kirimi.id/v1/send-message",
      guideText: "Masukkan Secret Key dan kombinasi User Code & Device ID dari dashboard Kirimi.id.",
      hasToken: true,
      tokenLabel: "Secret API Key",
      tokenPlaceholder: "Masukkan Secret Key dari akun Kirimi.id",
      tokenHint: "Kunci rahasia API akun Kirimi.id Anda.",
      tokenRequired: true,
      hasSender: true,
      senderRequired: true,
      senderLabel: "User Code : Device ID",
      senderPlaceholder: "Contoh: KM123:D-456",
      senderHint: "Format: UserCode:DeviceID dipisah tanda titik dua (:).",
      hasUrl: false,
      urlLabel: "",
      urlPlaceholder: "",
    },
    whacenter: {
      label: "WhaCenter (whacenter.com)",
      defaultUrl: "https://app.whacenter.com/api/send",
      guideText: "Cukup masukkan Device ID perangkat yang aktif di dashboard WhaCenter.",
      hasToken: false,
      tokenLabel: "",
      tokenPlaceholder: "",
      tokenHint: "",
      hasSender: true,
      senderRequired: true,
      senderLabel: "Device ID WhaCenter",
      senderPlaceholder: "Contoh: dev_whacenter_12345",
      senderHint: "Device ID perangkat WhatsApp di dashboard WhaCenter.",
      hasUrl: false,
      urlLabel: "",
      urlPlaceholder: "",
    },
    custom: {
      label: "Custom REST API Gateway",
      defaultUrl: "",
      guideText: "Gunakan untuk menghubungkan server WhatsApp gateway kustom mandiri berbasis REST API.",
      hasUrl: true,
      urlLabel: "Endpoint REST API URL (POST)",
      urlPlaceholder: "https://api.domain-anda.com/v1/send-message",
      urlHint: "URL endpoint API WhatsApp kustom Anda.",
      hasToken: true,
      tokenLabel: "Authorization Header / API Key",
      tokenPlaceholder: "Contoh: Bearer secret_token atau api_key_123",
      tokenHint: "Token atau kunci autentikasi header HTTP.",
      tokenRequired: true,
      hasSender: true,
      senderRequired: false,
      senderLabel: "Session ID / Nomor Pengirim (Opsional)",
      senderPlaceholder: "Contoh: session_id atau 08123456789",
      senderHint: "Parameter sesi/pengirim jika diperlukan oleh API kustom Anda.",
    },
  }

  const handleProviderSelect = (providerKey: string) => {
    const preset = providerPresets[providerKey]
    setGatewayForm((prev) => ({
      ...prev,
      provider: providerKey,
      api_url: preset?.defaultUrl || prev.api_url,
    }))
  }

  const handleSaveGateway = async (e: React.FormEvent) => {
    e.preventDefault()
    setSavingGateway(true)
    setGatewayError(null)

    try {
      const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") ?? ""
      const res = await fetch("/admin/whatsapp/test-and-save", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Accept: "application/json",
          "X-CSRF-TOKEN": csrfToken,
        },
        body: JSON.stringify({
          provider: gatewayForm.provider,
          api_url: gatewayForm.api_url,
          token: gatewayForm.token,
          sender_phone: gatewayForm.sender_phone,
          auto_typing: gatewayForm.auto_typing,
        }),
      })

      const data = await res.json()
      if (res.ok && data.success) {
        showToast("success", data.message || "Pengaturan WhatsApp Gateway berhasil disimpan & terhubung!")
        setGatewayModalOpen(false)
        handleProbeDeviceStatus()
      } else {
        setGatewayError(data.message || "Gagal menyimpan & memverifikasi WhatsApp Gateway.")
      }
    } catch (err: any) {
      setGatewayError("Gagal menghubungi server: " + (err?.message || "Koneksi terputus"))
    } finally {
      setSavingGateway(false)
    }
  }

  // Test Send State
  const [testModalOpen, setTestModalOpen] = useState(false)
  const [manageTemplate, setManageTemplate] = useState<Template | null>(null)
  const [testPhone, setTestPhone] = useState(waSettings?.sender_phone || "")
  const [testMessage, setTestMessage] = useState(
    `Halo! Ini adalah pesan uji coba koneksi WhatsApp Gateway dari ${companyName || "NODERA"}.\n\n✅ Gateway WhatsApp aktif dan siap mengirimkan notifikasi platform & tenant secara otomatis.`
  )
  const [testLoading, setTestLoading] = useState(false)

  // Handle Send Test Message
  const handleSendTestMessage = async (e: React.FormEvent) => {
    e.preventDefault()
    if (!testPhone.trim()) {
      showToast("error", "Nomor WhatsApp tujuan tidak boleh kosong.")
      return
    }

    setTestLoading(true)
    try {
      const res = await fetch("/admin/whatsapp/test-message", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Accept: "application/json",
          "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") ?? "",
        },
        body: JSON.stringify({
          phone: testPhone,
          message: testMessage,
        }),
      })

      const data = await res.json()
      if (res.ok && data.success !== false) {
        showToast("success", `Pesan uji coba berhasil dikirim ke ${testPhone}`)
        setTestModalOpen(false)
      } else {
        showToast("error", data.message || "Gagal mengirim pesan uji coba. Periksa koneksi API WhatsApp Anda.")
      }
    } catch (err) {
      showToast("error", "Terjadi kesalahan sistem saat menghubungi gateway WhatsApp.")
    } finally {
      setTestLoading(false)
    }
  }

  // Update pesan template lokal
  const handleMessageChange = (id: number, message: string) => {
    setTemplates((prev) => prev.map((t) => (t.id === id ? { ...t, message } : t)))
  }

  // Toggle status aktif template
  const handleToggleActive = async (id: number, currentStatus: boolean) => {
    const target = templates.find((t) => t.id === id)
    const nextStatus = !currentStatus
    setTemplates((prev) => prev.map((t) => (t.id === id ? { ...t, is_active: nextStatus } : t)))

    try {
      const res = await fetch(`/admin/whatsapp-templates/toggle/${id}`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Accept: "application/json",
          "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") ?? "",
        },
        body: JSON.stringify({ id, name: target?.name, is_active: nextStatus }),
      })

      const data = await res.json()
      if (!res.ok || data.success === false) throw new Error(data?.message || "Gagal mengubah status")
      if (typeof data.is_active === "boolean") {
        setTemplates((prev) => prev.map((t) => (t.id === id ? { ...t, is_active: data.is_active, id: data.id ?? t.id } : t)))
      }
      showToast("success", `Status template berhasil ${nextStatus ? "diaktifkan" : "dinonaktifkan"}.`)
    } catch {
      // Rollback
      setTemplates((prev) => prev.map((t) => (t.id === id ? { ...t, is_active: currentStatus } : t)))
      showToast("error", "Gagal memperbarui status aktif template.")
    }
  }

  // Simpan template per item
  const handleSaveTemplate = async (template: Template) => {
    setSavingId(template.id)
    try {
      const res = await fetch(`/admin/whatsapp-templates/save`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Accept: "application/json",
          "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") ?? "",
        },
        body: JSON.stringify({
          id: template.id,
          name: template.name,
          message: template.message,
          is_active: template.is_active,
        }),
      })

      const data = await res.json()
      if (res.ok && data.success !== false) {
        if (data.id) {
          setTemplates((prev) => prev.map((t) => (t.id === template.id ? { ...t, id: data.id } : t)))
        }
        showToast("success", `Template "${template.name}" berhasil disimpan.`)
      } else {
        showToast("error", data.message || "Gagal menyimpan perubahan template.")
      }
    } catch {
      showToast("error", "Terjadi kesalahan saat menyimpan template.")
    } finally {
      setSavingId(null)
    }
  }

  // Sisipkan variabel ke cursor textarea
  const handleInsertVariable = (id: number, variableTag: string) => {
    const textarea = document.getElementById(`template-textarea-${id}`) as HTMLTextAreaElement | null
    if (!textarea) return

    const start = textarea.selectionStart
    const end = textarea.selectionEnd
    const currentVal = textarea.value
    const newVal = currentVal.substring(0, start) + variableTag + currentVal.substring(end)

    handleMessageChange(id, newVal)

    // Kembalikan fokus dan posisikan kursor setelah variabel yang baru disisipkan
    setTimeout(() => {
      textarea.focus()
      textarea.setSelectionRange(start + variableTag.length, start + variableTag.length)
    }, 50)

    setCopiedTag(variableTag)
    setTimeout(() => setCopiedTag(null), 1500)
  }

  // Simpan Pengaturan Pengingat Otomatis
  const handleSaveNotifSettings = async (updated: Partial<NotifSettings>) => {
    const nextState = { ...notifSettings, ...updated }
    setNotifSettings(nextState)

    try {
      const res = await fetch("/admin/whatsapp/save-notif-settings", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Accept: "application/json",
          "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") ?? "",
        },
        body: JSON.stringify(nextState),
      })

      if (res.ok) {
        showToast("success", "Pengaturan notifikasi otomatis berhasil diperbarui.")
      } else {
        showToast("error", "Gagal menyimpan pengaturan notifikasi.")
      }
    } catch {
      showToast("error", "Terjadi kesalahan jaringan.")
    }
  }

  // Reset Template ke Default Pabrik
  const handleResetDefaults = async () => {
    if (!confirm("Apakah Anda yakin ingin mengembalikan seluruh template WhatsApp ke format bawaan pabrik NODERA?")) return

    setResetting(true)
    try {
      const res = await fetch("/admin/whatsapp-templates/reset-defaults", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Accept: "application/json",
          "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") ?? "",
        },
      })

      const data = await res.json()
      if (res.ok) {
        if (data.templates) setTemplates(data.templates)
        showToast("success", "Seluruh template berhasil direset ke standar pabrik.")
        router.reload()
      } else {
        showToast("error", data.message || "Gagal mereset template.")
      }
    } catch {
      showToast("error", "Terjadi kesalahan saat mereset template.")
    } finally {
      setResetting(false)
    }
  }

  // Filter templates
  const filteredTemplates = useMemo(() => {
    return templates.filter((t) => {
      const matchCat = activeCategory === "all" || t.category === activeCategory
      const matchQuery =
        !searchQuery ||
        t.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
        t.key.toLowerCase().includes(searchQuery.toLowerCase()) ||
        (t.description && t.description.toLowerCase().includes(searchQuery.toLowerCase())) ||
        t.message.toLowerCase().includes(searchQuery.toLowerCase())
      return matchCat && matchQuery
    })
  }, [templates, activeCategory, searchQuery])

  // Hitung jumlah per kategori
  const categoryCounts = useMemo(() => {
    const counts: Record<string, number> = { all: templates.length }
    templates.forEach((t) => {
      counts[t.category] = (counts[t.category] || 0) + 1
    })
    return counts
  }, [templates])

  const allCategories: { id: string; label: string; icon: any }[] = [
    { id: "all", label: "Semua Template", icon: Layers },
    { id: "billing", label: "Billing & Tagihan", icon: FileText },
    { id: "isolir", label: "Isolir & Blokir", icon: AlertCircle },
    { id: "pppoe", label: "Layanan PPPoE", icon: Radio },
    { id: "voucher", label: "Hotspot & Voucher", icon: Sparkles },
    { id: "member", label: "Member & Deposit", icon: ShieldCheck },
    { id: "vpn", label: "Cloud VPN Server", icon: Activity },
    { id: "mikhmon", label: "Server Mikhmon", icon: Smartphone },
    { id: "referral", label: "Mitra & Referral", icon: MessageSquare },
  ]

  // Filter kategori agar hanya menampilkan kategori yang memiliki template (> 0)
  const categories = useMemo(() => {
    return allCategories.filter((c) => {
      if (c.id === "all") return true
      return (categoryCounts[c.id] || 0) > 0
    })
  }, [allCategories, categoryCounts])

  // Copy seluruh teks pesan
  const handleCopyMessage = (id: number, text: string) => {
    navigator.clipboard.writeText(text)
    setCopiedTextId(id)
    showToast("info", "Format pesan berhasil disalin ke clipboard.")
    setTimeout(() => setCopiedTextId(null), 2000)
  }

  // Status Donut Chart Data
  const statusDonutData = useMemo(() => {
    const active = templates.filter((t) => t.is_active).length
    const inactive = templates.filter((t) => !t.is_active).length
    return {
      series: [active, inactive],
      labels: ["Template Aktif", "Template Nonaktif"],
    }
  }, [templates])

  const statusDonutOptions: ApexOptions = useMemo(() => ({
    chart: { type: "donut", fontFamily: "inherit" },
    labels: statusDonutData.labels,
    colors: ["#10B981", "#94A3B8"],
    legend: { position: "bottom", labels: { colors: "#64748B" } },
    dataLabels: { enabled: true, formatter: (val) => `${Number(val).toFixed(0)}%` },
    stroke: { width: 2, colors: ["transparent"] },
    plotOptions: {
      pie: {
        donut: {
          size: "68%",
          labels: {
            show: true,
            total: {
              show: true,
              label: "Total Format",
              color: "#64748B",
              formatter: () => `${templates.length}`,
            },
          },
        },
      },
    },
    tooltip: {
      theme: "light",
      y: { formatter: (val) => `${val} Format Template` },
    },
  }), [statusDonutData, templates.length])

  // Category Bar Chart Data
  const categoryBarData = useMemo(() => {
    const catMap: Record<string, number> = {}
    categories.filter((c) => c.id !== "all").forEach((c) => {
      catMap[c.label] = 0
    })
    templates.forEach((t) => {
      const catObj = categories.find((c) => c.id === t.category)
      const label = catObj ? catObj.label : t.category
      catMap[label] = (catMap[label] || 0) + 1
    })
    const filteredLabels = Object.keys(catMap).filter((k) => catMap[k] > 0)
    return {
      categories: filteredLabels,
      series: [{ name: "Jumlah Template", data: filteredLabels.map((k) => catMap[k]) }],
    }
  }, [templates, categories])

  const categoryBarOptions: ApexOptions = useMemo(() => ({
    chart: { type: "bar", fontFamily: "inherit", toolbar: { show: false } },
    plotOptions: {
      bar: {
        horizontal: true,
        borderRadius: 4,
        barHeight: "55%",
        distributed: true,
      },
    },
    colors: ["#3B82F6", "#10B981", "#8B5CF6", "#F59E0B", "#EC4899", "#06B6D4", "#6366F1"],
    dataLabels: { enabled: true, formatter: (val) => `${val}` },
    xaxis: {
      categories: categoryBarData.categories,
      labels: { style: { colors: "#64748B", fontSize: "11px" } },
    },
    yaxis: {
      labels: { style: { colors: "#64748B", fontSize: "11px" } },
    },
    legend: { show: false },
    tooltip: {
      theme: "light",
      y: { formatter: (val) => `${val} Template` },
    },
  }), [categoryBarData])

  return (
    <AppLayout
      title="Template & Notifikasi WhatsApp"
            brand={is_superadmin ? superadminBrand : adminBrand}
      sidebarItems={is_superadmin ? superadminSidebarItems : adminSidebarItems}
      navItems={is_superadmin ? superadminNavItems : adminNavItems}
    >
      <div className="space-y-6">
        {/* ── TOAST NOTIFICATION (TAILADMIN DESIGN SYSTEM) ── */}
        {feedback && (
          <div className="fixed top-20 sm:top-6 right-4 sm:right-6 z-[99999] max-w-sm w-[calc(100vw-2rem)] sm:w-96 animate-in slide-in-from-top-4 fade-in duration-200 pointer-events-auto">
            <div
              className={cn(
                "flex items-start gap-3 rounded-2xl border p-4 shadow-xl transition",
                feedback.type === "success"
                  ? "border-emerald-200 bg-white text-emerald-950 dark:border-emerald-500/30 dark:bg-gray-900 dark:text-emerald-100"
                  : feedback.type === "error"
                  ? "border-rose-200 bg-white text-rose-950 dark:border-rose-500/30 dark:bg-gray-900 dark:text-rose-100"
                  : "border-blue-200 bg-white text-blue-950 dark:border-blue-500/30 dark:bg-gray-900 dark:text-blue-100"
              )}
            >
              <div
                className={cn(
                  "shrink-0 p-2 rounded-xl flex items-center justify-center",
                  feedback.type === "success"
                    ? "bg-emerald-500 text-white"
                    : feedback.type === "error"
                    ? "bg-rose-500 text-white"
                    : "bg-[#0073C6] text-white"
                )}
              >
                {feedback.type === "success" && <CheckCircle2 className="h-4 w-4" />}
                {feedback.type === "error" && <AlertCircle className="h-4 w-4" />}
                {feedback.type === "info" && <Info className="h-4 w-4" />}
              </div>
              <div className="flex-1 min-w-0 pt-0.5">
                <h4 className="font-bold text-xs text-gray-900 dark:text-white">
                  {feedback.type === "success" ? "Berhasil" : feedback.type === "error" ? "Gagal" : "Informasi"}
                </h4>
                <p className="text-xs mt-0.5 leading-relaxed text-gray-600 dark:text-gray-300 break-words">{feedback.message}</p>
              </div>
              <button
                type="button"
                onClick={() => setFeedback(null)}
                className="shrink-0 p-1 rounded-lg text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition cursor-pointer"
              >
                <X className="h-4 w-4" />
              </button>
            </div>
          </div>
        )}

        {/* ── TOP TELEMETRY & ANALYTICS ROW ── */}
        <div className="grid grid-cols-1 md:grid-cols-3 gap-3 sm:gap-4">
          {/* Card 1: WhatsApp Gateway Status & Quick Action */}
          <div className="rounded-2xl border border-gray-200 bg-white p-5 shadow-xs dark:border-gray-800 dark:bg-white/[0.03] flex flex-col justify-between">
            <div>
              <div className="flex items-center justify-between gap-2">
                <span className="text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                  Status Gateway WhatsApp
                </span>
                <div className="flex items-center gap-1.5">
                  <span
                    className={cn(
                      "inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-[11px] font-bold text-white shadow-xs",
                      deviceStatus?.is_connected
                        ? "bg-emerald-500"
                        : deviceStatus?.is_configured
                        ? "bg-rose-500"
                        : "bg-gray-500"
                    )}
                  >
                    <span
                      className={cn(
                        "h-1.5 w-1.5 rounded-full bg-white",
                        deviceStatus?.is_connected && "animate-pulse"
                      )}
                    />
                    {isCheckingDevice
                      ? "Mengecek..."
                      : deviceStatus?.status_label || (waSettings?.is_configured ? "Terputus" : "Belum Siap")}
                  </span>
                  <button
                    type="button"
                    onClick={handleProbeDeviceStatus}
                    disabled={isCheckingDevice}
                    title="Cek Status Live Perangkat WhatsApp"
                    className="p-1 rounded-lg text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-gray-800 transition disabled:opacity-50 cursor-pointer"
                  >
                    <RotateCcw className={cn("h-3.5 w-3.5", isCheckingDevice && "animate-spin text-brand-500")} />
                  </button>
                </div>
              </div>

              <div 
                onClick={() => setGatewayModalOpen(true)}
                className="mt-4 flex items-center gap-3 p-2 rounded-xl hover:bg-gray-50 dark:hover:bg-gray-900/60 cursor-pointer transition border border-transparent hover:border-gray-200 dark:hover:border-gray-800"
                title="Klik untuk mengubah konfigurasi gateway WhatsApp"
              >
                <div
                  className={cn(
                    "flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl border",
                    deviceStatus?.is_connected
                      ? "border-emerald-200 bg-emerald-50 text-emerald-600 dark:border-emerald-900/40 dark:bg-emerald-950/20 dark:text-emerald-400"
                      : "border-rose-200 bg-rose-50 text-rose-600 dark:border-rose-900/40 dark:bg-rose-950/20 dark:text-rose-400"
                  )}
                >
                  <Smartphone className="h-6 w-6" />
                </div>
                <div className="min-w-0 flex-1">
                  <div className="flex items-center justify-between">
                    <span className="text-xs text-gray-500 dark:text-gray-400 font-medium">Provider Aktif</span>
                    <span className="text-[10px] font-bold text-brand-500 underline flex items-center gap-1">
                      <Sliders className="h-3 w-3" /> Ganti
                    </span>
                  </div>
                  <div className="text-sm font-bold text-gray-900 dark:text-white uppercase truncate">
                    {deviceStatus?.provider || waSettings?.provider || "FONNTE / MHWA"}
                  </div>
                </div>
              </div>

              <div className="mt-3 rounded-xl border border-gray-100 bg-gray-50/80 p-2.5 dark:border-gray-800 dark:bg-gray-900/50 flex items-center justify-between gap-2">
                <span className="text-[11px] font-medium text-gray-500 dark:text-gray-400">
                  {waSettings?.provider === "mhwa"
                    ? "Session ID"
                    : waSettings?.provider === "wablas"
                    ? "Nomor Pengirim"
                    : waSettings?.provider === "whacenter" || waSettings?.provider === "kirimi"
                    ? "Device ID"
                    : waSettings?.provider === "fonnte" || waSettings?.provider === "starsender"
                    ? "Kredensial API"
                    : "Session ID"}
                </span>
                <span className="font-mono text-xs font-bold text-brand-600 dark:text-brand-400 truncate max-w-[160px]" title={deviceStatus?.sender_id || waSettings?.sender_phone || "Aktif"}>
                  {deviceStatus?.sender_id || waSettings?.sender_phone || "Aktif"}
                </span>
              </div>

              {deviceStatus?.details && (
                <div className={cn(
                  "mt-2.5 p-2 rounded-xl text-[11px] leading-relaxed border font-medium",
                  deviceStatus.is_connected
                    ? "bg-emerald-50/60 border-emerald-200/60 text-emerald-800 dark:bg-emerald-950/30 dark:border-emerald-900/40 dark:text-emerald-300"
                    : "bg-rose-50/60 border-rose-200/60 text-rose-800 dark:bg-rose-950/30 dark:border-rose-900/40 dark:text-rose-300"
                )}>
                  {deviceStatus.details}
                </div>
              )}
            </div>

            <div className="mt-4 pt-3 border-t border-gray-100 dark:border-gray-800 grid grid-cols-2 gap-2">
              <button
                type="button"
                onClick={() => setGatewayModalOpen(true)}
                className="inline-flex items-center justify-center gap-1.5 h-9 rounded-xl bg-brand-500 hover:bg-brand-600 active:scale-95 text-xs font-bold text-white shadow-xs transition cursor-pointer"
              >
                <Settings2 className="h-3.5 w-3.5" />
                <span>Atur Gateway</span>
              </button>
              <button
                type="button"
                onClick={() => setTestModalOpen(true)}
                className="inline-flex items-center justify-center gap-1.5 h-9 rounded-xl bg-emerald-500 hover:bg-emerald-600 active:scale-95 text-xs font-bold text-white shadow-xs transition cursor-pointer"
              >
                <Send className="h-3.5 w-3.5" />
                <span>Uji Pesan</span>
              </button>
            </div>
          </div>

          {/* Card 2: Status Template Donut Chart */}
          <div className="rounded-2xl border border-gray-200 bg-white p-5 shadow-xs dark:border-gray-800 dark:bg-white/[0.03]">
            <div className="flex items-center justify-between">
              <div>
                <h4 className="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                  Rasio Status Template
                </h4>
                <div className="mt-1 flex items-baseline gap-2">
                  <span className="text-xl font-bold text-gray-900 dark:text-white">
                    {templates.filter((t) => t.is_active).length} Aktif
                  </span>
                  <span className="text-xs text-gray-500 dark:text-gray-400">
                    dari {templates.length} total
                  </span>
                </div>
              </div>
              <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400">
                <CheckCircle2 className="h-4 w-4" />
              </div>
            </div>
            <div className="mt-2 flex justify-center">
              <Chart
                options={statusDonutOptions}
                series={statusDonutData.series}
                type="donut"
                height={165}
                width="100%"
              />
            </div>
          </div>

          {/* Card 3: Distribusi Kategori Template Horizontal Bar Chart */}
          <div className="rounded-2xl border border-gray-200 bg-white p-5 shadow-xs dark:border-gray-800 dark:bg-white/[0.03]">
            <div className="flex items-center justify-between">
              <div>
                <h4 className="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                  Distribusi Kategori
                </h4>
                <div className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                  Sebaran template per modul layanan
                </div>
              </div>
              <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-400">
                <Layers className="h-4 w-4" />
              </div>
            </div>
            <div className="mt-1">
              <Chart
                options={categoryBarOptions}
                series={categoryBarData.series}
                type="bar"
                height={170}
                width="100%"
              />
            </div>
          </div>
        </div>

        {/* ── BAGIAN OTOMASI NOTIFIKASI SISTEM ── */}
        <div className="rounded-2xl border border-gray-200 bg-white p-5 shadow-xs dark:border-gray-800 dark:bg-white/[0.03] space-y-4">
          <div className="flex items-center gap-2">
            <div className="flex h-7 w-7 items-center justify-center rounded-lg bg-brand-500/10 text-brand-600 dark:bg-brand-500/20 dark:text-brand-400">
              <Clock className="h-4 w-4" />
            </div>
            <div>
              <h4 className="text-sm font-bold text-gray-900 dark:text-white">
                Otomasi Notifikasi Pengingat
              </h4>
              <p className="text-[11px] text-gray-500 dark:text-gray-400">
                Pemicu pengiriman pesan otomatis saat mendekati jatuh tempo & isolir pelanggan
              </p>
            </div>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-2 gap-3.5">
            {/* Box 1: Pengingat Jatuh Tempo */}
            <div className="p-4 rounded-xl border border-gray-200 bg-gray-50/60 dark:border-gray-800 dark:bg-gray-900/40 flex items-center justify-between gap-3">
              <div className="flex items-center gap-3 min-w-0">
                <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-brand-500/10 text-brand-600 dark:bg-brand-500/20 dark:text-brand-400">
                  <Clock className="h-5 w-5" />
                </div>
                <div className="min-w-0">
                  <h5 className="font-bold text-xs sm:text-sm text-gray-900 dark:text-white truncate">
                    Pengingat Jatuh Tempo
                  </h5>
                  <p className="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5">
                    Kirim berkala otomatis H-3 sebelum tagihan jatuh tempo
                  </p>
                </div>
              </div>

              <div className="flex items-center gap-2 shrink-0">
                <Switch
                  checked={notifSettings.reminder_enabled}
                  onChange={(checked) => handleSaveNotifSettings({ reminder_enabled: checked })}
                />
                <span className={cn(
                  "text-[10px] font-bold px-2 py-0.5 rounded-md text-white shadow-xs hidden sm:inline",
                  notifSettings.reminder_enabled ? "bg-emerald-500" : "bg-gray-500"
                )}>
                  {notifSettings.reminder_enabled ? "Aktif" : "Nonaktif"}
                </span>
              </div>
            </div>

            {/* Box 2: Pemberitahuan Isolir Otomatis */}
            <div className="p-4 rounded-xl border border-gray-200 bg-gray-50/60 dark:border-gray-800 dark:bg-gray-900/40 flex items-center justify-between gap-3">
              <div className="flex items-center gap-3 min-w-0">
                <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-rose-500/10 text-rose-600 dark:bg-rose-500/20 dark:text-rose-400">
                  <AlertCircle className="h-5 w-5" />
                </div>
                <div className="min-w-0">
                  <h5 className="font-bold text-xs sm:text-sm text-gray-900 dark:text-white truncate">
                    Pemberitahuan Isolir Otomatis
                  </h5>
                  <p className="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5">
                    Kirim pesan realtime saat akun MikroTik pelanggan diisolir
                  </p>
                </div>
              </div>

              <div className="flex items-center gap-2 shrink-0">
                <Switch
                  checked={notifSettings.isolir_enabled}
                  onChange={(checked) => handleSaveNotifSettings({ isolir_enabled: checked })}
                />
                <span className={cn(
                  "text-[10px] font-bold px-2 py-0.5 rounded-md text-white shadow-xs hidden sm:inline",
                  notifSettings.isolir_enabled ? "bg-emerald-500" : "bg-gray-500"
                )}>
                  {notifSettings.isolir_enabled ? "Aktif" : "Nonaktif"}
                </span>
              </div>
            </div>
          </div>
        </div>

        {/* ── BAGIAN 2: KAMUS VARIABEL DINAMIS ── */}
        <div className="rounded-2xl border border-gray-200 bg-white shadow-xs dark:border-gray-800 dark:bg-white/[0.03] overflow-hidden">
          <button
            type="button"
            onClick={() => setShowVariablesCard(!showVariablesCard)}
            className="w-full flex items-center justify-between p-4 sm:p-5 hover:bg-gray-50/50 dark:hover:bg-white/[0.02] transition text-left"
          >
            <div className="flex items-center gap-3 min-w-0 flex-1">
              <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-400">
                <Code className="h-4 w-4" />
              </div>
              <div className="min-w-0 flex-1">
                <div className="flex items-center gap-2">
                  <h4 className="font-bold text-xs uppercase tracking-wider text-gray-900 dark:text-white">
                    Kamus Variabel Dinamis Sistem
                  </h4>
                  <span className="text-[10px] font-mono font-bold px-2 py-0.5 rounded-full bg-gray-100 dark:bg-gray-800 text-brand-600 dark:text-brand-400">
                    {DYNAMIC_VARIABLES.length} Variabel
                  </span>
                </div>
                <p className="text-xs text-gray-500 dark:text-gray-400 mt-0.5 truncate">
                  Klik untuk {showVariablesCard ? "menutup" : "membuka"} referensi variabel dinamis WhatsApp
                </p>
              </div>
            </div>

            <ChevronDown
              className={cn(
                "h-4 w-4 transition-transform duration-200 text-gray-400",
                showVariablesCard && "rotate-180 text-brand-500"
              )}
            />
          </button>

          {showVariablesCard && (
            <div className="p-4 sm:p-5 pt-0 border-t border-gray-100 dark:border-gray-800 space-y-3">
              <div className="pt-3">
                <p className="text-xs text-gray-600 dark:text-gray-400 mb-3">
                  Klik salah satu variabel di bawah untuk menyisipkannya langsung ke template yang sedang Anda edit:
                </p>
                <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-2">
                  {DYNAMIC_VARIABLES.map((v) => (
                    <button
                      key={v.tag}
                      type="button"
                      onClick={() => {
                        if (typeof expandedTemplateId === "number") {
                          handleInsertVariable(expandedTemplateId, v.tag)
                        } else {
                          navigator.clipboard.writeText(v.tag)
                          setCopiedTag(v.tag)
                          showToast("info", `Variabel ${v.tag} disalin ke clipboard.`)
                          setTimeout(() => setCopiedTag(null), 2000)
                        }
                      }}
                      className="flex flex-col text-left p-2.5 rounded-xl border border-gray-200 bg-gray-50/50 hover:bg-white hover:border-brand-500 dark:border-gray-800 dark:bg-gray-900/50 dark:hover:bg-gray-900 dark:hover:border-brand-500 transition group shadow-xs"
                      title={`${v.label} - ${v.desc}`}
                    >
                      <div className="flex items-center justify-between gap-1 w-full">
                        <code className="font-mono text-xs font-bold text-brand-600 dark:text-brand-400">
                          {v.tag}
                        </code>
                        {copiedTag === v.tag ? (
                          <Check className="h-3 w-3 text-emerald-500 shrink-0" />
                        ) : (
                          <Copy className="h-3 w-3 text-gray-400 group-hover:text-gray-600 dark:group-hover:text-gray-200 shrink-0" />
                        )}
                      </div>
                      <span className="text-[11px] font-semibold text-gray-800 dark:text-gray-200 mt-1 truncate">
                        {v.label}
                      </span>
                      <span className="text-[10px] text-gray-500 dark:text-gray-400 truncate mt-0.5">
                        {v.desc}
                      </span>
                    </button>
                  ))}
                </div>
              </div>
            </div>
          )}
        </div>

        {/* ── BAGIAN 3: TEMPLATE LIST & CATEGORY TABS ── */}
        <div className="p-3 sm:p-4 rounded-2xl border border-gray-200 bg-white shadow-xs dark:border-gray-800 dark:bg-white/[0.03] space-y-4">
          <div className="flex flex-col gap-2.5 sm:gap-3 lg:flex-row lg:items-center lg:justify-between">
            {/* Search & Filter */}
            <div className="flex flex-wrap items-center gap-2 sm:gap-3 flex-1 min-w-0">
              <div className="relative flex-1 min-w-[180px] sm:min-w-[220px] w-full sm:w-auto">
                <Search className="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                <input
                  value={searchQuery}
                  onChange={(e) => setSearchQuery(e.target.value)}
                  placeholder="Cari nama template, key, isi pesan..."
                  className="h-10 w-full pl-9 pr-8 rounded-xl text-xs font-medium border border-gray-200 bg-gray-50/50 hover:bg-white focus:bg-white dark:border-gray-800 dark:bg-gray-900/50 dark:hover:bg-gray-900 text-gray-900 dark:text-white placeholder-gray-400 focus:outline-none focus:border-brand-500 transition"
                />
                {searchQuery && (
                  <button
                    type="button"
                    onClick={() => setSearchQuery("")}
                    className="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 cursor-pointer"
                  >
                    <X className="h-3.5 w-3.5" />
                  </button>
                )}
              </div>

              {/* Category Select Filter */}
              <select
                value={activeCategory}
                onChange={(e) => setActiveCategory(e.target.value)}
                className="h-10 rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 px-3 text-xs font-semibold text-gray-700 dark:text-gray-200 cursor-pointer w-full sm:w-auto shrink-0 focus:outline-none focus:border-brand-500"
              >
                {categories.map((cat) => (
                  <option key={cat.id} value={cat.id}>
                    {cat.label} ({categoryCounts[cat.id] || 0})
                  </option>
                ))}
              </select>
            </div>

            {/* Actions & ViewModeSwitcher */}
            <div className="flex items-center gap-2 shrink-0 w-full sm:w-auto justify-end">
              <button
                type="button"
                onClick={handleResetDefaults}
                disabled={resetting}
                className="inline-flex items-center gap-1.5 h-10 px-3 rounded-xl border border-rose-200 bg-rose-50 hover:bg-rose-100 text-rose-700 dark:border-rose-900/40 dark:bg-rose-950/20 dark:text-rose-300 text-xs font-semibold transition disabled:opacity-50 cursor-pointer"
                title="Reset Semua Template ke Standar Sistem"
              >
                <RotateCcw className={cn("h-3.5 w-3.5", resetting && "animate-spin")} />
                <span className="hidden sm:inline">Reset Standar</span>
              </button>

              <ViewModeSwitcher
                value={viewMode}
                onChange={setViewMode}
              />
            </div>
          </div>

          {/* Render Separated Table View or Cards */}
          {viewMode === "table" ? (
            <div className="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03] shadow-xs overflow-hidden">
              <div className="w-full overflow-x-auto custom-scrollbar">
                <table className="w-full min-w-[950px] text-start text-xs border-collapse">
                  <thead>
                    <tr className="border-b border-gray-200 dark:border-gray-800 bg-gray-50/75 dark:bg-gray-900/50">
                      <th className="py-3.5 px-4 font-bold text-gray-600 dark:text-gray-300 text-left">Nama Template & Key</th>
                      <th className="py-3.5 px-4 font-bold text-gray-600 dark:text-gray-300 text-center">Kategori</th>
                      <th className="py-3.5 px-4 font-bold text-gray-600 dark:text-gray-300 text-left">Isi Format Pesan</th>
                      <th className="py-3.5 px-4 font-bold text-gray-600 dark:text-gray-300 text-center">Status</th>
                      <th className="py-3.5 px-4 font-bold text-gray-600 dark:text-gray-300 text-center">Aksi</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-gray-100 dark:divide-gray-800/60">
                    {filteredTemplates.length === 0 ? (
                      <tr>
                        <td colSpan={5} className="py-12 text-center text-gray-400">
                          Tidak ada template yang cocok dengan filter atau kata kunci.
                        </td>
                      </tr>
                    ) : (
                      filteredTemplates.map((t) => (
                        <tr key={t.id} className="hover:bg-gray-50/50 dark:hover:bg-gray-800/30 transition">
                          {/* Nama & Trigger Key */}
                          <td className="py-3.5 px-4 max-w-[220px]">
                            <div className="flex items-center gap-2.5">
                              <div
                                className={cn(
                                  "flex h-8 w-8 shrink-0 items-center justify-center rounded-lg font-mono text-xs font-bold",
                                  t.is_active
                                    ? "bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-400"
                                    : "bg-gray-100 text-gray-400 dark:bg-gray-800 dark:text-gray-500"
                                )}
                              >
                                <MessageSquare className="h-4 w-4" />
                              </div>
                              <div className="min-w-0">
                                <div className="font-bold text-gray-900 dark:text-white truncate">{t.name}</div>
                                <div className="font-mono text-[11px] text-gray-500 dark:text-gray-400">{t.key}</div>
                              </div>
                            </div>
                          </td>

                          {/* Kategori Badge */}
                          <td className="py-3.5 px-4 text-center">
                            <span className="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-bold bg-brand-500 text-white shadow-xs whitespace-nowrap uppercase">
                              {t.category}
                            </span>
                          </td>

                          {/* Preview Format Pesan */}
                          <td className="py-3.5 px-4 max-w-[400px]">
                            <p className="text-gray-600 dark:text-gray-300 line-clamp-2 leading-relaxed text-[11px] font-mono bg-gray-50 dark:bg-gray-900/60 p-2 rounded-lg border border-gray-100 dark:border-gray-800/80">
                              {t.message}
                            </p>
                          </td>

                          {/* Status */}
                          <td className="py-3.5 px-4 text-center">
                            <span
                              className={cn(
                                "inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-bold text-white shadow-xs whitespace-nowrap",
                                t.is_active ? "bg-emerald-500" : "bg-gray-500"
                              )}
                            >
                              {t.is_active ? "Aktif" : "Nonaktif"}
                            </span>
                          </td>

                          {/* Aksi: Single Kelola Button */}
                          <td className="py-3.5 px-4 text-center">
                            <button
                              type="button"
                              onClick={() => setManageTemplate(t)}
                              className="h-8 px-3.5 rounded-lg bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200 font-bold text-xs transition cursor-pointer shadow-2xs inline-flex items-center gap-1"
                            >
                              <SlidersHorizontal className="size-3.5" /> Kelola
                            </button>
                          </td>
                        </tr>
                      ))
                    )}
                  </tbody>
                </table>
              </div>
            </div>
          ) : (
            /* Cards View */
            <div className="space-y-3 pt-2">
              {filteredTemplates.length === 0 ? (
                <div className="p-12 text-center text-gray-400 border border-dashed border-gray-200 dark:border-gray-800 rounded-2xl">
                  <MessageSquare className="mx-auto h-10 w-10 text-gray-300 dark:text-gray-600 mb-2" />
                  <p className="font-semibold text-gray-700 dark:text-gray-300">Tidak ada template yang cocok</p>
                  <p className="text-xs text-gray-500 dark:text-gray-400 mt-1">Coba ubah kata kunci pencarian atau kategori filter.</p>
                </div>
              ) : (
                filteredTemplates.map((t) => {
                  return (
                    <div
                      key={t.id}
                      className="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03] p-4 shadow-xs transition hover:border-gray-300 dark:hover:border-gray-700 flex flex-col sm:flex-row sm:items-center justify-between gap-3"
                    >
                      <div className="flex items-center gap-3 min-w-0 flex-1">
                        <div
                          className={cn(
                            "flex h-9 w-9 shrink-0 items-center justify-center rounded-xl font-mono text-xs font-bold",
                            t.is_active
                              ? "bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-400"
                              : "bg-gray-100 text-gray-400 dark:bg-gray-800 dark:text-gray-500"
                          )}
                        >
                          <MessageSquare className="h-4 w-4" />
                        </div>
                        <div className="min-w-0 flex-1">
                          <div className="flex items-center gap-2 flex-wrap">
                            <h5 className="font-bold text-sm text-gray-900 dark:text-white truncate">
                              {t.name}
                            </h5>
                            <span className="font-mono text-[10px] px-2 py-0.5 rounded bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 font-semibold">
                              {t.key}
                            </span>
                            <span
                              className={cn(
                                "text-[10px] font-bold px-2 py-0.5 rounded-md shadow-xs text-white",
                                t.is_active
                                  ? "bg-emerald-500"
                                  : "bg-gray-500"
                              )}
                            >
                              {t.is_active ? "Aktif" : "Nonaktif"}
                            </span>
                          </div>
                          {t.description && (
                            <p className="text-xs text-gray-500 dark:text-gray-400 mt-0.5 truncate">
                              {t.description}
                            </p>
                          )}
                        </div>
                      </div>

                      <div className="flex items-center gap-2 shrink-0 self-end sm:self-center">
                        <button
                          type="button"
                          onClick={() => setManageTemplate(t)}
                          className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200 font-bold text-xs transition cursor-pointer shadow-2xs"
                        >
                          <SlidersHorizontal className="size-3.5" /> Kelola
                        </button>
                      </div>
                    </div>
                  )
                })
              )}
            </div>
          )}
        </div>

        {/* ── SHEET: PENGATURAN WHATSAPP GATEWAY (GEAR ICON) ── */}
        <ServiceSettingsSheet
          service={waSettingsOpen ? "whatsapp" : null}
          onClose={() => {
            setWaSettingsOpen(false)
            router.reload()
          }}
        />

        {/* ── MODAL: KELOLA TEMPLATE WHATSAPP ── */}
        {manageTemplate && (
          <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-xs p-4 animate-fadeIn">
            <div className="w-full max-w-2xl rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 shadow-2xl p-6 space-y-4 max-h-[92vh] overflow-y-auto">
              <div className="flex items-center justify-between border-b border-gray-100 dark:border-gray-800 pb-3">
                <div className="flex items-center gap-2.5">
                  <div className="flex h-9 w-9 items-center justify-center rounded-xl bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-400 font-bold">
                    <MessageSquare className="h-4 w-4" />
                  </div>
                  <div>
                    <h4 className="font-bold text-base text-gray-900 dark:text-white">Kelola Template: {manageTemplate.name}</h4>
                    <p className="text-xs text-gray-500 dark:text-gray-400">Trigger: <span className="font-mono text-brand-600 dark:text-brand-400 font-bold">{manageTemplate.key}</span></p>
                  </div>
                </div>
                <button
                  type="button"
                  onClick={() => setManageTemplate(null)}
                  className="p-1 rounded-lg text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 cursor-pointer"
                >
                  <X className="h-5 w-5" />
                </button>
              </div>

              {/* Status Switch Box */}
              <div className="rounded-xl border border-gray-100 bg-gray-50/80 dark:border-gray-800 dark:bg-gray-900/50 p-3.5 flex items-center justify-between">
                <div>
                  <span className="text-xs font-bold text-gray-900 dark:text-white block">Status Pengiriman Notifikasi</span>
                  <span className="text-[11px] text-gray-500 dark:text-gray-400">Aktifkan untuk mengirim otomatis saat event trigger terjadi</span>
                </div>
                <div className="flex items-center gap-2">
                  <Switch
                    checked={manageTemplate.is_active}
                    onChange={(checked) => {
                      handleToggleActive(manageTemplate.id, !checked)
                      setManageTemplate({ ...manageTemplate, is_active: checked })
                    }}
                  />
                  <span className={cn(
                    "text-[11px] font-bold px-2 py-0.5 rounded-md text-white shadow-xs",
                    manageTemplate.is_active ? "bg-emerald-500" : "bg-gray-500"
                  )}>
                    {manageTemplate.is_active ? "Aktif" : "Nonaktif"}
                  </span>
                </div>
              </div>

              {/* Message Editor */}
              <div className="space-y-2">
                <div className="flex items-center justify-between">
                  <label className="text-xs font-bold text-gray-700 dark:text-gray-300">
                    Format Isi Pesan WhatsApp
                  </label>
                  <button
                    type="button"
                    onClick={() => handleCopyMessage(manageTemplate.id, manageTemplate.message)}
                    className="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg border border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-800 text-xs font-semibold text-gray-700 dark:text-gray-200 transition shadow-2xs cursor-pointer"
                  >
                    {copiedTextId === manageTemplate.id ? (
                      <>
                        <Check className="h-3.5 w-3.5 text-emerald-500" />
                        <span className="text-emerald-600 dark:text-emerald-400">Tersalin</span>
                      </>
                    ) : (
                      <>
                        <Copy className="h-3.5 w-3.5" />
                        <span>Salin Format</span>
                      </>
                    )}
                  </button>
                </div>

                <textarea
                  rows={8}
                  value={manageTemplate.message}
                  onChange={(e) => {
                    const newMsg = e.target.value
                    handleMessageChange(manageTemplate.id, newMsg)
                    setManageTemplate({ ...manageTemplate, message: newMsg })
                  }}
                  className="w-full rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-950 p-3.5 font-mono text-xs text-gray-900 dark:text-white placeholder-gray-400 focus:outline-hidden focus:border-brand-500 transition leading-relaxed resize-y"
                  placeholder="Tulis format template WhatsApp di sini..."
                />

                {/* Quick Insert Variables */}
                <div className="space-y-1.5 pt-1">
                  <span className="text-[11px] font-semibold text-gray-500 dark:text-gray-400 block">
                    Sisipkan Variabel Terkait:
                  </span>
                  <div className="flex flex-wrap gap-1.5 max-h-32 overflow-y-auto p-1">
                    {(manageTemplate.variables && manageTemplate.variables.length > 0
                      ? manageTemplate.variables
                      : DYNAMIC_VARIABLES.filter(
                          (v) => v.group === "general" || v.group === manageTemplate.category
                        ).map((v) => v.tag)
                    ).map((tag) => (
                      <button
                        key={tag}
                        type="button"
                        onClick={() => {
                          handleInsertVariable(manageTemplate.id, tag)
                          setManageTemplate((prev) => prev ? { ...prev, message: prev.message + " " + tag } : null)
                        }}
                        className="inline-flex items-center gap-1 px-2 py-1 rounded-lg border border-gray-200 bg-white hover:border-brand-500 hover:text-brand-600 dark:border-gray-800 dark:bg-gray-800 text-[11px] font-mono text-gray-700 dark:text-gray-300 transition cursor-pointer"
                        title={`Sisipkan ${tag}`}
                      >
                        <span>{tag}</span>
                      </button>
                    ))}
                  </div>
                </div>
              </div>

              {/* Action Buttons */}
              <div className="flex flex-wrap items-center justify-end gap-2 pt-3 border-t border-gray-100 dark:border-gray-800">
                <button
                  type="button"
                  onClick={() => {
                    setTestMessage(manageTemplate.message)
                    setTestModalOpen(true)
                  }}
                  className="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:hover:bg-gray-800 text-xs font-semibold text-gray-700 dark:text-gray-200 transition shadow-2xs cursor-pointer"
                >
                  <Send className="size-3.5 text-emerald-500" /> Uji Kirim Pesan
                </button>
                <button
                  type="button"
                  onClick={() => setManageTemplate(null)}
                  className="px-4 py-2 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:hover:bg-gray-800 text-xs font-semibold text-gray-700 dark:text-gray-200 transition shadow-2xs cursor-pointer"
                >
                  Tutup
                </button>
                <button
                  type="button"
                  onClick={() => {
                    handleSaveTemplate(manageTemplate)
                    setManageTemplate(null)
                  }}
                  disabled={savingId === manageTemplate.id}
                  className="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-brand-500 hover:bg-brand-600 active:scale-95 text-xs font-bold text-white shadow-xs transition cursor-pointer disabled:opacity-50"
                >
                  <Save className="size-4" /> Simpan Template
                </button>
              </div>
            </div>
          </div>
        )}

        {/* ── MODAL: UJI KIRIM PESAN WHATSAPP ── */}
        {testModalOpen && (
          <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4 animate-in fade-in duration-200">
            <div className="w-full max-w-lg rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 shadow-2xl p-5 sm:p-6 space-y-4 max-h-[92vh] overflow-y-auto">
              <div className="flex items-center justify-between border-b border-gray-100 dark:border-gray-800 pb-3">
                <div className="flex items-center gap-2.5">
                  <div className="flex h-9 w-9 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400 font-bold">
                    <Send className="h-4 w-4" />
                  </div>
                  <div>
                    <h4 className="font-bold text-base text-gray-900 dark:text-white">Uji Kirim Pesan WhatsApp</h4>
                    <p className="text-xs text-gray-500 dark:text-gray-400">Pastikan API Gateway WhatsApp aktif dan dapat mengirim pesan</p>
                  </div>
                </div>
                <button
                  type="button"
                  onClick={() => setTestModalOpen(false)}
                  className="p-1 rounded-lg text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"
                >
                  <X className="h-5 w-5" />
                </button>
              </div>

              <form onSubmit={handleSendTestMessage} className="space-y-4">
                <div className="space-y-1.5">
                  <label className="text-xs font-semibold text-gray-700 dark:text-gray-300">
                    Nomor WhatsApp Tujuan
                  </label>
                  <input
                    type="text"
                    required
                    placeholder="Contoh: 08123456789 atau 628123456789"
                    value={testPhone}
                    onChange={(e) => setTestPhone(e.target.value)}
                    className="w-full h-10 rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 px-3.5 text-xs text-gray-900 dark:text-white placeholder-gray-400 focus:outline-none focus:border-brand-500 transition"
                  />
                  <p className="text-[11px] text-gray-500 dark:text-gray-400">Format internasional didukung (awalan 08 / 628 / +62)</p>
                </div>

                <div className="space-y-1.5">
                  <label className="text-xs font-semibold text-gray-700 dark:text-gray-300">
                    Isi Pesan Uji Coba
                  </label>
                  <textarea
                    rows={4}
                    required
                    value={testMessage}
                    onChange={(e) => setTestMessage(e.target.value)}
                    className="w-full rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 p-3 text-xs text-gray-900 dark:text-white leading-relaxed placeholder-gray-400 focus:outline-none focus:border-brand-500 transition"
                  />
                </div>

                <div className="flex items-center justify-end gap-2.5 pt-3 border-t border-gray-100 dark:border-gray-800">
                  <button
                    type="button"
                    onClick={() => setTestModalOpen(false)}
                    className="px-4 py-2 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:hover:bg-gray-800 text-xs font-semibold text-gray-700 dark:text-gray-300 transition cursor-pointer"
                  >
                    Batal
                  </button>
                  <button
                    type="submit"
                    disabled={testLoading}
                    className="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-xs font-bold text-white shadow-xs transition disabled:opacity-50 cursor-pointer"
                  >
                    {testLoading ? (
                      <>
                        <RotateCcw className="h-3.5 w-3.5 animate-spin" />
                        <span>Mengirim...</span>
                      </>
                    ) : (
                      <>
                        <Send className="h-3.5 w-3.5" />
                        <span>Kirim Sekarang</span>
                      </>
                    )}
                  </button>
                </div>
              </form>
            </div>
          </div>
        )}

        {/* ── GATEWAY CONFIGURATION MODAL (IN-PAGE SETTINGS) ── */}
        {gatewayModalOpen && (
          <div className="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-gray-900/60 backdrop-blur-xs overflow-y-auto">
            <div className="relative w-full max-w-xl max-h-[92vh] flex flex-col rounded-2xl border border-gray-200 bg-white shadow-2xl dark:border-gray-800 dark:bg-gray-900 my-auto animate-in zoom-in-95 duration-150">
              {/* Modal Header */}
              <div className="flex items-center justify-between p-5 border-b border-gray-100 dark:border-gray-800 shrink-0">
                <div className="flex items-center gap-3">
                  <div className="h-10 w-10 rounded-xl bg-brand-50 dark:bg-brand-500/10 text-brand-600 dark:text-brand-400 flex items-center justify-center border border-brand-100 dark:border-brand-500/20">
                    <Settings2 className="h-5 w-5" />
                  </div>
                  <div>
                    <h3 className="text-base font-bold text-gray-900 dark:text-white">
                      Pengaturan WhatsApp Gateway
                    </h3>
                    <p className="text-xs text-gray-500 dark:text-gray-400">
                      Pilih provider &amp; konfigurasi kredensial WhatsApp API
                    </p>
                  </div>
                </div>
                <button
                  type="button"
                  onClick={() => setGatewayModalOpen(false)}
                  className="p-1.5 rounded-lg text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-800 transition cursor-pointer"
                >
                  <X className="h-5 w-5" />
                </button>
              </div>

              {/* Scrollable Form Body */}
              <form onSubmit={handleSaveGateway} className="flex-1 overflow-y-auto p-5 sm:p-6 space-y-4">
                {gatewayError && (
                  <div className="p-3.5 rounded-xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-900/50 flex items-start gap-2.5">
                    <AlertCircle className="h-4 w-4 text-rose-600 shrink-0 mt-0.5" />
                    <div className="text-xs text-rose-700 dark:text-rose-300 leading-relaxed font-medium">
                      {gatewayError}
                    </div>
                  </div>
                )}

                {/* Provider Selector Dropdown */}
                <div className="space-y-1.5">
                  <label className="text-xs font-bold text-gray-700 dark:text-gray-300 flex items-center justify-between">
                    <span>Pilih Provider WhatsApp Gateway</span>
                    <span className="text-[11px] font-normal text-gray-400">Multi-provider Engine</span>
                  </label>
                  <div className="relative">
                    <div className="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-brand-500">
                      <Smartphone className="h-4 w-4" />
                    </div>
                    <select
                      value={gatewayForm.provider}
                      onChange={(e) => handleProviderSelect(e.target.value)}
                      className="w-full h-11 pl-10 pr-10 rounded-xl border border-gray-200 bg-gray-50/50 hover:bg-white focus:bg-white dark:border-gray-800 dark:bg-gray-900/80 dark:hover:bg-gray-900 text-xs font-bold text-gray-900 dark:text-white focus:outline-none focus:border-brand-500 transition appearance-none cursor-pointer shadow-2xs"
                    >
                      {Object.entries(providerPresets).map(([key, preset]) => (
                        <option key={key} value={key} className="py-2 text-xs font-medium text-gray-900 dark:text-gray-100 bg-white dark:bg-gray-900">
                          {preset.label}
                        </option>
                      ))}
                    </select>
                    <div className="absolute inset-y-0 right-0 pr-3.5 flex items-center pointer-events-none text-gray-400">
                      <ChevronDown className="h-4 w-4" />
                    </div>
                  </div>
                  <p className="text-[11px] text-gray-500 dark:text-gray-400 mt-1">
                    {providerPresets[gatewayForm.provider]?.guideText || "Pilih layanan WhatsApp gateway yang Anda gunakan."}
                  </p>
                </div>

                {/* API URL Endpoint (Conditional) */}
                {providerPresets[gatewayForm.provider]?.hasUrl && (
                  <div className="space-y-1.5">
                    <label className="text-xs font-bold text-gray-700 dark:text-gray-300 flex items-center justify-between">
                      <span>{providerPresets[gatewayForm.provider]?.urlLabel || "Endpoint API URL"}</span>
                      <span className="text-[11px] font-normal text-gray-400">Target POST Endpoint</span>
                    </label>
                    <div className="relative">
                      <div className="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-gray-400">
                        <Globe className="h-4 w-4" />
                      </div>
                      {providerPresets[gatewayForm.provider]?.urlOptions ? (
                        <select
                          value={gatewayForm.api_url}
                          onChange={(e) => setGatewayForm({ ...gatewayForm, api_url: e.target.value })}
                          className="w-full h-10 pl-9.5 pr-10 rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 text-xs font-mono text-gray-900 dark:text-white focus:outline-none focus:border-brand-500 transition appearance-none cursor-pointer"
                        >
                          {providerPresets[gatewayForm.provider]?.urlOptions?.map((opt) => (
                            <option key={opt.value} value={opt.value} className="py-2 text-xs font-medium">
                              {opt.label}
                            </option>
                          ))}
                        </select>
                      ) : (
                        <input
                          type="text"
                          required
                          placeholder={providerPresets[gatewayForm.provider]?.urlPlaceholder || "https://..."}
                          value={gatewayForm.api_url}
                          onChange={(e) => setGatewayForm({ ...gatewayForm, api_url: e.target.value })}
                          className="w-full h-10 pl-9.5 pr-3 rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 text-xs font-mono text-gray-900 dark:text-white placeholder-gray-400 focus:outline-none focus:border-brand-500 transition"
                        />
                      )}
                    </div>
                    {providerPresets[gatewayForm.provider]?.urlHint && (
                      <p className="text-[11px] text-gray-500 dark:text-gray-400">
                        {providerPresets[gatewayForm.provider]?.urlHint}
                      </p>
                    )}
                  </div>
                )}

                {/* API Token / Key (Conditional) */}
                {providerPresets[gatewayForm.provider]?.hasToken && (
                  <div className="space-y-1.5">
                    <label className="text-xs font-bold text-gray-700 dark:text-gray-300 flex items-center justify-between">
                      <span>{providerPresets[gatewayForm.provider]?.tokenLabel || "API Token / API Key"}</span>
                      <span className="text-[11px] font-normal text-gray-400">Kredensial Otentikasi</span>
                    </label>
                    <div className="relative">
                      <div className="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-gray-400">
                        <Key className="h-4 w-4" />
                      </div>
                      <input
                        type={showToken ? "text" : "password"}
                        required={providerPresets[gatewayForm.provider]?.tokenRequired !== false}
                        placeholder={providerPresets[gatewayForm.provider]?.tokenPlaceholder || "Masukkan Token / API Key"}
                        value={gatewayForm.token}
                        onChange={(e) => setGatewayForm({ ...gatewayForm, token: e.target.value })}
                        className="w-full h-10 pl-9.5 pr-10 rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 text-xs font-mono text-gray-900 dark:text-white placeholder-gray-400 focus:outline-none focus:border-brand-500 transition"
                      />
                      <button
                        type="button"
                        onClick={() => setShowToken(!showToken)}
                        className="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 cursor-pointer"
                      >
                        {showToken ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                      </button>
                    </div>
                    {providerPresets[gatewayForm.provider]?.tokenHint && (
                      <p className="text-[11px] text-gray-500 dark:text-gray-400">
                        {providerPresets[gatewayForm.provider]?.tokenHint}
                      </p>
                    )}
                  </div>
                )}

                {/* Sender Phone / Session ID (Conditional) */}
                {providerPresets[gatewayForm.provider]?.hasSender && (
                  <div className="space-y-1.5">
                    <label className="text-xs font-bold text-gray-700 dark:text-gray-300 flex items-center justify-between">
                      <span>{providerPresets[gatewayForm.provider]?.senderLabel || "Session ID / Nomor Pengirim"}</span>
                      {providerPresets[gatewayForm.provider]?.senderRequired ? (
                        <span className="text-[11px] font-semibold text-amber-600 dark:text-amber-400">Wajib Diisi</span>
                      ) : (
                        <span className="text-[11px] font-normal text-gray-400">Opsional</span>
                      )}
                    </label>
                    <div className="relative">
                      <div className="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-gray-400">
                        <Hash className="h-4 w-4" />
                      </div>
                      <input
                        type="text"
                        required={providerPresets[gatewayForm.provider]?.senderRequired === true}
                        placeholder={providerPresets[gatewayForm.provider]?.senderPlaceholder || "Masukkan Session ID / Nomor"}
                        value={gatewayForm.sender_phone}
                        onChange={(e) => setGatewayForm({ ...gatewayForm, sender_phone: e.target.value })}
                        className="w-full h-10 pl-9.5 pr-3 rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 text-xs font-mono text-gray-900 dark:text-white placeholder-gray-400 focus:outline-none focus:border-brand-500 transition"
                      />
                    </div>
                    {providerPresets[gatewayForm.provider]?.senderHint && (
                      <p className="text-[11px] text-gray-500 dark:text-gray-400">
                        {providerPresets[gatewayForm.provider]?.senderHint}
                      </p>
                    )}
                  </div>
                )}

                {/* Auto Typing Simulation */}
                <div className="p-3 rounded-xl border border-gray-100 bg-gray-50/80 dark:border-gray-800 dark:bg-gray-800/40 flex items-center justify-between gap-3">
                  <div>
                    <h4 className="text-xs font-bold text-gray-900 dark:text-white">Simulasi Mengetik (Human Delay)</h4>
                    <p className="text-[11px] text-gray-500 dark:text-gray-400">Kirim status &apos;typing...&apos; sesaat sebelum pesan terkirim untuk keamanan nomor</p>
                  </div>
                  <Switch
                    checked={gatewayForm.auto_typing}
                    onChange={(val) => setGatewayForm({ ...gatewayForm, auto_typing: val })}
                  />
                </div>

                {/* Modal Footer */}
                <div className="flex items-center justify-end gap-2.5 pt-4 border-t border-gray-100 dark:border-gray-800 shrink-0">
                  <button
                    type="button"
                    onClick={() => setGatewayModalOpen(false)}
                    className="px-4 py-2 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:hover:bg-gray-800 text-xs font-semibold text-gray-700 dark:text-gray-300 transition cursor-pointer"
                  >
                    Batal
                  </button>
                  <button
                    type="submit"
                    disabled={savingGateway}
                    className="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-brand-500 hover:bg-brand-600 active:scale-95 text-xs font-bold text-white shadow-xs transition disabled:opacity-50 cursor-pointer"
                  >
                    {savingGateway ? (
                      <>
                        <RotateCcw className="h-3.5 w-3.5 animate-spin" />
                        <span>Menguji &amp; Menyimpan...</span>
                      </>
                    ) : (
                      <>
                        <Save className="h-3.5 w-3.5" />
                        <span>Simpan &amp; Uji Koneksi</span>
                      </>
                    )}
                  </button>
                </div>
              </form>
            </div>
          </div>
        )}
      </div>
    </AppLayout>
  )
}
