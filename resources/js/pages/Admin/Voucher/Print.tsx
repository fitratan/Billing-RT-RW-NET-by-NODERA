import { AppLayout } from "@/components/layout/app-layout"
import {
  Printer,
  Ticket,
  Wifi,
  ExternalLink,
  Sliders,
  Scissors,
  Layers,
  QrCode,
  Download,
  Loader2,
  FileText,
  LayoutGrid,
  Activity,
} from "lucide-react"
import { PageProps } from "@/types"
import { formatIDR, cn } from "@/lib/utils"
import { adminSidebarItems, adminNavItems, adminBrand } from "@/lib/admin-nav"
import { router } from "@inertiajs/react"
import { useState, useEffect } from "react"
import { MetricCard } from "@/components/tailadmin/MetricCard"

interface Voucher {
  id: number
  username: string
  password?: string
  profile: string
  price?: number
  time_limit?: string
  data_limit?: string
  comment?: string
  batch_id?: string
}

interface Batch {
  batch_id: string
  total_vouchers: number
  profile: string
}

export default function VoucherPrintPage({
  vouchers = [],
  batches = [],
  selectedBatch = "",
  hotspotName = "NODERA HOTSPOT",
  dnsName = "nodera.login",
}: PageProps<{
  vouchers: Voucher[]
  batches: Batch[]
  selectedBatch: string | null
  hotspotName: string
  dnsName: string
}>) {
  const [template, setTemplate] = useState<"a4" | "thermal">("a4")
  const [batch, setBatch] = useState(selectedBatch || "")

  // Spacing & density controls
  const [cols, setCols] = useState<number>(4)
  const [fontScale, setFontScale] = useState<"small" | "normal" | "large">("normal")
  const [gap, setGap] = useState<string>("3mm")
  const [margin, setMargin] = useState<string>("4mm")
  const [showQr, setShowQr] = useState<boolean>(true)
  const [borderStyle, setBorderStyle] = useState<"dashed" | "solid" | "dotted" | "none">("dashed")
  const [activePreset, setActivePreset] = useState<"max" | "dense" | "standard" | "zerogap" | "custom">("dense")

  // Load preferences from localStorage on mount
  useEffect(() => {
    try {
      const saved = localStorage.getItem("nodera_voucher_print_pref")
      if (saved) {
        const pref = JSON.parse(saved)
        if (pref.cols) setCols(Number(pref.cols))
        if (pref.fontScale) setFontScale(pref.fontScale)
        if (pref.gap) setGap(pref.gap)
        if (pref.margin) setMargin(pref.margin)
        if (pref.qr !== undefined) setShowQr(pref.qr === "block" || pref.qr === true)
        if (pref.border) setBorderStyle(pref.border)
        setActivePreset(pref.preset || "custom")
      }
    } catch (e) {}
  }, [])

  const savePreferences = (newPref: any) => {
    try {
      localStorage.setItem("nodera_voucher_print_pref", JSON.stringify(newPref))
    } catch (e) {}
  }

  const handleApplyPreset = (preset: "max" | "dense" | "standard" | "zerogap") => {
    setActivePreset(preset)
    let newCols = 4
    let newGap = "2.5mm"
    let newMargin = "4mm"
    let newQr = true
    let newBorder: "dashed" | "solid" | "dotted" | "none" = "dashed"
    let newFontScale: "small" | "normal" | "large" = "normal"

    if (preset === "max") {
      newCols = 6
      newGap = "1mm"
      newMargin = "2mm"
      newQr = false
    } else if (preset === "dense") {
      newCols = 4
      newGap = "2.5mm"
      newMargin = "4mm"
      newQr = true
    } else if (preset === "zerogap") {
      newCols = 4
      newGap = "0mm"
      newMargin = "2mm"
      newBorder = "solid"
      newQr = true
    } else if (preset === "standard") {
      newCols = 3
      newGap = "4mm"
      newMargin = "6mm"
      newQr = true
    }

    setCols(newCols)
    setGap(newGap)
    setMargin(newMargin)
    setShowQr(newQr)
    setBorderStyle(newBorder)
    setFontScale(newFontScale)

    savePreferences({
      cols: newCols,
      fontScale: newFontScale,
      gap: newGap,
      margin: newMargin,
      qr: newQr ? "block" : "none",
      border: newBorder,
      preset,
    })
  }

  const handleBatchChange = (batchId: string) => {
    setBatch(batchId)
    router.get("/admin/voucher/print", { batch_id: batchId }, { preserveState: true })
  }

  const handlePrint = () => {
    window.print()
  }

  const [isDownloading, setIsDownloading] = useState(false)

  const handleDownloadPdf = async () => {
    setIsDownloading(true)
    try {
      const html2pdfModule = await import("html2pdf.js")
      const html2pdf = html2pdfModule.default || html2pdfModule

      const printArea = document.getElementById("print-area")
      if (!printArea) {
        alert("Elemen lembar voucher tidak ditemukan.")
        setIsDownloading(false)
        return
      }

      const opt = {
        margin: [4, 4, 4, 4],
        filename: `Voucher_Hotspot_${batch || "Semua"}_${new Date().toISOString().slice(0, 10)}.pdf`,
        image: { type: "jpeg", quality: 0.98 },
        html2canvas: {
          scale: 2,
          useCORS: true,
          logging: false,
          scrollY: 0,
        },
        jsPDF: {
          unit: "mm",
          format: template === "thermal" ? [80, 200] : "a4",
          orientation: "portrait",
        },
      }

      await (html2pdf as any)().set(opt).from(printArea).save()
    } catch (err) {
      console.error("Gagal mengunduh PDF:", err)
      alert("Gagal mengunduh file PDF. Mengalihkan ke dialog Cetak browser.")
      window.print()
    } finally {
      setIsDownloading(false)
    }
  }

  const handleOpenPrintWindow = () => {
    const url = `/admin/voucher/print?batch_id=${encodeURIComponent(batch)}&print=1`
    window.open(url, "_blank", "width=800,height=900,menubar=no,toolbar=no,location=no,status=no")
  }

  const vouchersPerSheet = template === "thermal" ? 1 : cols * 6
  const totalSheetsNeeded = Math.ceil(vouchers.length / (vouchersPerSheet || 1))

  return (
    <AppLayout
      title="Cetak Voucher Hotspot"
      brand={adminBrand}
      sidebarItems={adminSidebarItems}
      navItems={adminNavItems}
    >
      <div className="space-y-6">
        {/* Top Metric Cards */}
        <div className="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 print:hidden">
          <MetricCard
            title="Total Voucher Siap Cetak"
            value={`${vouchers.length} Vch`}
            sub={batch ? `Batch: ${batch}` : "Semua batch aktif"}
            icon={Ticket}
            iconBgColor="bg-brand-50 dark:bg-brand-500/10"
            iconColor="text-brand-500 dark:text-brand-400"
            badge={{ text: "Ready", color: "success" }}
          />
          <MetricCard
            title="Kebutuhan Kertas"
            value={`${totalSheetsNeeded} Lembar`}
            sub={`~${vouchersPerSheet} voucher / lbr A4`}
            icon={FileText}
            iconBgColor="bg-purple-50 dark:bg-purple-500/10"
            iconColor="text-purple-600 dark:text-purple-400"
            badge={{ text: template.toUpperCase(), color: "info" }}
          />
          <MetricCard
            title="Format Kertas"
            value={template === "a4" ? "A4 Sheet" : "Thermal 80mm"}
            sub={template === "a4" ? `${cols} Kolom Grid` : "Struk Gulungan"}
            icon={LayoutGrid}
            iconBgColor="bg-emerald-50 dark:bg-emerald-500/10"
            iconColor="text-emerald-600 dark:text-emerald-400"
            badge={{ text: activePreset.toUpperCase(), color: "success" }}
          />
          <MetricCard
            title="Estimasi Nilai Total"
            value={`Rp ${vouchers.reduce((acc, v) => acc + (v.price || 0), 0).toLocaleString("id-ID")}`}
            sub="Total omset batch ini"
            icon={Activity}
            iconBgColor="bg-amber-50 dark:bg-amber-500/10"
            iconColor="text-amber-600 dark:text-amber-400"
            badge={{ text: "Omset", color: "warning" }}
          />
        </div>

        {/* ── TOOLBAR CONTROLS & PRINT PRESETS (PRINT:HIDDEN) ── */}
        <div className="p-4 rounded-2xl border border-gray-200 bg-white shadow-xs dark:border-gray-800 dark:bg-white/[0.03] space-y-3.5 print:hidden">
          <div className="flex flex-col lg:flex-row items-stretch lg:items-center justify-between gap-3">
            {/* Template & Batch Selectors */}
            <div className="flex items-center gap-2 flex-wrap">
              <div className="flex rounded-xl border border-gray-200 dark:border-gray-800 p-1 bg-gray-50/50 dark:bg-gray-900/50">
                <button
                  type="button"
                  onClick={() => setTemplate("a4")}
                  className={cn(
                    "px-3 py-1.5 rounded-lg text-xs font-bold transition",
                    template === "a4"
                      ? "bg-brand-500 text-white shadow-xs"
                      : "text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white"
                  )}
                >
                  Kertas A4
                </button>
                <button
                  type="button"
                  onClick={() => setTemplate("thermal")}
                  className={cn(
                    "px-3 py-1.5 rounded-lg text-xs font-bold transition",
                    template === "thermal"
                      ? "bg-brand-500 text-white shadow-xs"
                      : "text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white"
                  )}
                >
                  Thermal (POS)
                </button>
              </div>

              {batches.length > 0 && (
                <select
                  value={batch}
                  onChange={(e) => handleBatchChange(e.target.value)}
                  className="h-9 rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 px-3 text-xs font-semibold text-gray-700 dark:text-gray-200 focus:outline-none focus:border-brand-500"
                >
                  <option value="">Semua Batch ({batches.reduce((a, b) => a + b.total_vouchers, 0)})</option>
                  {batches.map((b) => (
                    <option key={b.batch_id} value={b.batch_id}>
                      {b.batch_id} ({b.total_vouchers} vch - {b.profile})
                    </option>
                  ))}
                </select>
              )}
            </div>

            {/* Quick Presets for A4 */}
            {template === "a4" && (
              <div className="flex items-center gap-1.5 overflow-x-auto pb-1 lg:pb-0">
                <span className="text-[11px] font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider shrink-0 mr-1">
                  Preset:
                </span>
                <button
                  type="button"
                  onClick={() => handleApplyPreset("dense")}
                  className={cn(
                    "px-2.5 py-1 rounded-lg text-xs font-semibold border transition shrink-0",
                    activePreset === "dense"
                      ? "bg-brand-500 border-brand-600 text-white shadow-xs"
                      : "border-gray-200 bg-white hover:bg-gray-50 text-gray-700 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800"
                  )}
                  title="24 Voucher/Lembar A4 (4 Kolom dengan QR)"
                >
                  24 / Lembar
                </button>
                <button
                  type="button"
                  onClick={() => handleApplyPreset("max")}
                  className={cn(
                    "px-2.5 py-1 rounded-lg text-xs font-semibold border transition shrink-0",
                    activePreset === "max"
                      ? "bg-brand-500 border-brand-600 text-white shadow-xs"
                      : "border-gray-200 bg-white hover:bg-gray-50 text-gray-700 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800"
                  )}
                  title="36 Voucher/Lembar A4 (6 Kolom tanpa QR hemat kertas)"
                >
                  36 Max (Hemat)
                </button>
                <button
                  type="button"
                  onClick={() => handleApplyPreset("zerogap")}
                  className={cn(
                    "px-2.5 py-1 rounded-lg text-xs font-semibold border transition shrink-0",
                    activePreset === "zerogap"
                      ? "bg-brand-500 border-brand-600 text-white shadow-xs"
                      : "border-gray-200 bg-white hover:bg-gray-50 text-gray-700 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800"
                  )}
                  title="0 Gap Spacing untuk potong lurus cepat dengan cutter/guillotine"
                >
                  Potong Lurus (0 Gap)
                </button>
                <button
                  type="button"
                  onClick={() => handleApplyPreset("standard")}
                  className={cn(
                    "px-2.5 py-1 rounded-lg text-xs font-semibold border transition shrink-0",
                    activePreset === "standard"
                      ? "bg-brand-500 border-brand-600 text-white shadow-xs"
                      : "border-gray-200 bg-white hover:bg-gray-50 text-gray-700 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800"
                  )}
                  title="18 Voucher/Lembar A4 (3 Kolom kartu besar)"
                >
                  18 (Besar)
                </button>
              </div>
            )}

            {/* Print Action Buttons */}
            <div className="flex items-center gap-2 shrink-0 flex-wrap">
              <button
                type="button"
                onClick={handlePrint}
                className="h-9 px-3.5 text-xs font-bold gap-1.5 rounded-xl bg-brand-500 hover:bg-brand-600 text-white flex items-center justify-center transition shadow-xs"
              >
                <Printer className="h-4 w-4" />
                <span>Cetak</span>
              </button>

              <button
                type="button"
                onClick={handleDownloadPdf}
                disabled={isDownloading}
                className="h-9 px-3.5 text-xs font-bold gap-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white flex items-center justify-center transition shadow-xs disabled:opacity-50"
              >
                {isDownloading ? <Loader2 className="h-4 w-4 animate-spin" /> : <Download className="h-4 w-4" />}
                <span>Download PDF</span>
              </button>

              <button
                type="button"
                onClick={handleOpenPrintWindow}
                className="h-9 px-3 text-xs font-semibold gap-1.5 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:hover:bg-gray-800 text-gray-700 dark:text-gray-200 transition flex items-center justify-center"
              >
                <ExternalLink className="h-3.5 w-3.5" />
                <span className="hidden md:inline">Tab Baru</span>
              </button>
            </div>
          </div>
        </div>

        {/* ── PRINT AREA CONTAINER ── */}
        <div className="flex justify-center">
          <div
            id="print-area"
            className={cn(
              "bg-white text-black p-4 sm:p-6 rounded-2xl border border-gray-200 shadow-sm print:border-none print:shadow-none print:p-0 print:m-0 print:w-full",
              template === "thermal" ? "max-w-xs w-full" : "w-full max-w-5xl"
            )}
          >
            {vouchers.length === 0 ? (
              <div className="p-12 text-center text-gray-400">
                <Ticket className="mx-auto h-12 w-12 text-gray-300 mb-2" />
                <p className="font-semibold text-gray-700">Tidak ada voucher yang siap dicetak</p>
                <p className="text-xs text-gray-500 mt-1">Pilih batch voucher atau generate voucher baru terlebih dahulu.</p>
              </div>
            ) : template === "thermal" ? (
              /* Thermal 58mm/80mm layout */
              <div className="space-y-4 divide-y divide-dashed divide-gray-300">
                {vouchers.map((v) => (
                  <div key={v.id} className="pt-4 first:pt-0 text-center font-mono text-xs">
                    <div className="font-bold text-sm uppercase tracking-wider">{hotspotName}</div>
                    <div className="text-[10px] text-gray-600 mb-2">{dnsName}</div>

                    <div className="border border-dashed border-gray-400 rounded-lg p-3 my-2 bg-gray-50">
                      <div className="text-[10px] text-gray-500 uppercase">KODE VOUCHER</div>
                      <div className="text-xl font-extrabold tracking-widest my-1">{v.username}</div>
                      {v.password && v.password !== v.username && (
                        <div className="text-xs font-bold text-gray-700">Pass: {v.password}</div>
                      )}
                    </div>

                    <div className="grid grid-cols-2 gap-1 text-[11px] text-gray-600 my-2">
                      <div className="text-left">Profil: <span className="font-bold text-black">{v.profile}</span></div>
                      <div className="text-right">Masa: <span className="font-bold text-black">{v.time_limit || "Unlimited"}</span></div>
                    </div>

                    <div className="text-center font-bold text-sm text-black border-t border-dashed border-gray-300 pt-2 mt-2">
                      {v.price ? formatIDR(v.price) : "GRATIS"}
                    </div>
                  </div>
                ))}
              </div>
            ) : (
              /* A4 Grid Layout */
              <div
                className="grid gap-2"
                style={{
                  gridTemplateColumns: `repeat(${cols}, minmax(0, 1fr))`,
                  gap: gap,
                  padding: margin,
                }}
              >
                {vouchers.map((v) => (
                  <div
                    key={v.id}
                    className="border border-dashed border-gray-400 rounded-lg p-2.5 bg-white text-black flex flex-col justify-between"
                    style={{
                      borderStyle: borderStyle,
                      minHeight: "105px",
                    }}
                  >
                    <div>
                      {/* Card Header */}
                      <div className="flex items-center justify-between border-b border-gray-200 pb-1 mb-1.5">
                        <div className="flex items-center gap-1 font-bold text-[11px] tracking-tight uppercase truncate">
                          <Wifi className="h-3 w-3 text-brand-600 shrink-0" />
                          <span className="truncate">{hotspotName}</span>
                        </div>
                        <span className="text-[10px] font-extrabold font-mono text-emerald-700 shrink-0">
                          {v.price ? formatIDR(v.price) : "FREE"}
                        </span>
                      </div>

                      {/* Code Display */}
                      <div className="bg-gray-50 border border-gray-200 rounded p-1.5 text-center my-1">
                        <div className="text-[9px] text-gray-500 uppercase tracking-wider">KODE VOUCHER</div>
                        <div
                          className={cn(
                            "font-mono font-extrabold tracking-wider select-all",
                            fontScale === "large" ? "text-base" : fontScale === "small" ? "text-xs" : "text-sm"
                          )}
                        >
                          {v.username}
                        </div>
                        {v.password && v.password !== v.username && (
                          <div className="text-[10px] font-mono text-gray-600">PIN: {v.password}</div>
                        )}
                      </div>
                    </div>

                    {/* Card Footer Info */}
                    <div className="pt-1 border-t border-gray-100 flex items-center justify-between text-[10px] text-gray-600">
                      <span className="font-semibold truncate">{v.profile}</span>
                      <span className="font-mono">{v.time_limit || "Aktif"}</span>
                    </div>
                  </div>
                ))}
              </div>
            )}
          </div>
        </div>
      </div>
    </AppLayout>
  )
}
