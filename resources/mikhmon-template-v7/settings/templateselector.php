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
 * Mikhmon Voucher Template & Color Selector
 * Built for NODERA Multi-Tenant Platform
 * Supporting 12 Layout Styles x 11 Color Palettes x Checkbox Table Display Customization
 * Pure Native Mikhmon UI Integration
 */

// hide all error
error_reporting(0);
if (!isset($_SESSION["mikhmon"])) {
  header("Location:../admin.php?id=login");
  exit;
} else {

  // localization via lang dictionary

  $configFile = "./include/voucher_config.php";
  $vConfig = [];
  $vThemes = [];

  if (file_exists($configFile)) {
    @include($configFile);
    if (isset($voucher_config) && is_array($voucher_config)) {
      $vConfig = $voucher_config;
    }
    if (isset($voucher_theme) && is_array($voucher_theme)) {
      $vThemes = $voucher_theme;
    }
  }

  $allowedStyles = [
    'modern-card' => [
      'name' => $_ts_style_modern_card_name ?? 'Modern Bento Card',
      'tag'  => $_ts_style_modern_card_tag ?? 'Rekomendasi Utama',
      'desc' => $_ts_style_modern_card_desc ?? 'Layout bento proporsional dengan header gradien, kontras kode tinggi, dan kotak QR pas.',
      'icon' => 'fa-th-large',
    ],
    'ticket-notch' => [
      'name' => $_ts_style_ticket_notch_name ?? 'Boarding Pass Ticket',
      'tag'  => $_ts_style_ticket_notch_tag ?? 'Gaya Tiket / Perforasi',
      'desc' => $_ts_style_ticket_notch_desc ?? 'Gaya tiket konser / boarding pass dengan garis perforasi samping dan stub QR terpisah.',
      'icon' => 'fa-ticket',
    ],
    'curved-wave' => [
      'name' => $_ts_style_curved_wave_name ?? 'Curved Wave & Floating Box',
      'tag'  => $_ts_style_curved_wave_tag ?? 'Fluid & Dinamis',
      'desc' => $_ts_style_curved_wave_desc ?? 'Header bergelombang melengkung dinamis dengan kotak kode melayang dan badge pil rounded.',
      'icon' => 'fa-tint',
    ],
    'industrial-grid' => [
      'name' => $_ts_style_industrial_grid_name ?? 'Industrial Brutalist Grid',
      'tag'  => $_ts_style_industrial_grid_tag ?? '0px Radius / Monospace',
      'desc' => $_ts_style_industrial_grid_desc ?? 'Struktur kotak geometris sudut tajam, label teknis monospace [ ACCESS CODE ], dan tabel rapi.',
      'icon' => 'fa-cubes',
    ],
    'cyber-neon' => [
      'name' => $_ts_style_cyber_neon_name ?? 'Cyber Gaming Dark Neon',
      'tag'  => $_ts_style_cyber_neon_tag ?? 'Dark Mode & Esports',
      'desc' => $_ts_style_cyber_neon_desc ?? 'Tema kartu gelap futuristik dengan aksen border menyala (glow neon) dan font tajam.',
      'icon' => 'fa-gamepad',
    ],
    'minimal-stamp' => [
      'name' => $_ts_style_minimal_stamp_name ?? 'Clean Minimal Stamp',
      'tag'  => $_ts_style_minimal_stamp_tag ?? 'Hemat Tinta & Cetak Massal',
      'desc' => $_ts_style_minimal_stamp_desc ?? 'Gaya stempel klasik dengan double border presisi, sangat rapi dan hemat tinta printer.',
      'icon' => 'fa-file-text-o',
    ],
    'retro-arcade' => [
      'name' => $_ts_style_retro_arcade_name ?? 'Retro Arcade 8-Bit Pixel',
      'tag'  => $_ts_style_retro_arcade_tag ?? 'Gamer Retro & Pop',
      'desc' => $_ts_style_retro_arcade_desc ?? 'Gaya pixel arcade klasik dengan bayangan tebal, tajuk Insert Coin, dan font game retro.',
      'icon' => 'fa-trophy',
    ],
    'luxury-vip' => [
      'name' => $_ts_style_luxury_vip_name ?? 'Luxury Gold VIP Ribbon',
      'tag'  => $_ts_style_luxury_vip_tag ?? 'Hotel & Lounge Premium',
      'desc' => $_ts_style_luxury_vip_desc ?? 'Kartu hitam obsidian mewah dengan aksen emas berkelas, badge VIP ribbon, dan tipografi eksklusif.',
      'icon' => 'fa-star',
    ],
    'synthwave-sunset' => [
      'name' => $_ts_style_synthwave_sunset_name ?? 'Neon Synthwave 80s',
      'tag'  => $_ts_style_synthwave_sunset_tag ?? 'Vibrant Glow & Sunset',
      'desc' => $_ts_style_synthwave_sunset_desc ?? 'Kombinasi gradien magenta sunset dan cyan elektrik dengan aksen neon retro wave 80-an.',
      'icon' => 'fa-bolt',
    ],
    'vintage-kraft' => [
      'name' => $_ts_style_vintage_kraft_name ?? 'Coffee Shop Kraft Paper',
      'tag'  => $_ts_style_vintage_kraft_tag ?? 'Warkop & Kafe Santai',
      'desc' => $_ts_style_vintage_kraft_desc ?? 'Kertas kraft cokelat hangat dengan border espresso klasik, stempel kafe, dan suasana warkop santai.',
      'icon' => 'fa-coffee',
    ],
    'glass-gradient' => [
      'name' => $_ts_style_glass_gradient_name ?? 'Modern Frosted Glass',
      'tag'  => $_ts_style_glass_gradient_tag ?? 'Glassmorphism & Clean',
      'desc' => $_ts_style_glass_gradient_desc ?? 'Efek kaca transparan modern (glassmorphism) dengan luminous gradient bar dan pil transparan.',
      'icon' => 'fa-clone',
    ],
    'split-coupon' => [
      'name' => $_ts_style_split_coupon_name ?? 'Compact Split Coupon',
      'tag'  => $_ts_style_split_coupon_tag ?? 'Gaya Kupon Diskon Retail',
      'desc' => $_ts_style_split_coupon_desc ?? 'Format kupon belah horizontal dengan stub harga tebal di samping dan garis gunting perforasi.',
      'icon' => 'fa-scissors',
    ],
  ];

  $allowedColors = [
    'blue' => [
      'name'       => $_ts_color_blue_name ?? 'Ocean Blue (Biru)',
      'border'     => '#0284c7',
      'hdr_start'  => '#0284c7',
      'hdr_end'    => '#0369a1',
      'box_bg'     => '#f0f9ff',
      'accent'     => '#0284c7',
      'badge_bg'   => '#e0f2fe',
      'badge_text' => '#0369a1',
      'neon'       => '#38bdf8',
      'card_bg'    => '#ffffff',
      'dark_card'  => '#0b192c',
      'dark_box'   => '#132a4a',
    ],
    'green' => [
      'name'       => $_ts_color_green_name ?? 'Emerald Green (Hijau)',
      'border'     => '#059669',
      'hdr_start'  => '#059669',
      'hdr_end'    => '#047857',
      'box_bg'     => '#ecfdf5',
      'accent'     => '#059669',
      'badge_bg'   => '#d1fae5',
      'badge_text' => '#065f46',
      'neon'       => '#34d399',
      'card_bg'    => '#ffffff',
      'dark_card'  => '#06281e',
      'dark_box'   => '#0d4233',
    ],
    'purple' => [
      'name'       => $_ts_color_purple_name ?? 'Cyber Purple (Ungu)',
      'border'     => '#6366f1',
      'hdr_start'  => '#6366f1',
      'hdr_end'    => '#4338ca',
      'box_bg'     => '#eef2ff',
      'accent'     => '#6366f1',
      'badge_bg'   => '#e0e7ff',
      'badge_text' => '#3730a3',
      'neon'       => '#818cf8',
      'card_bg'    => '#ffffff',
      'dark_card'  => '#15132d',
      'dark_box'   => '#272352',
    ],
    'orange' => [
      'name'       => $_ts_color_orange_name ?? 'Sunset Orange (Oranye)',
      'border'     => '#ea580c',
      'hdr_start'  => '#ea580c',
      'hdr_end'    => '#c2410c',
      'box_bg'     => '#fff7ed',
      'accent'     => '#ea580c',
      'badge_bg'   => '#ffedd5',
      'badge_text' => '#9a3412',
      'neon'       => '#fb923c',
      'card_bg'    => '#ffffff',
      'dark_card'  => '#241006',
      'dark_box'   => '#3d1b0c',
    ],
    'crimson' => [
      'name'       => $_ts_color_red_name ?? 'Ruby Crimson (Merah)',
      'border'     => '#dc2626',
      'hdr_start'  => '#dc2626',
      'hdr_end'    => '#991b1b',
      'box_bg'     => '#fef2f2',
      'accent'     => '#dc2626',
      'badge_bg'   => '#fee2e2',
      'badge_text' => '#991b1b',
      'neon'       => '#f87171',
      'card_bg'    => '#ffffff',
      'dark_card'  => '#2b0d0d',
      'dark_box'   => '#451212',
    ],
    'teal' => [
      'name'       => $_ts_color_teal_name ?? 'Tropical Teal (Toska/Cyan)',
      'border'     => '#0d9488',
      'hdr_start'  => '#0d9488',
      'hdr_end'    => '#115e59',
      'box_bg'     => '#f0fdfa',
      'accent'     => '#0d9488',
      'badge_bg'   => '#ccfbf1',
      'badge_text' => '#115e59',
      'neon'       => '#2dd4bf',
      'card_bg'    => '#ffffff',
      'dark_card'  => '#04221f',
      'dark_box'   => '#0a3d38',
    ],
    'gold' => [
      'name'       => $_ts_color_gold_name ?? 'Royal Gold (Emas Mewah)',
      'border'     => '#d97706',
      'hdr_start'  => '#d97706',
      'hdr_end'    => '#b45309',
      'box_bg'     => '#fffbeb',
      'accent'     => '#d97706',
      'badge_bg'   => '#fef3c7',
      'badge_text' => '#92400e',
      'neon'       => '#fbbf24',
      'card_bg'    => '#ffffff',
      'dark_card'  => '#241a05',
      'dark_box'   => '#3d2c09',
    ],
    'pink' => [
      'name'       => $_ts_color_pink_name ?? 'Bubblegum Pink (Magenta)',
      'border'     => '#db2777',
      'hdr_start'  => '#db2777',
      'hdr_end'    => '#9d174d',
      'box_bg'     => '#fdf2f8',
      'accent'     => '#db2777',
      'badge_bg'   => '#fce7f3',
      'badge_text' => '#9d174d',
      'neon'       => '#f472b6',
      'card_bg'    => '#ffffff',
      'dark_card'  => '#2b091e',
      'dark_box'   => '#451031',
    ],
    'coffee' => [
      'name'       => $_ts_color_coffee_name ?? 'Espresso Coffee (Cokelat Kopi)',
      'border'     => '#78350f',
      'hdr_start'  => '#78350f',
      'hdr_end'    => '#451a03',
      'box_bg'     => '#fbf7ee',
      'accent'     => '#78350f',
      'badge_bg'   => '#fef3c7',
      'badge_text' => '#78350f',
      'neon'       => '#d97706',
      'card_bg'    => '#ffffff',
      'dark_card'  => '#1c0f05',
      'dark_box'   => '#331b09',
    ],
    'dark' => [
      'name'       => $_ts_color_dark_name ?? 'Midnight Dark (Hitam Elegan)',
      'border'     => '#1e293b',
      'hdr_start'  => '#1e293b',
      'hdr_end'    => '#0f172a',
      'box_bg'     => '#f8fafc',
      'accent'     => '#0f172a',
      'badge_bg'   => '#f1f5f9',
      'badge_text' => '#0f172a',
      'neon'       => '#94a3b8',
      'card_bg'    => '#ffffff',
      'dark_card'  => '#0f172a',
      'dark_box'   => '#1e293b',
    ],
    'monochrome' => [
      'name'       => $_ts_color_mono_name ?? 'Clean Monochrome (Monokrom)',
      'border'     => '#18181b',
      'hdr_start'  => '#27272a',
      'hdr_end'    => '#18181b',
      'box_bg'     => '#ffffff',
      'accent'     => '#18181b',
      'badge_bg'   => '#f4f4f5',
      'badge_text' => '#18181b',
      'neon'       => '#ffffff',
      'card_bg'    => '#ffffff',
      'dark_card'  => '#18181b',
      'dark_box'   => '#27272a',
    ],
  ];

  // Resolve current active configuration
  $activeStyle = 'modern-card';
  $activeColor = 'blue';
  $activeShowPrice = 'yes';
  $activeShowValidity = 'yes';
  $activeShowDataLimit = 'yes';
  $activeShowLogo = 'yes';
  $activeShowHsName = 'yes';
  $activeShowDns = 'yes';
  $activeShowNum = 'yes';

  if (isset($vConfig[$session]) && is_array($vConfig[$session])) {
    $activeStyle = $vConfig[$session]['style'] ?? 'modern-card';
    $activeColor = $vConfig[$session]['color'] ?? 'blue';
    $activeShowPrice = ($vConfig[$session]['show_price'] ?? 'yes') === 'no' ? 'no' : 'yes';
    $activeShowValidity = ($vConfig[$session]['show_validity'] ?? 'yes') === 'no' ? 'no' : 'yes';
    $activeShowDataLimit = ($vConfig[$session]['show_datalimit'] ?? 'yes') === 'no' ? 'no' : 'yes';
    $activeShowLogo = ($vConfig[$session]['show_logo'] ?? 'yes') === 'no' ? 'no' : 'yes';
    $activeShowHsName = ($vConfig[$session]['show_hotspotname'] ?? 'yes') === 'no' ? 'no' : 'yes';
    $activeShowDns = ($vConfig[$session]['show_dns'] ?? 'yes') === 'no' ? 'no' : 'yes';
    $activeShowNum = ($vConfig[$session]['show_num'] ?? 'yes') === 'no' ? 'no' : 'yes';
  } elseif (isset($vThemes[$session])) {
    $oldTheme = $vThemes[$session];
    if ($oldTheme === 'emerald-green') { $activeStyle = 'modern-card'; $activeColor = 'green'; }
    elseif ($oldTheme === 'indigo-purple') { $activeStyle = 'modern-card'; $activeColor = 'purple'; }
    elseif ($oldTheme === 'sunset-orange') { $activeStyle = 'modern-card'; $activeColor = 'orange'; }
    elseif ($oldTheme === 'midnight-dark') { $activeStyle = 'modern-card'; $activeColor = 'dark'; }
    elseif ($oldTheme === 'classic-minimal') { $activeStyle = 'minimal-stamp'; $activeColor = 'monochrome'; }
    else { $activeStyle = 'modern-card'; $activeColor = 'blue'; }
  }

  if (!isset($allowedStyles[$activeStyle])) $activeStyle = 'modern-card';
  if (!isset($allowedColors[$activeColor])) $activeColor = 'blue';

  $galat = '';
  if (isset($_POST["save_voucher_settings"])) {
    $chosenStyle = $_POST["voucher_style"] ?? 'modern-card';
    $chosenColor = $_POST["voucher_color"] ?? 'blue';
    $chosenShowPrice = isset($_POST["show_price"]) ? 'yes' : 'no';
    $chosenShowValidity = isset($_POST["show_validity"]) ? 'yes' : 'no';
    $chosenShowDataLimit = isset($_POST["show_datalimit"]) ? 'yes' : 'no';
    $chosenShowLogo = isset($_POST["show_logo"]) ? 'yes' : 'no';
    $chosenShowHsName = isset($_POST["show_hotspotname"]) ? 'yes' : 'no';
    $chosenShowDns = isset($_POST["show_dns"]) ? 'yes' : 'no';
    $chosenShowNum = isset($_POST["show_num"]) ? 'yes' : 'no';
    
    if (!isset($allowedStyles[$chosenStyle])) $chosenStyle = 'modern-card';
    if (!isset($allowedColors[$chosenColor])) $chosenColor = 'blue';

    $vConfig[$session] = [
      'style' => $chosenStyle,
      'color' => $chosenColor,
      'show_price' => $chosenShowPrice,
      'show_validity' => $chosenShowValidity,
      'show_datalimit' => $chosenShowDataLimit,
      'show_logo' => $chosenShowLogo,
      'show_hotspotname' => $chosenShowHsName,
      'show_dns' => $chosenShowDns,
      'show_num' => $chosenShowNum,
    ];
    $legacyMap = [
      'blue' => 'modern-blue',
      'green' => 'emerald-green',
      'purple' => 'indigo-purple',
      'orange' => 'sunset-orange',
      'dark' => 'midnight-dark',
      'monochrome' => 'classic-minimal',
      'crimson' => 'sunset-orange',
      'teal' => 'modern-blue',
      'gold' => 'sunset-orange',
      'pink' => 'indigo-purple',
      'coffee' => 'sunset-orange',
    ];
    $vThemes[$session] = $legacyMap[$chosenColor] ?? 'modern-blue';

    $content = "<?php\n// Mikhmon Voucher Theme & Style Configuration\n";
    foreach ($vConfig as $s => $cfg) {
      $sEsc = addslashes($s);
      $stEsc = addslashes($cfg['style'] ?? 'modern-card');
      $clEsc = addslashes($cfg['color'] ?? 'blue');
      $spEsc = addslashes($cfg['show_price'] ?? 'yes');
      $svEsc = addslashes($cfg['show_validity'] ?? 'yes');
      $sdEsc = addslashes($cfg['show_datalimit'] ?? 'yes');
      $slEsc = addslashes($cfg['show_logo'] ?? 'yes');
      $shEsc = addslashes($cfg['show_hotspotname'] ?? 'yes');
      $snEsc = addslashes($cfg['show_dns'] ?? 'yes');
      $smEsc = addslashes($cfg['show_num'] ?? 'yes');
      $content .= "\$voucher_config['{$sEsc}'] = ['style' => '{$stEsc}', 'color' => '{$clEsc}', 'show_price' => '{$spEsc}', 'show_validity' => '{$svEsc}', 'show_datalimit' => '{$sdEsc}', 'show_logo' => '{$slEsc}', 'show_hotspotname' => '{$shEsc}', 'show_dns' => '{$snEsc}', 'show_num' => '{$smEsc}'];\n";
    }
    foreach ($vThemes as $s => $t) {
      $content .= "\$voucher_theme['" . addslashes($s) . "'] = '" . addslashes($t) . "';\n";
    }

    if (@file_put_contents($configFile, $content) !== false) {
      $activeStyle = $chosenStyle;
      $activeColor = $chosenColor;
      $activeShowPrice = $chosenShowPrice;
      $activeShowValidity = $chosenShowValidity;
      $activeShowDataLimit = $chosenShowDataLimit;
      $activeShowLogo = $chosenShowLogo;
      $activeShowHsName = $chosenShowHsName;
      $activeShowDns = $chosenShowDns;
      $activeShowNum = $chosenShowNum;

      $galat = '<div class="box bg-success"><i class="fa fa-check"></i> ' .
        sprintf(
          $_ts_save_success ?? '<b>Berhasil Disimpan!</b> Template voucher <b>%s</b> (%s) telah diterapkan untuk sesi <b>%s</b>.',
          htmlspecialchars($allowedStyles[$chosenStyle]['name'] ?? $chosenStyle),
          htmlspecialchars($allowedColors[$chosenColor]['name'] ?? $chosenColor),
          htmlspecialchars($session)
        ) .
        '</div>';
    } else {
      $galat = '<div class="box bg-danger"><i class="fa fa-warning"></i> ' .
        ($_ts_save_failed ?? 'Gagal menyimpan konfigurasi template ke disk.') .
        '</div>';
    }
  }

  $cInit = $allowedColors[$activeColor];
}

