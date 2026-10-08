import React, { useState, useEffect, useRef } from "react"
import { AppLayout } from "@/components/layout/app-layout"
import {
  ShoppingBag,
  Search,
  CheckCircle2,
  XCircle,
  Clock,
  ExternalLink,
  Phone,
  MessageCircle,
  Package as PackageIcon,
  Copy,
  Check,
  Layers,
  ChevronDown,
  Eye,
  CreditCard,
  Building2,
  MapPin,
  KeyRound,
  FileText,
  AlertTriangle,
  Pencil,
  Trash2,
  Printer,
  Sliders,
  CheckCheck,
  Truck,
  DollarSign,
  X,
} from "lucide-react"
import { PageProps } from "@/types"
import { cn, formatRupiah } from "@/lib/utils"
import { adminSidebarItems, adminNavItems, adminBrand } from "@/lib/admin-nav"
import { useForm, router, Link } from "@inertiajs/react"
import MetricCard from "@/components/tailadmin/MetricCard"
import Modal from "@/components/tailadmin/Modal"
import { EmptyState } from "@/components/ui/empty-state"
import { ViewModeSwitcher, type ViewMode } from "@/components/tailadmin/ui/view-mode-switcher"

interface ShopOrderItem {
  id: number
  product_name: string
  price: number
  quantity: number
  subtotal: number
}

interface ShopOrder {
  id: number
  order_number: string
  customer_name: string
  customer_phone: string
  customer_email?: string | null
  shipping_address?: string | null
  customer_notes?: string | null
  admin_notes?: string | null
  tracking_number?: string | null
  voucher_username?: string | null
  voucher_password?: string | null
  voucher_profile?: string | null
  voucher_created_in_mikrotik: boolean
  subtotal: number
  total_amount: number
  payment_method: string
  payment_bank?: string | null
  payment_proof?: string | null
  payment_status: "unpaid" | "paid" | "verified" | "rejected"
  order_status: "pending" | "processing" | "shipped" | "completed" | "cancelled"
  created_at: string
  items: ShopOrderItem[]
}

interface VoucherSlot {
  package_name?: string
  username: string
  password?: string
  profile?: string
}

function parseVouchers(order: ShopOrder): VoucherSlot[] {
  if (!order.voucher_username) {
    const vouchers: VoucherSlot[] = []
    let vCount = 0
    order.items?.forEach((it) => {
      if (it.product_name?.toLowerCase().includes("voucher")) {
        for (let i = 0; i < (it.quantity || 1); i++) {
          vCount++
          vouchers.push({
            package_name: it.product_name,
            username: `vch_${order.order_number}_${vCount}`,
            password: `pass_${vCount}`,
            profile: "default",
          })
        }
      }
    })
    return vouchers
  }

  const raw = order.voucher_username.trim()
  if (raw.startsWith("[") && raw.endsWith("]")) {
    try {
      const parsed = JSON.parse(raw)
      if (Array.isArray(parsed) && parsed.length > 0) {
        return parsed
      }
    } catch (e) {}
  }

  const totalVoucherQty =
    order.items?.reduce((acc, it) => {
      if (it.product_name?.toLowerCase().includes("voucher")) {
        return acc + (it.quantity || 1)
      }
      return acc
    }, 0) || 1

  if (totalVoucherQty > 1) {
    const list: VoucherSlot[] = []
    let counter = 0
    order.items?.forEach((it) => {
      const isVch = it.product_name?.toLowerCase().includes("voucher")
      const qty = isVch ? it.quantity || 1 : 0
      for (let q = 0; q < qty; q++) {
        counter++
        list.push({
          package_name: it.product_name,
          username: counter === 1 ? order.voucher_username! : `${order.voucher_username}_${counter}`,
          password:
            counter === 1
              ? order.voucher_password || order.voucher_username!
              : `${order.voucher_password || order.voucher_username}_${counter}`,
          profile: order.voucher_profile || "default",
        })
      }
    })
    if (list.length > 0) return list
  }

  return [
    {
      package_name: order.items?.[0]?.product_name || "Voucher WiFi",
      username: order.voucher_username,
      password: order.voucher_password || order.voucher_username,
      profile: order.voucher_profile || "default",
    },
  ]
}

function cleanPhone(phone: string): string {
  const digits = phone.replace(/[^0-9]/g, "")
  return digits.startsWith("0") ? "62" + digits.substring(1) : digits
}

