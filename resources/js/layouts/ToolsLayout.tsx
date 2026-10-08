import React, { useState, useEffect } from "react"
import { Head, Link, usePage } from "@inertiajs/react"
import {
  Terminal,
  ChevronDown,
  Radio,
  Gamepad2,
  Tv,
  ShieldCheck,
  Globe,
  ArrowDown,
  Gauge,
  LogIn,
  Menu,
  X,
  Receipt,
  Mail,
  Phone,
  MapPin,
  Sliders,
  Copy,
  Download,
  Check,
  RefreshCw,
  Play,
  Eye,
  EyeOff,
  CheckCircle2,
  XCircle,
  ArrowRight,
  UserPlus,
  Building2,
  Router,
  Smartphone,
  Server,
  Activity,
} from "lucide-react"
import axios from "axios"
import { cn } from "@/lib/utils"
import LanguageSwitcher from "@/components/layout/language-switcher"
import { AppLayout } from "@/components/layout/app-layout"
import { adminSidebarItems, adminNavItems, adminBrand } from "@/lib/admin-nav"

export interface RegisteredRouter {
  id: number
  name: string
  host: string
  port: number
  username: string
  location?: string | null
}

export const TOOLS_NAV_ITEMS = [
  {
    id: "loadbalance",
    title: "Load Balancing (PCC Multi-ISP)",
    shortTitle: "Load Balancing",
    href: "/tools/loadbalance",
    icon: Radio,
    badge: "Multi-ISP",
    desc: "Bagi beban multi-ISP dinamis (2-10+ ISP) dengan failover otomatis.",
  },
  {
    id: "game",
    title: "Pisah Traffic Game Online",
    shortTitle: "Game Online",
    href: "/tools/game",
    icon: Gamepad2,
    badge: "Anti-Lag",
    desc: "Prioritas latensi rendah untuk ML, PUBG, FF, Valorant, Dota 2, Steam.",
  },
  {
    id: "stream",
    title: "Pisah Traffic Streaming & CDN",
    shortTitle: "Streaming CDN",
    href: "/tools/stream",
    icon: Tv,
    badge: "Video CDN",
    desc: "Alirkan YouTube, Netflix, TikTok ke jalur khusus bandwidth besar.",
  },
  {
    id: "speedtest",
    title: "Bypass Speedtest (Ookla)",
    shortTitle: "Speedtest",
    href: "/tools/speedtest",
    icon: Activity,
    badge: "Max Speed",
    desc: "Bypass limitasi QoS saat pengujian speedtest agar hasil maksimal.",
  },
  {
    id: "security",
    title: "Hardening & Anti-Bruteforce",
    shortTitle: "Hardening",
    href: "/tools/security",
    icon: ShieldCheck,
    badge: "RAW Filter",
    desc: "Proteksi Winbox, SSH, anti port scanner PSD, tutup DNS WAN & bogon.",
  },
  {
    id: "hotspot_pppoe",
    title: "Generator Hotspot & PPPoE",
    shortTitle: "Hotspot & PPPoE",
    href: "/tools/hotspot-pppoe",
    icon: Globe,
    badge: "Quick Setup",
    desc: "Otomasi Bridge, IP Pool, DHCP Server, PPPoE Profile & NAT Masquerade.",
  },
  {
    id: "port_forward",
    title: "DST-NAT Port Forwarding",
    shortTitle: "Port Forwarding",
    href: "/tools/port-forward",
    icon: ArrowDown,
    badge: "NAT Forward",
    desc: "Buka akses port publik ke CCTV, DVR, Web Server, atau Remote.",
  },
  {
    id: "burst_qos",
    title: "Burst QoS & Simple Queue",
    shortTitle: "Burst QoS",
    href: "/tools/burst-qos",
    icon: Gauge,
    badge: "Smooth QoS",
    desc: "Kalkulator burst limit & threshold otomatis untuk browsing responsif.",
  },
]

interface ToolsLayoutProps {
  currentToolId: string
  title: string
  titleGradient?: string
  subtitle: string
  badgeText: string
  rosVersion: "v7" | "v6"
  setRosVersion: (v: "v7" | "v6") => void
  generatedScript: string
  registeredRouters?: RegisteredRouter[]
  company?: { name?: string; phone?: string; email?: string; address?: string }
  appName?: string
  isAuthenticated?: boolean
  children: React.ReactNode
}

