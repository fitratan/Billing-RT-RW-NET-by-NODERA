import * as React from "react"
import { useState, useMemo } from "react"
import { AppLayout } from "@/components/layout/app-layout"
import {
  Database,
  RefreshCw,
  CheckCircle2,
  AlertCircle,
  Users,
  Activity,
  Trash2,
  Plus,
  ChevronDown,
  Eye,
  EyeOff,
  Save,
  Loader2,
  HelpCircle,
  Sliders,
  Search,
  Router as RouterIcon,
  Cpu,
  PowerOff,
} from "lucide-react"
import { Label } from "@/components/ui/label"
import { Input } from "@/components/ui/input"
import { Switch } from "@/components/tailadmin/Switch"
import { PageProps } from "@/types"
import { cn } from "@/lib/utils"
import { adminSidebarItems, adminNavItems, adminBrand } from "@/lib/admin-nav"
import { useForm, router } from "@inertiajs/react"
import { MetricCard } from "@/components/tailadmin/MetricCard"
import axios from "axios"

interface RadiusSetting {
  id?: number
  radius_mode: "disabled" | "local_db" | "remote_db" | "userman_v7"
  is_active: boolean
  remote_db_driver: string
  remote_db_host: string
  remote_db_port: number
  remote_db_name: string
  remote_db_user: string
  remote_db_pass?: string
  nas_ip: string
  nas_secret?: string
  coa_port: number
  userman_host: string
  userman_port: number
  userman_user: string
  userman_pass?: string
  auto_sync_on_create: boolean
  auto_coa_on_isolate: boolean
  last_sync_at?: string | null
  last_test_at?: string | null
  last_test_status?: string | null
  last_test_message?: string | null
}

interface NasClient {
  id: number
  nasname: string
  shortname?: string
  type?: string
  secret: string
  description?: string
}

interface RadiusSession {
  radacctid?: number
  username: string
  groupname: string
  nasipaddress: string
  framedipaddress: string
  mac_address: string
  acctstarttime: string
  acctsessiontime?: number | string | null
  acctinputoctets?: number | string | null
  acctoutputoctets?: number | string | null
  acctterminatecause?: string | null
}

interface RadiusUser {
  id?: number | string
  username: string
  groupname: string
}

interface Props extends PageProps {
  setting: RadiusSetting
  nasClients: NasClient[]
  sessions: RadiusSession[]
  users: RadiusUser[]
  stats: {
    total_pppoe_customers: number
    total_radius_users: number
    total_active_sessions: number
    total_nas_clients: number
  }
}

function formatBytes(bytes: number | string | null | undefined): string {
  if (bytes == null || bytes === "") return "0 B"
  const n = Number(bytes)
  if (!n || isNaN(n)) return "0 B"
  if (n < 1024) return `${n} B`
  if (n < 1024 * 1024) return `${(n / 1024).toFixed(1)} KB`
  if (n < 1024 * 1024 * 1024) return `${(n / (1024 * 1024)).toFixed(1)} MB`
  return `${(n / (1024 * 1024 * 1024)).toFixed(2)} GB`
}

function formatDuration(seconds: number | string | null | undefined): string {
  if (seconds == null || seconds === "") return "-"
  const s = Number(seconds)
  if (isNaN(s)) return String(seconds)
  const d = Math.floor(s / (3600 * 24))
  const h = Math.floor((s % (3600 * 24)) / 3600)
  const m = Math.floor((s % 3600) / 60)
  if (d > 0) return `${d}h ${h}j ${m}m`
  if (h > 0) return `${h}j ${m}m`
  return `${m}m ${s % 60}d`
}

