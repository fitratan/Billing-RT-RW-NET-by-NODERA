import * as React from "react"
import { useState, useMemo } from "react"
import { AppLayout } from "@/components/layout/app-layout"
import { usePage, useForm, router, Link } from "@inertiajs/react"
import {
  User,
  Phone,
  AtSign,
  MapPin,
  Router as RouterIcon,
  Package as PackageIcon,
  ShieldCheck,
  ChevronLeft,
  ArrowLeft,
  UserPlus,
  Sparkles,
  CheckCircle2,
  Eye,
  EyeOff,
  Radio,
  Navigation,
  Globe,
  Calendar,
  Network,
  AlertTriangle,
  Layers,
  Activity,
} from "lucide-react"
import { PageProps } from "@/types"
import { collectorBrand, getCollectorNavItems, getCollectorSidebarItems } from "@/lib/collector-nav"
import { technicianBrand, getTechnicianNavItems, getTechnicianSidebarItems } from "@/lib/technician-nav"
import { cn } from "@/lib/utils"

interface Package {
  id: number
  name: string
  price?: number
}

interface Router {
  id: number
  name: string
  host?: string
}

interface OdpOption {
  id: number
  name: string
  capacity: number
}

interface ExistingCustomer {
  id: number
  name: string
  odp_id?: number | null
  odp_port?: number | null
}

