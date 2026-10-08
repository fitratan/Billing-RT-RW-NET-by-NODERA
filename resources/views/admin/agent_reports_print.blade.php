<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Laporan Kolektor</title>
<style>
body{font-family:'Courier New',monospace;font-size:11px;padding:20px;color:#000;}
h2{text-align:center;margin-bottom:5px;}
table{width:100%;border-collapse:collapse;margin-top:10px;}
th,td{border:1px solid #ccc;padding:6px 8px;text-align:left;font-size:10px;}
th{background:#f0f0f0;}
.text-right{text-align:right;}
@media print{@page{margin:10mm;}}
</style></head><body>
<h2>{{ session('tenant_name', 'Laporan') }}</h2>
<p style='text-align:center;font-size:10px;'>Laporan Kolektor</p>
<table><tr><th>#</th><th>Kolektor</th><th class='text-right'>Tagihan Dibayar</th><th class='text-right'>Total Tagihan</th><th class='text-right'>Total Dibayar</th></tr>
@php
    $collectors = \App\Models\Collector::where('tenant_id', session('tenant_id'))->get();
@endphp
@foreach($collectors as $i => $c)
@php
    $paidCount = \App\Models\Invoice::where('collector_id', $c->id)->where('paid', true)->count();
    $totalInvoices = \App\Models\Invoice::where('collector_id', $c->id)->count();
    $totalPaid = \App\Models\Invoice::where('collector_id', $c->id)->where('paid', true)->sum('amount');
    $totalAmount = \App\Models\Invoice::where('collector_id', $c->id)->sum('amount');
@endphp
<tr><td>{{ $i + 1 }}</td><td>{{ $c->name }}</td><td class='text-right'>{{ $paidCount }} / {{ $totalInvoices }}</td><td class='text-right'>Rp{{ number_format($totalAmount, 0, ',', '.') }}</td><td class='text-right'>Rp{{ number_format($totalPaid, 0, ',', '.') }}</td></tr>
@endforeach
</table>
<p style='text-align:right;margin-top:15px;font-size:10px;'>Dicetak: {{ now()->format('d/m/Y H:i') }}</p>
<script>window.print()</script>
</body></html>