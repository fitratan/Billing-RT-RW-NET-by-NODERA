import React, { useState } from "react"
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogDescription,
} from "@/components/ui/dialog"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Badge } from "@/components/ui/badge"
import {
  Server,
  CheckCircle2,
  AlertCircle,
  Loader2,
  Terminal,
  ShieldCheck,
  RefreshCw,
  Network,
  Play,
} from "lucide-react"
import axios from "axios"
import { router } from "@inertiajs/react"

interface AutoInstallChrModalProps {
  open: boolean
  onClose: () => void
}

interface VpsProbeResult {
  os: string
  cpu: string
  cores: number
  memory: string
  main_disk: string
  disks: string
  has_kvm: boolean
  detected_ip: string
  detected_gateway: string
}

export function AutoInstallChrModal({ open, onClose }: AutoInstallChrModalProps) {
  const [formData, setFormData] = useState({
    ssh_host: "",
    ssh_port: "22",
    ssh_user: "root",
    ssh_pass: "",
    ssh_key: "",
    install_mode: "raw_disk", // 'raw_disk' | 'qemu'
    ros_version: "7.18.2",
    server_name: "",
    server_location: "",
    server_domain: "",
    api_user: "admin",
    api_pass: "AdminChr123!",
    api_port: "8728",
    winbox_port: "7171",
    sstp_port: "443",
    profile_remot: "profile-remot",
    profile_dedicated: "profile-dedicated",
    ip_pool_remot_start: "10.10.10.10",
    ip_pool_remot_end: "10.10.10.250",
    ip_pool_dedicated_start: "10.10.20.10",
    ip_pool_dedicated_end: "10.10.20.250",
    nat_port_start: "50100",
    nat_port_end: "59999",
  })

  const [probing, setProbing] = useState(false)
  const [probeResult, setProbeResult] = useState<VpsProbeResult | null>(null)
  const [probeError, setProbeError] = useState<string | null>(null)

  const [installing, setInstalling] = useState(false)
  const [logs, setLogs] = useState<string[]>([])
  const [installError, setInstallError] = useState<string | null>(null)
  const [installSuccess, setInstallSuccess] = useState(false)

  const handleChange = (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>) => {
    const { name, value } = e.target
    setFormData((prev) => ({ ...prev, [name]: value }))
  }

  const handleTestVps = async () => {
    if (!formData.ssh_host) {
      setProbeError("Silakan masukkan alamat IP host server VPS.")
      return
    }

    setProbing(true)
    setProbeError(null)
    setProbeResult(null)

    try {
      const res = await axios.post("/superadmin/vpn-servers/check-vps", {
        ssh_host: formData.ssh_host,
        ssh_port: parseInt(formData.ssh_port) || 22,
        ssh_user: formData.ssh_user || "root",
        ssh_pass: formData.ssh_pass,
        ssh_key: formData.ssh_key,
      })

      if (res.data.ok) {
        setProbeResult(res.data.system)
        setFormData((prev) => ({
          ...prev,
          server_name: prev.server_name || `CHR-${res.data.system.detected_ip || formData.ssh_host}`,
          server_domain: prev.server_domain || res.data.system.detected_ip || formData.ssh_host,
        }))
      } else {
        setProbeError(res.data.error || "Gagal menghubungi VPS.")
      }
    } catch (err: any) {
      setProbeError(err.response?.data?.message || err.message || "Terjadi error koneksi SSH.")
    } finally {
      setProbing(false)
    }
  }

  const handleRunInstall = async () => {
    if (!formData.ssh_host || !formData.server_name || !formData.api_pass) {
      setInstallError("Harap lengkapi informasi wajib (Host, Nama Server, dan Password Admin).")
      return
    }

    setInstalling(true)
    setInstallError(null)
    setInstallSuccess(false)
    setLogs(["[Memulai] Mengirim perintah instalasi otomatis ke backend Nodera..."])

    try {
      const res = await axios.post("/superadmin/vpn-servers/auto-install", {
        ...formData,
        ssh_port: parseInt(formData.ssh_port) || 22,
        api_port: parseInt(formData.api_port) || 8728,
        winbox_port: parseInt(formData.winbox_port) || 7171,
        sstp_port: parseInt(formData.sstp_port) || 443,
        nat_port_start: parseInt(formData.nat_port_start) || 50100,
        nat_port_end: parseInt(formData.nat_port_end) || 59999,
      })

      if (res.data.logs) {
        setLogs(res.data.logs)
      }

      if (res.data.ok) {
        setInstallSuccess(true)
        setTimeout(() => {
          router.reload()
        }, 2000)
      } else {
        setInstallError(res.data.error || "Terjadi kesalahan saat proses instalasi.")
      }
    } catch (err: any) {
      setInstallError(err.response?.data?.message || err.message || "Gagal mengeksekusi instalasi.")
    } finally {
      setInstalling(false)
    }
  }

  const handleReset = () => {
    setProbeResult(null)
    setProbeError(null)
    setLogs([])
    setInstallError(null)
    setInstallSuccess(false)
    onClose()
  }

  return (
    <Dialog open={open} onOpenChange={handleReset}>
      <DialogContent className="max-w-4xl bg-[#0F141C] border-[#1F2937] text-white p-6 max-h-[92vh] overflow-y-auto">
        <DialogHeader>
          <div className="flex items-center gap-3">
            <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-[#0073C6] to-[#00C2FF] text-white shadow-lg">
              <Play className="h-5 w-5" />
            </div>
            <div>
              <DialogTitle className="text-lg font-bold text-white flex items-center gap-2">
                Auto-Install MikroTik CHR VPS
                <Badge variant="outline" className="border-[#00C2FF]/40 text-[#00C2FF] text-[10px]">
                  Otomatisasi 1-Klik
                </Badge>
              </DialogTitle>
              <DialogDescription className="text-xs text-slate-400">
                Install dan konfigurasi otomatis router MikroTik CHR (SSTP, Winbox, API &amp; NAT Port Forwarding) pada VPS Linux baru Anda.
              </DialogDescription>
            </div>
          </div>
        </DialogHeader>

        <div className="space-y-5 pt-2">
          {/* SECTION 1: VPS SSH ACCESS */}
          <div className="rounded-2xl border border-[#1E2633] bg-[#141A24] p-4 space-y-4">
            <div className="flex items-center justify-between">
              <h3 className="text-xs font-bold uppercase tracking-wider text-slate-300 flex items-center gap-2">
                <Server className="h-4 w-4 text-[#00C2FF]" /> 1. Akses SSH ke VPS Target
              </h3>
              <Button
                type="button"
                size="sm"
                variant="secondary"
                disabled={probing || installing}
                onClick={handleTestVps}
                className="h-7 text-xs bg-[#1E2633] hover:bg-[#2A374A] text-slate-200 border border-[#2E3C4E]"
              >
                {probing ? <Loader2 className="h-3.5 w-3.5 animate-spin mr-1.5" /> : <RefreshCw className="h-3.5 w-3.5 mr-1.5" />}
                Tes Koneksi &amp; Scan VPS
              </Button>
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
              <div className="sm:col-span-2 space-y-1.5">
                <Label className="text-xs text-slate-300">Alamat IP / Host VPS *</Label>
                <Input
                  name="ssh_host"
                  value={formData.ssh_host}
                  onChange={handleChange}
                  placeholder="cth: 141.94.120.45"
                  className="bg-[#0B0F15] border-[#222E3F] text-xs font-mono"
                />
              </div>

              <div className="space-y-1.5">
                <Label className="text-xs text-slate-300">Port SSH</Label>
                <Input
                  name="ssh_port"
                  value={formData.ssh_port}
                  onChange={handleChange}
                  placeholder="22"
                  className="bg-[#0B0F15] border-[#222E3F] text-xs font-mono"
                />
              </div>
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <div className="space-y-1.5">
                <Label className="text-xs text-slate-300">User SSH</Label>
                <Input
                  name="ssh_user"
                  value={formData.ssh_user}
                  onChange={handleChange}
                  placeholder="root"
                  className="bg-[#0B0F15] border-[#222E3F] text-xs font-mono"
                />
              </div>

              <div className="space-y-1.5">
                <Label className="text-xs text-slate-300">Password Root SSH</Label>
                <Input
                  type="password"
                  name="ssh_pass"
                  value={formData.ssh_pass}
                  onChange={handleChange}
                  placeholder="••••••••"
                  className="bg-[#0B0F15] border-[#222E3F] text-xs"
                />
              </div>
            </div>

            {probeError && (
              <div className="flex items-center gap-2 rounded-xl border border-red-500/30 bg-red-500/10 p-3 text-xs text-red-400">
                <AlertCircle className="h-4 w-4 shrink-0" />
                <span>{probeError}</span>
              </div>
            )}

            {probeResult && (
              <div className="rounded-xl border border-emerald-500/30 bg-emerald-500/5 p-3 space-y-2 text-xs">
                <div className="flex items-center gap-2 text-emerald-400 font-semibold">
                  <CheckCircle2 className="h-4 w-4" />
                  VPS Berhasil Terdeteksi &amp; Siap Di-install
                </div>
                <div className="grid grid-cols-2 sm:grid-cols-4 gap-2 text-slate-300 text-[11px] pt-1">
                  <div>
                    <span className="text-slate-500 block">OS Distro:</span>
                    <span className="font-medium text-white truncate block">{probeResult.os}</span>
                  </div>
                  <div>
                    <span className="text-slate-500 block">CPU &amp; Core:</span>
                    <span className="font-medium text-white">{probeResult.cores} Core ({probeResult.cpu || "vCPU"})</span>
                  </div>
                  <div>
                    <span className="text-slate-500 block">Memori RAM:</span>
                    <span className="font-medium text-white">{probeResult.memory}</span>
                  </div>
                  <div>
                    <span className="text-slate-500 block">Target Disk:</span>
                    <span className="font-mono text-emerald-400 font-semibold">{probeResult.main_disk}</span>
                  </div>
                </div>
              </div>
            )}
          </div>

          {/* SECTION 2: CHR & SERVER PROFILES */}
          <div className="rounded-2xl border border-[#1E2633] bg-[#141A24] p-4 space-y-4">
            <h3 className="text-xs font-bold uppercase tracking-wider text-slate-300 flex items-center gap-2">
              <ShieldCheck className="h-4 w-4 text-[#00C2FF]" /> 2. Pengaturan MikroTik RouterOS &amp; Identitas Server
            </h3>

            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <div className="space-y-1.5">
                <Label className="text-xs text-slate-300">Mode Instalasi CHR *</Label>
                <select
                  name="install_mode"
                  value={formData.install_mode}
                  onChange={handleChange}
                  className="w-full rounded-md bg-[#0B0F15] border border-[#222E3F] px-3 py-2 text-xs text-white focus:outline-none focus:ring-1 focus:ring-[#0073C6]"
                >
                  <option value="raw_disk">⚡ Raw Disk / Bare-Metal (0% CPU, Direkomendasikan untuk VPS Baru)</option>
                  <option value="qemu">📦 QEMU Headless VM (Virtual Machine di dalam Ubuntu)</option>
                </select>
                <span className="text-[10px] text-slate-400 block">
                  {formData.install_mode === "raw_disk"
                    ? "Menulis RouterOS langsung ke harddisk fisik VPS (Performa maksimal, beban CPU 0%)."
                    : "Menjalankan CHR di dalam mesin virtual QEMU headless (persis seperti VPS saat ini)."}
                </span>
              </div>

              <div className="space-y-1.5">
                <Label className="text-xs text-slate-300">Versi RouterOS CHR</Label>
                <select
                  name="ros_version"
                  value={formData.ros_version}
                  onChange={handleChange}
                  className="w-full rounded-md bg-[#0B0F15] border border-[#222E3F] px-3 py-2 text-xs text-white focus:outline-none focus:ring-1 focus:ring-[#0073C6]"
                >
                  <option value="7.18.2">RouterOS v7.18.2 (Versi Stable Terbaru v7)</option>
                  <option value="7.17.2">RouterOS v7.17.2</option>
                  <option value="6.49.17">RouterOS v6.49.17 (Versi Long-Term v6)</option>
                </select>
              </div>
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
              <div className="space-y-1.5">
                <Label className="text-xs text-slate-300">Nama Server *</Label>
                <Input
                  name="server_name"
                  value={formData.server_name}
                  onChange={handleChange}
                  placeholder="cth: SG-Singapore-1"
                  className="bg-[#0B0F15] border-[#222E3F] text-xs"
                />
              </div>

              <div className="space-y-1.5">
                <Label className="text-xs text-slate-300">Lokasi Server</Label>
                <Input
                  name="server_location"
                  value={formData.server_location}
                  onChange={handleChange}
                  placeholder="cth: Singapore"
                  className="bg-[#0B0F15] border-[#222E3F] text-xs"
                />
              </div>

              <div className="space-y-1.5">
                <Label className="text-xs text-slate-300">Domain / Host VPN</Label>
                <Input
                  name="server_domain"
                  value={formData.server_domain}
                  onChange={handleChange}
                  placeholder="cth: vpn1.airnetsolution.com"
                  className="bg-[#0B0F15] border-[#222E3F] text-xs font-mono"
                />
              </div>
            </div>

            <div className="grid grid-cols-2 sm:grid-cols-5 gap-3">
              <div className="space-y-1.5">
                <Label className="text-xs text-slate-300">User API</Label>
                <Input
                  name="api_user"
                  value={formData.api_user}
                  onChange={handleChange}
                  placeholder="admin"
                  className="bg-[#0B0F15] border-[#222E3F] text-xs font-mono"
                />
              </div>

              <div className="space-y-1.5">
                <Label className="text-xs text-slate-300">Password Admin *</Label>
                <Input
                  type="password"
                  name="api_pass"
                  value={formData.api_pass}
                  onChange={handleChange}
                  placeholder="Password CHR"
                  className="bg-[#0B0F15] border-[#222E3F] text-xs"
                />
              </div>

              <div className="space-y-1.5">
                <Label className="text-xs text-slate-300">Port Winbox</Label>
                <Input
                  name="winbox_port"
                  value={formData.winbox_port}
                  onChange={handleChange}
                  placeholder="7171"
                  className="bg-[#0B0F15] border-[#222E3F] text-xs font-mono"
                />
              </div>

              <div className="space-y-1.5">
                <Label className="text-xs text-slate-300">Port API</Label>
                <Input
                  name="api_port"
                  value={formData.api_port}
                  onChange={handleChange}
                  placeholder="8728"
                  className="bg-[#0B0F15] border-[#222E3F] text-xs font-mono"
                />
              </div>

              <div className="space-y-1.5 col-span-2 sm:col-span-1">
                <Label className="text-xs text-slate-300">Port SSTP VPN</Label>
                <Input
                  name="sstp_port"
                  value={formData.sstp_port}
                  onChange={handleChange}
                  placeholder="443"
                  className="bg-[#0B0F15] border-[#222E3F] text-xs font-mono"
                />
              </div>
            </div>
          </div>

          {/* SECTION 3: IP POOLS & NAT PORT FORWARDING */}
          <div className="rounded-2xl border border-[#1E2633] bg-[#141A24] p-4 space-y-4">
            <h3 className="text-xs font-bold uppercase tracking-wider text-slate-300 flex items-center gap-2">
              <Network className="h-4 w-4 text-[#00C2FF]" /> 3. Alokasi IP Pool &amp; Rentang Port NAT (Bisa Disesuaikan)
            </h3>

            {/* Remote IP Pool & Profile */}
            <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
              <div className="space-y-1.5">
                <Label className="text-xs text-slate-300">Nama Profile Remote</Label>
                <Input
                  name="profile_remot"
                  value={formData.profile_remot}
                  onChange={handleChange}
                  placeholder="profile-remot"
                  className="bg-[#0B0F15] border-[#222E3F] text-xs font-mono"
                />
              </div>
              <div className="space-y-1.5">
                <Label className="text-xs text-slate-300">Pool IP Remote Mulai</Label>
                <Input
                  name="ip_pool_remot_start"
                  value={formData.ip_pool_remot_start}
                  onChange={handleChange}
                  placeholder="10.10.10.10"
                  className="bg-[#0B0F15] border-[#222E3F] text-xs font-mono"
                />
              </div>
              <div className="space-y-1.5">
                <Label className="text-xs text-slate-300">Pool IP Remote Akhir</Label>
                <Input
                  name="ip_pool_remot_end"
                  value={formData.ip_pool_remot_end}
                  onChange={handleChange}
                  placeholder="10.10.10.250"
                  className="bg-[#0B0F15] border-[#222E3F] text-xs font-mono"
                />
              </div>
            </div>

            {/* Dedicated IP Pool & Profile */}
            <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
              <div className="space-y-1.5">
                <Label className="text-xs text-slate-300">Nama Profile Dedicated</Label>
                <Input
                  name="profile_dedicated"
                  value={formData.profile_dedicated}
                  onChange={handleChange}
                  placeholder="profile-dedicated"
                  className="bg-[#0B0F15] border-[#222E3F] text-xs font-mono"
                />
              </div>
              <div className="space-y-1.5">
                <Label className="text-xs text-slate-300">Pool IP Dedicated Mulai</Label>
                <Input
                  name="ip_pool_dedicated_start"
                  value={formData.ip_pool_dedicated_start}
                  onChange={handleChange}
                  placeholder="10.10.20.10"
                  className="bg-[#0B0F15] border-[#222E3F] text-xs font-mono"
                />
              </div>
              <div className="space-y-1.5">
                <Label className="text-xs text-slate-300">Pool IP Dedicated Akhir</Label>
                <Input
                  name="ip_pool_dedicated_end"
                  value={formData.ip_pool_dedicated_end}
                  onChange={handleChange}
                  placeholder="10.10.20.250"
                  className="bg-[#0B0F15] border-[#222E3F] text-xs font-mono"
                />
              </div>
            </div>

            {/* NAT Port Range */}
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-1 border-t border-[#1E2633]">
              <div className="space-y-1.5">
                <Label className="text-xs text-slate-300">Rentang Port NAT Mulai</Label>
                <Input
                  type="number"
                  name="nat_port_start"
                  value={formData.nat_port_start}
                  onChange={handleChange}
                  placeholder="50100"
                  className="bg-[#0B0F15] border-[#222E3F] text-xs font-mono"
                />
                <span className="text-[10px] text-slate-400">Port publik awal untuk port forwarding akun VPN.</span>
              </div>
              <div className="space-y-1.5">
                <Label className="text-xs text-slate-300">Rentang Port NAT Akhir</Label>
                <Input
                  type="number"
                  name="nat_port_end"
                  value={formData.nat_port_end}
                  onChange={handleChange}
                  placeholder="59999"
                  className="bg-[#0B0F15] border-[#222E3F] text-xs font-mono"
                />
                <span className="text-[10px] text-slate-400">Port publik akhir alokasi NAT forward.</span>
              </div>
            </div>
          </div>

          {/* SECTION 4: LIVE CONSOLE LOGS */}
          {(installing || logs.length > 0) && (
            <div className="rounded-2xl border border-[#1E2633] bg-[#0A0D13] p-4 space-y-2">
              <div className="flex items-center justify-between">
                <h4 className="text-xs font-bold text-slate-300 flex items-center gap-2">
                  <Terminal className="h-4 w-4 text-[#00C2FF]" /> Log Instalasi Live
                </h4>
                {installing && (
                  <Badge variant="outline" className="border-cyan-500/40 text-cyan-400 text-[10px] animate-pulse">
                    Sedang Menginstall...
                  </Badge>
                )}
                {installSuccess && (
                  <Badge variant="outline" className="border-emerald-500/40 text-emerald-400 text-[10px]">
                    Selesai &amp; Sukses
                  </Badge>
                )}
              </div>

              <div className="h-48 overflow-y-auto rounded-xl bg-black/70 p-3 font-mono text-[11px] text-slate-300 space-y-1 border border-white/5">
                {logs.map((log, idx) => (
                  <div key={idx} className="leading-relaxed">
                    {log.includes("Gagal") || log.includes("ERROR") || log.includes("FAIL") ? (
                      <span className="text-red-400 font-semibold">{log}</span>
                    ) : log.includes("✅") || log.includes("Sukses") || log.includes("berhasil") || log.includes("success") ? (
                      <span className="text-emerald-400 font-bold">{log}</span>
                    ) : (
                      <span className="text-slate-300">{log}</span>
                    )}
                  </div>
                ))}
              </div>
            </div>
          )}

          {installError && (
            <div className="flex items-center gap-2 rounded-xl border border-red-500/30 bg-red-500/10 p-3 text-xs text-red-400">
              <AlertCircle className="h-4 w-4 shrink-0" />
              <span>{installError}</span>
            </div>
          )}

          {installSuccess && (
            <div className="flex items-center gap-2 rounded-xl border border-emerald-500/30 bg-emerald-500/10 p-3 text-xs text-emerald-400">
              <CheckCircle2 className="h-4 w-4 shrink-0" />
              <span>
                Router MikroTik CHR berhasil dipasang dan otomatis terhubung ke sistem Nodera! Memuat ulang daftar server...
              </span>
            </div>
          )}

          {/* ACTION BUTTONS */}
          <div className="flex items-center justify-end gap-3 pt-2">
            <Button
              type="button"
              variant="outline"
              onClick={handleReset}
              disabled={installing}
              className="border-[#222E3F] bg-transparent text-slate-300 hover:bg-[#1B2433]"
            >
              Tutup
            </Button>

            <Button
              type="button"
              onClick={handleRunInstall}
              disabled={installing}
              className="bg-gradient-to-r from-[#0073C6] to-[#00C2FF] text-white hover:opacity-90 font-bold shadow-lg"
            >
              {installing ? (
                <>
                  <Loader2 className="h-4 w-4 animate-spin mr-2" />
                  Memproses Instalasi...
                </>
              ) : (
                <>
                  <Play className="h-4 w-4 mr-2" />
                  Mulai Auto-Install CHR
                </>
              )}
            </Button>
          </div>
        </div>
      </DialogContent>
    </Dialog>
  )
}
