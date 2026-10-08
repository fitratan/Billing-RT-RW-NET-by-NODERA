import React, { useState, useEffect, useRef } from "react"
import { AppLayout } from "@/components/layout/app-layout"
import {
  ShoppingBag,
  Plus,
  Pencil,
  Trash2,
  Search,
  Package as PackageIcon,
  Wifi,
  CheckCircle2,
  Boxes,
  X,
} from "lucide-react"
import { PageProps } from "@/types"
import { cn, formatRupiah } from "@/lib/utils"
import { adminSidebarItems, adminNavItems, adminBrand } from "@/lib/admin-nav"
import { useForm, router, Link } from "@inertiajs/react"
import MetricCard from "@/components/tailadmin/MetricCard"
import Modal from "@/components/tailadmin/Modal"
import { EmptyState } from "@/components/ui/empty-state"
import Switch from "@/components/tailadmin/Switch"
import { ViewModeSwitcher, type ViewMode } from "@/components/tailadmin/ui/view-mode-switcher"

interface ProductCategory {
  id: number
  name: string
  slug: string
  icon?: string | null
  description?: string | null
  sort_order: number
  is_active: boolean
  products_count?: number
}

interface VoucherPackage {
  id: number
  name: string
  price: number
  profile?: string
}

interface Product {
  id: number
  category_id?: number | null
  category?: ProductCategory | null
  voucher_package_id?: number | null
  voucher_package?: VoucherPackage | null
  name: string
  slug: string
  sku?: string | null
  product_type: "general" | "voucher"
  price: number
  original_price?: number | null
  stock: number
  badge?: string | null
  image?: string | null
  short_description?: string | null
  description?: string | null
  specifications?: string | null
  is_featured: boolean
  is_active: boolean
  sort_order: number
}

