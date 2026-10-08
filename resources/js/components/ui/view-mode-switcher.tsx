import * as React from "react"
import { LayoutGrid, List } from "lucide-react"
import { cn } from "@/lib/utils"

export type ViewMode = "table" | "grid"

export interface ViewModeSwitcherProps {
  value?: ViewMode
  defaultValue?: ViewMode
  onChange?: (mode: ViewMode) => void
  onViewChange?: (mode: ViewMode) => void
  storageKey?: string
  showLabels?: boolean
  tableLabel?: string
  gridLabel?: string
  size?: "xs" | "sm" | "md"
  className?: string
  ariaLabel?: string
}

export const ViewModeSwitcher: React.FC<ViewModeSwitcherProps> = ({
  value,
  defaultValue = "table",
  onChange,
  onViewChange,
  storageKey = "nodera_view_mode",
  showLabels = false,
  tableLabel = "Tabel",
  gridLabel = "Grid",
  size = "sm",
  className,
  ariaLabel = "Pilih tampilan data",
}) => {
  const [internalMode, setInternalMode] = React.useState<ViewMode>(() => {
    if (typeof window !== "undefined" && storageKey) {
      const saved = localStorage.getItem(storageKey) as ViewMode | null
      if (saved === "table" || saved === "grid") {
        return saved
      }
    }
    return defaultValue
  })

  // Synchronize with external value if controlled
  const isControlled = value !== undefined
  const currentMode = isControlled ? value : internalMode

  // Notify parent on initial mount if saved in localStorage
  React.useEffect(() => {
    if (typeof window !== "undefined" && storageKey && !isControlled) {
      const saved = localStorage.getItem(storageKey) as ViewMode | null
      if ((saved === "table" || saved === "grid") && saved !== defaultValue) {
        setInternalMode(saved)
        onChange?.(saved)
        onViewChange?.(saved)
      }
    }
  }, [storageKey])

  const handleSelect = (mode: ViewMode) => {
    if (mode === currentMode) return

    if (!isControlled) {
      setInternalMode(mode)
    }

    if (typeof window !== "undefined" && storageKey) {
      try {
        localStorage.setItem(storageKey, mode)
      } catch {
        // Ignore localStorage write quota errors
      }
    }

    onChange?.(mode)
    onViewChange?.(mode)
  }

  const sizeStyles = {
    xs: {
      container: "p-0.5 rounded-lg",
      button: "px-1.5 py-1 text-[11px] rounded-md gap-1",
      icon: "h-3.5 w-3.5",
    },
    sm: {
      container: "p-1 rounded-xl",
      button: "px-2.5 py-1 text-xs rounded-lg gap-1.5",
      icon: "h-4 w-4",
    },
    md: {
      container: "p-1.5 rounded-xl",
      button: "px-3 py-1.5 text-sm rounded-lg gap-2",
      icon: "h-4.5 w-4.5",
    },
  }[size]

  return (
    <div
      role="group"
      aria-label={ariaLabel}
      className={cn(
        "inline-flex items-center bg-gray-100 dark:bg-gray-800/90 border border-gray-200 dark:border-gray-700/60 shadow-xs select-none transition-colors",
        sizeStyles.container,
        className
      )}
    >
      <button
        type="button"
        aria-label="Tampilan Tabel"
        aria-pressed={currentMode === "table"}
        title={tableLabel}
        onClick={() => handleSelect("table")}
        className={cn(
          "inline-flex items-center justify-center font-medium transition-all duration-150 cursor-pointer focus:outline-hidden",
          sizeStyles.button,
          currentMode === "table"
            ? "bg-white dark:bg-gray-700 text-brand-600 dark:text-brand-400 font-bold shadow-xs"
            : "text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white"
        )}
      >
        <List className={cn(sizeStyles.icon, "shrink-0")} />
        {showLabels && <span>{tableLabel}</span>}
      </button>

      <button
        type="button"
        aria-label="Tampilan Grid"
        aria-pressed={currentMode === "grid"}
        title={gridLabel}
        onClick={() => handleSelect("grid")}
        className={cn(
          "inline-flex items-center justify-center font-medium transition-all duration-150 cursor-pointer focus:outline-hidden",
          sizeStyles.button,
          currentMode === "grid"
            ? "bg-white dark:bg-gray-700 text-brand-600 dark:text-brand-400 font-bold shadow-xs"
            : "text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white"
        )}
      >
        <LayoutGrid className={cn(sizeStyles.icon, "shrink-0")} />
        {showLabels && <span>{gridLabel}</span>}
      </button>
    </div>
  )
}

export default ViewModeSwitcher
