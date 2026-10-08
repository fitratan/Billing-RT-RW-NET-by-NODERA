<!-- ── Modal: Transaction Detail (Shared) ── -->
<div id="modalTxDetail" class="modal-backdrop">
    <div class="modal-box">
        <div class="modal-header">
            <span class="modal-title">Detail Transaksi</span>
            <button onclick="closeModal('modalTxDetail')" style="background:none;border:none;color:var(--muted-fg);cursor:pointer;font-size:22px;line-height:1;">&times;</button>
        </div>
        <div id="txDetailContent" style="font-size:13px;flex:1;max-height:55vh;overflow-y:auto;padding-right:4px;"></div>
        <div class="modal-footer" style="display:flex;gap:8px;align-items:center;justify-content:space-between;border-top:1px solid var(--border);padding-top:14px;flex-shrink:0;">
            <div style="display:flex;gap:8px;">
                <button type="button" onclick="editTxFromModal()" class="btn btn-outline btn-sm" style="color:var(--primary);border-color:color-mix(in srgb,var(--primary) 30%,transparent);" title="Edit Transaksi">
                    <i class="bi bi-pencil-square"></i> Edit
                </button>
                <button type="button" onclick="deleteTxFromModal()" class="btn btn-outline btn-sm" style="color:#ef4444;border-color:rgba(239,68,68,.3);" title="Hapus Transaksi">
                    <i class="bi bi-trash"></i> Hapus
                </button>
            </div>
            <button type="button" onclick="closeModal('modalTxDetail')" class="btn btn-primary btn-sm">Tutup</button>
        </div>
    </div>
</div>

<!-- ── Modal: Fullscreen Image Preview Lightbox ── -->
<div id="modalImagePreview" class="modal-backdrop" style="z-index:9999;background:rgba(0,0,0,.92);backdrop-filter:blur(8px);">
    <div style="position:relative;width:100%;height:100%;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:16px;">
        <button type="button" onclick="closeModal('modalImagePreview')" style="position:absolute;top:16px;right:16px;background:rgba(255,255,255,.2);border:none;color:#fff;width:40px;height:40px;border-radius:50%;font-size:24px;display:flex;align-items:center;justify-content:center;cursor:pointer;z-index:10;">&times;</button>
        <img id="lightboxImg" src="" style="max-width:100%;max-height:85vh;object-fit:contain;border-radius:12px;box-shadow:0 8px 30px rgba(0,0,0,.5);">
        <div style="margin-top:14px;display:flex;gap:12px;">
            <a id="lightboxDownloadBtn" href="" download class="btn btn-outline btn-sm" style="color:#fff;border-color:rgba(255,255,255,.4);background:rgba(255,255,255,.1);">
                <i class="bi bi-download"></i> Unduh Foto
            </a>
            <button type="button" onclick="closeModal('modalImagePreview')" class="btn btn-primary btn-sm">
                Tutup
            </button>
        </div>
    </div>
</div>

<script>
var _currentTx = null;

function handleRowClick(el) {
    if (!el) return;
    var raw = el.getAttribute('data-tx');
    if (!raw && el.dataset) raw = el.dataset.tx;
    if (!raw) return;
    try {
        var decoded = (typeof raw === 'string' && (raw.startsWith('%7B') || raw.startsWith('%7b'))) ? decodeURIComponent(raw) : raw;
        var tx = typeof decoded === 'string' ? JSON.parse(decoded) : decoded;
        showTxDetail(tx);
    } catch(e) {
        console.error('Failed to parse tx data:', e, raw);
    }
}

function showTxDetail(tx) {
    _currentTx = tx;
    var color = tx.type === 'income' ? '#22c55e' : '#ef4444';
    var label = tx.type === 'income' ? 'Pemasukan' : 'Pengeluaran';
    var receiptPath = tx.receipt_path || '';
    if (receiptPath && receiptPath.startsWith('../../')) {
        receiptPath = receiptPath.replace('../../', '');
    }

    document.getElementById('txDetailContent').innerHTML =
        '<div style="display:flex;flex-direction:column;gap:10px;">' +
        '<div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--border);"><span style="color:var(--muted-fg)">Jenis</span><span style="font-weight:600;color:' + color + '">' + label + '</span></div>' +
        '<div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--border);"><span style="color:var(--muted-fg)">Kategori</span><span style="font-weight:600;color:var(--fg)">' + tx.category + '</span></div>' +
        '<div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--border);"><span style="color:var(--muted-fg)">Nominal</span><span style="font-size:18px;font-weight:700;color:' + color + '">Rp ' + Number(tx.amount).toLocaleString("id-ID") + '</span></div>' +
        '<div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--border);"><span style="color:var(--muted-fg)">Tanggal</span><span style="font-weight:500;color:var(--fg)">' + tx.transaction_date + '</span></div>' +
        (tx.description ? '<div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--border);"><span style="color:var(--muted-fg)">Keterangan</span><span style="font-weight:500;color:var(--fg)">' + tx.description + '</span></div>' : '') +
        (receiptPath ? '<div style="padding:10px 0;"><span style="color:var(--muted-fg);display:block;margin-bottom:8px;font-weight:600;">Bukti / Foto Nota:</span><div onclick="openImageLightbox(\'' + receiptPath + '\')" style="cursor:pointer;text-align:center;position:relative;display:inline-block;width:100%;"><img src="' + receiptPath + '" style="max-height:180px;max-width:100%;object-fit:contain;border-radius:10px;border:1px solid var(--border);box-shadow:0 4px 12px rgba(0,0,0,.15);"><div style="font-size:11px;color:var(--primary);margin-top:6px;font-weight:600;"><i class="bi bi-arrows-angle-expand"></i> Klik untuk memperbesar foto</div></div></div>' : '') +
        '</div>';
    openModal('modalTxDetail');
}

function openImageLightbox(src) {
    var img = document.getElementById('lightboxImg');
    var dl  = document.getElementById('lightboxDownloadBtn');
    if (img) img.src = src;
    if (dl)  dl.href = src;
    openModal('modalImagePreview');
}

function deleteTxFromModal() {
    if (_currentTx && confirm('Hapus transaksi ini?')) {
        if (window.clearSPACache) window.clearSPACache();
        window.location.href = 'index.php?delete_tx=' + _currentTx.id + '&ref=' + encodeURIComponent(window.location.href);
    }
}

function editTxFromModal() {
    if (_currentTx) {
        closeModal('modalTxDetail');
        if (typeof openCatatModalEdit === 'function') {
            openCatatModalEdit(_currentTx);
        }
    }
}
</script>
