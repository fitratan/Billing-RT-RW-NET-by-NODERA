import React, { useState, useMemo } from "react"
import ToolsLayout, { RegisteredRouter } from "@/layouts/ToolsLayout"
import { Gamepad2, CheckSquare, Square } from "lucide-react"
import { cn } from "@/lib/utils"

const GAME_PRESETS = [
  { name: "Mobile Legends", portsTcp: "5000-5200,9000-9010", portsUdp: "5000-5200,5500-5700,9000-9010" },
  { name: "PUBG Mobile", portsTcp: "10012,17500,20000-20002", portsUdp: "10012,17500,20000-20002,8011" },
  { name: "Free Fire", portsTcp: "7006,39698,39779", portsUdp: "7006,39698,39779,10000-10009" },
  { name: "Valorant & Riot Games", portsTcp: "2099,5223,8393-8400", portsUdp: "7000-8000,8180-8181" },
  { name: "Point Blank Zepetto", portsTcp: "1280-1282", portsUdp: "40000-40010" },
  { name: "Genshin Impact", portsTcp: "27015-27030", portsUdp: "22101-22102" },
  { name: "Dota 2 & Steam Games", portsTcp: "27015-27030", portsUdp: "27000-27100" },
  { name: "Roblox", portsTcp: "80,443", portsUdp: "49152-65535" },
]

export default function GameTraffic({
  registeredRouters = [],
  company,
  appName,
}: {
  registeredRouters?: RegisteredRouter[]
  company?: any
  appName?: string
}) {
  const [rosVersion, setRosVersion] = useState<"v7" | "v6">("v7")
  const [targetWan, setTargetWan] = useState<string>("ISP1")
  const [targetGateway, setTargetGateway] = useState<string>("192.168.1.1")
  const [selectedGames, setSelectedGames] = useState<string[]>(GAME_PRESETS.map((g) => g.name))

  const toggleGame = (name: string) => {
    setSelectedGames((prev) =>
      prev.includes(name) ? prev.filter((n) => n !== name) : [...prev, name]
    )
  }

  const generatedScript = useMemo(() => {
    const isV7 = rosVersion === "v7"
    let lines: string[] = []

    lines.push(`# ================================================================`)
    lines.push(`# NODERA — PISAH TRAFFIC GAME ONLINE (LOW LATENCY)`)
    lines.push(`# Target OS    : RouterOS ${isV7 ? "v7.x (Routing Table)" : "v6.x (Routing Mark)"}`)
    lines.push(`# Jalur Game   : ${targetWan} (Gateway: ${targetGateway})`)
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
      lines.push(`/routing table add name=to_GAME fib comment="Routing Table Game Online"`)
      lines.push(``)
    }

    lines.push(`# --- 3. Mangle Connection Mark per Game Online ---`)
    GAME_PRESETS.filter((g) => selectedGames.includes(g.name)).forEach((game) => {
      if (game.portsTcp) {
        lines.push(`/ip firewall mangle add chain=prerouting dst-address-list=!LOCAL_LAN protocol=tcp dst-port=${game.portsTcp} action=mark-connection new-connection-mark=GAME_CONN passthrough=yes comment="Game TCP: ${game.name}"`)
      }
      if (game.portsUdp) {
        lines.push(`/ip firewall mangle add chain=prerouting dst-address-list=!LOCAL_LAN protocol=udp dst-port=${game.portsUdp} action=mark-connection new-connection-mark=GAME_CONN passthrough=yes comment="Game UDP: ${game.name}"`)
      }
    })

    lines.push(``)
    lines.push(`# --- 4. Mangle Mark Routing ---`)
    lines.push(`/ip firewall mangle add chain=prerouting connection-mark=GAME_CONN action=mark-routing new-routing-mark=to_GAME passthrough=no comment="Route Game Traffic"`)
    lines.push(``)

    lines.push(`# --- 5. IP Route Prioritas Game ---`)
    if (isV7) {
      lines.push(`/ip route add dst-address=0.0.0.0/0 gateway=${targetGateway} check-gateway=ping routing-table=to_GAME distance=1 comment="Jalur Prioritas Game (${targetWan})"`)
    } else {
      lines.push(`/ip route add dst-address=0.0.0.0/0 gateway=${targetGateway} check-gateway=ping routing-mark=to_GAME distance=1 comment="Jalur Prioritas Game (${targetWan})"`)
    }

    return lines.join("\n")
  }, [rosVersion, targetWan, targetGateway, selectedGames])

  return (
    <ToolsLayout
      currentToolId="game"
      title="Pisah Traffic"
      titleGradient="Game Online (Anti-Lag / QoS)"
      subtitle="Pisahkan dan prioritaskan koneksi paket game online (Mobile Legends, PUBG, Free Fire, Valorant, Dota 2, dll) ke ISP dengan ping terendah."
      badgeText="PBR GAME ONLINE STUDIO"
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
            <label className="block text-xs uppercase tracking-wider font-bold text-slate-300 mb-1.5">Alokasi Jalur / ISP Khusus Game</label>
            <input
              type="text"
              value={targetWan}
              onChange={(e) => setTargetWan(e.target.value)}
              placeholder="ISP1"
              className="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2 text-slate-200 focus:outline-none focus:border-cyan-500/50 text-sm"
            />
          </div>

          <div>
            <label className="block text-xs uppercase tracking-wider font-bold text-slate-300 mb-1.5">IP Gateway ISP Game</label>
            <input
              type="text"
              value={targetGateway}
              onChange={(e) => setTargetGateway(e.target.value)}
              placeholder="192.168.1.1"
              className="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2 text-slate-200 focus:outline-none focus:border-cyan-500/50 font-mono text-sm"
            />
          </div>
        </div>

        {/* Games Preset Checklist */}
        <div className="space-y-2">
          <label className="block text-xs uppercase tracking-wider font-bold text-slate-300">Pilih Game Online yang Dipisahkan:</label>
          <div className="space-y-2 max-h-[320px] overflow-y-auto pr-1 scrollbar-thin">
            {GAME_PRESETS.map((game) => {
              const isChecked = selectedGames.includes(game.name)
              return (
                <button
                  key={game.name}
                  type="button"
                  onClick={() => toggleGame(game.name)}
                  className={cn(
                    "w-full flex items-center justify-between p-3.5 rounded-xl border text-left transition-all",
                    isChecked
                      ? "bg-cyan-500/15 border-cyan-500/40 text-cyan-300 shadow-sm"
                      : "bg-white/[0.02] border-white/10 text-slate-400 hover:text-white"
                  )}
                >
                  <div className="flex items-center gap-3">
                    {isChecked ? <CheckSquare className="w-4 h-4 text-cyan-400" /> : <Square className="w-4 h-4 text-slate-600" />}
                    <span className="font-bold text-xs">{game.name}</span>
                  </div>
                  <span className="text-[10px] font-mono text-slate-500 truncate max-w-[140px]">
                    TCP/UDP
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
