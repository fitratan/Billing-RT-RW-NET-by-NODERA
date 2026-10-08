<?php
$pageTitle = 'Dashboard';
$activeTab = 'dashboard';
$pageFab = [
    'icon' => 'bi-plus-lg',
    'label' => 'Catat Transaksi',
    'onclick' => 'openCatatModalNew()'
];
require_once __DIR__ . '/../include/header.php';

$data         = getBkData();
$allTx        = $data['transactions'] ?? [];
$totalIncome  = 0; $totalExpense = 0;
foreach ($allTx as $tx) {
    if (($tx['type'] ?? '') === 'income') $totalIncome  += (float)($tx['amount'] ?? 0);
    else                                  $totalExpense += (float)($tx['amount'] ?? 0);
}
$balance    = $totalIncome - $totalExpense;
$recents    = array_slice($allTx, 0, 8);
$categories = $data['categories'] ?? [];

// Daily chart (last 30 days)
$dailyMap = [];
foreach ($allTx as $tx) {
    $d = $tx['transaction_date'] ?? '';
    if (!$d) continue;
    if (!isset($dailyMap[$d])) $dailyMap[$d] = 0;
    $dailyMap[$d] += (float)($tx['amount'] ?? 0);
}
ksort($dailyMap);
$dailyMap = array_slice($dailyMap, -30, null, true);
$maxD = max(1, ...array_values($dailyMap ?: [1]));
?>

<?php if (isset($_GET['msg'])): ?>
<div class="flash success">
    <i class="bi bi-check-circle-fill" style="flex-shrink:0;"></i>
    <span style="flex:1;">Transaksi berhasil disimpan!</span>
    <button class="flash-close" style="background:none;border:none;color:inherit;cursor:pointer;font-size:16px;padding:0;">&times;</button>
</div>
<?php endif; ?>

<!-- ── Hero + stat cards (Finance.tsx grid gap-3 sm:grid-cols-2) ── -->
<div style="display:grid;gap:12px;grid-template-columns:1fr 1fr;margin-bottom:16px;">
    <!-- Net balance hero card — CLICKABLE → go to reports -->
    <a href="index.php?page=reports" style="text-decoration:none;grid-column:1/3;">
        <div class="card-clickable" style="border-radius:12px;padding:20px;color:#fff;background:linear-gradient(135deg,var(--primary) 0%,color-mix(in srgb,var(--primary) 70%,#6366f1) 100%);box-shadow:0 8px 24px color-mix(in srgb,var(--primary) 30%,transparent);">
            <div style="display:flex;align-items:center;justify-content:space-between;">
                <span style="font-size:11px;font-weight:700;letter-spacing:.06em;opacity:.8;text-transform:uppercase;">Saldo Bersih</span>
                <div style="width:40px;height:40px;border-radius:12px;background:rgba(255,255,255,.2);display:flex;align-items:center;justify-content:center;font-size:18px;">
                    <i class="bi bi-wallet2"></i>
                </div>
            </div>
            <div style="margin-top:12px;">
                <p style="margin:0;font-size:clamp(22px,5vw,32px);font-weight:700;letter-spacing:-0.02em;word-break:break-all;">
                    <?= formatRupiah($balance) ?>
                </p>
                <p style="margin:4px 0 0;font-size:11px;opacity:.75;">Tap untuk lihat laporan &rarr;</p>
            </div>
        </div>
    </a>

    <!-- Income card — CLICKABLE → transactions filtered income -->
    <a href="index.php?page=transactions&type=income" style="text-decoration:none;">
        <div class="card card-clickable" style="padding:16px;">
            <div style="width:36px;height:36px;border-radius:10px;background:rgba(34,197,94,.1);color:#22c55e;display:flex;align-items:center;justify-content:center;font-size:16px;">
                <i class="bi bi-graph-up-arrow"></i>
            </div>
            <div style="margin-top:10px;font-size:clamp(13px,3.5vw,18px);font-weight:600;color:#22c55e;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                <?= formatRupiah($totalIncome) ?>
            </div>
            <div style="font-size:11px;color:var(--muted-fg);">Total Pemasukan</div>
        </div>
    </a>

    <!-- Expense card — CLICKABLE → transactions filtered expense -->
    <a href="index.php?page=transactions&type=expense" style="text-decoration:none;">
        <div class="card card-clickable" style="padding:16px;">
            <div style="width:36px;height:36px;border-radius:10px;background:rgba(239,68,68,.1);color:#ef4444;display:flex;align-items:center;justify-content:center;font-size:16px;">
                <i class="bi bi-arrow-down-circle-fill"></i>
            </div>
            <div style="margin-top:10px;font-size:clamp(13px,3.5vw,18px);font-weight:600;color:#ef4444;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                <?= formatRupiah($totalExpense) ?>
            </div>
            <div style="font-size:11px;color:var(--muted-fg);">Total Pengeluaran</div>
        </div>
    </a>
