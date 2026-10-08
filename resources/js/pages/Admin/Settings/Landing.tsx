import { AppLayout } from "@/components/layout/app-layout"
import {
  Globe,
  Send,
  ExternalLink,
  Phone,
  MapPin,
  Wifi,
  ShoppingBag,
  Bot,
  Loader2,
  ChevronDown,
  Save,
  Trash2,
  Layers,
  Plus,
  RotateCcw,
} from "lucide-react"
import { Label } from "@/components/ui/label"
import { Input } from "@/components/ui/input"
import { Textarea } from "@/components/ui/textarea"
import { Switch } from "@/components/tailadmin/Switch"
import { PageProps } from "@/types"
import { adminSidebarItems, adminNavItems, adminBrand } from "@/lib/admin-nav"
import { useForm, Link } from "@inertiajs/react"
import { useState } from "react"
import { cn } from "@/lib/utils"

export interface ShopSlide {
  badge: string
  title: string
  desc: string
  icon: string
  color: string
}

const DEFAULT_SLIDES: ShopSlide[] = [
  {
    badge: "OFFICIAL STORE",
    title: "Pusat Berbagai Kebutuhan Anda",
    desc: "Menyediakan beragam produk pilihan, mulai dari voucher wifi, perangkat jaringan, elektronik, hingga kebutuhan internet lainnya.",
    icon: "fa-shopping-bag",
    color: "#00E5FF",
  },
  {
    badge: "JARINGAN & SERVER",
    title: "Router MikroTik & Server Siap Pakai",
    desc: "Routerboard Gigabit, switch manage, mini PC server, dan perlengkapan jaringan handal dengan performa maksimal.",
    icon: "fa-server",
    color: "#00E5FF",
  },
  {
    badge: "VOUCHER INTERNET WIFI",
    title: "Aktivasi Instan & Otomatis",
    desc: "Beli voucher wifi hotspot instan, bayar via transfer / QRIS, dan akun langsung otomatis aktif di router.",
    icon: "fa-wifi",
    color: "#00E5FF",
  },
  {
    badge: "TRANSAKSI MUDAH & AMAN",
    title: "Pemesanan Praktis via WhatsApp",
    desc: "Pilih produk favorit Anda, checkout tanpa ribet, dan konfirmasi pesanan terhubung langsung ke admin.",
    icon: "fa-whatsapp",
    color: "#25D366",
  },
]

interface LandingSettingsProps {
  tenant: {
    id: number
    name: string
    slug: string
    logo?: string
    phone?: string
    address?: string
  }
  landingSettings: {
    landing_title: string
    landing_tagline: string
    landing_description: string
    landing_banner?: string | null
    landing_whatsapp: string
    landing_address: string
    landing_show_vouchers: boolean
    landing_show_products: boolean
    telegram_enabled?: boolean
    telegram_order_notif?: boolean
    telegram_bot_token?: string
    telegram_mode: "chat" | "topic"
    telegram_chat_id: string
    telegram_topic_id: string
    shop_slides?: ShopSlide[]
  }
}

