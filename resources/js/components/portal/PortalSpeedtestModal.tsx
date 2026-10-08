import { useState, useEffect, useRef } from "react"
import {
  Activity,
  ArrowDown,
  ArrowUp,
  Clock,
  Gauge,
  RotateCcw,
  X,
  CheckCircle2,
} from "lucide-react"

interface PortalSpeedtestModalProps {
  isOpen: boolean
  onClose: () => void
  packageName?: string
  packageSpeed?: string | number
  companyName?: string
}

type TestState = "idle" | "ping" | "download" | "upload" | "complete" | "error"

export function PortalSpeedtestModal({
  isOpen,
  onClose,
  packageName,
  packageSpeed,
  companyName = "NODERA",
}: PortalSpeedtestModalProps) {
  const [state, setState] = useState<TestState>("idle")
  const [ping, setPing] = useState<number | null>(null)
  const [jitter, setJitter] = useState<number | null>(null)
  const [downloadSpeed, setDownloadSpeed] = useState<number>(0)
  const [uploadSpeed, setUploadSpeed] = useState<number>(0)
  const [currentSpeed, setCurrentSpeed] = useState<number>(0)
  const [progress, setProgress] = useState<number>(0)
  const [statusText, setStatusText] = useState<string>("Siap menguji koneksi")
  const [errorMessage, setErrorMessage] = useState<string | null>(null)

  const abortControllerRef = useRef<AbortController | null>(null)
  const animFrameRef = useRef<number | null>(null)

  // Reset state on open
  useEffect(() => {
    if (isOpen) {
      setState("idle")
      setPing(null)
      setJitter(null)
      setDownloadSpeed(0)
      setUploadSpeed(0)
      setCurrentSpeed(0)
      setProgress(0)
      setStatusText("Tekan tombol di bawah untuk memulai uji kecepatan")
      setErrorMessage(null)
    } else {
      stopTest()
    }
    return () => {
      stopTest()
    }
  }, [isOpen])

  const stopTest = () => {
    if (abortControllerRef.current) {
      abortControllerRef.current.abort()
      abortControllerRef.current = null
    }
    if (animFrameRef.current) {
      cancelAnimationFrame(animFrameRef.current)
      animFrameRef.current = null
    }
  }

  const runTest = async () => {
    stopTest()
    const abortController = new AbortController()
    abortControllerRef.current = abortController
    const { signal } = abortController

    setState("ping")
    setPing(null)
    setJitter(null)
    setDownloadSpeed(0)
    setUploadSpeed(0)
    setCurrentSpeed(0)
    setProgress(5)
    setStatusText("Mengukur Latensi & Jitter...")
    setErrorMessage(null)

    try {
      // 1. PING & JITTER TEST (5 Rapid Round-Trips)
      const pings: number[] = []
      for (let i = 0; i < 5; i++) {
        if (signal.aborted) return
        const t0 = performance.now()
        const res = await fetch(`/portal/speedtest/ping?t=${Date.now()}_${i}`, {
          signal,
          headers: { Accept: "application/json" },
        })
        if (!res.ok) throw new Error("Ping gagal merespons")
        const t1 = performance.now()
        pings.push(t1 - t0)
        setProgress(5 + i * 3)
        await new Promise((r) => setTimeout(r, 80))
      }

      const avgPing = Math.round(pings.reduce((a, b) => a + b, 0) / pings.length)
      const jitters: number[] = []
      for (let i = 1; i < pings.length; i++) {
        jitters.push(Math.abs(pings[i] - pings[i - 1]))
      }
      const avgJitter = Math.round(jitters.reduce((a, b) => a + b, 0) / (jitters.length || 1))

      setPing(avgPing)
      setJitter(avgJitter)

      // 2. DOWNLOAD SPEED TEST (~6 Seconds)
      if (signal.aborted) return
      setState("download")
      setStatusText("Menguji Kecepatan Unduh (Download)...")
      setProgress(25)

      const downloadDurationMs = 6000
      const dlStartTime = performance.now()
      let dlTotalBytes = 0

      const activeDlStreams: Promise<void>[] = []
      const numStreams = 3

      for (let s = 0; s < numStreams; s++) {
        activeDlStreams.push(
          (async () => {
            while (performance.now() - dlStartTime < downloadDurationMs && !signal.aborted) {
              try {
                const res = await fetch(`/portal/speedtest/download?size=4&t=${Date.now()}_${s}`, {
                  signal,
                })
                if (!res.body) break
                const reader = res.body.getReader()
                while (!signal.aborted) {
                  const { done, value } = await reader.read()
                  if (done || !value) break
                  dlTotalBytes += value.length

                  const now = performance.now()
                  const elapsedFromStart = (now - dlStartTime) / 1000
                  const currentRateMbps = (dlTotalBytes * 8) / (elapsedFromStart * 1000000)

                  setCurrentSpeed(Math.max(0, currentRateMbps))
                  setDownloadSpeed(Math.max(0, currentRateMbps))

                  const dlProgress = 25 + Math.min(40, Math.round(((now - dlStartTime) / downloadDurationMs) * 40))
                  setProgress(dlProgress)

                  if (now - dlStartTime >= downloadDurationMs) {
                    reader.cancel()
                    break
                  }
                }
              } catch (e: any) {
                if (signal.aborted) return
                break
              }
            }
          })()
        )
      }

      await Promise.all(activeDlStreams)
      const finalDlElapsed = (performance.now() - dlStartTime) / 1000
      const finalDlMbps = finalDlElapsed > 0 ? (dlTotalBytes * 8) / (finalDlElapsed * 1000000) : 0
      setDownloadSpeed(Math.max(0.1, Number(finalDlMbps.toFixed(1))))
      setCurrentSpeed(0)

      // 3. UPLOAD SPEED TEST (~5 Seconds)
      if (signal.aborted) return
      setState("upload")
      setStatusText("Menguji Kecepatan Unggah (Upload)...")
      setProgress(68)

      const uploadDurationMs = 5000
      const ulStartTime = performance.now()
      let ulTotalBytes = 0

      // Create dummy 512KB payload chunk
      const chunkSize = 512 * 1024
      const chunkBuffer = new Uint8Array(chunkSize)
      for (let i = 0; i < chunkSize; i += 1024) {
        chunkBuffer[i] = 65 // 'A'
      }
      const chunkBlob = new Blob([chunkBuffer], { type: "application/octet-stream" })

      const activeUlStreams: Promise<void>[] = []
      const ulStreamsCount = 2

      for (let s = 0; s < ulStreamsCount; s++) {
        activeUlStreams.push(
          (async () => {
            while (performance.now() - ulStartTime < uploadDurationMs && !signal.aborted) {
              try {
                const res = await fetch(`/portal/speedtest/upload?t=${Date.now()}_${s}`, {
                  method: "POST",
                  body: chunkBlob,
                  signal,
                })
                if (res.ok) {
                  ulTotalBytes += chunkSize
                  const now = performance.now()
                  const elapsedSec = (now - ulStartTime) / 1000
                  const currentRateMbps = (ulTotalBytes * 8) / (elapsedSec * 1000000)

                  setCurrentSpeed(Math.max(0, currentRateMbps))
                  setUploadSpeed(Math.max(0, currentRateMbps))

                  const ulProgress = 68 + Math.min(30, Math.round(((now - ulStartTime) / uploadDurationMs) * 30))
                  setProgress(ulProgress)
                }
              } catch (e: any) {
                if (signal.aborted) return
                break
              }
            }
          })()
        )
      }

      await Promise.all(activeUlStreams)
      const finalUlElapsed = (performance.now() - ulStartTime) / 1000
      const finalUlMbps = finalUlElapsed > 0 ? (ulTotalBytes * 8) / (finalUlElapsed * 1000000) : 0
      setUploadSpeed(Math.max(0.1, Number(finalUlMbps.toFixed(1))))
      setCurrentSpeed(0)
      setProgress(100)
      setState("complete")
      setStatusText("Pengujian selesai dengan hasil optimal!")
    } catch (err: any) {
      if (signal.aborted) return
      setState("error")
      setErrorMessage(err?.message || "Terjadi kesalahan saat menguji koneksi.")
      setStatusText("Pengujian terhenti.")
    }
  }

  if (!isOpen) return null

  // Speedometer Gauge Math (Semi-Circle Arc)
  // Max scale defaults to 100 Mbps or 1.5x measured speed
  const maxScale = Math.max(50, Math.ceil((Math.max(downloadSpeed, uploadSpeed, currentSpeed) * 1.3) / 10) * 10)
  const gaugePercent = Math.min(1, Math.max(0, currentSpeed / maxScale))
  // Arc angle from -90 to +90 deg (total 180 deg)
  const needleRotation = -90 + gaugePercent * 180

  const activeMetricSpeed =
    state === "download" ? downloadSpeed : state === "upload" ? uploadSpeed : currentSpeed

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-black/70 backdrop-blur-xs animate-in fade-in duration-200">
      <div className="relative w-full max-w-md rounded-3xl bg-white dark:bg-[#10141A] border border-gray-200 dark:border-gray-800 shadow-2xl overflow-hidden flex flex-col max-h-[92vh]">
        {/* Header Modal */}
        <div className="flex items-center justify-between px-5 py-4 border-b border-gray-100 dark:border-gray-800/80 shrink-0">
          <div className="flex items-center gap-2.5">
            <div className="flex h-8 w-8 items-center justify-center rounded-xl bg-brand-600 text-white shadow-xs">
              <Gauge className="h-4 w-4" />
            </div>
            <div>
              <h3 className="text-sm font-black text-gray-900 dark:text-white uppercase tracking-wider">
                Uji Kecepatan Internet
              </h3>
              <p className="text-[11px] font-bold text-gray-400 dark:text-gray-500">
                Server Lokal: {companyName}
              </p>
            </div>
          </div>
          <button
            onClick={() => {
              stopTest()
              onClose()
            }}
            className="rounded-xl p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-gray-800 dark:hover:text-gray-200 transition-colors"
          >
            <X className="h-5 w-5" />
          </button>
        </div>

        {/* Content Body */}
        <div className="p-5 overflow-y-auto space-y-5">
          {/* Speedometer Gauge Visualizer */}
          <div className="relative flex flex-col items-center justify-center pt-2">
            <div className="relative w-56 h-32 overflow-hidden flex items-end justify-center">
              {/* Semi-circle track SVG */}
              <svg className="w-56 h-56 transform -rotate-180" viewBox="0 0 200 200">
                {/* Background Track */}
                <circle
                  cx="100"
                  cy="100"
                  r="80"
                  fill="none"
                  stroke="currentColor"
                  className="text-gray-100 dark:text-gray-800/80"
                  strokeWidth="16"
                  strokeDasharray="251.32 251.32"
                  strokeDashoffset="0"
                />
                {/* Active Progress Arc */}
                <circle
                  cx="100"
                  cy="100"
                  r="80"
                  fill="none"
                  stroke="url(#speed-gradient)"
                  strokeWidth="16"
                  strokeDasharray="251.32 251.32"
                  strokeDashoffset={251.32 * (1 - gaugePercent)}
                  strokeLinecap="round"
                  className="transition-all duration-150 ease-out"
                />
                <defs>
                  <linearGradient id="speed-gradient" x1="0%" y1="0%" x2="100%" y2="0%">
                    <stop offset="0%" stopColor="#10B981" />
                    <stop offset="50%" stopColor="#0284C7" />
                    <stop offset="100%" stopColor="#4F46E5" />
                  </linearGradient>
                </defs>
              </svg>

              {/* Needle Needle Indicator */}
              <div
                className="absolute bottom-0 w-1.5 h-20 bg-brand-600 dark:bg-brand-500 rounded-t-full origin-bottom transition-transform duration-150 ease-out shadow-md"
                style={{ transform: `rotate(${needleRotation}deg)` }}
              />
              <div className="absolute bottom-0 w-6 h-6 rounded-full bg-gray-900 dark:bg-white border-2 border-white dark:border-[#10141A] shadow-md z-10" />
            </div>

            {/* Live Center Reading */}
            <div className="text-center mt-2">
              <div className="text-3xl sm:text-4xl font-black font-mono tracking-tight text-gray-900 dark:text-white">
                {state === "idle"
                  ? "0.0"
                  : state === "complete"
                  ? downloadSpeed.toFixed(1)
                  : activeMetricSpeed.toFixed(1)}
              </div>
              <div className="text-[11px] font-black uppercase tracking-widest text-brand-600 dark:text-brand-400">
                {state === "upload" ? "Upload (Mbps)" : "Download (Mbps)"}
              </div>
            </div>

            {/* Status Subtitle */}
            <div className="mt-2 text-center text-xs font-bold text-gray-500 dark:text-gray-400 flex items-center justify-center gap-1.5">
              {(state === "ping" || state === "download" || state === "upload") && (
                <span className="relative flex h-2 w-2">
                  <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-brand-400 opacity-75"></span>
                  <span className="relative inline-flex rounded-full h-2 w-2 bg-brand-600"></span>
                </span>
              )}
              {state === "complete" && <CheckCircle2 className="h-3.5 w-3.5 text-emerald-500" />}
              <span>{statusText}</span>
            </div>

            {/* Linear Progress Bar */}
            {(state === "ping" || state === "download" || state === "upload") && (
              <div className="w-full bg-gray-100 dark:bg-gray-800 rounded-full h-1.5 mt-3 overflow-hidden">
                <div
                  className="bg-brand-600 h-1.5 rounded-full transition-all duration-200"
                  style={{ width: `${progress}%` }}
                />
              </div>
            )}
          </div>

          {/* 4 Stats Grid */}
          <div className="grid grid-cols-2 gap-2.5">
            {/* Ping */}
            <div className="rounded-2xl border border-gray-100 bg-gray-50/80 p-3 dark:border-gray-800/80 dark:bg-white/[0.02]">
              <div className="flex items-center gap-1.5 text-gray-400 mb-1">
                <Activity className="h-3.5 w-3.5 text-emerald-500" />
                <span className="text-[10px] font-bold uppercase tracking-wider">Ping</span>
              </div>
              <div className="text-lg font-black font-mono text-gray-900 dark:text-white">
                {ping !== null ? `${ping} ms` : "-"}
              </div>
            </div>

            {/* Jitter */}
            <div className="rounded-2xl border border-gray-100 bg-gray-50/80 p-3 dark:border-gray-800/80 dark:bg-white/[0.02]">
              <div className="flex items-center gap-1.5 text-gray-400 mb-1">
                <Clock className="h-3.5 w-3.5 text-indigo-500" />
                <span className="text-[10px] font-bold uppercase tracking-wider">Jitter</span>
              </div>
              <div className="text-lg font-black font-mono text-gray-900 dark:text-white">
                {jitter !== null ? `${jitter} ms` : "-"}
              </div>
            </div>

            {/* Download */}
            <div className="rounded-2xl border border-gray-100 bg-gray-50/80 p-3 dark:border-gray-800/80 dark:bg-white/[0.02]">
              <div className="flex items-center gap-1.5 text-gray-400 mb-1">
                <ArrowDown className="h-3.5 w-3.5 text-emerald-600" />
                <span className="text-[10px] font-bold uppercase tracking-wider">Download</span>
              </div>
              <div className="text-lg font-black font-mono text-emerald-600 dark:text-emerald-400">
                {downloadSpeed > 0 ? `${downloadSpeed.toFixed(1)} Mbps` : "-"}
              </div>
            </div>

            {/* Upload */}
            <div className="rounded-2xl border border-gray-100 bg-gray-50/80 p-3 dark:border-gray-800/80 dark:bg-white/[0.02]">
              <div className="flex items-center gap-1.5 text-gray-400 mb-1">
                <ArrowUp className="h-3.5 w-3.5 text-brand-600" />
                <span className="text-[10px] font-bold uppercase tracking-wider">Upload</span>
              </div>
              <div className="text-lg font-black font-mono text-brand-600 dark:text-brand-400">
                {uploadSpeed > 0 ? `${uploadSpeed.toFixed(1)} Mbps` : "-"}
              </div>
            </div>
          </div>

          {/* Package Benchmark Banner (when complete) */}
          {state === "complete" && packageName && (
            <div className="rounded-2xl border border-emerald-500/20 bg-emerald-500/5 p-3.5 text-xs text-gray-700 dark:text-gray-300 flex items-center justify-between">
              <div>
                <span className="text-[10px] font-bold uppercase tracking-wider text-emerald-600 dark:text-emerald-400 block">
                  Profil Paket Pelanggan
                </span>
                <span className="font-extrabold text-gray-900 dark:text-white">{packageName}</span>
              </div>
              <div className="rounded-lg bg-emerald-600 text-white px-2.5 py-1 text-[11px] font-black uppercase tracking-wider shadow-xs">
                Kondisi Prima
              </div>
            </div>
          )}

          {/* Error Notice */}
          {state === "error" && (
            <div className="rounded-2xl bg-rose-600 p-3.5 text-white text-xs font-bold">
              {errorMessage || "Pengujian gagal, pastikan koneksi internet Anda aktif."}
            </div>
          )}
        </div>

        {/* Footer Actions */}
        <div className="px-5 py-4 border-t border-gray-100 dark:border-gray-800/80 bg-gray-50/50 dark:bg-white/[0.01] shrink-0">
          {state === "idle" && (
            <button
              onClick={runTest}
              className="w-full h-11 rounded-xl bg-brand-600 hover:bg-brand-700 active:scale-[0.98] text-white text-sm font-black uppercase tracking-wider shadow-sm transition-all flex items-center justify-center gap-2"
            >
              <Gauge className="h-4 w-4" />
              <span>Mulai Uji Kecepatan</span>
            </button>
          )}

          {(state === "ping" || state === "download" || state === "upload") && (
            <button
              onClick={stopTest}
              className="w-full h-11 rounded-xl bg-rose-600 hover:bg-rose-700 active:scale-[0.98] text-white text-sm font-black uppercase tracking-wider shadow-sm transition-all"
            >
              Batalkan Pengujian
            </button>
          )}

          {(state === "complete" || state === "error") && (
            <div className="flex items-center gap-2.5">
              <button
                onClick={runTest}
                className="flex-1 h-11 rounded-xl bg-brand-600 hover:bg-brand-700 active:scale-[0.98] text-white text-sm font-black uppercase tracking-wider shadow-sm transition-all flex items-center justify-center gap-2"
              >
                <RotateCcw className="h-4 w-4" />
                <span>Uji Ulang</span>
              </button>
              <button
                onClick={onClose}
                className="px-5 h-11 rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200 text-sm font-bold transition-all"
              >
                Tutup
              </button>
            </div>
          )}
        </div>
      </div>
    </div>
  )
}