$popup = "javascript:window.open('./voucher/vpreview.php?usermode=up&qr=yes&session=" . $session . "','_blank','width=340,height=440')";
?>

<style>
/* Dynamic CSS Variables for Live Voucher Miniature Previews */
:root {
  --c-border: <?= $cInit['border']; ?>;
  --c-hdr-start: <?= $cInit['hdr_start']; ?>;
  --c-hdr-end: <?= $cInit['hdr_end']; ?>;
  --c-box-bg: <?= $cInit['box_bg']; ?>;
  --c-accent: <?= $cInit['accent']; ?>;
  --c-badge-bg: <?= $cInit['badge_bg']; ?>;
  --c-badge-text: <?= $cInit['badge_text']; ?>;
  --c-neon: <?= $cInit['neon']; ?>;
  --c-dark-card: <?= $cInit['dark_card']; ?>;
  --c-dark-box: <?= $cInit['dark_box']; ?>;
}

.ts-style-box {
  transition: transform 0.15s ease, box-shadow 0.15s ease, border-color 0.15s ease;
  height: 100%;
}
.ts-style-box:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}
.active-template {
  border: 2px solid #008BC9 !important;
  box-shadow: 0 0 10px rgba(0, 139, 201, 0.45) !important;
}

/* MINI VOUCHER PREVIEW CARDS */
.mv-box {
  width: 175px;
  max-width: 100%;
  margin: 0 auto;
  font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
  user-select: none;
  font-size: 8.5px;
  box-sizing: border-box;
}

