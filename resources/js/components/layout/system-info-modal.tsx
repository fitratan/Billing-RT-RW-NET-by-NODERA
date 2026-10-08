import * as React from "react"
import { motion, AnimatePresence } from "framer-motion"
import {
  X,
  Info,
  Send,
  MessageSquare,
  Router,
  Activity,
  CheckCircle2,
  ExternalLink,
  ShieldCheck,
  Clock,
  ChevronRight,
} from "lucide-react"
import { Link } from "@inertiajs/react"
import { cn } from "@/lib/utils"

interface SystemInfoModalProps {
  open: boolean
  onClose: () => void
}

export function SystemInfoModal({ open, onClose }: SystemInfoModalProps) {
  if (!open) return null

  return (
    <AnimatePresence>
      <div className="fixed inset-0 z-[9999] flex items-end sm:items-center justify-center p-0 sm:p-4 animate-in fade-in duration-200">
        {/* Backdrop */}
        <motion.div
          initial={{ opacity: 0 }}
          animate={{ opacity: 1 }}
          exit={{ opacity: 0 }}
          className="fixed inset-0 bg-[#0B0E14]/80 backdrop-blur-md"
          onClick={onClose}
        />

        {/* Modal Window */}
        <motion.div
          initial={{ opacity: 0, scale: 0.95, y: 16 }}
          animate={{ opacity: 1, scale: 1, y: 0 }}
          exit={{ opacity: 0, scale: 0.95, y: 16 }}
          transition={{ type: "spring", stiffness: 400, damping: 30 }}
          className="relative flex max-h-[90vh] w-full max-w-xl flex-col overflow-hidden rounded-t-3xl sm:rounded-3xl border border-[#1E2633] bg-[#10141A] text-white z-10"
        >
          {/* Header */}
          <div className="flex items-center justify-between border-b border-[#1E2633] px-5 py-4 shrink-0 bg-[#121720]">
            <div className="flex items-center gap-3">
              <div className="flex h-10 w-10 items-center justify-center rounded-2xl bg-[#0073C6]/20 border border-[#00C2FF]/30 text-[#00C2FF]">
                <Info className="h-5 w-5" />
              </div>
              <div>
                <h3 className="font-display text-base font-bold text-white flex items-center gap-2">
                  <span>Pusat Informasi &amp; Integrasi</span>
                </h3>
                <p className="text-xs text-slate-400">
                  Panduan status otomatisasi, Telegram bot, dan NMS monitoring
                </p>
              </div>
            </div>
            <button
              type="button"
              onClick={onClose}
              className="flex h-8 w-8 items-center justify-center rounded-xl bg-[#1A2230] border border-[#2A374D] text-slate-400 hover:text-white transition-all active:scale-95"
            >
              <X className="h-4 w-4" />
            </button>
          </div>

          {/* Content */}
          <div className="flex-1 overflow-y-auto p-5 space-y-4 text-xs">
            {/* HUB 1: Telegram Bot & NMS */}
            <div className="rounded-2xl border border-[#0088CC]/30 bg-[#0B1A28] p-4 space-y-3">
              <div className="flex items-center justify-between gap-2">
                <div className="flex items-center gap-2.5">
                  <div className="flex h-7 w-7 items-center justify-center rounded-lg bg-[#0088CC]/20 text-[#00C2FF]">
                    <Send className="h-4 w-4" />
                  </div>
                  <div>
                    <h4 className="font-bold text-white text-xs">Bot Telegram &amp; NMS Monitoring</h4>
                    <span className="text-[10px] text-cyan-300">Notifikasi Pesanan &amp; Jaringan 1-Menit</span>
                  </div>
                </div>

                <Link
                  href="/admin/telegram"
                  onClick={onClose}
                  className="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-[#0088CC] hover:bg-[#0099E6] text-[11px] font-bold text-white transition-colors"
                >
                  <span>Atur</span>
                  <ChevronRight className="h-3.5 w-3.5" />
                </Link>
              </div>

              <p className="text-slate-300 text-[11px] leading-relaxed">
                • <b>Pesanan Online:</b> Notifikasi pesanan voucher WiFi masuk langsung ke Telegram lengkap dengan tombol ACC 1-klik.<br />
                • <b>NMS Monitoring:</b> Pemantauan berkala setiap 1 menit untuk Router, PPPoE, Hotspot, dan ARP offline/online dengan sistem anti-spam (alert hanya dikirim saat status berubah).
              </p>
            </div>

            {/* HUB 2: WhatsApp Gateway */}
            <div className="rounded-2xl border border-emerald-500/30 bg-[#0B1C14] p-4 space-y-3">
              <div className="flex items-center justify-between gap-2">
                <div className="flex items-center gap-2.5">
                  <div className="flex h-7 w-7 items-center justify-center rounded-lg bg-emerald-500/20 text-emerald-400">
                    <MessageSquare className="h-4 w-4" />
                  </div>
                  <div>
                    <h4 className="font-bold text-white text-xs">WhatsApp Gateway &amp; Templates</h4>
                    <span className="text-[10px] text-emerald-300">Pengingat Tagihan &amp; Notifikasi Pelanggan</span>
                  </div>
                </div>

                <Link
                  href="/admin/whatsapp-templates"
                  onClick={onClose}
                  className="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-[11px] font-bold text-white transition-colors"
                >
                  <span>Atur</span>
                  <ChevronRight className="h-3.5 w-3.5" />
                </Link>
              </div>

              <p className="text-slate-300 text-[11px] leading-relaxed">
                Kirim invoice otomatis, konfirmasi pembayaran, serta peringatan isolir otomatis ke nomor WhatsApp pelanggan.
              </p>
            </div>

            {/* HUB 3: MikroTik & Billing Automations */}
            <div className="rounded-2xl border border-[#1E2633] bg-[#121720] p-4 space-y-2">
              <div className="flex items-center gap-2 font-bold text-slate-200">
                <ShieldCheck className="h-4 w-4 text-purple-400" />
                <span>Otomatisasi Sistem &amp; Cron</span>
              </div>
              <ul className="space-y-1.5 text-[11px] text-slate-300 pl-1">
                <li className="flex items-center gap-2">
                  <Clock className="h-3.5 w-3.5 text-cyan-400 shrink-0" />
                  <span><b>NMS Poller:</b> Berjalan otomatis di latar belakang setiap 1 menit.</span>
                </li>
                <li className="flex items-center gap-2">
                  <Clock className="h-3.5 w-3.5 text-cyan-400 shrink-0" />
                  <span><b>Auto Isolir:</b> Menjalankan isolir otomatis pelanggan jatuh tempo sesuai tanggal tagihan.</span>
                </li>
              </ul>
            </div>
          </div>

          {/* Footer */}
          <div className="border-t border-[#1E2633] p-4 bg-[#121720] shrink-0 flex justify-end">
            <button
              type="button"
              onClick={onClose}
              className="h-9 px-5 rounded-xl bg-[#1C2636] hover:bg-[#253348] border border-[#2C3B52] text-xs font-semibold text-white transition-all active:scale-95"
            >
              Tutup
            </button>
          </div>
        </motion.div>
      </div>
    </AnimatePresence>
  )
}
