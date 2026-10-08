<?php
// Load language
$langid = $_SESSION['lang'] ?? $_COOKIE['mikhmon_lang'] ?? '';
if (empty($langid)) {
    if (file_exists(__DIR__ . '/../include/lang.php')) {
        include __DIR__ . '/../include/lang.php';
    } elseif (file_exists(__DIR__ . '/lang.php')) {
        include __DIR__ . '/lang.php';
    }
}
if (empty($langid)) {
    $langid = 'id';
}
if (file_exists(__DIR__ . '/../lang/' . $langid . '.php')) {
    include __DIR__ . '/../lang/' . $langid . '.php';
} elseif (file_exists(__DIR__ . '/lang/' . $langid . '.php')) {
    include __DIR__ . '/lang/' . $langid . '.php';
}

/**
 * MIKHMON by NODERA Engine — Informasi Toko Hardware & Perlengkapan Jaringan
 * shop.dgtlnetsolution.com
 */
if (session_status() === PHP_SESSION_NONE) {
    @ini_set('session.cookie_path', '/');
    @session_start();
}
// hide all error
error_reporting(0);
if (!isset($_SESSION["mikhmon"]) && empty($_COOKIE['mikhmon_remember'])) {
    header("Location:./admin.php?id=login");
    exit;
}

$subdomain = defined('MIKHMON_SUBDOMAIN') ? MIKHMON_SUBDOMAIN : basename(dirname(__DIR__));
?>

<style>
.shop-page-container {
  padding: 4px 0 28px 0;
  box-sizing: border-box;
  width: 100%;
}
.shop-page-container .card {
  border-radius: 12px;
  box-shadow: 0 4px 18px rgba(0,0,0,0.06);
  border: 1px solid rgba(128,128,128,0.18);
  margin-bottom: 24px !important;
  overflow: hidden;
}
.shop-page-container .card-header {
  padding: 16px 22px !important;
  border-bottom: 1px solid rgba(128,128,128,0.16);
  display: flex !important;
  justify-content: space-between !important;
  align-items: center !important;
  flex-wrap: wrap !important;
  gap: 12px !important;
}
.shop-page-container .card-header h3 {
  font-size: 16px !important;
  font-weight: 700 !important;
  margin: 0 !important;
  display: flex !important;
  align-items: center !important;
  gap: 10px !important;
}
.shop-page-container .card-body {
  padding: 22px 24px !important;
  box-sizing: border-box;
}

/* Hero Box */
.shop-hero-box {
  background: var(--box-bg, rgba(255,255,255,0.03));
  border: 1px solid var(--border-color, rgba(128,128,128,0.22));
  border-radius: 12px;
  padding: 22px 24px;
  margin: 0 0 26px 0 !important;
  box-sizing: border-box;
}
.shop-hero-header {
  display: flex;
  align-items: flex-start;
  gap: 18px;
}
.shop-hero-icon {
  width: 48px;
  height: 48px;
  min-width: 48px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 22px;
  color: #ffffff;
  flex-shrink: 0;
  box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}
.shop-hero-body {
  flex: 1 1 auto;
  min-width: 0;
}
.shop-hero-title {
  font-size: 18.5px;
  font-weight: 800;
  margin: 0 0 8px 0;
  letter-spacing: -0.01em;
}
.shop-hero-desc {
  font-size: 13.5px;
  line-height: 1.65;
  opacity: 0.9;
  margin: 8px 0 16px 0;
}
.shop-hero-actions {
  margin-top: 14px;
  padding-top: 14px;
  border-top: 1px solid rgba(128,128,128,0.18);
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 12px;
}
.shop-action-btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 8px 18px;
  font-size: 12.5px;
  font-weight: 600;
  border-radius: 6px;
  text-decoration: none;
  transition: opacity 0.2s ease, transform 0.1s ease;
}
.shop-action-btn:hover {
  opacity: 0.92;
  transform: translateY(-1px);
}

/* Section Heading */
.shop-section-heading {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px 18px;
  margin: 28px 0 18px 0;
  border-radius: 8px;
  background: var(--header-bg, rgba(128,128,128,0.08));
  border-left: 4px solid #16a34a;
}
.shop-section-heading h3 {
  font-size: 14.5px;
  font-weight: 700;
  margin: 0;
}

