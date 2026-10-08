import { useState } from "react"
import {
  ShieldCheck,
  Key,
  Building2,
  User,
  CheckCircle2,
  ArrowRight,
  ArrowLeft,
  Loader2,
  Sparkles,
  AlertCircle,
  Wifi,
  Eye,
  EyeOff,
} from "lucide-react"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Card, CardContent } from "@/components/ui/card"

interface SetupWizardProps {
  hardwareId: string
  serverIp: string
  domain: string
  licenseServer: string
  defaultCompanyName: string
}

export default function SetupWizard({ hardwareId, serverIp, domain, defaultCompanyName }: SetupWizardProps) {
  const [step, setStep] = useState(1)

  // Step 1: License State
  const [licenseKey, setLicenseKey] = useState("")
  const [verifying, setVerifying] = useState(false)
  const [licenseValid, setLicenseValid] = useState(false)
  const [licenseData, setLicenseData] = useState<{
    client_name?: string
    package_type?: string
    expires_at?: string
    status?: string
    message?: string
  } | null>(null)
  const [licenseError, setLicenseError] = useState<string | null>(null)

  // Step 2: Company Profile State
  const [companyName, setCompanyName] = useState(defaultCompanyName || "NODERA Billing")
  const [companyPhone, setCompanyPhone] = useState("")
  const [companyEmail, setCompanyEmail] = useState("")

  // Step 3: Admin Account State
  const [adminName, setAdminName] = useState("Admin Billing")
  const [adminEmail, setAdminEmail] = useState("admin@isp.local")
  const [adminPassword, setAdminPassword] = useState("")
  const [adminPasswordConfirm, setAdminPasswordConfirm] = useState("")
  const [showPassword, setShowPassword] = useState(false)

  // Submit / Finalize State
  const [submitting, setSubmitting] = useState(false)
  const [submitError, setSubmitError] = useState<string | null>(null)

  // 1. Verify License Function
  const handleVerifyLicense = async (e?: React.FormEvent) => {
    e?.preventDefault()
    if (!licenseKey.trim()) {
      setLicenseError("Silakan masukkan License Key NODERA Anda.")
      return
    }

    setVerifying(true)
    setLicenseError(null)

    try {
      const csrfToken = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || ""
      const res = await fetch("/setup/verify-license", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "Accept": "application/json",
          "X-CSRF-TOKEN": csrfToken,
        },
        body: JSON.stringify({
          license_key: licenseKey.trim(),
          domain: domain,
        }),
      })

      const data = await res.json()

      if (res.ok && data.valid) {
        setLicenseValid(true)
        setLicenseData(data)
        setLicenseError(null)
      } else {
        setLicenseValid(false)
        setLicenseData(null)
        setLicenseError(data.message || "License Key tidak valid atau tidak terdaftar.")
      }
    } catch (err: any) {
      setLicenseValid(false)
      setLicenseError("Gagal menghubungi server pusat. Pastikan koneksi internet aktif.")
    } finally {
      setVerifying(false)
    }
  }

  // 2. Complete Setup Function
  const handleCompleteSetup = async () => {
    if (adminPassword !== adminPasswordConfirm) {
      setSubmitError("Konfirmasi password tidak cocok.")
      return
    }
    if (adminPassword.length < 6) {
      setSubmitError("Password minimal harus 6 karakter.")
      return
    }

    setSubmitting(true)
    setSubmitError(null)

    try {
      const csrfToken = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || ""
      const res = await fetch("/setup/complete", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "Accept": "application/json",
          "X-CSRF-TOKEN": csrfToken,
        },
        body: JSON.stringify({
          license_key: licenseKey.trim(),
          company_name: companyName.trim(),
          company_phone: companyPhone.trim(),
          company_email: companyEmail.trim(),
          admin_name: adminName.trim(),
          admin_email: adminEmail.trim(),
          admin_password: adminPassword,
        }),
      })

      const data = await res.json()

      if (res.ok && data.success) {
        window.location.href = data.redirect || "/dashboard"
      } else {
        setSubmitError(data.message || "Terjadi kesalahan saat menyelesaikan konfigurasi.")
        setSubmitting(false)
      }
    } catch (err: any) {
      setSubmitError("Gagal menghubungi server saat finalisasi.")
      setSubmitting(false)
    }
  }

  return (
    <div className="min-h-screen bg-[#07090E] text-slate-100 flex flex-col justify-center items-center p-4 sm:p-6 font-sans">
      <div className="w-full max-w-2xl">
        {/* Header Branding */}
        <div className="text-center mb-8">
          <div className="inline-flex items-center justify-center h-16 w-16 rounded-2xl bg-[#0B2138] border border-[#0073C6]/40 shadow-sm shadow-[#0073C6]/10 mb-3 text-[#00C2FF]">
            <Wifi className="h-8 w-8" />
          </div>
          <h1 className="text-2xl sm:text-3xl font-extrabold tracking-tight text-white flex items-center justify-center gap-2">
            NODERA Billing <span className="text-xs px-2.5 py-0.5 rounded-full bg-[#0073C6]/20 border border-[#0073C6]/40 text-[#00C2FF] font-mono font-bold">Standalone</span>
          </h1>
          <p className="text-xs sm:text-sm text-slate-400 mt-1">
            Panduan Setup & Aktivasi Awal Aplikasi Billing ISP Mandiri Anda
          </p>
        </div>

        {/* Steps Progress Bar */}
        <div className="grid grid-cols-3 gap-2 mb-6">
          {[
            { num: 1, label: "Aktivasi Lisensi", icon: Key },
            { num: 2, label: "Profil Usaha ISP", icon: Building2 },
            { num: 3, label: "Akun Admin Billing", icon: User },
          ].map((s) => {
            const Icon = s.icon
            const isActive = step === s.num
            const isDone = step > s.num
            return (
              <div
                key={s.num}
                className={`flex items-center gap-2 p-3 rounded-xl border transition-all ${
                  isActive
                    ? "border-[#0073C6] bg-[#0073C6]/15 text-[#00C2FF] font-bold"
                    : isDone
                    ? "border-emerald-500/40 bg-emerald-500/10 text-emerald-400"
                    : "border-slate-800 bg-[#0B0F19] text-slate-500"
                }`}
              >
                <div
                  className={`h-7 w-7 rounded-lg flex items-center justify-center text-xs font-bold shrink-0 ${
                    isActive
                      ? "bg-[#0073C6] text-white"
                      : isDone
                      ? "bg-emerald-500 text-slate-950 font-black"
                      : "bg-slate-800 text-slate-400"
                  }`}
                >
                  {isDone ? <CheckCircle2 className="h-4 w-4" /> : <Icon className="h-3.5 w-3.5" />}
                </div>
                <div className="hidden sm:block min-w-0">
                  <div className="text-[10px] uppercase tracking-wider opacity-70">Langkah {s.num}</div>
                  <div className="text-xs truncate">{s.label}</div>
                </div>
              </div>
            )
          })}
        </div>

        {/* Card Container */}
        <Card className="border border-slate-800 bg-[#0B0F19]/90 backdrop-blur-xl shadow-sm rounded-2xl overflow-hidden">
          <CardContent className="p-6 sm:p-8">
            {/* ================= STEP 1: LICENSE ================= */}
            {step === 1 && (
              <div className="space-y-5">
                <div className="border-b border-slate-800/80 pb-4">
                  <h2 className="text-lg font-bold text-white flex items-center gap-2">
                    <Key className="h-5 w-5 text-[#00C2FF]" />
                    Aktivasi Lisensi NODERA Billing
                  </h2>
                  <p className="text-xs text-slate-400 mt-1">
                    Setiap lisensi secara otomatis mengunci ke perangkat server (Machine ID & IP) ini.
                  </p>
                </div>

                {licenseError && (
                  <div className="p-3.5 rounded-xl border border-rose-500/40 bg-rose-500/10 text-rose-300 text-xs flex items-start gap-2.5">
                    <AlertCircle className="h-4 w-4 shrink-0 text-rose-400 mt-0.5" />
                    <span>{licenseError}</span>
                  </div>
                )}

                {licenseValid && licenseData && (
                  <div className="p-4 rounded-2xl border border-emerald-500/40 bg-emerald-500/10 text-emerald-300 space-y-2">
                    <div className="flex items-center gap-2 text-sm font-bold text-emerald-400">
                      <ShieldCheck className="h-5 w-5" />
                      Lisensi Valid & Terverifikasi di Server Pusat
                    </div>
                    <div className="grid grid-cols-2 gap-2 text-xs pt-2 border-t border-emerald-500/20 text-slate-300">
                      <div>
                        <span className="text-slate-500 block text-[10px]">Pemilik Lisensi</span>
                        <span className="font-semibold text-white">{licenseData.client_name || "Pelanggan ISP"}</span>
                      </div>
                      <div>
                        <span className="text-slate-500 block text-[10px]">Paket Lisensi</span>
                        <span className="font-semibold text-white">{licenseData.package_type || "Standalone Enterprise"}</span>
                      </div>
                      <div>
                        <span className="text-slate-500 block text-[10px]">Masa Berlaku</span>
                        <span className="font-semibold text-emerald-400">
                          {licenseData.expires_at ? new Date(licenseData.expires_at).toLocaleDateString("id-ID") : "Seumur Hidup (Lifetime)"}
                        </span>
                      </div>
                      <div>
                        <span className="text-slate-500 block text-[10px]">Hardware Lock</span>
                        <span className="font-mono text-[11px] text-cyan-400">{hardwareId.slice(0, 16)}...</span>
                      </div>
                    </div>
                  </div>
                )}

                <div className="space-y-2">
                  <Label htmlFor="license_key" className="text-xs font-semibold text-slate-300">
                    License Key (format NDR-ISP-XXXX-XXXX)
                  </Label>
                  <div className="relative">
                    <Input
                      id="license_key"
                      placeholder="NDR-ISP-XXXX-XXXX-XXXX-XXXX"
                      value={licenseKey}
                      onChange={(e) => {
                        setLicenseKey(e.target.value.toUpperCase())
                        setLicenseValid(false)
                        setLicenseError(null)
                      }}
                      className="font-mono h-11 bg-[#06080E] border-slate-700 text-white placeholder:text-slate-600 focus:border-[#0073C6] uppercase tracking-wider"
                      disabled={verifying}
                    />
                  </div>
                  <p className="text-[11px] text-slate-500">
                    Belum memiliki lisensi? Hubungi admin NODERA atau pesan via{" "}
                    <a href="https://panel.dgtlnetsolution.com" target="_blank" className="text-[#00C2FF] underline">
                      panel.dgtlnetsolution.com
                    </a>
                  </p>
                </div>

                <div className="pt-3 flex items-center justify-between gap-3">
                  <div className="text-[11px] text-slate-500 font-mono">IP: {serverIp}</div>
                  <div className="flex gap-2">
                    <Button
                      type="button"
                      onClick={handleVerifyLicense}
                      disabled={verifying || !licenseKey.trim()}
                      className="bg-slate-800 hover:bg-slate-700 text-white text-xs h-10 px-4 rounded-xl border border-slate-700"
                    >
                      {verifying ? <Loader2 className="h-4 w-4 animate-spin mr-1.5" /> : <Sparkles className="h-4 w-4 mr-1.5 text-cyan-400" />}
                      Cek & Verifikasi
                    </Button>

                    <Button
                      type="button"
                      onClick={() => {
                        if (!licenseValid) {
                          handleVerifyLicense()
                        } else {
                          setStep(2)
                        }
                      }}
                      disabled={!licenseValid}
                      className="bg-[#0073C6] hover:bg-[#0084E3] text-white font-bold text-xs h-10 px-5 rounded-xl shadow-[#0073C6]/20"
                    >
                      Lanjut <ArrowRight className="h-4 w-4 ml-1.5" />
                    </Button>
                  </div>
                </div>
              </div>
            )}

            {/* ================= STEP 2: COMPANY PROFILE ================= */}
            {step === 2 && (
              <div className="space-y-4">
                <div className="border-b border-slate-800/80 pb-4">
                  <h2 className="text-lg font-bold text-white flex items-center gap-2">
                    <Building2 className="h-5 w-5 text-[#00C2FF]" />
                    Informasi & Identitas Usaha ISP Anda
                  </h2>
                  <p className="text-xs text-slate-400 mt-1">
                    Informasi ini akan tercantum pada invoice tagihan dan nota pembayaran pelanggan Anda.
                  </p>
                </div>

                <div className="space-y-2">
                  <Label htmlFor="company_name" className="text-xs font-semibold text-slate-300">
                    Nama Usaha / Brand ISP <span className="text-rose-400">*</span>
                  </Label>
                  <Input
                    id="company_name"
                    placeholder="Contoh: Airnet Solution, Wifinet ISP"
                    value={companyName}
                    onChange={(e) => setCompanyName(e.target.value)}
                    className="h-10 bg-[#06080E] border-slate-700 text-white placeholder:text-slate-600 focus:border-[#0073C6]"
                  />
                </div>

                <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                  <div className="space-y-2">
                    <Label htmlFor="company_phone" className="text-xs font-semibold text-slate-300">
                      No. WhatsApp / CS Dukungan
                    </Label>
                    <Input
                      id="company_phone"
                      placeholder="Contoh: 081234567890"
                      value={companyPhone}
                      onChange={(e) => setCompanyPhone(e.target.value)}
                      className="h-10 bg-[#06080E] border-slate-700 text-white placeholder:text-slate-600 focus:border-[#0073C6]"
                    />
                  </div>

                  <div className="space-y-2">
                    <Label htmlFor="company_email" className="text-xs font-semibold text-slate-300">
                      Email Penagihan / Kontak
                    </Label>
                    <Input
                      id="company_email"
                      placeholder="kontak@ispanda.com"
                      value={companyEmail}
                      onChange={(e) => setCompanyEmail(e.target.value)}
                      className="h-10 bg-[#06080E] border-slate-700 text-white placeholder:text-slate-600 focus:border-[#0073C6]"
                    />
                  </div>
                </div>

                <div className="pt-4 flex items-center justify-between border-t border-slate-800/80">
                  <Button
                    type="button"
                    variant="outline"
                    onClick={() => setStep(1)}
                    className="border-slate-800 text-slate-400 hover:text-white h-10 px-4 rounded-xl text-xs"
                  >
                    <ArrowLeft className="h-4 w-4 mr-1.5" /> Kembali
                  </Button>

                  <Button
                    type="button"
                    onClick={() => {
                      if (!companyName.trim()) {
                        alert("Silakan isi nama usaha ISP Anda.")
                        return
                      }
                      setStep(3)
                    }}
                    className="bg-[#0073C6] hover:bg-[#0084E3] text-white font-bold text-xs h-10 px-5 rounded-xl shadow-[#0073C6]/20"
                  >
                    Lanjut <ArrowRight className="h-4 w-4 ml-1.5" />
                  </Button>
                </div>
              </div>
            )}

            {/* ================= STEP 3: ADMIN ACCOUNT ================= */}
            {step === 3 && (
              <div className="space-y-4">
                <div className="border-b border-slate-800/80 pb-4">
                  <h2 className="text-lg font-bold text-white flex items-center gap-2">
                    <User className="h-5 w-5 text-[#00C2FF]" />
                    Buat Akun Administrator Billing
                  </h2>
                  <p className="text-xs text-slate-400 mt-1">
                    Akun ini memiliki hak akses penuh untuk mengelola router MikroTik, OLT, pelanggan, tagihan, kasir, dan teknisi.
                  </p>
                </div>

                {submitError && (
                  <div className="p-3.5 rounded-xl border border-rose-500/40 bg-rose-500/10 text-rose-300 text-xs flex items-start gap-2.5">
                    <AlertCircle className="h-4 w-4 shrink-0 text-rose-400 mt-0.5" />
                    <span>{submitError}</span>
                  </div>
                )}

                <div className="space-y-2">
                  <Label htmlFor="admin_name" className="text-xs font-semibold text-slate-300">
                    Nama Lengkap Admin <span className="text-rose-400">*</span>
                  </Label>
                  <Input
                    id="admin_name"
                    placeholder="Contoh: Admin Billing ISP"
                    value={adminName}
                    onChange={(e) => setAdminName(e.target.value)}
                    className="h-10 bg-[#06080E] border-slate-700 text-white placeholder:text-slate-600 focus:border-[#0073C6]"
                  />
                </div>

                <div className="space-y-2">
                  <Label htmlFor="admin_email" className="text-xs font-semibold text-slate-300">
                    Username / Email Login <span className="text-rose-400">*</span>
                  </Label>
                  <Input
                    id="admin_email"
                    placeholder="admin@isp.com atau admin"
                    value={adminEmail}
                    onChange={(e) => setAdminEmail(e.target.value)}
                    className="h-10 bg-[#06080E] border-slate-700 text-white placeholder:text-slate-600 focus:border-[#0073C6]"
                  />
                </div>

                <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                  <div className="space-y-2">
                    <Label htmlFor="admin_pass" className="text-xs font-semibold text-slate-300">
                      Password Login <span className="text-rose-400">*</span>
                    </Label>
                    <div className="relative">
                      <Input
                        id="admin_pass"
                        type={showPassword ? "text" : "password"}
                        placeholder="••••••••"
                        value={adminPassword}
                        onChange={(e) => setAdminPassword(e.target.value)}
                        className="h-10 pr-10 bg-[#06080E] border-slate-700 text-white placeholder:text-slate-600 focus:border-[#0073C6]"
                      />
                      <button
                        type="button"
                        onClick={() => setShowPassword(!showPassword)}
                        className="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-500 hover:text-slate-300 p-1"
                      >
                        {showPassword ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                      </button>
                    </div>
                  </div>

                  <div className="space-y-2">
                    <Label htmlFor="admin_pass_confirm" className="text-xs font-semibold text-slate-300">
                      Konfirmasi Password <span className="text-rose-400">*</span>
                    </Label>
                    <Input
                      id="admin_pass_confirm"
                      type={showPassword ? "text" : "password"}
                      placeholder="••••••••"
                      value={adminPasswordConfirm}
                      onChange={(e) => setAdminPasswordConfirm(e.target.value)}
                      className="h-10 bg-[#06080E] border-slate-700 text-white placeholder:text-slate-600 focus:border-[#0073C6]"
                    />
                  </div>
                </div>

                <div className="pt-4 flex items-center justify-between border-t border-slate-800/80">
                  <Button
                    type="button"
                    variant="outline"
                    onClick={() => setStep(2)}
                    disabled={submitting}
                    className="border-slate-800 text-slate-400 hover:text-white h-10 px-4 rounded-xl text-xs"
                  >
                    <ArrowLeft className="h-4 w-4 mr-1.5" /> Kembali
                  </Button>

                  <Button
                    type="button"
                    onClick={handleCompleteSetup}
                    disabled={submitting || !adminName.trim() || !adminEmail.trim() || !adminPassword}
                    className="bg-gradient-to-r from-[#0073C6] to-cyan-500 hover:brightness-110 text-slate-950 font-extrabold text-xs h-11 px-6 rounded-xl shadow-sm shadow-[#0073C6]/20 transition-all"
                  >
                    {submitting ? (
                      <>
                        <Loader2 className="h-4 w-4 animate-spin mr-2" />
                        Menyelesaikan Konfigurasi...
                      </>
                    ) : (
                      <>
                        Selesaikan & Buka Dashboard <CheckCircle2 className="h-4 w-4 ml-2" />
                      </>
                    )}
                  </Button>
                </div>
              </div>
            )}
          </CardContent>
        </Card>

        {/* Footer Note */}
        <p className="text-center text-xs text-slate-500 mt-6">
          NODERA Billing &copy; {new Date().getFullYear()} · Hak Cipta Dilindungi.
        </p>
      </div>
    </div>
  )
}
