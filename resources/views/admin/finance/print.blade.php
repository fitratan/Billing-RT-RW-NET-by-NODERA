<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Keuangan</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Poppins', system-ui, -apple-system, sans-serif; font-size: 12px; color: #1e293b; padding: 40px; }
        .page { max-width: 800px; margin: 0 auto; }
        .top-bar { height: 4px; background: linear-gradient(90deg, #2563eb, #7c3aed); margin: 0 -40px 28px -40px; }
        .header { margin-bottom: 24px; }
        .header h2 { font-size: 20px; font-weight: 800; margin: 0; color: #0f172a; }
        .header p { font-size: 11px; color: #64748b; margin: 2px 0 0; }
        .stats { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px; margin-bottom: 24px; }
        .stat { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 16px; text-align: center; }
        .stat .lbl { font-size: 10px; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.8px; margin-bottom: 4px; }
        .stat .val { font-size: 18px; font-weight: 800; }
        .stat.green .val { color: #16a34a; }
        .stat.red .val { color: #dc2626; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th { font-size: 10px; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.8px; text-align: left; padding: 8px 12px; border-bottom: 2px solid #e2e8f0; background: none; }
        td { padding: 8px 12px; border-bottom: 1px solid #f1f5f9; font-size: 12px; }
        .text-right { text-align: right; }
        .section-title { font-size: 13px; font-weight: 700; color: #334155; margin: 20px 0 8px; padding-bottom: 6px; border-bottom: 1px solid #e2e8f0; }
        .footer { margin-top: 24px; padding-top: 12px; border-top: 1px solid #e2e8f0; text-align: center; font-size: 9px; color: #94a3b8; }
        .watermark { position: fixed; bottom: 0; left: 0; right: 0; text-align: center; font-size: 7px; color: #cbd5e1; padding: 6px; letter-spacing: 1px; text-transform: uppercase; }
        @media print {
            body { -webkit-print-color-adjust: exact; print-color-adjust: exact; padding: 20px; }
            .top-bar { margin: 0 -20px 20px -20px; }
        }
    </style>
</head>
<body>
    <div class="page">
        <div class="top-bar"></div>

        <div class="header">
            <h2>{{ session('tenant_name', 'Laporan Keuangan') }}</h2>
            <p>Periode: {{ \Carbon\Carbon::create($year, $month)->format('F Y') }}</p>
        </div>

        <!-- Summary Cards -->
        <div class="stats">
            <div class="stat green">
                <div class="lbl">Pemasukan</div>
                <div class="val">Rp{{ number_format($totalRevenue, 0, ',', '.') }}</div>
            </div>
            <div class="stat red">
                <div class="lbl">Pengeluaran</div>
                <div class="val">Rp{{ number_format($totalExpenses, 0, ',', '.') }}</div>
            </div>
            <div class="stat">
                <div class="lbl">Laba / Rugi</div>
                <div class="val" style="color:{{ $profitLoss >= 0 ? '#16a34a' : '#dc2626' }}">Rp{{ number_format($profitLoss, 0, ',', '.') }}</div>
            </div>
        </div>

        <!-- Revenue Detail -->
        <div class="section-title">Detail Pemasukan</div>
        @if($recentRevenue->count())
        <table>
            <thead>
                <tr><th>Pelanggan</th><th>Invoice</th><th class="text-right">Jumlah</th><th class="text-right">Tanggal</th></tr>
            </thead>
            <tbody>
                @foreach($recentRevenue as $inv)
                <tr>
                    <td>{{ $inv->customer_name }}</td>
                    <td>{{ $inv->invoice_number }}</td>
                    <td class="text-right">Rp{{ number_format($inv->amount, 0, ',', '.') }}</td>
                    <td class="text-right">{{ \Carbon\Carbon::parse($inv->paid_at)->format('d M Y') }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @else
        <p style="color:#94a3b8;text-align:center;padding:16px;">Tidak ada pemasukan bulan ini</p>
        @endif

        <!-- Expense Detail -->
        <div class="section-title">Detail Pengeluaran</div>
        @if($expenses->count())
        <table>
            <thead>
                <tr><th>Keterangan</th><th>Kategori</th><th class="text-right">Jumlah</th><th class="text-right">Tanggal</th></tr>
            </thead>
            <tbody>
                @foreach($expenses as $exp)
                <tr>
                    <td>{{ $exp->description }}</td>
                    <td>{{ $exp->category ?? '-' }}</td>
                    <td class="text-right">Rp{{ number_format($exp->amount, 0, ',', '.') }}</td>
                    <td class="text-right">{{ \Carbon\Carbon::parse($exp->date)->format('d M Y') }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @else
        <p style="color:#94a3b8;text-align:center;padding:16px;">Tidak ada pengeluaran bulan ini</p>
        @endif

        <div class="footer">
            <p style="margin:0;">Dicetak: {{ now()->format('d M Y H:i') }} — {{ session('tenant_name', 'NODERA') }}</p>
        </div>
    </div>

    <div class="watermark">NODERA by dgtlnetsolution.com</div>

    <script>window.onload = function() { setTimeout(function() { window.print(); }, 200); };</script>
</body>
</html>
