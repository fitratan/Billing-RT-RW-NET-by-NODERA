import React, { useState, useEffect, useMemo } from "react"
import {
  X,
  Sparkles,
  Layers,
  Radio,
  Gamepad2,
  Tv,
  ShieldCheck,
  Globe,
  ArrowDown,
  Gauge,
  Play,
  Sliders,
  Copy,
  Download,
  Check,
  RefreshCw,
  CheckCircle2,
  XCircle,
  SkipForward,
  PlayCircle,
  Eye,
  EyeOff,
  Terminal,
  Server,
  ArrowRight,
  CornerDownRight,
  CheckSquare,
  Activity,
} from "lucide-react"
import axios from "axios"
import { cn } from "@/lib/utils"

export type ToolType =
  | "loadbalance"
  | "pbr_game"
  | "pbr_stream"
  | "pbr_speedtest"
  | "security"
  | "hotspot_pppoe"
  | "port_forward"
  | "burst_qos"

export interface ToolMenuItem {
  id: ToolType
  title: string
  desc: string
  icon: React.ComponentType<{ className?: string }>
  badge: string
}

export const MIKROTIK_TOOLS_LIST: ToolMenuItem[] = [
  {
    id: "loadbalance",
    title: "Load Balancing (PCC & Failover)",
    desc: "Bagi beban 2, 3, atau 4 ISP dengan failover otomatis & recursive gateway.",
    icon: Radio,
    badge: "2-4 ISP",
  },
  {
    id: "pbr_game",
    title: "Pisah Traffic Game Online",
    desc: "Prioritas latensi rendah untuk ML, PUBG, Free Fire, Valorant, Dota 2, Steam.",
    icon: Gamepad2,
    badge: "Anti-Lag",
  },
  {
    id: "pbr_stream",
    title: "Pisah Traffic Streaming & CDN",
    desc: "Arahkan YouTube, Netflix, TikTok ke jalur khusus bandwidth besar.",
    icon: Tv,
    badge: "Video CDN",
  },
  {
    id: "pbr_speedtest",
    title: "Bypass Speedtest (Ookla & Fast.com)",
    desc: "Bypass limitasi QoS saat speedtest agar hasil uji kecepatan pelanggan maksimal.",
    icon: Activity,
    badge: "Max Speed",
  },
  {
    id: "security",
    title: "Hardening & Anti-Bruteforce",
    desc: "Proteksi Winbox, SSH, anti port scanner PSD, tutup DNS WAN & drop bogon.",
    icon: ShieldCheck,
    badge: "RAW Filter",
  },
  {
    id: "hotspot_pppoe",
    title: "Generator Hotspot & PPPoE Server",
    desc: "Otomasi Bridge, IP Pool, DHCP Server, PPPoE Profile & NAT Masquerade.",
    icon: Globe,
    badge: "Quick Setup",
  },
  {
    id: "port_forward",
    title: "DST-NAT Port Forwarding",
    desc: "Buka akses port publik ke CCTV, DVR, Web Server, atau Remote Desktop.",
    icon: ArrowDown,
    badge: "NAT Forward",
  },
  {
    id: "burst_qos",
    title: "Burst QoS & Simple Queue",
    desc: "Kalkulator burst limit & threshold otomatis untuk browsing responsif.",
    icon: Gauge,
    badge: "Smooth QoS",
  },
]

interface MikrotikToolsModalProps {
  isOpen: boolean
  onClose: () => void
  initialTool?: ToolType
  registeredRouters?: Array<{
    id: number
    name: string
    host: string
    port: number
    username: string
  }>
}

