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
.v-card-sm-base {
  width: 160px;
  display: inline-block;
  vertical-align: top;
  margin: 2px;
  box-sizing: border-box;
  page-break-inside: avoid;
  font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
}
</style>

<?php if ($vStyle === 'ticket-notch'): ?>
  <style>
  .v-sm-ticket {
    background: #ffffff;
    border: 1px dashed <?= $c['border']; ?>;
    border-radius: 6px;
    overflow: hidden;
  }
  .v-sm-ticket-hdr {
    background: <?= $c['hdr_start']; ?>;
    color: #fff;
    padding: 3px 5px;
    font-size: 9.5px;
    font-weight: 800;
    display: flex;
    justify-content: space-between;
    align-items: center;
    min-height: 16px;
  }
  </style>
  <div class="v-card-sm-base v-sm-ticket">
    <div class="v-sm-ticket-hdr">
      <span style="display:flex;align-items:center;gap:3px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:115px;">
        <?php if ($vShowLogo && !empty($logo)): ?><img src="<?= $logo; ?>" style="height:12px;max-width:24px;object-fit:contain;"><?php endif; ?>
        <?php if ($vShowHsName): ?><?= htmlspecialchars($hotspotname); ?><?php endif; ?>
      </span>
      <?php if ($vShowNum): ?><span>#<?= $num; ?></span><?php endif; ?>
    </div>
    <div style="padding: 4px;">
      <div style="background: <?= $c['box_bg']; ?>; border: 1px solid <?= $c['border']; ?>; border-radius: 4px; padding: 3px; text-align: center;">
        <?php if ($usermode == "vc"): ?>
          <div style="font-size: 7px; font-weight: 800; color: <?= $c['accent']; ?>;"><?= $lblPass; ?></div>
          <div style="font-family: monospace; font-size: 13px; font-weight: 900;"><?= $username; ?></div>
        <?php else: ?>
          <div style="font-family: monospace; font-size: 10px; font-weight: 800;">U: <?= $username; ?> | P: <?= $password; ?></div>
        <?php endif; ?>
      </div>
      <?php if ($vShowPrice || $vShowValidity): ?>
      <div style="display: flex; justify-content: space-between; margin-top: 3px; font-size: 8px;">
        <?php if ($vShowPrice): ?><span style="font-weight: 900; color: <?= $c['accent']; ?>;"><?= !empty($price) ? $price : $profile; ?></span><?php else: ?><span></span><?php endif; ?>
        <?php if ($vShowValidity): ?><span style="color: #475569; font-weight: 700;"><?= $validity ?: $timelimit; ?></span><?php endif; ?>
      </div>
      <?php endif; ?>
      <?php if ($vShowDns): ?>
      <div style="font-size: 7.5px; color: #64748b; text-align: center; margin-top: 2px; border-top: 1px dotted #e2e8f0; padding-top: 2px;">
        <?= $dnsname; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>

