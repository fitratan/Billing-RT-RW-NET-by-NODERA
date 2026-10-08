import { useState } from "react"
import {
  Sparkles,
  RefreshCw,
  CheckCircle2,
  ArrowUpCircle,
  Terminal,
  AlertCircle,
  Info,
  Check,
  ShieldCheck,
  ChevronDown,
  Key,
  Copy,
  Edit3,
  ExternalLink,
  ShieldAlert,
  Server,
} from "lucide-react"
import { cn } from "@/lib/utils"

interface SystemVersion {
  version: string
  release_date?: string
  codename?: string
  changelog?: string[]
  last_updated_at?: string
  is_standalone?: boolean
  license_key?: string
  has_license?: boolean
  license_server?: string
  license_status?: string
}

interface UpdateCheckResult {
  current_version: string
  latest_version: string
  update_available: boolean
  codename?: string
  changelog: string[]
  checked_at: string
}

interface SystemUpdaterCardProps {
  initialVersion?: SystemVersion
  checkUrl?: string
  updateUrl?: string
  saveLicenseUrl?: string
  isOpen?: boolean
  onToggle?: () => void
  isAccordion?: boolean
}

export function SystemUpdaterCard({
  initialVersion = { version: "2.6.0", codename: "Enterprise ISP Edition" },
  checkUrl = "/admin/my-settings/check-update",
  updateUrl = "/admin/my-settings/system-update",
  saveLicenseUrl = "/admin/my-settings/save-license",
  isOpen,
  onToggle,
  isAccordion = true,
}: SystemUpdaterCardProps) {
  const [internalOpen, setInternalOpen] = useState(false)

  const isExpanded = isAccordion ? (isOpen !== undefined ? isOpen : internalOpen) : true

  const handleToggle = () => {
    if (!isAccordion) return
    if (onToggle) {
      onToggle()
    } else {
      setInternalOpen(!internalOpen)
    }
  }

  const [checking, setChecking] = useState(false)
  const [updating, setUpdating] = useState(false)
  const [updateCompleted, setUpdateCompleted] = useState(false)
  const [updateInfo, setUpdateInfo] = useState<UpdateCheckResult | null>(null)
  const [updateLogs, setUpdateLogs] = useState<string[]>([])
  const [statusMessage, setStatusMessage] = useState<{ type: "success" | "error" | "info"; text: string } | null>(null)
  const [currentVer, setCurrentVer] = useState(initialVersion.version || "2.6.0")

  // License Management State
  const [licenseKey, setLicenseKey] = useState(initialVersion.license_key || "")
  const [inputKey, setInputKey] = useState(initialVersion.license_key || "")
  const [isEditingLicense, setIsEditingLicense] = useState(false)
  const [savingLicense, setSavingLicense] = useState(false)
  const [copiedKey, setCopiedKey] = useState(false)

  const handleCopyLicense = (e?: React.MouseEvent) => {
    e?.stopPropagation()
    if (!licenseKey) return
    navigator.clipboard.writeText(licenseKey)
    setCopiedKey(true)
    setTimeout(() => setCopiedKey(false), 2000)
  }

  const handleSaveLicense = async (e: React.FormEvent) => {
    e.preventDefault()
    setSavingLicense(true)
    setStatusMessage(null)

    try {
      const token = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content
      const endpoint = saveLicenseUrl || (checkUrl.includes("/superadmin") ? "/superadmin/settings/save-license" : "/admin/my-settings/save-license")

      const res = await fetch(endpoint, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "Accept": "application/json",
          "X-CSRF-TOKEN": token || "",
        },
        body: JSON.stringify({ license_key: inputKey }),
      })

      const data = await res.json()
      if (res.ok && data.success) {
        setLicenseKey(data.license_key || inputKey)
        setIsEditingLicense(false)
        setStatusMessage({
          type: "success",
          text: data.message || "Nomor lisensi berhasil disimpan & diverifikasi!",
        })
      } else {
        setStatusMessage({
          type: "error",
          text: data.message || "Gagal memverifikasi nomor lisensi",
        })
      }
    } catch (err: any) {
      setStatusMessage({
        type: "error",
        text: err.message || "Terjadi kesalahan saat menyimpan nomor lisensi",
      })
    } finally {
      setSavingLicense(false)
    }
  }

  const handleCheckUpdate = async (e?: React.MouseEvent) => {
    e?.stopPropagation()
    setChecking(true)
    setStatusMessage(null)
    try {
      const res = await fetch(checkUrl, {
        headers: { Accept: "application/json" },
      })
      if (!res.ok) throw new Error("Gagal memeriksa server pembaruan")
      const data: UpdateCheckResult = await res.json()
      setUpdateInfo(data)
      if (data.update_available) {
        setStatusMessage({
          type: "info",
          text: `Versi baru v${data.latest_version} tersedia! Silakan klik 'Perbarui Sistem'.`,
        })
      } else {
        setStatusMessage({
          type: "success",
          text: `Sistem Anda sudah menggunakan versi paling mutakhir (v${data.current_version}). Tidak ada pembaruan baru.`,
        })
      }
    } catch (err: any) {
      setStatusMessage({
        type: "error",
        text: err.message || "Gagal menghubungi server update",
      })
    } finally {
      setChecking(false)
    }
  }

  const handleExecuteUpdate = async (e?: React.MouseEvent) => {
    e?.stopPropagation()
    if (!confirm("Apakah Anda yakin ingin memperbarui sistem sekarang? Aplikasi akan melakukan migrasi database dan pembersihan cache otomatis.")) {
      return
    }

    setUpdating(true)
    setUpdateLogs(["Memulai proses pembaruan sistem..."])
    setStatusMessage(null)

    try {
      const token = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content
      const res = await fetch(updateUrl, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "Accept": "application/json",
          "X-CSRF-TOKEN": token || "",
        },
      })

      const data = await res.json()
      if (data.logs && Array.isArray(data.logs)) {
        setUpdateLogs(data.logs)
      }

      if (res.ok && data.success) {
        setCurrentVer(data.version || currentVer)
        setUpdateCompleted(true)
        setStatusMessage({
          type: "success",
          text: data.message || "Sistem berhasil diperbarui!",
        })
        setUpdateInfo(null)
      } else {
        setStatusMessage({
          type: "error",
          text: data.message || "Pembaruan gagal dijalankan",
        })
      }
    } catch (err: any) {
      setStatusMessage({
        type: "error",
        text: err.message || "Terjadi kesalahan saat mengeksekusi update",
      })
    } finally {
      setUpdating(false)
    }
  }

  return (
    <div className="rounded-2xl border border-[#212B3B] bg-[#121720] overflow-hidden text-white select-none">
      {/* Header Button for Accordion */}
      <button
        type="button"
        onClick={handleToggle}
        className="flex w-full items-center justify-between p-4 sm:p-5 text-left hover:bg-[#18202C] transition-colors gap-3"
      >
        <div className="flex items-center gap-3.5 min-w-0 flex-1">
          <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#0B2138] border border-[#0073C6]/40 text-[#00C2FF]">
            <Sparkles className="h-5 w-5" />
          </div>
          <div className="min-w-0 flex-1">
            <div className="flex flex-wrap items-center gap-2">
              <h3 className="font-display text-sm font-bold text-white">{initialVersion.is_standalone ? "Pembaruan & Lisensi Sistem" : "Pembaruan Sistem"}</h3>
              {isExpanded && (
                <>
                  <span className="font-mono text-[10px] font-bold border border-[#0073C6]/40 bg-[#0B2138] text-[#00C2FF] px-2 py-0.5 rounded-md uppercase tracking-wider">
                    v{currentVer}
                  </span>
                  {initialVersion.is_standalone && (
                    licenseKey ? (
                      <span className="font-mono text-[10px] font-bold border border-emerald-500/40 bg-emerald-500/10 text-emerald-400 px-2 py-0.5 rounded-md flex items-center gap-1">
                        <ShieldCheck className="h-3 w-3" /> Lisensi Aktif
                      </span>
                    ) : (
                      <span className="font-mono text-[10px] font-bold border border-amber-500/40 bg-amber-500/10 text-amber-300 px-2 py-0.5 rounded-md flex items-center gap-1">
                        <ShieldAlert className="h-3 w-3" /> Belum Berlisensi
                      </span>
                    )
                  )}
                </>
              )}
            </div>
            <p className="text-xs text-slate-400 mt-0.5 truncate">
              {initialVersion.is_standalone ? "Kelola nomor lisensi NODERA, pembaruan otomatis, modul & database" : "Deploy pembaruan otomatis sistem, modul & database"}
            </p>
          </div>
        </div>

        {isAccordion && (
          <div
            className={cn(
              "p-1 rounded-lg text-slate-500 transition-transform duration-200 shrink-0",
              isExpanded && "rotate-180 text-slate-300"
            )}
          >
            <ChevronDown className="h-4 w-4" />
          </div>
        )}
      </button>

      {/* Expanded Content Body */}
      {isExpanded && (
        <div className="border-t border-[#1E2633] p-4 sm:p-5 bg-[#121720] space-y-4 animate-in fade-in-50 duration-200">

          {/* LICENSE KEY CARD BOX (ONLY FOR STANDALONE) */}
          {initialVersion.is_standalone && (
          <div className="rounded-xl border border-border/60 bg-[#161C26] p-4 space-y-3">
            <div className="flex flex-wrap items-center justify-between gap-2 border-b border-border/40 pb-2.5">
              <div className="flex items-center gap-2">
                <Key className="w-4 h-4 text-[#00C2FF]" />
                <span className="text-xs font-bold uppercase tracking-wider text-slate-300">
                  Nomor Lisensi Aplikasi (NODERA License Key)
                </span>
              </div>
              <div className="flex items-center gap-2">
                <button
                  type="button"
                  onClick={() => setIsEditingLicense(!isEditingLicense)}
                  className="text-xs text-[#00C2FF] hover:underline flex items-center gap-1 font-semibold"
                >
                  <Edit3 className="w-3.5 h-3.5" />
                  {isEditingLicense ? "Tutup Form" : (licenseKey ? "Ganti Lisensi" : "Masukkan Lisensi")}
                </button>
              </div>
            </div>

            {/* License Key Display & Copy */}
            {!isEditingLicense ? (
              <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2.5 bg-[#0D1219] p-3 rounded-lg border border-border/40">
                <div className="space-y-0.5 min-w-0">
                  <div className="flex items-center gap-2">
                    <span className="font-mono text-sm font-bold text-white tracking-wider select-all truncate">
                      {licenseKey || "— Belum ada lisensi terpasang —"}
                    </span>
                    {licenseKey && (
                      <button
                        type="button"
                        onClick={handleCopyLicense}
                        className="text-xs text-primary font-bold hover:text-white px-2 py-0.5 rounded bg-primary/20 hover:bg-primary/30 flex items-center gap-1 shrink-0 transition-colors"
                      >
                        {copiedKey ? <Check className="w-3.5 h-3.5 text-emerald-400" /> : <Copy className="w-3.5 h-3.5" />}
                        {copiedKey ? "Tersalin" : "Salin"}
                      </button>
                    )}
                  </div>
                  <p className="text-[11px] text-slate-400">
                    Server Pusat: <span className="font-mono text-slate-300">{initialVersion.license_server || "https://panel.dgtlnetsolution.com"}</span>
                  </p>
                </div>

                <div className="flex items-center gap-2 shrink-0">
                  <span className={cn(
                    "text-xs font-bold px-2.5 py-1 rounded-full border flex items-center gap-1.5",
                    licenseKey
                      ? "bg-emerald-500/10 text-emerald-400 border-emerald-500/30"
                      : "bg-amber-500/10 text-amber-300 border-amber-500/30"
                  )}>
                    <span className={cn("w-2 h-2 rounded-full", licenseKey ? "bg-emerald-400" : "bg-amber-400 animate-pulse")} />
                    {licenseKey ? "Aktif & Terverifikasi" : "Belum Memiliki Lisensi"}
                  </span>
                </div>
              </div>
            ) : (
              /* License Key Edit Form */
              <form onSubmit={handleSaveLicense} className="space-y-3 bg-[#0D1219] p-3.5 rounded-lg border border-border/40">
                <div className="space-y-1">
                  <label className="text-xs font-semibold text-slate-300">
                    Masukkan License Key NODERA (format NDR-ISP-XXXX-XXXX / 32 Karakter)
                  </label>
                  <input
                    type="text"
                    value={inputKey}
                    onChange={(e) => setInputKey(e.target.value)}
                    placeholder="NDR-ISP-XXXX-XXXX-XXXX"
                    className="w-full font-mono text-xs px-3 py-2 rounded-lg bg-[#161C26] border border-border text-white focus:outline-none focus:border-[#00C2FF]"
                    required
                  />
                  <p className="text-[11px] text-slate-400">
                    Lisensi ini akan dikaitkan dengan instance standalone dan digunakan untuk memvalidasi hak akses serta menerima pembaruan sistem.
                  </p>
                </div>
                <div className="flex items-center justify-end gap-2">
                  <button
                    type="button"
                    onClick={() => setIsEditingLicense(false)}
                    className="px-3 py-1.5 rounded-lg text-xs font-medium text-slate-400 hover:text-white border border-border hover:bg-[#161C26]"
                  >
                    Batal
                  </button>
                  <button
                    type="submit"
                    disabled={savingLicense}
                    className="px-4 py-1.5 rounded-lg text-xs font-bold bg-[#0073C6] hover:bg-[#0084E3] text-white flex items-center gap-1.5 disabled:opacity-50"
                  >
                    {savingLicense ? <RefreshCw className="w-3.5 h-3.5 animate-spin" /> : <Check className="w-3.5 h-3.5" />}
                    <span>{savingLicense ? "Menyimpan..." : "Simpan & Verifikasi"}</span>
                  </button>
                </div>
              </form>
            )}
          </div>
          )}

          {/* Action Buttons Row */}
          <div className="flex flex-wrap items-center justify-between gap-2.5 pb-1">
            <div className="text-xs text-slate-400 font-medium">
              Edisi Platform: <span className="text-slate-200 font-bold">{initialVersion.codename || "NODERA Enterprise ISP Edition"}</span>
            </div>

            <div className="flex items-center gap-2 flex-wrap">
              <button
                type="button"
                onClick={handleCheckUpdate}
                disabled={checking || updating}
                className="flex h-9 items-center gap-1.5 rounded-xl border border-[#212B3B] bg-[#161B22] hover:bg-[#1C232E] hover:border-[#2F3D52] text-slate-200 hover:text-white px-3.5 text-xs font-bold transition-all active:scale-95 disabled:opacity-50"
              >
                <RefreshCw className={cn("h-3.5 w-3.5 text-[#00C2FF]", checking && "animate-spin")} />
                <span>{checking ? "Memeriksa..." : "Cek Pembaruan"}</span>
              </button>

              <button
                type="button"
                onClick={handleExecuteUpdate}
                disabled={updating || (updateInfo !== null && !updateInfo.update_available)}
                className="flex h-9 items-center gap-1.5 rounded-xl bg-[#0073C6] hover:bg-[#0084E3] border border-[#0094FF]/40 text-white px-4 text-xs font-bold transition-all active:scale-95 disabled:opacity-40"
              >
                <ArrowUpCircle className={cn("h-3.5 w-3.5", updating && "animate-spin")} />
                <span>{updating ? "Memperbarui..." : "Perbarui Sistem"}</span>
              </button>
            </div>
          </div>

          {/* Status Message Alert */}
          {statusMessage && (
            <div
              className={cn(
                "flex items-start gap-2.5 rounded-xl border p-3.5 text-xs font-medium",
                statusMessage.type === "success" && "border-emerald-500/30 bg-emerald-500/10 text-emerald-400",
                statusMessage.type === "error" && "border-rose-500/30 bg-rose-500/10 text-rose-400",
                statusMessage.type === "info" && "border-[#0073C6]/40 bg-[#0B2138] text-[#00C2FF]"
              )}
            >
              {statusMessage.type === "success" && <CheckCircle2 className="h-4 w-4 shrink-0 mt-0.5 text-emerald-400" />}
              {statusMessage.type === "error" && <AlertCircle className="h-4 w-4 shrink-0 mt-0.5 text-rose-400" />}
              {statusMessage.type === "info" && <Info className="h-4 w-4 shrink-0 mt-0.5 text-[#00C2FF]" />}
              <span className="leading-relaxed">{statusMessage.text}</span>
            </div>
          )}

          {/* Incoming Update Info */}
          {updateInfo && updateInfo.update_available && (
            <div className="rounded-xl border border-[#0073C6]/40 bg-[#0B2138]/60 p-4 space-y-2">
              <div className="flex items-center justify-between text-xs">
                <span className="font-bold text-[#00C2FF] flex items-center gap-1.5">
                  <Sparkles className="h-3.5 w-3.5" /> Versi Pembaruan Tersedia
                </span>
                <span className="border border-[#0073C6]/40 bg-[#0073C6] text-white font-mono text-[11px] font-bold px-2 py-0.5 rounded-md">
                  v{updateInfo.latest_version}
                </span>
              </div>

              {updateInfo.changelog && updateInfo.changelog.length > 0 && (
                <div className="space-y-1.5 border-t border-[#0073C6]/20 pt-2.5 mt-2">
                  <p className="text-[11px] font-bold uppercase tracking-wider text-slate-400">Catatan Pembaruan:</p>
                  <ul className="space-y-1 text-xs text-slate-300 list-disc list-inside">
                    {updateInfo.changelog.map((item, idx) => (
                      <li key={idx} className="leading-relaxed">{item}</li>
                    ))}
                  </ul>
                </div>
              )}
            </div>
          )}

          {/* Update Logs Terminal Output */}
          {updateLogs.length > 0 && (
            <div className="rounded-xl border border-[#1E2633] bg-[#0B0E14] p-3.5 text-xs font-mono text-emerald-400 overflow-hidden shadow-inner">
              <div className="flex items-center gap-1.5 border-b border-[#1E2633] pb-2 mb-2 text-[11px] text-slate-400">
                <Terminal className="h-3.5 w-3.5 text-[#00C2FF]" />
                <span className="font-bold font-sans text-slate-300">Log Eksekusi Pembaruan Sistem</span>
              </div>
              <div className="space-y-1 max-h-48 overflow-y-auto overflow-x-auto break-all whitespace-pre-wrap">
                {updateLogs.map((log, idx) => (
                  <div key={idx} className="leading-relaxed">
                    <span className="text-slate-500 select-none">&gt; </span>
                    {log}
                  </div>
                ))}
              </div>
            </div>
          )}

          {/* Detail Versi Terpasang */}
          {updateCompleted && (
            <div className="rounded-xl border border-emerald-500/30 bg-emerald-500/10 p-4 space-y-2 animate-in fade-in duration-300">
              <div className="flex items-center justify-between text-xs">
                <div className="flex items-center gap-1.5 font-bold text-emerald-400">
                  <Check className="h-4 w-4" />
                  <span>Detail Versi Terpasang (Terbaru)</span>
                </div>
                <span className="text-slate-400 font-mono text-[11px]">
                  {initialVersion.release_date ? `Rilis: ${initialVersion.release_date}` : "Stabil"}
                </span>
              </div>

              <p className="text-xs text-slate-300">
                Edisi: <strong className="text-white">{initialVersion.codename || "NODERA Enterprise ISP Edition"}</strong> · Versi: <strong className="font-mono text-[#00C2FF]">v{currentVer}</strong>
              </p>

              {initialVersion.changelog && initialVersion.changelog.length > 0 && (
                <div className="space-y-1 border-t border-emerald-500/20 pt-2.5">
                  <p className="text-[11px] font-bold uppercase tracking-wider text-slate-400">Fitur &amp; Modul Aktif:</p>
                  <ul className="space-y-1 text-xs text-slate-300 list-disc list-inside">
                    {initialVersion.changelog.map((item, idx) => (
                      <li key={idx} className="leading-relaxed">{item}</li>
                    ))}
                  </ul>
                </div>
              )}
            </div>
          )}
        </div>
      )}
    </div>
  )
}
