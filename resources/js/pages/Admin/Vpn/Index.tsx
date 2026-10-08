import { AppLayout } from "@/components/layout/app-layout"
import {
  Server,
  Plus,
  Copy,
  Check,
  Trash2,
  Terminal,
  ArrowRight,
  ExternalLink,
  ShieldCheck,
  CheckCircle2,
  AlertTriangle,
  Radio,
  Globe,
} from "lucide-react"
import { Head, router, useForm } from "@inertiajs/react"
import { useState } from "react"
import { PageProps } from "@/types"
import { adminSidebarItems, adminNavItems, adminBrand } from "@/lib/admin-nav"
import { MetricCard } from "@/components/tailadmin/MetricCard"
import Modal from "@/components/tailadmin/Modal"

interface PortItem {
  port?: number
  target?: number
  protocol?: string
}

interface VpnAccountItem {
  id: number
  vpn_username: string
  status: string
  protocol: string
  server_name: string
  host: string
  ports: PortItem[]
  is_lifetime: boolean
  expires_at: string | null
  created_at: string
  mikrotik_script: string
}

interface VpnServerOption {
  id: number
  name: string
  location: string | null
  host: string
}

const DEFAULT_5_PORTS = ["80", "8291", "8728", "22", "8132"]

export default function AdminVpnPage({
  accounts = [],
  servers = [],
  protocols = ["L2TP", "SSTP"],
  quota = { used: 0, max: 1, is_full: false },
  panel_url = "https://panel.dgtlnetsolution.com",
  flash,
  errors: pageErrors,
}: PageProps<{
  accounts: VpnAccountItem[]
  servers: VpnServerOption[]
  protocols?: string[]
  quota: { used: number; max: number; is_full: boolean }
  panel_url: string
  flash?: {
    success?: string
    error?: string
    message?: string
  }
}>) {
  const [modalOpen, setModalOpen] = useState(false)
  const [copiedKey, setCopiedKey] = useState<string | null>(null)
  const [scriptModalAccount, setScriptModalAccount] = useState<VpnAccountItem | null>(null)
  const [managingAccount, setManagingAccount] = useState<VpnAccountItem | null>(null)

  const [ports, setPorts] = useState<string[]>(DEFAULT_5_PORTS)
  const [portProtocols, setPortProtocols] = useState<string[]>(DEFAULT_5_PORTS.map(() => "tcp"))
  const [usernameStatus, setUsernameStatus] = useState<{ type: "ok" | "err" | "info"; text: string } | null>(null)
  const [checking, setChecking] = useState(false)

  const form = useForm({
    server_id: servers[0]?.id ? String(servers[0].id) : "",
    vpn_username: "",
    vpn_password: "",
    protocol: protocols[0]?.toLowerCase() || "l2tp",
    ports: DEFAULT_5_PORTS.map((p) => Number(p)),
    port_protocols: DEFAULT_5_PORTS.map(() => "tcp"),
  })

  const checkUsername = (customUn?: string, customSrv?: string) => {
    const un = (customUn !== undefined ? customUn : form.data.vpn_username).trim()
    const srv = customSrv !== undefined ? customSrv : form.data.server_id
    if (un.length < 3 || !srv) {
      setUsernameStatus(null)
      return
    }
    setChecking(true)
    setUsernameStatus({ type: "info", text: "Mengecek ke server MikroTik..." })
    fetch(`/admin/vpn/check-username?server_id=${srv}&username=${encodeURIComponent(un)}`, {
      headers: {
        "Accept": "application/json",
        "X-Requested-With": "XMLHttpRequest",
      },
    })
      .then((r) => (r.ok ? r.json() : null))
      .then((res) => {
        if (!res) {
          setUsernameStatus(null)
          return
        }
        setUsernameStatus(
          res.available
            ? { type: "ok", text: "Username tersedia & siap digunakan" }
            : { type: "err", text: res.message || "Sudah digunakan" }
        )
      })
      .catch(() => setUsernameStatus(null))
      .finally(() => setChecking(false))
  }

  const copy = (text: string, key: string) => {
    navigator.clipboard?.writeText(text).then(() => {
      setCopiedKey(key)
      setTimeout(() => setCopiedKey(null), 2000)
    })
  }

  const handlePortChange = (index: number, val: string) => {
    const next = [...ports]
    next[index] = val
    setPorts(next)
    form.setData(
      "ports",
      next.map((p) => Number(p) || 8291)
    )
  }

  const handleProtocolToggle = (index: number, proto: string) => {
    const next = [...portProtocols]
    next[index] = proto
    setPortProtocols(next)
    form.setData("port_protocols", next)
  }

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    if (!form.data.server_id) {
      alert("Silakan pilih server VPN terlebih dahulu.")
      return
    }
    if (usernameStatus?.type === "err") {
      alert("Username tidak valid atau sudah digunakan di MikroTik.")
      return
    }
    form.post("/admin/vpn/create", {
      preserveScroll: true,
      onSuccess: () => {
        setModalOpen(false)
        form.reset()
        setPorts(DEFAULT_5_PORTS)
        setUsernameStatus(null)
      },
    })
  }

  const handleDelete = (id: number) => {
    if (confirm("Hapus akun VPN Remote ini? Slot remote gratis akan dapat digunakan kembali.")) {
      router.post(`/admin/vpn/delete/${id}`, {}, { preserveScroll: true })
      setManagingAccount(null)
    }
  }

  const getPortLabel = (targetPort?: number) => {
    if (targetPort === 80) return "Web HTTP / Webfig"
    if (targetPort === 8291) return "Winbox GUI"
    if (targetPort === 8728) return "API MikroTik"
    if (targetPort === 22) return "SSH Terminal"
    if (targetPort === 8132) return "OLT / GenieACS"
    return "Port Kustom"
  }

  const currentAccount = accounts[0] || null
  const flashError = flash?.error || (pageErrors as any)?.error
  const flashSuccess = flash?.success

  return (
    <AppLayout
      title="VPN Remote"
      brand={adminBrand}
      sidebarItems={adminSidebarItems}
      navItems={adminNavItems}
    >
      <Head title="VPN Remote Access — Manajemen Tunnel" />

      <div className="space-y-6">
        {/* Flash Feedback Alert */}
        {flashSuccess && (
          <div className="flex items-center gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-emerald-900 dark:border-emerald-900/50 dark:bg-emerald-950/40 dark:text-emerald-200 shadow-xs">
            <CheckCircle2 className="h-5 w-5 shrink-0 text-emerald-600 dark:text-emerald-400" />
            <p className="text-xs font-semibold leading-relaxed">{flashSuccess}</p>
          </div>
        )}

        {flashError && (
          <div className="flex items-center gap-3 rounded-2xl border border-rose-200 bg-rose-50 p-4 text-rose-900 dark:border-rose-900/50 dark:bg-rose-950/40 dark:text-rose-200 shadow-xs">
            <AlertTriangle className="h-5 w-5 shrink-0 text-rose-600 dark:text-rose-400" />
            <p className="text-xs font-semibold leading-relaxed">{flashError}</p>
          </div>
        )}

        {servers.length === 0 && (
          <div className="flex items-center gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-amber-900 dark:border-amber-900/50 dark:bg-amber-950/40 dark:text-amber-200 shadow-xs">
            <AlertTriangle className="h-5 w-5 shrink-0 text-amber-600 dark:text-amber-400" />
            <p className="text-xs font-medium leading-relaxed">
              Belum ada Server VPN aktif yang dikonfigurasi di sistem. Silakan hubungi Superadmin untuk mengaktifkan Server VPN.
            </p>
          </div>
        )}

        {/* Top KPI Metrics */}
        <div className="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
          <MetricCard
            title="Total Akun VPN"
            value={accounts.length}
            icon={<Server className="size-5" />}
            iconBgColor="bg-brand-50 dark:bg-brand-500/10"
            iconColor="text-brand-500 dark:text-brand-400"
            description={quota.is_full ? "Kuota penuh (1/1)" : `Tersedia ${quota.max - quota.used} slot`}
          />
          <MetricCard
            title="Status Remote"
            value={currentAccount ? "Aktif" : "Belum Ada"}
            icon={<ShieldCheck className="size-5" />}
            iconBgColor="bg-emerald-50 dark:bg-emerald-500/10"
            iconColor="text-emerald-500 dark:text-emerald-400"
            description={currentAccount ? currentAccount.server_name : "Siap Dikonfigurasi"}
          />
          <MetricCard
            title="Protokol Utama"
            value={currentAccount?.protocol?.toUpperCase() || "L2TP / SSTP"}
            icon={<Radio className="size-5" />}
            iconBgColor="bg-blue-50 dark:bg-blue-500/10"
            iconColor="text-blue-500 dark:text-blue-400"
            description="Enkripsi IPsec/SSL"
          />
          <MetricCard
            title="Total Server Aktif"
            value={servers.length}
            icon={<Globe className="size-5" />}
            iconBgColor="bg-gray-100 dark:bg-gray-800"
            iconColor="text-gray-600 dark:text-gray-300"
            description="Cluster VPN Gateway"
          />
        </div>

        {/* ── 1-LINE RESPONSIVE INTEGRATED TOOLBAR ── */}
        <div className="rounded-2xl border border-gray-200 bg-white p-3 sm:p-4 shadow-xs dark:border-gray-800 dark:bg-white/[0.03] flex flex-col gap-2.5 sm:gap-3 lg:flex-row lg:items-center lg:justify-between">
          <div className="flex flex-wrap items-center gap-2 sm:gap-3 flex-1 min-w-0">
            <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-[11px] font-bold bg-brand-500 text-white shadow-xs whitespace-nowrap">
              <Server className="h-3.5 w-3.5" />
              <span>VPN Gateway</span>
            </span>

            <span className="text-xs text-gray-500 dark:text-gray-400">
              {accounts.length} Akun VPN ({quota.is_full ? "Kuota 1/1 Penuh" : `${quota.max - quota.used} Slot Tersedia`})
            </span>
          </div>

          <div className="flex items-center gap-2 shrink-0 w-full sm:w-auto justify-end">
            {!quota.is_full && (
              <button
                type="button"
                onClick={() => {
                  form.clearErrors()
                  setModalOpen(true)
                }}
                disabled={servers.length === 0}
                className="h-10 px-3.5 sm:px-4 rounded-xl bg-brand-500 hover:bg-brand-600 active:scale-95 text-xs font-bold text-white shadow-xs flex items-center justify-center gap-1.5 w-full sm:w-auto shrink-0 transition disabled:opacity-50 cursor-pointer"
              >
                <Plus className="size-4" />
                <span>Buat Remote</span>
              </button>
            )}
          </div>
        </div>

        {/* Master Card: VPN Accounts */}
        <div className="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xs dark:border-gray-800 dark:bg-white/[0.03]">
          <div className="p-4 sm:p-6">
            {currentAccount ? (
              <div className="space-y-4">
                {/* Account Details Banner */}
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900/50 shadow-2xs">
                  <div className="flex items-center gap-3">
                    <div className="flex size-11 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-500 dark:bg-brand-950/50 dark:text-brand-400 font-bold">
                      <Server className="size-5" />
                    </div>
                    <div>
                      <div className="flex items-center gap-2">
                        <h4 className="font-bold text-sm text-gray-900 dark:text-white">{currentAccount.vpn_username}</h4>
                        <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold bg-emerald-500 text-white shadow-xs whitespace-nowrap">Aktif</span>
                        <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold bg-brand-500 text-white shadow-xs uppercase whitespace-nowrap">{currentAccount.protocol}</span>
                      </div>
                      <p className="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                        Server: <span className="font-medium text-gray-900 dark:text-white">{currentAccount.server_name}</span> · Host: <span className="font-mono text-brand-600 dark:text-brand-400">{currentAccount.host}</span>
                      </p>
                    </div>
                  </div>

                  {/* Single Kelola Action Button */}
                  <div className="flex items-center gap-2">
                    <button
                      type="button"
                      onClick={() => setManagingAccount(currentAccount)}
                      className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200 font-bold text-xs transition cursor-pointer"
                    >
                      <span>Kelola Remote</span>
                    </button>
                  </div>
                </div>

                {/* 5 Port Forwarding Grid */}
                <div className="space-y-2">
                  <div className="text-xs font-semibold text-gray-700 dark:text-gray-300">
                    Daftar 5 Port Forwarding Aktif
                  </div>

                  <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    {(Array.isArray(currentAccount?.ports) ? currentAccount.ports : []).map((p: any, pIdx) => {
                      const portVal = typeof p === "object" ? p?.port : p
                      const targetVal = typeof p === "object" ? (p?.target ?? p?.port ?? "-") : p
                      const accessAddr = `${currentAccount.host}:${portVal ?? ""}`
                      const copyKey = `acc-${currentAccount.id}-p-${pIdx}`
                      return (
                        <div
                          key={pIdx}
                          className="flex items-center justify-between gap-2 rounded-xl border border-gray-100 bg-gray-50/80 p-3 dark:border-gray-800 dark:bg-gray-900/50 shadow-2xs"
                        >
                          <div className="min-w-0 flex-1">
                            <code className="font-mono text-xs font-bold text-brand-600 dark:text-brand-400 truncate block select-all">
                              {accessAddr}
                            </code>
                            <div className="flex items-center gap-1.5 text-[11px] text-gray-500 dark:text-gray-400 mt-0.5">
                              <ArrowRight className="size-3 shrink-0" />
                              <span className="font-medium text-gray-900 dark:text-white">Port {targetVal}</span>
                              <span>({getPortLabel(targetVal)})</span>
                            </div>
                          </div>

                          <button
                            type="button"
                            onClick={() => copy(accessAddr, copyKey)}
                            className="flex size-7 shrink-0 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-600 hover:text-brand-600 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:text-brand-400 transition-all shadow-2xs"
                            title="Salin Alamat"
                          >
                            {copiedKey === copyKey ? (
                              <Check className="size-3.5 text-emerald-500" />
                            ) : (
                              <Copy className="size-3.5" />
                            )}
                          </button>
                        </div>
                      )
                    })}
                  </div>
                </div>
              </div>
            ) : (
              /* Empty State */
              <div className="flex flex-col items-center justify-center text-center p-8 rounded-2xl border border-dashed border-gray-200 dark:border-gray-800 bg-gray-50/50 dark:bg-white/[0.01]">
                <div className="flex size-12 items-center justify-center rounded-2xl bg-brand-50 text-brand-500 dark:bg-brand-950/50 dark:text-brand-400 mb-3">
                  <ShieldCheck className="size-6" />
                </div>
                <h4 className="font-bold text-sm text-gray-900 dark:text-white">Belum Ada Akun VPN Remote</h4>
                <p className="text-xs text-gray-500 dark:text-gray-400 mt-1 max-w-sm">
                  Buat akun remote pertama Anda untuk menghubungkan router MikroTik dengan 5 port remote otomatis.
                </p>
                <button
                  type="button"
                  onClick={() => setModalOpen(true)}
                  className="mt-4 inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-brand-500 hover:bg-brand-600 active:scale-95 text-xs font-bold text-white shadow-xs transition"
                >
                  <Plus className="size-4" />
                  <span>Buat Akun Remote</span>
                </button>
              </div>
            )}

            {/* Info ringkas di bawah jika butuh kuota lebih */}
            <p className="text-center text-xs text-gray-500 dark:text-gray-400 pt-6">
              Butuh lebih dari 1 remote router? Kunjungi{" "}
              <a
                href={panel_url}
                target="_blank"
                rel="noreferrer"
                className="text-brand-600 hover:underline font-semibold inline-flex items-center gap-1 dark:text-brand-400"
              >
                <span>panel.dgtlnetsolution.com</span>
                <ExternalLink className="size-3" />
              </a>
            </p>
          </div>
        </div>
      </div>

      {/* POPUP MODAL KELOLA VPN REMOTE */}
      {managingAccount && (
        <Modal
          isOpen={true}
          onClose={() => setManagingAccount(null)}
          title="Kelola Akun VPN Remote"
          maxWidth="md"
        >
          <div className="p-5 max-h-[92vh] overflow-y-auto custom-scrollbar space-y-4">
            <div className="flex items-start justify-between gap-3 border-b border-gray-100 dark:border-gray-800 pb-3">
              <div>
                <h4 className="font-bold text-sm text-gray-900 dark:text-white">{managingAccount.vpn_username}</h4>
                <p className="text-xs text-gray-500 dark:text-gray-400">Server: {managingAccount.server_name}</p>
              </div>
              <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold bg-emerald-500 text-white shadow-xs whitespace-nowrap">
                Aktif
              </span>
            </div>

            <div className="rounded-xl border border-gray-100 bg-gray-50/80 p-3 dark:border-gray-800 dark:bg-gray-900/50 space-y-2 text-xs">
              <div className="flex items-center justify-between">
                <span className="text-gray-500 dark:text-gray-400">Host Gateway</span>
                <span className="font-mono font-bold text-gray-900 dark:text-white">{managingAccount.host}</span>
              </div>
              <div className="flex items-center justify-between">
                <span className="text-gray-500 dark:text-gray-400">Protokol</span>
                <span className="font-bold text-brand-600 dark:text-brand-400 uppercase">{managingAccount.protocol}</span>
              </div>
              <div className="flex items-center justify-between">
                <span className="text-gray-500 dark:text-gray-400">Jumlah Port</span>
                <span className="font-semibold text-gray-900 dark:text-white">{managingAccount.ports?.length || 5} Port Forwarding</span>
              </div>
            </div>

            <div className="pt-2 space-y-2">
              <button
                type="button"
                onClick={() => {
                  setScriptModalAccount(managingAccount)
                  setManagingAccount(null)
                }}
                className="w-full inline-flex items-center justify-center gap-1.5 px-3.5 py-2 rounded-xl bg-brand-500 hover:bg-brand-600 active:scale-95 text-xs font-bold text-white shadow-xs transition"
              >
                <Terminal className="size-4" />
                <span>Lihat Script MikroTik</span>
              </button>

              <button
                type="button"
                onClick={() => handleDelete(managingAccount.id)}
                className="w-full inline-flex items-center justify-center gap-1.5 px-3.5 py-2 rounded-xl bg-rose-500 hover:bg-rose-600 active:scale-95 text-xs font-bold text-white shadow-xs transition"
              >
                <Trash2 className="size-4" />
                <span>Hapus Akun Remote</span>
              </button>

              <button
                type="button"
                onClick={() => setManagingAccount(null)}
                className="w-full inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:hover:bg-gray-800 text-xs font-semibold text-gray-700 dark:text-gray-200 transition shadow-2xs"
              >
                <span>Tutup</span>
              </button>
            </div>
          </div>
        </Modal>
      )}

      {/* MODAL FORM PEMBUATAN VPN REMOTE */}
      {modalOpen && (
        <Modal
          isOpen={true}
          onClose={() => setModalOpen(false)}
          title="Buat VPN Remote (5 Port)"
          maxWidth="lg"
        >
          <form onSubmit={handleSubmit} className="p-5 max-h-[92vh] overflow-y-auto custom-scrollbar space-y-4">
            {/* 1. Pilih Server */}
            <div className="space-y-1.5">
              <label className="text-xs font-semibold text-gray-700 dark:text-gray-300">Pilih Server VPN <span className="text-rose-500">*</span></label>
              <select
                value={form.data.server_id}
                onChange={(e) => {
                  form.setData("server_id", e.target.value)
                  checkUsername(form.data.vpn_username, e.target.value)
                }}
                className="w-full rounded-xl border border-gray-200 bg-white px-3 py-2 text-xs font-medium text-gray-900 shadow-2xs focus:border-brand-500 focus:outline-hidden dark:border-gray-800 dark:bg-gray-900 dark:text-white"
                required
              >
                {servers.length === 0 ? (
                  <option value="">Tidak ada server aktif</option>
                ) : (
                  servers.map((s) => (
                    <option key={s.id} value={s.id}>
                      {s.name} ({s.host}) {s.location ? `· ${s.location}` : ""}
                    </option>
                  ))
                )}
              </select>
            </div>

            {/* 2. Username VPN */}
            <div className="space-y-1.5">
              <div className="flex items-center justify-between">
                <label className="text-xs font-semibold text-gray-700 dark:text-gray-300">Username VPN <span className="text-rose-500">*</span></label>
                {checking ? (
                  <span className="text-[11px] text-gray-400 animate-pulse">Mengecek MikroTik...</span>
                ) : usernameStatus ? (
                  <span className={`text-[11px] font-semibold ${
                    usernameStatus.type === "ok" ? "text-emerald-500" : usernameStatus.type === "err" ? "text-rose-500" : "text-gray-500"
                  }`}>
                    {usernameStatus.text}
                  </span>
                ) : null}
              </div>
              <input
                value={form.data.vpn_username}
                onChange={(e) => {
                  const cleanVal = e.target.value.toLowerCase().replace(/[^a-z0-9_]/g, "")
                  form.setData("vpn_username", cleanVal)
                  if (cleanVal.length >= 3) {
                    checkUsername(cleanVal, form.data.server_id)
                  } else {
                    setUsernameStatus(null)
                  }
                }}
                onBlur={() => checkUsername()}
                placeholder="cth: router_utama"
                className="w-full rounded-xl border border-gray-200 bg-white px-3 py-2 text-xs font-mono text-gray-900 shadow-2xs focus:border-brand-500 focus:outline-hidden dark:border-gray-800 dark:bg-gray-900 dark:text-white"
                required
              />
            </div>

            {/* 3. Password VPN */}
            <div className="space-y-1.5">
              <label className="text-xs font-semibold text-gray-700 dark:text-gray-300">Password VPN <span className="text-rose-500">*</span></label>
              <input
                type="password"
                value={form.data.vpn_password}
                onChange={(e) => form.setData("vpn_password", e.target.value)}
                placeholder="Masukkan password"
                className="w-full rounded-xl border border-gray-200 bg-white px-3 py-2 text-xs text-gray-900 shadow-2xs focus:border-brand-500 focus:outline-hidden dark:border-gray-800 dark:bg-gray-900 dark:text-white"
                required
              />
            </div>

            {/* 4. Protokol VPN */}
            <div className="space-y-1.5">
              <label className="text-xs font-semibold text-gray-700 dark:text-gray-300">Protokol VPN <span className="text-rose-500">*</span></label>
              <select
                value={form.data.protocol}
                onChange={(e) => form.setData("protocol", e.target.value)}
                className="w-full rounded-xl border border-gray-200 bg-white px-3 py-2 text-xs font-bold uppercase text-gray-900 shadow-2xs focus:border-brand-500 focus:outline-hidden dark:border-gray-800 dark:bg-gray-900 dark:text-white"
              >
                {protocols.map((proto) => {
                  const pUpper = proto.toUpperCase()
                  return (
                    <option key={proto} value={proto.toLowerCase()}>
                      {pUpper} {pUpper === "L2TP" ? "(L2TP Client)" : pUpper === "SSTP" ? "(SSTP SSL)" : ""}
                    </option>
                  )
                })}
              </select>
            </div>

            {/* 5. 5 Port Tujuan */}
            <div className="space-y-2 pt-2 border-t border-gray-100 dark:border-gray-800">
              <div className="flex items-center justify-between">
                <label className="text-xs font-semibold text-gray-700 dark:text-gray-300">
                  5 Port Tujuan MikroTik
                </label>
                <span className="text-[11px] text-gray-500">5 Port</span>
              </div>

              <div className="space-y-2">
                {ports.map((pVal, pIdx) => {
                  const labels = [
                    "Port 1: Webfig / Web HTTP (80)",
                    "Port 2: Winbox GUI (8291)",
                    "Port 3: API MikroTik (8728)",
                    "Port 4: SSH Terminal (22)",
                    "Port 5: OLT / TR-069 (8132)",
                  ]
                  return (
                    <div
                      key={pIdx}
                      className="flex items-center justify-between gap-2.5 rounded-xl border border-gray-100 bg-gray-50/80 p-2.5 dark:border-gray-800 dark:bg-gray-900/50"
                    >
                      <div className="flex-1 min-w-0">
                        <span className="text-[11px] font-semibold text-gray-500 dark:text-gray-400 block truncate">
                          {labels[pIdx] || `Port ${pIdx + 1}`}
                        </span>
                        <input
                          type="number"
                          min={1}
                          max={65535}
                          value={pVal}
                          onChange={(e) => handlePortChange(pIdx, e.target.value)}
                          className="h-8 w-full rounded-lg border border-gray-200 bg-white px-2 text-xs font-mono font-bold text-gray-900 focus:border-brand-500 focus:outline-hidden dark:border-gray-700 dark:bg-gray-800 dark:text-white mt-1"
                        />
                      </div>

                      <div className="flex items-center gap-1 shrink-0 pt-4">
                        {["tcp", "udp"].map((proto) => (
                          <button
                            key={proto}
                            type="button"
                            onClick={() => handleProtocolToggle(pIdx, proto)}
                            className={`h-7 px-2 text-[10px] font-bold uppercase rounded-lg transition-all ${
                              (portProtocols[pIdx] || "tcp") === proto
                                ? "bg-brand-500 text-white shadow-xs"
                                : "bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400"
                            }`}
                          >
                            {proto}
                          </button>
                        ))}
                      </div>
                    </div>
                  )
                })}
              </div>
            </div>

            <div className="flex items-center justify-end gap-2.5 pt-4 border-t border-gray-200 dark:border-gray-800">
              <button
                type="button"
                onClick={() => setModalOpen(false)}
                className="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:hover:bg-gray-800 text-xs font-semibold text-gray-700 dark:text-gray-200 transition shadow-2xs"
              >
                Batal
              </button>
              <button
                type="submit"
                disabled={form.processing}
                className="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-brand-500 hover:bg-brand-600 active:scale-95 text-xs font-bold text-white shadow-xs transition disabled:opacity-50"
              >
                <span>{form.processing ? "Memproses..." : "Simpan & Aktifkan"}</span>
              </button>
            </div>
          </form>
        </Modal>
      )}

      {/* MODAL SCRIPT MIKROTIK */}
      {scriptModalAccount && (
        <Modal
          isOpen={true}
          onClose={() => setScriptModalAccount(null)}
          title={`Script MikroTik: ${scriptModalAccount.vpn_username}`}
          maxWidth="lg"
        >
          <div className="p-5 max-h-[92vh] overflow-y-auto custom-scrollbar space-y-3.5">
            <div className="flex items-center justify-between">
              <span className="text-xs font-bold text-gray-700 dark:text-gray-300">Terminal Winbox Script</span>
              <button
                type="button"
                onClick={() => copy(scriptModalAccount.mikrotik_script, "script_modal")}
                className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-brand-500 hover:bg-brand-600 text-xs font-bold text-white shadow-xs transition active:scale-95"
              >
                {copiedKey === "script_modal" ? <Check className="size-3.5" /> : <Copy className="size-3.5" />}
                <span>{copiedKey === "script_modal" ? "Tersalin!" : "Salin Script"}</span>
              </button>
            </div>

            <pre className="rounded-xl bg-gray-900 p-4 text-xs font-mono text-emerald-400 overflow-x-auto whitespace-pre-wrap border border-gray-800 leading-relaxed select-all">
              {scriptModalAccount.mikrotik_script}
            </pre>

            <div className="rounded-xl bg-brand-50/50 border border-brand-100 p-3 text-xs text-brand-900 dark:bg-brand-950/30 dark:border-brand-900/50 dark:text-brand-300 space-y-1">
              <p className="font-bold">Cara Pasang di MikroTik:</p>
              <ol className="list-decimal list-inside text-[11px] space-y-0.5">
                <li>Buka Winbox &gt; Menu <strong>New Terminal</strong>.</li>
                <li>Paste script di atas lalu tekan <strong>Enter</strong>.</li>
                <li>Interface VPN Client akan otomatis terhubung.</li>
              </ol>
            </div>

            <div className="flex items-center justify-end pt-4 border-t border-gray-200 dark:border-gray-800">
              <button
                type="button"
                onClick={() => setScriptModalAccount(null)}
                className="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:hover:bg-gray-800 text-xs font-semibold text-gray-700 dark:text-gray-200 transition shadow-2xs"
              >
                Tutup
              </button>
            </div>
          </div>
        </Modal>
      )}
    </AppLayout>
  )
}