export default function ToolsLayout({
  currentToolId,
  title,
  titleGradient,
  subtitle,
  badgeText,
  rosVersion,
  setRosVersion,
  generatedScript,
  registeredRouters = [],
  company,
  appName = "NODERA",
  isAuthenticated,
  children,
}: ToolsLayoutProps) {
  const [isScrolled, setIsScrolled] = useState(false)
  const [mobileMenuOpen, setMobileMenuOpen] = useState(false)
  const [toolsDropdownOpen, setToolsDropdownOpen] = useState(false)
  const [isModalOpen, setIsModalOpen] = useState(false)
  const [copied, setCopied] = useState(false)
  const [activeTab, setActiveTab] = useState<"script" | "api_apply">("script")

  // API Execution State
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

  useEffect(() => {
    const handleScroll = () => setIsScrolled(window.scrollY > 40)
    window.addEventListener("scroll", handleScroll)
    return () => window.removeEventListener("scroll", handleScroll)
  }, [])

  const parsedCommands = React.useMemo(() => {
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
    el.download = `nodera_${currentToolId}_${rosVersion}.rsc`
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

  const pageProps = usePage<any>().props
  const isUserAuthenticated = Boolean(pageProps?.auth?.user || pageProps?.isAuthenticated || isAuthenticated)

  const renderRightWorkbench = (inAdmin: boolean) => (
    <div className={cn(
      "rounded-2xl transition-all space-y-5",
      inAdmin
        ? "bg-slate-900/70 border border-slate-800/90 p-4 sm:p-6 shadow-xl"
        : "bg-white/[0.03] border border-white/10 p-4 sm:p-6 lg:p-8 hover:border-white/20 shadow-2xl"
    )}>
      {/* Top Action Bar: Segmented Switch + Copy/Download buttons */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-800/80 pb-4">
        {/* Segmented Mode Selector */}
        <div className="inline-flex p-1 rounded-xl bg-slate-950/80 border border-slate-800/80 w-full sm:w-auto">
          <button
            type="button"
            onClick={() => setActiveTab("script")}
            className={cn(
              "flex-1 sm:flex-initial flex items-center justify-center gap-1.5 px-3.5 py-2 sm:py-1.5 rounded-lg text-xs font-bold transition-all",
              activeTab === "script"
                ? "bg-cyan-500 text-white shadow-md shadow-cyan-500/20"
                : "text-slate-400 hover:text-slate-200"
            )}
          >
            <Terminal className="w-3.5 h-3.5" />
            <span>Script Preview (.rsc)</span>
          </button>
          <button
            type="button"
            onClick={() => setActiveTab("api_apply")}
            className={cn(
              "flex-1 sm:flex-initial flex items-center justify-center gap-1.5 px-3.5 py-2 sm:py-1.5 rounded-lg text-xs font-bold transition-all",
              activeTab === "api_apply"
                ? "bg-cyan-500 text-white shadow-md shadow-cyan-500/20"
                : "text-slate-400 hover:text-slate-200"
            )}
          >
            <Gauge className="w-3.5 h-3.5" />
            <span>Terapkan via API</span>
          </button>
        </div>

        {/* Action Buttons: Copy & Download */}
        <div className="grid grid-cols-2 sm:flex items-center gap-2">
          <button
            type="button"
            onClick={handleCopy}
            className="inline-flex items-center justify-center gap-1.5 px-3.5 py-2 sm:py-1.5 text-xs font-bold rounded-xl bg-slate-800/70 hover:bg-slate-800 text-slate-200 border border-slate-700/60 active:scale-95 transition-all shadow-sm"
          >
            {copied ? <Check className="w-3.5 h-3.5 text-emerald-400" /> : <Copy className="w-3.5 h-3.5 text-slate-400" />}
            <span>{copied ? "Tersalin!" : "Salin Script"}</span>
          </button>
          <button
            type="button"
            onClick={handleDownloadRsc}
            className="inline-flex items-center justify-center gap-1.5 px-3.5 py-2 sm:py-1.5 text-xs font-bold rounded-xl bg-cyan-500/15 hover:bg-cyan-500/25 text-cyan-300 border border-cyan-500/30 active:scale-95 transition-all shadow-sm"
          >
            <Download className="w-3.5 h-3.5" />
            <span>Download .rsc</span>
          </button>
        </div>
      </div>

      {/* TAB 1: SCRIPT PREVIEW */}
      {activeTab === "script" && (
        <div className="relative rounded-xl bg-[#04070A] border border-slate-800/80 p-4 font-mono text-[11px] sm:text-xs text-slate-300 min-h-[380px] sm:min-h-[460px] max-h-[560px] overflow-y-auto leading-relaxed scrollbar-thin">
          <div className="sticky top-0 right-0 flex justify-end pb-2">
            <span className="text-[10px] font-sans font-bold px-2 py-0.5 rounded bg-slate-800/80 text-slate-400 border border-slate-700/50">
              {parsedCommands.length} Perintah MikroTik
            </span>
          </div>
          <pre className="whitespace-pre-wrap selection:bg-cyan-500/30 font-mono">{generatedScript}</pre>
        </div>
      )}

      {/* TAB 2: DIRECT API APPLY */}
      {activeTab === "api_apply" && (
        <div className="rounded-xl bg-[#04070A] border border-cyan-500/30 p-4 sm:p-5 space-y-4 min-h-[380px] sm:min-h-[460px] max-h-[560px] overflow-y-auto scrollbar-thin">
          {/* Router Selection / Manual Credentials */}
          <div className="space-y-3 text-xs">
            {registeredRouters.length > 0 ? (
              <div>
                <label className="block text-slate-300 mb-1.5 font-bold flex items-center justify-between">
                  <span>Pilih Router MikroTik Terdaftar</span>
                  <span className="text-[10px] text-cyan-400 font-normal">{registeredRouters.length} Router Tersedia</span>
                </label>
                <select
                  value={selectedRouterId}
                  onChange={(e) => {
                    setSelectedRouterId(e.target.value)
                    setConnStatus("idle")
                    setConnMessage("")
                    setRouterInfo(null)
                  }}
                  className="w-full bg-slate-950 border border-slate-700/70 rounded-xl px-3.5 py-2.5 text-slate-200 focus:outline-none focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500/20 text-xs sm:text-sm"
                >
                  {registeredRouters.map((r) => (
                    <option key={r.id} value={r.id} className="bg-[#0A1218] text-white">
                      {r.name} — {r.host}:{r.port} {r.location ? `(${r.location})` : ""}
                    </option>
                  ))}
                </select>
              </div>
            ) : (
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div className="sm:col-span-2">
                  <label className="block text-slate-300 mb-1 font-bold">Host / IP Publik MikroTik</label>
                  <input
                    type="text"
                    value={manualHost}
                    onChange={(e) => setManualHost(e.target.value)}
                    placeholder="192.168.88.1 atau vpn.nodera.net"
                    className="w-full bg-slate-950 border border-slate-700/70 rounded-xl px-3.5 py-2 text-slate-200 focus:outline-none focus:border-cyan-500 text-xs sm:text-sm"
                  />
                </div>
                <div>
                  <label className="block text-slate-300 mb-1 font-bold">Port API (Default 8728)</label>
                  <input
                    type="text"
                    value={manualPort}
                    onChange={(e) => setManualPort(e.target.value)}
                    placeholder="8728"
                    className="w-full bg-slate-950 border border-slate-700/70 rounded-xl px-3.5 py-2 text-slate-200 focus:outline-none focus:border-cyan-500 text-xs sm:text-sm"
                  />
                </div>
                <div>
                  <label className="block text-slate-300 mb-1 font-bold">Username</label>
                  <input
                    type="text"
                    value={manualUser}
                    onChange={(e) => setManualUser(e.target.value)}
                    placeholder="admin"
                    className="w-full bg-slate-950 border border-slate-700/70 rounded-xl px-3.5 py-2 text-slate-200 focus:outline-none focus:border-cyan-500 text-xs sm:text-sm"
                  />
                </div>
                <div className="sm:col-span-2 relative">
                  <label className="block text-slate-300 mb-1 font-bold">Password</label>
                  <input
                    type={showPass ? "text" : "password"}
                    value={manualPass}
                    onChange={(e) => setManualPass(e.target.value)}
                    placeholder="••••••"
                    className="w-full bg-slate-950 border border-slate-700/70 rounded-xl px-3.5 py-2 text-slate-200 pr-10 focus:outline-none focus:border-cyan-500 text-xs sm:text-sm"
                  />
                  <button
                    type="button"
                    onClick={() => setShowPass(!showPass)}
                    className="absolute right-3 top-8 text-slate-400 hover:text-white"
                  >
                    {showPass ? <EyeOff className="w-4 h-4" /> : <Eye className="w-4 h-4" />}
                  </button>
                </div>
              </div>
            )}

            {/* Test Connection Button & Status */}
            <div className="flex flex-wrap items-center gap-2.5 pt-1">
              <button
                type="button"
                onClick={handleTestConnection}
                disabled={isTestingConn}
                className="inline-flex items-center justify-center gap-2 px-4 py-2 rounded-xl text-xs font-bold bg-slate-800 hover:bg-slate-700 text-cyan-400 border border-cyan-500/30 transition-all active:scale-95 disabled:opacity-50"
              >
                <RefreshCw className={cn("w-3.5 h-3.5", isTestingConn && "animate-spin")} />
                <span>{isTestingConn ? "Menguji Koneksi..." : "Tes Koneksi API"}</span>
              </button>
              {connMessage && (
                <span className={cn(
                  "text-xs font-semibold px-3 py-1.5 rounded-lg border",
                  connStatus === "connected"
                    ? "bg-emerald-500/10 text-emerald-400 border-emerald-500/30"
                    : "bg-rose-500/10 text-rose-400 border-rose-500/30"
                )}>
                  {connMessage}
                </span>
              )}
            </div>

            {/* Router Hardware Info */}
            {routerInfo && (
              <div className="p-3 rounded-xl bg-slate-900/80 border border-emerald-500/30 grid grid-cols-3 gap-2 text-[11px] text-slate-300">
                <div><span className="text-slate-500 block">Board:</span> {routerInfo.board_name || "-"}</div>
                <div><span className="text-slate-500 block">ROS:</span> {routerInfo.version || "-"}</div>
                <div><span className="text-slate-500 block">CPU:</span> {routerInfo.cpu_load || "-"}</div>
              </div>
            )}
          </div>

          {/* Action Buttons: Batch Run & Step Mode */}
          <div className="pt-3 border-t border-slate-800/80 flex flex-col sm:flex-row items-stretch sm:items-center gap-2.5">
            <button
              type="button"
              onClick={handleExecuteBatch}
              disabled={isExecuting || parsedCommands.length === 0}
              className="flex-1 inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl text-xs font-bold uppercase tracking-wider bg-gradient-to-r from-cyan-500 to-blue-600 hover:from-cyan-400 hover:to-blue-500 text-white shadow-lg shadow-cyan-500/25 active:scale-[0.98] disabled:opacity-50 transition-all min-h-[40px]"
            >
              <Play className="w-4 h-4 fill-current" />
              <span>{isExecuting && !stepMode ? "Sedang Menerapkan..." : "Terapkan Semua Sekaligus"}</span>
            </button>

            <button
              type="button"
              onClick={() => {
                setStepMode(true)
                setCurrentStepIndex(0)
                setExecutionResults([])
              }}
              disabled={isExecuting || parsedCommands.length === 0}
              className="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl text-xs font-bold bg-slate-800/80 hover:bg-slate-800 text-slate-200 border border-slate-700/60 active:scale-[0.98] disabled:opacity-50 transition-all min-h-[40px]"
            >
              <Sliders className="w-3.5 h-3.5 text-cyan-400" />
              <span>Terapkan 1 per 1</span>
            </button>
          </div>

          {/* Step Mode UI */}
          {stepMode && (
            <div className="p-4 rounded-xl bg-slate-900/90 border border-cyan-500/40 space-y-3 text-xs">
              <div className="flex justify-between items-center text-slate-400">
                <span className="font-bold text-cyan-400">
                  Langkah {Math.min(currentStepIndex + 1, parsedCommands.length)} dari {parsedCommands.length}
                </span>
                <button type="button" onClick={() => setStepMode(false)} className="text-slate-400 hover:text-white text-[11px] underline">
                  Tutup Step Mode
                </button>
              </div>

              {currentStepIndex < parsedCommands.length ? (
                <div className="p-3 rounded-xl bg-[#060A0E] font-mono text-[11px] text-slate-200 break-all border border-slate-800">
                  {parsedCommands[currentStepIndex]}
                </div>
              ) : (
                <div className="p-3 rounded-xl bg-emerald-500/10 text-emerald-400 font-bold text-center border border-emerald-500/30">
                  Seluruh {parsedCommands.length} baris perintah berhasil dieksekusi!
                </div>
              )}

              {currentStepIndex < parsedCommands.length && (
                <div className="flex gap-2">
                  <button
                    type="button"
                    onClick={handleExecuteStep}
                    disabled={isExecuting}
                    className="flex-1 sm:flex-initial px-4 py-2 rounded-xl text-xs font-bold bg-cyan-500 hover:bg-cyan-400 text-white shadow"
                  >
                    Jalankan Baris Ini
                  </button>
                  <button
                    type="button"
                    onClick={() => setCurrentStepIndex((prev) => prev + 1)}
                    disabled={isExecuting}
                    className="px-4 py-2 rounded-xl text-xs font-semibold bg-slate-800 hover:bg-slate-700 text-slate-300"
                  >
                    Lewati (Skip)
                  </button>
                </div>
              )}
            </div>
          )}

          {/* Results Logs */}
          {executionResults.length > 0 && (
            <div className="space-y-1.5 max-h-48 overflow-y-auto bg-slate-950 p-3 rounded-xl text-[11px] font-mono border border-slate-800 scrollbar-thin">
              {executionResults.map((r, idx) => (
                <div key={idx} className="flex items-center gap-2">
                  {r.status === "success" ? (
                    <CheckCircle2 className="w-3.5 h-3.5 text-emerald-400 shrink-0" />
                  ) : (
                    <XCircle className="w-3.5 h-3.5 text-rose-400 shrink-0" />
                  )}
                  <span className="text-slate-500">[{r.line}]</span>
                  <span className="text-slate-200 truncate">{r.command}</span>
                </div>
              ))}
            </div>
          )}
        </div>
      )}
    </div>
  )

  // ==================== 1. ADMIN LOGGED-IN VIEW (INSIDE APPLAYOUT) ====================
  if (isUserAuthenticated) {
    return (
      <AppLayout
        title={`Studio Script & Tools — ${title}`}
        brand={adminBrand}
        sidebarItems={adminSidebarItems}
        navItems={adminNavItems}
      >
        <Head title={`Studio Script & Tools — ${title}`} />

        <div className="space-y-5">
          {/* Header Card with ROS Switcher */}
          <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 p-4 sm:p-5 rounded-2xl bg-slate-900/70 border border-slate-800/90 shadow-lg backdrop-blur-md">
            <div className="flex items-start sm:items-center gap-3">
              <div className="p-2.5 rounded-xl bg-cyan-500/10 text-cyan-400 border border-cyan-500/20 shrink-0">
                <Terminal className="w-5 h-5" />
              </div>
              <div className="min-w-0">
                <div className="flex flex-wrap items-center gap-2">
                  <h1 className="text-base sm:text-lg font-bold text-white tracking-wide">
                    {title} {titleGradient && <span className="text-cyan-400">{titleGradient}</span>}
                  </h1>
                  <span className="text-[10px] font-bold px-2 py-0.5 rounded-full bg-cyan-500/15 text-cyan-400 border border-cyan-500/30">
                    {badgeText}
                  </span>
                </div>
                <p className="text-xs text-slate-400 mt-0.5 line-clamp-2 sm:line-clamp-1">{subtitle}</p>
              </div>
            </div>

            {/* ROS Switcher Segmented Control */}
            <div className="grid grid-cols-2 sm:flex p-1 rounded-xl bg-slate-950/90 border border-slate-800/90 shrink-0 w-full sm:w-auto">
              <button
                type="button"
                onClick={() => setRosVersion("v7")}
                className={cn(
                  "px-3.5 py-2 sm:py-1.5 rounded-lg text-xs font-bold transition-all flex items-center justify-center gap-1.5",
                  rosVersion === "v7"
                    ? "bg-cyan-500 text-white shadow-md shadow-cyan-500/20"
                    : "text-slate-400 hover:text-slate-200"
                )}
              >
                <Terminal className="w-3.5 h-3.5" />
                <span>RouterOS v7.x</span>
              </button>
              <button
                type="button"
                onClick={() => setRosVersion("v6")}
                className={cn(
                  "px-3.5 py-2 sm:py-1.5 rounded-lg text-xs font-bold transition-all flex items-center justify-center gap-1.5",
                  rosVersion === "v6"
                    ? "bg-cyan-500 text-white shadow-md shadow-cyan-500/20"
                    : "text-slate-400 hover:text-slate-200"
                )}
              >
                <Terminal className="w-3.5 h-3.5" />
                <span>RouterOS v6.x</span>
              </button>
            </div>
          </div>

          {/* Horizontal Tool Selector Tabs */}
          <div className="flex items-center gap-2 overflow-x-auto pb-1.5 pt-0.5 scrollbar-thin flex-nowrap -mx-1 px-1">
            {TOOLS_NAV_ITEMS.map((tool) => {
              const Icon = tool.icon
              const isCurrent = currentToolId === tool.id
              return (
                <Link
                  key={tool.id}
                  href={tool.href}
                  className={cn(
                    "flex items-center gap-2 px-3.5 py-2 rounded-xl text-xs font-bold whitespace-nowrap transition-all border shrink-0 active:scale-95",
                    isCurrent
                      ? "bg-cyan-500 text-white border-cyan-400 shadow-md shadow-cyan-500/25"
                      : "bg-slate-900/70 hover:bg-slate-800/80 border-slate-800 text-slate-300 hover:text-white"
                  )}
                >
                  <Icon className={cn("w-3.5 h-3.5", isCurrent ? "text-white" : "text-cyan-400")} />
                  <span>{tool.shortTitle}</span>
                </Link>
              )
            })}
          </div>

          {/* 2-Column Workbench */}
          <div className="grid grid-cols-1 lg:grid-cols-12 gap-5 items-start pb-12">
            {/* Left: Configuration Form (5 cols) */}
            <div className="lg:col-span-5 rounded-2xl bg-slate-900/70 border border-slate-800/90 p-4 sm:p-6 space-y-4 shadow-xl">
              <div className="flex items-center justify-between border-b border-slate-800/80 pb-3">
                <span className="font-bold text-xs uppercase tracking-wider text-slate-200 flex items-center gap-2">
                  <Sliders className="w-4 h-4 text-cyan-400" />
                  <span>Parameter Konfigurasi</span>
                </span>
                <span className="text-[10px] font-mono font-bold text-cyan-400 bg-cyan-500/10 px-2 py-0.5 rounded-full border border-cyan-500/20">
                  ROS {rosVersion.toUpperCase()}
                </span>
              </div>
              {children}
            </div>

            {/* Right: Script Preview & Direct API Runner (7 cols) */}
            <div className="lg:col-span-7">
              {renderRightWorkbench(true)}
            </div>
          </div>
        </div>
      </AppLayout>
    )
  }

  // ==================== 2. PUBLIC MARKETING VIEW (STANDALONE VISITOR) ====================
  const canonicalToolSlug = currentToolId.replace(/_/g, "-")
  const pageCanonicalUrl = typeof window !== "undefined"
    ? `https://${window.location.host}/tools/${canonicalToolSlug}`
    : `https://dgtlnetsolution.com/tools/${canonicalToolSlug}`

  return (
    <div className="min-h-screen bg-[#060A0E] text-slate-100 selection:bg-cyan-500 selection:text-white font-sans antialiased overflow-x-hidden">
      <Head>
        <title>{`${title} — Generator Script MikroTik RouterOS v6 & v7 | ${appName}`}</title>
        <meta name="description" content={subtitle ? `${subtitle} — Generator script MikroTik otomatis untuk optimasi jaringan RT RW Net & ISP oleh ${appName}.` : "Generator script MikroTik RouterOS v6 & v7 gratis untuk Load Balancing, Game Anti-Lag, QoS Burst, dan Hardening."} />
        <meta name="keywords" content={`mikrotik ${currentToolId}, script mikrotik ${currentToolId}, generator script mikrotik, load balancing pcc, setting mikrotik rt rw net, nodera billing`} />
        <meta name="robots" content="index, follow, max-snippet:-1, max-image-preview:large, max-video-preview:-1" />
        <link rel="canonical" href={pageCanonicalUrl} />
        <meta property="og:title" content={`${title} — Generator Script MikroTik | ${appName}`} />
        <meta property="og:description" content={subtitle || "Generator script MikroTik gratis untuk optimasi jaringan ISP dan RT RW Net."} />
        <meta property="og:url" content={pageCanonicalUrl} />
        <meta property="og:type" content="website" />
        <meta name="twitter:card" content="summary_large_image" />
        <meta name="twitter:title" content={`${title} — Generator Script MikroTik | ${appName}`} />
        <meta name="twitter:description" content={subtitle || "Generator script MikroTik gratis untuk optimasi jaringan ISP dan RT RW Net."} />
      </Head>

      {/* ==================== TITAN NAVIGATION BAR (MATCHING LANDING) ==================== */}
      <nav
        className={cn(
          "fixed top-0 inset-x-0 z-50 transition-all duration-300",
          isScrolled
            ? "bg-[#060A0E]/90 backdrop-blur-md border-b border-white/10 shadow-2xl py-3.5"
            : "bg-transparent py-5"
        )}
      >
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex items-center justify-between">
          {/* Brand Logo */}
          <Link href="/" className="flex items-center gap-3 group">
            <div className="relative flex items-center justify-center w-10 h-10 rounded-xl bg-gradient-to-br from-cyan-500 to-indigo-500 p-0.5 group-hover:scale-105 transition-transform">
              <img src="/images/logo-white.png?v=36" alt="NODERA" className="w-full h-full object-contain rounded-[10px] bg-[#0A1218]" />
            </div>
            <span className="font-extrabold text-xl tracking-wider text-white">NODERA</span>
          </Link>

          {/* Desktop Nav Links */}
          <div className="hidden md:flex items-center gap-6 lg:gap-7 text-sm font-semibold tracking-wide text-slate-300">
            <Link href="/#home" className="hover:text-cyan-400 transition-colors">Beranda</Link>
            <Link href="/#services" className="hover:text-cyan-400 transition-colors">Solusi</Link>
            <Link href="/#features" className="hover:text-cyan-400 transition-colors">Fitur</Link>

            {/* Tools Dropdown Link */}
            <div
              className="relative"
              onMouseEnter={() => setToolsDropdownOpen(true)}
              onMouseLeave={() => setToolsDropdownOpen(false)}
            >
              <button
                type="button"
                onClick={() => setToolsDropdownOpen(!toolsDropdownOpen)}
                className="flex items-center gap-1 hover:text-cyan-400 transition-colors text-sm font-semibold tracking-wide text-cyan-400 py-1"
              >
                <span>Tools</span>
                <ChevronDown className={cn("w-3.5 h-3.5 transition-transform duration-200 opacity-70", toolsDropdownOpen && "rotate-180")} />
              </button>

              {/* Mega Dropdown Menu */}
              {toolsDropdownOpen && (
                <div className="absolute top-full left-1/2 -translate-x-1/2 mt-1 w-[520px] bg-[#0A1218]/95 backdrop-blur-2xl border border-cyan-500/30 rounded-3xl p-3 shadow-[0_20px_50px_rgba(0,0,0,0.8)] grid grid-cols-2 gap-2 animate-in fade-in slide-in-from-top-2 duration-200 z-50">
                  {TOOLS_NAV_ITEMS.map((tool) => {
                    const Icon = tool.icon
                    const isCurrent = currentToolId === tool.id
                    return (
                      <Link
                        key={tool.id}
                        href={tool.href}
                        className={cn(
                          "flex items-start gap-2.5 p-2.5 rounded-2xl transition-all group/item border",
                          isCurrent
                            ? "bg-cyan-500/15 border-cyan-500/40 text-cyan-300 shadow-sm"
                            : "bg-white/[0.03] hover:bg-cyan-500/10 border-white/5 hover:border-cyan-500/30 text-left"
                        )}
                      >
                        <div className="p-2 rounded-xl bg-cyan-500/10 text-cyan-400 group-hover/item:scale-110 transition-transform shrink-0">
                          <Icon className="w-4 h-4" />
                        </div>
                        <div className="flex-1 min-w-0">
                          <div className="flex items-center justify-between">
                            <span className={cn("font-bold text-xs truncate", isCurrent ? "text-cyan-300" : "text-white group-hover/item:text-cyan-300")}>
                              {tool.title.split(" (")[0]}
                            </span>
                            <span className="text-[9px] font-bold px-1.5 py-0.2 rounded bg-cyan-500/20 text-cyan-400">
                              {tool.badge}
                            </span>
                          </div>
                          <p className="text-[10px] text-slate-400 line-clamp-2 mt-0.5 leading-snug">
                            {tool.desc}
                          </p>
                        </div>
                      </Link>
                    )
                  })}
                </div>
              )}
            </div>

            <Link href="/#team" className="hover:text-cyan-400 transition-colors">Tim Kami</Link>
            <Link href="/#pricing" className="hover:text-cyan-400 transition-colors">Harga</Link>
            <Link href="/#faq" className="hover:text-cyan-400 transition-colors">FAQ</Link>
            <Link href="/#contact" className="hover:text-cyan-400 transition-colors">Kontak</Link>
          </div>

          {/* Right Action Buttons */}
          <div className="hidden lg:flex items-center gap-3">
            <LanguageSwitcher />
            <button
              onClick={() => setIsModalOpen(true)}
              className="inline-flex items-center gap-2 px-5 py-2.5 rounded-full text-xs font-bold uppercase tracking-wider bg-gradient-to-r from-cyan-500 to-blue-600 hover:from-cyan-400 hover:to-blue-500 text-white transition-all transform hover:-translate-y-0.5 active:translate-y-0 shadow-lg shadow-cyan-500/20"
            >
              <LogIn className="w-3.5 h-3.5" />
              <span>Mulai / Masuk Portal</span>
            </button>
          </div>

          {/* Mobile Menu Toggle */}
          <div className="flex md:hidden items-center gap-2">
            <LanguageSwitcher />
            <button
              onClick={() => setMobileMenuOpen(!mobileMenuOpen)}
              className="p-2 rounded-lg bg-white/5 border border-white/10 text-slate-300 hover:text-white"
            >
              {mobileMenuOpen ? <X className="w-6 h-6" /> : <Menu className="w-6 h-6" />}
            </button>
          </div>
        </div>

        {/* Mobile Dropdown Menu */}
        {mobileMenuOpen && (
          <div className="md:hidden bg-[#0A1218]/95 backdrop-blur-xl border-b border-white/10 px-6 py-6 space-y-4 animate-in slide-in-from-top-4 duration-200">
            <Link href="/#home" onClick={() => setMobileMenuOpen(false)} className="block text-base font-semibold text-slate-200 hover:text-cyan-400">Beranda</Link>
            <Link href="/#services" onClick={() => setMobileMenuOpen(false)} className="block text-base font-semibold text-slate-200 hover:text-cyan-400">Solusi</Link>
            <Link href="/#features" onClick={() => setMobileMenuOpen(false)} className="block text-base font-semibold text-slate-200 hover:text-cyan-400">Fitur</Link>

            {/* Mobile Tools Dropdown */}
            <div className="space-y-2">
              <button
                type="button"
                onClick={() => setToolsDropdownOpen(!toolsDropdownOpen)}
                className="w-full flex items-center justify-between text-base font-semibold text-slate-200 hover:text-cyan-400"
              >
                <span>Tools</span>
                <ChevronDown className={cn("w-4 h-4 transition-transform", toolsDropdownOpen && "rotate-180")} />
              </button>
              {toolsDropdownOpen && (
                <div className="grid grid-cols-1 gap-1.5 pl-2 animate-in fade-in duration-150">
                  {TOOLS_NAV_ITEMS.map((tool) => {
                    const Icon = tool.icon
                    const isCurrent = currentToolId === tool.id
                    return (
                      <Link
                        key={tool.id}
                        href={tool.href}
                        onClick={() => setMobileMenuOpen(false)}
                        className={cn(
                          "flex items-center justify-between p-2.5 rounded-lg text-xs font-semibold text-left border transition-all",
                          isCurrent
                            ? "bg-cyan-500/20 text-cyan-300 border-cyan-500/40"
                            : "bg-white/5 hover:bg-cyan-500/10 text-slate-300 hover:text-cyan-300 border-white/5"
                        )}
                      >
                        <span className="flex items-center gap-2">
                          <Icon className="w-3.5 h-3.5 text-cyan-400" />
                          <span>{tool.title}</span>
                        </span>
                        <span className="text-[9px] px-1.5 py-0.5 rounded bg-cyan-500/20 text-cyan-400 font-bold">
                          {tool.badge}
                        </span>
                      </Link>
                    )
                  })}
                </div>
              )}
            </div>

            <Link href="/#team" onClick={() => setMobileMenuOpen(false)} className="block text-base font-semibold text-slate-200 hover:text-cyan-400">Tim Kami</Link>
            <Link href="/#pricing" onClick={() => setMobileMenuOpen(false)} className="block text-base font-semibold text-slate-200 hover:text-cyan-400">Harga</Link>
            <Link href="/#faq" onClick={() => setMobileMenuOpen(false)} className="block text-base font-semibold text-slate-200 hover:text-cyan-400">FAQ</Link>
            <Link href="/#contact" onClick={() => setMobileMenuOpen(false)} className="block text-base font-semibold text-slate-200 hover:text-cyan-400">Kontak</Link>
            <div className="pt-4 border-t border-white/10 flex flex-col gap-3">
              <button
                onClick={() => { setMobileMenuOpen(false); setIsModalOpen(true) }}
                className="w-full py-3 rounded-xl bg-cyan-500 font-bold text-center text-white"
              >
                Mulai / Masuk Portal
              </button>
            </div>
          </div>
        )}
      </nav>

      {/* ==================== HERO SECTION ==================== */}
      <section className="relative pt-36 pb-12 md:pt-44 md:pb-16 overflow-hidden">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10 text-center">
          {/* Eyebrow Pill Badge */}
          <div className="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-cyan-500/10 border border-cyan-500/20 text-cyan-400 text-xs font-bold uppercase tracking-wider mb-6 animate-pulse">
            <Gauge className="h-3.5 w-3.5" />
            <span>{badgeText}</span>
          </div>

          {/* Grand Main Title */}
          <h1 className="text-3xl sm:text-5xl lg:text-6xl font-extrabold tracking-tight text-white max-w-5xl mx-auto leading-tight">
            {title}{" "}
            {titleGradient && (
              <span className="bg-gradient-to-r from-cyan-400 via-sky-300 to-indigo-300 bg-clip-text text-transparent block sm:inline">
                {titleGradient}
              </span>
            )}
          </h1>

          <p className="mt-4 text-base sm:text-lg text-slate-300 max-w-3xl mx-auto leading-relaxed">
            {subtitle}
          </p>

          {/* RouterOS Version Switcher Pill (Matching Pricing Toggle) */}
          <div className="mt-8 inline-flex p-1.5 rounded-full bg-white/5 border border-white/10">
            <button
              type="button"
              onClick={() => setRosVersion("v7")}
              className={cn(
                "px-6 py-2 rounded-full text-xs font-bold uppercase tracking-wider transition-all flex items-center gap-2",
                rosVersion === "v7"
                  ? "bg-cyan-500 text-white shadow-lg shadow-cyan-500/30"
                  : "text-slate-400 hover:text-white"
              )}
            >
              <Terminal className="w-3.5 h-3.5" />
              <span>RouterOS v7.x (Modern Table)</span>
            </button>
            <button
              type="button"
              onClick={() => setRosVersion("v6")}
              className={cn(
                "px-6 py-2 rounded-full text-xs font-bold uppercase tracking-wider transition-all flex items-center gap-2",
                rosVersion === "v6"
                  ? "bg-cyan-500 text-white shadow-lg shadow-cyan-500/30"
                  : "text-slate-400 hover:text-white"
              )}
            >
              <Terminal className="w-3.5 h-3.5" />
              <span>RouterOS v6.x (Classic Mark)</span>
            </button>
          </div>
        </div>
      </section>

      {/* ==================== HORIZONTAL TOOL SELECTOR PILLS ==================== */}
      <section className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mb-10">
        <div className="flex items-center gap-2 overflow-x-auto pb-2 scrollbar-none justify-start lg:justify-center flex-nowrap">
          {TOOLS_NAV_ITEMS.map((tool) => {
            const Icon = tool.icon
            const isCurrent = currentToolId === tool.id
            return (
              <Link
                key={tool.id}
                href={tool.href}
                className={cn(
                  "flex items-center gap-2 px-4 py-2.5 rounded-full text-xs font-bold whitespace-nowrap transition-all border shrink-0",
                  isCurrent
                    ? "bg-cyan-500 text-white border-cyan-400 shadow-lg shadow-cyan-500/30"
                    : "bg-white/[0.03] hover:bg-white/[0.08] border-white/10 text-slate-300 hover:text-white"
                )}
              >
                <Icon className={cn("w-3.5 h-3.5", isCurrent ? "text-white" : "text-cyan-400")} />
                <span>{tool.shortTitle}</span>
              </Link>
            )
          })}
        </div>
      </section>

      {/* ==================== MAIN WORKBENCH SECTION ==================== */}
      <section className="py-8 bg-[#080E14] relative border-t border-white/5">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pb-20">
          <div className="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
            {/* LEFT: TOOL CONFIGURATION FORM (5 Cols) */}
            <div className="lg:col-span-5 rounded-2xl bg-white/[0.03] border border-white/10 p-6 sm:p-8 hover:border-white/20 transition-all space-y-6">
              <div className="flex items-center justify-between border-b border-white/10 pb-4">
                <span className="font-extrabold text-sm uppercase tracking-wider text-white flex items-center gap-2">
                  <Sliders className="w-4 h-4 text-cyan-400" />
                  <span>Parameter Konfigurasi</span>
                </span>
                <span className="text-[10px] font-mono font-bold text-cyan-400 bg-cyan-500/10 px-2.5 py-1 rounded-full border border-cyan-500/20">
                  ROS {rosVersion.toUpperCase()}
                </span>
              </div>

              {/* Injected Tool Form */}
              {children}
            </div>

            {/* RIGHT: SCRIPT REVIEW & DIRECT API RUNNER (7 Cols) */}
            <div className="lg:col-span-7">
              {renderRightWorkbench(false)}
            </div>
          </div>
        </div>
      </section>

      {/* ==================== TECHNICAL GUIDE & PROGRAMMATIC SEO SECTION ==================== */}
      <section className="py-14 bg-[#080D14] border-t border-slate-800/80">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-12">
          {/* Section Heading */}
          <div className="text-center max-w-3xl mx-auto space-y-3">
            <span className="text-[11px] font-mono font-bold tracking-widest text-cyan-400 uppercase bg-cyan-500/10 px-3 py-1 rounded-full border border-cyan-500/20">
              Dokumentasi &amp; Panduan Praktis
            </span>
            <h2 className="text-xl sm:text-2xl font-black text-white tracking-tight">
              Panduan Optimasi &amp; Implementasi Script MikroTik
            </h2>
            <p className="text-xs text-slate-400 leading-relaxed">
              Pelajari cara penerapan konfigurasi RouterOS yang aman, terstandarisasi, dan terintegrasi otomatis dengan sistem manajemen ISP NODERA.
            </p>
          </div>

          {/* 3 Technical Cards */}
          <div className="grid grid-cols-1 md:grid-cols-3 gap-6 text-xs">
            <div className="p-6 rounded-2xl bg-[#04070A] border border-slate-800/80 space-y-3">
              <div className="w-10 h-10 rounded-xl bg-cyan-500/10 text-cyan-400 flex items-center justify-center font-bold">
                01
              </div>
              <h3 className="text-sm font-bold text-white">Cara Pasang via Winbox Terminal</h3>
              <p className="text-slate-400 leading-relaxed">
                Salin baris perintah script yang dihasilkan, buka <strong>Winbox &gt; New Terminal</strong>, lalu paste baris perintah. Alternatifnya, simpan sebagai file <code className="text-cyan-400">.rsc</code> dan jalankan perintah <code className="text-cyan-400">/import file-name.rsc</code>.
              </p>
            </div>

            <div className="p-6 rounded-2xl bg-[#04070A] border border-slate-800/80 space-y-3">
              <div className="w-10 h-10 rounded-xl bg-indigo-500/10 text-indigo-400 flex items-center justify-center font-bold">
                02
              </div>
              <h3 className="text-sm font-bold text-white">Kompatibilitas RouterOS v6 &amp; v7</h3>
              <p className="text-slate-400 leading-relaxed">
                Generator ini mendukung arsitektur tabel routing baru RouterOS v7 (<code className="text-cyan-400">routing-table=...</code>) dan syntax legacy RouterOS v6. Pastikan memilih versi firmware yang sesuai dengan router Anda.
              </p>
            </div>

            <div className="p-6 rounded-2xl bg-[#04070A] border border-slate-800/80 space-y-3">
              <div className="w-10 h-10 rounded-xl bg-emerald-500/10 text-emerald-400 flex items-center justify-center font-bold">
                03
              </div>
              <h3 className="text-sm font-bold text-white">Otomasi Isolir &amp; Billing Terpadu</h3>
              <p className="text-slate-400 leading-relaxed">
                Ingin isolir otomatis saat pelanggan menunggak? Hubungkan router MikroTik ke platform <strong>NODERA Billing</strong> untuk auto isolir PPPoE, cetak thermal kasir, dan integrasi QRIS otomatis.
              </p>
            </div>
          </div>

          {/* CTA Banner */}
          <div className="p-8 rounded-3xl bg-gradient-to-r from-cyan-950/60 via-slate-900 to-indigo-950/60 border border-cyan-500/30 flex flex-col md:flex-row items-center justify-between gap-6">
            <div className="space-y-2 text-center md:text-left">
              <h3 className="text-lg sm:text-xl font-black text-white">
                Kelola Jaringan RT RW Net &amp; ISP Jadi Lebih Efisien
              </h3>
              <p className="text-xs text-slate-300 max-w-2xl leading-relaxed">
                Tingkatkan bisnis internet Anda dengan sistem billing cloud NODERA: integrasi MikroTik otomatis, WhatsApp broadcast tagihan, aplikasi kolektor Android, dan manajemen OLT.
              </p>
            </div>
            <div className="flex flex-wrap items-center gap-3 shrink-0">
              <Link
                href="/register"
                className="px-5 py-3 rounded-xl bg-gradient-to-r from-cyan-500 to-blue-600 hover:from-cyan-400 hover:to-blue-500 text-white font-bold text-xs shadow-lg shadow-cyan-500/20 transition-all hover:scale-105"
              >
                Daftar Uji Coba Gratis
              </Link>
              <Link
                href="/#features"
                className="px-5 py-3 rounded-xl bg-slate-800/80 hover:bg-slate-700 text-slate-200 font-bold text-xs border border-slate-700 transition-all"
              >
                Pelajari Fitur Billing
              </Link>
            </div>
          </div>
        </div>
      </section>

      {/* ==================== TITAN MULTI-COLUMN FOOTER (MATCHING LANDING) ==================== */}
      <footer className="py-16 bg-[#04070A] border-t border-white/10 text-slate-400 text-xs">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 grid grid-cols-1 md:grid-cols-4 gap-10">
          {/* Col 1: Brand */}
          <div className="space-y-4">
            <div className="flex items-center gap-3">
              <div className="w-8 h-8 rounded-lg bg-cyan-500/20 p-1 flex items-center justify-center">
                <img src="/images/logo-white.png?v=36" alt="NODERA" className="w-full h-full object-contain" />
              </div>
              <span className="font-extrabold text-lg text-white tracking-wider">NODERA</span>
            </div>
            <p className="leading-relaxed">
              Platform software all-in-one untuk manajemen tagihan, pengawasan perangkat jaringan, OLT/ONU, serta otomasi pelanggan ISP dan RT/RW Net.
            </p>
            <div className="text-[11px] text-slate-500">
              © {new Date().getFullYear()} NODERA INDONESIA. Hak Cipta Dilindungi.
            </div>
          </div>

          {/* Col 2: Modules */}
          <div className="space-y-3">
            <h4 className="font-bold text-white uppercase tracking-wider text-xs">Solusi Sistem</h4>
            <ul className="space-y-2">
              <li><Link href="/tools/loadbalance" className="hover:text-white transition-colors">Load Balancing (PCC Multi-ISP)</Link></li>
              <li><Link href="/tools/game" className="hover:text-white transition-colors">Pisah Traffic Game Online</Link></li>
              <li><Link href="/tools/stream" className="hover:text-white transition-colors">Pisah Traffic Streaming CDN</Link></li>
              <li><Link href="/tools/security" className="hover:text-white transition-colors">Hardening &amp; Anti-Bruteforce</Link></li>
              <li><Link href="/tools/burst-qos" className="hover:text-white transition-colors">Burst QoS &amp; Simple Queue</Link></li>
            </ul>
          </div>

          {/* Col 3: Navigation */}
          <div className="space-y-3">
            <h4 className="font-bold text-white uppercase tracking-wider text-xs">Navigasi</h4>
            <ul className="space-y-2">
              <li><Link href="/#home" className="hover:text-white transition-colors">Beranda</Link></li>
              <li><Link href="/#team" className="hover:text-white transition-colors">Tim Kami</Link></li>
              <li><Link href="/#pricing" className="hover:text-white transition-colors">Daftar Harga</Link></li>
              <li><Link href="/#faq" className="hover:text-white transition-colors">Tanya Jawab (FAQ)</Link></li>
              <li><Link href="/login" className="hover:text-white transition-colors">Portal Login</Link></li>
              <li><Link href="/pelanggan/login" className="hover:text-white transition-colors">Portal Pelanggan</Link></li>
            </ul>
          </div>

          {/* Col 4: Contact */}
          <div className="space-y-3">
            <h4 className="font-bold text-white uppercase tracking-wider text-xs">Bantuan &amp; Kontak</h4>
            <ul className="space-y-2.5">
              <li className="flex items-center gap-2">
                <Mail className="w-4 h-4 text-cyan-400" />
                <span>{company?.email || "support@dgtlnetsolution.com"}</span>
              </li>
              <li className="flex items-center gap-2">
                <Phone className="w-4 h-4 text-cyan-400" />
                <span>{company?.phone || "+62 851-5517-3544"}</span>
              </li>
              <li className="flex items-center gap-2">
                <MapPin className="w-4 h-4 text-cyan-400" />
                <span>{company?.address || "Indonesia"}</span>
              </li>
            </ul>
          </div>
        </div>
      </footer>

      {/* ==================== GET STARTED DUAL-PORTAL MODAL (MATCHING LANDING) ==================== */}
      {isModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-md animate-in fade-in duration-200">
          <div className="relative w-full max-w-lg rounded-3xl bg-[#0B131A] border border-white/15 p-6 sm:p-8 shadow-2xl space-y-6">
            {/* Close Button */}
            <button
              onClick={() => setIsModalOpen(false)}
              className="absolute top-5 right-5 p-2 rounded-full bg-white/5 hover:bg-white/10 text-slate-400 hover:text-white"
            >
              <X className="w-5 h-5" />
            </button>

            <div className="text-center space-y-2">
              <h3 className="text-2xl font-extrabold text-white">Selamat Datang di NODERA</h3>
              <p className="text-xs text-slate-400">Pilih akses masuk yang sesuai dengan peran Anda di sistem</p>
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              {/* Option 1: Admin ISP Login */}
              <Link
                href="/login"
                className="group p-5 rounded-2xl bg-white/5 hover:bg-cyan-500/10 border border-white/10 hover:border-cyan-500/40 transition-all flex flex-col items-center text-center space-y-3"
              >
                <div className="w-12 h-12 rounded-xl bg-cyan-500/20 text-cyan-400 flex items-center justify-center group-hover:scale-110 transition-transform">
                  <Building2 className="w-6 h-6" />
                </div>
                <div>
                  <h4 className="font-bold text-white text-sm group-hover:text-cyan-400">Portal Admin ISP</h4>
                  <p className="text-[11px] text-slate-400 mt-1">Dashboard manajemen tagihan, router MikroTik &amp; OLT</p>
                </div>
              </Link>

              {/* Option 2: Customer Portal */}
              <Link
                href="/pelanggan/login"
                className="group p-5 rounded-2xl bg-white/5 hover:bg-indigo-500/10 border border-white/10 hover:border-indigo-500/40 transition-all flex flex-col items-center text-center space-y-3"
              >
                <div className="w-12 h-12 rounded-xl bg-indigo-500/20 text-indigo-400 flex items-center justify-center group-hover:scale-110 transition-transform">
                  <Smartphone className="w-6 h-6" />
                </div>
                <div>
                  <h4 className="font-bold text-white text-sm group-hover:text-indigo-400">Portal Pelanggan</h4>
                  <p className="text-[11px] text-slate-400 mt-1">Cek invoice bulanan, bayar QRIS &amp; tiket gangguan</p>
                </div>
              </Link>
            </div>

            {/* Quick Register CTA */}
            <div className="pt-4 border-t border-white/10 text-center">
              <p className="text-xs text-slate-400">
                Belum memiliki akun ISP?{" "}
                <Link href="/register" className="text-cyan-400 hover:text-cyan-300 font-bold underline">
                  Daftar Uji Coba Gratis
                </Link>
              </p>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}
