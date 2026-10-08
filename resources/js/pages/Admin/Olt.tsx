import React, { useState, useMemo } from "react"
import { AppLayout } from "@/components/layout/app-layout"
import {
  Server,
  Plus,
  Wifi,
  RefreshCw,
  Pencil,
  Trash2,
  CheckCircle2,
  AlertCircle,
  X,
  XCircle,
  Search,
  ExternalLink,
  Activity,
} from "lucide-react"
import axios from "axios"
import { PageProps } from "@/types"
import { cn } from "@/lib/utils"
import { adminSidebarItems, adminNavItems, adminBrand } from "@/lib/admin-nav"
import { useForm, router, Link } from "@inertiajs/react"
import { MetricCard } from "@/components/tailadmin/MetricCard"
import Modal from "@/components/tailadmin/Modal"
import { CreateSheet, type FieldDef } from "@/components/ui/create-sheet"
import { EmptyState } from "@/components/ui/empty-state"
import { ViewModeSwitcher } from "@/components/ui/view-mode-switcher"

interface HardwareMetrics {
  cpu?: number | null
  ram?: number | null
  ram_used_mb?: number | null
  ram_total_mb?: number | null
  temp?: number | null
  uptime?: string | null
  sys_name?: string | null
  sys_descr?: string | null
  pon_ports?: Record<string, { total: number; online: number; offline: number }>
}

interface Olt {
  id: number
  name: string
  host: string
  port: number
  snmp_port: number
  telnet_port: number
  username?: string
  snmp_community: string
  brand?: string
  model: string
  submodel: string | null
  connection_mode: "snmp" | "telnet" | "hybrid"
  location: string | null
  is_active: boolean
  onu_count: number
  last_poll_status: string | null
  last_poll_at: string | null
  hardware_metrics?: HardwareMetrics | null
}