<?php elseif ($vStyle === 'cyber-neon'): ?>
  <style>
  .v-sm-cyber {
    background: <?= $c['dark_card']; ?>;
    border: 1px solid <?= $c['neon']; ?>;
    border-radius: 4px;
    color: #fff;
  }
  .v-sm-cyber-hdr {
    background: rgba(0,0,0,0.4);
    border-bottom: 1px solid <?= $c['neon']; ?>;
    padding: 3px 5px;
    font-size: 9.5px;
    font-weight: 800;
    color: <?= $c['neon']; ?>;
    display: flex;
    justify-content: space-between;
    align-items: center;
    min-height: 16px;
  }
  </style>
  <div class="v-card-sm-base v-sm-cyber">
    <div class="v-sm-cyber-hdr">
      <span style="display:flex;align-items:center;gap:3px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:115px;">
        <?php if ($vShowLogo && !empty($logo)): ?><img src="<?= $logo; ?>" style="height:12px;max-width:24px;object-fit:contain;"><?php endif; ?>
        <?php if ($vShowHsName): ?><?= htmlspecialchars($hotspotname); ?><?php endif; ?>
      </span>
      <?php if ($vShowNum): ?><span>#<?= $num; ?></span><?php endif; ?>
    </div>
    <div style="padding: 4px;">
      <div style="background: <?= $c['dark_box']; ?>; border: 1px solid <?= $c['neon']; ?>; border-radius: 3px; padding: 3px; text-align: center;">
        <?php if ($usermode == "vc"): ?>
          <div style="font-size: 7px; color: <?= $c['neon']; ?>; font-weight: 800;"><?= $lblPass; ?></div>
          <div style="font-family: monospace; font-size: 13px; font-weight: 900; color: #fff; text-shadow: 0 0 3px <?= $c['neon']; ?>;"><?= $username; ?></div>
        <?php else: ?>
          <div style="font-family: monospace; font-size: 10px; color: #fff; font-weight: 800;">U: <?= $username; ?> | P: <?= $password; ?></div>
        <?php endif; ?>
      </div>
      <?php if ($vShowPrice || $vShowValidity): ?>
      <div style="display: flex; justify-content: space-between; margin-top: 3px; font-size: 8px;">
        <?php if ($vShowPrice): ?><span style="font-weight: 900; color: <?= $c['neon']; ?>;"><?= !empty($price) ? $price : $profile; ?></span><?php else: ?><span></span><?php endif; ?>
        <?php if ($vShowValidity): ?><span style="color: #94a3b8; font-weight: 700;"><?= $validity ?: $timelimit; ?></span><?php endif; ?>
      </div>
      <?php endif; ?>
      <?php if ($vShowDns): ?>
      <div style="font-size: 7.5px; color: #94a3b8; text-align: center; margin-top: 2px; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 2px;">
        <?= $dnsname; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>

<?php elseif ($vStyle === 'industrial-grid'): ?>
  <style>
  .v-sm-brutal {
    background: #ffffff;
    border: 1.5px solid <?= $c['border']; ?>;
    border-radius: 0;
  }
  .v-sm-brutal-hdr {
    background: <?= $c['accent']; ?>;
    color: #fff;
    padding: 3px 5px;
    font-size: 9px;
    font-family: monospace;
    font-weight: 900;
    display: flex;
    justify-content: space-between;
    align-items: center;
    min-height: 16px;
  }
  </style>
  <div class="v-card-sm-base v-sm-brutal">
    <div class="v-sm-brutal-hdr">
      <div style="display:flex;align-items:center;gap:3px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:115px;">
        <?php if ($vShowLogo && !empty($logo)): ?><img src="<?= $logo; ?>" style="height:11px;max-width:24px;object-fit:contain;"><?php endif; ?>
        <?php if ($vShowHsName): ?><span><?= strtoupper(htmlspecialchars($hotspotname)); ?></span><?php endif; ?>
      </div>
      <?php if ($vShowNum): ?><span>#<?= $num; ?></span><?php endif; ?>
    </div>
    <div style="padding: 4px;">
      <div style="border: 1px solid <?= $c['border']; ?>; background: <?= $c['box_bg']; ?>; padding: 3px; text-align: center;">
        <?php if ($usermode == "vc"): ?>
          <div style="font-size: 6.5px; font-weight: 900; font-family: monospace;">[ <?= strtoupper($lblPass); ?> ]</div>
          <div style="font-family: monospace; font-size: 12.5px; font-weight: 900;"><?= $username; ?></div>
        <?php else: ?>
          <div style="font-family: monospace; font-size: 9.5px; font-weight: 900;">U: <?= $username; ?> | P: <?= $password; ?></div>
        <?php endif; ?>
      </div>
      <?php if ($vShowPrice || $vShowValidity): ?>
      <div style="display: flex; justify-content: space-between; margin-top: 3px; font-family: monospace; font-size: 8px;">
        <?php if ($vShowPrice): ?><span style="font-weight: 900; color: <?= $c['accent']; ?>;"><?= !empty($price) ? $price : $profile; ?></span><?php else: ?><span></span><?php endif; ?>
        <?php if ($vShowValidity): ?><span><?= $validity ?: $timelimit; ?></span><?php endif; ?>
      </div>
      <?php endif; ?>
      <?php if ($vShowDns): ?>
      <div style="font-size: 7px; color: #64748b; font-family: monospace; text-align: center; margin-top: 2px;">
        <?= $dnsname; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>

