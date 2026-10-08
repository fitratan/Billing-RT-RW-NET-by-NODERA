import { AppLayout } from "@/components/layout/app-layout"
import {
  Briefcase,
  Plus,
  Pencil,
  Phone,
  Trash2,
  Copy,
  Check,
  ShieldCheck,
  Wrench,
  Wallet,
  ChevronDown,
  RefreshCw,
  Clock,
  User,
  Users,
  MessageCircle,
  Radio,
  X,
  SlidersHorizontal,
  KeyRound,
  MapPin,
  UserPlus,
  Receipt,
  Activity,
  CheckCircle2,
  Search,
  Network,
  Settings,
  ChevronRight,
} from "lucide-react"
import { EmptyState } from "@/components/ui/empty-state"
import { PageProps } from "@/types"
import { formatIDR, cn } from "@/lib/utils"
import { adminSidebarItems, adminNavItems, adminBrand } from "@/lib/admin-nav"
import { useForm, router } from "@inertiajs/react"
import { useState, useMemo } from "react"
import { MetricCard } from "@/components/tailadmin/MetricCard"
import { ViewModeSwitcher, type ViewMode } from "@/components/tailadmin/ui/view-mode-switcher"

interface Employee {
  id: number | string
  name: string
  username?: string
  phone: string | null
  email: string | null
  role?: string
  is_active?: boolean
  status: string
  position: string | null
  salary: number
  collection_area?: string
  commission_type?: string
  commission_value?: number
  router_id?: number | null
  permissions?: Record<string, boolean>
}

interface LoginUrls {
  collector: string
  technician: string
  admin: string
}

interface RolePermissionsData {
  technician: Record<string, boolean>
  collector: Record<string, boolean>
}

const ALL_EMPLOYEE_PERMISSIONS = [
  // Fitur Pelanggan & Penagihan
  {
    key: "customers",
    category: "Pelanggan",
    title: "Kelola & Edit Data Pelanggan",
    desc: "Melihat direktori seluruh pelanggan, ubah paket, ganti ODP, dan reset PIN pelanggan",
    icon: Users,
  },
  {
    key: "collect_payment",
    category: "Penagihan",
    title: "Terima Pembayaran POS & Cetak Struk",
    desc: "Menerima setoran tagihan tunai pelanggan dan cetak struk Bluetooth",
    icon: Receipt,
  },
  {
    key: "dashboard",
    category: "Penagihan",
    title: "Daftar Tagihan & Pelanggan",
    desc: "Melihat daftar tagihan jatuh tempo & direktori pelanggan di area penagihan",
    icon: Users,
  },
  {
    key: "top_bandwidth",
    category: "Penagihan",
    title: "Akses Realtime Top Bandwidth",
    desc: "Melihat pemakaian kuota dan statistik bandwidth pelanggan saat berkunjung",
    icon: Activity,
  },

  // Fitur Lapangan & Teknisi
  {
    key: "pool",
    category: "Teknisi",
    title: "Pool Pekerjaan & Tiket Gangguan",
    desc: "Melihat antrean tiket pemasangan baru & perbaikan gangguan lapangan",
    icon: Briefcase,
  },
  {
    key: "create_customer",
    category: "Teknisi",
    title: "Tambah / Registrasi Pelanggan Baru",
    desc: "Mendaftarkan pelanggan baru beserta paket dan titik koordinat GPS",
    icon: UserPlus,
  },
  {
    key: "history",
    category: "Teknisi",
    title: "Riwayat Pekerjaan & Transaksi Selesai",
    desc: "Melihat arsip tiket gangguan, instalasi, dan transaksi pembayaran yang sudah tuntas",
    icon: Clock,
  },

  // Fitur Jaringan & Umum
  {
    key: "pppoe",
    category: "Jaringan",
    title: "Monitoring PPPoE & Redaman Optik",
    desc: "Melihat status koneksi PPPoE, IP, dan telemetri redaman fiber/ONT",
    icon: KeyRound,
  },
  {
    key: "arp",
    category: "Jaringan",
    title: "Monitoring ARP & Static Binding",
    desc: "Melihat tabel host ARP MikroTik, binding IP-MAC statis, dan ping diagnostik",
    icon: Network,
  },
  {
    key: "isolate",
    category: "Jaringan",
    title: "Buka / Isolir Layanan",
    desc: "Izin mengubah status isolir atau buka blokir pelanggan langsung dari HP",
    icon: ShieldCheck,
  },
  {
    key: "map",
    category: "Jaringan",
    title: "Peta Jaringan & GIS (ODP / Kabel)",
    desc: "Melihat peta sebaran box ODP & rute kabel fiber di lapangan",
    icon: MapPin,
  },
  {
    key: "earnings",
    category: "Keuangan",
    title: "Laporan Pendapatan & Komisi",
    desc: "Melihat catatan insentif tagihan, komisi instalasi, dan rekap pendapatan",
    icon: Wallet,
  },
]

