import React from "react"
import { motion, AnimatePresence } from "framer-motion"
import {
  X,
  RefreshCw,
  ArrowRight,
  ArrowLeft,
  ArrowUpDown,
  Server,
  Database,
  ShieldCheck,
  CheckCircle2,
  Info,
  Layers,
} from "lucide-react"
import { cn } from "@/lib/utils"

interface SyncInfoModalProps {
  open: boolean
  onClose: () => void
}

export function SyncInfoModal({ open, onClose }: SyncInfoModalProps) {
  if (!open) return null

  return (
    <AnimatePresence>
      <div className="fixed inset-0 z-[99999] flex items-end sm:items-center justify-center p-0 sm:p-4 animate-in fade-in duration-200">
        {/* Backdrop */}
        <motion.div
          initial={{ opacity: 0 }}
          animate={{ opacity: 1 }}
          exit={{ opacity: 0 }}
          className="fixed inset-0 bg-black/60 backdrop-blur-xs"
          onClick={onClose}
        />

        {/* Modal Card */}
        <motion.div
          initial={{ opacity: 0, scale: 0.95, y: 16 }}
          animate={{ opacity: 1, scale: 1, y: 0 }}
          exit={{ opacity: 0, scale: 0.95, y: 16 }}
          transition={{ type: "spring", stiffness: 400, damping: 30 }}
          className="relative flex max-h-[92vh] w-full max-w-2xl flex-col overflow-hidden rounded-t-3xl sm:rounded-3xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 text-gray-900 dark:text-white shadow-2xl z-10"
        >
          {/* Header */}
          <div className="flex items-center justify-between border-b border-gray-100 dark:border-gray-800 px-5 py-4 shrink-0 bg-gray-50/75 dark:bg-gray-900/50">
            <div className="flex items-center gap-3">
              <div className="flex h-10 w-10 items-center justify-center rounded-2xl bg-brand-50 text-brand-600 dark:bg-brand-950 dark:text-brand-400 border border-brand-200 dark:border-brand-800">
                <ArrowUpDown className="h-5 w-5" />
              </div>
              <div>
                <h3 className="font-bold text-base sm:text-lg text-gray-900 dark:text-white tracking-tight flex items-center gap-2">
                  Panduan Sinkronisasi 2 Arah
                  <span className="rounded-full bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800 px-2 py-0.5 text-[10px] font-bold">
                    100% Akurat
                  </span>
                </h3>
                <p className="text-xs text-gray-500 dark:text-gray-400">Mekanisme integrasi data antara Database NODERA &amp; Router MikroTik</p>
              </div>
            </div>
            <button
              type="button"
              onClick={onClose}
              className="flex h-8 w-8 items-center justify-center rounded-xl border border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-800 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-white transition cursor-pointer"
            >
              <X className="h-4 w-4" />
            </button>
          </div>

          {/* Content Body */}
          <div className="flex-1 overflow-y-auto p-5 sm:p-6 space-y-5 text-xs sm:text-sm">
            
            {/* Flow Visualizer Card */}
            <div className="grid grid-cols-3 items-center justify-between p-4 rounded-2xl border border-gray-200 bg-gray-50/75 dark:border-gray-800 dark:bg-gray-950/50 text-center gap-2">
              <div className="flex flex-col items-center gap-1.5">
                <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-brand-50 text-brand-600 dark:bg-brand-950 dark:text-brand-400 border border-brand-200 dark:border-brand-800">
                  <Database className="h-5 w-5" />
                </div>
                <span className="text-[11px] font-bold text-gray-900 dark:text-gray-200">Aplikasi Web</span>
                <span className="text-[9px] text-gray-500 dark:text-gray-400 font-mono">MySQL Database</span>
              </div>

              <div className="flex flex-col items-center gap-1">
                <div className="flex items-center gap-1 text-brand-500">
                  <span className="h-0.5 w-6 sm:w-10 bg-gradient-to-r from-brand-500 to-emerald-500"></span>
                  <RefreshCw className="h-4 w-4 animate-spin text-emerald-500" />
                  <span className="h-0.5 w-6 sm:w-10 bg-gradient-to-r from-emerald-500 to-brand-500"></span>
                </div>
                <span className="text-[10px] font-bold text-emerald-600 dark:text-emerald-400">Sinkron 2 Arah</span>
              </div>

              <div className="flex flex-col items-center gap-1.5">
                <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-purple-50 text-purple-600 dark:bg-purple-950 dark:text-purple-400 border border-purple-200 dark:border-purple-800">
                  <Server className="h-5 w-5" />
                </div>
                <span className="text-[11px] font-bold text-gray-900 dark:text-gray-200">MikroTik Router</span>
                <span className="text-[9px] text-gray-500 dark:text-gray-400 font-mono">RouterOS Secret/Queue</span>
              </div>
            </div>

            {/* Direction 1: Push Sync */}
            <div className="rounded-2xl border border-sky-200 bg-sky-50/40 dark:border-sky-900/40 dark:bg-sky-950/20 p-4 sm:p-5 space-y-3">
              <div className="flex items-center justify-between gap-2 flex-wrap">
                <div className="flex items-center gap-2">
                  <span className="flex h-6 w-6 items-center justify-center rounded-full bg-brand-500 text-white font-black text-xs">
                    1
                  </span>
                  <h4 className="text-sm font-bold text-gray-900 dark:text-white flex items-center gap-1.5">
                    Arah Aplikasi <ArrowRight className="h-3.5 w-3.5 text-brand-500" /> MikroTik
                    <span className="text-[11px] text-brand-600 dark:text-brand-400 font-medium font-mono">(Push Sync)</span>
                  </h4>
                </div>
                <span className="rounded-md border border-brand-200 bg-brand-50 dark:border-brand-800 dark:bg-brand-950 px-2 py-0.5 text-[10px] font-bold text-brand-600 dark:text-brand-400">
                  Web → Router
                </span>
              </div>

              {/* Kondisi */}
              <div className="rounded-xl border border-sky-200 bg-white dark:border-sky-800/60 dark:bg-sky-950/40 p-3 text-xs text-sky-800 dark:text-sky-200">
                <strong className="text-gray-900 dark:text-white block mb-0.5">Kapan Digunakan / Kondisi:</strong>
                Anda menambah/mengubah data pelanggan di web saat MikroTik mati/offline, lalu klik <strong className="text-gray-900 dark:text-white">"Sync MikroTik"</strong> saat router sudah menyala kembali.
              </div>

              {/* Cara Kerja */}
              <div className="space-y-2 text-xs text-gray-600 dark:text-gray-300">
                <div className="font-semibold text-gray-800 dark:text-gray-200">Cara Kerja Sistem:</div>
                <ul className="space-y-1.5 pl-1">
                  <li className="flex items-start gap-2">
                    <CheckCircle2 className="h-4 w-4 text-emerald-500 shrink-0 mt-0.5" />
                    <span>Sistem memindai semua pelanggan di database aplikasi NODERA.</span>
                  </li>
                  <li className="flex items-start gap-2">
                    <CheckCircle2 className="h-4 w-4 text-emerald-500 shrink-0 mt-0.5" />
                    <span>Mengecek apakah secret/akun tersebut sudah terdaftar di RouterOS.</span>
                  </li>
                  <li className="flex items-start gap-2">
                    <CheckCircle2 className="h-4 w-4 text-emerald-500 shrink-0 mt-0.5" />
                    <span>Jika <strong>belum ada</strong> &rarr; Aplikasi otomatis membuat <code className="font-mono text-brand-600 dark:text-cyan-300 bg-gray-100 dark:bg-black/40 px-1 rounded">/ppp/secret</code> baru di MikroTik lengkap dengan <strong>Username</strong>, <strong>Password Secret Kustom</strong>, dan <strong>Profil Bandwidth</strong> yang sesuai.</span>
                  </li>
                  <li className="flex items-start gap-2">
                    <CheckCircle2 className="h-4 w-4 text-emerald-500 shrink-0 mt-0.5" />
                    <span>Untuk pelanggan <strong>Static IP</strong> &rarr; Aplikasi otomatis mendaftarkan <strong>IP-MAC ARP Binding</strong> dan <strong>Simple Queue Bandwidth</strong> di MikroTik.</span>
                  </li>
                </ul>
              </div>
            </div>

            {/* Direction 2: Pull Sync */}
            <div className="rounded-2xl border border-purple-200 bg-purple-50/40 dark:border-purple-900/40 dark:bg-purple-950/20 p-4 sm:p-5 space-y-3">
              <div className="flex items-center justify-between gap-2 flex-wrap">
                <div className="flex items-center gap-2">
                  <span className="flex h-6 w-6 items-center justify-center rounded-full bg-purple-600 text-white font-black text-xs">
                    2
                  </span>
                  <h4 className="text-sm font-bold text-gray-900 dark:text-white flex items-center gap-1.5">
                    Arah MikroTik <ArrowRight className="h-3.5 w-3.5 text-purple-500" /> Aplikasi
                    <span className="text-[11px] text-purple-600 dark:text-purple-300 font-medium font-mono">(Pull Sync / Import)</span>
                  </h4>
                </div>
                <span className="rounded-md border border-purple-200 bg-purple-50 dark:border-purple-800 dark:bg-purple-950 px-2 py-0.5 text-[10px] font-bold text-purple-600 dark:text-purple-300">
                  Router → Web
                </span>
              </div>

              {/* Kondisi */}
              <div className="rounded-xl border border-purple-200 bg-white dark:border-purple-800/60 dark:bg-purple-950/40 p-3 text-xs text-purple-800 dark:text-purple-200">
                <strong className="text-gray-900 dark:text-white block mb-0.5">Kapan Digunakan / Kondisi:</strong>
                Anda sebelumnya sudah memiliki ratusan secret di Winbox MikroTik, atau teknisi menambah secret langsung lewat Winbox di lapangan.
              </div>

              {/* Cara Kerja */}
              <div className="space-y-2 text-xs text-gray-600 dark:text-gray-300">
                <div className="font-semibold text-gray-800 dark:text-gray-200">Cara Kerja Sistem:</div>
                <ul className="space-y-1.5 pl-1">
                  <li className="flex items-start gap-2">
                    <CheckCircle2 className="h-4 w-4 text-emerald-500 shrink-0 mt-0.5" />
                    <span>Aplikasi menarik data live dari <code className="font-mono text-purple-600 dark:text-cyan-300 bg-gray-100 dark:bg-black/40 px-1 rounded">/ppp/secret/print</code> di RouterOS.</span>
                  </li>
                  <li className="flex items-start gap-2">
                    <CheckCircle2 className="h-4 w-4 text-emerald-500 shrink-0 mt-0.5" />
                    <span>Sistem membaca <strong>Username</strong>, <strong>Password Secret Asli</strong>, serta <strong>Profil Kecepatan</strong>.</span>
                  </li>
                  <li className="flex items-start gap-2">
                    <CheckCircle2 className="h-4 w-4 text-emerald-500 shrink-0 mt-0.5" />
                    <span>Aplikasi mencocokkan profil ke daftar <strong>Paket Langganan</strong> dan menyimpan pelanggan baru tanpa menduplikasi data yang sudah ada (<em>Idempotent updateOrCreate</em>).</span>
                  </li>
                </ul>
              </div>
            </div>

            {/* Note on Payment & Isolir */}
            <div className="rounded-2xl border border-emerald-200 bg-emerald-50/50 dark:border-emerald-900/40 dark:bg-emerald-950/20 p-4 space-y-1.5 text-xs text-emerald-800 dark:text-emerald-200">
              <div className="flex items-center gap-1.5 font-bold text-emerald-700 dark:text-emerald-300">
                <ShieldCheck className="h-4 w-4" />
                <span>Catatan Operasional Kasir &amp; Isolir:</span>
              </div>
              <p className="text-gray-600 dark:text-gray-300 leading-relaxed">
                • <strong>Bayar Tagihan Aktif</strong>: Transaksi murni pencatatan kas di database tanpa mengganggu/kick koneksi internet pelanggan yang sedang online.<br />
                • <strong>Bayar Tagihan Terisolir</strong>: Sistem otomatis membuka isolir di MikroTik dan me-refresh koneksi pelanggan ke profil kecepatan normal kembali.
              </p>
            </div>

          </div>

          {/* Footer Action */}
          <div className="border-t border-gray-100 dark:border-gray-800 p-4 bg-gray-50/75 dark:bg-gray-900/50 shrink-0 flex justify-end">
            <button
              type="button"
              onClick={onClose}
              className="h-10 px-6 rounded-xl bg-brand-500 hover:bg-brand-600 text-xs font-bold text-white shadow-xs transition-all active:scale-95 cursor-pointer"
            >
              Mengerti / Tutup
            </button>
          </div>
        </motion.div>
      </div>
    </AnimatePresence>
  )
}
