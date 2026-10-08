import { AppLayout } from "@/components/layout/app-layout"
import {
  Send,
  Users,
  Search,
  CheckSquare,
  Square,
  Clock,
  Sparkles,
  Sliders,
  CheckCircle2,
  XCircle,
  AlertCircle,
  Smartphone,
  Save,
  Trash2,
  Plus,
  RefreshCw,
  Key,
  Layers,
  MessageSquare,
  HelpCircle,
  Eye,
  Paperclip,
  RotateCcw,
  Check,
  ChevronLeft,
  ChevronDown,
  Radio,
  ExternalLink,
  SlidersHorizontal,
  Pencil,
  X,
} from "lucide-react"
import { PageProps } from "@/types"
import { adminSidebarItems, adminNavItems, adminBrand } from "@/lib/admin-nav"
import { useForm, router, Link } from "@inertiajs/react"
import { useState, useMemo } from "react"
import { Label } from "@/components/ui/label"
import { cn } from "@/lib/utils"
import { MetricCard } from "@/components/tailadmin/MetricCard"
import { Switch } from "@/components/tailadmin/Switch"
import { Checkbox } from "@/components/tailadmin/Checkbox"

interface Customer {
  id: number
  name: string
  phone: string
  package?: string
  status?: string
}

interface Template {
  id: number
  name: string
  message: string
  type?: string
}

interface NotifSettings {
  reminder_days_before: number
  reminder_time: string
  isolir_days_after: number
  isolir_time: string
  new_invoice_enabled: boolean
  reminder_enabled: boolean
  paid_enabled: boolean
  isolir_enabled: boolean
}

interface WaSettings {
  provider: "fonnte" | "mhwa" | "custom"
  api_url: string
  token: string
  sender_phone: string
  auto_typing: boolean
}