</div>

<!-- ── Quick Access Menu (Small icons + Lihat Semua) ── -->
<div class="card" style="padding:16px;margin-bottom:16px;">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">
        <div class="section-title">Akses Cepat</div>
        <button onclick="openModal('modalAllMenus')" style="background:none;border:none;color:var(--primary);font-size:12px;font-weight:600;cursor:pointer;padding:0;">
            Lihat Semua &rarr;
        </button>
    </div>
    <div style="display:grid;grid-template-columns:repeat(4, 1fr);gap:12px;text-align:center;">
        <a href="index.php?page=transactions" style="text-decoration:none;color:inherit;display:flex;flex-direction:column;align-items:center;gap:6px;">
            <div style="width:44px;height:44px;border-radius:12px;background:rgba(37,99,235,.1);color:var(--primary);display:flex;align-items:center;justify-content:center;font-size:20px;">
                <i class="bi bi-card-list"></i>
            </div>
            <span style="font-size:11px;font-weight:500;">Transaksi</span>
        </a>
        <a href="index.php?page=chat" style="text-decoration:none;color:inherit;display:flex;flex-direction:column;align-items:center;gap:6px;">
            <div style="width:44px;height:44px;border-radius:12px;background:rgba(168,85,247,.1);color:#a855f7;display:flex;align-items:center;justify-content:center;font-size:20px;">
                <i class="bi bi-chat-dots-fill"></i>
            </div>
            <span style="font-size:11px;font-weight:500;">Chat</span>
        </a>
        <a href="index.php?page=categories" style="text-decoration:none;color:inherit;display:flex;flex-direction:column;align-items:center;gap:6px;">
            <div style="width:44px;height:44px;border-radius:12px;background:rgba(234,179,8,.1);color:#eab308;display:flex;align-items:center;justify-content:center;font-size:20px;">
                <i class="bi bi-tags-fill"></i>
            </div>
            <span style="font-size:11px;font-weight:500;">Kategori</span>
        </a>
        <a href="index.php?page=reports" style="text-decoration:none;color:inherit;display:flex;flex-direction:column;align-items:center;gap:6px;">
            <div style="width:44px;height:44px;border-radius:12px;background:rgba(59,130,246,.1);color:#3b82f6;display:flex;align-items:center;justify-content:center;font-size:20px;">
                <i class="bi bi-bar-chart-fill"></i>
            </div>
            <span style="font-size:11px;font-weight:500;">Laporan</span>
        </a>
    </div>
</div>


<!-- ── Recent Transactions — CLICKABLE rows → shows detail modal ── -->
<div class="card" style="padding:20px;margin-bottom:16px;">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">
        <div class="section-title">Transaksi Terbaru</div>
        <a href="index.php?page=transactions" style="font-size:12px;font-weight:600;color:var(--primary);text-decoration:none;">Lihat Semua &rarr;</a>
    </div>

    <?php if (empty($recents)): ?>
        <div class="empty-state">
            <i class="bi bi-inbox"></i>
            <p>Belum ada transaksi. Ketuk FAB (+) untuk mencatat.</p>
        </div>
    <?php else: ?>
        <?php foreach ($recents as $tx): ?>
            <div class="divide-row tx-row" onclick="handleRowClick(this)"
                data-tx="<?= rawurlencode(json_encode($tx)) ?>"
                style="display:flex;align-items:center;gap:12px;padding:12px 0;cursor:pointer;border-radius:8px;margin:0 -4px;padding-left:4px;padding-right:4px;transition:background .15s;"
                onmouseover="this.style.background='var(--muted)'" onmouseout="this.style.background='transparent'">
                <div style="width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;
                    background:<?= $tx['type']==='income'?'rgba(34,197,94,.1)':'rgba(239,68,68,.1)' ?>;
                    color:<?= $tx['type']==='income'?'#22c55e':'#ef4444' ?>;">
                    <i class="bi <?= $tx['type']==='income'?'bi-arrow-down-left':'bi-arrow-up-right' ?>"></i>
                </div>
                <div style="flex:1;min-width:0;">
                    <p style="margin:0;font-size:13px;font-weight:500;color:var(--fg);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= htmlspecialchars($tx['category']) ?></p>
                    <p style="margin:0;font-size:11px;color:var(--muted-fg);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                        <?= htmlspecialchars($tx['transaction_date']) ?><?= $tx['description'] ? ' · ' . htmlspecialchars($tx['description']) : '' ?>
                    </p>
                </div>
                <span style="font-size:13px;font-weight:600;flex-shrink:0;color:<?= $tx['type']==='income'?'#22c55e':'#ef4444' ?>;">
                    <?= $tx['type']==='income'?'+':'-' ?> Rp <?= number_format($tx['amount'],0,',','.') ?>
                </span>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- ── Modal: Semua Menu ── -->