<?php elseif ($vStyle === 'minimal-stamp'): ?>
  <style>
  .v-sm-stamp {
    background: #ffffff;
    border: 2px double <?= $c['border']; ?>;
    border-radius: 2px;
  }
  .v-sm-stamp-hdr {
    border-bottom: 1px solid <?= $c['border']; ?>;
    padding: 2px 4px;
    font-size: 9px;
    font-weight: 900;
    color: <?= $c['accent']; ?>;
    display: flex;
    justify-content: space-between;
    align-items: center;
    min-height: 16px;
  }
  </style>
  <div class="v-card-sm-base v-sm-stamp">
    <div class="v-sm-stamp-hdr">
      <span style="display:flex;align-items:center;gap:3px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:115px;">
        <?php if ($vShowLogo && !empty($logo)): ?><img src="<?= $logo; ?>" style="height:12px;max-width:24px;object-fit:contain;"><?php endif; ?>
        <?php if ($vShowHsName): ?><?= htmlspecialchars($hotspotname); ?><?php endif; ?>
      </span>
      <?php if ($vShowNum): ?><span>#<?= $num; ?></span><?php endif; ?>
    </div>
    <div style="padding: 4px;">
      <div style="border: 1px solid <?= $c['border']; ?>; background: <?= $c['box_bg']; ?>; padding: 2px; text-align: center;">
        <?php if ($usermode == "vc"): ?>
          <div style="font-size: 6.5px; font-weight: 800; color: <?= $c['accent']; ?>;"><?= $lblCode; ?></div>
          <div style="font-family: monospace; font-size: 12px; font-weight: 900;"><?= $username; ?></div>
        <?php else: ?>
          <div style="font-family: monospace; font-size: 9.5px; font-weight: 800;"><?= $lblUser; ?>: <?= $username; ?> | <?= $lblPassword; ?>: <?= $password; ?></div>
        <?php endif; ?>
      </div>
      <?php if ($vShowPrice || $vShowValidity): ?>
      <div style="display: flex; justify-content: space-between; margin-top: 3px; font-size: 8px;">
        <?php if ($vShowPrice): ?><span style="font-weight: 900; color: <?= $c['accent']; ?>;"><?= !empty($price) ? $price : $profile; ?></span><?php else: ?><span></span><?php endif; ?>
        <?php if ($vShowValidity): ?><span style="color: #475569; font-weight: 700;"><?= $validity ?: $timelimit; ?></span><?php endif; ?>
      </div>
      <?php endif; ?>
      <?php if ($vShowDns): ?>
      <div style="font-size: 7px; color: #64748b; text-align: center; margin-top: 2px; border-top: 1px dotted #cbd5e1; padding-top: 1px;">
        <?= $dnsname; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>

