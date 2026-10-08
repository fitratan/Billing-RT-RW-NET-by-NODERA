import { AppLayout } from "@/components/layout/app-layout"
import {
  LifeBuoy,
  Plus,
  Send,
  MessageCircle,
  Ticket,
  X,
} from "lucide-react"
import { PageProps } from "@/types"
import { timeAgo } from "@/lib/utils"
import { useForm } from "@inertiajs/react"
import { useState } from "react"
import { Badge } from "@/components/ui/badge"
import { portalNavItems, portalBrand } from "@/lib/portal-nav"

interface TroubleTicketItem {
  id: number
  title: string
  description: string | null
  status: string
  created_at: string | null
}

export default function PortalLaporanPage({
  tickets = [],
  companyName = "NODERA",
  adminWa,
  customer,
}: PageProps<{
  tickets: TroubleTicketItem[]
  companyName?: string
  adminWa?: string
  customer?: any
}>) {
  const [open, setOpen] = useState(false)

  const { data, setData, post, processing, reset } = useForm<{ title: string; description: string }>({
    title: "",
    description: "",
  })

  const submit = (e: React.FormEvent) => {
    e.preventDefault()
    post("/portal/laporan", {
      preserveScroll: true,
      onSuccess: () => {
        setOpen(false)
        reset()
      },
    })
  }

  return (
    <AppLayout
      title="Pusat Bantuan & Laporan"
      subtitle={companyName}
      brand={portalBrand}
      navItems={portalNavItems}
      hideSidebar={true}
    >
      <div className="space-y-4 max-w-4xl mx-auto pb-32 sm:pb-20">
        {/* =========================================================================
            HEADER ACTION CARD
            ========================================================================= */}
        <div className="rounded-2xl border border-gray-200/80 bg-white p-4 shadow-xs dark:border-gray-800 dark:bg-white/[0.03] space-y-3">
          <div className="flex items-center justify-between">
            <div>
              <h2 className="text-xs sm:text-sm font-bold text-gray-900 dark:text-white">Butuh Bantuan Kendala?</h2>
              <p className="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5">
                Buat tiket laporan kendala atau hubungi admin WhatsApp langsung.
              </p>
            </div>
          </div>

          <div className="grid grid-cols-2 gap-2 pt-1">
            {adminWa ? (
              <a
                href={`https://wa.me/${adminWa.replace(/[^0-9]/g, "")}?text=${encodeURIComponent(
                  `Halo Admin ${companyName}, saya ingin meminta bantuan layanan internet.`
                )}`}
                target="_blank"
                rel="noreferrer"
                className="flex items-center justify-center gap-1.5 rounded-xl border border-emerald-500/30 bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400 py-2.5 text-xs font-bold shadow-xs active:scale-95 transition-all text-center"
              >
                <MessageCircle className="h-3.5 w-3.5" />
                <span>Chat WA</span>
              </a>
            ) : (
              <div />
            )}

            <button
              type="button"
              onClick={() => setOpen(true)}
              className="flex items-center justify-center gap-1.5 rounded-xl bg-brand-500 hover:bg-brand-600 text-white py-2.5 text-xs font-bold shadow-xs active:scale-95 transition-all cursor-pointer"
            >
              <Plus className="h-3.5 w-3.5" />
              <span>Buat Laporan</span>
            </button>
          </div>
        </div>

        {/* =========================================================================
            TICKETS LIST
            ========================================================================= */}
        <div className="space-y-2.5">
          <h3 className="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 flex items-center gap-1.5">
            <Ticket className="h-3.5 w-3.5 text-brand-500" />
            <span>Riwayat Tiket Laporan</span>
          </h3>

          {tickets.length === 0 ? (
            <div className="rounded-2xl border border-gray-200/80 bg-white p-8 text-center text-gray-500 shadow-xs dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-400">
              <LifeBuoy className="h-8 w-8 mx-auto text-gray-400 mb-2" />
              <p className="text-xs sm:text-sm font-bold text-gray-900 dark:text-white">Tidak Ada Tiket Gangguan</p>
              <p className="text-[11px] mt-0.5">Koneksi Anda saat ini terpantau berjalan normal.</p>
            </div>
          ) : (
            <div className="space-y-2.5">
              {tickets.map((t) => (
                <div
                  key={t.id}
                  className="rounded-2xl border border-gray-200/80 bg-white p-3.5 sm:p-4 shadow-xs dark:border-gray-800 dark:bg-white/[0.03] space-y-2"
                >
                  <div className="flex items-start justify-between gap-2">
                    <h4 className="text-xs sm:text-sm font-bold text-gray-900 dark:text-white">{t.title}</h4>
                    <Badge
                      variant={
                        t.status === "closed"
                          ? "success"
                          : t.status === "in_progress"
                          ? "info"
                          : "warning"
                      }
                      className="text-[10px] uppercase tracking-wider shrink-0"
                    >
                      {t.status === "closed" ? "Selesai" : t.status === "in_progress" ? "Dikerjakan" : "Menunggu"}
                    </Badge>
                  </div>
                  {t.description && (
                    <p className="text-[11px] text-gray-600 dark:text-gray-400 whitespace-pre-wrap">{t.description}</p>
                  )}
                  <p className="text-[10px] text-gray-400 dark:text-gray-500 pt-1">
                    Dibuat {t.created_at ? timeAgo(t.created_at) : "-"}
                  </p>
                </div>
              ))}
            </div>
          )}
        </div>
      </div>

      {/* Modal Popup Buat Tiket */}
      {open && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-xs">
          <div className="w-full max-w-md rounded-2xl bg-white p-5 shadow-2xl dark:bg-[#151B26] border border-gray-100 dark:border-gray-800 space-y-4">
            <div className="flex items-center justify-between">
              <h3 className="text-sm font-bold text-gray-900 dark:text-white">Buat Laporan Kendala</h3>
              <button
                type="button"
                onClick={() => setOpen(false)}
                className="rounded-lg p-1 text-gray-400 hover:bg-gray-100 dark:hover:bg-white/[0.06]"
              >
                <X className="h-4 w-4" />
              </button>
            </div>

            <form onSubmit={submit} className="space-y-3">
              <div>
                <label className="block text-[11px] font-semibold text-gray-700 dark:text-gray-300 mb-1">
                  Judul Kendala
                </label>
                <input
                  type="text"
                  value={data.title}
                  onChange={(e) => setData("title", e.target.value)}
                  placeholder="Contoh: Internet lambat / Lampu LOS merah"
                  required
                  className="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-xs text-gray-900 dark:border-gray-800 dark:bg-white/[0.04] dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-500"
                />
              </div>

              <div>
                <label className="block text-[11px] font-semibold text-gray-700 dark:text-gray-300 mb-1">
                  Detail Kendala
                </label>
                <textarea
                  value={data.description}
                  onChange={(e) => setData("description", e.target.value)}
                  rows={4}
                  placeholder="Deskripsikan kendala yang dialami..."
                  required
                  className="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-xs text-gray-900 dark:border-gray-800 dark:bg-white/[0.04] dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-500"
                />
              </div>

              <div className="flex items-center gap-2 pt-1">
                <button
                  type="button"
                  onClick={() => setOpen(false)}
                  className="w-1/3 rounded-xl border border-gray-200 bg-gray-50 py-2.5 text-xs font-bold text-gray-700 dark:border-gray-800 dark:bg-white/[0.04] dark:text-gray-300"
                >
                  Batal
                </button>
                <button
                  type="submit"
                  disabled={processing}
                  className="flex-1 flex items-center justify-center gap-1.5 rounded-xl bg-brand-500 hover:bg-brand-600 text-white py-2.5 text-xs font-bold shadow-xs active:scale-95 transition disabled:opacity-50"
                >
                  <Send className="h-3.5 w-3.5" />
                  <span>{processing ? "Mengirim..." : "Kirim Laporan"}</span>
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </AppLayout>
  )
}
