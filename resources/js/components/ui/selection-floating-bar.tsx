import * as React from "react"
import { X, CheckCheck, LucideIcon, Layers, ChevronRight, SlidersHorizontal } from "lucide-react"
import { cn } from "@/lib/utils"
import { useCardSelection } from "@/hooks/use-card-selection"

export interface SelectionAction {
  label: string
  icon?: LucideIcon
  variant?: "default" | "destructive" | "outline" | "secondary" | "ghost" | "success" | "warning"
  className?: string
  iconOnly?: boolean
  onClick: () => void
}

interface SelectionFloatingBarProps {
  selectedCount?: number
  totalCount?: number
  totalItems?: number
  onSelectAll?: () => void
  onDeselectAll?: () => void
  selection?: {
    selectedIds: (string | number)[]
    deselectAll: () => void
    [key: string]: any
  } | ReturnType<typeof useCardSelection<any>>
  actions?: SelectionAction[]
  children?: React.ReactNode
  className?: string
  label?: string
}

export function SelectionFloatingBar({
  selectedCount,
  totalCount,
  totalItems,
  onSelectAll,
  onDeselectAll,
  selection,
  actions,
  children,
  className,
  label = "Item",
}: SelectionFloatingBarProps) {
  const [modalOpen, setModalOpen] = React.useState(false)

  // Extract count & handlers from selection object if provided, or direct props
  const count = selection ? selection.selectedIds.length : (selectedCount ?? 0)
  const total = totalCount ?? totalItems ?? 0
  const handleDeselect = () => {
    setModalOpen(false)
    if (selection) {
      selection.deselectAll()
    } else if (onDeselectAll) {
      onDeselectAll()
    }
  }
  const handleSelectAll = () => {
    if (onSelectAll) {
      onSelectAll()
    }
  }

  // Strictly only show if count > 0
  if (!count || count <= 0) return null

  const isAllSelected = total > 0 && count >= total

  return (
    <>
      {/* COMPACT FLOATING POPUP TRIGGER PILL */}
      <div className="fixed bottom-[calc(env(safe-area-inset-bottom,0px)+4.75rem)] lg:bottom-6 left-0 right-0 z-40 pointer-events-none flex justify-center px-4 animate-in fade-in slide-in-from-bottom-4 duration-200">
        <div
          className={cn(
            "pointer-events-auto flex items-center gap-2 sm:gap-3 rounded-2xl border border-gray-200 bg-white/95 p-1.5 pl-2.5 sm:pl-3.5 shadow-2xl backdrop-blur-xl dark:border-gray-800 dark:bg-gray-900/95 text-gray-900 dark:text-white max-w-[calc(100vw-24px)]",
            className
          )}
        >
          {/* Selected badge & text */}
          <div className="flex items-center gap-2 shrink-0">
            <span className="flex h-5 min-w-[20px] items-center justify-center rounded-full bg-[#052A4E] dark:bg-brand-500 text-[11px] font-black text-white px-1 leading-none shadow-2xs">
              {count}
            </span>
            <span className="text-xs font-bold text-gray-800 dark:text-gray-200 truncate">
              {label} Dipilih
            </span>
          </div>

          <div className="h-4 w-[1px] bg-gray-200 dark:bg-gray-800 shrink-0" />

          {/* Trigger button opening popup modal */}
          <button
            type="button"
            onClick={() => setModalOpen(true)}
            className="inline-flex items-center gap-1.5 rounded-xl bg-brand-500 hover:bg-brand-600 active:scale-95 px-3 py-1.5 text-xs font-bold text-white shadow-xs transition cursor-pointer whitespace-nowrap"
          >
            <SlidersHorizontal className="h-3.5 w-3.5" />
            <span>Kelola Aksi</span>
            <ChevronRight className="h-3.5 w-3.5 opacity-80" />
          </button>

          {/* Quick deselect close button */}
          <button
            type="button"
            onClick={handleDeselect}
            title="Batal Memilih"
            className="flex h-7 w-7 items-center justify-center rounded-lg text-gray-400 hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-gray-800 dark:hover:text-gray-200 transition cursor-pointer shrink-0"
          >
            <X className="h-4 w-4" />
          </button>
        </div>
      </div>

      {/* POPUP MODAL MULTI-SELECT AKSI MASSAL */}
      {modalOpen && (
        <div
          className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-xs animate-in fade-in"
          onClick={() => setModalOpen(false)}
        >
          <div
            className="w-full max-w-md rounded-2xl border border-gray-200 bg-white p-5 sm:p-6 shadow-2xl dark:border-gray-800 dark:bg-gray-900 space-y-4 max-h-[92vh] overflow-y-auto custom-scrollbar"
            onClick={(e) => e.stopPropagation()}
          >
            {/* Header */}
            <div className="flex items-start justify-between border-b border-gray-100 pb-3 dark:border-gray-800">
              <div className="flex items-center gap-3">
                <div className="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-brand-50 dark:bg-brand-500/10 text-brand-500 font-bold">
                  <Layers className="h-5 w-5" />
                </div>
                <div>
                  <h3 className="text-base font-bold text-gray-900 dark:text-white">
                    Aksi Massal ({count} {label})
                  </h3>
                  <p className="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                    Pilih tindakan untuk {count} data yang ditandai
                  </p>
                </div>
              </div>
              <button
                type="button"
                onClick={() => setModalOpen(false)}
                className="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 transition cursor-pointer"
              >
                <X className="h-5 w-5" />
              </button>
            </div>

            {/* Select All / Deselect Toolbar in Modal */}
            <div className="flex items-center justify-between gap-2 rounded-xl bg-gray-50 dark:bg-gray-800/60 p-2.5 border border-gray-100 dark:border-gray-800">
              <div className="flex items-center gap-2">
                <span className="flex h-5 min-w-[20px] items-center justify-center rounded-full bg-[#052A4E] dark:bg-brand-500 text-[10px] font-black text-white px-1 leading-none shadow-2xs">
                  {count}
                </span>
                <span className="text-xs font-semibold text-gray-700 dark:text-gray-300">
                  {isAllSelected ? "Semua data terpilih" : `${count} dari ${total || count} terpilih`}
                </span>
              </div>
              {total > 0 && (
                <button
                  type="button"
                  onClick={isAllSelected ? handleDeselect : handleSelectAll}
                  className={cn(
                    "inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-bold transition cursor-pointer border",
                    isAllSelected
                      ? "border-brand-300 bg-brand-50 text-brand-600 dark:border-brand-500/40 dark:bg-brand-500/20 dark:text-brand-400"
                      : "border-gray-200 bg-white text-gray-700 hover:bg-gray-100 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300"
                  )}
                >
                  <CheckCheck className="h-3.5 w-3.5" />
                  <span>{isAllSelected ? "Batal Semua" : `Pilih Semua (${total})`}</span>
                </button>
              )}
            </div>

            {/* Actions Grid / List */}
            <div className="space-y-2 pt-1">
              <span className="text-[11px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">
                Pilihan Tindakan
              </span>

              {actions && actions.length > 0 && (
                <div className="grid grid-cols-1 gap-2">
                  {actions.map((act, idx) => {
                    const IconComp = act.icon
                    return (
                      <button
                        key={idx}
                        type="button"
                        onClick={() => {
                          setModalOpen(false)
                          act.onClick()
                        }}
                        className={cn(
                          "w-full flex items-center justify-between p-3 rounded-xl border text-xs font-bold transition-all active:scale-[0.99] cursor-pointer shadow-2xs",
                          act.variant === "destructive" && "border-rose-200 bg-rose-50 text-rose-700 hover:bg-rose-100 dark:border-rose-900/40 dark:bg-rose-950/40 dark:text-rose-300",
                          act.variant === "success" && "border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100 dark:border-emerald-900/40 dark:bg-emerald-950/40 dark:text-emerald-300",
                          act.variant === "warning" && "border-amber-200 bg-amber-50 text-amber-700 hover:bg-amber-100 dark:border-amber-900/40 dark:bg-amber-950/40 dark:text-amber-300",
                          (!act.variant || act.variant === "default" || act.variant === "outline" || act.variant === "secondary") && "border-gray-200 bg-white text-gray-800 hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-800/80 dark:text-gray-200 dark:hover:bg-gray-800",
                          act.className
                        )}
                      >
                        <div className="flex items-center gap-2.5">
                          {IconComp && <IconComp className="h-4 w-4 shrink-0" />}
                          <span>{act.label}</span>
                        </div>
                        <ChevronRight className="h-4 w-4 opacity-40" />
                      </button>
                    )
                  })}
                </div>
              )}

              {/* Children wrapper (for custom batch buttons in Invoices / Customers) */}
              {children && (
                <div
                  className="flex flex-col gap-2 pt-1 [&_button]:w-full [&_button]:justify-center [&_button]:py-2.5 [&_button]:text-xs [&_button]:font-bold [&_button]:rounded-xl"
                  onClick={() => setModalOpen(false)}
                >
                  {children}
                </div>
              )}
            </div>

            {/* Footer */}
            <div className="pt-2 border-t border-gray-100 dark:border-gray-800 flex items-center justify-between">
              <button
                type="button"
                onClick={handleDeselect}
                className="text-xs font-semibold text-gray-500 hover:text-rose-600 dark:text-gray-400 dark:hover:text-rose-400 transition cursor-pointer"
              >
                Batalkan Semua Pilihan
              </button>

              <button
                type="button"
                onClick={() => setModalOpen(false)}
                className="px-4 py-2 rounded-xl bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-xs font-bold text-gray-700 dark:text-gray-200 transition cursor-pointer"
              >
                Tutup
              </button>
            </div>
          </div>
        </div>
      )}
    </>
  )
}