<?php elseif ($vStyle === 'curved-wave'): ?>
  <style>
  .v-sm-wave {
    background: #ffffff;
    border: 1px solid <?= $c['border']; ?>;
    border-radius: 8px;
    overflow: hidden;
  }
  .v-sm-wave-hdr {
    background: <?= $c['hdr_start']; ?>;
    color: #fff;
    padding: 3px 5px 6px;
    font-size: 9.5px;
    font-weight: 800;
    border-radius: 0 0 10px 10px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    min-height: 16px;
  }
  </style>
  <div class="v-card-sm-base v-sm-wave">
    <div class="v-sm-wave-hdr">
      <span style="display:flex;align-items:center;gap:3px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:115px;">
        <?php if ($vShowLogo && !empty($logo)): ?><img src="<?= $logo; ?>" style="height:12px;max-width:24px;object-fit:contain;"><?php endif; ?>
        <?php if ($vShowHsName): ?><?= htmlspecialchars($hotspotname); ?><?php endif; ?>
      </span>
      <?php if ($vShowNum): ?><span>#<?= $num; ?></span><?php endif; ?>
    </div>
    <div style="padding: 4px;">
      <div style="background: <?= $c['box_bg']; ?>; border: 1px dashed <?= $c['border']; ?>; border-radius: 6px; padding: 3px; text-align: center;">
        <?php if ($usermode == "vc"): ?>
          <div style="font-size: 6.5px; font-weight: 800; color: <?= $c['accent']; ?>;"><?= $lblCode; ?></div>
          <div style="font-family: monospace; font-size: 13px; font-weight: 900;"><?= $username; ?></div>
        <?php else: ?>
          <div style="font-family: monospace; font-size: 10px; font-weight: 800;">U: <?= $username; ?> | P: <?= $password; ?></div>
        <?php endif; ?>
      </div>
      <?php if ($vShowPrice || $vShowValidity): ?>
      <div style="display: flex; justify-content: space-between; margin-top: 3px; font-size: 8px;">
        <?php if ($vShowPrice): ?>
          <span style="background: <?= $c['badge_bg']; ?>; color: <?= $c['badge_text']; ?>; padding: 1px 4px; border-radius: 99px; font-weight: 900;">
            <?= !empty($price) ? $price : $profile; ?>
          </span>
        <?php else: ?>
          <span></span>
        <?php endif; ?>
        <?php if ($vShowValidity): ?><span style="color: #475569; font-weight: 700;"><?= $validity ?: $timelimit; ?></span><?php endif; ?>
      </div>
      <?php endif; ?>
      <?php if ($vShowDns): ?>
      <div style="font-size: 7.5px; color: #64748b; text-align: center; margin-top: 2px;">
        <?= $dnsname; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>

<?php elseif ($vStyle === 'retro-arcade'): ?>
  <!-- RETRO ARCADE SMALL -->
  <style>
  .v-sm-retro {
    background: #ffffff;
    border: 1.5px solid #0f172a;
    box-shadow: 2px 2px 0px #0f172a;
  }
  .v-sm-retro-hdr {
    background: #0f172a;
    color: <?= $c['neon']; ?>;
    padding: 3px 5px;
    font-size: 9px;
    font-family: monospace;
    font-weight: 900;
    display: flex;
    justify-content: space-between;
    align-items: center;
    min-height: 16px;
  }
  </style>
  <div class="v-card-sm-base v-sm-retro">
    <div class="v-sm-retro-hdr">
      <span style="display:flex;align-items:center;gap:3px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:115px;">
        <?php if ($vShowLogo && !empty($logo)): ?><img src="<?= $logo; ?>" style="height:11px;max-width:24px;object-fit:contain;"><?php endif; ?>
        <?php if ($vShowHsName): ?>► <?= strtoupper(htmlspecialchars($hotspotname)); ?><?php endif; ?>
      </span>
      <?php if ($vShowNum): ?><span>[L.<?= $num; ?>]</span><?php endif; ?>
    </div>
    <div style="padding: 4px;">
      <div style="border: 1px solid #0f172a; background: <?= $c['box_bg']; ?>; padding: 2px 3px; text-align: center;">
        <?php if ($usermode == "vc"): ?>
          <div style="font-size: 6px; font-weight: 900; font-family: monospace; color: <?= $c['accent']; ?>;">[ <?= strtoupper($lblPass); ?> ]</div>
          <div style="font-family: monospace; font-size: 12.5px; font-weight: 900; color: #000;"><?= $username; ?></div>
        <?php else: ?>
          <div style="font-family: monospace; font-size: 9.5px; font-weight: 900;">U: <?= $username; ?> | P: <?= $password; ?></div>
        <?php endif; ?>
      </div>
      <?php if ($vShowPrice || $vShowValidity): ?>
      <div style="display: flex; justify-content: space-between; margin-top: 3px; font-family: monospace; font-size: 8px;">
        <?php if ($vShowPrice): ?><span style="font-weight: 900; color: <?= $c['accent']; ?>;"><?= !empty($price) ? $price : $profile; ?></span><?php else: ?><span></span><?php endif; ?>
        <?php if ($vShowValidity): ?><span style="font-weight: 700;"><?= $validity ?: $timelimit; ?></span><?php endif; ?>
      </div>
      <?php endif; ?>
      <?php if ($vShowDns): ?>
      <div style="font-size: 7px; color: #475569; font-family: monospace; text-align: center; margin-top: 2px;">
        <?= $dnsname; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>