export default function OltPage({
  olts = [],
  create = false,
  companyName = "NODERA Billing",
  tenantName,
}: PageProps<{
  olts: Olt[]
  create: boolean
  companyName?: string
  tenantName?: string
}>) {
  const [sheetOpen, setSheetOpen] = useState(create ?? false)
  const [searchQuery, setSearchQuery] = useState("")
  const [viewMode, setViewMode] = useState<"grid" | "table">("grid")
  const [editingOlt, setEditingOlt] = useState<Olt | null>(null)
  const [managingOlt, setManagingOlt] = useState<Olt | null>(null)

  const filteredOlts = useMemo(() => {
    if (!searchQuery.trim()) return olts
    const q = searchQuery.toLowerCase().trim()
    return olts.filter((o) => {
      const name = (o.name || "").toLowerCase()
      const host = (o.host || "").toLowerCase()
      const brand = (o.brand || "").toLowerCase()
      const model = (o.model || "").toLowerCase()
      return name.includes(q) || host.includes(q) || brand.includes(q) || model.includes(q)
    })
  }, [olts, searchQuery])

  const [testModalOpen, setTestModalOpen] = useState(false)
  const [testingOlt, setTestingOlt] = useState<Olt | null>(null)
  const [isTestingOlt, setIsTestingOlt] = useState(false)
  const [testResult, setTestResult] = useState<any>(null)
  const [testError, setTestError] = useState<string | null>(null)
  const [syncingIds, setSyncingIds] = useState<Record<number, boolean>>({})

  const handleSyncOlt = async (o: Olt) => {
    setSyncingIds((prev) => ({ ...prev, [o.id]: true }))
    try {
      const res = await axios.post(`/admin/olt/sync/${o.id}`, {}, {
        headers: { "X-Live-Sync": "true" },
      })
      if (res.data && res.data.success) {
        ;(router.reload as any)({
          preserveScroll: true,
          preserveState: true,
        })
      } else {
        alert(res.data?.message || "Gagal menyinkronkan data OLT.")
      }
    } catch (err: any) {
      console.error(err)
      alert(err.response?.data?.message || "Gagal menyinkronkan data OLT. Periksa koneksi dan kredensial OLT.")
    } finally {
      setSyncingIds((prev) => ({ ...prev, [o.id]: false }))
    }
  }

  const openTestOlt = async (o: Olt) => {
    setTestingOlt(o)
    setTestResult(null)
    setTestError(null)
    setTestModalOpen(true)
    setIsTestingOlt(true)

    try {
      const res = await axios.post("/admin/olt/test-connection", {
        olt_id: o.id,
      })
      if (res.data && res.data.success) {
        setTestResult(res.data.olt_info)
      } else {
        setTestError(res.data.message || "Gagal menghubungi OLT.")
      }
    } catch (err: any) {
      setTestError(
        err.response?.data?.message ||
        err.response?.data?.snmp_error ||
        err.message ||
        "Tidak dapat terhubung ke OLT."
      )
    } finally {
      setIsTestingOlt(false)
    }
  }

  const totalOlts = olts.length
  const totalOnus = olts.reduce((acc, o) => acc + (o.onu_count || 0), 0)
  const activeOlts = olts.filter((o) => o.is_active).length
  const offlineOlts = totalOlts - activeOlts

  const form = useForm({
    name: "",
    host: "",
    connection_mode: "snmp",
    port: "161",
    snmp_port: "161",
    telnet_port: "23",
    username: "admin",
    password: "",
    enable_password: "",
    snmp_community: "public",
    model: "hioso",
    submodel: "",
    location: "",
  })

  const openAdd = () => {
    setEditingOlt(null)
    form.reset()
    form.setData({
      name: "",
      host: "",
      connection_mode: "snmp",
      port: "161",
      snmp_port: "161",
      telnet_port: "23",
      username: "admin",
      password: "",
      enable_password: "",
      snmp_community: "public",
      model: "hioso",
      submodel: "",
      location: "",
    })
    setSheetOpen(true)
  }

  const openEdit = (o: Olt) => {
    setEditingOlt(o)
    setManagingOlt(null)
    form.setData({
      name: o.name,
      host: o.host,
      connection_mode: o.connection_mode || "snmp",
      port: String(o.port || 161),
      snmp_port: String(o.snmp_port || 161),
      telnet_port: String(o.telnet_port || 23),
      username: o.username || "admin",
      password: "",
      enable_password: "",
      snmp_community: o.snmp_community || "public",
      model: o.model || "hioso",
      submodel: o.submodel || "",
      location: o.location || "",
    })
    setSheetOpen(true)
  }

  const submit = (e: React.FormEvent) => {
    e.preventDefault()
    if (editingOlt) {
      form.post(`/admin/olt/edit/${editingOlt.id}`, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
          setSheetOpen(false)
        },
      })
    } else {
      form.post("/admin/olt/add", {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
          setSheetOpen(false)
        },
      })
    }
  }

  const fields: FieldDef[] = useMemo(() => {
    const baseFields: FieldDef[] = [
      { name: "name", label: "Nama OLT", required: true, placeholder: "cth: OLT HIOSO POP-01" },
      { name: "host", label: "IP Host / Domain OLT", required: true, placeholder: "192.168.8.100" },
      {
        name: "model",
        label: "Merek / Tipe OLT",
        type: "select",
        required: true,
        options: [
          { value: "hioso", label: "HIOSO (EPON 2/4/8 Port)" },
          { value: "hsgq", label: "HSGQ (EPON / GPON)" },
          { value: "zte", label: "ZTE (C300 / C320 GPON)" },
          { value: "huawei", label: "Huawei (MA5680T / MA5608T)" },
          { value: "vsol", label: "V-SOL (V1600 Series EPON/GPON)" },
          { value: "bdcom", label: "BDCOM (P3310 / P3600)" },
          { value: "cdata", label: "C-Data (FD1104 / FD1208)" },
          { value: "fiberhome", label: "Fiberhome (AN5516 / AN5116)" },
          { value: "other", label: "Generic / SNMP Standard MIB" },
        ],
      },
      {
        name: "submodel",
        label: "Submodel / OID Preset (Opsional)",
        placeholder: "cth: VSOL_EPON / VSOL_GPON (Kosong = Auto-Detect)",
      },
      {
        name: "connection_mode",
        label: "Mode Koneksi",
        type: "select",
        options: [
          { value: "snmp", label: "SNMP Only (Monitoring & Sinyal - Rekomendasi)" },
          { value: "telnet", label: "Telnet / CLI Only" },
          { value: "hybrid", label: "Hybrid (SNMP Monitoring + Telnet Provisioning)" },
        ],
      },
    ]

    const snmpFields: FieldDef[] = [
      { name: "snmp_community", label: "SNMP Community (Default: public)", placeholder: "public" },
      { name: "snmp_port", label: "SNMP Port (Default: 161)", type: "number", placeholder: "161" },
    ]

    const telnetFields: FieldDef[] = [
      { name: "telnet_port", label: "Telnet Port (Default: 23)", type: "number", placeholder: "23" },
      { name: "username", label: "Username Telnet/CLI", placeholder: "admin" },
      { name: "password", label: "Password Telnet/CLI", type: "password", placeholder: "••••••" },
      { name: "enable_password", label: "Enable Password (Opsional)", type: "password", placeholder: "••••••" },
    ]

    const locationField: FieldDef = {
      name: "location",
      label: "Lokasi OLT",
      placeholder: "Rack Server 1 / POP Pusat",
    }

    if (form.data.connection_mode === "snmp") {
      return [...baseFields, ...snmpFields, locationField]
    } else if (form.data.connection_mode === "telnet") {
      return [...baseFields, ...telnetFields, locationField]
    } else {
      return [...baseFields, ...snmpFields, ...telnetFields, locationField]
    }
  }, [form.data.connection_mode])

  return (
    <AppLayout
      title="Kelola OLT"
      brand={adminBrand}
      sidebarItems={adminSidebarItems}
      navItems={adminNavItems}
    >
      <div className="space-y-6">
        {/* Top 4 KPI Metrics */}
        <div className="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
          <MetricCard
            title="Total Perangkat OLT"
            value={`${totalOlts} OLT`}
            icon={<Server className="h-6 w-6 text-brand-500" />}
            sub="Gateway OLT terdaftar"
          />
          <MetricCard
            title="OLT Online"
            value={`${activeOlts} Unit`}
            icon={<CheckCircle2 className="h-6 w-6 text-emerald-500" />}
            sub="Koneksi SNMP/CLI aktif"
          />
          <MetricCard
            title="Total ONU Terdaftar"
            value={`${totalOnus} ONU`}
            icon={<Wifi className="h-6 w-6 text-blue-500" />}
            sub="Modem optic pelanggan"
          />
          <MetricCard
            title="OLT Offline"
            value={`${offlineOlts} Unit`}
            icon={<AlertCircle className="h-6 w-6 text-rose-500" />}
            sub="Perangkat terputus"
          />
        </div>

        {/* ── 1-LINE RESPONSIVE INTEGRATED TOOLBAR ── */}
        <div className="rounded-2xl border border-gray-200 bg-white p-3 sm:p-4 shadow-xs dark:border-gray-800 dark:bg-white/[0.03] flex flex-col gap-2.5 sm:gap-3 lg:flex-row lg:items-center lg:justify-between">
          <div className="flex flex-wrap items-center gap-2 sm:gap-3 flex-1 min-w-0">
            <div className="relative flex-1 min-w-[180px] sm:min-w-[220px] w-full sm:w-auto">
              <Search className="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400 dark:text-gray-500" />
              <input
                type="text"
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                placeholder="Cari nama OLT, IP host, merek, atau model..."
                className="h-10 w-full rounded-xl border border-gray-200 bg-gray-50/75 pl-9 pr-8 text-xs text-gray-800 placeholder:text-gray-400 focus:border-brand-500 focus:outline-none dark:border-gray-800 dark:bg-gray-900/50 dark:text-gray-200 dark:placeholder:text-gray-500 font-medium transition"
              />
              {searchQuery && (
                <button
                  type="button"
                  onClick={() => setSearchQuery("")}
                  className="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 cursor-pointer"
                >
                  <X className="h-3.5 w-3.5" />
                </button>
              )}
            </div>
          </div>

          <div className="flex items-center gap-2 w-full sm:w-auto">
            <div className="shrink-0">
              <ViewModeSwitcher value={viewMode} onChange={setViewMode} size="sm" />
            </div>

            <button
              type="button"
              onClick={openAdd}
              className="h-10 px-3.5 sm:px-4 rounded-xl bg-brand-500 hover:bg-brand-600 active:scale-95 text-xs font-bold text-white shadow-xs flex items-center justify-center gap-1.5 flex-1 sm:flex-initial shrink-0 cursor-pointer"
            >
              <Plus className="h-4 w-4" />
              <span>Tambah OLT</span>
            </button>
          </div>
        </div>

        {/* Master Card */}
        <div className="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xs dark:border-gray-800 dark:bg-white/[0.03]">
          {/* OLT Content */}
          <div className="p-4 sm:p-5">
            {filteredOlts.length === 0 ? (
              <div className="p-8 rounded-2xl border border-dashed border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-900/50 text-center">
                <EmptyState
                  icon={<Server className="h-8 w-8 text-brand-500" />}
                  title={searchQuery ? "Tidak ada OLT yang cocok" : "Belum ada perangkat OLT"}
                  description={
                    searchQuery
                      ? "Coba sesuaikan kata kunci pencarian Anda."
                      : "Daftarkan OLT HIOSO, HSGQ, ZTE, Huawei, atau V-SOL Anda untuk monitoring redaman optik fiber."
                  }
                />
              </div>
            ) : viewMode === "grid" ? (
              <div className="grid items-start gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-3 2xl:grid-cols-4">
                {filteredOlts.map((o: Olt) => {
                  return (
                    <div
                      key={o.id}
                      className="rounded-2xl border border-gray-200 bg-white p-4 sm:p-5 transition shadow-xs dark:border-gray-800 dark:bg-white/[0.02] space-y-3.5"
                    >
                      {/* Top Header Card */}
                      <div className="flex items-start justify-between gap-2">
                        <div className="flex items-center gap-3 min-w-0 flex-1">
                          <div className="relative flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gray-100 border border-gray-200 text-gray-700 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-200 font-bold">
                            <Server className="h-5 w-5 text-gray-600 dark:text-gray-300" />
                            {o.is_active ? (
                              <span className="absolute -bottom-0.5 -right-0.5 h-2.5 w-2.5 rounded-full bg-emerald-500 ring-2 ring-white dark:ring-gray-900 whitespace-nowrap" />
                            ) : (
                              <span className="absolute -bottom-0.5 -right-0.5 h-2.5 w-2.5 rounded-full bg-gray-400 ring-2 ring-white dark:ring-gray-900" />
                            )}
                          </div>
                          <div className="min-w-0 flex-1">
                            <h4 className="text-sm font-bold text-gray-900 dark:text-white truncate">
                              {o.name}
                            </h4>
                            <p className="font-mono text-[11px] text-gray-500 dark:text-gray-400 truncate">
                              {o.host}:{o.snmp_port || o.port || 161}
                            </p>
                          </div>
                        </div>

                        {/* Top Right: Status Badge */}
                        <span
                          className={cn(
                            o.is_active
                              ? "inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-bold bg-emerald-500 text-white shadow-xs whitespace-nowrap"
                              : "inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-bold bg-gray-500 text-white shadow-xs whitespace-nowrap"
                          )}
                        >
                          {o.is_active ? "Online" : "Offline"}
                        </span>
                      </div>

                      {/* Hardware & Protocol Specs with Sunken Box */}
                      <div className="grid grid-cols-2 gap-2 rounded-xl border border-gray-100 bg-gray-50/80 p-2.5 text-xs text-gray-600 dark:border-gray-800 dark:bg-gray-900/50 dark:text-gray-400">
                        <div>
                          <span className="text-[10px] text-gray-400 dark:text-gray-500 block mb-0.5">Merk / Tipe</span>
                          <strong className="text-gray-800 dark:text-gray-200 uppercase font-mono">{o.model}</strong>
                        </div>
                        <div className="text-right">
                          <span className="text-[10px] text-gray-400 dark:text-gray-500 block mb-0.5">Mode</span>
                          <strong className="text-gray-800 dark:text-gray-200 uppercase font-mono">{o.connection_mode}</strong>
                        </div>
                      </div>

                      {/* ONU Capacity Bar & Action Footer */}
                      <div className="pt-2 border-t border-gray-100 dark:border-gray-800 flex items-center justify-between gap-2 text-xs">
                        <div>
                          <span className="text-gray-500 dark:text-gray-400 font-medium">Terdaftar: </span>
                          <span className="font-mono font-bold text-brand-600 dark:text-brand-400">
                            {o.onu_count || 0} ONU
                          </span>
                        </div>

                        {/* Single Action Button: Kelola */}
                        <button
                          type="button"
                          onClick={() => setManagingOlt(o)}
                          className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200 font-bold text-xs transition cursor-pointer"
                        >
                          <span>Kelola</span>
                        </button>
                      </div>
                    </div>
                  )
                })}
              </div>
            ) : (
              <div className="w-full overflow-x-auto custom-scrollbar">
                <table className="w-full text-left text-xs sm:text-sm min-w-[950px] border-collapse">
                  <thead className="border-b border-gray-200 bg-gray-50/75 text-xs font-semibold text-gray-500 uppercase tracking-wider dark:border-gray-800 dark:bg-gray-900/50 dark:text-gray-400">
                    <tr>
                      <th className="px-4 py-3 sm:px-6">Status Koneksi</th>
                      <th className="px-4 py-3">Nama OLT</th>
                      <th className="px-4 py-3">Host IP &amp; Port</th>
                      <th className="px-4 py-3">Merk / Tipe</th>
                      <th className="px-4 py-3">Mode Koneksi</th>
                      <th className="px-4 py-3">Total ONU</th>
                      <th className="px-4 py-3">Lokasi</th>
                      <th className="px-4 py-3 text-end sm:px-6">Aksi</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-gray-100 dark:divide-gray-800/60">
                    {filteredOlts.map((o: Olt) => (
                      <tr key={o.id} className="hover:bg-gray-50/50 dark:hover:bg-white/[0.02] transition-colors">
                        <td className="px-4 py-3.5 sm:px-6">
                          <span
                            className={cn(
                              o.is_active
                                ? "inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-bold bg-emerald-500 text-white shadow-xs whitespace-nowrap"
                                : "inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-bold bg-gray-500 text-white shadow-xs whitespace-nowrap"
                            )}
                          >
                            {o.is_active ? "Online" : "Offline"}
                          </span>
                        </td>
                        <td className="px-4 py-3.5 font-bold text-gray-900 dark:text-white">
                          {o.name}
                        </td>
                        <td className="px-4 py-3.5 font-mono text-xs text-gray-700 dark:text-gray-300">
                          {o.host}:{o.snmp_port || o.port || 161}
                        </td>
                        <td className="px-4 py-3.5">
                          <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold bg-brand-500 text-white shadow-xs uppercase font-mono">
                            {o.model}
                          </span>
                        </td>
                        <td className="px-4 py-3.5 font-mono text-xs uppercase text-gray-700 dark:text-gray-300">
                          {o.connection_mode}
                        </td>
                        <td className="px-4 py-3.5 font-mono font-bold text-brand-600 dark:text-brand-400">
                          {o.onu_count || 0} ONU
                        </td>
                        <td className="px-4 py-3.5 text-xs text-gray-500 dark:text-gray-400">
                          {o.location || "POP Pusat"}
                        </td>
                        <td className="px-4 py-3.5 sm:px-6 text-end">
                          <button
                            type="button"
                            onClick={() => setManagingOlt(o)}
                            className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200 font-bold text-xs transition cursor-pointer"
                          >
                            <span>Kelola</span>
                          </button>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </div>
        </div>
      </div>

      {/* ── INTERACTIVE KELOLA MODAL (1:1 SUPERADMIN TAILADMIN) ── */}
      {managingOlt && (
        <div
          className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-xs animate-in fade-in"
          onClick={() => setManagingOlt(null)}
        >
          <div
            className="w-full max-w-md max-h-[92vh] overflow-y-auto rounded-2xl border border-gray-200 bg-white p-6 shadow-2xl dark:border-gray-800 dark:bg-gray-900 space-y-5"
            onClick={(e) => e.stopPropagation()}
          >
            {/* Modal Header */}
            <div className="flex items-start justify-between">
              <div className="flex items-center gap-3">
                <div className="flex h-12 w-12 items-center justify-center rounded-2xl bg-brand-500/10 text-brand-500 font-bold">
                  <Server className="h-6 w-6" />
                </div>
                <div>
                  <h3 className="text-base font-bold text-gray-900 dark:text-white">
                    {managingOlt.name}
                  </h3>
                  <p className="text-xs font-mono text-gray-500 dark:text-gray-400">
                    {managingOlt.host}:{managingOlt.snmp_port || managingOlt.port || 161}
                  </p>
                </div>
              </div>
              <button
                type="button"
                onClick={() => setManagingOlt(null)}
                className="rounded-xl p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-800 cursor-pointer"
              >
                <X className="h-5 w-5" />
              </button>
            </div>

            {/* Status Badges Row */}
            <div className="grid grid-cols-2 gap-2 text-xs">
              <div className="rounded-xl border border-gray-100 bg-gray-50/80 p-2.5 dark:border-gray-800 dark:bg-gray-900/50">
                <span className="text-[10px] text-gray-400 font-medium">Status OLT</span>
                <div className="mt-1">
                  <span
                    className={cn(
                      managingOlt.is_active
                        ? "inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold bg-emerald-500 text-white shadow-xs"
                        : "inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold bg-gray-500 text-white shadow-xs"
                    )}
                  >
                    {managingOlt.is_active ? "Online" : "Offline"}
                  </span>
                </div>
              </div>
              <div className="rounded-xl border border-gray-100 bg-gray-50/80 p-2.5 dark:border-gray-800 dark:bg-gray-900/50">
                <span className="text-[10px] text-gray-400 font-medium">Total ONU</span>
                <p className="font-bold text-gray-900 dark:text-white mt-1 font-mono text-xs">
                  {managingOlt.onu_count || 0} ONU Terdaftar
                </p>
              </div>
            </div>

            {/* Detail Info Grid */}
            <div className="rounded-xl border border-gray-100 bg-gray-50/80 p-3.5 dark:border-gray-800 dark:bg-gray-900/50 space-y-2 text-xs">
              <div className="flex items-center justify-between">
                <span className="text-gray-500 dark:text-gray-400">Merek / Tipe:</span>
                <strong className="font-mono uppercase text-gray-900 dark:text-white">{managingOlt.model}</strong>
              </div>
              <div className="flex items-center justify-between">
                <span className="text-gray-500 dark:text-gray-400">Mode Koneksi:</span>
                <strong className="font-mono uppercase text-gray-900 dark:text-white">{managingOlt.connection_mode}</strong>
              </div>
              <div className="flex items-center justify-between">
                <span className="text-gray-500 dark:text-gray-400">SNMP Port / Telnet:</span>
                <strong className="font-mono text-gray-900 dark:text-white">{managingOlt.snmp_port || 161} / {managingOlt.telnet_port || 23}</strong>
              </div>
              <div className="flex items-center justify-between">
                <span className="text-gray-500 dark:text-gray-400">Lokasi:</span>
                <strong className="text-gray-900 dark:text-white truncate max-w-[180px]">{managingOlt.location || "POP Pusat"}</strong>
              </div>
            </div>

            {/* Actions List */}
            <div className="space-y-2">
              <p className="text-xs font-bold text-gray-500 uppercase tracking-wider">Aksi Cepat &amp; Kontrol</p>

              <Link
                href={`/admin/olt/onus/${managingOlt.id}`}
                className="w-full flex items-center justify-between px-4 py-3 rounded-xl bg-brand-500 hover:bg-brand-600 text-white text-xs font-bold transition shadow-xs"
              >
                <div className="flex items-center gap-2.5">
                  <Wifi className="h-4 w-4" />
                  <span>Buka Monitor ONU &amp; Redaman</span>
                </div>
                <ExternalLink className="h-3.5 w-3.5" />
              </Link>

              <button
                type="button"
                onClick={() => {
                  const o = managingOlt
                  setManagingOlt(null)
                  openTestOlt(o)
                }}
                className="w-full flex items-center justify-between px-4 py-2.5 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:hover:bg-gray-800 text-gray-700 dark:text-gray-200 text-xs font-semibold transition shadow-2xs cursor-pointer"
              >
                <div className="flex items-center gap-2.5">
                  <Activity className="h-4 w-4 text-brand-500" />
                  <span>Uji Koneksi &amp; Telemetri SNMP</span>
                </div>
                <span className="text-[11px] text-gray-400">Uji</span>
              </button>

              <button
                type="button"
                onClick={() => {
                  const o = managingOlt
                  handleSyncOlt(o)
                }}
                disabled={!!syncingIds[managingOlt.id]}
                className="w-full flex items-center justify-between px-4 py-2.5 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:hover:bg-gray-800 text-gray-700 dark:text-gray-200 text-xs font-semibold transition shadow-2xs cursor-pointer disabled:opacity-50"
              >
                <div className="flex items-center gap-2.5">
                  <RefreshCw className={cn("h-4 w-4 text-cyan-500", syncingIds[managingOlt.id] && "animate-spin")} />
                  <span>{syncingIds[managingOlt.id] ? "Menyinkronkan..." : "Sinkronisasi ONU Sekarang"}</span>
                </div>
                <span className="text-[11px] text-cyan-600 dark:text-cyan-400 font-bold">Sync</span>
              </button>

              <button
                type="button"
                onClick={() => openEdit(managingOlt)}
                className="w-full flex items-center justify-between px-4 py-2.5 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:hover:bg-gray-800 text-gray-700 dark:text-gray-200 text-xs font-semibold transition shadow-2xs cursor-pointer"
              >
                <div className="flex items-center gap-2.5">
                  <Pencil className="h-4 w-4 text-gray-500" />
                  <span>Edit Konfigurasi OLT</span>
                </div>
                <span className="text-[11px] text-gray-400">Edit</span>
              </button>

              <button
                type="button"
                onClick={() => {
                  const o = managingOlt
                  if (confirm(`Hapus OLT ${o.name}?`)) {
                    setManagingOlt(null)
                    router.post(`/admin/olt/delete/${o.id}`, {}, { preserveScroll: true })
                  }
                }}
                className="w-full flex items-center justify-between px-4 py-2.5 rounded-xl bg-rose-500 hover:bg-rose-600 text-white text-xs font-bold transition shadow-xs cursor-pointer"
              >
                <div className="flex items-center gap-2.5">
                  <Trash2 className="h-4 w-4" />
                  <span>Hapus Perangkat OLT</span>
                </div>
                <span className="text-[11px] text-white/80">Hapus</span>
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Modal Hasil Uji Koneksi OLT */}
      <Modal
        isOpen={testModalOpen}
        onClose={() => setTestModalOpen(false)}
        title="Uji Koneksi & Telemetri OLT"
        description={`${testingOlt?.name || "OLT"} (${testingOlt?.host}:${testingOlt?.connection_mode === "telnet" ? (testingOlt?.telnet_port || 23) : (testingOlt?.snmp_port || 161)})`}
        maxWidth="lg"
        footer={
          <div className="flex items-center justify-end gap-2 w-full">
            {testingOlt && (
              <button
                type="button"
                onClick={() => openTestOlt(testingOlt)}
                disabled={isTestingOlt}
                className="px-4 py-2 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:hover:bg-gray-800 text-xs font-semibold text-gray-700 dark:text-gray-200 transition shadow-2xs disabled:opacity-50"
              >
                Uji Ulang
              </button>
            )}
            <button
              type="button"
              onClick={() => setTestModalOpen(false)}
              className="px-5 py-2 rounded-xl bg-brand-500 hover:bg-brand-600 text-white font-bold text-xs shadow-xs transition"
            >
              Tutup
            </button>
          </div>
        }
      >
        <div className="space-y-4 py-1">
          {isTestingOlt ? (
            <div className="py-8 text-center space-y-3">
              <RefreshCw className="w-8 h-8 text-brand-500 animate-spin mx-auto" />
              <p className="text-sm font-semibold text-gray-700 dark:text-gray-300">
                Menghubungi dan menguji autentikasi OLT...
              </p>
            </div>
          ) : testResult ? (
            <div className="space-y-3.5 text-xs">
              <div className="p-3.5 rounded-2xl bg-emerald-50 border border-emerald-200 dark:bg-emerald-950/20 dark:border-emerald-800/50 flex items-center gap-3">
                <CheckCircle2 className="w-6 h-6 text-emerald-600 dark:text-emerald-400 shrink-0" />
                <div>
                  <strong className="text-emerald-800 dark:text-emerald-300 font-bold block text-sm">
                    Berhasil Terhubung!
                  </strong>
                  <span className="text-emerald-700 dark:text-gray-300">
                    Respon OLT stabil dengan latensi{" "}
                    <strong className="font-mono text-gray-900 dark:text-white">{testResult.latency}</strong>.
                  </span>
                </div>
              </div>

              <div className="grid grid-cols-2 gap-2 text-gray-700 dark:text-gray-300 font-mono">
                <div className="p-2.5 rounded-xl bg-gray-50/80 border border-gray-200 dark:bg-gray-900/50 dark:border-gray-800">
                  <span className="text-[10px] text-gray-400 dark:text-gray-500 block font-sans">Status SNMP</span>
                  <strong className="text-emerald-600 dark:text-emerald-400 text-xs">{testResult.snmp_status}</strong>
                </div>
                {testResult.connection_mode !== "snmp" && (
                  <div className="p-2.5 rounded-xl bg-gray-50/80 border border-gray-200 dark:bg-gray-900/50 dark:border-gray-800">
                    <span className="text-[10px] text-gray-400 dark:text-gray-500 block font-sans">Port Telnet (CLI)</span>
                    <strong className="text-brand-600 dark:text-brand-400 text-xs">{testResult.telnet_status}</strong>
                  </div>
                )}
                <div className="p-2.5 rounded-xl bg-gray-50/80 border border-gray-200 dark:bg-gray-900/50 dark:border-gray-800">
                  <span className="text-[10px] text-gray-400 dark:text-gray-500 block font-sans">System Name</span>
                  <strong className="text-gray-800 dark:text-gray-200 text-xs">{testResult.sys_name}</strong>
                </div>
                <div className="p-2.5 rounded-xl bg-gray-50/80 border border-gray-200 dark:bg-gray-900/50 dark:border-gray-800">
                  <span className="text-[10px] text-gray-400 dark:text-gray-500 block font-sans">System Uptime</span>
                  <strong className="text-gray-800 dark:text-gray-200 text-xs">{testResult.uptime}</strong>
                </div>
              </div>

              <div className="p-3 rounded-xl bg-gray-50/80 border border-gray-200 dark:bg-gray-900/50 dark:border-gray-800 text-gray-700 dark:text-gray-300 text-[11px] leading-relaxed">
                <span className="text-gray-400 dark:text-gray-500 block font-bold mb-0.5">Deskripsi Hardware (OID 1.3.6.1.2.1.1.1.0)</span>
                <p className="font-mono text-gray-800 dark:text-gray-200 text-[10px]">{testResult.sys_descr}</p>
              </div>
            </div>
          ) : testError ? (
            <div className="p-4 rounded-2xl bg-rose-50 border border-rose-200 dark:bg-rose-950/20 dark:border-rose-800/50 flex items-start gap-3 text-xs text-rose-700 dark:text-rose-300">
              <XCircle className="w-5 h-5 text-rose-600 dark:text-rose-400 shrink-0 mt-0.5" />
              <div>
                <strong className="font-bold text-gray-900 dark:text-white block">Koneksi Gagal</strong>
                <span>{testError}</span>
              </div>
            </div>
          ) : null}
        </div>
      </Modal>

      <CreateSheet
        open={sheetOpen}
        title={editingOlt ? "Edit Konfigurasi OLT" : "Tambah OLT Baru"}
        onClose={() => setSheetOpen(false)}
        onSubmit={submit}
        processing={form.processing}
        fields={fields}
        values={form.data}
        setValue={(n, v) => form.setData(n as never, v as never)}
        errors={form.errors}
      />
    </AppLayout>
  )
}
