<?php
if (!isset($vConfigLoaded)) {
  $vConfigLoaded = true;
  $vCfgPath = file_exists('../include/voucher_config.php') ? '../include/voucher_config.php' : (file_exists('./include/voucher_config.php') ? './include/voucher_config.php' : '');
  if ($vCfgPath) { @include($vCfgPath); }

  $vColorPalettes = [
    'blue' => [
      'border' => '#0284c7', 'hdr_start' => '#0284c7', 'hdr_end' => '#0369a1', 'box_bg' => '#f0f9ff', 'accent' => '#0284c7', 'badge_bg' => '#e0f2fe', 'badge_text' => '#0369a1', 'neon' => '#38bdf8', 'card_bg' => '#ffffff', 'dark_card' => '#0b192c', 'dark_box' => '#132a4a',
    ],
    'green' => [
      'border' => '#059669', 'hdr_start' => '#059669', 'hdr_end' => '#047857', 'box_bg' => '#ecfdf5', 'accent' => '#059669', 'badge_bg' => '#d1fae5', 'badge_text' => '#065f46', 'neon' => '#34d399', 'card_bg' => '#ffffff', 'dark_card' => '#06281e', 'dark_box' => '#0d4233',
    ],
    'purple' => [
      'border' => '#6366f1', 'hdr_start' => '#6366f1', 'hdr_end' => '#4338ca', 'box_bg' => '#eef2ff', 'accent' => '#6366f1', 'badge_bg' => '#e0e7ff', 'badge_text' => '#3730a3', 'neon' => '#818cf8', 'card_bg' => '#ffffff', 'dark_card' => '#15132d', 'dark_box' => '#272352',
    ],
    'orange' => [
      'border' => '#ea580c', 'hdr_start' => '#ea580c', 'hdr_end' => '#c2410c', 'box_bg' => '#fff7ed', 'accent' => '#ea580c', 'badge_bg' => '#ffedd5', 'badge_text' => '#9a3412', 'neon' => '#fb923c', 'card_bg' => '#ffffff', 'dark_card' => '#241006', 'dark_box' => '#3d1b0c',
    ],
    'crimson' => [
      'border' => '#dc2626', 'hdr_start' => '#dc2626', 'hdr_end' => '#991b1b', 'box_bg' => '#fef2f2', 'accent' => '#dc2626', 'badge_bg' => '#fee2e2', 'badge_text' => '#991b1b', 'neon' => '#f87171', 'card_bg' => '#ffffff', 'dark_card' => '#2b0d0d', 'dark_box' => '#451212',
    ],
    'teal' => [
      'border' => '#0d9488', 'hdr_start' => '#0d9488', 'hdr_end' => '#115e59', 'box_bg' => '#f0fdfa', 'accent' => '#0d9488', 'badge_bg' => '#ccfbf1', 'badge_text' => '#115e59', 'neon' => '#2dd4bf', 'card_bg' => '#ffffff', 'dark_card' => '#04221f', 'dark_box' => '#0a3d38',
    ],
    'gold' => [
      'border' => '#d97706', 'hdr_start' => '#d97706', 'hdr_end' => '#b45309', 'box_bg' => '#fffbeb', 'accent' => '#d97706', 'badge_bg' => '#fef3c7', 'badge_text' => '#92400e', 'neon' => '#fbbf24', 'card_bg' => '#ffffff', 'dark_card' => '#241a05', 'dark_box' => '#3d2c09',
    ],
    'pink' => [
      'border' => '#db2777', 'hdr_start' => '#db2777', 'hdr_end' => '#9d174d', 'box_bg' => '#fdf2f8', 'accent' => '#db2777', 'badge_bg' => '#fce7f3', 'badge_text' => '#9d174d', 'neon' => '#f472b6', 'card_bg' => '#ffffff', 'dark_card' => '#2b091e', 'dark_box' => '#451031',
    ],
    'coffee' => [
      'border' => '#78350f', 'hdr_start' => '#78350f', 'hdr_end' => '#451a03', 'box_bg' => '#fbf7ee', 'accent' => '#78350f', 'badge_bg' => '#fef3c7', 'badge_text' => '#78350f', 'neon' => '#d97706', 'card_bg' => '#ffffff', 'dark_card' => '#1c0f05', 'dark_box' => '#331b09',
    ],
    'dark' => [
      'border' => '#1e293b', 'hdr_start' => '#1e293b', 'hdr_end' => '#0f172a', 'box_bg' => '#f8fafc', 'accent' => '#0f172a', 'badge_bg' => '#f1f5f9', 'badge_text' => '#0f172a', 'neon' => '#94a3b8', 'card_bg' => '#ffffff', 'dark_card' => '#0f172a', 'dark_box' => '#1e293b',
    ],
    'monochrome' => [
      'border' => '#18181b', 'hdr_start' => '#27272a', 'hdr_end' => '#18181b', 'box_bg' => '#ffffff', 'accent' => '#18181b', 'badge_bg' => '#f4f4f5', 'badge_text' => '#18181b', 'neon' => '#ffffff', 'card_bg' => '#ffffff', 'dark_card' => '#18181b', 'dark_box' => '#27272a',
    ],
  ];

  $cfgItem = $voucher_config[$session] ?? null;
  if (is_array($cfgItem)) {
    $vStyle = $cfgItem['style'] ?? 'modern-card';
    $vColorKey = $cfgItem['color'] ?? 'blue';
    $vShowPrice = ($cfgItem['show_price'] ?? 'yes') !== 'no';
    $vShowValidity = ($cfgItem['show_validity'] ?? 'yes') !== 'no';
    $vShowDataLimit = ($cfgItem['show_datalimit'] ?? 'yes') !== 'no';
    $vShowLogo = ($cfgItem['show_logo'] ?? 'yes') !== 'no';
    $vShowHsName = ($cfgItem['show_hotspotname'] ?? 'yes') !== 'no';
    $vShowDns = ($cfgItem['show_dns'] ?? 'yes') !== 'no';
    $vShowNum = ($cfgItem['show_num'] ?? 'yes') !== 'no';
  } else {
    $oldTheme = $voucher_theme[$session] ?? 'modern-blue';
    if ($oldTheme === 'emerald-green') { $vStyle = 'modern-card'; $vColorKey = 'green'; }
    elseif ($oldTheme === 'indigo-purple') { $vStyle = 'modern-card'; $vColorKey = 'purple'; }
    elseif ($oldTheme === 'sunset-orange') { $vStyle = 'modern-card'; $vColorKey = 'orange'; }
    elseif ($oldTheme === 'midnight-dark') { $vStyle = 'modern-card'; $vColorKey = 'dark'; }
    elseif ($oldTheme === 'classic-minimal') { $vStyle = 'minimal-stamp'; $vColorKey = 'monochrome'; }
    else { $vStyle = 'modern-card'; $vColorKey = 'blue'; }
    $vShowPrice = true;
    $vShowValidity = true;
    $vShowDataLimit = true;
    $vShowLogo = true;
    $vShowHsName = true;
    $vShowDns = true;
    $vShowNum = true;
  }

  $c = $vColorPalettes[$vColorKey] ?? $vColorPalettes['blue'];

  $vLang = $_SESSION['lang'] ?? ($langid ?? 'id');
  $vLabels = [
    'id' => ['code' => 'KODE VOUCHER', 'pass' => 'PASS CODE', 'user' => 'Username', 'password' => 'Password', 'login' => 'Login', 'data' => 'Kuota', 'scan' => 'SCAN QR'],
    'en' => ['code' => 'VOUCHER CODE', 'pass' => 'PASS CODE', 'user' => 'Username', 'password' => 'Password', 'login' => 'Login', 'data' => 'Data Limit', 'scan' => 'SCAN QR'],
    'fr' => ['code' => 'CODE VOUCHER', 'pass' => "CODE D'ACCÈS", 'user' => 'Utilisateur', 'password' => 'Mot de passe', 'login' => 'Connexion', 'data' => 'Données', 'scan' => 'SCANNER QR'],
    'es' => ['code' => 'CÓDIGO VOUCHER', 'pass' => 'CÓDIGO DE ACCESO', 'user' => 'Usuario', 'password' => 'Contraseña', 'login' => 'Iniciar sesión', 'data' => 'Datos', 'scan' => 'ESCANEAR QR'],
    'tl' => ['code' => 'VOUCHER CODE', 'pass' => 'PASS CODE', 'user' => 'Username', 'password' => 'Password', 'login' => 'Mag-login', 'data' => 'Data', 'scan' => 'I-SCAN ANG QR'],
  ];
  $curLbl = $vLabels[$vLang] ?? $vLabels['en'];
  $lblCode = $curLbl['code'];
  $lblPass = $curLbl['pass'];
  $lblUser = $curLbl['user'];
  $lblPassword = $curLbl['password'];
  $lblLogin = $curLbl['login'];
  $lblData = $curLbl['data'];
  $lblScan = $curLbl['scan'];
}
?>
<style>
.v-card-base {
  width: 215px;
  display: inline-block;
  vertical-align: top;
  margin: 3px;
  box-sizing: border-box;
  page-break-inside: avoid;
  font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
}
.v-qr-canvas canvas, .v-qr-canvas .qrcode {
  width: 62px !important;
  height: 62px !important;
  display: block;
  margin: 0 auto;
}
</style>

