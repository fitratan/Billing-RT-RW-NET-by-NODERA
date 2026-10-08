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

/*
 * Mikhmon Online Store Portal Theme & Element Customizer
 * Built for NODERA Multi-Tenant Platform
 * Supporting 16 Distinct Physical Layouts x Checkbox Display Element Customization
 * Pure Native Mikhmon UI Integration
 */

// hide all error
error_reporting(0);
if (!isset($_SESSION["mikhmon"])) {
  header("Location:./admin.php?id=login");
  exit;
}

// localization via lang dictionary

// Resolve session
$session = trim($_GET['session'] ?? ($_SESSION['mikhmon_session'] ?? ''));

$allSessions = [];
$configPath = __DIR__ . '/../include/config.php';
if (!file_exists($configPath)) $configPath = './include/config.php';
if (file_exists($configPath)) {
  @include($configPath);
  if (!empty($data) && is_array($data)) {
    foreach ($data as $k => $v) {
      if ($k !== 'mikhmon' && is_array($v)) {
        $allSessions[$k] = $v;
      }
    }
  }
}

$location_data = [];
$locConfigPath = __DIR__ . '/../include/location_config.php';
if (!file_exists($locConfigPath)) $locConfigPath = './include/location_config.php';
if (file_exists($locConfigPath)) {
  @include($locConfigPath);
}

$configFile = __DIR__ . '/../include/noderapay_config.php';
if (!file_exists($configFile)) {
  $configFile = "./include/noderapay_config.php";
}

$noderapay_data = [];
if (file_exists($configFile)) {
  @include($configFile);
}
if (!is_array($noderapay_data)) {
  $noderapay_data = [];
}

// Global store configuration
$cfg = (!empty($session) && isset($noderapay_data[$session]) && is_array($noderapay_data[$session])) 
  ? $noderapay_data[$session] 
  : ($noderapay_data['default'] ?? (is_array(reset($noderapay_data)) ? reset($noderapay_data) : []));

// Available 16 Distinct Themes Definition
$storeThemes = [
  'standard' => [
    'name'     => $_st_theme_standard_name ?? '1. Neo Cards (Standard Bento)',
    'category' => $_st_theme_standard_category ?? 'Gaya Bento & Grid',
    'tag'      => $_st_theme_standard_tag ?? 'Modern & Interaktif',
    'desc'     => $_st_theme_standard_desc ?? 'Layout grid 2 kolom modern dengan kartu rounded proporsional, status live, dan harga tegas di footer.',
    'icon'     => 'fa-th-large',
  ],
  'stripe' => [
    'name'     => $_st_theme_stripe_name ?? '2. Stripe Clean (List Minimalist)',
    'category' => $_st_theme_stripe_category ?? 'Gaya List Vertikal',
    'tag'      => $_st_theme_stripe_tag ?? 'Simpel & Cepat',
    'desc'     => $_st_theme_stripe_desc ?? 'Daftar paket vertikal elegan dengan radio selection ala Stripe Checkout, ideal untuk pilihan ringkas.',
    'icon'     => 'fa-list-ul',
  ],
  'linear' => [
    'name'     => $_st_theme_linear_name ?? '3. Linear Dark (Hero Featured)',
    'category' => $_st_theme_linear_category ?? 'Gaya Hero Unggulan',
    'tag'      => $_st_theme_linear_tag ?? 'High Conversion',
    'desc'     => $_st_theme_linear_desc ?? 'Menampilkan paket terlaris (Best Seller) secara megah di atas kartu lainnya untuk meningkatkan penjualan.',
    'icon'     => 'fa-star',
  ],
  'voucher' => [
    'name'     => $_st_theme_voucher_name ?? '4. Ticket Voucher (Kupon Fisik)',
    'category' => $_st_theme_voucher_category ?? 'Gaya Tiket / Kupon',
    'tag'      => $_st_theme_voucher_tag ?? 'Unik & Nostalgik',
    'desc'     => $_st_theme_voucher_desc ?? 'Setiap paket dirancang menyerupai tiket voucher cetak nyata dengan lekukan sobekan perforasi.',
    'icon'     => 'fa-ticket',
  ],
  'obsidian' => [
    'name'     => $_st_theme_obsidian_name ?? '5. Obsidian Glass (Cyber Glass)',
    'category' => $_st_theme_obsidian_category ?? 'Gaya Glassmorphism',
    'tag'      => $_st_theme_obsidian_tag ?? 'Futuristik Gelap',
    'desc'     => $_st_theme_obsidian_desc ?? 'Permukaan kaca gelap transparan dengan backdrop blur mendalam dan border glowing aksen biru.',
    'icon'     => 'fa-cube',
  ],
  'aurora' => [
    'name'     => $_st_theme_aurora_name ?? '6. Aurora Borealis (Vibrant Mesh)',
    'category' => $_st_theme_aurora_category ?? 'Gaya Gradien Hidup',
    'tag'      => $_st_theme_aurora_tag ?? 'Fintech Modern',
    'desc'     => $_st_theme_aurora_desc ?? 'Latar belakang aurora gradien dinamis ungu-emerald dengan kartu melayang estetik.',
    'icon'     => 'fa-magic',
  ],
  'emerald' => [
    'name'     => $_st_theme_emerald_name ?? '7. Emerald Pro (Fintech Trust)',
    'category' => $_st_theme_emerald_category ?? 'Gaya Finansial / Bank',
    'tag'      => $_st_theme_emerald_tag ?? 'Elegan & Tepercaya',
    'desc'     => $_st_theme_emerald_desc ?? 'Nuansa hijau emerald mewah melambangkan stabilitas finansial, kecepatan instan, dan keandalan tinggi.',
    'icon'     => 'fa-shield',
  ],
  'cyberpunk' => [
    'name'     => $_st_theme_cyberpunk_name ?? '8. Cyberpunk Neon (Hi-Tech)',
    'category' => $_st_theme_cyberpunk_category ?? 'Gaya Neon & Sci-Fi',
    'tag'      => $_st_theme_cyberpunk_tag ?? 'Aksen Kuat & Unik',
    'desc'     => $_st_theme_cyberpunk_desc ?? 'Kombinasi warna hitam pekat dengan aksen kuning neon cyber dan font tegas ala gamer modern.',
    'icon'     => 'fa-bolt',
  ],
  'swiss' => [
    'name'     => $_st_theme_swiss_name ?? '9. Swiss Typo (Editorial Clean)',
    'category' => $_st_theme_swiss_category ?? 'Gaya Tipografi Swiss',
    'tag'      => $_st_theme_swiss_tag ?? 'Minimalis Presisi',
    'desc'     => $_st_theme_swiss_desc ?? 'Desain tipografi presisi tinggi ala majalah Swiss, garis grid tajam, dan kontras monokrom bersih.',
    'icon'     => 'fa-font',
  ],
  'sunset' => [
    'name'     => $_st_theme_sunset_name ?? '10. Sunset Warm (Cozy Glow)',
    'category' => $_st_theme_sunset_category ?? 'Gaya Hangat & Ramah',
    'tag'      => $_st_theme_sunset_tag ?? 'Nyaman & Menarik',
    'desc'     => $_st_theme_sunset_desc ?? 'Gradasi jingga senja hangat yang ramah mata dan cocok untuk kafe, warkop, dan ruang santai.',
    'icon'     => 'fa-sun-o',
  ],
  'gaming' => [
    'name'     => $_st_theme_gaming_name ?? '11. Cyber Gaming (Esports Red)',
    'category' => $_st_theme_gaming_category ?? 'Gaya Esports / Game',
    'tag'      => $_st_theme_gaming_tag ?? 'Merah Crimson Berani',
    'desc'     => $_st_theme_gaming_desc ?? 'Nuansa gaming esports merah-hitam agresif dengan indikator latensi rendah (Low Ping).',
    'icon'     => 'fa-gamepad',
  ],
  'nordic' => [
    'name'     => $_st_theme_nordic_name ?? '12. Nordic Cafe (Scandinavian)',
    'category' => $_st_theme_nordic_category ?? 'Gaya Kafe & Warkop',
    'tag'      => $_st_theme_nordic_tag ?? 'Tenang & Berkelas',
    'desc'     => $_st_theme_nordic_desc ?? 'Estetika Skandinavia dengan palet warna pasir hangat (warm sand) dan tipografi lembut berkelas.',
    'icon'     => 'fa-coffee',
  ],
  'matrix' => [
    'name'     => $_st_theme_matrix_name ?? '13. Terminal Matrix (Hacker CLI)',
    'category' => $_st_theme_matrix_category ?? 'Gaya Terminal Komputer',
    'tag'      => $_st_theme_matrix_tag ?? 'Font Monospace Hijau',
    'desc'     => $_st_theme_matrix_desc ?? 'Antarmuka terminal retro hacker dengan teks monospace hijau neon dan prompt perintah unik.',
    'icon'     => 'fa-terminal',
  ],
  'luxury' => [
    'name'     => $_st_theme_luxury_name ?? '14. Royal Gold (Luxury VIP)',
    'category' => $_st_theme_luxury_category ?? 'Gaya Kemewahan / VIP',
    'tag'      => $_st_theme_luxury_tag ?? 'Emas Mewah Gelap',
    'desc'     => $_st_theme_luxury_desc ?? 'Aksen emas metalik mengkilap di atas kanvas hitam malam pekat untuk layanan berkelas VIP.',
    'icon'     => 'fa-diamond',
  ],
  'neumorphic' => [
    'name'     => $_st_theme_neumorphic_name ?? '15. Soft 3D (Neumorphism)',
    'category' => $_st_theme_neumorphic_category ?? 'Gaya Soft 3D Emboss',
    'tag'      => $_st_theme_neumorphic_tag ?? 'Bayangan Lembut Timbul',
    'desc'     => $_st_theme_neumorphic_desc ?? 'Efek tombol dan kartu timbul 3D yang lembut dan taktil saat disentuh di layar smartphone.',
    'icon'     => 'fa-square-o',
  ],
  'retro' => [
    'name'     => $_st_theme_retro_name ?? '16. Windows 98 (Retro Classic)',
    'category' => $_st_theme_retro_category ?? 'Gaya Klasik 90-an',
    'tag'      => $_st_theme_retro_tag ?? 'Nostalgia OS Klasik',
    'desc'     => $_st_theme_retro_desc ?? 'Desain retro nostalgia jendela OS 90-an lengkap dengan titlebar biru gradasi dan bevel tombol tebal.',
    'icon'     => 'fa-windows',
  ],
];

