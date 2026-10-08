import React, { useState, useRef, useEffect } from "react"
import { Calendar, ChevronLeft, ChevronRight, X, Check } from "lucide-react"

interface DateRangePickerProps {
  startDate: string
  endDate: string
  onChange: (start: string, end: string) => void
  onApply?: () => void
  placeholder?: string
  iconOnly?: boolean
}

export default function NoderaPayDateRangePicker({
  startDate,
  endDate,
  onChange,
  onApply,
  placeholder = "Pilih Tanggal Transaksi",
  iconOnly = true,
}: DateRangePickerProps) {
  const [isOpen, setIsOpen] = useState(false)
  const [tempStart, setTempStart] = useState(startDate)
  const [tempEnd, setTempEnd] = useState(endDate)

  // Current calendar viewing month/year
  const getInitialViewDate = () => {
    if (startDate) {
      const d = new Date(startDate)
      if (!isNaN(d.getTime())) return d
    }
    return new Date()
  }

  const [viewDate, setViewDate] = useState<Date>(getInitialViewDate())
  const containerRef = useRef<HTMLDivElement>(null)

  useEffect(() => {
    setTempStart(startDate)
    setTempEnd(endDate)
    if (startDate) {
      const d = new Date(startDate)
      if (!isNaN(d.getTime())) {
        setViewDate(d)
      }
    }
  }, [startDate, endDate])

  // Close on outside click
  useEffect(() => {
    const handleClickOutside = (event: MouseEvent) => {
      if (containerRef.current && !containerRef.current.contains(event.target as Node)) {
        setIsOpen(false)
      }
    }
    if (isOpen) {
      document.addEventListener("mousedown", handleClickOutside)
    }
    return () => {
      document.removeEventListener("mousedown", handleClickOutside)
    }
  }, [isOpen])

  const formatDisplayDate = (isoStr: string) => {
    if (!isoStr) return ""
    const d = new Date(isoStr)
    if (isNaN(d.getTime())) return isoStr
    return d.toLocaleDateString("id-ID", {
      day: "numeric",
      month: "short",
      year: "numeric",
    })
  }

  const handlePrevMonth = () => {
    setViewDate(new Date(viewDate.getFullYear(), viewDate.getMonth() - 1, 1))
  }

  const handleNextMonth = () => {
    setViewDate(new Date(viewDate.getFullYear(), viewDate.getMonth() + 1, 1))
  }

  const monthNames = [
    "Januari", "Februari", "Maret", "April", "Mei", "Juni",
    "Juli", "Agustus", "September", "Oktober", "November", "Desember",
  ]

  const daysOfWeek = ["Min", "Sen", "Sel", "Rab", "Kam", "Jum", "Sab"]

  // Build calendar matrix
  const year = viewDate.getFullYear()
  const month = viewDate.getMonth()
  const firstDayOfMonth = new Date(year, month, 1).getDay()
  const daysInMonth = new Date(year, month + 1, 0).getDate()

  const handleDateClick = (day: number) => {
    const yyyy = year
    const mm = String(month + 1).padStart(2, "0")
    const dd = String(day).padStart(2, "0")
    const clickedIso = `${yyyy}-${mm}-${dd}`

    if (!tempStart || (tempStart && tempEnd)) {
      setTempStart(clickedIso)
      setTempEnd("")
    } else if (tempStart && !tempEnd) {
      if (new Date(clickedIso) < new Date(tempStart)) {
        setTempEnd(tempStart)
        setTempStart(clickedIso)
      } else {
        setTempEnd(clickedIso)
      }
    }
  }

  const isSelected = (day: number) => {
    const yyyy = year
    const mm = String(month + 1).padStart(2, "0")
    const dd = String(day).padStart(2, "0")
    const curIso = `${yyyy}-${mm}-${dd}`
    return curIso === tempStart || curIso === tempEnd
  }

  const isInRange = (day: number) => {
    if (!tempStart || !tempEnd) return false
    const yyyy = year
    const mm = String(month + 1).padStart(2, "0")
    const dd = String(day).padStart(2, "0")
    const curIso = `${yyyy}-${mm}-${dd}`
    return curIso > tempStart && curIso < tempEnd
  }

  const handleApply = () => {
    onChange(tempStart, tempEnd)
    setIsOpen(false)
    if (onApply) onApply()
  }

  const handleClear = (e?: React.MouseEvent) => {
    if (e) e.stopPropagation()
    setTempStart("")
    setTempEnd("")
    onChange("", "")
    setIsOpen(false)
    if (onApply) onApply()
  }

  const hasFilter = Boolean(startDate || endDate)
  const label =
    startDate && endDate
      ? `${formatDisplayDate(startDate)} – ${formatDisplayDate(endDate)}`
      : startDate
      ? `Mulai ${formatDisplayDate(startDate)}`
      : placeholder

  return (
    <div className="relative inline-block" ref={containerRef}>
      {/* Trigger Button */}
      {iconOnly ? (
        <button
          type="button"
          onClick={() => setIsOpen(!isOpen)}
          className={`flex items-center justify-center h-10 w-10 rounded-xl border transition-all cursor-pointer relative shadow-xs ${
            hasFilter
              ? "bg-brand-500/15 border-brand-500 text-brand-500 dark:text-brand-400"
              : "bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300 border-gray-200 dark:border-gray-700"
          }`}
          title={hasFilter ? `Filter Tanggal: ${label}` : "Pilih Rentang Tanggal"}
        >
          <Calendar className="h-4 w-4" />
          {hasFilter && (
            <span className="absolute -top-1 -right-1 h-2.5 w-2.5 rounded-full bg-[#465FFF] ring-2 ring-white dark:ring-gray-900" />
          )}
        </button>
      ) : (
        <button
          type="button"
          onClick={() => setIsOpen(!isOpen)}
          className="w-full flex items-center justify-between gap-2 px-3.5 py-2.5 rounded-xl border border-gray-200 bg-white hover:border-brand-500 focus:border-brand-500 dark:border-gray-800 dark:bg-gray-900 text-xs font-medium text-gray-900 dark:text-white transition-all cursor-pointer shadow-xs"
        >
          <div className="flex items-center gap-2 truncate">
            <Calendar className="h-4 w-4 text-brand-500 dark:text-brand-400 shrink-0" />
            <span
              className={`truncate ${
                !hasFilter ? "text-gray-400 font-normal" : "font-bold text-gray-900 dark:text-white"
              }`}
            >
              {label}
            </span>
          </div>
          {hasFilter && (
            <button
              type="button"
              onClick={handleClear}
              className="p-1 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition-colors"
              title="Hapus Filter Tanggal"
            >
              <X className="h-3.5 w-3.5" />
            </button>
          )}
        </button>
      )}

      {/* Calendar Popover / Modal */}
      {isOpen && (
        <>
          {/* Mobile Modal (Centered with backdrop) */}
          <div
            className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs sm:hidden"
            onClick={() => setIsOpen(false)}
          >
            <div
              className="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-800 shadow-2xl p-4 w-full max-w-[320px] space-y-4 animate-in fade-in zoom-in-95 duration-150"
              onClick={(e) => e.stopPropagation()}
            >
              {/* Month Header Navigation */}
              <div className="flex items-center justify-between pb-2 border-b border-gray-100 dark:border-gray-800">
                <button
                  type="button"
                  onClick={handlePrevMonth}
                  className="p-1.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 hover:bg-gray-100 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300 transition-colors cursor-pointer"
                  title="Bulan Sebelumnya"
                >
                  <ChevronLeft className="h-4 w-4" />
                </button>
                <div className="text-xs font-bold text-gray-900 dark:text-white select-none">
                  {monthNames[month]} {year}
                </div>
                <button
                  type="button"
                  onClick={handleNextMonth}
                  className="p-1.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 hover:bg-gray-100 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300 transition-colors cursor-pointer"
                  title="Bulan Berikutnya"
                >
                  <ChevronRight className="h-4 w-4" />
                </button>
              </div>

              {/* Days Grid */}
              <div>
                <div className="grid grid-cols-7 gap-1 text-center mb-1">
                  {daysOfWeek.map((d) => (
                    <div key={d} className="text-[10px] font-bold text-gray-400 dark:text-gray-500 py-1">
                      {d}
                    </div>
                  ))}
                </div>

                <div className="grid grid-cols-7 gap-1">
                  {Array.from({ length: firstDayOfMonth }).map((_, i) => (
                    <div key={`empty-m-${i}`} className="h-8 w-8" />
                  ))}

                  {Array.from({ length: daysInMonth }).map((_, i) => {
                    const day = i + 1
                    const sel = isSelected(day)
                    const inRange = isInRange(day)

                    return (
                      <button
                        key={`day-m-${day}`}
                        type="button"
                        onClick={() => handleDateClick(day)}
                        className={`h-8 w-8 mx-auto rounded-xl text-xs font-bold transition-all flex items-center justify-center cursor-pointer ${
                          sel
                            ? "bg-[#465FFF] text-white shadow-xs font-extrabold"
                            : inRange
                            ? "bg-brand-500/15 text-[#465FFF] dark:text-brand-400 rounded-md font-semibold"
                            : "text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800"
                        }`}
                      >
                        {day}
                      </button>
                    )
                  })}
                </div>
              </div>

              {/* Date Selection Preview & Actions */}
              <div className="pt-3 border-t border-gray-100 dark:border-gray-800 space-y-2.5">
                <div className="flex items-center justify-between text-[11px]">
                  <div className="text-gray-500 dark:text-gray-400 font-medium truncate">
                    {tempStart ? (
                      <span className="text-gray-900 dark:text-white font-semibold font-mono">
                        {formatDisplayDate(tempStart)} {tempEnd ? `– ${formatDisplayDate(tempEnd)}` : ""}
                      </span>
                    ) : (
                      <span className="text-gray-400">Pilih rentang tanggal</span>
                    )}
                  </div>
                  {(tempStart || tempEnd) && (
                    <button
                      type="button"
                      onClick={() => {
                        setTempStart("")
                        setTempEnd("")
                      }}
                      className="text-[10px] font-bold text-rose-600 hover:underline cursor-pointer"
                    >
                      Reset
                    </button>
                  )}
                </div>

                <div className="flex items-center justify-end gap-1.5">
                  <button
                    type="button"
                    onClick={() => setIsOpen(false)}
                    className="px-3 py-1.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 hover:bg-gray-100 dark:bg-gray-800 dark:hover:bg-gray-700 text-[11px] font-bold text-gray-700 dark:text-gray-300 transition-colors cursor-pointer"
                  >
                    Batal
                  </button>
                  <button
                    type="button"
                    onClick={handleApply}
                    className="px-3.5 py-1.5 rounded-xl bg-[#465FFF] hover:bg-[#3641F5] text-white text-[11px] font-bold shadow-xs transition-colors flex items-center gap-1 cursor-pointer"
                  >
                    <Check className="h-3 w-3" />
                    <span>Terapkan</span>
                  </button>
                </div>
              </div>
            </div>
          </div>

          {/* Desktop Dropdown Popover */}
          <div className="hidden sm:block absolute right-0 top-full mt-2 z-50 bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-800 shadow-2xl p-5 w-[320px] space-y-4 animate-in fade-in zoom-in-95 duration-150">
            {/* Month Header Navigation */}
            <div className="flex items-center justify-between pb-2 border-b border-gray-100 dark:border-gray-800">
              <button
                type="button"
                onClick={handlePrevMonth}
                className="p-1.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 hover:bg-gray-100 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300 transition-colors cursor-pointer"
                title="Bulan Sebelumnya"
              >
                <ChevronLeft className="h-4 w-4" />
              </button>
              <div className="text-xs font-bold text-gray-900 dark:text-white select-none">
                {monthNames[month]} {year}
              </div>
              <button
                type="button"
                onClick={handleNextMonth}
                className="p-1.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 hover:bg-gray-100 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300 transition-colors cursor-pointer"
                title="Bulan Berikutnya"
              >
                <ChevronRight className="h-4 w-4" />
              </button>
            </div>

            {/* Days Grid */}
            <div>
              <div className="grid grid-cols-7 gap-1 text-center mb-1">
                {daysOfWeek.map((d) => (
                  <div key={d} className="text-[10px] font-bold text-gray-400 dark:text-gray-500 py-1">
                    {d}
                  </div>
                ))}
              </div>

              <div className="grid grid-cols-7 gap-1">
                {Array.from({ length: firstDayOfMonth }).map((_, i) => (
                  <div key={`empty-d-${i}`} className="h-8 w-8" />
                ))}

                {Array.from({ length: daysInMonth }).map((_, i) => {
                  const day = i + 1
                  const sel = isSelected(day)
                  const inRange = isInRange(day)

                  return (
                    <button
                      key={`day-d-${day}`}
                      type="button"
                      onClick={() => handleDateClick(day)}
                      className={`h-8 w-8 mx-auto rounded-xl text-xs font-bold transition-all flex items-center justify-center cursor-pointer ${
                        sel
                          ? "bg-[#465FFF] text-white shadow-xs font-extrabold"
                          : inRange
                          ? "bg-brand-500/15 text-[#465FFF] dark:text-brand-400 rounded-md font-semibold"
                          : "text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800"
                      }`}
                    >
                      {day}
                    </button>
                  )
                })}
              </div>
            </div>

            {/* Date Selection Preview & Actions */}
            <div className="pt-3 border-t border-gray-100 dark:border-gray-800 space-y-2.5">
              <div className="flex items-center justify-between text-[11px]">
                <div className="text-gray-500 dark:text-gray-400 font-medium truncate">
                  {tempStart ? (
                    <span className="text-gray-900 dark:text-white font-semibold font-mono">
                      {formatDisplayDate(tempStart)} {tempEnd ? `– ${formatDisplayDate(tempEnd)}` : ""}
                    </span>
                  ) : (
                    <span className="text-gray-400">Pilih rentang tanggal</span>
                  )}
                </div>
                {(tempStart || tempEnd) && (
                  <button
                    type="button"
                    onClick={() => {
                      setTempStart("")
                      setTempEnd("")
                    }}
                    className="text-[10px] font-bold text-rose-600 hover:underline cursor-pointer"
                  >
                    Reset
                  </button>
                )}
              </div>

              <div className="flex items-center justify-end gap-1.5">
                <button
                  type="button"
                  onClick={() => setIsOpen(false)}
                  className="px-3 py-1.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 hover:bg-gray-100 dark:bg-gray-800 dark:hover:bg-gray-700 text-[11px] font-bold text-gray-700 dark:text-gray-300 transition-colors cursor-pointer"
                >
                  Batal
                </button>
                <button
                  type="button"
                  onClick={handleApply}
                  className="px-3.5 py-1.5 rounded-xl bg-[#465FFF] hover:bg-[#3641F5] text-white text-[11px] font-bold shadow-xs transition-colors flex items-center gap-1 cursor-pointer"
                >
                  <Check className="h-3 w-3" />
                  <span>Terapkan</span>
                </button>
              </div>
            </div>
          </div>
        </>
      )}
    </div>
  )
}
