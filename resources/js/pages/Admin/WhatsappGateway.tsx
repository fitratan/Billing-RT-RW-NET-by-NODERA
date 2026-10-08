import React, { useState, useEffect, useMemo } from "react"
import { AppLayout } from "@/components/layout/app-layout"
import { router } from "@inertiajs/react"
import {
  Smartphone,
  QrCode,
  Key,
  Plus,
  RefreshCw,
  Power,
  Trash2,
  CheckCircle2,
  Clock,
  Send,
  Check,
  Copy,
  Globe,
  Radio,
  ExternalLink,
  Shield,
  Star,
  Activity,
  Code,
  X,
  Search,
  SlidersHorizontal,
} from "lucide-react"
import { formatPhone } from "@/lib/utils"
import { adminBrand, adminSidebarItems, adminNavItems } from "@/lib/admin-nav";
import { MetricCard } from "@/components/tailadmin/MetricCard"
import { ViewModeSwitcher, type ViewMode } from "@/components/ui/view-mode-switcher"

interface WhatsappDevice {
  id: number
  session_id: string
  name: string
  phone_number: string | null
  profile_name: string | null
  status: string
  live_status?: string
  is_connected?: boolean
  api_key: string | null
  webhook_url: string | null
  is_default: boolean
  last_connected_at: string | null
}

interface PageProps {
  devices: WhatsappDevice[]
  microserviceOnline: boolean
  gatewayUrl: string
  publicIp: string
  flash?: {
    msg?: string
    error?: string
  }
}

