import { AppLayout } from "@/components/layout/app-layout"
import { Home, LogOut, Receipt, Search, CheckCircle2 } from "lucide-react"
import { Card, CardContent } from "@/components/ui/card"
import { Input } from "@/components/ui/input"
import { EmptyState } from "@/components/ui/empty-state"
import { PageProps } from "@/types"
import { formatIDR, timeAgo } from "@/lib/utils"
import { useState, useEffect, useRef } from "react"

interface Tx { id: number; invoice_number: string; amount: number; method: string; created_at: string }

export default function CashierDashboardPage({ todayTransactions, todayCount, recentTx }: PageProps<{
  todayTransactions: number
  todayCount: number
  recentTx: Tx[]
}>) {
  const [search, setSearch] = useState("")
  const [found, setFound] = useState<{ id: number; invoice_number: string; amount: number; customer_name: string } | null>(null)
  const [searching, setSearching] = useState(false)
  const isFirstRender = useRef(true)

  const doSearch = (term = search) => {
    if (!term.trim()) {
      setFound(null)
      return
    }
    setSearching(true)
    setFound(null)
    fetch(`/api/invoice/search?q=${encodeURIComponent(term)}`)
      .then((r) => r.json())
      .then((d) => { if (d.invoice) setFound(d.invoice); setSearching(false) })
      .catch(() => setSearching(false))
  }

  useEffect(() => {
    if (isFirstRender.current) {
      isFirstRender.current = false
      return
    }
    const timeout = setTimeout(() => {
      doSearch(search)
    }, 300)
    return () => clearTimeout(timeout)
  }, [search])

  return (
    <AppLayout
      title="Kasir"
      brand={{ name: "NODERA", sub: "Panel Kasir", logo: "/images/logo-white.png?v=36" }}
      sidebarItems={[
        { href: "/cashier/dashboard", label: "Beranda", icon: Home },
        { href: "/cashier/logout", label: "Keluar", icon: LogOut },
      ]}
      navItems={[
        { href: "/cashier/dashboard", label: "Beranda", icon: Home },
        { href: "/cashier/logout", label: "Keluar", icon: LogOut },
      ]}
      hideHeader={true}
      className="p-0 sm:p-0 max-w-none bg-[#0B0E14]"
    >
      <div className="min-h-screen bg-[#0B0E14] text-slate-100 select-none pb-36 sm:pb-44">
        {/* Top Sticky App Bar */}
        <div className="sticky top-0 z-30 bg-[#0B0E14]/90 backdrop-blur-xl border-b border-[#1E2633] px-3 pt-3 pb-2.5 sm:px-6 lg:px-8 sm:py-4">
          <div className="max-w-7xl 2xl:max-w-[1600px] mx-auto flex items-center justify-between gap-2.5 sm:gap-4">
            <div className="flex items-center gap-2.5 sm:gap-3 min-w-0 flex-1 overflow-hidden">
              <div className="min-w-0 flex-1 overflow-hidden flex flex-col justify-center">
                <h1 className="font-display text-sm sm:text-lg lg:text-xl font-bold text-white truncate whitespace-nowrap block w-full">
                  Panel Kasir &amp; Pembayaran
                </h1>
              </div>
            </div>

            <div className="flex items-center gap-1.5 sm:gap-2.5 shrink-0 [&>*]:shrink-0">
              <a
                href="/cashier/logout"
                className="flex h-9 sm:h-10 items-center gap-1.5 rounded-xl border border-rose-500/30 bg-rose-500/10 text-rose-400 hover:bg-rose-500/20 px-3 sm:px-4 text-xs sm:text-sm font-bold transition-all shadow-sm active:scale-95"
              >
                <LogOut className="h-4 w-4 shrink-0" />
                <span className="hidden sm:inline">Keluar</span>
              </a>
            </div>
          </div>
        </div>

        <div className="max-w-4xl mx-auto px-3 sm:px-6 lg:px-8 pt-4 sm:pt-6 space-y-4 sm:space-y-6">
          <div className="grid grid-cols-2 gap-3">
            <div className="rounded-2xl border border-emerald-500/20 bg-[#121720] p-4 shadow-sm">
              <p className="text-xs text-slate-400 font-medium">Penerimaan Hari Ini</p>
              <p className="mt-1 font-display text-xl sm:text-2xl font-bold font-mono text-emerald-400">{formatIDR(todayTransactions)}</p>
            </div>
            <div className="rounded-2xl border border-blue-500/20 bg-[#121720] p-4 shadow-sm">
              <p className="text-xs text-slate-400 font-medium">Total Transaksi</p>
              <p className="mt-1 font-display text-xl sm:text-2xl font-bold font-mono text-[#00C2FF]">{todayCount}</p>
            </div>
          </div>

          <div className="flex gap-2">
            <div className="relative flex-1">
              <Search className="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
              <input
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                onKeyDown={(e) => e.key === "Enter" && doSearch()}
                placeholder="Cari nomor invoice pelanggan..."
                className="flex h-11 w-full rounded-2xl border border-[#1E2633] bg-[#121720] pl-10 pr-4 text-xs sm:text-sm text-white placeholder:text-slate-400 shadow-sm outline-none transition-all focus:border-[#00C2FF] focus:ring-2 focus:ring-[#00C2FF]/20"
              />
            </div>
            <button
              onClick={doSearch}
              className="rounded-2xl bg-[#0073C6] hover:bg-[#0084E3] border border-[#0094FF]/40 px-5 text-xs font-bold text-white active:scale-95 transition-all"
              disabled={searching}
            >
              {searching ? "..." : "Cari"}
            </button>
          </div>

          {found ? (
            <div className="rounded-2xl border border-emerald-500/30 bg-[#121720] p-5 shadow-sm space-y-2">
              <p className="text-sm font-bold text-white">{found.customer_name}</p>
              <p className="font-mono text-xs text-slate-400">{found.invoice_number}</p>
              <p className="mt-1 font-display text-2xl font-black text-emerald-400 font-mono">{formatIDR(found.amount)}</p>
              <form action="/cashier/pay" method="POST" className="mt-3">
                <input type="hidden" name="_token" value={csrf()} />
                <input type="hidden" name="invoice_id" value={found.id} />
                <button type="submit" className="flex h-11 w-full items-center justify-center gap-2 rounded-2xl bg-emerald-600 hover:bg-emerald-500 text-xs sm:text-sm font-bold text-white active:scale-95 transition-all">
                  <CheckCircle2 className="h-4 w-4" /> Terima Pembayaran
                </button>
              </form>
            </div>
          ) : null}

          <div className="space-y-3 pt-2">
            <h2 className="font-display text-xs font-bold uppercase tracking-wider text-slate-400">Transaksi Terbaru</h2>
            {recentTx.length === 0 ? (
              <div className="rounded-2xl border border-[#1E2633] bg-[#121720] p-10 text-center shadow-sm">
                <div className="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-[#18202C] text-slate-400 mb-3 border border-[#212B3B]">
                  <Receipt className="h-6 w-6" />
                </div>
                <p className="text-xs font-bold text-white">Belum ada transaksi</p>
              </div>
            ) : (
              <div className="rounded-2xl border border-[#1E2633] bg-[#121720] divide-y divide-[#1E2633] overflow-hidden shadow-sm">
                {recentTx.map((t: Tx) => (
                  <div key={t.id} className="flex items-center gap-3 px-4 py-3.5 hover:bg-[#18202C]/60 transition-colors">
                    <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-blue-500/10 border border-blue-500/20 text-[#00C2FF]"><Receipt className="h-4 w-4" /></div>
                    <div className="min-w-0 flex-1">
                      <p className="font-mono text-xs font-bold text-white">{t.invoice_number}</p>
                      <p className="text-[11px] text-slate-400 mt-0.5">{t.method ?? "cash"} · {timeAgo(t.created_at)}</p>
                    </div>
                    <span className="shrink-0 text-sm font-bold font-mono text-emerald-400">{formatIDR(t.amount)}</span>
                  </div>
                ))}
              </div>
            )}
          </div>
        </div>
      </div>
    </AppLayout>
  )
}

function csrf(): string {
  const meta = document.querySelector('meta[name="csrf-token"]')
  return meta ? meta.getAttribute("content") ?? "" : ""
}