/* Products Grid */
.shop-products-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 18px;
  margin-bottom: 28px;
}
.shop-product-box {
  background: var(--box-bg, rgba(255,255,255,0.025));
  border: 1px solid rgba(128,128,128,0.2);
  border-radius: 10px;
  padding: 20px 22px;
  display: flex;
  flex-direction: column;
  height: 100%;
  box-sizing: border-box;
}
.shop-product-head {
  display: flex;
  align-items: center;
  gap: 14px;
  margin-bottom: 12px;
}
.shop-product-icon {
  width: 38px;
  height: 38px;
  min-width: 38px;
  border-radius: 8px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 17px;
  color: #ffffff;
  flex-shrink: 0;
}
.shop-product-title {
  font-size: 13.5px;
  font-weight: 700;
  line-height: 1.35;
  margin: 0;
}
.shop-product-text {
  font-size: 12.5px;
  line-height: 1.6;
  opacity: 0.88;
  margin: 0;
  flex: 1 1 auto;
}

/* Info Box */
.shop-info-box {
  background: var(--box-bg, rgba(255,255,255,0.025));
  border: 1px solid rgba(128,128,128,0.2);
  border-radius: 10px;
  padding: 20px 22px;
  box-sizing: border-box;
}
.shop-info-box-header {
  font-size: 14px;
  font-weight: 700;
  margin: 0 0 16px 0;
  padding-bottom: 12px;
  border-bottom: 1px solid rgba(128,128,128,0.18);
  display: flex;
  align-items: center;
  gap: 10px;
}
.shop-info-table {
  width: 100%;
  border-collapse: separate;
  border-spacing: 0;
  font-size: 12.5px;
  border: 1px solid rgba(128,128,128,0.16);
  border-radius: 8px;
  overflow: hidden;
}
.shop-info-table td {
  padding: 11px 14px !important;
  line-height: 1.55;
  vertical-align: middle;
  border-bottom: 1px solid rgba(128,128,128,0.12);
}
.shop-info-table tr:last-child td {
  border-bottom: none;
}
.shop-info-table td:first-child {
  width: 130px;
  min-width: 110px;
  font-weight: 600;
  background: rgba(128,128,128,0.05);
  border-right: 1px solid rgba(128,128,128,0.12);
  vertical-align: top;
}
.shop-highlight-banner {
  margin-top: 18px;
  padding: 14px 18px;
  border-radius: 8px;
  background: rgba(128,128,128,0.08);
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 14px;
}

/* Mobile Responsiveness */
@media screen and (max-width: 900px) {
  .shop-products-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}
@media screen and (max-width: 768px) {
  .shop-page-container {
    padding: 2px 0 20px 0;
  }
  .shop-page-container .card {
    border-radius: 8px;
    margin: 6px auto 18px auto !important;
    width: calc(100% - 12px) !important;
    max-width: calc(100% - 12px) !important;
  }
  .shop-page-container .card-header {
    padding: 14px 16px !important;
  }
  .shop-page-container .card-body {
    padding: 16px 14px !important;
  }
  .shop-hero-box {
    padding: 16px 14px;
    margin-bottom: 20px !important;
    border-radius: 10px;
  }
  .shop-hero-header {
    flex-direction: column;
    gap: 12px;
  }
  .shop-hero-icon {
    width: 42px;
    height: 42px;
    min-width: 42px;
    font-size: 19px;
  }
  .shop-hero-title {
    font-size: 16.5px;
  }
  .shop-hero-actions {
    flex-direction: column;
    align-items: stretch;
    gap: 10px;
  }
  .shop-action-btn {
    width: 100%;
    justify-content: center;
    padding: 9px 14px;
    box-sizing: border-box;
  }
  .shop-section-heading {
    margin: 22px 0 14px 0;
    padding: 10px 14px;
  }
  .shop-products-grid {
    grid-template-columns: 1fr;
    gap: 14px;
    margin-bottom: 20px;
  }
  .shop-product-box {
    padding: 16px 16px;
  }
  .shop-info-box {
    padding: 16px 16px;
  }
  .shop-info-table td {
    padding: 9px 10px !important;
    font-size: 12px;
  }
  .shop-info-table td:first-child {
    width: 95px;
    min-width: 85px;
  }
  .shop-highlight-banner {
    flex-direction: column;
    align-items: stretch;
    gap: 12px;
    padding: 12px 14px;
  }
}
</style>