function buildOrderWhatsAppMessage(order: ShopOrder, storeName = "NODERA"): string {
  const cleanAddress = (() => {
    if (!order.shipping_address || order.shipping_address === "Layanan Voucher Online") {
      return "Layanan Voucher Online"
    }
    const lines = order.shipping_address
      .split(/\r?\n/)
      .map((l) => l.trim())
      .filter(Boolean)
    const unique = Array.from(new Set(lines))
    return unique.length > 0 ? unique.join(", ") : "Alamat belum diisi"
  })()

  const formattedTotal = formatRupiah(order.total_amount)
  const itemsText =
    order.items
      ?.map(
        (it, idx) =>
          `${idx + 1}. *${it.product_name}*\n   Qty: ${it.quantity} x ${formatRupiah(it.price)} = ${formatRupiah(
            it.subtotal
          )}`
      )
      .join("\n") || ""

  const dateStr = order.created_at
    ? new Date(order.created_at).toLocaleDateString("id-ID", {
        day: "2-digit",
        month: "2-digit",
        year: "numeric",
        hour: "2-digit",
        minute: "2-digit",
      }) + " WIB"
    : ""

  const vouchers = parseVouchers(order)
  const vouchersText =
    vouchers.length > 0
      ? `*Rincian Akun Voucher Hotspot (${vouchers.length} Akun):*\n` +
        vouchers
          .map(
            (v, idx) =>
              `${idx + 1}. *${v.package_name || "Voucher"}* (Profil: ${v.profile || "default"})\n   Username: ${
                v.username
              }\n   Password: ${v.password || "-"}`
          )
          .join("\n") +
        "\n----------------------------------------\nSilakan hubungkan perangkat Anda ke WiFi Hotspot lalu login di portal menggunakan username dan password di atas.\n"
      : ""

  if (order.order_status === "completed" || order.payment_status === "verified" || order.payment_status === "paid") {
    return [
      `*PESANAN DISETUJUI & SELESAI - ${storeName.toUpperCase()} SHOP*`,
      `No. Order: *${order.order_number}*`,
      dateStr ? `Tanggal: ${dateStr}` : "",
      `----------------------------------------`,
      `*Status: PEMBAYARAN DITERIMA & SELESAI*`,
      `----------------------------------------`,
      `*Data Pembeli:*`,
      `- Nama: ${order.customer_name}`,
      `- No. WhatsApp: ${order.customer_phone}`,
      `- Alamat Kirim: ${cleanAddress}`,
      `----------------------------------------`,
      `*Rincian Pesanan:*`,
      itemsText,
      `----------------------------------------`,
      `*TOTAL PEMBAYARAN: ${formattedTotal}*`,
      `Metode: ${order.payment_method}${order.payment_bank ? ` (${order.payment_bank})` : ""}`,
      `----------------------------------------`,
      vouchersText,
      `Halo ${order.customer_name}, pembayaran pesanan Anda telah kami terima dan transaksi telah disetujui. Terima kasih telah berbelanja di ${storeName}.`,
    ]
      .filter(Boolean)
      .join("\n")
  }

  if (order.order_status === "cancelled" || order.payment_status === "rejected") {
    return [
      `*PEMBERITAHUAN PESANAN DIBATALKAN - ${storeName.toUpperCase()} SHOP*`,
      `No. Order: *${order.order_number}*`,
      dateStr ? `Tanggal: ${dateStr}` : "",
      `----------------------------------------`,
      `*Status: DITOLAK / DIBATALKAN*`,
      order.admin_notes ? `Alasan: ${order.admin_notes}` : "",
      `----------------------------------------`,
      `*Data Pembeli:*`,
      `- Nama: ${order.customer_name}`,
      `- No. WhatsApp: ${order.customer_phone}`,
      `----------------------------------------`,
      `*TOTAL: ${formattedTotal}*`,
      `----------------------------------------`,
      `Halo ${order.customer_name}, mohon maaf pesanan Anda dengan No. Order #${order.order_number} belum dapat kami setujui atau telah dibatalkan. Jika Anda memiliki pertanyaan atau ingin konfirmasi lebih lanjut, silakan hubungi kami. Terima kasih.`,
    ]
      .filter(Boolean)
      .join("\n")
  }

  if (order.order_status === "shipped") {
    return [
      `*STATUS PESANAN: DALAM PENGIRIMAN - ${storeName.toUpperCase()} SHOP*`,
      `No. Order: *${order.order_number}*`,
      dateStr ? `Tanggal: ${dateStr}` : "",
      `----------------------------------------`,
      `*Status: SEDANG DIKIRIM*`,
      order.tracking_number ? `No. Resi / Kurir: *${order.tracking_number}*` : "",
      `----------------------------------------`,
      `*Data Pembeli:*`,
      `- Nama: ${order.customer_name}`,
      `- Alamat Kirim: ${cleanAddress}`,
      `----------------------------------------`,
      `*Rincian Pesanan:*`,
      itemsText,
      `----------------------------------------`,
      `Halo ${order.customer_name}, pesanan Anda telah dikirimkan ke alamat tujuan dengan nomor resi di atas. Silakan lacak pengiriman secara berkala. Terima kasih.`,
    ]
      .filter(Boolean)
      .join("\n")
  }

  if (order.order_status === "processing") {
    return [
      `*STATUS PESANAN: SEDANG DIPROSES - ${storeName.toUpperCase()} SHOP*`,
      `No. Order: *${order.order_number}*`,
      dateStr ? `Tanggal: ${dateStr}` : "",
      `----------------------------------------`,
      `*Status: SEDANG DIPROSES*`,
      `----------------------------------------`,
      `*Data Pembeli:*`,
      `- Nama: ${order.customer_name}`,
      `- Alamat Kirim: ${cleanAddress}`,
      `----------------------------------------`,
      `*Rincian Pesanan:*`,
      itemsText,
      `----------------------------------------`,
      `Halo ${order.customer_name}, pesanan Anda saat ini sedang disiapkan dan diproses oleh tim kami. Terima kasih telah berbelanja di ${storeName}.`,
    ]
      .filter(Boolean)
      .join("\n")
  }

  return [
    `*KONFIRMASI PESANAN - ${storeName.toUpperCase()} SHOP*`,
    `No. Order: *${order.order_number}*`,
    dateStr ? `Tanggal: ${dateStr}` : "",
    `----------------------------------------`,
    `*Data Pembeli:*`,
    `- Nama: ${order.customer_name}`,
    `- No. WhatsApp: ${order.customer_phone}`,
    order.customer_email ? `- Email: ${order.customer_email}` : "",
    `- Alamat Kirim: ${cleanAddress}`,
    order.customer_notes ? `- Catatan: ${order.customer_notes}` : "",
    `----------------------------------------`,
    `*Rincian Barang yang Dipesan:*`,
    itemsText,
    `----------------------------------------`,
    `*TOTAL PEMBAYARAN: ${formattedTotal}*`,
    `Metode: ${order.payment_method}${order.payment_bank ? ` (${order.payment_bank})` : ""}`,
    ``,
    `Halo ${order.customer_name}, kami telah menerima data pesanan Anda dengan No. Order #${order.order_number}. Mohon tunggu proses verifikasi pembayaran dari admin kami. Terima kasih!`,
  ]
    .filter(Boolean)
    .join("\n")
}

