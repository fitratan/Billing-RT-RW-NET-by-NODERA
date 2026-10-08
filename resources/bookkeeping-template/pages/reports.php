<?php
$pageTitle = 'Laporan';
$activeTab = 'reports';

$pageFab = [
    [
        'icon'    => 'bi-file-earmark-excel',
        'label'   => 'Export Excel',
        'onclick' => 'exportExcel()',
        'color'   => '#22c55e'
    ],
    [
        'icon'    => 'bi-printer',
        'label'   => 'Cetak / PDF',
        'onclick' => 'window.print()',
        'color'   => 'var(--primary)'
    ],
];

require_once __DIR__ . '/../include/header.php';

$data = getBkData();
$allTx = $data['transactions'] ?? [];
$monthlyMap = [];
$incomeCatMap  = [];
$expenseCatMap = [];
$grandIncome  = 0;
$grandExpense = 0;

foreach ($allTx as $tx) {
    $m   = substr($tx['transaction_date'] ?? date('Y-m'), 0, 7);
    $cat = !empty($tx['category']) ? $tx['category'] : 'Umum';
    $amt = (float)($tx['amount'] ?? 0);

    if (!isset($monthlyMap[$m])) $monthlyMap[$m] = ['income' => 0, 'expense' => 0];

    if (($tx['type'] ?? '') === 'income') {
        $monthlyMap[$m]['income'] += $amt;
        $grandIncome += $amt;
        if (!isset($incomeCatMap[$cat])) $incomeCatMap[$cat] = 0;
        $incomeCatMap[$cat] += $amt;
    } else {
        $monthlyMap[$m]['expense'] += $amt;
        $grandExpense += $amt;
        if (!isset($expenseCatMap[$cat])) $expenseCatMap[$cat] = 0;
        $expenseCatMap[$cat] += $amt;
    }
}
krsort($monthlyMap);
arsort($incomeCatMap);
arsort($expenseCatMap);

$maxIncomeCat  = max(1, ...array_values($incomeCatMap ?: [1]));
$maxExpenseCat = max(1, ...array_values($expenseCatMap ?: [1]));
$grandNet      = $grandIncome - $grandExpense;

if (!function_exists('formatReportMonth')) {
    function formatReportMonth($yyyy_mm) {
        $months = [
            '01'=>'Januari', '02'=>'Februari', '03'=>'Maret', '04'=>'April',
            '05'=>'Mei', '06'=>'Juni', '07'=>'Juli', '08'=>'Agustus',
            '09'=>'September', '10'=>'Oktober', '11'=>'November', '12'=>'Desember'
        ];
        $p = explode('-', $yyyy_mm);
        if (count($p) === 2 && isset($months[$p[1]])) {
            return $months[$p[1]] . ' ' . $p[0];
        }
        return $yyyy_mm;
    }
}
?>

<!-- Override: FAB juga tampil di desktop untuk laporan -->
<style>
    @media (min-width: 1024px) {
        .fab-btn { display: flex !important; }
        .fab-actions { display: none; }
        .fab-actions.fab-open { display: flex !important; }
    }
</style>

