import React, { useState } from "react"
import { AppLayout } from "@/components/layout/app-layout"
import {
  Image as ImageIcon,
  Plus,
  Trash2,
  CheckCircle2,
  X,
  Layers,
  Sparkles,
  SlidersHorizontal,
  ExternalLink,
} from "lucide-react"
import { PageProps } from "@/types"
import { adminSidebarItems, adminNavItems, adminBrand } from "@/lib/admin-nav"
import { router } from "@inertiajs/react"
import { Switch } from "@/components/tailadmin/Switch"
import { ViewModeSwitcher, type ViewMode } from "@/components/ui/view-mode-switcher"

interface Slide {
  id: number
  title: string
  image: string | null
  is_active: boolean
  sort_order: number
}

export default function PromoSlidesPage({ slides = [] }: PageProps<{ slides: Slide[] }>) {
  const [viewMode, setViewMode] = useState<ViewMode>("grid")
  const [selectedManageSlide, setSelectedManageSlide] = useState<Slide | null>(null)
  const [addModalOpen, setAddModalOpen] = useState(false)
  const [newTitle, setNewTitle] = useState("")
  const [newImage, setNewImage] = useState("")
  const [newSortOrder, setNewSortOrder] = useState(1)
  const [newIsActive, setNewIsActive] = useState(true)
  const [submitting, setSubmitting] = useState(false)

  const activeCount = slides.filter((s) => s.is_active).length

  const handleAddSlide = (e: React.FormEvent) => {
    e.preventDefault()
    setSubmitting(true)
    router.post(
      "/admin/promo-slides",
      {
        title: newTitle,
        image: newImage,
        sort_order: newSortOrder,
        is_active: newIsActive,
      },
      {
        onSuccess: () => {
          setAddModalOpen(false)
          setNewTitle("")
          setNewImage("")
          setNewSortOrder(1)
          setNewIsActive(true)
        },
        onFinish: () => setSubmitting(false),
      }
    )
  }

  const handleDeleteSlide = (id: number) => {
    if (confirm("Yakin ingin menghapus slide promo ini?")) {
      router.delete(`/admin/promo-slides/${id}`, {
        onSuccess: () => setSelectedManageSlide(null),
      })
    }
  }

  const handleToggleActive = (slide: Slide, currentActive: boolean) => {
    router.patch(`/admin/promo-slides/${slide.id}`, {
      is_active: !currentActive,
    }, {
      onSuccess: () => {
        if (selectedManageSlide && selectedManageSlide.id === slide.id) {
          setSelectedManageSlide({ ...selectedManageSlide, is_active: !currentActive })
        }
      }
    })
  }

  return (
    <AppLayout
      title="Banner & Slide Promo"
      brand={adminBrand}
      sidebarItems={adminSidebarItems}
      navItems={adminNavItems}
    >
      <div className="space-y-6">
        {/* Top Metric Cards */}
        <div className="grid grid-cols-2 sm:grid-cols-3 gap-3">
          <div className="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03] p-4 shadow-xs">
            <div className="flex items-center justify-between">
              <span className="text-xs font-semibold text-gray-500 dark:text-gray-400">Total Banner</span>
              <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold bg-brand-500 text-white shadow-xs whitespace-nowrap">
                Slide
              </span>
            </div>
            <div className="mt-2 text-xl font-bold text-gray-900 dark:text-white">
              {slides.length} Banner
            </div>
          </div>

          <div className="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03] p-4 shadow-xs">
            <div className="flex items-center justify-between">
              <span className="text-xs font-semibold text-gray-500 dark:text-gray-400">Slide Aktif</span>
              <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold bg-emerald-500 text-white shadow-xs whitespace-nowrap">
                Tayang
              </span>
            </div>
            <div className="mt-2 text-xl font-bold text-emerald-600 dark:text-emerald-400">
              {activeCount}
            </div>
          </div>

          <div className="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03] p-4 shadow-xs col-span-2 sm:col-span-1">
            <div className="flex items-center justify-between">
              <span className="text-xs font-semibold text-gray-500 dark:text-gray-400">Slide Nonaktif</span>
              <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold bg-gray-500 text-white shadow-xs whitespace-nowrap">
                Draft
              </span>
            </div>
            <div className="mt-2 text-xl font-bold text-gray-900 dark:text-white">
              {slides.length - activeCount}
            </div>
          </div>
        </div>

        {/* Responsive Action Toolbar */}
        <div className="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03] p-4 shadow-xs flex flex-col gap-2.5 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <h3 className="text-sm font-bold text-gray-900 dark:text-white">Daftar Slide Promo Pelanggan</h3>
            <p className="text-xs text-gray-500 dark:text-gray-400">Banner interaktif yang tampil di aplikasi portal pelanggan</p>
          </div>

          <div className="flex flex-wrap items-center gap-2">
            <ViewModeSwitcher
              value={viewMode}
              onChange={setViewMode}
            />

            <button
              type="button"
              onClick={() => setAddModalOpen(true)}
              className="h-10 inline-flex items-center gap-2 px-4 rounded-xl bg-brand-500 hover:bg-brand-600 active:scale-95 text-xs font-bold text-white shadow-xs transition cursor-pointer"
            >
              <Plus className="size-4" /> Tambah Slide Promo
            </button>
          </div>
        </div>

        {/* Slides Content: Table or Grid */}
        {slides.length === 0 ? (
          <div className="p-12 text-center text-gray-400 border border-dashed border-gray-200 dark:border-gray-800 rounded-2xl bg-white dark:bg-white/[0.02]">
            <ImageIcon className="mx-auto h-10 w-10 text-gray-300 dark:text-gray-600 mb-2" />
            <p className="font-semibold text-gray-700 dark:text-gray-300">Belum ada slide promo</p>
            <p className="text-xs text-gray-500 dark:text-gray-400 mt-1">Tambahkan banner untuk mempromosikan paket atau pengumuman penting.</p>
          </div>
        ) : viewMode === "table" ? (
          <div className="p-4 rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03] shadow-xs">
            <div className="w-full overflow-x-auto custom-scrollbar">
              <table className="w-full min-w-[900px] text-start text-xs border-collapse">
                <thead>
                  <tr className="border-b border-gray-200 dark:border-gray-800 text-gray-500 dark:text-gray-400 uppercase text-[11px] font-bold">
                    <th className="py-3 px-3.5 text-center w-16">Urutan</th>
                    <th className="py-3 px-3.5 text-left w-32">Pratinjau</th>
                    <th className="py-3 px-3.5 text-left">Judul Slide</th>
                    <th className="py-3 px-3.5 text-left">URL Gambar</th>
                    <th className="py-3 px-3.5 text-center">Status Tayang</th>
                    <th className="py-3 px-3.5 text-center">Aksi</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-gray-100 dark:divide-gray-800/60 font-medium">
                  {slides.map((s) => (
                    <tr key={s.id} className="hover:bg-gray-50/60 dark:hover:bg-gray-800/30 transition">
                      <td className="py-3 px-3.5 text-center font-mono font-bold text-gray-700 dark:text-gray-300">
                        #{s.sort_order}
                      </td>
                      <td className="py-3 px-3.5">
                        <div className="h-12 w-24 rounded-lg bg-gray-100 dark:bg-gray-800 overflow-hidden border border-gray-200 dark:border-gray-700 flex items-center justify-center shrink-0">
                          {s.image ? (
                            <img src={s.image} alt={s.title} className="h-full w-full object-cover" />
                          ) : (
                            <ImageIcon className="size-4 text-gray-400" />
                          )}
                        </div>
                      </td>
                      <td className="py-3 px-3.5 font-bold text-gray-900 dark:text-white max-w-[200px] truncate">
                        {s.title}
                      </td>
                      <td className="py-3 px-3.5 text-gray-500 dark:text-gray-400 font-mono text-[11px] max-w-[240px] truncate">
                        {s.image || "-"}
                      </td>
                      <td className="py-3 px-3.5 text-center whitespace-nowrap">
                        <span
                          className={`inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-bold text-white shadow-xs ${
                            s.is_active ? "bg-emerald-500" : "bg-gray-500"
                          }`}
                        >
                          {s.is_active ? "Tayang" : "Nonaktif"}
                        </span>
                      </td>
                      <td className="py-3 px-3.5 text-center whitespace-nowrap">
                        <button
                          type="button"
                          onClick={() => setSelectedManageSlide(s)}
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
          </div>
        ) : (
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            {slides.map((s) => (
              <div
                key={s.id}
                className="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03] p-4 shadow-xs space-y-3 flex flex-col justify-between"
              >
                <div>
                  <div className="h-36 w-full rounded-xl bg-gray-100 dark:bg-gray-800 overflow-hidden border border-gray-200 dark:border-gray-700 flex items-center justify-center">
                    {s.image ? (
                      <img src={s.image} alt={s.title} className="h-full w-full object-cover" />
                    ) : (
                      <ImageIcon className="size-8 text-gray-400" />
                    )}
                  </div>

                  <div className="mt-3 flex items-start justify-between gap-2">
                    <div className="min-w-0 flex-1">
                      <h4 className="font-bold text-sm text-gray-900 dark:text-white truncate">{s.title}</h4>
                      <p className="text-xs text-gray-500 dark:text-gray-400 font-mono">Urutan: #{s.sort_order}</p>
                    </div>

                    <span
                      className={`inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-bold text-white shadow-xs ${
                        s.is_active ? "bg-emerald-500" : "bg-gray-500"
                      }`}
                    >
                      {s.is_active ? "Tayang" : "Nonaktif"}
                    </span>
                  </div>
                </div>

                <div className="pt-2 border-t border-gray-100 dark:border-gray-800 flex justify-end">
                  <button
                    type="button"
                    onClick={() => setSelectedManageSlide(s)}
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

      {/* ── MODAL KELOLA SLIDE PROMO ── */}
      {selectedManageSlide && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs animate-fadeIn">
          <div className="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 w-full max-w-md max-h-[92vh] overflow-y-auto custom-scrollbar p-6 space-y-5 shadow-2xl relative">
            <button
              type="button"
              onClick={() => setSelectedManageSlide(null)}
              className="absolute top-4 right-4 text-gray-400 hover:text-gray-600 dark:hover:text-white p-1 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 transition cursor-pointer"
            >
              <X className="size-5" />
            </button>

            <div className="space-y-1">
              <h3 className="text-lg font-bold text-gray-900 dark:text-white">Kelola Slide Promo</h3>
              <p className="text-xs text-gray-500 dark:text-gray-400">Pengaturan tayang dan aksi cepat banner</p>
            </div>

            <div className="h-36 w-full rounded-xl bg-gray-100 dark:bg-gray-800 overflow-hidden border border-gray-200 dark:border-gray-700 flex items-center justify-center">
              {selectedManageSlide.image ? (
                <img src={selectedManageSlide.image} alt={selectedManageSlide.title} className="h-full w-full object-cover" />
              ) : (
                <ImageIcon className="size-8 text-gray-400" />
              )}
            </div>

            <div className="rounded-xl border border-gray-100 bg-gray-50/80 dark:border-gray-800 dark:bg-gray-900/50 p-3.5 space-y-2 text-xs">
              <div className="flex justify-between items-center">
                <span className="text-gray-500 dark:text-gray-400">Judul Slide</span>
                <span className="font-bold text-gray-900 dark:text-white">{selectedManageSlide.title}</span>
              </div>
              <div className="flex justify-between items-center">
                <span className="text-gray-500 dark:text-gray-400">Urutan Tampil</span>
                <span className="font-mono font-bold text-gray-900 dark:text-white">#{selectedManageSlide.sort_order}</span>
              </div>
              <div className="flex justify-between items-center">
                <span className="text-gray-500 dark:text-gray-400">Status Tayang</span>
                <span className={`inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-bold text-white shadow-xs ${selectedManageSlide.is_active ? "bg-emerald-500" : "bg-gray-500"}`}>
                  {selectedManageSlide.is_active ? "Tayang di Aplikasi" : "Nonaktif"}
                </span>
              </div>
            </div>

            {/* Status Switch Box */}
            <div className="rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-950 p-3.5 flex items-center justify-between">
              <div>
                <span className="text-xs font-bold text-gray-900 dark:text-white block">Status Tayang</span>
                <span className="text-[11px] text-gray-500 dark:text-gray-400">Tampilkan slide di beranda pelanggan</span>
              </div>
              <Switch
                checked={selectedManageSlide.is_active}
                onChange={(checked) => handleToggleActive(selectedManageSlide, !checked)}
              />
            </div>

            <div className="space-y-2 pt-2 border-t border-gray-100 dark:border-gray-800">
              {selectedManageSlide.image && (
                <a
                  href={selectedManageSlide.image}
                  target="_blank"
                  rel="noreferrer"
                  className="w-full h-10 inline-flex items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:hover:bg-gray-800 text-xs font-semibold text-gray-700 dark:text-gray-200 transition shadow-2xs cursor-pointer"
                >
                  <ExternalLink className="size-4 text-brand-500" /> Lihat Gambar Asli
                </a>
              )}

              <button
                type="button"
                onClick={() => handleDeleteSlide(selectedManageSlide.id)}
                className="w-full h-10 inline-flex items-center justify-center gap-1.5 px-3.5 rounded-xl bg-rose-500 hover:bg-rose-600 active:scale-95 text-xs font-bold text-white shadow-xs transition cursor-pointer"
              >
                <Trash2 className="size-4" /> Hapus Slide Promo
              </button>

              <button
                type="button"
                onClick={() => setSelectedManageSlide(null)}
                className="w-full h-10 inline-flex items-center justify-center px-4 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:hover:bg-gray-800 text-xs font-semibold text-gray-700 dark:text-gray-200 transition shadow-2xs cursor-pointer"
              >
                Tutup
              </button>
            </div>
          </div>
        </div>
      )}

      {/* ── MODAL TAMBAH SLIDE PROMO ── */}
      {addModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs animate-fadeIn">
          <div className="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 w-full max-w-md max-h-[92vh] overflow-y-auto custom-scrollbar p-6 space-y-5 shadow-2xl relative">
            <button
              type="button"
              onClick={() => setAddModalOpen(false)}
              className="absolute top-4 right-4 text-gray-400 hover:text-gray-600 dark:hover:text-white p-1 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 transition cursor-pointer"
            >
              <X className="size-5" />
            </button>

            <div className="space-y-1">
              <h3 className="text-lg font-bold text-gray-900 dark:text-white">Tambah Slide Promo Baru</h3>
              <p className="text-xs text-gray-500 dark:text-gray-400">Tambahkan banner untuk beranda aplikasi pelanggan</p>
            </div>

            <form onSubmit={handleAddSlide} className="space-y-4">
              <div className="space-y-1.5">
                <label className="text-xs font-bold text-gray-700 dark:text-gray-200">Judul Slide</label>
                <input
                  type="text"
                  required
                  value={newTitle}
                  onChange={(e) => setNewTitle(e.target.value)}
                  placeholder="Contoh: Promo Cashback 50% Bulan Ini"
                  className="w-full h-10 rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-950 px-3.5 text-xs text-gray-900 dark:text-white focus:border-brand-500 focus:outline-hidden"
                />
              </div>

              <div className="space-y-1.5">
                <label className="text-xs font-bold text-gray-700 dark:text-gray-200">URL Gambar Banner</label>
                <input
                  type="url"
                  required
                  value={newImage}
                  onChange={(e) => setNewImage(e.target.value)}
                  placeholder="https://dgtlnetsolution.com/banner-promo.jpg"
                  className="w-full h-10 rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-950 px-3.5 text-xs font-mono text-gray-900 dark:text-white focus:border-brand-500 focus:outline-hidden"
                />
              </div>

              <div className="space-y-1.5">
                <label className="text-xs font-bold text-gray-700 dark:text-gray-200">Urutan Prioritas Tampil</label>
                <input
                  type="number"
                  min={1}
                  required
                  value={newSortOrder}
                  onChange={(e) => setNewSortOrder(parseInt(e.target.value) || 1)}
                  className="w-full h-10 rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-950 px-3.5 text-xs text-gray-900 dark:text-white focus:border-brand-500 focus:outline-hidden"
                />
              </div>

              <div className="rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-950 p-3.5 flex items-center justify-between">
                <div>
                  <span className="text-xs font-bold text-gray-900 dark:text-white block">Langsung Tayangkan</span>
                  <span className="text-[11px] text-gray-500 dark:text-gray-400">Aktifkan agar langsung muncul di aplikasi</span>
                </div>
                <Switch
                  checked={newIsActive}
                  onChange={(checked) => setNewIsActive(checked)}
                />
              </div>

              <div className="pt-2 flex justify-end gap-2 border-t border-gray-100 dark:border-gray-800">
                <button
                  type="button"
                  onClick={() => setAddModalOpen(false)}
                  className="h-10 px-4 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:hover:bg-gray-800 text-xs font-semibold text-gray-700 dark:text-gray-200 transition shadow-2xs cursor-pointer"
                >
                  Batal
                </button>
                <button
                  type="submit"
                  disabled={submitting}
                  className="h-10 px-5 bg-brand-500 hover:bg-brand-600 text-white font-bold text-xs rounded-xl transition shadow-xs cursor-pointer disabled:opacity-50"
                >
                  Simpan Banner
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </AppLayout>
  )
}
