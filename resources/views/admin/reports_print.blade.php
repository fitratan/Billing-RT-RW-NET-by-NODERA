<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Laporan</title>
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
<p style='text-align:center;font-size:10px;'>Periode: {{ $from->format('d/m/Y') }} s/d {{ $to->format('d/m/Y') }}</p>
<p style='text-align:center;font-size:10px;'>Tipe: {{ ucfirst($type) }}</p>
@if($type === 'revenue')
<table><tr><th>#</th><th>Tanggal</th><th class='text-right'>Jumlah Tagihan</th><th class='text-right'>Total</th></tr>
@foreach($rows as $i => $row)
<tr><td>{{ $i + 1 }}</td><td>{{ \Carbon\Carbon::parse($row->report_date)->format('d/m/Y') }}</td><td class='text-right'>{{ number_format($row->invoice_count, 0, ',', '.') }}</td><td class='text-right'>Rp{{ number_format($row->total_amount, 0, ',', '.') }}</td></tr>
@endforeach
<tr><td colspan='3' class='text-right'><strong>Total</strong></td><td class='text-right'><strong>Rp{{ number_format($summary['total'], 0, ',', '.') }}</strong></td></tr>
</table>
@elseif($type === 'customers')
<table><tr><th>#</th><th>Nama</th><th>Telepon</th><th>Terdaftar</th></tr>
@foreach($rows as $i => $row)
<tr><td>{{ $i + 1 }}</td><td>{{ $row->name }}</td><td>{{ $row->phone }}</td><td>{{ $row->created_at ? $row->created_at->format('d/m/Y') : '-' }}</td></tr>
@endforeach
<tr><td colspan='4' class='text-right'><strong>Total: {{ number_format($summary['customer_count'], 0, ',', '.') }} pelanggan</strong></td></tr>
</table>
@elseif($type === 'collection')
<table><tr><th>#</th><th>Kolektor</th><th class='text-right'>Jumlah Tagihan</th><th class='text-right'>Total</th></tr>
@foreach($rows as $i => $row)
<tr><td>{{ $i + 1 }}</td><td>{{ $row->collector_name ?? '-' }}</td><td class='text-right'>{{ number_format($row->invoice_count, 0, ',', '.') }}</td><td class='text-right'>Rp{{ number_format($row->total_amount, 0, ',', '.') }}</td></tr>
@endforeach
<tr><td colspan='3' class='text-right'><strong>Total</strong></td><td class='text-right'><strong>Rp{{ number_format($summary['total'], 0, ',', '.') }}</strong></td></tr>
</table>
@endif
<p style='text-align:right;margin-top:15px;font-size:10px;'>Dicetak: {{ now()->format('d/m/Y H:i') }}</p>
<script>window.print()</script>
</body></html>