import * as React from "react"
import { AnimatePresence, motion } from "framer-motion"
import {
  Server,
  MessageSquare,
  Check,
  X,
  Eye,
  EyeOff,
  Loader2,
  AlertCircle,
  ShieldCheck,
  Activity,
  Copy,
} from "lucide-react"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Button } from "@/components/ui/button"

type ServiceKey = "genieacs" | "whatsapp"

const PROVIDER_PRESETS: Record<string, { label: string; defaultUrl: string }> = {
  nodera: { label: "NODERA WhatsApp Gateway (Rekomendasi • Cepat & Stabil)", defaultUrl: "https://wa.dgtlnetsolution.com/api/send-message" },
  fonnte: { label: "Fonnte (fonnte.com)", defaultUrl: "https://api.fonnte.com/send" },
  kirimi: { label: "Kirimi.id (kirimi.id)", defaultUrl: "https://api.kirimi.id/v1/send-message" },
  mhwa: { label: "MHWA (mhwa.biz.id)", defaultUrl: "https://mhwa.biz.id/api/message/send" },
  wablas: { label: "Wablas API", defaultUrl: "https://kudus.wablas.com/api/v2/send-message" },
  starsender: { label: "Starsender", defaultUrl: "https://starsender.online/api/sendText" },
  whacenter: { label: "WhaCenter", defaultUrl: "https://app.whacenter.com/api/send" },
  custom: { label: "Custom API Gateway / Server Mandiri", defaultUrl: "" },
}