export default function AdminShopProductsPage({
  products,
  categories,
  voucherPackages = [],
  filters,
}: PageProps<{
  products: {
    data: Product[]
    current_page: number
    last_page: number
    total: number
  }
  categories: ProductCategory[]
  voucherPackages: VoucherPackage[]
  filters: { search?: string; category_id?: string }
}>) {
  const [activeTab, setActiveTab] = useState<"products" | "categories">("products")
  const [viewMode, setViewMode] = useState<ViewMode>("grid")
  const [search, setSearch] = useState(filters.search || "")
  const [selectedCategory, setSelectedCategory] = useState(filters.category_id || "")
  const isFirstRender = useRef(true)

  useEffect(() => {
    if (isFirstRender.current) {
      isFirstRender.current = false
      return
    }
    const timeout = setTimeout(() => {
      router.get(
        "/admin/shop/products",
        {
          search: search || undefined,
          category_id: selectedCategory || undefined,
        },
        { preserveState: true, preserveScroll: true, replace: true }
      )
    }, 300)
    return () => clearTimeout(timeout)
  }, [search, selectedCategory])

  // Manage Modals
  const [manageProduct, setManageProduct] = useState<Product | null>(null)
  const [manageCategory, setManageCategory] = useState<ProductCategory | null>(null)

  // Edit / Add Form Modals
  const [isProductModalOpen, setIsProductModalOpen] = useState(false)
  const [editingProduct, setEditingProduct] = useState<Product | null>(null)
  const [isCategoryModalOpen, setIsCategoryModalOpen] = useState(false)
  const [editingCategory, setEditingCategory] = useState<ProductCategory | null>(null)
  const [isDeleteModalOpen, setIsDeleteModalOpen] = useState(false)
  const [itemToDelete, setItemToDelete] = useState<{ id: number; type: "product" | "category"; name: string } | null>(null)

  // Product Form
  const productForm = useForm({
    category_id: "" as string | number,
    voucher_package_id: "" as string | number,
    product_type: "general" as "general" | "voucher",
    name: "",
    sku: "",
    price: "",
    original_price: "",
    stock: "50",
    badge: "",
    short_description: "",
    description: "",
    is_featured: false,
    is_active: true,
    sort_order: "0",
    image: null as File | null,
  })

  // Category Form
  const categoryForm = useForm({
    name: "",
    description: "",
    icon: "fa-cube",
    sort_order: "0",
    is_active: true,
  })

  const fileInputRef = useRef<HTMLInputElement>(null)
  const [previewImage, setPreviewImage] = useState<string | null>(null)

  const openAddProduct = () => {
    setEditingProduct(null)
    productForm.reset()
    setPreviewImage(null)
    setIsProductModalOpen(true)
  }

  const openEditProduct = (p: Product) => {
    setEditingProduct(p)
    productForm.setData({
      category_id: p.category_id || "",
      voucher_package_id: p.voucher_package_id || "",
      product_type: p.product_type || "general",
      name: p.name,
      sku: p.sku || "",
      price: p.price.toString(),
      original_price: p.original_price?.toString() || "",
      stock: p.stock.toString(),
      badge: p.badge || "",
      short_description: p.short_description || "",
      description: p.description || "",
      is_featured: p.is_featured,
      is_active: p.is_active,
      sort_order: p.sort_order.toString(),
      image: null,
    })
    setPreviewImage(p.image ? (p.image.startsWith("http") ? p.image : `/storage/${p.image}`) : null)
    setManageProduct(null)
    setIsProductModalOpen(true)
  }

  const handleProductSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    if (editingProduct) {
      productForm.post(`/admin/shop/products/${editingProduct.id}/update`, {
        onSuccess: () => setIsProductModalOpen(false),
      })
    } else {
      productForm.post("/admin/shop/products", {
        onSuccess: () => setIsProductModalOpen(false),
      })
    }
  }

  const handleCategorySubmit = (e: React.FormEvent) => {
    e.preventDefault()
    if (editingCategory) {
      categoryForm.post(`/admin/shop/categories/${editingCategory.id}/update`, {
        onSuccess: () => setIsCategoryModalOpen(false),
      })
    } else {
      categoryForm.post("/admin/shop/categories", {
        onSuccess: () => setIsCategoryModalOpen(false),
      })
    }
  }

  const confirmDelete = () => {
    if (!itemToDelete) return
    if (itemToDelete.type === "product") {
      router.delete(`/admin/shop/products/${itemToDelete.id}`, {
        onSuccess: () => {
          setIsDeleteModalOpen(false)
          setManageProduct(null)
        },
      })
    } else {
      router.delete(`/admin/shop/categories/${itemToDelete.id}`, {
        onSuccess: () => {
          setIsDeleteModalOpen(false)
          setManageCategory(null)
        },
      })
    }
  }

  const activeProductsCount = products.data.filter((p) => p.is_active).length

  return (
    <AppLayout
      title="Katalog & Produk Toko"
      brand={adminBrand}
      sidebarItems={adminSidebarItems}
      navItems={adminNavItems}
    >
      <div className="space-y-4 sm:space-y-6">
        {/* Top Row: MetricCards */}
        <div className="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
          <MetricCard
            title="Total Produk"
            value={products.total}
            sub="Seluruh katalog terdaftar"
            icon={<ShoppingBag className="h-5 w-5 sm:h-6 sm:w-6 text-brand-500" />}
            iconBgColor="bg-brand-50 dark:bg-brand-500/10"
            iconColor="text-brand-500 dark:text-brand-400"
          />
          <MetricCard
            title="Produk Aktif"
            value={activeProductsCount}
            sub="Tayang di toko online"
            icon={<CheckCircle2 className="h-5 w-5 sm:h-6 sm:w-6 text-emerald-500" />}
            iconBgColor="bg-emerald-50 dark:bg-emerald-500/10"
            iconColor="text-emerald-500 dark:text-emerald-400"
          />
          <MetricCard
            title="Kategori Produk"
            value={categories.length}
            sub="Pengelompokan barang"
            icon={<Boxes className="h-5 w-5 sm:h-6 sm:w-6 text-purple-500" />}
            iconBgColor="bg-purple-50 dark:bg-purple-500/10"
            iconColor="text-purple-500 dark:text-purple-400"
          />
          <MetricCard
            title="Paket Hotspot"
            value={voucherPackages.length}
            sub="Voucher WiFi terintegrasi"
            icon={<Wifi className="h-5 w-5 sm:h-6 sm:w-6 text-amber-500" />}
            iconBgColor="bg-amber-50 dark:bg-amber-500/10"
            iconColor="text-amber-500 dark:text-amber-400"
          />
        </div>

        {/* Master Card with Toolbar & Content */}
        <div className="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xs dark:border-gray-800 dark:bg-white/[0.03]">
          {/* 1-Line Header Toolbar */}
          <div className="rounded-2xl border-b border-gray-200 bg-white p-3 sm:p-4 shadow-xs dark:border-gray-800 dark:bg-white/[0.03] flex flex-col gap-2.5 sm:gap-3 lg:flex-row lg:items-center lg:justify-between">
            {/* Tabs & Search */}
            <div className="flex flex-wrap items-center gap-2 sm:gap-3 flex-1 min-w-0">
              {/* Tab Switcher Pills */}
              <div className="flex items-center gap-1 rounded-xl border border-gray-200 bg-gray-50/80 p-1 dark:border-gray-700 dark:bg-gray-800/80 shrink-0">
                <button
                  type="button"
                  onClick={() => setActiveTab("products")}
                  className={cn(
                    "rounded-lg px-3 py-1.5 text-xs font-bold transition cursor-pointer",
                    activeTab === "products"
                      ? "bg-white text-gray-900 shadow-xs dark:bg-gray-900 dark:text-white"
                      : "text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white"
                  )}
                >
                  Daftar Produk ({products.total})
                </button>
                <button
                  type="button"
                  onClick={() => setActiveTab("categories")}
                  className={cn(
                    "rounded-lg px-3 py-1.5 text-xs font-bold transition cursor-pointer",
                    activeTab === "categories"
                      ? "bg-white text-gray-900 shadow-xs dark:bg-gray-900 dark:text-white"
                      : "text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white"
                  )}
                >
                  Kategori ({categories.length})
                </button>
              </div>

              {activeTab === "products" && (
                <>
                  <div className="relative flex-1 min-w-[180px] sm:min-w-[220px] w-full sm:w-auto">
                    <Search className="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                    <input
                      type="text"
                      value={search}
                      onChange={(e) => setSearch(e.target.value)}
                      placeholder="Cari nama produk atau SKU..."
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

                  <select
                    value={selectedCategory}
                    onChange={(e) => {
                      setSelectedCategory(e.target.value)
                      router.get("/admin/shop/products", { search, category_id: e.target.value })
                    }}
                    className="h-10 rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 px-3 text-xs font-semibold text-gray-700 dark:text-gray-200 cursor-pointer w-full sm:w-auto shrink-0 focus:outline-none focus:border-brand-500"
                  >
                    <option value="">Semua Kategori</option>
                    {categories.map((c) => (
                      <option key={c.id} value={c.id}>
                        {c.name}
                      </option>
                    ))}
                  </select>
                </>
              )}
            </div>

            {/* Actions */}
            <div className="flex items-center gap-2 shrink-0 w-full sm:w-auto justify-end flex-wrap sm:flex-nowrap">
              {activeTab === "products" && (
                <ViewModeSwitcher value={viewMode} onChange={setViewMode} />
              )}

              <Link
                href="/admin/shop/orders"
                className="h-10 inline-flex items-center justify-center gap-1.5 px-3.5 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:hover:bg-gray-800 text-xs font-semibold text-gray-700 dark:text-gray-200 transition shadow-2xs cursor-pointer shrink-0"
              >
                <ShoppingBag className="h-4 w-4 text-brand-500" />
                <span className="hidden sm:inline">Pesanan Toko</span>
              </Link>

              {activeTab === "products" ? (
                <button
                  type="button"
                  onClick={openAddProduct}
                  className="h-10 px-3.5 sm:px-4 rounded-xl bg-brand-500 hover:bg-brand-600 active:scale-95 text-xs font-bold text-white shadow-xs flex items-center justify-center gap-1.5 w-full sm:w-auto shrink-0 cursor-pointer transition"
                >
                  <Plus className="h-4 w-4" />
                  <span>Tambah Produk</span>
                </button>
              ) : (
                <button
                  type="button"
                  onClick={() => {
                    setEditingCategory(null)
                    categoryForm.reset()
                    setIsCategoryModalOpen(true)
                  }}
                  className="h-10 px-3.5 sm:px-4 rounded-xl bg-brand-500 hover:bg-brand-600 active:scale-95 text-xs font-bold text-white shadow-xs flex items-center justify-center gap-1.5 w-full sm:w-auto shrink-0 cursor-pointer transition"
                >
                  <Plus className="h-4 w-4" />
                  <span>Tambah Kategori</span>
                </button>
              )}
            </div>
          </div>

          {/* TAB 1: PRODUCTS LIST */}
          {activeTab === "products" && (
            <div className="p-4 sm:p-5">
              {products.data.length === 0 ? (
                <div className="p-8 sm:p-12 text-center">
                  <EmptyState
                    icon={<ShoppingBag className="h-8 w-8 text-brand-500" />}
                    title="Belum ada produk toko"
                    description="Tambahkan produk fisik, perangkat router, atau voucher hotspot untuk dijual di toko online Anda."
                  />
                </div>
              ) : viewMode === "table" ? (
                <div className="w-full overflow-x-auto custom-scrollbar">
                  <table className="w-full text-start text-xs min-w-[950px] border-collapse">
                    <thead className="border-b border-gray-200 bg-gray-50/80 font-bold text-gray-500 dark:border-gray-800 dark:bg-gray-900/50 dark:text-gray-400">
                      <tr>
                        <th className="py-3 px-4 text-start font-semibold">Foto</th>
                        <th className="py-3 px-4 text-start font-semibold">Nama Produk</th>
                        <th className="py-3 px-4 text-start font-semibold">Tipe</th>
                        <th className="py-3 px-4 text-start font-semibold">Kategori</th>
                        <th className="py-3 px-4 text-start font-semibold">Harga Jual</th>
                        <th className="py-3 px-4 text-center font-semibold">Stok</th>
                        <th className="py-3 px-4 text-center font-semibold">Status</th>
                        <th className="py-3 px-4 text-center font-semibold">Aksi</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-100 dark:divide-gray-800 font-medium">
                      {products.data.map((p) => (
                        <tr
                          key={p.id}
                          className="hover:bg-gray-50/50 dark:hover:bg-gray-800/30 transition-colors"
                        >
                          <td className="py-3 px-4">
                            <div className="h-12 w-12 rounded-xl overflow-hidden bg-gray-100 border border-gray-200 dark:border-gray-800 dark:bg-gray-800 flex items-center justify-center">
                              {p.image ? (
                                <img
                                  src={p.image.startsWith("http") ? p.image : `/storage/${p.image}`}
                                  alt={p.name}
                                  className="w-full h-full object-cover"
                                />
                              ) : p.product_type === "voucher" ? (
                                <Wifi className="w-5 h-5 text-brand-500/60" />
                              ) : (
                                <PackageIcon className="w-5 h-5 text-gray-400" />
                              )}
                            </div>
                          </td>
                          <td className="py-3 px-4">
                            <div className="space-y-0.5">
                              <div className="font-bold text-gray-900 dark:text-white line-clamp-1">{p.name}</div>
                              {p.sku && <div className="text-[11px] text-gray-400 font-mono">SKU: {p.sku}</div>}
                            </div>
                          </td>
                          <td className="py-3 px-4">
                            {p.product_type === "voucher" ? (
                              <span className="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-bold bg-brand-500 text-white whitespace-nowrap shadow-xs">
                                <Wifi className="w-3 h-3" /> Voucher
                              </span>
                            ) : (
                              <span className="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-bold bg-purple-600 text-white whitespace-nowrap shadow-xs">
                                <Boxes className="w-3 h-3" /> Fisik
                              </span>
                            )}
                          </td>
                          <td className="py-3 px-4 text-gray-600 dark:text-gray-300">
                            {p.category?.name || "-"}
                          </td>
                          <td className="py-3 px-4 font-mono font-bold text-emerald-600 dark:text-emerald-400">
                            {formatRupiah(p.price)}
                          </td>
                          <td className="py-3 px-4 text-center font-mono font-bold text-gray-900 dark:text-white">
                            {p.stock}
                          </td>
                          <td className="py-3 px-4 text-center">
                            <span
                              className={cn(
                                "inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-bold text-white shadow-xs whitespace-nowrap",
                                p.is_active ? "bg-emerald-500" : "bg-gray-500"
                              )}
                            >
                              {p.is_active ? "Aktif" : "Nonaktif"}
                            </span>
                          </td>
                          <td className="py-3 px-4 text-center">
                            <button
                              type="button"
                              onClick={() => setManageProduct(p)}
                              className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200 font-bold text-xs transition cursor-pointer"
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
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                  {products.data.map((p) => (
                    <div
                      key={p.id}
                      className="rounded-2xl border border-gray-200 bg-white p-4 flex flex-col justify-between space-y-3 hover:border-gray-300 dark:hover:border-gray-700 transition shadow-xs dark:border-gray-800 dark:bg-white/[0.02]"
                    >
                      <div>
                        {/* Image Preview Box */}
                        <div className="h-40 w-full rounded-xl overflow-hidden bg-gray-50/80 border border-gray-100 flex items-center justify-center relative mb-3 dark:border-gray-800 dark:bg-gray-900/50">
                          {p.image ? (
                            <img
                              src={p.image.startsWith("http") ? p.image : `/storage/${p.image}`}
                              alt={p.name}
                              className="w-full h-full object-cover"
                            />
                          ) : p.product_type === "voucher" ? (
                            <Wifi className="w-12 h-12 text-brand-500/40" />
                          ) : (
                            <PackageIcon className="w-12 h-12 text-gray-400" />
                          )}

                          {p.badge && (
                            <span className="absolute top-2.5 left-2.5 inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold bg-brand-500 text-white shadow-xs uppercase whitespace-nowrap">
                              {p.badge}
                            </span>
                          )}

                          <span
                            className={cn(
                              "absolute top-2.5 right-2.5 inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold uppercase tracking-wider text-white shadow-xs",
                              p.is_active ? "bg-emerald-500" : "bg-gray-500"
                            )}
                          >
                            {p.is_active ? "Aktif" : "Nonaktif"}
                          </span>
                        </div>

                        {/* Title & Info */}
                        <div className="space-y-1">
                          <div className="flex items-center gap-1.5 text-[10px] text-brand-600 dark:text-brand-400 font-bold uppercase">
                            {p.product_type === "voucher" ? (
                              <span className="flex items-center gap-1">
                                <Wifi className="w-3 h-3" /> Voucher Hotspot
                              </span>
                            ) : (
                              <span>{p.category?.name || "Umum"}</span>
                            )}
                          </div>
                          <h4 className="font-bold text-sm text-gray-900 dark:text-white line-clamp-1">{p.name}</h4>
                          <div className="text-base font-extrabold text-emerald-600 dark:text-emerald-400 font-mono">
                            {formatRupiah(p.price)}
                          </div>
                          {p.short_description && (
                            <p className="text-xs text-gray-500 dark:text-gray-400 line-clamp-2">{p.short_description}</p>
                          )}
                        </div>
                      </div>

                      {/* Card Footer with ONE KELOLA BUTTON */}
                      <div className="flex items-center justify-between pt-3 border-t border-gray-100 dark:border-gray-800">
                        <div className="text-[11px] text-gray-500 dark:text-gray-400">
                          Stok: <strong className="text-gray-900 dark:text-white font-mono">{p.stock}</strong>
                        </div>
                        <button
                          type="button"
                          onClick={() => setManageProduct(p)}
                          className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200 font-bold text-xs transition cursor-pointer"
                        >
                          Kelola
                        </button>
                      </div>
                    </div>
                  ))}
                </div>
              )}
            </div>
          )}

          {/* TAB 2: CATEGORIES LIST */}
          {activeTab === "categories" && (
            <div className="p-4 sm:p-5">
              <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                {categories.map((c) => (
                  <div
                    key={c.id}
                    className="rounded-2xl border border-gray-200 bg-white p-4 flex items-center justify-between shadow-xs dark:border-gray-800 dark:bg-white/[0.02]"
                  >
                    <div>
                      <h4 className="font-bold text-sm text-gray-900 dark:text-white">{c.name}</h4>
                      <p className="text-xs text-gray-500 dark:text-gray-400">{c.products_count || 0} Produk Terkait</p>
                    </div>
                    <button
                      type="button"
                      onClick={() => setManageCategory(c)}
                      className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200 font-bold text-xs transition cursor-pointer"
                    >
                      Kelola
                    </button>
                  </div>
                ))}
              </div>
            </div>
          )}
        </div>
      </div>

      {/* ── KELOLA PRODUCT POPUP MODAL ── */}
      <Modal
        isOpen={!!manageProduct}
        onClose={() => setManageProduct(null)}
        title="Kelola Produk"
        description="Detail informasi dan aksi cepat produk"
        maxWidth="md"
      >
        {manageProduct && (
          <div className="space-y-4 text-xs max-h-[92vh] overflow-y-auto custom-scrollbar pr-1">
            <div className="rounded-xl border border-gray-100 bg-gray-50/80 dark:border-gray-800 dark:bg-gray-900/50 p-4 space-y-3">
              <div className="flex items-center gap-3">
                <div className="h-12 w-12 rounded-xl overflow-hidden bg-white border border-gray-200 dark:border-gray-800 dark:bg-gray-900 shrink-0 flex items-center justify-center">
                  {manageProduct.image ? (
                    <img
                      src={manageProduct.image.startsWith("http") ? manageProduct.image : `/storage/${manageProduct.image}`}
                      alt={manageProduct.name}
                      className="w-full h-full object-cover"
                    />
                  ) : manageProduct.product_type === "voucher" ? (
                    <Wifi className="w-6 h-6 text-brand-500" />
                  ) : (
                    <PackageIcon className="w-6 h-6 text-gray-400" />
                  )}
                </div>
                <div className="min-w-0 flex-1">
                  <h4 className="font-bold text-sm text-gray-900 dark:text-white truncate">{manageProduct.name}</h4>
                  <div className="flex items-center gap-2 mt-1">
                    <span
                      className={cn(
                        "inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold text-white shadow-xs uppercase",
                        manageProduct.is_active ? "bg-emerald-500" : "bg-gray-500"
                      )}
                    >
                      {manageProduct.is_active ? "Aktif" : "Nonaktif"}
                    </span>
                    <span className="font-mono text-emerald-600 dark:text-emerald-400 font-bold">
                      {formatRupiah(manageProduct.price)}
                    </span>
                  </div>
                </div>
              </div>

              <div className="grid grid-cols-2 gap-2 pt-2 border-t border-gray-200 dark:border-gray-800 text-[11px]">
                <div>
                  <span className="text-gray-500 dark:text-gray-400">Tipe: </span>
                  <strong className="text-gray-900 dark:text-white font-medium">
                    {manageProduct.product_type === "voucher" ? "Voucher Hotspot" : "Produk Fisik"}
                  </strong>
                </div>
                <div>
                  <span className="text-gray-500 dark:text-gray-400">Stok: </span>
                  <strong className="text-gray-900 dark:text-white font-mono">{manageProduct.stock} Unit</strong>
                </div>
                <div>
                  <span className="text-gray-500 dark:text-gray-400">Kategori: </span>
                  <strong className="text-gray-900 dark:text-white font-medium">
                    {manageProduct.category?.name || "-"}
                  </strong>
                </div>
                <div>
                  <span className="text-gray-500 dark:text-gray-400">SKU: </span>
                  <strong className="text-gray-900 dark:text-white font-mono">{manageProduct.sku || "-"}</strong>
                </div>
              </div>
            </div>

            {/* Quick Action Buttons */}
            <div className="flex flex-col gap-2 pt-2">
              <button
                type="button"
                onClick={() => openEditProduct(manageProduct)}
                className="w-full inline-flex items-center justify-center gap-1.5 px-4 py-2.5 rounded-xl bg-brand-500 hover:bg-brand-600 active:scale-95 text-xs font-bold text-white shadow-xs transition"
              >
                <Pencil className="w-4 h-4" />
                <span>Edit Informasi Produk</span>
              </button>

              <button
                type="button"
                onClick={() => {
                  setItemToDelete({ id: manageProduct.id, type: "product", name: manageProduct.name })
                  setManageProduct(null)
                  setIsDeleteModalOpen(true)
                }}
                className="w-full inline-flex items-center justify-center gap-1.5 px-4 py-2.5 rounded-xl bg-rose-500 hover:bg-rose-600 active:scale-95 text-xs font-bold text-white shadow-xs transition"
              >
                <Trash2 className="w-4 h-4" />
                <span>Hapus Produk Ini</span>
              </button>
            </div>
          </div>
        )}
      </Modal>

      {/* ── KELOLA CATEGORY POPUP MODAL ── */}
      <Modal
        isOpen={!!manageCategory}
        onClose={() => setManageCategory(null)}
        title="Kelola Kategori"
        description="Detail informasi dan aksi kategori produk"
        maxWidth="md"
      >
        {manageCategory && (
          <div className="space-y-4 text-xs max-h-[92vh] overflow-y-auto custom-scrollbar pr-1">
            <div className="rounded-xl border border-gray-100 bg-gray-50/80 dark:border-gray-800 dark:bg-gray-900/50 p-4 space-y-2">
              <h4 className="font-bold text-sm text-gray-900 dark:text-white">{manageCategory.name}</h4>
              {manageCategory.description && (
                <p className="text-xs text-gray-500 dark:text-gray-400">{manageCategory.description}</p>
              )}
              <div className="pt-2 border-t border-gray-200 dark:border-gray-800 text-[11px] text-gray-500 dark:text-gray-400">
                Total Produk Terkait: <strong className="text-gray-900 dark:text-white font-mono">{manageCategory.products_count || 0}</strong>
              </div>
            </div>

            <div className="flex flex-col gap-2 pt-2">
              <button
                type="button"
                onClick={() => {
                  setEditingCategory(manageCategory)
                  categoryForm.setData({
                    name: manageCategory.name,
                    description: manageCategory.description || "",
                    icon: manageCategory.icon || "fa-cube",
                    sort_order: manageCategory.sort_order.toString(),
                    is_active: manageCategory.is_active,
                  })
                  setManageCategory(null)
                  setIsCategoryModalOpen(true)
                }}
                className="w-full inline-flex items-center justify-center gap-1.5 px-4 py-2.5 rounded-xl bg-brand-500 hover:bg-brand-600 active:scale-95 text-xs font-bold text-white shadow-xs transition"
              >
                <Pencil className="w-4 h-4" />
                <span>Edit Kategori</span>
              </button>

              <button
                type="button"
                onClick={() => {
                  setItemToDelete({ id: manageCategory.id, type: "category", name: manageCategory.name })
                  setManageCategory(null)
                  setIsDeleteModalOpen(true)
                }}
                className="w-full inline-flex items-center justify-center gap-1.5 px-4 py-2.5 rounded-xl bg-rose-500 hover:bg-rose-600 active:scale-95 text-xs font-bold text-white shadow-xs transition"
              >
                <Trash2 className="w-4 h-4" />
                <span>Hapus Kategori Ini</span>
              </button>
            </div>
          </div>
        )}
      </Modal>

      {/* ── PRODUCT FORM MODAL ── */}
      <Modal
        isOpen={isProductModalOpen}
        onClose={() => setIsProductModalOpen(false)}
        title={editingProduct ? "Edit Produk" : "Tambah Produk Baru"}
        description="Form data produk fisik atau paket voucher hotspot online"
        maxWidth="xl"
      >
        <form onSubmit={handleProductSubmit} className="space-y-4 text-xs max-h-[92vh] overflow-y-auto custom-scrollbar pr-1">
          <div className="grid grid-cols-2 gap-3">
            <div>
              <label className="mb-1 block font-semibold text-gray-700 dark:text-gray-300">Tipe Produk</label>
              <select
                value={productForm.data.product_type}
                onChange={(e) => productForm.setData("product_type", e.target.value as any)}
                className="h-10 w-full rounded-xl border border-gray-300 bg-white px-3 text-xs text-gray-900 focus:border-brand-500 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white"
              >
                <option value="general">Produk Fisik / Layanan</option>
                <option value="voucher">Hotspot Voucher</option>
              </select>
            </div>

            {productForm.data.product_type === "voucher" ? (
              <div>
                <label className="mb-1 block font-semibold text-gray-700 dark:text-gray-300">
                  Tautkan Paket Hotspot
                </label>
                <select
                  value={productForm.data.voucher_package_id}
                  onChange={(e) => {
                    const val = e.target.value
                    productForm.setData("voucher_package_id", val)
                    const pkg = voucherPackages.find((v) => v.id.toString() === val)
                    if (pkg && !productForm.data.name) {
                      productForm.setData("name", pkg.name)
                      productForm.setData("price", pkg.price.toString())
                    }
                  }}
                  className="h-10 w-full rounded-xl border border-gray-300 bg-white px-3 text-xs text-gray-900 focus:border-brand-500 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                >
                  <option value="">Pilih Paket...</option>
                  {voucherPackages.map((pkg) => (
                    <option key={pkg.id} value={pkg.id}>
                      {pkg.name} ({formatRupiah(pkg.price)})
                    </option>
                  ))}
                </select>
              </div>
            ) : (
              <div>
                <label className="mb-1 block font-semibold text-gray-700 dark:text-gray-300">Kategori Produk</label>
                <select
                  value={productForm.data.category_id}
                  onChange={(e) => productForm.setData("category_id", e.target.value)}
                  className="h-10 w-full rounded-xl border border-gray-300 bg-white px-3 text-xs text-gray-900 focus:border-brand-500 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                >
                  <option value="">Tanpa Kategori</option>
                  {categories.map((c) => (
                    <option key={c.id} value={c.id}>
                      {c.name}
                    </option>
                  ))}
                </select>
              </div>
            )}
          </div>

          <div>
            <label className="mb-1 block font-semibold text-gray-700 dark:text-gray-300">Nama Produk</label>
            <input
              type="text"
              required
              value={productForm.data.name}
              onChange={(e) => productForm.setData("name", e.target.value)}
              placeholder="cth: Router ZTE F609 / Voucher 3 Jam"
              className="h-10 w-full rounded-xl border border-gray-300 bg-transparent px-3.5 text-xs text-gray-900 placeholder:text-gray-400 focus:border-brand-500 focus:outline-hidden dark:border-gray-700 dark:text-white dark:placeholder:text-gray-500"
            />
          </div>

          <div className="grid grid-cols-2 gap-3">
            <div>
              <label className="mb-1 block font-semibold text-gray-700 dark:text-gray-300">Harga Jual (Rp)</label>
              <input
                type="number"
                required
                value={productForm.data.price}
                onChange={(e) => productForm.setData("price", e.target.value)}
                placeholder="0"
                className="h-10 w-full rounded-xl border border-gray-300 bg-transparent px-3.5 text-xs font-mono text-gray-900 placeholder:text-gray-400 focus:border-brand-500 focus:outline-hidden dark:border-gray-700 dark:text-white dark:placeholder:text-gray-500"
              />
            </div>
            <div>
              <label className="mb-1 block font-semibold text-gray-700 dark:text-gray-300">Stok Unit</label>
              <input
                type="number"
                value={productForm.data.stock}
                onChange={(e) => productForm.setData("stock", e.target.value)}
                placeholder="50"
                className="h-10 w-full rounded-xl border border-gray-300 bg-transparent px-3.5 text-xs font-mono text-gray-900 placeholder:text-gray-400 focus:border-brand-500 focus:outline-hidden dark:border-gray-700 dark:text-white dark:placeholder:text-gray-500"
              />
            </div>
          </div>

          <div>
            <label className="mb-1 block font-semibold text-gray-700 dark:text-gray-300">Deskripsi Singkat</label>
            <textarea
              rows={2}
              value={productForm.data.short_description}
              onChange={(e) => productForm.setData("short_description", e.target.value)}
              placeholder="Penjelasan ringkas produk..."
              className="w-full rounded-xl border border-gray-300 bg-transparent p-3 text-xs text-gray-900 placeholder:text-gray-400 focus:border-brand-500 focus:outline-hidden dark:border-gray-700 dark:text-white dark:placeholder:text-gray-500"
            />
          </div>

          <div>
            <label className="mb-1 block font-semibold text-gray-700 dark:text-gray-300">Foto / Gambar Produk</label>
            <input
              type="file"
              ref={fileInputRef}
              accept="image/*"
              onChange={(e) => {
                const file = e.target.files?.[0] || null
                productForm.setData("image", file)
                if (file) setPreviewImage(URL.createObjectURL(file))
              }}
              className="w-full text-xs text-gray-500 file:mr-3 file:py-1.5 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-brand-50 file:text-brand-600 hover:file:bg-brand-100 dark:file:bg-brand-500/10 dark:file:text-brand-400"
            />
            {previewImage && (
              <div className="mt-2 h-24 w-24 rounded-xl overflow-hidden border border-gray-200 dark:border-gray-800">
                <img src={previewImage} alt="Preview" className="w-full h-full object-cover" />
              </div>
            )}
          </div>

          <div className="flex items-center justify-between p-3 rounded-xl border border-gray-100 bg-gray-50/80 dark:border-gray-800 dark:bg-gray-900/50">
            <span className="text-xs font-semibold text-gray-900 dark:text-white">Tampilkan di Toko / Landing</span>
            <Switch checked={productForm.data.is_active} onChange={(v) => productForm.setData("is_active", v)} />
          </div>

          <div className="flex items-center justify-end gap-2 pt-3 border-t border-gray-200 dark:border-gray-800">
            <button
              type="button"
              onClick={() => setIsProductModalOpen(false)}
              className="inline-flex items-center justify-center px-3.5 py-2 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:hover:bg-gray-800 text-xs font-semibold text-gray-700 dark:text-gray-200 transition shadow-2xs"
            >
              Batal
            </button>
            <button
              type="submit"
              disabled={productForm.processing}
              className="inline-flex items-center justify-center px-3.5 py-2 rounded-xl bg-brand-500 hover:bg-brand-600 active:scale-95 text-xs font-bold text-white shadow-xs transition disabled:opacity-50"
            >
              {productForm.processing ? "Menyimpan..." : "Simpan Produk"}
            </button>
          </div>
        </form>
      </Modal>

      {/* ── CATEGORY FORM MODAL ── */}
      <Modal
        isOpen={isCategoryModalOpen}
        onClose={() => setIsCategoryModalOpen(false)}
        title={editingCategory ? "Edit Kategori" : "Tambah Kategori Baru"}
        description="Form nama dan keterangan kategori produk"
        maxWidth="md"
      >
        <form onSubmit={handleCategorySubmit} className="space-y-4 text-xs max-h-[92vh] overflow-y-auto custom-scrollbar pr-1">
          <div>
            <label className="mb-1 block font-semibold text-gray-700 dark:text-gray-300">Nama Kategori</label>
            <input
              type="text"
              required
              value={categoryForm.data.name}
              onChange={(e) => categoryForm.setData("name", e.target.value)}
              placeholder="cth: Perangkat Jaringan"
              className="h-10 w-full rounded-xl border border-gray-300 bg-transparent px-3.5 text-xs text-gray-900 placeholder:text-gray-400 focus:border-brand-500 focus:outline-hidden dark:border-gray-700 dark:text-white dark:placeholder:text-gray-500"
            />
          </div>
          <div>
            <label className="mb-1 block font-semibold text-gray-700 dark:text-gray-300">Deskripsi</label>
            <input
              type="text"
              value={categoryForm.data.description}
              onChange={(e) => categoryForm.setData("description", e.target.value)}
              placeholder="Keterangan kategori..."
              className="h-10 w-full rounded-xl border border-gray-300 bg-transparent px-3.5 text-xs text-gray-900 placeholder:text-gray-400 focus:border-brand-500 focus:outline-hidden dark:border-gray-700 dark:text-white dark:placeholder:text-gray-500"
            />
          </div>
          <div className="flex items-center justify-end gap-2 pt-3 border-t border-gray-200 dark:border-gray-800">
            <button
              type="button"
              onClick={() => setIsCategoryModalOpen(false)}
              className="inline-flex items-center justify-center px-3.5 py-2 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:hover:bg-gray-800 text-xs font-semibold text-gray-700 dark:text-gray-200 transition shadow-2xs"
            >
              Batal
            </button>
            <button
              type="submit"
              disabled={categoryForm.processing}
              className="inline-flex items-center justify-center px-3.5 py-2 rounded-xl bg-brand-500 hover:bg-brand-600 active:scale-95 text-xs font-bold text-white shadow-xs transition disabled:opacity-50"
            >
              Simpan Kategori
            </button>
          </div>
        </form>
      </Modal>

      {/* ── DELETE CONFIRM MODAL ── */}
      <Modal
        isOpen={isDeleteModalOpen}
        onClose={() => setIsDeleteModalOpen(false)}
        title={`Hapus ${itemToDelete?.type === "product" ? "Produk" : "Kategori"}`}
        description="Konfirmasi penghapusan data dari sistem"
        maxWidth="sm"
      >
        <div className="space-y-4 text-xs">
          <p className="text-gray-600 dark:text-gray-300">
            Apakah Anda yakin ingin menghapus <strong>{itemToDelete?.name}</strong>? Tindakan ini tidak dapat dibatalkan.
          </p>
          <div className="flex items-center justify-end gap-2 pt-3 border-t border-gray-200 dark:border-gray-800">
            <button
              type="button"
              onClick={() => setIsDeleteModalOpen(false)}
              className="inline-flex items-center justify-center px-3.5 py-2 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:hover:bg-gray-800 text-xs font-semibold text-gray-700 dark:text-gray-200 transition shadow-2xs"
            >
              Batal
            </button>
            <button
              type="button"
              onClick={confirmDelete}
              className="inline-flex items-center justify-center px-3.5 py-2 rounded-xl bg-rose-500 hover:bg-rose-600 active:scale-95 text-xs font-bold text-white shadow-xs transition"
            >
              Hapus
            </button>
          </div>
        </div>
      </Modal>
    </AppLayout>
  )
}