export default function MikrotikToolsModal({
  isOpen,
  onClose,
  initialTool = "loadbalance",
  registeredRouters = [],
}: MikrotikToolsModalProps) {
  const [selectedTool, setSelectedTool] = useState<ToolType>(initialTool)
  const [rosVersion, setRosVersion] = useState<"v7" | "v6">("v7")
  const [activeTab, setActiveTab] = useState<"script" | "api_apply">("script")
  const [copied, setCopied] = useState(false)

  useEffect(() => {
    if (initialTool) setSelectedTool(initialTool)
  }, [initialTool])

  // ==================== CONFIG STATES ====================
  // 1. Load Balance
  const [lbIspCount, setLbIspCount] = useState<2 | 3 | 4>(2)
  const [lbLanInterface, setLbLanInterface] = useState<string>("bridge-LAN")
  const [lbWan1Name, setLbWan1Name] = useState<string>("ether1-ISP1")
  const [lbWan1Gw, setLbWan1Gw] = useState<string>("192.168.1.1")
  const [lbWan2Name, setLbWan2Name] = useState<string>("ether2-ISP2")
  const [lbWan2Gw, setLbWan2Gw] = useState<string>("192.168.2.1")
  const [lbWan3Name, setLbWan3Name] = useState<string>("ether3-ISP3")
  const [lbWan3Gw, setLbWan3Gw] = useState<string>("192.168.3.1")
  const [lbWan4Name, setLbWan4Name] = useState<string>("ether4-ISP4")
  const [lbWan4Gw, setLbWan4Gw] = useState<string>("192.168.4.1")

  // 2. Pisah Traffic Game
  const [pbrGameWan, setPbrGameWan] = useState<string>("ISP1")
  const [pbrGameGw, setPbrGameGw] = useState<string>("192.168.1.1")

  // 3. Pisah Traffic Streaming
  const [pbrStreamWan, setPbrStreamWan] = useState<string>("ISP2")
  const [pbrStreamGw, setPbrStreamGw] = useState<string>("192.168.2.1")

  // 4. Security Hardening
  const [secWanInterface, setSecWanInterface] = useState<string>("ether1-WAN")
  const [secProtectWinbox, setSecProtectWinbox] = useState<boolean>(true)
  const [secProtectSsh, setSecProtectSsh] = useState<boolean>(true)
  const [secDropPortScanner, setSecDropPortScanner] = useState<boolean>(true)
  const [secDropDnsWan, setSecDropDnsWan] = useState<boolean>(true)
  const [secDropBogonWan, setSecDropBogonWan] = useState<boolean>(true)

  // 5. Hotspot & PPPoE
  const [setupLanName, setSetupLanName] = useState<string>("bridge-HOTSPOT")
  const [setupLanIp, setSetupLanIp] = useState<string>("192.168.10.1/24")
  const [setupDhcpRange, setSetupDhcpRange] = useState<string>("192.168.10.10-192.168.10.254")
  const [setupDnsName, setSetupDnsName] = useState<string>("wifi.nodera.net")
  const [setupEnablePppoe, setSetupEnablePppoe] = useState<boolean>(true)
  const [setupPppoePool, setSetupPppoePool] = useState<string>("10.10.0.10-10.10.0.254")

  // 6. Port Forwarding
  const [natProtocol, setNatProtocol] = useState<"tcp" | "udp">("tcp")
  const [natDstPort, setNatDstPort] = useState<string>("8080")
  const [natToIp, setNatToIp] = useState<string>("192.168.10.50")
  const [natToPort, setNatToPort] = useState<string>("80")
  const [natInWan, setNatInWan] = useState<string>("ether1-WAN")
  const [natComment, setNatComment] = useState<string>("Forward CCTV Server")

  // 7. Burst QoS
  const [burstTargetIp, setBurstTargetIp] = useState<string>("192.168.10.0/24")
  const [burstMaxUpload, setBurstMaxUpload] = useState<string>("10M")
  const [burstMaxDownload, setBurstMaxDownload] = useState<string>("20M")
  const [burstMultiplier, setBurstMultiplier] = useState<number>(1.5)
  const [burstDuration, setBurstDuration] = useState<number>(16)

  // ==================== DIRECT API STATE ====================
  const [manualHost, setManualHost] = useState<string>("")
  const [manualPort, setManualPort] = useState<string>("8728")
  const [manualUser, setManualUser] = useState<string>("admin")
  const [manualPass, setManualPass] = useState<string>("")
  const [showPass, setShowPass] = useState<boolean>(false)
  const [selectedRouterId, setSelectedRouterId] = useState<number | string>(registeredRouters[0]?.id || "")

  const [isTestingConn, setIsTestingConn] = useState<boolean>(false)
  const [connStatus, setConnStatus] = useState<"idle" | "connected" | "failed">("idle")
  const [connMessage, setConnMessage] = useState<string>("")
  const [routerInfo, setRouterInfo] = useState<any>(null)

  const [isExecuting, setIsExecuting] = useState<boolean>(false)
  const [stepMode, setStepMode] = useState<boolean>(false)
  const [currentStepIndex, setCurrentStepIndex] = useState<number>(0)
  const [executionResults, setExecutionResults] = useState<any[]>([])

  // ==================== GENERATED SCRIPT ====================
  const generatedScript = useMemo(() => {
    const isV7 = rosVersion === "v7"
    let lines: string[] = []

    lines.push(`# ================================================================`)
    lines.push(`# NODERA MIKROTIK STUDIO — ${selectedTool.toUpperCase()}`)
    lines.push(`# Target OS    : RouterOS ${isV7 ? "v7.x (Routing Table)" : "v6.x (Routing Mark)"}`)
    lines.push(`# Generated At : ${new Date().toLocaleString("id-ID")}`)
    lines.push(`# ================================================================`)
    lines.push(``)

    if (selectedTool === "loadbalance") {
      lines.push(`# --- 1. Address List Jaringan Lokal (Bypass PCC) ---`)
      lines.push(`/ip firewall address-list add address=10.0.0.0/8 list=LOCAL_SUBNET comment="Class A"`)
      lines.push(`/ip firewall address-list add address=172.16.0.0/12 list=LOCAL_SUBNET comment="Class B"`)
      lines.push(`/ip firewall address-list add address=192.168.0.0/16 list=LOCAL_SUBNET comment="Class C"`)
      lines.push(``)

      if (isV7) {
        lines.push(`# --- 2. Routing Table ROS v7 ---`)
        for (let i = 1; i <= lbIspCount; i++) {
          lines.push(`/routing table add name=to_ISP${i} fib comment="Table ISP ${i}"`)
        }
        lines.push(``)
      }

      lines.push(`# --- 3. Mangle PCC Rules ---`)
      lines.push(`/ip firewall mangle add chain=prerouting dst-address-list=LOCAL_SUBNET in-interface=${lbLanInterface} action=accept comment="Bypass Lokal"`)

      const wans = [
        { name: lbWan1Name, gw: lbWan1Gw },
        { name: lbWan2Name, gw: lbWan2Gw },
        { name: lbWan3Name, gw: lbWan3Gw },
        { name: lbWan4Name, gw: lbWan4Gw },
      ].slice(0, lbIspCount)

      wans.forEach((w, idx) => {
        const num = idx + 1
        lines.push(`/ip firewall mangle add chain=input in-interface=${w.name} action=mark-connection new-connection-mark=ISP${num}_conn passthrough=yes comment="In ISP${num}"`)
      })

      wans.forEach((w, idx) => {
        const num = idx + 1
        lines.push(`/ip firewall mangle add chain=output connection-mark=ISP${num}_conn action=mark-routing new-routing-mark=to_ISP${num} passthrough=no comment="Out ISP${num}"`)
      })

      wans.forEach((w, idx) => {
        const num = idx + 1
        lines.push(`/ip firewall mangle add chain=prerouting in-interface=${lbLanInterface} dst-address-list=!LOCAL_SUBNET connection-state=new per-connection-classifier=both-addresses-and-ports:${lbIspCount}/${idx} action=mark-connection new-connection-mark=ISP${num}_conn passthrough=yes comment="PCC ${lbIspCount}/${idx}"`)
      })

      wans.forEach((w, idx) => {
        const num = idx + 1
        lines.push(`/ip firewall mangle add chain=prerouting in-interface=${lbLanInterface} connection-mark=ISP${num}_conn action=mark-routing new-routing-mark=to_ISP${num} passthrough=no comment="Route ISP${num}"`)
      })

      lines.push(``)
      lines.push(`# --- 4. NAT Masquerade ---`)
      wans.forEach((w, idx) => {
        lines.push(`/ip firewall nat add chain=srcnat out-interface=${w.name} action=masquerade comment="NAT ISP${idx + 1}"`)
      })

      lines.push(``)
      lines.push(`# --- 5. IP Routes Failover ---`)
      wans.forEach((w, idx) => {
        const num = idx + 1
        if (isV7) {
          lines.push(`/ip route add dst-address=0.0.0.0/0 gateway=${w.gw} check-gateway=ping routing-table=to_ISP${num} distance=1 comment="Route to_ISP${num}"`)
          lines.push(`/ip route add dst-address=0.0.0.0/0 gateway=${w.gw} check-gateway=ping distance=${num} comment="Default Route ISP${num}"`)
        } else {
          lines.push(`/ip route add dst-address=0.0.0.0/0 gateway=${w.gw} check-gateway=ping routing-mark=to_ISP${num} distance=1 comment="Route to_ISP${num}"`)
          lines.push(`/ip route add dst-address=0.0.0.0/0 gateway=${w.gw} check-gateway=ping distance=${num} comment="Default Route ISP${num}"`)
        }
      })
    } else if (selectedTool === "pbr_game") {
      lines.push(`# --- Pisah Traffic Game Online (Low Latency / High Priority) ---`)
      lines.push(`/ip firewall address-list add address=10.0.0.0/8 list=LOCAL_LAN comment="Private Network"`)
      lines.push(`/ip firewall address-list add address=192.168.0.0/16 list=LOCAL_LAN comment="Private Network"`)
      lines.push(``)
      if (isV7) {
        lines.push(`/routing table add name=to_GAME fib comment="Table Game Online"`)
      }
      lines.push(`/ip firewall mangle add chain=prerouting dst-address-list=!LOCAL_LAN protocol=tcp dst-port=30000-30299,5000-5200,9000-9010,27015-27030 action=mark-connection new-connection-mark=GAME_CONN passthrough=yes comment="Game TCP (ML, PUBG, Steam)"`)
      lines.push(`/ip firewall mangle add chain=prerouting dst-address-list=!LOCAL_LAN protocol=udp dst-port=7000-8000,10000-10100,17000-17100,27000-27100,9000-9100,30000-30300 action=mark-connection new-connection-mark=GAME_CONN passthrough=yes comment="Game UDP (FF, Valorant, Genshin)"`)
      lines.push(`/ip firewall mangle add chain=prerouting connection-mark=GAME_CONN action=mark-routing new-routing-mark=to_GAME passthrough=no comment="Route Game"`)
      lines.push(``)
      if (isV7) {
        lines.push(`/ip route add dst-address=0.0.0.0/0 gateway=${pbrGameGw} check-gateway=ping routing-table=to_GAME distance=1 comment="Jalur Prioritas Game (${pbrGameWan})"`)
      } else {
        lines.push(`/ip route add dst-address=0.0.0.0/0 gateway=${pbrGameGw} check-gateway=ping routing-mark=to_GAME distance=1 comment="Jalur Prioritas Game (${pbrGameWan})"`)
      }
    } else if (selectedTool === "pbr_stream") {
      lines.push(`# --- Pisah Traffic Streaming & Video CDN (YouTube, Netflix, TikTok) ---`)
      lines.push(`/ip firewall address-list add address=10.0.0.0/8 list=LOCAL_LAN comment="Private Network"`)
      lines.push(`/ip firewall address-list add address=192.168.0.0/16 list=LOCAL_LAN comment="Private Network"`)
      lines.push(``)
      if (isV7) {
        lines.push(`/routing table add name=to_STREAM fib comment="Table Streaming CDN"`)
      }
      lines.push(`/ip firewall mangle add chain=prerouting dst-address-list=!LOCAL_LAN protocol=tcp dst-port=80,443 content="googlevideo.com" action=mark-connection new-connection-mark=STREAM_CONN passthrough=yes comment="YouTube & Google Video"`)
      lines.push(`/ip firewall mangle add chain=prerouting dst-address-list=!LOCAL_LAN protocol=tcp dst-port=80,443 content="netflix.com" action=mark-connection new-connection-mark=STREAM_CONN passthrough=yes comment="Netflix Video Stream"`)
      lines.push(`/ip firewall mangle add chain=prerouting dst-address-list=!LOCAL_LAN protocol=tcp dst-port=80,443 content="tiktokcdn.com" action=mark-connection new-connection-mark=STREAM_CONN passthrough=yes comment="TikTok Video CDN"`)
      lines.push(`/ip firewall mangle add chain=prerouting connection-mark=STREAM_CONN action=mark-routing new-routing-mark=to_STREAM passthrough=no comment="Route Streaming"`)
      lines.push(``)
      if (isV7) {
        lines.push(`/ip route add dst-address=0.0.0.0/0 gateway=${pbrStreamGw} check-gateway=ping routing-table=to_STREAM distance=1 comment="Jalur Khusus Streaming (${pbrStreamWan})"`)
      } else {
        lines.push(`/ip route add dst-address=0.0.0.0/0 gateway=${pbrStreamGw} check-gateway=ping routing-mark=to_STREAM distance=1 comment="Jalur Khusus Streaming (${pbrStreamWan})"`)
      }
    } else if (selectedTool === "pbr_speedtest") {
      lines.push(`# --- Bypass Speedtest (Ookla Speedtest, Fast.com, NPerf) ---`)
      lines.push(`/ip firewall mangle add chain=prerouting protocol=tcp dst-port=8080,5060 action=mark-connection new-connection-mark=SPEEDTEST_CONN passthrough=yes comment="Speedtest TCP Port Direct"`)
      lines.push(`/ip firewall mangle add chain=prerouting connection-mark=SPEEDTEST_CONN action=set-priority new-priority=7 passthrough=yes comment="Set High Priority 7 Speedtest"`)
    } else if (selectedTool === "security") {
      lines.push(`# --- Firewall Filter & Anti-Bruteforce Hardening ---`)
      lines.push(`/ip firewall filter add chain=input connection-state=invalid action=drop comment="Drop Invalid Input"`)
      lines.push(`/ip firewall filter add chain=forward connection-state=invalid action=drop comment="Drop Invalid Forward"`)
      lines.push(`/ip firewall filter add chain=input connection-state=established,related action=accept comment="Accept Established Input"`)
      lines.push(`/ip firewall filter add chain=forward connection-state=established,related action=accept comment="Accept Established Forward"`)

      if (secProtectWinbox) {
        lines.push(``)
        lines.push(`# --- Anti Bruteforce Winbox (Port 8291) ---`)
        lines.push(`/ip firewall filter add chain=input protocol=tcp dst-port=8291 src-address-list=WINBOX_BLACKLIST action=drop comment="Drop Winbox Blacklist"`)
        lines.push(`/ip firewall filter add chain=input protocol=tcp dst-port=8291 connection-state=new src-address-list=WINBOX_STAGE3 action=add-src-to-address-list address-list=WINBOX_BLACKLIST address-list-timeout=10d comment="Blacklist 10d"`)
        lines.push(`/ip firewall filter add chain=input protocol=tcp dst-port=8291 connection-state=new src-address-list=WINBOX_STAGE2 action=add-src-to-address-list address-list=WINBOX_STAGE3 address-list-timeout=1m comment="Stage 3"`)
        lines.push(`/ip firewall filter add chain=input protocol=tcp dst-port=8291 connection-state=new src-address-list=WINBOX_STAGE1 action=add-src-to-address-list address-list=WINBOX_STAGE2 address-list-timeout=1m comment="Stage 2"`)
        lines.push(`/ip firewall filter add chain=input protocol=tcp dst-port=8291 connection-state=new action=add-src-to-address-list address-list=WINBOX_STAGE1 address-list-timeout=1m comment="Stage 1"`)
      }

      if (secProtectSsh) {
        lines.push(``)
        lines.push(`# --- Anti Bruteforce SSH (Port 22) ---`)
        lines.push(`/ip firewall filter add chain=input protocol=tcp dst-port=22 src-address-list=SSH_BLACKLIST action=drop comment="Drop SSH Blacklist"`)
        lines.push(`/ip firewall filter add chain=input protocol=tcp dst-port=22 connection-state=new src-address-list=SSH_STAGE3 action=add-src-to-address-list address-list=SSH_BLACKLIST address-list-timeout=10d comment="SSH Blacklist"`)
        lines.push(`/ip firewall filter add chain=input protocol=tcp dst-port=22 connection-state=new src-address-list=SSH_STAGE2 action=add-src-to-address-list address-list=SSH_STAGE3 address-list-timeout=1m comment="SSH Stage 3"`)
        lines.push(`/ip firewall filter add chain=input protocol=tcp dst-port=22 connection-state=new src-address-list=SSH_STAGE1 action=add-src-to-address-list address-list=SSH_STAGE2 address-list-timeout=1m comment="SSH Stage 2"`)
        lines.push(`/ip firewall filter add chain=input protocol=tcp dst-port=22 connection-state=new action=add-src-to-address-list address-list=SSH_STAGE1 address-list-timeout=1m comment="SSH Stage 1"`)
      }

      if (secDropPortScanner) {
        lines.push(``)
        lines.push(`# --- Deteksi & Drop Port Scanner (PSD) ---`)
        lines.push(`/ip firewall filter add chain=input protocol=tcp psd=21,3s,3,1 action=add-src-to-address-list address-list=PORT_SCANNERS address-list-timeout=14d comment="Detect Port Scanner"`)
        lines.push(`/ip firewall filter add chain=input src-address-list=PORT_SCANNERS action=drop comment="Drop Port Scanners"`)
      }

      if (secDropDnsWan) {
        lines.push(``)
        lines.push(`# --- Tutup Port 53 DNS dari WAN ---`)
        lines.push(`/ip firewall filter add chain=input in-interface=${secWanInterface} protocol=udp dst-port=53 action=drop comment="Drop DNS UDP WAN"`)
        lines.push(`/ip firewall filter add chain=input in-interface=${secWanInterface} protocol=tcp dst-port=53 action=drop comment="Drop DNS TCP WAN"`)
      }

      if (secDropBogonWan) {
        lines.push(``)
        lines.push(`# --- Drop Bogon / Martian IPs (RAW Firewall) ---`)
        lines.push(`/ip firewall raw add chain=prerouting in-interface=${secWanInterface} src-address=10.0.0.0/8 action=drop comment="Drop Bogon 10/8"`)
        lines.push(`/ip firewall raw add chain=prerouting in-interface=${secWanInterface} src-address=172.16.0.0/12 action=drop comment="Drop Bogon 172.16/12"`)
        lines.push(`/ip firewall raw add chain=prerouting in-interface=${secWanInterface} src-address=192.168.0.0/16 action=drop comment="Drop Bogon 192.168/16"`)
      }
    } else if (selectedTool === "hotspot_pppoe") {
      lines.push(`# --- Setup Hotspot & PPPoE Server All-in-One ---`)
      lines.push(`/interface bridge add name=${setupLanName} comment="Bridge LAN"`)
      lines.push(`/ip address add address=${setupLanIp} interface=${setupLanName} comment="Gateway LAN"`)
      lines.push(`/ip pool add name=dhcp_pool_hotspot ranges=${setupDhcpRange}`)
      lines.push(`/ip dhcp-server add name=dhcp_hotspot interface=${setupLanName} address-pool=dhcp_pool_hotspot disabled=no`)
      lines.push(`/ip dhcp-server network add address=${setupLanIp.split('/')[0].replace(/\.\d+$/, '.0')}/24 gateway=${setupLanIp.split('/')[0]} dns-server=8.8.8.8,1.1.1.1`)
      lines.push(`/ip hotspot profile add name=hsprof1 dns-name="${setupDnsName}" hotspot-address=${setupLanIp.split('/')[0]} login-by=http-chap,cookie`)
      lines.push(`/ip hotspot add name=hotspot1 interface=${setupLanName} address-pool=dhcp_pool_hotspot profile=hsprof1 disabled=no`)
      if (setupEnablePppoe) {
        lines.push(``)
        lines.push(`/ip pool add name=pool_pppoe ranges=${setupPppoePool}`)
        lines.push(`/ppp profile add name=pppoe_profile local-address=${setupLanIp.split('/')[0]} remote-address=pool_pppoe dns-server=8.8.8.8,1.1.1.1 change-tcp-mss=yes use-encryption=yes`)
        lines.push(`/interface pppoe-server server add service-name=NODERA-PPPOE interface=${setupLanName} default-profile=pppoe_profile one-session-per-host=yes max-mtu=1492 max-mru=1492 disabled=no`)
      }
      lines.push(``)
      lines.push(`/ip firewall nat add chain=srcnat out-interface=ether1-WAN action=masquerade comment="NAT Internet"`)
    } else if (selectedTool === "port_forward") {
      lines.push(`# --- DST-NAT Port Forwarding ---`)
      lines.push(`/ip firewall nat add chain=dstnat in-interface=${natInWan} protocol=${natProtocol} dst-port=${natDstPort} action=dst-nat to-addresses=${natToIp} to-ports=${natToPort} comment="${natComment}"`)
      lines.push(`/ip firewall filter add chain=forward protocol=${natProtocol} dst-port=${natToPort} action=accept comment="Allow Forward ${natComment}"`)
    } else if (selectedTool === "burst_qos") {
      const upNum = parseInt(burstMaxUpload) || 10
      const downNum = parseInt(burstMaxDownload) || 20
      const burstUp = Math.round(upNum * burstMultiplier) + "M"
      const burstDown = Math.round(downNum * burstMultiplier) + "M"
      const threshUp = Math.round(upNum * 0.75) + "M"
      const threshDown = Math.round(downNum * 0.75) + "M"

      lines.push(`# --- Simple Queue Burst QoS Anti-Lag ---`)
      lines.push(`/queue simple add name="BURST_QOS_CLIENT" target="${burstTargetIp}" max-limit=${burstMaxUpload}/${burstMaxDownload} burst-limit=${burstUp}/${burstDown} burst-threshold=${threshUp}/${threshDown} burst-time=${burstDuration}s/${burstDuration}s queue=pcq-upload-default/pcq-download-default priority=8/8 comment="Auto Burst QoS"`)
    }

    return lines.join("\n")
  }, [
    selectedTool, rosVersion,
    lbIspCount, lbLanInterface, lbWan1Name, lbWan1Gw, lbWan2Name, lbWan2Gw, lbWan3Name, lbWan3Gw, lbWan4Name, lbWan4Gw,
    pbrGameWan, pbrGameGw, pbrStreamWan, pbrStreamGw,
    secWanInterface, secProtectWinbox, secProtectSsh, secDropPortScanner, secDropDnsWan, secDropBogonWan,
    setupLanName, setupLanIp, setupDhcpRange, setupDnsName, setupEnablePppoe, setupPppoePool,
    natProtocol, natDstPort, natToIp, natToPort, natInWan, natComment,
    burstTargetIp, burstMaxUpload, burstMaxDownload, burstMultiplier, burstDuration
  ])

  const parsedCommands = useMemo(() => {
    return generatedScript
      .split("\n")
      .map((l) => l.trim())
      .filter((l) => l.length > 0 && !l.startsWith("#") && !l.startsWith("//"))
  }, [generatedScript])

  const handleCopy = () => {
    navigator.clipboard.writeText(generatedScript)
    setCopied(true)
    setTimeout(() => setCopied(false), 2000)
  }

  const handleDownloadRsc = () => {
    const el = document.createElement("a")
    const file = new Blob([generatedScript], { type: "text/plain" })
    el.href = URL.createObjectURL(file)
    el.download = `nodera_${selectedTool}_${rosVersion}.rsc`
    document.body.appendChild(el)
    el.click()
    document.body.removeChild(el)
  }

  const handleTestConnection = async () => {
    setIsTestingConn(true)
    setConnStatus("idle")
    setConnMessage("")
    setRouterInfo(null)

    try {
      const payload: any = {}
      if (registeredRouters.length > 0 && selectedRouterId) {
        payload.router_id = selectedRouterId
      } else {
        payload.host = manualHost
        payload.port = parseInt(manualPort) || 8728
        payload.username = manualUser
        payload.password = manualPass
      }

      const res = await axios.post("/api/tools/mikrotik/test-connection", payload)
      if (res.data.success) {
        setConnStatus("connected")
        setConnMessage(res.data.message)
        setRouterInfo(res.data.router_info)
      } else {
        setConnStatus("failed")
        setConnMessage(res.data.message || "Koneksi gagal.")
      }
    } catch (err: any) {
      setConnStatus("failed")
      setConnMessage(err?.response?.data?.message || err?.message || "Gagal menghubungi router.")
    } finally {
      setIsTestingConn(false)
    }
  }

  const handleExecuteBatch = async () => {
    if (parsedCommands.length === 0) return
    setIsExecuting(true)
    setStepMode(false)

    try {
      const payload: any = { commands: parsedCommands }
      if (registeredRouters.length > 0 && selectedRouterId) {
        payload.router_id = selectedRouterId
      } else {
        payload.host = manualHost
        payload.port = parseInt(manualPort) || 8728
        payload.username = manualUser
        payload.password = manualPass
      }

      const res = await axios.post("/api/tools/mikrotik/execute-batch", payload)
      if (res.data.results) {
        setExecutionResults(res.data.results)
      }
    } catch (err: any) {
      // Error
    } finally {
      setIsExecuting(false)
    }
  }

  const handleExecuteStep = async () => {
    if (currentStepIndex >= parsedCommands.length) return
    const cmd = parsedCommands[currentStepIndex]
    setIsExecuting(true)

    try {
      const payload: any = { command: cmd }
      if (registeredRouters.length > 0 && selectedRouterId) {
        payload.router_id = selectedRouterId
      } else {
        payload.host = manualHost
        payload.port = parseInt(manualPort) || 8728
        payload.username = manualUser
        payload.password = manualPass
      }

      const res = await axios.post("/api/tools/mikrotik/execute-command", payload)
      setExecutionResults((prev) => [
        ...prev,
        { line: currentStepIndex + 1, command: cmd, status: res.data.success ? "success" : "failed", message: res.data.message },
      ])
      setCurrentStepIndex((prev) => prev + 1)
    } catch (err: any) {
      setExecutionResults((prev) => [
        ...prev,
        { line: currentStepIndex + 1, command: cmd, status: "failed", message: err?.message || "Gagal" },
      ])
      setCurrentStepIndex((prev) => prev + 1)
    } finally {
      setIsExecuting(false)
    }
  }

  if (!isOpen) return null

  const activeToolInfo = MIKROTIK_TOOLS_LIST.find((t) => t.id === selectedTool) || MIKROTIK_TOOLS_LIST[0]
  const ActiveIcon = activeToolInfo.icon

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-2 sm:p-4 bg-black/85 backdrop-blur-xl animate-in fade-in duration-200">
      <div className="relative w-full max-w-6xl max-h-[92vh] flex flex-col rounded-3xl bg-[#080E15] border border-cyan-500/30 shadow-[0_0_50px_rgba(0,229,255,0.15)] overflow-hidden">
        {/* ==================== MODAL HEADER ==================== */}
        <div className="bg-[#0B141F] px-5 sm:px-6 py-4 border-b border-white/10 flex items-center justify-between shrink-0">
          <div className="flex items-center gap-3">
            <div className="w-10 h-10 rounded-2xl bg-gradient-to-br from-cyan-500 to-blue-600 p-0.5 shadow-lg shadow-cyan-500/20 flex items-center justify-center">
              <ActiveIcon className="w-5 h-5 text-white" />
            </div>
            <div>
              <div className="flex items-center gap-2">
                <h3 className="font-extrabold text-base sm:text-lg text-white">{activeToolInfo.title}</h3>
                <span className="text-[10px] font-bold px-2 py-0.5 rounded-full bg-cyan-500/10 text-cyan-400 border border-cyan-500/30">
                  {activeToolInfo.badge}
                </span>
              </div>
              <p className="text-xs text-slate-400 hidden sm:block">{activeToolInfo.desc}</p>
            </div>
          </div>

          <div className="flex items-center gap-3">
            {/* Version Toggle Pill */}
            <div className="flex items-center bg-[#05080C] p-1 rounded-xl border border-white/10">
              <button
                onClick={() => setRosVersion("v7")}
                className={cn(
                  "px-2.5 py-1 text-xs font-bold rounded-lg transition-all",
                  rosVersion === "v7"
                    ? "bg-gradient-to-r from-cyan-500 to-blue-600 text-white shadow"
                    : "text-slate-400 hover:text-white"
                )}
              >
                ROS v7
              </button>
              <button
                onClick={() => setRosVersion("v6")}
                className={cn(
                  "px-2.5 py-1 text-xs font-bold rounded-lg transition-all",
                  rosVersion === "v6"
                    ? "bg-gradient-to-r from-indigo-500 to-purple-600 text-white shadow"
                    : "text-slate-400 hover:text-white"
                )}
              >
                ROS v6
              </button>
            </div>

            {/* Close Button */}
            <button
              onClick={onClose}
              className="p-2 rounded-full bg-white/5 hover:bg-white/10 text-slate-400 hover:text-white transition-colors"
            >
              <X className="w-5 h-5" />
            </button>
          </div>
        </div>

        {/* ==================== HORIZONTAL TOOL QUICK SWITCHER ==================== */}
        <div className="bg-[#050A10] px-4 py-2 border-b border-white/5 flex items-center gap-2 overflow-x-auto scrollbar-none shrink-0">
          {MIKROTIK_TOOLS_LIST.map((tool) => {
            const Icon = tool.icon
            const isSelected = selectedTool === tool.id
            return (
              <button
                key={tool.id}
                onClick={() => {
                  setSelectedTool(tool.id)
                  setExecutionResults([])
                  setStepMode(false)
                }}
                className={cn(
                  "flex items-center gap-2 px-3 py-1.5 rounded-lg text-xs font-bold whitespace-nowrap transition-all border",
                  isSelected
                    ? "bg-cyan-500/15 border-cyan-500 text-cyan-300 shadow-sm"
                    : "bg-white/[0.02] border-white/5 text-slate-400 hover:text-white hover:bg-white/[0.05]"
                )}
              >
                <Icon className={cn("w-3.5 h-3.5", isSelected ? "text-cyan-400" : "text-slate-500")} />
                <span>{tool.title.split(" (")[0]}</span>
              </button>
            )
          })}
        </div>

        {/* ==================== MODAL BODY 2-COLUMN WORKBENCH ==================== */}
        <div className="flex-1 grid grid-cols-1 lg:grid-cols-12 overflow-y-auto p-4 sm:p-6 gap-6 scrollbar-thin">
          {/* LEFT: PARAMETER CONFIGURATION (5 Cols) */}
          <div className="lg:col-span-5 bg-[#0B141F] border border-white/10 rounded-2xl p-5 shadow-xl space-y-4">
            <div className="flex items-center justify-between border-b border-white/10 pb-3">
              <span className="font-bold text-xs uppercase tracking-wider text-slate-300 flex items-center gap-1.5">
                <Sliders className="w-3.5 h-3.5 text-cyan-400" /> Parameter {activeToolInfo.title.split(" (")[0]}
              </span>
              <span className="text-[10px] font-mono text-cyan-400 bg-cyan-500/10 px-2 py-0.5 rounded border border-cyan-500/20">
                {rosVersion.toUpperCase()} SYNTAX
              </span>
            </div>

            {/* 1. LOAD BALANCING */}
            {selectedTool === "loadbalance" && (
              <div className="space-y-3.5 text-xs">
                <div>
                  <label className="block text-slate-400 font-semibold mb-1">Jumlah ISP WAN</label>
                  <div className="grid grid-cols-3 gap-2">
                    {[2, 3, 4].map((count) => (
                      <button
                        key={count}
                        type="button"
                        onClick={() => setLbIspCount(count as any)}
                        className={cn(
                          "py-2 rounded-xl font-bold border transition-all text-center",
                          lbIspCount === count
                            ? "bg-cyan-500/20 border-cyan-500 text-cyan-300 shadow-sm"
                            : "bg-[#05080C] border-white/10 text-slate-400 hover:text-white"
                        )}
                      >
                        {count} ISP
                      </button>
                    ))}
                  </div>
                </div>

                <div>
                  <label className="block text-slate-400 font-semibold mb-1">Interface LAN (Lokal Klien)</label>
                  <input
                    type="text"
                    value={lbLanInterface}
                    onChange={(e) => setLbLanInterface(e.target.value)}
                    placeholder="bridge-LAN"
                    className="w-full bg-[#05080C] border border-white/10 rounded-xl px-3 py-2 text-slate-200 focus:outline-none focus:border-cyan-500"
                  />
                </div>

                {/* WAN 1 & 2 */}
                <div className="p-3 rounded-xl bg-[#05080C] border border-white/5 space-y-2">
                  <span className="font-bold text-cyan-400 block">ISP 1 (Jalur Utama)</span>
                  <div className="grid grid-cols-2 gap-2">
                    <input
                      type="text"
                      value={lbWan1Name}
                      onChange={(e) => setLbWan1Name(e.target.value)}
                      placeholder="ether1-ISP1"
                      className="bg-[#0B141F] border border-white/10 rounded-lg px-2.5 py-1.5 text-slate-200"
                    />
                    <input
                      type="text"
                      value={lbWan1Gw}
                      onChange={(e) => setLbWan1Gw(e.target.value)}
                      placeholder="192.168.1.1"
                      className="bg-[#0B141F] border border-white/10 rounded-lg px-2.5 py-1.5 text-slate-200"
                    />
                  </div>
                </div>

                <div className="p-3 rounded-xl bg-[#05080C] border border-white/5 space-y-2">
                  <span className="font-bold text-blue-400 block">ISP 2 (Jalur Kedua)</span>
                  <div className="grid grid-cols-2 gap-2">
                    <input
                      type="text"
                      value={lbWan2Name}
                      onChange={(e) => setLbWan2Name(e.target.value)}
                      placeholder="ether2-ISP2"
                      className="bg-[#0B141F] border border-white/10 rounded-lg px-2.5 py-1.5 text-slate-200"
                    />
                    <input
                      type="text"
                      value={lbWan2Gw}
                      onChange={(e) => setLbWan2Gw(e.target.value)}
                      placeholder="192.168.2.1"
                      className="bg-[#0B141F] border border-white/10 rounded-lg px-2.5 py-1.5 text-slate-200"
                    />
                  </div>
                </div>

                {lbIspCount >= 3 && (
                  <div className="p-3 rounded-xl bg-[#05080C] border border-white/5 space-y-2">
                    <span className="font-bold text-purple-400 block">ISP 3</span>
                    <div className="grid grid-cols-2 gap-2">
                      <input
                        type="text"
                        value={lbWan3Name}
                        onChange={(e) => setLbWan3Name(e.target.value)}
                        placeholder="ether3-ISP3"
                        className="bg-[#0B141F] border border-white/10 rounded-lg px-2.5 py-1.5 text-slate-200"
                      />
                      <input
                        type="text"
                        value={lbWan3Gw}
                        onChange={(e) => setLbWan3Gw(e.target.value)}
                        placeholder="192.168.3.1"
                        className="bg-[#0B141F] border border-white/10 rounded-lg px-2.5 py-1.5 text-slate-200"
                      />
                    </div>
                  </div>
                )}
              </div>
            )}

            {/* 2. PISAH TRAFFIC GAME */}
            {selectedTool === "pbr_game" && (
              <div className="space-y-3.5 text-xs">
                <div className="p-3.5 rounded-xl bg-[#05080C] border border-white/5 space-y-2">
                  <span className="font-bold text-cyan-400 flex items-center gap-1.5">
                    <Gamepad2 className="w-4 h-4" /> Alokasi Jalur Game Online
                  </span>
                  <p className="text-[11px] text-slate-400">
                    Koneksi game online (ML, PUBG, FF, Valorant, Dota 2, Steam) akan dialokasikan ke ISP pilihan:
                  </p>
                  <select
                    value={pbrGameWan}
                    onChange={(e) => setPbrGameWan(e.target.value)}
                    className="w-full bg-[#0B141F] border border-white/10 rounded-lg px-3 py-2 text-slate-200"
                  >
                    <option value="ISP1">Arahkan ke ISP 1 (Jalur Utama / Ping Rendah)</option>
                    <option value="ISP2">Arahkan ke ISP 2</option>
                  </select>
                </div>

                <div>
                  <label className="block text-slate-400 font-semibold mb-1">Gateway IP ISP Game</label>
                  <input
                    type="text"
                    value={pbrGameGw}
                    onChange={(e) => setPbrGameGw(e.target.value)}
                    placeholder="192.168.1.1"
                    className="w-full bg-[#05080C] border border-white/10 rounded-xl px-3 py-2 text-slate-200"
                  />
                </div>
              </div>
            )}

            {/* 3. PISAH TRAFFIC STREAMING */}
            {selectedTool === "pbr_stream" && (
              <div className="space-y-3.5 text-xs">
                <div className="p-3.5 rounded-xl bg-[#05080C] border border-white/5 space-y-2">
                  <span className="font-bold text-blue-400 flex items-center gap-1.5">
                    <Tv className="w-4 h-4" /> Alokasi Jalur Streaming &amp; Video CDN
                  </span>
                  <p className="text-[11px] text-slate-400">
                    Traffic berat (YouTube, Netflix, TikTok) dialirkan ke jalur khusus agar tidak mengganggu browsing &amp; game:
                  </p>
                  <select
                    value={pbrStreamWan}
                    onChange={(e) => setPbrStreamWan(e.target.value)}
                    className="w-full bg-[#0B141F] border border-white/10 rounded-lg px-3 py-2 text-slate-200"
                  >
                    <option value="ISP2">Arahkan ke ISP 2 (Jalur Khusus Bandwidth Besar)</option>
                    <option value="ISP1">Arahkan ke ISP 1</option>
                  </select>
                </div>

                <div>
                  <label className="block text-slate-400 font-semibold mb-1">Gateway IP ISP Streaming</label>
                  <input
                    type="text"
                    value={pbrStreamGw}
                    onChange={(e) => setPbrStreamGw(e.target.value)}
                    placeholder="192.168.2.1"
                    className="w-full bg-[#05080C] border border-white/10 rounded-xl px-3 py-2 text-slate-200"
                  />
                </div>
              </div>
            )}

            {/* 4. SPEEDTEST BYPASS */}
            {selectedTool === "pbr_speedtest" && (
              <div className="space-y-3 text-xs">
                <div className="p-4 rounded-xl bg-[#05080C] border border-cyan-500/20 space-y-2 text-slate-300">
                  <span className="font-bold text-cyan-400 flex items-center gap-1.5">
                    <Gauge className="w-4 h-4" /> Prioritas Maksimal Speedtest
                  </span>
                  <p className="text-xs text-slate-300 leading-relaxed">
                    Script ini menandai koneksi port 8080 &amp; 5060 (Ookla Speedtest, Fast.com, NPerf) dan memberikan paket prioritas level 7 (Tinggi) sehingga hasil pengujian kecepatan pelanggan tampil optimal tanpa lag.
                  </p>
                </div>
              </div>
            )}

            {/* 5. HARDENING & ANTI-BRUTEFORCE */}
            {selectedTool === "security" && (
              <div className="space-y-2.5 text-xs">
                <div>
                  <label className="block text-slate-400 font-semibold mb-1">Interface WAN (Internet)</label>
                  <input
                    type="text"
                    value={secWanInterface}
                    onChange={(e) => setSecWanInterface(e.target.value)}
                    className="w-full bg-[#05080C] border border-white/10 rounded-xl px-3 py-2 text-slate-200"
                  />
                </div>
                <div className="p-3 rounded-xl bg-[#05080C] border border-white/5 space-y-2">
                  <label className="flex items-center gap-2 cursor-pointer">
                    <input
                      type="checkbox"
                      checked={secProtectWinbox}
                      onChange={(e) => setSecProtectWinbox(e.target.checked)}
                      className="rounded text-cyan-500 bg-slate-800 focus:ring-0"
                    />
                    <span>Anti-Bruteforce Winbox (Port 8291)</span>
                  </label>
                  <label className="flex items-center gap-2 cursor-pointer">
                    <input
                      type="checkbox"
                      checked={secProtectSsh}
                      onChange={(e) => setSecProtectSsh(e.target.checked)}
                      className="rounded text-cyan-500 bg-slate-800 focus:ring-0"
                    />
                    <span>Anti-Bruteforce SSH (Port 22)</span>
                  </label>
                  <label className="flex items-center gap-2 cursor-pointer">
                    <input
                      type="checkbox"
                      checked={secDropPortScanner}
                      onChange={(e) => setSecDropPortScanner(e.target.checked)}
                      className="rounded text-cyan-500 bg-slate-800 focus:ring-0"
                    />
                    <span>Deteksi &amp; Drop Port Scanner (PSD)</span>
                  </label>
                  <label className="flex items-center gap-2 cursor-pointer">
                    <input
                      type="checkbox"
                      checked={secDropDnsWan}
                      onChange={(e) => setSecDropDnsWan(e.target.checked)}
                      className="rounded text-cyan-500 bg-slate-800 focus:ring-0"
                    />
                    <span>Tutup DNS Port 53 dari WAN (Anti-DDoS)</span>
                  </label>
                </div>
              </div>
            )}

            {/* 6. HOTSPOT & PPPOE */}
            {selectedTool === "hotspot_pppoe" && (
              <div className="space-y-3 text-xs">
                <div className="grid grid-cols-2 gap-2">
                  <div>
                    <label className="block text-slate-400 mb-1">Bridge Name</label>
                    <input
                      type="text"
                      value={setupLanName}
                      onChange={(e) => setSetupLanName(e.target.value)}
                      className="w-full bg-[#05080C] border border-white/10 rounded-lg px-2.5 py-1.5 text-slate-200"
                    />
                  </div>
                  <div>
                    <label className="block text-slate-400 mb-1">IP Gateway LAN</label>
                    <input
                      type="text"
                      value={setupLanIp}
                      onChange={(e) => setSetupLanIp(e.target.value)}
                      className="w-full bg-[#05080C] border border-white/10 rounded-lg px-2.5 py-1.5 text-slate-200"
                    />
                  </div>
                </div>
                <div>
                  <label className="block text-slate-400 mb-1">DNS Name Hotspot</label>
                  <input
                    type="text"
                    value={setupDnsName}
                    onChange={(e) => setSetupDnsName(e.target.value)}
                    className="w-full bg-[#05080C] border border-white/10 rounded-lg px-2.5 py-1.5 text-slate-200"
                  />
                </div>
                <div className="p-3 rounded-xl bg-[#05080C] border border-white/5 space-y-2">
                  <label className="flex items-center gap-2 cursor-pointer">
                    <input
                      type="checkbox"
                      checked={setupEnablePppoe}
                      onChange={(e) => setSetupEnablePppoe(e.target.checked)}
                      className="rounded text-cyan-500 bg-slate-800 focus:ring-0"
                    />
                    <span>Aktifkan PPPoE Server di Bridge yang Sama</span>
                  </label>
                </div>
              </div>
            )}

            {/* 7. PORT FORWARDING */}
            {selectedTool === "port_forward" && (
              <div className="space-y-3 text-xs">
                <div className="grid grid-cols-2 gap-2">
                  <div>
                    <label className="block text-slate-400 mb-1">Protokol</label>
                    <select
                      value={natProtocol}
                      onChange={(e) => setNatProtocol(e.target.value as any)}
                      className="w-full bg-[#05080C] border border-white/10 rounded-lg px-2.5 py-1.5 text-slate-200"
                    >
                      <option value="tcp">TCP</option>
                      <option value="udp">UDP</option>
                    </select>
                  </div>
                  <div>
                    <label className="block text-slate-400 mb-1">Port Publik (Dst)</label>
                    <input
                      type="text"
                      value={natDstPort}
                      onChange={(e) => setNatDstPort(e.target.value)}
                      className="w-full bg-[#05080C] border border-white/10 rounded-lg px-2.5 py-1.5 text-slate-200"
                    />
                  </div>
                </div>
                <div className="grid grid-cols-2 gap-2">
                  <div>
                    <label className="block text-slate-400 mb-1">IP Lokal Target</label>
                    <input
                      type="text"
                      value={natToIp}
                      onChange={(e) => setNatToIp(e.target.value)}
                      className="w-full bg-[#05080C] border border-white/10 rounded-lg px-2.5 py-1.5 text-slate-200"
                    />
                  </div>
                  <div>
                    <label className="block text-slate-400 mb-1">Port Lokal</label>
                    <input
                      type="text"
                      value={natToPort}
                      onChange={(e) => setNatToPort(e.target.value)}
                      className="w-full bg-[#05080C] border border-white/10 rounded-lg px-2.5 py-1.5 text-slate-200"
                    />
                  </div>
                </div>
              </div>
            )}

            {/* 8. BURST QOS */}
            {selectedTool === "burst_qos" && (
              <div className="space-y-3 text-xs">
                <div>
                  <label className="block text-slate-400 mb-1">Target IP / Subnet</label>
                  <input
                    type="text"
                    value={burstTargetIp}
                    onChange={(e) => setBurstTargetIp(e.target.value)}
                    className="w-full bg-[#05080C] border border-white/10 rounded-lg px-3 py-2 text-slate-200"
                  />
                </div>
                <div className="grid grid-cols-2 gap-2">
                  <div>
                    <label className="block text-slate-400 mb-1">Max Upload</label>
                    <input
                      type="text"
                      value={burstMaxUpload}
                      onChange={(e) => setBurstMaxUpload(e.target.value)}
                      className="w-full bg-[#05080C] border border-white/10 rounded-lg px-2.5 py-1.5 text-slate-200"
                    />
                  </div>
                  <div>
                    <label className="block text-slate-400 mb-1">Max Download</label>
                    <input
                      type="text"
                      value={burstMaxDownload}
                      onChange={(e) => setBurstMaxDownload(e.target.value)}
                      className="w-full bg-[#05080C] border border-white/10 rounded-lg px-2.5 py-1.5 text-slate-200"
                    />
                  </div>
                </div>
              </div>
            )}
          </div>

          {/* RIGHT: SCRIPT REVIEW & API DIRECT RUNNER (7 Cols) */}
          <div className="lg:col-span-7 flex flex-col space-y-4">
            {/* Tab Bar: Script vs API Apply */}
            <div className="flex items-center justify-between border-b border-white/10 pb-2.5">
              <div className="flex items-center gap-2">
                <button
                  onClick={() => setActiveTab("script")}
                  className={cn(
                    "flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold transition-all",
                    activeTab === "script"
                      ? "bg-cyan-500/20 text-cyan-300 border border-cyan-500/30"
                      : "text-slate-400 hover:text-white"
                  )}
                >
                  <Terminal className="w-3.5 h-3.5" />
                  <span>Script Preview (.rsc)</span>
                </button>
                <button
                  onClick={() => setActiveTab("api_apply")}
                  className={cn(
                    "flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold transition-all",
                    activeTab === "api_apply"
                      ? "bg-cyan-500/20 text-cyan-300 border border-cyan-500/30"
                      : "text-slate-400 hover:text-white"
                  )}
                >
                  <Gauge className="w-3.5 h-3.5" />
                  <span>Terapkan via API (8728)</span>
                </button>
              </div>

              <div className="flex items-center gap-2">
                <button
                  onClick={handleCopy}
                  className="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-bold rounded-lg bg-white/5 hover:bg-white/10 text-slate-200 border border-white/10"
                >
                  {copied ? <Check className="w-3.5 h-3.5 text-green-400" /> : <Copy className="w-3.5 h-3.5" />}
                  <span>{copied ? "Tersalin" : "Salin"}</span>
                </button>
                <button
                  onClick={handleDownloadRsc}
                  className="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-bold rounded-lg bg-cyan-500/20 hover:bg-cyan-500/30 text-cyan-300 border border-cyan-500/30"
                >
                  <Download className="w-3.5 h-3.5" />
                  <span>.rsc</span>
                </button>
              </div>
            </div>

            {/* TAB 1: SCRIPT PREVIEW */}
            {activeTab === "script" && (
              <div className="flex-1 rounded-2xl bg-[#05080C] border border-white/10 p-4 font-mono text-xs text-slate-300 max-h-[380px] overflow-y-auto leading-relaxed scrollbar-thin">
                <pre className="whitespace-pre-wrap selection:bg-cyan-500/30">{generatedScript}</pre>
              </div>
            )}

            {/* TAB 2: DIRECT API APPLY */}
            {activeTab === "api_apply" && (
              <div className="flex-1 rounded-2xl bg-[#05080C] border border-cyan-500/30 p-4 space-y-4 overflow-y-auto max-h-[380px] scrollbar-thin">
                {/* Credentials */}
                <div className="space-y-3 text-xs">
                  {registeredRouters.length > 0 ? (
                    <div>
                      <label className="block text-slate-400 mb-1 font-semibold">Pilih Router MikroTik Terdaftar</label>
                      <select
                        value={selectedRouterId}
                        onChange={(e) => {
                          setSelectedRouterId(e.target.value)
                          setConnStatus("idle")
                        }}
                        className="w-full bg-[#0B141F] border border-white/10 rounded-xl px-3 py-2 text-slate-200"
                      >
                        {registeredRouters.map((r) => (
                          <option key={r.id} value={r.id}>
                            {r.name} — {r.host}:{r.port}
                          </option>
                        ))}
                      </select>
                    </div>
                  ) : (
                    <div className="grid grid-cols-2 gap-2">
                      <div className="col-span-2">
                        <label className="block text-slate-400 mb-1">Host / IP MikroTik</label>
                        <input
                          type="text"
                          value={manualHost}
                          onChange={(e) => setManualHost(e.target.value)}
                          placeholder="192.168.88.1"
                          className="w-full bg-[#0B141F] border border-white/10 rounded-lg px-2.5 py-1.5 text-slate-200"
                        />
                      </div>
                      <div>
                        <label className="block text-slate-400 mb-1">Username</label>
                        <input
                          type="text"
                          value={manualUser}
                          onChange={(e) => setManualUser(e.target.value)}
                          placeholder="admin"
                          className="w-full bg-[#0B141F] border border-white/10 rounded-lg px-2.5 py-1.5 text-slate-200"
                        />
                      </div>
                      <div className="relative">
                        <label className="block text-slate-400 mb-1">Password</label>
                        <input
                          type={showPass ? "text" : "password"}
                          value={manualPass}
                          onChange={(e) => setManualPass(e.target.value)}
                          placeholder="••••••"
                          className="w-full bg-[#0B141F] border border-white/10 rounded-lg px-2.5 py-1.5 text-slate-200 pr-8"
                        />
                        <button
                          type="button"
                          onClick={() => setShowPass(!showPass)}
                          className="absolute right-2.5 top-7 text-slate-400 hover:text-white"
                        >
                          {showPass ? <EyeOff className="w-3.5 h-3.5" /> : <Eye className="w-3.5 h-3.5" />}
                        </button>
                      </div>
                    </div>
                  )}

                  {/* Test Connection */}
                  <div className="flex items-center gap-3">
                    <button
                      onClick={handleTestConnection}
                      disabled={isTestingConn}
                      className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold bg-white/5 hover:bg-white/10 text-cyan-400 border border-cyan-500/30"
                    >
                      <RefreshCw className={cn("w-3 h-3", isTestingConn && "animate-spin")} />
                      <span>{isTestingConn ? "Menguji..." : "Tes Koneksi"}</span>
                    </button>
                    {connMessage && (
                      <span className={cn("text-xs font-semibold", connStatus === "connected" ? "text-green-400" : "text-red-400")}>
                        {connMessage}
                      </span>
                    )}
                  </div>
                </div>

                {/* Actions */}
                <div className="pt-3 border-t border-white/10 flex flex-wrap gap-2">
                  <button
                    onClick={handleExecuteBatch}
                    disabled={isExecuting || parsedCommands.length === 0}
                    className="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-bold uppercase tracking-wider bg-gradient-to-r from-cyan-500 to-blue-600 hover:from-cyan-400 hover:to-blue-500 text-slate-950 shadow-md shadow-cyan-500/20 disabled:opacity-50"
                  >
                    <Play className="w-3.5 h-3.5 fill-current" />
                    <span>{isExecuting && !stepMode ? "Sedang Menerapkan..." : "Terapkan Semua Sekaligus"}</span>
                  </button>

                  <button
                    onClick={() => {
                      setStepMode(true)
                      setCurrentStepIndex(0)
                      setExecutionResults([])
                    }}
                    disabled={isExecuting || parsedCommands.length === 0}
                    className="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-bold bg-[#0B141F] hover:bg-slate-800 text-white border border-white/10"
                  >
                    <Sliders className="w-3.5 h-3.5 text-cyan-400" />
                    <span>Terapkan 1 per 1</span>
                  </button>
                </div>

                {/* Step Mode UI */}
                {stepMode && (
                  <div className="p-3 rounded-xl bg-[#0B141F] border border-cyan-500/30 space-y-2 text-xs">
                    <div className="flex justify-between items-center text-slate-400">
                      <span className="font-bold text-cyan-400">
                        Langkah {Math.min(currentStepIndex + 1, parsedCommands.length)} dari {parsedCommands.length}
                      </span>
                      <button onClick={() => setStepMode(false)} className="text-slate-400 hover:text-white">
                        Tutup Mode Step
                      </button>
                    </div>

                    {currentStepIndex < parsedCommands.length ? (
                      <div className="p-2.5 rounded-lg bg-[#05080C] font-mono text-[11px] text-slate-200 break-all">
                        {parsedCommands[currentStepIndex]}
                      </div>
                    ) : (
                      <div className="p-2 rounded-lg bg-green-500/10 text-green-400 font-bold text-center">
                        Seluruh perintah telah dijalankan!
                      </div>
                    )}

                    {currentStepIndex < parsedCommands.length && (
                      <div className="flex gap-2">
                        <button
                          onClick={handleExecuteStep}
                          disabled={isExecuting}
                          className="px-3 py-1.5 rounded-lg text-xs font-bold bg-cyan-500 hover:bg-cyan-400 text-slate-950"
                        >
                          Jalankan Baris Ini
                        </button>
                        <button
                          onClick={() => setCurrentStepIndex((prev) => prev + 1)}
                          disabled={isExecuting}
                          className="px-3 py-1.5 rounded-lg text-xs font-semibold bg-white/5 hover:bg-white/10 text-slate-300"
                        >
                          Lewati (Skip)
                        </button>
                      </div>
                    )}
                  </div>
                )}

                {/* Execution Results */}
                {executionResults.length > 0 && (
                  <div className="space-y-1 max-h-36 overflow-y-auto bg-[#0B141F] p-2.5 rounded-xl text-[11px] font-mono scrollbar-thin">
                    {executionResults.map((r, idx) => (
                      <div key={idx} className="flex items-center gap-2">
                        {r.status === "success" ? (
                          <CheckCircle2 className="w-3.5 h-3.5 text-green-400 shrink-0" />
                        ) : (
                          <XCircle className="w-3.5 h-3.5 text-red-400 shrink-0" />
                        )}
                        <span className="text-slate-400">[{r.line}]</span>
                        <span className="text-slate-200 truncate">{r.command}</span>
                      </div>
                    ))}
                  </div>
                )}
              </div>
            )}
          </div>
        </div>
      </div>
    </div>
  )
}