export default function CreateCustomerPage({
  packages = [],
  routers = [],
  odps = [],
  customers = [],
}: PageProps<{
  packages: Package[]
  routers: Router[]
  odps?: OdpOption[]
  customers?: ExistingCustomer[]
}>) {
  const { url, props } = usePage<any>()
  const isCollector = url.startsWith("/kolektor")
  const brand = isCollector ? collectorBrand : technicianBrand
  const userPerms = props.permissions || {}
  const sidebarItems = isCollector ? getCollectorSidebarItems(userPerms) : getTechnicianSidebarItems(userPerms)
  const navItems = isCollector ? getCollectorNavItems(userPerms) : getTechnicianNavItems(userPerms)
  const submitRoute = isCollector ? "/kolektor/create-customer/store" : "/teknisi/create-customer/store"
  const dashboardRoute = isCollector ? "/kolektor/dashboard" : "/teknisi/dashboard"

  const [showPppoePass, setShowPppoePass] = useState(false)
  const [isGettingGps, setIsGettingGps] = useState(false)
  const [gpsError, setGpsError] = useState<string | null>(null)

  const { data, setData, post, processing, errors } = useForm<{
    connection_type: "pppoe" | "static" | "hotspot"
    name: string
    phone: string
    email: string
    isolation_date: string
    pppoe_username: string
    pppoe_password: string
    ip_address: string
    mac_address: string
    arp_interface: string
    create_arp: boolean
    package_id: string
    router_id: string
    odp_id: string
    odp_port: string
    address: string
    lat: string
    lng: string
    create_pppoe: boolean
  }>({
    connection_type: "pppoe",
    name: "",
    phone: "",
    email: "",
    isolation_date: "20",
    pppoe_username: "",
    pppoe_password: "",
    ip_address: "",
    mac_address: "",
    arp_interface: "bridge",
    create_arp: true,
    package_id: "",
    router_id: "",
    odp_id: "",
    odp_port: "",
    address: "",
    lat: "",
    lng: "",
    create_pppoe: true,
  })

  // Map of occupied ports for currently selected ODP
  const occupiedPortsForOdp = useMemo(() => {
    if (!data.odp_id) return new Map<number, string>()
    const map = new Map<number, string>()
    customers.forEach((c) => {
      if (String(c.odp_id) === String(data.odp_id) && c.odp_port) {
        map.set(Number(c.odp_port), c.name)
      }
    })
    return map
  }, [data.odp_id, customers])

  // Get current device GPS Geolocation
  const handleGetDeviceLocation = () => {
    if (!navigator.geolocation) {
      setGpsError("Browser / perangkat Anda tidak mendukung geolokasi.")
      return
    }

    setIsGettingGps(true)
    setGpsError(null)

    navigator.geolocation.getCurrentPosition(
      (position) => {
        setData((prev: any) => ({
          ...prev,
          lat: position.coords.latitude.toFixed(7),
          lng: position.coords.longitude.toFixed(7),
        }))
        setIsGettingGps(false)
      },
      (error) => {
        setIsGettingGps(false)
        switch (error.code) {
          case error.PERMISSION_DENIED:
            setGpsError("Izin akses lokasi ditolak di browser Anda.")
            break
          case error.POSITION_UNAVAILABLE:
            setGpsError("Informasi lokasi GPS tidak tersedia.")
            break
          case error.TIMEOUT:
            setGpsError("Waktu pencarian lokasi GPS habis.")
            break
          default:
            setGpsError("Gagal mendeteksi lokasi GPS.")
            break
        }
      },
      { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
    )
  }

  const submit = (e: React.FormEvent) => {
    e.preventDefault()
    post(submitRoute, { preserveScroll: true })
  }

  return (
    <AppLayout
      title="Pasang Baru (Pelanggan)"
      brand={brand}
      sidebarItems={sidebarItems}
      navItems={navItems}
      hideHeader={true}
      className="p-0 sm:p-0 max-w-none bg-[#0B0E14]"
    >
      <div className="min-h-screen bg-[#0B0E14] text-slate-100 select-none pb-28 sm:pb-36">
        {/* =========================================================================
            SECTION 1: TOP ELECTRIC BLUE CANVAS (Obsidian Modern Standard)
            ========================================================================= */}
        <section className="relative bg-gradient-to-br from-[#0073C6] via-[#0056A0] to-[#003E78] px-4 pt-5 pb-10 sm:px-8 sm:pt-7 sm:pb-12 text-white shadow-sm rounded-b-[2rem] sm:rounded-b-[2.5rem] border-b border-white/15 overflow-hidden">
          <div className="absolute inset-0 overflow-hidden pointer-events-none">
            <div
              className="absolute inset-0 opacity-20"
              
            />
            <div className="absolute -top-12 -right-12 h-56 w-56 rounded-full border border-white/15 pointer-events-none" />
            <div className="absolute -top-6 -right-6 h-44 w-44 rounded-full border border-dashed border-white/10 pointer-events-none" />
          </div>

          <div className="relative max-w-7xl 2xl:max-w-[1700px] mx-auto space-y-4 sm:space-y-5">
            {/* Top Bar Header inside Canvas */}
            <div className="flex items-center justify-between gap-2.5 sm:gap-4">
              <Link
                href={dashboardRoute}
                className="flex h-9 w-9 sm:h-10 sm:w-10 shrink-0 items-center justify-center rounded-full border border-white/20 bg-white/15 backdrop-blur-md text-white hover:bg-white/25 active:scale-95 transition-all lg:hidden"
                title="Kembali ke Dashboard"
              >
                <ChevronLeft className="h-5 w-5 shrink-0" />
              </Link>

              <div className="flex items-center gap-2 rounded-full border border-white/20 bg-black/25 px-3.5 py-1.5 backdrop-blur-md">
                <img
                  src="/images/logo-white.png?v=36"
                  alt="NODERA"
                  className="h-6 w-6 rounded-full object-contain bg-white/95 p-0.5"
                />
                <span className="text-xs font-black tracking-wider text-white">
                  NODERA {isCollector ? "KOLEKTOR" : "TEKNISI"}
                </span>
              </div>
            </div>

            {/* Hero Title Block */}
            <div className="space-y-1 pt-1">
              <div className="inline-flex items-center gap-1.5 rounded-full border border-white/25 bg-white/15 px-3 py-1 text-[11px] font-bold uppercase tracking-wider backdrop-blur-md">
                <Sparkles className="h-3.5 w-3.5 text-amber-300" />
                <span>Registrasi &amp; Pemasangan Baru</span>
              </div>
              <h1 className="font-display text-xl sm:text-2xl font-black tracking-tight text-white">
                Tambah Pelanggan Baru
              </h1>
              <p className="text-xs sm:text-sm text-white/80 font-medium">
                Formulir pendaftaran pelanggan lengkap, terintegrasi ODP, GPS Geolocation, dan MikroTik.
              </p>
            </div>
          </div>
        </section>

        {/* =========================================================================
            SECTION 2: FORM CARD (Obsidian Dark Glassmorphic)
            ========================================================================= */}
        <div className="max-w-7xl 2xl:max-w-[1700px] mx-auto px-4 -mt-5 sm:-mt-6 relative z-10">
          <div className="rounded-2xl border border-[#1E2633] bg-[#121720]/95 backdrop-blur-xl shadow-sm p-5 sm:p-8">
            <form onSubmit={submit} className="space-y-6">

              {/* 1. Tipe Koneksi Jaringan */}
              <div className="space-y-2">
                <label className="text-xs font-black uppercase tracking-wider text-[#00C2FF] flex items-center gap-2">
                  <Network className="h-4 w-4" />
                  <span>Tipe Koneksi Jaringan</span>
                </label>
                <select
                  value={data.connection_type}
                  onChange={(e) => setData("connection_type", e.target.value as any)}
                  className="h-11 w-full rounded-2xl border border-[#212B3B] bg-[#0B0E14] px-4 text-xs sm:text-sm font-bold text-white focus:outline-none focus:border-[#0073C6] shadow-inner"
                >
                  <option value="pppoe" className="bg-[#121720] text-white">PPPoE (MikroTik Local Secret)</option>
                  <option value="static" className="bg-[#121720] text-white">Static IP / ARP Binding</option>
                  <option value="hotspot" className="bg-[#121720] text-white">Hotspot / Voucher User</option>
                </select>
              </div>

              {/* 2. Informasi Identitas Pelanggan */}
              <div className="space-y-4 pt-2 border-t border-[#1E2633]/80">
                <h3 className="text-xs font-black uppercase tracking-wider text-[#00C2FF] flex items-center gap-2">
                  <User className="h-4 w-4" />
                  <span>Informasi Identitas Pelanggan</span>
                </h3>

                <div className="space-y-2">
                  <label className="text-xs font-bold text-slate-300 block">
                    Nama Lengkap Pelanggan <span className="text-rose-400">*</span>
                  </label>
                  <div className="relative">
                    <User className="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-500" />
                    <input
                      type="text"
                      required
                      value={data.name}
                      onChange={(e) => setData("name", e.target.value)}
                      placeholder="Masukkan nama lengkap pelanggan"
                      className="h-11 w-full rounded-2xl border border-[#1E2633] bg-[#0B0E14] pl-10 pr-4 text-xs sm:text-sm font-medium text-white placeholder:text-slate-500 focus:border-[#0073C6] focus:bg-[#0E131C] focus:outline-none transition-all shadow-inner"
                    />
                  </div>
                  {errors.name && <p className="text-xs text-rose-400 font-medium">{errors.name}</p>}
                </div>

                <div className="grid gap-4 sm:grid-cols-2">
                  <div className="space-y-2">
                    <label className="text-xs font-bold text-slate-300 block">
                      No. WhatsApp / HP Aktif <span className="text-rose-400">*</span>
                    </label>
                    <div className="relative">
                      <Phone className="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-500" />
                      <input
                        type="tel"
                        required
                        value={data.phone}
                        onChange={(e) => setData("phone", e.target.value)}
                        placeholder="Contoh: 081234567890"
                        className="h-11 w-full rounded-2xl border border-[#1E2633] bg-[#0B0E14] pl-10 pr-4 text-xs sm:text-sm font-medium text-white placeholder:text-slate-500 focus:border-[#0073C6] focus:bg-[#0E131C] focus:outline-none transition-all shadow-inner"
                      />
                    </div>
                    {errors.phone && <p className="text-xs text-rose-400 font-medium">{errors.phone}</p>}
                  </div>

                  <div className="space-y-2">
                    <label className="text-xs font-bold text-slate-300 block flex items-center justify-between">
                      <span>Tanggal Jatuh Tempo / Isolir</span>
                      <span className="text-[10px] text-slate-500 font-normal">Tgl 1 - 28</span>
                    </label>
                    <div className="relative">
                      <Calendar className="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-500" />
                      <input
                        type="number"
                        min={1}
                        max={28}
                        required
                        value={data.isolation_date}
                        onChange={(e) => setData("isolation_date", e.target.value)}
                        placeholder="20"
                        className="h-11 w-full rounded-2xl border border-[#1E2633] bg-[#0B0E14] pl-10 pr-4 text-xs sm:text-sm font-mono font-medium text-white placeholder:text-slate-500 focus:border-[#0073C6] focus:bg-[#0E131C] focus:outline-none transition-all shadow-inner"
                      />
                    </div>
                    {errors.isolation_date && <p className="text-xs text-rose-400 font-medium">{errors.isolation_date}</p>}
                  </div>
                </div>

                {/* Username & Password PPPoE */}
                {data.connection_type === "pppoe" && (
                  <div className="grid gap-4 sm:grid-cols-2">
                    <div className="space-y-2">
                      <label className="text-xs font-bold text-slate-300 block">
                        Username PPPoE <span className="text-rose-400">*</span>
                      </label>
                      <div className="relative">
                        <AtSign className="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-500" />
                        <input
                          type="text"
                          required
                          value={data.pppoe_username}
                          onChange={(e) => setData("pppoe_username", e.target.value.toLowerCase().replace(/\s+/g, ""))}
                          placeholder="Contoh: user_budi"
                          className="h-11 w-full rounded-2xl border border-[#1E2633] bg-[#0B0E14] pl-10 pr-4 text-xs sm:text-sm font-mono font-medium text-[#00C2FF] placeholder:text-slate-500 focus:border-[#0073C6] focus:bg-[#0E131C] focus:outline-none transition-all shadow-inner"
                        />
                      </div>
                      {errors.pppoe_username && (
                        <p className="text-xs text-rose-400 font-medium">{errors.pppoe_username}</p>
                      )}
                    </div>

                    <div className="space-y-2">
                      <label className="text-xs font-bold text-slate-300 block">
                        Password Secret PPPoE
                      </label>
                      <div className="relative">
                        <input
                          type={showPppoePass ? "text" : "password"}
                          value={data.pppoe_password}
                          onChange={(e) => setData("pppoe_password", e.target.value)}
                          placeholder="Default: 123456"
                          className="h-11 w-full rounded-2xl border border-[#1E2633] bg-[#0B0E14] pl-4 pr-10 text-xs sm:text-sm font-mono font-medium text-white placeholder:text-slate-500 focus:border-[#0073C6] focus:bg-[#0E131C] focus:outline-none transition-all shadow-inner"
                        />
                        <button
                          type="button"
                          onClick={() => setShowPppoePass(!showPppoePass)}
                          className="absolute right-3.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-white transition-colors"
                        >
                          {showPppoePass ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                        </button>
                      </div>
                    </div>
                  </div>
                )}

                {/* Static IP / MAC Address */}
                {data.connection_type === "static" && (
                  <div className="space-y-4 rounded-2xl border border-[#0073C6]/30 bg-[#0B2138]/40 p-4">
                    <div className="flex items-start gap-2.5 text-xs">
                      <Activity className="h-4 w-4 shrink-0 text-[#00C2FF] mt-0.5" />
                      <div>
                        <p className="font-bold text-white">Mode IP Statis / ARP Binding</p>
                        <p className="text-[11px] text-slate-300 mt-0.5 leading-relaxed">
                          Kecepatan internet dibatasi otomatis via <strong>Simple Queue MikroTik</strong> berdasarkan paket yang dipilih.
                        </p>
                      </div>
                    </div>
                    <div className="grid gap-4 sm:grid-cols-3">
                      <div className="space-y-2">
                        <label className="text-xs font-bold text-slate-300 block">
                          IP Address Static <span className="text-rose-400">*</span>
                        </label>
                        <input
                          type="text"
                          required
                          value={data.ip_address}
                          onChange={(e) => setData("ip_address", e.target.value)}
                          placeholder="Contoh: 192.168.1.100"
                          className="h-11 w-full rounded-2xl border border-[#1E2633] bg-[#0B0E14] px-4 text-xs sm:text-sm font-mono font-medium text-white placeholder:text-slate-500 focus:border-[#0073C6] focus:outline-none shadow-inner"
                        />
                        {errors.ip_address && <p className="text-xs text-rose-400 font-medium">{errors.ip_address}</p>}
                      </div>
                      <div className="space-y-2">
                        <label className="text-xs font-bold text-slate-300 block">
                          MAC Address
                        </label>
                        <input
                          type="text"
                          value={data.mac_address}
                          onChange={(e) => setData("mac_address", e.target.value)}
                          placeholder="AA:BB:CC:DD:EE:FF"
                          className="h-11 w-full rounded-2xl border border-[#1E2633] bg-[#0B0E14] px-4 text-xs sm:text-sm font-mono font-medium text-white placeholder:text-slate-500 focus:border-[#0073C6] focus:outline-none shadow-inner"
                        />
                      </div>
                      <div className="space-y-2">
                        <label className="text-xs font-bold text-slate-300 block">
                          Interface ARP
                        </label>
                        <select
                          value={data.arp_interface || "bridge"}
                          onChange={(e) => setData("arp_interface", e.target.value)}
                          className="h-11 w-full rounded-2xl border border-[#1E2633] bg-[#0B0E14] px-4 text-xs sm:text-sm font-mono font-medium text-white focus:border-[#0073C6] focus:outline-none cursor-pointer shadow-inner"
                        >
                          {["bridge", "ether1", "ether2", "ether3", "ether4", "ether5", "wlan1", "wlan2"].map((iface) => (
                            <option key={iface} value={iface} className="bg-[#121720] text-white">
                              {iface}
                            </option>
                          ))}
                        </select>
                      </div>
                    </div>
                  </div>
                )}
              </div>

              {/* 3. Pilihan Paket, Router, dan ODP */}
              <div className="space-y-4 pt-2 border-t border-[#1E2633]/80">
                <h3 className="text-xs font-black uppercase tracking-wider text-[#00C2FF] flex items-center gap-2">
                  <PackageIcon className="h-4 w-4" />
                  <span>Paket Langganan &amp; Infrastruktur</span>
                </h3>

                <div className="grid gap-4 sm:grid-cols-2">
                  <div className="space-y-2">
                    <label className="text-xs font-bold text-slate-300 block">
                      Paket Langganan Internet <span className="text-rose-400">*</span>
                    </label>
                    <div className="relative">
                      <select
                        required
                        value={data.package_id}
                        onChange={(e) => setData("package_id", e.target.value)}
                        className="h-11 w-full rounded-2xl border border-[#1E2633] bg-[#0B0E14] px-4 text-xs sm:text-sm font-medium text-white focus:border-[#0073C6] focus:bg-[#0E131C] focus:outline-none transition-all shadow-inner appearance-none cursor-pointer"
                      >
                        <option value="" className="bg-[#121720] text-white">Pilih paket internet...</option>
                        {packages.map((p: Package) => (
                          <option key={p.id} value={p.id} className="bg-[#121720] text-white">
                            {p.name} {p.price ? `(Rp ${p.price.toLocaleString("id-ID")})` : ""}
                          </option>
                        ))}
                      </select>
                      <div className="pointer-events-none absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 text-xs">
                        ▼
                      </div>
                    </div>
                    {errors.package_id && (
                      <p className="text-xs text-rose-400 font-medium">{errors.package_id}</p>
                    )}
                  </div>

                  <div className="space-y-2">
                    <label className="text-xs font-bold text-slate-300 block">
                      Router MikroTik Target
                    </label>
                    <div className="relative">
                      <select
                        value={data.router_id}
                        onChange={(e) => setData("router_id", e.target.value)}
                        className="h-11 w-full rounded-2xl border border-[#1E2633] bg-[#0B0E14] px-4 text-xs sm:text-sm font-medium text-white focus:border-[#0073C6] focus:bg-[#0E131C] focus:outline-none transition-all shadow-inner appearance-none cursor-pointer"
                      >
                        <option value="" className="bg-[#121720] text-white">Pilih router MikroTik...</option>
                        {routers.map((r: Router) => (
                          <option key={r.id} value={r.id} className="bg-[#121720] text-white">
                            {r.name} {r.host ? `(${r.host})` : ""}
                          </option>
                        ))}
                      </select>
                      <div className="pointer-events-none absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 text-xs">
                        ▼
                      </div>
                    </div>
                  </div>
                </div>

                {/* Box ODP & Port Splitter Selector */}
                <div className="grid gap-4 sm:grid-cols-2">
                  <div className="space-y-2">
                    <label className="text-xs font-bold text-slate-300 flex items-center gap-1.5">
                      <Radio className="h-4 w-4 text-amber-400" />
                      <span>Box ODP (Optical Distribution Point)</span>
                    </label>
                    <div className="relative">
                      <select
                        value={data.odp_id}
                        onChange={(e) => {
                          const newOdpId = e.target.value
                          setData("odp_id", newOdpId)
                          if (newOdpId) {
                            const selectedOdp = odps.find((o) => String(o.id) === newOdpId)
                            const cap = selectedOdp?.capacity || 8
                            const occupied = new Set<number>()
                            customers.forEach((c) => {
                              if (String(c.odp_id) === newOdpId && c.odp_port) {
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
                            setData("odp_port", firstFreePort)
                          } else {
                            setData("odp_port", "")
                          }
                        }}
                        className="h-11 w-full rounded-2xl border border-[#1E2633] bg-[#0B0E14] px-4 text-xs sm:text-sm font-medium text-white focus:border-[#0073C6] focus:outline-none appearance-none cursor-pointer"
                      >
                        <option value="" className="bg-[#121720] text-white">Pilih Box ODP (Opsional)...</option>
                        {odps.map((o) => (
                          <option key={o.id} value={String(o.id)} className="bg-[#121720] text-white">
                            {o.name} (Kapasitas {o.capacity} Port)
                          </option>
                        ))}
                      </select>
                      <div className="pointer-events-none absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 text-xs">
                        ▼
                      </div>
                    </div>
                  </div>

                  <div className="space-y-2">
                    <label className="text-xs font-bold text-slate-300 block">
                      Port ODP Digunakan
                    </label>
                    {data.odp_id ? (() => {
                      const selectedOdp = odps.find((o) => String(o.id) === String(data.odp_id))
                      const cap = selectedOdp?.capacity || 8
                      const isOccupied = occupiedPortsForOdp.has(Number(data.odp_port))
                      const takenBy = occupiedPortsForOdp.get(Number(data.odp_port))
                      const isTotallyFull = occupiedPortsForOdp.size >= cap

                      return (
                        <div className="space-y-1.5">
                          {isTotallyFull && (
                            <div className="p-2.5 rounded-xl bg-rose-950/40 border border-rose-500/40 text-rose-300 text-xs flex items-center gap-2">
                              <AlertTriangle className="h-4 w-4 shrink-0 text-rose-400" />
                              <span>Semua port pada ODP "{selectedOdp?.name}" sudah terisi penuh ({cap}/{cap} Port). Silakan pilih ODP lain.</span>
                            </div>
                          )}
                          <select
                            value={data.odp_port}
                            onChange={(e) => setData("odp_port", e.target.value)}
                            className={cn(
                              "h-11 w-full rounded-2xl border px-4 text-xs sm:text-sm font-medium text-white focus:outline-none",
                              isOccupied
                                ? "border-rose-500 bg-rose-950/30 text-rose-300 focus:border-rose-400"
                                : "border-[#1E2633] bg-[#0B0E14] focus:border-[#0073C6]"
                            )}
                          >
                            <option value="" className="bg-[#121720] text-white">-- Pilih Port ODP --</option>
                            {Array.from({ length: cap }, (_, i) => i + 1).map((p) => {
                              const occupiedBy = occupiedPortsForOdp.get(p)
                              return (
                                <option
                                  key={p}
                                  value={String(p)}
                                  disabled={!!occupiedBy}
                                  className={occupiedBy ? "bg-[#1a1114] text-rose-400" : "bg-[#121720] text-white"}
                                >
                                  Port {p} {occupiedBy ? `• [Terpakai: "${occupiedBy}"]` : "• (Tersedia)"}
                                </option>
                              )
                            })}
                          </select>
                          {isOccupied && (
                            <p className="text-[11px] text-rose-400 font-medium flex items-center gap-1.5">
                              <AlertTriangle className="h-3.5 w-3.5 shrink-0" />
                              <span>Port {data.odp_port} sudah digunakan oleh pelanggan "{takenBy}". Silakan klik port yang masih kosong di atas.</span>
                            </p>
                          )}
                        </div>
                      )
                    })() : (
                      <input
                        type="text"
                        disabled
                        placeholder="Pilih Box ODP terlebih dahulu"
                        className="h-11 w-full rounded-2xl border border-[#1E2633] bg-[#0B0E14]/50 px-4 text-xs text-slate-500 cursor-not-allowed"
                      />
                    )}
                  </div>
                </div>
              </div>

              {/* 4. Lokasi & GPS Geolocation */}
              <div className="space-y-4 pt-2 border-t border-[#1E2633]/80">
                <div className="flex items-center justify-between gap-2">
                  <h3 className="text-xs font-black uppercase tracking-wider text-[#00C2FF] flex items-center gap-2">
                    <MapPin className="h-4 w-4" />
                    <span>Lokasi &amp; Koordinat GPS</span>
                  </h3>
                  <button
                    type="button"
                    onClick={handleGetDeviceLocation}
                    disabled={isGettingGps}
                    className="flex items-center gap-1.5 rounded-xl border border-[#00C2FF]/30 bg-[#00C2FF]/10 px-3 py-1.5 text-xs font-bold text-[#00C2FF] hover:bg-[#00C2FF]/20 active:scale-95 transition-all cursor-pointer"
                  >
                    <Navigation className={cn("h-3.5 w-3.5", isGettingGps && "animate-spin text-amber-400")} />
                    <span>{isGettingGps ? "Mencari GPS..." : "Ambil GPS Saya"}</span>
                  </button>
                </div>

                {gpsError && (
                  <div className="flex items-center gap-2 rounded-xl border border-rose-500/30 bg-rose-500/10 p-3 text-xs text-rose-300">
                    <AlertTriangle className="h-4 w-4 shrink-0" />
                    <span>{gpsError}</span>
                  </div>
                )}

                <div className="grid gap-4 sm:grid-cols-2">
                  <div className="space-y-2">
                    <label className="text-xs font-bold text-slate-300 block">
                      Latitude
                    </label>
                    <input
                      type="text"
                      value={data.lat}
                      onChange={(e) => setData("lat", e.target.value)}
                      placeholder="-7.123456"
                      className="h-11 w-full rounded-2xl border border-[#1E2633] bg-[#0B0E14] px-4 text-xs sm:text-sm font-mono font-medium text-white placeholder:text-slate-500 focus:border-[#0073C6] focus:outline-none shadow-inner"
                    />
                  </div>
                  <div className="space-y-2">
                    <label className="text-xs font-bold text-slate-300 block">
                      Longitude
                    </label>
                    <input
                      type="text"
                      value={data.lng}
                      onChange={(e) => setData("lng", e.target.value)}
                      placeholder="112.123456"
                      className="h-11 w-full rounded-2xl border border-[#1E2633] bg-[#0B0E14] px-4 text-xs sm:text-sm font-mono font-medium text-white placeholder:text-slate-500 focus:border-[#0073C6] focus:outline-none shadow-inner"
                    />
                  </div>
                </div>

                <div className="space-y-2">
                  <label className="text-xs font-bold text-slate-300 block">
                    Alamat Lengkap / Patokan Lokasi
                  </label>
                  <div className="relative">
                    <MapPin className="pointer-events-none absolute left-3.5 top-3.5 h-4 w-4 text-slate-500" />
                    <textarea
                      rows={3}
                      value={data.address}
                      onChange={(e) => setData("address", e.target.value)}
                      placeholder="Masukkan alamat rumah, nomor rumah, RT/RW, atau patokan lokasi pelanggan"
                      className="w-full rounded-2xl border border-[#1E2633] bg-[#0B0E14] pl-10 pr-4 py-3 text-xs sm:text-sm font-medium text-white placeholder:text-slate-500 focus:border-[#0073C6] focus:bg-[#0E131C] focus:outline-none transition-all shadow-inner"
                    />
                  </div>
                </div>
              </div>

              {/* 5. Checkbox Otomatisasi MikroTik */}
              {data.connection_type === "pppoe" && (
                <div
                  onClick={() => setData("create_pppoe", !data.create_pppoe)}
                  className="flex items-start gap-3 rounded-2xl border border-[#0073C6]/40 bg-[#0B2138]/40 p-4 cursor-pointer select-none hover:bg-[#0B2138]/60 transition-all"
                >
                  <div
                    role="checkbox"
                    aria-checked={data.create_pppoe}
                    className={cn(
                      "flex h-5 w-5 shrink-0 items-center justify-center rounded-lg border mt-0.5 transition-all",
                      data.create_pppoe
                        ? "border-[#0073C6] bg-[#0073C6] text-white shadow-[#0073C6]/40"
                        : "border-slate-700 bg-slate-800 text-transparent"
                    )}
                  >
                    <CheckCircle2 className="h-3.5 w-3.5" />
                  </div>
                  <div className="min-w-0 flex-1">
                    <div className="text-xs sm:text-sm font-bold text-white flex items-center gap-1.5">
                      <span>Buat Akun Secret PPPoE Otomatis di MikroTik</span>
                      <ShieldCheck className="h-4 w-4 text-[#00C2FF]" />
                    </div>
                    <p className="text-[11px] text-slate-400 font-medium mt-0.5 leading-snug">
                      Sistem akan langsung menyinkronkan username dan password PPPoE ke MikroTik router yang dipilih.
                    </p>
                  </div>
                </div>
              )}

              {/* Submit Button */}
              <button
                type="submit"
                disabled={processing}
                className="h-12 w-full rounded-2xl bg-[#0073C6] hover:bg-[#0084E3] text-white text-xs sm:text-sm font-bold shadow-[#0073C6]/25 active:scale-[0.98] transition-all flex items-center justify-center gap-2 disabled:opacity-50 cursor-pointer"
              >
                <UserPlus className="h-4 w-4" />
                <span>{processing ? "Menyimpan Pelanggan..." : "Simpan & Daftarkan Pelanggan"}</span>
              </button>
            </form>
          </div>
        </div>
      </div>
    </AppLayout>
  )
}