// Available Checkbox Display Elements
$displayElements = [
  'show_header_badge' => [
    'title' => $_store_header_badge_label ?? 'Badge Label Header Atas (Hotspot Name)',
    'desc'  => $_store_header_badge_hint ?? 'Menampilkan teks label kecil di atas judul utama toko (contoh: Hotspot • Lokasi).',
    'icon'  => 'fa-tag',
  ],
  'show_stock_badge' => [
    'title' => $_store_stock_badge_label ?? 'Badge Stok Voucher Real-Time',
    'desc'  => $_store_stock_badge_desc ?? 'Menampilkan sisa stok voucher yang tersedia (contoh: Sisa 15 atau Ready) pada tiap kartu paket.',
    'icon'  => 'fa-cubes',
  ],
  'show_validity_badge' => [
    'title' => $_store_validity_badge_label ?? 'Badge Masa Aktif (Validity / Durasi)',
    'desc'  => $_store_validity_badge_desc ?? 'Menampilkan durasi masa aktif paket (contoh: 24 Jam, 7 Hari, 30 Hari) di sudut kartu.',
    'icon'  => 'fa-clock-o',
  ],
  'show_speed_indicator' => [
    'title' => $_store_speed_indicator_label ?? 'Indikator Kecepatan & Fitur (Speed Limit)',
    'desc'  => $_store_speed_indicator_desc ?? 'Menampilkan detail kecepatan (contoh: Up to 10 Mbps • Unlimited) di bawah nama paket.',
    'icon'  => 'fa-tachometer',
  ],
  'show_best_seller_badge' => [
    'title' => $_store_best_seller_badge_label ?? 'Badge Rekomendasi / Terlaris (Populer)',
    'desc'  => $_store_best_seller_badge_desc ?? 'Menyorot paket tertentu dengan badge Terlaris / Rekomendasi untuk menarik minat beli.',
    'icon'  => 'fa-certificate',
  ],
  'show_contact_card' => [
    'title' => $_store_contact_card_label ?? 'Kartu Bantuan & Kontak CS WhatsApp',
    'desc'  => $_store_contact_card_desc ?? 'Menampilkan tombol langsung hubungi Admin / CS untuk bantuan pembelian voucher.',
    'icon'  => 'fa-whatsapp',
  ],
  'show_pending_dock' => [
    'title' => $_store_pending_dock_label ?? 'Dock Lanjutkan Pembayaran (Pending Dock)',
    'desc'  => $_store_pending_dock_desc ?? 'Dock melayang pintar yang muncul otomatis jika pelanggan memiliki pesanan yang belum diselesaikan.',
    'icon'  => 'fa-hourglass-half',
  ],
];