<!-- Grand Total KPI Stats (Matches Dashboard Hero Card Design) -->
<div style="display:grid;gap:12px;grid-template-columns:1fr 1fr;margin-bottom:16px;">
    <!-- Net balance hero card -->
    <div style="grid-column:1/-1;border-radius:14px;padding:20px;color:#fff;background:linear-gradient(135deg,var(--primary) 0%,color-mix(in srgb,var(--primary) 70%,#6366f1) 100%);box-shadow:0 8px 24px color-mix(in srgb,var(--primary) 30%,transparent);">
        <div style="display:flex;align-items:center;justify-content:space-between;">
            <span style="font-size:11px;font-weight:700;letter-spacing:.06em;opacity:.85;text-transform:uppercase;">Saldo Bersih Total</span>
            <div style="width:40px;height:40px;border-radius:12px;background:rgba(255,255,255,.2);display:flex;align-items:center;justify-content:center;font-size:18px;">
                <i class="bi bi-wallet2"></i>
            </div>
        </div>
        <div style="margin-top:12px;">
            <p style="margin:0;font-size:clamp(24px,5.5vw,34px);font-weight:700;letter-spacing:-0.02em;word-break:break-all;">
                <?= formatRupiah($grandNet) ?>
            </p>
            <p style="margin:4px 0 0;font-size:11px;opacity:.8;">Akumulasi Seluruh Waktu Transaksi</p>
        </div>
    </div>

    <!-- Income card -->
    <div class="card" style="padding:16px;">
        <div style="width:36px;height:36px;border-radius:10px;background:rgba(34,197,94,.1);color:#22c55e;display:flex;align-items:center;justify-content:center;font-size:16px;">
            <i class="bi bi-graph-up-arrow"></i>
        </div>
        <div style="margin-top:10px;font-size:clamp(13px,3.5vw,18px);font-weight:600;color:#22c55e;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
            <?= formatRupiah($grandIncome) ?>
        </div>
        <div style="font-size:11px;color:var(--muted-fg);">Total Pemasukan</div>
    </div>

    <!-- Expense card -->
    <div class="card" style="padding:16px;">
        <div style="width:36px;height:36px;border-radius:10px;background:rgba(239,68,68,.1);color:#ef4444;display:flex;align-items:center;justify-content:center;font-size:16px;">
            <i class="bi bi-arrow-down-circle-fill"></i>
        </div>
        <div style="margin-top:10px;font-size:clamp(13px,3.5vw,18px);font-weight:600;color:#ef4444;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
            <?= formatRupiah($grandExpense) ?>
        </div>
        <div style="font-size:11px;color:var(--muted-fg);">Total Pengeluaran</div>
    </div>
</div>

<!-- Monthly Summary Table -->
<div class="card" style="margin-bottom:16px;">
    <div style="padding:20px;">
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:16px;">
            <div style="width:32px;height:32px;border-radius:8px;background:rgba(125,160,255,.1);color:var(--primary);display:flex;align-items:center;justify-content:center;">
                <i class="bi bi-table"></i>
            </div>
            <div>
                <div class="section-title">Ringkasan Bulanan</div>
            </div>
        </div>

        <?php if (empty($monthlyMap)): ?>
            <div class="empty-state"><i class="bi bi-inbox"></i><p>Belum ada data laporan.</p></div>
        <?php else: ?>
            <div class="no-scrollbar" style="overflow-x:auto;-webkit-overflow-scrolling:touch;margin:0 -4px;scrollbar-width:none;-ms-overflow-style:none;">
                <table style="width:100%;min-width:480px;border-collapse:collapse;font-size:12px;">
                    <thead>
                        <tr style="border-bottom:1px solid var(--border);">
                            <th style="text-align:left;padding:10px 12px;color:var(--muted-fg);font-weight:500;white-space:nowrap;">Bulan</th>
                            <th style="text-align:right;padding:10px 12px;color:#22c55e;font-weight:500;white-space:nowrap;">Pemasukan</th>
                            <th style="text-align:right;padding:10px 12px;color:#ef4444;font-weight:500;white-space:nowrap;">Pengeluaran</th>
                            <th style="text-align:right;padding:10px 12px;color:var(--muted-fg);font-weight:500;white-space:nowrap;">Saldo Bersih</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($monthlyMap as $month => $row):
                            $net = $row['income'] - $row['expense'];
                        ?>
                            <tr style="border-bottom:1px solid var(--border);">
                                <td style="padding:12px;font-weight:600;color:var(--fg);white-space:nowrap;"><?= htmlspecialchars(formatReportMonth($month)) ?></td>
                                <td style="padding:12px;text-align:right;font-weight:600;color:#22c55e;white-space:nowrap;font-variant-numeric:tabular-nums;"><?= formatRupiah($row['income']) ?></td>
                                <td style="padding:12px;text-align:right;font-weight:600;color:#ef4444;white-space:nowrap;font-variant-numeric:tabular-nums;"><?= formatRupiah($row['expense']) ?></td>
                                <td style="padding:12px;text-align:right;font-weight:700;color:<?= $net>=0?'var(--primary)':'#ef4444' ?>;white-space:nowrap;font-variant-numeric:tabular-nums;">
                                    <?= formatRupiah($net) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Category Breakdown Grid (Income vs Expense) -->
