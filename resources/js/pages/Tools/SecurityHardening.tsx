import React, { useState, useMemo } from "react"
import ToolsLayout, { RegisteredRouter } from "@/layouts/ToolsLayout"
import { ShieldCheck } from "lucide-react"

export default function SecurityHardening({
  registeredRouters = [],
  company,
  appName,
}: {
  registeredRouters?: RegisteredRouter[]
  company?: any
  appName?: string
}) {
  const [rosVersion, setRosVersion] = useState<"v7" | "v6">("v7")
  const [wanInterface, setWanInterface] = useState<string>("ether1-WAN")
  const [protectWinbox, setProtectWinbox] = useState<boolean>(true)
  const [protectSsh, setProtectSsh] = useState<boolean>(true)
  const [dropPortScanner, setDropPortScanner] = useState<boolean>(true)
  const [dropDnsWan, setDropDnsWan] = useState<boolean>(true)
  const [dropBogonWan, setDropBogonWan] = useState<boolean>(true)
  const [dropInvalid, setDropInvalid] = useState<boolean>(true)

  const generatedScript = useMemo(() => {
    let lines: string[] = []

    lines.push(`# ================================================================`)
    lines.push(`# NODERA — ROUTEROS HARDENING & ANTI-BRUTEFORCE FIREWALL`)
    lines.push(`# Target OS    : RouterOS ${rosVersion.toUpperCase()}`)
    lines.push(`# WAN Interface: ${wanInterface}`)
    lines.push(`# Generated At : ${new Date().toLocaleString("id-ID")}`)
    lines.push(`# ================================================================`)
    lines.push(``)

    if (dropInvalid) {
      lines.push(`# --- 1. Drop Invalid Connections ---`)
      lines.push(`/ip firewall filter add chain=input connection-state=invalid action=drop comment="Drop Invalid Input"`)
      lines.push(`/ip firewall filter add chain=forward connection-state=invalid action=drop comment="Drop Invalid Forward"`)
      lines.push(`/ip firewall filter add chain=input connection-state=established,related action=accept comment="Accept Established/Related Input"`)
      lines.push(`/ip firewall filter add chain=forward connection-state=established,related action=accept comment="Accept Established/Related Forward"`)
      lines.push(``)
    }

    if (protectWinbox) {
      lines.push(`# --- 2. Anti-Bruteforce Winbox (Port 8291) ---`)
      lines.push(`/ip firewall filter add chain=input protocol=tcp dst-port=8291 src-address-list=WINBOX_BLACKLIST action=drop comment="Drop Winbox Blacklist"`)
      lines.push(`/ip firewall filter add chain=input protocol=tcp dst-port=8291 connection-state=new src-address-list=WINBOX_STAGE3 action=add-src-to-address-list address-list=WINBOX_BLACKLIST address-list-timeout=10d comment="Blacklist Winbox 10d"`)
      lines.push(`/ip firewall filter add chain=input protocol=tcp dst-port=8291 connection-state=new src-address-list=WINBOX_STAGE2 action=add-src-to-address-list address-list=WINBOX_STAGE3 address-list-timeout=1m comment="Stage 3 Winbox"`)
      lines.push(`/ip firewall filter add chain=input protocol=tcp dst-port=8291 connection-state=new src-address-list=WINBOX_STAGE1 action=add-src-to-address-list address-list=WINBOX_STAGE2 address-list-timeout=1m comment="Stage 2 Winbox"`)
      lines.push(`/ip firewall filter add chain=input protocol=tcp dst-port=8291 connection-state=new action=add-src-to-address-list address-list=WINBOX_STAGE1 address-list-timeout=1m comment="Stage 1 Winbox"`)
      lines.push(``)
    }

    if (protectSsh) {
      lines.push(`# --- 3. Anti-Bruteforce SSH (Port 22) ---`)
      lines.push(`/ip firewall filter add chain=input protocol=tcp dst-port=22 src-address-list=SSH_BLACKLIST action=drop comment="Drop SSH Blacklist"`)
      lines.push(`/ip firewall filter add chain=input protocol=tcp dst-port=22 connection-state=new src-address-list=SSH_STAGE3 action=add-src-to-address-list address-list=SSH_BLACKLIST address-list-timeout=10d comment="Blacklist SSH 10d"`)
      lines.push(`/ip firewall filter add chain=input protocol=tcp dst-port=22 connection-state=new src-address-list=SSH_STAGE2 action=add-src-to-address-list address-list=SSH_STAGE3 address-list-timeout=1m comment="Stage 3 SSH"`)
      lines.push(`/ip firewall filter add chain=input protocol=tcp dst-port=22 connection-state=new src-address-list=SSH_STAGE1 action=add-src-to-address-list address-list=SSH_STAGE2 address-list-timeout=1m comment="Stage 2 SSH"`)
      lines.push(`/ip firewall filter add chain=input protocol=tcp dst-port=22 connection-state=new action=add-src-to-address-list address-list=SSH_STAGE1 address-list-timeout=1m comment="Stage 1 SSH"`)
      lines.push(``)
    }

    if (dropPortScanner) {
      lines.push(`# --- 4. Deteksi & Drop Port Scanner (PSD) ---`)
      lines.push(`/ip firewall filter add chain=input protocol=tcp psd=21,3s,3,1 action=add-src-to-address-list address-list=PORT_SCANNERS address-list-timeout=14d comment="Detect Port Scanners"`)
      lines.push(`/ip firewall filter add chain=input src-address-list=PORT_SCANNERS action=drop comment="Drop Port Scanners"`)
      lines.push(``)
    }

    if (dropDnsWan) {
      lines.push(`# --- 5. Tutup DNS Port 53 dari WAN (Cegah Open Resolver DNS Amplification) ---`)
      lines.push(`/ip firewall filter add chain=input in-interface=${wanInterface} protocol=udp dst-port=53 action=drop comment="Drop DNS UDP WAN"`)
      lines.push(`/ip firewall filter add chain=input in-interface=${wanInterface} protocol=tcp dst-port=53 action=drop comment="Drop DNS TCP WAN"`)
      lines.push(``)
    }

    if (dropBogonWan) {
      lines.push(`# --- 6. Drop Bogon / Martian IPs via RAW Firewall ---`)
      lines.push(`/ip firewall raw add chain=prerouting in-interface=${wanInterface} src-address=10.0.0.0/8 action=drop comment="Drop Bogon 10/8 WAN"`)
      lines.push(`/ip firewall raw add chain=prerouting in-interface=${wanInterface} src-address=172.16.0.0/12 action=drop comment="Drop Bogon 172.16/12 WAN"`)
      lines.push(`/ip firewall raw add chain=prerouting in-interface=${wanInterface} src-address=192.168.0.0/16 action=drop comment="Drop Bogon 192.168/16 WAN"`)
      lines.push(`/ip firewall raw add chain=prerouting in-interface=${wanInterface} src-address=127.0.0.0/8 action=drop comment="Drop Loopback WAN"`)
      lines.push(`/ip firewall raw add chain=prerouting in-interface=${wanInterface} src-address=0.0.0.0/8 action=drop comment="Drop Self-Identification WAN"`)
    }

    return lines.join("\n")
  }, [rosVersion, wanInterface, protectWinbox, protectSsh, dropPortScanner, dropDnsWan, dropBogonWan, dropInvalid])

  return (
    <ToolsLayout
      currentToolId="security"
      title="Hardening &"
      titleGradient="Anti-Bruteforce Firewall"
      subtitle="Amankan router MikroTik dari serangan bruteforce login Winbox, SSH, port scanner, DNS Amplification DDoS, dan paket bogon palsu dari internet."
      badgeText="ROUTER SECURITY & HARDENING"
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
            <label className="block text-xs uppercase tracking-wider font-bold text-slate-300 mb-1.5">Interface WAN (Jalur Internet Publik)</label>
            <input
              type="text"
              value={wanInterface}
              onChange={(e) => setWanInterface(e.target.value)}
              placeholder="ether1-WAN"
              className="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2 text-slate-200 focus:outline-none focus:border-cyan-500/50 text-sm"
            />
          </div>
        </div>

        <div className="p-4 rounded-2xl bg-white/[0.02] border border-white/10 space-y-2.5">
          <label className="block text-xs uppercase tracking-wider font-bold text-slate-300 mb-1">Modul Proteksi Aktif:</label>

          <label className="flex items-center gap-3 cursor-pointer p-3 rounded-xl bg-white/[0.02] border border-white/5 hover:border-cyan-500/30 transition-all">
            <input
              type="checkbox"
              checked={protectWinbox}
              onChange={(e) => setProtectWinbox(e.target.checked)}
              className="rounded text-cyan-500 bg-slate-800 focus:ring-0"
            />
            <div>
              <span className="font-bold text-white text-xs block">Anti-Bruteforce Winbox (Port 8291)</span>
              <span className="text-[11px] text-slate-400">Blokir otomatis IP penyerang selama 10 hari setelah 3x gagal login.</span>
            </div>
          </label>

          <label className="flex items-center gap-3 cursor-pointer p-3 rounded-xl bg-white/[0.02] border border-white/5 hover:border-cyan-500/30 transition-all">
            <input
              type="checkbox"
              checked={protectSsh}
              onChange={(e) => setProtectSsh(e.target.checked)}
              className="rounded text-cyan-500 bg-slate-800 focus:ring-0"
            />
            <div>
              <span className="font-bold text-white text-xs block">Anti-Bruteforce SSH (Port 22)</span>
              <span className="text-[11px] text-slate-400">Blokir otomatis IP brute-force SSH selama 10 hari.</span>
            </div>
          </label>

          <label className="flex items-center gap-3 cursor-pointer p-3 rounded-xl bg-white/[0.02] border border-white/5 hover:border-cyan-500/30 transition-all">
            <input
              type="checkbox"
              checked={dropPortScanner}
              onChange={(e) => setDropPortScanner(e.target.checked)}
              className="rounded text-cyan-500 bg-slate-800 focus:ring-0"
            />
            <div>
              <span className="font-bold text-white text-xs block">Deteksi Port Scanner (PSD)</span>
              <span className="text-[11px] text-slate-400">Deteksi scanning Nmap / port scanner dan masukkan ke blacklist 14 hari.</span>
            </div>
          </label>

          <label className="flex items-center gap-3 cursor-pointer p-3 rounded-xl bg-white/[0.02] border border-white/5 hover:border-cyan-500/30 transition-all">
            <input
              type="checkbox"
              checked={dropDnsWan}
              onChange={(e) => setDropDnsWan(e.target.checked)}
              className="rounded text-cyan-500 bg-slate-800 focus:ring-0"
            />
            <div>
              <span className="font-bold text-white text-xs block">Tutup DNS Port 53 dari WAN</span>
              <span className="text-[11px] text-slate-400">Mencegah router dieksploitasi sebagai Open-Resolver DNS DDoS.</span>
            </div>
          </label>

          <label className="flex items-center gap-3 cursor-pointer p-3 rounded-xl bg-white/[0.02] border border-white/5 hover:border-cyan-500/30 transition-all">
            <input
              type="checkbox"
              checked={dropBogonWan}
              onChange={(e) => setDropBogonWan(e.target.checked)}
              className="rounded text-cyan-500 bg-slate-800 focus:ring-0"
            />
            <div>
              <span className="font-bold text-white text-xs block">Drop Bogon / IP Palsu via RAW</span>
              <span className="text-[11px] text-slate-400">Drop traffic IP private/invalid dari WAN tanpa membebani CPU conntrack.</span>
            </div>
          </label>
        </div>
      </div>
    </ToolsLayout>
  )
}
