import React, { useState, useMemo } from "react"
import ToolsLayout, { RegisteredRouter } from "@/layouts/ToolsLayout"
import { ArrowDown } from "lucide-react"

export default function PortForward({
  registeredRouters = [],
  company,
  appName,
}: {
  registeredRouters?: RegisteredRouter[]
  company?: any
  appName?: string
}) {
  const [rosVersion, setRosVersion] = useState<"v7" | "v6">("v7")
  const [inWan, setInWan] = useState<string>("ether1-WAN")
  const [protocol, setProtocol] = useState<"tcp" | "udp">("tcp")
  const [dstPort, setDstPort] = useState<string>("8080")
  const [toIp, setToIp] = useState<string>("192.168.10.50")
  const [toPort, setToPort] = useState<string>("80")
  const [comment, setComment] = useState<string>("Forward CCTV Web Server")

  const generatedScript = useMemo(() => {
    let lines: string[] = []

    lines.push(`# ================================================================`)
    lines.push(`# NODERA — DST-NAT PORT FORWARDING`)
    lines.push(`# Target OS    : RouterOS ${rosVersion.toUpperCase()}`)
    lines.push(`# Target Server: ${toIp}:${toPort} (${protocol.toUpperCase()})`)
    lines.push(`# Generated At : ${new Date().toLocaleString("id-ID")}`)
    lines.push(`# ================================================================`)
    lines.push(``)

    lines.push(`# --- 1. IP Firewall NAT DST-NAT ---`)
    lines.push(`/ip firewall nat add chain=dstnat in-interface=${inWan} protocol=${protocol} dst-port=${dstPort} action=dst-nat to-addresses=${toIp} to-ports=${toPort} comment="${comment}"`)
    lines.push(``)

    lines.push(`# --- 2. Firewall Filter Allow Forward Target ---`)
    lines.push(`/ip firewall filter add chain=forward protocol=${protocol} dst-address=${toIp} dst-port=${toPort} action=accept comment="Allow Forward: ${comment}"`)

    return lines.join("\n")
  }, [rosVersion, inWan, protocol, dstPort, toIp, toPort, comment])

  return (
    <ToolsLayout
      currentToolId="port_forward"
      title="DST-NAT"
      titleGradient="Port Forwarding Studio"
      subtitle="Buka dan teruskan port publik router ke IP lokal server lokal, DVR / CCTV, Web Server, Billing Server, atau Remote Desktop."
      badgeText="NAT PORT FORWARDING STUDIO"
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
            <label className="block text-xs uppercase tracking-wider font-bold text-slate-300 mb-1.5">Interface WAN (Internet Publik)</label>
            <input
              type="text"
              value={inWan}
              onChange={(e) => setInWan(e.target.value)}
              placeholder="ether1-WAN"
              className="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2 text-slate-200 text-sm"
            />
          </div>

          <div className="grid grid-cols-2 gap-2.5">
            <div>
              <label className="block text-xs uppercase tracking-wider font-bold text-slate-300 mb-1.5">Protokol</label>
              <select
                value={protocol}
                onChange={(e) => setProtocol(e.target.value as any)}
                className="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2 text-slate-200 text-sm"
              >
                <option value="tcp" className="bg-[#0A1218] text-white">TCP</option>
                <option value="udp" className="bg-[#0A1218] text-white">UDP</option>
              </select>
            </div>
            <div>
              <label className="block text-xs uppercase tracking-wider font-bold text-slate-300 mb-1.5">Port Publik (Dst Port)</label>
              <input
                type="text"
                value={dstPort}
                onChange={(e) => setDstPort(e.target.value)}
                placeholder="8080"
                className="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2 text-slate-200 font-mono text-sm"
              />
            </div>
          </div>

          <div className="grid grid-cols-2 gap-2.5">
            <div>
              <label className="block text-xs uppercase tracking-wider font-bold text-slate-300 mb-1.5">IP Lokal Tujuan</label>
              <input
                type="text"
                value={toIp}
                onChange={(e) => setToIp(e.target.value)}
                placeholder="192.168.10.50"
                className="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2 text-slate-200 font-mono text-sm"
              />
            </div>
            <div>
              <label className="block text-xs uppercase tracking-wider font-bold text-slate-300 mb-1.5">Port Lokal Tujuan</label>
              <input
                type="text"
                value={toPort}
                onChange={(e) => setToPort(e.target.value)}
                placeholder="80"
                className="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2 text-slate-200 font-mono text-sm"
              />
            </div>
          </div>

          <div>
            <label className="block text-xs uppercase tracking-wider font-bold text-slate-300 mb-1.5">Keterangan / Comment</label>
            <input
              type="text"
              value={comment}
              onChange={(e) => setComment(e.target.value)}
              placeholder="Forward CCTV Web Server"
              className="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2 text-slate-200 text-sm"
            />
          </div>
        </div>
      </div>
    </ToolsLayout>
  )
}
