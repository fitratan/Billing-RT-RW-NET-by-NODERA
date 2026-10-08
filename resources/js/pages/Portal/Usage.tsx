import React, { useState, useEffect } from "react"
import { AppLayout } from "@/components/layout/app-layout"
import {
  ArrowDown,
  ArrowUp,
  Gauge,
  Activity,
  Clock,
  KeyRound,
  Router,
  Save,
  CheckCircle2,
  AlertCircle,
  Users,
  Copy,
  Check,
  Eye,
  EyeOff,
  Radio,
  RotateCw,
  Power,
  Wifi,
  Smartphone,
  Laptop,
  ShieldCheck,
  X,
  Settings2,
} from "lucide-react"
import { PageProps } from "@/types"
import { portalNavItems, portalBrand } from "@/lib/portal-nav"
import { useForm, usePage, router } from "@inertiajs/react"
import { PortalSpeedtestModal } from "@/components/portal/PortalSpeedtestModal"

interface UsageData {
  username?: string
  address?: string
  uptime?: string
  uptime_human?: string
  download?: number
  upload?: number
  total?: number
  download_speed?: number
  upload_speed?: number
}

interface MonthlyUsageHistory {
  period: string
  label?: string
  download: number
  upload: number
  total: number
}

interface ConnectedHost {
  hostname?: string
  ip_address?: string
  mac_address?: string
  interface_type?: string
  is_active?: boolean
  lease_time_remaining?: number
}

interface OnuData {
  serial?: string
  model?: string
  manufacturer?: string
  lastInform?: string
  online?: boolean
  rxPower?: string | number | null
  txPower?: string | number | null
  temp?: string | number | null
  voltage?: string | number | null
  pppoeIP?: string
  pppoeUsername?: string
  ssid?: string
  wifiPassword?: string
  clients?: number | null
  uptime?: string | null
  uptime_formatted?: string | null
  hosts?: ConnectedHost[]
}

