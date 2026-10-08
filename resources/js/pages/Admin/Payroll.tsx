import { AppLayout } from "@/components/layout/app-layout"
import {
  Briefcase,
  Plus,
  CheckCircle2,
  Clock,
  Printer,
  Trash2,
  DollarSign,
  Search,
  X,
  Settings,
  ChevronRight,
} from "lucide-react"
import { EmptyState } from "@/components/ui/empty-state"
import { PageProps } from "@/types"
import { formatIDR, cn } from "@/lib/utils"
import { adminSidebarItems, adminNavItems, adminBrand } from "@/lib/admin-nav"
import { useForm, router } from "@inertiajs/react"
import { useState, useMemo } from "react"
import { MetricCard } from "@/components/tailadmin/MetricCard"

interface Payroll {
  id: number
  user_id: number
  user: string
  user_role: string
  salary: number
  deductions: number
  bonus: number
  total: number
  status: "paid" | "pending"
  paid_at: string | null
  period: string
  period_month: number
  period_year: number
}

interface Summary {
  total_paid: number
  total_unpaid: number
  total_amount: number
  total_salary: number
  total_bonus: number
  total_deductions: number
  paid_count: number
  unpaid_count: number
  total_count: number
}

const MONTH_NAMES = [
  "Januari", "Februari", "Maret", "April", "Mei", "Juni",
  "Juli", "Agustus", "September", "Oktober", "November", "Desember"
]