<div class="row shop-page-container">
  <div class="col-12">
    <div class="card">
      <div class="card-header">
        <h3><i class="fa fa-shopping-cart text-green"></i> <?= $_shop_title ?? "Nodera Hardware Shop — Toko Perangkat Jaringan & Kasir"; ?></h3>
        <span class="badge bg-green" style="font-size: 11.5px; padding: 5px 10px; font-weight: 600;"><?= $_shop_official_badge ?? "Hardware Resmi &amp; Teruji"; ?></span>
      </div>
      <div class="card-body">
        
        <!-- Hero Box -->
        <div class="box box-bordered shop-hero-box">
          <div class="shop-hero-header">
            <div class="shop-hero-icon bg-primary">
              <i class="fa fa-shopping-bag"></i>
            </div>
            <div class="shop-hero-body">
              <h2 class="shop-hero-title"><?= $_shop_subtitle ?? "Pengadaan Perangkat MikroTik, Printer Thermal, & Alat ISP"; ?></h2>
              <p class="shop-hero-desc"><?= $_shop_hero_desc ?? "<b>shop.dgtlnetsolution.com</b> menyediakan berbagai kebutuhan hardware jaringan teruji untuk operasional RT/RW Net dan ISP."; ?></p>
              <div class="shop-hero-actions">
                <a href="https://shop.dgtlnetsolution.com" target="_blank" rel="noopener noreferrer" class="btn bg-primary shop-action-btn">
                  <i class="fa fa-shopping-cart"></i> <?= $_shop_open_online_store ?? "Buka Toko Online shop.dgtlnetsolution.com"; ?>
                </a>
                <a href="https://wa.me/6285155173547?text=Halo%20Admin%20NODERA,%20saya%20ingin%20tanya%20stok%20dan%20pemesanan%20hardware%20jaringan" target="_blank" rel="noopener noreferrer" class="btn bg-green shop-action-btn">
                  <i class="fa fa-whatsapp"></i> <?= $_shop_contact_admin_wa ?? "Hubungi Admin (WhatsApp)"; ?>
                </a>
              </div>
            </div>
          </div>
        </div>

        <!-- Section Heading -->
        <div class="shop-section-heading">
          <h3><i class="fa fa-tags text-primary"></i> <?= $_shop_categories ?? "Kategori Produk Tersedia"; ?></h3>
        </div>

        <!-- Products Grid -->
        <div class="shop-products-grid">
          <div class="shop-product-box" style="border: 1px solid rgba(0, 229, 255, 0.4); background: rgba(0, 229, 255, 0.04); border-radius: 10px;">
            <div class="shop-product-head">
              <div class="shop-product-icon" style="background: #00E5FF; color: #0B1120;"><i class="fa fa-desktop"></i></div>
              <h4 class="shop-product-title" style="color: #00E5FF;"><?= $_shop_template_hotspot ?? "Template Hotspot MikroTik"; ?> <span class="badge bg-green" style="font-size: 10px; margin-left: 4px;"><?= $_badge_hot ?? "HOT"; ?></span></h4>
            </div>
            <p class="shop-product-text"><?= $_shop_template_hotspot_desc ?? "Suite 30 Template Login Page MikroTik premium."; ?></p>
            <div style="margin-top: 10px; padding-top: 8px; border-top: 1px solid rgba(0, 229, 255, 0.15);">
              <a href="https://shop.dgtlnetsolution.com?category=template-hotspot" target="_blank" rel="noopener noreferrer" class="btn btn-xs" style="background: #00E5FF; color: #0B1120; font-weight: 700; font-size: 11px; padding: 5px 12px; border-radius: 6px; text-decoration: none; display: inline-flex; align-items: center; gap: 5px;">
                <i class="fa fa-eye"></i> <?= $_shop_live_preview_buy ?? "Live Preview &amp; Beli &rarr;"; ?>
              </a>
            </div>
          </div>

          <div class="shop-product-box">
            <div class="shop-product-head">
              <div class="shop-product-icon bg-blue"><i class="fa fa-microchip"></i></div>
              <h4 class="shop-product-title"><?= $_shop_mikrotik_routers ?? "Routerboard MikroTik"; ?></h4>
            </div>
            <p class="shop-product-text"><?= $_shop_mikrotik_desc ?? "Seri MikroTik original dengan garansi resmi."; ?></p>
          </div>

          <div class="shop-product-box">
            <div class="shop-product-head">
              <div class="shop-product-icon bg-green"><i class="fa fa-print"></i></div>
              <h4 class="shop-product-title"><?= $_shop_thermal_printers ?? "Printer Thermal Voucher"; ?></h4>
            </div>
            <p class="shop-product-text"><?= $_shop_thermal_printers_desc ?? "Printer kasir thermal 58mm &amp; 80mm."; ?></p>
          </div>

          <div class="shop-product-box">
            <div class="shop-product-head">
              <div class="shop-product-icon bg-yellow"><i class="fa fa-file-text-o"></i></div>
              <h4 class="shop-product-title"><?= $_shop_thermal_paper ?? "Kertas & Stiker Thermal"; ?></h4>
            </div>
            <p class="shop-product-text"><?= $_shop_thermal_paper_desc ?? "Roll kertas thermal dan stiker barcode voucher berkualitas tinggi."; ?></p>
          </div>

          <div class="shop-product-box">
            <div class="shop-product-head">
              <div class="shop-product-icon bg-primary"><i class="fa fa-wifi"></i></div>
              <h4 class="shop-product-title"><?= $_shop_ap_wireless ?? "Access Point &amp; Wireless"; ?></h4>
            </div>
            <p class="shop-product-text"><?= $_shop_ap_wireless_desc ?? "Perangkat wireless indoor/outdoor high power."; ?></p>
          </div>

          <div class="shop-product-box">
            <div class="shop-product-head">
              <div class="shop-product-icon bg-secondary"><i class="fa fa-exchange"></i></div>
              <h4 class="shop-product-title"><?= $_shop_fiber_optic ?? "Fiber Optik &amp; ONT Modem"; ?></h4>
            </div>
            <p class="shop-product-text"><?= $_shop_fiber_optic_desc ?? "Kabel FO dropcore, ONT GPON/EPON modem, OLT."; ?></p>
          </div>

          <div class="shop-product-box">
            <div class="shop-product-head">
              <div class="shop-product-icon bg-red"><i class="fa fa-plug"></i></div>
              <h4 class="shop-product-title"><?= $_shop_mini_ups ?? "Mini UPS &amp; Aksesoris"; ?></h4>
            </div>
            <p class="shop-product-text"><?= $_shop_mini_ups_desc ?? "Mini UPS DC backup, adaptor PoE injector."; ?></p>
          </div>
        </div>

        <!-- Order & Delivery Box -->
        <div class="shop-info-box">
          <div class="shop-info-box-header">
            <i class="fa fa-truck text-primary"></i> <?= $_shop_shipping_order_info ?? "Informasi Pengiriman &amp; Pemesanan"; ?>
          </div>
          <table class="shop-info-table">
            <tbody>
              <tr>
                <td><b><?= $_shop_online_store_label ?? "Toko Online"; ?></b></td>
                <td><a href="https://shop.dgtlnetsolution.com" target="_blank" rel="noopener noreferrer" class="text-primary"><b>shop.dgtlnetsolution.com</b></a></td>
              </tr>
              <tr>
                <td><b><?= $_shop_payment_methods ?? "Metode Bayar"; ?></b></td>
                <td><?= $_shop_payment_methods_val ?? "Transfer Bank, QRIS Instant, E-Wallet, dan COD."; ?></td>
              </tr>
              <tr>
                <td><b><?= $_shop_courier ?? "Ekspedisi"; ?></b></td>
                <td><?= $_shop_courier_val ?? "J&T, JNE, SiCepat, Anteraja, Cargo."; ?></td>
              </tr>
              <tr>
                <td><b><?= $_shop_warranty ?? "Garansi"; ?></b></td>
                <td><?= $_shop_warranty_val ?? "Garansi ganti baru jika perangkat rusak saat pengiriman."; ?></td>
              </tr>
            </tbody>
          </table>

          <div class="shop-highlight-banner">
            <span style="font-size: 13px;"><i class="fa fa-shield text-green"></i> <b><?= $_shop_quality_banner ?? "Semua perangkat dicek dan diuji fungsinya sebelum dikirim."; ?></b></span>
            <a href="https://shop.dgtlnetsolution.com" target="_blank" rel="noopener noreferrer" class="btn bg-primary shop-action-btn">
              <i class="fa fa-shopping-cart"></i> <?= $_shop_shop_now ?? "Belanja Sekarang"; ?>
            </a>
          </div>
        </div>

      </div>
    </div>
  </div>
</div>