<?php if ($vStyle === 'ticket-notch'): ?>
  <!-- STYLE 2: NOTCHED TICKET / BOARDING PASS -->
  <style>
  .v-ticket {
    background: #ffffff;
    border: 1.5px dashed <?= $c['border']; ?>;
    border-radius: 8px;
    position: relative;
    overflow: hidden;
    box-shadow: 0 2px 4px rgba(0,0,0,0.06);
  }
  .v-ticket-header {
    background: linear-gradient(135deg, <?= $c['hdr_start']; ?>, <?= $c['hdr_end']; ?>);
    color: #ffffff;
    padding: 4px 8px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    min-height: 18px;
  }
  .v-ticket-brand {
    font-size: 11px;
    font-weight: 800;
    display: flex;
    align-items: center;
    gap: 4px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 150px;
  }
  .v-ticket-brand img { height: 16px; max-width: 35px; object-fit: contain; }
  .v-ticket-inner {
    display: table;
    width: 100%;
    padding: 6px 6px 4px;
  }
  .v-ticket-left {
    display: table-cell;
    vertical-align: middle;
    width: 135px;
    padding-right: 4px;
  }
  .v-ticket-right {
    display: table-cell;
    vertical-align: middle;
    width: 65px;
    border-left: 1.5px dashed <?= $c['border']; ?>;
    padding-left: 4px;
    text-align: center;
  }
  .v-ticket-box {
    background: <?= $c['box_bg']; ?>;
    border: 1px solid <?= $c['border']; ?>;
    border-radius: 4px;
    padding: 3px 4px;
    text-align: center;
  }
  .v-ticket-code {
    font-family: "Courier New", Courier, monospace;
    font-size: 14px;
    font-weight: 900;
    color: #0f172a;
    letter-spacing: 1px;
    line-height: 1.2;
    word-break: break-all;
  }
  .v-ticket-meta {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 4px;
    font-size: 9px;
  }
  .v-ticket-price {
    font-weight: 900;
    color: <?= $c['accent']; ?>;
  }
  .v-ticket-footer {
    background: #f8fafc;
    border-top: 1px dotted #e2e8f0;
    padding: 2px 6px;
    font-size: 8px;
    color: #64748b;
    display: flex;
    justify-content: space-between;
  }
  </style>
  <div class="v-card-base v-ticket">
    <div class="v-ticket-header">
      <div class="v-ticket-brand">
        <?php if ($vShowLogo && !empty($logo)): ?><img src="<?= $logo; ?>"><?php endif; ?>
        <?php if ($vShowHsName): ?><span><?= htmlspecialchars($hotspotname); ?></span><?php endif; ?>
      </div>
      <?php if ($vShowNum): ?><span style="font-size: 8px; font-weight: 800; background: rgba(255,255,255,0.25); padding: 1px 4px; border-radius: 3px;">#<?= $num; ?></span><?php endif; ?>
    </div>
    <div class="v-ticket-inner">
      <div class="v-ticket-left">
        <?php if ($usermode == "vc"): ?>
          <div class="v-ticket-box">
            <div style="font-size: 7.5px; font-weight: 800; color: <?= $c['accent']; ?>; text-transform: uppercase;"><?= $lblPass; ?></div>
            <div class="v-ticket-code"><?= $username; ?></div>
          </div>
        <?php else: ?>
          <div class="v-ticket-box" style="padding: 2px 3px;">
            <div style="font-family: monospace; font-size: 11px; font-weight: 800;"><?= $lblUser; ?>: <?= $username; ?></div>
            <div style="font-family: monospace; font-size: 11px; font-weight: 800;"><?= $lblPassword; ?>: <?= $password; ?></div>
          </div>
        <?php endif; ?>
        <?php if ($vShowPrice || $vShowValidity): ?>
        <div class="v-ticket-meta">
          <?php if ($vShowPrice): ?><span class="v-ticket-price"><?= !empty($price) ? $price : $profile; ?></span><?php else: ?><span></span><?php endif; ?>
          <?php if ($vShowValidity): ?><span style="color: #475569; font-weight: 700;"><?= $validity ?: $timelimit; ?></span><?php endif; ?>
        </div>
        <?php endif; ?>
      </div>
      <div class="v-ticket-right v-qr-canvas">
        <?= $qrcode; ?>
        <div style="font-size: 7px; font-weight: 800; color: <?= $c['accent']; ?>; margin-top: 1px;"><?= $lblScan; ?></div>
      </div>
    </div>
    <?php if ($vShowDns || ($vShowDataLimit && !empty($datalimit))): ?>
    <div class="v-ticket-footer">
      <span><?= $vShowDns ? $dnsname : ''; ?></span>
      <span><?= ($vShowDataLimit && !empty($datalimit)) ? $lblData . ': ' . $datalimit : ($vShowDns ? 'PASS TICKET' : ''); ?></span>
    </div>
    <?php endif; ?>
  </div>

