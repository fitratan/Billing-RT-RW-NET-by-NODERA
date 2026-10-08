import { AppLayout } from "@/components/layout/app-layout"
import { ViewModeSwitcher, type ViewMode } from "@/components/ui/view-mode-switcher"
import {
  MapPin,
  ChevronLeft,
  ChevronDown,
  Users,
  Layers,
  Search,
  Navigation,
  Copy,
  Plus,
  Filter,
  Pencil,
  Trash2,
  RefreshCw,
  SlidersHorizontal,
  Activity,
  CheckCircle2,
  Cable,
  Server,
  Home,
  Check,
  X,
  Phone,
  Receipt,
  CalendarClock,
  ExternalLink,
  MessageCircle,
  Hash,
  Eye,
  Globe,
  Network,
  Cpu,
  ZoomIn,
  ZoomOut,
  Crosshair,
  Clock,
  ArrowDown,
  ArrowUp,
  Database,
  HardDrive,
  LocateFixed,
  UserPlus,
  UserCheck,
  Route,
  RotateCcw,
  Save,
  Radio,
  AlertCircle,
  Lock,
  Unlock,
  Maximize2,
  Minimize2,
} from "lucide-react"
import { PageProps } from "@/types"
import { adminSidebarItems, adminNavItems, adminBrand } from "@/lib/admin-nav"
import { technicianSidebarItems, technicianNavItems, technicianBrand } from "@/lib/technician-nav"
import { collectorSidebarItems, collectorNavItems, collectorBrand } from "@/lib/collector-nav"
import { Link, router, useForm } from "@inertiajs/react"
import { cn } from "@/lib/utils"
import { useEffect, useRef, useState, useMemo } from "react"
import "leaflet/dist/leaflet.css"
import L from "leaflet"
import { Dialog, DialogContent, DialogHeader, DialogTitle } from "@/components/ui/dialog"
import axios from "axios"

// ── SAFEGUARD LEAFLET AGAINST '_leaflet_pos' UNDEFINED ERRORS (ZOOM / PAN / TRANSITION / UNMOUNT) ──
if (typeof window !== "undefined" && L && L.DomUtil) {
  const origGetPosition = L.DomUtil.getPosition
  L.DomUtil.getPosition = function (el: any) {
    if (!el) {
      return new L.Point(0, 0)
    }
    try {
      return origGetPosition ? origGetPosition.call(this, el) : ((el as any)._leaflet_pos || new L.Point(0, 0))
    } catch {
      return (el as any)?._leaflet_pos || new L.Point(0, 0)
    }
  }

  if (L.Map) {
    const proto = L.Map.prototype as any
    if (proto._getMapPanePos) {
      const origGetMapPanePos = proto._getMapPanePos
      proto._getMapPanePos = function () {
        if (!this._mapPane) {
          return new L.Point(0, 0)
        }
        try {
          return origGetMapPanePos.call(this)
        } catch {
          return new L.Point(0, 0)
        }
      }
    }

    if (proto._onZoomTransitionEnd) {
      const origOnZoomTransitionEnd = proto._onZoomTransitionEnd
      proto._onZoomTransitionEnd = function () {
        if (!this._mapPane || !this._loaded) {
          this._animatingZoom = false
          return
        }
        try {
          origOnZoomTransitionEnd.call(this)
        } catch (e) {
          this._animatingZoom = false
        }
      }
    }

    if (proto._move) {
      const origMove = proto._move
      proto._move = function (center: any, zoom: any, data: any, flags: any) {
        if (!this._mapPane || !this._loaded) {
          return this
        }
        try {
          return origMove.call(this, center, zoom, data, flags)
        } catch {
          return this
        }
      }
    }
  }
}

export interface RouterMarker {
  id: number
  name: string
  host: string
  port: number
  is_active: boolean
  lat: number | null
  lng: number | null
  location?: string
  active_sessions_count?: number
  customers_count?: number
}

export interface SimpleCustomer {
  id: number
  name: string
  code: string
  pppoe_username?: string
  address?: string
  phone?: string
  odp_id?: number | null
  odp_name?: string | null
  odp_port?: number | null
  cable_path?: [number, number][]
  lat?: number
  lng?: number
  status?: string
  package_name?: string
  router_id?: number | null
  router_name?: string | null
}

export interface Odp {
  id: number
  name: string
  type?: "odc" | "odp" | "htb" | "switch"
  network_mode?: "pon" | "lan"
  lat: number
  lng: number
  onus_count: number
  customers_count?: number
  used_ports: number
  capacity: number
  available_ports?: number
  parent_odp_id?: number | null
  parent_name?: string | null
  router_id?: number | null
  router_name?: string | null
  cable_path?: [number, number][]
  total_clients?: number
  online_clients?: number
  is_full?: boolean
  is_critical?: boolean
  is_available?: boolean
  location?: string | null
}

export interface OnuMarker {
  id?: number
  name: string
  lat: number
  lng: number
  odp: string | null
  odp_id?: number | null
  customer: string | null
  customer_id?: number | null
  serial?: string
}

export interface CustomerMarker {
  id: number
  name: string
  code?: string | null
  pppoe_username?: string | null
  ip_address?: string | null
  mac_address?: string | null
  connection_type?: string | null
  uptime?: string | null
  is_online?: boolean
  mikrotik_reachable?: boolean
  rx_bytes?: string | null
  tx_bytes?: string | null
  total_bytes?: string | null
  rx_bytes_raw?: number
  tx_bytes_raw?: number
  profile?: string | null
  remote_address?: string | null
  local_address?: string | null
  router_name?: string | null
  router_ip?: string | null
  phone?: string | null
  email?: string | null
  address?: string | null
  package_id?: number | null
  package_name?: string | null
  package_price?: number | null
  status: string
  isolation_date?: string | null
  unpaid_invoices?: number
  lat: number
  lng: number
  odp_id?: number | null
  odp_name?: string | null
  odp_port?: number | null
  cable_path?: [number, number][]
  rx_power?: number | string | null
  tx_power?: number | string | null
  onu_serial?: string | null
  olt_name?: string | null
  pon_port?: string | null
}

export interface ConnectionLine {
  id: string
  category?: "backbone" | "feeder" | "drop_wire"
  source_type?: string
  source_id?: number
  source_name?: string
  target_type?: string
  target_id?: number
  target_name?: string
  odp_id?: number | null
  odp_name?: string | null
  customer_id?: number | null
  customer_name: string
  customer_pppoe?: string
  status: string
  is_connected: boolean
  odp_lat: number
  odp_lng: number
  target_lat: number
  target_lng: number
  odp_port?: number | null
  waypoints?: [number, number][]
  online_clients?: number
  total_clients?: number
}

interface PingResult {
  success: boolean
  ip: string
  target: string
  transmitted: number
  received: number
  loss_percent: number
  min_ms: number
  avg_ms: number
  max_ms: number
  jitter_ms: number
  samples: number[]
  quality: string
  status: "ONLINE" | "RTO" | "OFFLINE"
  source?: string
  timestamp: string
}

type MapStyle = "satellite" | "dark" | "streets" | "terrain"

const TILE_LAYERS: Record<MapStyle, { name: string; url: string; subdomains?: string[]; attribution: string; maxZoom: number }> = {
  satellite: {
    name: "Satelit Medan (Hybrid)",
    url: "https://mt1.google.com/vt/lyrs=y&x={x}&y={y}&z={z}",
    attribution: "&copy; Google Maps Satellite",
    maxZoom: 20,
  },
  dark: {
    name: "Mode Gelap (Cyber)",
    url: "https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png",
    subdomains: ["a", "b", "c", "d"],
    attribution: "&copy; CARTO Dark",
    maxZoom: 20,
  },
  streets: {
    name: "Mode Terang (Jalan)",
    url: "https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png",
    subdomains: ["a", "b", "c", "d"],
    attribution: "&copy; CARTO Voyager",
    maxZoom: 20,
  },
  terrain: {
    name: "Topografi & Kontur",
    url: "https://mt1.google.com/vt/lyrs=p&x={x}&y={y}&z={z}",
    attribution: "&copy; Google Terrain",
    maxZoom: 20,
  },
}

const SPECIAL_CITIES: Record<string, string> = {
  STABAT: "STB",
  LANGKAT: "LKT",
  MEDAN: "MDN",
  BINJAI: "BNJ",
  DELISERDANG: "DLS",
  JAKARTA: "JKT",
  SURABAYA: "SBY",
  BANDUNG: "BDG",
  SEMARANG: "SMG",
  YOGYAKARTA: "YOG",
  SOLO: "SLO",
  MALANG: "MLG",
  DENPASAR: "DPS",
  MAKASSAR: "MKS",
  PALEMBANG: "PLG",
  PEKANBARU: "PKU",
  PADANG: "PDG",
  ACEH: "ACH",
  BOGOR: "BGR",
  BEKASI: "BKS",
  TANGERANG: "TNG",
  DEPOK: "DPK",
}

function getRouterLocationCode(router?: RouterMarker | null): string {
  if (!router) return "STB"

  const candidates = [router.location, router.name].filter(Boolean) as string[]

  for (const raw of candidates) {
    if (!raw || typeof raw !== "string") continue
    // 1. Cek kode singkatan dalam kurung atau strip, contoh "Stabat (STB)" atau "Mikrotik-STB"
    const matchParen = raw.match(/\(([A-Za-z]{2,5})\)/)
    if (matchParen && matchParen[1] && !["NOC", "ROUTER", "SERVER"].includes((matchParen[1] || "").toUpperCase())) {
      return matchParen[1].toUpperCase()
    }

    const matchDash = raw.match(/[-_]([A-Za-z]{2,5})/i)
    if (matchDash && matchDash[1] && !["NOC", "ROUTER", "SERVER"].includes((matchDash[1] || "").toUpperCase())) {
      return matchDash[1].toUpperCase()
    }

    // 2. Bersihkan string dari SEMUA garis miring (/), backslash (\), angka, simbol
    const cleaned = (raw || "")
      .toUpperCase()
      .replace(/[^A-Z\s]/g, " ")
      .replace(/\s+/g, " ")
      .trim()

    const STOP_WORDS = new Set([
      "ROUTER", "SERVER", "NOC", "CORE", "GATEWAY", "MIKROTIK", "UTAMA", "PUSAT",
      "KABUPATEN", "KAB", "KOTA", "KECAMATAN", "KEC", "DESA", "KELURAHAN", "DEFAULT"
    ])

    const meaningfulWords = cleaned.split(/\s+/).filter((w) => w.length >= 2 && !STOP_WORDS.has(w))

    for (const word of meaningfulWords) {
      if (SPECIAL_CITIES[word]) {
        return SPECIAL_CITIES[word]
      }
    }

    if (meaningfulWords.length > 0) {
      const firstWord = meaningfulWords[0]
      if (firstWord.length >= 3) {
        const firstLetter = firstWord[0]
        const consonants = firstWord.substring(1).replace(/[AEIOU]/g, "")
        if (consonants.length >= 2) {
          return (firstLetter + consonants.substring(0, 2)).toUpperCase()
        }
        return firstWord.substring(0, 3).toUpperCase()
      }
    }
  }

  return "STB"
}

function generateDeviceName(
  type: "odc" | "odp" | "htb" | "switch",
  routerId: number | string | null | undefined,
  existingOdps: Odp[],
  routers: RouterMarker[]
): string {
  const targetRouter = routers.find((r) => String(r.id) === String(routerId)) || routers[0]
  const locCode = getRouterLocationCode(targetRouter)
  const prefix = `${type.toUpperCase()}-${locCode}-`

  let maxSeq = 0
  existingOdps.forEach((o) => {
    const oName = (o.name || "").toUpperCase().trim()
    if (oName.startsWith(prefix)) {
      const numPart = parseInt(oName.replace(prefix, "").trim(), 10)
      if (!isNaN(numPart) && numPart > maxSeq) {
        maxSeq = numPart
      }
    } else if (o.type === type) {
      const matchNum = oName.match(/(\d+)$/)
      if (matchNum) {
        const num = parseInt(matchNum[1], 10)
        if (!isNaN(num) && num > maxSeq) {
          maxSeq = num
        }
      }
    }
  })

  const nextSeq = String(maxSeq + 1).padStart(3, "0")
  return `${prefix}${nextSeq}`
}

function getDistanceMeters(lat1: number, lon1: number, lat2: number, lon2: number): number {
  const R = 6371e3
  const φ1 = (lat1 * Math.PI) / 180
  const φ2 = (lat2 * Math.PI) / 180
  const Δφ = ((lat2 - lat1) * Math.PI) / 180
  const Δλ = ((lon2 - lon1) * Math.PI) / 180
  const a =
    Math.sin(Δφ / 2) * Math.sin(Δφ / 2) +
    Math.cos(φ1) * Math.cos(φ2) *
    Math.sin(Δλ / 2) * Math.sin(Δλ / 2)
  const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a))
  return Math.round(R * c)
}

function calculatePathLengthMeters(points: [number, number][]): number {
  if (points.length < 2) return 0
  let total = 0
  for (let i = 0; i < points.length - 1; i++) {
    total += getDistanceMeters(points[i][0], points[i][1], points[i + 1][0], points[i + 1][1])
  }
  return total
}

function insertWaypointAtClosestSegment(
  newPoint: [number, number],
  allPoints: [number, number][]
): [number, number][] {
  if (allPoints.length <= 2) {
    return [newPoint]
  }

  let minDetour = Infinity
  let bestInsertIndex = 0

  for (let i = 0; i < allPoints.length - 1; i++) {
    const p1 = allPoints[i]
    const p2 = allPoints[i + 1]
    const d1 = getDistanceMeters(p1[0], p1[1], newPoint[0], newPoint[1])
    const d2 = getDistanceMeters(newPoint[0], newPoint[1], p2[0], p2[1])
    const segmentLength = getDistanceMeters(p1[0], p1[1], p2[0], p2[1])
    const detour = d1 + d2 - segmentLength
    if (detour < minDetour) {
      minDetour = detour
      bestInsertIndex = i
    }
  }

  const currentWaypoints = allPoints.slice(1, -1)
  const newWaypoints = [...currentWaypoints]
  newWaypoints.splice(bestInsertIndex, 0, newPoint)
  return newWaypoints
}

