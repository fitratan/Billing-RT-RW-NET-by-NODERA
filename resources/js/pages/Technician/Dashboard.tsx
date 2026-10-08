import * as React from "react"
import { AppLayout } from "@/components/layout/app-layout"
import { router, Link, usePage } from "@inertiajs/react"
import {
  Inbox,
  UserPlus,
  KeyRound,
  Map,
  DollarSign,
  History,
  Network,
  LayoutGrid,
  Ticket,
  CheckCircle2,
  Clock,
  Activity,
  ChevronRight,
  RotateCcw,
  Loader2,
  AlertTriangle,
  Users,
  Wrench,
  ShieldCheck,
} from "lucide-react"
import { PageProps } from "@/types"
import { cn, timeAgo } from "@/lib/utils"
import {
  technicianNavItems,
  technicianSidebarItems,
  getTechnicianNavItems,
  getTechnicianSidebarItems,
  technicianBrand,
} from "@/lib/technician-nav"
import { collectorSidebarItems, collectorNavItems, collectorBrand } from "@/lib/collector-nav"
import { Dialog, DialogContent, DialogHeader, DialogTitle } from "@/components/ui/dialog"
import axios from "axios"

interface TicketItem {
  id: number
  title: string
  description: string | null
  status: string
  priority: string | null
  customer_id?: number | null
  customer_name: string | null
  customer_phone?: string | null
  pppoe_username?: string | null
  onu_serial?: string | null
  router_name: string | null
  created_at: string | null
}

interface OnuSignalData {
  serial_number: string
  model: string
  manufacturer: string
  rx_power: string
  rx_raw: number | null
  signal_status: "good" | "warning" | "critical" | "strong"
  temperature: string
  uptime: string
  ssid: string
  active_clients: number
  ip_tr069: string
  last_inform: string
  customer_name: string
  pppoe_username: string
}

