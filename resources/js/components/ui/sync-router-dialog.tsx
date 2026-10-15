import { useForm } from "@inertiajs/react"
import {
  RefreshCw,
  Router,
  X,
  Server,
  Layers,
  ShieldAlert,
  Check,
} from "lucide-react"
import { Label } from "@/components/ui/label"
import { Button } from "@/components/ui/button"
import { useState, useEffect, useRef } from "react"
import { SyncOverlay } from "@/components/ui/sync-overlay"
import { cn } from "@/lib/utils"

interface Props {
  open: boolean
  onClose: () => void
  routers: { id: number; name: string }[]
  url?: string
  title?: string
}

export function SyncRouterDialog({
  open,
  onClose,
  routers = [],
  url = "/admin/billing/customers/sync",
  title = "Sinkronisasi MikroTik",
}: Props) {
  const [routerId, setRouterId] = useState("")
  const [isolationDate, setIsolationDate] = useState("20")
  const [syncType, setSyncType] = useState("all")
  const [isSyncing, setIsSyncing] = useState(false)
  const [progress, setProgress] = useState(0)
  const intervalRef = useRef<any>(null)

  const form = useForm({ router_id: routerId, sync_type: syncType, isolation_date: 20 })

  // Select first router by default if available
  useEffect(() => {
    if (routers.length > 0 && !routerId) {
      setRouterId(String(routers[0].id))
      form.setData("router_id", String(routers[0].id))
    }
  }, [routers, routerId])

  const startProgressAnimation = () => {
    setIsSyncing(true)
    setProgress(5)

    if (intervalRef.current) clearInterval(intervalRef.current)

    intervalRef.current = setInterval(() => {
      setProgress((prev) => {
        if (prev >= 92) {
          clearInterval(intervalRef.current)
          return 92
        }
        const next = prev + Math.floor(Math.random() * 8) + 4
        return Math.min(next, 92)
      })
    }, 280)
  }

  const completeProgressAnimation = (callback: () => void) => {
    if (intervalRef.current) clearInterval(intervalRef.current)
    setProgress(100)
    setTimeout(() => {
      setIsSyncing(false)
      setProgress(0)
      callback()
    }, 600)
  }

  const failProgressAnimation = () => {
    if (intervalRef.current) clearInterval(intervalRef.current)
    setIsSyncing(false)
    setProgress(0)
  }

  useEffect(() => {
    return () => {
      if (intervalRef.current) clearInterval(intervalRef.current)
    }
  }, [])

  const submit = (e: React.FormEvent) => {
    e.preventDefault()
    startProgressAnimation()

    form.post(url, {
      preserveScroll: true,
      onSuccess: () => {
        completeProgressAnimation(() => onClose())
      },
      onError: () => {
        failProgressAnimation()
      },
    })
  }

  if (!open && !isSyncing) return null

  return (
    <>
      <SyncOverlay show={isSyncing} progress={progress} title={title} />

      {open && !isSyncing && (
        <div className="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-0 sm:p-4 animate-in fade-in duration-200">
          <div className="fixed inset-0 bg-black/60 dark:bg-black/80 backdrop-blur-xs" onClick={onClose} />

          <div className="relative flex max-h-[90vh] w-full max-w-md flex-col overflow-hidden rounded-t-3xl sm:rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 text-gray-900 dark:text-white shadow-2xl animate-in slide-in-from-bottom-4 duration-200">
            {/* Modal Header */}
            <div className="flex items-center justify-between border-b border-gray-100 dark:border-gray-800 px-5 py-4 shrink-0 bg-gray-50/75 dark:bg-gray-900/50">
              <div className="flex items-center gap-2.5">
                <div className="flex h-9 w-9 items-center justify-center rounded-xl bg-brand-50 text-brand-600 dark:bg-brand-950 dark:text-brand-400 border border-brand-200 dark:border-brand-800">
                  <Router className="h-4.5 w-4.5" />
                </div>
                <div>
                  <h3 className="font-display text-sm sm:text-base font-bold text-gray-900 dark:text-white">
                    {title}
                  </h3>
                  <p className="text-[11px] text-gray-500 dark:text-gray-400">Sinkronkan database pelanggan &amp; secret dari router</p>
                </div>
              </div>
              <button
                type="button"
                onClick={onClose}
                className="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-gray-800 dark:hover:text-white transition-all cursor-pointer"
              >
                <X className="h-4 w-4" />
              </button>
            </div>

            {/* Modal Content Body */}
            <form onSubmit={submit} className="flex min-h-0 flex-1 flex-col overflow-hidden">
              <div className="flex-1 overflow-y-auto p-5 space-y-4">
                {routers.length === 0 ? (
                  <div className="rounded-2xl border border-dashed border-gray-200 dark:border-gray-800 bg-gray-50/75 dark:bg-gray-900/50 p-5 text-center">
                    <p className="text-xs text-gray-500 dark:text-gray-400">Belum ada router MikroTik terdaftar. Silakan tambahkan router terlebih dahulu di menu Router.</p>
                  </div>
                ) : (
                  <>
                    {/* Router Selector */}
                    <div className="space-y-1.5">
                      <Label className="text-xs font-semibold text-gray-700 dark:text-gray-300">Pilih Router Gateway MikroTik *</Label>
                      <select
                        value={routerId}
                        onChange={(e) => {
                          setRouterId(e.target.value)
                          form.setData("router_id", e.target.value)
                        }}
                        className="w-full h-10 rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-50/75 dark:bg-gray-900/50 px-3 text-xs font-bold text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-500 cursor-pointer"
                        required
                      >
                        <option value="" className="bg-white text-gray-900 dark:bg-gray-900 dark:text-white">Pilih router...</option>
                        {routers.map((r) => (
                          <option key={r.id} value={r.id} className="bg-white text-gray-900 dark:bg-gray-900 dark:text-white">
                            {r.name}
                          </option>
                        ))}
                      </select>
                    </div>

                    {/* Tanggal Jatuh Tempo / Isolir Pelanggan Baru */}
                    <div className="space-y-1.5">
                      <div className="flex items-center justify-between">
                        <Label className="text-xs font-semibold text-gray-700 dark:text-gray-300">
                          Tanggal Jatuh Tempo / Isolir (1 - 28) *
                        </Label>
                        <span className="text-[10px] text-brand-600 dark:text-brand-400 font-mono font-bold">
                          Tgl {isolationDate || "1"} tiap bulan
                        </span>
                      </div>
                      <input
                        type="number"
                        min={1}
                        max={28}
                        value={isolationDate}
                        onChange={(e) => {
                          const val = e.target.value
                          setIsolationDate(val)
                          const num = parseInt(val, 10)
                          if (!isNaN(num) && num >= 1 && num <= 28) {
                            form.setData("isolation_date", num)
                          }
                        }}
                        onBlur={() => {
                          if (!isolationDate || parseInt(isolationDate, 10) < 1) {
                            setIsolationDate("1")
                            form.setData("isolation_date", 1)
                          } else if (parseInt(isolationDate, 10) > 28) {
                            setIsolationDate("28")
                            form.setData("isolation_date", 28)
                          }
                        }}
                        placeholder="20"
                        className="w-full h-10 rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-50/75 dark:bg-gray-900/50 px-3 text-xs font-bold text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-500"
                        required
                      />
                      <p className="text-[11px] text-gray-500 dark:text-gray-400">
                        Tanggal jatuh tempo otomatis untuk data pelanggan yang pertama kali diimpor dari MikroTik.
                      </p>
                    </div>

                    {/* Data Type Options */}
                    <div className="space-y-1.5">
                      <Label className="text-xs font-semibold text-gray-700 dark:text-gray-300">Tipe Data yang Disinkronkan *</Label>
                      <div className="grid grid-cols-1 gap-2 pt-1">
                        {[
                          {
                            id: "all",
                            label: "Semua Data (PPPoE + Static IP / ARP)",
                            desc: "PPPoE Secrets dan Static IP / ARP Table",
                          },
                          {
                            id: "pppoe",
                            label: "PPPoE Secrets",
                            desc: "Data akun /ppp/secret dari MikroTik",
                          },
                          {
                            id: "arp",
                            label: "Static IP & ARP Table",
                            desc: "Data IP binding /ip/arp dari MikroTik",
                          },
                        ].map((item) => (
                          <label
                            key={item.id}
                            onClick={() => {
                              setSyncType(item.id)
                              form.setData("sync_type", item.id)
                            }}
                            className={cn(
                              "flex items-start gap-3 p-3 rounded-xl border cursor-pointer transition-all select-none",
                              syncType === item.id
                                ? "border-brand-500 bg-brand-50 dark:bg-brand-500/10 text-brand-600 dark:text-brand-400"
                                : "border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 text-gray-700 dark:text-gray-300 hover:border-gray-300 dark:hover:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800/60"
                            )}
                          >
                            <input
                              type="radio"
                              name="sync_type"
                              value={item.id}
                              checked={syncType === item.id}
                              onChange={() => {}}
                              className="mt-0.5 h-4 w-4 border-gray-300 text-brand-600 focus:ring-brand-500"
                            />
                            <div className="min-w-0 flex-1">
                              <span className="text-xs font-bold text-gray-900 dark:text-white block">{item.label}</span>
                              <span className="text-[11px] text-gray-500 dark:text-gray-400 block mt-0.5">{item.desc}</span>
                            </div>
                          </label>
                        ))}
                      </div>
                    </div>
                  </>
                )}
              </div>

              {/* Action Buttons */}
              <div className="border-t border-gray-100 dark:border-gray-800 p-4 bg-gray-50/75 dark:bg-gray-900/50 shrink-0 flex items-center gap-2">
                <button
                  type="button"
                  className="flex-1 rounded-xl h-10 text-xs font-bold border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 hover:text-gray-900 dark:hover:text-white transition-all cursor-pointer"
                  onClick={onClose}
                >
                  Batal
                </button>
                <button
                  type="submit"
                  disabled={form.processing || routers.length === 0 || !routerId}
                  className="flex-1 rounded-xl h-10 text-xs font-bold bg-brand-500 hover:bg-brand-600 text-white transition-all flex items-center justify-center gap-1.5 disabled:opacity-50 shadow-xs cursor-pointer"
                >
                  <RefreshCw className="h-4 w-4" /> Mulai Sinkronisasi
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </>
  )
}