export default function MapPage({
  routers = [],
  odps = [],
  onusMarkers = [],
  customerMarkers = [],
  allCustomers = [],
  connections = [],
  create = false,
  readOnly = false,
  role = "admin",
  companyName = "NODERA Billing",
  tenantName,
}: PageProps<{
  routers?: RouterMarker[]
  odps: Odp[]
  onusMarkers: OnuMarker[]
  odpMarkers?: Odp[]
  customerMarkers?: CustomerMarker[]
  allCustomers?: SimpleCustomer[]
  connections?: ConnectionLine[]
  create?: boolean
  readOnly?: boolean
  role?: "admin" | "technician" | "collector"
  companyName?: string
  tenantName?: string
}>) {
  const mapContainerRef = useRef<HTMLDivElement>(null)
  const mapInstanceRef = useRef<L.Map | null>(null)
  const tileLayerRef = useRef<L.TileLayer | null>(null)
  const layerGroupRef = useRef<L.LayerGroup | null>(null)
  const linesGroupRef = useRef<L.LayerGroup | null>(null)
  const clickedCoordMarkerRef = useRef<L.Marker | null>(null)
  const initialFitDoneRef = useRef(false)

  const toArray = <T,>(val: any): T[] => {
    if (Array.isArray(val)) return val
    if (val && typeof val === "object") return Object.values(val)
    return []
  }

  const [routersState, setRoutersState] = useState<RouterMarker[]>(() => toArray<RouterMarker>(routers))
  const [odpsState, setOdpsState] = useState<Odp[]>(() => toArray<Odp>(odps))
  const [customerMarkersState, setCustomerMarkersState] = useState<CustomerMarker[]>(() => toArray<CustomerMarker>(customerMarkers))
  const [allCustomersState, setAllCustomersState] = useState<SimpleCustomer[]>(() => toArray<SimpleCustomer>(allCustomers))
  const [connectionsState, setConnectionsState] = useState<ConnectionLine[]>(() => toArray<ConnectionLine>(connections))

  useEffect(() => { setRoutersState(toArray<RouterMarker>(routers)) }, [routers])
  useEffect(() => { setOdpsState(toArray<Odp>(odps)) }, [odps])
  useEffect(() => { setCustomerMarkersState(toArray<CustomerMarker>(customerMarkers)) }, [customerMarkers])
  useEffect(() => { setAllCustomersState(toArray<SimpleCustomer>(allCustomers)) }, [allCustomers])
  useEffect(() => { setConnectionsState(toArray<ConnectionLine>(connections)) }, [connections])

  const isAdmin = !readOnly && (!role || role === "admin" || (role as any) === "superadmin")
  const isAdminRef = useRef(isAdmin)
  useEffect(() => {
    isAdminRef.current = isAdmin
  }, [isAdmin])

  // Persist Admin Lock Mode (Anti-Accidental Drag/Touch on Map)
  const [isLocked, setIsLocked] = useState<boolean>(() => {
    if (typeof window !== "undefined") {
      const saved = localStorage.getItem("nodera_map_admin_locked")
      return saved !== null ? saved === "true" : false
    }
    return false
  })

  const isLockedRef = useRef(isLocked)
  useEffect(() => {
    isLockedRef.current = isLocked
  }, [isLocked])

  const canEdit = isAdmin && !isLocked

  const [isFullscreen, setIsFullscreen] = useState(false)

  useEffect(() => {
    const handleFullscreenChange = () => {
      setIsFullscreen(Boolean(document.fullscreenElement))
    }
    document.addEventListener("fullscreenchange", handleFullscreenChange)
    return () => document.removeEventListener("fullscreenchange", handleFullscreenChange)
  }, [])

  const toggleFullscreen = () => {
    if (!document.fullscreenElement) {
      const elem = mapContainerRef.current?.parentElement || document.documentElement
      if (elem.requestFullscreen) {
        elem.requestFullscreen().catch(() => {})
      }
    } else {
      if (document.exitFullscreen) {
        document.exitFullscreen().catch(() => {})
      }
    }
  }

  const toggleAdminLock = () => {
    const next = !isLocked
    setIsLocked(next)
    isLockedRef.current = next
    if (typeof window !== "undefined") {
      localStorage.setItem("nodera_map_admin_locked", String(next))
    }
    showToast(
      next
        ? "Kunci Admin Aktif: Marker diproteksi dari pergeseran/sentuhan tidak sengaja."
        : "Kunci Admin Nonaktif: Mode edit & geser marker aktif."
    )
  }

  // Hitung live statistik klien (total & online) per ODP secara real-time dari data pelanggan
  const odpClientStats = useMemo(() => {
    const stats: Record<number, { total: number; online: number }> = {}
    customerMarkersState.forEach((c) => {
      if (c.odp_id) {
        if (!stats[c.odp_id]) {
          stats[c.odp_id] = { total: 0, online: 0 }
        }
        stats[c.odp_id].total += 1
        const isCustOnline = Boolean(c.is_online)
        if (isCustOnline) {
          stats[c.odp_id].online += 1
        }
      }
    })
    return stats
  }, [customerMarkersState])

  // Persist Map Style in LocalStorage
  const [mapStyle, setMapStyle] = useState<MapStyle>(() => {
    if (typeof window !== "undefined") {
      const saved = localStorage.getItem("nodera_map_style")
      if (saved && TILE_LAYERS[saved as MapStyle]) return saved as MapStyle
    }
    return "satellite"
  })

  // Persist Animated Flow Lines in LocalStorage
  const [animateLines, setAnimateLines] = useState<boolean>(() => {
    if (typeof window !== "undefined") {
      const saved = localStorage.getItem("nodera_map_anim_lines")
      if (saved !== null) return saved === "true"
    }
    return true
  })

  const toggleAnimation = () => {
    setAnimateLines((prev) => {
      const next = !prev
      if (typeof window !== "undefined") {
        localStorage.setItem("nodera_map_anim_lines", String(next))
      }
      showToast(next ? "Animasi kabel diaktifkan" : "Animasi kabel dimatikan")
      return next
    })
  }

  const [showStyleMenu, setShowStyleMenu] = useState(false)
  const [filterModalOpen, setFilterModalOpen] = useState(false)
  const [showRouters, setShowRouters] = useState(true)
  const [showOdp, setShowOdp] = useState(true)
  const [showOnu, setShowOnu] = useState(true)
  const [showCustomers, setShowCustomers] = useState(true)
  const [showLines, setShowLines] = useState(true)

  // Persist Chosen Network System (Default: 'all', with 'pon' and 'lan')
  const [networkSystem, setNetworkSystem] = useState<"all" | "pon" | "lan">(() => {
    if (typeof window !== "undefined") {
      const saved = localStorage.getItem("nodera_network_system")
      if (saved === "lan" || saved === "pon" || saved === "all") return saved as "all" | "pon" | "lan"
    }
    return "all"
  })

  const handleSelectNetworkSystem = (sys: "all" | "pon" | "lan") => {
    setNetworkSystem(sys)
    if (typeof window !== "undefined") {
      localStorage.setItem("nodera_network_system", sys)
    }
    showToast(
      sys === "pon"
        ? "Sistem Peta: FTTH (ODC & ODP Optik)"
        : sys === "lan"
        ? "Sistem Peta: LAN (HTB & Switch Hub)"
        : "Sistem Peta: Semua Sistem (FTTH & LAN)"
    )
  }

  const [boxTypeFilter, setBoxTypeFilter] = useState<"all" | "odc" | "odp" | "htb" | "switch">("all")
  const [odpStatusFilter, setOdpStatusFilter] = useState<"all" | "available" | "full" | "critical">("all")
  const [custStatusFilter, setCustStatusFilter] = useState<"all" | "active" | "isolated">("all")
  const [searchQuery, setSearchQuery] = useState("")
  const [odpTableSearch, setOdpTableSearch] = useState("")
  const [categoryFilter, setCategoryFilter] = useState<"all" | "odp" | "odc" | "htb" | "switch" | "full" | "available">("all")
  const [viewMode, setViewMode] = useState<ViewMode>(() => {
    if (typeof window !== "undefined") {
      return (localStorage.getItem("nodera_gis_map_view") as ViewMode) || "grid"
    }
    return "grid"
  })
  const [clickedCoord, setClickedCoord] = useState<{ lat: number; lng: number } | null>(null)
  const [copied, setCopied] = useState(false)
  const [zoomLevel, setZoomLevel] = useState(13)
  const [toastMessage, setToastMessage] = useState<string | null>(null)
  const [isMapLoading, setIsMapLoading] = useState(true)
  const [modalOpen, setModalOpen] = useState(create ?? false)

  useEffect(() => {
    const timer = setTimeout(() => {
      setIsMapLoading(false)
    }, 700)
    return () => clearTimeout(timer)
  }, [])
  const [editingOdp, setEditingOdp] = useState<Odp | null>(null)
  const [gettingLocation, setGettingLocation] = useState(false)
  const [expandedId, setExpandedId] = useState<number | null>(null)
  const [customerModalOpen, setCustomerModalOpen] = useState(false)
  const [selectedCustomer, setSelectedCustomer] = useState<CustomerMarker | null>(null)
  const [selectedRouterModal, setSelectedRouterModal] = useState<RouterMarker | null>(null)
  const [setRouterLocationModalOpen, setSetRouterLocationModalOpen] = useState(false)
  const [selectedRouterToMove, setSelectedRouterToMove] = useState<number | null>(null)
  const [isPinging, setIsPinging] = useState(false)
  const [pingResult, setPingResult] = useState<PingResult | null>(null)

  // Context Menu & Customer Sync Modals
  const [quickActionModalOpen, setQuickActionModalOpen] = useState(false)
  const [customerSyncModalOpen, setCustomerSyncModalOpen] = useState(false)
  const [customerSyncSearch, setCustomerSyncSearch] = useState("")
  const [syncingCustomerId, setSyncingCustomerId] = useState<number | null>(null)

  // ODP Deletion
  const [deleteOdpModalOpen, setDeleteOdpModalOpen] = useState(false)
  const [odpToDelete, setOdpToDelete] = useState<{ id: number; name: string } | null>(null)

  // Cable Route Customization (Interactive Fiber Path Editor)
  const [editingCable, setEditingCable] = useState<ConnectionLine | null>(null)
  const [editingWaypoints, setEditingWaypoints] = useState<[number, number][]>([])
  const [isSavingCable, setIsSavingCable] = useState(false)

  const editingCableRef = useRef<ConnectionLine | null>(null)
  const editingWaypointsRef = useRef<[number, number][]>([])

  useEffect(() => {
    editingCableRef.current = editingCable
  }, [editingCable])

  useEffect(() => {
    editingWaypointsRef.current = editingWaypoints
  }, [editingWaypoints])

  // Customer Configuration in Map Sync Modal
  const [selectedSyncCustomer, setSelectedSyncCustomer] = useState<SimpleCustomer | null>(null)
  const [syncOdpId, setSyncOdpId] = useState<string>("")
  const [syncOdpSearch, setSyncOdpSearch] = useState<string>("")
  const [syncOdpPort, setSyncOdpPort] = useState<number>(1)
  const [syncAddress, setSyncAddress] = useState<string>("")
  const [syncLat, setSyncLat] = useState<string>("")
  const [syncLng, setSyncLng] = useState<string>("")

  // ODP Modal Parent (Sumber Induk) Search State
  const [parentOdpSearch, setParentOdpSearch] = useState<string>("")
  const [isParentDropdownOpen, setIsParentDropdownOpen] = useState<boolean>(false)

  // Map of occupied ports on currently selected ODP in Customer Sync modal
  const occupiedPortsForSyncOdp = useMemo(() => {
    if (!syncOdpId) return new Map<number, string>()
    const map = new Map<number, string>()
    allCustomersState.forEach((c) => {
      if (
        String(c.odp_id) === String(syncOdpId) &&
        c.id !== selectedSyncCustomer?.id &&
        c.odp_port
      ) {
        map.set(Number(c.odp_port), c.name)
      }
    })
    return map
  }, [syncOdpId, allCustomersState, selectedSyncCustomer])

  // ── Auto-Refresh 30s Real-Time State & Handlers ──
  const [isAutoRefresh, setIsAutoRefresh] = useState<boolean>(true)
  const [refreshCountdown, setRefreshCountdown] = useState<number>(30)
  const [isLiveUpdating, setIsLiveUpdating] = useState<boolean>(false)
  const [lastLiveUpdate, setLastLiveUpdate] = useState<string>(() => {
    const d = new Date()
    return `${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}:${String(d.getSeconds()).padStart(2, '0')}`
  })

  const selectedCustomerRef = useRef<CustomerMarker | null>(null)
  const editingOdpRef = useRef<Odp | null>(null)

  useEffect(() => {
    selectedCustomerRef.current = selectedCustomer
  }, [selectedCustomer])

  useEffect(() => {
    editingOdpRef.current = editingOdp
  }, [editingOdp])

  const fetchLiveMapData = async (silent = true, forceFresh = false) => {
    if (isLiveUpdating && !forceFresh) return
    if (!silent) setIsLiveUpdating(true)
    try {
      const url = forceFresh ? "/admin/map/data?refresh=1" : "/admin/map/data"
      const res = await axios.get(url)
      if (res.data && res.data.success) {
        if (res.data.customerMarkers) setCustomerMarkersState(res.data.customerMarkers)
        if (res.data.odpMarkers) setOdpsState(res.data.odpMarkers)
        if (res.data.allCustomers) setAllCustomersState(res.data.allCustomers)
        if (res.data.connections) setConnectionsState(res.data.connections)
        if (res.data.routers) setRoutersState(res.data.routers)

        // Perbarui selectedCustomer realtime jika modal sedang terbuka
        if (selectedCustomerRef.current) {
          const freshCust = (res.data.customerMarkers as CustomerMarker[])?.find((c) => c.id === selectedCustomerRef.current?.id)
          if (freshCust) setSelectedCustomer(freshCust)
        }
        // Perbarui editingOdp realtime jika modal sedang terbuka
        if (editingOdpRef.current) {
          const freshOdp = (res.data.odpMarkers as Odp[])?.find((o) => o.id === editingOdpRef.current?.id)
          if (freshOdp) setEditingOdp(freshOdp)
        }

        const nowStr = res.data.timestamp || (() => {
          const d = new Date()
          return `${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}:${String(d.getSeconds()).padStart(2, '0')}`
        })()
        setLastLiveUpdate(nowStr)
      }
    } catch (e) {
      console.warn("Gagal sinkronisasi data peta live:", e)
    } finally {
      setIsLiveUpdating(false)
    }
  }

  // Interval timer untuk countdown auto-refresh 30 detik
  useEffect(() => {
    if (!isAutoRefresh) return
    const timer = setInterval(() => {
      // Jeda jika user sedang mengedit jalur kabel atau sedang submit
      if (editingCableRef.current) return
      setRefreshCountdown((prev) => {
        if (prev <= 1) {
          fetchLiveMapData(true)
          return 30
        }
        return prev - 1
      })
    }, 1000)
    return () => clearInterval(timer)
  }, [isAutoRefresh])

  const showToast = (msg: string) => {
    setToastMessage(msg)
    setTimeout(() => setToastMessage(null), 3500)
  }

  const startEditingCableRoute = (conn: ConnectionLine, initialClickLatLng?: L.LatLng) => {
    if (!isAdmin) return
    setEditingCable(conn)
    let existing = conn.waypoints && conn.waypoints.length > 0 ? [...conn.waypoints] : []

    // Jika kabel masih lurus dan diklik langsung, langsung munculkan titik handle lingkaran di titik klik!
    if (existing.length === 0 && initialClickLatLng) {
      existing = [
        [
          Number(initialClickLatLng.lat.toFixed(6)),
          Number(initialClickLatLng.lng.toFixed(6)),
        ],
      ]
    }

    setEditingWaypoints(existing)

    if (mapInstanceRef.current && mapInstanceRef.current.getZoom() < 14) {
      const fullPath: [number, number][] = [
        [conn.odp_lat, conn.odp_lng],
        ...existing,
        [conn.target_lat, conn.target_lng],
      ]
      mapInstanceRef.current.fitBounds(L.latLngBounds(fullPath), { padding: [60, 60], maxZoom: 17 })
    }
  }

  const handleSaveCablePath = async () => {
    if (!isAdmin || !editingCable) return
    setIsSavingCable(true)
    try {
      const payload: any = { waypoints: editingWaypoints }
      if (editingCable.category === "drop_wire" && editingCable.customer_id) {
        payload.customer_id = editingCable.customer_id
      } else if (editingCable.target_id) {
        payload.odp_id = editingCable.target_id
      } else if (editingCable.odp_id) {
        payload.odp_id = editingCable.odp_id
      }

      const res = await axios.post("/admin/map/update-cable-path", payload)
      const savedWaypoints: [number, number][] = res.data.cable_path || editingWaypoints

      setConnectionsState((prev) =>
        prev.map((c) =>
          c.id === editingCable.id ? { ...c, waypoints: savedWaypoints } : c
        )
      )

      if (editingCable.customer_id) {
        setAllCustomersState((prev) =>
          prev.map((c) =>
            c.id === editingCable.customer_id ? { ...c, cable_path: savedWaypoints } : c
          )
        )
      } else if (editingCable.target_id) {
        setOdpsState((prev) =>
          prev.map((o) =>
            o.id === editingCable.target_id ? { ...o, cable_path: savedWaypoints } : o
          )
        )
      }

      showToast(`Jalur rute kabel "${editingCable.customer_name}" berhasil disimpan! (${savedWaypoints.length} titik belokan)`)
      setEditingCable(null)
      setEditingWaypoints([])
    } catch (err: any) {
      console.error(err)
      const errMsg = err?.response?.data?.message || "Gagal menyimpan jalur kabel."
      showToast(errMsg)
    } finally {
      setIsSavingCable(false)
    }
  }

  const handleResetStraightCable = () => {
    if (!isAdmin) return
    setEditingWaypoints([])
    showToast("Titik belokan dihapus, jalur kembali lurus.")
  }

  const handleCancelEditCable = () => {
    setEditingCable(null)
    setEditingWaypoints([])
    showToast("Mode edit jalur kabel ditutup.")
  }

  const handleSelectCustomerForSync = (cust: SimpleCustomer | CustomerMarker | any) => {
    if (!isAdmin) return
    const simple: SimpleCustomer = {
      id: cust.id,
      name: cust.name,
      code: cust.code || "",
      pppoe_username: cust.pppoe_username,
      address: cust.address,
      lat: Number(cust.lat) || 0,
      lng: Number(cust.lng) || 0,
      odp_id: cust.odp_id || null,
      odp_port: cust.odp_port || 1,
      is_online: cust.is_online,
      status: cust.status,
      router_id: cust.router_id || null,
      router_name: cust.router_name || null,
    }
    setSelectedSyncCustomer(simple)
    setSyncOdpId(cust.odp_id ? String(cust.odp_id) : "")
    setSyncOdpSearch("")
    setSyncOdpPort(cust.odp_port || 1)
    setSyncAddress(cust.address || "")
    const initialLat = (cust.lat && Number(cust.lat) !== 0) ? String(cust.lat) : (clickedCoord ? String(clickedCoord.lat) : "-6.200000")
    const initialLng = (cust.lng && Number(cust.lng) !== 0) ? String(cust.lng) : (clickedCoord ? String(clickedCoord.lng) : "106.816666")
    setSyncLat(initialLat)
    setSyncLng(initialLng)
    setCustomerSyncModalOpen(true)
  }

  const handleDeleteCustomerMarker = async (custId: number, custName?: string) => {
    if (!isAdmin) return
    const targetName = custName || `Pelanggan #${custId}`
    if (!confirm(`Hapus titik lokasi ${targetName} dari peta? Data pelanggan di sistem billing tetap aman.`)) {
      return
    }
    try {
      await axios.post("/admin/map/update-coords", {
        type: "customer",
        id: custId,
        lat: null,
        lng: null,
        odp_id: null,
      })
      setCustomerMarkersState((prev) => prev.filter((c) => c.id !== custId))
      setConnectionsState((prev) => prev.filter((conn) => conn.customer_id !== custId && !(conn.target_id === custId && conn.target_type === "customer")))
      setAllCustomersState((prev) =>
        prev.map((c) => (c.id === custId ? { ...c, lat: 0, lng: 0, odp_id: null } : c))
      )
      setCustomerModalOpen(false)
      showToast(`Titik lokasi ${targetName} berhasil dihapus dari peta.`)
    } catch (err) {
      console.error("Gagal menghapus titik pelanggan dari peta:", err)
      showToast("Gagal menghapus titik pelanggan.")
    }
  }

  const handleDeleteCustomerFull = (custId: number, custName?: string) => {
    if (!isAdmin) return
    const targetName = custName || `Pelanggan #${custId}`
    if (
      !confirm(
        `PERINGATAN: Yakin ingin MENGHAPUS PERMANEN pelanggan ${targetName}?\n\nSeluruh data tagihan invoice dan akun PPPoE pelanggan ini akan dihapus dari sistem billing.`
      )
    ) {
      return
    }
    router.post(
      `/admin/billing/customers/delete/${custId}`,
      {},
      {
        preserveScroll: true,
        onSuccess: () => {
          setCustomerMarkersState((prev) => prev.filter((c) => c.id !== custId))
          setConnectionsState((prev) => prev.filter((conn) => conn.customer_id !== custId && !(conn.target_id === custId && conn.target_type === "customer")))
          setAllCustomersState((prev) => prev.filter((c) => c.id !== custId))
          setCustomerModalOpen(false)
          showToast(`Pelanggan ${targetName} berhasil dihapus dari sistem.`)
        },
        onError: () => {
          showToast("Gagal menghapus data pelanggan dari sistem.")
        },
      }
    )
  }

  const handleSaveCustomerSyncForm = async (e: React.FormEvent) => {
    e.preventDefault()
    if (!isAdmin || !selectedSyncCustomer) return
    setSyncingCustomerId(selectedSyncCustomer.id)
    try {
      const cleanLatStr = String(syncLat || "").replace(",", ".").trim()
      const cleanLngStr = String(syncLng || "").replace(",", ".").trim()
      const lat = Number(cleanLatStr)
      const lng = Number(cleanLngStr)
      const odpId = syncOdpId ? Number(syncOdpId) : null
      const odpPort = syncOdpPort ? Number(syncOdpPort) : null
      const address = syncAddress

      const res = await axios.post("/admin/map/update-coords", {
        type: "customer",
        id: selectedSyncCustomer.id,
        lat,
        lng,
        odp_id: odpId,
        odp_port: odpPort,
        address,
      })

      const updatedCust: CustomerMarker = res.data.customer

      setCustomerMarkersState((prev) => {
        const exists = prev.some((c) => c.id === selectedSyncCustomer.id)
        if (exists) {
          return prev.map((c) => (c.id === selectedSyncCustomer.id ? updatedCust : c))
        }
        return [...prev, updatedCust]
      })

      const selectedOdpObj = odpsState.find((o) => o.id === odpId)

      setAllCustomersState((prev) =>
        prev.map((c) =>
          c.id === selectedSyncCustomer.id
            ? {
                ...c,
                lat,
                lng,
                odp_id: odpId,
                odp_name: selectedOdpObj?.name || null,
                odp_port: odpPort,
                address,
                router_id: selectedSyncCustomer.router_id ?? c.router_id,
                router_name: selectedSyncCustomer.router_name ?? c.router_name,
              }
            : c
        )
      )

      setConnectionsState((prev) => {
        const remaining = prev.filter((conn) => conn.customer_id !== selectedSyncCustomer.id)
        if (odpId && selectedOdpObj && selectedOdpObj.lat && selectedOdpObj.lng) {
          return [
            ...remaining,
            {
              id: `line-c-${selectedSyncCustomer.id}`,
              category: "drop_wire",
              source_type: selectedOdpObj.type || "odp",
              source_id: selectedOdpObj.id,
              source_name: selectedOdpObj.name,
              target_type: "customer",
              target_id: selectedSyncCustomer.id,
              target_name: selectedSyncCustomer.name,
              odp_id: selectedOdpObj.id,
              odp_name: selectedOdpObj.name,
              customer_id: selectedSyncCustomer.id,
              customer_name: selectedSyncCustomer.name,
              customer_pppoe: selectedSyncCustomer.pppoe_username,
              status: "active",
              is_connected: true,
              odp_lat: selectedOdpObj.lat,
              odp_lng: selectedOdpObj.lng,
              target_lat: lat,
              target_lng: lng,
              odp_port: odpPort,
              waypoints: updatedCust.cable_path || [],
            },
          ]
        }
        return remaining
      })

      showToast(`Data & Lokasi pelanggan "${selectedSyncCustomer.name}" berhasil disinkronkan ke ODP!`)
      setCustomerSyncModalOpen(false)
      setSelectedSyncCustomer(null)
      setQuickActionModalOpen(false)
      panToLocation(lat, lng)
    } catch (err) {
      console.error(err)
      showToast("Gagal menyinkronkan data pelanggan.")
    } finally {
      setSyncingCustomerId(null)
    }
  }

  const safeOdps = Array.isArray(odpsState) ? odpsState : toArray<Odp>(odpsState)
  const safeCustomers = Array.isArray(customerMarkersState) ? customerMarkersState : toArray<CustomerMarker>(customerMarkersState)

  const totalCapacity = safeOdps.reduce((acc, o) => acc + (o.capacity || 0), 0)
  const totalUsed = safeOdps.reduce((acc, o) => acc + (o.used_ports || 0), 0)
  const avgUtilization = totalCapacity > 0 ? Math.round((totalUsed / totalCapacity) * 100) : 0

  const availableOdpCount = safeOdps.filter((o) => (o.capacity || 8) - (o.used_ports || 0) > 0).length
  const fullOdpCount = safeOdps.filter((o) => (o.used_ports || 0) >= (o.capacity || 8)).length
  const criticalOdpCount = safeOdps.filter((o) => {
    const cap = o.capacity || 8
    const used = o.used_ports || 0
    return used < cap && cap > 0 && used / cap >= 0.75
  }).length

  const odcCount = safeOdps.filter((o) => o.type === "odc").length
  const standardOdpCount = safeOdps.filter((o) => o.type === "odp" || !o.type).length
  const htbCount = safeOdps.filter((o) => o.type === "htb").length
  const switchCount = safeOdps.filter((o) => o.type === "switch").length

  const activeCustCount = safeCustomers.filter((c) => Boolean(c.is_online)).length
  const isolatedCustCount = safeCustomers.filter((c) => !c.is_online).length

  const activeFiltersCount = useMemo(() => {
    let count = 0
    if (!showRouters || !showOdp || !showCustomers || !showLines || !showOnu) count++
    if (boxTypeFilter !== "all") count++
    if (odpStatusFilter !== "all") count++
    if (custStatusFilter !== "all") count++
    return count
  }, [showRouters, showOdp, showCustomers, showLines, showOnu, boxTypeFilter, odpStatusFilter, custStatusFilter])

  const form = useForm({
    name: "",
    type: "odp" as "odc" | "odp" | "htb" | "switch",
    network_mode: "pon" as "pon" | "lan",
    lat: "-6.200000",
    lng: "106.816666",
    capacity: 8,
    parent_odp_id: "" as string,
    router_id: "" as string,
  })

  const openAdd = (presetLat?: number, presetLng?: number, defaultType?: "odc" | "odp" | "htb" | "switch") => {
    if (!isAdmin) return
    setEditingOdp(null)
    setParentOdpSearch("")
    setIsParentDropdownOpen(false)
    const center = mapInstanceRef.current ? mapInstanceRef.current.getCenter() : { lat: -6.2, lng: 106.816666 }
    const chosenType = defaultType || (networkSystem === "lan" ? "htb" : "odp")
    const isLanType = networkSystem === "lan" || chosenType === "htb" || chosenType === "switch"
    const chosenMode = isLanType ? "lan" : "pon"
    const isOdc = chosenType === "odc"
    const isHtb = chosenType === "htb"
    const isSwitch = chosenType === "switch"
    const defaultRouterId = routersState[0]?.id ? String(routersState[0].id) : ""
    const autoName = generateDeviceName(chosenType, defaultRouterId, odpsState, routersState)

    form.setData({
      name: autoName,
      type: chosenType,
      network_mode: chosenMode,
      lat: presetLat != null ? String(presetLat) : String(center.lat.toFixed(6)),
      lng: presetLng != null ? String(presetLng) : String(center.lng.toFixed(6)),
      capacity: isOdc ? 24 : isHtb ? 2 : isSwitch ? 8 : 8,
      parent_odp_id: "",
      router_id: defaultRouterId,
    })
    setModalOpen(true)
  }

  const openEdit = (o: Odp) => {
    if (!isAdmin) return
    setEditingOdp(o)
    const parentObj = odpsState.find((item) => item.id === o.parent_odp_id)
    setParentOdpSearch(parentObj ? `${parentObj.name} (${(parentObj.type || "ODP").toUpperCase()})` : "")
    setIsParentDropdownOpen(false)
    form.setData({
      name: o.name,
      type: o.type || "odp",
      network_mode: o.network_mode || (o.type === "htb" || o.type === "switch" ? "lan" : "pon"),
      lat: String(o.lat || -6.200000),
      lng: String(o.lng || 106.816666),
      capacity: o.capacity || 8,
      parent_odp_id: o.parent_odp_id ? String(o.parent_odp_id) : "",
      router_id: o.router_id ? String(o.router_id) : "",
    })
    setModalOpen(true)
  }

  const requestDeviceGPS = (
    onSuccess: (lat: number, lng: number) => void,
    onFail: (msg: string) => void,
    onComplete: () => void
  ) => {
    if (typeof window === "undefined" || !navigator.geolocation) {
      onFail("Browser atau perangkat tidak mendukung sensor GPS.")
      onComplete()
      return
    }

    const isSecure = window.isSecureContext || window.location.hostname === "localhost" || window.location.hostname === "127.0.0.1"
    if (!isSecure && window.location.protocol !== "https:") {
      showToast("Info: Fitur GPS browser membutuhkan koneksi HTTPS.")
    }

    const fallbackLowAccuracy = (primaryErr?: GeolocationPositionError) => {
      navigator.geolocation.getCurrentPosition(
        (pos) => {
          const lat = Number(pos.coords.latitude.toFixed(6))
          const lng = Number(pos.coords.longitude.toFixed(6))
          onSuccess(lat, lng)
          onComplete()
        },
        (err) => {
          let msg = "Gagal mengambil lokasi GPS."
          if (err.code === 1 || primaryErr?.code === 1) {
            msg = "Izin lokasi ditolak. Mohon aktifkan izin akses lokasi di browser/HP."
          } else if (err.code === 2) {
            msg = "Sinyal GPS / posisi perangkat tidak dapat ditemukan."
          } else if (err.code === 3) {
            msg = "Waktu pencarian GPS habis (Timeout). Pastikan GPS HP aktif."
          }
          onFail(msg)
          onComplete()
        },
        { enableHighAccuracy: false, timeout: 12000, maximumAge: 120000 }
      )
    }

    // Strategi 1: Coba satelit GPS (High Accuracy)
    navigator.geolocation.getCurrentPosition(
      (pos) => {
        const lat = Number(pos.coords.latitude.toFixed(6))
        const lng = Number(pos.coords.longitude.toFixed(6))
        onSuccess(lat, lng)
        onComplete()
      },
      (err) => {
        if (err.code === 1) {
          onFail("Izin akses lokasi ditolak oleh browser/HP.")
          onComplete()
        } else {
          // Timeout / position unavailable -> otomatis fallback ke jaringan Wi-Fi / Cell tower
          fallbackLowAccuracy(err)
        }
      },
      { enableHighAccuracy: true, timeout: 6000, maximumAge: 30000 }
    )
  }

  const handleGetCurrentLocation = () => {
    setGettingLocation(true)
    requestDeviceGPS(
      (lat, lng) => {
        form.setData((prev) => ({
          ...prev,
          lat: String(lat),
          lng: String(lng),
        }))
        if (mapInstanceRef.current) {
          mapInstanceRef.current.flyTo([lat, lng], 17)
        }
        showToast(`Lokasi GPS didapat: ${lat}, ${lng}`)
      },
      (errMsg) => {
        showToast(errMsg)
      },
      () => {
        setGettingLocation(false)
      }
    )
  }

  const panToUserLocation = () => {
    showToast("Mencari posisi GPS perangkat...")
    requestDeviceGPS(
      (lat, lng) => {
        if (mapInstanceRef.current) {
          mapInstanceRef.current.flyTo([lat, lng], 17, { animate: true, duration: 1.2 })
          showToast(`Lokasi GPS Anda: ${lat}, ${lng}`)
        }
      },
      (errMsg) => {
        showToast(errMsg)
      },
      () => {}
    )
  }

  const [submittingOdp, setSubmittingOdp] = useState(false)

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    if (!isAdmin) return
    setSubmittingOdp(true)
    try {
      if (editingOdp) {
        const res = await axios.post(`/admin/odp/edit/${editingOdp.id}`, form.data)
        const updatedData = res.data?.odp || {}
        setOdpsState((prev) =>
          prev.map((o) => {
            if (o.id === editingOdp.id) {
              return {
                ...o,
                ...updatedData,
                name: form.data.name,
                type: form.data.type,
                capacity: form.data.capacity,
                parent_odp_id: form.data.parent_odp_id ? Number(form.data.parent_odp_id) : null,
                router_id: form.data.router_id ? Number(form.data.router_id) : null,
                lat: Number(form.data.lat) || o.lat,
                lng: Number(form.data.lng) || o.lng,
              }
            }
            return o
          })
        )
        setModalOpen(false)
        showToast(res.data?.message || `Titik distribusi "${form.data.name}" berhasil diperbarui!`)
        // Update koneksi kabel secara instan tanpa delay
        await fetchLiveMapData(false, true)
      } else {
        const res = await axios.post("/admin/odp/add", form.data)
        const newOdp = res.data?.odp
        if (newOdp) {
          setOdpsState((prev) => [...prev, newOdp])
        }
        setModalOpen(false)
        showToast(res.data?.message || `Titik distribusi baru "${form.data.name}" berhasil ditambahkan!`)
        // Update koneksi kabel secara instan dari database
        await fetchLiveMapData(false, true)
      }
    } catch (err: any) {
      console.error("Gagal simpan ODP:", err)
      const first =
        err?.response?.data?.message ||
        (err?.response?.data?.errors ? Object.values(err.response.data.errors)[0] : null) ||
        "Gagal menyimpan titik ODP. Periksa isian form."
      showToast(String(first))
    } finally {
      setSubmittingOdp(false)
    }
  }

  const triggerDeleteOdp = (odp: { id: number; name: string }) => {
    if (!isAdmin) return
    setOdpToDelete(odp)
    setDeleteOdpModalOpen(true)
  }

  const confirmDeleteOdp = async () => {
    if (!isAdmin || !odpToDelete) return
    const targetOdpId = odpToDelete.id
    const targetOdpName = odpToDelete.name

    try {
      const res = await axios.post(`/admin/odp/delete/${targetOdpId}`)
      setDeleteOdpModalOpen(false)
      showToast(res.data?.message || `Titik distribusi "${targetOdpName}" telah dihapus.`)

      // Hapus ODP dari state
      setOdpsState((prev) => prev.filter((o) => o.id !== targetOdpId))

      // Hapus sambungan kabel yang terkait dengan ODP ini
      setConnectionsState((prev) =>
        prev.filter((c) => c.odp_id !== targetOdpId && c.source_id !== targetOdpId && c.target_id !== targetOdpId)
      )

      // Pelanggan TETAP ADA di peta & database (hanya dilepaskan referensi ODP-nya)
      setCustomerMarkersState((prev) =>
        prev.map((c) => (c.odp_id === targetOdpId ? { ...c, odp_id: null, odp_name: undefined, odp_port: undefined } : c))
      )
      setAllCustomersState((prev) =>
        prev.map((c) => (c.odp_id === targetOdpId ? { ...c, odp_id: null, odp_name: undefined, odp_port: undefined } : c))
      )

      setOdpToDelete(null)
      await fetchLiveMapData(false, true)
    } catch (err: any) {
      console.error("Gagal hapus ODP:", err)
      const msg = err?.response?.data?.message || "Gagal menghapus titik distribusi."
      showToast(String(msg))
    }
  }

  const filteredSyncCustomers = useMemo(() => {
    if (!customerSyncSearch || !customerSyncSearch.trim()) return allCustomersState || []
    const q = (customerSyncSearch || "").toLowerCase()
    return (allCustomersState || []).filter(
      (c) =>
        (c?.name || "").toLowerCase().includes(q) ||
        (c?.pppoe_username || "").toLowerCase().includes(q) ||
        (c?.code || "").toLowerCase().includes(q) ||
        (c?.address || "").toLowerCase().includes(q) ||
        (c?.router_name || "").toLowerCase().includes(q)
    )
  }, [allCustomersState, customerSyncSearch])

  const filteredSyncOdps = useMemo(() => {
    let list = (odpsState || []).filter((o) => {
      if (!o) return false
      if (networkSystem === "pon" && (o.type === "htb" || o.type === "switch")) return false
      if (networkSystem === "lan" && (o.type === "odc" || o.type === "odp" || !o.type)) return false
      return true
    })
    if (!syncOdpSearch || !syncOdpSearch.trim()) return list
    const q = (syncOdpSearch || "").toLowerCase()
    return list.filter(
      (o) =>
        (o?.name || "").toLowerCase().includes(q) ||
        (o?.type || "").toLowerCase().includes(q) ||
        (o?.address && (o.address || "").toLowerCase().includes(q))
    )
  }, [odpsState, syncOdpSearch, networkSystem])

  const filteredOdpTable = useMemo(() => {
    let list = odpsState.length ? odpsState : odps
    if (networkSystem === "pon") {
      list = list.filter((o) => o && (o.type === "odc" || o.type === "odp" || !o.type))
    } else if (networkSystem === "lan") {
      list = list.filter((o) => o && (o.type === "htb" || o.type === "switch"))
    }

    if (categoryFilter === "odp") {
      list = list.filter((o) => (o.type || "odp") === "odp")
    } else if (categoryFilter === "odc") {
      list = list.filter((o) => o.type === "odc")
    } else if (categoryFilter === "htb") {
      list = list.filter((o) => o.type === "htb")
    } else if (categoryFilter === "switch") {
      list = list.filter((o) => o.type === "switch")
    } else if (categoryFilter === "full") {
      list = list.filter((o) => o.capacity > 0 && o.used_ports >= o.capacity)
    } else if (categoryFilter === "available") {
      list = list.filter((o) => o.capacity > 0 && o.used_ports < o.capacity)
    }

    if (!odpTableSearch || !odpTableSearch.trim()) return list
    const q = (odpTableSearch || "").toLowerCase().trim()
    return list.filter((o) => (o?.name || "").toLowerCase().includes(q) || (o?.type || "").toLowerCase().includes(q))
  }, [odpsState, odps, odpTableSearch, networkSystem, categoryFilter])

  const handleDelete = (o: Odp) => {
    if (!isAdmin) return
    triggerDeleteOdp({ id: o.id, name: o.name })
  }

  const runPingTest = async (customer: CustomerMarker) => {
    setIsPinging(true)
    setPingResult(null)
    try {
      const res = await axios.post<PingResult>("/admin/map/ping-customer", { customer_id: customer.id, ip: customer.ip_address })
      setPingResult(res.data)
    } catch {
      setPingResult({
        success: false, ip: customer.ip_address || "127.0.0.1", target: customer.name, transmitted: 5, received: 0, loss_percent: 100, min_ms: 0, avg_ms: 0, max_ms: 0, jitter_ms: 0, samples: [], quality: "Gagal terhubung ke host (RTO)", status: "RTO", timestamp: new Date().toLocaleTimeString(),
      })
    } finally {
      setIsPinging(false)
    }
  }

  const openCustomerDetail = (c: CustomerMarker) => {
    setSelectedCustomer(c)
    setPingResult(null)
    setCustomerModalOpen(true)
  }

  useEffect(() => {
    ;(window as any).__noderaOpenCustomer = (custId: number) => {
      const found = customerMarkersState.find((c) => c.id === custId)
      if (found) openCustomerDetail(found)
    }
    ;(window as any).__noderaEditCustomer = (custId: number) => {
      if (!isAdmin) return
      const found =
        customerMarkersState.find((c) => c.id === custId) ||
        allCustomersState.find((c) => c.id === custId)
      if (found) handleSelectCustomerForSync(found as any)
    }
    ;(window as any).__noderaDeleteCustomerMarker = (custId: number, custName: string) => {
      if (!isAdmin) return
      handleDeleteCustomerMarker(custId, custName)
    }
    ;(window as any).__noderaDeleteCustomerFull = (custId: number, custName: string) => {
      if (!isAdmin) return
      handleDeleteCustomerFull(custId, custName)
    }
    ;(window as any).__noderaEditOdp = (odpId: number) => {
      if (!isAdmin) return
      const found = odpsState.find((o) => o.id === odpId)
      if (found) openEdit(found)
    }
    ;(window as any).__noderaDeleteOdp = (odpId: number, odpName?: string) => {
      if (!isAdmin) return
      const found = odpsState.find((o) => o.id === odpId)
      if (found) {
        handleDelete(found)
      } else {
        triggerDeleteOdp({ id: odpId, name: odpName || `Titik #${odpId}` })
      }
    }
    ;(window as any).__noderaEditCableRoute = (connId: string) => {
      if (!isAdmin) return
      const found = connectionsState.find((c) => c.id === connId)
      if (found) startEditingCableRoute(found)
    }
    ;(window as any).__noderaDeleteWaypoint = (wpIdx: number) => {
      if (!isAdmin) return
      setEditingWaypoints((prev) => prev.filter((_, i) => i !== wpIdx))
    }
    ;(window as any).__noderaAddOdpAtSelected = () => {
      if (!isAdmin) return
      if (clickedCoord) openAdd(clickedCoord.lat, clickedCoord.lng)
    }
    ;(window as any).__noderaAddCustomerAtSelected = () => {
      if (!isAdmin) return
      setCustomerSyncSearch("")
      setSelectedSyncCustomer(null)
      setCustomerSyncModalOpen(true)
    }
    ;(window as any).__noderaMoveRouterAtSelected = () => {
      if (!isAdmin) return
      if (routersState.length > 0) {
        setSelectedRouterToMove(routersState[0].id)
      }
      setSetRouterLocationModalOpen(true)
    }
  }, [customerMarkersState, allCustomersState, odpsState, connectionsState, clickedCoord, routersState, isAdmin])

  useEffect(() => {
    if (!mapContainerRef.current) return
    if (mapInstanceRef.current) {
      try {
        mapInstanceRef.current.stop()
        mapInstanceRef.current.remove()
      } catch (e) {}
      mapInstanceRef.current = null
    }
    if ((mapContainerRef.current as any)._leaflet_id) {
      delete (mapContainerRef.current as any)._leaflet_id
    }

    // Cek apakah ada posisi kamera terakhir yang tersimpan di browser
    let defaultCenter: [number, number] = [-6.200000, 106.816666]
    let initialZoom = 13
    let hasSavedView = false

    if (typeof window !== "undefined") {
      const savedCenterStr = localStorage.getItem("nodera_map_center")
      const savedZoomStr = localStorage.getItem("nodera_map_zoom")
      if (savedCenterStr && savedZoomStr) {
        try {
          const parsedCenter = JSON.parse(savedCenterStr)
          const parsedZoom = Number(savedZoomStr)
          if (Array.isArray(parsedCenter) && parsedCenter.length === 2 && !isNaN(parsedZoom)) {
            defaultCenter = [parsedCenter[0], parsedCenter[1]]
            initialZoom = parsedZoom
            hasSavedView = true
            initialFitDoneRef.current = true
          }
        } catch (e) {}
      }
    }

    if (!hasSavedView) {
      // Pusat default peta adalah Router / Server NOC Utama jika koordinat tersedia
      const activeRouterWithCoord =
        routersState.find((r) => r.lat && r.lng && r.is_active) ||
        routersState.find((r) => r.lat && r.lng)

      if (activeRouterWithCoord && activeRouterWithCoord.lat && activeRouterWithCoord.lng) {
        defaultCenter = [Number(activeRouterWithCoord.lat), Number(activeRouterWithCoord.lng)]
        initialZoom = 15
      } else {
        const firstOdpWithCoord = odpsState.find((o) => o.lat && o.lng && (o.lat !== 0 || o.lng !== 0))
        if (firstOdpWithCoord) {
          defaultCenter = [Number(firstOdpWithCoord.lat), Number(firstOdpWithCoord.lng)]
          initialZoom = 15
        }
      }
    }

    const map = L.map(mapContainerRef.current, {
      center: defaultCenter,
      zoom: initialZoom,
      maxZoom: 20,
      zoomControl: false,
      preferCanvas: true,
    })

    const initialLayerConfig = TILE_LAYERS[mapStyle]
    const tileLayer = L.tileLayer(initialLayerConfig.url, {
      attribution: initialLayerConfig.attribution,
      maxZoom: initialLayerConfig.maxZoom,
      subdomains: initialLayerConfig.subdomains || ["a", "b", "c", "d"],
    }).addTo(map)

    tileLayerRef.current = tileLayer
    const linesGroup = L.layerGroup().addTo(map)
    const layerGroup = L.layerGroup().addTo(map)
    linesGroupRef.current = linesGroup
    layerGroupRef.current = layerGroup
    mapInstanceRef.current = map

    map.on("click", (e: L.LeafletMouseEvent) => {
      // Klik kiri di area kosong: deselect / hapus titik terpilih jika ada, tidak memicu aksi modal apapun
      setClickedCoord(null)
    })

    map.on("contextmenu", (e: L.LeafletMouseEvent) => {
      if (!isAdminRef.current || isLockedRef.current) return
      const activeCable = editingCableRef.current
      if (activeCable) {
        // Jika sedang memilih/mengedit kabel: Klik kanan menambah titik lingkaran belokan
        const newPt: [number, number] = [
          Number(e.latlng.lat.toFixed(6)),
          Number(e.latlng.lng.toFixed(6)),
        ]
        const fullPath: [number, number][] = [
          [activeCable.odp_lat, activeCable.odp_lng],
          ...editingWaypointsRef.current,
          [activeCable.target_lat, activeCable.target_lng],
        ]
        const updated = insertWaypointAtClosestSegment(newPt, fullPath)
        setEditingWaypoints(updated)
        return
      }

      // Normal mode: Klik kanan hanya menempatkan titik koordinat & marker visual pada peta (tanpa pop-up otomatis)
      const lat = Number(e.latlng.lat.toFixed(6))
      const lng = Number(e.latlng.lng.toFixed(6))
      setClickedCoord({ lat, lng })
    })

    map.on("moveend", () => {
      if (typeof window !== "undefined") {
        try {
          const c = map.getCenter()
          localStorage.setItem("nodera_map_center", JSON.stringify([Number(c.lat.toFixed(6)), Number(c.lng.toFixed(6))]))
          localStorage.setItem("nodera_map_zoom", String(map.getZoom()))
        } catch (e) {}
      }
    })

    map.on("zoomend", () => {
      try {
        setZoomLevel(map.getZoom())
      } catch (e) {}
    })

    const resizeTimer = setTimeout(() => {
      if (mapInstanceRef.current && (mapInstanceRef.current as any)._loaded) {
        try {
          mapInstanceRef.current.invalidateSize()
        } catch (e) {}
      }
    }, 250)

    return () => {
      clearTimeout(resizeTimer)
      try {
        map.stop()
        map.remove()
      } catch (e) {}
      mapInstanceRef.current = null
      if (mapContainerRef.current && (mapContainerRef.current as any)._leaflet_id) {
        delete (mapContainerRef.current as any)._leaflet_id
      }
    }
  }, [])

  const switchMapStyle = (style: MapStyle) => {
    setMapStyle(style)
    setShowStyleMenu(false)
    if (typeof window !== "undefined") {
      localStorage.setItem("nodera_map_style", style)
    }
    const map = mapInstanceRef.current
    if (!map) return
    if (tileLayerRef.current) map.removeLayer(tileLayerRef.current)
    const cfg = TILE_LAYERS[style]
    const newLayer = L.tileLayer(cfg.url, {
      attribution: cfg.attribution,
      maxZoom: cfg.maxZoom,
      subdomains: cfg.subdomains || ["a", "b", "c", "d"],
    }).addTo(map)
    tileLayerRef.current = newLayer
    showToast(`Peta beralih ke: ${cfg.name}`)
  }

  useEffect(() => {
    const map = mapInstanceRef.current
    const layerGroup = layerGroupRef.current
    const linesGroup = linesGroupRef.current
    if (!map || !layerGroup || !linesGroup) return

    layerGroup.clearLayers()
    linesGroup.clearLayers()
    const bounds: L.LatLngExpression[] = []
    const linesMap = new Map<string, { hit: L.Polyline; vis: L.Polyline; conn: any }>()

    // ── 1. RENDER TITIK ROUTER / SERVER NOC UTAMA ──
    if (showRouters) {
      routersState.forEach((r) => {
        if (!r.lat || !r.lng || (r.lat === 0 && r.lng === 0)) return
        const routerLatLng: [number, number] = [Number(r.lat), Number(r.lng)]
        bounds.push(routerLatLng)

        const isRouterOnline = r.is_active !== false
        const routerBg = isRouterOnline
          ? "linear-gradient(135deg, #6366f1, #4338ca)"
          : "linear-gradient(135deg, #e11d48, #9f1239)"
        const routerGlow = isRouterOnline ? "rgba(99, 102, 241, 0.7)" : "rgba(225, 29, 72, 0.9)"

        const routerIcon = L.divIcon({
          className: "custom-router-marker",
          html: `
            <div style="position: relative; display: flex; align-items: center; justify-content: center; width: 40px; height: 40px; cursor: ${canEdit ? "grab" : "pointer"};">
              <div style="position: absolute; inset: -4px; border-radius: 50%; background: ${isRouterOnline ? "rgba(99, 102, 241, 0.4)" : "rgba(239, 68, 68, 0.4)"}; animation: ping ${isRouterOnline ? "2.5s" : "1.5s"} cubic-bezier(0, 0, 0.2, 1) infinite; pointer-events: none;"></div>
              ${
                !isRouterOnline
                  ? `<span style="position: absolute; top: -5px; right: -5px; width: 17px; height: 17px; background: #e11d48; border: 2px solid #ffffff; border-radius: 50%; display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 6px rgba(0,0,0,0.6); z-index: 10;" title="Router Gateway Disconnect / Offline">
                       <svg width="8" height="8" viewBox="0 0 24 24" fill="none" stroke="#ffffff" stroke-width="3.5" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                     </span>`
                  : ""
              }
              <div style="
                display: flex;
                align-items: center;
                justify-content: center;
                width: 38px;
                height: 38px;
                background: ${routerBg};
                border: 2px solid ${isRouterOnline ? "#ffffff" : "#fecdd3"};
                border-radius: 50%;
                color: #ffffff;
                box-shadow: 0 4px 14px rgba(0,0,0,0.5), 0 0 14px ${routerGlow};
                transition: transform 0.15s;
              ">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#ffffff" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round">
                  <rect x="2" y="2" width="20" height="8" rx="2" ry="2"/>
                  <rect x="2" y="14" width="20" height="8" rx="2" ry="2"/>
                  <line x1="6" y1="6" x2="6.01" y2="6"/>
                  <line x1="6" y1="18" x2="6.01" y2="18"/>
                </svg>
              </div>
            </div>
          `,
          iconSize: [40, 40],
          iconAnchor: [20, 20],
          popupAnchor: [0, -20],
        })

        const marker = L.marker(routerLatLng, { icon: routerIcon, draggable: canEdit })

        if (canEdit) {
          marker.on("drag", (e: any) => {
            const pos = e.target.getLatLng()
            const lat = Number(pos.lat.toFixed(6))
            const lng = Number(pos.lng.toFixed(6))
            connectionsState.forEach((conn) => {
              const isSource = (conn.source_type === "router" || !conn.source_type) && (conn.source_id === r.id || conn.router_id === r.id)
              const isTarget = conn.target_type === "router" && (conn.target_id === r.id || conn.router_id === r.id)
              if (isSource || isTarget) {
                const p = linesMap.get(conn.id)
                if (p) {
                  const start: [number, number] = isSource ? [lat, lng] : [conn.odp_lat, conn.odp_lng]
                  const end: [number, number] = isTarget ? [lat, lng] : [conn.target_lat, conn.target_lng]
                  const wps = p.conn.waypoints || []
                  const path: [number, number][] = [start, ...wps, end]
                  p.vis.setLatLngs(path)
                  p.hit.setLatLngs(path)
                }
              }
            })
          })

          marker.on("dragend", async (e: any) => {
            const newPos = e.target.getLatLng()
            const newLat = Number(newPos.lat.toFixed(6))
            const newLng = Number(newPos.lng.toFixed(6))

            setRoutersState((prev) =>
              prev.map((item) => (item.id === r.id ? { ...item, lat: newLat, lng: newLng } : item))
            )
            setConnectionsState((prev) =>
              prev.map((conn) => {
                const isSource = (conn.source_type === "router" || !conn.source_type) && (conn.source_id === r.id || conn.router_id === r.id)
                const isTarget = conn.target_type === "router" && (conn.target_id === r.id || conn.router_id === r.id)
                if (isSource) return { ...conn, odp_lat: newLat, odp_lng: newLng, source_lat: newLat, source_lng: newLng }
                if (isTarget) return { ...conn, target_lat: newLat, target_lng: newLng }
                return conn
              })
            )

            try {
              await axios.post("/admin/map/update-coords", {
                type: "router",
                id: r.id,
                lat: newLat,
                lng: newLng,
              })
              showToast(`Posisi Router ${r.name} berhasil diperbarui.`)
            } catch (err) {
              console.error("Gagal menyimpan posisi router:", err)
              showToast("Gagal menyimpan posisi router.")
            }
          })
        }

        marker.bindPopup(`
          <div style="font-family: system-ui, sans-serif; min-width: 245px; padding: 2px;">
            <div style="display: flex; align-items: center; justify-content: space-between; gap: 6px; margin-bottom: 4px; padding-right: 24px;">
              <strong style="color: #ffffff; font-size: 13px;">${r.name}</strong>
              <span style="background: #3b0764; color: #d8b4fe; font-size: 9px; font-weight: bold; padding: 1.5px 6px; border-radius: 4px; border: 1px solid #a855f7; white-space: nowrap;">
                CORE NOC / ROUTER
              </span>
            </div>
            <p style="color: #94a3b8; font-size: 11px; margin: 0 0 6px 0;">Host: <strong style="font-family: monospace; color: #38bdf8;">${r.host}:${r.port}</strong></p>
            <div style="background: #0B0E14; border: 1px solid #212B3B; border-radius: 10px; padding: 7px; font-size: 11px; line-height: 1.6; color: #cbd5e1;">
              <div>Lokasi Server: <strong style="color: #ffffff;">${r.location || "NOC Pusat"}</strong></div>
              <div>Sesi PPPoE Aktif: <strong style="color: #34d399;">${r.active_sessions_count || 0} Online</strong></div>
              <div>Total Pelanggan Terdaftar: <strong style="color: #ffffff;">${r.customers_count || 0} Pelanggan</strong></div>
              <div style="font-family: monospace; font-size: 10px; color: #64748b; margin-top: 3px;">GPS: ${Number(r.lat).toFixed(5)}, ${Number(r.lng).toFixed(5)}</div>
            </div>
            ${isAdmin ? '<p style="font-size: 10px; color: #64748b; margin: 6px 0 0 0; text-align: center;">Tarik marker untuk memindahkan posisi NOC.</p>' : ''}
          </div>
        `)

        layerGroup.addLayer(marker)
      })
    }

    // ── 2. RENDER GARIS SAMBUNGAN FIBER (BACKBONE, FEEDER TRUNK, & DROP WIRE) ──
    if (showLines) {
      connectionsState.forEach((conn) => {
        if (!conn.odp_lat || !conn.odp_lng || !conn.target_lat || !conn.target_lng) return

        // Strict Network System Mode Isolation
        let connType: "lan" | "pon" = "pon"
        const sourceOdp = odpsState.find((o) => o.id === conn.source_id || o.id === conn.odp_id)
        const targetOdp = odpsState.find((o) => o.id === conn.target_id)

        if (
          conn.source_type === "htb" ||
          conn.target_type === "htb" ||
          conn.source_type === "switch" ||
          conn.target_type === "switch" ||
          (sourceOdp && (sourceOdp.type === "htb" || sourceOdp.type === "switch")) ||
          (targetOdp && (targetOdp.type === "htb" || targetOdp.type === "switch"))
        ) {
          connType = "lan"
        }

        if (networkSystem === "pon" && connType === "lan") return
        if (networkSystem === "lan" && connType === "pon") return

        if (custStatusFilter === "active" && !conn.is_connected) return
        if (custStatusFilter === "isolated" && conn.is_connected) return

        const isEditingThis = editingCable?.id === conn.id
        const currentWaypoints = isEditingThis ? editingWaypoints : (conn.waypoints || [])
        const fullPath: [number, number][] = [
          [conn.odp_lat, conn.odp_lng],
          ...currentWaypoints,
          [conn.target_lat, conn.target_lng],
        ]

        const dist = calculatePathLengthMeters(fullPath)
        const isConn = conn.is_connected
        const isBackbone = conn.category === "backbone"
        const isFeeder = conn.category === "feeder"
        const isCut = conn.status === "cut"
        const isStandby = conn.status === "standby"
        const isOffline = !isConn || isCut || conn.status === "isolated" || conn.status === "offline"

        // Line Color Scheme:
        // - Backbone NOC ➔ ODC/HTB: Ungu (#A855F7)
        // - Feeder ODC ➔ ODP / Distribusi Antar-Node: Biru Neon (#00C2FF)
        // - Drop Wire ODP ➔ Pelanggan: Hijau Emerald (#10B981)
        const strokeColor = isEditingThis
          ? "#00C2FF"
          : isCut
          ? "#EF4444"
          : isOffline
          ? "#F43F5E"
          : isBackbone
          ? "#A855F7"
          : isFeeder
          ? "#00C2FF"
          : "#10B981"

        const strokeWeight = isEditingThis ? 6 : isBackbone ? 5.5 : isFeeder ? 4.5 : isConn ? 3.5 : 3

        // Line Animation: HANYA bergerak jika koneksi ONLINE & TIDAK PUTUS / DISCONNECT
        const shouldAnimate = animateLines && !isOffline && !isCut && isConn && !isEditingThis

        // 1. Invisible wide hit-zone (24px wide) for effortless clicking on desktop & touchscreens
        const hitPolyline = L.polyline(fullPath, {
          weight: 24,
          opacity: 0,
          interactive: true,
          bubblingMouseEvents: false,
          className: "cable-hit-zone",
        })

        // 2. Visible styled fiber optic line
        const visiblePolyline = L.polyline(fullPath, {
          color: strokeColor,
          weight: strokeWeight,
          opacity: isEditingThis ? 1 : isCut ? 1 : isConn ? 0.95 : 0.8,
          dashArray: isEditingThis
            ? undefined
            : isCut
            ? "8, 8"
            : isOffline
            ? "8, 8"
            : shouldAnimate
            ? "6, 8"
            : undefined,
          className: isEditingThis
            ? "animated-fiber-glow-blue cursor-pointer"
            : isCut
            ? "fiber-cut-glow fiber-offline-static cursor-pointer"
            : isOffline
            ? "fiber-cut-glow fiber-offline-static cursor-pointer"
            : shouldAnimate
            ? (isBackbone ? "animated-fiber-line cursor-pointer" : isFeeder ? "animated-fiber-line animated-fiber-glow-blue cursor-pointer" : "animated-fiber-line animated-fiber-glow cursor-pointer")
            : "cursor-pointer",
          interactive: true,
          bubblingMouseEvents: false,
        })

        if (isEditingThis) {
          const onLineAddPoint = (e: L.LeafletMouseEvent) => {
            L.DomEvent.stopPropagation(e)
            const newPt: [number, number] = [
              Number(e.latlng.lat.toFixed(6)),
              Number(e.latlng.lng.toFixed(6)),
            ]
            const updated = insertWaypointAtClosestSegment(newPt, fullPath)
            setEditingWaypoints(updated)
          }

          hitPolyline.on("click", onLineAddPoint)
          visiblePolyline.on("click", onLineAddPoint)
          hitPolyline.on("contextmenu", onLineAddPoint)
          visiblePolyline.on("contextmenu", onLineAddPoint)

          currentWaypoints.forEach((wp, idx) => {
            const wpIcon = L.divIcon({
              className: "custom-cable-waypoint",
              html: `
                <div style="
                  display: flex;
                  align-items: center;
                  justify-content: center;
                  width: 24px;
                  height: 24px;
                  background: #00C2FF;
                  border: 2px solid #ffffff;
                  border-radius: 50%;
                  color: #090B0E;
                  font-size: 11px;
                  font-weight: 900;
                  font-family: monospace;
                  box-shadow: 0 0 12px rgba(0,194,255,0.9), 0 2px 8px rgba(0,0,0,0.6);
                  cursor: ${canEdit ? "grab" : "pointer"};
                  transition: transform 0.15s;
                ">
                  ${idx + 1}
                </div>
              `,
              iconSize: [24, 24],
              iconAnchor: [12, 12],
            })

            const wpMarker = L.marker([wp[0], wp[1]], { icon: wpIcon, draggable: canEdit })

            if (canEdit) {
              wpMarker.on("drag", (e: any) => {
                const newPos = e.target.getLatLng()
                const currentWps = [...editingWaypointsRef.current]
                currentWps[idx] = [
                  Number(newPos.lat.toFixed(6)),
                  Number(newPos.lng.toFixed(6)),
                ]
                editingWaypointsRef.current = currentWps
                const updatedFullPath: [number, number][] = [
                  [conn.odp_lat, conn.odp_lng],
                  ...currentWps,
                  [conn.target_lat, conn.target_lng],
                ]
                visiblePolyline.setLatLngs(updatedFullPath)
                hitPolyline.setLatLngs(updatedFullPath)
              })

              wpMarker.on("dragend", (e: any) => {
                const newPos = e.target.getLatLng()
                const currentWps = [...editingWaypointsRef.current]
                currentWps[idx] = [
                  Number(newPos.lat.toFixed(6)),
                  Number(newPos.lng.toFixed(6)),
                ]
                editingWaypointsRef.current = currentWps
                setEditingWaypoints(currentWps)
              })

              wpMarker.on("contextmenu", (e: any) => {
                L.DomEvent.stopPropagation(e)
                setEditingWaypoints((prev) => prev.filter((_, i) => i !== idx))
              })
            }

            wpMarker.bindPopup(`
              <div style="font-family: system-ui, sans-serif; min-width: 175px; padding: 2px; padding-right: 20px;">
                <strong style="color: #00C2FF; font-size: 12px; display: block;">Titik Belokan / Tiang #${idx + 1}</strong>
                <p style="color: #94a3b8; font-size: 10.5px; margin: 2px 0 6px 0;">${isAdmin ? "Geser untuk membelokkan kabel." : "Titik belokan jalur fiber."}</p>
                <div style="font-family: monospace; font-size: 9.5px; color: #cbd5e1; background: #0B0E14; padding: 4px 6px; border-radius: 6px; border: 1px solid #212B3B;">
                  ${wp[0].toFixed(6)}, ${wp[1].toFixed(6)}
                </div>
                ${isAdmin ? `
                  <button onclick="window.__noderaDeleteWaypoint(${idx})" style="width: 100%; margin-top: 6px; background: #e11d48; color: white; border: none; padding: 5px 8px; border-radius: 6px; font-size: 11px; font-weight: bold; cursor: pointer;">
                    Hapus Titik #${idx + 1}
                  </button>
                ` : ''}
              </div>
            `)

            linesGroup.addLayer(wpMarker)
          })
        } else {
          const handleDirectActivate = (e: L.LeafletMouseEvent) => {
            L.DomEvent.stopPropagation(e)
            if (isAdmin) {
              startEditingCableRoute(conn, e.latlng)
            }
          }

          if (isAdmin) {
            hitPolyline.on("click", handleDirectActivate)
            visiblePolyline.on("click", handleDirectActivate)
            hitPolyline.on("contextmenu", handleDirectActivate)
            visiblePolyline.on("contextmenu", handleDirectActivate)
          }

          // Hover highlight
          hitPolyline.on("mouseover", () => {
            visiblePolyline.setStyle({ weight: strokeWeight + 1.5, opacity: 1 })
          })
          hitPolyline.on("mouseout", () => {
            visiblePolyline.setStyle({ weight: strokeWeight, opacity: isConn ? 0.95 : 0.85 })
          })

          visiblePolyline.on("mouseover", () => {
            visiblePolyline.setStyle({ weight: strokeWeight + 1.5, opacity: 1 })
          })
          visiblePolyline.on("mouseout", () => {
            visiblePolyline.setStyle({ weight: strokeWeight, opacity: isConn ? 0.95 : 0.85 })
          })
        }

        linesMap.set(conn.id, { hit: hitPolyline, vis: visiblePolyline, conn })
        linesGroup.addLayer(visiblePolyline)
        linesGroup.addLayer(hitPolyline)
      })
    }

    // ── 3. RENDER TITIK DISTRIBUSI (ODC, ODP, HTB, SWITCH) ──
    if (showOdp) {
      (odpsState || []).forEach((odp) => {
        if (!odp || !odp.lat || !odp.lng || (odp.lat === 0 && odp.lng === 0)) return
        if (searchQuery && !(odp.name || "").toLowerCase().includes((searchQuery || "").toLowerCase())) return

        const cap = odp.capacity || 8
        const used = odp.used_ports || 0
        const isFull = used >= cap
        const isCritical = used < cap && cap > 0 && used / cap >= 0.75
        const isOdc = odp.type === "odc"
        const isHtb = odp.type === "htb"
        const isSwitch = odp.type === "switch"
        const isStandardOdp = odp.type === "odp" || !odp.type

        // Network System Mode Isolation
        if (networkSystem === "pon" && (isHtb || isSwitch)) return
        if (networkSystem === "lan" && (isOdc || isStandardOdp)) return

        if (boxTypeFilter === "odc" && !isOdc) return
        if (boxTypeFilter === "odp" && !isStandardOdp) return
        if (boxTypeFilter === "htb" && !isHtb) return
        if (boxTypeFilter === "switch" && !isSwitch) return

        if (odpStatusFilter === "available" && used >= cap) return
        if (odpStatusFilter === "full" && !isFull) return
        if (odpStatusFilter === "critical" && !isCritical) return

        const latlng: [number, number] = [Number(odp.lat), Number(odp.lng)]
        bounds.push(latlng)

        const dynStats = odpClientStats[odp.id]
        const totalClients = dynStats ? dynStats.total : (odp.total_clients || odp.used_ports || odp.customers_count || 0)
        const onlineClients = dynStats ? dynStats.online : (odp.online_clients ?? 0)
        const isCut = totalClients > 0 && onlineClients === 0
        const isPartial = totalClients > 0 && onlineClients < totalClients && onlineClients > 0

        const bgGradient = isCut
          ? "linear-gradient(135deg, #b91c1c, #7f1d1d)"
          : isFull
          ? "linear-gradient(135deg, #f43f5e, #e11d48)"
          : isCritical
          ? "linear-gradient(135deg, #ea580c, #c2410c)"
          : isOdc
          ? "linear-gradient(135deg, #a855f7, #7e22ce)"
          : isHtb
          ? "linear-gradient(135deg, #00C2FF, #0073C6)"
          : isSwitch
          ? "linear-gradient(135deg, #10b981, #059669)"
          : "linear-gradient(135deg, #f59e0b, #d97706)"

        const glowColor = isCut
          ? "rgba(239, 68, 68, 0.95)"
          : isFull
          ? "rgba(244,63,94,0.6)"
          : isCritical
          ? "rgba(234,88,12,0.6)"
          : isOdc
          ? "rgba(168,85,247,0.6)"
          : isHtb
          ? "rgba(0,194,255,0.6)"
          : isSwitch
          ? "rgba(16,185,129,0.6)"
          : "rgba(245,158,11,0.6)"

        const odpIcon = L.divIcon({
          className: "custom-odp-marker",
          html: `
            <div style="
              position: relative;
              display: flex;
              align-items: center;
              justify-content: center;
              width: 34px;
              height: 34px;
              background: ${bgGradient};
              border: 2px solid ${isCut ? "#fecaca" : "#ffffff"};
              border-radius: 50%;
              color: #ffffff;
              box-shadow: 0 4px 12px rgba(0,0,0,0.45), 0 0 ${isCut ? "16px" : "10px"} ${glowColor};
              cursor: ${canEdit ? "grab" : "pointer"};
              transition: transform 0.15s;
            ">
              ${
                isCut
                  ? `<span style="position: absolute; inset: -5px; border-radius: 50%; border: 2px solid #ef4444; background: rgba(239, 68, 68, 0.35); animation: ping 1.5s cubic-bezier(0, 0, 0.2, 1) infinite; pointer-events: none;"></span>
                     <span style="position: absolute; top: -6px; right: -6px; width: 17px; height: 17px; background: #dc2626; border: 2px solid #ffffff; border-radius: 50%; display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 6px rgba(0,0,0,0.6); z-index: 10;" title="Jalur Terputus / Semua Client Offline">
                       <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="#ffffff" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                     </span>`
                  : isPartial
                  ? `<span style="position: absolute; top: -5px; right: -5px; width: 15px; height: 15px; background: #ea580c; border: 2px solid #ffffff; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 8px; font-weight: 900; color: #fff; box-shadow: 0 2px 6px rgba(0,0,0,0.5); z-index: 10;" title="Sebagian Client Offline">
                       !
                     </span>`
                  : ""
              }
              ${
                isOdc
                  ? `<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#ffffff" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="2" width="16" height="20" rx="3" fill="rgba(255,255,255,0.25)"/><line x1="4" y1="8" x2="20" y2="8"/><line x1="4" y1="14" x2="20" y2="14"/><circle cx="16" cy="5" r="1" fill="#ffffff"/><circle cx="16" cy="11" r="1" fill="#ffffff"/><circle cx="16" cy="17" r="1" fill="#ffffff"/></svg>`
                  : isHtb
                  ? `<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#ffffff" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="3" fill="rgba(255,255,255,0.25)"/><circle cx="7" cy="12" r="1.8" fill="#ffffff"/><path d="M10 12h6"/><path d="M13 9l3 3-3 3"/></svg>`
                  : isSwitch
                  ? `<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#ffffff" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="6" width="20" height="12" rx="3" fill="rgba(255,255,255,0.25)"/><circle cx="6" cy="12" r="1.5" fill="#ffffff"/><circle cx="10" cy="12" r="1.5" fill="#ffffff"/><circle cx="14" cy="12" r="1.5" fill="#ffffff"/><circle cx="18" cy="12" r="1.5" fill="#ffffff"/></svg>`
                  : `<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#ffffff" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round"><rect x="15" y="15" width="7" height="7" rx="1.5" fill="rgba(255,255,255,0.25)"/><rect x="2" y="15" width="7" height="7" rx="1.5" fill="rgba(255,255,255,0.25)"/><rect x="8.5" y="2" width="7" height="7" rx="1.5" fill="rgba(255,255,255,0.25)"/><path d="M5.5 15v-3.5a1.5 1.5 0 0 1 1.5-1.5h10a1.5 1.5 0 0 1 1.5 1.5V15"/><line x1="12" y1="9" x2="12" y2="15"/></svg>`
              }
            </div>
          `,
          iconSize: [34, 34],
          iconAnchor: [17, 17],
          popupAnchor: [0, -17],
        })

        const marker = L.marker(latlng, { icon: odpIcon, draggable: canEdit })

        if (canEdit) {
          marker.on("drag", (e: any) => {
            const pos = e.target.getLatLng()
            const lat = Number(pos.lat.toFixed(6))
            const lng = Number(pos.lng.toFixed(6))
            connectionsState.forEach((conn) => {
              const isSource = (conn.odp_id === odp.id || conn.source_id === odp.id) && conn.source_type !== "router"
              const isTarget = conn.target_id === odp.id && conn.target_type !== "customer"
              if (isSource || isTarget) {
                const p = linesMap.get(conn.id)
                if (p) {
                  const start: [number, number] = isSource ? [lat, lng] : [conn.odp_lat, conn.odp_lng]
                  const end: [number, number] = isTarget ? [lat, lng] : [conn.target_lat, conn.target_lng]
                  const wps = p.conn.waypoints || []
                  const path: [number, number][] = [start, ...wps, end]
                  p.vis.setLatLngs(path)
                  p.hit.setLatLngs(path)
                }
              }
            })
          })

          marker.on("dragend", async (e: any) => {
            const newPos = e.target.getLatLng()
            const newLat = Number(newPos.lat.toFixed(6))
            const newLng = Number(newPos.lng.toFixed(6))

            setOdpsState((prev) =>
              prev.map((o) => (o.id === odp.id ? { ...o, lat: newLat, lng: newLng } : o))
            )
            setConnectionsState((prev) =>
              prev.map((conn) => {
                const isSource = (conn.odp_id === odp.id || conn.source_id === odp.id) && conn.source_type !== "router"
                const isTarget = conn.target_id === odp.id && conn.target_type !== "customer"
                if (isSource && isTarget) {
                  return { ...conn, odp_lat: newLat, odp_lng: newLng, source_lat: newLat, source_lng: newLng, target_lat: newLat, target_lng: newLng }
                }
                if (isSource) {
                  return { ...conn, odp_lat: newLat, odp_lng: newLng, source_lat: newLat, source_lng: newLng }
                }
                if (isTarget) {
                  return { ...conn, target_lat: newLat, target_lng: newLng }
                }
                return conn
              })
            )

            try {
              await axios.post("/admin/map/update-coords", {
                type: "odp",
                id: odp.id,
                lat: newLat,
                lng: newLng,
              })
              showToast(`Posisi ${odp.name} berhasil diperbarui.`)
            } catch (err) {
              console.error("Gagal menyimpan posisi ODP:", err)
              showToast("Gagal menyimpan posisi ODP.")
            }
          })
        }

        const typeLabel = isOdc
          ? "ODC (Optical Distribution Cabinet - Feeder)"
          : isHtb
          ? "HTB Media Converter Fiber-to-LAN"
          : isSwitch
          ? "Switch Hub Distribusi"
          : "ODP Box Splitter Optik"

        const uplinkLabel = odp.parent_name
          ? `Induk: ${odp.parent_name}`
          : odp.router_name
          ? `Router NOC: ${odp.router_name}`
          : "Router Gateway NOC"

        marker.bindPopup(`
          <div style="font-family: system-ui, sans-serif; min-width: 235px; padding: 2px;">
            <div style="display: flex; align-items: center; justify-content: space-between; gap: 6px; margin-bottom: 4px; padding-right: 24px;">
              <strong style="color: #ffffff; font-size: 13px; max-width: 130px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">${odp.name}</strong>
              <span style="background: ${isCut ? "#450a0a" : isFull ? "#4c0519" : isOdc ? "#3b0764" : "#064e3b"}; color: ${isCut ? "#fca5a5" : isFull ? "#fda4af" : isOdc ? "#d8b4fe" : "#34d399"}; font-size: 9px; font-weight: bold; padding: 1.5px 6px; border-radius: 4px; border: 1px solid ${isCut ? "#ef4444" : isFull ? "#e11d48" : isOdc ? "#a855f7" : "#059669"}; white-space: nowrap;">
                ${isCut ? "TERPUTUS" : isFull ? "FULL" : `${cap - used} PORT SISA`}
              </span>
            </div>
            <p style="color: #94a3b8; font-size: 10.5px; margin: 0 0 6px 0;">${typeLabel}</p>
            <div style="background: #0B0E14; border: 1px solid #212B3B; border-radius: 10px; padding: 7px; font-size: 11px; line-height: 1.6; color: #cbd5e1;">
              <div>Uplink / Induk: <strong style="color: #38bdf8;">${uplinkLabel}</strong></div>
              <div>Kapasitas Port: <strong style="color: #ffffff;">${used} / ${cap} Port (${Math.round((used / cap) * 100)}%)</strong></div>
              <div>Pelanggan Terhubung: <strong style="color: #ffffff;">${totalClients} Pelanggan</strong></div>
              <div>Klien Online: <strong style="color: ${isCut ? "#ef4444" : "#34d399"};">${onlineClients} dari ${totalClients} Online</strong></div>
              <div style="font-family: monospace; font-size: 10px; color: #64748b; margin-top: 3px;">GPS: ${Number(odp.lat).toFixed(5)}, ${Number(odp.lng).toFixed(5)}</div>
            </div>
            <div style="margin-top: 8px; display: flex; align-items: center; justify-content: flex-end; gap: 6px;">
              ${isAdmin ? `
                <button onclick="window.__noderaEditOdp(${odp.id})" title="Edit Titik" style="display: flex; align-items: center; justify-content: center; width: 30px; height: 30px; background: #0073C6; color: white; border: none; border-radius: 7px; cursor: pointer; box-shadow: 0 2px 8px rgba(0,115,198,0.4);">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"/></svg>
                </button>
                <button onclick="window.__noderaDeleteOdp(${odp.id}, '${odp.name.replace(/'/g, "\\'")}')" title="Hapus Titik" style="display: flex; align-items: center; justify-content: center; width: 30px; height: 30px; background: #e11d48; color: white; border: none; border-radius: 7px; cursor: pointer; box-shadow: 0 2px 8px rgba(225,29,72,0.4);">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
                </button>
              ` : `
                <span style="font-size: 10px; color: #64748b; font-style: italic; padding: 4px;">Tinjau Mode Teknisi/Kolektor</span>
              `}
            </div>
          </div>
        `)
        layerGroup.addLayer(marker)
      })
    }

    if (showCustomers) {
      (customerMarkersState || []).forEach((c) => {
        if (!c || !c.lat || !c.lng || (c.lat === 0 && c.lng === 0)) return
        if (
          searchQuery &&
          !(c.name || "").toLowerCase().includes((searchQuery || "").toLowerCase()) &&
          !(c.pppoe_username || "").toLowerCase().includes((searchQuery || "").toLowerCase()) &&
          !(c.ip_address || "").toLowerCase().includes((searchQuery || "").toLowerCase()) &&
          !(c.mac_address || "").toLowerCase().includes((searchQuery || "").toLowerCase()) &&
          !(c.code || "").toLowerCase().includes((searchQuery || "").toLowerCase())
        )
          return

        const isAct = Boolean(c.is_online)

        if (custStatusFilter === "active" && !isAct) return
        if (custStatusFilter === "isolated" && isAct) return

        // Network System Mode Isolation for Customers
        if (c.odp_id) {
          const parentOdp = odpsState.find((o) => o.id === c.odp_id)
          const isParentLan = parentOdp ? (parentOdp.type === "htb" || parentOdp.type === "switch") : false
          if (networkSystem === "pon" && isParentLan) return
          if (networkSystem === "lan" && !isParentLan) return
        }

        const latlng: [number, number] = [Number(c.lat), Number(c.lng)]
        bounds.push(latlng)

        const custBgGradient = isAct
          ? "linear-gradient(135deg, #10b981, #059669)"
          : "linear-gradient(135deg, #e11d48, #9f1239)"

        const custGlowColor = isAct ? "rgba(16, 185, 129, 0.6)" : "rgba(225, 29, 72, 0.9)"
        const statusText = isAct ? "Online" : "Offline (Disconnect)"

        const custIcon = L.divIcon({
          className: "custom-customer-marker",
          html: `
            <div style="
              position: relative;
              display: flex;
              align-items: center;
              justify-content: center;
              width: 34px;
              height: 34px;
              background: ${custBgGradient};
              border: 2px solid ${isAct ? "#ffffff" : "#fecdd3"};
              border-radius: 50%;
              color: #ffffff;
              box-shadow: 0 4px 12px rgba(0,0,0,0.45), 0 0 ${isAct ? "10px" : "14px"} ${custGlowColor};
              cursor: ${canEdit ? "grab" : "pointer"};
              transition: transform 0.15s;
            ">
              ${
                isAct
                  ? `<span style="position: absolute; inset: -4px; border-radius: 50%; background: rgba(16, 185, 129, 0.4); animation: ping 2s cubic-bezier(0, 0, 0.2, 1) infinite; pointer-events: none;"></span>`
                  : `<span style="position: absolute; inset: -4px; border-radius: 50%; border: 2px solid #ef4444; background: rgba(239, 68, 68, 0.35); animation: ping 1.8s cubic-bezier(0, 0, 0.2, 1) infinite; pointer-events: none;"></span>
                     <span style="position: absolute; top: -6px; right: -6px; width: 17px; height: 17px; background: #dc2626; border: 2px solid #ffffff; border-radius: 50%; display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 6px rgba(0,0,0,0.6); z-index: 10;" title="Pelanggan Disconnect / Terputus">
                       <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="#ffffff" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                     </span>`
              }
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#ffffff" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round">
                <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" fill="rgba(255,255,255,0.25)"/>
                <polyline points="9 22 9 12 15 12 15 22"/>
              </svg>
            </div>
          `,
          iconSize: [34, 34],
          iconAnchor: [17, 17],
          popupAnchor: [0, -17],
        })

        const marker = L.marker(latlng, { icon: custIcon, draggable: canEdit })

        if (canEdit) {
          marker.on("drag", (e: any) => {
            const pos = e.target.getLatLng()
            const lat = Number(pos.lat.toFixed(6))
            const lng = Number(pos.lng.toFixed(6))
            connectionsState.forEach((conn) => {
              if (conn.customer_id === c.id || (conn.target_id === c.id && conn.target_type === "customer")) {
                const p = linesMap.get(conn.id)
                if (p) {
                  const start: [number, number] = [conn.odp_lat, conn.odp_lng]
                  const end: [number, number] = [lat, lng]
                  const wps = p.conn.waypoints || []
                  const path: [number, number][] = [start, ...wps, end]
                  p.vis.setLatLngs(path)
                  p.hit.setLatLngs(path)
                }
              }
            })
          })

          marker.on("dragend", async (e: any) => {
            const newPos = e.target.getLatLng()
            const newLat = Number(newPos.lat.toFixed(6))
            const newLng = Number(newPos.lng.toFixed(6))

            // Simpan posisi lama untuk rollback jika server gagal
            const prevLat = c.lat
            const prevLng = c.lng

            // Update state React terlebih dahulu (optimistic update)
            setCustomerMarkersState((prev) =>
              prev.map((item) => (item.id === c.id ? { ...item, lat: newLat, lng: newLng } : item))
            )
            setConnectionsState((prev) =>
              prev.map((conn) =>
                (conn.customer_id === c.id || (conn.target_id === c.id && conn.target_type === "customer"))
                  ? { ...conn, target_lat: newLat, target_lng: newLng }
                  : conn
              )
            )
            setAllCustomersState((prev) =>
              prev.map((item) => (item.id === c.id ? { ...item, lat: newLat, lng: newLng } : item))
            )

            try {
              const res = await axios.post("/admin/map/update-coords", {
                type: "customer",
                id: c.id,
                lat: newLat,
                lng: newLng,
              })
              if (res.data?.success) {
                showToast(`Posisi pelanggan ${c.name} berhasil diperbarui.`)
              } else {
                // Server menolak — rollback ke posisi lama
                marker.setLatLng([prevLat, prevLng])
                setCustomerMarkersState((prev) =>
                  prev.map((item) => (item.id === c.id ? { ...item, lat: prevLat, lng: prevLng } : item))
                )
                setConnectionsState((prev) =>
                  prev.map((conn) =>
                    (conn.customer_id === c.id || (conn.target_id === c.id && conn.target_type === "customer"))
                      ? { ...conn, target_lat: prevLat, target_lng: prevLng }
                      : conn
                  )
                )
                setAllCustomersState((prev) =>
                  prev.map((item) => (item.id === c.id ? { ...item, lat: prevLat, lng: prevLng } : item))
                )
                showToast(res.data?.message || "Gagal menyimpan posisi pelanggan. Posisi dikembalikan.")
              }
            } catch (err: any) {
              console.error("Gagal menyimpan posisi pelanggan:", err)
              // Rollback ke posisi lama jika request gagal (CSRF expired, network error, dll)
              marker.setLatLng([prevLat, prevLng])
              setCustomerMarkersState((prev) =>
                prev.map((item) => (item.id === c.id ? { ...item, lat: prevLat, lng: prevLng } : item))
              )
              setConnectionsState((prev) =>
                prev.map((conn) =>
                  (conn.customer_id === c.id || (conn.target_id === c.id && conn.target_type === "customer"))
                    ? { ...conn, target_lat: prevLat, target_lng: prevLng }
                    : conn
                )
              )
              setAllCustomersState((prev) =>
                prev.map((item) => (item.id === c.id ? { ...item, lat: prevLat, lng: prevLng } : item))
              )
              const status = err.response?.status
              if (status === 419) {
                showToast("Sesi habis (CSRF). Silakan refresh halaman lalu coba geser lagi.")
              } else if (status === 403) {
                showToast("Tidak punya izin untuk memperbarui posisi pelanggan.")
              } else {
                const errMsg = err.response?.data?.message || "Gagal menyimpan posisi. Posisi dikembalikan ke semula."
                showToast(errMsg)
              }
            }
          })
        }

        marker.bindPopup(`
          <div style="font-family: system-ui, sans-serif; min-width: 235px; padding: 2px;">
            <div style="display: flex; align-items: center; justify-content: space-between; gap: 6px; margin-bottom: 4px; padding-right: 24px;">
              <strong style="color: #ffffff; font-size: 13px; max-width: 120px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">${c.name}</strong>
              <span style="background: ${isAct ? "#064e3b" : "#4c0519"}; color: ${isAct ? "#34d399" : "#fda4af"}; border: 1px solid ${isAct ? "#059669" : "#e11d48"}; font-size: 9px; font-weight: bold; padding: 1.5px 6px; border-radius: 4px; white-space: nowrap;">
                ${statusText.toUpperCase()} ${c.uptime && c.uptime !== "-" ? `· ${c.uptime}` : ""}
              </span>
            </div>
            <p style="color: #94a3b8; font-size: 11px; margin: 0 0 6px 0;">PPPoE: <strong style="color: #38bdf8;">${c.pppoe_username || "-"}</strong> · ID #${c.code || c.id}</p>
            <div style="background: #0B0E14; border: 1px solid #212B3B; border-radius: 10px; padding: 7px; font-size: 11px; line-height: 1.6; color: #cbd5e1;">
              <div>IP Aktif: <strong style="font-family: monospace; color: #34d399;">${c.ip_address && c.ip_address !== '-' ? c.ip_address : '<span style="color:#64748b;font-style:italic;">Tidak tersedia</span>'}</strong></div>
              <div>MAC / Caller: <strong style="font-family: monospace; color: #94a3b8;">${c.mac_address && c.mac_address !== '-' ? c.mac_address : '<span style="color:#64748b;font-style:italic;">Tidak tersedia</span>'}</strong></div>
              <div>Trafik Data: <strong style="color: #34d399;">Rx ${c.rx_bytes || "0 B"}</strong> · <strong style="color: #38bdf8;">Tx ${c.tx_bytes || "0 B"}</strong></div>
              <div>Distribusi: <strong style="color: #ffffff;">${c.odp_name ? `${c.odp_name} ${c.odp_port ? `(Port ${c.odp_port})` : ""}` : "Belum terpasang"}</strong></div>
              ${c.rx_power ? `<div>Redaman RX: <strong style="color: #38bdf8;">${c.rx_power} dBm</strong></div>` : ""}
              <div style="margin-top: 4px; padding-top: 4px; border-top: 1px solid #1e293b;">
                ${ (c as any).mikrotik_reachable
                  ? `<span style="display:inline-flex;align-items:center;gap:3px;background:#052e16;color:#4ade80;border:1px solid #166534;font-size:9px;font-weight:700;padding:1px 5px;border-radius:3px;">
                      <svg width="7" height="7" viewBox="0 0 24 24" fill="#4ade80"><circle cx="12" cy="12" r="10"/></svg>
                      LIVE MIKROTIK
                    </span>`
                  : `<span style="display:inline-flex;align-items:center;gap:3px;background:#1c1917;color:#a8a29e;border:1px solid #44403c;font-size:9px;font-weight:700;padding:1px 5px;border-radius:3px;">
                      <svg width="7" height="7" viewBox="0 0 24 24" fill="#a8a29e"><circle cx="12" cy="12" r="10"/></svg>
                      DATA DATABASE
                    </span>`
                }
              </div>
            </div>
            <div style="margin-top: 8px; display: flex; align-items: center; justify-content: flex-end; gap: 6px;">
              <button onclick="window.__noderaOpenCustomer(${c.id})" title="Buka Detail & Telemetri" style="flex: 1; display: flex; align-items: center; justify-content: center; gap: 5px; background: #0073C6; color: white; border: none; padding: 6.5px 10px; border-radius: 7px; font-size: 11px; font-weight: bold; cursor: pointer; box-shadow: 0 2px 8px rgba(0,115,198,0.4);">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                <span>Detail &amp; Uji Ping</span>
              </button>
              ${isAdmin ? `
                <button onclick="window.__noderaEditCustomer(${c.id})" title="Edit Lokasi & ODP" style="display: flex; align-items: center; justify-content: center; width: 30px; height: 30px; background: #161B22; border: 1px solid #212B3B; color: #cbd5e1; border-radius: 7px; cursor: pointer;">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"/></svg>
                </button>
                <button onclick="window.__noderaDeleteCustomerMarker(${c.id}, '${c.name.replace(/'/g, "\\'")}')" title="Hapus Titik dari Peta" style="display: flex; align-items: center; justify-content: center; width: 30px; height: 30px; background: #e11d48; color: white; border: none; border-radius: 7px; cursor: pointer; box-shadow: 0 2px 8px rgba(225,29,72,0.4);">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
                </button>
              ` : ''}
            </div>
          </div>
        `)
        layerGroup.addLayer(marker)
      })
    }

    if (showOnu) {
      (onusMarkers || []).forEach((onu) => {
        if (!onu || !onu.lat || !onu.lng || (onu.lat === 0 && onu.lng === 0)) return
        if (
          searchQuery &&
          !(onu.name || "").toLowerCase().includes((searchQuery || "").toLowerCase()) &&
          !(onu.customer || "").toLowerCase().includes((searchQuery || "").toLowerCase())
        ) {
          return
        }
        // ONUs are purely FTTH / PON devices
        if (networkSystem === "lan") return

        const latlng: [number, number] = [Number(onu.lat), Number(onu.lng)]
        bounds.push(latlng)

        const onuIcon = L.divIcon({
          className: "custom-onu-marker",
          html: `
            <div style="
              display: flex;
              align-items: center;
              justify-content: center;
              width: 30px;
              height: 30px;
              background: linear-gradient(135deg, #0ea5e9, #0284c7);
              border: 2px solid #ffffff;
              border-radius: 50%;
              color: #ffffff;
              box-shadow: 0 4px 12px rgba(0,0,0,0.45), 0 0 10px rgba(14,165,233,0.5);
              cursor: pointer;
            ">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#ffffff" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round">
                <rect x="2" y="2" width="20" height="8" rx="2" ry="2"/>
                <rect x="2" y="14" width="20" height="8" rx="2" ry="2"/>
                <line x1="6" y1="6" x2="6.01" y2="6"/>
                <line x1="6" y1="18" x2="6.01" y2="18"/>
              </svg>
            </div>
          `,
          iconSize: [30, 30],
          iconAnchor: [15, 15],
          popupAnchor: [0, -15],
        })

        const marker = L.marker(latlng, { icon: onuIcon })
        marker.bindPopup(`
          <div style="font-family: system-ui, sans-serif; min-width: 210px; padding: 2px;">
            <div style="padding-right: 24px; margin-bottom: 4px;">
              <strong style="color: #ffffff; font-size: 13px; display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">ONU ${onu.name}</strong>
            </div>
            <p style="color: #94a3b8; font-size: 11px; margin: 0 0 6px 0;">Pelanggan: <strong style="color: #ffffff;">${onu.customer || "Belum ditautkan"}</strong></p>
            <div style="background: #0B0E14; border: 1px solid #212B3B; border-radius: 10px; padding: 7px; font-size: 11px; line-height: 1.6; color: #cbd5e1;">
              <div>Terkoneksi ke ODP: <strong style="color: #ffffff;">${onu.odp || "-"}</strong></div>
              ${onu.serial ? `<div style="font-family: monospace; font-size: 10px; color: #38bdf8;">SN: ${onu.serial}</div>` : ""}
              <div style="font-family: monospace; font-size: 10px; color: #64748b; margin-top: 3px;">GPS: ${Number(onu.lat).toFixed(5)}, ${Number(onu.lng).toFixed(5)}</div>
            </div>
          </div>
        `)
        layerGroup.addLayer(marker)
      })
    }

    if (!initialFitDoneRef.current && bounds.length > 0 && !clickedCoord) {
      try {
        if (map && (map as any)._loaded) {
          map.fitBounds(L.latLngBounds(bounds), { padding: [40, 40], maxZoom: 16 })
        }
      } catch (e) {}
      initialFitDoneRef.current = true
    }
  }, [
    showOdp,
    showOnu,
    showCustomers,
    showLines,
    showRouters,
    networkSystem,
    animateLines,
    boxTypeFilter,
    odpStatusFilter,
    custStatusFilter,
    searchQuery,
    odpsState,
    onusMarkers,
    customerMarkersState,
    connectionsState,
    routersState,
    editingCable,
    editingWaypoints,
    isLocked,
  ])

  // ── RENDER MARKER VISUAL TITIK TERPILIH (KLIK KANAN PETA) ──
  useEffect(() => {
    const map = mapInstanceRef.current
    if (!map) return

    if (clickedCoordMarkerRef.current) {
      try {
        map.removeLayer(clickedCoordMarkerRef.current)
      } catch (e) {}
      clickedCoordMarkerRef.current = null
    }

    if (!clickedCoord) return

    const selectedIcon = L.divIcon({
      className: "custom-selected-point-marker",
      html: `
        <div style="position: relative; display: flex; flex-direction: column; align-items: center; justify-content: center; pointer-events: auto;">
          <!-- Pulsing Radar Rings -->
          <div style="position: absolute; top: -4px; width: 44px; height: 44px; border-radius: 50%; background: rgba(0, 194, 255, 0.4); animation: ping 1.8s cubic-bezier(0, 0, 0.2, 1) infinite; pointer-events: none;"></div>
          <div style="position: absolute; top: 0px; width: 36px; height: 36px; border-radius: 50%; background: rgba(0, 115, 198, 0.45); animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite; pointer-events: none;"></div>

          <!-- Center Target Pin -->
          <div style="
            position: relative;
            z-index: 10;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            background: linear-gradient(135deg, #00C2FF, #0073C6);
            border: 2.5px solid #ffffff;
            border-radius: 50%;
            color: #ffffff;
            box-shadow: 0 4px 16px rgba(0,0,0,0.6), 0 0 16px rgba(0, 194, 255, 0.8);
            cursor: pointer;
            transition: transform 0.15s ease;
          " title="Titik Terpilih: Klik untuk Buka Menu Aksi">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#ffffff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
              <path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/>
              <circle cx="12" cy="10" r="3"/>
            </svg>
          </div>

          <!-- Coordinate Tag Pill -->
          <div style="
            position: relative;
            z-index: 11;
            margin-top: 4px;
            background: #0B2138;
            border: 1px solid #00C2FF;
            color: #38bdf8;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-size: 10px;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 9999px;
            white-space: nowrap;
            box-shadow: 0 2px 8px rgba(0,0,0,0.6);
            pointer-events: none;
          ">
            ${clickedCoord.lat}, ${clickedCoord.lng}
          </div>
        </div>
      `,
      iconSize: [44, 65],
      iconAnchor: [22, 18],
      popupAnchor: [0, -20],
    })

    const marker = L.marker([clickedCoord.lat, clickedCoord.lng], {
      icon: selectedIcon,
      zIndexOffset: 1000,
    }).addTo(map)

    marker.on("click", () => {
      if (isAdminRef.current) {
        setQuickActionModalOpen(true)
      }
    })

    clickedCoordMarkerRef.current = marker

    return () => {
      if (clickedCoordMarkerRef.current && map) {
        try {
          map.removeLayer(clickedCoordMarkerRef.current)
        } catch (e) {}
        clickedCoordMarkerRef.current = null
      }
    }
  }, [clickedCoord])

  const copyCoordinates = () => {
    if (!clickedCoord) return
    navigator.clipboard.writeText(`${clickedCoord.lat}, ${clickedCoord.lng}`)
    setCopied(true)
    setTimeout(() => setCopied(false), 2000)
  }

  const panToLocation = (lat: number, lng: number) => {
    if (!mapInstanceRef.current || !lat || !lng) return
    try {
      mapInstanceRef.current.flyTo([lat, lng], 18, { duration: 1.2 })
    } catch (e) {}
    window.scrollTo({ top: 120, behavior: "smooth" })
  }

  const handleZoomIn = () => {
    if (mapInstanceRef.current) {
      try {
        mapInstanceRef.current.zoomIn()
      } catch (e) {}
    }
  }

  const handleZoomOut = () => {
    if (mapInstanceRef.current) {
      try {
        mapInstanceRef.current.zoomOut()
      } catch (e) {}
    }
  }

  const handleResetZoom = () => {
    if (mapInstanceRef.current) {
      try {
        mapInstanceRef.current.setView([-6.200000, 106.816666], 13)
      } catch (e) {}
    }
  }

  const resetAllFilters = () => {
    setShowOdp(true)
    setShowCustomers(true)
    setShowLines(true)
    setShowOnu(true)
    setShowRouters(true)
    setBoxTypeFilter("all")
    setOdpStatusFilter("all")
    setCustStatusFilter("all")
    setFilterModalOpen(false)
    showToast("Filter peta telah direset ke default.")
  }

  const validOdpCount = odpsState.filter((o) => {
    if (!o.lat || !o.lng || o.lat === 0) return false
    if (networkSystem === "pon") return o.type === "odc" || o.type === "odp" || !o.type
    if (networkSystem === "lan") return o.type === "htb" || o.type === "switch"
    return true
  }).length

  const validCustomerCount = customerMarkersState.filter((c) => c.lat && c.lng && c.lat !== 0).length

  const currentBrand = role === "technician" ? technicianBrand : role === "collector" ? collectorBrand : adminBrand
  const currentSidebar = role === "technician" ? technicianSidebarItems : role === "collector" ? collectorSidebarItems : adminSidebarItems
  const currentNav = role === "technician" ? technicianNavItems : role === "collector" ? collectorNavItems : adminNavItems

  const pageTitle = role === "collector" || role === "technician" ? "Peta Jaringan GIS" : "Peta Jaringan FTTH"

  return (
    <AppLayout
      title={pageTitle}
      brand={currentBrand}
      sidebarItems={currentSidebar}
      navItems={currentNav}
    >
      <div className="space-y-4 sm:space-y-6 select-none">
        {/* ── TOP MAP CONTROLS BAR ── */}
        <div className="flex items-center justify-between gap-2.5 rounded-2xl border border-gray-200 bg-white p-3 sm:p-4 shadow-xs dark:border-gray-800 dark:bg-white/[0.03]">
          <div className="flex items-center gap-2 flex-wrap min-w-0">
            {/* Auto-Refresh Status Button */}
            <button
              type="button"
              onClick={() => {
                fetchLiveMapData(false)
                setRefreshCountdown(30)
              }}
              disabled={isLiveUpdating}
              className={cn(
                "h-10 flex items-center gap-1.5 rounded-xl border px-3 text-xs font-bold transition shrink-0 cursor-pointer",
                isAutoRefresh
                  ? "border-emerald-600 bg-emerald-500 text-white shadow-xs"
                  : "border-gray-200 bg-white hover:bg-gray-50 text-gray-700 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800"
              )}
              title={`Sinkronisasi Otomatis Real-time (30 detik). Terakhir sinkron: ${lastLiveUpdate}.`}
            >
              <span className={cn("inline-flex rounded-full h-2 w-2", isAutoRefresh ? "bg-white" : "bg-gray-400")} />
              <RefreshCw className={cn("h-3.5 w-3.5", isLiveUpdating && "animate-spin text-white")} />
              <span className="font-mono text-xs font-bold">
                {isLiveUpdating ? "Sync..." : `Live ${refreshCountdown}s`}
              </span>
            </button>

            {/* Quick Reset Center */}
            <button
              type="button"
              onClick={handleResetZoom}
              className="h-10 flex items-center gap-1.5 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:hover:bg-gray-800 px-3 text-xs font-bold text-gray-700 dark:text-gray-200 transition shrink-0"
              title="Pusatkan Peta ke Lokasi Kantor / Base"
            >
              <Navigation className="h-3.5 w-3.5 text-brand-500" />
              <span className="hidden sm:inline">Pusatkan Peta</span>
            </button>

            {/* Layer Filter Modal Trigger */}
            <button
              type="button"
              onClick={() => setFilterModalOpen(true)}
              className="h-10 flex items-center gap-1.5 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:hover:bg-gray-800 px-3 text-xs font-bold text-gray-700 dark:text-gray-200 transition shrink-0"
            >
              <Layers className="h-3.5 w-3.5 text-purple-500" />
              <span>Layer Peta</span>
            </button>
          </div>

          <div className="flex items-center gap-2 shrink-0">
            {/* Fullscreen Toggle */}
            <button
              type="button"
              onClick={toggleFullscreen}
              className="h-10 w-10 flex items-center justify-center rounded-xl border border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:hover:bg-gray-800 text-gray-700 dark:text-gray-200 transition shrink-0"
              title="Fullscreen Peta"
            >
              {isFullscreen ? <Minimize2 className="h-4 w-4" /> : <Maximize2 className="h-4 w-4" />}
            </button>
          </div>
        </div>
        {/* ── MAP TOAST BADGE (NON-BLOCKING) ── */}
        {toastMessage && (
          <div
            className="fixed bottom-20 sm:bottom-12 left-1/2 -translate-x-1/2 z-[99999] pointer-events-none animate-in fade-in-50 slide-in-from-bottom-4 duration-200"
          >
            <div className="flex items-center gap-2.5 rounded-2xl border border-[#0073C6]/50 bg-[#0B2138]/95 px-4 py-2.5 text-xs text-white shadow-sm shadow-black/80 backdrop-blur-xl">
              <CheckCircle2 className="h-4 w-4 text-[#00C2FF] shrink-0" />
              <span className="font-semibold text-slate-100">{toastMessage}</span>
            </div>
          </div>
        )}

        {/* ── MAPPING NETWORK & GIS HEAVY DATA SYNC OVERLAY ── */}
        {isMapLoading && (
          <div className="fixed inset-0 z-[9999] flex flex-col items-center justify-center bg-[#0B0E14]/90 p-4 backdrop-blur-xl select-none animate-in fade-in-50 duration-300">
            <div className="relative flex flex-col items-center max-w-sm w-full p-6 text-center space-y-4">
              {/* Map Loading Animation */}
              <div className="relative flex h-16 w-16 items-center justify-center">
                <div className="flex h-14 w-14 items-center justify-center rounded-2xl bg-brand-500/10 border border-brand-500/30 text-brand-500 shadow-sm">
                  <MapPin className="h-7 w-7 text-brand-500" />
                </div>
              </div>

              <div className="space-y-1.5">
                <h3 className="font-display text-base sm:text-lg font-bold text-white tracking-tight">
                  Memuat Peta Jaringan &amp; Data GIS
                </h3>
                <p className="text-xs text-slate-300 font-medium leading-relaxed">
                  Sinkronisasi titik ODP, jalur kabel fiber optik, dan lokasi modem pelanggan...
                </p>
              </div>

              {/* Micro Badges Data Count */}
              <div className="flex flex-wrap items-center justify-center gap-1.5 pt-1">
                <span className="rounded-md border border-[#0073C6]/40 bg-[#0B2138] px-2 py-0.5 text-[10px] font-bold text-[#00C2FF]">
                  {odpsState.length || odps.length} Box ODP
                </span>
                <span className="rounded-md border border-emerald-500/40 bg-[#0B281E] px-2 py-0.5 text-[10px] font-bold text-emerald-400">
                  {connectionsState.length || connections.length} Jalur Kabel
                </span>
                <span className="rounded-md border border-[#212B3B] bg-[#161B22] px-2 py-0.5 text-[10px] font-medium text-slate-300">
                  {allCustomersState.length || customerMarkersState.length || 0} Titik Pelanggan
                </span>
              </div>

              <div className="w-48 h-1 bg-[#1E2633] rounded-full overflow-hidden mt-2">
                <div className="h-full bg-gradient-to-r from-[#0073C6] to-[#00C2FF] animate-pulse w-full rounded-full" />
              </div>
            </div>
          </div>
        )}



        {/* Content Body */}
        <div className="space-y-4">
          {/* Mobile-Friendly Single Toolbar: 1 Search Bar + 1 Filter Button */}
          <div className="rounded-2xl border border-gray-200 bg-white p-3 shadow-xs dark:border-gray-800 dark:bg-white/[0.03] flex items-center justify-between gap-2.5">
            {/* Search Input */}
            <div className="relative flex-1">
              <Search className="absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
              <input
                type="text"
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                placeholder="Cari Box ODP/HTB, nama pelanggan, IP Aktif, PPPoE, MAC..."
                className="h-10 w-full rounded-xl border border-gray-200 bg-gray-50/75 dark:bg-gray-900/50 pl-10 pr-3 text-xs text-gray-900 dark:text-white placeholder:text-gray-400 focus:outline-none focus:border-brand-500 transition"
              />
            </div>

            {/* Single Unified Filter Button */}
            <button
              type="button"
              onClick={() => setFilterModalOpen(true)}
              className={cn(
                "relative flex h-10 items-center gap-1.5 rounded-xl border px-3.5 text-xs font-bold transition-all shrink-0 cursor-pointer",
                activeFiltersCount > 0
                  ? "border-brand-500 bg-brand-50 dark:bg-brand-500/10 text-brand-600 dark:text-brand-400"
                  : "border-gray-200 bg-white hover:bg-gray-50 text-gray-700 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800"
              )}
            >
              <SlidersHorizontal className="h-4 w-4" />
              <span className="hidden sm:inline">Filter Peta</span>
              {activeFiltersCount > 0 && (
                <span className="flex h-4 min-w-4 items-center justify-center rounded-full bg-brand-500 text-[10px] font-extrabold text-white px-1">
                  {activeFiltersCount}
                </span>
              )}
            </button>
          </div>

          {/* Interactive Leaflet Map Container */}
          <div className="relative rounded-2xl border border-gray-200 dark:border-gray-800 bg-gray-100 dark:bg-gray-950 overflow-hidden shadow-xs h-[380px] sm:h-[480px] lg:h-[600px] w-full">
            <div ref={mapContainerRef} className="h-full w-full z-10" />

            {/* Top-Right In-Map Controls: Icon-only Vertical Stack from Top to Bottom */}
            <div className="absolute top-3 sm:top-4 right-3 sm:right-4 z-20 flex flex-col items-center gap-1.5 sm:gap-2">
              {/* 1. Map Layers Popover Toggle */}
              <div className="relative">
                <button
                  type="button"
                  onClick={() => setShowStyleMenu(!showStyleMenu)}
                  className="flex h-9 w-9 items-center justify-center rounded-xl border border-gray-200 bg-white/95 text-gray-700 shadow-md backdrop-blur-md hover:bg-gray-100 hover:text-gray-900 dark:border-gray-800 dark:bg-gray-900/95 dark:text-gray-200 dark:hover:bg-gray-800 transition active:scale-95 cursor-pointer"
                  title={`Jenis Peta: ${TILE_LAYERS[mapStyle].name}`}
                >
                  <Layers className="h-4 w-4 text-brand-500" />
                </button>

                {showStyleMenu && (
                  <div className="absolute top-0 right-11 z-40 w-48 rounded-2xl border border-gray-200 bg-white/95 p-1.5 shadow-xl backdrop-blur-xl dark:border-gray-800 dark:bg-gray-900/95 space-y-1 animate-in fade-in duration-150 text-xs">
                    <div className="px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                      Pilihan Jenis Peta
                    </div>
                    {(Object.keys(TILE_LAYERS) as MapStyle[]).map((key) => {
                      const active = mapStyle === key
                      return (
                        <button
                          key={key}
                          type="button"
                          onClick={() => switchMapStyle(key)}
                          className={cn(
                            "w-full flex items-center justify-between rounded-xl px-2.5 py-2 text-left transition-all cursor-pointer",
                            active
                              ? "bg-brand-50 text-brand-600 font-bold border border-brand-200 dark:bg-brand-500/15 dark:text-brand-400 dark:border-brand-800"
                              : "text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800"
                          )}
                        >
                          <span>{TILE_LAYERS[key].name}</span>
                          {active && <Check className="h-3.5 w-3.5" />}
                        </button>
                      )
                    })}
                  </div>
                )}
              </div>

              {/* 2. Fiber Flow Line Animation Toggle */}
              <button
                type="button"
                onClick={toggleAnimation}
                className={cn(
                  "flex h-9 w-9 items-center justify-center rounded-xl border transition-all shadow-md active:scale-95 backdrop-blur-md cursor-pointer",
                  animateLines
                    ? "border-emerald-500 bg-emerald-50 text-emerald-600 dark:bg-emerald-950/40 dark:text-emerald-400"
                    : "border-gray-200 bg-white/95 text-gray-500 hover:text-gray-900 dark:border-gray-800 dark:bg-gray-900/95 dark:text-gray-400 dark:hover:text-white"
                )}
                title={animateLines ? "Animasi Aliran Kabel: AKTIF" : "Animasi Aliran Kabel: NONAKTIF"}
              >
                <Activity className={cn("h-4 w-4", animateLines ? "text-emerald-500" : "text-gray-400")} />
              </button>

              {/* 3. Center / Recenter Map */}
              <button
                type="button"
                onClick={handleResetZoom}
                className="flex h-9 w-9 items-center justify-center rounded-xl border border-gray-200 bg-white/95 text-gray-700 shadow-md backdrop-blur-md hover:bg-gray-100 dark:border-gray-800 dark:bg-gray-900/95 dark:text-gray-200 dark:hover:bg-gray-800 transition active:scale-95 cursor-pointer"
                title="Pusatkan Peta (Recenter)"
              >
                <Crosshair className="h-4 w-4 text-amber-500" />
              </button>

              {/* 4. Zoom In */}
              <button
                type="button"
                onClick={handleZoomIn}
                className="flex h-9 w-9 items-center justify-center rounded-xl border border-gray-200 bg-white/95 text-gray-700 shadow-md backdrop-blur-md hover:bg-gray-100 dark:border-gray-800 dark:bg-gray-900/95 dark:text-gray-200 dark:hover:bg-gray-800 transition active:scale-95 cursor-pointer"
                title="Zoom In"
              >
                <ZoomIn className="h-4 w-4 text-brand-500" />
              </button>

              {/* 5. Zoom Out */}
              <button
                type="button"
                onClick={handleZoomOut}
                className="flex h-9 w-9 items-center justify-center rounded-xl border border-gray-200 bg-white/95 text-gray-700 shadow-md backdrop-blur-md hover:bg-gray-100 dark:border-gray-800 dark:bg-gray-900/95 dark:text-gray-200 dark:hover:bg-gray-800 transition active:scale-95 cursor-pointer"
                title="Zoom Out"
              >
                <ZoomOut className="h-4 w-4 text-gray-500" />
              </button>

              {/* 6. Locate GPS */}
              <button
                type="button"
                onClick={panToUserLocation}
                className="flex h-9 w-9 items-center justify-center rounded-xl border border-gray-200 bg-white/95 text-gray-700 shadow-md backdrop-blur-md hover:bg-gray-100 dark:border-gray-800 dark:bg-gray-900/95 dark:text-gray-200 dark:hover:bg-gray-800 transition active:scale-95 cursor-pointer"
                title="Posisi GPS Perangkat"
              >
                <LocateFixed className="h-4 w-4 text-emerald-500" />
              </button>

              {/* 7. Kunci Admin (Anti-Geser / Proteksi Sentuhan Tidak Sengaja) */}
              {isAdmin && (
                <button
                  type="button"
                  onClick={toggleAdminLock}
                  className={cn(
                    "flex h-9 w-9 items-center justify-center rounded-xl border transition-all shadow-md active:scale-95 backdrop-blur-md cursor-pointer",
                    isLocked
                      ? "border-amber-500 bg-amber-50 text-amber-600 dark:bg-amber-950/40 dark:text-amber-400"
                      : "border-gray-200 bg-white/95 text-gray-500 hover:text-gray-900 dark:border-gray-800 dark:bg-gray-900/95 dark:text-gray-400 dark:hover:text-white"
                  )}
                  title={
                    isLocked
                      ? "Kunci Admin: AKTIF (Marker Terkunci / Anti-Geser)"
                      : "Kunci Admin: NONAKTIF (Bisa Edit / Geser Marker)"
                  }
                >
                  {isLocked ? (
                    <Lock className="h-4 w-4 text-amber-500" />
                  ) : (
                    <Unlock className="h-4 w-4 text-gray-400" />
                  )}
                </button>
              )}
            </div>

            {/* Cable Route Customization Compact Left HUD */}
            {editingCable && (
              <div className="absolute top-4 left-4 z-30 w-80 max-w-[calc(100vw-32px)] rounded-2xl border border-[#00C2FF]/50 bg-[#0B1522]/95 backdrop-blur-xl p-3.5 shadow-sm text-white animate-in fade-in-50 slide-in-from-left-2 duration-200">
                {/* Header */}
                <div className="flex items-center justify-between gap-2 pb-2 border-b border-[#1E2633]">
                  <div className="flex items-center gap-2 min-w-0">
                    <span className="flex h-6 w-6 shrink-0 items-center justify-center rounded-lg bg-[#00C2FF] text-[#090B0E]">
                      <Route className="h-3.5 w-3.5" />
                    </span>
                    <div className="min-w-0">
                      <h5 className="text-xs font-bold text-white truncate">
                        {editingCable.customer_name}
                      </h5>
                      <span className="text-[10px] text-slate-400 font-mono block truncate">
                        Sesuaikan Jalur Kabel
                      </span>
                    </div>
                  </div>
                  <button
                    type="button"
                    onClick={handleCancelEditCable}
                    className="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg border border-[#212B3B] bg-[#121720] text-slate-400 hover:bg-[#1E2530] hover:text-white transition-all"
                    title="Tutup / Batal"
                  >
                    <X className="h-3.5 w-3.5" />
                  </button>
                </div>

                {/* Cable Specs */}
                <div className="py-2.5 space-y-1.5 text-xs">
                  <div className="flex items-center justify-between text-slate-300">
                    <span className="text-slate-400">Titik Induk:</span>
                    <strong className="text-amber-400 truncate max-w-[150px]">{editingCable.odp_name}</strong>
                  </div>
                  <div className="flex items-center justify-between text-slate-300">
                    <span className="text-slate-400">Panjang Kabel:</span>
                    <strong className="text-emerald-400 font-mono">
                      ~{calculatePathLengthMeters([[editingCable.odp_lat, editingCable.odp_lng], ...editingWaypoints, [editingCable.target_lat, editingCable.target_lng]])} M
                    </strong>
                  </div>
                  <div className="flex items-center justify-between text-slate-300">
                    <span className="text-slate-400">Titik Belokan:</span>
                    <strong className="text-[#00C2FF] font-mono">{editingWaypoints.length} Tiang / Belokan</strong>
                  </div>
                </div>

                {/* Tip */}
                <p className="text-[10px] text-slate-400 leading-normal pb-2.5">
                  Klik garis untuk menambah belokan baru, lalu geser titik nomor untuk mengarahkan rute kabel.
                </p>

                {/* Actions */}
                <div className="flex items-center gap-1.5 pt-2 border-t border-[#1E2633]">
                  <button
                    type="button"
                    onClick={handleResetStraightCable}
                    className="flex h-8 items-center gap-1 px-2.5 rounded-xl border border-[#212B3B] bg-[#161B22] hover:bg-[#1E2530] text-slate-300 text-xs font-semibold transition-all"
                    title="Luruskan Kembali"
                  >
                    <RotateCcw className="h-3 w-3" />
                    <span>Luruskan</span>
                  </button>

                  <button
                    type="button"
                    onClick={handleCancelEditCable}
                    className="flex h-8 items-center justify-center px-2.5 rounded-xl border border-[#212B3B] bg-[#161B22] hover:bg-[#1E2530] text-slate-300 text-xs font-semibold transition-all"
                  >
                    Batal
                  </button>

                  <button
                    type="button"
                    disabled={isSavingCable}
                    onClick={handleSaveCablePath}
                    className="flex-1 flex h-8 items-center justify-center gap-1.5 px-3 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold disabled:opacity-50 transition-all ml-auto"
                  >
                    {isSavingCable ? <RefreshCw className="h-3 w-3 animate-spin" /> : <Save className="h-3 w-3" />}
                    <span>Simpan</span>
                  </button>
                </div>
              </div>
            )}

            {/* High Zoom Precision Mode & Right-Click Tip Notification Bar */}
            {!editingCable && (
              <div className="absolute top-3 sm:top-4 left-3 sm:left-4 z-20 flex items-center gap-2 max-w-[calc(100vw-80px)] sm:max-w-none pointer-events-none">
                <div className="pointer-events-auto flex items-center gap-1.5 sm:gap-2 rounded-xl border border-gray-200 dark:border-gray-800 bg-white/90 dark:bg-gray-900/90 backdrop-blur-md px-2 py-1 sm:px-3 sm:py-1.5 text-gray-900 dark:text-white shadow-sm animate-in fade-in duration-200">
                  <span className="h-1.5 w-1.5 sm:h-2 sm:w-2 rounded-full bg-brand-500 shrink-0" />
                  <span className="text-[10px] sm:text-[11px] font-bold text-gray-900 dark:text-white whitespace-nowrap flex items-center gap-1.5">
                    <span>Zoom {zoomLevel}x</span>
                    {isAdmin && isLocked && (
                      <span className="inline-flex items-center gap-1 px-1.5 py-0.2 rounded bg-amber-500/20 text-amber-300 border border-amber-500/40 text-[9px] font-bold uppercase tracking-wider">
                        <Lock className="h-2.5 w-2.5" />
                        <span>Terkunci</span>
                      </span>
                    )}
                    <span className="hidden sm:inline">
                      {isLocked
                        ? " · Mode Proteksi Aktif (Marker Terkunci)"
                        : !isAdmin
                        ? " · Klik Node untuk Detail & Uji Ping"
                        : " · Klik Kanan untuk Aksi Titik"}
                    </span>
                  </span>
                  {zoomLevel >= 15 && canEdit && (
                    <button
                      type="button"
                      onClick={() => openAdd()}
                      className="ml-0.5 text-[9.5px] sm:text-[10px] font-bold bg-[#0073C6] hover:bg-[#0084E3] text-white px-1.5 sm:px-2 py-0.5 rounded-md shrink-0 whitespace-nowrap"
                    >
                      <span className="hidden sm:inline">+ Tambah di Tengah</span>
                      <span className="sm:hidden">+ Tambah</span>
                    </button>
                  )}
                </div>
              </div>
            )}

            {/* Clicked Coordinates Floating Action Card */}
            {clickedCoord && (
              <div className="absolute bottom-4 left-4 right-4 sm:left-auto sm:right-4 z-20 max-w-sm rounded-2xl border border-[#0073C6]/40 bg-[#0B2138]/95 backdrop-blur-md p-3.5 shadow-sm text-white animate-in fade-in-50 slide-in-from-bottom-2 duration-200">
                <div className="flex items-center justify-between gap-2 mb-2">
                  <span className="text-[11px] font-bold uppercase tracking-wider text-[#00C2FF] flex items-center gap-1">
                    <Navigation className="h-3.5 w-3.5" />
                    <span>Titik Koordinat Terpilih</span>
                  </span>
                  <button
                    onClick={() => setClickedCoord(null)}
                    className="text-slate-400 hover:text-white text-xs font-bold"
                  >
                    &times;
                  </button>
                </div>

                <div className="rounded-xl border border-[#1E2633] bg-[#0B0E14] p-2 text-xs font-mono text-slate-200 mb-2.5">
                  {clickedCoord.lat}, {clickedCoord.lng}
                </div>

                <div className="flex items-center gap-2">
                  {isAdmin && (
                    <button
                      type="button"
                      onClick={() => setQuickActionModalOpen(true)}
                      className="flex-1 flex items-center justify-center gap-1.5 rounded-xl bg-[#0073C6] hover:bg-[#0084E3] py-2 text-xs font-bold text-white transition-all"
                    >
                      <Plus className="h-3.5 w-3.5" />
                      <span>Pilih Aksi Titik</span>
                    </button>
                  )}

                  <button
                    type="button"
                    onClick={copyCoordinates}
                    className={cn(
                      "flex items-center justify-center gap-1 rounded-xl border border-[#212B3B] bg-[#161B22] hover:bg-[#1C232E] py-2 px-3 text-xs font-bold text-slate-200 hover:text-white transition-all",
                      !isAdmin ? "w-full" : ""
                    )}
                    title="Salin Koordinat"
                  >
                    <Copy className="h-3.5 w-3.5" />
                    <span>{copied ? "Tersalin" : "Salin Koordinat"}</span>
                  </button>
                </div>
              </div>
            )}
          </div>

          {/* ── DAFTAR TITIK DISTRIBUSI: TABLE VS GRID ── */}
          <div className="space-y-4 pt-2">
            <div className="flex items-center justify-between border-b border-gray-200 dark:border-gray-800 pb-3">
              <div>
                <h3 className="text-sm sm:text-base font-bold text-gray-900 dark:text-white flex items-center gap-2">
                  {networkSystem === "lan" ? (
                    <>
                      <Server className="h-4 w-4 text-cyan-500" />
                      <span>Data Titik Media Converter HTB &amp; Switch</span>
                    </>
                  ) : networkSystem === "pon" ? (
                    <>
                      <Network className="h-4 w-4 text-brand-500" />
                      <span>Data Titik Distribusi FTTH</span>
                    </>
                  ) : (
                    <>
                      <Layers className="h-4 w-4 text-purple-500" />
                      <span>Data Titik Distribusi Jaringan</span>
                    </>
                  )}
                  <span className="inline-flex items-center rounded-full bg-brand-500 px-2 py-0.5 text-[10px] font-bold text-white shadow-xs">
                    {filteredOdpTable.length} Titik
                  </span>
                </h3>
              </div>
            </div>

            {/* ── 1-LINE TOOLBAR DATA TITIK DISTRIBUSI ── */}
            <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2.5 rounded-2xl border border-gray-200 bg-white p-3 sm:p-4 shadow-xs dark:border-gray-800 dark:bg-white/[0.03]">
              <div className="flex items-center gap-2 flex-wrap sm:flex-nowrap flex-1 min-w-0">
                {/* System Selector Dropdown */}
                <div className="relative flex items-center shrink-0">
                  <select
                    value={networkSystem}
                    onChange={(e) => handleSelectNetworkSystem(e.target.value as "all" | "pon" | "lan")}
                    className="h-10 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:hover:bg-gray-800 px-3 pr-8 text-xs font-bold text-gray-700 dark:text-gray-200 transition focus:outline-none focus:border-brand-500 cursor-pointer"
                    title="Pilih Sistem Jaringan Titik Distribusi"
                  >
                    <option value="all">Semua Sistem</option>
                    <option value="pon">Sistem FTTH</option>
                    <option value="lan">Sistem LAN</option>
                  </select>
                  <ChevronDown className="pointer-events-none absolute right-2.5 h-3.5 w-3.5 text-gray-400" />
                </div>

                {/* Category / Type Filter Dropdown */}
                <div className="relative flex items-center shrink-0">
                  <select
                    value={categoryFilter}
                    onChange={(e) => setCategoryFilter(e.target.value as any)}
                    className="h-10 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:hover:bg-gray-800 px-3 pr-8 text-xs font-bold text-gray-700 dark:text-gray-200 transition focus:outline-none focus:border-brand-500 cursor-pointer"
                  >
                    <option value="all">Semua Tipe Titik ({odpsState.length || odps.length})</option>
                    {networkSystem !== "lan" && (
                      <>
                        <option value="odp">Box ODP ({standardOdpCount})</option>
                        <option value="odc">Box ODC ({odcCount})</option>
                      </>
                    )}
                    {networkSystem !== "pon" && (
                      <>
                        <option value="htb">HTB Converter ({htbCount})</option>
                        <option value="switch">Switch Hub ({switchCount})</option>
                      </>
                    )}
                    <option value="available">Port Tersedia</option>
                    <option value="full">Kapasitas Penuh</option>
                  </select>
                  <ChevronDown className="pointer-events-none absolute right-2.5 h-3.5 w-3.5 text-gray-400" />
                </div>
              </div>

              <div className="flex items-center gap-2 shrink-0">
                {/* Search Input */}
                <div className="relative flex-1 sm:w-60">
                  <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-gray-400" />
                  <input
                    type="text"
                    value={odpTableSearch}
                    onChange={(e) => setOdpTableSearch(e.target.value)}
                    placeholder="Cari titik distribusi..."
                    className="h-10 w-full rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 pl-9 pr-7 text-xs text-gray-900 dark:text-white placeholder:text-gray-400 focus:outline-none focus:border-brand-500 transition"
                  />
                  {odpTableSearch && (
                    <button
                      onClick={() => setOdpTableSearch("")}
                      className="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 text-xs font-bold"
                    >
                      &times;
                    </button>
                  )}
                </div>

                {/* ViewModeSwitcher */}
                <ViewModeSwitcher
                  value={viewMode}
                  onChange={setViewMode}
                  storageKey="nodera_gis_map_view"
                />

                {/* Tambah Titik Button */}
                {isAdmin && (
                  <button
                    type="button"
                    onClick={() => openAdd()}
                    className="flex h-10 items-center justify-center gap-1.5 rounded-xl bg-brand-500 hover:bg-brand-600 active:scale-95 text-white px-3.5 text-xs font-bold transition shadow-xs shrink-0 cursor-pointer"
                  >
                    <Plus className="h-4 w-4 shrink-0" />
                    <span className="hidden sm:inline">Tambah Titik</span>
                  </button>
                )}
              </div>
            </div>

            {filteredOdpTable.length === 0 ? (
              <div className="p-10 rounded-2xl border border-dashed border-gray-200 dark:border-gray-800 bg-gray-50/50 dark:bg-white/[0.02] text-center space-y-2">
                <MapPin className="mx-auto h-8 w-8 text-amber-500 opacity-80" />
                <p className="text-sm font-bold text-gray-900 dark:text-white">
                  {odpTableSearch ? "Tidak ada titik distribusi sesuai pencarian" : "Belum ada titik distribusi terdaftar"}
                </p>
                <p className="text-xs text-gray-500 dark:text-gray-400 max-w-sm mx-auto">
                  {odpTableSearch
                    ? `Tidak ditemukan titik distribusi dengan kata kunci "${odpTableSearch}".`
                    : "Tambahkan titik distribusi baru melalui tombol di toolbar atas."}
                </p>
              </div>
            ) : viewMode === "table" ? (
              <div className="rounded-xl border border-gray-200 bg-white shadow-xs dark:border-gray-800 dark:bg-gray-900 overflow-hidden">
                <div className="table-scrollbar overflow-x-auto">
                  <table className="w-full text-left border-collapse min-w-[800px]">
                    <thead>
                      <tr className="border-b border-gray-200 bg-gray-50/50 text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:border-gray-800 dark:bg-gray-800/40 dark:text-gray-400">
                        <th className="py-3 px-4">Titik Distribusi</th>
                        <th className="py-3 px-4">Tipe Node</th>
                        <th className="py-3 px-4">Kapasitas Port</th>
                        <th className="py-3 px-4">Koordinat GPS</th>
                        <th className="py-3 px-4">Status</th>
                        <th className="py-3 px-4 text-right">Aksi</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-100 text-xs dark:divide-gray-800">
                      {filteredOdpTable.map((o: Odp) => {
                        const pct = o.capacity > 0 ? Math.round((o.used_ports / o.capacity) * 100) : 0
                        const isFull = o.used_ports >= o.capacity
                        const isOdc = o.type === "odc"
                        const isHtb = o.type === "htb"
                        const isSwitch = o.type === "switch"

                        return (
                          <tr key={o.id} className="hover:bg-gray-50/80 dark:hover:bg-gray-800/40 transition-colors">
                            <td className="py-3 px-4">
                              <div className="flex items-center gap-2.5">
                                <div className={cn(
                                  "flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-white font-bold",
                                  isOdc ? "bg-purple-600" : isHtb ? "bg-cyan-600" : isSwitch ? "bg-emerald-600" : "bg-blue-600"
                                )}>
                                  {isOdc ? <Database className="h-4 w-4" /> : isHtb ? <Server className="h-4 w-4" /> : isSwitch ? <Cpu className="h-4 w-4" /> : <Network className="h-4 w-4" />}
                                </div>
                                <div>
                                  <span className="font-bold text-gray-900 dark:text-white">{o.name}</span>
                                  <p className="text-[11px] text-gray-400">{(o as any).address || (o as any).location || "Lokasi Tiang"}</p>
                                </div>
                              </div>
                            </td>
                            <td className="py-3 px-4">
                              <span className={cn(
                                "px-2 py-0.5 rounded-lg text-[10px] font-bold text-white shadow-2xs",
                                isOdc ? "bg-purple-600" : isHtb ? "bg-cyan-600" : isSwitch ? "bg-emerald-600" : "bg-blue-600"
                              )}>
                                {isOdc ? "ODC FEEDER" : isHtb ? "HTB CONVERTER" : isSwitch ? "SWITCH HUB" : "ODP SPLITTER"}
                              </span>
                            </td>
                            <td className="py-3 px-4">
                              <div className="flex items-center gap-2">
                                <span className="font-mono font-bold text-gray-900 dark:text-white">{o.used_ports} / {o.capacity}</span>
                                <div className="w-16 h-1.5 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-800">
                                  <div className={cn("h-full rounded-full", pct >= 100 ? "bg-rose-500" : pct >= 75 ? "bg-amber-500" : "bg-emerald-500")} style={{ width: `${Math.min(100, pct)}%` }} />
                                </div>
                                <span className={cn("text-[11px] font-mono font-bold", pct >= 100 ? "text-rose-500" : "text-emerald-500")}>
                                  {isFull ? "FULL" : `Sisa ${Math.max(0, o.capacity - o.used_ports)}`}
                                </span>
                              </div>
                            </td>
                            <td className="py-3 px-4 font-mono text-[11px] text-gray-500 dark:text-gray-400">
                              {o.lat && o.lng ? `${Number(o.lat).toFixed(4)}, ${Number(o.lng).toFixed(4)}` : "-"}
                            </td>
                            <td className="py-3 px-4">
                              <span className={cn(
                                "inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-bold text-white",
                                isFull ? "bg-rose-500" : "bg-emerald-500"
                              )}>
                                {isFull ? "Penuh" : "Tersedia"}
                              </span>
                            </td>
                            <td className="py-3 px-4 text-right">
                              <div className="flex items-center justify-end gap-1.5">
                                <button
                                  type="button"
                                  onClick={() => panToLocation(o.lat, o.lng)}
                                  className="flex h-8 w-8 items-center justify-center rounded-lg bg-brand-500 text-white shadow-xs hover:bg-brand-600 transition-colors"
                                  title="Pusatkan di Peta"
                                >
                                  <Crosshair className="h-3.5 w-3.5" />
                                </button>
                                {isAdmin && (
                                  <>
                                    <button
                                      type="button"
                                      onClick={() => openEdit(o)}
                                      className="flex h-8 w-8 items-center justify-center rounded-lg bg-amber-500 text-white shadow-xs hover:bg-amber-600 transition-colors"
                                      title="Edit Titik"
                                    >
                                      <Pencil className="h-3.5 w-3.5" />
                                    </button>
                                    <button
                                      type="button"
                                      onClick={() => handleDelete(o)}
                                      className="flex h-8 w-8 items-center justify-center rounded-lg bg-rose-500 text-white shadow-xs hover:bg-rose-600 transition-colors"
                                      title="Hapus Titik"
                                    >
                                      <Trash2 className="h-3.5 w-3.5" />
                                    </button>
                                  </>
                                )}
                              </div>
                            </td>
                          </tr>
                        )
                      })}
                    </tbody>
                  </table>
                </div>
              </div>
            ) : (
              <div className="grid items-start gap-3.5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-3 2xl:grid-cols-4">
                {filteredOdpTable.map((o: Odp) => {
                  const pct = o.capacity > 0 ? Math.round((o.used_ports / o.capacity) * 100) : 0
                  const isExpanded = expandedId === o.id
                  const isFull = o.used_ports >= o.capacity
                  const isOdc = o.type === "odc"
                  const isHtb = o.type === "htb"
                  const isSwitch = o.type === "switch"

                  return (
                    <div
                      key={o.id}
                      className={cn(
                        "p-4 sm:p-5 rounded-2xl border bg-white dark:bg-white/[0.03] transition-all select-none group space-y-3.5 text-gray-900 dark:text-white shadow-xs",
                        isExpanded
                          ? "border-brand-500 ring-1 ring-brand-500/40"
                          : isFull
                          ? "border-rose-300 dark:border-rose-900/60"
                          : "border-gray-200 dark:border-gray-800 hover:border-brand-500/50"
                      )}
                    >
                      <div>
                        {/* Header Box */}
                        <div
                          className="flex items-start justify-between gap-2.5 cursor-pointer"
                          onClick={() => setExpandedId(isExpanded ? null : o.id)}
                        >
                          <div className="flex items-center gap-3 min-w-0 flex-1">
                            <div className={cn(
                              "flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border font-bold text-sm group-hover:scale-105 transition-transform",
                              isOdc
                                ? "bg-purple-50 dark:bg-purple-950/40 border-purple-200 dark:border-purple-800 text-purple-600 dark:text-purple-400"
                                : isHtb
                                ? "bg-cyan-50 dark:bg-cyan-950/40 border-cyan-200 dark:border-cyan-800 text-cyan-600 dark:text-cyan-400"
                                : isSwitch
                                ? "bg-emerald-50 dark:bg-emerald-950/40 border-emerald-200 dark:border-emerald-800 text-emerald-600 dark:text-emerald-400"
                                : "bg-blue-50 dark:bg-blue-950/40 border-blue-200 dark:border-blue-800 text-blue-600 dark:text-blue-400"
                            )}>
                              {isOdc ? (
                                <Database className="h-5 w-5 text-purple-600 dark:text-purple-400" />
                              ) : isHtb ? (
                                <Server className="h-5 w-5 text-cyan-600 dark:text-cyan-400" />
                              ) : isSwitch ? (
                                <Cpu className="h-5 w-5 text-emerald-600 dark:text-emerald-400" />
                              ) : (
                                <Network className="h-5 w-5 text-blue-600 dark:text-blue-400" />
                              )}
                            </div>

                            <div className="min-w-0 flex-1">
                              <h4 className="truncate text-sm font-bold text-gray-900 dark:text-white group-hover:text-brand-500 transition-colors">
                                {o.name}
                              </h4>
                              <div className="mt-1 flex items-center gap-1.5 flex-wrap">
                                <span className={cn(
                                  "px-2 py-0.5 rounded-lg text-[10px] font-bold whitespace-nowrap shadow-2xs text-white",
                                  isOdc
                                    ? "bg-purple-600"
                                    : isHtb
                                    ? "bg-cyan-600"
                                    : isSwitch
                                    ? "bg-emerald-600"
                                    : "bg-blue-600"
                                )}>
                                  {isOdc ? "ODC FEEDER" : isHtb ? "HTB CONVERTER" : isSwitch ? "SWITCH HUB" : "ODP SPLITTER"}
                                </span>
                                <span className={cn(
                                  "px-2 py-0.5 rounded-lg text-[10px] font-bold whitespace-nowrap shadow-2xs text-white",
                                  isFull ? "bg-rose-500" : "bg-emerald-500"
                                )}>
                                  {isFull ? "FULL" : `Sisa ${Math.max(0, o.capacity - o.used_ports)} Port`}
                                </span>
                              </div>
                            </div>
                          </div>

                          <div className="shrink-0 p-1">
                            <ChevronDown
                              className={cn(
                                "h-4 w-4 text-gray-400 transition-transform duration-200",
                                isExpanded && "rotate-180 text-brand-500"
                              )}
                            />
                          </div>
                        </div>

                        {/* Utilization Bar */}
                        <div className="mt-3 rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-50/75 dark:bg-gray-900/50 p-2.5">
                          <div className="flex items-center justify-between text-xs font-semibold text-gray-600 dark:text-gray-400">
                            <span>
                              Kapasitas: <strong className="text-gray-900 dark:text-white font-mono">{o.used_ports} / {o.capacity} Port</strong>
                            </span>
                            <span
                              className={cn(
                                "font-bold font-mono",
                                pct >= 100 ? "text-rose-500" : pct >= 75 ? "text-amber-500" : "text-emerald-500"
                              )}
                            >
                              {pct}%
                            </span>
                          </div>
                          <div className="mt-1.5 h-2 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-800">
                            <div
                              className={cn(
                                "h-full rounded-full transition-all",
                                pct >= 100 ? "bg-rose-500" : pct >= 75 ? "bg-amber-500" : "bg-emerald-500"
                              )}
                              style={{ width: `${Math.min(100, Math.max(0, pct))}%` }}
                            />
                          </div>
                        </div>

                        {/* Bottom Actions on Card */}
                        <div className="mt-3 pt-2.5 border-t border-gray-200 dark:border-gray-800 flex items-center justify-between gap-2 text-xs">
                          <span className="font-mono text-[11px] text-gray-500 dark:text-gray-400 truncate">
                            {o.lat ? `${Number(o.lat).toFixed(4)}, ${Number(o.lng).toFixed(4)}` : "No GPS"}
                          </span>

                          <button
                            type="button"
                            onClick={() => panToLocation(o.lat, o.lng)}
                            className="inline-flex items-center gap-1 text-[11px] font-bold text-brand-600 dark:text-brand-400 hover:underline cursor-pointer"
                            title="Sorot Titik di Peta"
                          >
                            <Navigation className="h-3 w-3" />
                            <span>Sorot di Peta</span>
                          </button>
                        </div>
                      </div>

                      {/* Expanded Section */}
                      {isExpanded && (
                        <div
                          className="pt-3 border-t border-gray-200 dark:border-gray-800 space-y-2 text-xs text-gray-700 dark:text-gray-300 animate-in fade-in duration-200"
                          onClick={(e) => e.stopPropagation()}
                        >
                          <div className="flex items-center justify-between py-1 border-b border-gray-100 dark:border-gray-800">
                            <span className="text-gray-500 dark:text-gray-400">Tipe Node</span>
                            <span className="font-semibold text-brand-600 dark:text-brand-400">
                              {isOdc ? "ODC Feeder Fiber Optik" : isHtb ? "Media Converter (HTB)" : isSwitch ? "Switch Hub Distribusi" : "ODP Splitter Fiber Optik"}
                            </span>
                          </div>

                          <div className="flex items-center justify-between py-1 border-b border-gray-100 dark:border-gray-800">
                            <span className="text-gray-500 dark:text-gray-400">Port Tersisa</span>
                            <span className="font-mono text-emerald-600 dark:text-emerald-400 font-bold">
                              {Math.max(0, o.capacity - o.used_ports)} Port Kosong
                            </span>
                          </div>

                          <div className="flex items-center justify-between py-1 border-b border-gray-100 dark:border-gray-800">
                            <span className="text-gray-500 dark:text-gray-400">Koordinat GPS</span>
                            <span className="font-mono text-gray-800 dark:text-gray-200">{o.lat}, {o.lng}</span>
                          </div>

                          <div className="flex items-center justify-between py-1 border-b border-gray-100 dark:border-gray-800">
                            <span className="text-gray-500 dark:text-gray-400">Status Node</span>
                            <span className={cn("font-semibold", pct >= 100 ? "text-rose-500" : "text-emerald-500")}>
                              {pct >= 100 ? "Kapasitas Penuh (Full)" : "Siap Pasang Baru (Tersedia)"}
                            </span>
                          </div>

                          {/* Action Buttons (Admin Only) */}
                          {isAdmin && (
                            <div className="pt-2 flex items-center justify-end gap-2">
                              <button
                                type="button"
                                onClick={() => openEdit(o)}
                                className="flex h-8 w-8 items-center justify-center rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition cursor-pointer"
                                title="Edit Titik"
                              >
                                <Pencil className="h-3.5 w-3.5" />
                              </button>

                              <button
                                type="button"
                                onClick={() => handleDelete(o)}
                                className="flex h-8 w-8 items-center justify-center rounded-xl border border-rose-200 dark:border-rose-900/40 bg-rose-50 dark:bg-rose-950/30 text-rose-600 dark:text-rose-400 hover:bg-rose-100 dark:hover:bg-rose-900/50 transition cursor-pointer"
                                title="Hapus Node"
                              >
                                <Trash2 className="h-3.5 w-3.5" />
                              </button>
                            </div>
                          )}
                        </div>
                      )}
                    </div>
                  )
                })}
              </div>
            )}
          </div>
        </div>

        {/* ── MODAL FILTER TERPADU (SATU TOMBOL FILTER MOBILE-FRIENDLY) ── */}
        <Dialog open={filterModalOpen} onOpenChange={setFilterModalOpen}>
          <DialogContent className="w-[calc(100vw-24px)] sm:max-w-md max-h-[92vh] overflow-y-auto custom-scrollbar p-5 sm:p-6 space-y-4 rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 text-gray-900 dark:text-white shadow-2xl">
            <DialogHeader className="pb-2 border-b border-gray-200 dark:border-gray-800 pr-8">
              <DialogTitle className="text-sm sm:text-base font-bold flex flex-col sm:flex-row sm:items-center justify-between gap-1.5 text-white">
                <div className="flex items-center gap-2 min-w-0">
                  <SlidersHorizontal className="h-4 w-4 text-brand-600 dark:text-brand-400 shrink-0" />
                  <span className="truncate">Filter Tampilan Jaringan</span>
                </div>
                {activeFiltersCount > 0 && (
                  <span className="text-[10px] bg-brand-500 text-white px-2 py-0.5 rounded-full font-bold self-start sm:self-auto shrink-0">
                    {activeFiltersCount} Aktif
                  </span>
                )}
              </DialogTitle>
            </DialogHeader>

            <div className="space-y-4 text-xs">
              {/* Layer Visibility Toggles */}
              <div className="space-y-2">
                <label className="font-bold text-gray-700 dark:text-gray-300 block">Lapisan yang Ditampilkan</label>
                <div className="grid grid-cols-2 gap-2">
                  <button
                    type="button"
                    onClick={() => setShowRouters(!showRouters)}
                    className={cn(
                      "flex items-center justify-between p-2.5 rounded-xl border text-xs font-semibold transition-all",
                      showRouters
                        ? "border-purple-500/50 bg-purple-500/15 text-purple-300"
                        : "border-gray-200 dark:border-gray-800 bg-gray-50/75 dark:bg-gray-900/50 text-gray-400 dark:text-gray-500"
                    )}
                  >
                    <span className="flex items-center gap-1.5">
                      <Server className="h-3.5 w-3.5 text-purple-400" />
                      <span>Router NOC</span>
                    </span>
                    <span className="font-mono text-[11px] font-bold">({routersState.length})</span>
                  </button>

                  <button
                    type="button"
                    onClick={() => setShowOdp(!showOdp)}
                    className={cn(
                      "flex items-center justify-between p-2.5 rounded-xl border text-xs font-semibold transition-all",
                      showOdp
                        ? "border-amber-500/50 bg-amber-500/15 text-amber-300"
                        : "border-gray-200 dark:border-gray-800 bg-gray-50/75 dark:bg-gray-900/50 text-gray-400 dark:text-gray-500"
                    )}
                  >
                    <span className="flex items-center gap-1.5">
                      <Network className="h-3.5 w-3.5 text-amber-400" />
                      <span>{networkSystem === "lan" ? "Titik HTB / Switch" : "Titik ODP / ODC"}</span>
                    </span>
                    <span className="font-mono text-[11px] font-bold">({validOdpCount})</span>
                  </button>

                  <button
                    type="button"
                    onClick={() => setShowCustomers(!showCustomers)}
                    className={cn(
                      "flex items-center justify-between p-2.5 rounded-xl border text-xs font-semibold transition-all",
                      showCustomers
                        ? "border-emerald-500/50 bg-emerald-500/15 text-emerald-300"
                        : "border-gray-200 dark:border-gray-800 bg-gray-50/75 dark:bg-gray-900/50 text-gray-400 dark:text-gray-500"
                    )}
                  >
                    <span className="flex items-center gap-1.5">
                      <Home className="h-3.5 w-3.5 text-emerald-400" />
                      <span>Pelanggan</span>
                    </span>
                    <span className="font-mono text-[11px] font-bold">({validCustomerCount})</span>
                  </button>

                  <button
                    type="button"
                    onClick={() => setShowLines(!showLines)}
                    className={cn(
                      "flex items-center justify-between p-2.5 rounded-xl border text-xs font-semibold transition-all",
                      showLines
                        ? "border-brand-500/50 bg-brand-50 dark:bg-brand-500/10 text-brand-600 dark:text-brand-400"
                        : "border-gray-200 dark:border-gray-800 bg-gray-50/75 dark:bg-gray-900/50 text-gray-400 dark:text-gray-500"
                    )}
                  >
                    <span className="flex items-center gap-1.5">
                      <Cable className="h-3.5 w-3.5 text-brand-600 dark:text-brand-400" />
                      <span>Garis Sambungan</span>
                    </span>
                    <span className="text-[10px] font-bold">{showLines ? "ON" : "OFF"}</span>
                  </button>

                  <button
                    type="button"
                    onClick={() => setShowOnu(!showOnu)}
                    className={cn(
                      "flex items-center justify-between p-2.5 rounded-xl border text-xs font-semibold transition-all",
                      showOnu
                        ? "border-brand-500/50 bg-brand-50 dark:bg-brand-500/10 text-brand-600 dark:text-brand-400"
                        : "border-gray-200 dark:border-gray-800 bg-gray-50/75 dark:bg-gray-900/50 text-gray-400 dark:text-gray-500"
                    )}
                  >
                    <span className="flex items-center gap-1.5">
                      <HardDrive className="h-3.5 w-3.5 text-brand-600 dark:text-brand-400" />
                      <span>Modem ONU</span>
                    </span>
                    <span className="font-mono text-[11px] font-bold">({onusMarkers.length})</span>
                  </button>

                  <button
                    type="button"
                    onClick={() => setAnimateLines(!animateLines)}
                    className={cn(
                      "col-span-2 flex items-center justify-between p-2.5 rounded-xl border text-xs font-semibold transition-all",
                      animateLines
                        ? "border-emerald-500/50 bg-emerald-500/15 text-emerald-300"
                        : "border-gray-200 dark:border-gray-800 bg-gray-50/75 dark:bg-gray-900/50 text-gray-400 dark:text-gray-500"
                    )}
                  >
                    <span className="flex items-center gap-1.5">
                      <Activity className={cn("h-3.5 w-3.5", animateLines ? "text-emerald-400" : "text-gray-400 dark:text-gray-500")} />
                      <span>Animasi Aliran Garis Kabel (Flow Fiber)</span>
                    </span>
                    <span className={cn("text-[10px] font-bold px-2 py-0.5 rounded-md", animateLines ? "bg-emerald-500/20 text-emerald-300" : "bg-slate-800 text-gray-500 dark:text-gray-400")}>
                      {animateLines ? "AKTIF (ON)" : "NONAKTIF (OFF)"}
                    </span>
                  </button>
                </div>
              </div>

              {/* Tipe Node Distribusi */}
              <div className="space-y-2 pt-2 border-t border-gray-200 dark:border-gray-800">
                <label className="font-bold text-gray-700 dark:text-gray-300 block">
                  {networkSystem === "lan" ? "Jenis Perangkat LAN" : "Jenis Titik Distribusi FTTH"}
                </label>
                <div className="grid grid-cols-2 sm:grid-cols-3 gap-2">
                  {(networkSystem === "lan"
                    ? [
                        { id: "all", label: `Semua LAN (${htbCount + switchCount})` },
                        { id: "htb", label: `HTB Converter (${htbCount})` },
                        { id: "switch", label: `Switch Hub (${switchCount})` },
                      ]
                    : [
                        { id: "all", label: `Semua FTTH (${standardOdpCount + odcCount})` },
                        { id: "odp", label: `ODP Splitter (${standardOdpCount})` },
                        { id: "odc", label: `ODC Feeder (${odcCount})` },
                      ]
                  ).map((item) => (
                    <button
                      key={item.id}
                      type="button"
                      onClick={() => setBoxTypeFilter(item.id as any)}
                      className={cn(
                        "p-2 rounded-xl border text-xs font-semibold text-center transition-all",
                        boxTypeFilter === item.id
                          ? "border-brand-500 bg-brand-50 dark:bg-brand-500/10 text-brand-600 dark:text-brand-400"
                          : "border-gray-200 dark:border-gray-800 bg-gray-50/75 dark:bg-gray-900/50 text-gray-500 dark:text-gray-400 hover:text-white"
                      )}
                    >
                      {item.label}
                    </button>
                  ))}
                </div>
              </div>

              {/* Status ODP Capacity Filter */}
              <div className="space-y-2 pt-2 border-t border-gray-200 dark:border-gray-800">
                <label className="font-bold text-gray-700 dark:text-gray-300 block">Kapasitas Port Titik Distribusi</label>
                <div className="grid grid-cols-2 gap-2">
                  {[
                    { id: "all", label: `Semua (${odpsState.length})` },
                    { id: "available", label: `Sedia Port (${availableOdpCount})` },
                    { id: "full", label: `Penuh/Full (${fullOdpCount})` },
                    { id: "critical", label: `Kritis >=75% (${criticalOdpCount})` },
                  ].map((item) => (
                    <button
                      key={item.id}
                      type="button"
                      onClick={() => setOdpStatusFilter(item.id as any)}
                      className={cn(
                        "p-2 rounded-xl border text-xs font-semibold text-center transition-all",
                        odpStatusFilter === item.id
                          ? "border-brand-500 bg-brand-50 dark:bg-brand-500/10 text-brand-600 dark:text-brand-400"
                          : "border-gray-200 dark:border-gray-800 bg-gray-50/75 dark:bg-gray-900/50 text-gray-500 dark:text-gray-400 hover:text-white"
                      )}
                    >
                      {item.label}
                    </button>
                  ))}
                </div>
              </div>

              {/* Status Pelanggan Filter */}
              <div className="space-y-2 pt-2 border-t border-gray-200 dark:border-gray-800">
                <label className="font-bold text-gray-700 dark:text-gray-300 block">Status Konektivitas Pelanggan</label>
                <div className="grid grid-cols-3 gap-2">
                  {[
                    { id: "all", label: `Semua (${customerMarkersState.length})` },
                    { id: "active", label: `Online (${activeCustCount})` },
                    { id: "isolated", label: `Offline (${isolatedCustCount})` },
                  ].map((item) => (
                    <button
                      key={item.id}
                      type="button"
                      onClick={() => setCustStatusFilter(item.id as any)}
                      className={cn(
                        "p-2 rounded-xl border text-xs font-semibold text-center transition-all",
                        custStatusFilter === item.id
                          ? "border-brand-500 bg-brand-50 dark:bg-brand-500/10 text-brand-600 dark:text-brand-400"
                          : "border-gray-200 dark:border-gray-800 bg-gray-50/75 dark:bg-gray-900/50 text-gray-500 dark:text-gray-400 hover:text-white"
                      )}
                    >
                      {item.label}
                    </button>
                  ))}
                </div>
              </div>

              {/* Action Buttons */}
              <div className="pt-3 border-t border-gray-200 dark:border-gray-800 flex items-center justify-between gap-2">
                <button
                  type="button"
                  onClick={resetAllFilters}
                  className="rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-100 dark:bg-gray-800 hover:bg-[#1C232E] text-gray-700 dark:text-gray-300 px-3.5 py-2 text-xs font-semibold"
                >
                  Reset Filter
                </button>

                <button
                  type="button"
                  onClick={() => setFilterModalOpen(false)}
                  className="rounded-xl bg-brand-500 hover:bg-[#0084E3] text-white px-5 py-2 text-xs font-bold"
                >
                  Terapkan Filter
                </button>
              </div>
            </div>
          </DialogContent>
        </Dialog>

        {/* ── MODAL DETAIL TELEMETRI & JARINGAN PELANGGAN ── */}
        <Dialog open={customerModalOpen} onOpenChange={setCustomerModalOpen}>
          <DialogContent className="w-[calc(100vw-24px)] sm:max-w-2xl max-h-[90dvh] overflow-y-auto overflow-x-hidden p-4 sm:p-6 space-y-4 rounded-2xl sm:rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-white/[0.03] text-white">
            <DialogHeader className="pb-2 border-b border-gray-200 dark:border-gray-800 pr-8">
              <DialogTitle className="text-sm sm:text-base font-bold flex flex-col sm:flex-row sm:items-center justify-between gap-1.5 text-white">
                <div className="flex items-center gap-2 min-w-0">
                  <Activity className="h-4.5 w-4.5 text-brand-600 dark:text-brand-400 shrink-0" />
                  <span className="truncate">Telemetri &amp; Status Jaringan</span>
                </div>
                {selectedCustomer && (
                  <span
                    className={cn(
                      "text-[10px] px-2.5 py-0.5 rounded-full font-bold uppercase tracking-wider flex items-center gap-1.5 self-start sm:self-auto shrink-0",
                      selectedCustomer.is_online
                        ? "bg-emerald-500/20 text-emerald-300 border border-emerald-500/40"
                        : "bg-rose-500/20 text-rose-300 border border-rose-500/40"
                    )}
                  >
                    <span className={cn("h-1.5 w-1.5 rounded-full", selectedCustomer.is_online ? "bg-emerald-400" : "bg-rose-400")} />
                    <span>{selectedCustomer.is_online ? "Online (Sesi Aktif)" : "Offline (Terputus)"}</span>
                  </span>
                )}
              </DialogTitle>
            </DialogHeader>

            {selectedCustomer && (
              <div className="space-y-4 text-xs">
                {/* Network Profile Header */}
                <div className="rounded-2xl border border-gray-200 dark:border-gray-800 bg-gray-50/75 dark:bg-gray-900/50 p-3.5 flex flex-col sm:flex-row sm:items-center justify-between gap-3 overflow-hidden">
                  <div className="flex items-center gap-3 min-w-0 flex-1">
                    <div className={cn(
                      "flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl border font-bold text-sm",
                      selectedCustomer.is_online
                        ? "bg-[#0A261E] border-emerald-500/40 text-emerald-400"
                        : "bg-[#251016] border-rose-500/40 text-rose-400"
                    )}>
                      {selectedCustomer.name.slice(0, 2).toUpperCase()}
                    </div>
                    <div className="min-w-0 flex-1">
                      <h4 className="font-bold text-white text-sm flex items-center gap-2 flex-wrap">
                        <span className="truncate">{selectedCustomer.name}</span>
                        {selectedCustomer.uptime && selectedCustomer.uptime !== "-" && (
                          <span className="text-[10px] font-mono text-emerald-400 font-normal flex items-center gap-1 bg-emerald-950/60 border border-emerald-500/30 px-1.5 py-0.5 rounded-md shrink-0">
                            <Clock className="h-3 w-3" />
                            <span>Durasi Aktif: {selectedCustomer.uptime}</span>
                          </span>
                        )}
                      </h4>
                      <p className="text-gray-500 dark:text-gray-400 font-mono text-[11px] truncate mt-0.5">
                        PPPoE: <strong className="text-brand-600 dark:text-brand-400">{selectedCustomer.pppoe_username || "-"}</strong> · ID #{selectedCustomer.code || selectedCustomer.id}
                      </p>
                    </div>
                  </div>

                  <div className="text-left sm:text-right shrink-0 border-t sm:border-t-0 border-gray-200 dark:border-gray-800 pt-2 sm:pt-0">
                    <span className="text-xs font-bold text-white block truncate">
                      {selectedCustomer.profile || selectedCustomer.package_name || "Default Profile"}
                    </span>
                    <span className="text-[10px] font-mono text-brand-600 dark:text-brand-400 block">
                      {selectedCustomer.connection_type || "PPPOE"}
                    </span>
                  </div>
                </div>

                {/* Grid 1: Live MikroTik Network Specs */}
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                  <div className="rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-50/75 dark:bg-gray-900/50 p-2.5 space-y-1 overflow-hidden">
                    <span className="text-[10px] text-gray-500 dark:text-gray-400 font-semibold flex items-center gap-1">
                      <Cpu className="h-3 w-3 text-emerald-400" /> IP Address (Aktif)
                    </span>
                    <p className="font-mono text-xs font-bold text-emerald-300 truncate">
                      {selectedCustomer.ip_address || "Dynamic IP Pool"}
                    </p>
                  </div>

                  <div className="rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-50/75 dark:bg-gray-900/50 p-2.5 space-y-1 overflow-hidden">
                    <span className="text-[10px] text-gray-500 dark:text-gray-400 font-semibold flex items-center gap-1">
                      <Network className="h-3 w-3 text-brand-600 dark:text-brand-400" /> MAC Address / Caller-ID
                    </span>
                    <p className="font-mono text-xs font-bold text-gray-800 dark:text-gray-200 truncate">
                      {selectedCustomer.mac_address || "-"}
                    </p>
                  </div>

                  <div className="rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-50/75 dark:bg-gray-900/50 p-2.5 space-y-1 overflow-hidden">
                    <span className="text-[10px] text-gray-500 dark:text-gray-400 font-semibold flex items-center gap-1">
                      <Server className="h-3 w-3 text-purple-400" /> Router Gateway
                    </span>
                    <p className="font-semibold text-white truncate text-xs">
                      {selectedCustomer.router_name || "MikroTik Gateway"} {selectedCustomer.router_ip && `(${selectedCustomer.router_ip})`}
                    </p>
                  </div>

                  <div className="rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-50/75 dark:bg-gray-900/50 p-2.5 space-y-1 overflow-hidden">
                    <span className="text-[10px] text-gray-500 dark:text-gray-400 font-semibold flex items-center gap-1">
                      <Network className="h-3 w-3 text-amber-400" /> Titik Distribusi (ODP/HTB)
                    </span>
                    <p className="font-semibold text-white truncate text-xs">
                      {selectedCustomer.odp_name ? `${selectedCustomer.odp_name}${selectedCustomer.odp_port ? ` (Port ${selectedCustomer.odp_port})` : ""}` : "Belum terpasang"}
                    </p>
                  </div>
                </div>

                {/* Grid 2: Top Bandwidth & Live Traffic */}
                <div className="rounded-2xl border border-gray-200 dark:border-gray-800 bg-gray-50/75 dark:bg-gray-900/50 p-3 space-y-2 overflow-hidden">
                  <div className="flex items-center justify-between text-xs">
                    <span className="font-bold text-gray-800 dark:text-gray-200 flex items-center gap-1.5">
                      <HardDrive className="h-3.5 w-3.5 text-brand-600 dark:text-brand-400" />
                      <span>Pemakaian Data Sesi &amp; Trafik</span>
                    </span>
                    <span className="font-mono text-[11px] font-bold text-white bg-gray-100 dark:bg-gray-800 border border-gray-200 dark:border-gray-800 px-2 py-0.5 rounded-md">
                      Total: {selectedCustomer.total_bytes || "0 B"}
                    </span>
                  </div>

                  <div className="grid grid-cols-2 gap-2 text-xs pt-1">
                    <div className="rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-white/[0.03] p-2 flex items-center justify-between overflow-hidden">
                      <span className="text-gray-500 dark:text-gray-400 flex items-center gap-1 shrink-0">
                        <ArrowDown className="h-3.5 w-3.5 text-emerald-400" />
                        <span>Download (Rx)</span>
                      </span>
                      <span className="font-mono font-bold text-emerald-300 truncate ml-2">
                        {selectedCustomer.rx_bytes || "0 B"}
                      </span>
                    </div>

                    <div className="rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-white/[0.03] p-2 flex items-center justify-between overflow-hidden">
                      <span className="text-gray-500 dark:text-gray-400 flex items-center gap-1 shrink-0">
                        <ArrowUp className="h-3.5 w-3.5 text-brand-600 dark:text-brand-400" />
                        <span>Upload (Tx)</span>
                      </span>
                      <span className="font-mono font-bold text-brand-600 dark:text-brand-400 truncate ml-2">
                        {selectedCustomer.tx_bytes || "0 B"}
                      </span>
                    </div>
                  </div>
                </div>

                {/* Grid 3: Optical Power & FTTH Infrastructure */}
                <div className="grid grid-cols-3 gap-2 text-xs">
                  <div className="rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-50/75 dark:bg-gray-900/50 p-2.5 overflow-hidden">
                    <span className="text-[10px] text-gray-500 dark:text-gray-400 font-semibold block mb-0.5 truncate">
                      Redaman RX (Optik)
                    </span>
                    <p className={cn(
                      "font-mono text-sm font-bold truncate",
                      selectedCustomer.rx_power ? "text-brand-600 dark:text-brand-400" : "text-gray-400 dark:text-gray-500"
                    )}>
                      {selectedCustomer.rx_power ? `${selectedCustomer.rx_power} dBm` : "-"}
                    </p>
                  </div>

                  <div className="rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-50/75 dark:bg-gray-900/50 p-2.5 overflow-hidden">
                    <span className="text-[10px] text-gray-500 dark:text-gray-400 font-semibold block mb-0.5 truncate">
                      Daya Pancar TX
                    </span>
                    <p className={cn(
                      "font-mono text-sm font-bold truncate",
                      selectedCustomer.tx_power ? "text-emerald-400" : "text-gray-400 dark:text-gray-500"
                    )}>
                      {selectedCustomer.tx_power ? `${selectedCustomer.tx_power} dBm` : "-"}
                    </p>
                  </div>

                  <div className="rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-50/75 dark:bg-gray-900/50 p-2.5 overflow-hidden">
                    <span className="text-[10px] text-gray-500 dark:text-gray-400 font-semibold block mb-0.5 truncate">
                      Serial ONU
                    </span>
                    <p className="font-mono text-xs font-bold text-gray-800 dark:text-gray-200 truncate">
                      {selectedCustomer.onu_serial || "-"}
                    </p>
                  </div>
                </div>

                {/* Real-time Telemetry Section */}
                <div className="rounded-2xl border border-gray-200 dark:border-gray-800 bg-gray-50/75 dark:bg-gray-900/50 p-3.5 space-y-3 overflow-hidden">
                  <div className="flex items-center justify-between">
                    <span className="text-xs font-bold text-white flex items-center gap-1.5">
                      <Activity className="h-4 w-4 text-brand-600 dark:text-brand-400" />
                      <span>Uji Latensi &amp; Jitter Telemetri</span>
                    </span>

                    <button
                      type="button"
                      disabled={isPinging}
                      onClick={() => runPingTest(selectedCustomer)}
                      className="flex items-center gap-1.5 rounded-xl bg-brand-500 hover:bg-[#0084E3] text-white px-3 py-1.5 text-xs font-bold shadow-sm disabled:opacity-50"
                    >
                      <Activity className="h-3 w-3" />
                      <span>{isPinging ? "Menguji..." : "Mulai Uji Ping"}</span>
                    </button>
                  </div>

                  {pingResult ? (
                    <div className="space-y-2.5 pt-1 animate-in fade-in duration-200 overflow-hidden">
                      <div className="grid grid-cols-3 gap-2 text-center">
                        <div className="rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-white/[0.03] p-2">
                          <span className="text-[9.5px] text-gray-500 dark:text-gray-400 block mb-0.5 truncate">Rata Latensi</span>
                          <p className={cn(
                            "font-mono text-base font-bold",
                            pingResult.status === "RTO" ? "text-rose-400" : pingResult.avg_ms < 30 ? "text-emerald-400" : "text-amber-400"
                          )}>
                            {pingResult.status === "RTO" ? "RTO" : `${pingResult.avg_ms} ms`}
                          </p>
                        </div>

                        <div className="rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-white/[0.03] p-2">
                          <span className="text-[9.5px] text-gray-500 dark:text-gray-400 block mb-0.5 truncate">Jitter Delay</span>
                          <p className={cn(
                            "font-mono text-base font-bold",
                            pingResult.status === "RTO" ? "text-gray-400 dark:text-gray-500" : pingResult.jitter_ms <= 3 ? "text-emerald-400" : "text-amber-400"
                          )}>
                            {pingResult.status === "RTO" ? "0 ms" : `${pingResult.jitter_ms} ms`}
                          </p>
                        </div>

                        <div className="rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-white/[0.03] p-2">
                          <span className="text-[9.5px] text-gray-500 dark:text-gray-400 block mb-0.5 truncate">Packet Loss</span>
                          <p className={cn(
                            "font-mono text-base font-bold",
                            pingResult.loss_percent === 0 ? "text-emerald-400" : "text-rose-400"
                          )}>
                            {pingResult.loss_percent}%
                          </p>
                        </div>
                      </div>

                      {/* Waveform Bar */}
                      {pingResult.samples && pingResult.samples.length > 0 && (
                        <div className="rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-white/[0.03] p-2.5 space-y-1 overflow-hidden">
                          <span className="text-[9.5px] font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider block">
                            Gelombang 5-Paket ICMP
                          </span>
                          <div className="flex items-end gap-2 h-10 pt-1">
                            {pingResult.samples.map((sample, idx) => {
                              const heightPct = Math.min(100, Math.max(25, (sample / (pingResult.max_ms || 1)) * 100))
                              return (
                                <div key={idx} className="flex-1 flex flex-col items-center gap-0.5 h-full justify-end">
                                  <span className="text-[8px] font-mono text-gray-500 dark:text-gray-400">{sample}ms</span>
                                  <div
                                    className="w-full rounded-sm bg-gradient-to-t from-[#0073C6] to-[#00C2FF]"
                                    style={{ height: `${heightPct}%` }}
                                  />
                                </div>
                              )
                            })}
                          </div>
                        </div>
                      )}

                      <div className="rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-white/[0.03] p-2.5 space-y-1.5 text-[11px]">
                        <div className="flex items-start justify-between gap-2">
                          <span className="font-semibold text-gray-700 dark:text-gray-300 leading-snug break-words">
                            Kualitas: <span className={cn(
                              pingResult.loss_percent === 0 ? "text-emerald-400" : (pingResult.quality || "").includes("Aktif") ? "text-amber-400" : "text-rose-400"
                            )}>{pingResult.quality}</span>
                          </span>
                          <span className="text-[10px] font-mono text-gray-500 dark:text-gray-400 shrink-0">{pingResult.timestamp}</span>
                        </div>
                        {pingResult.source && (
                          <div className="text-[10px] text-gray-500 dark:text-gray-400 flex items-center gap-1 border-t border-gray-200 dark:border-gray-800/60 pt-1">
                            <span className="text-gray-400 dark:text-gray-500">Sumber:</span>
                            <span className="text-brand-600 dark:text-brand-400 font-medium">{pingResult.source}</span>
                          </div>
                        )}
                      </div>
                    </div>
                  ) : isPinging ? (
                    <div className="p-4 rounded-xl border border-dashed border-gray-200 dark:border-gray-800 bg-white dark:bg-white/[0.03] text-center space-y-1">
                      <RefreshCw className="h-5 w-5 mx-auto text-brand-600 dark:text-brand-400 animate-spin" />
                      <p className="text-xs font-semibold text-white">Mengukur Latensi &amp; Jitter...</p>
                    </div>
                  ) : (
                    <p className="text-[11px] text-gray-500 dark:text-gray-400 text-center py-2">
                      Klik "Mulai Uji Ping" untuk mengukur latensi dan stabilitas jitter secara langsung ke IP host.
                    </p>
                  )}
                </div>

                {/* Bottom Actions */}
                <div className="pt-2 flex flex-col gap-3 border-t border-gray-200 dark:border-gray-800">
                  <div className="flex flex-wrap items-center gap-2">
                    {isAdmin && selectedCustomer.odp_id && (
                      <button
                        type="button"
                        onClick={() => {
                          const foundConn = connectionsState.find((c) => c.customer_id === selectedCustomer.id)
                          if (foundConn) {
                            setCustomerModalOpen(false)
                            startEditingCableRoute(foundConn)
                          } else {
                            showToast("Garis kabel ODP untuk pelanggan ini belum terhubung.")
                          }
                        }}
                        className="flex h-9 items-center gap-1.5 px-3 rounded-xl border border-brand-500/40 bg-brand-50 dark:bg-brand-500/10 text-brand-600 dark:text-brand-400 hover:bg-brand-500 hover:text-white text-xs font-semibold"
                      >
                        <Route className="h-3.5 w-3.5" />
                        <span>Sesuaikan Jalur Kabel</span>
                      </button>
                    )}

                    {selectedCustomer.phone && (
                      <a
                        href={`https://wa.me/${selectedCustomer.phone.replace(/[^0-9]/g, "").replace(/^0/, "62")}?text=${encodeURIComponent(
                          `Halo Bapak/Ibu ${selectedCustomer.name}, ini dari Tim Teknis & Administrasi ${tenantName || companyName}.`
                        )}`}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="flex h-9 items-center gap-1.5 px-3 rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-100 dark:bg-gray-800 text-[#10B981] hover:bg-[#0E281E] hover:border-[#10B981]/40 text-xs font-semibold"
                      >
                        <MessageCircle className="h-3.5 w-3.5" />
                        <span>WhatsApp</span>
                      </a>
                    )}

                    {isAdmin && (
                      <>
                        <Link
                          href={`/admin/billing/customers?highlight=${selectedCustomer.id}`}
                          className="flex h-9 items-center gap-1.5 px-3 rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:bg-gray-700 hover:text-white text-xs font-semibold"
                        >
                          <ExternalLink className="h-3.5 w-3.5" />
                          <span>Buka di Billing</span>
                        </Link>

                        <button
                          type="button"
                          onClick={() => {
                            setCustomerModalOpen(false)
                            handleSelectCustomerForSync(selectedCustomer)
                          }}
                          className="flex h-9 items-center gap-1.5 px-3 rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:bg-gray-700 hover:text-white text-xs font-semibold"
                          title="Edit Lokasi & Distribusi ODP"
                        >
                          <Pencil className="h-3.5 w-3.5" />
                          <span>Edit Lokasi ODP</span>
                        </button>
                      </>
                    )}
                  </div>

                  {/* Hapus Titik dari Peta (Admin Only) / Footer Tutup */}
                  <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2.5 pt-2.5 border-t border-gray-200 dark:border-gray-800">
                    {isAdmin ? (
                      <>
                        <button
                          type="button"
                          onClick={() => handleDeleteCustomerMarker(selectedCustomer.id, selectedCustomer.name)}
                          className="flex h-9 items-center justify-center gap-1.5 px-3 rounded-xl border border-rose-500/30 bg-rose-500/10 text-rose-400 hover:bg-rose-500/20 hover:text-rose-300 text-xs font-semibold w-full sm:w-auto"
                          title="Hapus titik koordinat pelanggan dari peta. Data billing & tagihan tetap aman."
                        >
                          <MapPin className="h-3.5 w-3.5 shrink-0" />
                          <span>Hapus Titik dari Peta</span>
                        </button>
                        <p className="text-[10px] text-gray-400 dark:text-gray-500 text-center sm:text-left flex-1">Data billing &amp; tagihan tetap aman.</p>
                      </>
                    ) : (
                      <p className="text-[10px] text-gray-400 dark:text-gray-500 text-center sm:text-left flex-1 italic">
                        Mode Petugas ({role === "technician" ? "Teknisi" : "Kolektor"}) · Hanya detail &amp; uji ping yang diizinkan.
                      </p>
                    )}
                    <button
                      type="button"
                      onClick={() => setCustomerModalOpen(false)}
                      className="rounded-xl bg-gray-100 dark:bg-gray-800 border border-gray-200 dark:border-gray-800 text-gray-700 dark:text-gray-300 hover:bg-[#1E2636] px-4 py-2 text-xs font-semibold w-full sm:w-auto text-center"
                    >
                      Tutup
                    </button>
                  </div>
                </div>
              </div>
            )}
          </DialogContent>
        </Dialog>

        {/* ── MODAL AKSI TITIK KOORDINAT (KLIK KANAN MAP) ── */}
        <Dialog open={isAdmin && quickActionModalOpen} onOpenChange={setQuickActionModalOpen}>
          <DialogContent className="w-[calc(100vw-24px)] sm:max-w-md max-h-[92vh] overflow-y-auto custom-scrollbar p-5 sm:p-6 space-y-4 rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 text-gray-900 dark:text-white shadow-2xl">
            <DialogHeader className="pb-2 border-b border-gray-200 dark:border-gray-800 pr-8">
              <DialogTitle className="text-sm sm:text-base font-bold flex flex-col sm:flex-row sm:items-center justify-between gap-1.5 text-white">
                <span className="flex items-center gap-2 min-w-0">
                  <Navigation className="h-4 w-4 text-brand-600 dark:text-brand-400 shrink-0" />
                  <span className="truncate">Aksi Titik Lokasi Peta</span>
                </span>
                {clickedCoord && (
                  <span className="font-mono text-[10px] sm:text-[11px] font-normal text-gray-500 dark:text-gray-400 bg-gray-50/75 dark:bg-gray-900/50 px-2 py-0.5 rounded-lg border border-gray-200 dark:border-gray-800 self-start sm:self-auto shrink-0 break-all">
                    {clickedCoord.lat}, {clickedCoord.lng}
                  </span>
                )}
              </DialogTitle>
            </DialogHeader>

            <div className="space-y-3 pt-1">
              <p className="text-xs text-gray-700 dark:text-gray-300">
                Pilih tindakan untuk titik koordinat yang dipilih:
              </p>

              <div className="grid grid-cols-1 gap-2.5">
                {/* Option 1: Tambah Titik Distribusi Baru */}
                <button
                  type="button"
                  onClick={() => {
                    setQuickActionModalOpen(false)
                    openAdd(clickedCoord?.lat, clickedCoord?.lng)
                  }}
                  className="flex items-center gap-3.5 p-3.5 rounded-2xl border border-gray-200 dark:border-gray-800 bg-gray-50/75 dark:bg-gray-900/50 hover:border-brand-500 hover:bg-brand-50 dark:bg-brand-500/10 transition-all text-left group"
                >
                  <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-white dark:bg-white/[0.03] border border-amber-500/30 text-amber-400 group-hover:scale-105 transition-transform">
                    {networkSystem === "lan" ? (
                      <Server className="h-5 w-5 text-brand-600 dark:text-brand-400" />
                    ) : networkSystem === "pon" ? (
                      <Network className="h-5 w-5 text-amber-400" />
                    ) : (
                      <Layers className="h-5 w-5 text-purple-400" />
                    )}
                  </div>
                  <div className="min-w-0 flex-1">
                    <h4 className="text-xs font-bold text-white group-hover:text-brand-600 dark:text-brand-400">
                      {networkSystem === "lan"
                        ? "Tambah Titik Media Converter LAN (HTB / Switch)"
                        : networkSystem === "pon"
                        ? "Tambah Titik Distribusi FTTH (ODC / ODP)"
                        : "Tambah Titik Distribusi (ODC / ODP / HTB / Switch)"}
                    </h4>
                    <p className="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5">
                      {networkSystem === "lan"
                        ? "Pasang media converter HTB atau switch hub baru di koordinat ini."
                        : networkSystem === "pon"
                        ? "Pasang box splitter ODP atau ODC feeder optik baru di koordinat ini."
                        : "Pasang box ODC feeder, splitter ODP, atau converter HTB baru di koordinat ini."}
                    </p>
                  </div>
                </button>

                {/* Option 2: Pindah / Pasang Titik Router / Server NOC */}
                <button
                  type="button"
                  onClick={() => {
                    setQuickActionModalOpen(false)
                    if (routersState.length > 0) {
                      setSelectedRouterToMove(routersState[0].id)
                    }
                    setSetRouterLocationModalOpen(true)
                  }}
                  className="flex items-center gap-3.5 p-3.5 rounded-2xl border border-gray-200 dark:border-gray-800 bg-gray-50/75 dark:bg-gray-900/50 hover:border-purple-500/60 hover:bg-[#1A0F2E] transition-all text-left group"
                >
                  <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-white dark:bg-white/[0.03] border border-purple-500/30 text-purple-400 group-hover:scale-105 transition-transform">
                    <Server className="h-5 w-5" />
                  </div>
                  <div className="min-w-0 flex-1">
                    <h4 className="text-xs font-bold text-white group-hover:text-purple-300">
                      Pindah / Pasang Lokasi Router &amp; Server NOC
                    </h4>
                    <p className="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5">
                      Setel koordinat pusat server MikroTik NOC ke titik ini sebagai titik awal jaringan.
                    </p>
                  </div>
                </button>

                {/* Option 3: Sinkronkan Lokasi Pelanggan */}
                <button
                  type="button"
                  onClick={() => {
                    setQuickActionModalOpen(false)
                    setCustomerSyncSearch("")
                    setCustomerSyncModalOpen(true)
                  }}
                  className="flex items-center gap-3.5 p-3.5 rounded-2xl border border-gray-200 dark:border-gray-800 bg-gray-50/75 dark:bg-gray-900/50 hover:border-emerald-500/50 hover:bg-[#0A261E]/70 transition-all text-left group"
                >
                  <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-white dark:bg-white/[0.03] border border-emerald-500/30 text-emerald-400 group-hover:scale-105 transition-transform">
                    <Home className="h-5 w-5" />
                  </div>
                  <div className="min-w-0 flex-1">
                    <h4 className="text-xs font-bold text-white group-hover:text-emerald-300">
                      Sinkronkan / Pasang Lokasi Pelanggan
                    </h4>
                    <p className="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5">
                      Cari nama pelanggan dan setel rumah serta port ODP ke titik koordinat ini.
                    </p>
                  </div>
                </button>
              </div>
            </div>
          </DialogContent>
        </Dialog>

        {/* ── MODAL PINDAH / PASANG LOKASI ROUTER NOC ── */}
        <Dialog open={isAdmin && setRouterLocationModalOpen} onOpenChange={setSetRouterLocationModalOpen}>
          <DialogContent className="w-[calc(100vw-24px)] sm:max-w-md max-h-[90dvh] overflow-y-auto overflow-x-hidden p-4 sm:p-5 space-y-4 rounded-2xl sm:rounded-2xl border border-purple-500/30 bg-white dark:bg-white/[0.03] text-white">
            <DialogHeader className="pb-2 border-b border-gray-200 dark:border-gray-800 pr-8">
              <DialogTitle className="text-sm sm:text-base font-bold flex flex-col sm:flex-row sm:items-center justify-between gap-1.5 text-white">
                <span className="flex items-center gap-2 min-w-0">
                  <Server className="h-4 w-4 text-purple-400 shrink-0" />
                  <span className="truncate">Pasang Lokasi Router &amp; Server NOC</span>
                </span>
                {clickedCoord && (
                  <span className="font-mono text-[10px] sm:text-[11px] text-purple-300 bg-[#1A0F2E] px-2 py-0.5 rounded-lg border border-purple-500/30 self-start sm:self-auto shrink-0 break-all">
                    {clickedCoord.lat}, {clickedCoord.lng}
                  </span>
                )}
              </DialogTitle>
            </DialogHeader>

            <form
              onSubmit={async (e) => {
                e.preventDefault()
                const targetRouterId = selectedRouterToMove || routersState[0]?.id
                if (!targetRouterId || !clickedCoord) {
                  showToast("Pilih router dan klik titik di peta terlebih dahulu.")
                  return
                }
                try {
                  const res = await axios.post("/admin/map/update-coords", {
                    type: "router",
                    id: targetRouterId,
                    lat: clickedCoord.lat,
                    lng: clickedCoord.lng,
                  })

                  setRoutersState((prev) =>
                    prev.map((r) =>
                      r.id === targetRouterId
                        ? { ...r, lat: clickedCoord.lat, lng: clickedCoord.lng }
                        : r
                    )
                  )

                  const rName = routersState.find((r) => r.id === targetRouterId)?.name || "Router NOC"
                  showToast(res.data?.message || `Lokasi Router NOC "${rName}" berhasil dipasang di [${clickedCoord.lat}, ${clickedCoord.lng}]`)
                  setSetRouterLocationModalOpen(false)
                  panToLocation(clickedCoord.lat, clickedCoord.lng)
                } catch (err: any) {
                  console.error(err)
                  const errMsg = err?.response?.data?.message || "Gagal menyimpan lokasi router."
                  showToast(errMsg)
                }
              }}
              className="space-y-3.5 text-xs"
            >
              <div className="space-y-1">
                <label className="font-semibold text-gray-700 dark:text-gray-300">Pilih Router / Server Gateway</label>
                <select
                  value={selectedRouterToMove || ""}
                  onChange={(e) => setSelectedRouterToMove(Number(e.target.value))}
                  className="h-10 w-full rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-50/75 dark:bg-gray-900/50 px-3 text-xs text-white focus-visible:outline-none focus-visible:border-purple-500"
                >
                  {routersState.map((r) => (
                    <option key={r.id} value={r.id} className="bg-white dark:bg-white/[0.03]">
                      {r.name} — {r.host}:{r.port} ({r.location || "NOC Core"})
                    </option>
                  ))}
                </select>
              </div>

              <p className="text-[11px] text-gray-500 dark:text-gray-400">
                Pusat server NOC ini akan dijadikan titik acuan utama (default center view) pada peta dan induk kabel backbone jaringan fiber optik / LAN Anda.
              </p>

              <div className="pt-2 flex flex-col-reverse sm:flex-row sm:items-center sm:justify-end gap-2 border-t border-gray-200 dark:border-gray-800">
                <button
                  type="button"
                  onClick={() => setSetRouterLocationModalOpen(false)}
                  className="w-full sm:w-auto rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:bg-gray-700 px-4 py-2 text-xs font-semibold text-center"
                >
                  Batal
                </button>
                <button
                  type="submit"
                  className="w-full sm:w-auto rounded-xl bg-purple-600 hover:bg-purple-500 text-white px-4 py-2 text-xs font-bold flex items-center justify-center gap-1.5"
                >
                  <Check className="h-3.5 w-3.5" />
                  <span>Pasang Posisi Server NOC</span>
                </button>
              </div>
            </form>
          </DialogContent>
        </Dialog>

        {/* ── MODAL CARI & SINKRONKAN LOKASI PELANGGAN ── */}
        <Dialog
          open={isAdmin && customerSyncModalOpen}
          onOpenChange={(open) => {
            if (!isAdmin) {
              setCustomerSyncModalOpen(false)
              setSelectedSyncCustomer(null)
              return
            }
            setCustomerSyncModalOpen(open)
            if (!open) setSelectedSyncCustomer(null)
          }}
        >
          <DialogContent className="w-[calc(100vw-24px)] sm:max-w-lg max-h-[92vh] overflow-y-auto custom-scrollbar p-5 sm:p-6 space-y-4 rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 text-gray-900 dark:text-white shadow-2xl">
            <DialogHeader className="pb-2 border-b border-gray-200 dark:border-gray-800 pr-8">
              <DialogTitle className="text-sm sm:text-base font-bold text-white">
                <div className="flex flex-col gap-1.5">
                  <span className="flex items-center gap-2 min-w-0">
                    <Home className="h-4 w-4 shrink-0 text-emerald-400" />
                    <span className="leading-snug truncate">{selectedSyncCustomer ? "Atur Lokasi & ODP Pelanggan" : "Pilih Pelanggan untuk Titik Ini"}</span>
                  </span>
                  {clickedCoord ? (
                    <span className="font-mono text-[10px] sm:text-[11px] font-normal text-emerald-400 bg-[#0A261E] px-2 py-0.5 rounded-lg border border-emerald-500/30 self-start break-all">
                      {clickedCoord.lat}, {clickedCoord.lng}
                    </span>
                  ) : selectedSyncCustomer && (selectedSyncCustomer.lat || selectedSyncCustomer.lng) ? (
                    <span className="font-mono text-[10px] sm:text-[11px] font-normal text-emerald-400 bg-[#0A261E] px-2 py-0.5 rounded-lg border border-emerald-500/30 self-start break-all">
                      {selectedSyncCustomer.lat}, {selectedSyncCustomer.lng}
                    </span>
                  ) : null}
                </div>
              </DialogTitle>
            </DialogHeader>

            {!selectedSyncCustomer ? (
              <div className="space-y-3 text-xs">
                {/* Search Bar */}
                <div className="relative">
                  <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-500 dark:text-gray-400" />
                  <input
                    type="text"
                    value={customerSyncSearch}
                    onChange={(e) => setCustomerSyncSearch(e.target.value)}
                    placeholder="Cari nama pelanggan, username PPPoE, ID, alamat..."
                    autoFocus
                    className="h-10 w-full rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-50/75 dark:bg-gray-900/50 pl-9 pr-3 text-xs text-white placeholder:text-gray-400 dark:text-gray-500 focus-visible:outline-none focus-visible:border-emerald-500"
                  />
                </div>

                {/* Customer List */}
                <div className="max-h-64 sm:max-h-72 overflow-y-auto space-y-1.5 pr-1 custom-scrollbar">
                  {filteredSyncCustomers.length === 0 ? (
                    <div className="p-8 text-center rounded-2xl border border-dashed border-gray-200 dark:border-gray-800 bg-gray-50/75 dark:bg-gray-900/50 text-gray-500 dark:text-gray-400">
                      <Users className="h-6 w-6 mx-auto mb-1 opacity-50" />
                      <p className="text-xs font-semibold">Tidak ada pelanggan ditemukan</p>
                      <p className="text-[11px]">Coba cari dengan kata kunci lain.</p>
                    </div>
                  ) : (
                    filteredSyncCustomers.map((c: SimpleCustomer) => {
                      const hasCoord = c.lat && c.lng && (c.lat !== 0 || c.lng !== 0)
                      return (
                        <div
                          key={c.id}
                          onClick={() => handleSelectCustomerForSync(c)}
                          className="flex items-center justify-between p-3 rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-50/75 dark:bg-gray-900/50 hover:border-emerald-500/60 hover:bg-[#0A261E]/50 transition-all cursor-pointer select-none group min-w-0"
                        >
                          <div className="min-w-0 flex-1 pr-2">
                            <div className="flex items-center gap-2 flex-wrap">
                              <strong className="text-xs font-bold text-white group-hover:text-emerald-300 truncate">{c.name}</strong>
                              <span className="text-[10px] font-mono text-gray-500 dark:text-gray-400 shrink-0">#{c.code || c.id}</span>
                              {c.router_name && (
                                <span className="text-[9.5px] px-1.5 py-0.5 rounded bg-purple-500/20 text-purple-300 border border-purple-500/30 shrink-0 font-medium flex items-center gap-1">
                                  <Server className="h-2.5 w-2.5" />
                                  <span>{c.router_name}</span>
                                </span>
                              )}
                            </div>
                            <div className="text-[11px] text-gray-500 dark:text-gray-400 flex flex-wrap items-center gap-x-2 gap-y-0.5 mt-0.5 truncate">
                              <span className="truncate">PPPoE: <strong className="text-brand-600 dark:text-brand-400 font-mono">{c.pppoe_username || "-"}</strong></span>
                              {c.router_name && <span className="truncate text-gray-500 dark:text-gray-400">· Router: <strong className="text-purple-300">{c.router_name}</strong></span>}
                              {c.odp_name && <span className="truncate">· ODP: <strong className="text-amber-400">{c.odp_name}</strong></span>}
                            </div>
                            {c.address && (
                              <p className="text-[10px] text-gray-400 dark:text-gray-500 truncate mt-0.5">{c.address}</p>
                            )}
                          </div>

                          <div className="shrink-0 flex items-center gap-2">
                            <span className={cn(
                              "text-[9.5px] font-bold px-2 py-0.5 rounded-md border whitespace-nowrap",
                              hasCoord
                                ? "bg-slate-800 text-gray-500 dark:text-gray-400 border-slate-700"
                                : "bg-emerald-500/15 text-emerald-300 border-emerald-500/30"
                            )}>
                              {hasCoord ? "Ubah Lokasi" : "Pasang Baru"}
                            </span>
                          </div>
                        </div>
                      )
                    })
                  )}
                </div>
              </div>
            ) : (
              <form onSubmit={handleSaveCustomerSyncForm} className="space-y-3.5 text-xs">
                <div className="p-3 rounded-2xl border border-emerald-500/30 bg-[#0A261E]/50 flex items-center justify-between gap-2.5">
                  <div className="min-w-0 flex-1">
                    <div className="flex items-center gap-2 flex-wrap">
                      <h4 className="font-bold text-white text-xs truncate">{selectedSyncCustomer.name}</h4>
                      {selectedSyncCustomer.router_name && (
                        <span className="text-[9.5px] px-1.5 py-0.5 rounded bg-purple-500/20 text-purple-300 border border-purple-500/30 shrink-0 font-medium flex items-center gap-1">
                          <Server className="h-2.5 w-2.5" />
                          <span>{selectedSyncCustomer.router_name}</span>
                        </span>
                      )}
                    </div>
                    <p className="text-[11px] text-gray-500 dark:text-gray-400 font-mono truncate mt-0.5">
                      PPPoE: <strong className="text-brand-600 dark:text-brand-400">{selectedSyncCustomer.pppoe_username || "-"}</strong>
                      {selectedSyncCustomer.router_name && <> · Router: <strong className="text-purple-300">{selectedSyncCustomer.router_name}</strong></>}
                      {selectedSyncCustomer.odp_name && <> · ODP: <strong className="text-amber-400">{selectedSyncCustomer.odp_name}</strong></>}
                      {" "}· ID #{selectedSyncCustomer.code || selectedSyncCustomer.id}
                    </p>
                  </div>
                  <button
                    type="button"
                    onClick={() => setSelectedSyncCustomer(null)}
                    className="text-xs text-emerald-400 hover:underline font-semibold shrink-0 whitespace-nowrap"
                  >
                    Ganti Pelanggan
                  </button>
                </div>

                {/* Koordinat GPS */}
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                  <div className="space-y-1">
                    <label className="text-[11px] font-semibold text-gray-700 dark:text-gray-300">Latitude (Garis Lintang)</label>
                    <input
                      type="text"
                      value={syncLat}
                      onChange={(e) => setSyncLat(e.target.value)}
                      placeholder="-6.200000"
                      required
                      className="h-9 w-full rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-50/75 dark:bg-gray-900/50 px-3 font-mono text-xs text-white placeholder:text-slate-600 focus-visible:outline-none focus-visible:border-emerald-500"
                    />
                  </div>
                  <div className="space-y-1">
                    <label className="text-[11px] font-semibold text-gray-700 dark:text-gray-300">Longitude (Garis Bujur)</label>
                    <input
                      type="text"
                      value={syncLng}
                      onChange={(e) => setSyncLng(e.target.value)}
                      placeholder="106.816666"
                      required
                      className="h-9 w-full rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-50/75 dark:bg-gray-900/50 px-3 font-mono text-xs text-white placeholder:text-slate-600 focus-visible:outline-none focus-visible:border-emerald-500"
                    />
                  </div>
                </div>

                {/* Alamat Patokan */}
                <div className="space-y-1">
                  <label className="text-[11px] font-semibold text-gray-700 dark:text-gray-300">Alamat / Patokan Rumah</label>
                  <input
                    type="text"
                    value={syncAddress}
                    onChange={(e) => setSyncAddress(e.target.value)}
                    placeholder="Misal: Jl. Melati No. 4, RT 01/RW 03..."
                    className="h-9 w-full rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-50/75 dark:bg-gray-900/50 px-3 text-xs text-white placeholder:text-gray-400 dark:text-gray-500 focus-visible:outline-none focus-visible:border-emerald-500"
                  />
                </div>

                {/* Pilih Box ODP / ODC / HTB / Switch (Searchable) */}
                {syncOdpId ? (
                  <div className="space-y-1.5">
                    <div className="flex items-center justify-between gap-2">
                      <label className="font-semibold text-gray-700 dark:text-gray-300 text-xs">
                        Titik Distribusi Terpilih
                      </label>
                      <button
                        type="button"
                        onClick={() => {
                          setSyncOdpId("")
                          setSyncOdpSearch("")
                        }}
                        className="text-xs text-amber-400 hover:underline font-semibold shrink-0"
                      >
                        Ganti Titik
                      </button>
                    </div>
                    {(() => {
                      const selectedOdp = odpsState.find((o) => String(o.id) === String(syncOdpId))
                      if (!selectedOdp) return null
                      const avail = Math.max(0, selectedOdp.capacity - selectedOdp.used_ports)
                      const isFull = selectedOdp.used_ports >= selectedOdp.capacity
                      return (
                        <div className="p-2.5 rounded-2xl border border-emerald-500/40 bg-brand-50 dark:bg-brand-500/10/60 flex items-center justify-between gap-2.5 min-w-0">
                          <div className="flex items-center gap-2.5 min-w-0 flex-1">
                            <div className={cn(
                              "flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-white font-bold text-xs",
                              selectedOdp.type === "odc"
                                ? "bg-purple-600"
                                : selectedOdp.type === "htb"
                                ? "bg-brand-500"
                                : selectedOdp.type === "switch"
                                ? "bg-emerald-600"
                                : "bg-amber-600"
                            )}>
                              <Network className="h-4 w-4" />
                            </div>
                            <div className="min-w-0 flex-1">
                              <h5 className="font-bold text-white text-xs truncate">{selectedOdp.name}</h5>
                              <p className="text-[10.5px] text-gray-500 dark:text-gray-400 truncate">
                                {selectedOdp.type?.toUpperCase() || "ODP"} · {selectedOdp.used_ports}/{selectedOdp.capacity} Port · <span className={isFull ? "text-rose-400 font-bold" : "text-emerald-400 font-bold"}>{isFull ? "Penuh" : `Sisa ${avail} Port`}</span>
                              </p>
                            </div>
                          </div>
                          <button
                            type="button"
                            onClick={() => {
                              setSyncOdpId("")
                              setSyncOdpSearch("")
                            }}
                            className="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg border border-gray-200 dark:border-gray-800 bg-gray-100 dark:bg-gray-800 text-gray-500 dark:text-gray-400 hover:text-white"
                            title="Hapus pilihan"
                          >
                            <X className="h-3.5 w-3.5" />
                          </button>
                        </div>
                      )
                    })()}
                  </div>
                ) : (
                  <div className="space-y-1.5">
                    <label className="font-semibold text-gray-700 dark:text-gray-300">
                      Hubungkan ke{" "}
                      {networkSystem === "lan"
                        ? "Titik LAN (HTB / Switch)"
                        : networkSystem === "pon"
                        ? "Titik FTTH (ODP / ODC)"
                        : "Titik Distribusi"}
                    </label>

                    {/* Search Bar */}
                    <div className="relative">
                      <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-gray-500 dark:text-gray-400" />
                      <input
                        type="text"
                        value={syncOdpSearch}
                        onChange={(e) => setSyncOdpSearch(e.target.value)}
                        placeholder="Ketik untuk mencari nama ODP / ODC / HTB..."
                        className="h-9 w-full rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-50/75 dark:bg-gray-900/50 pl-9 pr-7 text-xs text-white placeholder:text-gray-400 dark:text-gray-500 focus-visible:outline-none focus-visible:border-emerald-500"
                      />
                      {syncOdpSearch && (
                        <button
                          type="button"
                          onClick={() => setSyncOdpSearch("")}
                          className="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-500 dark:text-gray-400 hover:text-white"
                        >
                          <X className="h-3.5 w-3.5" />
                        </button>
                      )}
                    </div>

                    {/* Scrollable Result List */}
                    <div className="max-h-44 overflow-y-auto space-y-1 rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-50/75 dark:bg-gray-900/50/60 p-1.5 custom-scrollbar">
                      {/* Option: Tanpa ODP */}
                      <div
                        onClick={() => {
                          setSyncOdpId("")
                          setSyncOdpPort(1)
                        }}
                        className={cn(
                          "p-2 rounded-lg border text-xs cursor-pointer flex items-center justify-between transition-all select-none",
                          !syncOdpId
                            ? "border-emerald-500/50 bg-emerald-500/10 text-emerald-300"
                            : "border-transparent bg-white dark:bg-white/[0.03] text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:bg-gray-800 hover:text-white"
                        )}
                      >
                        <span className="font-semibold truncate">Tanpa Titik Distribusi (Hanya Simpan Lokasi)</span>
                        {!syncOdpId && <Check className="h-3.5 w-3.5 text-emerald-400 shrink-0" />}
                      </div>

                      {filteredSyncOdps.length === 0 ? (
                        <div className="py-3 text-center text-xs text-gray-400 dark:text-gray-500">
                          Tidak ditemukan titik distribusi "{syncOdpSearch}"
                        </div>
                      ) : (
                        filteredSyncOdps.map((o) => {
                          const avail = Math.max(0, o.capacity - o.used_ports)
                          const isFull = o.used_ports >= o.capacity

                          return (
                            <div
                              key={o.id}
                              onClick={() => {
                                const newOdpId = String(o.id)
                                setSyncOdpId(newOdpId)
                                // Auto-pilih port pertama yang masih kosong pada ODP ini
                                const cap = o.capacity || 8
                                const occupied = new Set<number>()
                                allCustomersState.forEach((c) => {
                                  if (String(c.odp_id) === newOdpId && c.id !== selectedSyncCustomer?.id && c.odp_port) {
                                    occupied.add(Number(c.odp_port))
                                  }
                                })
                                let firstFreePort = 1
                                for (let p = 1; p <= cap; p++) {
                                  if (!occupied.has(p)) {
                                    firstFreePort = p
                                    break
                                  }
                                }
                                setSyncOdpPort(firstFreePort)
                              }}
                              className="p-2 rounded-lg border border-gray-200 dark:border-gray-800/60 bg-white dark:bg-white/[0.03] text-gray-700 dark:text-gray-300 hover:border-brand-500/60 hover:bg-[#16202E] cursor-pointer flex items-center justify-between gap-2 transition-all select-none min-w-0"
                            >
                              <div className="flex items-center gap-2 min-w-0 flex-1">
                                <span className={cn(
                                  "h-2 w-2 rounded-full shrink-0",
                                  o.type === "odc"
                                    ? "bg-purple-400"
                                    : o.type === "htb"
                                    ? "bg-[#00C2FF]"
                                    : o.type === "switch"
                                    ? "bg-emerald-400"
                                    : "bg-amber-400"
                                )} />
                                <div className="min-w-0 flex-1">
                                  <div className="font-bold truncate text-white text-xs">{o.name}</div>
                                  <div className="text-[10px] text-gray-500 dark:text-gray-400 truncate">
                                    {o.type?.toUpperCase() || "ODP"} · {o.used_ports}/{o.capacity} Port · <span className={isFull ? "text-rose-400 font-bold" : "text-emerald-400 font-bold"}>{isFull ? "Penuh" : `Sisa ${avail}`}</span>
                                  </div>
                                </div>
                              </div>

                              <span className={cn(
                                "text-[9.5px] font-bold px-2 py-0.5 rounded border shrink-0 whitespace-nowrap",
                                isFull
                                  ? "bg-rose-500/20 text-rose-300 border-rose-500/40"
                                  : "bg-brand-50 dark:bg-brand-500/10 text-brand-600 dark:text-brand-400 border-brand-500/40 hover:bg-brand-500 hover:text-white"
                              )}>
                                Pilih
                              </span>
                            </div>
                          )
                        })
                      )}
                    </div>
                  </div>
                )}

                {/* Port ODP Selection with Collision Prevention */}
                {syncOdpId && (() => {
                  const currentOdpObj = odpsState.find((o) => String(o.id) === String(syncOdpId))
                  const odpCap = currentOdpObj?.capacity || 8
                  const isPortOccupied = occupiedPortsForSyncOdp.has(Number(syncOdpPort))
                  const occupiedByName = occupiedPortsForSyncOdp.get(Number(syncOdpPort))

                  return (
                    <div className="space-y-2 p-2.5 rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-50/75 dark:bg-gray-900/50">
                      <div className="flex items-center justify-between">
                        <label className="font-semibold text-gray-700 dark:text-gray-300 text-xs flex items-center gap-1.5">
                          <Radio className="h-3.5 w-3.5 text-emerald-400" />
                          <span>Pilih Nomor Port ({currentOdpObj?.name || "ODP"})</span>
                        </label>
                        <span className="text-[10px] text-gray-500 dark:text-gray-400 font-mono">
                          Kapasitas: {odpCap} Port
                        </span>
                      </div>

                      {/* Visual Port Badges Grid */}
                      <div className="grid grid-cols-4 sm:grid-cols-8 gap-1.5">
                        {Array.from({ length: odpCap }, (_, i) => i + 1).map((p) => {
                          const takenBy = occupiedPortsForSyncOdp.get(p)
                          const isTaken = !!takenBy
                          const isCurrent = Number(syncOdpPort) === p

                          return (
                            <button
                              key={p}
                              type="button"
                              disabled={isTaken}
                              onClick={() => setSyncOdpPort(p)}
                              title={isTaken ? `Port ${p} terpakai oleh: ${takenBy}` : `Port ${p} (Tersedia)`}
                              className={cn(
                                "h-8 rounded-lg text-xs font-bold transition-all flex flex-col items-center justify-center border relative",
                                isTaken
                                  ? "bg-rose-950/40 border-rose-800/40 text-rose-400/60 cursor-not-allowed opacity-60"
                                  : isCurrent
                                  ? "bg-emerald-600 border-emerald-400 text-white scale-[1.03]"
                                  : "bg-white dark:bg-white/[0.03] border-gray-200 dark:border-gray-800 text-gray-700 dark:text-gray-300 hover:border-brand-500 hover:bg-[#16202E] hover:text-white"
                              )}
                            >
                              <span>P{p}</span>
                              {isTaken && (
                                <span className="text-[7px] font-mono leading-none text-rose-400 truncate max-w-[90%]">
                                  Isi
                                </span>
                              )}
                            </button>
                          )
                        })}
                      </div>

                      {/* Dropdown Alternative */}
                      <div className="pt-1">
                        <select
                          value={syncOdpPort}
                          onChange={(e) => setSyncOdpPort(Number(e.target.value))}
                          className={cn(
                            "h-9 w-full rounded-xl border px-3 text-xs text-white focus-visible:outline-none",
                            isPortOccupied
                              ? "border-rose-500 bg-rose-950/30 text-rose-300 focus-visible:border-rose-400"
                              : "border-gray-200 dark:border-gray-800 bg-white dark:bg-white/[0.03] focus-visible:border-emerald-500"
                          )}
                        >
                          {Array.from({ length: odpCap }, (_, i) => i + 1).map((p) => {
                            const takenBy = occupiedPortsForSyncOdp.get(p)
                            return (
                              <option
                                key={p}
                                value={p}
                                disabled={!!takenBy}
                                className={takenBy ? "bg-[#1a1114] text-rose-400" : "bg-white dark:bg-white/[0.03] text-white"}
                              >
                                Port {p} {takenBy ? `• [Terpakai: ${takenBy}]` : "• (Tersedia)"}
                              </option>
                            )
                          })}
                        </select>
                      </div>

                      {/* Error Banner jika port bentrok */}
                      {isPortOccupied && (
                        <div className="flex items-center gap-1.5 text-xs text-rose-400 bg-rose-500/10 border border-rose-500/30 rounded-lg p-2">
                          <AlertCircle className="h-4 w-4 shrink-0 text-rose-400" />
                          <span>
                            Port <strong>{syncOdpPort}</strong> sudah digunakan oleh pelanggan <strong>"{occupiedByName}"</strong>. Silakan klik port yang masih kosong di atas.
                          </span>
                        </div>
                      )}
                    </div>
                  )
                })()}

                {/* Form Buttons */}
                <div className="pt-2 flex flex-col-reverse sm:flex-row sm:items-center sm:justify-end gap-2 border-t border-gray-200 dark:border-gray-800">
                  <button
                    type="button"
                    onClick={() => setSelectedSyncCustomer(null)}
                    className="w-full sm:w-auto rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:bg-gray-700 px-4 py-2 text-xs font-semibold text-center"
                  >
                    Batal
                  </button>
                  <button
                    type="submit"
                    disabled={syncingCustomerId !== null || (!!syncOdpId && occupiedPortsForSyncOdp.has(Number(syncOdpPort)))}
                    className="w-full sm:w-auto rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white px-4 py-2 text-xs font-bold flex items-center justify-center gap-1.5 disabled:opacity-50 disabled:cursor-not-allowed"
                  >
                    {syncingCustomerId !== null ? (
                      <>
                        <RefreshCw className="h-3.5 w-3.5 animate-spin" />
                        <span>Menyimpan...</span>
                      </>
                    ) : (
                      <>
                        <Check className="h-3.5 w-3.5" />
                        <span>Simpan &amp; Pasang di Peta</span>
                      </>
                    )}
                  </button>
                </div>
              </form>
            )}
          </DialogContent>
        </Dialog>

        {/* ── MODAL KONFIRMASI HAPUS ODP ── */}
        <Dialog open={isAdmin && deleteOdpModalOpen} onOpenChange={setDeleteOdpModalOpen}>
          <DialogContent className="w-[calc(100vw-24px)] sm:max-w-sm max-h-[90dvh] overflow-y-auto overflow-x-hidden p-4 sm:p-5 space-y-4 rounded-2xl sm:rounded-2xl border border-rose-500/30 bg-white dark:bg-white/[0.03] text-white">
            <DialogHeader className="pb-2 border-b border-gray-200 dark:border-gray-800 pr-8">
              <DialogTitle className="text-sm sm:text-base font-bold flex items-center gap-2 text-rose-400">
                <Trash2 className="h-4 w-4 shrink-0 text-rose-400" />
                <span className="truncate">Hapus Titik Distribusi?</span>
              </DialogTitle>
            </DialogHeader>

            <div className="space-y-2 text-xs text-gray-700 dark:text-gray-300">
              <p>
                Apakah Anda yakin ingin menghapus titik distribusi <strong>"{odpToDelete?.name}"</strong>?
              </p>
              <p className="text-[11px] text-gray-500 dark:text-gray-400">
                Tindakan ini akan menghapus marker dari peta dan memutuskan garis sambungan yang tertaut ke titik ini.
              </p>
            </div>

            <div className="pt-2 flex flex-col-reverse sm:flex-row sm:items-center sm:justify-end gap-2 border-t border-gray-200 dark:border-gray-800">
              <button
                type="button"
                onClick={() => setDeleteOdpModalOpen(false)}
                className="w-full sm:w-auto rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:bg-gray-700 px-4 py-2 text-xs font-semibold text-center"
              >
                Batal
              </button>
              <button
                type="button"
                onClick={confirmDeleteOdp}
                className="w-full sm:w-auto rounded-xl bg-rose-600 hover:bg-rose-500 text-white px-4 py-2 text-xs font-bold text-center"
              >
                Hapus Titik
              </button>
            </div>
          </DialogContent>
        </Dialog>

        {/* ── MODAL DIALOG FORM TAMBAH / EDIT ODC, ODP, HTB, SWITCH ── */}
        <Dialog open={isAdmin && modalOpen} onOpenChange={setModalOpen}>
          <DialogContent className="w-[calc(100vw-24px)] sm:max-w-md max-h-[92vh] overflow-y-auto custom-scrollbar p-5 sm:p-6 space-y-4 rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 text-gray-900 dark:text-white shadow-2xl">
            <DialogHeader className="pb-2 border-b border-gray-200 dark:border-gray-800 pr-8">
              <DialogTitle className="text-sm sm:text-base font-bold flex items-center gap-2 text-white">
                {networkSystem === "lan" ? (
                  <Server className="h-4 w-4 shrink-0 text-brand-600 dark:text-brand-400" />
                ) : networkSystem === "pon" ? (
                  <Network className="h-4 w-4 shrink-0 text-amber-400" />
                ) : (
                  <Layers className="h-4 w-4 shrink-0 text-purple-400" />
                )}
                <span className="truncate">
                  {editingOdp
                    ? networkSystem === "lan"
                      ? "Edit Titik Media Converter LAN"
                      : networkSystem === "pon"
                      ? "Edit Titik Distribusi FTTH"
                      : "Edit Titik Distribusi Jaringan"
                    : networkSystem === "lan"
                    ? "Tambah Titik Media Converter LAN"
                    : networkSystem === "pon"
                    ? "Tambah Titik Distribusi FTTH"
                    : "Tambah Titik Distribusi"}
                </span>
              </DialogTitle>
            </DialogHeader>

            <form onSubmit={handleSubmit} className="space-y-3.5 text-xs">
              <div className="space-y-1">
                <label className="font-semibold text-gray-700 dark:text-gray-300">Nama / Kode Titik</label>
                <input
                  type="text"
                  value={form.data.name}
                  onChange={(e) => form.setData("name", e.target.value)}
                  placeholder={
                    networkSystem === "lan"
                      ? "Contoh: HTB-BLOK-A atau SW-DIST-01"
                      : networkSystem === "pon"
                      ? "Contoh: ODC-PUSAT-01 atau ODP-RW01-08"
                      : "Contoh: ODC-01, ODP-08, atau HTB-A"
                  }
                  required
                  className="h-9 w-full rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-50/75 dark:bg-gray-900/50 px-3 text-xs text-white placeholder:text-gray-400 dark:text-gray-500 focus-visible:outline-none focus-visible:border-brand-500"
                />
                {form.errors.name && <p className="text-rose-400 text-[11px]">{form.errors.name}</p>}
              </div>

              {/* Jenis Perangkat & Kapasitas Port */}
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                <div className="space-y-1">
                  <label className="font-semibold text-gray-700 dark:text-gray-300">Jenis Perangkat</label>
                  <select
                    value={form.data.type}
                    onChange={(e) => {
                      const newType = e.target.value as any
                      form.setData((prev) => {
                        const isAutoName = !editingOdp && (!prev.name || !prev.name.trim() || /^(ODP|ODC|HTB|SWITCH)[-_/ ]?/i.test(prev.name.trim()))
                        const updatedName = isAutoName ? generateDeviceName(newType, prev.router_id, odpsState, routersState) : prev.name
                        return {
                          ...prev,
                          name: updatedName,
                          type: newType,
                          network_mode: newType === "htb" || newType === "switch" ? "lan" : "pon",
                          capacity: newType === "odc" ? 24 : newType === "htb" ? 2 : newType === "switch" ? 8 : 8,
                        }
                      })
                    }}
                    className="h-9 w-full rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-50/75 dark:bg-gray-900/50 px-2.5 text-xs text-white focus-visible:outline-none focus-visible:border-brand-500"
                  >
                    {networkSystem === "lan" ? (
                      <>
                        <option value="htb" className="bg-white dark:bg-white/[0.03]">HTB (Media Converter)</option>
                        <option value="switch" className="bg-white dark:bg-white/[0.03]">Switch Hub (Distribusi LAN)</option>
                      </>
                    ) : networkSystem === "pon" ? (
                      <>
                        <option value="odp" className="bg-white dark:bg-white/[0.03]">ODP Modular (Splitter PLC)</option>
                        <option value="odp_ratio" className="bg-white dark:bg-white/[0.03]">ODP Ratio (Splitter Asimetris)</option>
                        <option value="odc" className="bg-white dark:bg-white/[0.03]">ODC (Optical Distribution Cabinet)</option>
                      </>
                    ) : (
                      <>
                        <option value="odp" className="bg-white dark:bg-white/[0.03]">ODP Modular (Splitter PLC)</option>
                        <option value="odp_ratio" className="bg-white dark:bg-white/[0.03]">ODP Ratio (Splitter Asimetris)</option>
                        <option value="odc" className="bg-white dark:bg-white/[0.03]">ODC (Optical Distribution Cabinet)</option>
                        <option value="htb" className="bg-white dark:bg-white/[0.03]">HTB (Media Converter)</option>
                        <option value="switch" className="bg-white dark:bg-white/[0.03]">Switch Hub (Distribusi LAN)</option>
                      </>
                    )}
                  </select>
                </div>

                <div className="space-y-1">
                  <label className="font-semibold text-gray-700 dark:text-gray-300">
                    {form.data.type === "htb"
                      ? "Kapasitas Port (SC & LAN)"
                      : form.data.type === "odp_ratio"
                      ? "Pilihan Ratio Splitter"
                      : form.data.type === "odp" || form.data.type === "odc"
                      ? "Kapasitas Port (Splitter)"
                      : "Kapasitas Port"}
                  </label>
                  <select
                    value={form.data.capacity}
                    onChange={(e) => form.setData("capacity", parseInt(e.target.value))}
                    className="h-9 w-full rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-50/75 dark:bg-gray-900/50 px-2.5 text-xs text-white focus-visible:outline-none focus-visible:border-brand-500"
                  >
                    {form.data.type === "htb" ? (
                      <>
                        <option value={1} className="bg-white dark:bg-white/[0.03]">1 SC + 1 LAN (Standar A/B)</option>
                        <option value={2} className="bg-white dark:bg-white/[0.03]">1 SC + 2 LAN</option>
                        <option value={4} className="bg-white dark:bg-white/[0.03]">1 SC + 4 LAN</option>
                        <option value={8} className="bg-white dark:bg-white/[0.03]">1 SC + 8 LAN</option>
                        <option value={2} className="bg-white dark:bg-white/[0.03]">2 SC + 2 LAN</option>
                        <option value={4} className="bg-white dark:bg-white/[0.03]">2 SC + 4 LAN (Board HTB)</option>
                        <option value={6} className="bg-white dark:bg-white/[0.03]">2 SC + 6 LAN</option>
                        <option value={8} className="bg-white dark:bg-white/[0.03]">2 SC + 8 LAN (Board HTB)</option>
                        <option value={2} className="bg-white dark:bg-white/[0.03]">3 SC + 2 LAN</option>
                        <option value={4} className="bg-white dark:bg-white/[0.03]">4 SC + 4 LAN</option>
                        <option value={2} className="bg-white dark:bg-white/[0.03]">6 SC + 2 LAN</option>
                        <option value={2} className="bg-white dark:bg-white/[0.03]">8 SC + 2 LAN</option>
                        <option value={14} className="bg-white dark:bg-white/[0.03]">14 Slot (Chassis Rack)</option>
                        <option value={16} className="bg-white dark:bg-white/[0.03]">16 Slot (Chassis Rack)</option>
                      </>
                    ) : form.data.type === "switch" ? (
                      <>
                        <option value={4} className="bg-white dark:bg-white/[0.03]">4 Port (Switch Hub)</option>
                        <option value={5} className="bg-white dark:bg-white/[0.03]">5 Port (Switch Mini)</option>
                        <option value={8} className="bg-white dark:bg-white/[0.03]">8 Port (Switch Hub)</option>
                        <option value={16} className="bg-white dark:bg-white/[0.03]">16 Port (Switch Hub)</option>
                        <option value={24} className="bg-white dark:bg-white/[0.03]">24 Port (Switch Rack)</option>
                        <option value={48} className="bg-white dark:bg-white/[0.03]">48 Port (Switch Core)</option>
                      </>
                    ) : form.data.type === "odc" ? (
                      <>
                        <option value={24} className="bg-white dark:bg-white/[0.03]">24 Port (ODC Mini)</option>
                        <option value={48} className="bg-white dark:bg-white/[0.03]">48 Port (ODC Standar)</option>
                        <option value={96} className="bg-white dark:bg-white/[0.03]">96 Port (ODC Besar)</option>
                        <option value={144} className="bg-white dark:bg-white/[0.03]">144 Port (ODC Maxi)</option>
                        <option value={288} className="bg-white dark:bg-white/[0.03]">288 Port (ODC Ultra)</option>
                      </>
                    ) : form.data.type === "odp_ratio" || form.data.type === "ratio" ? (
                      <>
                        <option value={2} className="bg-white dark:bg-white/[0.03]">2 Port (Ratio 1:99 / 99:1)</option>
                        <option value={2} className="bg-white dark:bg-white/[0.03]">2 Port (Ratio 2:98 / 98:2)</option>
                        <option value={2} className="bg-white dark:bg-white/[0.03]">2 Port (Ratio 3:97 / 97:3)</option>
                        <option value={2} className="bg-white dark:bg-white/[0.03]">2 Port (Ratio 5:95 / 95:5)</option>
                        <option value={2} className="bg-white dark:bg-white/[0.03]">2 Port (Ratio 10:90 / 90:10)</option>
                        <option value={2} className="bg-white dark:bg-white/[0.03]">2 Port (Ratio 15:85 / 85:15)</option>
                        <option value={2} className="bg-white dark:bg-white/[0.03]">2 Port (Ratio 20:80 / 80:20)</option>
                        <option value={2} className="bg-white dark:bg-white/[0.03]">2 Port (Ratio 30:70 / 70:30)</option>
                        <option value={2} className="bg-white dark:bg-white/[0.03]">2 Port (Ratio 40:60 / 60:40)</option>
                        <option value={2} className="bg-white dark:bg-white/[0.03]">2 Port (Ratio 50:50)</option>
                        <option value={3} className="bg-white dark:bg-white/[0.03]">3 Port (Ratio + Drop 1:2)</option>
                        <option value={4} className="bg-white dark:bg-white/[0.03]">4 Port (Ratio + Drop 1:4)</option>
                        <option value={8} className="bg-white dark:bg-white/[0.03]">8 Port (Ratio + Drop 1:8)</option>
                      </>
                    ) : (
                      <>
                        <option value={2} className="bg-white dark:bg-white/[0.03]">1:2 (2 Port)</option>
                        <option value={4} className="bg-white dark:bg-white/[0.03]">1:4 (4 Port)</option>
                        <option value={8} className="bg-white dark:bg-white/[0.03]">1:8 (8 Port - Standar ODP)</option>
                        <option value={16} className="bg-white dark:bg-white/[0.03]">1:16 (16 Port - ODP)</option>
                        <option value={24} className="bg-white dark:bg-white/[0.03]">1:24 (24 Port)</option>
                        <option value={32} className="bg-white dark:bg-white/[0.03]">1:32 (32 Port)</option>
                        <option value={64} className="bg-white dark:bg-white/[0.03]">1:64 (64 Port)</option>
                        <option value={96} className="bg-white dark:bg-white/[0.03]">1:96 (96 Port)</option>
                        <option value={128} className="bg-white dark:bg-white/[0.03]">1:128 (128 Port)</option>
                        <option value={144} className="bg-white dark:bg-white/[0.03]">1:144 (144 Port)</option>
                      </>
                    )}
                  </select>
                </div>
              </div>

              {/* Induk / Uplink Parent & Router */}
              <div className="space-y-1 relative">
                <div className="flex items-center justify-between">
                  <label className="font-semibold text-gray-700 dark:text-gray-300">Sumber Uplink (Induk)</label>
                  {form.data.parent_odp_id && (
                    <button
                      type="button"
                      onClick={() => {
                        form.setData("parent_odp_id", "")
                        setParentOdpSearch("")
                        setIsParentDropdownOpen(false)
                        if (!form.data.router_id && routersState.length > 0) {
                          form.setData("router_id", String(routersState[0].id))
                        }
                      }}
                      className="text-[10px] text-rose-400 hover:text-rose-300 font-medium"
                    >
                      Reset ke NOC Gateway
                    </button>
                  )}
                </div>
                <div className="relative">
                  <input
                    type="text"
                    placeholder="Ketik nama untuk cari ODP/ODC Induk... (kosong = Gateway NOC)"
                    value={parentOdpSearch}
                    onChange={(e) => {
                      const val = e.target.value
                      setParentOdpSearch(val)
                      setIsParentDropdownOpen(true)
                      if (!val.trim()) {
                        form.setData("parent_odp_id", "")
                        if (!form.data.router_id && routersState.length > 0) {
                          form.setData("router_id", String(routersState[0].id))
                        }
                      }
                    }}
                    onFocus={() => setIsParentDropdownOpen(true)}
                    className="h-9 w-full rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-50/75 dark:bg-gray-900/50 pl-8 pr-8 text-xs text-white placeholder:text-gray-400 dark:text-gray-500 focus-visible:outline-none focus-visible:border-brand-500"
                  />
                  <Search className="absolute left-2.5 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-gray-400 dark:text-gray-500 pointer-events-none" />
                  {parentOdpSearch && (
                    <button
                      type="button"
                      onClick={() => {
                        setParentOdpSearch("")
                        form.setData("parent_odp_id", "")
                        setIsParentDropdownOpen(false)
                        if (!form.data.router_id && routersState.length > 0) {
                          form.setData("router_id", String(routersState[0].id))
                        }
                      }}
                      className="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 dark:text-gray-500 hover:text-white"
                    >
                      <X className="h-3.5 w-3.5" />
                    </button>
                  )}
                </div>

                {/* Dropdown list */}
                {isParentDropdownOpen && (
                  <>
                    <div
                      className="fixed inset-0 z-40"
                      onClick={() => setIsParentDropdownOpen(false)}
                    />
                    <div className="absolute left-0 right-0 top-full z-50 mt-1 max-h-52 overflow-y-auto rounded-xl border border-gray-200 dark:border-gray-800 bg-[#0D1117] shadow-sm p-1 space-y-0.5 custom-scrollbar">
                      <div
                        onClick={() => {
                          form.setData("parent_odp_id", "")
                          setParentOdpSearch("")
                          setIsParentDropdownOpen(false)
                          if (!form.data.router_id && routersState.length > 0) {
                            form.setData("router_id", String(routersState[0].id))
                          }
                        }}
                        className={cn(
                          "px-2.5 py-2 rounded-lg text-xs cursor-pointer flex items-center justify-between transition-colors",
                          !form.data.parent_odp_id
                            ? "bg-brand-500/20 text-[#38bdf8] font-bold"
                            : "text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:bg-gray-800 hover:text-white"
                        )}
                      >
                        <span className="flex items-center gap-2">
                          <Radio className="h-3.5 w-3.5 text-amber-400 shrink-0" />
                          <span>-- Router Gateway NOC (Pusat) --</span>
                        </span>
                        {!form.data.parent_odp_id && <Check className="h-3.5 w-3.5 text-[#38bdf8] shrink-0" />}
                      </div>

                      {odpsState
                        .filter((o) => {
                          if (editingOdp && o.id === editingOdp.id) return false
                          if (networkSystem === "pon" && (o.type === "htb" || o.type === "switch")) return false
                          if (networkSystem === "lan" && (o.type === "odc" || o.type === "odp" || !o.type)) return false
                          if (!parentOdpSearch || !parentOdpSearch.trim()) return true
                          const q = (parentOdpSearch || "").toLowerCase()
                          return (o.name || "").toLowerCase().includes(q) || (o.type || "odp").toLowerCase().includes(q)
                        })
                        .map((o) => {
                          const isSelected = String(form.data.parent_odp_id) === String(o.id)
                          return (
                            <div
                              key={o.id}
                              onClick={() => {
                                form.setData("parent_odp_id", String(o.id))
                                setParentOdpSearch(`${o.name} (${(o.type || "ODP").toUpperCase()})`)
                                setIsParentDropdownOpen(false)
                              }}
                              className={cn(
                                "px-2.5 py-2 rounded-lg text-xs cursor-pointer flex items-center justify-between transition-colors",
                                isSelected
                                  ? "bg-brand-500/20 text-[#38bdf8] font-bold border border-brand-500/40"
                                  : "text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:bg-gray-800 hover:text-white"
                              )}
                            >
                              <div className="flex items-center gap-2 min-w-0">
                                <span className={cn(
                                  "h-2 w-2 rounded-full shrink-0",
                                  o.type === "odc" ? "bg-purple-400" : o.type === "htb" ? "bg-[#00C2FF]" : o.type === "switch" ? "bg-emerald-400" : "bg-amber-400"
                                )} />
                                <span className="truncate">{o.name}</span>
                                <span className="text-[10px] px-1.5 py-0.5 rounded bg-gray-100 dark:bg-gray-800 text-gray-500 dark:text-gray-400 border border-gray-200 dark:border-gray-800 uppercase font-mono">
                                  {o.type || "ODP"}
                                </span>
                              </div>
                              {isSelected && <Check className="h-3.5 w-3.5 text-[#38bdf8] shrink-0" />}
                            </div>
                          )
                        })}
                    </div>
                  </>
                )}
              </div>

              {/* Router NOC Gateway */}
              <div className="space-y-1">
                <label className="font-semibold text-gray-700 dark:text-gray-300">Router Core NOC Gateway</label>
                <select
                  value={form.data.router_id || (routersState[0]?.id ? String(routersState[0].id) : "")}
                  onChange={(e) => {
                    const newRouterId = e.target.value
                    form.setData((prev) => {
                      const isAutoName = !editingOdp && (!prev.name || !prev.name.trim() || /^(ODP|ODC|HTB|SWITCH)[-_/ ]?/i.test(prev.name.trim()))
                      const updatedName = isAutoName ? generateDeviceName(prev.type, newRouterId, odpsState, routersState) : prev.name
                      return {
                        ...prev,
                        router_id: newRouterId,
                        name: updatedName,
                      }
                    })
                  }}
                  className="h-9 w-full rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-50/75 dark:bg-gray-900/50 px-2.5 text-xs text-white focus-visible:outline-none focus-visible:border-brand-500"
                >
                  {routersState.map((r) => (
                    <option key={r.id} value={r.id} className="bg-white dark:bg-white/[0.03]">
                      {r.name} ({r.host}) {r.lat && r.lng ? "• [Lokasi Terpasang]" : "• [Belum Pasang Lokasi]"}
                    </option>
                  ))}
                </select>
                {routersState.length > 0 && !routersState.find((r) => String(r.id) === String(form.data.router_id || routersState[0]?.id))?.lat && (
                  <p className="text-[10px] text-amber-400">
                    * Garis backbone ungu akan terhubung otomatis setelah lokasi Router NOC dipasang pada peta.
                  </p>
                )}
              </div>

              {/* GPS Coordinates */}
              <div className="space-y-2 pt-1 border-t border-gray-200 dark:border-gray-800">
                <div className="flex items-center justify-between">
                  <label className="font-semibold text-gray-700 dark:text-gray-300 flex items-center gap-1">
                    <MapPin className="h-3.5 w-3.5 text-brand-600 dark:text-brand-400" />
                    <span>Koordinat Titik Lokasi</span>
                  </label>

                  <button
                    type="button"
                    onClick={handleGetCurrentLocation}
                    disabled={gettingLocation}
                    className="flex items-center gap-1 text-[11px] font-bold text-brand-600 dark:text-brand-400 hover:underline disabled:opacity-50"
                  >
                    <Navigation className="h-3 w-3" />
                    <span>{gettingLocation ? "Mencari GPS..." : "Ambil GPS Sekarang"}</span>
                  </button>
                </div>

                <div className="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                  <div>
                    <span className="text-[10px] text-gray-400 dark:text-gray-500 block mb-0.5">Latitude (Lintang)</span>
                    <input
                      type="text"
                      value={form.data.lat}
                      onChange={(e) => form.setData("lat", e.target.value)}
                      placeholder="-6.200000"
                      required
                      className="h-9 w-full rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-50/75 dark:bg-gray-900/50 px-3 text-xs font-mono text-white placeholder:text-gray-400 dark:text-gray-500 focus-visible:outline-none focus-visible:border-brand-500"
                    />
                  </div>

                  <div>
                    <span className="text-[10px] text-gray-400 dark:text-gray-500 block mb-0.5">Longitude (Bujur)</span>
                    <input
                      type="text"
                      value={form.data.lng}
                      onChange={(e) => form.setData("lng", e.target.value)}
                      placeholder="106.816666"
                      required
                      className="h-9 w-full rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-50/75 dark:bg-gray-900/50 px-3 text-xs font-mono text-white placeholder:text-gray-400 dark:text-gray-500 focus-visible:outline-none focus-visible:border-brand-500"
                    />
                  </div>
                </div>
              </div>

              {/* Action Buttons */}
              <div className="pt-3 border-t border-gray-200 dark:border-gray-800 flex flex-col-reverse sm:flex-row sm:items-center sm:justify-end gap-2">
                <button
                  type="button"
                  onClick={() => setModalOpen(false)}
                  className="w-full sm:w-auto rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-100 dark:bg-gray-800 hover:bg-[#1C232E] text-gray-700 dark:text-gray-300 px-4 py-2 text-xs font-semibold text-center"
                >
                  Batal
                </button>

                <button
                  type="submit"
                  disabled={form.processing || submittingOdp}
                  className="w-full sm:w-auto flex items-center justify-center gap-1.5 rounded-xl bg-brand-500 hover:bg-[#0084E3] border border-[#0094FF]/40 text-white px-4 py-2 text-xs font-bold disabled:opacity-50"
                >
                  <span>{submittingOdp ? "Menyimpan..." : editingOdp ? "Simpan Perubahan" : "Simpan Titik"}</span>
                </button>
              </div>
            </form>
          </DialogContent>
        </Dialog>
      </div>
    </AppLayout>
  )
}
