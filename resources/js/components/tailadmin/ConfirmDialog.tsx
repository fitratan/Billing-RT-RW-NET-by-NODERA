import React from "react"
import { AlertTriangle, Info, HelpCircle, X, Loader2 } from "lucide-react"

interface ConfirmDialogProps {
  isOpen: boolean
  title: string
  description: string
  confirmText?: string
  cancelText?: string
  variant?: "danger" | "warning" | "info" | "primary"
  loading?: boolean
  onConfirm: () => void
  onClose: () => void
}

export const ConfirmDialog: React.FC<ConfirmDialogProps> = ({
  isOpen,
  title,
  description,
  confirmText = "Konfirmasi",
  cancelText = "Batal",
  variant = "warning",
  loading = false,
  onConfirm,
  onClose,
}) => {
  if (!isOpen) return null

  const getVariantStyles = () => {
    switch (variant) {
      case "danger":
        return {
          icon: <AlertTriangle className="h-6 w-6 text-rose-500" />,
          iconBg: "bg-rose-500/10",
          btnBg: "bg-rose-600 hover:bg-rose-700 text-white",
        }
      case "info":
        return {
          icon: <Info className="h-6 w-6 text-sky-500" />,
          iconBg: "bg-sky-500/10",
          btnBg: "bg-sky-600 hover:bg-sky-700 text-white",
        }
      case "primary":
        return {
          icon: <HelpCircle className="h-6 w-6 text-brand-500" />,
          iconBg: "bg-brand-500/10",
          btnBg: "bg-brand-500 hover:bg-brand-600 text-white",
        }
      case "warning":
      default:
        return {
          icon: <AlertTriangle className="h-6 w-6 text-amber-500" />,
          iconBg: "bg-amber-500/10",
          btnBg: "bg-amber-600 hover:bg-amber-700 text-white",
        }
    }
  }

  const { icon, iconBg, btnBg } = getVariantStyles()

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-xs animate-in fade-in duration-200">
      <div className="relative w-full max-w-md rounded-2xl border border-gray-200 bg-white p-5 sm:p-6 shadow-2xl dark:border-gray-800 dark:bg-gray-900 space-y-4">
        {/* Top Header */}
        <div className="flex items-start justify-between">
          <div className="flex items-center gap-3">
            <div className={`flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl ${iconBg}`}>
              {icon}
            </div>
            <div>
              <h3 className="font-bold text-sm sm:text-base text-gray-900 dark:text-white">
                {title}
              </h3>
              <p className="text-xs text-gray-500 dark:text-gray-400 mt-0.5 leading-relaxed">
                {description}
              </p>
            </div>
          </div>
          <button
            type="button"
            onClick={onClose}
            disabled={loading}
            className="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-800 dark:hover:text-gray-200 transition cursor-pointer"
          >
            <X className="h-4 w-4" />
          </button>
        </div>

        {/* Action Buttons */}
        <div className="flex items-center justify-end gap-2.5 pt-3 border-t border-gray-100 dark:border-gray-800">
          <button
            type="button"
            onClick={onClose}
            disabled={loading}
            className="h-9 px-4 rounded-xl border border-gray-200 bg-white text-xs font-bold text-gray-700 hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800 transition cursor-pointer disabled:opacity-50"
          >
            {cancelText}
          </button>
          <button
            type="button"
            onClick={onConfirm}
            disabled={loading}
            className={`h-9 px-5 rounded-xl text-xs font-bold shadow-theme-xs transition flex items-center justify-center gap-1.5 cursor-pointer disabled:opacity-60 ${btnBg}`}
          >
            {loading && <Loader2 className="h-3.5 w-3.5 animate-spin" />}
            <span>{loading ? "Memproses..." : confirmText}</span>
          </button>
        </div>
      </div>
    </div>
  )
}

export default ConfirmDialog
