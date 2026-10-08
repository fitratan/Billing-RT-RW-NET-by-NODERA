import React, { useState, useEffect, useCallback, useRef } from "react"
import { router } from "@inertiajs/react"
import { Loader2, CheckCircle2, AlertCircle, MessageCircle } from "lucide-react"
import { QrisFintechCheckout } from "@/components/QrisFintechCheckout"

export interface PortalPaymentModalProps {
  isOpen: boolean
  onClose: () => void
  invoice: {
    id: number
    invoice_number: string
    amount: number
    paid?: boolean
    period?: string | null
    due_date?: string | null
    package_name?: string | null
  } | null
  customer?: {
    id?: number
    name?: string | null
    phone?: string | null
    package?: string | null
  } | null
  companyName?: string | null
  adminWa?: string | null
}

export function PortalPaymentModal({
  isOpen,
  onClose,
  invoice,
  customer,
  companyName,
  adminWa,
}: PortalPaymentModalProps) {
  const [loading, setLoading] = useState(true)
  const [qrSvg, setQrSvg] = useState<string | null>(null)
  const [qrImageUrl, setQrImageUrl] = useState<string | null>(null)
  const [checkoutUrl, setCheckoutUrl] = useState<string | null>(null)
  const [snapToken, setSnapToken] = useState<string | null>(null)
  const [errorMessage, setErrorMessage] = useState<string | null>(null)
  const [gatewayName, setGatewayName] = useState<string | null>(null)
  const [merchantName, setMerchantName] = useState<string | null>(null)
  const [merchantCity, setMerchantCity] = useState<string | null>(null)
  const [uniqueAmount, setUniqueAmount] = useState<number>(0)
  const [uniqueCode, setUniqueCode] = useState<number>(0)
  const [expiresAt, setExpiresAt] = useState<string | null>(null)
  const [timeoutMinutes, setTimeoutMinutes] = useState<number>(15)
  const [secondsLeft, setSecondsLeft] = useState<number>(900)
  const [isPaid, setIsPaid] = useState<boolean>(false)
  const [isSnapActive, setIsSnapActive] = useState<boolean>(false)

  const pollingRef = useRef<ReturnType<typeof setInterval> | null>(null)

  const stopPolling = useCallback(() => {
    if (pollingRef.current) {
      clearInterval(pollingRef.current)
      pollingRef.current = null
    }
  }, [])

  const checkStatus = useCallback(async () => {
    if (!invoice?.id) return
    try {
      const res = await fetch(`/api/v1/payments/qris/status/${invoice.id}`)
      const data = await res.json()
      if (data.success && data.paid) {
        setIsPaid(true)
        setIsSnapActive(false)
        stopPolling()
        setTimeout(() => {
          onClose()
          router.reload({ preserveScroll: true } as never)
        }, 1200)
      }
    } catch {
      // Ignore polling errors
    }
  }, [invoice, onClose, stopPolling])

  // Direct Midtrans Snap Popup Trigger
  const triggerSnapPopup = useCallback(
    (token: string, jsUrl?: string | null, cKey?: string | null, directUrl?: string | null) => {
      if (!token) {
        if (directUrl) {
          window.location.href = directUrl
        }
        return
      }

      setIsSnapActive(true)

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
          script.src = jsUrl || "https://app.midtrans.com/snap/snap.js"
          if (cKey) {
            script.setAttribute("data-client-key", cKey)
          }
          script.async = true
          script.onload = () => resolve()
          script.onerror = () => resolve()
          document.body.appendChild(script)
        })
      }

      loadScript().then(() => {
        if ((window as any).snap && typeof (window as any).snap.pay === "function") {
          try {
            ;(window as any).snap.pay(token, {
              onSuccess: function () {
                setIsPaid(true)
                setIsSnapActive(false)
                stopPolling()
                setTimeout(() => {
                  onClose()
                  router.reload({ preserveScroll: true } as never)
                }, 1200)
              },
              onPending: function () {
                checkStatus()
              },
              onError: function () {
                if (directUrl) {
                  window.location.href = directUrl
                }
              },
              onClose: function () {
                setIsSnapActive(false)
                onClose()
              },
            })
          } catch (err) {
            console.error("Midtrans snap.pay error:", err)
            if (directUrl) {
              window.location.href = directUrl
            }
          }
        } else if (directUrl) {
          window.location.href = directUrl
        }
      })
    },
    [checkStatus, onClose, stopPolling]
  )

  // Direct DOKU Checkout Popup Trigger
  const triggerDokuPopup = useCallback((dokuUrl: string, jsUrl?: string | null) => {
    if (!dokuUrl) return
    setIsSnapActive(true)

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
        script.src = jsUrl || "https://jokul.doku.com/jokul-checkout-js/v1/jokul-checkout-1.0.0.js"
        script.async = true
        script.onload = () => resolve()
        script.onerror = () => resolve()
        document.body.appendChild(script)
      })
    }

    loadScript().then(() => {
      if (typeof (window as any).loadJokulCheckout === "function") {
        try {
          ;(window as any).loadJokulCheckout(dokuUrl)
        } catch {
          window.location.href = dokuUrl
        }
      } else {
        window.location.href = dokuUrl
      }
    })
  }, [])

  const loadQris = useCallback(async () => {
    if (!invoice?.id) return
    setLoading(true)
    setIsPaid(Boolean(invoice.paid))

    try {
      const res = await fetch(`/api/v1/payments/qris/regenerate/${invoice.id}`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Accept: "application/json",
          "X-Requested-With": "XMLHttpRequest",
        },
      })

      const data = await res.json()
      if (data.success) {
        setErrorMessage(null)
        setQrSvg(data.qris_svg || null)
        setQrImageUrl(data.qris_image_url || data.qris_string || null)
        setCheckoutUrl(data.checkout_url || null)
        setSnapToken(data.snap_token || null)
        setGatewayName(data.gateway || null)
        setMerchantName(data.merchant_name || null)
        setMerchantCity(data.merchant_city || null)
        setUniqueAmount(data.unique_amount || invoice.amount)
        setUniqueCode(data.unique_code || 0)
        setExpiresAt(data.expires_at || null)
        if (data.timeout_minutes) {
          setTimeoutMinutes(Number(data.timeout_minutes))
        }
        if (data.duration_seconds !== undefined && data.duration_seconds !== null) {
          setSecondsLeft(Number(data.duration_seconds))
        } else if (data.expires_at) {
          const diff = Math.floor((new Date(data.expires_at).getTime() - Date.now()) / 1000)
          setSecondsLeft(diff > 0 ? diff : Number(data.timeout_minutes || 15) * 60)
        }
        if (data.paid) {
          setIsPaid(true)
        }

        // Direct Midtrans Snap Popup Launch (No intermediate template)
        if (data.gateway === "midtrans" || data.snap_token) {
          triggerSnapPopup(data.snap_token, data.snap_js_url, data.client_key, data.checkout_url)
        } else if (data.gateway === "doku" && data.checkout_url) {
          triggerDokuPopup(data.checkout_url, data.js_url)
        }
      } else {
        setErrorMessage(
          data.message ||
            "Pembayaran online otomatis belum diaktifkan oleh admin. Silakan hubungi admin untuk konfirmasi pembayaran."
        )
        setQrSvg(null)
        setQrImageUrl(null)
        setCheckoutUrl(null)
        setSnapToken(null)
        setGatewayName(null)
        setUniqueAmount(invoice.amount)
      }
    } catch (err: any) {
      setErrorMessage(err?.message || "Gagal memuat metode pembayaran online. Silakan hubungi admin.")
      setQrSvg(null)
      setQrImageUrl(null)
      setCheckoutUrl(null)
      setSnapToken(null)
      setGatewayName(null)
      setUniqueAmount(invoice.amount)
    } finally {
      setLoading(false)
    }
  }, [invoice, triggerSnapPopup])

  useEffect(() => {
    if (isOpen && invoice?.id && !invoice.paid) {
      loadQris()

      // Real-time auto-polling every 3.5 seconds
      stopPolling()
      pollingRef.current = setInterval(() => {
        checkStatus()
      }, 3500)
    } else {
      stopPolling()
    }

    return () => {
      stopPolling()
    }
  }, [isOpen, invoice, loadQris, checkStatus, stopPolling])

  if (!isOpen || !invoice) return null

  // If Midtrans Snap popup is actively open, don't show any background template
  if (isSnapActive && (gatewayName === "midtrans" || snapToken)) {
    return null
  }

  const payableAmount = uniqueAmount || invoice.amount
  const resolvedStoreName = companyName ? `${companyName.toUpperCase()}` : "CV. DIGITAL NETWORK SOLUTION"
  const isQrisGateway = Boolean(qrImageUrl || qrSvg)

  return (
    <div className="fixed inset-0 z-50 min-h-screen w-screen bg-[#0B0E14]/95 backdrop-blur-md flex items-center justify-center p-3 sm:p-4 overflow-y-auto select-none font-sans">
      <div className="relative w-full max-w-[420px] sm:max-w-[480px] md:max-w-[520px] my-auto py-6 space-y-3 animate-in fade-in zoom-in-95 duration-200">
        {loading ? (
          <div className="w-full flex flex-col items-center justify-center space-y-4 p-12 bg-white dark:bg-[#10141A] rounded-2xl border border-gray-200 dark:border-gray-800 shadow-2xl">
            <Loader2 className="h-9 w-9 text-brand-500 animate-spin" />
            <div className="text-center space-y-1">
              <h4 className="font-bold text-gray-900 dark:text-white text-sm">
                Menghubungkan ke Pembayaran...
              </h4>
              <p className="text-xs text-gray-500 dark:text-gray-400 font-mono">
                Invoice #{invoice.invoice_number}
              </p>
            </div>
          </div>
        ) : isPaid ? (
          <div className="w-full flex flex-col items-center justify-center space-y-4 p-12 bg-white dark:bg-[#10141A] rounded-2xl border border-gray-200 dark:border-gray-800 shadow-2xl">
            <div className="flex h-16 w-16 items-center justify-center rounded-full bg-emerald-500/10 text-emerald-500 animate-bounce">
              <CheckCircle2 className="h-10 w-10" />
            </div>
            <div className="text-center space-y-1">
              <h3 className="text-lg font-black text-gray-900 dark:text-white">Pembayaran Berhasil!</h3>
              <p className="text-xs text-gray-500 dark:text-gray-400">
                Tagihan telah lunas. Memperbarui status layanan Anda...
              </p>
            </div>
          </div>
        ) : isQrisGateway ? (
          <>
            <QrisFintechCheckout
              storeName={resolvedStoreName}
              merchantName={merchantName || companyName || "CV. DIGITAL NETWORK SOLUTION"}
              merchantCity={merchantCity || "SITUBONDO"}
              nmid="ID1024366211885"
              acquirerName={gatewayName ? gatewayName.toUpperCase() : "NODERA PAY"}
              orderId={invoice.invoice_number}
              amount={payableAmount}
              amountFormatted={`Rp ${Number(payableAmount).toLocaleString("id-ID")}`}
              invoiceDetails={{
                customerName: customer?.name || "Pelanggan",
                packageName: invoice.package_name || customer?.package || "Paket Internet",
                packagePrice: invoice.amount,
                adminFee: Math.max(0, payableAmount - invoice.amount),
                uniqueCode: uniqueCode && uniqueCode > 0 ? uniqueCode : null,
                period: invoice.period || "Tagihan Bulanan",
                dueDate: invoice.due_date || "-",
                total: payableAmount,
              }}
              qrSvg={qrSvg || qrImageUrl}
              qrImageUrl={qrImageUrl}
              checkoutUrl={checkoutUrl}
              snapToken={snapToken}
              secondsLeft={secondsLeft}
              timeoutMinutes={timeoutMinutes}
              expiresAt={expiresAt}
              onCheckStatus={checkStatus}
              isPaid={isPaid}
              onClose={onClose}
              onSuccess={() => {
                onClose()
                router.reload({ preserveScroll: true } as never)
              }}
              onRegenerate={loadQris}
            />

            {!isPaid && adminWa && (
              <div className="text-center pt-1">
                <a
                  href={`https://wa.me/${adminWa.replace(/\D/g, "")}?text=${encodeURIComponent(
                    `Halo Admin, saya ingin konfirmasi pembayaran tagihan Internet:%0ANo Invoice: #${invoice.invoice_number}%0ATotal: Rp ${Number(
                      payableAmount
                    ).toLocaleString("id-ID")}%0AMohon dibantu konfirmasi.`
                  )}`}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="inline-flex items-center gap-1.5 text-xs text-slate-400 hover:text-emerald-400 transition-colors"
                >
                  <MessageCircle className="w-3.5 h-3.5 text-emerald-400" />
                  <span>Butuh bantuan? Hubungi WhatsApp Admin</span>
                </a>
              </div>
            )}
          </>
        ) : (
          <div className="w-full flex flex-col items-center justify-center p-8 text-center space-y-4 bg-white dark:bg-[#10141A] rounded-2xl border border-gray-200 dark:border-gray-800 shadow-2xl">
            <div className="flex h-14 w-14 items-center justify-center rounded-2xl bg-amber-500/10 text-amber-500">
              <AlertCircle className="h-8 w-8" />
            </div>
            <div className="space-y-1.5 max-w-sm">
              <h3 className="text-sm font-bold text-gray-900 dark:text-white">
                Metode Pembayaran Belum Tersedia
              </h3>
              <p className="text-xs text-gray-500 dark:text-gray-400 leading-relaxed">
                {errorMessage ||
                  "Pembayaran online otomatis belum diaktifkan oleh admin. Silakan hubungi admin untuk konfirmasi pembayaran."}
              </p>
            </div>
            <div className="flex items-center gap-2 pt-2">
              {adminWa && (
                <a
                  href={`https://wa.me/${adminWa.replace(/\D/g, "")}?text=${encodeURIComponent(
                    `Halo Admin, saya ingin konfirmasi pembayaran Invoice #${invoice.invoice_number}`
                  )}`}
                  target="_blank"
                  rel="noreferrer"
                  className="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold transition"
                >
                  <MessageCircle className="h-4 w-4" />
                  <span>WhatsApp Admin</span>
                </a>
              )}
              <button
                type="button"
                onClick={onClose}
                className="px-4 py-2 rounded-xl bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300 text-xs font-bold transition cursor-pointer"
              >
                Tutup
              </button>
            </div>
          </div>
        )}
      </div>
    </div>
  )
}
