import { useState } from "react"
import { AppLayout } from "@/components/layout/app-layout"
import { Copy, Check, Globe, ShieldCheck, KeyRound, Activity } from "lucide-react"
import { PageProps } from "@/types"
import { adminSidebarItems, adminNavItems, adminBrand } from "@/lib/admin-nav"
import MetricCard from "@/components/tailadmin/MetricCard"

export default function ApiAppsPage({
  webhookUrls = {},
}: PageProps<{
  settings: Record<string, string>
  webhookUrls: Record<string, string>
}>) {
  const [copied, setCopied] = useState<string | null>(null)

  const copy = (text: string, key: string) => {
    navigator.clipboard?.writeText(text).then(() => {
      setCopied(key)
      setTimeout(() => setCopied(null), 1500)
    })
  }

  const endpointCount = Object.keys(webhookUrls || {}).length

  return (
    <AppLayout
      title="API Apps & Webhooks"
            brand={adminBrand}
      sidebarItems={adminSidebarItems}
      navItems={adminNavItems}
    >
      <div className="space-y-6">
        {/* Top Row: MetricCards */}
        <div className="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
          <MetricCard
            title="Total Endpoints"
            value={endpointCount}
            sub="Webhook Aktif"
            icon={<Globe className="h-5 w-5 sm:h-6 sm:w-6 text-brand-500" />}
            iconBgColor="bg-brand-50 dark:bg-brand-500/10"
            iconColor="text-brand-500 dark:text-brand-400"
          />
          <MetricCard
            title="Protokol"
            value="HTTPS Secure"
            sub="SSL / TLS Enkripsi"
            icon={<ShieldCheck className="h-5 w-5 sm:h-6 sm:w-6 text-emerald-500" />}
            iconBgColor="bg-emerald-50 dark:bg-emerald-500/10"
            iconColor="text-emerald-500 dark:text-emerald-400"
          />
          <MetricCard
            title="Status API"
            value="Online"
            sub="Siap Menerima Request"
            icon={<Activity className="h-5 w-5 sm:h-6 sm:w-6 text-amber-500" />}
            iconBgColor="bg-amber-50 dark:bg-amber-500/10"
            iconColor="text-amber-500 dark:text-amber-400"
          />
          <MetricCard
            title="Otentikasi"
            value="Callback Signature"
            sub="Verifikasi Otomatis"
            icon={<KeyRound className="h-5 w-5 sm:h-6 sm:w-6 text-purple-500" />}
            iconBgColor="bg-purple-50 dark:bg-purple-500/10"
            iconColor="text-purple-500 dark:text-purple-400"
          />
        </div>

        {/* Master Card: Webhook URLs */}
        <div className="overflow-hidden rounded-2xl border border-gray-200 bg-white p-5 shadow-xs dark:border-gray-800 dark:bg-white/[0.03] space-y-4">
          <div className="flex items-center justify-between pb-3 border-b border-gray-100 dark:border-gray-800">
            <div className="flex items-center gap-3">
              <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-500 dark:bg-brand-500/10 dark:text-brand-400">
                <Globe className="h-5 w-5" />
              </div>
              <div>
                <h3 className="text-sm font-bold text-gray-900 dark:text-white">
                  URL Webhook Callback Gateway
                </h3>
                <p className="text-xs text-gray-500 dark:text-gray-400">
                  Salin dan tempel alamat URL callback berikut ke dashboard gateway pembayaran yang Anda gunakan
                </p>
              </div>
            </div>
          </div>

          <div className="space-y-3">
            {endpointCount === 0 ? (
              <div className="rounded-xl border border-dashed border-gray-200 dark:border-gray-800 p-8 text-center text-xs text-gray-500 dark:text-gray-400">
                Belum ada webhook endpoint yang terdaftar.
              </div>
            ) : (
              Object.entries(webhookUrls || {}).map(([k, v]) => (
                <div
                  key={k}
                  className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 rounded-xl border border-gray-200 bg-gray-50/50 dark:border-gray-800 dark:bg-gray-900/50 p-3.5 sm:p-4"
                >
                  <div className="min-w-0 flex-1">
                    <div className="flex items-center gap-2">
                      <span className="text-xs font-bold text-gray-900 dark:text-white capitalize">
                        {k.replace(/_/g, " ")}
                      </span>
                      <span className="text-[10px] font-bold px-2 py-0.5 rounded bg-brand-500 text-white uppercase tracking-wider">
                        POST Callback
                      </span>
                    </div>
                    <code className="block truncate font-mono text-xs text-brand-600 dark:text-brand-400 mt-1 select-all bg-white dark:bg-gray-950 px-2.5 py-1.5 rounded-lg border border-gray-200 dark:border-gray-800">
                      {v}
                    </code>
                  </div>
                  <button
                    type="button"
                    onClick={() => copy(v, k)}
                    className="inline-flex h-9 items-center justify-center gap-1.5 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 px-3.5 text-xs font-bold text-gray-700 dark:border-gray-800 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700 transition-all shadow-xs active:scale-95 shrink-0"
                    title="Salin URL Webhook"
                  >
                    {copied === k ? (
                      <>
                        <Check className="h-4 w-4 text-emerald-500" />
                        <span className="text-emerald-600 dark:text-emerald-400">Tersalin</span>
                      </>
                    ) : (
                      <>
                        <Copy className="h-4 w-4" />
                        <span>Salin URL</span>
                      </>
                    )}
                  </button>
                </div>
              ))
            )}
          </div>
        </div>
      </div>
    </AppLayout>
  )
}