export function ServiceSettingsSheet({ service, onClose }: { service: ServiceKey | null; onClose: () => void }) {
  const [values, setValues] = React.useState<Record<string, string>>({
    WHATSAPP_PROVIDER: "nodera",
    WHATSAPP_API_URL: "https://wa.dgtlnetsolution.com/api/send-message",
    WHATSAPP_TOKEN: "",
    WHATSAPP_SENDER_PHONE: "",
    WHATSAPP_AUTO_TYPING: "1",
  })
  const [loading, setLoading] = React.useState(false)
  const [verifying, setVerifying] = React.useState(false)
  const [saved, setSaved] = React.useState(false)
  const [copiedWebhook, setCopiedWebhook] = React.useState(false)
  const [successMsg, setSuccessMsg] = React.useState<string | null>(null)
  const [error, setError] = React.useState<string | null>(null)
  const [showToken, setShowToken] = React.useState(false)

  const [showGeniePassword, setShowGeniePassword] = React.useState(false)
  const [showGenieToken, setShowGenieToken] = React.useState(false)

  // Load nilai saat sheet terbuka
  React.useEffect(() => {
    if (!service) return
    setLoading(true)
    setSaved(false)
    setError(null)
    setSuccessMsg(null)
    fetch(`/admin/service-settings?service=${service}`)
      .then((r) => r.json())
      .then((d) => {
        const incoming = d.values ?? {}
        setValues((prev) => ({
          ...prev,
          ...incoming,
          GENIEACS_URL: incoming.GENIEACS_URL || "http://127.0.0.1:7557",
          WHATSAPP_PROVIDER: incoming.WHATSAPP_PROVIDER || "nodera",
          WHATSAPP_API_URL: incoming.WHATSAPP_API_URL || "https://wa.dgtlnetsolution.com/api/send-message",
          WHATSAPP_AUTO_TYPING: incoming.WHATSAPP_AUTO_TYPING ?? "1",
        }))
      })
      .catch(() => setError("Gagal memuat pengaturan."))
      .finally(() => setLoading(false))
  }, [service])

  const handleProviderChange = (newProvider: string) => {
    const preset = PROVIDER_PRESETS[newProvider]
    setValues((prev) => ({
      ...prev,
      WHATSAPP_PROVIDER: newProvider,
      WHATSAPP_API_URL: preset?.defaultUrl || prev.WHATSAPP_API_URL || "",
    }))
  }

  // Uji koneksi dulu, baru simpan jika berhasil (WhatsApp)
  const handleTestAndSaveWhatsapp = async (e: React.FormEvent) => {
    e.preventDefault()
    setVerifying(true)
    setError(null)
    setSuccessMsg(null)

    try {
      const res = await fetch("/admin/whatsapp/test-and-save", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Accept: "application/json",
          "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") ?? "",
        },
        body: JSON.stringify({
          provider: values.WHATSAPP_PROVIDER,
          api_url: values.WHATSAPP_API_URL,
          token: values.WHATSAPP_TOKEN,
          sender_phone: values.WHATSAPP_SENDER_PHONE,
          auto_typing: values.WHATSAPP_AUTO_TYPING === "1",
        }),
      })

      const rawText = await res.text()
      let data: any = {}
      try {
        data = JSON.parse(rawText)
      } catch (parseErr) {
        setError(`Respon server tidak valid (${res.status}). Silakan coba muat ulang halaman.`)
        return
      }

      if (!res.ok || !data.success) {
        setError(data.message || "Koneksi gagal. Silakan periksa kembali Token API dan Endpoint URL.")
        return
      }

      setSaved(true)
      setSuccessMsg(data.message || "Koneksi terhubung & pengaturan berhasil disimpan!")
      setTimeout(() => {
        setSaved(false)
        onClose()
      }, 1200)
    } catch (err: any) {
      setError(err.message || "Gagal menghubungi server WhatsApp Gateway.")
    } finally {
      setVerifying(false)
    }
  }

  // Uji koneksi dulu, baru simpan jika berhasil (GenieACS TR-069)
  const handleTestAndSaveGenieacs = async (e: React.FormEvent) => {
    e.preventDefault()
    setVerifying(true)
    setError(null)
    setSuccessMsg(null)

    try {
      const res = await fetch("/admin/genieacs/test-and-save", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Accept: "application/json",
          "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") ?? "",
        },
        body: JSON.stringify({
          url: values.GENIEACS_URL,
          username: values.GENIEACS_USERNAME,
          password: values.GENIEACS_PASSWORD,
          token: values.GENIEACS_TOKEN,
        }),
      })

      const rawText = await res.text()
      let data: any = {}
      try {
        data = JSON.parse(rawText)
      } catch (parseErr) {
        setError(`Respon server tidak valid (${res.status}). Silakan coba muat ulang halaman.`)
        return
      }

      if (!res.ok || !data.success) {
        setError(data.message || "Gagal terhubung ke GenieACS. Pastikan URL NBI (port 7557) dan kredensial benar.")
        return
      }

      setSaved(true)
      setSuccessMsg(data.message || "Koneksi GenieACS terhubung & pengaturan berhasil disimpan!")
      setTimeout(() => {
        setSaved(false)
        onClose()
      }, 1200)
    } catch (err: any) {
      setError(err.message || "Gagal menghubungi server GenieACS.")
    } finally {
      setVerifying(false)
    }
  }

  return (
    <AnimatePresence>
      {service ? (
        <>
          <motion.div
            className="fixed inset-0 z-[60] bg-black/60 backdrop-blur-xs"
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            exit={{ opacity: 0 }}
            onClick={onClose}
          />
          <motion.div
            className="fixed inset-x-0 bottom-0 z-[61] mx-auto max-w-lg max-h-[92vh] overflow-y-auto rounded-t-3xl border-t border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 text-gray-900 dark:text-white p-5 sm:p-6 shadow-2xl space-y-4"
            initial={{ y: "100%" }}
            animate={{ y: 0 }}
            exit={{ y: "100%" }}
            transition={{ type: "spring", damping: 28, stiffness: 320 }}
          >
            <div className="mx-auto mb-2 h-1 w-12 rounded-full bg-gray-300 dark:bg-gray-700" />

            {/* Header */}
            <div className="flex items-center justify-between border-b border-gray-100 dark:border-gray-800 pb-3.5">
              <div className="flex items-center gap-3">
                <div className="flex h-9 w-9 items-center justify-center rounded-xl bg-brand-50 text-brand-600 dark:bg-brand-950 dark:text-brand-400 border border-brand-200 dark:border-brand-800">
                  {service === "whatsapp" ? <MessageSquare className="h-4.5 w-4.5" /> : <Server className="h-4.5 w-4.5" />}
                </div>
                <div>
                  <h3 className="font-bold text-sm text-gray-900 dark:text-white">
                    {service === "whatsapp" ? "Konfigurasi WhatsApp Gateway API" : "Setting GenieACS TR-069"}
                  </h3>
                  <p className="text-[11px] text-gray-500 dark:text-gray-400">
                    {service === "whatsapp"
                      ? "Uji & simpan kredensial API WhatsApp Gateway"
                      : "URL dan otentikasi server TR-069 GenieACS"}
                  </p>
                </div>
              </div>
              <button
                onClick={onClose}
                className="flex h-8 w-8 items-center justify-center rounded-lg border border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-800 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-white transition cursor-pointer"
                aria-label="Tutup"
              >
                <X className="h-4 w-4" />
              </button>
            </div>

            {loading ? (
              <div className="py-12 flex flex-col items-center justify-center gap-2 text-gray-400 text-xs">
                <Loader2 className="h-5 w-5 animate-spin text-brand-500" />
                <span>Memuat data konfigurasi...</span>
              </div>
            ) : service === "whatsapp" ? (
              <form onSubmit={handleTestAndSaveWhatsapp} className="space-y-4 text-xs">
                {/* Alert Error (Jika koneksi gagal, form tetap ada isinya) */}
                {error && (
                  <div className="rounded-xl border border-rose-200 bg-rose-50 dark:border-rose-900/50 dark:bg-rose-950/40 p-3.5 text-rose-700 dark:text-rose-300 text-xs flex items-start gap-2.5 animate-in fade-in-50">
                    <AlertCircle className="h-4 w-4 text-rose-500 shrink-0 mt-0.5" />
                    <div>
                      <span className="font-bold block text-rose-800 dark:text-rose-200">Koneksi Gagal Diuji</span>
                      <p className="mt-0.5 leading-relaxed">{error}</p>
                    </div>
                  </div>
                )}

                {/* Alert Success */}
                {successMsg && (
                  <div className="rounded-xl border border-emerald-200 bg-emerald-50 dark:border-emerald-900/50 dark:bg-emerald-950/40 p-3.5 text-emerald-700 dark:text-emerald-300 text-xs flex items-start gap-2.5 animate-in fade-in-50">
                    <Check className="h-4 w-4 text-emerald-500 shrink-0 mt-0.5" />
                    <div>
                      <span className="font-bold block text-emerald-800 dark:text-emerald-200">Koneksi Berhasil!</span>
                      <p className="mt-0.5 leading-relaxed">{successMsg}</p>
                    </div>
                  </div>
                )}

                {/* 1. Dropdown Penyedia Gateway */}
                <div>
                  <label className="font-semibold text-gray-700 dark:text-gray-300 mb-1.5 block">
                    Penyedia Gateway WhatsApp (Provider)
                  </label>
                  <select
                    value={values.WHATSAPP_PROVIDER || "fonnte"}
                    onChange={(e) => handleProviderChange(e.target.value)}
                    className="h-10 w-full rounded-xl border border-gray-200 bg-gray-50/75 dark:border-gray-800 dark:bg-gray-950 px-3.5 text-xs text-gray-900 dark:text-white focus:border-brand-500 focus:outline-hidden"
                  >
                    {Object.entries(PROVIDER_PRESETS).map(([k, p]) => (
                      <option key={k} value={k} className="bg-white dark:bg-gray-900 text-gray-900 dark:text-white">
                        {p.label}
                      </option>
                    ))}
                  </select>
                </div>

                {/* 2. Endpoint API URL */}
                <div>
                  <label className="font-semibold text-gray-700 dark:text-gray-300 mb-1.5 block">Endpoint API URL</label>
                  <input
                    type="text"
                    value={values.WHATSAPP_API_URL || ""}
                    onChange={(e) => setValues({ ...values, WHATSAPP_API_URL: e.target.value })}
                    placeholder="https://api.fonnte.com/send"
                    className="h-10 w-full rounded-xl border border-gray-200 bg-gray-50/75 dark:border-gray-800 dark:bg-gray-950 px-3.5 text-xs text-gray-900 dark:text-white font-mono placeholder:text-gray-400 focus:border-brand-500 focus:outline-hidden"
                    required
                  />
                </div>

                {/* 3. Token API / Secret Key */}
                <div>
                  <label className="font-semibold text-gray-700 dark:text-gray-300 mb-1.5 block">
                    {values.WHATSAPP_PROVIDER === "kirimi"
                      ? "Secret Key Kirimi.id"
                      : values.WHATSAPP_PROVIDER === "nodera"
                      ? "API Key (Bearer Token wa.dgtlnetsolution.com)"
                      : "API Token / Secret Key"}
                  </label>
                  <div className="relative">
                    <input
                      type={showToken ? "text" : "password"}
                      value={values.WHATSAPP_TOKEN || ""}
                      onChange={(e) => setValues({ ...values, WHATSAPP_TOKEN: e.target.value })}
                      placeholder={
                        values.WHATSAPP_PROVIDER === "kirimi"
                          ? "Masukkan Secret Key dari dashboard kirimi.id..."
                          : values.WHATSAPP_PROVIDER === "nodera"
                          ? "Salin API Key dari wa.dgtlnetsolution.com/credentials..."
                          : "Masukkan Token API WhatsApp..."
                      }
                      className="h-10 w-full rounded-xl border border-gray-200 bg-gray-50/75 dark:border-gray-800 dark:bg-gray-950 pl-3.5 pr-10 text-xs text-gray-900 dark:text-white font-mono placeholder:text-gray-400 focus:border-brand-500 focus:outline-hidden"
                      required
                    />
                    <button
                      type="button"
                      onClick={() => setShowToken(!showToken)}
                      className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-700 dark:hover:text-white cursor-pointer"
                    >
                      {showToken ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                    </button>
                  </div>
                </div>

                {/* 4. Nomor Pengirim (Device Phone / Session ID) */}
                <div>
                  <label className="font-semibold text-gray-700 dark:text-gray-300 mb-1.5 block">
                    {values.WHATSAPP_PROVIDER === "mhwa"
                      ? "Session ID Perangkat MHWA (Wajib)"
                      : values.WHATSAPP_PROVIDER === "kirimi"
                      ? "User Code & Device ID Kirimi.id (Format: USER_CODE:DEVICE_ID)"
                      : values.WHATSAPP_PROVIDER === "nodera"
                      ? "Device ID / Session ID NODERA (Opsional, Default jika kosong)"
                      : "No. Pengirim / Device ID / Session ID"}
                  </label>
                  <input
                    type="text"
                    value={values.WHATSAPP_SENDER_PHONE || ""}
                    onChange={(e) => setValues({ ...values, WHATSAPP_SENDER_PHONE: e.target.value })}
                    placeholder={
                      values.WHATSAPP_PROVIDER === "mhwa"
                        ? "Masukkan Session ID dari dashboard mhwa.biz.id (contoh: session_1)"
                        : values.WHATSAPP_PROVIDER === "kirimi"
                        ? "cth: USR-12345:DEV-67890 (atau DEV-67890)"
                        : values.WHATSAPP_PROVIDER === "nodera"
                        ? "cth: 1 atau dgtl_session_xxx (atau kosongkan untuk device default)"
                        : "cth: 081234567890 atau dev_123_456789"
                    }
                    className="h-10 w-full rounded-xl border border-gray-200 bg-gray-50/75 dark:border-gray-800 dark:bg-gray-950 px-3.5 text-xs text-gray-900 dark:text-white font-mono placeholder:text-gray-400 focus:border-brand-500 focus:outline-hidden"
                  />
                  <span className="text-[10px] text-gray-500 dark:text-gray-400 block mt-1">
                    {values.WHATSAPP_PROVIDER === "mhwa" ? (
                      <>
                        Wajib diisi sesuai <b>Session ID</b> perangkat Anda di{" "}
                        <a
                          href="https://mhwa.biz.id"
                          target="_blank"
                          rel="noreferrer"
                          className="text-brand-600 dark:text-brand-400 underline hover:text-brand-700"
                        >
                          mhwa.biz.id
                        </a>{" "}
                        (contoh: <code className="text-amber-600 dark:text-amber-400 font-bold">dev_21_1789530016696</code>).
                      </>
                    ) : values.WHATSAPP_PROVIDER === "kirimi" ? (
                      <>
                        Format: <code className="text-amber-600 dark:text-amber-400 font-bold">USER_CODE:DEVICE_ID</code>. Dapatkan User Code, Device ID, & Secret Key di dashboard{" "}
                        <a
                          href="https://kirimi.id"
                          target="_blank"
                          rel="noreferrer"
                          className="text-brand-600 dark:text-brand-400 underline hover:text-brand-700"
                        >
                          kirimi.id
                        </a>.
                      </>
                    ) : values.WHATSAPP_PROVIDER === "nodera" ? (
                      <>
                        Dapatkan API Key & Device ID di dashboard{" "}
                        <a
                          href="https://wa.dgtlnetsolution.com"
                          target="_blank"
                          rel="noreferrer"
                          className="text-brand-600 dark:text-brand-400 underline hover:text-brand-700"
                        >
                          wa.dgtlnetsolution.com
                        </a>. Jika hanya punya 1 perangkat atau ingin pakai default, nomor pengirim bisa dikosongkan.
                      </>
                    ) : (
                      "Dapat dikosongkan untuk Fonnte/Wablas jika hanya memiliki satu nomor utama terhubung."
                    )}
                  </span>
                </div>

                {/* 5. Inbound Webhook URL (Bot Mandiri Pelanggan) */}
                <div className="rounded-xl border border-brand-500/20 bg-brand-50/50 dark:border-brand-500/10 dark:bg-brand-950/20 p-3 space-y-1.5">
                  <div className="flex items-center justify-between">
                    <span className="text-[11px] font-bold text-brand-700 dark:text-brand-300">
                      URL Webhook Bot Mandiri (!status, !gantiwifi)
                    </span>
                    <button
                      type="button"
                      onClick={() => {
                        const url = `${window.location.origin}/webhook/whatsapp`
                        navigator.clipboard.writeText(url)
                        setCopiedWebhook(true)
                        setTimeout(() => setCopiedWebhook(false), 2000)
                      }}
                      className="inline-flex items-center gap-1 text-[10px] font-bold text-brand-600 dark:text-brand-400 hover:underline cursor-pointer"
                    >
                      {copiedWebhook ? <Check className="h-3 w-3 text-emerald-500" /> : <Copy className="h-3 w-3" />}
                      <span>{copiedWebhook ? "Disalin!" : "Salin URL"}</span>
                    </button>
                  </div>
                  <p className="font-mono text-[11px] text-gray-700 dark:text-gray-300 bg-white/75 dark:bg-gray-900/60 p-1.5 rounded-lg border border-brand-200/40 dark:border-brand-900/30 truncate">
                    {typeof window !== "undefined" ? `${window.location.origin}/webhook/whatsapp` : "https://domain-anda.com/webhook/whatsapp"}
                  </p>
                  <p className="text-[10px] text-gray-500 dark:text-gray-400 leading-tight">
                    Tempelkan URL ini di menu Webhook provider Anda (Fonnte/Wablas/Starsender) agar pelanggan bisa cek tagihan, status modem &amp; ganti password WiFi via chat WA.
                  </p>
                </div>

                {/* 6. Simulasi Mengetik Manusia */}
                <label className="flex items-start gap-3 p-3.5 rounded-xl border border-gray-200 bg-gray-50/75 dark:border-gray-800 dark:bg-gray-950 cursor-pointer hover:border-gray-300 dark:hover:border-gray-700 transition-all select-none">
                  <input
                    type="checkbox"
                    checked={values.WHATSAPP_AUTO_TYPING === "1"}
                    onChange={(e) =>
                      setValues({ ...values, WHATSAPP_AUTO_TYPING: e.target.checked ? "1" : "0" })
                    }
                    className="mt-0.5 h-4 w-4 rounded border-gray-300 dark:border-gray-700 text-brand-500 focus:ring-brand-500"
                  />
                  <div className="text-xs">
                    <span className="font-bold text-gray-900 dark:text-white block">Simulasikan Ketikan Manusia (Anti-Banned)</span>
                    <span className="text-[11px] text-gray-500 dark:text-gray-400 block mt-0.5">
                      Menambahkan jeda delay typing alami sebelum pesan terkirim.
                    </span>
                  </div>
                </label>

                {/* Tombol Uji Koneksi & Simpan */}
                <div className="pt-2">
                  <button
                    type="submit"
                    disabled={verifying || !values.WHATSAPP_TOKEN}
                    className="flex h-11 w-full items-center justify-center gap-2 rounded-xl bg-brand-500 hover:bg-brand-600 text-white font-bold text-xs shadow-xs transition-all active:scale-[0.98] disabled:opacity-50 cursor-pointer"
                  >
                    {verifying ? (
                      <>
                        <Loader2 className="h-4 w-4 animate-spin" />
                        <span>Menguji Koneksi WhatsApp...</span>
                      </>
                    ) : saved ? (
                      <>
                        <Check className="h-4 w-4 text-white" />
                        <span>Tersimpan &amp; Terverifikasi!</span>
                      </>
                    ) : (
                      <>
                        <Activity className="h-4 w-4" />
                        <span>Uji Koneksi &amp; Simpan Pengaturan</span>
                      </>
                    )}
                  </button>
                </div>
              </form>
            ) : (
              /* GenieACS Form */
              <form onSubmit={handleTestAndSaveGenieacs} className="space-y-4 text-xs">
                {error && (
                  <div className="flex items-start gap-2.5 rounded-xl border border-rose-200 bg-rose-50 dark:border-rose-900/50 dark:bg-rose-950/40 p-3 text-xs text-rose-700 dark:text-rose-300">
                    <AlertCircle className="h-4 w-4 shrink-0 text-rose-500 mt-0.5" />
                    <span>{error}</span>
                  </div>
                )}

                {successMsg && (
                  <div className="flex items-start gap-2.5 rounded-xl border border-emerald-200 bg-emerald-50 dark:border-emerald-900/50 dark:bg-emerald-950/40 p-3 text-xs text-emerald-700 dark:text-emerald-300">
                    <Check className="h-4 w-4 shrink-0 text-emerald-500 mt-0.5" />
                    <span>{successMsg}</span>
                  </div>
                )}

                {/* 1. URL GenieACS NBI API */}
                <div>
                  <label className="font-semibold text-gray-700 dark:text-gray-300 mb-1.5 block">
                    URL GenieACS NBI API (REST API) *
                  </label>
                  <input
                    type="url"
                    value={values.GENIEACS_URL || ""}
                    onChange={(e) => setValues({ ...values, GENIEACS_URL: e.target.value })}
                    placeholder="http://192.168.1.100:7557"
                    className="h-10 w-full rounded-xl border border-gray-200 bg-gray-50/75 dark:border-gray-800 dark:bg-gray-950 px-3.5 text-xs text-gray-900 dark:text-white font-mono placeholder:text-gray-400 focus:border-brand-500 focus:outline-hidden"
                    required
                  />
                  <p className="mt-1 text-[11px] text-gray-500 dark:text-gray-400">
                    Default port NBI GenieACS adalah <code className="text-brand-600 dark:text-brand-400 font-bold">:7557</code>.
                  </p>
                </div>

                {/* 2. Username (Basic Auth) */}
                <div>
                  <label className="font-semibold text-gray-700 dark:text-gray-300 mb-1.5 block">
                    Username NBI (Opsional)
                  </label>
                  <input
                    type="text"
                    value={values.GENIEACS_USERNAME || ""}
                    onChange={(e) => setValues({ ...values, GENIEACS_USERNAME: e.target.value })}
                    placeholder="admin"
                    className="h-10 w-full rounded-xl border border-gray-200 bg-gray-50/75 dark:border-gray-800 dark:bg-gray-950 px-3.5 text-xs text-gray-900 dark:text-white placeholder:text-gray-400 focus:border-brand-500 focus:outline-hidden"
                  />
                </div>

                {/* 3. Password (Basic Auth) */}
                <div>
                  <label className="font-semibold text-gray-700 dark:text-gray-300 mb-1.5 block">
                    Password NBI (Opsional)
                  </label>
                  <div className="relative">
                    <input
                      type={showGeniePassword ? "text" : "password"}
                      value={values.GENIEACS_PASSWORD || ""}
                      onChange={(e) => setValues({ ...values, GENIEACS_PASSWORD: e.target.value })}
                      placeholder="••••••••"
                      className="h-10 w-full rounded-xl border border-gray-200 bg-gray-50/75 dark:border-gray-800 dark:bg-gray-950 pl-3.5 pr-10 text-xs text-gray-900 dark:text-white font-mono placeholder:text-gray-400 focus:border-brand-500 focus:outline-hidden"
                    />
                    <button
                      type="button"
                      onClick={() => setShowGeniePassword(!showGeniePassword)}
                      className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-700 dark:hover:text-white cursor-pointer"
                    >
                      {showGeniePassword ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                    </button>
                  </div>
                </div>

                {/* 4. Token (Bearer Token) */}
                <div>
                  <label className="font-semibold text-gray-700 dark:text-gray-300 mb-1.5 block">
                    API Bearer Token (Opsional)
                  </label>
                  <div className="relative">
                    <input
                      type={showGenieToken ? "text" : "password"}
                      value={values.GENIEACS_TOKEN || ""}
                      onChange={(e) => setValues({ ...values, GENIEACS_TOKEN: e.target.value })}
                      placeholder="token-api-genieacs"
                      className="h-10 w-full rounded-xl border border-gray-200 bg-gray-50/75 dark:border-gray-800 dark:bg-gray-950 pl-3.5 pr-10 text-xs text-gray-900 dark:text-white font-mono placeholder:text-gray-400 focus:border-brand-500 focus:outline-hidden"
                    />
                    <button
                      type="button"
                      onClick={() => setShowGenieToken(!showGenieToken)}
                      className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-700 dark:hover:text-white cursor-pointer"
                    >
                      {showGenieToken ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                    </button>
                  </div>
                </div>

                {/* Tombol Uji Koneksi & Simpan */}
                <div className="pt-2">
                  <button
                    type="submit"
                    disabled={verifying || !values.GENIEACS_URL}
                    className="flex h-11 w-full items-center justify-center gap-2 rounded-xl bg-brand-500 hover:bg-brand-600 text-white font-bold text-xs shadow-xs transition-all active:scale-[0.98] disabled:opacity-50 cursor-pointer"
                  >
                    {verifying ? (
                      <>
                        <Loader2 className="h-4 w-4 animate-spin" />
                        <span>Menguji Koneksi GenieACS...</span>
                      </>
                    ) : saved ? (
                      <>
                        <Check className="h-4 w-4 text-white" />
                        <span>Tersimpan &amp; Terverifikasi!</span>
                      </>
                    ) : (
                      <>
                        <Activity className="h-4 w-4" />
                        <span>Uji Koneksi &amp; Simpan Pengaturan</span>
                      </>
                    )}
                  </button>
                </div>
              </form>
            )}
          </motion.div>
        </>
      ) : null}
    </AnimatePresence>
  )
}

