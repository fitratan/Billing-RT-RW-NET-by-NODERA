<?php
$pageTitle = 'Setelan';
$activeTab = 'settings';
$pageFab   = []; // No FAB on settings
require_once __DIR__ . '/../include/header.php';
?>

<?php if (isset($_GET['msg']) && $_GET['msg']==='pass_success'): ?>
<div class="flash success">
    <i class="bi bi-check-circle-fill" style="flex-shrink:0;"></i>
    <span style="flex:1;">Password berhasil diubah!</span>
    <button class="flash-close" style="background:none;border:none;color:inherit;cursor:pointer;font-size:16px;">&times;</button>
</div>
<?php endif; ?>

<?php if (isset($_GET['msg']) && $_GET['msg']==='restore_success'): ?>
<div class="flash success">
    <i class="bi bi-check-circle-fill" style="flex-shrink:0;"></i>
    <span style="flex:1;">Data pembukuan berhasil dipulihkan (restore)!</span>
    <button class="flash-close" style="background:none;border:none;color:inherit;cursor:pointer;font-size:16px;">&times;</button>
</div>
<?php endif; ?>

<?php if (isset($_GET['msg']) && $_GET['msg']==='restore_error'): ?>
<div class="flash error">
    <i class="bi bi-exclamation-triangle-fill" style="flex-shrink:0;"></i>
    <span style="flex:1;">Gagal memulihkan data. Berkas backup tidak valid.</span>
    <button class="flash-close" style="background:none;border:none;color:inherit;cursor:pointer;font-size:16px;">&times;</button>
</div>
<?php endif; ?>

<!-- PWA Application Card -->
<div class="card" style="margin-bottom:16px;background:linear-gradient(135deg, color-mix(in srgb, var(--primary) 12%, var(--card)) 0%, var(--card) 100%);">
    <div style="padding:20px;display:flex;align-items:center;justify-content:space-between;gap:12px;">
        <div>
            <h3 style="margin:0;font-size:14px;font-weight:700;color:var(--fg);"><i class="bi bi-phone" style="color:var(--primary);"></i> Aplikasi Pembukuan PWA</h3>
            <p style="margin:4px 0 0;font-size:12px;color:var(--muted-fg);">Instal ke layar utama HP untuk penggunaan harian cepat & offline.</p>
        </div>
        <button type="button" onclick="installPWA()" class="btn btn-primary btn-sm" id="pwaInstallBtn" style="flex-shrink:0;">
            <i class="bi bi-download"></i> Instal PWA
        </button>
    </div>
</div>

<!-- Backup & Restore Data Card -->
<div class="card" id="backupCard" style="margin-bottom:16px;">
    <div style="padding:20px;">
        <div class="section-title" style="margin-bottom:6px;"><i class="bi bi-database-down" style="color:var(--primary);"></i> Backup & Restore Data</div>
        <p style="font-size:12px;color:var(--muted-fg);margin:0 0 16px;">Unduh cadangan data pembukuan atau pulihkan dari berkas backup sebelumnya.</p>
        
        <div style="display:flex;flex-direction:column;gap:12px;">
            <a href="index.php?action=export_backup" class="btn btn-outline w-full" style="justify-content:center;color:var(--primary);border-color:color-mix(in srgb,var(--primary) 30%,transparent);">
                <i class="bi bi-download"></i> Unduh Cadangan Data (JSON Backup)
            </a>
            
            <form method="POST" action="index.php" enctype="multipart/form-data" style="border-top:1px dashed var(--border);padding-top:14px;margin-top:2px;">
                <input type="hidden" name="action" value="restore_backup">
                <label style="display:block;font-size:12px;font-weight:600;color:var(--muted-fg);margin-bottom:6px;">Pulihkan Data dari Berkas Backup *</label>
                <div style="display:flex;flex-direction:column;gap:8px;width:100%;">
                    <input type="file" name="backup_file" accept=".json" required style="width:100%;max-width:100%;box-sizing:border-box;padding:8px 12px;border-radius:10px;border:1px solid var(--border);background:var(--muted);color:var(--fg);font-size:12px;">
                    <button type="submit" onclick="return confirm('Pulihkan data dari backup? Data saat ini akan diperbarui dengan data dari file backup.')" class="btn btn-outline btn-sm w-full" style="color:#22c55e;border-color:rgba(34,197,94,.3);justify-content:center;">
                        <i class="bi bi-upload"></i> Restore Data
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="card" style="margin-bottom:16px;">
    <div style="padding:20px;">
        <div class="section-title" style="margin-bottom:16px;">Ganti Password Admin</div>
        <form method="POST" action="index.php" style="display:flex;flex-direction:column;gap:12px;">
            <input type="hidden" name="action" value="change_password">
            <div class="field">
                <label>Password Baru *</label>
                <input type="password" name="new_password" placeholder="Minimal 4 karakter" required minlength="4">
            </div>
            <button type="submit" class="btn btn-primary w-full">
                <i class="bi bi-shield-lock"></i> Simpan Password Baru
            </button>
        </form>
    </div>
</div>

<!-- Instance info -->
<div class="card" style="margin-bottom:16px;">
    <div style="padding:20px;">
        <div class="section-title" style="margin-bottom:16px;">Informasi Instansi</div>
        <div style="font-size:13px;">
            <div class="divide-row" style="display:flex;align-items:center;justify-content:space-between;padding:10px 0;gap:8px;">
                <span style="color:var(--muted-fg);flex-shrink:0;">Nama Usaha</span>
                <span style="font-weight:600;color:var(--fg);text-align:right;word-break:break-word;min-width:0;"><?= htmlspecialchars($businessName) ?></span>
            </div>
            <div class="divide-row" style="display:flex;align-items:center;justify-content:space-between;padding:10px 0;gap:8px;">
                <span style="color:var(--muted-fg);flex-shrink:0;">Subdomain</span>
                <span style="font-weight:600;color:var(--primary);text-align:right;word-break:break-all;min-width:0;"><?= htmlspecialchars($subdomain) ?>.dgtlnetsolution.com</span>
            </div>
            <div class="divide-row" style="display:flex;align-items:center;justify-content:space-between;padding:10px 0;gap:8px;">
                <span style="color:var(--muted-fg);flex-shrink:0;">Status</span>
                <span style="background:rgba(34,197,94,.12);color:#22c55e;font-size:11px;font-weight:700;padding:3px 10px;border-radius:999px;">AKTIF</span>
            </div>
            <?php if (defined('BOOKKEEPING_EXPIRY') && BOOKKEEPING_EXPIRY): ?>
            <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 0;">
                <span style="color:var(--muted-fg);">Berakhir</span>
                <span style="font-weight:600;color:var(--fg);"><?= htmlspecialchars(BOOKKEEPING_EXPIRY) ?></span>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Danger zone -->
<div class="card" style="border-color:rgba(239,68,68,.2);">
    <div style="padding:20px;">
        <div class="section-title" style="color:#ef4444;margin-bottom:4px;">Zona Bahaya</div>
        <p style="font-size:12px;color:var(--muted-fg);margin:0 0 14px;">Keluar dari sesi aktif aplikasi ini.</p>
        <a href="logout.php" onclick="return confirm('Yakin keluar?')" class="btn btn-outline w-full" style="color:#ef4444;border-color:rgba(239,68,68,.3);justify-content:center;">
            <i class="bi bi-box-arrow-right"></i> Logout
        </a>
    </div>
</div>

<?php require_once __DIR__ . '/../include/footer.php'; ?>
