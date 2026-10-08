import { AppLayout } from "@/components/layout/app-layout"
import Chart from "react-apexcharts"
import type { ApexOptions } from "apexcharts"
import {
  Receipt,
  Search,
  Printer,
  Check,
  CheckCheck,
  Trash2,
  CalendarClock,
  X,
  Wallet,
  Send,
  RefreshCw,
  ChevronLeft,
  ChevronRight,
  CheckSquare,
  CreditCard,
  TrendingUp,
  AlertTriangle,
  User,
  Users,
  MapPin,
  CheckCircle2,
  DollarSign,
  QrCode,
  SlidersHorizontal,
  ChevronDown,
  ChevronUp,
  Phone,
  ArrowUpRight,
  Percent,
  Plus,
  MessageCircle,
  Hash,
  Calendar,
  RotateCcw,
  Radio,
  Boxes,
  Settings,
  Copy,
  Power,
  CalendarPlus,
  CalendarDays,
  Loader2,
  LayoutGrid,
  Table,
  List,
  Eye,
  Building2,
  Sparkles,
  Filter,
  ShieldAlert,
  Activity,
} from "lucide-react"
import { useState, useMemo, useEffect, useRef, useCallback } from "react"
import { router, Link, usePage, Head } from "@inertiajs/react"
import { cn, formatIDR } from "@/lib/utils"
import { adminSidebarItems, adminNavItems, adminBrand } from "@/lib/admin-nav"
import { useCardSelection } from "@/hooks/use-card-selection"
import { SelectionFloatingBar } from "@/components/ui/selection-floating-bar"
import { SyncOverlay } from "@/components/ui/sync-overlay"
import { EmptyState } from "@/components/ui/empty-state"
import { Checkbox } from "@/components/tailadmin/Checkbox"
import { Switch } from "@/components/tailadmin/Switch"
import { ViewModeSwitcher, type ViewMode } from "@/components/ui/view-mode-switcher"

// ---------------------------------------------------------------------------
// TYPES & INTERFACES
// ---------------------------------------------------------------------------

export interface BreakdownPeriod {
  period: string
  amount: number
  label?: string
}

export interface Invoice {
  id: number
  invoice_number: string
  customer_id: number
  customer_name: string
  customer_phone?: string
  customer_code?: string
  package_name?: string
  router_name?: string
  amount: number
  paid: boolean
  status: string
  period?: string
  due_date?: string
  paid_at?: string
  created_at?: string
  payment_channel?: string
  periods_breakdown?: string | null
  admin_notes?: string
  notes?: string
  processed_by?: string
  processed_by_name?: string
  collector_id?: number
  collector_name?: string
  assigned_collector_name?: string
  customer_is_active?: boolean
}

export interface CustomerOption {
  id: number
  name: string
  customer_code?: string
  phone?: string
  package_name?: string
  package_price?: number
  router_name?: string
  is_active?: boolean
  address?: string
}

export interface CollectorOption {
  id: number
  name: string
  username?: string
  role?: string
}

export interface CollectorStat {
  collector_id?: number
  collector_name: string
  username?: string
  paid_count: number
  paid_amount: number
  pending_count: number
  pending_amount: number
  total_invoices: number
  total_amount: number
}

export interface AutoInvoiceConfig {
  auto_generate_invoice?: boolean
  invoice_generate_day?: number
  auto_wa_invoice_created?: boolean
  auto_wa_reminder?: boolean
  wa_reminder_days?: number[]
  active_customers_count?: number
  current_month_invoices_count?: number
}

interface InvoicesProps {
  invoices: Invoice[]
  customers?: CustomerOption[]
  selectedPeriod?: string
  selectedDate?: string
  selectedDay?: number | string
  initialDay?: number | string
  availablePeriods?: string[]
  dateInvoiceCounts?: Record<string, { total: number; pending: number; paid: number }>
  canResetInvoices?: boolean
  collectors?: CollectorOption[]
  selectedCollectorId?: number | null
  collectorStats?: CollectorStat[]
  totalCollectorAmount?: number
  isStaffRestricted?: boolean
  autoInvoiceConfig?: AutoInvoiceConfig
}

// ---------------------------------------------------------------------------
// HELPER FUNCTIONS
// ---------------------------------------------------------------------------

const parseBreakdown = (raw: string | null | undefined): BreakdownPeriod[] => {
  if (!raw) return []
  try {
    const parsed = JSON.parse(raw)
    if (Array.isArray(parsed)) {
      return parsed
        .map((p: any) => ({
          period: String(p.period || "").trim(),
          amount: Number(p.amount) || 0,
          label: p.label ? String(p.label) : undefined,
        }))
        .filter((p) => p.period.length > 0)
    }
  } catch {
    // fallback
  }
  return []
}

const MONTH_NAMES = [
  "Januari", "Februari", "Maret", "April", "Mei", "Juni",
  "Juli", "Agustus", "September", "Oktober", "November", "Desember"
]

const MONTH_NAMES_SHORT = [
  "Jan", "Feb", "Mar", "Apr", "Mei", "Jun",
  "Jul", "Agu", "Sep", "Okt", "Nov", "Des"
]

const periodLabel = (periodStr?: string | null): string => {
  if (!periodStr) return "-"
  const parts = periodStr.split("-")
  if (parts.length >= 2) {
    const y = parts[0]
    const m = parseInt(parts[1], 10)
    if (m >= 1 && m <= 12) {
      return `${MONTH_NAMES[m - 1]} ${y}`
    }
  }
  return periodStr
}

const periodLabelShort = (periodStr?: string | null): string => {
  if (!periodStr) return "-"
  const parts = periodStr.split("-")
  if (parts.length >= 2) {
    const y = parts[0]
    const m = parseInt(parts[1], 10)
    if (m >= 1 && m <= 12) {
      return `${MONTH_NAMES_SHORT[m - 1]} ${y}`
    }
  }
  return periodStr
}

const displayPeriod = (inv: Invoice): string => {
  if (inv.period) return inv.period
  if (inv.due_date) return inv.due_date.slice(0, 7)
  return ""
}

const formatDueDate = (dateStr?: string): string => {
  if (!dateStr) return "-"
  try {
    const d = new Date(dateStr)
    if (isNaN(d.getTime())) return dateStr
    return d.toLocaleDateString("id-ID", { day: "numeric", month: "short", year: "numeric" })
  } catch {
    return dateStr
  }
}

// ---------------------------------------------------------------------------
// MAIN COMPONENT
// ---------------------------------------------------------------------------

