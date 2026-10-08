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
 *  Copyright (C) 2018 Laksamadi Guko.
 *  Modified for NODERA Platform - Multi-Tenant Auto Logo Sync & Visibility Control
 */

// hide all error
error_reporting(0);
if (!isset($_SESSION["mikhmon"])) {
  header("Location:../admin.php?id=login");
  exit;
} else {

  // localization via lang dictionary
  $isFr   = in_array($currency ?? '', ['FCFA', 'XOF', 'CFA', 'GNF', 'CDF']);
  
  if (empty($session)) {
    $session = $_GET['session'] ?? ($_POST['session'] ?? ($_SESSION['session'] ?? ($_SESSION['mikhmon'] ?? '')));
  }
  $session = trim((string)$session);

  $logo_dir = "./img/";
  if (!is_dir($logo_dir)) {
    @mkdir($logo_dir, 0755, true);
  }

  $logo_config_file = "./include/logo_config.php";
  $logo_config_data = array();
  if (file_exists($logo_config_file)) {
    @include($logo_config_file);
  }
  if (!is_array($logo_config_data)) {
    $logo_config_data = array();
  }

  $useInWeb = !isset($logo_config_data[$session]['use_in_web']) || $logo_config_data[$session]['use_in_web'] !== 'no';
  $useInVoucher = !isset($logo_config_data[$session]['use_in_voucher']) || $logo_config_data[$session]['use_in_voucher'] !== 'no';

  $galat = '';

  // Handle saving checkbox preferences
  if (isset($_POST["save_prefs"])) {
    $useInWeb = isset($_POST["use_in_web"]);
    $useInVoucher = isset($_POST["use_in_voucher"]);

    $logo_config_data[$session] = [
      'use_in_web' => $useInWeb ? 'yes' : 'no',
      'use_in_voucher' => $useInVoucher ? 'yes' : 'no',
      'updated_at' => date('Y-m-d H:i:s'),
    ];

    $gen = '<' . '?php' . "\n" .
           'if(substr($_SERVER["REQUEST_URI"], -15) == "logo_config.php"){header("Location:./");};' . "\n" .
           '$logo_config_data = ' . var_export($logo_config_data, true) . ";\n";
    @file_put_contents($logo_config_file, $gen);

    $msg = $isIndo 
      ? 'Pengaturan penggunaan logo untuk router <b>' . htmlspecialchars($session) . '</b> berhasil disimpan!'
      : ($isFr 
        ? "Les préférences d'utilisation du logo pour <b>" . htmlspecialchars($session) . "</b> ont été enregistrées !"
        : 'Logo usage settings for session <b>' . htmlspecialchars($session) . '</b> saved successfully!');
    $galat = '<div class="box bg-success"><i class="fa fa-check"></i> ' . $msg . '</div>';
  }

  // Handle logo deletion
  if (isset($_GET['action']) && $_GET['action'] === 'delete') {
    $logoFiles = [
      $logo_dir . "logo-" . $session . ".png",
      $logo_dir . "logo-" . $session . ".jpg",
      $logo_dir . "logo-" . $session . ".jpeg",
      $logo_dir . "logo-" . $session . ".webp",
      $logo_dir . "logo-" . strtolower($session) . ".png",
      $logo_dir . "logo-" . strtolower($session) . ".jpg",
      $logo_dir . "logo-" . strtoupper($session) . ".png",
    ];
    $deleted = false;
    foreach ($logoFiles as $lf) {
      if (file_exists($lf) && is_file($lf)) {
        @unlink($lf);
        $deleted = true;
      }
    }
    if ($deleted) {
      $galat = '<div class="box bg-success"><i class="fa fa-check"></i> ' . ($_uplogo_success_del_session ?? 'Logo berhasil dihapus.') . '</div>';
    }
  }

  // Handle single file delete from table
  if (isset($_GET['action']) && $_GET['action'] === 'delete_file' && !empty($_GET['file'])) {
    $delFile = basename($_GET['file']);
    $delPath = $logo_dir . $delFile;
    if (file_exists($delPath) && is_file($delPath)) {
      @unlink($delPath);
      $galat = '<div class="box bg-success"><i class="fa fa-check"></i> ' . ($_uplogo_success_del_file ?? 'File logo berhasil dihapus.') . '</div>';
    }
  }

  // Handle upload
  if (isset($_POST["submit"])) {
    $useInWeb = isset($_POST["use_in_web"]);
    $useInVoucher = isset($_POST["use_in_voucher"]);

    $logo_config_data[$session] = [
      'use_in_web' => $useInWeb ? 'yes' : 'no',
      'use_in_voucher' => $useInVoucher ? 'yes' : 'no',
      'updated_at' => date('Y-m-d H:i:s'),
    ];

    $gen = '<' . '?php' . "\n" .
           'if(substr($_SERVER["REQUEST_URI"], -15) == "logo_config.php"){header("Location:./");};' . "\n" .
           '$logo_config_data = ' . var_export($logo_config_data, true) . ";\n";
    @file_put_contents($logo_config_file, $gen);

    $uploadLogo = $_FILES["UploadLogo"] ?? null;
    $fileName = '';
    $fileTmp = '';
    $fileSize = 0;
    $fileError = 0;

    if ($uploadLogo instanceof \Symfony\Component\HttpFoundation\File\UploadedFile) {
      $fileName = $uploadLogo->getClientOriginalName();
      $fileTmp = $uploadLogo->getPathname();
      $fileSize = $uploadLogo->getSize();
      $fileError = $uploadLogo->getError();
    } elseif (is_array($uploadLogo)) {
      $fileName = $uploadLogo["name"] ?? '';
      $fileTmp = $uploadLogo["tmp_name"] ?? '';
      $fileSize = $uploadLogo["size"] ?? 0;
      $fileError = $uploadLogo["error"] ?? 0;
    }

    if (empty($fileName) || empty($fileTmp) || !file_exists($fileTmp)) {
      $galat = '<div class="box bg-danger"><i class="fa fa-warning"></i> ' . ($_uplogo_err_choose_file ?? 'Silakan pilih file logo terlebih dahulu.') . '</div>';
    } elseif ($fileSize > 2097152) { // 2MB
      $galat = '<div class="box bg-danger"><i class="fa fa-warning"></i> ' . ($_uplogo_err_too_large ?? 'Ukuran file terlalu besar! Maksimum 2 MB.') . '</div>';
    } else {
      $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
      $allowed = ['png', 'jpg', 'jpeg', 'webp', 'gif'];
      
      if (!in_array($ext, $allowed)) {
        $galat = '<div class="box bg-danger"><i class="fa fa-warning"></i> ' . ($_uplogo_err_invalid_format ?? 'Format file tidak didukung! Gunakan format PNG, JPG, atau WEBP.') . '</div>';
      } else {
        $targetFile = $logo_dir . "logo-" . $session . ".png";

        // Remove old variants
        @unlink($logo_dir . "logo-" . $session . ".png");
        @unlink($logo_dir . "logo-" . $session . ".jpg");
        @unlink($logo_dir . "logo-" . $session . ".jpeg");
        @unlink($logo_dir . "logo-" . $session . ".webp");

        $moved = false;
        if (function_exists('move_uploaded_file') && is_uploaded_file($fileTmp)) {
          $moved = @move_uploaded_file($fileTmp, $targetFile);
        }
        if (!$moved) {
          $moved = @copy($fileTmp, $targetFile);
        }

        if ($moved) {
          // Auto duplicate to generic logo.png if not present
          if (!file_exists($logo_dir . "logo.png")) {
            @copy($targetFile, $logo_dir . "logo.png");
          }
          $galat = '<div class="box bg-success"><i class="fa fa-check"></i> ' . ($_uplogo_success_upload ?? 'Logo berhasil diunggah!') . '</div>';
        } else {
          $galat = '<div class="box bg-danger"><i class="fa fa-warning"></i> ' . ($_uplogo_err_save ?? 'Gagal menyimpan file logo ke folder img/.') . '</div>';
        }
      }
    }
  }

  // Discover active logo
  $activeLogoUrl = null;
  $activeLogoMeta = null;
  $logoCandidates = [
    $logo_dir . "logo-" . $session . ".png",
    $logo_dir . "logo-" . $session . ".jpg",
    $logo_dir . "logo-" . $session . ".jpeg",
    $logo_dir . "logo-" . $session . ".webp",
    $logo_dir . "logo-" . strtolower($session) . ".png",
    $logo_dir . "logo-" . strtolower($session) . ".jpg",
    $logo_dir . "logo-" . strtoupper($session) . ".png",
    $logo_dir . "logo.png",
    $logo_dir . "logo.jpg",
  ];

  foreach ($logoCandidates as $cand) {
    if (file_exists($cand) && filesize($cand) > 0) {
      $sz = @getimagesize($cand);
      $activeLogoUrl = $cand . '?t=' . filemtime($cand);
      $activeLogoMeta = [
        'name' => basename($cand),
        'size' => round(filesize($cand) / 1024, 1) . ' KB',
        'width' => $sz[0] ?? '-',
        'height' => $sz[1] ?? '-',
      ];
      break;
    }
  }

  // Scan all logo files in img/
  $allLogos = [];
  if (is_dir($logo_dir)) {
    $files = @scandir($logo_dir) ?: [];
    foreach ($files as $f) {
      if ($f === '.' || $f === '..' || $f === 'index.php') continue;
      $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
      if (in_array($ext, ['png', 'jpg', 'jpeg', 'webp', 'gif'])) {
        $p = $logo_dir . $f;
        $sz = @getimagesize($p);
        $allLogos[] = [
          'name' => $f,
          'path' => $p,
          'size' => round(filesize($p) / 1024, 1) . ' KB',
          'width' => $sz[0] ?? '-',
          'height' => $sz[1] ?? '-',
          'is_session' => (strpos($f, 'logo-' . $session) === 0 || strpos($f, 'logo-' . strtolower($session)) === 0),
        ];
      }
    }
  }
}
?>

