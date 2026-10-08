import { AppLayout } from "@/components/layout/app-layout"
import Chart from "react-apexcharts"
import type { ApexOptions } from "apexcharts"
import {
  Users,
  User,
  Plus,
  Search,
  Phone,
  Pencil,
  Trash2,
  Key,
  RefreshCw,
  Download,
  Upload,
  FileSpreadsheet,
  X,
  CalendarClock,
  Receipt,
  WifiOff,
  Power,
  FileUp,
  Wrench,
  ChevronDown,
  Network,
  Radio,
  SlidersHorizontal,
  Check,
  AlertCircle,
  AlertTriangle,
  CheckSquare,
  CheckCircle2,
  RotateCcw,
  MapPin,
  Hash,
  MessageCircle,
  KeyRound,
  Copy,
  Eye,
  EyeOff,
  Info,
  MoreVertical,
  Server,
  ExternalLink,
  Settings,
  Activity,
  TrendingUp,
} from "lucide-react"
import { EmptyState } from "@/components/ui/empty-state"
import { PageProps } from "@/types"
import { adminSidebarItems, adminNavItems, adminBrand } from "@/lib/admin-nav"
import { collectorBrand, getCollectorNavItems, getCollectorSidebarItems } from "@/lib/collector-nav"
import { technicianBrand, getTechnicianNavItems, getTechnicianSidebarItems } from "@/lib/technician-nav"
import { useForm, usePage, router, Link } from "@inertiajs/react"
import { useLanguage } from "@/lib/i18n"
import { SyncRouterDialog } from "@/components/ui/sync-router-dialog"
import { SyncInfoModal } from "@/components/ui/sync-info-modal"
import { SyncOverlay } from "@/components/ui/sync-overlay"
import { ViewModeSwitcher } from "@/components/tailadmin/ui/view-mode-switcher"
import { useCardSelection } from "@/hooks/use-card-selection"
import { SelectionFloatingBar } from "@/components/ui/selection-floating-bar"
import { Checkbox } from "@/components/tailadmin/Checkbox"
import * as React from "react"
import { useState, useMemo, useDeferredValue, useEffect, useRef } from "react"
import { cn, formatIDR } from "@/lib/utils"

interface Customer {
  id: number
  name: string
  code: string | null
  connection_type?: string
  pppoe_username: string | null
  pppoe_password?: string | null
  ip_address?: string | null
  mac_address?: string | null
  arp_interface?: string | null
  auto_arp?: boolean
  phone: string | null
  email: string | null
  address: string | null
  status: string
  isolation_date: string | null
  lat: string | null
  lng: string | null
  package_id: number | null
  package: string | null
  odp_id?: number | null
  odp_port?: number | null
  odp_name?: string | null
  router: string | null
  router_id: number | null
  unpaid_invoices: number
}

interface OdpOption {
  id: number
  name: string
  capacity: number
  used_ports: number
  available_ports: number
}