// Helper to save noderapay config
if (!function_exists('mikhmon_save_noderapay_config_safe')) {
  function mikhmon_save_noderapay_config_safe($file, $allData) {
    $out = "<?php\n";
    $out .= 'if(substr($_SERVER["REQUEST_URI"], -20) == "noderapay_config.php"){header("Location:./");};' . "\n";
    $out .= '$noderapay_data = ' . var_export($allData, true) . ";\n";
    return @file_put_contents($file, $out) !== false;
  }
}

// Handle Form Submission
$alert_msg = '';
$alert_type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['save_store_template']) || isset($_POST['np_portal_theme']))) {
  $postedTheme = trim($_POST['np_portal_theme'] ?? 'standard');
  if (isset($storeThemes[$postedTheme])) {
    $cfg['portal_theme'] = $postedTheme;
  } else {
    $cfg['portal_theme'] = 'standard';
  }

  // Handle Checkbox Elements
  $newElements = [];
  foreach ($displayElements as $eKey => $eVal) {
    $newElements[$eKey] = isset($_POST[$eKey]) && $_POST[$eKey] === 'yes' ? 'yes' : 'no';
  }
  $cfg['store_elements']     = $newElements;
  $cfg['header_badge_text']  = trim($_POST['header_badge_text'] ?? '');
  $cfg['store_title']        = trim($_POST['store_title'] ?? '');
  $cfg['store_subtitle']     = trim($_POST['store_subtitle'] ?? '');
  $cfg['custom_banner_text'] = trim($_POST['custom_banner_text'] ?? '');

  // 1. Save to default (global)
  if (!isset($noderapay_data['default']) || !is_array($noderapay_data['default'])) {
    $noderapay_data['default'] = [];
  }
  $noderapay_data['default']['portal_theme']       = $cfg['portal_theme'];
  $noderapay_data['default']['store_elements']     = $cfg['store_elements'];
  $noderapay_data['default']['header_badge_text']  = $cfg['header_badge_text'];
  $noderapay_data['default']['store_title']        = $cfg['store_title'];
  $noderapay_data['default']['store_subtitle']     = $cfg['store_subtitle'];
  $noderapay_data['default']['custom_banner_text'] = $cfg['custom_banner_text'];
  unset($noderapay_data['default']['custom_css']);

  // 2. If active session is selected, save explicitly to session key
  if (!empty($session)) {
    if (!isset($noderapay_data[$session]) || !is_array($noderapay_data[$session])) {
      $noderapay_data[$session] = [];
    }
    $noderapay_data[$session]['portal_theme']       = $cfg['portal_theme'];
    $noderapay_data[$session]['store_elements']     = $cfg['store_elements'];
    $noderapay_data[$session]['header_badge_text']  = $cfg['header_badge_text'];
    $noderapay_data[$session]['store_title']        = $cfg['store_title'];
    $noderapay_data[$session]['store_subtitle']     = $cfg['store_subtitle'];
    $noderapay_data[$session]['custom_banner_text'] = $cfg['custom_banner_text'];
    unset($noderapay_data[$session]['custom_css']);
  }

  // 3. Automatically sync to all configured router sessions
  $allTargetSessions = array_unique(array_merge(
    array_keys($allSessions),
    array_keys($location_data['locations'] ?? []),
    array_keys($noderapay_data)
  ));

  foreach ($allTargetSessions as $tSess) {
    if ($tSess === 'mikhmon' || empty($tSess)) continue;
    if (!isset($noderapay_data[$tSess]) || !is_array($noderapay_data[$tSess])) {
      $noderapay_data[$tSess] = [];
    }
    $noderapay_data[$tSess]['portal_theme']       = $cfg['portal_theme'];
    $noderapay_data[$tSess]['store_elements']     = $cfg['store_elements'];
    $noderapay_data[$tSess]['header_badge_text']  = $cfg['header_badge_text'];
    $noderapay_data[$tSess]['store_title']        = $cfg['store_title'];
    $noderapay_data[$tSess]['store_subtitle']     = $cfg['store_subtitle'];
    $noderapay_data[$tSess]['custom_banner_text'] = $cfg['custom_banner_text'];
    unset($noderapay_data[$tSess]['custom_css']);
  }

  if (mikhmon_save_noderapay_config_safe($configFile, $noderapay_data)) {
    $alert_msg = ($_store_settings_saved ?? 'Pengaturan template dan kustomisasi toko online berhasil disimpan secara global!');
    $alert_type = 'success';
  } else {
    $alert_msg = ($_store_settings_save_failed ?? 'Gagal menulis file konfigurasi. Periksa izin folder include/.');
    $alert_type = 'danger';
  }
}

// Current Values
$activeTheme = $cfg['portal_theme'] ?? 'standard';
if (!isset($storeThemes[$activeTheme])) {
  $activeTheme = 'standard';
}
$savedElements = $cfg['store_elements'] ?? [];

$hsDefaultName = '';
if (!empty($session) && isset($allSessions[$session])) {
  $hsParts = explode('%', $allSessions[$session][4] ?? '');
  $cand = trim($hsParts[1] ?? '');
  if (!empty($cand) && strtolower($cand) !== 'dns') {
    $hsDefaultName = $cand;
  }
}
if (empty($hsDefaultName) && !empty($session)) {
  $hsDefaultName = ucwords(str_replace(['-', '_'], ' ', $session));
}
if (empty($hsDefaultName)) {
  $hsDefaultName = 'WiFi Hotspot';
}