export default function BroadcastPage({
  customers = [],
  templates = [],
  notifSettings,
  waSettings,
  isConfigured = false,
  activeSender = null,
  activeDevice = null,
  flash = {},
}: PageProps<{
  customers: Customer[]
  templates: Template[]
  notifSettings: NotifSettings
  waSettings: WaSettings
  isConfigured: boolean
  activeSender?: string | null
  activeDevice?: any
  flash?: { success?: string; error?: string }
}>) {
  const [activeTab, setActiveTab] = useState<"broadcast" | "wa_gateway" | "templates" | "automated" | "test">("broadcast")
  const [selected, setSelected] = useState<Set<number>>(new Set())
  const [search, setSearch] = useState("")
  const [previewOpen, setPreviewOpen] = useState(false)
  const [templateModalOpen, setTemplateModalOpen] = useState(false)
  const [editingTemplate, setEditingTemplate] = useState<Template | null>(null)
  const [manageTemplateItem, setManageTemplateItem] = useState<Template | null>(null)
  const [copiedVar, setCopiedVar] = useState<string | null>(null)

  // 1. Broadcast Form
  const broadcastForm = useForm({
    target: "all" as "all" | "selected",
    customer_ids: [] as number[],
    template_id: "",
    message: "",
    image_url: "",
  })

  // 2. Notification Automated Settings Form
  const settingsForm = useForm({
    reminder_days_before: notifSettings?.reminder_days_before ?? 3,
    reminder_time: notifSettings?.reminder_time ?? "09:00",
    isolir_days_after: notifSettings?.isolir_days_after ?? 0,
    isolir_time: notifSettings?.isolir_time ?? "00:00",
    new_invoice_enabled: notifSettings?.new_invoice_enabled ?? true,
    reminder_enabled: notifSettings?.reminder_enabled ?? true,
    paid_enabled: notifSettings?.paid_enabled ?? true,
    isolir_enabled: notifSettings?.isolir_enabled ?? true,
  })

  // 3. Custom Template Form
  const templateForm = useForm({
    id: undefined as number | undefined,
    name: "",
    message: "",
  })

  // 4. WhatsApp Gateway API Settings Form
  const waForm = useForm({
    provider: waSettings?.provider || "fonnte",
    api_url: waSettings?.api_url || "https://api.fonnte.com/send",
    token: waSettings?.token || "",
    sender_phone: waSettings?.sender_phone || "",
    auto_typing: waSettings?.auto_typing ?? true,
  })

  // 5. Test WhatsApp Form
  const testWaForm = useForm({
    phone: "",
    message: "Halo! Ini adalah pesan uji coba dari NODERA Billing WhatsApp Gateway.",
  })

  const safeCustomers = Array.isArray(customers) ? customers : []
  const filteredCustomers = useMemo(() => {
    return safeCustomers
      .filter(
        (c) =>
          !search ||
          (c.name ?? "").toLowerCase().includes(search.toLowerCase()) ||
          (c.phone ?? "").includes(search)
      )
      .sort((a, b) => (a.name || "").localeCompare(b.name || "", undefined, { numeric: true, sensitivity: "base" }))
  }, [safeCustomers, search])

  const toggleCustomer = (id: number) => {
    const next = new Set(selected)
    if (next.has(id)) next.delete(id)
    else next.add(id)
    setSelected(next)
    broadcastForm.setData("customer_ids", Array.from(next))
  }

  const toggleSelectAll = () => {
    if (selected.size === filteredCustomers.length && filteredCustomers.length > 0) {
      setSelected(new Set())
      broadcastForm.setData("customer_ids", [])
    } else {
      const all = filteredCustomers.map((c) => c.id)
      setSelected(new Set(all))
      broadcastForm.setData("customer_ids", all)
    }
  }

  const insertVariable = (variable: string) => {
    const current = broadcastForm.data.message
    broadcastForm.setData("message", current + variable)
    setCopiedVar(variable)
    setTimeout(() => setCopiedVar(null), 1500)
  }

  const insertTemplateVariable = (variable: string) => {
    const current = templateForm.data.message
    templateForm.setData("message", current + variable)
  }

  const handleBroadcastSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    if (!broadcastForm.data.message.trim()) {
      alert("Tulis pesan broadcast terlebih dahulu.")
      return
    }
    if (broadcastForm.data.target === "selected" && selected.size === 0) {
      alert("Pilih minimal satu pelanggan penerima pesan.")
      return
    }

    broadcastForm.post("/admin/broadcast/send", {
      preserveScroll: true,
      preserveState: true,
    })
  }

  const openAddTemplate = () => {
    setEditingTemplate(null)
    templateForm.reset()
    templateForm.setData({ id: undefined, name: "", message: "" })
    setTemplateModalOpen(true)
  }

  const openEditTemplate = (t: Template) => {
    setEditingTemplate(t)
    templateForm.setData({ id: t.id, name: t.name, message: t.message })
    setTemplateModalOpen(true)
  }

  const submitTemplate = (e: React.FormEvent) => {
    e.preventDefault()
    templateForm.post("/admin/whatsapp-templates/save", {
      preserveScroll: true,
      preserveState: true,
      onSuccess: () => setTemplateModalOpen(false),
    })
  }

  const deleteTemplate = (id: number, name: string) => {
    if (confirm(`Hapus template "${name}"?`)) {
      router.post(`/admin/whatsapp-templates/delete/${id}`, {}, { preserveScroll: true })
    }
  }

  const resetDefaults = () => {
    if (confirm("Reset semua template ke standar sistem NODERA (Tagihan Baru, Pengingat, Lunas, Isolir)?")) {
      router.post("/admin/whatsapp-templates/reset-defaults", {}, { preserveScroll: true })
    }
  }

  const handleSettingsSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    settingsForm.post("/admin/broadcast/notif-settings", {
      preserveScroll: true,
      preserveState: true,
    })
  }

  const handleWaSettingsSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    waForm.post("/admin/broadcast/wa-settings", {
      preserveScroll: true,
      preserveState: true,
    })
  }

  const handleSendTest = (e: React.FormEvent) => {
    e.preventDefault()
    testWaForm.post("/admin/broadcast/test-send", {
      preserveScroll: true,
      preserveState: true,
    })
  }

  const handleSelectTemplate = (id: string) => {
    broadcastForm.setData("template_id", id)
    const t = templates.find((tpl) => String(tpl.id) === id)
    if (t) {
      broadcastForm.setData("message", t.message)
    }
  }

  const targetCount = broadcastForm.data.target === "all" ? safeCustomers.length : selected.size

  return (
    <AppLayout
      title="Broadcast WhatsApp"
            brand={adminBrand}
      sidebarItems={adminSidebarItems}
      navItems={adminNavItems}
    >
      <div className="space-y-6">
        {/* Flash Message */}
        {flash?.success && (
          <div className="flex items-center gap-2.5 rounded-2xl border border-emerald-200 bg-emerald-50 dark:border-emerald-900/40 dark:bg-emerald-950/20 p-4 text-xs text-emerald-800 dark:text-emerald-300">
            <CheckCircle2 className="h-4 w-4 shrink-0 text-emerald-500" />
            <span>{flash.success}</span>
          </div>
        )}
        {flash?.error && (
          <div className="flex items-center gap-2.5 rounded-2xl border border-rose-200 bg-rose-50 dark:border-rose-900/40 dark:bg-rose-950/20 p-4 text-xs text-rose-800 dark:text-rose-300">
            <AlertCircle className="h-4 w-4 shrink-0 text-rose-500" />
            <span>{flash.error}</span>
          </div>
        )}

        {/* ── TOP 4 METRIC CARDS ── */}
        <div className="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
          <MetricCard
            title="Total Kontak Pelanggan"
            value={`${safeCustomers.length} Kontak`}
            sub="Basis data nomor WhatsApp"
            icon={Users}
            iconBgColor="bg-brand-50 dark:bg-brand-500/10"
            iconColor="text-brand-500 dark:text-brand-400"
            badge={{ text: "Database", color: "info" }}
          />
          <MetricCard
            title="Target Penerima"
            value={`${targetCount} Pelanggan`}
            sub={broadcastForm.data.target === "all" ? "Semua kontak terdaftar" : "Pelanggan terpilih"}
            icon={Send}
            iconBgColor="bg-emerald-50 dark:bg-emerald-500/10"
            iconColor="text-emerald-600 dark:text-emerald-400"
            badge={{ text: broadcastForm.data.target === "all" ? "SEMUA" : "MANUAL", color: "success" }}
          />
          <MetricCard
            title="Status API Gateway"
            value={isConfigured ? "Terhubung" : "Belum Diatur"}
            sub={activeSender ? `Sender: ${activeSender}` : "Fonnte / MHWA"}
            icon={Key}
            iconBgColor={isConfigured ? "bg-emerald-50 dark:bg-emerald-500/10" : "bg-rose-50 dark:bg-rose-500/10"}
            iconColor={isConfigured ? "text-emerald-600 dark:text-emerald-400" : "text-rose-600 dark:text-rose-400"}
            badge={{ text: isConfigured ? "ONLINE" : "OFFLINE", color: isConfigured ? "success" : "error" }}
          />
          <MetricCard
            title="Template WhatsApp"
            value={`${templates.length} Template`}
            sub="Pesan cepat tersimpan"
            icon={MessageSquare}
            iconBgColor="bg-purple-50 dark:bg-purple-500/10"
            iconColor="text-purple-600 dark:text-purple-400"
            badge={{ text: "Siap Pakai", color: "info" }}
          />
        </div>

        {/* ── TABS NAVIGATION BAR ── */}
        <div className="flex items-center gap-2 overflow-x-auto pb-1 text-xs">
          <button
            onClick={() => setActiveTab("broadcast")}
            className={cn(
              "flex items-center gap-2 rounded-xl px-4 py-2.5 text-xs font-bold transition border shrink-0",
              activeTab === "broadcast"
                ? "bg-brand-500 text-white border-brand-600 shadow-xs"
                : "border-gray-200 bg-white hover:bg-gray-50 text-gray-700 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800"
            )}
          >
            <Send className="h-4 w-4" />
            <span>Kirim Broadcast</span>
          </button>

          <button
            onClick={() => setActiveTab("wa_gateway")}
            className={cn(
              "flex items-center gap-2 rounded-xl px-4 py-2.5 text-xs font-bold transition border shrink-0",
              activeTab === "wa_gateway"
                ? "bg-brand-500 text-white border-brand-600 shadow-xs"
                : "border-gray-200 bg-white hover:bg-gray-50 text-gray-700 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800"
            )}
          >
            <Key className="h-4 w-4" />
            <span>Setting API WhatsApp</span>
            {isConfigured ? (
              <span className="h-2 w-2 rounded-full bg-emerald-500 whitespace-nowrap" />
            ) : (
              <span className="h-2 w-2 rounded-full bg-amber-500 whitespace-nowrap" />
            )}
          </button>

          <button
            onClick={() => setActiveTab("templates")}
            className={cn(
              "flex items-center gap-2 rounded-xl px-4 py-2.5 text-xs font-bold transition border shrink-0",
              activeTab === "templates"
                ? "bg-brand-500 text-white border-brand-600 shadow-xs"
                : "border-gray-200 bg-white hover:bg-gray-50 text-gray-700 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800"
            )}
          >
            <MessageSquare className="h-4 w-4" />
            <span>Template Pesan ({templates.length})</span>
          </button>

          <button
            onClick={() => setActiveTab("automated")}
            className={cn(
              "flex items-center gap-2 rounded-xl px-4 py-2.5 text-xs font-bold transition border shrink-0",
              activeTab === "automated"
                ? "bg-brand-500 text-white border-brand-600 shadow-xs"
                : "border-gray-200 bg-white hover:bg-gray-50 text-gray-700 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800"
            )}
          >
            <Sliders className="h-4 w-4" />
            <span>Pengingat Otomatis</span>
          </button>

          <button
            onClick={() => setActiveTab("test")}
            className={cn(
              "flex items-center gap-2 rounded-xl px-4 py-2.5 text-xs font-bold transition border shrink-0",
              activeTab === "test"
                ? "bg-brand-500 text-white border-brand-600 shadow-xs"
                : "border-gray-200 bg-white hover:bg-gray-50 text-gray-700 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800"
            )}
          >
            <Smartphone className="h-4 w-4" />
            <span>Uji Coba Kirim</span>
          </button>
        </div>

        {/* ════════════════════════════════════════════════════════════════════════════ */}
        {/* ── TAB 1: KIRIM BROADCAST WHATSAPP ── */}
        {/* ════════════════════════════════════════════════════════════════════════════ */}
        {activeTab === "broadcast" && (
          <div className="grid gap-5 lg:grid-cols-12">
            {/* Sisi Kiri: Form Composer Pesan */}
            <div className="lg:col-span-5 space-y-4">
              <div className="rounded-2xl border border-gray-200 bg-white shadow-xs dark:border-gray-800 dark:bg-white/[0.03] p-5 sm:p-6 space-y-4">
                <div className="flex items-center justify-between border-b border-gray-100 dark:border-gray-800 pb-3">
                  <div className="flex items-center gap-2.5">
                    <div className="flex h-8 w-8 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400">
                      <MessageSquare className="h-4 w-4" />
                    </div>
                    <div>
                      <h4 className="text-sm font-bold text-gray-900 dark:text-white">Tulis Pesan WhatsApp</h4>
                      <p className="text-xs text-gray-500 dark:text-gray-400">Pesan langsung dikirim via WhatsApp Gateway</p>
                    </div>
                  </div>

                  <span className="text-xs font-bold px-2.5 py-1 rounded-lg bg-gray-100 dark:bg-gray-800 text-brand-600 dark:text-brand-400">
                    {broadcastForm.data.target === "all" ? `Semua (${safeCustomers.length})` : `${selected.size} Dipilih`}
                  </span>
                </div>

                <form onSubmit={handleBroadcastSubmit} className="space-y-4">
                  {/* Pilihan Target */}
                  <div className="space-y-1.5">
                    <Label className="text-xs font-semibold text-gray-700 dark:text-gray-300">Target Penerima</Label>
                    <div className="grid grid-cols-2 gap-2">
                      <button
                        type="button"
                        onClick={() => broadcastForm.setData("target", "all")}
                        className={cn(
                          "flex items-center justify-center gap-2 rounded-xl border p-2.5 text-xs font-bold transition",
                          broadcastForm.data.target === "all"
                            ? "bg-brand-500 border-brand-600 text-white shadow-xs"
                            : "border-gray-200 bg-white hover:bg-gray-50 text-gray-700 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800"
                        )}
                      >
                        <Users className="h-3.5 w-3.5" />
                        <span>Semua Pelanggan ({safeCustomers.length})</span>
                      </button>

                      <button
                        type="button"
                        onClick={() => broadcastForm.setData("target", "selected")}
                        className={cn(
                          "flex items-center justify-center gap-2 rounded-xl border p-2.5 text-xs font-bold transition",
                          broadcastForm.data.target === "selected"
                            ? "bg-brand-500 border-brand-600 text-white shadow-xs"
                            : "border-gray-200 bg-white hover:bg-gray-50 text-gray-700 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800"
                        )}
                      >
                        <CheckSquare className="h-3.5 w-3.5" />
                        <span>Pilih Manual ({selected.size})</span>
                      </button>
                    </div>
                  </div>

                  {/* Load Template Dropdown */}
                  {templates.length > 0 && (
                    <div className="space-y-1.5">
                      <Label className="text-xs font-semibold text-gray-700 dark:text-gray-300">Gunakan Template Pesan</Label>
                      <select
                        value={broadcastForm.data.template_id}
                        onChange={(e) => handleSelectTemplate(e.target.value)}
                        className="h-10 w-full rounded-xl border border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 px-3 text-xs text-gray-900 dark:text-white transition focus:outline-none focus:border-brand-500"
                      >
                        <option value="">-- Pilih Template Cepat --</option>
                        {templates.map((t) => (
                          <option key={t.id} value={t.id}>
                            {t.name}
                          </option>
                        ))}
                      </select>
                    </div>
                  )}

                  {/* Textarea Pesan */}
                  <div className="space-y-1.5">
                    <div className="flex items-center justify-between">
                      <Label className="text-xs font-semibold text-gray-700 dark:text-gray-300">Isi Pesan WhatsApp *</Label>
                      <span className="text-[10px] text-gray-400 font-mono">
                        {broadcastForm.data.message.length} karakter
                      </span>
                    </div>
                    <textarea
                      rows={6}
                      required
                      value={broadcastForm.data.message}
                      onChange={(e) => broadcastForm.setData("message", e.target.value)}
                      placeholder="Tulis format broadcast..."
                      className="w-full rounded-xl border border-gray-200 bg-gray-50/50 hover:bg-white focus:bg-white dark:border-gray-800 dark:bg-gray-900 p-3 font-mono text-xs text-gray-900 dark:text-white leading-relaxed placeholder-gray-400 focus:outline-none focus:border-brand-500 transition resize-y"
                    />
                  </div>

                  {/* Dynamic Variables Shortcut Tags */}
                  <div className="space-y-1.5">
                    <span className="text-[11px] font-semibold text-gray-500 dark:text-gray-400 block">
                      Sisipkan Variabel Pelanggan:
                    </span>
                    <div className="flex flex-wrap gap-1.5">
                      {[
                        { tag: "{nama}", label: "Nama" },
                        { tag: "{tagihan}", label: "Total Tagihan" },
                        { tag: "{periode}", label: "Periode" },
                        { tag: "{jatuh_tempo}", label: "Jatuh Tempo" },
                        { tag: "{paket}", label: "Paket Internet" },
                        { tag: "{link_pembayaran}", label: "Link Bayar" },
                        { tag: "{no_invoice}", label: "No Invoice" },
                      ].map((v) => (
                        <button
                          key={v.tag}
                          type="button"
                          onClick={() => insertVariable(v.tag)}
                          className="inline-flex items-center gap-1 px-2 py-1 rounded-lg border border-gray-200 bg-white hover:border-brand-500 hover:text-brand-600 dark:border-gray-800 dark:bg-gray-800 text-[11px] font-mono text-gray-700 dark:text-gray-300 transition"
                          title={`Sisipkan ${v.label}`}
                        >
                          <span>{v.tag}</span>
                        </button>
                      ))}
                    </div>
                  </div>

                  {/* Attachment URL (Optional) */}
                  <div className="space-y-1.5">
                    <Label className="text-xs font-semibold text-gray-700 dark:text-gray-300">URL Gambar / Poster (Opsional)</Label>
                    <input
                      value={broadcastForm.data.image_url}
                      onChange={(e) => broadcastForm.setData("image_url", e.target.value)}
                      placeholder="https://dgtlnetsolution.com/banner-promo.jpg"
                      className="h-10 w-full rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 px-3 text-xs text-gray-900 dark:text-white placeholder-gray-400 transition focus:outline-none focus:border-brand-500"
                    />
                  </div>

                  {/* Submit Button */}
                  <div className="pt-2">
                    <button
                      type="submit"
                      disabled={broadcastForm.processing}
                      className="w-full h-11 rounded-xl text-xs font-bold gap-2 bg-emerald-600 hover:bg-emerald-700 text-white flex items-center justify-center transition shadow-xs disabled:opacity-50"
                    >
                      <Send className="h-4 w-4" />
                      <span>
                        {broadcastForm.processing
                          ? "Sedang Mengirim..."
                          : `Kirim Broadcast ke ${targetCount} Pelanggan`}
                      </span>
                    </button>
                  </div>
                </form>
              </div>
            </div>

            {/* Sisi Kanan: Daftar Pelanggan & Seleksi */}
            <div className="lg:col-span-7 space-y-4">
              <div className="rounded-2xl border border-gray-200 bg-white shadow-xs dark:border-gray-800 dark:bg-white/[0.03] overflow-hidden">
                {/* Header Table / Search */}
                <div className="p-3 sm:p-4 border-b border-gray-100 dark:border-gray-800 flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-2.5">
                  <div className="relative flex-1">
                    <Search className="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                    <input
                      value={search}
                      onChange={(e) => setSearch(e.target.value)}
                      placeholder="Cari pelanggan berdasarkan nama, nomor HP..."
                      className="h-10 w-full pl-9 pr-8 rounded-xl text-xs font-medium border border-gray-200 bg-gray-50/50 hover:bg-white focus:bg-white dark:border-gray-800 dark:bg-gray-900/50 dark:hover:bg-gray-900 text-gray-900 dark:text-white placeholder-gray-400 focus:outline-none focus:border-brand-500 transition"
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

                  <button
                    type="button"
                    onClick={toggleSelectAll}
                    className="h-10 px-3.5 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:hover:bg-gray-800 text-xs font-bold text-gray-700 dark:text-gray-200 transition shrink-0 flex items-center justify-center gap-1.5 cursor-pointer shadow-2xs"
                  >
                    {selected.size === filteredCustomers.length && filteredCustomers.length > 0 ? (
                      <>
                        <CheckSquare className="h-4 w-4 text-brand-500" />
                        <span>Batal Pilih Semua</span>
                      </>
                    ) : (
                      <>
                        <Square className="h-4 w-4 text-gray-400" />
                        <span>Pilih Semua ({filteredCustomers.length})</span>
                      </>
                    )}
                  </button>
                </div>

                {/* Table List */}
                <div className="max-h-[500px] overflow-y-auto divide-y divide-gray-100 dark:divide-gray-800/60">
                  {filteredCustomers.length === 0 ? (
                    <div className="p-8 text-center text-gray-400">
                      <Users className="mx-auto h-8 w-8 text-gray-300 dark:text-gray-600 mb-2" />
                      <p className="font-semibold text-xs text-gray-700 dark:text-gray-300">Tidak ada pelanggan ditemukan</p>
                    </div>
                  ) : (
                    filteredCustomers.map((c) => {
                      const isChecked = selected.has(c.id)
                      return (
                        <div
                          key={c.id}
                          onClick={() => toggleCustomer(c.id)}
                          className={cn(
                            "flex items-center justify-between p-3.5 hover:bg-gray-50/80 dark:hover:bg-white/[0.02] cursor-pointer transition",
                            isChecked && "bg-brand-50/40 dark:bg-brand-500/5"
                          )}
                        >
                          <div className="flex items-center gap-3 min-w-0">
                            <Checkbox
                              checked={isChecked}
                              onChange={() => toggleCustomer(c.id)}
                            />
                            <div className="min-w-0">
                              <p className="font-bold text-xs text-gray-900 dark:text-white truncate">{c.name}</p>
                              <div className="flex items-center gap-2 text-[11px] text-gray-500 dark:text-gray-400">
                                <span className="font-mono">{c.phone || "-"}</span>
                                {c.package && (
                                  <>
                                    <span>•</span>
                                    <span className="truncate">{c.package}</span>
                                  </>
                                )}
                              </div>
                            </div>
                          </div>

                          {c.status && (
                            <span
                              className={cn(
                                "text-xs font-bold px-2.5 py-1 rounded-lg uppercase tracking-wider shrink-0 text-white shadow-xs whitespace-nowrap",
                                c.status === "active"
                                  ? "bg-emerald-500"
                                  : "bg-rose-500"
                              )}
                            >
                              {c.status}
                            </span>
                          )}
                        </div>
                      )
                    })
                  )}
                </div>
              </div>
            </div>
          </div>
        )}

        {/* ════════════════════════════════════════════════════════════════════════════ */}
        {/* ── TAB 2: SETTING API GATEWAY ── */}
        {/* ════════════════════════════════════════════════════════════════════════════ */}
        {activeTab === "wa_gateway" && (
          <div className="max-w-3xl rounded-2xl border border-gray-200 bg-white shadow-xs dark:border-gray-800 dark:bg-white/[0.03] p-5 sm:p-7 space-y-6">
            <div>
              <h4 className="text-base font-bold text-gray-900 dark:text-white mb-1">
                Pengaturan Token &amp; API WhatsApp Gateway
              </h4>
              <p className="text-xs text-gray-500 dark:text-gray-400">
                Pilih penyedia layanan WhatsApp (Fonnte, MHWA, atau API Kustom sendiri)
              </p>
            </div>

            <form onSubmit={handleWaSettingsSubmit} className="space-y-4">
              <div className="space-y-1.5">
                <Label className="text-xs font-semibold text-gray-700 dark:text-gray-300">Penyedia Gateway (Provider)</Label>
                <select
                  value={waForm.data.provider}
                  onChange={(e) => {
                    const p = e.target.value as any
                    waForm.setData("provider", p)
                    if (p === "fonnte") waForm.setData("api_url", "https://api.fonnte.com/send")
                  }}
                  className="h-10 w-full rounded-xl border border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 px-3 text-xs text-gray-900 dark:text-white transition focus:outline-none focus:border-brand-500"
                >
                  <option value="fonnte">Fonnte (Rekomendasi Indonesia)</option>
                  <option value="mhwa">MHWA Gateway</option>
                  <option value="custom">Custom Webhook / REST API</option>
                </select>
              </div>

              <div className="space-y-1.5">
                <Label className="text-xs font-semibold text-gray-700 dark:text-gray-300">Endpoint API URL</Label>
                <input
                  value={waForm.data.api_url}
                  onChange={(e) => waForm.setData("api_url", e.target.value)}
                  placeholder="https://api.fonnte.com/send"
                  className="h-10 w-full rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 px-3 text-xs font-mono text-gray-900 dark:text-white transition focus:outline-none focus:border-brand-500"
                  required
                />
              </div>

              <div className="space-y-1.5">
                <Label className="text-xs font-semibold text-gray-700 dark:text-gray-300">API Token / API Key</Label>
                <input
                  type="password"
                  value={waForm.data.token}
                  onChange={(e) => waForm.setData("token", e.target.value)}
                  placeholder="Masukkan token API..."
                  className="h-10 w-full rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 px-3 text-xs font-mono text-gray-900 dark:text-white transition focus:outline-none focus:border-brand-500"
                  required
                />
              </div>

              <div className="space-y-1.5">
                <Label className="text-xs font-semibold text-gray-700 dark:text-gray-300">Nomor Pengirim (Sender Phone)</Label>
                <input
                  value={waForm.data.sender_phone}
                  onChange={(e) => waForm.setData("sender_phone", e.target.value)}
                  placeholder="08123456789"
                  className="h-10 w-full rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 px-3 text-xs text-gray-900 dark:text-white transition focus:outline-none focus:border-brand-500"
                />
              </div>

              <div className="pt-3">
                <button
                  type="submit"
                  disabled={waForm.processing}
                  className="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-brand-500 hover:bg-brand-600 text-xs font-bold text-white transition shadow-xs disabled:opacity-50"
                >
                  <Save className="h-4 w-4" />
                  <span>{waForm.processing ? "Menyimpan..." : "Simpan Konfigurasi Gateway"}</span>
                </button>
              </div>
            </form>
          </div>
        )}

        {/* ════════════════════════════════════════════════════════════════════════════ */}
        {/* ── TAB 3: TEMPLATE PESAN ── */}
        {/* ════════════════════════════════════════════════════════════════════════════ */}
        {activeTab === "templates" && (
          <div className="space-y-4">
            <div className="flex items-center justify-between">
              <div>
                <h4 className="text-base font-bold text-gray-900 dark:text-white">Daftar Template Tersimpan</h4>
                <p className="text-xs text-gray-500 dark:text-gray-400">Template pesan standar yang bisa langsung digunakan</p>
              </div>
              <div className="flex items-center gap-2">
                <button
                  type="button"
                  onClick={resetDefaults}
                  className="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl border border-rose-200 bg-rose-50 hover:bg-rose-100 text-rose-700 dark:border-rose-900/40 dark:bg-rose-950/20 dark:text-rose-300 text-xs font-semibold transition"
                >
                  <RotateCcw className="h-3.5 w-3.5" />
                  <span>Reset Standar</span>
                </button>
                <button
                  type="button"
                  onClick={openAddTemplate}
                  className="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-brand-500 hover:bg-brand-600 text-xs font-bold text-white transition shadow-xs"
                >
                  <Plus className="h-4 w-4" />
                  <span>Tambah Template</span>
                </button>
              </div>
            </div>

            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
              {templates.map((t) => (
                <div
                  key={t.id}
                  className="rounded-2xl border border-gray-200 bg-white shadow-xs dark:border-gray-800 dark:bg-white/[0.03] p-4 space-y-3 flex flex-col justify-between"
                >
                  <div className="space-y-2">
                    <div className="flex items-center justify-between gap-2">
                      <h5 className="font-bold text-xs text-gray-900 dark:text-white truncate">{t.name}</h5>
                      <span className="text-[10px] font-bold px-2 py-0.5 rounded-md bg-brand-500 text-white shadow-xs whitespace-nowrap">
                        Template
                      </span>
                    </div>
                    <pre className="whitespace-pre-wrap font-mono text-[11px] text-gray-600 dark:text-gray-300 p-2.5 rounded-xl bg-gray-50 dark:bg-gray-900 border border-gray-100 dark:border-gray-800 line-clamp-6 leading-relaxed">
                      {t.message}
                    </pre>
                  </div>

                  <div className="flex items-center justify-between gap-2 pt-2 border-t border-gray-100 dark:border-gray-800">
                    <button
                      type="button"
                      onClick={() => {
                        broadcastForm.setData("template_id", String(t.id))
                        broadcastForm.setData("message", t.message)
                        setActiveTab("broadcast")
                      }}
                      className="flex-1 py-1.5 rounded-lg bg-brand-500 hover:bg-brand-600 text-white text-xs font-bold transition shadow-xs flex items-center justify-center gap-1.5 cursor-pointer"
                    >
                      <Send className="size-3.5" /> Pakai
                    </button>
                    <button
                      type="button"
                      onClick={() => setManageTemplateItem(t)}
                      className="h-8 px-3 rounded-lg bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200 font-bold text-xs transition cursor-pointer shadow-2xs inline-flex items-center gap-1"
                    >
                      <SlidersHorizontal className="size-3.5" /> Kelola
                    </button>
                  </div>
                </div>
              ))}
            </div>
          </div>
        )}

        {/* ════════════════════════════════════════════════════════════════════════════ */}
        {/* ── TAB 4: PENGINGAT OTOMATIS ── */}
        {/* ════════════════════════════════════════════════════════════════════════════ */}
        {activeTab === "automated" && (
          <div className="max-w-3xl rounded-2xl border border-gray-200 bg-white shadow-xs dark:border-gray-800 dark:bg-white/[0.03] p-5 sm:p-7 space-y-6">
            <div>
              <h4 className="text-base font-bold text-gray-900 dark:text-white mb-1">
                Jadwal Otomatis Notifikasi Sistem
              </h4>
              <p className="text-xs text-gray-500 dark:text-gray-400">
                Atur pengiriman otomatis tagihan baru, peringatan jatuh tempo, konfirmasi lunas, dan isolir
              </p>
            </div>

            <form onSubmit={handleSettingsSubmit} className="space-y-4">
              <div className="grid gap-4 sm:grid-cols-2">
                <div className="space-y-1.5">
                  <Label className="text-xs font-semibold text-gray-700 dark:text-gray-300">Pengingat Tagihan (H-Minus)</Label>
                  <input
                    type="number"
                    min={1}
                    max={14}
                    value={settingsForm.data.reminder_days_before}
                    onChange={(e) => settingsForm.setData("reminder_days_before", parseInt(e.target.value) || 3)}
                    className="h-10 w-full rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 px-3 text-xs text-gray-900 dark:text-white transition focus:outline-none focus:border-brand-500"
                  />
                </div>

                <div className="space-y-1.5">
                  <Label className="text-xs font-semibold text-gray-700 dark:text-gray-300">Jam Pengiriman Pengingat</Label>
                  <input
                    type="time"
                    value={settingsForm.data.reminder_time}
                    onChange={(e) => settingsForm.setData("reminder_time", e.target.value)}
                    className="h-10 w-full rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 px-3 text-xs text-gray-900 dark:text-white transition focus:outline-none focus:border-brand-500"
                  />
                </div>
              </div>

              <div className="pt-3">
                <button
                  type="submit"
                  disabled={settingsForm.processing}
                  className="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-brand-500 hover:bg-brand-600 text-xs font-bold text-white transition shadow-xs disabled:opacity-50"
                >
                  <Save className="h-4 w-4" />
                  <span>{settingsForm.processing ? "Menyimpan..." : "Simpan Jadwal Pengingat"}</span>
                </button>
              </div>
            </form>
          </div>
        )}

        {/* ════════════════════════════════════════════════════════════════════════════ */}
        {/* ── TAB 5: UJI COBA PENGIRIMAN ── */}
        {/* ════════════════════════════════════════════════════════════════════════════ */}
        {activeTab === "test" && (
          <div className="max-w-2xl rounded-2xl border border-gray-200 bg-white shadow-xs dark:border-gray-800 dark:bg-white/[0.03] p-5 sm:p-7 space-y-6">
            <div>
              <h4 className="text-base font-bold text-gray-900 dark:text-white mb-1">
                Uji Coba Pengiriman Pesan WhatsApp
              </h4>
              <p className="text-xs text-gray-500 dark:text-gray-400">
                Kirim pesan uji coba ke nomor Anda untuk memastikan koneksi API WhatsApp aktif
              </p>
            </div>

            <form onSubmit={handleSendTest} className="space-y-4">
              <div className="space-y-1.5">
                <Label className="text-xs font-semibold text-gray-700 dark:text-gray-300">Nomor WhatsApp Tujuan *</Label>
                <input
                  required
                  value={testWaForm.data.phone}
                  onChange={(e) => testWaForm.setData("phone", e.target.value)}
                  placeholder="08123456789 atau 628123456789"
                  className="h-10 w-full rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 px-3 text-xs text-gray-900 dark:text-white transition focus:outline-none focus:border-brand-500"
                />
              </div>

              <div className="space-y-1.5">
                <Label className="text-xs font-semibold text-gray-700 dark:text-gray-300">Isi Pesan Uji Coba</Label>
                <textarea
                  rows={4}
                  required
                  value={testWaForm.data.message}
                  onChange={(e) => testWaForm.setData("message", e.target.value)}
                  className="w-full rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 p-3 text-xs text-gray-900 dark:text-white leading-relaxed transition focus:outline-none focus:border-brand-500"
                />
              </div>

              <div className="pt-2">
                <button
                  type="submit"
                  disabled={testWaForm.processing}
                  className="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-xs font-bold text-white transition shadow-xs disabled:opacity-50"
                >
                  <Send className="h-4 w-4" />
                  <span>{testWaForm.processing ? "Sedang Mengirim..." : "Kirim Pesan Uji Coba"}</span>
                </button>
              </div>
            </form>
          </div>
        )}

        {/* ── MODAL: EDIT / TAMBAH TEMPLATE ── */}
        {templateModalOpen && (
          <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4 animate-in fade-in duration-200">
            <div className="w-full max-w-lg rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 shadow-2xl p-5 sm:p-6 space-y-4 max-h-[92vh] overflow-y-auto">
              <div className="flex items-center justify-between border-b border-gray-100 dark:border-gray-800 pb-3">
                <h4 className="font-bold text-base text-gray-900 dark:text-white">
                  {editingTemplate ? "Edit Template Pesan" : "Tambah Template Baru"}
                </h4>
                <button
                  type="button"
                  onClick={() => setTemplateModalOpen(false)}
                  className="p-1 rounded-lg text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"
                >
                  <XCircle className="h-5 w-5" />
                </button>
              </div>

              <form onSubmit={submitTemplate} className="space-y-4">
                <div className="space-y-1.5">
                  <Label className="text-xs font-semibold text-gray-700 dark:text-gray-300">Nama Template *</Label>
                  <input
                    required
                    value={templateForm.data.name}
                    onChange={(e) => templateForm.setData("name", e.target.value)}
                    placeholder="Contoh: Pengumuman Pemeliharaan Server"
                    className="w-full h-10 rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 px-3.5 text-xs text-gray-900 dark:text-white transition focus:outline-none focus:border-brand-500"
                  />
                </div>

                <div className="space-y-1.5">
                  <div className="flex items-center justify-between">
                    <Label className="text-xs font-semibold text-gray-700 dark:text-gray-300">Isi Format Pesan *</Label>
                  </div>
                  <textarea
                    rows={6}
                    required
                    value={templateForm.data.message}
                    onChange={(e) => templateForm.setData("message", e.target.value)}
                    placeholder="Tulis pesan template..."
                    className="w-full rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 p-3 font-mono text-xs text-gray-900 dark:text-white leading-relaxed transition focus:outline-none focus:border-brand-500"
                  />
                </div>

                {/* Tags Shortcut */}
                <div className="space-y-1.5">
                  <span className="text-[11px] font-semibold text-gray-500 dark:text-gray-400 block">
                    Sisipkan Variabel:
                  </span>
                  <div className="flex flex-wrap gap-1.5">
                    {["{nama}", "{tagihan}", "{periode}", "{jatuh_tempo}", "{paket}", "{link_pembayaran}", "{no_invoice}"].map((v) => (
                      <button
                        key={v}
                        type="button"
                        onClick={() => insertTemplateVariable(v)}
                        className="inline-flex items-center gap-1 px-2 py-1 rounded-lg border border-gray-200 bg-white hover:border-brand-500 hover:text-brand-600 dark:border-gray-800 dark:bg-gray-800 text-[11px] font-mono text-gray-700 dark:text-gray-300 transition"
                      >
                        <span>{v}</span>
                      </button>
                    ))}
                  </div>
                </div>

                <div className="flex items-center justify-end gap-2.5 pt-3 border-t border-gray-100 dark:border-gray-800">
                  <button
                    type="button"
                    onClick={() => setTemplateModalOpen(false)}
                    className="px-4 py-2 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:hover:bg-gray-800 text-xs font-semibold text-gray-700 dark:text-gray-300 transition"
                  >
                    Batal
                  </button>
                  <button
                    type="submit"
                    disabled={templateForm.processing}
                    className="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-brand-500 hover:bg-brand-600 text-xs font-bold text-white shadow-xs transition disabled:opacity-50"
                  >
                    <Save className="h-4 w-4" />
                    <span>{templateForm.processing ? "Menyimpan..." : "Simpan Template"}</span>
                  </button>
                </div>
              </form>
            </div>
          </div>
        )}
        {/* ── MODAL: KELOLA ITEM TEMPLATE ── */}
        {manageTemplateItem && (
          <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-xs p-4 animate-fadeIn">
            <div className="w-full max-w-md rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 shadow-2xl p-6 space-y-4 max-h-[92vh] overflow-y-auto custom-scrollbar">
              <div className="flex items-center justify-between border-b border-gray-100 dark:border-gray-800 pb-3">
                <div className="flex items-center gap-2">
                  <SlidersHorizontal className="size-4 text-brand-500" />
                  <h4 className="font-bold text-base text-gray-900 dark:text-white">
                    Kelola Template {manageTemplateItem.name}
                  </h4>
                </div>
                <button
                  type="button"
                  onClick={() => setManageTemplateItem(null)}
                  className="p-1 rounded-lg text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 cursor-pointer"
                >
                  <X className="h-5 w-5" />
                </button>
              </div>

              <div className="rounded-xl border border-gray-100 bg-gray-50/80 dark:border-gray-800 dark:bg-gray-900/50 p-3.5 space-y-1.5 text-xs">
                <span className="font-bold text-gray-700 dark:text-gray-300 block">Preview Isi Pesan</span>
                <pre className="whitespace-pre-wrap font-mono text-[11px] text-gray-600 dark:text-gray-300 line-clamp-4">
                  {manageTemplateItem.message}
                </pre>
              </div>

              <div className="space-y-2 pt-2 border-t border-gray-100 dark:border-gray-800">
                <button
                  type="button"
                  onClick={() => {
                    const t = manageTemplateItem
                    setManageTemplateItem(null)
                    broadcastForm.setData("template_id", String(t.id))
                    broadcastForm.setData("message", t.message)
                    setActiveTab("broadcast")
                  }}
                  className="w-full h-10 inline-flex items-center justify-center gap-2 rounded-xl bg-brand-500 hover:bg-brand-600 text-white font-bold text-xs shadow-xs transition cursor-pointer"
                >
                  <Send className="size-4" /> Gunakan Template Ini di Broadcast
                </button>

                <button
                  type="button"
                  onClick={() => {
                    const t = manageTemplateItem
                    setManageTemplateItem(null)
                    openEditTemplate(t)
                  }}
                  className="w-full h-10 inline-flex items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:hover:bg-gray-800 text-xs font-semibold text-gray-700 dark:text-gray-200 transition shadow-2xs cursor-pointer"
                >
                  <Pencil className="size-4 text-brand-500" /> Edit Template
                </button>

                <button
                  type="button"
                  onClick={() => {
                    const t = manageTemplateItem
                    setManageTemplateItem(null)
                    deleteTemplate(t.id, t.name)
                  }}
                  className="w-full h-10 inline-flex items-center justify-center gap-2 rounded-xl border border-rose-200 text-rose-600 hover:bg-rose-50 dark:border-rose-900/40 dark:text-rose-400 dark:hover:bg-rose-950/20 text-xs font-semibold transition cursor-pointer"
                >
                  <Trash2 className="size-4" /> Hapus Template
                </button>

                <button
                  type="button"
                  onClick={() => setManageTemplateItem(null)}
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