export default function Invoices({
  invoices = [],
  customers = [],
  selectedPeriod,
  selectedDate,
  selectedDay,
  initialDay,
  availablePeriods = [],
  dateInvoiceCounts = {},
  canResetInvoices = false,
  collectors = [],
  selectedCollectorId = null,
  collectorStats = [],
  totalCollectorAmount = 0,
  isStaffRestricted = false,
  autoInvoiceConfig = {},
}: InvoicesProps) {
  const { auth, flash } = usePage().props as any
  const user = auth?.user

  // ---------------------------------------------------------------------------
  // STATE: VIEW MODE & FILTERS
  // ---------------------------------------------------------------------------
  const [viewMode, setViewMode] = useState<"table" | "grid">(() => {
    if (typeof window !== "undefined") {
      const saved = localStorage.getItem("nodera_invoices_view_mode")
      if (saved === "table" || saved === "grid") return saved
    }
    return "table"
  })

  const handleViewModeChange = (mode: "table" | "grid") => {
    setViewMode(mode)
    if (typeof window !== "undefined") {
      localStorage.setItem("nodera_invoices_view_mode", mode)
    }
  }

  const [search, setSearch] = useState("")
  const [managingInvoice, setManagingInvoice] = useState<Invoice | null>(null)
  const [modalSelectedPeriods, setModalSelectedPeriods] = useState<string[]>([])
  const [status, setStatus] = useState<"all" | "pending" | "paid" | "overdue" | "cancelled">("all")
  const [channelFilter, setChannelFilter] = useState("all")
  const [collectorFilter, setCollectorFilter] = useState<string>(
    selectedCollectorId ? String(selectedCollectorId) : "all"
  )
  const [expandedInvoiceId, setExpandedInvoiceId] = useState<number | null>(null)
  const [copiedInvoiceNumber, setCopiedInvoiceNumber] = useState<string | null>(null)

  const topScrollRef = useRef<HTMLDivElement>(null)
  const tableScrollRef = useRef<HTMLDivElement>(null)
  const [tableScrollWidth, setTableScrollWidth] = useState(1100)
  const isSyncingTop = useRef(false)
  const isSyncingTable = useRef(false)

  const handleTopScroll = () => {
    if (isSyncingTop.current) {
      isSyncingTop.current = false
      return
    }
    if (topScrollRef.current && tableScrollRef.current) {
      isSyncingTable.current = true
      tableScrollRef.current.scrollLeft = topScrollRef.current.scrollLeft
    }
  }

  const handleTableScroll = () => {
    if (isSyncingTable.current) {
      isSyncingTable.current = false
      return
    }
    if (topScrollRef.current && tableScrollRef.current) {
      isSyncingTop.current = true
      topScrollRef.current.scrollLeft = tableScrollRef.current.scrollLeft
    }
  }

  useEffect(() => {
    if (tableScrollRef.current) {
      tableScrollRef.current.scrollLeft = 0
    }
    if (topScrollRef.current) {
      topScrollRef.current.scrollLeft = 0
    }
  }, [search, status, channelFilter, collectorFilter, selectedPeriod])

  // Modals state
  const [payConfirmModal, setPayConfirmModal] = useState<{
    open: boolean
    invoice: Invoice | null
    selectedPeriods: string[]
    allPeriods: BreakdownPeriod[]
    amountToPay: number
  }>({
    open: false,
    invoice: null,
    selectedPeriods: [],
    allPeriods: [],
    amountToPay: 0,
  })

  const [advanceModalOpen, setAdvanceModalOpen] = useState(false)
  const [resetModalOpen, setResetModalOpen] = useState(false)
  const [calendarOpen, setCalendarOpen] = useState(false)
  const [autoInvoiceModalOpen, setAutoInvoiceModalOpen] = useState(false)
  const [filterModalOpen, setFilterModalOpen] = useState(false)

  // Batch action confirmation modals
  const [batchPayConfirmOpen, setBatchPayConfirmOpen] = useState(false)
  const [batchCancelConfirmOpen, setBatchCancelConfirmOpen] = useState(false)
  const [batchDeleteConfirmOpen, setBatchDeleteConfirmOpen] = useState(false)
  const [batchUnisolateConfirmOpen, setBatchUnisolateConfirmOpen] = useState(false)
  const [waBatchConfirmOpen, setWaBatchConfirmOpen] = useState(false)

  // WhatsApp Single Modal
  const [waModalInvoice, setWaModalInvoice] = useState<Invoice | null>(null)
  const [waSending, setWaSending] = useState(false)
  const [waStatusMsg, setWaStatusMsg] = useState<{ type: "success" | "error"; text: string } | null>(null)

  // Multi-period checkboxes per invoice
  const [selectedPeriodsMap, setSelectedPeriodsMap] = useState<Record<number, string[]>>({})

  // Form states for advance payment
  const [advCustomerId, setAdvCustomerId] = useState<number | "">("")
  const [advCustomerSearch, setAdvCustomerSearch] = useState("")
  const [advCustomerDropdownOpen, setAdvCustomerDropdownOpen] = useState(false)
  const [advMonthsCount, setAdvMonthsCount] = useState(1)
  const [advStartPeriod, setAdvStartPeriod] = useState(() => {
    const d = new Date()
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, "0")}`
  })
  const [advAmountPerMonth, setAdvAmountPerMonth] = useState(0)
  const [advNotes, setAdvNotes] = useState("")
  const [advMarkPaid, setAdvMarkPaid] = useState(true)

  // Form states for auto-invoice settings
  const [autoGenInvoice, setAutoGenInvoice] = useState(
    autoInvoiceConfig?.auto_generate_invoice ?? true
  )
  const [autoGenDay, setAutoGenDay] = useState(
    autoInvoiceConfig?.invoice_generate_day ?? 1
  )
  const [autoWaCreated, setAutoWaCreated] = useState(
    autoInvoiceConfig?.auto_wa_invoice_created ?? true
  )
  const [autoWaReminder, setAutoWaReminder] = useState(
    autoInvoiceConfig?.auto_wa_reminder ?? true
  )
  const [reminderDays, setReminderDays] = useState<number[]>(
    autoInvoiceConfig?.wa_reminder_days ?? [3, 1]
  )
  const [savingAutoConfig, setSavingAutoConfig] = useState(false)

  // Keep state synced with backend autoInvoiceConfig props
  useEffect(() => {
    if (autoInvoiceConfig && typeof autoInvoiceConfig.auto_generate_invoice !== "undefined") {
      setAutoGenInvoice(Boolean(autoInvoiceConfig.auto_generate_invoice))
      setAutoGenDay(Number(autoInvoiceConfig.invoice_generate_day ?? 1))
      setAutoWaCreated(Boolean(autoInvoiceConfig.auto_wa_invoice_created))
      setAutoWaReminder(Boolean(autoInvoiceConfig.auto_wa_reminder))
      setReminderDays(autoInvoiceConfig.wa_reminder_days ?? [3, 1])
    }
  }, [autoInvoiceConfig])

  // Reset invoices modal state
  const [resetPeriod, setResetPeriod] = useState(selectedPeriod || "")
  const [resetScope, setResetScope] = useState<"pending" | "all">("pending")

  // Calendar modal year/month/date view state
  const [calendarYear, setCalendarYear] = useState(() => {
    if (selectedPeriod) {
      const parts = selectedPeriod.split("-")
      if (parts.length >= 1) return parseInt(parts[0], 10)
    }
    return new Date().getFullYear()
  })
  const [calendarTab, setCalendarTab] = useState<"month" | "date">("month")

  // ---------------------------------------------------------------------------
  // CONSOLIDATE MULTI-PERIOD INVOICES FOR THE SAME CUSTOMER
  // ---------------------------------------------------------------------------
  const consolidatedInvoices = useMemo(() => {
    const pendingMap = new Map<string, Invoice>()
    const paidList: Invoice[] = []

    for (const inv of invoices) {
      const isPaid = inv.paid || inv.status === "paid"

      if (isPaid) {
        paidList.push(inv)
        continue
      }

      // Unique customer identifier
      const custIdentifier = inv.customer_id
        ? `cid_${inv.customer_id}`
        : inv.customer_code && String(inv.customer_code).trim()
        ? `code_${String(inv.customer_code).trim().toLowerCase()}`
        : inv.customer_name && String(inv.customer_name).trim()
        ? `name_${String(inv.customer_name).trim().toLowerCase()}`
        : `invid_${inv.id}`

      const custKey = custIdentifier

      const rawBd = parseBreakdown(inv.periods_breakdown)
      const currentPeriods: BreakdownPeriod[] =
        rawBd.length > 0
          ? rawBd
          : [
              {
                period: inv.period ?? (inv.due_date ? inv.due_date.slice(0, 7) : ""),
                amount: Number(inv.amount || 0),
                label: periodLabel(displayPeriod(inv)),
              },
            ]

      if (!pendingMap.has(custKey)) {
        pendingMap.set(custKey, {
          ...inv,
          periods_breakdown: JSON.stringify(currentPeriods),
          amount:
            currentPeriods.reduce((sum, p) => sum + p.amount, 0) ||
            Number(inv.amount || 0),
        })
      } else {
        const existing = pendingMap.get(custKey)!
        const existingBd = parseBreakdown(existing.periods_breakdown)

        const periodMap = new Map<string, BreakdownPeriod>()
        for (const p of existingBd) {
          if (p.period) periodMap.set(p.period, p)
        }
        for (const p of currentPeriods) {
          if (p.period) periodMap.set(p.period, p)
        }

        const mergedPeriods = Array.from(periodMap.values())
        mergedPeriods.sort((a, b) => (a.period || "").localeCompare(b.period || ""))
        const totalAmount = mergedPeriods.reduce((sum, p) => sum + p.amount, 0)
        const latestPeriod =
          existing.period && inv.period
            ? existing.period > inv.period
              ? existing.period
              : inv.period
            : existing.period || inv.period

        pendingMap.set(custKey, {
          ...existing,
          id: Math.max(existing.id, inv.id),
          amount: totalAmount,
          period: latestPeriod,
          due_date:
            existing.due_date && inv.due_date
              ? existing.due_date > inv.due_date
                ? existing.due_date
                : inv.due_date
              : existing.due_date || inv.due_date,
          periods_breakdown: JSON.stringify(mergedPeriods),
        })
      }
    }

    return [...Array.from(pendingMap.values()), ...paidList]
  }, [invoices])

  // ---------------------------------------------------------------------------
  // FILTERING
  // ---------------------------------------------------------------------------
  const filteredInvoices = useMemo(() => {
    return consolidatedInvoices.filter((inv) => {
      const isPaid = inv.paid || inv.status === "paid"
      const isOverdue =
        !isPaid &&
        inv.status !== "cancelled" &&
        inv.status !== "dibatalkan" &&
        inv.due_date &&
        new Date(inv.due_date).getTime() < new Date().setHours(0, 0, 0, 0)
      const isCancelled = inv.status === "cancelled" || inv.status === "dibatalkan"

      // Status filter
      if (status === "pending" && (isPaid || isCancelled)) return false
      if (status === "paid" && !isPaid) return false
      if (status === "overdue" && !isOverdue) return false
      if (status === "cancelled" && !isCancelled) return false

      // Channel filter
      if (channelFilter !== "all") {
        if (channelFilter === "manual") {
          if (inv.payment_channel && inv.payment_channel !== "manual") return false
        } else if (inv.payment_channel !== channelFilter) {
          return false
        }
      }

      // Collector filter
      if (collectorFilter !== "all") {
        if (collectorFilter === "unassigned") {
          if (inv.collector_id || inv.collector_name || inv.processed_by) return false
        } else {
          const targetCollector = collectors.find(
            (c) => c.name === collectorFilter || String(c.id) === collectorFilter || c.username === collectorFilter
          )
          const filterLower = collectorFilter.toLowerCase()

          const matchCol =
            (targetCollector && inv.collector_id && Number(inv.collector_id) === Number(targetCollector.id)) ||
            (inv.collector_name && inv.collector_name.toLowerCase().includes(filterLower)) ||
            (inv.processed_by && inv.processed_by.toLowerCase().includes(filterLower))

          if (!matchCol) return false
        }
      }

      // Search query
      if (search.trim()) {
        const q = search.toLowerCase()
        const matchNumber = inv.invoice_number?.toLowerCase().includes(q)
        const matchName = inv.customer_name?.toLowerCase().includes(q)
        const matchCode = inv.customer_code?.toLowerCase().includes(q)
        const matchPhone = inv.customer_phone?.toLowerCase().includes(q)
        const matchPkg = inv.package_name?.toLowerCase().includes(q)
        const matchRouter = inv.router_name?.toLowerCase().includes(q)
        const matchCollector =
          (inv.collector_name && inv.collector_name.toLowerCase().includes(q)) ||
          (inv.processed_by && inv.processed_by.toLowerCase().includes(q))
        const matchNotes = inv.notes?.toLowerCase().includes(q) || inv.admin_notes?.toLowerCase().includes(q)

        if (
          !matchNumber &&
          !matchName &&
          !matchCode &&
          !matchPhone &&
          !matchPkg &&
          !matchRouter &&
          !matchCollector &&
          !matchNotes
        ) {
          return false
        }
      }

      return true
    })
  }, [consolidatedInvoices, status, channelFilter, collectorFilter, search])

  useEffect(() => {
    const updateWidth = () => {
      if (tableScrollRef.current) {
        setTableScrollWidth(tableScrollRef.current.scrollWidth)
      }
    }
    updateWidth()
    const timer = setTimeout(updateWidth, 100)
    return () => clearTimeout(timer)
  }, [filteredInvoices])

  // ---------------------------------------------------------------------------
  // STATS CALCULATIONS
  // ---------------------------------------------------------------------------
  const stats = useMemo(() => {
    let totalPendingAmount = 0
    let totalPaidAmount = 0
    let pendingCount = 0
    let paidCount = 0
    let overdueCount = 0
    let cancelledCount = 0

    for (const inv of consolidatedInvoices) {
      const isPaid = inv.paid || inv.status === "paid"
      const isCancelled = inv.status === "cancelled" || inv.status === "dibatalkan"
      const isOverdue =
        !isPaid &&
        !isCancelled &&
        inv.due_date &&
        new Date(inv.due_date).getTime() < new Date().setHours(0, 0, 0, 0)

      if (isCancelled) {
        cancelledCount++
      } else if (isPaid) {
        paidCount++
        totalPaidAmount += Number(inv.amount || 0)
      } else {
        pendingCount++
        totalPendingAmount += Number(inv.amount || 0)
        if (isOverdue) overdueCount++
      }
    }

    const grandTotal = totalPendingAmount + totalPaidAmount
    const collectionRate =
      grandTotal > 0 ? Math.round((totalPaidAmount / grandTotal) * 100) : 0

    return {
      totalCount: consolidatedInvoices.length,
      pendingCount,
      paidCount,
      overdueCount,
      cancelledCount,
      totalPendingAmount,
      totalPaidAmount,
      grandTotal,
      collectionRate,
    }
  }, [consolidatedInvoices])

  // ---------------------------------------------------------------------------
  // CARD SELECTION FOR BATCH ACTIONS
  // ---------------------------------------------------------------------------
  const selection = useCardSelection({
    items: filteredInvoices,
    getItemId: (inv) => inv.id,
  })

  const isAllFilteredSelected = useMemo(() => {
    if (filteredInvoices.length === 0) return false
    return filteredInvoices.every((inv) => selection.isSelected(inv.id))
  }, [filteredInvoices, selection])

  const handleToggleSelectAll = useCallback(() => {
    if (isAllFilteredSelected) {
      selection.deselectAll()
    } else {
      selection.selectAll(filteredInvoices.map((inv) => inv.id))
    }
  }, [isAllFilteredSelected, filteredInvoices, selection])

  const selectedInvoices = useMemo(() => {
    const set = new Set(selection.selectedIds)
    return filteredInvoices.filter((inv) => set.has(inv.id))
  }, [filteredInvoices, selection.selectedIds])

  const selectedPendingInvoices = useMemo(() => {
    return selectedInvoices.filter((inv) => !inv.paid && inv.status !== "paid")
  }, [selectedInvoices])

  const selectedPaidInvoices = useMemo(() => {
    return selectedInvoices.filter((inv) => inv.paid || inv.status === "paid")
  }, [selectedInvoices])

  const batchPayTotalAmount = useMemo(() => {
    const targetInvoices = selectedPendingInvoices.length > 0 ? selectedPendingInvoices : selectedInvoices
    return targetInvoices.reduce((sum, inv) => {
      const allBd = parseBreakdown(inv.periods_breakdown)
      if (allBd.length === 0) return sum + Number(inv.amount || 0)

      const defaultChecked = allBd.map((p) => p.period)
      const currentChecked = selectedPeriodsMap[inv.id] ?? defaultChecked
      const checkedAmount = allBd
        .filter((p) => currentChecked.includes(p.period))
        .reduce((s, p) => s + p.amount, 0)

      return sum + (checkedAmount > 0 ? checkedAmount : Number(inv.amount || 0))
    }, 0)
  }, [selectedPendingInvoices, selectedInvoices, selectedPeriodsMap])

  // Copy helper
  const handleCopy = (text: string) => {
    if (!text) return
    navigator.clipboard.writeText(text)
    setCopiedInvoiceNumber(text)
    setTimeout(() => {
      setCopiedInvoiceNumber(null)
    }, 2000)
  }

  // ---------------------------------------------------------------------------
  // ACTION HANDLERS
  // ---------------------------------------------------------------------------

  // Open manage & payment modal with multi-period selection
  const handleOpenManagingModal = (inv: Invoice) => {
    const allBd = parseBreakdown(inv.periods_breakdown)
    const defaultChecked =
      allBd.length > 0
        ? allBd.map((p) => p.period)
        : [inv.period ?? (inv.due_date ? inv.due_date.slice(0, 7) : "")]
    setModalSelectedPeriods(defaultChecked)
    setManagingInvoice(inv)
  }

  // Execute payment directly from detail modal
  const handleExecuteModalPay = () => {
    if (!managingInvoice) return
    const allBd = parseBreakdown(managingInvoice.periods_breakdown)
    const amountToPay =
      allBd.length > 0
        ? allBd
            .filter((p) => modalSelectedPeriods.includes(p.period))
            .reduce((sum, p) => sum + p.amount, 0)
        : Number(managingInvoice.amount || 0)

    router.post(
      `/admin/billing/pay/${managingInvoice.id}`,
      {
        paid_periods: modalSelectedPeriods.join(","),
        periods_breakdown: JSON.stringify(
          allBd.length > 0
            ? allBd
            : [
                {
                  period:
                    managingInvoice.period ??
                    (managingInvoice.due_date
                      ? managingInvoice.due_date.slice(0, 7)
                      : ""),
                  amount: Number(managingInvoice.amount || 0),
                  label: periodLabel(displayPeriod(managingInvoice)),
                },
              ]
        ),
        amount: amountToPay > 0 ? amountToPay : Number(managingInvoice.amount || 0),
      },
      {
        preserveScroll: true,
        onSuccess: () => {
          setManagingInvoice(null)
          selection.deselectAll()
        },
      }
    )
  }

  // Open single pay confirmation modal
  const handleOpenPayModal = (inv: Invoice) => {
    const allBd = parseBreakdown(inv.periods_breakdown)
    const defaultChecked =
      allBd.length > 0
        ? allBd.map((p) => p.period)
        : [inv.period ?? (inv.due_date ? inv.due_date.slice(0, 7) : "")]

    const currentChecked = selectedPeriodsMap[inv.id] ?? defaultChecked

    const amount =
      allBd.length > 0
        ? allBd
            .filter((p) => currentChecked.includes(p.period))
            .reduce((sum, p) => sum + p.amount, 0)
        : Number(inv.amount || 0)

    setConfirm({
      open: true,
      invoice: inv,
      selectedPeriods: currentChecked,
      allPeriods: allBd,
      amountToPay: amount > 0 ? amount : Number(inv.amount || 0),
    })
  }

  // Execute single payment
  const executePay = () => {
    if (!payConfirmModal.invoice) return

    router.post(
      `/admin/billing/pay/${payConfirmModal.invoice.id}`,
      {
        paid_periods: payConfirmModal.selectedPeriods.join(","),
        periods_breakdown: JSON.stringify(payConfirmModal.allPeriods),
        amount: payConfirmModal.amountToPay,
      },
      {
        preserveScroll: true,
        onSuccess: () => {
          setPayConfirmModal({
            open: false,
            invoice: null,
            selectedPeriods: [],
            allPeriods: [],
            amountToPay: 0,
          })
          selection.deselectAll()
        },
      }
    )
  }

  // Execute batch payment
  const executeBatchPay = () => {
    const targetInvoices = selectedPendingInvoices.length > 0 ? selectedPendingInvoices : selectedInvoices
    if (targetInvoices.length === 0) return

    const payload = targetInvoices.map((inv) => {
      const allBd = parseBreakdown(inv.periods_breakdown)
      const defaultChecked = allBd.map((p) => p.period)
      const currentChecked = selectedPeriodsMap[inv.id] ?? defaultChecked

      return {
        invoice_id: inv.id,
        paid_periods: currentChecked.join(","),
        periods_breakdown: JSON.stringify(allBd),
      }
    })

    router.post(
      `/admin/billing/pay-batch`,
      {
        items: payload,
        invoice_ids: targetInvoices.map((i) => i.id),
      },
      {
        preserveScroll: true,
        onSuccess: () => {
          setBatchPayConfirmOpen(false)
          selection.deselectAll()
        },
      }
    )
  }

  // Reset paid invoice back to unpaid
  const handleUnpayInvoice = (inv: Invoice) => {
    if (typeof window !== "undefined" && window.confirm(`Reset status tagihan ${inv.invoice_number} menjadi Belum Bayar?`)) {
      router.post(
        `/admin/billing/invoices/unpay/${inv.id}`,
        {},
        {
          preserveScroll: true,
          onSuccess: () => {
            selection.deselectAll()
          },
        }
      )
    }
  }

  // Delete invoice
  const handleDeleteInvoice = (inv: Invoice) => {
    if (typeof window !== "undefined" && window.confirm(`Hapus invoice ${inv.invoice_number} secara permanen?`)) {
      router.post(
        `/admin/billing/delete-invoice/${inv.id}`,
        {},
        {
          preserveScroll: true,
          onSuccess: () => {
            selection.deselectAll()
          },
        }
      )
    }
  }

  // Batch Cancel (Unpay)
  const executeBatchCancel = () => {
    if (selectedPaidInvoices.length === 0) return
    router.post(
      `/admin/billing/cancel-batch`,
      { invoice_ids: selectedPaidInvoices.map((i) => i.id) },
      {
        preserveScroll: true,
        onSuccess: () => {
          setBatchCancelConfirmOpen(false)
          selection.deselectAll()
        },
      }
    )
  }

  // Batch Delete
  const executeBatchDelete = () => {
    if (selectedInvoices.length === 0) return
    router.post(
      `/admin/billing/delete-batch`,
      { invoice_ids: selectedInvoices.map((i) => i.id) },
      {
        preserveScroll: true,
        onSuccess: () => {
          setBatchDeleteConfirmOpen(false)
          selection.deselectAll()
        },
      }
    )
  }

  // Batch Unisolate
  const executeBatchUnisolate = () => {
    if (selectedInvoices.length === 0) return
    const customerIds = Array.from(
      new Set(selectedInvoices.map((i) => i.customer_id).filter(Boolean))
    )
    router.post(
      `/admin/billing/customers/unisolate-batch`,
      { customer_ids: customerIds },
      {
        preserveScroll: true,
        onSuccess: () => {
          setBatchUnisolateConfirmOpen(false)
          selection.deselectAll()
        },
      }
    )
  }

  // WhatsApp Single Modal Send via Gateway
  const handleSendWaGateway = async () => {
    if (!waModalInvoice) return
    setWaSending(true)
    setWaStatusMsg(null)

    try {
      const res = await fetch(`/admin/billing/invoices/${waModalInvoice.id}/send-wa`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "Accept": "application/json",
          "X-Requested-With": "XMLHttpRequest",
          "X-CSRF-TOKEN":
            (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || "",
        },
      })
      const contentType = res.headers.get("content-type") || ""
      if (contentType.includes("application/json")) {
        const data = await res.json()
        if (res.ok && data.success) {
          setWaStatusMsg({
            type: "success",
            text: data.message || "Pesan WhatsApp berhasil dikirim ke antrean gateway.",
          })
        } else {
          setWaStatusMsg({
            type: "error",
            text: data.message || "Gagal mengirim WhatsApp via gateway.",
          })
        }
      } else {
        if (res.ok) {
          setWaStatusMsg({
            type: "success",
            text: "Pesan WhatsApp berhasil diproses.",
          })
        } else {
          setWaStatusMsg({
            type: "error",
            text: `Gagal mengirim WhatsApp (Status ${res.status}).`,
          })
        }
      }
    } catch (err: any) {
      setWaStatusMsg({
        type: "error",
        text: err.message || "Terjadi kesalahan koneksi saat mengirim WhatsApp.",
      })
    } finally {
      setWaSending(false)
    }
  }

  // WhatsApp Web Manual Link
  const getWaManualUrl = (inv: Invoice) => {
    const phone = inv.customer_phone ? inv.customer_phone.replace(/\D/g, "") : ""
    const targetPhone = phone.startsWith("0") ? "62" + phone.slice(1) : phone
    const isPaid = inv.paid || inv.status === "paid"

    let msg = ""
    if (isPaid) {
      msg = `Halo *${inv.customer_name}*, pembayaran tagihan internet No. *${inv.invoice_number}* sebesar *${formatIDR(inv.amount)}* telah kami terima. Terima kasih!`
    } else {
      msg = `Halo *${inv.customer_name}*, kami informasikan tagihan internet Anda No. *${inv.invoice_number}* sebesar *${formatIDR(inv.amount)}* periode *${periodLabel(displayPeriod(inv))}* belum terbayar. Mohon segera melakukan pembayaran. Terima kasih.`
    }
    return `https://wa.me/${targetPhone}?text=${encodeURIComponent(msg)}`
  }

  // Batch WhatsApp Send
  const executeBatchSendWa = () => {
    if (selectedInvoices.length === 0) return
    router.post(
      `/admin/billing/invoices/send-wa-batch`,
      { invoice_ids: selectedInvoices.map((i) => i.id) },
      {
        preserveScroll: true,
        onSuccess: () => {
          setWaBatchConfirmOpen(false)
          selection.deselectAll()
        },
      }
    )
  }

  // Advance Payment Submit
  const handleAdvancePaymentSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    if (!advCustomerId) {
      alert("Pilih pelanggan terlebih dahulu!")
      return
    }

    router.post(
      `/admin/billing/advance-payment`,
      {
        customer_id: advCustomerId,
        months_count: advMonthsCount,
        start_period: advStartPeriod,
        amount_per_month: advAmountPerMonth,
        notes: advNotes,
        mark_paid: advMarkPaid,
      },
      {
        preserveScroll: true,
        onSuccess: () => {
          setAdvanceModalOpen(false)
          setAdvCustomerId("")
          setAdvCustomerSearch("")
          setAdvMonthsCount(1)
          setAdvNotes("")
        },
      }
    )
  }

  // Auto Invoice Config Save
  const handleSaveAutoInvoiceConfig = async () => {
    setSavingAutoConfig(true)
    try {
      const res = await fetch(`/admin/billing/auto-invoice-settings`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "Accept": "application/json",
          "X-Requested-With": "XMLHttpRequest",
          "X-CSRF-TOKEN":
            (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || "",
        },
        body: JSON.stringify({
          is_auto: autoGenInvoice,
          auto_generate_invoice: autoGenInvoice,
          generate_day: autoGenDay,
          invoice_generate_day: autoGenDay,
          auto_wa: autoWaCreated,
          auto_wa_invoice_created: autoWaCreated,
          reminder_auto: autoWaReminder,
          auto_wa_reminder: autoWaReminder,
          reminder_days: reminderDays,
          wa_reminder_days: reminderDays,
        }),
      })
      const data = await res.json()
      if (res.ok && data.success) {
        setAutoInvoiceModalOpen(false)
        router.reload({ only: ["autoInvoiceConfig"] })
      } else {
        alert(data.message || "Gagal menyimpan pengaturan auto tagihan.")
      }
    } catch (err: any) {
      alert(err.message || "Terjadi kesalahan jaringan.")
    } finally {
      setSavingAutoConfig(false)
    }
  }

  // Trigger Generate Invoices Now
  const handleGenerateInvoicesNow = () => {
    if (typeof window !== "undefined" && window.confirm("Generate tagihan bulan ini untuk semua pelanggan aktif sekarang?")) {
      router.post(
        `/admin/billing/generate-now`,
        {},
        {
          preserveScroll: true,
          onSuccess: (page: any) => {
            setAutoInvoiceModalOpen(false)
            alert(page?.props?.flash?.success || "Tagihan bulan ini berhasil diproses!")
          },
          onError: (err: any) => {
            alert(err?.message || "Gagal memproses tagihan bulan ini.")
          },
        }
      )
    }
  }

  // Reset Invoices per Period
  const handleResetInvoicesSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    if (!resetPeriod) {
      alert("Pilih periode tagihan yang ingin di-reset!")
      return
    }

    if (
      typeof window !== "undefined" &&
      window.confirm(
        `PERINGATAN: Anda akan me-reset ${resetScope === "all" ? "SEMUA" : "tagihan BELUM LUNAS"} pada periode ${periodLabel(resetPeriod)}. Tindakan ini tidak dapat dibatalkan. Lanjutkan?`
      )
    ) {
      router.post(
        `/admin/billing/reset-invoices`,
        {
          period: resetPeriod,
          scope: resetScope,
        },
        {
          preserveScroll: true,
          onSuccess: () => {
            setResetModalOpen(false)
          },
        }
      )
    }
  }

  // Handle customer select in advance modal
  const handleSelectCustomerForAdvance = (c: CustomerOption) => {
    setAdvCustomerId(c.id)
    setAdvCustomerSearch(`${c.name} (${c.customer_code || `#${c.id}`})`)
    setAdvAmountPerMonth(c.package_price || 0)
    setAdvCustomerDropdownOpen(false)
  }

  // Period navigation helpers
  const currentMonthStr = useMemo(() => new Date().toISOString().slice(0, 7), [])

  const handlePrevPeriod = () => {
    const basePeriod = (selectedPeriod && selectedPeriod !== "all") ? selectedPeriod : currentMonthStr
    const parts = basePeriod.split("-")
    if (parts.length >= 2) {
      let y = parseInt(parts[0], 10)
      let m = parseInt(parts[1], 10) - 1
      if (m < 1) {
        m = 12
        y -= 1
      }
      const prevP = `${y}-${String(m).padStart(2, "0")}`
      router.get(
        "/admin/billing/invoices",
        { period: prevP },
        { preserveScroll: true, preserveState: true }
      )
    }
  }

  const handleNextPeriod = () => {
    const basePeriod = (selectedPeriod && selectedPeriod !== "all") ? selectedPeriod : currentMonthStr
    const parts = basePeriod.split("-")
    if (parts.length >= 2) {
      let y = parseInt(parts[0], 10)
      let m = parseInt(parts[1], 10) + 1
      if (m > 12) {
        m = 1
        y += 1
      }
      const nextP = `${y}-${String(m).padStart(2, "0")}`
      router.get(
        "/admin/billing/invoices",
        { period: nextP },
        { preserveScroll: true, preserveState: true }
      )
    }
  }

  const handleSelectPeriod = (p: string | null) => {
    setCalendarOpen(false)
    router.get(
      "/admin/billing/invoices",
      p ? { period: p } : {},
      { preserveScroll: true, preserveState: true }
    )
  }

  const handleSelectDay = (d: number | null) => {
    setCalendarOpen(false)
    const params: Record<string, any> = {}
    if (selectedPeriod && selectedPeriod !== "all") {
      params.period = selectedPeriod
    }
    if (d !== null) {
      params.day = d
    }
    router.get(
      "/admin/billing/invoices",
      params,
      { preserveScroll: true, preserveState: true }
    )
  }

  // Multi-period toggle for specific invoice
  const togglePeriodSelection = (invId: number, period: string, allPeriods: BreakdownPeriod[]) => {
    const defaultChecked = allPeriods.map((p) => p.period)
    const currentChecked = selectedPeriodsMap[invId] ?? defaultChecked

    let nextChecked: string[]
    if (currentChecked.includes(period)) {
      nextChecked = currentChecked.filter((p) => p !== period)
    } else {
      nextChecked = [...currentChecked, period]
    }

    setSelectedPeriodsMap((prev) => ({
      ...prev,
      [invId]: nextChecked,
    }))
  }

  // ---------------------------------------------------------------------------
  // DONUT CHART OPTIONS
  // ---------------------------------------------------------------------------
  const donutOptions: ApexOptions = useMemo(() => ({
    chart: {
      type: "donut",
      fontFamily: "Outfit, Inter, sans-serif",
      background: "transparent",
    },
    labels: ["Sudah Lunas", "Belum Bayar"],
    colors: ["#10B981", "#F59E0B"],
    stroke: { show: false },
    dataLabels: { enabled: false },
    legend: {
      position: "bottom",
      labels: { colors: "#94A3B8" },
      itemMargin: { horizontal: 6, vertical: 2 },
      fontSize: "11px",
      fontWeight: 600,
    },
    plotOptions: {
      pie: {
        donut: {
          size: "72%",
          labels: {
            show: true,
            total: {
              show: true,
              label: "TERTAGIH",
              color: "#94A3B8",
              fontSize: "10px",
              fontWeight: 700,
              formatter: () => `${stats.collectionRate}%`,
            },
            value: {
              fontSize: "12px",
              fontWeight: 800,
              color: "#10B981",
              formatter: () => `${stats.paidCount}/${stats.totalCount}`,
            },
          },
        },
      },
    },
    tooltip: {
      theme: "light",
      y: { formatter: (val: number) => formatIDR(val) },
    },
  }), [stats])

  const donutSeries = useMemo(() => [
    stats.totalPaidAmount || 0,
    stats.totalPendingAmount || 0,
  ], [stats])

  // ---------------------------------------------------------------------------
  // RENDER
  // ---------------------------------------------------------------------------
  return (
    <AppLayout
      title="Invoice & Tagihan"
      brand={adminBrand}
      sidebarItems={adminSidebarItems}
      navItems={adminNavItems}
      hideBottomNav={selection.isSelectMode}
    >
      <Head title="Manajemen Invoice & Tagihan - Nodera Admin" />
      <SyncOverlay />

      <div className="space-y-6">
        {/* Executive Collection Progress & Metric Overview */}
        <div className="rounded-2xl border border-gray-200 bg-white p-4 sm:p-5 shadow-xs dark:border-gray-800 dark:bg-white/[0.03]">
          <div className="grid grid-cols-1 lg:grid-cols-12 gap-5 items-center">
            {/* Left Metrics (8 Cols) */}
            <div className="lg:col-span-8 space-y-4">
              <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-gray-100 dark:border-gray-800/80 pb-3">
                <div>
                  <h3 className="text-sm font-bold text-gray-900 dark:text-white flex items-center gap-2">
                    <TrendingUp className="h-4 w-4 text-brand-500" />
                    <span>Performa &amp; Tingkat Penagihan</span>
                  </h3>
                  <p className="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                    Periode {selectedPeriod && selectedPeriod !== "all" ? periodLabel(selectedPeriod) : periodLabel(currentMonthStr)}
                  </p>
                </div>
                <div className="flex items-center gap-2">
                  <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-bold bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400">
                    <CheckCircle2 className="h-3.5 w-3.5" />
                    {stats.collectionRate}% Tertagih
                  </span>
                </div>
              </div>

              {/* Progress Bar */}
              <div className="space-y-1.5">
                <div className="flex justify-between text-xs font-semibold text-gray-600 dark:text-gray-300">
                  <span>Progres Terbayar: {formatIDR(stats.totalPaidAmount)}</span>
                  <span>Target: {formatIDR(stats.grandTotal)}</span>
                </div>
                <div className="h-2.5 w-full rounded-full bg-gray-100 dark:bg-gray-800 overflow-hidden">
                  <div
                    className="h-full bg-gradient-to-r from-emerald-500 to-teal-400 rounded-full transition-all duration-500"
                    style={{ width: `${Math.min(100, Math.max(0, stats.collectionRate))}%` }}
                  />
                </div>
              </div>

              {/* 3 Interactive Metric Buttons */}
              <div className="grid grid-cols-3 gap-2.5 pt-1">
                <button
                  type="button"
                  onClick={() => setStatus("paid")}
                  className={cn(
                    "p-3 rounded-xl border text-left transition cursor-pointer",
                    status === "paid"
                      ? "border-emerald-500 bg-emerald-50/60 dark:bg-emerald-500/10 shadow-2xs"
                      : "border-gray-100 dark:border-gray-800 bg-gray-50/60 dark:bg-gray-900/40 hover:bg-gray-100 dark:hover:bg-gray-800/60"
                  )}
                >
                  <span className="text-[10px] font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider block">Sudah Lunas</span>
                  <div className="text-sm sm:text-base font-bold text-emerald-600 dark:text-emerald-400 tabular-nums mt-0.5">
                    {formatIDR(stats.totalPaidAmount)}
                  </div>
                  <span className="text-[10px] text-gray-400">{stats.paidCount} tagihan</span>
                </button>

                <button
                  type="button"
                  onClick={() => setStatus("pending")}
                  className={cn(
                    "p-3 rounded-xl border text-left transition cursor-pointer",
                    status === "pending"
                      ? "border-amber-500 bg-amber-50/60 dark:bg-amber-500/10 shadow-2xs"
                      : "border-gray-100 dark:border-gray-800 bg-gray-50/60 dark:bg-gray-900/40 hover:bg-gray-100 dark:hover:bg-gray-800/60"
                  )}
                >
                  <span className="text-[10px] font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider block">Belum Bayar</span>
                  <div className="text-sm sm:text-base font-bold text-amber-600 dark:text-amber-400 tabular-nums mt-0.5">
                    {formatIDR(stats.totalPendingAmount)}
                  </div>
                  <span className="text-[10px] text-gray-400">{stats.pendingCount} pending</span>
                </button>

                <button
                  type="button"
                  onClick={() => setStatus("all")}
                  className={cn(
                    "p-3 rounded-xl border text-left transition cursor-pointer",
                    status === "all"
                      ? "border-brand-500 bg-brand-50/60 dark:bg-brand-500/10 shadow-2xs"
                      : "border-gray-100 dark:border-gray-800 bg-gray-50/60 dark:bg-gray-900/40 hover:bg-gray-100 dark:hover:bg-gray-800/60"
                  )}
                >
                  <span className="text-[10px] font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider block">Total Tagihan</span>
                  <div className="text-sm sm:text-base font-bold text-gray-900 dark:text-white tabular-nums mt-0.5">
                    {formatIDR(stats.grandTotal)}
                  </div>
                  <span className="text-[10px] text-gray-400">{stats.totalCount} invoice</span>
                </button>
              </div>
            </div>

            {/* Right Donut Chart (4 Cols) */}
            <div className="lg:col-span-4 flex flex-col items-center justify-center p-2 rounded-xl bg-gray-50/60 dark:bg-gray-900/30 border border-gray-100 dark:border-gray-800/60">
              <span className="text-[11px] font-bold text-gray-400 uppercase tracking-wider mb-1">Distribusi Tagihan</span>
              {stats.grandTotal === 0 ? (
                <div className="h-[160px] flex items-center justify-center text-xs text-gray-400">
                  Tidak ada data tagihan
                </div>
              ) : (
                <Chart
                  options={donutOptions}
                  series={donutSeries}
                  type="donut"
                  height={170}
                  width="100%"
                />
              )}
            </div>
          </div>
        </div>

        {/* ── 1-LINE RESPONSIVE INTEGRATED TOOLBAR ── */}
        <div className="rounded-2xl border border-gray-200 bg-white p-3 sm:p-4 shadow-xs dark:border-gray-800 dark:bg-white/[0.03] flex flex-col gap-2.5 sm:gap-3 lg:flex-row lg:items-center lg:justify-between">
          <div className="flex items-center gap-2 sm:gap-3 flex-1 min-w-0">
            {/* SEARCH INPUT */}
            <div className="relative min-w-[180px] sm:min-w-[220px] flex-1">
              <Search className="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400 dark:text-gray-500" />
              <input
                type="text"
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                placeholder="Cari invoice, pelanggan, paket, no hp..."
                className="h-10 w-full rounded-xl border border-gray-200 bg-gray-50/75 pl-9 pr-8 text-xs text-gray-800 placeholder:text-gray-400 focus:border-brand-500 focus:outline-none dark:border-gray-800 dark:bg-gray-900/50 dark:text-gray-200 dark:placeholder:text-gray-500"
              />
              {search && (
                <button
                  type="button"
                  onClick={() => setSearch("")}
                  className="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 cursor-pointer"
                >
                  <X className="h-3.5 w-3.5" />
                </button>
              )}
            </div>

            {/* CALENDAR & DATE SELECTOR BY ICON */}
            <button
              type="button"
              onClick={() => setCalendarOpen(true)}
              title={
                selectedDay && selectedDay !== "all"
                  ? `Filter Tanggal ${selectedDay}${selectedPeriod && selectedPeriod !== "all" ? ` • ${periodLabel(selectedPeriod)}` : ""}`
                  : selectedPeriod && selectedPeriod !== "all"
                  ? `Filter Bulan: ${periodLabel(selectedPeriod)}`
                  : "Pilih Bulan / Tanggal Tagihan"
              }
              className="inline-flex h-10 items-center justify-center gap-1.5 px-3 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:hover:bg-gray-800 text-gray-700 dark:text-gray-200 transition cursor-pointer shadow-2xs text-xs font-semibold shrink-0"
            >
              <Calendar className="h-4 w-4" />
              <span className="hidden sm:inline">
                {selectedDay && selectedDay !== "all"
                  ? `Tgl ${selectedDay}${selectedPeriod && selectedPeriod !== "all" ? ` • ${periodLabel(selectedPeriod)}` : ""}`
                  : (selectedPeriod && selectedPeriod !== "all")
                  ? periodLabel(selectedPeriod)
                  : "Semua Bulan"}
              </span>
            </button>
          </div>

          <div className="flex items-center gap-2 w-full lg:w-auto justify-between lg:justify-end shrink-0">
            {/* ViewModeSwitcher */}
            <div className="shrink-0">
              <ViewModeSwitcher value={viewMode} onChange={handleViewModeChange} size="sm" />
            </div>

            {/* FILTER BUTTON MODAL TRIGGER */}
            <button
              type="button"
              onClick={() => setFilterModalOpen(true)}
              className={cn(
                "inline-flex h-10 items-center justify-center gap-1.5 px-3 rounded-xl border text-xs font-semibold transition-all cursor-pointer shadow-2xs shrink-0",
                (channelFilter !== "all" || collectorFilter !== "all")
                  ? "border-brand-500 text-brand-500 font-bold bg-white dark:bg-gray-900"
                  : "border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800"
              )}
            >
              <SlidersHorizontal className="h-4 w-4" />
              <span className="hidden sm:inline">Filter</span>
              {(channelFilter !== "all" || collectorFilter !== "all") && (
                <span className="h-2 w-2 rounded-full bg-brand-500" />
              )}
            </button>

            {/* OTOMASI & GENERATE BUTTON */}
            <button
              type="button"
              onClick={() => setAutoInvoiceModalOpen(true)}
              title="Pengaturan Tagihan Otomatis & Generate Manual"
              className="inline-flex h-10 items-center justify-center gap-1.5 px-3 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:hover:bg-gray-800 text-xs font-semibold text-gray-700 dark:text-gray-200 transition cursor-pointer shadow-2xs shrink-0"
            >
              <Sparkles className="h-4 w-4" />
              <span className="hidden sm:inline">Otomasi</span>
            </button>

            {/* GENERATE TAGIHAN PRIMARY BUTTON */}
            <button
              type="button"
              onClick={handleGenerateInvoicesNow}
              title="Generate Tagihan Bulan Ini untuk Seluruh Pelanggan Aktif"
              className="inline-flex h-10 flex-1 sm:flex-initial items-center justify-center gap-1.5 px-3.5 sm:px-4 rounded-xl bg-brand-500 hover:bg-brand-600 active:scale-95 text-xs font-bold text-white shadow-xs transition cursor-pointer shrink-0"
            >
              <Activity className="h-4 w-4" />
              <span>Generate Tagihan</span>
            </button>
          </div>
        </div>

        {/* MASTER CARD CONTAINER 1:1 Packages.tsx */}
        <div className="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xs dark:border-gray-800 dark:bg-white/[0.03]">
          {filteredInvoices.length === 0 ? (
            <div className="p-8 sm:p-12 text-center space-y-4">
              <EmptyState
                icon={<Receipt className="h-8 w-8 text-brand-500" />}
                title="Tidak ada invoice ditemukan"
                description={
                  search || status !== "all" || channelFilter !== "all"
                    ? "Tidak ada invoice yang cocok dengan filter yang dipilih."
                    : "Belum ada invoice yang terbit pada periode ini."
                }
              />
              <div className="flex items-center justify-center gap-3 pt-2">
                {(search || status !== "all" || channelFilter !== "all") && (
                  <button
                    type="button"
                    onClick={() => {
                      setSearch("")
                      setStatus("all")
                      setChannelFilter("all")
                      setCollectorFilter("all")
                    }}
                    className="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:hover:bg-gray-800 text-xs font-semibold text-gray-700 dark:text-gray-200 transition shadow-2xs cursor-pointer"
                  >
                    Reset Filter
                  </button>
                )}
                <button
                  type="button"
                  onClick={() => {
                    setAdvCustomerId("")
                    setAdvCustomerSearch("")
                    setAdvMonthsCount(1)
                    setAdvanceModalOpen(true)
                  }}
                  className="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-brand-500 hover:bg-brand-600 active:scale-95 text-xs font-bold text-white shadow-xs transition cursor-pointer"
                >
                  <Plus className="h-4 w-4" />
                  <span>Buat Tagihan Manual</span>
                </button>
              </div>
            </div>
          ) : viewMode === "table" ? (
            /* High-Density TailAdmin Table View with Separated Columns */
            <div className="w-full min-w-0">
              {/* ── TOP SYNCHRONIZED SCROLL RUNWAY ── */}
              <div
                ref={topScrollRef}
                onScroll={handleTopScroll}
                className="overflow-x-auto table-scrollbar w-full border-b border-gray-100 dark:border-gray-800 bg-gray-50/70 dark:bg-gray-900/50"
              >
                <div style={{ width: `${tableScrollWidth}px`, height: '10px' }} />
              </div>

              <div
                ref={tableScrollRef}
                onScroll={handleTableScroll}
                className="w-full overflow-x-auto table-scrollbar"
              >
                <table className="w-full min-w-[1100px] text-start text-xs whitespace-nowrap border-collapse">
                <thead className="border-b border-gray-200 bg-gray-50/75 dark:border-gray-800 dark:bg-gray-800/50 text-gray-500 dark:text-gray-400 font-semibold">
                  <tr>
                    <th className="w-10 py-3.5 px-3 text-center">
                      <Checkbox
                        checked={isAllFilteredSelected}
                        onChange={handleToggleSelectAll}
                      />
                    </th>
                    <th className="py-3.5 px-3 text-start">No. Invoice</th>
                    <th className="py-3.5 px-3 text-start">Pelanggan</th>
                    <th className="py-3.5 px-3 text-start">Kontak / No HP</th>
                    <th className="py-3.5 px-3 text-start">Paket &amp; Router</th>
                    <th className="py-3.5 px-3 text-start">Periode / Jatuh Tempo</th>
                    <th className="py-3.5 px-3 text-start">Penanggung Jawab</th>
                    <th className="py-3.5 px-3 text-end">Nominal (IDR)</th>
                    <th className="py-3.5 px-3 text-center">Status</th>
                    <th className="py-3.5 px-3 text-center">Aksi</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-gray-200 dark:divide-gray-800">
                  {filteredInvoices.map((inv) => {
                    const isPaid = inv.paid || inv.status === "paid"
                    const isCancelled =
                      inv.status === "cancelled" || inv.status === "dibatalkan"
                    const isOverdue =
                      !isPaid &&
                      !isCancelled &&
                      inv.due_date &&
                      new Date(inv.due_date).getTime() < new Date().setHours(0, 0, 0, 0)

                    const allBd = parseBreakdown(inv.periods_breakdown)
                    const isMulti = !isPaid && allBd.length > 1

                    const defaultChecked =
                      allBd.length > 0
                        ? allBd.map((p) => p.period)
                        : [inv.period ?? (inv.due_date ? inv.due_date.slice(0, 7) : "")]

                    const currentChecked = selectedPeriodsMap[inv.id] ?? defaultChecked

                    const dynamicAmount =
                      allBd.length > 0 && !isPaid
                        ? allBd
                            .filter((p) => currentChecked.includes(p.period))
                            .reduce((s, p) => s + p.amount, 0)
                        : Number(inv.amount || 0)

                    const isExpanded = expandedInvoiceId === inv.id
                    const isSelected = selection.isSelected(inv.id)

                    return (
                      <tr
                        key={inv.id}
                        className={cn(
                          "transition-colors hover:bg-gray-50/50 dark:hover:bg-gray-800/30",
                          isSelected && "bg-brand-50/40 dark:bg-brand-950/20"
                        )}
                      >
                        {/* 1. CHECKBOX */}
                        <td className="px-3 py-3 text-center">
                          <Checkbox
                            checked={isSelected}
                            onChange={() => selection.toggleSelect(inv.id)}
                          />
                        </td>

                        {/* 2. INVOICE NUMBER */}
                        <td className="px-3 py-3">
                          <div className="flex items-center gap-1.5 font-mono text-xs font-semibold text-gray-900 dark:text-white">
                            <span>{inv.invoice_number}</span>
                            <button
                              type="button"
                              onClick={() => handleCopy(inv.invoice_number)}
                              title="Salin No. Invoice"
                              className="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 cursor-pointer"
                            >
                              {copiedInvoiceNumber === inv.invoice_number ? (
                                <Check className="h-3.5 w-3.5 text-emerald-500" />
                              ) : (
                                <Copy className="h-3.5 w-3.5" />
                              )}
                            </button>
                          </div>
                          {inv.payment_channel && inv.payment_channel !== "manual" && (
                            <span className="mt-0.5 inline-block text-[10px] font-medium text-gray-400">
                              Via {inv.payment_channel.toUpperCase()}
                            </span>
                          )}
                        </td>

                        {/* 3. CUSTOMER INFO */}
                        <td className="px-3 py-3">
                          <div className="flex flex-col">
                            <span className="font-bold text-gray-900 dark:text-white">
                              {inv.customer_name}
                            </span>
                            {inv.customer_code && (
                              <span className="mt-0.5 font-mono text-[10px] text-gray-500 dark:text-gray-400">
                                #{inv.customer_code}
                              </span>
                            )}
                          </div>
                        </td>

                        {/* 4. KONTAK / NO HP */}
                        <td className="px-3 py-3">
                          {inv.customer_phone ? (
                            <a
                              href={`https://wa.me/${inv.customer_phone.replace(/\D/g, "")}`}
                              target="_blank"
                              rel="noreferrer"
                              className="inline-flex items-center gap-1 text-[11px] font-medium text-emerald-600 hover:underline dark:text-emerald-400"
                            >
                              <MessageCircle className="h-3.5 w-3.5" />
                              <span>{inv.customer_phone}</span>
                            </a>
                          ) : (
                            <span className="text-[11px] text-gray-400">-</span>
                          )}
                        </td>

                        {/* 5. PAKET & ROUTER */}
                        <td className="px-3 py-3">
                          <div className="flex flex-col gap-0.5">
                            {inv.package_name ? (
                              <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold bg-brand-500 text-white whitespace-nowrap self-start shadow-2xs">
                                {inv.package_name}
                              </span>
                            ) : (
                              <span className="text-[11px] text-gray-400">-</span>
                            )}
                            {inv.router_name && (
                              <span className="text-[10px] text-gray-400">
                                Router: {inv.router_name}
                              </span>
                            )}
                          </div>
                        </td>

                        {/* 6. PERIOD & DUE DATE */}
                        <td className="px-3 py-3">
                          <div className="flex flex-col gap-1">
                            <div className="flex items-center gap-1.5 flex-wrap">
                              <span className="font-semibold text-gray-800 dark:text-gray-200">
                                {periodLabel(displayPeriod(inv))}
                              </span>
                              {isMulti && (
                                <span className="inline-flex items-center px-1.5 py-0.5 rounded-md text-[10px] font-bold bg-amber-500/15 text-amber-700 dark:bg-amber-500/20 dark:text-amber-300 whitespace-nowrap">
                                  {allBd.length} Periode
                                </span>
                              )}
                            </div>

                            <div className="flex items-center gap-1 text-[11px] text-gray-400">
                              <span>JT: {formatDueDate(inv.due_date)}</span>
                              {isOverdue && (
                                <span className="font-bold text-rose-500">
                                  (Lewat Jatuh Tempo)
                                </span>
                              )}
                            </div>
                          </div>
                        </td>

                        {/* 7. PENANGGUNG JAWAB */}
                        <td className="px-3 py-3">
                          {inv.processed_by ? (
                            <div className="flex flex-col">
                              <span className="font-bold text-gray-900 dark:text-white flex items-center gap-1">
                                <User className="h-3 w-3 text-emerald-500 shrink-0" />
                                <span className="truncate max-w-[130px]">{inv.processed_by}</span>
                              </span>
                              <span className="text-[10px] text-emerald-600 dark:text-emerald-400 font-medium">
                                Kolektor Penagih (Lunas)
                              </span>
                            </div>
                          ) : inv.collector_name ? (
                            <div className="flex flex-col">
                              <span className="font-semibold text-gray-900 dark:text-white flex items-center gap-1">
                                <User className="h-3 w-3 text-brand-500 shrink-0" />
                                <span className="truncate max-w-[130px]">{inv.collector_name}</span>
                              </span>
                              <span className="text-[10px] text-gray-400 font-medium">
                                Kolektor Wilayah
                              </span>
                            </div>
                          ) : (
                            <span className="text-[11px] text-gray-400 italic">Admin / Belum Ditugaskan</span>
                          )}
                        </td>

                        {/* 8. NOMINAL (IDR) */}
                        <td className="px-3 py-3 text-end">
                          <div
                            className={cn(
                              "font-mono text-sm font-bold",
                              isPaid
                                ? "text-emerald-600 dark:text-emerald-400"
                                : isOverdue
                                ? "text-rose-600 dark:text-rose-400"
                                : "text-gray-900 dark:text-white"
                            )}
                          >
                            {formatIDR(Number(inv.amount || 0))}
                          </div>
                        </td>

                        {/* 9. SOLID STATUS BADGE */}
                        <td className="px-3 py-3 text-center">
                          {isPaid ? (
                            <span className="inline-flex items-center justify-center rounded-lg whitespace-nowrap bg-emerald-500 px-2.5 py-1 text-xs font-bold uppercase tracking-wider text-white shadow-xs">
                              Lunas
                            </span>
                          ) : isCancelled ? (
                            <span className="inline-flex items-center justify-center rounded-lg whitespace-nowrap bg-gray-500 px-2.5 py-1 text-xs font-bold uppercase tracking-wider text-white shadow-xs">
                              Dibatalkan
                            </span>
                          ) : isOverdue ? (
                            <span className="inline-flex items-center justify-center rounded-lg whitespace-nowrap bg-rose-500 px-2.5 py-1 text-xs font-bold uppercase tracking-wider text-white shadow-xs">
                              Jatuh Tempo
                            </span>
                          ) : (
                            <span className="inline-flex items-center justify-center rounded-lg whitespace-nowrap bg-amber-500 px-2.5 py-1 text-xs font-bold uppercase tracking-wider text-white shadow-xs">
                              Belum Bayar
                            </span>
                          )}
                        </td>

                        {/* 10. ACTION BUTTONS: KELOLA (Solid Icon-Only) */}
                        <td className="px-3.5 py-3 text-center whitespace-nowrap align-middle">
                          <button
                            type="button"
                            onClick={() => handleOpenManagingModal(inv)}
                            className="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-brand-500 hover:bg-brand-600 active:scale-95 text-white shadow-xs transition cursor-pointer"
                            title="Kelola Tagihan"
                          >
                            <Settings className="h-4 w-4" />
                          </button>
                        </td>
                        </tr>
                      )
                    })}
                  </tbody>
                </table>
              </div>
            </div>
          ) : (
            /* ========================================================================= */
            /* GRID VIEW                                                                 */
            /* ========================================================================= */
            <div className="grid grid-cols-1 gap-3.5 sm:grid-cols-2 lg:grid-cols-3">
              {filteredInvoices.map((inv) => {
                const isPaid = inv.paid || inv.status === "paid"
                const isCancelled =
                  inv.status === "cancelled" || inv.status === "dibatalkan"
                const isOverdue =
                  !isPaid &&
                  !isCancelled &&
                  inv.due_date &&
                  new Date(inv.due_date).getTime() < new Date().setHours(0, 0, 0, 0)

                const allBd = parseBreakdown(inv.periods_breakdown)
                const isMulti = !isPaid && allBd.length > 1

                const defaultChecked =
                  allBd.length > 0
                    ? allBd.map((p) => p.period)
                    : [inv.period ?? (inv.due_date ? inv.due_date.slice(0, 7) : "")]

                const currentChecked = selectedPeriodsMap[inv.id] ?? defaultChecked

                const dynamicAmount =
                  allBd.length > 0 && !isPaid
                    ? allBd
                        .filter((p) => currentChecked.includes(p.period))
                        .reduce((s, p) => s + p.amount, 0)
                    : Number(inv.amount || 0)

                const isSelected = selection.isSelected(inv.id)

                return (
                  <div
                    key={inv.id}
                    className={cn(
                      "flex flex-col justify-between rounded-2xl border bg-white p-4 shadow-xs transition-all dark:bg-gray-900",
                      isSelected
                        ? "border-brand-500 ring-2 ring-brand-500/20 dark:border-brand-400"
                        : "border-gray-200 hover:border-gray-300 dark:border-gray-800 dark:hover:border-gray-700"
                    )}
                  >
                    {/* CARD HEADER */}
                    <div>
                      <div className="flex items-start justify-between gap-2">
                        <div className="flex items-center gap-2.5">
                          <Checkbox
                            checked={isSelected}
                            onChange={() => selection.toggleSelect(inv.id)}
                          />
                          <div>
                            <div className="flex items-center gap-1 font-mono text-xs font-semibold text-gray-900 dark:text-white">
                              <span>{inv.invoice_number}</span>
                              <button
                                type="button"
                                onClick={() => handleCopy(inv.invoice_number)}
                                className="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"
                              >
                                {copiedInvoiceNumber === inv.invoice_number ? (
                                  <Check className="h-3 w-3 text-emerald-500" />
                                ) : (
                                  <Copy className="h-3 w-3" />
                                )}
                              </button>
                            </div>
                            <span className="text-[10px] text-gray-400">
                              {inv.payment_channel ? `Via ${inv.payment_channel.toUpperCase()}` : "Manual Cash"}
                            </span>
                          </div>
                        </div>

                        {/* SOLID STATUS BADGE */}
                        <div>
                          {isPaid ? (
                            <span className="inline-flex rounded-lg whitespace-nowrap bg-emerald-500 px-2.5 py-1 text-xs font-bold uppercase tracking-wider text-white shadow-xs">
                              Lunas
                            </span>
                          ) : isCancelled ? (
                            <span className="inline-flex rounded-lg whitespace-nowrap bg-gray-500 px-2.5 py-1 text-xs font-bold uppercase tracking-wider text-white shadow-xs">
                              Dibatalkan
                            </span>
                          ) : isOverdue ? (
                            <span className="inline-flex rounded-lg whitespace-nowrap bg-rose-500 px-2.5 py-1 text-xs font-bold uppercase tracking-wider text-white shadow-xs">
                              Jatuh Tempo
                            </span>
                          ) : (
                            <span className="inline-flex rounded-lg whitespace-nowrap bg-amber-500 px-2.5 py-1 text-xs font-bold uppercase tracking-wider text-white shadow-xs">
                              Belum Bayar
                            </span>
                          )}
                        </div>
                      </div>

                      {/* CUSTOMER INFO & NOMINAL */}
                      <div className="mt-3 border-t border-gray-100 pt-3 dark:border-gray-800">
                        <div className="flex items-baseline justify-between gap-2">
                          <div>
                            <h4 className="font-bold text-gray-900 dark:text-white">
                              {inv.customer_name}
                            </h4>
                            <div className="flex flex-wrap items-center gap-1.5 text-[11px] text-gray-500 dark:text-gray-400">
                              {inv.customer_code && (
                                <span className="font-mono">{inv.customer_code}</span>
                              )}
                              {inv.package_name && (
                                <>
                                  <span>•</span>
                                  <span className="font-medium text-brand-600 dark:text-brand-400">
                                    {inv.package_name}
                                  </span>
                                </>
                              )}
                            </div>
                          </div>

                          <div className="text-right">
                            <div
                              className={cn(
                                "font-mono text-base font-bold",
                                isPaid
                                  ? "text-emerald-600 dark:text-emerald-400"
                                  : isOverdue
                                  ? "text-rose-600 dark:text-rose-400"
                                  : "text-gray-900 dark:text-white"
                              )}
                            >
                              {formatIDR(Number(inv.amount || 0))}
                            </div>
                            {isMulti && (
                              <div className="text-[10px] font-bold text-amber-600 dark:text-amber-400">
                                {allBd.length} Periode Tertunggak
                              </div>
                            )}
                          </div>
                        </div>

                        {/* METADATA STRIP */}
                        <div className="mt-3 flex flex-wrap items-center justify-between gap-2 text-[11px] text-gray-500 dark:text-gray-400">
                          <div className="flex items-center gap-1">
                            <Calendar className="h-3 w-3 text-gray-400" />
                            <span>JT: {formatDueDate(inv.due_date)}</span>
                          </div>
                          {inv.customer_phone && (
                            <a
                              href={`https://wa.me/${inv.customer_phone.replace(/\D/g, "")}`}
                              target="_blank"
                              rel="noreferrer"
                              className="flex items-center gap-1 text-emerald-600 hover:underline dark:text-emerald-400"
                            >
                              <Phone className="h-3 w-3" />
                              <span>{inv.customer_phone}</span>
                            </a>
                          )}
                        </div>

                        {/* PENANGGUNG JAWAB / KOLEKTOR STRIP */}
                        <div className="mt-2.5 flex items-center justify-between gap-2 rounded-xl bg-gray-50/80 dark:bg-gray-800/60 px-2.5 py-1.5 text-[11px]">
                          <span className="text-gray-500 dark:text-gray-400 font-medium">
                            Penanggung Jawab:
                          </span>
                          {inv.processed_by ? (
                            <span className="inline-flex items-center gap-1 font-bold text-emerald-600 dark:text-emerald-400">
                              <User className="h-3 w-3 shrink-0" />
                              <span>{inv.processed_by}</span>
                              <span className="text-[9px] font-normal text-emerald-500">(Lunas)</span>
                            </span>
                          ) : inv.collector_name ? (
                            <span className="inline-flex items-center gap-1 font-bold text-brand-600 dark:text-brand-400">
                              <User className="h-3 w-3 shrink-0" />
                              <span>{inv.collector_name}</span>
                            </span>
                          ) : (
                            <span className="font-medium text-gray-400 italic">
                              Admin / Belum Ditugaskan
                            </span>
                          )}
                        </div>
                      </div>
                    </div>

                    {/* CARD FOOTER ACTIONS */}
                    <div className="mt-4 flex items-center justify-end border-t border-gray-100 pt-3 dark:border-gray-800" onClick={(e) => e.stopPropagation()}>
                      <button
                        type="button"
                        onClick={(e) => {
                          e.stopPropagation()
                          handleOpenManagingModal(inv)
                        }}
                        className="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-brand-500 hover:bg-brand-600 active:scale-95 text-white shadow-xs transition cursor-pointer"
                        title="Kelola Tagihan"
                      >
                        <Settings className="h-4 w-4" />
                      </button>
                    </div>
                  </div>
                )
              })}
            </div>
          )}
        </div>

        {/* FLOATING BATCH SELECTION BAR */}
        {selection.selectedIds.length > 0 && (
          <SelectionFloatingBar
            selectedCount={selection.selectedIds.length}
            totalCount={filteredInvoices.length}
            onSelectAll={() => selection.selectAll(filteredInvoices.map((inv) => inv.id))}
            onDeselectAll={() => selection.deselectAll()}
          >
            <div className="flex flex-wrap items-center gap-1.5 sm:gap-2">
              <button
                type="button"
                onClick={() => setBatchUnisolateConfirmOpen(true)}
                className="inline-flex items-center gap-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 px-3 py-1.5 text-xs font-bold text-white shadow-xs whitespace-nowrap cursor-pointer"
              >
                <Power className="h-3.5 w-3.5" />
                <span>Buka Isolir ({selectedInvoices.length})</span>
              </button>

              <button
                type="button"
                onClick={() => setWaBatchConfirmOpen(true)}
                className="inline-flex items-center gap-1.5 rounded-xl bg-[#052A4E] hover:bg-[#052A4E]/90 dark:bg-brand-500 dark:hover:bg-brand-600 px-3 py-1.5 text-xs font-bold text-white shadow-xs whitespace-nowrap cursor-pointer"
              >
                <Send className="h-3.5 w-3.5" />
                <span>Kirim WA ({selectedInvoices.length})</span>
              </button>

              {selectedPaidInvoices.length > 0 && (
                <button
                  type="button"
                  onClick={() => setBatchCancelConfirmOpen(true)}
                  className="inline-flex items-center gap-1.5 rounded-xl bg-amber-500 hover:bg-amber-600 px-3 py-1.5 text-xs font-bold text-white shadow-xs whitespace-nowrap cursor-pointer"
                >
                  <RotateCcw className="h-3.5 w-3.5" />
                  <span>Reset Belum Bayar ({selectedPaidInvoices.length})</span>
                </button>
              )}

              <button
                type="button"
                onClick={() => setBatchDeleteConfirmOpen(true)}
                className="inline-flex items-center gap-1.5 rounded-xl bg-rose-600 hover:bg-rose-700 px-3 py-1.5 text-xs font-bold text-white shadow-xs whitespace-nowrap cursor-pointer"
              >
                <Trash2 className="h-3.5 w-3.5" />
                <span>Hapus ({selectedInvoices.length})</span>
              </button>

              {selectedInvoices.length > 0 && (
                <button
                  type="button"
                  onClick={() => setBatchPayConfirmOpen(true)}
                  className="inline-flex items-center gap-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 px-3.5 py-1.5 text-xs font-bold text-white shadow-xs whitespace-nowrap cursor-pointer"
                >
                  <Wallet className="h-3.5 w-3.5" />
                  <span>
                    Bayar ({selectedInvoices.length}) • {formatIDR(batchPayTotalAmount)}
                  </span>
                </button>
              )}
            </div>
          </SelectionFloatingBar>
        )}
      </div>

      {/* ========================================================================= */}
      {/* MODAL 1: SINGLE PAY CONFIRMATION                                         */}
      {/* ========================================================================= */}
      {payConfirmModal.open && payConfirmModal.invoice && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-xs">
          <div className="w-full max-w-md overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-2xl dark:border-gray-800 dark:bg-gray-900">
            <div className="flex items-center justify-between border-b border-gray-200 px-5 py-4 dark:border-gray-800">
              <div className="flex items-center gap-2">
                <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-500/10 text-emerald-600 dark:bg-emerald-500/20 dark:text-emerald-400 whitespace-nowrap">
                  <Wallet className="h-4 w-4" />
                </div>
                <h3 className="font-bold text-gray-900 dark:text-white">
                  Konfirmasi Pembayaran
                </h3>
              </div>
              <button
                type="button"
                onClick={() => setPayConfirmModal({ ...payConfirmModal, open: false })}
                className="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"
              >
                <X className="h-4 w-4" />
              </button>
            </div>

            <div className="max-h-[80vh] overflow-y-auto p-5">
              <div className="rounded-xl border border-gray-200 bg-gray-50 p-3.5 dark:border-gray-800 dark:bg-gray-900/50">
                <div className="text-xs text-gray-500 dark:text-gray-400">Pelanggan</div>
                <div className="font-bold text-gray-900 dark:text-white">
                  {payConfirmModal.invoice.customer_name}
                </div>
                <div className="mt-1 flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                  <span>No. {payConfirmModal.invoice.invoice_number}</span>
                  {payConfirmModal.invoice.package_name && (
                    <span>• {payConfirmModal.invoice.package_name}</span>
                  )}
                </div>
              </div>

              {payConfirmModal.allPeriods.length > 1 && (
                <div className="mt-4">
                  <div className="text-xs font-semibold text-gray-700 dark:text-gray-300">
                    Periode yang Dibayar ({payConfirmModal.selectedPeriods.length}):
                  </div>
                  <div className="mt-2 space-y-1.5">
                    {payConfirmModal.allPeriods.map((p) => {
                      const isChecked = payConfirmModal.selectedPeriods.includes(p.period)
                      return (
                        <div
                          key={p.period}
                          className={cn(
                            "flex items-center justify-between rounded-lg border p-2 text-xs",
                            isChecked
                              ? "border-emerald-500/30 bg-emerald-50/50 dark:border-emerald-900/40 dark:bg-emerald-950/20"
                              : "border-gray-200 bg-white opacity-60 dark:border-gray-800 dark:bg-gray-900"
                          )}
                        >
                          <span className="font-semibold text-gray-800 dark:text-gray-200">
                            {periodLabel(p.period)}
                          </span>
                          <span className="font-mono font-bold text-emerald-600 dark:text-emerald-400">
                            {formatIDR(p.amount)}
                          </span>
                        </div>
                      )
                    })}
                  </div>
                </div>
              )}

              <div className="mt-5 flex items-center justify-between rounded-xl bg-brand-50 p-4 dark:bg-brand-950/30">
                <span className="text-xs font-semibold text-brand-800 dark:text-brand-300">
                  Total Diterima
                </span>
                <span className="font-mono text-lg font-black text-brand-700 dark:text-brand-400">
                  {formatIDR(payConfirmModal.amountToPay)}
                </span>
              </div>
            </div>

            <div className="flex items-center justify-end gap-2 border-t border-gray-200 bg-gray-50 px-5 py-3 dark:border-gray-800 dark:bg-gray-900/50">
              <button
                type="button"
                onClick={() => setPayConfirmModal({ ...payConfirmModal, open: false })}
                className="rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-200"
              >
                Batal
              </button>
              <button
                type="button"
                onClick={executePay}
                className="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-4 py-2 text-xs font-bold text-white shadow-xs hover:bg-emerald-700 whitespace-nowrap"
              >
                <Check className="h-4 w-4" />
                <span>Konfirmasi Lunas</span>
              </button>
            </div>
          </div>
        </div>
      )}

      {/* ========================================================================= */}
      {/* MODAL 2: BATCH PAY CONFIRMATION                                          */}
      {/* ========================================================================= */}
      {batchPayConfirmOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-xs">
          <div className="w-full max-w-md overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-2xl dark:border-gray-800 dark:bg-gray-900">
            <div className="flex items-center justify-between border-b border-gray-200 px-5 py-4 dark:border-gray-800">
              <div className="flex items-center gap-2">
                <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-500/10 text-emerald-600 dark:bg-emerald-500/20 dark:text-emerald-400 whitespace-nowrap">
                  <Wallet className="h-4 w-4" />
                </div>
                <h3 className="font-bold text-gray-900 dark:text-white">
                  Bayar Tagihan Terpilih
                </h3>
              </div>
              <button
                type="button"
                onClick={() => setBatchPayConfirmOpen(false)}
                className="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"
              >
                <X className="h-4 w-4" />
              </button>
            </div>

            <div className="max-h-[80vh] overflow-y-auto p-5">
              <p className="text-xs text-gray-600 dark:text-gray-300">
                Anda akan menandai lunas <b>{selectedInvoices.length}</b> tagihan pelanggan yang dipilih.
              </p>

              <div className="mt-3 max-h-48 space-y-1.5 overflow-y-auto rounded-xl border border-gray-200 bg-gray-50 p-2.5 dark:border-gray-800 dark:bg-gray-900/50">
                {selectedInvoices.map((inv) => (
                  <div
                    key={inv.id}
                    className="flex items-center justify-between text-xs text-gray-700 dark:text-gray-300"
                  >
                    <span>{inv.customer_name}</span>
                    <span className="font-mono font-bold text-emerald-600 dark:text-emerald-400">
                      {formatIDR(inv.amount)}
                    </span>
                  </div>
                ))}
              </div>

              <div className="mt-4 flex items-center justify-between rounded-xl bg-emerald-50 p-4 dark:bg-emerald-950/30">
                <span className="text-xs font-semibold text-emerald-800 dark:text-emerald-300">
                  Total Pembayaran
                </span>
                <span className="font-mono text-lg font-black text-emerald-700 dark:text-emerald-400">
                  {formatIDR(batchPayTotalAmount)}
                </span>
              </div>
            </div>

            <div className="flex items-center justify-end gap-2 border-t border-gray-200 bg-gray-50 px-5 py-3 dark:border-gray-800 dark:bg-gray-900/50">
              <button
                type="button"
                onClick={() => setBatchPayConfirmOpen(false)}
                className="rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-200"
              >
                Batal
              </button>
              <button
                type="button"
                onClick={executeBatchPay}
                className="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-4 py-2 text-xs font-bold text-white shadow-xs hover:bg-emerald-700 whitespace-nowrap cursor-pointer"
              >
                <Check className="h-4 w-4" />
                <span>Bayar {selectedInvoices.length} Tagihan</span>
              </button>
            </div>
          </div>
        </div>
      )}

      {/* ========================================================================= */}
      {/* MODAL 3: MANUAL INVOICE / ADVANCE PAYMENT (BAYAR DI MUKA)                 */}
      {/* ========================================================================= */}
      {advanceModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-xs">
          <div className="w-full max-w-lg overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-2xl dark:border-gray-800 dark:bg-gray-900">
            <div className="flex items-center justify-between border-b border-gray-200 px-5 py-4 dark:border-gray-800">
              <div className="flex items-center gap-2">
                <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-brand-500/10 text-brand-600 dark:bg-brand-500/20 dark:text-brand-400 whitespace-nowrap">
                  <CalendarPlus className="h-4 w-4" />
                </div>
                <h3 className="font-bold text-gray-900 dark:text-white">
                  Buat Tagihan / Bayar di Muka
                </h3>
              </div>
              <button
                type="button"
                onClick={() => setAdvanceModalOpen(false)}
                className="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"
              >
                <X className="h-4 w-4" />
              </button>
            </div>

            <form onSubmit={handleAdvancePaymentSubmit}>
              <div className="max-h-[80vh] space-y-4 overflow-y-auto p-5 text-xs">
                {/* CUSTOMER SEARCH & SELECT */}
                <div className="relative">
                  <label className="mb-1 block font-semibold text-gray-700 dark:text-gray-300">
                    Pilih Pelanggan
                  </label>
                  <input
                    type="text"
                    value={advCustomerSearch}
                    onChange={(e) => {
                      setAdvCustomerSearch(e.target.value)
                      setAdvCustomerDropdownOpen(true)
                    }}
                    onFocus={() => setAdvCustomerDropdownOpen(true)}
                    placeholder="Ketik nama atau kode pelanggan..."
                    className="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs text-gray-900 focus:border-brand-500 focus:outline-hidden dark:border-gray-800 dark:bg-gray-900 dark:text-white"
                  />

                  {advCustomerDropdownOpen && customers.length > 0 && (
                    <div className="absolute left-0 right-0 top-full z-10 mt-1 max-h-48 overflow-y-auto rounded-xl border border-gray-200 bg-white p-1 shadow-lg dark:border-gray-800 dark:bg-gray-900">
                      {customers
                        .filter(
                          (c) =>
                            !advCustomerSearch ||
                            c.name.toLowerCase().includes(advCustomerSearch.toLowerCase()) ||
                            c.customer_code?.toLowerCase().includes(advCustomerSearch.toLowerCase())
                        )
                        .slice(0, 10)
                        .map((c) => (
                          <button
                            key={c.id}
                            type="button"
                            onClick={() => handleSelectCustomerForAdvance(c)}
                            className="flex w-full items-center justify-between rounded-lg p-2 text-start text-xs hover:bg-gray-50 dark:hover:bg-gray-800"
                          >
                            <div>
                              <div className="font-bold text-gray-900 dark:text-white">
                                {c.name}
                              </div>
                              <div className="text-[10px] text-gray-400">
                                {c.customer_code || `#${c.id}`} • {c.package_name || "Tanpa Paket"}
                              </div>
                            </div>
                            <div className="font-mono font-semibold text-emerald-600 dark:text-emerald-400">
                              {formatIDR(c.package_price || 0)}
                            </div>
                          </button>
                        ))}
                    </div>
                  )}
                </div>

                {/* MONTHS COUNT */}
                <div>
                  <label className="mb-1 block font-semibold text-gray-700 dark:text-gray-300">
                    Jumlah Bulan Tagihan
                  </label>
                  <div className="grid grid-cols-5 gap-1.5">
                    {[1, 2, 3, 6, 12].map((num) => (
                      <button
                        key={num}
                        type="button"
                        onClick={() => setAdvMonthsCount(num)}
                        className={cn(
                          "rounded-lg border py-2 text-center font-bold transition-all",
                          advMonthsCount === num
                            ? "border-brand-500 bg-brand-50 text-brand-700 dark:border-brand-400 dark:bg-brand-950/40 dark:text-brand-300"
                            : "border-gray-200 bg-white text-gray-700 hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300"
                        )}
                      >
                        {num} Bln
                      </button>
                    ))}
                  </div>
                </div>

                {/* START PERIOD & NOMINAL PER MONTH */}
                <div className="grid grid-cols-2 gap-3">
                  <div>
                    <label className="mb-1 block font-semibold text-gray-700 dark:text-gray-300">
                      Mulai Periode
                    </label>
                    <input
                      type="month"
                      value={advStartPeriod}
                      onChange={(e) => setAdvStartPeriod(e.target.value)}
                      className="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs text-gray-900 focus:border-brand-500 focus:outline-hidden dark:border-gray-800 dark:bg-gray-900 dark:text-white"
                    />
                  </div>

                  <div>
                    <label className="mb-1 block font-semibold text-gray-700 dark:text-gray-300">
                      Tarif per Bulan (IDR)
                    </label>
                    <input
                      type="number"
                      value={advAmountPerMonth}
                      onChange={(e) => setAdvAmountPerMonth(Number(e.target.value))}
                      className="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs font-semibold text-gray-900 focus:border-brand-500 focus:outline-hidden dark:border-gray-800 dark:bg-gray-900 dark:text-white"
                    />
                  </div>
                </div>

                {/* NOTES */}
                <div>
                  <label className="mb-1 block font-semibold text-gray-700 dark:text-gray-300">
                    Catatan (Opsional)
                  </label>
                  <input
                    type="text"
                    value={advNotes}
                    onChange={(e) => setAdvNotes(e.target.value)}
                    placeholder="Contoh: Promo langganan 3 bulan di muka..."
                    className="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs text-gray-900 focus:border-brand-500 focus:outline-hidden dark:border-gray-800 dark:bg-gray-900 dark:text-white"
                  />
                </div>

                {/* MARK AS PAID TOGGLE */}
                <div className="flex items-center gap-2 rounded-xl border border-gray-200 bg-gray-50 p-3 dark:border-gray-800 dark:bg-gray-900/50">
                  <input
                    type="checkbox"
                    id="advMarkPaid"
                    checked={advMarkPaid}
                    onChange={(e) => setAdvMarkPaid(e.target.checked)}
                    className="h-4 w-4 rounded-md border-gray-300 text-brand-600 focus:ring-brand-500"
                  />
                  <label
                    htmlFor="advMarkPaid"
                    className="cursor-pointer font-semibold text-gray-800 dark:text-gray-200"
                  >
                    Langsung Tandai Lunas (Kuitansi Terbayar)
                  </label>
                </div>

                {/* TOTAL AMOUNT PREVIEW */}
                <div className="flex items-center justify-between rounded-xl bg-brand-50 p-4 dark:bg-brand-950/30">
                  <span className="font-semibold text-brand-800 dark:text-brand-300">
                    Total Nominal ({advMonthsCount} Bulan)
                  </span>
                  <span className="font-mono text-lg font-black text-brand-700 dark:text-brand-400">
                    {formatIDR(advAmountPerMonth * advMonthsCount)}
                  </span>
                </div>
              </div>

              <div className="flex items-center justify-end gap-2 border-t border-gray-200 bg-gray-50 px-5 py-3 dark:border-gray-800 dark:bg-gray-900/50">
                <button
                  type="button"
                  onClick={() => setAdvanceModalOpen(false)}
                  className="rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-200"
                >
                  Batal
                </button>
                <button
                  type="submit"
                  className="inline-flex items-center gap-1.5 rounded-lg bg-brand-600 px-4 py-2 text-xs font-bold text-white shadow-xs hover:bg-brand-700 dark:bg-brand-500 whitespace-nowrap"
                >
                  <Check className="h-4 w-4" />
                  <span>Simpan Tagihan</span>
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* ========================================================================= */}
      {/* MODAL 4: WHATSAPP SINGLE MODAL                                           */}
      {/* ========================================================================= */}
      {waModalInvoice && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-xs">
          <div className="w-full max-w-md overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-2xl dark:border-gray-800 dark:bg-gray-900">
            <div className="flex items-center justify-between border-b border-gray-200 px-5 py-4 dark:border-gray-800">
              <div className="flex items-center gap-2">
                <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-500/10 text-emerald-600 dark:bg-emerald-500/20 dark:text-emerald-400 whitespace-nowrap">
                  <MessageCircle className="h-4 w-4" />
                </div>
                <h3 className="font-bold text-gray-900 dark:text-white">
                  {(waModalInvoice.paid || waModalInvoice.status === "paid")
                    ? "Kirim Kuitansi / Bukti Pembayaran"
                    : "Kirim Pengingat Tagihan"}
                </h3>
              </div>
              <button
                type="button"
                onClick={() => setWaModalInvoice(null)}
                className="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"
              >
                <X className="h-4 w-4" />
              </button>
            </div>

            <div className="space-y-4 p-5 text-xs">
              <div className="rounded-xl border border-gray-200 bg-gray-50 p-3 dark:border-gray-800 dark:bg-gray-900/50">
                <div className="flex items-center justify-between">
                  <span className="font-bold text-gray-900 dark:text-white">
                    {waModalInvoice.customer_name}
                  </span>
                  {(waModalInvoice.paid || waModalInvoice.status === "paid") ? (
                    <span className="inline-flex items-center rounded-md bg-emerald-500 px-2 py-0.5 text-[10px] font-bold text-white uppercase tracking-wider">
                      LUNAS
                    </span>
                  ) : (
                    <span className="inline-flex items-center rounded-md bg-amber-500 px-2 py-0.5 text-[10px] font-bold text-white uppercase tracking-wider">
                      BELUM LUNAS
                    </span>
                  )}
                </div>
                <div className="text-gray-500 dark:text-gray-400 mt-0.5">
                  {waModalInvoice.customer_phone || "Tanpa No. HP"} • {waModalInvoice.invoice_number}
                </div>
                <div className="mt-2 font-mono font-bold text-emerald-600 dark:text-emerald-400">
                  Tagihan: {formatIDR(waModalInvoice.amount)} ({periodLabel(displayPeriod(waModalInvoice))})
                </div>
              </div>

              {waStatusMsg && (
                <div
                  className={cn(
                    "rounded-xl border p-3",
                    waStatusMsg.type === "success"
                      ? "border-emerald-500/30 bg-emerald-50 text-emerald-800 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-300"
                      : "border-rose-500/30 bg-rose-50 text-rose-800 dark:border-rose-900/40 dark:bg-rose-950/30 dark:text-rose-300"
                  )}
                >
                  {waStatusMsg.text}
                </div>
              )}

              <div className="space-y-2">
                <button
                  type="button"
                  onClick={handleSendWaGateway}
                  disabled={waSending}
                  className="flex w-full items-center justify-center gap-2 rounded-xl bg-emerald-600 py-2.5 font-bold text-white shadow-xs hover:bg-emerald-700 disabled:opacity-50"
                >
                  {waSending ? (
                    <Loader2 className="h-4 w-4 animate-spin" />
                  ) : (
                    <Send className="h-4 w-4" />
                  )}
                  <span>
                    {(waModalInvoice.paid || waModalInvoice.status === "paid")
                      ? "Kirim Kuitansi Lunas via WA Gateway"
                      : "Kirim Pengingat Tagihan via WA Gateway"}
                  </span>
                </button>

                <a
                  href={getWaManualUrl(waModalInvoice)}
                  target="_blank"
                  rel="noreferrer"
                  className="flex w-full items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white py-2.5 font-semibold text-gray-700 shadow-2xs hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800"
                >
                  <ArrowUpRight className="h-4 w-4 text-emerald-500" />
                  <span>
                    {(waModalInvoice.paid || waModalInvoice.status === "paid")
                      ? "Buka WhatsApp (Kuitansi Lunas Manual)"
                      : "Buka WhatsApp (Pengingat Manual)"}
                  </span>
                </a>
              </div>
            </div>

            <div className="flex justify-end border-t border-gray-200 bg-gray-50 px-5 py-3 dark:border-gray-800 dark:bg-gray-900/50">
              <button
                type="button"
                onClick={() => setWaModalInvoice(null)}
                className="rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-200"
              >
                Tutup
              </button>
            </div>
          </div>
        </div>
      )}

      {/* ========================================================================= */}
      {/* MODAL 5: WHATSAPP BATCH MODAL                                            */}
      {/* ========================================================================= */}
      {waBatchConfirmOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-xs">
          <div className="w-full max-w-md overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-2xl dark:border-gray-800 dark:bg-gray-900">
            <div className="flex items-center justify-between border-b border-gray-200 px-5 py-4 dark:border-gray-800">
              <div className="flex items-center gap-2">
                <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-500/10 text-emerald-600 dark:bg-emerald-500/20 dark:text-emerald-400 whitespace-nowrap">
                  <Send className="h-4 w-4" />
                </div>
                <h3 className="font-bold text-gray-900 dark:text-white">
                  Kirim WhatsApp Massal
                </h3>
              </div>
              <button
                type="button"
                onClick={() => setWaBatchConfirmOpen(false)}
                className="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"
              >
                <X className="h-4 w-4" />
              </button>
            </div>

            <div className="p-5 text-xs">
              <p className="text-gray-600 dark:text-gray-300">
                Kirim pesan WhatsApp otomatis ke <b>{selectedInvoices.length}</b> pelanggan terpilih melalui antrean Gateway?
              </p>
            </div>

            <div className="flex items-center justify-end gap-2 border-t border-gray-200 bg-gray-50 px-5 py-3 dark:border-gray-800 dark:bg-gray-900/50">
              <button
                type="button"
                onClick={() => setWaBatchConfirmOpen(false)}
                className="rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-200"
              >
                Batal
              </button>
              <button
                type="button"
                onClick={executeBatchSendWa}
                className="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-4 py-2 text-xs font-bold text-white shadow-xs hover:bg-emerald-700 whitespace-nowrap"
              >
                <Send className="h-4 w-4" />
                <span>Kirim Sekarang</span>
              </button>
            </div>
          </div>
        </div>
      )}

      {/* ========================================================================= */}
      {/* MODAL 6: AUTO INVOICE CONFIG & GENERATION                                */}
      {/* ========================================================================= */}
      {autoInvoiceModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-xs">
          <div className="w-full max-w-md overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-2xl dark:border-gray-800 dark:bg-gray-900">
            <div className="flex items-center justify-between border-b border-gray-200 px-5 py-3.5 dark:border-gray-800">
              <div className="flex items-center gap-2.5">
                <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-brand-500 text-white shadow-xs whitespace-nowrap">
                  <SlidersHorizontal className="h-4 w-4" />
                </div>
                <div>
                  <h3 className="font-bold text-sm text-gray-900 dark:text-white">
                    Pengaturan Auto Tagihan
                  </h3>
                  <p className="text-[11px] text-gray-500 dark:text-gray-400">
                    Otomasi terbit tagihan &amp; notifikasi WhatsApp
                  </p>
                </div>
              </div>
              <button
                type="button"
                onClick={() => setAutoInvoiceModalOpen(false)}
                className="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 cursor-pointer"
              >
                <X className="h-4 w-4" />
              </button>
            </div>

            <div className="max-h-[75vh] space-y-3.5 overflow-y-auto p-4 text-xs">
              {/* STATUS SUMMARY BANNER WITH SOLID BADGES */}
              <div className="rounded-xl border border-gray-200 bg-gray-50/70 p-3 dark:border-gray-800 dark:bg-gray-900/50 space-y-2">
                <div className="flex items-center justify-between text-xs">
                  <span className="font-medium text-gray-700 dark:text-gray-300">Pelanggan Aktif</span>
                  <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold bg-brand-500 text-white shadow-xs">
                    {autoInvoiceConfig.active_customers_count ?? customers.length} Pelanggan
                  </span>
                </div>
                <div className="flex items-center justify-between text-xs border-t border-gray-200/60 dark:border-gray-800/60 pt-2">
                  <span className="font-medium text-gray-700 dark:text-gray-300">Tagihan Bulan Ini Terbit</span>
                  <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold bg-emerald-500 text-white shadow-xs">
                    {autoInvoiceConfig.current_month_invoices_count ?? 0} Tagihan
                  </span>
                </div>
              </div>

              {/* TOGGLES CONTAINER */}
              <div className="space-y-3 rounded-xl border border-gray-200 bg-gray-50/50 p-3.5 dark:border-gray-800 dark:bg-gray-900/40">
                <div className="flex items-center justify-between gap-3">
                  <div>
                    <div className="font-bold text-gray-900 dark:text-white text-xs">
                      Auto-Generate Invoice Tiap Bulan
                    </div>
                    <div className="text-[11px] text-gray-500 dark:text-gray-400">
                      Otomatis menerbitkan tagihan bulanan pelanggan aktif
                    </div>
                  </div>
                  <Switch
                    checked={autoGenInvoice}
                    onChange={(checked) => setAutoGenInvoice(checked)}
                  />
                </div>

                {autoGenInvoice && (
                  <div className="border-t border-gray-200/70 dark:border-gray-800/70 pt-2.5">
                    <label className="mb-1 block font-semibold text-gray-700 dark:text-gray-300 text-[11px]">
                      Tanggal Terbit Tagihan (1 - 28)
                    </label>
                    <input
                      type="number"
                      min={1}
                      max={28}
                      value={autoGenDay}
                      onChange={(e) => setAutoGenDay(Number(e.target.value))}
                      className="w-24 rounded-lg border border-gray-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-gray-900 focus:border-brand-500 focus:outline-hidden dark:border-gray-800 dark:bg-gray-900 dark:text-white"
                    />
                  </div>
                )}
              </div>

              <div className="space-y-3 rounded-xl border border-gray-200 bg-gray-50/50 p-3.5 dark:border-gray-800 dark:bg-gray-900/40">
                <div className="flex items-center justify-between gap-3">
                  <div>
                    <div className="font-bold text-gray-900 dark:text-white text-xs">
                      Notifikasi WA Saat Tagihan Terbit
                    </div>
                    <div className="text-[11px] text-gray-500 dark:text-gray-400">
                      Kirim rincian invoice otomatis ke nomor WhatsApp pelanggan
                    </div>
                  </div>
                  <Switch
                    checked={autoWaCreated}
                    onChange={(checked) => setAutoWaCreated(checked)}
                  />
                </div>

                <div className="flex items-center justify-between gap-3 border-t border-gray-200/70 pt-2.5 dark:border-gray-800/70">
                  <div>
                    <div className="font-bold text-gray-900 dark:text-white text-xs">
                      Pengingat Jatuh Tempo Otomatis
                    </div>
                    <div className="text-[11px] text-gray-500 dark:text-gray-400">
                      Kirim reminder sebelum &amp; saat tanggal jatuh tempo
                    </div>
                  </div>
                  <Switch
                    checked={autoWaReminder}
                    onChange={(checked) => setAutoWaReminder(checked)}
                  />
                </div>
              </div>
            </div>

            <div className="flex items-center justify-end gap-2 border-t border-gray-200 bg-gray-50/80 px-4 py-3 dark:border-gray-800 dark:bg-gray-900/80">
              <button
                type="button"
                onClick={() => setAutoInvoiceModalOpen(false)}
                className="inline-flex h-8.5 items-center px-3.5 rounded-lg border border-gray-200 bg-white text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-200 transition cursor-pointer"
              >
                Batal
              </button>
              <button
                type="button"
                onClick={handleSaveAutoInvoiceConfig}
                disabled={savingAutoConfig}
                className="inline-flex h-8.5 items-center gap-1.5 rounded-lg bg-brand-500 px-3.5 text-xs font-bold text-white shadow-xs hover:bg-brand-600 disabled:opacity-50 transition cursor-pointer whitespace-nowrap"
              >
                {savingAutoConfig && <Loader2 className="h-3.5 w-3.5 animate-spin" />}
                <span>Simpan Pengaturan</span>
              </button>
            </div>
          </div>
        </div>
      )}

      {/* ========================================================================= */}
      {/* MODAL 7: RESET INVOICES                                                  */}
      {/* ========================================================================= */}
      {resetModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-xs">
          <div className="w-full max-w-md overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-2xl dark:border-gray-800 dark:bg-gray-900">
            <div className="flex items-center justify-between border-b border-gray-200 px-5 py-4 dark:border-gray-800">
              <div className="flex items-center gap-2">
                <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-rose-500/10 text-rose-600 dark:bg-rose-500/20 dark:text-rose-400 whitespace-nowrap">
                  <RotateCcw className="h-4 w-4" />
                </div>
                <h3 className="font-bold text-gray-900 dark:text-white">
                  Reset Tagihan per Periode
                </h3>
              </div>
              <button
                type="button"
                onClick={() => setResetModalOpen(false)}
                className="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"
              >
                <X className="h-4 w-4" />
              </button>
            </div>

            <form onSubmit={handleResetInvoicesSubmit}>
              <div className="space-y-4 p-5 text-xs">
                <div className="rounded-xl border border-rose-200 bg-rose-50 p-3 dark:border-rose-900/40 dark:bg-rose-950/20">
                  <div className="font-bold text-rose-900 dark:text-rose-300">
                    Peringatan Tindakan Destruktif
                  </div>
                  <div className="mt-0.5 text-rose-700 dark:text-rose-400">
                    Reset tagihan akan menghapus atau membatalkan invoice pada periode yang dipilih.
                  </div>
                </div>

                <div>
                  <label className="mb-1 block font-semibold text-gray-700 dark:text-gray-300">
                    Pilih Periode
                  </label>
                  <select
                    value={resetPeriod}
                    onChange={(e) => setResetPeriod(e.target.value)}
                    className="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs font-semibold text-gray-900 focus:border-brand-500 focus:outline-hidden dark:border-gray-800 dark:bg-gray-900 dark:text-white"
                  >
                    <option value="">-- Pilih Periode --</option>
                    {availablePeriods.map((p) => (
                      <option key={p} value={p}>
                        {periodLabel(p)} ({p})
                      </option>
                    ))}
                  </select>
                </div>

                <div>
                  <label className="mb-1 block font-semibold text-gray-700 dark:text-gray-300">
                    Cakupan Reset
                  </label>
                  <div className="space-y-2">
                    <label className="flex items-center gap-2 rounded-lg border border-gray-200 p-2.5 dark:border-gray-800">
                      <input
                        type="radio"
                        name="resetScope"
                        value="pending"
                        checked={resetScope === "pending"}
                        onChange={() => setResetScope("pending")}
                        className="h-4 w-4 text-brand-600"
                      />
                      <div>
                        <div className="font-semibold text-gray-900 dark:text-white">
                          Hanya Tagihan Belum Lunas
                        </div>
                        <div className="text-[10px] text-gray-400">
                          Hanya menghapus invoice yang belum terbayar
                        </div>
                      </div>
                    </label>

                    <label className="flex items-center gap-2 rounded-lg border border-gray-200 p-2.5 dark:border-gray-800">
                      <input
                        type="radio"
                        name="resetScope"
                        value="all"
                        checked={resetScope === "all"}
                        onChange={() => setResetScope("all")}
                        className="h-4 w-4 text-rose-600"
                      />
                      <div>
                        <div className="font-semibold text-rose-600 dark:text-rose-400">
                          Semua Tagihan (Termasuk yang Lunas)
                        </div>
                        <div className="text-[10px] text-gray-400">
                          Menghapus seluruh invoice di periode ini
                        </div>
                      </div>
                    </label>
                  </div>
                </div>
              </div>

              <div className="flex items-center justify-end gap-2 border-t border-gray-200 bg-gray-50 px-5 py-3 dark:border-gray-800 dark:bg-gray-900/50">
                <button
                  type="button"
                  onClick={() => setResetModalOpen(false)}
                  className="rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-200"
                >
                  Batal
                </button>
                <button
                  type="submit"
                  className="inline-flex items-center gap-1.5 rounded-lg bg-rose-600 px-4 py-2 text-xs font-bold text-white shadow-xs hover:bg-rose-700 whitespace-nowrap"
                >
                  <Trash2 className="h-4 w-4" />
                  <span>Reset Tagihan Sekarang</span>
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* ========================================================================= */}
      {/* MODAL 8: CALENDAR & PERIOD / DATE PICKER                                  */}
      {/* ========================================================================= */}
      {calendarOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-xs">
          <div className="w-full max-w-md overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-2xl dark:border-gray-800 dark:bg-gray-900">
            {/* Header */}
            <div className="flex items-center justify-between border-b border-gray-200 px-5 py-3.5 dark:border-gray-800">
              <div className="flex items-center gap-2.5">
                <div className="flex h-9 w-9 items-center justify-center rounded-xl bg-brand-500 text-white shadow-xs">
                  <Calendar className="h-4 w-4" />
                </div>
                <div>
                  <h3 className="font-bold text-gray-900 dark:text-white text-sm">
                    Filter Waktu Tagihan
                  </h3>
                  <p className="text-[11px] text-gray-500 dark:text-gray-400">
                    Pilih berdasarkan periode bulan atau tanggal spesifik
                  </p>
                </div>
              </div>
              <button
                type="button"
                onClick={() => setCalendarOpen(false)}
                className="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 transition cursor-pointer"
              >
                <X className="h-4 w-4" />
              </button>
            </div>

            {/* Sub-Nav Tabs */}
            <div className="flex border-b border-gray-100 dark:border-gray-800 bg-gray-50/70 dark:bg-gray-900/50 p-1.5 gap-1.5">
              <button
                type="button"
                onClick={() => setCalendarTab("month")}
                className={cn(
                  "flex-1 py-1.5 text-xs font-bold rounded-lg transition text-center cursor-pointer",
                  calendarTab === "month"
                    ? "bg-white dark:bg-gray-800 text-brand-600 dark:text-brand-400 shadow-2xs"
                    : "text-gray-500 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-200"
                )}
              >
                Pilihan Bulan
              </button>
              <button
                type="button"
                onClick={() => setCalendarTab("date")}
                className={cn(
                  "flex-1 py-1.5 text-xs font-bold rounded-lg transition text-center cursor-pointer",
                  calendarTab === "date"
                    ? "bg-white dark:bg-gray-800 text-brand-600 dark:text-brand-400 shadow-2xs"
                    : "text-gray-500 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-200"
                )}
              >
                Pilihan Tanggal
              </button>
            </div>

            <div className="p-5 text-xs">
              {calendarTab === "month" ? (
                <>
                  {/* YEAR STEPPER */}
                  <div className="flex items-center justify-between rounded-xl border border-gray-200 bg-gray-50 p-2 dark:border-gray-800 dark:bg-gray-900">
                    <button
                      type="button"
                      onClick={() => setCalendarYear((y) => y - 1)}
                      className="flex h-7 w-7 items-center justify-center rounded-lg hover:bg-white dark:hover:bg-gray-800 cursor-pointer"
                    >
                      <ChevronLeft className="h-4 w-4" />
                    </button>
                    <span className="font-bold text-gray-900 dark:text-white">
                      Tahun {calendarYear}
                    </span>
                    <button
                      type="button"
                      onClick={() => setCalendarYear((y) => y + 1)}
                      className="flex h-7 w-7 items-center justify-center rounded-lg hover:bg-white dark:hover:bg-gray-800 cursor-pointer"
                    >
                      <ChevronRight className="h-4 w-4" />
                    </button>
                  </div>

                  {/* MONTHS GRID */}
                  <div className="mt-4 grid grid-cols-3 gap-2">
                    {MONTH_NAMES.map((mName, idx) => {
                      const mStr = `${calendarYear}-${String(idx + 1).padStart(2, "0")}`
                      const isSelected = selectedPeriod === mStr && !selectedDate

                      return (
                        <button
                          key={mStr}
                          type="button"
                          onClick={() => handleSelectPeriod(mStr)}
                          className={cn(
                            "rounded-xl border p-3 text-center transition-all cursor-pointer",
                            isSelected
                              ? "border-brand-500 bg-brand-500 text-white shadow-xs font-bold"
                              : "border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800"
                          )}
                        >
                          <div className="font-bold">{mName}</div>
                          <div className={cn("text-[10px]", isSelected ? "text-white/80" : "text-gray-400")}>{calendarYear}</div>
                        </button>
                      )
                    })}
                  </div>

                  {/* QUICK BUTTON: ALL PERIODS */}
                  <div className="mt-4 border-t border-gray-100 pt-3 dark:border-gray-800">
                    <button
                      type="button"
                      onClick={() => handleSelectPeriod(null)}
                      className="flex w-full items-center justify-center gap-1.5 rounded-xl border border-gray-200 bg-gray-50 py-2.5 font-semibold text-gray-700 hover:bg-gray-100 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-200 cursor-pointer"
                    >
                      <span>Tampilkan Semua Bulan (Semua Waktu)</span>
                    </button>
                  </div>
                </>
              ) : (
                <div className="space-y-4">
                  {/* DAY 1-31 NUMBER GRID */}
                  <div>
                    <div className="flex items-center justify-between mb-2">
                      <label className="text-gray-700 dark:text-gray-300 font-semibold text-xs">
                        Pilih Tanggal Tagihan / Jatuh Tempo (1 - 31)
                      </label>
                      {selectedDay && selectedDay !== "all" && (
                        <span className="text-[11px] font-bold text-brand-600 dark:text-brand-400">
                          Tanggal {selectedDay} Aktif
                        </span>
                      )}
                    </div>
                    <div className="grid grid-cols-7 gap-1.5">
                      {Array.from({ length: 31 }, (_, i) => i + 1).map((dNum) => {
                        const isSelected = String(selectedDay) === String(dNum)
                        return (
                          <button
                            key={dNum}
                            type="button"
                            onClick={() => handleSelectDay(dNum)}
                            className={cn(
                              "h-9 rounded-xl border text-center font-bold text-xs transition-all cursor-pointer",
                              isSelected
                                ? "border-brand-500 bg-brand-500 text-white shadow-xs"
                                : "border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800"
                            )}
                          >
                            {dNum}
                          </button>
                        )
                      })}
                    </div>
                  </div>

                  {/* QUICK BUTTON: ALL DAYS */}
                  <div className="border-t border-gray-100 dark:border-gray-800 pt-3">
                    <button
                      type="button"
                      onClick={() => handleSelectDay(null)}
                      className="flex w-full items-center justify-center gap-1.5 rounded-xl border border-gray-200 bg-gray-50 py-2.5 font-semibold text-gray-700 hover:bg-gray-100 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-200 cursor-pointer"
                    >
                      <span>Tampilkan Semua Tanggal (1 - 31)</span>
                    </button>
                  </div>
                </div>
              )}
            </div>
          </div>
        </div>
      )}

      {/* ========================================================================= */}
      {/* MODAL 9: FILTER & SORT ADVANCED MODAL                                    */}
      {/* ========================================================================= */}
      {filterModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-xs">
          <div className="w-full max-w-md overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-2xl dark:border-gray-800 dark:bg-gray-900">
            <div className="flex items-center justify-between border-b border-gray-200 px-5 py-4 dark:border-gray-800">
              <div className="flex items-center gap-2">
                <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-brand-500/10 text-brand-600 dark:bg-brand-500/20 dark:text-brand-400 whitespace-nowrap">
                  <SlidersHorizontal className="h-4 w-4" />
                </div>
                <h3 className="font-bold text-gray-900 dark:text-white">
                  Filter Lanjutan
                </h3>
              </div>
              <button
                type="button"
                onClick={() => setFilterModalOpen(false)}
                className="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"
              >
                <X className="h-4 w-4" />
              </button>
            </div>

            <div className="space-y-4 p-5 text-xs">
              {/* STATUS */}
              <div>
                <label className="mb-1 block font-semibold text-gray-700 dark:text-gray-300">
                  Status Tagihan
                </label>
                <select
                  value={status}
                  onChange={(e) => setStatus(e.target.value as any)}
                  className="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs text-gray-900 focus:border-brand-500 focus:outline-hidden dark:border-gray-800 dark:bg-gray-900 dark:text-white"
                >
                  <option value="all">Semua Status</option>
                  <option value="pending">Belum Bayar</option>
                  <option value="paid">Lunas</option>
                  <option value="overdue">Jatuh Tempo</option>
                  <option value="cancelled">Dibatalkan</option>
                </select>
              </div>

              {/* PAYMENT CHANNEL */}
              <div>
                <label className="mb-1 block font-semibold text-gray-700 dark:text-gray-300">
                  Kanal Pembayaran
                </label>
                <select
                  value={channelFilter}
                  onChange={(e) => setChannelFilter(e.target.value)}
                  className="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs text-gray-900 focus:border-brand-500 focus:outline-hidden dark:border-gray-800 dark:bg-gray-900 dark:text-white"
                >
                  <option value="all">Semua Kanal</option>
                  <option value="manual">Manual Cash</option>
                  <option value="tripay">TriPay (QRIS/VA)</option>
                  <option value="xendit">Xendit</option>
                  <option value="midtrans">Midtrans</option>
                  <option value="duitku">Duitku</option>
                  <option value="winpay">Winpay</option>
                </select>
              </div>

              {/* COLLECTOR FILTER WITH PERFORMANCE STATS */}
              {collectors.length > 0 && (
                <div className="space-y-2">
                  <div className="flex items-center justify-between">
                    <label className="font-semibold text-gray-700 dark:text-gray-300">
                      Petugas / Kolektor
                    </label>
                    {collectorFilter !== "all" && (
                      <button
                        type="button"
                        onClick={() => setCollectorFilter("all")}
                        className="text-[10px] font-bold text-brand-600 dark:text-brand-400 hover:underline cursor-pointer"
                      >
                        Reset Petugas
                      </button>
                    )}
                  </div>

                  <div className="space-y-1.5 max-h-56 overflow-y-auto pr-1">
                    <button
                      type="button"
                      onClick={() => setCollectorFilter("all")}
                      className={cn(
                        "w-full text-left p-2.5 rounded-xl border transition-all flex items-center justify-between gap-2 cursor-pointer",
                        collectorFilter === "all"
                          ? "border-brand-500 bg-brand-50/70 dark:bg-brand-950/40 text-brand-700 dark:text-brand-300 font-bold"
                          : "border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800/60"
                      )}
                    >
                      <div className="flex items-center gap-2">
                        <Users className="h-3.5 w-3.5 text-brand-500" />
                        <span className="text-xs">Semua Petugas &amp; Kolektor</span>
                      </div>
                      <span className="text-[10px] text-gray-400 font-medium">Tampilkan Semua</span>
                    </button>

                    {collectors.map((c) => {
                      const stat = collectorStats?.find(
                        (cs) =>
                          cs.collector_id === c.id ||
                          cs.collector_name === c.name ||
                          (cs.username && cs.username === c.username)
                      )
                      const isSelected =
                        collectorFilter === c.name ||
                        collectorFilter === c.username ||
                        collectorFilter === String(c.id)

                      return (
                        <button
                          key={c.id}
                          type="button"
                          onClick={() => setCollectorFilter(isSelected ? "all" : c.name)}
                          className={cn(
                            "w-full text-left p-2.5 rounded-xl border transition-all flex flex-col gap-1.5 cursor-pointer",
                            isSelected
                              ? "border-brand-500 bg-brand-50/70 dark:bg-brand-950/40 ring-1 ring-brand-500/20"
                              : "border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 hover:bg-gray-50 dark:hover:bg-gray-800/60"
                          )}
                        >
                          <div className="flex items-center justify-between gap-2 w-full">
                            <div className="flex items-center gap-2 min-w-0">
                              <User className={cn("h-3.5 w-3.5 shrink-0", isSelected ? "text-brand-600 dark:text-brand-400" : "text-gray-400")} />
                              <span className={cn("text-xs truncate font-bold", isSelected ? "text-brand-700 dark:text-brand-300" : "text-gray-900 dark:text-white")}>
                                {c.name}
                              </span>
                              {c.username && (
                                <span className="text-[10px] text-gray-400 font-mono">
                                  @{c.username}
                                </span>
                              )}
                            </div>
                            {isSelected && (
                              <span className="px-1.5 py-0.5 rounded text-[9px] font-bold bg-brand-600 text-white">
                                Terpilih
                              </span>
                            )}
                          </div>

                          {/* Kinerja & Tagihan Kolektor */}
                          <div className="grid grid-cols-2 gap-2 pt-1 border-t border-gray-100 dark:border-gray-800/80 text-[10px]">
                            <div className="flex flex-col">
                              <span className="text-emerald-600 dark:text-emerald-400 font-bold">
                                Selesai ({stat?.paid_count ?? 0} invoice)
                              </span>
                              <span className="font-mono text-gray-700 dark:text-gray-300 font-semibold">
                                {formatIDR(stat?.paid_amount ?? 0)}
                              </span>
                            </div>
                            <div className="flex flex-col text-right">
                              <span className="text-amber-600 dark:text-amber-400 font-bold">
                                Tertunda ({stat?.pending_count ?? 0} invoice)
                              </span>
                              <span className="font-mono text-gray-500 dark:text-gray-400">
                                {formatIDR(stat?.pending_amount ?? 0)}
                              </span>
                            </div>
                          </div>
                        </button>
                      )
                    })}

                    <button
                      type="button"
                      onClick={() => setCollectorFilter("unassigned")}
                      className={cn(
                        "w-full text-left p-2.5 rounded-xl border transition-all flex items-center justify-between gap-2 cursor-pointer",
                        collectorFilter === "unassigned"
                          ? "border-brand-500 bg-brand-50/70 dark:bg-brand-950/40 text-brand-700 dark:text-brand-300 font-bold"
                          : "border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800/60"
                      )}
                    >
                      <div className="flex items-center gap-2">
                        <User className="h-3.5 w-3.5 text-gray-400" />
                        <span className="text-xs">Tanpa Kolektor / Admin Langsung</span>
                      </div>
                    </button>
                  </div>
                </div>
              )}
            </div>

            <div className="flex items-center justify-between border-t border-gray-200 bg-gray-50 px-5 py-3 dark:border-gray-800 dark:bg-gray-900/50">
              <button
                type="button"
                onClick={() => {
                  setStatus("all")
                  setChannelFilter("all")
                  setCollectorFilter("all")
                  setFilterModalOpen(false)
                }}
                className="text-xs font-semibold text-gray-500 hover:text-gray-700 dark:hover:text-gray-300"
              >
                Reset Semua
              </button>
              <button
                type="button"
                onClick={() => setFilterModalOpen(false)}
                className="rounded-lg bg-brand-600 px-4 py-2 text-xs font-bold text-white shadow-xs hover:bg-brand-700 dark:bg-brand-500 whitespace-nowrap"
              >
                Terapkan Filter
              </button>
            </div>
          </div>
        </div>
      )}

      {/* ========================================================================= */}
      {/* MODAL 10: BATCH UNISOLATE CONFIRMATION                                    */}
      {/* ========================================================================= */}
      {batchUnisolateConfirmOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-xs">
          <div className="w-full max-w-md overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-2xl dark:border-gray-800 dark:bg-gray-900">
            <div className="flex items-center justify-between border-b border-gray-200 px-5 py-4 dark:border-gray-800">
              <div className="flex items-center gap-2">
                <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-500/10 text-emerald-600 dark:bg-emerald-500/20 dark:text-emerald-400 whitespace-nowrap">
                  <Power className="h-4 w-4" />
                </div>
                <h3 className="font-bold text-gray-900 dark:text-white">
                  Buka Isolir Pelanggan Terpilih
                </h3>
              </div>
              <button
                type="button"
                onClick={() => setBatchUnisolateConfirmOpen(false)}
                className="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"
              >
                <X className="h-4 w-4" />
              </button>
            </div>

            <div className="p-5 text-xs">
              <p className="text-gray-600 dark:text-gray-300">
                Buka isolir dan aktifkan kembali koneksi internet di MikroTik untuk pelanggan dari <b>{selectedInvoices.length}</b> invoice terpilih?
              </p>
            </div>

            <div className="flex items-center justify-end gap-2 border-t border-gray-200 bg-gray-50 px-5 py-3 dark:border-gray-800 dark:bg-gray-900/50">
              <button
                type="button"
                onClick={() => setBatchUnisolateConfirmOpen(false)}
                className="rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-200"
              >
                Batal
              </button>
              <button
                type="button"
                onClick={executeBatchUnisolate}
                className="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-4 py-2 text-xs font-bold text-white shadow-xs hover:bg-emerald-700 whitespace-nowrap"
              >
                <Power className="h-4 w-4" />
                <span>Buka Isolir Sekarang</span>
              </button>
            </div>
          </div>
        </div>
      )}

      {/* ========================================================================= */}
      {/* MODAL 11: BATCH CANCEL (UNPAY) CONFIRMATION                               */}
      {/* ========================================================================= */}
      {batchCancelConfirmOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-xs">
          <div className="w-full max-w-md overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-2xl dark:border-gray-800 dark:bg-gray-900">
            <div className="flex items-center justify-between border-b border-gray-200 px-5 py-4 dark:border-gray-800">
              <div className="flex items-center gap-2">
                <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-amber-500/10 text-amber-600 dark:bg-amber-500/20 dark:text-amber-400 whitespace-nowrap">
                  <RotateCcw className="h-4 w-4" />
                </div>
                <h3 className="font-bold text-gray-900 dark:text-white">
                  Reset Menjadi Belum Bayar
                </h3>
              </div>
              <button
                type="button"
                onClick={() => setBatchCancelConfirmOpen(false)}
                className="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"
              >
                <X className="h-4 w-4" />
              </button>
            </div>

            <div className="p-5 text-xs">
              <p className="text-gray-600 dark:text-gray-300">
                Reset status pembayaran untuk <b>{selectedPaidInvoices.length}</b> invoice lunas terpilih menjadi Belum Bayar?
              </p>
            </div>

            <div className="flex items-center justify-end gap-2 border-t border-gray-200 bg-gray-50 px-5 py-3 dark:border-gray-800 dark:bg-gray-900/50">
              <button
                type="button"
                onClick={() => setBatchCancelConfirmOpen(false)}
                className="rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-200"
              >
                Batal
              </button>
              <button
                type="button"
                onClick={executeBatchCancel}
                className="inline-flex items-center gap-1.5 rounded-lg bg-amber-600 px-4 py-2 text-xs font-bold text-white shadow-xs hover:bg-amber-700 whitespace-nowrap"
              >
                <RotateCcw className="h-4 w-4" />
                <span>Reset ke Belum Bayar</span>
              </button>
            </div>
          </div>
        </div>
      )}

      {/* ========================================================================= */}
      {/* MODAL 12: BATCH DELETE CONFIRMATION                                       */}
      {/* ========================================================================= */}
      {batchDeleteConfirmOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-xs">
          <div className="w-full max-w-md overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-2xl dark:border-gray-800 dark:bg-gray-900">
            <div className="flex items-center justify-between border-b border-gray-200 px-5 py-4 dark:border-gray-800">
              <div className="flex items-center gap-2">
                <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-rose-500/10 text-rose-600 dark:bg-rose-500/20 dark:text-rose-400 whitespace-nowrap">
                  <Trash2 className="h-4 w-4" />
                </div>
                <h3 className="font-bold text-gray-900 dark:text-white">
                  Hapus Invoice Terpilih
                </h3>
              </div>
              <button
                type="button"
                onClick={() => setBatchDeleteConfirmOpen(false)}
                className="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"
              >
                <X className="h-4 w-4" />
              </button>
            </div>

            <div className="p-5 text-xs">
              <p className="text-gray-600 dark:text-gray-300">
                Hapus secara permanen <b>{selectedInvoices.length}</b> invoice terpilih? Tindakan ini tidak dapat dibatalkan.
              </p>
            </div>

            <div className="flex items-center justify-end gap-2 border-t border-gray-200 bg-gray-50 px-5 py-3 dark:border-gray-800 dark:bg-gray-900/50">
              <button
                type="button"
                onClick={() => setBatchDeleteConfirmOpen(false)}
                className="rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-200"
              >
                Batal
              </button>
              <button
                type="button"
                onClick={executeBatchDelete}
                className="inline-flex items-center gap-1.5 rounded-lg bg-rose-600 px-4 py-2 text-xs font-bold text-white shadow-xs hover:bg-rose-700 whitespace-nowrap"
              >
                <Trash2 className="h-4 w-4" />
                <span>Hapus Permanen</span>
              </button>
            </div>
          </div>
        </div>
      )}
    
      {/* ═══════════════════════════════════════════════════════════════════ */}
      {/* POPUP MODAL KELOLA & BAYAR INVOICE (TAILADMIN FLAT CLEAN MODAL)     */}
      {/* ═══════════════════════════════════════════════════════════════════ */}
      {managingInvoice && (
        <div
          className="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-black/60 backdrop-blur-xs animate-in fade-in"
          onClick={() => setManagingInvoice(null)}
        >
          <div
            className="w-full max-w-md rounded-2xl border border-gray-200 bg-white p-5 sm:p-6 shadow-2xl dark:border-gray-800 dark:bg-gray-900 space-y-4 max-h-[92vh] overflow-y-auto"
            onClick={(e) => e.stopPropagation()}
          >
            {/* Modal Header */}
            <div className="flex items-start justify-between border-b border-gray-100 pb-3 dark:border-gray-800">
              <div className="flex items-center gap-3">
                <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-brand-500 text-white font-bold shadow-xs">
                  <Receipt className="h-5 w-5" />
                </div>
                <div>
                  <h3 className="text-sm font-bold text-gray-900 dark:text-white">
                    Detail &amp; Pembayaran Invoice
                  </h3>
                  <p className="font-mono text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                    {managingInvoice.invoice_number}
                  </p>
                </div>
              </div>
              <button
                type="button"
                onClick={() => setManagingInvoice(null)}
                className="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 transition cursor-pointer"
              >
                <X className="h-5 w-5" />
              </button>
            </div>

            {/* Customer Details: Flat Key-Value */}
            <div className="space-y-2 text-xs divide-y divide-gray-100 dark:divide-gray-800">
              <div className="flex items-center justify-between pb-1.5">
                <span className="text-gray-500">Pelanggan</span>
                <span className="font-bold text-gray-900 dark:text-white text-end">
                  {managingInvoice.customer_name} {managingInvoice.customer_code ? `(#${managingInvoice.customer_code})` : ""}
                </span>
              </div>

              <div className="flex items-center justify-between py-1.5">
                <span className="text-gray-500">Paket / Layanan</span>
                <span className="font-semibold text-gray-800 dark:text-gray-200 text-end">
                  {managingInvoice.package_name || "Paket Internet"}
                  {managingInvoice.router_name ? ` • ${managingInvoice.router_name}` : ""}
                </span>
              </div>

              <div className="flex items-center justify-between py-1.5">
                <span className="text-gray-500">Jatuh Tempo</span>
                <span className="font-medium text-gray-800 dark:text-gray-200">
                  {formatDueDate(managingInvoice.due_date)}
                </span>
              </div>

              <div className="flex items-center justify-between py-1.5">
                <span className="text-gray-500">Penanggung Jawab</span>
                <span className="font-semibold text-gray-900 dark:text-white flex items-center gap-1">
                  <User className="h-3.5 w-3.5 text-brand-500" />
                  <span>
                    {managingInvoice.processed_by ||
                      managingInvoice.collector_name ||
                      "Admin / Belum Ditugaskan"}
                  </span>
                  {managingInvoice.processed_by && (
                    <span className="text-[10px] text-emerald-500 font-normal">
                      (Penagih Lunas)
                    </span>
                  )}
                </span>
              </div>

              {managingInvoice.paid_at && (
                <div className="flex items-center justify-between py-1.5">
                  <span className="text-gray-500">Dibayar Pada</span>
                  <span className="font-semibold text-emerald-600 dark:text-emerald-400">
                    {formatDueDate(managingInvoice.paid_at)} ({managingInvoice.payment_channel || "Manual"})
                  </span>
                </div>
              )}
            </div>

            {/* Multi-Period Selection: Flat List */}
            {(() => {
              const allBd = parseBreakdown(managingInvoice.periods_breakdown)
              const isPaid = managingInvoice.status === "paid"

              if (allBd.length > 1 && !isPaid) {
                return (
                  <div className="border-t border-gray-100 dark:border-gray-800 pt-3 space-y-2">
                    <div className="flex items-center justify-between text-xs">
                      <span className="font-bold text-gray-800 dark:text-gray-200">
                        Pilih Periode Tunggakan ({modalSelectedPeriods.length}/{allBd.length})
                      </span>
                      <button
                        type="button"
                        onClick={() => {
                          if (modalSelectedPeriods.length === allBd.length) {
                            setModalSelectedPeriods([allBd[allBd.length - 1].period])
                          } else {
                            setModalSelectedPeriods(allBd.map((p) => p.period))
                          }
                        }}
                        className="text-[11px] font-bold text-brand-600 hover:text-brand-700 dark:text-brand-400 cursor-pointer"
                      >
                        {modalSelectedPeriods.length === allBd.length ? "Pilih Satu" : "Pilih Semua"}
                      </button>
                    </div>

                    <div className="divide-y divide-gray-100 dark:divide-gray-800 border-y border-gray-100 dark:border-gray-800">
                      {allBd.map((p) => {
                        const isChecked = modalSelectedPeriods.includes(p.period)
                        return (
                          <div
                            key={p.period}
                            onClick={() => {
                              if (isChecked) {
                                if (modalSelectedPeriods.length > 1) {
                                  setModalSelectedPeriods(modalSelectedPeriods.filter((item) => item !== p.period))
                                }
                              } else {
                                setModalSelectedPeriods([...modalSelectedPeriods, p.period])
                              }
                            }}
                            className="flex items-center justify-between py-2.5 px-1 cursor-pointer select-none hover:bg-gray-50/50 dark:hover:bg-gray-800/30 transition"
                          >
                            <div className="flex items-center gap-2.5">
                              <Checkbox
                                checked={isChecked}
                                onChange={() => {}}
                              />
                              <span className={cn(
                                "text-xs font-semibold",
                                isChecked ? "text-gray-900 dark:text-white" : "text-gray-400 dark:text-gray-500"
                              )}>
                                {periodLabel(p.period)}
                              </span>
                            </div>
                            <span className="font-mono text-xs font-semibold text-gray-900 dark:text-white">
                              {formatIDR(p.amount)}
                            </span>
                          </div>
                        )
                      })}
                    </div>
                  </div>
                )
              }

              return null
            })()}

            {/* Total Amount & Primary Pay Button */}
            {(() => {
              const allBd = parseBreakdown(managingInvoice.periods_breakdown)
              const isPaid = managingInvoice.status === "paid"
              const dynamicAmount =
                allBd.length > 0 && !isPaid
                  ? allBd
                      .filter((p) => modalSelectedPeriods.includes(p.period))
                      .reduce((sum, p) => sum + p.amount, 0)
                  : Number(managingInvoice.amount || 0)

              return (
                <div className="border-t border-gray-100 dark:border-gray-800 pt-3 flex items-center justify-between gap-3">
                  <div>
                    <span className="text-[11px] text-gray-500 dark:text-gray-400 font-medium block">
                      {allBd.length > 1 && !isPaid ? `Total (${modalSelectedPeriods.length} Periode)` : "Total Tagihan"}
                    </span>
                    <div className="text-lg font-bold text-gray-900 dark:text-white tabular-nums">
                      {formatIDR(dynamicAmount)}
                    </div>
                  </div>

                  {!isPaid ? (
                    <button
                      type="button"
                      onClick={handleExecuteModalPay}
                      className="inline-flex items-center gap-2 h-10 px-5 rounded-xl bg-brand-500 hover:bg-brand-600 active:scale-95 text-xs font-bold text-white shadow-xs transition cursor-pointer"
                    >
                      <span>Bayar {modalSelectedPeriods.length > 1 ? `${modalSelectedPeriods.length} Periode` : "Sekarang"}</span>
                    </button>
                  ) : (
                    <button
                      type="button"
                      onClick={() => {
                        const inv = managingInvoice
                        setManagingInvoice(null)
                        setAdvCustomerId(inv.customer_id)
                        setAdvCustomerSearch(`${inv.customer_name} (${inv.customer_code || `#${inv.customer_id}`})`)
                        setAdvAmountPerMonth(inv.amount || 0)
                        setAdvMonthsCount(1)
                        setAdvanceModalOpen(true)
                      }}
                      className="inline-flex items-center gap-1.5 h-10 px-4 rounded-xl bg-brand-500 hover:bg-brand-600 text-xs font-bold text-white shadow-xs transition cursor-pointer whitespace-nowrap"
                    >
                      <CalendarPlus className="h-4 w-4" />
                      <span>Bayar Dimuka</span>
                    </button>
                  )}
                </div>
              )
            })()}

            {/* Icon-Based Action Buttons Row (Solid Colors) */}
            <div className="flex items-center justify-between border-t border-gray-100 dark:border-gray-800 pt-3">
              <div className="flex items-center gap-2">
                <button
                  type="button"
                  onClick={() => {
                    const inv = managingInvoice
                    setManagingInvoice(null)
                    setWaModalInvoice(inv)
                    setWaStatusMsg(null)
                  }}
                  title="Kirim WhatsApp"
                  className="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white transition shadow-xs cursor-pointer"
                >
                  <MessageCircle className="h-4 w-4" />
                </button>

                <a
                  href={`/receipt/${managingInvoice.invoice_number}`}
                  target="_blank"
                  rel="noreferrer"
                  title="Cetak Struk"
                  className="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-blue-600 hover:bg-blue-700 text-white transition shadow-xs cursor-pointer"
                >
                  <Printer className="h-4 w-4" />
                </a>

                {managingInvoice.status === "paid" && (
                  <button
                    type="button"
                    onClick={() => {
                      const inv = managingInvoice
                      setManagingInvoice(null)
                      handleUnpayInvoice(inv)
                    }}
                    title="Reset Belum Bayar"
                    className="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-amber-500 hover:bg-amber-600 text-white transition shadow-xs cursor-pointer"
                  >
                    <RotateCcw className="h-4 w-4" />
                  </button>
                )}
              </div>

              <button
                type="button"
                onClick={() => {
                  const inv = managingInvoice
                  setManagingInvoice(null)
                  handleDeleteInvoice(inv)
                }}
                title="Hapus Invoice"
                className="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-rose-600 hover:bg-rose-700 text-white transition shadow-xs cursor-pointer"
              >
                <Trash2 className="h-4 w-4" />
              </button>
            </div>
          </div>
        </div>
      )}

    </AppLayout>
  )
}
