import { motion, AnimatePresence } from "framer-motion"
import { RefreshCw, Server, ShieldAlert, CheckCircle2 } from "lucide-react"
import { cn } from "@/lib/utils"

interface SyncOverlayProps {
  show: boolean
  progress?: number
  title?: string
  statusMessage?: string
  subMessage?: string
}

export function SyncOverlay({
  show,
  progress,
  title = "Sinkronisasi Data",
  statusMessage,
  subMessage,
}: SyncOverlayProps) {
  const getStatusText = (val?: number) => {
    if (statusMessage) return statusMessage
    if (val === undefined) return "Menyinkronkan data..."
    if (val < 25) return "Menghubungkan ke sistem..."
    if (val < 60) return "Memproses dan memperbarui data..."
    if (val < 88) return "Menyinkronkan status data..."
    if (val < 100) return "Menyelesaikan & menyimpan perubahan..."
    return "Sinkronisasi Berhasil Selesai!"
  }

  const isDone = progress !== undefined && progress >= 100

  return (
    <AnimatePresence>
      {show && (
        <motion.div
          initial={{ opacity: 0 }}
          animate={{ opacity: 1 }}
          exit={{ opacity: 0 }}
          transition={{ duration: 0.15 }}
          className="fixed inset-0 z-[9999] flex items-center justify-center bg-black/60 p-4 backdrop-blur-xs select-none pointer-events-auto"
        >
          <motion.div
            initial={{ opacity: 0, scale: 0.95, y: 10 }}
            animate={{ opacity: 1, scale: 1, y: 0 }}
            exit={{ opacity: 0, scale: 0.95, y: 6 }}
            transition={{ type: "spring", stiffness: 450, damping: 32 }}
            className="w-full max-w-sm rounded-2xl border border-gray-200 bg-white p-6 text-center text-gray-900 shadow-2xl dark:border-gray-800 dark:bg-gray-900 dark:text-white space-y-4"
          >
            {/* Animated Icon Box */}
            <div className="relative mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-brand-500/10 border border-brand-500/20 text-brand-500 dark:bg-brand-500/15 dark:border-brand-500/30">
              {isDone ? (
                <CheckCircle2 className="h-7 w-7 text-emerald-500 animate-in zoom-in-50" />
              ) : (
                <>
                  <Server className="h-7 w-7 text-brand-500" />
                  <RefreshCw className="absolute -bottom-1 -right-1 h-4 w-4 animate-spin text-brand-600 bg-white dark:bg-gray-900 rounded-full p-0.5 shadow-xs" />
                </>
              )}
            </div>

            {/* Title & Status Message */}
            <div className="space-y-1">
              <h3 className="font-bold text-base text-gray-900 dark:text-white">
                {title}
              </h3>
              <p className="text-xs font-medium text-gray-600 dark:text-gray-300 min-h-[24px] flex items-center justify-center leading-relaxed">
                {getStatusText(progress)}
              </p>
              {subMessage && (
                <p className="text-[11px] text-gray-400 font-mono">
                  {subMessage}
                </p>
              )}
            </div>

            {/* Progress Bar (Determinate or Indeterminate Pulse) */}
            {progress !== undefined ? (
              <div className="space-y-1.5">
                <span className="font-bold text-xl text-brand-500 tabular-nums tracking-tight">
                  {Math.min(100, Math.max(0, Math.round(progress)))}%
                </span>
                <div className="relative h-2 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
                  <motion.div
                    className="h-full rounded-full bg-brand-500 transition-all duration-300"
                    style={{ width: `${Math.min(100, Math.max(0, progress))}%` }}
                  />
                </div>
              </div>
            ) : (
              <div className="h-1.5 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800">
                <div className="h-full w-2/3 animate-pulse rounded-full bg-brand-500" />
              </div>
            )}

            {/* Safe Warning Note */}
            <div className="flex items-center justify-center gap-2 rounded-xl bg-amber-500/10 border border-amber-500/20 px-3 py-2 text-[11px] font-medium text-amber-700 dark:text-amber-300">
              <ShieldAlert className="h-3.5 w-3.5 shrink-0 text-amber-500" />
              <span>Mohon tunggu hingga proses selesai.</span>
            </div>
          </motion.div>
        </motion.div>
      )}
    </AnimatePresence>
  )
}
