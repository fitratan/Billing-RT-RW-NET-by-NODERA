import { Link, Head } from "@inertiajs/react"
import { Copy, Check, ExternalLink, RefreshCw } from "lucide-react"
import { PageProps } from "@/types"
import { useState, useEffect, useRef } from "react"
import { QrisFintechCheckout } from "@/components/QrisFintechCheckout"
import { formatIDR } from "@/lib/utils"
import { Button } from "@/components/ui/button"

interface BankAccount {
  id?: number
  bank_name: string
  account_number: string
  account_name: string
}

export default function RegisterSuksesPage({
  slug,
  company,
  app_domain,
  wa_number,
  expiry_minutes = 15,
  registration,
  package: pkg,
  qris,
  login_url,
  status_url,
  bank_accounts = [],
}: PageProps<{
  slug: string
  company: { name: string }
  app_domain?: string
  wa_number?: string
  expiry_minutes?: number
  registration?: {
    id: number
    order_id?: string
    slug: string
    name: string
    company: string
    username: string
    email: string
    phone: string
    status: string
    is_manual_bank?: boolean
    is_paid: boolean
    total_amount: number
    unique_code: number
    expires_at: string | null
    seconds_left: number
  } | null
  package?: {
    id?: number
    name: string
    duration: number
    monthly_price: number
  } | null
  qris?: {
    qr_svg: string | null
    merchant_name: string
    merchant_city: string
    nmid?: string | null
    amount: number
    amount_formatted: string
  } | null
  login_url?: string
  status_url?: string
  bank_accounts?: BankAccount[]
}>) {
  const [isPaid, setIsPaid] = useState<boolean>(Boolean(registration?.is_paid || registration?.status === 'approved'))
  const isManualBank = Boolean(registration?.is_manual_bank)
  const [isChecking, setIsChecking] = useState<boolean>(false)
  const [currentQrSvg, setCurrentQrSvg] = useState<string | null>(qris?.qr_svg ?? null)
  const [currentAmount, setCurrentAmount] = useState<number>(registration?.total_amount || 0)
  const [currentUniqueCode, setCurrentUniqueCode] = useState<number>(registration?.unique_code || 0)
  const [currentExpiresAt, setCurrentExpiresAt] = useState<string | null>(registration?.expires_at ?? null)
  const [secondsLeft, setSecondsLeft] = useState<number | null>(registration?.seconds_left ?? null)
  const [copiedBankId, setCopiedBankId] = useState<number | null>(null)
  const pollingTimerRef = useRef<NodeJS.Timeout | null>(null)

  const checkStatusEndpoint = status_url || `/register/status/${slug}`

  // Real-time polling every 2.5 seconds while waiting for payment
  useEffect(() => {
    if (isPaid) {
      if (pollingTimerRef.current) clearInterval(pollingTimerRef.current)
      return
    }

    const pollStatus = async () => {
      try {
        const res = await fetch(checkStatusEndpoint, {
          headers: { Accept: "application/json" },
        })
        if (res.ok) {
          const data = await res.json()
          if (data.is_paid || data.status === "approved") {
            setIsPaid(true)
            if (pollingTimerRef.current) clearInterval(pollingTimerRef.current)
          }
        }
      } catch (err) {
        // Silently retry on network hiccups
      }
    }

    pollingTimerRef.current = setInterval(pollStatus, 2500)
    return () => {
      if (pollingTimerRef.current) clearInterval(pollingTimerRef.current)
    }
  }, [isPaid, checkStatusEndpoint])

  // Manual Check Status button handler
  const handleManualCheckStatus = async (): Promise<boolean> => {
    setIsChecking(true)
    try {
      const res = await fetch(checkStatusEndpoint, {
        headers: { Accept: "application/json" },
      })
      if (res.ok) {
        const data = await res.json()
        if (data.is_paid || data.status === "approved") {
          setIsPaid(true)
          setIsChecking(false)
          return true
        }
      }
    } catch (err) {
      // Ignored
    } finally {
      setIsChecking(false)
    }
    return false
  }

  // Regenerate expired QRIS
  const handleRegenerate = async () => {
    try {
      const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") || ""
      const res = await fetch(`/register/regenerate/${slug}`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Accept: "application/json",
          "X-CSRF-TOKEN": token,
        },
      })
      if (res.ok) {
        const data = await res.json()
        if (data.success) {
          setCurrentQrSvg(data.qr_svg)
          setCurrentAmount(data.amount)
          setCurrentUniqueCode(data.unique_code)
          setCurrentExpiresAt(data.expires_at)
          setSecondsLeft(data.seconds_left)
        }
      }
    } catch (err) {
      // Fallback reload
      window.location.reload()
    }
  }

  const copyBank = (accNum: string, id: number) => {
    navigator.clipboard?.writeText(accNum)
    setCopiedBankId(id)
    setTimeout(() => setCopiedBankId(null), 2000)
  }

  const handleCancelAndClose = async () => {
    try {
      const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") || ""
      await fetch(`/register/cancel/${slug}`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Accept: "application/json",
          "X-CSRF-TOKEN": token,
        },
        keepalive: true,
      })
    } catch {}
    window.location.href = '/register'
  }

  const cleanWaNumber = wa_number ? wa_number.replace(/[^0-9]/g, "") : ""
  const waMsg = encodeURIComponent(
    `Halo Superadmin, saya sudah mendaftar di ${company?.name || 'NODERA'} dengan subdomain *${slug}*.\n\nDetail Pendaftaran:\n• Nama: *${registration?.name || slug}*\n• Brand / ISP: *${registration?.company || slug}*\n• Subdomain: *${slug}.${app_domain || 'nodera.id'}*\n• Paket: *${pkg?.name || 'Paket ISP'}* (${pkg?.duration || 1} Bulan)\n• Total Tagihan: *Rp ${currentAmount.toLocaleString('id-ID')}*\n• Metode: *Transfer Rekening Bank (Manual)*\n\nBerikut saya lampirkan bukti transfer pembayaran saya untuk diverifikasi dan di-ACC. Terima kasih.`
  )
  const waHref = cleanWaNumber ? `https://wa.me/${cleanWaNumber}?text=${waMsg}` : undefined

  return (
    <div className="min-h-screen bg-[#070A10] text-slate-100 flex flex-col items-center justify-center p-4 sm:p-6 lg:p-10 select-none relative overflow-x-hidden">
      <Head title="Konfirmasi Pembayaran Pendaftaran | NODERA" />

      {/* Ambient background glows */}
      <div className="absolute -top-32 -left-32 w-96 h-96 bg-[#0073C6]/15 rounded-full blur-3xl pointer-events-none" />
      <div className="w-full max-w-[420px] sm:max-w-[480px] md:max-w-[520px] mx-auto z-10 space-y-4">
        
        {/* Payment Content based on registration choice */}
        {(isPaid || !isManualBank) && (
          <div className="space-y-4">
            <QrisFintechCheckout
              storeName={company?.name || "NODERA BILLING & NETWORK"}
              merchantName={qris?.merchant_name || "CV. DIGITAL NETWORK SOLUTION"}
              merchantCity={qris?.merchant_city || "SITUBONDO"}
              nmid={qris?.nmid || "ID1024366211885"}
              acquirerName="NODERA"
              orderId={registration?.order_id || (registration?.id ? `${new Date().getFullYear()}${String(new Date().getMonth() + 1).padStart(2, '0')}${String(new Date().getDate()).padStart(2, '0')}${String(registration.id).padStart(4, '0')}` : String(Date.now()))}
              amount={currentAmount}
              amountFormatted={formatIDR(currentAmount)}
              invoiceDetails={{
                customerName: registration?.name || slug,
                packageName: `${pkg?.name || "Paket ISP"} (${pkg?.duration || 1} Bulan)`,
                packagePrice: currentAmount - currentUniqueCode,
                adminFee: 0,
                uniqueCode: currentUniqueCode > 0 ? currentUniqueCode : null,
                period: "Aktivasi Pertama",
                dueDate: currentExpiresAt ? new Date(currentExpiresAt).toLocaleTimeString("id-ID", { hour: '2-digit', minute: '2-digit' }) + " WIB" : `${expiry_minutes} Menit`,
                subtotal: currentAmount - currentUniqueCode,
                total: currentAmount,
              }}
              qrSvg={currentQrSvg}
              qrImageUrl={currentQrSvg}
              timeoutMinutes={expiry_minutes || 15}
              expiresAt={currentExpiresAt}
              secondsLeft={secondsLeft}
              onCheckStatus={handleManualCheckStatus}
              isCheckingStatus={isChecking}
              isPaid={isPaid}
              onRegenerate={handleRegenerate}
              onClose={handleCancelAndClose}
              successBadgeText="Pendaftaran Berhasil Diverifikasi!"
              successTitle="Pendaftaran Akun Berhasil!"
              successMessage={`Selamat datang di ekosistem ${company?.name || "NODERA"}. Instansi ISP Anda telah aktif dan siap digunakan secara penuh.`}
              successReceiptRows={[
                { label: "Nomor Order ID", value: registration?.order_id || `#${registration?.id || slug}`, isMono: true },
                { label: "Brand / Mitra", value: registration?.company || slug },
                { label: "Subdomain Anda", value: `${slug}.${app_domain || "nodera.id"}`, isMono: true, isHighlight: true, highlightColor: "text-[#0084E3]" },
                { label: "Paket Langganan", value: `${pkg?.name || "Starter"} (${pkg?.duration || 1} Bulan)` },
                { label: "Username Admin", value: registration?.username || "admin", isMono: true },
                { label: "Total Pembayaran", value: formatIDR(currentAmount), isMono: true, isHighlight: true, highlightColor: "text-emerald-600" },
              ]}
              successActionText="Masuk ke Dashboard Billing"
              successActionUrl={login_url || `/login`}
              secondaryActionText={waHref ? "Butuh Bantuan? Chat WhatsApp Admin" : undefined}
              secondaryActionUrl={waHref}
            />
          </div>
        )}

        {/* Content: Transfer Bank Manual (Hanya jika belum lunas) */}
        {!isPaid && isManualBank && (
          <div className="rounded-2xl border border-[#212B3B] bg-[#121720] p-6 space-y-4 shadow-sm">
            <div className="space-y-1 text-center">
              <h3 className="font-bold text-sm text-white">Transfer Bank Manual</h3>
              <p className="text-xs text-slate-400 leading-relaxed">
                Kirimkan nominal tepat sebesar <strong className="text-[#00C2FF] font-mono">Rp {currentAmount.toLocaleString("id-ID")}</strong> (sesuai nominal di atas) ke salah satu rekening resmi di bawah ini:
              </p>
            </div>

            <div className="space-y-2.5">
              {bank_accounts && bank_accounts.length > 0 ? (
                bank_accounts.map((b, idx) => (
                  <div
                    key={idx}
                    className="rounded-2xl border border-[#212B3B] bg-[#0B0E14] p-4 flex items-center justify-between"
                  >
                    <div className="space-y-0.5">
                      <span className="text-[11px] font-black uppercase text-[#00C2FF]">{b.bank_name}</span>
                      <div className="font-mono font-black text-sm text-white tracking-wider">{b.account_number}</div>
                      <div className="text-[11px] text-slate-400">a.n {b.account_name}</div>
                    </div>
                    <Button
                      type="button"
                      variant="outline"
                      size="sm"
                      onClick={() => copyBank(b.account_number, idx)}
                      className="h-8 border-[#212B3B] bg-[#161D29] hover:bg-[#1E2633] text-xs font-bold text-slate-200 cursor-pointer"
                    >
                      {copiedBankId === idx ? <Check className="h-3.5 w-3.5 text-emerald-400 mr-1" /> : <Copy className="h-3.5 w-3.5 mr-1" />}
                      <span>{copiedBankId === idx ? "Tersalin" : "Salin"}</span>
                    </Button>
                  </div>
                ))
              ) : (
                <div className="rounded-2xl border border-[#212B3B] bg-[#0B0E14] p-4 text-center text-xs text-slate-400">
                  BCA: 8293-0192-38 a.n CV. DIGITAL NETWORK SOLUTION
                </div>
              )}
            </div>

            <div className="rounded-xl border border-amber-500/20 bg-amber-500/10 p-3 text-xs text-amber-200/90 leading-relaxed text-center">
              Setelah melakukan transfer, silakan kirimkan bukti transfer Anda ke WhatsApp Superadmin untuk verifikasi dan persetujuan (ACC) aktivasi akun instansi Anda.
            </div>

            {waHref && (
              <a
                href={waHref}
                target="_blank"
                rel="noreferrer"
                className="flex w-full items-center justify-center gap-2 rounded-2xl bg-emerald-600 hover:bg-emerald-500 py-3 text-xs font-bold text-white transition-all shadow-md cursor-pointer"
              >
                <span>Konfirmasi Aktivasi via WhatsApp</span>
              </a>
            )}
          </div>
        )}

        {/* Help / Back button */}
        <div className="text-center pt-2 space-y-1.5">
          <Link
            href="/login"
            className="text-xs text-slate-400 hover:text-[#00C2FF] transition-colors inline-flex items-center gap-1 cursor-pointer"
          >
            <span>Sudah punya akun? Masuk ke Halaman Login</span>
          </Link>
        </div>
      </div>
    </div>
  )
}
