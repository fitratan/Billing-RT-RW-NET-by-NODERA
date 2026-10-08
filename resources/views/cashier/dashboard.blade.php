<!DOCTYPE html>
<html lang="id">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Dashboard Kasir</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>body{background:#f3f4f6;padding:20px;padding-bottom:80px;}
.card{border:none;border-radius:16px;box-shadow:0 2px 12px rgba(0,0,0,.04);}
.stat{background:var(--bs-primary);color:#fff;border-radius:12px;padding:16px;}
.stat .num{font-size:1.5rem;font-weight:800;}</style></head>
<body>
<div class="container" style="max-width:500px;">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div><h5 class="fw-bold mb-0"><i class="bi bi-cash-stack me-2"></i>Kasir</h5>
        <p class="text-muted small mb-0">{{ session('cashier_name') }}</p></div>
        <a href="/cashier/close" class="btn btn-outline-danger btn-sm rounded-3" onclick="return confirm('Tutup sesi kasir?')"><i class="bi bi-x-circle me-1"></i> Tutup</a>
    </div>

    @if(session('msg'))<div class="alert alert-success py-2 small">{{ session('msg') }}</div>@endif

    <div class="row g-2 mb-4">
        <div class="col-6"><div class="stat" style="background:#059669;">
            <p class="small mb-0 opacity-75">Transaksi Hari Ini</p>
            <div class="num">{{ $todayCount }}</div></div></div>
        <div class="col-6"><div class="stat" style="background:#2563eb;">
            <p class="small mb-0 opacity-75">Total Nominal</p>
            <div class="num">Rp{{ number_format($todayTransactions, 0, ',', '.') }}</div></div></div>
    </div>

    {{-- Bayar Invoice --}}
    <div class="card p-3 mb-3">
        <h6 class="fw-bold mb-3"><i class="bi bi-receipt me-1"></i>Bayar Invoice</h6>
        <form method="POST" action="/cashier/pay">
            @csrf
            <div class="mb-2"><input type="text" name="search" id="searchInv" class="form-control" placeholder="Cari invoice..."></div>
            <div class="mb-2">
                <select name="invoice_id" id="invoiceSelect" class="form-select" required>
                    <option value="">Pilih invoice...</option>
                    @foreach(\App\Models\Invoice::where('paid', false)->where('tenant_id', session('tenant_id'))->limit(20)->get() as $inv)
                    <option value="{{ $inv->id }}">{{ $inv->invoice_number }} - {{ $inv->customer_name }} - Rp{{ number_format($inv->amount,0,',','.') }}</option>
                    @endforeach
                </select>
            </div>
            <div class="row g-2 mb-2">
                <div class="col-6">
                    <select name="payment_method" class="form-select">
                        <option value="cash">Tunai</option>
                        <option value="transfer">Transfer</option>
                        <option value="qris">QRIS</option>
                    </select>
                </div>
                <div class="col-6"><input type="text" name="notes" class="form-control" placeholder="Catatan"></div>
            </div>
            <button type="submit" class="btn btn-success w-100 rounded-3 py-2 fw-semibold"><i class="bi bi-check-lg me-1"></i> Bayar</button>
        </form>
    </div>

    {{-- Transaksi Terbaru --}}
    <div class="card p-3">
        <h6 class="fw-bold mb-3"><i class="bi bi-clock-history me-1"></i>Transaksi Terbaru</h6>
        @if($recentTx->count() > 0)
        <div class="list-group list-group-flush">
            @foreach($recentTx as $tx)
            <div class="list-group-item border-0 d-flex justify-content-between px-0 py-2">
                <div><p class="small fw-semibold mb-0">Rp{{ number_format($tx->amount,0,',','.') }}</p>
                <p class="text-muted mb-0" style="font-size:10px;">{{ \Carbon\Carbon::parse($tx->created_at)->format('H:i') }} - {{ $tx->payment_method }}</p></div>
                <span class="badge bg-success bg-opacity-10 text-success" style="font-size:9px;">{{ $tx->type }}</span>
            </div>
            @endforeach
        </div>
        @else
        <p class="text-muted small text-center py-3 mb-0">Belum ada transaksi</p>
        @endif
    </div>
</div>
<script>
document.getElementById('searchInv')?.addEventListener('input', function() {
    const q = this.value.toLowerCase();
    document.querySelectorAll('#invoiceSelect option').forEach(opt => {
        if (opt.value === '') return;
        opt.style.display = opt.text.toLowerCase().includes(q) ? '' : 'none';
    });
});
</script>
</body></html>