export default function RadiusPage({
  setting,
  nasClients = [],
  sessions = [],
  users = [],
  stats,
}: Props) {
  const [openSections, setOpenSections] = useState<Record<string, boolean>>({
    guide: false,
    mode: true,
    server: true,
    nas: false,
    coa: false,
    sessions: false,
    users: false,
  })

  const toggleSection = (section: string) => {
    setOpenSections((prev) => ({
      ...prev,
      [section]: !prev[section],
    }))
  }

  const [showDbPass, setShowDbPass] = useState(false)
  const [showUsermanPass, setShowUsermanPass] = useState(false)

  const [testLoading, setTestLoading] = useState(false)
  const [testResult, setTestResult] = useState<{
    success: boolean
    message: string
    latency_ms?: number
    users_count?: number
  } | null>(null)

  const [syncLoading, setSyncLoading] = useState(false)
  const [syncSuccess, setSyncSuccess] = useState<string | null>(null)

  const [coaLoading, setCoaLoading] = useState(false)
  const [coaTargetUser, setCoaTargetUser] = useState("")
  const [coaTargetNas, setCoaTargetNas] = useState("")
  const [coaTargetIp, setCoaTargetIp] = useState("")
  const [coaResult, setCoaResult] = useState<{ success: boolean; message: string; latency_ms?: number } | null>(null)

  const [sessionSearch, setSessionSearch] = useState("")
  const [userSearch, setUserSearch] = useState("")

  const form = useForm<RadiusSetting>({
    radius_mode: setting.radius_mode || "disabled",
    is_active: setting.is_active || false,
    remote_db_driver: setting.remote_db_driver || "mysql",
    remote_db_host: setting.remote_db_host || "",
    remote_db_port: setting.remote_db_port || 3306,
    remote_db_name: setting.remote_db_name || "radius",
    remote_db_user: setting.remote_db_user || "",
    remote_db_pass: setting.remote_db_pass || "",
    nas_ip: setting.nas_ip || "",
    nas_secret: setting.nas_secret || "",
    coa_port: setting.coa_port || 3799,
    userman_host: setting.userman_host || "",
    userman_port: setting.userman_port || 8728,
    userman_user: setting.userman_user || "",
    userman_pass: setting.userman_pass || "",
    auto_sync_on_create: setting.auto_sync_on_create ?? true,
    auto_coa_on_isolate: setting.auto_coa_on_isolate ?? true,
  })

  const [showAddNas, setShowAddNas] = useState(false)
  const [nasForm, setNasForm] = useState({
    nasname: "",
    shortname: "mikrotik",
    type: "other",
    secret: "testing123",
    description: "",
  })

  const handleSubmitSettings = (e?: React.FormEvent) => {
    if (e) e.preventDefault()
    form.post("/admin/radius/settings", {
      preserveScroll: true,
    })
  }

  const handleTestConnection = async () => {
    setTestLoading(true)
    setTestResult(null)
    try {
      const res = await axios.post("/admin/radius/test-connection")
      setTestResult(res.data)
    } catch (err: any) {
      setTestResult({
        success: false,
        message: err?.response?.data?.message || "Koneksi ke server RADIUS gagal.",
        latency_ms: err?.response?.data?.latency_ms || 0,
      })
    } finally {
      setTestLoading(false)
    }
  }

  const handleSyncAll = () => {
    if (!confirm("Sinkronkan seluruh akun PPPoE pelanggan ke server RADIUS sekarang?")) return
    setSyncLoading(true)
    setSyncSuccess(null)
    router.post(
      "/admin/radius/sync-all",
      {},
      {
        preserveScroll: true,
        onSuccess: () => {
          setSyncSuccess("Semua data akun PPPoE berhasil disinkronkan ke server RADIUS!")
        },
        onFinish: () => setSyncLoading(false),
      }
    )
  }

  const handleSendCoaDisconnect = async (username: string, nasIp?: string, framedIp?: string) => {
    setCoaLoading(true)
    setCoaResult(null)
    try {
      const res = await axios.post("/admin/radius/disconnect-session", {
        username,
        nas_ip: nasIp,
        framed_ip: framedIp,
      })
      setCoaResult(res.data)
      router.reload({ only: ["sessions", "stats"] })
    } catch (err: any) {
      setCoaResult({
        success: false,
        message: err?.response?.data?.message || "Gagal mengirim Disconnect-Request RFC 3576 CoA.",
      })
    } finally {
      setCoaLoading(false)
    }
  }

  const handleSaveNas = (e: React.FormEvent) => {
    e.preventDefault()
    router.post("/admin/radius/nas/save", nasForm, {
      preserveScroll: true,
      onSuccess: () => {
        setShowAddNas(false)
        setNasForm({
          nasname: "",
          shortname: "mikrotik",
          type: "other",
          secret: "testing123",
          description: "",
        })
      },
    })
  }

  const handleDeleteNas = (id: number) => {
    if (!confirm("Hapus NAS Router Client ini dari tabel FreeRADIUS?")) return
    router.post(`/admin/radius/nas/delete/${id}`, {}, { preserveScroll: true })
  }

  const filteredSessions = useMemo(() => {
    if (!sessionSearch.trim()) return sessions
    const q = sessionSearch.toLowerCase()
    return sessions.filter(
      (s) =>
        s.username?.toLowerCase().includes(q) ||
        s.framedipaddress?.toLowerCase().includes(q) ||
        s.mac_address?.toLowerCase().includes(q) ||
        s.nasipaddress?.toLowerCase().includes(q)
    )
  }, [sessions, sessionSearch])

  const filteredUsers = useMemo(() => {
    if (!userSearch.trim()) return users
    const q = userSearch.toLowerCase()
    return users.filter(
      (u) => u.username?.toLowerCase().includes(q) || u.groupname?.toLowerCase().includes(q)
    )
  }, [users, userSearch])

  const isConfigured = form.data.radius_mode !== "disabled" && form.data.is_active

  return (
    <AppLayout
      title="RADIUS Server & AAA"
      brand={adminBrand}
      sidebarItems={adminSidebarItems}
      navItems={adminNavItems}
    >
      <div className="space-y-4 sm:space-y-6">
        {/* Top Metrics Row */}
        <div className="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
          <MetricCard
            title="Pelanggan PPPoE"
            value={stats.total_pppoe_customers}
            sub="Database billing"
            icon={<Users className="h-5 w-5 sm:h-6 sm:w-6 text-brand-500" />}
            iconBgColor="bg-brand-50 dark:bg-brand-500/10"
            iconColor="text-brand-500 dark:text-brand-400"
          />
          <MetricCard
            title="User RADIUS"
            value={stats.total_radius_users}
            sub="Tabel radcheck"
            icon={<Database className="h-5 w-5 sm:h-6 sm:w-6 text-indigo-500" />}
            iconBgColor="bg-indigo-50 dark:bg-indigo-500/10"
            iconColor="text-indigo-500 dark:text-indigo-400"
          />
          <MetricCard
            title="Sesi Online"
            value={stats.total_active_sessions}
            sub="Akun online realtime"
            icon={<Activity className="h-5 w-5 sm:h-6 sm:w-6 text-emerald-500" />}
            iconBgColor="bg-emerald-50 dark:bg-emerald-500/10"
            iconColor="text-emerald-500 dark:text-emerald-400"
          />
          <MetricCard
            title="Router NAS"
            value={stats.total_nas_clients}
            sub="NAS Client terdaftar"
            icon={<RouterIcon className="h-5 w-5 sm:h-6 sm:w-6 text-amber-500" />}
            iconBgColor="bg-amber-50 dark:bg-amber-500/10"
            iconColor="text-amber-500 dark:text-amber-400"
          />
        </div>

        {/* ── 1-LINE RESPONSIVE INTEGRATED TOOLBAR ── */}
        <div className="rounded-2xl border border-gray-200 bg-white p-3 sm:p-4 shadow-xs dark:border-gray-800 dark:bg-white/[0.03] flex flex-col gap-2.5 sm:gap-3 lg:flex-row lg:items-center lg:justify-between">
          <div className="flex flex-wrap items-center gap-2 sm:gap-3 flex-1 min-w-0">
            <span
              className={cn(
                "inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-[11px] font-bold shadow-xs whitespace-nowrap",
                isConfigured
                  ? "bg-emerald-500 text-white"
                  : "bg-amber-500 text-white"
              )}
            >
              <span className={cn("h-1.5 w-1.5 rounded-full", isConfigured ? "bg-white animate-pulse" : "bg-white/80")} />
              <span>{isConfigured ? "Aktif & Terhubung" : "Dinonaktifkan"}</span>
            </span>

            <span className="text-xs text-gray-500 dark:text-gray-400 font-mono">
              Mode: <strong className="text-gray-800 dark:text-white uppercase">{form.data.radius_mode}</strong>
            </span>
          </div>

          <div className="flex items-center gap-2 shrink-0 w-full sm:w-auto justify-end">
            <button
              type="button"
              onClick={handleTestConnection}
              disabled={testLoading || form.data.radius_mode === "disabled"}
              className="h-10 inline-flex items-center gap-1.5 px-3 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:hover:bg-gray-800 text-xs font-semibold text-gray-700 dark:text-gray-200 transition shadow-2xs disabled:opacity-50 cursor-pointer shrink-0"
            >
              {testLoading ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : <Activity className="h-3.5 w-3.5 text-brand-500" />}
              <span>Uji Koneksi</span>
            </button>

            <button
              type="button"
              onClick={handleSyncAll}
              disabled={syncLoading || form.data.radius_mode === "disabled"}
              className="h-10 inline-flex items-center gap-1.5 px-3.5 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:hover:bg-gray-800 text-xs font-semibold text-gray-700 dark:text-gray-200 transition shadow-2xs disabled:opacity-50 cursor-pointer shrink-0"
            >
              {syncLoading ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : <RefreshCw className="h-3.5 w-3.5 text-indigo-500" />}
              <span className="hidden sm:inline">Sinkronkan PPPoE</span>
            </button>

            <button
              type="button"
              onClick={() => handleSubmitSettings()}
              disabled={form.processing}
              className="h-10 px-3.5 sm:px-4 rounded-xl bg-brand-500 hover:bg-brand-600 active:scale-95 text-xs font-bold text-white shadow-xs flex items-center justify-center gap-1.5 w-full sm:w-auto shrink-0 transition disabled:opacity-50 cursor-pointer"
            >
              {form.processing ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : <Save className="h-3.5 w-3.5" />}
              <span>Simpan Pengaturan</span>
            </button>
          </div>
        </div>

        {/* Feedback Alerts */}
        {syncSuccess && (
          <div className="flex items-start gap-3 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-xs text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-950/40 dark:text-emerald-300">
            <CheckCircle2 className="h-5 w-5 text-emerald-600 dark:text-emerald-400 shrink-0 mt-0.5" />
            <div className="flex-1 min-w-0">
              <strong className="font-bold block mb-0.5">Sinkronisasi Berhasil</strong>
              <span>{syncSuccess}</span>
            </div>
          </div>
        )}

        {testResult && (
          <div
            className={cn(
              "flex items-start gap-3 rounded-xl border p-4 text-xs",
              testResult.success
                ? "border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-950/40 dark:text-emerald-300"
                : "border-rose-200 bg-rose-50 text-rose-800 dark:border-rose-500/30 dark:bg-rose-950/40 dark:text-rose-300"
            )}
          >
            {testResult.success ? (
              <CheckCircle2 className="h-5 w-5 text-emerald-600 dark:text-emerald-400 shrink-0 mt-0.5" />
            ) : (
              <AlertCircle className="h-5 w-5 text-rose-600 dark:text-rose-400 shrink-0 mt-0.5" />
            )}
            <div className="flex-1 min-w-0">
              <div className="flex items-center justify-between gap-2">
                <strong className="font-bold block mb-0.5">
                  {testResult.success ? "Uji Koneksi Berhasil" : "Uji Koneksi Gagal"}
                </strong>
                {testResult.latency_ms !== undefined && (
                  <span className="font-mono text-[10px] px-2 py-0.5 rounded bg-gray-100 dark:bg-black/40 text-gray-700 dark:text-gray-300 font-bold">
                    {testResult.latency_ms} ms
                  </span>
                )}
              </div>
              <span>{testResult.message}</span>
            </div>
          </div>
        )}

        {/* Master Form with Collapsible Cards */}
        <form onSubmit={handleSubmitSettings} className="space-y-4">
          {/* SECTION 1: PANDUAN INTEGRASI */}
          <div className="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xs dark:border-gray-800 dark:bg-white/[0.03]">
            <button
              type="button"
              onClick={() => toggleSection("guide")}
              className="w-full p-4 sm:p-5 flex items-center justify-between gap-3 text-left hover:bg-gray-50/50 dark:hover:bg-white/[0.02] transition cursor-pointer"
            >
              <div className="flex items-center gap-3 min-w-0">
                <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-cyan-50 text-cyan-600 dark:bg-cyan-500/10 dark:text-cyan-400 border border-cyan-200 dark:border-cyan-500/20">
                  <HelpCircle className="h-4 w-4" />
                </div>
                <div className="min-w-0">
                  <h2 className="text-sm font-bold text-gray-800 dark:text-white truncate">
                    Panduan Integrasi RADIUS &amp; RFC 3576 CoA
                  </h2>
                  <p className="text-[11px] text-gray-500 dark:text-gray-400 truncate">
                    Konsep AAA (Authentication, Accounting) &amp; Disconnect UDP 3799 di NODERA.
                  </p>
                </div>
              </div>
              <ChevronDown
                className={cn(
                  "h-4 w-4 text-gray-400 transition-transform duration-200",
                  openSections.guide && "rotate-180 text-brand-500"
                )}
              />
            </button>

            {openSections.guide && (
              <div className="px-4 pb-5 sm:px-5 border-t border-gray-200 dark:border-gray-800 pt-4 space-y-3">
                <div className="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs">
                  <div className="rounded-xl border border-gray-200 bg-gray-50/50 p-3.5 space-y-1.5 dark:border-gray-800 dark:bg-gray-900">
                    <span className="flex h-5 w-5 items-center justify-center rounded-full bg-cyan-500 text-white font-bold text-[10px]">1</span>
                    <strong className="text-gray-800 dark:text-white block">Tidak Wajib Punya Server</strong>
                    <p className="text-[11px] text-gray-500 dark:text-gray-400 leading-relaxed">
                      Nodera fleksibel. Jika hanya memakai 1-2 router MikroTik, mode <b>Disabled</b> otomatis mengelola akun via MikroTik API standar tanpa perlu setup RADIUS.
                    </p>
                  </div>

                  <div className="rounded-xl border border-gray-200 bg-gray-50/50 p-3.5 space-y-1.5 dark:border-gray-800 dark:bg-gray-900">
                    <span className="flex h-5 w-5 items-center justify-center rounded-full bg-cyan-500 text-white font-bold text-[10px]">2</span>
                    <strong className="text-gray-800 dark:text-white block">Database Sentral Terpadu</strong>
                    <p className="text-[11px] text-gray-500 dark:text-gray-400 leading-relaxed">
                      Jika memiliki FreeRADIUS eksternal, Nodera otomatis menulis ke <code>radcheck</code> dan membaca akuntansi <code>radacct</code>.
                    </p>
                  </div>

                  <div className="rounded-xl border border-gray-200 bg-gray-50/50 p-3.5 space-y-1.5 dark:border-gray-800 dark:bg-gray-900">
                    <span className="flex h-5 w-5 items-center justify-center rounded-full bg-cyan-500 text-white font-bold text-[10px]">3</span>
                    <strong className="text-gray-800 dark:text-white block">Isolir Instan via RFC 3576</strong>
                    <p className="text-[11px] text-gray-500 dark:text-gray-400 leading-relaxed">
                      Paket <b>Packet of Disconnect UDP 3799</b> dikirim langsung ke NAS Router saat tagihan jatuh tempo untuk memutus koneksi pelanggan secara realtime.
                    </p>
                  </div>
                </div>

                <div className="rounded-xl border border-gray-200 bg-gray-50 p-4 space-y-2 dark:border-gray-800 dark:bg-gray-900">
                  <span className="text-xs font-bold text-gray-800 dark:text-white flex items-center gap-1.5">
                    <Activity className="h-3.5 w-3.5 text-amber-500" />
                    Script MikroTik RouterOS (Incoming CoA &amp; RADIUS Client):
                  </span>
                  <div className="p-3 rounded-lg bg-gray-900 text-emerald-400 font-mono text-[11px] overflow-x-auto select-all leading-relaxed">
                    # 1. Aktifkan Incoming CoA Port 3799<br />
                    /radius incoming set accept=yes port=3799<br /><br />
                    # 2. Tambahkan Server RADIUS Client<br />
                    /radius add service=ppp address={form.data.remote_db_host || "103.x.x.x"} secret="{form.data.nas_secret || "testing123"}" timeout=3s<br /><br />
                    # 3. Aktifkan Autentikasi RADIUS di PPPoE Server<br />
                    /interface pppoe-server server set [find default=yes] use-radius=yes<br />
                    /ppp aaa set use-radius=yes interim-update=1m
                  </div>
                </div>
              </div>
            )}
          </div>

          {/* SECTION 2: MODE OPERASI & OTOMASI */}
          <div className="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xs dark:border-gray-800 dark:bg-white/[0.03]">
            <button
              type="button"
              onClick={() => toggleSection("mode")}
              className="w-full p-4 sm:p-5 flex items-center justify-between gap-3 text-left hover:bg-gray-50/50 dark:hover:bg-white/[0.02] transition cursor-pointer"
            >
              <div className="flex items-center gap-3 min-w-0">
                <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600 dark:bg-indigo-500/10 dark:text-indigo-400 border border-indigo-200 dark:border-indigo-500/20">
                  <Sliders className="h-4 w-4" />
                </div>
                <div className="min-w-0">
                  <h2 className="text-sm font-bold text-gray-800 dark:text-white truncate">
                    Mode Integrasi &amp; Otomatisasi RADIUS
                  </h2>
                  <p className="text-[11px] text-gray-500 dark:text-gray-400 truncate">
                    Pilih arsitektur RADIUS dan tentukan opsi sinkronisasi otomatis serta isolir.
                  </p>
                </div>
              </div>
              <ChevronDown
                className={cn(
                  "h-4 w-4 text-gray-400 transition-transform duration-200",
                  openSections.mode && "rotate-180 text-brand-500"
                )}
              />
            </button>

            {openSections.mode && (
              <div className="px-4 pb-5 sm:px-5 border-t border-gray-200 dark:border-gray-800 pt-4 space-y-4">
                <div className="space-y-2">
                  <Label className="text-xs font-semibold text-gray-700 dark:text-gray-300">Pilih Mode RADIUS Sesuai Infrastruktur</Label>
                  <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    {[
                      {
                        id: "disabled",
                        title: "Dinonaktifkan (Default)",
                        desc: "Gunakan direct MikroTik API bawaan tanpa server FreeRADIUS eksternal.",
                        icon: PowerOff,
                        activeColor: "border-gray-400 bg-gray-50 dark:border-gray-600 dark:bg-gray-800/40 text-gray-800 dark:text-white",
                      },
                      {
                        id: "remote_db",
                        title: "Remote FreeRADIUS DB",
                        desc: "Koneksi langsung ke database MySQL / PgSQL FreeRADIUS AAA.",
                        icon: Database,
                        activeColor: "border-indigo-500 bg-indigo-50/50 dark:bg-indigo-500/10 text-indigo-900 dark:text-indigo-300",
                      },
                      {
                        id: "userman_v7",
                        title: "User Manager v7",
                        desc: "MikroTik RouterOS v7 built-in User Manager via REST API / API-SSL.",
                        icon: Cpu,
                        activeColor: "border-purple-500 bg-purple-50/50 dark:bg-purple-500/10 text-purple-900 dark:text-purple-300",
                      },
                    ].map((item) => {
                      const Icon = item.icon
                      const isSelected = form.data.radius_mode === item.id
                      return (
                        <button
                          key={item.id}
                          type="button"
                          onClick={() => form.setData("radius_mode", item.id as any)}
                          className={cn(
                            "flex flex-col text-left p-3.5 rounded-xl border transition cursor-pointer select-none",
                            isSelected
                              ? item.activeColor + " shadow-xs font-bold"
                              : "border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 text-gray-600 hover:border-gray-300 dark:text-gray-400"
                          )}
                        >
                          <div className="flex items-center justify-between mb-2">
                            <Icon className="h-4 w-4 shrink-0" />
                            <span
                              className={cn(
                                "h-3 w-3 rounded-full border",
                                isSelected ? "bg-brand-500 border-brand-500" : "border-gray-300 bg-transparent"
                              )}
                            />
                          </div>
                          <strong className="text-xs text-gray-800 dark:text-white block">{item.title}</strong>
                          <p className="text-[11px] text-gray-500 dark:text-gray-400 mt-1 leading-relaxed font-normal">{item.desc}</p>
                        </button>
                      )
                    })}
                  </div>
                </div>

                <div className="pt-3 border-t border-gray-200 dark:border-gray-800 space-y-3">
                  <div className="flex items-center justify-between gap-4 p-3 rounded-xl border border-gray-200 bg-gray-50/50 dark:border-gray-800 dark:bg-gray-900">
                    <div className="space-y-0.5">
                      <Label className="text-xs font-semibold text-gray-800 dark:text-white">Aktifkan Layanan RADIUS</Label>
                      <p className="text-[11px] text-gray-500 dark:text-gray-400">
                        Aktifkan engine sinkronisasi &amp; accounting untuk sistem billing ISP Anda.
                      </p>
                    </div>
                    <Switch
                      checked={form.data.is_active}
                      onChange={(val) => form.setData("is_active", val)}
                    />
                  </div>

                  <div className="flex items-center justify-between gap-4 p-3 rounded-xl border border-gray-200 bg-gray-50/50 dark:border-gray-800 dark:bg-gray-900">
                    <div className="space-y-0.5">
                      <Label className="text-xs font-semibold text-gray-800 dark:text-white">Auto-Sync Saat Buat / Edit Pelanggan</Label>
                      <p className="text-[11px] text-gray-500 dark:text-gray-400">
                        Otomatis update akun ke <code>radcheck</code> / <code>radusergroup</code> saat data diubah di Nodera.
                      </p>
                    </div>
                    <Switch
                      checked={form.data.auto_sync_on_create}
                      onChange={(val) => form.setData("auto_sync_on_create", val)}
                    />
                  </div>

                  <div className="flex items-center justify-between gap-4 p-3 rounded-xl border border-gray-200 bg-gray-50/50 dark:border-gray-800 dark:bg-gray-900">
                    <div className="space-y-0.5">
                      <Label className="text-xs font-semibold text-gray-800 dark:text-white">Auto-CoA Saat Isolir / Lunas</Label>
                      <p className="text-[11px] text-gray-500 dark:text-gray-400">
                        Kirim sinyal RFC 3576 Disconnect UDP 3799 instan ke NAS Router saat status tagihan isolir berubah.
                      </p>
                    </div>
                    <Switch
                      checked={form.data.auto_coa_on_isolate}
                      onChange={(val) => form.setData("auto_coa_on_isolate", val)}
                    />
                  </div>
                </div>
              </div>
            )}
          </div>

          {/* SECTION 3: KONFIGURASI SERVER & KREDENSIAL */}
          <div className="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xs dark:border-gray-800 dark:bg-white/[0.03]">
            <button
              type="button"
              onClick={() => toggleSection("server")}
              className="w-full p-4 sm:p-5 flex items-center justify-between gap-3 text-left hover:bg-gray-50/50 dark:hover:bg-white/[0.02] transition cursor-pointer"
            >
              <div className="flex items-center gap-3 min-w-0">
                <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-cyan-50 text-cyan-600 dark:bg-cyan-500/10 dark:text-cyan-400 border border-cyan-200 dark:border-cyan-500/20">
                  <Database className="h-4 w-4" />
                </div>
                <div className="min-w-0">
                  <h2 className="text-sm font-bold text-gray-800 dark:text-white truncate">
                    Konfigurasi Database &amp; Kredensial Akses
                  </h2>
                  <p className="text-[11px] text-gray-500 dark:text-gray-400 truncate">
                    Parameter database FreeRADIUS (MySQL / PgSQL) atau API User Manager v7.
                  </p>
                </div>
              </div>
              <ChevronDown
                className={cn(
                  "h-4 w-4 text-gray-400 transition-transform duration-200",
                  openSections.server && "rotate-180 text-brand-500"
                )}
              />
            </button>

            {openSections.server && (
              <div className="px-4 pb-5 sm:px-5 border-t border-gray-200 dark:border-gray-800 pt-4 space-y-4">
                {form.data.radius_mode === "disabled" && (
                  <div className="p-4 rounded-xl border border-gray-200 bg-gray-50 text-center text-xs text-gray-500 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-400">
                    Mode RADIUS sedang <b>Dinonaktifkan</b>. Ubah mode integrasi di section atas jika ingin menghubungkan FreeRADIUS DB atau User Manager v7.
                  </div>
                )}

                {form.data.radius_mode === "remote_db" && (
                  <div className="space-y-4">
                    <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                      <div className="space-y-1.5">
                        <Label className="text-xs font-semibold text-gray-700 dark:text-gray-300">Database Driver</Label>
                        <select
                          value={form.data.remote_db_driver}
                          onChange={(e) => form.setData("remote_db_driver", e.target.value)}
                          className="h-10 w-full rounded-xl border border-gray-200 bg-white px-3 text-xs text-gray-800 focus:border-brand-500 focus:outline-none dark:border-gray-800 dark:bg-gray-900 dark:text-gray-200"
                        >
                          <option value="mysql">MySQL / MariaDB</option>
                          <option value="pgsql">PostgreSQL</option>
                        </select>
                      </div>

                      <div className="sm:col-span-2 space-y-1.5">
                        <Label className="text-xs font-semibold text-gray-700 dark:text-gray-300">Database Host / IP Public</Label>
                        <Input
                          value={form.data.remote_db_host}
                          onChange={(e) => form.setData("remote_db_host", e.target.value)}
                          placeholder="cth: 103.144.x.x atau radius.isp-anda.id"
                          className="h-10 rounded-xl border border-gray-200 bg-white px-3 font-mono text-xs text-gray-800 focus:border-brand-500 focus:outline-none dark:border-gray-800 dark:bg-gray-900 dark:text-gray-200"
                        />
                      </div>
                    </div>

                    <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                      <div className="space-y-1.5">
                        <Label className="text-xs font-semibold text-gray-700 dark:text-gray-300">Port Database</Label>
                        <Input
                          type="number"
                          value={form.data.remote_db_port}
                          onChange={(e) => form.setData("remote_db_port", parseInt(e.target.value) || 3306)}
                          placeholder="3306"
                          className="h-10 rounded-xl border border-gray-200 bg-white px-3 font-mono text-xs text-gray-800 focus:border-brand-500 focus:outline-none dark:border-gray-800 dark:bg-gray-900 dark:text-gray-200"
                        />
                      </div>

                      <div className="space-y-1.5">
                        <Label className="text-xs font-semibold text-gray-700 dark:text-gray-300">Nama Database</Label>
                        <Input
                          value={form.data.remote_db_name}
                          onChange={(e) => form.setData("remote_db_name", e.target.value)}
                          placeholder="radius"
                          className="h-10 rounded-xl border border-gray-200 bg-white px-3 font-mono text-xs text-gray-800 focus:border-brand-500 focus:outline-none dark:border-gray-800 dark:bg-gray-900 dark:text-gray-200"
                        />
                      </div>

                      <div className="space-y-1.5">
                        <Label className="text-xs font-semibold text-gray-700 dark:text-gray-300">User Database</Label>
                        <Input
                          value={form.data.remote_db_user}
                          onChange={(e) => form.setData("remote_db_user", e.target.value)}
                          placeholder="radius_user"
                          className="h-10 rounded-xl border border-gray-200 bg-white px-3 font-mono text-xs text-gray-800 focus:border-brand-500 focus:outline-none dark:border-gray-800 dark:bg-gray-900 dark:text-gray-200"
                        />
                      </div>
                    </div>

                    <div className="space-y-1.5">
                      <div className="flex items-center justify-between">
                        <Label className="text-xs font-semibold text-gray-700 dark:text-gray-300">Password Database</Label>
                        <button
                          type="button"
                          onClick={() => setShowDbPass(!showDbPass)}
                          className="text-[11px] text-brand-500 hover:underline inline-flex items-center gap-1 cursor-pointer"
                        >
                          {showDbPass ? <EyeOff className="h-3.5 w-3.5" /> : <Eye className="h-3.5 w-3.5" />}
                          <span>{showDbPass ? "Sembunyikan" : "Tampilkan"}</span>
                        </button>
                      </div>
                      <Input
                        type={showDbPass ? "text" : "password"}
                        value={form.data.remote_db_pass || ""}
                        onChange={(e) => form.setData("remote_db_pass", e.target.value)}
                        placeholder="Password user database FreeRADIUS"
                        className="h-10 rounded-xl border border-gray-200 bg-white px-3 font-mono text-xs text-gray-800 focus:border-brand-500 focus:outline-none dark:border-gray-800 dark:bg-gray-900 dark:text-gray-200"
                      />
                    </div>
                  </div>
                )}

                {form.data.radius_mode === "userman_v7" && (
                  <div className="space-y-4">
                    <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                      <div className="sm:col-span-2 space-y-1.5">
                        <Label className="text-xs font-semibold text-gray-700 dark:text-gray-300">Host / IP MikroTik RouterOS v7</Label>
                        <Input
                          value={form.data.userman_host}
                          onChange={(e) => form.setData("userman_host", e.target.value)}
                          placeholder="cth: 192.168.88.1 atau 103.x.x.x"
                          className="h-10 rounded-xl border border-gray-200 bg-white px-3 font-mono text-xs text-gray-800 focus:border-brand-500 focus:outline-none dark:border-gray-800 dark:bg-gray-900 dark:text-gray-200"
                        />
                      </div>

                      <div className="space-y-1.5">
                        <Label className="text-xs font-semibold text-gray-700 dark:text-gray-300">Port API RouterOS</Label>
                        <Input
                          type="number"
                          value={form.data.userman_port}
                          onChange={(e) => form.setData("userman_port", parseInt(e.target.value) || 8728)}
                          placeholder="8728"
                          className="h-10 rounded-xl border border-gray-200 bg-white px-3 font-mono text-xs text-gray-800 focus:border-brand-500 focus:outline-none dark:border-gray-800 dark:bg-gray-900 dark:text-gray-200"
                        />
                      </div>
                    </div>

                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                      <div className="space-y-1.5">
                        <Label className="text-xs font-semibold text-gray-700 dark:text-gray-300">Username API MikroTik</Label>
                        <Input
                          value={form.data.userman_user}
                          onChange={(e) => form.setData("userman_user", e.target.value)}
                          placeholder="admin"
                          className="h-10 rounded-xl border border-gray-200 bg-white px-3 font-mono text-xs text-gray-800 focus:border-brand-500 focus:outline-none dark:border-gray-800 dark:bg-gray-900 dark:text-gray-200"
                        />
                      </div>

                      <div className="space-y-1.5">
                        <div className="flex items-center justify-between">
                          <Label className="text-xs font-semibold text-gray-700 dark:text-gray-300">Password API MikroTik</Label>
                          <button
                            type="button"
                            onClick={() => setShowUsermanPass(!showUsermanPass)}
                            className="text-[11px] text-brand-500 hover:underline inline-flex items-center gap-1 cursor-pointer"
                          >
                            {showUsermanPass ? <EyeOff className="h-3.5 w-3.5" /> : <Eye className="h-3.5 w-3.5" />}
                            <span>{showUsermanPass ? "Sembunyikan" : "Tampilkan"}</span>
                          </button>
                        </div>
                        <Input
                          type={showUsermanPass ? "text" : "password"}
                          value={form.data.userman_pass || ""}
                          onChange={(e) => form.setData("userman_pass", e.target.value)}
                          placeholder="••••••••"
                          className="h-10 rounded-xl border border-gray-200 bg-white px-3 font-mono text-xs text-gray-800 focus:border-brand-500 focus:outline-none dark:border-gray-800 dark:bg-gray-900 dark:text-gray-200"
                        />
                      </div>
                    </div>
                  </div>
                )}
              </div>
            )}
          </div>

          {/* SECTION 4: DAFTAR ROUTER NAS */}
          <div className="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xs dark:border-gray-800 dark:bg-white/[0.03]">
            <button
              type="button"
              onClick={() => toggleSection("nas")}
              className="w-full p-4 sm:p-5 flex items-center justify-between gap-3 text-left hover:bg-gray-50/50 dark:hover:bg-white/[0.02] transition cursor-pointer"
            >
              <div className="flex items-center gap-3 min-w-0">
                <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400 border border-amber-200 dark:border-amber-500/20">
                  <RouterIcon className="h-4 w-4" />
                </div>
                <div className="min-w-0">
                  <h2 className="text-sm font-bold text-gray-800 dark:text-white truncate">
                    Daftar Router NAS (FreeRADIUS Clients)
                  </h2>
                  <p className="text-[11px] text-gray-500 dark:text-gray-400 truncate">
                    Daftar IP router NAS MikroTik yang diizinkan melakukan autentikasi di tabel <code>nas</code>.
                  </p>
                </div>
              </div>
              <div className="shrink-0 flex items-center gap-2">
                <span className="bg-amber-600 text-white font-bold text-[10px] px-2 py-0.5 rounded-md shadow-xs">
                  {nasClients.length} NAS
                </span>
                <ChevronDown
                  className={cn(
                    "h-4 w-4 text-gray-400 transition-transform duration-200",
                    openSections.nas && "rotate-180 text-brand-500"
                  )}
                />
              </div>
            </button>

            {openSections.nas && (
              <div className="px-4 pb-5 sm:px-5 border-t border-gray-200 dark:border-gray-800 pt-4 space-y-4">
                <div className="flex items-center justify-between">
                  <span className="text-xs text-gray-500 dark:text-gray-400">
                    Router NAS adalah MikroTik PPPoE Server yang mengirim request auth ke FreeRADIUS.
                  </span>
                  <button
                    type="button"
                    onClick={() => setShowAddNas(!showAddNas)}
                    className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border border-gray-200 bg-white text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800 transition shadow-xs"
                  >
                    <Plus className="h-3.5 w-3.5 text-brand-500" />
                    <span>{showAddNas ? "Tutup Form" : "Tambah Router NAS"}</span>
                  </button>
                </div>

                {showAddNas && (
                  <div className="rounded-xl border border-gray-200 bg-gray-50 p-4 space-y-3 dark:border-gray-800 dark:bg-gray-900">
                    <strong className="text-xs text-gray-800 dark:text-white block">Tambah Klien Router NAS Baru</strong>
                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                      <div className="space-y-1">
                        <Label className="text-[11px] text-gray-600 dark:text-gray-300">IP NAS (nasname) *</Label>
                        <Input
                          value={nasForm.nasname}
                          onChange={(e) => setNasForm({ ...nasForm, nasname: e.target.value })}
                          placeholder="cth: 192.168.88.1"
                          className="h-9 rounded-xl border border-gray-200 bg-white text-xs font-mono text-gray-800 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-200"
                        />
                      </div>

                      <div className="space-y-1">
                        <Label className="text-[11px] text-gray-600 dark:text-gray-300">Nama Singkat (shortname)</Label>
                        <Input
                          value={nasForm.shortname}
                          onChange={(e) => setNasForm({ ...nasForm, shortname: e.target.value })}
                          placeholder="mikrotik-core"
                          className="h-9 rounded-xl border border-gray-200 bg-white text-xs font-mono text-gray-800 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-200"
                        />
                      </div>

                      <div className="space-y-1">
                        <Label className="text-[11px] text-gray-600 dark:text-gray-300">Shared Secret *</Label>
                        <Input
                          value={nasForm.secret}
                          onChange={(e) => setNasForm({ ...nasForm, secret: e.target.value })}
                          placeholder="testing123"
                          className="h-9 rounded-xl border border-gray-200 bg-white text-xs font-mono text-gray-800 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-200"
                        />
                      </div>

                      <div className="space-y-1">
                        <Label className="text-[11px] text-gray-600 dark:text-gray-300">Deskripsi / Lokasi</Label>
                        <Input
                          value={nasForm.description}
                          onChange={(e) => setNasForm({ ...nasForm, description: e.target.value })}
                          placeholder="Router OLT Utama"
                          className="h-9 rounded-xl border border-gray-200 bg-white text-xs text-gray-800 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-200"
                        />
                      </div>
                    </div>

                    <div className="flex justify-end gap-2 pt-2">
                      <button
                        type="button"
                        onClick={() => setShowAddNas(false)}
                        className="px-3 py-1.5 rounded-xl border border-gray-200 text-gray-600 hover:bg-gray-100 text-xs dark:border-gray-800 dark:text-gray-400 dark:hover:bg-gray-800"
                      >
                        Batal
                      </button>
                      <button
                        type="button"
                        onClick={handleSaveNas}
                        disabled={!nasForm.nasname || !nasForm.secret}
                        className="px-4 py-1.5 rounded-xl bg-brand-500 hover:bg-brand-600 text-white font-semibold text-xs transition disabled:opacity-50 shadow-xs"
                      >
                        Simpan Router NAS
                      </button>
                    </div>
                  </div>
                )}

                {nasClients.length === 0 ? (
                  <div className="rounded-xl border border-dashed border-gray-200 p-6 text-center text-xs text-gray-500 dark:border-gray-800 dark:text-gray-400">
                    Belum ada router NAS terdaftar di tabel <code>nas</code> FreeRADIUS.
                  </div>
                ) : (
                  <div className="w-full overflow-x-auto custom-scrollbar">
                    <table className="w-full text-left text-xs min-w-[900px] border-collapse">
                      <thead className="bg-gray-50/75 border-b border-gray-200 text-gray-500 font-semibold dark:bg-gray-800/50 dark:border-gray-800 dark:text-gray-400">
                        <tr>
                          <th className="p-3">IP NAS Router</th>
                          <th className="p-3">Shortname</th>
                          <th className="p-3">Tipe</th>
                          <th className="p-3">Shared Secret</th>
                          <th className="p-3">Deskripsi</th>
                          <th className="p-3 text-end sm:px-4">Aksi</th>
                        </tr>
                      </thead>
                      <tbody className="divide-y divide-gray-200 dark:divide-gray-800 text-gray-700 dark:text-gray-300">
                        {nasClients.map((nas) => (
                          <tr key={nas.id} className="hover:bg-gray-50/50 dark:hover:bg-white/[0.02] transition">
                            <td className="p-3 font-mono font-bold text-gray-800 dark:text-white">{nas.nasname}</td>
                            <td className="p-3 font-mono text-gray-500">{nas.shortname || "-"}</td>
                            <td className="p-3">{nas.type || "other"}</td>
                            <td className="p-3 font-mono text-gray-400">••••••••</td>
                            <td className="p-3 text-gray-500">{nas.description || "-"}</td>
                            <td className="p-3 text-end sm:px-4">
                              <button
                                type="button"
                                onClick={() => handleDeleteNas(nas.id)}
                                className="p-1.5 rounded-lg text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-950/40 transition cursor-pointer whitespace-nowrap"
                                title="Hapus Router NAS"
                              >
                                <Trash2 className="h-4 w-4" />
                              </button>
                            </td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                )}
              </div>
            )}
          </div>

          {/* SECTION 5: UJI COBA RFC 3576 COA DISCONNECT */}
          <div className="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xs dark:border-gray-800 dark:bg-white/[0.03]">
            <button
              type="button"
              onClick={() => toggleSection("coa")}
              className="w-full p-4 sm:p-5 flex items-center justify-between gap-3 text-left hover:bg-gray-50/50 dark:hover:bg-white/[0.02] transition cursor-pointer"
            >
              <div className="flex items-center gap-3 min-w-0">
                <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-rose-50 text-rose-600 dark:bg-rose-500/10 dark:text-rose-400 border border-rose-200 dark:border-rose-500/20">
                  <Activity className="h-4 w-4" />
                </div>
                <div className="min-w-0">
                  <h2 className="text-sm font-bold text-gray-800 dark:text-white truncate">
                    Uji Coba RFC 3576 CoA / Packet of Disconnect
                  </h2>
                  <p className="text-[11px] text-gray-500 dark:text-gray-400 truncate">
                    Kirim paket Disconnect-Request instan via socket UDP 3799 untuk memutuskan sesi PPPoE aktif.
                  </p>
                </div>
              </div>
              <div className="shrink-0 flex items-center gap-2">
                <span className="bg-rose-500 text-white font-bold text-[10px] px-2 py-0.5 rounded-md shadow-xs font-mono">
                  UDP 3799
                </span>
                <ChevronDown
                  className={cn(
                    "h-4 w-4 text-gray-400 transition-transform duration-200",
                    openSections.coa && "rotate-180 text-brand-500"
                  )}
                />
              </div>
            </button>

            {openSections.coa && (
              <div className="px-4 pb-5 sm:px-5 border-t border-gray-200 dark:border-gray-800 pt-4 space-y-4">
                <div className="rounded-xl border border-gray-200 bg-gray-50/50 p-4 space-y-3 dark:border-gray-800 dark:bg-gray-900">
                  <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div className="space-y-1.5">
                      <Label className="text-xs font-semibold text-gray-700 dark:text-gray-300">PPPoE Username Target</Label>
                      <Input
                        value={coaTargetUser}
                        onChange={(e) => setCoaTargetUser(e.target.value)}
                        placeholder="cth: user-pppoe-01"
                        className="h-10 rounded-xl border border-gray-200 bg-white px-3 font-mono text-xs text-gray-800 focus:border-brand-500 focus:outline-none dark:border-gray-800 dark:bg-gray-900 dark:text-gray-200"
                      />
                    </div>

                    <div className="space-y-1.5">
                      <Label className="text-xs font-semibold text-gray-700 dark:text-gray-300">IP Router NAS Target</Label>
                      <Input
                        value={coaTargetNas}
                        onChange={(e) => setCoaTargetNas(e.target.value)}
                        placeholder="cth: 192.168.88.1 (opsional)"
                        className="h-10 rounded-xl border border-gray-200 bg-white px-3 font-mono text-xs text-gray-800 focus:border-brand-500 focus:outline-none dark:border-gray-800 dark:bg-gray-900 dark:text-gray-200"
                      />
                    </div>

                    <div className="space-y-1.5">
                      <Label className="text-xs font-semibold text-gray-700 dark:text-gray-300">IP Pelanggan (Framed IP)</Label>
                      <Input
                        value={coaTargetIp}
                        onChange={(e) => setCoaTargetIp(e.target.value)}
                        placeholder="cth: 10.10.10.25 (opsional)"
                        className="h-10 rounded-xl border border-gray-200 bg-white px-3 font-mono text-xs text-gray-800 focus:border-brand-500 focus:outline-none dark:border-gray-800 dark:bg-gray-900 dark:text-gray-200"
                      />
                    </div>
                  </div>

                  <div className="flex items-center justify-between pt-2 border-t border-gray-200 dark:border-gray-800">
                    <span className="text-[11px] text-gray-500 dark:text-gray-400">
                      Pastikan <b>/radius incoming set accept=yes port=3799</b> aktif di MikroTik Anda.
                    </span>
                    <button
                      type="button"
                      onClick={() => handleSendCoaDisconnect(coaTargetUser, coaTargetNas, coaTargetIp)}
                      disabled={!coaTargetUser || coaLoading}
                      className="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-rose-500 hover:bg-rose-600 text-white font-bold text-xs transition disabled:opacity-50 cursor-pointer shadow-xs"
                    >
                      {coaLoading ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : <Activity className="h-3.5 w-3.5" />}
                      <span>Kirim Disconnect CoA</span>
                    </button>
                  </div>

                  {coaResult && (
                    <div
                      className={cn(
                        "p-3 rounded-xl border text-xs leading-relaxed mt-2",
                        coaResult.success
                          ? "bg-emerald-50 border-emerald-200 text-emerald-800 dark:bg-emerald-950/40 dark:border-emerald-500/30 dark:text-emerald-300"
                          : "bg-rose-50 border-rose-200 text-rose-800 dark:bg-rose-950/40 dark:border-rose-500/30 dark:text-rose-300"
                      )}
                    >
                      <div className="font-bold flex items-center gap-1.5">
                        {coaResult.success ? (
                          <CheckCircle2 className="h-4 w-4 text-emerald-600 dark:text-emerald-400" />
                        ) : (
                          <AlertCircle className="h-4 w-4 text-rose-600 dark:text-rose-400" />
                        )}
                        <span>{coaResult.success ? "ACK Diterima (Sesi Berhasil Diputus)" : "NAK / Timeout"}</span>
                      </div>
                      <div className="mt-1 opacity-90 pl-5">{coaResult.message}</div>
                    </div>
                  )}
                </div>
              </div>
            )}
          </div>

          {/* SECTION 6: SESI ONLINE AKTIF REAL-TIME */}
          <div className="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xs dark:border-gray-800 dark:bg-white/[0.03]">
            <button
              type="button"
              onClick={() => toggleSection("sessions")}
              className="w-full p-4 sm:p-5 flex items-center justify-between gap-3 text-left hover:bg-gray-50/50 dark:hover:bg-white/[0.02] transition cursor-pointer"
            >
              <div className="flex items-center gap-3 min-w-0">
                <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-500/20">
                  <Activity className="h-4 w-4" />
                </div>
                <div className="min-w-0">
                  <h2 className="text-sm font-bold text-gray-800 dark:text-white truncate">
                    Sesi Online Aktif Real-time (radacct)
                  </h2>
                  <p className="text-[11px] text-gray-500 dark:text-gray-400 truncate">
                    Monitoring sesi pelanggan yang sedang tersambung, durasi online, IP framed, dan konsumsi bandwidth.
                  </p>
                </div>
              </div>
              <div className="shrink-0 flex items-center gap-2">
                <span className="bg-emerald-500 text-white font-bold text-[10px] px-2 py-0.5 rounded-md shadow-xs">
                  {sessions.length} Online
                </span>
                <ChevronDown
                  className={cn(
                    "h-4 w-4 text-gray-400 transition-transform duration-200",
                    openSections.sessions && "rotate-180 text-brand-500"
                  )}
                />
              </div>
            </button>

            {openSections.sessions && (
              <div className="px-4 pb-5 sm:px-5 border-t border-gray-200 dark:border-gray-800 pt-4 space-y-4">
                <div className="relative">
                  <Search className="absolute left-3 top-2.5 h-4 w-4 text-gray-400" />
                  <Input
                    value={sessionSearch}
                    onChange={(e) => setSessionSearch(e.target.value)}
                    placeholder="Cari berdasarkan username, IP address, MAC, atau NAS..."
                    className="h-10 pl-9 rounded-xl border border-gray-200 bg-white text-xs text-gray-800 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-200"
                  />
                </div>

                {filteredSessions.length === 0 ? (
                  <div className="rounded-xl border border-dashed border-gray-200 p-8 text-center text-xs text-gray-500 dark:border-gray-800 dark:text-gray-400">
                    Tidak ada sesi PPPoE aktif yang ditemukan di database accounting RADIUS.
                  </div>
                ) : (
                  <div className="w-full overflow-x-auto custom-scrollbar">
                    <table className="w-full text-left text-xs min-w-[1000px] border-collapse">
                      <thead className="bg-gray-50/75 border-b border-gray-200 text-gray-500 font-semibold dark:bg-gray-800/50 dark:border-gray-800 dark:text-gray-400">
                        <tr>
                          <th className="p-3">Status Sesi</th>
                          <th className="p-3">Username</th>
                          <th className="p-3">Profil / Group</th>
                          <th className="p-3">Framed IP</th>
                          <th className="p-3">MAC Address</th>
                          <th className="p-3">NAS Router</th>
                          <th className="p-3">Durasi</th>
                          <th className="p-3">Traffic (In/Out)</th>
                          <th className="p-3 text-end sm:px-4">Aksi CoA</th>
                        </tr>
                      </thead>
                      <tbody className="divide-y divide-gray-200 dark:divide-gray-800 text-gray-700 dark:text-gray-300 font-mono">
                        {filteredSessions.map((s, idx) => (
                          <tr key={s.radacctid || idx} className="hover:bg-gray-50/50 dark:hover:bg-white/[0.02] transition">
                            <td className="p-3">
                              <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold bg-emerald-500 text-white shadow-xs font-sans whitespace-nowrap">
                                Online
                              </span>
                            </td>
                            <td className="p-3">
                              <strong className="text-gray-800 dark:text-white block font-mono">{s.username}</strong>
                            </td>
                            <td className="p-3 font-sans">
                              <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold bg-brand-500 text-white shadow-xs whitespace-nowrap">
                                {s.groupname || "Default"}
                              </span>
                            </td>
                            <td className="p-3 font-mono">
                              <span className="text-brand-600 dark:text-brand-400 font-bold">{s.framedipaddress || "-"}</span>
                            </td>
                            <td className="p-3 font-mono text-gray-500 text-[11px]">
                              {s.mac_address || "-"}
                            </td>
                            <td className="p-3 font-mono text-gray-600 dark:text-gray-400">
                              {s.nasipaddress || "-"}
                            </td>
                            <td className="p-3 text-gray-600 dark:text-gray-300 font-sans">
                              {formatDuration(s.acctsessiontime)}
                            </td>
                            <td className="p-3 font-mono text-[11px]">
                              <span className="text-emerald-600 dark:text-emerald-400">↓ {formatBytes(s.acctoutputoctets)}</span>
                              <span className="text-gray-400 mx-1">|</span>
                              <span className="text-indigo-600 dark:text-indigo-400">↑ {formatBytes(s.acctinputoctets)}</span>
                            </td>
                            <td className="p-3 text-end sm:px-4">
                              <button
                                type="button"
                                onClick={() => handleSendCoaDisconnect(s.username, s.nasipaddress, s.framedipaddress)}
                                disabled={coaLoading}
                                className="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-rose-500 hover:bg-rose-600 text-white text-[11px] font-bold transition cursor-pointer shadow-xs whitespace-nowrap"
                                title="Putuskan Sesi via RFC 3576 CoA"
                              >
                                <Activity className="h-3 w-3" />
                                <span>Kick</span>
                              </button>
                            </td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                )}
              </div>
            )}
          </div>

          {/* SECTION 7: DAFTAR PENGGUNA RADIUS */}
          <div className="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xs dark:border-gray-800 dark:bg-white/[0.03]">
            <button
              type="button"
              onClick={() => toggleSection("users")}
              className="w-full p-4 sm:p-5 flex items-center justify-between gap-3 text-left hover:bg-gray-50/50 dark:hover:bg-white/[0.02] transition cursor-pointer"
            >
              <div className="flex items-center gap-3 min-w-0">
                <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-600 dark:bg-blue-500/10 dark:text-blue-400 border border-blue-200 dark:border-blue-500/20">
                  <Users className="h-4 w-4" />
                </div>
                <div className="min-w-0">
                  <h2 className="text-sm font-bold text-gray-800 dark:text-white truncate">
                    Daftar Akun Pengguna RADIUS (radcheck / radusergroup)
                  </h2>
                  <p className="text-[11px] text-gray-500 dark:text-gray-400 truncate">
                    Daftar akun PPPoE yang tersinkronisasi di database FreeRADIUS beserta profil bandwidth grup.
                  </p>
                </div>
              </div>
              <div className="shrink-0 flex items-center gap-2">
                <span className="bg-brand-500 text-white font-bold text-[10px] px-2 py-0.5 rounded-md shadow-xs">
                  {users.length} Users
                </span>
                <ChevronDown
                  className={cn(
                    "h-4 w-4 text-gray-400 transition-transform duration-200",
                    openSections.users && "rotate-180 text-brand-500"
                  )}
                />
              </div>
            </button>

            {openSections.users && (
              <div className="px-4 pb-5 sm:px-5 border-t border-gray-200 dark:border-gray-800 pt-4 space-y-4">
                <div className="flex flex-col sm:flex-row items-center justify-between gap-3">
                  <div className="relative w-full sm:max-w-xs">
                    <Search className="absolute left-3 top-2.5 h-4 w-4 text-gray-400" />
                    <Input
                      value={userSearch}
                      onChange={(e) => setUserSearch(e.target.value)}
                      placeholder="Cari user atau grup..."
                      className="h-10 pl-9 rounded-xl border border-gray-200 bg-white text-xs text-gray-800 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-200"
                    />
                  </div>

                  <button
                    type="button"
                    onClick={handleSyncAll}
                    disabled={syncLoading}
                    className="w-full sm:w-auto inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl border border-gray-200 bg-white text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800 transition shadow-xs cursor-pointer"
                  >
                    <RefreshCw className={cn("h-3.5 w-3.5 text-brand-500", syncLoading && "animate-spin")} />
                    <span>Sinkronkan Semua PPPoE ke RADIUS</span>
                  </button>
                </div>

                {filteredUsers.length === 0 ? (
                  <div className="rounded-xl border border-dashed border-gray-200 p-8 text-center text-xs text-gray-500 dark:border-gray-800 dark:text-gray-400">
                    Belum ada akun pengguna PPPoE di tabel FreeRADIUS. Klik &quot;Sinkronkan Semua PPPoE&quot; untuk menyinkronkan data.
                  </div>
                ) : (
                  <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2.5">
                    {filteredUsers.map((u, idx) => (
                      <div
                        key={u.id || idx}
                        className="rounded-xl border border-gray-200 bg-gray-50/50 p-3 flex items-center justify-between gap-2 dark:border-gray-800 dark:bg-gray-900"
                      >
                        <div className="min-w-0">
                          <strong className="text-xs text-gray-800 dark:text-white block font-mono truncate">{u.username}</strong>
                          <span className="text-[10px] text-gray-500 dark:text-gray-400 block mt-0.5">
                            Grup: <span className="text-brand-600 dark:text-brand-400 font-semibold">{u.groupname || "Default"}</span>
                          </span>
                        </div>
                        <span className="shrink-0 h-2 w-2 rounded-full bg-emerald-500 whitespace-nowrap" />
                      </div>
                    ))}
                  </div>
                )}
              </div>
            )}
          </div>
        </form>
      </div>
    </AppLayout>
  )
}
