import React, { useState, useEffect } from "react"
import { router } from "@inertiajs/react"
import { Server, X, Loader2, AlertCircle, Settings } from "lucide-react"

interface PortMapping {
  protocol?: string
  port?: number
  public_port?: number
  target?: number
  dst_port?: number
  internal_port?: number
  label?: string
}

interface VpnAccount {
  id: number
  vpn_username: string
  server?: string
  host?: string
  ports?: PortMapping[]
}

interface EditPortModalProps {
  isOpen: boolean
  onClose: () => void
  account: VpnAccount | null
}

export const EditPortModal: React.FC<EditPortModalProps> = ({
  isOpen,
  onClose,
  account,
}) => {
  if (!isOpen || !account) return null

  const rawPorts = account.ports || []
  const initialPorts = rawPorts.length > 0
    ? rawPorts.map((p) => ({
        dst_port: p.dst_port ?? p.target ?? p.internal_port ?? 8291,
        public_port: p.public_port ?? p.port ?? 0,
        protocol: (p.protocol || "tcp").toLowerCase(),
      }))
    : [{ dst_port: 8291, public_port: 0, protocol: "tcp" }]

  const [ports, setPorts] = useState(initialPorts)
  const [processing, setProcessing] = useState(false)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    if (account?.ports && account.ports.length > 0) {
      setPorts(
        account.ports.map((p) => ({
          dst_port: p.dst_port ?? p.target ?? p.internal_port ?? 8291,
          public_port: p.public_port ?? p.port ?? 0,
          protocol: (p.protocol || "tcp").toLowerCase(),
        }))
      )
    }
  }, [account])

  const handleInternalPortChange = (idx: number, val: number) => {
    const next = [...ports]
    next[idx] = { ...next[idx], dst_port: val }
    setPorts(next)
  }

  const handleProtocolChange = (idx: number, proto: string) => {
    const next = [...ports]
    next[idx] = { ...next[idx], protocol: proto.toLowerCase() }
    setPorts(next)
  }

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    setError(null)
    setProcessing(true)

    // Backend expects array of integer destination ports
    const internalPortArray = ports.map((p) => Number(p.dst_port || 8291))

    router.post(
      `/akun/${account.id}/edit-port`,
      {
        ports: internalPortArray,
      },
      {
        preserveScroll: true,
        onSuccess: () => {
          setProcessing(false)
          onClose()
        },
        onError: (errs) => {
          setProcessing(false)
          setError(Object.values(errs)[0] || "Gagal mengubah port.")
        },
        onFinish: () => setProcessing(false),
      }
    )
  }

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-xs animate-in fade-in duration-200">
      <div className="relative w-full max-w-lg rounded-2xl border border-gray-200 bg-white p-5 sm:p-6 shadow-2xl dark:border-gray-800 dark:bg-gray-900 space-y-4 max-h-[92vh] overflow-y-auto custom-scrollbar">
        {/* Header */}
        <div className="flex items-center justify-between pb-3 border-b border-gray-100 dark:border-gray-800">
          <div className="flex items-center gap-2.5">
            <div className="flex h-9 w-9 items-center justify-center rounded-xl bg-brand-500/10 text-brand-500">
              <Settings className="h-5 w-5" />
            </div>
            <div>
              <h3 className="font-bold text-sm sm:text-base text-gray-900 dark:text-white">
                Ubah Port — {account.vpn_username}
              </h3>
              <p className="text-[11px] text-gray-500 dark:text-gray-400">
                Atur tujuan port internal router atau server lokal Anda
              </p>
            </div>
          </div>
          <button
            type="button"
            onClick={onClose}
            className="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-800 dark:hover:text-gray-200 transition cursor-pointer"
          >
            <X className="h-5 w-5" />
          </button>
        </div>

        {/* Server & Account Info */}
        <div className="rounded-xl border border-gray-200 bg-gray-50/80 p-3 dark:border-gray-800 dark:bg-white/[0.02] flex items-center justify-between text-xs">
          <div className="flex items-center gap-2">
            <Server className="h-4 w-4 text-brand-500" />
            <span className="font-bold text-gray-900 dark:text-white">{account.server || "SERVER CLOUD"}</span>
          </div>
          <span className="font-mono text-gray-500 dark:text-gray-400">{account.host}</span>
        </div>

        {error && (
          <div className="flex items-start gap-2.5 rounded-xl border border-rose-200 bg-rose-50/80 p-3 text-xs text-rose-700 dark:border-rose-900/50 dark:bg-rose-950/40 dark:text-rose-300 font-medium">
            <AlertCircle className="h-4 w-4 shrink-0 mt-0.5 text-rose-500" />
            <span>{error}</span>
          </div>
        )}

        <form onSubmit={handleSubmit} className="space-y-3.5">
          <div className="space-y-2">
            <label className="block text-xs font-bold text-gray-700 dark:text-gray-300">
              Mapping Port Forwarding ({ports.length} Port)
            </label>

            <div className="space-y-2.5">
              {ports.map((p, idx) => (
                <div
                  key={idx}
                  className="p-3 rounded-xl border border-gray-200 bg-gray-50/50 dark:border-gray-800 dark:bg-gray-900/50 space-y-2"
                >
                  <div className="flex items-center justify-between text-[11px]">
                    <span className="font-bold text-gray-600 dark:text-gray-400">
                      Slot Port #{idx + 1}
                    </span>
                    <span className="text-[10px] font-mono font-bold text-brand-600 dark:text-brand-400">
                      Publik: :{p.public_port > 0 ? p.public_port : "Auto Remote"}
                    </span>
                  </div>

                  <div className="grid grid-cols-12 gap-2">
                    {/* Protocol Select */}
                    <div className="col-span-4">
                      <select
                        value={p.protocol}
                        onChange={(e) => handleProtocolChange(idx, e.target.value)}
                        className="h-9 w-full rounded-lg border border-gray-200 bg-white px-2.5 text-xs font-bold text-gray-800 focus:border-brand-500 focus:outline-hidden dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 cursor-pointer"
                      >
                        <option value="tcp">TCP</option>
                        <option value="udp">UDP</option>
                      </select>
                    </div>

                    {/* Internal Port Input */}
                    <div className="col-span-8">
                      <input
                        type="number"
                        value={p.dst_port}
                        onChange={(e) => handleInternalPortChange(idx, Number(e.target.value))}
                        placeholder="Contoh: 8291"
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
          </div>

          {/* Action Buttons */}
          <div className="flex items-center justify-end gap-2.5 pt-3 border-t border-gray-100 dark:border-gray-800">
            <button
              type="button"
              onClick={onClose}
              disabled={processing}
              className="h-10 px-4 rounded-xl border border-gray-200 bg-white text-xs font-bold text-gray-700 hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800 transition cursor-pointer"
            >
              Batal
            </button>
            <button
              type="submit"
              disabled={processing}
              className="h-10 px-5 rounded-xl bg-brand-500 hover:bg-brand-600 text-xs font-bold text-white shadow-theme-xs transition flex items-center justify-center gap-1.5 cursor-pointer disabled:opacity-60"
            >
              {processing && <Loader2 className="h-3.5 w-3.5 animate-spin" />}
              <span>{processing ? "Menyimpan..." : "Simpan Perubahan"}</span>
            </button>
          </div>
        </form>
      </div>
    </div>
  )
}

export default EditPortModal
