<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Transaksi — {{ $merchant->name }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 10mm 12mm 12mm 12mm;
        }
        * {
            box-sizing: border-box;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
        }
        body {
            background-color: #ffffff;
            color: #0f172a;
            margin: 0;
            padding: 0;
            font-size: 10px;
            line-height: 1.4;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
            border-bottom: 2px solid #0073C6;
            padding-bottom: 10px;
        }
        .header-table td {
            vertical-align: middle;
        }
        .brand-title {
            font-size: 16px;
            font-weight: 800;
            color: #0073C6;
            letter-spacing: -0.5px;
            margin: 0;
        }
        .brand-sub {
            font-size: 9px;
            color: #64748b;
            margin: 2px 0 0 0;
        }
        .doc-title {
            font-size: 14px;
            font-weight: 700;
            color: #0f172a;
            text-align: right;
            margin: 0;
        }
        .doc-meta {
            font-size: 9px;
            color: #64748b;
            text-align: right;
            margin: 2px 0 0 0;
        }
        .metrics-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 6px;
            margin-bottom: 14px;
        }
        .metric-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 8px 10px;
            vertical-align: top;
            width: 25%;
        }
        .metric-label {
            font-size: 8px;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .metric-val {
            font-size: 12px;
            font-weight: 800;
            color: #0f172a;
            margin-top: 2px;
            font-family: 'Courier New', Courier, monospace;
        }
        .metric-val.blue {
            color: #0073C6;
        }
        .metric-val.emerald {
            color: #059669;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        .data-table th {
            background-color: #0073C6;
            color: #ffffff;
            font-size: 8.5px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            padding: 6px 8px;
            text-align: left;
            border: 1px solid #0073C6;
        }
        .data-table td {
            padding: 5px 8px;
            font-size: 8.5px;
            border: 1px solid #e2e8f0;
            color: #1e293b;
            vertical-align: middle;
        }
        .data-table tr:nth-child(even) {
            background-color: #f8fafc;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .font-mono {
            font-family: 'Courier New', Courier, monospace;
        }
        .font-bold {
            font-weight: 700;
        }
        .badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 7.5px;
            font-weight: 700;
            text-transform: uppercase;
        }
        .badge-paid {
            background: #dcfce7;
            color: #15803d;
        }
        .badge-pending {
            background: #fef3c7;
            color: #b45309;
        }
        .badge-failed {
            background: #ffe4e6;
            color: #be123c;
        }
        .badge-expired {
            background: #f1f5f9;
            color: #64748b;
        }
        .footer {
            margin-top: 15px;
            border-top: 1px solid #e2e8f0;
            padding-top: 8px;
            font-size: 8px;
            color: #94a3b8;
            display: table;
            width: 100%;
        }
        .footer-left {
            display: table-cell;
            text-align: left;
        }
        .footer-right {
            display: table-cell;
            text-align: right;
        }
    </style>
</head>
<body>
    <table class="header-table">
        <tr>
            <td>
                <div class="brand-title">NODERA PAY</div>
                <div class="brand-sub">Universal Payment Gateway Hub &bull; {{ $merchant->name }} ({{ $merchant->merchant_code }})</div>
            </td>
            <td>
                <div class="doc-title">LAPORAN MUTASI TRANSAKSI</div>
                <div class="doc-meta">Periode: {{ $periodLabel }} &bull; Dicetak: {{ now()->format('d/m/Y H:i') }} WIB</div>
            </td>
        </tr>
    </table>

    <table class="metrics-table">
        <tr>
            <td class="metric-card">
                <div class="metric-label">Total Transaksi</div>
                <div class="metric-val">{{ count($transactions) }} Transaksi</div>
            </td>
            <td class="metric-card">
                <div class="metric-label">Total Nominal Bruto</div>
                <div class="metric-val">Rp {{ number_format($totalGross, 0, ',', '.') }}</div>
            </td>
            <td class="metric-card">
                <div class="metric-label">Total Biaya Fee</div>
                <div class="metric-val">Rp {{ number_format($totalFee, 0, ',', '.') }}</div>
            </td>
            <td class="metric-card">
                <div class="metric-label">Total Bersih (Netto Paid)</div>
                <div class="metric-val emerald">Rp {{ number_format($totalNetPaid, 0, ',', '.') }}</div>
            </td>
        </tr>
    </table>

    <table class="data-table">
        <thead>
            <tr>
                <th class="text-center" style="width: 25px;">No</th>
                <th style="width: 90px;">Tanggal / Waktu</th>
                <th>TRX Reference</th>
                <th>Ref ID</th>
                <th>Pelanggan</th>
                <th>Metode</th>
                <th class="text-right">Bruto</th>
                <th class="text-right">Fee</th>
                <th class="text-right">Netto</th>
                <th class="text-center" style="width: 55px;">Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse($transactions as $idx => $t)
                <tr>
                    <td class="text-center font-mono">{{ $idx + 1 }}</td>
                    <td class="font-mono">{{ \Carbon\Carbon::parse($t->created_at)->format('d/m/Y H:i') }}</td>
                    <td class="font-mono font-bold">{{ $t->trx_reference }}</td>
                    <td class="font-mono text-slate-500">{{ $t->ref_id ?: '-' }}</td>
                    <td>{{ $t->customer_name ?: 'Pelanggan Umum' }}</td>
                    <td style="text-transform: uppercase;">{{ str_replace('_', ' ', $t->payment_method ?: 'QRIS') }}</td>
                    <td class="text-right font-mono">Rp {{ number_format($t->gross_amount, 0, ',', '.') }}</td>
                    <td class="text-right font-mono">Rp {{ number_format($t->total_fee, 0, ',', '.') }}</td>
                    <td class="text-right font-mono font-bold" style="color: {{ $t->status === 'paid' ? '#059669' : '#0f172a' }};">
                        Rp {{ number_format($t->net_amount, 0, ',', '.') }}
                    </td>
                    <td class="text-center">
                        @if($t->status === 'paid')
                            <span class="badge badge-paid">Lunas</span>
                        @elseif($t->status === 'pending')
                            <span class="badge badge-pending">Pending</span>
                        @elseif($t->status === 'expired')
                            <span class="badge badge-expired">Expired</span>
                        @else
                            <span class="badge badge-failed">Gagal</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="10" class="text-center" style="padding: 20px; color: #94a3b8;">
                        Tidak ada riwayat transaksi pada filter periode ini.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        <div class="footer-left">
            Dokumen sah dihasilkan secara otomatis oleh sistem NODERA PAY Gateway Hub pada {{ now()->format('d F Y, H:i:s') }} WIB.
        </div>
        <div class="footer-right">
            Halaman 1 dari 1
        </div>
    </div>
</body>
</html>