export default function CustomersPage({
  customers = [],
  packages = [],
  routers = [],
  odps = [],
  technicians = [],
  interfaces = [],
  create = false,
  companyName = "NODERA Billing",
  tenantName,
}: PageProps<{
  customers: Customer[]
  packages: { id: number; name: string; price: number }[]
  routers: { id: number; name: string }[]
  odps?: OdpOption[]
  technicians?: { id: number; name: string }[]
  interfaces?: { name: string; type?: string; running?: boolean }[]
  create: boolean
  companyName?: string
  tenantName?: string
}>) {
  const { t } = useLanguage()

  // View Mode: Table vs Grid, persisted in localStorage
  const [viewMode, setViewMode] = useState<"table" | "grid">(() => {
    if (typeof window !== "undefined") {
      const saved = localStorage.getItem("nodera_customers_view_mode")
      if (saved === "table" || saved === "grid") return saved
    }
    return "table"
  })

  const handleSetViewMode = (mode: "table" | "grid") => {
    setViewMode(mode)
    if (typeof window !== "undefined") {
      localStorage.setItem("nodera_customers_view_mode", mode)
    }
  }

  // Available interface options for ARP dropdown
  const availableInterfaces = useMemo(() => {
    const set = new Set<string>()
    set.add("bridge")
    set.add("ether1")
    set.add("ether2")
    set.add("ether3")
    set.add("ether4")
    set.add("ether5")
    set.add("wlan1")
    set.add("wlan2")
    interfaces.forEach((i) => {
      if (i.name) set.add(i.name)
    })
    customers.forEach((c) => {
      if (c.arp_interface) set.add(c.arp_interface)
    })
    return Array.from(set).filter(Boolean)
  }, [interfaces, customers])

  const [search, setSearch] = useState("")
  const [managingCustomer, setManagingCustomer] = useState<Customer | null>(null)
  const [sheetOpen, setSheetOpen] = useState(create ?? false)
  const [syncOpen, setSyncOpen] = useState(false)
  const [infoModalOpen, setInfoModalOpen] = useState(false)
  const [filterModalOpen, setFilterModalOpen] = useState(false)
  const [editing, setEditing] = useState<Customer | null>(null)
  const [deleteTarget, setDeleteTarget] = useState<Customer | null>(null)
  const [deleteFromMikrotik, setDeleteFromMikrotik] = useState(true)
  const [deleting, setDeleting] = useState(false)
  const [ticketTarget, setTicketTarget] = useState<Customer | null>(null)
  const [excelModalOpen, setExcelModalOpen] = useState(false)
  const [excelModalTab, setExcelModalTab] = useState<"export" | "import">("export")
  const [importFile, setImportFile] = useState<File | null>(null)
  const [importCreatePppoe, setImportCreatePppoe] = useState(true)
  const [importRouterId, setImportRouterId] = useState("")
  const [importing, setImporting] = useState(false)
  const [importResult, setImportResult] = useState<{
    success: boolean
    message: string
    imported?: number
    updated?: number
    skipped?: number
    errors?: string[]
  } | null>(null)

  // Card Selection Hook
  const selection = useCardSelection()

  const form = useForm({
    name: "",
    phone: "",
    connection_type: "pppoe",
    pppoe_username: "",
    pppoe_password: "",
    ip_address: "",
    mac_address: "",
    arp_interface: "bridge",
    create_arp: true,
    router_id: "",
    package_id: "",
    odp_id: "",
    odp_port: "",
    isolation_date: "20",
    email: "",
    address: "",
    lat: "",
    lng: "",
    status: "active",
    create_pppoe: true,
  })

  // Map of occupied ports for currently selected ODP in customer form
  const occupiedPortsForOdp = useMemo(() => {
    if (!form.data.odp_id) return new Map<number, string>()
    const map = new Map<number, string>()
    customers.forEach((c) => {
      if (
        String(c.odp_id) === String(form.data.odp_id) &&
        (!editing || c.id !== editing.id) &&
        c.odp_port
      ) {
        map.set(Number(c.odp_port), c.name)
      }
    })
    return map
  }, [form.data.odp_id, customers, editing])

  const [showPppoePass, setShowPppoePass] = useState(false)
  const [revealedPasswords, setRevealedPasswords] = useState<Record<number, boolean>>({})
  const [copiedCodeId, setCopiedCodeId] = useState<number | string | null>(null)

  const toggleRevealPassword = (id: number) => {
    setRevealedPasswords((prev) => ({ ...prev, [id]: !prev[id] }))
  }

  const handleCopyText = (text: string, id: number | string) => {
    navigator.clipboard?.writeText(text)
    setCopiedCodeId(id)
    setTimeout(() => setCopiedCodeId(null), 1800)
  }

  const ticketForm = useForm({
    title: "",
    customer_id: "",
    assigned_to: "",
    description: "",
    priority: "medium",
    router_id: "",
  })

  // Filter states
  const [statusFilter, setStatusFilter] = useState<string>("all")
  const [connectionTypeFilter, setConnectionTypeFilter] = useState<string>("all")
  const [packageFilter, setPackageFilter] = useState<string>("all")
  const [routerFilter, setRouterFilter] = useState<string>("all")
  const [odpFilter, setOdpFilter] = useState<string>("all")
  const [unpaidFilter, setUnpaidFilter] = useState<boolean>(false)
  const [expandedIds, setExpandedIds] = useState<Record<number, boolean>>({})
  const [syncInfo, setSyncInfo] = useState<{ show: boolean; title?: string; statusMessage?: string } | null>(null)
  const [moreMenuOpen, setMoreMenuOpen] = useState(false)

  const toggleExpand = (id: number) => {
    setExpandedIds((prev) => ({ ...prev, [id]: !prev[id] }))
  }
  const [resetPinModalData, setResetPinModalData] = useState<{ customer: Customer; pin: string } | null>(null)
  const [copiedPin, setCopiedPin] = useState(false)
  const [batchDeleteModalOpen, setBatchDeleteModalOpen] = useState(false)
  const [deleteBatchFromMikrotik, setDeleteBatchFromMikrotik] = useState(false)
  const [batchDeleting, setBatchDeleting] = useState(false)

  const handleResetPin = async (e: React.MouseEvent, c: Customer) => {
    e.stopPropagation()
    setSyncInfo({
      show: true,
      title: "Reset Kode PIN",
      statusMessage: `Membuat PIN baru untuk ${c.name}...`,
    })
    try {
      const res = await fetch(`/admin/billing/customers/reset-pin/${c.id}`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "X-CSRF-TOKEN": (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || "",
          Accept: "application/json",
        },
      })
      const json = await res.json()
      setSyncInfo(null)
      if (json.success && json.pin) {
        setResetPinModalData({
          customer: c,
          pin: json.pin,
        })
      } else {
        alert(json.message || "Kode PIN berhasil direset.")
      }
    } catch {
      setSyncInfo(null)
      alert("Gagal mereset kode PIN.")
    }
  }

  const handleCopyPin = (pin: string) => {
    navigator.clipboard.writeText(pin)
    setCopiedPin(true)
    setTimeout(() => setCopiedPin(false), 2000)
  }

  const handleSendPinWa = (c: Customer, pin: string) => {
    if (!c.phone) return
    const phone = c.phone.replace(/[^0-9]/g, "").replace(/^0/, "62")
    const loginUrl = typeof window !== "undefined" ? `${window.location.origin}/portal/login` : "/portal/login"
    const text = `*INFORMASI AKSES PORTAL PELANGGAN*\n${tenantName || companyName}\n\nHalo *${c.name}*,\n\nKode PIN Portal Pelanggan Anda telah diperbarui :\n\n--------------------------------\n• ID Pelanggan : *${c.code || c.pppoe_username || c.name}*\n• PIN Baru     : *${pin}*\n• Link Login   : ${loginUrl}\n--------------------------------\n\nSilakan gunakan ID Pelanggan / No. WhatsApp dan PIN di atas untuk masuk ke Portal Pelanggan guna mengecek status tagihan, riwayat pembayaran, serta kelola password WiFi Anda.\n\nTerima kasih.`
    window.open(`https://wa.me/${phone}?text=${encodeURIComponent(text)}`, "_blank")
  }

  const handleChatCustomerWa = (e: React.MouseEvent, c: Customer) => {
    e.stopPropagation()
    if (!c.phone) return
    const phone = c.phone.replace(/[^0-9]/g, "").replace(/^0/, "62")
    const loginUrl = typeof window !== "undefined" ? `${window.location.origin}/portal/login` : "/portal/login"
    const text = `*LAYANAN PELANGGAN & AKSES PORTAL*\n${tenantName || companyName}\n\nHalo *${c.name}*,\nBerikut informasi akun dan link akses Portal Pelanggan internet Anda:\n\n--------------------------------\n• ID Pelanggan : *${c.code || c.pppoe_username || c.name}*\n• Layanan/Paket: *${c.package || '-'}*\n• Link Login   : ${loginUrl}\n• PIN Akses    : *123456* (Gunakan PIN ini jika belum pernah diubah)\n--------------------------------\n\nMelalui Portal Pelanggan, Anda dapat:\n✓ Cek status tagihan bulanan & bayar online\n✓ Unduh struk/kwitansi pembayaran resmi\n✓ Lihat riwayat transaksi & ubah sandi WiFi\n\nJika ada kendala atau pertanyaan seputar layanan internet, silakan balas pesan ini.\nTerima kasih telah berlangganan bersama *${tenantName || companyName}*.`
    window.open(`https://wa.me/${phone}?text=${encodeURIComponent(text)}`, "_blank")
  }

  const [batchUnisolateModalOpen, setBatchUnisolateModalOpen] = useState(false)
  const [batchUnisolating, setBatchUnisolating] = useState(false)
  const [batchIsolateModalOpen, setBatchIsolateModalOpen] = useState(false)
  const [batchIsolating, setBatchIsolating] = useState(false)

  const topScrollRef = useRef<HTMLDivElement>(null)
  const tableScrollRef = useRef<HTMLDivElement>(null)
  const [tableScrollWidth, setTableScrollWidth] = useState(1300)
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
  }, [search, statusFilter, routerFilter, odpFilter, packageFilter])

  const selectedCustomers = useMemo(() => {
    if (selection.selectedIds.length === 0) return []
    const selectedSet = new Set(selection.selectedIds)
    return customers.filter((c) => selectedSet.has(c.id))
  }, [selection.selectedIds, customers])

  const hasIsolatedSelected = useMemo(() => {
    return selectedCustomers.some((c) => c.status === "isolated" || c.status === "inactive")
  }, [selectedCustomers])

  const hasActiveSelected = useMemo(() => {
    return selectedCustomers.some((c) => c.status === "active" || (c.status !== "isolated" && c.status !== "inactive"))
  }, [selectedCustomers])

  const selectionActions = useMemo(() => {
    const actions = []

    if (hasIsolatedSelected) {
      actions.push({
        label: "Buka Isolir",
        icon: Power,
        variant: "success" as const,
        iconOnly: true,
        onClick: () => setBatchUnisolateModalOpen(true),
      })
    }

    if (hasActiveSelected) {
      actions.push({
        label: "Isolir",
        icon: WifiOff,
        variant: "warning" as const,
        iconOnly: true,
        onClick: () => setBatchIsolateModalOpen(true),
      })
    }

    actions.push({
      label: "Hapus",
      icon: Trash2,
      variant: "destructive" as const,
      iconOnly: true,
      onClick: () => {
        setDeleteBatchFromMikrotik(false)
        setBatchDeleteModalOpen(true)
      },
    })

    return actions
  }, [hasIsolatedSelected, hasActiveSelected])

  const executeBatchUnisolate = () => {
    if (selection.selectedIds.length === 0) return
    setBatchUnisolating(true)
    setSyncInfo({
      show: true,
      title: "Buka Isolir Pelanggan Terpilih",
      statusMessage: `Mengembalikan profil normal untuk ${selection.selectedIds.length} pelanggan di MikroTik...`,
    })
    router.post(
      "/admin/billing/customers/unisolate-batch",
      { ids: selection.selectedIds },
      {
        preserveScroll: true,
        onFinish: () => {
          setBatchUnisolating(false)
          setSyncInfo(null)
          setBatchUnisolateModalOpen(false)
          selection.deselectAll()
        },
      }
    )
  }

  const executeBatchIsolate = () => {
    if (selection.selectedIds.length === 0) return
    setBatchIsolating(true)
    setSyncInfo({
      show: true,
      title: "Isolir Pelanggan Terpilih",
      statusMessage: `Menerapkan profil isolir untuk ${selection.selectedIds.length} pelanggan di MikroTik...`,
    })
    router.post(
      "/admin/billing/customers/isolate-batch",
      { ids: selection.selectedIds },
      {
        preserveScroll: true,
        onFinish: () => {
          setBatchIsolating(false)
          setSyncInfo(null)
          setBatchIsolateModalOpen(false)
          selection.deselectAll()
        },
      }
    )
  }

  const activeFiltersCount = useMemo(() => {
    let count = 0
    if (statusFilter !== "all") count++
    if (connectionTypeFilter !== "all") count++
    if (packageFilter !== "all") count++
    if (routerFilter !== "all") count++
    if (odpFilter !== "all") count++
    if (unpaidFilter) count++
    return count
  }, [statusFilter, connectionTypeFilter, packageFilter, routerFilter, odpFilter, unpaidFilter])

  const counts = useMemo(() => {
    const total = customers.length
    const active = customers.filter((c) => c.status === "active").length
    const isolated = customers.filter((c) => c.status === "isolated").length
    const inactive = customers.filter((c) => c.status === "inactive").length
    const pppoe = customers.filter((c) => c.connection_type === "pppoe" || (!c.connection_type && !c.ip_address)).length
    const staticIp = customers.filter((c) => c.connection_type === "static" || (!c.pppoe_username && !!c.ip_address)).length
    const unpaid = customers.filter((c) => (c.unpaid_invoices ?? 0) > 0).length
    return { total, active, isolated, inactive, pppoe, staticIp, unpaid }
  }, [customers])

  const activeRate = useMemo(() => {
    if (counts.total === 0) return 100
    return Math.round((counts.active / counts.total) * 100)
  }, [counts])

  const donutOptions: ApexOptions = useMemo(() => ({
    chart: {
      type: "donut",
      fontFamily: "Outfit, Inter, sans-serif",
      background: "transparent",
    },
    labels: ["Aktif", "Terisolir", "Nonaktif"],
    colors: ["#10B981", "#EF4444", "#6B7280"],
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
              label: "AKTIF",
              color: "#94A3B8",
              fontSize: "10px",
              fontWeight: 700,
              formatter: () => `${activeRate}%`,
            },
            value: {
              fontSize: "12px",
              fontWeight: 800,
              color: "#10B981",
              formatter: () => `${counts.active}/${counts.total}`,
            },
          },
        },
      },
    },
    tooltip: {
      theme: "light",
      y: { formatter: (val: number) => `${val} Pelanggan` },
    },
  }), [counts, activeRate])

  const donutSeries = useMemo(() => [
    counts.active || 0,
    counts.isolated || 0,
    counts.inactive || 0,
  ], [counts])

  const deferredSearch = useDeferredValue(search)

  const filtered = useMemo(() => {
    return customers.filter((c) => {
      if (deferredSearch) {
        const q = deferredSearch.toLowerCase()
        const matchName = (c.name ?? "").toLowerCase().includes(q)
        const matchCode = (c.code ?? "").toLowerCase().includes(q)
        const matchIp = (c.ip_address ?? "").toLowerCase().includes(q)
        const matchUser = (c.pppoe_username ?? "").toLowerCase().includes(q)
        const matchPhone = (c.phone ?? "").toLowerCase().includes(q)
        const matchPkg = (c.package ?? "").toLowerCase().includes(q)
        const matchEmail = (c.email ?? "").toLowerCase().includes(q)
        const matchOdp = (c.odp_name ?? "").toLowerCase().includes(q)
        if (!matchName && !matchCode && !matchIp && !matchUser && !matchPhone && !matchPkg && !matchEmail && !matchOdp) {
          return false
        }
      }

      if (statusFilter !== "all" && c.status !== statusFilter) return false

      if (connectionTypeFilter !== "all") {
        const isStatic = c.connection_type === "static" || (!c.pppoe_username && !!c.ip_address)
        const isHotspot = c.connection_type === "hotspot"
        const isPppoe = !isStatic && !isHotspot
        if (connectionTypeFilter === "static" && !isStatic) return false
        if (connectionTypeFilter === "pppoe" && !isPppoe) return false
        if (connectionTypeFilter === "hotspot" && !isHotspot) return false
      }

      if (packageFilter !== "all" && c.package !== packageFilter) return false

      if (routerFilter !== "all" && String(c.router_id) !== String(routerFilter)) return false

      if (odpFilter !== "all" && String(c.odp_id) !== String(odpFilter)) return false

      if (unpaidFilter && (c.unpaid_invoices ?? 0) <= 0) return false

      return true
    }).sort((a, b) => (a.name || "").localeCompare(b.name || "", undefined, { numeric: true, sensitivity: "base" }))
  }, [customers, deferredSearch, statusFilter, connectionTypeFilter, packageFilter, routerFilter, odpFilter, unpaidFilter])

  const [visibleLimit, setVisibleLimit] = useState(60)

  useEffect(() => {
    setVisibleLimit(60)
  }, [search, statusFilter, connectionTypeFilter, packageFilter, routerFilter, odpFilter, unpaidFilter])

  const displayedCustomers = useMemo(() => filtered.slice(0, visibleLimit), [filtered, visibleLimit])

  useEffect(() => {
    const updateWidth = () => {
      if (tableScrollRef.current) {
        setTableScrollWidth(tableScrollRef.current.scrollWidth)
      }
    }
    updateWidth()
    const timer = setTimeout(updateWidth, 100)
    return () => clearTimeout(timer)
  }, [displayedCustomers, filtered])

  const resetFilters = () => {
    setStatusFilter("all")
    setConnectionTypeFilter("all")
    setPackageFilter("all")
    setRouterFilter("all")
    setOdpFilter("all")
    setUnpaidFilter(false)
    setSearch("")
  }

  const openAdd = () => {
    setEditing(null)
    setShowPppoePass(false)
    form.reset()
    form.setData({
      name: "",
      phone: "",
      connection_type: "pppoe",
      pppoe_username: "",
      pppoe_password: "",
      ip_address: "",
      mac_address: "",
      arp_interface: "bridge",
      create_arp: true,
      router_id: "",
      package_id: "",
      odp_id: "",
      odp_port: "",
      isolation_date: "20",
      email: "",
      address: "",
      lat: "",
      lng: "",
      status: "active",
      create_pppoe: true,
    })
    setSheetOpen(true)
  }

  const openEdit = (c: Customer) => {
    setEditing(c)
    setShowPppoePass(false)
    form.setData({
      name: c.name,
      phone: c.phone ?? "",
      connection_type: c.connection_type ?? (c.ip_address && !c.pppoe_username ? "static" : "pppoe"),
      pppoe_username: c.pppoe_username ?? "",
      pppoe_password: c.pppoe_password ?? "",
      ip_address: c.ip_address ?? "",
      mac_address: c.mac_address ?? "",
      arp_interface: c.arp_interface ?? "bridge",
      create_arp: false,
      router_id: c.router_id ? String(c.router_id) : "",
      package_id: c.package_id ? String(c.package_id) : "",
      odp_id: c.odp_id ? String(c.odp_id) : "",
      odp_port: c.odp_port ? String(c.odp_port) : "",
      isolation_date: c.isolation_date ?? "20",
      email: c.email ?? "",
      address: c.address ?? "",
      lat: c.lat ?? "",
      lng: c.lng ?? "",
      status: c.status ?? "active",
      create_pppoe: false,
    })
    setSheetOpen(true)
  }

  const openTicketModal = (c: Customer) => {
    setTicketTarget(c)
    ticketForm.setData({
      title: `Incident - ${c.name}`,
      customer_id: String(c.id),
      assigned_to: "",
      description: "",
      priority: "medium",
      router_id: c.router_id ? String(c.router_id) : "",
    })
  }

  const handleTicketSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    ticketForm.post("/admin/trouble-tickets/add", {
      preserveScroll: true,
      onSuccess: () => {
        setTicketTarget(null)
        ticketForm.reset()
      },
    })
  }

  const submit = (e: React.FormEvent) => {
    e.preventDefault()
    setSyncInfo({
      show: true,
      title: editing ? "Simpan & Sinkronkan Pelanggan" : "Tambah Pelanggan Baru",
      statusMessage: "Sinkronisasi akun PPPoE & binding IP/ARP ke router MikroTik...",
    })
    if (editing) {
      form.post(`/admin/billing/customers/edit/${editing.id}`, {
        preserveScroll: true,
        onSuccess: () => {
          setSheetOpen(false)
        },
        onFinish: () => setSyncInfo(null),
      })
    } else {
      form.post("/admin/billing/customers/add", {
        preserveScroll: true,
        onSuccess: () => {
          setSheetOpen(false)
        },
        onFinish: () => setSyncInfo(null),
      })
    }
  }

  const handleImportSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    if (!importFile || importing) return

    setImporting(true)
    setSyncInfo({
      show: true,
      title: "Import Data Pelanggan",
      statusMessage: "Membaca file Excel & sinkronisasi data pelanggan...",
    })

    const formData = new FormData()
    formData.append("import_file", importFile)
    formData.append("create_pppoe", importCreatePppoe ? "1" : "0")
    if (importRouterId) {
      formData.append("router_id", importRouterId)
    }

    try {
      const csrfToken = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || ""
      const res = await fetch("/admin/billing/customers/import", {
        method: "POST",
        headers: {
          "X-CSRF-TOKEN": csrfToken,
          Accept: "application/json",
        },
        body: formData,
      })

      const data = await res.json()
      setImporting(false)
      setSyncInfo(null)
      if (data.success) {
        setImportResult(data)
        router.reload({ preserveScroll: true })
      } else {
        alert(data.message || "Gagal mengimport data pelanggan.")
      }
    } catch (err: any) {
      setImporting(false)
      setSyncInfo(null)
      alert("Terjadi kesalahan saat mengunggah file.")
    }
  }

  const { url, props: pageProps } = usePage<any>()
  const isCollector = url.startsWith("/kolektor")
  const isTechnician = url.startsWith("/teknisi")
  const brand = isCollector ? collectorBrand : isTechnician ? technicianBrand : adminBrand
  const userPerms = pageProps?.permissions || {}
  const sidebarItems = isCollector
    ? getCollectorSidebarItems(userPerms)
    : isTechnician
    ? getTechnicianSidebarItems(userPerms)
    : adminSidebarItems
  const navItems = isCollector
    ? getCollectorNavItems(userPerms)
    : isTechnician
    ? getTechnicianNavItems(userPerms)
    : adminNavItems
  const dashboardHref = isCollector
    ? "/kolektor/dashboard"
    : isTechnician
    ? "/teknisi/dashboard"
    : "/dashboard"

  const mapHref = isCollector
    ? "/kolektor/map"
    : isTechnician
    ? "/teknisi/map"
    : "/admin/map"

  const handleAddCustomerClick = () => {
    openAdd()
  }

  const isAllDisplayedSelected = useMemo(() => {
    if (displayedCustomers.length === 0) return false
    return displayedCustomers.every((c) => selection.isSelected(c.id))
  }, [displayedCustomers, selection])

  const handleToggleSelectAll = () => {
    if (isAllDisplayedSelected) {
      selection.deselectAll()
    } else {
      selection.selectAll(displayedCustomers.map((c) => c.id))
    }
  }

  return (
    <AppLayout
      title={t("customer.title", "Data Pelanggan")}
      brand={brand}
      sidebarItems={sidebarItems}
      navItems={navItems}
      hideBottomNav={selection.isSelectMode}
    >
      <SyncOverlay />
      <div className="space-y-4 sm:space-y-6 w-full min-w-0 max-w-full">
        {/* Executive Customer Status & Metric Overview (Like Invoice) */}
        <div className="rounded-2xl border border-gray-200 bg-white p-4 sm:p-5 shadow-xs dark:border-gray-800 dark:bg-white/[0.03]">
          <div className="grid grid-cols-1 lg:grid-cols-12 gap-5 items-center">
            {/* Left Metrics (8 Cols) */}
            <div className="lg:col-span-8 space-y-4">
              <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-gray-100 dark:border-gray-800/80 pb-3">
                <div>
                  <h3 className="text-sm font-bold text-gray-900 dark:text-white flex items-center gap-2">
                    <TrendingUp className="h-4 w-4 text-brand-500" />
                    <span>Status &amp; Komposisi Pelanggan</span>
                  </h3>
                  <p className="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                    {counts.total} Total Pelanggan · {counts.pppoe} PPPoE · {counts.staticIp} Static IP{counts.unpaid > 0 ? ` · ${counts.unpaid} Menunggak` : ""}
                  </p>
                </div>
                <div className="flex items-center gap-2">
                  <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-bold bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400">
                    <CheckCircle2 className="h-3.5 w-3.5" />
                    {activeRate}% Pelanggan Aktif
                  </span>
                </div>
              </div>

              {/* Progress Bar */}
              <div className="space-y-1.5">
                <div className="flex justify-between text-xs font-semibold text-gray-600 dark:text-gray-300">
                  <span>Pelanggan Aktif: {counts.active}</span>
                  <span>Total Terdaftar: {counts.total} Pelanggan</span>
                </div>
                <div className="h-2.5 w-full rounded-full bg-gray-100 dark:bg-gray-800 overflow-hidden">
                  <div
                    className="h-full bg-gradient-to-r from-emerald-500 to-teal-400 rounded-full transition-all duration-500"
                    style={{ width: `${Math.min(100, Math.max(0, activeRate))}%` }}
                  />
                </div>
              </div>

              {/* 3 Interactive Metric Buttons */}
              <div className="grid grid-cols-3 gap-2.5 pt-1">
                <button
                  type="button"
                  onClick={() => setStatusFilter(statusFilter === "active" ? "all" : "active")}
                  className={cn(
                    "p-3 rounded-xl border text-left transition cursor-pointer",
                    statusFilter === "active"
                      ? "border-emerald-500 bg-emerald-50/60 dark:bg-emerald-500/10 shadow-2xs"
                      : "border-gray-100 dark:border-gray-800 bg-gray-50/60 dark:bg-gray-900/40 hover:bg-gray-100 dark:hover:bg-gray-800/60"
                  )}
                >
                  <span className="text-[10px] font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider block">Pelanggan Aktif</span>
                  <div className="text-sm sm:text-base font-bold text-emerald-600 dark:text-emerald-400 tabular-nums mt-0.5">
                    {counts.active}
                  </div>
                  <span className="text-[10px] text-gray-400">Online &amp; Berlangganan</span>
                </button>

                <button
                  type="button"
                  onClick={() => setStatusFilter(statusFilter === "isolated" ? "all" : "isolated")}
                  className={cn(
                    "p-3 rounded-xl border text-left transition cursor-pointer",
                    statusFilter === "isolated"
                      ? "border-rose-500 bg-rose-50/60 dark:bg-rose-500/10 shadow-2xs"
                      : "border-gray-100 dark:border-gray-800 bg-gray-50/60 dark:bg-gray-900/40 hover:bg-gray-100 dark:hover:bg-gray-800/60"
                  )}
                >
                  <span className="text-[10px] font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider block">Terisolir</span>
                  <div className="text-sm sm:text-base font-bold text-rose-600 dark:text-rose-400 tabular-nums mt-0.5">
                    {counts.isolated}
                  </div>
                  <span className="text-[10px] text-gray-400">Jatuh tempo / isolir</span>
                </button>

                <button
                  type="button"
                  onClick={() => setStatusFilter("all")}
                  className={cn(
                    "p-3 rounded-xl border text-left transition cursor-pointer",
                    statusFilter === "all"
                      ? "border-brand-500 bg-brand-50/60 dark:bg-brand-500/10 shadow-2xs"
                      : "border-gray-100 dark:border-gray-800 bg-gray-50/60 dark:bg-gray-900/40 hover:bg-gray-100 dark:hover:bg-gray-800/60"
                  )}
                >
                  <span className="text-[10px] font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider block">Total Pelanggan</span>
                  <div className="text-sm sm:text-base font-bold text-gray-900 dark:text-white tabular-nums mt-0.5">
                    {counts.total}
                  </div>
                  <span className="text-[10px] text-gray-400">Semua data terdaftar</span>
                </button>
              </div>
            </div>

            {/* Right Donut Chart (4 Cols) */}
            <div className="lg:col-span-4 flex flex-col items-center justify-center p-2 rounded-xl bg-gray-50/60 dark:bg-gray-900/30 border border-gray-100 dark:border-gray-800/60">
              <span className="text-[11px] font-bold text-gray-400 uppercase tracking-wider mb-1">Distribusi Pelanggan</span>
              {counts.total === 0 ? (
                <div className="h-[160px] flex items-center justify-center text-xs text-gray-400">
                  Tidak ada data pelanggan
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
        <div className="rounded-2xl border border-gray-200 bg-white p-3 sm:p-4 shadow-xs dark:border-gray-800 dark:bg-white/[0.03] flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2.5 sm:gap-3 w-full min-w-0">
          <div className="flex items-center gap-2 flex-1 min-w-0 flex-wrap sm:flex-nowrap">
            {/* Search Bar */}
            <div className="relative flex-1 min-w-[180px]">
              <Search className="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400 dark:text-gray-500" />
              <input
                type="text"
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                placeholder="Cari nama, ID #, IP, PPPoE, telepon..."
                className="h-10 w-full rounded-xl border border-gray-200 bg-white pl-9 pr-8 text-xs font-medium text-gray-900 placeholder:text-gray-400 focus:border-brand-500 focus:outline-none dark:border-gray-800 dark:bg-gray-900 dark:text-white dark:placeholder:text-gray-500 shadow-2xs"
              />
              {search && (
                <button
                  type="button"
                  onClick={() => setSearch("")}
                  className="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 cursor-pointer p-0.5"
                >
                  <X className="h-3.5 w-3.5" />
                </button>
              )}
            </div>

            {/* Router MikroTik Filter Dropdown */}
            {routers.length > 0 && (
              <div className="relative shrink-0">
                <select
                  value={routerFilter}
                  onChange={(e) => setRouterFilter(e.target.value)}
                  className={cn(
                    "h-10 rounded-xl border bg-white px-2.5 sm:px-3 text-xs font-semibold shadow-2xs focus:border-brand-500 focus:outline-hidden dark:bg-gray-900 cursor-pointer max-w-[150px] sm:max-w-[180px] truncate",
                    routerFilter !== "all"
                      ? "border-brand-500 text-brand-600 dark:text-brand-400 font-bold bg-brand-50/40 dark:bg-brand-500/10"
                      : "border-gray-200 text-gray-700 dark:border-gray-800 dark:text-gray-200"
                  )}
                >
                  <option value="all">Semua Router ({routers.length})</option>
                  {routers.map((r) => (
                    <option key={r.id} value={String(r.id)}>
                      {r.name}
                    </option>
                  ))}
                </select>
              </div>
            )}

            {/* Package Filter Dropdown */}
            {packages.length > 0 && (
              <div className="relative shrink-0">
                <select
                  value={packageFilter}
                  onChange={(e) => setPackageFilter(e.target.value)}
                  className={cn(
                    "h-10 rounded-xl border bg-white px-2.5 sm:px-3 text-xs font-semibold shadow-2xs focus:border-brand-500 focus:outline-hidden dark:bg-gray-900 cursor-pointer max-w-[130px] sm:max-w-[150px] truncate",
                    packageFilter !== "all"
                      ? "border-brand-500 text-brand-600 dark:text-brand-400 font-bold bg-brand-50/40 dark:bg-brand-500/10"
                      : "border-gray-200 text-gray-700 dark:border-gray-800 dark:text-gray-200"
                  )}
                >
                  <option value="all">Semua Paket</option>
                  {packages.map((pkg) => (
                    <option key={pkg.id} value={pkg.name}>
                      {pkg.name}
                    </option>
                  ))}
                </select>
              </div>
            )}
          </div>

          <div className="flex items-center gap-1.5 sm:gap-2 justify-end shrink-0">
            {/* ViewModeSwitcher */}
            <ViewModeSwitcher value={viewMode} onChange={handleSetViewMode} size="sm" />

            {/* Tambah Pelanggan Primary Button */}
            <button
              type="button"
              onClick={handleAddCustomerClick}
              className="inline-flex h-10 items-center justify-center gap-1.5 px-3 sm:px-3.5 rounded-xl bg-brand-500 hover:bg-brand-600 active:scale-95 text-xs font-bold text-white shadow-xs transition cursor-pointer shrink-0"
              title="Tambah Pelanggan Baru"
            >
              <Plus className="h-4 w-4" />
              <span className="hidden sm:inline">Tambah</span>
            </button>

            {/* Opsi / Menu Lainnya Center Modal Trigger */}
            <button
              type="button"
              onClick={() => setMoreMenuOpen(true)}
              className={cn(
                "relative inline-flex h-10 w-10 sm:w-auto sm:px-3 items-center justify-center gap-1.5 rounded-xl border bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:hover:bg-gray-800 text-gray-700 dark:text-gray-200 transition shadow-2xs cursor-pointer text-xs font-semibold shrink-0",
                activeFiltersCount > 0 ? "border-brand-500 text-brand-500 font-bold" : "border-gray-200"
              )}
              title="Aksi & Utilitas Pelanggan"
            >
              <SlidersHorizontal className="h-4 w-4" />
              <span className="hidden md:inline">Opsi</span>
              {activeFiltersCount > 0 && (
                <span className="absolute -top-1 -right-1 sm:static flex h-4 w-4 items-center justify-center rounded-full bg-brand-500 text-[10px] font-bold text-white">
                  {activeFiltersCount}
                </span>
              )}
            </button>
          </div>
        </div>

        {/* Master Data Card */}
        <div className="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xs dark:border-gray-800 dark:bg-white/[0.03]">
          {/* Header Bar */}
          <div className="p-4 sm:p-5 border-b border-gray-100 dark:border-gray-800 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
              <h3 className="text-base font-bold text-gray-800 dark:text-white/90">
                Daftar Pelanggan ISP
              </h3>
              <p className="text-xs text-gray-500 dark:text-gray-400">
                {displayedCustomers.length} dari {customers.length} pelanggan ditampilkan
              </p>
            </div>
          </div>

          {/* Active Filter Chips */}
          {activeFiltersCount > 0 && (
            <div className="flex items-center gap-2 flex-wrap text-xs px-1">
              <span className="text-gray-500 dark:text-slate-400 font-medium">Filter Aktif:</span>

              {statusFilter !== "all" && (
                <span className="inline-flex items-center gap-1 rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 px-2.5 py-1 text-[11px] font-semibold text-gray-700 dark:text-slate-300 shadow-xs">
                  Status: <strong className="text-gray-900 dark:text-white capitalize">{statusFilter === "active" ? "Aktif" : statusFilter === "isolated" ? "Terisolir" : statusFilter}</strong>
                  <button onClick={() => setStatusFilter("all")} className="hover:text-rose-500 cursor-pointer">
                    <X className="h-3 w-3 ml-0.5" />
                  </button>
                </span>
              )}

              {connectionTypeFilter !== "all" && (
                <span className="inline-flex items-center gap-1 rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 px-2.5 py-1 text-[11px] font-semibold text-gray-700 dark:text-slate-300 shadow-xs">
                  Koneksi: <strong className="text-gray-900 dark:text-white uppercase">{connectionTypeFilter}</strong>
                  <button onClick={() => setConnectionTypeFilter("all")} className="hover:text-rose-500 cursor-pointer">
                    <X className="h-3 w-3 ml-0.5" />
                  </button>
                </span>
              )}

              {packageFilter !== "all" && (
                <span className="inline-flex items-center gap-1 rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 px-2.5 py-1 text-[11px] font-semibold text-gray-700 dark:text-slate-300 shadow-xs">
                  Paket: <strong className="text-gray-900 dark:text-white">{packageFilter}</strong>
                  <button onClick={() => setPackageFilter("all")} className="hover:text-rose-500 cursor-pointer">
                    <X className="h-3 w-3 ml-0.5" />
                  </button>
                </span>
              )}

              {routerFilter !== "all" && (
                <span className="inline-flex items-center gap-1 rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 px-2.5 py-1 text-[11px] font-semibold text-gray-700 dark:text-slate-300 shadow-xs">
                  Router: <strong className="text-gray-900 dark:text-white">{routers.find((r) => String(r.id) === routerFilter)?.name || routerFilter}</strong>
                  <button onClick={() => setRouterFilter("all")} className="hover:text-rose-500 cursor-pointer">
                    <X className="h-3 w-3 ml-0.5" />
                  </button>
                </span>
              )}

              {odpFilter !== "all" && (
                <span className="inline-flex items-center gap-1 rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 px-2.5 py-1 text-[11px] font-semibold text-gray-700 dark:text-slate-300 shadow-xs">
                  ODP: <strong className="text-gray-900 dark:text-white">{odps.find((o) => String(o.id) === odpFilter)?.name || odpFilter}</strong>
                  <button onClick={() => setOdpFilter("all")} className="hover:text-rose-500 cursor-pointer">
                    <X className="h-3 w-3 ml-0.5" />
                  </button>
                </span>
              )}

              {unpaidFilter && (
                <span className="inline-flex items-center gap-1 rounded-lg border border-rose-200 dark:border-rose-900/40 bg-rose-50 dark:bg-[#240D12] px-2.5 py-1 text-[11px] font-semibold text-rose-700 dark:text-rose-300 shadow-xs whitespace-nowrap">
                  Belum Lunas
                  <button onClick={() => setUnpaidFilter(false)} className="hover:text-rose-900 dark:hover:text-white cursor-pointer">
                    <X className="h-3 w-3 ml-0.5" />
                  </button>
                </span>
              )}

              <button
                onClick={resetFilters}
                className="text-[11px] font-bold text-[#0073C6] dark:text-[#00C2FF] hover:underline ml-1 cursor-pointer"
              >
                Reset Semua
              </button>
            </div>
          )}

          {/* ── PRESENTATION VIEW: TABLE VS GRID ── */}
          {filtered.length === 0 ? (
            <div className="p-10 rounded-2xl border border-dashed border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 text-center shadow-xs">
              <EmptyState
                icon={<Users className="h-9 w-9 text-[#0073C6] dark:text-[#00C2FF]" />}
                title={t("customer.title", "Data Pelanggan")}
                description={
                  search || activeFiltersCount > 0
                    ? "Tidak ada pelanggan yang sesuai dengan kriteria pencarian atau filter yang dipilih."
                    : "Tambahkan pelanggan baru untuk mulai mengelola tagihan dan jaringan Anda."
                }
              />
            </div>
          ) : viewMode === "table" ? (
            /* =========================================================================
               1. TABLE VIEW: HIGH-DENSITY TAILADMIN TABLE (MIKHMON-STYLE WIDE SCROLL)
               ========================================================================= */
            <div className="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xs dark:border-gray-800 dark:bg-white/[0.03] min-w-0 max-w-full">
              {/* ── TOP HORIZONTAL SCROLL RUNWAY (Geser Tabel dari Atas) ── */}
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
                className="overflow-x-auto table-scrollbar w-full"
              >
                <table className="w-full min-w-[1300px] text-left text-xs whitespace-nowrap border-collapse">
                  <thead>
                    <tr className="border-b border-gray-200 dark:border-gray-800 bg-gray-50/75 dark:bg-white/[0.02] text-gray-500 dark:text-gray-400 font-semibold uppercase tracking-wider text-[11px]">
                      <th className="w-10 px-3.5 py-3.5 text-center">
                        <Checkbox
                          checked={isAllDisplayedSelected}
                          onChange={handleToggleSelectAll}
                        />
                      </th>
                      <th className="px-3.5 py-3.5 whitespace-nowrap">ID</th>
                      <th className="px-3.5 py-3.5 whitespace-nowrap">Pelanggan</th>
                      <th className="px-3.5 py-3.5 whitespace-nowrap">Kontak</th>
                      <th className="px-3.5 py-3.5 text-center whitespace-nowrap">Koneksi</th>
                      <th className="px-3.5 py-3.5 whitespace-nowrap">User / IP</th>
                      <th className="px-3.5 py-3.5 whitespace-nowrap">Secret / Password</th>
                      <th className="px-3.5 py-3.5 whitespace-nowrap">Paket</th>
                      <th className="px-3.5 py-3.5 text-center whitespace-nowrap">Tagihan</th>
                      <th className="px-3.5 py-3.5 text-center whitespace-nowrap">Status</th>
                      <th className="px-3.5 py-3.5 whitespace-nowrap">Router / ODP</th>
                      <th className="px-3.5 py-3.5 text-center whitespace-nowrap">Aksi</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-gray-100 dark:divide-gray-800/80">
                    {displayedCustomers.map((c: Customer) => {
                      const isStatic = c.connection_type === "static" || (!c.pppoe_username && !!c.ip_address)
                      const isHotspot = c.connection_type === "hotspot"
                      const isSelected = selection.isSelected(c.id)
                      const isIsolated = c.status === "isolated"
                      const isActive = c.status === "active"
                      const isPassRevealed = Boolean(revealedPasswords[c.id])

                      return (
                        <tr
                          key={c.id}
                          className={cn(
                            "transition-colors duration-150 group align-middle",
                            isSelected
                              ? "bg-blue-50/80 dark:bg-[#0E1A29]"
                              : "even:bg-gray-50/40 dark:even:bg-[#151C28]/30 hover:bg-blue-50/40 dark:hover:bg-[#162030]/60"
                          )}
                        >
                          {/* 1. Checkbox */}
                          <td className="w-10 px-3.5 py-3 text-center align-middle">
                            <Checkbox
                              checked={isSelected}
                              onChange={() => selection.toggleSelect(c.id)}
                            />
                          </td>

                          {/* 2. ID Pelanggan */}
                          <td className="px-3.5 py-3 whitespace-nowrap align-middle">
                            {c.code ? (
                              <button
                                type="button"
                                onClick={() => handleCopyText(c.code!, c.id)}
                                className="inline-flex items-center gap-1 font-mono text-xs font-bold text-gray-700 dark:text-gray-200 hover:text-brand-500 transition-colors cursor-pointer"
                                title="Salin ID Pelanggan"
                              >
                                <span>#{c.code}</span>
                                {copiedCodeId === c.id ? (
                                  <Check className="h-3 w-3 text-emerald-500" />
                                ) : (
                                  <Copy className="h-3 w-3 opacity-50 hover:opacity-100" />
                                )}
                              </button>
                            ) : (
                              <span className="font-mono text-xs text-gray-400">#{c.id}</span>
                            )}
                          </td>

                          {/* 3. Nama Pelanggan & Alamat */}
                          <td className="px-3.5 py-3 whitespace-nowrap align-middle">
                            <div className="min-w-0 max-w-[200px]">
                              <div className="font-bold text-gray-900 dark:text-white truncate">
                                {c.name}
                              </div>
                              {c.address && (
                                <div className="text-[11px] text-gray-500 dark:text-gray-400 truncate mt-0.5" title={c.address}>
                                  {c.address}
                                </div>
                              )}
                            </div>
                          </td>

                          {/* 4. Kontak / No HP */}
                          <td className="px-3.5 py-3 whitespace-nowrap align-middle">
                            {c.phone ? (
                              <button
                                type="button"
                                onClick={(e) => handleChatCustomerWa(e, c)}
                                className="inline-flex items-center gap-1 text-xs font-semibold text-emerald-600 dark:text-emerald-400 hover:underline cursor-pointer"
                                title="Kirim pesan WhatsApp"
                              >
                                <MessageCircle className="h-3.5 w-3.5 shrink-0" />
                                <span>{c.phone}</span>
                              </button>
                            ) : (
                              <span className="text-xs text-gray-400">-</span>
                            )}
                          </td>

                          {/* 5. Koneksi (Tipe Koneksi Badge) */}
                          <td className="px-3.5 py-3 text-center whitespace-nowrap align-middle">
                            <span className={cn(
                              "font-bold text-[10px] uppercase px-2 py-0.5 rounded text-white shadow-2xs inline-block",
                              isStatic
                                ? "bg-purple-600"
                                : isHotspot
                                ? "bg-amber-500"
                                : "bg-brand-500"
                            )}>
                              {isStatic ? "Static" : isHotspot ? "Hotspot" : "PPPoE"}
                            </span>
                          </td>

                          {/* 6. User / IP */}
                          <td className="px-3.5 py-3 whitespace-nowrap align-middle">
                            {c.pppoe_username || c.ip_address ? (
                              <button
                                type="button"
                                onClick={() => handleCopyText(c.pppoe_username || c.ip_address || "", `user_${c.id}`)}
                                className="inline-flex items-center gap-1 font-mono text-xs font-bold text-gray-800 dark:text-gray-200 hover:text-brand-500 transition-colors cursor-pointer"
                                title="Salin User / IP"
                              >
                                <span className="truncate max-w-[130px]">{c.pppoe_username || c.ip_address}</span>
                                {copiedCodeId === `user_${c.id}` ? (
                                  <Check className="h-3 w-3 text-emerald-500 shrink-0" />
                                ) : (
                                  <Copy className="h-3 w-3 opacity-50 hover:opacity-100 shrink-0" />
                                )}
                              </button>
                            ) : (
                              <span className="text-xs text-gray-400">-</span>
                            )}
                          </td>

                          {/* 7. Password Secret */}
                          <td className="px-3.5 py-3 whitespace-nowrap align-middle">
                            {c.pppoe_username ? (
                              <div className="flex items-center gap-1.5 text-xs text-gray-700 dark:text-gray-300 font-mono">
                                <span className="font-semibold">
                                  {isPassRevealed ? c.pppoe_password || "(kosong)" : "••••••••"}
                                </span>
                                <button
                                  type="button"
                                  onClick={() => toggleRevealPassword(c.id)}
                                  className="text-gray-400 hover:text-gray-700 dark:hover:text-white cursor-pointer p-0.5"
                                  title={isPassRevealed ? "Sembunyikan" : "Tampilkan"}
                                >
                                  {isPassRevealed ? <EyeOff className="h-3.5 w-3.5" /> : <Eye className="h-3.5 w-3.5" />}
                                </button>
                                {c.pppoe_password && (
                                  <button
                                    type="button"
                                    onClick={() => handleCopyText(c.pppoe_password!, `pass_${c.id}`)}
                                    className="text-gray-400 hover:text-gray-700 dark:hover:text-white cursor-pointer p-0.5"
                                    title="Salin Password Secret"
                                  >
                                    {copiedCodeId === `pass_${c.id}` ? (
                                      <Check className="h-3.5 w-3.5 text-emerald-500" />
                                    ) : (
                                      <Copy className="h-3.5 w-3.5" />
                                    )}
                                  </button>
                                )}
                              </div>
                            ) : (
                              <span className="text-xs text-gray-400">-</span>
                            )}
                          </td>

                          {/* 8. Paket */}
                          <td className="px-3.5 py-3 whitespace-nowrap align-middle">
                            <div className="space-y-0.5">
                              <div className="font-semibold text-gray-900 dark:text-gray-200 truncate max-w-[140px]">
                                {c.package || "Tanpa Paket"}
                              </div>
                              <div className="text-[11px] text-gray-500 dark:text-gray-400">
                                Tempo: Tgl {c.isolation_date ?? "20"}
                              </div>
                            </div>
                          </td>

                          {/* 9. Tagihan (Solid Badge) */}
                          <td className="px-3.5 py-3 text-center whitespace-nowrap align-middle">
                            {c.unpaid_invoices > 0 ? (
                              <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold bg-rose-500 text-white shadow-xs">
                                {c.unpaid_invoices} {c.unpaid_invoices > 1 ? "Periode" : "Unpaid"}
                              </span>
                            ) : (
                              <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold bg-emerald-500 text-white shadow-xs">
                                Lunas
                              </span>
                            )}
                          </td>

                          {/* 10. Status Badge (100% Solid Standard) */}
                          <td className="px-3.5 py-3 text-center whitespace-nowrap align-middle">
                            {isActive ? (
                              <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold bg-emerald-500 text-white shadow-xs">
                                Aktif
                              </span>
                            ) : isIsolated ? (
                              <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold bg-rose-500 text-white shadow-xs">
                                Terisolir
                              </span>
                            ) : (
                              <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold bg-gray-500 text-white shadow-xs">
                                Nonaktif
                              </span>
                            )}
                          </td>

                          {/* 11. Router / ODP */}
                          <td className="px-3.5 py-3 whitespace-nowrap align-middle">
                            <div className="space-y-0.5">
                              <div className="inline-flex items-center gap-1 rounded-md border border-gray-200 dark:border-gray-800 bg-gray-100 dark:bg-gray-800 px-2 py-0.5 text-[11px] font-medium text-gray-700 dark:text-gray-300 truncate max-w-[140px]">
                                <Server className="h-3 w-3 text-brand-500 shrink-0" />
                                <span className="truncate">{c.router || "Default Router"}</span>
                              </div>
                              {c.odp_name && (
                                <div className="text-[11px] text-amber-600 dark:text-amber-400 font-medium flex items-center gap-1 truncate">
                                  <Radio className="h-3 w-3 shrink-0" />
                                  <span className="truncate max-w-[120px]">{c.odp_name}</span>
                                  {c.odp_port && (
                                    <span className="font-mono font-bold text-[10px] text-amber-500 dark:text-amber-300">
                                      #{c.odp_port}
                                    </span>
                                  )}
                                </div>
                              )}
                            </div>
                          </td>

                          {/* 12. Single Kelola Action (Solid Icon-Only) */}
                          <td className="px-3.5 py-3 text-center whitespace-nowrap align-middle">
                            <button
                              type="button"
                              onClick={() => setManagingCustomer(c)}
                              className="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-brand-500 hover:bg-brand-600 active:scale-95 text-white shadow-xs transition cursor-pointer"
                              title="Kelola Pelanggan"
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
            /* =========================================================================
               2. GRID VIEW: FINTECH CUSTOMER CARDS (NO INITIAL AVATAR)
               ========================================================================= */
            <div className="grid grid-cols-1 items-start gap-3.5 sm:grid-cols-2 lg:grid-cols-3 w-full min-w-0">
              {displayedCustomers.map((c: Customer) => {
                const isStatic = c.connection_type === "static" || (!c.pppoe_username && !!c.ip_address)
                const isHotspot = c.connection_type === "hotspot"
                const isExpanded = !!expandedIds[c.id]
                const isSelected = selection.isSelected(c.id)
                const isIsolated = c.status === "isolated"
                const isActive = c.status === "active"

                return (
                  <div
                    key={c.id}
                    onClick={() => {
                      if (selection.isSelectMode) {
                        selection.toggleSelect(c.id)
                      } else {
                        toggleExpand(c.id)
                      }
                    }}
                    className={cn(
                      "relative w-full min-w-0 max-w-full overflow-hidden rounded-2xl border bg-white dark:bg-gray-900 p-4 sm:p-5 transition-all group cursor-pointer space-y-3.5 shadow-xs",
                      isSelected
                        ? "ring-1 ring-brand-500 bg-blue-50/70 dark:bg-brand-950/20 border-brand-500"
                        : "border-gray-200 dark:border-gray-800 hover:border-gray-300 dark:hover:border-gray-700"
                    )}
                  >
                    {/* Top Header Card */}
                    <div className="flex items-start justify-between gap-2 w-full min-w-0">
                      <div className="min-w-0 flex-1 overflow-hidden">
                        <h4 className="font-bold text-sm text-gray-900 dark:text-white truncate max-w-full group-hover:text-brand-500 transition-colors">
                          {c.name}
                        </h4>

                        {/* Code & Package */}
                        <div className="mt-0.5 flex items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400 truncate">
                          <span className="font-mono text-[11px] font-bold text-gray-700 dark:text-gray-300">
                            #{c.code || c.id}
                          </span>
                          {c.package && (
                            <>
                              <span>•</span>
                              <span className="font-medium text-gray-700 dark:text-gray-300 truncate">
                                {c.package}
                              </span>
                            </>
                          )}
                        </div>
                      </div>

                      {/* Top Right: Status Badge & Chevron / Checkbox */}
                      <div className="flex items-center gap-2 shrink-0">
                        {isActive ? (
                          <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold bg-emerald-500 text-white shadow-xs whitespace-nowrap">
                            Aktif
                          </span>
                        ) : isIsolated ? (
                          <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold bg-rose-500 text-white shadow-xs whitespace-nowrap">
                            Terisolir
                          </span>
                        ) : (
                          <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold bg-gray-500 text-white shadow-xs whitespace-nowrap">
                            Nonaktif
                          </span>
                        )}

                        {selection.isSelectMode ? (
                          <div
                            onClick={(e) => {
                              e.stopPropagation()
                              selection.toggleSelect(c.id)
                            }}
                            className="cursor-pointer"
                          >
                            <Checkbox
                              checked={isSelected}
                              onChange={() => selection.toggleSelect(c.id)}
                            />
                          </div>
                        ) : (
                          <ChevronDown
                            className={cn(
                              "h-4 w-4 text-gray-400 dark:text-gray-500 transition-transform duration-200",
                              isExpanded && "rotate-180 text-gray-700 dark:text-gray-300"
                            )}
                          />
                        )}
                      </div>
                    </div>

                    {/* Network & IP Bar */}
                    <div className="rounded-xl border border-gray-200 dark:border-[#1E2633] bg-gray-50 dark:bg-gray-900/50 px-3 py-2 flex items-center justify-between text-xs gap-2 w-full min-w-0">
                      <div className="flex items-center gap-2 min-w-0 flex-1 overflow-hidden">
                        <span className={cn(
                          "font-bold text-[9px] uppercase px-1.5 py-0.5 rounded shrink-0",
                          isStatic
                            ? "bg-purple-600 text-white"
                            : isHotspot
                            ? "bg-amber-600 text-white"
                            : "bg-blue-600 text-white"
                        )}>
                          {isStatic ? "Static" : isHotspot ? "Hotspot" : "PPPoE"}
                        </span>
                        <span className="font-mono text-xs font-semibold text-gray-800 dark:text-slate-200 truncate">
                          {isStatic ? c.ip_address || "No IP" : c.pppoe_username || "No Username"}
                        </span>
                      </div>

                      <div className="flex items-center gap-1.5 shrink-0">
                        <span className="rounded-md border border-gray-200 dark:border-gray-800 bg-white dark:bg-[#18202C] px-2 py-0.5 text-[10px] font-medium text-gray-700 dark:text-slate-300 truncate max-w-[120px]">
                          {c.router || "Default Router"}
                        </span>
                      </div>
                    </div>

                    {/* Quick Meta Row */}
                    <div className="flex items-center justify-between text-[11px] text-gray-500 dark:text-slate-400 pt-0.5 px-0.5">
                      <div className="flex items-center gap-1.5">
                        <CalendarClock className="h-3.5 w-3.5 text-gray-400 dark:text-slate-500" />
                        <span>Tempo: Tgl {c.isolation_date ?? "20"}</span>
                      </div>

                      {c.unpaid_invoices > 0 ? (
                        <span className="bg-rose-600 text-white font-bold text-[9px] px-1.5 py-0.5 rounded shadow-xs">
                          {c.unpaid_invoices} Tagihan Belum Lunas
                        </span>
                      ) : (
                        <span className="text-emerald-600 dark:text-emerald-400 font-semibold text-[10px]">
                          Tagihan Lunas
                        </span>
                      )}
                    </div>

                    {/* Expanded Details */}
                    {isExpanded && (
                      <div
                        className="pt-3 border-t border-gray-200 dark:border-[#1E2633] space-y-2.5 animate-in fade-in-50 duration-200"
                        onClick={(e) => e.stopPropagation()}
                      >
                        <div className="space-y-1.5 text-xs text-gray-600 dark:text-slate-300">
                          {c.code && (
                            <div className="flex items-center justify-between py-1 border-b border-gray-100 dark:border-[#1E2633]/60 font-mono gap-2">
                              <span className="text-gray-500 dark:text-slate-400 font-sans flex items-center gap-1.5 shrink-0">
                                <Hash className="h-3.5 w-3.5 text-gray-400 dark:text-slate-500" /> ID Pelanggan
                              </span>
                              <span className="font-bold text-gray-900 dark:text-white tracking-wider truncate max-w-[55%] text-right">
                                #{c.code}
                              </span>
                            </div>
                          )}

                          {c.pppoe_username && (
                            <>
                              <div className="flex items-center justify-between py-1 border-b border-gray-100 dark:border-[#1E2633]/60 font-mono gap-2">
                                <span className="text-gray-500 dark:text-slate-400 font-sans flex items-center gap-1.5 shrink-0">
                                  <User className="h-3.5 w-3.5 text-cyan-500" /> Username Secret
                                </span>
                                <span className="font-semibold text-cyan-600 dark:text-cyan-300 tracking-wider text-xs truncate max-w-[55%] text-right">
                                  {c.pppoe_username}
                                </span>
                              </div>

                              <div className="flex items-center justify-between py-1 border-b border-gray-100 dark:border-[#1E2633]/60 font-mono gap-2">
                                <span className="text-gray-500 dark:text-slate-400 font-sans flex items-center gap-1.5 shrink-0">
                                  <KeyRound className="h-3.5 w-3.5 text-amber-500" /> Password Secret
                                </span>
                                <span className="font-semibold text-amber-600 dark:text-amber-300 tracking-wider text-xs truncate max-w-[55%] text-right">
                                  {c.pppoe_password || "(Tanpa password)"}
                                </span>
                              </div>
                            </>
                          )}

                          {/* ODP & Port Info */}
                          <div className="flex items-center justify-between py-1 border-b border-gray-100 dark:border-[#1E2633]/60 gap-2">
                            <span className="text-gray-500 dark:text-slate-400 flex items-center gap-1.5 shrink-0">
                              <Radio className="h-3.5 w-3.5 text-amber-500" /> Kotak ODP &amp; Port
                            </span>
                            {c.odp_name ? (
                              <span className="font-semibold text-amber-600 dark:text-amber-300 flex items-center gap-1.5 truncate max-w-[55%] justify-end">
                                <span className="truncate">{c.odp_name}</span>
                                {c.odp_port && (
                                  <span className="rounded-md border border-amber-500/40 bg-amber-500/10 px-1.5 py-0.5 text-[10px] text-amber-700 dark:text-amber-200 font-mono font-bold shrink-0 whitespace-nowrap">
                                    Port #{c.odp_port}
                                  </span>
                                )}
                              </span>
                            ) : (
                              <span className="text-gray-400 dark:text-slate-500 italic shrink-0">Belum Terhubung</span>
                            )}
                          </div>

                          {c.phone && (
                            <div className="flex items-center justify-between py-1 border-b border-gray-100 dark:border-[#1E2633]/60 gap-2">
                              <span className="text-gray-500 dark:text-slate-400 flex items-center gap-1.5 shrink-0">
                                <Phone className="h-3.5 w-3.5 text-emerald-500" /> WhatsApp
                              </span>
                              <span className="font-medium text-gray-900 dark:text-white truncate max-w-[55%] text-right">{c.phone}</span>
                            </div>
                          )}

                          {c.address && (
                            <div className="flex items-start justify-between py-1 border-b border-gray-100 dark:border-[#1E2633]/60 gap-2">
                              <span className="text-gray-500 dark:text-slate-400 flex items-center gap-1.5 shrink-0">
                                <MapPin className="h-3.5 w-3.5 text-blue-500" /> Alamat
                              </span>
                              <span className="text-right text-[11px] text-gray-700 dark:text-slate-300 ml-2 max-w-[65%] line-clamp-2">{c.address}</span>
                            </div>
                          )}
                        </div>

                        {/* Bottom Actions Cluster */}
                        <div
                          className="pt-2.5 border-t border-gray-100 dark:border-gray-800 flex items-center justify-end"
                          onClick={(e) => e.stopPropagation()}
                        >
                          <button
                            type="button"
                            onClick={(e) => {
                              e.stopPropagation()
                              setManagingCustomer(c)
                            }}
                            className="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-brand-500 hover:bg-brand-600 active:scale-95 text-white shadow-xs transition cursor-pointer"
                            title="Kelola Pelanggan"
                          >
                            <Settings className="h-4 w-4" />
                          </button>
                        </div>
                      
                      </div>
                    )}
                  </div>
                )
              })}
            </div>
          )}

          {/* Pagination / Load More Bar */}
          {filtered.length > visibleLimit && (
            <div className="mt-4 flex flex-col sm:flex-row items-center justify-between gap-3 p-3.5 sm:p-4 rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 shadow-xs">
              <p className="text-xs text-gray-500 dark:text-slate-400 font-medium">
                Menampilkan <span className="text-gray-900 dark:text-white font-bold">{displayedCustomers.length}</span> dari <span className="text-gray-900 dark:text-white font-bold">{filtered.length}</span> pelanggan
              </p>
              <div className="flex items-center gap-2">
                <button
                  type="button"
                  onClick={() => setVisibleLimit((prev) => prev + 60)}
                  className="px-3.5 py-2 rounded-xl border border-[#0073C6]/40 bg-blue-50 dark:bg-[#0B2138] hover:bg-blue-100 dark:hover:bg-[#0073C6]/20 text-[#0073C6] dark:text-[#00C2FF] text-xs font-bold transition-all active:scale-95 cursor-pointer"
                >
                  +60 Pelanggan Berikutnya
                </button>
                <button
                  type="button"
                  onClick={() => setVisibleLimit(filtered.length)}
                  className="px-3.5 py-2 rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-800 hover:bg-gray-100 dark:hover:bg-[#1C232E] text-gray-700 dark:text-slate-300 hover:text-gray-900 dark:hover:text-white text-xs font-semibold transition-all active:scale-95 cursor-pointer"
                >
                  Tampilkan Semua ({filtered.length})
                </button>
              </div>
            </div>
          )}
        </div>
      </div>

      {/* ── CENTER MODAL: AKSI & UTILITAS PELANGGAN ── */}
      {moreMenuOpen && (
        <div className="fixed inset-0 z-[99999] flex items-center justify-center p-3 sm:p-4 bg-black/70 backdrop-blur-xs animate-in fade-in-50 duration-150">
          <div className="fixed inset-0" onClick={() => setMoreMenuOpen(false)} />
          <div className="relative w-full max-w-lg overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-2xl dark:border-gray-800 dark:bg-gray-900 max-h-[92vh] flex flex-col my-auto text-gray-900 dark:text-white z-10 animate-in zoom-in-95 duration-150">
            {/* Header */}
            <div className="flex items-center justify-between border-b border-gray-100 p-4 sm:p-5 dark:border-gray-800">
              <div className="flex items-center gap-2.5">
                <div className="flex h-9 w-9 items-center justify-center rounded-xl bg-brand-50 text-brand-500 dark:bg-brand-500/10">
                  <SlidersHorizontal className="h-5 w-5" />
                </div>
                <div>
                  <h3 className="text-sm sm:text-base font-bold text-gray-900 dark:text-white">Aksi &amp; Utilitas Pelanggan</h3>
                  <p className="text-xs text-gray-500 dark:text-gray-400">Pilih menu utilitas, filter, sinkronisasi, atau operasi massal</p>
                </div>
              </div>
              <button
                type="button"
                onClick={() => setMoreMenuOpen(false)}
                className="flex h-8 w-8 items-center justify-center rounded-lg text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-800 dark:hover:text-gray-300 transition cursor-pointer"
              >
                <X className="h-4 w-4" />
              </button>
            </div>

            {/* Body */}
            <div className="space-y-2.5 p-4 sm:p-5 overflow-y-auto custom-scrollbar">
              {/* 1. Export / Import Excel */}
              <button
                type="button"
                onClick={() => {
                  setMoreMenuOpen(false)
                  setImportFile(null)
                  setImportResult(null)
                  setExcelModalOpen(true)
                }}
                className="flex w-full items-center gap-3.5 rounded-xl border border-gray-200 bg-white p-3 text-left hover:border-emerald-500 hover:bg-emerald-50/40 dark:border-gray-800 dark:bg-white/[0.02] dark:hover:border-emerald-500/50 dark:hover:bg-emerald-950/20 transition group cursor-pointer shadow-2xs"
              >
                <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400 group-hover:scale-105 transition-transform">
                  <FileSpreadsheet className="h-5 w-5" />
                </div>
                <div className="min-w-0 flex-1">
                  <div className="text-xs font-bold text-gray-900 dark:text-white group-hover:text-emerald-600 dark:group-hover:text-emerald-400">
                    Ekspor &amp; Impor Excel
                  </div>
                  <div className="text-[11px] text-gray-500 dark:text-gray-400 truncate">
                    Unduh rekap data pelanggan (XLSX/CSV) atau impor data baru
                  </div>
                </div>
              </button>

              {/* 2. Filter Lanjutan */}
              <button
                type="button"
                onClick={() => {
                  setMoreMenuOpen(false)
                  setFilterModalOpen(true)
                }}
                className="flex w-full items-center gap-3.5 rounded-xl border border-gray-200 bg-white p-3 text-left hover:border-brand-500 hover:bg-brand-50/40 dark:border-gray-800 dark:bg-white/[0.02] dark:hover:border-brand-500/50 dark:hover:bg-brand-950/20 transition group cursor-pointer shadow-2xs"
              >
                <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-400 group-hover:scale-105 transition-transform">
                  <SlidersHorizontal className="h-5 w-5" />
                </div>
                <div className="min-w-0 flex-1">
                  <div className="flex items-center gap-2">
                    <span className="text-xs font-bold text-gray-900 dark:text-white group-hover:text-brand-600 dark:group-hover:text-brand-400">
                      Filter &amp; Pencarian Lanjutan
                    </span>
                    {activeFiltersCount > 0 && (
                      <span className="rounded-full bg-brand-500 px-1.5 py-0.2 text-[10px] font-bold text-white">
                        {activeFiltersCount} Aktif
                      </span>
                    )}
                  </div>
                  <div className="text-[11px] text-gray-500 dark:text-gray-400 truncate">
                    Filter berdasarkan ODP spesifik, rentang tanggal tempo, dan status
                  </div>
                </div>
              </button>

              {/* 3. Sinkronkan MikroTik */}
              <button
                type="button"
                onClick={() => {
                  setMoreMenuOpen(false)
                  setSyncOpen(true)
                }}
                className="flex w-full items-center gap-3.5 rounded-xl border border-gray-200 bg-white p-3 text-left hover:border-blue-500 hover:bg-blue-50/40 dark:border-gray-800 dark:bg-white/[0.02] dark:hover:border-blue-500/50 dark:hover:bg-blue-950/20 transition group cursor-pointer shadow-2xs"
              >
                <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-600 dark:bg-blue-500/10 dark:text-blue-400 group-hover:scale-105 transition-transform">
                  <RefreshCw className="h-5 w-5" />
                </div>
                <div className="min-w-0 flex-1">
                  <div className="text-xs font-bold text-gray-900 dark:text-white group-hover:text-blue-600 dark:group-hover:text-blue-400">
                    Sinkronkan MikroTik
                  </div>
                  <div className="text-[11px] text-gray-500 dark:text-gray-400 truncate">
                    Tarik data user PPPoE &amp; IP langsung dari router aktif
                  </div>
                </div>
              </button>

              {/* 4. Multi-Select Mode */}
              <button
                type="button"
                onClick={() => {
                  setMoreMenuOpen(false)
                  selection.toggleSelectMode()
                }}
                className="flex w-full items-center gap-3.5 rounded-xl border border-gray-200 bg-white p-3 text-left hover:border-indigo-500 hover:bg-indigo-50/40 dark:border-gray-800 dark:bg-white/[0.02] dark:hover:border-indigo-500/50 dark:hover:bg-indigo-950/20 transition group cursor-pointer shadow-2xs"
              >
                <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600 dark:bg-indigo-500/10 dark:text-indigo-400 group-hover:scale-105 transition-transform">
                  <CheckSquare className="h-5 w-5" />
                </div>
                <div className="min-w-0 flex-1">
                  <div className="text-xs font-bold text-gray-900 dark:text-white group-hover:text-indigo-600 dark:group-hover:text-indigo-400">
                    {selection.isSelectMode ? "Matikan Mode Multi-Select" : "Aktifkan Mode Multi-Select"}
                  </div>
                  <div className="text-[11px] text-gray-500 dark:text-gray-400 truncate">
                    Pilih banyak pelanggan untuk aksi batch atau cetak massal
                  </div>
                </div>
              </button>

              {/* 5. Isolir Massal (Zona Tindakan Kritis) */}
              <button
                type="button"
                onClick={() => {
                  setMoreMenuOpen(false)
                  if (selection.selectedIds.length === 0) {
                    selection.toggleSelectMode()
                  }
                  setBatchIsolateModalOpen(true)
                }}
                className="flex w-full items-center gap-3.5 rounded-xl border border-rose-200 bg-rose-50/30 p-3 text-left hover:border-rose-500 hover:bg-rose-50 dark:border-rose-900/40 dark:bg-rose-950/10 dark:hover:border-rose-500 dark:hover:bg-rose-950/30 transition group cursor-pointer shadow-2xs"
              >
                <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-rose-500 text-white group-hover:scale-105 transition-transform shadow-xs">
                  <AlertTriangle className="h-5 w-5" />
                </div>
                <div className="min-w-0 flex-1">
                  <div className="flex items-center gap-2">
                    <span className="text-xs font-bold text-rose-600 dark:text-rose-400">
                      Isolir Massal Pelanggan
                    </span>
                    <span className="rounded-md bg-rose-500 px-1.5 py-0.2 text-[9px] font-extrabold text-white">
                      ZONA KRITIS
                    </span>
                  </div>
                  <div className="text-[11px] text-rose-600/80 dark:text-rose-400/80 truncate">
                    Isolir serentak seluruh pelanggan yang memiliki tagihan jatuh tempo
                  </div>
                </div>
              </button>
            </div>

            {/* Footer */}
            <div className="border-t border-gray-100 p-3.5 sm:p-4 dark:border-gray-800 bg-gray-50/50 dark:bg-white/[0.01] flex justify-end">
              <button
                type="button"
                onClick={() => setMoreMenuOpen(false)}
                className="rounded-xl border border-gray-200 bg-white px-4 py-2 text-xs font-bold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700 transition cursor-pointer"
              >
                Tutup
              </button>
            </div>
          </div>
        </div>
      )}

      {/* ── MODAL 1 TOMBOL FILTER ── */}
      {filterModalOpen && (
        <div className="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-0 sm:p-4">
          <div className="fixed inset-0 bg-black/70 backdrop-blur-sm" onClick={() => setFilterModalOpen(false)} />
          <div className="relative flex max-h-[92vh] w-full max-w-md flex-col overflow-hidden rounded-t-3xl sm:rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 shadow-2xl animate-in slide-in-from-bottom-4 duration-200 text-gray-900 dark:text-white">
            <div className="flex items-center justify-between border-b border-gray-100 dark:border-[#1E2633] px-5 py-4 shrink-0 bg-white dark:bg-gray-900">
              <div className="flex items-center gap-2.5">
                <SlidersHorizontal className="h-5 w-5 text-[#0073C6] dark:text-[#00C2FF]" />
                <h3 className="font-display text-base font-bold text-gray-900 dark:text-white">Filter Pelanggan</h3>
              </div>
              <button
                onClick={() => setFilterModalOpen(false)}
                className="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-700 dark:hover:text-white cursor-pointer"
              >
                <X className="h-4 w-4" />
              </button>
            </div>

            <div className="flex-1 overflow-y-auto p-5 space-y-4 pr-3 custom-scrollbar">
              {/* Filter Status */}
              <div className="space-y-1.5">
                <label className="text-xs font-bold text-gray-700 dark:text-slate-300">Status Langganan</label>
                <div className="grid grid-cols-4 gap-2">
                  {[
                    { id: "all", label: "Semua" },
                    { id: "active", label: "Aktif" },
                    { id: "isolated", label: "Terisolir" },
                    { id: "inactive", label: "Nonaktif" },
                  ].map((s) => (
                    <button
                      key={s.id}
                      type="button"
                      onClick={() => setStatusFilter(s.id)}
                      className={cn(
                        "h-9 rounded-xl border text-xs font-bold transition-all cursor-pointer",
                        statusFilter === s.id
                          ? "border-[#0073C6] bg-blue-50 dark:bg-[#0B2138] text-[#0073C6] dark:text-[#00C2FF]"
                          : "border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-900/50 text-gray-600 dark:text-slate-400 hover:text-gray-900 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-gray-800"
                      )}
                    >
                      {s.label}
                    </button>
                  ))}
                </div>
              </div>

              {/* Filter Tipe Koneksi */}
              <div className="space-y-1.5">
                <label className="text-xs font-bold text-gray-700 dark:text-slate-300">Tipe Koneksi</label>
                <div className="grid grid-cols-4 gap-2">
                  {[
                    { id: "all", label: "Semua" },
                    { id: "pppoe", label: "PPPoE" },
                    { id: "static", label: "Static IP" },
                    { id: "hotspot", label: "Hotspot" },
                  ].map((k) => (
                    <button
                      key={k.id}
                      type="button"
                      onClick={() => setConnectionTypeFilter(k.id)}
                      className={cn(
                        "h-9 rounded-xl border text-xs font-bold transition-all cursor-pointer",
                        connectionTypeFilter === k.id
                          ? "border-[#0073C6] bg-blue-50 dark:bg-[#0B2138] text-[#0073C6] dark:text-[#00C2FF]"
                          : "border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-900/50 text-gray-600 dark:text-slate-400 hover:text-gray-900 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-gray-800"
                      )}
                    >
                      {k.label}
                    </button>
                  ))}
                </div>
              </div>

              {/* Filter Paket */}
              {packages.length > 0 && (
                <div className="space-y-1.5">
                  <label className="text-xs font-bold text-gray-700 dark:text-slate-300">Paket Langganan</label>
                  <select
                    value={packageFilter}
                    onChange={(e) => setPackageFilter(e.target.value)}
                    className="w-full h-10 rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-900/50 px-3 text-xs font-semibold text-gray-900 dark:text-white focus:outline-none focus:border-[#0073C6]"
                  >
                    <option value="all">Semua Paket</option>
                    {packages.map((pkg) => (
                      <option key={pkg.id} value={pkg.name}>
                        {pkg.name} ({formatIDR(pkg.price)})
                      </option>
                    ))}
                  </select>
                </div>
              )}

              {/* Filter Router MikroTik */}
              {routers.length > 0 && (
                <div className="space-y-1.5">
                  <label className="text-xs font-bold text-gray-700 dark:text-slate-300">Router MikroTik</label>
                  <select
                    value={routerFilter}
                    onChange={(e) => setRouterFilter(e.target.value)}
                    className="w-full h-10 rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-900/50 px-3 text-xs font-semibold text-gray-900 dark:text-white focus:outline-none focus:border-[#0073C6]"
                  >
                    <option value="all">Semua Router</option>
                    {routers.map((r) => (
                      <option key={r.id} value={String(r.id)}>
                        {r.name}
                      </option>
                    ))}
                  </select>
                </div>
              )}

              {/* Filter Kotak ODP */}
              {odps.length > 0 && (
                <div className="space-y-1.5">
                  <label className="text-xs font-bold text-gray-700 dark:text-slate-300">Kotak ODP (Fiber)</label>
                  <select
                    value={odpFilter}
                    onChange={(e) => setOdpFilter(e.target.value)}
                    className="w-full h-10 rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-900/50 px-3 text-xs font-semibold text-gray-900 dark:text-white focus:outline-none focus:border-[#0073C6]"
                  >
                    <option value="all">Semua Kotak ODP</option>
                    {odps.map((o) => (
                      <option key={o.id} value={String(o.id)}>
                        {o.name} ({o.available_ports}/{o.capacity} port tersedia)
                      </option>
                    ))}
                  </select>
                </div>
              )}

              {/* Filter Tunggakan */}
              <div className="space-y-1.5 pt-1">
                <label
                  onClick={() => setUnpaidFilter(!unpaidFilter)}
                  className={cn(
                    "flex items-center justify-between p-3 rounded-xl border cursor-pointer select-none transition-all shadow-xs",
                    unpaidFilter
                      ? "border-rose-300 dark:border-rose-900/60 bg-rose-50 dark:bg-[#240D12] text-rose-700 dark:text-rose-300"
                      : "border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-900/50 text-gray-600 dark:text-slate-400 hover:bg-gray-100 dark:hover:bg-gray-800"
                  )}
                >
                  <div className="flex items-center gap-2">
                    <Receipt className="h-4 w-4 text-rose-500" />
                    <span className="text-xs font-bold text-gray-800 dark:text-slate-200">Hanya Tagihan Belum Lunas</span>
                  </div>
                  <Checkbox
                    checked={unpaidFilter}
                    onChange={() => setUnpaidFilter(!unpaidFilter)}
                  />
                </label>
              </div>
            </div>

            <div className="border-t border-gray-100 dark:border-[#1E2633] p-4 bg-white dark:bg-gray-900 shrink-0 flex items-center gap-2">
              <button
                type="button"
                className="flex-1 rounded-xl h-10 text-xs font-bold border border-gray-200 dark:border-gray-800 bg-gray-100 dark:bg-[#18202C] text-gray-700 dark:text-slate-300 hover:bg-gray-200 dark:hover:bg-[#1E2636] hover:text-gray-900 dark:hover:text-white transition-all flex items-center justify-center gap-1.5 cursor-pointer"
                onClick={resetFilters}
              >
                <RotateCcw className="h-3.5 w-3.5" /> Reset Filter
              </button>
              <button
                type="button"
                className="flex-1 rounded-xl h-10 text-xs font-bold bg-[#0073C6] hover:bg-[#0084E3] text-white transition-all flex items-center justify-center gap-1.5 cursor-pointer shadow-xs"
                onClick={() => setFilterModalOpen(false)}
              >
                Terapkan Filter
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Floating Selection Bar for Multi-select */}
      <SelectionFloatingBar
        selection={selection}
        totalItems={filtered.length}
        onSelectAll={() => selection.selectAll(filtered.map((c) => c.id))}
        actions={selectionActions}
      />

      {/* ── MODAL FORM TAMBAH / EDIT PELANGGAN ── */}
      {sheetOpen && (
        <div className="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-0 sm:p-4">
          <div className="fixed inset-0 bg-black/70 backdrop-blur-sm" onClick={() => setSheetOpen(false)} />
          <div className="relative flex max-h-[92vh] w-full max-w-lg flex-col overflow-hidden rounded-t-3xl sm:rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 shadow-2xl animate-in slide-in-from-bottom-4 duration-200 text-gray-900 dark:text-white">
            <div className="flex items-center justify-between border-b border-gray-100 dark:border-[#1E2633] px-5 py-4 shrink-0 bg-white dark:bg-gray-900">
              <div className="flex items-center gap-2.5">
                <div className="flex h-9 w-9 items-center justify-center rounded-xl bg-blue-50 dark:bg-[#0B2138] text-[#0073C6] dark:text-[#00C2FF] border border-blue-200 dark:border-[#0073C6]/40">
                  <Users className="h-4.5 w-4.5" />
                </div>
                <h3 className="font-display text-base font-bold text-gray-900 dark:text-white">
                  {editing ? t("customer.edit_title", "Edit Data Pelanggan") : t("customer.add_title", "Tambah Pelanggan Baru")}
                </h3>
              </div>
              <button
                onClick={() => setSheetOpen(false)}
                className="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-700 dark:hover:text-white cursor-pointer"
              >
                <X className="h-4 w-4" />
              </button>
            </div>

            <form onSubmit={submit} className="flex min-h-0 flex-1 flex-col overflow-hidden">
              <div className="flex-1 overflow-y-auto p-5 space-y-4 pr-3 custom-scrollbar">
                {/* Error Banner if any */}
                {Object.keys(form.errors).length > 0 && (
                  <div className="rounded-xl border border-rose-200 dark:border-rose-900/60 bg-rose-50 dark:bg-[#240D12] p-3 text-xs text-rose-700 dark:text-rose-300 space-y-1">
                    <div className="flex items-center gap-1.5 font-bold text-rose-800 dark:text-rose-200">
                      <AlertCircle className="h-4 w-4 shrink-0 text-rose-500" />
                      <span>Gagal menyimpan perubahan:</span>
                    </div>
                    <ul className="list-disc list-inside space-y-0.5 text-[11px] text-rose-600 dark:text-rose-300/90 pl-1">
                      {Object.entries(form.errors).map(([key, msg]) => (
                        <li key={key}>{msg}</li>
                      ))}
                    </ul>
                  </div>
                )}

                <div className="space-y-1.5">
                  <label className="text-xs font-bold text-gray-700 dark:text-slate-300">Tipe Koneksi</label>
                  <select
                    value={form.data.connection_type}
                    onChange={(e) => form.setData("connection_type", e.target.value as any)}
                    className="h-10 w-full rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-900/50 px-3 text-xs font-bold text-gray-900 dark:text-white focus:outline-none focus:border-[#0073C6]"
                  >
                    <option value="pppoe">PPPoE (Secret MikroTik)</option>
                    <option value="static">IP Statis / ARP Binding</option>
                    <option value="hotspot">User Hotspot / Voucher</option>
                  </select>
                </div>

                <div className="space-y-1.5">
                  <label className="text-xs font-semibold text-gray-700 dark:text-slate-300">Nama Lengkap *</label>
                  <input
                    type="text"
                    value={form.data.name}
                    onChange={(e) => form.setData("name", e.target.value)}
                    placeholder="Nama pelanggan"
                    className={cn(
                      "w-full h-10 rounded-xl border bg-gray-50 dark:bg-gray-900/50 px-3 text-xs text-gray-900 dark:text-white placeholder:text-gray-400 dark:placeholder:text-slate-500 focus:outline-none",
                      form.errors.name ? "border-rose-500 focus:border-rose-400" : "border-gray-200 dark:border-gray-800 focus:border-[#0073C6]"
                    )}
                    required
                  />
                  {form.errors.name && <p className="text-[11px] text-rose-500 font-medium">{form.errors.name}</p>}
                </div>

                <div className="grid gap-3 sm:grid-cols-2">
                  <div className="space-y-1.5">
                    <label className="text-xs font-semibold text-gray-700 dark:text-slate-300">No. WhatsApp</label>
                    <input
                      type="text"
                      value={form.data.phone}
                      onChange={(e) => form.setData("phone", e.target.value)}
                      placeholder="08123456789..."
                      className={cn(
                        "w-full h-10 rounded-xl border bg-gray-50 dark:bg-gray-900/50 px-3 text-xs text-gray-900 dark:text-white placeholder:text-gray-400 dark:placeholder:text-slate-500 focus:outline-none",
                        form.errors.phone ? "border-rose-500 focus:border-rose-400" : "border-gray-200 dark:border-gray-800 focus:border-[#0073C6]"
                      )}
                    />
                    {form.errors.phone && <p className="text-[11px] text-rose-500 font-medium">{form.errors.phone}</p>}
                  </div>
                  <div className="space-y-1.5">
                    <label className="text-xs font-semibold text-gray-700 dark:text-slate-300">Tanggal Jatuh Tempo (1-28)</label>
                    <input
                      type="number"
                      min={1}
                      max={28}
                      value={form.data.isolation_date}
                      onChange={(e) => form.setData("isolation_date", e.target.value)}
                      className={cn(
                        "w-full h-10 rounded-xl border bg-gray-50 dark:bg-gray-900/50 px-3 text-xs text-gray-900 dark:text-white font-mono placeholder:text-gray-400 dark:placeholder:text-slate-500 focus:outline-none",
                        form.errors.isolation_date ? "border-rose-500 focus:border-rose-400" : "border-gray-200 dark:border-gray-800 focus:border-[#0073C6]"
                      )}
                    />
                    {form.errors.isolation_date && <p className="text-[11px] text-rose-500 font-medium">{form.errors.isolation_date}</p>}
                  </div>
                </div>

                {form.data.connection_type === "pppoe" && (
                  <div className="grid gap-3 sm:grid-cols-2">
                    <div className="space-y-1.5">
                      <label className="text-xs font-semibold text-gray-700 dark:text-slate-300">Username Secret *</label>
                      <input
                        type="text"
                        value={form.data.pppoe_username}
                        onChange={(e) => form.setData("pppoe_username", e.target.value)}
                        placeholder="pppoe_username"
                        className={cn(
                          "w-full h-10 rounded-xl border bg-gray-50 dark:bg-gray-900/50 px-3 text-xs text-gray-900 dark:text-white font-mono placeholder:text-gray-400 dark:placeholder:text-slate-500 focus:outline-none",
                          form.errors.pppoe_username ? "border-rose-500 focus:border-rose-400" : "border-gray-200 dark:border-gray-800 focus:border-[#0073C6]"
                        )}
                      />
                      {form.errors.pppoe_username && <p className="text-[11px] text-rose-500 font-medium">{form.errors.pppoe_username}</p>}
                    </div>
                    <div className="space-y-1.5">
                      <label className="text-xs font-semibold text-gray-700 dark:text-slate-300">Password Secret PPPoE</label>
                      <div className="relative">
                        <input
                          type={showPppoePass ? "text" : "password"}
                          value={form.data.pppoe_password}
                          onChange={(e) => form.setData("pppoe_password", e.target.value)}
                          placeholder="Password secret (default: 123456)"
                          className="w-full h-10 rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-900/50 pl-3 pr-9 text-xs text-gray-900 dark:text-white font-mono placeholder:text-gray-400 dark:placeholder:text-slate-500 focus:outline-none focus:border-[#0073C6]"
                        />
                        <button
                          type="button"
                          onClick={() => setShowPppoePass(!showPppoePass)}
                          className="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-700 dark:hover:text-white cursor-pointer"
                        >
                          {showPppoePass ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                        </button>
                      </div>
                      {form.errors.pppoe_password && <p className="text-[11px] text-rose-500 font-medium">{form.errors.pppoe_password}</p>}
                    </div>
                  </div>
                )}

                {form.data.connection_type === "static" && (
                  <div className="space-y-3 rounded-2xl border border-blue-200 dark:border-[#0073C6]/30 bg-blue-50/50 dark:bg-[#0B2138]/40 p-3.5">
                    <div className="flex items-start gap-2.5 text-xs">
                      <Activity className="h-4 w-4 shrink-0 text-[#0073C6] dark:text-[#00C2FF] mt-0.5" />
                      <div>
                        <p className="font-semibold text-gray-900 dark:text-white">Mode IP Statis / ARP Binding</p>
                        <p className="text-[11px] text-gray-600 dark:text-slate-300 mt-0.5 leading-relaxed">
                          Kecepatan internet dibatasi otomatis via <strong>Simple Queue MikroTik</strong> berdasarkan limit paket yang dipilih.
                        </p>
                      </div>
                    </div>
                    <div className="grid gap-3 sm:grid-cols-3">
                      <div className="space-y-1.5">
                        <label className="text-xs font-semibold text-gray-700 dark:text-slate-300">Alamat IP Statis *</label>
                        <input
                          type="text"
                          value={form.data.ip_address}
                          onChange={(e) => form.setData("ip_address", e.target.value)}
                          placeholder="192.168.1.100"
                          className={cn(
                            "w-full h-10 rounded-xl border bg-gray-50 dark:bg-gray-900/50 px-3 text-xs text-gray-900 dark:text-white font-mono placeholder:text-gray-400 dark:placeholder:text-slate-500 focus:outline-none",
                            form.errors.ip_address ? "border-rose-500 focus:border-rose-400" : "border-gray-200 dark:border-gray-800 focus:border-[#0073C6]"
                          )}
                        />
                        {form.errors.ip_address && <p className="text-[11px] text-rose-500 font-medium">{form.errors.ip_address}</p>}
                      </div>
                      <div className="space-y-1.5">
                        <label className="text-xs font-semibold text-gray-700 dark:text-slate-300">Alamat MAC</label>
                        <input
                          type="text"
                          value={form.data.mac_address}
                          onChange={(e) => form.setData("mac_address", e.target.value)}
                          placeholder="AA:BB:CC:DD:EE:FF"
                          className="w-full h-10 rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-900/50 px-3 text-xs text-gray-900 dark:text-white font-mono placeholder:text-gray-400 dark:placeholder:text-slate-500 focus:outline-none focus:border-[#0073C6]"
                        />
                        {form.errors.mac_address && <p className="text-[11px] text-rose-500 font-medium">{form.errors.mac_address}</p>}
                      </div>
                      <div className="space-y-1.5">
                        <label className="text-xs font-semibold text-gray-700 dark:text-slate-300">Interface ARP</label>
                        <select
                          value={form.data.arp_interface || "bridge"}
                          onChange={(e) => form.setData("arp_interface", e.target.value)}
                          className={cn(
                            "w-full h-10 rounded-xl border bg-gray-50 dark:bg-gray-900/50 px-3 text-xs text-gray-900 dark:text-white font-mono focus:outline-none cursor-pointer",
                            form.errors.arp_interface ? "border-rose-500 focus:border-rose-400" : "border-gray-200 dark:border-gray-800 focus:border-[#0073C6]"
                          )}
                        >
                          {availableInterfaces.map((iface) => (
                            <option key={iface} value={iface}>
                              {iface}
                            </option>
                          ))}
                        </select>
                        {form.errors.arp_interface && <p className="text-[11px] text-rose-500 font-medium">{form.errors.arp_interface}</p>}
                      </div>
                    </div>
                  </div>
                )}

                <div className="grid gap-3 sm:grid-cols-2">
                  <div className="space-y-1.5">
                    <label className="text-xs font-semibold text-gray-700 dark:text-slate-300">Paket Langganan</label>
                    <select
                      value={form.data.package_id}
                      onChange={(e) => form.setData("package_id", e.target.value)}
                      className={cn(
                        "h-10 w-full rounded-xl border bg-gray-50 dark:bg-gray-900/50 px-3 text-xs font-medium text-gray-900 dark:text-white focus:outline-none",
                        form.errors.package_id ? "border-rose-500 focus:border-rose-400" : "border-gray-200 dark:border-gray-800 focus:border-[#0073C6]"
                      )}
                    >
                      <option value="">Pilih Paket Langganan</option>
                      {packages.map((p) => (
                        <option key={p.id} value={String(p.id)}>
                          {p.name} ({formatIDR(p.price)})
                        </option>
                      ))}
                    </select>
                    {form.errors.package_id && <p className="text-[11px] text-rose-500 font-medium">{form.errors.package_id}</p>}
                  </div>
                  <div className="space-y-1.5">
                    <label className="text-xs font-semibold text-gray-700 dark:text-slate-300">Router MikroTik</label>
                    <select
                      value={form.data.router_id}
                      onChange={(e) => form.setData("router_id", e.target.value)}
                      className={cn(
                        "h-10 w-full rounded-xl border bg-gray-50 dark:bg-gray-900/50 px-3 text-xs font-medium text-gray-900 dark:text-white focus:outline-none",
                        form.errors.router_id ? "border-rose-500 focus:border-rose-400" : "border-gray-200 dark:border-gray-800 focus:border-[#0073C6]"
                      )}
                    >
                      <option value="">Pilih Router Gateway</option>
                      {routers.map((r) => (
                        <option key={r.id} value={String(r.id)}>
                          {r.name}
                        </option>
                      ))}
                    </select>
                    {form.errors.router_id && <p className="text-[11px] text-rose-500 font-medium">{form.errors.router_id}</p>}
                  </div>
                </div>

                {/* ODP & Port Selector */}
                <div className="grid gap-3 sm:grid-cols-2">
                  <div className="space-y-1.5">
                    <label className="text-xs font-semibold text-gray-700 dark:text-slate-300 flex items-center gap-1.5">
                      <Radio className="h-3.5 w-3.5 text-amber-500" />
                      <span>Kotak ODP (Distribusi Fiber)</span>
                    </label>
                    <select
                      value={form.data.odp_id}
                      onChange={(e) => {
                        const newOdpId = e.target.value
                        form.setData("odp_id", newOdpId)
                        if (newOdpId) {
                          const selectedOdp = odps.find((o) => String(o.id) === newOdpId)
                          const cap = selectedOdp?.capacity || 8
                          const occupied = new Set<number>()
                          customers.forEach((c) => {
                            if (String(c.odp_id) === newOdpId && (!editing || c.id !== editing.id) && c.odp_port) {
                              occupied.add(Number(c.odp_port))
                            }
                          })
                          let firstFreePort = ""
                          for (let p = 1; p <= cap; p++) {
                            if (!occupied.has(p)) {
                              firstFreePort = String(p)
                              break
                            }
                          }
                          form.setData("odp_port", firstFreePort)
                        } else {
                          form.setData("odp_port", "")
                        }
                      }}
                      className="h-10 w-full rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-900/50 px-3 text-xs font-medium text-gray-900 dark:text-white focus:outline-none focus:border-[#0073C6]"
                    >
                      <option value="">Pilih Kotak ODP (Opsional)</option>
                      {odps.map((o) => (
                        <option key={o.id} value={String(o.id)}>
                          {o.name} ({o.available_ports}/{o.capacity} port tersedia)
                        </option>
                      ))}
                    </select>
                  </div>
                  <div className="space-y-1.5">
                    <label className="text-xs font-semibold text-gray-700 dark:text-slate-300">Port ODP Digunakan</label>
                    {form.data.odp_id ? (() => {
                      const selectedOdp = odps.find((o) => String(o.id) === String(form.data.odp_id))
                      const cap = selectedOdp?.capacity || 8
                      const isOccupied = occupiedPortsForOdp.has(Number(form.data.odp_port))
                      const takenBy = occupiedPortsForOdp.get(Number(form.data.odp_port))
                      const isTotallyFull = occupiedPortsForOdp.size >= cap

                      return (
                        <div className="space-y-1.5">
                          {isTotallyFull && (
                            <div className="p-2 rounded-xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-500/40 text-rose-700 dark:text-rose-300 text-xs flex items-center gap-2">
                              <AlertTriangle className="h-4 w-4 shrink-0 text-rose-500" />
                              <span>Semua port pada ODP "{selectedOdp?.name}" sudah terisi penuh ({cap}/{cap} Port). Silakan pilih ODP lain.</span>
                            </div>
                          )}
                          <select
                            value={form.data.odp_port}
                            onChange={(e) => form.setData("odp_port", e.target.value)}
                            className={cn(
                              "h-10 w-full rounded-xl border px-3 text-xs font-medium text-gray-900 dark:text-white focus:outline-none",
                              isOccupied
                                ? "border-rose-500 bg-rose-50 dark:bg-rose-950/30 text-rose-700 dark:text-rose-300 focus:border-rose-400"
                                : "border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-900/50 focus:border-[#0073C6]"
                            )}
                          >
                            <option value="">-- Pilih Port ODP --</option>
                            {Array.from({ length: cap }, (_, i) => i + 1).map((p) => {
                              const occupiedBy = occupiedPortsForOdp.get(p)
                              return (
                                <option
                                  key={p}
                                  value={String(p)}
                                  disabled={!!occupiedBy}
                                  className={occupiedBy ? "text-rose-500" : ""}
                                >
                                  Port {p} {occupiedBy ? `• [Terpakai: "${occupiedBy}"]` : "• (Tersedia)"}
                                </option>
                              )
                            })}
                          </select>
                          {isOccupied && (
                            <p className="text-[11px] text-rose-500 font-medium leading-tight flex items-center gap-1.5">
                              <AlertTriangle className="h-3.5 w-3.5 shrink-0" />
                              <span>Port {form.data.odp_port} sudah digunakan oleh "{takenBy}". Silakan pilih port yang kosong.</span>
                            </p>
                          )}
                        </div>
                      )
                    })() : (
                      <input
                        type="text"
                        disabled
                        placeholder="Pilih Kotak ODP terlebih dahulu"
                        className="h-10 w-full rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-100 dark:bg-gray-900/50/50 px-3 text-xs text-gray-400 dark:text-slate-500 cursor-not-allowed"
                      />
                    )}
                  </div>
                </div>

                {/* GPS Coordinates */}
                <div className="space-y-2">
                  <div className="flex items-center justify-between gap-2">
                    <label className="text-xs font-semibold text-gray-700 dark:text-slate-300 flex items-center gap-1.5">
                      <MapPin className="h-3.5 w-3.5 text-[#0073C6] dark:text-[#00C2FF]" />
                      <span>Posisi Titik Koordinat (GPS)</span>
                    </label>
                    {editing && (editing.lat || editing.lng) && (
                      <button
                        type="button"
                        onClick={() => {
                          form.setData("lat", "")
                          form.setData("lng", "")
                        }}
                        className="flex items-center gap-1 rounded-lg border border-rose-200 dark:border-rose-500/30 bg-rose-50 dark:bg-rose-500/10 px-2.5 py-1 text-[10px] font-semibold text-rose-600 dark:text-rose-400 hover:bg-rose-100 dark:hover:bg-rose-500/20 transition-colors cursor-pointer whitespace-nowrap"
                        title="Hapus koordinat dari peta"
                      >
                        <MapPin className="h-3 w-3" />
                        Hapus Titik GPS
                      </button>
                    )}
                  </div>

                  <div className="grid gap-3 sm:grid-cols-2">
                    <div className="space-y-1.5">
                      <label className="text-[10px] font-medium text-gray-500 dark:text-slate-400">Latitude</label>
                      <input
                        type="text"
                        placeholder="-7.797068"
                        value={form.data.lat}
                        onChange={(e) => form.setData("lat", e.target.value)}
                        className="h-10 w-full rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-900/50 px-3 text-xs font-mono text-gray-900 dark:text-white placeholder:text-gray-400 dark:placeholder:text-slate-500 focus:outline-none focus:border-[#0073C6]"
                      />
                    </div>
                    <div className="space-y-1.5">
                      <label className="text-[10px] font-medium text-gray-500 dark:text-slate-400">Longitude</label>
                      <input
                        type="text"
                        placeholder="110.370529"
                        value={form.data.lng}
                        onChange={(e) => form.setData("lng", e.target.value)}
                        className="h-10 w-full rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-900/50 px-3 text-xs font-mono text-gray-900 dark:text-white placeholder:text-gray-400 dark:placeholder:text-slate-500 focus:outline-none focus:border-[#0073C6]"
                      />
                    </div>
                  </div>
                </div>

                <div className="space-y-1.5">
                  <label className="text-xs font-semibold text-gray-700 dark:text-slate-300">Alamat Tempat Tinggal</label>
                  <textarea
                    value={form.data.address}
                    onChange={(e) => form.setData("address", e.target.value)}
                    placeholder="Alamat lengkap atau patokan lokasi pelanggan"
                    rows={2}
                    className="w-full rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-900/50 p-3 text-xs text-gray-900 dark:text-white placeholder:text-gray-400 dark:placeholder:text-slate-500 focus:outline-none focus:border-[#0073C6]"
                  />
                </div>
              </div>

              <div className="border-t border-gray-100 dark:border-[#1E2633] p-4 bg-white dark:bg-gray-900 shrink-0 flex items-center gap-2">
                <button
                  type="button"
                  className="flex-1 rounded-xl h-10 text-xs font-bold border border-gray-200 dark:border-gray-800 bg-gray-100 dark:bg-[#18202C] text-gray-700 dark:text-slate-300 hover:bg-gray-200 dark:hover:bg-[#1E2636] hover:text-gray-900 dark:hover:text-white transition-all cursor-pointer"
                  onClick={() => setSheetOpen(false)}
                >
                  Batal
                </button>
                <button
                  type="submit"
                  disabled={form.processing || (!!form.data.odp_id && !!form.data.odp_port && occupiedPortsForOdp.has(Number(form.data.odp_port)))}
                  className="flex-1 rounded-xl h-10 text-xs font-bold bg-[#0073C6] hover:bg-[#0084E3] text-white transition-all flex items-center justify-center gap-1.5 disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer shadow-xs"
                >
                  {form.processing ? "Menyimpan..." : editing ? "Simpan Perubahan" : "Simpan Pelanggan"}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* ── MODAL KIRIM TIKET GANGGUAN ── */}
      {ticketTarget && (
        <div className="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-0 sm:p-4">
          <div className="fixed inset-0 bg-black/70 backdrop-blur-sm" onClick={() => setTicketTarget(null)} />
          <div className="relative flex max-h-[92vh] w-full max-w-lg flex-col overflow-hidden rounded-t-3xl sm:rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 shadow-2xl animate-in slide-in-from-bottom-4 duration-200 text-gray-900 dark:text-white">
            <div className="flex items-center justify-between border-b border-gray-100 dark:border-[#1E2633] px-5 py-4 shrink-0 bg-white dark:bg-gray-900">
              <div className="flex items-center gap-2.5">
                <div className="flex h-9 w-9 items-center justify-center rounded-xl bg-blue-50 dark:bg-[#0B2138] text-[#0073C6] dark:text-[#00C2FF] border border-blue-200 dark:border-[#0073C6]/40">
                  <Wrench className="h-4.5 w-4.5" />
                </div>
                <div>
                  <h3 className="font-display text-base font-bold text-gray-900 dark:text-white">
                    Buat Tiket Gangguan
                  </h3>
                  <p className="text-[11px] text-gray-500 dark:text-slate-400 font-medium">{ticketTarget.name}</p>
                </div>
              </div>
              <button
                type="button"
                onClick={() => setTicketTarget(null)}
                className="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-700 dark:hover:text-white transition-all cursor-pointer"
              >
                <X className="h-4 w-4" />
              </button>
            </div>

            <form onSubmit={handleTicketSubmit} className="flex min-h-0 flex-1 flex-col overflow-hidden">
              <div className="flex-1 overflow-y-auto p-5 space-y-4 pr-3 custom-scrollbar">
                <div className="space-y-1.5">
                  <label className="text-xs font-semibold text-gray-700 dark:text-slate-300">Judul Gangguan *</label>
                  <input
                    type="text"
                    value={ticketForm.data.title}
                    onChange={(e) => ticketForm.setData("title", e.target.value)}
                    placeholder="Cth: Kabel putus / Modem LOS / Internet lambat"
                    className="w-full h-10 rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-900/50 px-3 text-xs text-gray-900 dark:text-white placeholder:text-gray-400 dark:placeholder:text-slate-500 focus:outline-none focus:border-[#0073C6]"
                    required
                  />
                </div>

                <div className="space-y-1.5">
                  <label className="text-xs font-semibold text-gray-700 dark:text-slate-300">Tugaskan ke Teknisi</label>
                  <select
                    value={ticketForm.data.assigned_to}
                    onChange={(e) => ticketForm.setData("assigned_to", e.target.value)}
                    className="h-10 w-full rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-900/50 px-3 text-xs font-medium text-gray-900 dark:text-white focus:outline-none focus:border-[#0073C6]"
                  >
                    <option value="">Pilih Teknisi (Opsional)</option>
                    {technicians.map((t) => (
                      <option key={t.id} value={String(t.id)}>
                        {t.name}
                      </option>
                    ))}
                  </select>
                </div>

                <div className="space-y-1.5">
                  <label className="text-xs font-semibold text-gray-700 dark:text-slate-300">Rincian Gangguan *</label>
                  <textarea
                    value={ticketForm.data.description}
                    onChange={(e) => ticketForm.setData("description", e.target.value)}
                    placeholder="Jelaskan kendala atau keluhan yang dialami pelanggan..."
                    rows={4}
                    className="w-full rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-900/50 p-3 text-xs text-gray-900 dark:text-white placeholder:text-gray-400 dark:placeholder:text-slate-500 focus:outline-none focus:border-[#0073C6]"
                    required
                  />
                </div>
              </div>

              <div className="border-t border-gray-100 dark:border-[#1E2633] p-4 bg-white dark:bg-gray-900 shrink-0 flex items-center gap-2">
                <button
                  type="button"
                  className="flex-1 rounded-xl h-10 text-xs font-bold border border-gray-200 dark:border-gray-800 bg-gray-100 dark:bg-[#18202C] text-gray-700 dark:text-slate-300 hover:bg-gray-200 dark:hover:bg-[#1E2636] hover:text-gray-900 dark:hover:text-white transition-all cursor-pointer"
                  onClick={() => setTicketTarget(null)}
                >
                  Batal
                </button>
                <button
                  type="submit"
                  disabled={ticketForm.processing}
                  className="flex-1 rounded-xl h-10 text-xs font-bold bg-[#0073C6] hover:bg-[#0084E3] text-white transition-all flex items-center justify-center gap-1.5 disabled:opacity-50 cursor-pointer shadow-xs"
                >
                  {ticketForm.processing ? "Mengirim..." : "Kirim Tiket"}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* ── MODAL HAPUS PELANGGAN ── */}
      {deleteTarget && (
        <div className="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-0 sm:p-4">
          <div className="fixed inset-0 bg-black/70 backdrop-blur-sm" onClick={() => setDeleteTarget(null)} />
          <div className="relative flex w-full max-w-md flex-col overflow-hidden rounded-t-3xl sm:rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 shadow-2xl animate-in slide-in-from-bottom-4 duration-200 text-gray-900 dark:text-white">
            <div className="flex items-center justify-between border-b border-gray-100 dark:border-[#1E2633] px-5 py-4 shrink-0 bg-white dark:bg-gray-900">
              <div className="flex items-center gap-2.5">
                <div className="flex h-9 w-9 items-center justify-center rounded-xl bg-rose-50 dark:bg-[#240D12] text-rose-600 dark:text-rose-400 border border-rose-200 dark:border-rose-900/50">
                  <Trash2 className="h-4.5 w-4.5" />
                </div>
                <div>
                  <h3 className="font-display text-base font-bold text-gray-900 dark:text-white">Hapus Pelanggan</h3>
                  <p className="text-[11px] text-gray-500 dark:text-slate-400 font-medium">{deleteTarget.name}</p>
                </div>
              </div>
              <button
                type="button"
                onClick={() => setDeleteTarget(null)}
                className="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-700 dark:hover:text-white transition-all cursor-pointer"
              >
                <X className="h-4 w-4" />
              </button>
            </div>

            <div className="p-5 space-y-4">
              <p className="text-xs text-gray-600 dark:text-slate-300 leading-relaxed">
                Apakah Anda yakin ingin menghapus pelanggan <strong className="text-gray-900 dark:text-white">{deleteTarget.name}</strong> dari sistem NODERA Billing?
              </p>

              <label className="flex items-start gap-3 cursor-pointer select-none rounded-xl border border-gray-200 dark:border-gray-800 p-3.5 bg-gray-50 dark:bg-gray-900/50 hover:bg-gray-100 dark:hover:bg-gray-800 transition-all">
                <Checkbox
                  checked={deleteFromMikrotik}
                  onChange={(checked) => setDeleteFromMikrotik(checked)}
                />
                <div className="text-xs">
                  <span className="font-bold text-gray-900 dark:text-white block">Hapus juga dari Router MikroTik</span>
                  <span className="text-[11px] text-gray-500 dark:text-slate-400 block mt-0.5">Akun PPPoE atau IP Binding akan ikut dihapus dari MikroTik</span>
                </div>
              </label>
            </div>

            <div className="border-t border-gray-100 dark:border-[#1E2633] p-4 bg-white dark:bg-gray-900 shrink-0 flex items-center gap-2">
              <button
                type="button"
                className="flex-1 rounded-xl h-10 text-xs font-bold border border-gray-200 dark:border-gray-800 bg-gray-100 dark:bg-[#18202C] text-gray-700 dark:text-slate-300 hover:bg-gray-200 dark:hover:bg-[#1E2636] hover:text-gray-900 dark:hover:text-white transition-all cursor-pointer"
                onClick={() => setDeleteTarget(null)}
                disabled={deleting}
              >
                Batal
              </button>
              <button
                type="button"
                className="flex-1 rounded-xl h-10 text-xs font-bold bg-[#E11D48] hover:bg-[#BE123C] text-white transition-all flex items-center justify-center gap-1.5 disabled:opacity-50 cursor-pointer shadow-xs"
                disabled={deleting}
                onClick={() => {
                  setDeleting(true)
                  setSyncInfo({
                    show: true,
                    title: "Hapus Pelanggan",
                    statusMessage: `Menghapus ${deleteTarget.name} ${deleteFromMikrotik ? "dan akun MikroTik" : ""}...`,
                  })
                  router.post(
                    `/admin/billing/customers/delete/${deleteTarget.id}`,
                    { delete_from_mikrotik: deleteFromMikrotik },
                    {
                      preserveScroll: true,
                      onFinish: () => {
                        setDeleting(false)
                        setSyncInfo(null)
                        setDeleteTarget(null)
                      },
                    }
                  )
                }}
              >
                {deleting ? "Menghapus..." : "Ya, Hapus Pelanggan"}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* ── MODAL KONFIRMASI BUKA ISOLIR MULTI-SELECT ── */}
      {batchUnisolateModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
          <div className="fixed inset-0 bg-black/70 backdrop-blur-sm" onClick={() => !batchUnisolating && setBatchUnisolateModalOpen(false)} />
          <div className="relative w-full max-w-sm overflow-hidden rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 shadow-2xl animate-in zoom-in-95 duration-200 text-gray-900 dark:text-white">
            <div className="p-6 space-y-4 text-center">
              <div className="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/20 text-emerald-600 dark:text-emerald-400">
                <Power className="h-7 w-7" />
              </div>

              <div className="space-y-1">
                <h3 className="font-display text-base font-bold text-gray-900 dark:text-white">Buka Isolir Pelanggan?</h3>
                <p className="text-xs text-gray-600 dark:text-slate-400">
                  Buka isolir dan pulihkan koneksi untuk <span className="font-bold text-gray-900 dark:text-white">{selection.selectedIds.length}</span> pelanggan terpilih di MikroTik?
                </p>
              </div>
            </div>

            <div className="border-t border-gray-100 dark:border-[#1E2633] p-4 bg-white dark:bg-gray-900 shrink-0 flex items-center gap-2">
              <button
                type="button"
                className="flex-1 rounded-xl h-10 text-xs font-bold border border-gray-200 dark:border-gray-800 bg-gray-100 dark:bg-[#18202C] text-gray-700 dark:text-slate-300 hover:bg-gray-200 dark:hover:bg-[#1E2636] hover:text-gray-900 dark:hover:text-white transition-all cursor-pointer"
                onClick={() => setBatchUnisolateModalOpen(false)}
                disabled={batchUnisolating}
              >
                Batal
              </button>
              <button
                type="button"
                className="flex-1 rounded-xl h-10 text-xs font-bold bg-emerald-600 hover:bg-emerald-500 text-white transition-all flex items-center justify-center gap-1.5 disabled:opacity-50 cursor-pointer shadow-xs"
                disabled={batchUnisolating}
                onClick={executeBatchUnisolate}
              >
                {batchUnisolating ? "Memproses..." : "Ya, Buka Isolir"}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* ── MODAL KONFIRMASI ISOLIR MULTI-SELECT ── */}
      {batchIsolateModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
          <div className="fixed inset-0 bg-black/70 backdrop-blur-sm" onClick={() => !batchIsolating && setBatchIsolateModalOpen(false)} />
          <div className="relative w-full max-w-sm overflow-hidden rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 shadow-2xl animate-in zoom-in-95 duration-200 text-gray-900 dark:text-white">
            <div className="p-6 space-y-4 text-center">
              <div className="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-amber-50 dark:bg-amber-500/10 border border-amber-200 dark:border-amber-500/20 text-amber-600 dark:text-amber-400">
                <WifiOff className="h-7 w-7" />
              </div>

              <div className="space-y-1">
                <h3 className="font-display text-base font-bold text-gray-900 dark:text-white">Isolir Pelanggan Terpilih?</h3>
                <p className="text-xs text-gray-600 dark:text-slate-400">
                  Terapkan profil isolir untuk <span className="font-bold text-gray-900 dark:text-white">{selection.selectedIds.length}</span> pelanggan terpilih di MikroTik?
                </p>
              </div>
            </div>

            <div className="border-t border-gray-100 dark:border-[#1E2633] p-4 bg-white dark:bg-gray-900 shrink-0 flex items-center gap-2">
              <button
                type="button"
                className="flex-1 rounded-xl h-10 text-xs font-bold border border-gray-200 dark:border-gray-800 bg-gray-100 dark:bg-[#18202C] text-gray-700 dark:text-slate-300 hover:bg-gray-200 dark:hover:bg-[#1E2636] hover:text-gray-900 dark:hover:text-white transition-all cursor-pointer"
                onClick={() => setBatchIsolateModalOpen(false)}
                disabled={batchIsolating}
              >
                Batal
              </button>
              <button
                type="button"
                className="flex-1 rounded-xl h-10 text-xs font-bold bg-amber-600 hover:bg-amber-500 text-white transition-all flex items-center justify-center gap-1.5 disabled:opacity-50 cursor-pointer shadow-xs"
                disabled={batchIsolating}
                onClick={executeBatchIsolate}
              >
                {batchIsolating ? "Memproses..." : "Ya, Isolir"}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* ── MODAL KONFIRMASI HAPUS MULTI-SELECT ── */}
      {batchDeleteModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
          <div className="fixed inset-0 bg-black/70 backdrop-blur-sm" onClick={() => !batchDeleting && setBatchDeleteModalOpen(false)} />
          <div className="relative w-full max-w-sm overflow-hidden rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 shadow-2xl animate-in zoom-in-95 duration-200 text-gray-900 dark:text-white">
            <div className="p-6 space-y-4 text-center">
              <div className="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-rose-50 dark:bg-rose-500/10 border border-rose-200 dark:border-rose-500/20 text-rose-600 dark:text-rose-400">
                <Trash2 className="h-7 w-7" />
              </div>

              <div className="space-y-1">
                <h3 className="font-display text-base font-bold text-gray-900 dark:text-white">Hapus Pelanggan Terpilih?</h3>
                <p className="text-xs text-gray-600 dark:text-slate-400">
                  Apakah Anda yakin ingin menghapus <span className="font-bold text-gray-900 dark:text-white">{selection.selectedIds.length}</span> pelanggan terpilih dari sistem?
                </p>
              </div>

              <label className="flex items-start gap-3 p-3.5 rounded-2xl border border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-900/50 cursor-pointer hover:border-gray-400 dark:hover:border-slate-600 transition-colors text-left">
                <Checkbox
                  checked={deleteBatchFromMikrotik}
                  onChange={(checked) => setDeleteBatchFromMikrotik(checked)}
                />
                <div className="text-xs">
                  <span className="font-bold text-gray-900 dark:text-white block">Hapus juga dari Router MikroTik</span>
                  <span className="text-[11px] text-gray-500 dark:text-slate-400 block mt-0.5">Akun PPPoE atau IP Binding akan ikut dihapus dari MikroTik</span>
                </div>
              </label>
            </div>

            <div className="border-t border-gray-100 dark:border-[#1E2633] p-4 bg-white dark:bg-gray-900 shrink-0 flex items-center gap-2">
              <button
                type="button"
                className="flex-1 rounded-xl h-10 text-xs font-bold border border-gray-200 dark:border-gray-800 bg-gray-100 dark:bg-[#18202C] text-gray-700 dark:text-slate-300 hover:bg-gray-200 dark:hover:bg-[#1E2636] hover:text-gray-900 dark:hover:text-white transition-all cursor-pointer"
                onClick={() => setBatchDeleteModalOpen(false)}
                disabled={batchDeleting}
              >
                Batal
              </button>
              <button
                type="button"
                className="flex-1 rounded-xl h-10 text-xs font-bold bg-[#E11D48] hover:bg-[#BE123C] text-white transition-all flex items-center justify-center gap-1.5 disabled:opacity-50 cursor-pointer shadow-xs"
                disabled={batchDeleting}
                onClick={() => {
                  setBatchDeleting(true)
                  setSyncInfo({
                    show: true,
                    title: "Hapus Pelanggan Terpilih",
                    statusMessage: `Menghapus ${selection.selectedIds.length} pelanggan ${deleteBatchFromMikrotik ? "dan akun MikroTik" : ""}...`,
                  })
                  router.post(
                    "/admin/billing/customers/delete-batch",
                    {
                      ids: Array.from(selection.selectedIds),
                      customer_ids: Array.from(selection.selectedIds),
                      delete_pppoe: deleteBatchFromMikrotik,
                      delete_mikrotik: deleteBatchFromMikrotik,
                      force: true,
                    },
                    {
                      preserveScroll: true,
                      onFinish: () => {
                        setBatchDeleting(false)
                        setSyncInfo(null)
                        setBatchDeleteModalOpen(false)
                        selection.deselectAll()
                      },
                    }
                  )
                }}
              >
                {batchDeleting ? "Menghapus..." : `Hapus (${selection.selectedIds.length})`}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* ── MODAL SUKSES RESET PIN PELANGGAN ── */}
      {resetPinModalData && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
          <div className="fixed inset-0 bg-black/70 backdrop-blur-sm" onClick={() => setResetPinModalData(null)} />
          <div className="relative w-full max-w-sm overflow-hidden rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-6 shadow-2xl animate-in zoom-in-95 duration-200 text-center space-y-5 text-gray-900 dark:text-white">
            <div className="relative mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-blue-50 dark:bg-[#0B2138] border border-blue-200 dark:border-[#0073C6]/40 text-[#0073C6] dark:text-[#00C2FF]">
              <KeyRound className="h-8 w-8" />
              <CheckCircle2 className="absolute -bottom-1 -right-1 h-5 w-5 text-emerald-500 bg-white dark:bg-gray-900/50 rounded-full" />
            </div>

            <div className="space-y-1">
              <h3 className="font-display text-base sm:text-lg font-bold text-gray-900 dark:text-white">
                Kode PIN Berhasil Direset!
              </h3>
              <p className="text-xs text-gray-600 dark:text-slate-400">
                Kode PIN akses portal untuk <span className="font-semibold text-gray-900 dark:text-slate-200">{resetPinModalData.customer.name}</span>
              </p>
            </div>

            <div className="rounded-2xl border border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-900/50 p-4 space-y-2">
              <span className="text-[10px] uppercase font-bold text-gray-500 dark:text-slate-400 tracking-wider">Kode PIN Baru Portal Pelanggan</span>
              <div className="flex items-center justify-center gap-3">
                <span className="font-mono text-3xl font-black text-[#0073C6] dark:text-[#00C2FF] tracking-[0.25em]">
                  {resetPinModalData.pin}
                </span>
                <button
                  type="button"
                  onClick={() => handleCopyPin(resetPinModalData.pin)}
                  className="flex h-9 w-9 items-center justify-center rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-800 text-gray-700 dark:text-slate-300 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-white transition-all active:scale-95 cursor-pointer shadow-xs"
                  title="Salin PIN"
                >
                  {copiedPin ? <Check className="h-4 w-4 text-emerald-500" /> : <Copy className="h-4 w-4" />}
                </button>
              </div>
              {copiedPin && (
                <span className="text-[10px] font-bold text-emerald-600 dark:text-emerald-400 block animate-in fade-in">
                  Kode PIN berhasil disalin ke clipboard!
                </span>
              )}
            </div>

            <div className="space-y-2 pt-1">
              {resetPinModalData.customer.phone && (
                <button
                  type="button"
                  onClick={() => handleSendPinWa(resetPinModalData.customer, resetPinModalData.pin)}
                  className="w-full flex items-center justify-center gap-2 h-10 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-xs font-bold text-white transition-all active:scale-95 cursor-pointer shadow-xs"
                >
                  <MessageCircle className="h-4 w-4" />
                  <span>Kirim PIN via WhatsApp</span>
                </button>
              )}

              <button
                type="button"
                onClick={() => setResetPinModalData(null)}
                className="w-full flex items-center justify-center h-10 rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-100 dark:bg-gray-800 text-xs font-bold text-gray-700 dark:text-slate-300 hover:bg-gray-200 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-white transition-all cursor-pointer"
              >
                Tutup
              </button>
            </div>
          </div>
        </div>
      )}

      {/* ── MODAL KELOLA DATA EXCEL (EXPORT & IMPORT) ── */}
      {excelModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
          <div
            className="fixed inset-0 bg-black/70 backdrop-blur-sm animate-in fade-in"
            onClick={() => {
              if (!importing) {
                setExcelModalOpen(false)
                setImportResult(null)
              }
            }}
          />
          <div className="relative w-full max-w-lg overflow-hidden rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-6 shadow-2xl animate-in zoom-in-95 duration-200 space-y-5 text-gray-900 dark:text-white max-h-[92vh] overflow-y-auto custom-scrollbar">
            {/* Header */}
            <div className="flex items-center justify-between border-b border-gray-100 dark:border-[#1E334D] pb-4">
              <div className="flex items-center gap-3">
                <div className="flex h-10 w-10 items-center justify-center rounded-2xl bg-emerald-50 dark:bg-[#0073C6]/20 border border-emerald-200 dark:border-[#0073C6]/40 text-emerald-600 dark:text-[#00C2FF]">
                  <FileSpreadsheet className="h-5 w-5" />
                </div>
                <div>
                  <h3 className="font-display text-base font-bold text-gray-900 dark:text-white">
                    Kelola Data Pelanggan
                  </h3>
                  <p className="text-xs text-gray-500 dark:text-slate-400">
                    Export &amp; Import file Excel (.xlsx) atau CSV (.csv)
                  </p>
                </div>
              </div>
              <button
                type="button"
                disabled={importing}
                onClick={() => {
                  setExcelModalOpen(false)
                  setImportResult(null)
                }}
                className="flex h-8 w-8 items-center justify-center rounded-full border border-gray-200 dark:border-slate-700 bg-gray-100 dark:bg-slate-800 text-gray-500 dark:text-slate-400 hover:text-gray-900 dark:hover:text-white transition-all cursor-pointer whitespace-nowrap"
              >
                <X className="h-4 w-4" />
              </button>
            </div>

            {/* Tab Selector */}
            <div className="grid grid-cols-2 gap-2 p-1 rounded-2xl bg-gray-100 dark:bg-gray-900/50 border border-gray-200 dark:border-gray-800">
              <button
                type="button"
                onClick={() => setExcelModalTab("export")}
                className={cn(
                  "flex items-center justify-center gap-2 py-2 px-3 rounded-xl text-xs font-bold transition-all cursor-pointer",
                  excelModalTab === "export"
                    ? "bg-[#0073C6] text-white shadow-xs"
                    : "text-gray-600 dark:text-slate-400 hover:text-gray-900 dark:hover:text-white"
                )}
              >
                <Download className="h-4 w-4" />
                <span>Export Data</span>
              </button>
              <button
                type="button"
                onClick={() => setExcelModalTab("import")}
                className={cn(
                  "flex items-center justify-center gap-2 py-2 px-3 rounded-xl text-xs font-bold transition-all cursor-pointer",
                  excelModalTab === "import"
                    ? "bg-[#0073C6] text-white shadow-xs"
                    : "text-gray-600 dark:text-slate-400 hover:text-gray-900 dark:hover:text-white"
                )}
              >
                <Upload className="h-4 w-4" />
                <span>Import File</span>
              </button>
            </div>

            {/* TAB CONTENT: EXPORT */}
            {excelModalTab === "export" && (
              <div className="space-y-4 animate-in fade-in duration-150">
                <div className="rounded-2xl border border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-900 p-4 space-y-3">
                  <div>
                    <span className="text-xs font-bold text-gray-800 dark:text-slate-200 block">
                      Download Data Pelanggan Terdaftar
                    </span>
                    <span className="text-[10px] text-gray-500 dark:text-slate-400">
                      Unduh seluruh data pelanggan ({customers.length} data)
                    </span>
                  </div>

                  <div className="grid grid-cols-2 gap-2 pt-1">
                    <a
                      href="/admin/billing/customers/export?format=xlsx"
                      download
                      className="flex items-center justify-center gap-2 h-10 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-xs font-bold text-white transition-all shadow-xs active:scale-95 text-center px-2"
                    >
                      <FileSpreadsheet className="h-4 w-4 shrink-0" />
                      <span>Excel (.xlsx)</span>
                    </a>
                    <a
                      href="/admin/billing/customers/export?format=csv"
                      download
                      className="flex items-center justify-center gap-2 h-10 rounded-xl border border-gray-200 dark:border-slate-700 bg-white dark:bg-slate-800/80 hover:bg-gray-100 dark:hover:bg-slate-700 text-xs font-semibold text-gray-800 dark:text-slate-200 transition-all active:scale-95 text-center px-2 shadow-xs"
                    >
                      <Download className="h-4 w-4 shrink-0" />
                      <span>CSV (.csv)</span>
                    </a>
                  </div>
                </div>

                <div className="rounded-2xl border border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-900 p-4 space-y-3">
                  <div>
                    <span className="text-xs font-bold text-gray-800 dark:text-slate-200 block">
                      Download Format Template Kosong
                    </span>
                    <span className="text-[10px] text-gray-500 dark:text-slate-400">
                      Gunakan template resmi untuk mengisi data baru sebelum di-import
                    </span>
                  </div>

                  <div className="grid grid-cols-2 gap-2 pt-1">
                    <a
                      href="/admin/billing/customers/template?format=xlsx"
                      download
                      className="flex items-center justify-center gap-1.5 h-9 rounded-xl border border-emerald-300 dark:border-emerald-500/30 bg-emerald-50 dark:bg-emerald-500/10 hover:bg-emerald-100 dark:hover:bg-emerald-500/20 text-xs font-bold text-emerald-700 dark:text-emerald-400 transition-all active:scale-95 text-center px-2 shadow-xs"
                    >
                      <FileSpreadsheet className="h-3.5 w-3.5 shrink-0" />
                      <span>Template Excel</span>
                    </a>
                    <a
                      href="/admin/billing/customers/template?format=csv"
                      download
                      className="flex items-center justify-center gap-1.5 h-9 rounded-xl border border-gray-200 dark:border-slate-700 bg-white dark:bg-slate-800/80 hover:bg-gray-100 dark:hover:bg-slate-700 text-xs font-semibold text-gray-700 dark:text-slate-300 transition-all active:scale-95 text-center px-2 shadow-xs"
                    >
                      <Download className="h-3.5 w-3.5 shrink-0" />
                      <span>Template CSV</span>
                    </a>
                  </div>
                </div>
              </div>
            )}

            {/* TAB CONTENT: IMPORT */}
            {excelModalTab === "import" && (
              <div className="space-y-4 animate-in fade-in duration-150">
                {importResult?.success ? (
                  <div className="space-y-4 animate-in fade-in duration-200">
                    <div className="rounded-2xl p-5 border bg-emerald-50 dark:bg-emerald-950/40 border-emerald-200 dark:border-emerald-500/30 text-emerald-800 dark:text-emerald-300 text-center space-y-3">
                      <div className="flex flex-col items-center justify-center space-y-2">
                        <div className="flex h-12 w-12 items-center justify-center rounded-2xl bg-emerald-100 dark:bg-emerald-500/20 border border-emerald-300 dark:border-emerald-500/40 text-emerald-600 dark:text-emerald-400">
                          <CheckCircle2 className="h-7 w-7" />
                        </div>
                        <h4 className="text-base font-bold text-emerald-900 dark:text-emerald-100">
                          Import Data Berhasil Diselesaikan!
                        </h4>
                        <p className="text-xs text-emerald-700 dark:text-emerald-300 max-w-sm">
                          {importResult.message}
                        </p>
                      </div>

                      <div className="grid grid-cols-3 gap-2 pt-1 text-center">
                        <div className="rounded-xl border border-emerald-200/80 dark:border-emerald-500/20 bg-white/80 dark:bg-emerald-900/30 p-2.5">
                          <span className="text-[10px] uppercase font-bold text-gray-500 dark:text-emerald-400/80 block">Baru</span>
                          <span className="text-base font-extrabold text-emerald-700 dark:text-emerald-300 font-tabular-nums">{importResult.imported ?? 0}</span>
                        </div>
                        <div className="rounded-xl border border-emerald-200/80 dark:border-emerald-500/20 bg-white/80 dark:bg-emerald-900/30 p-2.5">
                          <span className="text-[10px] uppercase font-bold text-gray-500 dark:text-emerald-400/80 block">Diperbarui</span>
                          <span className="text-base font-extrabold text-blue-600 dark:text-blue-400 font-tabular-nums">{importResult.updated ?? 0}</span>
                        </div>
                        <div className="rounded-xl border border-emerald-200/80 dark:border-emerald-500/20 bg-white/80 dark:bg-emerald-900/30 p-2.5">
                          <span className="text-[10px] uppercase font-bold text-gray-500 dark:text-emerald-400/80 block">Dilewati</span>
                          <span className="text-base font-extrabold text-gray-600 dark:text-slate-400 font-tabular-nums">{importResult.skipped ?? 0}</span>
                        </div>
                      </div>

                      {importResult.errors && importResult.errors.length > 0 && (
                        <div className="space-y-1 max-h-32 overflow-y-auto pt-2 text-left font-mono text-[11px] text-amber-800 dark:text-amber-300 bg-amber-50 dark:bg-amber-950/40 p-3 rounded-xl border border-amber-200 dark:border-amber-500/30">
                          <div className="font-bold text-[10px] uppercase tracking-wider mb-1">Catatan / Peringatan:</div>
                          {importResult.errors.map((err, idx) => (
                            <div key={idx} className="flex items-start gap-1">
                              <span>•</span>
                              <span>{err}</span>
                            </div>
                          ))}
                        </div>
                      )}
                    </div>

                    <div className="flex items-center gap-2 pt-1">
                      <button
                        type="button"
                        onClick={() => {
                          setImportFile(null)
                          setImportResult(null)
                        }}
                        className="flex-1 h-10 rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-100 dark:bg-gray-800 text-xs font-bold text-gray-700 dark:text-slate-300 hover:bg-gray-200 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-white transition-all cursor-pointer"
                      >
                        Import File Lain
                      </button>
                      <button
                        type="button"
                        onClick={() => {
                          setExcelModalOpen(false)
                          setImportFile(null)
                          setImportResult(null)
                        }}
                        className="flex-[2] flex items-center justify-center gap-2 h-10 rounded-xl bg-gradient-to-r from-[#0073C6] to-[#00A3FF] hover:from-[#005FA3] hover:to-[#008BE6] text-xs font-bold text-white shadow-xs transition-all active:scale-95 cursor-pointer"
                      >
                        <span>Selesai &amp; Tutup</span>
                      </button>
                    </div>
                  </div>
                ) : (
                  <>
                    {importResult && !importResult.success && (
                      <div className="rounded-2xl p-4 border space-y-2 text-xs bg-rose-50 dark:bg-rose-950/40 border-rose-200 dark:border-rose-500/30 text-rose-800 dark:text-rose-300">
                        <div className="flex items-center gap-2 font-bold text-sm">
                          <AlertTriangle className="h-4 w-4 text-rose-600 dark:text-rose-400 shrink-0" />
                          <span>{importResult.message}</span>
                        </div>
                        {importResult.errors && importResult.errors.length > 0 && (
                          <div className="space-y-1 max-h-32 overflow-y-auto pt-1 font-mono text-[11px] text-gray-700 dark:text-slate-300">
                            {importResult.errors.map((err, idx) => (
                              <div key={idx} className="flex items-start gap-1 text-rose-600 dark:text-rose-300">
                                <span>•</span>
                                <span>{err}</span>
                              </div>
                            ))}
                          </div>
                        )}
                      </div>
                    )}

                    <form onSubmit={handleImportSubmit} className="space-y-4">
                      <div
                        className={cn(
                          "relative rounded-2xl border-2 border-dashed p-6 text-center transition-all cursor-pointer",
                          importFile
                            ? "border-emerald-500/60 bg-emerald-50/50 dark:bg-emerald-500/5"
                            : "border-gray-300 dark:border-gray-800 hover:border-[#0073C6] bg-gray-50 dark:bg-gray-900/50"
                        )}
                        onClick={() => document.getElementById("customer_import_input")?.click()}
                      >
                        <input
                          id="customer_import_input"
                          type="file"
                          accept=".xlsx,.xls,.csv"
                          className="hidden"
                          onChange={(e) => {
                            const f = e.target.files?.[0]
                            if (f) {
                              setImportFile(f)
                              setImportResult(null)
                            }
                          }}
                        />

                        <div className="flex flex-col items-center justify-center space-y-2 pointer-events-none">
                          <div
                            className={cn(
                              "flex h-12 w-12 items-center justify-center rounded-2xl border transition-all",
                              importFile
                                ? "bg-emerald-100 dark:bg-emerald-500/20 border-emerald-300 dark:border-emerald-500/40 text-emerald-600 dark:text-emerald-400"
                                : "bg-white dark:bg-gray-800 border-gray-200 dark:border-gray-800 text-[#0073C6] dark:text-[#00C2FF]"
                            )}
                          >
                            {importFile ? (
                              <FileSpreadsheet className="h-6 w-6" />
                            ) : (
                              <FileUp className="h-6 w-6" />
                            )}
                          </div>

                          {importFile ? (
                            <div className="space-y-0.5">
                              <p className="text-xs font-bold text-emerald-700 dark:text-emerald-300 truncate max-w-xs mx-auto">
                                {importFile.name}
                              </p>
                              <p className="text-[10px] text-gray-500 dark:text-slate-400">
                                {(importFile.size / 1024).toFixed(1)} KB · Klik untuk ganti file
                              </p>
                            </div>
                          ) : (
                            <div className="space-y-0.5">
                              <p className="text-xs font-bold text-gray-800 dark:text-slate-200">
                                Pilih file Excel / CSV atau Drag &amp; Drop ke sini
                              </p>
                              <p className="text-[10px] text-gray-500 dark:text-slate-400">
                                Format didukung: .xlsx, .xls, .csv
                              </p>
                            </div>
                          )}
                        </div>
                      </div>

                      <div className="space-y-3 rounded-2xl border border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-900 p-4">
                        <label className="flex items-start gap-2.5 cursor-pointer select-none">
                          <Checkbox
                            checked={importCreatePppoe}
                            onChange={(checked) => setImportCreatePppoe(checked)}
                          />
                          <div className="text-xs">
                            <span className="font-bold text-gray-800 dark:text-slate-200 block">
                              Otomatis sinkron ke MikroTik (PPPoE Secret &amp; ARP Binding)
                            </span>
                            <span className="text-[10px] text-gray-500 dark:text-slate-400">
                              Akun PPPoE / Binding ARP akan langsung didaftarkan ke router MikroTik saat import
                            </span>
                          </div>
                        </label>

                        {routers && routers.length > 0 && (
                          <div className="pt-2 border-t border-gray-200 dark:border-gray-800 space-y-1">
                            <label className="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-slate-400">
                              Target Router MikroTik (Opsional)
                            </label>
                            <select
                              value={importRouterId}
                              onChange={(e) => setImportRouterId(e.target.value)}
                              className="w-full h-9 rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900/50 px-3 text-xs text-gray-800 dark:text-slate-200 focus:border-[#0073C6] focus:outline-none"
                            >
                              <option value="">-- Ikuti Router Default / Sesuai Data --</option>
                              {routers.map((r) => (
                                <option key={r.id} value={r.id}>
                                  {r.name}
                                </option>
                              ))}
                            </select>
                          </div>
                        )}
                      </div>

                      <div className="flex items-center gap-2 pt-1">
                        <button
                          type="button"
                          disabled={importing}
                          onClick={() => {
                            setExcelModalOpen(false)
                            setImportResult(null)
                          }}
                          className="flex-1 h-10 rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-100 dark:bg-gray-800 text-xs font-bold text-gray-700 dark:text-slate-300 hover:bg-gray-200 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-white transition-all cursor-pointer"
                        >
                          Batal
                        </button>
                        <button
                          type="submit"
                          disabled={!importFile || importing}
                          className="flex-[2] flex items-center justify-center gap-2 h-10 rounded-xl bg-gradient-to-r from-[#0073C6] to-[#00A3FF] hover:from-[#005FA3] hover:to-[#008BE6] text-xs font-bold text-white shadow-xs transition-all active:scale-95 disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer"
                        >
                          {importing ? (
                            <>
                              <RefreshCw className="h-4 w-4 animate-spin" />
                              <span>Mengimport Data...</span>
                            </>
                          ) : (
                            <>
                              <Upload className="h-4 w-4" />
                              <span>Mulai Import Excel</span>
                            </>
                          )}
                        </button>
                      </div>
                    </form>
                  </>
                )}
              </div>
            )}
          </div>
        </div>
      )}

      {/* Sync Router Dialog */}
      <SyncRouterDialog open={syncOpen} onClose={() => setSyncOpen(false)} routers={routers} />

      {/* Sync Info 2-Way Guide Modal */}
      <SyncInfoModal open={infoModalOpen} onClose={() => setInfoModalOpen(false)} />

      {/* Unified MikroTik Sync Overlay */}
      <SyncOverlay
        show={Boolean(syncInfo?.show)}
        title={syncInfo?.title}
        statusMessage={syncInfo?.statusMessage}
      />
    
      {/* ═══════════════════════════════════════════════════════════════════ */}
      {/* POPUP MODAL KELOLA PELANGGAN (INTERACTIVE MODAL) */}
      {/* ═══════════════════════════════════════════════════════════════════ */}
      {managingCustomer && (
        <div
          className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-xs animate-in fade-in"
          onClick={() => setManagingCustomer(null)}
        >
          <div
            className="w-full max-w-lg rounded-2xl border border-gray-200 bg-white p-5 sm:p-6 shadow-2xl dark:border-gray-800 dark:bg-gray-900 space-y-4 max-h-[92vh] overflow-y-auto"
            onClick={(e) => e.stopPropagation()}
          >
            {/* Modal Header */}
            <div className="flex items-start justify-between border-b border-gray-100 pb-3 dark:border-gray-800">
              <div className="min-w-0">
                <h3 className="text-base font-bold text-gray-900 dark:text-white line-clamp-1">
                  {managingCustomer.name}
                </h3>
                <div className="flex items-center gap-2 mt-1">
                  <span
                    className={cn(
                      "inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold text-white shadow-xs",
                      managingCustomer.status === "active"
                        ? "bg-emerald-500"
                        : managingCustomer.status === "isolated"
                        ? "bg-rose-500"
                        : "bg-gray-500"
                    )}
                  >
                    {managingCustomer.status === "active" ? "Aktif" : managingCustomer.status === "isolated" ? "Terisolir" : "Nonaktif"}
                  </span>
                  {managingCustomer.code && (
                    <span className="font-mono text-xs font-semibold text-gray-500 dark:text-gray-400">
                      #{managingCustomer.code}
                    </span>
                  )}
                </div>
              </div>
              <button
                type="button"
                onClick={() => setManagingCustomer(null)}
                className="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 transition cursor-pointer"
              >
                <X className="h-5 w-5" />
              </button>
            </div>

            {/* Sunken detail boxes */}
            <div className="space-y-3">
              {/* Kontak & WhatsApp */}
              <div className="rounded-xl border border-gray-100 bg-gray-50/80 dark:border-gray-800 dark:bg-gray-900/50 p-3.5 space-y-2 text-xs">
                <div className="flex items-center justify-between">
                  <span className="text-gray-500 dark:text-gray-400 flex items-center gap-1.5">
                    <Phone className="h-3.5 w-3.5 text-emerald-500" /> WhatsApp / Kontak
                  </span>
                  {managingCustomer.phone ? (
                    <button
                      type="button"
                      onClick={(e) => handleChatCustomerWa(e, managingCustomer)}
                      className="inline-flex items-center gap-1 font-bold text-emerald-600 dark:text-emerald-400 hover:underline cursor-pointer"
                    >
                      <MessageCircle className="h-3.5 w-3.5" />
                      <span>{managingCustomer.phone}</span>
                    </button>
                  ) : (
                    <span className="text-gray-400">-</span>
                  )}
                </div>

                <div className="flex items-center justify-between">
                  <span className="text-gray-500 dark:text-gray-400 flex items-center gap-1.5">
                    <MapPin className="h-3.5 w-3.5 text-blue-500" /> Lokasi / Alamat
                  </span>
                  <div className="text-right">
                    <span className="font-semibold text-gray-900 dark:text-white truncate max-w-[200px] block">
                      {managingCustomer.address || "-"}
                    </span>
                    {managingCustomer.lat && managingCustomer.lng && (
                      <a
                        href={`https://www.google.com/maps?q=${managingCustomer.lat},${managingCustomer.lng}`}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="text-[11px] text-brand-500 hover:underline inline-flex items-center gap-0.5"
                      >
                        <ExternalLink className="h-3 w-3" /> Peta Navigasi GPS
                      </a>
                    )}
                  </div>
                </div>
              </div>

              {/* Paket & Tagihan */}
              <div className="rounded-xl border border-gray-100 bg-gray-50/80 dark:border-gray-800 dark:bg-gray-900/50 p-3.5 space-y-2 text-xs">
                <div className="flex items-center justify-between">
                  <span className="text-gray-500 dark:text-gray-400">Paket Langganan</span>
                  <span className="font-bold text-gray-900 dark:text-white">
                    {managingCustomer.package || "Tanpa Paket"}
                  </span>
                </div>

                <div className="flex items-center justify-between">
                  <span className="text-gray-500 dark:text-gray-400">Jatuh Tempo Bulanan</span>
                  <span className="font-medium text-gray-900 dark:text-white">
                    Tgl {managingCustomer.isolation_date ?? "20"} setiap bulan
                  </span>
                </div>

                <div className="flex items-center justify-between">
                  <span className="text-gray-500 dark:text-gray-400">Status Tagihan</span>
                  {managingCustomer.unpaid_invoices > 0 ? (
                    <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold bg-rose-500 text-white shadow-xs whitespace-nowrap">
                      {managingCustomer.unpaid_invoices} Belum Lunas
                    </span>
                  ) : (
                    <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold bg-emerald-500 text-white shadow-xs whitespace-nowrap">
                      Lunas
                    </span>
                  )}
                </div>
              </div>

              {/* Koneksi & MikroTik */}
              <div className="rounded-xl border border-gray-100 bg-gray-50/80 dark:border-gray-800 dark:bg-gray-900/50 p-3.5 space-y-2 text-xs">
                <div className="flex items-center justify-between">
                  <span className="text-gray-500 dark:text-gray-400">Koneksi / IP / Secret</span>
                  <div className="flex items-center gap-1.5 font-mono font-bold text-gray-900 dark:text-white">
                    <span>{managingCustomer.pppoe_username || managingCustomer.ip_address || "-"}</span>
                    {managingCustomer.pppoe_password && (
                      <button
                        type="button"
                        onClick={() => toggleRevealPassword(managingCustomer.id)}
                        className="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                      >
                        {revealedPasswords[managingCustomer.id] ? <EyeOff className="h-3.5 w-3.5" /> : <Eye className="h-3.5 w-3.5" />}
                      </button>
                    )}
                  </div>
                </div>
                {revealedPasswords[managingCustomer.id] && managingCustomer.pppoe_password && (
                  <div className="flex items-center justify-between text-brand-600 dark:text-brand-400 font-mono">
                    <span>Password PPPoE:</span>
                    <span className="font-bold">{managingCustomer.pppoe_password}</span>
                  </div>
                )}

                <div className="flex items-center justify-between">
                  <span className="text-gray-500 dark:text-gray-400">Router / ODP</span>
                  <span className="font-medium text-gray-900 dark:text-white">
                    {managingCustomer.router || "Default Router"} {managingCustomer.odp_name ? `• ${managingCustomer.odp_name}` : ""}
                  </span>
                </div>
              </div>
            </div>

            {/* Action Buttons in Modal - 1 Single Unified Row by Icon (Solid Colors) */}
            <div className="flex items-center justify-between gap-2 pt-3 border-t border-gray-100 dark:border-gray-800">
              {/* Left group of icon actions */}
              <div className="flex items-center gap-1.5 sm:gap-2">
                {/* 1. Chat WhatsApp */}
                {managingCustomer.phone && (
                  <button
                    type="button"
                    onClick={(e) => handleChatCustomerWa(e, managingCustomer)}
                    title="Chat WhatsApp Pelanggan"
                    className="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white transition shadow-xs cursor-pointer shrink-0"
                  >
                    <MessageCircle className="h-4 w-4" />
                  </button>
                )}

                {/* 2. Buat Tiket */}
                <button
                  type="button"
                  onClick={() => {
                    const c = managingCustomer
                    setManagingCustomer(null)
                    openTicketModal(c)
                  }}
                  title="Buat Tiket Gangguan"
                  className="flex h-10 w-10 items-center justify-center rounded-xl bg-blue-600 hover:bg-blue-700 text-white transition shadow-xs cursor-pointer shrink-0"
                >
                  <Wrench className="h-4 w-4" />
                </button>

                {/* 3. Reset PIN */}
                <button
                  type="button"
                  onClick={(e) => handleResetPin(e, managingCustomer)}
                  title="Reset PIN Customer Portal"
                  className="flex h-10 w-10 items-center justify-center rounded-xl bg-amber-500 hover:bg-amber-600 text-white transition shadow-xs cursor-pointer shrink-0"
                >
                  <Key className="h-4 w-4" />
                </button>

                {/* 4. Isolir / Buka Isolir */}
                {(managingCustomer.pppoe_username || managingCustomer.ip_address) && (
                  managingCustomer.status === "isolated" ? (
                    <button
                      type="button"
                      onClick={() => {
                        const c = managingCustomer
                        setManagingCustomer(null)
                        if (confirm(`Buka isolir koneksi untuk ${c.name}?`)) {
                          setSyncInfo({
                            show: true,
                            title: "Buka Isolir Pelanggan",
                            statusMessage: `Mengembalikan profil normal & membuka koneksi ${c.name} di MikroTik...`,
                          })
                          router.post(`/admin/billing/customers/unisolate/${c.id}`, {}, {
                            preserveScroll: true,
                            onFinish: () => setSyncInfo(null),
                          })
                        }
                      }}
                      title="Buka Isolir Koneksi"
                      className="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white transition shadow-xs cursor-pointer shrink-0"
                    >
                      <Power className="h-4 w-4" />
                    </button>
                  ) : (
                    <button
                      type="button"
                      onClick={() => {
                        const c = managingCustomer
                        setManagingCustomer(null)
                        if (confirm(`Isolir koneksi pelanggan ${c.name}?`)) {
                          setSyncInfo({
                            show: true,
                            title: "Isolir Pelanggan",
                            statusMessage: `Menerapkan profil isolir untuk ${c.name} di router MikroTik...`,
                          })
                          router.post(`/admin/billing/customers/isolate/${c.id}`, {}, {
                            preserveScroll: true,
                            onFinish: () => setSyncInfo(null),
                          })
                        }
                      }}
                      title="Isolir Koneksi MikroTik"
                      className="flex h-10 w-10 items-center justify-center rounded-xl bg-amber-500 hover:bg-amber-600 text-white transition shadow-xs cursor-pointer shrink-0"
                    >
                      <WifiOff className="h-4 w-4" />
                    </button>
                  )
                )}

                {/* 5. Hapus Pelanggan */}
                <button
                  type="button"
                  onClick={() => {
                    const c = managingCustomer
                    setManagingCustomer(null)
                    setDeleteTarget(c)
                  }}
                  title="Hapus Data Pelanggan"
                  className="flex h-10 w-10 items-center justify-center rounded-xl bg-rose-600 hover:bg-rose-700 text-white transition shadow-xs cursor-pointer shrink-0"
                >
                  <Trash2 className="h-4 w-4" />
                </button>
              </div>

              {/* Right: Edit Data Action (Icon-Only Solid Button) */}
              <button
                type="button"
                onClick={() => {
                  const c = managingCustomer
                  setManagingCustomer(null)
                  openEdit(c)
                }}
                title="Edit Data Pelanggan"
                className="flex h-10 w-10 items-center justify-center rounded-xl bg-brand-500 hover:bg-brand-600 active:scale-95 text-white shadow-xs transition cursor-pointer shrink-0"
              >
                <Pencil className="h-4 w-4" />
              </button>
            </div>
          </div>
        </div>
      )}

    </AppLayout>
  )
}
