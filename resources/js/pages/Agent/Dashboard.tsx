import { AppLayout } from "@/components/layout/app-layout"
import {
  Home,
  Wallet,
  History,
  LogOut,
  Search,
  Receipt,
  Ticket,
  CheckCircle2,
} from "lucide-react"
import { Card } from "@/components/ui/card"
import { Input } from "@/components/ui/input"
import { EmptyState } from "@/components/ui/empty-state"
import { PageProps } from "@/types"
import { cn, formatIDR, timeAgo } from "@/lib/utils"
import { router } from "@inertiajs/react"
import { useState, useEffect, useRef } from "react"

interface Tx { id: number; type: string; amount: number; description: string | null; created_at: string | null }
interface UnpaidInvoice { id: number; invoice_number: string; amount: number; customer_name: string | null; customer_id: number; due_date: string | null }
interface VoucherPkg { id: number; name: string; price: number; duration_days: number }

export default function AgentDashboardPage({ agent, type, query, transactions, unpaidInvoices, voucherPackages }: PageProps<{
  agent: { name: string; username: string }
  type: string
  query: string
  transactions: Tx[]
  unpaidInvoices: UnpaidInvoice[]
  voucherPackages: VoucherPkg[]
}>) {
  const [q, setQ] = useState(query)
  const [tab, setTab] = useState(type)
  const isFirstRender = useRef(true)

  const search = () => router.get("/agent/dashboard", { type: tab, query: q }, { preserveState: true, replace: true })

  useEffect(() => {
    if (isFirstRender.current) {
      isFirstRender.current = false
      return
    }
    const timeout = setTimeout(() => {
      search()
    }, 300)
    return () => clearTimeout(timeout)
  }, [q, tab])

  return (
    <AppLayout
      hideBack
      title="Dashboard Agen"
      subtitle={`Selamat datang, ${agent.name}`}
      brand={{ name: "NODERA", sub: "Portal Agen", logo: "/images/logo-white.png?v=36" }}
      sidebarItems={[
        { href: "/agent/dashboard", label: "Beranda", icon: Home },
        { href: "/agent/transactions", label: "Riwayat", icon: History },
      ]}
      navItems={[
        { href: "/agent/dashboard", label: "Beranda", icon: Home },
        { href: "/agent/dashboard?type=payment", label: "Tagih", icon: Receipt },
        { href: "/agent/dashboard?type=voucher", label: "Voucher", icon: Ticket },
        { href: "/agent/transactions", label: "Riwayat", icon: History },
        { href: "/agent/logout", label: "Keluar", icon: LogOut },
      ]}
      headerRight={
        <a href="/agent/logout" className="hidden h-9 items-center gap-1.5 rounded-lg border border-border px-3 text-sm font-medium text-muted-foreground hover:bg-accent lg:inline-flex">
          <LogOut className="h-4 w-4" /> Keluar
        </a>
      }
    >
      {/* Mode tabs */}
      <div className="grid grid-cols-2 gap-2">
        <button onClick={() => setTab("payment")} className={cn("rounded-xl border-2 py-3 text-xs font-medium", tab === "payment" ? "border-primary bg-primary/10 text-primary" : "border-border text-muted-foreground")}>Bayar Tagihan</button>
        <button onClick={() => setTab("voucher")} className={cn("rounded-xl border-2 py-3 text-xs font-medium", tab === "voucher" ? "border-primary bg-primary/10 text-primary" : "border-border text-muted-foreground")}>Jual Voucher</button>
      </div>

      {/* Search customer */}
      {tab === "payment" ? (
        <div className="mt-3 flex gap-2">
          <div className="relative flex-1">
            <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
            <Input value={q} onChange={(e) => setQ(e.target.value)} onKeyDown={(e) => e.key === "Enter" && search()} placeholder="Cari pelanggan (nama/HP/PPPoE)" className="pl-9" />
          </div>
          <button onClick={search} className="rounded-lg bg-primary px-4 text-sm font-medium text-primary-foreground">Cari</button>
        </div>
      ) : (
        <div className="mt-3 space-y-2">
          {voucherPackages.map((v: VoucherPkg) => (
            <Card key={v.id} className="p-4">
              <div className="flex items-center gap-3">
                <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary"><Ticket className="h-4 w-4" /></div>
                <div className="min-w-0 flex-1">
                  <p className="text-sm font-medium">{v.name}</p>
                  <p className="text-xs text-muted-foreground">{v.duration_days} hari</p>
                </div>
                <span className="text-sm font-semibold text-primary">{formatIDR(v.price)}</span>
              </div>
            </Card>
          ))}
        </div>
      )}

      {/* Unpaid invoices result */}
      {tab === "payment" && unpaidInvoices.length > 0 ? (
        <div className="mt-3 space-y-2">
          {unpaidInvoices.map((i: UnpaidInvoice) => (
            <Card key={i.id} className="p-4">
              <div className="flex items-center gap-3">
                <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-amber-500/10 text-amber-500"><Receipt className="h-4 w-4" /></div>
                <div className="min-w-0 flex-1">
                  <p className="truncate text-sm font-medium">{i.customer_name ?? "-"}</p>
                  <p className="font-mono text-xs text-muted-foreground">{i.invoice_number}</p>
                </div>
                <span className="text-sm font-semibold">{formatIDR(i.amount)}</span>
                <form action="/agent/pay-invoice" method="POST">
                  <input type="hidden" name="_token" value={csrf()} />
                  <input type="hidden" name="invoice_id" value={i.id} />
                  <input type="hidden" name="customer_id" value={i.customer_id} />
                  <button type="submit" className="flex h-8 items-center gap-1 rounded-lg bg-emerald-500 px-3 text-xs font-medium text-white"><CheckCircle2 className="h-3.5 w-3.5" /> Bayar</button>
                </form>
              </div>
            </Card>
          ))}
        </div>
      ) : null}

      {/* Recent transactions */}
      <div className="mt-6">
        <h2 className="mb-2.5 font-display text-sm font-semibold">Transaksi Terbaru</h2>
        {transactions.length === 0 ? (
          <Card><EmptyState icon={<Wallet className="h-7 w-7" />} title="Belum ada transaksi" /></Card>
        ) : (
          <Card>
            {transactions.map((t: Tx) => (
              <div key={t.id} className="flex items-center gap-3 border-b border-border px-4 py-3 last:border-b-0">
                <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-muted text-muted-foreground"><Wallet className="h-4 w-4" /></div>
                <div className="min-w-0 flex-1">
                  <p className="text-sm font-medium capitalize">{t.type}</p>
                  <p className="truncate text-xs text-muted-foreground">{t.description ?? "-"} · {timeAgo(t.created_at)}</p>
                </div>
                <span className="shrink-0 text-sm font-semibold">{formatIDR(t.amount)}</span>
              </div>
            ))}
          </Card>
        )}
      </div>
    </AppLayout>
  )
}

function csrf(): string {
  const meta = document.querySelector('meta[name="csrf-token"]')
  return meta ? meta.getAttribute("content") ?? "" : ""
}
