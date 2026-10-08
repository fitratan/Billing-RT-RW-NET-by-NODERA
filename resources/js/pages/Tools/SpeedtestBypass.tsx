import React, { useState, useMemo } from "react"
import ToolsLayout, { RegisteredRouter } from "@/layouts/ToolsLayout"
import { Gauge } from "lucide-react"
import { cn } from "@/lib/utils"

export default function SpeedtestBypass({
  registeredRouters = [],
  company,
  appName,
}: {
  registeredRouters?: RegisteredRouter[]
  company?: any
  appName?: string
}) {
  const [rosVersion, setRosVersion] = useState<"v7" | "v6">("v7")
  const [priorityLevel, setPriorityLevel] = useState<number>(7)
  const [includeFastCom, setIncludeFastCom] = useState<boolean>(true)
  const [includeNperf, setIncludeNperf] = useState<boolean>(true)

  const generatedScript = useMemo(() => {
    let lines: string[] = []

    lines.push(`# ================================================================`)
    lines.push(`# NODERA — BYPASS SPEEDTEST & HIGH PRIORITY QOS`)
    lines.push(`# Target OS    : RouterOS ${rosVersion.toUpperCase()}`)
    lines.push(`# Priority     : Level ${priorityLevel} (Tinggi)`)
    lines.push(`# Generated At : ${new Date().toLocaleString("id-ID")}`)
    lines.push(`# ================================================================`)
    lines.push(``)

    lines.push(`# --- 1. Address List Server Speedtest ---`)
    lines.push(`/ip firewall address-list add address=speedtest.net list=SPEEDTEST_SERVERS comment="Ookla Speedtest"`)
    lines.push(`/ip firewall address-list add address=fast.com list=SPEEDTEST_SERVERS comment="Fast.com Netflix"`)
    lines.push(``)

    lines.push(`# --- 2. Mangle Mark Connection Port 8080 & 5060 (Speedtest TCP/UDP) ---`)
    lines.push(`/ip firewall mangle add chain=prerouting protocol=tcp dst-port=8080,5060 action=mark-connection new-connection-mark=SPEEDTEST_CONN passthrough=yes comment="Speedtest Port Direct"`)
    lines.push(`/ip firewall mangle add chain=prerouting protocol=tcp dst-address-list=SPEEDTEST_SERVERS action=mark-connection new-connection-mark=SPEEDTEST_CONN passthrough=yes comment="Speedtest Address List"`)

    if (includeFastCom) {
      lines.push(`/ip firewall mangle add chain=prerouting protocol=tcp dst-port=80,443 content="fast.com" action=mark-connection new-connection-mark=SPEEDTEST_CONN passthrough=yes comment="Fast.com"`)
    }

    if (includeNperf) {
      lines.push(`/ip firewall mangle add chain=prerouting protocol=tcp dst-port=8080,8043 content="nperf.com" action=mark-connection new-connection-mark=SPEEDTEST_CONN passthrough=yes comment="NPerf Speedtest"`)
    }

    lines.push(``)
    lines.push(`# --- 3. Set High Packet Priority Level ${priorityLevel} ---`)
    lines.push(`/ip firewall mangle add chain=prerouting connection-mark=SPEEDTEST_CONN action=set-priority new-priority=${priorityLevel} passthrough=yes comment="Prioritas Tinggi Speedtest Level ${priorityLevel}"`)

    return lines.join("\n")
  }, [rosVersion, priorityLevel, includeFastCom, includeNperf])

  return (
    <ToolsLayout
      currentToolId="speedtest"
      title="Bypass Speedtest"
      titleGradient="(Ookla & Fast.com)"
      subtitle="Bypass limitasi queue/QoS saat pengujian kecepatan oleh pelanggan sehingga jarum speedtest mencapai bandwidth penuh tanpa terhambat."
      badgeText="SPEEDTEST MAX-PRIORITY STUDIO"
      rosVersion={rosVersion}
      setRosVersion={setRosVersion}
      generatedScript={generatedScript}
      registeredRouters={registeredRouters}
      company={company}
      appName={appName}
    >
      <div className="space-y-4 text-xs">
        <div className="p-4 rounded-2xl bg-cyan-500/10 border border-cyan-500/20 space-y-2">
          <div className="flex items-center gap-2 text-cyan-400 font-bold text-sm">
            <Gauge className="w-4 h-4" />
            <span>Optimalisasi Pengujian Kecepatan</span>
          </div>
          <p className="text-slate-300 leading-relaxed text-xs">
            Script ini mendeteksi koneksi pengujian kecepatan (port 8080/5060, domain Speedtest & Fast.com) dan menandai prioritas paket level tertinggi sehingga pengujian pelanggan selalu optimal.
          </p>
        </div>

        <div className="p-4 rounded-2xl bg-white/[0.02] border border-white/10 space-y-4">
          <div>
            <label className="block text-xs uppercase tracking-wider font-bold text-slate-300 mb-2">Tingkat Prioritas Paket (1-8, Rekomendasi 7)</label>
            <div className="flex gap-2">
              {[5, 6, 7, 8].map((lvl) => (
                <button
                  key={lvl}
                  type="button"
                  onClick={() => setPriorityLevel(lvl)}
                  className={cn(
                    "flex-1 py-2.5 rounded-xl font-bold border transition-all text-xs",
                    priorityLevel === lvl
                      ? "bg-cyan-500 text-white border-cyan-400 shadow-md"
                      : "bg-white/5 border-white/10 text-slate-400 hover:text-white"
                  )}
                >
                  Priority {lvl} {lvl === 7 && "★"}
                </button>
              ))}
            </div>
          </div>

          <div className="space-y-2 pt-3 border-t border-white/10">
            <label className="flex items-center gap-3 cursor-pointer p-3 rounded-xl bg-white/[0.02] border border-white/5 hover:border-cyan-500/30 transition-all">
              <input
                type="checkbox"
                checked={includeFastCom}
                onChange={(e) => setIncludeFastCom(e.target.checked)}
                className="rounded text-cyan-500 bg-slate-800 focus:ring-0"
              />
              <span className="text-slate-300 font-medium">Sertakan Bypass Netflix Fast.com</span>
            </label>
            <label className="flex items-center gap-3 cursor-pointer p-3 rounded-xl bg-white/[0.02] border border-white/5 hover:border-cyan-500/30 transition-all">
              <input
                type="checkbox"
                checked={includeNperf}
                onChange={(e) => setIncludeNperf(e.target.checked)}
                className="rounded text-cyan-500 bg-slate-800 focus:ring-0"
              />
              <span className="text-slate-300 font-medium">Sertakan Bypass NPerf Speedtest</span>
            </label>
          </div>
        </div>
      </div>
    </ToolsLayout>
  )
}