<?php elseif ($vStyle === 'curved-wave'): ?>
  <!-- STYLE 3: CURVED WAVE & FLOATING BOX -->
  <style>
  .v-wave {
    background: #ffffff;
    border: 1.5px solid <?= $c['border']; ?>;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 4px 6px rgba(0,0,0,0.05);
  }
  .v-wave-hdr {
    background: linear-gradient(135deg, <?= $c['hdr_start']; ?>, <?= $c['hdr_end']; ?>);
    color: #ffffff;
    padding: 6px 8px 10px;
    border-radius: 0 0 16px 16px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    min-height: 20px;
  }
  .v-wave-brand {
    font-size: 11px;
    font-weight: 800;
    display: flex;
    align-items: center;
    gap: 4px;
    max-width: 150px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }
  .v-wave-brand img { height: 16px; max-width: 35px; object-fit: contain; }
  .v-wave-body {
    padding: 6px 8px 4px;
  }
  .v-wave-codebox {
    background: <?= $c['box_bg']; ?>;
    border: 1.5px dashed <?= $c['border']; ?>;
    border-radius: 8px;
    padding: 5px;
    text-align: center;
    box-shadow: 0 2px 4px rgba(0,0,0,0.04);
  }
  .v-wave-code {
    font-family: "Courier New", Courier, monospace;
    font-size: 15px;
    font-weight: 900;
    color: #0f172a;
    letter-spacing: 1.5px;
    line-height: 1.2;
    word-break: break-all;
  }
  .v-wave-pills {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 5px;
  }
  .v-wave-price {
    background: <?= $c['badge_bg']; ?>;
    color: <?= $c['badge_text']; ?>;
    font-weight: 900;
    font-size: 10px;
    padding: 2px 6px;
    border-radius: 99px;
  }
  </style>
  <div class="v-card-base v-wave">
    <div class="v-wave-hdr">
      <div class="v-wave-brand">
        <?php if ($vShowLogo && !empty($logo)): ?><img src="<?= $logo; ?>"><?php endif; ?>
        <?php if ($vShowHsName): ?><span><?= htmlspecialchars($hotspotname); ?></span><?php endif; ?>
      </div>
      <?php if ($vShowNum): ?><span style="font-size: 8px; font-weight: 800; background: rgba(255,255,255,0.25); padding: 1px 5px; border-radius: 99px;">#<?= $num; ?></span><?php endif; ?>
    </div>
    <div class="v-wave-body">
      <div style="display: table; width: 100%;">
        <div style="display: table-cell; vertical-align: middle; padding-right: 6px;">
          <?php if ($usermode == "vc"): ?>
            <div class="v-wave-codebox">
              <div style="font-size: 7.5px; font-weight: 800; color: <?= $c['accent']; ?>; text-transform: uppercase;"><?= $lblCode; ?></div>
              <div class="v-wave-code"><?= $username; ?></div>
            </div>
          <?php else: ?>
            <div class="v-wave-codebox" style="padding: 2px 4px;">
              <div style="font-family: monospace; font-size: 11.5px; font-weight: 800;"><?= $lblUser; ?>: <?= $username; ?></div>
              <div style="font-family: monospace; font-size: 11.5px; font-weight: 800;"><?= $lblPassword; ?>: <?= $password; ?></div>
            </div>
          <?php endif; ?>
        </div>
        <div style="display: table-cell; vertical-align: middle; width: 62px; text-align: center;" class="v-qr-canvas">
          <?= $qrcode; ?>
        </div>
      </div>
      <?php if ($vShowPrice || $vShowValidity): ?>
      <div class="v-wave-pills">
        <?php if ($vShowPrice): ?><span class="v-wave-price"><?= !empty($price) ? $price : $profile; ?></span><?php else: ?><span></span><?php endif; ?>
        <?php if ($vShowValidity): ?><span style="font-size: 8.5px; font-weight: 700; color: #475569;"><i class="fa fa-clock-o"></i> <?= $validity ?: $timelimit; ?></span><?php endif; ?>
      </div>
      <?php endif; ?>
      <?php if ($vShowDns || ($vShowDataLimit && !empty($datalimit))): ?>
      <div style="font-size: 8px; color: #64748b; margin-top: 3px; display: flex; justify-content: space-between; border-top: 1px dotted #e2e8f0; padding-top: 2px;">
        <span><?= $vShowDns ? $lblLogin . ': ' . $dnsname : ''; ?></span>
        <span><?= ($vShowDataLimit && !empty($datalimit)) ? $datalimit : ''; ?></span>
      </div>
      <?php endif; ?>
    </div>
  </div>

