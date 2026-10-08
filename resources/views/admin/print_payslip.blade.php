@extends('layouts.print')
@section('title', 'Cetak Slip Gaji')
@section('content')
<style>
@media print { body * { visibility:hidden; } #printArea, #printArea * { visibility:visible; } #printArea { position:absolute; left:0; top:0; width:72mm; } @page { margin:0; size:72mm auto; } }
.thermal{width:72mm;margin:0 auto;background:#fff;color:#000;font-family:'Courier New',monospace;font-size:10px;padding:10px 5px;}
.thermal .header{text-align:center;font-weight:bold;font-size:12px;margin-bottom:8px;}
.thermal .divider{border-top:1px dashed #000;margin:6px 0;}
.thermal .row{display:flex;justify-content:space-between;}
</style>
<div class="container-fluid px-0">
    <div class="d-flex align-items-center justify-content-between mb-4">
        <h1 class="fs-4 fw-bold mb-0"><i class="bi bi-wallet2 text-primary me-2"></i>Cetak Slip Gaji</h1>
        <button class="btn btn-primary btn-sm" onclick="window.print()"><i class="bi bi-printer me-1"></i> Print</button>
    </div>
    <div id="printArea" class="thermal">
        <div class="header">{{ session('tenant_name', 'NODERA') }}</div>
        <div class="divider"></div>
        <div style="text-align:center;font-weight:bold;font-size:11px;">SLIP GAJI</div>
        <div class="divider"></div>
        @if(isset($payroll))
        <div class="row"><span>Nama</span><span>{{ $payroll->employee_name ?? '-' }}</span></div>
        <div class="row"><span>Periode</span><span>{{ $payroll->period ?? '-' }}</span></div>
        <div class="divider"></div>
        <div class="row"><span>Gaji Pokok</span><span>Rp{{ number_format($payroll->basic_salary ?? 0, 0, ',', '.') }}</span></div>
        <div class="row"><span>Bonus</span><span>Rp{{ number_format($payroll->bonus ?? 0, 0, ',', '.') }}</span></div>
        <div class="row"><span>Potongan</span><span>Rp{{ number_format($payroll->deductions ?? 0, 0, ',', '.') }}</span></div>
        <div class="divider"></div>
        <div class="row" style="font-size:14px;font-weight:bold;">
            <span>TOTAL</span>
            <span>Rp{{ number_format(($payroll->basic_salary ?? 0) + ($payroll->bonus ?? 0) - ($payroll->deductions ?? 0), 0, ',', '.') }}</span>
        </div>
        @else
        <div style="text-align:center;">Pilih data payroll untuk dicetak</div>
        @endif
        <div class="divider"></div>
        <div style="text-align:center;font-size:8px;">Terima kasih</div>
    </div>
</div>
@endsection
