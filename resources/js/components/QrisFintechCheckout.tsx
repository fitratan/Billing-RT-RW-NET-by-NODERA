import * as React from "react"
import {
  Copy,
  ChevronDown,
  Download,
  RefreshCw,
  X,
  Loader2,
  CheckCircle2,
  AlertCircle,
  Clock,
  ArrowLeft,
  ArrowRight,
  ExternalLink,
} from "lucide-react"
import { cn } from "@/lib/utils"

export interface QrisFintechInvoiceDetails {
  customerName?: string | null
  packageName?: string | null
  packagePrice?: number | string | null
  adminFee?: number | string | null
  uniqueCode?: number | null
  period?: string | null
  dueDate?: string | null
  subtotal?: number | null
  total?: number | null
}

export interface QrisFintechCheckoutProps {
  // Store / Header
  storeName?: string | null
  merchantName?: string | null
  merchantCity?: string | null
  nmid?: string | null
  acquirerName?: string | null

  // Order Details
  orderId?: string | null
  amount: number | string
  amountFormatted?: string | null
  invoiceDetails?: QrisFintechInvoiceDetails | null

  // QR Code & Checkout URL
  qrSvg?: string | null
  qrImageUrl?: string | null
  checkoutUrl?: string | null
  snapToken?: string | null
  gateway?: string | null

  // Countdown
  secondsLeft?: number | null
  timeoutMinutes?: number
  expiresAt?: string | null

  // Status Check Actions
  onCheckStatus?: () => Promise<boolean | void> | boolean | void
  isCheckingStatus?: boolean
  isPaid?: boolean
  isRejected?: boolean
  rejectedTitle?: string
  rejectedMessage?: string
  userSaldoAfterPaid?: number | null

  // Success View Customization (Consistent with QRIS theme)
  successBadgeText?: string
  successTitle?: string
  successMessage?: string
  successReceiptRows?: Array<{
    label: string
    value: React.ReactNode
    isMono?: boolean
    isHighlight?: boolean
    highlightColor?: string
  }>
  successActionText?: string
  successActionUrl?: string
  onSuccessAction?: () => void
  secondaryActionText?: string
  secondaryActionUrl?: string
  onSecondaryAction?: () => void

  // General Callbacks
  onClose?: () => void
  onSuccess?: () => void
  onRegenerate?: () => void
  className?: string
  showAsCard?: boolean

  // Cancellation confirmation
  confirmCancelOnClose?: boolean
  cancelConfirmationTitle?: string
  cancelConfirmationMessage?: string
  cancelConfirmText?: string
  cancelKeepText?: string
}