<?php elseif ($vStyle === 'industrial-grid'): ?>
  <!-- STYLE 4: INDUSTRIAL BRUTALIST GRID -->
  <style>
  .v-brutal {
    background: #ffffff;
    border: 2px solid <?= $c['border']; ?>;
    border-radius: 0;
    box-shadow: 2px 2px 0px <?= $c['border']; ?>;
  }
  .v-brutal-hdr {
    background: <?= $c['accent']; ?>;
    color: #ffffff;
    padding: 3px 6px;
    font-family: monospace;
    font-weight: 900;
    font-size: 10px;
    display: flex;
    justify-content: space-between;
    letter-spacing: 0.5px;
    min-height: 18px;
  }
  .v-brutal-body {
    padding: 5px;
  }
  .v-brutal-box {
    border: 1.5px solid <?= $c['border']; ?>;
    padding: 3px;
    text-align: center;
    background: <?= $c['box_bg']; ?>;
  }
  .v-brutal-code {
    font-family: "Courier New", Courier, monospace;
    font-size: 14px;
    font-weight: 900;
    color: #000;
    letter-spacing: 1px;
    line-height: 1.2;
    word-break: break-all;
  }
  .v-brutal-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 4px;
    font-size: 8.5px;
    font-family: monospace;
  }
  .v-brutal-table td {
    border: 1px solid <?= $c['border']; ?>;
    padding: 2px 4px;
  }
  </style>
  <div class="v-card-base v-brutal">
    <div class="v-brutal-hdr">
      <div style="display: flex; align-items: center; gap: 4px; max-width: 155px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
        <?php if ($vShowLogo && !empty($logo)): ?><img src="<?= $logo; ?>" style="height: 14px; max-width: 35px; object-fit: contain; vertical-align: middle;"><?php endif; ?>
        <?php if ($vShowHsName): ?><span><?= strtoupper(htmlspecialchars($hotspotname)); ?></span><?php endif; ?>
      </div>
      <?php if ($vShowNum): ?><span>[#<?= $num; ?>]</span><?php endif; ?>
    </div>
    <div class="v-brutal-body">
      <div style="display: table; width: 100%;">
        <div style="display: table-cell; vertical-align: middle; padding-right: 4px;">
          <?php if ($usermode == "vc"): ?>
            <div class="v-brutal-box">
              <div style="font-size: 7px; font-weight: 900; font-family: monospace;">[ <?= strtoupper($lblPass); ?> ]</div>
              <div class="v-brutal-code"><?= $username; ?></div>
            </div>
          <?php else: ?>
            <div class="v-brutal-box" style="text-align: left; padding: 2px 4px;">
              <div style="font-family: monospace; font-size: 10px; font-weight: 900;">USR: <?= $username; ?></div>
              <div style="font-family: monospace; font-size: 10px; font-weight: 900;">PWD: <?= $password; ?></div>
            </div>
          <?php endif; ?>
        </div>
        <div style="display: table-cell; vertical-align: middle; width: 62px; text-align: center;" class="v-qr-canvas">
          <?= $qrcode; ?>
        </div>
      </div>
      <?php if ($vShowPrice || $vShowValidity): ?>
      <table class="v-brutal-table">
        <tr>
          <?php if ($vShowPrice): ?><td style="font-weight: 900; background: <?= $c['box_bg']; ?>; color: <?= $c['accent']; ?>;"><?= $vIsFr ? 'PRIX' : 'PRICE'; ?>: <?= !empty($price) ? $price : $profile; ?></td><?php endif; ?>
          <?php if ($vShowValidity): ?><td style="text-align: <?= $vShowPrice ? 'right' : 'left'; ?>;"><?= $validity ?: $timelimit; ?></td><?php endif; ?>
        </tr>
      </table>
      <?php endif; ?>
      <?php if ($vShowDns || ($vShowDataLimit && !empty($datalimit))): ?>
      <div style="font-size: 7.5px; font-family: monospace; color: #334155; margin-top: 2px; display: flex; justify-content: space-between;">
        <span><?= $vShowDns ? 'IP: ' . $dnsname : ''; ?></span>
        <span><?= ($vShowDataLimit && !empty($datalimit)) ? 'CAP:' . $datalimit : ''; ?></span>
      </div>
      <?php endif; ?>
    </div>
  </div>

<?php elseif ($vStyle === 'cyber-neon'): ?>
  <!-- STYLE 5: CYBER GAMER / ESPORTS DARK MODE -->
  <style>
  .v-cyber {
    background: <?= $c['dark_card']; ?>;
    border: 1.5px solid <?= $c['neon']; ?>;
    border-radius: 6px;
    box-shadow: 0 0 6px rgba(0,0,0,0.6);
    color: #f8fafc;
  }
  .v-cyber-hdr {
    border-bottom: 1px solid <?= $c['neon']; ?>;
    background: rgba(0,0,0,0.4);
    padding: 4px 6px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    min-height: 18px;
  }
  .v-cyber-brand {
    font-size: 11px;
    font-weight: 900;
    letter-spacing: 0.5px;
    display: flex;
    align-items: center;
    gap: 4px;
    max-width: 150px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    color: var(--c-neon);
  }
  .v-cyber-brand img { height: 16px; max-width: 35px; object-fit: contain; }
  .v-cyber-body {
    padding: 6px 8px;
  }
  .v-cyber-codebox {
    background: <?= $c['dark_box']; ?>;
    border: 1px solid <?= $c['neon']; ?>;
    border-radius: 4px;
    padding: 4px 6px;
    text-align: center;
  }
  .v-cyber-code {
    font-family: "Courier New", Courier, monospace;
    font-size: 15px;
    font-weight: 900;
    letter-spacing: 1.5px;
    color: #ffffff;
    text-shadow: 0 0 4px <?= $c['neon']; ?>;
    line-height: 1.2;
    word-break: break-all;
  }
  .v-cyber-meta {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 5px;
    font-size: 9px;
  }
  .v-cyber-price {
    font-weight: 900;
    background: <?= $c['neon']; ?>;
    color: #0f172a;
    padding: 1px 6px;
    border-radius: 3px;
  }
  </style>
  <div class="v-card-base v-cyber">
    <div class="v-cyber-hdr">
      <div class="v-cyber-brand">
        <?php if ($vShowLogo && !empty($logo)): ?><img src="<?= $logo; ?>"><?php endif; ?>
        <?php if ($vShowHsName): ?><span><?= htmlspecialchars($hotspotname); ?></span><?php endif; ?>
      </div>
      <?php if ($vShowNum): ?><span style="font-size: 7.5px; font-weight: 800; border: 1px solid <?= $c['neon']; ?>; padding: 1px 4px; border-radius: 3px; color: <?= $c['neon']; ?>;">#<?= $num; ?></span><?php endif; ?>
    </div>
    <div class="v-cyber-body">
      <div style="display: table; width: 100%;">
        <div style="display: table-cell; vertical-align: middle; padding-right: 6px;">
          <?php if ($usermode == "vc"): ?>
            <div class="v-cyber-codebox">
              <div style="font-size: 7px; font-weight: 800; color: <?= $c['neon']; ?>; text-transform: uppercase;"><?= $lblPass; ?></div>
              <div class="v-cyber-code"><?= $username; ?></div>
            </div>
          <?php else: ?>
            <div class="v-cyber-codebox" style="padding: 2px 3px; text-align: left;">
              <div style="font-family: monospace; font-size: 10.5px; color: <?= $c['neon']; ?>; font-weight: 800;">U: <?= $username; ?></div>
              <div style="font-family: monospace; font-size: 10.5px; color: #fff; font-weight: 800;">P: <?= $password; ?></div>
            </div>
          <?php endif; ?>
        </div>
        <div style="display: table-cell; vertical-align: middle; width: 62px; text-align: center; background: #fff; border-radius: 4px; padding: 2px;" class="v-qr-canvas">
          <?= $qrcode; ?>
        </div>
      </div>
      <?php if ($vShowPrice || $vShowValidity): ?>
      <div class="v-cyber-meta">
        <?php if ($vShowPrice): ?><span class="v-cyber-price"><?= !empty($price) ? $price : $profile; ?></span><?php else: ?><span></span><?php endif; ?>
        <?php if ($vShowValidity): ?><span style="color: <?= $c['neon']; ?>; font-weight: 700;"><?= $validity ?: $timelimit; ?></span><?php endif; ?>
      </div>
      <?php endif; ?>
      <?php if ($vShowDns || ($vShowDataLimit && !empty($datalimit))): ?>
      <div style="font-size: 7.5px; color: #94a3b8; margin-top: 3px; display: flex; justify-content: space-between; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 2px;">
        <span><?= $vShowDns ? $lblLogin . ': ' . $dnsname : ''; ?></span>
        <span><?= ($vShowDataLimit && !empty($datalimit)) ? $datalimit : ($vShowDns ? 'ULTRA FAST' : ''); ?></span>
      </div>
      <?php endif; ?>
    </div>
  </div>

<?php elseif ($vStyle === 'minimal-stamp'): ?>
  <!-- STYLE 6: COMPACT CLEAN STAMP -->
  <style>
  .v-stamp {
    background: #ffffff;
    border: 3px double <?= $c['border']; ?>;
    border-radius: 4px;
    padding: 4px 6px;
    color: #0f172a;
  }
  .v-stamp-hdr {
    border-bottom: 1px solid <?= $c['border']; ?>;
    padding-bottom: 2px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    min-height: 16px;
  }
  .v-stamp-brand {
    font-size: 11px;
    font-weight: 900;
    color: <?= $c['accent']; ?>;
    letter-spacing: 0.5px;
    display: flex;
    align-items: center;
    gap: 4px;
    max-width: 150px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }
  .v-stamp-brand img { height: 16px; max-width: 35px; object-fit: contain; }
  .v-stamp-codebox {
    border: 1px solid <?= $c['border']; ?>;
    background: <?= $c['box_bg']; ?>;
    padding: 3px;
    text-align: center;
    margin-top: 3px;
  }
  .v-stamp-code {
    font-family: "Courier New", Courier, monospace;
    font-size: 14px;
    font-weight: 900;
    letter-spacing: 1px;
    line-height: 1.2;
    word-break: break-all;
  }
  </style>
  <div class="v-card-base v-stamp">
    <div class="v-stamp-hdr">
      <div class="v-stamp-brand">
        <?php if ($vShowLogo && !empty($logo)): ?><img src="<?= $logo; ?>"><?php endif; ?>
        <?php if ($vShowHsName): ?><span><?= htmlspecialchars($hotspotname); ?></span><?php endif; ?>
      </div>
      <?php if ($vShowNum): ?><span style="font-size: 8px; font-weight: 700;">#<?= $num; ?></span><?php endif; ?>
    </div>
    <div style="display: table; width: 100%; margin-top: 3px;">
      <div style="display: table-cell; vertical-align: middle; padding-right: 4px;">
        <?php if ($usermode == "vc"): ?>
          <div class="v-stamp-codebox">
            <div style="font-size: 7px; font-weight: 800; color: <?= $c['accent']; ?>; text-transform: uppercase;"><?= $lblCode; ?></div>
            <div class="v-stamp-code"><?= $username; ?></div>
          </div>
        <?php else: ?>
          <div class="v-stamp-codebox" style="padding: 2px 3px; text-align: left;">
            <div style="font-family: monospace; font-size: 10.5px; font-weight: 800;"><?= $lblUser; ?>: <?= $username; ?></div>
            <div style="font-family: monospace; font-size: 10.5px; font-weight: 800;"><?= $lblPassword; ?>: <?= $password; ?></div>
          </div>
        <?php endif; ?>
        <?php if ($vShowPrice || $vShowValidity): ?>
        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 3px; font-size: 8.5px;">
          <?php if ($vShowPrice): ?><span style="font-weight: 900; color: <?= $c['accent']; ?>;"><?= !empty($price) ? $price : $profile; ?></span><?php else: ?><span></span><?php endif; ?>
          <?php if ($vShowValidity): ?><span style="color: #475569; font-weight: 700;"><?= $validity ?: $timelimit; ?></span><?php endif; ?>
        </div>
        <?php endif; ?>
      </div>
      <div style="display: table-cell; vertical-align: middle; width: 62px; text-align: center;" class="v-qr-canvas">
        <?= $qrcode; ?>
      </div>
    </div>
    <?php if ($vShowDns || ($vShowDataLimit && !empty($datalimit))): ?>
    <div style="font-size: 7.5px; color: #64748b; margin-top: 2px; border-top: 1px dotted #cbd5e1; padding-top: 1px; display: flex; justify-content: space-between;">
      <span><?= $vShowDns ? $lblLogin . ': ' . $dnsname : ''; ?></span>
      <span><?= ($vShowDataLimit && !empty($datalimit)) ? $datalimit : ($vShowDns ? 'HOTSPOT' : ''); ?></span>
    </div>
    <?php endif; ?>
  </div>

<?php elseif ($vStyle === 'retro-arcade'): ?>
  <!-- STYLE 7: RETRO ARCADE 8-BIT PIXEL -->
  <style>
  .v-retro-c {
    background: #ffffff;
    border: 2px solid #0f172a;
    box-shadow: 3px 3px 0px #0f172a;
    border-radius: 0;
  }
  .v-retro-hdr-c {
    background: #0f172a;
    color: <?= $c['neon']; ?>;
    padding: 4px 6px;
    font-family: monospace;
    font-weight: 900;
    font-size: 10px;
    display: flex;
    justify-content: space-between;
    align-items: center;
  }
  .v-retro-box-c {
    border: 1.5px solid #0f172a;
    background: <?= $c['box_bg']; ?>;
    padding: 4px;
    text-align: center;
  }
  .v-retro-code-c {
    font-family: "Courier New", Courier, monospace;
    font-size: 15px;
    font-weight: 900;
    color: #000;
    letter-spacing: 1.5px;
    line-height: 1.2;
    word-break: break-all;
  }
  </style>
  <div class="v-card-base v-retro-c">
    <div class="v-retro-hdr-c">
      <div style="display: flex; align-items: center; gap: 4px; max-width: 155px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
        <?php if ($vShowLogo && !empty($logo)): ?><img src="<?= $logo; ?>" style="height: 14px; max-width: 35px; object-fit: contain; vertical-align: middle;"><?php endif; ?>
        <?php if ($vShowHsName): ?><span>► <?= strtoupper(htmlspecialchars($hotspotname)); ?></span><?php endif; ?>
      </div>
      <?php if ($vShowNum): ?><span>[LVL.<?= $num; ?>]</span><?php endif; ?>
    </div>
    <div style="padding: 5px;">
      <div style="display: table; width: 100%;">
        <div style="display: table-cell; vertical-align: middle; padding-right: 4px;">
          <?php if ($usermode == "vc"): ?>
            <div class="v-retro-box-c">
              <div style="font-size: 7px; font-weight: 900; font-family: monospace; color: <?= $c['accent']; ?>;">[ <?= strtoupper($lblPass); ?> ]</div>
              <div class="v-retro-code-c"><?= $username; ?></div>
            </div>
          <?php else: ?>
            <div class="v-retro-box-c" style="text-align: left; padding: 2px 4px;">
              <div style="font-family: monospace; font-size: 10.5px; font-weight: 900;">USR: <?= $username; ?></div>
              <div style="font-family: monospace; font-size: 10.5px; font-weight: 900;">PWD: <?= $password; ?></div>
            </div>
          <?php endif; ?>
        </div>
        <div style="display: table-cell; vertical-align: middle; width: 62px; text-align: center;" class="v-qr-canvas">
          <div style="border: 1.5px solid #0f172a; padding: 1px; display: inline-block;">
            <?= $qrcode; ?>
          </div>
        </div>
      </div>
      <?php if ($vShowPrice || $vShowValidity): ?>
      <div style="border: 1.5px solid #0f172a; margin-top: 4px; padding: 3px 5px; display: flex; justify-content: space-between; font-family: monospace; font-size: 8.5px; background: #f8fafc;">
        <?php if ($vShowPrice): ?><span style="font-weight: 900; color: <?= $c['accent']; ?>;"><?= !empty($price) ? $price : $profile; ?></span><?php else: ?><span></span><?php endif; ?>
        <?php if ($vShowValidity): ?><span style="font-weight: 700;"><?= $validity ?: $timelimit; ?></span><?php endif; ?>
      </div>
      <?php endif; ?>
      <?php if ($vShowDns || ($vShowDataLimit && !empty($datalimit))): ?>
      <div style="font-size: 7.5px; font-family: monospace; color: #475569; margin-top: 3px; display: flex; justify-content: space-between;">
        <span><?= $vShowDns ? 'URL: ' . $dnsname : ''; ?></span>
        <span><?= ($vShowDataLimit && !empty($datalimit)) ? 'DATA:' . $datalimit : ($vShowDns ? '1 COIN' : ''); ?></span>
      </div>
      <?php endif; ?>
    </div>
  </div>

<?php elseif ($vStyle === 'luxury-vip'): ?>
  <!-- STYLE 8: LUXURY GOLD VIP RIBBON -->
  <style>
  .v-luxury-c {
    background: #0f172a;
    border: 1.5px solid #d97706;
    border-radius: 8px;
    box-shadow: 0 4px 10px rgba(0,0,0,0.3);
    color: #f8fafc;
  }
  .v-luxury-hdr-c {
    background: #1e293b;
    border-bottom: 1px solid #d97706;
    padding: 4px 8px;
    color: #fbbf24;
    font-weight: 900;
    font-size: 11px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    min-height: 18px;
  }
  .v-luxury-hdr-c img { height: 16px; max-width: 35px; object-fit: contain; }
  .v-luxury-box-c {
    background: #1c1917;
    border: 1px solid #d97706;
    border-radius: 5px;
    padding: 4px;
    text-align: center;
  }
  .v-luxury-code-c {
    font-family: "Courier New", Courier, monospace;
    font-size: 15px;
    font-weight: 900;
    letter-spacing: 1.5px;
    color: #fef08a;
    line-height: 1.2;
    word-break: break-all;
  }
  </style>
  <div class="v-card-base v-luxury-c">
    <div class="v-luxury-hdr-c">
      <div style="display: flex; align-items: center; gap: 4px; max-width: 155px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
        <?php if ($vShowLogo && !empty($logo)): ?><img src="<?= $logo; ?>"><?php endif; ?>
        <?php if ($vShowHsName): ?><span>★ <?= htmlspecialchars($hotspotname); ?></span><?php endif; ?>
      </div>
      <?php if ($vShowNum): ?><span style="font-size: 7.5px; background: rgba(217,119,6,0.25); border: 1px solid #d97706; padding: 1px 4px; border-radius: 3px; color: #fbbf24;">VIP</span><?php endif; ?>
    </div>
    <div style="padding: 6px 8px;">
      <div style="display: table; width: 100%;">
        <div style="display: table-cell; vertical-align: middle; padding-right: 6px;">
          <?php if ($usermode == "vc"): ?>
            <div class="v-luxury-box-c">
              <div style="font-size: 7px; font-weight: 800; color: #fbbf24; letter-spacing: 0.5px;">★ VIP PASSCODE ★</div>
              <div class="v-luxury-code-c"><?= $username; ?></div>
            </div>
          <?php else: ?>
            <div class="v-luxury-box-c" style="padding: 2px 3px; text-align: left;">
              <div style="font-family: monospace; font-size: 10.5px; color: #fbbf24; font-weight: 800;">U: <?= $username; ?></div>
              <div style="font-family: monospace; font-size: 10.5px; color: #fef08a; font-weight: 800;">P: <?= $password; ?></div>
            </div>
          <?php endif; ?>
        </div>
        <div style="display: table-cell; vertical-align: middle; width: 62px; text-align: center; background: #ffffff; border-radius: 4px; padding: 2px;" class="v-qr-canvas">
          <?= $qrcode; ?>
        </div>
      </div>
      <?php if ($vShowPrice || $vShowValidity): ?>
      <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 5px; font-size: 9px;">
        <?php if ($vShowPrice): ?>
          <span style="background: linear-gradient(135deg, #d97706, #b45309); color: #ffffff; font-weight: 900; padding: 1px 6px; border-radius: 3px;">
            <?= !empty($price) ? $price : $profile; ?>
          </span>
        <?php else: ?>
          <span></span>
        <?php endif; ?>
        <?php if ($vShowValidity): ?><span style="color: #fbbf24; font-weight: 700;"><?= $validity ?: $timelimit; ?></span><?php endif; ?>
      </div>
      <?php endif; ?>
      <?php if ($vShowDns || ($vShowDataLimit && !empty($datalimit))): ?>
      <div style="font-size: 7.5px; color: #94a3b8; margin-top: 3px; display: flex; justify-content: space-between; border-top: 1px solid rgba(217,119,6,0.3); padding-top: 2px;">
        <span><?= $vShowDns ? $lblLogin . ': ' . $dnsname : ''; ?></span>
        <span><?= ($vShowDataLimit && !empty($datalimit)) ? $datalimit : ($vShowDns ? 'VIP NETWORK' : ''); ?></span>
      </div>
      <?php endif; ?>
    </div>
  </div>

<?php elseif ($vStyle === 'synthwave-sunset'): ?>
  <!-- STYLE 9: NEON SYNTHWAVE 80s -->
  <style>
  .v-synth-c {
    background: #150d2a;
    border: 1.5px solid #ec4899;
    border-radius: 8px;
    color: #ffffff;
    box-shadow: 0 0 8px rgba(236,72,153,0.35);
  }
  .v-synth-hdr-c {
    background: linear-gradient(90deg, #ec4899, #8b5cf6, #06b6d4);
    color: #ffffff;
    padding: 4px 8px;
    font-weight: 900;
    font-size: 11px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    min-height: 18px;
  }
  .v-synth-hdr-c img { height: 16px; max-width: 35px; object-fit: contain; }
  .v-synth-box-c {
    background: #241442;
    border: 1px solid #06b6d4;
    border-radius: 4px;
    padding: 4px 6px;
    text-align: center;
  }
  .v-synth-code-c {
    font-family: "Courier New", Courier, monospace;
    font-size: 15px;
    font-weight: 900;
    letter-spacing: 1.5px;
    color: #ffffff;
    text-shadow: 0 0 5px #06b6d4;
    line-height: 1.2;
    word-break: break-all;
  }
  </style>
  <div class="v-card-base v-synth-c">
    <div class="v-synth-hdr-c">
      <div style="display: flex; align-items: center; gap: 4px; max-width: 155px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
        <?php if ($vShowLogo && !empty($logo)): ?><img src="<?= $logo; ?>"><?php endif; ?>
        <?php if ($vShowHsName): ?><span><?= htmlspecialchars($hotspotname); ?></span><?php endif; ?>
      </div>
      <?php if ($vShowNum): ?><span style="font-size: 7.5px; background: rgba(0,0,0,0.35); padding: 1px 4px; border-radius: 3px;">80s</span><?php endif; ?>
    </div>
    <div style="padding: 6px 8px;">
      <div style="display: table; width: 100%;">
        <div style="display: table-cell; vertical-align: middle; padding-right: 6px;">
          <?php if ($usermode == "vc"): ?>
            <div class="v-synth-box-c">
              <div style="font-size: 7px; font-weight: 800; color: #ec4899; letter-spacing: 0.5px;">SYNTH ACCESS</div>
              <div class="v-synth-code-c"><?= $username; ?></div>
            </div>
          <?php else: ?>
            <div class="v-synth-box-c" style="padding: 2px 3px; text-align: left;">
              <div style="font-family: monospace; font-size: 10.5px; color: #ec4899; font-weight: 800;">U: <?= $username; ?></div>
              <div style="font-family: monospace; font-size: 10.5px; color: #06b6d4; font-weight: 800;">P: <?= $password; ?></div>
            </div>
          <?php endif; ?>
        </div>
        <div style="display: table-cell; vertical-align: middle; width: 62px; text-align: center; background: #ffffff; border-radius: 4px; padding: 2px;" class="v-qr-canvas">
          <?= $qrcode; ?>
        </div>
      </div>
      <?php if ($vShowPrice || $vShowValidity): ?>
      <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 5px; font-size: 9px;">
        <?php if ($vShowPrice): ?>
          <span style="background: #ec4899; color: #ffffff; font-weight: 900; padding: 1px 6px; border-radius: 3px;">
            <?= !empty($price) ? $price : $profile; ?>
          </span>
        <?php else: ?>
          <span></span>
        <?php endif; ?>
        <?php if ($vShowValidity): ?><span style="color: #06b6d4; font-weight: 700;"><?= $validity ?: $timelimit; ?></span><?php endif; ?>
      </div>
      <?php endif; ?>
      <?php if ($vShowDns || ($vShowDataLimit && !empty($datalimit))): ?>
      <div style="font-size: 7.5px; color: #94a3b8; margin-top: 3px; display: flex; justify-content: space-between; border-top: 1px solid rgba(255,255,255,0.15); padding-top: 2px;">
        <span><?= $vShowDns ? $lblLogin . ': ' . $dnsname : ''; ?></span>
        <span><?= ($vShowDataLimit && !empty($datalimit)) ? $datalimit : ($vShowDns ? 'FAST WAVE' : ''); ?></span>
      </div>
      <?php endif; ?>
    </div>
  </div>

<?php elseif ($vStyle === 'vintage-kraft'): ?>
  <!-- STYLE 10: COFFEE SHOP KRAFT PAPER -->
  <style>
  .v-kraft-c {
    background: #fbf7ee;
    border: 1.5px dashed #78350f;
    border-radius: 8px;
    color: #451a03;
    box-shadow: 0 2px 5px rgba(120,53,15,0.08);
  }
  .v-kraft-hdr-c {
    background: #78350f;
    color: #fef3c7;
    padding: 4px 8px;
    font-weight: 900;
    font-size: 11px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    min-height: 18px;
  }
  .v-kraft-hdr-c img { height: 16px; max-width: 35px; object-fit: contain; }
  .v-kraft-box-c {
    background: #ffffff;
    border: 1px dotted #78350f;
    border-radius: 5px;
    padding: 4px 6px;
    text-align: center;
  }
  .v-kraft-code-c {
    font-family: "Courier New", Courier, monospace;
    font-size: 15px;
    font-weight: 900;
    color: #451a03;
    letter-spacing: 1.5px;
    line-height: 1.2;
    word-break: break-all;
  }
  </style>
  <div class="v-card-base v-kraft-c">
    <div class="v-kraft-hdr-c">
      <div style="display: flex; align-items: center; gap: 4px; max-width: 155px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
        <?php if ($vShowLogo && !empty($logo)): ?><img src="<?= $logo; ?>"><?php endif; ?>
        <?php if ($vShowHsName): ?><span>☕ <?= htmlspecialchars($hotspotname); ?></span><?php endif; ?>
      </div>
      <?php if ($vShowNum): ?><span style="font-size: 7.5px; background: rgba(255,255,255,0.2); padding: 1px 4px; border-radius: 2px;">#<?= $num; ?></span><?php endif; ?>
    </div>
    <div style="padding: 6px 8px;">
      <div style="display: table; width: 100%;">
        <div style="display: table-cell; vertical-align: middle; padding-right: 6px;">
          <?php if ($usermode == "vc"): ?>
            <div class="v-kraft-box-c">
              <div style="font-size: 7px; font-weight: 800; color: #78350f; text-transform: uppercase;">KODE INTERNET</div>
              <div class="v-kraft-code-c"><?= $username; ?></div>
            </div>
          <?php else: ?>
            <div class="v-kraft-box-c" style="padding: 2px 3px; text-align: left;">
              <div style="font-family: monospace; font-size: 10.5px; color: #78350f; font-weight: 800;">U: <?= $username; ?></div>
              <div style="font-family: monospace; font-size: 10.5px; color: #451a03; font-weight: 800;">P: <?= $password; ?></div>
            </div>
          <?php endif; ?>
        </div>
        <div style="display: table-cell; vertical-align: middle; width: 62px; text-align: center; background: #ffffff; border: 1px solid #e7dcd2; border-radius: 4px; padding: 2px;" class="v-qr-canvas">
          <?= $qrcode; ?>
        </div>
      </div>
      <?php if ($vShowPrice || $vShowValidity): ?>
      <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 5px; font-size: 9px;">
        <?php if ($vShowPrice): ?>
          <span style="background: #78350f; color: #fef3c7; font-weight: 900; padding: 1px 6px; border-radius: 3px;">
            <?= !empty($price) ? $price : $profile; ?>
          </span>
        <?php else: ?>
          <span></span>
        <?php endif; ?>
        <?php if ($vShowValidity): ?><span style="color: #78350f; font-weight: 700;">⏳ <?= $validity ?: $timelimit; ?></span><?php endif; ?>
      </div>
      <?php endif; ?>
      <?php if ($vShowDns || ($vShowDataLimit && !empty($datalimit))): ?>
      <div style="font-size: 7.5px; color: #78350f; margin-top: 3px; display: flex; justify-content: space-between; border-top: 1px dotted #78350f; padding-top: 2px;">
        <span><?= $vShowDns ? $lblLogin . ': ' . $dnsname : ''; ?></span>
        <span><?= ($vShowDataLimit && !empty($datalimit)) ? $datalimit : ($vShowDns ? 'WIFI & RELAX' : ''); ?></span>
      </div>
      <?php endif; ?>
    </div>
  </div>

<?php elseif ($vStyle === 'glass-gradient'): ?>
  <!-- STYLE 11: MODERN FROSTED GLASS -->
  <style>
  .v-glass-c {
    background: #ffffff;
    border: 1.5px solid #e2e8f0;
    border-radius: 10px;
    overflow: hidden;
    color: #0f172a;
    box-shadow: 0 4px 10px rgba(0,0,0,0.06);
  }
  .v-glass-bar-c {
    height: 4px;
    background: linear-gradient(90deg, <?= $c['accent']; ?>, #a855f7, #ec4899);
  }
  .v-glass-hdr-c {
    padding: 4px 8px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid #f1f5f9;
    min-height: 18px;
  }
  .v-glass-brand-c {
    font-size: 11px;
    font-weight: 900;
    display: flex;
    align-items: center;
    gap: 4px;
    max-width: 155px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }
  .v-glass-brand-c img { height: 16px; max-width: 35px; object-fit: contain; }
  .v-glass-box-c {
    background: linear-gradient(135deg, <?= $c['box_bg']; ?> 0%, #ffffff 100%);
    border: 1px solid <?= $c['border']; ?>;
    border-radius: 6px;
    padding: 4px;
    text-align: center;
  }
  .v-glass-code-c {
    font-family: "Courier New", Courier, monospace;
    font-size: 15px;
    font-weight: 900;
    letter-spacing: 1.5px;
    color: #0f172a;
    line-height: 1.2;
    word-break: break-all;
  }
  </style>
  <div class="v-card-base v-glass-c">
    <div class="v-glass-bar-c"></div>
    <div class="v-glass-hdr-c">
      <div class="v-glass-brand-c">
        <?php if ($vShowLogo && !empty($logo)): ?><img src="<?= $logo; ?>"><?php endif; ?>
        <?php if ($vShowHsName): ?><span><?= htmlspecialchars($hotspotname); ?></span><?php endif; ?>
      </div>
      <?php if ($vShowNum): ?><span style="font-size: 7.5px; background: #f1f5f9; padding: 1px 5px; border-radius: 99px; font-weight: 800;">#<?= $num; ?></span><?php endif; ?>
    </div>
    <div style="padding: 6px 8px;">
      <div style="display: table; width: 100%;">
        <div style="display: table-cell; vertical-align: middle; padding-right: 6px;">
          <?php if ($usermode == "vc"): ?>
            <div class="v-glass-box-c">
              <div style="font-size: 7px; font-weight: 800; color: <?= $c['accent']; ?>; text-transform: uppercase;"><?= $lblCode; ?></div>
              <div class="v-glass-code-c"><?= $username; ?></div>
            </div>
          <?php else: ?>
            <div class="v-glass-box-c" style="padding: 2px 4px; text-align: left;">
              <div style="font-family: monospace; font-size: 11px; font-weight: 800;"><?= $lblUser; ?>: <?= $username; ?></div>
              <div style="font-family: monospace; font-size: 11px; font-weight: 800;"><?= $lblPassword; ?>: <?= $password; ?></div>
            </div>
          <?php endif; ?>
        </div>
        <div style="display: table-cell; vertical-align: middle; width: 62px; text-align: center;" class="v-qr-canvas">
          <?= $qrcode; ?>
        </div>
      </div>
      <?php if ($vShowPrice || $vShowValidity): ?>
      <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 5px;">
        <?php if ($vShowPrice): ?>
          <span style="font-size: 9.5px; font-weight: 900; background: <?= $c['badge_bg']; ?>; color: <?= $c['badge_text']; ?>; padding: 2px 6px; border-radius: 99px;">
            <?= !empty($price) ? $price : $profile; ?>
          </span>
        <?php else: ?>
          <span></span>
        <?php endif; ?>
        <?php if ($vShowValidity): ?><span style="font-size: 9px; font-weight: 700; color: #64748b;"><i class="fa fa-clock-o"></i> <?= $validity ?: $timelimit; ?></span><?php endif; ?>
      </div>
      <?php endif; ?>
      <?php if ($vShowDns || ($vShowDataLimit && !empty($datalimit))): ?>
      <div style="font-size: 8px; color: #64748b; margin-top: 3px; display: flex; justify-content: space-between; border-top: 1px dotted #e2e8f0; padding-top: 2px;">
        <span><?= $vShowDns ? $lblLogin . ': ' . $dnsname : ''; ?></span>
        <span><?= ($vShowDataLimit && !empty($datalimit)) ? $datalimit : ''; ?></span>
      </div>
      <?php endif; ?>
    </div>
  </div>

<?php elseif ($vStyle === 'split-coupon'): ?>
  <!-- STYLE 12: COMPACT SPLIT COUPON -->
  <style>
  .v-split-c {
    background: #ffffff;
    border: 1.5px solid <?= $c['border']; ?>;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 5px rgba(0,0,0,0.06);
    display: table;
    width: 215px;
  }
  .v-split-left-c {
    display: table-cell;
    width: 65px;
    background: <?= $c['accent']; ?>;
    color: #ffffff;
    vertical-align: middle;
    text-align: center;
    padding: 8px 4px;
    border-right: 1.5px dashed rgba(255,255,255,0.7);
  }
  .v-split-right-c {
    display: table-cell;
    vertical-align: middle;
    padding: 6px;
    background: #ffffff;
  }
  </style>
  <div class="v-card-base v-split-c">
    <div class="v-split-left-c">
      <div style="font-size: 8px; font-weight: 900; letter-spacing: 0.5px;">VOUCHER</div>
      <?php if ($vShowPrice): ?>
        <div style="font-size: 11px; font-weight: 900; margin-top: 4px;"><?= !empty($price) ? $price : $profile; ?></div>
      <?php endif; ?>
      <?php if ($vShowValidity): ?>
        <div style="font-size: 8px; font-weight: 700; margin-top: 4px; opacity: 0.9;"><?= $validity ?: $timelimit; ?></div>
      <?php endif; ?>
    </div>
    <div class="v-split-right-c">
      <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px dotted #cbd5e1; padding-bottom: 2px;">
        <span style="font-weight: 800; font-size: 9.5px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 105px;">
          <?php if ($vShowHsName): ?><?= htmlspecialchars($hotspotname); ?><?php endif; ?>
        </span>
        <?php if ($vShowNum): ?><span style="font-size: 7.5px; color: #64748b;">#<?= $num; ?></span><?php endif; ?>
      </div>
      <div style="display: table; width: 100%; margin-top: 4px;">
        <div style="display: table-cell; vertical-align: middle; padding-right: 4px;">
          <?php if ($usermode == "vc"): ?>
            <div style="background: <?= $c['box_bg']; ?>; border: 1px solid <?= $c['border']; ?>; border-radius: 4px; padding: 2px 3px; text-align: center;">
              <div style="font-family: monospace; font-size: 12.5px; font-weight: 900; color: #0f172a;"><?= $username; ?></div>
            </div>
          <?php else: ?>
            <div style="background: <?= $c['box_bg']; ?>; border: 1px solid <?= $c['border']; ?>; border-radius: 4px; padding: 2px; font-family: monospace; font-size: 10px; font-weight: 800;">
              <div>U: <?= $username; ?></div>
              <div>P: <?= $password; ?></div>
            </div>
          <?php endif; ?>
        </div>
        <div style="display: table-cell; vertical-align: middle; width: 45px; text-align: center;" class="v-qr-canvas">
          <?= $qrcode; ?>
        </div>
      </div>
      <?php if ($vShowDns): ?>
      <div style="font-size: 7.5px; color: #64748b; margin-top: 2px;">
        <?= $dnsname; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>

<?php else: ?>
  <!-- STYLE 1: MODERN BENTO CARD (DEFAULT) -->
  <style>
  .v-modern {
    background: #ffffff;
    border: 1.5px solid <?= $c['border']; ?>;
    border-radius: 8px;
    overflow: hidden;
    color: #0f172a;
    box-shadow: 0 2px 4px rgba(0,0,0,0.06);
  }
  .v-modern-hdr {
    background: linear-gradient(135deg, <?= $c['hdr_start']; ?> 0%, <?= $c['hdr_end']; ?> 100%);
    color: #ffffff;
    padding: 5px 8px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    min-height: 20px;
  }
  .v-modern-brand {
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: 11.5px;
    font-weight: 900;
    letter-spacing: 0.5px;
    max-width: 155px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }
  .v-modern-brand img {
    height: 17px;
    max-width: 38px;
    object-fit: contain;
  }
  .v-modern-body {
    padding: 6px 8px;
  }
  .v-modern-codebox {
    background: <?= $c['box_bg']; ?>;
    border: 1.5px dashed <?= $c['border']; ?>;
    border-radius: 6px;
    padding: 4px 6px;
    text-align: center;
    box-sizing: border-box;
  }
  .v-modern-label {
    font-size: 7px;
    font-weight: 800;
    color: <?= $c['accent']; ?>;
    letter-spacing: 0.5px;
    text-transform: uppercase;
    margin-bottom: 1px;
  }
  .v-modern-code {
    font-family: "Courier New", Courier, monospace;
    font-size: 16px;
    font-weight: 900;
    letter-spacing: 1.5px;
    color: #0f172a;
    line-height: 1.2;
    word-break: break-all;
  }
  .v-modern-up-val {
    font-family: "Courier New", Courier, monospace;
    font-size: 11.5px;
    font-weight: 900;
    color: #0f172a;
  }
  .v-modern-meta {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 5px;
  }
  .v-modern-price {
    font-size: 9.5px;
    font-weight: 900;
    background: <?= $c['badge_bg']; ?>;
    color: <?= $c['badge_text']; ?>;
    padding: 2px 6px;
    border-radius: 4px;
    letter-spacing: 0.3px;
  }
  .v-modern-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 8px;
    color: #64748b;
    margin-top: 4px;
    border-top: 1px dotted #e2e8f0;
    padding-top: 3px;
  }
  </style>
  <div class="v-card-base v-modern">
    <div class="v-modern-hdr">
      <div class="v-modern-brand">
        <?php if ($vShowLogo && !empty($logo)): ?><img src="<?= $logo; ?>"><?php endif; ?>
        <?php if ($vShowHsName): ?><span><?= htmlspecialchars($hotspotname); ?></span><?php endif; ?>
      </div>
      <?php if ($vShowNum): ?><span style="font-size: 8px; font-weight: 800; background: rgba(255,255,255,0.25); padding: 1px 5px; border-radius: 4px;">#<?= $num; ?></span><?php endif; ?>
    </div>
    <div class="v-modern-body">
      <?php if ($qr == "yes"): ?>
        <div style="display: table; width: 100%;">
          <div style="display: table-cell; vertical-align: middle; padding-right: 6px;">
            <?php if ($usermode == "vc"): ?>
              <div class="v-modern-codebox">
                <div class="v-modern-label"><?= $lblCode; ?></div>
                <div class="v-modern-code"><?= $username; ?></div>
              </div>
            <?php elseif ($usermode == "up"): ?>
              <div class="v-modern-codebox" style="padding: 2px 4px; text-align: left;">
                <div style="font-family: monospace; font-size: 11px; font-weight: 800;"><?= $lblUser; ?>: <?= $username; ?></div>
                <div style="font-family: monospace; font-size: 11px; font-weight: 800;"><?= $lblPassword; ?>: <?= $password; ?></div>
              </div>
            <?php endif; ?>
          </div>
          <div style="display: table-cell; vertical-align: middle; width: 62px; text-align: center;" class="v-qr-canvas">
            <?= $qrcode; ?>
          </div>
        </div>
      <?php else: ?>
        <?php if ($usermode == "vc"): ?>
          <div class="v-modern-codebox">
            <div class="v-modern-label"><?= $lblCode; ?></div>
            <div class="v-modern-code"><?= $username; ?></div>
          </div>
        <?php elseif ($usermode == "up"): ?>
          <div class="v-modern-codebox">
            <div style="display: table; width: 100%;">
              <div style="display: table-cell; width: 50%; border-right: 1px dashed <?= $c['border']; ?>; padding-right: 4px;">
                <div class="v-modern-label"><?= $lblUser; ?></div>
                <div class="v-modern-up-val"><?= $username; ?></div>
              </div>
              <div style="display: table-cell; width: 50%; padding-left: 4px;">
                <div class="v-modern-label"><?= $lblPassword; ?></div>
                <div class="v-modern-up-val"><?= $password; ?></div>
              </div>
            </div>
          </div>
        <?php endif; ?>
      <?php endif; ?>

      <?php if ($vShowPrice || $vShowValidity): ?>
      <div class="v-modern-meta">
        <?php if ($vShowPrice): ?><div class="v-modern-price"><?= !empty($price) ? $price : htmlspecialchars($profile); ?></div><?php else: ?><div></div><?php endif; ?>
        <?php if ($vShowValidity): ?>
        <div style="font-size: 9px; font-weight: 700; color: #475569;">
          <?php if (!empty($validity)): ?>
            <i class="fa fa-clock-o"></i> <?= $validity; ?>
          <?php elseif (!empty($timelimit)): ?>
            <i class="fa fa-clock-o"></i> <?= $timelimit; ?>
          <?php else: ?>
            <?= htmlspecialchars($profile); ?>
          <?php endif; ?>
        </div>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <?php if ($vShowDns || ($vShowDataLimit && !empty($datalimit))): ?>
      <div class="v-modern-footer">
        <?php if ($vShowDns): ?>
          <div><?= $lblLogin; ?>: <span style="font-weight: 600; color: <?= $c['accent']; ?>;"><?= $dnsname; ?></span></div>
        <?php else: ?>
          <div></div>
        <?php endif; ?>
        <?php if ($vShowDataLimit && !empty($datalimit)): ?>
          <div><?= $lblData; ?>: <?= $datalimit; ?></div>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>
<?php endif; ?>