<?php elseif ($vStyle === 'luxury-vip'): ?>
  <!-- LUXURY VIP SMALL -->
  <style>
  .v-sm-luxury {
    background: #0f172a;
    border: 1px solid #d97706;
    border-radius: 6px;
    color: #fff;
  }
  .v-sm-luxury-hdr {
    background: #1e293b;
    border-bottom: 1px solid #d97706;
    padding: 3px 5px;
    font-size: 9.5px;
    font-weight: 900;
    color: #fbbf24;
    display: flex;
    justify-content: space-between;
    align-items: center;
    min-height: 16px;
  }
  </style>
  <div class="v-card-sm-base v-sm-luxury">
    <div class="v-sm-luxury-hdr">
      <span style="display:flex;align-items:center;gap:3px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:115px;">
        <?php if ($vShowLogo && !empty($logo)): ?><img src="<?= $logo; ?>" style="height:12px;max-width:24px;object-fit:contain;"><?php endif; ?>
        <?php if ($vShowHsName): ?>★ <?= htmlspecialchars($hotspotname); ?><?php endif; ?>
      </span>
      <?php if ($vShowNum): ?><span style="font-size: 7px; color: #fbbf24;">VIP</span><?php endif; ?>
    </div>
    <div style="padding: 4px;">
      <div style="background: #1c1917; border: 1px solid #d97706; border-radius: 4px; padding: 3px; text-align: center;">
        <?php if ($usermode == "vc"): ?>
          <div style="font-size: 6.5px; color: #fbbf24; font-weight: 800;">★ VIP PASSCODE ★</div>
          <div style="font-family: monospace; font-size: 13px; font-weight: 900; color: #fef08a;"><?= $username; ?></div>
        <?php else: ?>
          <div style="font-family: monospace; font-size: 10px; color: #fef08a; font-weight: 800;">U: <?= $username; ?> | P: <?= $password; ?></div>
        <?php endif; ?>
      </div>
      <?php if ($vShowPrice || $vShowValidity): ?>
      <div style="display: flex; justify-content: space-between; margin-top: 3px; font-size: 8px;">
        <?php if ($vShowPrice): ?><span style="background: #d97706; color: #fff; padding: 0 4px; border-radius: 2px; font-weight: 900;"><?= !empty($price) ? $price : $profile; ?></span><?php else: ?><span></span><?php endif; ?>
        <?php if ($vShowValidity): ?><span style="color: #fbbf24; font-weight: 700;"><?= $validity ?: $timelimit; ?></span><?php endif; ?>
      </div>
      <?php endif; ?>
      <?php if ($vShowDns): ?>
      <div style="font-size: 7.5px; color: #94a3b8; text-align: center; margin-top: 2px;">
        <?= $dnsname; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>