export default function PayrollPage({
  payrolls = [],
  summary,
  employees = [],
  users = [],
  month = new Date().getMonth() + 1,
  year = new Date().getFullYear(),
  create = false,
  companyName = "NODERA Billing",
  tenantName,
}: PageProps<{
  create?: boolean
  employees?: { id: number; name: string; role: string; salary?: number }[]
  users?: { id: number; name: string; role: string; salary?: number }[]
  payrolls: Payroll[]
  summary: Summary
  month: number
  year: number
  companyName?: string
  tenantName?: string
}>) {
  const [modalOpen, setModalOpen] = useState(create)
  const [managingPayroll, setManagingPayroll] = useState<Payroll | null>(null)
  const [selectedMonth, setSelectedMonth] = useState<number>(month)
  const [selectedYear, setSelectedYear] = useState<number>(year)
  const [searchQuery, setSearchQuery] = useState("")
  const [statusFilter, setStatusFilter] = useState<string>("all")

  const staffList = useMemo(() => {
    if (employees && employees.length > 0) return employees
    if (users && users.length > 0) return users
    return []
  }, [employees, users])

  const form = useForm({
    user_id: "",
    salary: "",
    period_month: String(month),
    period_year: String(year),
    bonus: "0",
    deductions: "0",
  })

  const openAdd = () => {
    form.setData({
      user_id: staffList.length > 0 ? String(staffList[0].id) : "",
      salary: staffList.length > 0 && staffList[0].salary ? String(staffList[0].salary) : "",
      period_month: String(selectedMonth),
      period_year: String(selectedYear),
      bonus: "0",
      deductions: "0",
    })
    setModalOpen(true)
  }

  const handlePeriodChange = (newMonth: number, newYear: number) => {
    setSelectedMonth(newMonth)
    setSelectedYear(newYear)
    router.get(
      "/admin/payroll",
      { month: newMonth, year: newYear },
      { preserveState: true, preserveScroll: true }
    )
  }

  const submit = (e: React.FormEvent) => {
    e.preventDefault()
    form.post("/admin/payroll/create", {
      preserveScroll: true,
      preserveState: true,
      onSuccess: () => setModalOpen(false),
    })
  }

  const markAsPaid = (p: Payroll) => {
    if (confirm(`Tandai slip gaji untuk "${p.user}" (${formatIDR(p.total)}) sebagai SUDAH DIBAYAR / LUNAS?`)) {
      router.post(`/admin/payroll/pay/${p.id}`, {}, { preserveScroll: true })
    }
  }

  const deletePayroll = (p: Payroll) => {
    if (confirm(`Hapus slip gaji "${p.user}" untuk periode ${p.period}?`)) {
      router.post(`/admin/payroll/delete/${p.id}`, {}, { preserveScroll: true })
    }
  }

  const printSlip = (p: Payroll) => {
    window.open(`/admin/payroll/print/${p.id}`, "_blank")
  }

  const monthLabel = MONTH_NAMES[selectedMonth - 1] || `Bulan ${selectedMonth}`

  const filteredPayrolls = useMemo(() => {
    return payrolls.filter((p) => {
      if (statusFilter !== "all" && p.status !== statusFilter) return false
      if (searchQuery.trim()) {
        const q = searchQuery.toLowerCase().trim()
        const matchUser = (p.user ?? "").toLowerCase().includes(q)
        const matchRole = (p.user_role ?? "").toLowerCase().includes(q)
        if (!matchUser && !matchRole) return false
      }
      return true
    })
  }, [payrolls, searchQuery, statusFilter])

  return (
    <AppLayout
      title="Payroll & Penggajian"
      brand={adminBrand}
      sidebarItems={adminSidebarItems}
      navItems={adminNavItems}
    >
      <div className="space-y-6">
        {/* ── 4 TOP KPI METRICS ── */}
        <div className="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
          <MetricCard
            title="Total Anggaran"
            value={formatIDR(summary?.total_amount ?? 0)}
            icon={<DollarSign className="h-6 w-6 text-blue-500" />}
            sub={`Periode ${monthLabel} ${selectedYear}`}
            onClick={() => setStatusFilter("all")}
            isActive={statusFilter === "all"}
          />

          <MetricCard
            title="Sudah Dibayar"
            value={formatIDR(summary?.total_paid ?? 0)}
            icon={<CheckCircle2 className="h-6 w-6 text-emerald-500" />}
            sub={`${summary?.paid_count ?? 0} Karyawan lunas`}
            onClick={() => setStatusFilter(statusFilter === "paid" ? "all" : "paid")}
            isActive={statusFilter === "paid"}
          />

          <MetricCard
            title="Belum Dibayar"
            value={formatIDR(summary?.total_unpaid ?? 0)}
            icon={<Clock className="h-6 w-6 text-amber-500" />}
            sub={`${summary?.unpaid_count ?? 0} Slip pending`}
            onClick={() => setStatusFilter(statusFilter === "pending" ? "all" : "pending")}
            isActive={statusFilter === "pending"}
          />

          <MetricCard
            title="Total Slip Gaji"
            value={`${payrolls.length} Slip`}
            icon={<Briefcase className="h-6 w-6 text-purple-500" />}
            sub={`${summary?.total_count ?? payrolls.length} Staf terdaftar`}
          />
        </div>

        {/* ── 1-LINE RESPONSIVE INTEGRATED TOOLBAR ── */}
        <div className="rounded-2xl border border-gray-200 bg-white p-3 sm:p-4 shadow-xs dark:border-gray-800 dark:bg-white/[0.03] flex flex-col gap-2.5 sm:gap-3 lg:flex-row lg:items-center lg:justify-between">
          {/* Left side: Search bar & Month, Year, Status dropdowns */}
          <div className="flex flex-wrap items-center gap-2 sm:gap-3 flex-1 min-w-0">
            {/* Search */}
            <div className="relative flex-1 min-w-[180px] sm:min-w-[220px] w-full sm:w-auto">
              <Search className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-gray-400" />
              <input
                type="text"
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                placeholder="Cari nama karyawan / peran..."
                className="h-10 w-full rounded-xl border border-gray-200 bg-gray-50/75 dark:border-gray-800 dark:bg-gray-900/50 pl-9 pr-8 text-xs font-medium text-gray-900 dark:text-white placeholder:text-gray-400 focus:border-brand-500 focus:outline-hidden"
              />
              {searchQuery && (
                <button
                  type="button"
                  onClick={() => setSearchQuery("")}
                  className="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-white"
                >
                  <X className="size-3.5" />
                </button>
              )}
            </div>

            {/* Month Selector */}
            <select
              value={selectedMonth}
              onChange={(e) => handlePeriodChange(Number(e.target.value), selectedYear)}
              className="h-10 rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 px-3 text-xs font-semibold text-gray-700 dark:text-gray-200 cursor-pointer w-full sm:w-auto shrink-0 focus:border-brand-500 focus:outline-hidden"
            >
              {MONTH_NAMES.map((name, i) => (
                <option key={i + 1} value={i + 1}>
                  {name}
                </option>
              ))}
            </select>

            {/* Year Selector */}
            <select
              value={selectedYear}
              onChange={(e) => handlePeriodChange(selectedMonth, Number(e.target.value))}
              className="h-10 rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 px-3 text-xs font-semibold text-gray-700 dark:text-gray-200 cursor-pointer w-full sm:w-auto shrink-0 focus:border-brand-500 focus:outline-hidden"
            >
              {[2024, 2025, 2026, 2027, 2028].map((y) => (
                <option key={y} value={y}>
                  {y}
                </option>
              ))}
            </select>

            {/* Status Filter */}
            <select
              value={statusFilter}
              onChange={(e) => setStatusFilter(e.target.value)}
              className="h-10 rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 px-3 text-xs font-semibold text-gray-700 dark:text-gray-200 cursor-pointer w-full sm:w-auto shrink-0 focus:border-brand-500 focus:outline-hidden"
            >
              <option value="all">Semua Status</option>
              <option value="paid">Sudah Dibayar</option>
              <option value="pending">Pending</option>
            </select>
          </div>

          {/* Right side: Action Button */}
          <div className="flex items-center gap-2 shrink-0 w-full sm:w-auto justify-end">
            <button
              type="button"
              onClick={openAdd}
              className="h-10 px-3.5 sm:px-4 rounded-xl bg-brand-500 hover:bg-brand-600 active:scale-95 text-xs font-bold text-white shadow-xs flex items-center justify-center gap-1.5 w-full sm:w-auto shrink-0 cursor-pointer transition"
            >
              <Plus className="size-4" />
              <span>Buat Slip Gaji</span>
            </button>
          </div>
        </div>

        {/* ── MASTER CARD CONTAINER ── */}
        <div className="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xs dark:border-gray-800 dark:bg-white/[0.03]">

          {/* ── TABLE VIEW ── */}
          {filteredPayrolls.length === 0 ? (
            <div className="p-8 text-center">
              <EmptyState
                icon={<Briefcase className="h-8 w-8 text-brand-500" />}
                title={`Belum ada slip gaji untuk periode ${monthLabel} ${selectedYear}`}
                description="Klik tombol 'Buat Slip Gaji' untuk mulai membuat perhitungan gaji karyawan."
              />
            </div>
          ) : (
            <div className="w-full overflow-x-auto custom-scrollbar">
              <table className="w-full text-left text-xs text-gray-600 dark:text-gray-300 min-w-[700px] border-collapse">
                <thead className="border-b border-gray-200 bg-gray-50/75 text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:border-gray-800 dark:bg-gray-900/50 dark:text-gray-400">
                  <tr>
                    <th className="px-4 py-3.5">Karyawan</th>
                    <th className="px-4 py-3.5">Periode</th>
                    <th className="px-4 py-3.5">Gaji Pokok</th>
                    <th className="px-4 py-3.5">Bonus & Potongan</th>
                    <th className="px-4 py-3.5">Total Diterima</th>
                    <th className="px-4 py-3.5">Status</th>
                    <th className="px-4 py-3.5 text-right">Aksi</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-gray-100 dark:divide-gray-800">
                  {filteredPayrolls.map((p) => {
                    const isPaid = p.status === "paid"

                    return (
                      <tr key={p.id} className="hover:bg-gray-50/50 dark:hover:bg-white/[0.02] transition">
                        {/* Karyawan Column */}
                        <td className="px-4 py-3.5">
                          <div className="flex items-center gap-3">
                            <div className="relative flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-gray-100 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 font-bold text-xs text-gray-700 dark:text-gray-200">
                              <span>{p.user ? p.user.slice(0, 2).toUpperCase() : "ST"}</span>
                              <span
                                className={cn(
                                  "absolute -bottom-0.5 -right-0.5 h-2.5 w-2.5 rounded-full ring-2 ring-white dark:ring-gray-900",
                                  isPaid ? "bg-emerald-500" : "bg-amber-500"
                                )}
                              />
                            </div>
                            <div className="min-w-0">
                              <div className="font-bold text-gray-900 dark:text-white truncate">
                                {p.user}
                              </div>
                              <div className="text-[11px] text-gray-500 dark:text-gray-400 capitalize">
                                {p.user_role}
                              </div>
                            </div>
                          </div>
                        </td>

                        {/* Periode Column */}
                        <td className="px-4 py-3.5 font-medium text-gray-700 dark:text-gray-300">
                          {p.period}
                        </td>

                        {/* Gaji Pokok Column */}
                        <td className="px-4 py-3.5 font-mono text-gray-800 dark:text-gray-200">
                          {formatIDR(p.salary)}
                        </td>

                        {/* Bonus & Potongan Column */}
                        <td className="px-4 py-3.5 font-mono text-xs">
                          <div className="space-y-0.5">
                            {p.bonus > 0 && (
                              <div className="text-emerald-600 dark:text-emerald-400 font-semibold">
                                +{formatIDR(p.bonus)}
                              </div>
                            )}
                            {p.deductions > 0 && (
                              <div className="text-rose-600 dark:text-rose-400 font-semibold">
                                -{formatIDR(p.deductions)}
                              </div>
                            )}
                            {!p.bonus && !p.deductions && (
                              <span className="text-gray-400">-</span>
                            )}
                          </div>
                        </td>

                        {/* Total Diterima Column */}
                        <td className="px-4 py-3.5">
                          <div className="font-bold text-sm font-mono text-gray-900 dark:text-white">
                            {formatIDR(p.total)}
                          </div>
                          {p.paid_at && (
                            <div className="text-[10px] text-gray-400">
                              Dibayar: {p.paid_at}
                            </div>
                          )}
                        </td>

                        {/* Status Column */}
                        <td className="px-4 py-3.5">
                          {isPaid ? (
                            <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold bg-emerald-500 text-white shadow-xs whitespace-nowrap">
                              Sudah Dibayar
                            </span>
                          ) : (
                            <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold bg-amber-500 text-white shadow-xs whitespace-nowrap">
                              Pending
                            </span>
                          )}
                        </td>

                        {/* Single Action Button: Kelola */}
                        <td className="px-4 py-3.5 text-right whitespace-nowrap">
                          <button
                            type="button"
                            onClick={() => setManagingPayroll(p)}
                            className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200 font-bold text-xs transition cursor-pointer"
                            title="Kelola Slip Gaji"
                          >
                            <Settings className="h-3.5 w-3.5" />
                            <span>Kelola</span>
                          </button>
                        </td>
                      </tr>
                    )
                  })}
                </tbody>
              </table>
            </div>
          )}
        </div>
      </div>

      {/* ── MODAL INTERAKTIF: KELOLA SLIP GAJI ── */}
      {managingPayroll && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-xs">
          <div className="w-full max-w-md rounded-2xl border border-gray-200 bg-white p-5 shadow-2xl dark:border-gray-800 dark:bg-gray-900 space-y-4 max-h-[92vh] overflow-y-auto">
            {/* Modal Header */}
            <div className="flex items-center justify-between border-b border-gray-100 pb-3 dark:border-gray-800">
              <div className="flex items-center gap-3">
                <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-brand-500 text-white shadow-xs font-bold text-sm">
                  {managingPayroll.user ? managingPayroll.user.slice(0, 2).toUpperCase() : "ST"}
                </div>
                <div className="min-w-0">
                  <h3 className="font-bold text-sm text-gray-900 dark:text-white truncate">
                    Kelola Slip: {managingPayroll.user}
                  </h3>
                  <span className="text-[11px] text-gray-500 font-mono">
                    Periode {managingPayroll.period}
                  </span>
                </div>
              </div>
              <button
                type="button"
                onClick={() => setManagingPayroll(null)}
                className="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-800 dark:hover:text-gray-200 transition cursor-pointer"
              >
                <X className="h-5 w-5" />
              </button>
            </div>

            {/* Quick Summary Card */}
            <div className="rounded-xl border border-gray-200/80 bg-gray-50/70 p-3.5 dark:border-gray-800 dark:bg-gray-800/40 space-y-2 text-xs">
              <div className="flex items-center justify-between">
                <span className="text-gray-500 dark:text-gray-400">Status Pembayaran</span>
                {managingPayroll.status === "paid" ? (
                  <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold bg-emerald-500 text-white shadow-xs whitespace-nowrap">
                    Sudah Dibayar
                  </span>
                ) : (
                  <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold bg-amber-500 text-white shadow-xs whitespace-nowrap">
                    Pending
                  </span>
                )}
              </div>
              <div className="flex items-center justify-between">
                <span className="text-gray-500 dark:text-gray-400">Jabatan / Role</span>
                <span className="font-semibold text-gray-900 dark:text-white capitalize">
                  {managingPayroll.user_role}
                </span>
              </div>
              <div className="flex items-center justify-between">
                <span className="text-gray-500 dark:text-gray-400">Gaji Pokok</span>
                <span className="font-mono text-gray-800 dark:text-gray-200">
                  {formatIDR(managingPayroll.salary)}
                </span>
              </div>
              {managingPayroll.bonus > 0 && (
                <div className="flex items-center justify-between text-emerald-600 dark:text-emerald-400 font-semibold">
                  <span>Bonus / Tunjangan</span>
                  <span className="font-mono">+{formatIDR(managingPayroll.bonus)}</span>
                </div>
              )}
              {managingPayroll.deductions > 0 && (
                <div className="flex items-center justify-between text-rose-600 dark:text-rose-400 font-semibold">
                  <span>Potongan / Kasbon</span>
                  <span className="font-mono">-{formatIDR(managingPayroll.deductions)}</span>
                </div>
              )}
              <div className="pt-2 border-t border-gray-200 dark:border-gray-700 flex items-center justify-between">
                <span className="font-bold text-gray-900 dark:text-white">Total Gaji Bersih</span>
                <span className="font-mono font-bold text-sm text-emerald-600 dark:text-emerald-400">
                  {formatIDR(managingPayroll.total)}
                </span>
              </div>
              {managingPayroll.paid_at && (
                <div className="text-[10px] text-gray-500 dark:text-gray-400">
                  Dibayar pada: {managingPayroll.paid_at}
                </div>
              )}
            </div>

            {/* Action Buttons List */}
            <div className="space-y-2 pt-1 text-xs">
              <button
                type="button"
                onClick={() => {
                  printSlip(managingPayroll)
                }}
                className="w-full group flex items-center justify-between p-3 rounded-xl bg-gray-50/70 hover:bg-brand-50/50 border border-gray-100 hover:border-brand-200 dark:bg-gray-800/40 dark:hover:bg-gray-800 dark:border-gray-800 dark:hover:border-brand-900/60 transition cursor-pointer"
              >
                <div className="flex items-center gap-3">
                  <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-brand-50 text-brand-600 dark:bg-brand-950 dark:text-brand-400 whitespace-nowrap">
                    <Printer className="h-4 w-4" />
                  </div>
                  <div className="text-left">
                    <div className="font-bold text-gray-900 dark:text-white">
                      Cetak Slip Gaji (PDF / Print)
                    </div>
                    <div className="text-[10px] text-gray-500">Buka dokumen slip gaji resmi</div>
                  </div>
                </div>
                <ChevronRight className="h-4 w-4 text-gray-400 group-hover:text-brand-500 transition-colors" />
              </button>

              {managingPayroll.status !== "paid" && (
                <button
                  type="button"
                  onClick={() => {
                    const target = managingPayroll
                    setManagingPayroll(null)
                    markAsPaid(target)
                  }}
                  className="w-full group flex items-center justify-between p-3 rounded-xl bg-gray-50/70 hover:bg-emerald-50/50 border border-gray-100 hover:border-emerald-200 dark:bg-gray-800/40 dark:hover:bg-gray-800 dark:border-gray-800 dark:hover:border-emerald-900/60 transition cursor-pointer"
                >
                  <div className="flex items-center gap-3">
                    <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600 dark:bg-emerald-950 dark:text-emerald-400 whitespace-nowrap">
                      <CheckCircle2 className="h-4 w-4" />
                    </div>
                    <div className="text-left">
                      <div className="font-bold text-emerald-600 dark:text-emerald-400">
                        Tandai Sudah Dibayar (Lunas)
                      </div>
                      <div className="text-[10px] text-gray-500">Konfirmasi pencairan gaji staf</div>
                    </div>
                  </div>
                  <ChevronRight className="h-4 w-4 text-gray-400 group-hover:text-emerald-500 transition-colors" />
                </button>
              )}

              {managingPayroll.status !== "paid" && (
                <button
                  type="button"
                  onClick={() => {
                    const target = managingPayroll
                    setManagingPayroll(null)
                    deletePayroll(target)
                  }}
                  className="w-full group flex items-center justify-between p-3 rounded-xl bg-gray-50/70 hover:bg-rose-50/50 border border-gray-100 hover:border-rose-200 dark:bg-gray-800/40 dark:hover:bg-gray-800 dark:border-gray-800 dark:hover:border-rose-900/60 transition cursor-pointer"
                >
                  <div className="flex items-center gap-3">
                    <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-rose-50 text-rose-600 dark:bg-rose-950 dark:text-rose-400 whitespace-nowrap">
                      <Trash2 className="h-4 w-4" />
                    </div>
                    <div className="text-left">
                      <div className="font-bold text-rose-600 dark:text-rose-400">
                        Hapus Slip Gaji
                      </div>
                      <div className="text-[10px] text-gray-500">Batalkan dan hapus kalkulasi slip ini</div>
                    </div>
                  </div>
                  <ChevronRight className="h-4 w-4 text-gray-400 group-hover:text-rose-500 transition-colors" />
                </button>
              )}
            </div>
          </div>
        </div>
      )}

      {/* ── MODAL BUAT SLIP GAJI ── */}
      {modalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
          <div className="fixed inset-0 bg-black/60 backdrop-blur-sm" onClick={() => setModalOpen(false)} />
          <div className="relative flex max-h-[92vh] w-full max-w-md flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 text-gray-900 dark:text-white shadow-2xl animate-in zoom-in-95 duration-150">
            {/* Modal Header */}
            <div className="flex items-center justify-between border-b border-gray-100 dark:border-gray-800 px-5 py-4 shrink-0">
              <div className="flex items-center gap-3">
                <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-brand-50 dark:bg-brand-500/10 text-brand-500 border border-brand-200 dark:border-brand-500/20">
                  <Briefcase className="h-5 w-5" />
                </div>
                <div>
                  <h3 className="font-bold text-base text-gray-900 dark:text-white">
                    Buat Slip Gaji Karyawan
                  </h3>
                  <p className="text-xs text-gray-500 dark:text-gray-400">Periode {monthLabel} {selectedYear}</p>
                </div>
              </div>
              <button
                onClick={() => setModalOpen(false)}
                className="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-700 dark:hover:text-white"
              >
                <X className="h-4 w-4" />
              </button>
            </div>

            <form onSubmit={submit} className="flex min-h-0 flex-1 flex-col overflow-hidden">
              <div className="flex-1 overflow-y-auto p-5 space-y-4">
                <div className="space-y-1.5">
                  <label className="text-xs font-semibold text-gray-700 dark:text-gray-300">Pilih Karyawan</label>
                  <select
                    value={form.data.user_id}
                    onChange={(e) => {
                      const uid = e.target.value
                      form.setData("user_id", uid)
                      const found = staffList.find((s) => String(s.id) === uid)
                      if (found && found.salary) {
                        form.setData("salary", String(found.salary))
                      }
                    }}
                    required
                    className="w-full h-10 rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-950 px-3 text-xs text-gray-900 dark:text-white focus:outline-none focus:border-brand-500"
                  >
                    <option value="">-- Pilih Karyawan --</option>
                    {staffList.map((u) => (
                      <option key={u.id} value={String(u.id)}>
                        {u.name} ({u.role})
                      </option>
                    ))}
                  </select>
                </div>

                <div className="grid gap-3 sm:grid-cols-2">
                  <div className="space-y-1.5">
                    <label className="text-xs font-semibold text-gray-700 dark:text-gray-300">Bulan Periode</label>
                    <select
                      value={form.data.period_month}
                      onChange={(e) => form.setData("period_month", e.target.value)}
                      className="w-full h-10 rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-950 px-3 text-xs text-gray-900 dark:text-white focus:outline-none focus:border-brand-500"
                    >
                      {MONTH_NAMES.map((name, idx) => (
                        <option key={idx + 1} value={String(idx + 1)}>
                          {name}
                        </option>
                      ))}
                    </select>
                  </div>

                  <div className="space-y-1.5">
                    <label className="text-xs font-semibold text-gray-700 dark:text-gray-300">Tahun</label>
                    <select
                      value={form.data.period_year}
                      onChange={(e) => form.setData("period_year", e.target.value)}
                      className="w-full h-10 rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-950 px-3 text-xs text-gray-900 dark:text-white focus:outline-none focus:border-brand-500"
                    >
                      {[2024, 2025, 2026, 2027, 2028].map((y) => (
                        <option key={y} value={String(y)}>
                          {y}
                        </option>
                      ))}
                    </select>
                  </div>
                </div>

                <div className="space-y-1.5">
                  <label className="text-xs font-semibold text-gray-700 dark:text-gray-300">Gaji Pokok (Rp)</label>
                  <input
                    type="number"
                    value={form.data.salary}
                    onChange={(e) => form.setData("salary", e.target.value)}
                    placeholder="cth: 3500000"
                    required
                    className="w-full h-10 rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-950 px-3 text-xs text-gray-900 dark:text-white placeholder:text-gray-400 focus:outline-none focus:border-brand-500"
                  />
                </div>

                <div className="grid gap-3 sm:grid-cols-2">
                  <div className="space-y-1.5">
                    <label className="text-xs font-semibold text-gray-700 dark:text-gray-300">Bonus / Tunjangan (Rp)</label>
                    <input
                      type="number"
                      value={form.data.bonus}
                      onChange={(e) => form.setData("bonus", e.target.value)}
                      placeholder="0"
                      className="w-full h-10 rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-950 px-3 text-xs text-gray-900 dark:text-white placeholder:text-gray-400 focus:outline-none focus:border-brand-500"
                    />
                  </div>

                  <div className="space-y-1.5">
                    <label className="text-xs font-semibold text-gray-700 dark:text-gray-300">Potongan / Kasbon (Rp)</label>
                    <input
                      type="number"
                      value={form.data.deductions}
                      onChange={(e) => form.setData("deductions", e.target.value)}
                      placeholder="0"
                      className="w-full h-10 rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-950 px-3 text-xs text-gray-900 dark:text-white placeholder:text-gray-400 focus:outline-none focus:border-brand-500"
                    />
                  </div>
                </div>
              </div>

              {/* Bottom Actions */}
              <div className="border-t border-gray-100 dark:border-gray-800 p-4 bg-gray-50/50 dark:bg-gray-900/50 shrink-0 flex items-center gap-2">
                <button
                  type="button"
                  onClick={() => setModalOpen(false)}
                  className="flex-1 rounded-xl h-10 text-xs font-bold border border-gray-200 bg-white hover:bg-gray-100 dark:border-gray-800 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200 transition"
                >
                  Batal
                </button>
                <button
                  type="submit"
                  disabled={form.processing}
                  className="flex-1 rounded-xl h-10 text-xs font-bold bg-brand-500 hover:bg-brand-600 text-white transition flex items-center justify-center gap-1.5 disabled:opacity-50"
                >
                  {form.processing ? "Menyimpan..." : "Simpan Slip Gaji"}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </AppLayout>
  )
}