export default function EmployeesPage({
  employees = [],
  create = false,
  loginUrls,
  routers = [],
  companyName = "NODERA Billing",
  tenantName,
  rolePermissions,
}: PageProps<{
  employees: Employee[]
  create: boolean
  loginUrls?: LoginUrls
  routers?: { id: number; name: string }[]
  companyName?: string
  tenantName?: string
  rolePermissions?: RolePermissionsData
}>) {
  const [expandedIds, setExpandedIds] = useState<Record<number | string, boolean>>({})
  const [modalOpen, setModalOpen] = useState(create ?? false)
  const [managingEmployee, setManagingEmployee] = useState<Employee | null>(null)
  const [editing, setEditing] = useState<Employee | null>(null)
  const [viewMode, setViewMode] = useState<ViewMode>("table")

  // Filter States
  const [searchQuery, setSearchQuery] = useState("")
  const [roleFilter, setRoleFilter] = useState<string>("all")
  const [statusFilter, setStatusFilter] = useState<string>("all")

  // Delete Confirmation State
  const [deleteEmployeeTarget, setDeleteEmployeeTarget] = useState<Employee | null>(null)
  const [isDeletingEmployee, setIsDeletingEmployee] = useState(false)
  const [copiedId, setCopiedId] = useState<string | number | null>(null)

  // Per-Employee Permissions State
  const [permModalEmployee, setPermModalEmployee] = useState<Employee | null>(null)
  const [employeePerms, setEmployeePerms] = useState<Record<string, boolean>>({})
  const [isSavingEmployeePerms, setIsSavingEmployeePerms] = useState(false)

  const toggleExpand = (id: number | string) => {
    setExpandedIds((prev) => ({ ...prev, [id]: !prev[id] }))
  }

  const openEmployeePerms = (e: Employee) => {
    setPermModalEmployee(e)
    const defaults: Record<string, boolean> = {}
    ALL_EMPLOYEE_PERMISSIONS.forEach((p) => {
      defaults[p.key] = true
    })
    setEmployeePerms(e.permissions ? { ...defaults, ...e.permissions } : defaults)
  }

  const toggleEmpPerm = (key: string) => {
    setEmployeePerms((prev) => ({ ...prev, [key]: !prev[key] }))
  }

  const saveEmployeePermsSubmit = (employeeId?: string | number) => {
    const targetId = employeeId || permModalEmployee?.id
    if (!targetId) return
    setIsSavingEmployeePerms(true)
    router.post(
      `/admin/employees/permissions/${targetId}`,
      { permissions: employeePerms },
      {
        preserveScroll: true,
        onFinish: () => {
          setIsSavingEmployeePerms(false)
          setPermModalEmployee(null)
        },
      }
    )
  }

  const handleDeleteEmployee = () => {
    if (!deleteEmployeeTarget) return
    setIsDeletingEmployee(true)
    router.post(
      `/admin/employees/delete/${deleteEmployeeTarget.id}`,
      {},
      {
        preserveScroll: true,
        onFinish: () => {
          setIsDeletingEmployee(false)
          setDeleteEmployeeTarget(null)
        },
      }
    )
  }

  const handleCopyLoginUrl = (role?: string, id?: string | number) => {
    const url =
      role === "collector"
        ? loginUrls?.collector || window.location.origin + "/kolektor/login"
        : loginUrls?.technician || window.location.origin + "/teknisi/login"
    navigator.clipboard.writeText(url)
    setCopiedId(id || role || "copied")
    setTimeout(() => setCopiedId(null), 2000)
  }

  const techCount = useMemo(
    () => employees.filter((e) => e.role === "technician" || e.position?.toLowerCase().includes("teknisi")).length,
    [employees]
  )
  const collectorCount = useMemo(
    () => employees.filter((e) => e.role === "collector" || e.position?.toLowerCase().includes("kolektor")).length,
    [employees]
  )
  const activeCount = useMemo(
    () => employees.filter((e) => e.is_active !== false).length,
    [employees]
  )

  const filteredEmployees = useMemo(() => {
    return employees.filter((e) => {
      const isTech = e.role === "technician" || e.position?.toLowerCase().includes("teknisi")
      const isColl = e.role === "collector" || e.position?.toLowerCase().includes("kolektor")
      const isAdmin = e.role === "admin" || (!e.role && !e.position)

      if (roleFilter === "technician" && !isTech) return false
      if (roleFilter === "collector" && !isColl) return false
      if (roleFilter === "admin" && !isAdmin) return false

      const isActive = e.is_active !== false
      if (statusFilter === "active" && !isActive) return false
      if (statusFilter === "inactive" && isActive) return false

      if (searchQuery.trim()) {
        const q = searchQuery.toLowerCase().trim()
        const matchName = (e.name ?? "").toLowerCase().includes(q)
        const matchUser = (e.username ?? "").toLowerCase().includes(q)
        const matchPhone = (e.phone ?? "").toLowerCase().includes(q)
        const matchArea = (e.collection_area ?? "").toLowerCase().includes(q)
        if (!matchName && !matchUser && !matchPhone && !matchArea) return false
      }

      return true
    })
  }, [employees, searchQuery, roleFilter, statusFilter])

  const form = useForm<{
    name: string
    username: string
    password: string
    phone: string
    email: string
    role: "technician" | "collector" | "cashier" | "admin"
    is_active: string
    router_id: string
    collection_area: string
    commission_type: "fixed" | "percent"
    commission_value: string
    permissions: Record<string, boolean>
  }>({
    name: "",
    username: "",
    password: "",
    phone: "",
    email: "",
    role: "technician",
    is_active: "1",
    router_id: "",
    collection_area: "",
    commission_type: "fixed",
    commission_value: "0",
    permissions: {},
  })

  const openAdd = () => {
    setEditing(null)
    const defaults: Record<string, boolean> = {}
    ALL_EMPLOYEE_PERMISSIONS.forEach((p) => {
      defaults[p.key] = true
    })
    form.setData({
      name: "",
      username: "",
      password: "",
      phone: "",
      email: "",
      role: "technician",
      is_active: "1",
      router_id: "",
      collection_area: "",
      commission_type: "fixed",
      commission_value: "0",
      permissions: defaults,
    })
    setModalOpen(true)
  }

  const openEdit = (e: Employee) => {
    setEditing(e)
    const defaults: Record<string, boolean> = {}
    ALL_EMPLOYEE_PERMISSIONS.forEach((p) => {
      defaults[p.key] = true
    })
    form.setData({
      name: e.name,
      username: e.username ?? "",
      password: "",
      phone: e.phone ?? "",
      email: e.email ?? "",
      role: (e.role as any) ?? "technician",
      is_active: e.is_active === false ? "0" : "1",
      router_id: e.router_id ? String(e.router_id) : "",
      collection_area: e.collection_area ?? "",
      commission_type: (e.commission_type as any) ?? "fixed",
      commission_value: String(e.commission_value ?? 0),
      permissions: e.permissions ? { ...defaults, ...e.permissions } : defaults,
    })
    setModalOpen(true)
  }

  const submit = (e: React.FormEvent) => {
    e.preventDefault()
    if (editing) {
      form.post(`/admin/employees/edit/${editing.id}`, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => setModalOpen(false),
      })
    } else {
      form.post("/admin/employees/add", {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => setModalOpen(false),
      })
    }
  }

  return (
    <AppLayout
      title="Karyawan & Staff"
      brand={adminBrand}
      sidebarItems={adminSidebarItems}
      navItems={adminNavItems}
    >
      <div className="space-y-6">
        {/* ── 4 TOP KPI METRICS ── */}
        <div className="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
          <MetricCard
            title="Total Karyawan"
            value={`${employees.length} User`}
            icon={<Users className="h-6 w-6 text-blue-500" />}
            sub="Semua staf terdaftar"
            onClick={() => {
              setRoleFilter("all")
              setStatusFilter("all")
            }}
            isActive={roleFilter === "all" && statusFilter === "all"}
          />

          <MetricCard
            title="Teknisi Lapangan"
            value={`${techCount} User`}
            icon={<Wrench className="h-6 w-6 text-cyan-500" />}
            sub="Instalasi & tiket gangguan"
            onClick={() => {
              setRoleFilter(roleFilter === "technician" ? "all" : "technician")
            }}
            isActive={roleFilter === "technician"}
          />

          <MetricCard
            title="Kolektor Kasir"
            value={`${collectorCount} User`}
            icon={<Wallet className="h-6 w-6 text-purple-500" />}
            sub="Penagihan & POS door-to-door"
            onClick={() => {
              setRoleFilter(roleFilter === "collector" ? "all" : "collector")
            }}
            isActive={roleFilter === "collector"}
          />

          <MetricCard
            title="Akun Aktif"
            value={`${activeCount} User`}
            icon={<CheckCircle2 className="h-6 w-6 text-emerald-500" />}
            sub="Status akun operasional"
            onClick={() => {
              setStatusFilter(statusFilter === "active" ? "all" : "active")
            }}
            isActive={statusFilter === "active"}
          />
        </div>

        {/* ── 1-LINE RESPONSIVE INTEGRATED TOOLBAR ── */}
        <div className="rounded-2xl border border-gray-200 bg-white p-3 sm:p-4 shadow-xs dark:border-gray-800 dark:bg-white/[0.03] flex flex-col gap-2.5 sm:gap-3 lg:flex-row lg:items-center lg:justify-between">
          {/* Left side: Search Bar & Role/Status Filters */}
          <div className="flex flex-wrap items-center gap-2 sm:gap-3 flex-1 min-w-0">
            <div className="relative flex-1 min-w-[180px] sm:min-w-[220px] w-full sm:w-auto">
              <Search className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-gray-400" />
              <input
                type="text"
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                placeholder="Cari nama, username, phone, atau wilayah..."
                className="h-10 w-full rounded-xl border border-gray-200 bg-gray-50/75 dark:border-gray-800 dark:bg-gray-900/50 pl-9 pr-8 text-xs font-medium text-gray-900 dark:text-white placeholder:text-gray-400 focus:border-brand-500 focus:outline-hidden"
              />
              {searchQuery && (
                <button
                  type="button"
                  onClick={() => setSearchQuery("")}
                  className="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-white"
                >
                  <X className="size-3.5" />
                </button>
              )}
            </div>

            {/* Role & Status Filters */}
            <div className="flex items-center gap-2 w-full sm:w-auto flex-1 sm:flex-none">
              <select
                value={roleFilter}
                onChange={(e) => setRoleFilter(e.target.value)}
                className="h-10 rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 px-3 text-xs font-semibold text-gray-700 dark:text-gray-200 cursor-pointer flex-1 sm:flex-none sm:w-auto shrink-0 focus:border-brand-500 focus:outline-hidden"
              >
                <option value="all">Semua Peran</option>
                <option value="technician">Teknisi Lapangan</option>
                <option value="collector">Kolektor / POS</option>
                <option value="admin">Admin Operasional</option>
              </select>

              <select
                value={statusFilter}
                onChange={(e) => setStatusFilter(e.target.value)}
                className="h-10 rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 px-3 text-xs font-semibold text-gray-700 dark:text-gray-200 cursor-pointer flex-1 sm:flex-none sm:w-auto shrink-0 focus:border-brand-500 focus:outline-hidden"
              >
                <option value="all">Semua Status</option>
                <option value="active">Aktif</option>
                <option value="inactive">Nonaktif</option>
              </select>
            </div>
          </div>

          {/* Right side: ViewModeSwitcher + Tambah Karyawan */}
          <div className="flex items-center gap-2 shrink-0 w-full lg:w-auto">
            <ViewModeSwitcher
              value={viewMode}
              onChange={setViewMode}
              storageKey="nodera_employees_view_mode"
              className="shrink-0"
            />
            <button
              type="button"
              onClick={openAdd}
              className="h-10 px-3.5 sm:px-4 rounded-xl bg-brand-500 hover:bg-brand-600 active:scale-95 text-xs font-bold text-white shadow-xs flex items-center justify-center gap-1.5 flex-1 sm:flex-none cursor-pointer transition"
            >
              <Plus className="size-4" />
              <span>Tambah Karyawan</span>
            </button>
          </div>
        </div>

        {/* ── MASTER CARD CONTAINER ── */}
        <div className="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xs dark:border-gray-800 dark:bg-white/[0.03]">

          {/* ── TABLE VIEW / GRID VIEW ── */}
          {filteredEmployees.length === 0 ? (
            <div className="p-8 text-center">
              <EmptyState
                icon={<Briefcase className="h-8 w-8 text-brand-500" />}
                title="Tidak ada karyawan yang cocok"
                description="Coba ubah kata kunci pencarian atau ubah filter peranan di atas."
              />
            </div>
          ) : viewMode === "table" ? (
            /* TABLE MODE */
            <div className="w-full overflow-x-auto custom-scrollbar">
              <table className="w-full text-left text-xs text-gray-600 dark:text-gray-300 min-w-[950px] border-collapse">
                <thead className="border-b border-gray-200 bg-gray-50/75 text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:border-gray-800 dark:bg-gray-900/50 dark:text-gray-400">
                  <tr>
                    <th className="px-4 py-3.5">Petugas</th>
                    <th className="px-4 py-3.5">Peran / Role</th>
                    <th className="px-4 py-3.5">No. WhatsApp</th>
                    <th className="px-4 py-3.5">Area & Router</th>
                    <th className="px-4 py-3.5">Gaji / Komisi</th>
                    <th className="px-4 py-3.5">Status</th>
                    <th className="px-4 py-3.5 text-right">Aksi</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-gray-100 dark:divide-gray-800">
                  {filteredEmployees.map((e) => {
                    const isTech = e.role === "technician" || e.position?.toLowerCase().includes("teknisi")
                    const isColl = e.role === "collector" || e.position?.toLowerCase().includes("kolektor")
                    const isActive = e.is_active !== false

                    return (
                      <tr key={e.id} className="hover:bg-gray-50/50 dark:hover:bg-white/[0.02] transition">
                        {/* Petugas Column */}
                        <td className="px-4 py-3.5">
                          <div className="flex items-center gap-3">
                            <div className="relative flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-gray-100 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 font-bold text-xs text-gray-700 dark:text-gray-200">
                              <span>{e.name.slice(0, 2).toUpperCase()}</span>
                              <span
                                className={cn(
                                  "absolute -bottom-0.5 -right-0.5 h-2.5 w-2.5 rounded-full ring-2 ring-white dark:ring-gray-900",
                                  isActive ? "bg-emerald-500" : "bg-rose-500"
                                )}
                              />
                            </div>
                            <div className="min-w-0">
                              <div className="font-bold text-gray-900 dark:text-white truncate">
                                {e.name}
                              </div>
                              <div className="font-mono text-[11px] text-gray-500 dark:text-gray-400 truncate">
                                @{e.username || "user"}
                              </div>
                            </div>
                          </div>
                        </td>

                        {/* Peran Column */}
                        <td className="px-4 py-3.5">
                          {isTech ? (
                            <span className="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-bold bg-brand-500 text-white shadow-xs whitespace-nowrap">
                              <Wrench className="h-3 w-3" /> Teknisi
                            </span>
                          ) : isColl ? (
                            <span className="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-bold bg-purple-600 text-white shadow-xs whitespace-nowrap">
                              <Wallet className="h-3 w-3" /> Kolektor
                            </span>
                          ) : (
                            <span className="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-bold bg-amber-500 text-white shadow-xs whitespace-nowrap">
                              <User className="h-3 w-3" /> Admin
                            </span>
                          )}
                        </td>

                        {/* No WhatsApp Column */}
                        <td className="px-4 py-3.5">
                          <div className="flex items-center gap-1.5 font-mono text-gray-700 dark:text-gray-300">
                            <Phone className="h-3.5 w-3.5 text-gray-400" />
                            <span>{e.phone || "-"}</span>
                          </div>
                        </td>

                        {/* Area & Router Column */}
                        <td className="px-4 py-3.5">
                          <div className="space-y-0.5 text-[11px] text-gray-500 dark:text-gray-400">
                            <div className="flex items-center gap-1">
                              <MapPin className="h-3 w-3 text-gray-400" />
                              <span>{e.collection_area || "Semua Area"}</span>
                            </div>
                            <div className="text-brand-600 dark:text-brand-400 font-medium">
                              {routers.find((r) => r.id === e.router_id)?.name || "Semua Router"}
                            </div>
                          </div>
                        </td>

                        {/* Gaji / Komisi Column */}
                        <td className="px-4 py-3.5">
                          <div>
                            {e.salary > 0 ? (
                              <div className="font-semibold text-emerald-600 dark:text-emerald-400">
                                {formatIDR(e.salary)}
                              </div>
                            ) : (
                              <span className="text-gray-400">-</span>
                            )}
                            {e.commission_value && e.commission_value > 0 ? (
                              <div className="text-[11px] text-brand-600 dark:text-brand-400 font-medium">
                                Komisi: {e.commission_type === "percent" ? `${e.commission_value}%` : formatIDR(e.commission_value)}
                              </div>
                            ) : null}
                          </div>
                        </td>

                        {/* Status Column */}
                        <td className="px-4 py-3.5">
                          {isActive ? (
                            <span className="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-bold bg-emerald-500 text-white shadow-xs whitespace-nowrap">
                              Aktif
                            </span>
                          ) : (
                            <span className="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-bold bg-rose-500 text-white shadow-xs whitespace-nowrap">
                              Nonaktif
                            </span>
                          )}
                        </td>

                        {/* Single Action Button: Kelola */}
                        <td className="px-4 py-3.5 text-right whitespace-nowrap">
                          <button
                            type="button"
                            onClick={() => setManagingEmployee(e)}
                            className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200 font-bold text-xs transition cursor-pointer"
                            title="Kelola Karyawan"
                          >
                            <Settings className="h-3.5 w-3.5" />
                            <span>Kelola</span>
                          </button>
                        </td>
                      </tr>
                    )
                  })}
                </tbody>
              </table>
            </div>
          ) : (
            /* GRID / CARD MODE */
            <div className="p-4 sm:p-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
              {filteredEmployees.map((e) => {
                const isTech = e.role === "technician" || e.position?.toLowerCase().includes("teknisi")
                const isColl = e.role === "collector" || e.position?.toLowerCase().includes("kolektor")
                const isActive = e.is_active !== false
                const isExpanded = !!expandedIds[e.id]

                return (
                  <div
                    key={e.id}
                    onClick={() => toggleExpand(e.id)}
                    className={cn(
                      "rounded-2xl border bg-white dark:bg-gray-900/40 p-4 transition shadow-xs cursor-pointer select-none space-y-3",
                      isExpanded
                        ? "border-brand-500 ring-1 ring-brand-500/20"
                        : "border-gray-200 dark:border-gray-800 hover:border-gray-300 dark:hover:border-gray-700"
                    )}
                  >
                    {/* Top Header Card */}
                    <div className="flex items-start justify-between gap-2.5">
                      <div className="flex items-center gap-3 min-w-0 flex-1">
                        <div className="relative flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gray-100 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 font-bold text-xs text-gray-700 dark:text-gray-200">
                          <span>{e.name.slice(0, 2).toUpperCase()}</span>
                          <span
                            className={cn(
                              "absolute -bottom-0.5 -right-0.5 h-2.5 w-2.5 rounded-full ring-2 ring-white dark:ring-gray-900",
                              isActive ? "bg-emerald-500" : "bg-rose-500"
                            )}
                          />
                        </div>

                        <div className="min-w-0 flex-1">
                          <h4 className="font-bold text-sm text-gray-900 dark:text-white truncate">
                            {e.name}
                          </h4>
                          <div className="mt-0.5 flex items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400 truncate">
                            <span className="font-mono">@{e.username || "user"}</span>
                            <span>•</span>
                            <span className="font-medium text-brand-600 dark:text-brand-400">
                              {isTech ? "Teknisi" : isColl ? "Kolektor" : "Admin"}
                            </span>
                          </div>
                        </div>
                      </div>

                      <div className="flex items-center gap-1 shrink-0">
                        {isActive ? (
                          <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold bg-emerald-500 text-white shadow-xs whitespace-nowrap">
                            Aktif
                          </span>
                        ) : (
                          <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold bg-rose-500 text-white shadow-xs whitespace-nowrap">
                            Off
                          </span>
                        )}
                        <div className="p-1 text-gray-400">
                          <ChevronDown
                            className={cn(
                              "h-4 w-4 transition-transform duration-200",
                              isExpanded && "rotate-180 text-brand-500"
                            )}
                          />
                        </div>
                      </div>
                    </div>

                    {/* Sunken Box (Wilayah & WhatsApp) */}
                    <div className="rounded-xl border border-gray-100 dark:border-gray-800 bg-gray-50/75 dark:bg-gray-900/60 px-3 py-2 flex items-center justify-between text-xs">
                      <div className="flex items-center gap-1.5 min-w-0">
                        <Phone className="h-3.5 w-3.5 shrink-0 text-gray-400" />
                        <span className="font-mono text-gray-700 dark:text-gray-300 truncate">
                          {e.phone || "-"}
                        </span>
                      </div>
                      <div className="flex items-center gap-1 shrink-0 ml-2">
                        <MapPin className="h-3 w-3 text-gray-400" />
                        <span className="text-[11px] font-medium text-gray-600 dark:text-gray-300">
                          {e.collection_area || "Semua Area"}
                        </span>
                      </div>
                    </div>

                    {/* Expanded Details */}
                    {isExpanded && (
                      <div
                        className="pt-2 border-t border-gray-100 dark:border-gray-800 space-y-2 text-xs"
                        onClick={(ev) => ev.stopPropagation()}
                      >
                        <div className="flex items-center justify-between py-1 text-gray-600 dark:text-gray-300">
                          <span className="text-gray-400 flex items-center gap-1.5">
                            <Radio className="h-3.5 w-3.5 text-gray-400" /> Router:
                          </span>
                          <span className="font-medium text-brand-600 dark:text-brand-400">
                            {routers.find((r) => r.id === e.router_id)?.name || "Semua Router"}
                          </span>
                        </div>

                        {e.salary > 0 && (
                          <div className="flex items-center justify-between py-1 text-gray-600 dark:text-gray-300">
                            <span className="text-gray-400 flex items-center gap-1.5">
                              <Wallet className="h-3.5 w-3.5 text-gray-400" /> Gaji Pokok:
                            </span>
                            <span className="font-bold text-emerald-600 dark:text-emerald-400">
                              {formatIDR(e.salary)}
                            </span>
                          </div>
                        )}
                      </div>
                    )}

                    {/* Single Action Button: Kelola */}
                    <div className="pt-2 border-t border-gray-100 dark:border-gray-800">
                      <button
                        type="button"
                        onClick={(ev) => {
                          ev.stopPropagation()
                          setManagingEmployee(e)
                        }}
                        className="w-full inline-flex items-center justify-center gap-1.5 px-3 py-1.5 rounded-lg bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200 font-bold text-xs transition cursor-pointer"
                      >
                        <Settings className="h-3.5 w-3.5" />
                        <span>Kelola Karyawan</span>
                      </button>
                    </div>
                  </div>
                )
              })}
            </div>
          )}
        </div>
      </div>

      {/* ── MODAL INTERAKTIF: KELOLA KARYAWAN ── */}
      {managingEmployee && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-xs">
          <div className="w-full max-w-md rounded-2xl border border-gray-200 bg-white p-5 shadow-2xl dark:border-gray-800 dark:bg-gray-900 space-y-4 max-h-[92vh] overflow-y-auto custom-scrollbar">
            {/* Header */}
            <div className="flex items-center justify-between border-b border-gray-100 pb-3 dark:border-gray-800">
              <div className="flex items-center gap-3">
                <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-brand-500 text-white shadow-xs font-bold text-sm">
                  {managingEmployee.name.slice(0, 2).toUpperCase()}
                </div>
                <div className="min-w-0">
                  <h3 className="font-bold text-sm text-gray-900 dark:text-white truncate">
                    Kelola: {managingEmployee.name}
                  </h3>
                  <span className="text-[11px] text-gray-500 font-mono">
                    @{managingEmployee.username || "user"}
                  </span>
                </div>
              </div>
              <button
                type="button"
                onClick={() => setManagingEmployee(null)}
                className="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-800 dark:hover:text-gray-200 transition cursor-pointer"
              >
                <X className="h-5 w-5" />
              </button>
            </div>

            {/* Quick Summary Card */}
            <div className="rounded-xl border border-gray-200/80 bg-gray-50/70 p-3.5 dark:border-gray-800 dark:bg-gray-800/40 space-y-2 text-xs">
              <div className="flex items-center justify-between">
                <span className="text-gray-500 dark:text-gray-400">Status Akun</span>
                {managingEmployee.is_active !== false ? (
                  <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold bg-emerald-500 text-white shadow-xs whitespace-nowrap">
                    Aktif
                  </span>
                ) : (
                  <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold bg-rose-500 text-white shadow-xs whitespace-nowrap">
                    Nonaktif
                  </span>
                )}
              </div>
              <div className="flex items-center justify-between">
                <span className="text-gray-500 dark:text-gray-400">Peran / Role</span>
                {managingEmployee.role === "technician" || managingEmployee.position?.toLowerCase().includes("teknisi") ? (
                  <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-bold bg-brand-500 text-white shadow-xs whitespace-nowrap">
                    <Wrench className="h-3 w-3" /> Teknisi Lapangan
                  </span>
                ) : managingEmployee.role === "collector" || managingEmployee.position?.toLowerCase().includes("kolektor") ? (
                  <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-bold bg-purple-500 text-white shadow-xs whitespace-nowrap">
                    <Wallet className="h-3 w-3" /> Kolektor Kasir
                  </span>
                ) : (
                  <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-bold bg-amber-500 text-white shadow-xs whitespace-nowrap">
                    <User className="h-3 w-3" /> Admin Staff
                  </span>
                )}
              </div>
              {managingEmployee.phone && (
                <div className="flex items-center justify-between">
                  <span className="text-gray-500 dark:text-gray-400">No. WhatsApp</span>
                  <span className="font-mono font-semibold text-gray-800 dark:text-gray-200">{managingEmployee.phone}</span>
                </div>
              )}
              <div className="flex items-center justify-between">
                <span className="text-gray-500 dark:text-gray-400">Wilayah / Router</span>
                <span className="font-medium text-gray-800 dark:text-gray-200">
                  {managingEmployee.collection_area || "Semua Area"} • {routers.find((r) => r.id === managingEmployee.router_id)?.name || "Semua Router"}
                </span>
              </div>
              {managingEmployee.salary > 0 && (
                <div className="flex items-center justify-between">
                  <span className="text-gray-500 dark:text-gray-400">Gaji Pokok</span>
                  <span className="font-mono font-bold text-emerald-600 dark:text-emerald-400">{formatIDR(managingEmployee.salary)}</span>
                </div>
              )}
            </div>

            {/* Action Buttons List */}
            <div className="space-y-2 pt-1 text-xs">
              {managingEmployee.phone && (
                <a
                  href={`https://wa.me/${managingEmployee.phone.replace(/[^0-9]/g, "").replace(/^0/, "62")}`}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="w-full group flex items-center justify-between p-3 rounded-xl bg-gray-50/70 hover:bg-emerald-50/50 border border-gray-100 hover:border-emerald-200 dark:bg-gray-800/40 dark:hover:bg-gray-800 dark:border-gray-800 dark:hover:border-emerald-900/60 transition cursor-pointer"
                >
                  <div className="flex items-center gap-3">
                    <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600 dark:bg-emerald-950 dark:text-emerald-400 whitespace-nowrap">
                      <MessageCircle className="h-4 w-4" />
                    </div>
                    <div className="text-left">
                      <div className="font-bold text-gray-900 dark:text-white">
                        Hubungi via WhatsApp
                      </div>
                      <div className="text-[10px] text-gray-500">Kirim pesan langsung ke {managingEmployee.phone}</div>
                    </div>
                  </div>
                  <ChevronRight className="h-4 w-4 text-gray-400 group-hover:text-emerald-500 transition-colors" />
                </a>
              )}

              {managingEmployee.role !== "admin" && (
                <>
                  <button
                    type="button"
                    onClick={() => {
                      handleCopyLoginUrl(managingEmployee.role, managingEmployee.id)
                    }}
                    className="w-full group flex items-center justify-between p-3 rounded-xl bg-gray-50/70 hover:bg-brand-50/50 border border-gray-100 hover:border-brand-200 dark:bg-gray-800/40 dark:hover:bg-gray-800 dark:border-gray-800 dark:hover:border-brand-900/60 transition cursor-pointer"
                  >
                    <div className="flex items-center gap-3">
                      <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-brand-50 text-brand-600 dark:bg-brand-950 dark:text-brand-400 whitespace-nowrap">
                        {copiedId === managingEmployee.id ? <Check className="h-4 w-4 text-emerald-500" /> : <Copy className="h-4 w-4" />}
                      </div>
                      <div className="text-left">
                        <div className="font-bold text-gray-900 dark:text-white">
                          {copiedId === managingEmployee.id ? "Link Berhasil Disalin!" : "Salin Link Portal Login"}
                        </div>
                        <div className="text-[10px] text-gray-500">Kredensial akses portal lapangan</div>
                      </div>
                    </div>
                    <ChevronRight className="h-4 w-4 text-gray-400 group-hover:text-brand-500 transition-colors" />
                  </button>

                  <button
                    type="button"
                    onClick={() => {
                      const target = managingEmployee
                      setManagingEmployee(null)
                      openEmployeePerms(target)
                    }}
                    className="w-full group flex items-center justify-between p-3 rounded-xl bg-gray-50/70 hover:bg-purple-50/50 border border-gray-100 hover:border-purple-200 dark:bg-gray-800/40 dark:hover:bg-gray-800 dark:border-gray-800 dark:hover:border-purple-900/60 transition cursor-pointer"
                  >
                    <div className="flex items-center gap-3">
                      <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-purple-50 text-purple-600 dark:bg-purple-950 dark:text-purple-400 whitespace-nowrap">
                        <SlidersHorizontal className="h-4 w-4" />
                      </div>
                      <div className="text-left">
                        <div className="font-bold text-gray-900 dark:text-white">
                          Konfigurasi Hak Akses Modul
                        </div>
                        <div className="text-[10px] text-gray-500">Atur izin akses fitur spesifik staf ini</div>
                      </div>
                    </div>
                    <ChevronRight className="h-4 w-4 text-gray-400 group-hover:text-purple-500 transition-colors" />
                  </button>
                </>
              )}

              <button
                type="button"
                onClick={() => {
                  const target = managingEmployee
                  setManagingEmployee(null)
                  openEdit(target)
                }}
                className="w-full group flex items-center justify-between p-3 rounded-xl bg-gray-50/70 hover:bg-sky-50/50 border border-gray-100 hover:border-sky-200 dark:bg-gray-800/40 dark:hover:bg-gray-800 dark:border-gray-800 dark:hover:border-sky-900/60 transition cursor-pointer"
              >
                <div className="flex items-center gap-3">
                  <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-sky-50 text-sky-600 dark:bg-sky-950 dark:text-sky-400">
                    <Pencil className="h-4 w-4" />
                  </div>
                  <div className="text-left">
                    <div className="font-bold text-gray-900 dark:text-white">
                      Edit Data &amp; Password
                    </div>
                    <div className="text-[10px] text-gray-500">Ubah nama, peran, no HP, gaji, dan password</div>
                  </div>
                </div>
                <ChevronRight className="h-4 w-4 text-gray-400 group-hover:text-sky-500 transition-colors" />
              </button>

              <button
                type="button"
                onClick={() => {
                  const target = managingEmployee
                  setManagingEmployee(null)
                  setDeleteEmployeeTarget(target)
                }}
                className="w-full group flex items-center justify-between p-3 rounded-xl bg-gray-50/70 hover:bg-rose-50/50 border border-gray-100 hover:border-rose-200 dark:bg-gray-800/40 dark:hover:bg-gray-800 dark:border-gray-800 dark:hover:border-rose-900/60 transition cursor-pointer"
              >
                <div className="flex items-center gap-3">
                  <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-rose-50 text-rose-600 dark:bg-rose-950 dark:text-rose-400 whitespace-nowrap">
                    <Trash2 className="h-4 w-4" />
                  </div>
                  <div className="text-left">
                    <div className="font-bold text-rose-600 dark:text-rose-400">
                      Hapus Akun Karyawan
                    </div>
                    <div className="text-[10px] text-gray-500">Hapus permanen akses akun dari sistem</div>
                  </div>
                </div>
                <ChevronRight className="h-4 w-4 text-gray-400 group-hover:text-rose-500 transition-colors" />
              </button>
            </div>
          </div>
        </div>
      )}

      {/* ── MODAL ATUR HAK AKSES PER KARYAWAN ── */}
      {permModalEmployee && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
          <div className="fixed inset-0 bg-black/60 backdrop-blur-sm" onClick={() => setPermModalEmployee(null)} />
          <div className="relative flex max-h-[92vh] w-full max-w-lg flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 text-gray-900 dark:text-white shadow-2xl animate-in zoom-in-95 duration-150">
            {/* Modal Header */}
            <div className="flex items-center justify-between border-b border-gray-100 dark:border-gray-800 px-5 py-4 shrink-0">
              <div className="flex items-center gap-3">
                <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-brand-50 dark:bg-brand-500/10 text-brand-500 border border-brand-200 dark:border-brand-500/20">
                  <SlidersHorizontal className="h-5 w-5" />
                </div>
                <div>
                  <h3 className="font-bold text-base text-gray-900 dark:text-white">
                    Hak Akses: {permModalEmployee.name}
                  </h3>
                  <p className="text-xs text-gray-500 dark:text-gray-400">
                    Role: {permModalEmployee.role === "collector" || permModalEmployee.position?.toLowerCase().includes("kolektor") ? "Kolektor Lapangan" : "Teknisi Lapangan"} · @{permModalEmployee.username || "user"}
                  </p>
                </div>
              </div>
              <button
                onClick={() => setPermModalEmployee(null)}
                className="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-700 dark:hover:text-white"
              >
                <X className="h-4 w-4" />
              </button>
            </div>

            {/* Quick Presets Bar */}
            <div className="px-5 py-2.5 flex items-center gap-1.5 overflow-x-auto border-b border-gray-100 dark:border-gray-800 shrink-0 bg-gray-50/60 dark:bg-gray-950/40">
              <span className="text-[10px] font-bold uppercase tracking-wider text-gray-400 mr-1 shrink-0">Preset</span>
              <button
                type="button"
                onClick={() => {
                  const all: Record<string, boolean> = {}
                  ALL_EMPLOYEE_PERMISSIONS.forEach((p) => (all[p.key] = true))
                  setEmployeePerms(all)
                }}
                className="text-[10px] font-bold px-2.5 py-1 rounded-lg border border-brand-200 bg-brand-50 text-brand-600 dark:border-brand-900/50 dark:bg-brand-950/40 dark:text-brand-400 hover:bg-brand-100 active:scale-95 transition shrink-0 flex items-center gap-1 whitespace-nowrap"
              >
                <Check className="h-3 w-3" />
                <span>Full Akses</span>
              </button>
              <button
                type="button"
                onClick={() => {
                  const coll: Record<string, boolean> = {}
                  ALL_EMPLOYEE_PERMISSIONS.forEach((p) => {
                    coll[p.key] = ["collect_payment", "earnings", "dashboard", "customers", "pppoe"].includes(p.key)
                  })
                  setEmployeePerms(coll)
                }}
                className="text-[10px] font-bold px-2.5 py-1 rounded-lg border border-purple-200 bg-purple-50 text-purple-600 dark:border-purple-900/50 dark:bg-purple-950/40 dark:text-purple-400 hover:bg-purple-100 active:scale-95 transition shrink-0 whitespace-nowrap"
              >
                Fokus Penagihan
              </button>
              <button
                type="button"
                onClick={() => {
                  const tech: Record<string, boolean> = {}
                  ALL_EMPLOYEE_PERMISSIONS.forEach((p) => {
                    tech[p.key] = ["pool", "create_customer", "history", "pppoe", "arp", "map", "isolate", "top_bandwidth", "customers"].includes(p.key)
                  })
                  setEmployeePerms(tech)
                }}
                className="text-[10px] font-bold px-2.5 py-1 rounded-lg border border-cyan-200 bg-cyan-50 text-cyan-600 dark:border-cyan-900/50 dark:bg-cyan-950/40 dark:text-cyan-400 hover:bg-cyan-100 active:scale-95 transition shrink-0"
              >
                Fokus Lapangan
              </button>
              <button
                type="button"
                onClick={() => {
                  const none: Record<string, boolean> = {}
                  ALL_EMPLOYEE_PERMISSIONS.forEach((p) => (none[p.key] = false))
                  setEmployeePerms(none)
                }}
                className="text-[10px] font-bold px-2.5 py-1 rounded-lg border border-gray-200 bg-gray-100 text-gray-600 dark:border-gray-800 dark:bg-gray-800 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white active:scale-95 transition shrink-0"
              >
                Kosongkan
              </button>
            </div>

            {/* List Permissions */}
            <div className="flex-1 overflow-y-auto p-5 space-y-2.5">
              {ALL_EMPLOYEE_PERMISSIONS.map((item) => {
                const enabled = employeePerms[item.key] !== false
                const Icon = item.icon
                return (
                  <div
                    key={item.key}
                    onClick={() => toggleEmpPerm(item.key)}
                    className={cn(
                      "flex items-center justify-between p-3 rounded-xl border transition cursor-pointer select-none",
                      enabled
                        ? "border-brand-300 bg-brand-50/40 dark:border-brand-800 dark:bg-brand-950/30"
                        : "border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 opacity-60 hover:opacity-100"
                    )}
                  >
                    <div className="flex items-start gap-3 min-w-0 flex-1 pr-3">
                      <div
                        className={cn(
                          "flex h-8 w-8 shrink-0 items-center justify-center rounded-xl border mt-0.5",
                          enabled
                            ? "border-brand-300 bg-brand-100 text-brand-600 dark:border-brand-800 dark:bg-brand-950 dark:text-brand-400"
                            : "border-gray-200 bg-gray-100 text-gray-400 dark:border-gray-800 dark:bg-gray-800"
                        )}
                      >
                        <Icon className="h-4 w-4" />
                      </div>
                      <div className="min-w-0 flex-1">
                        <div className="flex items-center gap-1.5 flex-wrap">
                          <span className={cn("text-xs font-bold block", enabled ? "text-gray-900 dark:text-white" : "text-gray-500")}>
                            {item.title}
                          </span>
                          <span className="text-[9px] font-bold px-1.5 py-0.5 rounded bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400">
                            {item.category}
                          </span>
                        </div>
                        <span className="text-[11px] text-gray-500 dark:text-gray-400 font-medium block mt-0.5 leading-snug">
                          {item.desc}
                        </span>
                      </div>
                    </div>

                    {/* Toggle Switch */}
                    <div
                      role="switch"
                      aria-checked={enabled}
                      className={cn(
                        "relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none",
                        enabled ? "bg-brand-500" : "bg-gray-300 dark:bg-gray-700"
                      )}
                    >
                      <span
                        className={cn(
                          "pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out",
                          enabled ? "translate-x-5" : "translate-x-0"
                        )}
                      />
                    </div>
                  </div>
                )
              })}
            </div>

            {/* Modal Actions */}
            <div className="border-t border-gray-100 dark:border-gray-800 p-4 bg-gray-50/50 dark:bg-gray-900/50 shrink-0 flex items-center gap-2">
              <button
                type="button"
                onClick={() => setPermModalEmployee(null)}
                className="flex-1 rounded-xl h-10 text-xs font-bold border border-gray-200 bg-white hover:bg-gray-100 dark:border-gray-800 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200 transition"
              >
                Batal
              </button>
              <button
                type="button"
                onClick={() => saveEmployeePermsSubmit()}
                disabled={isSavingEmployeePerms}
                className="flex-1 rounded-xl h-10 text-xs font-bold bg-brand-500 hover:bg-brand-600 text-white transition flex items-center justify-center gap-1.5 disabled:opacity-50"
              >
                {isSavingEmployeePerms ? "Menyimpan..." : "Simpan Hak Akses"}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* ── MODAL TAMBAH / EDIT KARYAWAN ── */}
      {modalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
          <div className="fixed inset-0 bg-black/60 backdrop-blur-sm" onClick={() => setModalOpen(false)} />
          <div className="relative flex max-h-[92vh] w-full max-w-md flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 text-gray-900 dark:text-white shadow-2xl animate-in zoom-in-95 duration-150">
            {/* Modal Header */}
            <div className="flex items-center justify-between border-b border-gray-100 dark:border-gray-800 px-5 py-4 shrink-0">
              <div className="flex items-center gap-3">
                <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-brand-50 dark:bg-brand-500/10 text-brand-500 border border-brand-200 dark:border-brand-500/20">
                  <Users className="h-5 w-5" />
                </div>
                <div>
                  <h3 className="font-bold text-base text-gray-900 dark:text-white">
                    {editing ? "Edit Akun Karyawan" : "Tambah Karyawan Baru"}
                  </h3>
                  <p className="text-xs text-gray-500 dark:text-gray-400">Atur hak akses login dan peran sistem</p>
                </div>
              </div>
              <button
                onClick={() => setModalOpen(false)}
                className="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-700 dark:hover:text-white"
              >
                <X className="h-4 w-4" />
              </button>
            </div>

            <form onSubmit={submit} className="flex min-h-0 flex-1 flex-col overflow-hidden">
              <div className="flex-1 overflow-y-auto p-5 space-y-4">
                <div className="space-y-1.5">
                  <label className="text-xs font-semibold text-gray-700 dark:text-gray-300">Nama Lengkap</label>
                  <input
                    value={form.data.name}
                    onChange={(e) => form.setData("name", e.target.value)}
                    placeholder="cth: Budi Santoso"
                    required
                    className="w-full h-10 rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-950 px-3 text-xs text-gray-900 dark:text-white focus:outline-none focus:border-brand-500"
                  />
                </div>

                <div className="grid gap-3 sm:grid-cols-2">
                  <div className="space-y-1.5">
                    <label className="text-xs font-semibold text-gray-700 dark:text-gray-300">Username</label>
                    <input
                      value={form.data.username}
                      onChange={(e) => form.setData("username", e.target.value)}
                      placeholder="budi_tech"
                      required
                      className="w-full h-10 rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-950 px-3 text-xs text-gray-900 dark:text-white focus:outline-none focus:border-brand-500"
                    />
                  </div>

                  <div className="space-y-1.5">
                    <label className="text-xs font-semibold text-gray-700 dark:text-gray-300">
                      Password {editing ? "(Opsional)" : ""}
                    </label>
                    <input
                      type="password"
                      value={form.data.password}
                      onChange={(e) => form.setData("password", e.target.value)}
                      placeholder={editing ? "Kosongkan jika sama" : "Password"}
                      required={!editing}
                      className="w-full h-10 rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-950 px-3 text-xs text-gray-900 dark:text-white focus:outline-none focus:border-brand-500"
                    />
                  </div>
                </div>

                <div className="space-y-1.5">
                  <label className="text-xs font-semibold text-gray-700 dark:text-gray-300">Peran / Role Karyawan</label>
                  <select
                    value={form.data.role}
                    onChange={(e) => form.setData("role", e.target.value as any)}
                    className="w-full h-10 rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-950 px-3 text-xs text-gray-900 dark:text-white focus:outline-none focus:border-brand-500"
                  >
                    <option value="technician">Teknisi Lapangan (Tiket & ODP)</option>
                    <option value="collector">Kolektor / Kasir POS (Tagihan)</option>
                    <option value="admin">Staff Admin Operasional</option>
                  </select>
                </div>

                <div className="grid gap-3 sm:grid-cols-2">
                  <div className="space-y-1.5">
                    <label className="text-xs font-semibold text-gray-700 dark:text-gray-300">No. WhatsApp</label>
                    <input
                      value={form.data.phone}
                      onChange={(e) => form.setData("phone", e.target.value)}
                      placeholder="08xxxxxxxxxx"
                      className="w-full h-10 rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-950 px-3 text-xs text-gray-900 dark:text-white focus:outline-none focus:border-brand-500"
                    />
                  </div>

                  <div className="space-y-1.5">
                    <label className="text-xs font-semibold text-gray-700 dark:text-gray-300">Area Wilayah</label>
                    <input
                      value={form.data.collection_area}
                      onChange={(e) => form.setData("collection_area", e.target.value)}
                      placeholder="cth: Blok A - C"
                      className="w-full h-10 rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-950 px-3 text-xs text-gray-900 dark:text-white focus:outline-none focus:border-brand-500"
                    />
                  </div>
                </div>

                {form.data.role !== "admin" && (
                  <div className="grid gap-3 sm:grid-cols-2">
                    <div className="space-y-1.5">
                      <label className="text-xs font-semibold text-gray-700 dark:text-gray-300">Tipe Komisi</label>
                      <select
                        value={form.data.commission_type}
                        onChange={(e) => form.setData("commission_type", e.target.value as "fixed" | "percent")}
                        className="w-full h-10 rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-950 px-3 text-xs text-gray-900 dark:text-white focus:outline-none focus:border-brand-500"
                      >
                        <option value="fixed">Nominal Tetap (Rp)</option>
                        <option value="percent">Persen (%)</option>
                      </select>
                    </div>

                    <div className="space-y-1.5">
                      <label className="text-xs font-semibold text-gray-700 dark:text-gray-300">
                        {form.data.commission_type === "percent" ? "Persentase Komisi (%)" : "Nominal Komisi (Rp)"}
                      </label>
                      <input
                        type="number"
                        value={form.data.commission_value}
                        onChange={(e) => form.setData("commission_value", e.target.value)}
                        placeholder={form.data.commission_type === "percent" ? "cth: 5" : "cth: 5000"}
                        className="w-full h-10 rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-950 px-3 text-xs text-gray-900 dark:text-white focus:outline-none focus:border-brand-500"
                      />
                    </div>
                  </div>
                )}

                {routers.length > 0 && (
                  <div className="space-y-1.5">
                    <label className="text-xs font-semibold text-gray-700 dark:text-gray-300">Router MikroTik</label>
                    <select
                      value={form.data.router_id}
                      onChange={(e) => form.setData("router_id", e.target.value)}
                      className="w-full h-10 rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-950 px-3 text-xs text-gray-900 dark:text-white focus:outline-none focus:border-brand-500"
                    >
                      <option value="">Semua Router</option>
                      {routers.map((r) => (
                        <option key={r.id} value={String(r.id)}>
                          {r.name}
                        </option>
                      ))}
                    </select>
                  </div>
                )}

                {/* Konfigurasi Hak Akses Fitur Karyawan */}
                {form.data.role !== "admin" && (
                  <div className="pt-3 border-t border-gray-100 dark:border-gray-800 space-y-2.5">
                    <div className="flex items-center justify-between">
                      <label className="text-xs font-bold text-gray-900 dark:text-white flex items-center gap-1.5">
                        <SlidersHorizontal className="h-3.5 w-3.5 text-brand-500" />
                        Hak Akses Modul ({Object.values(form.data.permissions || {}).filter(Boolean).length}/{ALL_EMPLOYEE_PERMISSIONS.length})
                      </label>
                      <div className="flex items-center gap-1.5">
                        <button
                          type="button"
                          onClick={() => {
                            const all: Record<string, boolean> = {}
                            ALL_EMPLOYEE_PERMISSIONS.forEach((p) => (all[p.key] = true))
                            form.setData("permissions", all)
                          }}
                          className="text-[10px] font-bold text-brand-600 dark:text-brand-400 hover:underline"
                        >
                          Pilih Semua
                        </button>
                        <span className="text-gray-400">·</span>
                        <button
                          type="button"
                          onClick={() => {
                            const none: Record<string, boolean> = {}
                            ALL_EMPLOYEE_PERMISSIONS.forEach((p) => (none[p.key] = false))
                            form.setData("permissions", none)
                          }}
                          className="text-[10px] font-bold text-gray-500 hover:text-gray-700 dark:hover:text-white"
                        >
                          Kosongkan
                        </button>
                      </div>
                    </div>

                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-2 max-h-56 overflow-y-auto pr-1">
                      {ALL_EMPLOYEE_PERMISSIONS.map((item) => {
                        const isChecked = form.data.permissions?.[item.key] !== false
                        const Icon = item.icon
                        return (
                          <div
                            key={item.key}
                            onClick={() => {
                              form.setData("permissions", {
                                ...form.data.permissions,
                                [item.key]: !isChecked,
                              })
                            }}
                            className={cn(
                              "flex items-center gap-2 p-2 rounded-xl border text-xs cursor-pointer select-none transition",
                              isChecked
                                ? "border-brand-300 bg-brand-50/50 text-gray-900 dark:border-brand-800 dark:bg-brand-950/40 dark:text-white"
                                : "border-gray-200 bg-gray-50/50 text-gray-500 dark:border-gray-800 dark:bg-gray-950/30 opacity-70 hover:opacity-100"
                            )}
                          >
                            <div
                              className={cn(
                                "flex h-6 w-6 shrink-0 items-center justify-center rounded-lg border",
                                isChecked
                                  ? "border-brand-300 bg-brand-100 text-brand-600 dark:border-brand-800 dark:bg-brand-900/50 dark:text-brand-400"
                                  : "border-gray-200 bg-gray-100 text-gray-400 dark:border-gray-800 dark:bg-gray-900"
                              )}
                            >
                              <Icon className="h-3 w-3" />
                            </div>
                            <span className="font-semibold truncate flex-1 text-[11px]">{item.title}</span>
                            <div
                              className={cn(
                                "h-4 w-4 rounded flex items-center justify-center shrink-0",
                                isChecked ? "bg-brand-500 text-white" : "border border-gray-300 dark:border-gray-700 text-transparent"
                              )}
                            >
                              {isChecked && <Check className="h-3 w-3" />}
                            </div>
                          </div>
                        )
                      })}
                    </div>
                  </div>
                )}
              </div>

              {/* Modal Bottom Actions */}
              <div className="border-t border-gray-100 dark:border-gray-800 p-4 bg-gray-50/50 dark:bg-gray-900/50 shrink-0 flex items-center gap-2">
                <button
                  type="button"
                  onClick={() => setModalOpen(false)}
                  className="flex-1 rounded-xl h-10 text-xs font-bold border border-gray-200 bg-white hover:bg-gray-100 dark:border-gray-800 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200 transition"
                >
                  Batal
                </button>
                <button
                  type="submit"
                  disabled={form.processing}
                  className="flex-1 rounded-xl h-10 text-xs font-bold bg-brand-500 hover:bg-brand-600 text-white transition flex items-center justify-center gap-1.5 disabled:opacity-50"
                >
                  {form.processing ? "Menyimpan..." : editing ? "Simpan Perubahan" : "Tambah Karyawan"}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* ── MODAL KONFIRMASI HAPUS KARYAWAN ── */}
      {deleteEmployeeTarget && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
          <div className="fixed inset-0 bg-black/60 backdrop-blur-sm" onClick={() => setDeleteEmployeeTarget(null)} />
          <div className="w-full max-w-md rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 text-gray-900 dark:text-white shadow-2xl overflow-hidden animate-in zoom-in-95 duration-150">
            <div className="p-6 text-center space-y-4">
              <div className="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl border border-rose-200 bg-rose-50 text-rose-600 dark:border-rose-900/50 dark:bg-rose-950/40 dark:text-rose-400">
                <Trash2 className="h-7 w-7" />
              </div>

              <div className="space-y-1.5">
                <h3 className="font-bold text-base text-gray-900 dark:text-white">
                  Hapus Akun Karyawan?
                </h3>
                <p className="text-xs text-gray-500 dark:text-gray-400 leading-relaxed">
                  Apakah Anda yakin ingin menghapus akun{" "}
                  <span className="font-bold text-gray-900 dark:text-white">{deleteEmployeeTarget.name}</span>{" "}
                  (<span className="font-mono text-gray-600 dark:text-gray-300">@{deleteEmployeeTarget.username}</span>)?
                  Akses login dan riwayat terkait karyawan ini akan dihapus dari sistem.
                </p>
              </div>

              <div className="p-3 rounded-xl border border-gray-200 bg-gray-50 dark:border-gray-800 dark:bg-gray-950/50 text-left text-xs text-gray-600 dark:text-gray-300 space-y-1">
                <div className="flex justify-between">
                  <span className="text-gray-400">Role</span>
                  <span className="font-semibold text-gray-900 dark:text-white uppercase">{deleteEmployeeTarget.role || "Staff"}</span>
                </div>
                {deleteEmployeeTarget.phone && (
                  <div className="flex justify-between">
                    <span className="text-gray-400">WhatsApp</span>
                    <span className="font-mono text-gray-800 dark:text-gray-200">{deleteEmployeeTarget.phone}</span>
                  </div>
                )}
                {deleteEmployeeTarget.collection_area && (
                  <div className="flex justify-between">
                    <span className="text-gray-400">Wilayah</span>
                    <span className="font-medium text-gray-800 dark:text-gray-200">{deleteEmployeeTarget.collection_area}</span>
                  </div>
                )}
              </div>

              <div className="flex items-center gap-2.5 pt-2">
                <button
                  type="button"
                  onClick={() => setDeleteEmployeeTarget(null)}
                  disabled={isDeletingEmployee}
                  className="flex-1 rounded-xl h-10 text-xs font-bold border border-gray-200 bg-white hover:bg-gray-100 dark:border-gray-800 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200 transition disabled:opacity-50"
                >
                  Batal
                </button>
                <button
                  type="button"
                  onClick={handleDeleteEmployee}
                  disabled={isDeletingEmployee}
                  className="flex-1 rounded-xl h-10 text-xs font-bold bg-rose-600 hover:bg-rose-700 text-white transition flex items-center justify-center gap-1.5 disabled:opacity-50"
                >
                  {isDeletingEmployee ? (
                    <RefreshCw className="h-4 w-4 animate-spin" />
                  ) : (
                    <Trash2 className="h-4 w-4" />
                  )}
                  <span>{isDeletingEmployee ? "Menghapus..." : "Ya, Hapus"}</span>
                </button>
              </div>
            </div>
          </div>
        </div>
      )}
    </AppLayout>
  )
}
