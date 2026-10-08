<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Tagihan Invoice — NODERA</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 15mm;
        }
        * {
            box-sizing: border-box;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background-color: #f8fafc;
            color: #0f172a;
            margin: 0;
            padding: 0;
            font-size: 11px;
            line-height: 1.5;
        }
        .toolbar {
            position: sticky;
            top: 0;
            z-index: 100;
            background: #ffffff;
            border-bottom: 1px solid #e2e8f0;
            padding: 12px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }
        .toolbar-title {
            font-weight: 700;
            font-size: 14px;
            color: #1e293b;
        }
        .toolbar-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            border: 1px solid transparent;
            text-decoration: none;
            transition: all 0.15s ease;
        }
        .btn-outline {
            background: #ffffff;
            border-color: #cbd5e1;
            color: #475569;
        }
        .btn-outline:hover {
            background: #f1f5f9;
            color: #0f172a;
        }
        .btn-primary {
            background: #2563eb;
            color: #ffffff;
        }
        .btn-primary:hover {
            background: #1d4ed8;
        }
        .page-container {
            max-width: 1050px;
            margin: 24px auto;
            background: #ffffff;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.04);
            border: 1px solid #e2e8f0;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 24px;
            border-bottom: 2px solid #0f172a;
            padding-bottom: 16px;
        }
        .header-table td {
            vertical-align: top;
            padding: 0;
        }
        .brand-name {
            font-size: 24px;
            font-weight: 900;
            color: #2563eb;
            letter-spacing: -0.5px;
            margin: 0;
        }
        .brand-subtitle {
            font-size: 12px;
            color: #64748b;
            font-weight: 500;
            margin-top: 2px;
        }
        .doc-title {
            font-size: 16px;
            font-weight: 800;
            color: #0f172a;
            text-align: right;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .doc-meta {
            font-size: 11px;
            color: #64748b;
            text-align: right;
            margin-top: 4px;
        }
        table.report-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            font-size: 11px;
        }
        table.report-table th {
            background: #f8fafc;
            border-top: 1px solid #e2e8f0;
            border-bottom: 2px solid #cbd5e1;
            padding: 8px 10px;
            font-weight: 700;
            color: #475569;
            text-align: left;
        }
        table.report-table td {
            border-bottom: 1px solid #f1f5f9;
            padding: 8px 10px;
            color: #334155;
        }
        table.report-table tr:nth-child(even) td {
            background: #fafafa;
        }
        .text-right {
            text-align: right;
        }
        .font-mono {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
        }
        .font-bold {
            font-weight: 700;
        }
        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
        }
        .badge-success {
            background: #dcfce7;
            color: #15803d;
        }
        .badge-warning {
            background: #fef3c7;
            color: #b45309;
        }
        .footer-note {
            margin-top: 36px;
            padding-top: 16px;
            border-top: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 10px;
            color: #94a3b8;
        }

        @media print {
            .toolbar {
                display: none !important;
            }
            body {
                background: #ffffff;
                padding: 0;
            }
            .page-container {
                margin: 0;
                padding: 0;
                box-shadow: none;
                border: none;
                max-width: 100%;
            }
            table.report-table tr {
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <div class="toolbar-title">Pratinjau Daftar Tagihan Invoice</div>
        <div class="toolbar-actions">
            <a href="javascript:window.close()" class="btn btn-outline">Tutup</a>
            <button onclick="window.print()" class="btn btn-primary">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
                Cetak / Simpan sebagai PDF
            </button>
        </div>
    </div>

    <div class="page-container">
        <table class="header-table">
            <tr>
                <td>
                    <h1 class="brand-name">NODERA BILLING</h1>
                    <div class="brand-subtitle">Laporan Daftar Tagihan & Rekapitulasi Pembayaran Pelanggan</div>
                </td>
                <td class="text-right">
                    <div class="doc-title">Daftar Invoice</div>
                    <div class="doc-meta">Dicetak: {{ date('d/m/Y H:i:s') }}</div>
                </td>
            </tr>
        </table>

        <table class="report-table">
            <thead>
                <tr>
                    <th>No. Invoice</th>
                    <th>ID Pelanggan</th>
                    <th>Nama Pelanggan</th>
                    <th>Nominal (Rp)</th>
                    <th>Jatuh Tempo</th>
                    <th>Status</th>
                    <th>Tanggal Bayar</th>
                    <th>Petugas / Metode</th>
                </tr>
            </thead>
            <tbody>
                @forelse($invoices as $inv)
                <tr>
                    <td class="font-mono font-bold">{{ $inv->invoice_number }}</td>
                    <td class="font-mono font-bold" style="color:#2563eb;">{{ $inv->customer ? ($inv->customer->code ?? $inv->customer->id) : ($inv->customer_id ?? '-') }}</td>
                    <td class="font-bold">{{ $inv->customer ? $inv->customer->name : ($inv->customer_name ?? '-') }}</td>
                    <td class="font-bold" style="color:#0f172a;">Rp {{ number_format($inv->amount, 0, ',', '.') }}</td>
                    <td>{{ $inv->due_date ? $inv->due_date->format('d/m/Y') : '-' }}</td>
                    <td>
                        @if($inv->paid)
                            <span class="badge badge-success">LUNAS</span>
                        @else
                            <span class="badge badge-warning">TERTUNDA</span>
                        @endif
                    </td>
                    <td>{{ $inv->paid_at ? $inv->paid_at->format('d/m/Y H:i') : '-' }}</td>
                    <td>{{ $inv->processed_by ?? '-' }} {{ $inv->payment_method ? '('.$inv->payment_method.')' : '' }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" style="text-align:center;color:#94a3b8;padding:16px;">Tidak ada data invoice ditemukan</td>
                </tr>
                @endforelse
            </tbody>
        </table>

        <div class="footer-note">
            <div>Total Invoice: {{ count($invoices) }} data</div>
            <div>Halaman 1 dari 1</div>
        </div>
    </div>

    <script>
        window.addEventListener('load', function () {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('auto_print')) {
                setTimeout(function () {
                    window.print();
                }, 300);
            }
        });
    </script>
</body>
</html>
