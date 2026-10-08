import React, { useState } from "react"
import { AppLayout } from "@/components/layout/app-layout"
import {
  Send,
  Bot,
  CheckCircle2,
  AlertCircle,
  Loader2,
  Save,
  Radio,
  Layers,
  ChevronDown,
  Eye,
  EyeOff,
  Activity,
  Server,
  KeyRound,
  Wifi,
  Network,
  ShieldCheck,
} from "lucide-react"
import { Label } from "@/components/ui/label"
import { Input } from "@/components/ui/input"
import { Switch } from "@/components/tailadmin/Switch"
import { PageProps } from "@/types"
import { adminSidebarItems, adminNavItems, adminBrand } from "@/lib/admin-nav"
import { useForm } from "@inertiajs/react"
import { cn } from "@/lib/utils"
import axios from "axios"
import MetricCard from "@/components/tailadmin/MetricCard"

interface TelegramSettingsProps {
  tenant: {
    id: number
    name: string
    slug: string
  }
  superadminBotUsername?: string
  telegramSettings: {
    telegram_bot_token: string
    telegram_mode: "chat" | "topic"
    telegram_chat_id: string
    telegram_topic_id: string
    telegram_topic_nms: string
    telegram_topic_router?: string
    telegram_topic_pppoe?: string
    telegram_topic_hotspot?: string
    telegram_topic_arp?: string
    telegram_enabled: boolean
    telegram_order_notif: boolean
    telegram_nms_notif: boolean
    telegram_nms_router: boolean
    telegram_nms_pppoe: boolean
    telegram_nms_hotspot: boolean
    telegram_nms_arp: boolean
  }
}

