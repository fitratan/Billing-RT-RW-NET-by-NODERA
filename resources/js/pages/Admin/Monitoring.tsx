import { AppLayout } from "@/components/layout/app-layout"
import {
  Activity,
  AlertTriangle,
  Cpu,
  Database,
  HardDrive,
  MemoryStick,
  Server,
  Timer,
  CheckCircle2,
  XCircle,
  ChevronLeft,
  RefreshCw,
} from "lucide-react"
import { Card, CardContent } from "@/components/ui/card"
import { PageProps } from "@/types"
import { cn } from "@/lib/utils"
import { adminSidebarItems, adminNavItems, adminBrand } from "@/lib/admin-nav"
import { Link, router } from "@inertiajs/react"

const STATUS_MAP = {
  healthy: { label: "Sehat", cls: "bg-emerald-500/10 text-emerald-500", icon: CheckCircle2 },
  warning: { label: "Perhatian", cls: "bg-amber-500/10 text-amber-500", icon: AlertTriangle },
  critical: { label: "Kritis", cls: "bg-rose-500/10 text-rose-500", icon: XCircle },
}

interface Metrics {
  cpu: { percentage?: number; model?: string; cores?: number; load?: number[] }
  memory: { percentage?: number; used?: string; total?: string }
  disk: { percentage?: number; used?: string; total?: string; free?: string }
  uptime: { text?: string; days?: number }
  database: { size?: string; totalTables?: number; totalRecords?: number }
  server: { hostname?: string; php_version?: string; laravel_version?: string; os?: string }
}

export default function MonitoringPage({ healthStatus }: PageProps<{
  healthStatus: {
    status: string
    issues: string[]
    warnings: string[]
    timestamp: string | null
    metrics: Metrics
  }
}>) {
  const status = healthStatus?.status ?? "unknown"
  const issues = healthStatus?.issues ?? []
  const warnings = healthStatus?.warnings ?? []
  const m = healthStatus?.metrics

  const statusConfig = STATUS_MAP[status as keyof typeof STATUS_MAP] ?? { label: status, cls: "bg-muted text-muted-foreground", icon: Activity }
  const StatusIcon = statusConfig.icon

  const metrics = [
    { label: "CPU", value: m?.cpu?.percentage != null ? `${m.cpu.percentage}%` : "-", icon: Cpu, sub: m?.cpu?.model ? String(m.cpu.model).slice(0, 30) : undefined },
    { label: "Memory", value: m?.memory?.percentage != null ? `${m.memory.percentage}%` : "-", icon: MemoryStick, sub: m?.memory?.used ? `${m.memory.used} / ${m.memory.total}` : undefined },
    { label: "Disk", value: m?.disk?.percentage != null ? `${m.disk.percentage}%` : "-", icon: HardDrive, sub: m?.disk?.free ? `free ${m.disk.free}` : undefined },
    { label: "Uptime", value: m?.uptime?.text ?? "-", icon: Timer },
    { label: "Database", value: m?.database?.size ?? "-", icon: Database, sub: m?.database?.totalTables != null ? `${m.database.totalTables} tabel · ${m.database.totalRecords} record` : undefined },
    { label: "Server", value: m?.server?.hostname ?? "-", icon: Server, sub: m?.server?.os ? String(m.server.os).slice(0, 40) : undefined },
  ]

  return (
    <AppLayout
      title="Monitoring Server"
      brand={adminBrand}
      sidebarItems={adminSidebarItems}
      navItems={adminNavItems}
      hideHeader={true}
      className="p-0 sm:p-0 max-w-none bg-[#0B0E14]"
    >
      <div className="min-h-screen bg-[#0B0E14] text-slate-100 select-none pb-36 sm:pb-44">
        {/* Sticky Header */}
        <div className="sticky top-0 z-30 bg-[#0B0E14]/90 backdrop-blur-xl border-b border-[#1E2633] px-4 pt-4 pb-3 sm:px-8">
          <div className="max-w-7xl 2xl:max-w-[1700px] mx-auto flex items-center justify-between gap-3">
            <div className="flex items-center gap-3">
              <Link
                href="/admin/semua-fitur"
                className="flex h-9 w-9 items-center justify-center rounded-xl border border-[#212B3B] bg-[#121720] hover:bg-[#18202C] text-slate-300 hover:text-white transition-all active:scale-95 lg:hidden"
                title="Kembali ke Semua Fitur"
              >
                <ChevronLeft className="h-5 w-5" />
              </Link>
              <div>
                <h1 className="font-display text-base font-bold text-white">Status Kesehatan Server</h1>
              </div>
            </div>

            <button
              onClick={() => router.reload()}
              className="flex h-9 w-9 items-center justify-center rounded-xl border border-[#212B3B] bg-[#121720] hover:bg-[#18202C] text-slate-300 hover:text-white transition-all active:scale-95"
              title="Refresh"
            >
              <RefreshCw className="h-4 w-4" />
            </button>
          </div>
        </div>

        {/* Content Body */}
        <div className="max-w-7xl 2xl:max-w-[1700px] mx-auto px-4 pt-5 sm:px-8 space-y-4">
          <div className="flex items-center gap-3.5 rounded-2xl border border-[#212B3B] bg-[#121720] p-5 shadow-sm text-white">
            <div className={cn("flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl", statusConfig.cls)}>
              <StatusIcon className="h-5 w-5" />
            </div>
            <div className="min-w-0 flex-1">
              <p className="font-display text-base font-bold text-white">Status Server: {statusConfig.label}</p>
              <p className="truncate text-xs text-slate-400 mt-0.5">
                Host: {m?.server?.hostname ?? "localhost"} · {m?.server?.os ?? "Linux"}
              </p>
            </div>
          </div>

          {issues.length > 0 ? (
            <div className="rounded-2xl border border-rose-500/30 bg-rose-500/10 p-4 text-white">
              <h3 className="mb-2 flex items-center gap-1.5 font-display text-sm font-bold text-rose-400"><XCircle className="h-4 w-4" /> Masalah</h3>
              {issues.map((i, idx) => <p key={idx} className="py-0.5 text-xs text-rose-300">{i}</p>)}
            </div>
          ) : null}

          {warnings.length > 0 ? (
            <div className="rounded-2xl border border-amber-500/30 bg-amber-500/10 p-4 text-white">
              <h3 className="mb-2 flex items-center gap-1.5 font-display text-sm font-bold text-amber-400"><AlertTriangle className="h-4 w-4" /> Peringatan</h3>
              {warnings.map((w, idx) => <p key={idx} className="py-0.5 text-xs text-amber-300">{w}</p>)}
            </div>
          ) : null}

          <div className="grid grid-cols-2 gap-3 lg:grid-cols-3">
            {metrics.map((mt) => (
              <div key={mt.label} className="p-4 rounded-2xl border border-[#1E2633] bg-[#121720] text-white">
                <div className="flex items-center gap-2 text-slate-400">
                  <mt.icon className="h-4 w-4 text-[#00C2FF]" />
                  <span className="text-xs font-semibold">{mt.label}</span>
                </div>
                <p className="mt-1.5 truncate font-display text-xl font-bold leading-tight text-white">{mt.value}</p>
                {mt.sub ? <p className="mt-0.5 truncate text-xs text-slate-400">{mt.sub}</p> : null}
              </div>
            ))}
          </div>

          <p className="pt-2 text-center text-xs text-slate-500">
            Terakhir cek: {healthStatus?.timestamp ? new Date(healthStatus.timestamp).toLocaleString("id-ID") : "-"}
          </p>
        </div>
      </div>
    </AppLayout>
  )
}
