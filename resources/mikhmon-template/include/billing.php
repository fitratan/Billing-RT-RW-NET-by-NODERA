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
 * MIKHMON by NODERA Engine — Informasi Nodera Billing Cloud
 * Platform Billing ISP, Manajemen Pelanggan PPPoE & Hotspot Terintegrasi
 * panel.dgtlnetsolution.com
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
.billing-page-container {
  padding: 4px 0 28px 0;
  box-sizing: border-box;
  width: 100%;
}
.billing-page-container .card {
  border-radius: 12px;
  box-shadow: 0 4px 18px rgba(0,0,0,0.06);
  border: 1px solid rgba(128,128,128,0.18);
  margin-bottom: 24px !important;
  overflow: hidden;
}
.billing-page-container .card-header {
  padding: 16px 22px !important;
  border-bottom: 1px solid rgba(128,128,128,0.16);
  display: flex !important;
  justify-content: space-between !important;
  align-items: center !important;
  flex-wrap: wrap !important;
  gap: 12px !important;
}
.billing-page-container .card-header h3 {
  font-size: 16px !important;
  font-weight: 700 !important;
  margin: 0 !important;
  display: flex !important;
  align-items: center !important;
  gap: 10px !important;
}
.billing-page-container .card-body {
  padding: 22px 24px !important;
  box-sizing: border-box;
}

