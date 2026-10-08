<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Riwayat Proses Settlement — {{ $merchant->merchant_code }}</title>
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
            margin-bottom: 12px;
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
            font-size: 13px;
            font-weight: 800;
            color: #0f172a;
            text-align: right;
            margin: 0;
            text-transform: uppercase;
        }
        .doc-meta {
            font-size: 8.5px;
            color: #64748b;
            text-align: right;
            margin: 2px 0 0 0;
        }

        /* ── Callout Box: Proses Settlement Otomatis ── */
        .callout-box {
            background: #eff6ff;
            border: 1px solid #bae6fd;
            border-left: 4px solid #0073C6;
            border-radius: 6px;
            padding: 8px 12px;
            margin-bottom: 12px;
        }
        .callout-title {
            font-size: 9.5px;
            font-weight: 800;
            color: #0073C6;
            margin-bottom: 3px;
        }
        .callout-desc {
            font-size: 8.5px;
            color: #1e3a8a;
            line-height: 1.45;
            margin: 0;
        }

        .metrics-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 6px;
            margin-bottom: 12px;
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
            font-size: 7.5px;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .metric-val {
            font-size: 11px;
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
            margin-bottom: 14px;
        }
        .data-table th {
            background-color: #0073C6;
            color: #ffffff;
            font-size: 8px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            padding: 6px 8px;
            text-align: left;
            border: 1px solid #0073C6;
        }
        .data-table td {
            padding: 5px 8px;
            font-size: 8px;
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
            font-size: 7px;
            font-weight: 700;
            text-transform: uppercase;
        }
        .badge-completed {
            background: #dcfce7;
            color: #15803d;
        }
        .badge-processing {
            background: #e0f2fe;
            color: #0369a1;
        }
        .badge-pending {
            background: #fef3c7;
            color: #b45309;
        }
        .badge-rejected {
            background: #ffe4e6;
            color: #be123c;
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
                <div class="doc-title">RIWAYAT PROSES SETTLEMENT</div>
                <div class="doc-meta">Periode: {{ $periodLabel }} &bull; Dicetak: {{ now()->format('d/m/Y H:i') }} WIB</div>
            </td>
        </tr>
    </table>

    <!-- Callout Box Keterangan Proses Settlement Otomatis -->
    <div class="callout-box">
        <div class="callout-title">Proses Settlement Otomatis</div>
        <p class="callout-desc">
            Dana transaksi akan masuk ke saldo tersedia setelah melewati jadwal proses settlement yang ditentukan sistem.<br>
            Rincian jadwal dan aturan settlement sudah diatur oleh sistem dan ditampilkan di sini sebagai riwayat Proses Settlement.
        </p>
    </div>

    <table class="metrics-table">
        <tr>
            <td class="metric-card">
                <div class="metric-label">Total Riwayat Settlement</div>
                <div class="metric-val">{{ count($withdrawals) }} Transaksi</div>
            </td>
            <td class="metric-card">
                <div class="metric-label">Total Pengajuan</div>
                <div class="metric-val">Rp {{ number_format($totalAmount, 0, ',', '.') }}</div>
            </td>
            <td class="metric-card">
                <div class="metric-label">Total Biaya Admin</div>
                <div class="metric-val">Rp {{ number_format($totalFee, 0, ',', '.') }}</div>
            </td>
            <td class="metric-card">
                <div class="metric-label">Total Dana Masuk (Settled)</div>
                <div class="metric-val emerald">Rp {{ number_format($totalNetCompleted, 0, ',', '.') }}</div>
            </td>
        </tr>
    </table>

    <table class="data-table">
        <thead>
            <tr>
                <th class="text-center" style="width: 25px;">No</th>
                <th style="width: 90px;">Tanggal Pengajuan</th>
                <th>Kode Settlement</th>
                <th>Bank Tujuan</th>
                <th>No. Rekening</th>
                <th>Atas Nama</th>
                <th class="text-right">Nominal</th>
                <th class="text-right">Biaya</th>
                <th class="text-right">Diterima</th>
                <th class="text-center" style="width: 60px;">Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse($withdrawals as $idx => $w)
                <tr>
                    <td class="text-center font-mono">{{ $idx + 1 }}</td>
                    <td class="font-mono">{{ \Carbon\Carbon::parse($w->created_at)->format('d/m/Y H:i') }}</td>
                    <td class="font-mono font-bold" style="color: #0073C6;">{{ $w->withdrawal_code }}</td>
                    <td>{{ $w->bank_name }}</td>
                    <td class="font-mono">{{ $w->bank_account_number }}</td>
                    <td>{{ $w->bank_account_name }}</td>
                    <td class="text-right font-mono">Rp {{ number_format($w->amount, 0, ',', '.') }}</td>
                    <td class="text-right font-mono">Rp {{ number_format($w->fee, 0, ',', '.') }}</td>
                    <td class="text-right font-mono font-bold" style="color: {{ $w->status === 'completed' ? '#059669' : '#0f172a' }};">
                        Rp {{ number_format($w->net_amount, 0, ',', '.') }}
                    </td>
                    <td class="text-center">
                        @if($w->status === 'completed')
                            <span class="badge badge-completed">Berhasil</span>
                        @elseif($w->status === 'processing')
                            <span class="badge badge-processing">Diproses</span>
                        @elseif($w->status === 'pending')
                            <span class="badge badge-pending">Pending</span>
                        @else
                            <span class="badge badge-rejected">Ditolak</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="10" class="text-center" style="padding: 20px; color: #94a3b8;">
                        Tidak ada riwayat proses settlement pada filter periode ini.
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