export default function TechnicianDashboardPage({
  technician,
  stats,
  tickets = [],
}: PageProps<{
  technician: { name: string; username: string }
  stats: { total: number; pending: number; in_progress: number; resolved: number; closed: number }
  tickets: TicketItem[]
}>) {
  const { url, props } = usePage<any>()
  const isCollector = url.startsWith("/kolektor")
  const userPerms = props.userPermissions || {}

  const brand = isCollector ? collectorBrand : technicianBrand
  const sidebarItems = isCollector ? collectorSidebarItems : getTechnicianSidebarItems(userPerms)
  const navItems = isCollector ? collectorNavItems : getTechnicianNavItems(userPerms)

  const [signalModalOpen, setSignalModalOpen] = React.useState(false)
  const [activeTicket, setActiveTicket] = React.useState<TicketItem | null>(null)
  const [signalLoading, setSignalLoading] = React.useState(false)
  const [signalData, setSignalData] = React.useState<OnuSignalData | null>(null)
  const [signalError, setSignalError] = React.useState<string | null>(null)
  const [rebooting, setRebooting] = React.useState(false)
  const [rebootMsg, setRebootMsg] = React.useState<string | null>(null)
  const [allFeaturesModalOpen, setAllFeaturesModalOpen] = React.useState(false)

  const openSignalModal = async (ticket: TicketItem) => {
    setActiveTicket(ticket)
    setSignalModalOpen(true)
    setSignalLoading(true)
    setSignalData(null)
    setSignalError(null)
    setRebootMsg(null)

    try {
      const resp = await axios.get(`/teknisi/api/onu-signal/${ticket.id}`)
      if (resp.data && resp.data.success) {
        setSignalData(resp.data.data)
      } else {
        setSignalError(resp.data?.message || "Gagal membaca redaman ONT.")
      }
    } catch (err: any) {
      setSignalError(err.response?.data?.message || err.message || "Gagal menghubungi server ACS.")
    } finally {
      setSignalLoading(false)
    }
  }

  const handleRebootOnu = async () => {
    if (!activeTicket) return
    setRebooting(true)
    setRebootMsg(null)

    try {
      const resp = await axios.post(`/teknisi/api/onu-reboot/${activeTicket.id}`, {
        _token: csrf(),
      })
      if (resp.data && resp.data.success) {
        setRebootMsg(resp.data.message || "Perintah reboot berhasil dikirim ke ONT.")
      } else {
        setRebootMsg(resp.data?.message || "Gagal mengirim perintah reboot.")
      }
    } catch (err: any) {
      setRebootMsg(err.response?.data?.message || err.message || "Gagal mengirim perintah reboot.")
    } finally {
      setRebooting(false)
    }
  }

  const technicianFeatures = [
    {
      key: "pool",
      title: "Pool Gangguan & Pasang",
      desc: "Ambil tiket pekerjaan gangguan atau instalasi baru dari antrean umum.",
      href: "/teknisi/pool",
      icon: Inbox,
      gradient: "from-amber-500 to-amber-700",
      permitted: userPerms.pool !== false,
    },
    {
      key: "create_customer",
      title: "Pasang Baru (Pelanggan)",
      desc: "Form registrasi cepat pelanggan baru langsung di lokasi pemasangan.",
      href: "/teknisi/create-customer",
      icon: UserPlus,
      gradient: "from-emerald-500 to-emerald-700",
      permitted: userPerms.create_customer !== false,
    },
    {
      key: "pppoe",
      title: "PPPoE & Redaman Optik",
      desc: "Monitor status secret PPPoE aktif, kick session, dan diagnostik redaman optik TR-069.",
      href: "/teknisi/pppoe",
      icon: KeyRound,
      gradient: "from-sky-500 to-sky-700",
      permitted: userPerms.pppoe !== false,
    },
    {
      key: "arp",
      title: "ARP Static & Binding",
      desc: "Kelola tabel ARP MikroTik, jadikan IP static, toggle status, dan ping diagnostik.",
      href: "/teknisi/arp",
      icon: Network,
      gradient: "from-indigo-500 to-indigo-700",
      permitted: userPerms.arp !== false,
    },
    {
      key: "map",
      title: "Peta ODP/ONU GIS",
      desc: "Peta GIS jalur fiber optik, kapasitas port ODC/ODP, dan rute lokasi pelanggan.",
      href: "/teknisi/map",
      icon: Map,
      gradient: "from-rose-500 to-rose-700",
      permitted: userPerms.map !== false,
    },
    {
      key: "earnings",
      title: "Pendapatan & Insentif",
      desc: "Rekap insentif pemasangan baru dan komisi penanganan tiket gangguan.",
      href: "/teknisi/earnings",
      icon: DollarSign,
      gradient: "from-purple-500 to-purple-700",
      permitted: userPerms.earnings !== false,
    },
    {
      key: "history",
      title: "Riwayat Pekerjaan Selesai",
      desc: "Daftar histori tiket yang telah berhasil diselesaikan oleh Anda.",
      href: "/teknisi/history",
      icon: History,
      gradient: "from-cyan-500 to-teal-700",
      permitted: userPerms.history !== false,
    },
  ]

  return (
    <AppLayout
      title="Dashboard Teknisi"
      subtitle="Monitoring tiket & operasional lapangan"
      brand={brand}
      sidebarItems={sidebarItems}
      navItems={navItems}
    >
      <div className="space-y-4 sm:space-y-6 select-none pb-20">
        {/* ── TOP STATS METRIC CARDS ── */}
        <div className="grid grid-cols-2 gap-3 sm:grid-cols-4 sm:gap-4">
          {/* Pending */}
          <div className="rounded-2xl border border-gray-200 bg-white p-4 shadow-xs dark:border-gray-800 dark:bg-white/[0.03]">
            <div className="flex items-center justify-between">
              <span className="text-xs font-semibold text-gray-500 dark:text-gray-400">Tiket Pending</span>
              <span className="flex h-8 w-8 items-center justify-center rounded-xl bg-amber-500/10 text-amber-500">
                <Clock className="h-4 w-4" />
              </span>
            </div>
            <div className="mt-2 text-2xl font-bold font-display tabular-nums text-gray-900 dark:text-white">
              {stats.pending}
            </div>
            <span className="text-[11px] text-amber-600 dark:text-amber-400 font-medium">Menunggu tindakan</span>
          </div>

          {/* Dikerjakan */}
          <div className="rounded-2xl border border-gray-200 bg-white p-4 shadow-xs dark:border-gray-800 dark:bg-white/[0.03]">
            <div className="flex items-center justify-between">
              <span className="text-xs font-semibold text-gray-500 dark:text-gray-400">Sedang Dikerjakan</span>
              <span className="flex h-8 w-8 items-center justify-center rounded-xl bg-sky-500/10 text-sky-500">
                <Wrench className="h-4 w-4" />
              </span>
            </div>
            <div className="mt-2 text-2xl font-bold font-display tabular-nums text-gray-900 dark:text-white">
              {stats.in_progress}
            </div>
            <span className="text-[11px] text-sky-600 dark:text-sky-400 font-medium">Dalam progres</span>
          </div>

          {/* Selesai */}
          <div className="rounded-2xl border border-gray-200 bg-white p-4 shadow-xs dark:border-gray-800 dark:bg-white/[0.03]">
            <div className="flex items-center justify-between">
              <span className="text-xs font-semibold text-gray-500 dark:text-gray-400">Selesai</span>
              <span className="flex h-8 w-8 items-center justify-center rounded-xl bg-emerald-500/10 text-emerald-500">
                <CheckCircle2 className="h-4 w-4" />
              </span>
            </div>
            <div className="mt-2 text-2xl font-bold font-display tabular-nums text-gray-900 dark:text-white">
              {stats.resolved}
            </div>
            <span className="text-[11px] text-emerald-600 dark:text-emerald-400 font-medium">Tiket terselesaikan</span>
          </div>

          {/* Total */}
          <div className="rounded-2xl border border-gray-200 bg-white p-4 shadow-xs dark:border-gray-800 dark:bg-white/[0.03]">
            <div className="flex items-center justify-between">
              <span className="text-xs font-semibold text-gray-500 dark:text-gray-400">Total Tiket</span>
              <span className="flex h-8 w-8 items-center justify-center rounded-xl bg-gray-500/10 text-gray-600 dark:text-gray-300">
                <Ticket className="h-4 w-4" />
              </span>
            </div>
            <div className="mt-2 text-2xl font-bold font-display tabular-nums text-gray-900 dark:text-white">
              {stats.total}
            </div>
            <span className="text-[11px] text-gray-500 dark:text-gray-400 font-medium">Histori keseluruhan</span>
          </div>
        </div>

        {/* ── QUICK ACTION SHORTCUTS (8 Grid) ── */}
        <div>
          <div className="mb-3 flex items-center justify-between">
            <h3 className="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
              Pintasan Operasional
            </h3>
            <span className="text-xs font-semibold text-brand-500 dark:text-brand-400">
              {technician.name}
            </span>
          </div>

          <div className="grid grid-cols-4 gap-2.5 sm:gap-3.5 lg:grid-cols-8">
            {/* 1: Pool Tiket */}
            <div
              onClick={() => router.visit("/teknisi/pool")}
              className="flex flex-col items-center justify-center gap-2 rounded-2xl border border-gray-200 bg-white p-3 text-center cursor-pointer shadow-xs transition-all hover:border-amber-500/50 hover:bg-amber-50/30 dark:border-gray-800 dark:bg-white/[0.03] dark:hover:border-amber-500/30 dark:hover:bg-amber-950/10 hover:-translate-y-0.5 active:scale-95 group"
            >
              <div className="flex h-11 w-11 sm:h-12 sm:w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-amber-500 to-amber-600 text-white shadow-sm transition-transform group-hover:scale-105">
                <Inbox className="h-5 w-5 sm:h-6 sm:w-6" />
              </div>
              <div className="min-w-0">
                <span className="block truncate text-xs font-bold text-gray-900 dark:text-white leading-tight">Pool Tiket</span>
                <span className="block truncate text-[10px] text-gray-500 dark:text-gray-400 font-medium mt-0.5">Pekerjaan</span>
              </div>
            </div>

            {/* 2: Pasang Baru */}
            <div
              onClick={() => router.visit("/teknisi/create-customer")}
              className="flex flex-col items-center justify-center gap-2 rounded-2xl border border-gray-200 bg-white p-3 text-center cursor-pointer shadow-xs transition-all hover:border-emerald-500/50 hover:bg-emerald-50/30 dark:border-gray-800 dark:bg-white/[0.03] dark:hover:border-emerald-500/30 dark:hover:bg-emerald-950/10 hover:-translate-y-0.5 active:scale-95 group"
            >
              <div className="flex h-11 w-11 sm:h-12 sm:w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-emerald-500 to-emerald-600 text-white shadow-sm transition-transform group-hover:scale-105">
                <UserPlus className="h-5 w-5 sm:h-6 sm:w-6" />
              </div>
              <div className="min-w-0">
                <span className="block truncate text-xs font-bold text-gray-900 dark:text-white leading-tight">Pasang Baru</span>
                <span className="block truncate text-[10px] text-gray-500 dark:text-gray-400 font-medium mt-0.5">Registrasi</span>
              </div>
            </div>

            {/* 3: PPPoE & Optik */}
            <div
              onClick={() => router.visit("/teknisi/pppoe")}
              className="flex flex-col items-center justify-center gap-2 rounded-2xl border border-gray-200 bg-white p-3 text-center cursor-pointer shadow-xs transition-all hover:border-sky-500/50 hover:bg-sky-50/30 dark:border-gray-800 dark:bg-white/[0.03] dark:hover:border-sky-500/30 dark:hover:bg-sky-950/10 hover:-translate-y-0.5 active:scale-95 group"
            >
              <div className="flex h-11 w-11 sm:h-12 sm:w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-sky-500 to-sky-600 text-white shadow-sm transition-transform group-hover:scale-105">
                <KeyRound className="h-5 w-5 sm:h-6 sm:w-6" />
              </div>
              <div className="min-w-0">
                <span className="block truncate text-xs font-bold text-gray-900 dark:text-white leading-tight">PPPoE</span>
                <span className="block truncate text-[10px] text-gray-500 dark:text-gray-400 font-medium mt-0.5">Diagnostik</span>
              </div>
            </div>

            {/* 4: Peta ODP */}
            <div
              onClick={() => router.visit("/teknisi/map")}
              className="flex flex-col items-center justify-center gap-2 rounded-2xl border border-gray-200 bg-white p-3 text-center cursor-pointer shadow-xs transition-all hover:border-rose-500/50 hover:bg-rose-50/30 dark:border-gray-800 dark:bg-white/[0.03] dark:hover:border-rose-500/30 dark:hover:bg-rose-950/10 hover:-translate-y-0.5 active:scale-95 group"
            >
              <div className="flex h-11 w-11 sm:h-12 sm:w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-rose-500 to-rose-600 text-white shadow-sm transition-transform group-hover:scale-105">
                <Map className="h-5 w-5 sm:h-6 sm:w-6" />
              </div>
              <div className="min-w-0">
                <span className="block truncate text-xs font-bold text-gray-900 dark:text-white leading-tight">Peta ODP</span>
                <span className="block truncate text-[10px] text-gray-500 dark:text-gray-400 font-medium mt-0.5">GIS Fiber</span>
              </div>
            </div>

            {/* 5: Pendapatan */}
            <div
              onClick={() => router.visit("/teknisi/earnings")}
              className="flex flex-col items-center justify-center gap-2 rounded-2xl border border-gray-200 bg-white p-3 text-center cursor-pointer shadow-xs transition-all hover:border-purple-500/50 hover:bg-purple-50/30 dark:border-gray-800 dark:bg-white/[0.03] dark:hover:border-purple-500/30 dark:hover:bg-purple-950/10 hover:-translate-y-0.5 active:scale-95 group"
            >
              <div className="flex h-11 w-11 sm:h-12 sm:w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-purple-500 to-purple-600 text-white shadow-sm transition-transform group-hover:scale-105">
                <DollarSign className="h-5 w-5 sm:h-6 sm:w-6" />
              </div>
              <div className="min-w-0">
                <span className="block truncate text-xs font-bold text-gray-900 dark:text-white leading-tight">Pendapatan</span>
                <span className="block truncate text-[10px] text-gray-500 dark:text-gray-400 font-medium mt-0.5">Insentif</span>
              </div>
            </div>

            {/* 6: Riwayat */}
            <div
              onClick={() => router.visit("/teknisi/history")}
              className="flex flex-col items-center justify-center gap-2 rounded-2xl border border-gray-200 bg-white p-3 text-center cursor-pointer shadow-xs transition-all hover:border-teal-500/50 hover:bg-teal-50/30 dark:border-gray-800 dark:bg-white/[0.03] dark:hover:border-teal-500/30 dark:hover:bg-teal-950/10 hover:-translate-y-0.5 active:scale-95 group"
            >
              <div className="flex h-11 w-11 sm:h-12 sm:w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-teal-500 to-teal-600 text-white shadow-sm transition-transform group-hover:scale-105">
                <History className="h-5 w-5 sm:h-6 sm:w-6" />
              </div>
              <div className="min-w-0">
                <span className="block truncate text-xs font-bold text-gray-900 dark:text-white leading-tight">Riwayat</span>
                <span className="block truncate text-[10px] text-gray-500 dark:text-gray-400 font-medium mt-0.5">Selesai</span>
              </div>
            </div>

            {/* 7: ARP Static */}
            <div
              onClick={() => router.visit("/teknisi/arp")}
              className="flex flex-col items-center justify-center gap-2 rounded-2xl border border-gray-200 bg-white p-3 text-center cursor-pointer shadow-xs transition-all hover:border-indigo-500/50 hover:bg-indigo-50/30 dark:border-gray-800 dark:bg-white/[0.03] dark:hover:border-indigo-500/30 dark:hover:bg-indigo-950/10 hover:-translate-y-0.5 active:scale-95 group"
            >
              <div className="flex h-11 w-11 sm:h-12 sm:w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-indigo-500 to-indigo-600 text-white shadow-sm transition-transform group-hover:scale-105">
                <Network className="h-5 w-5 sm:h-6 sm:w-6" />
              </div>
              <div className="min-w-0">
                <span className="block truncate text-xs font-bold text-gray-900 dark:text-white leading-tight">ARP Static</span>
                <span className="block truncate text-[10px] text-gray-500 dark:text-gray-400 font-medium mt-0.5">IP Binding</span>
              </div>
            </div>

            {/* 8: SEMUA FITUR */}
            <div
              onClick={() => setAllFeaturesModalOpen(true)}
              className="flex flex-col items-center justify-center gap-2 rounded-2xl border border-dashed border-brand-500/40 bg-brand-50/40 p-3 text-center cursor-pointer shadow-xs transition-all hover:border-brand-500 hover:bg-brand-50/80 dark:border-brand-500/30 dark:bg-brand-950/20 dark:hover:border-brand-400 dark:hover:bg-brand-950/40 hover:-translate-y-0.5 active:scale-95 group"
            >
              <div className="flex h-11 w-11 sm:h-12 sm:w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-brand-500 to-brand-700 text-white shadow-sm transition-transform group-hover:scale-105">
                <LayoutGrid className="h-5 w-5 sm:h-6 sm:w-6" />
              </div>
              <div className="min-w-0">
                <span className="block truncate text-xs font-bold text-brand-600 dark:text-brand-400 leading-tight">Semua Fitur</span>
                <span className="block truncate text-[10px] text-gray-500 dark:text-gray-400 font-medium mt-0.5">Hak Akses</span>
              </div>
            </div>
          </div>
        </div>

        {/* ── TIKET TUGAS SAYA ── */}
        <div className="space-y-3">
          <div className="flex items-center justify-between">
            <h3 className="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 flex items-center gap-2">
              <Ticket className="h-4 w-4 text-brand-500" />
              <span>Tiket Tugas Saya ({tickets.length})</span>
            </h3>
            <Link
              href="/teknisi/pool"
              className="text-xs font-bold text-brand-500 hover:text-brand-600 dark:text-brand-400 flex items-center gap-1"
            >
              <span>Buka Pool Tiket</span>
              <ChevronRight className="h-3.5 w-3.5" />
            </Link>
          </div>

          {tickets.length === 0 ? (
            <div className="rounded-2xl border border-dashed border-gray-200 bg-white p-8 text-center dark:border-gray-800 dark:bg-white/[0.02]">
              <div className="flex h-12 w-12 items-center justify-center rounded-2xl bg-gray-100 text-gray-400 dark:bg-gray-800 dark:text-gray-500 mx-auto mb-3">
                <Ticket className="h-6 w-6" />
              </div>
              <p className="text-sm font-bold text-gray-900 dark:text-white">Tidak Ada Tiket Aktif</p>
              <p className="text-xs text-gray-500 dark:text-gray-400 mt-1 max-w-sm mx-auto">
                Tiket yang Anda ambil dari Pool Gangguan akan muncul di sini untuk penanganan dan penyelesaian.
              </p>
            </div>
          ) : (
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3.5">
              {tickets.map((t: TicketItem) => (
                <div
                  key={t.id}
                  className="rounded-2xl border border-gray-200 bg-white p-4 sm:p-5 shadow-xs dark:border-gray-800 dark:bg-white/[0.03] space-y-3"
                >
                  <div className="flex items-start gap-3">
                    <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-600 dark:bg-brand-950/30 dark:text-brand-400 border border-brand-200 dark:border-brand-800/40">
                      <Ticket className="h-5 w-5" />
                    </div>
                    <div className="min-w-0 flex-1">
                      <div className="flex items-center justify-between gap-2">
                        <h4 className="truncate text-sm font-bold text-gray-900 dark:text-white">{t.title}</h4>
                        <span
                          className={cn(
                            "inline-flex items-center rounded-full px-2 py-0.5 text-[9px] font-bold uppercase tracking-wider",
                            t.status === "in_progress"
                              ? "bg-sky-500/10 text-sky-600 dark:text-sky-400 border border-sky-500/20"
                              : t.status === "resolved"
                              ? "bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20"
                              : "bg-gray-500/10 text-gray-600 dark:text-gray-400 border border-gray-500/20"
                          )}
                        >
                          {t.status === "in_progress" ? "Dikerjakan" : t.status === "resolved" ? "Selesai" : t.status}
                        </span>
                      </div>

                      <p className="mt-0.5 truncate text-xs text-gray-500 dark:text-gray-400">
                        <span className="font-semibold text-gray-900 dark:text-white">{t.customer_name ?? "-"}</span> ·{" "}
                        {t.router_name ?? "-"} · {timeAgo(t.created_at)}
                      </p>

                      {t.description && (
                        <div className="mt-2.5 text-xs text-gray-700 dark:text-gray-300 bg-gray-50 dark:bg-gray-900/50 p-2.5 rounded-xl border border-gray-100 dark:border-gray-800">
                          {t.description}
                        </div>
                      )}

                      <div className="mt-3 flex flex-wrap items-center gap-2">
                        {t.priority && (
                          <span
                            className={cn(
                              "inline-flex items-center rounded-full px-2 py-0.5 text-[9px] font-bold uppercase",
                              t.priority === "high" || t.priority === "urgent"
                                ? "bg-rose-500/10 text-rose-600 dark:text-rose-400"
                                : t.priority === "medium"
                                ? "bg-amber-500/10 text-amber-600 dark:text-amber-400"
                                : "bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400"
                            )}
                          >
                            {t.priority}
                          </span>
                        )}

                        <button
                          type="button"
                          onClick={() => openSignalModal(t)}
                          className="inline-flex items-center gap-1.5 rounded-xl border border-brand-200 bg-brand-50 px-2.5 py-1 text-[11px] font-bold text-brand-600 hover:bg-brand-100 dark:border-brand-800/50 dark:bg-brand-950/30 dark:text-brand-400 dark:hover:bg-brand-950/50 transition-all active:scale-95 cursor-pointer"
                        >
                          <Activity className="h-3.5 w-3.5" />
                          <span>Cek Redaman TR-069</span>
                        </button>
                      </div>
                    </div>
                  </div>

                  {t.status === "in_progress" && (
                    <form
                      action={`/teknisi/resolve/${t.id}`}
                      method="POST"
                      className="border-t border-gray-100 dark:border-gray-800 pt-3"
                    >
                      <input type="hidden" name="_token" value={csrf()} />
                      <button
                        type="submit"
                        className="flex h-9 w-full items-center justify-center gap-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-xs font-bold text-white transition-all active:scale-95 shadow-xs cursor-pointer"
                      >
                        <CheckCircle2 className="h-4 w-4" />
                        <span>Tandai Pekerjaan Selesai</span>
                      </button>
                    </form>
                  )}
                </div>
              ))}
            </div>
          )}
        </div>
      </div>

      {/* ── MODAL SEMUA FITUR & HAK AKSES TEKNISI ── */}
      <Dialog open={allFeaturesModalOpen} onOpenChange={setAllFeaturesModalOpen}>
        <DialogContent className="w-[calc(100vw-24px)] sm:max-w-2xl max-h-[88dvh] overflow-y-auto overflow-x-hidden p-5 sm:p-6 space-y-4 rounded-3xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 text-gray-900 dark:text-white">
          <DialogHeader className="pb-3 border-b border-gray-100 dark:border-gray-800 pr-8 text-left">
            <DialogTitle className="text-base sm:text-lg font-bold flex items-center gap-2 text-gray-900 dark:text-white">
              <LayoutGrid className="h-5 w-5 text-brand-500" />
              <span>Semua Fitur &amp; Hak Akses Teknisi</span>
            </DialogTitle>
          </DialogHeader>

          <div className="space-y-3 min-w-0">
            <p className="text-xs text-gray-500 dark:text-gray-400">
              Berikut modul operasional dan hak akses yang diberikan Administrator untuk akun Anda:
            </p>

            <div className="grid gap-3 sm:grid-cols-2 min-w-0">
              {technicianFeatures.map((feat) => {
                const IconComp = feat.icon
                return (
                  <div
                    key={feat.key}
                    onClick={() => {
                      if (feat.permitted) {
                        setAllFeaturesModalOpen(false)
                        router.visit(feat.href)
                      }
                    }}
                    className={cn(
                      "p-4 rounded-2xl border transition-all flex items-start gap-3 min-w-0 overflow-hidden",
                      feat.permitted
                        ? "border-gray-200 bg-gray-50/50 hover:border-brand-500 hover:bg-brand-50/20 dark:border-gray-800 dark:bg-white/[0.02] dark:hover:border-brand-500/40 dark:hover:bg-brand-950/20 cursor-pointer group"
                        : "border-gray-100 bg-gray-50/30 opacity-50 dark:border-gray-800/40 dark:bg-white/[0.01] cursor-not-allowed"
                    )}
                  >
                    <div
                      className={cn(
                        "flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl text-white bg-gradient-to-br shadow-xs transition-transform group-hover:scale-105",
                        feat.gradient
                      )}
                    >
                      <IconComp className="h-5 w-5" />
                    </div>

                    <div className="min-w-0 flex-1 overflow-hidden">
                      <div className="flex items-center justify-between gap-1">
                        <h4 className="text-xs font-bold text-gray-900 dark:text-white truncate group-hover:text-brand-500 transition-colors">
                          {feat.title}
                        </h4>
                        <span
                          className={cn(
                            "text-[8px] font-extrabold uppercase px-1.5 py-0.5 rounded-full shrink-0 border",
                            feat.permitted
                              ? "bg-emerald-500/10 border-emerald-500/20 text-emerald-600 dark:text-emerald-400"
                              : "bg-rose-500/10 border-rose-500/20 text-rose-600 dark:text-rose-400"
                          )}
                        >
                          {feat.permitted ? "Aktif" : "Terkunci"}
                        </span>
                      </div>
                      <p className="text-[11px] text-gray-500 dark:text-gray-400 mt-1 line-clamp-2 leading-relaxed">
                        {feat.desc}
                      </p>
                    </div>
                  </div>
                )
              })}
            </div>
          </div>
        </DialogContent>
      </Dialog>

      {/* ── MODAL CEK REDAMAN & TELEMETRI ONT TR-069 ── */}
      {signalModalOpen && (
        <Dialog open={signalModalOpen} onOpenChange={setSignalModalOpen}>
          <DialogContent className="w-[calc(100vw-24px)] sm:max-w-lg max-h-[90dvh] overflow-y-auto p-5 sm:p-6 space-y-4 rounded-3xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 text-gray-900 dark:text-white">
            <DialogHeader className="pb-3 border-b border-gray-100 dark:border-gray-800 pr-8 text-left">
              <DialogTitle className="text-base font-bold text-gray-900 dark:text-white flex items-center gap-2">
                <Activity className="h-5 w-5 text-brand-500" />
                <span>Telemetri &amp; Redaman TR-069</span>
              </DialogTitle>
            </DialogHeader>

            {signalLoading ? (
              <div className="flex flex-col items-center justify-center p-8 space-y-3">
                <Loader2 className="h-8 w-8 animate-spin text-brand-500" />
                <p className="text-xs text-gray-500 dark:text-gray-400">Menghubungi ACS &amp; membaca telemetri ONT...</p>
              </div>
            ) : signalError ? (
              <div className="p-4 rounded-2xl border border-rose-500/20 bg-rose-500/10 text-rose-600 dark:text-rose-400 text-xs space-y-2">
                <div className="flex items-center gap-2 font-bold">
                  <AlertTriangle className="h-4 w-4" />
                  <span>Gagal Membaca Data TR-069</span>
                </div>
                <p>{signalError}</p>
              </div>
            ) : signalData ? (
              <div className="space-y-3.5 text-xs">
                <div className="rounded-2xl border border-gray-200 bg-gray-50 p-4 space-y-2 dark:border-gray-800 dark:bg-gray-800/40">
                  <div className="flex justify-between">
                    <span className="text-gray-500 dark:text-gray-400">Pelanggan:</span>
                    <span className="font-bold text-gray-900 dark:text-white">{signalData.customer_name}</span>
                  </div>
                  <div className="flex justify-between">
                    <span className="text-gray-500 dark:text-gray-400">PPPoE:</span>
                    <span className="font-mono text-gray-700 dark:text-gray-300">{signalData.pppoe_username}</span>
                  </div>
                  <div className="flex justify-between">
                    <span className="text-gray-500 dark:text-gray-400">Serial ONT:</span>
                    <span className="font-mono font-bold text-brand-600 dark:text-brand-400">{signalData.serial_number}</span>
                  </div>
                  <div className="flex justify-between">
                    <span className="text-gray-500 dark:text-gray-400">Model / Vendor:</span>
                    <span className="text-gray-700 dark:text-gray-300">{signalData.manufacturer} {signalData.model}</span>
                  </div>
                </div>

                <div className="rounded-2xl border border-gray-200 bg-gray-50 p-4 space-y-2 dark:border-gray-800 dark:bg-gray-800/40">
                  <div className="flex justify-between items-center">
                    <span className="text-gray-500 dark:text-gray-400">Optical Rx Power:</span>
                    <span className="font-mono font-bold text-base text-emerald-600 dark:text-emerald-400">
                      {signalData.rx_power}
                    </span>
                  </div>
                  <div className="flex justify-between">
                    <span className="text-gray-500 dark:text-gray-400">Suhu Perangkat:</span>
                    <span className="text-gray-700 dark:text-gray-300">{signalData.temperature}</span>
                  </div>
                  <div className="flex justify-between">
                    <span className="text-gray-500 dark:text-gray-400">Uptime ONT:</span>
                    <span className="text-gray-700 dark:text-gray-300">{signalData.uptime}</span>
                  </div>
                  <div className="flex justify-between">
                    <span className="text-gray-500 dark:text-gray-400">SSID Wi-Fi:</span>
                    <span className="text-gray-700 dark:text-gray-300">{signalData.ssid} ({signalData.active_clients} Perangkat)</span>
                  </div>
                </div>

                {rebootMsg && (
                  <div className="p-3 rounded-xl border border-emerald-500/20 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 text-xs">
                    {rebootMsg}
                  </div>
                )}

                <button
                  type="button"
                  onClick={handleRebootOnu}
                  disabled={rebooting}
                  className="w-full flex items-center justify-center gap-2 rounded-xl bg-amber-600 hover:bg-amber-700 disabled:opacity-50 p-3 text-xs font-bold text-white transition-all shadow-xs cursor-pointer"
                >
                  <RotateCcw className={cn("h-4 w-4", rebooting && "animate-spin")} />
                  <span>{rebooting ? "Mengirim Perintah Reboot..." : "Reboot ONT Pelanggan"}</span>
                </button>
              </div>
            ) : null}
          </DialogContent>
        </Dialog>
      )}
    </AppLayout>
  )
}

function csrf(): string {
  const meta = document.querySelector('meta[name="csrf-token"]')
  return meta ? meta.getAttribute("content") ?? "" : ""
}
