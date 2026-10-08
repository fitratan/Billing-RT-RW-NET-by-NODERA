<?php
$pageTitle = 'Log Aktivitas';
$activeTab = 'activitylog';
$pageFab   = []; // No FAB on this page
require_once __DIR__ . '/../include/header.php';

$data = getBkData();
$logs = $data['activity_log'] ?? [];
krsort($logs);
?>

<div class="card">
    <div style="padding:20px;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
            <div style="display:flex;align-items:center;gap:10px;">
                <div style="width:32px;height:32px;border-radius:8px;background:rgba(139,92,246,.1);color:#a78bfa;display:flex;align-items:center;justify-content:center;">
                    <i class="bi bi-clock-history"></i>
                </div>
                <div class="section-title">Riwayat Aktivitas</div>
            </div>
            <?php if (!empty($logs)): ?>
                <a href="index.php?clear_log=1" onclick="return confirm('Hapus semua log aktivitas?')" class="btn btn-ghost btn-sm no-print" style="color:#ef4444;">
                    <i class="bi bi-trash"></i> Hapus Log
                </a>
            <?php endif; ?>
        </div>

        <?php if (empty($logs)): ?>
            <div class="empty-state">
                <i class="bi bi-clock-history"></i>
                <p>Belum ada aktivitas tercatat.</p>
            </div>
        <?php else: ?>
            <?php foreach ($logs as $ts => $entries):
                if (!is_array($entries)) $entries = [$entries];
                foreach ($entries as $entry):
                    $icon  = strpos($entry, 'tambah')!==false || strpos($entry, 'Tambah')!==false ? 'bi-plus-circle-fill' : (strpos($entry, 'hapus')!==false || strpos($entry, 'Hapus')!==false ? 'bi-trash-fill' : 'bi-pencil-fill');
                    $color = strpos($entry, 'hapus')!==false || strpos($entry, 'Hapus')!==false ? '#ef4444' : (strpos($entry, 'tambah')!==false || strpos($entry, 'Tambah')!==false ? '#22c55e' : 'var(--primary)');
            ?>
                <div class="divide-row" style="display:flex;align-items:flex-start;gap:12px;padding:12px 0;">
                    <div style="width:32px;height:32px;border-radius:8px;flex-shrink:0;display:flex;align-items:center;justify-content:center;background:color-mix(in srgb,<?= $color ?> 12%,transparent);color:<?= $color ?>;">
                        <i class="bi <?= $icon ?>" style="font-size:14px;"></i>
                    </div>
                    <div style="flex:1;min-width:0;">
                        <p style="margin:0;font-size:13px;font-weight:500;color:var(--fg);"><?= htmlspecialchars($entry) ?></p>
                        <p style="margin:2px 0 0;font-size:11px;color:var(--muted-fg);font-family:monospace;"><?= htmlspecialchars($ts) ?></p>
                    </div>
                </div>
            <?php endforeach; endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../include/footer.php'; ?>
