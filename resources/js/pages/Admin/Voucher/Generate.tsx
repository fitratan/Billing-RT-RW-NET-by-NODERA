import { AppLayout } from "@/components/layout/app-layout"
import {
  Ticket,
  Layers,
  ArrowLeft,
  Server,
  Hash,
  Clock,
  DollarSign,
  Tag,
  CheckCircle2,
  AlertCircle,
  HelpCircle,
  Play,
} from "lucide-react"
import { PageProps } from "@/types"
import { adminSidebarItems, adminNavItems, adminBrand } from "@/lib/admin-nav"
import { useForm, router, Link } from "@inertiajs/react"
import { useState } from "react"
import { Label } from "@/components/ui/label"
import { MetricCard } from "@/components/tailadmin/MetricCard"
import { cn } from "@/lib/utils"

interface HotspotProfile {
  name: string
  shared_users?: string
  rate_limit?: string
}

export default function VoucherGeneratePage({
  routers = [],
  selectedRouterId,
  servers = [],
  profiles = [],
}: PageProps<{
  routers: { id: number; name: string; host: string }[]
  selectedRouterId: number | null
  servers: string[]
  profiles: string[]
}>) {
  const [currentProfiles, setCurrentProfiles] = useState<string[]>(profiles)
  const [currentServers, setCurrentServers] = useState<string[]>(servers)
  const [loadingProfiles, setLoadingProfiles] = useState(false)

  const form = useForm({
    router_id: selectedRouterId ? String(selectedRouterId) : (routers[0]?.id ? String(routers[0].id) : ""),
    server: "all",
    profile: profiles[0] || "default",
    quantity: 50,
    user_mode: "vc" as "vc" | "up",
    code_length: 6,
    char_pattern: "mix1" as "num" | "lower" | "upper" | "mix" | "mix1",
    prefix: "",
    time_limit: "",
    price: "",
    comment: "",
  })

  const handleRouterChange = (routerId: string) => {
    form.setData("router_id", routerId)
    setLoadingProfiles(true)

    router.get(
      "/admin/voucher/generate",
      { router_id: routerId },
      {
        preserveState: true,
        only: ["servers", "profiles", "selectedRouterId"],
        onSuccess: (page) => {
          const props = page.props as any
          if (props.profiles) {
            setCurrentProfiles(props.profiles)
            if (props.profiles.length > 0 && !props.profiles.includes(form.data.profile)) {
              form.setData("profile", props.profiles[0])
            }
          }
          if (props.servers) {
            setCurrentServers(props.servers)
          }
          setLoadingProfiles(false)
        },
        onError: () => setLoadingProfiles(false),
      }
    )
  }

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    form.post("/admin/voucher/generate", {
      preserveScroll: true,
    })
  }

  const estValue = (parseInt(form.data.price) || 0) * form.data.quantity

  return (
    <AppLayout
      title="Generate Voucher Hotspot"
      brand={adminBrand}
      sidebarItems={adminSidebarItems}
      navItems={adminNavItems}
    >
      <div className="space-y-6">
        {/* Header Actions */}
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
          <div className="flex items-center gap-3">
            <Link
              href="/admin/voucher"
              className="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:hover:bg-gray-800 text-xs font-semibold text-gray-700 dark:text-gray-200 transition"
            >
              <ArrowLeft className="h-4 w-4" />
              <span>Kembali</span>
            </Link>
            <div>
              <h3 className="text-base font-bold text-gray-800 dark:text-white/90">
                Formulir Batch Generator
              </h3>
              <p className="text-xs text-gray-500 dark:text-gray-400">
                Atur profil kuota, batas waktu, dan pola karakter kode voucher
              </p>
            </div>
          </div>

          <div className="flex items-center gap-2">
            <Link
              href="/admin/voucher/packages"
              className="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:hover:bg-gray-800 text-xs font-semibold text-gray-700 dark:text-gray-200 transition"
            >
              <Layers className="h-4 w-4" />
              <span>Paket Voucher</span>
            </Link>
          </div>
        </div>

        {/* ── TOP 4 PREVIEW KPI METRIC CARDS ── */}
        <div className="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
          <MetricCard
            title="Jumlah Voucher"
            value={`${form.data.quantity} Vch`}
            sub="Ukuran batch dibuat"
            icon={Ticket}
            iconBgColor="bg-brand-50 dark:bg-brand-500/10"
            iconColor="text-brand-500 dark:text-brand-400"
            badge={{ text: "Batch", color: "info" }}
          />
          <MetricCard
            title="Mode Voucher"
            value={form.data.user_mode === "vc" ? "User = Pass" : "User != Pass"}
            sub="Format login kredensial"
            icon={Hash}
            iconBgColor="bg-emerald-50 dark:bg-emerald-500/10"
            iconColor="text-emerald-600 dark:text-emerald-400"
            badge={{ text: form.data.user_mode.toUpperCase(), color: "success" }}
          />
          <MetricCard
            title="Panjang Kode"
            value={`${form.data.code_length} Digit`}
            sub={`Pola: ${form.data.char_pattern}`}
            icon={Clock}
            iconBgColor="bg-purple-50 dark:bg-purple-500/10"
            iconColor="text-purple-600 dark:text-purple-400"
            badge={{ text: "Karakter", color: "info" }}
          />
          <MetricCard
            title="Estimasi Omset"
            value={estValue > 0 ? `Rp ${estValue.toLocaleString("id-ID")}` : "Rp 0"}
            sub={form.data.price ? `@ Rp ${parseInt(form.data.price).toLocaleString("id-ID")}` : "Tarif belum diisi"}
            icon={DollarSign}
            iconBgColor="bg-amber-50 dark:bg-amber-500/10"
            iconColor="text-amber-600 dark:text-amber-400"
            badge={{ text: "Est. Total", color: "warning" }}
          />
        </div>

        {/* ── MASTER FORM CONTAINER ── */}
        <form onSubmit={handleSubmit}>
          <div className="p-5 sm:p-7 rounded-2xl border border-gray-200 bg-white shadow-xs dark:border-gray-800 dark:bg-white/[0.03] space-y-6">
            <div>
              <h4 className="text-sm font-bold text-gray-900 dark:text-white mb-1">
                Konfigurasi Target MikroTik
              </h4>
              <p className="text-xs text-gray-500 dark:text-gray-400">
                Pilih router dan profil server hotspot yang menjadi tujuan pembuatan akun
              </p>
            </div>

            <div className="grid gap-4 sm:grid-cols-3">
              <div className="space-y-1.5">
                <div className="flex items-center justify-between">
                  <Label className="text-xs font-semibold text-gray-700 dark:text-gray-300">Router MikroTik *</Label>
                  {loadingProfiles && (
                    <span className="text-[10px] text-brand-500 animate-pulse font-medium">Memuat profil...</span>
                  )}
                </div>
                <select
                  value={form.data.router_id}
                  onChange={(e) => handleRouterChange(e.target.value)}
                  className="h-10 w-full rounded-xl border border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:hover:bg-gray-800 px-3 text-xs font-bold text-gray-900 dark:text-white transition focus:outline-none focus:border-brand-500"
                  required
                >
                  {routers.map((r) => (
                    <option key={r.id} value={r.id}>
                      {r.name} ({r.host})
                    </option>
                  ))}
                </select>
              </div>

              <div className="space-y-1.5">
                <Label className="text-xs font-semibold text-gray-700 dark:text-gray-300">Server Hotspot</Label>
                <select
                  value={form.data.server}
                  onChange={(e) => form.setData("server", e.target.value)}
                  className="h-10 w-full rounded-xl border border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:hover:bg-gray-800 px-3 text-xs text-gray-900 dark:text-white transition focus:outline-none focus:border-brand-500"
                >
                  <option value="all">Semua Server (all)</option>
                  {currentServers.map((s) => (
                    <option key={s} value={s}>
                      {s}
                    </option>
                  ))}
                </select>
              </div>

              <div className="space-y-1.5">
                <Label className="text-xs font-semibold text-gray-700 dark:text-gray-300">Profil Hotspot MikroTik *</Label>
                <select
                  value={form.data.profile}
                  onChange={(e) => form.setData("profile", e.target.value)}
                  className="h-10 w-full rounded-xl border border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:hover:bg-gray-800 px-3 text-xs font-bold text-brand-600 dark:text-brand-400 transition focus:outline-none focus:border-brand-500"
                  required
                >
                  {currentProfiles.length === 0 ? (
                    <option value="default">default</option>
                  ) : (
                    currentProfiles.map((p) => (
                      <option key={p} value={p}>
                        {p}
                      </option>
                    ))
                  )}
                </select>
              </div>
            </div>

            <div className="pt-2 border-t border-gray-100 dark:border-gray-800 space-y-4">
              <div>
                <h4 className="text-sm font-bold text-gray-900 dark:text-white mb-1">
                  Format dan Karakter Kode
                </h4>
                <p className="text-xs text-gray-500 dark:text-gray-400">
                  Tentukan jumlah kuantitas, panjang digit, dan kombinasi acak karakter voucher
                </p>
              </div>

              <div className="space-y-2">
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                  <Label className="text-xs font-semibold text-gray-700 dark:text-gray-300">
                    Jumlah Voucher * (Maks 1.500 per batch)
                  </Label>
                  <div className="flex items-center gap-1.5 flex-wrap">
                    {[20, 50, 100, 250, 500, 1000, 1500].map((qty) => (
                      <button
                        key={qty}
                        type="button"
                        onClick={() => {
                          form.setData("quantity", qty)
                          if (qty >= 500 && form.data.code_length < 6) {
                            form.setData("code_length", 6)
                          }
                        }}
                        className={cn(
                          "px-2.5 py-1 rounded-lg text-xs font-mono font-bold border transition",
                          form.data.quantity === qty
                            ? "bg-brand-500 border-brand-600 text-white shadow-xs"
                            : "bg-white hover:bg-gray-50 border-gray-200 text-gray-700 dark:bg-gray-900 dark:border-gray-800 dark:text-gray-300 dark:hover:bg-gray-800"
                        )}
                      >
                        {qty}
                      </button>
                    ))}
                  </div>
                </div>

                <div className="grid gap-4 sm:grid-cols-3">
                  <div className="space-y-1.5">
                    <input
                      type="number"
                      min={1}
                      max={1500}
                      value={form.data.quantity}
                      onChange={(e) => form.setData("quantity", Math.min(1500, parseInt(e.target.value) || 1))}
                      className="h-10 w-full rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 px-3 text-xs font-bold text-gray-900 dark:text-white transition focus:outline-none focus:border-brand-500"
                      required
                    />
                    {form.data.quantity >= 500 && (
                      <p className="text-[11px] text-amber-600 dark:text-amber-400 font-semibold">
                        Batch besar (≥500): disarankan panjang kode minimal 6 karakter.
                      </p>
                    )}
                  </div>

                  <div className="space-y-1.5">
                    <Label className="text-xs font-semibold text-gray-700 dark:text-gray-300">Mode Voucher</Label>
                    <select
                      value={form.data.user_mode}
                      onChange={(e) => form.setData("user_mode", e.target.value as "vc" | "up")}
                      className="h-10 w-full rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 px-3 text-xs text-gray-900 dark:text-white transition focus:outline-none focus:border-brand-500"
                    >
                      <option value="vc">Username = Password</option>
                      <option value="up">Username &amp; Password Beda</option>
                    </select>
                  </div>

                  <div className="space-y-1.5">
                    <Label className="text-xs font-semibold text-gray-700 dark:text-gray-300">Panjang Kode</Label>
                    <input
                      type="number"
                      min={3}
                      max={16}
                      value={form.data.code_length}
                      onChange={(e) => form.setData("code_length", parseInt(e.target.value) || 6)}
                      className="h-10 w-full rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 px-3 text-xs font-mono text-gray-900 dark:text-white transition focus:outline-none focus:border-brand-500"
                      required
                    />
                  </div>
                </div>
              </div>

              <div className="grid gap-4 sm:grid-cols-2">
                <div className="space-y-1.5">
                  <Label className="text-xs font-semibold text-gray-700 dark:text-gray-300">Kombinasi Karakter</Label>
                  <select
                    value={form.data.char_pattern}
                    onChange={(e) => form.setData("char_pattern", e.target.value as any)}
                    className="h-10 w-full rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 px-3 text-xs text-gray-900 dark:text-white transition focus:outline-none focus:border-brand-500"
                  >
                    <option value="mix1">Huruf Kapital &amp; Angka (ABCD2345)</option>
                    <option value="num">Hanya Angka (23456789)</option>
                    <option value="lower">Huruf Kecil Saja (abcd...)</option>
                    <option value="upper">Huruf Kapital Saja (ABCD...)</option>
                    <option value="mix">Huruf Kecil &amp; Angka</option>
                  </select>
                </div>

                <div className="space-y-1.5">
                  <Label className="text-xs font-semibold text-gray-700 dark:text-gray-300">Prefix (Awalan Kode)</Label>
                  <input
                    value={form.data.prefix}
                    onChange={(e) => form.setData("prefix", e.target.value)}
                    placeholder="cth: VCH- / VIP-"
                    className="h-10 w-full rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 px-3 text-xs font-mono text-gray-900 dark:text-white placeholder-gray-400 transition focus:outline-none focus:border-brand-500"
                  />
                </div>
              </div>

              <div className="grid gap-4 sm:grid-cols-3">
                <div className="space-y-1.5">
                  <Label className="text-xs font-semibold text-gray-700 dark:text-gray-300">Time Limit (Masa Aktif)</Label>
                  <input
                    value={form.data.time_limit}
                    onChange={(e) => form.setData("time_limit", e.target.value)}
                    placeholder="cth: 3h / 1d / 30d"
                    className="h-10 w-full rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 px-3 text-xs font-mono text-gray-900 dark:text-white placeholder-gray-400 transition focus:outline-none focus:border-brand-500"
                  />
                </div>

                <div className="space-y-1.5">
                  <Label className="text-xs font-semibold text-gray-700 dark:text-gray-300">Harga Jual (Rp)</Label>
                  <input
                    type="number"
                    value={form.data.price}
                    onChange={(e) => form.setData("price", e.target.value)}
                    placeholder="5000"
                    className="h-10 w-full rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 px-3 text-xs text-gray-900 dark:text-white placeholder-gray-400 transition focus:outline-none focus:border-brand-500"
                  />
                </div>

                <div className="space-y-1.5">
                  <Label className="text-xs font-semibold text-gray-700 dark:text-gray-300">Komentar / Label Batch</Label>
                  <input
                    value={form.data.comment}
                    onChange={(e) => form.setData("comment", e.target.value)}
                    placeholder="cth: Promo Merdeka"
                    className="h-10 w-full rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 px-3 text-xs text-gray-900 dark:text-white placeholder-gray-400 transition focus:outline-none focus:border-brand-500"
                  />
                </div>
              </div>
            </div>

            <div className="pt-4">
              <button
                type="submit"
                disabled={form.processing}
                className="w-full h-11 rounded-xl text-xs font-bold gap-2 bg-brand-500 hover:bg-brand-600 text-white flex items-center justify-center transition shadow-xs disabled:opacity-50"
              >
                <Play className="h-4 w-4" />
                <span>{form.processing ? "Sedang Mengenerate..." : `Generate ${form.data.quantity} Voucher Sekarang`}</span>
              </button>
            </div>
          </div>
        </form>
      </div>
    </AppLayout>
  )
}
