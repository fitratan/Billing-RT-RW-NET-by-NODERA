<?php
$pageTitle = 'Kategori';
$activeTab = 'categories';

$pageFab = [
    'icon' => 'bi-plus-lg',
    'label' => 'Tambah Kategori',
    'onclick' => "openModal('modalAddCat')",
];
require_once __DIR__ . '/../include/header.php';

$data       = getBkData();
$categories = $data['categories'] ?? [];
?>

<?php if (isset($_GET['msg'])): ?>
<div class="flash success">
    <i class="bi bi-check-circle-fill" style="flex-shrink:0;"></i>
    <span style="flex:1;">Kategori diperbarui!</span>
    <button class="flash-close" style="background:none;border:none;color:inherit;cursor:pointer;font-size:16px;">&times;</button>
</div>
<?php endif; ?>

<!-- Category list only (adding category is handled exclusively via FAB / Modal) -->

<!-- Category list only (no inline form — form is in FAB/modal) -->
<div class="card">
    <div style="padding:20px;">
        <div class="section-title" style="margin-bottom:12px;">
            Daftar Kategori
            <span style="font-size:12px;font-weight:400;color:var(--muted-fg);margin-left:6px;"><?= count($categories) ?> kategori</span>
        </div>

        <?php if (empty($categories)): ?>
            <div class="empty-state">
                <i class="bi bi-tags"></i>
                <p>Belum ada kategori. Ketuk FAB (+) untuk menambah.</p>
            </div>
        <?php else: ?>
            <?php foreach ($categories as $cat): ?>
                <div class="divide-row" style="display:flex;align-items:center;gap:12px;padding:12px 0;">
                    <div style="width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;
                        background:<?= $cat['type']==='income'?'rgba(34,197,94,.1)':'rgba(239,68,68,.1)' ?>;
                        color:<?= $cat['type']==='income'?'#22c55e':'#ef4444' ?>;">
                        <i class="bi <?= $cat['type']==='income'?'bi-tag-fill':'bi-tag' ?>"></i>
                    </div>
                    <div style="flex:1;min-width:0;">
                        <p style="margin:0;font-size:13px;font-weight:500;color:var(--fg);"><?= htmlspecialchars($cat['name']) ?></p>
                        <p style="margin:0;font-size:11px;color:var(--muted-fg);"><?= $cat['type']==='income'?'Pemasukan (+)':'Pengeluaran (-)' ?></p>
                    </div>
                    <a href="index.php?delete_cat=<?= $cat['id'] ?>"
                       onclick="return confirm('Hapus kategori ini?')"
                       style="color:var(--muted-fg);text-decoration:none;font-size:16px;flex-shrink:0;transition:color .15s;"
                       onmouseover="this.style.color='#ef4444'" onmouseout="this.style.color='var(--muted-fg)'">
                        <i class="bi bi-trash"></i>
                    </a>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- ── Modal: Tambah Kategori (FAB opens this) ── -->
<div id="modalAddCat" class="modal-backdrop">
    <div class="modal-box">
        <div class="modal-header">
            <span class="modal-title">Tambah Kategori Baru</span>
            <button onclick="closeModal('modalAddCat')" style="background:none;border:none;color:var(--muted-fg);cursor:pointer;font-size:22px;line-height:1;">&times;</button>
        </div>
        <form method="POST" action="index.php" style="display:flex;flex-direction:column;gap:12px;">
            <input type="hidden" name="action" value="add_category">
            <div class="field">
                <label>Nama Kategori *</label>
                <input type="text" name="name" placeholder="cth: Penjualan Voucher" required>
            </div>
            <div class="field">
                <label>Jenis *</label>
                <select name="type" required>
                    <option value="income">Pemasukan (+)</option>
                    <option value="expense">Pengeluaran (-)</option>
                </select>
            </div>
            <div style="display:flex;gap:8px;">
                <button type="submit" class="btn btn-primary w-full">Simpan Kategori</button>
                <button type="button" onclick="closeModal('modalAddCat')" class="btn btn-outline">Batal</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../include/footer.php'; ?>
