import { AppLayout } from "@/components/layout/app-layout"
import { Eye, EyeOff, Save, ChevronLeft, LayoutGrid } from "lucide-react"
import { PageProps } from "@/types"
import { cn } from "@/lib/utils"
import { adminSidebarItems, adminNavItems, adminBrand } from "@/lib/admin-nav"
import { useForm, Link } from "@inertiajs/react"
import { useState } from "react"

const MENU_KEYS = ["dashboard", "analytics", "billing", "customers", "packages", "invoices", "mikrotik", "pppoe", "hotspot", "olt", "genieacs", "map", "trouble", "employees", "inventory", "finance", "broadcast", "voucher"]

export default function SidebarSettingsPage({ settings }: PageProps<{ settings: Record<string, number> }>) {
  const [selected, setSelected] = useState<Set<string>>(
    new Set(MENU_KEYS.filter((k) => settings[k] === 1 || settings[k] === undefined)),
  )
  const form = useForm({ menu: [] as string[] })

  const toggle = (k: string) => {
    const next = new Set(selected)
    if (next.has(k)) next.delete(k)
    else next.add(k)
    setSelected(next)
    form.setData("menu", Array.from(next))
  }

  const submit = (e: React.FormEvent) => {
    e.preventDefault()
    form.post("/admin/sidebar-settings/save", { preserveScroll: true, preserveState: true })
  }

  return (
    <AppLayout
      title="Pengaturan Tampilan Menu"
      brand={adminBrand}
      sidebarItems={adminSidebarItems}
      navItems={adminNavItems}
      hideHeader={true}
      className="p-0 sm:p-0 max-w-none bg-[#0B0E14]"
    >
      <div className="min-h-screen bg-[#0B0E14] text-slate-100 select-none pb-36 sm:pb-44">
        {/* Sticky Header */}
        <div className="sticky top-0 z-30 bg-[#0B0E14]/90 backdrop-blur-xl border-b border-[#1E2633] px-3 pt-3 pb-2.5 sm:px-6 lg:px-8 sm:py-4">
          <div className="max-w-7xl 2xl:max-w-[1700px] mx-auto flex items-center justify-between gap-2.5 sm:gap-4">
            <div className="flex items-center gap-2.5 sm:gap-3 min-w-0 flex-1 overflow-hidden">
              <Link
                href="/admin/semua-fitur"
                className="flex h-9 w-9 sm:h-10 sm:w-10 shrink-0 items-center justify-center rounded-xl border border-[#212B3B] bg-[#121720] hover:bg-[#18202C] text-slate-300 hover:text-white transition-all active:scale-95 lg:hidden"
                title="Kembali ke Semua Fitur"
              >
                <ChevronLeft className="h-5 w-5 shrink-0" />
              </Link>
              <div className="min-w-0 flex-1 overflow-hidden flex flex-col justify-center">
                <h1 className="font-display text-sm sm:text-lg lg:text-xl font-bold text-white truncate whitespace-nowrap block w-full">Pengaturan Visibilitas Menu</h1>
              </div>
            </div>
          </div>
        </div>

        {/* Content Body */}
        <div className="max-w-7xl 2xl:max-w-[1700px] mx-auto px-4 pt-5 sm:px-8 space-y-4">
          <div className="rounded-2xl border border-[#1E2633] bg-[#121720] p-5 text-white">
            <h2 className="mb-4 flex items-center gap-2 font-display text-sm font-bold text-white">
              <LayoutGrid className="h-4 w-4 text-[#00C2FF]" /> Pilih Menu Navigasi
            </h2>
            <form onSubmit={submit} className="space-y-5">
              <div className="grid grid-cols-2 gap-2.5 sm:grid-cols-3">
                {MENU_KEYS.map((k) => {
                  const on = selected.has(k)
                  return (
                    <button
                      key={k}
                      type="button"
                      onClick={() => toggle(k)}
                      className={cn(
                        "flex items-center justify-between gap-2 rounded-xl border p-3 text-xs font-bold capitalize transition-all active:scale-95",
                        on
                          ? "border-[#0073C6] bg-[#0B2138] text-[#00C2FF]"
                          : "border-[#212B3B] bg-[#0B0E14] text-slate-400 hover:border-[#1E2633] hover:text-white"
                      )}
                    >
                      <span>{k}</span>
                      {on ? <Eye className="h-3.5 w-3.5 text-[#00C2FF]" /> : <EyeOff className="h-3.5 w-3.5 text-slate-500" />}
                    </button>
                  )
                })}
              </div>
              <button
                type="submit"
                className="h-10 w-full rounded-xl text-xs font-bold bg-[#0073C6] hover:bg-[#0084E3] border border-[#0094FF]/40 text-white active:scale-95 transition-all flex items-center justify-center gap-2 disabled:opacity-50"
                disabled={form.processing}
              >
                <Save className="h-4 w-4" />
                <span>{form.processing ? "Menyimpan..." : "Simpan Pengaturan Sidebar"}</span>
              </button>
            </form>
          </div>
        </div>
      </div>
    </AppLayout>
  )
}