<?php elseif ($vStyle === 'synthwave-sunset'): ?>
  <!-- SYNTHWAVE SMALL -->
  <style>
  .v-sm-synth {
    background: #150d2a;
    border: 1px solid #ec4899;
    border-radius: 6px;
    color: #fff;
  }
  .v-sm-synth-hdr {
    background: linear-gradient(90deg, #ec4899, #8b5cf6, #06b6d4);
    padding: 3px 5px;
    font-size: 9.5px;
    font-weight: 900;
    color: #fff;
    display: flex;
    justify-content: space-between;
    align-items: center;
    min-height: 16px;
  }
  </style>
  <div class="v-card-sm-base v-sm-synth">
    <div class="v-sm-synth-hdr">
      <span style="display:flex;align-items:center;gap:3px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:115px;">
        <?php if ($vShowLogo && !empty($logo)): ?><img src="<?= $logo; ?>" style="height:12px;max-width:24px;object-fit:contain;"><?php endif; ?>
        <?php if ($vShowHsName): ?><?= htmlspecialchars($hotspotname); ?><?php endif; ?>
      </span>
      <?php if ($vShowNum): ?><span style="font-size: 7px;">80s</span><?php endif; ?>
    </div>
    <div style="padding: 4px;">
      <div style="background: #241442; border: 1px solid #06b6d4; border-radius: 4px; padding: 3px; text-align: center;">
        <?php if ($usermode == "vc"): ?>
          <div style="font-size: 6.5px; color: #ec4899; font-weight: 800;">WAVE CODE</div>
          <div style="font-family: monospace; font-size: 13px; font-weight: 900; color: #fff; text-shadow: 0 0 3px #06b6d4;"><?= $username; ?></div>
        <?php else: ?>
          <div style="font-family: monospace; font-size: 10px; color: #06b6d4; font-weight: 800;">U: <?= $username; ?> | P: <?= $password; ?></div>
        <?php endif; ?>
      </div>
      <?php if ($vShowPrice || $vShowValidity): ?>
      <div style="display: flex; justify-content: space-between; margin-top: 3px; font-size: 8px;">
        <?php if ($vShowPrice): ?><span style="background: #ec4899; color: #fff; padding: 0 4px; border-radius: 2px; font-weight: 900;"><?= !empty($price) ? $price : $profile; ?></span><?php else: ?><span></span><?php endif; ?>
        <?php if ($vShowValidity): ?><span style="color: #06b6d4; font-weight: 700;"><?= $validity ?: $timelimit; ?></span><?php endif; ?>
      </div>
      <?php endif; ?>
      <?php if ($vShowDns): ?>
      <div style="font-size: 7.5px; color: #94a3b8; text-align: center; margin-top: 2px;">
        <?= $dnsname; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>

<?php elseif ($vStyle === 'vintage-kraft'): ?>
  <!-- VINTAGE KRAFT SMALL -->
  <style>
  .v-sm-kraft {
    background: #fbf7ee;
    border: 1px dashed #78350f;
    border-radius: 6px;
    color: #451a03;
  }
  .v-sm-kraft-hdr {
    background: #78350f;
    color: #fef3c7;
    padding: 3px 5px;
    font-size: 9.5px;
    font-weight: 900;
    display: flex;
    justify-content: space-between;
    align-items: center;
    min-height: 16px;
  }
  </style>
  <div class="v-card-sm-base v-sm-kraft">
    <div class="v-sm-kraft-hdr">
      <span style="display:flex;align-items:center;gap:3px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:115px;">
        <?php if ($vShowLogo && !empty($logo)): ?><img src="<?= $logo; ?>" style="height:12px;max-width:24px;object-fit:contain;"><?php endif; ?>
        <?php if ($vShowHsName): ?>☕ <?= htmlspecialchars($hotspotname); ?><?php endif; ?>
      </span>
      <?php if ($vShowNum): ?><span>#<?= $num; ?></span><?php endif; ?>
    </div>
    <div style="padding: 4px;">
      <div style="background: #ffffff; border: 1px dotted #78350f; border-radius: 4px; padding: 3px; text-align: center;">
        <?php if ($usermode == "vc"): ?>
          <div style="font-size: 6.5px; color: #78350f; font-weight: 800;">KODE INTERNET</div>
          <div style="font-family: monospace; font-size: 13px; font-weight: 900; color: #451a03;"><?= $username; ?></div>
        <?php else: ?>
          <div style="font-family: monospace; font-size: 10px; color: #451a03; font-weight: 800;">U: <?= $username; ?> | P: <?= $password; ?></div>
        <?php endif; ?>
      </div>
      <?php if ($vShowPrice || $vShowValidity): ?>
      <div style="display: flex; justify-content: space-between; margin-top: 3px; font-size: 8px;">
        <?php if ($vShowPrice): ?><span style="background: #78350f; color: #fef3c7; padding: 0 4px; border-radius: 2px; font-weight: 900;"><?= !empty($price) ? $price : $profile; ?></span><?php else: ?><span></span><?php endif; ?>
        <?php if ($vShowValidity): ?><span style="color: #78350f; font-weight: 700;"><?= $validity ?: $timelimit; ?></span><?php endif; ?>
      </div>
      <?php endif; ?>
      <?php if ($vShowDns): ?>
      <div style="font-size: 7.5px; color: #78350f; text-align: center; margin-top: 2px;">
        <?= $dnsname; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>

<?php elseif ($vStyle === 'glass-gradient'): ?>
  <!-- GLASS GRADIENT SMALL -->
  <style>
  .v-sm-glass {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    overflow: hidden;
  }
  .v-sm-glass-bar {
    height: 3px;
    background: linear-gradient(90deg, <?= $c['accent']; ?>, #a855f7, #ec4899);
  }
  .v-sm-glass-hdr {
    padding: 3px 5px;
    font-size: 9.5px;
    font-weight: 800;
    display: flex;
    justify-content: space-between;
    align-items: center;
    min-height: 16px;
    color: #0f172a;
  }
  </style>
  <div class="v-card-sm-base v-sm-glass">
    <div class="v-sm-glass-bar"></div>
    <div class="v-sm-glass-hdr">
      <span style="display:flex;align-items:center;gap:3px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:115px;">
        <?php if ($vShowLogo && !empty($logo)): ?><img src="<?= $logo; ?>" style="height:12px;max-width:24px;object-fit:contain;"><?php endif; ?>
        <?php if ($vShowHsName): ?><?= htmlspecialchars($hotspotname); ?><?php endif; ?>
      </span>
      <?php if ($vShowNum): ?><span>#<?= $num; ?></span><?php endif; ?>
    </div>
    <div style="padding: 4px;">
      <div style="background: linear-gradient(135deg, <?= $c['box_bg']; ?> 0%, #ffffff 100%); border: 1px solid <?= $c['border']; ?>; border-radius: 4px; padding: 3px; text-align: center;">
        <?php if ($usermode == "vc"): ?>
          <div style="font-size: 6.5px; font-weight: 800; color: <?= $c['accent']; ?>;"><?= $lblCode; ?></div>
          <div style="font-family: monospace; font-size: 13px; font-weight: 900;"><?= $username; ?></div>
        <?php else: ?>
          <div style="font-family: monospace; font-size: 10px; font-weight: 800;">U: <?= $username; ?> | P: <?= $password; ?></div>
        <?php endif; ?>
      </div>
      <?php if ($vShowPrice || $vShowValidity): ?>
      <div style="display: flex; justify-content: space-between; margin-top: 3px; font-size: 8px;">
        <?php if ($vShowPrice): ?><span style="font-weight: 900; color: <?= $c['accent']; ?>;"><?= !empty($price) ? $price : $profile; ?></span><?php else: ?><span></span><?php endif; ?>
        <?php if ($vShowValidity): ?><span style="color: #64748b; font-weight: 700;"><?= $validity ?: $timelimit; ?></span><?php endif; ?>
      </div>
      <?php endif; ?>
      <?php if ($vShowDns): ?>
      <div style="font-size: 7.5px; color: #64748b; text-align: center; margin-top: 2px;">
        <?= $dnsname; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>

<?php elseif ($vStyle === 'split-coupon'): ?>
  <!-- SPLIT COUPON SMALL -->
  <style>
  .v-sm-split {
    background: #ffffff;
    border: 1px solid <?= $c['border']; ?>;
    border-radius: 6px;
    overflow: hidden;
    display: table;
    width: 160px;
  }
  .v-sm-split-l {
    display: table-cell;
    width: 45px;
    background: <?= $c['accent']; ?>;
    color: #fff;
    vertical-align: middle;
    text-align: center;
    padding: 4px 2px;
    border-right: 1px dashed rgba(255,255,255,0.7);
  }
  .v-sm-split-r {
    display: table-cell;
    vertical-align: middle;
    padding: 4px;
    background: #fff;
  }
  </style>
  <div class="v-card-sm-base v-sm-split">
    <div class="v-sm-split-l">
      <?php if ($vShowPrice): ?><div style="font-size: 9px; font-weight: 900;"><?= !empty($price) ? $price : $profile; ?></div><?php endif; ?>
      <?php if ($vShowValidity): ?><div style="font-size: 7px; font-weight: 700; margin-top: 2px;"><?= $validity ?: $timelimit; ?></div><?php endif; ?>
    </div>
    <div class="v-sm-split-r">
      <div style="display:flex;justify-content:space-between;align-items:center;font-size:8px;font-weight:800;border-bottom:1px dotted #cbd5e1;padding-bottom:1px;">
        <span><?php if ($vShowHsName): ?><?= htmlspecialchars($hotspotname); ?><?php endif; ?></span>
        <?php if ($vShowNum): ?><span style="color:#64748b;">#<?= $num; ?></span><?php endif; ?>
      </div>
      <div style="background: <?= $c['box_bg']; ?>; border: 1px solid <?= $c['border']; ?>; border-radius: 3px; padding: 2px; text-align: center; margin-top: 2px;">
        <?php if ($usermode == "vc"): ?>
          <div style="font-family: monospace; font-size: 11px; font-weight: 900;"><?= $username; ?></div>
        <?php else: ?>
          <div style="font-family: monospace; font-size: 8.5px; font-weight: 800;">U: <?= $username; ?> | P: <?= $password; ?></div>
        <?php endif; ?>
      </div>
      <?php if ($vShowDns): ?>
      <div style="font-size: 6.5px; color: #64748b; text-align: center; margin-top: 1px;">
        <?= $dnsname; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>

<?php else: ?>
  <!-- MODERN CARD SMALL (DEFAULT) -->
  <style>
  .v-sm-modern {
    background: #ffffff;
    border: 1px solid <?= $c['border']; ?>;
    border-radius: 6px;
    overflow: hidden;
  }
  .v-sm-modern-hdr {
    background: <?= $c['hdr_start']; ?>;
    color: #fff;
    padding: 3px 5px;
    font-size: 9.5px;
    font-weight: 800;
    display: flex;
    justify-content: space-between;
    align-items: center;
    min-height: 16px;
  }
  </style>
  <div class="v-card-sm-base v-sm-modern">
    <div class="v-sm-modern-hdr">
      <span style="display:flex;align-items:center;gap:3px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:115px;">
        <?php if ($vShowLogo && !empty($logo)): ?><img src="<?= $logo; ?>" style="height:12px;max-width:24px;object-fit:contain;"><?php endif; ?>
        <?php if ($vShowHsName): ?><?= htmlspecialchars($hotspotname); ?><?php endif; ?>
      </span>
      <?php if ($vShowNum): ?><span>#<?= $num; ?></span><?php endif; ?>
    </div>
    <div style="padding: 4px;">
      <div style="background: <?= $c['box_bg']; ?>; border: 1px dashed <?= $c['border']; ?>; border-radius: 4px; padding: 3px; text-align: center;">
        <?php if ($usermode == "vc"): ?>
          <div style="font-size: 6.5px; font-weight: 800; color: <?= $c['accent']; ?>;"><?= $lblCode; ?></div>
          <div style="font-family: monospace; font-size: 13px; font-weight: 900;"><?= $username; ?></div>
        <?php else: ?>
          <div style="font-family: monospace; font-size: 10px; font-weight: 800;">U: <?= $username; ?> | P: <?= $password; ?></div>
        <?php endif; ?>
      </div>
      <?php if ($vShowPrice || $vShowValidity): ?>
      <div style="display: flex; justify-content: space-between; margin-top: 3px; font-size: 8px;">
        <?php if ($vShowPrice): ?><span style="font-weight: 900; color: <?= $c['accent']; ?>;"><?= !empty($price) ? $price : $profile; ?></span><?php else: ?><span></span><?php endif; ?>
        <?php if ($vShowValidity): ?><span style="color: #475569; font-weight: 700;"><?= $validity ?: $timelimit; ?></span><?php endif; ?>
      </div>
      <?php endif; ?>
      <?php if ($vShowDns): ?>
      <div style="font-size: 7.5px; color: #64748b; text-align: center; margin-top: 2px; border-top: 1px dotted #e2e8f0; padding-top: 2px;">
        <?= $dnsname; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>
<?php endif; ?>