<div id="modalAllMenus" class="modal-backdrop">
    <div class="modal-box">
        <div class="modal-header">
            <span class="modal-title">Semua Menu & Fitur</span>
            <button onclick="closeModal('modalAllMenus')" style="background:none;border:none;color:var(--muted-fg);cursor:pointer;font-size:22px;line-height:1;">&times;</button>
        </div>
        <div style="display:grid;grid-template-columns:repeat(3, 1fr);gap:16px;padding:8px 0;text-align:center;">
            <a href="index.php?page=dashboard" style="text-decoration:none;color:inherit;display:flex;flex-direction:column;align-items:center;gap:8px;">
                <div style="width:48px;height:48px;border-radius:14px;background:rgba(37,99,235,.1);color:var(--primary);display:flex;align-items:center;justify-content:center;font-size:22px;">
                    <i class="bi bi-grid-fill"></i>
                </div>
                <span style="font-size:12px;font-weight:500;">Beranda</span>
            </a>
            <a href="index.php?page=transactions" style="text-decoration:none;color:inherit;display:flex;flex-direction:column;align-items:center;gap:8px;">
                <div style="width:48px;height:48px;border-radius:14px;background:rgba(34,197,94,.1);color:#22c55e;display:flex;align-items:center;justify-content:center;font-size:22px;">
                    <i class="bi bi-card-list"></i>
                </div>
                <span style="font-size:12px;font-weight:500;">Transaksi</span>
            </a>
            <a href="index.php?page=chat" style="text-decoration:none;color:inherit;display:flex;flex-direction:column;align-items:center;gap:8px;">
                <div style="width:48px;height:48px;border-radius:14px;background:rgba(168,85,247,.1);color:#a855f7;display:flex;align-items:center;justify-content:center;font-size:22px;">
                    <i class="bi bi-chat-dots-fill"></i>
                </div>
                <span style="font-size:12px;font-weight:500;">Chat</span>
            </a>
            <a href="index.php?page=reports" style="text-decoration:none;color:inherit;display:flex;flex-direction:column;align-items:center;gap:8px;">
                <div style="width:48px;height:48px;border-radius:14px;background:rgba(59,130,246,.1);color:#3b82f6;display:flex;align-items:center;justify-content:center;font-size:22px;">
                    <i class="bi bi-bar-chart-fill"></i>
                </div>
                <span style="font-size:12px;font-weight:500;">Laporan</span>
            </a>
            <a href="index.php?page=categories" style="text-decoration:none;color:inherit;display:flex;flex-direction:column;align-items:center;gap:8px;">
                <div style="width:48px;height:48px;border-radius:14px;background:rgba(234,179,8,.1);color:#eab308;display:flex;align-items:center;justify-content:center;font-size:22px;">
                    <i class="bi bi-tags-fill"></i>
                </div>
                <span style="font-size:12px;font-weight:500;">Kategori</span>
            </a>
            <a href="index.php?page=activitylog" style="text-decoration:none;color:inherit;display:flex;flex-direction:column;align-items:center;gap:8px;">
                <div style="width:48px;height:48px;border-radius:14px;background:rgba(249,115,22,.1);color:#f97316;display:flex;align-items:center;justify-content:center;font-size:22px;">
                    <i class="bi bi-clock-history"></i>
                </div>
                <span style="font-size:12px;font-weight:500;">Log Aktivitas</span>
            </a>
            <a href="index.php?page=settings#backupCard" style="text-decoration:none;color:inherit;display:flex;flex-direction:column;align-items:center;gap:8px;">
                <div style="width:48px;height:48px;border-radius:14px;background:rgba(14,165,233,.1);color:#0ea5e9;display:flex;align-items:center;justify-content:center;font-size:22px;">
                    <i class="bi bi-database-down"></i>
                </div>
                <span style="font-size:12px;font-weight:500;">Backup & Restore</span>
            </a>
            <a href="index.php?page=settings" style="text-decoration:none;color:inherit;display:flex;flex-direction:column;align-items:center;gap:8px;">
                <div style="width:48px;height:48px;border-radius:14px;background:rgba(107,114,128,.1);color:#6b7280;display:flex;align-items:center;justify-content:center;font-size:22px;">
                    <i class="bi bi-gear-fill"></i>
                </div>
                <span style="font-size:12px;font-weight:500;">Setelan</span>
            </a>
        </div>
        <div class="modal-footer">
            <button onclick="closeModal('modalAllMenus')" class="btn btn-outline w-full">Tutup</button>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../include/footer.php'; ?>
