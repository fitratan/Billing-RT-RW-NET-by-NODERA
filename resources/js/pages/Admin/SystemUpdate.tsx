import * as React from "react"
import { useState } from "react"
import { AppLayout } from "@/components/layout/app-layout"
import {
  RefreshCw,
  DownloadCloud,
  ShieldCheck,
  HardDrive,
  Lock,
  FolderArchive,
  CheckCircle2,
  AlertTriangle,
  FileCode,
  Terminal,
  ExternalLink,
  MessageSquare,
  ArrowUpCircle,
  Clock,
  KeyRound,
  Pencil,
  Copy,
  Check,
  Users,
  Router as RouterIcon,
  X,
} from "lucide-react"
import { PageProps } from "@/types"
import { adminSidebarItems, adminNavItems, adminBrand } from "@/lib/admin-nav"
import { Head } from "@inertiajs/react"

interface SystemUpdateProps extends PageProps {
  current_version: string
  latest_version?: string
  update_available: boolean
  release_date?: string
  changelog?: string[]
  license?: {
    key: string
    is_free_tier: boolean
    status: string
    customer_count: number
    max_customers: number
    router_count: number
    max_routers: number
  }
}

export default function SystemUpdatePage({
  current_version = "2.5.0",
  latest_version = "2.5.0",
  update_available = false,
  release_date = "",
  changelog = [],
  license,
}: SystemUpdateProps) {
  const [checking, setChecking] = useState(false)
  const [updating, setUpdating] = useState(false)
  const [updateSuccess, setUpdateSuccess] = useState(false)
  const [logs, setLogs] = useState<string[]>([])
  const [hasUpdate, setHasUpdate] = useState(update_available)
  const [remoteVersion, setRemoteVersion] = useState(latest_version || current_version)
  const [releaseDate, setReleaseDate] = useState(release_date)
  const [releaseNotes, setReleaseNotes] = useState<string[]>(
    changelog && changelog.length > 0
      ? changelog
      : [
          "Penyempurnaan modul Standalone Billing & sinkronisasi SQLite otomatis.",
          "Launcher 1-klik Windows (START-NODERA.bat & NODERA-SILENT.vbs).",
          "Optimalisasi sistem lisensi offline ISP hingga 35 pelanggan gratis.",
          "Proteksi Zero Data Loss: berkas .env, storage, dan database SQLite terisolasi aman.",
        ]
  )
  const [lastChecked, setLastChecked] = useState<string>("Baru saja")

  // License Modal State
  const [licenseModalOpen, setLicenseModalOpen] = useState(false)
  const [inputLicenseKey, setInputLicenseKey] = useState(license?.key !== "STANDALONE-FREE-TIER" ? license?.key || "" : "")
  const [savingLicense, setSavingLicense] = useState(false)
  const [licenseMessage, setLicenseMessage] = useState<{ type: "success" | "error"; text: string } | null>(null)
  const [copiedKey, setCopiedKey] = useState(false)

  const activeKey = license?.key || "STANDALONE-FREE-TIER"
  const isFreeTier = license?.is_free_tier ?? (activeKey === "STANDALONE-FREE-TIER" || !license?.key)
  const custCount = license?.customer_count ?? 0
  const maxCust = license?.max_customers ?? (isFreeTier ? 35 : 999999)
  const routerCount = license?.router_count ?? 0
  const maxRouter = license?.max_routers ?? (isFreeTier ? 1 : 999)
  const custPercent = maxCust > 0 && maxCust < 999999 ? Math.min(100, Math.round((custCount / maxCust) * 100)) : 0

  const handleCopyKey = () => {
    navigator.clipboard.writeText(activeKey)
    setCopiedKey(true)
    setTimeout(() => setCopiedKey(false), 2000)
  }

  const handleCheckUpdate = async () => {
    setChecking(true)
    try {
      const res = await fetch("/admin/system-update/check", {
        headers: {
          Accept: "application/json",
          "X-Requested-With": "XMLHttpRequest",
        },
      })
      const data = await res.json()
      setHasUpdate(data.update_available)
      if (data.latest_version) setRemoteVersion(data.latest_version)
      if (data.release_date) setReleaseDate(data.release_date)
      if (data.changelog && data.changelog.length > 0) setReleaseNotes(data.changelog)
      setLastChecked(new Date().toLocaleTimeString("id-ID", { hour: "2-digit", minute: "2-digit", second: "2-digit" }))
    } catch (e) {
      console.error(e)
    } finally {
      setChecking(false)
    }
  }

  const handlePerformUpdate = async () => {
    if (!confirm("Apakah Anda yakin ingin memperbarui sistem sekarang? Database SQLite dan berkas .env Anda terproteksi aman 100%.")) {
      return
    }

    setUpdating(true)
    setLogs(["[START] Memulai proses pembaruan sistem NODERA..."])
    try {
      const csrfToken = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || ""
      const res = await fetch("/admin/system-update/perform", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Accept: "application/json",
          "X-CSRF-TOKEN": csrfToken,
          "X-Requested-With": "XMLHttpRequest",
        },
      })
      const data = await res.json()
      if (data.logs && Array.isArray(data.logs)) {
        setLogs(data.logs)
      }
      if (data.success) {
        setUpdateSuccess(true)
        setHasUpdate(false)
        setTimeout(() => {
          window.location.reload()
        }, 2000)
      } else {
        alert(data.message || "Gagal melakukan pembaruan sistem.")
      }
    } catch (e) {
      console.error(e)
      setLogs((prev) => [...prev, "[ERROR] Terjadi kesalahan koneksi saat mengunduh rilis."])
    } finally {
      setUpdating(false)
    }
  }

  const handleSaveLicense = async (e: React.FormEvent) => {
    e.preventDefault()
    setSavingLicense(true)
    setLicenseMessage(null)

    try {
      const csrfToken = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || ""
      const res = await fetch("/admin/system-update/license", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Accept: "application/json",
          "X-CSRF-TOKEN": csrfToken,
          "X-Requested-With": "XMLHttpRequest",
        },
        body: JSON.stringify({
          license_key: inputLicenseKey,
        }),
      })
      const data = await res.json()
      if (data.success) {
        setLicenseMessage({ type: "success", text: data.message || "Kunci lisensi berhasil disimpan & diverifikasi!" })
        setTimeout(() => {
          setLicenseModalOpen(false)
          window.location.reload()
        }, 1200)
      } else {
        setLicenseMessage({ type: "error", text: data.message || "Gagal memverifikasi kunci lisensi." })
      }
    } catch (err) {
      setLicenseMessage({ type: "error", text: "Terjadi kesalahan jaringan saat menyimpan lisensi." })
    } finally {
      setSavingLicense(false)
    }
  }

  return (
    <AppLayout brand={adminBrand} sidebarItems={adminSidebarItems} navItems={adminNavItems}>
      <Head title="Pembaruan Sistem" />

      <div className="space-y-4 sm:space-y-6">
        {/* ── TOP KPI OVERVIEW CARDS (1:1 TAILADMIN STANDARD) ── */}
        <div className="grid grid-cols-1 lg:grid-cols-12 gap-4 sm:gap-5">
          {/* Card 1: Status Pembaruan Sistem (5 Cols) */}
          <div className="lg:col-span-5 rounded-2xl border border-gray-200 bg-white p-4 sm:p-5 shadow-xs dark:border-gray-800 dark:bg-white/[0.03] flex flex-col justify-between">
            <div className="flex items-center justify-between border-b border-gray-100 dark:border-gray-800/80 pb-3">
              <div>
                <h3 className="text-sm font-bold text-gray-900 dark:text-white flex items-center gap-2">
                  <RefreshCw className="h-4 w-4 text-brand-500" />
                  <span>Status Pembaruan Sistem</span>
                </h3>
                <p className="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                  Pemeriksaan berkas rilis resmi GitHub
                </p>
              </div>
              <div>
                {hasUpdate ? (
                  <span className="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-bold bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400">
                    <span className="h-1.5 w-1.5 rounded-full bg-amber-500 animate-pulse" />
                    Update Tersedia
                  </span>
                ) : (
                  <span className="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-bold bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400">
                    <span className="h-1.5 w-1.5 rounded-full bg-emerald-500" />
                    Versi Mutakhir
                  </span>
                )}
              </div>
            </div>

            <div className="pt-3 space-y-2">
              <div className="flex items-center justify-between rounded-xl bg-gray-50/80 p-2.5 dark:bg-gray-900/50 border border-gray-100 dark:border-gray-800/60">
                <span className="text-xs text-gray-500 dark:text-gray-400 flex items-center gap-1.5">
                  <FileCode className="h-3.5 w-3.5 text-brand-500" /> Versi Terpasang
                </span>
                <span className="font-bold text-gray-900 dark:text-white text-xs">v{current_version} (Lokal)</span>
              </div>
              <div className="flex items-center justify-between rounded-xl bg-gray-50/80 p-2.5 dark:bg-gray-900/50 border border-gray-100 dark:border-gray-800/60">
                <span className="text-xs text-gray-500 dark:text-gray-400 flex items-center gap-1.5">
                  <DownloadCloud className="h-3.5 w-3.5 text-emerald-500" /> Rilis GitHub Terkini
                </span>
                <span className="font-bold text-emerald-600 dark:text-emerald-400 text-xs">v{remoteVersion}</span>
              </div>
              <div className="flex items-center justify-between rounded-xl bg-gray-50/80 p-2.5 dark:bg-gray-900/50 border border-gray-100 dark:border-gray-800/60">
                <span className="text-xs text-gray-500 dark:text-gray-400 flex items-center gap-1.5">
                  <Clock className="h-3.5 w-3.5 text-purple-500" /> Pemeriksaan Terakhir
                </span>
                <span className="font-medium text-gray-700 dark:text-gray-300 text-xs">{lastChecked}</span>
              </div>
            </div>
          </div>

          {/* Card 2: Lisensi & Kuota ISP Standalone (7 Cols) */}
          <div className="lg:col-span-7 rounded-2xl border border-gray-200 bg-white p-4 sm:p-5 shadow-xs dark:border-gray-800 dark:bg-white/[0.03] flex flex-col justify-between">
            <div className="flex items-center justify-between border-b border-gray-100 dark:border-gray-800/80 pb-3">
              <div>
                <h3 className="text-sm font-bold text-gray-900 dark:text-white flex items-center gap-2">
                  <KeyRound className="h-4 w-4 text-emerald-500" />
                  <span>Lisensi &amp; Kuota ISP Standalone</span>
                </h3>
                <p className="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                  Manajemen lisensi offline dan batasan router &amp; pelanggan
                </p>
              </div>
              <div>
                <button
                  type="button"
                  onClick={() => {
                    setLicenseMessage(null)
                    setLicenseModalOpen(true)
                  }}
                  className="h-8 w-8 inline-flex items-center justify-center rounded-lg border border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:hover:bg-gray-800 text-gray-600 hover:text-brand-600 dark:text-gray-400 dark:hover:text-brand-400 transition shadow-2xs cursor-pointer"
                  title="Ganti Kunci Lisensi"
                >
                  <Pencil className="h-3.5 w-3.5" />
                </button>
              </div>
            </div>

            <div className="pt-3 space-y-2">
              {/* Row 1: License Key Bar */}
              <div className="flex items-center justify-between rounded-xl bg-gray-50/80 p-2.5 dark:bg-gray-900/50 border border-gray-100 dark:border-gray-800/60">
                <div className="flex items-center gap-2 min-w-0">
                  <span className="text-xs text-gray-500 dark:text-gray-400 shrink-0">Kunci Lisensi:</span>
                  <code className="text-xs font-bold text-gray-900 dark:text-white truncate bg-white dark:bg-gray-800 px-2 py-0.5 rounded border border-gray-200 dark:border-gray-700">
                    {activeKey}
                  </code>
                </div>
                <button
                  type="button"
                  onClick={handleCopyKey}
                  className="inline-flex items-center gap-1 text-[11px] font-semibold text-gray-500 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-200 cursor-pointer px-1.5 py-0.5"
                >
                  {copiedKey ? <Check className="h-3.5 w-3.5 text-emerald-500" /> : <Copy className="h-3.5 w-3.5" />}
                  <span>{copiedKey ? "Disalin" : "Salin"}</span>
                </button>
              </div>

              {/* Row 2: Customer Quota */}
              <div className="rounded-xl bg-gray-50/80 p-2.5 dark:bg-gray-900/50 border border-gray-100 dark:border-gray-800/60">
                <div className="flex items-center justify-between text-xs mb-1">
                  <span className="text-gray-500 dark:text-gray-400 flex items-center gap-1.5">
                    <Users className="h-3.5 w-3.5 text-brand-500" /> Kuota Pelanggan
                  </span>
                  <span className="font-bold text-gray-900 dark:text-white tabular-nums">
                    {custCount} / {isFreeTier ? "35 Pelanggan" : "Unlimited"}
                  </span>
                </div>
                {isFreeTier && (
                  <div className="h-1.5 w-full rounded-full bg-gray-200 dark:bg-gray-800 overflow-hidden">
                    <div
                      className={`h-full rounded-full transition-all ${
                        custPercent >= 90 ? "bg-rose-500" : custPercent >= 70 ? "bg-amber-500" : "bg-brand-500"
                      }`}
                      style={{ width: `${Math.max(4, custPercent)}%` }}
                    />
                  </div>
                )}
              </div>

              {/* Row 3: Router MikroTik */}
              <div className="rounded-xl bg-gray-50/80 p-2.5 dark:bg-gray-900/50 border border-gray-100 dark:border-gray-800/60 flex items-center justify-between">
                <span className="text-xs text-gray-500 dark:text-gray-400 flex items-center gap-1.5">
                  <RouterIcon className="h-3.5 w-3.5 text-purple-500" /> Router MikroTik
                </span>
                <span className="font-bold text-gray-900 dark:text-white text-xs tabular-nums">
                  {routerCount} / {isFreeTier ? "1 Router" : "Unlimited"}
                </span>
              </div>
            </div>
          </div>
        </div>

        {/* ── 1-LINE RESPONSIVE INTEGRATED TOOLBAR ── */}
        <div className="rounded-2xl border border-gray-200 bg-white p-3 sm:p-4 shadow-xs dark:border-gray-800 dark:bg-white/[0.03] flex flex-col gap-2.5 sm:gap-3 lg:flex-row lg:items-center lg:justify-between">
          <div className="flex items-center gap-2 min-w-0">
            <span className="text-xs text-gray-600 dark:text-gray-400 font-medium">
              {hasUpdate ? (
                <span className="text-amber-600 dark:text-amber-400 font-bold flex items-center gap-1.5">
                  <AlertTriangle className="h-4 w-4" /> Versi baru v{remoteVersion} siap dipasang. Klik tombol perbarui untuk mengeksekusi.
                </span>
              ) : (
                <span className="text-gray-600 dark:text-gray-400 flex items-center gap-1.5">
                  <CheckCircle2 className="h-4 w-4 text-emerald-500" /> Instalasi lokal Anda telah berada pada versi rilis paling mutakhir.
                </span>
              )}
            </span>
          </div>

          <div className="flex items-center gap-2 shrink-0 w-full sm:w-auto justify-end">
            <button
              type="button"
              onClick={handleCheckUpdate}
              disabled={checking || updating}
              className="h-10 inline-flex items-center gap-1.5 px-3.5 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:hover:bg-gray-800 text-xs font-semibold text-gray-700 dark:text-gray-200 transition shadow-2xs cursor-pointer disabled:opacity-50 shrink-0"
              title="Periksa Pembaruan"
            >
              <RefreshCw className={`h-4 w-4 text-brand-500 ${checking ? "animate-spin" : ""}`} />
              <span>{checking ? "Memeriksa..." : "Periksa Pembaruan"}</span>
            </button>

            {hasUpdate && (
              <button
                type="button"
                onClick={handlePerformUpdate}
                disabled={updating}
                className="h-10 px-4 rounded-xl bg-emerald-600 hover:bg-emerald-700 active:scale-95 text-xs font-bold text-white shadow-xs flex items-center justify-center gap-1.5 w-full sm:w-auto shrink-0 cursor-pointer disabled:opacity-50"
              >
                <ArrowUpCircle className={`h-4 w-4 ${updating ? "animate-spin" : ""}`} />
                <span>{updating ? "Memperbarui..." : "Perbarui Sekarang (1-Klik)"}</span>
              </button>
            )}
          </div>
        </div>

        {/* ── MAIN CONTENT GRID ── */}
        <div className="grid grid-cols-1 lg:grid-cols-12 gap-4 sm:gap-6">
          {/* Kolom Kiri: Catatan Rilis & Log Eksekusi (8 Cols) */}
          <div className="lg:col-span-8 space-y-4 sm:space-y-5">
            {/* Card Catatan Rilis */}
            <div className="rounded-2xl border border-gray-200 bg-white p-4 sm:p-5 shadow-xs dark:border-gray-800 dark:bg-white/[0.03]">
              <div className="border-b border-gray-100 dark:border-gray-800/80 pb-3 mb-4 flex items-center justify-between">
                <div>
                  <h3 className="text-sm font-bold text-gray-900 dark:text-white flex items-center gap-2">
                    <DownloadCloud className="h-4 w-4 text-brand-500" />
                    <span>Catatan Rilis &amp; Fitur Terbaru</span>
                  </h3>
                  <p className="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                    Daftar perbaikan bug dan peningkatan performa sistem
                  </p>
                </div>
                <span className="text-xs font-semibold text-gray-500 dark:text-gray-400">
                  {releaseDate ? `Rilis: ${releaseDate}` : "Versi Stabil"}
                </span>
              </div>

              <div className="space-y-2.5">
                {releaseNotes.map((note, idx) => (
                  <div key={idx} className="flex items-start gap-2.5 text-xs text-gray-700 dark:text-gray-300">
                    <CheckCircle2 className="h-4 w-4 text-emerald-500 shrink-0 mt-0.5" />
                    <span className="leading-relaxed">{note}</span>
                  </div>
                ))}
              </div>
            </div>

            {/* Card Terminal Log (Saat Update Berjalan / Selesai) */}
            {logs.length > 0 && (
              <div className="rounded-2xl border border-gray-200 bg-white p-4 sm:p-5 shadow-xs dark:border-gray-800 dark:bg-white/[0.03]">
                <div className="border-b border-gray-100 dark:border-gray-800/80 pb-3 mb-3 flex items-center justify-between">
                  <h3 className="text-sm font-bold text-gray-900 dark:text-white flex items-center gap-2">
                    <Terminal className="h-4 w-4 text-purple-500" />
                    <span>Log Eksekusi Pembaruan</span>
                  </h3>
                  {updateSuccess && (
                    <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-bold bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400">
                      Selesai 100%
                    </span>
                  )}
                </div>

                <div className="rounded-xl bg-gray-900 text-gray-100 p-3.5 font-mono text-[11px] leading-relaxed max-h-48 overflow-y-auto space-y-1">
                  {logs.map((log, i) => (
                    <div key={i} className="text-emerald-400">
                      &gt; {log}
                    </div>
                  ))}
                </div>
              </div>
            )}
          </div>

          {/* Kolom Kanan: Panduan Windows & Layanan Bantuan (4 Cols) */}
          <div className="lg:col-span-4 space-y-4 sm:space-y-5">
            {/* Card Jaminan Keamanan Berkas */}
            <div className="rounded-2xl border border-gray-200 bg-white p-4 sm:p-5 shadow-xs dark:border-gray-800 dark:bg-white/[0.03]">
              <div className="border-b border-gray-100 dark:border-gray-800/80 pb-3 mb-3.5">
                <h3 className="text-sm font-bold text-gray-900 dark:text-white flex items-center gap-2">
                  <ShieldCheck className="h-4 w-4 text-emerald-500" />
                  <span>Jaminan Zero Data Loss</span>
                </h3>
                <p className="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                  Proteksi otomatis saat pembaruan berkas
                </p>
              </div>

              <div className="space-y-2 text-xs text-gray-600 dark:text-gray-400">
                <div className="flex items-center gap-2 p-2 rounded-lg bg-gray-50 dark:bg-gray-900/50 border border-gray-100 dark:border-gray-800/60">
                  <HardDrive className="h-3.5 w-3.5 text-brand-500 shrink-0" />
                  <span>Database SQLite otomatis dibackup.</span>
                </div>
                <div className="flex items-center gap-2 p-2 rounded-lg bg-gray-50 dark:bg-gray-900/50 border border-gray-100 dark:border-gray-800/60">
                  <Lock className="h-3.5 w-3.5 text-emerald-500 shrink-0" />
                  <span>Berkas <code>.env</code> tidak akan ditimpa.</span>
                </div>
                <div className="flex items-center gap-2 p-2 rounded-lg bg-gray-50 dark:bg-gray-900/50 border border-gray-100 dark:border-gray-800/60">
                  <FolderArchive className="h-3.5 w-3.5 text-purple-500 shrink-0" />
                  <span>File logo &amp; invoice uploads aman 100%.</span>
                </div>
              </div>
            </div>

            {/* Card Bantuan CS & Upgrade */}
            <div className="rounded-2xl border border-gray-200 bg-white p-4 sm:p-5 shadow-xs dark:border-gray-800 dark:bg-white/[0.03]">
              <div className="border-b border-gray-100 dark:border-gray-800/80 pb-3 mb-3.5">
                <h3 className="text-sm font-bold text-gray-900 dark:text-white flex items-center gap-2">
                  <MessageSquare className="h-4 w-4 text-emerald-500" />
                  <span>Bantuan &amp; Lisensi Resmi</span>
                </h3>
                <p className="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                  Konsultasi teknis &amp; upgrade kuota unlimited
                </p>
              </div>

              <div className="space-y-2">
                <a
                  href="https://wa.me/6285155173547?text=Halo%20Admin%20NODERA,%20saya%20ingin%20upgrade%20lisensi%20Billing%20Standalone"
                  target="_blank"
                  rel="noreferrer"
                  className="w-full h-10 rounded-xl bg-emerald-600 hover:bg-emerald-700 active:scale-95 text-xs font-bold text-white shadow-xs flex items-center justify-center gap-2 transition"
                >
                  <MessageSquare className="h-4 w-4" />
                  <span>Hubungi CS WhatsApp Resmi</span>
                </a>

                <a
                  href="https://panel.dgtlnetsolution.com/desktop-licenses"
                  target="_blank"
                  rel="noreferrer"
                  className="w-full h-9 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:hover:bg-gray-800 text-xs font-semibold text-gray-700 dark:text-gray-300 shadow-2xs flex items-center justify-center gap-1.5 transition"
                >
                  <ExternalLink className="h-3.5 w-3.5 text-brand-500" />
                  <span>Portal Lisensi NODERA</span>
                </a>
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* ── MODAL GANTI / AKTIVASI LISENSI RESMI ── */}
      {licenseModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-xs p-4">
          <div className="relative w-full max-w-md rounded-2xl border border-gray-200 bg-white p-5 sm:p-6 shadow-xl dark:border-gray-800 dark:bg-gray-900 animate-in fade-in zoom-in-95 duration-150">
            <div className="flex items-center justify-between border-b border-gray-100 dark:border-gray-800 pb-3 mb-4">
              <div className="flex items-center gap-2">
                <KeyRound className="h-5 w-5 text-emerald-500" />
                <h3 className="text-base font-bold text-gray-900 dark:text-white">
                  Aktivasi / Ganti Lisensi Resmi
                </h3>
              </div>
              <button
                type="button"
                onClick={() => setLicenseModalOpen(false)}
                className="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 cursor-pointer p-1"
              >
                <X className="h-5 w-5" />
              </button>
            </div>

            <form onSubmit={handleSaveLicense} className="space-y-4">
              <div>
                <label className="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-1.5">
                  Kode Kunci Lisensi (License Key)
                </label>
                <input
                  type="text"
                  required
                  value={inputLicenseKey}
                  onChange={(e) => setInputLicenseKey(e.target.value)}
                  placeholder="Contoh: NODERA-PRO-XXXX-XXXX-XXXX"
                  className="h-10 w-full rounded-xl border border-gray-200 bg-gray-50/75 px-3.5 font-mono text-xs text-gray-900 placeholder:text-gray-400 focus:border-brand-500 focus:outline-none dark:border-gray-800 dark:bg-gray-800 dark:text-white font-semibold"
                />
                <p className="text-[11px] text-gray-500 dark:text-gray-400 mt-1">
                  Masukkan kunci lisensi yang Anda peroleh dari portal resmi NODERA atau CS WhatsApp. Kosongkan untuk kembali ke Free Tier (35 pelanggan).
                </p>
              </div>

              {licenseMessage && (
                <div
                  className={`p-3 rounded-xl text-xs font-semibold flex items-center gap-2 ${
                    licenseMessage.type === "success"
                      ? "bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800"
                      : "bg-rose-50 text-rose-700 dark:bg-rose-500/10 dark:text-rose-400 border border-rose-200 dark:border-rose-800"
                  }`}
                >
                  {licenseMessage.type === "success" ? (
                    <CheckCircle2 className="h-4 w-4 shrink-0" />
                  ) : (
                    <AlertTriangle className="h-4 w-4 shrink-0" />
                  )}
                  <span>{licenseMessage.text}</span>
                </div>
              )}

              <div className="flex items-center justify-end gap-2 pt-2 border-t border-gray-100 dark:border-gray-800">
                <button
                  type="button"
                  onClick={() => setLicenseModalOpen(false)}
                  className="h-9 px-3.5 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-800 text-xs font-semibold text-gray-700 dark:text-gray-300 transition shadow-2xs cursor-pointer"
                >
                  Batal
                </button>

                <button
                  type="submit"
                  disabled={savingLicense}
                  className="h-9 px-4 rounded-xl bg-brand-500 hover:bg-brand-600 active:scale-95 text-xs font-bold text-white shadow-xs flex items-center justify-center gap-1.5 transition cursor-pointer disabled:opacity-50"
                >
                  {savingLicense ? <RefreshCw className="h-3.5 w-3.5 animate-spin" /> : <CheckCircle2 className="h-3.5 w-3.5" />}
                  <span>{savingLicense ? "Memverifikasi..." : "Simpan & Verifikasi"}</span>
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </AppLayout>
  )
}
