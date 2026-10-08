import * as React from "react"
import { Head } from "@inertiajs/react"
import { QrisFintechCheckout } from "@/components/QrisFintechCheckout"
import { formatIDR } from "@/lib/utils"
import { ShieldCheck } from "lucide-react"

interface NoderaPayCheckoutProps {
  transaction: {
    id: number
    order_id: string
    amount: number
    unique_code: number
    total_amount: number
    status: string
    dynamic_qris_string: string
    qr_svg: string
    qr_image_url: string
    seconds_left: number
    expires_at: string | null
    paid_at: string | null
    customer_name?: string | null
    note?: string | null
  }
  merchant: {
    name: string
    city?: string | null
    nmid?: string | null
    acquirer_name?: string | null
  }
  checkStatusUrl: string
}

export default function NoderaPayCheckout({
  transaction,
  merchant,
  checkStatusUrl,
}: NoderaPayCheckoutProps) {
  const [isPaid, setIsPaid] = React.useState(transaction.status === "paid")
  const [checking, setChecking] = React.useState(false)

  const handleCheckStatus = React.useCallback(async () => {
    if (isPaid) return true
    setChecking(true)
    try {
      const res = await fetch(checkStatusUrl)
      const data = await res.json()
      if (data.success && (data.status === "PAID" || data.status === "paid")) {
        setIsPaid(true)
        return true
      }
    } catch (e) {
      console.error("[NODERA Pay] Check status failed:", e)
    } finally {
      setChecking(false)
    }
    return false
  }, [checkStatusUrl, isPaid])

  // Polling check status automatically every 3.5 seconds
  React.useEffect(() => {
    if (isPaid) return
    const interval = setInterval(() => {
      handleCheckStatus()
    }, 3500)
    return () => clearInterval(interval)
  }, [isPaid, handleCheckStatus])

  return (
    <div className="min-h-screen bg-[#07090E] text-slate-100 flex flex-col justify-center items-center p-3 sm:p-6 relative overflow-x-hidden selection:bg-[#00C2FF]/30">
      <Head title={`Pembayaran QRIS Dinamis - ${merchant.name}`} />

      {/* Subtle Background Glows */}
      <div className="absolute -top-40 -left-40 w-96 h-96 bg-[#00C2FF]/10 rounded-full blur-3xl pointer-events-none" />
      <div className="absolute -bottom-40 -right-40 w-96 h-96 bg-[#ED1C24]/10 rounded-full blur-3xl pointer-events-none" />

      {/* Main Checkout Container */}
      <div className="w-full max-w-md my-auto relative z-10 space-y-4">
        <QrisFintechCheckout
          storeName={merchant.name}
          merchantName={merchant.name}
          merchantCity={merchant.city}
          nmid={merchant.nmid}
          acquirerName={merchant.acquirer_name || "NODERA Pay"}
          orderId={transaction.order_id}
          amount={transaction.total_amount}
          amountFormatted={formatIDR(transaction.total_amount)}
          invoiceDetails={{
            customerName: transaction.customer_name || `Order #${transaction.order_id}`,
            packageName: transaction.note || "Pembayaran Tagihan / Transaksi",
            packagePrice: transaction.amount,
            adminFee: Math.max(0, transaction.total_amount - (transaction.amount + (transaction.unique_code || 0))),
            uniqueCode: transaction.unique_code > 0 ? transaction.unique_code : null,
            period: "Selesai Otomatis",
            subtotal: transaction.amount,
            total: transaction.total_amount,
          }}
          qrSvg={transaction.qr_svg}
          qrImageUrl={transaction.qr_image_url}
          secondsLeft={transaction.seconds_left}
          timeoutMinutes={Math.max(1, Math.round((transaction.seconds_left || 300) / 60))}
          expiresAt={transaction.expires_at || undefined}
          isPaid={isPaid}
          isCheckingStatus={checking}
          onCheckStatus={handleCheckStatus}
          onRegenerate={() => window.location.reload()}
          successBadgeText="TERVERIFIKASI OTOMATIS"
          successTitle="Pembayaran Berhasil Diterima!"
          successMessage="Terima kasih! Pembayaran Anda telah terverifikasi secara instan oleh sistem gateway NODERA Pay."
          successReceiptRows={[
            { label: "Nomor Order", value: transaction.order_id, isMono: true },
            { label: "Merchant", value: merchant.name },
            {
              label: "Total Dibayar",
              value: formatIDR(transaction.total_amount),
              isHighlight: true,
              highlightColor: "text-emerald-400 font-bold",
            },
            {
              label: "Status Pembayaran",
              value: "LUNAS (Auto-Settled)",
              isHighlight: true,
              highlightColor: "text-emerald-400 font-bold",
            },
          ]}
          successActionText="Kembali"
          onSuccessAction={() => {
            if (window.history.length > 1) {
              window.history.back()
            } else {
              window.close()
            }
          }}
        />

        {/* Secure Footer Branding */}
        <div className="flex items-center justify-center gap-2 text-center text-xs text-slate-500 py-2">
          <ShieldCheck className="h-4 w-4 text-emerald-400" />
          <span>Diproteksi oleh <b>NODERA Pay</b> Gateway Dinamis</span>
        </div>
      </div>
    </div>
  )
}
