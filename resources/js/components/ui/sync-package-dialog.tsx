import { useForm } from "@inertiajs/react"
import {
  RefreshCw,
  Router,
  X,
  Server,
  Layers,
  ShieldAlert,
  Check,
  Cpu,
} from "lucide-react"
import { Label } from "@/components/ui/label"
import { useState, useEffect, useRef } from "react"
import { SyncOverlay } from "@/components/ui/sync-overlay"
import { cn } from "@/lib/utils"

interface Props {
  open: boolean
  onClose: () => void
  routers: { id: number; name: string }[]
}

export function SyncPackageDialog({
  open,
  onClose,
  routers = [],
}: Props) {
  const [routerId, setRouterId] = useState("")
  const [syncType, setSyncType] = useState("pppoe")
  const [isSyncing, setIsSyncing] = useState(false)
  const [progress, setProgress] = useState(0)
  const intervalRef = useRef<any>(null)

  const form = useForm({ router_id: routerId, sync_type: syncType })

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
    }, 250)
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

    form.post("/admin/billing/packages/sync", {
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
      <SyncOverlay show={isSyncing} progress={progress} title="Sinkronisasi Profil Paket MikroTik" />

      {open && !isSyncing && (
        <div className="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-0 sm:p-4 animate-in fade-in duration-200">
          <div className="fixed inset-0 bg-black/80 backdrop-blur-md" onClick={onClose} />

          <div className="relative flex max-h-[90vh] w-full max-w-md flex-col overflow-hidden rounded-t-3xl sm:rounded-2xl border border-[#2B3544] bg-[#161B22] shadow-2xl animate-in slide-in-from-bottom-4 duration-200">
            {/* Modal Header */}
            <div className="flex items-center justify-between border-b border-white/10 px-5 py-4 shrink-0 bg-[#161B22]">
              <div className="flex items-center gap-2.5">
                <div className="flex h-9 w-9 items-center justify-center rounded-xl bg-[#00C2FF]/15 text-[#00C2FF] border border-[#00C2FF]/30">
                  <Layers className="h-4.5 w-4.5" />
                </div>
                <div>
                  <h3 className="font-display text-sm sm:text-base font-bold text-white">
                    Tarik Profil Paket dari Router
                  </h3>
                  <p className="text-[11px] text-slate-400">Sinkronkan PPPoE atau Simple Queue ARP ke daftar paket</p>
                </div>
              </div>
              <button
                type="button"
                onClick={onClose}
                className="rounded-lg p-1.5 text-slate-400 hover:bg-[#1D242E] hover:text-white transition-all"
              >
                <X className="h-4 w-4" />
              </button>
            </div>

            {/* Modal Content Body */}
            <form onSubmit={submit} className="flex min-h-0 flex-1 flex-col overflow-hidden">
              <div className="flex-1 overflow-y-auto p-5 space-y-4">
                {routers.length === 0 ? (
                  <div className="rounded-2xl border border-dashed border-[#2B3544] bg-[#10141A] p-5 text-center">
                    <p className="text-xs text-slate-400">Belum ada router MikroTik terdaftar. Silakan daftarkan router terlebih dahulu.</p>
                  </div>
                ) : (
                  <>
                    {/* Router Selector */}
                    <div className="space-y-1.5">
                      <Label className="text-xs font-semibold text-slate-300">Pilih Router MikroTik *</Label>
                      <select
                        value={routerId}
                        onChange={(e) => {
                          setRouterId(e.target.value)
                          form.setData("router_id", e.target.value)
                        }}
                        className="w-full h-10 rounded-xl border border-[#2B3544] bg-[#10141A] px-3 text-xs font-bold text-white focus:outline-none focus:ring-2 focus:ring-[#00C2FF]"
                        required
                      >
                        <option value="" className="bg-[#161B22] text-white">Pilih router...</option>
                        {routers.map((r) => (
                          <option key={r.id} value={r.id} className="bg-[#161B22] text-white">
                            {r.name}
                          </option>
                        ))}
                      </select>
                    </div>

                    {/* Data Type Options */}
                    <div className="space-y-1.5">
                      <Label className="text-xs font-semibold text-slate-300">Sumber Profil yang Disinkronkan *</Label>
                      <div className="grid grid-cols-1 gap-2 pt-1">
                        {[
                          {
                            id: "all",
                            label: "Semua Profil (PPPoE + Simple Queue)",
                            desc: "Sinkronkan profile PPPoE dan kelompokkan Simple Queue dari router",
                          },
                          {
                            id: "pppoe",
                            label: "PPPoE Profiles (/ppp/profile)",
                            desc: "Tarik semua profile PPP di MikroTik menjadi daftar paket langganan",
                          },
                          {
                            id: "queue",
                            label: "ARP / Static IP (Simple Queue per Kecepatan)",
                            desc: "Tarik limit kecepatan Simple Queue pelanggan ARP/Static IP dan kelompokkan otomatis menjadi paket (cth: 10M/10M, 20M/20M)",
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
                                ? "border-[#00C2FF] bg-[#00C2FF]/10 text-white"
                                : "border-[#2B3544] bg-[#10141A] text-slate-400 hover:border-slate-600 hover:bg-[#1D242E]"
                            )}
                          >
                            <input
                              type="radio"
                              name="sync_type"
                              value={item.id}
                              checked={syncType === item.id}
                              onChange={() => {}}
                              className="mt-0.5 h-4 w-4 border-[#2B3544] text-[#00C2FF] focus:ring-[#00C2FF]"
                            />
                            <div className="min-w-0 flex-1">
                              <span className="text-xs font-bold text-white block">{item.label}</span>
                              <span className="text-[11px] text-slate-400 block mt-0.5">{item.desc}</span>
                            </div>
                          </label>
                        ))}
                      </div>
                    </div>

                    <div className="rounded-xl border border-sky-500/20 bg-sky-500/10 p-3 text-[11px] text-sky-300 leading-relaxed">
                      <strong>Catatan:</strong> Profil yang ditarik akan tersimpan otomatis sebagai paket di NODERA. Anda dapat mengatur nominal harga bulanan dan profil isolir setelah proses sinkronisasi selesai.
                    </div>
                  </>
                )}
              </div>

              {/* Action Buttons */}
              <div className="border-t border-white/10 p-4 bg-[#161B22] shrink-0 flex items-center gap-2">
                <button
                  type="button"
                  className="flex-1 rounded-xl h-10 text-xs font-bold border border-white/10 bg-[#1D242E] text-slate-300 hover:bg-[#26303D] hover:text-white transition-all"
                  onClick={onClose}
                >
                  Batal
                </button>
                <button
                  type="submit"
                  disabled={form.processing || routers.length === 0 || !routerId}
                  className="flex-1 rounded-xl h-10 text-xs font-black bg-gradient-to-r from-[#00C2FF] to-[#0073C6] text-white hover:opacity-90 transition-all flex items-center justify-center gap-1.5 disabled:opacity-50"
                >
                  <RefreshCw className="h-4 w-4" /> Tarik Profil Paket
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </>
  )
}