/* 1. Modern Card Mini */
.mv-modern {
  background: #ffffff;
  border: 1.5px solid var(--c-border);
  border-radius: 6px;
  overflow: hidden;
  box-shadow: 0 2px 6px rgba(0,0,0,0.08);
}
.mv-modern-hdr {
  background: linear-gradient(135deg, var(--c-hdr-start) 0%, var(--c-hdr-end) 100%);
  color: #ffffff;
  padding: 4px 6px;
  font-weight: 800;
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.mv-modern-body {
  padding: 5px;
  background: #ffffff;
}
.mv-modern-codebox {
  background: var(--c-box-bg);
  border: 1px dashed var(--c-border);
  border-radius: 4px;
  padding: 3px;
  text-align: center;
}
.mv-modern-meta {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-top: 4px;
}

/* 2. Ticket Notch Mini */
.mv-ticket {
  background: #ffffff;
  border: 1.5px dashed var(--c-border);
  border-radius: 6px;
  overflow: hidden;
  box-shadow: 0 2px 6px rgba(0,0,0,0.08);
}
.mv-ticket-hdr {
  background: linear-gradient(135deg, var(--c-hdr-start), var(--c-hdr-end));
  color: #ffffff;
  padding: 3px 6px;
  font-weight: 800;
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.mv-ticket-inner {
  display: table;
  width: 100%;
  padding: 4px;
  background: #ffffff;
}
.mv-ticket-left {
  display: table-cell;
  vertical-align: middle;
  width: 110px;
  padding-right: 3px;
}
.mv-ticket-right {
  display: table-cell;
  vertical-align: middle;
  width: 44px;
  border-left: 1px dashed var(--c-border);
  padding-left: 3px;
  text-align: center;
}

/* 3. Curved Wave Mini */
.mv-wave {
  background: #ffffff;
  border: 1.5px solid var(--c-border);
  border-radius: 6px;
  overflow: hidden;
  box-shadow: 0 2px 6px rgba(0,0,0,0.08);
}
.mv-wave-hdr {
  background: var(--c-accent);
  color: #ffffff;
  padding: 4px 6px 6px;
  font-weight: 800;
  display: flex;
  justify-content: space-between;
  align-items: center;
  border-radius: 0 0 50% 50% / 0 0 8px 8px;
}

/* 4. Industrial Grid Mini */
.mv-brutal {
  background: #ffffff;
  border: 1.5px solid var(--c-border);
  border-radius: 0;
  box-shadow: 2px 2px 0px var(--c-border);
}
.mv-brutal-hdr {
  background: var(--c-border);
  color: #ffffff;
  padding: 3px 5px;
  font-family: monospace;
  font-weight: 900;
  font-size: 8px;
  display: flex;
  justify-content: space-between;
}

/* 5. Cyber Neon Mini */
.mv-cyber {
  background: var(--c-dark-card);
  border: 1.5px solid var(--c-neon);
  border-radius: 5px;
  color: #f8fafc;
  box-shadow: 0 0 8px rgba(0,0,0,0.4);
}
.mv-cyber-hdr {
  border-bottom: 1px solid var(--c-neon);
  background: rgba(0,0,0,0.4);
  padding: 3px 5px;
  font-weight: 800;
  display: flex;
  justify-content: space-between;
  align-items: center;
  color: var(--c-neon);
}

/* 6. Clean Minimal Stamp Mini */
.mv-stamp {
  background: #ffffff;
  border: 2px double var(--c-border);
  border-radius: 3px;
  padding: 3px 4px;
}
.mv-stamp-hdr {
  border-bottom: 1px solid var(--c-border);
  padding-bottom: 2px;
  font-weight: 900;
  display: flex;
  justify-content: space-between;
  color: var(--c-accent);
}

/* 7. Retro Arcade Mini */
.mv-retro {
  background: #ffffff;
  border: 2px solid #0f172a;
  box-shadow: 2px 2px 0px #0f172a;
  border-radius: 0;
}
.mv-retro-hdr {
  background: #0f172a;
  color: var(--c-neon);
  padding: 3px 5px;
  font-family: monospace;
  font-weight: 900;
  font-size: 7.5px;
  display: flex;
  justify-content: space-between;
}

/* 8. Luxury VIP Ribbon Mini */
.mv-luxury {
  background: #0f172a;
  border: 1.5px solid #d97706;
  border-radius: 6px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.3);
  color: #f8fafc;
}
.mv-luxury-hdr {
  background: #1e293b;
  border-bottom: 1px solid #d97706;
  padding: 3px 5px;
  color: #fbbf24;
  font-weight: 900;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

/* 9. Synthwave Sunset Mini */
.mv-synthwave {
  background: #150d2a;
  border: 1.5px solid #ec4899;
  border-radius: 6px;
  color: #fff;
  box-shadow: 0 0 8px rgba(236,72,153,0.35);
}
.mv-synthwave-hdr {
  background: linear-gradient(90deg, #ec4899, #8b5cf6, #06b6d4);
  color: #fff;
  padding: 3px 5px;
  font-weight: 900;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

/* 10. Vintage Kraft Paper Mini */
.mv-kraft {
  background: #fbf7ee;
  border: 1.5px dashed #78350f;
  border-radius: 6px;
  color: #451a03;
}
.mv-kraft-hdr {
  background: #78350f;
  color: #fef3c7;
  padding: 3px 5px;
  font-weight: 900;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

/* 11. Modern Frosted Glass Mini */
.mv-glass {
  background: #ffffff;
  border: 1.5px solid #e2e8f0;
  border-radius: 8px;
  box-shadow: 0 3px 10px rgba(0,0,0,0.06);
  overflow: hidden;
}
.mv-glass-bar {
  height: 3px;
  background: linear-gradient(90deg, var(--c-accent), #a855f7, #ec4899);
}
.mv-glass-hdr {
  padding: 3px 6px;
  font-weight: 800;
  display: flex;
  justify-content: space-between;
  align-items: center;
  color: #0f172a;
}

/* 12. Compact Split Coupon Mini */
.mv-split {
  background: #ffffff;
  border: 1px solid var(--c-border);
  border-radius: 6px;
  overflow: hidden;
  box-shadow: 0 2px 6px rgba(0,0,0,0.06);
  display: table;
  width: 100%;
}
.mv-split-left {
  display: table-cell;
  width: 45px;
  background: var(--c-accent);
  color: #ffffff;
  vertical-align: middle;
  text-align: center;
  padding: 4px 2px;
  border-right: 1px dashed rgba(255,255,255,0.6);
}
.mv-split-right {
  display: table-cell;
  vertical-align: middle;
  padding: 4px;
  background: #ffffff;
}

.mv-qr-mock {
  width: 34px;
  height: 34px;
  background: #ffffff;
  border: 1px solid #cbd5e1;
  border-radius: 4px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto;
  color: #0f172a;
}
</style>

<div class="row">
  <div class="col-12">
    <div class="card box-bordered">
      <div class="card-header">
        <h3><i class="fa fa-paint-brush"></i> <?= $_ts_voucher_template_selector ?? 'Pemilih Template & Gaya Voucher'; ?></h3>
      </div>
      <div class="card-body">
        <?= $galat; ?>

        <form autocomplete="off" method="post" action="">
          <div>
            <button type="submit" class="btn bg-primary" name="save_voucher_settings"><i class="fa fa-save"></i> <?= $_save ?></button>
            <a class="btn bg-secondary" href="<?= $popup; ?>" title="Live Preview Voucher"><i class="fa fa-eye"></i> Live Preview</a>
          </div>

          <div class="mr-t-10">
            <table class="table">
              <tr>
                <td class="align-middle" style="width: 200px;">
                  <b><?= $_ts_template_design_style ?? 'Gaya Desain Template'; ?></b>
                </td>
                <td>
                  <select class="form-control" name="voucher_style" id="voucher_style" onchange="syncFromUI()">
                    <?php foreach ($allowedStyles as $stKey => $st): ?>
                      <option value="<?= htmlspecialchars($stKey); ?>" <?= ($activeStyle === $stKey) ? 'selected' : ''; ?>>
                        <?= htmlspecialchars($st['name']); ?> (<?= htmlspecialchars($st['tag']); ?>)
                      </option>
                    <?php endforeach; ?>
                  </select>
                </td>
              </tr>
              <tr>
                <td class="align-middle">
                  <b><?= $_ts_color_palette_theme ?? 'Palet Tema Warna'; ?></b>
                </td>
                <td>
                  <select class="form-control" name="voucher_color" id="voucher_color" onchange="syncFromUI()">
                    <?php foreach ($allowedColors as $cKey => $col): ?>
                      <option value="<?= htmlspecialchars($cKey); ?>" <?= ($activeColor === $cKey) ? 'selected' : ''; ?>>
                        <?= htmlspecialchars($col['name']); ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </td>
              </tr>
            </table>
          </div>

          <!-- TABEL PEMILIHAN ELEMEN VOUCHER (SEPERTI TABEL PEMILIHAN PROFIL TOKO ONLINE) -->
          <div class="box-bordered mr-t-10" style="padding: 12px; border-radius: 4px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; flex-wrap: wrap; gap: 6px;">
              <div>
                <b style="font-size: 13px;"><i class="fa fa-th-list text-primary"></i> <?= $_ts_voucher_elements ?? 'Daftar Elemen Voucher'; ?></b>
                <div class="text-secondary" style="font-size: 11px; margin-top: 2px;"><?= $_ts_check_elements ?? 'Centang informasi yang ingin ditampilkan pada voucher:'; ?></div>
              </div>
              <div>
                <button type="button" class="btn bg-secondary" style="padding: 3px 8px; font-size: 11px; margin: 0 2px;" onclick="var c=document.querySelectorAll('.ts-elem-cb'); c.forEach(function(i){i.checked=true;}); syncFromUI();"><i class="fa fa-check-square-o"></i> <?= $_ts_select_all ?? 'Pilih Semua'; ?></button>
                <button type="button" class="btn bg-secondary" style="padding: 3px 8px; font-size: 11px; margin: 0 2px;" onclick="var c=document.querySelectorAll('.ts-elem-cb'); c.forEach(function(i){i.checked=false;}); syncFromUI();"><i class="fa fa-square-o"></i> <?= $_ts_deselect_all ?? 'Batal Semua'; ?></button>
              </div>
            </div>

            <div class="overflow" style="max-height: 280px; margin-top: 6px;">
              <table class="table table-bordered table-hover text-nowrap" style="margin-bottom: 0;">
                <thead>
                  <tr>
                    <th style="width: 40px; text-align: center;">#</th>
                    <th><?= $_ts_element_name ?? 'Nama Elemen'; ?></th>
                    <th><?= $_ts_element_desc ?? 'Posisi / Keterangan'; ?></th>
                    <th><?= $_ts_element_sample ?? 'Contoh Tampilan'; ?></th>
                  </tr>
                </thead>
                <tbody>
                  <tr style="cursor: pointer;" onclick="var cb=this.querySelector('.ts-elem-cb'); if(event.target!==cb){cb.checked=!cb.checked; syncFromUI();}">
                    <td style="text-align: center; vertical-align: middle;">
                      <input type="checkbox" class="ts-elem-cb" name="show_price" id="cb_show_price" value="yes" <?= ($activeShowPrice === 'yes') ? 'checked' : ''; ?> style="cursor: pointer;" onclick="event.stopPropagation(); syncFromUI();">
                    </td>
                    <td style="vertical-align: middle;">
                      <b><i class="fa fa-tag text-primary"></i> <?= $_ts_price ?? 'Harga Voucher'; ?></b>
                    </td>
                    <td style="vertical-align: middle;">
                      <?= $_ts_price_desc ?? 'Badge harga paket voucher'; ?>
                    </td>
                    <td style="vertical-align: middle;">
                      <?= ($currency ?: 'Rp') . ' 5.000'; ?>
                    </td>
                  </tr>

                  <tr style="cursor: pointer;" onclick="var cb=this.querySelector('.ts-elem-cb'); if(event.target!==cb){cb.checked=!cb.checked; syncFromUI();}">
                    <td style="text-align: center; vertical-align: middle;">
                      <input type="checkbox" class="ts-elem-cb" name="show_validity" id="cb_show_validity" value="yes" <?= ($activeShowValidity === 'yes') ? 'checked' : ''; ?> style="cursor: pointer;" onclick="event.stopPropagation(); syncFromUI();">
                    </td>
                    <td style="vertical-align: middle;">
                      <b><i class="fa fa-clock-o text-green"></i> <?= $_ts_validity ?? 'Masa Aktif (Validity / Time Limit)'; ?></b>
                    </td>
                    <td style="vertical-align: middle;">
                      <?= $_ts_validity_desc ?? 'Durasi batas waktu masa aktif voucher'; ?>
                    </td>
                    <td style="vertical-align: middle;">
                      1 Hari / 6 Jam
                    </td>
                  </tr>

                  <tr style="cursor: pointer;" onclick="var cb=this.querySelector('.ts-elem-cb'); if(event.target!==cb){cb.checked=!cb.checked; syncFromUI();}">
                    <td style="text-align: center; vertical-align: middle;">
                      <input type="checkbox" class="ts-elem-cb" name="show_datalimit" id="cb_show_datalimit" value="yes" <?= ($activeShowDataLimit === 'yes') ? 'checked' : ''; ?> style="cursor: pointer;" onclick="event.stopPropagation(); syncFromUI();">
                    </td>
                    <td style="vertical-align: middle;">
                      <b><i class="fa fa-database text-info"></i> <?= $_ts_datalimit ?? 'Batas Kuota (Data Limit)'; ?></b>
                    </td>
                    <td style="vertical-align: middle;">
                      <?= $_ts_datalimit_desc ?? 'Batas kuota internet paket voucher'; ?>
                    </td>
                    <td style="vertical-align: middle;">
                      1 GB / 500 MB
                    </td>
                  </tr>

                  <tr style="cursor: pointer;" onclick="var cb=this.querySelector('.ts-elem-cb'); if(event.target!==cb){cb.checked=!cb.checked; syncFromUI();}">
                    <td style="text-align: center; vertical-align: middle;">
                      <input type="checkbox" class="ts-elem-cb" name="show_logo" id="cb_show_logo" value="yes" <?= ($activeShowLogo === 'yes') ? 'checked' : ''; ?> style="cursor: pointer;" onclick="event.stopPropagation(); syncFromUI();">
                    </td>
                    <td style="vertical-align: middle;">
                      <b><i class="fa fa-image text-warning"></i> <?= $_ts_logo ?? 'Logo Hotspot'; ?></b>
                    </td>
                    <td style="vertical-align: middle;">
                      <?= $_ts_logo_desc ?? 'Gambar logo hotspot pada header voucher'; ?>
                    </td>
                    <td style="vertical-align: middle;">
                      Logo Hotspot
                    </td>
                  </tr>

                  <tr style="cursor: pointer;" onclick="var cb=this.querySelector('.ts-elem-cb'); if(event.target!==cb){cb.checked=!cb.checked; syncFromUI();}">
                    <td style="text-align: center; vertical-align: middle;">
                      <input type="checkbox" class="ts-elem-cb" name="show_hotspotname" id="cb_show_hotspotname" value="yes" <?= ($activeShowHsName === 'yes') ? 'checked' : ''; ?> style="cursor: pointer;" onclick="event.stopPropagation(); syncFromUI();">
                    </td>
                    <td style="vertical-align: middle;">
                      <b><i class="fa fa-wifi text-primary"></i> <?= $_ts_hotspotname ?? 'Nama Hotspot'; ?></b>
                    </td>
                    <td style="vertical-align: middle;">
                      <?= $_ts_hotspotname_desc ?? 'Teks judul nama hotspot pada header'; ?>
                    </td>
                    <td style="vertical-align: middle;">
                      <?= htmlspecialchars($hotspotname ?: 'HOTSPOT'); ?>
                    </td>
                  </tr>

                  <tr style="cursor: pointer;" onclick="var cb=this.querySelector('.ts-elem-cb'); if(event.target!==cb){cb.checked=!cb.checked; syncFromUI();}">
                    <td style="text-align: center; vertical-align: middle;">
                      <input type="checkbox" class="ts-elem-cb" name="show_dns" id="cb_show_dns" value="yes" <?= ($activeShowDns === 'yes') ? 'checked' : ''; ?> style="cursor: pointer;" onclick="event.stopPropagation(); syncFromUI();">
                    </td>
                    <td style="vertical-align: middle;">
                      <b><i class="fa fa-link text-secondary"></i> <?= $_ts_dns ?? 'DNS / Link Login'; ?></b>
                    </td>
                    <td style="vertical-align: middle;">
                      <?= $_ts_dns_desc ?? 'Alamat URL / DNS login MikroTik di footer'; ?>
                    </td>
                    <td style="vertical-align: middle;">
                      http://<?= htmlspecialchars($dnsname ?: 'hotspot.net'); ?>
                    </td>
                  </tr>

                  <tr style="cursor: pointer;" onclick="var cb=this.querySelector('.ts-elem-cb'); if(event.target!==cb){cb.checked=!cb.checked; syncFromUI();}">
                    <td style="text-align: center; vertical-align: middle;">
                      <input type="checkbox" class="ts-elem-cb" name="show_num" id="cb_show_num" value="yes" <?= ($activeShowNum === 'yes') ? 'checked' : ''; ?> style="cursor: pointer;" onclick="event.stopPropagation(); syncFromUI();">
                    </td>
                    <td style="vertical-align: middle;">
                      <b><i class="fa fa-list-ol"></i> <?= $_ts_voucher_index_num ?? 'Nomor Urut Voucher'; ?></b>
                    </td>
                    <td style="vertical-align: middle;">
                      <?= $_ts_voucher_index_num_desc ?? 'Nomor urutan cetak (#1, #2, #3) pada voucher'; ?>
                    </td>
                    <td style="vertical-align: middle;">
                      #1, #2, #3
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <small class="text-secondary" style="display: block; margin-top: 8px;">
              <i class="fa fa-info-circle text-primary"></i> <?= $_ts_check_elements ?? 'Elemen yang tidak dicentang otomatis disembunyikan dari cetak voucher.'; ?>
            </small>
          </div>

          <div class="mr-t-10">
            <h3><i class="fa fa-th"></i> <?= $_ts_styles_catalog ?? 'Katalog & Pilihan Langsung Gaya Template'; ?></h3>
            <div class="row mr-t-10">

              <!-- 1. Modern Bento Card -->
              <div class="col-4 col-box-12 mr-b-10">
                <div class="card box-bordered ts-style-box <?= ($activeStyle === 'modern-card') ? 'active-template' : ''; ?>" id="card_modern-card" onclick="selectStyleCard('modern-card')">
                  <div class="card-header">
                    <h3 style="font-size: 13px;"><i class="fa fa-th-large"></i> <?= htmlspecialchars($allowedStyles['modern-card']['name']); ?></h3>
                  </div>
                  <div class="card-body text-center" style="padding: 10px;">
                    <div class="mv-box mv-modern">
                      <div class="mv-modern-hdr">
                        <div style="display: flex; align-items: center; gap: 4px;">
                          <span class="mv-hsname-el" style="<?= ($activeShowHsName === 'no') ? 'display: none;' : ''; ?>"><?= htmlspecialchars($hotspotname ?: 'HOTSPOT'); ?></span>
                        </div>
                        <span class="mv-num-el" style="background: rgba(255,255,255,0.25); padding: 0 4px; border-radius: 3px; <?= ($activeShowNum === 'no') ? 'display: none;' : ''; ?>">#1</span>
                      </div>
                      <div class="mv-modern-body">
                        <div style="display: table; width: 100%;">
                          <div style="display: table-cell; vertical-align: middle; padding-right: 4px;">
                            <div class="mv-modern-codebox">
                              <div style="font-size: 6.5px; font-weight: 800; color: var(--c-accent);">KODE VOUCHER</div>
                              <div style="font-family: monospace; font-size: 11px; font-weight: 900; color: #0f172a; letter-spacing: 1px;">VCR-89240</div>
                            </div>
                          </div>
                          <div style="display: table-cell; vertical-align: middle; width: 40px; text-align: center;">
                            <div class="mv-qr-mock"><i class="fa fa-qrcode" style="font-size: 24px;"></i></div>
                          </div>
                        </div>
                        <div class="mv-modern-meta">
                          <div class="mv-price-el" style="<?= ($activeShowPrice === 'no') ? 'display: none;' : ''; ?>">
                            <span style="background: var(--c-badge-bg); color: var(--c-badge-text); padding: 1px 4px; border-radius: 3px; font-weight: 800;">
                              <?= ($currency ?: 'Rp') . ' 5.000'; ?>
                            </span>
                          </div>
                          <div class="mv-validity-el" style="<?= ($activeShowValidity === 'no') ? 'display: none;' : ''; ?>">
                            <span style="color: #64748b; font-weight: 700;"><i class="fa fa-clock-o"></i> 1 <?= strtoupper($_day ?? 'HARI'); ?></span>
                          </div>
                        </div>
                        <div class="mv-dns-el" style="font-size: 7.5px; color: #64748b; margin-top: 3px; border-top: 1px dotted #e2e8f0; padding-top: 2px; text-align: left; <?= ($activeShowDns === 'no') ? 'display: none;' : ''; ?>">
                          Login: <?= htmlspecialchars($dnsname ?: 'hotspot.net'); ?>
                        </div>
                      </div>
                    </div>
                    <div style="font-size: 11px; margin-top: 8px; opacity: 0.85; line-height: 1.35;">
                      <?= htmlspecialchars($allowedStyles['modern-card']['desc']); ?>
                    </div>
                  </div>
                  <div class="card-footer text-center" style="padding: 5px;">
                    <span class="btn btn-sm <?= ($activeStyle === 'modern-card') ? 'bg-primary' : 'bg-secondary'; ?> select-btn" id="btn_modern-card" style="margin: 0; font-size: 12px;">
                      <i class="fa <?= ($activeStyle === 'modern-card') ? 'fa-check' : 'fa-circle-o'; ?>"></i> <?= ($activeStyle === 'modern-card') ? ($_ts_selected ?? 'Terpilih') : ($_ts_select_theme ?? 'Pilih Gaya Ini'); ?>
                    </span>
                  </div>
                </div>
              </div>

              <!-- 2. Ticket Notch -->
              <div class="col-4 col-box-12 mr-b-10">
                <div class="card box-bordered ts-style-box <?= ($activeStyle === 'ticket-notch') ? 'active-template' : ''; ?>" id="card_ticket-notch" onclick="selectStyleCard('ticket-notch')">
                  <div class="card-header">
                    <h3 style="font-size: 13px;"><i class="fa fa-ticket"></i> <?= htmlspecialchars($allowedStyles['ticket-notch']['name']); ?></h3>
                  </div>
                  <div class="card-body text-center" style="padding: 10px;">
                    <div class="mv-box mv-ticket">
                      <div class="mv-ticket-hdr">
                        <span class="mv-hsname-el" style="<?= ($activeShowHsName === 'no') ? 'display: none;' : ''; ?>"><?= htmlspecialchars($hotspotname ?: 'HOTSPOT'); ?></span>
                        <span class="mv-num-el" style="background: rgba(255,255,255,0.25); padding: 0 4px; border-radius: 3px; <?= ($activeShowNum === 'no') ? 'display: none;' : ''; ?>">#1</span>
                      </div>
                      <div class="mv-ticket-inner">
                        <div class="mv-ticket-left">
                          <div style="background: var(--c-box-bg); border: 1px solid var(--c-border); border-radius: 4px; padding: 2px 3px; text-align: center;">
                            <div style="font-size: 6px; font-weight: 800; color: var(--c-accent);">PASS CODE</div>
                            <div style="font-family: monospace; font-size: 10.5px; font-weight: 900; color: #0f172a;">VCR-89240</div>
                          </div>
                          <div style="display: flex; justify-content: space-between; margin-top: 3px; font-size: 7.5px;">
                            <div class="mv-price-el" style="<?= ($activeShowPrice === 'no') ? 'display: none;' : ''; ?>">
                              <span style="font-weight: 900; color: var(--c-accent);"><?= ($currency ?: 'Rp') . ' 5.000'; ?></span>
                            </div>
                            <div class="mv-validity-el" style="<?= ($activeShowValidity === 'no') ? 'display: none;' : ''; ?>">
                              <span style="color: #475569; font-weight: 700;">1 <?= strtoupper($_day ?? 'HARI'); ?></span>
                            </div>
                          </div>
                        </div>
                        <div class="mv-ticket-right">
                          <div class="mv-qr-mock" style="width: 32px; height: 32px;"><i class="fa fa-qrcode" style="font-size: 20px;"></i></div>
                          <div style="font-size: 5.5px; font-weight: 800; color: var(--c-accent); margin-top: 1px;">SCAN</div>
                        </div>
                      </div>
                    </div>
                    <div style="font-size: 11px; margin-top: 8px; opacity: 0.85; line-height: 1.35;">
                      <?= htmlspecialchars($allowedStyles['ticket-notch']['desc']); ?>
                    </div>
                  </div>
                  <div class="card-footer text-center" style="padding: 5px;">
                    <span class="btn btn-sm <?= ($activeStyle === 'ticket-notch') ? 'bg-primary' : 'bg-secondary'; ?> select-btn" id="btn_ticket-notch" style="margin: 0; font-size: 12px;">
                      <i class="fa <?= ($activeStyle === 'ticket-notch') ? 'fa-check' : 'fa-circle-o'; ?>"></i> <?= ($activeStyle === 'ticket-notch') ? ($_ts_selected ?? 'Terpilih') : ($_ts_select_theme ?? 'Pilih Gaya Ini'); ?>
                    </span>
                  </div>
                </div>
              </div>

              <!-- 3. Curved Wave -->
              <div class="col-4 col-box-12 mr-b-10">
                <div class="card box-bordered ts-style-box <?= ($activeStyle === 'curved-wave') ? 'active-template' : ''; ?>" id="card_curved-wave" onclick="selectStyleCard('curved-wave')">
                  <div class="card-header">
                    <h3 style="font-size: 13px;"><i class="fa fa-tint"></i> <?= htmlspecialchars($allowedStyles['curved-wave']['name']); ?></h3>
                  </div>
                  <div class="card-body text-center" style="padding: 10px;">
                    <div class="mv-box mv-wave">
                      <div class="mv-wave-hdr">
                        <span class="mv-hsname-el" style="<?= ($activeShowHsName === 'no') ? 'display: none;' : ''; ?>"><?= htmlspecialchars($hotspotname ?: 'HOTSPOT'); ?></span>
                        <span class="mv-num-el" style="background: rgba(255,255,255,0.25); padding: 0 4px; border-radius: 99px; <?= ($activeShowNum === 'no') ? 'display: none;' : ''; ?>">#1</span>
                      </div>
                      <div style="padding: 5px; background: #ffffff;">
                        <div style="display: table; width: 100%;">
                          <div style="display: table-cell; vertical-align: middle; padding-right: 4px;">
                            <div style="background: var(--c-box-bg); border: 1px dashed var(--c-border); border-radius: 6px; padding: 3px; text-align: center;">
                              <div style="font-size: 6.5px; font-weight: 800; color: var(--c-accent);">KODE VOUCHER</div>
                              <div style="font-family: monospace; font-size: 11px; font-weight: 900; color: #0f172a;">VCR-89240</div>
                            </div>
                          </div>
                          <div style="display: table-cell; vertical-align: middle; width: 38px; text-align: center;">
                            <div class="mv-qr-mock"><i class="fa fa-qrcode" style="font-size: 22px;"></i></div>
                          </div>
                        </div>
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 4px;">
                          <div class="mv-price-el" style="<?= ($activeShowPrice === 'no') ? 'display: none;' : ''; ?>">
                            <span style="background: var(--c-badge-bg); color: var(--c-badge-text); padding: 1px 5px; border-radius: 99px; font-weight: 800;">
                              <?= ($currency ?: 'Rp') . ' 5.000'; ?>
                            </span>
                          </div>
                          <div class="mv-validity-el" style="<?= ($activeShowValidity === 'no') ? 'display: none;' : ''; ?>">
                            <span style="color: #64748b; font-weight: 700;">1 <?= strtoupper($_day ?? 'HARI'); ?></span>
                          </div>
                        </div>
                      </div>
                    </div>
                    <div style="font-size: 11px; margin-top: 8px; opacity: 0.85; line-height: 1.35;">
                      <?= htmlspecialchars($allowedStyles['curved-wave']['desc']); ?>
                    </div>
                  </div>
                  <div class="card-footer text-center" style="padding: 5px;">
                    <span class="btn btn-sm <?= ($activeStyle === 'curved-wave') ? 'bg-primary' : 'bg-secondary'; ?> select-btn" id="btn_curved-wave" style="margin: 0; font-size: 12px;">
                      <i class="fa <?= ($activeStyle === 'curved-wave') ? 'fa-check' : 'fa-circle-o'; ?>"></i> <?= ($activeStyle === 'curved-wave') ? ($_ts_selected ?? 'Terpilih') : ($_ts_select_theme ?? 'Pilih Gaya Ini'); ?>
                    </span>
                  </div>
                </div>
              </div>

              <!-- 4. Industrial Brutalist -->
              <div class="col-4 col-box-12 mr-b-10">
                <div class="card box-bordered ts-style-box <?= ($activeStyle === 'industrial-grid') ? 'active-template' : ''; ?>" id="card_industrial-grid" onclick="selectStyleCard('industrial-grid')">
                  <div class="card-header">
                    <h3 style="font-size: 13px;"><i class="fa fa-cubes"></i> <?= htmlspecialchars($allowedStyles['industrial-grid']['name']); ?></h3>
                  </div>
                  <div class="card-body text-center" style="padding: 10px;">
                    <div class="mv-box mv-brutal">
                      <div class="mv-brutal-hdr">
                        <span class="mv-hsname-el" style="<?= ($activeShowHsName === 'no') ? 'display: none;' : ''; ?>"><?= strtoupper(htmlspecialchars($hotspotname ?: 'HOTSPOT')); ?></span>
                        <span class="mv-num-el" style="<?= ($activeShowNum === 'no') ? 'display: none;' : ''; ?>">#1</span>
                      </div>
                      <div style="padding: 4px; background: #ffffff;">
                        <div style="display: table; width: 100%;">
                          <div style="display: table-cell; vertical-align: middle; padding-right: 3px;">
                            <div style="border: 1px solid var(--c-border); background: var(--c-box-bg); padding: 2px; text-align: center;">
                              <div style="font-size: 6px; font-weight: 900; font-family: monospace;">[ ACCESS CODE ]</div>
                              <div style="font-family: monospace; font-size: 10.5px; font-weight: 900; color: #000;">VCR-89240</div>
                            </div>
                          </div>
                          <div style="display: table-cell; vertical-align: middle; width: 38px; text-align: center;">
                            <div class="mv-qr-mock" style="border-radius: 0; border: 1px solid var(--c-border);"><i class="fa fa-qrcode" style="font-size: 22px;"></i></div>
                          </div>
                        </div>
                        <div style="border: 1px solid var(--c-border); margin-top: 3px; padding: 2px 4px; display: flex; justify-content: space-between; font-family: monospace; font-size: 7.5px;">
                          <div class="mv-price-el" style="<?= ($activeShowPrice === 'no') ? 'display: none;' : ''; ?>">
                            <span style="font-weight: 900; color: var(--c-accent);"><?= ($currency ?: 'Rp') . ' 5.000'; ?></span>
                          </div>
                          <div class="mv-validity-el" style="<?= ($activeShowValidity === 'no') ? 'display: none;' : ''; ?>">
                            <span>1 <?= strtoupper($_day ?? 'HARI'); ?></span>
                          </div>
                        </div>
                      </div>
                    </div>
                    <div style="font-size: 11px; margin-top: 8px; opacity: 0.85; line-height: 1.35;">
                      <?= htmlspecialchars($allowedStyles['industrial-grid']['desc']); ?>
                    </div>
                  </div>
                  <div class="card-footer text-center" style="padding: 5px;">
                    <span class="btn btn-sm <?= ($activeStyle === 'industrial-grid') ? 'bg-primary' : 'bg-secondary'; ?> select-btn" id="btn_industrial-grid" style="margin: 0; font-size: 12px;">
                      <i class="fa <?= ($activeStyle === 'industrial-grid') ? 'fa-check' : 'fa-circle-o'; ?>"></i> <?= ($activeStyle === 'industrial-grid') ? ($_ts_selected ?? 'Terpilih') : ($_ts_select_theme ?? 'Pilih Gaya Ini'); ?>
                    </span>
                  </div>
                </div>
              </div>

              <!-- 5. Cyber Gaming Dark Neon -->
              <div class="col-4 col-box-12 mr-b-10">
                <div class="card box-bordered ts-style-box <?= ($activeStyle === 'cyber-neon') ? 'active-template' : ''; ?>" id="card_cyber-neon" onclick="selectStyleCard('cyber-neon')">
                  <div class="card-header">
                    <h3 style="font-size: 13px;"><i class="fa fa-gamepad"></i> <?= htmlspecialchars($allowedStyles['cyber-neon']['name']); ?></h3>
                  </div>
                  <div class="card-body text-center" style="padding: 10px;">
                    <div class="mv-box mv-cyber">
                      <div class="mv-cyber-hdr">
                        <span class="mv-hsname-el" style="<?= ($activeShowHsName === 'no') ? 'display: none;' : ''; ?>"><?= htmlspecialchars($hotspotname ?: 'HOTSPOT'); ?></span>
                        <span class="mv-num-el" style="font-size: 7px; border: 1px solid var(--c-neon); padding: 0 3px; border-radius: 2px; <?= ($activeShowNum === 'no') ? 'display: none;' : ''; ?>">#1</span>
                      </div>
                      <div style="padding: 4px;">
                        <div style="display: table; width: 100%;">
                          <div style="display: table-cell; vertical-align: middle; padding-right: 4px;">
                            <div style="background: var(--c-dark-box); border: 1px solid var(--c-neon); border-radius: 3px; padding: 3px; text-align: center;">
                              <div style="font-size: 6px; font-weight: 800; color: var(--c-neon);">ACCESS KEY</div>
                              <div style="font-family: monospace; font-size: 10.5px; font-weight: 900; color: #fff; text-shadow: 0 0 3px var(--c-neon);">VCR-89240</div>
                            </div>
                          </div>
                          <div style="display: table-cell; vertical-align: middle; width: 38px; text-align: center; background: #fff; border-radius: 3px; padding: 2px;">
                            <div class="mv-qr-mock" style="border: 0;"><i class="fa fa-qrcode" style="font-size: 22px; color: #000;"></i></div>
                          </div>
                        </div>
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 4px; font-size: 7.5px;">
                          <div class="mv-price-el" style="<?= ($activeShowPrice === 'no') ? 'display: none;' : ''; ?>">
                            <span style="background: var(--c-neon); color: #0f172a; font-weight: 900; padding: 1px 4px; border-radius: 2px;">
                              <?= ($currency ?: 'Rp') . ' 5.000'; ?>
                            </span>
                          </div>
                          <div class="mv-validity-el" style="<?= ($activeShowValidity === 'no') ? 'display: none;' : ''; ?>">
                            <span style="color: var(--c-neon); font-weight: 700;">1 <?= strtoupper($_day ?? 'HARI'); ?></span>
                          </div>
                        </div>
                      </div>
                    </div>
                    <div style="font-size: 11px; margin-top: 8px; opacity: 0.85; line-height: 1.35;">
                      <?= htmlspecialchars($allowedStyles['cyber-neon']['desc']); ?>
                    </div>
                  </div>
                  <div class="card-footer text-center" style="padding: 5px;">
                    <span class="btn btn-sm <?= ($activeStyle === 'cyber-neon') ? 'bg-primary' : 'bg-secondary'; ?> select-btn" id="btn_cyber-neon" style="margin: 0; font-size: 12px;">
                      <i class="fa <?= ($activeStyle === 'cyber-neon') ? 'fa-check' : 'fa-circle-o'; ?>"></i> <?= ($activeStyle === 'cyber-neon') ? ($_ts_selected ?? 'Terpilih') : ($_ts_select_theme ?? 'Pilih Gaya Ini'); ?>
                    </span>
                  </div>
                </div>
              </div>

              <!-- 6. Clean Minimal Stamp -->
              <div class="col-4 col-box-12 mr-b-10">
                <div class="card box-bordered ts-style-box <?= ($activeStyle === 'minimal-stamp') ? 'active-template' : ''; ?>" id="card_minimal-stamp" onclick="selectStyleCard('minimal-stamp')">
                  <div class="card-header">
                    <h3 style="font-size: 13px;"><i class="fa fa-file-text-o"></i> <?= htmlspecialchars($allowedStyles['minimal-stamp']['name']); ?></h3>
                  </div>
                  <div class="card-body text-center" style="padding: 10px;">
                    <div class="mv-box mv-stamp">
                      <div class="mv-stamp-hdr">
                        <span class="mv-hsname-el" style="<?= ($activeShowHsName === 'no') ? 'display: none;' : ''; ?>"><?= htmlspecialchars($hotspotname ?: 'HOTSPOT'); ?></span>
                        <span class="mv-num-el" style="<?= ($activeShowNum === 'no') ? 'display: none;' : ''; ?>">#1</span>
                      </div>
                      <div style="display: table; width: 100%; margin-top: 3px; background: #ffffff;">
                        <div style="display: table-cell; vertical-align: middle; padding-right: 3px;">
                          <div style="border: 1px solid var(--c-border); background: var(--c-box-bg); padding: 2px; text-align: center;">
                            <div style="font-size: 6px; font-weight: 800; color: var(--c-accent);">KODE VOUCHER</div>
                            <div style="font-family: monospace; font-size: 10.5px; font-weight: 900; color: #0f172a;">VCR-89240</div>
                          </div>
                          <div style="display: flex; justify-content: space-between; margin-top: 2px; font-size: 7.5px;">
                            <div class="mv-price-el" style="<?= ($activeShowPrice === 'no') ? 'display: none;' : ''; ?>">
                              <span style="font-weight: 900; color: var(--c-accent);"><?= ($currency ?: 'Rp') . ' 5.000'; ?></span>
                            </div>
                            <div class="mv-validity-el" style="<?= ($activeShowValidity === 'no') ? 'display: none;' : ''; ?>">
                              <span style="color: #475569; font-weight: 700;">1 <?= strtoupper($_day ?? 'HARI'); ?></span>
                            </div>
                          </div>
                        </div>
                        <div style="display: table-cell; vertical-align: middle; width: 38px; text-align: center;">
                          <div class="mv-qr-mock"><i class="fa fa-qrcode" style="font-size: 22px;"></i></div>
                        </div>
                      </div>
                    </div>
                    <div style="font-size: 11px; margin-top: 8px; opacity: 0.85; line-height: 1.35;">
                      <?= htmlspecialchars($allowedStyles['minimal-stamp']['desc']); ?>
                    </div>
                  </div>
                  <div class="card-footer text-center" style="padding: 5px;">
                    <span class="btn btn-sm <?= ($activeStyle === 'minimal-stamp') ? 'bg-primary' : 'bg-secondary'; ?> select-btn" id="btn_minimal-stamp" style="margin: 0; font-size: 12px;">
                      <i class="fa <?= ($activeStyle === 'minimal-stamp') ? 'fa-check' : 'fa-circle-o'; ?>"></i> <?= ($activeStyle === 'minimal-stamp') ? ($_ts_selected ?? 'Terpilih') : ($_ts_select_theme ?? 'Pilih Gaya Ini'); ?>
                    </span>
                  </div>
                </div>
              </div>

              <!-- 7. Retro Arcade 8-Bit Pixel -->
              <div class="col-4 col-box-12 mr-b-10">
                <div class="card box-bordered ts-style-box <?= ($activeStyle === 'retro-arcade') ? 'active-template' : ''; ?>" id="card_retro-arcade" onclick="selectStyleCard('retro-arcade')">
                  <div class="card-header">
                    <h3 style="font-size: 13px;"><i class="fa fa-trophy"></i> <?= htmlspecialchars($allowedStyles['retro-arcade']['name']); ?></h3>
                  </div>
                  <div class="card-body text-center" style="padding: 10px;">
                    <div class="mv-box mv-retro">
                      <div class="mv-retro-hdr">
                        <span class="mv-hsname-el" style="<?= ($activeShowHsName === 'no') ? 'display: none;' : ''; ?>">► <?= strtoupper(htmlspecialchars($hotspotname ?: 'HOTSPOT')); ?></span>
                        <span class="mv-num-el" style="<?= ($activeShowNum === 'no') ? 'display: none;' : ''; ?>">LVL.1</span>
                      </div>
                      <div style="padding: 4px; background: #ffffff;">
                        <div style="display: table; width: 100%;">
                          <div style="display: table-cell; vertical-align: middle; padding-right: 3px;">
                            <div style="border: 1.5px solid #0f172a; background: var(--c-box-bg); padding: 2px; text-align: center;">
                              <div style="font-size: 6px; font-weight: 900; font-family: monospace; color: var(--c-accent);">[ INSERT COIN ]</div>
                              <div style="font-family: monospace; font-size: 10.5px; font-weight: 900; color: #000; letter-spacing: 1px;">VCR-89240</div>
                            </div>
                          </div>
                          <div style="display: table-cell; vertical-align: middle; width: 38px; text-align: center;">
                            <div class="mv-qr-mock" style="border-radius: 0; border: 1.5px solid #0f172a;"><i class="fa fa-qrcode" style="font-size: 22px;"></i></div>
                          </div>
                        </div>
                        <div style="border: 1.5px solid #0f172a; margin-top: 3px; padding: 2px 4px; display: flex; justify-content: space-between; font-family: monospace; font-size: 7.5px; background: #f8fafc;">
                          <div class="mv-price-el" style="<?= ($activeShowPrice === 'no') ? 'display: none;' : ''; ?>">
                            <span style="font-weight: 900; color: var(--c-accent);"><?= ($currency ?: 'Rp') . ' 5.000'; ?></span>
                          </div>
                          <div class="mv-validity-el" style="<?= ($activeShowValidity === 'no') ? 'display: none;' : ''; ?>">
                            <span>1 <?= strtoupper($_day ?? 'HARI'); ?></span>
                          </div>
                        </div>
                      </div>
                    </div>
                    <div style="font-size: 11px; margin-top: 8px; opacity: 0.85; line-height: 1.35;">
                      <?= htmlspecialchars($allowedStyles['retro-arcade']['desc']); ?>
                    </div>
                  </div>
                  <div class="card-footer text-center" style="padding: 5px;">
                    <span class="btn btn-sm <?= ($activeStyle === 'retro-arcade') ? 'bg-primary' : 'bg-secondary'; ?> select-btn" id="btn_retro-arcade" style="margin: 0; font-size: 12px;">
                      <i class="fa <?= ($activeStyle === 'retro-arcade') ? 'fa-check' : 'fa-circle-o'; ?>"></i> <?= ($activeStyle === 'retro-arcade') ? ($_ts_selected ?? 'Terpilih') : ($_ts_select_theme ?? 'Pilih Gaya Ini'); ?>
                    </span>
                  </div>
                </div>
              </div>

              <!-- 8. Luxury Gold VIP Ribbon -->
              <div class="col-4 col-box-12 mr-b-10">
                <div class="card box-bordered ts-style-box <?= ($activeStyle === 'luxury-vip') ? 'active-template' : ''; ?>" id="card_luxury-vip" onclick="selectStyleCard('luxury-vip')">
                  <div class="card-header">
                    <h3 style="font-size: 13px;"><i class="fa fa-star"></i> <?= htmlspecialchars($allowedStyles['luxury-vip']['name']); ?></h3>
                  </div>
                  <div class="card-body text-center" style="padding: 10px;">
                    <div class="mv-box mv-luxury">
                      <div class="mv-luxury-hdr">
                        <span class="mv-hsname-el" style="<?= ($activeShowHsName === 'no') ? 'display: none;' : ''; ?>"><i class="fa fa-diamond" style="color: #fbbf24;"></i> <?= htmlspecialchars($hotspotname ?: 'HOTSPOT VIP'); ?></span>
                        <span class="mv-num-el" style="font-size: 7px; background: rgba(217, 119, 6, 0.3); border: 1px solid #d97706; padding: 0 3px; border-radius: 2px; color: #fbbf24; <?= ($activeShowNum === 'no') ? 'display: none;' : ''; ?>">VIP</span>
                      </div>
                      <div style="padding: 4px;">
                        <div style="display: table; width: 100%;">
                          <div style="display: table-cell; vertical-align: middle; padding-right: 4px;">
                            <div style="background: #1c1917; border: 1px solid #d97706; border-radius: 4px; padding: 3px; text-align: center;">
                              <div style="font-size: 6px; font-weight: 800; color: #fbbf24; letter-spacing: 0.5px;">★ VIP PASSCODE ★</div>
                              <div style="font-family: monospace; font-size: 10.5px; font-weight: 900; color: #fef08a; letter-spacing: 1px;">VCR-89240</div>
                            </div>
                          </div>
                          <div style="display: table-cell; vertical-align: middle; width: 38px; text-align: center; background: #ffffff; border-radius: 3px; padding: 2px;">
                            <div class="mv-qr-mock" style="border: 0;"><i class="fa fa-qrcode" style="font-size: 22px; color: #000;"></i></div>
                          </div>
                        </div>
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 4px; font-size: 7.5px;">
                          <div class="mv-price-el" style="<?= ($activeShowPrice === 'no') ? 'display: none;' : ''; ?>">
                            <span style="background: linear-gradient(135deg, #d97706, #b45309); color: #ffffff; font-weight: 900; padding: 1px 4px; border-radius: 2px;">
                              <?= ($currency ?: 'Rp') . ' 5.000'; ?>
                            </span>
                          </div>
                          <div class="mv-validity-el" style="<?= ($activeShowValidity === 'no') ? 'display: none;' : ''; ?>">
                            <span style="color: #fbbf24; font-weight: 700;">1 <?= strtoupper($_day ?? 'HARI'); ?></span>
                          </div>
                        </div>
                      </div>
                    </div>
                    <div style="font-size: 11px; margin-top: 8px; opacity: 0.85; line-height: 1.35;">
                      <?= htmlspecialchars($allowedStyles['luxury-vip']['desc']); ?>
                    </div>
                  </div>
                  <div class="card-footer text-center" style="padding: 5px;">
                    <span class="btn btn-sm <?= ($activeStyle === 'luxury-vip') ? 'bg-primary' : 'bg-secondary'; ?> select-btn" id="btn_luxury-vip" style="margin: 0; font-size: 12px;">
                      <i class="fa <?= ($activeStyle === 'luxury-vip') ? 'fa-check' : 'fa-circle-o'; ?>"></i> <?= ($activeStyle === 'luxury-vip') ? ($_ts_selected ?? 'Terpilih') : ($_ts_select_theme ?? 'Pilih Gaya Ini'); ?>
                    </span>
                  </div>
                </div>
              </div>

              <!-- 9. Neon Synthwave 80s -->
              <div class="col-4 col-box-12 mr-b-10">
                <div class="card box-bordered ts-style-box <?= ($activeStyle === 'synthwave-sunset') ? 'active-template' : ''; ?>" id="card_synthwave-sunset" onclick="selectStyleCard('synthwave-sunset')">
                  <div class="card-header">
                    <h3 style="font-size: 13px;"><i class="fa fa-bolt"></i> <?= htmlspecialchars($allowedStyles['synthwave-sunset']['name']); ?></h3>
                  </div>
                  <div class="card-body text-center" style="padding: 10px;">
                    <div class="mv-box mv-synthwave">
                      <div class="mv-synthwave-hdr">
                        <span class="mv-hsname-el" style="<?= ($activeShowHsName === 'no') ? 'display: none;' : ''; ?>"><?= htmlspecialchars($hotspotname ?: 'SYNTHWAVE'); ?></span>
                        <span class="mv-num-el" style="background: rgba(0,0,0,0.3); padding: 0 4px; border-radius: 3px; font-size: 7px; <?= ($activeShowNum === 'no') ? 'display: none;' : ''; ?>">80s</span>
                      </div>
                      <div style="padding: 4px;">
                        <div style="display: table; width: 100%;">
                          <div style="display: table-cell; vertical-align: middle; padding-right: 4px;">
                            <div style="background: #241442; border: 1px solid #06b6d4; border-radius: 4px; padding: 3px; text-align: center;">
                              <div style="font-size: 6px; font-weight: 800; color: #ec4899; letter-spacing: 0.5px;">WAVE CODE</div>
                              <div style="font-family: monospace; font-size: 10.5px; font-weight: 900; color: #fff; text-shadow: 0 0 4px #06b6d4;">VCR-89240</div>
                            </div>
                          </div>
                          <div style="display: table-cell; vertical-align: middle; width: 38px; text-align: center; background: #fff; border-radius: 3px; padding: 2px;">
                            <div class="mv-qr-mock" style="border: 0;"><i class="fa fa-qrcode" style="font-size: 22px; color: #000;"></i></div>
                          </div>
                        </div>
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 4px; font-size: 7.5px;">
                          <div class="mv-price-el" style="<?= ($activeShowPrice === 'no') ? 'display: none;' : ''; ?>">
                            <span style="background: #ec4899; color: #fff; font-weight: 900; padding: 1px 4px; border-radius: 2px;">
                              <?= ($currency ?: 'Rp') . ' 5.000'; ?>
                            </span>
                          </div>
                          <div class="mv-validity-el" style="<?= ($activeShowValidity === 'no') ? 'display: none;' : ''; ?>">
                            <span style="color: #06b6d4; font-weight: 700;">1 <?= strtoupper($_day ?? 'HARI'); ?></span>
                          </div>
                        </div>
                      </div>
                    </div>
                    <div style="font-size: 11px; margin-top: 8px; opacity: 0.85; line-height: 1.35;">
                      <?= htmlspecialchars($allowedStyles['synthwave-sunset']['desc']); ?>
                    </div>
                  </div>
                  <div class="card-footer text-center" style="padding: 5px;">
                    <span class="btn btn-sm <?= ($activeStyle === 'synthwave-sunset') ? 'bg-primary' : 'bg-secondary'; ?> select-btn" id="btn_synthwave-sunset" style="margin: 0; font-size: 12px;">
                      <i class="fa <?= ($activeStyle === 'synthwave-sunset') ? 'fa-check' : 'fa-circle-o'; ?>"></i> <?= ($activeStyle === 'synthwave-sunset') ? ($_ts_selected ?? 'Terpilih') : ($_ts_select_theme ?? 'Pilih Gaya Ini'); ?>
                    </span>
                  </div>
                </div>
              </div>

              <!-- 10. Coffee Shop Kraft Paper -->
              <div class="col-4 col-box-12 mr-b-10">
                <div class="card box-bordered ts-style-box <?= ($activeStyle === 'vintage-kraft') ? 'active-template' : ''; ?>" id="card_vintage-kraft" onclick="selectStyleCard('vintage-kraft')">
                  <div class="card-header">
                    <h3 style="font-size: 13px;"><i class="fa fa-coffee"></i> <?= htmlspecialchars($allowedStyles['vintage-kraft']['name']); ?></h3>
                  </div>
                  <div class="card-body text-center" style="padding: 10px;">
                    <div class="mv-box mv-kraft">
                      <div class="mv-kraft-hdr">
                        <span class="mv-hsname-el" style="<?= ($activeShowHsName === 'no') ? 'display: none;' : ''; ?>"><i class="fa fa-coffee"></i> <?= htmlspecialchars($hotspotname ?: 'WARKOP WIFI'); ?></span>
                        <span class="mv-num-el" style="background: rgba(255,255,255,0.2); padding: 0 4px; border-radius: 2px; font-size: 7px; <?= ($activeShowNum === 'no') ? 'display: none;' : ''; ?>">#1</span>
                      </div>
                      <div style="padding: 4px;">
                        <div style="display: table; width: 100%;">
                          <div style="display: table-cell; vertical-align: middle; padding-right: 4px;">
                            <div style="background: #ffffff; border: 1px dotted #78350f; border-radius: 4px; padding: 3px; text-align: center;">
                              <div style="font-size: 6px; font-weight: 800; color: #78350f;">KODE INTERNET</div>
                              <div style="font-family: monospace; font-size: 10.5px; font-weight: 900; color: #451a03;">VCR-89240</div>
                            </div>
                          </div>
                          <div style="display: table-cell; vertical-align: middle; width: 38px; text-align: center; background: #ffffff; border: 1px solid #d7c4b7; border-radius: 3px; padding: 2px;">
                            <div class="mv-qr-mock" style="border: 0;"><i class="fa fa-qrcode" style="font-size: 22px; color: #451a03;"></i></div>
                          </div>
                        </div>
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 4px; font-size: 7.5px;">
                          <div class="mv-price-el" style="<?= ($activeShowPrice === 'no') ? 'display: none;' : ''; ?>">
                            <span style="background: #78350f; color: #fef3c7; font-weight: 900; padding: 1px 4px; border-radius: 2px;">
                              <?= ($currency ?: 'Rp') . ' 5.000'; ?>
                            </span>
                          </div>
                          <div class="mv-validity-el" style="<?= ($activeShowValidity === 'no') ? 'display: none;' : ''; ?>">
                            <span style="color: #78350f; font-weight: 700;">1 <?= strtoupper($_day ?? 'HARI'); ?></span>
                          </div>
                        </div>
                      </div>
                    </div>
                    <div style="font-size: 11px; margin-top: 8px; opacity: 0.85; line-height: 1.35;">
                      <?= htmlspecialchars($allowedStyles['vintage-kraft']['desc']); ?>
                    </div>
                  </div>
                  <div class="card-footer text-center" style="padding: 5px;">
                    <span class="btn btn-sm <?= ($activeStyle === 'vintage-kraft') ? 'bg-primary' : 'bg-secondary'; ?> select-btn" id="btn_vintage-kraft" style="margin: 0; font-size: 12px;">
                      <i class="fa <?= ($activeStyle === 'vintage-kraft') ? 'fa-check' : 'fa-circle-o'; ?>"></i> <?= ($activeStyle === 'vintage-kraft') ? ($_ts_selected ?? 'Terpilih') : ($_ts_select_theme ?? 'Pilih Gaya Ini'); ?>
                    </span>
                  </div>
                </div>
              </div>

              <!-- 11. Modern Frosted Glass -->
              <div class="col-4 col-box-12 mr-b-10">
                <div class="card box-bordered ts-style-box <?= ($activeStyle === 'glass-gradient') ? 'active-template' : ''; ?>" id="card_glass-gradient" onclick="selectStyleCard('glass-gradient')">
                  <div class="card-header">
                    <h3 style="font-size: 13px;"><i class="fa fa-clone"></i> <?= htmlspecialchars($allowedStyles['glass-gradient']['name']); ?></h3>
                  </div>
                  <div class="card-body text-center" style="padding: 10px;">
                    <div class="mv-box mv-glass">
                      <div class="mv-glass-bar"></div>
                      <div class="mv-glass-hdr">
                        <span class="mv-hsname-el" style="<?= ($activeShowHsName === 'no') ? 'display: none;' : ''; ?>"><?= htmlspecialchars($hotspotname ?: 'HOTSPOT'); ?></span>
                        <span class="mv-num-el" style="background: #f1f5f9; padding: 0 4px; border-radius: 99px; font-size: 7px; <?= ($activeShowNum === 'no') ? 'display: none;' : ''; ?>">#1</span>
                      </div>
                      <div style="padding: 5px;">
                        <div style="display: table; width: 100%;">
                          <div style="display: table-cell; vertical-align: middle; padding-right: 4px;">
                            <div style="background: linear-gradient(135deg, var(--c-box-bg) 0%, #ffffff 100%); border: 1px solid var(--c-border); border-radius: 6px; padding: 3px; text-align: center;">
                              <div style="font-size: 6px; font-weight: 800; color: var(--c-accent);">ACCESS CODE</div>
                              <div style="font-family: monospace; font-size: 11px; font-weight: 900; color: #0f172a;">VCR-89240</div>
                            </div>
                          </div>
                          <div style="display: table-cell; vertical-align: middle; width: 38px; text-align: center;">
                            <div class="mv-qr-mock" style="border-radius: 6px;"><i class="fa fa-qrcode" style="font-size: 22px;"></i></div>
                          </div>
                        </div>
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 4px;">
                          <div class="mv-price-el" style="<?= ($activeShowPrice === 'no') ? 'display: none;' : ''; ?>">
                            <span style="background: var(--c-badge-bg); color: var(--c-badge-text); padding: 1px 5px; border-radius: 99px; font-weight: 800;">
                              <?= ($currency ?: 'Rp') . ' 5.000'; ?>
                            </span>
                          </div>
                          <div class="mv-validity-el" style="<?= ($activeShowValidity === 'no') ? 'display: none;' : ''; ?>">
                            <span style="color: #64748b; font-weight: 700;">1 <?= strtoupper($_day ?? 'HARI'); ?></span>
                          </div>
                        </div>
                      </div>
                    </div>
                    <div style="font-size: 11px; margin-top: 8px; opacity: 0.85; line-height: 1.35;">
                      <?= htmlspecialchars($allowedStyles['glass-gradient']['desc']); ?>
                    </div>
                  </div>
                  <div class="card-footer text-center" style="padding: 5px;">
                    <span class="btn btn-sm <?= ($activeStyle === 'glass-gradient') ? 'bg-primary' : 'bg-secondary'; ?> select-btn" id="btn_glass-gradient" style="margin: 0; font-size: 12px;">
                      <i class="fa <?= ($activeStyle === 'glass-gradient') ? 'fa-check' : 'fa-circle-o'; ?>"></i> <?= ($activeStyle === 'glass-gradient') ? ($_ts_selected ?? 'Terpilih') : ($_ts_select_theme ?? 'Pilih Gaya Ini'); ?>
                    </span>
                  </div>
                </div>
              </div>

              <!-- 12. Compact Split Coupon -->
              <div class="col-4 col-box-12 mr-b-10">
                <div class="card box-bordered ts-style-box <?= ($activeStyle === 'split-coupon') ? 'active-template' : ''; ?>" id="card_split-coupon" onclick="selectStyleCard('split-coupon')">
                  <div class="card-header">
                    <h3 style="font-size: 13px;"><i class="fa fa-scissors"></i> <?= htmlspecialchars($allowedStyles['split-coupon']['name']); ?></h3>
                  </div>
                  <div class="card-body text-center" style="padding: 10px;">
                    <div class="mv-box mv-split">
                      <div class="mv-split-left">
                        <div style="font-size: 6px; font-weight: 800; opacity: 0.9;">PASS</div>
                        <div class="mv-price-el" style="<?= ($activeShowPrice === 'no') ? 'display: none;' : ''; ?>; font-size: 8px; font-weight: 900; margin-top: 2px;">5K</div>
                        <div class="mv-validity-el" style="<?= ($activeShowValidity === 'no') ? 'display: none;' : ''; ?>; font-size: 6px; margin-top: 2px;">1D</div>
                      </div>
                      <div class="mv-split-right">
                        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px dotted #cbd5e1; padding-bottom: 2px;">
                          <span class="mv-hsname-el" style="font-weight: 800; font-size: 8px; <?= ($activeShowHsName === 'no') ? 'display: none;' : ''; ?>"><?= htmlspecialchars($hotspotname ?: 'HOTSPOT'); ?></span>
                          <span class="mv-num-el" style="font-size: 6.5px; color: #64748b; <?= ($activeShowNum === 'no') ? 'display: none;' : ''; ?>">#1</span>
                        </div>
                        <div style="display: table; width: 100%; margin-top: 3px;">
                          <div style="display: table-cell; vertical-align: middle; padding-right: 3px;">
                            <div style="background: var(--c-box-bg); border: 1px solid var(--c-border); border-radius: 3px; padding: 2px; text-align: center;">
                              <div style="font-family: monospace; font-size: 10px; font-weight: 900; color: #0f172a;">VCR-89240</div>
                            </div>
                          </div>
                          <div style="display: table-cell; vertical-align: middle; width: 30px; text-align: center;">
                            <div class="mv-qr-mock" style="width: 28px; height: 28px;"><i class="fa fa-qrcode" style="font-size: 18px;"></i></div>
                          </div>
                        </div>
                      </div>
                    </div>
                    <div style="font-size: 11px; margin-top: 8px; opacity: 0.85; line-height: 1.35;">
                      <?= htmlspecialchars($allowedStyles['split-coupon']['desc']); ?>
                    </div>
                  </div>
                  <div class="card-footer text-center" style="padding: 5px;">
                    <span class="btn btn-sm <?= ($activeStyle === 'split-coupon') ? 'bg-primary' : 'bg-secondary'; ?> select-btn" id="btn_split-coupon" style="margin: 0; font-size: 12px;">
                      <i class="fa <?= ($activeStyle === 'split-coupon') ? 'fa-check' : 'fa-circle-o'; ?>"></i> <?= ($activeStyle === 'split-coupon') ? ($_ts_selected ?? 'Terpilih') : ($_ts_select_theme ?? 'Pilih Gaya Ini'); ?>
                    </span>
                  </div>
                </div>
              </div>

            </div>
          </div>
        </form>

      </div>
    </div>
  </div>
</div>

<script>
const voucherColors = <?= json_encode($allowedColors); ?>;

function syncFromUI() {
  const styleVal = document.getElementById('voucher_style').value;
  const colorVal = document.getElementById('voucher_color').value;
  
  const showPrice = document.getElementById('cb_show_price').checked;
  const showValidity = document.getElementById('cb_show_validity').checked;
  const showDataLimit = document.getElementById('cb_show_datalimit').checked;
  const showLogo = document.getElementById('cb_show_logo').checked;
  const showHsName = document.getElementById('cb_show_hotspotname').checked;
  const showDns = document.getElementById('cb_show_dns').checked;
  const showNum = document.getElementById('cb_show_num').checked;

  // 1. Update style card selection
  document.querySelectorAll('.ts-style-box').forEach(function(card) {
    const cardId = card.id.replace('card_', '');
    const btn = document.getElementById('btn_' + cardId);
    if (cardId === styleVal) {
      card.classList.add('active-template');
      if (btn) {
        btn.className = 'btn btn-sm bg-primary select-btn';
        btn.innerHTML = '<i class="fa fa-check"></i> ' + <?= json_encode($_ts_selected ?? 'Terpilih'); ?>;
      }
    } else {
      card.classList.remove('active-template');
      if (btn) {
        btn.className = 'btn btn-sm bg-secondary select-btn';
        btn.innerHTML = '<i class="fa fa-circle-o"></i> ' + <?= json_encode($_ts_select_theme ?? 'Pilih Gaya Ini'); ?>;
      }
    }
  });

  // 2. Update colors on previews
  const c = voucherColors[colorVal];
  if (c) {
    const root = document.documentElement;
    root.style.setProperty('--c-border', c.border);
    root.style.setProperty('--c-hdr-start', c.hdr_start);
    root.style.setProperty('--c-hdr-end', c.hdr_end);
    root.style.setProperty('--c-box-bg', c.box_bg);
    root.style.setProperty('--c-accent', c.accent);
    root.style.setProperty('--c-badge-bg', c.badge_bg);
    root.style.setProperty('--c-badge-text', c.badge_text);
    root.style.setProperty('--c-neon', c.neon);
    root.style.setProperty('--c-dark-card', c.dark_card);
    root.style.setProperty('--c-dark-box', c.dark_box);
  }

  // 3. Update preview elements visibility
  document.querySelectorAll('.mv-price-el').forEach(function(el) { el.style.display = showPrice ? '' : 'none'; });
  document.querySelectorAll('.mv-validity-el').forEach(function(el) { el.style.display = showValidity ? '' : 'none'; });
  document.querySelectorAll('.mv-hsname-el').forEach(function(el) { el.style.display = showHsName ? '' : 'none'; });
  document.querySelectorAll('.mv-dns-el').forEach(function(el) { el.style.display = showDns ? '' : 'none'; });
  document.querySelectorAll('.mv-num-el').forEach(function(el) { el.style.display = showNum ? '' : 'none'; });
}

function selectStyleCard(styleKey) {
  const sel = document.getElementById('voucher_style');
  if (sel) {
    sel.value = styleKey;
    syncFromUI();
  }
}
</script>
