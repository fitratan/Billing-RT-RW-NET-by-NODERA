import React, { useState, useMemo } from "react"
import ToolsLayout, { RegisteredRouter } from "@/layouts/ToolsLayout"
import { Gauge } from "lucide-react"
import { cn } from "@/lib/utils"

export default function BurstQos({
  registeredRouters = [],
  company,
  appName,
}: {
  registeredRouters?: RegisteredRouter[]
  company?: any
  appName?: string
}) {
  const [rosVersion, setRosVersion] = useState<"v7" | "v6">("v7")
  const [targetSubnet, setTargetSubnet] = useState<string>("192.168.10.0/24")
  const [maxUpload, setMaxUpload] = useState<string>("10M")
  const [maxDownload, setMaxDownload] = useState<string>("20M")
  const [multiplier, setMultiplier] = useState<number>(1.5)
  const [duration, setDuration] = useState<number>(16)
  const [queueName, setQueueName] = useState<string>("BURST_CLIENT_QOS")

  const generatedScript = useMemo(() => {
    const upNum = parseInt(maxUpload) || 10
    const downNum = parseInt(maxDownload) || 20
    const burstUp = Math.round(upNum * multiplier) + "M"
    const burstDown = Math.round(downNum * multiplier) + "M"
    const threshUp = Math.round(upNum * 0.75) + "M"
    const threshDown = Math.round(downNum * 0.75) + "M"

    let lines: string[] = []

    lines.push(`# ================================================================`)
    lines.push(`# NODERA — SIMPLE QUEUE BURST QOS CALCULATOR`)
    lines.push(`# Target OS    : RouterOS ${rosVersion.toUpperCase()}`)
    lines.push(`# Target Subnet: ${targetSubnet} (${maxUpload}/${maxDownload} ➔ Burst: ${burstUp}/${burstDown})`)
    lines.push(`# Generated At : ${new Date().toLocaleString("id-ID")}`)
    lines.push(`# ================================================================`)
    lines.push(``)

    lines.push(`# --- Simple Queue Burst Multiplier Rule ---`)
    lines.push(`/queue simple add name="${queueName}" target="${targetSubnet}" max-limit=${maxUpload}/${maxDownload} burst-limit=${burstUp}/${burstDown} burst-threshold=${threshUp}/${threshDown} burst-time=${duration}s/${duration}s queue=pcq-upload-default/pcq-download-default priority=8/8 comment="Auto Burst QoS Anti-Lag"`)

    return lines.join("\n")
  }, [rosVersion, targetSubnet, maxUpload, maxDownload, multiplier, duration, queueName])

  return (
    <ToolsLayout
      currentToolId="burst_qos"
      title="Burst QoS &"
      titleGradient="Simple Queue Multiplier"
      subtitle="Kalkulasi otomatis burst-limit, burst-threshold, dan burst-time agar pelanggan merasakan loading website super cepat instan di awal request."
      badgeText="BURST QOS CALCULATOR"
      rosVersion={rosVersion}
      setRosVersion={setRosVersion}
      generatedScript={generatedScript}
      registeredRouters={registeredRouters}
      company={company}
      appName={appName}
    >
      <div className="space-y-4 text-xs">
        <div className="p-4 rounded-2xl bg-white/[0.02] border border-white/10 space-y-3">
          <div>
            <label className="block text-xs uppercase tracking-wider font-bold text-slate-300 mb-1.5">Nama Queue</label>
            <input
              type="text"
              value={queueName}
              onChange={(e) => setQueueName(e.target.value)}
              className="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2 text-slate-200 text-sm"
            />
          </div>

          <div>
            <label className="block text-xs uppercase tracking-wider font-bold text-slate-300 mb-1.5">Target IP Subnet / Client</label>
            <input
              type="text"
              value={targetSubnet}
              onChange={(e) => setTargetSubnet(e.target.value)}
              placeholder="192.168.10.0/24"
              className="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2 text-slate-200 font-mono text-sm"
            />
          </div>

          <div className="grid grid-cols-2 gap-2.5">
            <div>
              <label className="block text-xs uppercase tracking-wider font-bold text-slate-300 mb-1.5">Max Upload</label>
              <input
                type="text"
                value={maxUpload}
                onChange={(e) => setMaxUpload(e.target.value)}
                placeholder="10M"
                className="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2 text-slate-200 font-mono text-sm"
              />
            </div>
            <div>
              <label className="block text-xs uppercase tracking-wider font-bold text-slate-300 mb-1.5">Max Download</label>
              <input
                type="text"
                value={maxDownload}
                onChange={(e) => setMaxDownload(e.target.value)}
                placeholder="20M"
                className="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2 text-slate-200 font-mono text-sm"
              />
            </div>
          </div>

          <div>
            <label className="block text-xs uppercase tracking-wider font-bold text-slate-300 mb-2">
              Burst Boost Multiplier (+{Math.round((multiplier - 1) * 100)}% Speed Awal)
            </label>
            <div className="flex gap-2">
              {[1.25, 1.5, 1.75, 2.0].map((m) => (
                <button
                  key={m}
                  type="button"
                  onClick={() => setMultiplier(m)}
                  className={cn(
                    "flex-1 py-2 rounded-xl font-bold border transition-all text-xs",
                    multiplier === m
                      ? "bg-cyan-500 text-white border-cyan-400 shadow-md"
                      : "bg-white/5 border-white/10 text-slate-400 hover:text-white"
                  )}
                >
                  +{Math.round((m - 1) * 100)}%
                </button>
              ))}
            </div>
          </div>

          <div>
            <label className="block text-xs uppercase tracking-wider font-bold text-slate-300 mb-1.5">Durasi Burst Time (Detik)</label>
            <input
              type="number"
              value={duration}
              onChange={(e) => setDuration(parseInt(e.target.value) || 16)}
              className="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2 text-slate-200 font-mono text-sm"
            />
          </div>
        </div>
      </div>
    </ToolsLayout>
  )
}