/* Hero Box */
.billing-hero-box {
  background: var(--box-bg, rgba(255,255,255,0.03));
  border: 1px solid var(--border-color, rgba(128,128,128,0.22));
  border-radius: 12px;
  padding: 22px 24px;
  margin: 0 0 26px 0 !important;
  box-sizing: border-box;
}
.billing-hero-header {
  display: flex;
  align-items: flex-start;
  gap: 18px;
}
.billing-hero-icon {
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
.billing-hero-body {
  flex: 1 1 auto;
  min-width: 0;
}
.billing-hero-title {
  font-size: 18.5px;
  font-weight: 800;
  margin: 0 0 8px 0;
  letter-spacing: -0.01em;
}
.billing-hero-desc {
  font-size: 13.5px;
  line-height: 1.65;
  opacity: 0.9;
  margin: 8px 0 16px 0;
}
.billing-hero-actions {
  margin-top: 14px;
  padding-top: 14px;
  border-top: 1px solid rgba(128,128,128,0.18);
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 12px;
}
.billing-action-btn {
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
.billing-action-btn:hover {
  opacity: 0.92;
  transform: translateY(-1px);
}

/* Section Heading */
.billing-section-heading {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px 18px;
  margin: 28px 0 18px 0;
  border-radius: 8px;
  background: var(--header-bg, rgba(128,128,128,0.08));
  border-left: 4px solid #3b82f6;
}
.billing-section-heading h3 {
  font-size: 14.5px;
  font-weight: 700;
  margin: 0;
}

/* Features Grid */
.billing-features-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 18px;
  margin-bottom: 28px;
}
.billing-feature-box {
  background: var(--box-bg, rgba(255,255,255,0.025));
  border: 1px solid rgba(128,128,128,0.2);
  border-radius: 10px;
  padding: 20px 22px;
  display: flex;
  flex-direction: column;
  height: 100%;
  box-sizing: border-box;
}
.billing-feature-head {
  display: flex;
  align-items: center;
  gap: 14px;
  margin-bottom: 12px;
}
.billing-feature-icon {
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
.billing-feature-title {
  font-size: 13.5px;
  font-weight: 700;
  line-height: 1.35;
  margin: 0;
}
.billing-feature-text {
  font-size: 12.5px;
  line-height: 1.6;
  opacity: 0.88;
  margin: 0;
  flex: 1 1 auto;
}

/* Steps Box */
.billing-steps-box {
  background: var(--box-bg, rgba(255,255,255,0.025));
  border: 1px solid rgba(128,128,128,0.2);
  border-radius: 10px;
  padding: 20px 22px;
  box-sizing: border-box;
}
.billing-steps-box-header {
  font-size: 14px;
  font-weight: 700;
  margin: 0 0 16px 0;
  padding-bottom: 12px;
  border-bottom: 1px solid rgba(128,128,128,0.18);
  display: flex;
  align-items: center;
  gap: 10px;
}
.billing-steps-table {
  width: 100%;
  border-collapse: separate;
  border-spacing: 0;
  font-size: 12.5px;
  border: 1px solid rgba(128,128,128,0.16);
  border-radius: 8px;
  overflow: hidden;
}
.billing-steps-table td {
  padding: 12px 14px !important;
  line-height: 1.55;
  vertical-align: middle;
  border-bottom: 1px solid rgba(128,128,128,0.12);
}
.billing-steps-table tr:last-child td {
  border-bottom: none;
}
.billing-steps-table td:first-child {
  width: 120px;
  min-width: 100px;
  font-weight: 600;
  background: rgba(128,128,128,0.05);
  border-right: 1px solid rgba(128,128,128,0.12);
  vertical-align: middle;
  text-align: center;
}
.billing-highlight-banner {
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
  .billing-features-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}
@media screen and (max-width: 768px) {
  .billing-page-container {
    padding: 2px 0 20px 0;
  }
  .billing-page-container .card {
    border-radius: 8px;
    margin: 6px auto 18px auto !important;
    width: calc(100% - 12px) !important;
    max-width: calc(100% - 12px) !important;
  }
  .billing-page-container .card-header {
    padding: 14px 16px !important;
  }
  .billing-page-container .card-body {
    padding: 16px 14px !important;
  }
  .billing-hero-box {
    padding: 16px 14px;
    margin-bottom: 20px !important;
    border-radius: 10px;
  }
  .billing-hero-header {
    flex-direction: column;
    gap: 12px;
  }
  .billing-hero-icon {
    width: 42px;
    height: 42px;
    min-width: 42px;
    font-size: 19px;
  }
  .billing-hero-title {
    font-size: 16.5px;
  }
  .billing-hero-actions {
    flex-direction: column;
    align-items: stretch;
    gap: 10px;
  }
  .billing-action-btn {
    width: 100%;
    justify-content: center;
    padding: 9px 14px;
    box-sizing: border-box;
  }
  .billing-section-heading {
    margin: 22px 0 14px 0;
    padding: 10px 14px;
  }
  .billing-features-grid {
    grid-template-columns: 1fr;
    gap: 14px;
    margin-bottom: 20px;
  }
  .billing-feature-box {
    padding: 16px 16px;
  }
  .billing-steps-box {
    padding: 16px 16px;
  }
  .billing-steps-table td {
    padding: 9px 10px !important;
    font-size: 12px;
  }
  .billing-steps-table td:first-child {
    width: 85px;
    min-width: 75px;
  }
  .billing-highlight-banner {
    flex-direction: column;
    align-items: stretch;
    gap: 12px;
    padding: 12px 14px;
  }
}
</style>

<div class="row billing-page-container">
  <div class="col-12">
    <div class="card">
      <div class="card-header">
        <h3><i class="fa fa-cloud text-primary"></i> <?= $_billing_cloud_title ?? "Nodera Billing Cloud — Manajemen ISP & Hotspot"; ?></h3>
        <span class="badge bg-primary" style="font-size: 11.5px; padding: 5px 10px; font-weight: 600;"><?= $_billing_integrated_badge ?? "Platform Terintegrasi"; ?></span>
      </div>
      <div class="card-body">
        
        <!-- Hero Box -->
        <div class="box box-bordered billing-hero-box">
          <div class="billing-hero-header">
            <div class="billing-hero-icon bg-primary">
              <i class="fa fa-rocket"></i>
            </div>
            <div class="billing-hero-body">
              <h2 class="billing-hero-title"><?= $_billing_cloud_subtitle ?? "Kelola Bisnis RT/RW Net & ISP Lebih Praktis, Rapi, dan Otomatis"; ?></h2>
              <p class="billing-hero-desc"><?= $_billing_hero_desc ?? "<b>Nodera Billing</b> adalah platform SaaS billing terintegrasi."; ?></p>
              <div class="billing-hero-actions">
                <a href="https://panel.dgtlnetsolution.com/register" target="_blank" rel="noopener noreferrer" class="btn bg-primary billing-action-btn">
                  <i class="fa fa-user-plus"></i> <?= $_billing_register_account ?? "Daftar Akun di panel.dgtlnetsolution.com"; ?>
                </a>
                <a href="https://panel.dgtlnetsolution.com/register" target="_blank" rel="noopener noreferrer" class="btn bg-secondary billing-action-btn">
                  <i class="fa fa-external-link"></i> <?= $_billing_portal_btn ?? "Portal Billing"; ?>
                </a>
                <a href="https://wa.me/6285155173547?text=Halo%20Admin%20NODERA,%20saya%20ingin%20mendaftar%20dan%20berlangganan%20Nodera%20Billing%20Cloud" target="_blank" rel="noopener noreferrer" class="btn bg-green billing-action-btn">
                  <i class="fa fa-whatsapp"></i> <?= $_billing_contact_admin_wa ?? "Hubungi Admin (WhatsApp)"; ?>
                </a>
              </div>
            </div>
          </div>
        </div>

        <!-- Section Heading -->
        <div class="billing-section-heading">
          <h3><i class="fa fa-star text-yellow"></i> <?= $_billing_features ?? "Fitur Unggulan Nodera Billing Cloud"; ?></h3>
        </div>

        <!-- Features Grid -->
        <div class="billing-features-grid">
          <div class="billing-feature-box">
            <div class="billing-feature-head">
              <div class="billing-feature-icon bg-blue"><i class="fa fa-bolt"></i></div>
              <h4 class="billing-feature-title"><?= $_billing_feat_auto_isolir ?? "Auto-Isolir &amp; Auto-Aktif"; ?></h4>
            </div>
            <p class="billing-feature-text"><?= $_billing_feat_auto_isolir_desc ?? "Otomatisasi pemutusan akses pelanggan saat jatuh tempo."; ?></p>
          </div>

          <div class="billing-feature-box">
            <div class="billing-feature-head">
              <div class="billing-feature-icon bg-green"><i class="fa fa-whatsapp"></i></div>
              <h4 class="billing-feature-title"><?= $_billing_feat_wa_gateway ?? "WhatsApp Gateway Otomatis"; ?></h4>
            </div>
            <p class="billing-feature-text"><?= $_billing_feat_wa_gateway_desc ?? "Kirim tagihan, invoice PDF, struk pembayaran ke WhatsApp pelanggan."; ?></p>
          </div>

          <div class="billing-feature-box">
            <div class="billing-feature-head">
              <div class="billing-feature-icon bg-yellow"><i class="fa fa-credit-card"></i></div>
              <h4 class="billing-feature-title"><?= $_billing_feat_payment_gateway ?? "Payment Gateway 24/7"; ?></h4>
            </div>
            <p class="billing-feature-text"><?= $_billing_feat_payment_gateway_desc ?? "Terima pembayaran via QRIS real-time, Virtual Account Bank."; ?></p>
          </div>

          <div class="billing-feature-box">
            <div class="billing-feature-head">
              <div class="billing-feature-icon bg-primary"><i class="fa fa-server"></i></div>
              <h4 class="billing-feature-title"><?= $_billing_feat_multi_router ?? "Multi-Router &amp; VPN Remote"; ?></h4>
            </div>
            <p class="billing-feature-text"><?= $_billing_feat_multi_router_desc ?? "Kelola puluhan MikroTik dari lokasi berbeda tanpa IP Publik statis."; ?></p>
          </div>

          <div class="billing-feature-box">
            <div class="billing-feature-head">
              <div class="billing-feature-icon bg-secondary"><i class="fa fa-users"></i></div>
              <h4 class="billing-feature-title"><?= $_billing_feat_reseller ?? "Reseller &amp; Teknisi Lapangan"; ?></h4>
            </div>
            <p class="billing-feature-text"><?= $_billing_feat_reseller_desc ?? "Kelola hak akses kasir, kolektor tagihan lapangan, saldo deposit."; ?></p>
          </div>

          <div class="billing-feature-box">
            <div class="billing-feature-head">
              <div class="billing-feature-icon bg-red"><i class="fa fa-line-chart"></i></div>
              <h4 class="billing-feature-title"><?= $_billing_feat_reports ?? "Laporan Finansial Lengkap"; ?></h4>
            </div>
            <p class="billing-feature-text"><?= $_billing_feat_reports_desc ?? "Rekapitulasi omzet harian/bulanan, arus kas keluar-masuk."; ?></p>
          </div>
        </div>

        <!-- How to Get Started Box -->
        <div class="billing-steps-box">
          <div class="billing-steps-box-header">
            <i class="fa fa-list-ol text-primary"></i> <?= $_billing_steps_title ?? "Cara Mendaftar &amp; Menggunakan Nodera Billing"; ?>
          </div>
          <table class="billing-steps-table">
            <tbody>
              <tr>
                <td><span class="badge bg-primary" style="padding: 4px 8px; font-size: 11px;"><?= $_billing_step1_badge ?? "Langkah 1"; ?></span></td>
                <td><?= $_billing_step1_text ?? "<b>Daftar Akun :</b> Buka portal panel.dgtlnetsolution.com dan buat akun baru bisnis ISP Anda."; ?></td>
              </tr>
              <tr>
                <td><span class="badge bg-primary" style="padding: 4px 8px; font-size: 11px;"><?= $_billing_step2_badge ?? "Langkah 2"; ?></span></td>
                <td><?= $_billing_step2_text ?? "<b>Integrasikan MikroTik :</b> Tambahkan router MikroTik Anda menggunakan VPN Remote & API Key."; ?></td>
              </tr>
              <tr>
                <td><span class="badge bg-primary" style="padding: 4px 8px; font-size: 11px;"><?= $_billing_step3_badge ?? "Langkah 3"; ?></span></td>
                <td><?= $_billing_step3_text ?? "<b>Input Paket & Pelanggan :</b> Impor atau input data pelanggan PPPoE / Hotspot Anda."; ?></td>
              </tr>
              <tr>
                <td><span class="badge bg-primary" style="padding: 4px 8px; font-size: 11px;"><?= $_billing_step4_badge ?? "Langkah 4"; ?></span></td>
                <td><?= $_billing_step4_text ?? "<b>Otomatisasi Berjalan :</b> Sistem langsung mulai mengirimkan tagihan WhatsApp dan memproses pembayaran."; ?></td>
              </tr>
            </tbody>
          </table>

          <div class="billing-highlight-banner">
            <span style="font-size: 13px;"><i class="fa fa-info-circle text-primary"></i> <?= $_billing_help_banner ?? "<b>Butuh panduan integrasi?</b> Tim teknis kami siap membantu sampai router Anda online."; ?></span>
            <a href="https://panel.dgtlnetsolution.com/register" target="_blank" rel="noopener noreferrer" class="btn bg-primary billing-action-btn">
              <i class="fa fa-sign-in"></i> <?= $_billing_to_portal_btn ?? "Menuju Portal Billing"; ?>
            </a>
          </div>
        </div>

      </div>
    </div>
  </div>
</div>
