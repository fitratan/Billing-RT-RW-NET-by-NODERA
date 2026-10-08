import React, { useState } from "react"
import { AppLayout } from "@/components/layout/app-layout"
import {
  Database,
  Download,
  Trash2,
  RefreshCw,
  HardDrive,
  Layers,
  X,
  SlidersHorizontal,
} from "lucide-react"
import { PageProps } from "@/types"
import { timeAgo } from "@/lib/utils"
import { router } from "@inertiajs/react"
import { MetricCard } from "@/components/tailadmin/MetricCard"
import { adminSidebarItems, adminNavItems, adminBrand } from "@/lib/admin-nav"
import { ViewModeSwitcher, type ViewMode } from "@/components/ui/view-mode-switcher"

interface Backup {
  filename: string
  size: number
  type: string
  created_at: string | null
}

function fmtSize(b: number) {
  if (b >= 1073741824) return (b / 1073741824).toFixed(1) + " GB"
  if (b >= 1048576) return (b / 1048576).toFixed(1) + " MB"
  return (b / 1024).toFixed(0) + " KB"
}

export default function AdminBackupPage({
  backups = [],
  dbCount = 0,
  fullCount = 0,
}: PageProps<{
  backups: Backup[]
  dbCount: number
  fullCount: number
  companyName?: string
  tenantName?: string
}>) {
  const [viewMode, setViewMode] = useState<ViewMode>("table")
  const [creating, setCreating] = useState(false)
  const [manageBackup, setManageBackup] = useState<Backup | null>(null)

  const handleCreate = () => {
    setCreating(true)
    router.post(
      "/admin/backup/create",
      {},
      {
        preserveScroll: true,
        onFinish: () => setCreating(false),
      }
    )
  }

  return (
    <AppLayout
      title="Backup & Database"
      brand={adminBrand}
      sidebarItems={adminSidebarItems}
      navItems={adminNavItems}
    >
      <div className="space-y-6">
        {/* Top 3 KPI Metrics */}
        <div className="grid grid-cols-1 gap-3 sm:grid-cols-3 sm:gap-4">
          <MetricCard
            title="Total File Backup"
            value={backups.length}
            description="Arsip Tersimpan"
            icon={HardDrive}
            iconBgColor="bg-brand-50 dark:bg-brand-500/10"
            iconColor="text-brand-500 dark:text-brand-400"
          />
          <MetricCard
            title="Database SQL"
            value={dbCount}
            description="Dump SQL Terpisah"
            icon={Database}
            iconBgColor="bg-emerald-50 dark:bg-emerald-500/10"
            iconColor="text-emerald-500 dark:text-emerald-400"
          />
          <MetricCard
            title="Full Snapshot"
            value={fullCount}
            description="Snapshot Sistem Penuh"
            icon={Layers}
            iconBgColor="bg-purple-50 dark:bg-purple-500/10"
            iconColor="text-purple-500 dark:text-purple-400"
          />
        </div>

        {/* Master Card */}
        <div className="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xs dark:border-gray-800 dark:bg-white/[0.03]">
          {/* Responsive Header Toolbar */}
          <div className="p-3 sm:p-4 border-b border-gray-100 dark:border-gray-800 flex flex-col gap-2.5 sm:gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div>
              <h3 className="text-sm font-bold text-gray-900 dark:text-white">
                Daftar Snapshot &amp; Backup Database
              </h3>
              <p className="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                File backup dapat diunduh langsung untuk disimpan offline atau pemulihan darurat
              </p>
            </div>

            <div className="flex items-center gap-2 shrink-0 w-full sm:w-auto justify-end flex-wrap sm:flex-nowrap">
              <ViewModeSwitcher
                value={viewMode}
                onChange={setViewMode}
              />

              <button
                onClick={handleCreate}
                disabled={creating}
                className="h-10 px-3.5 sm:px-4 rounded-xl bg-brand-500 hover:bg-brand-600 active:scale-95 text-xs font-bold text-white shadow-xs flex items-center justify-center gap-1.5 w-full sm:w-auto shrink-0 cursor-pointer disabled:opacity-50 transition"
              >
                <RefreshCw className={creating ? "h-4 w-4 animate-spin" : "h-4 w-4"} />
                <span>{creating ? "Membuat Backup..." : "Backup Sekarang"}</span>
              </button>
            </div>
          </div>

          {/* Backup Content List: Table or Card */}
          <div className="p-4 sm:p-6">
            {backups.length === 0 ? (
              <div className="flex flex-col items-center justify-center py-12 text-center">
                <Database className="h-12 w-12 text-gray-400 dark:text-gray-600 mb-3" />
                <h4 className="text-sm font-bold text-gray-900 dark:text-white">Belum ada arsip backup</h4>
                <p className="text-xs text-gray-500 dark:text-gray-400 mt-1 max-w-sm">
                  Klik tombol &quot;Backup Sekarang&quot; di atas untuk membuat cadangan snapshot database secara instan.
                </p>
              </div>
            ) : viewMode === "table" ? (
              <div className="w-full overflow-x-auto custom-scrollbar">
                <table className="w-full min-w-[750px] text-start text-xs border-collapse">
                  <thead>
                    <tr className="border-b border-gray-200 dark:border-gray-800 text-gray-500 dark:text-gray-400 uppercase text-[11px] font-bold">
                      <th className="py-3 px-3.5 text-left">Nama File Arsip</th>
                      <th className="py-3 px-3.5 text-center">Tipe Snapshot</th>
                      <th className="py-3 px-3.5 text-left">Ukuran</th>
                      <th className="py-3 px-3.5 text-left">Waktu Dibuat</th>
                      <th className="py-3 px-3.5 text-center">Aksi</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-gray-100 dark:divide-gray-800/60 font-medium">
                    {backups.map((b: Backup) => (
                      <tr key={b.filename} className="hover:bg-gray-50/60 dark:hover:bg-gray-800/30 transition">
                        <td className="py-3 px-3.5 font-mono font-bold text-gray-900 dark:text-white max-w-[280px] truncate">
                          {b.filename}
                        </td>
                        <td className="py-3 px-3.5 text-center whitespace-nowrap">
                          <span className="rounded-lg bg-brand-500 px-2.5 py-1 text-xs font-bold uppercase tracking-wider text-white shadow-xs">
                            {b.type}
                          </span>
                        </td>
                        <td className="py-3 px-3.5 font-mono text-gray-700 dark:text-gray-300 whitespace-nowrap">
                          {fmtSize(b.size)}
                        </td>
                        <td className="py-3 px-3.5 text-gray-500 dark:text-gray-400 whitespace-nowrap">
                          {b.created_at ? timeAgo(b.created_at) : "Baru"}
                        </td>
                        <td className="py-3 px-3.5 text-center whitespace-nowrap">
                          <button
                            type="button"
                            onClick={() => setManageBackup(b)}
                            className="h-9 px-3.5 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:hover:bg-gray-800 text-xs font-bold text-gray-700 dark:text-gray-200 transition shadow-2xs inline-flex items-center gap-1.5 cursor-pointer"
                          >
                            <SlidersHorizontal className="size-3.5 text-brand-500" />
                            Kelola
                          </button>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            ) : (
              <div className="space-y-3">
                {backups.map((b: Backup) => (
                  <div
                    key={b.filename}
                    className="flex items-center justify-between gap-3 rounded-2xl border border-gray-200 bg-white p-4 shadow-xs transition-all hover:border-brand-500/50 hover:shadow-xs dark:border-gray-800 dark:bg-white/[0.02] dark:hover:border-brand-500/30"
                  >
                    <div className="flex items-center gap-3 min-w-0 flex-1">
                      <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-brand-50 border border-brand-100 text-brand-500 dark:bg-brand-500/10 dark:border-brand-500/20 dark:text-brand-400">
                        <Database className="h-5 w-5" />
                      </div>

                      <div className="min-w-0 flex-1">
                        <p className="truncate font-mono text-xs font-bold text-gray-900 dark:text-white">
                          {b.filename}
                        </p>
                        <div className="flex items-center gap-2 mt-1">
                          <span className="rounded-lg bg-brand-500 px-2.5 py-0.5 text-xs font-bold uppercase tracking-wider text-white shadow-xs">
                            {b.type}
                          </span>
                          <span className="text-xs text-gray-500 dark:text-gray-400 font-mono">
                            {fmtSize(b.size)} • {b.created_at ? timeAgo(b.created_at) : "Baru"}
                          </span>
                        </div>
                      </div>
                    </div>

                    <div className="flex items-center gap-2 shrink-0">
                      <button
                        type="button"
                        onClick={() => setManageBackup(b)}
                        className="h-9 px-3.5 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:hover:bg-gray-800 text-xs font-bold text-gray-700 dark:text-gray-200 transition shadow-2xs inline-flex items-center gap-1.5 cursor-pointer"
                      >
                        <SlidersHorizontal className="size-3.5 text-brand-500" />
                        Kelola
                      </button>
                    </div>
                  </div>
                ))}
              </div>
            )}
          </div>
        </div>
      </div>

      {/* ── MODAL KELOLA BACKUP ── */}
      {manageBackup && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs animate-fadeIn">
          <div className="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 w-full max-w-md max-h-[92vh] overflow-y-auto custom-scrollbar p-6 space-y-5 shadow-2xl relative">
            <button
              type="button"
              onClick={() => setManageBackup(null)}
              className="absolute top-4 right-4 text-gray-400 hover:text-gray-600 dark:hover:text-white p-1 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 transition cursor-pointer"
            >
              <X className="size-5" />
            </button>

            <div className="space-y-1">
              <h3 className="text-lg font-bold text-gray-900 dark:text-white">Kelola File Backup</h3>
              <p className="text-xs text-gray-500 dark:text-gray-400">Unduh atau hapus arsip snapshot database</p>
            </div>

            <div className="rounded-xl border border-gray-100 bg-gray-50/80 dark:border-gray-800 dark:bg-gray-900/50 p-4 space-y-2 text-xs">
              <div>
                <span className="text-gray-500 dark:text-gray-400 block mb-1">Nama File Arsip</span>
                <span className="font-mono font-bold text-gray-900 dark:text-white break-all text-xs">{manageBackup.filename}</span>
              </div>
              <div className="flex justify-between items-center pt-2 border-t border-gray-200/60 dark:border-gray-800/60">
                <span className="text-gray-500 dark:text-gray-400">Ukuran File</span>
                <span className="font-bold text-gray-900 dark:text-white font-mono">{fmtSize(manageBackup.size)}</span>
              </div>
              <div className="flex justify-between items-center">
                <span className="text-gray-500 dark:text-gray-400">Tipe Snapshot</span>
                <span className="rounded-lg bg-brand-500 px-2.5 py-1 text-xs font-bold uppercase tracking-wider text-white shadow-xs">
                  {manageBackup.type}
                </span>
              </div>
              <div className="flex justify-between items-center">
                <span className="text-gray-500 dark:text-gray-400">Waktu Dibuat</span>
                <span className="text-gray-700 dark:text-gray-300">{manageBackup.created_at ? timeAgo(manageBackup.created_at) : "Baru"}</span>
              </div>
            </div>

            <div className="space-y-2 pt-2 border-t border-gray-100 dark:border-gray-800">
              <button
                type="button"
                onClick={() => {
                  window.open(`/admin/backup/download/${encodeURIComponent(manageBackup.filename)}`, "_blank")
                  setManageBackup(null)
                }}
                className="w-full h-10 inline-flex items-center justify-center gap-2 px-4 rounded-xl bg-brand-500 hover:bg-brand-600 active:scale-95 text-xs font-bold text-white shadow-xs transition cursor-pointer"
              >
                <Download className="size-4" /> Unduh Arsip Database (.sql / .zip)
              </button>

              <button
                type="button"
                onClick={() => {
                  if (confirm(`Hapus file backup "${manageBackup.filename}"?`)) {
                    router.post(`/admin/backup/delete/${encodeURIComponent(manageBackup.filename)}`, {}, {
                      onSuccess: () => setManageBackup(null)
                    })
                  }
                }}
                className="w-full h-10 inline-flex items-center justify-center gap-2 px-4 rounded-xl border border-rose-200 text-rose-600 hover:bg-rose-50 dark:border-rose-900/40 dark:text-rose-400 dark:hover:bg-rose-950/20 text-xs font-semibold transition cursor-pointer"
              >
                <Trash2 className="size-4" /> Hapus File Backup Ini
              </button>

              <button
                type="button"
                onClick={() => setManageBackup(null)}
                className="w-full h-10 inline-flex items-center justify-center px-4 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:hover:bg-gray-800 text-xs font-semibold text-gray-700 dark:text-gray-200 transition shadow-2xs cursor-pointer"
              >
                Tutup
              </button>
            </div>
          </div>
        </div>
      )}
    </AppLayout>
  )
}