$buyUrl = "./buy.php" . (!empty($session) ? "?session=" . urlencode($session) : "");
$currencySample = $currency ?: 'Rp';
$priceSample1 = ($currencySample === 'Rp') ? 'Rp 3.000' : '3.000 ' . $currencySample;
$priceSample2 = ($currencySample === 'Rp') ? 'Rp 10.000' : '10.000 ' . $currencySample;
$priceSampleHero = ($currencySample === 'Rp') ? 'Rp 50.000' : '50.000 ' . $currencySample;
$pkgName1 = ($_store_pkg_24h ?? 'Paket 24 Jam');
$pkgName2 = ($_store_pkg_7d ?? 'Paket 7 Hari');
$pkgNameHero = ($_store_pkg_30d ?? 'Paket 30 Hari');
$stockSample = sprintf($_store_stock_sample ?? 'Sisa %d', 1590);
?>

<style>
/* Mikhmon Native Theme Integration for Store Template Selector */
.ts-style-box {
  transition: transform 0.15s ease, box-shadow 0.15s ease, border-color 0.15s ease;
  height: 100%;
  cursor: pointer;
  border-radius: 6px;
  overflow: hidden;
}
.ts-style-box:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}
.active-template {
  border: 2px solid #008BC9 !important;
  box-shadow: 0 0 10px rgba(0, 139, 201, 0.4) !important;
}
.ts-style-box .card-header h3 {
  font-weight: 700;
  display: flex;
  align-items: center;
  gap: 6px;
}
.ts-style-box .select-btn {
  width: 100%;
  border-radius: 4px;
  font-weight: 600;
  letter-spacing: 0.3px;
}

/* Miniature Mockup Container */
.mst-box {
  width: 100%;
  height: 130px;
  border-radius: 6px;
  padding: 8px;
  box-sizing: border-box;
  font-size: 8px;
  line-height: 1.2;
  overflow: hidden;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  text-align: left;
  user-select: none;
  border: 1px solid rgba(0,0,0,0.08);
}

