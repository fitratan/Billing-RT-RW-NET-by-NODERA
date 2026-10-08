import * as React from "react"
import {
  CalendarClock,
  Check,
  X,
  Loader2,
  Sparkles,
  AlertCircle,
  Calendar,
  Clock,
  Bell,
} from "lucide-react"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { cn } from "@/lib/utils"

export function AutoInvoiceSettingsModal() {
  const [open, setOpen] = React.useState(false)
  const [loading, setLoading] = React.useState(false)
  const [saving, setSaving] = React.useState(false)
  const [generating, setGenerating] = React.useState(false)
  const [saved, setSaved] = React.useState(false)
  const [msg, setMsg] = React.useState<{ type: "success" | "error"; text: string } | null>(null)

  const [settings, setSettings] = React.useState<{
    is_auto: boolean
    generate_day: number
    due_day: number
    auto_wa: boolean
    reminder_auto: boolean
    reminder_days: number[]
    total_active_customers: number
    current_month_generated_count: number
    current_month_label: string
  }>({
    is_auto: true,
    generate_day: 1,
    due_day: 20,
    auto_wa: true,
    reminder_auto: true,
    reminder_days: [3, 1, 0],
    total_active_customers: 0,
    current_month_generated_count: 0,
    current_month_label: "",
  })

  const REMINDER_DAY_OPTIONS = [
    { value: 7, label: "H-7", desc: "7 Hari Sebelum" },
    { value: 5, label: "H-5", desc: "5 Hari Sebelum" },
    { value: 3, label: "H-3", desc: "3 Hari Sebelum" },
    { value: 2, label: "H-2", desc: "2 Hari Sebelum" },
    { value: 1, label: "H-1", desc: "1 Hari Sebelum" },
    { value: 0, label: "Hari H", desc: "Jatuh Tempo" },
  ]

  const toggleReminderDay = (day: number) => {
    const current = settings.reminder_days || []
    if (current.includes(day)) {
      if (current.length === 1) return // Keep at least one day if enabled
      setSettings({ ...settings, reminder_days: current.filter((d) => d !== day).sort((a, b) => b - a) })
    } else {
      setSettings({ ...settings, reminder_days: [...current, day].sort((a, b) => b - a) })
    }
  }

  const fetchSettings = () => {
    fetch("/admin/billing/auto-invoice-settings", {
      headers: {
        Accept: "application/json",
      },
    })
      .then((r) => {
        const ct = r.headers.get("content-type")
        if (r.ok && ct && ct.includes("application/json")) {
          return r.json()
        }
        return null
      })
      .then((data) => {
        if (data && data.success && data.settings) {
          setSettings({
            is_auto: data.settings.is_auto ?? true,
            generate_day: data.settings.generate_day ?? 1,
            due_day: data.settings.due_day ?? 20,
            auto_wa: data.settings.auto_wa ?? true,
            reminder_auto: data.settings.reminder_auto ?? true,
            reminder_days: Array.isArray(data.settings.reminder_days) && data.settings.reminder_days.length > 0 ? data.settings.reminder_days : [3, 1, 0],
            total_active_customers: data.settings.total_active_customers ?? 0,
            current_month_generated_count: data.settings.current_month_generated_count ?? 0,
            current_month_label: data.settings.current_month_label ?? "",
          })
        }
      })
      .catch(() => {})
      .finally(() => setLoading(false))
  }

  // Initial fetch dihapus agar tidak fetch saat modal belum dibuka

  // Global trigger event (e.g. from FAB or page action)
  React.useEffect(() => {
    const handleOpen = () => setOpen(true)
    window.addEventListener("open-auto-invoice-settings", handleOpen)
    return () => window.removeEventListener("open-auto-invoice-settings", handleOpen)
  }, [])

  React.useEffect(() => {
    if (open) {
      setMsg(null)
      setSaved(false)
      setLoading(true)
      fetchSettings()
    }
  }, [open])

  const handleSave = async (e: React.FormEvent) => {
    e.preventDefault()
    setSaving(true)
    setMsg(null)
    setSaved(false)

    try {
      const res = await fetch("/admin/billing/auto-invoice-settings", {
        method: "POST",
        headers: {
          Accept: "application/json",
          "Content-Type": "application/json",
          "X-CSRF-TOKEN": (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? "",
        },
        body: JSON.stringify({
          is_auto: settings.is_auto,
          generate_day: Number(settings.generate_day),
          due_day: Number(settings.due_day),
          auto_wa: settings.auto_wa,
          reminder_auto: settings.reminder_auto,
          reminder_days: settings.reminder_days,
        }),
      })

      const ct = res.headers.get("content-type")
      if (!ct || !ct.includes("application/json")) {
        throw new Error("Respon server tidak valid. Silakan muat ulang halaman.")
      }

      const data = await res.json()
      if (data.success) {
        setSaved(true)
        setMsg({ type: "success", text: data.message || "Pengaturan jadwal invoice berhasil disimpan!" })
        setTimeout(() => setSaved(false), 2500)
      } else {
        setMsg({ type: "error", text: data.message || "Gagal menyimpan pengaturan." })
      }
    } catch (err: unknown) {
      setMsg({ type: "error", text: err instanceof Error ? err.message : "Terjadi kesalahan sistem." })
    } finally {
      setSaving(false)
    }
  }

  const handleGenerateNow = async () => {
    if (!confirm(`Terbitkan tagihan bulan ${settings.current_month_label || "ini"} untuk semua pelanggan aktif sekarang?`)) {
      return
    }

    setGenerating(true)
    setMsg(null)

    try {
      const res = await fetch("/admin/billing/generate-now", {
        method: "POST",
        headers: {
          Accept: "application/json",
          "Content-Type": "application/json",
          "X-CSRF-TOKEN": (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? "",
        },
      })

      const ct = res.headers.get("content-type")
      if (!ct || !ct.includes("application/json")) {
        throw new Error("Respon server tidak valid. Silakan muat ulang halaman.")
      }

      const data = await res.json()
      if (data.success) {
        setMsg({ type: "success", text: data.message })
        fetchSettings()
      } else {
        setMsg({ type: "error", text: data.message || "Gagal memproses tagihan." })
      }
    } catch (err: unknown) {
      setMsg({ type: "error", text: err instanceof Error ? err.message : "Terjadi kesalahan server." })
    } finally {
      setGenerating(false)
    }
  }

  return (
    <>
      {/* Modal Dialog Form — Native Bottom Sheet on Mobile & Modal Card on Desktop */}
      {open ? (
        <div className="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-0 sm:p-4">
          <div className="fixed inset-0 bg-black/70 backdrop-blur-[2px]" onClick={() => setOpen(false)} />
          <div className="relative flex max-h-[90vh] sm:max-h-[92vh] w-full max-w-lg flex-col overflow-hidden rounded-t-3xl sm:rounded-2xl border-t sm:border border-border bg-card shadow-2xl z-10 animate-in slide-in-from-bottom-4 duration-200">
            {/* Mobile Drag Handle Indicator */}
            <div className="drag-handle sm:hidden" />

            {/* Header Modal */}
            <div className="flex items-center justify-between border-b border-border/60 px-5 py-3 sm:py-4 shrink-0 bg-card">
              <div className="flex items-center gap-2.5">
                <CalendarClock className="h-5 w-5 text-primary shrink-0" />
                <div>
                  <h3 className="font-display text-base font-semibold text-foreground">Pengaturan Jadwal Tagihan</h3>
                  <p className="text-xs text-muted-foreground">Jadwal auto-generate invoice bulanan & tanggal jatuh tempo</p>
                </div>
              </div>
              <button
                type="button"
                onClick={() => setOpen(false)}
                className="rounded-lg p-1 text-muted-foreground hover:bg-accent hover:text-foreground"
              >
                <X className="h-4 w-4" />
              </button>
            </div>

            {loading ? (
              <div className="flex flex-col items-center justify-center py-16 gap-2 text-muted-foreground">
                <Loader2 className="h-6 w-6 animate-spin text-primary" />
                <span className="text-xs">Memuat pengaturan...</span>
              </div>
            ) : (
              <form onSubmit={handleSave} className="flex min-h-0 flex-1 flex-col overflow-hidden">
                <div className="flex-1 overflow-y-auto p-5 space-y-4">
                  {/* Status Banner Pelanggan & Tagihan */}
                  <div className="grid grid-cols-2 gap-3 rounded-xl border border-primary/20 bg-primary/5 p-3 text-xs">
                    <div>
                      <span className="text-muted-foreground block text-[11px]">Total Pelanggan Aktif</span>
                      <strong className="text-foreground text-sm">{settings.total_active_customers} Orang</strong>
                    </div>
                    <div>
                      <span className="text-muted-foreground block text-[11px]">Invoice Bulan Ini ({settings.current_month_label})</span>
                      <strong className="text-primary text-sm">{settings.current_month_generated_count} Diterbitkan</strong>
                    </div>
                  </div>

                  {msg && (
                    <div
                      className={cn(
                        "rounded-xl p-3 text-xs flex items-center gap-2 font-medium",
                        msg.type === "success"
                          ? "bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30"
                          : "bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/30"
                      )}
                    >
                      {msg.type === "success" ? <Check className="h-4 w-4 shrink-0" /> : <AlertCircle className="h-4 w-4 shrink-0" />}
                      <span>{msg.text}</span>
                    </div>
                  )}

                  {/* Mode Auto Generate */}
                  <div className="space-y-1.5">
                    <Label className="text-xs font-bold text-foreground">Sistem Pembuatan Tagihan</Label>
                    <select
                      value={settings.is_auto ? "auto" : "manual"}
                      onChange={(e) => setSettings({ ...settings, is_auto: e.target.value === "auto" })}
                      className="h-10 w-full rounded-xl border border-input bg-background px-3 text-sm font-semibold focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-primary"
                    >
                      <option value="auto">Otomatis Terbit Tiap Bulan (Direkomendasikan)</option>
                      <option value="manual">Manual (Hanya Terbit Saat Ditekan Tombol)</option>
                    </select>
                  </div>

                  {/* Grid Tanggal Terbit & Jatuh Tempo */}
                  <div className="grid gap-3 sm:grid-cols-2">
                    <div className="space-y-1.5">
                      <Label className="text-xs font-semibold text-foreground flex items-center gap-1.5">
                        <Calendar className="h-3.5 w-3.5 text-primary" />
                        Tanggal Terbit Tagihan <span className="text-destructive">*</span>
                      </Label>
                      <select
                        value={settings.generate_day}
                        onChange={(e) => setSettings({ ...settings, generate_day: Number(e.target.value) })}
                        className="h-10 w-full rounded-lg border border-input bg-background px-3 text-sm font-medium focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-primary"
                      >
                        {Array.from({ length: 28 }, (_, i) => i + 1).map((day) => (
                          <option key={day} value={day}>
                            Setiap Tanggal {day}
                          </option>
                        ))}
                      </select>
                      <span className="text-[11px] text-muted-foreground">Invoice baru dibuat di sistem</span>
                    </div>

                    <div className="space-y-1.5">
                      <Label className="text-xs font-semibold text-foreground flex items-center gap-1.5">
                        <Clock className="h-3.5 w-3.5 text-amber-500" />
                        Tanggal Jatuh Tempo <span className="text-destructive">*</span>
                      </Label>
                      <select
                        value={settings.due_day}
                        onChange={(e) => setSettings({ ...settings, due_day: Number(e.target.value) })}
                        className="h-10 w-full rounded-lg border border-input bg-background px-3 text-sm font-medium focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-primary"
                      >
                        {Array.from({ length: 28 }, (_, i) => i + 1).map((day) => (
                          <option key={day} value={day}>
                            Setiap Tanggal {day}
                          </option>
                        ))}
                      </select>
                      <span className="text-[11px] text-muted-foreground">Batas akhir bayar pelanggan</span>
                    </div>
                  </div>

                  {/* Toggle Kirim Notifikasi WhatsApp Baru */}
                  <label className="flex cursor-pointer items-center gap-3 rounded-xl border border-border p-3.5 bg-background transition-colors hover:bg-accent/40">
                    <input
                      type="checkbox"
                      checked={settings.auto_wa}
                      onChange={(e) => setSettings({ ...settings, auto_wa: e.target.checked })}
                      className="h-4 w-4 rounded accent-[var(--color-primary)]"
                    />
                    <div className="space-y-0.5">
                      <span className="text-sm font-medium text-foreground">Kirim Notifikasi Terbit Tagihan via WhatsApp</span>
                      <p className="text-xs text-muted-foreground">Otomatis kirim rincian tagihan baru ke WA pelanggan saat invoice dibuat</p>
                    </div>
                  </label>

                  {/* Pengingat Jatuh Tempo (H-X) */}
                  <div className="rounded-xl border border-border bg-background/50 p-3.5 space-y-3">
                    <label className="flex cursor-pointer items-center justify-between gap-3">
                      <div className="space-y-0.5">
                        <div className="flex items-center gap-1.5">
                          <Bell className="h-4 w-4 text-emerald-400" />
                          <span className="text-sm font-semibold text-foreground">Pengingat Tagihan Otomatis</span>
                        </div>
                        <p className="text-xs text-muted-foreground">
                          Kirim pesan WhatsApp & Push Notification pengingat tagihan sebelum jatuh tempo
                        </p>
                      </div>
                      <input
                        type="checkbox"
                        checked={settings.reminder_auto}
                        onChange={(e) => setSettings({ ...settings, reminder_auto: e.target.checked })}
                        className="h-4 w-4 rounded accent-emerald-500 shrink-0"
                      />
                    </label>

                    {settings.reminder_auto && (
                      <div className="pt-2.5 border-t border-border/60 space-y-2 animate-in fade-in-50 duration-150">
                        <div className="flex items-center justify-between">
                          <span className="text-[11px] font-bold text-slate-300 uppercase tracking-wider">
                            Pilih Jadwal Pengingat (Hari)
                          </span>
                          <span className="text-[10.5px] font-mono text-emerald-400">Pukul 09:00 WIB</span>
                        </div>
                        <div className="grid grid-cols-3 gap-2">
                          {REMINDER_DAY_OPTIONS.map((opt) => {
                            const active = (settings.reminder_days || []).includes(opt.value)
                            return (
                              <button
                                key={opt.value}
                                type="button"
                                onClick={() => toggleReminderDay(opt.value)}
                                className={cn(
                                  "flex flex-col items-center justify-center p-2 rounded-xl border text-center transition-all cursor-pointer select-none",
                                  active
                                    ? "border-emerald-500/80 bg-emerald-500/10 text-emerald-400 shadow-xs ring-1 ring-emerald-500/30 font-bold"
                                    : "border-border/70 bg-card/60 text-muted-foreground hover:border-border hover:text-foreground"
                                )}
                              >
                                <span className="text-xs font-bold leading-tight">{opt.label}</span>
                                <span className="text-[10px] opacity-75 font-normal leading-tight mt-0.5">{opt.desc}</span>
                              </button>
                            )
                          })}
                        </div>
                        <p className="text-[11px] text-muted-foreground leading-relaxed pt-0.5">
                          Sistem akan memindai invoice belum lunas dan mengirim pesan pengingat pada hari-hari yang dipilih di atas.
                        </p>
                      </div>
                    )}
                  </div>

                  {/* Trigger Manual Instan */}
                  <div className="rounded-xl border border-primary/20 bg-primary/5 p-3.5 flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3">
                    <div className="space-y-0.5 min-w-0">
                      <p className="text-xs font-semibold text-foreground truncate">Terbitkan Tagihan Sekarang (Manual)</p>
                      <p className="text-[11px] text-muted-foreground">Generate langsung invoice bulan ini tanpa menunggu tanggal jadwal</p>
                    </div>
                    <Button
                      type="button"
                      variant="outline"
                      size="sm"
                      disabled={generating}
                      onClick={handleGenerateNow}
                      className="shrink-0 text-xs gap-1.5 rounded-lg border-primary/30 text-primary hover:bg-primary/10 w-full sm:w-auto"
                    >
                      {generating ? (
                        <>
                          <Loader2 className="h-3.5 w-3.5 animate-spin" />
                          Memproses...
                        </>
                      ) : (
                        <>
                          <Sparkles className="h-3.5 w-3.5" />
                          Generate Sekarang
                        </>
                      )}
                    </Button>
                  </div>
                </div>

                {/* Footer Modal — Gaya Tambah Pelanggan */}
                <div className="flex items-center gap-2.5 border-t border-border/60 bg-card p-4 shrink-0">
                  <Button
                    type="button"
                    variant="outline"
                    className="flex-1 rounded-xl"
                    onClick={() => setOpen(false)}
                  >
                    Batal
                  </Button>
                  <Button
                    type="submit"
                    className="flex-1 rounded-xl font-semibold"
                    disabled={saving}
                  >
                    {saving ? (
                      <>
                        <Loader2 className="mr-1.5 h-4 w-4 animate-spin" />
                        Menyimpan...
                      </>
                    ) : saved ? (
                      <>
                        <Check className="mr-1.5 h-4 w-4 text-emerald-400" />
                        Tersimpan!
                      </>
                    ) : (
                      "Simpan Pengaturan"
                    )}
                  </Button>
                </div>
              </form>
            )}
          </div>
        </div>
      ) : null}
    </>
  )
}