export default function AdminTelegramSettingsPage({
  tenant: _tenant,
  superadminBotUsername,
  telegramSettings,
}: PageProps<TelegramSettingsProps>) {
  const botUsername = superadminBotUsername || "NoderaCenterBot"
  const form = useForm({
    telegram_bot_token: telegramSettings.telegram_bot_token || "",
    telegram_mode: telegramSettings.telegram_mode || "chat",
    telegram_chat_id: telegramSettings.telegram_chat_id || "",
    telegram_topic_id: telegramSettings.telegram_topic_id || "",
    telegram_topic_nms: telegramSettings.telegram_topic_nms || "",
    telegram_topic_router: telegramSettings.telegram_topic_router || "",
    telegram_topic_pppoe: telegramSettings.telegram_topic_pppoe || "",
    telegram_topic_hotspot: telegramSettings.telegram_topic_hotspot || "",
    telegram_topic_arp: telegramSettings.telegram_topic_arp || "",
    telegram_enabled: telegramSettings.telegram_enabled ?? true,
    telegram_order_notif: telegramSettings.telegram_order_notif ?? true,
    telegram_nms_notif: telegramSettings.telegram_nms_notif ?? true,
    telegram_nms_router: telegramSettings.telegram_nms_router ?? true,
    telegram_nms_pppoe: telegramSettings.telegram_nms_pppoe ?? true,
    telegram_nms_hotspot: telegramSettings.telegram_nms_hotspot ?? false,
    telegram_nms_arp: telegramSettings.telegram_nms_arp ?? false,
  })

  const [openSections, setOpenSections] = useState<Record<string, boolean>>({
    guide: false,
    credentials: true,
    switches: true,
    nms_items: false,
    preview: false,
    testing: false,
  })

  const toggleSection = (section: string) => {
    setOpenSections((prev) => ({
      ...prev,
      [section]: !prev[section],
    }))
  }

  const [showToken, setShowToken] = useState(false)
  const [previewTab, setPreviewTab] = useState<"pppoe" | "hotspot" | "router" | "arp" | "order">("pppoe")
  const [testingType, setTestingType] = useState<string | null>(null)
  const [testSuccess, setTestSuccess] = useState<string | null>(null)
  const [testError, setTestError] = useState<string | null>(null)

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    form.post("/admin/telegram/save", {
      preserveScroll: true,
    })
  }

  const handleTestTelegram = async (type: "general" | "order" | "nms" | "pppoe" | "hotspot" | "arp" | "router") => {
    if (!form.data.telegram_bot_token || !form.data.telegram_chat_id) {
      setTestError("Token Bot Telegram dan Chat ID wajib diisi terlebih dahulu untuk melakukan uji coba.")
      setTestSuccess(null)
      return
    }

    setTestingType(type)
    setTestSuccess(null)
    setTestError(null)

    try {
      const res = await axios.post("/admin/telegram/test", {
        telegram_bot_token: form.data.telegram_bot_token,
        telegram_chat_id: form.data.telegram_chat_id,
        telegram_mode: form.data.telegram_mode,
        telegram_topic_id: form.data.telegram_topic_id,
        telegram_topic_nms: form.data.telegram_topic_nms,
        telegram_topic_router: form.data.telegram_topic_router,
        telegram_topic_pppoe: form.data.telegram_topic_pppoe,
        telegram_topic_hotspot: form.data.telegram_topic_hotspot,
        telegram_topic_arp: form.data.telegram_topic_arp,
        test_type: type,
      })

      if (res.data.success) {
        setTestSuccess(res.data.message || "Pesan uji coba berhasil terkirim ke Telegram!")
      } else {
        setTestError(res.data.message || "Gagal mengirim pesan uji coba ke Telegram.")
      }
    } catch (err: any) {
      setTestError(
        err.response?.data?.message ||
        "Gagal menghubungi server Telegram. Pastikan Bot Token valid dan Bot sudah di-start di Telegram."
      )
    } finally {
      setTestingType(null)
    }
  }

  const isConfigured = Boolean(form.data.telegram_bot_token && form.data.telegram_chat_id)

  return (
    <AppLayout
      title="Bot & Notifikasi Telegram"
            brand={adminBrand}
      sidebarItems={adminSidebarItems}
      navItems={adminNavItems}
    >
      <div className="space-y-6">
        {/* Top Row: MetricCards */}
        <div className="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
          <MetricCard
            title="Status Bot"
            value={isConfigured ? "Terkonfigurasi" : "Belum Siap"}
            sub={form.data.telegram_enabled ? "Notifikasi Aktif" : "Nonaktif"}
            icon={<Bot className="h-5 w-5 sm:h-6 sm:w-6 text-brand-500" />}
            iconBgColor="bg-brand-50 dark:bg-brand-500/10"
            iconColor="text-brand-500 dark:text-brand-400"
          />
          <MetricCard
            title="Mode Pengiriman"
            value={form.data.telegram_mode === "topic" ? "Forum Topik" : "Direct Chat"}
            sub={form.data.telegram_chat_id ? "Chat ID Terpasang" : "Chat ID Kosong"}
            icon={<Send className="h-5 w-5 sm:h-6 sm:w-6 text-purple-500" />}
            iconBgColor="bg-purple-50 dark:bg-purple-500/10"
            iconColor="text-purple-500 dark:text-purple-400"
          />
          <MetricCard
            title="Notif Pesanan"
            value={form.data.telegram_order_notif ? "1-Klik ACC Aktif" : "Nonaktif"}
            sub="Pesanan Masuk & Voucher"
            icon={<CheckCircle2 className="h-5 w-5 sm:h-6 sm:w-6 text-emerald-500" />}
            iconBgColor="bg-emerald-50 dark:bg-emerald-500/10"
            iconColor="text-emerald-500 dark:text-emerald-400"
          />
          <MetricCard
            title="Pemantauan NMS"
            value={form.data.telegram_nms_notif ? "Aktif (1 Menit)" : "Nonaktif"}
            sub="Router, PPPoE, Hotspot & ARP"
            icon={<Activity className="h-5 w-5 sm:h-6 sm:w-6 text-amber-500" />}
            iconBgColor="bg-amber-50 dark:bg-amber-500/10"
            iconColor="text-amber-500 dark:text-amber-400"
          />
        </div>

        {/* Top Action Toolbar */}
        <div className="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3 rounded-2xl border border-gray-200 bg-white p-4 shadow-xs dark:border-gray-800 dark:bg-white/[0.03]">
          <div className="flex items-center gap-3">
            <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-500 dark:bg-brand-500/10 dark:text-brand-400">
              <Bot className="h-5 w-5" />
            </div>
            <div>
              <h2 className="text-sm font-bold text-gray-900 dark:text-white">
                Konfigurasi Telegram Bot
              </h2>
              <p className="text-xs text-gray-500 dark:text-gray-400">
                Pusat kontrol integrasi bot, auto-ACC voucher, dan alarm insiden NOC
              </p>
            </div>
          </div>

          <div className="flex items-center gap-2">
            <span
              className={cn(
                "hidden sm:inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold text-white",
                isConfigured
                  ? "bg-emerald-600"
                  : "bg-amber-600"
              )}
            >
              <span className={cn("h-2 w-2 rounded-full bg-white", isConfigured && "animate-pulse")} />
              <span>{isConfigured ? "Terkonfigurasi" : "Belum Dikonfigurasi"}</span>
            </span>

            <button
              type="button"
              onClick={handleSubmit}
              disabled={form.processing}
              className="inline-flex h-9 items-center justify-center gap-1.5 rounded-xl bg-brand-500 hover:bg-brand-600 px-4 text-xs font-bold text-white transition-all shadow-xs active:scale-95 disabled:opacity-50"
            >
              {form.processing ? (
                <>
                  <Loader2 className="h-4 w-4 animate-spin" />
                  <span>Menyimpan...</span>
                </>
              ) : (
                <>
                  <Save className="h-4 w-4" />
                  <span>Simpan Pengaturan</span>
                </>
              )}
            </button>
          </div>
        </div>

        {/* Content Body */}
        <form onSubmit={handleSubmit} className="space-y-4">
          {/* ALERT NOTIFIKASI TEST */}
          {testSuccess && (
            <div className="flex items-start gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-xs text-emerald-800 dark:border-emerald-900/50 dark:bg-emerald-950/40 dark:text-emerald-200 animate-in fade-in">
              <CheckCircle2 className="h-5 w-5 text-emerald-600 dark:text-emerald-400 shrink-0 mt-0.5" />
              <div className="flex-1 min-w-0">
                <strong className="font-bold block mb-0.5">Uji Coba Berhasil!</strong>
                <span>{testSuccess}</span>
              </div>
            </div>
          )}

          {testError && (
            <div className="flex items-start gap-3 rounded-2xl border border-rose-200 bg-rose-50 p-4 text-xs text-rose-800 dark:border-rose-900/50 dark:bg-rose-950/40 dark:text-rose-200 animate-in fade-in">
              <AlertCircle className="h-5 w-5 text-rose-600 dark:text-rose-400 shrink-0 mt-0.5" />
              <div className="flex-1 min-w-0">
                <strong className="font-bold block mb-0.5">Uji Coba Gagal</strong>
                <span>{testError}</span>
              </div>
            </div>
          )}

          {/* CARD 1: PANDUAN CEPAT BOT */}
          <div className="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xs dark:border-gray-800 dark:bg-white/[0.03]">
            <button
              type="button"
              onClick={() => toggleSection("guide")}
              className="w-full p-4 sm:p-5 flex items-center justify-between gap-3 text-left hover:bg-gray-50/50 dark:hover:bg-white/[0.02] transition-colors"
            >
              <div className="flex items-center gap-3 min-w-0">
                <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-500 dark:bg-brand-500/10 dark:text-brand-400">
                  <Bot className="h-4 w-4" />
                </div>
                <div className="min-w-0">
                  <h3 className="text-sm font-bold text-gray-900 dark:text-white truncate">
                    Panduan Singkat Integrasi Bot
                  </h3>
                  <p className="text-xs text-gray-500 dark:text-gray-400 truncate">
                    Cara membuat bot Telegram di @BotFather dan mencari Chat ID / Group ID.
                  </p>
                </div>
              </div>
              <div className="shrink-0 p-1">
                <ChevronDown
                  className={cn(
                    "h-4 w-4 text-gray-400 transition-transform duration-200",
                    openSections.guide && "rotate-180 text-gray-700 dark:text-gray-200"
                  )}
                />
              </div>
            </button>

            {openSections.guide && (
              <div className="px-4 pb-5 sm:px-5 border-t border-gray-100 dark:border-gray-800 pt-4 space-y-3">
                <div className="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs text-gray-700 dark:text-gray-300">
                  <div className="rounded-xl border border-gray-200 bg-gray-50/50 dark:border-gray-800 dark:bg-gray-900/50 p-3.5 space-y-1.5">
                    <span className="flex h-5 w-5 items-center justify-center rounded-full bg-brand-500 text-white font-bold text-[10px] whitespace-nowrap">1</span>
                    <strong className="text-gray-900 dark:text-white block">Buat Bot Telegram</strong>
                    <p className="text-[11px] text-gray-500 dark:text-gray-400 leading-relaxed">
                      Buka <code>@BotFather</code> di Telegram, ketik <code>/newbot</code>, beri nama bot Anda, dan salin <b>HTTP API Token</b> ke form kredensial.
                    </p>
                  </div>

                  <div className="rounded-xl border border-gray-200 bg-gray-50/50 dark:border-gray-800 dark:bg-gray-900/50 p-3.5 space-y-1.5">
                    <span className="flex h-5 w-5 items-center justify-center rounded-full bg-brand-500 text-white font-bold text-[10px] whitespace-nowrap">2</span>
                    <strong className="text-gray-900 dark:text-white block">Cek Chat ID Pribadi</strong>
                    <p className="text-[11px] text-gray-500 dark:text-gray-400 leading-relaxed">
                      Buka bot resmi kami di Telegram <a href={`https://t.me/${botUsername}`} target="_blank" rel="noopener noreferrer" className="text-brand-500 font-bold hover:underline">@{botUsername}</a> lalu kirim <code>/start</code> untuk melihat nomor Chat ID Anda.
                    </p>
                  </div>

                  <div className="rounded-xl border border-gray-200 bg-gray-50/50 dark:border-gray-800 dark:bg-gray-900/50 p-3.5 space-y-1.5">
                    <span className="flex h-5 w-5 items-center justify-center rounded-full bg-brand-500 text-white font-bold text-[10px] whitespace-nowrap">3</span>
                    <strong className="text-gray-900 dark:text-white block">Cek Group ID &amp; Topik</strong>
                    <p className="text-[11px] text-gray-500 dark:text-gray-400 leading-relaxed">
                      Masukkan <a href={`https://t.me/${botUsername}`} target="_blank" rel="noopener noreferrer" className="text-brand-500 font-bold hover:underline">@{botUsername}</a> ke grup Anda, lalu ketik perintah <code>/getid</code> di grup untuk mendapatkan Group ID &amp; Topic ID.
                    </p>
                  </div>
                </div>
              </div>
            )}
          </div>

          {/* CARD 2: KREDENSIAL BOT TELEGRAM & TOPIK */}
          <div className="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xs dark:border-gray-800 dark:bg-white/[0.03]">
            <button
              type="button"
              onClick={() => toggleSection("credentials")}
              className="w-full p-4 sm:p-5 flex items-center justify-between gap-3 text-left hover:bg-gray-50/50 dark:hover:bg-white/[0.02] transition-colors"
            >
              <div className="flex items-center gap-3 min-w-0">
                <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-500 dark:bg-brand-500/10 dark:text-brand-400">
                  <KeyRound className="h-4 w-4" />
                </div>
                <div className="min-w-0">
                  <h3 className="text-sm font-bold text-gray-900 dark:text-white truncate">
                    Kredensial &amp; Target Topik Notifikasi
                  </h3>
                  <p className="text-xs text-gray-500 dark:text-gray-400 truncate">
                    Token bot @BotFather, Chat ID / Group ID, dan alokasi ID topik forum.
                  </p>
                </div>
              </div>
              <div className="shrink-0 p-1 flex items-center gap-2">
                <span
                  className={cn(
                    "text-[10px] font-bold px-2 py-0.5 rounded-md text-white uppercase tracking-wider",
                    isConfigured ? "bg-emerald-600" : "bg-amber-600"
                  )}
                >
                  {isConfigured ? "Tersimpan" : "Kosong"}
                </span>
                <ChevronDown
                  className={cn(
                    "h-4 w-4 text-gray-400 transition-transform duration-200",
                    openSections.credentials && "rotate-180 text-gray-700 dark:text-gray-200"
                  )}
                />
              </div>
            </button>

            {openSections.credentials && (
              <div className="px-4 pb-5 sm:px-5 border-t border-gray-100 dark:border-gray-800 pt-4 space-y-4">
                <div className="rounded-xl border border-gray-200 bg-gray-50/50 dark:border-gray-800 dark:bg-gray-900/50 p-4 space-y-4">
                  {/* Token Bot */}
                  <div className="space-y-1.5">
                    <Label className="text-xs font-semibold text-gray-700 dark:text-gray-300 flex items-center justify-between">
                      <span>Token Bot Telegram *</span>
                      <button
                        type="button"
                        onClick={() => setShowToken(!showToken)}
                        className="text-[11px] text-brand-500 hover:underline inline-flex items-center gap-1 font-semibold"
                      >
                        {showToken ? <EyeOff className="h-3.5 w-3.5" /> : <Eye className="h-3.5 w-3.5" />}
                        <span>{showToken ? "Sembunyikan" : "Tampilkan"}</span>
                      </button>
                    </Label>
                    <Input
                      type={showToken ? "text" : "password"}
                      value={form.data.telegram_bot_token}
                      onChange={(e) => form.setData("telegram_bot_token", e.target.value)}
                      placeholder="cth: 123456789:ABCdefGhIJKlmNoPQRsTUVwxyZ (Wajib dari @BotFather)"
                      className="h-10 rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-950 px-3 font-mono text-xs text-gray-900 dark:text-white focus:ring-2 focus:ring-brand-500"
                    />
                    <p className="text-[10px] text-gray-400">
                      Token bot Telegram unik yang didapatkan dari @BotFather.
                    </p>
                  </div>

                  {/* Mode Target */}
                  <div className="space-y-1.5">
                    <Label className="text-xs font-semibold text-gray-700 dark:text-gray-300">Mode Target Pengiriman *</Label>
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                      <button
                        type="button"
                        onClick={() => form.setData("telegram_mode", "chat")}
                        className={cn(
                          "flex items-start gap-3 p-3 rounded-xl border text-left transition-all",
                          form.data.telegram_mode === "chat"
                            ? "border-brand-500 bg-brand-50 dark:bg-brand-500/10 text-gray-900 dark:text-white"
                            : "border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-950 text-gray-500 hover:text-gray-900 dark:hover:text-white"
                        )}
                      >
                        <Radio className={cn("h-4 w-4 mt-0.5 shrink-0", form.data.telegram_mode === "chat" ? "text-brand-500" : "text-gray-400")} />
                        <div>
                          <strong className="text-xs block">Chat Langsung / Group Standar</strong>
                          <span className="text-[10px] text-gray-500 dark:text-gray-400 leading-tight block mt-0.5">
                            Direct Message ke akun admin atau grup biasa tanpa fitur topik thread.
                          </span>
                        </div>
                      </button>

                      <button
                        type="button"
                        onClick={() => form.setData("telegram_mode", "topic")}
                        className={cn(
                          "flex items-start gap-3 p-3 rounded-xl border text-left transition-all",
                          form.data.telegram_mode === "topic"
                            ? "border-purple-500 bg-purple-50 dark:bg-purple-500/10 text-gray-900 dark:text-white"
                            : "border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-950 text-gray-500 hover:text-gray-900 dark:hover:text-white"
                        )}
                      >
                        <Layers className={cn("h-4 w-4 mt-0.5 shrink-0", form.data.telegram_mode === "topic" ? "text-purple-500" : "text-gray-400")} />
                        <div>
                          <strong className="text-xs block">Supergroup Forum Bertopik</strong>
                          <span className="text-[10px] text-gray-500 dark:text-gray-400 leading-tight block mt-0.5">
                            Pisahkan topik Pesanan, NMS Utama, PPPoE, Hotspot, dan ARP dalam satu grup.
                          </span>
                        </div>
                      </button>
                    </div>
                  </div>

                  {/* Chat ID & Topics */}
                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-1">
                    <div className="space-y-1.5">
                      <Label className="text-xs font-semibold text-gray-700 dark:text-gray-300">
                        {form.data.telegram_mode === "chat" ? "Telegram Chat ID / Group ID *" : "Telegram Group ID (diawali -100) *"}
                      </Label>
                      <Input
                        value={form.data.telegram_chat_id}
                        onChange={(e) => form.setData("telegram_chat_id", e.target.value)}
                        placeholder={form.data.telegram_mode === "chat" ? "cth: 123456789 atau -100123456789" : "cth: -1001234567890"}
                        className="h-10 rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-950 px-3 font-mono text-xs text-gray-900 dark:text-white focus:ring-2 focus:ring-brand-500"
                      />
                      <p className="text-[10px] text-gray-400">
                        Kirim <code>/start</code> ke <a href={`https://t.me/${botUsername}`} target="_blank" rel="noopener noreferrer" className="text-brand-500 font-semibold hover:underline">@{botUsername}</a> untuk ID pribadi, atau kirim <code>/getid</code> di dalam grup.
                      </p>
                    </div>

                    {form.data.telegram_mode === "topic" ? (
                      <div className="space-y-1.5">
                        <Label className="text-xs font-semibold text-gray-700 dark:text-gray-300">Topic ID Pesanan &amp; ACC Voucher</Label>
                        <Input
                          value={form.data.telegram_topic_id}
                          onChange={(e) => form.setData("telegram_topic_id", e.target.value)}
                          placeholder="cth: 42 (ID Thread Topik Pesanan)"
                          className="h-10 rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-950 px-3 font-mono text-xs text-gray-900 dark:text-white focus:ring-2 focus:ring-brand-500"
                        />
                        <p className="text-[10px] text-gray-400">
                          ID thread di grup forum untuk notifikasi pesanan masuk &amp; ACC 1-klik.
                        </p>
                      </div>
                    ) : null}
                  </div>

                  {form.data.telegram_mode === "topic" && (
                    <div className="space-y-4 pt-2 border-t border-gray-200 dark:border-gray-800">
                      <div className="space-y-1.5">
                        <Label className="text-xs font-semibold text-gray-700 dark:text-gray-300">Topic ID NMS Utama (Default)</Label>
                        <Input
                          value={form.data.telegram_topic_nms}
                          onChange={(e) => form.setData("telegram_topic_nms", e.target.value)}
                          placeholder="cth: 88 (ID Thread Topik NMS Utama)"
                          className="h-10 rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-950 px-3 font-mono text-xs text-gray-900 dark:text-white focus:ring-2 focus:ring-brand-500"
                        />
                        <p className="text-[10px] text-gray-400">
                          Topik default untuk semua notifikasi jaringan jika topik khusus di bawah tidak diisi.
                        </p>
                      </div>

                      {/* Topik Khusus per Layanan */}
                      <div className="rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-950 p-4 space-y-3">
                        <div className="flex items-center gap-2">
                          <Layers className="h-4 w-4 text-purple-500" />
                          <h4 className="text-xs font-bold text-gray-900 dark:text-white">Topik Khusus per Layanan (Opsional)</h4>
                        </div>
                        <p className="text-[11px] text-gray-500 dark:text-gray-400">
                          Isi Topic ID di bawah jika ingin memisahkan notifikasi per kategori ke thread berbeda. Jika dikosongkan, notifikasi otomatis masuk ke <b>Topic ID NMS Utama</b>.
                        </p>

                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-1">
                          <div className="space-y-1.5">
                            <Label className="text-[11px] font-semibold text-cyan-600 dark:text-cyan-400 flex items-center gap-1.5">
                              <Network className="h-3.5 w-3.5" />
                              <span>Topic ID Khusus PPPoE</span>
                            </Label>
                            <Input
                              value={form.data.telegram_topic_pppoe}
                              onChange={(e) => form.setData("telegram_topic_pppoe", e.target.value)}
                              placeholder="cth: 101 (Opsional)"
                              className="h-9 rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-50/50 dark:bg-gray-900 px-3 font-mono text-xs text-gray-900 dark:text-white"
                            />
                          </div>

                          <div className="space-y-1.5">
                            <Label className="text-[11px] font-semibold text-amber-600 dark:text-amber-400 flex items-center gap-1.5">
                              <Wifi className="h-3.5 w-3.5" />
                              <span>Topic ID Khusus Hotspot</span>
                            </Label>
                            <Input
                              value={form.data.telegram_topic_hotspot}
                              onChange={(e) => form.setData("telegram_topic_hotspot", e.target.value)}
                              placeholder="cth: 102 (Opsional)"
                              className="h-9 rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-50/50 dark:bg-gray-900 px-3 font-mono text-xs text-gray-900 dark:text-white"
                            />
                          </div>

                          <div className="space-y-1.5">
                            <Label className="text-[11px] font-semibold text-blue-600 dark:text-blue-400 flex items-center gap-1.5">
                              <Server className="h-3.5 w-3.5" />
                              <span>Topic ID Khusus Router NOC</span>
                            </Label>
                            <Input
                              value={form.data.telegram_topic_router}
                              onChange={(e) => form.setData("telegram_topic_router", e.target.value)}
                              placeholder="cth: 103 (Opsional)"
                              className="h-9 rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-50/50 dark:bg-gray-900 px-3 font-mono text-xs text-gray-900 dark:text-white"
                            />
                          </div>

                          <div className="space-y-1.5">
                            <Label className="text-[11px] font-semibold text-purple-600 dark:text-purple-400 flex items-center gap-1.5">
                              <Activity className="h-3.5 w-3.5" />
                              <span>Topic ID Khusus ARP Host</span>
                            </Label>
                            <Input
                              value={form.data.telegram_topic_arp}
                              onChange={(e) => form.setData("telegram_topic_arp", e.target.value)}
                              placeholder="cth: 104 (Opsional)"
                              className="h-9 rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-50/50 dark:bg-gray-900 px-3 font-mono text-xs text-gray-900 dark:text-white"
                            />
                          </div>
                        </div>
                      </div>
                    </div>
                  )}
                </div>
              </div>
            )}
          </div>

          {/* CARD 3: SAKLAR FITUR & MODUL NOTIFIKASI */}
          <div className="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xs dark:border-gray-800 dark:bg-white/[0.03]">
            <button
              type="button"
              onClick={() => toggleSection("switches")}
              className="w-full p-4 sm:p-5 flex items-center justify-between gap-3 text-left hover:bg-gray-50/50 dark:hover:bg-white/[0.02] transition-colors"
            >
              <div className="flex items-center gap-3 min-w-0">
                <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-purple-50 text-purple-600 dark:bg-purple-500/10 dark:text-purple-400">
                  <Send className="h-4 w-4" />
                </div>
                <div className="min-w-0">
                  <h3 className="text-sm font-bold text-gray-900 dark:text-white truncate">
                    Saklar Fitur &amp; Otomatisasi
                  </h3>
                  <p className="text-xs text-gray-500 dark:text-gray-400 truncate">
                    Master switch Telegram, notifikasi pesanan toko &amp; ACC 1-klik, serta NMS master.
                  </p>
                </div>
              </div>
              <div className="shrink-0 p-1 flex items-center gap-2">
                <span
                  className={cn(
                    "text-[10px] font-bold px-2 py-0.5 rounded-md text-white uppercase tracking-wider",
                    form.data.telegram_enabled ? "bg-emerald-600" : "bg-gray-500"
                  )}
                >
                  {form.data.telegram_enabled ? "Aktif" : "Nonaktif"}
                </span>
                <ChevronDown
                  className={cn(
                    "h-4 w-4 text-gray-400 transition-transform duration-200",
                    openSections.switches && "rotate-180 text-gray-700 dark:text-gray-200"
                  )}
                />
              </div>
            </button>

            {openSections.switches && (
              <div className="px-4 pb-5 sm:px-5 border-t border-gray-100 dark:border-gray-800 pt-4 space-y-4">
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                  {/* Master Telegram Switch */}
                  <div className="flex items-center justify-between p-4 rounded-xl border border-gray-200 bg-gray-50/50 dark:border-gray-800 dark:bg-gray-900/50">
                    <div className="space-y-0.5 pr-3">
                      <div className="flex items-center gap-2">
                        <Send className="h-4 w-4 text-brand-500" />
                        <p className="text-xs font-bold text-gray-900 dark:text-white">Master Switch Telegram</p>
                      </div>
                      <p className="text-[10px] text-gray-500 dark:text-gray-400">
                        Nyalakan seluruh pengiriman notifikasi ke bot Telegram.
                      </p>
                    </div>
                    <Switch
                      checked={form.data.telegram_enabled}
                      onChange={(v) => form.setData("telegram_enabled", v)}
                    />
                  </div>

                  {/* Order & ACC Voucher Switch */}
                  <div className="flex items-center justify-between p-4 rounded-xl border border-gray-200 bg-gray-50/50 dark:border-gray-800 dark:bg-gray-900/50">
                    <div className="space-y-0.5 pr-3">
                      <div className="flex items-center gap-2">
                        <Bot className="h-4 w-4 text-emerald-500" />
                        <p className="text-xs font-bold text-gray-900 dark:text-white">Pesanan &amp; ACC 1-Klik</p>
                      </div>
                      <p className="text-[10px] text-gray-500 dark:text-gray-400">
                        Kirim pesanan toko &amp; tombol ACC/Tolak voucher ke Telegram.
                      </p>
                    </div>
                    <Switch
                      checked={form.data.telegram_order_notif}
                      onChange={(v) => form.setData("telegram_order_notif", v)}
                      disabled={!form.data.telegram_enabled}
                    />
                  </div>
                </div>

                {/* NMS Master Switch */}
                <div className="flex items-center justify-between p-4 rounded-xl border border-gray-200 bg-gray-50/50 dark:border-gray-800 dark:bg-gray-900/50">
                  <div className="space-y-0.5 pr-3">
                    <div className="flex items-center gap-2">
                      <Activity className="h-4 w-4 text-brand-500" />
                      <p className="text-xs font-bold text-gray-900 dark:text-white">Network Monitoring System (NMS)</p>
                    </div>
                    <p className="text-[10px] text-gray-500 dark:text-gray-400">
                      Pemantauan berkala setiap 1 menit. Alert dikirim hanya saat status berubah (anti-spam).
                    </p>
                  </div>
                  <Switch
                    checked={form.data.telegram_nms_notif}
                    onChange={(v) => form.setData("telegram_nms_notif", v)}
                    disabled={!form.data.telegram_enabled}
                  />
                </div>
              </div>
            )}
          </div>

          {/* CARD 4: ITEM PEMANTAUAN JARINGAN NMS */}
          <div className="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xs dark:border-gray-800 dark:bg-white/[0.03]">
            <button
              type="button"
              onClick={() => toggleSection("nms_items")}
              className="w-full p-4 sm:p-5 flex items-center justify-between gap-3 text-left hover:bg-gray-50/50 dark:hover:bg-white/[0.02] transition-colors"
            >
              <div className="flex items-center gap-3 min-w-0">
                <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400">
                  <ShieldCheck className="h-4 w-4" />
                </div>
                <div className="min-w-0">
                  <h3 className="text-sm font-bold text-gray-900 dark:text-white truncate">
                    Item Pemantauan NMS (1-Menit)
                  </h3>
                  <p className="text-xs text-gray-500 dark:text-gray-400 truncate">
                    Pilih modul pemantauan: Router, PPPoE, Hotspot, dan ARP Offline/Online.
                  </p>
                </div>
              </div>
              <div className="shrink-0 p-1 flex items-center gap-2">
                <span
                  className={cn(
                    "text-[10px] font-bold px-2 py-0.5 rounded-md text-white uppercase tracking-wider",
                    form.data.telegram_nms_notif && form.data.telegram_enabled
                      ? "bg-emerald-600"
                      : "bg-gray-500"
                  )}
                >
                  {form.data.telegram_nms_notif && form.data.telegram_enabled ? "NMS Siap" : "NMS Nonaktif"}
                </span>
                <ChevronDown
                  className={cn(
                    "h-4 w-4 text-gray-400 transition-transform duration-200",
                    openSections.nms_items && "rotate-180 text-gray-700 dark:text-gray-200"
                  )}
                />
              </div>
            </button>

            {openSections.nms_items && (
              <div className="px-4 pb-5 sm:px-5 border-t border-gray-100 dark:border-gray-800 pt-4 space-y-4">
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                  {/* Router Offline/Online */}
                  <div className="flex items-center justify-between p-3.5 rounded-xl border border-gray-200 bg-gray-50/50 dark:border-gray-800 dark:bg-gray-900/50">
                    <div className="space-y-0.5 pr-3">
                      <div className="flex items-center gap-2">
                        <Server className="h-3.5 w-3.5 text-blue-500" />
                        <p className="text-xs font-semibold text-gray-900 dark:text-white">Router MikroTik</p>
                      </div>
                      <p className="text-[10px] text-gray-500 dark:text-gray-400">Alert saat router mati / offline &amp; pulih online</p>
                    </div>
                    <Switch
                      checked={form.data.telegram_nms_router}
                      onChange={(v) => form.setData("telegram_nms_router", v)}
                    />
                  </div>

                  {/* PPPoE Offline/Online */}
                  <div className="flex items-center justify-between p-3.5 rounded-xl border border-gray-200 bg-gray-50/50 dark:border-gray-800 dark:bg-gray-900/50">
                    <div className="space-y-0.5 pr-3">
                      <div className="flex items-center gap-2">
                        <Network className="h-3.5 w-3.5 text-cyan-500" />
                        <p className="text-xs font-semibold text-gray-900 dark:text-white">PPPoE Secrets Client</p>
                      </div>
                      <p className="text-[10px] text-gray-500 dark:text-gray-400">Alert saat sesi PPPoE terputus atau terhubung</p>
                    </div>
                    <Switch
                      checked={form.data.telegram_nms_pppoe}
                      onChange={(v) => form.setData("telegram_nms_pppoe", v)}
                    />
                  </div>

                  {/* Hotspot Offline/Online */}
                  <div className="flex items-center justify-between p-3.5 rounded-xl border border-gray-200 bg-gray-50/50 dark:border-gray-800 dark:bg-gray-900/50">
                    <div className="space-y-0.5 pr-3">
                      <div className="flex items-center gap-2">
                        <Wifi className="h-3.5 w-3.5 text-amber-500" />
                        <p className="text-xs font-semibold text-gray-900 dark:text-white">Pengguna Hotspot Voucher</p>
                      </div>
                      <p className="text-[10px] text-gray-500 dark:text-gray-400">Alert saat voucher login atau logout</p>
                    </div>
                    <Switch
                      checked={form.data.telegram_nms_hotspot}
                      onChange={(v) => form.setData("telegram_nms_hotspot", v)}
                    />
                  </div>

                  {/* ARP Offline/Online */}
                  <div className="flex items-center justify-between p-3.5 rounded-xl border border-gray-200 bg-gray-50/50 dark:border-gray-800 dark:bg-gray-900/50">
                    <div className="space-y-0.5 pr-3">
                      <div className="flex items-center gap-2">
                        <Activity className="h-3.5 w-3.5 text-purple-500" />
                        <p className="text-xs font-semibold text-gray-900 dark:text-white">Tabel Host ARP / IP Binding</p>
                      </div>
                      <p className="text-[10px] text-gray-500 dark:text-gray-400">Alert saat host IP tidak terdeteksi di router</p>
                    </div>
                    <Switch
                      checked={form.data.telegram_nms_arp}
                      onChange={(v) => form.setData("telegram_nms_arp", v)}
                    />
                  </div>
                </div>
              </div>
            )}
          </div>

          {/* CARD 5: FORMAT STANDAR NOTIFIKASI & PRATINJAU */}
          <div className="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xs dark:border-gray-800 dark:bg-white/[0.03]">
            <button
              type="button"
              onClick={() => toggleSection("preview")}
              className="w-full p-4 sm:p-5 flex items-center justify-between gap-3 text-left hover:bg-gray-50/50 dark:hover:bg-white/[0.02] transition-colors"
            >
              <div className="flex items-center gap-3 min-w-0">
                <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-500 dark:bg-brand-500/10 dark:text-brand-400">
                  <Send className="h-3.5 w-3.5" />
                </div>
                <div className="min-w-0">
                  <h3 className="text-sm font-bold text-gray-900 dark:text-white truncate">
                    Format Standar Notifikasi Telegram
                  </h3>
                  <p className="text-xs text-gray-500 dark:text-gray-400 truncate">
                    Pratinjau tampilan pesan alert NMS &amp; notifikasi pesanan di aplikasi Telegram.
                  </p>
                </div>
              </div>
              <div className="shrink-0 p-1">
                <ChevronDown
                  className={cn(
                    "h-4 w-4 text-gray-400 transition-transform duration-200",
                    openSections.preview && "rotate-180 text-gray-700 dark:text-gray-200"
                  )}
                />
              </div>
            </button>

            {openSections.preview && (
              <div className="px-4 pb-5 sm:px-5 border-t border-gray-100 dark:border-gray-800 pt-4 space-y-3">
                <div className="flex items-center justify-between">
                  <p className="text-xs font-semibold text-gray-700 dark:text-gray-300">Pilih Kategori Pratinjau:</p>
                  <div className="flex items-center gap-1 bg-gray-100 dark:bg-gray-900 p-1 rounded-xl border border-gray-200 dark:border-gray-800">
                    <button
                      type="button"
                      onClick={() => setPreviewTab("pppoe")}
                      className={cn(
                        "px-2.5 py-1 rounded-lg text-[11px] font-bold transition-colors",
                        previewTab === "pppoe" ? "bg-brand-500 text-white" : "text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white"
                      )}
                    >
                      PPPoE
                    </button>
                    <button
                      type="button"
                      onClick={() => setPreviewTab("hotspot")}
                      className={cn(
                        "px-2.5 py-1 rounded-lg text-[11px] font-bold transition-colors",
                        previewTab === "hotspot" ? "bg-brand-500 text-white" : "text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white"
                      )}
                    >
                      Hotspot
                    </button>
                    <button
                      type="button"
                      onClick={() => setPreviewTab("router")}
                      className={cn(
                        "px-2.5 py-1 rounded-lg text-[11px] font-bold transition-colors",
                        previewTab === "router" ? "bg-brand-500 text-white" : "text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white"
                      )}
                    >
                      Router
                    </button>
                    <button
                      type="button"
                      onClick={() => setPreviewTab("arp")}
                      className={cn(
                        "px-2.5 py-1 rounded-lg text-[11px] font-bold transition-colors",
                        previewTab === "arp" ? "bg-brand-500 text-white" : "text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white"
                      )}
                    >
                      ARP
                    </button>
                    <button
                      type="button"
                      onClick={() => setPreviewTab("order")}
                      className={cn(
                        "px-2.5 py-1 rounded-lg text-[11px] font-bold transition-colors",
                        previewTab === "order" ? "bg-brand-500 text-white" : "text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white"
                      )}
                    >
                      Pesanan
                    </button>
                  </div>
                </div>

                <div className="rounded-xl border border-gray-200 bg-gray-50/50 dark:border-gray-800 dark:bg-gray-900/50 p-4 font-mono text-xs text-gray-800 dark:text-gray-200 leading-relaxed space-y-1 select-all">
                  {previewTab === "pppoe" && (
                    <>
                      <div className="text-emerald-600 dark:text-emerald-400 font-bold">[ONLINE] PPPOE SESSION</div>
                      <div className="text-gray-300 dark:text-gray-700">────────────────────</div>
                      <div className="text-gray-500 dark:text-gray-400">2026-08-25 | 16:47:11</div>
                      <div className="text-gray-300 dark:text-gray-700">────────────────────</div>
                      <div><b>User :</b> holip</div>
                      <div><b>IP   :</b> 41.33.55.114</div>
                      <div><b>CallerID :</b> 14:AD:CA:0A:E6:29</div>
                      <div><b>Status :</b> ONLINE</div>
                      <div className="text-brand-500"><b>Online :</b> 125 user</div>
                    </>
                  )}

                  {previewTab === "hotspot" && (
                    <>
                      <div className="text-emerald-600 dark:text-emerald-400 font-bold">[ONLINE] HOTSPOT SESSION</div>
                      <div className="text-gray-300 dark:text-gray-700">────────────────────</div>
                      <div className="text-gray-500 dark:text-gray-400">2026-08-25 | 16:47:11</div>
                      <div className="text-gray-300 dark:text-gray-700">────────────────────</div>
                      <div><b>User :</b> voucher_wifi_24h</div>
                      <div><b>IP   :</b> 192.168.88.50</div>
                      <div><b>MAC  :</b> 14:AD:CA:0A:E6:29</div>
                      <div><b>Status :</b> ONLINE</div>
                      <div className="text-brand-500"><b>Online :</b> 45 user</div>
                    </>
                  )}

                  {previewTab === "router" && (
                    <>
                      <div className="text-emerald-600 dark:text-emerald-400 font-bold">[ONLINE] ROUTER SYSTEM</div>
                      <div className="text-gray-300 dark:text-gray-700">────────────────────</div>
                      <div className="text-gray-500 dark:text-gray-400">2026-08-25 | 16:47:11</div>
                      <div className="text-gray-300 dark:text-gray-700">────────────────────</div>
                      <div><b>Router :</b> Router NOC Utama</div>
                      <div><b>Host   :</b> 103.150.190.1:8728</div>
                      <div><b>CPU    :</b> 12%</div>
                      <div><b>Uptime :</b> 14d 06:22:15</div>
                      <div><b>Status :</b> ONLINE</div>
                    </>
                  )}

                  {previewTab === "arp" && (
                    <>
                      <div className="text-emerald-600 dark:text-emerald-400 font-bold">[ONLINE] ARP HOST</div>
                      <div className="text-gray-300 dark:text-gray-700">────────────────────</div>
                      <div className="text-gray-500 dark:text-gray-400">2026-08-25 | 16:47:11</div>
                      <div className="text-gray-300 dark:text-gray-700">────────────────────</div>
                      <div><b>Router :</b> Router NOC Utama</div>
                      <div><b>IP     :</b> 192.168.1.100</div>
                      <div><b>MAC    :</b> 14:AD:CA:0A:E6:29</div>
                      <div><b>Iface  :</b> ether2-lan</div>
                      <div><b>Status :</b> ONLINE</div>
                    </>
                  )}

                  {previewTab === "order" && (
                    <>
                      <div className="text-amber-600 dark:text-amber-400 font-bold">[ORDER] PESANAN VOUCHER #ORD-9821</div>
                      <div className="text-gray-300 dark:text-gray-700">────────────────────</div>
                      <div className="text-gray-500 dark:text-gray-400">2026-08-25 | 16:47:11</div>
                      <div className="text-gray-300 dark:text-gray-700">────────────────────</div>
                      <div><b>Paket :</b> Voucher WiFi 24 Jam (Rp 10.000)</div>
                      <div><b>Customer :</b> Budi (08123456789)</div>
                      <div><b>Bayar :</b> Transfer Bank (Menunggu ACC)</div>
                      <div className="text-emerald-600 dark:text-emerald-400 pt-1">[Setujui] [Tolak]</div>
                    </>
                  )}
                </div>
              </div>
            )}
          </div>

          {/* CARD 6: PANEL UJI COBA PENGIRIMAN NOTIFIKASI */}
          <div className="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xs dark:border-gray-800 dark:bg-white/[0.03]">
            <button
              type="button"
              onClick={() => toggleSection("testing")}
              className="w-full p-4 sm:p-5 flex items-center justify-between gap-3 text-left hover:bg-gray-50/50 dark:hover:bg-white/[0.02] transition-colors"
            >
              <div className="flex items-center gap-3 min-w-0">
                <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400">
                  <Send className="h-4 w-4" />
                </div>
                <div className="min-w-0">
                  <h3 className="text-sm font-bold text-gray-900 dark:text-white truncate">
                    Panel Uji Coba Pengiriman Pesan
                  </h3>
                  <p className="text-xs text-gray-500 dark:text-gray-400 truncate">
                    Kirim pesan uji coba ke bot/grup/topik untuk memverifikasi konfigurasi.
                  </p>
                </div>
              </div>
              <div className="shrink-0 p-1">
                <ChevronDown
                  className={cn(
                    "h-4 w-4 text-gray-400 transition-transform duration-200",
                    openSections.testing && "rotate-180 text-gray-700 dark:text-gray-200"
                  )}
                />
              </div>
            </button>

            {openSections.testing && (
              <div className="px-4 pb-5 sm:px-5 border-t border-gray-100 dark:border-gray-800 pt-4 space-y-3">
                <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-2.5">
                  <button
                    type="button"
                    onClick={() => handleTestTelegram("general")}
                    disabled={testingType !== null || !isConfigured}
                    className="flex flex-col items-center justify-center gap-1.5 h-16 p-2 rounded-xl bg-white dark:bg-gray-900 hover:bg-gray-50 dark:hover:bg-gray-800 border border-gray-200 dark:border-gray-800 text-xs font-semibold text-gray-900 dark:text-white disabled:opacity-50 transition-all active:scale-95 text-center shadow-xs"
                  >
                    {testingType === "general" ? (
                      <Loader2 className="h-4 w-4 animate-spin text-brand-500" />
                    ) : (
                      <Send className="h-4 w-4 text-brand-500" />
                    )}
                    <span className="text-[11px]">Pesan Umum</span>
                  </button>

                  <button
                    type="button"
                    onClick={() => handleTestTelegram("order")}
                    disabled={testingType !== null || !isConfigured}
                    className="flex flex-col items-center justify-center gap-1.5 h-16 p-2 rounded-xl bg-white dark:bg-gray-900 hover:bg-gray-50 dark:hover:bg-gray-800 border border-gray-200 dark:border-gray-800 text-xs font-semibold text-gray-900 dark:text-white disabled:opacity-50 transition-all active:scale-95 text-center shadow-xs"
                  >
                    {testingType === "order" ? (
                      <Loader2 className="h-4 w-4 animate-spin text-brand-500" />
                    ) : (
                      <Bot className="h-4 w-4 text-emerald-500" />
                    )}
                    <span className="text-[11px]">Notif Pesanan</span>
                  </button>

                  <button
                    type="button"
                    onClick={() => handleTestTelegram("pppoe")}
                    disabled={testingType !== null || !isConfigured}
                    className="flex flex-col items-center justify-center gap-1.5 h-16 p-2 rounded-xl bg-white dark:bg-gray-900 hover:bg-gray-50 dark:hover:bg-gray-800 border border-gray-200 dark:border-gray-800 text-xs font-semibold text-gray-900 dark:text-white disabled:opacity-50 transition-all active:scale-95 text-center shadow-xs"
                  >
                    {testingType === "pppoe" ? (
                      <Loader2 className="h-4 w-4 animate-spin text-brand-500" />
                    ) : (
                      <Network className="h-4 w-4 text-cyan-500" />
                    )}
                    <span className="text-[11px]">Alert PPPoE</span>
                  </button>

                  <button
                    type="button"
                    onClick={() => handleTestTelegram("hotspot")}
                    disabled={testingType !== null || !isConfigured}
                    className="flex flex-col items-center justify-center gap-1.5 h-16 p-2 rounded-xl bg-white dark:bg-gray-900 hover:bg-gray-50 dark:hover:bg-gray-800 border border-gray-200 dark:border-gray-800 text-xs font-semibold text-gray-900 dark:text-white disabled:opacity-50 transition-all active:scale-95 text-center shadow-xs"
                  >
                    {testingType === "hotspot" ? (
                      <Loader2 className="h-4 w-4 animate-spin text-brand-500" />
                    ) : (
                      <Wifi className="h-4 w-4 text-amber-500" />
                    )}
                    <span className="text-[11px]">Alert Hotspot</span>
                  </button>

                  <button
                    type="button"
                    onClick={() => handleTestTelegram("router")}
                    disabled={testingType !== null || !isConfigured}
                    className="flex flex-col items-center justify-center gap-1.5 h-16 p-2 rounded-xl bg-white dark:bg-gray-900 hover:bg-gray-50 dark:hover:bg-gray-800 border border-gray-200 dark:border-gray-800 text-xs font-semibold text-gray-900 dark:text-white disabled:opacity-50 transition-all active:scale-95 text-center shadow-xs"
                  >
                    {testingType === "router" ? (
                      <Loader2 className="h-4 w-4 animate-spin text-brand-500" />
                    ) : (
                      <Server className="h-4 w-4 text-blue-500" />
                    )}
                    <span className="text-[11px]">Alert Router</span>
                  </button>

                  <button
                    type="button"
                    onClick={() => handleTestTelegram("arp")}
                    disabled={testingType !== null || !isConfigured}
                    className="flex flex-col items-center justify-center gap-1.5 h-16 p-2 rounded-xl bg-white dark:bg-gray-900 hover:bg-gray-50 dark:hover:bg-gray-800 border border-gray-200 dark:border-gray-800 text-xs font-semibold text-gray-900 dark:text-white disabled:opacity-50 transition-all active:scale-95 text-center shadow-xs"
                  >
                    {testingType === "arp" ? (
                      <Loader2 className="h-4 w-4 animate-spin text-brand-500" />
                    ) : (
                      <Activity className="h-4 w-4 text-purple-500" />
                    )}
                    <span className="text-[11px]">Alert ARP</span>
                  </button>
                </div>
              </div>
            )}
          </div>
        </form>
      </div>
    </AppLayout>
  )
}