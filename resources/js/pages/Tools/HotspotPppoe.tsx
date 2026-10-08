import React, { useState, useMemo } from "react"
import ToolsLayout, { RegisteredRouter } from "@/layouts/ToolsLayout"
import { Switch } from "@/components/tailadmin/Switch"
import { Globe } from "lucide-react"

export default function HotspotPppoe({
  registeredRouters = [],
  company,
  appName,
}: {
  registeredRouters?: RegisteredRouter[]
  company?: any
  appName?: string
}) {
  const [rosVersion, setRosVersion] = useState<"v7" | "v6">("v7")
  const [bridgeName, setBridgeName] = useState<string>("bridge-HOTSPOT")
  const [gatewayIp, setGatewayIp] = useState<string>("192.168.10.1/24")
  const [dhcpRange, setDhcpRange] = useState<string>("192.168.10.10-192.168.10.254")
  const [dnsName, setDnsName] = useState<string>("wifi.nodera.net")
  const [enablePppoe, setEnablePppoe] = useState<boolean>(true)
  const [pppoePool, setPppoePool] = useState<string>("10.10.0.10-10.10.0.254")
  const [wanInterface, setWanInterface] = useState<string>("ether1-WAN")

  const generatedScript = useMemo(() => {
    let lines: string[] = []
    const cleanIp = gatewayIp.split("/")[0]

    lines.push(`# ================================================================`)
    lines.push(`# NODERA — QUICK SETUP HOTSPOT & PPPOE SERVER ALL-IN-ONE`)
    lines.push(`# Target OS    : RouterOS ${rosVersion.toUpperCase()}`)
    lines.push(`# Bridge LAN   : ${bridgeName} (${gatewayIp})`)
    lines.push(`# Generated At : ${new Date().toLocaleString("id-ID")}`)
    lines.push(`# ================================================================`)
    lines.push(``)

    lines.push(`# --- 1. Bridge & Gateway IP LAN ---`)
    lines.push(`/interface bridge add name=${bridgeName} comment="Bridge Hotspot/LAN"`)
    lines.push(`/ip address add address=${gatewayIp} interface=${bridgeName} comment="Gateway ${bridgeName}"`)
    lines.push(``)

    lines.push(`# --- 2. IP Pool & DHCP Server Hotspot ---`)
    lines.push(`/ip pool add name=pool_hotspot ranges=${dhcpRange}`)
    lines.push(`/ip dhcp-server add name=dhcp_hotspot interface=${bridgeName} address-pool=pool_hotspot disabled=no`)
    lines.push(`/ip dhcp-server network add address=${cleanIp.replace(/\.\d+$/, ".0")}/24 gateway=${cleanIp} dns-server=8.8.8.8,1.1.1.1 comment="Network Hotspot"`)
    lines.push(``)

    lines.push(`# --- 3. Hotspot Server Profile & Instance ---`)
    lines.push(`/ip hotspot profile add name=hsprof1 dns-name="${dnsName}" hotspot-address=${cleanIp} login-by=http-chap,cookie`)
    lines.push(`/ip hotspot add name=hotspot1 interface=${bridgeName} address-pool=pool_hotspot profile=hsprof1 disabled=no`)
    lines.push(``)

    if (enablePppoe) {
      lines.push(`# --- 4. PPPoE Server di Bridge yang Sama ---`)
      lines.push(`/ip pool add name=pool_pppoe ranges=${pppoePool}`)
      lines.push(`/ppp profile add name=pppoe_profile local-address=${cleanIp} remote-address=pool_pppoe dns-server=8.8.8.8,1.1.1.1 change-tcp-mss=yes use-encryption=yes`)
      lines.push(`/interface pppoe-server server add service-name=NODERA-PPPOE interface=${bridgeName} default-profile=pppoe_profile one-session-per-host=yes max-mtu=1492 max-mru=1492 disabled=no`)
      lines.push(``)
    }

    lines.push(`# --- 5. NAT Internet Masquerade ---`)
    lines.push(`/ip firewall nat add chain=srcnat out-interface=${wanInterface} action=masquerade comment="NAT Internet Masquerade"`)

    return lines.join("\n")
  }, [rosVersion, bridgeName, gatewayIp, dhcpRange, dnsName, enablePppoe, pppoePool, wanInterface])

  return (
    <ToolsLayout
      currentToolId="hotspot_pppoe"
      title="Generator Hotspot &"
      titleGradient="PPPoE Server All-in-One"
      subtitle="Otomasi pembuatan Bridge LAN, DHCP Server, Hotspot Profile, PPPoE Server, IP Pool, dan NAT Masquerade dalam satu script ringkas."
      badgeText="HOTSPOT & PPPOE GENERATOR"
      rosVersion={rosVersion}
      setRosVersion={setRosVersion}
      generatedScript={generatedScript}
      registeredRouters={registeredRouters}
      company={company}
      appName={appName}
    >
      <div className="space-y-4 text-xs">
        <div className="p-4 rounded-2xl bg-white/[0.02] border border-white/10 space-y-3">
          <div className="grid grid-cols-2 gap-2.5">
            <div>
              <label className="block text-xs uppercase tracking-wider font-bold text-slate-300 mb-1.5">Nama Bridge LAN</label>
              <input
                type="text"
                value={bridgeName}
                onChange={(e) => setBridgeName(e.target.value)}
                className="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2 text-slate-200 text-sm"
              />
            </div>
            <div>
              <label className="block text-xs uppercase tracking-wider font-bold text-slate-300 mb-1.5">IP Gateway LAN</label>
              <input
                type="text"
                value={gatewayIp}
                onChange={(e) => setGatewayIp(e.target.value)}
                className="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2 text-slate-200 font-mono text-sm"
              />
            </div>
          </div>

          <div>
            <label className="block text-xs uppercase tracking-wider font-bold text-slate-300 mb-1.5">DHCP IP Range Hotspot</label>
            <input
              type="text"
              value={dhcpRange}
              onChange={(e) => setDhcpRange(e.target.value)}
              className="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2 text-slate-200 font-mono text-xs"
            />
          </div>

          <div>
            <label className="block text-xs uppercase tracking-wider font-bold text-slate-300 mb-1.5">DNS Name Hotspot Login</label>
            <input
              type="text"
              value={dnsName}
              onChange={(e) => setDnsName(e.target.value)}
              className="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2 text-slate-200 text-sm"
            />
          </div>

          <div>
            <label className="block text-xs uppercase tracking-wider font-bold text-slate-300 mb-1.5">Interface WAN (Internet)</label>
            <input
              type="text"
              value={wanInterface}
              onChange={(e) => setWanInterface(e.target.value)}
              className="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2 text-slate-200 text-sm"
            />
          </div>
        </div>

        <div className="p-4 rounded-2xl bg-white/[0.02] border border-white/10 space-y-3">
          <Switch
            checked={enablePppoe}
            onChange={setEnablePppoe}
            label="Aktifkan PPPoE Server di Bridge yang Sama"
          />

          {enablePppoe && (
            <div className="pt-2 border-t border-white/10">
              <label className="block text-[11px] text-slate-400 mb-1">PPPoE IP Pool Range</label>
              <input
                type="text"
                value={pppoePool}
                onChange={(e) => setPppoePool(e.target.value)}
                className="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2 text-slate-200 font-mono text-xs"
              />
            </div>
          )}
        </div>
      </div>
    </ToolsLayout>
  )
}
