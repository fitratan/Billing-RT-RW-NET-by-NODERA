import { AppLayout } from "@/components/layout/app-layout"
import {
  CalendarCheck,
  Clock,
  CheckCircle2,
  Search,
  Users,
  Calendar,
  X,
  UserCheck,
  Settings,
  ChevronRight,
} from "lucide-react"
import { EmptyState } from "@/components/ui/empty-state"
import { PageProps } from "@/types"
import { adminSidebarItems, adminNavItems, adminBrand } from "@/lib/admin-nav"
import { router } from "@inertiajs/react"
import { useState, useMemo } from "react"
import { MetricCard } from "@/components/tailadmin/MetricCard"
import { cn } from "@/lib/utils"

interface Attendance {
  id: number
  user: string | null
  role: string | null
  check_in: string | null
  check_out: string | null
}

export default function AttendancePage({
  attendances = [],
  stats = { total: 0, checked_in: 0, checked_out: 0 },
  date = "",
  users = [],
  companyName = "NODERA Billing",
  tenantName,
}: PageProps<{
  attendances: Attendance[]
  stats: { total: number; checked_in: number; checked_out: number }
  date: string
  users: { id: number; name: string; role: string }[]
  companyName?: string
  tenantName?: string
}>) {
  const [managingAttendance, setManagingAttendance] = useState<Attendance | null>(null)
  const [searchQuery, setSearchQuery] = useState("")
  const [selectedUserId, setSelectedUserId] = useState<string>("")
  const [statusFilter, setStatusFilter] = useState<string>("all")
  const [selectedDate, setSelectedDate] = useState<string>(date || new Date().toISOString().split("T")[0])

  const handleDateChange = (newDate: string) => {
    setSelectedDate(newDate)
    router.get(
      "/admin/attendance",
      { date: newDate, user_id: selectedUserId || undefined },
      { preserveState: true, preserveScroll: true }
    )
  }

  const handleUserFilterChange = (userId: string) => {
    setSelectedUserId(userId)
    router.get(
      "/admin/attendance",
      { date: selectedDate, user_id: userId || undefined },
      { preserveState: true, preserveScroll: true }
    )
  }

  const filteredAttendances = useMemo(() => {
    return attendances.filter((a) => {
      const isFinished = Boolean(a.check_out)
      if (statusFilter === "in" && isFinished) return false
      if (statusFilter === "out" && !isFinished) return false

      if (searchQuery.trim()) {
        const q = searchQuery.toLowerCase().trim()
        const matchUser = (a.user ?? "").toLowerCase().includes(q)
        const matchRole = (a.role ?? "").toLowerCase().includes(q)
        if (!matchUser && !matchRole) return false
      }
      return true
    })
  }, [attendances, searchQuery, statusFilter])

  return (
    <AppLayout
      title="Presensi & Absensi"
      brand={adminBrand}
      sidebarItems={adminSidebarItems}
      navItems={adminNavItems}
    >
      <div className="space-y-6">
        {/* ── 4 TOP KPI METRICS ── */}
        <div className="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
          <MetricCard
            title="Total Petugas"
            value={`${stats.total} Petugas`}
            icon={<Users className="h-6 w-6 text-blue-500" />}
            sub="Karyawan terdaftar"
            onClick={() => setStatusFilter("all")}
            isActive={statusFilter === "all"}
          />

          <MetricCard
            title="Sedang Bertugas"
            value={`${stats.checked_in} Petugas`}
            icon={<Clock className="h-6 w-6 text-amber-500" />}
            sub="Check-in aktif"
            onClick={() => setStatusFilter(statusFilter === "in" ? "all" : "in")}
            isActive={statusFilter === "in"}
          />

          <MetricCard
            title="Sudah Checkout"
            value={`${stats.checked_out} Petugas`}
            icon={<CheckCircle2 className="h-6 w-6 text-emerald-500" />}
            sub="Selesai bertugas"
            onClick={() => setStatusFilter(statusFilter === "out" ? "all" : "out")}
            isActive={statusFilter === "out"}
          />

          <MetricCard
            title="Total Log Presensi"
            value={`${attendances.length} Log`}
            icon={<CalendarCheck className="h-6 w-6 text-purple-500" />}
            sub={selectedDate ? `Tanggal: ${selectedDate}` : "Hari ini"}
          />
        </div>

        {/* ── 1-LINE RESPONSIVE INTEGRATED TOOLBAR ── */}
        <div className="rounded-2xl border border-gray-200 bg-white p-3 sm:p-4 shadow-xs dark:border-gray-800 dark:bg-white/[0.03] flex flex-col gap-2.5 sm:gap-3 lg:flex-row lg:items-center lg:justify-between">
          {/* Left side: Search & Filter dropdowns */}
          <div className="flex flex-wrap items-center gap-2 sm:gap-3 flex-1 min-w-0">
            {/* Search */}
            <div className="relative flex-1 min-w-[180px] sm:min-w-[220px] w-full sm:w-auto">
              <Search className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-gray-400" />
              <input
                type="text"
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                placeholder="Cari nama petugas / role..."
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

            {/* Date Filter */}
            <div className="flex items-center gap-1.5 h-10 rounded-xl border border-gray-200 bg-white px-3 dark:border-gray-800 dark:bg-gray-900 w-full sm:w-auto shrink-0">
              <Calendar className="size-4 text-gray-400 shrink-0" />
              <input
                type="date"
                value={selectedDate}
                onChange={(e) => handleDateChange(e.target.value)}
                className="bg-transparent text-xs font-semibold text-gray-700 dark:text-gray-200 focus:outline-hidden cursor-pointer"
              />
            </div>

            {/* User Filter */}
            <select
              value={selectedUserId}
              onChange={(e) => handleUserFilterChange(e.target.value)}
              className="h-10 rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 px-3 text-xs font-semibold text-gray-700 dark:text-gray-200 cursor-pointer w-full sm:w-auto shrink-0 focus:border-brand-500 focus:outline-hidden"
            >
              <option value="">Semua Petugas</option>
              {users.map((u) => (
                <option key={u.id} value={String(u.id)}>
                  {u.name} ({u.role})
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
              <option value="in">Sedang Bertugas</option>
              <option value="out">Sudah Checkout</option>
            </select>
          </div>

          {/* Right side: Summary Pill */}
          <div className="flex items-center gap-2 shrink-0 w-full sm:w-auto justify-end">
            <span className="inline-flex items-center h-10 px-3.5 rounded-xl border border-gray-200 bg-gray-50/75 dark:border-gray-800 dark:bg-gray-900/50 text-xs font-semibold text-gray-600 dark:text-gray-400">
              {filteredAttendances.length} Log Presensi
            </span>
          </div>
        </div>

        {/* ── MASTER CARD CONTAINER ── */}
        <div className="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xs dark:border-gray-800 dark:bg-white/[0.03]">

          {/* ── TABLE VIEW ── */}
          {filteredAttendances.length === 0 ? (
            <div className="p-8 text-center">
              <EmptyState
                icon={<CalendarCheck className="h-8 w-8 text-brand-500" />}
                title="Belum ada riwayat absensi"
                description="Petugas dapat melakukan check-in mandiri dari aplikasi portal lapangan mereka."
              />
            </div>
          ) : (
            <div className="w-full overflow-x-auto custom-scrollbar">
              <table className="w-full text-left text-xs text-gray-600 dark:text-gray-300 min-w-[900px] border-collapse">
                <thead className="border-b border-gray-200 bg-gray-50/75 text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:border-gray-800 dark:bg-gray-900/50 dark:text-gray-400">
                  <tr>
                    <th className="px-4 py-3.5">Petugas</th>
                    <th className="px-4 py-3.5">Peran / Role</th>
                    <th className="px-4 py-3.5">Jam Masuk (Check-In)</th>
                    <th className="px-4 py-3.5">Jam Pulang (Check-Out)</th>
                    <th className="px-4 py-3.5">Status Kehadiran</th>
                    <th className="px-4 py-3.5 text-right">Aksi</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-gray-100 dark:divide-gray-800">
                  {filteredAttendances.map((a) => {
                    const isFinished = Boolean(a.check_out)

                    return (
                      <tr key={a.id} className="hover:bg-gray-50/50 dark:hover:bg-white/[0.02] transition">
                        {/* Petugas Column */}
                        <td className="px-4 py-3.5">
                          <div className="flex items-center gap-3">
                            <div className="relative flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-gray-100 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 font-bold text-xs text-gray-700 dark:text-gray-200">
                              <span>{a.user ? a.user.slice(0, 2).toUpperCase() : "PT"}</span>
                              <span
                                className={cn(
                                  "absolute -bottom-0.5 -right-0.5 h-2.5 w-2.5 rounded-full ring-2 ring-white dark:ring-gray-900",
                                  isFinished ? "bg-emerald-500" : "bg-amber-500"
                                )}
                              />
                            </div>
                            <div className="min-w-0">
                              <div className="font-bold text-gray-900 dark:text-white truncate">
                                {a.user ?? "Petugas Lapangan"}
                              </div>
                              <div className="text-[11px] text-gray-500 dark:text-gray-400 capitalize">
                                ID #{a.id}
                              </div>
                            </div>
                          </div>
                        </td>

                        {/* Peran Column */}
                        <td className="px-4 py-3.5">
                          <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold bg-brand-500 text-white shadow-xs capitalize whitespace-nowrap">
                            {a.role || "Petugas Lapangan"}
                          </span>
                        </td>

                        {/* Check In Column */}
                        <td className="px-4 py-3.5 font-mono text-emerald-600 dark:text-emerald-400 font-semibold">
                          <div className="flex items-center gap-1.5">
                            <Clock className="h-3.5 w-3.5" />
                            <span>{a.check_in || "-"}</span>
                          </div>
                        </td>

                        {/* Check Out Column */}
                        <td className="px-4 py-3.5 font-mono text-gray-700 dark:text-gray-300">
                          {a.check_out ? (
                            <div className="flex items-center gap-1.5 font-semibold text-gray-900 dark:text-white">
                              <UserCheck className="h-3.5 w-3.5 text-emerald-500" />
                              <span>{a.check_out}</span>
                            </div>
                          ) : (
                            <span className="text-amber-600 dark:text-amber-400 font-medium">
                              Masih Bertugas
                            </span>
                          )}
                        </td>

                        {/* Status Column */}
                        <td className="px-4 py-3.5">
                          {isFinished ? (
                            <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold bg-emerald-500 text-white shadow-xs whitespace-nowrap">
                              Selesai
                            </span>
                          ) : (
                            <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold bg-amber-500 text-white shadow-xs whitespace-nowrap">
                              Sedang Bertugas
                            </span>
                          )}
                        </td>

                        {/* Single Action Button: Kelola */}
                        <td className="px-4 py-3.5 text-right whitespace-nowrap">
                          <button
                            type="button"
                            onClick={() => setManagingAttendance(a)}
                            className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200 font-bold text-xs transition cursor-pointer"
                            title="Detail Presensi"
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

      {/* ── MODAL INTERAKTIF: KELOLA PRESENSI ── */}
      {managingAttendance && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-xs">
          <div className="w-full max-w-md rounded-2xl border border-gray-200 bg-white p-5 shadow-2xl dark:border-gray-800 dark:bg-gray-900 space-y-4 max-h-[92vh] overflow-y-auto custom-scrollbar">
            {/* Modal Header */}
            <div className="flex items-center justify-between border-b border-gray-100 pb-3 dark:border-gray-800">
              <div className="flex items-center gap-3">
                <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-brand-500 text-white shadow-xs font-bold text-sm">
                  {managingAttendance.user ? managingAttendance.user.slice(0, 2).toUpperCase() : "PT"}
                </div>
                <div className="min-w-0">
                  <h3 className="font-bold text-sm text-gray-900 dark:text-white truncate">
                    Presensi: {managingAttendance.user ?? "Petugas Lapangan"}
                  </h3>
                  <span className="text-[11px] text-gray-500 font-mono">
                    Log ID #{managingAttendance.id} • {selectedDate}
                  </span>
                </div>
              </div>
              <button
                type="button"
                onClick={() => setManagingAttendance(null)}
                className="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-800 dark:hover:text-gray-200 transition cursor-pointer"
              >
                <X className="h-5 w-5" />
              </button>
            </div>

            {/* Quick Summary Card */}
            <div className="rounded-xl border border-gray-200/80 bg-gray-50/70 p-3.5 dark:border-gray-800 dark:bg-gray-800/40 space-y-2 text-xs">
              <div className="flex items-center justify-between">
                <span className="text-gray-500 dark:text-gray-400">Status Kehadiran</span>
                {managingAttendance.check_out ? (
                  <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold bg-emerald-500 text-white shadow-xs whitespace-nowrap">
                    Selesai Bertugas
                  </span>
                ) : (
                  <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold bg-amber-500 text-white shadow-xs whitespace-nowrap">
                    Sedang Bertugas
                  </span>
                )}
              </div>
              <div className="flex items-center justify-between">
                <span className="text-gray-500 dark:text-gray-400">Peran / Role</span>
                <span className="font-semibold text-gray-900 dark:text-white capitalize">
                  {managingAttendance.role || "Petugas Lapangan"}
                </span>
              </div>
              <div className="flex items-center justify-between">
                <span className="text-gray-500 dark:text-gray-400">Jam Masuk (Check-In)</span>
                <span className="font-mono font-bold text-emerald-600 dark:text-emerald-400">
                  {managingAttendance.check_in || "-"}
                </span>
              </div>
              <div className="flex items-center justify-between">
                <span className="text-gray-500 dark:text-gray-400">Jam Pulang (Check-Out)</span>
                <span className="font-mono font-medium text-gray-800 dark:text-gray-200">
                  {managingAttendance.check_out || "Masih Aktif di Lapangan"}
                </span>
              </div>
              <div className="flex items-center justify-between">
                <span className="text-gray-500 dark:text-gray-400">Tanggal Log</span>
                <span className="font-medium text-gray-900 dark:text-white">
                  {selectedDate}
                </span>
              </div>
            </div>

            {/* Action Buttons List */}
            <div className="space-y-2 pt-1 text-xs">
              <button
                type="button"
                onClick={() => {
                  if (managingAttendance.user) {
                    setSearchQuery(managingAttendance.user)
                  }
                  setManagingAttendance(null)
                }}
                className="w-full group flex items-center justify-between p-3 rounded-xl bg-gray-50/70 hover:bg-brand-50/50 border border-gray-100 hover:border-brand-200 dark:bg-gray-800/40 dark:hover:bg-gray-800 dark:border-gray-800 dark:hover:border-brand-900/60 transition cursor-pointer"
              >
                <div className="flex items-center gap-3">
                  <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-brand-50 text-brand-600 dark:bg-brand-950 dark:text-brand-400 whitespace-nowrap">
                    <Search className="h-4 w-4" />
                  </div>
                  <div className="text-left">
                    <div className="font-bold text-gray-900 dark:text-white">
                      Filter Riwayat Petugas Ini
                    </div>
                    <div className="text-[10px] text-gray-500">Tampilkan seluruh log presensi petugas</div>
                  </div>
                </div>
                <ChevronRight className="h-4 w-4 text-gray-400 group-hover:text-brand-500 transition-colors" />
              </button>
            </div>
          </div>
        </div>
      )}
    </AppLayout>
  )
}
