@extends('layouts.print')
@section('title', 'Cetak Invoice Thermal')
@section('content')
<style>
@media print {
    body * { visibility: hidden; }
    #printArea, #printArea * { visibility: visible; }
    #printArea { position: absolute; left: 0; top: 0; width: 72mm; padding: 0; }
    @page { margin: 0; size: 72mm auto; }
}
.thermal-paper{width:72mm;margin:0 auto;background:#fff;color:#000;font-family:'Courier New',monospace;font-size:10px;padding:10px 5px;}
.thermal-paper .header{text-align:center;font-weight:bold;font-size:12px;margin-bottom:8px;}
.thermal-paper .divider{border-top:1px dashed #000;margin:6px 0;}
.thermal-paper .row{display:flex;justify-content:space-between;}
</style>

<div class="container-fluid px-0">
    <div class="d-flex align-items-center justify-content-between mb-4">
        <h1 class="fs-4 fw-bold mb-0"><i class="bi bi-printer text-primary me-2"></i>Cetak Invoice Thermal</h1>
        <button class="btn btn-primary btn-sm" onclick="window.print()"><i class="bi bi-printer me-1"></i> Print</button>
    </div>

    <div id="printArea" class="thermal-paper">
        @if(!empty($invoice))
        @php
            $invNum = is_array($invoice) ? ($invoice['invoice_number'] ?? '') : ($invoice->invoice_number ?? '');
            $custName = is_array($invoice) ? ($invoice['customer_name'] ?? '-') : ($invoice->customer_name ?? '-');
            $routerName = is_array($invoice) ? ($invoice['router_name'] ?? '-') : ($invoice->router_name ?? '-');
            $rawPeriod = is_array($invoice) ? ($invoice['period'] ?? null) : ($invoice->period ?? null);
            if (empty($rawPeriod)) {
                $rawDate = is_array($invoice) ? ($invoice['due_date'] ?? $invoice['created_at'] ?? now()) : ($invoice->due_date ?? $invoice->created_at ?? now());
                $period = \Carbon\Carbon::parse($rawDate)->isoFormat('MMMM YYYY');
            } elseif (preg_match('/^(\d{4})-(\d{1,2})/', $rawPeriod, $pm)) {
                $period = \Carbon\Carbon::createFromDate((int)$pm[1], (int)$pm[2], 1)->isoFormat('MMMM YYYY');
            } else {
                $period = $rawPeriod;
            }
            $pkgName = is_array($invoice) ? ($invoice['package_name'] ?? '-') : ($invoice->package_name ?? '-');
            $amount = is_array($invoice) ? ($invoice['amount'] ?? 0) : ($invoice->amount ?? 0);
            $isPaid = is_array($invoice) ? (($invoice['status'] ?? '') === 'paid' || !empty($invoice['paid'])) : (($invoice->status ?? '') === 'paid' || !empty($invoice->paid));
            $paidAt = is_array($invoice) ? ($invoice['paid_at'] ?? null) : ($invoice->paid_at ?? null);
        @endphp
        <div class="header">{{ session('tenant_name', 'NODERA') }}</div>
        <div style="text-align:center;font-size:9px;">{{ session('tenant_address', '') }}</div>
        <div style="text-align:center;font-size:9px;">Telp: {{ session('tenant_phone', '') }}</div>
        <div class="divider"></div>
        <div style="text-align:center;font-weight:bold;font-size:11px;">INVOICE</div>
        <div class="divider"></div>
        <div class="row"><span>No. Invoice</span><span>{{ $invNum }}</span></div>
        <div class="row"><span>Tanggal</span><span>{{ now()->format('d/m/Y') }}</span></div>
        <div class="row"><span>Pelanggan</span><span>{{ $custName }}</span></div>
        <div class="row"><span>Router</span><span>{{ $routerName }}</span></div>
        <div class="divider"></div>
        <div class="row"><span>Periode</span><span>{{ $period }}</span></div>
        <div class="row"><span>Paket</span><span>{{ $pkgName }}</span></div>
        <div class="divider"></div>
        <div class="row" style="font-size:14px;font-weight:bold;">
            <span>TOTAL</span>
            <span>Rp{{ number_format((float)$amount, 0, ',', '.') }}</span>
        </div>
        <div class="divider"></div>
        @if($isPaid)
        <div style="text-align:center;font-weight:bold;color:green;">LUNAS</div>
        <div style="text-align:center;font-size:9px;">{{ $paidAt ? \Carbon\Carbon::parse($paidAt)->format('d/m/Y H:i') : '' }}</div>
        @else
        <div style="text-align:center;font-weight:bold;color:red;">BELUM LUNAS</div>
        @endif
        <div class="divider"></div>
        <div style="text-align:center;font-size:8px;">Terima kasih</div>
        @else
        <div class="text-center py-5 text-muted">
            <p class="mb-0">Pilih invoice untuk dicetak</p>
        </div>
        @endif
    </div>

    <div class="card shadow-sm border-0 rounded-3 mt-4">
        <div class="card-body">
            <h6 class="fw-bold mb-3">Pilih Invoice</h6>
            <form method="GET" action="/admin/billing/invoices/print-thermal">
                <div class="mb-3">
                    <label class="form-label small fw-medium">Invoice Number</label>
                    <input type="text" name="id" class="form-control" placeholder="Masukkan ID invoice">
                </div>
                <button type="submit" class="btn btn-primary w-100 rounded-3">Tampilkan Preview</button>
            </form>
        </div>
    </div>
</div>
@endsection