/* Theme 1: Standard Neo Bento */
.mst-standard { background: #f8fafc; color: #0f172a; }
.mst-standard-hero { background: #0284c7; color: #fff; padding: 4px; border-radius: 4px; font-size: 7px; text-align: center; }
.mst-standard-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 4px; margin-top: 4px; }
.mst-standard-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 4px; padding: 3px; display: flex; flex-direction: column; justify-content: space-between; height: 42px; }
.mst-standard-card.active { border-color: #0284c7; background: #f0f9ff; }

/* Theme 2: Stripe Clean List */
.mst-stripe { background: #ffffff; color: #1e293b; border-color: #e2e8f0; }
.mst-stripe-row { display: flex; justify-content: space-between; align-items: center; padding: 4px 6px; border: 1px solid #e2e8f0; border-radius: 4px; margin-bottom: 3px; background: #f8fafc; }
.mst-stripe-row.active { border-color: #6366f1; background: #eef2ff; color: #4338ca; }
.mst-stripe-dot { width: 6px; height: 6px; border-radius: 50%; border: 1.5px solid #6366f1; display: inline-block; margin-right: 4px; }

/* Theme 3: Linear Hero Featured */
.mst-linear { background: #090d16; color: #f1f5f9; }
.mst-linear-hero { background: linear-gradient(135deg, #1e293b, #0f172a); border: 1px solid #3b82f6; border-radius: 5px; padding: 5px; margin-bottom: 4px; }
.mst-linear-sub { background: #111827; border: 1px solid #1f2937; border-radius: 4px; padding: 4px; display: flex; justify-content: space-between; }

/* Theme 4: Voucher Ticket */
.mst-voucher { background: #f1f5f9; color: #334155; }
.mst-ticket-item { background: #fff; border: 1px dashed #cbd5e1; border-radius: 4px; padding: 4px 6px; position: relative; margin-bottom: 4px; display: flex; justify-content: space-between; }
.mst-ticket-item:before, .mst-ticket-item:after { content: ""; position: absolute; width: 6px; height: 6px; background: #f1f5f9; border-radius: 50%; top: calc(50% - 3px); }
.mst-ticket-item:before { left: -4px; }
.mst-ticket-item:after { right: -4px; }

/* Theme 5: Obsidian Glass */
.mst-obsidian { background: #05070a; color: #e2e8f0; }
.mst-obs-glass { background: rgba(255,255,255,0.05); backdrop-filter: blur(4px); border: 1px solid rgba(255,255,255,0.12); border-radius: 5px; padding: 4px 6px; margin-bottom: 4px; }
.mst-obs-glass.active { border-color: #38bdf8; background: rgba(56,189,248,0.1); }

/* Theme 6: Aurora Vibrant Mesh */
.mst-aurora { background: linear-gradient(135deg, #0b1120 0%, #1e1b4b 50%, #064e3b 100%); color: #f8fafc; }
.mst-aurora-card { background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.18); border-radius: 5px; padding: 4px 6px; margin-bottom: 4px; }

/* Theme 7: Emerald Pro */
.mst-emerald { background: #061a14; color: #ecfdf5; }
.mst-eme-hdr { color: #10b981; font-weight: bold; margin-bottom: 3px; font-size: 8px; }
.mst-eme-card { background: #0b2920; border: 1px solid #059669; border-radius: 4px; padding: 4px 6px; margin-bottom: 3px; display: flex; justify-content: space-between; }

/* Theme 8: Cyberpunk Neon */
.mst-cyberpunk { background: #0a0a0c; color: #fef08a; border: 1px solid #facc15; }
.mst-cyber-card { background: #18181b; border: 1px solid #facc15; border-radius: 2px; padding: 4px; margin-bottom: 3px; color: #fff; }
.mst-cyber-tag { background: #facc15; color: #000; font-weight: 800; font-size: 6px; padding: 1px 3px; border-radius: 2px; display: inline-block; }

/* Theme 9: Swiss Typo */
.mst-swiss { background: #ffffff; color: #000000; border: 1px solid #000; }
.mst-swiss-card { border-bottom: 2px solid #000; padding: 4px 0; display: flex; justify-content: space-between; font-weight: 700; }

/* Theme 10: Sunset Warm */
.mst-sunset { background: #fffbeb; color: #78350f; }
.mst-sunset-card { background: linear-gradient(135deg, #fff7ed, #ffedd5); border: 1px solid #fdba74; border-radius: 5px; padding: 4px; margin-bottom: 3px; display: flex; justify-content: space-between; }

/* Theme 11: Gaming Crimson */
.mst-gaming { background: #0a0507; color: #fecdd3; border: 1px solid #e11d48; }
.mst-gaming-card { background: #1c0b10; border: 1px solid #e11d48; border-radius: 3px; padding: 4px; margin-bottom: 3px; }

/* Theme 12: Nordic Cafe */
.mst-nordic { background: #f5f5f4; color: #292524; }
.mst-nordic-card { background: #e7e5e4; border-radius: 4px; padding: 4px 6px; margin-bottom: 3px; border: 1px solid #d6d3d1; }

/* Theme 13: Matrix Terminal */
.mst-matrix { background: #020804; color: #22c55e; font-family: monospace; }
.mst-matrix-card { border: 1px solid #22c55e; padding: 3px; margin-bottom: 3px; background: rgba(34,197,94,0.05); }

/* Theme 14: Royal Gold */
.mst-luxury { background: #090d16; color: #fef08a; border: 1px solid #d4af37; }
.mst-luxury-card { background: #131b2e; border: 1px solid #d4af37; border-radius: 4px; padding: 4px; margin-bottom: 3px; }

/* Theme 15: Neumorphic Soft 3D */
.mst-neumorphic { background: #e0e5ec; color: #334155; }
.mst-neu-card { background: #e0e5ec; box-shadow: 2px 2px 5px #b8b9be, -2px -2px 5px #ffffff; border-radius: 6px; padding: 4px 6px; margin-bottom: 4px; }

/* Theme 16: Windows 98 Retro */
.mst-retro { background: #008080; color: #000; font-family: Tahoma, sans-serif; }
.mst-win-box { background: #c0c0c0; border: 2px outset #fff; padding: 3px; }
.mst-win-hdr { background: #000080; color: #fff; padding: 1px 3px; font-weight: bold; font-size: 7px; display: flex; justify-content: space-between; }
</style>

<form autocomplete="off" method="post" action="">
  <input type="hidden" id="st_selected_theme_input" name="np_portal_theme" value="<?= htmlspecialchars($activeTheme); ?>">

  <div class="row">
    <div class="col-12">
      <div class="card">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
          <h3 class="card-title">
            <i class="fa fa-desktop text-primary"></i> <?= $_store_template_customizer ?? 'Template & Desain Toko Online (buy.php)'; ?>
          </h3>
          <div style="display: flex; gap: 8px; align-items: center;">
            <button type="submit" name="save_store_template" class="btn btn-sm bg-primary" style="margin: 0;">
              <i class="fa fa-save"></i> <?= $_ts_save_settings ?? 'Simpan Pengaturan'; ?>
            </button>
            <a id="st_preview_link" href="<?= htmlspecialchars($buyUrl); ?>" target="_blank" class="btn btn-sm bg-secondary" style="margin: 0;">
              <i class="fa fa-external-link"></i> <?= $_live_preview ?? 'Live Preview'; ?>
            </a>
          </div>
        </div>
        <div class="card-body">

          <?php if (!empty($alert_msg)): ?>
            <div class="alert bg-<?= $alert_type; ?>" style="margin-bottom: 15px;">
              <i class="fa <?= $alert_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle'; ?>"></i> <?= $alert_msg; ?>
            </div>
          <?php endif; ?>

          <!-- SECTION 1: HEADER TEXT & STORE IDENTITY CUSTOMIZER (TOP) -->
          <div class="box-bordered" style="padding: 14px; border-radius: 6px; margin-bottom: 18px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; flex-wrap: wrap; gap: 8px;">
              <div>
                <b style="font-size: 13.5px;"><i class="fa fa-sliders text-primary"></i> <?= $_store_header_identity_title ?? 'Teks Header, Nama Toko & Identitas Hero Banner'; ?></b>
                <div class="text-secondary" style="font-size: 11.5px; margin-top: 2px;">
                  <?= $_store_header_identity_desc ?? 'Sesuaikan teks label atas, judul utama toko, serta slogan yang tampil pada halaman beli voucher pelanggan:'; ?>
                </div>
              </div>
            </div>

            <table class="table" style="margin-top: 10px; margin-bottom: 0;">
              <tr>
                <td style="width: 32%; border: none;" class="align-middle">
                  <b><?= $_store_header_badge_label ?? 'Teks Label Header Atas'; ?></b>
                </td>
                <td style="border: none;">
                  <input class="form-control" type="text" name="header_badge_text" value="<?= htmlspecialchars($cfg['header_badge_text'] ?? ''); ?>" placeholder="<?= $_store_header_badge_placeholder ?? ('Hotspot • ' . htmlspecialchars($hsDefaultName)); ?>">
                  <small class="text-secondary"><?= $_store_header_badge_hint ?? ('Teks label kecil di atas nama toko (contoh: <code>Hotspot • ' . htmlspecialchars($hsDefaultName) . '</code> atau isi teks bebas, kosongkan untuk otomatis).'); ?></small>
                </td>
              </tr>
              <tr>
                <td style="border: none;" class="align-middle">
                  <b><?= $_store_title_label ?? 'Judul Landing Page / Nama Toko'; ?></b>
                </td>
                <td style="border: none;">
                  <input class="form-control" type="text" name="store_title" value="<?= htmlspecialchars($cfg['store_title'] ?? ''); ?>" placeholder="<?= htmlspecialchars($hsDefaultName ?: 'Voucher WiFi Online'); ?>">
                  <small class="text-secondary"><?= $_store_title_hint ?? 'Nama utama yang tampil besar di hero banner halaman beli voucher.'; ?></small>
                </td>
              </tr>
              <tr>
                <td style="border: none;" class="align-middle">
                  <b><?= $_store_subtitle_label ?? 'Sub-Judul / Keterangan'; ?></b>
                </td>
                <td style="border: none;">
                  <input class="form-control" type="text" name="store_subtitle" value="<?= htmlspecialchars($cfg['store_subtitle'] ?? ''); ?>" placeholder="<?= $_store_subtitle_placeholder ?? 'Internet Cepat, Murah & Aktif Otomatis'; ?>">
                  <small class="text-secondary"><?= $_store_subtitle_hint ?? 'Slogan atau keterangan tepat di bawah judul toko.'; ?></small>
                </td>
              </tr>
              <tr>
                <td style="border: none;" class="align-middle">
                  <b><?= $_store_ticker_label ?? 'Teks Running Ticker Notifikasi (Opsional)'; ?></b>
                </td>
                <td style="border: none;">
                  <input class="form-control" type="text" name="custom_banner_text" value="<?= htmlspecialchars($cfg['custom_banner_text'] ?? ''); ?>" placeholder="<?= $_store_ticker_placeholder ?? '0812-****-8910 baru saja beli Paket 24 Jam'; ?>">
                  <small class="text-secondary"><?= $_store_ticker_hint ?? 'Teks berjalan simulasi penjualan live di atas header (kosongkan jika ingin dinamis otomatis).'; ?></small>
                </td>
              </tr>
            </table>
          </div>

          <!-- SECTION 2: CHECKBOX DISPLAY ELEMENTS CUSTOMIZER -->
          <div class="box-bordered" style="padding: 14px; border-radius: 6px; margin-bottom: 18px;">
            <b style="font-size: 13.5px;"><i class="fa fa-check-square-o text-primary"></i> <?= $_store_visual_elements_title ?? 'Pengaturan Elemen Visual & Informasi Voucher'; ?></b>
            <div class="text-secondary" style="font-size: 11.5px; margin-top: 2px; margin-bottom: 12px;">
              <?= $_store_visual_elements_desc ?? 'Aktifkan atau sembunyikan komponen informasi voucher di halaman pembelian pelanggan:'; ?>
            </div>

            <div class="row">
              <?php foreach ($displayElements as $eKey => $eVal): 
                $isChecked = !isset($savedElements[$eKey]) || $savedElements[$eKey] !== 'no';
              ?>
                <div class="col-6 col-box-12 mr-b-10">
                  <div style="border: 1px solid rgba(0,0,0,0.08); border-radius: 4px; padding: 8px 10px; background: rgba(0,0,0,0.01);">
                    <label style="display: flex; align-items: flex-start; cursor: pointer; margin: 0; font-weight: normal;">
                      <input type="checkbox" name="<?= htmlspecialchars($eKey); ?>" value="yes" <?= $isChecked ? 'checked' : ''; ?> style="margin-top: 3px; margin-right: 8px;">
                      <div>
                        <b><i class="fa <?= htmlspecialchars($eVal['icon']); ?> text-primary"></i> <?= htmlspecialchars($eVal['title']); ?></b>
                        <div class="text-secondary" style="font-size: 11px; margin-top: 2px; line-height: 1.3;">
                          <?= htmlspecialchars($eVal['desc']); ?>
                        </div>
                      </div>
                    </label>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>

          <!-- SECTION 3: 16 DISTINCT THEME CARDS (NATIVE MIKHMON 3-COL GRID) -->
          <div class="box-bordered" style="padding: 14px; border-radius: 6px;">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
              <div>
                <b style="font-size: 13.5px;"><i class="fa fa-paint-brush text-primary"></i> <?= $_store_16_themes_title ?? 'Koleksi 16 Tema Fisik Toko Online (buy.php)'; ?></b>
                <div class="text-secondary" style="font-size: 11.5px; margin-top: 2px;">
                  <?= $_store_16_themes_desc ?? 'Klik salah satu kartu tema di bawah, lalu klik tombol <b>Simpan Pengaturan</b> di header kanan atas.'; ?>
                </div>
              </div>
            </div>

            <div class="row mr-t-10">
              <?php foreach ($storeThemes as $tKey => $tData): 
                $isThemeActive = ($activeTheme === $tKey);
              ?>
                <div class="col-4 col-box-12 mr-b-10">
                  <div class="card box-bordered ts-style-box <?= $isThemeActive ? 'active-template' : ''; ?>" id="card_<?= htmlspecialchars($tKey); ?>" onclick="selectStoreTheme('<?= htmlspecialchars($tKey); ?>')">
                    <div class="card-header" style="padding: 8px 10px; display: flex; justify-content: space-between; align-items: center;">
                      <h3 style="font-size: 12px; margin: 0;"><i class="fa <?= htmlspecialchars($tData['icon']); ?> text-primary"></i> <?= htmlspecialchars($tData['name']); ?></h3>
                      <span class="badge <?= $isThemeActive ? 'bg-primary' : 'bg-secondary'; ?>" id="badge_<?= htmlspecialchars($tKey); ?>" style="font-size: 9.5px;">
                        <?= $isThemeActive ? ($_active ?? 'Aktif') : ($_choose ?? 'Pilih'); ?>
                      </span>
                    </div>

                    <div class="card-body text-center" style="padding: 10px;">
                      
                      <!-- REALISTIC MINIATURE PORTAL MOCKUP PER THEME -->
                      <div class="mst-box mst-<?= htmlspecialchars($tKey); ?>">
                        <?php if ($tKey === 'standard'): ?>
                          <div class="mst-standard-hero">
                            <i class="fa fa-wifi text-primary"></i> <b>HOTSPOT WIFI</b>
                          </div>
                          <div class="mst-standard-grid">
                            <div class="mst-standard-card active">
                              <div><b><?= $pkgName1; ?></b><div style="font-size:6.5px;color:#0284c7;"><?= $stockSample; ?></div></div>
                              <div style="text-align:right;font-weight:800;color:#0284c7;"><?= $priceSample1; ?></div>
                            </div>
                            <div class="mst-standard-card">
                              <div><b><?= $pkgName2; ?></b><div style="font-size:6.5px;color:#64748b;"><?= $stockSample; ?></div></div>
                              <div style="text-align:right;font-weight:800;"><?= $priceSample2; ?></div>
                            </div>
                          </div>
                          <div style="background:#0284c7;color:#fff;border-radius:4px;text-align:center;padding:2px;margin-top:4px;font-weight:700;">ACHETER / BELI</div>

                        <?php elseif ($tKey === 'stripe'): ?>
                          <div style="font-weight:700;margin-bottom:3px;color:#334155;"><i class="fa fa-bolt text-primary"></i> FORFAITS WIFI</div>
                          <div class="mst-stripe-row active">
                            <div><span class="mst-stripe-dot"></span><b><?= $pkgName1; ?></b></div>
                            <b style="color:#6366f1;"><?= $priceSample1; ?></b>
                          </div>
                          <div class="mst-stripe-row">
                            <div><span class="mst-stripe-dot"></span><b><?= $pkgName2; ?></b></div>
                            <b><?= $priceSample2; ?></b>
                          </div>
                          <div style="background:#6366f1;color:#fff;border-radius:4px;text-align:center;padding:2px;font-weight:700;">COMMANDER</div>

                        <?php elseif ($tKey === 'linear'): ?>
                          <div class="mst-linear-hero">
                            <div style="display:flex;justify-content:space-between;"><span style="color:#60a5fa;font-weight:800;">★ BEST SELLER</span><span style="color:#93c5fd;"><?= $priceSampleHero; ?></span></div>
                            <b style="font-size:9px;"><?= $pkgNameHero; ?></b>
                          </div>
                          <div class="mst-linear-sub">
                            <span><?= $pkgName1; ?></span>
                            <span style="color:#38bdf8;"><?= $priceSample1; ?></span>
                          </div>
                          <div style="background:#3b82f6;color:#fff;border-radius:4px;text-align:center;padding:2px;margin-top:3px;font-weight:700;">GET PASS</div>

                        <?php elseif ($tKey === 'voucher'): ?>
                          <div class="mst-ticket-item">
                            <div><b><?= $pkgName1; ?></b><div style="font-size:6.5px;color:#64748b;">PASS 24H</div></div>
                            <b style="color:#e11d48;font-size:9px;"><?= $priceSample1; ?></b>
                          </div>
                          <div class="mst-ticket-item">
                            <div><b><?= $pkgName2; ?></b><div style="font-size:6.5px;color:#64748b;">PASS 7J</div></div>
                            <b style="color:#e11d48;font-size:9px;"><?= $priceSample2; ?></b>
                          </div>
                          <div style="background:#e11d48;color:#fff;border-radius:4px;text-align:center;padding:2px;font-weight:700;">COUPON PASS</div>

                        <?php elseif ($tKey === 'obsidian'): ?>
                          <div style="color:#38bdf8;font-weight:800;letter-spacing:0.5px;margin-bottom:3px;">CYBER OBSIDIAN</div>
                          <div class="mst-obs-glass active">
                            <div style="display:flex;justify-content:space-between;"><b><?= $pkgName1; ?></b><span style="color:#38bdf8;font-weight:800;"><?= $priceSample1; ?></span></div>
                          </div>
                          <div class="mst-obs-glass">
                            <div style="display:flex;justify-content:space-between;"><span><?= $pkgName2; ?></span><span><?= $priceSample2; ?></span></div>
                          </div>
                          <div style="background:#0284c7;color:#fff;border-radius:4px;text-align:center;padding:2px;font-weight:700;">ACCESS PASS</div>

                        <?php elseif ($tKey === 'aurora'): ?>
                          <div style="color:#a7f3d0;font-weight:800;margin-bottom:3px;">AURORA ULTRA</div>
                          <div class="mst-aurora-card">
                            <div style="display:flex;justify-content:space-between;"><b><?= $pkgName1; ?></b><span style="color:#6ee7b7;font-weight:700;"><?= $priceSample1; ?></span></div>
                          </div>
                          <div class="mst-aurora-card">
                            <div style="display:flex;justify-content:space-between;"><span><?= $pkgName2; ?></span><span><?= $priceSample2; ?></span></div>
                          </div>
                          <div style="background:linear-gradient(90deg,#8b5cf6,#10b981);color:#fff;border-radius:4px;text-align:center;padding:2px;font-weight:700;">CONNECT NOW</div>

                        <?php elseif ($tKey === 'emerald'): ?>
                          <div class="mst-eme-hdr"><i class="fa fa-shield"></i> EMERALD SECURE</div>
                          <div class="mst-eme-card">
                            <span><?= $pkgName1; ?></span>
                            <b style="color:#34d399;"><?= $priceSample1; ?></b>
                          </div>
                          <div class="mst-eme-card">
                            <span><?= $pkgName2; ?></span>
                            <b><?= $priceSample2; ?></b>
                          </div>
                          <div style="background:#059669;color:#fff;border-radius:4px;text-align:center;padding:2px;font-weight:700;">PAY INSTANT</div>

                        <?php elseif ($tKey === 'cyberpunk'): ?>
                          <div style="display:flex;justify-content:space-between;margin-bottom:2px;"><span class="mst-cyber-tag">CYBER</span><span style="color:#facc15;font-weight:bold;">v2077</span></div>
                          <div class="mst-cyber-card">
                            <div style="display:flex;justify-content:space-between;"><b><?= $pkgName1; ?></b><span style="color:#facc15;font-weight:bold;"><?= $priceSample1; ?></span></div>
                          </div>
                          <div style="background:#facc15;color:#000;border-radius:2px;text-align:center;padding:2px;font-weight:900;">JACK IN</div>

                        <?php elseif ($tKey === 'swiss'): ?>
                          <div style="font-size:9px;font-weight:900;letter-spacing:-0.5px;border-bottom:2px solid #000;padding-bottom:2px;margin-bottom:3px;">SWISS PASS</div>
                          <div class="mst-swiss-card">
                            <span><?= $pkgName1; ?></span>
                            <span><?= $priceSample1; ?></span>
                          </div>
                          <div class="mst-swiss-card">
                            <span><?= $pkgName2; ?></span>
                            <span><?= $priceSample2; ?></span>
                          </div>
                          <div style="background:#000;color:#fff;text-align:center;padding:2px;font-weight:bold;margin-top:2px;">SELECT</div>

                        <?php elseif ($tKey === 'sunset'): ?>
                          <div style="color:#c2410c;font-weight:bold;margin-bottom:3px;"><i class="fa fa-sun-o"></i> SUNSET WIFI</div>
                          <div class="mst-sunset-card">
                            <span><?= $pkgName1; ?></span>
                            <b style="color:#ea580c;"><?= $priceSample1; ?></b>
                          </div>
                          <div class="mst-sunset-card">
                            <span><?= $pkgName2; ?></span>
                            <b><?= $priceSample2; ?></b>
                          </div>
                          <div style="background:#ea580c;color:#fff;border-radius:4px;text-align:center;padding:2px;font-weight:700;">AMBIL VOUCHER</div>

                        <?php elseif ($tKey === 'gaming'): ?>
                          <div style="color:#f43f5e;font-weight:bold;display:flex;justify-content:space-between;margin-bottom:2px;"><span>ESPORTS</span><span>LOW PING</span></div>
                          <div class="mst-gaming-card">
                            <div style="display:flex;justify-content:space-between;"><b><?= $pkgName1; ?></b><span style="color:#fb7185;"><?= $priceSample1; ?></span></div>
                          </div>
                          <div style="background:#e11d48;color:#fff;border-radius:3px;text-align:center;padding:2px;font-weight:800;">READY TO PLAY</div>

                        <?php elseif ($tKey === 'nordic'): ?>
                          <div style="font-style:italic;color:#78716c;margin-bottom:3px;">Café & Wifi Hygge</div>
                          <div class="mst-nordic-card">
                            <div style="display:flex;justify-content:space-between;"><span><?= $pkgName1; ?></span><b><?= $priceSample1; ?></b></div>
                          </div>
                          <div class="mst-nordic-card">
                            <div style="display:flex;justify-content:space-between;"><span><?= $pkgName2; ?></span><b><?= $priceSample2; ?></b></div>
                          </div>
                          <div style="background:#44403c;color:#fff;border-radius:3px;text-align:center;padding:2px;font-weight:600;">Bestil Pass</div>

                        <?php elseif ($tKey === 'matrix'): ?>
                          <div style="margin-bottom:2px;">root@matrix:~#</div>
                          <div class="mst-matrix-card">
                            <div>$ pkg --buy "<?= $pkgName1; ?>"</div>
                            <div style="text-align:right;">[ <?= $priceSample1; ?> ]</div>
                          </div>
                          <div style="background:#22c55e;color:#000;text-align:center;padding:2px;font-weight:bold;">$ ./checkout.sh</div>

                        <?php elseif ($tKey === 'luxury'): ?>
                          <div style="color:#d4af37;text-align:center;font-weight:800;letter-spacing:1px;margin-bottom:2px;">ROYAL VIP</div>
                          <div class="mst-luxury-card">
                            <div style="display:flex;justify-content:space-between;"><b><?= $pkgName1; ?></b><span style="color:#d4af37;font-weight:800;"><?= $priceSample1; ?></span></div>
                          </div>
                          <div class="mst-luxury-card">
                            <div style="display:flex;justify-content:space-between;"><b><?= $pkgName2; ?></b><span style="color:#d4af37;"><?= $priceSample2; ?></span></div>
                          </div>
                          <div style="background:#d4af37;color:#090d16;border-radius:3px;text-align:center;padding:2px;font-weight:800;">VIP ACCESS</div>

                        <?php elseif ($tKey === 'neumorphic'): ?>
                          <div class="mst-neu-card">
                            <div style="display:flex;justify-content:space-between;"><b><?= $pkgName1; ?></b><span style="font-weight:800;color:#2563eb;"><?= $priceSample1; ?></span></div>
                          </div>
                          <div class="mst-neu-card">
                            <div style="display:flex;justify-content:space-between;"><b><?= $pkgName2; ?></b><span><?= $priceSample2; ?></span></div>
                          </div>
                          <div style="background:#2563eb;color:#fff;border-radius:6px;text-align:center;padding:2px;font-weight:700;">Select 3D</div>

                        <?php elseif ($tKey === 'retro'): ?>
                          <div class="mst-win-box">
                            <div class="mst-win-hdr"><span>WiFi.exe</span><span>X</span></div>
                            <div style="padding:2px;background:#fff;border:1px inset #808080;margin:2px 0;">
                              <div><?= $pkgName1; ?> : <?= $priceSample1; ?></div>
                            </div>
                            <div style="background:#c0c0c0;border:1px outset #fff;text-align:center;padding:1px;font-weight:bold;font-size:7px;">OK / Buy</div>
                          </div>
                        <?php endif; ?>
                      </div>

                      <div style="font-size: 11px; margin-top: 8px; opacity: 0.85; line-height: 1.35;">
                        <?= htmlspecialchars($tData['desc']); ?>
                      </div>
                    </div>

                    <div class="card-footer text-center" style="padding: 5px;">
                      <span class="btn btn-sm <?= $isThemeActive ? 'bg-primary' : 'bg-secondary'; ?> select-btn" id="btn_sel_<?= htmlspecialchars($tKey); ?>" style="margin: 0; font-size: 12px;">
                        <i class="fa <?= $isThemeActive ? 'fa-check' : 'fa-circle-o'; ?>"></i> <?= $isThemeActive ? ($_ts_active_theme ?? 'Tema Aktif') : ($_ts_select_theme ?? 'Pilih Tema Ini'); ?>
                      </span>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>

        </div>
      </div>
    </div>
  </div>
</form>

<script>
var currentSession = <?= json_encode($session); ?>;

function selectStoreTheme(themeKey) {
  var input = document.getElementById('st_selected_theme_input');
  if (input) {
    input.value = themeKey;
  }

  var cards = document.querySelectorAll('.ts-style-box');
  cards.forEach(function(c) {
    var cid = c.id.replace('card_', '');
    var badge = document.getElementById('badge_' + cid);
    var btnEl = document.getElementById('btn_sel_' + cid);

    if (cid === themeKey) {
      c.classList.add('active-template');
      if (badge) {
        badge.className = 'badge bg-primary';
        badge.innerText = '' + <?= json_encode($_active ?? 'Aktif'); ?>;
      }
      if (btnEl) {
        btnEl.className = 'btn btn-sm bg-primary select-btn';
        btnEl.innerHTML = '<i class="fa fa-check"></i> ' + <?= json_encode($_ts_active_theme ?? 'Tema Aktif'); ?>;
      }
    } else {
      c.classList.remove('active-template');
      if (badge) {
        badge.className = 'badge bg-secondary';
        badge.innerText = '' + <?= json_encode($_choose ?? 'Pilih'); ?>;
      }
      if (btnEl) {
        btnEl.className = 'btn btn-sm bg-secondary select-btn';
        btnEl.innerHTML = '<i class="fa fa-circle-o"></i> ' + <?= json_encode($_ts_select_theme ?? 'Pilih Tema Ini'); ?>;
      }
    }
  });

  var pLink = document.getElementById('st_preview_link');
  var newUrl = './buy.php?' + (currentSession ? 'session=' + encodeURIComponent(currentSession) + '&' : '') + 'theme=' + encodeURIComponent(themeKey);
  if (pLink) pLink.href = newUrl;
}
</script>
