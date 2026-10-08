import React, { useState, useMemo } from "react"
import ToolsLayout, { RegisteredRouter } from "@/layouts/ToolsLayout"
import { Tv, CheckSquare, Square } from "lucide-react"
import { cn } from "@/lib/utils"

const STREAM_PRESETS = [
  { name: "YouTube & Google Video CDN", domain: "googlevideo.com" },
  { name: "Netflix Video Stream", domain: "netflix.com" },
  { name: "TikTok Video CDN", domain: "tiktokcdn.com" },
  { name: "Facebook & Instagram Video", domain: "fbcdn.net" },
  { name: "Akamai CDN Video", domain: "akamaized.net" },
  { name: "Twitch Live Stream", domain: "ttvnw.net" },
]

export default function StreamTraffic({
  registeredRouters = [],
  company,
  appName,
}: {
  registeredRouters?: RegisteredRouter[]
  company?: any
  appName?: string
}) {
  const [rosVersion, setRosVersion] = useState<"v7" | "v6">("v7")
  const [targetWan, setTargetWan] = useState<string>("ISP2")
  const [targetGateway, setTargetGateway] = useState<string>("192.168.2.1")
  const [selectedStreams, setSelectedStreams] = useState<string[]>(STREAM_PRESETS.map((s) => s.name))

  const toggleStream = (name: string) => {
    setSelectedStreams((prev) =>
      prev.includes(name) ? prev.filter((n) => n !== name) : [...prev, name]
    )
  }

  const generatedScript = useMemo(() => {
    const isV7 = rosVersion === "v7"
    let lines: string[] = []

    lines.push(`# ================================================================`)
    lines.push(`# NODERA — PISAH TRAFFIC STREAMING & VIDEO CDN`)
    lines.push(`# Target OS    : RouterOS ${isV7 ? "v7.x (Routing Table)" : "v6.x (Routing Mark)"}`)
    lines.push(`# Jalur Stream : ${targetWan} (Gateway: ${targetGateway})`)
    lines.push(`# Generated At : ${new Date().toLocaleString("id-ID")}`)
    lines.push(`# ================================================================`)
    lines.push(``)

    lines.push(`# --- 1. Address List Jaringan Lokal ---`)
    lines.push(`/ip firewall address-list add address=10.0.0.0/8 list=LOCAL_LAN comment="Private LAN"`)
    lines.push(`/ip firewall address-list add address=172.16.0.0/12 list=LOCAL_LAN comment="Private LAN"`)
    lines.push(`/ip firewall address-list add address=192.168.0.0/16 list=LOCAL_LAN comment="Private LAN"`)
    lines.push(``)

    if (isV7) {
      lines.push(`# --- 2. Routing Table ROS v7 ---`)
      lines.push(`/routing table add name=to_STREAM fib comment="Routing Table Streaming CDN"`)
      lines.push(``)
    }

    lines.push(`# --- 3. Mangle Connection Mark per CDN Content ---`)
    STREAM_PRESETS.filter((s) => selectedStreams.includes(s.name)).forEach((stream) => {
      lines.push(`/ip firewall mangle add chain=prerouting dst-address-list=!LOCAL_LAN protocol=tcp dst-port=80,443 content="${stream.domain}" action=mark-connection new-connection-mark=STREAM_CONN passthrough=yes comment="CDN: ${stream.name}"`)
    })

    lines.push(``)
    lines.push(`# --- 4. Mangle Mark Routing ---`)
    lines.push(`/ip firewall mangle add chain=prerouting connection-mark=STREAM_CONN action=mark-routing new-routing-mark=to_STREAM passthrough=no comment="Route Streaming Traffic"`)
    lines.push(``)

    lines.push(`# --- 5. IP Route Khusus Streaming ---`)
    if (isV7) {
      lines.push(`/ip route add dst-address=0.0.0.0/0 gateway=${targetGateway} check-gateway=ping routing-table=to_STREAM distance=1 comment="Jalur Khusus Streaming (${targetWan})"`)
    } else {
      lines.push(`/ip route add dst-address=0.0.0.0/0 gateway=${targetGateway} check-gateway=ping routing-mark=to_STREAM distance=1 comment="Jalur Khusus Streaming (${targetWan})"`)
    }

    return lines.join("\n")
  }, [rosVersion, targetWan, targetGateway, selectedStreams])

  return (
    <ToolsLayout
      currentToolId="stream"
      title="Pisah Traffic"
      titleGradient="Streaming & Video CDN"
      subtitle="Alirkan beban bandwidth besar seperti YouTube, Netflix, TikTok, dan video streaming ke ISP khusus agar koneksi browsing & game pelanggan tetap lancar."
      badgeText="PBR VIDEO CDN STUDIO"
      rosVersion={rosVersion}
      setRosVersion={setRosVersion}
      generatedScript={generatedScript}
      registeredRouters={registeredRouters}
      company={company}
      appName={appName}
    >
      <div className="space-y-4 text-xs">
        {/* WAN Selector */}
        <div className="p-4 rounded-2xl bg-white/[0.02] border border-white/10 space-y-3">
          <div>
            <label className="block text-xs uppercase tracking-wider font-bold text-slate-300 mb-1.5">Alokasi Jalur / ISP Khusus Streaming</label>
            <input
              type="text"
              value={targetWan}
              onChange={(e) => setTargetWan(e.target.value)}
              placeholder="ISP2"
              className="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2 text-slate-200 focus:outline-none focus:border-cyan-500/50 text-sm"
            />
          </div>

          <div>
            <label className="block text-xs uppercase tracking-wider font-bold text-slate-300 mb-1.5">IP Gateway ISP Streaming</label>
            <input
              type="text"
              value={targetGateway}
              onChange={(e) => setTargetGateway(e.target.value)}
              placeholder="192.168.2.1"
              className="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2 text-slate-200 focus:outline-none focus:border-cyan-500/50 font-mono text-sm"
            />
          </div>
        </div>

        {/* Video CDN Checklist */}
        <div className="space-y-2">
          <label className="block text-xs uppercase tracking-wider font-bold text-slate-300">Pilih Platform Streaming yang Dipisahkan:</label>
          <div className="space-y-2 max-h-[320px] overflow-y-auto pr-1 scrollbar-thin">
            {STREAM_PRESETS.map((stream) => {
              const isChecked = selectedStreams.includes(stream.name)
              return (
                <button
                  key={stream.name}
                  type="button"
                  onClick={() => toggleStream(stream.name)}
                  className={cn(
                    "w-full flex items-center justify-between p-3.5 rounded-xl border text-left transition-all",
                    isChecked
                      ? "bg-cyan-500/15 border-cyan-500/40 text-cyan-300 shadow-sm"
                      : "bg-white/[0.02] border-white/10 text-slate-400 hover:text-white"
                  )}
                >
                  <div className="flex items-center gap-3">
                    {isChecked ? <CheckSquare className="w-4 h-4 text-cyan-400" /> : <Square className="w-4 h-4 text-slate-600" />}
                    <span className="font-bold text-xs">{stream.name}</span>
                  </div>
                  <span className="text-[10px] font-mono text-slate-500 truncate max-w-[140px]">
                    {stream.domain}
                  </span>
                </button>
              )
            })}
          </div>
        </div>
      </div>
    </ToolsLayout>
  )
}
