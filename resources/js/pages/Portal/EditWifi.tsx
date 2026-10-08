import { AppLayout } from "@/components/layout/app-layout"
import {
  Wifi,
  KeyRound,
  Save,
  ShieldCheck,
  CheckCircle2,
  AlertCircle,
  Router,
} from "lucide-react"
import { PageProps } from "@/types"
import { useForm, usePage } from "@inertiajs/react"
import { portalNavItems, portalBrand } from "@/lib/portal-nav"

interface OntDeviceInfo {
  serial_number: string
  wifi_ssid?: string
  status: string
  rx_power?: string | number | null
  connected_devices_count?: number
  temperature?: string | number | null
  last_inform_at?: string | null
}

export default function PortalEditWifiPage({
  genieacsConfigured,
  ontDevice,
  customer,
  companyName = "NODERA",
}: PageProps<{
  genieacsConfigured: boolean
  ontDevice?: OntDeviceInfo | null
  customer?: any
  companyName?: string
}>) {
  const { flash } = usePage<PageProps<{ flash?: { msg?: string | null; error?: string | null } }>>().props

  const ssid = useForm<{ ssid: string }>({ ssid: ontDevice?.wifi_ssid || "" })
  const password = useForm<{ password: string }>({ password: "" })
  const portalPwd = useForm<{ portal_password: string; portal_password_confirmation: string }>({
    portal_password: "",
    portal_password_confirmation: "",
  })

  const isLinked = Boolean(genieacsConfigured || ontDevice?.serial_number)

  const rxVal = ontDevice?.rx_power ? parseFloat(String(ontDevice.rx_power)) : null
  const isGoodRx = rxVal !== null && rxVal >= -23.5 && rxVal <= -14.0
  const isWarnRx = rxVal !== null && rxVal < -23.5 && rxVal >= -27.0
  const isBadRx = rxVal !== null && rxVal < -27.0

  return (
    <AppLayout
      title="Pengaturan WiFi & PIN"
      subtitle={companyName}
      brand={portalBrand}
      navItems={portalNavItems}
      hideSidebar={true}
      hideBottomNav={true}
    >
      <div className="space-y-4 max-w-4xl mx-auto">
        {/* Flash Notifications */}
        {flash?.msg && (
          <div className="rounded-2xl border border-emerald-500/20 bg-emerald-500/10 p-3.5 text-emerald-700 dark:text-emerald-400 text-xs font-semibold flex items-center gap-2">
            <CheckCircle2 className="h-4 w-4 shrink-0 text-emerald-500" />
            <span>{flash.msg}</span>
          </div>
        )}
        {flash?.error && (
          <div className="rounded-2xl border border-rose-500/20 bg-rose-500/10 p-3.5 text-rose-700 dark:text-rose-400 text-xs font-semibold flex items-center gap-2">
            <AlertCircle className="h-4 w-4 shrink-0 text-rose-500" />
            <span>{flash.error}</span>
          </div>
        )}

        {/* Status Hubungan TR-069 */}
        {!isLinked ? (
          <div className="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-amber-800 dark:border-amber-500/20 dark:bg-amber-500/5 dark:text-amber-300 space-y-1.5">
            <div className="flex items-center gap-2 font-bold text-xs">
              <AlertCircle className="h-4 w-4 text-amber-500 shrink-0" />
              <span>Modem Belum Tertaut ke Sistem Manajemen Jaringan</span>
            </div>
            <p className="text-[11px] leading-relaxed text-amber-700 dark:text-amber-400">
              Fitur ganti SSID dan password modem mandiri hanya aktif untuk modem yang telah ditautkan ke sistem manajemen jaringan. Hubungi admin untuk aktivasi.
            </p>
          </div>
        ) : (
          ontDevice && (
            <div className="rounded-2xl border border-gray-200/80 bg-white p-4 shadow-xs dark:border-gray-800 dark:bg-white/[0.03] space-y-3">
              <div className="flex items-center justify-between">
                <h3 className="font-bold text-xs text-gray-900 dark:text-white flex items-center gap-1.5">
                  <Router className="h-4 w-4 text-brand-500" />
                  <span>Informasi Modem Pelanggan</span>
                </h3>
                <span
                  className={`inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold ${
                    ontDevice.status === "online"
                      ? "bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400"
                      : "bg-gray-100 text-gray-600 dark:bg-white/[0.04] dark:text-gray-400"
                  }`}
                >
                  <span className={`h-1.5 w-1.5 rounded-full ${ontDevice.status === "online" ? "bg-emerald-500" : "bg-gray-400"}`} />
                  <span>{ontDevice.status === "online" ? "Online" : "Offline"}</span>
                </span>
              </div>

              <div className="grid grid-cols-2 gap-2 text-left">
                <div className="p-2.5 rounded-xl border border-gray-100 bg-gray-50/60 dark:border-gray-800/60 dark:bg-white/[0.02]">
                  <span className="text-[10px] text-gray-400 font-bold block uppercase">Serial Number</span>
                  <p className="font-mono text-xs font-black text-gray-900 dark:text-white mt-0.5 truncate">
                    {ontDevice.serial_number}
                  </p>
                </div>

                <div className="p-2.5 rounded-xl border border-gray-100 bg-gray-50/60 dark:border-gray-800/60 dark:bg-white/[0.02]">
                  <span className="text-[10px] text-gray-400 font-bold block uppercase">Sinyal Rx Optik</span>
                  <div className="flex items-center gap-1.5 mt-0.5">
                    <span className="font-mono text-xs font-black text-gray-900 dark:text-white tabular-nums">
                      {ontDevice.rx_power ? `${ontDevice.rx_power} dBm` : "-"}
                    </span>
                    {isGoodRx && <span className="h-2 w-2 rounded-full bg-emerald-500 shrink-0" />}
                    {isWarnRx && <span className="h-2 w-2 rounded-full bg-amber-500 shrink-0" />}
                    {isBadRx && <span className="h-2 w-2 rounded-full bg-rose-500 shrink-0" />}
                  </div>
                </div>
              </div>
            </div>
          )
        )}

        {/* Form Ubah Nama WiFi & Password (Hanya jika tertaut) */}
        {isLinked && (
          <div className="space-y-3">
            {/* Ubah Nama SSID */}
            <form
              onSubmit={(e) => {
                e.preventDefault()
                ssid.post("/portal/wifi/update-ssid")
              }}
              className="rounded-2xl border border-gray-200/80 bg-white p-4 shadow-xs dark:border-gray-800 dark:bg-white/[0.03] space-y-3"
            >
              <div className="flex items-center gap-1.5">
                <Wifi className="h-4 w-4 text-brand-500" />
                <h3 className="font-bold text-xs sm:text-sm text-gray-900 dark:text-white">Ubah Nama WiFi (SSID)</h3>
              </div>

              <div>
                <label className="block text-[11px] font-semibold text-gray-700 dark:text-gray-300 mb-1">
                  Nama WiFi Baru
                </label>
                <input
                  type="text"
                  value={ssid.data.ssid}
                  onChange={(e) => ssid.setData("ssid", e.target.value)}
                  placeholder="Contoh: WiFi Rumah Kami"
                  required
                  className="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-xs text-gray-900 dark:border-gray-800 dark:bg-white/[0.04] dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-500"
                />
              </div>

              <button
                type="submit"
                disabled={ssid.processing}
                className="flex items-center justify-center gap-1.5 w-full rounded-xl bg-brand-500 hover:bg-brand-600 text-white py-2.5 text-xs font-bold shadow-xs active:scale-95 transition disabled:opacity-50 cursor-pointer"
              >
                <Save className="h-3.5 w-3.5" />
                <span>{ssid.processing ? "Menyimpan..." : "Simpan Nama WiFi"}</span>
              </button>
            </form>

            {/* Ubah Password WiFi */}
            <form
              onSubmit={(e) => {
                e.preventDefault()
                password.post("/portal/wifi/update-password", {
                  onSuccess: () => password.reset("password"),
                })
              }}
              className="rounded-2xl border border-gray-200/80 bg-white p-4 shadow-xs dark:border-gray-800 dark:bg-white/[0.03] space-y-3"
            >
              <div className="flex items-center gap-1.5">
                <KeyRound className="h-4 w-4 text-purple-500" />
                <h3 className="font-bold text-xs sm:text-sm text-gray-900 dark:text-white">Ubah Password WiFi</h3>
              </div>

              <div>
                <label className="block text-[11px] font-semibold text-gray-700 dark:text-gray-300 mb-1">
                  Password WiFi Baru (Min. 8 karakter)
                </label>
                <input
                  type="password"
                  value={password.data.password}
                  onChange={(e) => password.setData("password", e.target.value)}
                  placeholder="Minimal 8 karakter"
                  required
                  minLength={8}
                  className="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-xs text-gray-900 dark:border-gray-800 dark:bg-white/[0.04] dark:text-white focus:outline-none focus:ring-2 focus:ring-purple-500"
                />
              </div>

              <button
                type="submit"
                disabled={password.processing}
                className="flex items-center justify-center gap-1.5 w-full rounded-xl bg-purple-600 hover:bg-purple-700 text-white py-2.5 text-xs font-bold shadow-xs active:scale-95 transition disabled:opacity-50 cursor-pointer"
              >
                <Save className="h-3.5 w-3.5" />
                <span>{password.processing ? "Menyimpan..." : "Simpan Password WiFi"}</span>
              </button>
            </form>
          </div>
        )}

        {/* Ubah PIN / Password Login Portal */}
        <form
          onSubmit={(e) => {
            e.preventDefault()
            portalPwd.post("/portal/wifi/update-portal-password", {
              onSuccess: () => portalPwd.reset(),
            })
          }}
          className="rounded-2xl border border-gray-200/80 bg-white p-4 shadow-xs dark:border-gray-800 dark:bg-white/[0.03] space-y-3"
        >
          <div className="flex items-center gap-1.5">
            <ShieldCheck className="h-4 w-4 text-emerald-500" />
            <h3 className="font-bold text-xs sm:text-sm text-gray-900 dark:text-white">Ubah PIN Portal Pelanggan</h3>
          </div>
          <p className="text-[11px] text-gray-500 dark:text-gray-400">
            PIN ini digunakan untuk login mandiri ke portal pelanggan ini.
          </p>

          <div className="space-y-2">
            <div>
              <label className="block text-[11px] font-semibold text-gray-700 dark:text-gray-300 mb-1">
                PIN Baru
              </label>
              <input
                type="password"
                value={portalPwd.data.portal_password}
                onChange={(e) => portalPwd.setData("portal_password", e.target.value)}
                placeholder="Minimal 4 digit angka / karakter"
                required
                minLength={4}
                className="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-xs text-gray-900 dark:border-gray-800 dark:bg-white/[0.04] dark:text-white focus:outline-none focus:ring-2 focus:ring-emerald-500"
              />
            </div>

            <div>
              <label className="block text-[11px] font-semibold text-gray-700 dark:text-gray-300 mb-1">
                Ulangi PIN Baru
              </label>
              <input
                type="password"
                value={portalPwd.data.portal_password_confirmation}
                onChange={(e) => portalPwd.setData("portal_password_confirmation", e.target.value)}
                placeholder="Konfirmasi PIN baru"
                required
                minLength={4}
                className="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-xs text-gray-900 dark:border-gray-800 dark:bg-white/[0.04] dark:text-white focus:outline-none focus:ring-2 focus:ring-emerald-500"
              />
            </div>
          </div>

          <button
            type="submit"
            disabled={portalPwd.processing}
            className="flex items-center justify-center gap-1.5 w-full rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white py-2.5 text-xs font-bold shadow-xs active:scale-95 transition disabled:opacity-50 cursor-pointer"
          >
            <Save className="h-3.5 w-3.5" />
            <span>{portalPwd.processing ? "Menyimpan..." : "Update PIN Portal"}</span>
          </button>
        </form>
      </div>
    </AppLayout>
  )
}
