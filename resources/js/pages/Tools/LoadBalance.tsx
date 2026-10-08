import React, { useState, useMemo } from "react"
import ToolsLayout, { RegisteredRouter } from "@/layouts/ToolsLayout"
import {
  Plus,
  Trash2,
  Radio,
  Sliders,
  Shield,
  AlertTriangle,
  CheckCircle2,
  Info,
} from "lucide-react"
import { cn } from "@/lib/utils"

interface IspConfig {
  id: string
  name: string
  interfaceName: string
  gateway: string
  weight: number
}

interface LoadBalanceProps {
  registeredRouters?: RegisteredRouter[]
  company?: any
  appName?: string
  isAuthenticated?: boolean
}

export default function LoadBalance({
  registeredRouters = [],
  company,
  appName,
}: LoadBalanceProps) {
  const [rosVersion, setRosVersion] = useState<"v7" | "v6">("v6")
  const [lanInterface, setLanInterface] = useState<string>("bridge-LAN")
  const [lanType, setLanType] = useState<"interface" | "list">("interface")
  const [pccClassifier, setPccClassifier] = useState<string>("both-addresses")
  const [localSubnets, setLocalSubnets] = useState<string>("192.168.0.0/16, 10.0.0.0/8, 172.16.0.0/12")
  const [disableFasttrack, setDisableFasttrack] = useState<boolean>(true)
  const [enableDnsConfig, setEnableDnsConfig] = useState<boolean>(true)
  const [enableMssClamping, setEnableMssClamping] = useState<boolean>(true)

  const [isps, setIsps] = useState<IspConfig[]>([
    { id: "1", name: "ISP 1 (Utama)", interfaceName: "ether1-ISP1", gateway: "192.168.1.1", weight: 1 },
    { id: "2", name: "ISP 2 (Cadangan)", interfaceName: "ether2-ISP2", gateway: "192.168.2.1", weight: 1 },
  ])

  const handleAddIsp = () => {
    const nextNum = isps.length + 1
    setIsps((prev) => [
      ...prev,
      {
        id: Date.now().toString(),
        name: `ISP ${nextNum}`,
        interfaceName: `ether${nextNum}-ISP${nextNum}`,
        gateway: `192.168.${nextNum}.1`,
        weight: 1,
      },
    ])
  }

  const handleRemoveIsp = (id: string) => {
    if (isps.length <= 2) return
    setIsps((prev) => prev.filter((i) => i.id !== id))
  }

  const handleUpdateIsp = (id: string, field: keyof IspConfig, val: any) => {
    setIsps((prev) =>
      prev.map((i) => (i.id === id ? { ...i, [field]: val } : i))
    )
  }

  // Calculate Total Weight for PCC Formula
  const totalWeight = useMemo(() => {
    return isps.reduce((acc, curr) => acc + (Math.max(1, parseInt(curr.weight as any) || 1)), 0)
  }, [isps])

  const generatedScript = useMemo(() => {
    const isV7 = rosVersion === "v7"
    let lines: string[] = []

    lines.push(`# ================================================================`)
    lines.push(`# NODERA — DYNAMIC MULTI-ISP LOAD BALANCING (PCC)`)
    lines.push(`# Target OS    : RouterOS ${isV7 ? "v7.x (Routing Table)" : "v6.x (Routing Mark)"}`)
    lines.push(`# Total ISP    : ${isps.length} ISP (Total PCC Streams: ${totalWeight})`)
    lines.push(`# Classifier   : ${pccClassifier}`)
    lines.push(`# Generated At : ${new Date().toLocaleString("id-ID")}`)
    lines.push(`# ================================================================`)
    lines.push(``)

    // 0. Disable FastTrack
    if (disableFasttrack) {
      lines.push(`# --- 0. NONAKTIFKAN FASTTRACK (WAJIB UNTUK LOAD BALANCING) ---`)
      lines.push(`# FastTrack membypass tabel Mangle, sehingga jika aktif, pembagian`)
      lines.push(`# bandwidth dan routing mark tidak akan berjalan sama sekali.`)
      lines.push(`/ip firewall filter disable [find action=fasttrack-connection]`)
      lines.push(``)
    }

    // 1. DNS Configuration
    if (enableDnsConfig) {
      lines.push(`# --- 1. KONFIGURASI DNS ROUTER ---`)
      lines.push(`/ip dns set allow-remote-requests=yes servers=1.1.1.1,8.8.8.8,1.0.0.1,8.8.4.4`)
      lines.push(``)
    }

    // 2. Local Subnets Bypass
    lines.push(`# --- 2. Address List Jaringan Lokal (Bypass PCC) ---`)
    const subnets = localSubnets.split(",").map((s) => s.trim()).filter(Boolean)
    subnets.forEach((sub, idx) => {
      lines.push(`/ip firewall address-list add address=${sub} list=LOCAL_SUBNET comment="Subnet Lokal ${idx + 1}"`)
    })
    lines.push(``)

    // 3. ROS v7 Routing Tables
    if (isV7) {
      lines.push(`# --- 3. Routing Table ROS v7 ---`)
      isps.forEach((isp, idx) => {
        lines.push(`/routing table add name=to_ISP${idx + 1} fib comment="Table ${isp.name}"`)
      })
      lines.push(``)
    }

    // 4. TCP MSS Clamping
    if (enableMssClamping) {
      lines.push(`# --- 4. TCP MSS Clamping (Mencegah MTU Fragmentation pada PPPoE/Fiber) ---`)
      lines.push(`/ip firewall mangle add chain=forward protocol=tcp tcp-flags=syn tcp-mss=1400-65535 action=change-mss new-mss=clamp-to-pmtu passthrough=yes comment="Clamp MSS for Load Balancing"`)
      lines.push(``)
    }

    // 5. Mangle Rules (PCC)
    lines.push(`# --- 5. Mangle Rules (Bypass Akses Router & Inter-LAN) ---`)
    const lanParam = lanType === "list" ? `in-interface-list=${lanInterface}` : `in-interface=${lanInterface}`

    // Bypass router self & local traffic
    lines.push(`/ip firewall mangle add chain=prerouting dst-address-type=local action=accept comment="Bypass Akses ke Router (DNS, Winbox, Hotspot)"`)
    lines.push(`/ip firewall mangle add chain=prerouting dst-address-list=LOCAL_SUBNET ${lanParam} action=accept comment="Bypass Jaringan Lokal"`)

    // Inbound WAN Connection Marking (using chain=prerouting for port forwarding compatibility)
    lines.push(``)
    lines.push(`# --- Inbound WAN Connection Marking ---`)
    isps.forEach((isp, idx) => {
      const num = idx + 1
      lines.push(`/ip firewall mangle add chain=prerouting in-interface=${isp.interfaceName} connection-mark=no-mark action=mark-connection new-connection-mark=ISP${num}_conn passthrough=yes comment="Inbound WAN ${isp.name}"`)
    })

    // Outbound Router Self-Generated Traffic
    lines.push(``)
    lines.push(`# --- Outbound Router Output Routing ---`)
    isps.forEach((isp, idx) => {
      const num = idx + 1
      lines.push(`/ip firewall mangle add chain=output connection-mark=ISP${num}_conn action=mark-routing new-routing-mark=to_ISP${num} passthrough=no comment="Outbound Router ${isp.name}"`)
    })

    // PCC Multi-Stream Distribution
    lines.push(``)
    lines.push(`# --- PCC Multi-Stream Connection Marking ---`)
    let streamIndex = 0
    isps.forEach((isp, idx) => {
      const num = idx + 1
      const weight = Math.max(1, parseInt(isp.weight as any) || 1)
      for (let w = 0; w < weight; w++) {
        lines.push(`/ip firewall mangle add chain=prerouting ${lanParam} dst-address-type=!local dst-address-list=!LOCAL_SUBNET connection-mark=no-mark per-connection-classifier=${pccClassifier}:${totalWeight}/${streamIndex} action=mark-connection new-connection-mark=ISP${num}_conn passthrough=yes comment="PCC Stream ${totalWeight}/${streamIndex} (${isp.name})"`)
        streamIndex++
      }
    })

    // Mark Routing for LAN traffic
    lines.push(``)
    lines.push(`# --- Mark Routing Klien LAN ke WAN Masing-Masing ---`)
    isps.forEach((isp, idx) => {
      const num = idx + 1
      lines.push(`/ip firewall mangle add chain=prerouting ${lanParam} connection-mark=ISP${num}_conn action=mark-routing new-routing-mark=to_ISP${num} passthrough=no comment="Route to ${isp.name}"`)
    })

    lines.push(``)

    // 6. NAT Masquerade
    lines.push(`# --- 6. NAT Masquerade Out-Interface ---`)
    isps.forEach((isp, idx) => {
      lines.push(`/ip firewall nat add chain=srcnat out-interface=${isp.interfaceName} action=masquerade comment="NAT Masquerade ${isp.name}"`)
    })

    lines.push(``)

    // 7. IP Routes with Ping Failover
    lines.push(`# --- 7. IP Routes Failover & Gateway Routing ---`)
    lines.push(`# PENTING: Pada /ip dhcp-client di interface WAN, pastikan opsi 'Add Default Route' diset 'no'.`)
    isps.forEach((isp, idx) => {
      const num = idx + 1
      if (isV7) {
        lines.push(`/ip route add dst-address=0.0.0.0/0 gateway=${isp.gateway} check-gateway=ping routing-table=to_ISP${num} distance=1 comment="Route khusus to_ISP${num}"`)
        lines.push(`/ip route add dst-address=0.0.0.0/0 gateway=${isp.gateway} check-gateway=ping distance=${num} comment="Default Failover ISP${num}"`)
      } else {
        lines.push(`/ip route add dst-address=0.0.0.0/0 gateway=${isp.gateway} check-gateway=ping routing-mark=to_ISP${num} distance=1 comment="Route khusus to_ISP${num}"`)
        lines.push(`/ip route add dst-address=0.0.0.0/0 gateway=${isp.gateway} check-gateway=ping distance=${num} comment="Default Failover ISP${num}"`)
      }
    })

    return lines.join("\n")
  }, [rosVersion, lanInterface, lanType, pccClassifier, localSubnets, isps, totalWeight, disableFasttrack, enableDnsConfig, enableMssClamping])

  return (
    <ToolsLayout
      currentToolId="loadbalance"
      title="Load Balancing"
      titleGradient="(PCC Multi-ISP Dinamis)"
      subtitle="Bagi beban koneksi internet ke 2, 3, 4, 5, hingga 10+ ISP secara dinamis dengan pembagian rasio bandwidth dan auto-failover."
      badgeText="PCC MULTI-GATEWAY STUDIO"
      rosVersion={rosVersion}
      setRosVersion={setRosVersion}
      generatedScript={generatedScript}
      registeredRouters={registeredRouters}
      company={company}
      appName={appName}
    >
      <div className="space-y-4 text-xs">
        {/* Troubleshooting & Checklist Info Box */}
        <div className="p-4 rounded-2xl bg-amber-500/10 border border-amber-500/20 text-amber-200 space-y-2">
          <div className="flex items-center gap-2 font-bold text-amber-300 text-xs">
            <AlertTriangle className="w-4 h-4 text-amber-400 shrink-0" />
            <span>PENTING — Agar Load Balancing Berjalan Lancar di MikroTik:</span>
          </div>
          <ul className="list-disc list-inside space-y-1 text-[11px] text-slate-300 pl-1">
            <li>
              <strong className="text-amber-300">Nonaktifkan FastTrack</strong>: Rule FastTrack di <span className="font-mono text-cyan-300">/ip firewall filter</span> wajib di-disable (sudah otomatis ada di script).
            </li>
            <li>
              <strong className="text-amber-300">DHCP-Client WAN</strong>: Jika IP WAN dari DHCP/Modem ISP, ubah opsi <span className="font-mono text-cyan-300">Add Default Route = no</span> di DHCP Client agar tidak bentrok dengan failover route.
            </li>
            <li>
              <strong className="text-amber-300">PCC Classifier</strong>: Gunakan <span className="font-mono text-cyan-300">both-addresses</span> (default) agar sesi HTTPS, m-Banking, WhatsApp, & game tetap stabil dan tidak sering logout.
            </li>
          </ul>
        </div>

        {/* Global Settings */}
        <div className="space-y-3.5 p-4 rounded-2xl bg-white/[0.02] border border-white/10">
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <div className="flex items-center justify-between mb-1.5">
                <label className="text-xs uppercase tracking-wider font-bold text-slate-300">
                  {lanType === "list" ? "Interface List LAN" : "Interface LAN (Lokal Klien)"}
                </label>
                <div className="flex items-center gap-1 bg-white/5 p-0.5 rounded-lg border border-white/10 text-[10px]">
                  <button
                    type="button"
                    onClick={() => setLanType("interface")}
                    className={cn("px-2 py-0.5 rounded", lanType === "interface" ? "bg-cyan-500 text-white font-bold" : "text-slate-400")}
                  >
                    Interface
                  </button>
                  <button
                    type="button"
                    onClick={() => setLanType("list")}
                    className={cn("px-2 py-0.5 rounded", lanType === "list" ? "bg-cyan-500 text-white font-bold" : "text-slate-400")}
                  >
                    Interface-List
                  </button>
                </div>
              </div>
              <input
                type="text"
                value={lanInterface}
                onChange={(e) => setLanInterface(e.target.value)}
                placeholder={lanType === "list" ? "LAN" : "bridge-LAN"}
                className="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2 text-slate-200 focus:outline-none focus:border-cyan-500/50 text-sm font-mono"
              />
            </div>

            <div>
              <label className="block text-xs uppercase tracking-wider font-bold text-slate-300 mb-1.5">
                Metode Classifier PCC
              </label>
              <select
                value={pccClassifier}
                onChange={(e) => setPccClassifier(e.target.value)}
                className="w-full bg-[#0E1522] border border-white/10 rounded-xl px-3 py-2 text-slate-200 focus:outline-none focus:border-cyan-500/50 text-xs"
              >
                <option value="both-addresses">both-addresses (Rekomendasi / Paling Stabil untuk Web & Game)</option>
                <option value="both-addresses-and-ports">both-addresses-and-ports (Maksimal Pembagian Bandwidth)</option>
                <option value="src-address">src-address (Per IP Klien)</option>
                <option value="src-address-and-port">src-address-and-port (Per Port Klien)</option>
                <option value="dst-address">dst-address (Per Tujuan)</option>
              </select>
            </div>
          </div>

          <div>
            <label className="block text-xs uppercase tracking-wider font-bold text-slate-300 mb-1.5">
              Subnet Lokal Bypass (Dipisahkan Koma)
            </label>
            <input
              type="text"
              value={localSubnets}
              onChange={(e) => setLocalSubnets(e.target.value)}
              placeholder="192.168.0.0/16, 10.0.0.0/8, 172.16.0.0/12"
              className="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2 text-slate-200 focus:outline-none focus:border-cyan-500/50 font-mono text-xs"
            />
          </div>

          {/* Quick Options Toggles */}
          <div className="pt-2 border-t border-white/5 grid grid-cols-1 sm:grid-cols-3 gap-2">
            <label className="flex items-center gap-2 cursor-pointer text-slate-300 text-[11px]">
              <input
                type="checkbox"
                checked={disableFasttrack}
                onChange={(e) => setDisableFasttrack(e.target.checked)}
                className="rounded border-white/20 bg-white/5 text-cyan-500 focus:ring-0"
              />
              <span>Disable FastTrack (Wajib)</span>
            </label>
            <label className="flex items-center gap-2 cursor-pointer text-slate-300 text-[11px]">
              <input
                type="checkbox"
                checked={enableDnsConfig}
                onChange={(e) => setEnableDnsConfig(e.target.checked)}
                className="rounded border-white/20 bg-white/5 text-cyan-500 focus:ring-0"
              />
              <span>Set DNS Resolver Router</span>
            </label>
            <label className="flex items-center gap-2 cursor-pointer text-slate-300 text-[11px]">
              <input
                type="checkbox"
                checked={enableMssClamping}
                onChange={(e) => setEnableMssClamping(e.target.checked)}
                className="rounded border-white/20 bg-white/5 text-cyan-500 focus:ring-0"
              />
              <span>TCP MSS Clamping (PPPoE)</span>
            </label>
          </div>
        </div>

        {/* Dynamic ISP List Header */}
        <div className="flex items-center justify-between pt-2">
          <span className="font-extrabold text-white text-xs uppercase tracking-wider flex items-center gap-1.5">
            <Radio className="w-3.5 h-3.5 text-cyan-400" />
            <span>Daftar ISP WAN ({isps.length} Terpasang)</span>
          </span>
          <button
            type="button"
            onClick={handleAddIsp}
            className="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-full text-xs font-bold bg-cyan-500/20 hover:bg-cyan-500/30 text-cyan-300 border border-cyan-500/40 transition-colors shadow-sm"
          >
            <Plus className="w-3.5 h-3.5" />
            <span>Tambah ISP</span>
          </button>
        </div>

        {/* Dynamic ISP Cards */}
        <div className="space-y-3 max-h-[380px] overflow-y-auto pr-1 scrollbar-thin">
          {isps.map((isp, idx) => (
            <div
              key={isp.id}
              className="p-4 rounded-2xl bg-white/[0.02] border border-white/10 hover:border-cyan-500/30 transition-all space-y-3"
            >
              <div className="flex items-center justify-between">
                <span className="font-bold text-cyan-400 flex items-center gap-2">
                  <span className="w-5 h-5 rounded-full bg-cyan-500/20 text-cyan-300 text-[10px] flex items-center justify-center font-extrabold">
                    {idx + 1}
                  </span>
                  <span>{isp.name}</span>
                </span>
                {isps.length > 2 && (
                  <button
                    type="button"
                    onClick={() => handleRemoveIsp(isp.id)}
                    className="p-1.5 rounded-lg text-slate-400 hover:text-red-400 hover:bg-red-500/10 transition-colors"
                  >
                    <Trash2 className="w-4 h-4" />
                  </button>
                )}
              </div>

              <div className="grid grid-cols-2 gap-2.5">
                <div>
                  <label className="block text-[11px] text-slate-400 mb-1">Nama Interface</label>
                  <input
                    type="text"
                    value={isp.interfaceName}
                    onChange={(e) => handleUpdateIsp(isp.id, "interfaceName", e.target.value)}
                    placeholder="ether1-ISP1"
                    className="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-1.5 text-slate-200 text-xs"
                  />
                </div>
                <div>
                  <label className="block text-[11px] text-slate-400 mb-1">IP Gateway WAN</label>
                  <input
                    type="text"
                    value={isp.gateway}
                    onChange={(e) => handleUpdateIsp(isp.id, "gateway", e.target.value)}
                    placeholder="192.168.1.1"
                    className="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-1.5 text-slate-200 font-mono text-xs"
                  />
                </div>
              </div>

              <div>
                <label className="block text-[11px] text-slate-400 mb-1">
                  Rasio / Bobot Bandwidth (PCC Weight: {isp.weight}x)
                </label>
                <div className="flex items-center gap-2">
                  {[1, 2, 3, 4].map((w) => (
                    <button
                      key={w}
                      type="button"
                      onClick={() => handleUpdateIsp(isp.id, "weight", w)}
                      className={cn(
                        "flex-1 py-1.5 rounded-xl font-bold text-xs border transition-all text-center",
                        isp.weight === w
                          ? "bg-cyan-500 text-white border-cyan-400 shadow-md"
                          : "bg-white/5 border-white/10 text-slate-400 hover:text-white"
                      )}
                    >
                      {w}x
                    </button>
                  ))}
                </div>
              </div>
            </div>
          ))}
        </div>
      </div>
    </ToolsLayout>
  )
}