export function QrisFintechCheckout({
  storeName,
  merchantName,
  merchantCity,
  nmid,
  acquirerName = "NODERA PAY",
  orderId,
  amount,
  amountFormatted,
  invoiceDetails,
  qrSvg,
  qrImageUrl,
  checkoutUrl,
  snapToken,
  gateway,
  secondsLeft: initialSecondsLeft,
  timeoutMinutes = 5,
  expiresAt,
  onCheckStatus,
  isCheckingStatus: externalChecking = false,
  isPaid = false,
  isRejected = false,
  rejectedTitle,
  rejectedMessage,
  userSaldoAfterPaid,
  successBadgeText,
  successTitle,
  successMessage,
  successReceiptRows,
  successActionText,
  successActionUrl,
  onSuccessAction,
  secondaryActionText,
  secondaryActionUrl,
  onSecondaryAction,
  onClose,
  onSuccess,
  onRegenerate,
  className,
  showAsCard = true,
  confirmCancelOnClose = true,
  cancelConfirmationTitle,
  cancelConfirmationMessage,
  cancelConfirmText,
  cancelKeepText,
}: QrisFintechCheckoutProps) {
  const [showCancelModal, setShowCancelModal] = React.useState<boolean>(false)
  const [showDetails, setShowDetails] = React.useState<boolean>(false)
  const [showInstructions, setShowInstructions] = React.useState<boolean>(false)
  const [copiedType, setCopiedType] = React.useState<string | null>(null)
  const [internalChecking, setInternalChecking] = React.useState<boolean>(false)
  const [statusAlert, setStatusAlert] = React.useState<{
    type: "success" | "info" | "error"
    message: string
  } | null>(null)

  const handleHeaderClose = () => {
    if (isPaid || !confirmCancelOnClose) {
      onClose?.()
      return
    }
    setShowCancelModal(true)
  }

  // Real-time ticking countdown with strictly integer seconds (never fractional)
  const [seconds, setSeconds] = React.useState<number | null>(() => {
    if (initialSecondsLeft !== undefined && initialSecondsLeft !== null) {
      return Math.max(0, Math.floor(Number(initialSecondsLeft)))
    }
    if (expiresAt) {
      const diff = Math.floor((new Date(expiresAt).getTime() - Date.now()) / 1000)
      return diff > 0 ? diff : 0
    }
    return Math.floor((timeoutMinutes || 15) * 60)
  })

  React.useEffect(() => {
    if (initialSecondsLeft !== undefined && initialSecondsLeft !== null) {
      setSeconds(Math.max(0, Math.floor(Number(initialSecondsLeft))))
    } else if (expiresAt) {
      const diff = Math.floor((new Date(expiresAt).getTime() - Date.now()) / 1000)
      setSeconds(diff > 0 ? diff : 0)
    } else if (timeoutMinutes) {
      setSeconds(Math.floor(timeoutMinutes * 60))
    }
  }, [initialSecondsLeft, expiresAt, timeoutMinutes])

  React.useEffect(() => {
    if (seconds === null || seconds <= 0) return

    const timer = setInterval(() => {
      setSeconds((prev) => {
        if (prev === null || prev <= 1) {
          clearInterval(timer)
          return 0
        }
        return Math.floor(prev) - 1
      })
    }, 1000)

    return () => clearInterval(timer)
  }, [seconds])

  // Direct Midtrans Snap Popup Trigger if snapToken is present
  const triggerSnapPopup = React.useCallback(() => {
    if (!snapToken) {
      if (checkoutUrl) window.location.href = checkoutUrl
      return
    }

    const loadScript = (): Promise<void> => {
      return new Promise((resolve) => {
        if ((window as any).snap) {
          resolve()
          return
        }
        const existing = document.getElementById("midtrans-snap-script") as HTMLScriptElement | null
        if (existing) {
          existing.addEventListener("load", () => resolve())
          return
        }
        const script = document.createElement("script")
        script.id = "midtrans-snap-script"
        script.src = "https://app.midtrans.com/snap/snap.js"
        script.async = true
        script.onload = () => resolve()
        script.onerror = () => resolve()
        document.body.appendChild(script)
      })
    }

    loadScript().then(() => {
      if ((window as any).snap && typeof (window as any).snap.pay === "function") {
        try {
          ;(window as any).snap.pay(snapToken, {
            onSuccess: function () {
              onSuccess?.()
            },
            onPending: function () {
              onCheckStatus?.()
            },
            onError: function () {
              if (checkoutUrl) window.location.href = checkoutUrl
            },
            onClose: function () {
              // Popup closed by user
            },
          })
        } catch {
          if (checkoutUrl) window.location.href = checkoutUrl
        }
      } else if (checkoutUrl) {
        window.location.href = checkoutUrl
      }
    })
  }, [snapToken, checkoutUrl, onSuccess, onCheckStatus])

  // Direct DOKU Checkout Popup Trigger
  const triggerDokuPopup = React.useCallback(() => {
    if (!checkoutUrl) return
    const loadScript = (): Promise<void> => {
      return new Promise((resolve) => {
        if ((window as any).loadJokulCheckout) {
          resolve()
          return
        }
        const existing = document.getElementById("doku-jokul-script") as HTMLScriptElement | null
        if (existing) {
          existing.addEventListener("load", () => resolve())
          return
        }
        const script = document.createElement("script")
        script.id = "doku-jokul-script"
        script.src = "https://jokul.doku.com/jokul-checkout-js/v1/jokul-checkout-1.0.0.js"
        script.async = true
        script.onload = () => resolve()
        script.onerror = () => resolve()
        document.body.appendChild(script)
      })
    }

    loadScript().then(() => {
      if (typeof (window as any).loadJokulCheckout === "function") {
        try {
          ;(window as any).loadJokulCheckout(checkoutUrl)
        } catch {
          window.location.href = checkoutUrl
        }
      } else {
        window.location.href = checkoutUrl
      }
    })
  }, [checkoutUrl])

  React.useEffect(() => {
    if (snapToken) {
      triggerSnapPopup()
    } else if (gateway === 'doku' && checkoutUrl) {
      triggerDokuPopup()
    }
  }, [snapToken, gateway, checkoutUrl, triggerSnapPopup, triggerDokuPopup])

  const formatCountdown = (secs: number | null) => {
    const totalSecs = secs !== null ? Math.max(0, Math.floor(Number(secs))) : Math.floor((timeoutMinutes || 15) * 60)
    if (totalSecs <= 0) return "00:00:00"
    const h = Math.floor(totalSecs / 3600)
    const m = Math.floor((totalSecs % 3600) / 60)
    const s = Math.floor(totalSecs % 60)
    if (h > 0) {
      return `${String(h).padStart(2, "0")}:${String(m).padStart(2, "0")}:${String(s).padStart(2, "0")}`
    }
    return `${String(m).padStart(2, "0")}:${String(s).padStart(2, "0")}`
  }

  const isExpired = !isPaid && seconds !== null && seconds <= 0
  const isChecking = externalChecking || internalChecking

  const displayAmount = amountFormatted
    ? amountFormatted
    : typeof amount === "number"
    ? `Rp ${amount.toLocaleString("id-ID")}`
    : `Rp ${amount}`

  const cleanAmountString = typeof amount === "number" ? String(amount) : String(amount).replace(/[^0-9]/g, "")

  const handleCopy = (text: string, type: string) => {
    if (!text) return
    navigator.clipboard.writeText(text)
    setCopiedType(type)
    setTimeout(() => setCopiedType(null), 2000)
  }

  // Convert SVG / Image to PNG for clean download to phone gallery
  const handleDownloadQris = () => {
    const src = qrSvg || qrImageUrl
    if (!src) return

    const fallbackDownload = (dataUrl: string) => {
      const link = document.createElement("a")
      link.href = dataUrl
      link.download = `QRIS-${orderId || "payment"}.png`
      document.body.appendChild(link)
      link.click()
      document.body.removeChild(link)
    }

    try {
      if (typeof src === "string" && src.trim().startsWith("<svg")) {
        const blob = new Blob([src], { type: "image/svg+xml;charset=utf-8" })
        const url = URL.createObjectURL(blob)
        const img = new Image()
        img.onload = () => {
          const canvas = document.createElement("canvas")
          canvas.width = 800
          canvas.height = 800
          const ctx = canvas.getContext("2d")
          if (ctx) {
            ctx.fillStyle = "#FFFFFF"
            ctx.fillRect(0, 0, canvas.width, canvas.height)
            ctx.drawImage(img, 0, 0, canvas.width, canvas.height)
            const pngUrl = canvas.toDataURL("image/png")
            fallbackDownload(pngUrl)
            URL.revokeObjectURL(url)
            return
          }
          fallbackDownload(url)
          URL.revokeObjectURL(url)
        }
        img.onerror = () => {
          fallbackDownload(url)
          URL.revokeObjectURL(url)
        }
        img.src = url
        return
      }

      const img = new Image()
      img.crossOrigin = "anonymous"
      img.onload = () => {
        const canvas = document.createElement("canvas")
        canvas.width = img.naturalWidth || 600
        canvas.height = img.naturalHeight || 600
        const ctx = canvas.getContext("2d")
        if (ctx) {
          ctx.fillStyle = "#FFFFFF"
          ctx.fillRect(0, 0, canvas.width, canvas.height)
          ctx.drawImage(img, 0, 0, canvas.width, canvas.height)
          const pngUrl = canvas.toDataURL("image/png")
          fallbackDownload(pngUrl)
          return
        }
        fallbackDownload(src)
      }
      img.onerror = () => {
        fallbackDownload(src)
      }
      img.src = src
    } catch {
      fallbackDownload(src)
    }
  }

  const handleCheckStatusClick = async () => {
    if (isChecking) return

    setStatusAlert(null)
    setInternalChecking(true)

    try {
      if (onCheckStatus) {
        const result = await onCheckStatus()
        if (result === true) {
          setStatusAlert({
            type: "success",
            message: "Pembayaran berhasil diverifikasi!",
          })
          if (onSuccess) {
            setTimeout(() => onSuccess(), 1000)
          } else if (onClose) {
            setTimeout(() => onClose(), 1200)
          }
          return
        }
      }

      // Default notification
      setTimeout(() => {
        setStatusAlert({
          type: "info",
          message: "Pembayaran belum terdeteksi. Silakan selesaikan pembayaran di aplikasi m-Banking / E-Wallet Anda.",
        })
      }, 700)
    } catch {
      setStatusAlert({
        type: "error",
        message: "Gagal memeriksa status pembayaran. Silakan coba kembali.",
      })
    } finally {
      setTimeout(() => setInternalChecking(false), 800)
    }
  }

  const isRawSvg = typeof qrSvg === "string" && qrSvg.trim().startsWith("<svg")

  const resolvedQrSrc = React.useMemo(() => {
    if (typeof qrImageUrl === "string" && (qrImageUrl.startsWith("http") || qrImageUrl.startsWith("data:image"))) {
      return qrImageUrl
    }
    if (typeof qrSvg === "string" && (qrSvg.startsWith("http") || qrSvg.startsWith("data:image"))) {
      return qrSvg
    }
    const rawCode = (typeof qrSvg === "string" && !qrSvg.trim().startsWith("<svg") ? qrSvg : "") || (typeof qrImageUrl === "string" ? qrImageUrl : "")
    if (rawCode && (rawCode.startsWith("000201") || rawCode.length > 15)) {
      return `https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=${encodeURIComponent(rawCode)}`
    }
    return null
  }, [qrSvg, qrImageUrl])

  return (
    <div
      className={cn(
        "relative w-full max-w-[360px] sm:max-w-[380px] mx-auto bg-white text-gray-900 rounded-2xl overflow-hidden text-left select-none font-sans transition-all duration-200",
        showAsCard && "shadow-2xl border border-gray-200/80",
        className
      )}
    >
      {/* 1. Deep Navy Header Bar (Authentic Fintech Signature Style) */}
      <div className="bg-[#052A4E] text-white px-4 py-3 flex items-center justify-between shadow-sm">
        <span className="font-extrabold text-xs sm:text-sm tracking-wide uppercase truncate">
          {storeName || merchantName || "QRIS Merchant"}
        </span>
        {isPaid ? (
          <span className="inline-flex items-center gap-1 text-[11px] font-bold bg-emerald-500/20 text-emerald-300 px-2 py-0.5 rounded-full border border-emerald-400/30">
            <CheckCircle2 className="w-3 h-3 text-emerald-400" />
            <span>LUNAS</span>
          </span>
        ) : onClose ? (
          <button
            type="button"
            onClick={handleHeaderClose}
            className="text-white/80 hover:text-white transition-colors p-0.5 cursor-pointer ml-auto"
            title="Tutup"
          >
            <X className="w-5 h-5 stroke-[2.5]" />
          </button>
        ) : null}
      </div>

      {/* RENDER VIEW ACCORDING TO PAYMENT STATE */}
      {isPaid ? (
        /* ============================================================== */
        /* STATE A: PAYMENT SUCCESS / LUNAS (Consistent Fintech Theme)    */
        /* ============================================================== */
        <div className="animate-in fade-in zoom-in-95 duration-200">
          {/* Amount Header */}
          <div className="p-4 sm:p-5 pb-2.5 bg-white">
            <div className="flex items-center gap-2">
              <span className="text-2xl sm:text-[26px] font-extrabold text-gray-950 font-display tabular-nums tracking-tight">
                {displayAmount}
              </span>
            </div>
            <div className="mt-1 flex items-center justify-between text-xs text-gray-600">
              <span className="font-mono">Order ID #{orderId || "PEMBAYARAN"}</span>
              <span className="inline-flex items-center gap-1 font-semibold text-emerald-600">
                <CheckCircle2 className="w-3.5 h-3.5" /> Lunas
              </span>
            </div>
          </div>

          {/* Success Banner Strip */}
          <div className="bg-emerald-50 border-y border-emerald-200 py-2 px-4 text-center">
            <span className="text-xs font-bold text-emerald-700 flex items-center justify-center gap-1.5">
              <CheckCircle2 className="w-4 h-4 text-emerald-600 shrink-0" />
              <span>{successBadgeText || "Pembayaran Berhasil Diverifikasi!"}</span>
            </span>
          </div>

          {/* Success Content Box */}
          <div className="p-6 sm:p-8 text-center space-y-4">
            <div className="w-16 h-16 sm:w-20 sm:h-20 bg-emerald-50 border-2 border-emerald-200 rounded-full flex items-center justify-center mx-auto text-emerald-600 shadow-sm animate-in zoom-in-95 duration-300">
              <CheckCircle2 className="w-10 h-10 sm:w-12 sm:h-12" />
            </div>

            <div>
              <h3 className="text-lg sm:text-xl font-black text-gray-950">
                {successTitle || (typeof userSaldoAfterPaid === "number" ? "Deposit Saldo Berhasil!" : "Pembayaran Berhasil!")}
              </h3>
              <p className="text-xs sm:text-sm text-gray-600 mt-1 max-w-sm mx-auto leading-relaxed">
                {successMessage || (typeof userSaldoAfterPaid === "number" ? "Saldo Anda telah berhasil ditambahkan. Transaksi telah diverifikasi secara otomatis oleh sistem." : "Transaksi Anda telah diverifikasi secara otomatis oleh sistem.")}
              </p>
            </div>

            {/* Receipt Breakdown */}
            <div className="rounded-xl border border-gray-200 bg-slate-50 p-3.5 sm:p-4 text-left text-xs sm:text-sm space-y-2.5">
              {successReceiptRows && successReceiptRows.length > 0 ? (
                successReceiptRows.map((row, idx) => (
                  <div key={idx} className="flex justify-between items-center gap-2">
                    <span className="text-gray-500 shrink-0">{row.label}</span>
                    <span
                      className={cn(
                        "text-right truncate",
                        row.isMono ? "font-mono font-bold" : "font-semibold",
                        row.isHighlight ? (row.highlightColor || "text-emerald-600") : "text-gray-900"
                      )}
                    >
                      {row.value}
                    </span>
                  </div>
                ))
              ) : (
                <>
                  <div className="flex justify-between">
                    <span className="text-gray-500">Nomor Invoice</span>
                    <span className="font-mono font-bold text-gray-900">{orderId}</span>
                  </div>
                  <div className="flex justify-between">
                    <span className="text-gray-500">Metode</span>
                    <span className="font-semibold text-gray-900">QRIS (Otomatis)</span>
                  </div>
                  <div className="flex justify-between">
                    <span className="text-gray-500">Nominal Masuk</span>
                    <span className="font-mono font-bold text-emerald-600">{displayAmount}</span>
                  </div>
                  {typeof userSaldoAfterPaid === "number" && (
                    <div className="flex justify-between border-t border-gray-200 pt-2 font-bold">
                      <span className="text-gray-700">Saldo Akun Sekarang</span>
                      <span className="font-mono text-[#0084E3]">
                        Rp {userSaldoAfterPaid.toLocaleString("id-ID")}
                      </span>
                    </div>
                  )}
                </>
              )}
            </div>

            <div className="pt-2 space-y-2.5">
              {successActionUrl ? (
                <a
                  href={successActionUrl}
                  className="w-full py-3.5 px-4 rounded-xl bg-[#052A4E] hover:bg-[#031E38] text-white text-sm font-bold shadow-md active:scale-[0.99] transition-all cursor-pointer flex items-center justify-center gap-2 text-center"
                >
                  <span>{successActionText || "Selesai"}</span>
                  <ArrowRight className="w-4 h-4 text-white" />
                </a>
              ) : (
                <button
                  type="button"
                  onClick={onSuccessAction || onClose}
                  className="w-full py-3.5 px-4 rounded-xl bg-[#052A4E] hover:bg-[#031E38] text-white text-sm font-bold shadow-md active:scale-[0.99] transition-all cursor-pointer flex items-center justify-center gap-2"
                >
                  <CheckCircle2 className="w-4 h-4 text-emerald-400" />
                  <span>{successActionText || (typeof userSaldoAfterPaid === "number" ? "Selesai & Lihat Saldo" : "Selesai")}</span>
                </button>
              )}

              {secondaryActionUrl ? (
                <a
                  href={secondaryActionUrl}
                  target="_blank"
                  rel="noreferrer"
                  className="w-full py-2.5 px-4 rounded-xl border border-gray-300 bg-white hover:bg-gray-50 text-gray-700 text-xs sm:text-sm font-semibold transition-all cursor-pointer flex items-center justify-center gap-2 text-center shadow-xs"
                >
                  <span>{secondaryActionText || "Butuh Bantuan? Hubungi Admin"}</span>
                </a>
              ) : secondaryActionText ? (
                <button
                  type="button"
                  onClick={onSecondaryAction}
                  className="w-full py-2.5 px-4 rounded-xl border border-gray-300 bg-white hover:bg-gray-50 text-gray-700 text-xs sm:text-sm font-semibold transition-all cursor-pointer flex items-center justify-center gap-2 shadow-xs"
                >
                  <span>{secondaryActionText}</span>
                </button>
              ) : null}
            </div>
          </div>
        </div>
      ) : isRejected ? (
        /* ============================================================== */
        /* STATE D: TRANSACTION REJECTED / DITOLAK                        */
        /* ============================================================== */
        <div className="animate-in fade-in zoom-in-95 duration-200">
          {/* Amount Header */}
          <div className="p-5 sm:p-6 pb-3 bg-white">
            <div className="flex items-center gap-2">
              <span className="text-2xl sm:text-3xl md:text-4xl font-extrabold text-gray-950 font-display tabular-nums tracking-tight">
                {displayAmount}
              </span>
            </div>
            <div className="mt-1 flex items-center justify-between text-xs sm:text-sm text-gray-600">
              <span className="font-mono">Order ID #{orderId || "TOPUP"}</span>
              <span className="inline-flex items-center gap-1 font-bold text-rose-600">
                <X className="w-3.5 h-3.5" /> Ditolak
              </span>
            </div>
          </div>

          {/* Rejected Banner Strip */}
          <div className="bg-rose-50 border-y border-rose-200 py-2.5 px-5 text-center">
            <span className="text-xs sm:text-sm font-bold text-rose-700 flex items-center justify-center gap-1.5">
              <AlertCircle className="w-4 h-4 text-rose-600 shrink-0" />
              <span>Permintaan Transaksi Ditolak</span>
            </span>
          </div>

          {/* Rejected Content Box */}
          <div className="p-6 sm:p-8 text-center space-y-4">
            <div className="w-16 h-16 sm:w-20 sm:h-20 bg-rose-50 border-2 border-rose-200 rounded-full flex items-center justify-center mx-auto text-rose-600 shadow-sm animate-in zoom-in-95 duration-300">
              <X className="w-10 h-10 sm:w-12 sm:h-12" />
            </div>

            <div>
              <h3 className="text-lg sm:text-xl font-black text-gray-950">
                {rejectedTitle || "Permintaan Topup Ditolak"}
              </h3>
              <p className="text-xs sm:text-sm text-gray-600 mt-1 max-w-xs mx-auto leading-relaxed">
                {rejectedMessage || "Permintaan topup ini telah ditolak oleh Admin / Superadmin. Sesi barcode QRIS telah ditutup dan dinonaktifkan."}
              </p>
            </div>

            <div className="space-y-2.5 pt-1">
              {onClose && (
                <button
                  type="button"
                  onClick={onClose}
                  className="w-full py-3 px-4 rounded-xl border border-gray-300 bg-white hover:bg-gray-50 text-gray-700 text-xs sm:text-sm font-bold active:scale-[0.99] transition-all cursor-pointer flex items-center justify-center gap-2"
                >
                  <ArrowLeft className="w-4 h-4" />
                  <span>Kembali ke Menu Topup</span>
                </button>
              )}
            </div>
          </div>
        </div>
      ) : isExpired ? (
        /* ============================================================== */
        /* STATE B: QRIS EXPIRED / KEDALUWARSA (Consistent Fintech Theme)  */
        /* ============================================================== */
        <div className="animate-in fade-in zoom-in-95 duration-200">
          {/* Amount Header */}
          <div className="p-5 sm:p-6 pb-3 bg-white">
            <div className="flex items-center gap-2">
              <span className="text-2xl sm:text-3xl md:text-4xl font-extrabold text-gray-950 font-display tabular-nums tracking-tight">
                {displayAmount}
              </span>
            </div>
            <div className="mt-1 flex items-center justify-between text-xs sm:text-sm text-gray-600">
              <span className="font-mono">Order ID #{orderId || "TOPUP"}</span>
              <span className="inline-flex items-center gap-1 font-semibold text-rose-600">
                <Clock className="w-3.5 h-3.5" /> Kedaluwarsa
              </span>
            </div>
          </div>

          {/* Expired Banner Strip */}
          <div className="bg-rose-50 border-y border-rose-200 py-2.5 px-5 text-center">
            <span className="text-xs sm:text-sm font-bold text-rose-700 flex items-center justify-center gap-1.5">
              <AlertCircle className="w-4 h-4 text-rose-600 shrink-0" />
              <span>Waktu Pembayaran Habis (Kedaluwarsa)</span>
            </span>
          </div>

          {/* Expired Content Box */}
          <div className="p-6 sm:p-8 text-center space-y-4">
            <div className="w-16 h-16 sm:w-20 sm:h-20 bg-rose-50 border-2 border-rose-200 rounded-full flex items-center justify-center mx-auto text-rose-500 shadow-sm animate-in zoom-in-95 duration-300">
              <Clock className="w-10 h-10 sm:w-12 sm:h-12" />
            </div>

            <div>
              <h3 className="text-lg sm:text-xl font-black text-gray-950">QRIS Telah Kedaluwarsa</h3>
              <p className="text-xs sm:text-sm text-gray-600 mt-1 max-w-xs mx-auto leading-relaxed">
                Batas waktu pembayaran {timeoutMinutes} menit telah berakhir untuk menjaga keamanan transaksi. Kode QR ini tidak dapat digunakan lagi.
              </p>
            </div>

            <div className="bg-amber-50/80 border border-amber-200 rounded-xl p-3 sm:p-4 text-left text-xs text-amber-900 space-y-1">
              <p className="font-bold flex items-center gap-1.5 text-amber-800">
                <AlertCircle className="w-4 h-4 shrink-0" />
                Pemberitahuan Keamanan:
              </p>
              <p className="text-[11px] sm:text-xs leading-relaxed text-amber-800/90">
                Jangan melakukan transfer ke barcode yang sudah kedaluwarsa. Silakan klik tombol perbarui di bawah untuk mendapatkan sesi pembayaran baru.
              </p>
            </div>

            <div className="space-y-2.5 pt-1">
              {onRegenerate && (
                <button
                  type="button"
                  onClick={onRegenerate}
                  className="w-full py-3.5 px-4 rounded-xl bg-[#0084E3] hover:bg-[#0070C0] text-white text-sm font-bold shadow-md active:scale-[0.99] transition-all cursor-pointer flex items-center justify-center gap-2"
                >
                  <RefreshCw className="w-4 h-4" />
                  <span>Perbarui / Buat QRIS Baru</span>
                </button>
              )}

              {onClose && (
                <button
                  type="button"
                  onClick={onClose}
                  className="w-full py-3 px-4 rounded-xl border border-gray-300 bg-white hover:bg-gray-50 text-gray-700 text-xs sm:text-sm font-semibold active:scale-[0.99] transition-all cursor-pointer flex items-center justify-center gap-2"
                >
                  <ArrowLeft className="w-4 h-4" />
                  <span>Kembali ke Menu Topup</span>
                </button>
              )}
            </div>
          </div>
        </div>
      ) : (
        /* ============================================================== */
        /* STATE C: ACTIVE / PENDING (Normal QRIS Checkout Flow)           */
        /* ============================================================== */
        <div>
          {/* 2. Amount & Order ID & Rincian Header */}
          <div className="p-4 sm:p-5 pb-2.5 bg-white">
            {/* Large Amount */}
            <div className="flex items-center gap-2">
              <span className="text-2xl sm:text-[26px] font-extrabold text-gray-950 font-display tabular-nums tracking-tight">
                {displayAmount}
              </span>
              <button
                type="button"
                onClick={() => handleCopy(cleanAmountString, "amount")}
                className="text-[#0084E3] hover:text-[#006BB8] transition-colors p-1 cursor-pointer"
                title="Salin nominal tagihan"
              >
                {copiedType === "amount" ? (
                  <span className="text-[10px] font-bold text-emerald-600 bg-emerald-50 border border-emerald-200 px-1.5 py-0.5 rounded">
                    Tersalin!
                  </span>
                ) : (
                  <Copy className="w-4 h-4" />
                )}
              </button>
            </div>

            {/* Order ID & Rincian Toggle */}
            <div className="mt-1 flex items-center justify-between text-xs text-gray-600">
              <div className="flex items-center gap-1 font-mono">
                <span>Order ID #{orderId || "SIM-DEMO"}</span>
                <button
                  type="button"
                  onClick={() => handleCopy(orderId || "SIM-DEMO", "orderId")}
                  className="text-[#0084E3] hover:text-[#006BB8] transition-colors p-0.5 cursor-pointer"
                  title="Salin Order ID"
                >
                  {copiedType === "orderId" ? (
                    <span className="text-[9px] font-bold text-emerald-600">✓</span>
                  ) : (
                    <Copy className="w-3.5 h-3.5" />
                  )}
                </button>
              </div>

              <button
                type="button"
                onClick={() => setShowDetails(!showDetails)}
                className="flex items-center gap-1 text-xs font-semibold text-[#0084E3] hover:underline cursor-pointer"
              >
                <span>Rincian</span>
                <ChevronDown className={cn("w-3.5 h-3.5 transition-transform duration-200", showDetails && "rotate-180")} />
              </button>
            </div>

            {/* Expandable Rincian Drawer */}
            {showDetails && (
              <div className="mt-2.5 p-3 bg-slate-50 border border-slate-200 rounded-xl space-y-1.5 text-xs text-slate-700 animate-in fade-in duration-150">
                {invoiceDetails?.customerName && (
                  <div className="flex justify-between">
                    <span className="text-slate-500">Nama Pelanggan:</span>
                    <span className="font-semibold text-slate-900">{invoiceDetails.customerName}</span>
                  </div>
                )}
                {invoiceDetails?.packageName && (
                  <div className="flex justify-between">
                    <span className="text-slate-500">Paket Layanan:</span>
                    <span className="font-semibold text-slate-900">{invoiceDetails.packageName}</span>
                  </div>
                )}
                {invoiceDetails?.period && (
                  <div className="flex justify-between">
                    <span className="text-slate-500">Periode Tagihan:</span>
                    <span className="font-semibold text-slate-900">{invoiceDetails.period}</span>
                  </div>
                )}
                {invoiceDetails?.dueDate && (
                  <div className="flex justify-between">
                    <span className="text-slate-500">Jatuh Tempo:</span>
                    <span className="font-semibold text-slate-900">{invoiceDetails.dueDate}</span>
                  </div>
                )}
                {/* Harga Pokok / Paket / Subtotal */}
                {(invoiceDetails?.packagePrice != null || (invoiceDetails?.subtotal != null && (Number(invoiceDetails.total || amount) !== Number(invoiceDetails.subtotal)))) && (
                  <div className="flex justify-between">
                    <span className="text-slate-500">Harga Paket:</span>
                    <span className="font-semibold text-slate-900">
                      {typeof (invoiceDetails.packagePrice ?? invoiceDetails.subtotal) === 'number'
                        ? `Rp ${(invoiceDetails.packagePrice ?? invoiceDetails.subtotal)!.toLocaleString('id-ID')}`
                        : (invoiceDetails.packagePrice ?? invoiceDetails.subtotal)}
                    </span>
                  </div>
                )}
                {/* Biaya Layanan QRIS (Gabungan Fee & Offset) */}
                {(() => {
                  const baseAmt = Number(invoiceDetails?.packagePrice ?? invoiceDetails?.subtotal ?? amount) || 0
                  const totAmt = Number(invoiceDetails?.total ?? amount) || 0
                  const explicitFee = (Number(invoiceDetails?.adminFee || 0) + Number(invoiceDetails?.uniqueCode || 0))
                  const finalFee = explicitFee > 0 ? explicitFee : Math.max(0, totAmt - baseAmt)
                  if (finalFee <= 0) return null
                  return (
                    <div className="flex justify-between">
                      <span className="text-slate-500">Biaya Layanan (QRIS):</span>
                      <span className="font-semibold text-slate-900">
                        +Rp {finalFee.toLocaleString('id-ID')}
                      </span>
                    </div>
                  )
                })()}
                <div className="flex justify-between border-t border-slate-300 pt-1.5 font-bold text-slate-900">
                  <span>Total Tagihan:</span>
                  <span className="font-mono text-emerald-700">{displayAmount}</span>
                </div>
              </div>
            )}
          </div>

          {/* 3. Countdown Strip */}
          <div className="bg-[#F1F5F9] border-y border-slate-200/90 py-2 px-4 text-center">
            <span className="text-xs font-medium text-gray-800 tabular-nums flex items-center justify-center gap-1.5">
              <span>Bayar dalam</span>
              <span className="font-mono font-bold text-gray-950 text-xs sm:text-sm">{formatCountdown(seconds)}</span>
            </span>
          </div>

          {/* Standee Card */}
          <div className="px-4 pt-2.5 pb-1.5">
            <div className="relative bg-white rounded-xl border border-slate-200/90 shadow-sm p-3 pt-2 overflow-hidden text-center max-w-[260px] mx-auto">
              {/* Merchant Name & NMID */}
              <div className="pt-0.5 pb-0.5 relative z-10">
                <h4 className="font-extrabold text-xs sm:text-sm text-gray-950 tracking-tight px-1 line-clamp-1">
                  {merchantName || storeName || "QRIS Merchant"}
                </h4>
                {nmid && (
                  <div className="mt-0.5 flex items-center justify-center">
                    <span className="text-[10px] font-mono text-slate-500 font-semibold">
                      NMID: {nmid}
                    </span>
                  </div>
                )}
              </div>

              {/* QR Code Center with Viewfinder Brackets */}
              <div className="relative z-10 my-1 flex justify-center items-center">
                <div className="relative p-2 bg-white rounded-lg border border-slate-100 shadow-inner inline-block">
                  {/* Top-Left Corner Bracket */}
                  <div className="absolute top-1 left-1 w-3 h-3 border-t-2 border-l-2 border-[#0084E3] rounded-tl pointer-events-none" />
                  {/* Top-Right Corner Bracket */}
                  <div className="absolute top-1 right-1 w-3 h-3 border-t-2 border-r-2 border-[#0084E3] rounded-tr pointer-events-none" />
                  {/* Bottom-Left Corner Bracket */}
                  <div className="absolute bottom-1 left-1 w-3 h-3 border-b-2 border-l-2 border-[#0084E3] rounded-bl pointer-events-none" />
                  {/* Bottom-Right Corner Bracket */}
                  <div className="absolute bottom-1 right-1 w-3 h-3 border-b-2 border-r-2 border-[#0084E3] rounded-br pointer-events-none" />

                  {isRawSvg ? (
                    <div
                      className="w-44 h-44 sm:w-48 sm:h-48 object-contain mx-auto flex items-center justify-center [&>svg]:w-full [&>svg]:h-full"
                      dangerouslySetInnerHTML={{ __html: qrSvg! }}
                    />
                  ) : resolvedQrSrc ? (
                    <img
                      src={resolvedQrSrc}
                      alt="QRIS Barcode"
                      className="w-44 h-44 sm:w-48 sm:h-48 object-contain mx-auto transition-opacity duration-300"
                    />
                  ) : checkoutUrl ? (
                    <div className="w-44 h-44 sm:w-48 sm:h-48 flex flex-col items-center justify-center p-3 text-center bg-blue-50/60 rounded-xl border border-blue-200">
                      <ExternalLink className="w-7 h-7 text-[#0084E3] mb-2" />
                      <span className="text-[11px] font-bold text-gray-900 mb-2 leading-tight">
                        Bayar via {acquirerName || "Midtrans Snap"}
                      </span>
                      <a
                        href={checkoutUrl}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="w-full py-2 px-3 bg-[#0084E3] hover:bg-[#0070C0] text-white text-[11px] font-bold rounded-lg shadow-sm transition inline-flex items-center justify-center gap-1.5 cursor-pointer"
                      >
                        <span>Buka Pembayaran</span>
                        <ExternalLink className="w-3 h-3" />
                      </a>
                    </div>
                  ) : (
                    <div className="w-44 h-44 sm:w-48 sm:h-48 flex flex-col items-center justify-center border border-dashed border-gray-300 rounded-xl text-gray-400">
                      <Clock className="w-8 h-8 stroke-[1.5] mb-2 animate-pulse" />
                      <span className="text-xs font-semibold">Membuat Barcode QRIS...</span>
                    </div>
                  )}
                </div>
              </div>

              <p className="text-[10px] text-slate-500 font-medium mt-0.5 relative z-10">
                Arahkan kamera m-Banking atau E-Wallet Anda
              </p>

              {/* Card Footer: Acquirer Notice */}
              <div className="relative z-10 mt-1.5 pt-1 border-t border-slate-100 text-center text-[10px] text-slate-600">
                Dicetak oleh: <span className="font-bold text-slate-800">{acquirerName || "NODERA PAY"}</span>
              </div>
            </div>
          </div>

          {/* 5. Cara Bayar Accordion */}
          <div className="px-5 pt-2 pb-1">
            <button
              type="button"
              onClick={() => setShowInstructions(!showInstructions)}
              className="flex items-center gap-1.5 text-xs sm:text-sm font-bold text-[#0066CC] hover:underline cursor-pointer"
            >
              <div className="w-4 h-4 rounded-full bg-[#0066CC] text-white flex items-center justify-center text-[10px] font-bold">
                ?
              </div>
              <span>Cara bayar</span>
              <ChevronDown className={cn("w-3.5 h-3.5 transition-transform duration-200", showInstructions && "rotate-180")} />
            </button>

            {showInstructions && (
              <div className="mt-2.5 p-3.5 sm:p-4 bg-blue-50/70 border border-blue-100 rounded-xl text-xs sm:text-sm text-gray-700 space-y-2 animate-in fade-in duration-150">
                <ol className="list-decimal pl-4 space-y-1.5 leading-relaxed">
                  <li>
                    Buka aplikasi m-Banking atau E-Wallet pilihan Anda (BCA, Mandiri, BRI, BNI, GoPay, OVO, DANA, ShopeePay, LinkAja, dll).
                  </li>
                  <li>Pilih menu <strong>Scan</strong> atau <strong>Bayar QR</strong>.</li>
                  <li>
                    Arahkan kamera ke kode QRIS di atas (atau unggah dari galeri jika Anda telah menekan tombol <em>Download QRIS</em>).
                  </li>
                  <li>
                    Periksa nama merchant dan pastikan nominal tagihan sesuai hingga digit terakhir (<strong>{displayAmount}</strong>) agar transaksi terverifikasi instan tanpa konfirmasi manual.
                  </li>
                  <li>
                    Masukkan PIN Anda dan selesaikan transaksi. Pembayaran akan terkonfirmasi secara otomatis dalam beberapa detik.
                  </li>
                </ol>
              </div>
            )}
          </div>

          {/* 6. Action Buttons */}
          <div className="p-4 pt-2.5 space-y-2 border-t border-gray-100 mt-1">
            {/* Button 1: Download QR */}
            <button
              type="button"
              onClick={handleDownloadQris}
              className="w-full py-2.5 px-3.5 rounded-xl border border-gray-300 bg-white text-gray-800 text-xs font-semibold hover:bg-gray-50 active:scale-[0.99] transition-all flex items-center justify-center gap-2 cursor-pointer shadow-xs"
            >
              <Download className="w-3.5 h-3.5 text-gray-600" />
              <span>Download QR</span>
            </button>

            {/* Button 2: Cek status */}
            <button
              type="button"
              onClick={handleCheckStatusClick}
              disabled={isChecking}
              className="w-full py-2.5 px-3.5 rounded-xl bg-[#0084E3] text-white text-xs font-bold hover:bg-[#0070C0] active:scale-[0.99] transition-all flex items-center justify-center gap-2 cursor-pointer shadow-md disabled:opacity-60"
            >
              {isChecking ? (
                <>
                  <Loader2 className="w-3.5 h-3.5 animate-spin text-white" />
                  <span>Memeriksa status pembayaran...</span>
                </>
              ) : (
                <>
                  <RefreshCw className="w-3.5 h-3.5 text-white" />
                  <span>Cek status</span>
                </>
              )}
            </button>

            {/* Status Toast Notification */}
            {statusAlert && (
              <div
                className={cn(
                  "p-3 rounded-xl text-xs flex items-start gap-2.5 animate-in fade-in duration-200",
                  statusAlert.type === "success" && "bg-emerald-50 border border-emerald-200 text-emerald-800",
                  statusAlert.type === "info" && "bg-amber-50 border border-amber-200 text-amber-800",
                  statusAlert.type === "error" && "bg-rose-50 border border-rose-200 text-rose-800"
                )}
              >
                {statusAlert.type === "success" ? (
                  <CheckCircle2 className="w-4 h-4 text-emerald-600 shrink-0 mt-0.5" />
                ) : (
                  <AlertCircle className="w-4 h-4 text-amber-600 shrink-0 mt-0.5" />
                )}
                <span className="leading-snug">{statusAlert.message}</span>
              </div>
            )}
          </div>
        </div>
      )}

      {/* Cancellation Confirmation Modal Overlay */}
      {showCancelModal && (
        <div className="absolute inset-0 z-50 bg-gray-950/80 backdrop-blur-sm flex items-center justify-center p-4 sm:p-6 animate-in fade-in duration-200">
          <div className="w-full max-w-xs bg-white rounded-2xl p-5 shadow-2xl border border-gray-100 text-center space-y-4 animate-in zoom-in-95 duration-200">
            <div className="w-12 h-12 rounded-full bg-rose-50 border border-rose-100 flex items-center justify-center mx-auto text-rose-600">
              <AlertCircle className="w-6 h-6 stroke-[2.2]" />
            </div>
            <div className="space-y-1.5">
              <h4 className="text-base font-extrabold text-gray-950 font-display">
                {cancelConfirmationTitle || "Batalkan Transaksi?"}
              </h4>
              <p className="text-xs text-gray-600 leading-relaxed">
                {cancelConfirmationMessage || "Menutup halaman QRIS ini akan membatalkan pesanan/pendaftaran secara permanen. Apakah Anda yakin ingin keluar?"}
              </p>
            </div>
            <div className="space-y-2 pt-1">
              <button
                type="button"
                onClick={() => {
                  setShowCancelModal(false)
                  onClose?.()
                }}
                className="w-full py-2.5 px-4 rounded-xl bg-rose-600 hover:bg-rose-700 active:bg-rose-800 text-white text-xs font-bold transition-all shadow-sm shadow-rose-600/20 cursor-pointer"
              >
                {cancelConfirmText || "Ya, Batalkan Transaksi"}
              </button>
              <button
                type="button"
                onClick={() => setShowCancelModal(false)}
                className="w-full py-2.5 px-4 rounded-xl bg-gray-100 hover:bg-gray-200 active:bg-gray-300 text-gray-800 text-xs font-bold transition-all cursor-pointer"
              >
                {cancelKeepText || "Lanjutkan Pembayaran"}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}
