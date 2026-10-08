import { AppLayout } from "@/components/layout/app-layout"
import { Home, History, LogOut, ArrowDownCircle, ArrowUpCircle } from "lucide-react"
import { Card } from "@/components/ui/card"
import { EmptyState } from "@/components/ui/empty-state"
import { PageProps } from "@/types"
import { cn, formatIDR, timeAgo } from "@/lib/utils"

interface Tx { id: number; type: string; amount: number; balance_before: number; balance_after: number; note: string | null; created_at: string | null }

export default function AgentTransactionsPage({ agent, transactions }: PageProps<{
  agent: { name: string; balance: number }
  transactions: Tx[]
}>) {
  return (
    <AppLayout
      title="Riwayat Transaksi"
      subtitle={`${agent.name} · saldo ${formatIDR(agent.balance)}`}
      brand={{ name: "NODERA", sub: "Portal Agen", logo: "/images/logo-white.png?v=36" }}
      sidebarItems={[
        { href: "/agent/dashboard", label: "Beranda", icon: Home },
        { href: "/agent/transactions", label: "Riwayat", icon: History },
      ]}
      navItems={[
        { href: "/agent/dashboard", label: "Beranda", icon: Home },
        { href: "/agent/transactions", label: "Riwayat", icon: History },
        { href: "/agent/logout", label: "Keluar", icon: LogOut },
      ]}
    >
      {transactions.length === 0 ? (
        <Card><EmptyState icon={<History className="h-7 w-7" />} title="Belum ada transaksi" /></Card>
      ) : (
        <Card>
          {transactions.map((t: Tx) => {
            const inflow = t.type === "payment" || t.type === "topup" || t.type === "commission"
            return (
              <div key={t.id} className="flex items-center gap-3 border-b border-border px-4 py-3.5 last:border-b-0">
                <div className={cn("flex h-9 w-9 shrink-0 items-center justify-center rounded-xl", inflow ? "bg-emerald-500/10 text-emerald-500" : "bg-sky-500/10 text-sky-500")}>
                  {inflow ? <ArrowDownCircle className="h-4 w-4" /> : <ArrowUpCircle className="h-4 w-4" />}
                </div>
                <div className="min-w-0 flex-1">
                  <p className="text-sm font-medium capitalize">{t.type}</p>
                  <p className="truncate text-xs text-muted-foreground">{t.note ?? "-"} · {timeAgo(t.created_at)}</p>
                  <p className="text-[11px] text-muted-foreground">Saldo {formatIDR(t.balance_before)} → {formatIDR(t.balance_after)}</p>
                </div>
                <span className={cn("shrink-0 text-sm font-semibold", inflow ? "text-emerald-500" : "text-foreground")}>
                  {inflow ? "+" : "-"}{formatIDR(Math.abs(t.amount))}
                </span>
              </div>
            )
          })}
        </Card>
      )}
    </AppLayout>
  )
}
