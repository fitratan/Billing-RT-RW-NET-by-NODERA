import * as React from "react"
import { AnimatePresence, motion } from "framer-motion"
import { Users, MessageSquare, Sparkles, Coins, X, ArrowUpRight } from "lucide-react"
import { usePage } from "@inertiajs/react"

export function CommunityFloatingBanner() {
  const { url, props } = usePage()
  const [isModalOpen, setIsModalOpen] = React.useState(false)
  const [isDismissed, setIsDismissed] = React.useState(false)
  const [mounted, setMounted] = React.useState(false)

  const rawCommunityUrl = (props as any)?.telegramCommunityUrl as string | undefined

  const fullTelegramUrl = React.useMemo(() => {
    if (!rawCommunityUrl || !rawCommunityUrl.trim()) {
      return "https://t.me/nodera_community"
    }
    const val = rawCommunityUrl.trim()
    if (val.startsWith("http://") || val.startsWith("https://")) {
      return val
    }
    return `https://t.me/${val.replace(/^@/, "")}`
  }, [rawCommunityUrl])

  const displayHandle = React.useMemo(() => {
    try {
      const clean = fullTelegramUrl.replace(/^https?:\/\//, "").replace(/^t\.me\//, "")
      return `t.me/${clean}`
    } catch {
      return "t.me/nodera_community"
    }
  }, [fullTelegramUrl])

  // Hanya tampilkan di halaman Dashboard utama (Admin, Superadmin, Kolektor, Teknisi, Agent, Cashier, VPN)
  const isDashboard = React.useMemo(() => {
    const cleanPath = (url || "").split("?")[0].replace(/\/+$/, "") || "/"
    return (
      cleanPath === "/" ||
      cleanPath === "/dashboard" ||
      cleanPath === "/admin" ||
      cleanPath === "/admin/dashboard" ||
      cleanPath === "/superadmin" ||
      cleanPath === "/superadmin/dashboard" ||
      cleanPath === "/kolektor" ||
      cleanPath === "/kolektor/dashboard" ||
      cleanPath === "/teknisi" ||
      cleanPath === "/teknisi/dashboard" ||
      cleanPath === "/agent" ||
      cleanPath === "/agent/dashboard" ||
      cleanPath === "/cashier" ||
      cleanPath === "/cashier/dashboard" ||
      cleanPath === "/vpn" ||
      cleanPath === "/vpn/dashboard" ||
      cleanPath.endsWith("/dashboard")
    )
  }, [url])

  React.useEffect(() => {
    setMounted(true)
  }, [url])

  if (!mounted || !isDashboard || isDismissed) return null

  const handleDismiss = (e: React.MouseEvent) => {
    e.stopPropagation()
    setIsDismissed(true)
  }

  const openTelegram = () => {
    window.open(fullTelegramUrl, "_blank", "noopener,noreferrer")
  }

  return (
    <>
      {/* ── FLOATING COMMUNITY & MITRA BUTTON (BOTTOM-LEFT) ── */}
      <div className="fixed bottom-[calc(env(safe-area-inset-bottom,0px)+4.75rem)] left-4 sm:bottom-6 sm:left-6 z-40 select-none">
        <AnimatePresence>
          <motion.div
            initial={{ scale: 0, opacity: 0, y: 15 }}
            animate={{ scale: 1, opacity: 1, y: 0 }}
            exit={{ scale: 0, opacity: 0, y: 15 }}
            transition={{ type: "spring", stiffness: 450, damping: 24 }}
            className="relative flex items-center gap-3 group"
          >
            {/* Dedicated Relative Wrapper for Floating Icon + X Button */}
            <div className="relative inline-flex shrink-0">
              {/* Pure Circular Button with Professional Users/Community Icon */}
              <button
                type="button"
                onClick={() => setIsModalOpen(true)}
                className="relative flex h-14 w-14 sm:h-16 sm:w-16 items-center justify-center rounded-full bg-gradient-to-tr from-[#0073C6] via-[#0088cc] to-[#00C2FF] text-white shadow-2xl shadow-[#0088cc]/50 ring-2 ring-white/30 transition-all duration-200 hover:scale-105 hover:shadow-[#00C2FF]/60 hover:ring-white/50 active:scale-95 cursor-pointer outline-none focus:ring-4 focus:ring-[#00C2FF]/40"
                title="Komunitas & Program Mitra Nodera"
                aria-label="Komunitas & Mitra"
              >
                <Users className="h-7 w-7 sm:h-8 sm:w-8 text-white drop-shadow-md transition-transform duration-200 group-hover:scale-110" />

                {/* Status active indicator dot (Live Pulse) */}
                <span className="absolute top-0.5 left-0.5 flex h-3.5 w-3.5 items-center justify-center">
                  <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75" />
                  <span className="relative inline-flex h-2.5 w-2.5 rounded-full bg-emerald-400 ring-2 ring-[#0073C6]" />
                </span>
              </button>

              {/* Tombol X pas tepat di atas / sudut atas lingkaran icon */}
              <button
                type="button"
                onClick={handleDismiss}
                className="absolute -top-1.5 -right-1.5 z-20 flex h-6 w-6 items-center justify-center rounded-full border border-white/40 bg-[#0E1520] text-slate-300 hover:text-white hover:bg-rose-600 hover:border-rose-400 shadow-xl transition-all active:scale-90 cursor-pointer"
                title="Tutup sementara"
                aria-label="Tutup"
              >
                <X className="h-3.5 w-3.5" />
              </button>
            </div>

            {/* Desktop Tooltip on Hover (appears to the right) */}
            <div
              onClick={() => setIsModalOpen(true)}
              className="hidden sm:flex flex-col rounded-2xl border border-[#2B3544] bg-[#0E1520]/95 px-3.5 py-2 text-left shadow-2xl backdrop-blur-md transition-all opacity-0 group-hover:opacity-100 translate-x-1 group-hover:translate-x-0 cursor-pointer hover:border-[#00C2FF]/50"
            >
              <span className="text-xs font-bold text-white leading-tight">Komunitas &amp; Mitra</span>
              <span className="text-[10px] text-sky-400 font-medium font-mono">{displayHandle}</span>
            </div>
          </motion.div>
        </AnimatePresence>
      </div>

      {/* ── FAST ZERO-LAG MODAL POPUP (NO EMOJIS · CLEAN PROFESSIONAL SVG ICONS) ── */}
      <AnimatePresence>
        {isModalOpen && (
          <motion.div
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            exit={{ opacity: 0 }}
            transition={{ duration: 0.12 }}
            className="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4 select-none"
            onClick={() => setIsModalOpen(false)}
          >
            <motion.div
              initial={{ scale: 0.93, opacity: 0, y: 12 }}
              animate={{ scale: 1, opacity: 1, y: 0 }}
              exit={{ scale: 0.95, opacity: 0, y: 8 }}
              transition={{ type: "spring", stiffness: 450, damping: 28 }}
              onClick={(e) => e.stopPropagation()}
              className="relative w-full max-w-md overflow-hidden rounded-3xl border border-[#2B3544] bg-[#121720] text-slate-100 shadow-2xl shadow-black/90"
            >
              {/* Close Button Top Right */}
              <button
                type="button"
                onClick={() => setIsModalOpen(false)}
                className="absolute top-4 right-4 z-20 flex h-8 w-8 items-center justify-center rounded-full border border-white/20 bg-black/40 text-white hover:bg-white/20 transition-all active:scale-90 cursor-pointer"
                aria-label="Tutup"
              >
                <X className="h-4 w-4" />
              </button>

              {/* Top Banner Graphic Header */}
              <div className="relative bg-gradient-to-br from-[#0073C6] via-[#0056A0] to-[#003E78] p-5 sm:p-6 text-white overflow-hidden">
                <div className="absolute -right-8 -bottom-8 h-36 w-36 rounded-full bg-white/10 blur-xl pointer-events-none" />

                <div className="relative z-10 flex items-center gap-3.5">
                  <div className="flex h-12 w-12 sm:h-14 sm:w-14 shrink-0 items-center justify-center rounded-full bg-white/15 border border-white/25 text-white backdrop-blur-sm">
                    <Users className="h-6 w-6 sm:h-7 sm:w-7 text-sky-100 drop-shadow-md" />
                  </div>
                  <div className="min-w-0 pr-6">
                    <div className="flex items-center gap-2">
                      <span className="rounded-full border border-white/20 bg-white/15 px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wider text-sky-200">
                        Grup Resmi
                      </span>
                    </div>
                    <h3 className="font-display text-base sm:text-lg font-black text-white mt-1 leading-tight">
                      Komunitas &amp; Mitra NODERA
                    </h3>
                    <p className="text-xs text-white/80 font-mono mt-0.5">
                      {displayHandle}
                    </p>
                  </div>
                </div>
              </div>

              {/* Modal Body */}
              <div className="p-5 sm:p-6 space-y-4 text-xs text-slate-300">
                <p className="leading-relaxed text-slate-200">
                  Bergabung bersama ratusan pengusaha RT RW Net, ISP, teknisi jaringan, dan integrator MikroTik se-Indonesia.
                </p>

                {/* Clean Professional Benefit List (Pure SVG Icons, No Emoji) */}
                <div className="space-y-2.5 rounded-2xl border border-[#212B3B] bg-[#0B0E14] p-3.5">
                  <div className="flex items-start gap-3">
                    <div className="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-sky-500/15 border border-sky-500/30 text-sky-400">
                      <MessageSquare className="h-3.5 w-3.5" />
                    </div>
                    <div>
                      <strong className="text-white block font-semibold">Forum Diskusi Jaringan</strong>
                      <span className="text-slate-400">Tanya jawab konfigurasi MikroTik, OLT, GenieACS, &amp; kendala lapangan.</span>
                    </div>
                  </div>

                  <div className="flex items-start gap-3">
                    <div className="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-emerald-500/15 border border-emerald-500/30 text-emerald-400">
                      <Sparkles className="h-3.5 w-3.5" />
                    </div>
                    <div>
                      <strong className="text-white block font-semibold">Update Fitur &amp; Changelog</strong>
                      <span className="text-slate-400">Dapatkan rilis pembaruan, patch keamanan, &amp; panduan teknis paling awal.</span>
                    </div>
                  </div>

                  <div className="flex items-start gap-3">
                    <div className="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-amber-500/15 border border-amber-500/30 text-amber-400">
                      <Coins className="h-3.5 w-3.5" />
                    </div>
                    <div>
                      <strong className="text-white block font-semibold">Program Kemitraan &amp; Afiliasi</strong>
                      <span className="text-slate-400">Peluang komisi dan bonus referral dari aktivasi pelanggan baru.</span>
                    </div>
                  </div>
                </div>

                {/* Actions */}
                <div className="pt-2 flex flex-col sm:flex-row items-center gap-2">
                  <button
                    type="button"
                    onClick={openTelegram}
                    className="w-full flex h-10 sm:h-11 items-center justify-center gap-2 rounded-xl bg-[#0073C6] hover:bg-[#0084E3] border border-[#0094FF]/40 px-4 text-xs font-bold text-white shadow-[#0073C6]/25 transition-all active:scale-95 cursor-pointer"
                  >
                    <span>Buka Grup Telegram</span>
                    <ArrowUpRight className="h-4 w-4" />
                  </button>

                  <button
                    type="button"
                    onClick={() => setIsModalOpen(false)}
                    className="w-full sm:w-auto flex h-10 sm:h-11 items-center justify-center rounded-xl border border-[#2B3544] bg-[#161B22] px-4 text-xs font-semibold text-slate-300 hover:bg-[#1D242E] hover:text-white transition-all cursor-pointer"
                  >
                    Tutup
                  </button>
                </div>
              </div>
            </motion.div>
          </motion.div>
        )}
      </AnimatePresence>
    </>
  )
}