<div class="row">
  <div class="col-12">
    <?= $galat; ?>
  </div>

  <!-- KOLOM KIRI: FORM UNGGAH LOGO (NATIVE MIKHMON TABLE FORM) -->
  <div class="col-6">
    <div class="card box-bordered">
      <div class="card-header">
        <h3><i class="fa fa-upload"></i> <?= $_upload_logo; ?> (<?= htmlspecialchars($session); ?>)</h3>
      </div>
      <div class="card-body">
        <form action="" method="post" enctype="multipart/form-data">
          <input type="hidden" name="session" value="<?= htmlspecialchars($session); ?>">
          <table class="table">
            <tr>
              <td class="align-middle"><?= $_logo; ?></td>
              <td>
                <input class="form-control" type="file" name="UploadLogo" accept="image/png, image/jpeg, image/jpg, image/webp" required>
              </td>
            </tr>
            <tr>
              <td class="align-middle"><?= $_uplogo_use_for ?? 'Gunakan Pada'; ?></td>
              <td>
                <div style="margin-bottom: 6px;">
                  <label style="font-weight: normal; cursor: pointer;">
                    <input type="checkbox" name="use_in_web" value="1" <?= $useInWeb ? 'checked' : ''; ?>>
                    <?= $_uplogo_web_store ?? 'Toko Web / Beli Voucher (buy.php)'; ?>
                  </label>
                </div>
                <div>
                  <label style="font-weight: normal; cursor: pointer;">
                    <input type="checkbox" name="use_in_voucher" value="1" <?= $useInVoucher ? 'checked' : ''; ?>>
                    <?= $_uplogo_print_voucher ?? 'Cetak Voucher (Print / Thermal)'; ?>
                  </label>
                </div>
              </td>
            </tr>
            <tr>
              <td></td>
              <td>
                <button class="btn bg-primary" type="submit" name="submit">
                  <i class="fa fa-upload"></i> <?= $_upload_logo; ?>
                </button>
              </td>
            </tr>
          </table>
        </form>
      </div>
    </div>
  </div>

  <!-- KOLOM KANAN: PENGATURAN VISIBILITAS & PREVIEW LOGO (NATIVE MIKHMON) -->
  <div class="col-6">
    <div class="card box-bordered">
      <div class="card-header">
        <h3><i class="fa fa-sliders"></i> <?= $_uplogo_visibility_settings ?? 'Pengaturan Visibilitas Logo'; ?></h3>
      </div>
      <div class="card-body">
        <form action="" method="post">
          <input type="hidden" name="session" value="<?= htmlspecialchars($session); ?>">
          <table class="table">
            <tr>
              <td class="align-middle"><?= $_uplogo_web_store ?? 'Toko Web (buy.php)'; ?></td>
              <td>
                <label style="font-weight: normal; cursor: pointer;">
                  <input type="checkbox" name="use_in_web" value="1" <?= $useInWeb ? 'checked' : ''; ?>>
                  <?= $_uplogo_show_web ?? 'Tampilkan Logo di Web & Modal QRIS'; ?>
                </label>
              </td>
            </tr>
            <tr>
              <td class="align-middle"><?= $_uplogo_print_voucher ?? 'Cetak Voucher'; ?></td>
              <td>
                <label style="font-weight: normal; cursor: pointer;">
                  <input type="checkbox" name="use_in_voucher" value="1" <?= $useInVoucher ? 'checked' : ''; ?>>
                  <?= $_uplogo_show_print ?? 'Tampilkan Logo pada Cetak Voucher'; ?>
                </label>
              </td>
            </tr>
            <tr>
              <td></td>
              <td>
                <button class="btn bg-primary" type="submit" name="save_prefs">
                  <i class="fa fa-save"></i> <?= $_save; ?>
                </button>
              </td>
            </tr>
          </table>
        </form>

        <hr style="margin: 15px 0; border: 0; border-top: 1px solid #e2e8f0;">

        <!-- Preview Logo Aktif -->
        <div class="text-center">
          <h4><?= $_uplogo_current_logo ?? 'Logo Saat Ini'; ?></h4>
          <?php if (!empty($activeLogoUrl)): ?>
            <div style="margin: 12px 0; padding: 10px; background: #fafafa; border: 1px dashed #ccc; border-radius: 4px; display: inline-block;">
              <img src="<?= htmlspecialchars($activeLogoUrl); ?>" alt="Logo" style="max-height: 80px; max-width: 220px; object-fit: contain; display: block; margin: 0 auto;">
            </div>
            <div style="font-size: 11px; color: #666; margin-bottom: 10px;">
              <?= htmlspecialchars($activeLogoMeta['name'] ?? 'logo.png'); ?> (<?= $activeLogoMeta['width'] ?? '-'; ?>&times;<?= $activeLogoMeta['height'] ?? '-'; ?> px, <?= $activeLogoMeta['size'] ?? ''; ?>)
            </div>
            <div>
              <a class="btn bg-danger" href="./admin.php?id=uplogo&session=<?= urlencode($session); ?>&action=delete" onclick="return confirm('<?= $_uplogo_delete_confirm ?? 'Yakin ingin menghapus logo untuk sesi ini?'; ?>')">
                <i class="fa fa-trash"></i> <?= $_delete; ?>
              </a>
            </div>
          <?php else: ?>
            <p class="text-muted" style="margin: 15px 0;">
              <i><?= $_uplogo_no_logo_uploaded ?? 'Belum ada file logo terunggah.'; ?></i>
            </p>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- DAFTAR SEMUA FILE LOGO DI FOLDER IMG/ -->
  <?php if (!empty($allLogos)): ?>
  <div class="col-12" style="margin-top: 15px;">
    <div class="card box-bordered">
      <div class="card-header">
        <h3><i class="fa fa-folder-open"></i> <?= $_uplogo_file_list ?? 'Daftar File Logo di img/'; ?></h3>
      </div>
      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-bordered table-hover">
            <thead>
              <tr>
                <th style="width: 50px;" class="text-center">#</th>
                <th style="width: 90px;" class="text-center"><?= $_uplogo_preview ?? 'Preview'; ?></th>
                <th><?= $_uplogo_file_name ?? 'Nama File'; ?></th>
                <th style="width: 120px;"><?= $_uplogo_dimensions ?? 'Dimensi'; ?></th>
                <th style="width: 100px;"><?= $_uplogo_file_size ?? 'Ukuran'; ?></th>
                <th style="width: 110px;" class="text-center"><?= $_action; ?></th>
              </tr>
            </thead>
            <tbody>
              <?php $no = 1; foreach ($allLogos as $lg): ?>
                <tr <?= $lg['is_session'] ? 'style="background: rgba(0,139,201,0.06); font-weight: bold;"' : ''; ?>>
                  <td class="text-center align-middle"><?= $no++; ?></td>
                  <td class="text-center align-middle">
                    <img src="<?= htmlspecialchars($lg['path']); ?>?t=<?= time(); ?>" style="max-height: 35px; max-width: 70px; object-fit: contain;">
                  </td>
                  <td class="align-middle">
                    <?= htmlspecialchars($lg['name']); ?>
                    <?php if ($lg['is_session']): ?>
                      <span class="badge bg-primary" style="font-size: 10px; margin-left: 5px;"><?= $_session_active ?? 'Sesi Aktif'; ?></span>
                    <?php endif; ?>
                  </td>
                  <td class="align-middle"><?= $lg['width']; ?> &times; <?= $lg['height']; ?> px</td>
                  <td class="align-middle"><?= $lg['size']; ?></td>
                  <td class="text-center align-middle">
                    <a class="btn btn-sm bg-danger" href="./admin.php?id=uplogo&session=<?= urlencode($session); ?>&action=delete_file&file=<?= urlencode($lg['name']); ?>" onclick="return confirm('<?= $isIndo ? 'Hapus file ' : 'Supprimer '; ?><?= htmlspecialchars($lg['name']); ?>?')">
                      <i class="fa fa-trash"></i> <?= $_delete; ?>
                    </a>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
  <?php endif; ?>
</div>