export default function WhatsappGatewayPage({
  devices = [],
  microserviceOnline: _microserviceOnline = false,
  gatewayUrl,
  publicIp,
}: PageProps) {
  const [viewMode, setViewMode] = useState<ViewMode>("table")
  const [searchQuery, setSearchQuery] = useState("")
  const [statusFilter, setStatusFilter] = useState("all")
  const [selectedDevice, setSelectedDevice] = useState<WhatsappDevice | null>(null)
  const [manageDevice, setManageDevice] = useState<WhatsappDevice | null>(null)

  // Modals state
  const [qrModalOpen, setQrModalOpen] = useState(false)
  const [pairingModalOpen, setPairingModalOpen] = useState(false)
  const [addModalOpen, setAddModalOpen] = useState(false)
  const [testModalOpen, setTestModalOpen] = useState(false)
  const [apiDocOpen, setApiDocOpen] = useState(false)

  // QR Polling state
  const [qrDataUrl, setQrDataUrl] = useState<string | null>(null)
  const [qrStatus, setQrStatus] = useState<string>("INITIALIZING")
  const [isQrLoading, setIsQrLoading] = useState(false)

  // Pairing Code state
  const [pairingPhone, setPairingPhone] = useState("")
  const [pairingCodeResult, setPairingCodeResult] = useState<string | null>(null)
  const [pairingLoading, setPairingLoading] = useState(false)

  // New Session state
  const [newSessionName, setNewSessionName] = useState("")
  const [newWebhookUrl, setNewWebhookUrl] = useState("")

  // Test Message state
  const [testPhone, setTestPhone] = useState("")
  const [testMessage, setTestMessage] = useState("Halo! Ini adalah pesan uji coba dari NODERA Universal WhatsApp Gateway.")
  const [testSessionId, setTestSessionId] = useState("default")
  const [sendingTest, setSendingTest] = useState(false)

  // Copy state
  const [copiedKey, setCopiedKey] = useState<string | null>(null)

  const copyToClipboard = (text: string, id: string) => {
    navigator.clipboard.writeText(text)
    setCopiedKey(id)
    setTimeout(() => setCopiedKey(null), 2000)
  }

  // Fetch QR Code
  const fetchQrCode = async (sessionId: string) => {
    setIsQrLoading(true)
    try {
      const res = await fetch(`/admin/whatsapp-gateway/qr/${sessionId}`)
      const data = await res.json()
      if (data.success && data.status === "QR_READY") {
        setQrDataUrl(data.qr_image)
        setQrStatus("QR_READY")
      } else if (data.status === "CONNECTED") {
        setQrStatus("CONNECTED")
        setQrModalOpen(false)
        router.reload()
      } else {
        setQrStatus(data.status || "WAITING")
      }
    } catch {
      setQrStatus("ERROR")
    } finally {
      setIsQrLoading(false)
    }
  }

  // Auto-refresh QR code every 3 seconds while QR modal is open
  useEffect(() => {
    let interval: any
    if (qrModalOpen && selectedDevice) {
      fetchQrCode(selectedDevice.session_id)
      interval = setInterval(() => {
        fetchQrCode(selectedDevice.session_id)
      }, 3000)
    }
    return () => {
      if (interval) clearInterval(interval)
    }
  }, [qrModalOpen, selectedDevice])

  const openQrModal = (dev: WhatsappDevice) => {
    setSelectedDevice(dev)
    setQrDataUrl(null)
    setQrStatus("INITIALIZING")
    setQrModalOpen(true)
  }

  const openPairingModal = (dev: WhatsappDevice) => {
    setSelectedDevice(dev)
    setPairingCodeResult(null)
    setPairingPhone(dev.phone_number || "")
    setPairingModalOpen(true)
  }

  const handleRequestPairingCode = async (e: React.FormEvent) => {
    e.preventDefault()
    if (!selectedDevice || !pairingPhone) return
    setPairingLoading(true)
    try {
      const res = await fetch(`/admin/whatsapp-gateway/pairing-code/${selectedDevice.session_id}`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "X-CSRF-TOKEN": (document.querySelector('meta[name="csrf-token"]') as any)?.content || "",
        },
        body: JSON.stringify({ phone: pairingPhone }),
      })
      const data = await res.json()
      if (data.success && data.pairing_code) {
        setPairingCodeResult(data.pairing_code)
      } else {
        alert(data.error || "Gagal mendapatkan kode pairing")
      }
    } catch (err: any) {
      alert("Error: " + err.message)
    } finally {
      setPairingLoading(false)
    }
  }

  const handleCreateSession = (e: React.FormEvent) => {
    e.preventDefault()
    router.post(
      "/admin/whatsapp-gateway/create",
      {
        name: newSessionName,
        webhook_url: newWebhookUrl,
      },
      {
        onSuccess: () => {
          setAddModalOpen(false)
          setNewSessionName("")
          setNewWebhookUrl("")
        },
      }
    )
  }

  const handleSendTest = (e: React.FormEvent) => {
    e.preventDefault()
    setSendingTest(true)
    router.post(
      "/admin/whatsapp-gateway/send-test",
      {
        phone: testPhone,
        message: testMessage,
        session_id: testSessionId,
      },
      {
        onFinish: () => {
          setSendingTest(false)
          setTestModalOpen(false)
        },
      }
    )
  }

  const connectedCount = devices.filter((d) => d.is_connected || d.live_status === "CONNECTED").length
  const defaultDevice = devices.find((d) => d.is_default)

  const filteredDevices = useMemo(() => {
    return devices.filter((d) => {
      const isConn = d.is_connected || d.live_status === "CONNECTED"
      const isQrReady = d.live_status === "QR_READY"

      const matchesSearch =
        d.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
        d.session_id.toLowerCase().includes(searchQuery.toLowerCase()) ||
        (d.phone_number && d.phone_number.includes(searchQuery))

      if (!matchesSearch) return false

      if (statusFilter === "connected") return isConn
      if (statusFilter === "disconnected") return !isConn
      if (statusFilter === "qr") return isQrReady
      return true
    })
  }, [devices, searchQuery, statusFilter])

  return (
    <AppLayout
      title="WhatsApp Gateway"
      brand={adminBrand}
      sidebarItems={adminSidebarItems}
      navItems={adminNavItems}
    >
      <div className="space-y-6">
        {/* Telemetry & Metrics Header */}
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
          <MetricCard
            title="Nomor Pengirim Terhubung"
            value={connectedCount > 0 && defaultDevice?.phone_number ? formatPhone(defaultDevice.phone_number) : (connectedCount > 0 ? "Terhubung" : "Belum Ada")}
            badge={{
              text: connectedCount > 0 ? "Online" : "Offline",
              color: connectedCount > 0 ? "success" : "error",
            }}
            icon={Smartphone}
            iconBgColor={connectedCount > 0 ? "bg-emerald-50 dark:bg-emerald-950/30" : "bg-gray-50 dark:bg-gray-900/50"}
            iconColor={connectedCount > 0 ? "text-emerald-600 dark:text-emerald-400" : "text-gray-500 dark:text-gray-400"}
          />
          <MetricCard
            title="Status Gateway"
            value={connectedCount > 0 ? "Siap Kirim Pesan" : "Perlu Scan QR"}
            badge={{
              text: connectedCount > 0 ? "Aktif" : "Menunggu",
              color: connectedCount > 0 ? "success" : "warning",
            }}
            icon={Radio}
            iconBgColor={connectedCount > 0 ? "bg-brand-50 dark:bg-brand-950/30" : "bg-amber-50 dark:bg-amber-950/30"}
            iconColor={connectedCount > 0 ? "text-brand-600 dark:text-brand-400" : "text-amber-600 dark:text-amber-400"}
          />
          <MetricCard
            title="Sesi Terhubung"
            value={`${connectedCount} Sesi`}
            badge={{
              text: "Aktif",
              color: "success",
            }}
            icon={CheckCircle2}
            iconBgColor="bg-emerald-50 dark:bg-emerald-950/30"
            iconColor="text-emerald-600 dark:text-emerald-400"
          />
          <MetricCard
            title="Sesi Pengirim Utama"
            value={defaultDevice ? defaultDevice.name : (devices.length > 0 ? devices[0].name : "Belum Diatur")}
            badge={{
              text: "Default",
              color: "warning",
            }}
            icon={Star}
            iconBgColor="bg-amber-50 dark:bg-amber-950/30"
            iconColor="text-amber-600 dark:text-amber-400"
          />
        </div>

        {/* Action Toolbar */}
        <div className="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03] p-3 sm:p-4 shadow-xs flex flex-col gap-2.5 sm:gap-3 lg:flex-row lg:items-center lg:justify-between">
          {/* Left Side: Search and Filters */}
          <div className="flex flex-wrap items-center gap-2 sm:gap-3 flex-1 min-w-0">
            <div className="relative flex-1 min-w-[180px] sm:min-w-[220px] w-full sm:w-auto">
              <Search className="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 size-4 text-gray-400" />
              <input
                type="text"
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                placeholder="Cari sesi, ID, atau nomor WhatsApp..."
                className="h-10 w-full pl-9 pr-8 rounded-xl text-xs font-medium border border-gray-200 bg-gray-50/50 hover:bg-white focus:bg-white dark:border-gray-800 dark:bg-gray-900/50 dark:hover:bg-gray-900 text-gray-900 dark:text-white placeholder-gray-400 focus:outline-none focus:border-brand-500 transition"
              />
              {searchQuery && (
                <button
                  type="button"
                  onClick={() => setSearchQuery("")}
                  className="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 cursor-pointer"
                >
                  <X className="size-3.5" />
                </button>
              )}
            </div>

            <select
              value={statusFilter}
              onChange={(e) => setStatusFilter(e.target.value)}
              className="h-10 rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 px-3 text-xs font-semibold text-gray-700 dark:text-gray-200 cursor-pointer w-full sm:w-auto shrink-0 focus:outline-none focus:border-brand-500"
            >
              <option value="all">Semua Status</option>
              <option value="connected">Terhubung</option>
              <option value="disconnected">Terputus</option>
              <option value="qr">Siap Scan QR</option>
            </select>
          </div>

          {/* Right Side: Actions & View Mode */}
          <div className="flex items-center gap-2 shrink-0 w-full sm:w-auto justify-end flex-wrap sm:flex-nowrap">
            <ViewModeSwitcher
              value={viewMode}
              onChange={setViewMode}
            />

            <button
              type="button"
              onClick={() => setTestModalOpen(true)}
              className="h-10 inline-flex items-center justify-center gap-1.5 px-3.5 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:hover:bg-gray-800 text-xs font-semibold text-gray-700 dark:text-gray-200 transition shadow-2xs cursor-pointer shrink-0"
            >
              <Send className="size-3.5 text-emerald-500" />
              <span>Uji Pesan</span>
            </button>

            <button
              type="button"
              onClick={() => setApiDocOpen(true)}
              className="h-10 inline-flex items-center justify-center gap-1.5 px-3.5 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:hover:bg-gray-800 text-xs font-semibold text-gray-700 dark:text-gray-200 transition shadow-2xs cursor-pointer shrink-0"
            >
              <Code className="size-3.5 text-brand-500" />
              <span>REST API</span>
            </button>

            <button
              type="button"
              onClick={() => setAddModalOpen(true)}
              className="h-10 px-3.5 sm:px-4 rounded-xl bg-brand-500 hover:bg-brand-600 active:scale-95 text-xs font-bold text-white shadow-xs flex items-center justify-center gap-1.5 w-full sm:w-auto shrink-0 cursor-pointer transition"
            >
              <Plus className="size-4" />
              <span>Tambah Sesi</span>
            </button>
          </div>
        </div>

        {/* Separated Table Columns View */}
        {viewMode === "table" ? (
          <div className="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03] shadow-xs overflow-hidden">
            <div className="w-full overflow-x-auto custom-scrollbar">
              <table className="w-full min-w-[950px] text-start text-xs border-collapse">
                <thead>
                  <tr className="border-b border-gray-200 dark:border-gray-800 bg-gray-50/75 dark:bg-gray-900/50">
                    <th className="py-3.5 px-4 font-bold text-gray-600 dark:text-gray-300 text-left">Perangkat / Sesi</th>
                    <th className="py-3.5 px-4 font-bold text-gray-600 dark:text-gray-300 text-left">Nomor WhatsApp</th>
                    <th className="py-3.5 px-4 font-bold text-gray-600 dark:text-gray-300 text-left">Profil WhatsApp</th>
                    <th className="py-3.5 px-4 font-bold text-gray-600 dark:text-gray-300 text-center">Status Sesi</th>
                    <th className="py-3.5 px-4 font-bold text-gray-600 dark:text-gray-300 text-center">Tipe Sesi</th>
                    <th className="py-3.5 px-4 font-bold text-gray-600 dark:text-gray-300 text-left">API Key</th>
                    <th className="py-3.5 px-4 font-bold text-gray-600 dark:text-gray-300 text-center">Aksi</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-gray-100 dark:divide-gray-800/60">
                  {filteredDevices.length === 0 ? (
                    <tr>
                      <td colSpan={7} className="py-12 text-center text-gray-400">
                        Tidak ada perangkat / sesi WhatsApp ditemukan.
                      </td>
                    </tr>
                  ) : (
                    filteredDevices.map((dev) => {
                      const isConn = dev.is_connected || dev.live_status === "CONNECTED"
                      const isQrReady = dev.live_status === "QR_READY"

                      return (
                        <tr key={dev.id} className="hover:bg-gray-50/50 dark:hover:bg-gray-800/30 transition">
                          {/* Sesi / Name */}
                          <td className="py-3.5 px-4">
                            <div className="flex items-center gap-3">
                              <div className={`p-2 rounded-xl border flex items-center justify-center shrink-0 ${
                                isConn ? "bg-emerald-50 border-emerald-200 text-emerald-600 dark:bg-emerald-950/30 dark:border-emerald-800 dark:text-emerald-400" : "bg-gray-100 border-gray-200 text-gray-500 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-400"
                              }`}>
                                <Smartphone className="size-4" />
                              </div>
                              <div>
                                <div className="font-bold text-gray-900 dark:text-white">{dev.name}</div>
                                <div className="text-[11px] font-mono text-gray-400">ID: {dev.session_id}</div>
                              </div>
                            </div>
                          </td>

                          {/* Nomor WhatsApp */}
                          <td className="py-3.5 px-4">
                            <span className="font-mono font-bold text-gray-900 dark:text-white">
                              {dev.phone_number ? `+${dev.phone_number}` : "- Belum Terhubung -"}
                            </span>
                          </td>

                          {/* Profil WhatsApp */}
                          <td className="py-3.5 px-4">
                            <span className="text-gray-700 dark:text-gray-300 font-medium">
                              {dev.profile_name || "-"}
                            </span>
                          </td>

                          {/* Status Sesi */}
                          <td className="py-3.5 px-4 text-center">
                            {isConn ? (
                              <span className="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-bold bg-emerald-500 text-white shadow-xs whitespace-nowrap">
                                Terhubung
                              </span>
                            ) : isQrReady ? (
                              <span className="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-bold bg-amber-500 text-white shadow-xs whitespace-nowrap">
                                Siap Scan QR
                              </span>
                            ) : (
                              <span className="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-bold bg-rose-500 text-white shadow-xs whitespace-nowrap">
                                Terputus
                              </span>
                            )}
                          </td>

                          {/* Tipe Sesi */}
                          <td className="py-3.5 px-4 text-center">
                            {dev.is_default ? (
                              <span className="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-bold bg-amber-500 text-white shadow-xs whitespace-nowrap">
                                Sesi Utama
                              </span>
                            ) : (
                              <span className="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-bold bg-gray-500 text-white shadow-xs whitespace-nowrap">
                                Sesi Tambahan
                              </span>
                            )}
                          </td>

                          {/* API Key */}
                          <td className="py-3.5 px-4">
                            <div className="flex items-center gap-1.5 font-mono text-[11px] text-brand-600 dark:text-brand-400">
                              <span>{dev.api_key ? dev.api_key.slice(0, 12) + "..." : "-"}</span>
                              {dev.api_key && (
                                <button
                                  type="button"
                                  onClick={() => copyToClipboard(dev.api_key!, dev.session_id)}
                                  className="text-gray-400 hover:text-gray-600 dark:hover:text-white cursor-pointer p-1 rounded hover:bg-gray-100 dark:hover:bg-gray-800"
                                  title="Salin API Key"
                                >
                                  {copiedKey === dev.session_id ? <Check className="size-3.5 text-emerald-500" /> : <Copy className="size-3.5" />}
                                </button>
                              )}
                            </div>
                          </td>

                          {/* Aksi: Single Kelola Button */}
                          <td className="py-3.5 px-4 text-center">
                            <button
                              type="button"
                              onClick={() => setManageDevice(dev)}
                              className="h-8 px-3.5 rounded-lg bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200 font-bold text-xs transition cursor-pointer shadow-2xs inline-flex items-center gap-1"
                            >
                              <SlidersHorizontal className="size-3.5" /> Kelola
                            </button>
                          </td>
                        </tr>
                      )
                    })
                  )}
                </tbody>
              </table>
            </div>
          </div>
        ) : (
          /* Grid View */
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            {filteredDevices.map((dev) => {
              const isConn = dev.is_connected || dev.live_status === "CONNECTED"
              const isQrReady = dev.live_status === "QR_READY"

              return (
                <div
                  key={dev.id}
                  className="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03] p-5 shadow-xs space-y-3.5 flex flex-col justify-between"
                >
                  <div>
                    {/* Card Top */}
                    <div className="flex items-start justify-between gap-3">
                      <div className="flex items-center gap-3 min-w-0">
                        <div className={`p-2.5 rounded-xl border flex items-center justify-center shrink-0 ${
                          isConn ? "bg-emerald-50 border-emerald-200 text-emerald-600 dark:bg-emerald-950/30 dark:border-emerald-800 dark:text-emerald-400" : "bg-gray-100 border-gray-200 text-gray-500 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-400"
                        }`}>
                          <Smartphone className="size-5" />
                        </div>
                        <div className="min-w-0">
                          <div className="flex items-center gap-1.5 flex-wrap">
                            <h3 className="font-bold text-sm text-gray-900 dark:text-white truncate">{dev.name}</h3>
                            {dev.is_default && (
                              <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold bg-amber-500 text-white shadow-xs whitespace-nowrap">
                                Utama
                              </span>
                            )}
                          </div>
                          <p className="text-xs font-mono text-gray-500 dark:text-gray-400 truncate">ID: {dev.session_id}</p>
                        </div>
                      </div>

                      <div>
                        {isConn ? (
                          <span className="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-bold bg-emerald-500 text-white shadow-xs whitespace-nowrap">
                            Terhubung
                          </span>
                        ) : isQrReady ? (
                          <span className="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-bold bg-amber-500 text-white shadow-xs whitespace-nowrap">
                            Siap Scan QR
                          </span>
                        ) : (
                          <span className="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-bold bg-rose-500 text-white shadow-xs whitespace-nowrap">
                            Terputus
                          </span>
                        )}
                      </div>
                    </div>

                    {/* Connected Info Sunken Box */}
                    <div className="mt-3.5 rounded-xl border border-gray-100 bg-gray-50/80 dark:border-gray-800 dark:bg-gray-900/50 p-3 space-y-1.5 text-xs">
                      <div className="flex justify-between items-center text-gray-500 dark:text-gray-400">
                        <span>Nomor WA</span>
                        <span className="font-bold text-gray-900 dark:text-white font-mono">
                          {dev.phone_number ? `+${dev.phone_number}` : "- Belum Terhubung -"}
                        </span>
                      </div>
                      {dev.profile_name && (
                        <div className="flex justify-between items-center text-gray-500 dark:text-gray-400">
                          <span>Nama Profil</span>
                          <span className="font-medium text-gray-900 dark:text-white truncate max-w-[160px]">{dev.profile_name}</span>
                        </div>
                      )}
                      <div className="flex justify-between items-center text-gray-500 dark:text-gray-400">
                        <span>API Key Sesi</span>
                        <div className="flex items-center gap-1 font-mono text-[11px] text-brand-600 dark:text-brand-400">
                          <span>{dev.api_key ? dev.api_key.slice(0, 10) + "..." : "-"}</span>
                          {dev.api_key && (
                            <button
                              type="button"
                              onClick={() => copyToClipboard(dev.api_key!, dev.session_id)}
                              className="text-gray-400 hover:text-gray-600 dark:hover:text-white cursor-pointer"
                            >
                              {copiedKey === dev.session_id ? <Check className="size-3 text-emerald-500" /> : <Copy className="size-3" />}
                            </button>
                          )}
                        </div>
                      </div>
                    </div>
                  </div>

                  {/* Single Kelola Button */}
                  <div className="pt-3 border-t border-gray-100 dark:border-gray-800 flex items-center justify-between">
                    <span className="text-[11px] text-gray-400">Sesi {dev.session_id}</span>
                    <button
                      type="button"
                      onClick={() => setManageDevice(dev)}
                      className="h-8 inline-flex items-center gap-1.5 px-3.5 rounded-lg bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200 font-bold text-xs transition cursor-pointer shadow-2xs"
                    >
                      <SlidersHorizontal className="size-3.5" /> Kelola
                    </button>
                  </div>
                </div>
              )
            })}
          </div>
        )}

        {/* Universal REST API Docs Banner Card */}
        <div className="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03] p-6 shadow-xs space-y-4">
          <div className="flex items-center gap-2.5">
            <div className="p-2 rounded-xl bg-brand-50 border border-brand-200 text-brand-600 dark:bg-brand-950/30 dark:border-brand-800 dark:text-brand-400">
              <Code className="size-5" />
            </div>
            <div>
              <h3 className="text-base font-bold text-gray-900 dark:text-white">Universal Endpoint Integrasi REST API</h3>
              <p className="text-xs text-gray-500 dark:text-gray-400">Gunakan endpoint ini untuk mengirim WhatsApp dari aplikasi eksternal, website, atau bot</p>
            </div>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div className="rounded-xl border border-gray-100 bg-gray-50/80 dark:border-gray-800 dark:bg-gray-900/50 p-4 space-y-2">
              <div className="flex justify-between items-center">
                <span className="text-xs font-bold text-emerald-600 dark:text-emerald-400 uppercase tracking-wider font-mono">POST /api/send-message</span>
                <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold bg-brand-500 text-white shadow-xs whitespace-nowrap">JSON / Form</span>
              </div>
              <p className="text-xs text-gray-600 dark:text-gray-400">Endpoint universal pengiriman pesan teks WhatsApp dengan auto-queue.</p>
              <div className="rounded-lg border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-950 p-2.5 text-[11px] font-mono text-gray-800 dark:text-gray-300 overflow-x-auto">
                {`curl -X POST http://${publicIp}:3000/api/send-message \\\n  -H "X-Api-Key: nodera..." \\\n  -d '{"phone": "081234567890", "message": "Halo dari NODERA Gateway"}'`}
              </div>
            </div>

            <div className="rounded-xl border border-gray-100 bg-gray-50/80 dark:border-gray-800 dark:bg-gray-900/50 p-4 space-y-2">
              <div className="flex justify-between items-center">
                <span className="text-xs font-bold text-brand-600 dark:text-brand-400 uppercase tracking-wider font-mono">POST /api/send-media</span>
                <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold bg-emerald-500 text-white shadow-xs whitespace-nowrap">Multipart</span>
              </div>
              <p className="text-xs text-gray-600 dark:text-gray-400">Kirim gambar struk/kuitansi, dokumen invoice PDF, atau file media.</p>
              <div className="rounded-lg border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-950 p-2.5 text-[11px] font-mono text-gray-800 dark:text-gray-300 overflow-x-auto">
                {`curl -X POST http://${publicIp}:3000/api/send-media \\\n  -H "X-Api-Key: nodera..." \\\n  -F "phone=081234567890" \\\n  -F "caption=Kuitansi Lunas" \\\n  -F "file=@/path/invoice.pdf"`}
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* ── MODAL KELOLA PERANGKAT WHATSAPP ── */}
      {manageDevice && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs animate-fadeIn">
          <div className="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 w-full max-w-md max-h-[92vh] overflow-y-auto p-6 space-y-5 shadow-2xl relative">
            <button
              type="button"
              onClick={() => setManageDevice(null)}
              className="absolute top-4 right-4 text-gray-400 hover:text-gray-600 dark:hover:text-white p-1 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 transition cursor-pointer"
            >
              <X className="size-5" />
            </button>

            <div className="space-y-1">
              <h3 className="text-lg font-bold text-gray-900 dark:text-white">Kelola Sesi WhatsApp</h3>
              <p className="text-xs text-gray-500 dark:text-gray-400">Detail status dan aksi cepat perangkat gateway</p>
            </div>

            {/* Info Box */}
            <div className="rounded-xl border border-gray-100 bg-gray-50/80 dark:border-gray-800 dark:bg-gray-900/50 p-3.5 space-y-2 text-xs">
              <div className="flex justify-between items-center">
                <span className="text-gray-500 dark:text-gray-400">Nama Perangkat</span>
                <span className="font-bold text-gray-900 dark:text-white">{manageDevice.name}</span>
              </div>
              <div className="flex justify-between items-center">
                <span className="text-gray-500 dark:text-gray-400">ID Sesi</span>
                <span className="font-mono text-gray-700 dark:text-gray-300">{manageDevice.session_id}</span>
              </div>
              <div className="flex justify-between items-center">
                <span className="text-gray-500 dark:text-gray-400">Nomor Telepon</span>
                <span className="font-mono font-bold text-gray-900 dark:text-white">{manageDevice.phone_number ? `+${manageDevice.phone_number}` : "-"}</span>
              </div>
              <div className="flex justify-between items-center">
                <span className="text-gray-500 dark:text-gray-400">Status Koneksi</span>
                <span className={`inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold text-white shadow-xs ${(manageDevice.is_connected || manageDevice.live_status === "CONNECTED") ? "bg-emerald-500" : manageDevice.live_status === "QR_READY" ? "bg-amber-500" : "bg-rose-500"}`}>
                  {(manageDevice.is_connected || manageDevice.live_status === "CONNECTED") ? "Terhubung" : manageDevice.live_status === "QR_READY" ? "Siap Scan QR" : "Terputus"}
                </span>
              </div>
              <div className="flex justify-between items-center">
                <span className="text-gray-500 dark:text-gray-400">Status Sesi</span>
                {manageDevice.is_default ? (
                  <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold bg-amber-500 text-white shadow-xs whitespace-nowrap">Sesi Utama</span>
                ) : (
                  <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold bg-gray-500 text-white shadow-xs whitespace-nowrap">Sesi Tambahan</span>
                )}
              </div>
            </div>

            {/* Aksi Cepat */}
            <div className="space-y-2 pt-2 border-t border-gray-100 dark:border-gray-800">
              {!(manageDevice.is_connected || manageDevice.live_status === "CONNECTED") ? (
                <div className="grid grid-cols-2 gap-2">
                  <button
                    type="button"
                    onClick={() => {
                      const d = manageDevice
                      setManageDevice(null)
                      openQrModal(d)
                    }}
                    className="inline-flex items-center justify-center gap-1.5 px-3 py-2.5 rounded-xl bg-emerald-500 hover:bg-emerald-600 active:scale-95 text-xs font-bold text-white shadow-xs transition cursor-pointer"
                  >
                    <QrCode className="size-4" /> Scan QR Code
                  </button>

                  <button
                    type="button"
                    onClick={() => {
                      const d = manageDevice
                      setManageDevice(null)
                      openPairingModal(d)
                    }}
                    className="inline-flex items-center justify-center gap-1.5 px-3 py-2.5 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:hover:bg-gray-800 text-xs font-semibold text-gray-700 dark:text-gray-200 transition shadow-2xs cursor-pointer"
                  >
                    <Key className="size-3.5" /> Pairing Code
                  </button>
                </div>
              ) : (
                <div className="space-y-2">
                  {!manageDevice.is_default && (
                    <button
                      type="button"
                      onClick={() => {
                        router.post(`/admin/whatsapp-gateway/set-default/${manageDevice.session_id}`)
                        setManageDevice(null)
                      }}
                      className="w-full inline-flex items-center justify-center gap-1.5 px-3 py-2.5 rounded-xl bg-amber-500 hover:bg-amber-600 active:scale-95 text-xs font-bold text-white shadow-xs transition cursor-pointer"
                    >
                      <Star className="size-3.5" /> Jadikan Sesi Utama (Default)
                    </button>
                  )}

                  <button
                    type="button"
                    onClick={() => {
                      if (confirm(`Yakin ingin memutus koneksi WhatsApp ${manageDevice.name}?`)) {
                        router.post(`/admin/whatsapp-gateway/logout/${manageDevice.session_id}`)
                        setManageDevice(null)
                      }
                    }}
                    className="w-full inline-flex items-center justify-center gap-1.5 px-3 py-2.5 rounded-xl bg-rose-500 hover:bg-rose-600 active:scale-95 text-xs font-bold text-white shadow-xs transition cursor-pointer"
                  >
                    <Power className="size-3.5" /> Putus Koneksi (Logout)
                  </button>
                </div>
              )}

              <button
                type="button"
                onClick={() => {
                  setTestSessionId(manageDevice.session_id)
                  setManageDevice(null)
                  setTestModalOpen(true)
                }}
                className="w-full inline-flex items-center justify-center gap-1.5 px-3 py-2.5 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:hover:bg-gray-800 text-xs font-semibold text-gray-700 dark:text-gray-200 transition shadow-2xs cursor-pointer"
              >
                <Send className="size-4 text-emerald-500" /> Uji Kirim Pesan dari Sesi Ini
              </button>

              <button
                type="button"
                onClick={() => {
                  if (confirm(`Hapus sesi WhatsApp ${manageDevice.name}? Data autentikasi akan dihapus permanen.`)) {
                    router.post(`/admin/whatsapp-gateway/delete/${manageDevice.session_id}`)
                    setManageDevice(null)
                  }
                }}
                className="w-full inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl border border-rose-200 text-rose-600 hover:bg-rose-50 dark:border-rose-900/40 dark:text-rose-400 dark:hover:bg-rose-950/20 text-xs font-semibold transition cursor-pointer"
              >
                <Trash2 className="size-3.5" /> Hapus Sesi Permanen
              </button>

              <button
                type="button"
                onClick={() => setManageDevice(null)}
                className="w-full inline-flex items-center justify-center px-4 py-2 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:hover:bg-gray-800 text-xs font-semibold text-gray-700 dark:text-gray-200 transition shadow-2xs cursor-pointer"
              >
                Tutup
              </button>
            </div>
          </div>
        </div>
      )}

      {/* ── MODAL SCAN QR CODE ── */}
      {qrModalOpen && selectedDevice && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs animate-fadeIn">
          <div className="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 w-full max-w-md max-h-[92vh] overflow-y-auto p-6 space-y-5 shadow-2xl relative">
            <button
              type="button"
              onClick={() => setQrModalOpen(false)}
              className="absolute top-4 right-4 text-gray-400 hover:text-gray-600 dark:hover:text-white p-1 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 transition cursor-pointer"
            >
              <X className="size-5" />
            </button>

            <div className="text-center space-y-1">
              <div className="inline-flex p-3 bg-emerald-50 text-emerald-600 dark:bg-emerald-950/30 dark:text-emerald-400 rounded-2xl mb-1">
                <QrCode className="size-8" />
              </div>
              <h3 className="text-lg font-bold text-gray-900 dark:text-white">Scan QR WhatsApp</h3>
              <p className="text-xs text-gray-500 dark:text-gray-400">Sesi: <span className="text-emerald-600 dark:text-emerald-400 font-bold">{selectedDevice.name}</span></p>
            </div>

            <div className="flex flex-col items-center justify-center p-4 bg-white rounded-2xl border-2 border-emerald-500/30 min-h-[260px]">
              {qrDataUrl ? (
                <img src={qrDataUrl} alt="WhatsApp QR Code" className="size-56 rounded-lg object-contain" />
              ) : (
                <div className="flex flex-col items-center gap-3 text-gray-500">
                  <RefreshCw className="size-8 animate-spin text-emerald-500" />
                  <span className="text-xs font-bold">Membuat QR Code baru...</span>
                </div>
              )}
            </div>

            <div className="rounded-xl border border-gray-100 bg-gray-50/80 dark:border-gray-800 dark:bg-gray-900/50 p-3.5 space-y-2 text-xs text-gray-700 dark:text-gray-300">
              <p className="font-bold text-gray-900 dark:text-white flex items-center gap-1.5">
                <Smartphone className="size-3.5 text-emerald-500" /> Langkah Menghubungkan
              </p>
              <ol className="list-decimal list-inside space-y-1 text-gray-600 dark:text-gray-400 text-[11px] leading-relaxed">
                <li>Buka aplikasi <strong>WhatsApp</strong> di ponsel Anda</li>
                <li>Ketuk menu <strong>Titik Tiga (⋮)</strong> atau <strong>Pengaturan</strong></li>
                <li>Pilih <strong>Perangkat Tertaut (Linked Devices)</strong></li>
                <li>Ketuk <strong>Tautkan Perangkat</strong> dan arahkan kamera ke QR di atas</li>
              </ol>
            </div>

            <div className="flex items-center justify-between pt-1">
              <span className="text-[11px] text-gray-400 flex items-center gap-1">
                <RefreshCw className="size-3 animate-spin" /> Auto-refresh setiap 3 detik
              </span>
              <button
                type="button"
                onClick={() => fetchQrCode(selectedDevice.session_id)}
                className="text-xs font-bold text-brand-600 dark:text-brand-400 hover:underline cursor-pointer"
              >
                Refresh Manual
              </button>
            </div>
          </div>
        </div>
      )}

      {/* ── MODAL PAIRING CODE ── */}
      {pairingModalOpen && selectedDevice && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs animate-fadeIn">
          <div className="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 w-full max-w-md max-h-[92vh] overflow-y-auto p-6 space-y-5 shadow-2xl relative">
            <button
              type="button"
              onClick={() => setPairingModalOpen(false)}
              className="absolute top-4 right-4 text-gray-400 hover:text-gray-600 dark:hover:text-white p-1 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 transition cursor-pointer"
            >
              <X className="size-5" />
            </button>

            <div className="text-center space-y-1">
              <div className="inline-flex p-3 bg-brand-50 text-brand-600 dark:bg-brand-950/30 dark:text-brand-400 rounded-2xl mb-1">
                <Key className="size-8" />
              </div>
              <h3 className="text-lg font-bold text-gray-900 dark:text-white">Hubungkan via Pairing Code</h3>
              <p className="text-xs text-gray-500 dark:text-gray-400">Tautkan nomor WhatsApp tanpa perlu scan kamera</p>
            </div>

            {!pairingCodeResult ? (
              <form onSubmit={handleRequestPairingCode} className="space-y-4">
                <div className="space-y-1.5">
                  <label className="text-xs font-bold text-gray-700 dark:text-gray-200">Nomor WhatsApp Ponsel</label>
                  <input
                    type="text"
                    required
                    value={pairingPhone}
                    onChange={(e) => setPairingPhone(e.target.value)}
                    placeholder="Contoh: 081234567890 / 6281234567890"
                    className="w-full rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-950 px-3.5 py-2.5 text-xs text-gray-900 dark:text-white font-mono focus:border-brand-500 focus:outline-hidden"
                  />
                  <p className="text-[11px] text-gray-400">Masukkan nomor yang aktif di aplikasi WhatsApp ponsel Anda.</p>
                </div>

                <button
                  type="submit"
                  disabled={pairingLoading}
                  className="w-full py-2.5 bg-brand-500 hover:bg-brand-600 text-white font-bold text-xs rounded-xl transition shadow-xs flex items-center justify-center gap-2 cursor-pointer disabled:opacity-50"
                >
                  {pairingLoading ? (
                    <>
                      <RefreshCw className="size-4 animate-spin" /> Meminta Kode WhatsApp...
                    </>
                  ) : (
                    <>
                      <Key className="size-4" /> Dapatkan 8-Digit Kode Pairing
                    </>
                  )}
                </button>
              </form>
            ) : (
              <div className="space-y-4 text-center">
                <div className="p-4 rounded-2xl border-2 border-dashed border-emerald-500 bg-emerald-50/50 dark:bg-emerald-950/20 space-y-2">
                  <p className="text-xs font-bold text-gray-600 dark:text-gray-400">Masukkan kode ini di notifikasi WhatsApp ponsel:</p>
                  <div className="text-2xl font-black font-mono tracking-widest text-emerald-600 dark:text-emerald-400 select-all">
                    {pairingCodeResult}
                  </div>
                </div>

                <p className="text-xs text-gray-500 dark:text-gray-400 leading-relaxed">
                  Buka notifikasi WhatsApp di ponsel Anda atau buka <strong>Perangkat Tertaut &gt; Tautkan dengan nomor telepon</strong> dan masukkan kode di atas.
                </p>

                <button
                  type="button"
                  onClick={() => setPairingCodeResult(null)}
                  className="px-4 py-2 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:hover:bg-gray-800 text-xs font-semibold text-gray-700 dark:text-gray-200 transition shadow-2xs cursor-pointer"
                >
                  Minta Kode Baru
                </button>
              </div>
            )}
          </div>
        </div>
      )}

      {/* ── MODAL TAMBAH SESI ── */}
      {addModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs animate-fadeIn">
          <div className="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 w-full max-w-md max-h-[92vh] overflow-y-auto p-6 space-y-5 shadow-2xl relative">
            <button
              type="button"
              onClick={() => setAddModalOpen(false)}
              className="absolute top-4 right-4 text-gray-400 hover:text-gray-600 dark:hover:text-white p-1 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 transition cursor-pointer"
            >
              <X className="size-5" />
            </button>

            <div className="space-y-1">
              <h3 className="text-lg font-bold text-gray-900 dark:text-white">Tambah Perangkat WhatsApp Baru</h3>
              <p className="text-xs text-gray-500 dark:text-gray-400">Buat sesi baru untuk nomor CS, Kasir, atau Notifikasi</p>
            </div>

            <form onSubmit={handleCreateSession} className="space-y-4">
              <div className="space-y-1.5">
                <label className="text-xs font-bold text-gray-700 dark:text-gray-200">Nama Perangkat / Sesi</label>
                <input
                  type="text"
                  required
                  value={newSessionName}
                  onChange={(e) => setNewSessionName(e.target.value)}
                  placeholder="Contoh: CS Billing Cabang Timur"
                  className="w-full rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-950 px-3.5 py-2.5 text-xs text-gray-900 dark:text-white focus:border-brand-500 focus:outline-hidden"
                />
              </div>

              <div className="space-y-1.5">
                <label className="text-xs font-bold text-gray-700 dark:text-gray-200">Webhook URL Inbound (Opsional)</label>
                <input
                  type="url"
                  value={newWebhookUrl}
                  onChange={(e) => setNewWebhookUrl(e.target.value)}
                  placeholder="https://domain-anda.com/api/wa-webhook"
                  className="w-full rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-950 px-3.5 py-2.5 text-xs font-mono text-gray-900 dark:text-white focus:border-brand-500 focus:outline-hidden"
                />
                <p className="text-[11px] text-gray-400">URL untuk menerima notifikasi pesan masuk / balasan pelanggan.</p>
              </div>

              <div className="pt-2 flex justify-end gap-2">
                <button
                  type="button"
                  onClick={() => setAddModalOpen(false)}
                  className="px-4 py-2 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:hover:bg-gray-800 text-xs font-semibold text-gray-700 dark:text-gray-200 transition shadow-2xs cursor-pointer"
                >
                  Batal
                </button>
                <button
                  type="submit"
                  className="px-5 py-2 bg-brand-500 hover:bg-brand-600 text-white font-bold text-xs rounded-xl transition shadow-xs cursor-pointer"
                >
                  Simpan & Buat Sesi
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* ── MODAL UJI PESAN ── */}
      {testModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs animate-fadeIn">
          <div className="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 w-full max-w-md max-h-[92vh] overflow-y-auto p-6 space-y-5 shadow-2xl relative">
            <button
              type="button"
              onClick={() => setTestModalOpen(false)}
              className="absolute top-4 right-4 text-gray-400 hover:text-gray-600 dark:hover:text-white p-1 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 transition cursor-pointer"
            >
              <X className="size-5" />
            </button>

            <div className="space-y-1">
              <h3 className="text-lg font-bold text-gray-900 dark:text-white">Uji Kirim Pesan WhatsApp</h3>
              <p className="text-xs text-gray-500 dark:text-gray-400">Kirim pesan langsung ke nomor ponsel untuk memverifikasi gateway</p>
            </div>

            <form onSubmit={handleSendTest} className="space-y-4">
              <div className="space-y-1.5">
                <label className="text-xs font-bold text-gray-700 dark:text-gray-200">Pilih Sesi Pengirim</label>
                <select
                  value={testSessionId}
                  onChange={(e) => setTestSessionId(e.target.value)}
                  className="w-full rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-950 px-3.5 py-2.5 text-xs text-gray-900 dark:text-white focus:border-brand-500 focus:outline-hidden"
                >
                  {devices.map((d) => (
                    <option key={d.session_id} value={d.session_id}>
                      {d.name} ({d.phone_number ? `+${d.phone_number}` : "Offline"})
                    </option>
                  ))}
                </select>
              </div>

              <div className="space-y-1.5">
                <label className="text-xs font-bold text-gray-700 dark:text-gray-200">Nomor Ponsel Tujuan</label>
                <input
                  type="text"
                  required
                  value={testPhone}
                  onChange={(e) => setTestPhone(e.target.value)}
                  placeholder="Contoh: 081234567890"
                  className="w-full rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-950 px-3.5 py-2.5 text-xs font-mono text-gray-900 dark:text-white focus:border-brand-500 focus:outline-hidden"
                />
              </div>

              <div className="space-y-1.5">
                <label className="text-xs font-bold text-gray-700 dark:text-gray-200">Isi Pesan</label>
                <textarea
                  rows={4}
                  required
                  value={testMessage}
                  onChange={(e) => setTestMessage(e.target.value)}
                  className="w-full rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-950 p-3 text-xs leading-relaxed text-gray-900 dark:text-white focus:border-brand-500 focus:outline-hidden"
                />
              </div>

              <div className="pt-2 flex justify-end gap-2">
                <button
                  type="button"
                  onClick={() => setTestModalOpen(false)}
                  className="px-4 py-2 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:hover:bg-gray-800 text-xs font-semibold text-gray-700 dark:text-gray-200 transition shadow-2xs cursor-pointer"
                >
                  Batal
                </button>
                <button
                  type="submit"
                  disabled={sendingTest}
                  className="px-5 py-2 bg-brand-500 hover:bg-brand-600 text-white font-bold text-xs rounded-xl transition shadow-xs flex items-center gap-1.5 cursor-pointer disabled:opacity-50"
                >
                  {sendingTest ? <RefreshCw className="size-4 animate-spin" /> : <Send className="size-4" />} Kirim Pesan Uji
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* ── MODAL REST API DOCS ── */}
      {apiDocOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs animate-fadeIn">
          <div className="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 w-full max-w-2xl max-h-[85vh] overflow-y-auto p-6 space-y-5 shadow-2xl relative">
            <button
              type="button"
              onClick={() => setApiDocOpen(false)}
              className="absolute top-4 right-4 text-gray-400 hover:text-gray-600 dark:hover:text-white p-1 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 transition cursor-pointer"
            >
              <X className="size-5" />
            </button>

            <div className="space-y-1">
              <h3 className="text-lg font-bold text-gray-900 dark:text-white flex items-center gap-2">
                <Code className="size-5 text-brand-500" /> Dokumentasi Integrasi REST API
              </h3>
              <p className="text-xs text-gray-500 dark:text-gray-400">Endpoint & Format Payload untuk integrasi sistem eksternal</p>
            </div>

            <div className="space-y-4 text-xs">
              <div className="rounded-xl border border-gray-100 bg-gray-50/80 dark:border-gray-800 dark:bg-gray-900/50 p-4 space-y-2">
                <h4 className="font-bold text-gray-900 dark:text-white">1. Kirim Pesan Teks</h4>
                <div className="rounded-lg border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-950 p-3 font-mono text-[11px] text-gray-800 dark:text-gray-300 overflow-x-auto space-y-1">
                  <div className="text-emerald-600 dark:text-emerald-400 font-bold">POST http://{publicIp || "IP_SERVER"}:3000/api/send-message</div>
                  <div className="text-gray-400">Headers: X-Api-Key: &lt;API_KEY&gt;</div>
                  <div className="text-gray-400">Content-Type: application/json</div>
                  <pre className="text-gray-900 dark:text-gray-200 mt-2">
{`{
  "session": "default",
  "phone": "081234567890",
  "message": "Halo! Tagihan internet Anda sebesar Rp 150.000 telah terbit."
}`}
                  </pre>
                </div>
              </div>

              <div className="rounded-xl border border-gray-100 bg-gray-50/80 dark:border-gray-800 dark:bg-gray-900/50 p-4 space-y-2">
                <h4 className="font-bold text-gray-900 dark:text-white">2. Kirim Dokumen PDF / Gambar Media</h4>
                <div className="rounded-lg border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-950 p-3 font-mono text-[11px] text-gray-800 dark:text-gray-300 overflow-x-auto space-y-1">
                  <div className="text-brand-600 dark:text-brand-400 font-bold">POST http://{publicIp || "IP_SERVER"}:3000/api/send-media</div>
                  <div className="text-gray-400">Headers: X-Api-Key: &lt;API_KEY&gt;</div>
                  <div className="text-gray-400">Content-Type: multipart/form-data</div>
                  <pre className="text-gray-900 dark:text-gray-200 mt-2">
{`Form fields:
- phone: "081234567890"
- caption: "Kwitansi Pembayaran Lunas"
- file: (File Blob PDF/Image)`}
                  </pre>
                </div>
              </div>
            </div>

            <div className="flex justify-end pt-2">
              <button
                type="button"
                onClick={() => setApiDocOpen(false)}
                className="px-5 py-2 bg-brand-500 hover:bg-brand-600 text-white font-bold text-xs rounded-xl transition shadow-xs cursor-pointer"
              >
                Tutup Dokumentasi
              </button>
            </div>
          </div>
        </div>
      )}
    </AppLayout>
  )
}