export default function AdminLandingSettingsPage({
  tenant,
  landingSettings,
}: PageProps<LandingSettingsProps>) {
  const form = useForm({
    landing_title: landingSettings.landing_title || "",
    landing_tagline: landingSettings.landing_tagline || "",
    landing_description: landingSettings.landing_description || "",
    landing_whatsapp: landingSettings.landing_whatsapp || "",
    landing_address: landingSettings.landing_address || "",
    landing_show_vouchers: landingSettings.landing_show_vouchers ?? true,
    landing_show_products: landingSettings.landing_show_products ?? true,
    telegram_enabled: landingSettings.telegram_enabled ?? true,
    telegram_order_notif: landingSettings.telegram_order_notif ?? true,
    telegram_bot_token: landingSettings.telegram_bot_token || "",
    telegram_mode: landingSettings.telegram_mode || "chat",
    telegram_chat_id: landingSettings.telegram_chat_id || "",
    telegram_topic_id: landingSettings.telegram_topic_id || "",
    banner_image: null as File | null,
    shop_slides: (landingSettings.shop_slides && landingSettings.shop_slides.length > 0) ? landingSettings.shop_slides : DEFAULT_SLIDES,
  })

  // Accordion open/close states
  const [openSections, setOpenSections] = useState<{ [key: string]: boolean }>({
    identity: true,
    slides: true,
    catalog: true,
    telegram: true,
  })

  const toggleSection = (section: string) => {
    setOpenSections((prev) => ({ ...prev, [section]: !prev[section] }))
  }

  const addSlide = () => {
    const newSlide: ShopSlide = {
      badge: "PROMO BARU",
      title: "Judul Slide Promosi",
      desc: "Tuliskan deskripsi atau pesan menarik untuk pelanggan toko di sini.",
      icon: "fa-tag",
      color: "#00E5FF",
    }
    form.setData("shop_slides", [...form.data.shop_slides, newSlide])
  }

  const removeSlide = (index: number) => {
    const updated = form.data.shop_slides.filter((_, i) => i !== index)
    form.setData("shop_slides", updated.length > 0 ? updated : DEFAULT_SLIDES)
  }

  const updateSlide = (index: number, field: keyof ShopSlide, val: string) => {
    const updated = [...form.data.shop_slides]
    updated[index] = { ...updated[index], [field]: val }
    form.setData("shop_slides", updated)
  }

  const resetSlides = () => {
    if (confirm("Reset semua slide ke susunan teks bawaan?")) {
      form.setData("shop_slides", DEFAULT_SLIDES)
    }
  }

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    form.post("/admin/landing-settings", {
      preserveScroll: true,
    })
  }

  return (
    <AppLayout
      title="Pengaturan Landing & Toko"
            brand={adminBrand}
      sidebarItems={adminSidebarItems}
      navItems={adminNavItems}
    >
      <div className="space-y-6">
        {/* Top Action Toolbar */}
        <div className="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3 rounded-2xl border border-gray-200 bg-white p-4 shadow-xs dark:border-gray-800 dark:bg-white/[0.03]">
          <div className="flex items-center gap-3">
            <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-500 dark:bg-brand-500/10 dark:text-brand-400">
              <Globe className="h-5 w-5" />
            </div>
            <div>
              <h2 className="text-sm font-bold text-gray-900 dark:text-white">
                Landing Page Subdomain
              </h2>
              <p className="text-xs text-gray-500 dark:text-gray-400">
                Toko online aktif di <span className="font-mono text-brand-500 font-semibold">/t/{tenant.slug}</span>
              </p>
            </div>
          </div>

          <div className="flex items-center gap-2">
            <a
              href={`/t/${tenant.slug}`}
              target="_blank"
              rel="noopener noreferrer"
              className="inline-flex h-9 items-center justify-center gap-1.5 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 px-3.5 text-xs font-bold text-gray-700 dark:border-gray-800 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700 transition-all shadow-xs active:scale-95"
            >
              <ExternalLink className="h-4 w-4 text-brand-500" />
              <span>Lihat Toko</span>
            </a>

            <button
              type="button"
              onClick={handleSubmit}
              disabled={form.processing}
              className="inline-flex h-9 items-center justify-center gap-1.5 rounded-xl bg-brand-500 hover:bg-brand-600 px-4 text-xs font-bold text-white transition-all shadow-xs active:scale-95 disabled:opacity-50"
            >
              {form.processing ? (
                <>
                  <Loader2 className="h-4 w-4 animate-spin" />
                  <span>Menyimpan...</span>
                </>
              ) : (
                <>
                  <Save className="h-4 w-4" />
                  <span>Simpan Perubahan</span>
                </>
              )}
            </button>
          </div>
        </div>

        {/* Content Body */}
        <form onSubmit={handleSubmit} className="space-y-4">
          {/* CARD 1: IDENTITAS & PROFIL USAHA */}
          <div className="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xs dark:border-gray-800 dark:bg-white/[0.03]">
            <button
              type="button"
              onClick={() => toggleSection("identity")}
              className="w-full p-4 sm:p-5 flex items-center justify-between gap-3 text-left hover:bg-gray-50/50 dark:hover:bg-white/[0.02] transition-colors"
            >
              <div className="flex items-center gap-3 min-w-0">
                <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-500 dark:bg-brand-500/10 dark:text-brand-400">
                  <Globe className="h-4 w-4" />
                </div>
                <div className="min-w-0">
                  <h3 className="text-sm font-bold text-gray-900 dark:text-white truncate">
                    Identitas &amp; Profil Toko
                  </h3>
                  <p className="text-xs text-gray-500 dark:text-gray-400 truncate">
                    Judul headline, slogan, nomor CS WhatsApp dan alamat layanan.
                  </p>
                </div>
              </div>
              <div className="shrink-0 p-1">
                <ChevronDown
                  className={cn(
                    "h-4 w-4 text-gray-400 transition-transform duration-200",
                    openSections.identity && "rotate-180 text-gray-700 dark:text-gray-200"
                  )}
                />
              </div>
            </button>

            {openSections.identity && (
              <div className="px-4 pb-5 sm:px-5 border-t border-gray-100 dark:border-gray-800 pt-4 space-y-4">
                <div className="rounded-xl border border-gray-200 bg-gray-50/50 dark:border-gray-800 dark:bg-gray-900/50 p-4 space-y-4">
                  <div className="space-y-1.5">
                    <Label className="text-xs font-semibold text-gray-700 dark:text-gray-300">Judul Utama Landing (Headline) *</Label>
                    <Input
                      required
                      value={form.data.landing_title}
                      onChange={(e) => form.setData("landing_title", e.target.value)}
                      placeholder={`cth: Selamat Datang di ${tenant.name}`}
                      className="h-10 rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-950 px-3 text-xs text-gray-900 dark:text-white focus:ring-2 focus:ring-brand-500"
                    />
                  </div>

                  <div className="space-y-1.5">
                    <Label className="text-xs font-semibold text-gray-700 dark:text-gray-300">Tagline / Slogan Toko</Label>
                    <Input
                      value={form.data.landing_tagline}
                      onChange={(e) => form.setData("landing_tagline", e.target.value)}
                      placeholder="cth: Solusi Internet Cepat, Stabil, & Terjangkau"
                      className="h-10 rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-950 px-3 text-xs text-gray-900 dark:text-white focus:ring-2 focus:ring-brand-500"
                    />
                  </div>

                  <div className="space-y-1.5">
                    <Label className="text-xs font-semibold text-gray-700 dark:text-gray-300">Deskripsi Lengkap Toko / Layanan</Label>
                    <Textarea
                      rows={3}
                      value={form.data.landing_description}
                      onChange={(e) => form.setData("landing_description", e.target.value)}
                      placeholder="Jelaskan produk & keunggulan layanan WiFi hotspot Anda..."
                      className="rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-950 p-3 text-xs text-gray-900 dark:text-white focus:ring-2 focus:ring-brand-500"
                    />
                  </div>

                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-1">
                    <div className="space-y-1.5">
                      <Label className="text-xs font-semibold text-gray-700 dark:text-gray-300 flex items-center gap-1.5">
                        <Phone className="h-3.5 w-3.5 text-emerald-500" />
                        <span>No. WhatsApp CS</span>
                      </Label>
                      <Input
                        value={form.data.landing_whatsapp}
                        onChange={(e) => form.setData("landing_whatsapp", e.target.value)}
                        placeholder="cth: 081234567890"
                        className="h-10 rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-950 px-3 text-xs text-gray-900 dark:text-white focus:ring-2 focus:ring-brand-500"
                      />
                    </div>

                    <div className="space-y-1.5">
                      <Label className="text-xs font-semibold text-gray-700 dark:text-gray-300 flex items-center gap-1.5">
                        <MapPin className="h-3.5 w-3.5 text-rose-500" />
                        <span>Wilayah Layanan</span>
                      </Label>
                      <Input
                        value={form.data.landing_address}
                        onChange={(e) => form.setData("landing_address", e.target.value)}
                        placeholder="cth: Jl. Merdeka No. 10, Jakarta"
                        className="h-10 rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-950 px-3 text-xs text-gray-900 dark:text-white focus:ring-2 focus:ring-brand-500"
                      />
                    </div>
                  </div>
                </div>
              </div>
            )}
          </div>

          {/* CARD 2: SLIDE BANNER & ONBOARDING TOKO */}
          <div className="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xs dark:border-gray-800 dark:bg-white/[0.03]">
            <button
              type="button"
              onClick={() => toggleSection("slides")}
              className="w-full p-4 sm:p-5 flex items-center justify-between gap-3 text-left hover:bg-gray-50/50 dark:hover:bg-white/[0.02] transition-colors"
            >
              <div className="flex items-center gap-3 min-w-0">
                <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400">
                  <Layers className="h-4 w-4" />
                </div>
                <div className="min-w-0">
                  <h3 className="text-sm font-bold text-gray-900 dark:text-white truncate">
                    Slide Promosi &amp; Onboarding Toko
                  </h3>
                  <p className="text-xs text-gray-500 dark:text-gray-400 truncate">
                    Atur teks banner swipe, judul, pesan ajakan, ikon dan badge promosi saat pembeli membuka toko.
                  </p>
                </div>
              </div>
              <div className="shrink-0 p-1">
                <ChevronDown
                  className={cn(
                    "h-4 w-4 text-gray-400 transition-transform duration-200",
                    openSections.slides && "rotate-180 text-gray-700 dark:text-gray-200"
                  )}
                />
              </div>
            </button>

            {openSections.slides && (
              <div className="px-4 pb-5 sm:px-5 border-t border-gray-100 dark:border-gray-800 pt-4 space-y-4">
                <div className="flex items-center justify-between gap-2">
                  <p className="text-xs text-gray-500 dark:text-gray-400">
                    Daftar slide banner yang muncul pada tampilan layar awal toko ({form.data.shop_slides.length} slide).
                  </p>
                  <div className="flex items-center gap-2">
                    <button
                      type="button"
                      onClick={resetSlides}
                      className="flex items-center gap-1 text-xs font-semibold text-gray-700 dark:text-gray-300 px-2.5 py-1 rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 hover:bg-gray-50 dark:hover:bg-gray-800 transition-all shadow-xs"
                      title="Reset ke Slide Default"
                    >
                      <RotateCcw className="h-3 w-3" />
                      <span className="hidden sm:inline">Reset Default</span>
                    </button>
                    <button
                      type="button"
                      onClick={addSlide}
                      className="flex items-center gap-1 text-xs font-bold text-brand-500 dark:text-brand-400 px-3 py-1 rounded-lg border border-brand-500/20 bg-brand-500/10 hover:bg-brand-500/20 transition-all whitespace-nowrap"
                    >
                      <Plus className="h-3.5 w-3.5" />
                      <span>Tambah Slide</span>
                    </button>
                  </div>
                </div>

                <div className="space-y-3">
                  {form.data.shop_slides.map((slide, idx) => (
                    <div
                      key={idx}
                      className="rounded-xl border border-gray-200 bg-gray-50/50 dark:border-gray-800 dark:bg-gray-900/50 p-3.5 sm:p-4 space-y-3 relative group"
                    >
                      <div className="flex items-center justify-between gap-2 border-b border-gray-200 dark:border-gray-800 pb-2.5">
                        <div className="flex items-center gap-2">
                          <span
                            className="inline-flex h-6 w-6 items-center justify-center rounded-lg text-xs font-black font-mono"
                            style={{
                              backgroundColor: `${slide.color || "#00E5FF"}20`,
                              color: slide.color || "#00E5FF",
                              border: `1px solid ${slide.color || "#00E5FF"}40`,
                            }}
                          >
                            #{idx + 1}
                          </span>
                          <span className="text-xs font-bold text-gray-900 dark:text-white truncate max-w-[200px] sm:max-w-xs">
                            {slide.title || `Slide #${idx + 1}`}
                          </span>
                        </div>

                        <div className="flex items-center gap-1.5">
                          {form.data.shop_slides.length > 1 && (
                            <button
                              type="button"
                              onClick={() => removeSlide(idx)}
                              className="flex h-7 w-7 items-center justify-center rounded-lg border border-rose-200 bg-rose-50 text-rose-600 hover:bg-rose-100 dark:border-rose-900/30 dark:bg-rose-950/30 dark:text-rose-400 transition-all whitespace-nowrap"
                              title="Hapus Slide Ini"
                            >
                              <Trash2 className="h-3.5 w-3.5" />
                            </button>
                          )}
                        </div>
                      </div>

                      <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div className="space-y-1">
                          <Label className="text-[11px] font-semibold text-gray-700 dark:text-gray-300">Judul Slide *</Label>
                          <Input
                            required
                            value={slide.title}
                            onChange={(e) => updateSlide(idx, "title", e.target.value)}
                            placeholder="cth: Pusat Berbagai Kebutuhan Anda"
                            className="h-9 rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-950 px-3 text-xs text-gray-900 dark:text-white focus:ring-2 focus:ring-brand-500"
                          />
                        </div>

                        <div className="space-y-1">
                          <Label className="text-[11px] font-semibold text-gray-700 dark:text-gray-300">Badge / Label Highlight</Label>
                          <Input
                            value={slide.badge}
                            onChange={(e) => updateSlide(idx, "badge", e.target.value)}
                            placeholder="cth: OFFICIAL STORE / PROMO KHUSUS"
                            className="h-9 rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-950 px-3 text-xs text-gray-900 dark:text-white focus:ring-2 focus:ring-brand-500"
                          />
                        </div>
                      </div>

                      <div className="space-y-1">
                        <Label className="text-[11px] font-semibold text-gray-700 dark:text-gray-300">Pesan / Deskripsi Slide *</Label>
                        <Textarea
                          rows={2}
                          required
                          value={slide.desc}
                          onChange={(e) => updateSlide(idx, "desc", e.target.value)}
                          placeholder="Tuliskan penjelasan produk atau promo yang ditawarkan..."
                          className="rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-950 p-2.5 text-xs text-gray-900 dark:text-white focus:ring-2 focus:ring-brand-500"
                        />
                      </div>

                      <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-1">
                        <div className="space-y-1 min-w-0">
                          <Label className="text-[11px] font-semibold text-gray-700 dark:text-gray-300">Ikon FontAwesome</Label>
                          <div className="flex flex-col sm:flex-row gap-2 min-w-0">
                            <select
                              value={slide.icon || "fa-shopping-bag"}
                              onChange={(e) => updateSlide(idx, "icon", e.target.value)}
                              className="h-9 w-full sm:flex-1 rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-950 px-2.5 text-xs text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-500"
                            >
                              <option value="fa-shopping-bag">fa-shopping-bag (Toko)</option>
                              <option value="fa-wifi">fa-wifi (WiFi Hotspot)</option>
                              <option value="fa-server">fa-server (Router &amp; Server)</option>
                              <option value="fa-sitemap">fa-sitemap (Fiber Optik / OLT)</option>
                              <option value="fa-laptop">fa-laptop (Elektronik / Komputer)</option>
                              <option value="fa-whatsapp">fa-whatsapp (WhatsApp)</option>
                              <option value="fa-bolt">fa-bolt (Kecepatan / Instan)</option>
                              <option value="fa-tag">fa-tag (Promo Diskon)</option>
                              <option value="fa-star">fa-star (Favorit / Unggulan)</option>
                              <option value="fa-shield">fa-shield (Aman &amp; Bergaransi)</option>
                            </select>
                            <Input
                              value={slide.icon}
                              onChange={(e) => updateSlide(idx, "icon", e.target.value)}
                              placeholder="fa-icon"
                              className="h-9 w-full sm:w-28 rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-950 px-2 font-mono text-[11px] text-gray-900 dark:text-white"
                            />
                          </div>
                        </div>

                        <div className="space-y-1 min-w-0">
                          <Label className="text-[11px] font-semibold text-gray-700 dark:text-gray-300">Warna Aksen Highlight</Label>
                          <div className="flex items-center gap-2 min-w-0">
                            <input
                              type="color"
                              value={slide.color || "#00E5FF"}
                              onChange={(e) => updateSlide(idx, "color", e.target.value)}
                              className="h-9 w-12 cursor-pointer rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-950 p-1 shrink-0"
                            />
                            <div className="flex flex-wrap gap-1.5 flex-1 min-w-0">
                              {["#00E5FF", "#25D366", "#F59E0B", "#A855F7", "#EC4899", "#3B82F6"].map((c) => (
                                <button
                                  key={c}
                                  type="button"
                                  onClick={() => updateSlide(idx, "color", c)}
                                  className="h-6 w-6 rounded-md border transition-transform hover:scale-110 shrink-0"
                                  style={{
                                    backgroundColor: c,
                                    borderColor: slide.color === c ? "#ffffff" : "transparent",
                                  }}
                                  title={c}
                                />
                              ))}
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                  ))}
                </div>
              </div>
            )}
          </div>

          {/* CARD 3: VISIBILITAS KATALOG */}
          <div className="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xs dark:border-gray-800 dark:bg-white/[0.03]">
            <button
              type="button"
              onClick={() => toggleSection("catalog")}
              className="w-full p-4 sm:p-5 flex items-center justify-between gap-3 text-left hover:bg-gray-50/50 dark:hover:bg-white/[0.02] transition-colors"
            >
              <div className="flex items-center gap-3 min-w-0">
                <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400">
                  <Wifi className="h-4 w-4" />
                </div>
                <div className="min-w-0">
                  <h3 className="text-sm font-bold text-gray-900 dark:text-white truncate">
                    Visibilitas Produk &amp; Voucher
                  </h3>
                  <p className="text-xs text-gray-500 dark:text-gray-400 truncate">
                    Atur tampilan paket voucher WiFi hotspot dan produk fisik di landing page.
                  </p>
                </div>
              </div>
              <div className="shrink-0 p-1">
                <ChevronDown
                  className={cn(
                    "h-4 w-4 text-gray-400 transition-transform duration-200",
                    openSections.catalog && "rotate-180 text-gray-700 dark:text-gray-200"
                  )}
                />
              </div>
            </button>

            {openSections.catalog && (
              <div className="px-4 pb-5 sm:px-5 border-t border-gray-100 dark:border-gray-800 pt-4">
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                  <div className="flex items-center justify-between p-3.5 rounded-xl border border-gray-200 bg-gray-50/50 dark:border-gray-800 dark:bg-gray-900/50">
                    <div className="flex items-center gap-2.5">
                      <Wifi className="h-4 w-4 text-brand-500" />
                      <div>
                        <p className="text-xs font-semibold text-gray-900 dark:text-white">Voucher WiFi Hotspot</p>
                        <p className="text-[10px] text-gray-500 dark:text-gray-400">Tampilkan katalog voucher hotspot</p>
                      </div>
                    </div>
                    <Switch
                      checked={form.data.landing_show_vouchers}
                      onChange={(v) => form.setData("landing_show_vouchers", v)}
                    />
                  </div>

                  <div className="flex items-center justify-between p-3.5 rounded-xl border border-gray-200 bg-gray-50/50 dark:border-gray-800 dark:bg-gray-900/50">
                    <div className="flex items-center gap-2.5">
                      <ShoppingBag className="h-4 w-4 text-blue-500" />
                      <div>
                        <p className="text-xs font-semibold text-gray-900 dark:text-white">Produk &amp; Layanan Lain</p>
                        <p className="text-[10px] text-gray-500 dark:text-gray-400">Tampilkan hardware &amp; jasa instalasi</p>
                      </div>
                    </div>
                    <Switch
                      checked={form.data.landing_show_products}
                      onChange={(v) => form.setData("landing_show_products", v)}
                    />
                  </div>
                </div>
              </div>
            )}
          </div>

          {/* CARD 4: INTEGRASI NOTIFIKASI TELEGRAM BOT */}
          <div className="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xs dark:border-gray-800 dark:bg-white/[0.03]">
            <button
              type="button"
              onClick={() => toggleSection("telegram")}
              className="w-full p-4 sm:p-5 flex items-center justify-between gap-3 text-left hover:bg-gray-50/50 dark:hover:bg-white/[0.02] transition-colors"
            >
              <div className="flex items-center gap-3 min-w-0">
                <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-purple-50 text-purple-600 dark:bg-purple-500/10 dark:text-purple-400">
                  <Bot className="h-4 w-4" />
                </div>
                <div className="min-w-0">
                  <h3 className="text-sm font-bold text-gray-900 dark:text-white truncate">
                    Bot Telegram &amp; Notifikasi Pesanan (ACC 1-Klik)
                  </h3>
                  <p className="text-xs text-gray-500 dark:text-gray-400 truncate">
                    Integrasi notifikasi pesanan masuk, ACC/Tolak voucher via bot Telegram atau lewat aplikasi.
                  </p>
                </div>
              </div>
              <div className="shrink-0 p-1">
                <ChevronDown
                  className={cn(
                    "h-4 w-4 text-gray-400 transition-transform duration-200",
                    openSections.telegram && "rotate-180 text-gray-700 dark:text-gray-200"
                  )}
                />
              </div>
            </button>

            {openSections.telegram && (
              <div className="px-4 pb-5 sm:px-5 border-t border-gray-100 dark:border-gray-800 pt-4 space-y-4">
                <div className="flex items-center justify-between p-4 rounded-xl border border-gray-200 bg-gray-50/50 dark:border-gray-800 dark:bg-gray-900/50">
                  <div className="space-y-0.5 pr-3">
                    <div className="flex items-center gap-2">
                      <Bot className="h-4 w-4 text-emerald-500" />
                      <p className="text-xs font-bold text-gray-900 dark:text-white">Kirim Pesanan ke Telegram (ACC 1-Klik)</p>
                    </div>
                    <p className="text-[11px] text-gray-500 dark:text-gray-400">
                      Otomatis kirim rincian pesanan baru dan voucher ke Bot Telegram untuk di-ACC atau ditolak 1-klik dari Telegram. Jika dimatikan, Anda dapat memproses pesanan langsung dari dashboard aplikasi.
                    </p>
                  </div>
                  <Switch
                    checked={form.data.telegram_order_notif}
                    onChange={(v) => form.setData("telegram_order_notif", v)}
                  />
                </div>

                <div className="rounded-xl border border-brand-200 bg-brand-50 dark:border-brand-900/40 dark:bg-brand-950/20 p-4 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                  <div className="flex items-start gap-3">
                    <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-brand-500/10 text-brand-500 dark:text-brand-400">
                      <Send className="h-4 w-4" />
                    </div>
                    <div className="space-y-1">
                      <h4 className="text-xs font-bold text-gray-900 dark:text-white">
                        Pengaturan Bot Telegram &amp; NMS Monitoring Telah Dipindah
                      </h4>
                      <p className="text-[11px] text-gray-600 dark:text-gray-300 leading-relaxed">
                        Konfigurasi Token Bot Telegram, Chat ID, Group Topic, serta Notifikasi Jaringan NMS (Router, PPPoE, Hotspot, ARP Offline/Online) kini dikelola secara mandiri pada menu khusus.
                      </p>
                    </div>
                  </div>

                  <Link
                    href="/admin/telegram"
                    className="inline-flex items-center gap-2 shrink-0 px-4 py-2 rounded-xl bg-brand-500 hover:bg-brand-600 text-xs font-bold text-white transition-all shadow-xs active:scale-95"
                  >
                    <span>Buka Pengaturan Telegram</span>
                    <ExternalLink className="h-3.5 w-3.5" />
                  </Link>
                </div>
              </div>
            )}
          </div>
        </form>
      </div>
    </AppLayout>
  )
}