import { useState, useRef, useCallback, useMemo } from "react"
import { triggerHaptic } from "@/lib/haptics"

export interface CardSelectionHandlers {
  onContextMenu: (e: React.MouseEvent) => void
  onTouchStart: (e: React.TouchEvent) => void
  onTouchEnd: (e: React.TouchEvent) => void
  onTouchMove: (e: React.TouchEvent) => void
  onClick: (e: React.MouseEvent, defaultAction?: () => void) => void
}

export interface UseCardSelectionOptions<TItem = any, TId extends string | number = number> {
  items?: TItem[]
  getItemId?: (item: TItem) => TId
  initialSelectedIds?: TId[]
}

export function useCardSelection<T extends string | number = number, TItem = any>(
  options?: UseCardSelectionOptions<TItem, T>
) {
  const [selectedIds, setSelectedIds] = useState<T[]>(() => {
    return Array.isArray(options?.initialSelectedIds) ? options.initialSelectedIds : []
  })
  const [isSelectModeManual, setIsSelectModeManual] = useState(false)
  const timerRef = useRef<ReturnType<typeof setTimeout> | null>(null)
  const isLongPressRef = useRef(false)
  const startTouchRef = useRef<{ x: number; y: number } | null>(null)

  // Safe selectedIds guarantee
  const safeSelectedIds = useMemo(() => {
    return Array.isArray(selectedIds) ? selectedIds : []
  }, [selectedIds])

  const isSelectMode = isSelectModeManual || safeSelectedIds.length > 0
  const hasSelection = safeSelectedIds.length > 0
  const selectedCount = safeSelectedIds.length

  const toggleSelect = useCallback((id: T) => {
    triggerHaptic("selection")
    setSelectedIds((prev) => {
      const arr = Array.isArray(prev) ? prev : []
      const exists = arr.includes(id)
      const next = exists ? arr.filter((i) => i !== id) : [...arr, id]
      if (next.length === 0) {
        setIsSelectModeManual(false)
      } else {
        setIsSelectModeManual(true)
      }
      return next
    })
  }, [])

  const selectAll = useCallback((ids?: T[]) => {
    triggerHaptic("medium")
    if (Array.isArray(ids)) {
      setSelectedIds(ids)
      setIsSelectModeManual(true)
    } else if (options?.items && options?.getItemId) {
      const allIds = options.items.map(options.getItemId)
      setSelectedIds(allIds)
      setIsSelectModeManual(true)
    } else {
      setSelectedIds([])
    }
  }, [options?.items, options?.getItemId])

  const deselectAll = useCallback(() => {
    triggerHaptic("light")
    setSelectedIds([])
    setIsSelectModeManual(false)
  }, [])

  const clearSelection = useCallback(() => {
    deselectAll()
  }, [deselectAll])

  const toggleSelectMode = useCallback(() => {
    triggerHaptic("light")
    setIsSelectModeManual((prev) => {
      if (prev) {
        setSelectedIds([])
        return false
      }
      return true
    })
  }, [])

  const isSelected = useCallback(
    (id: T) => safeSelectedIds.includes(id),
    [safeSelectedIds]
  )

  const isAllSelected = useCallback(
    (ids?: T[]) => {
      let targetIds: T[] = []
      if (Array.isArray(ids)) {
        targetIds = ids
      } else if (options?.items && options?.getItemId) {
        targetIds = options.items.map(options.getItemId)
      }
      return targetIds.length > 0 && targetIds.every((id) => safeSelectedIds.includes(id))
    },
    [safeSelectedIds, options?.items, options?.getItemId]
  )

  const toggleSelectAll = useCallback(
    (ids?: T[]) => {
      triggerHaptic("medium")
      let targetIds: T[] = []
      if (Array.isArray(ids)) {
        targetIds = ids
      } else if (options?.items && options?.getItemId) {
        targetIds = options.items.map(options.getItemId)
      }
      if (targetIds.length === 0) return

      setSelectedIds((prev) => {
        const arr = Array.isArray(prev) ? prev : []
        const allIn = targetIds.length > 0 && targetIds.every((id) => arr.includes(id))
        if (allIn) {
          const next = arr.filter((id) => !targetIds.includes(id))
          if (next.length === 0) setIsSelectModeManual(false)
          return next
        } else {
          setIsSelectModeManual(true)
          const set = new Set([...arr, ...targetIds])
          return Array.from(set)
        }
      })
    },
    [options?.items, options?.getItemId]
  )

  const clearTimer = useCallback(() => {
    if (timerRef.current) {
      clearTimeout(timerRef.current)
      timerRef.current = null
    }
    startTouchRef.current = null
  }, [])

  const getCardHandlers = useCallback(
    (id: T, onDefaultClick?: () => void): CardSelectionHandlers => {
      return {
        // Desktop Right Click & Android Chrome native long press
        onContextMenu: (e: React.MouseEvent) => {
          e.preventDefault()
          e.stopPropagation()
          toggleSelect(id)
        },

        // Mobile Touch Handlers with 25px gesture slop tolerance
        onTouchStart: (e: React.TouchEvent) => {
          if (e.touches && e.touches.length === 1) {
            startTouchRef.current = {
              x: e.touches[0].clientX,
              y: e.touches[0].clientY,
            }
            isLongPressRef.current = false
            if (timerRef.current) clearTimeout(timerRef.current)

            timerRef.current = setTimeout(() => {
              isLongPressRef.current = true
              toggleSelect(id)
              triggerHaptic("heavy")
            }, 300)
          }
        },

        onTouchMove: (e: React.TouchEvent) => {
          if (startTouchRef.current && e.touches && e.touches.length > 0) {
            const dx = Math.abs(e.touches[0].clientX - startTouchRef.current.x)
            const dy = Math.abs(e.touches[0].clientY - startTouchRef.current.y)
            // Cancel only if user actually scrolls (displacement > 25px)
            if (dx > 25 || dy > 25) {
              clearTimer()
            }
          }
        },

        onTouchEnd: (_e: React.TouchEvent) => {
          clearTimer()
        },

        onClick: (e: React.MouseEvent, customDefaultAction?: () => void) => {
          if (isLongPressRef.current) {
            setTimeout(() => {
              isLongPressRef.current = false
            }, 150)
            e.preventDefault()
            e.stopPropagation()
            return
          }

          if (isSelectMode) {
            e.preventDefault()
            e.stopPropagation()
            toggleSelect(id)
            return
          }

          const action = customDefaultAction || onDefaultClick
          if (action) {
            action()
          }
        },
      }
    },
    [isSelectMode, toggleSelect, clearTimer]
  )

  return {
    selectedIds: safeSelectedIds,
    setSelectedIds,
    isSelectMode,
    hasSelection,
    selectedCount,
    toggleSelectMode,
    toggleSelect,
    toggle: toggleSelect,
    selectAll,
    deselectAll,
    clearSelection,
    isSelected,
    isAllSelected,
    toggleSelectAll,
    getCardHandlers,
  }
}
