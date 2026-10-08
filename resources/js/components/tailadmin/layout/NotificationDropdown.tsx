import React, { useState } from "react"
import { createPortal } from "react-dom"
import { usePage, Link } from "@inertiajs/react"
import {
  Bell,
  Smartphone,
  ArrowRight,
  X,
  Activity,
  ShieldCheck,
  TrendingUp,
  TrendingDown,
  Clock,
  QrCode,
  CreditCard,
} from "lucide-react"

interface CloudActivityLog {
  id: number
  type: string
  title: string
  amount: number
  is_credit: boolean
  time: string
}

export const NotificationDropdown: React.FC = () => {
  const { url, props } = usePage()

  const isSuperadmin = url.startsWith("/superadmin")

  const isNoderaPay =
    !isSuperadmin &&
    (url.startsWith("/noderapay") ||
      url.startsWith("/transactions") ||
      url.startsWith("/withdrawals") ||
      url.startsWith("/payment-methods") ||
      url.startsWith("/api-apps") ||
      (typeof window !== "undefined" && window.location.hostname.startsWith("gateway.")))

  const isCloudPanel =
    !isNoderaPay &&
    (url.startsWith("/akun") ||
      url.startsWith("/mikhmon") ||
      url.startsWith("/history") ||
      url.startsWith("/topup") ||
      url.startsWith("/profil") ||
      url.startsWith("/isp-billing") ||
      url.startsWith("/vpn") ||
      (typeof window !== "undefined" &&
        (window.location.hostname.startsWith("panel.") ||
          window.location.hostname.includes("vpn") ||
          window.location.hostname.includes("cloud"))))

  const isWaGateway =
    !isNoderaPay &&
    !isCloudPanel &&
    (url.startsWith("/wagateway") ||
      url.startsWith("/devices") ||
      url.startsWith("/messages") ||
      url.startsWith("/quick-send") ||
      url.startsWith("/media") ||
      url.startsWith("/broadcast") ||
      url.startsWith("/scheduled") ||
      url.startsWith("/auto-reply") ||
      (typeof window !== "undefined" && window.location.hostname.startsWith("wa.")))

  const prefix = url.startsWith("/wagateway") ? "/wagateway" : ""
  const waMerchant = (props as any)?.merchant || (props as any)?.wa_merchant
  const isPro = waMerchant?.plan_type === "pro" || waMerchant?.is_pro
  const cloudActivityLogs = ((props as any)?.cloud_activity_logs || []) as CloudActivityLog[]

  const [isOpen, setIsOpen] = useState(false)
  const [notifying, setNotifying] = useState(cloudActivityLogs.length > 0)

  const openModal = () => {
    setIsOpen(true)
    setNotifying(false)
  }

  const closeModal = () => {
    setIsOpen(false)
  }

  const formatRupiah = (val: number) => {
    return "Rp " + Number(val || 0).toLocaleString("id-ID")
  }

  const targetUrl = isSuperadmin
    ? "/superadmin/audit-logs"
    : isNoderaPay
    ? "/transactions"
    : isCloudPanel
    ? "/history"
    : isWaGateway
    ? `${prefix}/messages`
    : "/admin/notifications"

  const buttonLabel = isSuperadmin
    ? "Buka Log Audit Superadmin"
    : isNoderaPay
    ? "Buka Riwayat & Log Transaksi"
    : isCloudPanel
    ? "Buka Riwayat & Log Saldo"
    : isWaGateway
    ? "Buka Log Pesan WhatsApp"
    : "Buka Log Aktivitas Sistem"

  return (
    <>
      {/* Trigger Button di Topnav */}
      <button
        type="button"
        onClick={openModal}
        aria-label="Log & Notifikasi Aktivitas"
        className="relative flex h-10 w-10 items-center justify-center rounded-xl border border-gray-200 bg-white text-gray-600 hover:bg-gray-50 hover:text-gray-900 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800 dark:hover:text-white transition shadow-theme-xs cursor-pointer active:scale-95"
      >
        {notifying && (
          <span className="absolute top-2.5 right-2.5 h-2 w-2 rounded-full bg-[#0073C6]" />
        )}
        <Bell className="h-4.5 w-4.5" />
      </button>

      {/* Pop-up Modal Portal (Persis Standar Pop-up Komunitas NODERA) */}
      {isOpen &&
        typeof document !== "undefined" &&
        createPortal(
          <div className="fixed inset-0 z-[99999] flex items-center justify-center bg-black/70 p-4 backdrop-blur-sm animate-in fade-in duration-200">
            <div className="relative w-full max-w-md rounded-2xl border border-gray-200 bg-white p-5 sm:p-6 shadow-2xl dark:border-gray-800 dark:bg-gray-900 text-gray-900 dark:text-white space-y-4 max-h-[92vh] overflow-y-auto custom-scrollbar">
              {/* Header Modal */}
              <div className="flex items-center justify-between pb-3 border-b border-gray-100 dark:border-[#1E2633]">
                <div className="flex items-center gap-2.5">
                  <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-[#0073C6]/15 text-[#0073C6] shrink-0">
                    {isNoderaPay ? (
                      <QrCode className="h-5 w-5" />
                    ) : isWaGateway ? (
                      <Smartphone className="h-5 w-5" />
                    ) : (
                      <Activity className="h-5 w-5" />
                    )}
                  </div>
                  <div className="min-w-0">
                    <h3 className="font-bold text-sm sm:text-base text-gray-900 dark:text-white truncate">
                      {isSuperadmin
                        ? "Log Aktivitas Superadmin"
                        : isNoderaPay
                        ? "Status & Notifikasi Gateway"
                        : isCloudPanel
                        ? "Log Aktivitas Cloud Panel"
                        : isWaGateway
                        ? "Status WhatsApp Gateway"
                        : "Log Aktivitas Sistem"}
                    </h3>
                    <p className="text-[11px] text-gray-500 dark:text-slate-400 truncate">
                      {isNoderaPay
                        ? "Monitoring realtime sistem pembayaran NODERA"
                        : isCloudPanel
                        ? "Riwayat transaksi & status layanan cloud"
                        : isWaGateway
                        ? "Monitoring antrean pesan & koneksi perangkat"
                        : "Monitoring operasional sistem NODERA"}
                    </p>
                  </div>
                </div>
                <button
                  type="button"
                  onClick={closeModal}
                  aria-label="Tutup Pop-up"
                  className="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-800 dark:hover:text-gray-200 transition cursor-pointer shrink-0"
                >
                  <X className="h-5 w-5" />
                </button>
              </div>

              {/* Body Content Items */}
              <div className="space-y-2.5">
                {isNoderaPay ? (
                  /* ── NODERA PAY GATEWAY REALTIME STATUS CARDS ── */
                  <>
                    <div className="flex items-start gap-3 rounded-xl p-3 bg-gray-50/70 dark:bg-[#0E131B] hover:bg-gray-100 dark:hover:bg-[#161D27] border border-gray-100 dark:border-[#1E2633] transition-colors">
                      <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-emerald-600 text-white shadow-xs">
                        <QrCode className="h-4.5 w-4.5" />
                      </div>
                      <div className="flex-1 min-w-0">
                        <div className="flex items-center justify-between gap-1">
                          <p className="text-xs font-bold text-gray-800 dark:text-white truncate">
                            Gateway Engine QRIS
                          </p>
                          <span className="inline-flex items-center rounded-md bg-emerald-600 px-2 py-0.5 text-[10px] font-bold text-white uppercase tracking-wider shrink-0">
                            ONLINE
                          </span>
                        </div>
                        <p className="text-[11px] text-gray-500 dark:text-slate-400 mt-0.5 leading-tight">
                          Sistem QRIS Dinamis &amp; API transaksi aktif realtime
                        </p>
                      </div>
                    </div>

                    <div className="flex items-start gap-3 rounded-xl p-3 bg-gray-50/70 dark:bg-[#0E131B] hover:bg-gray-100 dark:hover:bg-[#161D27] border border-gray-100 dark:border-[#1E2633] transition-colors">
                      <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-[#0073C6] text-white shadow-xs">
                        <CreditCard className="h-4.5 w-4.5" />
                      </div>
                      <div className="flex-1 min-w-0">
                        <div className="flex items-center justify-between gap-1">
                          <p className="text-xs font-bold text-gray-800 dark:text-white truncate">
                            Sistem Penarikan &amp; Payout
                          </p>
                          <span className="inline-flex items-center rounded-md bg-[#0073C6] px-2 py-0.5 text-[10px] font-bold text-white uppercase tracking-wider shrink-0">
                            STANDBY
                          </span>
                        </div>
                        <p className="text-[11px] text-gray-500 dark:text-slate-400 mt-0.5 leading-tight">
                          Pencairan dana ke rekening bank terverifikasi siap diproses
                        </p>
                      </div>
                    </div>

                    <div className="flex items-start gap-3 rounded-xl p-3 bg-gray-50/70 dark:bg-[#0E131B] hover:bg-gray-100 dark:hover:bg-[#161D27] border border-gray-100 dark:border-[#1E2633] transition-colors">
                      <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-blue-600 text-white shadow-xs">
                        <ShieldCheck className="h-4.5 w-4.5" />
                      </div>
                      <div className="flex-1 min-w-0">
                        <div className="flex items-center justify-between gap-1">
                          <p className="text-xs font-bold text-gray-800 dark:text-white truncate">
                            Webhook &amp; Signature
                          </p>
                          <span className="inline-flex items-center rounded-md bg-blue-600 px-2 py-0.5 text-[10px] font-bold text-white uppercase tracking-wider shrink-0">
                            AKTIF
                          </span>
                        </div>
                        <p className="text-[11px] text-gray-500 dark:text-slate-400 mt-0.5 leading-tight">
                          Enkripsi HMAC-SHA256 &amp; notifikasi realtime callback aman
                        </p>
                      </div>
                    </div>
                  </>
                ) : isCloudPanel ? (
                  /* ── CLOUD PANEL REAL ACTIVITY LOGS ── */
                  cloudActivityLogs.length > 0 ? (
                    cloudActivityLogs.map((log) => (
                      <div
                        key={log.id}
                        className="flex items-start gap-3 rounded-xl p-3 bg-gray-50/70 dark:bg-[#0E131B] hover:bg-gray-100 dark:hover:bg-[#161D27] border border-gray-100 dark:border-[#1E2633] transition-colors"
                      >
                        <div
                          className={`flex h-9 w-9 shrink-0 items-center justify-center rounded-xl text-white shadow-xs ${
                            log.is_credit ? "bg-emerald-600" : "bg-rose-600"
                          }`}
                        >
                          {log.is_credit ? (
                            <TrendingUp className="h-4.5 w-4.5" />
                          ) : (
                            <TrendingDown className="h-4.5 w-4.5" />
                          )}
                        </div>
                        <div className="flex-1 min-w-0">
                          <div className="flex items-center justify-between gap-1">
                            <p className="text-xs font-bold text-gray-800 dark:text-white truncate">
                              {log.title}
                            </p>
                            {log.amount > 0 ? (
                              <span
                                className={`inline-flex items-center rounded-md px-1.5 py-0.5 text-[10px] font-bold text-white font-mono tracking-tight shrink-0 ${
                                  log.is_credit ? "bg-emerald-600" : "bg-rose-600"
                                }`}
                              >
                                {log.is_credit ? "+" : "-"}
                                {formatRupiah(log.amount)}
                              </span>
                            ) : (
                              <span className="inline-flex items-center rounded-md bg-[#0073C6] px-1.5 py-0.5 text-[10px] font-bold text-white uppercase tracking-wider shrink-0">
                                {log.type === "VPN" ? "ONLINE" : log.type === "MIKHMON" ? "ONLINE" : "AKTIF"}
                              </span>
                            )}
                          </div>
                          <div className="flex items-center gap-1 text-[11px] text-gray-500 dark:text-slate-400 mt-0.5">
                            <Clock className="h-3 w-3 text-gray-400" />
                            <span>{log.time}</span>
                          </div>
                        </div>
                      </div>
                    ))
                  ) : (
                    <div className="py-8 text-center text-gray-500 dark:text-slate-400">
                      <Activity className="h-8 w-8 mx-auto mb-2 text-gray-300 dark:text-slate-600" />
                      <p className="text-xs font-semibold text-slate-300">Belum ada riwayat aktivitas terbaru</p>
                      <p className="text-[11px] text-slate-500 mt-0.5">Transaksi dan notifikasi akun Anda akan tampil di sini</p>
                    </div>
                  )
                ) : isWaGateway ? (
                  /* ── WA GATEWAY ITEMS ── */
                  <>
                    <div className="flex items-start gap-3 rounded-xl p-3 bg-gray-50/70 dark:bg-[#0E131B] hover:bg-gray-100 dark:hover:bg-[#161D27] border border-gray-100 dark:border-[#1E2633] transition-colors">
                      <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-emerald-600 text-white shadow-xs">
                        <Smartphone className="h-4.5 w-4.5" />
                      </div>
                      <div className="flex-1 min-w-0">
                        <div className="flex items-center justify-between gap-1">
                          <p className="text-xs font-bold text-gray-800 dark:text-white truncate">
                            Status WhatsApp Gateway
                          </p>
                          <span className="inline-flex items-center rounded-md bg-emerald-600 px-2 py-0.5 text-[10px] font-bold text-white uppercase tracking-wider shrink-0">
                            ONLINE
                          </span>
                        </div>
                        <p className="text-[11px] text-gray-500 dark:text-slate-400 mt-0.5 leading-tight">
                          Perangkat aktif &amp; siap mengirim pesan otomatis
                        </p>
                      </div>
                    </div>

                    <div className="flex items-start gap-3 rounded-xl p-3 bg-gray-50/70 dark:bg-[#0E131B] hover:bg-gray-100 dark:hover:bg-[#161D27] border border-gray-100 dark:border-[#1E2633] transition-colors">
                      <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-[#0073C6] text-white shadow-xs">
                        <Activity className="h-4.5 w-4.5" />
                      </div>
                      <div className="flex-1 min-w-0">
                        <div className="flex items-center justify-between gap-1">
                          <p className="text-xs font-bold text-gray-800 dark:text-white truncate">
                            Gateway Engine
                          </p>
                          <span className="inline-flex items-center rounded-md bg-[#0073C6] px-2 py-0.5 text-[10px] font-bold text-white uppercase tracking-wider shrink-0">
                            AKTIF
                          </span>
                        </div>
                        <p className="text-[11px] text-gray-500 dark:text-slate-400 mt-0.5 leading-tight">
                          Socket realtime &amp; antrean HTTP request normal
                        </p>
                      </div>
                    </div>

                    <div className="flex items-start gap-3 rounded-xl p-3 bg-gray-50/70 dark:bg-[#0E131B] hover:bg-gray-100 dark:hover:bg-[#161D27] border border-gray-100 dark:border-[#1E2633] transition-colors">
                      <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-blue-600 text-white shadow-xs">
                        <ShieldCheck className="h-4.5 w-4.5" />
                      </div>
                      <div className="flex-1 min-w-0">
                        <div className="flex items-center justify-between gap-1">
                          <p className="text-xs font-bold text-gray-800 dark:text-white truncate">
                            {isPro ? "Paket Pro Unlimited" : "Starter Free Plan"}
                          </p>
                          <span className="inline-flex items-center rounded-md bg-blue-600 px-2 py-0.5 text-[10px] font-bold text-white uppercase tracking-wider shrink-0">
                            {isPro ? "UNLIMITED" : "500/BLN"}
                          </span>
                        </div>
                        <p className="text-[11px] text-gray-500 dark:text-slate-400 mt-0.5 leading-tight">
                          {isPro
                            ? "Fitur broadcast, media, dan auto-reply tanpa batas"
                            : "Kuota pesan gratis reset otomatis setiap tanggal 1"}
                        </p>
                      </div>
                    </div>
                  </>
                ) : isSuperadmin ? (
                  /* ── SUPERADMIN AUDIT LOGS ── */
                  cloudActivityLogs.length > 0 ? (
                    cloudActivityLogs.map((log) => (
                      <div
                        key={log.id}
                        className="flex items-start gap-3 rounded-xl p-3 bg-gray-50/70 dark:bg-white/[0.03] hover:bg-gray-100 dark:hover:bg-white/[0.06] border border-gray-100 dark:border-gray-800 transition-colors"
                      >
                        <div
                          className={`flex h-9 w-9 shrink-0 items-center justify-center rounded-xl text-white shadow-xs ${
                            log.is_credit ? "bg-brand-500" : "bg-rose-600"
                          }`}
                        >
                          <Activity className="h-4.5 w-4.5" />
                        </div>
                        <div className="flex-1 min-w-0">
                          <div className="flex items-center justify-between gap-1">
                            <p className="text-xs font-bold text-gray-800 dark:text-white truncate">
                              {log.title}
                            </p>
                            <span
                              className={`inline-flex items-center rounded-md px-1.5 py-0.5 text-[9px] font-bold text-white uppercase tracking-wider shrink-0 ${
                                log.is_credit ? "bg-brand-500" : "bg-rose-600"
                              }`}
                            >
                              {log.type}
                            </span>
                          </div>
                          <div className="flex items-center gap-1 text-[11px] text-gray-500 dark:text-slate-400 mt-0.5">
                            <Clock className="h-3 w-3 text-gray-400" />
                            <span>{log.time}</span>
                          </div>
                        </div>
                      </div>
                    ))
                  ) : (
                    <div className="py-8 text-center text-gray-500 dark:text-slate-400">
                      <Activity className="h-8 w-8 mx-auto mb-2 text-gray-300 dark:text-slate-600" />
                      <p className="text-xs font-semibold text-gray-700 dark:text-slate-300">Belum ada riwayat audit terbaru</p>
                      <p className="text-[11px] text-gray-500 dark:text-slate-500 mt-0.5">Seluruh tindakan penghapusan dan edit akan tercatat di sini</p>
                    </div>
                  )
                ) : (
                  /* ── GENERAL ADMIN ITEMS ── */
                  <div className="py-8 text-center text-gray-500 dark:text-slate-400">
                    <Activity className="h-8 w-8 mx-auto mb-2 text-gray-300 dark:text-slate-600" />
                    <p className="text-xs font-semibold text-slate-300">Belum ada riwayat aktivitas terbaru</p>
                    <p className="text-[11px] text-slate-500 mt-0.5">Log aktivitas sistem akan tercatat secara otomatis</p>
                  </div>
                )}
              </div>

              {/* Footer Action Buttons */}
              <div className="pt-3 border-t border-gray-100 dark:border-[#1E2633] flex items-center justify-between gap-2">
                <button
                  type="button"
                  onClick={closeModal}
                  className="h-9 px-4 rounded-xl border border-gray-200 bg-white text-xs font-bold text-gray-700 hover:bg-gray-50 dark:border-[#2B3544] dark:bg-[#161B22] dark:text-slate-300 dark:hover:bg-[#1E2633] transition cursor-pointer"
                >
                  Tutup
                </button>
                <Link
                  href={targetUrl}
                  onClick={closeModal}
                  className="h-9 px-4 rounded-xl bg-[#0073C6] hover:bg-[#0084E3] text-xs font-bold text-white flex items-center gap-1.5 transition shadow-xs cursor-pointer"
                >
                  <span>{buttonLabel}</span>
                  <ArrowRight className="h-3.5 w-3.5" />
                </Link>
              </div>
            </div>
          </div>,
          document.body
        )}
    </>
  )
}

export default NotificationDropdown
