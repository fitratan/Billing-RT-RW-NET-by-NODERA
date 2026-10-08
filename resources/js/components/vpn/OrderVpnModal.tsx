import React, { useState, useEffect } from "react"
import { createPortal } from "react-dom"
import { useForm, usePage } from "@inertiajs/react"
import { Server, Eye, EyeOff, Loader2, X, AlertCircle } from "lucide-react"
import { formatIDR } from "@/lib/utils"

interface ServerItem {
  id: number
  name: string
  location: string | null
  host: string
}

interface PackageItem {
  id: number
  name: string
  port_count: number
  duration_days: number
  price: number
  protocols: string[]
}

interface OrderVpnModalProps {
  isOpen: boolean
  onClose: () => void
  servers?: ServerItem[]
  packages?: PackageItem[]
  userSaldo?: number
}

const defaultPortSeq = ["8291", "80", "8728", "22", "8132"]
const portLabels = [
  "Port 1 (Winbox / Remote 1)",
  "Port 2 (WebFig / HTTP / API)",
  "Port 3 (API / Service 3)",
  "Port 4 (SSH / Custom 4)",
  "Port 5 (Custom Service 5)",
]

export const OrderVpnModal: React.FC<OrderVpnModalProps> = ({
  isOpen,
  onClose,
  servers = [],
  packages = [],
  userSaldo = 0,
}) => {
  const { flash } = usePage().props as any
  const firstPkg = packages[0]
  const firstServer = servers[0]

  const [portCount, setPortCount] = useState<number>(3)
  const [ports, setPorts] = useState<string[]>(["8291", "80", "8728"])
  const [portProtocols, setPortProtocols] = useState<string[]>(["tcp", "tcp", "tcp"])
  const [showPassword, setShowPassword] = useState(false)

  const { data, setData, post, processing, errors, reset, clearErrors } = useForm<{
    server_id: string
    package_id: string
    vpn_username: string
    vpn_password: string
    protocol: string
    ports: string[]
    port_protocols: string[]
  }>({
    server_id: firstServer ? String(firstServer.id) : (servers[0] ? String(servers[0].id) : "1"),
    package_id: firstPkg ? String(firstPkg.id) : (packages[0] ? String(packages[0].id) : "1"),
    vpn_username: "",
    vpn_password: "",
    protocol: firstPkg?.protocols?.[0] ?? "sstp",
    ports: ["8291", "80", "8728"],
    port_protocols: ["tcp", "tcp", "tcp"],
  })

  // Sync initial server and package when modal opens or props load
  useEffect(() => {
    if (isOpen) {
      if (servers.length > 0 && (!data.server_id || !servers.some((s) => String(s.id) === String(data.server_id)))) {
        setData("server_id", String(servers[0].id))
      }
      if (packages.length > 0 && (!data.package_id || !packages.some((p) => String(p.id) === String(data.package_id)))) {
        const initialPkg = packages[0]
        setData("package_id", String(initialPkg.id))
        const count = Math.max(1, Math.min(5, initialPkg.port_count || 3))
        setPortCount(count)
        const newPorts = defaultPortSeq.slice(0, count)
        setPorts(newPorts)
        setPortProtocols(newPorts.map(() => "tcp"))
        setData("ports", newPorts)
        setData("port_protocols", newPorts.map(() => "tcp"))
      }
    }
  }, [isOpen, servers, packages])

  const selectedPkg = packages.find((p) => String(p.id) === data.package_id) || firstPkg
  const pkgPrice = selectedPkg?.price || 0
  const isEnough = userSaldo >= pkgPrice

  const handlePortCountChange = (count: number) => {
    const clamped = Math.max(1, Math.min(5, count))
    setPortCount(clamped)
    const newPorts = defaultPortSeq.slice(0, count)
    const newProtocols: string[] = []
    for (let i = 0; i < clamped; i++) {
      if (ports[i]) newPorts[i] = ports[i]
      newProtocols[i] = portProtocols[i] || "tcp"
    }
    setPorts(newPorts)
    setPortProtocols(newProtocols)
    setData((prev) => ({
      ...prev,
      ports: newPorts,
      port_protocols: newProtocols,
    }))
  }

  const handleSelectPackage = (pkgId: string) => {
    const p = packages.find((pkg) => String(pkg.id) === pkgId)
    if (!p) return
    const count = Math.max(1, Math.min(5, p.port_count || portCount))
    setPortCount(count)
    const newPorts = defaultPortSeq.slice(0, count)
    const newProtocols: string[] = []
    for (let i = 0; i < count; i++) {
      if (ports[i]) newPorts[i] = ports[i]
      newProtocols[i] = portProtocols[i] || "tcp"
    }
    setPorts(newPorts)
    setPortProtocols(newProtocols)
    setData((prev) => ({
      ...prev,
      package_id: String(p.id),
      protocol: p.protocols?.[0] ?? "sstp",
      ports: newPorts,
      port_protocols: newProtocols,
    }))
  }

  const handlePortChange = (idx: number, val: string) => {
    const next = [...ports]
    next[idx] = val
    setPorts(next)
    setData("ports", next)
  }

  const handleProtocolChange = (idx: number, proto: string) => {
    const next = [...portProtocols]
    next[idx] = proto.toLowerCase()
    setPortProtocols(next)
    setData("port_protocols", next)
  }

  const handleClose = () => {
    if (processing) return
    clearErrors()
    reset()
    onClose()
  }

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    post("/akun/order", {
      preserveScroll: true,
      onSuccess: () => {
        handleClose()
      },
    })
  }

  if (!isOpen) return null

  const hasErrors = Boolean(flash?.error || Object.keys(errors).length > 0)

  return createPortal(
    <div className="fixed inset-0 z-[99999] flex items-center justify-center bg-black/70 p-4 backdrop-blur-sm animate-in fade-in duration-200">
      <div className="relative w-full max-w-lg rounded-2xl border border-gray-200 bg-white p-5 sm:p-6 shadow-2xl dark:border-gray-800 dark:bg-[#121720] space-y-4 max-h-[92vh] overflow-y-auto custom-scrollbar">
        {/* Header */}
        <div className="flex items-center justify-between pb-3 border-b border-gray-100 dark:border-gray-800">
          <div className="flex items-center gap-2.5">
            <div className="flex h-9 w-9 items-center justify-center rounded-xl bg-brand-500/10 text-brand-500">
              <Server className="h-5 w-5" />
            </div>
            <div>
              <h3 className="font-bold text-sm sm:text-base text-gray-900 dark:text-white">
                Order VPN Remote Baru
              </h3>
              <p className="text-[11px] text-gray-500 dark:text-gray-400">
                Pilih gateway server, durasi, dan mapping port forward
              </p>
            </div>
          </div>
          <button
            type="button"
            onClick={handleClose}
            disabled={processing}
            className="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 disabled:opacity-50 disabled:cursor-not-allowed disabled:pointer-events-none dark:hover:bg-gray-800 dark:hover:text-gray-200 transition cursor-pointer"
          >
            <X className="h-5 w-5" />
          </button>
        </div>

        {/* Global / Validation Error Banner */}
        {hasErrors && (
          <div className="p-3.5 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-600 dark:text-rose-400 text-xs space-y-1">
            <div className="flex items-center gap-1.5 font-bold">
              <AlertCircle className="h-4 w-4 shrink-0" />
              <span>Gagal memproses order:</span>
            </div>
            <ul className="list-disc pl-5 space-y-0.5 font-medium">
              {flash?.error && <li>{flash.error}</li>}
              {Object.entries(errors).map(([key, msg]) => (
                <li key={key}>{String(msg)}</li>
              ))}
            </ul>
          </div>
        )}

        <form onSubmit={handleSubmit} className="space-y-3.5">
          {/* Grid: Server & Package Dropdowns */}
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
            {/* Server Gateway Dropdown */}
            <div>
              <label className="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-1.5">
                Server Gateway <span className="text-rose-500">*</span>
              </label>
              <select
                value={data.server_id}
                onChange={(e) => setData("server_id", e.target.value)}
                required
                className="h-10 w-full rounded-xl border border-gray-200 bg-gray-50/50 px-3 text-xs font-bold text-gray-900 focus:border-brand-500 focus:bg-white focus:outline-hidden dark:border-gray-800 dark:bg-gray-900 dark:text-white cursor-pointer"
              >
                {servers.length > 0 ? (
                  servers.map((s) => (
                    <option key={s.id} value={String(s.id)}>
                      {s.name} ({s.location || "Indonesia"})
                    </option>
                  ))
                ) : (
                  <option value="1">SERVER ID1 — Jakarta (ID Gateway)</option>
                )}
              </select>
              {errors.server_id && (
                <p className="mt-1 text-xs text-rose-500 font-medium">{errors.server_id}</p>
              )}
            </div>

            {/* Package Dropdown */}
            <div>
              <label className="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-1.5">
                Paket Langganan <span className="text-rose-500">*</span>
              </label>
              <select
                value={data.package_id}
                onChange={(e) => handleSelectPackage(e.target.value)}
                required
                className="h-10 w-full rounded-xl border border-gray-200 bg-gray-50/50 px-3 text-xs font-bold text-gray-900 focus:border-brand-500 focus:bg-white focus:outline-hidden dark:border-gray-800 dark:bg-gray-900 dark:text-white cursor-pointer"
              >
                {packages.length > 0 ? (
                  packages.map((pkg) => (
                    <option key={pkg.id} value={String(pkg.id)}>
                      {pkg.name} — {formatIDR(pkg.price)} ({pkg.duration_days} Hari)
                    </option>
                  ))
                ) : (
                  <option value="1">1 Bulan — Rp 3.000 (30 Hari)</option>
                )}
              </select>
              {errors.package_id && (
                <p className="mt-1 text-xs text-rose-500 font-medium">{errors.package_id}</p>
              )}
            </div>
          </div>

          {/* Dedicated Dropdown for Port Count */}
          <div>
            <div className="flex items-center justify-between mb-1.5">
              <label className="text-xs font-bold text-gray-700 dark:text-gray-300">
                Jumlah Port Forwarding <span className="text-rose-500">*</span>
              </label>
              <span className="text-[11px] font-mono text-brand-600 dark:text-brand-400 font-bold">
                {portCount} Port Aktif
              </span>
            </div>
            <select
              value={portCount}
              onChange={(e) => handlePortCountChange(Number(e.target.value))}
              className="h-10 w-full rounded-xl border border-gray-200 bg-gray-50/50 px-3 text-xs font-bold text-gray-900 focus:border-brand-500 focus:bg-white focus:outline-hidden dark:border-gray-800 dark:bg-gray-900 dark:text-white cursor-pointer"
            >
              <option value={1}>1 Port Forwarding (Port 8291)</option>
              <option value={2}>2 Port Forwarding (Port 8291, 80)</option>
              <option value={3}>3 Port Forwarding (Port 8291, 80, 8728)</option>
              <option value={4}>4 Port Forwarding (Port 8291, 80, 8728, 22)</option>
              <option value={5}>5 Port Forwarding (5 Port Custom)</option>
            </select>
          </div>

          {/* Dynamic Target Ports with Protocol Selection */}
          <div className="space-y-2 pt-1">
            <label className="block text-xs font-bold text-gray-700 dark:text-gray-300">
              Konfigurasi Port Internal &amp; Protokol
            </label>
            <div className="space-y-2">
              {ports.map((port, idx) => (
                <div
                  key={idx}
                  className="p-2.5 rounded-xl border border-gray-200 bg-gray-50/60 dark:border-gray-800 dark:bg-gray-900/60 space-y-1.5"
                >
                  <span className="text-[11px] font-bold text-gray-600 dark:text-gray-400 block">
                    {portLabels[idx] || `Port Forward #${idx + 1}`}
                  </span>
                  <div className="grid grid-cols-12 gap-2">
                    <div className="col-span-4">
                      <select
                        value={portProtocols[idx] || "tcp"}
                        onChange={(e) => handleProtocolChange(idx, e.target.value)}
                        className="h-9 w-full rounded-lg border border-gray-200 bg-white px-2 text-xs font-bold text-gray-800 focus:border-brand-500 focus:outline-hidden dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 cursor-pointer"
                      >
                        <option value="tcp">TCP</option>
                        <option value="udp">UDP</option>
                      </select>
                    </div>
                    <div className="col-span-8">
                      <input
                        type="number"
                        value={port}
                        onChange={(e) => handlePortChange(idx, e.target.value)}
                        placeholder="8291"
                        required
                        min={1}
                        max={65535}
                        className="h-9 w-full rounded-lg border border-gray-200 bg-white px-3 text-xs font-mono font-bold text-gray-900 focus:border-brand-500 focus:outline-hidden dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                      />
                    </div>
                  </div>
                </div>
              ))}
            </div>
            {errors.ports && (
              <p className="text-xs text-rose-500 font-medium">{errors.ports}</p>
            )}
          </div>

          {/* Credentials */}
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <label className="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-1.5">
                Username VPN <span className="text-rose-500">*</span>
              </label>
              <input
                type="text"
                value={data.vpn_username}
                onChange={(e) => setData("vpn_username", e.target.value.toLowerCase().replace(/[^a-z0-9_]/g, ""))}
                placeholder="contoh: router_utama"
                required
                minLength={3}
                maxLength={50}
                className="h-10 w-full rounded-xl border border-gray-200 bg-gray-50/50 px-3 text-xs font-mono font-bold text-gray-900 placeholder:text-gray-400 focus:border-brand-500 focus:bg-white focus:outline-hidden dark:border-gray-800 dark:bg-gray-900 dark:text-white"
              />
              {errors.vpn_username && (
                <p className="mt-1 text-xs text-rose-500 font-medium">{errors.vpn_username}</p>
              )}
            </div>

            <div>
              <label className="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-1.5">
                Password VPN (Min. 6 Karakter) <span className="text-rose-500">*</span>
              </label>
              <div className="relative">
                <input
                  type={showPassword ? "text" : "password"}
                  value={data.vpn_password}
                  onChange={(e) => setData("vpn_password", e.target.value)}
                  placeholder="Minimal 6 karakter"
                  required
                  minLength={6}
                  maxLength={50}
                  className="h-10 w-full rounded-xl border border-gray-200 bg-gray-50/50 pl-3 pr-10 text-xs font-mono font-bold text-gray-900 placeholder:text-gray-400 focus:border-brand-500 focus:bg-white focus:outline-hidden dark:border-gray-800 dark:bg-gray-900 dark:text-white"
                />
                <button
                  type="button"
                  onClick={() => setShowPassword(!showPassword)}
                  className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 cursor-pointer"
                >
                  {showPassword ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                </button>
              </div>
              {errors.vpn_password && (
                <p className="mt-1 text-xs text-rose-500 font-medium">{errors.vpn_password}</p>
              )}
            </div>
          </div>

          {/* Action Buttons */}
          <div className="flex items-center justify-end gap-2.5 pt-3 border-t border-gray-100 dark:border-gray-800">
            <button
              type="button"
              onClick={handleClose}
              disabled={processing}
              className="h-10 px-4 rounded-xl border border-gray-200 bg-white text-xs font-bold text-gray-700 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed disabled:pointer-events-none dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800 transition cursor-pointer"
            >
              Batal
            </button>
            <button
              type="submit"
              disabled={processing || !isEnough}
              className="h-10 px-5 rounded-xl bg-brand-500 hover:bg-brand-600 disabled:opacity-50 disabled:cursor-not-allowed disabled:pointer-events-none text-xs font-bold text-white shadow-theme-xs transition flex items-center justify-center gap-1.5 cursor-pointer"
            >
              {processing && <Loader2 className="h-3.5 w-3.5 animate-spin" />}
              <span>
                {processing ? "Memproses Order..." : isEnough ? `Order Sekarang (${formatIDR(pkgPrice)})` : "Saldo Tidak Cukup"}
              </span>
            </button>
          </div>
        </form>
      </div>
    </div>,
    document.body
  )
}

export default OrderVpnModal