export default function PortalUsagePage({
  usageData,
  history = [],
  customer,
  onuData,
  hasAcsDevice = false,
  companyName = "NODERA",
}: PageProps<{
  usageData: UsageData | null
  history: MonthlyUsageHistory[]
  customer?: any
  onuData?: OnuData | null
  hasAcsDevice?: boolean
  companyName?: string
}>) {
  const { flash } = usePage<PageProps<{ flash?: { msg?: string | null; error?: string | null } }>>().props

  // Real-time live traffic states
  const [liveDownloadSpeed, setLiveDownloadSpeed] = useState<number>(usageData?.download_speed ?? 0)
  const [liveUploadSpeed, setLiveUploadSpeed] = useState<number>(usageData?.upload_speed ?? 0)
  const [liveDownloadBytes, setLiveDownloadBytes] = useState<number>(usageData?.download ?? 0)
  const [liveUploadBytes, setLiveUploadBytes] = useState<number>(usageData?.upload ?? 0)
  const [isLiveActive, setIsLiveActive] = useState<boolean>(true)

  // Live Auto-Polling RouterOS Traffic (3s interval)
  useEffect(() => {
    let isMounted = true

    const pollTraffic = async () => {
      if (document.hidden) return

      try {
        const res = await fetch("/portal/realtime-traffic", {
          headers: { Accept: "application/json" },
        })
        if (res.ok) {
          const data = await res.json()
          if (isMounted && data.success) {
            setLiveDownloadSpeed(Number(data.download_speed ?? 0))
            setLiveUploadSpeed(Number(data.upload_speed ?? 0))
            if (data.download !== undefined) setLiveDownloadBytes(Number(data.download))
            if (data.upload !== undefined) setLiveUploadBytes(Number(data.upload))
            setIsLiveActive(true)
          }
        }
      } catch (err) {
        // Silent catch on temporary connection latency
      }
    }

    const interval = setInterval(pollTraffic, 3000)
    return () => {
      isMounted = false
      clearInterval(interval)
    }
  }, [])

  const [copiedSerial, setCopiedSerial] = useState(false)
  const [showWifiPass, setShowWifiPass] = useState(false)

  // Popup Modals state
  const [isSpeedtestModalOpen, setIsSpeedtestModalOpen] = useState(false)
  const [isWifiModalOpen, setIsWifiModalOpen] = useState(false)
  const [isHostsModalOpen, setIsHostsModalOpen] = useState(false)
  const [isRebootModalOpen, setIsRebootModalOpen] = useState(false)

  const [isRebooting, setIsRebooting] = useState(false)
  const [isRefreshing, setIsRefreshing] = useState(false)

  // Unified Form for WiFi SSID & Password
  const wifiForm = useForm({
    ssid: onuData?.ssid || "",
    password: "",
  })

  const [wifiMsg, setWifiMsg] = useState<{ type: "success" | "error"; text: string } | null>(null)

  const copySerial = (text: string) => {
    navigator.clipboard.writeText(text)
    setCopiedSerial(true)
    setTimeout(() => setCopiedSerial(false), 2000)
  }

  const handleUpdateWifi = (e: React.FormEvent) => {
    e.preventDefault()
    setWifiMsg(null)

    if (wifiForm.data.ssid.trim().length < 3) {
      setWifiMsg({ type: "error", text: "Nama WiFi (SSID) minimal 3 karakter." })
      return
    }

    if (wifiForm.data.password && wifiForm.data.password.length < 8) {
      setWifiMsg({
        type: "error",
        text: "Password WiFi minimal 8 karakter (atau kosongkan jika tidak ingin mengubah password).",
      })
      return
    }

    wifiForm.post("/portal/wifi", {
      preserveScroll: true,
      onSuccess: () => {
        wifiForm.reset("password")
        setWifiMsg({
          type: "success",
          text: "Pengaturan WiFi berhasil diperbarui ke modem!",
        })
        setTimeout(() => {
          setIsWifiModalOpen(false)
          setWifiMsg(null)
        }, 1500)
      },
      onError: (err) => {
        const firstErr = Object.values(err)[0] as string
        setWifiMsg({ type: "error", text: firstErr || "Gagal mengubah pengaturan WiFi." })
      },
    })
  }

  const handleRebootModem = () => {
    setIsRebooting(true)
    router.post(
      "/portal/reboot-ont",
      {},
      {
        preserveScroll: true,
        onSuccess: () => {
          setIsRebooting(false)
          setIsRebootModalOpen(false)
        },
        onError: () => {
          setIsRebooting(false)
          setIsRebootModalOpen(false)
        },
      }
    )
  }

  const handleRefreshStatus = () => {
    setIsRefreshing(true)
    router.reload({
      only: ["onuData", "usageData"],
      onFinish: () => {
        setTimeout(() => setIsRefreshing(false), 500)
      },
    })
  }

  const fmtBytes = (b: number) => {
    if (b >= 1073741824) return (b / 1073741824).toFixed(2) + " GB"
    if (b >= 1048576) return (b / 1048576).toFixed(1) + " MB"
    if (b >= 1024) return (b / 1024).toFixed(0) + " KB"
    return b + " B"
  }
  const fmtSpeed = (b: number) => {
    if (!b || b <= 0) return "0.0 Mbps"
    if (b >= 1000000) return (b / 1000000).toFixed(1) + " Mbps"
    if (b >= 1000) return (b / 1000).toFixed(0) + " Kbps"
    return b + " bps"
  }
  const maxHist = Math.max(1, ...history.map((h) => h.total))

  const toSafeString = (val: any, fallback = "-"): string => {
    if (val === null || val === undefined) return fallback;
    if (typeof val === "string" || typeof val === "number" || typeof val === "boolean") {
      const s = String(val).trim();
      return s.length > 0 ? s : fallback;
    }
    if (typeof val === "object") {
      if (val._value !== undefined && val._value !== null && typeof val._value !== "object") {
        return String(val._value);
      }
      if (val.value !== undefined && val.value !== null && typeof val.value !== "object") {
        return String(val.value);
      }
      return fallback;
    }
    return fallback;
  };

  const rxVal = onuData?.rxPower ? parseFloat(toSafeString(onuData.rxPower, "0")) : null
  const isGoodRx = rxVal !== null && rxVal >= -24.0 && rxVal <= -14.0
  const isWarnRx = rxVal !== null && rxVal < -24.0 && rxVal >= -27.0
  const isBadRx = rxVal !== null && rxVal < -27.0

  const rawHosts = Array.isArray(onuData?.hosts) ? onuData.hosts : []
  const hostsList = rawHosts.map((h: any) => ({
    hostname: toSafeString(h?.hostname || h?.HostName, "Perangkat Tanpa Nama"),
    ip_address: toSafeString(h?.ip_address || h?.IPAddress, "-"),
    mac_address: toSafeString(h?.mac_address || h?.MACAddress, "-"),
    interface_type: toSafeString(h?.interface_type || h?.InterfaceType, "WiFi"),
    is_active: Boolean(h?.is_active),
  }))
  const hostsCount = onuData?.clients !== null && onuData?.clients !== undefined && typeof onuData?.clients !== "object" ? Number(onuData.clients) : hostsList.length

  const currentTotalSession = (liveDownloadBytes > 0 || liveUploadBytes > 0)
    ? (liveDownloadBytes + liveUploadBytes)
    : (usageData?.total ?? 0)

  return (
    <AppLayout
      title="Status Jaringan & Modem"
      subtitle={companyName}
      brand={portalBrand}
      navItems={portalNavItems}
      hideSidebar={true}
    >
      <div className="space-y-4 max-w-4xl mx-auto pb-32 sm:pb-20">
        {/* Flash Notifications */}
        {flash?.msg && (
          <div className="rounded-2xl bg-emerald-600 p-3.5 text-white text-xs font-bold flex items-center gap-2 shadow-xs">
            <CheckCircle2 className="h-4 w-4 shrink-0 text-white" />
            <span>{flash.msg}</span>
          </div>
        )}
        {flash?.error && (
          <div className="rounded-2xl bg-rose-600 p-3.5 text-white text-xs font-bold flex items-center gap-2 shadow-xs">
            <AlertCircle className="h-4 w-4 shrink-0 text-white" />
            <span>{flash.error}</span>
          </div>
        )}

        {/* =========================================================================
            SECTION 1: 2 SPEED TELEMETRY CARDS (DOWNLOAD & UPLOAD)
            ========================================================================= */}
        <div>
          <div className="flex items-center justify-between mb-2 px-0.5">
            <div className="flex items-center gap-2">
              <span className="text-[10px] font-black uppercase tracking-wider text-gray-400 dark:text-gray-500">
                Kecepatan Realtime
              </span>
              {isLiveActive && (
                <div className="flex items-center gap-1.5">
                  <span className="relative flex h-2 w-2">
                    <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                    <span className="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                  </span>
                  <span className="text-[10px] font-bold text-emerald-600 dark:text-emerald-400">
                    Live 3s
                  </span>
                </div>
              )}
            </div>

            <button
              type="button"
              onClick={() => setIsSpeedtestModalOpen(true)}
              className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-brand-600 hover:bg-brand-700 active:scale-95 text-white text-[11px] font-black uppercase tracking-wider shadow-xs transition-all cursor-pointer"
            >
              <Gauge className="h-3 w-3" />
              <span>Uji Kecepatan</span>
            </button>
          </div>

          <div className="grid grid-cols-2 gap-2.5 sm:gap-3">
            {/* Download Speed */}
            <div className="rounded-2xl border border-gray-200 bg-white p-3.5 sm:p-4 shadow-xs dark:border-gray-800 dark:bg-white/[0.03]">
              <div className="flex items-center gap-2 mb-2">
                <div className="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-emerald-600 text-white font-bold">
                  <ArrowDown className="h-4 w-4" />
                </div>
                <span className="text-[11px] sm:text-xs font-black uppercase tracking-wider text-gray-600 dark:text-gray-300">
                  Download
                </span>
              </div>
              <div className="text-xl sm:text-2xl font-black font-mono tracking-tight text-gray-900 dark:text-white">
                {fmtSpeed(liveDownloadSpeed)}
              </div>
              <div className="text-[11px] font-mono font-bold text-gray-500 dark:text-gray-400 mt-2 border-t border-gray-100 dark:border-gray-800/80 pt-1.5">
                Total: {fmtBytes(liveDownloadBytes)}
              </div>
            </div>

            {/* Upload Speed */}
            <div className="rounded-2xl border border-gray-200 bg-white p-3.5 sm:p-4 shadow-xs dark:border-gray-800 dark:bg-white/[0.03]">
              <div className="flex items-center gap-2 mb-2">
                <div className="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-brand-600 text-white font-bold">
                  <ArrowUp className="h-4 w-4" />
                </div>
                <span className="text-[11px] sm:text-xs font-black uppercase tracking-wider text-gray-600 dark:text-gray-300">
                  Upload
                </span>
              </div>
              <div className="text-xl sm:text-2xl font-black font-mono tracking-tight text-gray-900 dark:text-white">
                {fmtSpeed(liveUploadSpeed)}
              </div>
              <div className="text-[11px] font-mono font-bold text-gray-500 dark:text-gray-400 mt-2 border-t border-gray-100 dark:border-gray-800/80 pt-1.5">
                Total: {fmtBytes(liveUploadBytes)}
              </div>
            </div>
          </div>
        </div>

        {/* =========================================================================
            SECTION 2: SESSION STATUS STRIP (CLEAN & COMPACT)
            ========================================================================= */}
        <div className="rounded-2xl border border-gray-200 bg-white p-3.5 sm:p-4 shadow-xs dark:border-gray-800 dark:bg-white/[0.03] flex flex-wrap items-center justify-between gap-3">
          <div className="flex items-center gap-2.5">
            <div className="p-1.5 rounded-lg bg-brand-600 text-white shadow-xs">
              <Gauge className="h-4 w-4" />
            </div>
            <div>
              <span className="text-[10px] text-gray-400 uppercase font-bold block">Status Sesi Pelanggan</span>
              <div className="flex items-center gap-2 mt-0.5">
                <span className="font-bold text-xs text-gray-900 dark:text-white">
                  Total Sesi: {fmtBytes(currentTotalSession)}
                </span>
                {usageData?.address && (
                  <span className="text-gray-300 dark:text-gray-700">•</span>
                )}
                {usageData?.address && (
                  <span className="text-xs font-mono font-bold text-gray-500 dark:text-gray-400">
                    IP {usageData.address}
                  </span>
                )}
              </div>
            </div>
          </div>

          <div className="flex items-center gap-1.5">
            {usageData?.uptime_human && !["aktif", "aktif terhubung", "-"].includes(usageData.uptime_human.toLowerCase().trim()) && (
              <span className="inline-flex items-center gap-1 bg-emerald-600 text-white px-2.5 py-0.5 text-[10px] font-black rounded-md uppercase tracking-wider">
                <Clock className="h-3 w-3 text-white shrink-0" />
                <span>{usageData.uptime_human}</span>
              </span>
            )}
            <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-black uppercase tracking-wider bg-emerald-600 text-white">
              Aktif Terhubung
            </span>
          </div>
        </div>

        {/* =========================================================================
            SECTION 3: CLEAN COMPACT ONT MODEM CARD WITH POPUP MODAL TRIGGERS
            ========================================================================= */}
        {hasAcsDevice && onuData && (
          <div className="rounded-2xl border border-gray-200 bg-white shadow-xs dark:border-gray-800 dark:bg-white/[0.03] overflow-hidden space-y-4 p-4 sm:p-5">
            {/* Header: Title, Status Badge, and Refresh/Reboot Icons */}
            <div className="flex items-center justify-between gap-3 border-b border-gray-100 dark:border-gray-800 pb-3.5">
              <div className="flex items-center gap-2.5">
                <div className="p-2 rounded-xl bg-brand-600 text-white shadow-xs">
                  <Router className="h-4 w-4" />
                </div>
                <div>
                  <h3 className="font-bold text-sm text-gray-900 dark:text-white">
                    Modem & Jaringan WiFi
                  </h3>
                  <div className="flex items-center gap-1.5 mt-0.5">
                    <span
                      className={`inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md text-[10px] font-black uppercase tracking-wider ${
                        onuData.online
                          ? "bg-emerald-600 text-white"
                          : "bg-slate-600 text-white"
                      }`}
                    >
                      <span
                        className={`w-1.5 h-1.5 rounded-full ${
                          onuData.online ? "bg-white animate-pulse" : "bg-gray-300"
                        }`}
                      />
                      {onuData.online ? "Online" : "Offline"}
                    </span>
                  </div>
                </div>
              </div>

              {/* Action Buttons: Refresh & Reboot by Icon Only */}
              <div className="flex items-center gap-1.5">
                <button
                  type="button"
                  onClick={handleRefreshStatus}
                  disabled={isRefreshing}
                  className="w-8 h-8 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 text-gray-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 flex items-center justify-center transition shadow-2xs active:scale-95 disabled:opacity-50 cursor-pointer"
                  title="Segarkan data modem"
                >
                  <RotateCw className={`w-3.5 h-3.5 ${isRefreshing ? "animate-spin text-brand-500" : ""}`} />
                </button>

                <button
                  type="button"
                  onClick={() => setIsRebootModalOpen(true)}
                  disabled={isRebooting}
                  className="w-8 h-8 rounded-xl bg-rose-600 hover:bg-rose-700 text-white flex items-center justify-center transition shadow-2xs active:scale-95 disabled:opacity-50 cursor-pointer"
                  title="Reboot Modem"
                >
                  <Power className="w-3.5 h-3.5" />
                </button>
              </div>
            </div>

            {/* Telemetry Metric Cards Grid */}
            <div className="grid grid-cols-2 sm:grid-cols-4 gap-2.5 text-left">
              {/* Model & Manufacturer */}
              <div className="p-3 rounded-xl border border-gray-100 bg-gray-50/80 dark:border-gray-800 dark:bg-white/[0.02]">
                <span className="text-[10px] text-gray-400 font-bold block uppercase">Perangkat</span>
                <p className="font-bold text-xs text-gray-900 dark:text-white mt-0.5 truncate">
                  {onuData.model || "ONT Router"}
                </p>
                <span className="text-[10px] text-gray-400 block truncate">{onuData.manufacturer || "Router"}</span>
              </div>

              {/* Serial Number */}
              <div className="p-3 rounded-xl border border-gray-100 bg-gray-50/80 dark:border-gray-800 dark:bg-white/[0.02]">
                <span className="text-[10px] text-gray-400 font-bold block uppercase">Serial Number</span>
                <div className="flex items-center justify-between gap-1 mt-0.5">
                  <p className="font-mono text-xs font-black text-gray-900 dark:text-white truncate">
                    {onuData.serial || "-"}
                  </p>
                  {onuData.serial && (
                    <button
                      type="button"
                      onClick={() => copySerial(onuData.serial!)}
                      className="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 cursor-pointer"
                      title="Salin SN"
                    >
                      {copiedSerial ? <Check className="w-3 h-3 text-emerald-600" /> : <Copy className="w-3 h-3" />}
                    </button>
                  )}
                </div>
              </div>

              {/* Optical RX Power */}
              <div className="p-3 rounded-xl border border-gray-100 bg-gray-50/80 dark:border-gray-800 dark:bg-white/[0.02]">
                <span className="text-[10px] text-gray-400 font-bold block uppercase">Sinyal Rx Optik</span>
                <div className="flex items-center gap-1.5 mt-0.5">
                  <span className="font-mono text-xs font-black text-gray-900 dark:text-white tabular-nums">
                    {onuData.rxPower ? `${onuData.rxPower} dBm` : "-"}
                  </span>
                  {rxVal !== null && (
                    <span
                      className={`text-[9px] font-black px-1.5 py-0.2 rounded uppercase ${
                        isGoodRx
                          ? "bg-emerald-600 text-white"
                          : isWarnRx
                          ? "bg-amber-600 text-white"
                          : "bg-rose-600 text-white"
                      }`}
                    >
                      {isGoodRx ? "Bagus" : isWarnRx ? "Sedang" : "Kritis"}
                    </span>
                  )}
                </div>
                {onuData.txPower && (
                  <span className="text-[10px] text-gray-400 block mt-0.5">Tx: {onuData.txPower} dBm</span>
                )}
              </div>

              {/* Nama WiFi & Status */}
              <div className="p-3 rounded-xl border border-gray-100 bg-gray-50/80 dark:border-gray-800 dark:bg-white/[0.02]">
                <span className="text-[10px] text-gray-400 font-bold block uppercase flex items-center gap-1">
                  <Wifi className="w-3 h-3 text-brand-500" />
                  <span>WiFi (SSID)</span>
                </span>
                <p className="font-bold text-xs text-gray-900 dark:text-white mt-0.5 truncate">
                  {onuData.ssid || "WiFi Aktif"}
                </p>
                <span className="text-[10px] text-emerald-600 dark:text-emerald-400 block font-medium">
                  {hostsCount} Klien Terhubung
                </span>
              </div>
            </div>

            {/* CLEAN POPUP MODAL TRIGGER BUTTONS */}
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-2.5 pt-1">
              {/* Button 1: Ganti Nama & Password WiFi Popup */}
              <button
                type="button"
                onClick={() => {
                  wifiForm.setData({ ssid: onuData.ssid || "", password: "" })
                  setIsWifiModalOpen(true)
                }}
                className="w-full px-4 py-2.5 rounded-xl bg-brand-600 hover:bg-brand-700 text-white font-bold text-xs transition flex items-center justify-center gap-2 cursor-pointer shadow-xs active:scale-95"
              >
                <Settings2 className="w-4 h-4" />
                <span>Ubah Nama & Password WiFi</span>
              </button>

              {/* Button 2: Lihat Daftar Perangkat Terhubung Popup */}
              <button
                type="button"
                onClick={() => setIsHostsModalOpen(true)}
                className="w-full px-4 py-2.5 rounded-xl border border-gray-200 bg-gray-50 hover:bg-gray-100 text-gray-800 dark:border-gray-700 dark:bg-gray-800/80 dark:hover:bg-gray-800 dark:text-gray-200 font-bold text-xs transition flex items-center justify-center gap-2 cursor-pointer active:scale-95"
              >
                <Users className="w-4 h-4 text-brand-500" />
                <span>Lihat Perangkat Terhubung ({hostsList.length || hostsCount})</span>
              </button>
            </div>
          </div>
        )}

        {/* =========================================================================
            POPUP MODAL 1: PENGATURAN WIFI (SSID & PASSWORD SATU FORM)
            ========================================================================= */}
        {isWifiModalOpen && (
          <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs animate-in fade-in duration-200">
            <div className="w-full max-w-md rounded-2xl border border-gray-200 bg-white p-5 shadow-2xl dark:border-gray-800 dark:bg-[#151A22] space-y-4 max-h-[92vh] overflow-y-auto">
              {/* Modal Header */}
              <div className="flex items-center justify-between border-b border-gray-100 dark:border-gray-800 pb-3">
                <div className="flex items-center gap-2.5">
                  <div className="p-2 rounded-xl bg-brand-600 text-white shadow-xs">
                    <Wifi className="h-4 w-4" />
                  </div>
                  <div>
                    <h3 className="font-bold text-sm text-gray-900 dark:text-white">
                      Pengaturan WiFi Modem
                    </h3>
                    <p className="text-[11px] text-gray-500 dark:text-gray-400">
                      Ubah nama jaringan (SSID) dan password
                    </p>
                  </div>
                </div>

                <button
                  type="button"
                  onClick={() => setIsWifiModalOpen(false)}
                  className="p-1 rounded-lg text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-800 transition cursor-pointer"
                >
                  <X className="w-4 h-4" />
                </button>
              </div>

              {/* Alert Feedback Inside Modal */}
              {wifiMsg && (
                <div
                  className={`p-3 rounded-xl text-xs flex items-center gap-2 ${
                    wifiMsg.type === "success"
                      ? "bg-emerald-600 text-white font-bold"
                      : "bg-rose-600 text-white font-bold"
                  }`}
                >
                  {wifiMsg.type === "success" ? (
                    <CheckCircle2 className="w-4 h-4 shrink-0 text-white" />
                  ) : (
                    <AlertCircle className="w-4 h-4 shrink-0 text-white" />
                  )}
                  <span>{wifiMsg.text}</span>
                </div>
              )}

              {/* Unified WiFi Form */}
              <form onSubmit={handleUpdateWifi} className="space-y-3.5">
                {/* Field 1: Nama WiFi (SSID) */}
                <div className="space-y-1.5">
                  <label className="text-xs font-bold text-gray-700 dark:text-gray-300 flex items-center gap-1.5">
                    <Radio className="w-3.5 h-3.5 text-brand-500" />
                    <span>Nama WiFi (SSID)</span>
                  </label>
                  <input
                    type="text"
                    value={wifiForm.data.ssid}
                    onChange={(e) => wifiForm.setData("ssid", e.target.value)}
                    placeholder="Masukkan nama WiFi baru"
                    className="w-full px-3.5 py-2.5 text-xs bg-gray-50 border border-gray-200 rounded-xl text-gray-900 placeholder-gray-400 focus:bg-white focus:outline-none focus:border-brand-500 dark:bg-gray-900/60 dark:border-gray-700 dark:text-white dark:focus:bg-gray-900 transition font-medium"
                    required
                  />
                  <span className="text-[10px] text-gray-400 block">
                    Nama jaringan WiFi yang terlihat pada HP / Laptop.
                  </span>
                </div>

                {/* Field 2: Password WiFi */}
                <div className="space-y-1.5">
                  <label className="text-xs font-bold text-gray-700 dark:text-gray-300 flex items-center gap-1.5">
                    <KeyRound className="w-3.5 h-3.5 text-brand-500" />
                    <span>Password WiFi Baru</span>
                  </label>
                  <div className="relative">
                    <input
                      type={showWifiPass ? "text" : "password"}
                      value={wifiForm.data.password}
                      onChange={(e) => wifiForm.setData("password", e.target.value)}
                      placeholder="Password baru (min. 8 karakter)"
                      className="w-full px-3.5 py-2.5 text-xs bg-gray-50 border border-gray-200 rounded-xl text-gray-900 placeholder-gray-400 focus:bg-white focus:outline-none focus:border-brand-500 dark:bg-gray-900/60 dark:border-gray-700 dark:text-white dark:focus:bg-gray-900 pr-10 transition font-mono"
                    />
                    <button
                      type="button"
                      onClick={() => setShowWifiPass(!showWifiPass)}
                      className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 cursor-pointer"
                      title={showWifiPass ? "Sembunyikan" : "Lihat Password"}
                    >
                      {showWifiPass ? <EyeOff className="w-4 h-4" /> : <Eye className="w-4 h-4" />}
                    </button>
                  </div>
                  <span className="text-[10px] text-gray-400 block">
                    Kosongkan jika hanya ingin mengganti nama WiFi tanpa mengubah password.
                  </span>
                </div>

                <div className="p-3 rounded-xl bg-gray-50 dark:bg-white/[0.02] border border-gray-100 dark:border-gray-800 text-[11px] text-gray-500 dark:text-gray-400 flex items-start gap-2">
                  <ShieldCheck className="w-4 h-4 text-emerald-500 shrink-0 mt-0.5" />
                  <span>
                    Setelah disimpan, modem akan memperbarui konfigurasi secara otomatis dan perangkat yang terhubung perlu login ulang.
                  </span>
                </div>

                {/* Form Action Buttons */}
                <div className="flex items-center justify-end gap-2 pt-2">
                  <button
                    type="button"
                    onClick={() => setIsWifiModalOpen(false)}
                    disabled={wifiForm.processing}
                    className="px-4 py-2 text-xs font-bold text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800 rounded-xl transition cursor-pointer"
                  >
                    Batal
                  </button>
                  <button
                    type="submit"
                    disabled={wifiForm.processing}
                    className="px-4 py-2 bg-brand-600 hover:bg-brand-700 text-white font-bold text-xs rounded-xl shadow-xs transition active:scale-95 disabled:opacity-50 flex items-center gap-1.5 cursor-pointer"
                  >
                    <Save className="w-3.5 h-3.5" />
                    <span>{wifiForm.processing ? "Menyimpan..." : "Simpan Pengaturan"}</span>
                  </button>
                </div>
              </form>
            </div>
          </div>
        )}

        {/* =========================================================================
            POPUP MODAL 2: DAFTAR PERANGKAT TERHUBUNG (HOSTS)
            ========================================================================= */}
        {isHostsModalOpen && (
          <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs animate-in fade-in duration-200">
            <div className="w-full max-w-lg rounded-2xl border border-gray-200 bg-white p-5 shadow-2xl dark:border-gray-800 dark:bg-[#151A22] space-y-4 max-h-[92vh] overflow-y-auto">
              {/* Modal Header */}
              <div className="flex items-center justify-between border-b border-gray-100 dark:border-gray-800 pb-3">
                <div className="flex items-center gap-2.5">
                  <div className="p-2 rounded-xl bg-brand-600 text-white shadow-xs">
                    <Users className="h-4 w-4" />
                  </div>
                  <div>
                    <h3 className="font-bold text-sm text-gray-900 dark:text-white">
                      Perangkat Terhubung ({hostsList.length || hostsCount})
                    </h3>
                    <p className="text-[11px] text-gray-500 dark:text-gray-400">
                      Daftar HP / Laptop yang tersambung ke jaringan WiFi & LAN
                    </p>
                  </div>
                </div>

                <button
                  type="button"
                  onClick={() => setIsHostsModalOpen(false)}
                  className="p-1 rounded-lg text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-800 transition cursor-pointer"
                >
                  <X className="w-4 h-4" />
                </button>
              </div>

              {/* Host list */}
              <div className="space-y-2">
                {hostsList.length > 0 ? (
                  <div className="divide-y divide-gray-100 dark:divide-gray-800 border border-gray-100 dark:border-gray-800 rounded-xl overflow-hidden bg-gray-50/40 dark:bg-white/[0.01]">
                    {hostsList.map((h, idx) => {
                      const ifaceStr = typeof h.interface_type === "string"
                        ? h.interface_type
                        : (h.interface_type ? String(h.interface_type) : "");
                      const isWifi = ifaceStr.toLowerCase().includes("802.11") || ifaceStr.toLowerCase().includes("wifi");

                      return (
                        <div key={idx} className="p-3 flex items-center justify-between gap-3">
                          <div className="flex items-center gap-2.5 min-w-0">
                            <div className="p-2 rounded-lg bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300 shrink-0">
                              {isWifi ? (
                                <Smartphone className="w-4 h-4" />
                              ) : (
                                <Laptop className="w-4 h-4" />
                              )}
                            </div>
                            <div className="min-w-0">
                              <div className="flex items-center gap-1.5">
                                <span className="font-bold text-xs text-gray-900 dark:text-white truncate">
                                  {h.hostname || "Perangkat Tanpa Nama"}
                                </span>
                                {h.is_active !== false && (
                                  <span className="w-1.5 h-1.5 rounded-full bg-emerald-500 shrink-0" title="Aktif" />
                                )}
                              </div>
                              <div className="flex items-center gap-2 text-[10px] text-gray-400 font-mono mt-0.5 truncate">
                                <span>{h.ip_address || "-"}</span>
                                <span>•</span>
                                <span>{h.mac_address || "-"}</span>
                              </div>
                            </div>
                          </div>

                          <span className="text-[10px] font-bold px-2 py-0.5 rounded-md bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400 shrink-0">
                            {ifaceStr.includes("802.11") ? "WiFi" : ifaceStr || "LAN"}
                          </span>
                        </div>
                      );
                    })}
                  </div>
                ) : (
                  <div className="p-6 text-center rounded-xl border border-dashed border-gray-200 dark:border-gray-800 text-gray-400 text-xs">
                    Belum ada perangkat terhubung yang terbaca pada sesi modem saat ini.
                  </div>
                )}
              </div>

              <div className="flex items-center justify-end pt-2">
                <button
                  type="button"
                  onClick={() => setIsHostsModalOpen(false)}
                  className="px-4 py-2 bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-800 dark:text-gray-200 font-bold text-xs rounded-xl transition cursor-pointer"
                >
                  Tutup
                </button>
              </div>
            </div>
          </div>
        )}

        {/* =========================================================================
            POPUP MODAL 3: REBOOT CONFIRMATION MODAL
            ========================================================================= */}
        {isRebootModalOpen && (
          <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs animate-in fade-in duration-200">
            <div className="w-full max-w-md rounded-2xl border border-gray-200 bg-white p-5 shadow-2xl dark:border-gray-800 dark:bg-[#151A22] space-y-4">
              <div className="flex items-center gap-3">
                <div className="p-2.5 rounded-xl bg-rose-600 text-white shrink-0">
                  <Power className="h-5 w-5" />
                </div>
                <div>
                  <h3 className="font-bold text-sm text-gray-900 dark:text-white">Reboot / Restart Modem?</h3>
                  <p className="text-xs text-gray-500 dark:text-gray-400">Konfirmasi restart perangkat</p>
                </div>
              </div>

              <div className="p-3 rounded-xl bg-gray-50 dark:bg-white/[0.02] border border-gray-100 dark:border-gray-800 text-xs text-gray-600 dark:text-gray-300 space-y-2">
                <p>
                  Perangkat modem ONT Anda akan dimatikan dan dinyalakan ulang secara otomatis.
                </p>
                <p className="text-[11px] text-amber-600 dark:text-amber-400 font-medium flex items-center gap-1">
                  <AlertCircle className="w-3.5 h-3.5 shrink-0" />
                  <span>Koneksi internet dan WiFi akan terputus sementara selama 1–2 menit.</span>
                </p>
              </div>

              <div className="flex items-center justify-end gap-2 pt-2">
                <button
                  type="button"
                  onClick={() => setIsRebootModalOpen(false)}
                  disabled={isRebooting}
                  className="px-4 py-2 text-xs font-bold text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800 rounded-xl transition cursor-pointer"
                >
                  Batal
                </button>
                <button
                  type="button"
                  onClick={handleRebootModem}
                  disabled={isRebooting}
                  className="px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white font-bold text-xs rounded-xl shadow-xs transition active:scale-95 disabled:opacity-50 flex items-center gap-1.5 cursor-pointer"
                >
                  <Power className="w-3.5 h-3.5" />
                  <span>{isRebooting ? "Mengirim Perintah..." : "Ya, Reboot Sekarang"}</span>
                </button>
              </div>
            </div>
          </div>
        )}

        {/* =========================================================================
            SECTION 4: HISTORICAL MONTHLY USAGE
            ========================================================================= */}
        {history.length > 0 && (
          <div className="rounded-2xl border border-gray-200 bg-white p-4 shadow-xs dark:border-gray-800 dark:bg-white/[0.03] space-y-3">
            <h2 className="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 flex items-center gap-1.5">
              <Activity className="h-3.5 w-3.5 text-brand-500" />
              <span>Histori Pemakaian Bulanan</span>
            </h2>

            <div className="space-y-3 pt-1">
              {history.map((h, i) => {
                const pct = Math.round((h.total / maxHist) * 100)
                return (
                  <div key={i} className="space-y-1.5">
                    <div className="flex items-center justify-between text-xs">
                      <span className="font-bold text-gray-900 dark:text-white">{h.label || h.period}</span>
                      <span className="font-mono font-bold text-gray-900 dark:text-white">{fmtBytes(h.total)}</span>
                    </div>
                    <div className="h-2 w-full rounded-full bg-gray-100 dark:bg-gray-800 overflow-hidden">
                      <div
                        className="h-full rounded-full bg-brand-600 transition-all duration-500"
                        style={{ width: `${Math.max(5, pct)}%` }}
                      />
                    </div>
                    <div className="flex items-center justify-between text-[10px] text-gray-400 font-mono">
                      <span>↓ {fmtBytes(h.download || 0)} Download</span>
                      <span>↑ {fmtBytes(h.upload || 0)} Upload</span>
                    </div>
                  </div>
                )
              })}
            </div>
          </div>
        )}

        {/* Speedtest Modal */}
        <PortalSpeedtestModal
          isOpen={isSpeedtestModalOpen}
          onClose={() => setIsSpeedtestModalOpen(false)}
          packageName={customer?.package_name}
          packageSpeed={customer?.package_speed}
          companyName={companyName}
        />
      </div>
    </AppLayout>
  )
}
