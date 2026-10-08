<?php
/**
 * MIKHMON by NODERA Engine — Tentang & Informasi Sistem
 * Platform Manajemen Hotspot MikroTik & Billing Terintegrasi
 * panel.dgtlnetsolution.com
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
error_reporting(0);

include_once __DIR__ . '/../lang/isocodelang.php';
$langid = !empty($_SESSION['lang']) ? $_SESSION['lang'] : '';
if (empty($langid) || empty($isocodelang[$langid])) {
    if (file_exists(__DIR__ . '/lang.php')) {
        include __DIR__ . '/lang.php';
    }
    if (empty($langid) || empty($isocodelang[$langid])) {
        $langid = 'id';
    }
}
if (!file_exists(__DIR__ . '/../lang/' . $langid . '.php')) {
    $langid = 'id';
}
include_once __DIR__ . '/../lang/' . $langid . '.php';

include_once __DIR__ . '/license.php';

$expiryText = function_exists('mikhmon_get_license_expiry_text') ? mikhmon_get_license_expiry_text() : 'Aktif';
$isExpired = function_exists('mikhmon_is_expired') ? mikhmon_is_expired() : false;
$subdomain = defined('MIKHMON_SUBDOMAIN') ? MIKHMON_SUBDOMAIN : basename(dirname(__DIR__));
$brandName = defined('MIKHMON_BRAND') ? MIKHMON_BRAND : 'by panel.dgtlnetsolution.com';
$isDesktop = function_exists('mikhmon_is_desktop_mode') ? mikhmon_is_desktop_mode() : false;
$licenseKey = defined('MIKHMON_LICENSE_KEY') ? MIKHMON_LICENSE_KEY : '';
$isTrial = (defined('MIKHMON_PRODUCT_NAME') && stripos(MIKHMON_PRODUCT_NAME, 'Trial') !== false) || (strpos($licenseKey, 'NDR-TRL') === 0);

$aboutLang = [
    "id" => [
        "hero_title" => "MIKHMON by NODERA Engine",
        "hero_sub" => "Edisi Khusus Terintegrasi Platform Billing ISP & Manajemen Hotspot MikroTik <b>panel.dgtlnetsolution.com</b>.",
        "f1_title" => "Dual Core ROS 6 & ROS 7",
        "f1_desc" => "Dukungan penuh MikroTik RouterOS v6 dan v7 API secara simultan tanpa kendala enkripsi.",
        "f2_title" => "Cetak Cepat & QR Code",
        "f2_desc" => "Cetak thermal 58mm/80mm & lembaran A4. Login instan pelanggan via pindai QR Code.",
        "f3_title" => "Laporan & Rekap Realtime",
        "f3_desc" => "Pencatatan omzet voucher harian, mingguan, bulanan, dan grafik performa per paket.",
        "f4_title" => "Live Traffic & Diagnostik",
        "f4_desc" => "Pantau bandwidth real-time, sewa DHCP leases, dan daftar pengguna aktif di router.",
        "f5_title" => "Otomasi & Proteksi Cloud",
        "f5_desc" => "Sinkronisasi cloud lisensi terpadu dengan perlindungan keamanan session & brute-force.",
        "f6_title" => "Multi-Tier Agen Reseller",
        "f6_desc" => "Sistem cetak mandiri mitra dengan deposit saldo & integrasi billing NODERA.",
        "info_system" => "Informasi Sistem & Lingkungan",
        "info_credits" => "Kredit & Lisensi Terbuka",
        "engine_build" => "Versi Engine MIKHMON",
        "os_platform" => "Platform Sistem Operasi",
        "php_runtime" => "PHP Runtime",
        "time_zone" => "Zona Waktu Aktif",
        "license_title" => "Lisensi Software",
        "original_author" => "Author Asli MIKHMON",
        "mod_maintainer" => "Pengembang & Integrasi",
        "community" => "Komunitas & Bantuan",
    ],
    "en" => [
        "hero_title" => "MIKHMON by NODERA Engine",
        "hero_sub" => "Special Edition Integrated ISP Billing Platform & MikroTik Hotspot Management <b>panel.dgtlnetsolution.com</b>.",
        "f1_title" => "Dual Core ROS 6 & ROS 7",
        "f1_desc" => "Full simultaneous support for MikroTik RouterOS v6 & v7 API with modern TLS encryption.",
        "f2_title" => "Quick Print & QR Code",
        "f2_desc" => "58mm/80mm thermal receipt & A4 sheet printing. Instant customer login via QR scan.",
        "f3_title" => "Real-Time Sales Reports",
        "f3_desc" => "Daily, weekly, and monthly revenue tracking with package performance charts.",
        "f4_title" => "Live Traffic & Diagnostics",
        "f4_desc" => "Real-time bandwidth monitoring, active DHCP leases, and online hotspot users.",
        "f5_title" => "Cloud Sync & Protection",
        "f5_desc" => "Unified cloud license synchronization with brute-force prevention and session security.",
        "f6_title" => "Multi-Tier Reseller Agents",
        "f6_desc" => "Agent self-service voucher printing with wallet deposit and NODERA billing integration.",
        "info_system" => "System & Environment Information",
        "info_credits" => "Credits & Open Source Licenses",
        "engine_build" => "MIKHMON Engine Version",
        "os_platform" => "Operating System Platform",
        "php_runtime" => "PHP Runtime",
        "time_zone" => "Active Timezone",
        "license_title" => "Software License",
        "original_author" => "Original Author of MIKHMON",
        "mod_maintainer" => "Developer & Integration",
        "community" => "Community & Support",
    ],
    "fr" => [
        "hero_title" => "Moteur MIKHMON par NODERA",
        "hero_sub" => "Édition Spéciale Intégrée Plateforme de Facturation FAI & Gestion Hotspot MikroTik <b>panel.dgtlnetsolution.com</b>.",
        "f1_title" => "Dual Core ROS 6 & ROS 7",
        "f1_desc" => "Prise en charge simultanée complète de l'API MikroTik RouterOS v6 et v7 avec chiffrement TLS.",
        "f2_title" => "Impression Rapide & Code QR",
        "f2_desc" => "Impression thermique 58mm/80mm et feuilles A4. Connexion instantanée client par scan QR.",
        "f3_title" => "Rapports des Ventes en Direct",
        "f3_desc" => "Suivi des revenus quotidiens, hebdomadaires et mensuels avec graphiques de performance.",
        "f4_title" => "Trafic en Direct & Diagnostics",
        "f4_desc" => "Surveillance de la bande passante en temps réel, baux DHCP actifs et utilisateurs connectés.",
        "f5_title" => "Sync Cloud & Sécurité",
        "f5_desc" => "Synchronisation unifiée des licences cloud avec protection brute-force et sécurité des sessions.",
        "f6_title" => "Réseau d'Agents & Revendeurs",
        "f6_desc" => "Impression autonome de coupons pour partenaires avec solde prépayé et intégration NODERA.",
        "info_system" => "Informations Système & Environnement",
        "info_credits" => "Crédits & Licences Open Source",
        "engine_build" => "Version du Moteur MIKHMON",
        "os_platform" => "Système d'Exploitation",
        "php_runtime" => "Runtime PHP",
        "time_zone" => "Fuseau Horaire Actif",
        "license_title" => "Licence Logicielle",
        "original_author" => "Auteur Original de MIKHMON",
        "mod_maintainer" => "Développeur & Intégration",
        "community" => "Communauté & Assistance",
    ],
    "es" => [
        "hero_title" => "Motor MIKHMON por NODERA",
        "hero_sub" => "Edición Especial Integrada Plataforma de Facturación ISP y Gestión Hotspot MikroTik <b>panel.dgtlnetsolution.com</b>.",
        "f1_title" => "Dual Core ROS 6 y ROS 7",
        "f1_desc" => "Soporte simultáneo completo para la API de MikroTik RouterOS v6 y v7 con cifrado moderno.",
        "f2_title" => "Impresión Rápida y Código QR",
        "f2_desc" => "Impresión térmica de 58mm/80mm y hojas A4. Inicio de sesión instantáneo mediante código QR.",
        "f3_title" => "Informes de Ventas en Vivo",
        "f3_desc" => "Seguimiento de ingresos diarios, semanales y mensuales con gráficos de rendimiento.",
        "f4_title" => "Tráfico en Vivo y Diagnóstico",
        "f4_desc" => "Monitoreo de ancho de banda en tiempo real, arrendamientos DHCP y usuarios activos.",
        "f5_title" => "Sincronización Cloud y Protección",
        "f5_desc" => "Sincronización unificada de licencias en la nube con prevención de fuerza bruta.",
        "f6_title" => "Red de Agentes Revendedores",
        "f6_desc" => "Impresión de fichas para socios con saldo recargable e integración de facturación NODERA.",
        "info_system" => "Información del Sistema y Entorno",
        "info_credits" => "Créditos y Licencias de Código Abierto",
        "engine_build" => "Versión del Motor MIKHMON",
        "os_platform" => "Sistema Operativo",
        "php_runtime" => "Tiempo de Ejecución PHP",
        "time_zone" => "Zona Horaria Activa",
        "license_title" => "Licencia de Software",
        "original_author" => "Autor Original de MIKHMON",
        "mod_maintainer" => "Desarrollador e Integración",
        "community" => "Comunidad y Soporte",
    ],
    "tl" => [
        "hero_title" => "MIKHMON ng NODERA Engine",
        "hero_sub" => "Espesyal na Edisyon Pinagsamang ISP Billing Platform at MikroTik Hotspot Management <b>panel.dgtlnetsolution.com</b>.",
        "f1_title" => "Dual Core ROS 6 & ROS 7",
        "f1_desc" => "Buong sabay-sabay na suporta para sa MikroTik RouterOS v6 at v7 API na may modernong TLS.",
        "f2_title" => "Mabilisang Pag-print & QR Code",
        "f2_desc" => "58mm/80mm thermal receipt at A4 sheet printing. Mabilisang pag-login ng customer gamit ang QR.",
        "f3_title" => "Ulat ng Benta sa Real-Time",
        "f3_desc" => "Araw-araw, lingguhan, at buwanang kita na may mga chart ng pagganap ng pakete.",
        "f4_title" => "Live Traffic at Diagnostic",
        "f4_desc" => "Pagsubaybay sa bandwidth, aktibong DHCP leases, at mga konektadong gumagamit ng hotspot.",
        "f5_title" => "Cloud Sync at Proteksyon",
        "f5_desc" => "Pinag-isang cloud license sync na may proteksyon laban sa brute-force at seguridad sa session.",
        "f6_title" => "Multi-Tier Reseller Agents",
        "f6_desc" => "Sariling pag-print ng voucher para sa mga ahente na may deposito at NODERA billing integration.",
        "info_system" => "Impormasyon sa Sistema at Kapaligiran",
        "info_credits" => "Mga Kredito at Open Source License",
        "engine_build" => "Bersyon ng MIKHMON Engine",
        "os_platform" => "Operating System",
        "php_runtime" => "PHP Runtime",
        "time_zone" => "Aktibong Timezone",
        "license_title" => "Lisensya ng Software",
        "original_author" => "Orihinal na May-akda ng MIKHMON",
        "mod_maintainer" => "Tagabuo at Integrasyon",
        "community" => "Komunidad at Suporta",
    ],
];
$aTxt = $aboutLang[$langid] ?? $aboutLang['id'];
?>

<style>
.about-grid-features {
  display: grid;
  grid-template-columns: 1fr;
  gap: 12px;
  margin-bottom: 20px;
}
@media (min-width: 640px) {
  .about-grid-features {
    grid-template-columns: repeat(2, 1fr);
  }
}
@media (min-width: 992px) {
  .about-grid-features {
    grid-template-columns: repeat(3, 1fr);
  }
}

.about-feature-card {
  display: flex;
  align-items: stretch;
  border-radius: 8px;
  border: 1px solid rgba(128, 128, 128, 0.2);
  background: rgba(128, 128, 128, 0.04);
  overflow: hidden;
  box-sizing: border-box;
  transition: transform 0.15s ease, box-shadow 0.15s ease;
}
.about-feature-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.about-feature-icon {
  width: 52px;
  min-height: 72px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 22px;
  color: #ffffff;
  flex-shrink: 0;
}

.about-feature-body {
  flex: 1;
  padding: 10px 14px;
  display: flex;
  flex-direction: column;
  justify-content: center;
  min-width: 0;
}
.about-feature-title {
  margin: 0 0 4px 0;
  font-size: 13.5px;
  font-weight: 700;
  line-height: 1.3;
}
.about-feature-desc {
  font-size: 11.5px;
  line-height: 1.45;
  opacity: 0.85;
}

.about-hero-box {
  border-radius: 8px;
  border: 1px solid rgba(128, 128, 128, 0.22);
  background: rgba(128, 128, 128, 0.04);
  padding: 16px;
  margin-bottom: 18px;
}

.about-tables-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 16px;
}
@media (min-width: 860px) {
  .about-tables-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}
</style>

<div class="row" style="padding-bottom: 90px;">
  <div class="col-12">
    <div class="card">
      <div class="card-header">
        <h3 class="card-title"><i class="fa fa-info-circle"></i> <?= !empty($_about) ? $_about : 'About'; ?> MIKHMON by NODERA</h3>
      </div>
      <div class="card-body">
        
        <!-- Hero Box -->
        <div class="about-hero-box">
          <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px; margin-bottom: 8px;">
            <div style="display: flex; align-items: center; gap: 8px;">
              <i class="fa fa-wifi text-primary" style="font-size: 20px;"></i>
              <h3 style="margin: 0; font-weight: 700; font-size: 17px;"><?= $aTxt['hero_title']; ?></h3>
            </div>
            <span class="badge bg-primary" style="font-size: 11px; padding: 4px 8px;">v3.20 (Dual Core ROS 6 &amp; ROS 7)</span>
          </div>
          <p style="margin: 0 0 12px 0; font-size: 13px; line-height: 1.5; opacity: 0.9;">
            <?= $aTxt['hero_sub']; ?>
          </p>
          <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; padding-top: 10px; border-top: 1px dashed rgba(128,128,128,0.25);">
            <div style="font-size: 12.5px; display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
              <?php if ($isDesktop): ?>
              <span>
                <b><?= $_type ?? 'Type'; ?> :</b> <?= $isTrial ? ($_trial_7_days ?? 'FREE (Trial 7 Days)') : ($_official_paid ?? 'Official Paid') ?> &bull; <b><?= $_validity ?? 'Active Period'; ?> :</b> <span><?= htmlspecialchars($expiryText); ?></span>
              </span>
              <?php endif; ?>
              <?php if (!empty($subdomain) && !$isDesktop): ?>
              <span>
                <b><?= $_active_subscription ?? 'Cloud Subscription'; ?> :</b> <span><?= htmlspecialchars($expiryText); ?></span> &bull; <b>Instance :</b> <code><?= htmlspecialchars($subdomain); ?></code>
              </span>
              <?php endif; ?>
            </div>
            <div>
              <a href="https://panel.dgtlnetsolution.com" target="_blank" rel="noopener noreferrer" class="btn bg-primary btn-sm">
                <i class="fa fa-external-link"></i> Portal panel.dgtlnetsolution.com
              </a>
            </div>
          </div>
        </div>

        <!-- Features Grid (1 Column on Mobile, 2 on Tablet, 3 on Desktop) -->
        <div class="about-grid-features">
          
          <div class="about-feature-card">
            <div class="about-feature-icon bg-blue"><i class="fa fa-microchip"></i></div>
            <div class="about-feature-body">
              <h4 class="about-feature-title"><?= $aTxt['f1_title']; ?></h4>
              <span class="about-feature-desc"><?= $aTxt['f1_desc']; ?></span>
            </div>
          </div>

          <div class="about-feature-card">
            <div class="about-feature-icon bg-green"><i class="fa fa-qrcode"></i></div>
            <div class="about-feature-body">
              <h4 class="about-feature-title"><?= $aTxt['f2_title']; ?></h4>
              <span class="about-feature-desc"><?= $aTxt['f2_desc']; ?></span>
            </div>
          </div>

          <div class="about-feature-card">
            <div class="about-feature-icon bg-yellow"><i class="fa fa-money"></i></div>
            <div class="about-feature-body">
              <h4 class="about-feature-title"><?= $aTxt['f3_title']; ?></h4>
              <span class="about-feature-desc"><?= $aTxt['f3_desc']; ?></span>
            </div>
          </div>

          <div class="about-feature-card">
            <div class="about-feature-icon bg-primary"><i class="fa fa-line-chart"></i></div>
            <div class="about-feature-body">
              <h4 class="about-feature-title"><?= $aTxt['f4_title']; ?></h4>
              <span class="about-feature-desc"><?= $aTxt['f4_desc']; ?></span>
            </div>
          </div>

          <div class="about-feature-card">
            <div class="about-feature-icon bg-red"><i class="fa fa-shield"></i></div>
            <div class="about-feature-body">
              <h4 class="about-feature-title"><?= $aTxt['f5_title']; ?></h4>
              <span class="about-feature-desc"><?= $aTxt['f5_desc']; ?></span>
            </div>
          </div>

          <div class="about-feature-card">
            <div class="about-feature-icon bg-secondary"><i class="fa fa-users"></i></div>
            <div class="about-feature-body">
              <h4 class="about-feature-title"><?= $aTxt['f6_title']; ?></h4>
              <span class="about-feature-desc"><?= $aTxt['f6_desc']; ?></span>
            </div>
          </div>

        </div>

        <!-- Details & Tables Grid (Stacked on Mobile, 2 Columns on Desktop) -->
        <div class="about-tables-grid">
          
          <div class="box box-bordered">
            <h4 style="margin-top: 0; padding-bottom: 8px; border-bottom: 1px solid rgba(128,128,128,0.2);"><i class="fa fa-server"></i> <?= $aTxt['info_system']; ?></h4>
            <table class="table table-sm" style="margin-bottom: 0;">
              <tbody>
                <tr>
                  <td style="width: 45%;"><strong><?= $aTxt['engine_build']; ?></strong></td>
                  <td>MIKHMON v3.20 (ROS 6 &amp; ROS 7 Dual Core)</td>
                </tr>
                <tr>
                  <td><strong><?= $aTxt['os_platform']; ?></strong></td>
                  <td><?= php_uname('s') . ' ' . php_uname('r'); ?></td>
                </tr>
                <tr>
                  <td><strong><?= $aTxt['php_runtime']; ?></strong></td>
                  <td>PHP <?= PHP_VERSION; ?> (<?= PHP_SAPI; ?>)</td>
                </tr>
                <tr>
                  <td><strong><?= $aTxt['time_zone']; ?></strong></td>
                  <td><?= date_default_timezone_get(); ?> (<?= date('d M Y, H:i:s'); ?>)</td>
                </tr>
                <tr>
                  <td><strong><?= $aTxt['license_title']; ?></strong></td>
                  <td>GNU GPL v2 + NODERA Proprietary Add-on Suite</td>
                </tr>
              </tbody>
            </table>
          </div>

          <div class="box box-bordered">
            <h4 style="margin-top: 0; padding-bottom: 8px; border-bottom: 1px solid rgba(128,128,128,0.2);"><i class="fa fa-heart"></i> <?= $aTxt['info_credits']; ?></h4>
            <table class="table table-sm" style="margin-bottom: 0;">
              <tbody>
                <tr>
                  <td style="width: 45%;"><strong><?= $aTxt['original_author']; ?></strong></td>
                  <td>Laksamadi Guko (Laksa19)</td>
                </tr>
                <tr>
                  <td><strong><?= $aTxt['mod_maintainer']; ?></strong></td>
                  <td>NODERA Developer Team (panel.dgtlnetsolution.com)</td>
                </tr>
                <tr>
                  <td><strong>RouterOS API Engine</strong></td>
                  <td>Denis Basta (RouterOS API PHP Class)</td>
                </tr>
                <tr>
                  <td><strong>Libraries &amp; Icons</strong></td>
                  <td>Font Awesome 4.7, jQuery 3.3.1, ApexCharts</td>
                </tr>
                <tr>
                  <td><strong><?= $aTxt['community']; ?></strong></td>
                  <td><a href="https://t.me/mikhmon" target="_blank" rel="noopener noreferrer">Grup Komunitas Telegram Mikhmon</a></td>
                </tr>
              </tbody>
            </table>
          </div>

        </div>

      </div>
    </div>
  </div>
</div>