export default function AdminShopOrdersPage({
  orders,
  stats,
  filters,
}: PageProps<{
  orders: {
    data: ShopOrder[]
    current_page: number
    last_page: number
    total: number
  }
  stats: {
    total: number
    pending: number
    completed: number
    cancelled: number
    total_revenue: number
  }
  filters: { search?: string; status?: string }
}>) {
  const [search, setSearch] = useState(filters.search || "")
  const [status, setStatus] = useState(filters.status || "all")
  const [viewMode, setViewMode] = useState<ViewMode>("table")
  const [expandedIds, setExpandedIds] = useState<Record<number, boolean>>({})
  const [selectedOrder, setSelectedOrder] = useState<ShopOrder | null>(null)
  const [isDetailOpen, setIsDetailOpen] = useState(false)
  const [isEditStatusOpen, setIsEditStatusOpen] = useState(false)
  const [isInvoiceOpen, setIsInvoiceOpen] = useState(false)
  const [copiedKey, setCopiedKey] = useState<string | null>(null)
  const isFirstRender = useRef(true)

  useEffect(() => {
    if (isFirstRender.current) {
      isFirstRender.current = false
      return
    }
    const timeout = setTimeout(() => {
      router.get(
        "/admin/shop/orders",
        {
          search: search || undefined,
          status: status !== "all" ? status : undefined,
        },
        { preserveState: true, preserveScroll: true, replace: true }
      )
    }, 300)
    return () => clearTimeout(timeout)
  }, [search, status])

  const statusForm = useForm({
    order_status: "pending",
    payment_status: "unpaid",
    tracking_number: "",
    admin_notes: "",
  })

  const toggleExpand = (id: number) => {
    setExpandedIds((prev) => ({ ...prev, [id]: !prev[id] }))
  }

  const openDetail = (order: ShopOrder, e?: React.MouseEvent) => {
    if (e) e.stopPropagation()
    setSelectedOrder(order)
    setIsDetailOpen(true)
  }

  const openEditStatus = (order: ShopOrder, e?: React.MouseEvent) => {
    if (e) e.stopPropagation()
    setSelectedOrder(order)
    statusForm.setData({
      order_status: order.order_status,
      payment_status: order.payment_status,
      tracking_number: order.tracking_number || "",
      admin_notes: order.admin_notes || "",
    })
    setIsEditStatusOpen(true)
  }

  const openInvoice = (order: ShopOrder, e?: React.MouseEvent) => {
    if (e) e.stopPropagation()
    setSelectedOrder(order)
    setIsInvoiceOpen(true)
  }

  const handleUpdateStatus = (e: React.FormEvent) => {
    e.preventDefault()
    if (!selectedOrder) return

    statusForm.post(`/admin/shop/orders/${selectedOrder.id}/update-status`, {
      preserveScroll: true,
      onSuccess: () => {
        setIsEditStatusOpen(false)
        setIsDetailOpen(false)
      },
    })
  }

  const handleDeleteOrder = (order: ShopOrder, e?: React.MouseEvent) => {
    if (e) e.stopPropagation()
    if (confirm(`Apakah Anda yakin ingin menghapus pesanan #${order.order_number} dari sistem?`)) {
      router.post(
        `/admin/shop/orders/${order.id}/delete`,
        {},
        {
          preserveScroll: true,
          onSuccess: () => {
            setIsDetailOpen(false)
          },
        }
      )
    }
  }

  const handleApprove = (id: number, e?: React.MouseEvent) => {
    if (e) e.stopPropagation()
    if (confirm("Setujui pesanan ini? Seluruh akun hotspot akan otomatis dibuat di router MikroTik.")) {
      router.post(
        `/admin/shop/orders/${id}/approve`,
        {},
        {
          preserveScroll: true,
          onSuccess: () => {
            setIsDetailOpen(false)
          },
        }
      )
    }
  }

  const handleReject = (id: number, e?: React.MouseEvent) => {
    if (e) e.stopPropagation()
    const reason = prompt("Alasan penolakan / pembatalan pesanan:")
    if (reason !== null) {
      router.post(
        `/admin/shop/orders/${id}/reject`,
        { admin_notes: reason },
        {
          preserveScroll: true,
          onSuccess: () => {
            setIsDetailOpen(false)
          },
        }
      )
    }
  }

  const copyToClipboard = (text: string, key: string, e?: React.MouseEvent) => {
    if (e) e.stopPropagation()
    navigator.clipboard.writeText(text)
    setCopiedKey(key)
    setTimeout(() => setCopiedKey(null), 2000)
  }

  const handleStatusFilter = (st: string) => {
    setStatus(st)
    router.get("/admin/shop/orders", { search, status: st })
  }

  const getOrderStatusBadge = (st: string) => {
    switch (st) {
      case "pending":
        return (
          <span className="inline-flex items-center gap-1 rounded-md bg-amber-500 px-2 py-0.5 text-[11px] font-bold uppercase tracking-wider text-white shadow-xs whitespace-nowrap">
            <Clock className="w-3 h-3" /> Menunggu ACC
          </span>
        )
      case "processing":
        return (
          <span className="inline-flex items-center gap-1 rounded-md bg-brand-500 px-2 py-0.5 text-[11px] font-bold uppercase tracking-wider text-white shadow-xs whitespace-nowrap">
            <Sliders className="w-3 h-3" /> Diproses
          </span>
        )
      case "shipped":
        return (
          <span className="inline-flex items-center gap-1 rounded-md bg-purple-600 px-2 py-0.5 text-[11px] font-bold uppercase tracking-wider text-white shadow-xs whitespace-nowrap">
            <Truck className="w-3 h-3" /> Dikirim
          </span>
        )
      case "completed":
        return (
          <span className="inline-flex items-center gap-1 rounded-md bg-emerald-500 px-2 py-0.5 text-[11px] font-bold uppercase tracking-wider text-white shadow-xs whitespace-nowrap">
            <CheckCircle2 className="w-3 h-3" /> Selesai
          </span>
        )
      case "cancelled":
        return (
          <span className="inline-flex items-center gap-1 rounded-md bg-rose-500 px-2 py-0.5 text-[11px] font-bold uppercase tracking-wider text-white shadow-xs whitespace-nowrap">
            <XCircle className="w-3 h-3" /> Dibatalkan
          </span>
        )
      default:
        return (
          <span className="inline-flex items-center gap-1 rounded-md bg-gray-500 px-2 py-0.5 text-[11px] font-bold uppercase tracking-wider text-white shadow-xs whitespace-nowrap">
            {st}
          </span>
        )
    }
  }

  const getPaymentBadge = (st: string) => {
    switch (st) {
      case "paid":
      case "verified":
        return (
          <span className="inline-flex items-center gap-1 rounded-md bg-emerald-500 px-2 py-0.5 text-[11px] font-bold uppercase tracking-wider text-white shadow-xs whitespace-nowrap">
            <CheckCheck className="w-3 h-3" /> Lunas
          </span>
        )
      case "unpaid":
        return (
          <span className="inline-flex items-center gap-1 rounded-md bg-amber-500 px-2 py-0.5 text-[11px] font-bold uppercase tracking-wider text-white shadow-xs whitespace-nowrap">
            <Clock className="w-3 h-3" /> Belum Bayar
          </span>
        )
      case "rejected":
        return (
          <span className="inline-flex items-center gap-1 rounded-md bg-rose-500 px-2 py-0.5 text-[11px] font-bold uppercase tracking-wider text-white shadow-xs whitespace-nowrap">
            <XCircle className="w-3 h-3" /> Ditolak
          </span>
        )
      default:
        return (
          <span className="inline-flex items-center gap-1 rounded-md bg-gray-500 px-2 py-0.5 text-[11px] font-bold uppercase tracking-wider text-white shadow-xs whitespace-nowrap">
            {st}
          </span>
        )
    }
  }

  return (
    <AppLayout
      title="Pesanan Toko & Voucher"
      brand={adminBrand}
      sidebarItems={adminSidebarItems}
      navItems={adminNavItems}
    >
      <div className="space-y-4 sm:space-y-6">
        {/* Top Row: MetricCards */}
        <div className="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
          <MetricCard
            title="Total Pesanan"
            value={stats.total}
            sub="Seluruh transaksi masuk"
            icon={<ShoppingBag className="h-5 w-5 sm:h-6 sm:w-6 text-brand-500" />}
            iconBgColor="bg-brand-50 dark:bg-brand-500/10"
            iconColor="text-brand-500 dark:text-brand-400"
          />
          <MetricCard
            title="Menunggu ACC"
            value={stats.pending}
            sub="Perlu verifikasi admin"
            icon={<Clock className="h-5 w-5 sm:h-6 sm:w-6 text-amber-500" />}
            iconBgColor="bg-amber-50 dark:bg-amber-500/10"
            iconColor="text-amber-500 dark:text-amber-400"
          />
          <MetricCard
            title="Selesai / Lunas"
            value={stats.completed}
            sub="Transaksi berhasil"
            icon={<CheckCircle2 className="h-5 w-5 sm:h-6 sm:w-6 text-emerald-500" />}
            iconBgColor="bg-emerald-50 dark:bg-emerald-500/10"
            iconColor="text-emerald-500 dark:text-emerald-400"
          />
          <MetricCard
            title="Total Pendapatan"
            value={formatRupiah(stats.total_revenue)}
            sub="Akumulasi omset toko"
            icon={<DollarSign className="h-5 w-5 sm:h-6 sm:w-6 text-purple-500" />}
            iconBgColor="bg-purple-50 dark:bg-purple-500/10"
            iconColor="text-purple-500 dark:text-purple-400"
          />
        </div>

        {/* Master Card with Toolbar & Orders List */}
        <div className="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xs dark:border-gray-800 dark:bg-white/[0.03]">
          {/* 1-Line Header Toolbar */}
          <div className="rounded-2xl border-b border-gray-200 bg-white p-3 sm:p-4 shadow-xs dark:border-gray-800 dark:bg-white/[0.03] flex flex-col gap-2.5 sm:gap-3 lg:flex-row lg:items-center lg:justify-between">
            {/* Left Side: Search & Status Dropdown */}
            <div className="flex flex-wrap items-center gap-2 sm:gap-3 flex-1 min-w-0">
              <div className="relative flex-1 min-w-[180px] sm:min-w-[220px] w-full sm:w-auto">
                <Search className="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                <input
                  type="text"
                  value={search}
                  onChange={(e) => setSearch(e.target.value)}
                  placeholder="Cari no. pesanan, nama pembeli, voucher..."
                  className="h-10 w-full pl-9 pr-8 rounded-xl text-xs font-medium border border-gray-200 bg-gray-50/50 hover:bg-white focus:bg-white dark:border-gray-800 dark:bg-gray-900/50 dark:hover:bg-gray-900 text-gray-900 dark:text-white placeholder-gray-400 focus:outline-none focus:border-brand-500 transition"
                />
                {search && (
                  <button
                    type="button"
                    onClick={() => setSearch("")}
                    className="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 cursor-pointer"
                  >
                    <X className="h-3.5 w-3.5" />
                  </button>
                )}
              </div>

              {/* Status Select Filter Dropdown */}
              <select
                value={status}
                onChange={(e) => handleStatusFilter(e.target.value)}
                className="h-10 rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 px-3 text-xs font-semibold text-gray-700 dark:text-gray-200 cursor-pointer w-full sm:w-auto shrink-0 focus:outline-none focus:border-brand-500"
              >
                <option value="all">Semua Status</option>
                <option value="pending">Menunggu</option>
                <option value="processing">Diproses</option>
                <option value="completed">Selesai</option>
                <option value="cancelled">Batal</option>
              </select>
            </div>

            {/* Right Side: Quick Links & View Mode */}
            <div className="flex items-center gap-2 shrink-0 w-full sm:w-auto justify-end flex-wrap sm:flex-nowrap">
              <ViewModeSwitcher value={viewMode} onChange={setViewMode} />

              <Link
                href="/admin/shop/products"
                className="h-10 inline-flex items-center justify-center gap-1.5 px-3.5 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:hover:bg-gray-800 text-xs font-semibold text-gray-700 dark:text-gray-200 transition shadow-2xs cursor-pointer shrink-0"
                title="Katalog Produk"
              >
                <PackageIcon className="h-4 w-4 text-brand-500" />
                <span className="hidden sm:inline">Katalog Produk</span>
              </Link>
              <Link
                href="/admin/landing-settings"
                className="h-10 inline-flex items-center justify-center gap-1.5 px-3.5 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:hover:bg-gray-800 text-xs font-semibold text-gray-700 dark:text-gray-200 transition shadow-2xs cursor-pointer shrink-0"
                title="Pengaturan Landing"
              >
                <Layers className="h-4 w-4 text-purple-500" />
                <span className="hidden sm:inline">Pengaturan Toko</span>
              </Link>
            </div>
          </div>

          {/* Orders List Content */}
          {orders.data.length === 0 ? (
            <div className="p-8 sm:p-12 text-center">
              <EmptyState
                icon={<ShoppingBag className="h-8 w-8 text-brand-500" />}
                title="Belum ada pesanan masuk"
                description={
                  search || status !== "all"
                    ? "Tidak ada data pesanan yang sesuai dengan filter pencarian."
                    : "Transaksi pesanan dari subdomain toko online Anda akan muncul di sini secara real-time."
                }
              />
            </div>
          ) : viewMode === "table" ? (
            <div className="w-full overflow-x-auto custom-scrollbar">
              <table className="w-full text-start text-xs min-w-[1000px] border-collapse">
                <thead className="border-b border-gray-200 bg-gray-50/80 font-bold text-gray-500 dark:border-gray-800 dark:bg-gray-900/50 dark:text-gray-400">
                  <tr>
                    <th className="py-3 px-4 text-start font-semibold">No. Pesanan</th>
                    <th className="py-3 px-4 text-start font-semibold">Tanggal</th>
                    <th className="py-3 px-4 text-start font-semibold">Pembeli & Kontak</th>
                    <th className="py-3 px-4 text-start font-semibold">Item / Produk</th>
                    <th className="py-3 px-4 text-start font-semibold">Total Nominal</th>
                    <th className="py-3 px-4 text-start font-semibold">Metode Bayar</th>
                    <th className="py-3 px-4 text-center font-semibold">Status Bayar</th>
                    <th className="py-3 px-4 text-center font-semibold">Status Pesanan</th>
                    <th className="py-3 px-4 text-center font-semibold">Aksi</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-gray-100 dark:divide-gray-800 font-medium">
                  {orders.data.map((order) => (
                    <tr
                      key={order.id}
                      className="hover:bg-gray-50/50 dark:hover:bg-gray-800/30 transition-colors"
                    >
                      <td className="py-3.5 px-4 font-mono font-black text-gray-900 dark:text-white whitespace-nowrap">
                        #{order.order_number}
                      </td>
                      <td className="py-3.5 px-4 text-gray-500 dark:text-gray-400 whitespace-nowrap">
                        {new Date(order.created_at).toLocaleString("id-ID", {
                          day: "2-digit",
                          month: "short",
                          year: "numeric",
                          hour: "2-digit",
                          minute: "2-digit",
                        })}
                      </td>
                      <td className="py-3.5 px-4">
                        <div className="space-y-0.5">
                          <div className="font-bold text-gray-900 dark:text-white">{order.customer_name}</div>
                          <div className="text-[11px] text-gray-500 dark:text-gray-400 flex items-center gap-1">
                            <Phone className="h-3 w-3 text-gray-400" />
                            <span>{order.customer_phone}</span>
                          </div>
                        </div>
                      </td>
                      <td className="py-3.5 px-4">
                        <div className="text-gray-700 dark:text-gray-300 max-w-[200px] truncate">
                          {order.items && order.items.length > 0
                            ? order.items.map((it) => `${it.quantity}x ${it.product_name}`).join(", ")
                            : "1 Item"}
                        </div>
                      </td>
                      <td className="py-3.5 px-4 font-mono font-bold text-emerald-600 dark:text-emerald-400 whitespace-nowrap">
                        {formatRupiah(order.total_amount)}
                      </td>
                      <td className="py-3.5 px-4 text-gray-700 dark:text-gray-300 uppercase font-mono font-semibold whitespace-nowrap">
                        {order.payment_method}
                      </td>
                      <td className="py-3.5 px-4 text-center">
                        {getPaymentBadge(order.payment_status)}
                      </td>
                      <td className="py-3.5 px-4 text-center">
                        {getOrderStatusBadge(order.order_status)}
                      </td>
                      <td className="py-3.5 px-4 text-center">
                        <button
                          type="button"
                          onClick={(e) => openDetail(order, e)}
                          className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200 font-bold text-xs transition cursor-pointer whitespace-nowrap"
                        >
                          Kelola
                        </button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          ) : (
            <div className="divide-y divide-gray-200 dark:divide-gray-800">
              {orders.data.map((order) => {
                const isExpanded = Boolean(expandedIds[order.id])
                const voucherList = parseVouchers(order)
                const isVoucher = voucherList.length > 0
                const isPending = order.order_status === "pending"

                return (
                  <div key={order.id} className="transition-colors hover:bg-gray-50/50 dark:hover:bg-white/[0.01]">
                    {/* Header Row */}
                    <div
                      onClick={() => toggleExpand(order.id)}
                      className="p-4 sm:p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-3 cursor-pointer"
                    >
                      <div className="flex items-start sm:items-center gap-3 min-w-0">
                        <div
                          className={cn(
                            "flex h-10 w-10 shrink-0 items-center justify-center rounded-xl",
                            isPending
                              ? "bg-amber-100 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400"
                              : "bg-brand-50 text-brand-500 dark:bg-brand-500/10 dark:text-brand-400"
                          )}
                        >
                          <ShoppingBag className="h-5 w-5" />
                        </div>
                        <div className="min-w-0">
                          <div className="flex items-center gap-2 flex-wrap">
                            <span className="font-mono text-xs font-black text-gray-900 dark:text-white">
                              #{order.order_number}
                            </span>
                            {getOrderStatusBadge(order.order_status)}
                            {getPaymentBadge(order.payment_status)}
                          </div>
                          <div className="text-xs text-gray-600 dark:text-gray-300 font-medium truncate mt-0.5">
                            <strong className="text-gray-900 dark:text-white">{order.customer_name}</strong>
                            <span className="text-gray-400 mx-1.5">•</span>
                            <span className="text-gray-500 dark:text-gray-400">{order.customer_phone}</span>
                            <span className="text-gray-400 mx-1.5">•</span>
                            <span className="text-gray-500 dark:text-gray-400">{order.items?.length || 1} item</span>
                          </div>
                        </div>
                      </div>

                      <div className="flex items-center justify-between sm:justify-end gap-3 shrink-0 pt-2 sm:pt-0 border-t sm:border-t-0 border-gray-100 dark:border-gray-800">
                        <div className="text-left sm:text-right">
                          <div className="font-mono text-sm sm:text-base font-black text-emerald-600 dark:text-emerald-400">
                            {formatRupiah(order.total_amount)}
                          </div>
                          <span className="text-[10px] text-gray-400 block">
                            {new Date(order.created_at).toLocaleString("id-ID", {
                              day: "2-digit",
                              month: "short",
                              year: "numeric",
                              hour: "2-digit",
                              minute: "2-digit",
                            })}
                          </span>
                        </div>
                        <ChevronDown
                          className={cn(
                            "h-4 w-4 text-gray-400 transition-transform duration-200",
                            isExpanded && "rotate-180 text-brand-500"
                          )}
                        />
                      </div>
                    </div>

                    {/* Expanded Body */}
                    {isExpanded && (
                      <div className="px-4 pb-4 sm:px-5 sm:pb-5 pt-1 border-t border-gray-100 dark:border-gray-800 space-y-3.5 text-xs bg-gray-50/50 dark:bg-white/[0.01]">
                        {/* Customer Info & Order Metadata */}
                        <div className="rounded-xl border border-gray-200 bg-white p-3.5 space-y-2 text-xs dark:border-gray-800 dark:bg-gray-900/50">
                          <div className="flex items-center justify-between py-1 border-b border-gray-100 dark:border-gray-800">
                            <span className="text-gray-500 flex items-center gap-1.5 dark:text-gray-400">
                              <Phone className="h-3.5 w-3.5 text-gray-400" /> WhatsApp
                            </span>
                            <a
                              href={`https://wa.me/${cleanPhone(order.customer_phone)}`}
                              target="_blank"
                              rel="noopener noreferrer"
                              className="font-bold text-emerald-600 dark:text-emerald-400 hover:underline flex items-center gap-1"
                            >
                              <span>{order.customer_phone}</span>
                              <ExternalLink className="h-2.5 w-2.5" />
                            </a>
                          </div>

                          <div className="flex items-center justify-between py-1 border-b border-gray-100 dark:border-gray-800">
                            <span className="text-gray-500 flex items-center gap-1.5 dark:text-gray-400">
                              <CreditCard className="h-3.5 w-3.5 text-gray-400" /> Pembayaran
                            </span>
                            <span className="font-semibold text-gray-900 dark:text-white uppercase">
                              {order.payment_method}
                            </span>
                          </div>

                          {order.payment_bank && (
                            <div className="flex items-center justify-between py-1 border-b border-gray-100 dark:border-gray-800">
                              <span className="text-gray-500 flex items-center gap-1.5 dark:text-gray-400">
                                <Building2 className="h-3.5 w-3.5 text-brand-500" /> Rekening Tujuan
                              </span>
                              <span className="font-mono text-brand-600 dark:text-brand-400 font-semibold">
                                {order.payment_bank}
                              </span>
                            </div>
                          )}

                          {order.tracking_number && (
                            <div className="flex items-center justify-between py-1 border-b border-gray-100 dark:border-gray-800">
                              <span className="text-gray-500 flex items-center gap-1.5 dark:text-gray-400">
                                <Truck className="h-3.5 w-3.5 text-purple-500" /> No. Resi Pengiriman
                              </span>
                              <span className="font-mono text-purple-600 dark:text-purple-400 font-bold">
                                {order.tracking_number}
                              </span>
                            </div>
                          )}

                          {order.shipping_address && (
                            <div className="flex items-start justify-between py-1 border-b border-gray-100 dark:border-gray-800">
                              <span className="text-gray-500 flex items-center gap-1.5 shrink-0 dark:text-gray-400">
                                <MapPin className="h-3.5 w-3.5 text-gray-400" /> Alamat
                              </span>
                              <span className="text-right text-[11px] text-gray-700 dark:text-gray-300 ml-4">
                                {order.shipping_address}
                              </span>
                            </div>
                          )}

                          {order.customer_notes && (
                            <div className="flex items-start justify-between py-1 border-b border-gray-100 dark:border-gray-800">
                              <span className="text-gray-500 flex items-center gap-1.5 shrink-0 dark:text-gray-400">
                                <FileText className="h-3.5 w-3.5 text-gray-400" /> Catatan Pembeli
                              </span>
                              <span className="text-right text-[11px] text-gray-600 dark:text-gray-400 italic ml-4">
                                "{order.customer_notes}"
                              </span>
                            </div>
                          )}

                          {order.admin_notes && (
                            <div className="flex items-start justify-between py-1">
                              <span className="text-amber-600 dark:text-amber-400 flex items-center gap-1.5 shrink-0">
                                <AlertTriangle className="h-3.5 w-3.5" /> Catatan Admin / MikroTik
                              </span>
                              <span className="text-right text-[11px] text-amber-600 dark:text-amber-400 ml-4 font-mono font-medium">
                                {order.admin_notes}
                              </span>
                            </div>
                          )}
                        </div>

                        {/* Items Breakdown */}
                        <div className="rounded-xl border border-gray-200 bg-white p-3.5 space-y-1.5 dark:border-gray-800 dark:bg-gray-900/50">
                          <span className="text-[10px] text-gray-500 dark:text-gray-400 uppercase font-bold tracking-wider block">
                            Item Pesanan ({order.items.length})
                          </span>
                          <div className="divide-y divide-gray-100 dark:divide-gray-800 text-xs">
                            {order.items.map((it) => (
                              <div key={it.id} className="py-1.5 flex items-center justify-between">
                                <span className="text-gray-800 dark:text-gray-200">
                                  <strong className="text-brand-600 dark:text-brand-400 font-mono">{it.quantity}x</strong>{" "}
                                  {it.product_name}
                                </span>
                                <span className="font-mono text-gray-900 dark:text-white font-semibold">
                                  {formatRupiah(it.subtotal)}
                                </span>
                              </div>
                            ))}
                          </div>
                        </div>

                        {/* Voucher Hotspot Codes Box */}
                        {isVoucher && (
                          <div className="rounded-xl border border-blue-200 bg-blue-50/50 p-3.5 space-y-2 dark:border-blue-900/30 dark:bg-blue-950/20">
                            <div className="flex items-center justify-between text-xs">
                              <span className="text-[10px] text-brand-600 dark:text-brand-400 font-bold uppercase tracking-wider flex items-center gap-1.5">
                                <KeyRound className="h-3.5 w-3.5" />
                                <span>Akun Hotspot ({voucherList.length} Akun Terdaftar)</span>
                              </span>
                              <span
                                className={cn(
                                  "text-[10px] font-mono px-2 py-0.5 rounded-md font-bold shadow-xs",
                                  order.voucher_created_in_mikrotik
                                    ? "bg-emerald-600 text-white"
                                    : "bg-amber-600 text-white"
                                )}
                              >
                                {order.voucher_created_in_mikrotik ? "MikroTik: Aktif" : "MikroTik: Belum Dibuat"}
                              </span>
                            </div>

                            <div className="space-y-2 max-h-56 overflow-y-auto pr-1">
                              {voucherList.map((vch, vIdx) => {
                                const uKey = `card_u_${order.id}_${vIdx}`
                                const pKey = `card_p_${order.id}_${vIdx}`
                                return (
                                  <div
                                    key={vIdx}
                                    className="rounded-xl border border-gray-200 bg-white p-2.5 space-y-1.5 font-mono text-xs dark:border-gray-800 dark:bg-gray-900"
                                  >
                                    <div className="flex items-center justify-between text-[10px] text-gray-500 dark:text-gray-400 border-b border-gray-100 dark:border-gray-800 pb-1">
                                      <span className="font-bold text-brand-600 dark:text-brand-400">
                                        #{vIdx + 1} {vch.package_name || "Voucher WiFi"}
                                      </span>
                                      <span className="bg-gray-100 dark:bg-gray-800 px-1.5 py-0.5 rounded text-[10px]">
                                        Profil: {vch.profile || "default"}
                                      </span>
                                    </div>
                                    <div className="grid grid-cols-2 gap-2 pt-0.5">
                                      <div className="flex items-center justify-between bg-gray-50 dark:bg-gray-800/50 px-2 py-1 rounded border border-gray-200 dark:border-gray-700">
                                        <span className="text-gray-900 dark:text-white font-bold truncate text-[11px]">
                                          {vch.username}
                                        </span>
                                        <button
                                          type="button"
                                          onClick={(e) => copyToClipboard(vch.username, uKey, e)}
                                          className="text-gray-400 hover:text-brand-500 ml-1 p-0.5"
                                          title="Salin Username"
                                        >
                                          {copiedKey === uKey ? (
                                            <Check className="h-3 w-3 text-emerald-500" />
                                          ) : (
                                            <Copy className="h-3 w-3" />
                                          )}
                                        </button>
                                      </div>
                                      <div className="flex items-center justify-between bg-gray-50 dark:bg-gray-800/50 px-2 py-1 rounded border border-gray-200 dark:border-gray-700">
                                        <span className="text-amber-600 dark:text-amber-400 font-bold truncate text-[11px]">
                                          {vch.password}
                                        </span>
                                        <button
                                          type="button"
                                          onClick={(e) => copyToClipboard(vch.password || "", pKey, e)}
                                          className="text-gray-400 hover:text-brand-500 ml-1 p-0.5"
                                          title="Salin Password"
                                        >
                                          {copiedKey === pKey ? (
                                            <Check className="h-3 w-3 text-emerald-500" />
                                          ) : (
                                            <Copy className="h-3 w-3" />
                                          )}
                                        </button>
                                      </div>
                                    </div>
                                  </div>
                                )
                              })}
                            </div>
                          </div>
                        )}

                        {/* Bottom Actions Toolbar */}
                        <div className="pt-3 border-t border-gray-100 dark:border-gray-800 flex items-center justify-between gap-2">
                          <div className="text-[11px] text-gray-500 dark:text-gray-400">
                            Metode: <strong className="text-gray-900 dark:text-white uppercase font-mono">{order.payment_method}</strong>
                          </div>

                          <button
                            type="button"
                            onClick={(e) => openDetail(order, e)}
                            className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200 font-bold text-xs transition cursor-pointer"
                          >
                            Kelola
                          </button>
                        </div>
                      </div>
                    )}
                  </div>
                )
              })}
            </div>
          )}
        </div>
      </div>

      {/* Modal Detail & Kredensial */}
      <Modal
        isOpen={isDetailOpen && Boolean(selectedOrder)}
        onClose={() => setIsDetailOpen(false)}
        title={selectedOrder ? `Rincian Pesanan #${selectedOrder.order_number}` : "Rincian Pesanan"}
        description="Informasi lengkap pembeli, item pesanan, dan akun voucher hotspot"
        maxWidth="lg"
      >
        {selectedOrder && (
          <div className="space-y-3.5 text-xs max-h-[92vh] overflow-y-auto custom-scrollbar pr-1">
            {/* Customer Info */}
            <div className="p-3.5 rounded-xl border border-gray-200 bg-gray-50/50 dark:border-gray-800 dark:bg-gray-900/50 space-y-2">
              <span className="text-[10px] text-gray-500 dark:text-gray-400 uppercase font-bold tracking-wider block">
                Data Pelanggan
              </span>
              <div className="grid grid-cols-2 gap-2">
                <div>
                  <span className="text-gray-500 dark:text-gray-400 block text-[10px]">Nama Pembeli</span>
                  <span className="font-bold text-gray-900 dark:text-white text-sm">{selectedOrder.customer_name}</span>
                </div>
                <div>
                  <span className="text-gray-500 dark:text-gray-400 block text-[10px]">WhatsApp</span>
                  <a
                    href={`https://wa.me/${cleanPhone(selectedOrder.customer_phone)}?text=${encodeURIComponent(
                      buildOrderWhatsAppMessage(selectedOrder)
                    )}`}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="font-bold text-emerald-600 dark:text-emerald-400 hover:underline flex items-center gap-1"
                  >
                    <Phone className="w-3 h-3" />
                    <span>{selectedOrder.customer_phone}</span>
                  </a>
                </div>
              </div>
              {selectedOrder.shipping_address && (
                <div>
                  <span className="text-gray-500 dark:text-gray-400 block text-[10px]">Alamat Pengiriman</span>
                  <span className="text-gray-700 dark:text-gray-300">{selectedOrder.shipping_address}</span>
                </div>
              )}
              {selectedOrder.customer_notes && (
                <div>
                  <span className="text-gray-500 dark:text-gray-400 block text-[10px]">Catatan Pembeli</span>
                  <span className="text-gray-600 dark:text-gray-400 italic">"{selectedOrder.customer_notes}"</span>
                </div>
              )}
            </div>

            {/* Voucher Credentials List */}
            {parseVouchers(selectedOrder).length > 0 && (
              <div className="p-3.5 rounded-xl border border-blue-200 bg-blue-50/50 dark:border-blue-900/30 dark:bg-blue-950/20 space-y-2">
                <div className="flex items-center justify-between">
                  <span className="text-[10px] text-brand-600 dark:text-brand-400 uppercase font-bold tracking-wider flex items-center gap-1.5">
                    <KeyRound className="h-3.5 w-3.5" />
                    <span>Kredensial Voucher ({parseVouchers(selectedOrder).length} Akun)</span>
                  </span>
                  <span
                    className={cn(
                      "text-[10px] font-mono px-2 py-0.5 rounded font-bold shadow-xs",
                      selectedOrder.voucher_created_in_mikrotik
                        ? "bg-emerald-600 text-white"
                        : "bg-amber-600 text-white"
                    )}
                  >
                    {selectedOrder.voucher_created_in_mikrotik ? "MikroTik: Aktif" : "MikroTik: Belum Dibuat"}
                  </span>
                </div>

                <div className="space-y-2 max-h-56 overflow-y-auto pr-1">
                  {parseVouchers(selectedOrder).map((vch, idx) => (
                    <div
                      key={idx}
                      className="p-2.5 rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 space-y-1.5 font-mono"
                    >
                      <div className="flex items-center justify-between text-[10px] text-gray-500 dark:text-gray-400">
                        <span className="text-brand-600 dark:text-brand-400 font-bold">
                          #{idx + 1} {vch.package_name || "Voucher WiFi"}
                        </span>
                        <span className="bg-gray-100 dark:bg-gray-800 px-1.5 py-0.5 rounded text-[10px]">
                          Profil: {vch.profile || "default"}
                        </span>
                      </div>

                      <div className="grid grid-cols-2 gap-2 text-xs">
                        <div>
                          <span className="text-[10px] text-gray-500 dark:text-gray-400 block font-sans">Username</span>
                          <div className="p-1.5 rounded bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 flex items-center justify-between mt-0.5">
                            <span className="text-gray-900 dark:text-white font-bold truncate">{vch.username}</span>
                            <button
                              type="button"
                              onClick={(e) => copyToClipboard(vch.username, `modal_u_${idx}`, e)}
                              className="text-gray-400 hover:text-brand-500 ml-1 p-0.5"
                              title="Salin Username"
                            >
                              {copiedKey === `modal_u_${idx}` ? (
                                <Check className="h-3.5 w-3.5 text-emerald-500" />
                              ) : (
                                <Copy className="h-3.5 w-3.5" />
                              )}
                            </button>
                          </div>
                        </div>

                        <div>
                          <span className="text-[10px] text-gray-500 dark:text-gray-400 block font-sans">Password</span>
                          <div className="p-1.5 rounded bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 flex items-center justify-between mt-0.5">
                            <span className="text-amber-600 dark:text-amber-400 font-bold truncate">{vch.password}</span>
                            <button
                              type="button"
                              onClick={(e) => copyToClipboard(vch.password || "", `modal_p_${idx}`, e)}
                              className="text-gray-400 hover:text-brand-500 ml-1 p-0.5"
                              title="Salin Password"
                            >
                              {copiedKey === `modal_p_${idx}` ? (
                                <Check className="h-3.5 w-3.5 text-emerald-500" />
                              ) : (
                                <Copy className="h-3.5 w-3.5" />
                              )}
                            </button>
                          </div>
                        </div>
                      </div>
                    </div>
                  ))}
                </div>
              </div>
            )}

            {/* Items Breakdown */}
            <div className="p-3.5 rounded-xl border border-gray-200 bg-gray-50/50 dark:border-gray-800 dark:bg-gray-900/50 space-y-2">
              <span className="text-[10px] text-gray-500 dark:text-gray-400 uppercase font-bold tracking-wider block">
                Item Pesanan
              </span>
              <div className="divide-y divide-gray-200 dark:divide-gray-800">
                {selectedOrder.items.map((it) => (
                  <div key={it.id} className="py-1.5 flex items-center justify-between">
                    <div>
                      <span className="font-semibold text-gray-900 dark:text-white block">{it.product_name}</span>
                      <span className="text-gray-500 dark:text-gray-400 text-[11px]">
                        {it.quantity}x @ {formatRupiah(it.price)}
                      </span>
                    </div>
                    <span className="font-mono font-bold text-gray-900 dark:text-white">{formatRupiah(it.subtotal)}</span>
                  </div>
                ))}
              </div>
            </div>

            {/* Payment Details */}
            <div className="p-3.5 rounded-xl border border-gray-200 bg-gray-50/50 dark:border-gray-800 dark:bg-gray-900/50 space-y-1">
              <span className="text-[10px] text-gray-500 dark:text-gray-400 uppercase font-bold tracking-wider block">
                Metode Pembayaran
              </span>
              <div className="flex items-center justify-between">
                <span className="text-gray-800 dark:text-gray-200 font-semibold uppercase">
                  {selectedOrder.payment_method}
                </span>
                <span className="font-mono font-bold text-emerald-600 dark:text-emerald-400 text-sm">
                  {formatRupiah(selectedOrder.total_amount)}
                </span>
              </div>
              {selectedOrder.payment_bank && (
                <p className="text-[11px] text-brand-600 dark:text-brand-400 font-mono pt-1">
                  {selectedOrder.payment_bank}
                </p>
              )}
            </div>

            {/* Action Buttons in Modal */}
            <div className="space-y-2 pt-3 border-t border-gray-200 dark:border-gray-800">
              <span className="text-[10px] text-gray-500 dark:text-gray-400 uppercase font-bold tracking-wider block">
                Aksi Cepat Pesanan
              </span>

              {selectedOrder.order_status === "pending" && (
                <div className="grid grid-cols-2 gap-2">
                  <button
                    type="button"
                    onClick={() => handleApprove(selectedOrder.id)}
                    className="inline-flex items-center justify-center gap-1.5 px-3.5 py-2.5 rounded-xl bg-emerald-500 hover:bg-emerald-600 active:scale-95 text-xs font-bold text-white shadow-xs transition"
                  >
                    <CheckCircle2 className="w-4 h-4" />
                    <span>ACC & Buat Akun</span>
                  </button>

                  <button
                    type="button"
                    onClick={() => handleReject(selectedOrder.id)}
                    className="inline-flex items-center justify-center gap-1.5 px-3.5 py-2.5 rounded-xl bg-rose-500 hover:bg-rose-600 active:scale-95 text-xs font-bold text-white shadow-xs transition"
                  >
                    <XCircle className="w-4 h-4" />
                    <span>Tolak Pesanan</span>
                  </button>
                </div>
              )}

              <div className="grid grid-cols-2 sm:grid-cols-3 gap-2">
                {selectedOrder.customer_phone && (
                  <a
                    href={`https://wa.me/${cleanPhone(selectedOrder.customer_phone)}?text=${encodeURIComponent(
                      buildOrderWhatsAppMessage(selectedOrder)
                    )}`}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:hover:bg-gray-800 text-xs font-semibold text-gray-700 dark:text-gray-200 transition shadow-2xs"
                  >
                    <MessageCircle className="h-4 w-4 text-emerald-500" />
                    <span>Kirim WhatsApp</span>
                  </a>
                )}

                <button
                  type="button"
                  onClick={() => openInvoice(selectedOrder)}
                  className="inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:hover:bg-gray-800 text-xs font-semibold text-gray-700 dark:text-gray-200 transition shadow-2xs"
                >
                  <Printer className="h-4 w-4 text-brand-500" />
                  <span>Cetak Faktur</span>
                </button>

                <button
                  type="button"
                  onClick={() => openEditStatus(selectedOrder)}
                  className="inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:hover:bg-gray-800 text-xs font-semibold text-gray-700 dark:text-gray-200 transition shadow-2xs"
                >
                  <Pencil className="h-4 w-4 text-amber-500" />
                  <span>Ubah Status</span>
                </button>
              </div>

              <div className="flex items-center justify-between pt-2">
                <button
                  type="button"
                  onClick={() => handleDeleteOrder(selectedOrder)}
                  className="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl border border-rose-200 bg-rose-50/50 hover:bg-rose-100 text-rose-600 dark:border-rose-900/40 dark:bg-rose-950/20 dark:hover:bg-rose-950/40 dark:text-rose-400 text-xs font-semibold transition"
                >
                  <Trash2 className="h-3.5 w-3.5" />
                  <span>Hapus Pesanan</span>
                </button>

                <button
                  type="button"
                  onClick={() => setIsDetailOpen(false)}
                  className="inline-flex items-center justify-center px-4 py-2 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:hover:bg-gray-800 text-xs font-semibold text-gray-700 dark:text-gray-200 transition shadow-2xs"
                >
                  Tutup
                </button>
              </div>
            </div>
          </div>
        )}
      </Modal>

      {/* Modal Ubah Status */}
      <Modal
        isOpen={isEditStatusOpen && Boolean(selectedOrder)}
        onClose={() => setIsEditStatusOpen(false)}
        title={selectedOrder ? `Ubah Status Pesanan #${selectedOrder.order_number}` : "Ubah Status"}
        description="Perbarui status pesanan, verifikasi pembayaran, atau nomor resi kurir"
        maxWidth="md"
      >
        <form onSubmit={handleUpdateStatus} className="space-y-4 text-xs max-h-[92vh] overflow-y-auto custom-scrollbar pr-1">
          <div>
            <label className="mb-1 block font-semibold text-gray-700 dark:text-gray-300">Status Pesanan *</label>
            <select
              value={statusForm.data.order_status}
              onChange={(e) => statusForm.setData("order_status", e.target.value as any)}
              className="h-10 w-full rounded-xl border border-gray-300 bg-white px-3 text-xs text-gray-900 focus:border-brand-500 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white"
            >
              <option value="pending">Menunggu ACC (Pending)</option>
              <option value="processing">Sedang Diproses (Processing)</option>
              <option value="shipped">Sedang Dikirim (Shipped)</option>
              <option value="completed">Selesai / Lunas (Completed)</option>
              <option value="cancelled">Dibatalkan (Cancelled)</option>
            </select>
          </div>

          <div>
            <label className="mb-1 block font-semibold text-gray-700 dark:text-gray-300">Status Pembayaran *</label>
            <select
              value={statusForm.data.payment_status}
              onChange={(e) => statusForm.setData("payment_status", e.target.value as any)}
              className="h-10 w-full rounded-xl border border-gray-300 bg-white px-3 text-xs text-gray-900 focus:border-brand-500 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white"
            >
              <option value="unpaid">Belum Bayar (Unpaid)</option>
              <option value="paid">Sudah Bayar (Paid)</option>
              <option value="verified">Terverifikasi (Verified)</option>
              <option value="rejected">Ditolak (Rejected)</option>
            </select>
          </div>

          <div>
            <label className="mb-1 block font-semibold text-gray-700 dark:text-gray-300">
              No. Resi Pengiriman (Opsional)
            </label>
            <input
              type="text"
              value={statusForm.data.tracking_number}
              onChange={(e) => statusForm.setData("tracking_number", e.target.value)}
              placeholder="cth: JNE123456789 / ID Pengiriman"
              className="h-10 w-full rounded-xl border border-gray-300 bg-transparent px-3 text-xs font-mono text-gray-900 placeholder:text-gray-400 focus:border-brand-500 focus:outline-hidden dark:border-gray-700 dark:text-white dark:placeholder:text-gray-500"
            />
          </div>

          <div>
            <label className="mb-1 block font-semibold text-gray-700 dark:text-gray-300">Catatan Admin</label>
            <textarea
              rows={3}
              value={statusForm.data.admin_notes}
              onChange={(e) => statusForm.setData("admin_notes", e.target.value)}
              placeholder="Catatan internal atau alasan pembatalan..."
              className="w-full rounded-xl border border-gray-300 bg-transparent p-3 text-xs text-gray-900 placeholder:text-gray-400 focus:border-brand-500 focus:outline-hidden dark:border-gray-700 dark:text-white dark:placeholder:text-gray-500"
            />
          </div>

          <div className="flex items-center justify-end gap-2 pt-3 border-t border-gray-200 dark:border-gray-800">
            <button
              type="button"
              onClick={() => setIsEditStatusOpen(false)}
              className="h-10 rounded-xl border border-gray-300 bg-white px-4 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
            >
              Batal
            </button>
            <button
              type="submit"
              disabled={statusForm.processing}
              className="h-10 rounded-xl bg-brand-500 px-4 text-xs font-bold text-white shadow-xs hover:bg-brand-600 disabled:opacity-50 transition"
            >
              Simpan Perubahan
            </button>
          </div>
        </form>
      </Modal>

      {/* Modal Invoice / Struk */}
      <Modal
        isOpen={isInvoiceOpen && Boolean(selectedOrder)}
        onClose={() => setIsInvoiceOpen(false)}
        title={selectedOrder ? `Faktur Pembelian #${selectedOrder.order_number}` : "Faktur Pembelian"}
        description="Bukti struk pembelian toko subdomain"
        maxWidth="2xl"
      >
        {selectedOrder && (
          <div className="space-y-4 text-xs print:text-black max-h-[92vh] overflow-y-auto custom-scrollbar pr-1">
            <div className="flex items-start justify-between border-b border-gray-200 pb-4 dark:border-gray-800">
              <div>
                <h2 className="text-base font-black text-gray-900 dark:text-white">NODERA WiFi &amp; Store</h2>
                <p className="text-gray-500 dark:text-gray-400 text-[11px]">Subdomain Store Order</p>
              </div>
              <div className="text-right">
                <span className="font-mono text-xs font-bold text-brand-600 dark:text-brand-400 block">
                  #{selectedOrder.order_number}
                </span>
                <span className="text-[11px] text-gray-500 dark:text-gray-400">
                  {new Date(selectedOrder.created_at).toLocaleDateString("id-ID")}
                </span>
              </div>
            </div>

            <div className="grid grid-cols-2 gap-3 p-3 rounded-xl border border-gray-200 bg-gray-50/50 dark:border-gray-800 dark:bg-gray-900/50">
              <div>
                <span className="text-[10px] text-gray-500 dark:text-gray-400 uppercase font-bold block">Penerima</span>
                <strong className="text-gray-900 dark:text-white text-xs block mt-0.5">{selectedOrder.customer_name}</strong>
                <span className="text-gray-600 dark:text-gray-300">{selectedOrder.customer_phone}</span>
              </div>
              <div>
                <span className="text-[10px] text-gray-500 dark:text-gray-400 uppercase font-bold block">
                  Alamat / Layanan
                </span>
                <span className="text-gray-600 dark:text-gray-300 block mt-0.5">
                  {selectedOrder.shipping_address || "Layanan Voucher Online"}
                </span>
              </div>
            </div>

            {parseVouchers(selectedOrder).length > 0 && (
              <div className="rounded-xl border border-blue-200 bg-blue-50/50 p-3 space-y-2 dark:border-blue-900/30 dark:bg-blue-950/20">
                <span className="text-[10px] font-bold text-brand-600 dark:text-brand-400 uppercase tracking-wider block">
                  Daftar Akun Voucher Hotspot ({parseVouchers(selectedOrder).length} Akun)
                </span>
                <div className="space-y-1.5">
                  {parseVouchers(selectedOrder).map((v, i) => (
                    <div
                      key={i}
                      className="flex items-center justify-between p-2 rounded-lg border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 font-mono text-xs"
                    >
                      <span className="text-brand-600 dark:text-brand-400 font-bold">
                        #{i + 1} {v.package_name || "Voucher WiFi"}
                      </span>
                      <div className="flex items-center gap-3">
                        <span>
                          User: <strong className="text-gray-900 dark:text-white">{v.username}</strong>
                        </span>
                        <span>
                          Pass: <strong className="text-amber-600 dark:text-amber-400">{v.password}</strong>
                        </span>
                      </div>
                    </div>
                  ))}
                </div>
              </div>
            )}

            <div className="rounded-xl border border-gray-200 bg-white overflow-hidden dark:border-gray-800 dark:bg-gray-900">
              <table className="w-full text-xs min-w-[700px] border-collapse">
                <thead className="border-b border-gray-200 bg-gray-50/75 dark:border-gray-800 dark:bg-gray-800/50 text-gray-600 dark:text-gray-300">
                  <tr>
                    <th className="p-2.5 text-left font-bold">Item Produk</th>
                    <th className="p-2.5 text-center font-bold">Qty</th>
                    <th className="p-2.5 text-right font-bold">Harga</th>
                    <th className="p-2.5 text-right font-bold">Subtotal</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-gray-200 dark:divide-gray-800">
                  {selectedOrder.items.map((it) => (
                    <tr key={it.id}>
                      <td className="p-2.5 font-medium text-gray-900 dark:text-white">{it.product_name}</td>
                      <td className="p-2.5 text-center font-mono">{it.quantity}</td>
                      <td className="p-2.5 text-right font-mono text-gray-600 dark:text-gray-400">
                        {formatRupiah(it.price)}
                      </td>
                      <td className="p-2.5 text-right font-mono font-bold text-emerald-600 dark:text-emerald-400">
                        {formatRupiah(it.subtotal)}
                      </td>
                    </tr>
                  ))}
                </tbody>
                <tfoot className="border-t border-gray-200 bg-gray-50/75 dark:border-gray-800 dark:bg-gray-800/50">
                  <tr>
                    <td colSpan={3} className="p-2.5 font-bold text-right text-gray-900 dark:text-white">
                      Total Tagihan
                    </td>
                    <td className="p-2.5 font-bold text-right font-mono text-sm text-emerald-600 dark:text-emerald-400">
                      {formatRupiah(selectedOrder.total_amount)}
                    </td>
                  </tr>
                </tfoot>
              </table>
            </div>

            <div className="flex items-center justify-end gap-2 pt-3 border-t border-gray-200 dark:border-gray-800">
              <button
                type="button"
                onClick={() => setIsInvoiceOpen(false)}
                className="h-9 px-4 rounded-xl border border-gray-300 bg-white text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
              >
                Tutup
              </button>
              <button
                type="button"
                onClick={() => window.print()}
                className="h-9 inline-flex items-center gap-1.5 px-4 rounded-xl bg-brand-500 text-xs font-bold text-white hover:bg-brand-600 shadow-xs"
              >
                <Printer className="w-3.5 h-3.5" />
                <span>Cetak / PDF</span>
              </button>
            </div>
          </div>
        )}
      </Modal>
    </AppLayout>
  )
}