<div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(280px, 1fr));gap:16px;margin-bottom:16px;">
    <!-- 🟢 Income Categories -->
    <div class="card">
        <div style="padding:20px;">
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:16px;">
                <div style="width:32px;height:32px;border-radius:8px;background:rgba(34,197,94,.1);color:#22c55e;display:flex;align-items:center;justify-content:center;">
                    <i class="bi bi-arrow-down-left"></i>
                </div>
                <div>
                    <h3 style="margin:0;font-size:14px;font-weight:700;color:var(--fg);">Kategori Pemasukan</h3>
                    <p style="margin:2px 0 0;font-size:11px;color:var(--muted-fg);"><?= count($incomeCatMap) ?> kategori aktif</p>
                </div>
            </div>

            <?php if (empty($incomeCatMap)): ?>
                <p style="font-size:12px;color:var(--muted-fg);margin:0;text-align:center;padding:20px 0;">Belum ada data pemasukan.</p>
            <?php else: ?>
                <?php foreach (array_slice($incomeCatMap, 0, 10, true) as $cat => $total):
                    $pct = $grandIncome > 0 ? round(($total / $grandIncome) * 100, 1) : 0;
                    $barWidth = max(4, ($total / $maxIncomeCat) * 100);
                ?>
                    <div style="margin-bottom:14px;">
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:4px;">
                            <span style="font-size:12px;font-weight:500;color:var(--fg);"><?= htmlspecialchars($cat) ?></span>
                            <div style="text-align:right;">
                                <span style="font-size:12px;font-weight:600;color:#22c55e;">Rp <?= number_format($total,0,',','.') ?></span>
                                <span style="font-size:10px;color:var(--muted-fg);margin-left:4px;">(<?= $pct ?>%)</span>
                            </div>
                        </div>
                        <div style="height:6px;background:var(--muted);border-radius:999px;overflow:hidden;">
                            <div style="height:100%;width:<?= $barWidth ?>%;background:#22c55e;border-radius:999px;transition:width .6s;"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- 🔴 Expense Categories -->
    <div class="card">
        <div style="padding:20px;">
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:16px;">
                <div style="width:32px;height:32px;border-radius:8px;background:rgba(239,68,68,.1);color:#ef4444;display:flex;align-items:center;justify-content:center;">
                    <i class="bi bi-arrow-up-right"></i>
                </div>
                <div>
                    <h3 style="margin:0;font-size:14px;font-weight:700;color:var(--fg);">Kategori Pengeluaran</h3>
                    <p style="margin:2px 0 0;font-size:11px;color:var(--muted-fg);"><?= count($expenseCatMap) ?> kategori aktif</p>
                </div>
            </div>

            <?php if (empty($expenseCatMap)): ?>
                <p style="font-size:12px;color:var(--muted-fg);margin:0;text-align:center;padding:20px 0;">Belum ada data pengeluaran.</p>
            <?php else: ?>
                <?php foreach (array_slice($expenseCatMap, 0, 10, true) as $cat => $total):
                    $pct = $grandExpense > 0 ? round(($total / $grandExpense) * 100, 1) : 0;
                    $barWidth = max(4, ($total / $maxExpenseCat) * 100);
                ?>
                    <div style="margin-bottom:14px;">
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:4px;">
                            <span style="font-size:12px;font-weight:500;color:var(--fg);"><?= htmlspecialchars($cat) ?></span>
                            <div style="text-align:right;">
                                <span style="font-size:12px;font-weight:600;color:#ef4444;">Rp <?= number_format($total,0,',','.') ?></span>
                                <span style="font-size:10px;color:var(--muted-fg);margin-left:4px;">(<?= $pct ?>%)</span>
                            </div>
                        </div>
                        <div style="height:6px;background:var(--muted);border-radius:999px;overflow:hidden;">
                            <div style="height:100%;width:<?= $barWidth ?>%;background:#ef4444;border-radius:999px;transition:width .6s;"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../include/footer.php'; ?>
