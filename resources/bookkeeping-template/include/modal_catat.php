<?php
// Shared modal — included by dashboard.php and transactions.php
$cats = $categories ?? (getBkData()['categories'] ?? []);
?>
<div id="modalCatat" class="modal-backdrop">
    <div class="modal-box">
        <div class="modal-header">
            <span class="modal-title" id="catatTitle">Catat Transaksi</span>
            <button onclick="closeModal('modalCatat')" style="background:none;border:none;color:var(--muted-fg);cursor:pointer;font-size:22px;line-height:1;">&times;</button>
        </div>
        <form id="catatForm" method="POST" action="index.php" enctype="multipart/form-data" style="display:flex;flex-direction:column;gap:12px;">
            <input type="hidden" name="action" id="catatAction" value="add_transaction">
            <input type="hidden" name="tx_id" id="catatTxId" value="">
            <input type="hidden" name="redirect_url" id="catatRedirectUrl" value="">
            <div class="field">
                <label>Jenis Transaksi *</label>
                <select name="type" id="catatType" required onchange="filterCatatCategories()">
                    <option value="income">Pemasukan (+)</option>
                    <option value="expense">Pengeluaran (-)</option>
                </select>
            </div>
            <div class="field">
                <label>Kategori *</label>
                <select name="category" id="catatCategory" required>
                    <?php foreach ($cats as $c): ?>
                        <option value="<?= htmlspecialchars($c['name']) ?>" data-type="<?= $c['type'] ?>"><?= htmlspecialchars($c['name']) ?> (<?= $c['type']==='income'?'Pemasukan':'Pengeluaran' ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                <div class="field">
                    <label>Nominal (Rp) *</label>
                    <input type="number" name="amount" id="catatAmount" placeholder="150000" required step="1000" style="color:var(--primary);font-weight:700;font-size:15px;">
                </div>
                <div class="field">
                    <label>Tanggal *</label>
                    <input type="date" name="transaction_date" id="catatDate" value="<?= date('Y-m-d') ?>" required>
                </div>
            </div>
            <div class="field">
                <label>Keterangan</label>
                <input type="text" name="description" id="catatDesc" placeholder="Opsional">
            </div>
            <div class="field">
                <label>Upload Bukti / Nota (Opsional)</label>
                <input type="file" name="receipt" accept="image/*,.pdf" style="padding:6px 14px;">
            </div>
            <div style="display:flex;gap:8px;margin-top:4px;">
                <button type="submit" class="btn btn-primary w-full" id="catatSubmitBtn">Simpan Transaksi</button>
                <button type="button" onclick="closeModal('modalCatat')" class="btn btn-outline">Batal</button>
            </div>
        </form>
    </div>
</div>

<script>
function filterCatatCategories() {
    var type = document.getElementById('catatType').value;
    var catSelect = document.getElementById('catatCategory');
    if(!catSelect) return;
    var options = catSelect.options;
    var firstVisible = null;
    for(var i=0; i<options.length; i++){
        var opt = options[i];
        if(opt.getAttribute('data-type') === type) {
            opt.style.display = '';
            if(!firstVisible) firstVisible = opt;
        } else {
            opt.style.display = 'none';
        }
    }
    // Only auto-select if current is hidden
    if(catSelect.options[catSelect.selectedIndex].style.display === 'none' && firstVisible) {
        firstVisible.selected = true;
    }
}
// Init category filter on load
document.addEventListener('DOMContentLoaded', filterCatatCategories);

function openCatatModalNew() {
    document.getElementById('catatTitle').innerText = 'Catat Transaksi';
    document.getElementById('catatAction').value = 'add_transaction';
    document.getElementById('catatTxId').value = '';
    document.getElementById('catatRedirectUrl').value = window.location.href;
    document.getElementById('catatForm').reset();
    document.getElementById('catatDate').value = new Date().toISOString().split('T')[0];
    document.getElementById('catatSubmitBtn').innerText = 'Simpan Transaksi';
    filterCatatCategories();
    openModal('modalCatat');
}

function openCatatModalEdit(tx) {
    document.getElementById('catatTitle').innerText = 'Edit Transaksi';
    document.getElementById('catatAction').value = 'edit_transaction';
    document.getElementById('catatTxId').value = tx.id;
    document.getElementById('catatRedirectUrl').value = window.location.href;
    document.getElementById('catatType').value = tx.type;
    filterCatatCategories();
    
    var catSelect = document.getElementById('catatCategory');
    if(!Array.from(catSelect.options).some(function(o){ return o.value === tx.category; })) {
        var opt = document.createElement('option');
        opt.value = tx.category;
        opt.text = tx.category + ' (' + (tx.type === 'income' ? 'Pemasukan' : 'Pengeluaran') + ')';
        opt.setAttribute('data-type', tx.type);
        catSelect.appendChild(opt);
    }
    catSelect.value = tx.category;
    
    document.getElementById('catatAmount').value = tx.amount;
    document.getElementById('catatDate').value = tx.transaction_date;
    document.getElementById('catatDesc').value = tx.description || '';
    document.getElementById('catatSubmitBtn').innerText = 'Simpan Perubahan';
    openModal('modalCatat');
}
</script>
