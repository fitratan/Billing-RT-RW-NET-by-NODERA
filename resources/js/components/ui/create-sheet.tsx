import * as React from "react"
import { motion, AnimatePresence } from "framer-motion"
import { X } from "lucide-react"
import { Button } from "@/components/ui/button"
import { cn } from "@/lib/utils"
import { PriceInput } from "@/components/ui/price-input"

export interface FieldDef {
  name: string
  label: string
  type?: "text" | "number" | "price" | "select" | "textarea" | "date" | "password" | "checkbox" | "switch" | "toggle"
  required?: boolean
  placeholder?: string
  helper?: string
  options?: { value: string; label: string }[]
  cols?: 1 | 2
}

export function CreateSheet({
  open,
  title,
  description,
  onClose,
  onOpenChange,
  onSubmit,
  processing,
  fields = [],
  values = {},
  setValue = () => {},
  errors = {},
  footer,
  children,
}: {
  open: boolean
  title: string
  description?: string
  onClose?: () => void
  onOpenChange?: (open: boolean) => void
  onSubmit?: (e: React.FormEvent) => void
  processing?: boolean
  fields?: FieldDef[]
  values?: Record<string, string | number | boolean>
  setValue?: (name: string, value: string | number | boolean) => void
  errors?: Record<string, string>
  footer?: React.ReactNode
  children?: React.ReactNode
}) {
  const handleClose = () => {
    if (onClose) onClose()
    if (onOpenChange) onOpenChange(false)
  }

  const fieldList = Array.isArray(fields) ? fields : []

  return (
    <AnimatePresence>
      {open ? (
        <div className="fixed inset-0 z-[60] flex items-end justify-center p-0 sm:p-4 lg:items-center">
          <motion.div
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            exit={{ opacity: 0 }}
            transition={{ duration: 0.2 }}
            className="fixed inset-0 z-[60] bg-black/60 backdrop-blur-xs"
            onClick={handleClose}
          />
          <motion.div
            initial={{ y: "100%", opacity: 0.8 }}
            animate={{ y: 0, opacity: 1 }}
            exit={{ y: "100%", opacity: 0 }}
            transition={{ type: "spring", damping: 28, stiffness: 320 }}
            className="relative z-[61] w-full max-h-[90dvh] overflow-y-auto rounded-t-2xl border border-gray-200 bg-white p-5 sm:p-6 shadow-2xl sm:max-h-[88vh] sm:max-w-lg sm:rounded-2xl text-gray-900 dark:border-gray-800 dark:bg-gray-900 dark:text-white"
          >
            <div className="mx-auto mb-3 h-1.5 w-10 rounded-full bg-gray-300 dark:bg-gray-700 lg:hidden" />
            <div className="mb-4 flex items-center justify-between border-b border-gray-100 dark:border-gray-800 pb-3">
              <div>
                <h2 className="font-bold text-base text-gray-900 dark:text-white">{title}</h2>
                {description && <p className="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{description}</p>}
              </div>
              <button
                onClick={handleClose}
                className="flex h-8 w-8 items-center justify-center rounded-xl border border-gray-200 bg-gray-50 text-gray-500 hover:text-gray-700 hover:bg-gray-100 dark:border-gray-800 dark:bg-gray-800 dark:text-gray-400 dark:hover:text-white dark:hover:bg-gray-700 transition cursor-pointer"
                aria-label="Tutup"
              >
                <X className="h-4 w-4" />
              </button>
            </div>

            {children ? (
              <div className="space-y-3.5 pt-1">
                {children}
                {footer}
              </div>
            ) : (
              <form onSubmit={onSubmit} className="space-y-3.5 pt-1">
                {fieldList.map((f) => {
                  const isSwitch = f.type === "checkbox" || f.type === "switch" || f.type === "toggle"
                  const isChecked = Boolean(values[f.name])

                  return (
                    <div key={f.name} className={cn(f.cols === 2 && "grid grid-cols-2 gap-3")}>
                      <div className={cn(f.cols === 2 && "contents")}>
                        {isSwitch ? (
                          <div className="flex items-center justify-between rounded-xl border border-gray-200 bg-gray-50/70 p-3.5 dark:border-gray-800 dark:bg-gray-800/40">
                            <div className="pr-3">
                              <label
                                className="text-xs font-bold text-gray-900 dark:text-white cursor-pointer select-none"
                                onClick={() => setValue(f.name, !isChecked)}
                              >
                                {f.label}
                                {f.required ? <span className="text-rose-500"> *</span> : null}
                              </label>
                              {f.placeholder && <p className="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5">{f.placeholder}</p>}
                            </div>
                            <button
                              type="button"
                              role="switch"
                              aria-checked={isChecked}
                              onClick={() => setValue(f.name, !isChecked)}
                              className={cn(
                                "relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-hidden",
                                isChecked ? "bg-brand-500" : "bg-gray-300 dark:bg-gray-700"
                              )}
                            >
                              <span
                                className={cn(
                                  "pointer-events-none inline-block h-5 w-5 rounded-full bg-white shadow-xs ring-0 transition duration-200 ease-in-out",
                                  isChecked ? "translate-x-5" : "translate-x-0"
                                )}
                              />
                            </button>
                          </div>
                        ) : (
                          <div className="space-y-1.5">
                            <label className="text-xs font-bold text-gray-700 dark:text-gray-300 block">
                              {f.label}
                              {f.required ? <span className="text-rose-500"> *</span> : null}
                            </label>
                            {f.type === "select" ? (
                              <select
                                value={String(values[f.name] ?? "")}
                                onChange={(e) => setValue(f.name, e.target.value)}
                                className="h-10 w-full rounded-xl border border-gray-200 bg-gray-50/50 px-3 text-xs font-medium text-gray-900 placeholder:text-gray-400 focus:border-brand-500 focus:bg-white focus:outline-hidden dark:border-gray-800 dark:bg-gray-900 dark:text-white dark:placeholder:text-gray-500 transition"
                              >
                                <option value="" className="bg-white dark:bg-gray-900 text-gray-400">{f.placeholder ?? "Pilih..."}</option>
                                {f.options?.map((o) => (
                                  <option key={o.value} value={o.value} className="bg-white dark:bg-gray-900 text-gray-900 dark:text-white">
                                    {o.label}
                                  </option>
                                ))}
                              </select>
                            ) : f.type === "textarea" ? (
                              <textarea
                                value={String(values[f.name] ?? "")}
                                onChange={(e) => setValue(f.name, e.target.value)}
                                placeholder={f.placeholder}
                                className="flex min-h-[80px] w-full rounded-xl border border-gray-200 bg-gray-50/50 px-3 py-2 text-xs font-medium text-gray-900 placeholder:text-gray-400 focus:border-brand-500 focus:bg-white focus:outline-hidden dark:border-gray-800 dark:bg-gray-900 dark:text-white dark:placeholder:text-gray-500 transition"
                              />
                            ) : f.type === "price" ? (
                              <PriceInput
                                value={String(values[f.name] ?? "")}
                                onChange={(v) => setValue(f.name, v)}
                                placeholder={f.placeholder}
                              />
                            ) : (
                              <input
                                type={f.type ?? "text"}
                                value={String(values[f.name] ?? "")}
                                onChange={(e) => setValue(f.name, e.target.value)}
                                placeholder={f.placeholder}
                                className="flex h-10 w-full rounded-xl border border-gray-200 bg-gray-50/50 px-3 py-2 text-xs font-medium text-gray-900 placeholder:text-gray-400 focus:border-brand-500 focus:bg-white focus:outline-hidden dark:border-gray-800 dark:bg-gray-900 dark:text-white dark:placeholder:text-gray-500 transition"
                              />
                            )}
                            {f.helper && <p className="text-[11px] text-gray-500 dark:text-gray-400 mt-1 leading-relaxed">{f.helper}</p>}
                            {errors?.[f.name] ? <p className="text-xs text-rose-500 font-medium mt-1">{errors[f.name]}</p> : null}
                          </div>
                        )}
                      </div>
                    </div>
                  )
                })}
                {footer}
                <div className="flex gap-2 pt-3 border-t border-gray-100 dark:border-gray-800">
                  <Button
                    type="button"
                    variant="outline"
                    className="flex-1 text-xs font-semibold rounded-xl border-gray-200 bg-white text-gray-700 hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
                    onClick={handleClose}
                  >
                    Batal
                  </Button>
                  <Button
                    type="submit"
                    className="flex-1 text-xs font-bold text-white rounded-xl bg-brand-500 hover:bg-brand-600 shadow-xs"
                    disabled={processing}
                  >
                    {processing ? "Menyimpan..." : "Simpan"}
                  </Button>
                </div>
              </form>
            )}
          </motion.div>
        </div>
      ) : null}
    </AnimatePresence>
  )
}
